<?php

namespace OOPSPAM\Integrations;

add_action('give_checkout_error_checks', 'OOPSPAM\Integrations\oopspamantispam_givewp_pre_submission', 10, 1);

function oopspamantispam_givewp_pre_submission($data)
{
 
    // Sanitize Posted Data.
    $post_data = give_clean($_POST);

    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');

    if (!empty(oopspamantispam_get_key()) && oopspam_is_spamprotection_enabled('give')) {

        $email = "";
        $userIP = "";
        $raw_entry = "";
        $escapedMsg = "";
        $form_id = "";

        
        if (isset($post_data['give_first'])) {
            $escapedMsg .= give_clean($post_data['give_first']) . ' ';
        }
        if (isset($post_data['give_last'])) {
            $escapedMsg .= give_clean($post_data['give_last']) . ' ';
        }
        if (isset($post_data['give_comment'])) {
            $escapedMsg .= give_clean($post_data['give_comment']);
        }
        $escapedMsg = trim($escapedMsg);

        if (isset($post_data['give-form-id'])) {
            $form_id = absint($post_data['give-form-id']);
        }

        if (isset($post_data["give_email"])) {
            $email = sanitize_email($post_data["give_email"]);
        }
        if (is_array($post_data)) {
            $raw_entry = json_encode($post_data);
        }

        if (!isset($privacyOptions['oopspam_is_check_for_ip']) || $privacyOptions['oopspam_is_check_for_ip'] != true) {
            $userIP = give_get_ip();
        }

        // Flag as spam when used gateway and enabled gateway mismatches
        if (!isGatewayEnabled($post_data["give-gateway"])) {
            $frmEntry = [
                "Score" => 6,
                "Message" => $escapedMsg,
                "IP" => $userIP,
                "Email" => $email,
                "RawEntry" => $raw_entry,
                "FormId" => $form_id,
            ];
            oopspam_store_spam_submission($frmEntry, "Gateway mismatch");
            $error_to_show = (isset($options['oopspam_give_spam_message']) && !empty($options['oopspam_give_spam_message'])) ? $options['oopspam_give_spam_message'] : 'Your submission has been flagged as spam.';
            give_set_error('give_message', $error_to_show);
            return $data;
        }

        $detectionResult = oopspamantispam_call_OOPSpam("", $userIP, $email, true, "give");

        if (!isset($detectionResult["isItHam"])) {
            return $data;
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
            $error_to_show = (isset($options['oopspam_give_spam_message']) && !empty($options['oopspam_give_spam_message'])) ? $options['oopspam_give_spam_message'] : 'Your submission has been flagged as spam.';
            give_set_error('give_message', $error_to_show);
        } else {
            // It's ham
            oopspam_store_ham_submission($frmEntry);
            return $data;
        }
    }

    return $data;
}

// Check if the donation payment method is matches with the enabled gateways
function isGatewayEnabled($payment_gateway) {
    $gateways = give_get_enabled_payment_gateways();
    return array_key_exists($payment_gateway, $gateways);
}
