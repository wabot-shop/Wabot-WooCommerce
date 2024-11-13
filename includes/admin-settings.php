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

    // Add more fields to "Other Settings" as needed...
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
    ?>
    <div class="wrap">
        <h1>Wabot Settings</h1>

        <?php
        // Display any settings errors or success messages
        if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) {
            add_settings_error( 'wabot_messages', 'wabot_message', 'Settings Saved', 'updated' );
        }
        settings_errors( 'wabot_messages' );
        ?>

        <!-- Tab Navigation -->
        <h2 class="nav-tab-wrapper">
            <a href="?page=wabot-settings&tab=credentials" class="nav-tab <?php echo $tab == 'credentials' ? 'nav-tab-active' : ''; ?>">Credentials</a>
            <a href="?page=wabot-settings&tab=templates" class="nav-tab <?php echo $tab == 'templates' ? 'nav-tab-active' : ''; ?>">Templates</a>
            <a href="?page=wabot-settings&tab=other_settings" class="nav-tab <?php echo $tab == 'other_settings' ? 'nav-tab-active' : ''; ?>">Other Settings</a>
        </h2>

        <form action="options.php" method="post">
            <?php
            if ( $tab == 'credentials' ) {
                settings_fields( 'wabot_settings_credentials_group' );
                do_settings_sections( 'wabot-settings-credentials' );
            } elseif ( $tab == 'templates' ) {
                settings_fields( 'wabot_settings_templates_group' );
                do_settings_sections( 'wabot-settings-templates' );
            } elseif ( $tab == 'other_settings' ) {
                settings_fields( 'wabot_settings_other_group' );
                do_settings_sections( 'wabot-settings-other' );
            }

            // Include the current tab in the form submission
            echo '<input type="hidden" name="tab" value="' . esc_attr( $tab ) . '" />';

            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Render functions for Credentials Tab
function wabot_api_key_render( $args ) {
    $options = get_option( 'wabot_settings_credentials' );
    $description = isset( $args['description'] ) ? $args['description'] : '';
    ?>
    <input type='text' name='wabot_settings_credentials[wabot_api_key]' value='<?php echo esc_attr( $options['wabot_api_key'] ?? '' ); ?>' />
    <?php if ( $description ) : ?>
        <p class="description"><?php echo esc_html( $description ); ?></p>
    <?php endif; ?>
    <?php
}

function wabot_api_secret_render( $args ) {
    $options = get_option( 'wabot_settings_credentials' );
    $description = isset( $args['description'] ) ? $args['description'] : '';
    ?>
    <input type='text' name='wabot_settings_credentials[wabot_api_secret]' value='<?php echo esc_attr( $options['wabot_api_secret'] ?? '' ); ?>' />
    <?php if ( $description ) : ?>
        <p class="description"><?php echo esc_html( $description ); ?></p>
    <?php endif; ?>
    <?php
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

function wabot_template_render( $args ) {
    $options = get_option( 'wabot_settings_templates' );
    $key = $args['key'];
    $selected_template = $options["wabot_template_$key"] ?? '';
    $description = isset( $args['description'] ) ? $args['description'] : '';

    // Get templates
    $templates = wabot_get_templates();

    if ( ! empty( $templates ) ) {
        ?>
        <select name="wabot_settings_templates[wabot_template_<?php echo esc_attr( $key ); ?>]">
            <option value="">Select a Template</option>
            <?php foreach ( $templates as $template ) : 
                $template_id = $template['template_id'] ?? '';
                $template_name = $template['name'] ?? '';
                if ( $template_id && $template_name ) :
            ?>
                <option value="<?php echo esc_attr( $template_id ); ?>" <?php selected( $selected_template, $template_id ); ?>>
                    <?php echo esc_html( $template_name ); ?>
                </option>
            <?php 
                endif;
            endforeach; ?>
        </select>
        <?php echo "<button type='button' class='button wabot-preview-button' data-key='$key'>Preview</button>"; ?>
        <?php echo "<button type='button' class='button wabot-test-button' data-key='$key'>Test</button>"; ?>
        
        <div id="wabot-template-preview-modal" class="wabot-modal">
            <div class="wabot-modal-content">
            <div class="modal-header">
            <h2>Template Preview</h2>
            <button type="button" id="close-preview-modal" class="close-button">×</button>
        </div>
            <div id="template-preview-container"></div>
            </div>
        </div>


        <?php if ( $description ) : ?>
            <p class="description"><?php echo esc_html( $description ); ?></p>
        <?php endif; ?>
        <?php
    } else {
        ?>
        <p class="description">Could not retrieve templates. Please check your API credentials.</p>
        <?php
    }
}

// Render functions for Other Settings Tab
function wabot_abandonment_time_render( $args ) {
    $options = get_option( 'wabot_settings_other' );
    $description = isset( $args['description'] ) ? $args['description'] : '';
    ?>
    <input type='number' name='wabot_settings_other[wabot_abandonment_time]' value='<?php echo esc_attr( $options['wabot_abandonment_time'] ?? '60' ); ?>' min="1" />
    <?php if ( $description ) : ?>
        <p class="description"><?php echo esc_html( $description ); ?></p>
    <?php endif; ?>
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
    $template_params = isset( $_POST['template_params'] ) ? array_map( 'sanitize_text_field', $_POST['template_params'] ) : array();

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

    // Simulate fetching template details from the Wabot API
    $template_data = [
        'name' => $template_name,
        'components' => [
            [
                'type' => 'header',
                'content' => 'Finalize account set-up',
            ],
            [
                'type' => 'body',
                'content' => 'Hi {{1}},\n\nYour new account has been created successfully. \n\nPlease verify {{2}} to complete your profile.',
            ],
            [
                'type' => 'button',
                'content' => 'Verify account',
                'url' => 'https://app.wabot.shop/',
            ],
        ],
    ];

    wp_send_json_success($template_data);
}
add_action('wp_ajax_wabot_get_template_preview', 'wabot_get_template_preview');
