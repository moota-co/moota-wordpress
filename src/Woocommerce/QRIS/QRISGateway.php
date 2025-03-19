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
            'woocommerce_order_details_after_order_table',
            [$this, 'order_details'],
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
            ]
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
        // Ambil data status order
        $order_status = $order->get_status();
        $status_label = wc_get_order_status_name($order_status);

        // Mapping warna status
        $status_colors = [
            'pending'    => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-800'],
            'processing' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-800'],
            'on-hold'    => ['bg' => 'bg-purple-100', 'text' => 'text-purple-800'],
            'completed'  => ['bg' => 'bg-green-100', 'text' => 'text-green-800'],
            'cancelled'  => ['bg' => 'bg-red-100', 'text' => 'text-red-800'],
            'refunded'   => ['bg' => 'bg-pink-100', 'text' => 'text-pink-800'],
            'failed'     => ['bg' => 'bg-gray-100', 'text' => 'text-gray-800'],
        ];

        $current_color = $status_colors[$order_status] ?? ['bg' => 'bg-gray-100', 'text' => 'text-gray-800'];

        // Ambil data lainnya
        $settings = $this->get_option('description');
        $account_holder = $this->get_option('account_holder');
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
?>
        <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
        <div class="moota-payment-instructions mt-8 bg-white rounded-xl lg:w-1/2 mx-auto border shadow-lg overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-500 p-6">
                <div class="flex items-center space-x-4">
                    <div class="bg-white/10 p-3 rounded-lg">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <div>
                        <h3 style="line-height: 2rem; font-size: 1.5rem; font-weight: 700; color: #ffffff;">Pembayaran QRIS</h3>
                        <p style="color: rgb(219, 234, 254); margin-top: 4px; font-size: 14px; line-height: 20px;">Scan QR Code berikut untuk menyelesaikan pembayaran</p>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="grid gap-8 p-6">
                <!-- QR Code Section -->
                <div class="space-y-6">
                    <div class="text-center">
                        <div class="relative inline-block">
                            <?php if ($order_status !== 'cancelled' && ($qris_url = $order->get_meta('moota_qris_url'))): ?>
                                <div class="p-4 bg-white rounded-xl border-2 border-dashed border-blue-100">
                                    <img
                                        src="<?php echo esc_url($qris_url) ?>"
                                        alt="QR Code Pembayaran"
                                        class="w-64 h-64 object-contain mx-auto hover:scale-105 transition-transform duration-200">
                                </div>
                            <?php else: ?>
                                <div class="p-8 bg-gray-50 rounded-xl text-center">
                                    <div class="text-gray-400 mb-4">
                                        <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <p class="text-gray-500 text-sm">
                                        <?php echo $order_status === 'cancelled'
                                            ? 'QR sudah Kadaluarsa'
                                            : 'QR Code tidak tersedia' ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Payment Details & Actions -->
                <div class="space-y-6">
                    <!-- Amount Section -->
                    <div class="bg-blue-50 rounded-lg p-5">
                        <!-- Merchant Info -->
                        <div class="space-y-4">
                            <div class="flex items-start space-x-4">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                        </svg>
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <h4 class="font-bold text-gray-900"><?php echo esc_html($this->gateway) ?></h4>
                                    <div class="text-sm text-gray-600">
                                        <p class="flex items-center">
                                            <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                            Nama Penerima: <span class="font-medium ml-1"><?php echo esc_html($account_holder) ?></span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <dl class="space-y-3">
                            <div class="flex justify-between items-center">
                                <dt class="text-gray-600">Jumlah Tagihan</dt>
                                <dd class="text-2xl font-bold text-blue-600">
                                    <?php echo $order->get_formatted_order_total() ?>
                                </dd>
                            </div>
                            <div class="flex justify-between items-center">
                                <dt class="text-gray-600">Status</dt>
                                <dd class="px-3 py-1 rounded-full <?php echo $current_color['bg']; ?> <?php echo $current_color['text']; ?> text-sm font-medium">
                                    <?php echo esc_html($status_label); ?>
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Expire Time -->
                    <?php if ($order_status !== "completed"): ?>
                        <div class="bg-red-50 border-l-4 border-red-400 p-4 rounded-lg">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-red-700">
                                        Batas waktu pembayaran:
                                        <span class="font-semibold"><?php echo $formattedDate; ?></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Important Notes or Thank You Message -->
                    <?php if ($order_status === "completed"): ?>
                        <div class="bg-green-50 border-l-4 border-green-400 p-4 rounded-lg">
                            <div class="flex">
                                <svg class="flex-shrink-0 w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M6 10l2 2 6-6 1.5 1.5-7.5 7.5-3.5-3.5L6 10z" clip-rule="evenodd" />
                                </svg>
                                <div class="ml-3">
                                    <h4 class="text-sm font-medium text-green-800">Terima Kasih!</h4>
                                    <div class="mt-2 text-sm text-green-700">
                                        <p>Terima kasih telah melakukan pemesanan! Kami berharap dapat melayani Anda lagi di masa depan.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Action Buttons -->
            <div class="mt-6 grid grid-cols-1 items-center gap-4" style="justify-items: stretch;">
                <?php if ($order->get_status() != "cancelled" && $order->get_status() != "completed"): ?>
                    <!-- Tombol Cek Status Pembayaran -->
                    <a href="javascript:void(0)"
                        onclick="window.location.reload()"
                        style="display: inline-flex; 
              align-items: center; 
              justify-content: center; 
              width: 100%;
              padding: 12px; 
              background-color: #3b82f6; 
              color: white; 
              border-radius: 0.5rem; 
              box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06); 
              text-decoration: none; 
              transition: background-color 0.2s;
              cursor: pointer;
              margin: 4px;">
                        <svg style="width: 1.25rem; height: 1.25rem; margin-right: 0.5rem;"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Cek Status Pembayaran
                    </a>
                <?php endif; ?>
                <!-- Tombol Kembali ke Toko -->
                <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>"
                    style="display: inline-flex; 
              align-items: center; 
              justify-content: center; 
              width: 100%;
              padding: 8px 16px; 
              box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
              font-weight: 500; 
              border-radius: 0.375rem; 
              color: black; 
              text-decoration: none; 
              transition: background-color 0.2s, color 0.2s;
              margin: 4px;
              border-bottom: 1px solid currentColor;">
                    <svg style="width: 1.25rem; height: 1.25rem; margin-right: 0.5rem;"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    Kembali ke Toko
                </a>
            </div>
                </div>
            </div>
        </div>
        <?php
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
}

?>