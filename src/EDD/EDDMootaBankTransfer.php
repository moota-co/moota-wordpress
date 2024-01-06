<?php

namespace Moota\MootaSuperPlugin\EDD;
use Moota\MootaSuperPlugin\Concerns\MootaPayment;

class EDDMootaBankTransfer {


    /**
     * Instance
     */
    private static $instance;


    /**
     * Retrieve current instance
     *
     * @access private
     * @since  0.1
     * @return EDDMootaBankTransfer instance
     */
    static function getInstance() {

        if ( ! isset( self::$instance ) && ! ( self::$instance instanceof EDDMootaBankTransfer ) ) {
            self::$instance = new EDDMootaBankTransfer;
        }

        return self::$instance;

    }


    /**
     * Initialize Class
     */
    public function __construct()
    {
        add_filter( 'edd_currencies', array( $this, 'add_currency' ) );
        add_filter( 'edd_currency_symbol', array( $this, 'add_currency_symbol' ), 1, 2 );
        add_filter( 'edd_payment_gateways', array( $this, 'register_gateway' ), 1, 1 );

        if (is_admin()) {
            add_filter( 'edd_settings_sections_emails', array( $this, 'register_emails_section' ) );
            add_filter( 'edd_settings_emails', array( $this, 'register_emails_settings' ) );
        } else {
            add_action( 'get_template_part_shortcode', array( $this, 'template_shortcode_pending_receipt' ), 1, 2 );
        }

        add_filter("edd_order_receipt_after_table", [$this, 'display_payment_detail']);
    }


    /**
     * Register admin and checkout label
     * @param  array $gateways
     * @return array
     */
    public function register_gateway( $gateways ) 
    {
        $moota_settings = get_option("moota_settings");
        
        $banks = (new MootaPayment(array_get($moota_settings, "moota_v2_api_key")))->getPayments();

        foreach($banks ?? [] as $bank)
        {
            $gateways[$bank->bank_id] = array(
                'admin_label' => __("Moota Bank - {$bank->label}", $bank->bank_id),
                'checkout_label' => __("Bank Transfer - {$bank->label}", $bank->bank_id),
                'confirmation_label' => __("Bank Transfer - {$bank->label}", $bank->bank_id)
            );

            add_action( "edd_{$bank->bank_id}_cc_form", '__return_false' );
            add_action( "edd_gateway_{$bank->bank_id}", array( $this, 'process_payment' ) );

        }

        return $gateways;
    }


    /**
     * Add setting field for gateway
     * 
     * @param  array $gateway_sections
     * @return array
     */
    public function register_gateway_section( $gateway_sections ) 
    {
        $gateway_sections['moota-edd'] = __( 'Bank Transfer - Moota.co', 'moota-edd' );

        return $gateway_sections;
    }
    
    /**
     * Register email section for pending receipts
     * 
     * @param  array $settings
     * @return array
     */
    public function register_emails_section( $settings )
    {
        $settings['pending_receipts'] = __( 'Pending Receipt', 'moota-edd' );
        return $settings;
    }


    /**
     * Registers the email pending receipts settings
     * 
     * @param  array $settings
     * @return array
     */
    public function register_emails_settings( $settings )
    {
        $moota_settings = array(
            'pending_receipt_email_settings' => array(
                'id'   => 'pending_receipt_email_settings',
                'name' => '',
                'desc' => '',
                'type' => 'hook',
            ),
            'pending_subject' => array(
                'id'   => 'pending_subject',
                'name' => __( 'Pending Email Subject', 'moota-edd' ),
                'desc' => __( 'Enter the subject line for the pending receipt email.', 'moota-edd' ),
                'type' => 'text',
                'std'  => __( 'Pending Receipt', 'moota-edd' ),
            ),
            'pending_heading' => array(
                'id'   => 'pending_heading',
                'name' => __( 'Pending Email Heading', 'moota-edd' ),
                'desc' => __( 'Enter the heading for the pending receipt email.', 'moota-edd' ),
                'type' => 'text',
                'std'  => __( 'Pending Receipt', 'moota-edd' ),
            ),
            'pending_receipt' => array(
                'id'   => 'pending_receipt',
                'name' => __( 'Pending Receipt', 'moota-edd' ),
                'desc' => __('Enter the text that is sent as pending receipt email to users after completion of a checkout. HTML is accepted. Available template tags:','moota-edd' ) . '<br/>' . edd_get_emails_tags_list(),
                'type' => 'rich_editor',
                'std'  => "",
            ),
        );

        $settings['pending_receipts'] = $moota_settings;
        return $settings;
    }


    /**
     * Process payment on checkout
     */
    public function process_payment( $purchase_data ) 
    {
        $bank_id = $purchase_data['gateway'];

        if(empty($bank_id)){
            wp_die( __( 'Nonce verification has failed', 'moota-edd' ), __( 'Error', 'moota-edd' ), array( 'response' => 403 ) );
        }

        if( ! wp_verify_nonce( $purchase_data['gateway_nonce'], 'edd-gateway' ) ) {
            wp_die( __( 'Nonce verification has failed', 'moota-edd' ), __( 'Error', 'moota-edd' ), array( 'response' => 403 ) );
        }
        global $edd_options;

        $moota_settings = get_option("moota_settings");

        $errors = edd_get_errors();
        
        if ( ! $errors ) {

            /** setup the payment details to be stored */
            $payment = array(
                'price'        => $purchase_data['price'],
                'date'         => $purchase_data['date'],
                'user_email'   => $purchase_data['user_email'],
                'purchase_key' => $purchase_data['purchase_key'],
                'currency'     => $edd_options['currency'],
                'downloads'    => $purchase_data['downloads'],
                'cart_details' => $purchase_data['cart_details'],
                'user_info'    => $purchase_data['user_info'],
                'status'       => 'pending'
            );

            if ( ! empty( $purchase_data['user_info']['address'] ) ) {
                $payment['address1'] = $purchase_data['user_info']['address']['line1'];
                $payment['address2'] = $purchase_data['user_info']['address']['line2'];
                $payment['city']     = $purchase_data['user_info']['address']['city'];
                $payment['country']  = $purchase_data['user_info']['address']['country'];
            }

            $unique_code = array_get($moota_settings, "enable_moota_unique_code");
            $unique_name = "Kode Unik";
            $unique_type = array_get($moota_settings, "unique_code_type", "increase");
            $unique_start = array_get($moota_settings, "moota_unique_code_start", 1);
            $unique_end = array_get($moota_settings, "moota_unique_code_end", 1);
            $unique_verification = array_get($moota_settings, "unique_code_verification_type", "nominal");

            if ( $unique_code  && (int) $purchase_data['price'] > 0 && $unique_verification == "nominal") {
                $amount = rand($unique_start, $unique_end);
                if ($unique_type == 'decrease') {
                    $amount = $amount * -1;
                }
                EDD()->fees->add_fee( $amount, $unique_name, 'moota_unique_code' );
            }

            /** Record the pending payment */
            $payment = edd_insert_payment( $payment );

            if(!$payment){
                wp_die( __( 'Nonce verification has failed', $bank_id ), __( 'Error', $bank_id ), array( 'response' => 500 ) );
            }

            $payment_model = new \EDD_Payment($payment);

            $payment_model->add_meta("bank_id", $bank_id, true);

            if($unique_verification == "news"){
                $payment_model->add_meta("news_code", $this->generateRandomString(5), true);
            }

            EDD()->fees->remove_fee( 'moota_unique_code' );

            // Empty the shopping cart
            edd_empty_cart();

            wp_redirect(edd_get_receipt_page_uri($payment));

        }
    }


    /**
     * Add Indonesia Rupiah Currency
     */
    public function add_currency( $currencies )
    {
        $currencies['IDR'] = __('Indonesia Rupiah (Rp)', 'moota-edd');

        return $currencies;
    }


    /**
     * Add currency symbol Rupiah (Rp)
     */
    public function add_currency_symbol( $symbol, $currency )
    {
        if ( $currency == 'IDR' ) {
            $symbol = 'Rp';
        }

        return $symbol;
    }


    /**
     * Display summary payment for costumer
     * 
     * @param  string $shortcode 
     * @param  string $shortcode_name
     * @return string
     */
    public function template_shortcode_pending_receipt( $shortcode, $shortcode_name )
    {
        // print_r("test template");

        if ( $shortcode != 'shortcode' || $shortcode_name != 'receipt') {
            return "test";
        }
        
        global $edd_receipt_args, $edd_options;

        /** Get variable payment */
        // print_r($edd_options);

        $payment_id     = $edd_receipt_args["id"];
        $payment_method = edd_get_payment_gateway( $payment_id );
        $status         = edd_get_payment_status( $payment_id, true );
        $message        = "";  
        if(isset($edd_options['pending_receipt'])){
            $message    = edd_do_email_tags( $edd_options['pending_receipt'], $payment_id );
        }
        /** Check if payemnt method is not moota_edd than return false */
        if ( $payment_method != 'moota_edd' || $status != 'Pending' ) {
            return;
        }

        /** Display notice on edd receipts */
        echo wpautop($message);
    }

    private function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public function display_payment_detail(\EDD\Orders\Order $order)
    {
        global $wpdb;

        $order_id = $order->__get("id");

        $sql = "SELECT meta_value as bank_id from {$wpdb->edd_ordermeta} where meta_key='bank_id' and edd_order_id='{$order_id}'";

        

        $result = $wpdb->get_row($sql);

        $moota_settings = get_option("moota_settings");

        $unique_verification = array_get($moota_settings, "unique_code_verification_type", "nominal");

        $all_banks = (new MootaPayment(array_get($moota_settings, "moota_v2_api_key")))->getPayments();

        $bank = array_filter((array)$all_banks, function($v, $k) use($result){
            
            return $v->bank_id === $result->bank_id;

        }, ARRAY_FILTER_USE_BOTH );

        $bank = array_pop($bank);

        ?>
            <div class="space-y-3">
                <h3>
                    Transfer
                </h3>
                <div class="p-3 border border-gray-200">
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
                        if($unique_verification == 'news'){
                            $sql2 = "SELECT meta_value as news_code from {$wpdb->edd_ordermeta} where meta_key='news_code' and edd_order_id='{$order_id}'";

                            $result2 = $wpdb->get_row($sql2);

                            ?>
                            <div>
                                Masukan kode <span class="px-2 py-1 bg-green-500 text-white font-bold rounded-md"> <?php echo $result2->news_code ?></span> didalam berita transfer untuk transaksi otomatis!
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