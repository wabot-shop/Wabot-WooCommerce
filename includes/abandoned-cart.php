<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Create custom table on plugin activation
register_activation_hook( __FILE__, 'wabot_create_abandoned_cart_table' );
function wabot_create_abandoned_cart_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wabot_abandoned_carts';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        session_id varchar(255) NOT NULL,
        cart_contents longtext NOT NULL,
        user_id bigint(20) UNSIGNED NULL,
        email varchar(100) NULL,
        phone varchar(20) NULL,
        timestamp datetime NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}

function wabot_capture_cart_data() {
    if ( ! WC()->cart->is_empty() ) {
        if ( is_user_logged_in() ) {
            $user_id = get_current_user_id();
            $user = wp_get_current_user();
            $email = $user->user_email;
            $phone = get_user_meta( $user_id, 'billing_phone', true );
        } else {
            $user_id = null;
            $email = isset( $_SESSION['wabot_guest_email'] ) ? $_SESSION['wabot_guest_email'] : null;
            $phone = isset( $_SESSION['wabot_guest_phone'] ) ? $_SESSION['wabot_guest_phone'] : null;
        }

        // Proceed only if email and phone are available
        if ( ! $email || ! $phone ) {
            return;
        }

        $cart_contents = WC()->cart->get_cart_contents();
        $session_id = WC()->session->get_customer_id();

        global $wpdb;
        $table_name = $wpdb->prefix . 'wabot_abandoned_carts';
        $wpdb->replace( $table_name, array(
            'session_id'    => $session_id,
            'cart_contents' => maybe_serialize( $cart_contents ),
            'user_id'       => $user_id,
            'email'         => $email,
            'phone'         => $phone,
            'timestamp'     => current_time( 'mysql' ),
        ) );
    }
}


// Remove cart data on order completion
add_action( 'woocommerce_thankyou', 'wabot_remove_cart_data_on_order' );
function wabot_remove_cart_data_on_order( $order_id ) {
    $order = wc_get_order( $order_id );
    $session_id = WC()->session->get_customer_id();

    global $wpdb;
    $table_name = $wpdb->prefix . 'wabot_abandoned_carts';
    $wpdb->delete( $table_name, array( 'session_id' => $session_id ) );
}

// Schedule Cron Event
if ( ! wp_next_scheduled( 'wabot_check_abandoned_carts' ) ) {
    wp_schedule_event( time(), 'hourly', 'wabot_check_abandoned_carts' );
}

add_action( 'wabot_check_abandoned_carts', 'wabot_process_abandoned_carts' );
function wabot_process_abandoned_carts() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wabot_abandoned_carts';

    $options = get_option( 'wabot_settings' );
    $abandonment_time = isset( $options['wabot_abandonment_time'] ) ? intval( $options['wabot_abandonment_time'] ) * 60 : 3600; // Default 1 hour

    $results = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM $table_name WHERE TIMESTAMPDIFF(SECOND, timestamp, NOW()) > %d",
        $abandonment_time
    ) );

    foreach ( $results as $cart ) {
        // Check if recovery message already sent
        if ( ! get_post_meta( $cart->id, '_wabot_recovery_sent', true ) ) {
            // Send recovery email or WhatsApp message
            wabot_send_recovery_message( $cart );
            // Mark as processed
            update_post_meta( $cart->id, '_wabot_recovery_sent', 'yes' );
        }
    }
}

function wabot_send_recovery_message( $cart ) {
    $options = get_option( 'wabot_settings' );

    // Generate coupon code
    $coupon_code = 'RECOVER-' . strtoupper( wp_generate_password( 6, false ) );

    // Create a new coupon
    $coupon = new WC_Coupon();
    $coupon->set_code( $coupon_code );
    $coupon->set_discount_type( 'percent' ); // or 'fixed_cart'
    $coupon->set_amount( 10 ); // 10% discount
    $coupon->set_individual_use( true );
    $coupon->set_usage_limit( 1 );
    $coupon->set_date_expires( strtotime( '+7 days' ) );
    $coupon->save();

    // Prepare variables
    $variables = array(
        'coupon_code' => $coupon_code,
        'cart_link'   => site_url( '/cart/' ),
    );

    // Send email
    if ( $cart->email ) {
        $to = $cart->email;
        $subject = 'We Miss You! Here’s a Coupon to Complete Your Purchase';
        $body = 'Use coupon code ' . $coupon_code . ' to get 10% off on your cart. Click here to recover your cart: ' . $variables['cart_link'];
        $headers = array('Content-Type: text/html; charset=UTF-8');

        wp_mail( $to, $subject, $body, $headers );

        // Log email sent
        wabot_log_email( $to, $subject, $body );
    }

    // Send WhatsApp message
    if ( $cart->phone ) {
        $wabot = new Wabot_API();
        $template_id = $options['wabot_template_abandoned_cart'] ?? '';

        $wabot->send_message( $cart->phone, $template_id, $variables );
    }
}

// Email log function
function wabot_log_email( $to, $subject, $body ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wabot_email_log';

    $wpdb->insert( $table_name, array(
        'recipient' => $to,
        'subject'   => $subject,
        'body'      => $body,
        'timestamp' => current_time( 'mysql' ),
    ) );
}

// Create email log table
register_activation_hook( __FILE__, 'wabot_create_email_log_table' );
function wabot_create_email_log_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wabot_email_log';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        recipient varchar(100) NOT NULL,
        subject varchar(255) NOT NULL,
        body longtext NOT NULL,
        timestamp datetime NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}

// Admin Dashboard for Abandoned Carts
add_action( 'admin_menu', 'wabot_add_abandoned_carts_page' );
function wabot_add_abandoned_carts_page() {
    add_submenu_page(
        'wabot-settings',
        'Abandoned Carts',
        'Abandoned Carts',
        'manage_options',
        'wabot-abandoned-carts',
        'wabot_abandoned_carts_page'
    );
}

function wabot_abandoned_carts_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wabot_abandoned_carts';
    $results = $wpdb->get_results( "SELECT * FROM $table_name" );

    echo '<div class="wrap"><h1>Abandoned Carts</h1><table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>ID</th><th>Email</th><th>Phone</th><th>Timestamp</th><th>Actions</th></tr></thead><tbody>';

    foreach ( $results as $cart ) {
        echo '<tr>';
        echo '<td>' . esc_html( $cart->id ) . '</td>';
        echo '<td>' . esc_html( $cart->email ) . '</td>';
        echo '<td>' . esc_html( $cart->phone ) . '</td>';
        echo '<td>' . esc_html( $cart->timestamp ) . '</td>';
        echo '<td><a href="#">View</a> | <a href="#">Send Email</a></td>';
        echo '</tr>';
    }

    echo '</tbody></table></div>';
}



// Enqueue Scripts
add_action( 'wp_enqueue_scripts', 'wabot_enqueue_scripts' );
function wabot_enqueue_scripts() {
    wp_enqueue_script( 'wabot-modal', plugin_dir_url( __FILE__ ) . '../js/wabot-modal.js', array( 'jquery' ), '1.0', true );
    wp_enqueue_style( 'wabot-modal-style', plugin_dir_url( __FILE__ ) . '../css/wabot-modal.css' );

    // Enqueue intl-tel-input
    wp_enqueue_script( 'intl-tel-input', 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/intlTelInput.min.js', array( 'jquery' ), '17.0.8', true );
    wp_enqueue_style( 'intl-tel-input-css', 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/css/intlTelInput.min.css' );
    

    wp_localize_script( 'wabot-modal', 'wabot_ajax_object', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'wabot_nonce' ),
    ) );
}

add_action( 'wp_footer', 'wabot_modal_html' );
function wabot_modal_html() {
    if ( ! is_user_logged_in() ) {
        ?>
        <div id="wabot-email-modal">
            <div class="wabot-modal-content">
                <span id="wabot-modal-close">&times;</span>
                <p>Please enter your email and phone number to continue:</p>
                <input type="email" id="wabot-guest-email" name="wabot_guest_email" placeholder="Email">
                <input type="tel" id="wabot-guest-phone" name="wabot_guest_phone" placeholder="Phone Number">
                <button id="wabot-email-submit">Submit</button>
            </div>
        </div>
        <?php
    }
}


add_action( 'wp_ajax_nopriv_wabot_save_guest_info', 'wabot_save_guest_info' );
function wabot_save_guest_info() {
    check_ajax_referer( 'wabot_nonce', 'security' );

    session_start();

    $email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
    $phone = isset( $_POST['phone'] ) ? sanitize_text_field( $_POST['phone'] ) : '';

    if ( ! $email || ! $phone ) {
        wp_send_json_error( 'Email and phone number are required.' );
    }

    // Store the email and phone in session
    $_SESSION['wabot_guest_email'] = $email;
    $_SESSION['wabot_guest_phone'] = $phone;

    wp_send_json_success();
}



// Schedule Cron Event for Cleanup
if ( ! wp_next_scheduled( 'wabot_cleanup_abandoned_carts' ) ) {
    wp_schedule_event( time(), 'daily', 'wabot_cleanup_abandoned_carts' );
}

add_action( 'wabot_cleanup_abandoned_carts', 'wabot_delete_old_abandoned_carts' );
function wabot_delete_old_abandoned_carts() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wabot_abandoned_carts';

    $options = get_option( 'wabot_settings' );
    $cleanup_time = isset( $options['wabot_cleanup_time'] ) ? intval( $options['wabot_cleanup_time'] ) * 86400 : 30 * 86400; // Default 30 days

    $wpdb->query( $wpdb->prepare(
        "DELETE FROM $table_name WHERE TIMESTAMPDIFF(SECOND, timestamp, NOW()) > %d",
        $cleanup_time
    ) );
}