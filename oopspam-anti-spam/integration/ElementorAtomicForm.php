<?php

namespace OOPSPAM\Integrations;

add_filter('elementor_pro/atomic_forms/spam_check', 'OOPSPAM\Integrations\oopspamantispam_atomic_el_pre_submission', 10, 4);

function oopspamantispam_atomic_el_pre_submission($is_spam, $form_fields, $widget_settings, $post_id)
{
    if ($is_spam) {
        return true;
    }

    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');

    if (empty(oopspamantispam_get_key()) || !oopspam_is_spamprotection_enabled('el_atomic')) {
        return false;
    }

    $form_name = oopspamantispam_atomic_el_get_form_name();

    if (isset($options['oopspam_el_atomic_exclude_form']) && $options['oopspam_el_atomic_exclude_form']) {
        $formIds = sanitize_text_field(trim($options['oopspam_el_atomic_exclude_form']));
        $excludedFormIds = array_map('trim', explode(',', $formIds));

        foreach ($excludedFormIds as $id) {
            if ($form_name === $id) {
                return false;
            }
        }
    }

    $message = oopspamantispam_atomic_el_get_message($form_fields, $options, $form_name);
    $email = oopspamantispam_atomic_el_get_first_field_value_by_type($form_fields, 'email');
    $raw_entry = wp_json_encode(oopspamantispam_atomic_el_prepare_raw_entry($form_fields));

    $userIP = '';

    if (!isset($privacyOptions['oopspam_is_check_for_ip']) || ($privacyOptions['oopspam_is_check_for_ip'] !== true && $privacyOptions['oopspam_is_check_for_ip'] !== 'on')) {
        $userIP = oopspamantispam_get_ip();
    }

    $escapedMsg = sanitize_textarea_field($message);
    $detectionResult = oopspamantispam_call_OOPSpam($escapedMsg, $userIP, $email, true, 'elementor-atomic');

    if (!isset($detectionResult['isItHam'])) {
        return false;
    }

    $frmEntry = [
        'Score' => $detectionResult['Score'],
        'Message' => $escapedMsg,
        'IP' => $userIP,
        'Email' => $email,
        'RawEntry' => $raw_entry,
        'FormId' => $form_name,
    ];

    if (!$detectionResult['isItHam']) {
        oopspam_store_spam_submission($frmEntry, $detectionResult['Reason']);
        return true;
    }

    oopspam_store_ham_submission($frmEntry);

    return false;
}

function oopspamantispam_atomic_el_get_form_name()
{
    if (isset($_POST['form_name']) && is_string($_POST['form_name'])) {
        return sanitize_text_field(wp_unslash($_POST['form_name']));
    }

    if (isset($_POST['form_id']) && is_string($_POST['form_id'])) {
        return sanitize_text_field(wp_unslash($_POST['form_id']));
    }

    return '';
}

function oopspamantispam_atomic_el_get_message($form_fields, $options, $form_name)
{
    $message = '';

    if (isset($options['oopspam_el_atomic_content_field']) && is_string($options['oopspam_el_atomic_content_field']) && $options['oopspam_el_atomic_content_field']) {
        $jsonData = json_decode($options['oopspam_el_atomic_content_field'], true);

        if (is_array($jsonData)) {
            foreach ($jsonData as $contentFieldPair) {
                if (!is_array($contentFieldPair) || !isset($contentFieldPair['formId'], $contentFieldPair['fieldId'])) {
                    continue;
                }

                if ($contentFieldPair['formId'] !== $form_name) {
                    continue;
                }

                $fieldIds = explode(',', $contentFieldPair['fieldId']);

                foreach ($fieldIds as $fieldId) {
                    $matchingValue = oopspamantispam_atomic_el_get_field_value_by_id($form_fields, trim($fieldId));

                    if ('' !== $matchingValue) {
                        $message .= $matchingValue . ' ';
                    }
                }

                $message = trim($message);
                break;
            }
        }
    }

    if (empty($message)) {
        $message = oopspamantispam_atomic_el_get_first_field_value_by_type($form_fields, 'textarea');
    }

    if (empty($message)) {
        $message = oopspamantispam_atomic_el_get_first_field_value_by_type($form_fields, 'text');
    }

    return $message;
}

function oopspamantispam_atomic_el_get_first_field_value_by_type($form_fields, $target_type)
{
    foreach ($form_fields as $field) {
        if (!is_array($field)) {
            continue;
        }

        $type = sanitize_text_field($field['type'] ?? 'text');

        if ($type !== $target_type) {
            continue;
        }

        return oopspamantispam_atomic_el_sanitize_field_value($field);
    }

    return '';
}

function oopspamantispam_atomic_el_get_field_value_by_id($form_fields, $target_id)
{
    if ('' === $target_id) {
        return '';
    }

    foreach ($form_fields as $field) {
        if (!is_array($field)) {
            continue;
        }

        $field_id = sanitize_text_field($field['id'] ?? '');

        if ($field_id !== $target_id) {
            continue;
        }

        return oopspamantispam_atomic_el_sanitize_field_value($field);
    }

    return '';
}

function oopspamantispam_atomic_el_prepare_raw_entry($form_fields)
{
    $raw_entry = [];

    foreach ($form_fields as $field) {
        if (!is_array($field)) {
            continue;
        }

        $type = sanitize_text_field($field['type'] ?? 'text');

        if ('password' === $type) {
            continue;
        }

        $raw_entry[] = [
            'id' => sanitize_text_field($field['id'] ?? ''),
            'label' => sanitize_text_field($field['label'] ?? ''),
            'type' => $type,
            'value' => oopspamantispam_atomic_el_sanitize_raw_value($field['value'] ?? '', $type),
        ];
    }

    return $raw_entry;
}

function oopspamantispam_atomic_el_sanitize_field_value($field)
{
    $value = $field['value'] ?? '';
    $type = sanitize_text_field($field['type'] ?? 'text');

    if (is_array($value)) {
        $sanitized_values = array_filter(array_map('sanitize_text_field', $value), 'strlen');
        return implode(' ', $sanitized_values);
    }

    if ('textarea' === $type) {
        return sanitize_textarea_field($value);
    }

    return sanitize_text_field($value);
}

function oopspamantispam_atomic_el_sanitize_raw_value($value, $type)
{
    if (is_array($value)) {
        return array_values(array_filter(array_map('sanitize_text_field', $value), 'strlen'));
    }

    if ('textarea' === $type) {
        return sanitize_textarea_field($value);
    }

    return sanitize_text_field($value);
}