<?php

namespace Moota\MootaSuperPlugin\Contracts;
use Moota\MootaSuperPlugin\Concerns\MootaPayment;
use WC_Order;

class MootaTransaction
{
    public static function request( $order_id, $channel_id, $with_unique_code, $start_unique_code, $end_unique_code, $payment_method_type = 'bank_transfer') {
		global $woocommerce;
		$order = new WC_Order( $order_id );

		$moota_settings = get_option("moota_settings", []);

		$unique_verification = array_get($moota_settings, "unique_code_verification_type", "nominal");

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

			$items[] = [
				'name'      => $item->get_name(),
				'qty'       => $item->get_quantity(),
				'price'     => $product->get_price() * $item->get_quantity(),
				'sku'       => $product->get_sku() ?? "product",
				'image_url' => $image_url
			];
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

		if ( strlen( $start_unique_code ) < 2 ) {
			$start_unique_code = sprintf( '%02d', $start_unique_code );
		}

		if ( $start_unique_code > $end_unique_code ) {
			$end_unique_code += 10;
		}

        $item_price_sum = 0;

        foreach($items as $item){
            $item_price_sum += $item['price'];
        }

        $unique_code = $with_unique_code ? rand($start_unique_code, $end_unique_code):0;

		if(array_get($moota_settings, "unique_code_type", "increase") == "increase"){
			$all_total = $item_price_sum + $unique_code;
		}

		if(array_get($moota_settings, "unique_code_type", "increase") == "decrease"){
			$all_total = $item_price_sum - $unique_code;
		}

		$note_code = $with_unique_code ? (new self)->generateRandomString(5):null;

		if($unique_verification == "news"){
			$unique_code = (new self)->generateRandomString(5);

			$all_total = $item_price_sum;
		}

        $order->update_meta_data( "bank_id", $channel_id );
		$order->update_meta_data( "unique_code", $unique_code );
		$order->update_meta_data( "note_code", $note_code );
		$order->update_meta_data( "total", $all_total);
        $order->update_meta_data( "mutation_tag", "{$channel_id}.{$all_total}");
		$order->update_meta_data( "mutation_note_tag", "{$channel_id}.{$note_code}");

		$payment_link = self::get_return_url( $order );

		// Mark as on-hold (we're awaiting the cheque)
		$order->update_status( 'on-hold', __( 'Awaiting Payment', 'woocommerce-gateway-moota' ) );

		// Remove cart
		$woocommerce->cart->empty_cart();

		// Return thankyou redirect
		return array(
			'result'   => 'success',
			'redirect' => $payment_link
		);
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