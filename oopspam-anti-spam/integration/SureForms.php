<?php

namespace OOPSPAM\Integrations;

add_filter('srfm_before_submission', 'OOPSPAM\Integrations\oopspamantispam_sure_pre_submission', 10, 1);

function oopspamantispam_sure_pre_submission($submission_data) {
    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');

    if (!empty($options['oopspam_api_key']) && !empty($options['oopspam_is_sure_activated'])) {
        $form_id = $submission_data['form_id'];
        $message = "";
        $email = "";

        // Check if the form is excluded from spam protection
        if (isset($options['oopspam_sure_exclude_form']) && $options['oopspam_sure_exclude_form']) {
            $formIds = sanitize_text_field(trim($options['oopspam_sure_exclude_form']));
            $excludedFormIds = array_map('trim', explode(',', $formIds));

            if (in_array($form_id, $excludedFormIds)) {
                return $submission_data;
            }
        }

        // Get the first email field value
        foreach ($submission_data['data'] as $key => $value) {
            if (strpos($key, 'srfm-email') !== false && empty($email)) {
                $email = sanitize_email($value);
            }
        }

        // Check for custom content field setting
        if (isset($options['oopspam_sure_content_field']) && $options['oopspam_sure_content_field']) {
            $contentField = sanitize_text_field(trim($options['oopspam_sure_content_field']));
            if (isset($submission_data['data'][$contentField])) {
                $message = $submission_data['data'][$contentField];
            }
        }

        // If no custom content field, look for textarea
        if (empty($message)) {
            foreach ($submission_data['data'] as $key => $value) {
                if (strpos($key, 'srfm-textarea') !== false) {
                    $message .= $value . ' ';
                }
            }
            $message = trim($message);
        }

        error_log(json_encode($submission_data));
        // If still no message, use any input field
        if (empty($message)) {
            foreach ($submission_data['data'] as $key => $value) {
            if (strpos($key, '-input') !== false || strpos($key, 'text-field') !== false) {
                $message .= $value . ' ';
            }
            }
            $message = trim($message);
        }

        $userIP = "";
        if (!isset($privacyOptions['oopspam_is_check_for_ip']) || $privacyOptions['oopspam_is_check_for_ip'] != true) {
            $userIP = oopspamantispam_get_ip();
        }

        $escapedMsg = sanitize_textarea_field($message);
        $raw_entry = json_encode($submission_data);
        
        $detectionResult = oopspamantispam_call_OOPSpam($escapedMsg, $userIP, $email, true, "sureforms");
        if (!isset($detectionResult["isItHam"])) {
            return $submission_data;
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
            wp_die(__($options['oopspam_sure_spam_message'], 'oopspam'));
        } else {
            // It's ham
            oopspam_store_ham_submission($frmEntry);
            return $submission_data;
        }
    }

    return $submission_data;
}
