<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STS_Abandoned_Cart {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'plugins_loaded', array( $this, 'init' ) );
    }

    public function init() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
            return;
        }

        add_filter( 'cron_schedules', array( $this, 'register_cron_interval' ) );
        add_action( 'sts_ac_reminder_cron', array( $this, 'run_cron' ) );

        load_plugin_textdomain( 'sts-abandoned-cart', false, dirname( plugin_basename( STS_AC_PLUGIN_DIR . 'sts-abandoned-cart.php' ) ) . '/languages' );
    }

    public static function activate() {
        if ( ! wp_next_scheduled( 'sts_ac_reminder_cron' ) ) {
            wp_schedule_event( time(), 'sts_ac_every_five_minutes', 'sts_ac_reminder_cron' );
        }
    }

    public static function deactivate() {
        $ts = wp_next_scheduled( 'sts_ac_reminder_cron' );
        if ( $ts ) {
            wp_unschedule_event( $ts, 'sts_ac_reminder_cron' );
        }
    }

    public function register_cron_interval( $schedules ) {
        $schedules['sts_ac_every_five_minutes'] = array(
            'interval' => 300,
            'display'  => __( 'כל 5 דקות (STS Abandoned Cart)', 'sts-abandoned-cart' ),
        );
        return $schedules;
    }

    public function run_cron() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        // Expire stale rows first.
        STS_Abandoned_Cart_DB::expire_old_pending();

        $rows = STS_Abandoned_Cart_DB::get_pending_for_cron( 50 );
        if ( empty( $rows ) ) {
            return;
        }

        $sender = new STS_Abandoned_Cart_Sender();
        foreach ( $rows as $row ) {
            $sender->send( $row );
        }
    }

    public function woocommerce_missing_notice() {
        echo '<div class="notice notice-error"><p>'
            . esc_html__( 'STS Abandoned Cart מצריך את WooCommerce להיות פעיל.', 'sts-abandoned-cart' )
            . '</p></div>';
    }
}
