<?php
class OOPSpamBreakdanceAction extends \Breakdance\Forms\Actions\Action {

    /**
     * @return string
     */
    public static function name() {
    	return 'Check for spam by OOPSpam';
    }

    /**
     * @return string
     */
    public static function slug() {
        return 'oopspam_spam_check';
    }

    /**
     * Check for spam
     *
     * @param array $form
     * @param array $settings
     * @param array $extra
     * @return array success or error message
     */
    public function run($form, $settings, $extra) {
        try {
            $options = get_option('oopspamantispam_settings');
            $privacyOptions = get_option('oopspamantispam_privacy_settings');
            $email = "";
            $message = "";

            foreach ($form as $field) {
                // Capture the email
                if ($field["type"] == "email") { $email = sanitize_email($field["value"]); }
                
                // Capture the message
                if ($field["type"] == "textarea") {

                    // Capture the default message field value
                    if (empty($message)) {
                        $message = $field["value"];
                    }

                      // Check if the form is excluded from spam protection
                    if (isset($options['oopspam_bd_exclude_form']) && $options['oopspam_bd_exclude_form']) {
                        $formIds = sanitize_text_field(trim($options['oopspam_bd_exclude_form']));
                        // Split the IDs string into an array using the comma as the delimiter
                        $excludedFormIds = array_map('trim', explode(',', $formIds));

                        foreach ($excludedFormIds as $id) {
                            // Don't check for spam for this form
                            // Don't log under Form Ham Entries
                            if ($extra["formId"] == $id) {
                                return;
                            }
                        }
                    }

                     // unless it's custom field ID is set by the user
                    if (isset($options['oopspam_bd_content_field']) && $options['oopspam_bd_content_field']) {
                        $nameOfCustomTextareaField = sanitize_text_field(trim($options['oopspam_bd_content_field']));
                        // Split the IDs string into an array using the comma as the delimiter
                        $idsArray = array_map('trim', explode(',', $nameOfCustomTextareaField));

                        
                        // Iterate through each ID to look for message field value
                        foreach ($idsArray as $id) {
                            // Capture the content

                            if ($field["advanced"]["id"] === $id) {
                                $message = $field["value"];
                                break;
                            }
                        }
                    }
                } 
            }

            if (!empty($options['oopspam_api_key'])) { 
                
                $userIP = "";
                if (!isset($privacyOptions['oopspam_is_check_for_ip']) || $privacyOptions['oopspam_is_check_for_ip'] != true) {
                    $userIP = $extra["ip"];
                }

                $escapedMsg = sanitize_textarea_field($message);
                $detectionResult = oopspamantispam_call_OOPSpam($escapedMsg, $userIP, $email, true, "breakdance");
                $raw_entry = json_encode($extra["fields"]);

                if (!isset($detectionResult["isItHam"])) {
                    return;
                }

                $frmEntry = [
                    "Score" => $detectionResult["Score"],
                    "Message" => $escapedMsg,
                    "IP" => $userIP,
                    "Email" => $email,
                    "RawEntry" => $raw_entry,
                    "FormId" => $extra["formId"],
                ];

                if (!$detectionResult["isItHam"]) {

                    // It's spam, store the submission and show error
                    oopspam_store_spam_submission($frmEntry, $detectionResult["Reason"]);

                    $error_to_show = $options['oopspam_bd_spam_message'];
                    
                    return ['type' => 'error', 'message' => $error_to_show];
                } else {
                    // It's ham
                    oopspam_store_ham_submission($frmEntry);
                    return ['type' => 'success', 'message' => "ok"];

                }
             }
             return ['type' => 'success', 'message' => "ok"];

        } catch(Exception $e) {
            return ['type' => 'error', 'message' => $e->getMessage()];
        }

        return ['type' => 'success', 'message' => "ok"];
    
    }
}