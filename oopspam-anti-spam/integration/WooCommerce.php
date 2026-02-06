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
        
        // Check if WooCommerce integration is enabled
        $woo_enabled = oopspam_is_spamprotection_enabled('woo');
        
        // If WooCommerce integration is not enabled, don't set up any hooks
        if (!$woo_enabled) {
            return;
        }
        
        $honeypot_enabled = isset($options['oopspam_woo_check_honeypot']) && $options['oopspam_woo_check_honeypot'] == 1;
        $disable_rest_checkout = isset($options['oopspam_woo_disable_rest_checkout']) && $options['oopspam_woo_disable_rest_checkout'] == 1;
        
        // Disable WooCommerce REST API checkout endpoints if the setting is enabled
        if ($disable_rest_checkout) {
            add_action('rest_api_init', array($this, 'oopspam_disable_wc_rest_checkout'));
        }

        // Initialize actions & filters
        if ($honeypot_enabled) {
            add_action('woocommerce_register_form', [$this, 'oopspam_woocommerce_register_form'], 1, 0);
            add_action('woocommerce_after_checkout_billing_form', [$this, 'oopspam_woocommerce_register_form']);
            add_action('woocommerce_login_form', [$this, 'oopspam_woocommerce_login_form'], 1, 0);
        }
        
        // Add hooks for checkout validation
        add_action('woocommerce_register_post', array($this, 'oopspam_process_registration'), 10, 3);
        add_action('woocommerce_process_registration_errors', [$this, 'oopspam_woocommerce_register_errors'], 10, 4);
        add_filter('woocommerce_process_login_errors', [$this, 'oopspam_woocommerce_login_errors'], 1, 1);
        add_action('woocommerce_checkout_process', [$this, 'oopspam_checkout_process']);

        add_action('woocommerce_store_api_checkout_order_processed', [$this, 'oopspam_checkout_store_api_processed'], 10, 1);
        add_action('woocommerce_checkout_order_processed', [$this, 'oopspam_checkout_classic_processed'], 10, 3);
        // Legacy API hook
        add_action('woocommerce_new_order', [$this, 'oopspam_legacy_checkout_classic_processed'], 10, 2);

        add_action('woocommerce_order_save_attribution_data', [$this, 'oopspam_check_order_attributes'], 10, 2);
    }

    private function cleanSensitiveData($data) {
        if (is_string($data)) {
            $data = json_decode($data, true);
        }
        
        if (is_array($data)) {
            $sensitive_fields = [
                'password',
                'user_pass',
                'account_password',
                'moneris-card-number',
                'moneris-card-expiry',
                'moneris-card-cvc'
            ];

            foreach ($sensitive_fields as $field) {
                if (isset($data[$field])) {
                    unset($data[$field]);
                }
            }
        }
        
        return json_encode($data);
    }

    /**
     * Check if order total matches blocked amounts and handle accordingly
     * Returns true if order was blocked, false otherwise
     */
    private function checkBlockedOrderTotal($order_total, $email, $order_id = null, $log_entry = true) {
        $options = get_option('oopspamantispam_settings');
        $blocked_totals_input = isset($options['oopspam_woo_block_order_total']) && $options['oopspam_woo_block_order_total'] !== '' ? $options['oopspam_woo_block_order_total'] : '';
        
        if (empty($blocked_totals_input)) {
            return false;
        }
        
        $blocked_totals = array_map('trim', preg_split('/\r\n|\r|\n/', $blocked_totals_input));
        $blocked_totals = array_filter(array_map('floatval', $blocked_totals), function($val) { return $val > 0; });
        
        if (empty($blocked_totals)) {
            return false;
        }
        
        foreach ($blocked_totals as $blocked_total) {
            if (abs($order_total - $blocked_total) < 0.001) { // Use small tolerance for float comparison
                // Check if user has completed orders before - don't block if they do
                if (!empty($email) && $this->hasCompletedOrders($email)) {
                    // User has previous completed orders, allow this order to proceed
                    return false;
                }
                
                // Check if we've already logged this order to prevent duplicates
                $transient_key = 'oopspam_blocked_order_' . ($order_id ? $order_id : md5($email . $order_total . time()));
                if (get_transient($transient_key)) {
                    // Already logged, just return true to block
                    return true;
                }
                
                // Only log if requested (to avoid duplicate entries)
                if ($log_entry) {
                    $userIP = oopspamantispam_get_ip();
                    $frmEntry = [
                        "Score" => 6,
                        "Message" => "",
                        "IP" => $userIP,
                        "Email" => $email,
                        "RawEntry" => json_encode(array("order_total" => $order_total, "blocked_total" => $blocked_total, "order_id" => $order_id)),
                        "FormId" => "WooCommerce",
                    ];
                    oopspam_store_spam_submission($frmEntry, "Blocked order total: $" . number_format($order_total, 2));
                    
                    // Set transient to prevent duplicate logging for 5 minutes
                    set_transient($transient_key, true, 300);
                }
                
                return true; // Order should be blocked
            }
        }
        
        return false;
    }

    function oopspam_check_order_attributes($order, $data ) {

        $options = get_option('oopspamantispam_settings');
        
        // Check if WooCommerce integration is enabled
        $woo_enabled = oopspam_is_spamprotection_enabled('woo');
        
        // If WooCommerce integration is not enabled, don't perform any checks
        if (!$woo_enabled) {
            return $order;
        }
        
        // Check for allowed email/IP
        $email = $order->get_billing_email();
        $hasAllowedEmail = $email ? $this->isEmailAllowed($email, $data) : false;
        $userIP = oopspamantispam_get_ip();
        $hasAllowedIP = oopspam_is_ip_allowed($userIP);

        if ($hasAllowedEmail || $hasAllowedIP) {
            return $order;
        }

        $minSessionPages = isset($options['oopspam_woo_min_session_pages']) && $options['oopspam_woo_min_session_pages'] !== '' ? intval($options['oopspam_woo_min_session_pages']) : 0;
        $requireDeviceType = isset($options['oopspam_woo_require_device_type']) && $options['oopspam_woo_require_device_type'] == 1;
        $shouldBlockFromUnknownOrigin = $options['oopspam_woo_check_origin'] ?? false;
        
        // Helper function to check if a value is valid (not empty, null, or "(none)")
        $isValidValue = function($value) {
            return !empty($value) && $value !== "(none)";
        };
        
        // Block order if any of the independent checks fail
        $blockOrder = false;
        $blockReason = "";
        
        // 1. Device type check - block if user_agent doesn't exist, is empty, or is "(none)" and device type is required
        if ($requireDeviceType && !$isValidValue($data['user_agent'] ?? '')) {
            $blockOrder = true;
            $blockReason = "Invalid Device Type";
        }
        
        // 2. Origin check - block if source_type doesn't exist, is empty, or is "(none)" when required
        if ($shouldBlockFromUnknownOrigin && get_option("woocommerce_feature_order_attribution_enabled") === "yes") {
            $payment_methods = isset($options['oopspam_woo_payment_methods']) ? $options['oopspam_woo_payment_methods'] : '';
            $should_check_origin = false;

            // If no payment methods specified, always check origin
            if (empty($payment_methods)) {
                $should_check_origin = true;
            } 
            // If payment methods are specified, only check if current method matches
            else {
                $current_payment_method = strtolower($order->get_payment_method_title());
                $allowed_methods = array_map('trim', preg_split('/\r\n|\r|\n/', $payment_methods));
                $allowed_methods = array_map('strtolower', array_filter($allowed_methods));
                
                foreach ($allowed_methods as $method) {
                    if (strpos($current_payment_method, $method) !== false) {
                        $should_check_origin = true;
                        break;
                    }
                }
            }

            if ($should_check_origin && !$isValidValue($data['source_type'] ?? '')) {
                $blockOrder = true;
                $blockReason = "Unknown Order Attribution";
            }
        }
        
        // 3. Session pages check - block if session_pages is less than minimum required, is "(none)", or invalid
        if ($minSessionPages > 0) {
            $sessionPagesValue = $data['session_pages'] ?? '';
            
            // Check if session_pages is valid (not "(none)" and is a valid number)
            if (!$isValidValue($sessionPagesValue) || !is_numeric($sessionPagesValue)) {
                $blockOrder = true;
                $blockReason = "Invalid Session Pages: " . $sessionPagesValue;
            } else {
                $sessionPagesValue = intval($sessionPagesValue);
                if ($sessionPagesValue < $minSessionPages) {
                    $blockOrder = true;
                    $blockReason = "Insufficient Session Pages: {$sessionPagesValue}/{$minSessionPages}";
                }
            }
        }
        
        // Process blockOrder regardless of the origin check
        if ($blockOrder) {
            // Check if user has completed orders before - don't block if they do
            $email = $order->get_billing_email();
            $hasCompletedOrders = $this->hasCompletedOrders($email);
            
            if ($hasCompletedOrders) {
                // User has previous completed orders, allow this order to proceed
                return $order;
            } else {
                $userIP = oopspamantispam_get_ip();
                // No previous orders, proceed with blocking
                $frmEntry = [
                    "Score" => 6,
                    "Message" => "",
                    "IP" => $userIP,
                    "Email" => $email,
                    "RawEntry" => $this->cleanSensitiveData($data),
                    "FormId" => "WooCommerce",
                ];
                oopspam_store_spam_submission($frmEntry, $blockReason);

                // Trash the order
                if ($order) {
                    $order->delete(true); // 'true' deletes permanently
                }

                $error_to_show = $this->get_error_message();
                wp_die(esc_html($error_to_show));
            }
        }
        
        return $order;
    }
    function oopspam_legacy_checkout_classic_processed($order_id, $order) {
        $options = get_option('oopspamantispam_settings');
        
        // Check if WooCommerce integration is enabled
        $woo_enabled = oopspam_is_spamprotection_enabled('woo');
        
        // If WooCommerce integration is not enabled, don't perform any checks
        if (!$woo_enabled) {
            return $order;
        }
        
        $data = json_decode($order, true);
        $post = $_POST;

        // Check for allowed email/IP
        $hasAllowedEmail = isset($data['billing']['email']) ? $this->isEmailAllowed($data['billing']['email'], $data) : false;

        if ($hasAllowedEmail) {
            return $order;
        }

        // Check for blocked order total
        $wc_order = wc_get_order($order_id);
        if ($wc_order) {
            $order_total = floatval($wc_order->get_total());
            $email = isset($data['billing']['email']) ? $data['billing']['email'] : '';
            
            if ($this->checkBlockedOrderTotal($order_total, $email, $order_id)) {
                // Delete the order and show error
                $wc_order->delete(true);
                $error_to_show = $this->get_error_message();
                \wc_add_notice( esc_html( $error_to_show ), 'error' );
                return $order;
            }
        }

        // Now check with OOPSpam API
        $message = isset($post['order_comments']) ? sanitize_text_field($post['order_comments']) : '';
        if (empty($message) && isset($data['customer_note'])) {
            $message = sanitize_text_field($data['customer_note']);
        }
        $showError = $this->checkEmailAndIPInOOPSpam(sanitize_email($data['billing']['email']), $message);
        if ($showError) {
            $error_to_show = $this->get_error_message();
            \wc_add_notice( esc_html( $error_to_show ), 'error' );
        }

    }

    function oopspam_checkout_store_api_processed($order) {
        $options = get_option('oopspamantispam_settings');
        
        // Check if WooCommerce integration is enabled
        $woo_enabled = oopspam_is_spamprotection_enabled('woo');
        
        // If WooCommerce integration is not enabled, don't perform any checks
        if (!$woo_enabled) {
            return $order;
        }
        
        $data = json_decode($order, true);

        // Check for allowed email/IP
        $hasAllowedEmail = isset($data['billing']['email']) ? $this->isEmailAllowed($data['billing']['email'], $data) : false;

        if ($hasAllowedEmail) {
            return $order;
        }
        
        // Check for blocked order total
        // $order might be an order object or order ID, let's handle both
        $wc_order = is_numeric($order) ? wc_get_order($order) : $order;
        if ($wc_order && is_a($wc_order, 'WC_Order')) {
            $order_total = floatval($wc_order->get_total());
            $email = isset($data['billing']['email']) ? $data['billing']['email'] : $wc_order->get_billing_email();
            
            if ($this->checkBlockedOrderTotal($order_total, $email, $wc_order->get_id())) {
                // Delete the order and show error
                $wc_order->delete(true);
                $error_to_show = $this->get_error_message();
                \wc_add_notice( esc_html( $error_to_show ), 'error' );
                return $order;
            }
        }
            
        // Now check with OOPSpam API
            $message = isset($data['customer_note']) ? sanitize_text_field($data['customer_note']) : '';
            if (empty($message) && isset($data['order_comments'])) {
                $message = sanitize_text_field($data['order_comments']);
            }
            $showError = $this->checkEmailAndIPInOOPSpam(sanitize_email($data['billing']['email']), $message);
            if ($showError) {
                $error_to_show = $this->get_error_message();
                \wc_add_notice( esc_html( $error_to_show ), 'error' );
            }
        
    }    

    function oopspam_checkout_classic_processed($order_id, $posted_data, $order) {
        $options = get_option('oopspamantispam_settings');
        
        // Check if WooCommerce integration is enabled
        $woo_enabled = oopspam_is_spamprotection_enabled('woo');
        
        // If WooCommerce integration is not enabled, don't perform any checks
        if (!$woo_enabled) {
            return $order;
        }
        
        $data = json_decode($order, true);

        // Check for allowed email/IP
        $hasAllowedEmail = isset($data['billing']['email']) ? $this->isEmailAllowed($data['billing']['email'], $data) : false;

        if ($hasAllowedEmail) {
            return $order;
        }
        
        // Check for blocked order total
        $wc_order = wc_get_order($order_id);
        if ($wc_order) {
            $order_total = floatval($wc_order->get_total());
            $email = isset($data['billing']['email']) ? $data['billing']['email'] : '';
            
            if ($this->checkBlockedOrderTotal($order_total, $email, $order_id)) {
                // Delete the order and show error
                $wc_order->delete(true);
                $error_to_show = $this->get_error_message();
                \wc_add_notice( esc_html( $error_to_show ), 'error' );
                return $order;
            }
        }
        
        // Now check with OOPSpam API
        $message = isset($data['customer_note']) ? sanitize_text_field($data['customer_note']) : '';
        if (empty($message) && isset($posted_data['order_comments'])) {
            $message = sanitize_text_field($posted_data['order_comments']);
        }
        $showError = $this->checkEmailAndIPInOOPSpam(sanitize_email($data['billing']['email']), $message);
        if ($showError) {
            $error_to_show = $this->get_error_message();
            \wc_add_notice( esc_html( $error_to_show ), 'error' );
        }
    }    

    function oopspam_checkout_process() {
        $options = get_option('oopspamantispam_settings');
        
        // Check if WooCommerce integration is enabled
        $woo_enabled = oopspam_is_spamprotection_enabled('woo');
        
        // If WooCommerce integration is not enabled, don't perform any checks
        if (!$woo_enabled) {
            return;
        }

        $email = ""; $message = "";
        $message = isset($_POST['order_comments']) ? sanitize_text_field($_POST['order_comments']) : '';
        if (empty($message) && isset($_POST['customer_note'])) {
            $message = sanitize_text_field($_POST['customer_note']);
        }
        if (isset($_POST["billing_email"]) && is_email($_POST["billing_email"])) {
            $email = $_POST["billing_email"];
        }
        
        // Note: Blocked order total check is handled in the order processing functions
        // to avoid duplicate entries and ensure proper logging
        
        $showError = $this->checkEmailAndIPInOOPSpam(sanitize_email($email), sanitize_text_field($message));
        if ($showError) {
            $error_to_show = $this->get_error_message();
            \wc_add_notice( esc_html( $error_to_show ), 'error' );
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
        
        // Check if WooCommerce integration is enabled
        $woo_enabled = oopspam_is_spamprotection_enabled('woo');
        
        // If WooCommerce integration is not enabled, don't perform any checks
        if (!$woo_enabled) {
            return $validation_error;
        }

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
                    $validation_error = new \WP_Error('oopspam_error', esc_html($error_to_show));

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
        
        // Check if WooCommerce integration is enabled
        $woo_enabled = oopspam_is_spamprotection_enabled('woo');
        
        // If WooCommerce integration is not enabled, don't perform any checks
        if (!$woo_enabled) {
            return $errors;
        }

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
                    $errors->add('oopspam_error', esc_html($error_to_show));
                    return $errors;
                }
            }
        }

        // OOPSpam check
        $message = isset($_POST['order_comments']) ? sanitize_text_field($_POST['order_comments']) : '';
        if (empty($message) && isset($_POST['customer_note'])) {
            $message = sanitize_text_field($_POST['customer_note']);
        }
        $showError = $this->checkEmailAndIPInOOPSpam(sanitize_email($email), $message);
        if ($showError) {
            $error_to_show = $this->get_error_message();
            $errors->add('oopspam_error', esc_html($error_to_show));
            wp_die( esc_html($error_to_show) );
            return $errors;
        }

        return $errors;
    }

    /**
     * Login validation
     */
    public function oopspam_woocommerce_login_errors($errors)
    {
        $options = get_option('oopspamantispam_settings');
        
        // Check if WooCommerce integration is enabled
        $woo_enabled = oopspam_is_spamprotection_enabled('woo');
        
        // If WooCommerce integration is not enabled, don't perform any checks
        if (!$woo_enabled) {
            return $errors;
        }
        
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
                    $errors = new \WP_Error('oopspam_error', esc_html($error_to_show));
                    
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
        $message = isset($_POST['order_comments']) ? sanitize_text_field($_POST['order_comments']) : '';
        if (empty($message) && isset($_POST['customer_note'])) {
            $message = sanitize_text_field($_POST['customer_note']);
        }
        $showError = $this->checkEmailAndIPInOOPSpam(sanitize_email($email), $message);

        if ($showError) {
            $error_to_show = $this->get_error_message();
            $errors = new \WP_Error('oopspam_error', esc_html($error_to_show));
            return $errors;
        }

        return $errors;
    }

    public function checkEmailAndIPInOOPSpam($email, $message)
    {
        $options = get_option('oopspamantispam_settings');
        
        // Check if WooCommerce integration is enabled
        $woo_enabled = oopspam_is_spamprotection_enabled('woo');
        
        // If WooCommerce integration is not enabled, don't perform any checks
        if (!$woo_enabled) {
            return false; // Return false to indicate no spam (allow the action)
        }
        
        $privacyOptions = get_option('oopspamantispam_privacy_settings');
        $userIP = "";
        if (!isset($privacyOptions['oopspam_is_check_for_ip']) || ($privacyOptions['oopspam_is_check_for_ip'] !== true && $privacyOptions['oopspam_is_check_for_ip'] !== 'on')) {
            $userIP = oopspamantispam_get_ip();
        }

        // First check if user has previous completed orders
        if (!empty($email)) {
            // If they have completed orders, consider them not spam
            if ($this->hasCompletedOrders($email)) {
                // Log this as ham automatically
                $rawEntry = (object) array("IP" => $userIP, "email" => $email);
                $frmEntry = [
                    "Score" => 0, // Low score since we trust returning customers
                    "Message" => $message,
                    "IP" => $userIP,
                    "Email" => $email,
                    "RawEntry" => json_encode($rawEntry),
                    "FormId" => "WooCommerce",
                ];
                
                // Store as ham submission
                oopspam_store_ham_submission($frmEntry);
                return false; // Not spam
            }
        }

        if (!empty(oopspamantispam_get_key()) && oopspam_is_spamprotection_enabled('woo')) {

        if (!empty($userIP) || !empty($email)) {
            $detectionResult = oopspamantispam_call_OOPSpam($message, $userIP, $email, true, "woo");
            if (!isset($detectionResult["isItHam"])) {
                return false;
            }
            $rawEntry = (object) array("IP" => $userIP, "email" => $email);
            $frmEntry = [
                "Score" => $detectionResult["Score"],
                "Message" => $message,
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
    return (isset($options['oopspam_woo_spam_message']) && !empty($options['oopspam_woo_spam_message'])) 
        ? $options['oopspam_woo_spam_message'] 
        : __('Your order was detected as spam. Please try again or contact support.', 'woocommerce');
}

private function isEmailAllowed($email, $rawEntry)
    {
        $hasAllowedEmail = oopspam_is_email_allowed($email);
        
        if ($hasAllowedEmail) {
            $userIP = oopspamantispam_get_ip();
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

/**
 * Blocks WooCommerce checkout endpoints in the REST API
 * This helps prevent spam orders from automated tools and bots that bypass the normal checkout flow
 * Can be enabled/disabled via the WooCommerce settings in the OOPSpam options
 */
public function oopspam_disable_wc_rest_checkout() {
    $options = get_option('oopspamantispam_settings');
    
    // Check if WooCommerce integration is enabled
    $woo_enabled = oopspam_is_spamprotection_enabled('woo');
    
    // If WooCommerce integration is not enabled, don't block anything
    if (!$woo_enabled) {
        return;
    }
    
    $current_url = $_SERVER['REQUEST_URI'];
    
    // Block v1 endpoints
    if (strpos($current_url, '/wp-json/wc/store/v1/checkout') !== false) {
        // Get proper IP address using the plugin's method
        $userIP = oopspamantispam_get_ip();
        
        // Try to extract email from request body
        $email = '';
        $request_body = file_get_contents('php://input');
        if (!empty($request_body)) {
            $json_data = json_decode($request_body, true);
            if (is_array($json_data) && isset($json_data['billing_address']['email'])) {
                $email = sanitize_email($json_data['billing_address']['email']);
            }
        }
        
        $request_details = [
            'IP' => $userIP,
            'User Agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Not provided',
            'Referer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'Not provided',
            'URL' => $current_url,
            'Request Body' => $request_body
        ];
        
        $frmEntry = [
            "Score" => 6,
            "Message" => "",
            "IP" => $userIP,
            "Email" => $email,
            "RawEntry" => json_encode($request_details),
            "FormId" => "WooCommerce",
        ];
        oopspam_store_spam_submission($frmEntry, "Blocked WC REST API v1 checkout");
        
        wp_safe_redirect(home_url('/404.php'));
        exit;
    }
    
    // Block v2 endpoints if they exist
    if (strpos($current_url, '/wp-json/wc/store/v2/checkout') !== false) {
        // Get proper IP address using the plugin's method
        $userIP = oopspamantispam_get_ip();
        
        // Try to extract email from request body
        $email = '';
        $request_body = file_get_contents('php://input');
        if (!empty($request_body)) {
            $json_data = json_decode($request_body, true);
            if (is_array($json_data) && isset($json_data['billing_address']['email'])) {
                $email = sanitize_email($json_data['billing_address']['email']);
            }
        }
        
        $request_details = [
            'IP' => $userIP,
            'User Agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Not provided',
            'Referer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'Not provided',
            'URL' => $current_url,
            'Request Body' => $request_body
        ];
        
        $frmEntry = [
            "Score" => 6,
            "Message" => "",
            "IP" => $userIP,
            "Email" => $email,
            "RawEntry" => json_encode($request_details),
            "FormId" => "WooCommerce",
        ];
        oopspam_store_spam_submission($frmEntry, "Blocked WC REST API v2 checkout");
        
        wp_safe_redirect(home_url('/404.php'));
        exit;
    }
    
    // Block older checkout/payment endpoints
    if (strpos($current_url, '/wp-json/wc/v') !== false && 
        (strpos($current_url, '/checkout') !== false || strpos($current_url, '/payment') !== false)) {
        // Get proper IP address using the plugin's method
        $userIP = oopspamantispam_get_ip();
        
        // Try to extract email from request body
        $email = '';
        $request_body = file_get_contents('php://input');
        if (!empty($request_body)) {
            $json_data = json_decode($request_body, true);
            // Legacy endpoints might have different structure - try to find email in various possible locations
            if (is_array($json_data)) {
                if (isset($json_data['billing_address']['email'])) {
                    $email = sanitize_email($json_data['billing_address']['email']);
                } elseif (isset($json_data['billing']['email'])) {
                    $email = sanitize_email($json_data['billing']['email']);
                } elseif (isset($json_data['email'])) {
                    $email = sanitize_email($json_data['email']);
                }
            }
        }
        
        $request_details = [
            'IP' => $userIP,
            'User Agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Not provided',
            'Referer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'Not provided',
            'URL' => $current_url,
            'Request Body' => $request_body
        ];
        
        $frmEntry = [
            "Score" => 6,
            "Message" => "",
            "IP" => $userIP,
            "Email" => $email,
            "RawEntry" => json_encode($request_details),
            "FormId" => "WooCommerce",
        ];
        oopspam_store_spam_submission($frmEntry, "Blocked legacy WC REST API checkout");
        
        wp_safe_redirect(home_url('/404.php'));
        exit;
    }
}

/**
 * Check if a user has any completed orders
 * 
 * @param string $email Customer email address
 * @param bool $debug Whether to log debug information
 * @return boolean True if user has completed orders, false otherwise
 */
private function hasCompletedOrders($email, $debug = false) {
    if (empty($email)) {
        if ($debug) {
            error_log("OOPSpam: hasCompletedOrders - Empty email provided");
        }
        return false;
    }
    
    // Query for completed orders with this email
    $args = array(
        'customer' => $email,
        'status' => array('wc-completed'),
        'limit' => 1,
    );
    
    $orders = wc_get_orders($args);
    
    $hasOrders = !empty($orders);
    
    if ($debug) {
        error_log("OOPSpam: hasCompletedOrders - Email: $email, Has orders: " . ($hasOrders ? 'Yes' : 'No'));
    }
    
    // Return true if at least one completed order exists
    return $hasOrders;
}
}