<?php

namespace OOPSPAM\Integrations;

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Spectra processes its forms inside AJAX handlers and exposes no pre-submit
 * filter: its only hook, do_action('uagb_form_success', $form_data), fires after
 * wp_mail() has already sent the message.
 *
 * These checks therefore run on the same AJAX actions at priority 1, before
 * Spectra's own priority 10 handlers. A spam submission answers and dies there,
 * so no email is sent and no user is created.
 */

add_action('wp_ajax_uagb_process_forms', 'OOPSPAM\Integrations\oopspam_spectra_form_check', 1);
add_action('wp_ajax_nopriv_uagb_process_forms', 'OOPSPAM\Integrations\oopspam_spectra_form_check', 1);

add_action('wp_ajax_spectra_pro_block_register', 'OOPSPAM\Integrations\oopspam_spectra_register_check', 1);
add_action('wp_ajax_nopriv_spectra_pro_block_register', 'OOPSPAM\Integrations\oopspam_spectra_register_check', 1);

/**
 * Run the oopspam spam check for the Spectra forms block (uagb/forms).
 *
 * The block sends its values as a JSON object keyed by field label.
 */
function oopspam_spectra_form_check()
{
    if (oopspam_spectra_skip('nonce', 'uagb_forms_ajax_nonce')) {
        return;
    }

    $form_data = json_decode(oopspam_spectra_post_string('form_data'), true);
    if (!is_array($form_data) || empty($form_data)) {
        return;
    }

    $options = get_option('oopspamantispam_settings');
    $form_id = absint(oopspam_spectra_post_string('post_id'));

    if (oopspam_spectra_is_excluded($options, $form_id)) {
        return;
    }

    // "Form ID | Field ID" pairs, where a Field ID is the field label.
    $content_keys = oopspam_spectra_get_mapped_fields($options, $form_id);

    $message = '';
    $email   = '';

    foreach ($form_data as $label => $value) {
        $value = oopspam_spectra_string_value($value);

        // The email address is checked separately, never as message content.
        if ('' !== $value && is_email($value)) {
            if ('' === $email) {
                $email = $value;
            }
            continue;
        }

        if (empty($content_keys) || in_array((string) $label, $content_keys, true)) {
            $message .= ' ' . $value;
        }
    }

    $email = sanitize_email($email);

    if (!oopspam_spectra_is_allowed(trim($message), $email, $form_id, $form_data, 'spectra')) {
        // The block script only understands a data value of 400, and shows the
        // failure message configured on the block itself.
        wp_send_json_error(400);
    }
}

/**
 * Run the oopspam spam check for the Spectra Pro register block.
 *
 * This block creates the user with wp_insert_user() directly, so it is not
 * covered by the WordPress registration integration.
 */
function oopspam_spectra_register_check()
{
    if (oopspam_spectra_skip('_nonce', 'spectra-pro-register-nonce')) {
        return;
    }

    $options = get_option('oopspamantispam_settings');
    $form_id = absint(oopspam_spectra_post_string('post_id'));

    if (oopspam_spectra_is_excluded($options, $form_id)) {
        return;
    }

    $first_name = sanitize_text_field(oopspam_spectra_post_string('first_name'));
    $last_name  = sanitize_text_field(oopspam_spectra_post_string('last_name'));
    $username   = sanitize_user(oopspam_spectra_post_string('username'));
    $email      = sanitize_email(oopspam_spectra_post_string('email'));

    // Neither the password nor the email address is part of the message.
    $message = trim($first_name . ' ' . $last_name . ' ' . $username);
    $raw     = array(
        'first_name' => $first_name,
        'last_name'  => $last_name,
        'username'   => $username,
        'email'      => $email,
    );

    if (!oopspam_spectra_is_allowed($message, $email, $form_id, $raw, 'spectra_register')) {
        // The block script reads the error as field key => message.
        wp_send_json_error(array('email' => oopspam_spectra_spam_message()));
    }
}

/**
 * Whether this request is none of our business: the integration is off, or the
 * request does not carry Spectra's own nonce.
 *
 * Returning rather than calling check_ajax_referer() keeps a nonce rename in a
 * future Spectra release from blocking every submission - Spectra's own handler
 * still runs and rejects the request itself if the nonce is really invalid.
 *
 * @param string $nonce_field  POST key holding the nonce.
 * @param string $nonce_action Nonce action.
 * @return bool
 */
function oopspam_spectra_skip($nonce_field, $nonce_action)
{
    if (empty(oopspamantispam_get_key()) || !oopspam_is_spamprotection_enabled('spectra')) {
        return true;
    }

    return !wp_verify_nonce(sanitize_text_field(oopspam_spectra_post_string($nonce_field)), $nonce_action);
}

/**
 * Read a POSTed value as an unslashed string.
 *
 * Anything that is not a scalar - a nested array, for example - is read as an
 * empty string, so a crafted request cannot reach a PHP string function such as
 * json_decode() or strlen() with a non-string value.
 *
 * @param string $key POST key.
 * @return string
 */
function oopspam_spectra_post_string($key)
{
    if (!isset($_POST[$key]) || !is_scalar($_POST[$key])) {
        return '';
    }

    return (string) wp_unslash($_POST[$key]);
}

/**
 * Whether the given form is excluded from spam protection.
 *
 * @param array $options The oopspam settings.
 * @param int   $form_id The ID of the page holding the block.
 * @return bool
 */
function oopspam_spectra_is_excluded($options, $form_id)
{
    if (!$form_id || empty($options['oopspam_spectra_exclude_form'])) {
        return false;
    }

    $excluded = array_filter(
        array_map('trim', explode(',', sanitize_text_field($options['oopspam_spectra_exclude_form']))),
        'strlen'
    );

    return in_array((string) $form_id, $excluded, true);
}

/**
 * The content field labels mapped to the given form in the settings.
 *
 * Stored as "Form ID | Field ID" pairs, the same format used by the other
 * integrations, where Form ID is the page holding the block and Field ID is the
 * field label.
 *
 * @param array $options The oopspam settings.
 * @param int   $form_id The ID of the page holding the block.
 * @return array Field labels to collect the message from.
 */
function oopspam_spectra_get_mapped_fields($options, $form_id)
{
    if (empty($options['oopspam_spectra_content_field'])) {
        return array();
    }

    // Settings import / WP-CLI can store the already-decoded array.
    $pairs = $options['oopspam_spectra_content_field'];
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
 * Reduce a submitted value to a trimmed string.
 *
 * Checkbox and multi-select fields post arrays.
 *
 * @param mixed $value Submitted value.
 * @return string
 */
function oopspam_spectra_string_value($value)
{
    if (is_array($value)) {
        $value = implode(', ', array_filter($value, 'is_scalar'));
    }

    return is_scalar($value) ? trim((string) $value) : '';
}

/**
 * The spam message to show, escaped for output in the form.
 *
 * @return string
 */
function oopspam_spectra_spam_message()
{
    $options = get_option('oopspamantispam_settings');

    $message = (isset($options['oopspam_spectra_spam_message']) && !empty($options['oopspam_spectra_spam_message']))
        ? $options['oopspam_spectra_spam_message']
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
 * @param int    $form_id ID of the page holding the block.
 * @param array  $raw     Raw submitted data for the log.
 * @param string $type    Which Spectra block this is.
 * @return bool True when the submission may continue, false when it is spam.
 */
function oopspam_spectra_is_allowed($message, $email, $form_id, $raw, $type)
{
    $privacyOptions = get_option('oopspamantispam_privacy_settings');

    // Capture user's IP if allowed
    $userIP = '';
    if (!isset($privacyOptions['oopspam_is_check_for_ip']) || ($privacyOptions['oopspam_is_check_for_ip'] !== true && $privacyOptions['oopspam_is_check_for_ip'] !== 'on')) {
        $userIP = oopspamantispam_get_ip();
    }

    $escapedMsg = sanitize_textarea_field($message);

    $detectionResult = oopspamantispam_call_OOPSpam($escapedMsg, $userIP, $email, true, $type);
    if (!isset($detectionResult['isItHam'])) {
        return true;
    }

    $frmEntry = array(
        'Score'    => $detectionResult['Score'],
        'Message'  => $escapedMsg,
        'IP'       => $userIP,
        'Email'    => $email,
        'RawEntry' => wp_json_encode($raw),
        'FormId'   => \oopspam_format_form_id($form_id, $form_id ? get_the_title($form_id) : '', $type),
    );

    if (!$detectionResult['isItHam']) {
        oopspam_store_spam_submission($frmEntry, $detectionResult['Reason'] ?? '');
        return false;
    }

    oopspam_store_ham_submission($frmEntry);

    return true;
}
