<?php

add_filter('bricks/form/validate', 'oopspam_spam_check', 10, 2);

function oopspam_spam_check($errors, $form)
{
    
    $fields = $form->get_fields();
    $formId = $fields['formId'];
    $settings = $form->get_settings();

    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');

    if (!empty($options['oopspam_api_key']) && !empty($options['oopspam_is_br_activated'])) {

          // Check if the form is excluded from spam protection
          if (isset($options['oopspam_br_exclude_form']) && $options['oopspam_br_exclude_form']) {
            $formIds = sanitize_text_field(trim($options['oopspam_br_exclude_form']));
            // Split the IDs string into an array using the comma as the delimiter
            $excludedFormIds = array_map('trim', explode(',', $formIds));

            foreach ($excludedFormIds as $id) {
                // Don't check for spam for this form
                // Don't log under Form Ham Entries
                if ($formId === $id) {
                    return $errors;
                }
            }
        }

        // Get email and content field Ids
        $msgFieldId = "";
        $emailFieldId = "";
        foreach ($settings['fields'] as $field) {
            if ($field["type"] == "email") {
                $emailFieldId = $field["id"];
            }
            if ($field["type"] == "textarea") {
                $msgFieldId = $field["id"];
            }
        }

        // In Bricks, every form field starts with "form-field-[ID]"
        $message = "";
        $email = "";

        // Capture the content
        if (!empty($msgFieldId)) {

            // The default textarea field value
            $message = sanitize_text_field($fields["form-field-" . $msgFieldId]);

            // and if a custom field id defined, then capture it instead
            if (isset($options['oopspam_br_content_field']) && $options['oopspam_br_content_field']) {
                $customContentField = sanitize_text_field(trim($options['oopspam_br_content_field']));
                $idsArray = array_map('trim', explode(',', $customContentField));

                // Iterate through each ID to look for message field value
                foreach ($idsArray as $id) {
                    // Capture the content
                    if (isset($fields["form-field-" . $id])) {
                        $message = $fields["form-field-" . $id];
                        break;
                    }
                }
                
            }

        }

        // Capture the email
        if (!empty($emailFieldId)) {
            $email = sanitize_email($fields["form-field-" . $emailFieldId]);
        }

        $raw_entry = json_encode($fields);
        $userIP = "";

        if (!isset($privacyOptions['oopspam_is_check_for_ip']) || $privacyOptions['oopspam_is_check_for_ip'] != true) {
            $userIP = oopspamantispam_get_ip();
        }

        $escapedMsg = sanitize_textarea_field($message);
        $detectionResult = oopspamantispam_call_OOPSpam($escapedMsg, $userIP, $email, true, "bricks");

        if (!isset($detectionResult["isItHam"])) {
            return $errors;
        }

        $frmEntry = [
            "Score" => $detectionResult["Score"],
            "Message" => $escapedMsg,
            "IP" => $userIP,
            "Email" => $email,
            "RawEntry" => $raw_entry,
            "FormId" => $formId,
        ];

        if (!$detectionResult["isItHam"]) {
            // It's spam, store the submission and show error
            oopspam_store_spam_submission($frmEntry, $detectionResult["Reason"]);

            $error_to_show = $options['oopspam_br_spam_message'];
            // Show error
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
            $errors[] = wp_kses($error_to_show , 'oopspam' );
            return $errors;
        } else {
            // It's ham
            oopspam_store_ham_submission($frmEntry);
            return $errors;
        }


    }

    return $errors;
}
