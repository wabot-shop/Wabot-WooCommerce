<?php
require_once plugin_dir_path(__FILE__) . 'includes/class-wabot-api.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-wabot-webhook.php';

// Update notification handling to use webhooks when enabled
function wabot_handle_notification($type, $data) {
    $webhook = Wabot_Webhook::get_instance();
    
    if ($webhook->is_enabled()) {
        return $webhook->send_notification($type, $data);
    }
    
    // Fallback to API if webhook is disabled
    $api = new Wabot_API();
    return $api->send_message($data['to'], $data['template'], $data['params']);
}

// Update order notification handling
add_action('woocommerce_new_order', 'wabot_handle_new_order', 10, 1);
function wabot_handle_new_order($order_id) {
    $webhook = Wabot_Webhook::get_instance();
    
    if ($webhook->is_enabled()) {
        $webhook->send_order_notification($order_id, 'new_order');
    } else {
        // Existing API-based notification code
        $order = wc_get_order($order_id);
        if (!$order) return;

        $customer_phone = $order->get_billing_phone();
        $customer_email = $order->get_billing_email();

        if ($customer_phone) {
            $api = new Wabot_API();
            $api->send_message($customer_phone, 'new_order', array(
                'customer_name' => $order->get_formatted_billing_full_name(),
                'order_id' => $order->get_id(),
                'order_total' => $order->get_total(),
                'order_status' => $order->get_status()
            ));
        }
    }
}

// Update abandoned cart notification handling
function wabot_send_recovery_message($cart) {
    $webhook = Wabot_Webhook::get_instance();
    
    if ($webhook->is_enabled()) {
        return $webhook->send_abandoned_cart_notification($cart->id);
    }
    
    // Existing API-based notification code
    $api = new Wabot_API();
    return $api->send_message($cart->phone, 'abandoned_cart', array(
        'customer_name' => $cart->name,
        'recovery_link' => wabot_get_recovery_link($cart->id),
        'coupon_code' => wabot_generate_coupon_code()
    ));
}

// Update user registration notification handling
add_action('user_register', 'wabot_handle_new_user', 10, 1);
function wabot_handle_new_user($user_id) {
    $webhook = Wabot_Webhook::get_instance();
    
    if ($webhook->is_enabled()) {
        $webhook->send_user_notification($user_id, 'new_user');
    } else {
        // Existing API-based notification code
        $user = get_userdata($user_id);
        if (!$user) return;

        $phone = get_user_meta($user_id, 'billing_phone', true);
        if ($phone) {
            $api = new Wabot_API();
            $api->send_message($phone, 'new_user', array(
                'customer_name' => $user->display_name,
                'site_name' => get_bloginfo('name'),
                'site_url' => get_site_url()
            ));
        }
    }
} 