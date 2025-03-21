<?php

namespace OOPSPAM\Integrations;

add_filter('registration_errors', 'OOPSPAM\Integrations\oopspamantispam_validate_email', 10, 3);

function oopspamantispam_validate_email($errors, $sanitized_user_login, $user_email)
{

    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');

    if (!empty($user_email) && !empty(oopspamantispam_get_key()) && oopspam_is_spamprotection_enabled('wpregister')) {

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
            $error_to_show = isset($options['oopspam_wpregister_spam_message']) ? $options['oopspam_wpregister_spam_message'] : __('Your submission has been flagged as spam.', 'oopspam');
            $errors->add('oopspam_error', __($error_to_show, 'oopspam'));
            return $errors;
        } else {
            // It's ham
            oopspam_store_ham_submission($frmEntry);
        }
    }

    return $errors;

}
