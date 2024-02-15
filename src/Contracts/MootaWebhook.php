<?php

namespace Moota\MootaSuperPlugin\Contracts;
use Moota\MootaSuperPlugin\Concerns\MootaPayment;

class MootaWebhook {
	private static $filename = 'mutasi-log';
	private static $callback_name = 'moota-callback';

	public function __construct() {
		self::init();
	}

	public static function init() {
		add_action( 'rest_api_init', function () {

			register_rest_route( 'moota-callback', 'webhook', array(
			  'methods' => 'post',
			  'callback' => [self::class, '_endpoint_handler'],
			  'permission_callback' => '__return_true'
			) );

			register_rest_route("internal", 'get-mutation-now', [
				'methods' => 'post',
				'callback' => [self::class, 'mutation_now_endpoint'],
				'permission_callback' => '__return_true'
			]);

		} );

		// add_action( 'template_redirect', [self::class, '_endpoint_handler'] );
	}

	public static function mutation_now_endpoint(\WP_REST_Request $request)
	{
		if(!$request->has_param("bank_id")){
			return "Bank ID is Required!";
		}

		$moota_settings = get_option("moota_settings", []);

		(new MootaPayment(array_get($moota_settings, "moota_v2_api_key")))->refreshMutation($request->get_param("bank_id"));

		return "OK";
	}

	public static function endpoint() {
		add_rewrite_endpoint( self::$callback_name, EP_ROOT );
	}

	private static function get_client_ip() {
		$ipaddress = '';
		if (getenv('HTTP_CLIENT_IP'))
			$ipaddress = getenv('HTTP_CLIENT_IP');
		else if(getenv('HTTP_X_FORWARDED_FOR'))
			$ipaddress = getenv('HTTP_X_FORWARDED_FOR');
		else if(getenv('HTTP_X_FORWARDED'))
			$ipaddress = getenv('HTTP_X_FORWARDED');
		else if(getenv('HTTP_FORWARDED_FOR'))
			$ipaddress = getenv('HTTP_FORWARDED_FOR');
		else if(getenv('HTTP_FORWARDED'))
		   $ipaddress = getenv('HTTP_FORWARDED');
		else if(getenv('REMOTE_ADDR'))
			$ipaddress = getenv('REMOTE_ADDR');
		else
			$ipaddress = 'UNKNOWN';
		return $ipaddress;
	}

	public static function _endpoint_handler(\WP_REST_Request $request) {

		global $wp_query;

		$moota_settings = get_option("moota_settings", []);

		

		$http_signature = $request->get_header("Signature");

		

		if ( $http_signature && $_SERVER['REQUEST_METHOD'] == 'POST' ) {

			$response = file_get_contents('php://input');

			$moota_mode = array_get($moota_settings ?? [], 'moota_production_mode', 0);

			$secret   = array_get($moota_settings, "moota_webhook_secret_key");

			$ip = self::get_client_ip();

			$signature = hash_hmac( 'sha256', $response, $secret ?? "" );

			$log      = '';

			if ( !$moota_mode || (hash_equals( $http_signature, $signature ) && in_array($ip, ["103.236.201.178", "103.28.52.182"])) ) {

				foreach(json_decode($response, true) as $mutation)
				{
					$verify = $moota_mode ? (new MootaPayment(array_get($moota_settings, "moota_v2_api_key")))->verifyMutation($mutation):true;

					if(!$verify){
						
						return "Mutations Data Is Not Verified!";
					}
				}

				if( class_exists("WooCommerce") ){
					self::WooCommerceHandler(json_decode($response, true));
				}

				if(function_exists( 'EDD' )){
					self::EDDHandler(json_decode($response, true));
				}
				

			} else {
				$log = "Invalid Signature, IP : {$ip}";

				return "Invalid Signature or IP! Your IP : {$ip}";
			}


			return "OK";
		}
	}


	public static function clearLog() {
		$file = ABSPATH . '/' . self::$filename;
		if ( file_exists( $file ) ) {
			unlink( $file );
		}
	}

	public static function addLog( $message ) {
		$file = ABSPATH . '/' . self::$filename;
		if ( ! file_exists( $file ) ) {
			touch( $file );
		}

		$log = file_get_contents( $file );
		$log .= PHP_EOL . ' ' . date( 'Y-m-d H:i:s' ) . ' : ' . $message;
		file_put_contents( $file, $log );
	}

	public static function getLog() {
		$file = ABSPATH . '/' . self::$filename;
		if ( file_exists( $file ) ) {
			return file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		}

		return [];
	}


	private static function WooCommerceHandler(array $mutations)
	{
		$moota_settings = get_option("moota_settings", []);

		$status_paid = array_get($moota_settings, "wc_success_status", "completed");

		$db_prefix = array_get($moota_settings, "wp_db_prefix");

		$backup_table = "wp_wc_orders_meta";

		if($db_prefix){
			$backup_table = $db_prefix."wc_orders_meta";
		}

		$updatingByNote = self::updateWCUniqueNote($mutations);

		if($updatingByNote){
			return;
		}

		foreach($mutations as $mutation){
			$bank_id = array_get($mutation, "bank_id");

			global $wpdb;

			$sql = "SELECT post_id as order_id
			FROM {$wpdb->postmeta} 
			WHERE meta_key='mutation_tag' AND meta_value='{$bank_id}.{$mutation['amount']}'";
			
			$meta = $wpdb->get_row($sql);

			if(empty($meta)){

				$sql = "SELECT order_id 
				FROM {$backup_table} 
				WHERE meta_key='mutation_tag' AND meta_value='{$bank_id}.{$mutation['amount']}'";
				
				$meta = $wpdb->get_row($sql);

			}


			if(empty($meta)){
				return;
			}

			$order = new \WC_Order( $meta->order_id );

			if ( $order->get_order_number() ) {

				$order->update_status($status_paid);

				$wpdb->delete($wpdb->postmeta, [
					"meta_key" => "mutation_tag",
					"meta_value" => "{$bank_id}.{$mutation['amount']}"
				]);
					
				(new MootaPayment(array_get($moota_settings, "moota_v2_api_key")))->attachTransactionId($mutation['mutation_id'], (string)$meta->order_id);
				(new MootaPayment(array_get($moota_settings, "moota_v2_api_key")))->attachPlatform($mutation['mutation_id'], "WooCommerce");
				(new MootaPayment(array_get($moota_settings, "moota_v2_api_key")))->attachMerchant($mutation['mutation_id'], array_get($moota_settings, "moota_merchant_name"));
			}

		}
	}

	private static function EDDHandler(array $mutations)
	{
		global $wpdb;
		$moota_settings = get_option("moota_settings", []);

		$unique_verification = array_get($moota_settings, "unique_code_verification_type", "nominal");

		if(self::updateEDDUniqueNote($mutations)){
			return;
		}

		foreach( $mutations as $mutation) {

			$sql = "SELECT B.id as order_id 
			FROM {$wpdb->edd_ordermeta} A, {$wpdb->edd_orders} B 
			WHERE A.meta_key='bank_id' 
			AND A.meta_value='{$mutation['bank_id']}' 
			AND B.id=A.edd_order_id 
			AND B.status='pending' 
			AND B.total={$mutation['amount']}";

			$query = $wpdb->get_row($sql);

			if(empty($query)){
				return "OK";
			}

			if( !empty($query->order_id)) {
				$admin_email = get_bloginfo('admin_email');
				$message = sprintf( __( 'Hai Admin.' ) ) . "\r\n\r\n";
				$message .= sprintf( __( 'Ada order yang sama, dengan nominal Rp %s' ), $mutation['amount'] ). "\r\n\r\n";
				$message .= sprintf( __( 'Mohon dicek manual.' ) ). "\r\n\r\n";
				wp_mail( $admin_email, sprintf( __( '[%s] Ada nominal order yang sama - Moota' ), get_option('blogname') ), $message );

				$updated = edd_update_payment_status( $query->order_id, 'publish' );

				if ($updated) {

					$note = "Payment applied from Moota, MootaID: {$mutation['id']}"
						. ", amount: {$mutation['amount']}, from Bank: {$mutation['bank_type']}";

					wp_insert_comment( wp_filter_comment( array(
						'comment_post_ID'      => $query->order_id,
						'comment_content'      => $note,
						'user_id'              => 0,
						'comment_date'         => current_time( 'mysql' ),
						'comment_date_gmt'     => current_time( 'mysql', 1 ),
						'comment_approved'     => 1,
						'comment_parent'       => 0,
						'comment_author'       => '',
						'comment_author_IP'    => '',
						'comment_author_url'   => '',
						'comment_author_email' => '',
						'comment_type'         => 'edd_payment_note'
					) ) );

					array_push($results, array(
						'order_id'          =>  $query->order_id,
						'status'            =>  'ada',
						'amount'            =>  (int) $mutation['amount'],
						'transaction_id'    =>  (int) $mutation['id']
					));

					(new MootaPayment(array_get($moota_settings, "moota_v2_api_key")))->attachTransactionId($mutation['mutation_id'], (string)$query->order_id);
					(new MootaPayment(array_get($moota_settings, "moota_v2_api_key")))->attachPlatform($mutation['mutation_id'], "Easy Digital Downloads");
					(new MootaPayment(array_get($moota_settings, "moota_v2_api_key")))->attachMerchant($mutation['mutation_id'], array_get($moota_settings, "moota_merchant_name"));
				}

				wp_reset_postdata();
			}
		}
	}

	private static function updateWCUniqueNote(array $mutations)
	{
		global $wpdb;
		$moota_settings = get_option("moota_settings", []);

		$db_prefix = array_get($moota_settings, "wp_db_prefix");

		$backup_table = "wp_wc_orders_meta";

		$backup_order_table = "wp_wc_orders";

		if($db_prefix){
			$backup_table = $db_prefix."wc_orders_meta";
			$backup_order_table = $db_prefix."wc_orders";
		}

		$status_paid = array_get($moota_settings, "wc_success_status", "completed");

		$sql = "SELECT A.order_id as order_id, A.meta_value AS unique_note, B.total_amount AS total 
		FROM {$backup_table} A, {$backup_order_table} B 
		WHERE A.meta_key = 'note_code'
		AND B.id = A.order_id
		AND B.status = 'wc-on-hold'";

		$meta = $wpdb->get_row($sql);

		if(empty($meta)){
			return false;
		}

		$order_founds = 0;

		foreach($mutations as $mutation){

			if($mutation['amount'] == $meta->total && strpos($mutation['description'], $meta->unique_note)){

				$order = new \WC_Order( $meta->order_id );

				if ( $order->get_order_number() ) {

					$order->update_status($status_paid);
						
					(new MootaPayment(array_get($moota_settings, "moota_v2_api_key")))->attachTransactionId($mutation['mutation_id'], (string)$meta->order_id);
					(new MootaPayment(array_get($moota_settings, "moota_v2_api_key")))->attachPlatform($mutation['mutation_id'], "WooCommerce");
					(new MootaPayment(array_get($moota_settings, "moota_v2_api_key")))->attachMerchant($mutation['mutation_id'], array_get($moota_settings, "moota_merchant_name"));

					$order_founds++;

				}

			}

		}

		return $order_founds ? true:false;
	}


	private static function updateEDDUniqueNote(array $mutations)
	{
		global $wpdb;
		$moota_settings = get_option("moota_settings", []);

		$status_paid = array_get($moota_settings, "wc_success_status", "completed");

		$sql = "SELECT A.edd_order_id as order_id, A.meta_value AS unique_note, B.subtotal AS total 
		FROM {$wpdb->edd_ordermeta} A, {$wpdb->edd_orders} B 
		WHERE A.meta_key = 'news_code'
		AND B.id = A.edd_order_id
		AND B.status = 'pending'";

		$meta = $wpdb->get_row($sql);

		if(empty($meta)){
			return false;
		}

		$order_founds = 0;

		foreach($mutations as $mutation){

			if($mutation['amount'] == $meta->total && strpos($mutation['description'], $meta->unique_note)){

				if( !empty($meta->order_id)) {
					$admin_email = get_bloginfo('admin_email');
					$message = sprintf( __( 'Hai Admin.' ) ) . "\r\n\r\n";
					$message .= sprintf( __( 'Ada order yang sama, dengan nominal Rp %s' ), $mutation['amount'] ). "\r\n\r\n";
					$message .= sprintf( __( 'Mohon dicek manual.' ) ). "\r\n\r\n";
					wp_mail( $admin_email, sprintf( __( '[%s] Ada nominal order yang sama - Moota' ), get_option('blogname') ), $message );
	
					$updated = edd_update_payment_status( $meta->order_id, 'publish' );
	
					if ($updated) {
	
						$note = "Payment applied from Moota, MootaID: {$mutation['id']}"
							. ", amount: {$mutation['amount']}, from Bank: {$mutation['bank_type']}";
	
						wp_insert_comment( wp_filter_comment( array(
							'comment_post_ID'      => $meta->order_id,
							'comment_content'      => $note,
							'user_id'              => 0,
							'comment_date'         => current_time( 'mysql' ),
							'comment_date_gmt'     => current_time( 'mysql', 1 ),
							'comment_approved'     => 1,
							'comment_parent'       => 0,
							'comment_author'       => '',
							'comment_author_IP'    => '',
							'comment_author_url'   => '',
							'comment_author_email' => '',
							'comment_type'         => 'edd_payment_note'
						) ) );
	
						array_push($results, array(
							'order_id'          =>  $meta->order_id,
							'status'            =>  'ada',
							'amount'            =>  (int) $mutation['amount'],
							'transaction_id'    =>  (int) $mutation['id']
						));
	
						(new MootaPayment(array_get($moota_settings, "moota_v2_api_key")))->attachTransactionId($mutation['mutation_id'], (string)$meta->order_id);
						(new MootaPayment(array_get($moota_settings, "moota_v2_api_key")))->attachPlatform($mutation['mutation_id'], "Easy Digital Downloads");
						(new MootaPayment(array_get($moota_settings, "moota_v2_api_key")))->attachMerchant($mutation['mutation_id'], array_get($moota_settings, "moota_merchant_name"));
					}
	
					wp_reset_postdata();
				}

			}

		}

		return $order_founds ? true:false;
	}

}
