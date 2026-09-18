<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STS_Abandoned_Cart_Unsubscribe {

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
        add_filter( 'query_vars', array( $this, 'register_query_var' ) );
        add_action( 'init', array( $this, 'maybe_handle_unsubscribe' ), 10 );
    }

    public function register_query_var( $vars ) {
        $vars[] = 'sts_ac_unsub';
        return $vars;
    }

    public function maybe_handle_unsubscribe() {
        $token = get_query_var( 'sts_ac_unsub' );
        if ( ! $token ) {
            // Also check raw $_GET in case query vars aren't parsed yet (e.g. on plain permalinks).
            $token = isset( $_GET['sts_ac_unsub'] ) ? sanitize_text_field( wp_unslash( $_GET['sts_ac_unsub'] ) ) : '';
        }

        if ( ! $token ) {
            return;
        }

        $cart_row = STS_Abandoned_Cart_DB::get_by_unsubscribe_token( $token );

        if ( ! $cart_row ) {
            // Token not found — show generic confirmation anyway to avoid leaking info.
            $this->show_confirmation_page( '', false );
        }

        // Add to suppression list.
        STS_Abandoned_Cart_DB::add_suppression( $cart_row->email, $cart_row->phone, 'email_link' );

        // Mark the cart row as unsubscribed.
        STS_Abandoned_Cart_DB::update( $cart_row->id, array(
            'status' => 'unsubscribed',
        ) );

        // If user is registered, also flip the newsletter flag so other plugins respect it.
        if ( $cart_row->user_id ) {
            update_user_meta( $cart_row->user_id, 'newsletter_subscribed', 'no' );
        }

        $this->show_confirmation_page( $cart_row->email );
    }

    private function show_confirmation_page( $email = '', $found = true ) {
        ob_start();
        include STS_AC_PLUGIN_DIR . 'templates/unsubscribe-confirmation.php';
        $html = ob_get_clean();

        wp_die(
            $html,
            __( 'הסרה מרשימת התזכורות', 'sts-abandoned-cart' ),
            array( 'response' => 200, 'back_link' => false )
        );
    }
}

STS_Abandoned_Cart_Unsubscribe::get_instance();
