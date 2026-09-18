<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STS_Abandoned_Cart_Detector {

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

        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_ajax_sts_ac_capture',        array( $this, 'ajax_capture' ) );
        add_action( 'wp_ajax_nopriv_sts_ac_capture', array( $this, 'ajax_capture' ) );

        // Mark cart as recovered when an order is placed.
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'maybe_mark_recovered_on_order' ), 10, 3 );
    }

    public function enqueue_scripts() {
        if ( ! is_checkout() ) {
            return;
        }

        wp_enqueue_script(
            'sts-ac-checkout-capture',
            STS_AC_PLUGIN_URL . 'assets/js/checkout-capture.js',
            array( 'jquery' ),
            STS_AC_VERSION,
            true
        );

        wp_localize_script( 'sts-ac-checkout-capture', 'stsAcData', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'sts_ac_capture' ),
        ) );
    }

    public function ajax_capture() {
        check_ajax_referer( 'sts_ac_capture', 'nonce' );

        if ( ! class_exists( 'WooCommerce' ) ) {
            wp_send_json_error();
        }

        $email         = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        $phone         = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
        $session_token = isset( $_POST['session_token'] ) ? sanitize_text_field( wp_unslash( $_POST['session_token'] ) ) : '';

        if ( ! is_email( $email ) || strlen( $session_token ) !== 32 ) {
            wp_send_json_error( array( 'code' => 'invalid_data' ) );
        }

        // Silently skip suppressed contacts — don't leak whether they're in the list.
        if ( STS_Abandoned_Cart_DB::is_suppressed( $email, $phone ) ) {
            wp_send_json_success();
        }

        $cart = WC()->cart;
        if ( ! $cart || $cart->is_empty() ) {
            wp_send_json_success();
        }

        $cart_snapshot = $this->serialize_cart( $cart );
        $delay_minutes = (int) get_option( 'sts_ac_reminder_delay', 60 );
        $scheduled_at  = date( 'Y-m-d H:i:s', time() + $delay_minutes * 60 );

        $data = array(
            'unsubscribe_token'     => $this->generate_unsubscribe_token( $email ),
            'user_id'               => get_current_user_id(),
            'email'                 => $email,
            'phone'                 => $this->normalize_phone( $phone ),
            'cart_contents'         => wp_json_encode( $cart_snapshot['items'] ),
            'cart_total'            => $cart_snapshot['total'],
            'currency'              => get_woocommerce_currency(),
            'coupons'               => wp_json_encode( $cart->get_applied_coupons() ),
            'consent_source'        => 'checkout_email_typed',
            'consent_ip'            => $this->get_client_ip(),
            'consent_user_agent'    => substr( sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 500 ),
            'reminder_scheduled_at' => $scheduled_at,
        );

        STS_Abandoned_Cart_DB::upsert_cart( $session_token, $data );

        // Store session token in WC session so recovery can correlate with order.
        if ( WC()->session ) {
            WC()->session->set( 'sts_ac_session_token', $session_token );
        }

        wp_send_json_success();
    }

    public function maybe_mark_recovered_on_order( $order_id, $posted_data, $order ) {
        $session_token = WC()->session ? WC()->session->get( 'sts_ac_session_token' ) : '';

        $cart_row = null;

        if ( $session_token ) {
            $cart_row = STS_Abandoned_Cart_DB::get_by_session_token( $session_token );
        }

        // Fallback: match by billing email within the last 7 days.
        if ( ! $cart_row && $order ) {
            $billing_email = $order->get_billing_email();
            if ( $billing_email ) {
                $cart_row = STS_Abandoned_Cart_DB::get_pending_reminded_by_email( $billing_email );
                // Also check pending rows (user completed before reminder fired).
                if ( ! $cart_row ) {
                    global $wpdb;
                    $cart_row = $wpdb->get_row( $wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}sts_abandoned_carts
                         WHERE email = %s AND status = 'pending'
                           AND created_at >= %s
                         ORDER BY created_at DESC LIMIT 1",
                        strtolower( $billing_email ),
                        date( 'Y-m-d H:i:s', strtotime( '-7 days' ) )
                    ) );
                }
            }
        }

        if ( ! $cart_row ) {
            return;
        }

        STS_Abandoned_Cart_DB::update( $cart_row->id, array(
            'status'            => 'recovered',
            'recovered_at'      => current_time( 'mysql' ),
            'recovery_order_id' => $order_id,
        ) );

        if ( WC()->session ) {
            WC()->session->set( 'sts_ac_session_token', null );
        }
    }

    private function serialize_cart( $cart ) {
        $items = array();
        $total = 0;

        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            $product = $cart_item['data'];
            if ( ! $product ) {
                continue;
            }

            $image_id  = $product->get_image_id();
            $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : '';

            $items[] = array(
                'product_id'   => $cart_item['product_id'],
                'variation_id' => $cart_item['variation_id'],
                'qty'          => $cart_item['quantity'],
                'name'         => $product->get_name(),
                'price'        => (float) $cart_item['line_subtotal'],
                'image_url'    => $image_url,
                'permalink'    => get_permalink( $cart_item['product_id'] ),
            );

            $total += (float) $cart_item['line_subtotal'];
        }

        return array( 'items' => $items, 'total' => $total );
    }

    private function generate_unsubscribe_token( $email ) {
        // Deterministic per email so re-captures reuse the same unsub token.
        return md5( $email . AUTH_SALT . 'sts_ac_unsub' );
    }

    private function normalize_phone( $phone ) {
        if ( ! $phone ) return '';
        $digits = preg_replace( '/[^\d+]/', '', $phone );
        // Israeli mobile: starts with 05 → convert to +9725
        if ( preg_match( '/^05(\d{8})$/', $digits, $m ) ) {
            return '+9725' . $m[1];
        }
        return $digits;
    }

    private function get_client_ip() {
        $keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );
        foreach ( $keys as $k ) {
            if ( ! empty( $_SERVER[ $k ] ) ) {
                $ip = trim( explode( ',', $_SERVER[ $k ] )[0] );
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) return $ip;
            }
        }
        return '';
    }
}

STS_Abandoned_Cart_Detector::get_instance();
