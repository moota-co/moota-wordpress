<?php

namespace Moota\MootaSuperPlugin\Woocommerce;
use Moota\MootaSuperPlugin\Concerns\MootaPayment;
use Moota\MootaSuperPlugin\Contracts\MootaTransaction;
use WC_Payment_Gateway;

class WCMootaBankTransfer extends WC_Payment_Gateway
{
    private $bank_selection = [];

    public $all_banks = [];

	public function __construct() {
		$this->id                 = 'wc-super-moota-bank-transfer';
		$this->has_fields         = true;
		$this->method_title       = 'Bank Transfer';
		$this->method_description = 'Terima Pembayaran langsung ke masuk kerekening tanpa biaya per-transaksi. Mendukung Banyak Bank Nasional';

        $this->init_form_fields();
		$this->init_settings();

		// Populate Values settings
		$this->enabled = $this->get_option('enabled');
        $this->title = $this->get_option( 'title' );
        $this->description = $this->get_option('description');

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [
			$this,
			'process_admin_options'
		] );
		
        // var_dump($this->payment_fields());

		// custom fields
		add_filter( 'woocommerce_generate_bank_lists_html', [ $this, 'bank_lists_bank' ], 99, 4 );
		add_filter( 'woocommerce_settings_api_sanitized_fields_' . $this->id, function ( $settings ) {
			return $settings;
		} );

        add_action('woocommerce_order_details_after_order_table', [$this, 'order_details'], 99);
	}

	public function init_form_fields(){
        $this->form_fields = array(
            'enabled' => array(
                'title'         => 'Enable/Disable',
                'type'          => 'checkbox',
                'label'         => 'Enable Moota Bank Transfer Payment Gateway',
                'default'       => 'yes'
            ),
            'title' => array(
                'title'         => 'Method Title',
                'type'          => 'text',
                'description'   => 'This controls the payment method title',
                'default'       => 'Moota Bank Transfer',
                'desc_tip'      => true,
            ),
            'description' => array(
                'title'         => 'Customer Message',
                'type'          => 'textarea',
                'css'           => 'width:500px;',
                'default'       => 'Terima Pembayaran langsung ke masuk kerekening tanpa biaya per-transaksi. Mendukung Banyak Bank Nasional',
                'description'   => 'The message which you want it to appear to the customer in the checkout page.',
            )
        );
    }

	// Custom Validate
	public function validate_bank_lists_field( $key, $value ) {
		return $value;
	}

	/**
	 * Handle WooCommerce Checkout
	 */
	private function bank_selection( $bank_id ) {

        if ( empty($this->all_banks) ) {
            $moota_settings = get_option("moota_settings", []);

            $this->all_banks = (new MootaPayment(array_get($moota_settings, "moota_v2_api_key")))->getBanks();
        }

        if ( ! empty($this->all_banks) ) {
            foreach ($this->all_banks as $bank) {
                if ( $bank_id == $bank->bank_id ) {
                    return $bank;
                }
            }
        }

		return [];
	}

	public function payment_fields() {
        $moota_settings = get_option("moota_settings", []);

        
        $banks = (new MootaPayment(array_get($moota_settings ?? [], "moota_v2_api_key")))->getPayments();
        
        
		 ?>
		 <ul>
		 <?php if ( ! empty( $banks ) ) :
             foreach ( $banks as $item ) :
                    $bank_selection = $this->bank_selection( $item->bank_id );
                    // die(json_encode($bank_selection));
                 ?>
                 <li>
                     <label for="bank-transfer-<?php echo esc_attr($bank_selection->label); ?>-bank-id-<?php echo esc_attr($item->bank_id); ?>" class="flex gap-3 items-center">
                        <input id="bank-transfer-<?php echo esc_attr($bank_selection->label); ?>-bank-id-<?php echo esc_attr($item->bank_id); ?>" name="channels" type="radio"
                        value="<?php echo esc_attr($item->bank_id); ?>">
                        <span>
                            <img src="<?php echo esc_attr($bank_selection->icon);?>" alt="<?php echo esc_attr($bank_selection->label); ?>">
                        </span>
                        <span class="moota-bank-account">
                            <?php echo esc_attr($bank_selection->label); ?> <?php echo esc_attr($bank_selection->account_number); ?> An. (<?php echo esc_attr($bank_selection->atas_nama); ?>)
                        </span>
                     </label>
                 </li>
             <?php endforeach;
         endif; ?>
		 </ul>
		 <?php
		 $description = $this->get_description();
         if ( $description ) {
            echo esc_attr($description); // @codingStandardsIgnoreLine.
         }

        // wc_add_notice( \_\_('Payment error:', 'woothemes') . $error_message, 'error' );
	}

	public function validate_fields():bool {
		if ( empty( $_POST['channels'] ) ) {
			wc_add_notice( '<strong>Channel Pembayaran</strong> Pilih Channel Pembayaran', 'error' );

			return false;
		}

		return true;
	}

	public function process_payment( $order_id ) {

        $moota_settings = get_option("moota_settings", []);

        $channel_id = sanitize_text_field( $_POST['channels'] );
        $with_unique_code = array_get($moota_settings, "enable_moota_unique_code", true);
        $unique_start = array_get($moota_settings, "moota_unique_code_start", 0);
        $unique_end = array_get($moota_settings, "moota_unique_code_end", 999);

		return MootaTransaction::request($order_id, $channel_id, $with_unique_code, $unique_start, $unique_end, 'bank_transfer');
	}

    public function order_details($order) {
        if ( $order->get_payment_method() == $this->id ) {
            $kodeunik = null;
            $bank_id = null;
            $total = null;
            $note_code = null;

            $moota_settings = get_option("moota_settings", []);

            $unique_verification = array_get($moota_settings, "unique_code_verification_type", "nominal");
			
			foreach ($order->get_meta_data() as $object) {
			  $object_array = array_values((array)$object);
			  foreach ($object_array as $object_item) {
				if ('bank_id' == $object_item['key']) {
				  $bank_id = $object_item['value'];
				  break;
				}
				  
				if ('unique_code' == $object_item['key']) {
				  $kodeunik = $object_item['value'];
				  break;
				}
				 
				if ('total' == $object_item['key']) {
				  $total = $object_item['value'];
				  break;
				}

                if ('note_code' == $object_item['key']) {
                    $note_code = $object_item['value'];
                    break;
                  }

			  }
			}

            $all_banks = (new MootaPayment(array_get($moota_settings ?? [], "moota_v2_api_key")))->getPayments();

            $bank = array_filter((array)$all_banks, function($v, $k) use($bank_id){
                
                return $v->bank_id === $bank_id;

            }, ARRAY_FILTER_USE_BOTH );

            $bank = array_pop($bank);

            // $payment_link = get_post_meta($order->get_id(), 'payment_link', true );
            ?>

            <table class="wc-block-order-confirmation-totals__table ">
               <tr>
                    <th scope="row">Kode Unik</th>
                    <td class="wc-block-order-confirmation-totals__total"><?php echo wc_price($kodeunik); ?></td>
               </tr>
               <tr>
                   <th scope="row">Nominal Yang Harus Dibayar</th>
                   <td class="wc-block-order-confirmation-totals__total"><?php echo wc_price($total);?></td>
               </tr>
            </table>

            <div class="space-y-3 py-3">
                <h3>
                    Transfer
                </h3>
                <div class="p-3 border border-gray-200">
                    <?php
                        if(array_get($moota_settings, 'payment_instruction')){
                    ?>

                        <?php echo nl2br($this->replacer(array_get($moota_settings, 'payment_instruction'), [
                            "[bank_account]" => $bank->account_number,
                            "[unique_note]" => "<span class='px-2 py-1 bg-green-500 text-white font-bold rounded-md'> ".$note_code."</span>",
                            "[bank_name]" => $bank->label,
                            "[bank_holder]" => $bank->atas_nama,
                            "[check_button]" => "<button id='moota-get-mutation-button' class='text-white font-semibold px-4 py-2 bg-sky-300 rounded-lg'>Check Status Pembayaran</button>",
                            "[bank_logo]" => "<img src='".$bank->icon."'>"
                        ])) ?>

                    <?php } else { ?>

                        <figure>
                            <img src="<?php echo $bank->icon; ?>" alt="">
                        </figure>
                        <div class="flex flex-col gap-1 text-sm">
                            <div>
                                Transfer ke Bank <strong><?php echo $bank->label; ?></strong>
                            </div>
                            <div class="font-semibold">
                                <?php echo $bank->account_number; ?> a.n <?php echo $bank->atas_nama; ?>
                            </div>
                            <?php
                            if(!empty($note_code)){
                                ?>
                                <div>
                                    Atau Anda bisa memasukan kode berikut : <span class="px-2 py-1 bg-green-500 text-white font-bold rounded-md"> <?php echo $note_code ?></span> didalam berita transfer untuk transaksi otomatis!
                                </div>
                            <?php
                                }
                            ?>
                        </div>
                        <div class="py-2">
                            <button id="moota-get-mutation-button" class="text-white font-semibold px-4 py-2 bg-sky-300 rounded-lg">
                                Check Status Pembayaran
                            </button>
                        </div>

                    <?php } ?>

                    <script>
                        var gm_button = document.getElementById("moota-get-mutation-button");

                        async function postData(url = "", data = {}) {
                            toastr.info('Data sedang dicheck!');


                            // Default options are marked with *
                            const response = await fetch(url, {
                                method: "POST", // *GET, POST, PUT, DELETE, etc.
                                mode: "cors", // no-cors, *cors, same-origin
                                cache: "no-cache", // *default, no-cache, reload, force-cache, only-if-cached
                                credentials: "same-origin", // include, *same-origin, omit
                                headers: {
                                "Content-Type": "application/json",
                                // 'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                redirect: "follow", // manual, *follow, error
                                referrerPolicy: "no-referrer", // no-referrer, *no-referrer-when-downgrade, origin, origin-when-cross-origin, same-origin, strict-origin, strict-origin-when-cross-origin, unsafe-url
                                body: JSON.stringify(data), // body data type must match "Content-Type" header
                            });

                            location.reload();
                        }


                        gm_button.addEventListener("click", () => postData("/wp-json/internal/get-mutation-now", {
                            bank_id:"<?php echo esc_attr($bank->bank_id); ?>"
                        }));

                    </script>
                </div>
            </div>

            <?php
        }
    }

    private function replacer(string $template, array $data)
    {
        $parsed = $template;

        foreach($data as $key => $value){
            $parsed = str_replace($key, $value, $parsed);
        }

        return $parsed;
    }
}