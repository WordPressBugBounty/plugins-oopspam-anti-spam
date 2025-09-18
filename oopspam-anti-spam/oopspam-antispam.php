<?php
/**
 * Plugin Name: OOPSpam Anti-Spam
 * Plugin URI: https://www.oopspam.com/
 * Description: Stop bots and manual spam from reaching you in comments & contact forms. All with high accuracy, accessibility, and privacy.
 * Version: 1.2.49
 * Author: OOPSpam
 * Author URI: https://www.oopspam.com/
 * URI: https://www.oopspam.com/
 * Copyright: (c) 2017 - 2025, OOPSpam LLC
 * License: GPL3
 */
if (!function_exists('add_action')) {
    die();
}

// Include the setup wizard
require_once dirname(__FILE__) . '/setup-wizard.php';

// Start session handling before any output
function oopspam_start_session() {
    try {
        // Get rate limiting settings first
        $rtOptions = get_option('oopspamantispam_ratelimit_settings');
        
        // Only start session if minimum submission time is set
        if (!isset($rtOptions['oopspamantispam_min_submission_time'])) {
            return;
        }

        // Check if session is already active
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            $session_started = session_start([
                'cookie_httponly' => true,
                'cookie_secure' => is_ssl(),
                'use_only_cookies' => true,
                'cookie_samesite' => 'Strict'
            ]);
            
            if (!$session_started) {
                error_log('OOPSpam: Failed to start session');
            } else {
                // Initialize entry time if not set
                if (!isset($_SESSION['oopspam_entry_time'])) {
                    $_SESSION['oopspam_entry_time'] = time();
                }
                // Close session write immediately after setting values
                session_write_close();
            }
        }
    } catch (Exception $e) {
        error_log('OOPSpam: Session start error - ' . $e->getMessage());
    }
}

// Initialize session very early
add_action('init', 'oopspam_start_session', 1);

// Output buffering
function oopspam_do_output_buffer() {
    ob_start();
}
add_action('init', 'oopspam_do_output_buffer', 2);

// include a helper class to call the OOPSpam API
require_once dirname(__FILE__) . '/OOPSpamAPI.php';
use OOPSPAM\API\OOPSpamAPI;
use OOPSPAM\RateLimiting\OOPSpam_RateLimiter;

if (is_admin()) { //if admin include the admin specific functions
    require_once dirname(__FILE__) . '/options.php';
    
    // Add admin notice if plugin is not set up
    add_action('admin_notices', 'oopspam_admin_setup_notice');
}

// Include the plugin helpers.
require_once dirname(__FILE__) . '/include/helpers.php';
require_once dirname(__FILE__) . '/include/oopspam-country-list.php';
require_once dirname(__FILE__) . '/include/oopspam-language-list.php';
require_once dirname(__FILE__) . '/include/UI/display-ham-entries.php';
require_once dirname(__FILE__) . '/include/UI/display-spam-entries.php';
require_once dirname(__FILE__) . '/include/oopspam-rate-limiting.php';
require_once dirname(__FILE__) . '/include/Background/AsyncProcessor.php';

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
require_once dirname(__FILE__) . '/integration/JetpackForms.php';
require_once dirname(__FILE__) . '/integration/MC4WP.php';
require_once dirname(__FILE__) . '/integration/SureForms.php';
require_once dirname(__FILE__) . '/integration/SureCart.php';
require_once dirname(__FILE__) . '/integration/BreakdanceForm.php';
require_once dirname(__FILE__) . '/integration/Quform.php';
require_once dirname(__FILE__) . '/integration/HappyForms.php';


require_once dirname(__FILE__) . '/integration/WooCommerce.php';
add_action('plugins_loaded', array('\OOPSPAM\WOOCOMMERCE\WooSpamProtection', 'getInstance'));

require_once dirname(__FILE__) . '/db/oopspam-spamentries.php';
require_once dirname(__FILE__) . '/db/oopspam-db-ratelimit.php';

register_activation_hook(__FILE__, 'oopspam_plugin_activate');
register_activation_hook(__FILE__, 'oopspam_db_install');
register_deactivation_hook(__FILE__, 'oopspam_plugin_deactivation');

// Migrate the privacy settings. Added: v. 1.2.14
register_activation_hook(__FILE__, 'oopspamantispam_check_run_migration');
add_action('plugins_loaded', 'oopspamantispam_check_run_migration');

// Automatically mark wizard as completed for existing installations with API keys
add_action('admin_init', 'oopspam_check_and_mark_wizard_completed', 5); // Run with high priority

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
    // Run privacy settings migration if needed
    if (get_option('oopspamantispam_privacy_migration_completed') !== true) {
        oopspamantispam_migrate_privacy_settings();
    }
    
    // Check if this is a plugin update by checking if API key exists but wizard is not completed
    // This is especially important for updates from older versions
    if (function_exists('oopspamantispam_checkIfValidKey')) {
        $api_key = oopspamantispam_checkIfValidKey();
        
        if ($api_key && !get_option('oopspam_wizard_completed', false)) {
            // API key exists but wizard is not marked as completed
            // Mark wizard as completed to prevent notice after update
            update_option('oopspam_wizard_completed', true);
        }
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
function oopspam_cron_job() {
    try {
        $rtOptions = get_option('oopspamantispam_ratelimit_settings');
        
        $options = get_option('oopspamantispam_settings');
        if (!is_array($options)) {
            $options = [];
        }

        if (oopspam_isRateLimitingEnabled()) {
            $cleanDuration = isset($rtOptions['oopspamantispam_ratelimit_cleanup_duration']) ? $rtOptions['oopspamantispam_ratelimit_cleanup_duration'] : 48; // Default is 48 hours
            $rateLimiter = new OOPSpam_RateLimiter();
            $rateLimiter->reschedule_cleanup(0, $cleanDuration);
        }

        if (!wp_next_scheduled('oopspam_cleanup_ham_entries_cron')) {
            wp_schedule_event(strtotime('+1 month'), 'oopspam-monthly', 'oopspam_cleanup_ham_entries_cron');
            $options['oopspam_clear_ham_entries'] = "monthly";
        }

        if (!wp_next_scheduled('oopspam_cleanup_spam_entries_cron')) {
            wp_schedule_event(strtotime('+1 month'), 'oopspam-monthly', 'oopspam_cleanup_spam_entries_cron');
            $options['oopspam_clear_spam_entries'] = "monthly";
        }

        update_option('oopspamantispam_settings', $options);
    } catch (Exception $e) {
        error_log('oopspam_cron_job error: ' . $e->getMessage());
    }
}

function oopspam_cleanup_ham_entries() {
    try {
        global $wpdb;
        $table = $wpdb->prefix . 'oopspam_frm_ham_entries';
        $options = get_option('oopspamantispam_settings');

        // Determine the time threshold based on the user setting
        $time_period = isset($options['oopspam_clear_ham_entries']) ? $options['oopspam_clear_ham_entries'] : 'monthly';
        $interval = ($time_period === 'monthly') ? '-1 month' : '-2 weeks';

        // Calculate the date threshold
        $date_threshold = date('Y-m-d H:i:s', strtotime($interval));

        // Delete entries older than the calculated date
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table WHERE `date` < %s",
                $date_threshold
            )
        );

        wp_send_json_success(array(
            'success' => true,
        ), 200);
    } catch (Exception $e) {
        error_log('oopspam_cleanup_ham_entries: ' . $e->getMessage());
    }
}
function oopspam_cleanup_spam_entries() {
    try {
        global $wpdb;
        $table = $wpdb->prefix . 'oopspam_frm_spam_entries';
        $options = get_option('oopspamantispam_settings');

        // Determine the time threshold based on the user setting
        $time_period = isset($options['oopspam_clear_spam_entries']) ? $options['oopspam_clear_spam_entries'] : 'monthly';
        $interval = ($time_period === 'monthly') ? '-1 month' : '-2 weeks';

        // Calculate the date threshold
        $date_threshold = date('Y-m-d H:i:s', strtotime($interval));

        // Delete entries older than the calculated date
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table WHERE `date` < %s",
                $date_threshold
            )
        );

        wp_send_json_success(array(
            'success' => true,
        ), 200);
    } catch (Exception $e) {
        error_log('oopspam_cleanup_spam_entries: ' . $e->getMessage());
    }
}
add_action('oopspam_cleanup_spam_entries_cron', 'oopspam_cleanup_spam_entries');
add_action('oopspam_cleanup_ham_entries_cron', 'oopspam_cleanup_ham_entries');

add_action('oopspam_cleanup_ratelimit_entries_cron', 'oopspam_ratelimit_cleanup');

 function oopspam_ratelimit_cleanup() {
     $rate_limiter = new OOPSpam_RateLimiter();
    

    // Schedule rate limiter cleanup
    $rate_limiter->oopspam_ratelimit_cleanup();
   
 }
function oopspam_plugin_activate() {

    // Schedule other cron jobs
    oopspam_cron_job();

    // Set default settings
    do_action('oopspam_set_default_settings');
    
    // Use function to check if API key exists (after helpers.php is included)
    // If helpers.php hasn't been included yet, fall back to direct option check
    if (function_exists('oopspamantispam_checkIfValidKey')) {
        $has_key = oopspamantispam_checkIfValidKey();
    } else {
        // Fallback to direct option check if function not available yet
        $options = get_option('oopspamantispam_settings');
        $has_key = defined('OOPSPAM_API_KEY') || (isset($options['oopspam_api_key']) && !empty($options['oopspam_api_key']));
    }
    
    // If API key exists, mark wizard as completed automatically
    if ($has_key) {
        update_option('oopspam_wizard_completed', true);
    }
    // Otherwise, set transient for redirect to setup wizard
    elseif (!get_option('oopspam_wizard_completed', false)) {
        // Set a transient to redirect to the setup wizard
        set_transient('oopspam_activation_redirect', true, 30);
    }
}

// Set default values
function oopspam_default_options()
{

    $options = get_option('oopspamantispam_settings');
    $rtOptions = get_option('oopspamantispam_ratelimit_settings');

    $defaultRt = array(
        'oopspamantispam_ratelimit_ip_limit' => 2,
        'oopspamantispam_ratelimit_email_limit' => 2
    );

    update_option('oopspamantispam_ratelimit_settings', $defaultRt);

    if (!isset($options['oopspam_api_key_source'])) {
        $default = array(
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
    wp_clear_scheduled_hook('oopspam_cleanup_ratelimit_entries_cron');
    
    // Clean up session variables
    if (session_id()) {
        session_destroy();
    }
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

function oopspam_is_keyword_blocked($text) {
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
function oopspam_is_email_blocked($email) {
 
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
function oopspam_is_ip_blocked($ip) {
 
    // Get blocked IPs
    $manual_moderation_options = get_option('manual_moderation_settings');
    $blocked_ips = isset($manual_moderation_options['mm_blocked_ips']) ? $manual_moderation_options['mm_blocked_ips'] : '';

    if ('' == $blocked_ips || empty($ip)) {
       return false;
    }
   
    // Convert IP to long format for range comparison
    $ip_long = ip2long($ip);
    
    // If IP can't be converted, return false
    if ($ip_long === false) {
        return false;
    }

    $ips = explode("\n", $blocked_ips);

    foreach ((array)$ips as $b_ip) {
        $b_ip = trim($b_ip);
       
        // Skip empty lines
        if (empty($b_ip)) {
            continue;
        }
        
        // Check for exact match
        if ($b_ip === $ip) {
            return true;
        }
        
        // Check for IP range in CIDR notation (e.g., 192.168.1.0/24)
        if (strpos($b_ip, '/') !== false) {
            list($range, $netmask) = explode('/', $b_ip, 2);
            
            // If netmask is invalid or range is invalid, skip
            if (!is_numeric($netmask) || $netmask < 0 || $netmask > 32 || ip2long($range) === false) {
                continue;
            }
            
            // Convert range to long format
            $range_long = ip2long($range);
            
            // Calculate the mask
            $mask = ~((1 << (32 - $netmask)) - 1);
            
            // Check if IP is in range
            if (($ip_long & $mask) == ($range_long & $mask)) {
                return true;
            }
        }
        
        // Check for IP range with hyphen (e.g., 192.168.1.1-192.168.1.10)
        if (strpos($b_ip, '-') !== false) {
            list($start, $end) = explode('-', $b_ip, 2);
            
            // Validate start and end IPs
            $start_long = ip2long(trim($start));
            $end_long = ip2long(trim($end));
            
            // If either IP is invalid, skip
            if ($start_long === false || $end_long === false) {
                continue;
            }
            
            // Check if IP is in range
            if ($ip_long >= $start_long && $ip_long <= $end_long) {
                return true;
            }
        }
    }
    return false;
}

// Check if an email is allowed locally
function oopspam_is_email_allowed($email) {
 
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
function oopspam_is_ip_allowed($ip) {
 
    // Get allowed IPs
    $manual_moderation_options = get_option('manual_moderation_settings');
    $allowed_ips = isset($manual_moderation_options['mm_allowed_ips']) ? $manual_moderation_options['mm_allowed_ips'] : '';

    if ('' == $allowed_ips || empty($ip)) {
       return false;
    }
   
    // Convert IP to long format for range comparison
    $ip_long = ip2long($ip);
    
    // If IP can't be converted, return false
    if ($ip_long === false) {
        return false;
    }

    $ips = explode("\n", $allowed_ips);

    foreach ((array)$ips as $b_ip) {
       $b_ip = trim($b_ip);
       
       // Skip empty lines.
       if (empty($b_ip)) {
           continue;
       }
       
       // Check for exact match
       if ($b_ip === $ip) {
           return true;
       }
       
       // Check for IP range in CIDR notation (e.g., 192.168.1.0/24)
       if (strpos($b_ip, '/') !== false) {
           list($range, $netmask) = explode('/', $b_ip, 2);
           
           // If netmask is invalid or range is invalid, skip
           if (!is_numeric($netmask) || $netmask < 0 || $netmask > 32 || ip2long($range) === false) {
               continue;
           }
           
           // Convert range to long format
           $range_long = ip2long($range);
           
           // Calculate the mask
           $mask = ~((1 << (32 - $netmask)) - 1);
           
           // Check if IP is in range
           if (($ip_long & $mask) == ($range_long & $mask)) {
               return true;
           }
       }
       
       // Check for IP range with hyphen (e.g., 192.168.1.1-192.168.1.10)
       if (strpos($b_ip, '-') !== false) {
           list($start, $end) = explode('-', $b_ip, 2);
           
           // Validate start and end IPs
           $start_long = ip2long(trim($start));
           $end_long = ip2long(trim($end));
           
           // If either IP is invalid, skip
           if ($start_long === false || $end_long === false) {
               continue;
           }
           
           // Check if IP is in range
           if ($ip_long >= $start_long && $ip_long <= $end_long) {
               return true;
           }
       }
   }
   return false;
}


function oopspam_containsUrl($text) {
    // The Regular Expression filter to detect URLs
    $reg_exUrl = "/(?i)\b((?:https?:\/\/|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}\/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:'\".,<>?«»“”‘’]))/";

    // Check if there is a URL in the text
    return preg_match($reg_exUrl, $text);
}

function oopspam_isRateLimitingEnabled() {
    $options = get_option('oopspamantispam_ratelimit_settings');
    $requiredKeys = [
        'oopspam_is_rt_enabled',
        'oopspamantispam_ratelimit_ip_limit', 
        'oopspamantispam_ratelimit_email_limit', 
        'oopspamantispam_ratelimit_block_duration', 
        'oopspamantispam_ratelimit_cleanup_duration'
    ];

    // Check each required key in the provided or current settings
    foreach ($requiredKeys as $key) {
        if (empty($options[$key])) {
            return false;
        }
    }
    return true;
}

function oopspamantispam_call_OOPSpam($commentText, $commentIP, $email, $returnReason, $type)
{
    // Get rate limiting settings
    $rtOptions = get_option('oopspamantispam_ratelimit_settings');
    
    // Only check submission speed if min_submission_time is set
    if (isset($rtOptions['oopspamantispam_min_submission_time'])) {
        $submissionSpeed = 0;
        if (isset($_SESSION['oopspam_entry_time'])) {
            $submissionSpeed = time() - $_SESSION['oopspam_entry_time'];
        }

        // Get minimum submission time from settings
        $minSubmissionTime = intval($rtOptions['oopspamantispam_min_submission_time']);

        // If submission is too fast, mark as spam
        if ($submissionSpeed < $minSubmissionTime && $submissionSpeed > 0) {
            if ($returnReason) {
                return [
                    "Score" => 6,
                    "isItHam" => false,
                    "Reason" => "Submission too fast - " . $submissionSpeed . " seconds"
                ];
            }
            return false;
        }
    }

    // Check blocked emails, IPs, keywords locally
    $hasBlockedKeyword = oopspam_is_keyword_blocked($commentText);
    $hasBlockedEmail = oopspam_is_email_blocked($email);
    $hasBlockedIP = oopspam_is_ip_blocked($commentIP);

    $hasAllowedEmail = oopspam_is_email_allowed($email);
    $hasAllowedIP = oopspam_is_ip_allowed($commentIP);

    if ($hasBlockedEmail || $hasBlockedIP) {
    
        // The entry blocked locally by the Manual moderation settings
        if ($returnReason) {
            $reason = [
                "Score" => 6,
                "isItHam" => false,
                "Reason" => $hasBlockedEmail && $hasBlockedIP ? "Blocked Email and IP under the Manual Moderation" : ($hasBlockedEmail ? "Blocked Email under the Manual Moderation" : "Blocked IP under the Manual Moderation")
            ];
            return $reason;
        }
        return false;
}

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

    if ($hasBlockedKeyword) {
    
            // The entry blocked locally by the Manual moderation settings
            if ($returnReason) {
                $reason = [
                    "Score" => 6,
                    "isItHam" => false,
                    "Reason" => "Blocked keyword under the Manual Moderation"
                ];
                return $reason;
            }
            return false;
    }

    // Run rate limiting check
    try {
        if($type !== "search") {
            $rtOptions = get_option('oopspamantispam_ratelimit_settings');
            $isRateLimitEnabled = oopspam_isRateLimitingEnabled();
            if($isRateLimitEnabled) {
                $rate_limiter = new OOPSpam_RateLimiter();
            
                if (!$rate_limiter->checkLimit($commentIP, 'ip')) {
                    if ($returnReason) {
                        $reason = [
                            "Score" => 6,
                            "isItHam" => false,
                            "Reason" => "Too many submissions from the IP address"
                        ];
                        return $reason;
                    }
                    return false;
                }
            
                if (!$rate_limiter->checkLimit($email, 'email')) {
                    if ($returnReason) {
                        $reason = [
                            "Score" => 6,
                            "isItHam" => false,
                            "Reason" => "Too many submissions from the email"
                        ];
                        return $reason;
                    }
                    return false;
                }
        }
        }

        // Check for unique Google Ads lead per submission
        $gclid = oopspam_get_gclid_from_url();
        $gclidLimit = isset($rtOptions['oopspamantispam_ratelimit_gclid_limit']) ? $rtOptions['oopspamantispam_ratelimit_gclid_limit'] : '';
        
        if (!empty($gclid) && !empty($gclidLimit)) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'oopspam_frm_ham_entries';
            $gclid_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE gclid = %s", $gclid));
            if ($gclid_count >= $gclidLimit) {
            if ($returnReason) {
                $reason = [
                "Score" => 6,
                "isItHam" => false,
                "Reason" => "Submission with this Google Ads lead exceeds the allowed limit"
                ];
                return $reason;
            }
            return false;
            }
        }

    } catch (Exception $e) {
        error_log('Rate limiter check failed: ' . $e->getMessage());
    }

    
    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');

    // Check if the message contains any URLs
    $blockURLs = (isset($options['oopspam_is_urls_allowed']) ? $options['oopspam_is_urls_allowed'] : false);
    if ($blockURLs) {
        $hasURL = oopspam_containsUrl($commentText);
        if ($hasURL) {
            if ($returnReason) {
                $reason = [
                    "Score" => 6,
                    "isItHam" => false,
                    "Reason" => "A URL in the message"
                ];
                return $reason;
            }
            return false;
        }
    }

    $apiKey = oopspamantispam_checkIfValidKey();


    $ipFilteringOptions = get_option('oopspamantispam_ipfiltering_settings');
    
    // Properly handle empty arrays by filtering out any empty strings
    $countryallowlistSetting = array_filter((array)get_option('oopspam_countryallowlist', []));
    $countryblocklistSetting = array_filter((array)get_option('oopspam_countryblocklist', []));
    $countryAlwaysAllowSetting = array_filter((array)get_option('oopspam_country_always_allow', []));
    $languageallowlistSetting = array_filter((array)get_option('oopspam_languageallowlist', []));
    $checkForLength = (isset($options['oopspam_is_check_for_length']) ? $options['oopspam_is_check_for_length'] : false);
    $isLoggable = defined('OOPSPAM_ENABLE_REMOTE_LOGGING') ? OOPSPAM_ENABLE_REMOTE_LOGGING : (isset($options['oopspam_is_loggable']) ? $options['oopspam_is_loggable'] : false);
    
    // Check if submission is from an always allowed country
    if (!empty($commentIP) && !empty($countryAlwaysAllowSetting)) {
        $args = array(
            'timeout' => 5,
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking' => true,
            'sslverify' => true
        );

        $response = wp_remote_get("https://reallyfreegeoip.org/json/{$commentIP}", $args);
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            if (isset($data['country_code']) && !empty($data['country_code'])) {
                $country_code = strtolower($data['country_code']);
                if (!empty($country_code) && in_array($country_code, $countryAlwaysAllowSetting)) {
                    if ($returnReason) {
                        $reason = [
                            "Score" => 0,
                            "isItHam" => true,
                            "Reason" => "From always allowed country"
                        ];
                        return $reason;
                    }
                    return true;
                }
            }
        }
    }

    $blockTempEmail = (isset($options['oopspam_block_temp_email']) ? $options['oopspam_block_temp_email'] : false);
    $blockVPNs = (isset($ipFilteringOptions['oopspam_block_vpns']) ? $ipFilteringOptions['oopspam_block_vpns'] : false);
    $blockDC = (isset($ipFilteringOptions['oopspam_block_cloud_providers']) ? $ipFilteringOptions['oopspam_block_cloud_providers'] : false);

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
    if ($type === "give" || $type === "woo" || $type === "mc4wp"
    || $type === "mailpoet" || $type === "search" 
    || $type === "wpregister" || $type === "umember" || $type === "mpress"
    || $type === "pmp" || $type === "surecart") {
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

    if ($apiKey) {

        $OOPSpamAPI = new OOPSpamAPI($apiKey, $checkForLength, $isLoggable, $blockTempEmail, $blockVPNs, $blockDC);
        
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

            $contextDetectionOptions = get_option('oopspamantispam_contextai_settings');
            $contextualEnabled = isset($contextDetectionOptions['oopspam_is_contextai_enabled']) ? true : false;

            if (isset($response['Details']['isContentSpam'])) {
                $isContent = $response['Details']['isContentSpam'];
            }


            if ($contextualEnabled && isset($isContent)) {
                if ($isContent === 'spam') {
                    if ($returnReason) {
                        return [
                            "Score" => 6,
                            "isItHam" => false,
                            "Reason" => "Contextual Content detection: Spam content"
                        ];
                    }
                    return false;
                } else if ($isContent === 'notspam') {
                    if ($returnReason) {
                        return [
                            "Score" => 0,
                            "isItHam" => true
                        ];
                    }
                    return true;
                }
            }

            // Default scoring logic if contextual detection is disabled or no result
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
            // Return special score -1 to indicate rate limit
            return $returnReason ? ["Score" => -1, "isItHam" => true, "Reason" => "Rate limit reached"] : true;
        } else {
            // Allow all submission as no analyses are done but mark with special score -2
            if (is_wp_error($response)) {
                error_log($response->get_error_message());
            }
            return $returnReason ? ["Score" => -2, "isItHam" => true, "Reason" => "API error occurred"] : true;
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
            'isContentTooShort' => 'Content too short'
        ];

        foreach ($booleanChecks as $key => $reason) {
            if (isset($details[$key]) && $details[$key] === true) {
                return $reason;
            }
        }

        if (isset($details['isContentSpam']) && $details['isContentSpam'] === 'spam') {
            return 'Content identified as spam';
        }

        // Check for language and country mismatch
        if (isset($details['langMatch']) && $details['langMatch'] === false) {
            return 'Language not allowed';
        }

        if (isset($details['countryMatch']) && $details['countryMatch'] === true && $response['Score'] >= 6) {
            return 'Country blocked';
        }
    }
    

       
    // If no specific reason found, use the overall score
    if (isset($response['Score']) && $response['Score'] >= 3 ) {
        return 'High spam score';
    }

    // If no reason found at all
    return 'Multiple spam indicators';
}

function oopspamantispam_report_OOPSpam($commentText, $commentIP, $email, $isSpam)
{

    $apiKey = oopspamantispam_checkIfValidKey();

    $options = get_option('oopspamantispam_settings');
    $ipFilterOptions = get_option('oopspamantispam_ipfiltering_settings');

    $countryallowlistSetting = (get_option('oopspam_countryallowlist') != null ? get_option('oopspam_countryallowlist') : [""]);
    $countryblocklistSetting = (get_option('oopspam_countryblocklist') != null ? get_option('oopspam_countryblocklist') : [""]);
    $languageallowlistSetting = (get_option('oopspam_languageallowlist') != null ? get_option('oopspam_languageallowlist') : [""]);
    $checkForLength = (isset($options['oopspam_is_check_for_length']) ? $options['oopspam_is_check_for_length'] : false);
    $blockTempEmail = (isset($options['oopspam_block_temp_email']) ? $options['oopspam_block_temp_email'] : false);
    $blockVPNs = (isset($ipFilterOptions['oopspam_block_vpns']) ? $ipFilterOptions['oopspam_block_vpns'] : false);
    $blockDC = (isset($ipFilterOptions['oopspam_block_cloud_providers']) ? $ipFilterOptions['oopspam_block_cloud_providers'] : false);


    if (oopspamantispam_checkIfValidKey()) {

        $OOPSpamAPI = new OOPSpamAPI($apiKey, $checkForLength, 0, $blockTempEmail, $blockVPNs, $blockDC);
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

// Remove http & https from domain
function oopspam_urlToDomain($url)
{
    return implode(array_slice(explode('/', preg_replace('/https?:\/\/(www\.)?/', '', $url)), 0, 1));
}


function oopspamantispam_check_comment($approved, $commentdata)
{    

    static $processed_comments = array();
    
    // Generate a unique identifier for this comment
    $comment_identifier = md5($commentdata['comment_content'] . $commentdata['comment_author_IP']);
    
    // If we've already processed this comment, return the previous result
    if (isset($processed_comments[$comment_identifier])) {
        return $processed_comments[$comment_identifier];
    }
        
    // If admin skip
    if (current_user_can('administrator')) {
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
        $senderIp = oopspamantispam_get_ip();
    }

    if (!isset($options['oopspam_is_check_for_email']) || $options['oopspam_is_check_for_email'] != true) {
        $email = sanitize_email($commentdata['comment_author_email']);
    }

    $trimmedURL = oopspam_urlToDomain($commentdata['comment_author_url']);

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
    }

    if ($isItSpam) {
        $detectionResult = [
            "Score" => 6,
            "isItHam" => false,
            "Reason" => $reason
        ];
    } else {
        // if Spam filtering is on and the OOPSpam Service considers it spam then mark it as spam
        $detectionResult = oopspamantispam_call_OOPSpam(sanitize_textarea_field($content), $senderIp, $email, true, "comment");
        if (!isset($detectionResult["isItHam"])) {
            return;
        }
    }
   
    $raw_entry = json_encode($commentdata);
    $frmEntry = [
            "Score" => $detectionResult["Score"],
            "Message" => $content,
            "IP" => $senderIp,
            "Email" => $email,
            "RawEntry" => $raw_entry,
            "FormId" => "comment",
        ];

    // Move the spam comment select folder (Trash or spam) and log
    if (!$detectionResult["isItHam"]) {
        oopspam_store_spam_submission($frmEntry, $detectionResult["Reason"]);
        $processed_comments[$comment_identifier] = $currentSpamFolder;
        return $currentSpamFolder;
        // TODO: Allow UI customization for this message
        // wp_die(__('Your comment has been flagged as spam.', 'oopspam'));
    } else {
        // It's ham
        oopspam_store_ham_submission($frmEntry);
        // Store the result before returning
        $processed_comments[$comment_identifier] = $approved;
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

        $trimmedURL = oopspam_urlToDomain($commentdata['comment_author_url']);

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
add_filter('preprocess_comment', 'oopspam_fix_comment_author_ip');
// WordPress doesn't handle comment author IP correctly, especially when using proxies or load balancers.
function oopspam_fix_comment_author_ip($commentdata) {
    // Get the real IP address
    $real_ip = oopspamantispam_get_ip();
    
    // Override the comment author IP
    $commentdata['comment_author_IP'] = $real_ip;
    
    return $commentdata;
}

add_action('admin_init', 'oopspam_admin_init');

add_action('pre_get_posts', 'oopspam_check_search_for_spam');

// When a comment flagged as spam, let OOPSpam know too
add_action('transition_comment_status', 'oopspam_comment_spam_transition', 10, 3);
function oopspam_comment_spam_transition($new_status, $old_status, $comment) {
    if ($new_status === 'spam' && $old_status !== 'spam') {
         // Report as spam
         $commentText = $comment->comment_content; 
         $commentIP = $comment->comment_author_IP;
         $email = $comment->comment_author_email;  
         $isSpam = true;  
        
         oopspamantispam_report_OOPSpam($commentText, $commentIP, $email, $isSpam);
    }
    else if ($old_status === 'spam' && ($new_status === 'approved' || $new_status === 'unapproved')) {
         // Report as ham
         $commentText = $comment->comment_content;
         $commentIP = $comment->comment_author_IP; 
         $email = $comment->comment_author_email;
         $isSpam = false;

         oopspamantispam_report_OOPSpam($commentText, $commentIP, $email, $isSpam); 
    }
}

function oopspam_check_search_for_spam($query)
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
    // Initialize rate limiter and create table
    $rate_limiter = new OOPSpam_RateLimiter();
    
    // Ensure styles are added only on plugin settings pages
    if (isset($_GET['page']) && (
        $_GET['page'] === 'wp_oopspam_settings_page' ||
        $_GET['page'] === 'wp_oopspam_frm_ham_entries' ||
        $_GET['page'] === 'wp_oopspam_frm_spam_entries'
    )) {
        wp_register_style('oopspam_stylesheet', plugins_url('styles/main.css', __FILE__));
        wp_register_style('tom-select', plugins_url('./include/libs/tom-select.min.css', __FILE__));
        add_action('admin_print_styles', 'oopspam_admin_style');
    
        require_once plugin_dir_path(__FILE__) . 'include/localize-script.php';
    }
}

function oopspam_admin_style()
{
    wp_enqueue_style('oopspam_stylesheet');
    wp_enqueue_style('tom-select');
}

/**
 * Display admin notice if the plugin is not set up
 */
/**
 * Check for existing API key and mark wizard as completed
 * This ensures older installations with API keys don't see the wizard notice
 */
function oopspam_check_and_mark_wizard_completed() {
    static $already_run = false;
    
    // Make sure this only runs once per page load
    if ($already_run) {
        return;
    }
    $already_run = true;
    
    // Use the most reliable API key check
    if (function_exists('oopspamantispam_checkIfValidKey')) {
        $has_api_key = oopspamantispam_checkIfValidKey();
    } else if (function_exists('oopspam_has_api_key')) {
        $has_api_key = oopspam_has_api_key();
    } else {
        $options = get_option('oopspamantispam_settings');
        $has_api_key = defined('OOPSPAM_API_KEY') || (isset($options['oopspam_api_key']) && !empty($options['oopspam_api_key']));
    }
    
    // If API key exists but wizard not completed, mark it as completed
    if (!get_option('oopspam_wizard_completed', false) && $has_api_key) {
        update_option('oopspam_wizard_completed', true);
    }
}

/**
 * Display admin notice for setup wizard
 */
function oopspam_admin_setup_notice() {
    // Don't show notice on the setup wizard page
    if (isset($_GET['page']) && $_GET['page'] === 'oopspam_setup_wizard') {
        return;
    }
    
    // Check and mark wizard completed first if API key exists
    oopspam_check_and_mark_wizard_completed();
    
    // Run the check for existing installations one more time using the most reliable method
    if (function_exists('oopspamantispam_checkIfValidKey')) {
        $api_key = oopspamantispam_checkIfValidKey();
        if ($api_key && !get_option('oopspam_wizard_completed', false)) {
            update_option('oopspam_wizard_completed', true);
        }
    }
    
    // Only show the notice if wizard is not completed AND API key is missing after the check
    if (!get_option('oopspam_wizard_completed', false) && !oopspam_has_api_key()) {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong><?php _e('OOPSpam Anti-Spam is not fully set up!', 'oopspam-anti-spam'); ?></strong>
                <?php _e('Complete the setup wizard to protect your site from spam.', 'oopspam-anti-spam'); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=oopspam_setup_wizard')); ?>" class="button button-primary">
                    <?php _e('Run Setup Wizard', 'oopspam-anti-spam'); ?>
                </a>
            </p>
        </div>
        <?php
    }
}
