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
            'woocommerce_order_details_after_order_table',
            [$this, 'order_details'],
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
            'account_holder' => [
                'title' => 'Bank Label',
                'type' => 'text'
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

    public function order_details($order)
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

?>
        <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
        <div class="moota-payment-instructions border lg:w-1/2 items-center mx-auto mt-8 p-6 bg-white rounded-lg shadow-md">
            <!-- Header -->
            <div class="border-b pb-4 mb-6">
                <h3 class="text-xl font-bold text-gray-800 flex items-center">
                    <svg class="w-6 h-6 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    Instruksi Pembayaran Virtual Account
                </h3>
                <p class="text-sm text-gray-600 mt-1">Simpan bukti transfer sebagai tanda pembayaran</p>
            </div>

            <!-- Content Grid -->
            <div class="grid grid-cols-1 gap-6">
                <!-- Bank Details -->
                <div class="space-y-4">
                    <div class="flex items-center space-x-4">
                        <div class="flex-shrink-0">
                            <img src="<?php echo esc_url($order->get_meta('moota_icon_url')) ?>"
                                alt="Bank Logo"
                                class="w-16 h-16 object-contain">
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-800"><?php echo esc_html($this->bankName) ?></h4>
                            <p class="text-sm text-gray-600">Kode Bank: <?php echo esc_html(strtoupper($this->bankCode)) ?></p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <?php if ($order->get_status() != "cancelled"): ?>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600 flex-1">Nomor VA</span>
                                <div class="flex items-center">
                                    <span class="font-medium text-gray-800" id="va-number">
                                        <?php echo $order->get_meta('moota_va_number') ?>
                                    </span>
                                    <button id="copy-va" class="ml-2 p-1 rounded-md bg-gray-200 hover:bg-gray-300 focus:outline-none">
                                        <svg class="w-6 h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                                            <path fill-rule="evenodd" d="M18 3a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-1V9a4 4 0 0 0-4-4h-3a1.99 1.99 0 0 0-1 .267V5a2 2 0 0 1 2-2h7Z" clip-rule="evenodd" />
                                            <path fill-rule="evenodd" d="M8 7.054V11H4.2a2 2 0 0 1 .281-.432l2.46-2.87A2 2 0 0 1 8 7.054ZM10 7v4a2 2 0 0 1-2 2H4v6a2 2 0 0 0 2 2h7a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3Z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600 flex-1">Jumlah Transfer</span>
                            <span class="font-medium text-blue-600 flex-1 text-right"><?php echo $order->get_formatted_order_total() ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600 flex-1">Status Pembayaran</span>
                            <span class="px-3 py-1 rounded-full text-center <?php echo $current_color['bg']; ?> <?php echo $current_color['text']; ?> font-medium flex-1"><?php echo esc_html($status_label); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Block Jika Copy Button di Click -->
                <div id="copy-message" class="bg-green-50 p-4 text-sm rounded-lg border border-green-200 hidden">
                    <div class="flex">
                        <svg class="flex-shrink-0 w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M6 10l2 2 6-6 1.5 1.5-7.5 7.5-3.5-3.5L6 10z" clip-rule="evenodd" />
                        </svg>
                        <div class="ml-3">
                            <span class="text-green-600 font-bold text-center">Nomor VA telah disalin!</span>
                        </div>
                    </div>
                </div>

                <!-- Payment Deadline -->
                <div class="flex items-center p-2 bg-red-100 rounded-lg border text-sm text-red-500">
                    <svg class="flex-shrink-0 mr-2 w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Harap melakukan pembayaran sebelum:
                    <span class="font-medium ml-1 text-red-700"><?php echo $formattedDate ?></span>
                </div>

                <!-- QR Code & Notes -->
                <div class="space-y-6">
                    <?php if ($order->get_status() === "completed"): ?>
                        <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                            <div class="flex">
                                <svg class="flex-shrink-0 w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M6 10l2 2 6-6 1.5 1.5-7.5 7.5-3.5-3.5L6 10z" clip-rule="evenodd" />
                                </svg>
                                <div class="ml-3">
                                    <h4 class="text-sm font-medium text-green-800">Terima Kasih!</h4>
                                    <div class="mt-2 text-sm text-green-700">
                                        <p>Terima kasih telah melakukan pemesanan. Kami berharap dapat melayani Anda lagi di masa depan!</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
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
}
