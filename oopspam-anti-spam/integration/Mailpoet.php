<?php
namespace OOPSPAM\Integrations;

add_action('mailpoet_subscription_before_subscribe', 'OOPSPAM\Integrations\oopspam_mailpoet_pre_subscription', 10, 3);

function oopspam_mailpoet_pre_subscription($subscriber_data, $subscriber, $form_data)
{
    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');

    if (!empty(oopspamantispam_get_key()) && oopspam_is_spamprotection_enabled('mpoet')) {
        // Capture the email address
        $email = sanitize_email($subscriber_data['email']);

        $raw_entry = json_encode($subscriber_data);
        $form_id = "MailPoet: "  . sanitize_text_field($form_data->getName());
        $userIP = "";
        if (!isset($privacyOptions['oopspam_is_check_for_ip']) || ($privacyOptions['oopspam_is_check_for_ip'] !== true && $privacyOptions['oopspam_is_check_for_ip'] !== 'on')) {
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
            $error_to_show = (isset($options['oopspam_mpoet_spam_message']) && !empty($options['oopspam_mpoet_spam_message'])) ? $options['oopspam_mpoet_spam_message'] : 'Your submission has been flagged as spam.';
            throw new \MailPoet\UnexpectedValueException(esc_html($error_to_show));
        } else {
            // It's ham, continue with the subscription
            oopspam_store_ham_submission($frmEntry);
        }
    }
}