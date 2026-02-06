<?php
namespace OOPSPAM\Integrations;

add_filter( 'mc4wp_form_errors', function( array $errors, \MC4WP_Form $form ) {

    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');

    if (!empty(oopspamantispam_get_key()) && oopspam_is_spamprotection_enabled('mc4wp')) {

        // Capture the email address
        $email = isset($_POST['EMAIL']) ? sanitize_email($_POST['EMAIL']) : '';

        $data = $form->get_raw_data();

        $raw_entry = json_encode($data);
        $form_id = "MC4WP: Mailchimp for WordPress: "  . $data['_mc4wp_form_id'];
        $userIP = "";
        if (!isset($privacyOptions['oopspam_is_check_for_ip']) || ($privacyOptions['oopspam_is_check_for_ip'] !== true && $privacyOptions['oopspam_is_check_for_ip'] !== 'on')) {
            $userIP = oopspamantispam_get_ip();
        }

        $detectionResult = oopspamantispam_call_OOPSpam("", $userIP, $email, true, "mc4wp");

        if (!isset($detectionResult["isItHam"])) {
            return;
        }

        $frmEntry = [
            "Score" => $detectionResult["Score"],
            "Message" => "", // Since this is for MailChimp, we don't have a message field
            "IP" => $userIP,
            "Email" => $email,
            "RawEntry" => $raw_entry,
            "FormId" => $form_id,
        ];

        if (!$detectionResult["isItHam"]) {
            // It's spam, show error
            oopspam_store_spam_submission($frmEntry, $detectionResult["Reason"]);
            $errors[] = 'oopspam_spam';
            return $errors;
        } else {
            // It's ham, continue with the subscription
            oopspam_store_ham_submission($frmEntry);
        }
    }
    return $errors;
}, 10, 2 );

/**
* Registers an additional Mailchimp for WP error message to match our error code from above
*
* @return array Messages for the various error codes
*/
function oopspam_add_mc4wp_error_message($messages) {
    $options = get_option('oopspamantispam_settings');
    $error_to_show = (isset($options['oopspam_mc4wp_spam_message']) && !empty($options['oopspam_mc4wp_spam_message'])) ? $options['oopspam_mc4wp_spam_message'] : 'Your submission has been flagged as spam.';
    $messages['oopspam_spam'] = esc_html($error_to_show);
    return $messages;
}

add_filter('mc4wp_form_messages', 'OOPSPAM\Integrations\oopspam_add_mc4wp_error_message');