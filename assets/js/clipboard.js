/**
 * WordPress Word Markup Cleaner - Clipboard Utility
 * 
 * A shared utility for copying text to clipboard across the plugin
 */
(function($) {
    'use strict';

    // Create the global WordMarkupClipboard object
    window.WordMarkupClipboard = {
        /**
         * Copy text to clipboard
         * 
         * @param {string|Element} source Text content or DOM element to copy
         * @param {Object} options Configuration options
         * @return {boolean} Whether the operation was successful
         */
        copy: function(source, options) {
            // Default options
            options = $.extend({
                message: 'Copied to clipboard!',
                duration: 3000,
                position: 'bottom-right',
                elementType: 'auto', // 'auto', 'text', or 'element'
                onSuccess: null,
                onError: null
            }, options || {});
            
            var successful = false;
            var content = '';
            
            try {
                // Determine source type if set to auto
                if (options.elementType === 'auto') {
                    if (typeof source === 'string') {
                        options.elementType = 'text';
                    } else if (source instanceof Element || (typeof source === 'object' && source.nodeType === 1)) {
                        options.elementType = 'element';
                    } else if (source instanceof jQuery) {
                        source = source[0];
                        options.elementType = 'element';
                    }
                }
                
                // Handle different source types
                if (options.elementType === 'text') {
                    // Direct text content
                    content = source;
                    successful = this.copyTextToClipboard(content);
                } else if (options.elementType === 'element') {
                    // DOM element
                    successful = this.copyElementToClipboard(source);
                    content = $(source).text();
                } else {
                    throw new Error('Invalid source type');
                }
                
                // Handle success/failure
                if (successful) {
                    this.showNotification(options.message, options.duration, options.position);
                    
                    if (typeof options.onSuccess === 'function') {
                        options.onSuccess(content);
                    }
                } else {
                    var errorMsg = 'Failed to copy content to clipboard';
                    if (typeof options.onError === 'function') {
                        options.onError(errorMsg);
                    } else {
                        alert(errorMsg);
                    }
                }
            } catch (err) {
                var errorMsg = 'Error copying to clipboard: ' + err.message;
                if (typeof options.onError === 'function') {
                    options.onError(errorMsg);
                } else {
                    alert(errorMsg);
                }
                
                successful = false;
            }
            
            return successful;
        },
        
        /**
         * Copy text content to clipboard
         * 
         * @param {string} text The text to copy
         * @return {boolean} Whether the operation was successful
         */
        copyTextToClipboard: function(text) {
            // Create a temporary textarea
            var textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'absolute';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            
            // Select the text and copy
            textarea.select();
            var successful = document.execCommand('copy');
            
            // Remove the temporary textarea
            document.body.removeChild(textarea);
            
            return successful;
        },
        
        /**
         * Copy content from a DOM element to clipboard
         * 
         * @param {Element} element The DOM element to copy from
         * @return {boolean} Whether the operation was successful
         */
        copyElementToClipboard: function(element) {
            // Create a range and select the element's content
            var range = document.createRange();
            range.selectNode(element);
            
            // Clear any existing selections
            window.getSelection().removeAllRanges();
            window.getSelection().addRange(range);
            
            // Execute copy command
            var successful = document.execCommand('copy');
            
            // Clear the selection
            window.getSelection().removeAllRanges();
            
            return successful;
        },
        
        /**
         * Show a notification message
         * 
         * @param {string} message The message to display
         * @param {number} duration How long to show the notification in ms
         * @param {string} position Where to show the notification (top-right, bottom-right, etc.)
         */
        showNotification: function(message, duration, position) {
            // Get or create notification element
            var $notification = $('#wp-word-cleaner-notification');
            
            // Create if it doesn't exist
            if ($notification.length === 0) {
                // Define positions
                var positions = {
                    'top-right': 'top: 20px; right: 20px;',
                    'top-left': 'top: 20px; left: 20px;',
                    'bottom-right': 'bottom: 20px; right: 20px;',
                    'bottom-left': 'bottom: 20px; left: 20px;',
                    'center': 'top: 50%; left: 50%; transform: translate(-50%, -50%);'
                };
                
                var positionStyle = positions[position] || positions['bottom-right'];
                
                $notification = $('<div>', {
                    id: 'wp-word-cleaner-notification',
                    css: {
                        display: 'none',
                        position: 'fixed',
                        background: '#4CAF50',
                        color: 'white',
                        padding: '10px 20px',
                        borderRadius: '4px',
                        boxShadow: '0 2px 5px rgba(0,0,0,0.2)',
                        zIndex: 9999,
                        maxWidth: '300px'
                    },
                    html: message
                });
                
                // Add position styles
                $notification.attr('style', $notification.attr('style') + '; ' + positionStyle);
                
                $('body').append($notification);
            } else {
                // Update existing notification
                $notification.html(message);
            }
            
            // Show the notification
            $notification.fadeIn(300).delay(duration).fadeOut(500);
        }
    };
    
})(jQuery);