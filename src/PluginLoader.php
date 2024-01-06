<?php

namespace Moota\MootaSuperPlugin;
use Moota\MootaSuperPlugin\Concerns\MootaPayment;
use Moota\MootaSuperPlugin\Contracts\MootaWebhook;
use Jeffreyvr\WPSettings\WPSettings;
use Moota\MootaSuperPlugin\EDD\EDDMootaBankTransfer;
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
		$general_tab = $settings->add_tab(__( 'General'));

		$bank_tab = $settings->add_tab(__( 'Banks Available'));

		/**
		 * Defining Sections
		 */
		$api_section = $general_tab->add_section('API Settings');

		$unique_code = $general_tab->add_section("Unique Code Settings");

		$merchant_section = $general_tab->add_section("Merchant Settings");

		$bank_section = $bank_tab->add_section('Active Bank');

		/**
		 * API Setting Fields
		 */
		$api_section->add_option('text', [
			'name' => 'moota_v2_api_key',
			'label' => __('Moota V2 API Key')
		]);

		$api_section->add_option('text', [
			'name' => 'moota_webhook_secret_key',
			'label' => __('Webhook Secret Key')
		]);

		/**
		 * Unique Code Settings Fields
		 */
		$unique_code->add_option('checkbox', [
			"name" => "enable_moota_unique_code",
			"label" => __('Activate Unique Code')
		]);

		$unique_code->add_option('text', [
			'name' => 'moota_unique_code_start',
			'label' => __('Moota Unique Code Start'),
			'type' => "number"
		]);

		$unique_code->add_option('text', [
			'name' => 'moota_unique_code_end',
			'label' => __('Moota Unique Code End'),
			'type' => "number"
		]);

		$unique_code->add_option('select', [
			'name' => 'unique_code_type',
			'label' => __( 'Unique Code Type', 'textdomain' ),
			'options' => [
				'increase' => 'Increase Transaction Total',
				'decrease' => 'Decrease Transaction Total'
			]
		]);

		$unique_code->add_option('select', [
			'name' => 'unique_code_verification_type',
			'label' => __( 'Unique Code Verification Type', 'textdomain' ),
			'options' => [
				'nominal' => 'Transaction Nominal Verification (last three digit)',
				'news' => 'Transfer Bank News'
			]
		]);

		/**
		 * Merchant Setting Fields
		 */
		$merchant_section->add_option("text", [
			"name" => "moota_merchant_name",
			"label" => "Merchant Name"
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

			$wc_general_section = $wc_tab->add_section("General Settings");

			$wc_general_section->add_option("select", [
				"name" => "wc_success_status",
				"label" => "Order Status When Paid",
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

}