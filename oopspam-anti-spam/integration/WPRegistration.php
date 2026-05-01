<?php

namespace OOPSPAM\Integrations;

add_filter('registration_errors', 'OOPSPAM\Integrations\oopspamantispam_validate_email', 10, 3);
add_filter('wp_authenticate_user', 'OOPSPAM\Integrations\oopspamantispam_validate_login', 10, 2);
add_filter('lostpassword_errors', 'OOPSPAM\Integrations\oopspamantispam_validate_lost_password', 10, 2);

function oopspamantispam_get_wp_auth_error_message()
{
    $options = get_option('oopspamantispam_settings');

    return (isset($options['oopspam_wpregister_spam_message']) && !empty($options['oopspam_wpregister_spam_message']))
        ? $options['oopspam_wpregister_spam_message']
        : __('Your submission has been flagged as spam.', 'oopspam-anti-spam');
}

function oopspamantispam_get_wp_auth_ip()
{
    $privacyOptions = get_option('oopspamantispam_privacy_settings');

    if (!isset($privacyOptions['oopspam_is_check_for_ip']) || ($privacyOptions['oopspam_is_check_for_ip'] !== true && $privacyOptions['oopspam_is_check_for_ip'] !== 'on')) {
        return oopspamantispam_get_ip();
    }

    return '';
}

function oopspamantispam_is_wp_login_protection_enabled()
{
    $options = get_option('oopspamantispam_settings');

    if (defined('OOPSPAM_IS_WPLOGIN_ACTIVATED')) {
        return OOPSPAM_IS_WPLOGIN_ACTIVATED;
    }

    return isset($options['oopspam_is_wplogin_activated']) && 1 == $options['oopspam_is_wplogin_activated'];
}

function oopspamantispam_is_default_auth_request($form)
{
    global $pagenow;

    $request_method = isset($_SERVER['REQUEST_METHOD']) && is_string($_SERVER['REQUEST_METHOD'])
        ? strtoupper($_SERVER['REQUEST_METHOD'])
        : '';

    if ('POST' !== $request_method || 'wp-login.php' !== $pagenow) {
        return false;
    }

    $action = isset($_REQUEST['action']) && is_string($_REQUEST['action'])
        ? sanitize_text_field(wp_unslash($_REQUEST['action']))
        : 'login';

    if ('login' === $form) {
        return '' === $action || 'login' === $action;
    }

    if ('lostpassword' === $form) {
        return in_array($action, array('lostpassword', 'retrievepassword'), true);
    }

    return false;
}

function oopspamantispam_check_wp_auth_submission($email, $rawEntry, $formId, $loggedMessage = '')
{
    $loggedMessage = sanitize_textarea_field($loggedMessage);
    $userIP = oopspamantispam_get_wp_auth_ip();
    $email = sanitize_email($email);

    $detectionResult = oopspamantispam_call_OOPSpam('', $userIP, $email, true, 'wpregister');
    if (!isset($detectionResult['isItHam'])) {
        return array('is_spam' => false);
    }

    $frmEntry = array(
        'Score' => $detectionResult['Score'],
        'Message' => $loggedMessage,
        'IP' => $userIP,
        'Email' => $email,
        'RawEntry' => $rawEntry,
        'FormId' => $formId,
    );

    if (!$detectionResult['isItHam']) {
        oopspam_store_spam_submission($frmEntry, isset($detectionResult['Reason']) ? $detectionResult['Reason'] : '');
        return array('is_spam' => true);
    }

    oopspam_store_ham_submission($frmEntry);
    return array('is_spam' => false);
}

function oopspamantispam_validate_email($errors, $sanitized_user_login, $user_email)
{
    if (empty($user_email) || empty(oopspamantispam_get_key()) || !oopspam_is_spamprotection_enabled('wpregister')) {
        return $errors;
    }

    $detectionResult = oopspamantispam_check_wp_auth_submission(
        $user_email,
        wp_json_encode(array(
            'user_login' => $sanitized_user_login,
            'user_email' => $user_email,
            'form' => 'register',
        )),
        'WP Registration'
    );

    if (!empty($detectionResult['is_spam'])) {
        $errors->add('oopspam_error', esc_html(oopspamantispam_get_wp_auth_error_message()));
    }

    return $errors;

}

function oopspamantispam_validate_login($user, $password)
{
    if (!oopspamantispam_is_default_auth_request('login')) {
        return $user;
    }

    if (is_wp_error($user) || !($user instanceof \WP_User)) {
        return $user;
    }

    if (empty($password) || empty(oopspamantispam_get_key()) || !oopspam_is_spamprotection_enabled('wpregister') || !oopspamantispam_is_wp_login_protection_enabled()) {
        return $user;
    }

    $submittedIdentifier = isset($_POST['log']) && is_string($_POST['log'])
        ? sanitize_text_field(wp_unslash($_POST['log']))
        : '';
    $userLogin = !empty($user->user_login) ? sanitize_text_field($user->user_login) : $submittedIdentifier;
    $email = !empty($user->user_email) ? sanitize_email($user->user_email) : '';

    $detectionResult = oopspamantispam_check_wp_auth_submission(
        $email,
        wp_json_encode(array(
            'submitted_identifier' => $submittedIdentifier,
            'user_login' => $userLogin,
            'user_email' => $email,
            'form' => 'login',
        )),
        'WP Login'
    );

    if (!empty($detectionResult['is_spam'])) {
        return new \WP_Error('oopspam_error', esc_html(oopspamantispam_get_wp_auth_error_message()));
    }

    return $user;

}

function oopspamantispam_validate_lost_password($errors, $user_data)
{
    if (!oopspamantispam_is_default_auth_request('lostpassword') || !($errors instanceof \WP_Error)) {
        return $errors;
    }

    if (empty(oopspamantispam_get_key()) || !oopspam_is_spamprotection_enabled('wpregister')) {
        return $errors;
    }

    if (!isset($_POST['user_login']) || !is_string($_POST['user_login'])) {
        return $errors;
    }

    $identifier = sanitize_text_field(wp_unslash($_POST['user_login']));
    if (empty($identifier)) {
        return $errors;
    }

    $email = '';
    if ($user_data instanceof \WP_User && !empty($user_data->user_email)) {
        $email = sanitize_email($user_data->user_email);
    } elseif (is_email($identifier)) {
        $email = sanitize_email($identifier);
    }

    $detectionResult = oopspamantispam_check_wp_auth_submission(
        $email,
        wp_json_encode(array(
            'user_login' => $identifier,
            'user_email' => $email,
            'form' => 'lostpassword',
        )),
        'WP Lost Password'
    );

    if (!empty($detectionResult['is_spam'])) {
        $errors->add('oopspam_error', esc_html(oopspamantispam_get_wp_auth_error_message()));
    }

    return $errors;

}
