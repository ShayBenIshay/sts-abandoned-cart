<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Placeholder WhatsApp sender — POSTs {to, message} JSON to a configurable webhook URL.
 * Wire sts_ac_wa_endpoint in settings to your wwebjs HTTP service.
 */
class STS_Abandoned_Cart_WhatsApp {

    /**
     * @param string $phone   E.164 format, e.g. +972501234567
     * @param string $message Plain-text body (already prefixed by compliance class).
     * @return true|WP_Error
     */
    public function send( $phone, $message ) {
        $endpoint    = get_option( 'sts_ac_wa_endpoint', '' );
        $auth_header = get_option( 'sts_ac_wa_auth_header', 'Authorization' );
        $auth_value  = get_option( 'sts_ac_wa_auth_value', '' );

        if ( ! $endpoint ) {
            return new WP_Error( 'not_configured', 'WhatsApp endpoint not configured' );
        }

        if ( ! $phone ) {
            return new WP_Error( 'no_phone', 'No phone number' );
        }

        $headers = array( 'Content-Type' => 'application/json' );
        if ( $auth_header && $auth_value ) {
            $headers[ $auth_header ] = $auth_value;
        }

        $response = wp_remote_post( $endpoint, array(
            'headers' => $headers,
            'body'    => wp_json_encode( array(
                'to'      => $phone,
                'message' => $message,
            ) ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 300 ) {
            $body = wp_remote_retrieve_body( $response );
            return new WP_Error( 'http_error', "WhatsApp API returned HTTP $code: $body" );
        }

        return true;
    }
}
