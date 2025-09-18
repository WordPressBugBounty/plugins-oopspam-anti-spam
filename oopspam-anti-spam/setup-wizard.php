<?php
/**
 * OOPSpam Anti-Spam Setup Wizard
 * 
 * This file contains the setup wizard functionality to help users
 * get started with the OOPSpam plugin quickly and efficiently.
 */

if (!function_exists('add_action')) {
    die();
}

/**
 * Register the setup wizard page
 */
function oopspam_register_setup_wizard() {
    // Add hidden page for redirects
    add_submenu_page(
        '', // Empty string for a hidden page, not null
        'OOPSpam Setup Wizard',
        'Setup Wizard',
        'manage_options',
        'oopspam_setup_wizard',
        'oopspam_setup_wizard_content'
    );
    
    // Add visible link at the bottom of the menu
    add_submenu_page(
        'wp_oopspam_settings_page', // Parent menu slug
        'OOPSpam Setup Wizard',
        '↺ Setup Wizard',
        'manage_options',
        'oopspam_setup_wizard',
        'oopspam_setup_wizard_content'
    );
}
// Use a high priority number (99) to ensure the Setup Wizard link appears at the bottom of the menu
add_action('admin_menu', 'oopspam_register_setup_wizard', 99);

/**
 * Check if the setup wizard has been completed
 */
function oopspam_is_wizard_completed() {
    return get_option('oopspam_wizard_completed', false);
}

/**
 * Add styling for the Setup Wizard menu item
 */
function oopspam_admin_menu_styles() {
    wp_enqueue_style('oopspam-admin-menu', plugin_dir_url(__FILE__) . 'include/admin-menu.css', array(), '1.0.0');
}
add_action('admin_enqueue_scripts', 'oopspam_admin_menu_styles');

/**
 * Redirect to setup wizard on plugin activation if not completed
 */
function oopspam_maybe_redirect_to_wizard() {
    // Check and mark wizard completed first if API key exists
    if (oopspam_has_api_key() && !oopspam_is_wizard_completed()) {
        update_option('oopspam_wizard_completed', true);
    }
    
    // Only redirect if it's a single activation, wizard not completed, API key doesn't exist, and no previous redirect
    if (isset($_GET['activate-multi']) || 
        oopspam_is_wizard_completed() || 
        oopspam_has_api_key() || 
        get_transient('oopspam_activation_redirect')) {
        return;
    }

    // Set transient to prevent multiple redirects
    set_transient('oopspam_activation_redirect', true, 30);
    
    // Redirect to setup wizard
    wp_redirect(admin_url('admin.php?page=oopspam_setup_wizard'));
    exit;
}
add_action('admin_init', 'oopspam_maybe_redirect_to_wizard');

/**
 * Check if API key exists
 */
function oopspam_has_api_key() {
    $options = get_option('oopspamantispam_settings');
    $has_key = defined('OOPSPAM_API_KEY') || (isset($options['oopspam_api_key']) && !empty($options['oopspam_api_key']));
    return $has_key;
}

/**
 * Get a list of active form plugins
 */
function oopspam_get_active_form_plugins() {
    $form_plugins = array();
    
    // List of supported form plugins and their check functions
    $supported_plugins = array(
        'cf7' => 'Contact Form 7',
        'wpf' => 'WPForms',
        'nf' => 'Ninja Forms',
        'gf' => 'Gravity Forms',
        'ff' => 'Fluent Forms',
        'el' => 'Elementor Forms',
        'fable' => 'Formidable Forms',
        'br' => 'Bricks Forms',
        'bd' => 'Breakdance Forms',
        'ws' => 'WS Form',
        'wpdis' => 'WPDiscuz',
        'kb' => 'Kadence Forms',
        'pionet' => 'Piotnet Forms',
        'ts' => 'Toolset Forms',
        'happyforms' => 'Happy Forms',
        'give' => 'GiveWP',
        'wp-register' => 'WordPress Registration',
        'woo' => 'WooCommerce',
        'forminator' => 'Forminator',
        'bb' => 'Beaver Builder',
        'umember' => 'Ultimate Member',
        'mpress' => 'MemberPress',
        'jform' => 'Jetpack Form',
        'mc4wp' => 'Mailchimp for WordPress',
        'mpoet' => 'MailPoet',
        'quform' => 'Quform',
        'surecart' => 'SureCart',
        'sure' => 'SureForms',
    );
    
    foreach ($supported_plugins as $key => $name) {
        if (oopspamantispam_plugin_check($key)) {
            $form_plugins[$key] = $name;
        }
    }
    
    return $form_plugins;
}

/**
 * Process wizard form submissions via AJAX
 */
function oopspam_process_wizard_step() {
    // Check nonce for security
    check_ajax_referer('oopspam_wizard_nonce', 'nonce');
    
    // Check for permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
        return;
    }
    
    $step = isset($_POST['step']) ? sanitize_text_field($_POST['step']) : '';
    $options = get_option('oopspamantispam_settings', array());
    
    // Process different steps
    switch ($step) {
        case 'api_key':
            if (isset($_POST['api_key']) && !empty($_POST['api_key'])) {
                $api_key = sanitize_text_field($_POST['api_key']);
                $options['oopspam_api_key'] = $api_key;
                update_option('oopspamantispam_settings', $options);
                wp_send_json_success('API key saved successfully');
            } else {
                wp_send_json_error('API key cannot be empty');
            }
            break;
            
        case 'form_protection':
            if (isset($_POST['forms']) && is_array($_POST['forms'])) {
                foreach ($_POST['forms'] as $form_id) {
                    $form_id = sanitize_text_field($form_id);
                    $option_name = 'oopspam_is_' . $form_id . '_activated';
                    $options[$option_name] = 1;
                    
                    // Handle WooCommerce enhanced protection options
                    if ($form_id === 'woo') {
                        // Check for WooCommerce enhanced protection options
                        if (isset($_POST['woo_enhanced_options']) && is_array($_POST['woo_enhanced_options'])) {
                            foreach ($_POST['woo_enhanced_options'] as $woo_option) {
                                if ($woo_option === 'check_origin') {
                                    $options['oopspam_woo_check_origin'] = 1;
                                }
                                if ($woo_option === 'require_device_type') {
                                    $options['oopspam_woo_require_device_type'] = 1;
                                }
                            }
                        }
                    }
                }
                update_option('oopspamantispam_settings', $options);
                wp_send_json_success('Form protection settings saved');
            } else {
                // No forms selected, but that's okay - just continue
                wp_send_json_success('No forms selected');
            }
            break;
            
        case 'country_filter':
            $filter_type = isset($_POST['filter_type']) ? sanitize_text_field($_POST['filter_type']) : '';
            
            if ($filter_type === 'allowlist') {
                // Ensure we have an array of countries
                $countries = isset($_POST['countries']) && is_array($_POST['countries']) 
                    ? array_map('sanitize_text_field', $_POST['countries']) 
                    : array();
                
                // Always store as an array, even if empty
                update_option('oopspam_countryallowlist', $countries);
                
                // Clear blocklist if switching to allowlist mode
                delete_option('oopspam_countryblocklist');
            }
            else if ($filter_type === 'blocklist') {
                // Ensure we have an array of countries
                $countries = isset($_POST['countries']) && is_array($_POST['countries']) 
                    ? array_map('sanitize_text_field', $_POST['countries']) 
                    : array();
                
                // Always store as an array, even if empty
                update_option('oopspam_countryblocklist', $countries);
                
                // Clear allowlist if switching to blocklist mode
                delete_option('oopspam_countryallowlist');
            } else if ($filter_type === 'skip') {
                // Skip was selected - do nothing, keep existing settings
            }
            
            // Mark wizard as completed
            update_option('oopspam_wizard_completed', true);
            wp_send_json_success('Country filter settings saved');
            break;
            
        case 'skip':
            // Mark wizard as completed
            update_option('oopspam_wizard_completed', true);
            wp_send_json_success('Wizard completed');
            break;
            
        default:
            wp_send_json_error('Unknown step');
            break;
    }
}
add_action('wp_ajax_oopspam_process_wizard_step', 'oopspam_process_wizard_step');

/**
 * Display the setup wizard content
 */
function oopspam_setup_wizard_content() {
    // Enqueue required scripts and styles
    wp_enqueue_style('oopspam-wizard-style', plugin_dir_url(__FILE__) . 'include/oopspam-wizard.css', array(), '1.0.0');
    wp_enqueue_style('tom-select', plugin_dir_url(__FILE__) . 'include/libs/tom-select.min.css', array(), '1.0.0');
    
    wp_enqueue_script('tom-select', plugin_dir_url(__FILE__) . 'include/libs/tom-select.complete.min.js', array('jquery'), '1.0.0', true);
    wp_enqueue_script('oopspam-helper-script', plugin_dir_url(__FILE__) . 'include/helper.js', array('jquery', 'tom-select'), '1.0.0', true);
    wp_enqueue_script('oopspam-wizard-script', plugin_dir_url(__FILE__) . 'include/oopspam-wizard.js', array('jquery', 'tom-select', 'oopspam-helper-script'), '1.0.0', true);
    wp_localize_script('oopspam-wizard-script', 'oopspam_wizard', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('oopspam_wizard_nonce'),
        'settings_url' => admin_url('admin.php?page=wp_oopspam_settings_page'),
        'debug' => defined('WP_DEBUG') && WP_DEBUG
    ));
    
    // Include country list if needed
    include_once dirname(__FILE__) . '/include/oopspam-country-list.php';
    
    // Get form plugins
    $form_plugins = oopspam_get_active_form_plugins();
    
    // Check if API key exists
    $has_api_key = oopspam_has_api_key();
    
    // Start wizard HTML
    ?>
    <div class="oopspam-wizard-container">
        <div class="oopspam-wizard-header">
            <img src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'include/oopspam-logo.png'); ?>" alt="OOPSpam Logo" class="oopspam-logo">
            <h1>Welcome to OOPSpam Anti-Spam</h1>
            <p>Let's set up your spam protection in a few simple steps</p>
        </div>
        
        <div class="oopspam-wizard-progress">
            <div class="oopspam-progress-step active" data-step="1">1</div>
            <div class="oopspam-progress-line"></div>
            <div class="oopspam-progress-step" data-step="2">2</div>
            <div class="oopspam-progress-line"></div>
            <div class="oopspam-progress-step" data-step="3">3</div>
        </div>
        
        <div class="oopspam-wizard-body">
            <!-- Step 1: API Key -->
            <div class="oopspam-wizard-step active" id="oopspam-step-1">
                <h2>Add Your API Key</h2>
                <p>To get started with OOPSpam, you need an API key. This key allows your website to connect to our spam detection service.</p>
                
                <?php if ($has_api_key): ?>
                    <div class="oopspam-success-message">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <p>API key is already configured.</p>
                    </div>
                    <button class="button button-primary oopspam-next-button" data-step="1" data-action="next">Continue</button>
                <?php else: ?>
                    <div class="oopspam-form-group">
                        <label for="oopspam-api-key">Your OOPSpam API Key:</label>
                        <input type="password" id="oopspam-api-key" class="regular-text" placeholder="Enter your API key here">
                        <p class="description">Don't have an API key? <a href="https://app.oopspam.com/Identity/Account/Register" target="_blank">Create a free account</a> to get one.</p>
                    </div>
                    <div class="oopspam-wizard-buttons">
                        <button class="button button-primary oopspam-next-button" data-step="1" data-action="save">Save & Continue</button>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wp_oopspam_settings_page')); ?>" class="button">Skip Setup</a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Step 2: Form Protection -->
            <div class="oopspam-wizard-step" id="oopspam-step-2">
                <h2>Enable Spam Protection for Your Forms</h2>
                <p>Select which form plugins you want to protect with OOPSpam:</p>
                
                <?php if (empty($form_plugins)): ?>
                    <div class="oopspam-notice">
                        <p>No supported form plugins detected. If you install a supported form plugin later, you can enable protection in the OOPSpam settings.</p>
                    </div>
                    <button class="button button-primary oopspam-next-button" data-step="2" data-action="next">Continue</button>
                <?php else: ?>
                    <div class="oopspam-form-group">
                        <div class="oopspam-form-checkbox-group">
                            <?php foreach ($form_plugins as $key => $name): ?>
                            <div class="oopspam-checkbox-wrapper">
                                <label for="oopspam-form-<?php echo esc_attr($key); ?>"><?php echo esc_html($name); ?></label>
                                <input type="checkbox" class="oopspam-toggle" id="oopspam-form-<?php echo esc_attr($key); ?>" name="oopspam-forms[]" value="<?php echo esc_attr($key); ?>">
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- WooCommerce enhanced protection options -->
                        <div id="oopspam-woocommerce-options" style="display:none; margin-top: 20px; background-color: #f9f9f9; padding: 15px; border-radius: 5px; border-left: 4px solid #2271b1;">
                            <h3 style="margin-top: 0;"><span class="dashicons dashicons-shield-alt"></span> Enhanced WooCommerce Protection</h3>
                            
                            <div class="oopspam-under-attack-container" style="margin-bottom: 15px;">
                                <div class="oopspam-checkbox-wrapper" style="margin-bottom: 10px;">
                                    <label for="oopspam-woo-under-attack" style="font-weight: bold;">
                                        <input type="checkbox" id="oopspam-woo-under-attack" name="oopspam-woo-under-attack" value="1">
                                        I am experiencing spam orders
                                    </label>
                                </div>
                                <p class="description">Enable additional security measures to fight aggressive spam attacks on your store.</p>
                            </div>
                            
                            <div id="oopspam-woo-attack-options" style="display:none; padding-left: 15px; border-left: 3px solid #ddd;">
                                <div class="oopspam-checkbox-wrapper" style="margin-bottom: 10px;">
                                    <label for="oopspam-woo-check-origin">
                                        <input type="checkbox" id="oopspam-woo-check-origin" name="oopspam-woo-enhanced-options[]" value="check_origin">
                                        Block orders from unknown origin
                                    </label>
                                    <p class="description">Spam orders often don't have proper referrer information. This setting blocks submissions without valid origin.</p>
                                </div>
                                
                                <div class="oopspam-checkbox-wrapper" style="margin-bottom: 10px;">
                                    <label for="oopspam-woo-require-device-type">
                                        <input type="checkbox" id="oopspam-woo-require-device-type" name="oopspam-woo-enhanced-options[]" value="require_device_type">
                                        Require valid device type
                                    </label>
                                    <p class="description">Spam orders often use fake browsers that don't properly identify themselves. This setting enforces device type validation.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="oopspam-form-selection-notice" class="oopspam-notice oopspam-warning" style="display: none;">
                        <p><span class="dashicons dashicons-warning"></span> Please select at least one form to enable spam protection. Without selecting forms, no protection will be activated.</p>
                    </div>
                    <div class="oopspam-wizard-buttons">
                        <button class="button button-primary oopspam-next-button" data-step="2" data-action="save">Save & Continue</button>
                        <button class="button oopspam-prev-button" data-step="2">Previous</button>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Step 3: Country Filtering -->
            <div class="oopspam-wizard-step" id="oopspam-step-3">
                <h2>Configure Country Filtering</h2>
                <p>Do you serve specific geographic regions or want to block specific countries?</p>
                
                <div class="oopspam-form-group">
                    <div class="oopspam-radio-group">
                        <?php
                        // Check if we have existing country settings
                        $allowed_countries = get_option('oopspam_countryallowlist', array());
                        $blocked_countries = get_option('oopspam_countryblocklist', array());
                        
                        // Ensure they're arrays
                        if (!is_array($allowed_countries)) {
                            $allowed_countries = empty($allowed_countries) ? array() : array($allowed_countries);
                        }
                        if (!is_array($blocked_countries)) {
                            $blocked_countries = empty($blocked_countries) ? array() : array($blocked_countries);
                        }
                        
                        $has_allowlist = !empty($allowed_countries);
                        $has_blocklist = !empty($blocked_countries);
                        
                        // Set default selection based on existing settings
                        $specific_checked = $has_allowlist ? 'checked' : '';
                        $international_checked = ($has_blocklist && !$has_allowlist) ? 'checked' : '';
                        $skip_checked = (!$has_allowlist && !$has_blocklist) ? 'checked' : '';
                        ?>
                        <div class="oopspam-radio-option">
                            <input type="radio" id="oopspam-region-specific" name="oopspam-region" value="specific" <?php echo $specific_checked; ?>>
                            <label for="oopspam-region-specific">I serve specific regions</label>
                            <p class="description">Use Country Allowlist to only allow submissions from specific countries</p>
                        </div>
                        <div class="oopspam-radio-option">
                            <input type="radio" id="oopspam-region-international" name="oopspam-region" value="international" <?php echo $international_checked; ?>>
                            <label for="oopspam-region-international">I serve internationally</label>
                            <p class="description">Use Country Blocklist to block submissions from problematic countries</p>
                        </div>
                        <div class="oopspam-radio-option">
                            <input type="radio" id="oopspam-region-skip" name="oopspam-region" value="skip" <?php echo $skip_checked; ?>>
                            <label for="oopspam-region-skip">Skip this step</label>
                            <p class="description">You can configure country filtering later in settings</p>
                        </div>
                    </div>
                </div>
                
                <div class="oopspam-country-selection" id="oopspam-allowlist-container" style="display: none;">
                    <h3>Select Countries to Allow</h3>
                    <p>Submissions from countries not on this list will be treated as spam:</p>
                    <select id="oopspam-allowlist-countries" class="oopspam-country-select" multiple="multiple" data-placeholder="Choose countries...">
                        <optgroup label="(de)select all countries">
                        <?php 
                        $countrylist = oopspam_get_isocountries();
                        // Get existing allowlist countries and ensure it's an array
                        $allowed_countries = get_option('oopspam_countryallowlist', array());
                        if (!is_array($allowed_countries)) {
                            $allowed_countries = empty($allowed_countries) ? array() : array($allowed_countries);
                        }
                        
                        foreach ($countrylist as $code => $name) {
                            $selected = in_array($code, $allowed_countries) ? 'selected="selected"' : '';
                            echo '<option value="' . esc_attr($code) . '" ' . $selected . '>' . esc_html($name) . '</option>';
                        }
                        ?>
                        </optgroup>
                    </select>
                    <p class="description">When countries are selected, ONLY submissions from these countries will be processed.</p>
                </div>
                
                <div class="oopspam-country-selection" id="oopspam-blocklist-container" style="display: none;">
                    <h3>Select Countries to Block</h3>
                    <p>Submissions from these countries will be treated as spam:</p>
                    <select id="oopspam-blocklist-countries" class="oopspam-country-select" multiple="multiple" data-placeholder="Choose countries...">
                        <optgroup label="(de)select all countries">
                        <?php 
                        $countrylist = oopspam_get_isocountries();
                        // Get existing blocklist countries and ensure it's an array
                        $blocked_countries = get_option('oopspam_countryblocklist', array());
                        if (!is_array($blocked_countries)) {
                            $blocked_countries = empty($blocked_countries) ? array() : array($blocked_countries);
                        }
                        
                        foreach ($countrylist as $code => $name) {
                            $selected = in_array($code, $blocked_countries) ? 'selected="selected"' : '';
                            echo '<option value="' . esc_attr($code) . '" ' . $selected . '>' . esc_html($name) . '</option>';
                        }
                        ?>
                        </optgroup>
                    </select>
                    <div class="oopspam-quick-buttons">
                        <button type="button" id="spam-countries-wizard" class="button">Add China and Russia</button>
                        <button type="button" id="african-countries-wizard" class="button">Add countries in Africa</button>
                        <button type="button" id="eu-countries-wizard" class="button">Add countries in the EU</button>
                    </div>
                    <p class="description">Submissions from selected countries will be treated as spam.</p>
                </div>
                
                <div class="oopspam-wizard-buttons">
                    <button class="button button-primary oopspam-finish-button" data-step="3" data-action="save">Complete Setup</button>
                    <button class="button oopspam-prev-button" data-step="3">Previous</button>
                </div>
            </div>
            
            <!-- Completion Step -->
            <div class="oopspam-wizard-step" id="oopspam-step-complete">
                <div class="oopspam-completion-message">
                    <span class="dashicons dashicons-yes"></span>
                    <h2>Setup Complete!</h2>
                    <p>You've successfully set up OOPSpam Anti-Spam. Your website is now protected against spam submissions.</p>
                </div>
                
                <div class="oopspam-next-steps">
                    <h3>Next Steps:</h3>
                    <ul>
                        <li><p><span class="dashicons dashicons-admin-settings"></span> <a href="<?php echo esc_url(admin_url('admin.php?page=wp_oopspam_settings_page')); ?>">Visit the settings page</a> to fine-tune your configuration</p></li>
                        <li><p><span class="dashicons dashicons-shield"></span> Learn more about <a href="https://help.oopspam.com/wordpress/" target="_blank">advanced protection options</a></p></li>
                        <li><p><span class="dashicons dashicons-chart-area"></span> Monitor submissions in the local <a href="<?php echo esc_url(admin_url('admin.php?page=wp_oopspam_frm_spam_entries')); ?>">Form Spam Entries</a> and <a href="<?php echo esc_url(admin_url('admin.php?page=wp_oopspam_frm_ham_entries')); ?>">Form Valid Entries</a> tables. You can also enable <a href="<?php echo esc_url(admin_url('admin.php?page=wp_oopspam_settings_page')); ?>">Log submissions to OOPSpam</a> setting to track submissions in the OOPSpam dashboard</p></li>
                    </ul>
                </div>
                
                <div class="oopspam-wizard-buttons">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=wp_oopspam_settings_page&from_wizard=1')); ?>" class="button button-primary">Go to OOPSpam Settings</a>
                    <a href="<?php echo esc_url(admin_url('index.php')); ?>" class="button">Return to Dashboard</a>
                </div>
            </div>
        </div>
        
        <div class="oopspam-wizard-footer">
            <p>Need help? Visit our <a href="https://help.oopspam.com/wordpress/" target="_blank">documentation</a> or contact <a href="mailto:support@oopspam.com">support@oopspam.com</a></p>
        </div>
    </div>
    <?php
}
