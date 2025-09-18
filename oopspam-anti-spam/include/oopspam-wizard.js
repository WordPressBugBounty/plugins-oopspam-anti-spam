/**
 * OOPSpam Setup Wizard JavaScript
 */
(function($) {
    'use strict';
    
    // Initialize when document is ready
    $(document).ready(function() {
        // Initialize wizard functionality
        initWizard();
        
        // Wait a brief moment to ensure DOM is fully loaded
        setTimeout(function() {
            // Initialize country selection dropdowns if Tom Select is available
            initCountrySelects();
            
            // Handle country filtering option selection
            handleCountryFilteringOptions();
            
            // Show the correct country container based on initial radio selection
            const selectedRegionType = $('input[name="oopspam-region"]:checked').val();
            if (selectedRegionType === 'specific') {
                $('#oopspam-allowlist-container').show();
            } else if (selectedRegionType === 'international') {
                $('#oopspam-blocklist-container').show();
            }
            
            // Handle WooCommerce options visibility
            handleWooCommerceOptions();
        }, 100);
    });
    
    /**
     * Initialize the wizard navigation and button handlers
     */
    function initWizard() {
        // Next button handler
        $('.oopspam-next-button').on('click', function() {
            const step = $(this).data('step');
            const action = $(this).data('action');
            
            // If action is save, process the form data
            if (action === 'save') {
                processStep(step);
            } else {
                // Just navigate to next step
                navigateToStep(step + 1);
            }
        });
        
        // Form selection change handler
        $('input[name="oopspam-forms[]"]').on('change', function() {
            // Check if any forms are selected
            if ($('input[name="oopspam-forms[]"]:checked').length === 0) {
                $('#oopspam-form-selection-notice').fadeIn();
            } else {
                $('#oopspam-form-selection-notice').fadeOut();
            }
        });
        
        // Previous button handler
        $('.oopspam-prev-button').on('click', function() {
            const step = $(this).data('step');
            navigateToStep(step - 1);
        });
        
        // Finish button handler
        $('.oopspam-finish-button').on('click', function() {
            const step = $(this).data('step');
            processStep(step);
        });

        // Settings page link handler - add from_wizard parameter
        $('.oopspam-wizard-buttons a[href*="wp_oopspam_settings_page"]').each(function() {
            let href = $(this).attr('href');
            if (href.indexOf('from_wizard=1') === -1) {
                href += (href.indexOf('?') !== -1 ? '&' : '?') + 'from_wizard=1';
                $(this).attr('href', href);
            }
        });
    }
    
    /**
     * Process the current step's form data
     */
    function processStep(step) {
        // Add loading state to button
        const $button = $('[data-step="' + step + '"][data-action="save"], .oopspam-finish-button[data-step="' + step + '"]');
        $button.addClass('oopspam-loading').prop('disabled', true);
        
        // Prepare data based on step
        let data = {
            action: 'oopspam_process_wizard_step',
            nonce: oopspam_wizard.nonce,
            step: ''
        };
        
        // Process specific step data
        switch (parseInt(step)) {
            case 1:
                // API Key step
                data.step = 'api_key';
                data.api_key = $('#oopspam-api-key').val();
                break;
                
            case 2:
                // Form protection step
                data.step = 'form_protection';
                data.forms = [];
                data.woo_enhanced_options = [];
                
                // Get selected form plugins
                $('input[name="oopspam-forms[]"]:checked').each(function() {
                    data.forms.push($(this).val());
                });
                
                // Check if WooCommerce is selected and get enhanced options
                if ($('#oopspam-form-woo').is(':checked') && $('#oopspam-woo-under-attack').is(':checked')) {
                    // Check which enhanced options are selected
                    $('input[name="oopspam-woo-enhanced-options[]"]:checked').each(function() {
                        data.woo_enhanced_options.push($(this).val());
                    });
                }
                
                // Check if at least one form is selected
                if (data.forms.length === 0) {
                    // Show warning
                    $('#oopspam-form-selection-notice').fadeIn();
                    
                    // Ask user to confirm if they want to continue without selecting any forms
                    if (!confirm('You haven\'t selected any forms to protect. No spam protection will be enabled. Do you want to continue anyway?')) {
                        $button.removeClass('oopspam-loading').prop('disabled', false);
                        return false;
                    }
                } else {
                    // Hide warning if it was shown
                    $('#oopspam-form-selection-notice').hide();
                }
                break;
                
            case 3:
                // Country filtering step
                data.step = 'country_filter';
                const regionType = $('input[name="oopspam-region"]:checked').val();
                
                if (regionType === 'specific') {
                    data.filter_type = 'allowlist';
                    data.countries = getSelectedCountries('allowlist');
                } else if (regionType === 'international') {
                    data.filter_type = 'blocklist';
                    data.countries = getSelectedCountries('blocklist');
                } else {
                    // Skip this step
                    data.step = 'skip';
                }
                break;
        }
        
        // Debug logging if enabled
        if (oopspam_wizard.debug) {
            console.log('Sending data to:', oopspam_wizard.ajax_url);
            console.log('Data:', data);
        }
        
        // Send AJAX request
        $.post(oopspam_wizard.ajax_url, data, function(response) {
            $button.removeClass('oopspam-loading').prop('disabled', false);
            
            if (oopspam_wizard.debug) {
                console.log('Response:', response);
            }
            
            if (response.success) {
                if (step === 3) {
                    // If we've completed the last step, show completion page
                    navigateToStep('complete');
                    
                    // Update all links to include from_wizard parameter
                    $('.oopspam-wizard-buttons a[href*="wp_oopspam_settings_page"]').each(function() {
                        let href = $(this).attr('href');
                        if (href.indexOf('from_wizard=1') === -1) {
                            href += (href.indexOf('?') !== -1 ? '&' : '?') + 'from_wizard=1';
                            $(this).attr('href', href);
                        }
                    });
                } else {
                    // Navigate to next step
                    navigateToStep(step + 1);
                }
            } else {
                // Show error message
                alert('Error: ' + (response.data || 'An error occurred while processing your request.'));
            }
        }).fail(function(xhr, status, error) {
            $button.removeClass('oopspam-loading').prop('disabled', false);
            console.error('AJAX error:', status, error, xhr.responseText);
            alert('A connection error occurred. Please try again. If the problem persists, check the console for more details.');
        });
    }
    
    /**
     * Get selected countries from a specific list
     */
    function getSelectedCountries(listType) {
        // If using regular select
        if (!$.fn.tomSelect) {
            return $('#oopspam-' + listType + '-countries').val() || [];
        }
        
        // If using Tom Select
        const tomSelect = $('#oopspam-' + listType + '-countries')[0].tomSelect;
        if (tomSelect) {
            return tomSelect.getValue();
        }
        
        return [];
    }
    
    /**
     * Navigate to a specific step
     */
    function navigateToStep(stepNumber) {
        // Hide all steps
        $('.oopspam-wizard-step').removeClass('active');
        
        // Show the target step
        if (stepNumber === 'complete') {
            $('#oopspam-step-complete').addClass('active');
            
            // Update progress indicators
            $('.oopspam-progress-step').addClass('completed');
            $('.oopspam-progress-line').addClass('active');
        } else {
            $('#oopspam-step-' + stepNumber).addClass('active');
            
            // Update progress indicators
            $('.oopspam-progress-step').removeClass('active completed');
            $('.oopspam-progress-line').removeClass('active');
            
            for (let i = 1; i <= stepNumber; i++) {
                if (i < stepNumber) {
                    $('.oopspam-progress-step[data-step="' + i + '"]').addClass('completed');
                } else {
                    $('.oopspam-progress-step[data-step="' + i + '"]').addClass('active');
                }
                
                if (i < stepNumber) {
                    // Activate progress line before this step
                    $('.oopspam-progress-step[data-step="' + i + '"]').next('.oopspam-progress-line').addClass('active');
                }
            }
        }
        
        // Scroll to top of wizard
        $('.oopspam-wizard-container').get(0).scrollIntoView({ behavior: 'smooth' });
    }
    
    /**
     * Initialize country selection dropdowns
     */
    function initCountrySelects() {
        // Create a global object to store TomSelect instances if it doesn't exist
        if (!window.tomSelects) {
            window.tomSelects = {};
        }
        
        // Initialize with Tom Select if available
        if (typeof TomSelect !== 'undefined') {
            try {
                // Initialize allowlist countries select
                if (document.getElementById('oopspam-allowlist-countries')) {
                    window.tomSelects['oopspam-allowlist-countries'] = new TomSelect('#oopspam-allowlist-countries', {
                        plugins: ['remove_button', 'optgroup_columns'],
                        placeholder: 'Select countries...',
                        create: false,
                        optgroupField: 'class',
                        optgroupLabelField: 'label',
                        optgroupValueField: 'value',
                        lockOptgroupOrder: true,
                        render: {
                            optgroup_header: function(data, escape) {
                                return '<div class="optgroup-header">' + escape(data.label) + '</div>';
                            }
                        }
                    });
                }
                
                // Initialize blocklist countries select
                if (document.getElementById('oopspam-blocklist-countries')) {
                    window.tomSelects['oopspam-blocklist-countries'] = new TomSelect('#oopspam-blocklist-countries', {
                        plugins: ['remove_button', 'optgroup_columns'],
                        placeholder: 'Select countries...',
                        create: false,
                        optgroupField: 'class',
                        optgroupLabelField: 'label',
                        optgroupValueField: 'value',
                        lockOptgroupOrder: true,
                        render: {
                            optgroup_header: function(data, escape) {
                                return '<div class="optgroup-header">' + escape(data.label) + '</div>';
                            }
                        }
                    });
                }
            } catch (e) {
                console.error('Error initializing TomSelect:', e);
            }
        }
    }
    
    /**
     * Handle country filtering option selection
     */
    function handleCountryFilteringOptions() {
        // Define African countries ISO codes
        const africanCountries = [
            "dz", "ao", "bj", "bw", "bf", "bi", "cm", "cv", "cf", "td", 
            "km", "cg", "cd", "dj", "eg", "gq", "er", "et", "ga", "gm", 
            "gh", "gn", "gw", "ci", "ke", "ls", "lr", "ly", "mg", "mw", 
            "ml", "mr", "mu", "ma", "mz", "na", "ne", "ng", "rw", "st", 
            "sn", "sc", "sl", "so", "za", "ss", "sd", "sz", "tz", "tg", 
            "tn", "ug", "zm", "zw"
        ];

        // Define EU countries ISO codes
        const euCountries = [
            "at", "be", "bg", "hr", "cy", "cz", "dk", "ee", "fi", "fr",
            "de", "gr", "hu", "ie", "it", "lv", "lt", "lu", "mt", "nl",
            "pl", "pt", "ro", "sk", "si", "es", "se"
        ];
        
        $('input[name="oopspam-region"]').on('change', function() {
            const value = $(this).val();
            
            // Hide both containers first
            $('#oopspam-allowlist-container, #oopspam-blocklist-container').hide();
            
            // Show the relevant container based on selection
            if (value === 'specific') {
                $('#oopspam-allowlist-container').show();
            } else if (value === 'international') {
                $('#oopspam-blocklist-container').show();
            }
        });

        // Add event handlers for quick-select buttons
        $("#spam-countries-wizard").click(function() {
            // Make sure we safely access the TomSelect instance
            try {
                // The element might be available but the TomSelect instance might not be initialized yet
                const selectElement = document.getElementById('oopspam-blocklist-countries');
                if (!selectElement) {
                    console.error("Select element not found");
                    return;
                }
                
                // Get TomSelect instance (checking for both possible implementations)
                let tomSelect;
                if (selectElement.tomselect) {
                    tomSelect = selectElement.tomselect;
                } else if (window.tomSelects && window.tomSelects['oopspam-blocklist-countries']) {
                    tomSelect = window.tomSelects['oopspam-blocklist-countries'];
                } else {
                    // As a fallback, try to reinitialize it
                    tomSelect = new TomSelect('#oopspam-blocklist-countries', {
                        plugins: ['remove_button', 'optgroup_columns'],
                        placeholder: 'Select countries...'
                    });
                }
                
                // Define spam countries directly to ensure they're available
                const spamCountries = ["ru", "cn"];
                
                // Get current selections
                const currentSelections = tomSelect.getValue();
                
                // Add spam countries to current selections
                const newSelections = [...new Set([...currentSelections, ...spamCountries])];
                tomSelect.setValue(newSelections);
            } catch (error) {
                console.error("Error adding spam countries:", error);
            }
        });
        
        $("#african-countries-wizard").click(function() {
            try {
                // The element might be available but the TomSelect instance might not be initialized yet
                const selectElement = document.getElementById('oopspam-blocklist-countries');
                if (!selectElement) {
                    console.error("Select element not found");
                    return;
                }
                
                // Get TomSelect instance (checking for both possible implementations)
                let tomSelect;
                if (selectElement.tomselect) {
                    tomSelect = selectElement.tomselect;
                } else if (window.tomSelects && window.tomSelects['oopspam-blocklist-countries']) {
                    tomSelect = window.tomSelects['oopspam-blocklist-countries'];
                } else {
                    // As a fallback, try to reinitialize it
                    tomSelect = new TomSelect('#oopspam-blocklist-countries', {
                        plugins: ['remove_button', 'optgroup_columns'],
                        placeholder: 'Select countries...'
                    });
                }
                
                // Get current selections
                const currentSelections = tomSelect.getValue();
                
                // Add African countries to current selections
                const newSelections = [...new Set([...currentSelections, ...africanCountries])];
                tomSelect.setValue(newSelections);
            } catch (error) {
                console.error("Error adding African countries:", error);
            }
        });
        
        $("#eu-countries-wizard").click(function() {
            try {
                // The element might be available but the TomSelect instance might not be initialized yet
                const selectElement = document.getElementById('oopspam-blocklist-countries');
                if (!selectElement) {
                    console.error("Select element not found");
                    return;
                }
                
                // Get TomSelect instance (checking for both possible implementations)
                let tomSelect;
                if (selectElement.tomselect) {
                    tomSelect = selectElement.tomselect;
                } else if (window.tomSelects && window.tomSelects['oopspam-blocklist-countries']) {
                    tomSelect = window.tomSelects['oopspam-blocklist-countries'];
                } else {
                    // As a fallback, try to reinitialize it
                    tomSelect = new TomSelect('#oopspam-blocklist-countries', {
                        plugins: ['remove_button', 'optgroup_columns'],
                        placeholder: 'Select countries...'
                    });
                }
                
                // Get current selections
                const currentSelections = tomSelect.getValue();
                
                // Add EU countries to current selections
                const newSelections = [...new Set([...currentSelections, ...euCountries])];
                tomSelect.setValue(newSelections);
            } catch (error) {
                console.error("Error adding EU countries:", error);
            }
        });
    }

    /**
     * Handle WooCommerce enhanced protection options
     */
    function handleWooCommerceOptions() {
        // Show WooCommerce options when WooCommerce checkbox is checked
        $('#oopspam-form-woo').on('change', function() {
            if ($(this).is(':checked')) {
                $('#oopspam-woocommerce-options').slideDown(300);
            } else {
                $('#oopspam-woocommerce-options').slideUp(300);
                
                // Uncheck all WooCommerce options when WooCommerce is unchecked
                $('#oopspam-woo-under-attack').prop('checked', false);
                $('#oopspam-woo-attack-options').slideUp(300);
                $('#oopspam-woo-check-origin, #oopspam-woo-require-device-type').prop('checked', false);
            }
        });
        
        // Toggle attack options when "under attack" checkbox is toggled
        $('#oopspam-woo-under-attack').on('change', function() {
            if ($(this).is(':checked')) {
                $('#oopspam-woo-attack-options').slideDown(300);
            } else {
                $('#oopspam-woo-attack-options').slideUp(300);
                $('#oopspam-woo-check-origin, #oopspam-woo-require-device-type').prop('checked', false);
            }
        });
    }
})(jQuery);
