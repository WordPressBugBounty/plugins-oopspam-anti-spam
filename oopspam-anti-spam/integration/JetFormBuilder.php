<?php

namespace OOPSPAM\Integrations;

use Jet_Form_Builder\Form_Messages\Manager;
use JFB_Modules\Security\Exceptions\Spam_Exception;

if (!defined('ABSPATH')) {
    exit;
}

add_action('jet-form-builder/request', 'OOPSPAM\Integrations\oopspam_jfb_check_spam', 10);

/**
 * Run the oopspam spam check for a JetFormBuilder submission.
 *
 * @throws Spam_Exception When the submission is spam.
 */
function oopspam_jfb_check_spam()
{

    if (empty(oopspamantispam_get_key()) || !oopspam_is_spamprotection_enabled('jfb')) {
        return;
    }

    // Internals we rely on. Bail out rather than fatal if a release renames them.
    if (
        !function_exists('jet_fb_context') || !function_exists('jet_fb_handler')
        || !class_exists(Spam_Exception::class) || !class_exists(Manager::class)
    ) {
        return;
    }

    $options = get_option('oopspamantispam_settings');
    $context = jet_fb_context();
    $form_id = absint(jet_fb_handler()->get_form_id());
    $request = $context->get_request();

    if (!is_array($request) || empty($request) || oopspam_jfb_is_excluded($options, $form_id)) {
        return;
    }

    // "Form ID | Field ID" pairs, where a Field ID is the field name.
    $content_keys = oopspam_jfb_get_mapped_fields($options, $form_id);

    $message  = '';
    $fallback = '';
    $email    = '';
    $raw      = array();

    foreach ($request as $name => $value) {
        // Passwords and any other field JetFormBuilder marks as secure are
        // neither analysed nor logged.
        if ($context->is_secure($name)) {
            continue;
        }

        $value      = oopspam_jfb_string_value($value);
        $raw[$name] = $value;

        // The request also carries JetFormBuilder's own data (__form_id,
        // _wpnonce, ...). Those entries have no field type.
        $type = $context->get_field_type($name);

        if ('' === $type) {
            continue;
        }

        // The email address is checked separately, never as message content.
        if ('' !== $value && is_email($value)) {
            if ('' === $email) {
                $email = $value;
            }
            continue;
        }

        if (!empty($content_keys) && !in_array((string) $name, $content_keys, true)) {
            continue;
        }

        /*
         * Without a mapping, textarea and wysiwyg fields hold the content that
         * needs checking. Other fields are only used when the form has no such
         * field, so a short "subject" can't crowd out the message body.
         */
        if (empty($content_keys) && !in_array($type, array('textarea-field', 'wysiwyg-field'), true)) {
            $fallback .= ' ' . $value;
            continue;
        }

        $message .= ' ' . $value;
    }

    if ('' === trim($message)) {
        $message = $fallback;
    }

    if (!oopspam_jfb_is_allowed(trim($message), sanitize_email($email), $form_id, $raw)) {
        throw new Spam_Exception(Manager::dynamic_error(oopspam_jfb_spam_message()));
    }
}

/**
 * Whether the given form is excluded from spam protection.
 *
 * @param array $options The oopspam settings.
 * @param int   $form_id The JetFormBuilder form ID.
 * @return bool
 */
function oopspam_jfb_is_excluded($options, $form_id)
{
    if (!$form_id || empty($options['oopspam_jfb_exclude_form'])) {
        return false;
    }

    $excluded = array_filter(
        array_map('trim', explode(',', sanitize_text_field($options['oopspam_jfb_exclude_form']))),
        'strlen'
    );

    return in_array((string) $form_id, $excluded, true);
}

/**
 * The field names mapped to the given form in the settings.
 *
 * Stored as "Form ID | Field ID" pairs, the same format used by the other
 * integrations, where the Field ID is the field name.
 *
 * @param array $options The oopspam settings.
 * @param int   $form_id The JetFormBuilder form ID.
 * @return array Field names to collect the message from.
 */
function oopspam_jfb_get_mapped_fields($options, $form_id)
{
    if (empty($options['oopspam_jfb_content_field'])) {
        return array();
    }

    // Settings import / WP-CLI can store the already-decoded array.
    $pairs = $options['oopspam_jfb_content_field'];
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
 * Checkbox and repeater fields post arrays.
 *
 * @param mixed $value Submitted value.
 * @return string
 */
function oopspam_jfb_string_value($value)
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
function oopspam_jfb_spam_message()
{
    $options = get_option('oopspamantispam_settings');

    $message = (isset($options['oopspam_jfb_spam_message']) && !empty($options['oopspam_jfb_spam_message']))
        ? $options['oopspam_jfb_spam_message']
        : __('Your submission has been flagged as spam.', 'oopspam-anti-spam');

    /*
     * JetFormBuilder replaces any status of 255+ bytes with a generic message,
     * and our status carries a "derror|" prefix, so keep the text short.
     */
    $plain = wp_strip_all_tags($message);

    if (strlen($plain) > 240) {
        return substr($plain, 0, 240);
    }

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
 * @param int    $form_id JetFormBuilder form ID.
 * @param array  $raw     Raw submitted data for the log.
 * @return bool True when the submission may continue, false when it is spam.
 */
function oopspam_jfb_is_allowed($message, $email, $form_id, $raw)
{
    $privacyOptions = get_option('oopspamantispam_privacy_settings');

    // Capture user's IP if allowed
    $userIP = '';
    if (!isset($privacyOptions['oopspam_is_check_for_ip']) || ($privacyOptions['oopspam_is_check_for_ip'] !== true && $privacyOptions['oopspam_is_check_for_ip'] !== 'on')) {
        $userIP = oopspamantispam_get_ip();
    }

    $escapedMsg = sanitize_textarea_field($message);

    $detectionResult = oopspamantispam_call_OOPSpam($escapedMsg, $userIP, $email, true, 'jfb');
    if (!isset($detectionResult['isItHam'])) {
        return true;
    }

    $frmEntry = array(
        'Score'    => $detectionResult['Score'],
        'Message'  => $escapedMsg,
        'IP'       => $userIP,
        'Email'    => $email,
        'RawEntry' => wp_json_encode($raw),
        'FormId'   => \oopspam_format_form_id($form_id, $form_id ? get_the_title($form_id) : '', 'jfb'),
    );

    if (!$detectionResult['isItHam']) {
        oopspam_store_spam_submission($frmEntry, $detectionResult['Reason'] ?? '');
        return false;
    }

    oopspam_store_ham_submission($frmEntry);

    return true;
}
