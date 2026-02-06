<?php

namespace OOPSPAM\Integrations;

add_action('acf/validate_save_post', 'OOPSPAM\Integrations\oopspam_acf_validate_save_post');
function oopspam_acf_validate_save_post() {

    // Remove all errors if the user is an administrator.
    if (current_user_can('manage_options')) {
        return;
    }

    if (empty($_POST['acf'])) {
        return;
    }

    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');
    $message = "";
    $email = "";

    // First pass: collect all fields by type for prioritization
    $textarea_fields = [];
    $wysiwyg_fields = [];
    $text_fields = [];
    $email_fields = [];

    foreach ($_POST['acf'] as $field_key => $field_value) {
        $field = acf_get_field($field_key);
        if ($field && !empty($field_value)) {
            $field_type = $field['type'];

            // Categorize fields by type
            switch ($field_type) {
                case 'email':
                    $email_fields[] = $field_value;
                    break;
                case 'textarea':
                    $textarea_fields[] = $field_value;
                    break;
                case 'wysiwyg':
                    $wysiwyg_fields[] = $field_value;
                    break;
                case 'text':
                    $text_fields[] = $field_value;
                    break;
            }
        }
    }

    // Extract email (use first email field found)
    if (!empty($email_fields)) {
        $email = sanitize_email($email_fields[0]);
    }

    // 1. Check if custom content field mapping is set (page-based mapping)
    if (isset($options['oopspam_acf_content_field']) && !empty($options['oopspam_acf_content_field'])) {
        $formDataJson = $options['oopspam_acf_content_field'];
        $formData = json_decode($formDataJson, true);
        
        // Get current page information for ACF form identification
        $current_page_id = '';
        $current_page_title = '';
        
        // For ACF frontend forms, we need to get the page where the form is displayed,
        // not the post being created (which is often "new_post")
        
        // Try to get the current page ID from HTTP_REFERER or current context
        if (isset($_SERVER['HTTP_REFERER'])) {
            $referer_url = $_SERVER['HTTP_REFERER'];
            $current_page_id = url_to_postid($referer_url);
        }
        
        // If that doesn't work, try other methods
        if (empty($current_page_id) || $current_page_id == 0) {
            // Check if we have a valid post_id that's not "new_post"
            if (isset($_POST['post_id']) && $_POST['post_id'] !== 'new_post') {
                $current_page_id = sanitize_text_field($_POST['post_id']);
            } elseif (isset($_POST['acf']['post_id']) && $_POST['acf']['post_id'] !== 'new_post') {
                $current_page_id = sanitize_text_field($_POST['acf']['post_id']);
            } elseif (get_the_ID()) {
                $current_page_id = get_the_ID();
            }
        }
        
        // Get page title if we have a valid page ID
        if (!empty($current_page_id) && is_numeric($current_page_id)) {
            $current_page_title = get_the_title($current_page_id);
        }
                
        // Create possible identifiers: page ID, page title, or combination
        $possible_identifiers = array_filter([
            $current_page_id,
            $current_page_title,
            $current_page_id . ' - ' . $current_page_title
        ]);
        
        // Look for field mapping using any of the possible identifiers
        if (is_array($formData)) {
            foreach ($formData as $mapping) {
                if (isset($mapping['formId']) && in_array($mapping['formId'], $possible_identifiers)) {
                    $field_keys = array_map('trim', explode(',', $mapping['fieldId']));
                    $message_parts = [];
                    foreach ($_POST['acf'] as $field_key => $field_value) {
                        if (in_array($field_key, $field_keys) && !empty($field_value)) {
                            $field = acf_get_field($field_key);
                            if ($field && $field['type'] === 'wysiwyg') {
                                $cleaned_value = wp_strip_all_tags($field_value);
                                $message_parts[] = sanitize_textarea_field($cleaned_value);
                            } else {
                                $message_parts[] = sanitize_textarea_field($field_value);
                            }
                        }
                    }
                    
                    if (!empty($message_parts)) {
                        $message = implode(' ', $message_parts);
                        break;
                    }
                }
            }
        }
    }

    // 2. If no custom mapping or no match found, use automatic field prioritization
    if (empty($message)) {
        if (!empty($textarea_fields)) {
            // Prioritize textarea fields
            $message = sanitize_textarea_field($textarea_fields[0]);
        } elseif (!empty($wysiwyg_fields)) {
            // Fallback to wysiwyg fields (strip HTML tags)
            $message = wp_strip_all_tags($wysiwyg_fields[0]);
            $message = sanitize_textarea_field($message);
        } elseif (!empty($text_fields)) {
            // Final fallback to text fields
            $message = sanitize_textarea_field($text_fields[0]);
        }
    }

    // Skip spam check if no content found or spam protection not enabled
    if (empty($message) || !oopspam_is_spamprotection_enabled('acf') || empty(oopspamantispam_get_key())) {
        return;
    }

    // Get IP address for spam check
    $userIP = "";
    if (!isset($privacyOptions['oopspam_is_check_for_ip']) || ($privacyOptions['oopspam_is_check_for_ip'] !== true && $privacyOptions['oopspam_is_check_for_ip'] !== 'on')) {
        $userIP = oopspamantispam_get_ip();
    }

    $escapedMsg = sanitize_textarea_field($message);
    $detectionResult = oopspamantispam_call_OOPSpam($escapedMsg, $userIP, $email, true, "acf");
    
    if (!isset($detectionResult["isItHam"])) {
        return;
    }

    // Determine page-based identifier for logging
    $form_id = 'ACF Frontend Form';
    
    // Get page information for better identification (same logic as above)
    $page_id = '';
    $page_title = '';
    
    // Try to get the current page ID from HTTP_REFERER first
    if (isset($_SERVER['HTTP_REFERER'])) {
        $referer_url = $_SERVER['HTTP_REFERER'];
        $page_id = url_to_postid($referer_url);
    }
    
    // If that doesn't work, try other methods but avoid "new_post"
    if (empty($page_id) || $page_id == 0) {
        if (isset($_POST['post_id']) && $_POST['post_id'] !== 'new_post') {
            $page_id = sanitize_text_field($_POST['post_id']);
        } elseif (isset($_POST['acf']['post_id']) && $_POST['acf']['post_id'] !== 'new_post') {
            $page_id = sanitize_text_field($_POST['acf']['post_id']);
        } elseif (get_the_ID()) {
            $page_id = get_the_ID();
        }
    }
    
    if (!empty($page_id) && is_numeric($page_id)) {
        $page_title = get_the_title($page_id);
        $form_id = 'ACF Form - Page ID: ' . $page_id . ' (' . $page_title . ')';
    }

    $frmEntry = [
        "Score" => $detectionResult["Score"],
        "Message" => $escapedMsg,
        "IP" => $userIP,
        "Email" => $email,
        "RawEntry" => json_encode($_POST['acf']),
        "FormId" => $form_id,
    ];

    if (!$detectionResult["isItHam"]) {
        // It's spam, store the submission and show error
        oopspam_store_spam_submission($frmEntry, $detectionResult["Reason"]);

        $error_to_show = (isset($options['oopspam_acf_spam_message']) && !empty($options['oopspam_acf_spam_message'])) ? $options['oopspam_acf_spam_message'] : __('Your submission has been flagged as spam.', 'oopspam-anti-spam');

       // Add validation error to prevent form submission
        acf_add_validation_error('', esc_html($error_to_show));
    } else {
        // It's ham
        oopspam_store_ham_submission($frmEntry);
    }
}