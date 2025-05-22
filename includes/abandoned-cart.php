<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Initialize guest data from cookies
add_action('init', 'wabot_init_guest_data', 5);
function wabot_init_guest_data() {
    if (!is_user_logged_in() && function_exists('WC') && WC()->session) {
        // Check if we have cookies but no session data
        if (
            (!WC()->session->get('wabot_guest_email') || !WC()->session->get('wabot_guest_phone')) && 
            (isset($_COOKIE['wabot_guest_email']) || isset($_COOKIE['wabot_guest_phone']))
        ) {
            if (isset($_COOKIE['wabot_guest_email'])) {
                $email = sanitize_email($_COOKIE['wabot_guest_email']);
                WC()->session->set('wabot_guest_email', $email);
            }
            
            if (isset($_COOKIE['wabot_guest_phone'])) {
                $phone = sanitize_text_field($_COOKIE['wabot_guest_phone']);
                WC()->session->set('wabot_guest_phone', $phone);
            }
            
            error_log("Initialized guest data from cookies into WC session");
        }
    }
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

// Hook into WooCommerce cart actions to capture cart data
add_action( 'woocommerce_add_to_cart', 'wabot_capture_cart_data', 20 );
add_action( 'woocommerce_cart_item_removed', 'wabot_capture_cart_data', 20 );
add_action( 'woocommerce_cart_item_restored', 'wabot_capture_cart_data', 20 );
add_action( 'woocommerce_cart_item_set_quantity', 'wabot_capture_cart_data', 20 );
add_action( 'woocommerce_after_checkout_form', 'wabot_capture_cart_data', 20 );
add_action( 'wp_footer', 'wabot_capture_cart_data', 99 ); // Last resort fallback on page load
add_action( 'woocommerce_checkout_update_order_review', 'wabot_capture_cart_data', 20 ); // When checkout fields are updated
add_action( 'woocommerce_after_calculate_totals', 'wabot_capture_cart_data', 20 ); // After cart totals are calculated

// Special handling for user login
add_action( 'wp_login', 'wabot_user_login_cart_update', 10, 2 );
function wabot_user_login_cart_update( $user_login, $user ) {
    // Wait for WooCommerce session to be established after login
    add_action( 'wp_loaded', function() use ( $user ) {
        // Make sure WooCommerce is loaded and initialized
        if (!function_exists('WC') || !WC()->cart || !is_callable(array(WC()->cart, 'is_empty'))) {
            error_log('WooCommerce not properly initialized during login');
            return;
        }
        
        $user_id = $user->ID;
        $email = $user->user_email;
        $phone = get_user_meta( $user_id, 'billing_phone', true );
        $session_id = WC()->session->get_customer_id();
        
        error_log("User login - User ID: $user_id, Email: $email, Session: $session_id");
        
        // Update any existing cart records with this session ID to belong to this user
        global $wpdb;
        $table_name = $wpdb->prefix . 'wabot_abandoned_carts';
        
        $wpdb->update(
            $table_name,
            array(
                'user_id' => $user_id,
                'email' => $email,
                'phone' => $phone,
                'timestamp' => current_time('mysql')
            ),
            array('session_id' => $session_id)
        );
        
        // Also run the normal cart capture
        wabot_capture_cart_data();
    }, 20);
}

function wabot_capture_cart_data() {
    // Log to error log for debugging
    error_log('wabot_capture_cart_data function called');
    
    // Make sure WooCommerce is loaded and initialized
    if (!function_exists('WC') || !WC()->cart || !is_callable(array(WC()->cart, 'is_empty'))) {
        error_log('WooCommerce not properly initialized');
        return;
    }
    
    if ( ! WC()->cart->is_empty() ) {
        $session_id = WC()->session->get_customer_id();
        
        // Get user info - prioritize logged in user data
        if ( is_user_logged_in() ) {
            $user_id = get_current_user_id();
            $user = wp_get_current_user();
            $email = $user->user_email;
            $phone = get_user_meta( $user_id, 'billing_phone', true );
            
            // If a guest had items in cart and then logged in, update their records
            global $wpdb;
            $table_name = $wpdb->prefix . 'wabot_abandoned_carts';
            $wpdb->update(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'email' => $email,
                    'phone' => $phone
                ),
                array('session_id' => $session_id)
            );
            
            // Debug log
            error_log("Logged in user - User ID: $user_id, Email: $email, Phone: $phone");
        } else {
            $user_id = null;
            
            // Use WooCommerce session
            $wc_session = WC()->session;
            $email = $wc_session->get('wabot_guest_email');
            $phone = $wc_session->get('wabot_guest_phone');
            
            // Fallback to cookies if session values are empty
            if (empty($email) && isset($_COOKIE['wabot_guest_email'])) {
                $email = sanitize_email($_COOKIE['wabot_guest_email']);
            }
            
            if (empty($phone) && isset($_COOKIE['wabot_guest_phone'])) {
                $phone = sanitize_text_field($_COOKIE['wabot_guest_phone']);
            }
            
            // Debug log
            error_log("Guest user - Email: $email, Phone: $phone");
            error_log("WC Session ID: " . $session_id);
        }

        // Proceed only if email and phone are available
        if ( ! $email || ! $phone ) {
            error_log("Email or phone missing, abandoning cart capture");
            return;
        }

        $cart_contents = WC()->cart->get_cart_contents();

        global $wpdb;
        $table_name = $wpdb->prefix . 'wabot_abandoned_carts';
        
        // Check if record with this session_id already exists
        $existing_record = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE session_id = %s",
            $session_id
        ));
        
        if ($existing_record) {
            // Update existing record
            $wpdb->update(
                $table_name,
                array(
                    'cart_contents' => maybe_serialize($cart_contents),
                    'user_id'       => $user_id,
                    'email'         => $email,
                    'phone'         => $phone,
                    'timestamp'     => current_time('mysql'),
                ),
                array('session_id' => $session_id)
            );
            error_log("Updated existing cart data for session: $session_id");
        } else {
            // Insert new record
            $wpdb->insert(
                $table_name,
                array(
                    'session_id'    => $session_id,
                    'cart_contents' => maybe_serialize($cart_contents),
                    'user_id'       => $user_id,
                    'email'         => $email,
                    'phone'         => $phone,
                    'timestamp'     => current_time('mysql'),
                )
            );
            error_log("Inserted new cart data for session: $session_id");
        }
    } else {
        error_log("Cart is empty, nothing to capture");
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
    $templates_options = get_option( 'wabot_settings_templates' );

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
        $subject = 'We Miss You! Here\'s a Coupon to Complete Your Purchase';
        $body = 'Use coupon code ' . $coupon_code . ' to get 10% off on your cart. Click here to recover your cart: ' . $variables['cart_link'];
        $headers = array('Content-Type: text/html; charset=UTF-8');

        wp_mail( $to, $subject, $body, $headers );

        // Log email sent
        wabot_log_email( $to, $subject, $body );
    }

    // Send WhatsApp message
    if ( $cart->phone ) {
        $template_id = $options['wabot_template_abandoned_cart'] ?? '';
        $template_enabled = isset( $templates_options['wabot_template_abandoned_cart_enabled'] ) ? $templates_options['wabot_template_abandoned_cart_enabled'] : '1';
        
        // Only send WhatsApp if template is enabled
        if ( $template_id && $template_enabled == '1' ) {
            $wabot = new Wabot_API();
            $wabot->send_message( $cart->phone, $template_id, $variables );
        }
    }
}

// Email log function
function wabot_log_email( $to, $subject, $body ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wabot_email_log';

    $wpdb->insert( $table_name, array(
        'email_to' => $to,
        'template_key' => 'abandoned_cart',
        'subject' => $subject,
        'variables' => '',
        'status' => 'sent',
        'sent_at' => current_time('mysql'),
        'error_message' => ''
    ) );
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
    
    // Enqueue admin scripts and styles
    wp_enqueue_script('jquery-ui-datepicker');
    wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.7.0', true);
    wp_enqueue_style('jquery-ui', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
    
    // Add custom admin styles
    echo '<style>
        .wabot-tabs { margin-top: 20px; }
        .wabot-tab { display: inline-block; padding: 10px 15px; background: #f1f1f1; cursor: pointer; margin-right: 5px; }
        .wabot-tab.active { background: #fff; border: 1px solid #ccc; border-bottom: none; }
        .wabot-tab-content { display: none; padding: 20px; background: #fff; border: 1px solid #ccc; }
        .wabot-tab-content.active { display: block; }
        .wabot-filters { margin: 20px 0; padding: 15px; background: #f9f9f9; border: 1px solid #e5e5e5; }
        .wabot-filters input, .wabot-filters select { margin-right: 10px; }
        .wabot-dashboard-widgets { display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 20px; }
        .wabot-widget { flex: 1 0 200px; background: #fff; border: 1px solid #e5e5e5; padding: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .wabot-widget h3 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .wabot-widget .number { font-size: 24px; font-weight: bold; color: #0073aa; }
        .wabot-charts-container { display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 20px; }
        .wabot-chart-container { flex: 1; min-width: 300px; background: #fff; padding: 15px; border: 1px solid #e5e5e5; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .wabot-chart-container canvas { width: 100% !important; height: auto !important; max-height: 300px; image-rendering: crisp-edges; }
        .wabot-cart-detail { background: #fff; padding: 20px; border: 1px solid #e5e5e5; margin-top: 20px; }
        .wabot-cart-products { margin-top: 15px; }
        .wabot-cart-products table { width: 100%; border-collapse: collapse; }
        .wabot-cart-products th, .wabot-cart-products td { padding: 8px; border: 1px solid #e5e5e5; }
        .wabot-period-selector { margin-bottom: 20px; }
        .wabot-period-selector button { margin-right: 5px; }
    </style>';
    
    // Get current tab
    $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'dashboard';
    
    // Get filters
    $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
    $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
    $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    
    // Get cart ID for detail view
    $cart_id = isset($_GET['cart_id']) ? intval($_GET['cart_id']) : 0;
    
    echo '<div class="wrap">';
    echo '<h1>Abandoned Carts</h1>';
    
    // Tabs
    echo '<div class="wabot-tabs">';
    echo '<div class="wabot-tab ' . ($current_tab === 'dashboard' ? 'active' : '') . '" data-tab="dashboard">Dashboard</div>';
    echo '<div class="wabot-tab ' . ($current_tab === 'carts' ? 'active' : '') . '" data-tab="carts">Abandoned Carts</div>';
    echo '<div class="wabot-tab ' . ($current_tab === 'recovered' ? 'active' : '') . '" data-tab="recovered">Recovered Carts</div>';
    echo '</div>';
    
    // Dashboard Tab
    echo '<div class="wabot-tab-content ' . ($current_tab === 'dashboard' ? 'active' : '') . '" id="dashboard">';
    
    // Period selector
    echo '<div class="wabot-period-selector">';
    echo '<button class="button" data-period="7">Last 7 Days</button>';
    echo '<button class="button" data-period="30">Last 30 Days</button>';
    echo '<button class="button" data-period="90">Last 90 Days</button>';
    echo '<span style="margin-left: 15px;">Custom: </span>';
    echo '<input type="text" id="dash-date-from" placeholder="From Date" style="width: 120px;" value="' . date('Y-m-d', strtotime('-30 days')) . '">';
    echo '<input type="text" id="dash-date-to" placeholder="To Date" style="width: 120px;" value="' . date('Y-m-d') . '">';
    echo '<button class="button" id="apply-dash-dates">Apply</button>';
    echo '</div>';
    
    // Dashboard Widgets
    echo '<div class="wabot-dashboard-widgets">';
    
    // Total Abandoned Carts
    $total_carts = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    echo '<div class="wabot-widget">';
    echo '<h3>Total Abandoned Carts</h3>';
    echo '<div class="number">' . number_format($total_carts) . '</div>';
    echo '</div>';
    
    // Total Value
    $total_value = calculate_abandoned_cart_value();
    echo '<div class="wabot-widget">';
    echo '<h3>Total Abandoned Value</h3>';
    echo '<div class="number">' . wc_price($total_value) . '</div>';
    echo '</div>';
    
    // Recovery Rate
    $recovery_rate = calculate_recovery_rate();
    echo '<div class="wabot-widget">';
    echo '<h3>Recovery Rate</h3>';
    echo '<div class="number">' . number_format($recovery_rate, 1) . '%</div>';
    echo '</div>';
    
    // Average Cart Value
    $avg_value = $total_carts > 0 ? $total_value / $total_carts : 0;
    echo '<div class="wabot-widget">';
    echo '<h3>Average Cart Value</h3>';
    echo '<div class="number">' . wc_price($avg_value) . '</div>';
    echo '</div>';
    
    echo '</div>'; // End dashboard widgets
    
    // Charts side by side
    echo '<div class="wabot-charts-container" style="display: flex; gap: 20px; flex-wrap: wrap; width: 100%;">';
    
    // Abandoned Carts Chart
    echo '<div class="wabot-chart-container" style="flex: 1 1 45%; min-width: 300px;">';
    echo '<h2>Abandoned Carts Over Time</h2>';
    echo '<div style="position: relative; width: 100%;">';
    echo '<canvas id="abandoned-carts-chart"></canvas>';
    echo '</div>';
    echo '</div>';
    
    // Recovery Statistics Chart
    echo '<div class="wabot-chart-container" style="flex: 1 1 45%; min-width: 300px;">';
    echo '<h2>Recovery Statistics</h2>';
    echo '<div style="position: relative; width: 100%;">';
    echo '<canvas id="recovery-chart"></canvas>';
    echo '</div>';
    echo '</div>';
    
    echo '</div>'; // End charts container
    
    echo '</div>'; // End dashboard tab
    
    // Carts Tab
    echo '<div class="wabot-tab-content ' . ($current_tab === 'carts' ? 'active' : '') . '" id="carts">';
    
    if ($cart_id > 0) {
        // Detail view for a specific cart
        $cart = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $cart_id));
        
        if ($cart) {
            echo '<div class="wabot-cart-detail">';
            echo '<h2>Cart Details</h2>';
            echo '<p><a href="' . admin_url('admin.php?page=wabot-abandoned-carts&tab=carts') . '" class="button">&laquo; Back to List</a></p>';
            
            echo '<table class="form-table">';
            echo '<tr><th>ID</th><td>' . esc_html($cart->id) . '</td></tr>';
            echo '<tr><th>Email</th><td>' . esc_html($cart->email) . '</td></tr>';
            echo '<tr><th>Phone</th><td>' . esc_html($cart->phone) . '</td></tr>';
            echo '<tr><th>Date</th><td>' . esc_html($cart->timestamp) . '</td></tr>';
            echo '<tr><th>Session ID</th><td>' . esc_html($cart->session_id) . '</td></tr>';
            
            if ($cart->user_id) {
                $user = get_user_by('ID', $cart->user_id);
                echo '<tr><th>User</th><td><a href="' . admin_url('user-edit.php?user_id=' . $cart->user_id) . '">' . esc_html($user->display_name) . '</a></td></tr>';
            }
            
            echo '</table>';
            
            // Cart products
            $cart_contents = maybe_unserialize($cart->cart_contents);
            
            if (!empty($cart_contents)) {
                echo '<div class="wabot-cart-products">';
                echo '<h3>Cart Items</h3>';
                echo '<table>';
                echo '<thead><tr><th>Product</th><th>Quantity</th><th>Price</th><th>Total</th></tr></thead>';
                echo '<tbody>';
                
                $cart_total = 0;
                
                foreach ($cart_contents as $cart_item) {
                    $product_id = $cart_item['product_id'];
                    $product = wc_get_product($product_id);
                    
                    if ($product) {
                        $quantity = $cart_item['quantity'];
                        $price = $product->get_price();
                        $total = $price * $quantity;
                        $cart_total += $total;
                        
                        echo '<tr>';
                        echo '<td><a href="' . get_edit_post_link($product_id) . '">' . esc_html($product->get_name()) . '</a></td>';
                        echo '<td>' . esc_html($quantity) . '</td>';
                        echo '<td>' . wc_price($price) . '</td>';
                        echo '<td>' . wc_price($total) . '</td>';
                        echo '</tr>';
                    }
                }
                
                echo '</tbody>';
                echo '<tfoot><tr><th colspan="3">Total</th><th>' . wc_price($cart_total) . '</th></tr></tfoot>';
                echo '</table>';
                echo '</div>';
            }
            
            // Recovery actions
            echo '<div class="wabot-recovery-actions" style="margin-top: 20px;">';
            echo '<h3>Recovery Actions</h3>';
            echo '<a href="' . admin_url('admin.php?page=wabot-abandoned-carts&action=send_email&cart_id=' . $cart->id) . '" class="button button-primary">Send Recovery Email</a> ';
            echo '<a href="' . admin_url('admin.php?page=wabot-abandoned-carts&action=send_whatsapp&cart_id=' . $cart->id) . '" class="button button-primary">Send WhatsApp Message</a>';
            echo '</div>';
            
            echo '</div>'; // End cart detail
        } else {
            echo '<div class="notice notice-error"><p>Cart not found.</p></div>';
        }
    } else {
        // Filters
        echo '<div class="wabot-filters">';
        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="wabot-abandoned-carts">';
        echo '<input type="hidden" name="tab" value="carts">';
        echo '<input type="text" name="date_from" id="date-from" placeholder="From Date" value="' . esc_attr($date_from) . '">';
        echo '<input type="text" name="date_to" id="date-to" placeholder="To Date" value="' . esc_attr($date_to) . '">';
        echo '<select name="status">';
        echo '<option value="">All Status</option>';
        echo '<option value="new" ' . selected($status, 'new', false) . '>New</option>';
        echo '<option value="recovered" ' . selected($status, 'recovered', false) . '>Recovered</option>';
        echo '<option value="sent" ' . selected($status, 'sent', false) . '>Message Sent</option>';
        echo '</select>';
        echo '<input type="submit" class="button" value="Filter">';
        echo '</form>';
        echo '</div>';
        
        // Build query with filters
        $query = "SELECT * FROM $table_name WHERE 1=1";
        $query_params = array();
        
        if (!empty($date_from)) {
            $query .= " AND timestamp >= %s";
            $query_params[] = $date_from . ' 00:00:00';
        }
        
        if (!empty($date_to)) {
            $query .= " AND timestamp <= %s";
            $query_params[] = $date_to . ' 23:59:59';
        }
        
        if (!empty($status)) {
            if ($status === 'new') {
                $query .= " AND NOT EXISTS (SELECT 1 FROM {$wpdb->prefix}postmeta WHERE meta_key = '_wabot_recovery_sent' AND meta_value = 'yes' AND post_id = id)";
            } elseif ($status === 'sent') {
                $query .= " AND EXISTS (SELECT 1 FROM {$wpdb->prefix}postmeta WHERE meta_key = '_wabot_recovery_sent' AND meta_value = 'yes' AND post_id = id)";
            } elseif ($status === 'recovered') {
                // This would need a way to track recovered carts
                $query .= " AND EXISTS (SELECT 1 FROM {$wpdb->prefix}postmeta WHERE meta_key = '_wabot_cart_recovered' AND meta_value = 'yes' AND post_id = id)";
            }
        }
        
        $query .= " ORDER BY timestamp DESC";
        
        // Prepare the query if we have parameters
        if (!empty($query_params)) {
            $results = $wpdb->get_results($wpdb->prepare($query, $query_params));
        } else {
            $results = $wpdb->get_results($query);
        }
        
        // Display results
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>
            <th>ID</th>
            <th>Customer</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Date</th>
            <th>Products</th>
            <th>Cart Value</th>
            <th>Status</th>
            <th>Actions</th>
        </tr></thead><tbody>';
        
        foreach ($results as $cart) {
            $cart_contents = maybe_unserialize($cart->cart_contents);
            $cart_value = 0;
            $product_count = 0;
            
            if (is_array($cart_contents)) {
                foreach ($cart_contents as $item) {
                    $product_count++;
                    $product = wc_get_product($item['product_id']);
                    if ($product) {
                        $cart_value += $product->get_price() * $item['quantity'];
                    }
                }
            }
            
            $status = get_post_meta($cart->id, '_wabot_recovery_sent', true) === 'yes' ? 'Message Sent' : 'New';
            
            echo '<tr>';
            echo '<td>' . esc_html($cart->id) . '</td>';
            
            // Customer name from user ID or email
            if ($cart->user_id) {
                $user = get_user_by('ID', $cart->user_id);
                echo '<td>' . ($user ? esc_html($user->display_name) : 'User #' . esc_html($cart->user_id)) . '</td>';
            } else {
                echo '<td>Guest</td>';
            }
            
            echo '<td>' . esc_html($cart->email) . '</td>';
            echo '<td>' . esc_html($cart->phone) . '</td>';
            echo '<td>' . esc_html($cart->timestamp) . '</td>';
            echo '<td>' . esc_html($product_count) . ' items</td>';
            echo '<td>' . wc_price($cart_value) . '</td>';
            echo '<td>' . esc_html($status) . '</td>';
            echo '<td>
                <a href="' . admin_url('admin.php?page=wabot-abandoned-carts&tab=carts&cart_id=' . $cart->id) . '">View</a> | 
                <a href="' . admin_url('admin.php?page=wabot-abandoned-carts&action=send_email&cart_id=' . $cart->id) . '">Email</a> | 
                <a href="' . admin_url('admin.php?page=wabot-abandoned-carts&action=send_whatsapp&cart_id=' . $cart->id) . '">WhatsApp</a>
            </td>';
            echo '</tr>';
        }
        
        if (count($results) === 0) {
            echo '<tr><td colspan="9">No abandoned carts found.</td></tr>';
        }
        
        echo '</tbody></table>';
    }
    
    echo '</div>'; // End carts tab
    
    // Recovered Carts Tab
    echo '<div class="wabot-tab-content ' . ($current_tab === 'recovered' ? 'active' : '') . '" id="recovered">';
    
    // Filters for recovered carts
    echo '<div class="wabot-filters">';
    echo '<form method="get">';
    echo '<input type="hidden" name="page" value="wabot-abandoned-carts">';
    echo '<input type="hidden" name="tab" value="recovered">';
    echo '<input type="text" name="date_from" id="recovered-date-from" placeholder="From Date" value="' . esc_attr($date_from) . '">';
    echo '<input type="text" name="date_to" id="recovered-date-to" placeholder="To Date" value="' . esc_attr($date_to) . '">';
    echo '<input type="submit" class="button" value="Filter">';
    echo '</form>';
    echo '</div>';
    
    // Build query for recovered carts
    $query = "SELECT ac.* FROM $table_name ac
              INNER JOIN {$wpdb->prefix}postmeta pm ON pm.post_id = ac.id
              WHERE pm.meta_key = '_wabot_cart_recovered' AND pm.meta_value = 'yes'";
    $query_params = array();
    
    if (!empty($date_from)) {
        $query .= " AND ac.timestamp >= %s";
        $query_params[] = $date_from . ' 00:00:00';
    }
    
    if (!empty($date_to)) {
        $query .= " AND ac.timestamp <= %s";
        $query_params[] = $date_to . ' 23:59:59';
    }
    
    $query .= " ORDER BY ac.timestamp DESC";
    
    // Prepare the query if we have parameters
    if (!empty($query_params)) {
        $recovered_carts = $wpdb->get_results($wpdb->prepare($query, $query_params));
    } else {
        $recovered_carts = $wpdb->get_results($query);
    }
    
    // Display recovered carts
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr>
        <th>ID</th>
        <th>Customer</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Abandoned Date</th>
        <th>Recovered Date</th>
        <th>Products</th>
        <th>Cart Value</th>
        <th>Recovery Method</th>
        <th>Actions</th>
    </tr></thead><tbody>';
    
    if (count($recovered_carts) > 0) {
        foreach ($recovered_carts as $cart) {
            $cart_contents = maybe_unserialize($cart->cart_contents);
            $cart_value = 0;
            $product_count = 0;
            
            if (is_array($cart_contents)) {
                foreach ($cart_contents as $item) {
                    $product_count++;
                    $product = wc_get_product($item['product_id']);
                    if ($product) {
                        $cart_value += $product->get_price() * $item['quantity'];
                    }
                }
            }
            
            // Get recovery date and method
            $recovery_date = get_post_meta($cart->id, '_wabot_recovery_date', true);
            $email_sent = get_post_meta($cart->id, '_wabot_recovery_email_sent', true);
            $whatsapp_sent = get_post_meta($cart->id, '_wabot_recovery_whatsapp_sent', true);
            
            $recovery_method = '';
            if ($email_sent && $whatsapp_sent) {
                $recovery_method = 'Email & WhatsApp';
            } elseif ($email_sent) {
                $recovery_method = 'Email';
            } elseif ($whatsapp_sent) {
                $recovery_method = 'WhatsApp';
            } else {
                $recovery_method = 'Direct Visit';
            }
            
            echo '<tr>';
            echo '<td>' . esc_html($cart->id) . '</td>';
            
            // Customer name from user ID or email
            if ($cart->user_id) {
                $user = get_user_by('ID', $cart->user_id);
                echo '<td>' . ($user ? esc_html($user->display_name) : 'User #' . esc_html($cart->user_id)) . '</td>';
            } else {
                echo '<td>Guest</td>';
            }
            
            echo '<td>' . esc_html($cart->email) . '</td>';
            echo '<td>' . esc_html($cart->phone) . '</td>';
            echo '<td>' . esc_html($cart->timestamp) . '</td>';
            echo '<td>' . esc_html($recovery_date) . '</td>';
            echo '<td>' . esc_html($product_count) . ' items</td>';
            echo '<td>' . wc_price($cart_value) . '</td>';
            echo '<td>' . esc_html($recovery_method) . '</td>';
            echo '<td><a href="' . admin_url('admin.php?page=wabot-abandoned-carts&tab=carts&cart_id=' . $cart->id) . '">View Details</a></td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="10">No recovered carts found.</td></tr>';
    }
    
    echo '</tbody></table>';
    
    // Add some stats at the bottom
    $total_recovered = count($recovered_carts);
    $total_recovered_value = 0;
    
    foreach ($recovered_carts as $cart) {
        $cart_contents = maybe_unserialize($cart->cart_contents);
        if (is_array($cart_contents)) {
            foreach ($cart_contents as $item) {
                $product = wc_get_product($item['product_id']);
                if ($product) {
                    $total_recovered_value += $product->get_price() * $item['quantity'];
                }
            }
        }
    }
    
    echo '<div class="wabot-recovery-stats" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #e5e5e5;">';
    echo '<h3>Recovery Statistics</h3>';
    echo '<p><strong>Total Recovered Carts:</strong> ' . $total_recovered . '</p>';
    echo '<p><strong>Total Recovered Value:</strong> ' . wc_price($total_recovered_value) . '</p>';
    echo '</div>';
    
    echo '</div>'; // End recovered tab
    
    echo '</div>'; // End wrap
    
    // Add JavaScript for tabs and datepickers
    ?>
    <script>
    jQuery(document).ready(function($) {
        // Tabs
        $('.wabot-tab').on('click', function() {
            var tab = $(this).data('tab');
            $('.wabot-tab').removeClass('active');
            $(this).addClass('active');
            $('.wabot-tab-content').removeClass('active');
            $('#' + tab).addClass('active');
            
            // Update URL without reloading
            var url = new URL(window.location);
            url.searchParams.set('tab', tab);
            window.history.pushState({}, '', url);
        });
        
        // Datepickers
        $('#date-from, #date-to, #dash-date-from, #dash-date-to, #recovered-date-from, #recovered-date-to').datepicker({
            dateFormat: 'yy-mm-dd',
            changeMonth: true,
            changeYear: true
        });
        
        // Dashboard period selectors
        $('.wabot-period-selector button[data-period]').on('click', function() {
            var days = $(this).data('period');
            var toDate = new Date();
            var fromDate = new Date();
            fromDate.setDate(toDate.getDate() - days);
            
            $('#dash-date-from').val(fromDate.toISOString().split('T')[0]);
            $('#dash-date-to').val(toDate.toISOString().split('T')[0]);
            
            loadChartData(fromDate.toISOString().split('T')[0], toDate.toISOString().split('T')[0]);
            return false;
        });
        
        $('#apply-dash-dates').on('click', function() {
            loadChartData($('#dash-date-from').val(), $('#dash-date-to').val());
            return false;
        });
        
        // Initialize charts
        var cartsChart = null;
        var recoveryChart = null;
        
        function initCharts() {
            // Get default date range (last 30 days)
            var toDate = new Date();
            var fromDate = new Date();
            fromDate.setDate(toDate.getDate() - 30);
            
            var fromStr = fromDate.toISOString().split('T')[0];
            var toStr = toDate.toISOString().split('T')[0];
            
            loadChartData(fromStr, toStr);
        }
        
        function loadChartData(fromDate, toDate) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wabot_get_chart_data',
                    from_date: fromDate,
                    to_date: toDate,
                    security: '<?php echo wp_create_nonce('wabot_admin_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success && response.data) {
                        updateCharts(response.data);
                    }
                }
            });
        }
        
        function updateCharts(data) {
            var ctx1 = document.getElementById('abandoned-carts-chart').getContext('2d');
            var ctx2 = document.getElementById('recovery-chart').getContext('2d');
            
            // Set device pixel ratio for sharper rendering
            var dpr = window.devicePixelRatio || 1;
            var rect1 = ctx1.canvas.getBoundingClientRect();
            var rect2 = ctx2.canvas.getBoundingClientRect();
            
            // Adjust canvas for high DPI displays
            ctx1.canvas.width = rect1.width * dpr;
            ctx1.canvas.height = 300 * dpr;
            ctx1.scale(dpr, dpr);
            ctx1.canvas.style.width = rect1.width + 'px';
            ctx1.canvas.style.height = '300px';
            
            ctx2.canvas.width = rect2.width * dpr;
            ctx2.canvas.height = 300 * dpr;
            ctx2.scale(dpr, dpr);
            ctx2.canvas.style.width = rect2.width + 'px';
            ctx2.canvas.style.height = '300px';
            
            // Destroy existing charts if they exist
            if (cartsChart) cartsChart.destroy();
            if (recoveryChart) recoveryChart.destroy();
            
            // Create new charts
            cartsChart = new Chart(ctx1, {
                type: 'line',
                data: {
                    labels: data.dates,
                    datasets: [{
                        label: 'Abandoned Carts',
                        data: data.carts,
                        borderColor: 'rgba(54, 162, 235, 1)',
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    aspectRatio: 2,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                maxTicksLimit: 5
                            }
                        },
                        x: {
                            ticks: {
                                maxTicksLimit: 10,
                                maxRotation: 0
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                boxWidth: 10,
                                padding: 5,
                                font: {
                                    size: 10
                                }
                            }
                        }
                    }
                }
            });
            
            recoveryChart = new Chart(ctx2, {
                type: 'bar',
                data: {
                    labels: ['Abandoned', 'Recovered', 'Recovery Rate'],
                    datasets: [{
                        label: 'Recovery Statistics',
                        data: [data.total_abandoned, data.total_recovered, data.recovery_rate],
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.2)',
                            'rgba(75, 192, 192, 0.2)',
                            'rgba(255, 206, 86, 0.2)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(75, 192, 192, 1)',
                            'rgba(255, 206, 86, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    aspectRatio: 2,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                maxTicksLimit: 5
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                boxWidth: 10,
                                padding: 5,
                                font: {
                                    size: 10
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Initialize charts on page load
        if ($('#abandoned-carts-chart').length) {
            initCharts();
        }
    });
    </script>
    <?php
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
    // Check if guest modal is enabled
    $options = get_option('wabot_settings_other', array());
    $is_modal_enabled = isset($options['wabot_guest_modal_enabled']) ? $options['wabot_guest_modal_enabled'] : '1';

    if (!is_user_logged_in() && $is_modal_enabled === '1') {
        ?>
        <div id="wabot-email-modal" style="display: none;">
            <div class="wabot-modal-content">
                <button id="wabot-modal-close" aria-label="Close">&times;</button>
                
                <h2>Stay Connected</h2>
                <p>Get updates on our latest offers and events</p>
                
                <div class="wabot-form-group">
                    <label for="wabot-guest-email">Email Address</label>
                    <input type="email" id="wabot-guest-email" name="wabot_guest_email" placeholder="Your email address" autocomplete="email">
                </div>
                
                <div class="wabot-form-group">
                    <label for="wabot-guest-phone">Phone Number</label>
                    <input type="tel" id="wabot-guest-phone" name="wabot_guest_phone" autocomplete="tel">
                </div>
                
                <button type="submit" id="wabot-email-submit">Subscribe</button>
                
                <div class="wabot-privacy-notice">
                    By submitting, you agree to our privacy policy and terms of service.
                </div>
            </div>
        </div>
        <?php
    }
}


add_action( 'wp_ajax_nopriv_wabot_save_guest_info', 'wabot_save_guest_info' );
function wabot_save_guest_info() {
    check_ajax_referer( 'wabot_nonce', 'security' );

    $email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
    $phone = isset( $_POST['phone'] ) ? sanitize_text_field( $_POST['phone'] ) : '';

    if ( ! $email || ! $phone ) {
        wp_send_json_error( 'Email and phone number are required.' );
    }

    // Store in WooCommerce session if available
    if ( function_exists( 'WC' ) && WC()->session ) {
        WC()->session->set( 'wabot_guest_email', $email );
        WC()->session->set( 'wabot_guest_phone', $phone );
        error_log("Guest info saved to WC session: $email, $phone");
    }

    // Also set a cookie as fallback
    setcookie('wabot_guest_email', $email, time() + (30 * DAY_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN);
    setcookie('wabot_guest_phone', $phone, time() + (30 * DAY_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN);

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

// Helper function to calculate total abandoned cart value
function calculate_abandoned_cart_value() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wabot_abandoned_carts';
    $carts = $wpdb->get_results("SELECT cart_contents FROM $table_name");
    $total_value = 0;
    
    foreach ($carts as $cart) {
        $cart_contents = maybe_unserialize($cart->cart_contents);
        if (is_array($cart_contents)) {
            foreach ($cart_contents as $item) {
                $product = wc_get_product($item['product_id']);
                if ($product) {
                    $total_value += $product->get_price() * $item['quantity'];
                }
            }
        }
    }
    
    return $total_value;
}

// Helper function to calculate recovery rate
function calculate_recovery_rate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wabot_abandoned_carts';
    $total_carts = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    
    // Get recovered carts count
    $recovered_carts = $wpdb->get_var("
        SELECT COUNT(*) FROM {$wpdb->prefix}postmeta 
        WHERE meta_key = '_wabot_cart_recovered' 
        AND meta_value = 'yes'
    ");
    
    return $total_carts > 0 ? ($recovered_carts / $total_carts) * 100 : 0;
}

// AJAX handler for chart data
add_action('wp_ajax_wabot_get_chart_data', 'wabot_get_chart_data');
function wabot_get_chart_data() {
    check_ajax_referer('wabot_admin_nonce', 'security');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    $from_date = isset($_POST['from_date']) ? sanitize_text_field($_POST['from_date']) : date('Y-m-d', strtotime('-30 days'));
    $to_date = isset($_POST['to_date']) ? sanitize_text_field($_POST['to_date']) : date('Y-m-d');
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'wabot_abandoned_carts';
    
    // Get all dates in range
    $dates = array();
    $carts_by_date = array();
    
    $current = strtotime($from_date);
    $end = strtotime($to_date);
    
    while ($current <= $end) {
        $date = date('Y-m-d', $current);
        $dates[] = $date;
        $carts_by_date[$date] = 0;
        $current = strtotime('+1 day', $current);
    }
    
    // Get abandoned cart counts by date
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT DATE(timestamp) as date, COUNT(*) as count FROM $table_name 
         WHERE timestamp >= %s AND timestamp <= %s 
         GROUP BY DATE(timestamp) 
         ORDER BY date",
        $from_date . ' 00:00:00',
        $to_date . ' 23:59:59'
    ));
    
    foreach ($results as $row) {
        $carts_by_date[$row->date] = intval($row->count);
    }
    
    // Get cart counts in order
    $carts = array_values($carts_by_date);
    
    // Total counts and recovery rate
    $total_abandoned = array_sum($carts);
    $total_recovered = $wpdb->get_var("
        SELECT COUNT(*) FROM {$wpdb->prefix}postmeta 
        WHERE meta_key = '_wabot_cart_recovered' 
        AND meta_value = 'yes'
    ");
    
    $recovery_rate = $total_abandoned > 0 ? ($total_recovered / $total_abandoned) * 100 : 0;
    
    wp_send_json_success(array(
        'dates' => $dates,
        'carts' => $carts,
        'total_abandoned' => $total_abandoned,
        'total_recovered' => $total_recovered,
        'recovery_rate' => $recovery_rate
    ));
}

// Action handlers for abandoned cart recovery messages
add_action('admin_init', 'wabot_handle_recovery_actions');
function wabot_handle_recovery_actions() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Check if we're on the abandoned carts page with an action
    if (
        isset($_GET['page']) && $_GET['page'] === 'wabot-abandoned-carts' && 
        isset($_GET['action']) && isset($_GET['cart_id'])
    ) {
        $action = sanitize_text_field($_GET['action']);
        $cart_id = intval($_GET['cart_id']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'wabot_abandoned_carts';
        $cart = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $cart_id));
        
        if (!$cart) {
            wp_die('Cart not found.');
        }
        
        if ($action === 'send_email') {
            // Send recovery email
            $result = wabot_send_recovery_email($cart);
            
            if ($result) {
                // Update sent status
                update_post_meta($cart_id, '_wabot_recovery_sent', 'yes');
                update_post_meta($cart_id, '_wabot_recovery_email_sent', current_time('mysql'));
                
                // Redirect with success message
                wp_redirect(add_query_arg('message', 'email_sent', admin_url('admin.php?page=wabot-abandoned-carts&tab=carts')));
                exit;
            } else {
                // Redirect with error message
                wp_redirect(add_query_arg('error', 'email_failed', admin_url('admin.php?page=wabot-abandoned-carts&tab=carts')));
                exit;
            }
        } elseif ($action === 'send_whatsapp') {
            // Send WhatsApp message
            $result = wabot_send_recovery_whatsapp($cart);
            
            if ($result) {
                // Update sent status
                update_post_meta($cart_id, '_wabot_recovery_sent', 'yes');
                update_post_meta($cart_id, '_wabot_recovery_whatsapp_sent', current_time('mysql'));
                
                // Redirect with success message
                wp_redirect(add_query_arg('message', 'whatsapp_sent', admin_url('admin.php?page=wabot-abandoned-carts&tab=carts')));
                exit;
            } else {
                // Redirect with error message
                wp_redirect(add_query_arg('error', 'whatsapp_failed', admin_url('admin.php?page=wabot-abandoned-carts&tab=carts')));
                exit;
            }
        }
    }
    
    // Display admin notices for messages
    if (isset($_GET['page']) && $_GET['page'] === 'wabot-abandoned-carts') {
        add_action('admin_notices', 'wabot_display_recovery_notices');
    }
}

// Display notices for recovery actions
function wabot_display_recovery_notices() {
    if (isset($_GET['message'])) {
        $message = sanitize_text_field($_GET['message']);
        
        if ($message === 'email_sent') {
            echo '<div class="notice notice-success is-dismissible"><p>Recovery email sent successfully.</p></div>';
        } elseif ($message === 'whatsapp_sent') {
            echo '<div class="notice notice-success is-dismissible"><p>Recovery WhatsApp message sent successfully.</p></div>';
        }
    }
    
    if (isset($_GET['error'])) {
        $error = sanitize_text_field($_GET['error']);
        
        if ($error === 'email_failed') {
            echo '<div class="notice notice-error is-dismissible"><p>Failed to send recovery email. Please try again.</p></div>';
        } elseif ($error === 'whatsapp_failed') {
            echo '<div class="notice notice-error is-dismissible"><p>Failed to send WhatsApp message. Please check your settings.</p></div>';
        }
    }
}

// Send a recovery email to a specific abandoned cart
function wabot_send_recovery_email($cart) {
    // Generate coupon code for this recovery
    $coupon_code = 'RECOVER-' . strtoupper(wp_generate_password(6, false));
    
    // Create a new coupon
    $coupon = new WC_Coupon();
    $coupon->set_code($coupon_code);
    $coupon->set_discount_type('percent'); // or 'fixed_cart'
    $coupon->set_amount(10); // 10% discount
    $coupon->set_individual_use(true);
    $coupon->set_usage_limit(1);
    $coupon->set_date_expires(strtotime('+7 days'));
    $coupon->save();
    
    // Only proceed if we have an email
    if (!$cart->email) {
        return false;
    }
    
    // Format cart content for email
    $cart_contents = maybe_unserialize($cart->cart_contents);
    $cart_items_html = '';
    $cart_total = 0;
    
    if (is_array($cart_contents)) {
        $cart_items_html .= '<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">';
        $cart_items_html .= '<tr style="background-color: #f8f8f8;"><th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Product</th><th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Quantity</th><th style="padding: 8px; border: 1px solid #ddd; text-align: right;">Price</th></tr>';
        
        foreach ($cart_contents as $item) {
            $product = wc_get_product($item['product_id']);
            if ($product) {
                $price = $product->get_price() * $item['quantity'];
                $cart_total += $price;
                
                $cart_items_html .= '<tr>';
                $cart_items_html .= '<td style="padding: 8px; border: 1px solid #ddd;">' . esc_html($product->get_name()) . '</td>';
                $cart_items_html .= '<td style="padding: 8px; border: 1px solid #ddd;">' . esc_html($item['quantity']) . '</td>';
                $cart_items_html .= '<td style="padding: 8px; border: 1px solid #ddd; text-align: right;">' . wc_price($price) . '</td>';
                $cart_items_html .= '</tr>';
            }
        }
        
        $cart_items_html .= '<tr style="background-color: #f8f8f8;"><td colspan="2" style="padding: 8px; border: 1px solid #ddd; text-align: right;"><strong>Total:</strong></td><td style="padding: 8px; border: 1px solid #ddd; text-align: right;"><strong>' . wc_price($cart_total) . '</strong></td></tr>';
        $cart_items_html .= '</table>';
    }
    
    // Build recovery URL with the session ID
    $recovery_url = add_query_arg(array(
        'recover_cart' => $cart->session_id,
        'coupon' => $coupon_code
    ), site_url('/cart/'));
    
    // Email content
    $subject = 'Complete Your Purchase with a Special Discount';
    
    $body = '<div style="max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif; color: #333;">';
    $body .= '<div style="background-color: #f8f8f8; padding: 20px; text-align: center;">';
    $body .= '<h1 style="color: #0073aa; margin: 0;">Items Left in Your Cart</h1>';
    $body .= '</div>';
    $body .= '<div style="padding: 20px;">';
    $body .= '<p>Hello,</p>';
    $body .= '<p>We noticed you left some items in your shopping cart. Would you like to complete your purchase?</p>';
    $body .= '<p>Here\'s what you had in your cart:</p>';
    $body .= $cart_items_html;
    $body .= '<p>Good news! We\'ve created a <strong>special discount</strong> just for you.</p>';
    $body .= '<p>Use code: <strong style="background-color: #f1f1f1; padding: 5px 10px;">' . $coupon_code . '</strong> to get 10% off your purchase.</p>';
    $body .= '<div style="text-align: center; margin: 30px 0;">';
    $body .= '<a href="' . esc_url($recovery_url) . '" style="background-color: #0073aa; color: white; padding: 12px 20px; text-decoration: none; border-radius: 4px; font-weight: bold;">Complete Your Purchase</a>';
    $body .= '</div>';
    $body .= '<p>This offer is valid for 7 days, so don\'t miss out!</p>';
    $body .= '<p>Thank you for shopping with us.</p>';
    $body .= '</div>';
    $body .= '<div style="background-color: #f8f8f8; padding: 15px; text-align: center; font-size: 12px; color: #666;">';
    $body .= '<p>' . get_bloginfo('name') . ' - ' . site_url() . '</p>';
    $body .= '</div>';
    $body .= '</div>';
    
    $headers = array('Content-Type: text/html; charset=UTF-8');
    
    // Send the email
    $result = wp_mail($cart->email, $subject, $body, $headers);
    
    // Log the email
    if ($result) {
        wabot_log_email($cart->email, $subject, $body);
    }
    
    return $result;
}

// Send a recovery WhatsApp message to a specific abandoned cart
function wabot_send_recovery_whatsapp($cart) {
    // Only proceed if we have a phone number
    if (!$cart->phone) {
        return false;
    }
    
    $options = get_option('wabot_settings');
    $templates_options = get_option('wabot_settings_templates');
    $template_id = $options['wabot_template_abandoned_cart'] ?? '';
    $template_enabled = isset($templates_options['wabot_template_abandoned_cart_enabled']) ? 
                         $templates_options['wabot_template_abandoned_cart_enabled'] : '1';
    
    // Check if template exists and is enabled
    if (!$template_id || $template_enabled != '1') {
        return false;
    }
    
    // Generate coupon code for this recovery
    $coupon_code = 'RECOVER-' . strtoupper(wp_generate_password(6, false));
    
    // Create a new coupon
    $coupon = new WC_Coupon();
    $coupon->set_code($coupon_code);
    $coupon->set_discount_type('percent'); // or 'fixed_cart'
    $coupon->set_amount(10); // 10% discount
    $coupon->set_individual_use(true);
    $coupon->set_usage_limit(1);
    $coupon->set_date_expires(strtotime('+7 days'));
    $coupon->save();
    
    // Build recovery URL
    $recovery_url = add_query_arg(array(
        'recover_cart' => $cart->session_id,
        'coupon' => $coupon_code
    ), site_url('/cart/'));
    
    // Prepare variables for template
    $variables = array(
        'coupon_code' => $coupon_code,
        'cart_link' => $recovery_url,
    );
    
    // Send WhatsApp message
    try {
        $wabot = new Wabot_API();
        $result = $wabot->send_message($cart->phone, $template_id, $variables);
        return true;
    } catch (Exception $e) {
        error_log('WhatsApp message sending failed: ' . $e->getMessage());
        return false;
    }
}

// Handle cart recovery from email/WhatsApp links
add_action('template_redirect', 'wabot_handle_cart_recovery');
function wabot_handle_cart_recovery() {
    // Check if we have the recovery parameters
    if (isset($_GET['recover_cart']) && !empty($_GET['recover_cart'])) {
        $session_id = sanitize_text_field($_GET['recover_cart']);
        
        // Get the abandoned cart data
        global $wpdb;
        $table_name = $wpdb->prefix . 'wabot_abandoned_carts';
        $cart = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE session_id = %s", $session_id));
        
        if ($cart) {
            // Clear the current cart
            if (function_exists('WC') && WC()->cart) {
                WC()->cart->empty_cart();
                
                // Get the cart contents
                $cart_contents = maybe_unserialize($cart->cart_contents);
                
                if (is_array($cart_contents)) {
                    // Add each item to the cart
                    foreach ($cart_contents as $cart_item_key => $values) {
                        $product_id = $values['product_id'];
                        $quantity = $values['quantity'];
                        $variation_id = $values['variation_id'] ?? 0;
                        $variation = $values['variation'] ?? array();
                        $cart_item_data = $values['cart_item_data'] ?? array();
                        
                        // Add to cart
                        WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation, $cart_item_data);
                    }
                    
                    // Mark as recovered
                    update_post_meta($cart->id, '_wabot_cart_recovered', 'yes');
                    update_post_meta($cart->id, '_wabot_recovery_date', current_time('mysql'));
                    
                    // Apply coupon if provided
                    if (isset($_GET['coupon']) && !empty($_GET['coupon'])) {
                        $coupon_code = sanitize_text_field($_GET['coupon']);
                        WC()->cart->apply_coupon($coupon_code);
                        
                        // Add a notice
                        wc_add_notice(sprintf(
                            __('Welcome back! Your coupon %s has been applied to your cart.', 'wabot-woocommerce'),
                            '<strong>' . $coupon_code . '</strong>'
                        ), 'success');
                    } else {
                        // Add a notice
                        wc_add_notice(__('Welcome back! Your cart has been restored.', 'wabot-woocommerce'), 'success');
                    }
                }
            }
        }
    }
}