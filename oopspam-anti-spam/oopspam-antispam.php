<?php
/**
 * Plugin Name: OOPSpam Anti-Spam
 * Plugin URI: https://www.oopspam.com/
 * Description: Stop bots and manual spam from reaching you in comments & contact forms. All with high accuracy, accessibility, and privacy.
 * Version: 1.2.15
 * Author: OOPSpam
 * URI: https://www.oopspam.com/
 * License: GPL2
 */
if (!function_exists('add_action')) {
    die();
}
// include a helper class to call the OOPSpam API
require_once dirname(__FILE__) . '/OOPSpamAPI.php';

if (is_admin()) { //if admin include the admin specific functions
    require_once dirname(__FILE__) . '/options.php';

    // Loading oopspam plugin review code
    require 'include/libs/class-plugin-review.php';
    new OOPSpam_Plugin_Review(
        array(
            'slug' => 'oopspam', // The plugin slug
            'name' => 'OOPSpam Anti-Spam', // The plugin name
            'time_limit' => WEEK_IN_SECONDS, // The time limit at which notice is shown
        )
    );
}

// Include the plugin helpers.
require_once dirname(__FILE__) . '/include/helpers.php';
require_once dirname(__FILE__) . '/include/oopspam-country-list.php';
require_once dirname(__FILE__) . '/include/oopspam-language-list.php';
require_once dirname(__FILE__) . '/include/UI/display-ham-entries.php';
require_once dirname(__FILE__) . '/include/UI/display-spam-entries.php';

add_action('init', 'do_output_buffer');
function do_output_buffer()
{
    ob_start();
}

// Used to detect installed plugins.
require_once ABSPATH . 'wp-admin/includes/plugin.php';

// Integrations
require_once dirname(__FILE__) . '/integration/NinjaForms.php';
require_once dirname(__FILE__) . '/integration/GravityForms.php';
require_once dirname(__FILE__) . '/integration/ContactForm7.php';
require_once dirname(__FILE__) . '/integration/ElementorForm.php';
require_once dirname(__FILE__) . '/integration/FluentForms.php';
require_once dirname(__FILE__) . '/integration/WPForms.php';
require_once dirname(__FILE__) . '/integration/FormidableForms.php';
require_once dirname(__FILE__) . '/integration/GiveWP.php';
require_once dirname(__FILE__) . '/integration/WPRegistration.php';
require_once dirname(__FILE__) . '/integration/BricksForm.php';
require_once dirname(__FILE__) . '/integration/WSForm.php';
require_once dirname(__FILE__) . '/integration/Toolset.php';
require_once dirname(__FILE__) . '/integration/PionetForms.php';
require_once dirname(__FILE__) . '/integration/Kadence.php';
require_once dirname(__FILE__) . '/integration/WPDiscuz.php';
require_once dirname(__FILE__) . '/integration/Mailpoet.php';
require_once dirname(__FILE__) . '/integration/Forminator.php';
require_once dirname(__FILE__) . '/integration/BeaverBuilder.php';
require_once dirname(__FILE__) . '/integration/UMember.php';
require_once dirname(__FILE__) . '/integration/MemberPress.php';
require_once dirname(__FILE__) . '/integration/Pmpro.php';


add_action('init', function () {
    // Check if Breakdance is available
    if (!function_exists('\Breakdance\Forms\Actions\registerAction') || !class_exists('\Breakdance\Forms\Actions\Action')) {
        return;
    }

    // Include the action class file
    require_once (dirname(__FILE__) . '/integration/BreakdanceForm.php');

    // Register the action
    \Breakdance\Forms\Actions\registerAction(new OOPSpamBreakdanceAction());
});

require_once dirname(__FILE__) . '/integration/WooCommerce.php';
add_action('plugins_loaded', array('\OOPSPAM\WOOCOMMERCE\WooSpamProtection', 'getInstance'));

require_once dirname(__FILE__) . '/db/oopspam-spamentries.php';

register_activation_hook(__FILE__, 'oopspam_plugin_activate');
register_activation_hook(__FILE__, 'oopspam_db_install');
register_deactivation_hook(__FILE__, 'oopspam_plugin_deactivation');

// Migrate the privacy settings. Added: v. 1.2.14
register_activation_hook(__FILE__, 'oopspamantispam_check_run_migration');
add_action('plugins_loaded', 'oopspamantispam_check_run_migration');

function oopspamantispam_migrate_privacy_settings() {
    $old_options = get_option('oopspamantispam_settings');
    $privacy_options = get_option('oopspamantispam_privacy_settings', array());

    $privacy_fields = array('oopspam_is_check_for_ip', 'oopspam_is_check_for_email', 'oopspam_anonym_content');

    foreach ($privacy_fields as $field) {
        if (isset($old_options[$field])) {
            $privacy_options[$field] = $old_options[$field];
            unset($old_options[$field]);
        }
    }

    update_option('oopspamantispam_privacy_settings', $privacy_options);
    update_option('oopspamantispam_settings', $old_options);

    // Set a flag in the database to indicate that migration has been performed
    update_option('oopspamantispam_privacy_migration_completed', true);
}

function oopspamantispam_check_run_migration() {
    if (get_option('oopspamantispam_privacy_migration_completed') !== true) {
        oopspamantispam_migrate_privacy_settings();
    }
}

// Add two weeks & monthly intervals
function oopspam_schedule_intervals($schedules)
{

    try {
        if (!is_array($schedules)) {
            throw new Exception('The provided schedules parameter is not an array.');
        }

        // add a 'weekly' interval
        $schedules['oopspam-biweekly'] = array(
            'interval' => 1209600,
            'display' => __('Every two weeks'),
        );
        $schedules['oopspam-monthly'] = array(
            'interval' => MONTH_IN_SECONDS,
            'display' => __('Once a month'),
        );
        return $schedules;
    } catch (Exception $e) {
        // Handle the exception
        error_log('Error in oopspam_schedule_intervals: ' . $e->getMessage());
        return $schedules; // Return the original schedules array or handle it as needed
    }
}
add_filter('cron_schedules', 'oopspam_schedule_intervals');

// Schedule Cron Job Event
function oopspam_cron_job()
{

    try {
        $options = get_option('oopspamantispam_settings');

        // Set default intervals for Ham/Spam Entries table clean up
        if (!wp_next_scheduled('oopspam_cleanup_ham_entries_cron')) {
            // Once per month
            wp_schedule_event(strtotime('+1 month'), 'oopspam-monthly', 'oopspam_cleanup_ham_entries_cron');
            $options['oopspam_clear_ham_entries'] = "monthly";
        }
        if (!wp_next_scheduled('oopspam_cleanup_spam_entries_cron')) {
            // Once per month
            wp_schedule_event(strtotime('+1 month'), 'oopspam-monthly', 'oopspam_cleanup_spam_entries_cron');
            $options['oopspam_clear_spam_entries'] = "monthly";
        }

        update_option('oopspamantispam_settings', $options);
    } catch (Exception $e) {
        // Handle the exception
        error_log('oopspam_cron_job: ' . $e->getMessage());
    }
}

function oopspam_cleanup_ham_entries()
{
    // Truncate the table
    try {
        global $wpdb;
        $table = $wpdb->prefix . 'oopspam_frm_ham_entries';

        $wpdb->query("TRUNCATE TABLE $table");
        wp_send_json_success(array(
            'success' => true,
        ), 200);
    } catch (Exception $e) {
        // Handle the exception
        error_log('oopspam_cleanup_ham_entries: ' . $e->getMessage());
    }
}
function oopspam_cleanup_spam_entries()
{
    // Truncate the table
    try {
        global $wpdb;
        $table = $wpdb->prefix . 'oopspam_frm_spam_entries';
        $wpdb->query("TRUNCATE TABLE $table");
        wp_send_json_success(array(
            'success' => true,
        ), 200);
    } catch (Exception $e) {
        // Handle the exception
        error_log('oopspam_cleanup_spam_entries: ' . $e->getMessage());
    }
}
add_action('oopspam_cleanup_spam_entries_cron', 'oopspam_cleanup_spam_entries');
add_action('oopspam_cleanup_ham_entries_cron', 'oopspam_cleanup_ham_entries');

function oopspam_plugin_activate()
{
    // plugin activated
    do_action('oopspam_set_default_settings');
}

// Set default values
function oopspam_default_options()
{
    $options = get_option('oopspamantispam_settings');
    if (!isset($options['oopspam_api_key_source'])) {
        $default = array(
            'oopspam_is_check_for_length' => true,
            'oopspam_api_key_source' => 'OOPSpamDashboard',
            'oopspam_api_key_usage' => '0/0',
            'oopspam_clear_spam_entries' => 'monthly',
            'oopspam_clear_ham_entries' => 'monthly',
        );
        update_option('oopspamantispam_settings', $default);
    }
}

add_action('oopspam_set_default_settings', 'oopspam_default_options');

function oopspam_plugin_deactivation()
{
    // plugin deactivation
    wp_clear_scheduled_hook('oopspam_cleanup_ham_entries_cron');
    wp_clear_scheduled_hook('oopspam_cleanup_spam_entries_cron');
}

add_filter('plugin_action_links', 'oopspam_plugin_action_links', 10, 2);

function oopspam_plugin_action_links($links, $file)
{
    static $this_plugin;

    if (!$this_plugin) {
        $this_plugin = plugin_basename(__FILE__);
    }

    if ($file == $this_plugin) {
        // add Settings link on the plugins page
        $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wp_oopspam_settings_page">Settings</a>';
        array_unshift($links, $settings_link);
    }

    return $links;
}

function is_keyword_blocked($text) {
    // Get keywords
    $manual_moderation_options = get_option('manual_moderation_settings');
    $blocked_keywords = isset($manual_moderation_options['mm_blocked_keywords']) ? $manual_moderation_options['mm_blocked_keywords'] : '';

    
    if ('' == $blocked_keywords || empty($text)) {
        return false;
    }
    
    
    // Remove HTML tags
    $text_without_html = strtolower(wp_strip_all_tags($text));
    
    $words = explode("\n", $blocked_keywords);
    
    foreach ((array)$words as $word) {
        $word = strtolower(trim($word));
        
        // Skip empty lines.
        if (empty($word)) {
            continue;
        }
        

        $word = preg_quote($word, '~');
        
        $pattern = "~\b$word\b~i";
        if (preg_match($pattern, $text_without_html)) {
            return true;
        }
    }
    
    
    return false;
}

// Check if an email is blocked locally
function is_email_blocked($email) {
 
    $email = strtolower($email);
     // Get email
     $manual_moderation_options = get_option('manual_moderation_settings');
     $blocked_emails= isset($manual_moderation_options['mm_blocked_emails']) ? $manual_moderation_options['mm_blocked_emails'] : '';
     
     if ('' == $blocked_emails || empty($email)) {
        return false;
    }

     $emails = explode("\n", $blocked_emails);

     foreach ((array)$emails as $b_email) {
        $b_email = strtolower(trim($b_email));


        // Skip empty lines.
        if (empty($b_email)) {
            continue;
        }
        
        // Check if the blocked email contains a wildcard
        if (strpos($b_email, '*') !== false) {
            // Extract the domain part
            $blocked_domain = substr($b_email, strpos($b_email, '@') + 1);
            $email_domain = substr($email, strpos($email, '@') + 1);
            
            // Check if the provided email matches the blocked domain
            if (fnmatch($blocked_domain, $email_domain)) {
                return true;
            }
        } else {
            // Check if the provided email exactly matches the blocked email
            if ($b_email === $email) {
                return true;
            }
        }
    }
    return false;
}

// Check if an IP is blocked locally
function is_ip_blocked($ip) {
 
    // Get email
    $manual_moderation_options = get_option('manual_moderation_settings');
    $blocked_ips= isset($manual_moderation_options['mm_blocked_ips']) ? $manual_moderation_options['mm_blocked_ips'] : '';

    if ('' == $blocked_ips || empty($ip)) {
       return false;
   }
   

    $ips = explode("\n", $blocked_ips);

    foreach ((array)$ips as $b_ip) {
       $b_ip = trim($b_ip);
       
       // Skip empty lines.
       if (empty($b_ip)) {
           continue;
       }
       
       if ($b_ip === $ip) {
           return true;
       }
   }
   return false;
}

// Check if an email is allowed locally
function is_email_allowed($email) {
 
    $email = strtolower($email);
     // Get email
     $manual_moderation_options = get_option('manual_moderation_settings');
     $allowed_emails= isset($manual_moderation_options['mm_allowed_emails']) ? $manual_moderation_options['mm_allowed_emails'] : '';
     
     if ('' == $allowed_emails || empty($email)) {
        return false;
    }

     $emails = explode("\n", $allowed_emails);

     foreach ((array)$emails as $b_email) {
        $b_email = strtolower(trim($b_email));


        // Skip empty lines.
        if (empty($b_email)) {
            continue;
        }
        
        // Check if the allowed email contains a wildcard
        if (strpos($b_email, '*') !== false) {
            // Extract the domain part
            $allowed_domain = substr($b_email, strpos($b_email, '@') + 1);
            $email_domain = substr($email, strpos($email, '@') + 1);
            
            // Check if the provided email matches the allowed domain
            if (fnmatch($allowed_domain, $email_domain)) {
                return true;
            }
        } else {
            // Check if the provided email exactly matches the allowed email
            if ($b_email === $email) {
                return true;
            }
        }
    }
    return false;
}

// Check if an IP is allowed locally
function is_ip_allowed($ip) {
 
    // Get email
    $manual_moderation_options = get_option('manual_moderation_settings');
    $allowed_ips= isset($manual_moderation_options['mm_allowed_ips']) ? $manual_moderation_options['mm_allowed_ips'] : '';

    if ('' == $allowed_ips || empty($ip)) {
       return false;
   }
   

    $ips = explode("\n", $allowed_ips);

    foreach ((array)$ips as $b_ip) {
       $b_ip = trim($b_ip);
       
       // Skip empty lines.
       if (empty($b_ip)) {
           continue;
       }
       
       if ($b_ip === $ip) {
           return true;
       }
   }
   return false;
}


function containsUrl($text) {
    // The Regular Expression filter to detect URLs
    $reg_exUrl = "/(?i)\b((?:https?:\/\/|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}\/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:'\".,<>?«»“”‘’]))/";

    // Check if there is a URL in the text
    return preg_match($reg_exUrl, $text);
}


function oopspamantispam_call_OOPSpam($commentText, $commentIP, $email, $returnReason, $type)
{

    // Check blocked emails, IPs, keywords locally
    $hasBlockedKeyword = is_keyword_blocked($commentText);
    $hasBlockedEmail = is_email_blocked($email);
    $hasBlockedIP = is_ip_blocked($commentIP);
    $hasAllowedEmail = is_email_allowed($email);
    $hasAllowedIP = is_ip_allowed($commentIP);

    if ($hasAllowedEmail || $hasAllowedIP) {
    
        // The entry allowed locally by the Manual moderation settings
        if ($returnReason) {
            $reason = [
                "Score" => 0,
                "isItHam" => true,
            ];
            return $reason;
        }
        return false;
    }

    if ($hasBlockedKeyword || $hasBlockedEmail || $hasBlockedIP) {
    
            // The entry blocked locally by the Manual moderation settings
            if ($returnReason) {
                $reason = [
                    "Score" => 6,
                    "isItHam" => false,
                    "Reason" => "Block under the Manual Moderation"
                ];
                return $reason;
            }
            return false;
    }
    
    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');

    // Check if the message contains any URLs
    $blockURLs = (isset($options['oopspam_is_urls_allowed']) ? $options['oopspam_is_urls_allowed'] : false);
    if ($blockURLs) {
        $hasURL = containsUrl($commentText);
        if ($hasURL) {
            if ($returnReason) {
                $reason = [
                    "Score" => 6,
                    "isItHam" => false,
                    "Reason" => "An URL in the message"
                ];
                return $reason;
            }
            return false;
        }
    }

    $apiKey = oopspamantispam_checkIfValidKey();
    
    $countryallowlistSetting = (get_option('oopspam_countryallowlist') != null ? get_option('oopspam_countryallowlist') : [""]);
    $countryblocklistSetting = (get_option('oopspam_countryblocklist') != null ? get_option('oopspam_countryblocklist') : [""]);
    $languageallowlistSetting = (get_option('oopspam_languageallowlist') != null ? get_option('oopspam_languageallowlist') : [""]);
    $checkForLength = (isset($options['oopspam_is_check_for_length']) ? $options['oopspam_is_check_for_length'] : false);
    $isLoggable = (isset($options['oopspam_is_loggable']) ? $options['oopspam_is_loggable'] : false);
    $blockTempEmail = (isset($options['oopspam_block_temp_email']) ? $options['oopspam_block_temp_email'] : false);


    // Attempt to anonymize messages
    if (isset($privacyOptions['oopspam_anonym_content']) && $privacyOptions['oopspam_anonym_content'] && !empty($commentText)) {
        $email_regex = '/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i';
        $address_regex = '/\d+\s[A-z]+\s[A-z]+/';
        $phoneNumber_regex = '/(?:\+|\d+)(?:\-|\s|\d)/';
        // $name_regex = '/\p{Lu}\p{L}+\s\p{Lu}\p{L}+/';

        $commentText = preg_replace($email_regex, '', $commentText);
        $commentText = preg_replace($address_regex, '', $commentText);
        $commentText = preg_replace($phoneNumber_regex, '', $commentText);
        // $commentText = preg_replace($name_regex, '', $commentText);
    }

    // Don't send Email if not allowed by user
    if (isset($privacyOptions['oopspam_is_check_for_email']) && $privacyOptions['oopspam_is_check_for_email']) {
        $email = "";
    }

    // Allow devs to apply custom filter and return a score
    // @return $filtered_score: 0 for ham, 6 for spam
    $filtered_score = apply_filters('oopspam_check_for_spam', $commentText, $commentIP, $email);
    if (!empty($filtered_score) && $filtered_score != $commentText) {

        $isItHam = $filtered_score < 3 ? true : false;
        if ($returnReason) {
            $reason = [
                "Score" => $filtered_score,
                "isItHam" => $isItHam,
            ];
            return $reason;
        }
        return $isItHam;
    }


    // Bypass content length check as GiveWP & Woo usually doesn't have content field
    if ($type === "give" || $type === "woo"
    || $type === "mailpoet" || $type === "search" 
    || $type === "wpregister" || $type === "umember" || $type === "mpress"
    || $type === "pmp") {
        $checkForLength = false;
    }

    // If length check allowed then anything shorter than 20 should return spam
    if ($checkForLength && strlen($commentText) <= 20) {
        if ($returnReason) {
            $reason = [
                "Score" => 6,
                "isItHam" => false,
                "Reason" => "Consider short messages as spam setting"
            ];

            return $reason;
        }
        return false;
    }

    if (oopspamantispam_checkIfValidKey()) {

        $OOPSpamAPI = new OOPSpamAPI($apiKey, $checkForLength, $isLoggable, $blockTempEmail);
        
        // Unicode support
        $commentText = mb_convert_encoding($commentText, "UTF-8");

        $response = $OOPSpamAPI->SpamDetection($commentText, 
        $commentIP, 
        $email, 
        $countryallowlistSetting, 
        $languageallowlistSetting, 
        $countryblocklistSetting);

        $response_code = wp_remote_retrieve_response_code($response);
        if (!is_wp_error($response) && $response_code == "200") {
            update_option('over_rate_limit', false);

            $response = json_decode($response['body'], true);
            $api_reason = extractReasonFromAPIResponse($response);

            $currentThreshold = oopspamantispam_get_spamscore_threshold();

            if ($response['Score'] >= $currentThreshold) {
                // It is spam
                if ($returnReason) {
                    $reason = [
                        "Score" => $response['Score'],
                        "isItHam" => false,
                        "Reason" => $api_reason
                    ];
                    return $reason;
                }
                return false;
            } else {
                // It is ham
                if ($returnReason) {
                    $reason = [
                        "Score" => $response['Score'],
                        "isItHam" => true,
                    ];
                    return $reason;
                }
                return true;
            }

        } else if (!is_wp_error($response) && $response_code == "429") {
            // The API limit is reached or some other errors
            update_option('over_rate_limit', true);
        } else {
            // Allow all submission as no analyses are done.
            return $returnReason ? ["Score" => 0, "isItHam" => true] : true;
            if (is_wp_error($response)) {
                echo $response->get_error_message();
            }
        }
        unset($OOPSpamAPI);
    }
}


function extractReasonFromAPIResponse($response) {

    if (isset($response['Details'])) {
        $details = $response['Details'];

        $booleanChecks = [
            'isIPBlocked' => 'IP blocked',
            'isEmailBlocked' => 'Email blocked',
            'isContentTooShort' => 'Content short'
        ];

        foreach ($booleanChecks as $key => $reason) {
            if (isset($details[$key]) && $details[$key] === true) {
                return $reason;
            }
        }

        if (isset($details['isContentSpam']) && $details['isContentSpam'] === 'spam') {
            return 'The message is spam';
        }

        // Check for language and country mismatch
        if (isset($details['langMatch']) && $details['langMatch'] === false) {
            return 'The language is not allowed';
        }

        if (isset($details['countryMatch']) && $details['countryMatch'] === false && $response['Score'] >= 6) {
            return 'The country is not allowed';
        }
    }


    // If no specific reason found, use the overall score
    if (isset($response['Score']) && $response['Score'] >= 3 ) {
        return 'High score';
    }

    // If no reason found at all
    return 'Unknown reason';
}

function oopspamantispam_report_OOPSpam($commentText, $commentIP, $email, $isSpam)
{

    $apiKey = oopspamantispam_checkIfValidKey();

    $options = get_option('oopspamantispam_settings');
    $countryallowlistSetting = (get_option('oopspam_countryallowlist') != null ? get_option('oopspam_countryallowlist') : [""]);
    $countryblocklistSetting = (get_option('oopspam_countryblocklist') != null ? get_option('oopspam_countryblocklist') : [""]);
    $languageallowlistSetting = (get_option('oopspam_languageallowlist') != null ? get_option('oopspam_languageallowlist') : [""]);
    $checkForLength = (isset($options['oopspam_is_check_for_length']) ? $options['oopspam_is_check_for_length'] : false);
    $blockTempEmail = (isset($options['oopspam_block_temp_email']) ? $options['oopspam_block_temp_email'] : false);


    if (oopspamantispam_checkIfValidKey()) {

        $OOPSpamAPI = new OOPSpamAPI($apiKey, $checkForLength, 0, $blockTempEmail);
        $response = $OOPSpamAPI->Report($commentText, $commentIP, $email, $countryallowlistSetting, $languageallowlistSetting, $countryblocklistSetting, $isSpam);

        $response_code = wp_remote_retrieve_response_code($response);
        if (!is_wp_error($response) && $response_code == "201") {
            $response = json_decode($response['body'], true);
            return $response['message'];
        } else {
            if (is_wp_error($response)) {
                echo $response->get_error_message();
            }
            return false;
        }
        unset($OOPSpamAPI);
    }
}

function oopspamantispam_get_ip_address()
{
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '::1';
}

// Remove http & https from domain
function urlToDomain($url)
{
    return implode(array_slice(explode('/', preg_replace('/https?:\/\/(www\.)?/', '', $url)), 0, 1));
}


function oopspamantispam_check_comment($approved, $commentdata)
{
    // If admin skip
    if( current_user_can( 'administrator' ) ){
        return $approved;
    }

    $senderIp = "";
    $email = "";
    $isItSpam = false;
    $reason = "";
    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');
    $currentSpamFolder = oopspamantispam_get_folder_for_spam();

    $checkForLength = (isset($options['oopspam_is_check_for_length']) ? $options['oopspam_is_check_for_length'] : false);

    if (!isset($privacyOptions['oopspam_is_check_for_ip']) || $privacyOptions['oopspam_is_check_for_ip'] != true) {
        $senderIp = oopspamantispam_get_ip_address();
    }

    if (!isset($options['oopspam_is_check_for_email']) || $options['oopspam_is_check_for_email'] != true) {
        $email = sanitize_email($commentdata['comment_author_email']);
    }

    $trimmedURL = urlToDomain($commentdata['comment_author_url']);

    $sanitized_author_url = esc_url_raw($trimmedURL);
    $sanitized_content = sanitize_text_field($commentdata['comment_content']);

    $content = $sanitized_content . " " . $sanitized_author_url;

    // Capture non-URLs that doesn't contain dot and able to bypass WP's validation
    if (!empty($trimmedURL) && strpos($trimmedURL, ".") === false) {
        $isItSpam = true;
        $reason = "Invalid website";
    }    

    // If length check allowed then anything shorter than 20 should be considered as spam
    if ($checkForLength && strlen($commentdata['comment_content']) <= 20) {
        $isItSpam = true;
        $reason = "Consider short messages as spam setting";
    } else {
        // if Spam filtering is on and the OOPSpam Service considers it spam then mark it as spam
        $isItSpam = oopspamantispam_call_OOPSpam(sanitize_textarea_field($content), $senderIp, $email, false, "comment") == false;
        if ($isItSpam) {
            $reason = "High Score for the comment";
        }
    }

    $raw_entry = json_encode($commentdata);
    $frmEntry = [
        "Score" => 0,
        "Message" => $content,
        "IP" => $senderIp,
        "Email" => $email,
        "RawEntry" => $raw_entry,
        "FormId" => "comment",
    ];

    // Move the spam comment select folder (Trash or spam) and store
    if ($isItSpam) {
        // Manually assign the highest score to a comment because it is spam.
        $frmEntry["Score"] = 6;
        oopspam_store_spam_submission($frmEntry, $reason);

        return $currentSpamFolder;
        // TODO: Allow UI customization for this message
        // wp_die(__('Your comment has been flagged as spam.', 'oopspam'));
    } else {
        // It's ham
        $frmEntry["Score"] = 0;
        oopspam_store_ham_submission($frmEntry);
        return $approved;
    }

    // Return the processed comment data
    return $approved;
}

function oopspamantispam_check_pingback($approved, $commentdata)
{

    if ($commentdata['comment_type'] == 'pingback' || $commentdata['comment_type'] == 'trackback') {
        $senderIp = "";
        $email = "";
        $isItSpam = false;
        $options = get_option('oopspamantispam_settings');
        $privacyOptions = get_option('oopspamantispam_privacy_settings');
        $currentSpamFolder = oopspamantispam_get_folder_for_spam();

        $checkForLength = (isset($options['oopspam_is_check_for_length']) ? $options['oopspam_is_check_for_length'] : false);

        if (!isset($privacyOptions['oopspam_is_check_for_ip']) || $privacyOptions['oopspam_is_check_for_ip'] != true) {
            $senderIp = $commentdata['comment_author_IP'];
        }

        if (!isset($options['oopspam_is_check_for_email']) || $options['oopspam_is_check_for_email'] != true) {
            $email = sanitize_email($commentdata['comment_author_email']);
        }

        $trimmedURL = urlToDomain($commentdata['comment_author_url']);

        $sanitized_author_url = esc_url_raw($trimmedURL);
        $sanitized_content = sanitize_text_field($commentdata['comment_content']);

        $content = $sanitized_author_url . " " . $sanitized_content;

        // Capture non-URLs that doesn't contain dot and able to bypass WP's validation
        if (!empty($trimmedURL) && strpos($trimmedURL, ".") === false) {
            $isItSpam = true;
        }

        // If length check allowed then anything shorter than 20 should be considered as spam
        if ($checkForLength && strlen($commentdata['comment_content']) <= 20) {
            $isItSpam = true;
        } else if (oopspamantispam_call_OOPSpam(sanitize_textarea_field($content), $senderIp, $email, false, "comment") == false) {
            // if Spam filtering is on and the OOPSpam Service considers it spam then mark it as spam
            $isItSpam = true;
        }

        // Move the spam comment select folder (Trash or spam)
        if ($isItSpam) {
            $currentSpamFolder === "trash" ? "trash" : "spam";
            return $currentSpamFolder;
        }
    }
    return $approved;
}



add_filter('pre_comment_approved', 'oopspamantispam_check_comment', 10, 2);
add_filter( 'pre_comment_approved', 'oopspamantispam_check_pingback', 10, 2 );

add_action('admin_init', 'oopspam_admin_init');

add_action('pre_get_posts', 'check_search_for_spam');

// When a comment flagged as spam, let OOPSpam know too
add_action('transition_comment_status', 'oopspam_comment_spam_transition', 10, 3);
function oopspam_comment_spam_transition($new_status, $old_status, $comment) {
    if ($new_status === 'spam' && $old_status !== 'spam') {
         
         $commentText = $comment->comment_content; 
         $commentIP = $comment->comment_author_IP;
         $email = $comment->comment_author_email;  
         $isSpam = true;  
        
         oopspamantispam_report_OOPSpam($commentText, $commentIP, $email, $isSpam);
    }
}

function check_search_for_spam($query)
{
    // Only front end search
    if (!is_admin() && $query->is_main_query() && $query->is_search()) {

        $options = get_option('oopspamantispam_settings');
        $privacyOptions = get_option('oopspamantispam_privacy_settings');

        if (isset($options['oopspam_is_search_protection_on']) && $options['oopspam_is_search_protection_on'] == true)

        // WP Site Search is enabled only if IP check is allowed
        {
            if (!isset($privacyOptions['oopspam_is_check_for_ip']) || $privacyOptions['oopspam_is_check_for_ip'] != true) {

                // Get the user's IP address
                $userIP = oopspamantispam_get_ip();
                $sanitizedQuery = sanitize_text_field(get_search_query()); // Sanitize the search query

                $detectionResult = oopspamantispam_call_OOPSpam("", $userIP, "", true, "search");

                if (!isset($detectionResult["isItHam"])) {
                    return;
                }

                $frmEntry = [
                    "Score" => $detectionResult["Score"],
                    "Message" => $sanitizedQuery,
                    "IP" => $userIP,
                    "Email" => "",
                    "RawEntry" => "",
                    "FormId" => "WordPress Site Search",
                ];

                if (!$detectionResult["isItHam"]) {
                    // block search
                    oopspam_store_spam_submission($frmEntry, $detectionResult["Reason"]);
                    wp_redirect(home_url('/')); // Redirect to the homepage
                    exit();

                }

            }
        }

    }
}

// load the main.css style
function oopspam_admin_init()
{

    // Add corn jobs
    oopspam_cron_job();

    // Check if we are on the plugin settings page
    if (isset($_GET['page']) && (
        $_GET['page'] === 'wp_oopspam_settings_page' ||
        $_GET['page'] === 'wp_oopspam_frm_ham_entries' ||
        $_GET['page'] === 'wp_oopspam_frm_spam_entries'
    )) {

        wp_register_style('oopspam_stylesheet', plugins_url('styles/main.css', __FILE__));
        wp_register_style('tom-select', plugins_url('./include/libs/tom-select.min.css', __FILE__));
        add_action('admin_print_styles', 'oopspam_admin_style');
    
        require_once plugin_dir_path(__FILE__) . 'include/localize-script.php';
    
        function oopspam_admin_style()
        {
            wp_enqueue_style('oopspam_stylesheet');
            wp_enqueue_style('tom-select');
        }
     }

   
}
