<?php
/**
 * Plugin Name: STS Abandoned Cart
 * Plugin URI: https://shaytechsolutions.com
 * Description: Abandoned cart recovery with email and WhatsApp reminders for Israeli WooCommerce stores. Complies with חוק התקשורת תיקון 40.
 * Version: 1.0.0
 * Author: Shay Tech Solutions
 * Author URI: https://shaytechsolutions.com
 * Text Domain: sts-abandoned-cart
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

define( 'STS_AC_VERSION', '1.0.0' );
define( 'STS_AC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'STS_AC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once STS_AC_PLUGIN_DIR . 'includes/class-sts-abandoned-cart-db.php';
require_once STS_AC_PLUGIN_DIR . 'includes/class-sts-abandoned-cart-compliance.php';
require_once STS_AC_PLUGIN_DIR . 'includes/class-sts-abandoned-cart-unsubscribe.php';
require_once STS_AC_PLUGIN_DIR . 'includes/class-sts-abandoned-cart-detector.php';
require_once STS_AC_PLUGIN_DIR . 'includes/class-sts-abandoned-cart-email.php';
require_once STS_AC_PLUGIN_DIR . 'includes/class-sts-abandoned-cart-whatsapp.php';
require_once STS_AC_PLUGIN_DIR . 'includes/class-sts-abandoned-cart-sender.php';
require_once STS_AC_PLUGIN_DIR . 'includes/class-sts-abandoned-cart-recovery.php';
require_once STS_AC_PLUGIN_DIR . 'includes/class-sts-abandoned-cart-admin.php';
require_once STS_AC_PLUGIN_DIR . 'includes/class-sts-abandoned-cart.php';

register_activation_hook( __FILE__, array( 'STS_Abandoned_Cart_DB', 'install' ) );
register_activation_hook( __FILE__, array( 'STS_Abandoned_Cart', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'STS_Abandoned_Cart', 'deactivate' ) );

// Instantiate at file-load time so the constructors can register their plugins_loaded hooks
// before that action fires. (Same pattern as sts-newsletter.)
STS_Abandoned_Cart::get_instance();
STS_Abandoned_Cart_Admin::get_instance();
