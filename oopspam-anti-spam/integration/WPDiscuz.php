<?php
add_filter('wpdiscuz_before_comment_post', 'oopspam_wpdis_pre_submission');

// Filter function
function oopspam_wpdis_pre_submission()
{

    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');

    if (!empty($options['oopspam_api_key']) && !empty($options['oopspam_is_wpdis_activated'])) {

        // Capture the content
        $message = "";
        $email = "";
         // get email and comment
        if (isset($_POST)) {
            if (isset($_POST["wc_comment"])) {
                $message = $_POST["wc_comment"];
            }
            if (isset($_POST["wc_email"])) {
                $email = sanitize_email($_POST["wc_email"]);

            }
        }

        $raw_entry = json_encode($_POST);
        $form_id = "wpDiscuz";
        $userIP = "";
        if (!isset($privacyOptions['oopspam_is_check_for_ip']) || $privacyOptions['oopspam_is_check_for_ip'] != true) {
            $userIP = oopspamantispam_get_ip();
        }

        $escapedMsg = sanitize_textarea_field($message);
        $detectionResult = oopspamantispam_call_OOPSpam($escapedMsg, $userIP, $email, true, "discuz");

        if (!isset($detectionResult["isItHam"])) {
            return;
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
            $error_to_show = $options['oopspam_wpdis_spam_message'];
            wp_die( __( $error_to_show, 'oopspam' ) );

        } else {
            // It's ham
            oopspam_store_ham_submission($frmEntry);
        }

    }

    return;
};
