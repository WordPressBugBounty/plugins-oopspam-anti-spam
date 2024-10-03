<?php
add_filter('um_submit_form_errors_hook', 'oopspamantispam_um_submission', 10, 1);

// Filter function
function oopspamantispam_um_submission($post)
{
    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');

    if (!empty($options['oopspam_api_key']) && !empty($options['oopspam_is_umember_activated'])) {

        $form_id = "";
        $email = "";
        $userIP = "";
        $raw_entry = "";

        if (isset($post["form_id"])) {
           $form_id = $post["form_id"]; 
        }
        if (isset($post["user_email"])) {
            $email = $post["user_email"]; 
         }
        
        if (isset($post["user_password"])) {
            unset($post["user_password"]);
            unset($post["submitted"]["user_password"]);
        }
        

        // Capture user's IP if allowed
        if (!isset($privacyOptions['oopspam_is_check_for_ip']) || $privacyOptions['oopspam_is_check_for_ip'] != true) {
            $userIP = um_user_ip();
        }

        // Capture raw entry
        $raw_entry = json_encode($post);

        $detectionResult = oopspamantispam_call_OOPSpam("", $userIP, $email, true, "umember");
        if (!isset($detectionResult["isItHam"])) {
            return;
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
            $error_to_show = $options['oopspam_umember_spam_message'];
            UM()->form()->add_error( 'user_email', __( $error_to_show, 'oopspam' ) );
          
        } else {
            // It's ham
            oopspam_store_ham_submission($frmEntry);
        }

    }
    
    return;
};
