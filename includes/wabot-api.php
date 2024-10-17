<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Wabot_API {

    private $api_key;
    private $api_secret;
    private $api_url = 'https://api.wabot.com/send'; // Replace with the actual API endpoint

    public function __construct() {
        $options = get_option( 'wabot_settings' );
        $this->api_key    = $options['wabot_api_key'] ?? '';
        $this->api_secret = $options['wabot_api_secret'] ?? '';
    }

    public function send_message( $phone_number, $template_id, $variables = array() ) {

        $body = array(
            'api_key'     => $this->api_key,
            'api_secret'  => $this->api_secret,
            'to'          => $phone_number,
            'template_id' => $template_id,
            'variables'   => $variables,
        );

        $response = wp_remote_post( $this->api_url, array(
            'method'      => 'POST',
            'timeout'     => 45,
            'headers'     => array( 'Content-Type' => 'application/json' ),
            'body'        => json_encode( $body ),
            'data_format' => 'body',
        ) );

        if ( is_wp_error( $response ) ) {
            error_log( 'Wabot API Error: ' . $response->get_error_message() );
            return false;
        }

        return json_decode( wp_remote_retrieve_body( $response ), true );
    }




      // Fetch templates
      public function get_templates() {
        $api_url = 'https://api.wabot.com/templates'; // Replace with the actual endpoint

        $body = array(
            'api_key'    => $this->api_key,
            'api_secret' => $this->api_secret,
        );

        $response = wp_remote_post( $api_url, array(
            'method'      => 'POST',
            'timeout'     => 45,
            'headers'     => array( 'Content-Type' => 'application/json' ),
            'body'        => json_encode( $body ),
            'data_format' => 'body',
        ) );

        if ( is_wp_error( $response ) ) {
            error_log( 'Wabot API Error (get_templates): ' . $response->get_error_message() );
            return false;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $data['templates'] ) ) {
            return $data['templates'];
        } else {
            return false;
        }
    }
}
