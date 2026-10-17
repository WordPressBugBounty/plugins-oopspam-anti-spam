<?php

namespace OOPSPAM\Integrations;


if (!defined('ABSPATH')) {
    exit;
}

add_filter('ipt_fsqm_filter_data_errors', 'OOPSPAM\Integrations\oopspam_eform_check_spam', 10, 2);

/**
 * Run the oopspam spam check for an eForm (eForm - WordPress Form Builder)
 * submission.
 *
 * @param array  $errors Errors collected so far (each: id, msgs).
 * @param object $data   The IPT_FSQm_Form_Elements_Data instance for the
 *                       submission.
 * @return array Unchanged `$errors` when ham, or `$errors` with an extra entry
 *               when the submission is flagged as spam.
 */
function oopspam_eform_check_spam($errors, $data)
{
    // Let eForm resolve its own field validation before we spend an API call.
    if (!empty($errors)) {
        return $errors;
    }

    if (!is_object($data) || !isset($data->data) || !is_object($data->data)) {
        return $errors;
    }

    $options        = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');

    if (empty(oopspamantispam_get_key()) || !oopspam_is_spamprotection_enabled('eform')) {
        return $errors;
    }

    $form_id = isset($data->form_id) ? sanitize_text_field((string) $data->form_id) : '';

    // Skip forms the admin asked us not to protect.
    if ('' !== $form_id && !empty($options['oopspam_eform_exclude_form'])) {
        $excluded = array_filter(
            array_map('trim', explode(',', sanitize_text_field($options['oopspam_eform_exclude_form']))),
            'strlen'
        );
        if (in_array($form_id, $excluded, true)) {
            return $errors;
        }
    }

    $pinfo    = (isset($data->data->pinfo) && is_array($data->data->pinfo)) ? $data->data->pinfo : array();
    $freetype = (isset($data->data->freetype) && is_array($data->data->freetype)) ? $data->data->freetype : array();

    // Message: the free-text (freetype) values. When the admin mapped a
    // "Form ID | Field ID" pair to this form, only those field keys are used.
    $content_keys = array();
    if (!empty($options['oopspam_eform_content_field'])) {
        $pairs = $options['oopspam_eform_content_field'];
        if (is_string($pairs)) {
            $pairs = json_decode(trim($pairs), true);
        }
        if (is_array($pairs)) {
            foreach ($pairs as $pair) {
                if (!is_array($pair) || !isset($pair['formId'], $pair['fieldId'])) {
                    continue;
                }
                if ((string) $pair['formId'] !== $form_id) {
                    continue;
                }
                $content_keys = array_filter(array_map('trim', explode(',', (string) $pair['fieldId'])), 'strlen');
                break;
            }
        }
    }

    $message = '';
    foreach ($freetype as $key => $field) {
        if (empty($content_keys) || in_array((string) $key, $content_keys, true)) {
            $value    = (isset($field['value']) && is_string($field['value'])) ? $field['value'] : '';
            $message .= ' ' . $value;
        }
    }
    $message = trim($message);

    // Fallback: the longest personal-info value.
    if ('' === $message) {
        foreach ($pinfo as $field) {
            if (isset($field['value']) && is_string($field['value']) && strlen(trim($field['value'])) > strlen($message)) {
                $message = trim($field['value']);
            }
        }
    }

    // Email: eForm keeps the submitter's address in a pinfo element, e.g.
    // `email` (primary) or `p_email`. `$data->data->email` is only the mapped
    // DB column and often holds the site's own address, so try it last.
    $email = '';
    foreach ($pinfo as $field) {
        if (isset($field['type'], $field['value']) && is_string($field['type']) && is_string($field['value'])
            && false !== strpos($field['type'], 'email') && '' !== trim($field['value'])) {
            $email = $field['value'];
            break;
        }
    }
    if ('' === $email && isset($data->data->email) && is_string($data->data->email)) {
        $email = $data->data->email;
    }
    $email = sanitize_email($email);

    $userIP = '';
    if (!isset($privacyOptions['oopspam_is_check_for_ip']) || ($privacyOptions['oopspam_is_check_for_ip'] !== true && $privacyOptions['oopspam_is_check_for_ip'] !== 'on')) {
        $userIP = oopspamantispam_get_ip();
    }

    $escapedMsg      = sanitize_textarea_field($message);
    $detectionResult = oopspamantispam_call_OOPSpam($escapedMsg, $userIP, $email, true, 'eform');

    if (!isset($detectionResult['isItHam'])) {
        return $errors;
    }

    $form_name = isset($data->name) ? sanitize_text_field($data->name) : '';

    $frmEntry = array(
        'Score'    => $detectionResult['Score'],
        'Message'  => $escapedMsg,
        'IP'       => $userIP,
        'Email'    => $email,
        'RawEntry' => wp_json_encode(
            array(
                'pinfo'    => isset($data->data->pinfo) ? $data->data->pinfo : array(),
                'freetype' => $freetype,
                'mcq'      => isset($data->data->mcq) ? $data->data->mcq : array(),
            )
        ),
        'FormId'   => \oopspam_format_form_id($form_id, $form_name, 'eform'),
    );

    if (!$detectionResult['isItHam']) {
        // It's spam: log it and abort the submission with a message.
        oopspam_store_spam_submission($frmEntry, $detectionResult['Reason']);

        $error_to_show = (isset($options['oopspam_eform_spam_message']) && !empty($options['oopspam_eform_spam_message']))
            ? $options['oopspam_eform_spam_message']
            : __('Your submission has been flagged as spam.', 'oopspam-anti-spam');

        $allowedEls = array(
            'a'      => array(
                'href'  => array(),
                'title' => array(),
            ),
            'br'     => array(),
            'em'     => array(),
            'strong' => array(),
            'i'      => array(),
            'u'      => array(),
        );

        // eForm shows an empty id as a general form-level error.
        $errors[] = array(
            'id'   => '',
            'msgs' => array(wp_kses($error_to_show, $allowedEls)),
        );

        return $errors;
    }

    // It's ham.
    oopspam_store_ham_submission($frmEntry);

    return $errors;
}
