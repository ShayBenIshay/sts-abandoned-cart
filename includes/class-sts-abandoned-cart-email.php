<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STS_Abandoned_Cart_Email {

    private $from_email = '';
    private $from_name  = '';

    public function __construct() {
        $this->from_email = get_option( 'sts_ac_from_email', get_option( 'admin_email' ) );
        $this->from_name  = get_option( 'sts_ac_from_name', get_bloginfo( 'name' ) );
    }

    /**
     * @param object $cart_row DB row from sts_abandoned_carts.
     * @return true|WP_Error
     */
    public function send( $cart_row ) {
        if ( ! is_email( $cart_row->email ) ) {
            return new WP_Error( 'invalid_email', 'Invalid email: ' . $cart_row->email );
        }

        $store_name = get_option( 'sts_ac_sender_name', get_bloginfo( 'name' ) );
        $raw_subject = sprintf( 'שכחתם משהו? העגלה שלכם ב%s מחכה', $store_name );
        $subject     = STS_Abandoned_Cart_Compliance::prefix_subject( $raw_subject );

        $body = $this->render_template( $cart_row );

        // Scope From: headers to this send only.
        add_filter( 'wp_mail_from',      array( $this, 'filter_from_email' ) );
        add_filter( 'wp_mail_from_name', array( $this, 'filter_from_name' ) );

        $result = wp_mail(
            $cart_row->email,
            $subject,
            $body,
            array( 'Content-Type: text/html; charset=UTF-8' )
        );

        remove_filter( 'wp_mail_from',      array( $this, 'filter_from_email' ) );
        remove_filter( 'wp_mail_from_name', array( $this, 'filter_from_name' ) );

        if ( ! $result ) {
            return new WP_Error( 'send_failed', 'wp_mail() returned false' );
        }

        return true;
    }

    private function render_template( $cart_row ) {
        $items       = json_decode( $cart_row->cart_contents, true ) ?: array();
        $recovery_url = STS_Abandoned_Cart_Compliance::recovery_url( $cart_row->session_token );
        $pixel_url    = STS_Abandoned_Cart_Compliance::open_pixel_url( $cart_row->session_token );
        $footer_html  = STS_Abandoned_Cart_Compliance::email_footer_html( $cart_row );
        $logo_url     = get_option( 'sts_ac_logo_url', home_url( '/wp-content/uploads/2025/12webp/Logo-pearl.webp' ) );
        $store_name   = get_option( 'sts_ac_sender_name', get_bloginfo( 'name' ) );
        $currency     = $cart_row->currency ?: get_woocommerce_currency();
        $currency_sym = get_woocommerce_currency_symbol( $currency );

        ob_start();
        include STS_AC_PLUGIN_DIR . 'templates/email-reminder.php';
        return ob_get_clean();
    }

    public function filter_from_email( $email ) {
        return $this->from_email ?: $email;
    }

    public function filter_from_name( $name ) {
        return $this->from_name ?: $name;
    }
}
