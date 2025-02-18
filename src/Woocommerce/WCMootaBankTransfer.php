<?php

namespace Moota\MootaSuperPlugin\Woocommerce;

use Exception;
use Moota\MootaSuperPlugin\Concerns\MootaPayment;
use Moota\MootaSuperPlugin\Contracts\MootaTransaction;
use WC_Payment_Gateway;

class WCMootaBankTransfer extends WC_Payment_Gateway
{
    private $bank_selection = [];

    public $list_banks = [];
    public $enable_unique_code;
    public $interval;
    public $unique_code_start;
    public $unique_code_end;
    public $unique_code_type;
    public $all_banks = [];
    public $payment_description;

	public function __construct() {
		$this->id                 = 'wc-super-moota-bank-transfer';
		$this->has_fields         =  true;
		$this->method_title       = 'Bank Transfer';
		$this->method_description = 'Terima Pembayaran langsung ke masuk kerekening tanpa biaya per-transaksi. Mendukung Banyak Bank Nasional';

        $this->init_form_fields();
		$this->init_settings();

		// Populate Values settings
		$this->enabled              = $this->get_option('enabled');
        $this->title                = $this->get_option('title');
        $this->description          = $this->get_option('description');
        $this->payment_description  = $this->get_option('payment_description');
        $this->enable_unique_code   = $this->get_option('enable_moota_unique_code');
        $this->unique_code_start    = $this->get_option('moota_unique_code_start');
        $this->unique_code_end      = $this->get_option('moota_unique_code_end');
        $this->unique_code_type     = $this->get_option('enable_unique_type');

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [$this,'process_admin_options'] );
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'save_list_banks' ) );

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
                'title'         => 'Checkout Message',
                'type'          => 'textarea',
                'css'           => 'width:500px;',
                'default'       => 'Terima Pembayaran langsung masuk kerekening tanpa biaya per-transaksi. Mendukung Banyak Bank Nasional',
                'description'   => 'The message which you want it to appear to the customer in the checkout page.',
            ),
            'payment_description' => array(
                'title'         => 'Thanks Page (Order) Message',
                'type'          => 'textarea',
                'css'           => 'width:500px',
                'default'       => 'Harap untuk transfer sesuai dengan jumlah yang sudah ditentukan sampai 3 digit terakhir atau masukan kode [unique_note] kedalam berita / note transfer. 
                Transfer Ke Bank [bank_name] 
                [bank_logo]
                [bank_account] A/n [bank_holder]',
                'description'   => '<div>Gunakan Replacer Berikut:</div>
                <div>Logo Bank : <b>[bank_logo]</b> </div>
                <div>Nama Bank : <b>[bank_name]</b> </div>
                <div>Nomor Rekening : <b>[bank_account]</b> </div>
                <div>Atas Nama Bank : <b>[bank_holder]</b> </div>
                <div>Kode Unik : <b>[unique_code]</b> </div>
                <div>Kode Unik Note (untuk berita/note transaksi) : <b>[unique_note]</b> </div>',
            ),
            'enable_moota_unique_code' => [
                'title' => 'Unique code',
                'type'  => 'checkbox',
                'description' => 'Aktifkan untuk Menambahkan Kode unik di setiap Transaksi.',
                'label' => 'Aktifkan Kode Unik'
            ],
            'moota_unique_code_start' => [
                'title' => 'Unique code Start',
                'type'  => 'number',
                'description' => 'Nominal minimal kode unik pembayaran',
                'label' => 'Nominal minimal kode unik pembayaran'
            ],
            'moota_unique_code_end' => [
                'title' => 'Unique code End',
                'type'  => 'number',
                'description' => 'Nominal maksimal kode unik pembayaran',
                'label' => 'Angka Akhir Kode Unik'
            ],
            'unique_code_type' => [
                'title' => 'Unique code Type',
                'type'  => 'select',
                'options' => [
                    'increase' => 'Menaikkan Total Transaksi',
                    'decrease' => 'Menurunkan Total Transaksi'
                ],
                'description' => 'Angka Awal untuk sebuah kode unik'
            ],
            'bank_lists' => [
                'title' => 'Bank Lists',
                'type' => 'bank_lists'
            ]
        );
        // die(var_dump($_POST));
    }

	// Custom Validate
	public function validate_bank_lists_field( $key, $value ) {
		return $value;
	}

    public function generate_bank_lists_html() {
        $account_option = get_option('moota_list_banks', []);
        $moota_settings = get_option('moota_settings', []);

        if(empty($account_option)) {
            $fetched_accounts = (new MootaPayment(array_get($moota_settings ?? [], "moota_v2_api_key")))->getBanks();
            try {
                update_option('moota_list_banks', $fetched_accounts);
                wp_redirect(add_query_arg());
                exit;
            } catch (Exception $e) {
                error_log("Gak jalan bro: " . $e->getMessage());
            }
        }

        $account_option = array_map(function($item) {
            return is_array($item) ? (object) $item : $item;
        }, $account_option);

        // var_dump($account_option);
        // die();

        ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label>
					<?php esc_html_e( 'Account details:', 'woocommerce' ); ?>
					<?php echo wp_kses_post( wc_help_tip( __( 'These account details will be displayed within the order thank you page and confirmation email.', 'woocommerce' ) ) ); ?>
				</label>
			</th>
			<td class="forminp" id="moota_accounts">
				<div class="wc_input_table_wrapper">
					<table class="widefat wc_input_table sortable" cellspacing="0">
						<thead>
							<tr>
								<th class="sort">&nbsp;</th>
                                <th style="width: auto;">&nbsp;</th>
								<th><?php esc_html_e( 'Bank name', 'woocommerce' ); ?></th>
								<th><?php esc_html_e( 'Bank ID', 'woocommerce' ); ?></th>
                                <th><?php esc_html_e( 'Bank Type', 'woocommerce' ); ?></th>
                                <th><?php esc_html_e( 'Bank Label', 'woocommerce' ); ?></th>
							</tr>
						</thead>
						<tbody class="accounts">
                        <?php
                        $i = -1;
                        if (!empty($account_option)) {
                            foreach ($account_option as $account) {
                            $i++;
                            if (!preg_match('/va$/i', $account->bank_type) && $account->bank_type != 'winpayProduction') {
                            ?>
                            <tr class="account">
                            <td class="sort"></td>
                            <td style="padding: 6px; text-align: center;">
                                <input type="hidden" name="moota_bank_number[<?php echo esc_attr($i); ?>]" value="<?php echo $account->account_number; ?>">
                                <input type="hidden" name="moota_bank_icon[<?php echo esc_attr($i); ?>]" value="<?php echo $account->icon; ?>">
                                <input type="hidden" name="moota_enable_bank[<?php echo esc_attr($i); ?>]" value="no">
                                <input type="checkbox" 
                                    name="moota_enable_bank[<?php echo esc_attr($i); ?>]"
                                    value="yes"
                                    style="vertical-align: middle;"
                                    <?php 
                                    if (property_exists($account, 'enable_bank') && $account->enable_bank === 'yes') {
                                        echo "checked";
                                    }
                                    ?>
                                />
                            </td>
                            <td>
                                <input type="text" 
                                           value="<?php echo esc_attr(wp_unslash($account->username)); ?>" 
                                           name="moota_bank_name[<?php echo esc_attr($i); ?>]" readonly />
                                </td>
                                <td>
                                    <input type="text" 
                                           value="<?php echo esc_attr($account->bank_id); ?>" 
                                           name="moota_bank_id[<?php echo esc_attr($i); ?>]" readonly />
                                </td>
                                <td>
                                    <input type="text" 
                                           value="<?php echo esc_attr(wp_unslash($account->bank_type)); ?>" 
                                           name="moota_bank_type[<?php echo esc_attr($i); ?>]" readonly />
                                </td>
                                <td>
                                        <input type="text"
                                            name="moota_bank_label[<?php echo esc_attr($i); ?>]" 
                                           value="<?php echo esc_attr(
                                  property_exists($account, 'bank_label') 
                                        ? $account->bank_label 
                                        : (
                                        preg_match('/^(.*?)(?:va|v\d+|syariah|bisnis)/i', $account->bank_type, $matches)
                                        ? strtoupper(rtrim($matches[1], ' -_')) . ' - Bank Transfer'
                                        : strtoupper($account->bank_type) . ' - Bank Transfer'
                                        )
                                        ); ?>"
                                            />
                                        </td>
                                    </tr>
                                    <?php
                                    }
                                }
                            }
                            ?>
						</tbody>
					</table>
                    <div style="display: flex; align-items: baseline; border-left: 3px solid #007cba; padding-left: 12px; margin: 10px 0;">
                        <p style="font-weight: 700; margin: 0 12px 0 0; min-width: 70px; color: #000;">Bank Label</p>
                        <span style="color: #50575e; font-size: 14px; line-height: 1.5;">
                            Digunakan untuk Penamaan Metode Pembayaran di Halaman Order (Thanks Page).
                        </span>
                    </div>
                    <div style="display: flex; align-items: baseline; border-left: 3px solid #007cba; padding-left: 12px; margin: 10px 0;">
                        <p style="font-weight: 700; margin: 0 12px 0 0; min-width: 70px; color: #000;">Contoh :</p>
                        <span style="color: #50575e; font-size: 14px; line-height: 1.5;">
                            BCA Bank Transfer - PT. XXXX, BNI - Example Official Store
                        </span>
                    </div>
				</div>
				<script type="text/javascript">
					jQuery(function() {
						jQuery('#moota_accounts').on( 'click', 'a.add', function(){

							var size = jQuery('#moota_accounts').find('tbody .account').length;

							jQuery('<tr class="account">\
									<td class="sort"></td>\
									<td><input type="text" name="moota_bank_name[' + size + ']" /></td>\
									<td><input type="text" name="moota_bank_id[' + size + ']" /></td>\
									<td><input type="text" name="moota_bank_type[' + size + ']" /></td>\
									<td><input type="text" name="moota_bank_label[' + size + ']" /></td>\
                                    </tr>').appendTo('#moota_accounts table tbody');

							return false;
						});
					});
				</script>
			</td>
		</tr>
		<?php
		return ob_get_clean();

    }

    public function save_list_banks() {
        $accounts = array();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verification already handled in WC_Admin_Settings::save()
		if ( isset( $_POST['moota_bank_id'] ) && isset( $_POST['moota_bank_name'] ) && isset( $_POST['moota_bank_type'] )
			 && isset( $_POST['moota_bank_label'] ) && isset($_POST['moota_enable_bank']) && isset($_POST['moota_bank_icon'])
             && isset($_POST['moota_bank_number']) ) {

            $account_number     = wc_clean(wp_unslash($_POST['moota_bank_number']));
            $icon               = wc_clean(wp_unslash($_POST['moota_bank_icon']));
            $enable_account     = wc_clean( wp_unslash( $_POST['moota_enable_bank']));
			$account_name       = wc_clean( wp_unslash( $_POST['moota_bank_name'] ) );
			$account_channel_id = wc_clean( wp_unslash( $_POST['moota_bank_id'] ) );
			$bank_names         = wc_clean( wp_unslash( $_POST['moota_bank_type'] ) );
			$account_label      = wc_clean( wp_unslash( $_POST['moota_bank_label'] ) );

			foreach ( $account_name as $i => $name ) {
				if ( ! isset( $account_name[ $i ] ) ) {
					continue;
				}

				$accounts[] = array(
                    'account_number'        => $account_number[$i],
                    'icon'                  => $icon[$i],
                    'enable_bank'           => $enable_account[ $i ],
					'username'              => $account_name[ $i ],
					'bank_id'               => $account_channel_id[ $i ],
					'bank_type'             => $bank_names[ $i ],
					'bank_label'            => $account_label[ $i ]
				);
			}
		}
		// phpcs:enable
        // var_dump($accounts);
        // die();
		do_action( 'woocommerce_update_option', array( 'id' => 'moota_list_banks' ) );
		update_option( 'moota_list_banks', $accounts );
    }

	/**
	 * Handle WooCommerce Checkout
	 */
	private function bank_selection( $bank_id ) {

        if ( empty($this->all_banks) ) {
            $moota_settings     = get_option("moota_list_banks", []);
            // $this->all_banks = (new MootaPayment(array_get($moota_settings, "moota_v2_api_key")))->getBanks();
        }

        if ( ! empty($moota_settings) ) {
            foreach ($moota_settings as $bank) {
                if ( $bank_id == $bank['bank_id'] ) {
                    return $bank;
                }
            }
        }
		return [];
	}

	public function payment_fields() {
        $moota_settings = get_option("moota_list_banks", []);
        // var_dump($moota_settings);
        //     die();
        ?>
		 <ul>
             <?php if (!empty($moota_settings)) : ?>
                <h3>Moota Bank Transfer</h3> <br>
                <?php foreach ($moota_settings as $item) : 
                $bank_selection = $this->bank_selection($item['bank_id']);
                if ($bank_selection['enable_bank'] == "yes") : ?>
                    <li style="display: flex; align-items: center;">
                        <label for="bank-transfer-<?php echo esc_attr($bank_selection['bank_type']); ?>-bank-id-<?php echo esc_attr($item['bank_id']); ?>" class="flex gap-3 items-center">
                            <input id="bank-transfer-<?php echo esc_attr($bank_selection['bank_type']); ?>-bank-id-<?php echo esc_attr($item['bank_id']); ?>" name="channels" type="radio"
                                   value="<?php echo esc_attr($item['bank_id']); ?>">
                            <img src="<?= $bank_selection['icon']; ?>" >
                            <span class="moota-bank-account">
                                <?php echo esc_attr($bank_selection['bank_label']); ?>
                            </span>
                        </label>
                    </li>
                <?php endif; 
            endforeach; ?>
        <?php endif; ?>
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

        $moota_settings = get_option("woocommerce_" . $this->id . "_settings", []);

        $channel_id = sanitize_text_field( $_POST['channels'] );
        $with_unique_code = array_get($moota_settings, "enable_moota_unique_code", true);
        $unique_start = array_get($moota_settings, "moota_unique_code_start", 0);
        $unique_end = array_get($moota_settings, "moota_unique_code_end", 999);

		return MootaTransaction::request($order_id, $channel_id, $with_unique_code, '', '', $unique_start, $unique_end);
	}

    public function order_details($order) {
        if ( $order->get_payment_method() == $this->id ) {
            $kodeunik = null;
            $bank_id = null;
            $total = null;
            $note_code = null;

            $moota_settings = get_option("moota_list_banks", []);
            $instruction = get_option("woocommerce_wc-super-moota-bank-transfer_settings", []);

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

            $bank = array_filter($moota_settings, function($v, $k) use($bank_id){
                
                return $v['bank_id'] === $bank_id;

            }, ARRAY_FILTER_USE_BOTH );

            $bank = array_pop($bank);
            
            ?>
            <div class="space-y-3 py-3">
                <h3>
                    Transfer
                </h3>
                <div class="p-3 border border-gray-200">
                    <?php
                        if(array_get($instruction, 'payment_description')){
                    ?>

                        <?php echo nl2br($this->replacer(array_get($instruction, 'payment_description'), [
                            "[bank_account]" => $bank['account_number'],
                            "[unique_note]" => "<span class='px-2 py-1 bg-green-500 text-white font-bold rounded-md'> ".$note_code."</span>",
                            "[bank_name]" => $bank['bank_label'],
                            "[bank_holder]" => $bank['username'],
                            "[bank_logo]" => "<img src='".$bank['icon']."'>"
                            ])) ?>
                    <?php } else { ?>

                        <figure>
                            <img src="<?php echo $bank->icon; ?>" alt="">
                        </figure>
                        <div class="flex flex-col gap-1 text-sm">
                            <div>
                                Transfer ke Bank <strong><?php echo $bank->bank_type; ?></strong>
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
                            bank_id:"<?php echo esc_attr($bank['bank_id']); ?>"
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