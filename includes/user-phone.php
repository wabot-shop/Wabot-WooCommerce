<?php
if (!defined('ABSPATH')) {
    exit;
}

class Wabot_User_Phone {
    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Add phone field to registration form
        add_action('register_form', array($this, 'add_phone_field_to_registration'));
        add_filter('registration_errors', array($this, 'validate_phone_field'), 10, 3);
        add_action('user_register', array($this, 'save_phone_field'));

        // Add phone field to user profile
        add_action('show_user_profile', array($this, 'add_phone_field_to_profile'));
        add_action('edit_user_profile', array($this, 'add_phone_field_to_profile'));
        add_action('personal_options_update', array($this, 'save_phone_field_profile'));
        add_action('edit_user_profile_update', array($this, 'save_phone_field_profile'));

        // Add phone field to WooCommerce registration
        add_action('woocommerce_register_form', array($this, 'add_phone_field_to_wc_registration'));
        add_filter('woocommerce_registration_errors', array($this, 'validate_wc_phone_field'), 10, 3);
        add_action('woocommerce_created_customer', array($this, 'save_phone_field'));

        // Add phone to account details
        add_action('woocommerce_edit_account_form', array($this, 'add_phone_field_to_wc_account'));
        add_action('woocommerce_save_account_details', array($this, 'save_phone_field_wc_account'));
        add_filter('woocommerce_save_account_details_required_fields', array($this, 'make_phone_field_required'));

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_phone_scripts'));
    }

    /**
     * Check if phone field is enabled
     */
    private function is_phone_field_enabled() {
        $options = get_option('wabot_settings_other', array());
        return isset($options['wabot_phone_field_enabled']) && $options['wabot_phone_field_enabled'] === '1';
    }

    /**
     * Add phone field to WordPress registration form
     */
    public function add_phone_field_to_registration() {
        if (!$this->is_phone_field_enabled()) return;
        ?>
        <p>
            <label for="phone_number"><?php esc_html_e('Phone Number', 'wabot-woocommerce'); ?><br/>
                <input type="tel" name="phone_number" id="phone_number" class="input wabot-phone-input" value="<?php echo isset($_POST['phone_number']) ? esc_attr($_POST['phone_number']) : ''; ?>" size="25" />
            </label>
        </p>
        <?php
    }

    /**
     * Add phone field to WooCommerce registration form
     */
    public function add_phone_field_to_wc_registration() {
        if (!$this->is_phone_field_enabled()) return;
        ?>
        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <label for="reg_phone_number"><?php esc_html_e('Phone Number', 'wabot-woocommerce'); ?>&nbsp;<span class="required">*</span></label>
            <input type="tel" class="woocommerce-Input woocommerce-Input--text input-text wabot-phone-input" name="phone_number" id="reg_phone_number" value="<?php echo isset($_POST['phone_number']) ? esc_attr($_POST['phone_number']) : ''; ?>" />
        </p>
        <?php
    }

    /**
     * Add phone field to user profile
     */
    public function add_phone_field_to_profile($user) {
        if (!$this->is_phone_field_enabled()) return;
        ?>
        <h3><?php esc_html_e('Phone Number', 'wabot-woocommerce'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="phone_number"><?php esc_html_e('Phone Number', 'wabot-woocommerce'); ?></label></th>
                <td>
                    <input type="tel" name="phone_number" id="phone_number" value="<?php echo esc_attr(get_user_meta($user->ID, 'phone_number', true)); ?>" class="regular-text wabot-phone-input" />
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Add phone field to WooCommerce account details
     */
    public function add_phone_field_to_wc_account() {
        if (!$this->is_phone_field_enabled()) return;
        $user_id = get_current_user_id();
        $phone = get_user_meta($user_id, 'phone_number', true);
        ?>
        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <label for="account_phone_number"><?php esc_html_e('Phone Number', 'wabot-woocommerce'); ?>&nbsp;<span class="required">*</span></label>
            <input type="tel" class="woocommerce-Input woocommerce-Input--text input-text wabot-phone-input" name="phone_number" id="account_phone_number" value="<?php echo esc_attr($phone); ?>" />
        </p>
        <?php
    }

    /**
     * Validate phone field on registration
     */
    public function validate_phone_field($errors, $sanitized_user_login, $user_email) {
        if ($this->is_phone_field_enabled()) {
            if (empty($_POST['phone_number'])) {
                $errors->add('phone_number_error', __('Please enter your phone number.', 'wabot-woocommerce'));
            } else {
                // The phone number will be validated by intl-tel-input on the frontend
                // Here we just ensure it's not empty and has a reasonable format
                $phone = sanitize_text_field($_POST['phone_number']);
                if (!preg_match('/^\+?[1-9]\d{1,14}$/', $phone)) {
                    $errors->add('phone_number_error', __('Please enter a valid international phone number.', 'wabot-woocommerce'));
                }
            }
        }
        return $errors;
    }

    /**
     * Validate phone field on WooCommerce registration
     */
    public function validate_wc_phone_field($errors, $username, $email) {
        if ($this->is_phone_field_enabled()) {
            if (empty($_POST['phone_number'])) {
                $errors->add('phone_number_error', __('Please enter your phone number.', 'wabot-woocommerce'));
            } else {
                // The phone number will be validated by intl-tel-input on the frontend
                // Here we just ensure it's not empty and has a reasonable format
                $phone = sanitize_text_field($_POST['phone_number']);
                if (!preg_match('/^\+?[1-9]\d{1,14}$/', $phone)) {
                    $errors->add('phone_number_error', __('Please enter a valid international phone number.', 'wabot-woocommerce'));
                }
            }
        }
        return $errors;
    }

    /**
     * Save phone field on registration
     */
    public function save_phone_field($user_id) {
        if ($this->is_phone_field_enabled() && isset($_POST['phone_number'])) {
            $phone = sanitize_text_field($_POST['phone_number']);
            // Store the full international format
            update_user_meta($user_id, 'phone_number', $phone);
        }
    }

    /**
     * Save phone field on profile update
     */
    public function save_phone_field_profile($user_id) {
        if ($this->is_phone_field_enabled() && isset($_POST['phone_number'])) {
            $phone = sanitize_text_field($_POST['phone_number']);
            // Store the full international format
            update_user_meta($user_id, 'phone_number', $phone);
        }
    }

    /**
     * Save phone field on WooCommerce account update
     */
    public function save_phone_field_wc_account($user_id) {
        if ($this->is_phone_field_enabled() && isset($_POST['phone_number'])) {
            $phone = sanitize_text_field($_POST['phone_number']);
            // Store the full international format
            update_user_meta($user_id, 'phone_number', $phone);
        }
    }

    /**
     * Make phone field required in WooCommerce account
     */
    public function make_phone_field_required($required_fields) {
        if ($this->is_phone_field_enabled()) {
            $required_fields['phone_number'] = __('Phone number is required.', 'wabot-woocommerce');
        }
        return $required_fields;
    }

    /**
     * Enqueue necessary scripts and styles for phone input
     */
    public function enqueue_phone_scripts() {
        if (!$this->is_phone_field_enabled()) return;

        // Enqueue intl-tel-input
        wp_enqueue_script('intl-tel-input', 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/intlTelInput.min.js', array('jquery'), '17.0.8', true);
        wp_enqueue_script('intl-tel-input-utils', 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/utils.js', array('intl-tel-input'), '17.0.8', true);
        wp_enqueue_style('intl-tel-input-css', 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/css/intlTelInput.min.css');

        // Enqueue our custom script
        wp_enqueue_script('wabot-phone-input', plugin_dir_url(dirname(__FILE__)) . 'js/wabot-phone-input.js', array('jquery', 'intl-tel-input'), '1.0', true);
        wp_enqueue_style('wabot-phone-input', plugin_dir_url(dirname(__FILE__)) . 'css/wabot-phone-input.css');

        // Add localized data
        wp_localize_script('wabot-phone-input', 'wabotPhone', array(
            'utilsScript' => 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/utils.js',
        ));
    }
}

// Initialize the user phone class
add_action('init', array('Wabot_User_Phone', 'get_instance')); 