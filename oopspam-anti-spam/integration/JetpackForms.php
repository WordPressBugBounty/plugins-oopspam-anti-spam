<?php

namespace OOPSPAM\Integrations;

add_filter( 'jetpack_contact_form_is_spam', 'OOPSPAM\Integrations\oopspam_contact_form_is_spam_jetpack', 11, 2 );

function oopspam_contact_form_is_spam_jetpack($_is_spam, $form) {

    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');
    $message = "";
    $email = "";
    $userIP = "";

    if (!empty($options['oopspam_api_key']) && !empty($options['oopspam_is_jform_activated'])) { 

        // Capture the content
        $message = isset($form['comment_content']) ? sanitize_textarea_field($form['comment_content']) : "";
        $email = isset($form['comment_author_email']) ? sanitize_email($form['comment_author_email']) : "";
        $author = isset($form['comment_author']) ? sanitize_text_field($form['comment_author']) : "";
        $message = $author . ' ' . $message;

        if (!isset($privacyOptions['oopspam_is_check_for_ip']) || $privacyOptions['oopspam_is_check_for_ip'] != true) {
            $userIP = isset($form['user_ip']) ? sanitize_text_field($form['user_ip']) : "";
        }

        $detectionResult = oopspamantispam_call_OOPSpam($message, $userIP, $email, true, "jform");
        if (!isset($detectionResult["isItHam"])) {
            return $_is_spam;
        }
        $frmEntry = [
            "Score" => $detectionResult["Score"],
            "Message" => $message,
            "IP" => $userIP,
            "Email" => $email,
            "RawEntry" => json_encode($form),
            "FormId" => "Jetpack Form",
        ];

        if (!$detectionResult["isItHam"]) {
            // It's spam, store the submission and show error
            oopspam_store_spam_submission($frmEntry, $detectionResult["Reason"]);
            // TODO: Remove oopspam_jform_spam_message from options
            // $error_to_show = $options['oopspam_jform_spam_message'];
            // wp_die( $error_to_show );
            $_is_spam = true;
        } else {
            // It's ham
            oopspam_store_ham_submission($frmEntry);
        }
    }

    return $_is_spam;
}