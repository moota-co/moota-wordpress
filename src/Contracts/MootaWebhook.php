<?php

namespace Moota\MootaSuperPlugin\Contracts;
use Exception;
use Moota\MootaSuperPlugin\Concerns\MootaPayment;
use WP_REST_Response;

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
		$response_data = [
			'Status'  => 'success',
			'Message' => 'Mutasi Ditemukan! Proses Auto-Konfirm Berhasil :D'
		];
		$http_code = 200;
	
		if ($http_signature && $_SERVER['REQUEST_METHOD'] == 'POST') {
			$response = file_get_contents('php://input');
			$data = json_decode($response, true);
			$moota_mode = array_get($moota_settings ?? [], 'moota_production_mode', 0);
			$secret = array_get($moota_settings, "moota_webhook_secret_key");
			$ip = self::get_client_ip();
			$signature = hash_hmac('sha256', $response, $secret ?? "");
	
			// Pengecekan test webhook (hanya di production mode)
			if (empty($moota_mode) && !empty($data[0]['amount']) && $data[0]['amount'] == 10000) {
				$response_data['Message'] = "OK (Test Webhook)";
				return new WP_REST_Response($response_data, $http_code);
			}
	
			if (!$moota_mode || (hash_equals($http_signature, $signature) && in_array($ip, ["103.236.201.178", "103.28.52.182"]))) {
				try {
					if (class_exists("WooCommerce")) {
						self::WooCommerceHandler($data); // Gunakan $data yang sudah di-decode
					}
	
					if (function_exists('EDD')) {
						self::EDDHandler($data); // Gunakan $data yang sudah di-decode
					}
				} catch (Exception $e) {
					$response_data['Status'] = 'failed';
					$response_data['Message'] = $e->getMessage();
				}
			} else {
				$response_data['Status'] = 'failed';
				$response_data['Message'] = "Invalid Signature or IP! Your IP : {$ip}";
			}
		} else {
			$response_data['Status'] = 'failed';
			$response_data['Message'] = "Invalid request method or missing signature.";
		}
	
		return new WP_REST_Response($response_data, $http_code);
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
    global $wpdb;

    self::addLog("Memulai proses WooCommerceHandler dengan " . count($mutations) . " mutasi");

    $moota_settings = get_option("moota_settings", []);
    $status_paid = array_get($moota_settings, "moota_wc_success_status", "completed");
    
    // Gunakan method resmi WooCommerce untuk cek HPOS
    $hpos_enabled = get_option('woocommerce_custom_orders_table_enabled', []);
	if($hpos_enabled === "yes") {
		self::addLog("HPOS Aktif.");
	} else {
		self::addLog("Legacy Mode Aktif.");
	}

    foreach ($mutations as $mutation) {
		$bank_id = array_get($mutation, "bank_id");
		$amount = $mutation['amount'];
		$bank_type = array_get($mutation, "bank.bank_type", "");
		$order_id = null;
	
		// Tentukan mutation_tag berdasarkan jenis bank (VA atau non-VA)
		if (preg_match('/va$/i', $bank_type)) {
			// VA: mutation_tag = "moota_{va_number}_{amount}"
			$va_number = array_get($mutation, 'account_number', '');
			$mutation_tag = "moota_" . trim($va_number) . "_{$amount}";
		} else {
			// Non-VA: mutation_tag = "bank_id.amount"
			$mutation_tag = "{$bank_id}.{$amount}";
		}
	
		// Query order dengan mutation_tag
        $order_ids = wc_get_orders([
            'limit'        => -1,
            'orderby'      => 'date_created',
            'order'        => 'DESC',
            'meta_key'     => 'moota_mutation_tag',
            'meta_value'   => $mutation_tag,
            'status'       => ['pending', 'on-hold'],
            'return'       => 'ids',
        ]);

        // Batalkan proses jika ada duplikasi
        if (count($order_ids) > 1) {
            self::addLog("Pembatalan Auto-Konfirmasi: Duplikasi mutation_tag {$mutation_tag}");
            self::notifyAdminAboutDuplication($mutation_tag, $order_ids);
            continue;
        }

        // Ambil order_id jika hanya ada 1
        $order_id = $order_ids[0] ?? null;
		
		if (empty($order_id)) {
			self::addLog("Order tidak ditemukan untuk mutation_tag: {$mutation_tag}");
			continue;
		}

		$order = wc_get_order($order_id);
		
		if (!$order) {
			self::addLog("Order ID {$order_id} tidak valid");
			continue;
		}
	
		// Validasi VA Number (hanya untuk VA)
		if (preg_match('/va$/i', $bank_type)) {
			$va_number_in_order = $order->get_meta('moota_va_number');
			$amount_in_order = $order->get_total();
			
			// Pastikan VA Number dan Amount sesuai
			if ($va_number_in_order !== $va_number || $amount_in_order != $amount) {
				self::addLog("VA Number/Amount tidak match: Order {$va_number_in_order}/{$amount_in_order} vs Mutation {$va_number}/{$amount}");
				continue;
			}
			
			self::addLog("VA Number valid: {$va_number}");
		}
	
		// Proses pembaruan status order
		$order->update_status($status_paid);
		self::addLog("Status order {$order_id} diupdate ke: {$status_paid}");
	
		// Hapus mutation_tag dari metadata
		$order->delete_meta_data('mutation_tag');
		$order->save();
		self::addLog("Mutation_tag dihapus dari order {$order_id}");
	
		// Lampirkan data ke Moota
		self::addLog("Melampirkan transaction ID ke Moota: Mutation ID " . $mutation['mutation_id']);
		(new MootaPayment(array_get($moota_settings, "moota_v2_api_key")))->attachTransactionId($mutation['mutation_id'], (string)$order_id);
		
		self::addLog("Melampirkan platform ke Moota");
		(new MootaPayment(array_get($moota_settings, "moota_v2_api_key")))->attachPlatform($mutation['mutation_id'], "WooCommerce");
	}

	if (count($order_ids) > 1) {
		throw new Exception("Duplikasi Order Ditemukan Sebanyak " . count($order_ids) . "! Berikut Tagnya : {$mutation_tag}, Silahkan Check Secara Manual Ya!");
	}

	if (empty($order_id)) {
        throw new Exception("Order tidak ditemukan untuk mutation_tag: {$mutation_tag}");
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
				}

				wp_reset_postdata();
			}
		}
	}

	private static function updateWCUniqueNote(array $mutations)
{
    global $wpdb;
    $moota_settings = get_option("moota_settings", []);
    $hpos_enabled = get_option('woocommerce_custom_orders_table_enabled', 'no');

    $db_prefix = $wpdb->prefix;
    $backup_table = $db_prefix . 'wc_orders_meta';
    $backup_order_table = $db_prefix . 'wc_orders';

    $status_paid = array_get($moota_settings, "moota_wc_success_status", "completed");

    // Sesuaikan status berdasarkan HPOS
    $status_condition = $hpos_enabled === 'yes' ? 'pending' : 'wc-pending';

    $sql = $wpdb->prepare("
        SELECT A.order_id as order_id, A.meta_value AS unique_note, B.total_amount AS total 
        FROM {$backup_table} A 
        INNER JOIN {$backup_order_table} B 
            ON A.order_id = B.id 
        WHERE A.meta_key = 'moota_mutation_note_tag'
            AND B.status = %s
    ", $status_condition);

    $meta = $wpdb->get_row($sql);

    if (empty($meta)) {
        return false;
    }

    $order_founds = 0;

    foreach ($mutations as $mutation) {
        $note_code = strtolower(trim($meta->moota_note_code));
        $description = strtolower(trim($mutation['description']));

        if (
            $mutation['amount'] == $meta->total 
            && strpos($description, $note_code) !== false
        ) {
            $order = wc_get_order($meta->order_id);

            if ($order && $order->get_order_number()) {
                $order->update_status($status_paid);
                
        self::addLog("Melampirkan transaction ID ke Moota: Mutation ID " . $mutation['mutation_id']);
            (new MootaPayment(array_get($moota_settings, "moota_v2_api_key")))->attachTransactionId($mutation['mutation_id'], (string)$meta->order_id);
            
        self::addLog("Melampirkan platform ke Moota");
            (new MootaPayment(array_get($moota_settings, "moota_v2_api_key")))->attachPlatform($mutation['mutation_id'], "WooCommerce");

                $order_founds++;
            }
        }
    }

    return $order_founds > 0;
}


	private static function updateEDDUniqueNote(array $mutations)
	{
		global $wpdb;
		$moota_settings = get_option("moota_settings", []);

		$status_paid = array_get($moota_settings, "moota_wc_success_status", "completed");

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

			if($mutation['amount'] == $meta->total && strpos($mutation['description'], $meta->moota_note_code)){

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
					}
	
					wp_reset_postdata();
				}

			}

		}

		return $order_founds ? true:false;
	}

	private static function notifyAdminAboutDuplication($mutation_tag, $order_ids) {
		$admin_email = get_bloginfo('admin_email');
    $subject = "⚠️ [DUPLIKASI] Mutation Tag: {$mutation_tag}";
    $message = "Ditemukan " . count($order_ids) . " order dengan mutation_tag yang sama:\n";
    
    foreach ($order_ids as $order_id) {
        $order = wc_get_order($order_id);
        $message .= "- Order #{$order_id} (Total: " . wc_price($order->get_total()) . ")\n";
    }
    
    $message .= "\n**Auto-konfirmasi dibatalkan**. Mohon verifikasi manual!";
    wp_mail($admin_email, $subject, $message);
	}
}
