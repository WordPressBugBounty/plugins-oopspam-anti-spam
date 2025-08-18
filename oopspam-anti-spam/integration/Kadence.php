<?php
namespace OOPSPAM\Integrations;

add_action( 'kadence_blocks_form_submission', 'OOPSPAM\Integrations\oopspamantispam_kb_pre_submission' , 10, 4 );
add_action( 'kadence_blocks_advanced_form_submission_reject', 'OOPSPAM\Integrations\oopspamantispam_kb_adv_pre_submission' , 10, 4 );

if ( file_exists( WP_PLUGIN_DIR . '/kadence-blocks/includes/form-ajax.php' ) ) {
    require_once( WP_PLUGIN_DIR . '/kadence-blocks/includes/form-ajax.php' );
}

function oopspamantispam_kb_adv_pre_submission($reject, $form_args, $processed_fields, $post_id)
{
    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');
    $message = "";
    $email = "";

    if (empty($processed_fields)) {
        return $reject;
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


    if (!empty(oopspamantispam_get_key()) && oopspam_is_spamprotection_enabled('kb')) {

        $userIP = "";
        if (!isset($privacyOptions['oopspam_is_check_for_ip']) || $privacyOptions['oopspam_is_check_for_ip'] != true) {
            $userIP = oopspamantispam_get_ip();
        }
        $escapedMsg = sanitize_textarea_field($message);
        $raw_entry = json_encode($processed_fields);
        $detectionResult = oopspamantispam_call_OOPSpam($escapedMsg, $userIP, $email, true, "kadence");
        if (!isset($detectionResult["isItHam"])) {
            return $reject;
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

            // Hook into the kadence_blocks_advanced_form_submission_reject_message filter
            add_filter('kadence_blocks_advanced_form_submission_reject_message', 'OOPSPAM\Integrations\oopspam_kadence_reject_message', 10, 4);
            $reject = true;
            return $reject;
        } else {
            // It's ham
            oopspam_store_ham_submission($frmEntry);
            return $reject;
        }

    }
    return $reject;
}

// Custom rejection message function
function oopspam_kadence_reject_message($message, $form_args, $processed_fields, $post_id) {
    // Customize the rejection message
    $options = get_option('oopspamantispam_settings');
    $error_to_show = (isset($options['oopspam_kb_spam_message']) && !empty($options['oopspam_kb_spam_message'])) ? $options['oopspam_kb_spam_message'] : 'Your submission has been flagged as spam.';
    return __($error_to_show, 'oopspam');
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


    if (!empty(oopspamantispam_get_key()) && oopspam_is_spamprotection_enabled('kb')) {

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
            $error_to_show = (isset($options['oopspam_kb_spam_message']) && !empty($options['oopspam_kb_spam_message'])) ? $options['oopspam_kb_spam_message'] : 'Your submission has been flagged as spam.';
            $kb = new \KB_Ajax_Form();
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
