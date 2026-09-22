<?php

namespace OOPSPAM\Integrations;

add_action('super_before_sending_email_hook', 'OOPSPAM\Integrations\oopspamantispam_superforms_pre_submission', 10, 1);

function oopspamantispam_superforms_pre_submission($atts)
{
    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');

    if (!empty(oopspamantispam_get_key()) && oopspam_is_spamprotection_enabled('superforms')) {

        $data = isset($atts['data']) && is_array($atts['data']) ? $atts['data'] : array();
        $form_id = isset($atts['post']['form_id']) ? absint($atts['post']['form_id']) : 0;

        // Check if the form is excluded from spam protection
        if (isset($options['oopspam_superforms_exclude_form']) && $options['oopspam_superforms_exclude_form']) {
            $formIds = sanitize_text_field(trim($options['oopspam_superforms_exclude_form']));
            $excludedFormIds = array_map('trim', explode(',', $formIds));

            if (in_array($form_id, $excludedFormIds)) {
                return;
            }
        }

        $email = "";
        $message = "";

        // Get the email field value (exact "email" name first, then any field whose name contains "email")
        foreach ($data as $key => $field) {
            if (!is_array($field)) {
                continue;
            }
            if ($key === 'email' && empty($email)) {
                $email = sanitize_email(isset($field['value']) ? $field['value'] : '');
            }
        }
        if (empty($email)) {
            foreach ($data as $key => $field) {
                if (!is_array($field)) {
                    continue;
                }
                if (strpos($key, 'email') !== false && empty($email)) {
                    $email = sanitize_email(isset($field['value']) ? $field['value'] : '');
                }
            }
        }

        // Check for custom content field setting
        if (isset($options['oopspam_superforms_content_field']) && $options['oopspam_superforms_content_field']) {
            $contentFields = array_map('trim', explode(',', sanitize_text_field($options['oopspam_superforms_content_field'])));
            foreach ($contentFields as $fieldName) {
                if (isset($data[$fieldName]['value'])) {
                    $message .= $data[$fieldName]['value'] . ' ';
                }
            }
            $message = trim($message);
        }

        // If no custom content field, look for text/textarea fields
        if (empty($message)) {
            foreach ($data as $key => $field) {
                if (!is_array($field)) {
                    continue;
                }
                $type = isset($field['type']) ? $field['type'] : '';
                if ($type === 'text' || $type === 'textarea') {
                    $message .= (isset($field['value']) ? $field['value'] : '') . ' ';
                }
            }
            $message = trim($message);
        }

        // If still no message, use any regular input field
        if (empty($message)) {
            foreach ($data as $key => $field) {
                if (!is_array($field)) {
                    continue;
                }
                $type = isset($field['type']) ? $field['type'] : '';
                if ($type === 'var') {
                    $message .= (isset($field['value']) ? $field['value'] : '') . ' ';
                }
            }
            $message = trim($message);
        }

        $userIP = "";
        if (!isset($privacyOptions['oopspam_is_check_for_ip']) || ($privacyOptions['oopspam_is_check_for_ip'] !== true && $privacyOptions['oopspam_is_check_for_ip'] !== 'on')) {
            $userIP = oopspamantispam_get_ip();
        }

        $escapedMsg = sanitize_textarea_field($message);

        // Keep the raw entry small: Super Forms passes its full form `settings`
        // inside $atts, which bloats the stored entry (and the debug log) with
        // ~35KB of irrelevant configuration. Store only the submitted field data
        // and the form id.
        $raw_entry = json_encode(array(
            'data' => $data,
            'post' => array(
                'action'  => isset($atts['post']['action']) ? sanitize_text_field($atts['post']['action']) : '',
                'form_id' => $form_id,
            ),
        ));

        $detectionResult = oopspamantispam_call_OOPSpam($escapedMsg, $userIP, $email, true, "superforms");
        if (!isset($detectionResult["isItHam"])) {
            return;
        }

        $frmEntry = [
            "Score" => $detectionResult["Score"],
            "Message" => $escapedMsg,
            "IP" => $userIP,
            "Email" => $email,
            "RawEntry" => $raw_entry,
            "FormId" => \oopspam_format_form_id($form_id, get_the_title($form_id)),
        ];

        if (!$detectionResult["isItHam"]) {
            // It's spam, store the submission and block it
            $reason = !empty($detectionResult["Reason"]) ? $detectionResult["Reason"] : __('Spam detected', 'oopspam-anti-spam');
            oopspam_store_spam_submission($frmEntry, $reason);
            $error_to_show = (isset($options['oopspam_superforms_spam_message']) && !empty($options['oopspam_superforms_spam_message'])) ? $options['oopspam_superforms_spam_message'] : __('Your submission has been flagged as spam.', 'oopspam-anti-spam');

            if (class_exists('\SUPER_Common')) {
                \SUPER_Common::output_message(true, esc_html($error_to_show));
            }
            exit;
        } else {
            // It's ham
            oopspam_store_ham_submission($frmEntry);
        }
    }
}
