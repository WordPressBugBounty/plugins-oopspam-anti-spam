<?php

add_filter('frm_validate_entry', 'oopspamantispam_formidable_pre_submission', 20, 2);

function oopspamantispam_formidable_pre_submission($errors, $values)
{

    if (!isset($values['item_meta']) || empty($values['item_meta']) || !empty($errors)) {
        // only check spam if there are no other errors
        return $errors;
    }

    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');

    if (!empty($options['oopspam_api_key']) && !empty($options['oopspam_is_fable_activated'])) {

        $form_fields = FrmField::get_all_types_in_form($values['form_id'], "textarea");
        $raw_entry = json_encode($values);
        $email = "";
        $message = "";
        $userIP = "";
        $isCustomFieldSet = false;

        // Check if the form is excluded from spam protection
        if (isset($options['oopspam_fable_exclude_form']) && $options['oopspam_fable_exclude_form']) {
        $formIds = sanitize_text_field(trim($options['oopspam_fable_exclude_form']));
        // Split the IDs string into an array using the comma as the delimiter
        $excludedFormIds = array_map('trim', explode(',', $formIds));

        foreach ($excludedFormIds as $id) {
            // Don't check for spam for this form
            // Don't log under Form Ham Entries
            if ($values['form_id'] == $id) {
                return $errors;
            }
        }
    }

        // Capture the email field
        $emailField = FrmField::get_all_types_in_form($values['form_id'], "email");
        if (is_array($emailField) && !empty($emailField)) {
            $email = sanitize_email($_POST['item_meta'][current($emailField)->id]);
        }

        // Select the first textarea field
        if (is_array($form_fields) && !empty($form_fields)) {
            $field_id = current($form_fields)->id;
            $message = $_POST['item_meta'][$field_id];
        }

        // Capture the predefined textarea field
        if (isset($options['oopspam_fable_content_field']) && $options['oopspam_fable_content_field']) {

            $customFieldId = sanitize_text_field(trim($options['oopspam_fable_content_field']));
            $idsArray = array_map('trim', explode(',', $customFieldId));

            // Iterate through each ID to look for message field value
            foreach ($idsArray as $id) {
                
                $idInt = intval($id);
                if (isset($_POST['item_meta'][$idInt])) {
                    $message = $_POST['item_meta'][$idInt];
                    $isCustomFieldSet = true;
                    break;
                }
            }
        }

        if (!$isCustomFieldSet && (isset($errors['spam']) || fform_is_in_progress($values))) {
            return $errors;
        }

        if (!isset($privacyOptions['oopspam_is_check_for_ip']) || $privacyOptions['oopspam_is_check_for_ip'] != true) {
            $userIP = FrmAppHelper::get_ip_address();
        }

        $escapedMsg = sanitize_textarea_field($message);
        $detectionResult = oopspamantispam_call_OOPSpam($escapedMsg, $userIP, $email, true, "formidable");

        if (!isset($detectionResult["isItHam"])) {
            return $errors;
        }

        $frmEntry = [
            "Score" => $detectionResult["Score"],
            "Message" => $escapedMsg,
            "IP" => $userIP,
            "Email" => $email,
            "RawEntry" => $raw_entry,
            "FormId" => $values['form_id'],
        ];

        if (!$detectionResult["isItHam"]) {

            // It's spam, store the submission and show error
            oopspam_store_spam_submission($frmEntry, $detectionResult["Reason"]);
            $error_to_show = $options['oopspam_fable_spam_message'];
            $errors['spam'] = $error_to_show;
        } else {
            // It's ham
            oopspam_store_ham_submission($frmEntry);
        }
    }

    return $errors;
}

function fform_is_in_progress($values)
{
    return FrmAppHelper::pro_is_installed() &&
    (isset($values['frm_page_order_' . $values['form_id']]) || FrmAppHelper::get_post_param('frm_next_page')) &&
    FrmField::get_all_types_in_form($values['form_id'], 'break');
}
