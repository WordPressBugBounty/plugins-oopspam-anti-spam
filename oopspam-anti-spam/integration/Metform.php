<?php

namespace OOPSPAM\Integrations;

add_filter('mf_after_validation_check', 'OOPSPAM\Integrations\oopspamantispam_metform_validation_check', 10, 1);

function oopspamantispam_metform_validation_check($filter_validate)
{
    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');

    // Extract data from the filter
    $is_valid = $filter_validate['is_valid'] ?? true;
    $form_data = $filter_validate['form_data'] ?? [];
    $file_data = $filter_validate['file_data'] ?? [];

    // If validation already failed, return as is
    if (!$is_valid) {
        return $filter_validate;
    }

    if (!empty(oopspamantispam_get_key()) && oopspam_is_spamprotection_enabled('metform')) {
        
        // Get form ID from form data or context
        $form_id = $form_data['id'] ?? '';
        
        // Check if the form is excluded from spam protection
        if (isset($options['oopspam_metform_exclude_form']) && $options['oopspam_metform_exclude_form']) {
            $formIds = sanitize_text_field(trim($options['oopspam_metform_exclude_form']));
            // Split the IDs string into an array using the comma as the delimiter
            $excludedFormIds = array_map('trim', explode(',', $formIds));

            foreach ($excludedFormIds as $id) {
                // Don't check for spam for this form
                // Don't log under Valid Entries
                if ($form_id == $id) {
                    return $filter_validate;
                }
            }
        }

        $message = "";
        $email = "";
        $fields_info = [];

        // Get the form post object to extract field types
        $form_post = get_post($form_id);
        
        if ($form_post) {
            // Get form configuration from post meta
            $form_config = get_post_meta($form_id, '_elementor_data', true);
            
            if ($form_config) {
                $form_config = json_decode($form_config, true);
                $fields_info = extract_metform_fields($form_config);
            }
        }

        // Extract message content from form data
        // 1. Check if custom Form ID|Field ID pair is set for content field
        if (isset($options['oopspam_metform_content_field']) && $options['oopspam_metform_content_field']) {
            $nameOfTextareaField = sanitize_text_field(trim($options['oopspam_metform_content_field']));
            // Decode the JSON data into an associative array
            $jsonData = json_decode($nameOfTextareaField, true);
            $currentFormId = $form_id; 

            if(is_array($jsonData)) {
                foreach ($jsonData as $contentFieldPair) {
                    // Scan only for this form by matching Form ID
                    if ($contentFieldPair['formId'] == $currentFormId) {
                        $fieldIds = explode(',', $contentFieldPair['fieldId']);
    
                        foreach ($fieldIds as $fieldId) {
                            $fieldId = trim($fieldId);
                            if (isset($form_data[$fieldId])) {
                                $message .= ' ' . sanitize_textarea_field($form_data[$fieldId]) . ' ';
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

        // 2. Extract email using field type information (always run)
        if (empty($email) && !empty($fields_info)) {
            foreach ($form_data as $field_name => $field_value) {
                if (isset($fields_info[$field_name])) {
                    $field_type = $fields_info[$field_name]['type'];
                    
                    // Extract email from email field types
                    if (in_array($field_type, ['mf-email'])) {
                        $email = sanitize_email($field_value);
                        break; // Found email, no need to continue
                    }
                }
            }
        }

        // 3. If no custom field specified, use field type information to find content
        if (empty($message) && !empty($fields_info)) {
            // Process form data with field type information
            foreach ($form_data as $field_name => $field_value) {
                if (isset($fields_info[$field_name])) {
                    $field_type = $fields_info[$field_name]['type'];
                    
                    // Extract message from textarea and text field types
                    if (in_array($field_type, ['mf-textarea'])) {
                        $message = sanitize_textarea_field($field_value);
                        break; // Found textarea content, no need to continue
                    }
                }
            }
        }

        // 4. If still no message, concatenate text-like fields using field type info
        if (empty($message) && !empty($fields_info)) {
            foreach ($form_data as $field_name => $field_value) {
                if (is_string($field_value) && !empty($field_value) && isset($fields_info[$field_name])) {
                    $field_type = $fields_info[$field_name]['type'];
                    // Include text, textarea, and other content fields but exclude email/phone
                    if (in_array($field_type, ['mf-text', 'mf-textarea', 'mf-number'])) {
                        $message .= ' ' . sanitize_textarea_field($field_value) . ' ';
                    }
                }
            }
            $message = trim($message);
        }

        $userIP = "";
        if (!isset($privacyOptions['oopspam_is_check_for_ip']) || ($privacyOptions['oopspam_is_check_for_ip'] !== true && $privacyOptions['oopspam_is_check_for_ip'] !== 'on')) {
            $userIP = oopspamantispam_get_ip();
        }

        $escapedMsg = sanitize_textarea_field($message);
        $raw_entry = json_encode($form_data);
        $detectionResult = oopspamantispam_call_OOPSpam($escapedMsg, $userIP, $email, true, "metform");

        if (!isset($detectionResult["isItHam"])) {
            return $filter_validate;
        }

        $frmEntry = [
            "Score" => $detectionResult["Score"],
            "Message" => $escapedMsg,
            "IP" => $userIP,
            "Email" => $email,
            "RawEntry" => json_encode($form_data),
            "FormId" => $form_id,
        ];

        if (!$detectionResult["isItHam"]) {
            // It's spam, store the submission and prevent form submission
            oopspam_store_spam_submission($frmEntry, $detectionResult["Reason"]);

            // Get custom error message
            $error_to_show = isset($options['oopspam_metform_spam_message']) && !empty($options['oopspam_metform_spam_message']) 
                ? $options['oopspam_metform_spam_message'] 
                : "Your submission has been flagged as spam.";

            // Return validation failure
            return [
                'is_valid' => false,
                'message' => esc_html($error_to_show)
            ];

        } else {
            // It's ham, store as valid submission
            oopspam_store_ham_submission($frmEntry);
        }
    }

    return $filter_validate;
}

function extract_metform_fields($elements, &$fields = []) {
    foreach ($elements as $element) {
        if (isset($element['widgetType']) && strpos($element['widgetType'], 'mf-') === 0) {
            $settings = $element['settings'] ?? [];
            $field_name = $settings['mf_input_name'] ?? '';
            
            if ($field_name) {
                $fields[$field_name] = [
                    'type' => $element['widgetType'],
                    'label' => $settings['mf_input_label'] ?? '',
                    'settings' => $settings
                ];
            }
        }
        
        if (isset($element['elements']) && is_array($element['elements'])) {
            extract_metform_fields($element['elements'], $fields);
        }
    }
    
    return $fields;
}
