<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Wabot_API {

    private $client_id;
    private $client_secret;
    private $token;
    private $refresh_token;

    public function __construct() {
        $credentials = get_option( 'wabot_settings_credentials' );
        $this->client_id     = $credentials['wabot_api_key'] ?? '';
        $this->client_secret = $credentials['wabot_api_secret'] ?? '';
        $this->token         = get_option( 'wabot_access_token', '' );
        $this->refresh_token = get_option( 'wabot_refresh_token', '' );
    }

    public function authenticate() {
        $api_url = 'https://api.wabot.shop/v1/authenticate';
    
        $headers = array(
            'clientSecret' => $this->client_secret,
            'clientId'     => $this->client_id,
        );
        $response = wp_remote_post( $api_url, array(
            'method'  => 'POST',
            'timeout' => 45,
            'headers' => $headers,
        ) );
        if ( is_wp_error( $response ) ) {
            error_log( 'Wabot API Error (authenticate): ' . $response->get_error_message() );
            return false;
        }
    
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
    
        if ( isset( $data['token'] ) && isset( $data['refreshToken'] ) ) {
            // Store tokens in options
            update_option( 'wabot_access_token', $data['token'] );
            update_option( 'wabot_refresh_token', $data['refreshToken'] );
            $this->token = $data['token'];
            $this->refresh_token = $data['refreshToken'];
            return true;
        } else {
            error_log( 'Wabot API Error (authenticate): Invalid response' );
            return false;
        }
    }

    public function refresh_token() {
        $api_url = 'https://api.wabot.shop/v1/refreshToken';
    
        $headers = array(
            'clientSecret' => $this->client_secret,
            'clientId'     => $this->client_id,
            'Content-Type' => 'application/json',
        );
    
        $body = json_encode( array(
            'refreshToken' => $this->refresh_token,
        ) );
    
        $response = wp_remote_post( $api_url, array(
            'method'  => 'POST',
            'timeout' => 45,
            'headers' => $headers,
            'body'    => $body,
        ) );
    
        if ( is_wp_error( $response ) ) {
            error_log( 'Wabot API Error (refresh_token): ' . $response->get_error_message() );
            // Clear tokens
            $this->token = '';
            $this->refresh_token = '';
            delete_option( 'wabot_access_token' );
            delete_option( 'wabot_refresh_token' );
            delete_option( 'wabot_token_expiration' );
            return false;
        }
    
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
    
        if ( isset( $data['token'] ) && isset( $data['refreshToken'] ) ) {
            $token = $data['token'];
            $refresh_token = $data['refreshToken'];
    
            // Decode the token to get expiration time
            $token_parts = explode( '.', $token );
            if ( count( $token_parts ) === 3 ) {
                $payload = json_decode( base64_decode( $token_parts[1] ), true );
                if ( isset( $payload['exp'] ) ) {
                    $token_expiration = $payload['exp'];
                    update_option( 'wabot_token_expiration', $token_expiration );
                } else {
                    // If 'exp' is not present, set a default expiration time (e.g., 1 hour from now)
                    $token_expiration = time() + 3600;
                    update_option( 'wabot_token_expiration', $token_expiration );
                }
            }
    
            update_option( 'wabot_access_token', $token );
            update_option( 'wabot_refresh_token', $refresh_token );
            $this->token = $token;
            $this->refresh_token = $refresh_token;
    
            return true;
        } else {
            error_log( 'Wabot API Error (refresh_token): Invalid response' );
            // Clear tokens
            $this->token = '';
            $this->refresh_token = '';
            delete_option( 'wabot_access_token' );
            delete_option( 'wabot_refresh_token' );
            delete_option( 'wabot_token_expiration' );
            return false;
        }
    }
    

    private function is_token_expired() {
        $token_expiration = get_option( 'wabot_token_expiration', 0 );
        $current_time = time();
        return $current_time >= $token_expiration;
    }
    

    private function ensure_token() {
        if ( empty( $this->token ) ) {
            $this->authenticate();
        } elseif ( $this->is_token_expired() ) {
            if ( ! $this->refresh_token() ) {
                // If refresh_token() fails, try to authenticate again
                $this->authenticate();
            }
        }
    }


    public function ensure_valid_token() {
        // If we don't have a token, authenticate
        if (empty($this->token)) {
            return $this->authenticate();
        }
    
        // Decode the token to get the expiration time
        $decoded_token = $this->decode_jwt($this->token);
    
        if ($decoded_token && isset($decoded_token['exp'])) {
            $expiration_time = $decoded_token['exp'];
            $current_time = time();
    
            // If the token is expired or about to expire in the next 60 seconds
            if ($current_time >= ($expiration_time - 60)) {
                // Attempt to refresh the token
                return $this->refresh_token();
            } else {
                // Token is still valid
                return true;
            }
        } else {
            // Unable to decode token; authenticate again
            return $this->authenticate();
        }
    }
    

    public function get_account() {
        // Ensure we have a valid token
        $this->ensure_token();
        if ( empty( $this->token ) ) {
            error_log( 'Wabot API Error: Unable to authenticate.' );
            return false;
        }
    
        $api_url = 'https://api.wabot.shop/account';
        
        $headers = array(
            'Authorization' => $this->token,
        );
    
        $response = wp_remote_get( $api_url, array(
            'timeout' => 45,
            'headers' => $headers,
        ) );
    
        if ( is_wp_error( $response ) ) {
            error_log( 'Wabot API Error (get_templates): ' . $response->get_error_message() );
            return false;
        }
    
        $status_code = wp_remote_retrieve_response_code( $response );
    
        // If unauthorized, try refreshing the token
        if ( $status_code == 401 ) {
            // Attempt to refresh token
            if ( $this->refresh_token() ) {
                // Retry the API call with the new token
                $headers['Authorization'] = $this->token;
                $response = wp_remote_get( $api_url, array(
                    'timeout' => 45,
                    'headers' => $headers,
                ) );
    
                if ( is_wp_error( $response ) ) {
                    error_log( 'Wabot API Error (get_templates after refresh): ' . $response->get_error_message() );
                    return false;
                }
            } else {
                error_log( 'Wabot API Error: Unable to refresh token.' );
                return false;
            }
        }
    
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
    
        if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
            return $data['data'];
        } else {
            error_log( 'Wabot API Error (account): Invalid response' );
            return false;
        }
    }
    
    public function get_templates() {
        // Ensure we have a valid token
        $this->ensure_token();
        if ( empty( $this->token ) ) {
            error_log( 'Wabot API Error: Unable to authenticate.' );
            return false;
        }
    
        $api_url = 'https://api.wabot.shop/templates/get-templates';
        
        $headers = array(
            'Authorization' => $this->token,
        );
    
        $response = wp_remote_get( $api_url, array(
            'timeout' => 45,
            'headers' => $headers,
        ) );
    
        if ( is_wp_error( $response ) ) {
            error_log( 'Wabot API Error (get_templates): ' . $response->get_error_message() );
            return false;
        }
    
        $status_code = wp_remote_retrieve_response_code( $response );
    
        // If unauthorized, try refreshing the token
        if ( $status_code == 401 ) {
            // Attempt to refresh token
            if ( $this->refresh_token() ) {
                // Retry the API call with the new token
                $headers['Authorization'] = $this->token;
                $response = wp_remote_get( $api_url, array(
                    'timeout' => 45,
                    'headers' => $headers,
                ) );
    
                if ( is_wp_error( $response ) ) {
                    error_log( 'Wabot API Error (get_templates after refresh): ' . $response->get_error_message() );
                    return false;
                }
            } else {
                error_log( 'Wabot API Error: Unable to refresh token.' );
                return false;
            }
        }
    
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
    
        if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
            return $data['data'];
        } else {
            error_log( 'Wabot API Error (get_templates): Invalid response' );
            return false;
        }
    }
    
    
    public function send_message( $to, $template_name, $template_params = array() ) {
        // Ensure we have a valid token
        $this->ensure_token();

        // Debug log the input parameters
        error_log('Template params for ' . $template_name . ': ' . json_encode($template_params));
    
        if ( empty( $this->token ) ) {
            error_log( 'Wabot API Error: Unable to authenticate.' );
            return false;
        }
    
        // Get the active phone number from profile settings
        $profile_settings = get_option('wabot_settings_profile', array());
        $phone_id = !empty($profile_settings['active_phone']) ? $profile_settings['active_phone'] : '';
        
        if (empty($phone_id)) {
            error_log('Wabot API Error: No active phone ID selected in settings.');
            return false;
        }
    
        // API endpoint with dynamic phone ID
        $api_url = 'https://api.wabot.shop/send-message/' . $phone_id;
    
        // Headers for the request
        $headers = array(
            'Content-Type'  => 'application/json',
            'Authorization' => $this->token,
        );
    
        // Body for the request
        $body = array(
            'to'           => $to, // Validated phone number
            'templateName' => $template_name,
            'typOfmsg'     => 'template'
        );
        
        // Get full template details to extract components
        $template_details = $this->get_template_details($template_name);
        
        // Log full template details for debugging
        error_log('Template details for ' . $template_name . ': ' . json_encode($template_details));
        
        if (!$template_details) {
            error_log('Wabot API Error: Template details not available for ' . $template_name);
            return false;
        }
        
        // Initialize component tracking
        $has_header = false;
        $has_body = false;
        $has_buttons = false;
        $template_variables = array();
        $button_components = array();
        
        // Extract components from template
        if (isset($template_details['components']) && is_array($template_details['components'])) {
            foreach ($template_details['components'] as $component) {
                if (!isset($component['type'])) {
                    continue;
                }
                
                // Track component types
                switch ($component['type']) {
                    case 'header':
                        $has_header = true;
                        break;
                        
                    case 'body':
                        $has_body = true;
                        
                        // Extract variables from body component variables array
                        if (isset($component['variables']) && is_array($component['variables'])) {
                            foreach ($component['variables'] as $index => $var) {
                                if (isset($var['text'])) {
                                    // Store variable with position and name
                                    $template_variables[$index + 1] = array(
                                        'position' => $index + 1,
                                        'name' => $var['text'],
                                        'placeholder' => isset($var['value']) ? $var['value'] : 'Variable ' . ($index + 1)
                                    );
                                }
                            }
                        }
                        break;
                        
                    case 'button':
                        $has_buttons = true;
                        $button_components[] = $component;
                        break;
                }
            }
        }
        
        // Debug information
        error_log('Template components: Header=' . ($has_header ? 'yes' : 'no') . 
                  ', Body=' . ($has_body ? 'yes' : 'no') . 
                  ', Buttons=' . ($has_buttons ? 'yes' : 'no'));
        error_log('Template variables: ' . json_encode($template_variables));
        
        // Build the variables array with the new name=>value format
        $variables = array();
        $has_cta_link = false;
        
        // Check if we have associative array (named parameters)
        $is_associative = is_array($template_params) && 
                         array_keys($template_params) !== range(0, count($template_params) - 1);
                         
        if ($is_associative) {
            // Handle recipient name if present
            if (isset($template_params['name'])) {
                $body['receipentName'] = $template_params['name'];
            }
            
            // Check for CTA link first
            if (isset($template_params['cta_link']) && !empty($template_params['cta_link'])) {
                $has_cta_link = true;
                // Add cta_link in the new format
                $variables['cta_link'] = $template_params['cta_link'];
            }
            
            // Map parameters to template variables
            if (!empty($template_variables)) {
                foreach ($template_variables as $position => $var_info) {
                    $var_name = strtolower($var_info['name']);
                    
                    // Look for parameters matching variable name pattern (custom1, custom2, etc)
                    $found = false;
                    
                    // Try multiple variations of the parameter name
                    $possible_names = array(
                        $var_info['name'],                     // Original case (Custom1)
                        strtolower($var_info['name']),         // Lowercase (custom1)
                        'variable_' . $position,               // variable_1
                        'var' . $position,                     // var1
                        'custom' . $position,                  // custom1
                        'variable' . $position                 // variable1
                    );
                    
                    foreach ($possible_names as $param_name) {
                        if (isset($template_params[$param_name]) && !empty($template_params[$param_name])) {
                            // Use the new name=>value format
                            $variables[strtolower($var_info['name'])] = $template_params[$param_name];
                            $found = true;
                            break;
                        }
                    }
                    
                    // Debug logging for parameter matching
                    error_log("Looking for template variable {$var_info['name']} (position $position) - Found: " . ($found ? 'yes' : 'no'));
                    error_log("Available parameters: " . json_encode(array_keys($template_params)));
                    
                    // If no matching parameter found, add placeholder
                    if (!$found) {
                        // Use the new name=>value format
                        $variables[strtolower($var_info['name'])] = $var_info['placeholder'];
                    }
                }
            } else {
                // If we couldn't extract structured variables, fall back to adding all params
                foreach ($template_params as $key => $value) {
                    if (!empty($value) && $key !== 'name' && $key !== 'cta_link') {
                        // Use the new name=>value format
                        $variables[strtolower($key)] = $value;
                    }
                }
            }
            
            // Add variables to request body
            if (!empty($variables)) {
                $body['variables'] = $variables;
            }
            
            // Add buttons if template has them and we have CTA link
            if ($has_buttons && !empty($button_components)) {
                $buttons = array();
                
                foreach ($button_components as $component) {
                    if (isset($component['buttons']) && is_array($component['buttons'])) {
                        foreach ($component['buttons'] as $btn) {
                            if (isset($btn['type']) && $btn['type'] === 'URL') {
                                // Only add URL button if we have a CTA link or default URL
                                if ($has_cta_link || isset($btn['url'])) {
                                    $buttons[] = array(
                                        'text' => isset($btn['text']) ? $btn['text'] : 'Visit Link',
                                        'type' => 'link'
                                    );
                                }
                            } else if (isset($btn['type']) && $btn['type'] === 'PHONE_NUMBER') {
                                if (isset($template_params['cta_phone']) && !empty($template_params['cta_phone'])) {
                                    $buttons[] = array(
                                        'text' => isset($btn['text']) ? $btn['text'] : 'Call Now',
                                        'type' => 'phone'
                                    );
                                }
                            }
                        }
                    }
                }
                
                // Add buttons to request if any exist
                if (!empty($buttons)) {
                    $body['buttons'] = $buttons;
                }
            }
        } 
        // Handle sequential array input (from test modal)
        else if (is_array($template_params)) {
            // Log the raw parameters for debugging
            error_log('Processing sequential array input (test modal): ' . json_encode($template_params));
            
            // For test modal input, ensure all variables are added
            // Variables should be named Custom1, Custom2, etc. according to template
            if (!empty($template_variables)) {
                // Process all parameters that are provided
                foreach ($template_params as $index => $value) {
                    $position = $index + 1;
                    if (isset($template_variables[$position]) && !empty($value)) {
                        // Use the new name=>value format
                        $variables[strtolower($template_variables[$position]['name'])] = $value;
                    }
                }
                
                // Make sure all template variables have a value (even if not provided)
                // This ensures all expected variables are included
                foreach ($template_variables as $position => $var_info) {
                    $var_name = strtolower($var_info['name']);
                    if (!isset($variables[$var_name])) {
                        // Use the new name=>value format
                        $variables[$var_name] = $var_info['placeholder'];
                    }
                }
                
                // Handle special case for CTA link (typically the last input in the test modal)
                $last_index = count($template_params) - 1;
                if (isset($template_params[$last_index]) && !empty($template_params[$last_index])) {
                    // Check if this is a URL by simple validation
                    $potential_url = $template_params[$last_index];
                    if (filter_var($potential_url, FILTER_VALIDATE_URL) !== false ||
                        strpos($potential_url, 'http://') === 0 ||
                        strpos($potential_url, 'https://') === 0) {
                        
                        // Add as cta_link and mark that we have a CTA link
                        $variables['cta_link'] = $potential_url;
                        $has_cta_link = true;
                        
                        // Find and add any button components
                        if ($has_buttons && !empty($button_components)) {
                            $buttons = array();
                            foreach ($button_components as $component) {
                                if (isset($component['buttons']) && is_array($component['buttons'])) {
                                    foreach ($component['buttons'] as $btn) {
                                        if (isset($btn['type']) && $btn['type'] === 'URL') {
                                            $buttons[] = array(
                                                'text' => isset($btn['text']) ? $btn['text'] : 'Visit Link',
                                                'type' => 'link'
                                            );
                                            break; // Only add one button
                                        }
                                    }
                                }
                            }
                            
                            // Add buttons to request
                            if (!empty($buttons)) {
                                $body['buttons'] = $buttons;
                            }
                        }
                    }
                }
            } else {
                // Fallback if template variables not available
                foreach ($template_params as $index => $value) {
                    if (!empty($value)) {
                        // Use the new name=>value format
                        $variables['variable_' . ($index + 1)] = $value;
                    }
                }
            }
            
            if (!empty($variables)) {
                $body['variables'] = $variables;
            }
        }
        
        // Add debug logging for the request
        error_log('Wabot API request to ' . $api_url . ': ' . json_encode($body));
    
        // Make the POST request
        $response = wp_remote_post( $api_url, array(
            'method'  => 'POST',
            'timeout' => 45,
            'headers' => $headers,
            'body'    => json_encode( $body ),
        ) );
    
        // Handle the response
        if ( is_wp_error( $response ) ) {
            error_log( 'Wabot API Error: ' . $response->get_error_message() );
            return false;
        }
    
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        error_log('Wabot API response: ' . wp_remote_retrieve_body( $response ));
    
        if ( isset( $data['error'] ) ) {
            error_log( 'Wabot API Error: ' . $data['error'] );
            return false;
        }
    
        return $data;
    }

    // Helper function to get template details including components
    private function get_template_details($template_name) {
        // First try to get the template from cache
        $cached_templates = get_transient('wabot_template_details');
        if ($cached_templates && isset($cached_templates[$template_name])) {
            return $cached_templates[$template_name];
        }
        
        // Get all templates
        $templates = $this->get_templates();
        if (!$templates || !is_array($templates)) {
            return false;
        }
        
        // Find the specific template
        foreach ($templates as $template) {
            if (isset($template['name']) && $template['name'] === $template_name) {
                // Store in cache for future requests
                if (!$cached_templates) {
                    $cached_templates = array();
                }
                $cached_templates[$template_name] = $template;
                set_transient('wabot_template_details', $cached_templates, 3600); // Cache for 1 hour
                
                return $template;
            }
        }
        
        return false;
    }
}
