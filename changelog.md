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