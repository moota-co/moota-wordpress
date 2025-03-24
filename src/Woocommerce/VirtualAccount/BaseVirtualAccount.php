<?php

namespace Moota\MootaSuperPlugin\Woocommerce\VirtualAccount;

use DateTime;
use DateTimeZone;
use WC_Payment_Gateway;
use WC_Order;
use Exception;
use Moota\MootaSuperPlugin\Contracts\MootaTransaction;
use Moota\MootaSuperPlugin\PluginLoader;
use Throwable;

abstract class BaseVirtualAccount extends WC_Payment_Gateway
{
    public $bankCode;
    public $bankName;
    public $defaultAccountNumber;
    public $defaultAccountHolder;
    public $list_banks = [];

    public function __construct()
    {
        $this->id = 'moota_' . strtolower($this->bankCode) . '_virtual';
        $this->method_title = $this->bankName;
        $this->method_description = 'Pembayaran via ' . $this->bankName . " Virtual";
        $this->has_fields = true;

        $this->init_form_fields();
        $this->init_settings();

        add_action('admin_notices', function () {
            if (empty($this->get_account_options()) && $this->enabled === 'yes') {
                echo '<div class="notice notice-warning">';
                echo '<p>Belum ada rekening ' . $this->bankCode . ' yang aktif. ';
                echo '<a href="' . admin_url('admin.php?page=moota-settings') . '">';
                echo 'Refresh Daftar Bank</a></p>';
                echo '</div>';
            }
        });

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
                'default' => $this->bankName . ' Transfer',
                'desc_tip' => true,
            ],
            'description' => [
                'title' => 'Deskripsi',
                'type' => 'textarea',
                'default' => 'Transfer ke rekening ' . $this->bankName,
            ],
            'account' => [
                'title'       => "Pilih Akun " . strtoupper($this->bankCode),
                'type'        => 'select',
                'description' => 'Pilih Akun VA yang akan digunakan',
                'options'     => $this->get_account_options(),
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
				<div>Nomor Virtual Account : <b>[virtual_account]</b> </div>
				<div>Atas Nama Bank : <b>[account_holder]</b> </div>
				<div>Total Harga : <b>[amount]</b> </div>
				<div>Kembali ke Toko : <b>[shop_url]</b> </div>
                <div>Expire Order : <b>[expire_at]</b> </div>
			",
                'default'     =>  "
                Harap untuk transfer ke Nomor Virtual Account [bank_name] Berikut : <br><br>
                [virtual_account]. <br> <br>
                Dengan Jumlah Rp[amount] <br>
                
                <div>Order akan Kadaluarsa dalam : [expire_at]</div>"
            ],
            'admin_fee_amount' => [
                'title' => 'Nilai Biaya Admin',
                'type' => 'number',
                'default' => 0,
                'description' => sprintf(
                    '<div class="admin-fee-description" style="margin-top:8px;color:#666; line-height:1.6;">
                        <strong>📝 Contoh Perhitungan:</strong><br>
                        • <u>Biaya Tetap</u>: Total Rp18.000 + Biaya Rp2.000 = <strong>Rp20.000</strong><br>
                        • <u>Persentase</u> : Total Rp18.000 × %s%% = <strong>Rp%s</strong> → Total Akhir <strong>Rp21.600</strong>
                        
                        <div style="margin-top:10px; padding:8px; background:#f8f9fa; border-radius:4px; border-left:4px solid #2196f3;">
                            💡 <strong>Tips Penting:</strong><br>
                            • Untuk persentase, cukup masukkan <strong>angka saja</strong> (contoh: 20)<br>
                            • Tidak perlu menambahkan simbol <code>%%</code> atau karakter khusus lainnya
                        </div>
                    </div>',
                    '<span class="percentage-example">20</span>',
                    '<span class="result-example">3.600</span>'
                ),
                'custom_attributes' => array(
                    'min'  => 0,
                    'step' => 0.01
                )
            ],
            'admin_fee_type' => [
                'title' => 'Tipe Biaya Admin',
                'type' => 'select',
                'options' => [
                    'fixed' => 'Biaya Tetap',
                    'percent' => 'Persentase Total Belanja'
                ],
                'default' => 'fixed'
            ]
        ];
    }

    private function get_account_options()
    {
        $banks = get_option('moota_list_accounts', []);
        $options = [];

        foreach ($banks as $bank) {
            // Pastikan field yang diperlukan ada dan cocok dengan tipe bank
            if (
                !isset($bank['bank_id'], $bank['bank_type'], $bank['username']) ||
                !$this->is_bank_type_match($bank['bank_type'])
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
            ['' => sprintf('Pilih Akun %s', strtoupper($this->bankCode))],
            $options
        );
    }

    protected function is_bank_type_match($bankType)
    {
        // Cocokkan bank_type yang diakhiri VA
        return preg_match(
            '/^' . preg_quote($this->bankCode, '/') . 'VA$/i', // Akhiran VA
            $bankType
        );
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
        $formattedDate = $dateTime->format('d F Y - H:i');
        $order_status = $order->get_status();

        $listSettings = get_option('moota_list_accounts');
        $bankSettings = $this->get_option('payment_instructions');

        $bankId = $order->get_meta('moota_bank_id');
        $username = '';

        foreach ($listSettings as $bank) {
            if ($bank['bank_id'] === $bankId) {
                $username = $bank['username'];
                break; // Keluar dari loop setelah menemukan bank yang sesuai
            }
        }
        $data = [
            'virtual_account'=> "<span class='font-bold'>".$order->get_meta('moota_va_number')."</span>",
            'account_holder' => "<span class='font-bold'>" . $username . "</span>",
            'bank_logo'      => "<img src='" . $bank['icon'] . "'>",
            'bank_name'      => "<span class='font-bold'>" . strtoupper($this->bankCode) . "</span>",
            'amount'         => "<span class='font-bold'>" . $order->get_total(),
            'shop_url'       => esc_url(wc_get_page_permalink('shop')),
            'expire_at'      => $formattedDate
        ];

        $rendered_content = self::render_payment_instructions($bankSettings, $data);

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
                $('#copy-va').on('click', function() {
                    var btn = $(this);
                    var vaNumber = $('#va-number').text().trim();
                    console.log('VA Number: ' + vaNumber);

                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(vaNumber)
                            .then(function() {
                                console.log('Nomor VA berhasil disalin');
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
                        $temp.val(vaNumber).select();
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

    protected function get_available_banks()
    {
        $all_banks = get_option('moota_list_accounts', []);

        return array_filter($all_banks, function ($bank) {
            return $this->is_bank_type_match($bank['bank_type']);
        });
    }

    public function save_custom_settings()
    {
        $this->process_admin_options();

        // Simpan data tambahan jika diperlukan
        update_option(
            'moota_' . strtolower($this->bankCode) . '_settings',
            [
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
            $moota_settings = get_option('woocommerce_moota_' . $this->bankCode . '_virtual_settings');
            $banks = $this->get_available_banks();
            $selectedBank = null;


            foreach ($banks as $bank) {
                if ($bank['bank_id'] === array_get($moota_settings, 'account')) {
                    $selectedBank = $bank;
                    break;
                }
            }

        ?>
            <div class="moota-bank-details"> <!-- Hapus atribut name -->
                <?php if (empty($selectedBank)): ?>
                    <p class="error">Tidak ada Akun <?php echo $this->bankName; ?> Aktif yang ditemukan.</p>
                <?php else: ?>
                    <input
                        type="hidden"
                        name="moota_selected_bank"
                        value="<?php echo esc_attr($moota_settings); ?>">
                    <p>Gunakan Pembayaran dengan Virtual Account <?= strtoupper($this->bankCode) ?> dari Moota dan Winpay.</p>
                <?php endif; ?>
            </div>
        <?php

        } catch (Throwable $e) {
            PluginLoader::log_to_file(
                "Error fetching banks: " . $e->getMessage()
            );
            echo '<p>Anda Belum memilih Akun VA.</p>';
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
        $all_banks = get_option('moota_list_accounts', []);

        foreach ($all_banks as $bank) {
            if ($bank['bank_id'] === $bank_id) {
                return $bank;
            }
        }

        return null;
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        $selectedBankId = $this->get_option('account');
        $selectedBank = $this->get_selected_bank($selectedBankId);

        // Validasi bank yang dipilih
        if (!$selectedBank || !$this->is_bank_type_match($selectedBank['bank_type'])) {
            throw new Exception('Rekening tidak valid atau tidak sesuai dengan tipe gateway');
        }

        // Simpan data bank ke order
        $order->update_meta_data('moota_bank_id', $selectedBankId);
        $order->update_meta_data('moota_bank_details', $selectedBank);

        return MootaTransaction::request(
            $order_id,
            $selectedBankId,
            "",
            $this->get_option('admin_fee_type'),
            $this->get_option('admin_fee_amount'),
            "",
            "",
            $this->bankCode
        );
    }

    public function order_details($order)
    {
        if (
            !$order instanceof WC_Order ||
            $order->get_payment_method() !== $this->id
        ) {
            return;
        }

        $listSettings = get_option('moota_list_accounts');
        $bankId = $order->get_meta('moota_bank_id');
        $username = '';
        $va = $order->get_meta('moota_va_number');
        $order_status = $order->get_status();
        $status_label = wc_get_order_status_name($order_status);

        foreach ($listSettings as $bank) {
            if ($bank['bank_id'] === $bankId) {
                $username = $bank['username'];
                break; // Keluar dari loop setelah menemukan bank yang sesuai
            }
        }

        $bankSettings = get_option('woocommerce_moota_' . strtolower($this->bankCode) . '_transfer_settings');
        $order_status = $order->get_status();

        // Tampilkan username
        echo '<tr class="moota-order-username-detail">';
        echo '<th scope="row" style="font-weight: 700;">' . __('Nama Penerima', 'textdomain') . '</th>';
        echo '<td>' . $username . '</td>';
        echo '</tr>';
        echo '<tr class="moota-order-number-account-detail">';
        echo '<th scope="row" style="font-weight: 700;">' . __('Nomor VA', 'textdomain') . '</th>';
        echo '<td style="align-items: center;">';

        // Cek status order
        if ($order->get_status() === 'cancelled') {
            // Jika status canceled, tampilkan pesan kadaluarsa
            echo '<span class="font-bold">Nomor VA ini telah Kadaluarsa</span>';
        } else {
            // Jika tidak canceled, tampilkan nomor VA
            echo '<span id="va-number" style="display: none;">' . $va . '</span>';
            echo $va;
        }

        echo '<button id="copy-va" class="ml-2 p-1 rounded-md bg-gray-200 hover:bg-gray-300 focus:outline-none">
    <svg class="w-6 h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
        <path fill-rule="evenodd" d="M18 3a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-1V9a4 4 0 0 0-4-4h-3a1.99 1.99 0 0 0-1 .267V5a2 2 0 0 1 2-2h7Z" clip-rule="evenodd" />
        <path fill-rule="evenodd" d="M8 7.054V11H4.2a2 2 0 0 1 .281-.432l2.46-2.87A2 2 0 0 1 8 7.054ZM10 7v4a2 2 0 0 1-2 2H4v6a2 2 0 0 0 2 2h7a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3Z" clip-rule="evenodd" />
    </svg>
</button>';
        echo '</td>';
        echo '</tr>';
        ?>
<?php
    }

    public static function render_payment_instructions($content, $data = [])
    {
        // Daftar placeholder dan nilainya
        $placeholders = [
            '[virtual_account]' => isset($data['virtual_account']) ? $data['virtual_account'] : '',
            '[account_holder]' => isset($data['account_holder']) ? $data['account_holder'] : '',
            '[bank_logo]'      => isset($data['bank_logo'])      ? $data['bank_logo']      : '',
            '[bank_name]'      => isset($data['bank_name'])      ? $data['bank_name']      : '',
            '[amount]'         => isset($data['amount'])         ? $data['amount']         : '',
            '[shop_url]'       => isset($data['shop_url'])       ? $data['shop_url']       : '',
            '[expire_at]'      => isset($data['expire_at'])      ? $data['expire_at']      : '',
        ];

        // Ganti semua placeholder dengan nilainya
        return str_replace(array_keys($placeholders), array_values($placeholders), $content);
    }
}
