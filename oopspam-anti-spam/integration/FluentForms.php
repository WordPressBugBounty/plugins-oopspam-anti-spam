<?php

add_action('fluentform/before_insert_submission', 'oopspamantispam_ff_pre_submission', 20, 3);

function oopspamantispam_ff_pre_submission($insertData, $data, $form)
{
   
    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');
    $fields = json_decode($form->form_fields, true);
    $email = "";
    $message = "";

    // Default "textarea" field name in Fluent Forms is "description"
    $nameOfTextareaField = "description";

    // 1. Check if custom Form ID|Field name pair is set for content field
    if (isset($options['oopspam_ff_content_field']) && $options['oopspam_ff_content_field']) {
        $nameOfTextareaField = sanitize_text_field(trim($options['oopspam_ff_content_field']));
        // Decode the JSON data into an associative array
        $jsonData = json_decode($nameOfTextareaField, true);
        $currentFormId = $insertData["form_id"]; 

        foreach ($jsonData as $contentFieldPair) {
            // Scan only for this form by matching Form ID
            if ($contentFieldPair['formId'] == $currentFormId) {
                $fieldIds = explode(',', $contentFieldPair['fieldId']);

                foreach ($fieldIds as $fieldId) {
                    $fieldId = trim($fieldId);
                    if (isset($data[$fieldId])) {
                        $message .= $data[$fieldId] . ' '; // Concatenate the field values with a space
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
        
        // Use default textarea field name first
        $nameOfTextareaField = "description";
            if (isset($data[$nameOfTextareaField])) {
                $message = $data[$nameOfTextareaField];
            } else {
                // Capture the textarea field name and value
                foreach ($fields["fields"] as $field) {
                    if (isset($field["attributes"]["type"]) && $field["attributes"]["type"] == "textarea") {
                        $nameOfTextareaField = $field["attributes"]["name"];
                    }
                }
                // error_log($nameOfTextareaField);
                if ($nameOfTextareaField != "description")
                $message = $data[$nameOfTextareaField];
            }
    }

        
    // 3. No textarea found, capture any text/name field
    if (empty($message)) {
        foreach ($fields["fields"] as $field) {
            if (isset($field["attributes"]["type"]) && $field["attributes"]["type"] == "text") {
                $message = $data[$field["attributes"]["name"]];
            }
        }
    }


    // Capture the email
    if (isset($data["email"])) {
        $email = sanitize_email($data["email"]);
    }


    if (!empty($options['oopspam_api_key']) && !empty($options['oopspam_is_ff_activated'])) {

         // Check if the form is excluded from spam protection
        if (isset($options['oopspam_ff_exclude_form']) && $options['oopspam_ff_exclude_form']) {
            $formIds = sanitize_text_field(trim($options['oopspam_ff_exclude_form']));
            // Split the IDs string into an array using the comma as the delimiter
            $excludedFormIds = array_map('trim', explode(',', $formIds));
    
            foreach ($excludedFormIds as $id) {
                // Don't check for spam for this form
                // Don't log under Form Ham Entries
                if ($insertData["form_id"] == $id) {
                    return;
                }
            }
        }

        $userIP = "";
        if (!isset($privacyOptions['oopspam_is_check_for_ip']) || $privacyOptions['oopspam_is_check_for_ip'] != true) {
            $userIP = $insertData["ip"];
        }

        $escapedMsg = sanitize_textarea_field($message);
        $detectionResult = oopspamantispam_call_OOPSpam($escapedMsg, $userIP, $email, true, "fluent");
        $raw_entry = json_encode($data);

        if (!isset($detectionResult["isItHam"])) {
            return;
        }

        $frmEntry = [
            "Score" => $detectionResult["Score"],
            "Message" => $escapedMsg,
            "IP" => $userIP,
            "Email" => $email,
            "RawEntry" => $raw_entry,
            "FormId" => $insertData["form_id"],
        ];

        if (!$detectionResult["isItHam"]) {

            // It's spam, store the submission and show error
            oopspam_store_spam_submission($frmEntry, $detectionResult["Reason"]);

            $error_to_show = $options['oopspam_ff_spam_message'];
            wp_send_json(['errors' => [
                'restricted' => [
                    $error_to_show,
                ],
            ]], 422);
        } else {
            // It's ham
            oopspam_store_ham_submission($frmEntry);
        }
    }

    return;

}
