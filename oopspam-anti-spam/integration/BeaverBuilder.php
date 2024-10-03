<?php

add_action('fl_module_contact_form_before_send', 'oopspamantispam_bb_pre_submission', 10, 5);

function oopspamantispam_bb_pre_submission($mailto, $subject, $template, $headers, $settings )
{

    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');

    if (!empty($options['oopspam_api_key']) && !empty($options['oopspam_is_bb_activated'])) {

        // Capture the content
        $message = "";
        if ( isset( $_POST['message'] ) ) {
            $message = $_POST['message'];
        }

        // Capture the email
        $email = "";
        if ( isset( $_POST['email'] ) ) {
            $email = $_POST['email'];
        }

        $raw_entry = json_encode($template);

        $userIP = "";
        if (!isset($privacyOptions['oopspam_is_check_for_ip']) || $privacyOptions['oopspam_is_check_for_ip'] != true) {
            $userIP = oopspamantispam_get_ip();
        }

        $escapedMsg = sanitize_textarea_field($message);
        $detectionResult = oopspamantispam_call_OOPSpam($escapedMsg, $userIP, $email, true, "bb");

        if (!isset($detectionResult["isItHam"])) {
            return;
        }

        $frmEntry = [
            "Score" => $detectionResult["Score"],
            "Message" => $escapedMsg,
            "IP" => $userIP,
            "Email" => $email,
            "RawEntry" => $raw_entry,
            "FormId" => $_POST["post_id"],
        ];

        if (!$detectionResult["isItHam"]) {
            // It's spam, store the submission and show error
            oopspam_store_spam_submission($frmEntry, $detectionResult["Reason"]);
            wp_die();
            
        } else {
            // It's ham
            oopspam_store_ham_submission($frmEntry);
        }

    }

    return;
}
