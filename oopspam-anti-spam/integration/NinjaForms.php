<?php
namespace OOPSPAM\Integrations;

add_filter('ninja_forms_submit_data', 'OOPSPAM\Integrations\oopspamantispam_forms_after_submission');

function oopspamantispam_forms_after_submission($form_data)
{

    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');

    if (!empty(oopspamantispam_get_key()) && oopspam_is_spamprotection_enabled('nj')) {
        $message = "";
        $field_id = "";
        $email = "";

        // Default Field Key (aka ID) starts with "textarea"
        $keyToLook = "textarea";

        // Check if the form is excluded from spam protection
        if (isset($options['oopspam_nj_exclude_form']) && $options['oopspam_nj_exclude_form']) {
            $formIds = sanitize_text_field(trim($options['oopspam_nj_exclude_form']));
            // Split the IDs string into an array using the comma as the delimiter
            $excludedFormIds = array_map('trim', explode(',', $formIds));

            foreach ($excludedFormIds as $id) {
                // Don't check for spam for this form
                // Don't log under Valid Entries
                if ($form_data["id"] == $id) {
                    return $form_data;
                }
            }
        }

        // Check if the option is not empty
        if (!empty($options['oopspam_nj_content_field'])) {
            $keyToLook = sanitize_text_field(trim($options['oopspam_nj_content_field']));
        }
        
        $idsArray = array_map('trim', explode(',', $keyToLook));
        
        foreach ($form_data['fields'] as $field) {
            // Capture the content
            foreach ($idsArray as $id) {
                if (strpos($field['key'], $id) !== false) {
                    $message = $field['value'];
                    $field_id = $field['id'];
                    if (!empty($email)) {
                        break 2; // Break out of both loops
                    }
                }
            }
        
            // Capture the email field
            if (strpos($field['key'], "email") !== false && empty($email)) {
                $email = sanitize_email($field['value']);
                if (!empty($message)) {
                    break;
                }
            }
        
            // Look for textarea field only
            if (strpos($field['key'], $keyToLook) !== false) {
                $message = $field['value'];
                $field_id = $field['id'];
                // Use early return to exit the loop
                if (!empty($email)) {
                    break;
                }
            }
        }        

        $userIP = "";
        if (!isset($privacyOptions['oopspam_is_check_for_ip']) || ($privacyOptions['oopspam_is_check_for_ip'] !== true && $privacyOptions['oopspam_is_check_for_ip'] !== 'on')) {
            $userIP = oopspamantispam_get_ip();
        }

        $escapedMsg = sanitize_textarea_field($message);
        $raw_entry = json_encode($form_data['fields']);
        $detectionResult = oopspamantispam_call_OOPSpam($escapedMsg, $userIP, $email, true, "ninja");
        if (!isset($detectionResult["isItHam"])) {
            return $form_data;
        }
        $frmEntry = [
            "Score" => $detectionResult["Score"],
            "Message" => $escapedMsg,
            "IP" => $userIP,
            "Email" => $email,
            "RawEntry" => $raw_entry,
            "FormId" => $form_data["id"],
        ];

        if (!$detectionResult["isItHam"]) {
            // It's spam, store the submission and show error
            oopspam_store_spam_submission($frmEntry, $detectionResult["Reason"]);

            $error_to_show = isset($options['oopspam_nj_spam_message']) && !empty($options['oopspam_nj_spam_message']) ? $options['oopspam_nj_spam_message'] : "Your submission has been flagged as spam.";

            // Content field isn't available. Capture first item's ID from the array to show the error.
            if (empty($field_id)) {
                $field_id = array_values($form_data['fields'])[0]['id'];
            }
            // Preserve the original form_data structure and add errors properly
            if (!isset($form_data['errors'])) {
                $form_data['errors'] = [];
            }
            if (!isset($form_data['errors']['fields'])) {
                $form_data['errors']['fields'] = [];
            }
            
            $form_data['errors']['fields'][$field_id] = esc_html($error_to_show);
            
            return $form_data;
        } else {
            // It's ham
            oopspam_store_ham_submission($frmEntry);
        }
    }
    return $form_data;
}
