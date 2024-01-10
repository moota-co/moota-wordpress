<?php

namespace Moota\MootaSuperPlugin;
use Moota\MootaSuperPlugin\Concerns\MootaPayment;
use Moota\MootaSuperPlugin\Contracts\MootaWebhook;
use Jeffreyvr\WPSettings\WPSettings;
use Moota\MootaSuperPlugin\EDD\EDDMootaBankTransfer;
use Moota\MootaSuperPlugin\Options\WebhookOption;
use Moota\MootaSuperPlugin\Woocommerce\WCMootaBankTransfer;

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

		if( function_exists( 'EDD' ) ){

			EDDMootaBankTransfer::getInstance();
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
	public function add_moota_gateway_class( $methods ) {
		$methods[] = WCMootaBankTransfer::class;
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
        MootaWebhook::init();
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

		$bank_tab = $settings->add_tab(__( 'Bank Tersedia'));

		/**
		 * Defining Sections
		 */
		$api_section = $general_tab->add_section('Pengaturan API');

		$unique_code = $general_tab->add_section("Pengaturan Pembayaran");

		$merchant_section = $general_tab->add_section("Pengaturan Merchant");

		$bank_section = $bank_tab->add_section('Aktifkan Bank');

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
			"description" => "API Token Moota bisa Anda dapatkan <a href='https://app.moota.co/integrations/personal' target='_blank'>disini</a>"
		]);

		$api_section->add_option('text', [
			'name' => 'moota_webhook_secret_key',
			'label' => __('Webhook Secret Key'),
			"description" => "Secret token bisa Anda dapatkan <a href='https://app.moota.co/integrations/webhook' target='_blank'>disini</a>"
		]);

		/**
		 * Unique Code Settings Fields
		 */
		$unique_code->add_option('text', [
			'name' => 'moota_refresh_mutation_interval',
			'label' => __('Interval cek status transaksi'),
			'type' => "number",
			'default' => 5,
			'description' => "durasi waktu untuk check status transaksi"
		]);

		$unique_code->add_option('checkbox', [
			"name" => "enable_moota_unique_code",
			"label" => __('Aktifkan Kode Unik')
		]);

		$unique_code->add_option('text', [
			'name' => 'moota_unique_code_start',
			'label' => __('Angka Kode Unik Dimulai'),
			'type' => "number",
			'description' => "Nominal minimal kode unik pembayaran"
		]);

		$unique_code->add_option('text', [
			'name' => 'moota_unique_code_end',
			'label' => __('Angka Kode Unik Berakhir'),
			'type' => "number",
			'description' => "Nominal maksimal kode unik pembayaran"
		]);

		$unique_code->add_option('select', [
			'name' => 'unique_code_type',
			'label' => __( 'Tipe Kode Unik', 'textdomain' ),
			'options' => [
				'increase' => 'Menaikan Total Transaksi',
				'decrease' => 'Menurunkan Total Transaksi'
			]
		]);

		$unique_code->add_option('textarea', [
			'name' => 'payment_instruction',
			'label' => __( 'Instruksi Pembayaran', 'textdomain' ),
			'description' => "
				<div>Gunakan Replacer Berikut:</div>
				<div>Logo Bank : <b>[bank_logo]</b> </div>
				<div>Nama Bank : <b>[bank_name]</b> </div>
				<div>Nomor Rekening : <b>[bank_account]</b> </div>
				<div>Atas Nama Bank : <b>[bank_holder]</b> </div>
				<div>Kode Unik : <b>[unique_code]</b> </div>
				<div>Kode Unik Note (untuk berita/note transaksi) : <b>[unique_note]</b> </div>
				<div>Tombol Check Transaksi : <b>[check_button]</b> </div>
			",
			"default" => "
Harap untuk transfer sesuai dengan jumlah yang sudah ditentukan sampai 3 digit terakhir atau masukan kode [unique_note] kedalam berita / note transfer.

Transfer Ke Bank [bank_name] 
[bank_logo]
[bank_account] A/n [bank_holder]
			
[check_button]"
		]);

		/**
		 * Merchant Setting Fields
		 */
		$merchant_section->add_option("text", [
			"name" => "moota_merchant_name",
			"label" => "Nama Merchant",
			'default' => $_SERVER['SERVER_NAME']
		]);
		
		/**
		 * Bank Setting Fields
		 */
		$moota_settings = !empty(get_option("moota_settings")) ? get_option("moota_settings"):[];

		if(array_has($moota_settings, "moota_v2_api_key")){
			$banks = (new MootaPayment(array_get($moota_settings, "moota_v2_api_key")))->getBanks();

			foreach($banks ?? [] as $bank){
				$bank_section->add_option('checkbox', [
					'name' => $bank->bank_id,
					'label' => "{$bank->label} - {$bank->atas_nama}/{$bank->account_number}"
				]);
			}

		}

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

	public function production_mode_notice()
	{
		$moota_settings = get_option("moota_settings");


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