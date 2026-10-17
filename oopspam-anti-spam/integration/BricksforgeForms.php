<?php

namespace OOPSPAM\Integrations;


if (!defined('ABSPATH')) {
    exit;
}

add_filter('bricksforge/pro_forms/validate', 'OOPSPAM\Integrations\oopspam_bricksforge_check_spam', 10, 4);

/**
 * Run the oopspam spam check for a Bricksforge Pro Forms submission.
 *
 * @param mixed  $validation_result `true` when valid, otherwise an array of
 *                                  field errors (each: field, message).
 * @param array  $form_data         Sanitised submitted field data. Keys are
 *                                  `form-field-{id}`.
 * @param int    $post_id           ID of the post/template holding the form.
 * @param string $form_id           The Bricksforge form element ID.
 * @return mixed Unchanged `$validation_result` when ham, or an array of field
 *               errors when the submission is flagged as spam.
 */
function oopspam_bricksforge_check_spam($validation_result, $form_data, $post_id, $form_id)
{
    // Respect errors already raised by Bricksforge/Bricks validation.
    if ($validation_result !== true) {
        return $validation_result;
    }

    $options        = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');

    if (empty(oopspamantispam_get_key()) || !oopspam_is_spamprotection_enabled('bricksforge')) {
        return $validation_result;
    }

    if (!is_array($form_data) || empty($form_data)) {
        return $validation_result;
    }

    $form_id_clean = sanitize_text_field((string) $form_id);

    // Skip forms the admin asked us not to protect.
    if (!empty($options['oopspam_bricksforge_exclude_form'])) {
        $excluded = array_filter(
            array_map('trim', explode(',', sanitize_text_field($options['oopspam_bricksforge_exclude_form'])))
        );
        if (in_array($form_id_clean, $excluded, true)) {
            return $validation_result;
        }
    }

   
    $form_settings = array();
    if (class_exists('\Bricks\Helpers') && method_exists('\Bricks\Helpers', 'get_element_settings')) {
        $settings = \Bricks\Helpers::get_element_settings($post_id, $form_id);
        if (is_array($settings)) {
            $form_settings = $settings;
        }
    }

    // Auto-detect the email and content field IDs from the form settings.
    $email_field_id   = '';
    $content_field_id = '';

    if (!empty($form_settings['fields']) && is_array($form_settings['fields'])) {
        foreach ($form_settings['fields'] as $field) {
            if (empty($field['type']) || empty($field['id'])) {
                continue;
            }
            if ('email' === $field['type'] && '' === $email_field_id) {
                $email_field_id = $field['id'];
            }
            if ('textarea' === $field['type'] && '' === $content_field_id) {
                $content_field_id = $field['id'];
            }
        }
    }

    // Use Bricksforge's field labels to spot the message field when the element
    // settings could not be resolved (e.g. form inside a template or preview).
    if ('' === $content_field_id && !empty($form_data['fieldLabels'])) {
        $field_labels = is_string($form_data['fieldLabels']) ? json_decode($form_data['fieldLabels'], true) : $form_data['fieldLabels'];
        if (is_array($field_labels)) {
            foreach ($field_labels as $label_key => $label_value) {
                if (is_string($label_value) && preg_match('/message|comment|content|enquiry|inquiry|details/i', $label_value) && isset($form_data['form-field-' . $label_key])) {
                    $content_field_id = $label_key;
                    break;
                }
            }
        }
    }

    // A custom content field mapping overrides the auto-detected textarea.
    if (!empty($options['oopspam_bricksforge_content_field'])) {
        $custom_ids = array_filter(
            array_map('trim', explode(',', sanitize_text_field($options['oopspam_bricksforge_content_field'])))
        );
        foreach ($custom_ids as $custom_id) {
            if (isset($form_data['form-field-' . $custom_id])) {
                $content_field_id = $custom_id;
                break;
            }
        }
    }

    // Collect the message content.
    $message = '';
    if ('' !== $content_field_id && isset($form_data['form-field-' . $content_field_id]) && is_string($form_data['form-field-' . $content_field_id])) {
        $message = $form_data['form-field-' . $content_field_id];
    } else {
        // Fallback: use the longest submitted string value.
        foreach ($form_data as $key => $value) {
            if (!is_string($key) || 0 !== strpos($key, 'form-field-') || !is_string($value)) {
                continue;
            }
            if (strlen($value) > strlen($message)) {
                $message = $value;
            }
        }
    }

    /*
     * Bricksforge submits a map of field IDs to labels (`fieldLabels`, a JSON
     * string). The submitted keys are `form-field-{id}` where {id} is a random
     * hash, so the field name never contains "email". Use the label map to find
     * the email field when the Bricks element settings are unavailable.
     */
    if ('' === $email_field_id && !empty($form_data['fieldLabels'])) {
        $field_labels = is_string($form_data['fieldLabels']) ? json_decode($form_data['fieldLabels'], true) : $form_data['fieldLabels'];
        if (is_array($field_labels)) {
            foreach ($field_labels as $label_key => $label_value) {
                if (is_string($label_value) && stripos($label_value, 'email') !== false && isset($form_data['form-field-' . $label_key])) {
                    $email_field_id = $label_key;
                    break;
                }
            }
        }
    }

    // Collect the email address.
    $email = '';
    if ('' !== $email_field_id && isset($form_data['form-field-' . $email_field_id]) && is_string($form_data['form-field-' . $email_field_id])) {
        $email = $form_data['form-field-' . $email_field_id];
    } else {
        // Fallback 1: the first submitted field value that is a valid email.
        foreach ($form_data as $key => $value) {
            if (is_string($key) && 0 === strpos($key, 'form-field-') && is_string($value) && is_email($value)) {
                $email = $value;
                break;
            }
        }

        // Fallback 2: a field whose key mentions "email".
        if ('' === $email) {
            foreach ($form_data as $key => $value) {
                if (is_string($key) && stripos($key, 'email') !== false && is_string($value)) {
                    $email = $value;
                    break;
                }
            }
        }
    }
    $email = sanitize_email($email);

    $userIP = '';
    if (!isset($privacyOptions['oopspam_is_check_for_ip']) || ($privacyOptions['oopspam_is_check_for_ip'] !== true && $privacyOptions['oopspam_is_check_for_ip'] !== 'on')) {
        $userIP = oopspamantispam_get_ip();
    }

    $escapedMsg      = sanitize_textarea_field($message);
    $detectionResult = oopspamantispam_call_OOPSpam($escapedMsg, $userIP, $email, true, 'bricksforge');

    if (!isset($detectionResult['isItHam'])) {
        return $validation_result;
    }

    $form_name = isset($form_settings['formName']) ? sanitize_text_field($form_settings['formName']) : '';
    if ('' === $form_name && !empty($post_id)) {
        $form_name = get_the_title($post_id);
    }

    $frmEntry = array(
        'Score'    => $detectionResult['Score'],
        'Message'  => $escapedMsg,
        'IP'       => $userIP,
        'Email'    => $email,
        'RawEntry' => wp_json_encode($form_data),
        'FormId'   => \oopspam_format_form_id($form_id_clean, $form_name, 'bricksforge'),
    );

    if (!$detectionResult['isItHam']) {
        // It's spam: log it and abort the submission with a message.
        oopspam_store_spam_submission($frmEntry, $detectionResult['Reason']);

        $error_to_show = (isset($options['oopspam_bricksforge_spam_message']) && !empty($options['oopspam_bricksforge_spam_message']))
            ? $options['oopspam_bricksforge_spam_message']
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

        // Bricksforge expects a list of field errors. Attach the message to a
        // real field when we know one, so the front-end renders it in place.
        $field_key = '';
        if ('' !== $email_field_id) {
            $field_key = 'form-field-' . $email_field_id;
        } elseif ('' !== $content_field_id) {
            $field_key = 'form-field-' . $content_field_id;
        }

        return array(
            array(
                'field'   => $field_key,
                'message' => wp_kses($error_to_show, $allowedEls),
            ),
        );
    }

    // It's ham.
    oopspam_store_ham_submission($frmEntry);

    return $validation_result;
}
