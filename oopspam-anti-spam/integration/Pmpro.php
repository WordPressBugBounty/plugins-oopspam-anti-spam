<?php
add_filter('pmpro_registration_checks', 'oopspamantispam_pmp_submission', 10, 1);

// Filter function
function oopspamantispam_pmp_submission( $continue )
{
    // Bail if another check already failed.
    if ( ! $continue ) {
        return $continue;
    }
    
    // If the user is logged in already during checkout, just bail. Let's assume they're ok.
    if ( is_user_logged_in() ) {
        return $continue;
    }

    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');

    if (!empty($options['oopspam_api_key']) && !empty($options['oopspam_is_pmp_activated'])) {

        $form_id = "Paid Memberships Pro";
        $email = "";
        $userIP = "";
        $raw_entry = "";

        if (isset($_REQUEST["password"])) {
            unset($_REQUEST["password"]);
            unset($_REQUEST["password2"]);
        }
        if (isset($_REQUEST["AccountNumber"])) {
            unset($_REQUEST["AccountNumber"]);
            unset($_REQUEST["CVV"]);
            unset($_REQUEST["ExpirationMonth"]);
            unset($_REQUEST["ExpirationYear"]);
        }


        if (isset($_REQUEST["bemail"])) {
            $email = sanitize_email( $_REQUEST['bemail'] ); 
         }
        

        // Capture user's IP if allowed
        if (!isset($privacyOptions['oopspam_is_check_for_ip']) || $privacyOptions['oopspam_is_check_for_ip'] != true) {
            $userIP = oopspamantispam_get_ip();
        }

        // Capture raw entry
        $raw_entry = json_encode($_REQUEST);

        $detectionResult = oopspamantispam_call_OOPSpam("", $userIP, $email, true, "pmp");
        if (!isset($detectionResult["isItHam"])) {
            return $continue;
        }
        $frmEntry = [
            "Score" => $detectionResult["Score"],
            "Message" => "",
            "IP" => $userIP,
            "Email" => $email,
            "RawEntry" => $raw_entry,
            "FormId" => $form_id,
        ];

        if (!$detectionResult["isItHam"]) {

            // It's spam, store the submission in Form Spam Entries
            oopspam_store_spam_submission($frmEntry, $detectionResult["Reason"]);
            $error_to_show = $options['oopspam_pmp_spam_message'];
            pmpro_setMessage( esc_html__( $error_to_show, 'pmpro-oopspam' ), 'pmpro_error' );
            return $continue;
          
        } else {
            // It's ham
            oopspam_store_ham_submission($frmEntry);
            return $continue;
        }

    }
    
    return $continue;
};
