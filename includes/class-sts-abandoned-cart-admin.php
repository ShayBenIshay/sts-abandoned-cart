<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STS_Abandoned_Cart_Admin {

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
        add_action( 'admin_menu',            array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_init',            array( $this, 'handle_actions' ) );
        add_action( 'wp_ajax_sts_ac_resend',  array( $this, 'ajax_resend' ) );
        add_action( 'wp_ajax_sts_ac_test_send', array( $this, 'ajax_test_send' ) );
        add_action( 'wp_ajax_sts_ac_set_compliance_prefix', array( $this, 'ajax_set_compliance_prefix' ) );
    }

    public function register_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'עגלות נטושות', 'sts-abandoned-cart' ),
            __( 'עגלות נטושות', 'sts-abandoned-cart' ),
            'manage_woocommerce',
            'sts-abandoned-cart',
            array( $this, 'render_dashboard' )
        );

        add_submenu_page(
            'woocommerce',
            __( 'עגלות נטושות — רשימה', 'sts-abandoned-cart' ),
            __( 'רשימת עגלות', 'sts-abandoned-cart' ),
            'manage_woocommerce',
            'sts-abandoned-cart-list',
            array( $this, 'render_carts_list' )
        );

        add_submenu_page(
            'woocommerce',
            __( 'עגלות נטושות — הגדרות', 'sts-abandoned-cart' ),
            __( 'הגדרות עגלות נטושות', 'sts-abandoned-cart' ),
            'manage_woocommerce',
            'sts-abandoned-cart-settings',
            array( $this, 'render_settings' )
        );
    }

    public function enqueue_assets( $hook ) {
        $pages = array(
            'woocommerce_page_sts-abandoned-cart',
            'woocommerce_page_sts-abandoned-cart-list',
            'woocommerce_page_sts-abandoned-cart-settings',
        );

        if ( ! in_array( $hook, $pages, true ) ) {
            return;
        }

        wp_enqueue_style(
            'sts-ac-admin',
            STS_AC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            STS_AC_VERSION
        );

        wp_enqueue_script(
            'sts-ac-admin',
            STS_AC_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            STS_AC_VERSION,
            true
        );

        wp_localize_script( 'sts-ac-admin', 'stsAcAdmin', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'sts_ac_admin' ),
        ) );
    }

    public function handle_actions() {
        if ( empty( $_GET['page'] ) || 0 !== strpos( $_GET['page'], 'sts-abandoned-cart' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        // Save settings.
        if ( isset( $_POST['sts_ac_save_settings'] ) ) {
            check_admin_referer( 'sts_ac_settings' );
            $this->save_settings();
            wp_redirect( add_query_arg( 'saved', '1', wp_get_referer() ) );
            exit;
        }

        // Delete cart.
        if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && ! empty( $_GET['cart_id'] ) ) {
            check_admin_referer( 'sts_ac_cart_action' );
            STS_Abandoned_Cart_DB::delete( absint( $_GET['cart_id'] ) );
            wp_redirect( remove_query_arg( array( 'action', 'cart_id', '_wpnonce' ) ) );
            exit;
        }

        // Mark as recovered.
        if ( isset( $_GET['action'] ) && 'mark_recovered' === $_GET['action'] && ! empty( $_GET['cart_id'] ) ) {
            check_admin_referer( 'sts_ac_cart_action' );
            STS_Abandoned_Cart_DB::update( absint( $_GET['cart_id'] ), array(
                'status'       => 'recovered',
                'recovered_at' => current_time( 'mysql' ),
            ) );
            wp_redirect( remove_query_arg( array( 'action', 'cart_id', '_wpnonce' ) ) );
            exit;
        }
    }

    private function save_settings() {
        $fields = array(
            'sts_ac_reminder_delay'    => 'intval',
            'sts_ac_from_email'        => 'sanitize_email',
            'sts_ac_from_name'         => 'sanitize_text_field',
            'sts_ac_sender_name'       => 'sanitize_text_field',
            'sts_ac_sender_address'    => 'sanitize_text_field',
            'sts_ac_sender_business_id'=> 'sanitize_text_field',
            'sts_ac_logo_url'          => 'esc_url_raw',
            'sts_ac_subject_prefix'    => 'sanitize_text_field',
            'sts_ac_wa_endpoint'       => 'esc_url_raw',
            'sts_ac_wa_auth_header'    => 'sanitize_text_field',
            'sts_ac_wa_auth_value'     => 'sanitize_text_field',
        );

        foreach ( $fields as $key => $sanitizer ) {
            if ( isset( $_POST[ $key ] ) ) {
                update_option( $key, $sanitizer( wp_unslash( $_POST[ $key ] ) ) );
            }
        }

        // Channels: array of checked values.
        $channels = array();
        if ( ! empty( $_POST['sts_ac_channels'] ) && is_array( $_POST['sts_ac_channels'] ) ) {
            foreach ( $_POST['sts_ac_channels'] as $ch ) {
                if ( in_array( $ch, array( 'email', 'whatsapp' ), true ) ) {
                    $channels[] = $ch;
                }
            }
        }
        update_option( 'sts_ac_channels', $channels ?: array( 'email' ) );
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    public function ajax_resend() {
        check_ajax_referer( 'sts_ac_admin', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => 'אין הרשאה' ) );
        }

        $cart_id = absint( $_POST['cart_id'] ?? 0 );
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sts_abandoned_carts WHERE id = %d LIMIT 1",
            $cart_id
        ) );

        if ( ! $row ) {
            wp_send_json_error( array( 'message' => 'עגלה לא נמצאה' ) );
        }

        // Reset status so cron/sender will pick it up.
        STS_Abandoned_Cart_DB::update( $row->id, array(
            'status'           => 'pending',
            'reminder_sent_at' => null,
            'attempt_count'    => 0,
            'error_message'    => null,
        ) );

        $sender = new STS_Abandoned_Cart_Sender();
        $result = $sender->send( $row );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array( 'message' => 'תזכורת נשלחה בהצלחה' ) );
    }

    public function ajax_test_send() {
        check_ajax_referer( 'sts_ac_admin', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => 'אין הרשאה' ) );
        }

        $admin_email = get_option( 'admin_email' );
        $store_name  = get_option( 'sts_ac_sender_name', get_bloginfo( 'name' ) );

        // Build a synthetic cart row for preview.
        $fake_token = md5( 'test_' . time() );
        $fake_row   = (object) array(
            'id'                => 0,
            'session_token'     => $fake_token,
            'unsubscribe_token' => md5( 'unsub_test_' . time() ),
            'email'             => $admin_email,
            'phone'             => '',
            'cart_contents'     => wp_json_encode( array(
                array(
                    'product_id'   => 0,
                    'variation_id' => 0,
                    'qty'          => 1,
                    'name'         => 'מוצר לדוגמה — בדיקת תזכורת',
                    'price'        => 199.00,
                    'image_url'    => '',
                    'permalink'    => home_url( '/' ),
                ),
            ) ),
            'cart_total'        => 199.00,
            'currency'          => get_woocommerce_currency(),
            'coupons'           => '[]',
            'reminder_channel'  => 'email',
        );

        $mailer = new STS_Abandoned_Cart_Email();
        $result = $mailer->send( $fake_row );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => 'שגיאה: ' . $result->get_error_message() ) );
        }

        wp_send_json_success( array( 'message' => 'אימייל בדיקה נשלח לכתובת ' . $admin_email ) );
    }

    public function ajax_set_compliance_prefix() {
        check_ajax_referer( 'sts_ac_admin', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error();
        }
        update_option( 'sts_ac_subject_prefix', 'פרסומת | תזכורת' );
        wp_send_json_success( array( 'prefix' => 'פרסומת | תזכורת' ) );
    }

    // -------------------------------------------------------------------------
    // Page renderers
    // -------------------------------------------------------------------------

    public function render_dashboard() {
        $stats  = STS_Abandoned_Cart_DB::get_stats( 30 );
        $daily  = STS_Abandoned_Cart_DB::get_daily_counts( 30 );
        $cur_sym = get_woocommerce_currency_symbol();

        $open_rate    = $stats['reminded'] > 0 ? round( $stats['opened']    / $stats['reminded'] * 100, 1 ) : 0;
        $click_rate   = $stats['reminded'] > 0 ? round( $stats['clicked']   / $stats['reminded'] * 100, 1 ) : 0;
        $recover_rate = $stats['reminded'] > 0 ? round( $stats['recovered'] / $stats['reminded'] * 100, 1 ) : 0;

        $max_daily = 1;
        foreach ( $daily as $d ) {
            $max_daily = max( $max_daily, (int) $d->abandoned );
        }
        ?>
        <div class="wrap sts-ac-wrap" dir="rtl">
            <h1><?php esc_html_e( 'עגלות נטושות — לוח בקרה', 'sts-abandoned-cart' ); ?></h1>
            <p class="sts-ac-period"><?php esc_html_e( '30 ימים אחרונים', 'sts-abandoned-cart' ); ?></p>

            <div class="sts-ac-kpi-grid">
                <?php $this->kpi( 'עגלות שננטשו', $stats['abandoned'] ); ?>
                <?php $this->kpi( 'תזכורות שנשלחו', $stats['reminded'] ); ?>
                <?php $this->kpi( 'שיעור פתיחה', $open_rate . '%' ); ?>
                <?php $this->kpi( 'שיעור הקלקה', $click_rate . '%' ); ?>
                <?php $this->kpi( 'עגלות שהתאוששו', $stats['recovered'] ); ?>
                <?php $this->kpi( 'הכנסות שחולצו', $cur_sym . number_format( $stats['recovered_revenue'], 2 ) ); ?>
            </div>

            <?php if ( $daily ) : ?>
            <div class="sts-ac-chart-wrap">
                <h2><?php esc_html_e( 'פעילות יומית', 'sts-abandoned-cart' ); ?></h2>
                <div class="sts-ac-chart">
                    <?php foreach ( $daily as $d ) :
                        $pct_abandoned = $max_daily > 0 ? round( (int) $d->abandoned / $max_daily * 100 ) : 0;
                        $pct_recovered = $max_daily > 0 ? round( (int) $d->recovered / $max_daily * 100 ) : 0;
                    ?>
                    <div class="sts-ac-bar-group" title="<?php echo esc_attr( $d->day . ': ' . $d->abandoned . ' נטושות, ' . $d->recovered . ' שחזורים' ); ?>">
                        <div class="sts-ac-bar-inner">
                            <div class="sts-ac-bar sts-ac-bar--abandoned" style="height:<?php echo $pct_abandoned; ?>%"></div>
                            <div class="sts-ac-bar sts-ac-bar--recovered" style="height:<?php echo $pct_recovered; ?>%"></div>
                        </div>
                        <span class="sts-ac-bar-label"><?php echo esc_html( date( 'd/m', strtotime( $d->day ) ) ); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="sts-ac-legend">
                    <span class="sts-ac-legend-item sts-ac-legend--abandoned"><?php esc_html_e( 'נטושות', 'sts-abandoned-cart' ); ?></span>
                    <span class="sts-ac-legend-item sts-ac-legend--recovered"><?php esc_html_e( 'שוחזרו', 'sts-abandoned-cart' ); ?></span>
                </div>
            </div>
            <?php endif; ?>

            <p style="margin-top:20px;">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=sts-abandoned-cart-list' ) ); ?>" class="button">
                    <?php esc_html_e( 'לרשימת העגלות הנטושות', 'sts-abandoned-cart' ); ?>
                </a>
            </p>
        </div>
        <?php
    }

    private function kpi( $label, $value ) {
        ?>
        <div class="sts-ac-kpi">
            <div class="sts-ac-kpi__value"><?php echo esc_html( $value ); ?></div>
            <div class="sts-ac-kpi__label"><?php echo esc_html( $label ); ?></div>
        </div>
        <?php
    }

    public function render_carts_list() {
        $args = array(
            'status'    => sanitize_text_field( $_GET['status'] ?? 'all' ),
            'email'     => sanitize_text_field( $_GET['s'] ?? '' ),
            'date_from' => sanitize_text_field( $_GET['date_from'] ?? '' ),
            'date_to'   => sanitize_text_field( $_GET['date_to'] ?? '' ),
            'paged'     => max( 1, intval( $_GET['paged'] ?? 1 ) ),
            'per_page'  => 25,
        );

        $result   = STS_Abandoned_Cart_DB::get_carts_list( $args );
        $rows     = $result['rows'];
        $total    = $result['total'];
        $pages    = ceil( $total / $args['per_page'] );
        $statuses = array( 'all' => 'הכל', 'pending' => 'ממתין', 'reminded' => 'נשלח', 'recovered' => 'שוחזר', 'expired' => 'פג תוקף', 'unsubscribed' => 'הסיר' );
        ?>
        <div class="wrap sts-ac-wrap" dir="rtl">
            <h1><?php esc_html_e( 'עגלות נטושות — רשימה', 'sts-abandoned-cart' ); ?></h1>

            <form method="get" class="sts-ac-filter-form">
                <input type="hidden" name="page" value="sts-abandoned-cart-list">
                <select name="status">
                    <?php foreach ( $statuses as $val => $label ) : ?>
                    <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $args['status'], $val ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="search" name="s" placeholder="חיפוש לפי אימייל" value="<?php echo esc_attr( $args['email'] ); ?>">
                <input type="date" name="date_from" value="<?php echo esc_attr( $args['date_from'] ); ?>">
                <span>עד</span>
                <input type="date" name="date_to" value="<?php echo esc_attr( $args['date_to'] ); ?>">
                <button type="submit" class="button"><?php esc_html_e( 'סנן', 'sts-abandoned-cart' ); ?></button>
            </form>

            <table class="wp-list-table widefat fixed striped sts-ac-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'אימייל', 'sts-abandoned-cart' ); ?></th>
                        <th><?php esc_html_e( 'טלפון', 'sts-abandoned-cart' ); ?></th>
                        <th><?php esc_html_e( 'סכום', 'sts-abandoned-cart' ); ?></th>
                        <th><?php esc_html_e( 'סטטוס', 'sts-abandoned-cart' ); ?></th>
                        <th><?php esc_html_e( 'נוצר', 'sts-abandoned-cart' ); ?></th>
                        <th><?php esc_html_e( 'פעולות', 'sts-abandoned-cart' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $rows ) ) : ?>
                    <tr><td colspan="6"><?php esc_html_e( 'לא נמצאו עגלות', 'sts-abandoned-cart' ); ?></td></tr>
                <?php else : foreach ( $rows as $row ) :
                    $status_labels = array( 'pending' => 'ממתין', 'reminded' => 'נשלח', 'recovered' => 'שוחזר', 'expired' => 'פג תוקף', 'unsubscribed' => 'הסיר' );
                    $status_label  = $status_labels[ $row->status ] ?? $row->status;
                    $cur_sym       = get_woocommerce_currency_symbol( $row->currency );
                    $nonce_url     = wp_nonce_url( add_query_arg( array( 'page' => 'sts-abandoned-cart-list', 'cart_id' => $row->id, 'action' => '__ACTION__' ), admin_url( 'admin.php' ) ), 'sts_ac_cart_action' );
                    $delete_url    = str_replace( '__ACTION__', 'delete', $nonce_url );
                    $recover_url   = str_replace( '__ACTION__', 'mark_recovered', $nonce_url );
                ?>
                    <tr>
                        <td><?php echo esc_html( $row->email ); ?></td>
                        <td><?php echo esc_html( $row->phone ?: '—' ); ?></td>
                        <td><?php echo esc_html( $cur_sym . number_format( (float) $row->cart_total, 2 ) ); ?></td>
                        <td><span class="sts-ac-status sts-ac-status--<?php echo esc_attr( $row->status ); ?>"><?php echo esc_html( $status_label ); ?></span>
                            <?php if ( $row->error_message ) : ?>
                            <span class="sts-ac-error-hint" title="<?php echo esc_attr( $row->error_message ); ?>">⚠</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $row->created_at ) ) ); ?></td>
                        <td class="sts-ac-row-actions">
                            <button class="button button-small sts-ac-resend-btn" data-cart-id="<?php echo esc_attr( $row->id ); ?>"><?php esc_html_e( 'שלח שוב', 'sts-abandoned-cart' ); ?></button>
                            <?php if ( 'recovered' !== $row->status ) : ?>
                            <a href="<?php echo esc_url( $recover_url ); ?>" class="button button-small"><?php esc_html_e( 'סמן כשוחזר', 'sts-abandoned-cart' ); ?></a>
                            <?php endif; ?>
                            <a href="<?php echo esc_url( $delete_url ); ?>" class="button button-small sts-ac-delete-btn" onclick="return confirm('למחוק עגלה זו?')"><?php esc_html_e( 'מחק', 'sts-abandoned-cart' ); ?></a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>

            <?php if ( $pages > 1 ) : ?>
            <div class="tablenav sts-ac-pagination">
                <?php
                echo paginate_links( array(
                    'base'    => add_query_arg( 'paged', '%#%' ),
                    'format'  => '',
                    'current' => $args['paged'],
                    'total'   => $pages,
                ) );
                ?>
                <span class="sts-ac-total"><?php echo sprintf( '%d עגלות', $total ); ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public function render_settings() {
        $saved    = ! empty( $_GET['saved'] );
        $channels = get_option( 'sts_ac_channels', array( 'email' ) );
        $prefix   = get_option( 'sts_ac_subject_prefix', 'פרסומת' );
        ?>
        <div class="wrap sts-ac-wrap" dir="rtl">
            <h1><?php esc_html_e( 'הגדרות עגלות נטושות', 'sts-abandoned-cart' ); ?></h1>

            <?php if ( $saved ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'ההגדרות נשמרו', 'sts-abandoned-cart' ); ?></p></div>
            <?php endif; ?>

            <form method="post" action="">
                <?php wp_nonce_field( 'sts_ac_settings' ); ?>
                <input type="hidden" name="sts_ac_save_settings" value="1">

                <table class="form-table" role="presentation">

                    <tr>
                        <th scope="row"><?php esc_html_e( 'דחיית תזכורת (דקות)', 'sts-abandoned-cart' ); ?></th>
                        <td>
                            <input type="number" name="sts_ac_reminder_delay" min="1" max="10080"
                                   value="<?php echo esc_attr( get_option( 'sts_ac_reminder_delay', 60 ) ); ?>" class="small-text">
                            <p class="description"><?php esc_html_e( 'כמה דקות לחכות לאחר נטישה לפני שליחת תזכורת. ברירת מחדל: 60 דקות.', 'sts-abandoned-cart' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e( 'ערוצי שליחה', 'sts-abandoned-cart' ); ?></th>
                        <td>
                            <label><input type="checkbox" name="sts_ac_channels[]" value="email" <?php checked( in_array( 'email', $channels, true ) ); ?>> <?php esc_html_e( 'אימייל', 'sts-abandoned-cart' ); ?></label><br>
                            <label><input type="checkbox" name="sts_ac_channels[]" value="whatsapp" <?php checked( in_array( 'whatsapp', $channels, true ) ); ?>> <?php esc_html_e( 'WhatsApp', 'sts-abandoned-cart' ); ?></label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e( 'שם שולח (אימייל)', 'sts-abandoned-cart' ); ?></th>
                        <td><input type="text" name="sts_ac_from_name" value="<?php echo esc_attr( get_option( 'sts_ac_from_name', get_bloginfo( 'name' ) ) ); ?>" class="regular-text"></td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e( 'כתובת שולח (אימייל)', 'sts-abandoned-cart' ); ?></th>
                        <td><input type="email" name="sts_ac_from_email" value="<?php echo esc_attr( get_option( 'sts_ac_from_email', get_option( 'admin_email' ) ) ); ?>" class="regular-text"></td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e( 'שם העסק (לחתימה)', 'sts-abandoned-cart' ); ?></th>
                        <td><input type="text" name="sts_ac_sender_name" value="<?php echo esc_attr( get_option( 'sts_ac_sender_name', get_bloginfo( 'name' ) ) ); ?>" class="regular-text"></td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e( 'כתובת העסק (לחתימה)', 'sts-abandoned-cart' ); ?></th>
                        <td><input type="text" name="sts_ac_sender_address" value="<?php echo esc_attr( get_option( 'sts_ac_sender_address', '' ) ); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e( 'נדרש לפי חוק התקשורת תיקון 40.', 'sts-abandoned-cart' ); ?></p></td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e( 'ח.פ / ע.מ', 'sts-abandoned-cart' ); ?></th>
                        <td><input type="text" name="sts_ac_sender_business_id" value="<?php echo esc_attr( get_option( 'sts_ac_sender_business_id', '' ) ); ?>" class="regular-text"></td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e( 'לוגו לאימייל (URL)', 'sts-abandoned-cart' ); ?></th>
                        <td><input type="url" name="sts_ac_logo_url" value="<?php echo esc_attr( get_option( 'sts_ac_logo_url', home_url( '/wp-content/uploads/2025/12webp/Logo-pearl.webp' ) ) ); ?>" class="regular-text"></td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e( 'קידומת נושא הודעה', 'sts-abandoned-cart' ); ?></th>
                        <td>
                            <input type="text" name="sts_ac_subject_prefix" id="sts_ac_subject_prefix"
                                   value="<?php echo esc_attr( $prefix ?: 'פרסומת' ); ?>" class="regular-text">
                            <div class="sts-ac-law-warning">
                                <strong><?php esc_html_e( 'שים לב — חוק התקשורת תיקון 40:', 'sts-abandoned-cart' ); ?></strong>
                                <?php esc_html_e( 'קריאה שמרנית של החוק מחייבת שימוש במילה "פרסומת" בנושא הודעות שיווקיות. עגלה נטושה נמצאת בגבול — ייתכן שתנאי שימוש ב"תזכורת" יחשב כ"דבר פרסומת". לפעולה בטוחה לחצו:', 'sts-abandoned-cart' ); ?>
                                <button type="button" id="sts-ac-compliance-btn" class="button button-small">
                                    <?php esc_html_e( 'החלף למצב תאימות מחמיר (פרסומת | תזכורת)', 'sts-abandoned-cart' ); ?>
                                </button>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e( 'WhatsApp — כתובת Webhook', 'sts-abandoned-cart' ); ?></th>
                        <td>
                            <input type="url" name="sts_ac_wa_endpoint" value="<?php echo esc_attr( get_option( 'sts_ac_wa_endpoint', '' ) ); ?>" class="regular-text" placeholder="https://your-wwebjs-service/send">
                            <p class="description"><?php esc_html_e( 'שירות ה-wwebjs שלכם. הפלאגין ישלח POST עם JSON: {"to":"...","message":"..."}.', 'sts-abandoned-cart' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e( 'WhatsApp — Auth Header', 'sts-abandoned-cart' ); ?></th>
                        <td><input type="text" name="sts_ac_wa_auth_header" value="<?php echo esc_attr( get_option( 'sts_ac_wa_auth_header', 'Authorization' ) ); ?>" class="regular-text" placeholder="Authorization"></td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e( 'WhatsApp — Auth Value', 'sts-abandoned-cart' ); ?></th>
                        <td><input type="text" name="sts_ac_wa_auth_value" value="<?php echo esc_attr( get_option( 'sts_ac_wa_auth_value', '' ) ); ?>" class="regular-text" placeholder="Bearer YOUR_TOKEN"></td>
                    </tr>

                </table>

                <p class="submit">
                    <?php submit_button( __( 'שמור הגדרות', 'sts-abandoned-cart' ), 'primary', 'submit', false ); ?>
                    &nbsp;
                    <button type="button" id="sts-ac-test-send-btn" class="button">
                        <?php esc_html_e( 'שלח אימייל בדיקה לכתובת המנהל', 'sts-abandoned-cart' ); ?>
                    </button>
                    <span id="sts-ac-test-send-result" style="margin-right:10px;"></span>
                </p>
            </form>
        </div>
        <?php
    }
}
