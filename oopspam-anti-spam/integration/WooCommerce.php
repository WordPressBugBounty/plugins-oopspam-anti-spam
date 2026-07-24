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

        // Track failed payment attempts for velocity check
        add_action('woocommerce_order_status_failed', [$this, 'oopspam_track_failed_payment'], 10, 2);

        // Admin order actions: Block as Spam / Undo Block
        add_filter('woocommerce_order_actions', [$this, 'add_order_actions'], 10, 2);
        add_action('woocommerce_order_action_oopspam_block_as_spam', [$this, 'handle_block_order_action']);
        add_action('woocommerce_order_action_oopspam_undo_block', [$this, 'handle_undo_block_order_action']);

        // Bulk actions on Orders list — legacy CPT screen
        add_filter('bulk_actions-edit-shop_order', [$this, 'add_bulk_actions']);
        add_filter('handle_bulk_actions-edit-shop_order', [$this, 'handle_bulk_block_orders'], 10, 3);

        // Bulk actions on Orders list — HPOS screen
        add_filter('bulk_actions-woocommerce_page_wc-orders', [$this, 'add_bulk_actions']);
        add_filter('handle_bulk_actions-woocommerce_page_wc-orders', [$this, 'handle_bulk_block_orders'], 10, 3);

        // Admin notice after bulk/manual action
        add_action('admin_notices', [$this, 'display_block_action_notices']);

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

    /**
     * Check if billing address matches blocked addresses and handle accordingly
     * Returns true if order was blocked, false otherwise
     */
    private function checkBlockedBillingAddress($order, $order_id = null, $log_entry = true) {
        $options = get_option('oopspamantispam_settings');
        $blocked_addresses_input = isset($options['oopspam_woo_block_billing_address']) && $options['oopspam_woo_block_billing_address'] !== '' ? $options['oopspam_woo_block_billing_address'] : '';
        
        if (empty($blocked_addresses_input)) {
            return false;
        }
        
        $blocked_addresses = array_map('trim', preg_split('/\r\n|\r|\n/', $blocked_addresses_input));
        $blocked_addresses = array_filter($blocked_addresses);
        
        if (empty($blocked_addresses)) {
            return false;
        }
        
        // Build the full billing address from the order
        $address_parts = [];
        if (is_a($order, 'WC_Order')) {
            $address_parts[] = $order->get_billing_address_1();
            $address_parts[] = $order->get_billing_address_2();
            $email = $order->get_billing_email();
        } else {
            return false;
        }
        
        $full_address = strtolower(implode(' ', array_filter($address_parts)));
        
        if (empty($full_address)) {
            return false;
        }
        
        foreach ($blocked_addresses as $blocked_address) {
            $blocked_address_lower = strtolower($blocked_address);
            if (strpos($full_address, $blocked_address_lower) !== false) {
                // Check if user has completed orders before - don't block if they do
                if (!empty($email) && $this->hasCompletedOrders($email)) {
                    return false;
                }
                
                // Check if we've already logged this order to prevent duplicates
                $transient_key = 'oopspam_blocked_address_' . ($order_id ? $order_id : md5($email . $full_address . time()));
                if (get_transient($transient_key)) {
                    return true;
                }
                
                if ($log_entry) {
                    $userIP = oopspamantispam_get_ip();
                    $frmEntry = [
                        "Score" => 6,
                        "Message" => "",
                        "IP" => $userIP,
                        "Email" => $email,
                        "RawEntry" => json_encode(array("billing_address" => $full_address, "blocked_address" => $blocked_address, "order_id" => $order_id)),
                        "FormId" => "WooCommerce",
                    ];
                    oopspam_store_spam_submission($frmEntry, "Blocked billing address: " . $blocked_address);
                    
                    set_transient($transient_key, true, 300);
                }
                
                return true;
            }
        }
        
        return false;
    }

    /**
     * Track failed payment attempts per IP using transients.
     */
    public function oopspam_track_failed_payment($order_id, $order = null) {
        $options = get_option('oopspamantispam_settings');
        $max_failed = isset($options['oopspam_woo_max_failed_payments']) && $options['oopspam_woo_max_failed_payments'] !== '' ? intval($options['oopspam_woo_max_failed_payments']) : 0;
        
        if ($max_failed <= 0) {
            return;
        }

        $userIP = oopspamantispam_get_ip();
        if (empty($userIP)) {
            return;
        }

        $window_hours = isset($options['oopspam_woo_failed_payments_window']) && $options['oopspam_woo_failed_payments_window'] !== '' ? intval($options['oopspam_woo_failed_payments_window']) : 24;
        $transient_key = 'oopspam_failed_pay_' . md5($userIP);
        $failed_data = get_transient($transient_key);

        if (!$failed_data || !is_array($failed_data)) {
            $failed_data = [];
        }

        // Add current timestamp
        $failed_data[] = time();

        // Clean old entries outside the time window
        $cutoff = time() - ($window_hours * 3600);
        $failed_data = array_values(array_filter($failed_data, function($ts) use ($cutoff) {
            return $ts >= $cutoff;
        }));

        set_transient($transient_key, $failed_data, $window_hours * 3600);
    }

    /**
     * Check if an IP has exceeded failed payment attempt threshold.
     * Returns true if the IP should be blocked.
     */
    private function checkFailedPaymentVelocity($email = '') {
        $options = get_option('oopspamantispam_settings');
        $max_failed = isset($options['oopspam_woo_max_failed_payments']) && $options['oopspam_woo_max_failed_payments'] !== '' ? intval($options['oopspam_woo_max_failed_payments']) : 0;
        
        if ($max_failed <= 0) {
            return false;
        }

        $userIP = oopspamantispam_get_ip();
        if (empty($userIP)) {
            return false;
        }

        $window_hours = isset($options['oopspam_woo_failed_payments_window']) && $options['oopspam_woo_failed_payments_window'] !== '' ? intval($options['oopspam_woo_failed_payments_window']) : 24;
        $transient_key = 'oopspam_failed_pay_' . md5($userIP);
        $failed_data = get_transient($transient_key);

        if (!$failed_data || !is_array($failed_data)) {
            return false;
        }

        // Clean old entries outside the time window
        $cutoff = time() - ($window_hours * 3600);
        $failed_data = array_values(array_filter($failed_data, function($ts) use ($cutoff) {
            return $ts >= $cutoff;
        }));

        if (count($failed_data) >= $max_failed) {
            $frmEntry = [
                "Score" => 6,
                "Message" => "",
                "IP" => $userIP,
                "Email" => $email,
                "RawEntry" => json_encode([
                    'failed_attempts' => count($failed_data),
                    'max_allowed' => $max_failed,
                    'window_hours' => $window_hours
                ]),
                "FormId" => "WooCommerce",
            ];
            oopspam_store_spam_submission($frmEntry, "Too many failed payment attempts: " . count($failed_data) . "/" . $max_failed . " in " . $window_hours . "h");
            return true;
        }

        return false;
    }

    /**
     * Check if there are too many recent orders with the same total amount.
     * Returns true if the order should be blocked.
     */
    private function checkSameAmountOrders($order_total, $email = '', $order_id = null) {
        $options = get_option('oopspamantispam_settings');
        $threshold = isset($options['oopspam_woo_same_amount_threshold']) && $options['oopspam_woo_same_amount_threshold'] !== '' ? intval($options['oopspam_woo_same_amount_threshold']) : 0;

        if ($threshold <= 0 || $order_total <= 0) {
            return false;
        }

        $window_hours = isset($options['oopspam_woo_same_amount_window']) && $options['oopspam_woo_same_amount_window'] !== '' ? intval($options['oopspam_woo_same_amount_window']) : 1;
        $date_after_ts = time() - ($window_hours * HOUR_IN_SECONDS);

        $args = [
            'date_created' => '>' . $date_after_ts,
            'status' => ['processing', 'on-hold', 'pending', 'failed', 'completed'],
            'limit' => 200,
            'return' => 'ids',
        ];

        // Exclude the current order if provided
        if ($order_id) {
            $args['exclude'] = [$order_id];
        }

        $recent_orders = wc_get_orders($args);
        
        // Count how many have the same total
        $same_amount_count = 0;
        foreach ($recent_orders as $rid) {
            $recent_order = wc_get_order($rid);
            if ($recent_order) {
                $recent_total = floatval($recent_order->get_total());
                if (abs($recent_total - $order_total) < 0.01) {
                    $same_amount_count++;
                }
            }
        }


        if ($same_amount_count >= $threshold) {
            $userIP = oopspamantispam_get_ip();
            $frmEntry = [
                "Score" => 6,
                "Message" => "",
                "IP" => $userIP,
                "Email" => $email,
                "RawEntry" => json_encode([
                    'order_total' => $order_total,
                    'same_amount_count' => $same_amount_count,
                    'threshold' => $threshold,
                    'window_hours' => $window_hours
                ]),
                "FormId" => "WooCommerce",
            ];
            oopspam_store_spam_submission($frmEntry, "Too many orders with same amount ($" . number_format($order_total, 2) . "): " . $same_amount_count . "/" . $threshold . " in " . $window_hours . "h");
            return true;
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
                $this->block_checkout_with_error($error_to_show);
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
        
        // $order is a WC_Order object from woocommerce_new_order
        $wc_order = is_a($order, 'WC_Order') ? $order : wc_get_order($order_id);
        if (!$wc_order) {
            return $order;
        }

        $email = $wc_order->get_billing_email();
        $post = $_POST;

        // Check for allowed email/IP
        $hasAllowedEmail = $email ? $this->isEmailAllowed($email, $wc_order->get_data()) : false;

        if ($hasAllowedEmail) {
            return $order;
        }

        // Check for blocked order total and billing address
        $order_total = floatval($wc_order->get_total());

        if ($this->checkBlockedOrderTotal($order_total, $email, $order_id)) {
            $wc_order->delete(true);
            $error_to_show = $this->get_error_message();
            \wc_add_notice( esc_html( $error_to_show ), 'error' );
            return $order;
        }

        if ($this->checkBlockedBillingAddress($wc_order, $order_id)) {
            $wc_order->delete(true);
            $error_to_show = $this->get_error_message();
            \wc_add_notice( esc_html( $error_to_show ), 'error' );
            return $order;
        }

        // Same-amount orders velocity check
        if (!$this->hasCompletedOrders($email) && $this->checkSameAmountOrders($order_total, $email, $order_id)) {
            $wc_order->delete(true);
            $error_to_show = $this->get_error_message();
            \wc_add_notice( esc_html( $error_to_show ), 'error' );
            return $order;
        }

        // Now check with OOPSpam API
        $message = isset($post['order_comments']) ? sanitize_text_field($post['order_comments']) : '';
        if (empty($message)) {
            $message = sanitize_text_field($wc_order->get_customer_note());
        }
        $showError = $this->checkEmailAndIPInOOPSpam(sanitize_email($email), $message, $this->buildOrderMetadata($wc_order));
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
        
        // $order is a WC_Order object from woocommerce_store_api_checkout_order_processed
        $wc_order = $order;
        $email = $wc_order->get_billing_email();

        // Check for allowed email/IP
        $hasAllowedEmail = $email ? $this->isEmailAllowed($email, $wc_order->get_data()) : false;

        if ($hasAllowedEmail) {
            return $order;
        }

        $userIP = oopspamantispam_get_ip();
        $hasAllowedIP = oopspam_is_ip_allowed($userIP);

        if (!$hasAllowedIP) {
            $hasCompletedOrders = !empty($email) ? $this->hasCompletedOrders($email) : false;

            if (!$hasCompletedOrders) {
                // Failed payment velocity check
                if ($this->checkFailedPaymentVelocity($email)) {
                    $wc_order->delete(true);
                    $error_to_show = $this->get_error_message();
                    $this->block_checkout_with_error($error_to_show);
                    return $order;
                }

            }
        }

        // Check for blocked order total
        $order_total = floatval($wc_order->get_total());

        if ($this->checkBlockedOrderTotal($order_total, $email, $wc_order->get_id())) {
            $wc_order->delete(true);
            $error_to_show = $this->get_error_message();
            $this->block_checkout_with_error($error_to_show);
            return $order;
        }

        if ($this->checkBlockedBillingAddress($wc_order, $wc_order->get_id())) {
            $wc_order->delete(true);
            $error_to_show = $this->get_error_message();
            $this->block_checkout_with_error($error_to_show);
            return $order;
        }

        // Same-amount orders velocity check
        if (!$this->hasCompletedOrders($email) && $this->checkSameAmountOrders($order_total, $email, $wc_order->get_id())) {
            $wc_order->delete(true);
            $error_to_show = $this->get_error_message();
            $this->block_checkout_with_error($error_to_show);
            return $order;
        }
            
        // Now check with OOPSpam API
        $message = sanitize_text_field($wc_order->get_customer_note());
        $showError = $this->checkEmailAndIPInOOPSpam(sanitize_email($email), $message, $this->buildOrderMetadata($wc_order));
        if ($showError) {
            $error_to_show = $this->get_error_message();
            $this->block_checkout_with_error($error_to_show);
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
        
        // $order is a WC_Order object from woocommerce_checkout_order_processed
        $wc_order = $order;
        $email = $wc_order->get_billing_email();

        // Check for allowed email/IP
        $hasAllowedEmail = $email ? $this->isEmailAllowed($email, $wc_order->get_data()) : false;

        if ($hasAllowedEmail) {
            return $order;
        }
        
        // Check for blocked order total
        $order_total = floatval($wc_order->get_total());

        if ($this->checkBlockedOrderTotal($order_total, $email, $order_id)) {
            $wc_order->delete(true);
            $error_to_show = $this->get_error_message();
            \wc_add_notice( esc_html( $error_to_show ), 'error' );
            return $order;
        }

        if ($this->checkBlockedBillingAddress($wc_order, $order_id)) {
            $wc_order->delete(true);
            $error_to_show = $this->get_error_message();
            \wc_add_notice( esc_html( $error_to_show ), 'error' );
            return $order;
        }

        // Same-amount orders velocity check
        if (!$this->hasCompletedOrders($email) && $this->checkSameAmountOrders($order_total, $email, $order_id)) {
            $wc_order->delete(true);
            $error_to_show = $this->get_error_message();
            \wc_add_notice( esc_html( $error_to_show ), 'error' );
            return $order;
        }
        
        // Now check with OOPSpam API
        $message = sanitize_text_field($wc_order->get_customer_note());
        if (empty($message) && isset($posted_data['order_comments'])) {
            $message = sanitize_text_field($posted_data['order_comments']);
        }
        $showError = $this->checkEmailAndIPInOOPSpam(sanitize_email($email), $message, $this->buildOrderMetadata($wc_order));
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
        
        // Check for allowed email/IP before velocity checks
        $hasAllowedEmail = !empty($email) ? $this->isEmailAllowed($email, $_POST) : false;
        $userIP = oopspamantispam_get_ip();
        $hasAllowedIP = oopspam_is_ip_allowed($userIP);

        if (!$hasAllowedEmail && !$hasAllowedIP) {
            // Check if user has completed orders - skip velocity checks for returning customers
            $hasCompletedOrders = !empty($email) ? $this->hasCompletedOrders($email) : false;

            if (!$hasCompletedOrders) {
                // Failed payment velocity check
                if ($this->checkFailedPaymentVelocity($email)) {
                    $error_to_show = $this->get_error_message();
                    \wc_add_notice( esc_html( $error_to_show ), 'error' );
                    return;
                }

            }
        }

        // Note: Blocked order total and same-amount checks are handled in the order processing functions
        // to avoid duplicate entries and ensure proper logging
        
        // Build lightweight order metadata from POST for pre-order validation
        $postMetadata = array_filter(array(
            'billing_country'  => isset($_POST['billing_country']) ? sanitize_text_field($_POST['billing_country']) : '',
            'shipping_country' => isset($_POST['shipping_country']) ? sanitize_text_field($_POST['shipping_country']) : '',
            'billing_state'    => isset($_POST['billing_state']) ? sanitize_text_field($_POST['billing_state']) : '',
            'shipping_state'   => isset($_POST['shipping_state']) ? sanitize_text_field($_POST['shipping_state']) : '',
            'billing_city'     => isset($_POST['billing_city']) ? sanitize_text_field($_POST['billing_city']) : '',
            'shipping_city'    => isset($_POST['shipping_city']) ? sanitize_text_field($_POST['shipping_city']) : '',
            'payment_method'   => isset($_POST['payment_method']) ? sanitize_text_field($_POST['payment_method']) : '',
        ), function($value) {
            return $value !== null && $value !== '';
        });
        
        $showError = $this->checkEmailAndIPInOOPSpam(sanitize_email($email), sanitize_text_field($message), $postMetadata);
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

    /**
     * Build non-sensitive order metadata for fraud detection.
     *
     * @param \WC_Order|null $order The WooCommerce order object, or null.
     * @return array Order metadata safe for logging and API reporting.
     */
    private function buildOrderMetadata($order) {
        if (!$order || !is_a($order, 'WC_Order')) {
            return array();
        }

        $metadata = array(
            'payment_method'  => $order->get_payment_method(),
            'currency'        => $order->get_currency(),
            'order_total'     => floatval($order->get_total()),
            'shipping_total'  => floatval($order->get_shipping_total()),
            'discount_total'  => floatval($order->get_discount_total()),
            'coupons'         => $order->get_coupon_codes(),
            'item_count'      => $order->get_item_count(),
            'is_guest'        => $order->get_user_id() === 0,
            'billing_country' => $order->get_billing_country(),
            'shipping_country'=> $order->get_shipping_country(),
            'billing_state'   => $order->get_billing_state(),
            'shipping_state'  => $order->get_shipping_state(),
            'billing_city'    => $order->get_billing_city(),
            'shipping_city'   => $order->get_shipping_city(),
            'shipping_method' => $order->get_shipping_method(),
        );

        // Determine if order has digital/downloadable items
        $has_digital = false;
        $product_categories = array();
        $product_skus = array();

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product) {
                if ($product->is_virtual() || $product->is_downloadable()) {
                    $has_digital = true;
                }
                $sku = $product->get_sku();
                if (!empty($sku)) {
                    $product_skus[] = $sku;
                }
                $cats = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'));
                if (!empty($cats) && !is_wp_error($cats)) {
                    foreach ($cats as $cat) {
                        $product_categories[] = $cat;
                    }
                }
            }
        }

        $metadata['has_digital_items']   = $has_digital;
        $metadata['product_categories']  = array_values(array_unique($product_categories));
        $metadata['product_skus']        = $product_skus;

        // Strip empty/null values to keep metadata lean
        return array_filter($metadata, function($value) {
            return $value !== null && $value !== '' && $value !== array();
        });
    }

    /**
     * Build the raw entry array combining IP and email.
     *
     * @param string $userIP Client IP address.
     * @param string $email  Customer email.
     * @return array Raw entry data for storage.
     */
    private function buildRawEntry($userIP, $email) {
        return array(
            'IP'    => $userIP,
            'email' => $email,
        );
    }

    public function checkEmailAndIPInOOPSpam($email, $message, $orderMetadata = array())
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
                $rawEntry = $this->buildRawEntry($userIP, $email);
                $frmEntry = [
                    "Score" => 0, // Low score since we trust returning customers
                    "Message" => $message,
                    "IP" => $userIP,
                    "Email" => $email,
                    "RawEntry" => json_encode($rawEntry),
                    "FormId" => "WooCommerce",
                    "OrderMetadata" => $orderMetadata,
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
            $rawEntry = $this->buildRawEntry($userIP, $email);
            $frmEntry = [
                "Score" => $detectionResult["Score"],
                "Message" => $message,
                "IP" => $userIP,
                "Email" => $email,
                "RawEntry" => json_encode($rawEntry),
                "FormId" => "WooCommerce",
                "OrderMetadata" => $orderMetadata,
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
 * Check if the current request is a WooCommerce Store API request (block-based checkout).
 */
private function is_store_api_request() {
    if (defined('REST_REQUEST') && REST_REQUEST) {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        if (strpos($request_uri, 'wc/store') !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Block checkout with an error message, using the appropriate method
 * depending on whether this is a Store API (block checkout) or classic checkout.
 *
 * For Store API requests, throws a RouteException so WooCommerce returns
 * a proper JSON error response. For classic checkout, uses wc_add_notice.
 * Falls back to wp_die() if neither is available.
 */
private function block_checkout_with_error($error_message) {
    if ($this->is_store_api_request() && class_exists('\Automattic\WooCommerce\StoreApi\Exceptions\RouteException')) {
        throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
            'oopspam_spam_detected',
            esc_html($error_message),
            400
        );
    }

    if (function_exists('wc_add_notice')) {
        \wc_add_notice(esc_html($error_message), 'error');
        return;
    }

    wp_die(esc_html($error_message));
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

/**
 * Add custom order actions to the "Order actions" metabox on the Edit Order screen.
 *
 * @param array    $actions Existing order actions.
 * @param \WC_Order $order   The order object.
 * @return array Modified order actions.
 */
public function add_order_actions($actions, $order) {
    if (!is_a($order, 'WC_Order')) {
        return $actions;
    }

    $is_blocked = $order->get_meta('_oopspam_blocked', true);

    if ($is_blocked) {
        $actions['oopspam_undo_block'] = __('Undo Block (OOPSpam)', 'oopspam-anti-spam');
    } else {
        $actions['oopspam_block_as_spam'] = __('Block as Spam (OOPSpam)', 'oopspam-anti-spam');
    }

    return $actions;
}

/**
 * Handle the "Block as Spam" order action from the Edit Order screen.
 *
 * @param \WC_Order $order The order object.
 */
public function handle_block_order_action($order) {
    $this->block_order($order);
}

/**
 * Handle the "Undo Block" order action from the Edit Order screen.
 *
 * @param \WC_Order $order The order object.
 */
public function handle_undo_block_order_action($order) {
    $this->undo_block_order($order);
}

/**
 * Core logic to block an order as spam.
 * - Reports to OOPSpam API
 * - Adds email/IP to manual moderation blocked lists
 * - Stores a spam entry
 * - Marks the order as blocked (metadata)
 *
 * @param \WC_Order $order The order object.
 * @return bool True on success, false on failure.
 */
private function block_order($order) {
    if (!is_a($order, 'WC_Order')) {
        return false;
    }

    $email = $order->get_billing_email();
    $userIP = $order->get_customer_ip_address();
    if (empty($userIP)) {
        $userIP = oopspamantispam_get_ip();
    }

    $customer_note = $order->get_customer_note();
    $message = !empty($customer_note) ? $customer_note : '';

    // Build raw entry with order metadata
    $rawEntry = array(
        'IP'            => $userIP,
        'email'         => $email,
        'order_id'      => $order->get_id(),
        'order_total'   => $order->get_total(),
        'order_status'  => $order->get_status(),
        'payment_method'=> $order->get_payment_method(),
    );

    $orderMetadata = $this->buildOrderMetadata($order);

    // Report to OOPSpam API as spam
    $metadata = json_encode(array_merge($rawEntry, $orderMetadata));
    $reportResult = oopspamantispam_report_OOPSpam($message, $userIP, $email, true, $metadata);

    if ($reportResult === false) {
        $this->set_action_notice(__('Failed to report order to OOPSpam API.', 'oopspam-anti-spam'), 'error');
        return false;
    }

    // Add email to blocked list
    if (!empty($email)) {
        oopspam_add_manual_moderation_entry('mm_blocked_emails', $email, true);
    }

    // Add IP to blocked list
    if (!empty($userIP)) {
        oopspam_add_manual_moderation_entry('mm_blocked_ips', $userIP);
    }

    // Store spam entry
    $frmEntry = [
        "Score"      => 6,
        "Message"    => $message,
        "IP"         => $userIP,
        "Email"      => $email,
        "RawEntry"   => json_encode($rawEntry),
        "FormId"     => "WooCommerce",
        "OrderMetadata" => $orderMetadata,
    ];
    oopspam_store_spam_submission($frmEntry, "Manually blocked from Orders admin");

    // Mark order as blocked
    $order->update_meta_data('_oopspam_blocked', true);
    $order->add_order_note(
        sprintf(
            /* translators: 1: email, 2: IP address */
            __('Order blocked as spam by OOPSpam. Email: %1$s, IP: %2$s', 'oopspam-anti-spam'),
            $email,
            $userIP
        )
    );
    $order->save();

    $this->set_action_notice(
        sprintf(
            /* translators: %s: order number */
            __('Order #%s has been blocked as spam.', 'oopspam-anti-spam'),
            $order->get_order_number()
        ),
        'success'
    );

    return true;
}

/**
 * Core logic to undo a block on an order.
 * - Removes email/IP from manual moderation blocked lists
 * - Adds email/IP to allowed lists
 * - Removes the blocked metadata flag
 *
 * @param \WC_Order $order The order object.
 * @return bool True on success, false on failure.
 */
private function undo_block_order($order) {
    if (!is_a($order, 'WC_Order')) {
        return false;
    }

    $email = $order->get_billing_email();
    $userIP = $order->get_customer_ip_address();
    if (empty($userIP)) {
        $userIP = oopspamantispam_get_ip();
    }

    $success = false;

    // Remove email from blocked list and add to allowed list
    if (!empty($email)) {
        $email_removed = oopspam_remove_manual_moderation_entry('mm_blocked_emails', $email, true);
        $email_allowed = oopspam_add_manual_moderation_entry('mm_allowed_emails', $email, true);
        $success = $email_removed || $email_allowed || $success;
    }

    // Remove IP from blocked list and add to allowed list
    if (!empty($userIP)) {
        $ip_removed = oopspam_remove_manual_moderation_entry('mm_blocked_ips', $userIP);
        $ip_allowed = oopspam_add_manual_moderation_entry('mm_allowed_ips', $userIP);
        $success = $ip_removed || $ip_allowed || $success;
    }

    // Remove blocked metadata flag
    $order->delete_meta_data('_oopspam_blocked');
    $order->add_order_note(
        sprintf(
            /* translators: 1: email, 2: IP address */
            __('Order block undone by OOPSpam. Email: %1$s, IP: %2$s added to allow list.', 'oopspam-anti-spam'),
            $email,
            $userIP
        )
    );
    $order->save();

    $this->set_action_notice(
        sprintf(
            /* translators: %s: order number */
            __('Block on order #%s has been undone. Email &amp; IP added to allow list.', 'oopspam-anti-spam'),
            $order->get_order_number()
        ),
        'success'
    );

    return $success;
}

/**
 * Add custom bulk actions to the Orders list page dropdown.
 *
 * @param array $bulk_actions Existing bulk actions.
 * @return array Modified bulk actions.
 */
public function add_bulk_actions($bulk_actions) {
    $bulk_actions['oopspam_bulk_block'] = __('Block as Spam (OOPSpam)', 'oopspam-anti-spam');
    $bulk_actions['oopspam_bulk_undo_block'] = __('Undo Block (OOPSpam)', 'oopspam-anti-spam');
    return $bulk_actions;
}

/**
 * Handle bulk actions on the Orders list page.
 *
 * @param string $redirect_url The redirect URL.
 * @param string $action       The action being performed.
 * @param array  $order_ids    Array of order IDs.
 * @return string Modified redirect URL.
 */
public function handle_bulk_block_orders($redirect_url, $action, $order_ids) {
    if ($action === 'oopspam_bulk_block') {
        $blocked = 0;
        $failed = 0;

        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if ($order && $this->block_order($order)) {
                $blocked++;
            } else {
                $failed++;
            }
        }

        // Clear per-order notices since we're setting a bulk one
        $this->clear_action_notice();

        $notice_type = ($failed === 0) ? 'success' : 'warning';
        $message = sprintf(
            /* translators: 1: number blocked, 2: number failed */
            __('%1$d order(s) blocked as spam. %2$d failed.', 'oopspam-anti-spam'),
            $blocked,
            $failed
        );
        $this->set_action_notice($message, $notice_type);
    }

    if ($action === 'oopspam_bulk_undo_block') {
        $undone = 0;
        $failed = 0;

        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if ($order && $this->undo_block_order($order)) {
                $undone++;
            } else {
                $failed++;
            }
        }

        $this->clear_action_notice();

        $notice_type = ($failed === 0) ? 'success' : 'warning';
        $message = sprintf(
            /* translators: 1: number undone, 2: number failed */
            __('%1$d order(s) block undone. %2$d failed.', 'oopspam-anti-spam'),
            $undone,
            $failed
        );
        $this->set_action_notice($message, $notice_type);
    }

    return $redirect_url;
}

/**
 * Store a notice to be displayed after redirect (using transients).
 *
 * @param string $message The notice message.
 * @param string $type    Notice type: success, error, warning.
 */
private function set_action_notice($message, $type = 'success') {
    set_transient('oopspam_order_action_notice', array(
        'message' => $message,
        'type'    => $type,
    ), 60);
}

/**
 * Clear the stored action notice.
 */
private function clear_action_notice() {
    delete_transient('oopspam_order_action_notice');
}

/**
 * Display admin notice after a manual/bulk block action.
 */
public function display_block_action_notices() {
    $screen = get_current_screen();
    if (!$screen || !in_array($screen->id, array('shop_order', 'edit-shop_order', 'woocommerce_page_wc-orders'), true)) {
        return;
    }

    $notice = get_transient('oopspam_order_action_notice');
    if (!$notice || !is_array($notice)) {
        return;
    }

    $type = isset($notice['type']) ? $notice['type'] : 'success';
    $message = isset($notice['message']) ? $notice['message'] : '';

    if (!empty($message)) {
        printf(
            '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
            esc_attr($type),
            wp_kses_post($message)
        );
    }

    delete_transient('oopspam_order_action_notice');
}
}