<?php
add_filter('cred_form_validate', 'oopspam_toolset_pre_submission', 10, 2);

function oopspam_toolset_pre_submission($error_fields, $form_data)
{

    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');
    $email = "";
    $userIP = "";
    $message = "";

    list($fields, $errors) = $error_fields;

    // Capture email field
    foreach ($fields as $field) { // Field settings, including the field key and value.
        if (strpos($field['type'], "email") !== false && empty($email)) {
            $email = sanitize_email($field['value']);
            if (!empty($message)) {
                break;
            }

        }
        // Capture textarea field
        if (strpos($field['type'], "textarea") !== false) {
            $message = sanitize_textarea_field($field['value']);
            if (!empty($email)) {
                break;
            }

        }
    }

    // Capture IP
    if (!isset($privacyOptions['oopspam_is_check_for_ip']) || $privacyOptions['oopspam_is_check_for_ip'] != true) {
        $userIP = oopspamantispam_get_ip();
    }

    $raw_entry = json_encode($fields);
    $detectionResult = oopspamantispam_call_OOPSpam($message, $userIP, $email, true, "toolset");
    if (!isset($detectionResult["isItHam"])) {
        return array($fields, $errors);
    }
    $frmEntry = [
        "Score" => $detectionResult["Score"],
        "Message" => $message,
        "IP" => $userIP,
        "Email" => $email,
        "RawEntry" => $raw_entry,
        "FormId" => $form_data["id"],
    ];

    if (!$detectionResult["isItHam"]) {

        // It's spam, store the submission and show error
        oopspam_store_spam_submission($frmEntry, $detectionResult["Reason"]);

        $error_to_show = $options['oopspam_ts_spam_message'];

        // Get first element in the form to display error message
        $keys = array_keys($fields);
        $errors[$keys[0]] = $error_to_show;

    } else {
        // It's ham
        oopspam_store_ham_submission($frmEntry);
    }

    return array($fields, $errors);

};
