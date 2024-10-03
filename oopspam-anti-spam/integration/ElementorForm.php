<?php

add_action('elementor_pro/forms/validation', 'oopspamantispam_el_pre_submission', 10, 2);

function oopspamantispam_el_pre_submission($record, $ajax_handler)
{

    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');

    // get password types and later remove them from $raw_entry so that it's not stored locally
    $password_fields = $record->get_field([
        'type' => 'password',
    ]);

    $text_fields = $record->get_field([
        'type' => 'textarea',
    ]);

    $email_fields = $record->get_field([
        'type' => 'email',
    ]);

    $field = current($text_fields);
    $email_field = current($email_fields);

    if (!empty($options['oopspam_api_key']) && !empty($options['oopspam_is_el_activated'])) {

        $form_id = $record->get('form_settings');
        
          // Check if the form is excluded from spam protection
          if (isset($options['oopspam_el_exclude_form']) && $options['oopspam_el_exclude_form']) {
            $formIds = sanitize_text_field(trim($options['oopspam_el_exclude_form']));
            // Split the IDs string into an array using the comma as the delimiter
            $excludedFormIds = array_map('trim', explode(',', $formIds));

            foreach ($excludedFormIds as $id) {
                // Don't check for spam for this form
                // Don't log under Form Ham Entries
                if ($form_id["form_name"] === $id) {
                    return;
                }
            }
        }

        // Capture the content
        $message = "";
        // 1. Check if custom Form ID|Field ID pair is set for content field
        if (isset($options['oopspam_el_content_field']) && $options['oopspam_el_content_field']) {
            $nameOfTextareaField = sanitize_text_field(trim($options['oopspam_el_content_field']));
            // Decode the JSON data into an associative array
            $jsonData = json_decode($nameOfTextareaField, true);
            $currentFormId = $form_id["form_name"]; 

            foreach ($jsonData as $contentFieldPair) {
                // Scan only for this form by matching Form ID
                if ($contentFieldPair['formId'] === $currentFormId) {
                    $fieldIds = explode(',', $contentFieldPair['fieldId']);
                   
                    foreach ($fieldIds as $fieldId) {
                       $matchingID = $record->get_field([
                            'id' => trim($fieldId),
                        ]);
                        if (!empty($matchingID)) {
                            $message .= current($matchingID)['value'] . ' '; // Concatenate the field values with a space
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
        if (empty($message) && !empty($field)) {
            $message = $field["value"];
    }

        
    // 3. No textarea found, capture any text/name field
    if (empty($message)) {
        $any_textfield = $record->get_field([
            'type' => 'text',
        ]);
        if(!empty($any_textfield)) {
            $message = current($any_textfield)['value'];
        }
    }

        // Capture the email
        $email = "";
        if (is_array($email_field) && !empty($email_field)) {
            $email = $email_field['value'];
        }


        $raw_entry = $record->get('sent_data');

        // Remove any password fields
        if($password_fields) {
            foreach ($password_fields as $field => $field_val) {
                unset($raw_entry[$field]);
            }
        }
        $raw_entry = json_encode($raw_entry);


        
        $userIP = "";

        if (!isset($privacyOptions['oopspam_is_check_for_ip']) || $privacyOptions['oopspam_is_check_for_ip'] != true) {
            $userIP = \ElementorPro\Core\Utils::get_client_ip();
        }

        $escapedMsg = sanitize_textarea_field($message);
        $detectionResult = oopspamantispam_call_OOPSpam($escapedMsg, $userIP, $email, true, "elementor");

        if (!isset($detectionResult["isItHam"])) {
            return;
        }

        $frmEntry = [
            "Score" => $detectionResult["Score"],
            "Message" => $escapedMsg,
            "IP" => $userIP,
            "Email" => $email,
            "RawEntry" => $raw_entry,
            "FormId" => $form_id["form_name"],
        ];

        if (!$detectionResult["isItHam"]) {
            // It's spam, store the submission and show error
            oopspam_store_spam_submission($frmEntry, $detectionResult["Reason"]);

            $field_id = "";
            // TODO: Show general error rather than field specific
            // Content field isn't available. Capture first item's ID from the array to show the error.
            if (empty($field['id'])) {
                $field_id = array_keys($record->get('sent_data'))[0];
            } else {
                $field_id = $field['id'];
            }

            $error_to_show = $options['oopspam_el_spam_message'];
            $ajax_handler->add_error($field_id, $error_to_show);
        } else {
            // It's ham
            oopspam_store_ham_submission($frmEntry);
        }

    }

    return;
}
