<?php

namespace OOPSPAM\Integrations;

add_filter('quform_pre_validate', 'OOPSPAM\Integrations\my_pre_validate', 10, 2);

function my_pre_validate(array $result, \Quform_Form $form)
{
    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');

    if (!empty(oopspamantispam_get_key()) && oopspam_is_spamprotection_enabled('quform')) {
        $form_id = $form->getId();
        $message = "";
        $email = "";

        // Check if the form is excluded from spam protection
        if (isset($options['oopspam_quform_exclude_form']) && $options['oopspam_quform_exclude_form']) {
            $formIds = sanitize_text_field(trim($options['oopspam_quform_exclude_form']));
            $excludedFormIds = array_map('trim', explode(',', $formIds));

            if (in_array($form_id, $excludedFormIds)) {
                return $result;
            }
        }

        // Get Email field value
        foreach ($form->getValues() as $key => $value) {
            $element = $form->getElement($key);
        
            if ($element instanceof \Quform_Element_Email && empty($email)) {
                $email = sanitize_email($element->getValue());
            }
        }

        // Check for custom content field setting
        if (isset($options['oopspam_quform_content_field']) && !empty($options['oopspam_quform_content_field'])) {
            $contentFields = array_map('trim', explode(',', $options['oopspam_quform_content_field']));
            foreach ($contentFields as $fieldId) {
                if ($element = $form->getElement('quform_' . $fieldId)) {
                    $message .= $element->getValue() . ' ';
                }
            }
            $message = trim($message);
        }

        // If no custom content field value found, fallback to default behavior
        if (empty($message)) {
            foreach ($form->getValues() as $key => $value) {
                $element = $form->getElement($key);
                // Get message from textarea
                if ($element instanceof \Quform_Element_Textarea) {
                    $message .= $element->getValue() . ' ';
                }
            }

            // If still no message, try text fields
            if (empty($message)) {
                foreach ($form->getValues() as $key => $value) {
                    $element = $form->getElement($key);
                    if ($element instanceof \Quform_Element_Text) {
                        $message .= $element->getValue() . ' ';
                    }
                }
            }
            $message = trim($message);
        }

        $userIP = "";
        if (!isset($privacyOptions['oopspam_is_check_for_ip']) || $privacyOptions['oopspam_is_check_for_ip'] != true) {
            $userIP = oopspamantispam_get_ip();
        }

        $escapedMsg = sanitize_textarea_field($message);
        $raw_entry = json_encode($form->getValues());
        
        $detectionResult = oopspamantispam_call_OOPSpam($escapedMsg, $userIP, $email, true, "quform");
        if (!isset($detectionResult["isItHam"])) {
            return $result;
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
            $error_to_show = isset($options['oopspam_quform_spam_message']) ? 
                            $options['oopspam_quform_spam_message'] : 
                            __('Your submission has been flagged as spam.', 'oopspam');
            
            return array(
                'type' => 'error',
                'error' => array(
                    'enabled' => true,
                    'content' => $error_to_show
                )
            );
        } else {
            // It's ham
            oopspam_store_ham_submission($frmEntry);
            return $result;
        }
    }

    return $result;
}