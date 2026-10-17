<?php

namespace OOPSPAM\Integrations;

if (!defined('ABSPATH')) {
    exit;
}

add_filter('wpbs_form_validation', 'OOPSPAM\Integrations\oopspam_wpbs_check_spam', 10, 2);

/**
 * Run the oopspam spam check for a WP Booking System submission.
 *
 * @param mixed $custom_validation Previous validation result (false when unset).
 * @param array $args              Form, calendar and field data for this submission.
 * @return mixed Unchanged `$custom_validation` when ham, or an error array when spam.
 */
function oopspam_wpbs_check_spam($custom_validation, $args)
{
    // Another plugin has already flagged this submission.
    if (false !== $custom_validation) {
        return $custom_validation;
    }

    if (!is_array($args) || empty($args['form']) || !is_object($args['form']) || !method_exists($args['form'], 'get')) {
        return $custom_validation;
    }

    $options        = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');

    if (empty(oopspamantispam_get_key()) || !oopspam_is_spamprotection_enabled('wpbs')) {
        return $custom_validation;
    }

    $form      = $args['form'];
    $form_id   = absint($form->get('id'));
    $form_name = (string) $form->get('name');

    // Skip forms the admin asked us not to protect.
    if ($form_id && !empty($options['oopspam_wpbs_exclude_form'])) {
        $excluded = array_filter(
            array_map('trim', explode(',', sanitize_text_field($options['oopspam_wpbs_exclude_form']))),
            'strlen'
        );
        if (in_array((string) $form_id, $excluded, true)) {
            return $custom_validation;
        }
    }

    $form_fields = (isset($args['form_fields']) && is_array($args['form_fields'])) ? $args['form_fields'] : array();

    // 1. The field IDs the admin mapped for this form.
    $message   = '';
    $field_ids = oopspam_wpbs_get_mapped_fields($options, $form_id);

    foreach ($form_fields as $field) {
        if (!is_array($field) || !isset($field['id'], $field['user_value'])) {
            continue;
        }
        if (in_array((string) $field['id'], $field_ids, true) && is_string($field['user_value'])) {
            $message .= ' ' . $field['user_value'];
        }
    }

    // 2. Any textarea, then any text field.
    if ('' === trim($message)) {
        $message = oopspam_wpbs_collect_fields($form_fields, array('textarea'));
    }
    if ('' === trim($message)) {
        $message = oopspam_wpbs_collect_fields($form_fields, array('text'));
    }
    $message = trim($message);

    $email = oopspam_wpbs_get_email($form_fields);

    // Capture user's IP if allowed
    $userIP = '';
    if (!isset($privacyOptions['oopspam_is_check_for_ip']) || ($privacyOptions['oopspam_is_check_for_ip'] !== true && $privacyOptions['oopspam_is_check_for_ip'] !== 'on')) {
        $userIP = oopspamantispam_get_ip();
    }

    $escapedMsg = sanitize_textarea_field($message);

    $detectionResult = oopspamantispam_call_OOPSpam($escapedMsg, $userIP, $email, true, "wpbs");
    if (!isset($detectionResult['isItHam'])) {
        return $custom_validation;
    }

    $frmEntry = array(
        'Score'    => $detectionResult['Score'],
        'Message'  => $escapedMsg,
        'IP'       => $userIP,
        'Email'    => $email,
        'RawEntry' => wp_json_encode($form_fields),
        'FormId'   => \oopspam_format_form_id($form_id, $form_name, 'wpbs'),
    );

    if (!$detectionResult['isItHam']) {
        // It's spam: log it and abort the booking.
        oopspam_store_spam_submission($frmEntry, $detectionResult['Reason'] ?? '');

        $error_to_show = (isset($options['oopspam_wpbs_spam_message']) && !empty($options['oopspam_wpbs_spam_message']))
            ? $options['oopspam_wpbs_spam_message']
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

        return array(
            'error'         => true,
            'error_message' => wp_kses($error_to_show, $allowedEls),
        );
    }

    // It's ham.
    oopspam_store_ham_submission($frmEntry);

    return $custom_validation;
}

/**
 * The content field ID(s) mapped to the given form in the settings.
 *
 * The mapping is stored as "Form ID | Field ID" pairs, the same format used by
 * the other integrations.
 *
 * @param array $options The oopspam settings.
 * @param int   $form_id The submitted form ID.
 * @return array Field IDs to collect the message from.
 */
function oopspam_wpbs_get_mapped_fields($options, $form_id)
{
    if (empty($options['oopspam_wpbs_content_field'])) {
        return array();
    }

    // Settings import / WP-CLI can store the already-decoded array.
    $pairs = $options['oopspam_wpbs_content_field'];
    if (is_string($pairs)) {
        $pairs = json_decode(trim($pairs), true);
    }
    if (!is_array($pairs)) {
        return array();
    }

    foreach ($pairs as $pair) {
        if (!is_array($pair) || !isset($pair['formId'], $pair['fieldId'])) {
            continue;
        }
        if ((string) $pair['formId'] !== (string) $form_id) {
            continue;
        }
        return array_filter(array_map('trim', explode(',', (string) $pair['fieldId'])), 'strlen');
    }

    return array();
}

/**
 * Join the submitted values of the given field types.
 *
 * @param array $form_fields The submitted fields.
 * @param array $types       Field types to collect.
 * @return string
 */
function oopspam_wpbs_collect_fields($form_fields, $types)
{
    $values = array();

    foreach ($form_fields as $field) {
        if (!is_array($field) || !isset($field['type'], $field['user_value'])) {
            continue;
        }
        if (in_array($field['type'], $types, true) && is_string($field['user_value']) && '' !== trim($field['user_value'])) {
            $values[] = $field['user_value'];
        }
    }

    return implode(' ', $values);
}

/**
 * The submitter's email address.
 *
 * @param array $form_fields The submitted fields.
 * @return string
 */
function oopspam_wpbs_get_email($form_fields)
{
    $email = '';

    foreach ($form_fields as $field) {
        if (is_array($field) && isset($field['type'], $field['user_value']) && 'email' === $field['type'] && is_string($field['user_value'])) {
            $email = $field['user_value'];
            break;
        }
    }

    // Fallback: any submitted value that looks like an email address.
    if ('' === $email) {
        foreach ($form_fields as $field) {
            if (is_array($field) && isset($field['user_value']) && is_string($field['user_value']) && is_email($field['user_value'])) {
                $email = $field['user_value'];
                break;
            }
        }
    }

    return sanitize_email($email);
}
