/**
 * WordPress Word Markup Cleaner - Logger Scripts
 */
(function($) {
    'use strict';

    // Store global settings
    var settings = {
        autoRefresh: false,
        refreshInterval: 5000, // 5 seconds
        refreshTimer: null,
        expanded: false,
        lastLogSize: 0,
        filterText: '',
        lastUpdate: 0
    };
    
    /**
     * Initialize the log viewer
     */
    function initLogViewer() {
        // Create the log viewer UI if it doesn't exist
        if ($('.word-markup-log-viewer').length === 0 && $('.debug-log').length > 0) {
            createLogViewer();
        }
        
        // Attach event handlers
        attachEventHandlers();
        
        // Load log content initially
        loadLogContent();
    }
    
    /**
     * Create the log viewer UI
     */
    function createLogViewer() {
        // Replace the existing debug log content with our enhanced viewer
        $('.debug-log').html(
            '<div class="word-markup-log-viewer">' +
                '<div class="log-filters">' +
                    '<input type="text" id="log-filter" placeholder="Filter log..." />' +
                    '<button type="button" class="button log-apply-filter">Apply Filter</button>' +
                    '<button type="button" class="button log-clear-filter">Clear</button>' +
                    '<div class="auto-refresh-toggle">' +
                        '<label>' +
                            '<input type="checkbox" id="auto-refresh-toggle" />' +
                            'Auto-refresh' +
                        '</label>' +
                    '</div>' +
                '</div>' +
                '<div class="log-container">' +
                    '<div class="log-loading"><span>Loading log...</span></div>' +
                    '<pre id="log-content"></pre>' +
                '</div>' +
                '<div class="log-actions">' +
                    '<div class="log-buttons">' +
                        '<button type="button" class="button button-secondary log-refresh">Refresh</button>' +
                        '<button type="button" class="button button-secondary log-clear">Clear Log</button>' +
                        '<button type="button" class="button button-secondary log-copy">Copy to Clipboard</button>' +
                        '<button type="button" class="button button-secondary log-expand">Expand</button>' +
                    '</div>' +
                    '<div class="log-info">' +
                        '<span class="log-size">Size: --</span>' +
                        '<span class="log-updated">Updated: --</span>' +
                    '</div>' +
                '</div>' +
                '<div class="log-file-info">' +
                    '<dl>' +
                        '<dt>Log Status:</dt>' +
                        '<dd>' +
                            '<span class="log-status ' + (wordMarkupLoggerData.isDebugEnabled ? 'enabled' : 'disabled') + '"></span>' +
                            (wordMarkupLoggerData.isDebugEnabled ? 'Enabled' : 'Disabled') +
                        '</dd>' +
                        '<dt>Log Path:</dt>' +
                        '<dd>' + wordMarkupLoggerData.logPath + '</dd>' +
                        '<dt>Max Log Size:</dt>' +
                        '<dd>' + wordMarkupLoggerData.maxLogSizeFormatted + ' (logs will be rotated when this size is reached)</dd>' +
                    '</dl>' +
                '</div>' +
            '</div>'
        );
        
        // Create the copy notification element
        if ($('.copy-notification').length === 0) {
            $('body').append('<div class="copy-notification">Log copied to clipboard!</div>');
        }
    }
    
    /**
     * Attach event handlers to the log viewer UI
     */
    function attachEventHandlers() {
        // Refresh log button
        $('.log-refresh').on('click', function() {
            loadLogContent();
        });
        
        // Clear log button
        $('.log-clear').on('click', function() {
            clearLog();
        });
        
        // Copy to clipboard button
        $('.log-copy').on('click', function() {
            copyLogToClipboard();
        });
        
        // Expand log button
        $('.log-expand').on('click', function() {
            toggleExpandedView();
        });
        
        // Auto-refresh toggle
        $('#auto-refresh-toggle').on('change', function() {
            settings.autoRefresh = $(this).prop('checked');
            
            if (settings.autoRefresh) {
                startAutoRefresh();
            } else {
                stopAutoRefresh();
            }
        });
        
        // Filter log
        $('.log-apply-filter').on('click', function() {
            settings.filterText = $('#log-filter').val();
            filterLogContent();
        });
        
        // Clear filter
        $('.log-clear-filter').on('click', function() {
            $('#log-filter').val('');
            settings.filterText = '';
            filterLogContent();
        });
        
        // Filter on enter key
        $('#log-filter').on('keypress', function(e) {
            if (e.which === 13) {
                settings.filterText = $(this).val();
                filterLogContent();
                e.preventDefault();
            }
        });
    }
    
    /**
     * Start auto-refresh timer
     */
    function startAutoRefresh() {
        // Clear any existing timer
        stopAutoRefresh();
        
        // Start new timer
        settings.refreshTimer = setInterval(function() {
            loadLogContent();
        }, settings.refreshInterval);
    }
    
    /**
     * Stop auto-refresh timer
     */
    function stopAutoRefresh() {
        if (settings.refreshTimer) {
            clearInterval(settings.refreshTimer);
            settings.refreshTimer = null;
        }
    }
    
    /**
     * Load log content via AJAX
     */
    function loadLogContent() {
        // Show loading indicator
        $('.log-loading').show();
        
        // Make AJAX request
        $.ajax({
            url: wordMarkupLoggerData.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'word_markup_cleaner_get_log',
                nonce: wordMarkupLoggerData.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update log content
                    $('#log-content').html(escapeHtml(response.data.content));
                    
                    // Update log info
                    $('.log-size').text('Size: ' + response.data.size_formatted);
                    $('.log-updated').text('Updated: ' + response.data.last_updated);
                    
                    // Store last log size for comparison
                    settings.lastLogSize = response.data.size;
                    settings.lastUpdate = new Date();
                    
                    // Apply filter if set
                    if (settings.filterText) {
                        filterLogContent();
                    }
                    
                    // Scroll to bottom
                    var logContainer = $('.log-container');
                    logContainer.scrollTop(logContainer[0].scrollHeight);
                    
                    // Show empty message if needed
                    if (!response.data.content) {
                        $('.log-container').addClass('empty');
                        $('#log-content').html('<div class="no-log-content">Log is empty.</div>');
                    } else {
                        $('.log-container').removeClass('empty');
                    }
                } else {
                    // Show error message
                    $('#log-content').html('<div class="no-log-content">Error loading log: ' + response.data.message + '</div>');
                }
            },
            error: function() {
                // Show error message
                $('#log-content').html('<div class="no-log-content">Error loading log. Please try again.</div>');
            },
            complete: function() {
                // Hide loading indicator
                $('.log-loading').hide();
            }
        });
    }
    
    /**
     * Filter log content based on search text
     */
    function filterLogContent() {
        if (!settings.filterText) {
            // If no filter, restore original content
            $('#log-content').find('.log-highlight').each(function() {
                $(this).replaceWith($(this).text());
            });
            return;
        }
        
        // Get log content
        var content = $('#log-content').html();
        
        // Replace existing highlights first
        content = content.replace(/<span class="log-highlight">(.*?)<\/span>/g, '$1');
        
        // Create a regex for the search text
        var regex = new RegExp('(' + escapeRegExp(settings.filterText) + ')', 'gi');
        
        // Highlight matches
        content = content.replace(regex, '<span class="log-highlight">$1</span>');
        
        // Update content
        $('#log-content').html(content);
    }
    
    /**
     * Clear log file via AJAX
     */
    function clearLog() {
        // Confirm before clearing
        if (!confirm('Are you sure you want to clear the log file? This cannot be undone.')) {
            return;
        }
        
        // Show loading indicator
        $('.log-loading').show();
        
        // Make AJAX request
        $.ajax({
            url: wordMarkupLoggerData.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'word_markup_cleaner_clear_log',
                nonce: wordMarkupLoggerData.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Reload log content
                    loadLogContent();
                } else {
                    // Show error message
                    alert('Error clearing log: ' + response.data.message);
                }
            },
            error: function() {
                // Show error message
                alert('Error clearing log. Please try again.');
            },
            complete: function() {
                // Hide loading indicator
                $('.log-loading').hide();
            }
        });
    }
    
    /**
     * Copy log content to clipboard
     */
    function copyLogToClipboard() {
        var logContent = $('#log-content').text();
        
        // Use the shared clipboard utility instead
        if (typeof WordMarkupClipboard !== 'undefined') {
            WordMarkupClipboard.copy(logContent, {
                message: 'Log copied to clipboard!',
                elementType: 'text', // Explicitly set to copy text content
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
     * Toggle expanded view for log
     */
    function toggleExpandedView() {
        if (settings.expanded) {
            // Exit expanded view
            $('.log-viewer-expanded').remove();
            settings.expanded = false;
            
            // Show the original viewer
            $('.word-markup-log-viewer').show();
            
            // Update button text
            $('.log-expand').text('Expand');
        } else {
            // Enter expanded view
            settings.expanded = true;
            
            // Clone the log viewer
            var expandedViewer = $('.word-markup-log-viewer').clone();
            expandedViewer.addClass('log-expanded');
            
            // Add a header with close button
            expandedViewer.prepend(
                '<div class="log-header">' +
                    '<div class="log-title">Word Markup Cleaner Debug Log</div>' +
                    '<button type="button" class="button exit-expanded">Exit Expanded View</button>' +
                '</div>'
            );
            
            // Append to body
            $('body').append(expandedViewer);
            
            // Hide the original viewer
            $('.word-markup-log-viewer').hide();
            
            // Attach event handler to exit button
            $('.exit-expanded').on('click', function() {
                toggleExpandedView();
            });
            
            // Update button text in original viewer
            $('.log-expand').text('Exit Expanded View');
        }
    }
    
    /**
     * Escape HTML special characters
     * 
     * @param {string} text The text to escape
     * @return {string} The escaped text
     */
    function escapeHtml(text) {
        if (typeof text !== 'string') {
            return '';
        }
        
        // Create a temporary element
        var div = document.createElement('div');
        // Set the text as textContent which automatically escapes HTML
        div.textContent = text;
        // Return the HTML-escaped string
        return div.innerHTML;
    }
    
    /**
     * Escape special characters for use in a regular expression
     * 
     * @param {string} string The string to escape
     * @return {string} The escaped string
     */
    function escapeRegExp(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
    
    // Initialize when the document is ready
    $(document).ready(function() {
        // Only initialize if we're on the settings page
        if (typeof wordMarkupLoggerData !== 'undefined') {
            initLogViewer();
        }
    });
    
})(jQuery);