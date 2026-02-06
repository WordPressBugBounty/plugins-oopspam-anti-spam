<?php

namespace OOPSPAM\Integrations;

add_filter('happyforms_validate_submission', 'OOPSPAM\Integrations\oopspamantispam_happyforms_pre_submission', 1, 3);

// Filter function
function oopspamantispam_happyforms_pre_submission($is_valid, $request, $_form)
{

    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');

    if (!empty(oopspamantispam_get_key()) && oopspam_is_spamprotection_enabled('happyforms')) {

        $form_id = $request['happyforms_form_id'];
        $message = "";
        $email = "";
        $userIP = "";
        $raw_entry = "";

        // Check if the form is excluded from spam protection
        if (isset($options['oopspam_happyforms_exclude_form']) && $options['oopspam_happyforms_exclude_form']) {
            $formIds = sanitize_text_field(trim($options['oopspam_happyforms_exclude_form']));
            // Split the IDs string into an array using the comma as the delimiter
            $excludedFormIds = array_map('trim', explode(',', $formIds));

            foreach ($excludedFormIds as $id) {
                // Don't check for spam for this form
                // Don't log under Valid Entries
                if ($form_id == $id) {
                    return $is_valid;
                }
            }
        }

        // Capture the email
        foreach ($request as $field_key => $field_value) {
            if (strpos($field_key, 'email') !== false && empty($email)) {
                $email = sanitize_email($field_value);
            }
        }

        // TODO: HappyForms doesn't support Field id mapping

        // 1. Check if custom Form ID|Field ID pair is set for content field
        // if (isset($options['oopspam_happyforms_content_field']) && $options['oopspam_happyforms_content_field']) {

        //     $nameOfTextareaField = sanitize_text_field(trim($options['oopspam_happyforms_content_field']));
        //     // Decode the JSON data into an associative array
        //     $jsonData = json_decode($nameOfTextareaField, true);

        //     if(is_array($jsonData)) {
        //         foreach ($jsonData as $contentFieldPair) {
        //             // Scan only for this form by matching Form ID
        //             if ($contentFieldPair['formId'] == $form_id) {
        //                 $fieldIds = explode(',', $contentFieldPair['fieldId']);
    
        //                 foreach ($request as $field_key => $field_value) {
        //                     if (!empty($field_value) && strpos($field_key, "multi_line_text") !== false) {
        //                         $message .= $field_value . ' '; // Concatenate the field values with a space
        //                     }
        //                 }
    
        //                 // Trim any extra spaces from the end of the message
        //                 $message = trim($message);
        //                 // Break the loop once the message is captured
        //                 break 1;
        //             }
        //         }
        //     }
        // }

        // 2. Attempt to capture any textarea with its value
        if (empty($message)) {
            foreach ($request as $field_key => $field_value) {
                if (!empty($field_value) && strpos($field_key, "multi_line_text") !== false) {
                    $message = $field_value;
                }
            }
        }

        // 3. No textarea found, capture any text/name field
        if (empty($message)) {
            foreach ($request as $field_key => $field_value) {
                if (!empty($field_value) && strpos($field_key, "single_line_text") !== false) {
                    $message .= $field_value;
                }
            }
        }

        // Sanitize message field
        $escapedMsg = sanitize_textarea_field($message);

        // Capture user's IP if allowed
        if (!isset($privacyOptions['oopspam_is_check_for_ip']) || ($privacyOptions['oopspam_is_check_for_ip'] !== true && $privacyOptions['oopspam_is_check_for_ip'] !== 'on')) {
            $userIP = oopspamantispam_get_ip();
        }

        // Capture raw entry
        $raw_entry = json_encode($request);

        $detectionResult = oopspamantispam_call_OOPSpam($escapedMsg, $userIP, $email, true, "happyforms");
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
            // It's spam, store the submission in Spam Entries
            oopspam_store_spam_submission($frmEntry, $detectionResult["Reason"]);
            $error_to_show = isset($options['oopspam_happyforms_spam_message']) && !empty($options['oopspam_happyforms_spam_message']) 
            ? $options['oopspam_happyforms_spam_message'] 
            : __('Your submission has been flagged as spam.', 'oopspam-anti-spam');
            
            wp_send_json_error(array(
                    'html' => '<div class="happyforms-form happyforms-styles">
                                <form action="" method="post" novalidate="true">
                                <div class="happyforms-flex"><div class="happyforms-message-notices">
                                <div class="happyforms-message-notice error">
                                <h2>' . esc_html($error_to_show) . '</h2></div></div>
                                </form></div>'
                ));
            $is_valid = false;
            return $is_valid;
        } else {
            // It's ham
            oopspam_store_ham_submission($frmEntry);
            return $is_valid;
        }
    }

    return $is_valid;
}
