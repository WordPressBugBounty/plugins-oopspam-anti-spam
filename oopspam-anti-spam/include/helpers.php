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
            if (is_plugin_active('ws-form-pro/ws-form.php')) {
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
    }

    return $result;
}

function oopspamantispam_get_key()
{
    $options = get_option('oopspamantispam_settings');
    return $options['oopspam_api_key'];
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
    //fetch the API key
    $apiKey = oopspamantispam_get_key();
    if ($apiKey == false || $apiKey == '' || empty($apiKey)) {
        return false;
    }

    return $apiKey;
}

function oopspamantispam_get_IP_from_headers($var)
{
    if (getenv($var)) {
        return getenv($var);
    } elseif (isset($_SERVER[$var])) {
        return $_SERVER[$var];
    } else {
        return '';
    }
}

function oopspamantispam_get_ip() {
    $options = get_option('oopspamantispam_settings');
    $privacyOptions = get_option('oopspamantispam_privacy_settings');
    
    $ipaddress = '';

    if (!isset($privacyOptions['oopspam_is_check_for_ip']) || $privacyOptions['oopspam_is_check_for_ip'] !== true) {
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'REMOTE_ADDR',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ipaddress = $_SERVER[$header];
                break;
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
    global $wpdb;
    $table_name = $wpdb->prefix . 'oopspam_frm_ham_entries';
    $data = array(
        'message' => $frmEntry["Message"],
        'ip' => $frmEntry["IP"],
        'email' => $frmEntry["Email"],
        'score' => $frmEntry["Score"],
        'raw_entry' => $frmEntry["RawEntry"],
        'form_id' => $frmEntry["FormId"],
    );
    $format = array('%s', '%s', '%s', '%d', '%s', '%s');
    $wpdb->insert($table_name, $data, $format);

}
