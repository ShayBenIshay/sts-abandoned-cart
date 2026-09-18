<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STS_Abandoned_Cart_DB {

    const SCHEMA_VERSION = '1.0.0';

    public static function install() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $carts = $wpdb->prefix . 'sts_abandoned_carts';
        dbDelta( "CREATE TABLE $carts (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  session_token CHAR(32) NOT NULL DEFAULT '',
  unsubscribe_token CHAR(32) NOT NULL DEFAULT '',
  user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
  email VARCHAR(190) NOT NULL DEFAULT '',
  phone VARCHAR(32) NOT NULL DEFAULT '',
  cart_contents LONGTEXT NOT NULL,
  cart_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  currency CHAR(3) NOT NULL DEFAULT '',
  coupons TEXT NOT NULL,
  consent_source VARCHAR(40) NOT NULL DEFAULT '',
  consent_at DATETIME NULL,
  consent_ip VARCHAR(45) NOT NULL DEFAULT '',
  consent_user_agent TEXT NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  reminder_scheduled_at DATETIME NULL,
  reminder_sent_at DATETIME NULL,
  reminder_channel VARCHAR(20) NOT NULL DEFAULT 'email',
  attempt_count TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
  opened_at DATETIME NULL,
  clicked_at DATETIME NULL,
  recovered_at DATETIME NULL,
  recovery_order_id BIGINT(20) UNSIGNED NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  error_message TEXT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY session_token (session_token),
  UNIQUE KEY unsubscribe_token (unsubscribe_token),
  KEY email (email),
  KEY phone (phone),
  KEY status_scheduled (status, reminder_scheduled_at)
) $charset_collate;" );

        $unsub = $wpdb->prefix . 'sts_abandoned_cart_unsubscribes';
        dbDelta( "CREATE TABLE $unsub (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(190) NOT NULL DEFAULT '',
  phone VARCHAR(32) NOT NULL DEFAULT '',
  unsubscribed_at DATETIME NOT NULL,
  source VARCHAR(40) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  UNIQUE KEY email (email)
) $charset_collate;" );

        update_option( 'sts_ac_db_version', self::SCHEMA_VERSION );
    }

    public static function is_suppressed( $email, $phone = '' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'sts_abandoned_cart_unsubscribes';

        if ( $email ) {
            $hit = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM $table WHERE email = %s LIMIT 1",
                strtolower( trim( $email ) )
            ) );
            if ( $hit ) return true;
        }

        if ( $phone ) {
            $hit = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM $table WHERE phone = %s LIMIT 1",
                $phone
            ) );
            if ( $hit ) return true;
        }

        return false;
    }

    public static function add_suppression( $email, $phone = '', $source = 'email_link' ) {
        global $wpdb;
        $wpdb->replace(
            $wpdb->prefix . 'sts_abandoned_cart_unsubscribes',
            array(
                'email'           => strtolower( trim( $email ) ),
                'phone'           => $phone,
                'unsubscribed_at' => current_time( 'mysql' ),
                'source'          => $source,
            ),
            array( '%s', '%s', '%s', '%s' )
        );
    }

    public static function upsert_cart( $session_token, $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'sts_abandoned_carts';

        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, created_at FROM $table WHERE session_token = %s LIMIT 1",
            $session_token
        ) );

        if ( $existing ) {
            $wpdb->update(
                $table,
                array(
                    'email'                  => strtolower( trim( $data['email'] ) ),
                    'phone'                  => $data['phone'] ?? '',
                    'cart_contents'          => $data['cart_contents'],
                    'cart_total'             => $data['cart_total'],
                    'currency'               => $data['currency'],
                    'coupons'                => $data['coupons'] ?? '',
                    'consent_source'         => $data['consent_source'],
                    'consent_at'             => current_time( 'mysql' ),
                    'consent_ip'             => $data['consent_ip'] ?? '',
                    'consent_user_agent'     => $data['consent_user_agent'] ?? '',
                    'updated_at'             => current_time( 'mysql' ),
                    'reminder_scheduled_at'  => $data['reminder_scheduled_at'],
                ),
                array( 'id' => $existing->id ),
                array( '%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s' ),
                array( '%d' )
            );
            return $existing->id;
        }

        $wpdb->insert(
            $table,
            array(
                'session_token'          => $session_token,
                'unsubscribe_token'      => $data['unsubscribe_token'],
                'user_id'                => $data['user_id'] ?? 0,
                'email'                  => strtolower( trim( $data['email'] ) ),
                'phone'                  => $data['phone'] ?? '',
                'cart_contents'          => $data['cart_contents'],
                'cart_total'             => $data['cart_total'],
                'currency'               => $data['currency'],
                'coupons'                => $data['coupons'] ?? '',
                'consent_source'         => $data['consent_source'],
                'consent_at'             => current_time( 'mysql' ),
                'consent_ip'             => $data['consent_ip'] ?? '',
                'consent_user_agent'     => $data['consent_user_agent'] ?? '',
                'created_at'             => current_time( 'mysql' ),
                'updated_at'             => current_time( 'mysql' ),
                'reminder_scheduled_at'  => $data['reminder_scheduled_at'],
                'status'                 => 'pending',
            ),
            array(
                '%s','%s','%d','%s','%s','%s','%f','%s','%s',
                '%s','%s','%s','%s','%s','%s','%s','%s',
            )
        );

        return $wpdb->insert_id;
    }

    public static function get_by_session_token( $token ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sts_abandoned_carts WHERE session_token = %s LIMIT 1",
            $token
        ) );
    }

    public static function get_by_unsubscribe_token( $token ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sts_abandoned_carts WHERE unsubscribe_token = %s LIMIT 1",
            $token
        ) );
    }

    public static function get_pending_reminded_by_email( $email, $days = 7 ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sts_abandoned_carts
             WHERE email = %s AND status = 'reminded'
               AND reminder_sent_at >= %s
             ORDER BY reminder_sent_at DESC LIMIT 1",
            strtolower( trim( $email ) ),
            date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) )
        ) );
    }

    public static function update( $id, $data, $formats = null ) {
        global $wpdb;
        if ( null === $formats ) {
            $formats = array_fill( 0, count( $data ), '%s' );
        }
        $wpdb->update(
            $wpdb->prefix . 'sts_abandoned_carts',
            $data,
            array( 'id' => absint( $id ) ),
            $formats,
            array( '%d' )
        );
    }

    public static function delete( $id ) {
        global $wpdb;
        $wpdb->delete(
            $wpdb->prefix . 'sts_abandoned_carts',
            array( 'id' => absint( $id ) ),
            array( '%d' )
        );
    }

    public static function get_pending_for_cron( $limit = 50 ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sts_abandoned_carts
             WHERE status = 'pending'
               AND reminder_scheduled_at <= %s
               AND reminder_scheduled_at > %s
             LIMIT %d",
            current_time( 'mysql' ),
            date( 'Y-m-d H:i:s', strtotime( '-7 days' ) ),
            $limit
        ) );
    }

    public static function expire_old_pending() {
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->prefix}sts_abandoned_carts
             SET status = 'expired'
             WHERE status = 'pending'
               AND reminder_scheduled_at <= %s",
            date( 'Y-m-d H:i:s', strtotime( '-7 days' ) )
        ) );
    }

    public static function get_stats( $days = 30 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'sts_abandoned_carts';
        $from  = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

        return array(
            'abandoned'         => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE created_at >= %s", $from ) ),
            'reminded'          => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE reminder_sent_at >= %s", $from ) ),
            'opened'            => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE opened_at >= %s", $from ) ),
            'clicked'           => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE clicked_at >= %s", $from ) ),
            'recovered'         => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE recovered_at >= %s", $from ) ),
            'recovered_revenue' => (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(cart_total),0) FROM $table WHERE recovered_at >= %s", $from ) ),
        );
    }

    public static function get_daily_counts( $days = 30 ) {
        global $wpdb;
        $from = date( 'Y-m-d', strtotime( "-{$days} days" ) );
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT
                DATE(created_at) AS day,
                COUNT(*) AS abandoned,
                SUM(CASE WHEN reminder_sent_at IS NOT NULL THEN 1 ELSE 0 END) AS reminded,
                SUM(CASE WHEN recovered_at IS NOT NULL THEN 1 ELSE 0 END) AS recovered
             FROM {$wpdb->prefix}sts_abandoned_carts
             WHERE DATE(created_at) >= %s
             GROUP BY DATE(created_at)
             ORDER BY day ASC",
            $from
        ) );
    }

    public static function get_carts_list( $args = array() ) {
        global $wpdb;
        $table   = $wpdb->prefix . 'sts_abandoned_carts';
        $where   = array( '1=1' );
        $values  = array();
        $per_page = max( 1, intval( $args['per_page'] ?? 25 ) );
        $page     = max( 1, intval( $args['paged'] ?? 1 ) );
        $offset   = ( $page - 1 ) * $per_page;

        if ( ! empty( $args['status'] ) && 'all' !== $args['status'] ) {
            $where[]  = 'status = %s';
            $values[] = $args['status'];
        }
        if ( ! empty( $args['email'] ) ) {
            $where[]  = 'email LIKE %s';
            $values[] = '%' . $wpdb->esc_like( $args['email'] ) . '%';
        }
        if ( ! empty( $args['date_from'] ) ) {
            $where[]  = 'created_at >= %s';
            $values[] = $args['date_from'] . ' 00:00:00';
        }
        if ( ! empty( $args['date_to'] ) ) {
            $where[]  = 'created_at <= %s';
            $values[] = $args['date_to'] . ' 23:59:59';
        }

        $where_sql = implode( ' AND ', $where );

        $count_sql = "SELECT COUNT(*) FROM $table WHERE $where_sql";
        $rows_sql  = "SELECT * FROM $table WHERE $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d";

        if ( $values ) {
            $total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $values ) );
            $rows  = $wpdb->get_results( $wpdb->prepare( $rows_sql, array_merge( $values, array( $per_page, $offset ) ) ) );
        } else {
            $total = (int) $wpdb->get_var( $count_sql );
            $rows  = $wpdb->get_results( $wpdb->prepare( $rows_sql, $per_page, $offset ) );
        }

        return array( 'rows' => $rows ?: array(), 'total' => $total );
    }
}
