<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Wabot_Notifications {
    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // User registration hooks
        add_action('user_register', array($this, 'handle_new_user_registration'), 10, 1);
        add_action('after_password_reset', array($this, 'handle_password_reset'), 10, 2);

        // WooCommerce order hooks
        add_action('woocommerce_new_order', array($this, 'handle_new_order'), 10, 1);
        add_action('woocommerce_order_status_changed', array($this, 'handle_order_status_change'), 10, 3);

        // Abandoned cart hook (custom)
        add_action('wabot_abandoned_cart_notification', array($this, 'handle_abandoned_cart'), 10, 1);
    }

    /**
     * Handle new user registration
     */
    public function handle_new_user_registration($user_id) {
        $user = get_userdata($user_id);
        if (!$user) return;

        // Get user's phone number from user meta
        $phone = get_user_meta($user_id, 'phone_number', true);
        
        // Prepare variables for templates
        $variables = array(
            'customer_name' => $user->display_name,
            'site_name' => get_bloginfo('name'),
            'site_url' => get_site_url()
        );

        // Send notifications
        if ($phone) {
            $this->send_whatsapp_notification('new_user', $phone, $variables);
        }
        $this->send_email_notification('new_user', $user->user_email, $variables);
    }

    /**
     * Handle password reset
     */
    public function handle_password_reset($user, $new_pass) {
        if (!$user) return;

        // Get user's phone number
        $phone = get_user_meta($user->ID, 'phone_number', true);
        
        // Prepare variables
        $variables = array(
            'customer_name' => $user->display_name,
            'site_name' => get_bloginfo('name'),
            'site_url' => get_site_url()
        );

        // Send notifications
        if ($phone) {
            $this->send_whatsapp_notification('password_reset', $phone, $variables);
        }
        $this->send_email_notification('password_reset', $user->user_email, $variables);
    }

    /**
     * Handle new order
     */
    public function handle_new_order($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;

        // Get customer's phone number and email
        $phone = $order->get_billing_phone();
        $email = $order->get_billing_email();
        
        // If user is registered, try to get phone from user meta
        if ($order->get_user_id()) {
            $user_phone = get_user_meta($order->get_user_id(), 'phone_number', true);
            if ($user_phone) {
                $phone = $user_phone;
            }
        }
        
        // Get order items for potential use in templates
        $items_list = array();
        foreach ($order->get_items() as $item) {
            $items_list[] = $item->get_name() . ' x ' . $item->get_quantity();
        }
        
        // Prepare variables
        $variables = array(
            'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'order_id' => $order->get_order_number(),
            'order_total' => $order->get_formatted_order_total(),
            'order_status' => wc_get_order_status_name($order->get_status()),
            'site_name' => get_bloginfo('name'),
            'site_url' => get_site_url(),
            'order_items' => implode(", ", $items_list)
        );

        // Send notifications
        if ($phone) {
            $this->send_whatsapp_notification('new_order', $phone, $variables);
        }
        $this->send_email_notification('new_order', $email, $variables);
    }

    /**
     * Handle order status change
     */
    public function handle_order_status_change($order_id, $old_status, $new_status) {
        $order = wc_get_order($order_id);
        if (!$order) return;

        // Get customer's phone number and email
        $phone = $order->get_billing_phone();
        $email = $order->get_billing_email();
        
        // If user is registered, try to get phone from user meta
        if ($order->get_user_id()) {
            $user_phone = get_user_meta($order->get_user_id(), 'phone_number', true);
            if ($user_phone) {
                $phone = $user_phone;
            }
        }
        
        // Get order items
        $items_list = array();
        foreach ($order->get_items() as $item) {
            $items_list[] = $item->get_name() . ' x ' . $item->get_quantity();
        }
        
        // Prepare variables
        $variables = array(
            'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'order_id' => $order->get_order_number(),
            'order_total' => $order->get_formatted_order_total(),
            'order_status' => wc_get_order_status_name($new_status),
            'site_name' => get_bloginfo('name'),
            'site_url' => get_site_url(),
            'order_items' => implode(", ", $items_list)
        );

        // Send notifications
        if ($phone) {
            $this->send_whatsapp_notification('order_status', $phone, $variables);
        }
        $this->send_email_notification('order_status', $email, $variables);
    }

    /**
     * Handle abandoned cart notification
     */
    public function handle_abandoned_cart($cart_id) {
        global $wpdb;
        
        // Get cart details from your abandoned cart table
        $cart = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wabot_abandoned_carts WHERE id = %d",
            $cart_id
        ));
        
        if (!$cart) return;

        // Generate recovery link
        $recovery_link = add_query_arg(array(
            'recover_cart' => base64_encode($cart_id),
            'token' => wp_create_nonce('recover_cart_' . $cart_id)
        ), wc_get_cart_url());

        // Generate coupon code if enabled
        $coupon_code = $this->generate_abandoned_cart_coupon($cart_id);
        
        // Get cart items
        $cart_items = maybe_unserialize($cart->cart_contents);
        $items_list = array();
        if (is_array($cart_items)) {
            foreach ($cart_items as $item) {
                if (isset($item['product_id']) && isset($item['quantity'])) {
                    $product = wc_get_product($item['product_id']);
                    if ($product) {
                        $items_list[] = $product->get_name() . ' x ' . $item['quantity'];
                    }
                }
            }
        }
        
        // Prepare variables
        $variables = array(
            'customer_name' => $cart->customer_name,
            'recovery_link' => $recovery_link,
            'coupon_code' => $coupon_code,
            'site_name' => get_bloginfo('name'),
            'site_url' => get_site_url(),
            'cart_items' => implode(", ", $items_list),
            'cart_total' => wc_price($cart->cart_total)
        );

        // Send notifications
        if ($cart->customer_phone) {
            $this->send_whatsapp_notification('abandoned_cart', $cart->customer_phone, $variables);
        }
        $this->send_email_notification('abandoned_cart', $cart->customer_email, $variables);
    }

    /**
     * Send WhatsApp notification
     */
    private function send_whatsapp_notification($template_key, $phone, $variables) {
        // Get template settings
        $templates = get_option('wabot_settings_templates', array());
        
        // Check if this template is enabled
        if (empty($templates["wabot_template_{$template_key}_enabled"]) || 
            $templates["wabot_template_{$template_key}_enabled"] !== '1') {
            error_log("WhatsApp template '{$template_key}' is disabled. Skipping message send.");
            return;
        }

        // Get selected template name
        $template_name = $templates["wabot_template_$template_key"] ?? '';
        if (empty($template_name)) {
            error_log("No template selected for '{$template_key}'. Skipping message send.");
            return;
        }

        // Get template data to check available variables
        $template_data = wabot_get_single_template($template_name);
        if (!$template_data) {
            error_log("Could not get template data for '{$template_name}'. Skipping message send.");
            return;
        }

        // Get template variables
        $template_variables = array();
        foreach ($template_data['components'] as $component) {
            if ($component['type'] === 'body' && isset($component['variables'])) {
                $template_variables = $component['variables'];
                break;
            }
        }

        if (empty($template_variables)) {
            error_log("No variables found in template '{$template_name}'. Sending without variables.");
            $wabot = new Wabot_API();
            $wabot->send_message($phone, $template_name, array());
            return;
        }

        // Get configured variable mappings and default values
        $variable_mappings = isset($templates["wabot_template_{$template_key}_mapping"]) ? 
            $templates["wabot_template_{$template_key}_mapping"] : array();
        $default_values = isset($templates["wabot_template_{$template_key}_variables"]) ? 
            $templates["wabot_template_{$template_key}_variables"] : array();

        // Prepare variables for WhatsApp API
        $api_variables = array();
        foreach ($template_variables as $var_key => $var_info) {
            $var_name = $var_info['text'];
            
            // Check if there's a mapping for this variable
            if (isset($variable_mappings[$var_key]) && !empty($variable_mappings[$var_key])) {
                $mapped_key = $variable_mappings[$var_key];
                
                // Use mapped system variable if available
                if (isset($variables[$mapped_key])) {
                    $api_variables[$var_name] = $variables[$mapped_key];
                }
                // Otherwise use default value
                else if (isset($default_values[$var_key])) {
                    $api_variables[$var_name] = $default_values[$var_key];
                }
            }
            // If no mapping, use default value
            else if (isset($default_values[$var_key])) {
                $api_variables[$var_name] = $default_values[$var_key];
            }
        }

        // Log the variables being sent (for debugging)
        error_log("Sending WhatsApp notification for template '{$template_key}' with variables: " . json_encode($api_variables));

        // Send WhatsApp message
        $wabot = new Wabot_API();
        $wabot->send_message($phone, $template_name, $api_variables);
    }

    /**
     * Send email notification
     */
    private function send_email_notification($template_key, $email, $variables) {
        // Get email settings
        $settings = get_option('wabot_settings_email', array());
        
        // Check if this template is enabled
        if (empty($settings["wabot_email_template_{$template_key}_enabled"]) || 
            $settings["wabot_email_template_{$template_key}_enabled"] !== '1') {
            return;
        }

        // Get template content and subject
        $subject = $settings["wabot_email_template_{$template_key}_subject"] ?? '';
        $content = $settings["wabot_email_template_$template_key"] ?? '';

        if (empty($subject) || empty($content)) return;

        // Replace variables in subject and content
        foreach ($variables as $key => $value) {
            $subject = str_replace("{{$key}}", $value, $subject);
            $content = str_replace("{{$key}}", $value, $content);
        }

        // Set up email headers
        $headers = array('Content-Type: text/html; charset=UTF-8');

        // Send email
        wp_mail($email, $subject, $content, $headers);
    }

    /**
     * Generate a unique coupon code for abandoned cart recovery
     */
    private function generate_abandoned_cart_coupon($cart_id) {
        // Generate a unique coupon code
        $coupon_code = 'RECOVER' . $cart_id . strtoupper(substr(uniqid(), -6));
        
        // Create the coupon
        $coupon = new WC_Coupon();
        $coupon->set_code($coupon_code);
        $coupon->set_discount_type('percent');
        $coupon->set_amount(10); // 10% discount
        $coupon->set_individual_use(true);
        $coupon->set_usage_limit(1);
        $coupon->set_date_expires(strtotime('+7 days'));
        $coupon->save();
        
        return $coupon_code;
    }
}

// Initialize the notifications class
add_action('init', array('Wabot_Notifications', 'get_instance'));
