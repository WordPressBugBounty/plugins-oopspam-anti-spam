<?php

namespace OOPSPAM\Integrations;

if (!defined('ABSPATH')) {
    exit;
}

add_filter('rwmb_frontend_validate', 'OOPSPAM\Integrations\oopspam_mbfs_check_spam', 10, 2);

/**
 * Run the oopspam spam check for a Meta Box Frontend Submission form.
 *
 * @param bool|string $validate Current validation result.
 * @param array       $config   Frontend submission form config.
 * @return bool|string True to continue, an error message to block.
 */
function oopspam_mbfs_check_spam($validate, $config)
{
    // Meta Box's own validation or nonce check already failed.
    if (true !== $validate || !is_array($config) || empty($config['id'])) {
        return $validate;
    }

    if (empty(oopspamantispam_get_key()) || !oopspam_is_spamprotection_enabled('mbfs')) {
        return $validate;
    }

    // Internals we rely on. Bail out rather than fatal if a release renames them.
    if (!function_exists('rwmb_get_registry')) {
        return $validate;
    }

    $options = get_option('oopspamantispam_settings');
    $form_id = sanitize_text_field((string) $config['id']);

    if (oopspam_mbfs_is_excluded($options, $form_id)) {
        return $validate;
    }

    // "Form ID | Field ID" pairs, where a Field ID is the meta box field ID.
    $content_keys = oopspam_mbfs_get_mapped_fields($options, $form_id);

    $message  = '';
    $fallback = '';
    $email    = '';
    $raw      = array();

    // Submitted values are read from $_POST, the same way Meta Box saves them.
    foreach (oopspam_mbfs_get_fields($form_id) as $field_id => $type) {
        // Passwords are never analysed nor stored.
        if ('password' === $type) {
            continue;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked by Meta Box before this filter.
        $value = isset($_POST[$field_id])
            ? oopspam_mbfs_string_value(wp_unslash($_POST[$field_id]))
            : '';

        if ('' === $value) {
            continue;
        }

        $raw[$field_id] = $value;

        // The email address is checked separately, never as message content.
        if ('email' === $type || is_email($value)) {
            if ('' === $email) {
                $email = $value;
            }
            continue;
        }

        if (!empty($content_keys) && !in_array($field_id, $content_keys, true)) {
            continue;
        }

        /*
         * Without a mapping only text fields are used: textarea and wysiwyg
         * fields hold the message body, other text fields are a fallback so a
         * short "name" can't crowd it out. Files, images and the like are
         * never sent.
         */
        if (empty($content_keys)) {
            if (in_array($type, array('textarea', 'wysiwyg'), true)) {
                $message .= ' ' . $value;
            } elseif (in_array($type, array('text', 'url', 'hidden'), true)) {
                $fallback .= ' ' . $value;
            }

            continue;
        }

        $message .= ' ' . $value;
    }

    if ('' === trim($message)) {
        $message = $fallback;
    }

    if (!oopspam_mbfs_is_allowed(trim($message), sanitize_email($email), $form_id, $raw)) {
        return oopspam_mbfs_spam_message();
    }

    return $validate;
}

/**
 * The submitted fields of the form, as field ID => field type.
 *
 * The form ID is the (comma separated) meta box ID list from the shortcode.
 *
 * @param string $form_id Meta box ID(s) used by the form.
 * @return array Field IDs mapped to their type.
 */
function oopspam_mbfs_get_fields($form_id)
{
    $registry = rwmb_get_registry('meta_box');
    $fields   = array();

    foreach (explode(',', $form_id) as $box_id) {
        $box = $registry->get(trim($box_id));

        // RW_Meta_Box::$meta_box is the only public property; its magic
        // __get() has no __isset() partner, so empty() cannot be used on it.
        if (!$box || empty($box->meta_box['fields']) || !is_array($box->meta_box['fields'])) {
            continue;
        }

        foreach ($box->meta_box['fields'] as $field) {
            if (empty($field['id']) || !is_string($field['id'])) {
                continue;
            }

            $fields[$field['id']] = isset($field['type']) ? (string) $field['type'] : '';
        }
    }

    return $fields;
}

/**
 * The title of the first meta box used by the form.
 *
 * @param string $form_id Meta box ID(s) used by the form.
 * @return string
 */
function oopspam_mbfs_get_form_title($form_id)
{
    $registry = rwmb_get_registry('meta_box');

    foreach (explode(',', $form_id) as $box_id) {
        $box = $registry->get(trim($box_id));

        if ($box && !empty($box->meta_box['title'])) {
            return (string) $box->meta_box['title'];
        }
    }

    return '';
}

/**
 * Whether the given form is excluded from spam protection.
 *
 * @param array  $options The oopspam settings.
 * @param string $form_id The form ID.
 * @return bool
 */
function oopspam_mbfs_is_excluded($options, $form_id)
{
    if ('' === $form_id || empty($options['oopspam_mbfs_exclude_form'])) {
        return false;
    }

    $excluded = array_filter(
        array_map('trim', explode(',', sanitize_text_field($options['oopspam_mbfs_exclude_form']))),
        'strlen'
    );

    return in_array($form_id, $excluded, true);
}

/**
 * The field IDs mapped to the given form in the settings.
 *
 * Stored as "Form ID | Field ID" pairs, the same format used by the other
 * integrations, where the Field ID is the meta box field ID.
 *
 * @param array  $options The oopspam settings.
 * @param string $form_id The form ID.
 * @return array Field IDs to collect the message from.
 */
function oopspam_mbfs_get_mapped_fields($options, $form_id)
{
    if (empty($options['oopspam_mbfs_content_field'])) {
        return array();
    }

    // Settings import / WP-CLI can store the already-decoded array.
    $pairs = $options['oopspam_mbfs_content_field'];
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
        if (trim((string) $pair['formId']) !== $form_id) {
            continue;
        }
        return array_filter(array_map('trim', explode(',', (string) $pair['fieldId'])), 'strlen');
    }

    return array();
}

/**
 * Reduce a submitted value to a trimmed string.
 *
 * Clone and group fields post nested arrays.
 *
 * @param mixed $value Submitted value, already unslashed.
 * @return string
 */
function oopspam_mbfs_string_value($value)
{
    if (is_array($value)) {
        $parts = array();

        foreach ($value as $item) {
            $item = oopspam_mbfs_string_value($item);

            if ('' !== $item) {
                $parts[] = $item;
            }
        }

        return implode(', ', $parts);
    }

    return is_scalar($value) ? trim((string) $value) : '';
}

/**
 * The spam message to show instead of the form.
 *
 * Meta Box prints it with wp_kses_post() and Meta Box Frontend Submission
 * injects it into the page over AJAX, so only allowlist simple markup.
 *
 * @return string
 */
function oopspam_mbfs_spam_message()
{
    $options = get_option('oopspamantispam_settings');

    $message = (isset($options['oopspam_mbfs_spam_message']) && !empty($options['oopspam_mbfs_spam_message']))
        ? $options['oopspam_mbfs_spam_message']
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

    return wp_kses($message, $allowedEls);
}

/**
 * Send the submission to oopspam and log the outcome.
 *
 * @param string $message Text to analyse.
 * @param string $email   Submitter email, when available.
 * @param string $form_id Form ID.
 * @param array  $raw     Submitted data for the log.
 * @return bool True when the submission may continue, false when it is spam.
 */
function oopspam_mbfs_is_allowed($message, $email, $form_id, $raw)
{
    $privacyOptions = get_option('oopspamantispam_privacy_settings');

    // Capture user's IP if allowed
    $userIP = '';
    if (!isset($privacyOptions['oopspam_is_check_for_ip']) || ($privacyOptions['oopspam_is_check_for_ip'] !== true && $privacyOptions['oopspam_is_check_for_ip'] !== 'on')) {
        $userIP = oopspamantispam_get_ip();
    }

    $escapedMsg = sanitize_textarea_field($message);

    $detectionResult = oopspamantispam_call_OOPSpam($escapedMsg, $userIP, $email, true, 'mbfs');
    if (!isset($detectionResult['isItHam'])) {
        return true;
    }

    $frmEntry = array(
        'Score'    => $detectionResult['Score'],
        'Message'  => $escapedMsg,
        'IP'       => $userIP,
        'Email'    => $email,
        'RawEntry' => wp_json_encode($raw),
        'FormId'   => \oopspam_format_form_id($form_id, oopspam_mbfs_get_form_title($form_id), 'mbfs'),
    );

    if (!$detectionResult['isItHam']) {
        oopspam_store_spam_submission($frmEntry, $detectionResult['Reason'] ?? '');
        return false;
    }

    oopspam_store_ham_submission($frmEntry);
    return true;
}
