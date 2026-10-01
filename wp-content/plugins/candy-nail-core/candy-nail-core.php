<?php
/**
 * Plugin Name: Candy Nail Core
 * Description: Core plugin for Candy Nail Supplies.
 * Version: 1.0.0
 * Author: Candy Nail Supplies
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Declare HPOS compatibility.
 */
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
});

define('CN_CORE_PATH', plugin_dir_path(__FILE__));
define('CN_CORE_URL', plugin_dir_url(__FILE__));
define('CN_CORE_VERSION', '1.0.0');

require_once CN_CORE_PATH . 'includes/loader.php';

register_activation_hook(
    __FILE__,
    ['CN_Database_Installer', 'activate']
);