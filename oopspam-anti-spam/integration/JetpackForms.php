<?php
function oopspam_jpack_validation( $post_id, $all_values, $extra_values ) {
    
    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');
    $message = "";
    $email = "";
    $userIP = "";

    if (!empty($options['oopspam_api_key']) && !empty($options['oopspam_is_jform_activated'])) { 

        // Process fields in $all_values
        foreach ( $all_values as $key => $value ) {
            // Check if the field value is a valid email
            if ( filter_var( $value, FILTER_VALIDATE_EMAIL ) && empty($email) ) {
                $email = $value;
            }

            // Heuristic: Detect textarea by large content
            if ( strlen( $value ) > 100 && empty($message) ) {
                $message = sanitize_textarea_field($value);
            }
        }

        if (!isset($privacyOptions['oopspam_is_check_for_ip']) || $privacyOptions['oopspam_is_check_for_ip'] != true) {
            $userIP = oopspamantispam_get_ip();
        }

        $detectionResult = oopspamantispam_call_OOPSpam($message, $userIP, $email, true, "jform");
        if (!isset($detectionResult["isItHam"])) {
            return;
        }
        $frmEntry = [
            "Score" => $detectionResult["Score"],
            "Message" => $message,
            "IP" => $userIP,
            "Email" => $email,
            "RawEntry" => json_encode($all_values),
            "FormId" => $post_id,
        ];

        if (!$detectionResult["isItHam"]) {
            // It's spam, store the submission and show error
            oopspam_store_spam_submission($frmEntry, $detectionResult["Reason"]);
            $error_to_show = $options['oopspam_jform_spam_message'];
            wp_die( $error_to_show );
        } else {
            // It's ham
            oopspam_store_ham_submission($frmEntry);
        }

    }

    return true;
}
add_filter( 'grunion_pre_message_sent', 'oopspam_jpack_validation', 10, 3 );