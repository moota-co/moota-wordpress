<?php

namespace Moota\MootaSuperPlugin\Woocommerce\QRIS;

use DateTime;
use DateTimeZone;
use WC_Payment_Gateway;
use WC_Order;
use Exception;
use IntlDateFormatter;
use Moota\MootaSuperPlugin\Contracts\MootaTransaction;
use Moota\MootaSuperPlugin\PluginLoader;
use Throwable;

class QRISGateway extends WC_Payment_Gateway
{
    public $gateway = "QRIS";
    private $expiry_hours = 24;

    public function __construct()
    {
        $this->id = 'moota_qris_gateway';
        $this->method_title = "Moota " . $this->gateway;
        $this->method_description = 'Pembayaran via Moota ' . $this->gateway;
        $this->has_fields = true;

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');

        add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            [$this, 'process_admin_options']
        );
        // add_action(
        //     'woocommerce_update_options_payment_gateways_' . $this->id, 
        //     [$this, 'save_custom_settings']
        // );

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
            "enabled" => [
                'title' => 'Enable/Disable',
                'type' => 'checkbox',
                'label' => 'Aktifkan Pembayaran Moota ' . $this->gateway,
                'default' => 'yes'
            ],
            'title' => [
                'title' => 'Judul',
                'type' => 'text',
                'default' => "Moota " . $this->gateway . ' Payment Gateway',
                'desc_tip' => true,
            ],
            'description' => [
                'title' => 'Deskripsi',
                'type'  => 'text',
                'default' => 'Scan Code QR Berikut untuk melanjutkan Proses Pembayaran',
            ],
            'account'   => [
                'title' => "Pilih Akun {$this->gateway}",
                'type'  => "select",
                'description' => "Pilih Akun QRIS Moota.",
                'options' => $this->get_qris_gateway(),
                'desc_tip'    => true,
                'class'       => 'wc-enhanced-select',
            ],
            'account_holder' => [
                'title' => 'Moota QRIS Label',
                'type'  => 'text',
                'description' => 'Jika Tidak ingin menggunakan Atas Nama Akun QRIS-Mu, Gunakan Label Custom Ini untuk Mengganti Account Holder-mu.',
                'default'     => 'Moota'
            ],
            'payment_instructions' => [
                'title'       => 'Instruksi Pembayaran',
                'type'        => 'textarea',
                'description' => 'Kolom ini diisi untuk Mengatur Pesan Instruksi Pembayaran di halaman Order Received. Bisa juga Mengrender HTML.' .
                    "
				<div>Gunakan Replacer Berikut:</div>
				<div>Logo Bank             : <b>[bank_logo]</b> </div>
				<div>Atas Nama Bank        : <b>[account_holder]</b> </div>
				<div>Nama Bank             : <b>[bank_name]</b> </div>
				<div>Total Harga           : <b>[amount]</b> </div>
				<div>Kembali ke Toko       : <b>[shop_url]</b> </div>
                <div>Expire Order          : <b>[expire_at]</b> </div>
			",
                'default'     =>  "
                Harap untuk transfer dengan Scan Code QR [bank_name] Berikut Dengan Jumlah Rp[amount] <br>
                [bank_logo] <br>
                <div>Order akan Kadaluarsa dalam : [expire_at]</div>"
            ],
        ];
    }

    private function get_qris_gateway()
    {
        $banks = get_option('moota_list_accounts', []);
        $options = [];

        foreach ($banks as $bank) {
            // Pastikan field yang diperlukan ada dan bank_type adalah QRIS
            if (
                !isset(
                    $bank['bank_id'],
                    $bank['bank_type'],
                    $bank['username'],
                    $bank['account_number']
                ) ||
                strtolower($bank['bank_type']) !== 'qris'
            ) {
                continue;
            }

            // Format opsi
            $options[$bank['bank_id']] = sprintf(
                'QRIS - %s (%s)',
                $bank['username'],
                $bank['account_number']
            );
        }

        if (empty($options)) {
            return ['' => 'Tidak ada akun QRIS yang aktif'];
        }

        return array_merge(
            ['' => 'Pilih Akun QRIS'],
            $options
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
        $order_status = $order->get_status();
        $status_label = wc_get_order_status_name($order_status);

        foreach ($listSettings as $bank) {
            if ($bank['bank_id'] === $bankId) {
                $username = $bank['username'];
                break; // Keluar dari loop setelah menemukan bank yang sesuai
            }
        }
        ?>
<?php
        // Username
        echo '<tr class="moota-order-username-detail">';
        echo '<th scope="row" style="font-weight: 700;">' . __('Nama Penerima', 'textdomain') . '</th>';
        echo '<td>Moota - ' . $username . '</td>';
        echo '</tr>';
        // Payment Status
        echo '<tr class="payment_status">';
        echo '<th scope="row" style="font-weight: 700;">' . __('Payment Status', 'textdomain') . '</th>';
        echo '<td style="font-weight: 700;">' . $status_label . '</td>';
        echo '</tr>';
    }

    protected function is_bank_type_match($bankType)
    {
        // Cocokkan bank_type yang persis 'qris' (case-insensitive)
        return strcasecmp($bankType, 'qris') === 0;
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
            "",
            "",
            "",
            "",
            $this->gateway
        );
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

    public function payment_fields()
    {
        try {
            $selectedBankId = $this->get_option('account');
            $selectedBank = $this->get_selected_bank($selectedBankId);

        ?>
            <div class="moota-qris-details">
                <?php if (empty($selectedBank)): ?>
                    <p class="error">Akun QRIS belum dipilih di pengaturan</p>
                <?php else: ?>
                    <div class="flex items-center gap-4 mb-4">
                        <div>
                            <p class="font-semibold">Gunakan Pembayaran dengan <?= strtoupper($this->gateway) ?> dari Moota dan Winpay.</p>
                        </div>
                    </div>
                    <p class="text-sm">Pembayaran akan kadaluarsa dalam <?php echo get_option('woocommerce_hold_stock_minutes', []) / 60 ?> jam</p>
                <?php endif; ?>
            </div>
<?php
        } catch (Throwable $e) {
            PluginLoader::log_to_file("QRIS Error: " . $e->getMessage());
            echo '<p>Anda belum memilih Akun QRIS.</p>';
        }
    }

    public function validate_fields()
    {
        $selectedBankId = $this->get_option('account');

        if (empty($selectedBankId)) {
            wc_add_notice('Admin belum memilih akun QRIS di pengaturan', 'error');
            return false;
        }

        $selectedBank = $this->get_selected_bank($selectedBankId);

        if (!$selectedBank || !$this->is_bank_type_match($selectedBank['bank_type'])) {
            wc_add_notice('Akun QRIS tidak valid', 'error');
            return false;
        }

        return true;
    }

    public function order_instructions($order) {
        if (
            !$order instanceof WC_Order ||
            $order->get_payment_method() !== $this->id
        ) {
            return;
        }
        // Ambil data status order
        $listSettings = get_option('moota_list_accounts');

        $bankId = $order->get_meta('moota_bank_id');
        $username = '';

        foreach ($listSettings as $bank) {
            if ($bank['bank_id'] === $bankId) {
                $username = $bank['username'];
                break; // Keluar dari loop setelah menemukan bank yang sesuai
            }
        }

        // Ambil data lainnya
        $paymentInstruction = $this->get_option('payment_instructions');
        $expiredAt = $order->get_meta('moota_expire_at');
        $dateTime  = new DateTime($expiredAt);
        $dateTime->setTimezone(new DateTimeZone('Asia/Jakarta'));

        // Menggunakan IntlDateFormatter untuk format tanggal dalam bahasa Indonesia
        $formatter = new IntlDateFormatter(
            'id_ID', // Locale untuk bahasa Indonesia
            IntlDateFormatter::FULL, // Format tanggal
            IntlDateFormatter::NONE, // Format waktu
            'Asia/Jakarta', // Zona waktu
            IntlDateFormatter::GREGORIAN, // Kalender
            'EEEE, dd MMMM yyyy - HH:mm' // Format yang diinginkan
        );

        $formattedDate = $formatter->format($dateTime);
        // Mapping warna status
        $data = [
            'account_holder' => "<span class='font-bold'>" . $username . "</span>",
            'bank_logo'      => "<img src='" . $bank['icon'] . "'>",
            'bank_name'      => "<span class='font-bold'>" . strtoupper($this->gateway) . "</span>",
            'amount'         => "<span class='font-bold'>" . $order->get_total(),
            'shop_url'       => esc_url(wc_get_page_permalink('shop')),
            'expire_at'      => $formattedDate
        ];
        $rendered_content = self::render_payment_instructions($paymentInstruction, $data);
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
            <img src="<?= $order->get_meta('moota_qris_url') ?>" alt="" style="display: block; margin: 0 auto;">
                <?= html_entity_decode($rendered_content) ?>
            </td>
        </tr>
    </tfoot>
</table>
<?php endif; ?>
        <?php
    }

    public static function render_payment_instructions($content, $data = [])
    {
        // Daftar placeholder dan nilainya
        $placeholders = [
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

?>