<?php

namespace OOPSPAM\Integrations;

add_filter('breakdance_form_run_action_email', 'OOPSPAM\Integrations\checkSpamBeforeEmail', 1, 3);

function checkSpamBeforeEmail($canExecute, $action, $form) {
    
    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');
    
    // Extract message and email from form data
    $message = '';
    $email = '';

    // Check common email field names first
    if (isset($form['fields']['email'])) {
        $email = sanitize_email($form['fields']['email']);
    } else {
        // If default email fields not found, look for any field containing 'email'
        foreach ($form['fields'] as $field_name => $field_value) {
            if (is_string($field_name) && stripos($field_name, 'email') !== false) {
                $email = sanitize_email($field_value);
                break;
            }
        }
    }

    // First try to get message from default 'message' field
    if (isset($form['fields']['message'])) {
        $message = $form['fields']['message'];
    } else {
        // Look for any field value that's longer than 20 characters (excluding email fields)
        foreach ($form['fields'] as $field_name => $field_value) {
            if (is_string($field_value) && 
                strlen($field_value) > 20 && 
                stripos($field_name, 'email') === false) {
                $message = $field_value;
                break;
            }
        }
    }

    // If custom content field is set, try to get message from that field
    if (isset($options['oopspam_bd_content_field']) && !empty($options['oopspam_bd_content_field'])) {
        $customFields = array_map('trim', explode(',', $options['oopspam_bd_content_field']));
        foreach ($customFields as $fieldName) {
            if (isset($form['fields'][$fieldName])) {
                $message = $form['fields'][$fieldName];
                break;
            }
        }
    }

    // Check if form is excluded
    if (isset($options['oopspam_bd_exclude_form'])) {
        $excludedFormIds = array_map('trim', explode(',', $options['oopspam_bd_exclude_form']));
        if (in_array($form['formId'], $excludedFormIds)) {
            return $canExecute;
        }
    }

    if (!empty(oopspamantispam_get_key())) {
        $userIP = '';
        if (!isset($privacyOptions['oopspam_is_check_for_ip']) || ($privacyOptions['oopspam_is_check_for_ip'] !== true && $privacyOptions['oopspam_is_check_for_ip'] !== 'on')) {
            $userIP = $form['ip'];
        }

        $escapedMsg = sanitize_textarea_field($message);
        $detectionResult = oopspamantispam_call_OOPSpam($escapedMsg, $userIP, $email, true, "breakdance");

        $frmEntry = [
            "Score" => $detectionResult["Score"],
            "Message" => $escapedMsg,
            "IP" => $userIP,
            "Email" => $email,
            "RawEntry" => json_encode($form['fields']),
            "FormId" => $form['formId'],
        ];

        if (isset($detectionResult["isItHam"]) && !$detectionResult["isItHam"]) {
            // Store spam submission
            oopspam_store_spam_submission($frmEntry, $detectionResult["Reason"]);

            // Prevent email from being sent
            return new \WP_Error('spam_detected', 'Email action prevented - submission was marked as spam.');
        } else {
            // It's ham
            oopspam_store_ham_submission($frmEntry);
        }
    }

    return $canExecute;
}