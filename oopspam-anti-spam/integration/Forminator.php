<?php

add_action('forminator_custom_form_submit_before_set_fields', 'oopspam_forminator_pre_submission', 10, 3);

function oopspam_forminator_pre_submission($entry, $form_id, $field_data_array) {

    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');

    if (!empty($options['oopspam_api_key']) && !empty($options['oopspam_is_forminator_activated'])) {
        
        $userIP = '' ; $email = ''; $escapedMsg = ''; $raw_entry = json_encode($field_data_array);

        // Capture message and email
        foreach ($field_data_array as $field) {
            if (!isset($field["field_type"])) continue;

            if ($field["field_type"] == "email") {
                $email = sanitize_email($field["value"]);
            }
            if ($field["field_type"] == "textarea" && empty($escapedMsg)) {
                $escapedMsg = sanitize_textarea_field($field["value"]);
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