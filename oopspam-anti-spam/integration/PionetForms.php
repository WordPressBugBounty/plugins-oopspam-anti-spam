<?php

add_filter( 'piotnetforms/form_builder/validate_pre_submit_form', 'oopspamantispam_pionetf_pre_submission', 10, 4 );



function oopspamantispam_pionetf_pre_submission($custom_message, $fields, $form, $form_id)
{

    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');
    $message = "";
    $email = "";

    if (empty($fields)) {
        return;
    }


      // Check if the form is excluded from spam protection
      if (isset($options['oopspam_pionet_exclude_form']) && $options['oopspam_pionet_exclude_form']) {
        $formIds = sanitize_text_field(trim($options['oopspam_pionet_exclude_form']));
        // Split the IDs string into an array using the comma as the delimiter
        $excludedFormIds = array_map('trim', explode(',', $formIds));

        foreach ($excludedFormIds as $id) {
            // Don't check for spam for this form
            // Don't log under Form Ham Entries
            if ($form_id == $id) {
                return;
            }
        }
    }

    // Attempt to find textarea field name
    foreach ($fields as $field) {
        if (isset($field["type"]) && $field["type"] == "textarea") {
            $message = $field["value"];
        }
        if (isset($field["type"]) && $field["type"] == "email") {
            $email = sanitize_email($field["value"]);
        }
    }

    // Check if a custom Field ID is set
    if (isset($options['oopspam_pionet_content_field']) && $options['oopspam_pionet_content_field']) {
        $nameOfTextareaField = sanitize_text_field(trim($options['oopspam_pionet_content_field']));

        // Split the IDs string into an array using the comma as the delimiter
        $idsArray = array_map('trim', explode(',', $nameOfTextareaField));
        
        // Iterate through each ID to look for message field value
        foreach ($idsArray as $id) {
            // Capture the content
            foreach ($fields as $field) {
                if (isset($field["name"]) && $field["name"] == $id) {
                    $message = $field["value"];
                    break 2;
                }
            }
        }
    }

    if (!empty($options['oopspam_api_key']) && !empty($options['oopspam_is_pionet_activated'])) {

        $userIP = "";
        if (!isset($privacyOptions['oopspam_is_check_for_ip']) || $privacyOptions['oopspam_is_check_for_ip'] != true) {
            $userIP = oopspamantispam_get_ip();
        }
        $escapedMsg = sanitize_textarea_field($message);
        $raw_entry = json_encode($fields);
        $detectionResult = oopspamantispam_call_OOPSpam($escapedMsg, $userIP, $email, true, "pionet");
        if (!isset($detectionResult["isItHam"])) {
            return;
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

            // It's spam, store the submission and show error
            oopspam_store_spam_submission($frmEntry, $detectionResult["Reason"]);
            $error_to_show = $options['oopspam_pionet_spam_message'];
            return $error_to_show;
        } else {
            // It's ham
            oopspam_store_ham_submission($frmEntry);
            return;
        }

    }

    return;
}
