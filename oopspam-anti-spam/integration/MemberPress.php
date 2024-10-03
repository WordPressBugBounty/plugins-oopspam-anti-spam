<?php
add_action('mepr-validate-signup', 'oopspamantispam_mpress_validate_signup', 10, 1);

function oopspamantispam_mpress_validate_signup($errors)
{
    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');
    $message = "";
    $email = "";

    if (empty($_POST)) {
        return $errors;
    }

    // Capture the email
    $email = sanitize_email($_POST['user_email']);

    // Capture the message (combine various fields)
    $message = $_POST['user_first_name'] . ' ' . $_POST['user_last_name'] . ' ';
 
    if (!empty($options['oopspam_api_key']) && !empty($options['oopspam_is_mpress_activated'])) {
        
        // Check if the membership is excluded from spam protection
        if (isset($options['oopspam_mpress_exclude_form']) && $options['oopspam_mpress_exclude_form']) { 
            $membershipIds = sanitize_text_field(trim($options['oopspam_mpress_exclude_form']));
            $excludedMembershipIds = array_map('trim', explode(',', $membershipIds));

            if (in_array($_POST['mepr_product_id'], $excludedMembershipIds)) {
                return $errors;
            }
        }

        $userIP = "";
        if (!isset($privacyOptions['oopspam_is_check_for_ip']) || $privacyOptions['oopspam_is_check_for_ip'] != true) {
            $userIP = oopspamantispam_get_ip();
        }

        $escapedMsg = sanitize_textarea_field($message);
        $raw_entry = json_encode($_POST);

        $detectionResult = oopspamantispam_call_OOPSpam($escapedMsg, $userIP, $email, true, "mpress");
        if (!isset($detectionResult["isItHam"])) {
            return $errors;
        }
        $frmEntry = [
            "Score" => $detectionResult["Score"],
            "Message" => $escapedMsg,
            "IP" => $userIP,
            "Email" => $email,
            "RawEntry" => $raw_entry,
            "FormId" => $_POST['mepr_product_id'],
        ];

        if (!$detectionResult["isItHam"]) {
            // It's spam, store the submission and add error
            oopspam_store_spam_submission($frmEntry, $detectionResult["Reason"]);
            $error_to_show = $options['oopspam_mpress_spam_message'];
            $errors[] = wp_kses($error_to_show, 'post');
        } else {
            // It's ham
            oopspam_store_ham_submission($frmEntry);
        }
    }

    return $errors;
}