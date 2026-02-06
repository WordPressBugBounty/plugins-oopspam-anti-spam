<?php

namespace OOPSPAM\Integrations;

add_action('bp_signup_validate', 'OOPSPAM\Integrations\oopspamantispam_buddypress_validate_signup');

function oopspamantispam_buddypress_validate_signup()
{

    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');

    // Check if BuddyPress integration is enabled and we have an API key
    if (!empty(oopspamantispam_get_key()) && oopspam_is_spamprotection_enabled('buddypress')) {
        
        // Get user data from the signup process
        $user_login = isset($_POST['signup_username']) ? sanitize_user($_POST['signup_username']) : '';
        $user_email = isset($_POST['signup_email']) ? sanitize_email($_POST['signup_email']) : '';
        
        // Only proceed if we have an email
        if (!empty($user_email)) {
            
            $userIP = "";
            if (!isset($privacyOptions['oopspam_is_check_for_ip']) || ($privacyOptions['oopspam_is_check_for_ip'] !== true && $privacyOptions['oopspam_is_check_for_ip'] !== 'on')) {
                $userIP = oopspamantispam_get_ip();
            }

            // Prepare content for spam check (combine username and any profile fields)
            $content = $user_login;
            
            // Check for profile fields if available
            if (isset($_POST['field_1']) && !empty($_POST['field_1'])) {
                $content .= " " . sanitize_text_field($_POST['field_1']); // Usually the display name
            }
            
            // Add any additional profile fields that might contain text
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'field_') === 0 && is_string($value) && !empty($value)) {
                    $sanitized_field = sanitize_text_field($value);
                    if (strlen($sanitized_field) > 3) { // Only add meaningful content
                        $content .= " " . $sanitized_field;
                    }
                }
            }

            $detectionResult = oopspamantispam_call_OOPSpam($content, $userIP, $user_email, true, "buddypress");
            
            if (!isset($detectionResult["isItHam"])) {
                return;
            }

            $frmEntry = [
                "Score" => $detectionResult["Score"],
                "Message" => $content,
                "IP" => $userIP,
                "Email" => $user_email,
                "RawEntry" => json_encode($_POST),
                "FormId" => "BuddyPress Registration",
            ];

            if (!$detectionResult["isItHam"]) {
                // It's spam, store the submission and block registration
                oopspam_store_spam_submission($frmEntry, $detectionResult["Reason"]);
                
                $error_to_show = (isset($options['oopspam_buddypress_spam_message']) && !empty($options['oopspam_buddypress_spam_message'])) 
                    ? $options['oopspam_buddypress_spam_message'] 
                    : __('Your registration has been flagged as spam.', 'oopspam-anti-spam');
                
                // Block the registration with wp_die
                wp_die(esc_html($error_to_show));
            } else {
                // It's ham
                oopspam_store_ham_submission($frmEntry);
            }
        }
    }
}