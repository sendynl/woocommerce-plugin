<?php

/**
 * Plugin Name: Sendy
 * Plugin URI: https://app.sendy.nl/
 * Description: A WooCommerce plugin that connects your site to the Sendy platform
 * Version: 3.0
 * Author: Sendy
 * Author URI: https://sendy.nl/
 * License: MIT
 * Text Domain: sendy
 * Domain Path: /languages
 * Requires at least: 5.2
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 8.2
 * WC tested up to: 9.1.4
 *
 * @package Sendy
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('SENDY_WC_PLUGIN_FILE')) {
    define('SENDY_WC_PLUGIN_FILE', __FILE__);
}

if (! defined('SENDY_WC_PLUGIN_BASENAME')) {
    define('SENDY_WC_PLUGIN_BASENAME', plugin_basename(SENDY_WC_PLUGIN_FILE));
}

require_once __DIR__ . '/vendor/autoload.php';

function init_sendy() {
    require_once __DIR__ . '/src/helpers.php';

    return Sendy\WooCommerce\Plugin::instance();
}

add_action('plugins_loaded', 'init_sendy');