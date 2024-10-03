<?php

add_filter('registration_errors', 'oopspamantispam_validate_email', 10, 3);

function oopspamantispam_validate_email($errors, $sanitized_user_login, $user_email)
{

    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');

    if (!empty($user_email) && !empty($options['oopspam_api_key']) && !empty($options['oopspam_is_wpregister_activated'])) {

        $userIP = "";
        if (!isset($privacyOptions['oopspam_is_check_for_ip']) || $privacyOptions['oopspam_is_check_for_ip'] != true) {
            $userIP = oopspamantispam_get_ip();
        }

        $detectionResult = oopspamantispam_call_OOPSpam("", $userIP, $user_email, true, "wpregister");
        if (!isset($detectionResult["isItHam"])) {
            return $errors;
        }
        $frmEntry = [
            "Score" => $detectionResult["Score"],
            "Message" => "",
            "IP" => $userIP,
            "Email" => $user_email,
            "RawEntry" => json_encode(array($sanitized_user_login, $user_email)),
            "FormId" => "WP Registration",
        ];

        if (!$detectionResult["isItHam"]) {

            // It's spam, store the submission and show error
            oopspam_store_spam_submission($frmEntry, $detectionResult["Reason"]);
            $error_to_show = $options['oopspam_wpregister_spam_message'];
            $errors->add('oopspam_error', __($error_to_show, 'oopspam'));
            return $errors;
        } else {
            // It's ham
            oopspam_store_ham_submission($frmEntry);
        }
    }

    return $errors;

}
