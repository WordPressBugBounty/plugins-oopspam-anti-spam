<?php

namespace OOPSPAM\Integrations;

// When errors are returned here, save is aborted and form re-renders with errors under the email field.
add_filter('newsletters_subscriber_validation', 'OOPSPAM\Integrations\oopspam_tribulant_before_save', 10, 2);

function oopspam_tribulant_get_value($data, $field)
{
    if (is_object($data) && isset($data->{$field})) {
        return $data->{$field};
    }

    if (is_array($data) && isset($data[$field])) {
        return $data[$field];
    }

    return null;
}

/**
 * Primary spam check hook.
 * If we return a non-empty errors array, the save is blocked and the form shows the errors.
 */
function oopspam_tribulant_before_save($errors, $data)
{
    $debugFormId = isset($_POST['form_id']) ? sanitize_text_field((string) wp_unslash($_POST['form_id'])) : '';
    $debugListId = isset($_POST['list_id']) ? wp_unslash($_POST['list_id']) : '';
    if (is_array($debugListId)) {
        $debugListId = implode(',', array_map('sanitize_text_field', $debugListId));
    } else {
        $debugListId = sanitize_text_field((string) $debugListId);
    }

    // Only run on frontend subscriber submissions, not admin saves
    if (is_admin() && !defined('DOING_AJAX')) {
        return $errors;
    }

    if (!is_array($errors)) {
        $errors = [];
    }

    // If there are already errors, don't add ours
    if (!empty($errors)) {
        return $errors;
    }

    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');

    if (empty(oopspamantispam_get_key()) || !oopspam_is_spamprotection_enabled('tnl')) {
        return $errors;
    }

    // Build payload from POST data (data passed to this filter is an object, we need raw POST for form/list IDs)
    $payload = isset($_POST) ? wp_unslash($_POST) : [];

    $currentFormId = '';
    if (!empty($payload['form_id'])) {
        $currentFormId = sanitize_text_field((string) $payload['form_id']);
    }

    if (empty($currentFormId) && !empty($payload['list_id'])) {
        $listIdRaw = $payload['list_id'];
        if (is_array($listIdRaw) && !empty($listIdRaw)) {
            $currentFormId = sanitize_text_field((string) reset($listIdRaw));
        } elseif (!empty($listIdRaw)) {
            $currentFormId = sanitize_text_field((string) $listIdRaw);
        }
    }

    if (isset($options['oopspam_tnl_exclude_form']) && !empty($options['oopspam_tnl_exclude_form']) && !empty($currentFormId)) {
        $excludedFormIds = array_map('trim', explode(',', $options['oopspam_tnl_exclude_form']));
        if (in_array($currentFormId, $excludedFormIds, true)) {
            return $errors;
        }
    }

    // Get email from the data object
    $email = '';
    if (is_object($data) && !empty($data->email)) {
        $email = sanitize_email((string) $data->email);
    } elseif (is_array($data) && !empty($data['email'])) {
        $email = sanitize_email((string) $data['email']);
    } elseif (!empty($payload['email'])) {
        $email = sanitize_email((string) $payload['email']);
    }

    if (empty($email)) {
        return $errors;
    }

    $rawEntry = wp_json_encode($payload);
    $formId = 'Newsletters by Tribulant';
    if (!empty($currentFormId)) {
        $formId = 'Newsletters by Tribulant: ' . $currentFormId;
    }

    $userIP = '';
    if (!isset($privacyOptions['oopspam_is_check_for_ip']) || ($privacyOptions['oopspam_is_check_for_ip'] !== true && $privacyOptions['oopspam_is_check_for_ip'] !== 'on')) {
        $userIP = oopspamantispam_get_ip();
    }

    $detectionResult = oopspamantispam_call_OOPSpam('', $userIP, $email, true, 'tribulant-newsletters');

    if (!isset($detectionResult['isItHam'])) {
        return $errors;
    }

    $frmEntry = [
        'Score' => $detectionResult['Score'],
        'Message' => '',
        'IP' => $userIP,
        'Email' => $email,
        'RawEntry' => $rawEntry,
        'FormId' => $formId,
    ];

    if (!$detectionResult['isItHam']) {
        oopspam_store_spam_submission($frmEntry, $detectionResult['Reason']);
        $errorToShow = (isset($options['oopspam_tnl_spam_message']) && !empty($options['oopspam_tnl_spam_message'])) ? $options['oopspam_tnl_spam_message'] : 'Your submission has been flagged as spam.';
        $errors['email'] = esc_html($errorToShow);

        return $errors;
    }

    oopspam_store_ham_submission($frmEntry);

    return $errors;
}
