<?php
/**
 * The WooCommerce integration class
 * Adds honeypot
 * Check against OOPSpam API
 */
namespace OOPSPAM\WOOCOMMERCE;

if (!defined('ABSPATH')) {
    exit;
}
class WooSpamProtection
{
    private static $instance;

    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        $options = get_option('oopspamantispam_settings');
        $honeypot_enabled = isset($options['oopspam_woo_check_honeypot']) && $options['oopspam_woo_check_honeypot'] == 1;

        // Initialize actions & filters
        if ($honeypot_enabled) {
            add_action('woocommerce_register_form', [$this, 'oopspam_woocommerce_register_form'], 1, 0);
            add_action('woocommerce_after_checkout_billing_form', [$this, 'oopspam_woocommerce_register_form']);
            add_action('woocommerce_login_form', [$this, 'oopspam_woocommerce_login_form'], 1, 0);
        }
        
        // Always add these hooks as they handle both honeypot and API validation
        add_action('woocommerce_register_post', array($this, 'oopspam_process_registration'), 10, 3);
        add_action('woocommerce_process_registration_errors', [$this, 'oopspam_woocommerce_register_errors'], 10, 4);
        add_filter('woocommerce_process_login_errors', [$this, 'oopspam_woocommerce_login_errors'], 1, 1);
        add_action('woocommerce_checkout_process', [$this, 'oopspam_checkout_process']);

        add_action('woocommerce_store_api_checkout_order_processed', [$this, 'oopspam_checkout_store_api_processed'], 10, 1);
        add_action('woocommerce_checkout_order_processed', [$this, 'oopspam_checkout_classic_processed'], 10, 3);
        // Legacy API hook
        add_action('woocommerce_new_order', [$this, 'oopspam_legacy_checkout_classic_processed'], 10, 2);
    }

    private function cleanSensitiveData($data) {
        if (is_string($data)) {
            $data = json_decode($data, true);
        }
        
        if (is_array($data)) {
            if (isset($data['password'])) {
                unset($data['password']);
            }
            if (isset($data['user_pass'])) {
                unset($data['user_pass']);
            }
        }
        
        return json_encode($data);
    }

    function oopspam_legacy_checkout_classic_processed($order_id, $order) {
        
        $data = json_decode($order, true);
        $post = $_POST;

        // Check for allowed email/IP
        $hasAllowedEmail = isset($data['billing']['email']) ? $this->isEmailAllowed($data['billing']['email'], $data) : false;

        if ($hasAllowedEmail) {
            return $order;
        }

        $options = get_option('oopspamantispam_settings');
        $shouldBlockFromUnknownOrigin = $options['oopspam_woo_check_origin'] ?? false;

        // Check if WooCommerce -> Settings -> Advanced -> Features -> Order Attribution and "Block orders from unknown origin" are enabled.
        if ($shouldBlockFromUnknownOrigin && get_option("woocommerce_feature_order_attribution_enabled") === "yes") {

            $sourceTypeExists = false;
            $sourceTypeValue = null; 

            if (isset($data['meta_data'])) {
                foreach ($data['meta_data'] as $meta) {
                    if (isset($meta['key']) && $meta['key'] === '_wc_order_attribution_source_type') {
                        $sourceTypeExists = true;
                        $sourceTypeValue = $meta['value'];
                        break;
                    }
                }
            }

            // Check legacy origin attributes
            if (empty($sourceTypeValue) && isset($post['wc_order_attribution_source_type'])) {
                $sourceTypeExists = true;
                $sourceTypeValue = $post['wc_order_attribution_source_type'];
            }

            // This is to prevent the order from being processed if the source type is not set.            
            if (!$sourceTypeExists || empty($sourceTypeValue)) {
                
                $frmEntry = [
                    "Score" => 6,
                    "Message" => "",
                    "IP" => $data['customer_ip_address'],
                    "Email" => sanitize_email($data['billing']['email']),
                    "RawEntry" => $this->cleanSensitiveData(array_merge($data, $post)),
                    "FormId" => "WooCommerce",
                ];
                oopspam_store_spam_submission($frmEntry, "Unknown Order Attribution");

                // Trash the order
                if ( $order ) {
                    $order->delete( true ); // 'false' moves to trash, 'true' deletes permanently
                }

                $error_to_show = $this->get_error_message();
                wp_die($error_to_show);
            }

            // Now check with OOPSpam API
            $showError = $this->checkEmailAndIPInOOPSpam(sanitize_email($data['billing']['email']));
            if ($showError) {
                $error_to_show = $this->get_error_message();
                wc_add_notice( esc_html__( $error_to_show ), 'error' );
            }
        }
    }    
    

    function oopspam_checkout_store_api_processed($order) {
        
        $data = json_decode($order, true);

        // Check for allowed email/IP
        $hasAllowedEmail = isset($data['billing']['email']) ? $this->isEmailAllowed($data['billing']['email'], $data) : false;

        if ($hasAllowedEmail) {
            return $order;
        }

        $options = get_option('oopspamantispam_settings');
        $shouldBlockFromUnknownOrigin = $options['oopspam_woo_check_origin'] ?? false;
        
        // Check if WooCommerce -> Settings -> Advanced -> Features -> Order Attribution and "Block orders from unknown origin" are enabled.
        if ($shouldBlockFromUnknownOrigin && get_option("woocommerce_feature_order_attribution_enabled") === "yes") {

            $sourceTypeExists = false;
            $sourceTypeValue = null; 

            if (isset($data['meta_data'])) {
                foreach ($data['meta_data'] as $meta) {
                    if (isset($meta['key']) && $meta['key'] === '_wc_order_attribution_source_type') {
                        $sourceTypeExists = true;
                        $sourceTypeValue = $meta['value'];
                        break;
                    }
                }
            }

            if (isset($data['password'])) {
                unset($data['password']);
            }
            // This is to prevent the order from being processed if the source type is not set.            
            if (!$sourceTypeExists || empty($sourceTypeValue)) {
                
                $frmEntry = [
                    "Score" => 6,
                    "Message" => "",
                    "IP" => $data['customer_ip_address'],
                    "Email" => sanitize_email($data['billing']['email']),
                    "RawEntry" => $this->cleanSensitiveData($data),
                    "FormId" => "WooCommerce",
                ];
                oopspam_store_spam_submission($frmEntry, "Unknown Order Attribution");

                $error_to_show = $this->get_error_message();
                wp_die($error_to_show);
            }

            // Now check with OOPSpam API
            $showError = $this->checkEmailAndIPInOOPSpam(sanitize_email($data['billing']['email']));
            if ($showError) {
                $error_to_show = $this->get_error_message();
                wc_add_notice( esc_html__( $error_to_show ), 'error' );
            }
        }
    }    

    function oopspam_checkout_classic_processed($order_id, $posted_data, $order) {
        
        $data = json_decode($order, true);

        // Check for allowed email/IP
        $hasAllowedEmail = isset($data['billing']['email']) ? $this->isEmailAllowed($data['billing']['email'], $data) : false;

        if ($hasAllowedEmail) {
            return $order;
        }
        
        $options = get_option('oopspamantispam_settings');
        $shouldBlockFromUnknownOrigin = $options['oopspam_woo_check_origin'] ?? false;

        // Check if WooCommerce -> Settings -> Advanced -> Features -> Order Attribution and "Block orders from unknown origin" are enabled.
        if ($shouldBlockFromUnknownOrigin && get_option("woocommerce_feature_order_attribution_enabled") === "yes") {

            $sourceTypeExists = false;
            $sourceTypeValue = null;

            if (isset($data['meta_data'])) {
                foreach ($data['meta_data'] as $meta) {
                    if (isset($meta['key']) && $meta['key'] === '_wc_order_attribution_source_type') {
                        $sourceTypeExists = true;
                        $sourceTypeValue = $meta['value'];
                        break;
                    }
                }
            }

            // This is to prevent the order from being processed if the source type is not set.            
            if (!$sourceTypeExists || empty($sourceTypeValue)) {
                
                $frmEntry = [
                    "Score" => 6,
                    "Message" => "",
                    "IP" => $data['customer_ip_address'],
                    "Email" => sanitize_email($data['billing']['email']),
                    "RawEntry" => $this->cleanSensitiveData($data),
                    "FormId" => "WooCommerce",
                ];
                oopspam_store_spam_submission($frmEntry, "Unknown Order Attribution");

                $error_to_show = $this->get_error_message();
                wp_die($error_to_show);
            }

            // Now check with OOPSpam API
            $showError = $this->checkEmailAndIPInOOPSpam(sanitize_email($data['billing']['email']));
            if ($showError) {
                $error_to_show = $this->get_error_message();
                wc_add_notice( esc_html__( $error_to_show ), 'error' );
            }
        }
    }    

    function oopspam_checkout_process() {

        $email = "";
        if (isset($_POST["billing_email"]) && is_email($_POST["billing_email"])) {
            $email = $_POST["billing_email"];
        }
        $showError = $this->checkEmailAndIPInOOPSpam(sanitize_email($email));
        if ($showError) {
            $error_to_show = $this->get_error_message();
            wc_add_notice( esc_html__( $error_to_show ), 'error' );
        }
    }
    /**
     * Registration form honeypot
     */
    public function oopspam_woocommerce_register_form()
    {
        // Generate a unique field name using timestamp
        $timestamp = time();
        $field_name = 'honey_' . $timestamp;
        
        // Store the field name in session for validation
        if (function_exists('WC')) {
            WC()->session && WC()->session->set('honeypot_field', $field_name);
        }
        ?>
        <div class="form-row" style="opacity:0;position:absolute;top:0;left:0;height:0;width:0;z-index:-1" aria-hidden="true">
            <label for="<?php echo esc_attr($field_name); ?>">
                <?php esc_html_e('Please leave this blank', 'woocommerce'); ?>
            </label>
            <input type="text" 
                   id="<?php echo esc_attr($field_name); ?>" 
                   name="<?php echo esc_attr($field_name); ?>" 
                   value="" 
                   tabindex="-1" 
                   autocomplete="nope" 
                   style="pointer-events:none;"
            />
        </div>
        <?php
    }

    /**
     * Login form honeypot
     */
    public function oopspam_woocommerce_login_form()
    {

        $timestamp = time();
        $field_name = 'honey_log_' . $timestamp;
        
        if (function_exists('WC')) {
            WC()->session && WC()->session->set('honeypot_field_login', $field_name);
        }
        ?>
        <div class="form-row" style="opacity:0;position:absolute;top:0;left:0;height:0;width:0;z-index:-1" aria-hidden="true">
            <label for="<?php echo esc_attr($field_name); ?>">
                <?php esc_html_e('Please leave this blank', 'woocommerce'); ?>
            </label>
            <input type="text" 
                   id="<?php echo esc_attr($field_name); ?>" 
                   name="<?php echo esc_attr($field_name); ?>" 
                   value="" 
                   tabindex="-1" 
                   autocomplete="nope"
                   style="pointer-events:none;"
            />
        </div>
        <?php
    }

    /**
     * Registration validation
     */
    public function oopspam_woocommerce_register_errors($validation_error, $username, $password, $email)
    {

        $options = get_option('oopspamantispam_settings');

        // Bypass honeypot check for allowed emails/IPs
        $hasAllowedEmail = $this->isEmailAllowed($email, $_POST);

        if ($hasAllowedEmail) {
            return $validation_error;
        }
        
        // Only check honeypot if enabled
        if ($this->should_check_honeypot()) {
            // Check if any honeypot fields are filled
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'honey_') === 0 && !empty($value)) {
                    $error_to_show = $this->get_error_message();
                    $validation_error = new \WP_Error('oopspam_error', __($error_to_show, 'woocommerce'));

                    $frmEntry = [
                        "Score" => 6,
                        "Message" => sanitize_text_field($value),
                        "IP" => "",
                        "Email" => $email,
                        "RawEntry" => $this->cleanSensitiveData($_POST),
                        "FormId" => "WooCommerce",
                    ];
                    oopspam_store_spam_submission($frmEntry, "Failed honeypot validation");

                    return $validation_error;
                }
            }
        }

        return $validation_error;
    }

    /**
     * Registration during the checkout process
     */
    public function oopspam_process_registration($username, $email, $errors)
    {

        $options = get_option('oopspamantispam_settings');

        $hasAllowedEmail = $this->isEmailAllowed($email, $_POST);

        if ($hasAllowedEmail) {
            return $errors;
        }

        // Check honeypot fields
        if ($this->should_check_honeypot()) {
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'honey_') === 0 && !empty($value)) {
                    $isHoneypotDisabled = apply_filters('oopspam_woo_disable_honeypot', false);

                    if ($isHoneypotDisabled) {
                        return $errors;
                    }

                    $frmEntry = [
                        "Score" => 6,
                        "Message" => sanitize_text_field($value),
                        "IP" => "",
                        "Email" => $email,
                        "RawEntry" => $this->cleanSensitiveData($_POST),
                        "FormId" => "WooCommerce",
                    ];
                    oopspam_store_spam_submission($frmEntry, "Failed honeypot validation");

                    $error_to_show = $this->get_error_message();
                    $errors->add('oopspam_error', $error_to_show);
                    return $errors;
                }
            }
        }

        // OOPSpam check
        $showError = $this->checkEmailAndIPInOOPSpam(sanitize_email($email));
        if ($showError) {
            $error_to_show = $this->get_error_message();
            $errors->add('oopspam_error', $error_to_show);
            wp_die( $error_to_show );
            return $errors;
        }

        return $errors;
    }

    /**
     * Login validation
     */
    public function oopspam_woocommerce_login_errors($errors)
    {
        $email = isset($_POST["username"]) && is_email($_POST["username"]) ? $_POST["username"] : "unknown";

        $hasAllowedEmail = $this->isEmailAllowed($email, $_POST);

        if ($hasAllowedEmail) {
            return $errors;
        }

        // Check honeypot fields
        if ($this->should_check_honeypot()) {
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'honey_') === 0 && !empty($value)) {
                    $isHoneypotDisabled = apply_filters('oopspam_woo_disable_honeypot', false);

                    if ($isHoneypotDisabled) {
                        return $errors;
                    }

                    $error_to_show = $this->get_error_message();
                    $errors = new \WP_Error('oopspam_error', __($error_to_show, 'woocommerce'));
                    
                    $frmEntry = [
                        "Score" => 6,
                        "Message" => sanitize_text_field($value),
                        "IP" => "",
                        "Email" => $email,
                        "RawEntry" => $this->cleanSensitiveData($_POST),
                        "FormId" => "WooCommerce",
                    ];
                    oopspam_store_spam_submission($frmEntry, "Failed honeypot validation");
                    return $errors;
                }
            }
        }

        // OOPSpam check
        $showError = $this->checkEmailAndIPInOOPSpam(sanitize_email($email));

        if ($showError) {
            $error_to_show = $this->get_error_message();
            $errors = new \WP_Error('oopspam_error', __($error_to_show, 'woocommerce'));
            return $errors;
        }

        return $errors;
    }

    public function checkEmailAndIPInOOPSpam($email)
    {

        $options = get_option('oopspamantispam_settings');
        $privacyOptions = get_option('oopspamantispam_privacy_settings');
        $userIP = "";

        if (!empty(oopspamantispam_get_key()) && oopspam_is_spamprotection_enabled('woo')) {

        if (!isset($privacyOptions['oopspam_is_check_for_ip']) || $privacyOptions['oopspam_is_check_for_ip'] != true) {
            $userIP = \WC_Geolocation::get_ip_address();
        }

        if (!empty($userIP) || !empty($email)) {
            $detectionResult = oopspamantispam_call_OOPSpam("", $userIP, $email, true, "woo");
            if (!isset($detectionResult["isItHam"])) {
                return false;
            }
            $rawEntry = (object) array("IP" => $userIP, "email" => $email);
            $frmEntry = [
                "Score" => $detectionResult["Score"],
                "Message" => "",
                "IP" => $userIP,
                "Email" => $email,
                "RawEntry" => json_encode($rawEntry),
                "FormId" => "WooCommerce",
            ];

            if (!$detectionResult["isItHam"]) {
                // It's spam, store the submission and show error
                oopspam_store_spam_submission($frmEntry, $detectionResult["Reason"]);
                return true;
            } else {
                // It's ham
                oopspam_store_ham_submission($frmEntry);
                return false;
            }
        }
    }
    return false;
}

/**
 * Get error message from options or return default
 */
private function get_error_message()
{
    $options = get_option('oopspamantispam_settings', array());
    return isset($options['oopspam_woo_spam_message']) 
        ? $options['oopspam_woo_spam_message'] 
        : __('There was an error with your submission. Please try again.', 'woocommerce');
}

private function isEmailAllowed($email, $rawEntry)
    {
        $hasAllowedEmail = oopspam_is_email_allowed($email);
        
        if ($hasAllowedEmail) {
            $userIP = \WC_Geolocation::get_ip_address();
            $frmEntry = [
                "Score" => 0,
                "Message" => "",
                "IP" => $userIP,
                "Email" => $email,
                "RawEntry" => $this->cleanSensitiveData($rawEntry),
                "FormId" => "WooCommerce",
            ];
            oopspam_store_ham_submission($frmEntry);
            return true;
        }

        return false;
    }

private function should_check_honeypot() {
    $options = get_option('oopspamantispam_settings');
    return isset($options['oopspam_woo_check_honeypot']) && $options['oopspam_woo_check_honeypot'] == 1;
}
}