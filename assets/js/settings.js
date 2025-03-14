/**
 * WordPress Word Markup Cleaner - Admin Scripts
 */
(function($) {
    'use strict';

    /**
     * Copy log content to clipboard
     * 
     * @param {string} logId The ID of the log element
     */
    function copyLogToClipboard(logId) {
        const logElement = document.getElementById(logId);
        if (!logElement) return;
        
        // Use the shared clipboard utility
        if (typeof WordMarkupClipboard !== 'undefined') {
            WordMarkupClipboard.copy(logElement, {
                message: 'Log copied to clipboard!',
                position: 'bottom-right',
                onError: function(errorMsg) {
                    alert(errorMsg);
                }
            });
        } else {
            // Fallback if utility is not available
            alert('Clipboard utility not loaded. Please try selecting and copying manually.');
        }
    }
    
    /**
     * Handle tab navigation
     */
    function setupTabNavigation() {
        // Handle tab clicks
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            const tabId = $(this).attr('href');
            
            // Update active tab
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            
            // Show selected tab content
            $('.word-markup-tab-content').removeClass('active');
            $(tabId).addClass('active');
            
            // Store active tab in localStorage
            if (window.localStorage) {
                localStorage.setItem('wordMarkupCleanerActiveTab', tabId);
            }
        });
        
        // Simple tab restoration from localStorage
        if (window.localStorage) {
            const savedTab = localStorage.getItem('wordMarkupCleanerActiveTab');
            
            if (savedTab && $(savedTab).length && $('.nav-tab[href="' + savedTab + '"]').length) {
                // If there's a saved valid tab preference, use it
                $('.nav-tab[href="' + savedTab + '"]').click();
            } else {
                // No valid saved preference, default to first tab
                $('.nav-tab:first').click();
            }
        } else {
            // No localStorage, default to first tab
            $('.nav-tab:first').click();
        }
    }
    
    /**
     * Initialize the admin page functionality
     */
    function init() {
        // Set up tab navigation
        setupTabNavigation();

        // Toggle advanced mode
        $('#advanced_mode').on('change', function() {
            if ($(this).is(':checked')) {
                $('.advanced-options').slideDown(200);
            } else {
                $('.advanced-options').slideUp(200);
            }
        });
        
        // Toggle all cleaning options
        $('.toggle-options').on('click', function(e) {
            e.preventDefault();
            
            // Get all checkboxes in the options grid
            var $checkboxes = $('.options-grid input[type="checkbox"]');
            
            // Check if all are checked
            var allChecked = $checkboxes.length === $checkboxes.filter(':checked').length;
            
            // Toggle based on current state
            $checkboxes.prop('checked', !allChecked);
        });
        
        // Handle content type changes
        $('#content_type').on('change', function() {
            var selectedType = $(this).val();
            
            // If custom is selected, enable checkboxes
            $('.options-grid input[type="checkbox"]').prop('disabled', selectedType !== 'custom');
            
            // Show notification when a predefined content type is selected
            if (selectedType !== 'custom') {
                // Remove any existing notification
                $('.content-type-selection .notice').remove();
                
                // Add notification
                var $notification = $('<div class="notice notice-info inline"><p><strong>Note:</strong> Using predefined settings for ' + selectedType + ' content type. Individual options above will be ignored.</p></div>');
                $('.content-type-selection').append($notification);
            } else {
                // Remove notification when custom is selected
                $('.content-type-selection .notice').remove();
            }
        });
        
        // Initialize content type selector state on page load
        $('#content_type').trigger('change');
        
        // Handle click on "Copy Log" button
        $('.copy-log-button').on('click', function() {
            const logId = $(this).data('log-id');
            copyLogToClipboard(logId);
        });
        
        // Remove clear_log parameter from URL after page load
        if (window.location.href.indexOf('clear_log=1') > -1) {
            const cleanUrl = window.location.href.replace(/[?&]clear_log=1/, '');
            window.history.replaceState({}, document.title, cleanUrl);
        }

        // Handle content type dropdown
        $('#content-type-select').on('change', function() {
            var selectedType = $(this).val();
            
            // Hide all panels
            $('.content-panel').hide();
            
            // Show the selected panel
            $('#panel-' + selectedType).show();
        }).change(); // Trigger change to show the first option
    }
    
    // Initialize when document is ready
    $(document).ready(init);
    
})(jQuery);