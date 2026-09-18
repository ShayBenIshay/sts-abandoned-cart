<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Only drop data if admin opted in — default is OFF to prevent accidental data loss.
if ( get_option( 'sts_ac_delete_on_uninstall' ) !== 'yes' ) {
    return;
}

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sts_abandoned_carts" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sts_abandoned_cart_unsubscribes" );

$options = array(
    'sts_ac_db_version',
    'sts_ac_reminder_delay',
    'sts_ac_channels',
    'sts_ac_from_email',
    'sts_ac_from_name',
    'sts_ac_sender_name',
    'sts_ac_sender_address',
    'sts_ac_sender_business_id',
    'sts_ac_logo_url',
    'sts_ac_subject_prefix',
    'sts_ac_wa_endpoint',
    'sts_ac_wa_auth_header',
    'sts_ac_wa_auth_value',
    'sts_ac_delete_on_uninstall',
);

foreach ( $options as $option ) {
    delete_option( $option );
}
