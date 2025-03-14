/**
 * WordPress Word Markup Cleaner - ACF Integration Scripts
 */
(function($) {
    'use strict';

    // Store field type lists
    var textFieldTypes = [];
    var containerFieldTypes = [];

    /**
     * Initialize ACF integration features
     */
    function initAcfIntegration() {
        // Get settings from localized variables
        loadFieldTypeSettings();
        
        // Add notice to WYSIWYG editors
        addNoticeToWysiwygEditors();
        
        // Add paste handler to detect Word content
        addPasteHandlers();
        
        // Check if cleaner is enabled
        checkCleanerStatus();
    }
    
    /**
     * Load field type settings from localized variables
     */
    function loadFieldTypeSettings() {
        if (typeof wordMarkupCleanerSettings !== 'undefined') {
            if (wordMarkupCleanerSettings.textFieldTypes) {
                textFieldTypes = wordMarkupCleanerSettings.textFieldTypes;
            }
            
            if (wordMarkupCleanerSettings.containerFieldTypes) {
                containerFieldTypes = wordMarkupCleanerSettings.containerFieldTypes;
            }
        }
    }
    
    /**
     * Add a notice to all WYSIWYG editors
     */
    function addNoticeToWysiwygEditors() {
        // Add notice to existing WYSIWYG editors
        $('.acf-field-wysiwyg .wp-editor-container').each(function() {
            addNoticeToEditor($(this));
        });
        
        // Listen for new WYSIWYG editors being added (e.g. in repeaters)
        acf.addAction('append', function($el) {
            // Find any WYSIWYG editors in the appended element
            $el.find('.acf-field-wysiwyg .wp-editor-container').each(function() {
                addNoticeToEditor($(this));
            });
        });
    }
    
    /**
     * Add a notice to a specific WYSIWYG editor
     * 
     * @param {jQuery} $editor The editor container
     */
    function addNoticeToEditor($editor) {
        // Only add if not already added
        if ($editor.find('.word-markup-cleaner-notice').length === 0) {
            $editor.prepend(
                '<div class="word-markup-cleaner-notice">' +
                '<strong>Word Markup Cleaner:</strong> ' +
                'Content pasted from Microsoft Word will be automatically cleaned when saved. ' +
                'For best results, use "Paste as text" (Ctrl+Shift+V or ⌘+Shift+V) when pasting from Word.' +
                '</div>'
            );
        }
    }
    
    /**
     * Add paste event handlers to detect Word content
     */
    function addPasteHandlers() {
        // Generate selector for all text field types
        var textFieldSelectors = [];
        
        // For each text field type, create a selector
        textFieldTypes.forEach(function(type) {
            if (type === 'wysiwyg') {
                // WYSIWYG gets handled separately
                return;
            }
            
            textFieldSelectors.push('.acf-field-' + type + ' input, .acf-field-' + type + ' textarea');
        });
        
        // Only attach handlers to text field types
        if (textFieldSelectors.length > 0) {
            $(textFieldSelectors.join(', ')).on('paste', function(e) {
                detectWordContent(e, $(this));
            });
        }
        
        // Handle paste in WYSIWYG editors
        acf.addAction('wysiwyg_init', function(editor, id, field) {
            editor.on('PastePreProcess', function(e) {
                // Check if this seems to be Word content
                if (containsWordMarkup(e.content)) {
                    // Add a class to the field to indicate Word content
                    $('#' + id).closest('.acf-field').addClass('has-word-markup');
                    
                    // Show a notification to the user
                    showWordContentDetectedNotification(
                        $('#' + id).closest('.acf-field').find('.acf-label label').text()
                    );
                }
            });
        });
    }
    
    /**
     * Detect if pasted content is from Word
     * 
     * @param {Event} e The paste event
     * @param {jQuery} $field The field element
     */
    function detectWordContent(e, $field) {
        // We need to wait a moment to get the pasted content
        setTimeout(function() {
            var content = $field.val();
            
            // Check if this looks like Word content
            if (containsWordMarkup(content)) {
                // Add a class to the field to indicate Word content
                $field.closest('.acf-field').addClass('has-word-markup');
                
                // Show a notification to the user
                showWordContentDetectedNotification(
                    $field.closest('.acf-field').find('.acf-label label').text()
                );
            }
        }, 100);
    }
    
    /**
     * Check if content contains Word markup
     * 
     * @param {string} content The content to check
     * @return {bool} Whether the content contains Word markup
     */
    function containsWordMarkup(content) {
        // Common markers of Word content
        var wordMarkers = [
            'mso-',                // MSO styles
            'class="Mso',          // MSO classes
            '<o:p>',               // Office XML tags
            '<!--[if',             // Word conditional comments
            'w:WordDocument',      // Word XML namespace
            'font-family:Calibri', // Common Word font
            'panose-1:',           // Word typography metadata
            'lang=',               // Word language metadata
            'class="MsoNormal"'    // Common Word class
        ];
        
        // Check if any of the markers are present
        for (var i = 0; i < wordMarkers.length; i++) {
            if (content.indexOf(wordMarkers[i]) !== -1) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Show a notification that Word content was detected
     * 
     * @param {string} fieldName The name of the field where Word content was detected
     */
    function showWordContentDetectedNotification(fieldName) {
        // Create notification if it doesn't exist
        if ($('#word-markup-notification').length === 0) {
            $('body').append(
                '<div id="word-markup-notification" style="display:none; position:fixed; bottom:20px; right:20px; ' +
                'background:#4CAF50; color:#fff; padding:10px 15px; border-radius:3px; box-shadow:0 2px 5px rgba(0,0,0,0.2); ' +
                'z-index:99999; max-width:300px;"></div>'
            );
        }
        
        // Show the notification
        $('#word-markup-notification')
            .html('<strong>Word content detected</strong><br>Microsoft Word markup found in "' + fieldName + '" and will be cleaned when saved.')
            .fadeIn()
            .delay(5000)
            .fadeOut();
    }
    
    /**
     * Check if the Word Markup Cleaner is enabled
     */
    function checkCleanerStatus() {
        // Check if the global settings variable exists
        if (typeof wordMarkupCleanerSettings !== 'undefined') {
            if (!wordMarkupCleanerSettings.acfCleaningEnabled) {
                $('body').addClass('word-markup-cleaner-disabled');
            }
        }
    }
    
    // Initialize when ACF is ready
    $(document).ready(function() {
        // Initialize only if ACF is loaded
        if (typeof acf !== 'undefined') {
            acf.addAction('ready', initAcfIntegration);
        }
    });
    
})(jQuery);