<?php
add_filter('gform_entry_is_spam', 'oopspamantispam_gform_check_spam', 999, 3);
add_filter( 'gform_confirmation', function ( $confirmation, $form, $entry ) {
    if ( empty( $entry ) || rgar( $entry, 'status' ) === 'spam' ) {
 
        $options = get_option('oopspamantispam_settings');
        if ( isset($options['oopspam_gf_spam_message']) && !empty($options['oopspam_gf_spam_message']) )
        return "<div class='gform_confirmation_message'>" . $options['oopspam_gf_spam_message'] . "</div>";
    }
 
    return $confirmation;
}, 11, 3 );

// Report spam to OOPSpam when an entry is flagged as spam
add_filter( 'gform_update_status', 'marked_as_spam', 10, 3 );
function marked_as_spam($entry_id, $property_value, $previous_value) {

    $should_report = ($property_value == 'spam') || ($property_value == 'active' && $previous_value == 'spam');

    if ($should_report) {
        $entry = GFAPI::get_entry($entry_id);
        $form = GFAPI::get_form(rgar($entry, 'form_id'));
        $extractedData = extractData($form, $entry);

        $is_spam = ($property_value == 'spam');

        oopspamantispam_report_OOPSpam(
            $extractedData['message'],
            $extractedData['ip'],
            $extractedData['email'],
            $is_spam
        );
    }
}

function oopspamantispam_gform_check_spam($is_spam, $form, $entry)
{
    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');

    $extractedData = extractData($form, $entry);
    $message = $extractedData['message'];
    $userIP = $extractedData['ip'];
    $email = $extractedData['email'];
    

    if (!empty($options['oopspam_api_key']) && !empty($options['oopspam_is_gf_activated'])) {
        // Check if the form is excluded from spam protection
        if (isset($options['oopspam_gf_exclude_form']) && $options['oopspam_gf_exclude_form']) {
            $excludedFormIdsSanitized = sanitize_text_field(trim($options['oopspam_gf_exclude_form']));
            $excludedFormIds = array_map('trim', explode(',', $excludedFormIdsSanitized));
            if (in_array($form['id'], $excludedFormIds)) {
                return $is_spam;
            }
        }

        $escapedMsg = sanitize_textarea_field($message);
        

        $detectionResult = oopspamantispam_call_OOPSpam($escapedMsg, $userIP, $email, true, "gravity");
        if (!isset($detectionResult["isItHam"])) {
            return $is_spam;
        }

        $frmEntry = [
            "Score" => $detectionResult["Score"],
            "Message" => $escapedMsg,
            "IP" => $userIP,
            "Email" => $email,
            "RawEntry" => json_encode($entry),
            "FormId" => $form['id'],
        ];

        if (!$detectionResult["isItHam"]) {
            // It's spam, store the submission
            oopspam_store_spam_submission($frmEntry, $detectionResult["Reason"]);
            if ( method_exists( 'GFCommon', 'set_spam_filter' ) ) {
                GFCommon::set_spam_filter( rgar( $form, 'id' ), 'OOPSpam Spam Protection', "The submission has a spam score of " . $detectionResult["Score"] . " , which is higher than the threshold of " . oopspamantispam_get_spamscore_threshold() );
            }
            return true; // Mark as spam
        } else {
            // It's ham
            oopspam_store_ham_submission($frmEntry);
        }
    }

    return $is_spam;
}

function extractData($form, $entry) {
    $options = get_option('oopspamantispam_settings');

    $message = "";
    $email = "";
    $userIP = "";


    // Capture the email
    foreach ($form['fields'] as $field) {
        if ('email' == $field['type']) {
            $email = sanitize_email(rgar($entry, $field['id']));
        }
    }

    // Capture the IP
    if (!isset($privacyOptions['oopspam_is_check_for_ip']) || $privacyOptions['oopspam_is_check_for_ip'] != true) {
        $userIP = rgar($entry, 'ip');
    }

    // 1. Check if custom Form ID|Field ID pair is set for content field
    if (isset($options['oopspam_gf_content_field']) && $options['oopspam_gf_content_field']) {
        $nameOfTextareaField = sanitize_text_field(trim($options['oopspam_gf_content_field']));
        $jsonData = json_decode($nameOfTextareaField, true);
        $currentFormId = $form['id'];

        foreach ($jsonData as $contentFieldPair) {
            if ($contentFieldPair['formId'] == $currentFormId) {
                $fieldIds = explode(',', $contentFieldPair['fieldId']);
                foreach ($fieldIds as $fieldId) {
                    if (isset($entry[$fieldId])) {
                        $message .= rgar($entry, $fieldId) . ' ';
                    }
                }
                $message = trim($message);
                break;
            }
        }
    }

    // 2. Attempt to capture any textarea with its value
    if (empty($message)) {
        foreach ($form['fields'] as $field) {
            if ($field["type"] == "textarea") {
                $message = rgar($entry, $field['id']);
                break;
            }
        }
    }

    // 3. No textarea found, capture any text/name field
    if (empty($message)) {
        foreach ($form['fields'] as $field) {
            if ($field["type"] == "text") {
                $message = rgar($entry, $field['id']);
                break;
            }
        }
    }

    return array(
        'message' => $message,
        'ip' => $userIP,
        'email' => $email
    );

}