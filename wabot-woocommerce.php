<?php
/*
Plugin Name: Wabot WooCommerce
Description: Integrate Wabot WhatsApp notifications with WooCommerce.
Version: 1.0
Author: wabot.shop
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'is_woocommerce_active' ) ) {
    function is_woocommerce_active() {
        return in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) );
    }
}

if ( is_woocommerce_active() ) {
    // Your plugin code
} else {
    wp_die( 'This plugin requires WooCommerce to be active. Click back button' );
}

// Check if WooCommerce is active
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

    // Include necessary files
    include_once plugin_dir_path( __FILE__ ) . 'includes/admin-settings.php';
    include_once plugin_dir_path( __FILE__ ) . 'includes/wabot-api.php';
    include_once plugin_dir_path( __FILE__ ) . 'includes/notifications.php';
    include_once plugin_dir_path( __FILE__ ) . 'includes/abandoned-cart.php';

} else {
    add_action( 'admin_notices', 'wabot_wc_inactive_notice' );
    function wabot_wc_inactive_notice() {

        echo '<div class="error"><p><strong>Wabot WooCommerce Integration</strong> requires WooCommerce to be installed and active.</p></div>';
    }
}


// On Plugin Activation
register_activation_hook( __FILE__, 'wabot_plugin_activate' );
function wabot_plugin_activate() {
    // Create necessary tables
    wabot_create_abandoned_cart_table();
    wabot_create_email_log_table();

    // Schedule events
    if ( ! wp_next_scheduled( 'wabot_check_abandoned_carts' ) ) {
        wp_schedule_event( time(), 'hourly', 'wabot_check_abandoned_carts' );
    }
    if ( ! wp_next_scheduled( 'wabot_cleanup_abandoned_carts' ) ) {
        wp_schedule_event( time(), 'daily', 'wabot_cleanup_abandoned_carts' );
    }
}

// On Plugin Deactivation
register_deactivation_hook( __FILE__, 'wabot_plugin_deactivate' );
function wabot_plugin_deactivate() {
    // Clear scheduled events
    wp_clear_scheduled_hook( 'wabot_check_abandoned_carts' );
    wp_clear_scheduled_hook( 'wabot_cleanup_abandoned_carts' );
}


function wabot_enqueue_admin_scripts( $hook ) {
    error_log( 'Current hook: ' . $hook );

    if ( strpos( $hook, 'wabot-settings' ) === false ) {
        return;
    }

    // Enqueue modal styling and JavaScript
    wp_enqueue_style( 'wabot-admin-style', plugin_dir_url( __FILE__ ) . '/css/wabot-admin.css' );
    wp_enqueue_script( 'wabot-admin-script', plugins_url( '/js/wabot-admin.js', __FILE__ ), array( 'jquery' ), '1.0', true );

    // Include intl-tel-input library
    wp_enqueue_style( 'intl-tel-input-style', 'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.min.css' );
    wp_enqueue_script( 'intl-tel-input-script', 'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/intlTelInput.min.js', array( 'jquery' ), null, true );
    
    // Localize script for dynamic modal content
    wp_localize_script( 'wabot-admin-script', 'wabotAdmin', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
    ) );
}
add_action( 'admin_enqueue_scripts', 'wabot_enqueue_admin_scripts' );
