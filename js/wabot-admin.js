jQuery(document).ready(function ($) {
    // Initialize the template preview modal once on page load
    if ($('#wabot-template-preview-modal').length === 0) {
        $('body').append(`
            <div id="wabot-template-preview-modal" class="wabot-modal">
                <div class="wabot-modal-content">
                    <div class="modal-header">
                        <h2>Template Preview</h2>
                        <button type="button" id="close-preview-modal" class="close-button">×</button>
                    </div>
                    <div id="template-preview-container"></div>
                </div>
            </div>
        `);
    }
    
    // Template enable/disable toggle functionality
    $(document).on('change', '[id^=template_][id$=_enabled]', function() {
        const isEnabled = $(this).prop('checked');
        const key = $(this).attr('id').replace('template_', '').replace('_enabled', '');
        const $label = $(this).closest('.template-toggle-container').find('.wabot-toggle-label');
        const $templateTrigger = $(this).closest('.wabot-form-group').find('.template-select-trigger');
        const $templateActions = $(this).closest('.wabot-form-group').find('.template-actions');
        const $actionButtons = $templateActions.find('button');
        
        // Update toggle label
        $label.text(isEnabled ? 'Enabled' : 'Disabled');
        
        // Toggle disabled class on the template selector and action buttons
        if (isEnabled) {
            $templateTrigger.removeClass('disabled');
            $templateActions.removeClass('disabled');
            $actionButtons.removeClass('disabled');
        } else {
            $templateTrigger.addClass('disabled');
            $templateActions.addClass('disabled');
            $actionButtons.addClass('disabled');
        }
        
        // Highlight the save button to indicate unsaved changes
        const $saveButton = $(this).closest('form').find('.wabot-button[type="submit"]');
        $saveButton.css({
            'background-color': '#f80',
            'box-shadow': '0 0 5px rgba(255, 136, 0, 0.5)'
        });
        
        // Add a small reminder to save changes
        if (!$('.save-reminder').length) {
            $saveButton.after('<span class="save-reminder" style="margin-left: 10px; color: #f80; font-style: italic;">Don\'t forget to save your changes!</span>');
        }
    });
    
    // Debug Templates button
    $(document).on('click', '#debug-templates-btn', function() {
        const $button = $(this);
        const originalText = $button.html();
        
        // Show loading state
        $button.html('<span class="dashicons dashicons-update" style="margin-right: 5px; animation: spin 1s linear infinite;"></span> Loading...');
        $button.prop('disabled', true);
        
        // Add spin animation if not already defined
        if (!document.getElementById('spin-animation')) {
            $('head').append(`
                <style id="spin-animation">
                    @keyframes spin {
                        0% { transform: rotate(0deg); }
                        100% { transform: rotate(360deg); }
                    }
                </style>
            `);
        }
        
        // Make AJAX request to get debug data
        $.ajax({
            url: wabotAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'wabot_debug_templates',
                nonce: wabotAdmin.nonce
            },
            success: function(response) {
                // Restore button
                $button.html(originalText);
                $button.prop('disabled', false);
                
                if (response.success) {
                    // Log data to console
                    console.group('Wabot Templates Debug');
                    console.log('Raw Templates from API:', response.data.raw_templates);
                    console.log('Processed Templates:', response.data.processed_templates);
                    console.log('Active Phone ID:', response.data.active_phone_id);
                    console.log('API Endpoint:', response.data.api_endpoint);
                    console.log('Template Enabled States:', response.data.template_enabled_states);
                    console.log('All Template Settings:', response.data.all_template_settings);
                    console.groupEnd();
                    
                    // Show notification
                    showNotification('Debug data logged to browser console (F12)', 'info');
                } else {
                    console.error('Error fetching debug data:', response);
                    showNotification('Error fetching debug data', 'error');
                }
            },
            error: function(xhr, status, error) {
                // Restore button
                $button.html(originalText);
                $button.prop('disabled', false);
                
                console.error('AJAX Error:', error);
                showNotification('Error connecting to server', 'error');
            }
        });
    });

    // Initialize the template gallery modal
    if ($('#template-gallery-modal').length === 0) {
        $('body').append(`
            <div id="template-gallery-modal" class="wabot-modal template-gallery-modal">
                <div class="wabot-modal-content">
                    <div class="template-gallery-header">
                        <h2>Select a Template</h2>
                        <button type="button" id="close-gallery-modal" class="close-button">×</button>
                    </div>
                    <div class="template-gallery-search">
                        <input type="text" id="template-search" placeholder="Search templates...">
                    </div>
                    <div class="template-gallery-grid" id="template-gallery-grid">
                        <!-- Templates will be loaded here dynamically -->
                        <div class="wabot-loading">Loading templates...</div>
                    </div>
                    <div class="template-selection-actions">
                        <button type="button" id="cancel-template-selection" class="wabot-button secondary">Cancel</button>
                        <button type="button" id="confirm-template-selection" class="wabot-button template-select-button">Select Template</button>
                    </div>
                </div>
            </div>
        `);
    }

    // Variables to store current selections
    let selectedTemplateName = '';
    let currentTemplateKey = '';

    // Template selector click event - show gallery modal
    $(document).on('click', '.template-select-trigger', function() {
        const key = $(this).data('key');
        currentTemplateKey = key;
        
        // Load templates into the gallery
        loadTemplatesIntoGallery(key);
        
        // Show the gallery modal
        $('#template-gallery-modal').addClass('active');
        $('body').addClass('modal-open');
    });
    
    // Close gallery modal
    $(document).on('click', '#close-gallery-modal, #cancel-template-selection', function() {
        $('#template-gallery-modal').removeClass('active');
        $('body').removeClass('modal-open');
    });
    
    // Handle template search
    $(document).on('input', '#template-search', function() {
        const searchTerm = $(this).val().toLowerCase();
        
        $('.template-card').each(function() {
            const templateName = $(this).data('name').toLowerCase();
            
            if (templateName.includes(searchTerm)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
        
        // Show no results message if all cards are hidden
        if ($('.template-card:visible').length === 0) {
            if ($('#no-templates-message').length === 0) {
                $('#template-gallery-grid').append('<div id="no-templates-message" class="template-gallery-no-results">No templates match your search</div>');
            }
        } else {
            $('#no-templates-message').remove();
        }
    });
    
    // Template card selection
    $(document).on('click', '.template-card', function() {
        $('.template-card').removeClass('selected');
        $(this).addClass('selected');
        selectedTemplateName = $(this).data('name');
    });
    
    // Confirm template selection
    $(document).on('click', '#confirm-template-selection', function() {
        if (selectedTemplateName && currentTemplateKey) {
            // Update the hidden input value
            $(`#template_${currentTemplateKey}_value`).val(selectedTemplateName);
            
            // Update the template trigger text
            const $trigger = $(`.template-select-trigger[data-key="${currentTemplateKey}"]`);
            $trigger.find('.template-select-trigger-text')
                .text(selectedTemplateName)
                .removeClass('placeholder');
            
            // Get template data and update configuration
            $.post(wabotAdmin.ajax_url, {
                action: "wabot_get_template_preview",
                template_name: selectedTemplateName,
                nonce: wabotAdmin.nonce
            }).done(function(response) {
                if (response.success && response.data) {
                    updateTemplateConfiguration(response.data, $trigger.closest('.wabot-form-group'));
                }
            });
            
            // Close modal
            $('#template-gallery-modal').removeClass('active');
            $('body').removeClass('modal-open');
        } else {
            showNotification('Please select a template first', 'error');
        }
    });
    
    // Load templates into the gallery
    function loadTemplatesIntoGallery(key) {
        const $grid = $('#template-gallery-grid');
        $grid.html('<div class="wabot-loading">Loading templates...</div>');
        
        // Get current selected template
        const currentTemplate = $(`#template_${key}_value`).val();
        
        // Fetch templates
        $.post(wabotAdmin.ajax_url, {
            action: 'wabot_get_all_templates',
            nonce: wabotAdmin.nonce
        }).done(function(response) {
            if (response.success && response.data) {
                console.log('Templates loaded for gallery:', response.data);
                renderTemplateGallery(response.data, currentTemplate);
            } else {
                console.error('Failed to load templates:', response);
                $grid.html('<div class="wabot-error">Failed to load templates. Please check your API credentials.</div>');
            }
        }).fail(function(xhr, status, error) {
            console.error('Error fetching templates:', error, xhr);
            $grid.html('<div class="wabot-error">Error fetching templates.</div>');
        });
    }
    
    // Render template gallery from data
    function renderTemplateGallery(templates, currentTemplate) {
        const $grid = $('#template-gallery-grid');
        $grid.empty();
        
        if (!templates || templates.length === 0) {
            $grid.html('<div class="template-gallery-no-results">No templates available</div>');
            return;
        }
        
        // Reset selected template
        selectedTemplateName = currentTemplate;
        
        // Build template cards
        templates.forEach(template => {
            // Ensure template has required properties
            if (!template || !template.name) {
                console.warn('Skipping invalid template:', template);
                return;
            }
            
            const templateName = template.name;
            const isSelected = templateName === currentTemplate;
            
            // Before generating preview, ensure template has components property
            // If not, we'll create a simplified structure for preview
            let previewTemplate = template;
            if (!template.components || !Array.isArray(template.components) || template.components.length === 0) {
                console.log('Creating placeholder template structure for:', templateName);
                previewTemplate = {
                    name: templateName,
                    components: []
                };
                
                // Add body component if available
                if (template.body) {
                    previewTemplate.components.push({
                        type: 'body',
                        content: template.body
                    });
                } else {
                    // Add placeholder body if none exists
                    previewTemplate.components.push({
                        type: 'body',
                        content: 'Template content'
                    });
                }
                
                // If there's a header, add it
                if (template.header) {
                    previewTemplate.components.unshift({
                        type: 'header',
                        content: template.header
                    });
                }
                
                // If there's a footer or button, add it
                if (template.footer || template.buttons) {
                    previewTemplate.components.push({
                        type: 'button',
                        content: template.footer || (template.buttons && template.buttons[0]) || 'Button'
                    });
                }
            }
            
            const templatePreview = getTemplatePreview(previewTemplate);
            
            const $card = $(`
                <div class="template-card ${isSelected ? 'selected' : ''}" data-name="${templateName}">
                    <h3 title="${templateName}">${templateName}</h3>
                    <div class="template-card-preview">
                        ${templatePreview}
                    </div>
                    <div class="template-card-actions">
                        <div>
                            <span class="template-card-badge">WhatsApp</span>
                        </div>
                        <button type="button" class="preview-in-card wabot-btn-icon" data-name="${templateName}">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                    </div>
                </div>
            `);
            
            $grid.append($card);
        });
    }
    
    // Generate a preview snippet for a template card
    function getTemplatePreview(template) {
        let preview = '';
        
        if (template.components && Array.isArray(template.components)) {
            // Try to find a body component
            const bodyComponent = template.components.find(c => c.type === 'body');
            
            if (bodyComponent && bodyComponent.content) {
                // Format the preview - truncate to ~100 chars
                let text = bodyComponent.content;
                if (text.length > 100) {
                    text = text.substring(0, 97) + '...';
                }
                // Replace variables with placeholders
                text = text.replace(/{{\d+}}/g, match => {
                    const varNumber = match.replace(/[{}]/g, "");
                    return `<span class="variable">[Variable ${varNumber}]</span>`;
                });
                
                // Replace \n with <br> for proper line breaks
                text = text.replace(/\\n/g, "<br>");
                
                preview = `<div class="template-card-content">${text}</div>`;
            } else {
                // Try to check if there's raw data we can parse
                if (template.body) {
                    let text = template.body;
                    if (text.length > 100) {
                        text = text.substring(0, 97) + '...';
                    }
                    // Replace variables with placeholders
                    text = text.replace(/{{\d+}}/g, match => {
                        const varNumber = match.replace(/[{}]/g, "");
                        return `<span class="variable">[Variable ${varNumber}]</span>`;
                    });
                    
                    // Replace \n with <br> for proper line breaks
                    text = text.replace(/\\n/g, "<br>");
                    
                    preview = `<div class="template-card-content">${text}</div>`;
                }
            }
        }
        
        return preview || '<div class="template-card-content">No preview available</div>';
    }
    
    // Preview a template from gallery
    $(document).on('click', '.preview-in-card', function(e) {
        e.stopPropagation(); // Don't trigger card selection
        const templateName = $(this).data('name');
        
        // Show modal with preview
        fetchTemplatePreview(templateName);
        $("#wabot-template-preview-modal").addClass('active');
        $('body').addClass('modal-open');
    });

    // Use event delegation for preview buttons
    $(document).on("click", ".wabot-preview-button", function () {
        const key = $(this).data("key");
        fetchTemplatePreview(key);
        
        // Show modal with our new CSS transitions
        $("#wabot-template-preview-modal").addClass('active');
        $('body').addClass('modal-open');
    });

    // Handle Test Button click with event delegation
    $(document).on("click", ".wabot-test-button", function () {
        const key = $(this).data("key");
        
        // Remove any existing test modals first
        $('#wabot-test-modal').remove();
        
        // Show loading overlay
        $('body').append('<div id="wabot-test-loading" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:99998;display:flex;justify-content:center;align-items:center;"><div style="background:white;padding:20px;border-radius:8px;">Loading template details...</div></div>');
        
        // Fetch template details to get variables
        $.post(wabotAdmin.ajax_url, {
            action: 'wabot_get_template_preview',
            nonce: wabotAdmin.nonce,
            template_name: key,
        }).done(function (response) {
            // Remove loading overlay
            $('#wabot-test-loading').remove();
            
            if (!response.success) {
                showNotification('Failed to load template details', 'error');
                return;
            }
            
            const template = response.data;
            
            // Extract variables from template
            let variables = [];
            let ctaParams = [];
            
            if (template.components) {
                // First extract variables from body components
                template.components.forEach(component => {
                    if (component.type === 'body' && component.content) {
                        // Extract {{1}}, {{2}}, etc.
                        const matches = component.content.match(/{{\d+}}/g) || [];
                        
                        // Convert matches to unique variable objects
                        matches.forEach(match => {
                            const varNumber = match.replace(/[{}]/g, "");
                            if (!variables.some(v => v.number === varNumber)) {
                                let varLabel = `Variable ${varNumber}`;
                                
                                // Try to get better label from template metadata
                                if (component.variables) {
                                    const templateVar = component.variables.find(v => 
                                        v.text.includes(varNumber) || v.text === `Custom${varNumber}`);
                                    if (templateVar) {
                                        varLabel = templateVar.text;
                                    }
                                }
                                
                                variables.push({
                                    number: varNumber,
                                    label: varLabel,
                                    type: 'text'
                                });
                            }
                        });
                    }
                    
                    // Extract CTA parameters from button components
                    if (component.type === 'button') {
                        if (component.buttons && component.buttons.length > 0) {
                            component.buttons.forEach(button => {
                                if (button.type === 'URL' && button.url) {
                                    ctaParams.push({
                                        label: 'CTA Link',
                                        key: 'cta_link',
                                        type: 'url',
                                        placeholder: 'Enter CTA URL',
                                        default: button.url !== '#' ? button.url : ''
                                    });
                                }
                            });
                        }
                    }
                });
            }
            
            // Create variable fields HTML
            let variableFieldsHtml = '';
            if (variables.length > 0) {
                variables.forEach(variable => {
                    variableFieldsHtml += `
                        <div class="wabot-form-group">
                            <label for="test-var-${variable.number}">${variable.label}:</label>
                            <input type="text" id="test-var-${variable.number}" 
                                class="test-variable-input" data-var-number="${variable.number}" 
                                placeholder="Enter value for ${variable.label}">
                        </div>
                    `;
                });
            } else {
                variableFieldsHtml = '<p>No variables found in this template.</p>';
            }
            
            // Create CTA fields HTML
            let ctaFieldsHtml = '';
            if (ctaParams.length > 0) {
                ctaParams.forEach(param => {
                    ctaFieldsHtml += `
                        <div class="wabot-form-group">
                            <label for="test-${param.key}">${param.label}:</label>
                            <input type="${param.type}" id="test-${param.key}" 
                                class="test-cta-input" data-key="${param.key}" 
                                placeholder="${param.placeholder}" 
                                value="${param.default || ''}" />
                        </div>
                    `;
                });
            }
            
            // Add CTA fields section if needed
            const ctaSectionHtml = ctaParams.length > 0 ? `
                <div class="wabot-form-section" style="margin-bottom: 20px; border: 1px solid var(--wabot-border); border-radius: 6px; overflow: hidden;">
                    <div class="wabot-form-header" style="background: #f5f7fa; padding: 12px 15px; border-bottom: 1px solid var(--wabot-border);">
                        <h3 style="margin: 0; font-size: 14px;">Button Parameters</h3>
                    </div>
                    <div class="wabot-form-body" style="padding: 15px;">
                        ${ctaFieldsHtml}
                    </div>
                </div>
            ` : '';

            // Create the modal HTML
            const modalHtml = `
                <div id="wabot-test-modal" class="wabot-modal">
                    <div class="wabot-modal-content">
                        <div class="modal-header">
                            <h2>Test Template: ${key}</h2>
                            <button type="button" id="close-test-modal" class="close-button">×</button>
                        </div>
                        
                        <div class="wabot-form-section" style="margin-bottom: 20px; border: 1px solid var(--wabot-border); border-radius: 6px; overflow: hidden;">
                            <div class="wabot-form-header" style="background: #f5f7fa; padding: 12px 15px; border-bottom: 1px solid var(--wabot-border);">
                                <h3 style="margin: 0; font-size: 14px;">Recipient Information</h3>
                            </div>
                            <div class="wabot-form-body" style="padding: 15px;">
                                <div class="wabot-form-group">
                                    <label for="test-recipient">Phone Number:</label>
                                    <input type="tel" id="test-recipient" class="intl-tel-input" name="test-recipient" placeholder="Enter WhatsApp number with country code">
                                </div>
                            </div>
                        </div>
                        
                        <div class="wabot-form-section" style="margin-bottom: 20px; border: 1px solid var(--wabot-border); border-radius: 6px; overflow: hidden;">
                            <div class="wabot-form-header" style="background: #f5f7fa; padding: 12px 15px; border-bottom: 1px solid var(--wabot-border);">
                                <h3 style="margin: 0; font-size: 14px;">Template Variables</h3>
                            </div>
                            <div class="wabot-form-body" style="padding: 15px;">
                                ${variableFieldsHtml}
                            </div>
                        </div>
                        
                        ${ctaSectionHtml}
                        
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <button type="button" id="preview-test-message" class="wabot-button secondary">
                                <span class="dashicons dashicons-visibility" style="margin-right: 5px;"></span> Preview
                            </button>
                            <button type="button" id="send-test-message" class="wabot-button">
                                <span class="dashicons dashicons-email-alt" style="margin-right: 5px;"></span> Send Test Message
                            </button>
                        </div>
                    </div>
                </div>
            `;
            $("body").append(modalHtml);

            // Display the modal with our CSS transitions
            setTimeout(() => {
                $("#wabot-test-modal").addClass('active');
                $('body').addClass('modal-open');
            }, 10);

            // Initialize intl-tel-input on the phone number field
            const input = document.querySelector("#test-recipient");
            const iti = window.intlTelInput(input, {
                initialCountry: "auto",
                geoIpLookup: function (callback) {
                    $.get("https://ipinfo.io", function () {}, "jsonp").always(function (resp) {
                        const countryCode = resp && resp.country ? resp.country : "us";
                        callback(countryCode);
                    });
                },
                utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js",
            });

            // Preview button handler
            $(document).on("click", "#preview-test-message", function() {
                // Get entered variables
                const previewValues = {};
                $('.test-variable-input').each(function() {
                    const varNumber = $(this).data('var-number');
                    const value = $(this).val() || `[Variable ${varNumber}]`;
                    previewValues[varNumber] = value;
                });
                
                // Get entered CTA parameters
                const ctaValues = {};
                $('.test-cta-input').each(function() {
                    const key = $(this).data('key');
                    const value = $(this).val();
                    ctaValues[key] = value;
                });
                
                // Create preview content
                let previewHtml = '<div class="wabot-preview-container" style="margin-top: 15px;">';
                
                // Iterate through components to create preview
                template.components.forEach(component => {
                    if (component.type === "header") {
                        previewHtml += `
                            <div class="chat-header">
                                <span>${component.content || ''}</span>
                            </div>`;
                    } else if (component.type === "body") {
                        // Replace variables with entered values
                        let bodyContent = component.content || '';
                        bodyContent = bodyContent.replace(/{{\d+}}/g, match => {
                            const varNumber = match.replace(/[{}]/g, "");
                            
                            // Get variable label if available
                            let variableLabel = '';
                            if (component.variables) {
                                const variable = component.variables.find(v => v.text.includes(varNumber) || v.text === `Custom${varNumber}`);
                                if (variable) {
                                    variableLabel = variable.text + ': ';
                                }
                            }
                            
                            return `<span class="variable">${variableLabel}${previewValues[varNumber] || match}</span>`;
                        });
                        
                        // Replace \n with <br> for proper new line rendering
                        bodyContent = bodyContent.replace(/\\n/g, "<br>");
                        
                        previewHtml += `
                            <div class="chat-body">
                                <p>${bodyContent}</p>
                            </div>`;
                    } else if (component.type === "button") {
                        // Handle button preview with the new format
                        let buttonContent = 'Button';
                        let buttonUrl = '#';
                        
                        if (component.buttons && component.buttons.length > 0) {
                            // New format with buttons array
                            buttonContent = component.buttons[0].text || component.buttons[0].value || 'Button';
                            
                            // Use entered CTA link if available, otherwise use default
                            if (ctaValues.cta_link && component.buttons[0].type === 'URL') {
                                buttonUrl = ctaValues.cta_link;
                            } else {
                                buttonUrl = component.buttons[0].url || '#';
                            }
                            
                            // For phone number buttons
                            if (ctaValues.cta_phone && component.buttons[0].type === 'PHONE_NUMBER') {
                                buttonUrl = 'tel:' + ctaValues.cta_phone;
                            }
                        } else if (component.content) {
                            // Old or simplified format
                            buttonContent = component.content;
                            
                            // Use entered CTA link if available
                            buttonUrl = ctaValues.cta_link || component.url || '#';
                        }
                        
                        previewHtml += `
                            <div class="chat-button">
                                <button data-url="${buttonUrl}">${buttonContent}</button>
                                ${component.buttons && component.buttons.length > 1 ? 
                                    '<div class="button-info" style="font-size: 12px; color: #666; margin-top: 5px;">+ ' + (component.buttons.length - 1) + ' more button(s)</div>' : ''}
                                <div class="button-url" style="font-size: 12px; color: #666; margin-top: 5px;">URL: ${buttonUrl}</div>
                            </div>`;
                    } else if (component.type === "carousel") {
                        previewHtml += `
                            <div class="chat-carousel">
                                <div class="carousel-placeholder" style="background: #f0f0f0; padding: 15px; border-radius: 5px; text-align: center;">
                                    <p>Carousel with ${Array.isArray(component.content) ? component.content.length : 0} cards</p>
                                </div>
                            </div>`;
                    }
                });
                
                previewHtml += '</div>';
                
                // Add or update preview section
                if ($('#test-message-preview').length === 0) {
                    $('#wabot-test-modal .wabot-modal-content').append(
                        `<div id="test-message-preview" style="margin-top: 20px; border-top: 1px solid var(--wabot-border); padding-top: 20px;">
                            <h3 style="margin-top: 0; font-size: 14px;">Message Preview:</h3>
                            ${previewHtml}
                        </div>`
                    );
                } else {
                    $('#test-message-preview').html(
                        `<h3 style="margin-top: 0; font-size: 14px;">Message Preview:</h3>
                        ${previewHtml}`
                    );
                }
            });

            // Close modal handler
            $(document).on("click", "#close-test-modal", function () {
                $("#wabot-test-modal").removeClass('active');
                $('body').removeClass('modal-open');
            });

            // Send Test Button click handler
            $(document).on("click", "#send-test-message", function () {
                // Show loading state
                const $button = $(this);
                const originalText = $button.text();
                $button.html('<span class="dashicons dashicons-update" style="margin-right: 5px; animation: spin 1s linear infinite;"></span> Sending...').prop('disabled', true).addClass('sending');
                
                // Add spin animation if not already defined
                if (!document.getElementById('spin-animation')) {
                    $('head').append(`
                        <style id="spin-animation">
                            @keyframes spin {
                                0% { transform: rotate(0deg); }
                                100% { transform: rotate(360deg); }
                            }
                        </style>
                    `);
                }
                
                const recipient = iti.getNumber(); // Get full number with country code
                
                // Validate phone number
                if (!iti.isValidNumber()) {
                    const errorMessage = iti.getValidationError(); // Get error code
                    let errorText;

                    // Convert error code to user-friendly message
                    switch (errorMessage) {
                        case intlTelInputUtils.validationError.INVALID_COUNTRY_CODE:
                            errorText = "Invalid country code.";
                            break;
                        case intlTelInputUtils.validationError.TOO_SHORT:
                            errorText = "The phone number is too short.";
                            break;
                        case intlTelInputUtils.validationError.TOO_LONG:
                            errorText = "The phone number is too long.";
                            break;
                        case intlTelInputUtils.validationError.NOT_A_NUMBER:
                            errorText = "The input is not a valid number.";
                            break;
                        default:
                            errorText = "Invalid phone number.";
                    }

                    // Restore button state
                    $button.html('<span class="dashicons dashicons-email-alt" style="margin-right: 5px;"></span> Send Test Message').prop('disabled', false).removeClass('sending');
                    
                    // Show error message with toast-like notification
                    showNotification(errorText, 'error');
                    return; // Stop further execution
                }
                
                // Create structured parameters object with named parameters
                const templateParams = {};
                
                // Add all variables with their labels as keys
                $('.test-variable-input').each(function() {
                    const varNumber = $(this).data('var-number');
                    const value = $(this).val();
                    
                    if (value) {
                        // Get the label for this variable
                        let label = `variable_${varNumber}`;
                        
                        // Try to find a better label from the template
                        const bodyComponent = template.components.find(c => c.type === 'body');
                        if (bodyComponent && bodyComponent.variables) {
                            const variable = bodyComponent.variables.find(v => v.text.includes(varNumber) || v.text === `Custom${varNumber}`);
                            if (variable) {
                                // Use the exact variable name from the template
                                // This ensures we use Custom1, Custom2, etc. instead of converting all to 'custom'
                                label = variable.text;
                                
                                // Keep the original variable name instead of changing all custom variables to 'custom'
                                // Only apply special formatting to specific types of variables
                                if (label.toLowerCase().includes('name') && !label.includes('Custom')) label = 'name';
                                if (label.toLowerCase().includes('email') && !label.includes('Custom')) label = 'email';
                                if (label.toLowerCase().includes('phone') && !label.includes('Custom')) label = 'cta_phone';
                                if ((label.toLowerCase().includes('link') || label.toLowerCase().includes('url')) && 
                                    !label.includes('Custom')) label = 'cta_link';
                            }
                        }
                        
                        // Set the value with the appropriate key
                        templateParams[label] = value;
                    }
                });
                
                // Add all CTA parameters
                $('.test-cta-input').each(function() {
                    const key = $(this).data('key');
                    const value = $(this).val();
                    
                    if (value) {
                        templateParams[key] = value;
                    }
                });
                
                console.log('Sending template with parameters:', templateParams);
                
                // AJAX request to call the backend function
                $.ajax({
                    url: wabotAdmin.ajax_url,
                    type: "POST",
                    data: {
                        action: "wabot_send_message",
                        to: recipient,
                        template_name: key,
                        template_params: templateParams
                    },
                    success: function (response) {
                        if (response.success) {
                            showNotification("Message sent successfully!", 'success');
                            $("#wabot-test-modal").removeClass('active');
                            $('body').removeClass('modal-open');
                        } else {
                            showNotification(response.data?.message || "Error sending message", 'error');
                            // Restore button state
                            $button.html('<span class="dashicons dashicons-email-alt" style="margin-right: 5px;"></span> Send Test Message').prop('disabled', false).removeClass('sending');
                        }
                    },
                    error: function (e) {
                        console.error(e);
                        showNotification("Server error. Please try again.", 'error');
                        // Restore button state
                        $button.html('<span class="dashicons dashicons-email-alt" style="margin-right: 5px;"></span> Send Test Message').prop('disabled', false).removeClass('sending');
                    },
                });
            });
        }).fail(function () {
            $('#wabot-test-loading').remove();
            showNotification("Error fetching template details", 'error');
        });
    });

    // Function to show toast-like notifications
    function showNotification(message, type = 'info') {
        // Remove any existing notifications
        $('.wabot-notification').remove();
        
        // Create notification element
        const $notification = $(`<div class="wabot-notification ${type}">${message}</div>`);
        $('body').append($notification);
        
        // Animate in
        setTimeout(() => {
            $notification.addClass('show');
            
            // Auto remove after delay
            setTimeout(() => {
                $notification.removeClass('show');
                setTimeout(() => {
                    $notification.remove();
                }, 300);
            }, 3000);
        }, 10);
    }

    function fetchTemplatePreview(templateName) {
        if (!templateName) {
            $("#template-preview-container").html('<div class="wabot-empty-state">Please select a template to preview</div>');
            return;
        }
        
        // Show loading state
        $("#template-preview-container").html('<div class="wabot-loading">Loading template preview...</div>');
    
        $.post(wabotAdmin.ajax_url, {
            action: "wabot_get_template_preview",
            template_name: templateName,
        }).done(function (response) {
            if (response.success) {
                console.log("Template preview data:", response.data);
                renderTemplatePreview(response.data);
            } else {
                console.error("Failed to load template preview:", response);
                $("#template-preview-container").html(`<div class="wabot-error">Failed to load template preview: ${response.data?.message || 'Unknown error'}</div>`);
            }
        }).fail(function (xhr, status, error) {
            console.error("Error fetching template preview:", error, xhr);
            $("#template-preview-container").html('<div class="wabot-error">Error fetching template preview.</div>');
        });
    }
    
    function renderTemplatePreview(template) {
        if (!template || !template.name) {
            $("#template-preview-container").html('<div class="wabot-error">Invalid template data received.</div>');
            return;
        }
        
        // Add diagnostic output for debugging
        const diagnosticInfo = {
            name: template.name,
            hasComponents: Boolean(template.components),
            componentsIsArray: Array.isArray(template.components),
            componentCount: Array.isArray(template.components) ? template.components.length : 0,
            componentTypes: Array.isArray(template.components) 
                ? template.components.map(c => c.type).join(', ') 
                : 'none'
        };
        
        console.log('Template diagnostic info:', diagnosticInfo);
        
        if (!template.components || !Array.isArray(template.components) || template.components.length === 0) {
            $("#template-preview-container").html('<div class="wabot-error">No components found in template data.</div>');
            console.error("Invalid components in template:", template);
            return;
        }
        
        let previewHtml = `<h3>Template: ${template.name}</h3>`;
        previewHtml += `<div class="wabot-preview-container">`;
        
        template.components.forEach((component) => {
            if (component.type === "header") {
                previewHtml += `
                    <div class="chat-header">
                            <span>${component.content || ''}</span>
                    </div>`;
            } else if (component.type === "body") {
                // Replace variables ({{1}}, {{2}}) with placeholders
                    let bodyContent = component.content ? component.content.replace(/{{\d+}}/g, (match) => {
                    const varNumber = match.replace(/[{}]/g, "");
                    
                    // If we have variable definitions, use those labels
                    let variableLabel = `Variable ${varNumber}`;
                    if (component.variables) {
                        const variable = component.variables.find(v => v.text.includes(varNumber) || v.text === `Custom${varNumber}`);
                        if (variable) {
                            variableLabel = variable.text;
                        }
                    }
                    
                    return `<span class="variable">[${variableLabel}]</span>`;
                    }) : '';
    
                // Replace \n with <br> for proper new line rendering
                bodyContent = bodyContent.replace(/\\n/g, "<br>");
    
                previewHtml += `
                    <div class="chat-body">
                        <p>${bodyContent}</p>
                    </div>`;
            } else if (component.type === "button") {
                // Handle button preview - now supports both old and new format
                let buttonContent = 'Button';
                let buttonUrl = '#';
                
                if (component.buttons && component.buttons.length > 0) {
                    // New format with buttons array
                    buttonContent = component.buttons[0].text || component.buttons[0].value || 'Button';
                    buttonUrl = component.buttons[0].url || '#';
                } else if (component.content) {
                    // Old or simplified format
                    buttonContent = component.content;
                    buttonUrl = component.url || '#';
                }
                
                previewHtml += `
                    <div class="chat-button">
                            <button data-url="${buttonUrl}">${buttonContent}</button>
                            ${component.buttons && component.buttons.length > 1 ? 
                                '<div class="button-info" style="font-size: 12px; color: #666; margin-top: 5px;">+ ' + (component.buttons.length - 1) + ' more button(s)</div>' : ''}
                    </div>`;
            } else if (component.type === "carousel") {
                previewHtml += `
                    <div class="chat-carousel">
                        <div class="carousel-placeholder" style="background: #f0f0f0; padding: 15px; border-radius: 5px; text-align: center;">
                            <p>Carousel with ${Array.isArray(component.content) ? component.content.length : 0} cards</p>
                        </div>
                    </div>`;
            }
        });
        
        previewHtml += `</div>`; // Close preview container
    
        // Inject the preview HTML into the modal
        $("#template-preview-container").html(previewHtml);
    }
    
    // Close the modal when the close button is clicked - use event delegation
    $(document).on("click", "#close-preview-modal", function () {
        $("#wabot-template-preview-modal").removeClass('active');
        $('body').removeClass('modal-open');
    });

    // Close modals when clicking outside the modal content
    $(document).on("click", ".wabot-modal", function (e) {
        if ($(e.target).is(".wabot-modal")) {
            $(this).removeClass('active');
            $('body').removeClass('modal-open');
            
            // If it's a dynamically created modal, remove it after transition
            if ($(this).attr('id') === 'wabot-test-modal') {
                setTimeout(() => {
                    $(this).remove();
                }, 300);
            }
        }
    });
    
    // Clean up event handlers when test modal is removed
    $(document).on('remove', '#wabot-test-modal', function() {
        $(document).off('click', '#close-test-modal');
        $(document).off('click', '#send-test-message');
    });

    // Email template enable/disable toggle functionality
    $(document).on('change', '[id^=email_template_][id$=_enabled]', function() {
        const isEnabled = $(this).prop('checked');
        const key = $(this).attr('id').replace('email_template_', '').replace('_enabled', '');
        const $label = $(this).closest('.template-toggle-container').find('.wabot-toggle-label');
        const $editorContainer = $(this).closest('.wabot-form-group').find('.email-template-editor');
        const $testButton = $(this).closest('.wabot-form-group').find('.wabot-test-email-button');
        
        // Update toggle label
        $label.text(isEnabled ? 'Enabled' : 'Disabled');
        
        // Toggle disabled class on the template editor and action buttons
        if (isEnabled) {
            $editorContainer.removeClass('disabled');
            $testButton.removeClass('disabled');
        } else {
            $editorContainer.addClass('disabled');
            $testButton.addClass('disabled');
        }
        
        // Highlight the save button to indicate unsaved changes
        const $saveButton = $(this).closest('form').find('.wabot-button[type="submit"]');
        $saveButton.css({
            'background-color': '#f80',
            'box-shadow': '0 0 5px rgba(255, 136, 0, 0.5)'
        });
        
        // Add a small reminder to save changes
        if (!$('.save-reminder').length) {
            $saveButton.after('<span class="save-reminder" style="margin-left: 10px; color: #f80; font-style: italic;">Don\'t forget to save your changes!</span>');
        }
    });
    
    // Initialize the email test modal
    if ($('#email-test-modal').length === 0) {
        $('body').append(`
            <div id="email-test-modal" class="wabot-modal">
                <div class="wabot-modal-content">
                    <div class="modal-header">
                        <h2>Test Email Template</h2>
                        <button type="button" id="close-email-test-modal" class="close-button">×</button>
                    </div>
                    <div class="wabot-form-body" style="padding: 20px;">
                        <div class="wabot-form-group">
                            <label for="test-email-recipient">Email Address:</label>
                            <input type="email" id="test-email-recipient" class="regular-text" placeholder="Enter recipient email address">
                        </div>
                        <div id="email-test-variables-container" style="margin-top: 20px;">
                            <!-- Dynamic variables will be inserted here -->
                        </div>
                        <div class="wabot-form-actions" style="margin-top: 20px; text-align: right;">
                            <button type="button" id="send-test-email" class="wabot-button">
                                <span class="dashicons dashicons-email-alt" style="margin-right: 5px;"></span> Send Test Email
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `);
    }
    
    // Test Email Button click event
    $(document).on("click", ".wabot-test-email-button", function() {
        const templateKey = $(this).data('template');
        const templateSubject = $(`#wabot_email_template_${templateKey}_subject`).val();
        const templateContent = $(`#wabot_email_template_${templateKey}`).val();
        
        // Extract variables from the template content using regex for {variable_name} format
        const variables = [];
        const variableRegex = /\{([^}]+)\}/g;
        let match;
        
        while ((match = variableRegex.exec(templateContent)) !== null) {
            const variableName = match[1];
            if (!variables.includes(variableName)) {
                variables.push(variableName);
            }
        }
        
        // Also check subject line for variables
        while ((match = variableRegex.exec(templateSubject)) !== null) {
            const variableName = match[1];
            if (!variables.includes(variableName)) {
                variables.push(variableName);
            }
        }
        
        // Generate form fields for each variable
        let variableFields = '';
        if (variables.length > 0) {
            variableFields += '<h3 style="margin-top: 0;">Template Variables</h3>';
            
            variables.forEach(variable => {
                let defaultValue = '';
                
                // Set some sensible default values based on variable name
                if (variable === 'customer_name') defaultValue = 'John Doe';
                if (variable === 'order_id') defaultValue = '12345';
                if (variable === 'order_total') defaultValue = '$99.99';
                if (variable === 'order_status') defaultValue = 'Processing';
                if (variable === 'site_name') defaultValue = window.location.hostname;
                if (variable === 'site_url') defaultValue = window.location.origin;
                if (variable === 'recovery_link') defaultValue = window.location.origin + '/cart/';
                if (variable === 'coupon_code') defaultValue = 'TESTCOUPON10';
                
                variableFields += `
                    <div class="wabot-form-group">
                        <label for="email-var-${variable}">{${variable}}:</label>
                        <input type="text" id="email-var-${variable}" class="email-variable-input" 
                            data-var-name="${variable}" value="${defaultValue}" style="width: 100%;">
                    </div>
                `;
            });
        } else {
            variableFields = '<p>No variables found in this template.</p>';
        }
        
        // Update the modal with the variables
        $('#email-test-variables-container').html(variableFields);
        
        // Store the current template key for the send function
        $('#send-test-email').data('template-key', templateKey);
        
        // Show the modal
        $('#email-test-modal').addClass('active');
        $('body').addClass('modal-open');
    });
    
    // Close email test modal
    $(document).on('click', '#close-email-test-modal', function() {
        $('#email-test-modal').removeClass('active');
        $('body').removeClass('modal-open');
    });
    
    // Send test email button click
    $(document).on('click', '#send-test-email', function() {
        const $button = $(this);
        const templateKey = $button.data('template-key');
        const recipient = $('#test-email-recipient').val();
        
        // Validate email address
        if (!recipient || !isValidEmail(recipient)) {
            showNotification('Please enter a valid email address', 'error');
            return;
        }
        
        // Collect variable values
        const variables = {};
        $('.email-variable-input').each(function() {
            const varName = $(this).data('var-name');
            const varValue = $(this).val();
            variables[varName] = varValue;
        });
        
        // Show loading state
        const originalText = $button.html();
        $button.html('<span class="dashicons dashicons-update" style="margin-right: 5px; animation: spin 1s linear infinite;"></span> Sending...').prop('disabled', true);
        
        // Make AJAX request to send test email
        $.ajax({
            url: wabotAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'wabot_send_test_email',
                nonce: wabotAdmin.nonce,
                template_key: templateKey,
                recipient: recipient,
                variables: variables
            },
            success: function(response) {
                // Restore button
                $button.html(originalText).prop('disabled', false);
                
                if (response.success) {
                    showNotification('Test email sent successfully!', 'success');
                    // Close modal
                    $('#email-test-modal').removeClass('active');
                    $('body').removeClass('modal-open');
                } else {
                    showNotification(response.data.message || 'Failed to send test email', 'error');
                }
            },
            error: function() {
                // Restore button
                $button.html(originalText).prop('disabled', false);
                showNotification('Server error. Please try again.', 'error');
            }
        });
    });
    
    // Helper function to validate email address
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    // Email preview functionality
    $(document).on('click', '.wabot-preview-email-button', function() {
        const templateKey = $(this).data('template');
        const templateSubject = $(`#wabot_email_template_${templateKey}_subject`).val();
        const templateContent = $(`#wabot_email_template_${templateKey}`).val();
        
        // Extract variables from the template content using regex for {variable_name} format
        const variables = [];
        const variableRegex = /\{([^}]+)\}/g;
        let match;
        
        while ((match = variableRegex.exec(templateContent)) !== null) {
            const variableName = match[1];
            if (!variables.includes(variableName)) {
                variables.push(variableName);
            }
        }
        
        // Also check subject line for variables
        while ((match = variableRegex.exec(templateSubject)) !== null) {
            const variableName = match[1];
            if (!variables.includes(variableName)) {
                variables.push(variableName);
            }
        }
        
        // Generate form fields for each variable
        let variableFields = '';
        if (variables.length > 0) {
            variableFields += '<h3 style="margin-top: 0;">Template Variables</h3>';
            
            variables.forEach(variable => {
                let defaultValue = '';
                
                // Set some sensible default values based on variable name
                if (variable === 'customer_name') defaultValue = 'John Doe';
                if (variable === 'order_id') defaultValue = '12345';
                if (variable === 'order_total') defaultValue = '$99.99';
                if (variable === 'order_status') defaultValue = 'Processing';
                if (variable === 'site_name') defaultValue = window.location.hostname;
                if (variable === 'site_url') defaultValue = window.location.origin;
                if (variable === 'recovery_link') defaultValue = window.location.origin + '/cart/';
                if (variable === 'coupon_code') defaultValue = 'TESTCOUPON10';
                
                variableFields += `
                    <div class="wabot-form-group">
                        <label for="preview-var-${variable}">{${variable}}:</label>
                        <input type="text" id="preview-var-${variable}" class="preview-variable-input" 
                            data-var-name="${variable}" value="${defaultValue}" style="width: 100%;">
                    </div>
                `;
            });
        } else {
            variableFields = '<p>No variables found in this template.</p>';
        }
        
        // Update the modal with the variables
        $('#email-preview-variables-container').html(variableFields);
        
        // Store the current template key for the preview function
        $('#refresh-email-preview').data('template-key', templateKey);
        
        // Show initial preview
        updateEmailPreview(templateKey);
        
        // Show the modal
        $('#email-preview-modal').addClass('active');
        $('body').addClass('modal-open');
    });

    // Close email preview modal
    $(document).on('click', '#close-email-preview-modal', function() {
        $('#email-preview-modal').removeClass('active');
        $('body').removeClass('modal-open');
    });

    // Refresh preview button click
    $(document).on('click', '#refresh-email-preview', function() {
        const templateKey = $(this).data('template-key');
        updateEmailPreview(templateKey);
    });

    // Update preview when variables change
    $(document).on('input', '.preview-variable-input', function() {
        const templateKey = $('#refresh-email-preview').data('template-key');
        updateEmailPreview(templateKey);
    });

    function updateEmailPreview(templateKey) {
        let templateSubject = $(`#wabot_email_template_${templateKey}_subject`).val();
        let templateContent = $(`#wabot_email_template_${templateKey}`).val();
        
        // Collect variable values
        $('.preview-variable-input').each(function() {
            const varName = $(this).data('var-name');
            const varValue = $(this).val();
            const variableRegex = new RegExp(`\\{${varName}\\}`, 'g');
            
            templateSubject = templateSubject.replace(variableRegex, varValue);
            templateContent = templateContent.replace(variableRegex, varValue);
        });
        
        // Update preview
        $('#email-preview-subject').html(`<strong>Subject:</strong> ${templateSubject}`);
        $('#email-preview-content').html(templateContent);
    }

    // Function to update template configuration sections
    function updateTemplateConfiguration(templateData, container) {
        // Clear existing configuration
        container.find('.template-configuration').empty();

        if (!templateData || !templateData.components) {
            container.find('.template-configuration').html(`
                <div class="wabot-empty-state">
                    <p>No template configuration available</p>
                </div>
            `);
            return;
        }

        // Create configuration container
        let configHtml = '<div class="template-configuration">';

        // Variables Section
        const bodyComponents = templateData.components.filter(c => c.type === 'body');
        let variables = [];
        let variableCount = 0;

        bodyComponents.forEach(component => {
            if (component.content) {
                // Extract {{1}}, {{2}}, etc.
                const matches = component.content.match(/{{\d+}}/g) || [];
                
                matches.forEach(match => {
                    const varNumber = match.replace(/[{}]/g, "");
                    if (!variables.some(v => v.number === varNumber)) {
                        let varLabel = `Variable ${varNumber}`;
                        
                        // Try to get better label from template metadata
                        if (component.variables) {
                            const templateVar = component.variables.find(v => 
                                v.text.includes(varNumber) || v.text === `Custom${varNumber}`);
                            if (templateVar) {
                                varLabel = templateVar.text;
                            }
                        }
                        
                        variables.push({
                            number: varNumber,
                            label: varLabel,
                            type: 'text'
                        });
                        variableCount++;
                    }
                });
            }
        });

        if (variableCount > 0) {
            configHtml += `
                <div class="config-section variables-section">
                    <h3 class="section-title">
                        Variables Configuration
                        <span class="component-count">${variableCount}</span>
                    </h3>
                    <div class="variables-config">
                        <table class="wabot-config-table">
                            <thead>
                                <tr>
                                    <th>Variable</th>
                                    <th>Default Value</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
            `;

            variables.forEach(variable => {
                configHtml += `
                    <tr>
                        <td><code>{{${variable.number}}}</code><br><small>${variable.label}</small></td>
                        <td>
                            <input type="text" class="variable-input" 
                                name="template_variable_${variable.number}" 
                                placeholder="Enter default value">
                            <div class="variable-picker">
                                <select class="insert-variable">
                                    <option value="">Insert system variable...</option>
                                    <option value="{customer_name}">Customer Name</option>
                                    <option value="{order_id}">Order ID</option>
                                    <option value="{order_total}">Order Total</option>
                                    <option value="{order_status}">Order Status</option>
                                    <option value="{site_name}">Site Name</option>
                                    <option value="{site_url}">Site URL</option>
                                </select>
                            </div>
                        </td>
                        <td>
                            <span class="variable-description">
                                Enter the default value for this variable. You can also select from system variables.
                            </span>
                        </td>
                    </tr>
                `;
            });

            configHtml += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        }

        // Buttons/CTA Section
        const buttonComponents = templateData.components.filter(c => c.type === 'button');
        let buttonCount = 0;

        if (buttonComponents.length > 0) {
            configHtml += `
                <div class="config-section buttons-section">
                    <h3 class="section-title">
                        Button Configuration
                        <span class="component-count">${buttonComponents.length}</span>
                    </h3>
                    <div class="buttons-config">
                        <table class="wabot-config-table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Configuration</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
            `;

            buttonComponents.forEach((button, index) => {
                const buttonType = button.buttons && button.buttons[0] ? button.buttons[0].type : 'URL';
                buttonCount++;

                configHtml += `
                    <tr>
                        <td>${buttonType}</td>
                        <td>
                            ${buttonType === 'URL' ? `
                                <input type="url" class="button-input" 
                                    name="template_button_${index}_url" 
                                    placeholder="Enter default URL">
                                <div class="variable-picker">
                                    <select class="insert-variable">
                                        <option value="">Insert dynamic URL...</option>
                                        <option value="{cart_url}">Cart URL</option>
                                        <option value="{checkout_url}">Checkout URL</option>
                                        <option value="{account_url}">Account URL</option>
                                    </select>
                                </div>
                            ` : `
                                <input type="tel" class="button-input" 
                                    name="template_button_${index}_phone" 
                                    placeholder="Enter default phone number">
                            `}
                        </td>
                        <td>
                            <span class="variable-description">
                                ${buttonType === 'URL' ? 
                                    'Enter the default URL for this button. You can also select from dynamic URLs.' :
                                    'Enter the default phone number for this button.'}
                            </span>
                        </td>
                    </tr>
                `;
            });

            configHtml += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        }

        configHtml += '</div>';

        // Add the configuration to the container
        container.find('.template-configuration').replaceWith(configHtml);

        // Initialize variable pickers
        initializeVariablePickers(container);
    }

    // Initialize variable pickers functionality
    function initializeVariablePickers(container) {
        container.find('.insert-variable').on('change', function() {
            const value = $(this).val();
            if (!value) return;
            
            // Save the selected value
            $(this).find(`option[value="${value}"]`).prop('selected', true);
            
            // If this is a default value field, update it with the system variable
            const input = $(this).closest('td').next('td').find('input[type="text"], input[type="url"]');
            if (input.length) {
                const systemVars = {
                    'customer_name': 'John Doe',
                    'order_id': '12345',
                    'order_total': '$99.99',
                    'order_status': 'Processing',
                    'site_name': window.location.hostname,
                    'site_url': window.location.origin,
                    'recovery_link': window.location.origin + '/cart/',
                    'coupon_code': 'WELCOME10'
                };
                
                input.val(systemVars[value] || '');
            }
            
            // Highlight the save button
            const $saveButton = $(this).closest('form').find('.wabot-button[type="submit"]');
            $saveButton.css({
                'background-color': '#f80',
                'box-shadow': '0 0 5px rgba(255, 136, 0, 0.5)'
            });
            
            // Add save reminder if not already present
            if (!$('.save-reminder').length) {
                $saveButton.after('<span class="save-reminder" style="margin-left: 10px; color: #f80; font-style: italic;">Don\'t forget to save your changes!</span>');
            }
        });
    }

    // Handle template selection
    $('.template-select-trigger').on('click', function() {
        const container = $(this).closest('.wabot-form-group');
        const templateKey = $(this).data('key');
        
        // Show template gallery modal
        showTemplateGallery(templateKey, function(selectedTemplate) {
            if (selectedTemplate) {
                // Update template configuration
                updateTemplateConfiguration(selectedTemplate, container);
            }
        });
    });

    // Initialize existing templates
    $('.template-select-trigger').each(function() {
        const container = $(this).closest('.wabot-form-group');
        const templateName = container.find('input[type="hidden"]').val();
        
        if (templateName) {
            // Get template data and update configuration
            $.ajax({
                url: wabotAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wabot_get_template_preview',
                    template_name: templateName,
                    nonce: wabotAdmin.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        updateTemplateConfiguration(response.data, container);
                    }
                }
            });
        }
    });

    // Variable picker functionality
    $('.insert-variable').on('change', function() {
        const value = $(this).val();
        if (!value) return;
        
        // Save the selected value
        $(this).find(`option[value="${value}"]`).prop('selected', true);
        
        // If this is a default value field, update it with the system variable
        const input = $(this).closest('td').next('td').find('input[type="text"], input[type="url"]');
        if (input.length) {
            const systemVars = {
                'customer_name': 'John Doe',
                'order_id': '12345',
                'order_total': '$99.99',
                'order_status': 'Processing',
                'site_name': window.location.hostname,
                'site_url': window.location.origin,
                'recovery_link': window.location.origin + '/cart/',
                'coupon_code': 'WELCOME10'
            };
            
            input.val(systemVars[value] || '');
        }
        
        // Highlight the save button
        const $saveButton = $(this).closest('form').find('.wabot-button[type="submit"]');
        $saveButton.css({
            'background-color': '#f80',
            'box-shadow': '0 0 5px rgba(255, 136, 0, 0.5)'
        });
        
        // Add save reminder if not already present
        if (!$('.save-reminder').length) {
            $saveButton.after('<span class="save-reminder" style="margin-left: 10px; color: #f80; font-style: italic;">Don\'t forget to save your changes!</span>');
        }
    });

    // Handle template enable/disable toggle
    $('input[id^="template_"][id$="_enabled"]').on('change', function() {
        const container = $(this).closest('.wabot-form-group');
        const isEnabled = $(this).prop('checked');
        
        container.find('.template-select-trigger').toggleClass('disabled', !isEnabled);
        container.find('.template-configuration').toggleClass('disabled', !isEnabled);
        container.find('.template-actions').toggleClass('disabled', !isEnabled);
        container.find('.wabot-btn-icon').toggleClass('disabled', !isEnabled);
    });

    // Clear Template Cache button
    $(document).on('click', '#clear-template-cache-btn', function() {
        const $button = $(this);
        const originalText = $button.html();
        $button.html('<span class="dashicons dashicons-update" style="margin-right: 5px; animation: spin 1s linear infinite;"></span> Clearing...');
        $button.prop('disabled', true);
        
        // Add spin animation if not already defined
        if (!document.getElementById('spin-animation')) {
            $('head').append(`
                <style id="spin-animation">
                    @keyframes spin {
                        0% { transform: rotate(0deg); }
                        100% { transform: rotate(360deg); }
                    }
                </style>
            `);
        }

        $.ajax({
            url: wabotAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'wabot_clear_template_cache',
                nonce: wabotAdmin.nonce
            },
            success: function(response) {
                $button.html(originalText);
                $button.prop('disabled', false);
                if (response.success) {
                    showNotification('Template cache cleared!', 'success');
                } else {
                    showNotification('Failed to clear template cache.', 'error');
                }
            },
            error: function(xhr, status, error) {
                $button.html(originalText);
                $button.prop('disabled', false);
                showNotification('AJAX error clearing cache.', 'error');
            }
        });
    });
});


