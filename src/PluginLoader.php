<?php

namespace Moota\MootaSuperPlugin;

use DateTimeZone;
use Exception;
use Moota\MootaSuperPlugin\Concerns\MootaPayment;
use Moota\MootaSuperPlugin\Contracts\MootaWebhook;
use Jeffreyvr\WPSettings\WPSettings;
use Moota\MootaSuperPlugin\EDD\EDDMootaBankTransfer;
use Moota\MootaSuperPlugin\Options\WebhookOption;
use Moota\MootaSuperPlugin\Woocommerce\WCMootaBankTransfer;
use Moota\MootaSuperPlugin\Woocommerce\WCMootaVirtualAccountTransfer;

class PluginLoader
{
    private static $init;

	private string $plugin_name = "moota-super-plugin";

	public function __construct() {

		add_action( 'plugins_loaded', [ $this, 'onload' ] );

		register_activation_hook( MOOTA_FULL_PATH, [ $this, 'activation_plugins' ] );
		register_deactivation_hook( MOOTA_FULL_PATH, [ $this, 'deactivation_plugins' ] );

        register_shutdown_function(function () {
        //    print_r( error_get_last() );
        });

		add_action( 'admin_menu', [$this, 'register_setting_page'] );

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

	public function onload() {

		if ( class_exists( 'WooCommerce' ) ) {
			add_filter( 'woocommerce_payment_gateways', [ $this, 'add_moota_gateway_class' ] );
		}

		// if( function_exists( 'EDD' ) ){

		// 	EDDMootaBankTransfer::getInstance();
		// }

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
	public function add_moota_gateway_class( $methods ) {
		$methods[] = WCMootaBankTransfer::class;
		$methods[] = WCMootaVirtualAccountTransfer::class;
		return $methods;
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
				Terakhir update: ' . $this->get_last_sync_time() . '
			</span>
			<span id="moota_info_message"></span>
			<div><span style="color:red;">Warning!</span> Anda harus setting ulang Setelan Bank & Akun Setelah sinkronisasi selesai.</div>
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
				"name" => "wc_success_status",
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
            $api_key = array_get($moota_settings ,'moota_v2_api_key', []);
            
            if (empty($api_key)) {
                throw new Exception('API Key belum diisi!');
            }

            // Panggil API getBanks()
            $moota = new MootaPayment($api_key);
            $banks = $moota->getBanks();
			$bankArray = json_decode(json_encode($banks), true);
            
            // Simpan data bank
            update_option('moota_list_banks', $bankArray);
            update_option('moota_list_accounts', $bankArray);
            update_option('moota_last_sync', current_time('mysql', true));
            
            wp_send_json_success([
                'message' => 'Data bank berhasil disinkronisasi! ' . count($banks) . ' Akun aktif ditemukan dalam key ini.',
                'time' => $this->get_last_sync_time()
            ]);
        } catch(Exception $e) {
            wp_send_json_error($e->getMessage());
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
							'<div style="color: red">' + response.data + '</div>'
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

}