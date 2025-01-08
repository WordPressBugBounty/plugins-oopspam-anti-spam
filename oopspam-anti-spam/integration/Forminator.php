<?php

namespace OOPSPAM\Integrations;

add_action('forminator_custom_form_submit_before_set_fields', 'OOPSPAM\Integrations\oopspam_forminator_pre_submission', 10, 3);

function oopspam_forminator_pre_submission($entry, $form_id, $field_data_array) {
    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');

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

    if (!empty($options['oopspam_api_key']) && !empty($options['oopspam_is_forminator_activated'])) {
        $escapedMsg = sanitize_textarea_field($message);

        // Capture message and email
        foreach ($field_data_array as $field) {
            if (!isset($field["field_type"])) continue;

            if ($field["field_type"] == "email") {
                $email = sanitize_email($field["value"]);
            }
        }

        // Capture IP
        if (!isset($privacyOptions['oopspam_is_check_for_ip']) || $privacyOptions['oopspam_is_check_for_ip'] != true) {
            $userIP = oopspamantispam_get_ip();
        }

        // Perform spam check using OOPSpam
        $detectionResult = oopspamantispam_call_OOPSpam($escapedMsg, $userIP, $email, true, "forminator");

        if (!isset($detectionResult['isItHam'])) {
            return $entry;
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
            // It's spam, store the submission and show error
            oopspam_store_spam_submission($frmEntry, $detectionResult["Reason"]);
            $error_to_show = $options['oopspam_forminator_spam_message'];
            wp_send_json_error($error_to_show);
        } else {
            // It's ham
            oopspam_store_ham_submission($frmEntry);
        }
    }

    return $entry;
}