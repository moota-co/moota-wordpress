<?php

namespace Moota\MootaSuperPlugin\Contracts;
use Exception;
use Moota\Moota\Data\CreateTransactionData;
use Moota\Moota\Data\CustomerData;
use Moota\Moota\MootaApi;
use Moota\MootaSuperPlugin\PluginLoader;
use Throwable;
use WC_Order;
use WC_Customer;

class MootaTransaction
{
    public static function request( $order_id, $channel_id, $with_unique_code, $with_admin_fee, $admin_fee_amount, $start_unique_code, $end_unique_code, $payment_method_type = 'bank_transfer') {
		try {
			
			global $woocommerce;
			$order = new WC_Order( $order_id );
			$account_lists	= get_option("moota_list_accounts", []);
			$bank_settings 	= get_option("woocommerce_wc-super-moota-bank-transfer_settings", []);
			
			$account = array_filter($account_lists, function($bank) use ($channel_id) {
				return $bank['bank_id'] == $channel_id;
			});

			// Jika $account adalah array multidimensi (seperti contoh di var_dump)
			$accountObject = !empty($account) ? (object) reset($account) : null;

			foreach($account as $f_account)
			{
				$account = $f_account;
			}
	
			$items = [];
			/**
			 * @var $item WC_Order_Item_Product
			 */
			foreach ( $order->get_items() as $item ) {
				$product = wc_get_product( $item->get_product_id() );
	
				$image_meta = wp_get_attachment_metadata( $item->get_product_id() );
				$image_file = get_attached_file( $item->get_product_id(), false );
	
				$image_url = empty( $image_meta['original_image'] ) ? $image_file : path_join( dirname( $image_file ), $image_meta['original_image'] );
	
				if(empty($image_url)){
					$image_url = get_the_post_thumbnail_url( $item->get_product_id() );
				}
	
			}
	
			if ( $order->get_shipping_total() ) {
				$items[] = [
					'name'      => 'Ongkos Kirim',
					'qty'       => 1,
					'price'     => $order->get_shipping_total(),
					'sku'       => 'shipping-cost',
					'image_url' => ''
				];
			}
	
			$tax = 0;
	
			if ( $order->get_tax_totals() ) {
				foreach ( $order->get_tax_totals() as $i ) {
					$tax += $i->amount;
				}
				$items[] = [
					'name'      => 'Pajak',
					'qty'       => 1,
					'price'     => $tax,
					'sku'       => 'taxes-cost',
					'image_url' => ''
				];
			}
	
			if(preg_match('/va$/i', $accountObject->bank_type)){
				$customer = CustomerData::create(
					$order->get_billing_first_name() . " " .$order->get_billing_last_name(),
					$order->get_billing_email(),
					ltrim($order->get_billing_phone(), "+")
				);

				if (empty($_POST['billing_phone'])) {
					wc_add_notice('Nomor HP wajib diisi untuk pembayaran VA', 'error');
					return [
						'result' => 'failure',
						'refresh' => true
					];

				}

				if ((int)WC()->cart->total < 10000) {
					wc_add_notice('Total Pembayaran dengan Metode VA Harus Senilai Rp10.000 atau Lebih!', 'error');
					return [
						'result' => 'failure',
						'refresh' => true
					];
				}
				
				try {

					$item_fee = new \WC_Order_Item_Fee();
					
					if($with_admin_fee == 'percent') {
						$item_fees = ($order->get_total() * $admin_fee_amount) / 100;
					}

					if($with_admin_fee == 'fixed') {
						$item_fees = (float) $admin_fee_amount;
					}

					$all_total = $order->get_total() + $item_fees;
		
					$item_fee->set_name( "Biaya Admin" ); // Generic fee name
					$item_fee->set_amount( $item_fees ); // Fee amount
					$item_fee->set_tax_class( '' ); // default for ''
					$item_fee->set_tax_status( 'none' ); // or 'none'
					$item_fee->set_total( $item_fees ); // Fee amount
		
					// Add Fee item to the order
					$order->add_item( $item_fee );
		
					## ----------------------------------------------- ##
		
					$order->calculate_totals();
				} catch (Throwable $e) {
					// Log error untuk item ini dan lanjutkan ke item berikutnya
					PluginLoader::log_to_file(
						"VA Payment Field Error - Bank ID {$item['bank_id']}: " . 
						$e->getMessage() . PHP_EOL .
						"File: " . $e->getFile() . PHP_EOL .
						"Line: " . $e->getLine()
					);
				}

				$items[] = [
					'name'      => $item->get_name(),
					'qty'       => $item->get_quantity(),
					'price'     => $product->get_price(),
					'sku'       => $product->get_sku() ?? "product",
				];

				$items[] = [
					'name'		=> "Biaya Admin",
					'qty'		=> 1,
					'price'		=> $item_fees ?? 0,
					'sku'		=> "admin_tax"
				];
				
				try {
					$create_transaction = CreateTransactionData::create(
						$order_id,
						$account['bank_id'], 
						$customer,
						$items,
						$order->get_total(),
						$account['bank_type'],
						null,
						null,
						null,
						get_option('woocommerce_hold_stock_minutes', [])
					);

					$transaction = MootaApi::createTransaction($create_transaction);
				
					// Cek error response
					if (isset($transaction->errors) && $transaction->status !== 'success') {
						$errorMessage = $transaction->message ?? 'Terjadi kesalahan validasi';
						
						if (!empty($transaction->errors)) {
							foreach ($transaction->errors as $field => $messages) {
								$errorMessage .= "\n" . implode("\n", $messages);
							}
						}
						
						throw new Exception($errorMessage);
					}
					MootaWebhook::addLog(
						"Transaksi berhasil dibuat! Berikut informasi detailnya: \n" . 
						print_r($transaction, true)
					);
				
					$order->update_meta_data("moota_bank_id", $channel_id);
					$order->update_meta_data("moota_total", $all_total);
					$order->update_meta_data("moota_items", $items);
					$order->update_meta_data("moota_admin_fee", $item_fees);
					$order->update_meta_data("moota_mutation_tag", "moota_{$transaction->data->va_number}_{$all_total}");
					$order->update_meta_data("moota_redirect", $transaction->data->payment_url);
					$order->update_meta_data("moota_va_number", $transaction->data->va_number);
					$order->update_meta_data('moota_expire_at', $transaction->data->expired_at);
					$order->save();
					
					$woocommerce->cart->empty_cart();
				
					return [
						'result'   => 'success',
						'redirect' => self::get_return_url($order)
					];
				
				} catch (Exception $e) {
					// --- TAMBAHKAN RETURN FAILURE DI CATCH ---
					wc_add_notice("Kolom customer phone wajib diisi jika memilih virtual account", 'error');
					
					error_log("Moota API Error: " . $e->getMessage());
					PluginLoader::log_to_file(
						"Transaction Error: " . $e->getMessage() . PHP_EOL .
						print_r($transaction ?? null, true)
					);
					
					// Mark order as failed
					if ($order) {
						$order->update_status('failed', $e->getMessage());
					}

					$woocommerce->cart->empty_cart();
					
					return [
						'result' => 'failure',
						'refresh' => true
					];
				}
			}
			
			$end_unique_code = (int)$end_unique_code;

			if ( strlen( $start_unique_code ) < 2 ) {
				$start_unique_code = (int)sprintf( '%02d', $start_unique_code );
			}
	
			if ( $start_unique_code > $end_unique_code ) {
				$end_unique_code += 10;
			}
	
			$item_price_sum = $order->get_total();

			if($with_unique_code === "yes"){
				$unique_code = rand($start_unique_code, $end_unique_code);
			} else {
				$unique_code = 0;
			}
	
			if(array_get($bank_settings, "unique_code_type", "increase") == "increase"){
				$all_total = $item_price_sum + $unique_code;
			}
	
			if(array_get($bank_settings, "unique_code_type", "increase") == "decrease"){
				$all_total = $item_price_sum - $unique_code;
			}
	
			$note_code = $with_unique_code ? (new self)->generateRandomString(5):null;
	
			$order->update_meta_data('wc_total', $item_price_sum);
			$order->update_meta_data( "moota_bank_id", $channel_id );
			$order->update_meta_data( "moota_unique_code", $unique_code );
			$order->update_meta_data( "moota_note_code", $note_code );
			$order->update_meta_data( "moota_total", $all_total);
			$order->update_meta_data( "moota_mutation_tag", "{$channel_id}.{$all_total}");
			$order->update_meta_data( "moota_mutation_note_tag", "{$channel_id}.{$note_code}");
			
			try {
				$item_fee = new \WC_Order_Item_Fee();
				
				if(array_get($bank_settings, "unique_code_type", "increase") == "decrease"){
					$unique_code = $unique_code * -1;
				}
	
				$item_fee->set_name( "Kode Unik" ); // Generic fee name
				$item_fee->set_amount( $unique_code ); // Fee amount
				$item_fee->set_tax_class( '' ); // default for ''
				$item_fee->set_tax_status( 'none' ); // or 'none'
				$item_fee->set_total( $unique_code ); // Fee amount
	
				// Add Fee item to the order
				$order->add_item( $item_fee );
	
				## ----------------------------------------------- ##
	
				$order->calculate_totals();
			} catch(Exception $e){
				
			}
			
	
			$payment_link = self::get_return_url( $order );
	
			// Mark as on-hold (we're awaiting the cheque)
			$order->update_status( 'pending', __( 'Awaiting Payment', 'woocommerce-gateway-moota' ) );
	
			// Remove cart
			$woocommerce->cart->empty_cart();
	
			// Return thankyou redirect
			return array(
				'result'   => 'success',
				'redirect' => $payment_link
			);
		} catch (Exception $e) {
			
		}
	}

	private function generateRandomString($length = 10) {
        $characters = '2345678abcdefhjkmnpqrstuvwxyz';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $randomString;
    }

	public static function get_return_url( $order ) {
		if ( $order ) {
			$return_url = $order->get_checkout_order_received_url();
		} else {
			$return_url = wc_get_endpoint_url( 'order-received', '', wc_get_checkout_url() );
		}

		return apply_filters( 'woocommerce_get_return_url', $return_url, $order );
	}

}