<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Send an email using the specified template.
 *
 * @param string $template_key Template key identifier
 * @param string $recipient Recipient email address
 * @param array $variables Variables to replace in the template
 * @return bool Whether the email was sent successfully
 */
function wabot_send_email($template_key, $recipient, $variables = []) {
    // Get template content and subject
    $email_settings = get_option('wabot_settings_email', []);
    
    // Check if this template is enabled
    $template_enabled = isset($email_settings["wabot_email_template_{$template_key}_enabled"]) ? 
                       $email_settings["wabot_email_template_{$template_key}_enabled"] : '1';
                       
    if ($template_enabled !== '1') {
        error_log("Email template '{$template_key}' is disabled. Skipping email send.");
        return false;
    }
    
    $template_content = isset($email_settings["wabot_email_template_{$template_key}"]) ? 
                       $email_settings["wabot_email_template_{$template_key}"] : 
                       wabot_get_default_email_template($template_key);
    
    $template_subject = isset($email_settings["wabot_email_template_{$template_key}_subject"]) ? 
                       $email_settings["wabot_email_template_{$template_key}_subject"] : 
                       wabot_get_default_email_subject($template_key);
    
    // Replace variables in subject and content
    if (is_array($variables) && !empty($variables)) {
        foreach ($variables as $key => $value) {
            $template_subject = str_replace("{{$key}}", $value, $template_subject);
            $template_content = str_replace("{{$key}}", $value, $template_content);
        }
    }
    
    // Get site info for email headers
    $site_name = get_bloginfo('name');
    $admin_email = get_option('admin_email');
    
    // Set up email headers
    $headers = [];
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $headers[] = "From: {$site_name} <{$admin_email}>";
    
    // Send the email
    $sent = wp_mail($recipient, $template_subject, $template_content, $headers);
    
    // Log the result
    global $wpdb;
    $table_name = $wpdb->prefix . 'wabot_email_log';
    
    $wpdb->insert($table_name, array(
        'email_to' => $recipient,
        'template_key' => $template_key,
        'subject' => $template_subject,
        'variables' => maybe_serialize($variables),
        'status' => $sent ? 'sent' : 'failed',
        'sent_at' => current_time('mysql'),
        'error_message' => $sent ? '' : 'Failed to send email'
    ));
    
    if ($sent) {
        error_log("Email '{$template_key}' sent successfully to {$recipient}");
    } else {
        error_log("Failed to send email '{$template_key}' to {$recipient}");
    }
    
    return $sent;
}

/**
 * Integrate our emails with the plugin's notification system
 * Implementation examples for different notification types
 */

// New User Registration
function wabot_send_new_user_email($user_id) {
    $user = get_userdata($user_id);
    $user_email = $user->user_email;
    
    if (!$user_email) {
        return false;
    }
    
    $variables = [
        'customer_name' => $user->display_name,
        'site_name' => get_bloginfo('name'),
        'site_url' => get_site_url()
    ];
    
    return wabot_send_email('new_user', $user_email, $variables);
}

// New Order
function wabot_send_new_order_email($order_id) {
    if (!class_exists('WC_Order')) {
        return false;
    }
    
    $order = wc_get_order($order_id);
    if (!$order) {
        return false;
    }
    
    $customer_email = $order->get_billing_email();
    if (!$customer_email) {
        return false;
    }
    
    $variables = [
        'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
        'order_id' => $order->get_order_number(),
        'order_total' => $order->get_formatted_order_total(),
        'site_name' => get_bloginfo('name'),
        'site_url' => get_site_url()
    ];
    
    return wabot_send_email('new_order', $customer_email, $variables);
}

// Order Status Change
function wabot_send_order_status_email($order_id, $old_status, $new_status) {
    if (!class_exists('WC_Order')) {
        return false;
    }
    
    $order = wc_get_order($order_id);
    if (!$order) {
        return false;
    }
    
    $customer_email = $order->get_billing_email();
    if (!$customer_email) {
        return false;
    }
    
    $variables = [
        'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
        'order_id' => $order->get_order_number(),
        'order_status' => wc_get_order_status_name($new_status),
        'site_name' => get_bloginfo('name'),
        'site_url' => get_site_url()
    ];
    
    return wabot_send_email('order_status', $customer_email, $variables);
}

// Abandoned Cart Email
function wabot_send_abandoned_cart_email($cart) {
    if (!isset($cart->email) || empty($cart->email)) {
        return false;
    }
    
    // Generate a recovery link and coupon code
    $recovery_link = site_url('/cart/');
    $coupon_code = 'RECOVER-' . strtoupper(wp_generate_password(6, false));
    
    // Create a new coupon
    if (class_exists('WC_Coupon')) {
        $coupon = new WC_Coupon();
        $coupon->set_code($coupon_code);
        $coupon->set_discount_type('percent');
        $coupon->set_amount(10); // 10% discount
        $coupon->set_individual_use(true);
        $coupon->set_usage_limit(1);
        $coupon->set_date_expires(strtotime('+7 days'));
        $coupon->save();
    }
    
    $variables = [
        'customer_name' => $cart->first_name ?? 'Customer',
        'coupon_code' => $coupon_code,
        'recovery_link' => $recovery_link,
        'site_name' => get_bloginfo('name'),
        'site_url' => get_site_url()
    ];
    
    return wabot_send_email('abandoned_cart', $cart->email, $variables);
} 