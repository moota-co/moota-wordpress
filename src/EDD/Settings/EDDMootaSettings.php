<?php

namespace Moota\MootaSuperPlugin\EDD\Settings;

class EDDMootaSettings {
    /**
     * Instance
     */
    private static $instance;


    /**
     * Retrieve current instance
     *
     * @access private
     * @since  0.1
     * @return EDDMootaSettings instance
     */
    static function getInstance() {

        if ( ! isset( self::$instance ) && ! ( self::$instance instanceof EDDMootaSettings ) ) {
            self::$instance = new EDDMootaSettings;
        }

        return self::$instance;

    }

    public function __construct(){
        add_filter('edd_settings_gateways', [$this, 'register_gateway_settings']);
        add_filter( 'edd_settings_sections_gateways', [$this, 'register_moota_gateway_section'], 1, 1 );
    }

    public function register_moota_gateway_section( $gateway_sections ) {
        $gateway_sections['moota_commerce'] = __( 'Moota', 'Moota' );
    
        return $gateway_sections;
    }


     public function register_gateway_settings($gateway_settings) {
    
        $moota_settings = array(
            'moota_settings' => array(
                'id'   => 'moota_bank_settings',
                'name' => '<h1>' . __( 'Moota Settings', 'easy-digital-downloads' ) . '</h1>',
                'type' => 'header',
            ),
            'moota_bank_transfer_section' => array(
                'id'   => 'moota_bank_transfer_section',
                'name' => '<h3>' . __( 'Bank Transfer Settings', 'easy-digital-downloads' ) . '</h3>',
                'type' => 'descriptive_text',
            ),
            'enable_moota_unique_code' => array(
                'id'   => 'enable_moota_unique_code',
                'name' =>  __('Moota Unique Code', 'easy-digital-downloads'),
                'type' => 'checkbox',
                'desc' => 'Aktifkan Mekanik Kode unik Untuk setiap Transaksi yang Dibuat?'
            ),
            'start_moota_unique_code' => array(
                'id' => 'start_moota_unique_code',
                'name' => 'Start Unique Code',
                'type' => 'number',
                'desc' => 'Angka Awal Generate Kode Unik'
            ),
            'end_moota_unique_code' => array(
                'id' => 'end_moota_unique_code',
                'name' => 'End Unique Code',
                'type' => 'number',
                'desc' => 'Angka Akhir Generate Kode Unik'
            ),
            'moota_unique_code_type' => array(
                'id' => 'moota_unique_code_type',
                'name' => 'Tipe Kode Unik',
                'type' => 'select',
                'options' => array(
                    'increase_total' => __('Menambahkan total Transaksi', 'easy-digital-downloads'),
                    'decrease_total' => __('Mengurangi total Transaksi', 'easy-digital-downloads')
                    ),
                    'desc' => '<strong>Tips:</strong> Pilih cara kerja kode unik pada total pembayaran Anda. 
                    <ul style="margin-top: 4px;">
                      <li><strong>Menambahkan :</strong> Total pembayaran akan <em>ditambahkan</em> dengan angka unik (contoh: Rp100.000 + 123 → Rp100.123).</li>
                      <li><strong>Mengurangi :</strong> Total pembayaran akan <em>dikurangi</em> dengan angka unik (contoh: Rp100.000 - 123 → Rp99.877).</li>
                    </ul>
                    Gunakan fitur ini untuk membantu mencocokkan pembayaran secara otomatis.'
                ),
            'moota_bank_transfer_payment_detail' => array(
                'id' => 'moota_bank_transfer_payment_detail',
                'name' => 'Instruksi Pembayaran',
                'type' => 'textarea',
                'desc' => 'Kolom ini diisi untuk Mengatur Pesan Instruksi Pembayaran di halaman Order Received. Bisa juga Mengrender HTML.' . 
                "
				<div>Gunakan Replacer Berikut:</div>
				<div>Nomor Rekening : [bank_account]</div>
                <div>Logo Bank      : [bank_logo]</div>
                <div>Note Unik (Hanya Bisa Digunakan Ketika Menggunakan Kode Unik) : [unique_note]</div>
                <div>Username Bank  : [bank_name]</div>
                <div>Atas Nama Bank : [bank_holder]</div>
			"
            ),
            'moota_virtual_account_section' => array(
                'id' => 'moota_virtual_account_section',
                'name' => '<h3>' . __( 'Virtual Account Settings', 'easy-digital-downloads' ) . '</h3>',
                'type' => 'descriptive_text'
            ),
            'enable_moota_virtual_admin_fee' => array(
                'id' => 'enable_moota_virtual_admin_fee',
                'name' => 'Moota Admin Fee',
                'type' => 'checkbox',
                'desc' => 'Aktifkan Biaya Admin untuk Setiap Transaksi?'
            ),
            'moota_virtual_admin_fee_amount' => array(
                'id' => 'moota_virtual_admin_fee_amount',
                'name' => 'Jumlah Biaya Admin',
                'type' => 'number',
                'desc' => '<strong>Tips :</strong> Masukkan Jumlah Biaya admin yang Diinginkan'
            ),
            'moota_virtual_admin_fee_type' => array(
                'id' => 'moota_virtual_admin_fee_type',
                'name' => 'Tipe Biaya Admin',
                'type' => 'select',
                'options' => array(
                    'fixed_amount' => __('Biaya Flat', 'easy-digital-downloads'),
                    'percent_amount' => __('Persentasi Dari Jumlah Belanja', 'easy-digital-downloads')
                ),
                'desc' => '<strong>Tips :</strong> Pilih metode perhitungan biaya admin yang akan ditambahkan ke total belanja pelanggan.
                <br><br>
                <ul style="margin-top: 4px;">
                  <li><strong>Biaya Flat:</strong> Biaya tetap yang akan ditambahkan, tidak tergantung pada jumlah belanja. (Contoh: Rp5.000 per transaksi)</li>
                  <li><strong>Persentasi Dari Jumlah Belanja:</strong> Biaya dihitung berdasarkan persentase dari total belanja pelanggan. (Contoh: 2% dari total pembelian)</li>
                </ul>
                Gunakan metode yang sesuai dengan kebutuhan bisnismu!'
            ),
            'moota_virtual_account_payment_detail' => array(
                'id' => 'moota_virtual_account_payment_detail',
                'name' => 'Instruksi Pembayaran',
                'type' => 'textarea',
                'desc' => '<strong>Tips :</strong> Masukkan instruksi pembayaran yang akan dilihat oleh pelanggan setelah checkout.
                            <br>
                            Jika dikosongkan, sistem akan menggunakan instruksi default.
                            <br>
                            <br>
                            Kamu juga bisa menggunakan beberapa Holder Berikut : 
                            <br>
                            <strong>[bank_name] :</strong> Nama Bank Virtual Account
                            <br>
                            <strong>[bank_holder] :</strong> Nama Pemilik Virtual Account
                            <br>
                            <strong>[virtual_number] : </strong> Nomor Tujuan Virtual Account
                            <br>
                            <strong>[bank_logo] : </strong> Logo Bank VA'
            ),
            'moota_qris_section' => array(
                'id' => 'moota_qris_section',
                'name' => '<h3>' . __( 'Moota Qris Settings', 'easy-digital-downloads' ) . '</h3>',
                'type' => 'descriptive_text'
            ),
            'moota_qris_label' => array(
                'id' => 'moota_qris_label',
                'name' => 'QRIS Label',
                'type' => 'text',
                'desc' => 'Gunakan Label Metode Pembayaran Yang di custom <br> <strong>Default : Moota QRIS</strong>'
            ),
            'moota_qris_payment_instruction' => array(
                'id' => 'moota_qris_payment_instruction',
                'name' => 'Instruksi Pembayaran',
                'type' => 'textarea',
                'desc' => '<strong>Tips :</strong> Masukkan instruksi pembayaran yang akan dilihat oleh pelanggan setelah checkout.
                            <br>
                            Jika dikosongkan, sistem akan menggunakan instruksi default. <br>
                            <br>
                            Kamu juga bisa menggunakan Holder Berikut : <br>
                            [bank_holder] : Nama Username QRIS Moota <br>
                            [qr_image] : Foto QR Dinamis<br>
                            [bank_logo] : Logo Pembayaran QRIS<br>'
            ),
        );
    
        // Buat tab baru 'Moota Bank'
        $moota_settings                     = apply_filters( 'edd_moota_bank_settings', $moota_settings );
        $gateway_settings['moota_commerce'] = $moota_settings;

        add_filter( 'edd_get_option_start_moota_unique_code', function( $value ) {
            return $value !== '' ? $value : 1;
        } );

        add_filter( 'edd_get_option_end_moota_unique_code', function( $value ) {
            return $value !== '' ? $value : 999;
        } );

        add_filter('edd_get_option_moota_bank_transfer_payment_detail', function($value) {
            return $value !== '' ? $value : "
            Harap untuk transfer sesuai dengan jumlah yang sudah ditentukan sampai 3 digit terakhir [unique_code].<br>
            
            Transfer Ke Bank [bank_name] <br> <br>
            [bank_logo] <br>
            [account_number] A/n [account_holder]";
        });
        
        return $gateway_settings;
    }
}

?>