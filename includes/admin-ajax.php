<?php
// test email AJAX handler
add_action('wp_ajax_wabot_send_test_email', 'wabot_send_test_email');
function wabot_send_test_email() {
    // Check nonce
    check_ajax_referer('wabot_admin_nonce', 'nonce');
    
    // Check user capability
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied']);
        return;
    }
    
    // Get parameters
    $template_key = isset($_POST['template_key']) ? sanitize_text_field($_POST['template_key']) : '';
    $recipient = isset($_POST['recipient']) ? sanitize_email($_POST['recipient']) : '';
    $variables = isset($_POST['variables']) ? $_POST['variables'] : [];
    
    if (empty($template_key) || empty($recipient)) {
        wp_send_json_error(['message' => 'Missing required parameters']);
        return;
    }
    
    // Get template content and subject
    $email_settings = get_option('wabot_settings_email', []);
    $template_content = isset($email_settings["wabot_email_template_{$template_key}"]) ? 
                        $email_settings["wabot_email_template_{$template_key}"] : 
                        wabot_get_default_email_template($template_key);
    
    $template_subject = isset($email_settings["wabot_email_template_{$template_key}_subject"]) ? 
                        $email_settings["wabot_email_template_{$template_key}_subject"] : 
                        wabot_get_default_email_subject($template_key);
    
    // Replace variables in subject and content
    if (is_array($variables)) {
        foreach ($variables as $key => $value) {
            $template_subject = str_replace("{{$key}}", $value, $template_subject);
            $template_content = str_replace("{{$key}}", $value, $template_content);
        }
    }
    
    // Get the site info for headers
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
        'error_message' => $sent ? '' : 'Failed to send test email'
    ));
    
    if ($sent) {
        wp_send_json_success(['message' => 'Test email sent successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to send test email']);
    }
} 