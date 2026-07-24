<?php
namespace OOPSPAM\Integrations;

add_action( 'kadence_blocks_advanced_form_submission_reject', 'OOPSPAM\Integrations\oopspamantispam_kb_adv_pre_submission' , 10, 4 );


// Hook BEFORE the form processor runs (priority 1 vs default 10)
add_action( 'wp_ajax_kb_process_ajax_submit', 'OOPSPAM\Integrations\oopspamantispam_kb_intercept', 1 );
add_action( 'wp_ajax_nopriv_kb_process_ajax_submit', 'OOPSPAM\Integrations\oopspamantispam_kb_intercept', 1 );

function oopspamantispam_kb_intercept() {
    // Skip if not a Kadence form submission
    if ( empty( $_POST['_kb_form_id'] ) ) {
        return;
    }

    $options = get_option( 'oopspamantispam_settings' );
    $privacyOptions = get_option( 'oopspamantispam_privacy_settings' );

    if ( empty( oopspamantispam_get_key() ) || ! oopspam_is_spamprotection_enabled( 'kb' ) ) {
        return;
    }

    // Extract fields from POST data (same as your existing logic)
    $message = '';
    $email   = '';
    foreach ( $_POST as $key => $value ) {
        if ( strpos( $key, 'kb_field_' ) !== 0 || ! is_string( $value ) ) {
            continue;
        }
        // Detect email by format
        if ( ! $email && is_email( $value ) ) {
            $email = sanitize_email( $value );
        } else {
            $message .= ' ' . $value;
        }
    }
    $message = trim( $message );


    $userIP = '';
    if ( empty( $privacyOptions['oopspam_is_check_for_ip'] ) ) {
        $userIP = oopspamantispam_get_ip();
    }

    $escapedMsg      = sanitize_textarea_field( $message );
    $detectionResult = oopspamantispam_call_OOPSpam( $escapedMsg, $userIP, $email, true, 'kadence' );

   if (!isset($detectionResult["isItHam"])) {
            return;
        }

        $frmEntry = [
            "Score"    => $detectionResult["Score"],
            "Message"  => $escapedMsg,
            "IP"       => $userIP,
            "Email"    => $email,
            "RawEntry" => json_encode( $_POST ),
            "FormId"   => oopspam_format_form_id( $_POST['_kb_form_id'], get_the_title( $_POST['_kb_form_id'] ) ),
        ];

        if ( ! $detectionResult['isItHam'] ) {
            // It's spam, store the submission and show error
            oopspam_store_spam_submission( $frmEntry, $detectionResult['Reason'] );

            // Block ALL wp_mail calls for this request
            add_filter( 'pre_wp_mail', '__return_false', PHP_INT_MAX );

            // Send error response and exit
            add_action( 'kadence_blocks_form_submission', function() use ( $options ) {
                $error_to_show = ! empty( $options['oopspam_kb_spam_message'] )
                    ? $options['oopspam_kb_spam_message']
                    : 'Your submission has been flagged as spam.';

                while ( ob_get_level() ) {
                    ob_end_clean();
                }
                header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
                echo wp_json_encode( [
                    'success' => false,
                    'data'    => [
                        'html'    => '<div class="kadence-blocks-form-message kadence-blocks-form-warning">' . esc_html( $error_to_show ) . '</div>',
                        'console' => __( 'Spam Detected by OOPSpam', 'oopspam-anti-spam' ),
                    ],
                ] );
                exit;
            }, 9 );
        } else {
            // It's ham
            oopspam_store_ham_submission( $frmEntry );
            return;
        }
}

if ( file_exists( WP_PLUGIN_DIR . '/kadence-blocks/includes/form-ajax.php' ) ) {
    require_once( WP_PLUGIN_DIR . '/kadence-blocks/includes/form-ajax.php' );
}

function oopspamantispam_kb_adv_pre_submission($reject, $form_args, $processed_fields, $post_id)
{
    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');
    $message = "";
    $email = "";

    if (empty($processed_fields)) {
        return $reject;
    }

    // Attempt to capture textarea and email fields value
    foreach ($processed_fields as $field) {
        if (isset($field["type"]) && $field["type"] == "textarea") {
            $message = $field["value"];
        }
        if (isset($field["type"]) && $field["type"] == "email") {
            $email = sanitize_email($field["value"]);
        }
    }


    if (!empty(oopspamantispam_get_key()) && oopspam_is_spamprotection_enabled('kb')) {

        $userIP = "";
        if (!isset($privacyOptions['oopspam_is_check_for_ip']) || ($privacyOptions['oopspam_is_check_for_ip'] !== true && $privacyOptions['oopspam_is_check_for_ip'] !== 'on')) {
            $userIP = oopspamantispam_get_ip();
        }
        $escapedMsg = sanitize_textarea_field($message);
        $raw_entry = json_encode($processed_fields);
        $detectionResult = oopspamantispam_call_OOPSpam($escapedMsg, $userIP, $email, true, "kadence");
        if (!isset($detectionResult["isItHam"])) {
            return $reject;
        }
        $frmEntry = [
            "Score" => $detectionResult["Score"],
            "Message" => $escapedMsg,
            "IP" => $userIP,
            "Email" => $email,
            "RawEntry" => $raw_entry,
            "FormId" => \oopspam_format_form_id($post_id, get_the_title($post_id)),
        ];

        if (!$detectionResult["isItHam"]) {
            // It's spam, store the submission and show error
            oopspam_store_spam_submission($frmEntry, $detectionResult["Reason"]);

            // Hook into the kadence_blocks_advanced_form_submission_reject_message filter
            add_filter('kadence_blocks_advanced_form_submission_reject_message', 'OOPSPAM\Integrations\oopspam_kadence_reject_message', 10, 4);
            $reject = true;
            return $reject;
        } else {
            // It's ham
            oopspam_store_ham_submission($frmEntry);
            return $reject;
        }

    }
    return $reject;
}

// Custom rejection message function
function oopspam_kadence_reject_message($message, $form_args, $processed_fields, $post_id) {
    // Customize the rejection message
    $options = get_option('oopspamantispam_settings');
    $error_to_show = (isset($options['oopspam_kb_spam_message']) && !empty($options['oopspam_kb_spam_message'])) ? $options['oopspam_kb_spam_message'] : 'Your submission has been flagged as spam.';
    return esc_html($error_to_show);
}
