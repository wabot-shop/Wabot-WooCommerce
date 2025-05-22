<?php
if (!defined('ABSPATH')) {
    exit;
}

class Wabot_Webhook {
    private static $instance = null;
    private $webhook_url = 'https://woo.wabot.shop/webhook/';
    private $is_enabled;

    private function __construct() {
        $options = get_option('wabot_settings_credentials', array());
        $this->is_enabled = isset($options['wabot_integration_type']) && $options['wabot_integration_type'] === 'webhook';
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function is_enabled() {
        return $this->is_enabled;
    }

    public function send_notification($type, $data) {
        if (!$this->is_enabled()) {
            return false;
        }

        $payload = array(
            'type' => $type,
            'data' => $data,
            'timestamp' => current_time('mysql'),
            'site_url' => get_site_url(),
            'site_name' => get_bloginfo('name')
        );

        $response = wp_remote_post($this->webhook_url, array(
            'method' => 'POST',
            'timeout' => 45,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-Wabot-Webhook' => '1'
            ),
            'body' => json_encode($payload),
            'cookies' => array()
        ));

        if (is_wp_error($response)) {
            error_log('Wabot Webhook Error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('Wabot Webhook Error: Invalid response code ' . $response_code);
            return false;
        }

        return true;
    }

    public function send_order_notification($order_id, $type = 'new_order') {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }

        $data = array(
            'order_id' => $order->get_id(),
            'order_status' => $order->get_status(),
            'order_total' => $order->get_total(),
            'customer_name' => $order->get_formatted_billing_full_name(),
            'customer_email' => $order->get_billing_email(),
            'customer_phone' => $order->get_billing_phone(),
            'items' => array()
        );

        foreach ($order->get_items() as $item) {
            $data['items'][] = array(
                'product_id' => $item->get_product_id(),
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'total' => $item->get_total()
            );
        }

        return $this->send_notification($type, $data);
    }

    public function send_abandoned_cart_notification($cart_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wabot_abandoned_carts';
        
        $cart = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $cart_id
        ));

        if (!$cart) {
            return false;
        }

        $data = array(
            'cart_id' => $cart->id,
            'customer_email' => $cart->email,
            'customer_phone' => $cart->phone,
            'cart_total' => $cart->cart_total,
            'cart_contents' => maybe_unserialize($cart->cart_contents),
            'created_at' => $cart->created_at,
            'recovery_link' => wabot_get_recovery_link($cart->id)
        );

        return $this->send_notification('abandoned_cart', $data);
    }

    public function send_user_notification($user_id, $type = 'new_user') {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        $data = array(
            'user_id' => $user->ID,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'name' => $user->display_name,
            'phone' => get_user_meta($user->ID, 'billing_phone', true)
        );

        return $this->send_notification($type, $data);
    }
} 