jQuery(document).ready(function($) {
    // Menggunakan myPluginData.imageUrl untuk mendapatkan URL gambar
    var newImageUrl = myPluginData.imageUrl;

    // Mengganti src untuk gambar dengan kelas woocommerce-list__item-image
    $('.woocommerce-list__item-image').attr('src', newImageUrl);
});