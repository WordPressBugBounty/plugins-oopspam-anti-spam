<?php

add_action('mailpoet_subscription_before_subscribe', 'oopspam_mailpoet_pre_subscription', 10, 3);

function oopspam_mailpoet_pre_subscription($subscriber_data, $subscriber, $form_data)
{
    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');

    if (!empty($options['oopspam_api_key']) && !empty($options['oopspam_is_mpoet_activated'])) {

        // Capture the email address
        $email = sanitize_email($subscriber_data['email']);

        $raw_entry = json_encode($subscriber_data);
        $form_id = "MailPoet: "  . sanitize_text_field($form_data->getName());
        $userIP = "";
        if (!isset($privacyOptions['oopspam_is_check_for_ip']) || $privacyOptions['oopspam_is_check_for_ip'] != true) {
            $userIP = oopspamantispam_get_ip();
        }

        $detectionResult = oopspamantispam_call_OOPSpam("", $userIP, $email, true, "mailpoet");

        if (!isset($detectionResult["isItHam"])) {
            return;
        }

        $frmEntry = [
            "Score" => $detectionResult["Score"],
            "Message" => "", // Since this is for MailPoet, we don't have a message field
            "IP" => $userIP,
            "Email" => $email,
            "RawEntry" => $raw_entry,
            "FormId" => $form_id,
        ];

        if (!$detectionResult["isItHam"]) {
            // It's spam, show error
            oopspam_store_spam_submission($frmEntry, $detectionResult["Reason"]);
            $error_to_show = $options['oopspam_mpoet_spam_message'];
            throw new \MailPoet\UnexpectedValueException($error_to_show);

        } else {
            // It's ham, continue with the subscription
            oopspam_store_ham_submission($frmEntry);
        }

    }
}