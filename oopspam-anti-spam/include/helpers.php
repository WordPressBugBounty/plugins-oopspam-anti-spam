<?php

function oopspamantispam_plugin_check($plugin)
{
    $result = false;
    switch ($plugin) {
        case 'nf':
            if (is_plugin_active('ninja-forms/ninja-forms.php')) {
                $result = true;
            }
            break;
        case 'cf7':
            if (is_plugin_active('contact-form-7/wp-contact-form-7.php')) {
                $result = true;
            }
            break;
        case 'gf':
            if (is_plugin_active('gravityforms/gravityforms.php')) {
                $result = true;
            }
            break;
        case 'el':
            if (is_plugin_active('elementor-pro/elementor-pro.php')) {
                $result = true;
            }
            break;
        case 'br':
            $theme = wp_get_theme(); // gets the current theme
            if ('Bricks' == $theme->name || 'Bricks' == $theme->parent_theme) {
                $result = true;
            }
            break;
        case 'ff':
            if (is_plugin_active('fluentformpro/fluentformpro.php') || is_plugin_active('fluentform/fluentform.php')) {
                $result = true;
            }
            break;
        case 'ws':
            if (is_plugin_active('ws-form-pro/ws-form.php') || is_plugin_active('ws-form/ws-form.php')) {
                $result = true;
            }
            break;
        case 'wpf':
            if (is_plugin_active('wpforms/wpforms.php') || is_plugin_active('wpforms-lite/wpforms.php')) {
                $result = true;
            }
            break;
        case 'fable':
            if (is_plugin_active('formidable/formidable.php') || is_plugin_active('formidable-pro/formidable-pro.php')) {
                $result = true;
            }
            break;
        case 'give':
            if (is_plugin_active('give/give.php')) {
                $result = true;
            }
            break;
        case 'wp-register':
            if (get_option('users_can_register')) {
                $result = true;
            }
            break;
        case 'woo':
            if (is_plugin_active('woocommerce/woocommerce.php')) {
                $result = true;
            }
            break;
        case 'ts':
            if (is_plugin_active('cred-frontend-editor/plugin.php')) {
                $result = true;
            }
            break;
        case 'pionet':
            if (is_plugin_active('piotnetforms-pro/piotnetforms-pro.php') || is_plugin_active('piotnetforms/piotnetforms.php')) {
                $result = true;
            }
            break;
        case 'kb':
            if (is_plugin_active('kadence-blocks/kadence-blocks.php') || is_plugin_active('kadence-blocks-pro/kadence-blocks-pro.php')) {
                $result = true;
            }
            break;
        case 'wpdis':
                if (is_plugin_active('wpdiscuz/class.WpdiscuzCore.php')) {
                    $result = true;
                }
            break;
        case 'mpoet':
                if (is_plugin_active('mailpoet/mailpoet.php')) {
                    $result = true;
                }
            break;
            case 'forminator':
                if (is_plugin_active('forminator/forminator.php')) {
                    $result = true;
                }
            break;
            case 'bd':
                if (function_exists('\Breakdance\Forms\Actions\registerAction') && class_exists('\Breakdance\Forms\Actions\Action')) {
                    $result = true;
                }
            break;
            case 'bb':
                if (is_plugin_active('bb-plugin/fl-builder.php')) {
                    $result = true;
                }
            break;
            case 'umember':
                if (is_plugin_active('ultimate-member/ultimate-member.php')) {
                    $result = true;
                }
            break;
            case 'mpress':
                if (is_plugin_active('memberpress/memberpress.php')) {
                    $result = true;
                }
            break;
            case 'pmp':
                if (is_plugin_active('paid-memberships-pro/paid-memberships-pro.php')) {
                    $result = true;
                }
            break;
            case 'jform':
                if (is_plugin_active('jetpack/jetpack.php')) {
                    $result = true;
                }
            break;
            case 'mc4wp':
                if (is_plugin_active('mailchimp-for-wp/mailchimp-for-wp.php')) {
                    $result = true;
                }
            break;
            case 'sure':
                if (is_plugin_active('sureforms/sureforms.php')) {
                    $result = true;
                }
            break;
            case 'surecart':
                if (is_plugin_active('surecart/surecart.php')) {
                    $result = true;
                }
            break;
    }

    return $result;
}

function oopspam_is_spamprotection_enabled($form_builder) {
    $options = get_option('oopspamantispam_settings');
    $wp_config_constants = array(
        'forminator' => 'OOPSPAM_IS_FORMINATOR_ACTIVATED',
        'mpoet' => 'OOPSPAM_IS_MPOET_ACTIVATED',
        'mc4wp' => 'OOPSPAM_IS_MC4WP_ACTIVATED',
        'wpdis' => 'OOPSPAM_IS_WPDIS_ACTIVATED',
        'kb' => 'OOPSPAM_IS_KB_ACTIVATED',
        'nj' => 'OOPSPAM_IS_NJ_ACTIVATED',
        'pionet' => 'OOPSPAM_IS_PIONET_ACTIVATED',
        'ts' => 'OOPSPAM_IS_TS_ACTIVATED',
        'fable' => 'OOPSPAM_IS_FABLE_ACTIVATED',
        'gf' => 'OOPSPAM_IS_GF_ACTIVATED',
        'el' => 'OOPSPAM_IS_EL_ACTIVATED',
        'br' => 'OOPSPAM_IS_BR_ACTIVATED',
        'ws' => 'OOPSPAM_IS_WS_ACTIVATED',
        'wpf' => 'OOPSPAM_IS_WPF_ACTIVATED',
        'ff' => 'OOPSPAM_IS_FF_ACTIVATED',
        'cf7' => 'OOPSPAM_IS_CF7_ACTIVATED',
        'give' => 'OOPSPAM_IS_GIVE_ACTIVATED',
        'wpregister' => 'OOPSPAM_IS_WPREGISTER_ACTIVATED',
        'woo' => 'OOPSPAM_IS_WOO_ACTIVATED',
        'bb' => 'OOPSPAM_IS_BB_ACTIVATED',
        'umember' => 'OOPSPAM_IS_UMEMBER_ACTIVATED',
        'pmp' => 'OOPSPAM_IS_PMP_ACTIVATED',
        'mpress' => 'OOPSPAM_IS_MPRESS_ACTIVATED',
        'sure' => 'OOPSPAM_IS_SURE_ACTIVATED',
        'surecart' => 'OOPSPAM_IS_SURECART_ACTIVATED',
        'jform' => 'OOPSPAM_IS_JFORM_ACTIVATED'
    );

    // Check if there's a constant defined for this form builder
    if (isset($wp_config_constants[$form_builder]) && defined($wp_config_constants[$form_builder])) {
        return constant($wp_config_constants[$form_builder]);
    }

    // Map form builder to option name
    $option_map = array(
        'forminator' => 'oopspam_is_forminator_activated',
        'mpoet' => 'oopspam_is_mpoet_activated',
        'mc4wp' => 'oopspam_is_mc4wp_activated',
        'wpdis' => 'oopspam_is_wpdis_activated',
        'kb' => 'oopspam_is_kb_activated',
        'nj' => 'oopspam_is_nj_activated',
        'pionet' => 'oopspam_is_pionet_activated',
        'ts' => 'oopspam_is_ts_activated',
        'fable' => 'oopspam_is_fable_activated',
        'gf' => 'oopspam_is_gf_activated',
        'el' => 'oopspam_is_el_activated',
        'br' => 'oopspam_is_br_activated',
        'ws' => 'oopspam_is_ws_activated',
        'wpf' => 'oopspam_is_wpf_activated',
        'ff' => 'oopspam_is_ff_activated',
        'cf7' => 'oopspam_is_cf7_activated',
        'give' => 'oopspam_is_give_activated',
        'wpregister' => 'oopspam_is_wpregister_activated',
        'woo' => 'oopspam_is_woo_activated',
        'bb' => 'oopspam_is_bb_activated',
        'umember' => 'oopspam_is_umember_activated',
        'pmp' => 'oopspam_is_pmp_activated',
        'mpress' => 'oopspam_is_mpress_activated',
        'sure' => 'oopspam_is_sure_activated',
        'surecart' => 'oopspam_is_surecart_activated',
        'jform' => 'oopspam_is_jform_activated'
    );

    $option_name = isset($option_map[$form_builder]) ? $option_map[$form_builder] : $form_builder;
    return isset($options[$option_name]) && $options[$option_name];
}

function oopspamantispam_get_key() {
    // Check if the constant is defined in wp-config.php
    if (defined('OOPSPAM_API_KEY')) {
        return OOPSPAM_API_KEY;
    }

    // Fallback to GUI settings
    $options = get_option('oopspamantispam_settings');
    
    // Safely return the API key from options (avoids undefined index notices)
    return isset($options['oopspam_api_key']) ? $options['oopspam_api_key'] : '';
}

function oopspamantispam_get_spamscore_threshold()
{
    $options = get_option('oopspamantispam_settings');
    $currentThreshold = (isset($options['oopspam_spam_score_threshold'])) ? (int) $options['oopspam_spam_score_threshold'] : 3;
    return $currentThreshold;
}

function oopspamantispam_get_folder_for_spam()
{
    $options = get_option('oopspamantispam_settings');
    $currentFolder = (isset($options['oopspam_spam_movedspam_to_folder'])) ? $options['oopspam_spam_movedspam_to_folder'] : "spam";
    return $currentFolder;
}

function oopspamantispam_checkIfValidKey()
{
    $apiKey = oopspamantispam_get_key();
    if (empty($apiKey)) {
        return false;
    }
    return $apiKey;
}

// function oopspamantispam_get_IP_from_headers($var)
// {
//     if (getenv($var)) {
//         return getenv($var);
//     } elseif (isset($_SERVER[$var])) {
//         return $_SERVER[$var];
//     } else {
//         return '';
//     }
// }

function oopspamantispam_get_ip() {
    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');
    
    $ipaddress = '';
    if (!isset($privacyOptions['oopspam_is_check_for_ip']) || $privacyOptions['oopspam_is_check_for_ip'] !== true) {
        // Direct check for Cloudflare header via getallheaders() if available
        if (function_exists('getallheaders')) {
            $headers_list = getallheaders();
            if (isset($headers_list['CF-Connecting-IP'])) {
                $ipaddress = $headers_list['CF-Connecting-IP'];
            } else {
                // Headers might be case-insensitive
                foreach ($headers_list as $key => $value) {
                    if (strtolower($key) == 'cf-connecting-ip') {
                        $ipaddress = $value;
                        break;
                    }
                }
            }
        }
        
        // If we didn't get the IP from Cloudflare header directly, try server variables
        if (empty($ipaddress)) {
            $headers = [
                "HTTP_X_SUCURI_CLIENTIP", // Sucuri
                'HTTP_CF_CONNECTING_IP', // Cloudflare
                'HTTP_X_FORWARDED_FOR',
                'HTTP_X_FORWARDED',
                'HTTP_FORWARDED_FOR',
                'HTTP_FORWARDED',
                'HTTP_CLIENT_IP',
                'REMOTE_ADDR'
            ];
            
            foreach ($headers as $header) {
                if (!empty($_SERVER[$header])) {
                    $ipaddress = $_SERVER[$header];
                    break;
                }
            }
        }
        
        // If IP is a comma-separated list, get the first one
        if (strpos($ipaddress, ',') !== false) {
            $ipaddress = trim(explode(',', $ipaddress)[0]);
        }
        
        // Validate IP address
        if (!filter_var($ipaddress, FILTER_VALIDATE_IP)) {
            $ipaddress = '::1'; // localhost IPv6
        }
    }
    
    return $ipaddress;
}

function oopspam_store_spam_submission($frmEntry, $reason)
{
    // Check if constant is defined in wp-config.php
    if (defined('OOPSPAM_DISABLE_LOCAL_LOGGING')) {
        if (OOPSPAM_DISABLE_LOCAL_LOGGING) {
            return;
        }
    } else {
        // Fallback to settings option
        $options = get_option('oopspamantispam_settings');
        if (isset($options['oopspam_disable_local_logging'])) {
            return;
        }
    }
    global $wpdb;
    $table_name = $wpdb->prefix . 'oopspam_frm_spam_entries';
    $data = array(
        'message' => $frmEntry["Message"],
        'ip' => $frmEntry["IP"],
        'email' => $frmEntry["Email"],
        'score' => $frmEntry["Score"],
        'raw_entry' => $frmEntry["RawEntry"],
        'form_id' => $frmEntry["FormId"],
        'reason' => $reason
    );
    $format = array('%s', '%s', '%s', '%d', '%s', '%s', '%s');
    $wpdb->insert($table_name, $data, $format);
}

function oopspam_store_ham_submission($frmEntry)
{
    // Check if constant is defined in wp-config.php
    if (defined('OOPSPAM_DISABLE_LOCAL_LOGGING')) {
        if (OOPSPAM_DISABLE_LOCAL_LOGGING) {
            return;
        }
    } else {
        // Fallback to settings option
        $options = get_option('oopspamantispam_settings');
        if (isset($options['oopspam_disable_local_logging'])) {
            return;
        }
    }


    global $wpdb;

    $gclid = oopspam_get_gclid_from_url();

    $table_name = $wpdb->prefix . 'oopspam_frm_ham_entries';
    $data = array(
        'message' => $frmEntry["Message"],
        'ip' => $frmEntry["IP"],
        'email' => $frmEntry["Email"],
        'score' => $frmEntry["Score"],
        'raw_entry' => $frmEntry["RawEntry"],
        'form_id' => $frmEntry["FormId"],
        'gclid' => $gclid
    );
    $format = array('%s', '%s', '%s', '%d', '%s', '%s');
    $wpdb->insert($table_name, $data, $format);

}

function oopspam_get_gclid_from_url() {
    $referer_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    if (!empty($referer_url)) {
        $url_parts = wp_parse_url($referer_url);
        if (!empty($url_parts['query'])) {
            parse_str($url_parts['query'], $query_params);
            return isset($query_params['gclid']) ? sanitize_text_field($query_params['gclid']) : '';
        }
    }
    return '';
}
