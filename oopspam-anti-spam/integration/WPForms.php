<?php

add_action('wpforms_process', 'oopspamantispam_wpf_pre_submission', 10, 4);

function oopspamantispam_wpf_pre_submission($fields, $entry, $form_data)
{

    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');
    $message = "";
    $email = "";

    if (empty($fields)) {
        return;
    }

      // Capture the email field
        foreach ($fields as $field) {
        if ($field["type"] == "email") {
            $email = sanitize_email($field["value"]);
            break;
        }
    }

        // 1. Check if custom Form ID|Field ID pair is set for content field
        if (isset($options['oopspam_wpf_content_field']) && $options['oopspam_wpf_content_field']) {
            $nameOfTextareaField = sanitize_text_field(trim($options['oopspam_wpf_content_field']));
            // Decode the JSON data into an associative array
            $jsonData = json_decode($nameOfTextareaField, true);
            $currentFormId = $form_data['id']; 

            foreach ($jsonData as $contentFieldPair) {
                // Scan only for this form by matching Form ID
                if ($contentFieldPair['formId'] == $currentFormId) {
                    $fieldIds = explode(',', $contentFieldPair['fieldId']);

                    foreach ($fields as $field) {
                        if (in_array($field['id'], $fieldIds)) {
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
        foreach ($fields as $field) {
            if ($field["type"] == "textarea") {
                $message = $field["value"];
            }
        }
    }

        
    // 3. No textarea found, capture any text/name field
    if (empty($message)) {
        foreach ($fields as $field) {
            if ($field["type"] == "text" || $field["type"] == "name") {
                $message = $field["value"];
            }
        }
    }

    if (!empty($options['oopspam_api_key']) && !empty($options['oopspam_is_wpf_activated'])) {
        
        // Check if the form is excluded from spam protection
        if (isset($options['oopspam_wpf_exclude_form']) && $options['oopspam_wpf_exclude_form']) { 
            $formIds = sanitize_text_field(trim($options['oopspam_wpf_exclude_form']));

            // Split the IDs string into an array using the comma as the delimiter
            $excludedFormIds = array_map('trim', explode(',', $formIds));

            foreach ($excludedFormIds as $id) {
                // Don't check for spam for this form
                // Don't log under Form Ham Entries
                if ($form_data['id'] == $id) {
                    return;
                }
            }
        }

        $userIP = "";
        if (!isset($privacyOptions['oopspam_is_check_for_ip']) || $privacyOptions['oopspam_is_check_for_ip'] != true) {
            $userIP = wpforms_get_ip();
        }
        $escapedMsg = sanitize_textarea_field($message);
        $raw_entry = json_encode($fields);
        $detectionResult = oopspamantispam_call_OOPSpam($escapedMsg, $userIP, $email, true, "wpforms");
        if (!isset($detectionResult["isItHam"])) {
            return;
        }
        $frmEntry = [
            "Score" => $detectionResult["Score"],
            "Message" => $escapedMsg,
            "IP" => $userIP,
            "Email" => $email,
            "RawEntry" => $raw_entry,
            "FormId" => $form_data['id'],
        ];

        if (!$detectionResult["isItHam"]) {

            // It's spam, store the submission and show error
            oopspam_store_spam_submission($frmEntry, $detectionResult["Reason"]);
            $error_to_show = $options['oopspam_wpf_spam_message'];
            $allowedEls = array(
                'a' => array(
                    'href' => array(),
                    'title' => array()
                ),
                'br' => array(),
                'em' => array(),
                'strong' => array(),
                'i' => array(),
                'u' => array()
            );
            wpforms()->process->errors[$form_data['id']]['footer'] = wp_kses($error_to_show, $allowedEls);
        } else {
            // It's ham
            oopspam_store_ham_submission($frmEntry);
        }

    }

    return;
}
