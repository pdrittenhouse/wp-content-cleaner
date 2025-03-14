/**
 * WordPress Word Markup Cleaner - Content Cleaner Scripts
 */
(function($) {
    'use strict';

    /**
     * Initialize any frontend features for the content cleaner
     */
    function init() {
        // Add class to indicate content has been cleaned (if enabled)
        if (typeof wordMarkupCleanerSettings !== 'undefined' && wordMarkupCleanerSettings.addIndicator) {
            $('.entry-content').addClass('word-markup-cleaned');
        }
        
        // Add listeners for admin bar (if available)
        if ($('#wp-admin-bar-word-markup-cleaner').length) {
            $('#wp-admin-bar-word-markup-cleaner').on('click', function(e) {
                e.preventDefault();
                toggleWordMarkupHighlight();
            });
        }
    }
    
    /**
     * Toggle highlighting of any remaining Word markup
     * This is only relevant for admin users who want to check if any Word markup remains
     */
    function toggleWordMarkupHighlight() {
        $('body').toggleClass('word-markup-highlight-mode');
        
        if ($('body').hasClass('word-markup-highlight-mode')) {
            // Highlight any remaining Word markup
            highlightRemainingWordMarkup();
        } else {
            // Remove highlights
            $('.word-markup-highlight').each(function() {
                $(this).replaceWith($(this).html());
            });
        }
    }
    
    /**
     * Highlight any remaining Word markup in the content
     * This is for debugging purposes only
     */
    function highlightRemainingWordMarkup() {
        // Find elements with style attributes containing mso-
        $('[style*="mso-"]').addClass('word-markup-remnant').wrap('<span class="word-markup-highlight"></span>');
        
        // Find classes with Mso
        $('[class*="Mso"]').addClass('word-markup-remnant').wrap('<span class="word-markup-highlight"></span>');
        
        // Find XML namespace elements (rare but possible)
        $('o\\:p, w\\:*').addClass('word-markup-remnant').wrap('<span class="word-markup-highlight"></span>');
    }
    
    // Initialize when document is ready
    $(document).ready(init);
    
})(jQuery);