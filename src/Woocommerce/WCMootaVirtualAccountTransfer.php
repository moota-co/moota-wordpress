<?php

namespace Moota\MootaSuperPlugin\Woocommerce;

use DateTime;
use DateTimeZone;
use Exception;
use Moota\MootaSuperPlugin\Concerns\MootaPayment;
use Moota\MootaSuperPlugin\Contracts\MootaTransaction;
use Moota\MootaSuperPlugin\PluginLoader;
use Throwable;
use WC_Payment_Gateway;

class WCMootaVirtualAccountTransfer extends WC_Payment_Gateway
{
    public $all_banks = [];

    public function __construct()
    {
        $this->id                 = 'wc-super-moota-virtual-transfer';
        $this->has_fields         = true;
        $this->method_title       = 'Virtual Account Transfer (Whitelabel)';
        $this->method_description = 'Terima Pembayaran langsung ke masuk kerekening tanpa biaya per-transaksi. Mendukung Banyak Bank Nasional';

        $this->init_form_fields();
        $this->init_settings();

        // Populate Values settings
        $this->enabled = $this->get_option('enabled');
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'save_list_accounts'));

        // custom fields
        add_filter('woocommerce_generate_bank_lists_html', [$this, 'bank_lists_bank'], 99, 4);
        add_filter('woocommerce_settings_api_sanitized_fields_' . $this->id, function ($settings) {
            return $settings;
        });

        add_action('woocommerce_order_details_after_order_table', [$this, 'order_details'], 99);
    }

    public function init_form_fields()
    {
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
                'default'       => 'Moota Virtual Account',
                'desc_tip'      => true,
            ),
            'description' => array(
                'title'         => 'Customer Message',
                'type'          => 'textarea',
                'css'           => 'width:500px;',
                'default'       => 'Terima Pembayaran langsung ke rekening dengan virtual account. Mendukung Banyak Bank Nasional. <p style="color: red">Warning! : </p> Menggunakan Payment Method ini mewajibkan kamu untuk menggunakan No.HP / No.Telp!',
                'description'   => 'The message which you want it to appear to the customer in the checkout page.',
            ),
            'payment_description' => array(
                'title'         => 'Thanks Page (Order) Message',
                'type'          => 'textarea',
                'css'           => 'width:500px',
                'default'       => 'Segera Transfer dengan menggunakan Metode Pembayaran Virtual Account [bank_name] ke Nomor [account_number]. Bayar Sebelum : <p style="color:red">[expire_at] GMT+7 (Asia/Jakarta)</p>Jika Masa Berlaku sudah terlewat, Maka Pesananmu akan segera dibatalkan.

                [bank_logo]',
                'description'   => "
                <div>Gunakan Replacer Berikut:</div>
                <div>Logo Bank : <b>[bank_logo]</b> </div>
                <div>Nama Bank : <b>[bank_name]</b> </div>
                <div>Nomor VA  : <b>[account_number]</b> </div>
                <div>Kadaluarsa  : <b>[expire_at]</b> </div>
                ",
            ),
            'list_accounts' => array(
                'title' => 'Daftar Akun',
                'type' => 'list_accounts'
            )
        );
    }

    public function generate_list_accounts_html()
    {
        $account_option = get_option('moota_list_accounts', []);
        $moota_settings = get_option('moota_settings', []);
        $token          = array_get($moota_settings, 'moota_v2_api_key', []);

        if (!empty($account_option) && empty($token)) {
            try {
                $deleted = delete_option('moota_list_accounts');
    
                // Jika gagal dihapus, lempar exception
                if (!$deleted) {
                    throw new Exception("Opsi moota_list_banks tidak pernah ditemukan'");
                }
            } catch (Throwable $e) {
            echo "Error: Terjadi Kesalahan Teknis";
            PluginLoader::log_to_file(
                "Error: " . $e->getMessage() . PHP_EOL .
                "File: " . $e->getFile() . PHP_EOL .
                "Line: " . $e->getLine()
            );
            exit;
            }
        }

        if (empty($token)) {
            ob_start();
            ?>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label>
                        <?php esc_html_e('List Banks Tersedia:', 'woocommerce'); ?>
                        <?php echo wp_kses_post(wc_help_tip(__('These account details will be displayed within the order thank you page and confirmation email.', 'woocommerce'))); ?>
                    </label>
                </th>
                <td class="forminp" id="moota_accounts">
                    <div class="wc_input_table_wrapper">
                        <table class="widefat wc_input_table sortable" cellspacing="0">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Account name', 'woocommerce'); ?></th>
                                    <th><?php esc_html_e('Account Type', 'woocommerce'); ?></th>
                                    <th><?php esc_html_e('Label', 'woocommerce'); ?></th>
                                    <th><?php esc_html_e('Admin Fee', 'woocommerce'); ?></th>
                                </tr>
                            </thead>
                            <tbody class="accounts">
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 20px;">
                                        API Token belum diisi. Silakan isi API Token di bagian 
                                        <a href="<?php echo admin_url('admin.php?page=moota-settings'); ?>">
                                            pengaturan Moota
                                        </a> 
                                        terlebih dahulu.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </td>
            </tr>
            <?php
            return ob_get_clean();
        }

        if (empty($account_option)) {
            $fetched_accounts = (new MootaPayment(array_get($moota_settings ?? [], "moota_v2_api_key")))->getBanks();
            if (!empty($fetched_accounts)) {
                update_option('moota_list_accounts', $fetched_accounts);
                wp_redirect(add_query_arg());
                exit;
            } else {
                // Token is invalid, do not update the option
                // Proceed to render the invalid token message
                ob_start();
                ?>
                <tr valign="top">
                    <th scope="row" class="titledesc">
                        <label>
                            <?php esc_html_e('List Banks Tersedia:', 'woocommerce'); ?>
                            <?php echo wp_kses_post(wc_help_tip(__('These account details will be displayed within the order thank you page and confirmation email.', 'woocommerce'))); ?>
                        </label>
                    </th>
                    <td class="forminp" id="moota_accounts">
                        <div class="wc_input_table_wrapper">
                            <table class="widefat wc_input_table sortable" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Account name', 'woocommerce'); ?></th>
                                        <th><?php esc_html_e('Account Type', 'woocommerce'); ?></th>
                                        <th><?php esc_html_e('Label', 'woocommerce'); ?></th>
                                        <th><?php esc_html_e('Admin Fee', 'woocommerce'); ?></th>
                                    </tr>
                                </thead>
                                <tbody class="accounts">
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 20px;">
                                            Token API tidak valid. Silakan periksa kembali API Token Anda di bagian 
                                            <a href="<?php echo admin_url('admin.php?page=moota-settings'); ?>">
                                                pengaturan Moota
                                            </a>.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </td>
                </tr>
                <?php
                return ob_get_clean();
            }
        }

        if (empty($account_option) && !empty($token)) {
            ob_start();
            ?>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label>
                        <?php esc_html_e('List Banks Tersedia:', 'woocommerce'); ?>
                        <?php echo wp_kses_post(wc_help_tip(__('These account details will be displayed within the order thank you page and confirmation email.', 'woocommerce'))); ?>
                    </label>
                </th>
                <td class="forminp" id="moota_accounts">
                    <div class="wc_input_table_wrapper">
                        <table class="widefat wc_input_table sortable" cellspacing="0">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Account name', 'woocommerce'); ?></th>
                                    <th><?php esc_html_e('Account Type', 'woocommerce'); ?></th>
                                    <th><?php esc_html_e('Label', 'woocommerce'); ?></th>
                                    <th><?php esc_html_e('Admin Fee', 'woocommerce'); ?></th>
                                </tr>
                            </thead>
                            <tbody class="accounts">
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 20px;">
                                        Tidak Ada Akun yang tersedia Dalam Key Ini. 
                                        <a href="<?php echo admin_url('admin.php?page=moota-settings'); ?>">
                                            pengaturan Moota
                                        </a> 
                                        terlebih dahulu.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </td>
            </tr>
            <?php
            return ob_get_clean();
        }

        $account_option = array_map(function ($item) {
            return is_array($item) ? (object) $item : $item;
        }, $account_option);

        ob_start();
?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label>
                    <?php esc_html_e('Account details:', 'woocommerce'); ?>
                    <?php echo wp_kses_post(wc_help_tip(__('These account details will be displayed within the order thank you page and confirmation email.', 'woocommerce'))); ?>
                </label>
            </th>
            <td class="forminp" id="moota_accounts">
                <div class="wc_input_table_wrapper">
                    <table class="widefat wc_input_table sortable" cellspacing="0">
                        <thead>
                            <tr>
                                <th class="sort">&nbsp;</th>
                                <th style="width: auto;">&nbsp;</th>
                                <th><?php esc_html_e('Account name', 'woocommerce'); ?></th>
                                <th><?php esc_html_e('Account Type', 'woocommerce'); ?></th>
                                <th><?php esc_html_e('Label', 'woocommerce'); ?></th>
                                <th><?php esc_html_e('Admin Fee', 'woocommerce'); ?></th>
                            </tr>
                        </thead>
                        <tbody class="accounts">
                            <?php
                            $i = -1;
                            $hasVaAccounts = false;
                            if (!empty($account_option)) {
                                foreach ($account_option as $account) {
                                    $i++;
                                    if (preg_match('/va$/i', $account->bank_type)) {
                                        $hasVaAccounts = true;
                            ?>
                                        <tr class="account">
                                            <td class="sort"></td>
                                            <td style="padding: 6px; text-align: center;">
                                                <input type="hidden" name="moota_bank_id[<?php echo esc_attr($i); ?>]" value="<?php echo esc_attr($account->bank_id); ?>">
                                                <input type="hidden" name="moota_bank_icon[<?php echo esc_attr($i); ?>]" value="<?php echo esc_attr(wp_unslash($account->icon)); ?>">
                                                <input type="hidden" name="moota_enable_bank[<?php echo esc_attr($i); ?>]" value="no">
                                                <input type="checkbox"
                                                    name="moota_enable_bank[<?php echo esc_attr($i); ?>]"
                                                    value="yes"
                                                    style="vertical-align: middle;"
                                                    <?php
                                                    if (property_exists($account, 'enable_account') && $account->enable_account === 'yes') {
                                                        echo "checked";
                                                    }
                                                    ?> />
                                            </td>
                                            <td>
                                                <span style="padding: 8px;" class="moota-bank-name"><?php echo esc_html($account->username); ?></span>
                                                <input type="hidden" name="moota_bank_name[<?php echo esc_attr($i); ?>]" value="<?php echo esc_attr($account->username); ?>">
                                            </td>                                                           
                                            <td>
                                                <span style="padding: 8px;" class="moota-bank-type"><?php echo esc_html($account->bank_type); ?></span>
                                                <input type="hidden" name="moota_bank_type[<?php echo esc_attr($i); ?>]" value="<?php echo esc_attr($account->bank_type); ?>">
                                            </td>
                                            <td>
                                                <input type="text"
                                                    name="moota_bank_label[<?php echo esc_attr($i); ?>]"
                                                    value="<?php echo esc_attr(
                                                                property_exists($account, 'bank_label')
                                                                    ? $account->bank_label
                                                                    : (
                                                                        preg_match('/^(.*?)va$/i', $account->bank_type, $matches)
                                                                        ? strtoupper(rtrim($matches[1], ' -_')) . ' - Virtual Account'
                                                                        : strtoupper($account->bank_type) . ' - Virtual Account'
                                                                    )
                                                            ); ?>" />
                                            </td>
                                            <td>
                                            <div style="position: relative; display: inline-flex; align-items: center;">
                                                <input 
                                                    type="text" 
                                                    id="numericInput_<?php echo $i; ?>" 
                                                    class="admin-fee-input"
                                                    style="padding: 8px 40px 8px 12px; font-size: 14px;"
                                                    placeholder="<?php echo ($account->fee_mode ?? 'percent') === 'percent' ? '0,00' : '0'; ?>"
                                                    name="moota_admin_fee[<?php echo $i; ?>]"
                                                    value="<?php echo esc_attr(
                                                        ($account->fee_mode ?? 'percent') === 'percent'
                                                            ? number_format($account->admin_fee ?? 0, 2, ',', '.')
                                                            : number_format($account->admin_fee ?? 0, 0, ',', '.')
                                                    ); ?>"
                                                />
                                                <input type="hidden"
                                                    name="moota_fee_mode[<?php echo $i; ?>]"
                                                    class="fee-mode-input"
                                                    value="<?php echo esc_attr($account->fee_mode ?? 'percent'); ?>"
                                                />
                                                <div class="toggleMode"
                                                    style="position: absolute; right: 8px; cursor: pointer; padding: 4px; display: flex;"
                                                    title="Toggle Percentage/Fixed"
                                                    data-index="<?php echo $i; ?>"
                                                    data-mode="<?php echo esc_attr($account->fee_mode ?? 'percent'); ?>">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path class="percentIcon"
                                                    style="display: <?php echo ($account->fee_mode ?? 'percent') === 'percent' ? 'block' : 'none'; ?>;"
                                                    d="M19 5L5 19M9 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4zm10 10a2 2 0 1 0 0-4 2 2 0 0 0 0 4z" />
                                                <path class="fixedIcon"
                                                    style="display: <?php echo ($account->fee_mode ?? 'percent') === 'fixed' ? 'block' : 'none'; ?>;"
                                                    d="M12 1v22M17 5H9.5a3.5 3.5 0 1 0 0 7h5a3.5 3.5 0 1 1 0 7H6" />
                                            </svg>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php
                                    }
                                }
                            }
                            if (!$hasVaAccounts) {
                                echo '<tr><td colspan="7" style="text-align: center; padding: 20px;">Anda belum memiliki Akun VA apapun.</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                    <div style="display: flex; align-items: baseline; border-left: 3px solid #007cba; padding-left: 12px; margin: 10px 0;">
                        <p style="font-weight: 700; margin: 0 12px 0 0; min-width: 70px; color: #000;">Label :</p>
                        <span style="color: #50575e; font-size: 14px; line-height: 1.5;">
                            Digunakan untuk Penamaan Metode Pembayaran di Halaman Checkout/Order.
                        </span>
                    </div>
                    <div style="display: flex; align-items: baseline; border-left: 3px solid #007cba; padding-left: 12px; margin: 10px 0;">
                        <p style="font-weight: 700; margin: 0 12px 0 0; min-width: 70px; color: #000;">Contoh :</p>
                        <span style="color: #50575e; font-size: 14px; line-height: 1.5;">
                            BCA Virtual Account - PT. XXXX, BNI - PT. XXXX, CV. XXXX. <br> Default : CHANNEL_BANK - Virtual Account
                        </span>
                    </div>
                    <div style="display: flex; align-items: baseline; border-left: 3px solid #007cba; padding-left: 12px; margin: 10px 0;">
                        <p style="font-weight: 700; margin: 0 12px 0 0; min-width: 70px; color: #000;">Admin Fee :</p>
                        <span style="color: #50575e; font-size: 14px; line-height: 1.5;">
                            Tidak diisi = 0 (Nonaktif). Kamu bisa klik pada Icon Dollar/Percent untuk memasuki mode By Percentage/Fixed Fee (Untuk Percentage dibatasi hingga 100% Harga Produk, Input melebihi 100 tetap dianggap 100%)
                        </span>
                    </div>
                </div>
            <script type="text/javascript">
            // Toggle mode handler
            jQuery(document).on('click', '.toggleMode', function() {
                const container = jQuery(this).closest('td');
                const input = container.find('.admin-fee-input');
                const modeInput = container.find('.fee-mode-input');
                const isPercent = modeInput.val() === 'percent';
                const newMode = isPercent ? 'fixed' : 'percent';

            // Ambil nilai saat ini
            let currentValue = parseFloat(
                input.val().toString()
                    .replace(/[^\d,]/g, '')
                    .replace(',', '.')
            ) || 0;
            
            // Konversi nilai saat mode berubah
            if (isPercent && newMode === 'fixed') {
                currentValue = Math.round(currentValue);
            } else if (!isPercent && newMode === 'percent') {
                // currentValue = 0;
            }

            // Update mode dan placeholder
            modeInput.val(newMode);
            input.attr('placeholder', newMode === 'percent' ? '0,00' : '0');

            // Format ulang nilai
            if (newMode === 'percent') {
                input.val(currentValue.toFixed(2).replace('.', ','));
            } else {
                input.val(currentValue.toLocaleString('id-ID'));
            }

            // Toggle icon
            container.find('.percentIcon').toggle(!isPercent);
            container.find('.fixedIcon').toggle(isPercent);
        });

        // Input handler
        jQuery(document).on('input', '.admin-fee-input', function() {
            const input = jQuery(this);
            const mode = input.siblings('.fee-mode-input').val();
            let value = input.val().toString().replace(/[^\d,]/g, '');

            // Izinkan hanya satu koma
            const commaIndex = value.indexOf(',');
            if (commaIndex !== -1) {
                value = value.substring(0, commaIndex + 3); // Batasi 2 desimal
            }

            // Ganti koma dengan titik untuk parsing
            const parsedValue = parseFloat(value.replace(',', '.')) || 0;

            // Batasi nilai
            if (mode === 'percent') {
                // Format ke 2 desimal hanya jika ada koma
                if (value.includes(',')) {
                    parsedValue = Math.min(100, Math.max(0, parsedValue));
                    input.val(parsedValue.toFixed(2).replace('.', ','));
                } else {
                    input.val(parsedValue);
                }
            }
        });

            // Add new row handler
            jQuery('#moota_accounts').on('click', 'a.add', function() {
                const size = jQuery('#moota_accounts').find('tbody .account').length;

                        const newRow = jQuery('<tr class="account">\
                        <td class="sort"></td>\
                        <td><input type="text" name="moota_account_name[' + size + ']" /></td>\
                        <td><input type="text" name="moota_account_number[' + size + ']" /></td>\
                        <td><input type="text" name="moota_bank_name[' + size + ']" /></td>\
                        <td><input type="text" name="moota_bank_label[' + size + ']" /></td>\
                        <td>\
                            <div style="position: relative; display: inline-flex; align-items: center;">\
                                <input type="number" class="admin-fee-input" name="moota_admin_fee[' + size + ']" step="0.01" placeholder="0.00%">\
                                <input type="hidden" class="fee-mode-input" name="moota_fee_mode[' + size + ']" value="percent">\
                                <div class="toggleMode" data-index="' + size + '" style="position: absolute; right: 8px; cursor: pointer; padding: 4px; display: flex;">\
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">\
                                        <path class="percentIcon" d="M19 5L5 19M9 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4zm10 10a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"/>\
                                        <path class="fixedIcon" d="M12 1v22M17 5H9.5a3.5 3.5 0 1 0 0 7h5a3.5 3.5 0 1 1 0 7H6" style="display:none;"/>\
                                    </svg>\
                                </div>\
                            </div>\
                        </td>\
                    </tr>');

                        jQuery('#moota_accounts table tbody').append(newRow);
                    });
                </script>
            </td>
        </tr>
    <?php
        return ob_get_clean();
    }

    public function save_list_accounts()
    {
        $accounts = array();

        if (isset(
            $_POST['moota_bank_icon'],
            $_POST['moota_bank_id'],
            $_POST['moota_bank_name'],
            $_POST['moota_bank_type'],
            $_POST['moota_bank_label'],
            $_POST['moota_enable_bank'],
            $_POST['moota_admin_fee'],
            $_POST['moota_fee_mode']
        )) {
            $icon               = wc_clean(wp_unslash($_POST['moota_bank_icon']));
            $enable_account     = wc_clean(wp_unslash($_POST['moota_enable_bank']));
            $account_name       = wc_clean(wp_unslash($_POST['moota_bank_name']));
            $account_channel_id = wc_clean(wp_unslash($_POST['moota_bank_id']));
            $bank_names         = wc_clean(wp_unslash($_POST['moota_bank_type']));
            $account_label      = wc_clean(wp_unslash($_POST['moota_bank_label']));
            $admin_fees         = wc_clean(wp_unslash($_POST['moota_admin_fee']));
            $fee_modes          = wc_clean(wp_unslash($_POST['moota_fee_mode']));

            foreach ($account_name as $i => $name) {
                $accounts[] = array(
                    'icon'           => $icon[$i],
                    'enable_account' => $enable_account[$i] ?? 'no',
                    'username'       => $name,
                    'bank_id'        => $account_channel_id[$i],
                    'bank_type'      => $bank_names[$i],
                    'bank_label'     => $account_label[$i],
                    'admin_fee'      => $this->sanitize_fee(
                        $admin_fees[$i] ?? 0,
                        $fee_modes[$i] ?? 'percent'
                    ),
                    'fee_mode'       => in_array($fee_modes[$i], ['percent', 'fixed'])
                        ? $fee_modes[$i]
                        : 'percent'
                );
            }
        }

        update_option('moota_list_accounts', $accounts);
    }

    private function sanitize_fee($value, $mode) {
        // Hapus semua karakter non-numerik kecuali koma
        $cleaned_value = preg_replace('/[^0-9,]/', '', $value);
        
        // Ganti koma dengan titik untuk parsing float
        $cleaned_value = str_replace(',', '.', $cleaned_value);
        
        $numeric_value = (float) $cleaned_value;
    
        if ($mode === 'percent') {
            return number_format(min(100, max(0, $numeric_value)), 2, '.', '');
        } else {
            return number_format(max(0, $numeric_value), 0, '.', '');
        }
    }

    // Custom Validate
    public function validate_bank_lists_field($key, $value)
    {
        return $value;
    }

    /**
     * Handle WooCommerce Checkout
     */
    private function bank_selection($bank_id)
    {

        if (empty($this->all_banks)) {
            $moota_settings     = get_option("moota_list_accounts", []);
        }

        if (! empty($moota_settings)) {
            foreach ($moota_settings as $bank) {
                if ($bank_id == $bank['bank_id']) {
                    return $bank;
                }
            }
        }
        return [];
    }

    public function payment_fields() {
        try {
            $moota_settings = get_option("moota_list_accounts", []);
            $hasActiveVa = false;
            ?>
            <ul>
                <h3>Moota Virtual Account</h3> <br>
                <?php 
                if (!empty($moota_settings) && is_array($moota_settings)) {
                    foreach ($moota_settings as $item) {
                        try {
                            // Validasi struktur data item
                            if (!isset($item['bank_id'], $item['bank_type'])) {
                                throw new Exception('Struktur data bank tidak valid');
                            }
    
                            $bank_selection = $this->bank_selection($item['bank_id']);
                            
                            // Validasi struktur bank_selection
                            $enable_account = $bank_selection['enable_account'] ?? 'no';
                            $bank_type = $item['bank_type'] ?? '';
                            $icon = $bank_selection['icon'] ?? '';
                            $bank_label = $bank_selection['bank_label'] ?? 'Virtual Account';
    
                            if (
                                $enable_account === "yes" 
                                && preg_match('/va$/i', $bank_type)
                            ) {
                                $hasActiveVa = true;
                                ?>
                                <li style="display: flex; align-items: center;">
                                    <label for="bank-transfer-<?= esc_attr($bank_type) ?>-bank-id-<?= esc_attr($item['bank_id']) ?>" 
                                        class="flex gap-3 items-center">
                                        
                                        <input 
                                            id="bank-transfer-<?= esc_attr($bank_type) ?>-bank-id-<?= esc_attr($item['bank_id']) ?>" 
                                            name="channels" 
                                            type="radio"
                                            value="<?= esc_attr($item['bank_id']) ?>"
                                        >
                                        
                                        <?php if (!empty($icon)) : ?>
                                            <img src="<?= esc_url($icon) ?>" 
                                                alt="<?= esc_attr($bank_label) ?>"
                                                style="max-width: 40px; height: auto;">
                                        <?php endif; ?>
                                        
                                        <span class="moota-bank-account">
                                            <?= esc_html($bank_label) ?>
                                        </span>
                                    </label>
                                </li>
                                <?php
                            }
                        } catch (Throwable $e) {
                            // Log error untuk item ini dan lanjutkan ke item berikutnya
                            PluginLoader::log_to_file(
                                "VA Payment Field Error - Bank ID {$item['bank_id']}: " . 
                                $e->getMessage() . PHP_EOL .
                                "File: " . $e->getFile() . PHP_EOL .
                                "Line: " . $e->getLine()
                            );
                            continue;
                        }
                    }
                }
                
                if (!$hasActiveVa) : ?>
                    <li style="text-align: center; padding: 20px;">
                        <span class="moota-bank-account">Tidak ada akun VA yang tersedia.</span>
                    </li>
                <?php endif; ?>
            </ul>
            <?php
            
            $description = $this->get_description();
            if ($description) {
                echo wp_kses_post($description);
            }
    
        } catch (Throwable $e) {
            // Handle error utama
            PluginLoader::log_to_file(
                "Payment Fields Fatal Error: " . $e->getMessage() . PHP_EOL .
                "File: " . $e->getFile() . PHP_EOL .
                "Line: " . $e->getLine()
            );
            
            // Tampilkan pesan error umum
            echo '<div class="moota-error-notice">Sistem pembayaran sedang mengalami gangguan. Silakan coba lagi nanti.</div>';
        }
    }

    public function validate_fields(): bool
    {
        if (empty($_POST['channels'])) {
            wc_add_notice('<strong>Channel Pembayaran</strong> Pilih Channel Pembayaran', 'error');

            return false;
        }

        return true;
    }

    public function process_payment($order_id)
    {

        $moota_settings = get_option("moota_list_accounts", []);
        $channel_id = sanitize_text_field($_POST['channels']);

        $matched_channels = array_filter($moota_settings, function ($channel) use ($channel_id) {
            return isset($channel['bank_id']) && $channel['bank_id'] === $channel_id;
        });

        $matched_channel = reset($matched_channels);

        $with_admin_fee = $matched_channel['fee_mode'] ?? 'fixed';
        $admin_fee_amount = $matched_channel['admin_fee'] ?? 0;

        return MootaTransaction::request($order_id, $channel_id, '', $with_admin_fee, $admin_fee_amount, 0, 0, 'virtual_account');
    }

    public function order_details($order)
    {

        if ($order->get_payment_method() == $this->id) {
            $bank_id = null;

            $instruction = get_option("woocommerce_wc-super-moota-virtual-transfer_settings", []);
            $moota_settings = get_option("moota_list_accounts", []);

            foreach ($order->get_meta_data() as $object) {
                $object_array = array_values((array)$object);
                foreach ($object_array as $object_item) {
                    if ('moota_bank_id' == $object_item['key']) {
                        $bank_id = $object_item['value'];
                        break;
                    }
                }
            }
            $bank = array_filter($moota_settings, function ($v, $k) use ($bank_id) {

                return $v['bank_id'] === $bank_id;
            }, ARRAY_FILTER_USE_BOTH);

            $bank = array_pop($bank);

            $expiredAt = $order->get_meta('moota_expire_at');
            $dateTime  = new DateTime($expiredAt);
            $dateTime->setTimezone(new DateTimeZone('Asia/Jakarta'));
            $formattedDate = $dateTime->format('d F Y - H:i');
        ?>
            
            <?php if($order->get_status() != "completed") : ?>
            <div class="space-y-3 py-3">
                <h3>
                    <p class="text-base font-bold">Instruksi Pembayaran</p>
                </h3>
                <div class="p-3 border border-gray-200">
                    <?php if (array_get($instruction, 'payment_description')) : ?>
                        <?php echo nl2br($this->replacer(array_get($instruction, 'payment_description'), [
                            "[bank_name]" => $bank['bank_label'],
                            "[bank_holder]" => $bank['username'],
                            "[bank_logo]" => "<img src='" . $bank['icon'] . "'>",
                            "[account_number]" => $order->get_meta('moota_va_number'),
                            "[expire_at]" => $formattedDate
                        ])); ?>
                        <div class="flex flex-row justify-between items-center">
                            <p>Kamu Bisa Cek Pembayaran-mu dengan klik tombol Sebelah kanan.</p>
                            <button onclick="location.reload();" class='text-white font-semibold px-4 py-1 bg-sky-300 rounded-lg'>Check Status Pembayaran</button>
                        </div>
                    <?php else : ?>
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
                        </div>
                        <div class="py-2">
                            <button id="moota-get-mutation-button" class="text-white font-semibold px-4 py-2 bg-sky-300 rounded-lg">
                                Check Status Pembayaran
                            </button>
                        </div>
                    <?php endif; ?>
                    <script>
                        // Script tetap ada di sini
                        var gm_button = document.getElementById("moota-get-mutation-button");
                            
                        async function postData(url = "", data = {}) {
                            toastr.info('Data sedang dicheck!');
                        
                            const response = await fetch(url, {
                                method: "POST",
                                headers: {
                                    "Content-Type": "application/json",
                                },
                                body: JSON.stringify(data),
                            });
                        
                            location.reload();
                        }
                    
                        gm_button.addEventListener("click", () => postData("/wp-json/internal/get-mutation-now", {
                            bank_id: "<?php echo esc_attr($bank['bank_id']); ?>"
                        }));
                    </script>
                </div>
            </div>
            <?php endif; ?>
        <?php
        }
    }

    private function replacer(string $template, array $data)
    {
        $parsed = $template;

        foreach ($data as $key => $value) {
            $parsed = str_replace($key, $value, $parsed);
        }

        return $parsed;
    }
}
