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

    /**
     * Track if registration action is successful
     *
     * @var bool
     */
    private static $instance;

    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Plugin constructor.
     */
    public function __construct()
    {
        // Initialize actions & filters

        // Registration during checkout
        // Adds honeypot field
        add_action('woocommerce_register_form', [$this, 'oopspam_woocommerce_register_form'], 1, 0);
        add_action('woocommerce_after_checkout_billing_form', [$this, 'oopspam_woocommerce_register_form']);

        // Fires during checkout & My account
        add_action('woocommerce_register_post', array($this, 'oopspam_process_registration'), 10, 3);

        // Registration under My Account
        // Adds honeypot field
        add_action('woocommerce_process_registration_errors', [$this, 'oopspam_woocommerce_register_errors'], 10, 4);

        //Login Actions or process error
        // Adds honeypot field
        add_action('woocommerce_login_form', [$this, 'oopspam_woocommerce_login_form'], 1, 0);
        // Honeypot & API level check
        add_filter('woocommerce_process_login_errors', [$this, 'oopspam_woocommerce_login_errors'], 1, 1);

    }

    public function oopspam_woocommerce_register_errors($validation_error, $username, $password, $email)
    {

        $options = get_option('oopspamantispam_settings');

        // Check with honeypot field
        if (isset($_POST['wc_website_input']) && !empty($_POST['wc_website_input'])) {
            
            $isHoneypotDisabled = apply_filters('oopspam_woo_disable_honeypot', false);

            if ($isHoneypotDisabled) {
                return $validation_error;
            }

            $error_to_show = $options['oopspam_woo_spam_message'];
            $validation_error = new \WP_Error('oopspam_error', __($error_to_show, 'woocommerce'));

            $frmEntry = [
                "Score" => 6,
                "Message" => sanitize_text_field($_POST['wc_website_input']),
                "IP" => "",
                "Email" => $email,
                "RawEntry" => json_encode($_POST),
                "FormId" => "WooCommerce",
            ];
            oopspam_store_spam_submission($frmEntry, "Failed honeypot validation");

            return $validation_error;
        }

        return $validation_error;
    }

    public function oopspam_process_registration($username, $email, $errors)
    {

        $billing_first_name = "";
        if(isset($_POST["billing_first_name"])) { 
            $billing_first_name = $_POST["billing_first_name"];
        } else {
            $customer_data = WC()->session->get('customer');
            $billing_first_name = $customer_data['first_name'];
        }

        $options = get_option('oopspamantispam_settings');
        $cleanEmail = "";

        // Block if the first part of First Name has more than 2 characters
        if(!empty($billing_first_name)) {
            $cleanFName = sanitize_text_field($billing_first_name);
            if(!ctype_upper($cleanFName) && !empty($cleanFName)) {
                $firstPartOfFName = explode(" ", $cleanFName, 2)[0];
                if(strlen(preg_replace('![^A-Z]+!', '', $firstPartOfFName)) > 2){
                    
                    $frmEntry = [
                        "Score" => 6,
                        "Message" => "",
                        "IP" => "",
                        "Email" => $email,
                        "RawEntry" => json_encode($_POST),
                        "FormId" => "WooCommerce",
                    ];
                    oopspam_store_spam_submission($frmEntry, "Failed form data validation");

                    $error_to_show = $options['oopspam_woo_spam_message'];
                    $errors->add('oopspam_error', $error_to_show);
                    return $errors;
                }
            }
        }

        // First, check with honeypot field
        if (isset($_POST['wc_website_input']) && !empty($_POST['wc_website_input'])) {
            
            $isHoneypotDisabled = apply_filters('oopspam_woo_disable_honeypot', false);

            if ($isHoneypotDisabled) {
                return $errors;
            }

            $frmEntry = [
                "Score" => 6,
                "Message" => sanitize_text_field($_POST['wc_website_input']),
                "IP" => "",
                "Email" => $email,
                "RawEntry" => json_encode($_POST),
                "FormId" => "WooCommerce",
            ];
            oopspam_store_spam_submission($frmEntry, "Failed honeypot validation");

            $error_to_show = $options['oopspam_woo_spam_message'];
            $errors->add('oopspam_error', $error_to_show);
            return $errors;
        }

        // Second, If passed, then check with OOPSpam
        if (isset($email) && is_email($email)) {
            $cleanEmail = $email;
        }

        $showError = $this->checkEmailAndIPInOOPSpam(sanitize_email($cleanEmail));

        if ($showError) {
            $error_to_show = $options['oopspam_woo_spam_message'];
            $errors->add('oopspam_error', $error_to_show);
            return $errors;
        }

        return $errors;
    }

    /**
     * Login
     * Check if honeypot input has value. Allow if it exists and has an empty value.
     *
     * @hooked woocommerce_process_login_errors
     * @priority 1
     */
    public function oopspam_woocommerce_login_errors($errors)
    {

        $options = get_option('oopspamantispam_settings');
        $email = "";

        if (isset($_POST["username"]) && is_email($_POST["username"])) {
            $email = $_POST["username"];
        }

        // First, check with honeypot field
        if (!empty($_POST['wc_login_website_input'])) {

            $isHoneypotDisabled = apply_filters('oopspam_woo_disable_honeypot', false);

            if ($isHoneypotDisabled) {
                return $errors;
            }

            $error_to_show = $options['oopspam_woo_spam_message'];
            $errors = new \WP_Error('oopspam_error', __($error_to_show, 'woocommerce'));
            // Log the submission
            $frmEntry = [
                "Score" => 6,
                "Message" => sanitize_text_field($_POST['wc_login_website_input']),
                "IP" => "",
                "Email" => $email,
                "RawEntry" => json_encode($_POST),
                "FormId" => "WooCommerce",
            ];
            oopspam_store_spam_submission($frmEntry, "Failed honeypot validation");
            return $errors;
        }

        
        // Second, If passed, then check with OOPSpam
        $showError = $this->checkEmailAndIPInOOPSpam(sanitize_email($email));

        if ($showError) {
            $error_to_show = $options['oopspam_woo_spam_message'];

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

        if (!empty($options['oopspam_api_key']) && !empty($options['oopspam_is_woo_activated'])) {

        if (!isset($privacyOptions['oopspam_is_check_for_ip']) || $privacyOptions['oopspam_is_check_for_ip'] != true) {
            $userIP = oopspamantispam_get_ip();
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
     * Put in a honeypot trap in the customer registration form to fool automated registration bots
     *
     * @hooked woocommerce_register_form_end
     */
    public function oopspam_woocommerce_register_form()
    {

        ?>

<div class="form-row" style="<?php echo ((is_rtl()) ? 'right' : 'left'); ?>: -999em; position: absolute;" aria-hidden="true">

  <label for="wc_website_input">

    <?php _e('WC Name', 'woocommerce');?>

  </label>

  <input type="text" id="wc_website_input" name="wc_website_input" value="" tabindex="-1" autocomplete="off" class="input-field" />

</div>

<?php
}

    /**
     * @hooked woocommerce_login_form
     */
    public function oopspam_woocommerce_login_form()
    {
        ?>
    <div class="form-row" style="<?php echo ((is_rtl()) ? 'right' : 'left'); ?>: -999em; position: absolute;" aria-hidden="true">

      <label for="wc_login_website_input">

        <?php _e('WC Login', 'woocommerce');?>

      </label>

      <input type="text" id="wc_login_website_input" name="wc_login_website_input" value="" tabindex="-1" autocomplete="off" class="input-field" />
    </div>
<?php
}

}