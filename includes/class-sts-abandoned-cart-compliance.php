<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * All Israeli-law compliance obligations live here so they can be audited in one place.
 *
 * Hard-coded: suppression check, sender identity, unsubscribe link.
 * Configurable: subject prefix (default "תזכורת", can switch to "פרסומת | תזכורת").
 */
class STS_Abandoned_Cart_Compliance {

    public static function prefix_subject( $raw ) {
        $prefix = get_option( 'sts_ac_subject_prefix', 'פרסומת' );
        return $prefix . ' | ' . $raw;
    }

    public static function whatsapp_message_prefix() {
        $prefix = get_option( 'sts_ac_subject_prefix', 'פרסומת' );
        return $prefix . ': ';
    }

    public static function whatsapp_footer_text( $cart_row ) {
        $unsub_url = self::unsubscribe_url( $cart_row->unsubscribe_token );
        return "\n\n— להסרה מרשימת התזכורות: " . $unsub_url;
    }

    public static function email_footer_html( $cart_row ) {
        $sender_name = get_option( 'sts_ac_sender_name', get_bloginfo( 'name' ) );
        $address     = get_option( 'sts_ac_sender_address', '' );
        $business_id = get_option( 'sts_ac_sender_business_id', '' );
        $unsub_url   = self::unsubscribe_url( $cart_row->unsubscribe_token );
        $store_url   = home_url();

        ob_start();
        ?>
        <table width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #e8e0d0;margin-top:30px;padding-top:20px;direction:rtl;text-align:right;">
            <tr>
                <td style="font-family:Arial,sans-serif;font-size:12px;color:#888;line-height:1.6;">
                    <p style="margin:0 0 6px 0;">
                        קיבלתם הודעה זו מכיוון שהתחלתם תהליך רכישה באתר
                        <a href="<?php echo esc_url( $store_url ); ?>" style="color:#888;"><?php echo esc_html( $sender_name ); ?></a>.
                    </p>
                    <?php if ( $address ) : ?>
                    <p style="margin:0 0 6px 0;"><?php echo esc_html( $sender_name ); ?> | <?php echo esc_html( $address ); ?><?php echo $business_id ? ' | ח.פ/ע.מ: ' . esc_html( $business_id ) : ''; ?></p>
                    <?php endif; ?>
                    <p style="margin:0;">
                        <a href="<?php echo esc_url( $unsub_url ); ?>" style="color:#c00;text-decoration:underline;">הסרה מרשימת התזכורות</a>
                        &nbsp;|&nbsp;
                        <a href="<?php echo esc_url( $store_url ); ?>" style="color:#888;">לאתר</a>
                    </p>
                </td>
            </tr>
        </table>
        <?php
        return ob_get_clean();
    }

    public static function can_send( $email, $phone = '' ) {
        if ( STS_Abandoned_Cart_DB::is_suppressed( $email, $phone ) ) {
            return new WP_Error( 'suppressed', 'כתובת האימייל/טלפון ברשימת ההסרות' );
        }
        return true;
    }

    public static function unsubscribe_url( $unsubscribe_token ) {
        return add_query_arg( 'sts_ac_unsub', rawurlencode( $unsubscribe_token ), home_url( '/' ) );
    }

    public static function open_pixel_url( $session_token ) {
        return add_query_arg( 'sts_ac_open', rawurlencode( $session_token ), home_url( '/' ) );
    }

    public static function recovery_url( $session_token ) {
        return add_query_arg( 'sts_ac_recover', rawurlencode( $session_token ), wc_get_checkout_url() );
    }
}
