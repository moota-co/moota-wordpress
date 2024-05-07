<?php
/**
 * Plugin Name: Moota Wordpress
 * Plugin URI: https://moota.co/plugin-wordpress/
 * Description: Platform penerima pembayaran otomatis untuk produk, jasa dan apapun.
 * Author: Moota <hi@moota.co>
 * Author URI: https://moota.co/
 * Version: 1.0.8
 * Requires at least: 6.0.0
 * Requires PHP: 7.4
 * Tested up to: 6.0.2
 * Text Domain: moota-super-plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const MOOTA_FULL_PATH = __FILE__;
const MOOTA_LOGS_PATH = __DIR__."/logs";

require __DIR__ . '/vendor/autoload.php';


use Moota\MootaSuperPlugin\PluginLoader;

PluginLoader::init();
