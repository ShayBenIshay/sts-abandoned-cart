<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STS_Abandoned_Cart_Recovery {

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
            return;
        }
        // Mark click + store token in WC session at init (session is ready by then).
        add_action( 'init', array( $this, 'maybe_flag_recovery' ), 20 );
        // Restore cart items later, once WC()->cart is populated from session.
        add_action( 'woocommerce_cart_loaded_from_session', array( $this, 'maybe_restore_cart_items' ), 10 );
        // Tracking pixel must exit before template loads.
        add_action( 'template_redirect', array( $this, 'maybe_track_open' ), 1 );
    }

    /**
     * Early: mark clicked_at and store token in WC session.
     * Cart items are hydrated later in maybe_restore_cart_items().
     */
    public function maybe_flag_recovery() {
        if ( empty( $_GET['sts_ac_recover'] ) ) {
            return;
        }

        $token    = sanitize_text_field( wp_unslash( $_GET['sts_ac_recover'] ) );
        $cart_row = STS_Abandoned_Cart_DB::get_by_session_token( $token );

        if ( ! $cart_row || in_array( $cart_row->status, array( 'recovered', 'expired' ), true ) ) {
            return;
        }

        if ( ! $cart_row->clicked_at ) {
            STS_Abandoned_Cart_DB::update( $cart_row->id, array(
                'clicked_at' => current_time( 'mysql' ),
            ) );
        }

        // Persist the token so maybe_restore_cart_items() and the order hook can find it.
        if ( WC()->session ) {
            WC()->session->set( 'sts_ac_session_token', $token );
        }
    }

    /**
     * Late: WC()->cart is now loaded — add items from the snapshot if cart is empty.
     * Reads token from GET param directly because this hook fires before maybe_flag_recovery()
     * (woocommerce_cart_loaded_from_session fires at init priority 0; our flag runs at priority 20).
     */
    public function maybe_restore_cart_items() {
        // Prefer GET param; fall back to WC session for subsequent page loads after cart restored.
        $token = '';
        if ( ! empty( $_GET['sts_ac_recover'] ) ) {
            $token = sanitize_text_field( wp_unslash( $_GET['sts_ac_recover'] ) );
        } elseif ( WC()->session ) {
            $token = (string) WC()->session->get( 'sts_ac_session_token' );
        }

        if ( ! $token ) {
            return;
        }

        $cart_row = STS_Abandoned_Cart_DB::get_by_session_token( $token );
        if ( ! $cart_row || in_array( $cart_row->status, array( 'recovered', 'expired' ), true ) ) {
            return;
        }

        if ( ! WC()->cart || ! WC()->cart->is_empty() ) {
            return;
        }

        $items = json_decode( $cart_row->cart_contents, true );
        if ( ! is_array( $items ) ) {
            return;
        }

        foreach ( $items as $item ) {
            $product_id   = absint( $item['product_id'] );
            $variation_id = absint( $item['variation_id'] ?? 0 );
            $qty          = max( 1, absint( $item['qty'] ?? 1 ) );

            $product = wc_get_product( $product_id );
            if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
                continue;
            }

            WC()->cart->add_to_cart( $product_id, $qty, $variation_id );
        }
    }

    /**
     * Server-side open tracking pixel.
     */
    public function maybe_track_open() {
        if ( empty( $_GET['sts_ac_open'] ) ) {
            return;
        }

        $token    = sanitize_text_field( wp_unslash( $_GET['sts_ac_open'] ) );
        $cart_row = STS_Abandoned_Cart_DB::get_by_session_token( $token );

        if ( $cart_row && ! $cart_row->opened_at ) {
            STS_Abandoned_Cart_DB::update( $cart_row->id, array(
                'opened_at' => current_time( 'mysql' ),
            ) );
        }

        header( 'Content-Type: image/gif' );
        header( 'Cache-Control: no-store, no-cache, must-revalidate' );
        header( 'Pragma: no-cache' );
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
        echo base64_decode( 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' );
        exit;
    }
}

STS_Abandoned_Cart_Recovery::get_instance();
