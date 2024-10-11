<?php
add_action( 'kadence_blocks_form_submission', 'oopspamantispam_kb_pre_submission' , 5, 4 );
add_action( 'kadence_blocks_advanced_form_submission', 'oopspamantispam_kb_adv_pre_submission' , 5, 3 );

if ( file_exists( WP_PLUGIN_DIR . '/kadence-blocks/includes/form-ajax.php' ) ) {
    require_once( WP_PLUGIN_DIR . '/kadence-blocks/includes/form-ajax.php' );
}

function oopspamantispam_kb_adv_pre_submission($form_args, $processed_fields, $post_id)
{
    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');
    $message = "";
    $email = "";

    if (empty($processed_fields)) {
        return;
    }

    // Attempt to capture textarea and email fields value
    foreach ($processed_fields as $field) {
        if (isset($field["type"]) && $field["type"] == "textarea") {
            $message = $field["value"];
        }
        if (isset($field["type"]) && $field["type"] == "email") {
            $email = sanitize_email($field["value"]);
        }
    }


    if (!empty($options['oopspam_api_key']) && !empty($options['oopspam_is_kb_activated'])) {

        $userIP = "";
        if (!isset($privacyOptions['oopspam_is_check_for_ip']) || $privacyOptions['oopspam_is_check_for_ip'] != true) {
            $userIP = oopspamantispam_get_ip();
        }
        $escapedMsg = sanitize_textarea_field($message);
        $raw_entry = json_encode($processed_fields);
        $detectionResult = oopspamantispam_call_OOPSpam($escapedMsg, $userIP, $email, true, "kadence");
        if (!isset($detectionResult["isItHam"])) {
            return;
        }
        $frmEntry = [
            "Score" => $detectionResult["Score"],
            "Message" => $escapedMsg,
            "IP" => $userIP,
            "Email" => $email,
            "RawEntry" => $raw_entry,
            "FormId" => $post_id,
        ];

        if (!$detectionResult["isItHam"]) {
            // It's spam, store the submission and show error
            oopspam_store_spam_submission($frmEntry, $detectionResult["Reason"]);
            $error_to_show = $options['oopspam_kb_spam_message'];
            $kb = new KB_Ajax_Form();
            $kb -> process_bail( __( $error_to_show, 'oopspam' ), __( 'Spam Detected by OOPSpam', 'oopspam' ) );
            return;
        } else {
            // It's ham
            oopspam_store_ham_submission($frmEntry);
            return;
        }

    }
    return;
}
// Filter function
function oopspamantispam_kb_pre_submission($form_args, $fields, $form_id, $post_id)
{

    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');
    $message = "";
    $email = "";

    if (empty($fields)) {
        return;
    }

    // Attempt to capture textarea and email fields value
    foreach ($fields as $field) {
        if (isset($field["type"]) && $field["type"] == "textarea") {
            $message = $field["value"];
        }
        if (isset($field["type"]) && $field["type"] == "email") {
            $email = sanitize_email($field["value"]);
        }
    }


    if (!empty($options['oopspam_api_key']) && !empty($options['oopspam_is_kb_activated'])) {

        $userIP = "";
        if (!isset($privacyOptions['oopspam_is_check_for_ip']) || $privacyOptions['oopspam_is_check_for_ip'] != true) {
            $userIP = oopspamantispam_get_ip();
        }
        $escapedMsg = sanitize_textarea_field($message);
        $raw_entry = json_encode($fields);
        $detectionResult = oopspamantispam_call_OOPSpam($escapedMsg, $userIP, $email, true, "kadence");
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
            $error_to_show = $options['oopspam_kb_spam_message'];
            $kb = new KB_Ajax_Form();
            $kb -> process_bail( __( $error_to_show, 'oopspam' ), __( 'Spam Detected by OOPSpam', 'oopspam' ) );
            return;
        } else {
            // It's ham
            oopspam_store_ham_submission($frmEntry);
            return;
        }

    }
    return;
};
