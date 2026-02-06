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
            case 'quform':
                if (is_plugin_active('quform/quform.php')) {
                    $result = true;
                }
            break;
            case 'happyforms':
                if (is_plugin_active('happyforms-upgrade/happyforms-upgrade.php')) {
                    $result = true;
                }
            break;
            case 'buddypress':
                if (is_plugin_active('buddypress/bp-loader.php') || function_exists('buddypress')) {
                    $result = true;
                }
            break;
            case 'avada':
                $theme = wp_get_theme(); // gets the current theme
                if ('Avada' == $theme->name || 'Avada' == $theme->parent_theme) {
                    $result = true;
                }
            break;
            case 'metform':
                if (is_plugin_active('metform/metform.php')) {
                    $result = true;
                }
            break;
            case 'acf':
                if (is_plugin_active('advanced-custom-fields/acf.php') || is_plugin_active('advanced-custom-fields-pro/acf.php')) {
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
        'jform' => 'OOPSPAM_IS_JFORM_ACTIVATED',
        'quform' => 'OOPSPAM_IS_QUFORM_ACTIVATED',
        'happyforms' => 'OOPSPAM_IS_HAPPYFORMS_ACTIVATED',
        'buddypress' => 'OOPSPAM_IS_BUDDYPRESS_ACTIVATED',
        'avada' => 'OOPSPAM_IS_AVADA_ACTIVATED',
        'metform' => 'OOPSPAM_IS_METFORM_ACTIVATED',
        'acf' => 'OOPSPAM_IS_ACF_ACTIVATED'
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
        'jform' => 'oopspam_is_jform_activated',
        'quform' => 'oopspam_is_quform_activated',
        'happyforms' => 'oopspam_is_happyforms_activated',
        'buddypress' => 'oopspam_is_buddypress_activated',
        'avada' => 'oopspam_is_avada_activated',
        'metform' => 'oopspam_is_metform_activated',
        'acf' => 'oopspam_is_acf_activated'
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

function oopspamantispam_get_ip() {
    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');
    
    $ipaddress = '';
    // When oopspam_is_check_for_ip is enabled, we should NOT capture IP addresses for privacy
    // Check for both string "on" (from checkbox) and boolean true
    if (isset($privacyOptions['oopspam_is_check_for_ip']) && 
        ($privacyOptions['oopspam_is_check_for_ip'] === true || $privacyOptions['oopspam_is_check_for_ip'] === 'on')) {
        return '';
    }
    
    // Get the actual remote address first
    $remote_addr = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // Only trust proxy headers if explicitly configured to do so
    $trust_proxy_headers = false;
    
    // Check wp-config.php constant first (takes precedence)
    if (defined('OOPSPAM_TRUST_PROXY_HEADERS')) {
        $trust_proxy_headers = OOPSPAM_TRUST_PROXY_HEADERS;
    } else {
        // Check UI setting
        $misc_options = get_option('oopspamantispam_misc_settings');
        $trust_proxy_headers = isset($misc_options['oopspam_trust_proxy_headers']);
    }
    
    if ($trust_proxy_headers) {
        $headers = [
            'HTTP_CF_CONNECTING_IP',    // Cloudflare
            'HTTP_X_SUCURI_CLIENTIP',   // Sucuri
            'HTTP_TRUE_CLIENT_IP',      // Cloudflare Enterprise / Akamai
            'HTTP_FASTLY_CLIENT_IP',    // Fastly
            'HTTP_X_REAL_IP',           // Nginx proxy / Generic
            'HTTP_X_CLUSTER_CLIENT_IP', // Rackspace / Riverbed
            'HTTP_X_FORWARDED_FOR',     // Most proxies (AWS, Azure, etc.)
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'HTTP_CLIENT_IP'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ipaddress = $_SERVER[$header];
                break;
            }
        }
    }
    
    // Always fall back to REMOTE_ADDR if no proxy headers or not configured to trust them
    if (empty($ipaddress)) {
        $ipaddress = $remote_addr;
    }
    
    // If IP is a comma-separated list, get the first one
    if (strpos($ipaddress, ',') !== false) {
        $ipaddress = trim(explode(',', $ipaddress)[0]);
    }
    
    // Validate IP address
    if (!filter_var($ipaddress, FILTER_VALIDATE_IP)) {
        $ipaddress = '::1'; // localhost IPv6
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
    
    // Enrich raw entry with HTTP headers and request metadata
    $enriched_raw_entry = oopspam_enrich_raw_entry($frmEntry["RawEntry"]);
    
    $data = array(
        'message' => $frmEntry["Message"],
        'ip' => $frmEntry["IP"],
        'email' => $frmEntry["Email"],
        'score' => $frmEntry["Score"],
        'raw_entry' => $enriched_raw_entry,
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
    
    // Enrich raw entry with HTTP headers and request metadata
    $enriched_raw_entry = oopspam_enrich_raw_entry($frmEntry["RawEntry"]);

    $table_name = $wpdb->prefix . 'oopspam_frm_ham_entries';
    $data = array(
        'message' => $frmEntry["Message"],
        'ip' => $frmEntry["IP"],
        'email' => $frmEntry["Email"],
        'score' => $frmEntry["Score"],
        'raw_entry' => $enriched_raw_entry,
        'form_id' => $frmEntry["FormId"],
        'gclid' => $gclid
    );
    $format = array('%s', '%s', '%s', '%d', '%s', '%s', '%s');
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

/**
 * Captures HTTP headers and other request metadata useful for spam/abuse/fraud detection.
 * This information is stored alongside form entries to help with analysis and debugging.
 *
 * @return array Associative array containing request metadata
 */
function oopspam_get_request_metadata() {
    $metadata = array();
    
    // User Agent 
    $metadata['user_agent'] = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
    
    // Referer header
    $metadata['referer'] = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : '';
    
    // Request Method
    $metadata['request_method'] = isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field($_SERVER['REQUEST_METHOD']) : '';
    
    // Accept Language 
    $metadata['accept_language'] = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? sanitize_text_field($_SERVER['HTTP_ACCEPT_LANGUAGE']) : '';
    
    // Accept Encoding
    $metadata['accept_encoding'] = isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? sanitize_text_field($_SERVER['HTTP_ACCEPT_ENCODING']) : '';
    
    // Accept header
    $metadata['accept'] = isset($_SERVER['HTTP_ACCEPT']) ? sanitize_text_field($_SERVER['HTTP_ACCEPT']) : '';
    
    // Content Type
    $metadata['content_type'] = isset($_SERVER['CONTENT_TYPE']) ? sanitize_text_field($_SERVER['CONTENT_TYPE']) : '';
    
    // Origin header 
    $metadata['origin'] = isset($_SERVER['HTTP_ORIGIN']) ? esc_url_raw($_SERVER['HTTP_ORIGIN']) : '';
    
    // X-Requested-With 
    $metadata['x_requested_with'] = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? sanitize_text_field($_SERVER['HTTP_X_REQUESTED_WITH']) : '';
    
    // Connection type
    $metadata['connection'] = isset($_SERVER['HTTP_CONNECTION']) ? sanitize_text_field($_SERVER['HTTP_CONNECTION']) : '';
    
    // Request URI/Page 
    $metadata['request_uri'] = isset($_SERVER['REQUEST_URI']) ? esc_url_raw($_SERVER['REQUEST_URI']) : '';
    
    // Host
    $metadata['host'] = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field($_SERVER['HTTP_HOST']) : '';
    
    // Sec-Fetch headers (modern browsers) 
    $metadata['sec_fetch_mode'] = isset($_SERVER['HTTP_SEC_FETCH_MODE']) ? sanitize_text_field($_SERVER['HTTP_SEC_FETCH_MODE']) : '';
    $metadata['sec_fetch_site'] = isset($_SERVER['HTTP_SEC_FETCH_SITE']) ? sanitize_text_field($_SERVER['HTTP_SEC_FETCH_SITE']) : '';
    $metadata['sec_fetch_dest'] = isset($_SERVER['HTTP_SEC_FETCH_DEST']) ? sanitize_text_field($_SERVER['HTTP_SEC_FETCH_DEST']) : '';
    $metadata['sec_fetch_user'] = isset($_SERVER['HTTP_SEC_FETCH_USER']) ? sanitize_text_field($_SERVER['HTTP_SEC_FETCH_USER']) : '';
    
    // Sec-CH-UA headers (Client Hints) 
    $metadata['sec_ch_ua'] = isset($_SERVER['HTTP_SEC_CH_UA']) ? sanitize_text_field($_SERVER['HTTP_SEC_CH_UA']) : '';
    $metadata['sec_ch_ua_mobile'] = isset($_SERVER['HTTP_SEC_CH_UA_MOBILE']) ? sanitize_text_field($_SERVER['HTTP_SEC_CH_UA_MOBILE']) : '';
    $metadata['sec_ch_ua_platform'] = isset($_SERVER['HTTP_SEC_CH_UA_PLATFORM']) ? sanitize_text_field($_SERVER['HTTP_SEC_CH_UA_PLATFORM']) : '';
    
    // Cache-Control
    $metadata['cache_control'] = isset($_SERVER['HTTP_CACHE_CONTROL']) ? sanitize_text_field($_SERVER['HTTP_CACHE_CONTROL']) : '';
    
    // DNT (Do Not Track)
    $metadata['dnt'] = isset($_SERVER['HTTP_DNT']) ? sanitize_text_field($_SERVER['HTTP_DNT']) : '';
    
    // Cloudflare-specific 
    $metadata['cf_ray'] = isset($_SERVER['HTTP_CF_RAY']) ? sanitize_text_field($_SERVER['HTTP_CF_RAY']) : '';
    $metadata['cf_ipcountry'] = isset($_SERVER['HTTP_CF_IPCOUNTRY']) ? sanitize_text_field($_SERVER['HTTP_CF_IPCOUNTRY']) : '';
    $metadata['cf_visitor'] = isset($_SERVER['HTTP_CF_VISITOR']) ? sanitize_text_field($_SERVER['HTTP_CF_VISITOR']) : '';
    
    // Proxy-related headers 
    $metadata['x_forwarded_for'] = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR']) : '';
    $metadata['x_forwarded_proto'] = isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? sanitize_text_field($_SERVER['HTTP_X_FORWARDED_PROTO']) : '';
    $metadata['x_real_ip'] = isset($_SERVER['HTTP_X_REAL_IP']) ? sanitize_text_field($_SERVER['HTTP_X_REAL_IP']) : '';
    
    // Server port and protocol
    $metadata['server_port'] = isset($_SERVER['SERVER_PORT']) ? intval($_SERVER['SERVER_PORT']) : '';
    $metadata['server_protocol'] = isset($_SERVER['SERVER_PROTOCOL']) ? sanitize_text_field($_SERVER['SERVER_PROTOCOL']) : '';
    $metadata['https'] = isset($_SERVER['HTTPS']) ? sanitize_text_field($_SERVER['HTTPS']) : '';
    
    // Timestamp of the request (server time)
    $metadata['request_time'] = isset($_SERVER['REQUEST_TIME']) ? intval($_SERVER['REQUEST_TIME']) : time();
    $metadata['request_time_float'] = isset($_SERVER['REQUEST_TIME_FLOAT']) ? floatval($_SERVER['REQUEST_TIME_FLOAT']) : microtime(true);
    
    // Remote port 
    $metadata['remote_port'] = isset($_SERVER['REMOTE_PORT']) ? intval($_SERVER['REMOTE_PORT']) : '';
    
    // Remove empty values to keep the data clean
    $metadata = array_filter($metadata, function($value) {
        return $value !== '' && $value !== null;
    });
    
    return $metadata;
}

/**
 * Enriches a raw entry with HTTP headers and request metadata.
 *
 * @param string $raw_entry The original raw entry (JSON encoded form data)
 * @return string JSON encoded data with form fields and request metadata
 */
function oopspam_enrich_raw_entry($raw_entry) {
    // Decode the original raw entry
    $form_data = json_decode($raw_entry, true);
    
    // If decoding failed, use the raw string as-is
    if ($form_data === null) {
        $form_data = $raw_entry;
    }
    
    // Get request metadata
    $metadata = oopspam_get_request_metadata();
    
    // Create enriched entry structure
    $enriched_entry = array(
        'form_fields' => $form_data,
        'request_metadata' => $metadata
    );
    
    return json_encode($enriched_entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
