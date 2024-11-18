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

        ///print_r($template_params); exit;
    
        if ( empty( $this->token ) ) {
            error_log( 'Wabot API Error: Unable to authenticate.' );
            return false;
        }
    
        // API endpoint
        $api_url = 'https://api.wabot.shop/send-message/455428624312178';
    
        // Headers for the request
        $headers = array(
            'Content-Type'  => 'application/json',
            'Authorization' => $this->token,
        );
    
        // Body for the request
        $body = array(
            'to'            => $to, // Validated phone number
            'templateName'  => $template_name,
            'receipentName' => isset( $template_params['name'] ) ? $template_params['name'] : '',
        );
    
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
    
        if ( isset( $data['error'] ) ) {
            error_log( 'Wabot API Error: ' . $data['error'] );
            return false;
        }
    
        return $data;
    }

    public function process_template_components($template) {
        $variables = [];
    
        foreach ($template['components'] as $component) {
            if (isset($component['childComponents'])) {
                foreach ($component['childComponents'] as $child) {
                    if ($child['type'] === 'variable') {
                        $variables[] = [
                            'name' => $child['text'], // Variable name
                            'value' => $child['value'], // Default value if any
                        ];
                    }
                }
            }
        }
    
        return $variables;
    }
    
        
}
