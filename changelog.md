# Log Perubahan

## [1.0.0] - 2024/01/06
- Rilis awal untuk Moota WordPress
- Menambahkan dukungan pembayaran transfer bank Easy Digital Downloads
- Menambahkan dukungan pembayaran transfer bank WooCommerce

## [1.0.1] - 2024/01/10
- Menambah Webhook Url di halaman settingan

## [1.0.2] - 2024/01/10
- Fix Webhook Url di halaman settingan

## [1.0.3] - 2024/01/30
- Fix type error when getting payment methods

## [1.0.4] - 2024/01/30
- Fix Moota settings not loaded and return boolean type

## [1.0.5] - 2024/01/31
- Add WP DB prefix setting

## [1.0.6] - 2024/01/31
- Remove default value for db prefix

## [1.0.7] - 2024/02/15
- Add Moota webhook IP debugging

## [1.0.8] - 2024/02/15
- Fix WooCommerce discount bug

## [1.0.9] - 2024/05/07
- Fix EDD installation issue error

## [1.0.10] - 2024/05/07
- Fix EDD installation access token type error

## [1.0.11] - 2024/05/07
- Fix EDD bug when registering payment method

## [1.0.12] - 2024/05/10
- Fix Woocommerce bug unique code doesn't included to order item

## [1.0.13] - 2024/05/10
- Fix Woocommerce bug when decreasing unique code

## [2.0.0] - 2025/03/03
Fixes   :
- Mengoptimalisasi Performa Plugin dengan Sistem Cache
- Memindahkan List Bank Tersedia & Settingan Kode Unik Ke Halaman Payment Settings WooCommerce (Bank Transfer & Virtual Transfer)
- Security Update
- Support HPOS & Legacy Penyimpanan Database Order WooCommerce

Feature :
- Menambahkan Payment Baru Moota - Virtual Account Transfer
- Menambahkan Payment Baru Moota - QRIS Payment
- Menambahkan Button Synchronize Banks untuk sinkronisasi Daftar Akun Moota
- Ketentuan Baru untuk Checkout : Bila menggunakan Payment Virtual account Moota, Customer Wajib Mengisi No.HP
- Ketentuan Baru untuk Checkout : Bila menggunakan Payment Virtual account Moota, Jumlah Pembelian Customer Harus Melebihi Rp10.000
- Table List Banks & Accounts   : Memudahkan Cara Baca Daftar List Akun User Moota
- Mekanik Baru Virtual Transfer : Biaya Admin By Fixed Amount / Percent by Product Price
- Mekanik Baru Virtual Transfer : Expire At          - Untuk Customize Berapa lama, Bisa di set melalui Settingan WooCommerce Stock Hold
- Fitur Baru List Akun          : Bank/Account Label - Digunakan Untuk Customize Nama Metode Pembayaran Moota

Deleted :
- Bank Tersedia Di Halaman Moota Settings (Dipindahkan Ke Halaman Payment Bank/Virtual Transfer Moota)
- Pengaturan Kode Unik Di Halaman Moota Settings (Dipindahkan Ke Halaman Payment Bank Transfer Moota)
- Pengaturan Merchant Di Halaman Moota Settings
- Checkout Message Field Di Halaman Moota Settings (Dipindahkan Ke Halaman Payment Bank/Virtual Transfer Moota)
- Thanks Order Page Field Di Halaman Moota Settings (Dipindahkan Ke Halaman Payment Bank/Virtual Transfer Moota)

- Edd Support Soon! :D

## [2.0.1] - 2025/03/24
Fixes : 
- Fix bug dimana ibbiz BRI bukan bagian dari Bank BRI
- Fix bug Dimana jika ada transaksi seharga Rp10000 dan Sedang dalam mode prod. Tidak mengreturn "OK (Test Webhook)"

## [2.0.2] - 2025/04/15
Feature :
- Menambahkan Fitur Checkout Moota : Ingin Tampilan Checkout Yang lebih Flexible dan Simple? Kini Fitur Redirect Checkout Moota Telah Hadir! Sekarang Sobat udah bisa pakai Checkout WooCommerce kalian menggunakan Fitur Checkout dari Moota!
- Menambahkan 3 Opsi Yang hanya bisa dijalankan Ketika menggunakan fitur Checkout dari Moota (Redirect per Status) : Kembali Ke Detail Produk, Kembali ke Halaman Sebelumnya dan Kembali Ke Halaman Thanks Page.
- Metode Bank Transfer sekarang menggunakan API Checkout Dari Moota.

Fix :
- Fix Dimana Harga Order berbeda dengan Jumlah Total Items Ketika Membuat Sebuah Transaksi dengan Metode Bank Transfer.

Requirement :
- Versi PHP Minimal dari 7.4 -> 8.x

## [2.1.0] - 2025/04/22
Feature :
- Mengganti Sistem Caching dari Doctrine (Library luar) ke Transients (Bawaan Wordpress)

Fixes :
- Fix bug Dimana Plugin Membutuhkan Versi 8.x Keatas (Versi 7.x bisa gunakan kembali)
- Fix Bug Dimana jika Settings Redirect Optionnya Belum Pernah Diedit, Maka Akan Mengreturn Error saat memilih Payment Method Moota
- Fix Bug Dimana EDD untuk Rupiah IDR Currency Tidak Muncul

## [2.2.0] - 2025/05/01
Feature :
- Kini Plugin Moota Wordpress Telah diperbarui untuk Support Kebutuhan Transaksi Kamu Di EDD!
- Menambahkan Setting Moota Khusus EDD yang terletak di : Downloads -> Settings -> Payments -> Moota
- Menambahkan Payment Method Virtual Account dan QRIS dari Moota
- Menambahkan Setting Status Payment Setelah Dibayar yang terletak di : Moota Settings -> EDD
- Memindahkan Semua Halaman Receipt Ke Moota (Back to Merchant untuk Kembali Ke Halaman Receipt EDD)

Fixes :
- Fix Bug WooCommerce ketika Menggunakan Payment BCA akan Memunculkan 2x Payment Status, No.Rek dan Instruksi Pembayaran
- Fix Bug WooCommerce Menampilkan Username Ibanking pada bank BCA (From Username -> Atas Nama)
- Meminimalisir Penggunaan Fetch API Berlebih
- Cleaning Code Untuk Method yang tidak lagi digunakan
- Fix Error Validation Failed, Success & Pending for Default Settings

Security :
- Menghindari penggunaan Raw SQL Query pada Proses Payment, Instruksi Pembayaran, Dan Webhook.