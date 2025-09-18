<?php
if (!function_exists('add_action')) {
    die();
}

include_once dirname(__FILE__) . '/include/UI/display-spam-entries.php';
include_once dirname(__FILE__) . '/include/UI/display-ham-entries.php';
require_once dirname(__FILE__) . '/include/oopspam-rate-limiting.php';

use OOPSPAM\RateLimiting\OOPSpam_RateLimiter;

add_action('admin_menu', 'oopspamantispam_admin_menu');
add_action('admin_init', 'oopspamantispam_settings_init');

function oopspamantispam_admin_menu()
{
    $hook = add_menu_page(
        'OOPSpam Anti-Spam',
        'OOPSpam Anti-Spam',
        'manage_options',
        'wp_oopspam_settings_page',
        'oopspamantispam_options_page',
        'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyMCIgaGVpZ2h0PSIxNyIgdmlld0JveD0iMCAwIDIwIDE3Ij48cGF0aCBmaWxsPSIjRkZGIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiIGQ9Ik0xOS41MTk1NDAyLDE0Ljg0ODI3NTkgQzE3Ljc3OTMxMDMsMTQuMzEyNjQzNyAxNi4wNDgyNzU5LDEzLjQ5NDI1MjkgMTUuNTcyNDEzOCwxMS41MzU2MzIyIEMxNS4xNzI0MTM4LDkuOTEyNjQzNjggMTUuOTUxNzI0MSw4LjAwNjg5NjU1IDE3LjEwMzQ0ODMsNi44NjY2NjY2NyBDMTcuODI3NTg2Miw2LjE0NzEyNjQ0IDE4Ljc4MzkwOCw1LjUwNTc0NzEzIDE5LjI2ODk2NTUsNC41Njc4MTYwOSBDMTkuNjg3MzU2MywzLjc3MDExNDk0IDE5LjM0MDIyOTksMi45MzEwMzQ0OCAxOC40MDIyOTg5LDIuODk2NTUxNzIgQzE3LjAxMTQ5NDMsMi44NTUxNzI0MSAxNS40OTY1NTE3LDQuMzQyNTI4NzQgMTQuMTEyNjQzNywzLjg4NTA1NzQ3IEMxMy4xNzkzMTAzLDMuNTc3MDExNDkgMTIuOTg4NTA1NywyLjI3NTg2MjA3IDEyLjQ4NzM1NjMsMS41NzAxMTQ5NCBDMTIuMTY1ODU5MiwxLjEwODkyNzUxIDExLjcxNDU3OTcsMC43NTM2NjQ5OTUgMTEuMTkwODA0NiwwLjU0OTQyNTI4NyBDMTAuMTEwOTIyMywwLjExMjk4NTUxNyA4LjkwMTgzNTYzLDAuMTIzNzM2NjMgNy44Mjk4ODUwNiwwLjU3OTMxMDM0NSBDNi41MjQxMzc5MywxLjE3MjQxMzc5IDYuMDU1MTcyNDEsMi41Mjg3MzU2MyA1LjEzNzkzMTAzLDMuNTM3OTMxMDMgQzUuMTA3NDQxODYsMy41NzI0NDMxNyA1LjA3OTc2NzA4LDMuNjA5MzQyODUgNS4wNTUxNzI0MSwzLjY0ODI3NTg2IEM0LjI2NjY2NjY3LDQuMTc5MzEwMzQgMy44NDgyNzU4Niw0LjE5MDgwNDYgMy4wNjY2NjY2NywzLjUxNDk0MjUzIEMyLjU1ODYyMDY5LDMuMDc1ODYyMDcgMi4wMzkwODA0NiwyLjI5ODg1MDU3IDEuMzE3MjQxMzgsMi4yMTgzOTA4IEMtMC4xOTc3MDExNDksMi4wNjg5NjU1MiAtMC4yMDQ1OTc3MDEsMy43NDk0MjUyOSAwLjMwMTE0OTQyNSw0LjcwMzQ0ODI4IEMwLjc3NDcxMjY0NCw1LjU5MzEwMzQ1IDEuNTMzMzMzMzMsNi4yNzgxNjA5MiAxLjgzOTA4MDQ2LDcuMjY0MzY3ODIgQzIuMTQxNDg0MDMsOC4zMDI1MDA2IDIuMDU5ODc1ODMsOS40MTQ4MjAzIDEuNjA5MTk1NCwxMC4zOTc3MDExIEMxLjA0NTk3NzAxLDExLjc0MjUyODcgMC4xOTc3MDExNDksMTMuMzMzMzMzMyAxLjE5MDgwNDYsMTQuNjk2NTUxNyBDMi4xMjE4MzkwOCwxNS45ODM5MDggMy44OTE5NTQwMiwxNS44MDY4OTY2IDUuMjMyMTgzOTEsMTUuMzg2MjA2OSBDNi4wMzkwODA0NiwxNS4xNDAyMjk5IDYuODExNDk0MjUsMTQuNzY3ODE2MSA3LjY0MTM3OTMxLDE0LjYgQzguNzI4NzM1NjMsMTQuMzcwMTE0OSA5Ljc3MjQxMzc5LDE0LjY0ODI3NTkgMTAuNzkzMTAzNCwxNS4wMjA2ODk3IEMxMi40MzIxODM5LDE1LjYxODM5MDggMTMuODQxMzc5MywxNi4xNzAxMTQ5IDE1LjYwNjg5NjYsMTYuMTQ5NDI1MyBDMTYuODEzNzkzMSwxNi4xNDk0MjUzIDE4LjM0NzEyNjQsMTYuMzI2NDM2OCAxOS41MTQ5NDI1LDE1Ljk2NTUxNzIgQzE5Ljc2MDE3MzgsMTUuODkwNjgyNyAxOS45MzAzNTg1LDE1LjY2Nzc3NzggMTkuOTM3OTMxLDE1LjQxMTQ5NDMgTDE5LjkzNzkzMSwxNS40MDIyOTg5IEMxOS45MzY4MjYsMTUuMTQ1MjUzNyAxOS43NjY0NzI0LDE0LjkxOTY3NTYgMTkuNTE5NTQwMiwxNC44NDgyNzU5IFogTTcuNjk0MjUyODcsOS4yOTg4NTA1NyBDNi41MTEzNjU1NCw5LjI2ODc1MDg1IDUuNTc2MzEzNDcsOC4yODYzODA0MSA1LjYwNDU5NzcsNy4xMDM0NDgyOCBDNS41NzYzMTM0Nyw1LjkyMDUxNjE0IDYuNTExMzY1NTQsNC45MzgxNDU3IDcuNjk0MjUyODcsNC45MDgwNDU5OCBDOC44NzcxNDAyMSw0LjkzODE0NTcgOS44MTIxOTIyOCw1LjkyMDUxNjE0IDkuNzgzOTA4MDUsNy4xMDM0NDgyOCBDOS44MTIxOTIyOCw4LjI4NjM4MDQxIDguODc3MTQwMjEsOS4yNjg3NTA4NSA3LjY5NDI1Mjg3LDkuMjk4ODUwNTcgWiBNMTIuMzM1NjMyMiw5LjEzMzMzMzMzIEMxMS42NjIzNzQ4LDkuMTI5NTI5NTYgMTEuMTE5MzE1NCw4LjU4MTMzNTMzIDExLjEyMTgzODksNy45MDgwNzE5MyBDMTEuMTI0MzgsNy4yMzQ4MDg1NSAxMS42NzE1NDc2LDYuNjkwNzE0ODcgMTIuMzQ0ODE0Niw2LjY5MTk3MzQ3IEMxMy4wMTgwODE2LDYuNjkzMjM2NDEgMTMuNTYzMjIwNiw3LjIzOTM3NTUyIDEzLjU2MzIyMDYsNy45MTI2NDM2OCBDMTMuNTYzODQxMSw4LjIzNzc3ODggMTMuNDM0NDg0Myw4LjU0OTY3NTkzIDEzLjIwMzkzMiw4Ljc3ODkzMzA4IEMxMi45NzMzNzk2LDkuMDA4MTkwMjEgMTIuNjYwNzU4Niw5LjEzNTc4Nzc5IDEyLjMzNTYzMjIsOS4xMzMzMzMzMyBaIi8+PC9zdmc+'
    );

}

add_action('wp_ajax_update_cloud_providers_setting', 'oopspam_update_cloud_providers_setting');

function oopspam_update_cloud_providers_setting() {
    // Verify nonce
    if (!check_ajax_referer('oopspam_update_cloud_providers', 'nonce', false)) {
        wp_send_json_error('Invalid security token');
        return;
    }

    // Get current settings
    $options = get_option('oopspamantispam_ipfiltering_settings', array());
    
    // Update based on enable parameter
    $enable = isset($_POST['enable']) && filter_var($_POST['enable'], FILTER_VALIDATE_BOOLEAN);
    if (!is_array($options)) {
        $options = array();
    }
    
    if ($enable) {
        $options['oopspam_block_cloud_providers'] = "1";
    } else {
        unset($options['oopspam_block_cloud_providers']);
    }
    
    // Save the updated settings
    if (update_option('oopspamantispam_ipfiltering_settings', $options)) {
        wp_send_json_success('Setting updated successfully');
    } else {
        wp_send_json_error('Failed to update setting');
    }
}

add_action('updated_option', 'oopspam_schedule_cron_job', 10, 3);
function oopspam_schedule_cron_job($option, $old_value, $new_value)
{
    if (strpos($option, "oopspam") === false) {
        return;
    }

    $options = get_option('oopspamantispam_settings');
    
    if (isset($new_value["oopspam_clear_spam_entries"]) && 
        (!isset($old_value["oopspam_clear_spam_entries"]) || 
        $new_value["oopspam_clear_spam_entries"] != $old_value["oopspam_clear_spam_entries"])) {
        
        $options["oopspam_clear_spam_entries"] = $new_value["oopspam_clear_spam_entries"];
        schedule_cron_job('oopspam_cleanup_spam_entries_cron', $new_value["oopspam_clear_spam_entries"]);
    }
    
    if (isset($new_value["oopspam_clear_ham_entries"]) && 
        (!isset($old_value["oopspam_clear_ham_entries"]) || 
        $new_value["oopspam_clear_ham_entries"] != $old_value["oopspam_clear_ham_entries"])) {

        $options["oopspam_clear_ham_entries"] = $new_value["oopspam_clear_ham_entries"];
        schedule_cron_job('oopspam_cleanup_ham_entries_cron', $new_value["oopspam_clear_ham_entries"]);
    }
}

add_action('updated_option', 'oopspam_ratelimit_schedule_cron_job', 10, 3);

function oopspam_ratelimit_schedule_cron_job($option, $old_value, $new_value)
{
    if ($option !== 'oopspamantispam_ratelimit_settings') {
        return;
    }

    $new_duration = $new_value['oopspamantispam_ratelimit_cleanup_duration'] ?? null;
    $old_duration = $old_value['oopspamantispam_ratelimit_cleanup_duration'] ?? null;


    if (oopspam_isRateLimitingEnabled() && !wp_next_scheduled("oopspam_cleanup_ratelimit_entries_cron")) {
        if (class_exists('OOPSPAM\RateLimiting\OOPSpam_RateLimiter')) {
            try {
                $rateLimiter = new OOPSpam_RateLimiter();
                $rateLimiter->schedule_cleanup($new_duration);
            } catch (Exception $e) {
                error_log("Error scheduling cleanup job: " . $e->getMessage());
            }
        } else {
            error_log("OOPSpam_RateLimiter class not found");
        }
    }
    // Case 2: Duration changed while rate limit is enabled - reschedule cleanup job
    elseif ($new_duration !== $old_duration && oopspam_isRateLimitingEnabled()) {
        if (class_exists('OOPSPAM\RateLimiting\OOPSpam_RateLimiter')) {
            try {
                $rateLimiter = new OOPSpam_RateLimiter();
                $rateLimiter->reschedule_cleanup($old_duration, $new_duration);
            } catch (Exception $e) {
                error_log("Error rescheduling cleanup job: " . $e->getMessage());
            }
        } else {
            error_log("OOPSpam_RateLimiter class not found");
        }
    }
    // Case 3: Rate limit fields are cleared - unschedule any existing cleanup job
    elseif (!oopspam_isRateLimitingEnabled()) {
        $timestamp = wp_next_scheduled('oopspam_cleanup_ratelimit_entries_cron');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'oopspam_cleanup_ratelimit_entries_cron');
            $rateLimiter = new OOPSpam_RateLimiter();
            $rateLimiter->oopspam_truncate_ratelimit();
        }
    }
}


function schedule_cron_job($hook, $frequency)
{
    wp_clear_scheduled_hook($hook);
    $interval = $frequency === "monthly" ? '+1 month' : '+2 weeks';
    $schedule = $frequency === "monthly" ? 'oopspam-monthly' : 'oopspam-biweekly';
    wp_schedule_event(strtotime($interval), $schedule, $hook);
}



function manual_moderation_blockedemails_render() {
    $manual_moderation_options = get_option('manual_moderation_settings');
    $blocked_emails = isset($manual_moderation_options['mm_blocked_emails']) ? $manual_moderation_options['mm_blocked_emails'] : '';
    ?>
    <details>
        <summary><?php echo __('View blocked emails', 'oopspam'); ?></summary>
        <div style="margin-top: 10px;">
            <textarea name="manual_moderation_settings[mm_blocked_emails]" 
                      placeholder="testing@example.com&#10;test@test.com&#10;*@example.com"  
                      rows="10" 
                      cols="50" 
                      id="mm_blocked_emails" 
                      class="large-text code"><?php echo esc_textarea($blocked_emails); ?></textarea>
            <p class="description">
                <?php echo __('One email per line', 'oopspam'); ?>
            </p>
        </div>
    </details>
    <?php
}

function manual_moderation_blockedips_render() {
    $manual_moderation_options = get_option('manual_moderation_settings');
    $mm_blocked_ips = isset($manual_moderation_options['mm_blocked_ips']) ? $manual_moderation_options['mm_blocked_ips'] : '';
    ?>
    <details>
        <summary><?php echo __('View blocked IPs', 'oopspam'); ?></summary>
        <div style="margin-top: 10px;">
            <textarea name="manual_moderation_settings[mm_blocked_ips]" 
                      placeholder="125.450.87.89&#10;127.0.0.1&#10;192.168.1.0/24&#10;10.0.0.1-10.0.0.50"  
                      rows="10" 
                      cols="50" 
                      id="mm_blocked_ips" 
                      class="large-text code"><?php echo esc_textarea($mm_blocked_ips); ?></textarea>
            <p class="description">
                <?php echo __('One IP per line. Supports individual IPs (e.g., 127.0.0.1), CIDR notation (e.g., 192.168.1.0/24), or IP ranges (e.g., 192.168.1.1-192.168.1.10)', 'oopspam'); ?>
            </p>
        </div>
    </details>
    <?php
}

function manual_moderation_keywords_render() {
    $manual_moderation_options = get_option('manual_moderation_settings');
    $mm_blocked_keywords = isset($manual_moderation_options['mm_blocked_keywords']) ? $manual_moderation_options['mm_blocked_keywords'] : '';
    ?>
    <details>
        <summary><?php echo __('View blocked keywords', 'oopspam'); ?></summary>
        <div style="margin-top: 10px;">
            <textarea name="manual_moderation_settings[mm_blocked_keywords]" 
                      placeholder="seo&#10;invest"  
                      rows="10" 
                      cols="50" 
                      id="mm_blocked_keywords" 
                      class="large-text code"><?php echo esc_textarea($mm_blocked_keywords); ?></textarea>
            <p class="description">
                <?php echo __('One keyword per line. It will do exact match, so "seo" will match "seo", not "seoul".', 'oopspam'); ?>
            </p>
        </div>
    </details>
    <?php
}

function manual_moderation_allowedemails_render() {
    $manual_moderation_options = get_option('manual_moderation_settings');
    $allowed_emails = isset($manual_moderation_options['mm_allowed_emails']) ? $manual_moderation_options['mm_allowed_emails'] : '';
    ?>
    <details>
        <summary><?php echo __('View allowed emails', 'oopspam'); ?></summary>
        <div style="margin-top: 10px;">
            <textarea name="manual_moderation_settings[mm_allowed_emails]" 
                      placeholder="testing@example.com&#10;test@test.com&#10;*@example.com"  
                      rows="10" 
                      cols="50" 
                      id="mm_allowed_emails" 
                      class="large-text code"><?php echo esc_textarea($allowed_emails); ?></textarea>
            <p class="description">
                <?php echo __('One email per line', 'oopspam'); ?>
            </p>
        </div>
    </details>
    <?php
}

function manual_moderation_allowedips_render() {
    $manual_moderation_options = get_option('manual_moderation_settings');
    $mm_allowed_ips = isset($manual_moderation_options['mm_allowed_ips']) ? $manual_moderation_options['mm_allowed_ips'] : '';
    ?>
    <details>
        <summary><?php echo __('View allowed IPs', 'oopspam'); ?></summary>
        <div style="margin-top: 10px;">
            <textarea name="manual_moderation_settings[mm_allowed_ips]" 
                      placeholder="125.450.87.89&#10;127.0.0.1&#10;192.168.1.0/24&#10;10.0.0.1-10.0.0.50"  
                      rows="10" 
                      cols="50" 
                      id="mm_allowed_ips" 
                      class="large-text code"><?php echo esc_textarea($mm_allowed_ips); ?></textarea>
            <p class="description">
                <?php echo __('One IP per line. Supports individual IPs (e.g., 127.0.0.1), CIDR notation (e.g., 192.168.1.0/24), or IP ranges (e.g., 192.168.1.1-192.168.1.10)', 'oopspam'); ?>
            </p>
        </div>
    </details>
    <?php
}

function render_section_info() {
    echo '<p>Configure rate limits to control the number of allowed submissions per IP and email, effectively preventing spam.</p>
<p><strong>Note</strong>: To reset all limits, toggle the ‘Enable rate limiting’ setting off, save, then toggle it back on.</p>';
}

function render_number_field($args) {
    $option_name = $args['label_for'];
    $rtOptions = get_option('oopspamantispam_ratelimit_settings');
    
    // Define default values for the rate limit settings
    $default_values = [
        'oopspamantispam_ratelimit_ip_limit' => 3,
        'oopspamantispam_ratelimit_email_limit' => 3,
        'oopspamantispam_ratelimit_block_duration' => 24,
        'oopspamantispam_ratelimit_cleanup_duration' => 48
    ];
    
    // Determine the value to display in the input field
    $value = isset($rtOptions[$option_name]) ? $rtOptions[$option_name] : '';
    ?>
    <div>
        <input required type="number" min="1" step="1" 
               id="<?php echo esc_attr($option_name); ?>" 
               name="oopspamantispam_ratelimit_settings[<?php echo esc_attr($option_name); ?>]" 
               value="<?php echo esc_attr($value); ?>" 
               placeholder="Example: <?php echo esc_attr($default_values[$option_name]); ?>"
               class="regular-text">
    </div>
    <?php
}

function render_oopspamantispam_ratelimit_gclid_limit($args) {
    $option_name = $args['label_for'];
    $rtOptions = get_option('oopspamantispam_ratelimit_settings');

    $value = isset($rtOptions[$option_name]) ? $rtOptions[$option_name] : '';
    ?>
    <div>
        <input type="number" min="1" step="1" 
               id="<?php echo esc_attr($option_name); ?>" 
               name="oopspamantispam_ratelimit_settings[<?php echo esc_attr($option_name); ?>]" 
               value="<?php echo esc_attr($value); ?>" 
               placeholder="Example: 1"
               class="regular-text">
    </div>
    <p class="description">
        <?php echo __('This setting works independently and does not require the \'Enable Rate Limiting\' option.', 'oopspam'); ?>
    </p>
    <?php
}

function render_oopspam_min_submission_time_field($args) {
    $option_name = $args['label_for'];
    $rtOptions = get_option('oopspamantispam_ratelimit_settings');
    
    $value = isset($rtOptions[$option_name]) ? $rtOptions[$option_name] : '';
    ?>
    <div>
        <input type="number" min="1" step="1" 
               id="<?php echo esc_attr($option_name); ?>" 
               name="oopspamantispam_ratelimit_settings[<?php echo esc_attr($option_name); ?>]" 
               value="<?php echo esc_attr($value); ?>" 
               placeholder="Example: 2"
               class="regular-text">
        <p class="description">
            <?php echo __('Submissions faster than this will be marked as spam. Most humans take at least 2-3 seconds to fill out a form.', 'oopspam'); ?>
        </p>
    </div>
    <?php
}

function sanitize_positive_int($value) {
    $value = absint($value);
    return max(1, $value); // Ensure value is at least 1
}

function oopspam_sanitize_settings($input) {
    // Get existing settings
    $existing_settings = get_option('oopspamantispam_settings');
    
    // Preserve the API usage value
    if (isset($existing_settings['oopspam_api_key_usage'])) {
        $input['oopspam_api_key_usage'] = $existing_settings['oopspam_api_key_usage'];
    }
    
    return $input;
}

function oopspamantispam_settings_init()
{

    // Register settings
    register_setting('oopspamantispam-manual-moderation', 'manual_moderation_settings');
    register_setting('oopspamantispam-privacy-settings-group', 'oopspamantispam_privacy_settings');
    register_setting('oopspamantispam-ratelimit-settings-group', 'oopspamantispam_ratelimit_settings');
    register_setting('oopspamantispam-ipfiltering-settings-group', 'oopspamantispam_ipfiltering_settings');
    register_setting('oopspamantispam-settings-group', 'oopspamantispam_settings', 'oopspam_sanitize_settings');


    add_settings_section('manual_moderation_section', 'Manual Moderation Settings', false, 'oopspamantispam-manual-moderation');
    add_settings_field('mm_blocked_emails', __('Blocked emails'), 'manual_moderation_blockedemails_render', 'oopspamantispam-manual-moderation', 'manual_moderation_section');
    add_settings_field('mm_blocked_ips', __('Blocked IPs'), 'manual_moderation_blockedips_render', 'oopspamantispam-manual-moderation', 'manual_moderation_section');
    add_settings_field('mm_blocked_keywords', __('Blocked keywords'), 'manual_moderation_keywords_render', 'oopspamantispam-manual-moderation', 'manual_moderation_section');
    add_settings_field('mm_allowed_emails', __('Allowed emails'), 'manual_moderation_allowedemails_render', 'oopspamantispam-manual-moderation', 'manual_moderation_section');
    add_settings_field('mm_allowed_ips', __('Allowed IPs'), 'manual_moderation_allowedips_render', 'oopspamantispam-manual-moderation', 'manual_moderation_section');


    add_settings_section(
        'oopspam_privacy_settings_section',
        __('Privacy Settings', 'oopspam'),
        false,
        'oopspamantispam-privacy-settings-group'
    );

    add_settings_field(
        'oopspam_is_check_for_ip',
        __('Do not analyze IP addresses', 'oopspam'),
        'oopspam_is_check_for_ip_render',
        'oopspamantispam-privacy-settings-group',
        'oopspam_privacy_settings_section'
    );
    add_settings_field(
        'oopspam_is_check_for_email',
        __('Do not analyze Email addresses', 'oopspam'),
        'oopspam_is_check_for_email_render',
        'oopspamantispam-privacy-settings-group',
        'oopspam_privacy_settings_section'
    );
    add_settings_field(
        'oopspam_anonym_content',
        __('Remove sensitive information from messages', 'oopspam'),
        'oopspam_anonym_content_render',
        'oopspamantispam-privacy-settings-group',
        'oopspam_privacy_settings_section'
    );

    register_setting('oopspamantispam-settings-group', 'oopspamantispam_settings');
    register_setting('oopspamantispam-settings-group', 'oopspam_countryallowlist');
    register_setting('oopspamantispam-settings-group', 'oopspam_countryblocklist');
    register_setting('oopspamantispam-settings-group', 'oopspam_country_always_allow');
    register_setting('oopspamantispam-settings-group', 'oopspam_languageallowlist');
    register_setting('oopspamantispam-settings-group', 'oopspam_admin_emails');

    register_setting('oopspamantispam-cf7-settings-group', 'oopspamantispam_settings');
    register_setting('oopspamantispam-nj-settings-group', 'oopspamantispam_settings');
    register_setting('oopspamantispam-gf-settings-group', 'oopspamantispam_settings');
    register_setting('oopspamantispam-el-settings-group', 'oopspamantispam_settings');
    register_setting('oopspamantispam-ff-settings-group', 'oopspamantispam_settings');
    register_setting('oopspamantispam-wpf-settings-group', 'oopspamantispam_settings');
    register_setting('oopspamantispam-fable-settings-group', 'oopspamantispam_settings');
    register_setting('oopspamantispam-give-settings-group', 'oopspamantispam_settings');
    register_setting('oopspamantispam-wpregister-settings-group', 'oopspamantispam_settings');
    register_setting('oopspamantispam-woo-settings-group', 'oopspamantispam_settings', 'sanitize_oopspam_settings');
    register_setting('oopspamantispam-br-settings-group', 'oopspamantispam_settings');
    register_setting('oopspamantispam-ws-settings-group', 'oopspamantispam_settings');
    register_setting('oopspamantispam-ts-settings-group', 'oopspamantispam_settings');
    register_setting('oopspamantispam-pionet-settings-group', 'oopspamantispam_settings');
    register_setting('oopspamantispam-kb-settings-group', 'oopspamantispam_settings');
    register_setting('oopspamantispam-wpdis-settings-group', 'oopspamantispam_settings');
    register_setting('oopspamantispam-mc4wp-settings-group', 'oopspamantispam_settings');
    register_setting('oopspamantispam-mpoet-settings-group', 'oopspamantispam_settings');
    register_setting('oopspamantispam-forminator-settings-group', 'oopspamantispam_settings');
    register_setting('oopspamantispam-bd-settings-group', 'oopspamantispam_settings');
    register_setting('oopspamantispam-bb-settings-group', 'oopspamantispam_settings');
    register_setting('oopspamantispam-umember-settings-group', 'oopspamantispam_settings');
    register_setting('oopspamantispam-mpress-settings-group', 'oopspamantispam_settings');
    register_setting('oopspamantispam-happyforms-settings-group', 'oopspamantispam_settings');
    register_setting('oopspamantispam-quform-settings-group', 'oopspamantispam_settings');

    // Add settings section
    add_settings_section(
        'oopspamantispam_ratelimit_section',
        'Rate Limiting Settings',
        'render_section_info',
        'oopspamantispam-ratelimit-settings-group'
    );

    // Start Register Rate Limit settings
    add_settings_field(
        'oopspam_is_rt_enabled',
        __('Enable rate limiting', 'oopspam'),
        'oopspam_is_rt_enabled_render',
        'oopspamantispam-ratelimit-settings-group',
        'oopspamantispam_ratelimit_section'
    );
    
    add_settings_field(
        'oopspamantispam_ratelimit_ip_limit',
        'Max Submissions per IP per Hour',
        'render_number_field',
        'oopspamantispam-ratelimit-settings-group',
        'oopspamantispam_ratelimit_section',
        ['label_for' => 'oopspamantispam_ratelimit_ip_limit']
    );
    
    add_settings_field(
        'oopspamantispam_ratelimit_email_limit',
        'Max Submissions per Email per Hour',
        'render_number_field',
        'oopspamantispam-ratelimit-settings-group',
        'oopspamantispam_ratelimit_section',
        ['label_for' => 'oopspamantispam_ratelimit_email_limit']
    );
    
    add_settings_field(
        'oopspamantispam_ratelimit_block_duration',
        'Block Duration (in hours)',
        'render_number_field',
        'oopspamantispam-ratelimit-settings-group',
        'oopspamantispam_ratelimit_section',
        ['label_for' => 'oopspamantispam_ratelimit_block_duration']
    );
    
    add_settings_field(
        'oopspamantispam_ratelimit_cleanup_duration',
        'Data Clean Up Frequency (in hours)',
        'render_number_field',
        'oopspamantispam-ratelimit-settings-group',
        'oopspamantispam_ratelimit_section',
        ['label_for' => 'oopspamantispam_ratelimit_cleanup_duration']
    );
    
    add_settings_field(
        'oopspamantispam_ratelimit_gclid_limit',
        'Restrict submissions per Google Ads lead',
        'render_oopspamantispam_ratelimit_gclid_limit',
        'oopspamantispam-ratelimit-settings-group',
        'oopspamantispam_ratelimit_section',
        ['label_for' => 'oopspamantispam_ratelimit_gclid_limit']
    );

    add_settings_field(
        'oopspamantispam_min_submission_time',
        'Minimum Time Between Page Load and Submission (in seconds)',
        'render_oopspam_min_submission_time_field',
        'oopspamantispam-ratelimit-settings-group',
        'oopspamantispam_ratelimit_section',
        ['label_for' => 'oopspamantispam_min_submission_time']
    );
    // End Register Rate Limit settings


    add_settings_section('oopspam_settings_section',
        __('OOPSpam - General Settings', 'oopspam'),
        false,
        'oopspamantispam-settings-group'
    );

    add_settings_field('oopspam_api_key_usage',
        __('Current usage', 'oopspam'),
        'oopspam_api_key_usage_render',
        'oopspamantispam-settings-group',
        'oopspam_settings_section'
    );

    add_settings_field('oopspam_api_key_source',
        __('I got my API Key from', 'oopspam'),
        'oopspam_api_key_source_render',
        'oopspamantispam-settings-group',
        'oopspam_settings_section'
    );

    add_settings_field('oopspam_api_key',
        __('My API Key', 'oopspam'),
        'oopspam_api_key_render',
        'oopspamantispam-settings-group',
        'oopspam_settings_section'
    );

    add_settings_field('oopspam_spam_score_threshold',
        __('Sensitivity level', 'oopspam'),
        'oopspam_spam_score_threshold_render',
        'oopspamantispam-settings-group',
        'oopspam_settings_section'
    );

    add_settings_field('oopspam_spam_movedspam_to_folder',
        __('Move spam comments to', 'oopspam'),
        'oopspam_spam_movedspam_to_folder_render',
        'oopspamantispam-settings-group',
        'oopspam_settings_section'
    );

    add_settings_field('oopspam_admin_emails',
        __('Admin emails', 'oopspam'),
        'oopspam_admin_emails_render',
        'oopspamantispam-settings-group',
        'oopspam_settings_section'
    );


    add_settings_field('oopspam_is_loggable',
    __('Log submissions to OOPSpam', 'oopspam'),
    'oopspam_is_loggable_render',
    'oopspamantispam-settings-group',
    'oopspam_settings_section'
    );

    add_settings_field('oopspam_disable_local_logging',
    __('Disable local logging', 'oopspam'),
    'oopspam_disable_local_logging_render',
    'oopspamantispam-settings-group',
    'oopspam_settings_section'
    );

    add_settings_field('oopspam_is_urls_allowed',
    __('Block messages containing URLs', 'oopspam'),
    'oopspam_is_urls_allowed_render',
    'oopspamantispam-settings-group',
    'oopspam_settings_section'
    );

    add_settings_field('oopspam_clear_spam_entries',
        __('Empty "Form Spam Entries" table every', 'oopspam'),
        'oopspam_clear_spam_entries_render',
        'oopspamantispam-settings-group',
        'oopspam_settings_section'
    );

    add_settings_field('oopspam_clear_ham_entries',
        __('Empty "Form Valid Entries" table every', 'oopspam'),
        'oopspam_clear_ham_entries_render',
        'oopspamantispam-settings-group',
        'oopspam_settings_section'
    );

    add_settings_field('oopspam_is_check_for_length',
        __('Consider short messages as spam', 'oopspam'),
        'oopspam_is_check_for_length_render',
        'oopspamantispam-settings-group',
        'oopspam_settings_section'
    );

    add_settings_field('oopspam_block_temp_email',
    __('Block disposable emails', 'oopspam'),
    'oopspam_block_temp_email_render',
    'oopspamantispam-settings-group',
    'oopspam_settings_section'
    );

    add_settings_field('oopspam_is_search_protection_on',
        __('Protect against internal search spam', 'oopspam'),
        'oopspam_is_search_protection_on_render',
        'oopspamantispam-settings-group',
        'oopspam_settings_section'
    );

    $privacy_options = get_option('oopspamantispam_privacy_settings');
    $isItAllowedToCheckIPs = isset($privacy_options['oopspam_is_check_for_ip']) ? $privacy_options['oopspam_is_check_for_ip'] : false;

    if(!$isItAllowedToCheckIPs) {
        add_settings_field('oopspam_country_always_allow',
            __('Trusted Countries:', 'oopspam') . 
            '<span class="oopspam-tooltip"><span class="dashicons dashicons-info-outline"></span><span class="tooltip-text">' . 
            __('Submissions from these countries will always bypass spam checks. Use this for countries you fully trust.', 'oopspam') . 
            '</span></span>',
            'oopspam_country_always_allow_render',
            'oopspamantispam-settings-group',
            'oopspam_settings_section'
        );

        add_settings_field('oopspam_countryallowlist',
            __('Country Allowlist:', 'oopspam') . 
            '<span class="oopspam-tooltip"><span class="dashicons dashicons-info-outline"></span><span class="tooltip-text">' . 
            __('Only accept submissions from these countries. All other countries will be blocked.', 'oopspam') . 
            '</span></span>',
            'oopspam_countryallowlist_render',
            'oopspamantispam-settings-group',
            'oopspam_settings_section'
        );

        add_settings_field('oopspam_countryblocklist',
            __('Country Blocklist:', 'oopspam') . 
            '<span class="oopspam-tooltip"><span class="dashicons dashicons-info-outline"></span><span class="tooltip-text">' . 
            __('Reject submissions from these countries. All other countries will be allowed.', 'oopspam') . 
            '</span></span>',
            'oopspam_countryblocklist_render',
            'oopspamantispam-settings-group',
            'oopspam_settings_section'
        );
    }

    
    add_settings_field('oopspam_languageallowlist',
        __('Language Allowlist:', 'oopspam') . 
        '<span class="oopspam-tooltip"><span class="dashicons dashicons-info-outline"></span><span class="tooltip-text">' . 
        __('Only process messages in these languages. Messages in other languages will be treated as spam.', 'oopspam') . 
        '</span></span>',
        'oopspam_languageallowlist_render',
        'oopspamantispam-settings-group',
        'oopspam_settings_section'
    );

    // Add this section after other general settings fields but before form-specific settings
    add_settings_section(
        'oopspam_vpn_cloud_section', 
        __('IP Filtering', 'oopspam'),
        false,
        'oopspamantispam-ipfiltering-settings-group'
    );

    add_settings_field(
        'oopspam_block_vpns',
        __('Block VPNs', 'oopspam'),
        'oopspam_block_vpns_render',
        'oopspamantispam-ipfiltering-settings-group',
        'oopspam_vpn_cloud_section'
    );

    add_settings_field(
        'oopspam_block_cloud_providers',
        __('Block Cloud Providers', 'oopspam'),
        'oopspam_block_cloud_providers_render',
        'oopspamantispam-ipfiltering-settings-group',
        'oopspam_vpn_cloud_section'
    );

    $options = get_option('oopspamantispam_settings');

    // Forminator settings section
    if (oopspamantispam_plugin_check('forminator') && !empty(oopspamantispam_get_key())) {

        add_settings_section('oopspam_forminator_settings_section',
            __('Forminator', 'oopspam'),
            false,
            'oopspamantispam-forminator-settings-group'
        );
        add_settings_field('oopspam_is_forminator_activated',
            __('Activate Spam Protection', 'oopspam'),
            'oopspam_is_forminator_activated_render',
            'oopspamantispam-forminator-settings-group',
            'oopspam_forminator_settings_section'
        );

        add_settings_field('oopspam_forminator_spam_message',
            __('Forminator Spam Message', 'oopspam'),
            'oopspam_forminator_spam_message_render',
            'oopspamantispam-forminator-settings-group',
            'oopspam_forminator_settings_section'
        );

        add_settings_field('oopspam_forminator_content_field',
            __('The main content field ID (optional)', 'oopspam'),
            'oopspam_forminator_content_field_render',
            'oopspamantispam-forminator-settings-group',
            'oopspam_forminator_settings_section'
        );
    }
    // Mailpoet settings section
    if (oopspamantispam_plugin_check('mpoet') && !empty(oopspamantispam_get_key())) {

        add_settings_section('oopspam_mpoet_settings_section',
            __('MailPoet', 'oopspam'),
            false,
            'oopspamantispam-mpoet-settings-group'
        );
        add_settings_field('oopspam_is_mpoet_activated',
            __('Activate Spam Protection', 'oopspam'),
            'oopspam_is_mpoet_activated_render',
            'oopspamantispam-mpoet-settings-group',
            'oopspam_mpoet_settings_section'
        );

        add_settings_field('oopspam_mpoet_spam_message',
            __('MailPoet Spam Message', 'oopspam'),
            'oopspam_mpoet_spam_message_render',
            'oopspamantispam-mpoet-settings-group',
            'oopspam_mpoet_settings_section'
        );
    }

    // MC4WP settings section
    if (oopspamantispam_plugin_check('mc4wp') && !empty(oopspamantispam_get_key())) {

        add_settings_section('oopspam_mc4wp_settings_section',
            __('MC4WP: Mailchimp for WordPress', 'oopspam'),
            false,
            'oopspamantispam-mc4wp-settings-group'
        );
        add_settings_field('oopspam_is_mc4wp_activated',
            __('Activate Spam Protection', 'oopspam'),
            'oopspam_is_mc4wp_activated_render',
            'oopspamantispam-mc4wp-settings-group',
            'oopspam_mc4wp_settings_section'
        );

        add_settings_field('oopspam_mc4wp_spam_message',
            __('Mailchimp Spam Message', 'oopspam'),
            'oopspam_mc4wp_spam_message_render',
            'oopspamantispam-mc4wp-settings-group',
            'oopspam_mc4wp_settings_section'
        );
    }

    // WPDiscuz settings section
    if (oopspamantispam_plugin_check('wpdis') && !empty(oopspamantispam_get_key())) {

        add_settings_section('oopspam_wpdis_settings_section',
            __('WPDiscuz', 'oopspam'),
            false,
            'oopspamantispam-wpdis-settings-group'
        );
        add_settings_field('oopspam_is_wpdis_activated',
            __('Activate Spam Protection', 'oopspam'),
            'oopspam_is_wpdis_activated_render',
            'oopspamantispam-wpdis-settings-group',
            'oopspam_wpdis_settings_section'
        );

        add_settings_field('oopspam_wpdis_spam_message',
            __('WPDiscuz Spam Message', 'oopspam'),
            'oopspam_wpdis_spam_message_render',
            'oopspamantispam-wpdis-settings-group',
            'oopspam_wpdis_settings_section'
        );
    }
    // Kadence Form Block settings section
    if (oopspamantispam_plugin_check('kb') && !empty(oopspamantispam_get_key())) {

        add_settings_section('oopspam_kb_settings_section',
            __('Kadence Form Block', 'oopspam'),
            false,
            'oopspamantispam-kb-settings-group'
        );
        add_settings_field('oopspam_is_kb_activated',
            __('Activate Spam Protection', 'oopspam'),
            'oopspam_is_kb_activated_render',
            'oopspamantispam-kb-settings-group',
            'oopspam_kb_settings_section'
        );

        add_settings_field('oopspam_kb_spam_message',
            __('Kadence Form Block Spam Message', 'oopspam'),
            'oopspam_kb_spam_message_render',
            'oopspamantispam-kb-settings-group',
            'oopspam_kb_settings_section'
        );
    }

    // Ninja Forms settings section
    if (oopspamantispam_plugin_check('nf') && !empty(oopspamantispam_get_key())) {

        add_settings_section('oopspam_nj_settings_section',
            __('Ninja Forms', 'oopspam'),
            false,
            'oopspamantispam-nj-settings-group'
        );
        add_settings_field('oopspam_is_nj_activated',
            __('Activate Spam Protection', 'oopspam'),
            'oopspam_is_nj_activated_render',
            'oopspamantispam-nj-settings-group',
            'oopspam_nj_settings_section'
        );

        add_settings_field('oopspam_nj_spam_message',
            __('Ninja Forms Spam Message', 'oopspam'),
            'oopspam_nj_spam_message_render',
            'oopspamantispam-nj-settings-group',
            'oopspam_nj_settings_section'
        );

        add_settings_field('oopspam_nj_content_field',
            __('The main content field ID (optional)', 'oopspam'),
            'oopspam_nj_content_field_render',
            'oopspamantispam-nj-settings-group',
            'oopspam_nj_settings_section'
        );

        add_settings_field('oopspam_nj_exclude_form',
        __("Don't protect these forms", 'oopspam'),
        'oopspam_nj_exclude_form_render',
        'oopspamantispam-nj-settings-group',
        'oopspam_nj_settings_section'
    );
    }

     // Piotnet Forms settings section
     if (oopspamantispam_plugin_check('pionet') && !empty(oopspamantispam_get_key())) {

        add_settings_section('oopspam_pionet_settings_section',
            __('Piotnet Forms', 'oopspam'),
            false,
            'oopspamantispam-pionet-settings-group'
        );
        add_settings_field('oopspam_is_pionet_activated',
            __('Activate Spam Protection', 'oopspam'),
            'oopspam_is_pionet_activated_render',
            'oopspamantispam-pionet-settings-group',
            'oopspam_pionet_settings_section'
        );

        add_settings_field('oopspam_pionet_spam_message',
            __('Pionet Forms Spam Message', 'oopspam'),
            'oopspam_pionet_spam_message_render',
            'oopspamantispam-pionet-settings-group',
            'oopspam_pionet_settings_section'
        );

        add_settings_field('oopspam_pionet_content_field',
            __('The main content field ID (optional)', 'oopspam'),
            'oopspam_pionet_content_field_render',
            'oopspamantispam-pionet-settings-group',
            'oopspam_pionet_settings_section'
        );

        add_settings_field('oopspam_pionet_exclude_form',
        __("Don't protect these forms", 'oopspam'),
        'oopspam_pionet_exclude_form_render',
        'oopspamantispam-pionet-settings-group',
        'oopspam_pionet_settings_section'
    );
    }

    // Toolset Forms settings section
    if (oopspamantispam_plugin_check('ts') && !empty(oopspamantispam_get_key())) {

        add_settings_section('oopspam_ts_settings_section',
            __('Toolset Forms', 'oopspam'),
            false,
            'oopspamantispam-ts-settings-group'
        );
        add_settings_field('oopspam_is_ts_activated',
            __('Activate Spam Protection', 'oopspam'),
            'oopspam_is_ts_activated_render',
            'oopspamantispam-ts-settings-group',
            'oopspam_ts_settings_section'
        );

        add_settings_field('oopspam_ts_spam_message',
            __('Toolset Forms Spam Message', 'oopspam'),
            'oopspam_ts_spam_message_render',
            'oopspamantispam-ts-settings-group',
            'oopspam_ts_settings_section'
        );
    }

    // Formidable Forms settings section
    if (oopspamantispam_plugin_check('fable') && !empty(oopspamantispam_get_key())) {

        add_settings_section('oopspam_fable_settings_section',
            __('Formidable Forms', 'oopspam'),
            false,
            'oopspamantispam-fable-settings-group'
        );
        add_settings_field('oopspam_is_fable_activated',
            __('Activate Spam Protection', 'oopspam'),
            'oopspam_is_fable_activated_render',
            'oopspamantispam-fable-settings-group',
            'oopspam_fable_settings_section'
        );

        add_settings_field('oopspam_fable_spam_message',
            __('Formidable Forms Spam Message', 'oopspam'),
            'oopspam_fable_spam_message_render',
            'oopspamantispam-fable-settings-group',
            'oopspam_fable_settings_section'
        );

        add_settings_field('oopspam_fable_content_field',
            __('The main content field ID (optional)', 'oopspam'),
            'oopspam_fable_content_field_render',
            'oopspamantispam-fable-settings-group',
            'oopspam_fable_settings_section'
        );

        add_settings_field('oopspam_fable_exclude_form',
        __("Don't protect these forms", 'oopspam'),
        'oopspam_fable_exclude_form_render',
        'oopspamantispam-fable-settings-group',
        'oopspam_fable_settings_section'
    );

    }

    // Gravity Forms settings section
    if (oopspamantispam_plugin_check('gf') && !empty(oopspamantispam_get_key())) {

        add_settings_section('oopspam_gf_settings_section',
            __('Gravity Forms', 'oopspam'),
            false,
            'oopspamantispam-gf-settings-group'
        );
        add_settings_field('oopspam_is_gf_activated',
            __('Activate Spam Protection', 'oopspam'),
            'oopspam_is_gf_activated_render',
            'oopspamantispam-gf-settings-group',
            'oopspam_gf_settings_section'
        );

        add_settings_field('oopspam_gf_spam_message',
            __('Gravity Forms Spam Message', 'oopspam'),
            'oopspam_gf_spam_message_render',
            'oopspamantispam-gf-settings-group',
            'oopspam_gf_settings_section'
        );

        add_settings_field('oopspam_gf_content_field',
            __('The main content field ID (optional)', 'oopspam'),
            'oopspam_gf_content_field_render',
            'oopspamantispam-gf-settings-group',
            'oopspam_gf_settings_section'
        );
        add_settings_field('oopspam_gf_exclude_form',
        __("Don't protect these forms", 'oopspam'),
        'oopspam_gf_exclude_form_render',
        'oopspamantispam-gf-settings-group',
        'oopspam_gf_settings_section'
    );
    }

    // Elementor Forms settings section
    if (oopspamantispam_plugin_check('el') && !empty(oopspamantispam_get_key())) {

        add_settings_section('oopspam_el_settings_section',
            __('Elementor Forms', 'oopspam'),
            false,
            'oopspamantispam-el-settings-group'
        );
        add_settings_field('oopspam_is_el_activated',
            __('Activate Spam Protection', 'oopspam'),
            'oopspam_is_el_activated_render',
            'oopspamantispam-el-settings-group',
            'oopspam_el_settings_section'
        );

        add_settings_field('oopspam_el_spam_message',
            __('Elementor Forms Spam Message', 'oopspam'),
            'oopspam_el_spam_message_render',
            'oopspamantispam-el-settings-group',
            'oopspam_el_settings_section'
        );

        add_settings_field('oopspam_el_content_field',
            __('The main content field ID (optional)', 'oopspam'),
            'oopspam_el_content_field_render',
            'oopspamantispam-el-settings-group',
            'oopspam_el_settings_section'
        );

        add_settings_field('oopspam_el_exclude_form',
            __("Don't protect these forms", 'oopspam'),
            'oopspam_el_exclude_form_render',
            'oopspamantispam-el-settings-group',
            'oopspam_el_settings_section'
        );
    }

    // Brick Forms settings section
    if (oopspamantispam_plugin_check('br') && !empty(oopspamantispam_get_key())) {

        add_settings_section('oopspam_br_settings_section',
            __('Bricks Forms', 'oopspam'),
            false,
            'oopspamantispam-br-settings-group'
        );
        add_settings_field('oopspam_is_br_activated',
            __('Activate Spam Protection', 'oopspam'),
            'oopspam_is_br_activated_render',
            'oopspamantispam-br-settings-group',
            'oopspam_br_settings_section'
        );

        add_settings_field('oopspam_br_spam_message',
            __('Bricks Forms Spam Message', 'oopspam'),
            'oopspam_br_spam_message_render',
            'oopspamantispam-br-settings-group',
            'oopspam_br_settings_section'
        );

        add_settings_field('oopspam_br_content_field',
            __('The main content field ID (optional)', 'oopspam'),
            'oopspam_br_content_field_render',
            'oopspamantispam-br-settings-group',
            'oopspam_br_settings_section'
        );

        add_settings_field('oopspam_br_exclude_form',
        __("Don't protect these forms", 'oopspam'),
        'oopspam_br_exclude_form_render',
        'oopspamantispam-br-settings-group',
        'oopspam_br_settings_section'
    );
    }

     // HappyForms settings section
    if (oopspamantispam_plugin_check('happyforms') && !empty(oopspamantispam_get_key())) {

        add_settings_section('oopspam_happyforms_settings_section',
            __('HappyForms', 'oopspam'),
            false,
            'oopspamantispam-happyforms-settings-group'
        );
        add_settings_field('oopspam_is_happyforms_activated',
            __('Activate Spam Protection', 'oopspam'),
            'oopspam_is_happyforms_activated_render',
            'oopspamantispam-happyforms-settings-group',
            'oopspam_happyforms_settings_section'
        );

        add_settings_field('oopspam_happyforms_spam_message',
        __('HappyForms Spam Message', 'oopspam'),
        'oopspam_happyforms_spam_message_render',
        'oopspamantispam-happyforms-settings-group',
        'oopspam_happyforms_settings_section'
    );

        add_settings_field('oopspam_happyforms_exclude_form',
            __("Don't protect these forms", 'oopspam'),
            'oopspam_happyforms_exclude_form_render',
            'oopspamantispam-happyforms-settings-group',
            'oopspam_happyforms_settings_section'
        );
    }

    // WS Form settings section
    if (oopspamantispam_plugin_check('ws') && !empty(oopspamantispam_get_key())) {

        add_settings_section('oopspam_ws_settings_section',
            __('WS Form', 'oopspam'),
            false,
            'oopspamantispam-ws-settings-group'
        );
        add_settings_field('oopspam_is_ws_activated',
            __('Activate Spam Protection', 'oopspam'),
            'oopspam_is_ws_activated_render',
            'oopspamantispam-ws-settings-group',
            'oopspam_ws_settings_section'
        );

        add_settings_field('oopspam_ws_spam_message',
        __('WS Form Spam Message', 'oopspam'),
        'oopspam_ws_spam_message_render',
        'oopspamantispam-ws-settings-group',
        'oopspam_ws_settings_section'
    );

        add_settings_field('oopspam_ws_content_field',
            __('The main content field ID (optional)', 'oopspam'),
            'oopspam_ws_content_field_render',
            'oopspamantispam-ws-settings-group',
            'oopspam_ws_settings_section'
        );

        add_settings_field('oopspam_ws_exclude_form',
            __("Don't protect these forms", 'oopspam'),
            'oopspam_ws_exclude_form_render',
            'oopspamantispam-ws-settings-group',
            'oopspam_ws_settings_section'
        );

        
    }

    // WPForms settings section
    if (oopspamantispam_plugin_check('wpf') && !empty(oopspamantispam_get_key())) {

        add_settings_section('oopspam_wpf_settings_section',
            __('WPForms', 'oopspam'),
            false,
            'oopspamantispam-wpf-settings-group'
        );
        add_settings_field('oopspam_is_wpf_activated',
            __('Activate Spam Protection', 'oopspam'),
            'oopspam_is_wpf_activated_render',
            'oopspamantispam-wpf-settings-group',
            'oopspam_wpf_settings_section'
        );

        add_settings_field('oopspam_wpf_spam_message',
            __('WPForms Spam Message', 'oopspam'),
            'oopspam_wpf_spam_message_render',
            'oopspamantispam-wpf-settings-group',
            'oopspam_wpf_settings_section'
        );

        add_settings_field('oopspam_wpf_content_field',
            __('The main content field (optional)', 'oopspam'),
            'oopspam_wpf_content_field_render',
            'oopspamantispam-wpf-settings-group',
            'oopspam_wpf_settings_section'
        );

        add_settings_field('oopspam_wpf_exclude_form',
            __("Don't protect these forms", 'oopspam'),
            'oopspam_wpf_exclude_form_render',
            'oopspamantispam-wpf-settings-group',
            'oopspam_wpf_settings_section'
        );
    }

    /* Jetpack Form settings section starts */

function oopspam_is_jform_activated_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
                <div>
                    <label for="jform_support">
                    <input class="oopspam-toggle" type="checkbox" id="jform_support" name="oopspamantispam_settings[oopspam_is_jform_activated]" value="1" <?php if (isset($options['oopspam_is_jform_activated']) && 1 == $options['oopspam_is_jform_activated']) {
        echo 'checked="checked"';
    }
    ?>/>
                    </label>
                </div>
            <?php
}

function oopspam_jform_spam_message_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                    <label for="oopspam_jform_spam_message">
                    <input id="oopspam_jform_spam_message" type="text" class="regular-text" name="oopspamantispam_settings[oopspam_jform_spam_message]" value="<?php if (isset($options['oopspam_jform_spam_message'])) {
        esc_html_e($options['oopspam_jform_spam_message'], "oopspam");
    }
    ?>">
                        <p class="description"><?php echo __('Enter a short message to display when a spam Jetpack Form entry has been submitted. (e.g Our spam detection classified your donation as spam. Please contact via name@example.com)', 'oopspam'); ?></p>
                        </label>
                </div>
            <?php
}


/* Jetpack Form settings section ends */

    // Contact Form 7 settings section
    if (oopspamantispam_plugin_check('cf7') && !empty(oopspamantispam_get_key())) {

        add_settings_section('oopspam_cf7_settings_section',
            __('Contact Form 7', 'oopspam'),
            false,
            'oopspamantispam-cf7-settings-group'
        );
        add_settings_field('oopspam_is_cf7_activated',
            __('Activate Spam Protection', 'oopspam'),
            'oopspam_is_cf7_activated_render',
            'oopspamantispam-cf7-settings-group',
            'oopspam_cf7_settings_section'
        );

        add_settings_field('oopspam_cf7_spam_message',
            __('Contact Form 7 Spam Message', 'oopspam'),
            'oopspam_cf7_spam_message_render',
            'oopspamantispam-cf7-settings-group',
            'oopspam_cf7_settings_section'
        );

        add_settings_field('oopspam_is_cf7_content_field',
            __('The main content field ID (optional)', 'oopspam'),
            'oopspam_cf7_content_field_render',
            'oopspamantispam-cf7-settings-group',
            'oopspam_cf7_settings_section'
        );
    }

    // Jetpack Forms settings section
    if (oopspamantispam_plugin_check('jform') && !empty(oopspamantispam_get_key())) {

        add_settings_section('oopspam_jform_settings_section',
            __('Jetpack Form', 'oopspam'),
            false,
            'oopspamantispam-jform-settings-group'
        );
        add_settings_field('oopspam_is_jform_activated',
            __('Activate Spam Protection', 'oopspam'),
            'oopspam_is_jform_activated_render',
            'oopspamantispam-jform-settings-group',
            'oopspam_jform_settings_section'
        );

        add_settings_field('oopspam_jform_spam_message',
            __('Jetpack Form Spam Message', 'oopspam'),
            'oopspam_jform_spam_message_render',
            'oopspamantispam-jform-settings-group',
            'oopspam_jform_settings_section'
        );
    }
    
    // Fluent Forms settings section
    if (oopspamantispam_plugin_check('ff') && !empty(oopspamantispam_get_key())) {

        add_settings_section('oopspam_ff_settings_section',
            __('Fluent Forms', 'oopspam'),
            false,
            'oopspamantispam-ff-settings-group'
        );
        add_settings_field('oopspam_is_ff_activated',
            __('Activate Spam Protection', 'oopspam'),
            'oopspam_is_ff_activated_render',
            'oopspamantispam-ff-settings-group',
            'oopspam_ff_settings_section'
        );

        add_settings_field('oopspam_ff_spam_message',
            __('Fluent Forms Spam Message', 'oopspam'),
            'oopspam_ff_spam_message_render',
            'oopspamantispam-ff-settings-group',
            'oopspam_ff_settings_section'
        );

        add_settings_field('oopspam_ff_content_field',
            __('The main content field ID (optional)', 'oopspam'),
            'oopspam_ff_content_field_render',
            'oopspamantispam-ff-settings-group',
            'oopspam_ff_settings_section'
        );

        add_settings_field('oopspam_ff_exclude_form',
        __("Don't protect these forms", 'oopspam'),
        'oopspam_ff_exclude_form_render',
        'oopspamantispam-ff-settings-group',
        'oopspam_ff_settings_section'
    );
    }

     // Breakdance form settings section
     if (oopspamantispam_plugin_check('bd') && !empty(oopspamantispam_get_key())) {

        add_settings_section('oopspam_bd_settings_section',
            __('Breakdance Forms', 'oopspam'),
            false,
            'oopspamantispam-bd-settings-group');

        add_settings_field('oopspam_bd_spam_message',
            __('Breakdance Forms Spam Message', 'oopspam'),
            'oopspam_bd_spam_message_render',
            'oopspamantispam-bd-settings-group',
            'oopspam_bd_settings_section'
        );

        add_settings_field('oopspam_bd_content_field',
            __('The main content field ID (optional)', 'oopspam'),
            'oopspam_bd_content_field_render',
            'oopspamantispam-bd-settings-group',
            'oopspam_bd_settings_section'
        );

        add_settings_field('oopspam_bd_exclude_form',
        __("Don't protect these forms", 'oopspam'),
        'oopspam_bd_exclude_form_render',
        'oopspamantispam-bd-settings-group',
        'oopspam_bd_settings_section'
    );
    }

     // Beaver Builder contact form settings section
     if (oopspamantispam_plugin_check('bb') && !empty(oopspamantispam_get_key())) {

        add_settings_section('oopspam_bb_settings_section',
            __('Beaver Builder Forms', 'oopspam'),
            false,
            'oopspamantispam-bb-settings-group');

            add_settings_field('oopspam_is_bb_activated',
            __('Activate Spam Protection', 'oopspam'),
            'oopspam_is_bb_activated_render',
            'oopspamantispam-bb-settings-group',
            'oopspam_bb_settings_section'
        );

    }

    // GiveWP settings section
    if (oopspamantispam_plugin_check('give') && !empty(oopspamantispam_get_key())) {

        add_settings_section('oopspam_give_settings_section',
            __('GiveWP', 'oopspam'),
            false,
            'oopspamantispam-give-settings-group'
        );
        add_settings_field('oopspam_is_give_activated',
            __('Activate Spam Protection', 'oopspam'),
            'oopspam_is_give_activated_render',
            'oopspamantispam-give-settings-group',
            'oopspam_give_settings_section'
        );

        add_settings_field('oopspam_give_spam_message',
            __('GiveWP Forms Spam Message', 'oopspam'),
            'oopspam_give_spam_message_render',
            'oopspamantispam-give-settings-group',
            'oopspam_give_settings_section'
        );
    }

    // WP registration settings section
    if (oopspamantispam_plugin_check('wp-register') && !empty(oopspamantispam_get_key())) {

        add_settings_section('oopspam_wpregister_settings_section',
            __('WordPress Registration', 'oopspam'),
            false,
            'oopspamantispam-wpregister-settings-group'
        );
        add_settings_field('oopspam_is_wpregister_activated',
            __('Activate Spam Protection', 'oopspam'),
            'oopspam_is_wpregister_activated_render',
            'oopspamantispam-wpregister-settings-group',
            'oopspam_wpregister_settings_section'
        );

        add_settings_field('oopspam_wpregister_spam_message',
            __('WP Registration Forms Spam Message', 'oopspam'),
            'oopspam_wpregister_spam_message_render',
            'oopspamantispam-wpregister-settings-group',
            'oopspam_wpregister_settings_section'
        );
    }


    // Ultimate Member settings section 
    if (oopspamantispam_plugin_check('umember') && !empty(oopspamantispam_get_key())) {

        add_settings_section('oopspam_umember_settings_section',
            __('Ultimate Member Form', 'oopspam'),
            false,
            'oopspamantispam-umember-settings-group'
        );
        add_settings_field('oopspam_is_umember_activated',
            __('Activate Spam Protection', 'oopspam'),
            'oopspam_is_umember_activated_render',
            'oopspamantispam-umember-settings-group',
            'oopspam_umember_settings_section'
        );

        add_settings_field('oopspam_umember_spam_message',
            __('Ultimate Member Spam Message', 'oopspam'),
            'oopspam_umember_spam_message_render',
            'oopspamantispam-umember-settings-group',
            'oopspam_umember_settings_section'
        );
    }

     // Pro Membership Pro settings section 
     if (oopspamantispam_plugin_check('pmp') && !empty(oopspamantispam_get_key())) {

        add_settings_section('oopspam_pmp_settings_section',
            __('Paid Memberships Pro', 'oopspam'),
            false,
            'oopspamantispam-pmp-settings-group'
        );
        add_settings_field('oopspam_is_pmp_activated',
            __('Activate Spam Protection', 'oopspam'),
            'oopspam_is_pmp_activated_render',
            'oopspamantispam-pmp-settings-group',
            'oopspam_pmp_settings_section'
        );

        add_settings_field('oopspam_pmp_spam_message',
            __('Paid Memberships Pro Spam Message', 'oopspam'),
            'oopspam_pmp_spam_message_render',
            'oopspamantispam-pmp-settings-group',
            'oopspam_pmp_settings_section'
        );
    }

    // Member Press settings section 
    if (oopspamantispam_plugin_check('mpress') && !empty(oopspamantispam_get_key())) {

        add_settings_section('oopspam_mpress_settings_section',
            __('MemberPress', 'oopspam'),
            false,
            'oopspamantispam-mpress-settings-group'
        );
        add_settings_field('oopspam_is_mpress_activated',
            __('Activate Spam Protection', 'oopspam'),
            'oopspam_is_mpress_activated_render',
            'oopspamantispam-mpress-settings-group',
            'oopspam_mpress_settings_section'
        );

        add_settings_field('oopspam_mpress_spam_message',
            __('MemberPress Spam Message', 'oopspam'),
            'oopspam_mpress_spam_message_render',
            'oopspamantispam-mpress-settings-group',
            'oopspam_mpress_settings_section'
        );

        add_settings_field('oopspam_mpress_exclude_form',
        __("Don't protect these forms", 'oopspam'),
        'oopspam_mpress_exclude_form_render',
        'oopspamantispam-mpress-settings-group',
        'oopspam_mpress_settings_section'
        );
    }

    // WooCommerce settings section
    if (oopspamantispam_plugin_check('woo') && !empty(oopspamantispam_get_key())) {

        add_settings_section('oopspam_woo_settings_section',
            __('WooCommerce', 'oopspam'),
            false,
            'oopspamantispam-woo-settings-group'
        );
        add_settings_field('oopspam_is_woo_activated',
            __('Activate Spam Protection', 'oopspam'),
            'oopspam_is_woo_activated_render',
            'oopspamantispam-woo-settings-group',
            'oopspam_woo_settings_section'
        );

        add_settings_field('oopspam_woo_spam_message',
            __('WooCommerce Spam Order & Registration Message', 'oopspam'),
            'oopspam_woo_spam_message_render',
            'oopspamantispam-woo-settings-group',
            'oopspam_woo_settings_section'
        );

        add_settings_field('oopspam_woo_check_origin',
            __('Block orders from unknown origin', 'oopspam'),
            'oopspam_woo_check_origin_render',
            'oopspamantispam-woo-settings-group',
            'oopspam_woo_settings_section'
        );

        add_settings_field(
            'oopspam_woo_payment_methods',
            __('Payment methods to check origin', 'oopspam'),
            'oopspam_woo_payment_methods_render',
            'oopspamantispam-woo-settings-group',
            'oopspam_woo_settings_section'
        );

        add_settings_field(
            'oopspam_woo_min_session_pages',
            __('Minimum session page views', 'oopspam'),
            'oopspam_woo_min_session_pages_render',
            'oopspamantispam-woo-settings-group',
            'oopspam_woo_settings_section'
        );

        add_settings_field(
            'oopspam_woo_require_device_type',
            __('Require valid device type', 'oopspam'),
            'oopspam_woo_require_device_type_render',
            'oopspamantispam-woo-settings-group',
            'oopspam_woo_settings_section'
        );

        add_settings_field(
            'oopspam_woo_check_honeypot',
            __('Enable honeypot protection', 'oopspam'),
            'oopspam_woo_check_honeypot_render',
            'oopspamantispam-woo-settings-group',
            'oopspam_woo_settings_section'
        );
        
        add_settings_field(
            'oopspam_woo_disable_rest_checkout',
            __('Disable WooCommerce checkout via REST API', 'oopspam') . 
            '<span class="oopspam-tooltip"><span class="dashicons dashicons-info-outline"></span><span class="tooltip-text">' . 
            __('Do not use if you have third-party integrations (Amazon, etc). Blocks REST API checkout endpoints to prevent automated spam orders.', 'oopspam') . 
            '</span></span>',
            'oopspam_woo_disable_rest_checkout_render',
            'oopspamantispam-woo-settings-group',
            'oopspam_woo_settings_section'
        );
    }

    // SureCart settings section
    if (oopspamantispam_plugin_check('surecart') && !empty(oopspamantispam_get_key())) {

        add_settings_section('oopspam_surecart_settings_section',
            __('SureCart', 'oopspam'),
            false,
            'oopspamantispam-surecart-settings-group'
        );
        add_settings_field('oopspam_is_surecart_activated',
            __('Activate Spam Protection', 'oopspam'),
            'oopspam_is_surecart_activated_render',
            'oopspamantispam-surecart-settings-group',
            'oopspam_surecart_settings_section'
        );

        add_settings_field('oopspam_surecart_spam_message',
            __('SureCart Spam Message', 'oopspam'),
            'oopspam_surecart_spam_message_render',
            'oopspamantispam-surecart-settings-group',
            'oopspam_surecart_settings_section'
        );

    }

    // SureForms settings section
    if (oopspamantispam_plugin_check('sure') && !empty(oopspamantispam_get_key())) {

        add_settings_section('oopspam_sure_settings_section',
            __('SureForms', 'oopspam'),
            false,
            'oopspamantispam-sure-settings-group'
        );
        add_settings_field('oopspam_is_sure_activated',
            __('Activate Spam Protection', 'oopspam'),
            'oopspam_is_sure_activated_render',
            'oopspamantispam-sure-settings-group',
            'oopspam_sure_settings_section'
        );

        add_settings_field('oopspam_sure_spam_message',
            __('SureForms Spam Message', 'oopspam'),
            'oopspam_sure_spam_message_render',
            'oopspamantispam-sure-settings-group',
            'oopspam_sure_settings_section'
        );

        add_settings_field('oopspam_sure_content_field',
            __('The main content field ID (optional)', 'oopspam'),
            'oopspam_sure_content_field_render',
            'oopspamantispam-sure-settings-group',
            'oopspam_sure_settings_section'
        );

        add_settings_field('oopspam_sure_exclude_form',
            __("Don't protect these forms", 'oopspam'),
            'oopspam_sure_exclude_form_render',
            'oopspamantispam-sure-settings-group',
            'oopspam_sure_settings_section'
        );

    }

       // Quform settings section
    if (oopspamantispam_plugin_check('quform') && !empty(oopspamantispam_get_key())) {

        add_settings_section('oopspam_quform_settings_section',
            __('Quform', 'oopspam'),
            false,
            'oopspamantispam-quform-settings-group'
        );
        add_settings_field('oopspam_is_quform_activated',
            __('Activate Spam Protection', 'oopspam'),
            'oopspam_is_quform_activated_render',
            'oopspamantispam-quform-settings-group',
            'oopspam_quform_settings_section'
        );

        add_settings_field('oopspam_quform_spam_message',
            __('Quform Spam Message', 'oopspam'),
            'oopspam_quform_spam_message_render',
            'oopspamantispam-quform-settings-group',
            'oopspam_quform_settings_section'
        );

        add_settings_field('oopspam_quform_content_field',
            __('The main content field Unique ID (optional)', 'oopspam'),
            'oopspam_quform_content_field_render',
            'oopspamantispam-quform-settings-group',
            'oopspam_quform_settings_section'
        );

        add_settings_field('oopspam_quform_exclude_form',
            __("Don't protect these forms", 'oopspam'),
            'oopspam_quform_exclude_form_render',
            'oopspamantispam-quform-settings-group',
            'oopspam_quform_settings_section'
        );

    }

    // Add Contextual Detection settings section
    register_setting(
        'oopspamantispam-contextai-settings-group',
        'oopspamantispam_contextai_settings'
    );

    add_settings_section(
        'oopspam_contextai_section',
        __('Contextual Detection Settings', 'oopspam'),
        'render_contextai_section_info',
        'oopspamantispam-contextai-settings-group'
    );

    add_settings_field(
        'oopspam_is_contextai_enabled',
        __('Enable Contextual Detection', 'oopspam'),
        'oopspam_is_contextai_enabled_render',
        'oopspamantispam-contextai-settings-group',
        'oopspam_contextai_section'
    );

    add_settings_field(
        'oopspam_website_context',
        __('Website Context', 'oopspam'),
        'oopspam_website_context_render',
        'oopspamantispam-contextai-settings-group',
        'oopspam_contextai_section'
    );

}

function render_contextai_section_info() {
    echo '<p>Enable Contextual Detection to improve spam accuracy by analyzing form submissions based on your website’s purpose.</p>';
    echo '<p><strong>Use this feature ONLY if your forms include a required textarea field. It relies on message content to function properly.</strong></p>';
    echo '<p>When enabled, standard spam detection will be disabled. Only Contextual Detection will be used.</p>';    
}

function oopspam_is_contextai_enabled_render() {
    $options = get_option('oopspamantispam_contextai_settings');
    ?>
    <div>
        <label for="contextai_enabled">
            <input type="checkbox"  class="oopspam-toggle"
                   id="contextai_enabled" 
                   name="oopspamantispam_contextai_settings[oopspam_is_contextai_enabled]" 
                   <?php checked(!isset($options['oopspam_is_contextai_enabled']), false, true); ?>/>
        </label>
    </div>
    <?php
}

function oopspam_website_context_render() {
    $options = get_option('oopspamantispam_contextai_settings');
    $context = isset($options['oopspam_website_context']) ? $options['oopspam_website_context'] : '';
    ?>
    <div>
        <textarea 
            name="oopspamantispam_contextai_settings[oopspam_website_context]" 
            id="website_context"
            class="large-text"
            rows="3"
            maxlength="500"
            placeholder="Example: We sell handmade wooden furniture for homes and offices. Our products include chairs, tables, and custom-made pieces crafted by local artisans."
        ><?php echo esc_textarea($context); ?></textarea>
        <p class="description">
            <?php echo __('Briefly describe your website\'s purpose or business (2-3 sentences maximum).', 'oopspam'); ?>
        </p>
    </div>
    <?php
}

function oopspam_api_key_render()
{
    $options = get_option('oopspamantispam_settings');
    $api_key = defined('OOPSPAM_API_KEY') ? OOPSPAM_API_KEY : (isset($options['oopspam_api_key']) ? $options['oopspam_api_key'] : '');
    $is_constant = defined('OOPSPAM_API_KEY');
    ?>
        <div class="api_key_section">
            <label for="oopspam_api_key">
                <input id="oopspam_api_key" type="password" name="oopspamantispam_settings[oopspam_api_key]" class="regular-text" value="<?php echo esc_attr($api_key); ?>" <?php echo $is_constant ? 'disabled' : ''; ?> />
                <button class="button button-secondary" type="button" id="toggleApiKey" style="margin-left: 5px;">Show</button>
                <?php if ($is_constant): ?>
                    <p class="description"><?php echo __('API key is defined in wp-config.php'); ?></p>
                <?php endif; ?>
            </label>
        </div>
        <script>
            document.getElementById('toggleApiKey').addEventListener('click', function () {
                var apiKeyField = document.getElementById('oopspam_api_key');
                if (apiKeyField.type === 'password') {
                    apiKeyField.type = 'text';
                    this.textContent = 'Hide';
                } else {
                    apiKeyField.type = 'password';
                    this.textContent = 'Show';
                }
            });
        </script>
    <?php
}


function oopspam_spam_score_threshold_render()
{
    $options = get_option('oopspamantispam_settings');
    $currentThreshold = (isset($options['oopspam_spam_score_threshold'])) ? (int) $options['oopspam_spam_score_threshold'] : 3;
    // Mapping of threshold levels to words
    $thresholdDescriptions = [
        1 => 'Extremely strict',
        2 => 'Very strict',
        3 => 'Moderate (recommended)',
        4 => 'Slightly lenient',
        5 => 'Lenient',
        6 => 'Very lenient'
    ];
    // Get the corresponding description for the current threshold
    $currentDescription = isset($thresholdDescriptions[$currentThreshold]) ? $thresholdDescriptions[$currentThreshold] : $thresholdDescriptions[3];
    ?>
    <div class="spam_score_threshold_section">
        <label for="oopspam_spam_score_threshold">
            <p style="display: flex;
  justify-content: flex-start;
  align-items: center;">
                <input type="range" id="oopspam_spam_score_threshold" 
                    oninput="updateRangeText(this); updateRangeColor(this);" 
                    min="1" max="6" 
                    name="oopspamantispam_settings[oopspam_spam_score_threshold]" 
                    class="regular-text range-input" value="<?php echo $currentThreshold ?>" />
                <output style="padding-left: 10px;" id="range_text"><?php echo $currentDescription; ?></output>
            </p>
            <p class="description">
                <?php echo __('Adjust the spam detection sensitivity with this setting. For optimal results, we recommend selecting "Moderate (recommended).', 'oopspam'); ?>
            </p>
        </label>
    </div>
    <style>
        .range-input {
            background: linear-gradient(to right, rgba(255, 0, 0, 0.58) 0%, rgb(56, 239, 93) 100%);
            -webkit-appearance: none;
            height: 20px;
            border-radius: 10px;
        }

        .range-input::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            background: #fff;
            border-radius: 50%;
            cursor: pointer;
        }

        .range-input::-moz-range-thumb {
            width: 20px;
            height: 20px;
            background: #fff;
            border-radius: 50%;
            cursor: pointer;
        }
    </style>
    <script>
        // Mapping for live update of range text description
        const thresholdDescriptions = {
            1: 'Extremely strict',
            2: 'Very strict',
            3: 'Moderate (recommended)',
            4: 'Slightly lenient',
            5: 'Lenient',
            6: 'Very lenient'
        };

        function updateRangeText(rangeInput) {
            var rangeTextOutput = document.getElementById('range_text');
            rangeTextOutput.value = thresholdDescriptions[rangeInput.value];

            // Send AJAX request to update cloud providers setting
            var value = parseInt(rangeInput.value);
            var enableCloudProviders = value >= 4;
            
            jQuery.post(ajaxurl, {
                action: 'update_cloud_providers_setting',
                nonce: '<?php echo wp_create_nonce("oopspam_update_cloud_providers"); ?>',
                enable: enableCloudProviders
            });
        }

        function updateRangeColor(rangeInput) {
            var rangeElement = document.getElementById('oopspam_spam_score_threshold');
            switch (parseInt(rangeInput.value)) {
                case 1:
                    rangeElement.style.background = 'linear-gradient(to right, red 0%, red 100%)';
                    break;
                case 2:
                    rangeElement.style.background = 'linear-gradient(to right, red 0%, rgba(255, 0, 0, 0.58) 100%)';
                    break;
                case 3:
                    rangeElement.style.background = 'linear-gradient(to right, rgba(255, 0, 0, 0.58) 0%, rgb(56, 239, 93) 100%)';
                    break;
                case 4:
                    rangeElement.style.background = 'linear-gradient(to right, rgba(0, 128, 0, 0.54) 0%, rgba(0, 128, 0, 0.76) 100%)';
                    break;
                case 5:
                     rangeElement.style.background = 'linear-gradient(to right, rgba(0, 128, 0, 0.76) 0%, rgb(0, 128, 0) 100%)';
                    break;
                case 6:
                    rangeElement.style.background = 'linear-gradient(to right, green 0%, green 100%)';
                    break;
            }
        }
    </script>
    <?php
}




function oopspam_clear_spam_entries_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                <label for="biweekly">  <input type="radio" name="oopspamantispam_settings[oopspam_clear_spam_entries]" id="biweekly" value="biweekly"  <?php checked("biweekly", isset($options["oopspam_clear_spam_entries"]) ? $options["oopspam_clear_spam_entries"] : false, true);?> />Two weeks  </label>
                <label for="month"> <input type="radio" name="oopspamantispam_settings[oopspam_clear_spam_entries]" id="month" value="monthly"   <?php checked("monthly", isset($options["oopspam_clear_spam_entries"]) ? $options["oopspam_clear_spam_entries"] : false, true);?> />Month</label>
            </div>
        <?php
}

function oopspam_clear_ham_entries_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                <label for="h-biweekly">  <input type="radio" name="oopspamantispam_settings[oopspam_clear_ham_entries]" id="h-biweekly" value="biweekly"  <?php checked("biweekly", isset($options["oopspam_clear_ham_entries"]) ? $options["oopspam_clear_ham_entries"] : false, true);?> />Two weeks  </label>
                <label for="h-month"> <input type="radio" name="oopspamantispam_settings[oopspam_clear_ham_entries]" id="h-month" value="monthly"   <?php checked("monthly", isset($options["oopspam_clear_ham_entries"]) ? $options["oopspam_clear_ham_entries"] : false, true);?> />Month</label>
                </>
            </div>
        <?php
}

function oopspam_spam_movedspam_to_folder_render()
{
    $options = get_option('oopspamantispam_settings');
    $currentFolder = (isset($options['oopspam_spam_movedspam_to_folder'])) ? $options['oopspam_spam_movedspam_to_folder'] : "spam";
    ?>
        <div class="oopspam_spam_movedspam_to_folder_section">
            <label for="move-spam-to-folder">
                <p>
                    <?php
$items = array("spam", "trash");
    echo "<select id='move-spam-to-folder' name='oopspamantispam_settings[oopspam_spam_movedspam_to_folder]'>";
    foreach ($items as $item) {
        $selected = ($currentFolder == $item) ? 'selected="selected"' : '';
        echo "<option value='$item' $selected>$item</option>";
    }
    echo "</select>";
    ?>
                </p>
        </label>
    </div>
        <?php
}


function oopspam_is_check_for_length_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                <label for="short_text_support">
                <input class="oopspam-toggle" type="checkbox" id="short_text_support" name="oopspamantispam_settings[oopspam_is_check_for_length]"  <?php checked(!isset($options['oopspam_is_check_for_length']), false, true);?>/>
                <p class="description"><?php echo __('<strong>Important: </strong> Messages that are less than 20 characters in length, including blank messages, will be considered spam. Uncheck this setting if you have an optional (not required) message field in your forms.', 'oopspam'); ?></p>

                </label>
            </div>
        <?php
}

function oopspam_block_temp_email_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                <label for="block_temp_email">
                <input class="oopspam-toggle" type="checkbox" id="block_temp_email" name="oopspamantispam_settings[oopspam_block_temp_email]"  <?php checked(!isset($options['oopspam_block_temp_email']), false, true);?>/>
                </label>
            </div>
        <?php
}

function oopspam_is_rt_enabled_render()
{
    $rtOptions = get_option('oopspamantispam_ratelimit_settings');
    ?>
            <div>
                <label for="rt_enabled">
                <input 
                    class="oopspam-toggle" 
                    type="checkbox" 
                    id="rt_enabled" 
                    name="oopspamantispam_ratelimit_settings[oopspam_is_rt_enabled]"
                    value="1"
                    <?php checked(!isset($rtOptions['oopspam_is_rt_enabled']), false, true);?> /> 
                </label>
            </div>
        <?php
}

function oopspam_is_loggable_render()
{
    $options = get_option('oopspamantispam_settings');
    $is_constant = defined('OOPSPAM_ENABLE_REMOTE_LOGGING');
    ?>
    <div>
        <label for="loggable">
            <input class="oopspam-toggle" type="checkbox" id="loggable" 
                   name="oopspamantispam_settings[oopspam_is_loggable]"
                   <?php checked(!isset($options['oopspam_is_loggable']), false, true); ?>
                   <?php echo $is_constant ? 'disabled' : ''; ?>/>
            <p class="description"><?php echo __('Allows you to view logs in the OOPSpam Dashboard', 'oopspam'); ?></p>
            <?php if ($is_constant): ?>
                <p class="description"><?php echo __('This setting is defined in wp-config.php'); ?></p>
            <?php endif; ?>
        </label>
    </div>
    <?php
}

function oopspam_disable_local_logging_render() {
    $options = get_option('oopspamantispam_settings');
    $is_constant = defined('OOPSPAM_DISABLE_LOCAL_LOGGING');
    ?>
            <div>
                <label for="local-loggable">
                <input class="oopspam-toggle" type="checkbox" id="local-loggable" 
                       name="oopspamantispam_settings[oopspam_disable_local_logging]"  
                       <?php checked(!isset($options['oopspam_disable_local_logging']), false, true); ?>
                       <?php echo $is_constant ? 'disabled' : ''; ?>/>
                <p class="description"><?php echo __('Disables storing submissions in the Form Spam Entries and Form Valid Entries tables.', 'oopspam'); ?></p>
                <?php if ($is_constant): ?>
                    <p class="description"><?php echo __('This setting is defined in wp-config.php'); ?></p>
                <?php endif; ?>
                </label>
            </div>
        <?php
}

function oopspam_is_urls_allowed_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                <label for="block_urls">
                <input class="oopspam-toggle" type="checkbox" id="block_urls" name="oopspamantispam_settings[oopspam_is_urls_allowed]"  <?php checked(!isset($options['oopspam_is_urls_allowed']), false, true);?>/>
                </label>
            </div>
        <?php
}


// display custom admin notice
function oopspam_custom_admin_notice()
{
    $options = get_option('oopspamantispam_settings');
    if (get_option('over_rate_limit')) {
        ?>
            <div class="notice notice-error is-dismissible">
            <h4>OOPSpam Anti-Spam</h4>
            <p><?php _e('Your API key exceeded your current plan\'s limit. The spam filtering functionality is disabled. Please upgrade to enable spam protection.', 'oopspam');?> </p>
            <p>
                   <?php _e("For the API key obtained through OOPSpam Dashboard visit:", 'oopspam');?> <a href="https://app.oopspam.com/" target="_blank">https://app.oopspam.com</a>.
                   </p>
                   <p>
                   <strong>
                   <?php _e("Note: This warning may appear immediately after entering your API key. It will be automatically dismissed once your website receives its first submission.", 'oopspam');?> 
    </strong> </p>

		<p><?php _e('For any questions email us: <a href="mailto:contact@oopspam.com">contact@oopspam.com</a>', 'oopspam');?></p>
            </div>
            <?php
}
    ?>

    <?php }

add_action('admin_notices', 'oopspam_custom_admin_notice');

function oopspam_api_key_usage_render() {
    $options = get_option('oopspamantispam_settings');
    $usage = isset($options['oopspam_api_key_usage']) ? $options['oopspam_api_key_usage'] : "0/0";
    
    // Parse usage values
    list($remaining, $limit) = array_map('intval', explode('/', $usage));
    $used = $limit - $remaining;
    $percentage = $limit > 0 ? ($used / $limit) * 100 : 0;
    
    // Determine color based on usage percentage
    $bar_color = '#2271b1'; // Default WordPress blue
    if ($percentage >= 90) {
        $bar_color = '#d63638'; // Red for high usage
    } else if ($percentage >= 70) {
        $bar_color = '#dba617'; // Yellow for moderate usage
    }
    ?>
    <div class="oopspam-usage-stats">
        <div class="oopspam-usage-numbers">
            <span class="usage-label"><?php _e('API Calls Available:', 'oopspam'); ?></span>
            <span class="usage-value"><?php echo number_format($remaining); ?> / <?php echo number_format($limit); ?></span>
        </div>
        
        <div class="oopspam-usage-bar-container">
            <div class="oopspam-usage-bar" style="width: <?php echo esc_attr($percentage); ?>%; background-color: <?php echo esc_attr($bar_color); ?>"></div>
        </div>
        
        <p class="oopspam-usage-note description">
            <?php echo __('Usage updates automatically with new submissions. Changes to your plan limit will be reflected after the next submission.', 'oopspam'); ?>
        </p>

        <style>
            .oopspam-usage-stats {
                margin: 15px 0;
                max-width: 500px;
            }
            .oopspam-usage-numbers {
                margin-bottom: 8px;
                font-size: 14px;
            }
            .usage-label {
                font-weight: 500;
                margin-right: 8px;
            }
            .usage-value {
                font-family: Consolas, Monaco, monospace;
            }
            .oopspam-usage-bar-container {
                height: 8px;
                background: #f0f0f1;
                border-radius: 4px;
                overflow: hidden;
            }
            .oopspam-usage-bar {
                height: 100%;
                transition: width 0.3s ease;
                border-radius: 4px;
            }
            .oopspam-usage-note {
                margin-top: 8px;
                font-style: italic;
            }
        </style>
    </div>
    <?php
}

function oopspam_is_check_for_ip_render()
{
    $privacyOptions = get_option('oopspamantispam_privacy_settings');
    ?>
            <div>
                <label for="ip_check_support">
                <input class="oopspam-toggle" type="checkbox" id="ip_check_support" name="oopspamantispam_privacy_settings[oopspam_is_check_for_ip]"  <?php checked(!isset($privacyOptions['oopspam_is_check_for_ip']), false, true);?>/>
                <p class="description"><?php echo __('Turning on this setting may weaken the spam protection', 'oopspam'); ?></p>

                </label>
            </div>
        <?php
}

function oopspam_anonym_content_render()
{
    $privacyOptions = get_option('oopspamantispam_privacy_settings');
    ?>
            <div>
                <label for="anonym_content_support">
                <input class="oopspam-toggle" type="checkbox" id="anonym_content_support" name="oopspamantispam_privacy_settings[oopspam_anonym_content]"  <?php checked(!isset($privacyOptions['oopspam_anonym_content']), false ,true);?>/>
                <p class="description"><?php echo __('Before sending a message to OOPSpam for spam detection, try to remove Emails, Addresses, Phone Numbers.
It should be noted, however, that there is no guarantee that these data points will be accurately removed. Turning on this setting may weaken the spam protection', 'oopspam'); ?></p>

                </label>
            </div>
        <?php
}

function oopspam_is_check_for_email_render() {
    $privacyOptions = get_option('oopspamantispam_privacy_settings');
    ?>
            <div>
                <label for="email_check_support">
                <input class="oopspam-toggle" type="checkbox" id="email_check_support" name="oopspamantispam_privacy_settings[oopspam_is_check_for_email]"  <?php checked(!isset($privacyOptions['oopspam_is_check_for_email']), false, true);?>/>
                <p class="description"><?php echo __('Turning on this setting may weaken the spam protection', 'oopspam'); ?></p>

                </label>
            </div>
        <?php
}

function oopspam_is_search_protection_on_render() {
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                <label for="search_check_support">
                <input class="oopspam-toggle" type="checkbox" id="search_check_support" name="oopspamantispam_settings[oopspam_is_search_protection_on]"  <?php checked(!isset($options['oopspam_is_search_protection_on']), false, true);?>/>
                </label>
            </div>
        <?php
}

function oopspam_country_always_allow_render()
{
    $countryAlwaysAllowSetting = get_option('oopspam_country_always_allow');
    $countrylist = oopspam_get_isocountries();
    ?>

        <div id="alwaysallowcountry">
        <select class="select" data-placeholder="Choose a country..." name="oopspam_country_always_allow[]" multiple="true" style="width:600px;">
        <optgroup label="(de)select all countries">

            <?php
foreach ($countrylist as $key => $value) {
        print "<option value=\"$key\"";
        if (is_array($countryAlwaysAllowSetting) && in_array($key, $countryAlwaysAllowSetting)) {
            print " selected=\"selected\" ";
        }
        print ">$value</option>\n";
}
            ?>
        </optgroup>
        </select>
        </div>
        <p class="description">
            <?php echo __('Highest priority: Submissions from these countries will always be allowed and bypass all spam checks.', 'oopspam'); ?>
        </p>
<?php
}

function oopspam_countryallowlist_render()
{
    $countryallowlistSetting = get_option('oopspam_countryallowlist');
    $countrylist = oopspam_get_isocountries();
    ?>

        <div id="allowcountry">
        <select class="select" data-placeholder="Choose a country..." name="oopspam_countryallowlist[]" multiple="true" style="width:600px;">
        <optgroup label="(de)select all countries">

            <?php
foreach ($countrylist as $key => $value) {
        print "<option value=\"$key\"";
        if (is_array($countryallowlistSetting) && in_array($key, $countryallowlistSetting)) {
            print " selected=\"selected\" ";
        }
        print ">$value</option>\n";
    }
    echo "</optgroup>";
    echo "                     </select>";

    ?>
        </div>
        <p class="description">
            <?php echo __('When countries are selected here, ONLY submissions from these countries will be processed. Leave empty to accept from all countries not in the blocklist.', 'oopspam'); ?>
        </p>
        <?php

}
function oopspam_admin_emails_render() {
    $adminEmailListSetting = get_option('oopspam_admin_emails', array());
    ?>

    <div id="admin-emails">
        <select id="admin-email-list" data-placeholder="Enter emails..." name="oopspam_admin_emails[]" multiple style="width:500px;">
            <?php
            if (is_array($adminEmailListSetting)) {
                foreach ($adminEmailListSetting as $email) {
                    echo '<option value="' . esc_attr($email) . '" selected="selected">' . esc_html($email) . '</option>';
                }
            }
            ?>
        </select>
    </div>
    <p class="description">
        <?php echo __('Send flagged spam entries to multiple email addresses from the Form Spam Entries table. Leave blank to use the email address set in the General -> Administration Email Address setting.', 'oopspam'); ?>
    </p>

    <?php
}


function oopspam_countryblocklist_render()
{
    $countryblocklistSetting = get_option('oopspam_countryblocklist');
    $countrylist = oopspam_get_isocountries();
    ?>
        <div id="blockcountry">
        <select class="select" data-placeholder="Choose a country..." name="oopspam_countryblocklist[]" multiple="true" style="width:600px;">
        <optgroup label="(de)select all countries">
            <?php
            foreach ($countrylist as $key => $value) {
                print "<option value=\"$key\"";
                if (is_array($countryblocklistSetting) && in_array($key, $countryblocklistSetting)) {
                    print " selected=\"selected\" ";
                }
                print ">$value</option>\n";
            }
            echo "</optgroup>";
            echo "                     </select>";
            ?>
            <div style="padding-top:0.3em;">
                <button id="spam-countries" type="button" class="button button-secondary">Add China and Russia</button>
                <button id="african-countries" type="button" class="button button-secondary">Add countries in Africa</button>
                <button id="eu-countries" type="button" class="button button-secondary">Add countries in the EU</button>
            </div>
        </div>
        <p class="description">
            <?php echo __('Submissions from these countries will be rejected, unless they appear in the Trusted Countries list above.', 'oopspam'); ?>
        </p>
        <?php
}

function oopspam_api_key_source_render()
{

    $options = get_option('oopspamantispam_settings');

    ?>
        <div id="oopspam-api-key-source">
        <input type="radio" name="oopspamantispam_settings[oopspam_api_key_source]" value="RapidAPI" <?php checked("RapidAPI", isset($options["oopspam_api_key_source"]) ? $options["oopspam_api_key_source"] : false, true);?>>RapidAPI
        <input type="radio" name="oopspamantispam_settings[oopspam_api_key_source]" value="OOPSpamDashboard" <?php checked("OOPSpamDashboard", isset($options["oopspam_api_key_source"]) ? $options["oopspam_api_key_source"] : false, true);?>>OOPSpam Dashboard
        </div>
   <?php
}

function oopspam_languageallowlist_render()
{
    $languageallowlistSetting = get_option('oopspam_languageallowlist');
    $languagelist = oopspam_get_isolanguages();
    ?>

        <div>
        <select class="select" data-placeholder="Choose a language..." name="oopspam_languageallowlist[]" multiple="true" style="width:600px;">
        <optgroup label="(de)select all languages">

            <?php
foreach ($languagelist as $key => $value) {
        print "<option value=\"$key\"";
        if (is_array($languageallowlistSetting) && in_array($key, $languageallowlistSetting)) {
            print " selected=\"selected\" ";
        }
        print ">$value</option>\n";
    }
    echo "</optgroup>";
    echo "                     </select>";

    ?>
        </div>
        <p class="description">
            <?php echo __('When languages are selected, only messages in these languages will be accepted. Leave empty to accept all languages.', 'oopspam'); ?>
        </p>
        <?php

}


/* Forminator UI settings section starts */

function oopspam_is_forminator_activated_render() {
    $options = get_option('oopspamantispam_settings');
    $is_constant = defined('OOPSPAM_IS_FORMINATOR_ACTIVATED');
    $is_activated = $is_constant ? OOPSPAM_IS_FORMINATOR_ACTIVATED : (isset($options['oopspam_is_forminator_activated']) && 1 == $options['oopspam_is_forminator_activated']);
    ?>
    <div>
        <label for="forminator_support">
            <input class="oopspam-toggle" type="checkbox" id="forminator_support" 
                   name="oopspamantispam_settings[oopspam_is_forminator_activated]" 
                   value="1" <?php echo $is_activated ? 'checked="checked"' : ''; ?> 
                   <?php echo $is_constant ? 'disabled' : ''; ?>/>
            <?php if ($is_constant): ?>
                <p class="description"><?php echo __('This setting is defined in wp-config.php'); ?></p>
            <?php endif; ?>
        </label>
    </div>
    <?php
}

function oopspam_forminator_spam_message_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                    <label for="oopspam_forminator_spam_message">
                    <input id="oopspam_forminator_spam_message" type="text" class="regular-text" name="oopspamantispam_settings[oopspam_forminator_spam_message]" value="<?php if (isset($options['oopspam_forminator_spam_message'])) {
        esc_html_e($options['oopspam_forminator_spam_message'], "oopspam");
    }
    ?>">
                        <p class="description"><?php echo __('Enter a short message to display when a spam Forminator Form entry has been submitted. (e.g Our spam detection classified your submission as spam. Please contact via name@example.com).', 'oopspam'); ?></p>
                        </label>
                </div>
            <?php
}

function oopspam_forminator_content_field_render() {
    $options = get_option('oopspamantispam_settings');
    $formDataJson = isset($options['oopspam_forminator_content_field']) ? $options['oopspam_forminator_content_field'] : '[]';
    $formData = json_decode($formDataJson, true); // Decode JSON data into PHP array
    ?>
    <div>
        <form id="formData">
            <label for="formIdInput">Form ID:</label>
            <input type="text" id="formIdInput" name="formIdInput" placeholder="167">
            <label for="fieldIdInput">Field ID:</label>
            <input type="text" id="fieldIdInput" name="fieldIdInput" placeholder="2,3,4">
            <button type="button" onclick="addData(this)">Add Pair</button>
        </form>
        <table id="savedFormData">
            <thead>
                <tr>
                    <th>Form ID</th>
                    <th>Field ID</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (is_array($formData)) : ?>
                    <?php foreach ($formData as $key => $entry) : ?>
                        <tr>
                            <td contenteditable="true"><?php echo esc_html($entry['formId']); ?></td>
                            <td contenteditable="true"><?php echo esc_html($entry['fieldId']); ?></td>
                            <td><button type="button" onclick="deleteRow(this)">Delete</button></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <input type="hidden" name="oopspamantispam_settings[oopspam_forminator_content_field]" id="formDataInput" value="<?php echo esc_attr(json_encode($formData)); ?>">
        <p class="description"><?php echo __('Enter the Form ID and Field ID pairs in the table above. If multiple Field IDs are provided for a Form ID, their values will be joined together.', 'oopspam'); ?></p>
    </div>
    <?php
}

/* Forminator UI settings section ends */

/* MC4WP UI settings section starts */

function oopspam_is_mc4wp_activated_render()
{
    $options = get_option('oopspamantispam_settings');
    $is_constant = defined('OOPSPAM_IS_MC4WP_ACTIVATED');
    $is_activated = $is_constant ? OOPSPAM_IS_MC4WP_ACTIVATED : (isset($options['oopspam_is_mc4wp_activated']) && 1 == $options['oopspam_is_mc4wp_activated']);
    ?>
    <div>
        <label for="mc4wp_support">
            <input class="oopspam-toggle" type="checkbox" id="mc4wp_support" 
                   name="oopspamantispam_settings[oopspam_is_mc4wp_activated]" 
                   value="1" <?php echo $is_activated ? 'checked="checked"' : ''; ?> 
                   <?php echo $is_constant ? 'disabled' : ''; ?>/>
            <?php if ($is_constant): ?>
                <p class="description"><?php echo __('This setting is defined in wp-config.php'); ?></p>
            <?php endif; ?>
        </label>
    </div>
    <?php
}

function oopspam_mc4wp_spam_message_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                    <label for="oopspam_mc4wp_spam_message">
                    <input id="oopspam_mc4wp_spam_message" type="text" class="regular-text" name="oopspamantispam_settings[oopspam_mc4wp_spam_message]" value="<?php if (isset($options['oopspam_mc4wp_spam_message'])) {
        esc_html_e($options['oopspam_mc4wp_spam_message'], "oopspam");
    }
    ?>">
                        <p class="description"><?php echo __('Enter a short message to display when a spam email has been submitted via Mailchimp form. (e.g Our spam detection classified your submission as spam. Please contact via name@example.com)', 'oopspam'); ?></p>
                        </label>
                </div>
            <?php
}

/* MC4WP UI settings section ends */

/* MailPoet UI settings section starts */

function oopspam_is_mpoet_activated_render()
{
    $options = get_option('oopspamantispam_settings');
    $is_constant = defined('OOPSPAM_IS_MPOET_ACTIVATED');
    $is_activated = $is_constant ? OOPSPAM_IS_MPOET_ACTIVATED : (isset($options['oopspam_is_mpoet_activated']) && 1 == $options['oopspam_is_mpoet_activated']);
    ?>
    <div>
        <label for="mpoet_support">
            <input class="oopspam-toggle" type="checkbox" id="mpoet_support" 
                   name="oopspamantispam_settings[oopspam_is_mpoet_activated]" 
                   value="1" <?php echo $is_activated ? 'checked="checked"' : ''; ?> 
                   <?php echo $is_constant ? 'disabled' : ''; ?>/>
            <?php if ($is_constant): ?>
                <p class="description"><?php echo __('This setting is defined in wp-config.php'); ?></p>
            <?php endif; ?>
        </label>
    </div>
    <?php
}

function oopspam_mpoet_spam_message_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                    <label for="oopspam_mpoet_spam_message">
                    <input id="oopspam_mpoet_spam_message" type="text" class="regular-text" name="oopspamantispam_settings[oopspam_mpoet_spam_message]" value="<?php if (isset($options['oopspam_mpoet_spam_message'])) {
        esc_html_e($options['oopspam_mpoet_spam_message'], "oopspam");
    }
    ?>">
                        <p class="description"><?php echo __('Enter a short message to display when a spam email has been submitted via MailPoet form. (e.g Our spam detection classified your submission as spam. Please contact via name@example.com)', 'oopspam'); ?></p>
                        </label>
                </div>
            <?php
}

/* MailPoet UI settings section ends */

/* Discuz UI settings section starts */

function oopspam_is_wpdis_activated_render()
{
    $options = get_option('oopspamantispam_settings');
    $is_constant = defined('OOPSPAM_IS_WPDIS_ACTIVATED');
    $is_activated = $is_constant ? OOPSPAM_IS_WPDIS_ACTIVATED : (isset($options['oopspam_is_wpdis_activated']) && 1 == $options['oopspam_is_wpdis_activated']);
    ?>
    <div>
        <label for="wpdis_support">
            <input class="oopspam-toggle" type="checkbox" id="wpdis_support" 
                   name="oopspamantispam_settings[oopspam_is_wpdis_activated]" 
                   value="1" <?php echo $is_activated ? 'checked="checked"' : ''; ?> 
                   <?php echo $is_constant ? 'disabled' : ''; ?>/>
            <?php if ($is_constant): ?>
                <p class="description"><?php echo __('This setting is defined in wp-config.php'); ?></p>
            <?php endif; ?>
        </label>
    </div>
    <?php
}

function oopspam_wpdis_spam_message_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                    <label for="oopspam_wpdis_spam_message">
                    <input id="oopspam_wpdis_spam_message" type="text" class="regular-text" name="oopspamantispam_settings[oopspam_wpdis_spam_message]" value="<?php if (isset($options['oopspam_wpdis_spam_message'])) {
        esc_html_e($options['oopspam_wpdis_spam_message'], "oopspam");
    }
    ?>">
                        <p class="description"><?php echo __('Enter a short message to display when a spam comment entry has been submitted via WPDiscuz comment system. (e.g Our spam detection classified your submission as spam. Please contact via name@example.com)', 'oopspam'); ?></p>
                        </label>
                </div>
            <?php
}

/* Discuz UI settings section ends */

/* Kadence Block Form UI settings section starts */

function oopspam_is_kb_activated_render()
{
    $options = get_option('oopspamantispam_settings');
    $is_constant = defined('OOPSPAM_IS_KB_ACTIVATED');
    $is_activated = $is_constant ? OOPSPAM_IS_KB_ACTIVATED : (isset($options['oopspam_is_kb_activated']) && 1 == $options['oopspam_is_kb_activated']);
    ?>
    <div>
        <label for="kb_support">
            <input class="oopspam-toggle" type="checkbox" id="kb_support" 
                   name="oopspamantispam_settings[oopspam_is_kb_activated]" 
                   value="1" <?php echo $is_activated ? 'checked="checked"' : ''; ?> 
                   <?php echo $is_constant ? 'disabled' : ''; ?>/>
            <?php if ($is_constant): ?>
                <p class="description"><?php echo __('This setting is defined in wp-config.php'); ?></p>
            <?php endif; ?>
        </label>
    </div>
    <?php
}

function oopspam_kb_spam_message_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                    <label for="oopspam_kb_spam_message">
                    <input id="oopspam_kb_spam_message" type="text" class="regular-text" name="oopspamantispam_settings[oopspam_kb_spam_message]" value="<?php if (isset($options['oopspam_kb_spam_message'])) {
        esc_html_e($options['oopspam_kb_spam_message'], "oopspam");
    }
    ?>">
                        <p class="description"><?php echo __('Enter a short message to display when a spam Kadence Form entry has been submitted. (e.g Our spam detection classified your submission as spam. Please contact via name@example.com)', 'oopspam'); ?></p>
                        </label>
                </div>
            <?php
}

/* Kadence Block Form settings section ends */

/* Ninja Forms UI settings section starts */

function oopspam_is_nj_activated_render()
{
    $options = get_option('oopspamantispam_settings');
    $is_constant = defined('OOPSPAM_IS_NJ_ACTIVATED');
    $is_activated = $is_constant ? OOPSPAM_IS_NJ_ACTIVATED : (isset($options['oopspam_is_nj_activated']) && 1 == $options['oopspam_is_nj_activated']);
    ?>
    <div>
        <label for="nf_support">
            <input class="oopspam-toggle" type="checkbox" id="nf_support" 
                   name="oopspamantispam_settings[oopspam_is_nj_activated]" 
                   value="1" <?php echo $is_activated ? 'checked="checked"' : ''; ?> 
                   <?php echo $is_constant ? 'disabled' : ''; ?>/>
            <?php if ($is_constant): ?>
                <p class="description"><?php echo __('This setting is defined in wp-config.php'); ?></p>
            <?php endif; ?>
        </label>
    </div>
    <?php
}

function oopspam_nj_spam_message_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                    <label for="oopspam_nj_spam_message">
                    <input id="oopspam_nj_spam_message" type="text" class="regular-text" name="oopspamantispam_settings[oopspam_nj_spam_message]" value="<?php if (isset($options['oopspam_nj_spam_message'])) {
        esc_html_e($options['oopspam_nj_spam_message'], "oopspam");
    }
    ?>">
                        <p class="description"><?php echo __('Enter a short message to display when a spam Ninja Forms entry has been submitted. (e.g Our spam detection classified your submission as spam. Please contact via name@example.com)', 'oopspam'); ?></p>
                        </label>
                </div>
            <?php
}

function oopspam_nj_content_field_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                    <label for="oopspam_nj_content_field">
                    <input id="oopspam_nj_content_field" type="text" class="regular-text" name="oopspamantispam_settings[oopspam_nj_content_field]" value="<?php if (isset($options['oopspam_nj_content_field'])) {
        echo esc_html($options['oopspam_nj_content_field']);
    }
    ?>">
                        <p class="description"><?php echo __('By default, OOPSpam looks for a textarea field in your Ninja Forms. If you have multiple textarea fields, specify the main content/message FIELD KEY here.', 'oopspam'); ?></p>
                        <p class="description"><?php echo __('Have multiple forms? Enter the message field keys separated by commas.', 'oopspam'); ?></p>
                        </label>
                </div>
            <?php
}

function oopspam_nj_exclude_form_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                    <label for="oopspam_nj_exclude_form">
                    <input id="oopspam_nj_exclude_form" type="text" placeholder="Enter form IDs (e.g 1,5,2 or 5)" class="regular-text" name="oopspamantispam_settings[oopspam_nj_exclude_form]" value="<?php if (isset($options['oopspam_nj_exclude_form'])) {
        echo esc_html($options['oopspam_nj_exclude_form']);
    }
    ?>">
                        </label>
                </div>
            <?php
}

/* Ninja Forms UI settings section ends */


/* Piotnet Forms UI settings section starts */

function oopspam_is_pionet_activated_render()
{
    $options = get_option('oopspamantispam_settings');
    $is_constant = defined('OOPSPAM_IS_PIONET_ACTIVATED');
    $is_activated = $is_constant ? OOPSPAM_IS_PIONET_ACTIVATED : (isset($options['oopspam_is_pionet_activated']) && 1 == $options['oopspam_is_pionet_activated']);
    ?>
    <div>
        <label for="pionet_support">
            <input class="oopspam-toggle" type="checkbox" id="pionet_support" 
                   name="oopspamantispam_settings[oopspam_is_pionet_activated]" 
                   value="1" <?php echo $is_activated ? 'checked="checked"' : ''; ?> 
                   <?php echo $is_constant ? 'disabled' : ''; ?>/>
            <?php if ($is_constant): ?>
                <p class="description"><?php echo __('This setting is defined in wp-config.php'); ?></p>
            <?php endif; ?>
        </label>
    </div>
    <?php
}

function oopspam_pionet_spam_message_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                    <label for="oopspam_pionet_spam_message">
                    <input id="oopspam_pionet_spam_message" type="text" class="regular-text" name="oopspamantispam_settings[oopspam_pionet_spam_message]" value="<?php if (isset($options['oopspam_pionet_spam_message'])) {
        esc_html_e($options['oopspam_pionet_spam_message'], "oopspam");
    }
    ?>">
                        <p class="description"><?php echo __('Enter a short message to display when a spam Pionet Forms entry has been submitted. (e.g Our spam detection classified your submission as spam. Please contact via name@example.com)', 'oopspam'); ?></p>
                        </label>
                </div>
            <?php
}

function oopspam_pionet_content_field_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                    <label for="oopspam_pionet_content_field">
                    <input type="text" class="regular-text" name="oopspamantispam_settings[oopspam_pionet_content_field]" value="<?php if (isset($options['oopspam_pionet_content_field'])) {
        echo esc_html($options['oopspam_pionet_content_field']);
    }
    ?>">
                        <p class="description"><?php echo __('By default, OOPSpam looks for a textarea field in your Pionet Forms. If you have multiple textarea fields, specify the main content/message Field ID here.', 'oopspam'); ?></p>
                        <p class="description"><?php echo __('Have multiple forms? Enter the message field ids separated by commas.', 'oopspam'); ?></p>
                        </label>
                </div>
            <?php
}

function oopspam_pionet_exclude_form_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                    <label for="oopspam_pionet_exclude_form">
                    <input type="text" placeholder="Enter form IDs (e.g 1,5,2 or 5)" class="regular-text" name="oopspamantispam_settings[oopspam_pionet_exclude_form]" value="<?php if (isset($options['oopspam_pionet_exclude_form'])) {
        echo esc_html($options['oopspam_pionet_exclude_form']);
    }
    ?>">
                </div>
            <?php
}

/* Piotnet Forms UI settings section ends */

/* Toolset Forms UI settings section starts */

function oopspam_is_ts_activated_render()
{
    $options = get_option('oopspamantispam_settings');
    $is_constant = defined('OOPSPAM_IS_TS_ACTIVATED');
    $is_activated = $is_constant ? OOPSPAM_IS_TS_ACTIVATED : (isset($options['oopspam_is_ts_activated']) && 1 == $options['oopspam_is_ts_activated']);
    ?>
    <div>
        <label for="ts_support">
            <input class="oopspam-toggle" type="checkbox" id="ts_support" 
                   name="oopspamantispam_settings[oopspam_is_ts_activated]" 
                   value="1" <?php echo $is_activated ? 'checked="checked"' : ''; ?> 
                   <?php echo $is_constant ? 'disabled' : ''; ?>/>
            <?php if ($is_constant): ?>
                <p class="description"><?php echo __('This setting is defined in wp-config.php'); ?></p>
            <?php endif; ?>
        </label>
    </div>
    <?php
}

function oopspam_ts_spam_message_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                    <label for="oopspam_ts_spam_message">
                    <input id="oopspam_ts_spam_message" type="text" class="regular-text" name="oopspamantispam_settings[oopspam_ts_spam_message]" value="<?php if (isset($options['oopspam_ts_spam_message'])) {
        esc_html_e($options['oopspam_ts_spam_message'], "oopspam");
    }
    ?>">
                        <p class="description"><?php echo __('Enter a short message to display when a spam Toolset Forms entry has been submitted. (e.g Our spam detection classified your submission as spam. Please contact via name@example.com)', 'oopspam'); ?></p>
                        </label>
                </div>
            <?php
}

/* Toolset Forms UI settings section ends */

/* Formidable Forms UI settings section starts */

function oopspam_is_fable_activated_render()
{
    $options = get_option('oopspamantispam_settings');
    $is_constant = defined('OOPSPAM_IS_FABLE_ACTIVATED');
    $is_activated = $is_constant ? OOPSPAM_IS_FABLE_ACTIVATED : (isset($options['oopspam_is_fable_activated']) && 1 == $options['oopspam_is_fable_activated']);
    ?>
    <div>
        <label for="fable_support">
            <input class="oopspam-toggle" type="checkbox" id="fable_support" 
                   name="oopspamantispam_settings[oopspam_is_fable_activated]" 
                   value="1" <?php echo $is_activated ? 'checked="checked"' : ''; ?> 
                   <?php echo $is_constant ? 'disabled' : ''; ?>/>
            <?php if ($is_constant): ?>
                <p class="description"><?php echo __('This setting is defined in wp-config.php'); ?></p>
            <?php endif; ?>
        </label>
    </div>
    <?php
}

function oopspam_fable_spam_message_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                    <label for="oopspam_fable_spam_message">
                    <input id="oopspam_fable_spam_message" type="text" class="regular-text" name="oopspamantispam_settings[oopspam_fable_spam_message]" value="<?php if (isset($options['oopspam_fable_spam_message'])) {
        esc_html_e($options['oopspam_fable_spam_message'], "oopspam");
    }
    ?>">
                        <p class="description"><?php echo __('Enter a short message to display when a spam Formidable Forms entry has been submitted. (e.g Our spam detection classified your submission as spam. Please contact via name@example.com)', 'oopspam'); ?></p>
                        </label>
                </div>
            <?php
}

function oopspam_fable_content_field_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                    <label for="oopspam_fable_content_field">
                    <input type="text" class="regular-text" name="oopspamantispam_settings[oopspam_fable_content_field]" value="<?php if (isset($options['oopspam_fable_content_field'])) {
        echo esc_html($options['oopspam_fable_content_field']);
    }
    ?>">
                        <p class="description"><?php echo __('By default, OOPSpam looks for a textarea field in your Formidable Forms. If you have multiple textarea fields, specify the main content/message field ID here.', 'oopspam'); ?></p>
                        <p class="description"><?php echo __('Have multiple forms? Enter the message field ids separated by commas.', 'oopspam'); ?></p>
                        </label>
                </div>
            <?php
}

function oopspam_fable_exclude_form_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                    <label for="oopspam_fable_exclude_form">
                    <input type="text" placeholder="Enter form IDs (e.g 1,5,2 or 5)" class="regular-text" name="oopspamantispam_settings[oopspam_fable_exclude_form]" value="<?php if (isset($options['oopspam_fable_exclude_form'])) {
        echo esc_html($options['oopspam_fable_exclude_form']);
    }
    ?>">
                        </label>
                </div>
            <?php
}

/* Formidable Forms settings section ends */

/* Gravity Forms UI settings section starts */

function oopspam_is_gf_activated_render()
{
    $options = get_option('oopspamantispam_settings');
    $is_constant = defined('OOPSPAM_IS_GF_ACTIVATED');
    $is_activated = $is_constant ? OOPSPAM_IS_GF_ACTIVATED : (isset($options['oopspam_is_gf_activated']) && 1 == $options['oopspam_is_gf_activated']);
    ?>
    <div>
        <label for="gf_support">
            <input class="oopspam-toggle" type="checkbox" id="gf_support" 
                   name="oopspamantispam_settings[oopspam_is_gf_activated]" 
                   value="1" <?php echo $is_activated ? 'checked="checked"' : ''; ?> 
                   <?php echo $is_constant ? 'disabled' : ''; ?>/>
            <?php if ($is_constant): ?>
                <p class="description"><?php echo __('This setting is defined in wp-config.php'); ?></p>
            <?php endif; ?>
        </label>
    </div>
    <?php
}

function oopspam_gf_spam_message_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                    <label for="oopspam_gf_spam_message">
                    <input id="oopspam_gf_spam_message" type="text" class="regular-text" name="oopspamantispam_settings[oopspam_gf_spam_message]" value="<?php if (isset($options['oopspam_gf_spam_message'])) {
        esc_html_e($options['oopspam_gf_spam_message'], "oopspam");
    }
    ?>">
                        <p class="description"><?php echo __('Enter a short message to display when a spam Gravity Forms entry has been submitted. (e.g Our spam detection classified your submission as spam. Please contact via name@example.com)', 'oopspam'); ?></p>
                        </label>
                </div>
            <?php
}

function oopspam_gf_content_field_render() {
    $options = get_option('oopspamantispam_settings');
    $formDataJson = isset($options['oopspam_gf_content_field']) ? $options['oopspam_gf_content_field'] : '[]';
    $formData = json_decode($formDataJson, true); // Decode JSON data into PHP array
    ?>
    <div>
        <form id="formData">
            <label for="formIdInput">Form ID:</label>
            <input type="text" id="formIdInput" name="formIdInput" placeholder="167">
            <label for="fieldIdInput">Field ID:</label>
            <input type="text" id="fieldIdInput" name="fieldIdInput" placeholder="2,3,4">
            <button type="button" onclick="addData(this)">Add Pair</button>
        </form>
        <table id="savedFormData">
            <thead>
                <tr>
                    <th>Form ID</th>
                    <th>Field ID</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (is_array($formData)) : ?>
                    <?php foreach ($formData as $key => $entry) : ?>
                        <tr>
                            <td contenteditable="true"><?php echo esc_html($entry['formId']); ?></td>
                            <td contenteditable="true"><?php echo esc_html($entry['fieldId']); ?></td>
                            <td><button type="button" onclick="deleteRow(this)">Delete</button></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <input type="hidden" name="oopspamantispam_settings[oopspam_gf_content_field]" id="formDataInput" value="<?php echo esc_attr(json_encode($formData)); ?>">
        <p class="description"><?php echo __('Enter the Form ID and Field ID pairs in the table above. If multiple Field IDs are provided for a Form ID, their values will be joined together.', 'oopspam'); ?></p>
    </div>
    <?php
}

function oopspam_gf_exclude_form_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                    <label for="oopspam_gf_exclude_form">
                    <input placeholder="Enter form IDs (e.g 1,5,2 or 5)" type="text" class="regular-text" name="oopspamantispam_settings[oopspam_gf_exclude_form]" value="<?php if (isset($options['oopspam_gf_exclude_form'])) {
        echo esc_html($options['oopspam_gf_exclude_form']);
    }
    ?>">
                        </label>
                </div>
            <?php
}

/* Gravity Forms UI settings section ends */

/* Elementor Forms UI settings section starts */

function oopspam_is_el_activated_render()
{
    $options = get_option('oopspamantispam_settings');
    $is_constant = defined('OOPSPAM_IS_EL_ACTIVATED');
    $is_activated = $is_constant ? OOPSPAM_IS_EL_ACTIVATED : (isset($options['oopspam_is_el_activated']) && 1 == $options['oopspam_is_el_activated']);
    ?>
    <div>
        <label for="el_support">
            <input class="oopspam-toggle" type="checkbox" id="el_support" 
                   name="oopspamantispam_settings[oopspam_is_el_activated]" 
                   value="1" <?php echo $is_activated ? 'checked="checked"' : ''; ?> 
                   <?php echo $is_constant ? 'disabled' : ''; ?>/>
            <?php if ($is_constant): ?>
                <p class="description"><?php echo __('This setting is defined in wp-config.php'); ?></p>
            <?php endif; ?>
        </label>
    </div>
    <?php
}

function oopspam_el_spam_message_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                    <label for="oopspam_el_spam_message">
                    <input id="oopspam_el_spam_message" type="text" class="regular-text" name="oopspamantispam_settings[oopspam_el_spam_message]" value="<?php if (isset($options['oopspam_el_spam_message'])) {
        esc_html_e($options['oopspam_el_spam_message'], "oopspam");
    }
    ?>">
                        <p class="description"><?php echo __('Enter a short message to display when a spam Elementor Forms entry has been submitted. (e.g Our spam detection classified your submission as spam. Please contact via name@example.com)', 'oopspam'); ?></p>
                        </label>
                </div>
            <?php
}

function oopspam_el_content_field_render()
{
    $options = get_option('oopspamantispam_settings');
    $formDataJson = isset($options['oopspam_el_content_field']) ? $options['oopspam_el_content_field'] : '[]';
    $formData = json_decode($formDataJson, true); // Decode JSON data into PHP array
    ?>
    <div>
        <form id="formData">
            <label for="formIdInput">Form Name:</label>
            <input type="text" id="formIdInput" name="formIdInput" placeholder="167">
            <label for="fieldIdInput">Field ID:</label>
            <input type="text" id="fieldIdInput" name="fieldIdInput" placeholder="2,3,4">
            <button type="button" onclick="addData(this)">Add Pair</button>
        </form>
        <table id="savedFormData">
            <thead>
                <tr>
                    <th>Form Name</th>
                    <th>Field ID</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (is_array($formData)) : ?>
                    <?php foreach ($formData as $key => $entry) : ?>
                        <tr>
                            <td contenteditable="true"><?php echo esc_html($entry['formId']); ?></td>
                            <td contenteditable="true"><?php echo esc_html($entry['fieldId']); ?></td>
                            <td><button type="button" onclick="deleteRow(this)">Delete</button></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <input type="hidden" name="oopspamantispam_settings[oopspam_el_content_field]" id="formDataInput" value="<?php echo esc_attr(json_encode($formData)); ?>">
        <p class="description"><?php echo __('Enter the Form Name and Field ID pairs in the table above. If multiple Field IDs are provided for a Form Name, their values will be joined together.', 'oopspam'); ?></p>
    </div>
    <?php
}

function oopspam_el_exclude_form_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                    <label for="oopspam_el_exclude_form">
                    <input id="oopspam_el_exclude_form" type="text" placeholder="Enter form names (e.g Sales Form, Contact Us or Sales Form)" class="regular-text" name="oopspamantispam_settings[oopspam_el_exclude_form]" value="<?php if (isset($options['oopspam_el_exclude_form'])) {
        echo esc_html($options['oopspam_el_exclude_form']);
    }
    ?>">
                        </label>
                </div>
            <?php
}

/* Elementor Forms UI settings section ends */

/* Bricks Forms UI settings section starts */

function oopspam_is_br_activated_render()
{
    $options = get_option('oopspamantispam_settings');
    $is_constant = defined('OOPSPAM_IS_BR_ACTIVATED');
    $is_activated = $is_constant ? OOPSPAM_IS_BR_ACTIVATED : (isset($options['oopspam_is_br_activated']) && 1 == $options['oopspam_is_br_activated']);
    ?>
    <div>
        <label for="br_support">
            <input class="oopspam-toggle" type="checkbox" id="br_support" 
                   name="oopspamantispam_settings[oopspam_is_br_activated]" 
                   value="1" <?php echo $is_activated ? 'checked="checked"' : ''; ?> 
                   <?php echo $is_constant ? 'disabled' : ''; ?>/>
            <?php if ($is_constant): ?>
                <p class="description"><?php echo __('This setting is defined in wp-config.php'); ?></p>
            <?php endif; ?>
        </label>
    </div>
    <?php
}

function oopspam_br_spam_message_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
          <div>
                  <label for="oopspam_br_spam_message">
                  <input id="oopspam_br_spam_message" type="text" class="regular-text" name="oopspamantispam_settings[oopspam_br_spam_message]" value="<?php if (isset($options['oopspam_br_spam_message'])) {
        esc_html_e($options['oopspam_br_spam_message'], "oopspam");
    }
    ?>">
                      <p class="description"><?php echo __('Enter a short message to display when a spam Bricks Forms entry has been submitted. (e.g Our spam detection classified your submission as spam. Please contact via name@example.com)', 'oopspam'); ?></p>
                      </label>
              </div>
          <?php
}

function oopspam_br_content_field_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
          <div>
                  <label for="oopspam_br_content_field">
                  <input type="text" class="regular-text" name="oopspamantispam_settings[oopspam_br_content_field]" value="<?php if (isset($options['oopspam_br_content_field'])) {
        echo esc_html($options['oopspam_br_content_field']);
    }
    ?>">
                      <p class="description"><?php echo __('By default, OOPSpam looks for a textarea field in your Bricks forms. If you have multiple textarea fields, specify the main content/message field ID here.', 'oopspam'); ?></p>
                      <p class="description"><?php echo __('Have multiple forms? Enter the message field ids separated by commas.', 'oopspam'); ?></p>
                      </label>
              </div>
          <?php
}

function oopspam_br_exclude_form_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
          <div>
                  <label for="oopspam_br_exclude_form">
                  <input type="text" placeholder="Enter form name (e.g ibptlm, jrmxxf or ibptlm)" class="regular-text" name="oopspamantispam_settings[oopspam_br_exclude_form]" value="<?php if (isset($options['oopspam_br_exclude_form'])) {
        echo esc_html($options['oopspam_br_exclude_form']);
    }
    ?>">
                      </label>
              </div>
          <?php
}

/* Bricks Forms UI settings section ends */

/* HappyForms UI settings section starts */

function oopspam_is_happyforms_activated_render()
{
    $options = get_option('oopspamantispam_settings');
    $is_constant = defined('OOPSPAM_IS_HAPPYFORMS_ACTIVATED');
    $is_activated = $is_constant ? OOPSPAM_IS_HAPPYFORMS_ACTIVATED : (isset($options['oopspam_is_happyforms_activated']) && 1 == $options['oopspam_is_happyforms_activated']);
    ?>
    <div>
        <label for="happyforms_support">
            <input class="oopspam-toggle" type="checkbox" id="happyforms_support" 
                   name="oopspamantispam_settings[oopspam_is_happyforms_activated]" 
                   value="1" <?php echo $is_activated ? 'checked="checked"' : ''; ?> 
                   <?php echo $is_constant ? 'disabled' : ''; ?>/>
            <?php if ($is_constant): ?>
                <p class="description"><?php echo __('This setting is defined in wp-config.php'); ?></p>
            <?php endif; ?>
        </label>
    </div>
    <?php
}

function oopspam_happyforms_exclude_form_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
       <div>
               <label for="oopspam_happyforms_exclude_form">
               <input id="oopspam_happyforms_exclude_form" type="text" placeholder="Enter form IDs (e.g 1,5,2 or 5)" class="regular-text" name="oopspamantispam_settings[oopspam_happyforms_exclude_form]" value="<?php if (isset($options['oopspam_happyforms_exclude_form'])) {
        echo esc_html($options['oopspam_happyforms_exclude_form']);
    }
    ?>">
                   </label>
           </div>
       <?php
}

function oopspam_happyforms_spam_message_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
          <div>
                  <label for="oopspam_happyforms_spam_message">
                  <input id="oopspam_happyforms_spam_message" type="text" class="regular-text" name="oopspamantispam_settings[oopspam_happyforms_spam_message]" value="<?php if (isset($options['oopspam_happyforms_spam_message'])) {
        esc_html_e($options['oopspam_happyforms_spam_message'], "oopspam");
    }
    ?>">
                      <p class="description"><?php echo __('Enter a short message to display when a spam HappyForms entry has been submitted. (e.g Our spam detection classified your submission as spam. Please contact via name@example.com)', 'oopspam'); ?></p>
                      </label>
              </div>
          <?php
}

/* HappyForms UI settings section ends */

/* WS Form UI settings section starts */

function oopspam_is_ws_activated_render()
{
    $options = get_option('oopspamantispam_settings');
    $is_constant = defined('OOPSPAM_IS_WS_ACTIVATED');
    $is_activated = $is_constant ? OOPSPAM_IS_WS_ACTIVATED : (isset($options['oopspam_is_ws_activated']) && 1 == $options['oopspam_is_ws_activated']);
    ?>
    <div>
        <label for="ws_support">
            <input class="oopspam-toggle" type="checkbox" id="ws_support" 
                   name="oopspamantispam_settings[oopspam_is_ws_activated]" 
                   value="1" <?php echo $is_activated ? 'checked="checked"' : ''; ?> 
                   <?php echo $is_constant ? 'disabled' : ''; ?>/>
            <?php if ($is_constant): ?>
                <p class="description"><?php echo __('This setting is defined in wp-config.php'); ?></p>
            <?php endif; ?>
        </label>
    </div>
    <?php
}

function oopspam_ws_content_field_render() {
    $options = get_option('oopspamantispam_settings');
    $formDataJson = isset($options['oopspam_ws_content_field']) ? $options['oopspam_ws_content_field'] : '[]';
    $formData = json_decode($formDataJson, true); // Decode JSON data into PHP array
    ?>
    <div>
        <form id="formData">
            <label for="formIdInput">Form ID:</label>
            <input type="text" id="formIdInput" name="formIdInput" placeholder="167">
            <label for="fieldIdInput">Field ID:</label>
            <input type="text" id="fieldIdInput" name="fieldIdInput" placeholder="2,3,4">
            <button type="button" onclick="addData(this)">Add Pair</button>
        </form>
        <table id="savedFormData">
            <thead>
                <tr>
                    <th>Form ID</th>
                    <th>Field ID</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (is_array($formData)) : ?>
                    <?php foreach ($formData as $key => $entry) : ?>
                        <tr>
                            <td contenteditable="true"><?php echo esc_html($entry['formId']); ?></td>
                            <td contenteditable="true"><?php echo esc_html($entry['fieldId']); ?></td>
                            <td><button type="button" onclick="deleteRow(this)">Delete</button></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <input type="hidden" name="oopspamantispam_settings[oopspam_ws_content_field]" id="formDataInput" value="<?php echo esc_attr(json_encode($formData)); ?>">
        <p class="description"><?php echo __('Enter the Form ID and Field ID pairs in the table above. If multiple Field IDs are provided for a Form ID, their values will be joined together.', 'oopspam'); ?></p>
    </div>
    <?php
}

function oopspam_ws_exclude_form_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
       <div>
               <label for="oopspam_ws_exclude_form">
               <input id="oopspam_ws_exclude_form" type="text" placeholder="Enter form IDs (e.g 1,5,2 or 5)" class="regular-text" name="oopspamantispam_settings[oopspam_ws_exclude_form]" value="<?php if (isset($options['oopspam_ws_exclude_form'])) {
        echo esc_html($options['oopspam_ws_exclude_form']);
    }
    ?>">
                   </label>
           </div>
       <?php
}

function oopspam_ws_spam_message_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
          <div>
                  <label for="oopspam_ws_spam_message">
                  <input id="oopspam_ws_spam_message" type="text" class="regular-text" name="oopspamantispam_settings[oopspam_ws_spam_message]" value="<?php if (isset($options['oopspam_ws_spam_message'])) {
        esc_html_e($options['oopspam_ws_spam_message'], "oopspam");
    }
    ?>">
                      <p class="description"><?php echo __('Enter a short message to display when a spam WS Form entry has been submitted. (e.g Our spam detection classified your submission as spam. Please contact via name@example.com)', 'oopspam'); ?></p>
                      </label>
              </div>
          <?php
}

/* WS Form UI settings section ends */

/* SureForms UI settings section starts */

function oopspam_is_sure_activated_render()
{
    $options = get_option('oopspamantispam_settings');
    $is_constant = defined('OOPSPAM_IS_SURE_ACTIVATED');
    $is_activated = $is_constant ? OOPSPAM_IS_SURE_ACTIVATED : (isset($options['oopspam_is_sure_activated']) && 1 == $options['oopspam_is_sure_activated']);
    ?>
    <div>
        <label for="sure_support">
            <input class="oopspam-toggle" type="checkbox" id="sure_support" 
                   name="oopspamantispam_settings[oopspam_is_sure_activated]" 
                   value="1" <?php echo $is_activated ? 'checked="checked"' : ''; ?> 
                   <?php echo $is_constant ? 'disabled' : ''; ?>/>
            <?php if ($is_constant): ?>
                <p class="description"><?php echo __('This setting is defined in wp-config.php'); ?></p>
            <?php endif; ?>
        </label>
    </div>
    <?php
}

function oopspam_sure_content_field_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                    <label for="oopspam_sure_content_field">
                    <input type="text" class="regular-text" name="oopspamantispam_settings[oopspam_sure_content_field]" value="<?php if (isset($options['oopspam_sure_content_field'])) {
        echo esc_html($options['oopspam_sure_content_field']);
    }
    ?>">
                        <p class="description"><?php echo __('By default, OOPSpam looks for a textarea field in your SureForms. If you have multiple textarea fields, specify the main content/message field ID here.', 'oopspam'); ?></p>
                        <p class="description"><?php echo __('Have multiple forms? Enter the message field ids separated by commas.', 'oopspam'); ?></p>
                        </label>
                </div>
            <?php
}

function oopspam_sure_exclude_form_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
       <div>
               <label for="oopspam_sure_exclude_form">
               <input id="oopspam_sure_exclude_form" type="text" placeholder="Enter form IDs (e.g 1,5,2 or 5)" class="regular-text" name="oopspamantispam_settings[oopspam_sure_exclude_form]" value="<?php if (isset($options['oopspam_sure_exclude_form'])) {
        echo esc_html($options['oopspam_sure_exclude_form']);
    }
    ?>">
                   </label>
           </div>
       <?php
}

function oopspam_sure_spam_message_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
          <div>
                  <label for="oopspam_sure_spam_message">
                  <input id="oopspam_sure_spam_message" type="text" class="regular-text" name="oopspamantispam_settings[oopspam_sure_spam_message]" value="<?php if (isset($options['oopspam_sure_spam_message'])) {
        esc_html_e($options['oopspam_sure_spam_message'], "oopspam");
    }
    ?>">
                      <p class="description"><?php echo __('Enter a short message to display when a spam SureForms entry has been submitted. (e.g Our spam detection classified your submission as spam. Please contact via name@example.com)', 'oopspam'); ?></p>
                      </label>
              </div>
          <?php
}

/* SureForms UI settings section ends */

/* QuForm UI settings section starts */

function oopspam_is_quform_activated_render()
{
    $options = get_option('oopspamantispam_settings');
    $is_constant = defined('OOPSPAM_IS_QUFORM_ACTIVATED');
    $is_activated = $is_constant ? OOPSPAM_IS_QUFORM_ACTIVATED : (isset($options['oopspam_is_quform_activated']) && 1 == $options['oopspam_is_quform_activated']);
    ?>
    <div>
        <label for="quform_support">
            <input class="oopspam-toggle" type="checkbox" id="quform_support" 
                   name="oopspamantispam_settings[oopspam_is_quform_activated]" 
                   value="1" <?php echo $is_activated ? 'checked="checked"' : ''; ?> 
                   <?php echo $is_constant ? 'disabled' : ''; ?>/>
            <?php if ($is_constant): ?>
                <p class="description"><?php echo __('This setting is defined in wp-config.php'); ?></p>
            <?php endif; ?>
        </label>
    </div>
    <?php
}

function oopspam_quform_content_field_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                    <label for="oopspam_quform_content_field">
                    <input type="text" class="regular-text" name="oopspamantispam_settings[oopspam_quform_content_field]" value="<?php if (isset($options['oopspam_quform_content_field'])) {
        echo esc_html($options['oopspam_quform_content_field']);
    }
    ?>">
                        <p class="description"><?php echo __('By default, OOPSpam looks for a textarea field in your QuForms. If you have multiple textarea fields, specify the main content/message Unique ID here.', 'oopspam'); ?></p>
                        <p class="description"><?php echo __('Have multiple forms? Enter the message field ids separated by commas.', 'oopspam'); ?></p>
                        </label>
                </div>
            <?php
}

function oopspam_quform_exclude_form_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
       <div>
               <label for="oopspam_quform_exclude_form">
               <input id="oopspam_quform_exclude_form" type="text" placeholder="Enter form IDs (e.g 1,5,2 or 5)" class="regular-text" name="oopspamantispam_settings[oopspam_quform_exclude_form]" value="<?php if (isset($options['oopspam_quform_exclude_form'])) {
        echo esc_html($options['oopspam_quform_exclude_form']);
    }
    ?>">
                   </label>
           </div>
       <?php
}

function oopspam_quform_spam_message_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
          <div>
                  <label for="oopspam_quform_spam_message">
                  <input id="oopspam_quform_spam_message" type="text" class="regular-text" name="oopspamantispam_settings[oopspam_quform_spam_message]" value="<?php if (isset($options['oopspam_quform_spam_message'])) {
        esc_html_e($options['oopspam_quform_spam_message'], "oopspam");
    }
    ?>">
                      <p class="description"><?php echo __('Enter a short message to display when a spam QuForms entry has been submitted. (e.g Our spam detection classified your submission as spam. Please contact via name@example.com)', 'oopspam'); ?></p>
                      </label>
              </div>
          <?php
}

/* QuForm UI settings section ends */

/* SureCart UI settings section starts */

function oopspam_is_surecart_activated_render()
{
    $options = get_option('oopspamantispam_settings');
    $is_constant = defined('OOPSPAM_IS_SURECART_ACTIVATED');
    $is_activated = $is_constant ? OOPSPAM_IS_SURECART_ACTIVATED : (isset($options['oopspam_is_surecart_activated']) && 1 == $options['oopspam_is_surecart_activated']);
    ?>
    <div>
        <label for="surecart_support">
            <input class="oopspam-toggle" type="checkbox" id="surecart_support" 
                   name="oopspamantispam_settings[oopspam_is_surecart_activated]" 
                   value="1" <?php echo $is_activated ? 'checked="checked"' : ''; ?> 
                   <?php echo $is_constant ? 'disabled' : ''; ?>/>
            <?php if ($is_constant): ?>
                <p class="description"><?php echo __('This setting is defined in wp-config.php'); ?></p>
            <?php endif; ?>
        </label>
    </div>
    <?php
}


function oopspam_surecart_spam_message_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
          <div>
                  <label for="oopspam_surecart_spam_message">
                  <input id="oopspam_surecart_spam_message" type="text" class="regular-text" name="oopspamantispam_settings[oopspam_surecart_spam_message]" value="<?php if (isset($options['oopspam_surecart_spam_message'])) {
        esc_html_e($options['oopspam_surecart_spam_message'], "oopspam");
    }
    ?>">
                      <p class="description"><?php echo __('Enter a short message to display when a spam SureCart order has been submitted. (e.g Our spam detection classified your order as spam. Please contact via name@example.com)', 'oopspam'); ?></p>
                      </label>
              </div>
          <?php
}

/* SureCart UI settings section ends */

/* WPForms  settings section starts */

function oopspam_is_wpf_activated_render()
{
    $options = get_option('oopspamantispam_settings');
    $is_constant = defined('OOPSPAM_IS_WPF_ACTIVATED');
    $is_activated = $is_constant ? OOPSPAM_IS_WPF_ACTIVATED : (isset($options['oopspam_is_wpf_activated']) && 1 == $options['oopspam_is_wpf_activated']);
    ?>
    <div>
        <label for="wpf_support">
            <input class="oopspam-toggle" type="checkbox" id="wpf_support" 
                   name="oopspamantispam_settings[oopspam_is_wpf_activated]" 
                   value="1" <?php echo $is_activated ? 'checked="checked"' : ''; ?> 
                   <?php echo $is_constant ? 'disabled' : ''; ?>/>
            <?php if ($is_constant): ?>
                <p class="description"><?php echo __('This setting is defined in wp-config.php'); ?></p>
            <?php endif; ?>
        </label>
    </div>
    <?php
}

function oopspam_wpf_spam_message_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
          <div>
                  <label for="oopspam_wpf_spam_message">
                  <input id="oopspam_wpf_spam_message" type="text" class="regular-text" name="oopspamantispam_settings[oopspam_wpf_spam_message]" value="<?php if (isset($options['oopspam_wpf_spam_message'])) {
        esc_html_e($options['oopspam_wpf_spam_message'], "oopspam");
    }
    ?>">
                      <p class="description"><?php echo __('Enter a short message to display when a spam WPForms entry has been submitted. (e.g Our spam detection classified your submission as spam. Please contact via name@example.com)', 'oopspam'); ?></p>
                      </label>
              </div>
          <?php
}


function oopspam_wpf_content_field_render() {
    $options = get_option('oopspamantispam_settings');
    $formDataJson = isset($options['oopspam_wpf_content_field']) ? $options['oopspam_wpf_content_field'] : '[]';
    $formData = json_decode($formDataJson, true); // Decode JSON data into PHP array
    ?>
    <div>
        <form id="formData">
            <label for="formIdInput">Form ID:</label>
            <input type="text" id="formIdInput" name="formIdInput" placeholder="167">
            <label for="fieldIdInput">Field ID:</label>
            <input type="text" id="fieldIdInput" name="fieldIdInput" placeholder="2,3,4">
            <button type="button" onclick="addData(this)">Add Pair</button>
        </form>
        <table id="savedFormData">
            <thead>
                <tr>
                    <th>Form ID</th>
                    <th>Field ID</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (is_array($formData)) : ?>
                    <?php foreach ($formData as $key => $entry) : ?>
                        <tr>
                            <td contenteditable="true"><?php echo esc_html($entry['formId']); ?></td>
                            <td contenteditable="true"><?php echo esc_html($entry['fieldId']); ?></td>
                            <td><button type="button" onclick="deleteRow(this)">Delete</button></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <input type="hidden" name="oopspamantispam_settings[oopspam_wpf_content_field]" id="formDataInput" value="<?php echo esc_attr(json_encode($formData)); ?>">
        <p class="description"><?php echo __('Enter the Form ID and Field ID pairs in the table above. If multiple Field IDs are provided for a Form ID, their values will be joined together.', 'oopspam'); ?></p>
    </div>
    <?php
}

function oopspam_wpf_exclude_form_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                    <label for="oopspam_wpf_exclude_form">
                    <input placeholder="Enter form IDs (e.g 1,5,2 or 5)" type="text" class="regular-text" name="oopspamantispam_settings[oopspam_wpf_exclude_form]" value="<?php if (isset($options['oopspam_wpf_exclude_form'])) {
        echo esc_html($options['oopspam_wpf_exclude_form']);
    }
    ?>">
                        </label>
                </div>
            <?php
}

/* WPForms settings section ends */

/* Fluent Forms settings section starts */

function oopspam_is_ff_activated_render()
{
    $options = get_option('oopspamantispam_settings');
    $is_constant = defined('OOPSPAM_IS_FF_ACTIVATED');
    $is_activated = $is_constant ? OOPSPAM_IS_FF_ACTIVATED : (isset($options['oopspam_is_ff_activated']) && 1 == $options['oopspam_is_ff_activated']);
    ?>
    <div>
        <label for="ff_support">
            <input class="oopspam-toggle" type="checkbox" id="ff_support" 
                   name="oopspamantispam_settings[oopspam_is_ff_activated]" 
                   value="1" <?php echo $is_activated ? 'checked="checked"' : ''; ?> 
                   <?php echo $is_constant ? 'disabled' : ''; ?>/>
            <?php if ($is_constant): ?>
                <p class="description"><?php echo __('This setting is defined in wp-config.php'); ?></p>
            <?php endif; ?>
        </label>
    </div>
    <?php
}

function oopspam_ff_spam_message_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                    <label for="oopspam_ff_spam_message">
                    <input id="oopspam_ff_spam_message" type="text" class="regular-text" name="oopspamantispam_settings[oopspam_ff_spam_message]" value="<?php if (isset($options['oopspam_ff_spam_message'])) {
        esc_html_e($options['oopspam_ff_spam_message'], "oopspam");
    }
    ?>">
                        <p class="description"><?php echo __('Enter a short message to display when a spam Fluent Forms entry has been submitted. (e.g Our spam detection classified your submission as spam. Please contact via name@example.com)', 'oopspam'); ?></p>
                        </label>
                </div>
            <?php
}


function oopspam_ff_content_field_render() {
    $options = get_option('oopspamantispam_settings');
    $formDataJson = isset($options['oopspam_ff_content_field']) ? $options['oopspam_ff_content_field'] : '[]';
    $formData = json_decode($formDataJson, true); // Decode JSON data into PHP array
    ?>
    <div>
        <form id="formData">
            <label for="formIdInput">Form ID:</label>
            <input type="text" id="formIdInput" name="formIdInput" placeholder="167">
            <label for="fieldIdInput">Name Attribute:</label>
            <input type="text" id="fieldIdInput" name="fieldIdInput" placeholder="2,3,4">
            <button type="button" onclick="addData(this)">Add Pair</button>
        </form>
        <table id="savedFormData">
            <thead>
                <tr>
                    <th>Form ID</th>
                    <th>Name Attribute</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (is_array($formData)) : ?>
                    <?php foreach ($formData as $key => $entry) : ?>
                        <tr>
                            <td contenteditable="true"><?php echo esc_html($entry['formId']); ?></td>
                            <td contenteditable="true"><?php echo esc_html($entry['fieldId']); ?></td>
                            <td><button type="button" onclick="deleteRow(this)">Delete</button></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <input type="hidden" name="oopspamantispam_settings[oopspam_ff_content_field]" id="formDataInput" value="<?php echo esc_attr(json_encode($formData)); ?>">
        <p class="description"><?php echo __('Enter the Form ID and Name Attribute pairs in the table above. If multiple Field Names are provided for a Name Attribute, their values will be joined together.', 'oopspam'); ?></p>
    </div>
    <?php
}



function oopspam_ff_exclude_form_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                    <label for="oopspam_ff_exclude_form">
                    <input id="oopspam_ff_exclude_form" placeholder="Enter form IDs (e.g 1,5,2 or 5)" type="text" class="regular-text" name="oopspamantispam_settings[oopspam_ff_exclude_form]" value="<?php if (isset($options['oopspam_ff_exclude_form'])) {
        echo esc_html($options['oopspam_ff_exclude_form']);
    }
    ?>">
                        </label>
                </div>
            <?php
}

/* Fluent Forms settings section ends */

/* Breakdance Forms settings section starts */

function oopspam_bd_spam_message_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                    <label for="oopspam_bd_spam_message">
                    <input id="oopspam_bd_spam_message" type="text" class="regular-text" name="oopspamantispam_settings[oopspam_bd_spam_message]" value="<?php if (isset($options['oopspam_bd_spam_message'])) {
        esc_html_e($options['oopspam_bd_spam_message'], "oopspam");
    }
    ?>">
                        <p class="description"><?php echo __('Enter a short message to display when a spam Breakdance Forms entry has been submitted. The message will only be displayed to an admin.', 'oopspam'); ?></p>
                        </label>
                </div>
            <?php
}

function oopspam_bd_content_field_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                    <label for="oopspam_bd_content_field">
                    <input type="text" class="regular-text" name="oopspamantispam_settings[oopspam_bd_content_field]" value="<?php if (isset($options['oopspam_bd_content_field'])) {
        echo esc_html($options['oopspam_bd_content_field']);
    }
    ?>">
                        <p class="description"><?php echo __('By default, OOPSpam looks for a textarea field in your Breakdance Forms. If you have multiple textarea fields, specify the main content/message field ID here.', 'oopspam'); ?></p>
                        <p class="description"><?php echo __('Have multiple forms? Enter the message field ids separated by commas.', 'oopspam'); ?></p>
                        </label>
                </div>
            <?php
}

function oopspam_bd_exclude_form_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                    <label for="oopspam_bd_exclude_form">
                    <input type="text" placeholder="Enter form IDs (e.g 1,5,2 or 5)" class="regular-text" name="oopspamantispam_settings[oopspam_bd_exclude_form]" value="<?php if (isset($options['oopspam_bd_exclude_form'])) {
        echo esc_html($options['oopspam_bd_exclude_form']);
    }
    ?>">
                        </label>
                </div>
            <?php
}

/* Breakdance Forms settings section ends */

/* Beaver Builder Forms settings section starts */

function oopspam_is_bb_activated_render()
{
    $options = get_option('oopspamantispam_settings');
    $is_constant = defined('OOPSPAM_IS_BB_ACTIVATED');
    $is_activated = $is_constant ? OOPSPAM_IS_BB_ACTIVATED : (isset($options['oopspam_is_bb_activated']) && 1 == $options['oopspam_is_bb_activated']);
    ?>
    <div>
        <label for="bb_support">
            <input class="oopspam-toggle" type="checkbox" id="bb_support" 
                   name="oopspamantispam_settings[oopspam_is_bb_activated]" 
                   value="1" <?php echo $is_activated ? 'checked="checked"' : ''; ?> 
                   <?php echo $is_constant ? 'disabled' : ''; ?>/>
            <?php if ($is_constant): ?>
                <p class="description"><?php echo __('This setting is defined in wp-config.php'); ?></p>
            <?php endif; ?>
        </label>
    </div>
    <?php
}

/* Beaver Builder Forms settings section ends */

/* Contact Form 7 settings section starts */

function oopspam_is_cf7_activated_render()
{
    $options = get_option('oopspamantispam_settings');
    $is_constant = defined('OOPSPAM_IS_CF7_ACTIVATED');
    $is_activated = $is_constant ? OOPSPAM_IS_CF7_ACTIVATED : (isset($options['oopspam_is_cf7_activated']) && 1 == $options['oopspam_is_cf7_activated']);
    ?>
    <div>
        <label for="cf7_support">
            <input class="oopspam-toggle" type="checkbox" id="cf7_support" 
                   name="oopspamantispam_settings[oopspam_is_cf7_activated]" 
                   value="1" <?php echo $is_activated ? 'checked="checked"' : ''; ?> 
                   <?php echo $is_constant ? 'disabled' : ''; ?>/>
            <?php if ($is_constant): ?>
                <p class="description"><?php echo __('This setting is defined in wp-config.php'); ?></p>
            <?php endif; ?>
        </label>
    </div>
    <?php
}

function oopspam_cf7_spam_message_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                    <label for="oopspam_cf7_spam_message">
                    <input id="oopspam_cf7_spam_message" type="text" class="regular-text" name="oopspamantispam_settings[oopspam_cf7_spam_message]" value="<?php if (isset($options['oopspam_cf7_spam_message'])) {
        esc_html_e($options['oopspam_cf7_spam_message'], "oopspam");
    }
    ?>">
                        <p class="description"><?php echo __('Enter a short message to display when a spam Contact Form 7 entry has been submitted. (e.g Our spam detection classified your donation as spam. Please contact via name@example.com)', 'oopspam'); ?></p>
                        </label>
                </div>
            <?php
}

function oopspam_cf7_content_field_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                    <label for="oopspam_is_cf7_content_field">
                    <input type="text" class="regular-text" name="oopspamantispam_settings[oopspam_is_cf7_content_field]" value="<?php if (isset($options['oopspam_is_cf7_content_field'])) {
        echo esc_html($options['oopspam_is_cf7_content_field']);
    }
    ?>">
                        <p class="description"><?php echo __('By default, OOPSpam looks for a textarea field with "your_message" name in your CF7 form. If you have multiple textarea fields, specify the main content/message field name here.', 'oopspam'); ?></p>
                        <p class="description"><?php echo __('Have multiple forms? Enter the message field names separated by commas.', 'oopspam'); ?></p>
                        </label>
                </div>
            <?php
}


/* Contact Form 7 settings section ends */

/* GiveWP settings section starts */

function oopspam_is_give_activated_render()
{
    $options = get_option('oopspamantispam_settings');
    $is_constant = defined('OOPSPAM_IS_GIVE_ACTIVATED');
    $is_activated = $is_constant ? OOPSPAM_IS_GIVE_ACTIVATED : (isset($options['oopspam_is_give_activated']) && 1 == $options['oopspam_is_give_activated']);
    ?>
    <div>
        <label for="give_support">
            <input class="oopspam-toggle" type="checkbox" id="give_support" 
                   name="oopspamantispam_settings[oopspam_is_give_activated]" 
                   value="1" <?php echo $is_activated ? 'checked="checked"' : ''; ?> 
                   <?php echo $is_constant ? 'disabled' : ''; ?>/>
            <?php if ($is_constant): ?>
                <p class="description"><?php echo __('This setting is defined in wp-config.php'); ?></p>
            <?php endif; ?>
        </label>
    </div>
    <?php
}

function oopspam_give_spam_message_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                    <label for="oopspam_give_spam_message">
                    <input id="oopspam_give_spam_message" type="text" class="regular-text" name="oopspamantispam_settings[oopspam_give_spam_message]" value="<?php if (isset($options['oopspam_give_spam_message'])) {
        esc_html_e($options['oopspam_give_spam_message'], "oopspam");
    }
    ?>">
                        <p class="description"><?php echo __('Enter a short message to display when a spam GiveWP Forms entry has been submitted. (e.g Our spam detection classified your donation as spam. Please contact via name@example.com)', 'oopspam'); ?></p>
                        </label>
                </div>
            <?php
}

/* GiveWP settings section ends */

/* WooCommerce settings section starts */

function oopspam_is_woo_activated_render()
{
    $options = get_option('oopspamantispam_settings');
    $is_constant = defined('OOPSPAM_IS_WOO_ACTIVATED');
    $is_activated = $is_constant ? OOPSPAM_IS_WOO_ACTIVATED : (isset($options['oopspam_is_woo_activated']) && 1 == $options['oopspam_is_woo_activated']);
    ?>
    <div>
        <label for="woo_support">
            <input class="oopspam-toggle" type="checkbox" id="woo_support" 
                   name="oopspamantispam_settings[oopspam_is_woo_activated]" 
                   value="1" <?php echo $is_activated ? 'checked="checked"' : ''; ?> 
                   <?php echo $is_constant ? 'disabled' : ''; ?>/>
            <?php if ($is_constant): ?>
                <p class="description"><?php echo __('This setting is defined in wp-config.php'); ?></p>
            <?php endif; ?>
        </label>
    </div>
    <?php
}

function oopspam_woo_payment_methods_render() {
    $options = get_option('oopspamantispam_settings');
    $payment_methods = isset($options['oopspam_woo_payment_methods']) ? $options['oopspam_woo_payment_methods'] : '';
    ?>
    <details>
        <summary><?php echo __('Specify payment methods', 'oopspam'); ?></summary>
        <div style="margin-top: 10px;">
            <textarea name="oopspamantispam_settings[oopspam_woo_payment_methods]" 
                    placeholder="paypal&#10;stripe&#10;credit"  
                    rows="5" 
                    cols="50" 
                    class="large-text code"><?php echo esc_textarea($payment_methods); ?></textarea>
            <p class="description">
                <?php echo __('One payment method per line. Origin check will only apply to orders using these payment methods. Leave empty to check all methods.', 'oopspam'); ?>
            </p>
        </div>
    </details>
    <?php
}

function sanitize_oopspam_settings($input) {
    if (isset($input['oopspam_woo_payment_methods'])) {
        // Sanitize textarea - remove any HTML tags and preserve line breaks
        $input['oopspam_woo_payment_methods'] = sanitize_textarea_field($input['oopspam_woo_payment_methods']);
    }
    return $input;
}

function oopspam_woo_check_origin_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
                <div>
                    <label for="woo_order_origin">
                    <input class="oopspam-toggle" type="checkbox" id="woo_order_origin" name="oopspamantispam_settings[oopspam_woo_check_origin]" value="1" <?php if (isset($options['oopspam_woo_check_origin']) && 1 == $options['oopspam_woo_check_origin']) {
        echo 'checked="checked"';
    }
    ?>/>
                     <p class="description"><?php echo __('Enable this setting if all your legitimate orders have a proper origin that is not "Unknown." The "Order Attribution" feature must be enabled in WooCommerce (Settings → Advanced → Features).', 'oopspam'); ?></p>
                     <p class="description"><?php echo __('Avoid using this feature if you place orders via the API.', 'oopspam'); ?></p>
                    </label>
                </div>
            <?php
}

function oopspam_woo_spam_message_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                    <label for="oopspam_woo_spam_message">
                    <input type="text" class="regular-text" name="oopspamantispam_settings[oopspam_woo_spam_message]" value="<?php if (isset($options['oopspam_woo_spam_message'])) {
        esc_html_e($options['oopspam_woo_spam_message'], "oopspam");
    }
    ?>">
                        <p class="description"><?php echo __('Enter a short message to display when a spam order has been submitted in your WooCommerce store. (e.g Our spam detection classified your order as spam. Please contact via name@example.com)', 'oopspam'); ?></p>
                        </label>
                </div>
            <?php
}

function oopspam_woo_check_honeypot_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
    <div>
        <label for="woo_honeypot">
            <input class="oopspam-toggle" type="checkbox" id="woo_honeypot" 
                   name="oopspamantispam_settings[oopspam_woo_check_honeypot]" 
                   value="1" <?php checked(isset($options['oopspam_woo_check_honeypot']) && $options['oopspam_woo_check_honeypot'] == 1); ?>/>
        </label>
    </div>
    <?php
}

function oopspam_woo_disable_rest_checkout_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
    <div>
        <label for="woo_disable_rest_checkout">
            <input class="oopspam-toggle" type="checkbox" id="woo_disable_rest_checkout" 
                   name="oopspamantispam_settings[oopspam_woo_disable_rest_checkout]" 
                   value="1" <?php checked(isset($options['oopspam_woo_disable_rest_checkout']) && $options['oopspam_woo_disable_rest_checkout'] == 1); ?>/>
        </label>
    </div>
    <?php
}

function oopspam_woo_min_session_pages_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
    <div>
        <label for="woo_min_session_pages">
            <input type="number" min="0" id="woo_min_session_pages" 
                   name="oopspamantispam_settings[oopspam_woo_min_session_pages]" 
                   value="<?php echo isset($options['oopspam_woo_min_session_pages']) && $options['oopspam_woo_min_session_pages'] !== '' ? esc_attr($options['oopspam_woo_min_session_pages']) : ''; ?>" 
                   placeholder="0"/>
            <p class="description"><?php echo __('Set the minimum number of unique pages a customer must view before placing an order. Leave empty or set to 0 to disable this check. This helps prevent automated bots that don\'t browse your site before checkout.', 'oopspam'); ?></p>
            <p class="description"><?php echo __('Note: Requires WooCommerce Order Attribution to be enabled.', 'oopspam'); ?></p>
        </label>
    </div>
    <?php
}

function oopspam_woo_require_device_type_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
    <div>
        <label for="woo_require_device_type">
            <input class="oopspam-toggle" type="checkbox" id="woo_require_device_type" 
                   name="oopspamantispam_settings[oopspam_woo_require_device_type]" 
                   value="1" <?php checked(isset($options['oopspam_woo_require_device_type']) && $options['oopspam_woo_require_device_type'] == 1); ?>/>
            <p class="description"><?php echo __('Block orders that don\'t have a valid device type. This helps prevent orders from bots that don\'t properly identify their device.', 'oopspam'); ?></p>
            <p class="description"><?php echo __('Note: Requires WooCommerce Order Attribution to be enabled.', 'oopspam'); ?></p>
        </label>
    </div>
    <?php
}

/* WooCommerce settings section ends */

/* WP Registration settings section starts */

function oopspam_is_wpregister_activated_render()
{
    $options = get_option('oopspamantispam_settings');
    $is_constant = defined('OOPSPAM_IS_WPREGISTER_ACTIVATED');
    $is_activated = $is_constant ? OOPSPAM_IS_WPREGISTER_ACTIVATED : (isset($options['oopspam_is_wpregister_activated']) && 1 == $options['oopspam_is_wpregister_activated']);
    ?>
    <div>
        <label for="wpregister_support">
            <input class="oopspam-toggle" type="checkbox" id="wpregister_support" 
                   name="oopspamantispam_settings[oopspam_is_wpregister_activated]" 
                   value="1" <?php echo $is_activated ? 'checked="checked"' : ''; ?> 
                   <?php echo $is_constant ? 'disabled' : ''; ?>/>
            <?php if ($is_constant): ?>
                <p class="description"><?php echo __('This setting is defined in wp-config.php'); ?></p>
            <?php endif; ?>
        </label>
    </div>
    <?php
}

function oopspam_wpregister_spam_message_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                    <label for="oopspam_wpregister_spam_message">
                    <input id="oopspam_wpregister_spam_message" type="text" class="regular-text" name="oopspamantispam_settings[oopspam_wpregister_spam_message]" value="<?php if (isset($options['oopspam_wpregister_spam_message'])) {
        esc_html_e($options['oopspam_wpregister_spam_message'], "oopspam");
    }
    ?>">
                        <p class="description"><?php echo __('Enter a short message to display when a spam WordPress registration entry has been submitted. (e.g Our spam detection classified your registration as spam. Please contact via name@example.com)', 'oopspam'); ?></p>
                        </label>
                </div>
            <?php
}

/* WP Registration settings section ends */

/* Ultimate Member settings starts */

function oopspam_is_umember_activated_render()
{
    $options = get_option('oopspamantispam_settings');
    $is_constant = defined('OOPSPAM_IS_UMEMBER_ACTIVATED');
    $is_activated = $is_constant ? OOPSPAM_IS_UMEMBER_ACTIVATED : (isset($options['oopspam_is_umember_activated']) && 1 == $options['oopspam_is_umember_activated']);
    ?>
    <div>
        <label for="umember_support">
            <input class="oopspam-toggle" type="checkbox" id="umember_support" 
                   name="oopspamantispam_settings[oopspam_is_umember_activated]" 
                   value="1" <?php echo $is_activated ? 'checked="checked"' : ''; ?> 
                   <?php echo $is_constant ? 'disabled' : ''; ?>/>
            <?php if ($is_constant): ?>
                <p class="description"><?php echo __('This setting is defined in wp-config.php'); ?></p>
            <?php endif; ?>
        </label>
    </div>
    <?php
}

function oopspam_umember_spam_message_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                    <label for="oopspam_umember_spam_message">
                    <input id="oopspam_umember_spam_message" type="text" class="regular-text" name="oopspamantispam_settings[oopspam_umember_spam_message]" value="<?php if (isset($options['oopspam_umember_spam_message'])) {
        esc_html_e($options['oopspam_umember_spam_message'], "oopspam");
    }
    ?>">
                        <p class="description"><?php echo __('Enter a short message to display when a spam Ultimate Member Form entry has been submitted. (e.g Our spam detection classified your registration as spam. Please contact via name@example.com)', 'oopspam'); ?></p>
                        </label>
                </div>
            <?php
}

/* Ultimate Member settings section ends */

/* Pro Membership Pro settings starts */

function oopspam_is_pmp_activated_render()
{
    $options = get_option('oopspamantispam_settings');
    $is_constant = defined('OOPSPAM_IS_PMP_ACTIVATED');
    $is_activated = $is_constant ? OOPSPAM_IS_PMP_ACTIVATED : (isset($options['oopspam_is_pmp_activated']) && 1 == $options['oopspam_is_pmp_activated']);
    ?>
    <div>
        <label for="pmp_support">
            <input class="oopspam-toggle" type="checkbox" id="pmp_support" 
                   name="oopspamantispam_settings[oopspam_is_pmp_activated]" 
                   value="1" <?php echo $is_activated ? 'checked="checked"' : ''; ?> 
                   <?php echo $is_constant ? 'disabled' : ''; ?>/>
            <?php if ($is_constant): ?>
                <p class="description"><?php echo __('This setting is defined in wp-config.php'); ?></p>
            <?php endif; ?>
        </label>
    </div>
    <?php
}

function oopspam_pmp_spam_message_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                    <label for="oopspam_pmp_spam_message">
                    <input id="oopspam_pmp_spam_message" type="text" class="regular-text" name="oopspamantispam_settings[oopspam_pmp_spam_message]" value="<?php if (isset($options['oopspam_pmp_spam_message'])) {
        esc_html_e($options['oopspam_pmp_spam_message'], "oopspam");
    }
    ?>">
                        <p class="description"><?php echo __('Enter a short message to display when a spam Pro Membership Pro checkout from entry has been submitted. (e.g Our spam detection classified your registration as spam. Please contact via name@example.com)', 'oopspam'); ?></p>
                        </label>
                </div>
            <?php
}

/* Pro Membership Pro settings section ends */


/* MemberPress settings starts */

function oopspam_is_mpress_activated_render()
{
    $options = get_option('oopspamantispam_settings');
    $is_constant = defined('OOPSPAM_IS_MPRESS_ACTIVATED');
    $is_activated = $is_constant ? OOPSPAM_IS_MPRESS_ACTIVATED : (isset($options['oopspam_is_mpress_activated']) && 1 == $options['oopspam_is_mpress_activated']);
    ?>
    <div>
        <label for="mpress_support">
            <input class="oopspam-toggle" type="checkbox" id="mpress_support" 
                   name="oopspamantispam_settings[oopspam_is_mpress_activated]" 
                   value="1" <?php echo $is_activated ? 'checked="checked"' : ''; ?> 
                   <?php echo $is_constant ? 'disabled' : ''; ?>/>
            <?php if ($is_constant): ?>
                <p class="description"><?php echo __('This setting is defined in wp-config.php'); ?></p>
            <?php endif; ?>
        </label>
    </div>
    <?php
}

function oopspam_mpress_spam_message_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                    <label for="oopspam_mpress_spam_message">
                    <input id="oopspam_mpress_spam_message" type="text" class="regular-text" name="oopspamantispam_settings[oopspam_mpress_spam_message]" value="<?php if (isset($options['oopspam_mpress_spam_message'])) {
        esc_html_e($options['oopspam_mpress_spam_message'], "oopspam");
    }
    ?>">
                        <p class="description"><?php echo __('Enter a short message to display when a spam MemberPress Membership form entry has been submitted. (e.g Our spam detection classified your registration as spam. Please contact via name@example.com)', 'oopspam'); ?></p>
                        </label>
                </div>
            <?php
}

function oopspam_mpress_exclude_form_render()
{
    $options = get_option('oopspamantispam_settings');
    ?>
            <div>
                    <label for="oopspam_mpress_exclude_form">
                    <input id="oopspam_mpress_exclude_form" type="text" placeholder="Enter form IDs (e.g 1,5,2 or 5)" class="regular-text" name="oopspamantispam_settings[oopspam_mpress_exclude_form]" value="<?php if (isset($options['oopspam_mpress_exclude_form'])) {
        echo esc_html($options['oopspam_mpress_exclude_form']);
    }
    ?>">
                        </label>
                </div>
            <?php
}

/* MemberPress settings section ends */


function oopspamantispam_options_page()
{
    ?>
    <div style="display:flex; flex-direction:row; justify-content:space-around;">
        <p>Contact support via <a href="mailto:contact@oopspam.com">contact@oopspam.com</a> </p>
        <p>Need help with the plugin? <a href="https://wordpress.org/support/plugin/oopspam-anti-spam/">WordPress Plugin Support Forum</a> </p>
        <p><a href="https://wordpress.org/support/plugin/oopspam-anti-spam/reviews/#new-post">Support us with a review ♥️</a></p>
    </div>
    <?php
    
    $options = get_option('oopspamantispam_settings');
    $api_key = defined('OOPSPAM_API_KEY') ? OOPSPAM_API_KEY : (isset($options['oopspam_api_key']) ? $options['oopspam_api_key'] : '');

    // Check if user manually requested the wizard
    $start_wizard = isset($_GET['start_wizard']) && $_GET['start_wizard'] == 1;
    
    // If API key is not set or user manually requested the wizard, redirect to setup wizard
    if (empty($api_key) || $start_wizard) {
        // Prevent redirect loops by checking if we're already coming from the wizard or have a redirect flag
        $from_wizard = isset($_GET['from_wizard']) && $_GET['from_wizard'] == 1;
        $redirect_flag = get_transient('oopspam_options_redirect');
        
        if (!$from_wizard && !$redirect_flag) {
            // Set a transient to prevent multiple redirects
            set_transient('oopspam_options_redirect', true, 30);
            // Redirect to setup wizard
            wp_redirect(admin_url('admin.php?page=oopspam_setup_wizard'));
            exit;
        }
    }
    ?>
    
    <hr/><br/>
    <?php
    ?>
<?php
$active_tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'general';

if( isset( $_GET[ 'tab' ] ) ) {

	$active_tab = $_GET[ 'tab' ];

}

?>

<h2 class="nav-tab-wrapper">
        <a href="?page=wp_oopspam_settings_page&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">General</a>
        <a href="?page=wp_oopspam_settings_page&tab=privacy" class="nav-tab <?php echo $active_tab == 'privacy' ? 'nav-tab-active' : ''; ?>">Privacy</a>
        <a href="?page=wp_oopspam_settings_page&tab=manual_moderation" class="nav-tab <?php echo $active_tab == 'manual_moderation' ? 'nav-tab-active' : ''; ?>">Manual Moderation</a>
        <a href="?page=wp_oopspam_settings_page&tab=rate_limiting" class="nav-tab <?php echo $active_tab == 'rate_limiting' ? 'nav-tab-active' : ''; ?>">Rate Limiting</a>
        <a href="?page=wp_oopspam_settings_page&tab=ip_filtering" class="nav-tab <?php echo $active_tab == 'ip_filtering' ? 'nav-tab-active' : ''; ?>">IP Filtering</a>
        <a href="?page=wp_oopspam_settings_page&tab=contextai" class="nav-tab <?php echo $active_tab == 'contextai' ? 'nav-tab-active' : ''; ?>">Contextual Detection <span class="beta-tag" style="font-size: 10px; background: #e3e3e3; padding: 2px 4px; border-radius: 3px;">Experimental</span></a>
    </h2>


        <form action='options.php' method='post'>
            
        <?php
               if ($active_tab == 'manual_moderation') {
                settings_fields('oopspamantispam-manual-moderation');
                do_settings_sections('oopspamantispam-manual-moderation');
            } elseif ($active_tab == 'privacy') {
                settings_fields('oopspamantispam-privacy-settings-group');
                do_settings_sections('oopspamantispam-privacy-settings-group');
            } elseif ($active_tab == 'rate_limiting') {
                settings_fields('oopspamantispam-ratelimit-settings-group');
                do_settings_sections('oopspamantispam-ratelimit-settings-group');
            } elseif ($active_tab == 'ip_filtering') {
                settings_fields('oopspamantispam-ipfiltering-settings-group');
                do_settings_sections('oopspamantispam-ipfiltering-settings-group');
            } elseif ($active_tab == 'contextai') {
                settings_fields('oopspamantispam-contextai-settings-group');
                do_settings_sections('oopspamantispam-contextai-settings-group');
            } else {
           
                settings_fields('oopspamantispam-settings-group');
                    do_settings_sections('oopspamantispam-settings-group');
                    ?>
                            <div class="ninja-forms form-setting">
                            <?php
                do_settings_sections('oopspamantispam-nj-settings-group');
                    ?>
                            </div>
                            <div class="elementor-forms form-setting">
                            <?php
                do_settings_sections('oopspamantispam-el-settings-group');
                    ?>
                            </div>
                            <div class="bricks-forms form-setting">
                            <?php
                do_settings_sections('oopspamantispam-br-settings-group');
                    ?>
                            </div>
                            <div class="gravity-forms form-setting">
                            <?php
                do_settings_sections('oopspamantispam-gf-settings-group');
                    ?>
                            </div>
                            <div class="fluent-forms form-setting">
                            <?php
                do_settings_sections('oopspamantispam-ff-settings-group');
                    ?>
                            </div>
                            <div class="breakdance-forms form-setting">
                            <?php
                do_settings_sections('oopspamantispam-bd-settings-group');
                    ?>
                            </div>
                            <div class="cf7 form-setting">
                            <?php
                do_settings_sections('oopspamantispam-cf7-settings-group');
                    ?>
                            </div>
                            <div class="jform form-setting">
                            <?php
                do_settings_sections('oopspamantispam-jform-settings-group');
                    ?>
                            </div>
                            <div class="wpforms form-setting">
                            <?php
                do_settings_sections('oopspamantispam-wpf-settings-group');
                    ?>
                            </div>
                            <div class="fable form-setting">
                            <?php
                do_settings_sections('oopspamantispam-fable-settings-group');
                    ?>
                            </div>
                            <div class="give form-setting">
                            <?php
                do_settings_sections('oopspamantispam-give-settings-group');
                    ?>
                            </div>
                            <div class="woo form-setting">
                            <?php
                do_settings_sections('oopspamantispam-woo-settings-group');
                    ?>
                            </div>
                            <div class="wpregister form-setting">
                            <?php
                do_settings_sections('oopspamantispam-wpregister-settings-group');
                    ?>
                            </div>
                            <div class="ws-form form-setting">
                            <?php
                do_settings_sections('oopspamantispam-ws-settings-group');
                    ?>
                            </div>
                            <div class="ts-form form-setting">
                            <?php
                do_settings_sections('oopspamantispam-ts-settings-group');
                    ?>
                            </div>
                            <div class="pionet-form form-setting">
                            <?php
                do_settings_sections('oopspamantispam-pionet-settings-group');
                    ?>
                            </div>
                            <div class="kb-form form-setting">
                            <?php
                do_settings_sections('oopspamantispam-kb-settings-group');
                    ?>
                            </div>
                            <div class="wpdiscuz form-setting">
                            <?php
                do_settings_sections('oopspamantispam-wpdis-settings-group');
                    ?>
                            </div>
                            <div class="mc4wp form-setting">
                            <?php
                do_settings_sections('oopspamantispam-mc4wp-settings-group');
                    ?>
                            </div>
                            <div class="mpoet form-setting">
                            <?php
                do_settings_sections('oopspamantispam-mpoet-settings-group');
                    ?>
                            </div>
                            <div class="bb-forms form-setting">
                            <?php
                do_settings_sections('oopspamantispam-bb-settings-group');
                    ?>
                            </div>
                            <div class="forminator form-setting">
                            <?php
                do_settings_sections('oopspamantispam-forminator-settings-group');
                    ?>
                            </div>
                            <div class="umember form-setting">
                            <?php
                do_settings_sections('oopspamantispam-umember-settings-group');
                    ?>
                    </div>
                    <div class="pmp form-setting">
                            <?php
                do_settings_sections('oopspamantispam-pmp-settings-group');
                    ?>
                    </div>
                    <div class="mpress form-setting">
                            <?php
                do_settings_sections('oopspamantispam-mpress-settings-group');
                    ?>
                    </div>
                    <div class="sure-forms form-setting">
                    <?php
                    do_settings_sections('oopspamantispam-sure-settings-group');
                    ?>
                    </div>
                    <div class="surecart form-setting">
                    <?php
                    do_settings_sections('oopspamantispam-surecart-settings-group');
                    ?>
                    </div>
                    <div class="quform form-setting">
                    <?php
                    do_settings_sections('oopspamantispam-quform-settings-group');
                    ?>
                    </div>
                     <div class="happyforms form-setting">
                    <?php
                    do_settings_sections('oopspamantispam-happyforms-settings-group');
                    ?>
                    </div>
                    <?php
        }
        ?>
        <?php submit_button(); ?>
    </form>
    <?php
}

function oopspam_block_vpns_render()
{
    $options = get_option('oopspamantispam_ipfiltering_settings');
    ?>
    <div>
        <label for="block_vpns">
            <input class="oopspam-toggle" type="checkbox" id="block_vpns" 
                   name="oopspamantispam_ipfiltering_settings[oopspam_block_vpns]"  <?php checked(!isset($options['oopspam_block_vpns']), false, true); ?>/>
            <p class="description"><?php echo __('Block submissions from known VPN services.', 'oopspam'); ?></p>
        </label>
    </div>
    <?php
}

function oopspam_block_cloud_providers_render()
{
    $options = get_option('oopspamantispam_ipfiltering_settings');
    ?>
    <div>
        <label for="block_cloud_providers">
            <input class="oopspam-toggle" type="checkbox" id="block_cloud_providers" 
                   name="oopspamantispam_ipfiltering_settings[oopspam_block_cloud_providers]"  <?php checked(!isset($options['oopspam_block_cloud_providers']), false, true); ?>/>
            <p class="description"><?php echo __('Block submissions from over 1500+ known cloud provider IPs (AWS, Google Cloud, Azure, etc).', 'oopspam'); ?></p>
        </label>
    </div>
    <?php
}