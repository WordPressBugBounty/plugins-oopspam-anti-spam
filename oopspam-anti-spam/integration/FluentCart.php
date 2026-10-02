<?php
/**
 * The fluentCart integration class.
 *
 * Runs an oopspam spam check against fluentCart's checkout validation flow
 * before the order is processed.
 *
 * @package OOPSpam_Anti_Spam
 */

namespace OOPSPAM\Integrations;

add_filter('fluent_cart/checkout/validate_data', 'OOPSPAM\Integrations\oopspamantispam_fluentcart_pre_order', 10, 2);

function oopspamantispam_fluentcart_pre_order( $errors, $request ) {

    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');

    if (!is_array($errors)) {
        $errors = array();
    }

    if (empty(oopspamantispam_get_key()) || !oopspam_is_spamprotection_enabled('fluentcart')) {
        return $errors;
    }

    if (!empty($errors)) {
        return $errors;
    }

    $data = isset($request['data']) && is_array($request['data']) ? $request['data'] : array();
    $cart = isset($request['cart']) ? $request['cart'] : null;

    $email = "";
    if (!empty($data['billing_email'])) {
        $email = sanitize_email($data['billing_email']);
    } elseif (is_object($cart) && !empty($cart->email)) {
        $email = sanitize_email($cart->email);
    }

    
    $message = isset($data['order_notes']) ? sanitize_text_field($data['order_notes']) : '';

    $userIP = "";
    if (!isset($privacyOptions['oopspam_is_check_for_ip']) || ($privacyOptions['oopspam_is_check_for_ip'] !== true && $privacyOptions['oopspam_is_check_for_ip'] !== 'on')) {
        $userIP = oopspamantispam_get_ip();
    }

    $raw_entry = json_encode($data);

    $detectionResult = oopspamantispam_call_OOPSpam($message, $userIP, $email, true, "fluentcart");
    if (!isset($detectionResult["isItHam"])) {
        return $errors;
    }

    $frmEntry = [
        "Score" => $detectionResult["Score"],
        "Message" => $message,
        "IP" => $userIP,
        "Email" => $email,
        "RawEntry" => $raw_entry,
        "FormId" => "fluentCart",
    ];

    if (!$detectionResult["isItHam"]) {
        // It's spam: store the submission and add a checkout validation error.
        oopspam_store_spam_submission($frmEntry, $detectionResult["Reason"]);
        $error_to_show = (isset($options['oopspam_fluentcart_spam_message']) && !empty($options['oopspam_fluentcart_spam_message'])) ? $options['oopspam_fluentcart_spam_message'] : __('Your order has been flagged as spam.', 'oopspam-anti-spam');
        $errors['billing_email']['oopspam'] = esc_html($error_to_show);

    } else {
        // It's ham
        oopspam_store_ham_submission($frmEntry);
    }

    return $errors;
}
