<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STS_Abandoned_Cart_Sender {

    private $email_sender;
    private $whatsapp_sender;

    public function __construct() {
        $this->email_sender    = new STS_Abandoned_Cart_Email();
        $this->whatsapp_sender = new STS_Abandoned_Cart_WhatsApp();
    }

    /**
     * Attempt to send reminder for a single abandoned cart row.
     * Returns true on success, WP_Error on failure.
     */
    public function send( $cart_row ) {
        // Compliance: re-check suppression list at send time.
        $can = STS_Abandoned_Cart_Compliance::can_send( $cart_row->email, $cart_row->phone );
        if ( is_wp_error( $can ) ) {
            STS_Abandoned_Cart_DB::update( $cart_row->id, array( 'status' => 'unsubscribed' ) );
            return $can;
        }

        $channels_setting = get_option( 'sts_ac_channels', array( 'email' ) );
        if ( ! is_array( $channels_setting ) ) {
            $channels_setting = array( 'email' );
        }

        $email_enabled     = in_array( 'email', $channels_setting, true );
        $whatsapp_enabled  = in_array( 'whatsapp', $channels_setting, true );

        $email_ok    = false;
        $whatsapp_ok = false;
        $last_error  = null;

        // --- Email ---
        if ( $email_enabled && $cart_row->email ) {
            $result = $this->email_sender->send( $cart_row );
            if ( is_wp_error( $result ) ) {
                $last_error = $result;
                error_log( 'STS Abandoned Cart email error [cart #' . $cart_row->id . ']: ' . $result->get_error_message() );
            } else {
                $email_ok = true;
            }
        }

        // --- WhatsApp ---
        if ( $whatsapp_enabled && $cart_row->phone ) {
            $store_name = get_option( 'sts_ac_sender_name', get_bloginfo( 'name' ) );
            $items      = json_decode( $cart_row->cart_contents, true ) ?: array();

            $item_lines = array();
            foreach ( $items as $item ) {
                $item_lines[] = '• ' . $item['name'] . ' (×' . $item['qty'] . ')';
            }

            $recovery_url = STS_Abandoned_Cart_Compliance::recovery_url( $cart_row->session_token );
            $wa_body      = STS_Abandoned_Cart_Compliance::whatsapp_message_prefix()
                . 'שלום! שכחתם משהו ב' . $store_name . "?\n\n"
                . "בעגלה שלכם:\n" . implode( "\n", $item_lines ) . "\n\n"
                . "להשלמת הרכישה: " . $recovery_url
                . STS_Abandoned_Cart_Compliance::whatsapp_footer_text( $cart_row );

            $result = $this->whatsapp_sender->send( $cart_row->phone, $wa_body );
            if ( is_wp_error( $result ) ) {
                if ( 'not_configured' !== $result->get_error_code() ) {
                    // Only log real errors, not "not configured".
                    $last_error = $result;
                    error_log( 'STS Abandoned Cart WhatsApp error [cart #' . $cart_row->id . ']: ' . $result->get_error_message() );
                }
            } else {
                $whatsapp_ok = true;
            }
        }

        // Determine sent channel string.
        $sent_channel = '';
        if ( $email_ok && $whatsapp_ok ) {
            $sent_channel = 'both';
        } elseif ( $email_ok ) {
            $sent_channel = 'email';
        } elseif ( $whatsapp_ok ) {
            $sent_channel = 'whatsapp';
        }

        if ( $sent_channel ) {
            STS_Abandoned_Cart_DB::update( $cart_row->id, array(
                'status'           => 'reminded',
                'reminder_sent_at' => current_time( 'mysql' ),
                'reminder_channel' => $sent_channel,
                'error_message'    => null,
            ) );
            return true;
        }

        // Nothing sent — increment attempt count and record error.
        $attempt_count = (int) $cart_row->attempt_count + 1;
        $new_status    = $attempt_count >= 3 ? 'expired' : 'pending';
        STS_Abandoned_Cart_DB::update( $cart_row->id, array(
            'status'        => $new_status,
            'attempt_count' => $attempt_count,
            'error_message' => $last_error ? $last_error->get_error_message() : 'No channel succeeded',
        ) );

        return $last_error ?: new WP_Error( 'no_channel', 'No channel succeeded' );
    }
}
