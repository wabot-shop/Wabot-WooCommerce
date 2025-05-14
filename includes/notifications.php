<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// New User Registration
add_action( 'user_register', 'wabot_send_new_user_message', 10, 1 );
function wabot_send_new_user_message( $user_id ) {
    $user = get_userdata( $user_id );
    $phone = get_user_meta( $user_id, 'billing_phone', true );
    if ( $phone ) {
        $wabot = new Wabot_API();
        $options = get_option( 'wabot_settings' );
        $templates_options = get_option( 'wabot_settings_templates' );
        $template_id = $options['wabot_template_new_user'] ?? '';
        $template_enabled = isset( $templates_options['wabot_template_new_user_enabled'] ) ? 
                          $templates_options['wabot_template_new_user_enabled'] : '1';
        
        // Only send if template is enabled
        if ( $template_id && $template_enabled == '1' ) {
            $variables = array(
                'name' => $user->first_name,
            );

            $wabot->send_message( $phone, $template_id, $variables );
        }
    }
}

// Password Reset
add_action( 'after_password_reset', 'wabot_send_password_reset_message', 10, 2 );
function wabot_send_password_reset_message( $user, $new_pass ) {
    $phone = get_user_meta( $user->ID, 'billing_phone', true );
    if ( $phone ) {
        $wabot = new Wabot_API();
        $options = get_option( 'wabot_settings' );
        $templates_options = get_option( 'wabot_settings_templates' );
        $template_id = $options['wabot_template_password_reset'] ?? '';
        $template_enabled = isset( $templates_options['wabot_template_password_reset_enabled'] ) ? 
                          $templates_options['wabot_template_password_reset_enabled'] : '1';
        
        // Only send if template is enabled
        if ( $template_id && $template_enabled == '1' ) {
            $variables = array(
                'name'     => $user->first_name,
                'new_pass' => $new_pass,
            );

            $wabot->send_message( $phone, $template_id, $variables );
        }
    }
}

// New Order
add_action( 'woocommerce_thankyou', 'wabot_send_new_order_message', 10, 1 );
function wabot_send_new_order_message( $order_id ) {
    if ( ! $order_id ) {
        return;
    }

    $order = wc_get_order( $order_id );
    $phone = $order->get_billing_phone();

    if ( $phone ) {
        $wabot = new Wabot_API();
        $options = get_option( 'wabot_settings' );
        $templates_options = get_option( 'wabot_settings_templates' );
        $template_id = $options['wabot_template_new_order'] ?? '';
        $template_enabled = isset( $templates_options['wabot_template_new_order_enabled'] ) ? 
                          $templates_options['wabot_template_new_order_enabled'] : '1';
        
        // Only send if template is enabled
        if ( $template_id && $template_enabled == '1' ) {
            $variables = array(
                'order_id' => $order->get_order_number(),
                'total'    => $order->get_total(),
            );

            $wabot->send_message( $phone, $template_id, $variables );
        }
    }
}

// Order Status Updates
add_action( 'woocommerce_order_status_changed', 'wabot_send_order_status_message', 10, 4 );
function wabot_send_order_status_message( $order_id, $old_status, $new_status, $order ) {
    $phone = $order->get_billing_phone();
    if ( $phone ) {
        $wabot = new Wabot_API();
        $options = get_option( 'wabot_settings' );
        $templates_options = get_option( 'wabot_settings_templates' );
        $template_id = $options['wabot_template_order_status'] ?? '';
        $template_enabled = isset( $templates_options['wabot_template_order_status_enabled'] ) ? 
                          $templates_options['wabot_template_order_status_enabled'] : '1';
        
        // Only send if template is enabled
        if ( $template_id && $template_enabled == '1' ) {
            $variables = array(
                'order_id' => $order->get_order_number(),
                'status'   => $new_status,
            );

            $wabot->send_message( $phone, $template_id, $variables );
        }
    }
}
