<?php

namespace OOPSPAM\Integrations;

add_filter('wpcf7_spam', 'OOPSPAM\Integrations\oopspamantispam_cf7_pre_submission', 10, 1);

function oopspamantispam_cf7_pre_submission($spam)
{
    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');

    if (!empty(oopspamantispam_get_key()) && oopspam_is_spamprotection_enabled('cf7')) {

        if ($spam) {
            return $spam;
        }

        $userIP = "";
        $email = "";
        $escapedMsg = "";
        if (!isset($privacyOptions['oopspam_is_check_for_ip']) || $privacyOptions['oopspam_is_check_for_ip'] != true) {
            $userIP = oopspamantispam_get_ip();
        }

        // Check for default email field first
        if (isset($_POST["your-email"])) {
            $email = sanitize_email($_POST["your-email"]);
        } else {
            // If default email field not found, look for any field containing 'email'
            foreach ($_POST as $field_name => $field_value) {
                if (is_string($field_name) && stripos($field_name, 'email') !== false) {
                    $email = sanitize_email($field_value);
                    break;
                }
            }
        }


        if (isset($options['oopspam_is_cf7_content_field']) && $options['oopspam_is_cf7_content_field']) {
            $customContentFieldId = sanitize_text_field(trim($options['oopspam_is_cf7_content_field']));
            $idsArray = array_map('trim', explode(',', $customContentFieldId));

            // Iterate through each ID to look for message field value
            foreach ($idsArray as $id) {
                // Capture the content
                if (isset($_POST[$id])) {
                    $escapedMsg .= ' ' . sanitize_textarea_field($_POST[$id]) . ' ';
                }
            }
        } else {
            // Default message field
            $escapedMsg = isset($_POST["your-message"]) ? sanitize_textarea_field($_POST["your-message"]) : '';
        }
        
        $detectionResult = oopspamantispam_call_OOPSpam($escapedMsg, $userIP, $email, true, "cf7");
        $raw_entry = json_encode($_POST);

        if (!isset($detectionResult["isItHam"])) {
            return $spam;
        }

        $frmEntry = [
            "Score" => $detectionResult["Score"],
            "Message" => $escapedMsg,
            "IP" => $userIP,
            "Email" => $email,
            "RawEntry" => $raw_entry,
            "FormId" => $_POST['_wpcf7'],
        ];

        if (!$detectionResult["isItHam"]) {
            // It's spam, store the submission and log it
            oopspam_store_spam_submission($frmEntry, $detectionResult["Reason"]);

            $spam = true;

            // Leaving a spam log.
            $submission = \WPCF7_Submission::get_instance();

            $submission->add_spam_log(array(
                'agent' => 'OOPSpam',
                'reason' => "OOPSpam score " . $detectionResult["Score"] . " is higher than the threshold " . oopspamantispam_get_spamscore_threshold(),
            ));

            // Show a custom message
            if (isset($options['oopspam_cf7_spam_message']) && !empty($options['oopspam_cf7_spam_message'])) {
                $error_to_show = $options['oopspam_cf7_spam_message'];
            } else {
                $error_to_show = "Your submission has been flagged as spam.";
            }
            add_filter('wpcf7_display_message', function($message, $status) use ($error_to_show) { return $error_to_show; }, 10, 2);

        } else {
            // It's ham
            oopspam_store_ham_submission($frmEntry);
        }
    }

    return $spam;
}
