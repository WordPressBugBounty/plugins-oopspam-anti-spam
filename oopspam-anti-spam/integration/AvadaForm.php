<?php

namespace OOPSPAM\Integrations;

add_filter('fusion_form_submission_data', 'OOPSPAM\Integrations\oopspamantispam_avada_submission', 10, 2);

// Filter function
function oopspamantispam_avada_submission($form_data, $form_post_id)
{
    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');

    if (!empty(oopspamantispam_get_key()) && oopspam_is_spamprotection_enabled('avada')) {

        $form_id = isset($form_data['submission']['form_id']) ? $form_data['submission']['form_id'] : '';
        $message = "";
        $email = "";
        $userIP = "";
        
        // Check if the form is excluded from spam protection
        if (isset($options['oopspam_avada_exclude_form']) && $options['oopspam_avada_exclude_form']) {
            $formIds = sanitize_text_field(trim($options['oopspam_avada_exclude_form']));
            // Split the IDs string into an array using the comma as the delimiter
            $excludedFormIds = array_map('trim', explode(',', $formIds));

            foreach ($excludedFormIds as $id) {
                // Don't check for spam for this form
                // Don't log under Valid Entries
                if ($form_id == $id) {
                    return $form_data;
                }
            }
        }

        // Extract data from the form_data structure
        $form_fields = isset($form_data['data']) ? $form_data['data'] : array();
        $field_types = isset($form_data['field_types']) ? $form_data['field_types'] : array();

        // 1. Check if custom Form ID|Field ID pair is set for content field
        if (isset($options['oopspam_avada_content_field']) && $options['oopspam_avada_content_field']) {
            $nameOfTextareaField = sanitize_text_field(trim($options['oopspam_avada_content_field']));
            // Decode the JSON data into an associative array
            $jsonData = json_decode($nameOfTextareaField, true);

            if (is_array($jsonData)) {
                foreach ($jsonData as $contentFieldPair) {
                    // Scan only for this form by matching Form ID
                    if ($contentFieldPair['formId'] == $form_id) {
                        $fieldIds = explode(',', $contentFieldPair['fieldId']);

                        foreach ($fieldIds as $fieldId) {
                            $fieldId = trim($fieldId);
                            if (isset($form_fields[$fieldId])) {
                                $message .= $form_fields[$fieldId] . ' '; // Concatenate the field values with a space
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
            foreach ($form_fields as $field_name => $field_value) {
                if (isset($field_types[$field_name]) && $field_types[$field_name] === 'textarea') {
                    $message = $field_value;
                    break;
                }
            }
        }

        // 3. No textarea found, capture any text/message field
        if (empty($message)) {
            foreach ($form_fields as $field_name => $field_value) {
                // Capture all fields with 'text' type or fields with message-related names
                if ((isset($field_types[$field_name]) && $field_types[$field_name] === 'text')) {
                    $message .= $field_value . ' ';
                }
            }
            $message = trim($message);
        }

        // Capture the email field
        foreach ($form_fields as $field_name => $field_value) {
            if ((isset($field_types[$field_name]) && $field_types[$field_name] === 'email') || 
                stripos($field_name, 'email') !== false) {
                $email = sanitize_email($field_value);
                break;
            }
        }

        // Sanitize message field
        $escapedMsg = sanitize_textarea_field($message);

        // Capture user's IP if allowed
        if (!isset($privacyOptions['oopspam_is_check_for_ip']) || ($privacyOptions['oopspam_is_check_for_ip'] !== true && $privacyOptions['oopspam_is_check_for_ip'] !== 'on')) {
            $userIP = oopspamantispam_get_ip();
        }

        // Capture raw entry
        $raw_entry = json_encode($form_data);

        $detectionResult = oopspamantispam_call_OOPSpam($escapedMsg, $userIP, $email, true, "avada");
        
        if (!isset($detectionResult["isItHam"])) {
            return $form_data;
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
            // It's spam, store the submission
            oopspam_store_spam_submission($frmEntry, $detectionResult["Reason"]);
            
            $error_to_show = isset($options['oopspam_avada_spam_message']) && !empty($options['oopspam_avada_spam_message']) 
                ? $options['oopspam_avada_spam_message'] 
                : __('Your submission has been flagged as spam.', 'oopspam-anti-spam');
            
            // For Avada forms, prevent submission by stopping execution with error
            wp_die(esc_html($error_to_show), __('Spam Detected',  'oopspam-anti-spam'), array('response' => 403));
            
        } else {
            // It's ham
            oopspam_store_ham_submission($frmEntry);
        }
    }

    return $form_data;
}
