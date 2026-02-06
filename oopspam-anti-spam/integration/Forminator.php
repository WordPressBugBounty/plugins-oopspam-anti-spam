<?php

namespace OOPSPAM\Integrations;

add_filter('forminator_spam_protection', 'OOPSPAM\Integrations\oopspam_forminator_spam_check', 10, 4);

function oopspam_forminator_spam_check($is_spam, $field_data_array, $form_id, $form_type) {

    // If already marked as spam by another filter, don't override
    if ($is_spam) {
        return $is_spam;
    }

    // Only process custom forms
    if ($form_type !== 'form') {
        return $is_spam;
    }
    

    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');

    if (empty(oopspamantispam_get_key()) || !oopspam_is_spamprotection_enabled('forminator')) {
        return $is_spam;
    }

    $userIP = ''; 
    $email = ''; 
    $message = ''; 
    $raw_entry = json_encode($field_data_array);

    // 1. Check if custom Form ID|Field ID pair is set for content field
    if (isset($options['oopspam_forminator_content_field']) && $options['oopspam_forminator_content_field']) {
        $nameOfTextareaField = sanitize_text_field(trim($options['oopspam_forminator_content_field']));
        // Decode the JSON data into an associative array
        $jsonData = json_decode($nameOfTextareaField, true);
        $currentFormId = $form_id; 

        if(is_array($jsonData)) {
            foreach ($jsonData as $contentFieldPair) {
                // Scan only for this form by matching Form ID
                if ($contentFieldPair['formId'] == $currentFormId) {
                    $fieldIds = explode(',', $contentFieldPair['fieldId']);
    
                    foreach ($field_data_array as $field) {
                        if (!isset($field["field_type"])) continue;
                        if (in_array($field['name'], $fieldIds)) {
                            $message .= $field['value'] . ' '; // Concatenate the field values with a space
                        }
                    }
        
                    // Trim any extra spaces from the end of the message
                    $message = trim($message);
                    // Break the loop once the message is captured
                    break 1;
                }
            }
        }
    }

    // 2. Attempt to capture any textarea with its value
    if (empty($message)) {
        foreach ($field_data_array as $field) {
            if (!isset($field["field_type"])) continue;
            if ($field["field_type"] == "textarea") {
                $message = $field["value"];
                break 1;
            }
        }
    }

    // 3. No textarea found, capture any text/name field
    if (empty($message)) {
        foreach ($field_data_array as $field) {
            if (!isset($field["field_type"])) continue;
            if ($field["field_type"] == "text" || $field["field_type"] == "name") {
                $message = $field["value"];
                break 1;
            }
        }
    }

    $escapedMsg = sanitize_textarea_field($message);

    // Capture email
    foreach ($field_data_array as $field) {
        if (!isset($field["field_type"])) continue;

        if ($field["field_type"] == "email") {
            $email = sanitize_email($field["value"]);
            break;
        }
    }

    // Capture IP
    if (!isset($privacyOptions['oopspam_is_check_for_ip']) || ($privacyOptions['oopspam_is_check_for_ip'] !== true && $privacyOptions['oopspam_is_check_for_ip'] !== 'on')) {
        $userIP = oopspamantispam_get_ip();
    }

    // Perform spam check using OOPSpam
    $detectionResult = oopspamantispam_call_OOPSpam($escapedMsg, $userIP, $email, true, "forminator");

    if (!isset($detectionResult['isItHam'])) {
        return $is_spam;
    }

    $frmEntry = [
        "Score" => $detectionResult["Score"],
        "Message" => $escapedMsg,
        "IP" => $userIP,
        "Email" => $email,
        "RawEntry" => $raw_entry,
        "FormId" => $form_id,
    ];

    if (!$detectionResult['isItHam']) {
        // It's spam, store the submission
        oopspam_store_spam_submission($frmEntry, $detectionResult["Reason"]);
        return true; // Return true to mark as spam
    } else {
        // It's ham
        oopspam_store_ham_submission($frmEntry);
        return false; // Return false to allow submission
    }
}