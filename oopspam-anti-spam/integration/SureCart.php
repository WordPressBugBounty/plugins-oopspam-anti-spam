<?php

namespace OOPSPAM\Integrations;

add_filter('surecart/checkout/validate', 'OOPSPAM\Integrations\oopspamantispam_surecart_pre_order', 10, 3);

function oopspamantispam_surecart_pre_order( $errors, $args, $request ) {

    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');

    if (!empty(oopspamantispam_get_key()) && oopspam_is_spamprotection_enabled('surecart')) {
        $email = "";
        if (isset($args['email'])) {
            $email = sanitize_email($args['email']);
        }

        $userIP = "";
        if (!isset($privacyOptions['oopspam_is_check_for_ip']) || $privacyOptions['oopspam_is_check_for_ip'] != true) {
            $userIP = oopspamantispam_get_ip();
        }

        $raw_entry = json_encode($args);
        
        $detectionResult = oopspamantispam_call_OOPSpam("", $userIP, $email, true, "surecart");
        if (!isset($detectionResult["isItHam"])) {
            return $errors;
        }

        $frmEntry = [
            "Score" => $detectionResult["Score"],
            "Message" => "",
            "IP" => $userIP,
            "Email" => $email,
            "RawEntry" => $raw_entry,
            "FormId" => "SureCart",
        ];

        if (!$detectionResult["isItHam"]) {
            // It's spam, store the submission and show error
            oopspam_store_spam_submission($frmEntry, $detectionResult["Reason"]);
            $error_to_show = isset($options['oopspam_surecart_spam_message']) ? $options['oopspam_surecart_spam_message'] : __('Your order has been flagged as spam.', 'oopspam');
            $errors->add( 'blocked', $error_to_show );

        } else {
            // It's ham
            oopspam_store_ham_submission($frmEntry);
            return $submission_data;
        }
    }

    return $errors;
}
