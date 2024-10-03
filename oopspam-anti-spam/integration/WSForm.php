<?php
add_filter('wsf_submit_validate', 'oopspamantispam_ws_pre_submission', 10, 3);

// Filter function
function oopspamantispam_ws_pre_submission($field_error_action_array, $post_mode, $submit)
{
    // Only process validation if the form is submitted and not saved
    if ( $post_mode !== 'submit' ) {
        return $field_error_action_array;
    }

    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');

    if (!empty($options['oopspam_api_key']) && !empty($options['oopspam_is_ws_activated'])) {

        $form_id = $submit->form_id;
        $field_id = "";
        $message = "";
        $email = "";
        $userIP = "";
        $raw_entry = "";

        // Check if the form is excluded from spam protection
        if (isset($options['oopspam_ws_exclude_form']) && $options['oopspam_ws_exclude_form']) {
            $formIds = sanitize_text_field(trim($options['oopspam_ws_exclude_form']));
            // Split the IDs string into an array using the comma as the delimiter
            $excludedFormIds = array_map('trim', explode(',', $formIds));

            foreach ($excludedFormIds as $id) {
                // Don't check for spam for this form
                // Don't log under Form Ham Entries
                if ($form_id == $id) {
                    return $field_error_action_array;
                }
            }
        }

        // Capture the email
        foreach ($submit->meta as $field) {

            if (isset($field["type"]) && $field["type"] === "email") {
                $email = $field["value"];
            }
        }


        // 1. Check if custom Form ID|Field ID pair is set for content field
        if (isset($options['oopspam_ws_content_field']) && $options['oopspam_ws_content_field']) {

            $nameOfTextareaField = sanitize_text_field(trim($options['oopspam_ws_content_field']));
            // Decode the JSON data into an associative array
            $jsonData = json_decode($nameOfTextareaField, true);
            // $currentFormId = $form_data['id']; 

            foreach ($jsonData as $contentFieldPair) {
                // Scan only for this form by matching Form ID
                if ($contentFieldPair['formId'] == $form_id) {
                    $fieldIds = explode(',', $contentFieldPair['fieldId']);

                    foreach ($submit->meta as $field) {
                        
                        if (!empty($field) && in_array($field['id'], $fieldIds)) {
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
            foreach ($submit->meta as $field) {
            if (!empty($field))
            if ($field["type"] === "textarea") {
                $message = $field["value"];
            }
        }
     }

        
        // 3. No textarea found, capture any text/name field
        if (empty($message)) {
            foreach ($submit->meta as $field) {
                if (!empty($field))
                if ($field["type"] === "text") {
                    $message = $field["value"];
                }
            }
        }               


        // Sanitize message field
        $escapedMsg = sanitize_textarea_field($message);

        // Capture user's IP if allowed
        if (!isset($privacyOptions['oopspam_is_check_for_ip']) || $privacyOptions['oopspam_is_check_for_ip'] != true) {
            $userIP = oopspamantispam_get_ip();
        }

        // Capture raw entry
        $raw_entry = json_encode($submit->meta);

        $detectionResult = oopspamantispam_call_OOPSpam($escapedMsg, $userIP, $email, true, "wsform");
        if (!isset($detectionResult["isItHam"])) {
            return $field_error_action_array;
        }
        $frmEntry = [
            "Score" => $detectionResult["Score"],
            "Message" => $escapedMsg,
            "IP" => $userIP,
            "Email" => $email,
            "RawEntry" => $raw_entry,
            "FormId" => $form_id,
        ];

        if (!$detectionResult["isItHam"]) {

            // It's spam, store the submission in Form Spam Entries
            oopspam_store_spam_submission($frmEntry, $detectionResult["Reason"]);
            $error_to_show = $options['oopspam_ws_spam_message'];
            $field_error_action_array[] = array(
                'action' => 'message',
                'message' => $error_to_show,
                'type' => 'danger'
            );
            return $field_error_action_array;
        } else {
            // It's ham
            oopspam_store_ham_submission($frmEntry);
            return $field_error_action_array;
        }

    }
    
    return $field_error_action_array;
};
