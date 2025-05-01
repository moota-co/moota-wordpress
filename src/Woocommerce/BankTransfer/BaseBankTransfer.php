<?php

namespace Moota\MootaSuperPlugin\Woocommerce\BankTransfer;

use DateTime;
use DateTimeZone;
use WC_Payment_Gateway;
use WC_Order;
use Exception;
use Moota\MootaSuperPlugin\Contracts\MootaTransaction;
use Moota\MootaSuperPlugin\PluginLoader;
use Throwable;

abstract class BaseBankTransfer extends WC_Payment_Gateway
{
    public $bankCode;
    public $bankName;
    public $defaultAccountNumber;
    public $defaultAccountHolder;
    public $list_banks = [];

    public function __construct()
    {
        $this->id = 'moota_' . strtolower($this->bankCode) . '_transfer';
        $this->method_title = $this->bankName;
        $this->method_description = 'Pembayaran via ' . $this->bankName;
        $this->has_fields = true;

        $this->init_form_fields();
        $this->init_settings();


        add_action('admin_notices', function () {
            if (empty($this->get_bank_options()) && $this->enabled === 'yes') {
                echo '<div class="notice notice-warning">';
                echo '<p>Belum ada rekening ' . $this->bankCode . ' yang aktif. ';
                echo '<a href="' . admin_url('admin.php?page=moota-settings') . '">';
                echo 'Refresh Daftar Bank</a></p>';
                echo '</div>';
            }
        });

        add_action('wp_enqueue_scripts', [$this, 'my_plugin_enqueue_scripts']);

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');

        add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            [$this, 'process_admin_options']
        );
        add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            [$this, 'save_custom_settings']
        );

        add_action(
            'woocommerce_order_details_after_order_table_items',
            [$this, 'order_details'],
            10,
            1
        );

        add_action(
            'woocommerce_order_details_after_order_table',
            [$this, 'order_instructions'],
            10,
            1
        );
    }

    public function init_form_fields()
    {
        $this->form_fields = [
            'enabled' => [
                'title' => 'Enable/Disable',
                'type' => 'checkbox',
                'label' => 'Aktifkan Pembayaran ' . $this->bankName,
                'default' => 'yes'
            ],
            'title' => [
                'title' => 'Judul',
                'type' => 'text',
                'default' => $this->bankName . "Transfer",
                'desc_tip' => true,
            ],
            'description' => [
                'title' => 'Deskripsi',
                'type' => 'textarea',
                'default' => 'Transfer ke rekening ' . $this->bankName,
            ],
            'account' => [
                'title'       => "Pilih Akun {$this->bankCode}",
                'type'        => 'select',
                'description' => 'Pilih rekening bank yang akan digunakan',
                'options'     => $this->get_bank_options(),
                'desc_tip'    => true,
                'class'       => 'wc-enhanced-select',
            ],
            'payment_instructions' => [
                'title'       => 'Instruksi Pembayaran',
                'type'        => 'textarea',
                'description' => 'Kolom ini diisi untuk Mengatur Pesan Instruksi Pembayaran di halaman Order Received. Bisa juga Mengrender HTML.' . 
                "
				<div>Gunakan Replacer Berikut:</div>
				<div>Logo Bank : <b>[bank_logo]</b> </div>
				<div>Nama Bank : <b>[bank_name]</b> </div>
				<div>Nomor Rekening : <b>[account_number]</b> </div>
				<div>Atas Nama Bank : <b>[account_holder]</b> </div>
				<div>Kode Unik : <b>[unique_code]</b> </div>
				<div>Total Harga : <b>[amount]</b> </div>
				<div>Kembali ke Toko : <b>[shop_url]</b> </div>
			",
                'default'     => "
                Harap untuk transfer sesuai dengan jumlah yang sudah ditentukan sampai 3 digit terakhir [unique_code].<br>
                
                Transfer Ke Bank [bank_name] <br> <br>
                [bank_logo] <br>
                [account_number] A/n [account_holder]"
            ],
            'enable_unique_code' => [
                'title' => 'Kode Unik',
                'type' => 'checkbox',
                'label' => 'Aktifkan Kode Unik',
                'default' => 'yes'
            ],
            'unique_code_start' => [
                'title' => 'Range Awal Kode Unik',
                'type' => 'number',
                'default' => 1,
                'custom_attributes' => [
                    'min' => 1,
                    'max' => 999
                ]
            ],
            'unique_code_end' => [
                'title' => 'Range Akhir Kode Unik',
                'type' => 'number',
                'default' => 999,
                'custom_attributes' => [
                    'min' => 1,
                    'max' => 999
                ]
            ],
            'unique_code_type' => [
                'title' => 'Tipe Kode Unik',
                'type' => 'select',
                'options' => [
                    'increase' => 'Tambahkan ke Total',
                    'decrease' => 'Kurangi dari Total'
                ],
                'default' => 'increase'
            ]
        ];
    }

    private function get_bank_options()
    {
        $banks = get_option('moota_list_banks', []);
        $options = [];

        foreach ($banks as $bank) {
            if (
                !isset($bank['bank_id'], $bank['bank_type'], $bank['username'], $bank['account_number']) ||
                !$this->is_bank_type_match($bank['bank_type']) ||
                preg_match('/va$/i', $bank['bank_type']) // Pastikan tidak ada VA
            ) {
                continue;
            }

            // Format the option
            $options[$bank['bank_id']] = sprintf(
                '%s - %s / %s ~ %s',
                strtoupper($this->bankCode),
                $bank['username'],
                $bank['account_number'],
                $bank['bank_type']
            );
        }

        if (empty($options)) {
            return ['' => sprintf('Tidak ada rekening %s yang aktif', $this->bankCode)];
        }

        return array_merge(
            ['' => sprintf('Pilih Rekening %s', $this->bankCode)],
            $options
        );
    }

    private function is_bank_type_match($bankType)
    {
        // Cocokkan jika bankType mengandung kode bank, kecuali yang diakhiri "VA"
        return preg_match(
            '/' . preg_quote($this->bankCode, '/') . '(?!VA$)/i',
            strtolower($bankType)
        );
    }
    

    protected function get_available_banks()
    {
        $all_banks = get_option('moota_list_banks', []);

        return array_filter($all_banks, function ($bank) {
            return $this->is_bank_type_match($bank['bank_type']) &&
                !preg_match('/va$/i', $bank['bank_type']);
        });
    }

    public function save_custom_settings()
    {
        $this->process_admin_options();

        // Simpan data tambahan jika diperlukan
        update_option(
            'moota_' . strtolower($this->bankCode) . '_settings',
            [
                'account_number' => $this->get_option('account_number'),
                'account_holder' => $this->get_option('account_holder')
            ]
        );
    }

    protected function get_bank_type()
    {
        return strtolower($this->bankCode);
    }

    public function payment_fields()
    {
        try {
            $moota_settings = get_option('woocommerce_moota_' . $this->get_bank_type() . '_transfer_settings');
            $banks = $this->get_available_banks();
            $selectedBank = null;

            // var_dump(array_get($moota_settings, 'account'));
            // die();

            foreach ($banks as $bank) {
                if ($bank['bank_id'] === array_get($moota_settings, 'account')) {
                    $selectedBank = $bank;
                    break;
                }
            }

?>
            <div class="moota-bank-details"> <!-- Hapus atribut name -->

                <?php if (empty($selectedBank)): ?>
                    <p class="error">Kami belum memiliki Metode Transfer untuk <?php echo $this->bankName; ?>.</p>
                <?php else: ?>
                    <input
                        type="hidden"
                        name="moota_selected_bank"
                        value="<?php echo esc_attr($moota_settings); ?>">
                    <p>Gunakan Pembayaran dengan Transfer ke Bank <?= strtoupper($this->bankCode) ?> dari Moota.</p>
                <?php endif; ?>
            </div>
        <?php

        } catch (Throwable $e) {
            PluginLoader::log_to_file(
                "Error fetching banks: " . $e->getMessage()
            );
            echo '<p>Anda Belum memilih Akun Bank</p>';
        }
    }

    public function validate_fields()
    {
        $selectedBankId = $this->get_option('account'); // Ambil dari settings

        if (empty($selectedBankId)) {
            wc_add_notice('Admin belum memilih rekening di pengaturan', 'error');
            return false;
        }

        $selectedBank = $this->get_selected_bank($selectedBankId);

        if (!$selectedBank) {
            wc_add_notice('Rekening bank tidak valid', 'error');
            return false;
        }

        return true;
    }

    private function get_selected_bank($bank_id)
    {
        $all_banks = get_option('moota_list_banks', []);

        foreach ($all_banks as $bank) {
            if ($bank['bank_id'] === $bank_id) {
                return $bank;
            }
        }

        return null;
    }

    public function order_details($order)
    {
        if (
            !$order instanceof WC_Order ||
            $order->get_payment_method() !== $this->id
        ) {
            return;
        }

        $listSettings = get_option('moota_list_banks');
        $bankId = $order->get_meta('moota_bank_id');
        $username = '';

        foreach ($listSettings as $bank) {
            if ($bank['bank_id'] === $bankId) {
                $username = $bank['atas_nama'];
                break; // Keluar dari loop setelah menemukan bank yang sesuai
            }
        }
        $order_status = $order->get_status();
        $status_label = wc_get_order_status_name($order_status);

        // Tampilkan username
        echo '<tr class="moota-order-username-detail">';
        echo '<th scope="row" style="font-weight: 700;">' . __('Nama Penerima', 'textdomain') . '</th>';
        echo '<td>' . $username . '</td>';
        echo '</tr>';
        // Nomor Rekening
        echo '<tr class="moota-order-number-account-detail">';
        echo '<th scope="row" style="font-weight: 700;">' . __('Nomor Rekening', 'textdomain') . '</th>';
        echo '<td style=" align-items: center;">
        <span id="account_number" style="display: none;">' . $bank['account_number'] . '</span>
        ' . $bank['account_number'] . '
        <button id="copy-number" class="ml-2 p-1 rounded-md bg-gray-200 hover:bg-gray-300 focus:outline-none">
        <svg class="w-6 h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
            <path fill-rule="evenodd" d="M18 3a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-1V9a4 4 0 0 0-4-4h-3a1.99 1.99 0 0 0-1 .267V5a2 2 0 0 1 2-2h7Z" clip-rule="evenodd" />
            <path fill-rule="evenodd" d="M8 7.054V11H4.2a2 2 0 0 1 .281-.432l2.46-2.87A2 2 0 0 1 8 7.054ZM10 7v4a2 2 0 0 1-2 2H4v6a2 2 0 0 0 2 2h7a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3Z" clip-rule="evenodd" />
        </svg>
    </button> </td>';
        echo '</tr>'
        ?>
    <?php
        // Payment Status
        echo '<tr class="payment_status">';
        echo '<th scope="row" style="font-weight: 700;">' . __('Payment Status', 'textdomain') . '</th>';
        echo '<td style="font-weight: 700;">' . $status_label . '</td>';
        echo '</tr>';
    }

    public function process_payment($order_id)
{
    $order = wc_get_order($order_id);
    $selectedBankId = $this->get_option('account');
    $selectedBank = $this->get_selected_bank($selectedBankId);
    $moota_settings = get_option('moota_settings', []);

    // Validasi bank yang dipilih
    if (preg_match('/va$/i', $selectedBank['bank_type'])) {
        throw new Exception('Rekening Virtual Account tidak bisa digunakan untuk Bank Transfer');
    }

    // Ambil redirect setting dari admin
    $failed_option  = array_get($moota_settings, 'moota_failed_redirect_url');
    $pending_option = array_get($moota_settings, 'moota_pending_redirect_url');
    $success_option = array_get($moota_settings, 'moota_success_redirect_url');

    // Ambil referer jika tersedia
    $referer = $_SERVER['HTTP_REFERER'] ?? home_url();

    // URL detail produk (misalnya produk pertama dari order)
    $items = $order->get_items();

    if (count($items) > 1) {
        // Redirect ke halaman shop jika lebih dari 1 produk
        $product_url = wc_get_page_permalink('shop');
    } else {
        // Redirect ke halaman produk jika hanya 1
        $first_product = reset($items);
        $product_url = get_permalink($first_product->get_product_id());
    }
    $first_product = reset($items);

    // Mapping setting ke URL dengan if-else
    if ($failed_option === 'last_visited') {
        $failed_redirect = $referer;
    } elseif ($failed_option === 'Detail Produk') {
        $failed_redirect = $product_url;
    } elseif ($failed_option === 'thanks_page') {
        $failed_redirect = $order->get_checkout_order_received_url();
    } else {
        throw new Exception('Pilihan redirect gagal tidak valid');
    }

    if ($pending_option === 'last_visited') {
        $pending_redirect = $referer;
    } elseif ($pending_option === 'Detail Produk') {
        $pending_redirect = $product_url;
    } elseif ($pending_option === 'thanks_page') {
        $pending_redirect = $order->get_checkout_order_received_url();
    } else {
        throw new Exception('Pilihan redirect pending tidak valid');
    }

    if ($success_option === 'last_visited') {
        $success_redirect = $referer;
    } elseif ($success_option === 'Detail Produk') {
        $success_redirect = $product_url;
    } elseif ($success_option === 'thanks_page') {
        $success_redirect = $order->get_checkout_order_received_url();
    } else {
        throw new Exception('Pilihan redirect sukses tidak valid');
    }

    return MootaTransaction::request(
        !empty($failed_redirect) ? $failed_redirect : self::get_return_url($order),
        !empty($pending_redirect) ? $pending_redirect : self::get_return_url($order),
        !empty($success_redirect) ? $success_redirect : self::get_return_url($order),
        $order_id,
        $selectedBankId,
        $this->get_option('enable_unique_code'),
        "",
        "",
        $this->get_option('unique_code_start'),
        $this->get_option('unique_code_end'),
        $this->bankCode
    );
}


    public static function render_payment_instructions( $content, $data = [] ) {
        // Daftar placeholder dan nilainya
        $placeholders = [
            '[account_number]' => isset( $data['account_number'] ) ? $data['account_number'] : '',
            '[bank_logo]'      => isset( $data['bank_logo'] )      ? $data['bank_logo']      : '',
            '[unique_code]'    => isset( $data['unique_code'] )    ? $data['unique_code']    : '',
            '[account_holder]' => isset( $data['account_holder'] ) ? $data['account_holder'] : '',
            '[bank_name]'      => isset( $data['bank_name'] )      ? $data['bank_name']      : '',
            '[amount]'         => isset( $data['amount'] )         ? $data['amount']         : '',
            '[shop_url]'       => isset( $data['shop_url'] )       ? $data['shop_url']       : '',
        ];
    
        // Ganti semua placeholder dengan nilainya
        return str_replace( array_keys( $placeholders ), array_values( $placeholders ), $content );
    }
    

    public function order_instructions($order)
    {
        if (
            !$order instanceof WC_Order ||
            $order->get_payment_method() !== $this->id
        ) {
            return;
        }

        $expiredAt = $order->get_meta('moota_expire_at');
        $dateTime  = new DateTime($expiredAt);
        $dateTime->setTimezone(new DateTimeZone('Asia/Jakarta'));

        $listSettings = get_option('moota_list_banks');
        $bankSettings = $this->get_option('payment_instructions');

        $bankId = $order->get_meta('moota_bank_id');

        foreach ($listSettings as $bank) {
            if ($bank['bank_id'] === $bankId) {
                break; // Keluar dari loop setelah menemukan bank yang sesuai
            }
        }
        $data = [
            'account_number' => $bank['account_number'],
            'account_holder' => $bank['atas_nama'],
            'bank_logo'      => "<img src='".$bank['icon']."'>",
            'unique_code'    => "<span class='font-bold'>".$order->get_meta('moota_unique_code')."</span>",
            'bank_name'      => $this->bankCode,
            'amount'         => $order->get_total(),
            'shop_url'       => esc_url( wc_get_page_permalink( 'shop' ) ),
        ];

        $rendered_content = self::render_payment_instructions( $bankSettings, $data );

    ?>
       <?php if ($order->get_status() != 'cancelled') : ?>
    <table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
    <tfoot>
        <tr style="border: none;">
            <th colspan="2" style="border: none; padding-bottom: 0;">
                <h3 class="font-bold">Instruksi Pembayaran</h3>
            </th>
        </tr>
        <tr style="border: none;">
            <td colspan="2" style="border: none; padding-top: 0;">
                <?= html_entity_decode($rendered_content) ?>
            </td>
        </tr>
    </tfoot>
</table>
<?php endif; ?>
        <script>
            jQuery(document).ready(function($) {
                $('#copy-number').on('click', function() {
                    var btn = $(this);
                    var account_number = $('#account_number').text().trim();
                    console.log('Nomor Rekening: ' + account_number);

                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(account_number)
                            .then(function() {
                                console.log('Nomor Rekening berhasil disalin');
                                $('#copy-message').removeClass('hidden').fadeIn().delay(2000).fadeOut(); // Tampilkan pesan
                                btn.find('svg').css('color', 'green');
                                setTimeout(function() {
                                    btn.find('svg').css('color', '');
                                }, 1000);
                            })
                            .catch(function(err) {
                                console.error('Gagal menyalin: ', err);
                            });
                    } else {
                        // Fallback untuk browser yang tidak mendukung Clipboard API
                        var $temp = $('<input>');
                        $('body').append($temp);
                        $temp.val(account_number).select();
                        document.execCommand('copy');
                        $temp.remove();
                        $('#copy-message').removeClass('hidden').fadeIn().delay(2000).fadeOut(); // Tampilkan pesan
                        btn.find('svg').css('color', 'green');
                        setTimeout(function() {
                            btn.find('svg').css('color', '');
                        }, 1000);
                    }
                });
            });
        </script>
<?php
    }

    public function my_plugin_enqueue_scripts() {
        // Memastikan jQuery sudah dimuat
        wp_enqueue_script('jquery');
    
        // Memuat file JavaScript kustom
        wp_enqueue_script('my-plugin-icon-js', plugins_url('assets/js/icon.js', MOOTA_FULL_PATH), array('jquery'), null, true);
    
        // Mengirimkan data PHP ke JavaScript
        wp_localize_script('my-plugin-icon-js', 'myPluginData', array(
            'bankCode' => $this->bankCode, // Pastikan $this->bankCode tersedia di sini
            'imageUrl' => plugins_url("assets/img/logo/{$this->bankCode}/{$this->bankCode}.png", MOOTA_FULL_PATH) // Path gambar
        ));
    }

    
}
