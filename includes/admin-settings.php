<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Add settings menu
add_action( 'admin_menu', 'wabot_add_admin_menu' );
function wabot_add_admin_menu() {
    // Get the plugin URL
    $plugin_url = plugin_dir_url( dirname( __FILE__ ) );

    // Define the icon URL
    $icon_url = $plugin_url . 'assets/wabot-icon.png';

    add_menu_page(
        'Wabot Settings',
        'Wabot Settings',
        'manage_options',
        'wabot-settings',
        'wabot_settings_page',
        $icon_url,
        8
    );
}

// Register settings
add_action( 'admin_init', 'wabot_settings_init' );
function wabot_settings_init() {
    // Credentials Tab
    register_setting( 'wabot_settings_credentials_group', 'wabot_settings_credentials' );

    add_settings_section(
        'wabot_api_section',
        'API Credentials',
        null,
        'wabot-settings-credentials'
    );

    add_settings_field(
        'wabot_api_key',
        'API Key',
        'wabot_api_key_render',
        'wabot-settings-credentials',
        'wabot_api_section',
        array(
            'description' => 'Enter your Wabot API Key here. You can find it in your Wabot dashboard under API settings.',
        )
    );

    add_settings_field(
        'wabot_api_secret',
        'API Secret',
        'wabot_api_secret_render',
        'wabot-settings-credentials',
        'wabot_api_section',
        array(
            'description' => 'Enter your Wabot API Secret here. This is required for authenticating API requests.',
        )
    );

    // Templates Tab
    register_setting( 'wabot_settings_templates_group', 'wabot_settings_templates' );

    add_settings_section(
        'wabot_templates_section',
        'WhatsApp Templates',
        null,
        'wabot-settings-templates'
    );

    $notification_types = array(
        'new_user'       => array(
            'label'       => 'New User Registration',
            'description' => 'Select the WhatsApp template to send when a new user registers.',
        ),
        'password_reset' => array(
            'label'       => 'Password Reset',
            'description' => 'Select the WhatsApp template to send when a user resets their password.',
        ),
        'new_order'      => array(
            'label'       => 'New Order',
            'description' => 'Select the WhatsApp template to send when a new order is placed.',
        ),
        'order_status'   => array(
            'label'       => 'Order Status Update',
            'description' => 'Select the WhatsApp template to send when an order status is updated.',
        ),
        'abandoned_cart' => array(
            'label'       => 'Abandoned Cart',
            'description' => 'Select the WhatsApp template to send for abandoned cart recovery.',
        ),
    );

    foreach ( $notification_types as $key => $data ) {
        add_settings_field(
            "wabot_template_$key",
            $data['label'],
            'wabot_template_render',
            'wabot-settings-templates',
            'wabot_templates_section',
            array(
                'key'         => $key,
                'description' => $data['description'],
            )
        );
    }

    // Other Settings Tab
    register_setting( 'wabot_settings_other_group', 'wabot_settings_other' );

    add_settings_section(
        'wabot_other_settings_section',
        'Other Settings',
        null,
        'wabot-settings-other'
    );

    add_settings_field(
        'wabot_abandonment_time',
        'Cart Abandonment Time (minutes)',
        'wabot_abandonment_time_render',
        'wabot-settings-other',
        'wabot_other_settings_section',
        array(
            'description' => 'Set the number of minutes after which a cart is considered abandoned. Default is 60 minutes.',
        )
    );

        // Profile Settings Tab
        register_setting( 'wabot_settings_profile_group', 'wabot_settings_profile' );

        add_settings_section(
            'wabot_profile_section',
            'Profile Settings',
            null,
            'wabot-settings-profile'
        );
    
        add_settings_field(
            'wabot_active_phone',
            'Active Phone Number',
            'wabot_active_phone_render',
            'wabot-settings-profile',
            'wabot_profile_section',
            array(
                'description' => 'Select the active phone number to be used for WhatsApp messages.',
            )
        );
    
    

}

function wabot_active_phone_render( $args ) {
    $account = wabot_get_phonenumbers();
    $phone_numbers = isset($account['phone_numbers']) ? $account['phone_numbers'] : [];
    $active_phone  = get_option( 'wabot_settings_profile' )['active_phone'] ?? '';
    
    echo '<div class="wabot-form-group">';
    echo '<select name="wabot_settings_profile[active_phone]" id="wabot_active_phone" onchange="updateVerifiedName()">';
    echo '<option value="" > Select Whatsapp Phone number</option>';

    foreach ( $phone_numbers as $phone ) {
        $selected = selected( $phone['whatsappPhoneId'], $active_phone, false );
        if ( $phone['whatsappPhoneId'] === $active_phone ) {
            $active_verified_name = $phone['verified_name'];
        }

        echo '<option 
        value="' . esc_attr( $phone['whatsappPhoneId'] ) . '" 
        data-verified-name="' . esc_attr( $phone['verified_name'] ) . '" ' . $selected . '>
        ' . esc_html( $phone['phone_number'] ) . 
        '</option>';
    }
    echo '</select>';

    echo '<div id="verified_name_display" style="margin-top: 10px;">' . 
        ( $active_verified_name ? 'Verified Name: ' . esc_html( $active_verified_name ) : '' ) . 
        '</div>';

    if ( isset( $args['description'] ) ) {
        echo '<p class="wabot-form-description">' . esc_html( $args['description'] ) . '</p>';
    }
    echo '</div>';

   //  JavaScript to dynamically update verified name
   echo '<script>
   document.addEventListener("DOMContentLoaded", function() {
       const select = document.getElementById("wabot_active_phone");
       const display = document.getElementById("verified_name_display");

       function updateVerifiedName() {
           const verifiedName = select.options[select.selectedIndex]?.getAttribute("data-verified-name");
           display.textContent = verifiedName ? "Verified Name: " + verifiedName : "";
       }

       select.addEventListener("change", updateVerifiedName);

       // Initialize on page load
       updateVerifiedName();
   });
</script>';
}


// Redirect filter to include the tab parameter after saving settings
add_filter( 'redirect_post_location', 'wabot_settings_redirect', 10, 2 );
function wabot_settings_redirect( $location, $status ) {
    if ( isset( $_POST['option_page'] ) && strpos( $_POST['option_page'], 'wabot_settings_' ) === 0 ) {
        $tab = isset( $_POST['tab'] ) ? sanitize_text_field( $_POST['tab'] ) : 'credentials';
        $location = add_query_arg( array( 'tab' => $tab ), $location );
    }
    return $location;
}

// Settings page content with tabs
function wabot_settings_page() {
    // Get current tab
    $tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'credentials';
    
    // Enqueue styles and scripts
    wp_enqueue_style('wabot-admin-style', plugin_dir_url(dirname(__FILE__)) . 'css/wabot-admin.css', array(), '1.0.0');
    wp_enqueue_script('wabot-admin-script', plugin_dir_url(dirname(__FILE__)) . 'js/wabot-admin.js', array('jquery'), '1.0.0', true);
    
    // Localize script with ajax URL
    wp_localize_script('wabot-admin-script', 'wabotAdmin', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wabot_admin_nonce')
    ));
    
    // Get API credentials for status display
    $credentials = get_option('wabot_settings_credentials');
    $api_key = $credentials['wabot_api_key'] ?? '';
    $api_secret = $credentials['wabot_api_secret'] ?? '';
    $connection_status = (!empty($api_key) && !empty($api_secret)) ? 'connected' : 'disconnected';
    
    // Get stats
    $account = wabot_get_phonenumbers();
    $phone_numbers = isset($account['phone_numbers']) ? $account['phone_numbers'] : [];
    $phone_count = count($phone_numbers);
    
    // Get templates
    $templates = wabot_get_templates();
    $template_count = is_array($templates) ? count($templates) : 0;
    
    // Get abandoned cart count
    global $wpdb;
    $abandoned_carts_table = $wpdb->prefix . 'wabot_abandoned_carts';
    $abandoned_count = 0;
    
    if($wpdb->get_var("SHOW TABLES LIKE '$abandoned_carts_table'") == $abandoned_carts_table) {
        $abandoned_count = $wpdb->get_var("SELECT COUNT(*) FROM $abandoned_carts_table");
    }
    ?>
    <div class="wrap wabot-settings-wrap">
        <h1 style="padding: 20px 20px 0; margin-bottom:20px;display: flex; align-items: center;">
            <img src="<?php echo plugin_dir_url(dirname(__FILE__)) . 'assets/wabot-icon.png'; ?>" alt="Wabot Logo" style="width: 32px; height: 32px; margin-right: 10px;">
            Wabot WhatsApp Integration
        </h1>

        <?php
        // Display any settings errors or success messages
        if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) {
            add_settings_error( 'wabot_messages', 'wabot_message', 'Settings Saved', 'updated' );
        }
        settings_errors( 'wabot_messages' );
        ?>

        <!-- Dashboard Stats -->
        <div class="wabot-dashboard">
            <div class="wabot-stats-card">
                <h3>Connection Status</h3>
                <div class="wabot-stats-value">
                    <span class="wabot-connection-status <?php echo $connection_status; ?>">
                        <?php echo $connection_status === 'connected' ? 'Connected' : 'Disconnected'; ?>
                    </span>
                </div>
            </div>
            
            <div class="wabot-stats-card">
                <h3>WhatsApp Numbers</h3>
                <div class="wabot-stats-value"><?php echo $phone_count; ?></div>
            </div>
            
            <div class="wabot-stats-card">
                <h3>Available Templates</h3>
                <div class="wabot-stats-value"><?php echo $template_count; ?></div>
            </div>
            
            <div class="wabot-stats-card">
                <h3>Abandoned Carts</h3>
                <div class="wabot-stats-value"><?php echo $abandoned_count; ?></div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <h2 class="wabot-nav-tab-wrapper">
            <a href="?page=wabot-settings&tab=credentials" class="wabot-nav-tab nav-tab <?php echo $tab == 'credentials' ? 'nav-tab-active' : ''; ?>">Credentials</a>
            <a href="?page=wabot-settings&tab=profile" class="wabot-nav-tab nav-tab <?php echo $tab == 'profile' ? 'nav-tab-active' : ''; ?>">Profile</a>
            <a href="?page=wabot-settings&tab=templates" class="wabot-nav-tab nav-tab <?php echo $tab == 'templates' ? 'nav-tab-active' : ''; ?>">Templates</a>
            <a href="?page=wabot-settings&tab=other_settings" class="wabot-nav-tab nav-tab <?php echo $tab == 'other_settings' ? 'nav-tab-active' : ''; ?>">Settings</a>
        </h2>

        <div class="wabot-form-container">
            <?php if ($tab == 'templates'): ?>
                <div style="margin-bottom: 20px;">
                    <button type="button" id="debug-templates-btn" class="wabot-button secondary">
                        <span class="dashicons dashicons-code-standards" style="margin-right: 5px;"></span> Debug Templates
                    </button>
                    <span class="wabot-form-description" style="margin-left: 10px; display: inline-block;">
                        Click to console log template data from API for debugging
                    </span>
                </div>
            <?php endif; ?>

            <form action="options.php" method="post">
                <?php
                if ( $tab == 'credentials' ) {
                    settings_fields( 'wabot_settings_credentials_group' );
                    
                    echo '<div class="wabot-form-section">';
                    echo '<div class="wabot-form-header"><h2>API Credentials</h2></div>';
                    echo '<div class="wabot-form-body">';
                    do_settings_sections( 'wabot-settings-credentials' );
                    echo '</div></div>';
                    
                } elseif ( $tab == 'profile' ) {
                    settings_fields( 'wabot_settings_profile_group' );
                    
                    echo '<div class="wabot-form-section">';
                    echo '<div class="wabot-form-header"><h2>Profile Settings</h2></div>';
                    echo '<div class="wabot-form-body">';
                    do_settings_sections( 'wabot-settings-profile' );
                    echo '</div></div>';
                    
                } elseif ( $tab == 'templates' ) {
                    settings_fields( 'wabot_settings_templates_group' );
                    
                    echo '<div class="wabot-form-section">';
                    echo '<div class="wabot-form-header"><h2>WhatsApp Templates</h2></div>';
                    echo '<div class="wabot-form-body">';
                    do_settings_sections( 'wabot-settings-templates' );
                    echo '</div></div>';
                    
                } elseif ( $tab == 'other_settings' ) {
                    settings_fields( 'wabot_settings_other_group' );
                    
                    echo '<div class="wabot-form-section">';
                    echo '<div class="wabot-form-header"><h2>Other Settings</h2></div>';
                    echo '<div class="wabot-form-body">';
                    do_settings_sections( 'wabot-settings-other' );
                    echo '</div></div>';
                }

                // Include the current tab in the form submission
                echo '<input type="hidden" name="tab" value="' . esc_attr( $tab ) . '" />';

                submit_button('Save Settings', 'wabot-button');
                ?>
            </form>
        </div>
    </div>
    <?php
}

// Render functions for Credentials Tab
function wabot_api_key_render( $args ) {
    $options = get_option( 'wabot_settings_credentials' );
    $description = isset( $args['description'] ) ? $args['description'] : '';
    ?>
    <div class="wabot-form-group">
        <input type='text' class="regular-text" name='wabot_settings_credentials[wabot_api_key]' value='<?php echo esc_attr( $options['wabot_api_key'] ?? '' ); ?>' />
        <?php if ( $description ) : ?>
            <p class="wabot-form-description"><?php echo esc_html( $description ); ?></p>
        <?php endif; ?>
    </div>
    <?php
}

function wabot_api_secret_render( $args ) {
    $options = get_option( 'wabot_settings_credentials' );
    $description = isset( $args['description'] ) ? $args['description'] : '';
    ?>
    <div class="wabot-form-group">
        <input type='text' class="regular-text" name='wabot_settings_credentials[wabot_api_secret]' value='<?php echo esc_attr( $options['wabot_api_secret'] ?? '' ); ?>' />
        <?php if ( $description ) : ?>
            <p class="wabot-form-description"><?php echo esc_html( $description ); ?></p>
        <?php endif; ?>
    </div>
    <?php
}

function wabot_get_phonenumbers() {
    // Get API credentials
    $credentials = get_option( 'wabot_settings_credentials' );
    $api_key = $credentials['wabot_api_key'] ?? '';
    $api_secret = $credentials['wabot_api_secret'] ?? '';

    if ( empty( $api_key ) || empty( $api_secret ) ) {
        return false; // API credentials are missing
    }

    // Check if account are cached
    $account_details = get_transient( 'wabot_account' );

    if ( false === $account_details ) {
        // Account not cached, fetch from API
        $wabot = new Wabot_API();
        $account_details = $wabot->get_account();

        if ( $account_details ) {
            // Cache the account details for 12 hours
            set_transient( 'wabot_account', $account_details, 12 * HOUR_IN_SECONDS );
        } else {
            // Handle error
            $account_details = array();
        }
    }

    return $account_details;
}

// Function to get templates with caching
function wabot_get_templates() {
    // Get API credentials
    $credentials = get_option( 'wabot_settings_credentials' );
    $api_key = $credentials['wabot_api_key'] ?? '';
    $api_secret = $credentials['wabot_api_secret'] ?? '';

    if ( empty( $api_key ) || empty( $api_secret ) ) {
        return false; // API credentials are missing
    }

    // Check if templates are cached
    $templates = get_transient( 'wabot_templates' );

    if ( false === $templates ) {
        // Templates not cached, fetch from API
        $wabot = new Wabot_API();
        $templates = $wabot->get_templates();

        if ( $templates ) {
            // Cache the templates for 12 hours
            set_transient( 'wabot_templates', $templates, 12 * HOUR_IN_SECONDS );
        } else {
            // Handle error
            $templates = array();
        }
    }

    return $templates;
}

function wabot_get_single_template($template) {
    $templatesArray = wabot_get_templates();

    // Check if decoding was successful
    if ($templatesArray === null) {
        die('Error decoding JSON');
    }

    if ($template === null || $template == "") {
        die('Invalid Template');
    }

    foreach ($templatesArray as $templates) {
       
        if ($templates['name'] == $template) {
            $desiredTemplate = $templates;
            break;
        }
    }

    if ($desiredTemplate !== null) {
        return $desiredTemplate;
    }
    else {
        return "";
    }

    

}



function wabot_extractComponents($originalArray, $type = 'variables') {
    $result = [];

    foreach ($originalArray['components'] as $component) {
        if ($type === 'variables' && isset($component['type']) && $component['type'] === 'body') {
            // Extract variables from the body
            $variables = [];
            foreach ($component['childComponents'] as $child) {
                if ($child['type'] === 'variable') {
                    $variables[] = [
                        'text' => $child['text'],
                        'value' => $child['value'],
                    ];
                }
            }

            if (!empty($variables)) {
                $result = array_merge($result, $variables);
            }
        }

        if ($type === 'call_to_actions' && isset($component['type']) && $component['type'] === 'button') {
            // Extract call-to-action headers from buttons
            foreach ($component['childComponents'] as $child) {
                if ($child['type'] === 'button') {
                    $result[] = [
                        'text' => $child['text'],
                        'type' => $child['type'],
                        'value' => $child['value'],
                        'url'   => $child['url'],
                    ];
                }
            }
        }

        if ($type === 'header' && isset($component['type']) && $component['type'] === 'header') {
            // Extract header content
            foreach ($component['childComponents'] as $child) {
                $headerContent = [];
                switch ($child['type']) {
                    case 'text':
                        $headerContent = [
                            'content_type' => 'text',
                            'content' => $child['text'],
                        ];
                        break;
                    case 'video':
                        $headerContent = [
                            'content_type' => 'video',
                            'content' => $child['video_link'],
                        ];
                        break;
                    case 'document':
                        $headerContent = [
                            'content_type' => 'document',
                            'content' => $child['document_link'],
                        ];
                        break;
                    case 'image':
                        $headerContent = [
                            'content_type' => 'image',
                            'content' => $child['image_link'],
                        ];
                        break;
                }

                if (!empty($headerContent)) {
                    $result[] = $headerContent;
                }
            }
        }
    }

    return $result;
}






function wabot_convertToDesiredFormat($originalArray) {
    // Initialize the new array structure
    $newArray = [
        'name' => $originalArray['name'] ?? '',
        'components' => [],
    ];

    // Extract components array if it exists
    $components = [];
    if (isset($originalArray['components']) && is_array($originalArray['components'])) {
        $components = $originalArray['components'];
    } else {
        // Check if the original array itself is a list of components
        foreach ($originalArray as $key => $value) {
            if (is_array($value) && isset($value['type']) && isset($value['childComponents'])) {
                $components[] = $value;
            }
        }
    }

    // If no components were found, return a basic structure
    if (empty($components)) {
        error_log('No components found in template data');
        return $newArray;
    }

    // Loop through each component
    foreach ($components as $component) {
        $newComponent = [];

        // Process components based on their type
        switch ($component['type']) {
            case 'header':
                $content = '';
                $contentType = '';

                // Loop through child components for headers
                if (isset($component['childComponents']) && is_array($component['childComponents'])) {
                    foreach ($component['childComponents'] as $child) {
                        switch ($child['type']) {
                            case 'text':
                                $content = $child['text'];
                                $contentType = 'text';
                                break;
                            case 'video':
                                $content = $child['video_link'];
                                $contentType = 'video';
                                break;
                            case 'document':
                                $content = $child['document_link'];
                                $contentType = 'document';
                                break;
                            case 'image':
                                $content = $child['image_link'];
                                $contentType = 'image';
                                break;
                        }
                    }
                }

                $newComponent = [
                    'type' => 'header',
                    'content_type' => $contentType,
                    'content' => $content,
                ];
                break;

            case 'body':
                $bodyContent = '';
                $variables = [];

                // Loop through child components for body content and variables
                if (isset($component['childComponents']) && is_array($component['childComponents'])) {
                    foreach ($component['childComponents'] as $child) {
                        if ($child['type'] === 'text') {
                            $bodyContent = $child['text'];
                        }
                        if ($child['type'] === 'variable') {
                            $variables[] = [
                                'text' => $child['text'],
                                'value' => $child['value'],
                            ];
                        }
                    }
                }

                $newComponent = [
                    'type' => 'body',
                    'content' => $bodyContent,
                ];

                // Add variables if present
                if (!empty($variables)) {
                    $newComponent['variables'] = $variables;
                }
                break;

            case 'button':
                $buttons = [];
                
                // Loop through child components for buttons
                if (isset($component['childComponents']) && is_array($component['childComponents'])) {
                    foreach ($component['childComponents'] as $child) {
                        // Handle CTA type (URL buttons)
                        if ($child['type'] === 'cta') {
                            $buttons[] = [
                                'text' => $child['value'],
                                'type' => $child['text'], // URL, PHONE_NUMBER, etc.
                                'value' => $child['value'],
                                'url' => $child['url'],
                            ];
                        }
                        // Handle regular button type
                        else if ($child['type'] === 'button') {
                            $buttons[] = [
                                'text' => $child['text'],
                                'type' => 'button',
                                'value' => $child['value'],
                                'url' => $child['url'] ?? '',
                            ];
                        }
                    }
                }
                
                if (!empty($buttons)) {
                    $newComponent = [
                        'type' => 'button',
                        'content' => $buttons[0]['text'],
                        'url' => $buttons[0]['url'] ?? '',
                        'buttons' => $buttons, // Store all buttons in case of multiple
                    ];
                }
                break;

            case 'carousel':
                $newComponent = [
                    'type' => 'carousel',
                    'content' => $component['carouselCards'] ?? [],
                ];
                break;
        }

        // Add processed component to the new array if it exists
        if (!empty($newComponent)) {
            $newArray['components'][] = $newComponent;
        }
    }

    return $newArray;
}


function wabot_template_render( $args ) {
    $options = get_option( 'wabot_settings_templates' );
    $key = $args['key'];
    $selected_template = $options["wabot_template_$key"] ?? '';
    $description = isset( $args['description'] ) ? $args['description'] : '';
    $is_enabled = isset($options["wabot_template_{$key}_enabled"]) ? $options["wabot_template_{$key}_enabled"] : '1'; // Default to enabled

    // Get templates
    $templates = wabot_get_templates();

    echo '<div class="wabot-form-group">';
    if ( ! empty( $templates ) ) {
        // Get the selected template name for display
        $selected_template_name = '';
        foreach ( $templates as $template ) {
            $template_name = $template['name'] ?? '';
            if ( $template_name === $selected_template ) {
                $selected_template_name = $template_name;
                break;
            }
        }
        
        // Create hidden input to store the selected template
        echo '<input type="hidden" name="wabot_settings_templates[wabot_template_' . esc_attr( $key ) . ']" id="template_' . esc_attr( $key ) . '_value" value="' . esc_attr( $selected_template ) . '">';
        
        // Add toggle switch for enabling/disabling the template
        echo '<div class="template-header">';
        echo '<div class="template-toggle-container">';
        echo '<label class="wabot-toggle-switch">';
        echo '<input type="checkbox" name="wabot_settings_templates[wabot_template_' . esc_attr( $key ) . '_enabled]" id="template_' . esc_attr( $key ) . '_enabled" value="1" ' . checked('1', $is_enabled, false) . '>';
        echo '<span class="wabot-toggle-slider"></span>';
        echo '</label>';
        echo '<label for="template_' . esc_attr( $key ) . '_enabled" class="wabot-toggle-label">' . ($is_enabled == '1' ? 'Enabled' : 'Disabled') . '</label>';
        echo '</div>';
        
        // Create template selector button
        echo '<div class="template-select-trigger' . ($is_enabled != '1' ? ' disabled' : '') . '" data-key="' . esc_attr( $key ) . '">';
        echo '<span class="template-select-trigger-text ' . (empty($selected_template) ? 'placeholder' : '') . '">' . 
            (empty($selected_template_name) ? 'Select a Template' : esc_html($selected_template_name)) . 
            '</span>';
        echo '<span class="dashicons dashicons-portfolio"></span>';
        echo '</div>';
        echo '</div>'; // Close template-header
        
        // Add preview and test buttons if template is selected
        if (!empty($selected_template)) {
            echo '<div class="template-actions' . ($is_enabled != '1' ? ' disabled' : '') . '" style="margin-top: 10px;">'; 
            echo "<button type='button' class='wabot-preview-button wabot-btn-icon" . ($is_enabled != '1' ? ' disabled' : '') . "' data-template='$key' data-key='$selected_template'><span class='dashicons dashicons-visibility'></span> Preview</button>";
            echo "<button type='button' class='wabot-test-button wabot-btn-icon" . ($is_enabled != '1' ? ' disabled' : '') . "' data-template='$key' data-key='$selected_template'><span class='dashicons dashicons-email-alt'></span> Test</button>";
            echo '</div>';
        }
        
        // Add description
        if ( $description ) : 
            echo '<p class="wabot-form-description">' . esc_html( $description ) . '</p>';
        endif;
    } else {
        echo '<p class="wabot-form-description">Could not retrieve templates. Please check your API credentials.</p>';
    }
    echo '</div>';
}

// Render functions for Other Settings Tab
function wabot_abandonment_time_render( $args ) {
    $options = get_option( 'wabot_settings_other' );
    $description = isset( $args['description'] ) ? $args['description'] : '';
    ?>
    <div class="wabot-form-group">
        <input type='number' name='wabot_settings_other[wabot_abandonment_time]' value='<?php echo esc_attr( $options['wabot_abandonment_time'] ?? '60' ); ?>' min="1" />
        <?php if ( $description ) : ?>
            <p class="wabot-form-description"><?php echo esc_html( $description ); ?></p>
        <?php endif; ?>
    </div>
    <?php
}

function wabot_send_message_ajax() {
    if ( ! isset( $_POST['to'], $_POST['template_name'] ) ) {
        wp_send_json_error( array( 'message' => 'Invalid parameters.' ) );
        return;
    }

    // Sanitize inputs
    $to = sanitize_text_field( $_POST['to'] );
    $template_name = sanitize_text_field( $_POST['template_name'] );
    
    // Process template parameters
    $template_params = array();
    
    if (isset($_POST['template_params'])) {
        // Check if it's a JSON string (from JavaScript)
        if (is_string($_POST['template_params']) && strpos($_POST['template_params'], '{') === 0) {
            $decoded = json_decode(stripslashes($_POST['template_params']), true);
            if ($decoded) {
                // Sanitize each parameter
                foreach ($decoded as $key => $value) {
                    $template_params[sanitize_text_field($key)] = sanitize_text_field($value);
                }
            }
        }
        // Check if it's an array
        else if (is_array($_POST['template_params'])) {
            // Check if it's an associative array
            $keys = array_keys($_POST['template_params']);
            $is_associative = count(array_filter($keys, 'is_string')) > 0;
            
            if ($is_associative) {
                // Sanitize each parameter for associative array
                foreach ($_POST['template_params'] as $key => $value) {
                    $template_params[sanitize_text_field($key)] = sanitize_text_field($value);
                }
            } else {
                // Sanitize each parameter for sequential array
                foreach ($_POST['template_params'] as $value) {
                    $template_params[] = sanitize_text_field($value);
                }
            }
        }
    }
    
    // Add debug log
    error_log('Sending message with parameters: ' . json_encode($template_params));

    // Call the send_message method of your API class
    $api_instance = new Wabot_API(); // Replace with your actual API class instantiation
    $response = $api_instance->send_message( $to, $template_name, $template_params );

    if ( $response ) {
        wp_send_json_success( array( 'message' => 'Message sent successfully.' ) );
    } else {
        wp_send_json_error( array( 'message' => 'Failed to send message.' ) );
    }
}
add_action( 'wp_ajax_wabot_send_message', 'wabot_send_message_ajax' );



function wabot_get_template_preview() {
    $template_name = isset($_POST['template_name']) ? sanitize_text_field($_POST['template_name']) : '';
    
    if (empty($template_name)) {
        wp_send_json_error(['message' => 'Template name is required']);
        return;
    }
    
    $template = wabot_get_single_template($template_name);
    
    if (empty($template)) {
        wp_send_json_error(['message' => 'Template not found']);
        return;
    }
    
    // Convert template to desired format
    $template_data = wabot_convertToDesiredFormat($template);
    
    // Debug log
    error_log('Template preview for: ' . $template_name);
    error_log('Original template: ' . json_encode($template));
    error_log('Converted template: ' . json_encode($template_data));
    
    // Ensure components are present
    if (empty($template_data['components'])) {
        // Fallback to a basic structure if conversion fails
        $template_data = [
            'name' => $template_name,
            'components' => [
                [
                    'type' => 'body',
                    'content' => 'Template content could not be loaded properly.',
                ]
            ]
        ];
    }
    
    wp_send_json_success($template_data);
}
add_action('wp_ajax_wabot_get_template_preview', 'wabot_get_template_preview');

// Add the missing AJAX handler for getting all templates
function wabot_get_all_templates() {
    // Verify nonce if sent
    if (isset($_POST['nonce']) && !wp_verify_nonce($_POST['nonce'], 'wabot_admin_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed.'));
        return;
    }
    
    // Get templates from the existing function
    $templates = wabot_get_templates();
    
    if ($templates && is_array($templates)) {
        // Process templates to ensure they have the right format for display
        $formatted_templates = array();
        
        foreach ($templates as $template) {
            // Make sure each template has the necessary properties
            $formatted_template = $template;
            
            // If we need to convert or restructure the template data for the JS
            if (isset($template['name'])) {
                // Get the full template data with components
                $single_template = wabot_get_single_template($template['name']);
                
                if ($single_template) {
                    $formatted_template = wabot_convertToDesiredFormat($single_template);
                }
            }
            
            $formatted_templates[] = $formatted_template;
        }
        
        wp_send_json_success($formatted_templates);
    } else {
        wp_send_json_error(array('message' => 'Could not retrieve templates. Please check your API credentials.'));
    }
}
add_action('wp_ajax_wabot_get_all_templates', 'wabot_get_all_templates');





//$variables = wabot_extractComponents($originalArray, 'variables');
//print_r($variables);

/* Output:
Array
(
    [0] => Array
        (
            [text] => Name
            [value] => John Doe
        )
    [1] => Array
        (
            [text] => Order
            [value] => 12345
        )
)
*/



//$callToActions = wabot_extractComponents($originalArray, 'call_to_actions');
//print_r($callToActions);

/* Output:
Array
(
    [0] => Array
        (
            [text] => Buy Now
            [type] => button
            [value] => Purchase
            [url] => https://example.com/buy
        )
)
*/



//$header = wabot_extractComponents($originalArray, 'header');
//print_r($header);

/* Output:
Array
(
    [0] => Array
        (
            [content_type] => text
            [content] => Welcome to our service
        )
    [1] => Array
        (
            [content_type] => image
            [content] => https://example.com/image.jpg
        )
)
*/

// Add debug API function for console logging
function wabot_debug_templates() {
    check_ajax_referer('wabot_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied']);
        return;
    }

    // Get raw templates data
    $api = new Wabot_API();
    $raw_templates = $api->get_templates();
    
    // Get processed templates
    $processed_templates = [];
    if (is_array($raw_templates)) {
        foreach ($raw_templates as $template) {
            if (isset($template['name'])) {
                $single_template = wabot_get_single_template($template['name']);
                if ($single_template) {
                    $processed_templates[] = [
                        'raw' => $template,
                        'processed' => wabot_convertToDesiredFormat($single_template)
                    ];
                }
            }
        }
    }
    
    // Get active phone data
    $profile_settings = get_option('wabot_settings_profile', array());
    $phone_id = !empty($profile_settings['active_phone']) ? $profile_settings['active_phone'] : '';
    
    $debug_data = [
        'raw_templates' => $raw_templates,
        'processed_templates' => $processed_templates,
        'active_phone_id' => $phone_id,
        'api_endpoint' => 'https://api.wabot.shop/send-message/' . $phone_id,
    ];
    
    wp_send_json_success($debug_data);
}
add_action('wp_ajax_wabot_debug_templates', 'wabot_debug_templates');
