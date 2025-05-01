<?php

namespace Moota\MootaSuperPlugin\EDD;

use Moota\Moota\Data\CreateTransactionData;
use Moota\Moota\Data\CustomerData;
use Moota\Moota\MootaApi;
use Moota\MootaSuperPlugin\Concerns\MootaPayment;
use Moota\MootaSuperPlugin\Contracts\MootaWebhook;

class EDDMootaQRISTransfer {
    /**
     * Instance
     */
    private static $instance;


    /**
     * Retrieve current instance
     *
     * @access private
     * @since  0.1
     * @return EDDMootaQRISTransfer instance
     */
    static function getInstance() {

        if ( ! isset( self::$instance ) && ! ( self::$instance instanceof EDDMootaQRISTransfer ) ) {
            self::$instance = new EDDMootaQRISTransfer;
        }

        return self::$instance;

    }


    /**
     * Initialize Class
     */
    public function __construct()
    {
        $moota_settings = get_option("moota_settings", []);

        add_filter( 'edd_currencies', array( $this, 'add_currency' ) );
        add_filter( 'edd_currency_symbol', array( $this, 'add_currency_symbol' ), 1, 2 );

        if(array_get($moota_settings ?? [], "moota_v2_api_key")){
            add_filter( 'edd_payment_gateways', array( $this, 'register_gateway' ), 1, 1 );
        }

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
    $banks = get_option('moota_list_accounts', []);
    $edd_settings = get_option('edd_settings', []);
    $qris_label = array_get($edd_settings, 'moota_qris_label', []);

    foreach($banks ?? [] as $bank)
    {
        // Cek apakah bank_type berakhiran 'va' atau 'qris'
        $bankType = $bank['bank_type'];
        
        if ($bankType === 'qris') {
            $gateways[$bank['bank_id']] = array(
                'admin_label' => __("Moota - " . strtoupper($bankType), 'moota-edd'),
                'checkout_label' => __($qris_label ? $qris_label : "Moota - " . strtoupper($bankType), 'moota-edd'),
                'confirmation_label' => __($qris_label ? $qris_label : "Moota - " . strtoupper($bankType), 'moota-edd'),
            );

    
            add_action( "edd_{$bank['bank_id']}_cc_form", '__return_false' );
            add_action( "edd_gateway_{$bank['bank_id']}", array( $this, 'process_payment' ) );
        }
        
    }

    return $gateways;
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
        if (!$purchase_data['post_data']['edd_phone']) {
            edd_set_error('phone_required', 'Untuk Pembayaran Via QRIS, Silahkan Masukkan No.Telp!');
            edd_send_back_to_checkout();
            return;
        }
        
        if(empty($bank_id)){
            wp_die( __( 'Nonce verification has failed', 'moota-edd' ), __( 'Error', 'moota-edd' ), array( 'response' => 403 ) );
        }

        if( ! wp_verify_nonce( $purchase_data['gateway_nonce'], 'edd-gateway' ) ) {
            wp_die( __( 'Nonce verification has failed', 'moota-edd' ), __( 'Error', 'moota-edd' ), array( 'response' => 403 ) );
        }
        global $edd_options;

        $moota_settings = get_option("moota_settings", []);
        $edd_settings = get_option('edd_settings', []);
        $banks = get_option('moota_list_accounts', []);
        $bank_label = '';

        foreach ($banks as $bank) {
            if (isset($bank['bank_id']) && $bank['bank_id'] === $bank_id) {
                $bank_label = $bank['bank_type'] ?? '';
                break;
            }
        }

        $gateway_label = 'Moota ';
        if ($bank_label !== '') {
            $gateway_label .= ' - ' . strtoupper($bank_label);
        }

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
                'status'       => 'pending',
                'gateway'      => $gateway_label
            );

            if ( ! empty( $purchase_data['user_info']['address'] ) ) {
                $payment['address1'] = $purchase_data['user_info']['address']['line1'];
                $payment['address2'] = $purchase_data['user_info']['address']['line2'];
                $payment['city']     = $purchase_data['user_info']['address']['city'];
                $payment['country']  = $purchase_data['user_info']['address']['country'];
            }

            /** Record the pending payment */
            $payment = edd_insert_payment( $payment );

            if(!$payment){
                wp_die( __( 'Nonce verification has failed', $bank_id ), __( 'Error', $bank_id ), array( 'response' => 500 ) );
            }
            
            $payment_model = new \EDD_Payment($payment);
            
            $payment_model->add_meta("bank_id", $bank_id, true);

            $items = [];

            foreach ($purchase_data['cart_details'] as $item) {
                $items[] = [
                    'name' => $item['name'],
                    'qty' => $item['quantity'],
                    'price' => $item['item_price'],
                ];

            }
            $total = 0;

            foreach ($items as $item) {
                $total += $item['price'] * $item['qty'];
            }

            if ($total < 10000) {
                edd_set_error('total_kurang', __('Untuk Menggunakan Metode Pembayaran QRIS, Total harus melebihi atau sama dengan 10.000!', 'moota-edd'));
                edd_send_back_to_checkout();
                return;
            }
            
            $customer_data = CustomerData::create(
                $purchase_data['user_info']['first_name'] . " " . $purchase_data['user_info']['last_name'],
                $purchase_data['user_info']['email'],
                $purchase_data['post_data']['edd_phone']
            );

            $create_transaction = CreateTransactionData::create(
                "{$purchase_data['purchase_key']}",
                $bank_id,
                $customer_data,
                $items,
                $total,
                $bank_label,
                "",
                "Order From Easy Digital Downloads",
                edd_get_receipt_page_uri($payment),
                null,
                false,
                edd_get_receipt_page_uri($payment),
                edd_get_receipt_page_uri($payment),
            );

            $transaction = MootaApi::createTransaction($create_transaction);
            MootaWebhook::addLog(
                "Transaksi EDD dengan Moota QRIS berhasil dibuat: \n" . 
                print_r($transaction, true)
            );

            $payment_model->add_meta("qris_url", $transaction->data->qr_url, true);

            // Empty the shopping cart
            edd_empty_cart();

            if(empty($transaction->data->payment_url)){
                wp_redirect(edd_get_receipt_page_uri($payment));
            }
            wp_redirect($transaction->data->payment_url);
            exit;
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
        $characters = '2345678abcdefhjkmnpqrstuvwxyz';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public function display_payment_detail(\EDD\Orders\Order $order)
    {

        $order_id = $order->__get("id");

        $qris_url = edd_get_order_meta($order_id, 'qris_url', true);

        $bank_id = edd_get_order_meta($order_id, 'bank_id', true);

        $edd_settings = get_option('edd_settings', []);

        $all_banks = get_option('moota_list_banks', []);

        $bank = array_filter((array)$all_banks, function($v, $k) use($bank_id){
            
            return $v['bank_id'] === $bank_id;

        }, ARRAY_FILTER_USE_BOTH );

        $bank = array_pop($bank);
        ?>
            <?php
                if(array_get($edd_settings, 'moota_qris_payment_instruction') && $bank['bank_type'] === 'qris'){
            ?>
                <h3>Instruksi Pembayaran</h3>
                <div class="p-3 border border-gray-200">

                        <?php echo nl2br($this->replacer(array_get($edd_settings, 'moota_qris_payment_instruction'), [
                            "[bank_holder]" => $bank['username'],
                            "[qr_image]" => "<img src='".$qris_url."'",
                            "[bank_logo]" => "<img src='".$bank['icon']."'",
                        ])) ?>

                    <?php } ?>
                </div>
            </div>
        <?php
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