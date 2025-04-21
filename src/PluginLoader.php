<?php

namespace Moota\MootaSuperPlugin;

use DateTimeZone;
use Exception;
use Moota\MootaSuperPlugin\Concerns\MootaPayment;
use Moota\MootaSuperPlugin\Contracts\MootaWebhook;
use Jeffreyvr\WPSettings\WPSettings;
use Moota\MootaSuperPlugin\EDD\EDDMootaBankTransfer;
use Moota\MootaSuperPlugin\Options\WebhookOption;
use Moota\MootaSuperPlugin\Woocommerce\QRIS\QRISGateway;

class PluginLoader
{
    private static $init;

	public function __construct() {

		add_action( 'plugins_loaded', [ $this, 'onload' ] );

		register_activation_hook( MOOTA_FULL_PATH, [ $this, 'activation_plugins' ] );
		register_deactivation_hook( MOOTA_FULL_PATH, [ $this, 'deactivation_plugins' ] );

        register_shutdown_function(function () {
        });

		add_action( 'admin_menu', [$this, 'register_setting_page'] );
		add_action('update_option_moota_settings', [$this, 'clear_cache_on_api_key_change'], 10, 2);

		add_action('admin_notices', [$this, 'production_mode_notice']);
		add_action('wp_ajax_moota_sync_banks', [$this, 'ajax_sync_banks']);
        add_action('admin_footer', [$this, 'admin_footer_scripts']);
	}

	public static function init() {
		if ( ! self::$init instanceof self ) {
			self::$init = new self();
		}

		return self::$init;
	}

	public function clear_cache_on_api_key_change($old_value, $new_value) {
    $old_api_key = array_get($old_value, 'moota_v2_api_key');
    $new_api_key = array_get($new_value, 'moota_v2_api_key');
    
    if ($old_api_key !== $new_api_key) {
        // Hapus semua cache terkait
        delete_option('moota_list_banks');
        delete_option('moota_list_accounts');
        delete_option('moota_last_sync');
    }
}

	public function onload() {

		if ( class_exists( 'WooCommerce' ) ) {
			add_filter( 'woocommerce_payment_gateways', [ $this, 'add_moota_gateway_class' ] );
		}

        add_action('wp_enqueue_scripts', [$this, 'front_end_scripts']);

		MootaWebhook::init();

		add_filter('wp_settings_option_type_map', function($options){
			$options['webhook-option'] = WebhookOption::class;
			return $options;
		});
	}

	/**
	 * @param $methods
	 * Register Payment Method Woocommerce
	 * @return mixed
	 */
	public function add_moota_gateway_class($methods) {
		$banks = ['BCA', 'Sinarmas', 'Permata', 'BNC', 'CIMB', 'BJB', 'BNI', 'BRI', 'BTN', 'BSI', 'Muamalat', 'Maybank', 'Mandiri'];
		
		foreach ($banks as $bank) {
			$bankTransferClass = "Moota\\MootaSuperPlugin\\Woocommerce\\BankTransfer\\{$bank}\\{$bank}Gateway";
			$vaClass = "Moota\\MootaSuperPlugin\\Woocommerce\\VirtualAccount\\{$bank}\\{$bank}VA";
			
			if (class_exists($bankTransferClass)) {
				$methods[] = $bankTransferClass;
			}
			
			if (class_exists($vaClass)) {
				$methods[] = $vaClass;
			}
		}
	
		array_push($methods,
			QRISGateway::class
		);

		// var_dump(print_r($methods)); die();
		
		return $methods;
	}

	public function add_moota_edd_gateway_class($gateways) 
	{
		$gateways[] = EDDMootaBankTransfer::class;
		return $gateways;
	}

	function register_edd_custom_gateway($gateways) {
		$gateways['edd_moota_bank_transfer'] = array(
			'admin_label'    => 'Moota Bank Transfer',
			'checkout_label' => 'Bank Transfer',
		);
		return $gateways;
	}

	public function activation_plugins() {
	}

	public function deactivation_plugins() {
	}

    public function front_end_scripts() {
        $assets = plugin_dir_url( MOOTA_FULL_PATH ) . 'assets/';

        wp_enqueue_style( 'moota-payment-gateway',  $assets . 'style.css' );
		wp_enqueue_style( 'moota-toastr',  $assets . 'css/toastr.css' );
		wp_enqueue_style( 'moota-tailwind',  $assets . 'css/style.min.css' );
		wp_enqueue_script("moota-taostr", $assets . 'js/toastr.min.js');
    }

	public function register_setting_page() : void
	{
		$settings = new WPSettings(__('Moota Settings'));
		$moota_settings = get_option('moota_settings', []);

		$submitted_checkout = isset($_POST['moota_settings']['moota_custom_checkout_redirect'])
        ? sanitize_text_field($_POST['moota_settings']['moota_custom_checkout_redirect'])
        : array_get($moota_settings, 'moota_custom_checkout_redirect', 'whitelable');
		
		$settings->set_menu_icon(plugin_dir_url( MOOTA_FULL_PATH ) . 'assets/img/icon-moota-small-2.png');

		/**
		 * Defining Tabs
		 */
		$general_tab = $settings->add_tab(__( 'Umum'));

		/**
		 * Defining Sections
		 */
		$api_section = $general_tab->add_section('Pengaturan API');

		/**
		 * API Setting Fields
		 */
		$api_section->add_option('checkbox', [
			'name' => 'moota_production_mode',
			'label' => __('Aktifkan Mode Production')
		]);

		$api_section->add_option('webhook-option', [
			'name' => 'moota_webhook_endpoint',
			'label' => __('Webhook Url'),
			'disable' => true,
			'default' => $_SERVER['SERVER_NAME']."/wp-json/moota-callback/webhook"
		]);

		$api_section->add_option('text', [
			'name' => 'moota_v2_api_key',
			'label' => __('Moota V2 API Key'),
			'description' => '
			<button type="button" style="display: flex; align-items: center;"
				id="moota-sync-banks" 
				class="button button-secondary"
				data-nonce="' . wp_create_nonce('moota_sync_banks') . '"
			><span class="dashicons dashicons-update"></span> Sinkronisasi Bank</button>
			<span id="moota-last-sync" style="color: #666;">
				Terakhir update: ' . $this->get_last_sync_time() . ' GMT+7 (Asia/Jakarta)
			</span>
			<span id="moota_info_message"></span>
			<div><span style="color:red;">Warning!</span> Pastikan sinkronisasi kembali setelah menambah, mengedit, atau menghapus akun di Moota. Periksa dan atur ulang Setelan Bank & Akun jika diperlukan.</div>
			<p class="description">API Token Moota bisa Anda dapatkan <a href="https://app.moota.co/integrations/personal" target="_blank">disini</a></p>
			'
		]);

		$api_section->add_option('text', [
			'name' => 'moota_webhook_secret_key',
			'label' => __('Webhook Secret Key'),
			"description" => "Secret token bisa Anda dapatkan <a href='https://app.moota.co/integrations/webhook' target='_blank'>disini</a>"
		]);

		/**
		 * Plugin Setting Tab 
		 */
		if ( class_exists( 'WooCommerce' ) ) {
			/**
			 * Defining WooCommerce Tab
			 */
			$wc_tab = $settings->add_tab("WooCommerce");

			$wc_general_section = $wc_tab->add_section("Pengaturan Umum");

			$wc_general_section->add_option("select", [
				"name" => "moota_wc_success_status",
				"label" => "Status Pesanan Ketika Sudah Dibayar",
				"options" => [
					'processing' => "Processing",
					"on-hold" => "On Hold",
					"failed" => "Failed",
					"canceled" => "Canceled",
					"refunded" => "Refunded",
					"completed" => "Completed"
				]
			]);
			$wc_general_section->add_option("select", [
				"name" => "moota_wc_initiate_status",
				"label" => "Status Pesanan Setelah Membuat Order",
				"options" => [
					"on-hold" => "On Hold",
					"pending" => "Pending Payment"
				]
			]);
			$wc_general_section->add_option("select", [
				"name" => "moota_custom_checkout_redirect",
				"label" => "Custom Checkout Redirect",
				"options" => [
					'whitelable' 	=> "Gunakan Checkout WooCommerce",
					'moota'			=> "Gunakan Checkout Moota"
				],
				"description" => "<strong>Redirect Checkout </strong>: Ketika melakukan Order, Kamu bisa Pilih ingin tetap Menggunakan Checkout dari <strong>WooCommerce</strong> atau Checkout dari <strong>Moota</strong>"
			]);
			if($submitted_checkout == 'moota'){
				$wc_general_section->add_option("select", [
					"name" => "moota_failed_redirect_url",
					"label" => "Failed Payment Redirect URL",
					"options" => [
						'thanks_page' => "Ke Halaman Thanks Page (Default WooCommerce)",
						'last_visited' => "Ke Halaman Terakhir Dikunjungi",
						'Detail Produk' => "Menuju Detail Produk",
					]
				]);
				$wc_general_section->add_option("select", [
					"name" => "moota_pending_redirect_url",
					"label" => "New Order Payment Redirect URL",
					"options" => [
						'thanks_page' => "Ke Halaman Thanks Page (Default WooCommerce)",
						'last_visited' => "Ke Halaman Terakhir Dikunjungi",
						'Detail Produk' => "Menuju Detail Produk",
					]
				]);
				$wc_general_section->add_option("select", [
					"name" => "moota_success_redirect_url",
					"label" => "Success Payment Redirect URL",
					"options" => [
						'thanks_page' => "Ke Halaman Thanks Page (Default WooCommerce)",
						'last_visited' => "Ke Halaman Terakhir Dikunjungi",
						'Detail Produk' => "Menuju Detail Produk",
					],
					"description" => "
						<div style='font-size: 14px; line-height: 1.6;'>
							<p>Pilih halaman yang ingin dituju setelah pembayaran :</p>
							<ul style='list-style-type: disc; margin-left: 20px;'>
								<li>
									<strong>Ke Halaman Thanks Page (Default WooCommerce):</strong>
									<br>Mengarahkan pelanggan ke halaman terima kasih default WooCommerce. Halaman ini memberikan konfirmasi bahwa pesanan mereka telah diterima, meskipun pembayaran gagal.
								</li>
								<li>
									<strong>Ke Halaman Terakhir Dikunjungi:</strong>
									<br>Mengarahkan pelanggan kembali ke halaman terakhir yang mereka kunjungi. Ini membantu mereka untuk melanjutkan pengalaman berbelanja mereka tanpa kehilangan jejak.
								</li>
								<li>
									<strong>Menuju Detail Produk:</strong>
									<br>Mengarahkan pelanggan ke halaman detail produk. Ini memberikan kesempatan untuk melihat kembali produk yang mereka coba beli dan mungkin memotivasi mereka untuk mencoba melakukan pembayaran lagi.<br>
									<p style='color: red;'>Warning! Ketika Produknya melebihi 1 Jenis Produk, Maka akan dipindahkan ke halaman Store Utama</p>
								</li>
							</ul>
						</div>
					"
				]);
			}
		}
		$settings->make();
	}

	public function ajax_sync_banks() {
		check_ajax_referer('moota_sync_banks', 'nonce');
		
		try {
			if (!current_user_can('manage_options')) {
				throw new Exception('Akses ditolak');
			}
	
			$moota_settings = get_option('moota_settings', []);
			$api_key = array_get($moota_settings, 'moota_v2_api_key', '');
			
			if (empty($api_key)) {
				throw new Exception('API Key belum diisi!');
			}
	
			// Panggil API getBanks() dan tangkap error
			$moota = new MootaPayment($api_key);
			$moota->clearCache();
			$banks = $moota->getBanks(true);
	
			// Jika API mengembalikan error (misal: token invalid)
			if (empty($banks) || isset($banks['error'])) {
				throw new Exception(
					$banks['error'] ?? 'Token tidak valid atau tidak ada akun apapun yang terdaftar dengan key ini.'
				);
			}
	
			// Simpan data bank
			$bankArray = json_decode(json_encode($banks), true);
			update_option('moota_list_banks', $bankArray);
			update_option('moota_list_accounts', $bankArray);
			update_option('moota_last_sync', current_time('mysql', true));
			
			wp_send_json_success([
				'message' => 'Data bank berhasil disinkronisasi! ' . count($banks) . ' Akun aktif ditemukan dalam key ini.',
				'time' => $this->get_last_sync_time() . ' GMT+7 (Asia/Jakarta)'
			]);
		} catch(Exception $e) {
			// Hapus cache jika token invalid
			delete_option('moota_list_banks');
			delete_option('moota_list_accounts');
	
			wp_send_json_error([
				'error' => $e->getMessage()
			]);
		}
	}

    private function get_last_sync_time() {
        $last_sync = get_option('moota_last_sync');
    
    if (empty($last_sync)) {
        return 'Belum pernah disinkronisasi';
    }

    try {
        $utc_timestamp = strtotime($last_sync . ' UTC');
        
        // Format ke waktu Jakarta dengan terjemahan
        return wp_date(
            'j F Y H:i', 
            $utc_timestamp, 
            new DateTimeZone('Asia/Jakarta')
        );
    	} catch (Exception $e) {
        	return 'Format waktu tidak valid';
    	}
    }

    public function admin_footer_scripts() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('#moota-sync-banks').on('click', function() {
                var $button = $(this);
                $button.prop('disabled', true).find('.dashicons').addClass('spin');
                
                $.post(ajaxurl, {
                    action: 'moota_sync_banks',
                    nonce: $button.data('nonce')
                }, function(response) {
                    if (response.success) {
                        $('#moota-last-sync').html(
                            'Terakhir update: ' + response.data.time
                        );
                        $('#moota_info_message').html(
							'<div style="color: green">' + response.data.message + '</div>'
						);
                    } else {
                        $('#moota_info_message').html(
							'<div style="color: red">Error : ' + response.data.error + '</div>'
						);
                    }
                }).always(function() {
                    $button.prop('disabled', false)
                           .find('.dashicons')
                           .removeClass('spin');
                });
            });
        });

        // CSS untuk animasi putar
        const style = document.createElement('style');
        style.textContent = `
            .dashicons.spin {
                animation: moota-spin 1s infinite linear;
                display: inline-block;
            }
            @keyframes moota-spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
        </script>
        <?php
    }


	public function production_mode_notice()
	{
		$moota_settings = get_option("moota_settings", []);


		if(array_get($moota_settings ?? [], 'moota_production_mode', 0)){
			return;
		}

		?>

		<div class="update-nag notice" style="display: block;">
            <p><?php _e( '<b>Moota Wordpress</b> dalam mode <b>testing</b>. Fitur verifikasi mutasi, verifikasi signature dan whitelist IP dimatikan.', 'moota-super-plugin' ); ?>
            </p>
        </div>

		<?php
	}

	public static function log_to_file($message) {
		// Path baru: wp-content/moota-logs/
		$log_dir = WP_CONTENT_DIR . '/moota-logs/';
		$log_file = $log_dir . 'debug.log';
	
		// Buat direktori jika belum ada
		if (!file_exists($log_dir)) {
			mkdir($log_dir, 0755, true); // 0755 = izin direktori
		}
	
		// Format pesan log
		$timestamp = date('Y-m-d H:i:s');
		$log_content = "[$timestamp] $message" . PHP_EOL;
	
		// Tulis ke file
		file_put_contents($log_file, $log_content, FILE_APPEND);
	}

}