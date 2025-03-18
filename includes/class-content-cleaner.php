<?php
/**
 * Content cleaning functionality for the Word Markup Cleaner plugin
 *
 * @package WordPress_Word_Markup_Cleaner
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class WP_Word_Markup_Cleaner_Content
 * 
 * Handles the core Word markup cleaning functionality
 */
class WP_Word_Markup_Cleaner_Content {

    /**
     * Regex patterns for Word markup cleaning
     */
    const PATTERN_O_TAGS = '/<\/?o:p>\s*/is';
    const PATTERN_XML_NAMESPACE = '/<\/?[a-z]+:[a-z]+[^>]*>\s*/is';
    const PATTERN_CONDITIONAL_COMMENTS = '/<!--\[if.*?\]>.*?<!\[endif\]-->/is';
    const PATTERN_CONDITIONAL_TAGS = '/<!\[if.*?\]>.*?<!\[endif\]>/is';
    const PATTERN_MSO_CLASSES = '/class\s*=\s*(["\'])(.*?)(Mso|mso)[^"\']*\1/is';
    const PATTERN_MSO_STYLES = '/(<[^>]*)\s+mso-[^=\s>]*:[^;>]*;?/is';
    const PATTERN_FONT_FAMILY_STYLE = '/(<p[^>]*+)(\s+style\s*=\s*(["\']))([^"\']*?font-family\s*:\s*[^;]+;?)([^"\']*?)(\3[^>]*+>)/is';
    const PATTERN_FONT_FAMILY = '/(<p[^>]*)\s+font-family:[^;>]*;?/is';
    const PATTERN_FONT_SIZE = '/(<p[^>]*)\s+font-size:[^;>]*;?/is';
    const PATTERN_LINE_HEIGHT = '/(<p[^>]*)\s+line-height:[^;>]*;?/is';
    const PATTERN_TAG_FONT_FAMILY = '/<([^>]*)font-family:[^;>]*;?/is';
    const PATTERN_TAG_FONT_SIZE = '/<([^>]*)font-size:[^;>]*;?/is';
    const PATTERN_TAG_FONT_WEIGHT = '/<([^>]*)font-weight:[^;>]*;?/is';
    const PATTERN_TAG_FONT_STYLE = '/<([^>]*)font-style:[^;>]*;?/is';
    const PATTERN_TAG_TRAILING = '/(<[^>]*);?\s*>/is';
    const PATTERN_STYLE_ATTRS = '/<(p|span|li|td|tr|th|div|ul|ol|table)([^>]*)style\s*=\s*["\'][^"\']*["\']([^>]*)>/is';
    const PATTERN_ALL_STYLE_ATTRS = '/\s+style\s*=\s*(["\'])[^"\']*?\1/is'; 
    const PATTERN_LANG_ATTRS = '/<(p|span|div|td|tr|th|li|ul|ol|table)([^>]*)lang\s*=\s*["\'][^"\']*["\']([^>]*)>/is';
    const PATTERN_MSO_ATTRS = '/<([^>]*)mso-[^=\s>]*\s*=\s*["\'][^"\']*["\']([^>]*)>/is';
    const PATTERN_MSO_STYLES_REMAINING = '/style\s*=\s*["\'][^"\']*mso-[^:]+:[^;]+;?[^"\']*["\']/is';
    const PATTERN_EMPTY_STYLE = '/style\s*=\s*["\'](\s*)["\']/';
    const PATTERN_EMPTY_SPAN = '/<span>\s*<\/span>/is';
    const PATTERN_EMPTY_SPAN_ATTR = '/<span\s+[^>]*>\s*<\/span>/is';
    const PATTERN_PT_TAGS = '/pt">(\s*<\/?(p|span|div))/is';
    const PATTERN_PT_REMNANTS = '/pt">/is';
    const PATTERN_EMPTY_CLASS = '/class\s*=\s*["\'](\s*)["\']/';

    // Table and list extraction patterns
    const PATTERN_TABLES = '/<table[^>]*>.*?<\/table>/is';
    const PATTERN_LISTS = '/<(ol|ul)[^>]*>.*?<\/\1>/is';
    const PATTERN_MSO_LISTS = '/<p[^>]*class\s*=\s*["\']MsoListParagraph[^"\']*["\'][^>]*>.*?<\/p>(\s*<p[^>]*class\s*=\s*["\']MsoListParagraph[^"\']*["\'][^>]*>.*?<\/p>)*/is';

    // Table cleaning patterns
    const PATTERN_TABLE_STRUCTURE = '/<table[^>]*>(.*)<\/table>/is';
    const PATTERN_TABLE_ATTRS = '/<table([^>]*)>/is';
    const PATTERN_TABLE_ROWS = '/<tr[^>]*>.*?<\/tr>/is';
    const PATTERN_TABLE_CELLS = '/<td[^>]*>.*?<\/td>/is';
    const PATTERN_CELL_CONTENT = '/<td([^>]*)>(.*)<\/td>/is';
    const PATTERN_BORDER = '/border\s*=\s*["\']?(\d+)["\']?/';
    const PATTERN_CELLSPACING = '/cellspacing\s*=\s*["\']?(\d+)["\']?/';
    const PATTERN_CELLPADDING = '/cellpadding\s*=\s*["\']?(\d+)["\']?/';
    const PATTERN_WIDTH = '/width\s*=\s*["\']?(\d+)["\']?/';
    const PATTERN_VALIGN = '/valign\s*=\s*["\']?(\w+)["\']?/';
    const PATTERN_TABLE_CLASS = '/class\s*=\s*["\'][^"\']*["\']/';
    const PATTERN_TABLE_STYLE = '/style\s*=\s*["\'][^"\']*["\']/';
    const PATTERN_TABLE_MSO = '/mso-[^=\s>]*\s*=\s*["\'][^"\']*["\']/';
    const PATTERN_CELL_P = '/<p[^>]*>/';
    const PATTERN_CELL_SPAN = '/<span[^>]*>/';
    const PATTERN_NESTED_P_SIMPLE = '/<p>\s*<p>/is';

    // Additional content cleaning patterns for cells
    const PATTERN_CELL_MSO = '/mso-[^=\s>]*\s*=\s*["\'][^"\']*["\']/';

    // List cleaning patterns
    const PATTERN_LIST_SPLIT = '/<\/p>\s*<p[^>]*class\s*=\s*["\']MsoListParagraph[^"\']*["\']/is';
    const PATTERN_LIST_MARKERS = '/<!--\[if !supportLists\]-->.*?<!--\[endif\]-->/is';
    const PATTERN_LIST_PARAGRAPH = '/<p[^>]*class\s*=\s*["\']MsoListParagraph[^"\']*["\']/is';
    const PATTERN_CLOSE_P = '/<\/p>$/is';
    const PATTERN_LI_STYLE_DOUBLE = '/(<li[^>]*)\s+style="[^"]*"/is';
    const PATTERN_LI_STYLE_SINGLE = '/(<li[^>]*)\s+style=\'[^\']*\'/is';
    const PATTERN_LI_TEXT_INDENT = '/(<li[^>]*)\s+text-indent:[^;>]*;?/is';
    const PATTERN_LI_MSO_LIST = '/(<li[^>]*)\s+mso-list:[^;>]*;?/is';
    const PATTERN_LI_ALL_ATTRS = '/<li\s+[^>]*>/is';
    const PATTERN_LIST_STRUCTURE = '/<(ul|ol)[^>]*>/';
    const PATTERN_LI_TRAILING = '/<li>>\s*/';
    const PATTERN_LI_TRAILING_SPACE = '/<li>\s*>/s';
    const PATTERN_LIST_STYLE = '/<(li|ul|ol)([^>]*)style\s*=\s*["\'][^"\']*["\']([^>]*)>/is';

    // Structural fixes patterns
    const PATTERN_TABLE_P_OPEN = '/<p>\s*(<table[^>]*>)/is';
    const PATTERN_TABLE_P_CLOSE = '/(<\/table>)\s*<\/p>/is';
    const PATTERN_P_SPAN = '/<p>\s*<span>(.*?)<\/span>\s*<\/p>/is';
    const PATTERN_NESTED_P = '/<p\s+([^>]*)>\s*<p\s+([^>]*)>/is';
    const PATTERN_DOUBLE_CLOSE_P = '/<\/p>\s*<\/p>/is';
    const PATTERN_TEXT_INDENT = '/(<[^>]*)\s+text-indent:[^;>]*;?/is';
    const PATTERN_MSO_LIST_INLINE = '/(<[^>]*)\s+mso-list:[^;>]*;?/is';
    const PATTERN_MSO_INLINE = '/(<[^>]*)\s+mso-[^:=\s>]*:[^;>]*;?/is';
    const PATTERN_MARGIN = '/(<[^>]*)\s+margin[^:=\s>]*:[^;>]*;?/is';
    const PATTERN_LINE_HEIGHT_INLINE = '/(<[^>]*)\s+line-height[^:=\s>]*:[^;>]*;?/is';
    const PATTERN_FONT_INLINE = '/(<[^>]*)\s+font-[^:=\s>]*:[^;>]*;?/is';
    const PATTERN_STYLE_DOUBLE_QUOTE = '/<(p|span|div|li|td|tr|th)([^>]*)>\s*style="[^"]*"/is';
    const PATTERN_STYLE_SINGLE_QUOTE = '/<(p|span|div|li|td|tr|th)([^>]*)>\s*style=\'[^\']*\'/is';

    // Analysis patterns for logging
    const PATTERN_MSO_STYLE_ATTR = '/mso-[^:=\s>]*:[^;>]*;?/i';
    const PATTERN_CLASS_MSO_ATTR = '/class\s*=\s*(["\'])(.*?)(Mso|mso)[^"\']*\1/i';
    const PATTERN_WORD_XML_TAGS = '/<\/?(o:p|w:WordDocument|w:[^>]*|m:[^>]*|v:[^>]*)>/i';
    const PATTERN_WORD_CONDITIONALS = '/<!--\[if.*?\]>.*?<!\[endif\]-->/is';
    const PATTERN_STYLE_ATTR = '/style\s*=\s*["\'][^"\']*["\']/';
    const PATTERN_FONT_ATTR = '/font-[^:=\s>]*:[^;>]*;?/';
    const PATTERN_LOG_ANALYSIS_TRIMMED = '/^(.{0,200})(.*)/s';
    
    /**
     * Logger instance
     *
     * @var WP_Word_Markup_Cleaner_Logger
     */
    private $logger;
    
    /**
     * Options for cleaning
     * 
     * @var array
     */
    private $options;

    /**
     * Settings manager instance
     *
     * @var WP_Word_Markup_Cleaner_Settings_Manager
     */
    private $settings_manager;

    /**
     * Content cache array
     *
     * @var array
     */
    private $content_cache = array();

    /**
     * Maximum content cache entries
     *
     * @var int
     */
    private $max_cache_entries = 100;

    /**
     * Content cache enabled flag
     *
     * @var bool
     */
    private $cache_enabled = true;

    /**
     * Cache version (changes when cleaning rules change)
     *
     * @var string
     */
    private $cache_version = '1.0';

    /**
     * Cache hit counter for stats
     *
     * @var int
     */
    private $cache_hits = 0;

    /**
     * Cache miss counter for stats
     *
     * @var int
     */
    private $cache_misses = 0;

    /**
     * DOM Processor instance
     *
     * @var WP_Word_Markup_Cleaner_DOM_Processor
     */
    private $dom_processor = null;

    /**
     * Flag to track if DOM processing is available and enabled
     *
     * @var bool
     */
    private $dom_processing_enabled = true;

    /**
     * Initialize the content cleaner
     *
     * @param WP_Word_Markup_Cleaner_Logger $logger Logger instance
     * @param WP_Word_Markup_Cleaner_Settings_Manager $settings_manager Settings manager instance
     */
    public function __construct($logger, $settings_manager) {
        $this->logger = $logger;
        $this->settings_manager = $settings_manager;
        
        // Set default options based on settings manager values
        $this->options = array(
            'use_dom_processing' => $this->settings_manager->get_option('use_dom_processing', true),
            'enable_content_cleaning' => $this->settings_manager->get_option('enable_content_cleaning', true),
            'enable_debug' => $this->settings_manager->get_option('enable_debug', false),
            'protect_tables' => $this->settings_manager->get_option('protect_tables', true),
            'protect_lists' => $this->settings_manager->get_option('protect_lists', true),
            'strip_all_styles' => $this->settings_manager->get_option('strip_all_styles', false),
        );

        // Initialize cache settings
        $this->cache_enabled = apply_filters('wp_word_cleaner_content_cache_enabled', true);
        $this->max_cache_entries = apply_filters('wp_word_cleaner_max_cache_entries', 100);
        $this->cache_version = defined('WP_WORD_MARKUP_CLEANER_VERSION') ? WP_WORD_MARKUP_CLEANER_VERSION : '1.0';
        
        // Hook into post content if enabled
        if ($this->options['enable_content_cleaning']) {
            add_filter('content_save_pre', array($this, 'clean_content'), 10, 1);

            // Add hook for excerpts
            add_filter('excerpt_save_pre', array($this, 'clean_excerpt'), 10, 1);
        }
        
        // Log initialization if debug is enabled
        if ($this->options['enable_debug']) {
            $this->logger->log_debug("Content cleaner initialized");
        }

        // Register a cleanup action for scheduled cache cleanup
        if (!wp_next_scheduled('wp_word_cleaner_cache_cleanup')) {
            wp_schedule_event(time(), 'daily', 'wp_word_cleaner_cache_cleanup');
        }
        add_action('wp_word_cleaner_cache_cleanup', array($this, 'cleanup_cache'));
    }

    /**
     * Clean up content cache at shutdown
     */
    public function __destruct() {
        // Perform cache stats logging at shutdown if debug is enabled
        if ($this->options['enable_debug'] && ($this->cache_hits > 0 || $this->cache_misses > 0)) {
            $total = $this->cache_hits + $this->cache_misses;
            $hit_rate = $total > 0 ? round(($this->cache_hits / $total) * 100, 2) : 0;
            $this->logger->log_debug("Content cache stats - Hits: {$this->cache_hits}, Misses: {$this->cache_misses}, Hit rate: {$hit_rate}%");
        }
    }

    /**
     * Clean Microsoft Word markup from excerpts
     * 
     * @param string $excerpt The excerpt to be cleaned
     * @return string The cleaned excerpt
     */
    public function clean_excerpt($excerpt) {
        return $this->clean_content($excerpt, 'excerpt_save_pre');
    }

    /**
     * Safe pattern replacement with enhanced error handling
     * 
     * @param string $pattern Regex pattern
     * @param string $replacement Replacement string
     * @param string $subject Subject to search in
     * @param string $context Context for logging
     * @return string Result of replacement
     */
    private function safe_preg_replace($pattern, $replacement, $subject, $context = 'unknown') {
        // Only proceed if we have content
        if (empty($subject)) {
            return $subject;
        }
        
        try {
            // Apply replacement
            $result = preg_replace($pattern, $replacement, $subject);
            
            // Handle preg errors
            if ($result === null) {
                if ($this->options['enable_debug']) {
                    $error = preg_last_error();
                    $error_message = $this->get_preg_error_message($error);
                    $this->logger->log_debug("REGEX ERROR in {$context}: {$error_message} (Code: {$error})");
                }
                return $subject; // Return original on regex error
            }
            
            return $result;
        } catch (Exception $e) {
            if ($this->options['enable_debug']) {
                $this->logger->log_debug("Exception in {$context}: " . $e->getMessage());
            }
            return $subject; // Return original on exception
        }
    }

    /**
     * Get human-readable preg error message
     * 
     * @param int $error_code Error code from preg_last_error()
     * @return string Error message
     */
    private function get_preg_error_message($error_code) {
        $errors = array(
            PREG_NO_ERROR => 'No error',
            PREG_INTERNAL_ERROR => 'Internal PCRE error',
            PREG_BACKTRACK_LIMIT_ERROR => 'Backtrack limit exhausted',
            PREG_RECURSION_LIMIT_ERROR => 'Recursion limit exhausted',
            PREG_BAD_UTF8_ERROR => 'Malformed UTF-8 data',
            PREG_BAD_UTF8_OFFSET_ERROR => 'Bad UTF-8 offset'
        );
        
        // Add PCRE2 error codes if available (PHP 7.3+)
        if (defined('PREG_JIT_STACKLIMIT_ERROR')) {
            $errors[PREG_JIT_STACKLIMIT_ERROR] = 'JIT stack limit exhausted';
        }
        
        return isset($errors[$error_code]) ? $errors[$error_code] : 'Unknown error';
    }

    /**
     * Process large content in chunks to avoid memory issues
     * 
     * @param string $content The content to process
     * @param string $content_type Content type identifier
     * @param array $cleaning_level Cleaning levels
     * @return string Processed content
     */
    private function process_large_content_in_chunks($content, $content_type, $cleaning_level) {
        if (empty($content)) {
            return $content;
        }
        
        try {
            if ($this->options['enable_debug']) {
                $this->logger->log_debug("Processing large content in chunks: " . strlen($content) . " bytes");
            }
            
            // Define chunk size
            $chunk_size = 40000;
            
            // Define safe breaking points
            $safe_breaks = array('</p>', '</div>', '</h1>', '</h2>', '</h3>', '</table>', '</ul>', '</ol>');
            
            // Initialize
            $chunks = array();
            $offset = 0;
            $content_length = strlen($content);
            
            // Split content into manageable chunks
            while ($offset < $content_length) {
                // Determine where to break
                $break_point = $offset + $chunk_size;
                
                if ($break_point >= $content_length) {
                    // Last chunk
                    $chunks[] = substr($content, $offset);
                    break;
                }
                
                // Find nearest safe breaking point
                $nearest_break = $content_length;
                
                foreach ($safe_breaks as $break_tag) {
                    $pos = strpos($content, $break_tag, $break_point);
                    if ($pos !== false && $pos < $nearest_break) {
                        $nearest_break = $pos + strlen($break_tag);
                    }
                }
                
                if ($nearest_break === $content_length) {
                    // No safe break found, use chunk_size
                    $nearest_break = $break_point;
                }
                
                // Extract chunk and store
                $chunks[] = substr($content, $offset, $nearest_break - $offset);
                $offset = $nearest_break;
            }
            
            // Process each chunk individually
            $processed = array();
            foreach ($chunks as $index => $chunk) {
                if ($this->options['enable_debug']) {
                    $this->logger->log_debug("Processing chunk {$index}: " . strlen($chunk) . " bytes");
                }
                
                // Mark this as a chunk to avoid recursive chunking
                $chunk_type = $content_type . '_chunk';
                $processed[] = $this->clean_content($chunk, $chunk_type, $cleaning_level);
            }
            
            // Use memory efficient string operations
            $result = '';
            foreach ($processed as $chunk) {
                $result .= $chunk;
                // Release the chunk from memory
                unset($chunk);
            }
            
            // Force garbage collection after large operations
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
            
            if ($this->options['enable_debug']) {
                $this->logger->log_debug("Completed processing chunks: " . strlen($result) . " bytes");
            }
            
            return $result;
        } catch (Exception $e) {
            if ($this->options['enable_debug']) {
                $this->logger->log_debug("Error in content chunking: " . $e->getMessage());
            }
            // Provide fallback behavior - return original content
            return $content;
        }
    }

    /**
     * Quick check if content has Word markup
     * 
     * @param string $content Content to check
     * @return bool True if content likely has Word markup
     */
    private function contains_word_markup($content) {
        // Common markers of Word content
        $word_markers = array(
            'mso-',                // MSO styles
            'class="Mso',          // MSO classes
            '<o:p>',               // Office XML tags
            '<!--[if',             // Word conditional comments
            'w:WordDocument',      // Word XML namespace
            'panose-1:',           // Word typography metadata
            'urn:schemas-microsoft-com:',  // Microsoft schemas
            'style="mso-',         // MSO style attributes
            '<m:',                 // Math markup
            '<v:',                 // Vector markup
            'font-family:"Cambria Math"',  // Word equation font
        );
        
        // Check for Word markers
        foreach ($word_markers as $marker) {
            if (strpos($content, $marker) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Process simple text content without complex HTML
     * 
     * @param string $content The content
     * @param array $cleaning_level Cleaning levels
     * @return string Cleaned content
     */
    private function clean_simple_text_content($content, $cleaning_level) {
        // Skip if content doesn't look like it has Word markup
        if (!$this->contains_word_markup($content)) {
            return $content;
        }
        
        // Handle content with minimal HTML
        $patterns = array();
        $replacements = array();
        
        // Add patterns based on cleaning levels
        if (!empty($cleaning_level['xml_namespaces'])) {
            $patterns[] = self::PATTERN_O_TAGS;
            $replacements[] = '';
            
            $patterns[] = self::PATTERN_XML_NAMESPACE;
            $replacements[] = '';
        }
        
        if (!empty($cleaning_level['conditional_comments'])) {
            $patterns[] = self::PATTERN_CONDITIONAL_COMMENTS;
            $replacements[] = '';
            
            $patterns[] = self::PATTERN_CONDITIONAL_TAGS;
            $replacements[] = '';
        }
        
        if (!empty($cleaning_level['mso_classes'])) {
            $patterns[] = self::PATTERN_MSO_CLASSES;
            $replacements[] = '';
        }
        
        if (!empty($cleaning_level['mso_styles'])) {
            $patterns[] = self::PATTERN_MSO_STYLES;
            $replacements[] = '$1';
            
            $patterns[] = self::PATTERN_MSO_STYLES_REMAINING;
            $replacements[] = '';
        }
        
        // Apply patterns
        foreach ($patterns as $i => $pattern) {
            $content = $this->safe_preg_replace($pattern, $replacements[$i], $content, 'simple_text');
        }
        
        return $content;
    }

    /**
     * Determine if content contains complex HTML
     * 
     * @param string $content Content to analyze
     * @return bool True if content has complex HTML
     */
    private function has_complex_html($content) {
        // Skip if empty
        if (empty($content)) {
            return false;
        }
        
        // Check for complex HTML tags
        $complex_tags = array('<table', '<div', '<ul', '<ol', '<h1', '<h2', '<h3');
        
        foreach ($complex_tags as $tag) {
            if (strpos($content, $tag) !== false) {
                return true;
            }
        }
        
        // Count HTML tags - if many, consider it complex
        $tag_count = preg_match_all('/<[^>]+>/', $content, $matches);
        if ($tag_count > 10) {
            return true;
        }
        
        return false;
    }

    /**
     * Get default cleaning levels for a content type
     * 
     * @param string $content_type Content type identifier
     * @return array Default cleaning levels
     */
    private function get_default_cleaning_levels($content_type) {
        // Get content type settings from settings manager
        return $this->settings_manager->get_content_type_settings($content_type);
    }

    /**
     * Clean Microsoft Word markup from content with element-based processing
     * 
     * @param string $content The content to be cleaned
     * @param string $content_type Optional. The type of content (post, acf, etc.)
     * @param array $cleaning_level Optional. Specific cleaning levels to apply
     * @return string The cleaned content
     */
    public function clean_content($content, $content_type = 'post', $cleaning_level = [])
    {
        if (!is_string($content) || empty($content)) {
            return $content;
        }

        // Default cleaning levels if none specified
        if (empty($cleaning_level)) {
            // Get the post type if we're in a filter context
            $current_filter = current_filter();
            $post_type = get_post_type();

            // Determine content type based on current filter and context
            if ($content_type === 'post' && $post_type) {
                // If we got a generic 'post' but have actual post type info, use that
                $content_type = $post_type;
            } elseif ($current_filter === 'content_save_pre') {
                // We're in the main content filter but don't know post type yet
                $content_type = 'wp_content';
            }

            // Get default settings for this content type from settings manager
            $cleaning_level = $this->settings_manager->get_content_type_settings($content_type);

            if ($this->options['enable_debug']) {
                $this->logger->log_debug("Using default settings for {$content_type}");
            }
        }

        // Check if we only need to strip all styles without other Word cleaning
        if (!empty($cleaning_level['strip_all_styles']) && !$this->contains_word_markup($content)) {
            // We only need to strip styles, no Word markup to clean
            if ($this->options['enable_debug']) {
                $this->logger->log_debug("No Word markup detected in {$content_type} content - only stripping styles");
            }

            // Check for escaped quotes and unescape before processing
            $has_escaped_quotes = (strpos($content, '\"') !== false || strpos($content, "\'") !== false);
            $working_content = $has_escaped_quotes ? stripslashes($content) : $content;

            // Strip all style attributes and return
            $stripped_content = $this->safe_preg_replace(self::PATTERN_ALL_STYLE_ATTRS, '', $working_content, 'strip_all_styles');

            // Re-escape quotes if they were escaped originally
            $result = $has_escaped_quotes ? addslashes($stripped_content) : $stripped_content;

            return $result;
        }

        // Early bail-out: Check if content contains Word markup
        if (!$this->contains_word_markup($content)) {
            if ($this->options['enable_debug']) {
                $this->logger->log_debug("No Word markup detected in {$content_type} content - skipping");
            }
            return $content;
        }

        // Create a cache key based on content, settings, and content type
        $cache_key = $this->get_content_cache_key($content, $cleaning_level, $content_type);

        // Check if we have a cached version of this content
        $cached_content = $this->get_from_content_cache($cache_key);
        if ($cached_content !== false) {
            // We have a cache hit!
            if ($this->options['enable_debug']) {
                $this->logger->log_debug("CACHE HIT for {$content_type} - Using cached cleaned content");
            }
            $this->cache_hits++;
            return $cached_content;
        }

        $this->cache_misses++;

        // Check WordPress object cache (persistent across requests)
        if (function_exists('wp_cache_get')) {
            $wp_cached = wp_cache_get($cache_key, 'word_markup_cleaner');
            if ($wp_cached !== false) {
                if ($this->options['enable_debug']) {
                    $this->logger->log_debug("WP OBJECT CACHE HIT for {$content_type}");
                }

                // Also store in local cache for faster access
                $this->add_to_content_cache($cache_key, $wp_cached);

                return $wp_cached;
            }
        }

        // Special case - strip all HTML for excerpts
        if (!empty($cleaning_level['strip_all_html'])) {
            $cleaned = wp_strip_all_tags($content);

            if ($this->options['enable_debug']) {
                $this->logger->log_debug("Stripped all HTML tags from content");
            }

            // Store in cache
            if (function_exists('wp_cache_set')) {
                wp_cache_set($cache_key, $cleaned, 'word_markup_cleaner', 3600);
            }

            return $cleaned;
        }

        // Determine whether to use DOM processing or legacy regex-based processing
        $use_dom = $this->dom_processing_enabled;

        // For simple content types or very small content, use regex-based approach
        if (($content_type === 'acf_text' || $content_type === 'acf_textarea') &&
            !$this->has_complex_html($content)
        ) {
            $use_dom = false;
        }

        // Process the content using the appropriate method
        if ($use_dom) {
            try {
                // Use DOM-based element processing
                $dom_processor = $this->get_dom_processor();
                $cleaned = $dom_processor->process_content($content, $content_type, $cleaning_level);

                $processing_stats = $dom_processor->get_statistics();
                if ($this->options['enable_debug']) {
                    $this->logger->log_debug("DOM Processing used for {$content_type}. Efficiency: {$processing_stats['efficiency']}%");
                }

                // Store in cache
                $this->add_to_content_cache($cache_key, $cleaned);
                if (function_exists('wp_cache_set')) {
                    wp_cache_set($cache_key, $cleaned, 'word_markup_cleaner', 3600);
                }

                return $cleaned;
            } catch (Exception $e) {
                // If DOM processing fails, fall back to legacy processing
                if ($this->options['enable_debug']) {
                    $this->logger->log_debug("DOM Processing failed, falling back to legacy mode: " . $e->getMessage());
                }

                // Fall through to legacy method
                $use_dom = false;
            }
        }

        // Legacy regex-based processing
        if (!$use_dom) {
            $cleaned = $this->legacy_clean_content($content, $content_type, $cleaning_level);

            // Store in cache
            $this->add_to_content_cache($cache_key, $cleaned);
            if (function_exists('wp_cache_set')) {
                wp_cache_set($cache_key, $cleaned, 'word_markup_cleaner', 3600);
            }

            return $cleaned;
        }

        // We should never reach here, but just in case
        return $content;
    }

    /**
     * Legacy method for cleaning content using regex-based approach
     * 
     * @param string $content The content to be cleaned
     * @param string $content_type The type of content
     * @param array $cleaning_level Cleaning levels to apply
     * @param array $tables Pre-extracted tables (optional)
     * @param array $lists Pre-extracted lists (optional)
     * @return string The cleaned content
     */
    public function legacy_clean_content($content, $content_type = 'post', $cleaning_level = [], $tables = [], $lists = [])
    {
        if ($this->options['enable_debug']) {
            $this->logger->log_debug("USING LEGACY CLEANING for {$content_type}");
        }

        // Optimize for simple text content without complex HTML
        if (($content_type === 'acf_text' || $content_type === 'acf_textarea') &&
            !$this->has_complex_html($content)
        ) {
            return $this->clean_simple_text_content($content, $cleaning_level);
        }

        // Process large content in chunks to avoid memory issues
        $max_size = 50000; // 50KB
        if (
            strlen($content) > $max_size &&
            strpos($content_type, '_chunk') === false && // Avoid recursive chunking
            in_array($content_type, array('post', 'page', 'wp_content', 'acf_wysiwyg'))
        ) {
            return $this->process_large_content_in_chunks($content, $content_type, $cleaning_level);
        }

        // Log operation if debug is enabled
        if ($this->options['enable_debug']) {
            $this->logger->log_debug("CLEANING {$content_type} CONTENT");
            $this->logger->log_debug("CLEANING LEVELS: " . json_encode($cleaning_level));
        }

        // Store the original content for comparison
        $original = $content;

        // Check if the content has escaped quotes (common in ACF fields)
        $has_escaped_quotes = (strpos($content, '\"') !== false);
        $working_content = $has_escaped_quotes ? stripslashes($content) : $content;

        if ($this->options['enable_debug'] && $has_escaped_quotes) {
            $this->logger->log_debug("Content has escaped quotes - unescaped for processing");
        }

        // Track if we made any changes
        $modified = false;

        // Step 1: Store tables for protection if enabled and not already extracted
        if (empty($tables) && !empty($cleaning_level['protect_tables']) && strpos($working_content, '<table') !== false) {
            // Extract tables into the array and replace with markers
            preg_match_all(self::PATTERN_TABLES, $working_content, $matches);

            foreach ($matches[0] as $index => $table) {
                $marker = "TABLE_MARKER_" . $index;
                $tables[$marker] = $table;
                $working_content = str_replace($table, $marker, $working_content);

                if ($this->options['enable_debug']) {
                    $this->logger->log_debug("Protected table #$index");
                }
            }
        }

        // Step 2: Store lists for protection if enabled and not already extracted
        if (
            empty($lists) && !empty($cleaning_level['protect_lists']) &&
            (strpos($working_content, '<ul') !== false ||
                strpos($working_content, '<ol') !== false ||
                strpos($working_content, 'MsoListParagraph') !== false)
        ) {

            // Extract list patterns into the array and replace with markers
            // First detect ol/ul lists
            preg_match_all(self::PATTERN_LISTS, $working_content, $list_matches);

            foreach ($list_matches[0] as $index => $list) {
                $marker = "LIST_MARKER_" . $index;
                $lists[$marker] = $list;
                $working_content = str_replace($list, $marker, $working_content);

                if ($this->options['enable_debug']) {
                    $this->logger->log_debug("Protected list #$index");
                }
            }

            // Also detect consecutive li items with MsoListParagraph classes
            if (strpos($working_content, 'MsoListParagraph') !== false) {
                preg_match_all(self::PATTERN_MSO_LISTS, $working_content, $mso_list_matches);

                foreach ($mso_list_matches[0] as $index => $mso_list) {
                    $marker = "MSOLIST_MARKER_" . ($index + count($lists));
                    $lists[$marker] = $mso_list;
                    $working_content = str_replace($mso_list, $marker, $working_content);

                    if ($this->options['enable_debug']) {
                        $this->logger->log_debug("Protected MSO list #" . ($index + count($lists)));
                    }
                }
            }
        }

        // Step 3: Clean general Word markup based on cleaning levels
        $old = $working_content;

        // Remove Word XML namespaces and tags
        if (!empty($cleaning_level['xml_namespaces'])) {
            $working_content = $this->safe_preg_replace(self::PATTERN_O_TAGS, '', $working_content, 'o_tags');
            $working_content = $this->safe_preg_replace(self::PATTERN_XML_NAMESPACE, '', $working_content, 'xml_namespace');
        }

        // Remove Word conditional comments
        if (!empty($cleaning_level['conditional_comments'])) {
            $working_content = $this->safe_preg_replace(self::PATTERN_CONDITIONAL_COMMENTS, '', $working_content, 'conditional_comments');
            $working_content = $this->safe_preg_replace(self::PATTERN_CONDITIONAL_TAGS, '', $working_content, 'conditional_tags');
        }

        // Remove class="MsoX" or class="msoX" but keep other classes
        if (!empty($cleaning_level['mso_classes'])) {
            $working_content = $this->safe_preg_replace(self::PATTERN_MSO_CLASSES, '', $working_content, 'mso_classes');
        }

        // Handle all potential direct font/style attributes
        if (!empty($cleaning_level['mso_styles'])) {
            $working_content = $this->safe_preg_replace(self::PATTERN_MSO_STYLES, '$1', $working_content, 'mso_styles');
            $working_content = $this->safe_preg_replace(self::PATTERN_MSO_STYLES_REMAINING, '', $working_content, 'mso_styles_remaining');
        }

        // Clean font attributes if enabled
        if (!empty($cleaning_level['font_attributes'])) {
            $working_content = $this->safe_preg_replace(self::PATTERN_FONT_FAMILY_STYLE, '$1$2$5$6', $working_content, 'font_family_style');
            $working_content = $this->safe_preg_replace(self::PATTERN_FONT_FAMILY, '$1', $working_content, 'font_family');
            $working_content = $this->safe_preg_replace(self::PATTERN_FONT_SIZE, '$1', $working_content, 'font_size');
            $working_content = $this->safe_preg_replace(self::PATTERN_LINE_HEIGHT, '$1', $working_content, 'line_height');

            $working_content = $this->safe_preg_replace(self::PATTERN_TAG_FONT_FAMILY, '<$1', $working_content, 'tag_font_family');
            $working_content = $this->safe_preg_replace(self::PATTERN_TAG_FONT_SIZE, '<$1', $working_content, 'tag_font_size');
            $working_content = $this->safe_preg_replace(self::PATTERN_TAG_FONT_WEIGHT, '<$1', $working_content, 'tag_font_weight');
            $working_content = $this->safe_preg_replace(self::PATTERN_TAG_FONT_STYLE, '<$1', $working_content, 'tag_font_style');

            $working_content = $this->safe_preg_replace(self::PATTERN_TAG_TRAILING, '$1>', $working_content, 'tag_trailing');
        }

        // Clean style attributes if enabled
        if (!empty($cleaning_level['style_attributes'])) {
            $working_content = $this->safe_preg_replace(self::PATTERN_STYLE_ATTRS, '<$1$2$3>', $working_content, 'style_attrs');
            $working_content = $this->safe_preg_replace(self::PATTERN_EMPTY_STYLE, '', $working_content, 'empty_style');
        }

        // Clean lang attributes if enabled
        if (!empty($cleaning_level['lang_attributes'])) {
            $working_content = $this->safe_preg_replace(self::PATTERN_LANG_ATTRS, '<$1$2$3>', $working_content, 'lang_attrs');
        }

        // Remove mso-* attributes from all tags
        if (!empty($cleaning_level['mso_styles'])) {
            $working_content = $this->safe_preg_replace(self::PATTERN_MSO_ATTRS, '<$1$2>', $working_content, 'mso_attrs');
        }

        // Strip all styles if enabled
        if (!empty($cleaning_level['strip_all_styles'])) {
            $original_content = $working_content;
            $working_content = $this->safe_preg_replace(self::PATTERN_ALL_STYLE_ATTRS, '', $working_content, 'strip_all_styles');

            if ($this->options['enable_debug']) {
                $this->logger->log_debug("Stripped all style attributes from content");
                $this->logger->log_debug("Original length: " . strlen($original_content) . ", Stripped length: " . strlen($working_content));
            }
        }

        // Clean empty elements if enabled
        if (!empty($cleaning_level['empty_elements'])) {
            $working_content = $this->safe_preg_replace(self::PATTERN_EMPTY_SPAN, '', $working_content, 'empty_span');
            $working_content = $this->safe_preg_replace(self::PATTERN_EMPTY_SPAN_ATTR, '', $working_content, 'empty_span_attr');
            $working_content = $this->safe_preg_replace(self::PATTERN_PT_TAGS, '$1', $working_content, 'pt_tags');
            $working_content = $this->safe_preg_replace(self::PATTERN_PT_REMNANTS, '', $working_content, 'pt_remnants');
            $working_content = $this->safe_preg_replace(self::PATTERN_EMPTY_CLASS, '', $working_content, 'empty_class');
        }

        if ($old !== $working_content) {
            $modified = true;
            if ($this->options['enable_debug']) {
                $this->logger->log_debug("Cleaned Word markup from content");
            }
        }

        // Step 4: Clean each stored table individually if enabled
        if (!empty($cleaning_level['protect_tables']) && !empty($tables)) {
            foreach ($tables as $marker => $table) {
                // Process the table to remove MSO-specific attributes while preserving structure
                $cleaned_table = $this->clean_table($table, $cleaning_level);

                // Store cleaned tables back in the array
                $tables[$marker] = $cleaned_table;
            }
        }

        // Step 5: Clean each stored list individually if enabled
        if (!empty($cleaning_level['protect_lists']) && !empty($lists)) {
            foreach ($lists as $marker => $list) {
                // Process the list
                $cleaned_list = $this->clean_list($list, $marker, $cleaning_level);
                $lists[$marker] = $cleaned_list;
            }
        }

        // Step 6: Restore tables and lists to content
        if (!empty($cleaning_level['protect_tables']) && !empty($tables)) {
            foreach ($tables as $marker => $cleaned_table) {
                $working_content = str_replace($marker, $cleaned_table, $working_content);
            }
        }

        if (!empty($cleaning_level['protect_lists']) && !empty($lists)) {
            foreach ($lists as $marker => $cleaned_list) {
                $working_content = str_replace($marker, $cleaned_list, $working_content);
            }
        }

        // Step 7: Fix common tag structure issues
        if ($content_type === 'post' || $content_type === 'acf_wysiwyg') {
            // Ensure that tables aren't wrapped in p tags
            $working_content = $this->safe_preg_replace(self::PATTERN_TABLE_P_OPEN, '$1', $working_content, 'table_p_open');
            $working_content = $this->safe_preg_replace(self::PATTERN_TABLE_P_CLOSE, '$1', $working_content, 'table_p_close');

            // Fix malformed nested p/span in tables
            $working_content = $this->safe_preg_replace(self::PATTERN_P_SPAN, '<p>$1</p>', $working_content, 'p_span');

            // Fix table structure - remove improper nesting
            $working_content = $this->safe_preg_replace(self::PATTERN_NESTED_P, '<p $1>', $working_content, 'nested_p');
            $working_content = $this->safe_preg_replace(self::PATTERN_DOUBLE_CLOSE_P, '</p>', $working_content, 'double_close_p');

            // Remove any inline styles in tags if style cleaning is enabled
            if (!empty($cleaning_level['style_attributes'])) {
                $working_content = $this->safe_preg_replace(self::PATTERN_TEXT_INDENT, '$1', $working_content, 'text_indent');
                $working_content = $this->safe_preg_replace(self::PATTERN_MARGIN, '$1', $working_content, 'margin');
                $working_content = $this->safe_preg_replace(self::PATTERN_LINE_HEIGHT_INLINE, '$1', $working_content, 'line_height_inline');
                $working_content = $this->safe_preg_replace(self::PATTERN_FONT_INLINE, '$1', $working_content, 'font_inline');
            }

            // Remove mso-specific inline styles if MSO cleaning is enabled
            if (!empty($cleaning_level['mso_styles'])) {
                $working_content = $this->safe_preg_replace(self::PATTERN_MSO_LIST_INLINE, '$1', $working_content, 'mso_list_inline');
                $working_content = $this->safe_preg_replace(self::PATTERN_MSO_INLINE, '$1', $working_content, 'mso_inline');
            }

            // Final cleanup of any remaining style attributes
            if (!empty($cleaning_level['style_attributes'])) {
                $working_content = $this->safe_preg_replace(self::PATTERN_STYLE_DOUBLE_QUOTE, '<$1$2>', $working_content, 'style_double_quote');
                $working_content = $this->safe_preg_replace(self::PATTERN_STYLE_SINGLE_QUOTE, '<$1$2>', $working_content, 'style_single_quote');
            }

            // SPECIFIC FIX: Remove the trailing '>' in list items
            $working_content = $this->safe_preg_replace(self::PATTERN_LI_TRAILING, '<li>', $working_content, 'li_trailing');
            $working_content = $this->safe_preg_replace(self::PATTERN_LI_TRAILING_SPACE, '<li>', $working_content, 'li_trailing_space');

            // Fix any unclosed tags using WordPress' native function
            if (function_exists('balanceTags')) {
                $working_content = balanceTags($working_content, true);
                if ($this->options['enable_debug']) {
                    $this->logger->log_debug("Balanced tags using WordPress balanceTags function");
                }
            }
        }

        // Final content - don't automatically re-escape quotes
        $final_content = $working_content;

        // Compare and log the changes
        if ($modified || $final_content !== $original) {
            if ($this->options['enable_debug']) {
                $this->log_detailed_changes($original, $final_content, strtoupper($content_type) . ' CONTENT');
            }

            try {
                return $final_content;
            } catch (Exception $e) {
                if ($this->options['enable_debug']) {
                    $this->logger->log_debug("Error returning final content: " . $e->getMessage());
                }
                return $original; // Fallback to original on error
            }
        } else {
            if ($this->options['enable_debug']) {
                $this->logger->log_debug("NO CHANGES MADE TO CONTENT");
            }
            return $original;
        }

        // Force garbage collection after processing large content
        if (strlen($content) > 500000 && function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }

    // [Other methods remain unchanged - maintain clean_table, clean_list, etc.]

    /**
     * Set max cache entries for content cache
     *
     * @param int $max_entries Maximum number of entries
     */
    public function set_max_cache_entries($max_entries)
    {
        $this->max_cache_entries = max(10, min(1000, (int) $max_entries));
    }

    /**
     * Enable or disable DOM processing
     *
     * @param bool $enabled Whether DOM processing should be enabled
     */
    public function set_dom_processing_enabled($enabled)
    {
        $this->dom_processing_enabled = $this->settings_manager->get_option('use_dom_processing', true) && class_exists('DOMDocument');

        if ($this->options['enable_debug']) {
            $this->logger->log_debug("DOM processing enabled setting: " . var_export($this->settings_manager->get_option('use_dom_processing', true), true));
        }
        
    }

    /**
     * Check if DOM processing is enabled and available
     *
     * @return bool Whether DOM processing is enabled
     */
    public function is_dom_processing_enabled()
    {
        return $this->dom_processing_enabled;
    }

    /**
     * Lazy-load the DOM processor when needed
     *
     * @return WP_Word_Markup_Cleaner_DOM_Processor DOM processor instance
     */
    private function get_dom_processor()
    {
        if ($this->dom_processor === null) {
            $this->dom_processor = new WP_Word_Markup_Cleaner_DOM_Processor($this->logger, $this);

            if ($this->options['enable_debug']) {
                $this->logger->log_debug("DOM Processor initialized");
            }
        }

        return $this->dom_processor;
    }
        
    /**
     * Helper method to clean tables
     * 
     * @param string $table The table HTML
     * @param array $cleaning_level Cleaning levels
     * @return string The cleaned table
     */
    public function clean_table($table, $cleaning_level) {
        // Use static cache to avoid reprocessing identical tables
        static $table_cache = array();
        
        // Generate cache key
        $cache_key = md5($table . serialize($cleaning_level));
        
        // Check cache
        if (isset($table_cache[$cache_key])) {
            return $table_cache[$cache_key];
        }
        
        // First extract just the table structure and rebuild it
        preg_match(self::PATTERN_TABLE_STRUCTURE, $table, $table_parts);
        
        // Get table attributes but clean them
        preg_match(self::PATTERN_TABLE_ATTRS, $table, $table_attr_match);
        $table_attrs = isset($table_attr_match[1]) ? $table_attr_match[1] : '';
        
        // Clean table attributes - keep only essential ones
        $table_attrs = $this->safe_preg_replace(self::PATTERN_STYLE_ATTR, '', $table_attrs, 'table_attrs_style');
        $table_attrs = $this->safe_preg_replace(self::PATTERN_TABLE_CLASS, '', $table_attrs, 'table_attrs_class');
        $table_attrs = $this->safe_preg_replace(self::PATTERN_TABLE_MSO, '', $table_attrs, 'table_attrs_mso');
        
        // Keep only border, cellspacing, cellpadding
        if (preg_match(self::PATTERN_BORDER, $table_attrs, $border_match)) {
            $border = $border_match[1];
        } else {
            $border = '1';
        }
        
        if (preg_match(self::PATTERN_CELLSPACING, $table_attrs, $cellspacing_match)) {
            $cellspacing = $cellspacing_match[1];
        } else {
            $cellspacing = '0';
        }
        
        if (preg_match(self::PATTERN_CELLPADDING, $table_attrs, $cellpadding_match)) {
            $cellpadding = $cellpadding_match[1];
        } else {
            $cellpadding = '0';
        }
        
        // Start creating the cleaned table
        $cleaned_table = "<table border=\"$border\" cellspacing=\"$cellspacing\" cellpadding=\"$cellpadding\">\n";
        
        // Extract all rows
        preg_match_all(self::PATTERN_TABLE_ROWS, isset($table_parts[1]) ? $table_parts[1] : $table, $rows_matches);
        
        if (!empty($rows_matches[0])) {
            foreach ($rows_matches[0] as $row) {
                // Clean the row
                $row = $this->safe_preg_replace(self::PATTERN_STYLE_ATTR, '', $row, 'row_style');
                $row = $this->safe_preg_replace(self::PATTERN_TABLE_CLASS, '', $row, 'row_class');
                $row = $this->safe_preg_replace(self::PATTERN_TABLE_MSO, '', $row, 'row_mso');
                
                // Extract the tds
                preg_match_all(self::PATTERN_TABLE_CELLS, $row, $cells_matches);
                
                $cleaned_row = "<tr>\n";
                
                foreach ($cells_matches[0] as $cell) {
                    // Get cell attributes but keep only width and valign
                    preg_match(self::PATTERN_CELL_CONTENT, $cell, $cell_parts);
                    $cell_attrs = isset($cell_parts[1]) ? $cell_parts[1] : '';
                    $cell_content = isset($cell_parts[2]) ? $cell_parts[2] : '';
                    
                    // Clean cell attributes
                    $cell_attrs = $this->safe_preg_replace(self::PATTERN_STYLE_ATTR, '', $cell_attrs, 'cell_style');
                    $cell_attrs = $this->safe_preg_replace(self::PATTERN_TABLE_CLASS, '', $cell_attrs, 'cell_class');
                    $cell_attrs = $this->safe_preg_replace(self::PATTERN_CELL_MSO, '', $cell_attrs, 'cell_mso');
                    
                    // Preserve width and valign attributes
                    $width = '';
                    $valign = '';
                    
                    if (preg_match(self::PATTERN_WIDTH, $cell_attrs, $width_match)) {
                        $width = " width=\"{$width_match[1]}\"";
                    }
                    
                    if (preg_match(self::PATTERN_VALIGN, $cell_attrs, $valign_match)) {
                        $valign = " valign=\"{$valign_match[1]}\"";
                    }
                    
                    // Clean the content inside the cell
                    $cell_content = $this->safe_preg_replace(self::PATTERN_CELL_P, '<p>', $cell_content, 'cell_p');
                    $cell_content = $this->safe_preg_replace(self::PATTERN_CELL_SPAN, '<span>', $cell_content, 'cell_span');
                    
                    // Clean nested p and span tags
                    $cell_content = $this->safe_preg_replace(self::PATTERN_P_SPAN, '<p>$1</p>', $cell_content, 'cell_p_span');
                    $cell_content = $this->safe_preg_replace(self::PATTERN_NESTED_P_SIMPLE, '<p>', $cell_content, 'cell_nested_p');
                    $cell_content = $this->safe_preg_replace(self::PATTERN_DOUBLE_CLOSE_P, '</p>', $cell_content, 'cell_double_p');
                    
                    // Fix any broken nested tags
                    if (function_exists('balanceTags')) {
                        $cell_content = balanceTags($cell_content, true);
                    }
                    
                    $cleaned_row .= "  <td{$width}{$valign}>\n  {$cell_content}\n  </td>\n";
                }
                
                $cleaned_row .= "</tr>\n";
                $cleaned_table .= $cleaned_row;
            }
        }
        
        $cleaned_table .= "</table>";
        
        // Cache the result
        $table_cache[$cache_key] = $cleaned_table;
        
        return $cleaned_table;
    }

    /**
     * Helper method to clean lists
     * 
     * @param string $list The list HTML
     * @param string $marker The marker ID
     * @param array $cleaning_level Cleaning levels
     * @return string The cleaned list
     */
    public function clean_list($list, $marker, $cleaning_level) {
        // Use static cache to avoid reprocessing identical lists
        static $list_cache = array();
        
        // Generate cache key
        $cache_key = md5($list . $marker . serialize($cleaning_level));
        
        // Check cache
        if (isset($list_cache[$cache_key])) {
            return $list_cache[$cache_key];
        }
        
        // Convert Word lists to proper HTML lists if they're using Word's special format
        if (strpos($marker, 'MSOLIST_MARKER') === 0) {
            // Convert MSO list paragraphs to proper list items
            $list_items = preg_split(self::PATTERN_LIST_SPLIT, $list);
            
            // Clean up each item
            foreach ($list_items as $key => $item) {
                // Remove Word's list markers and paragraph tags
                $item = $this->safe_preg_replace(self::PATTERN_LIST_MARKERS, '', $item, 'msolist_markers');
                $item = $this->safe_preg_replace(self::PATTERN_LIST_PARAGRAPH, '', $item, 'msolist_paragraph');
                $item = $this->safe_preg_replace(self::PATTERN_CLOSE_P, '', $item, 'msolist_close_p');
                
                // Store back
                $list_items[$key] = trim($item);
            }
            
            // Convert to a proper HTML list
            $cleaned_list = "<ul>\n";
            foreach ($list_items as $item) {
                if (!empty($item)) {
                    $cleaned_list .= "  <li>" . $item . "</li>\n";
                }
            }
            $cleaned_list .= "</ul>";
        } else {
            // For regular HTML lists, just clean the Word attributes
            $cleaned_list = $this->safe_preg_replace(self::PATTERN_MSO_CLASSES, '', $list, 'list_mso_classes');
            
            // Remove all style attributes from list items
            $cleaned_list = $this->safe_preg_replace(self::PATTERN_LIST_STYLE, '<$1$2$3>', $cleaned_list, 'list_style');
            
            // Remove empty style attributes
            $cleaned_list = $this->safe_preg_replace(self::PATTERN_EMPTY_STYLE, '', $cleaned_list, 'list_empty_style');
            $cleaned_list = $this->safe_preg_replace(self::PATTERN_EMPTY_CLASS, '', $cleaned_list, 'list_empty_class');
            
            // Remove Word's list markers from list items
            $cleaned_list = $this->safe_preg_replace(self::PATTERN_LIST_MARKERS, '', $cleaned_list, 'list_markers');
            
            // Fix any direct embedded style attributes in li tags
            $cleaned_list = $this->safe_preg_replace(self::PATTERN_LI_STYLE_DOUBLE, '$1', $cleaned_list, 'li_style_double');
            $cleaned_list = $this->safe_preg_replace(self::PATTERN_LI_STYLE_SINGLE, '$1', $cleaned_list, 'li_style_single');
            $cleaned_list = $this->safe_preg_replace(self::PATTERN_LI_TEXT_INDENT, '$1', $cleaned_list, 'li_text_indent');
            $cleaned_list = $this->safe_preg_replace(self::PATTERN_LI_MSO_LIST, '$1', $cleaned_list, 'li_mso_list');
            
            // Make sure we remove ALL attributes from li tags
            $cleaned_list = $this->safe_preg_replace(self::PATTERN_LI_ALL_ATTRS, '<li>', $cleaned_list, 'li_all_attrs');
            
            // Ensure clean list structure
            $cleaned_list = $this->safe_preg_replace(self::PATTERN_LIST_STRUCTURE, '<$1>', $cleaned_list, 'list_structure');
            
            // Fix any trailing '>' characters in list items
            $cleaned_list = $this->safe_preg_replace(self::PATTERN_LI_TRAILING, '<li>', $cleaned_list, 'li_trailing');
            $cleaned_list = $this->safe_preg_replace(self::PATTERN_LI_TRAILING_SPACE, '<li>', $cleaned_list, 'li_trailing_space');
        }
        
        // Cache the result
        $list_cache[$cache_key] = $cleaned_list;
        
        return $cleaned_list;
    }
    
    /**
     * Enhanced logging function to show detailed changes
     * 
     * @param string $original The original content
     * @param string $cleaned The cleaned content
     * @param string $context The context of the cleaning (e.g., "table", "list", "content")
     * @return bool Whether logging was successful
     */
    private function log_detailed_changes($original, $cleaned, $context = 'content') {
        if (!$this->options['enable_debug']) {
            return false;
        }
        
        // Truncate very large content for logging purposes
        $max_log_length = 2000; // Maximum characters to log for before/after samples
        
        $this->logger->log_debug("=== DETAILED CHANGES FOR $context ===");
        
        // Log the basic statistics
        $orig_length = strlen($original);
        $clean_length = strlen($cleaned);
        $diff = $orig_length - $clean_length;
        $percent = $orig_length > 0 ? round(($diff / $orig_length) * 100, 2) : 0;
        
        $this->logger->log_debug("ORIGINAL LENGTH: $orig_length chars");
        $this->logger->log_debug("CLEANED LENGTH: $clean_length chars");
        $this->logger->log_debug("REMOVED: $diff chars ($percent% reduction)");
        
        // Define common Word markup patterns to analyze
        $patterns = [
            'mso-style-attributes' => self::PATTERN_MSO_STYLE_ATTR,
            'class-mso-attributes' => self::PATTERN_CLASS_MSO_ATTR,
            'word-xml-tags' => self::PATTERN_WORD_XML_TAGS,
            'word-conditionals' => self::PATTERN_WORD_CONDITIONALS,
            'style-attributes' => self::PATTERN_STYLE_ATTR,
            'font-attributes' => self::PATTERN_FONT_ATTR
        ];
        
        // Count and log occurrences of each pattern in original content
        $this->log_pattern_analysis($original, $patterns);
        
        // For content not too large, analyze line-by-line differences
        if ($orig_length < 10000 && $clean_length < 10000) {
            $this->log_line_differences($original, $cleaned);
        } else {
            // For large content, just log sample before/after
            $this->logger->log_debug("BEFORE SAMPLE (truncated):");
            $this->logger->log_debug(substr($original, 0, $max_log_length) . 
                (strlen($original) > $max_log_length ? "..." : ""));
            $this->logger->log_debug("AFTER SAMPLE (truncated):");
            $this->logger->log_debug(substr($cleaned, 0, $max_log_length) . 
                (strlen($cleaned) > $max_log_length ? "..." : ""));
        }
        
        $this->logger->log_debug("=== END DETAILED CHANGES ===");
        return true;
    }

    /**
     * Analyze and log occurrences of specific patterns in content
     * 
     * @param string $content The content to analyze
     * @param array $patterns Array of patterns to search for
     * @return int Total number of patterns found
     */
    private function log_pattern_analysis($content, $patterns) {
        $pattern_counts = [];
        $total_patterns = 0;
        
        foreach ($patterns as $name => $pattern) {
            $count = preg_match_all($pattern, $content, $matches);
            $pattern_counts[$name] = $count;
            $total_patterns += $count;
            
            if ($count > 0) {
                // Log examples of each pattern found (up to 3)
                $this->logger->log_debug("FOUND $count $name:");
                $examples = array_slice($matches[0], 0, 3);
                foreach ($examples as $index => $example) {
                    $this->logger->log_debug("  Example " . ($index + 1) . ": " . trim($example));
                }
            }
        }
        
        $this->logger->log_debug("TOTAL WORD MARKUP PATTERNS FOUND: $total_patterns");
        return $total_patterns;
    }

    /**
     * Analyze and log differences between lines in two strings
     * 
     * @param string $original The original content
     * @param string $cleaned The cleaned content
     * @return int Number of different lines found
     */
    private function log_line_differences($original, $cleaned) {
        // Find differences between lines
        $orig_lines = explode("\n", $original);
        $clean_lines = explode("\n", $cleaned);
        
        $total_lines = count($orig_lines);
        $different_lines = 0;
        
        // Compare line by line (up to first 100 lines)
        $max_lines_to_check = min(100, $total_lines);
        $examples_shown = 0;
        $max_examples = 5; // Maximum number of examples to show
        
        for ($i = 0; $i < $max_lines_to_check; $i++) {
            if (isset($orig_lines[$i]) && isset($clean_lines[$i]) && $orig_lines[$i] !== $clean_lines[$i]) {
                $different_lines++;
                
                // Show a few examples of specific line changes
                if ($examples_shown < $max_examples && strlen($orig_lines[$i]) > 0) {
                    $examples_shown++;
                    $this->logger->log_debug("CHANGED LINE #$i:");
                    $this->logger->log_debug("  BEFORE: " . substr($orig_lines[$i], 0, 200) . 
                        (strlen($orig_lines[$i]) > 200 ? "..." : ""));
                    $this->logger->log_debug("  AFTER:  " . substr($clean_lines[$i], 0, 200) . 
                        (strlen($clean_lines[$i]) > 200 ? "..." : ""));
                }
            }
        }
        
        $this->logger->log_debug("CHANGED LINES: $different_lines of $total_lines lines examined");
        return $different_lines;
    }
    
    /**
     * Set an option for the content cleaner
     * 
     * @param string $key The option key
     * @param mixed $value The option value
     */
    public function set_option($key, $value) {
        $this->options[$key] = $value;
        
        // Update setting in settings manager
        $this->settings_manager->update_option($key, $value);
    }
    
    /**
     * Get an option from the content cleaner
     * 
     * @param string $key The option key
     * @param mixed $default Default value if option doesn't exist
     * @return mixed The option value or default
     */
    public function get_option($key, $default = false) {
        // First check local options
        if (isset($this->options[$key])) {
            return $this->options[$key];
        }
        
        // Then try to get from settings manager
        return $this->settings_manager->get_option($key, $default);
    }
    
    /**
     * Update multiple options at once
     * 
     * @param array $options Array of options to update
     */
    public function update_options($options) {
        $this->options = wp_parse_args($options, $this->options);
        
        // Update settings in settings manager
        $this->settings_manager->update_options($options);
    }

    /**
     * Generate a cache key for content
     *
     * @param string $content The content to clean
     * @param array $cleaning_level Cleaning settings to apply
     * @param string $content_type Content type identifier
     * @return string Cache key
     */
    private function get_content_cache_key($content, $cleaning_level, $content_type) {
        // Create a unique hash based on:
        // 1. The first 50 chars of the content (to detect duplicate content)
        // 2. The overall content length (to differentiate between similar starts)
        // 3. A hash of the full content (for complete uniqueness)
        // 4. The cleaning levels as a serialized string
        // 5. The content type
        // 6. The cache version (to invalidate when plugin is updated)
        
        $content_prefix = substr($content, 0, 50);
        $content_length = strlen($content);
        $content_hash = md5($content);
        $settings_hash = md5(serialize($cleaning_level));
        
        // Combine all factors into a single key
        $key = md5("{$content_prefix}_{$content_length}_{$content_hash}_{$settings_hash}_{$content_type}_{$this->cache_version}");

        if ($this->options['enable_debug']) {
            $this->logger->log_debug("CACHE KEY GENERATED FOR: {$content_type}");
            $this->logger->log_debug("Content hash: {$content_hash}");
            $this->logger->log_debug("Key: {$key}");
        }
        
        return "wpmsc_content_{$key}";
    }

    /**
     * Add content to the memory cache
     *
     * @param string $key Cache key
     * @param string $content Cleaned content
     * @return bool Success
     */
    private function add_to_content_cache($key, $content) {
        if (!$this->cache_enabled) {
            return false;
        }
        
        // Check if we need to trim the cache
        if (count($this->content_cache) >= $this->max_cache_entries) {
            $this->trim_content_cache();
        }
        
        // Add to cache with timestamp
        $this->content_cache[$key] = array(
            'content' => $content,
            'time' => time()
        );
        
        return true;
    }

    /**
     * Get content from memory cache
     *
     * @param string $key Cache key
     * @return string|false Cached content or false if not found
     */
    private function get_from_content_cache($key) {
        if (!$this->cache_enabled || !isset($this->content_cache[$key])) {
            return false;
        }
        
        // Return the cached content
        return $this->content_cache[$key]['content'];
    }

    /**
     * Trim the content cache when it gets too large
     * Removes the oldest 20% of entries
     */
    private function trim_content_cache() {
        if (empty($this->content_cache)) {
            return;
        }
        
        // Sort by timestamp (oldest first)
        uasort($this->content_cache, function($a, $b) {
            return $a['time'] - $b['time'];
        });
        
        // Calculate how many to remove (20% of max)
        $remove_count = (int) ($this->max_cache_entries * 0.2);
        if ($remove_count < 1) {
            $remove_count = 1;
        }
        
        // Remove oldest entries
        $i = 0;
        foreach ($this->content_cache as $key => $data) {
            unset($this->content_cache[$key]);
            $i++;
            if ($i >= $remove_count) {
                break;
            }
        }
        
        if ($this->options['enable_debug']) {
            $this->logger->log_debug("Content cache trimmed: removed {$remove_count} oldest entries");
        }
    }

    /**
     * Completely clear the content cache
     */
    public function clear_content_cache() {
        $cache_size = count($this->content_cache);
        $this->content_cache = array();
        
        // Also clear WordPress object cache for this plugin
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('word_markup_cleaner');
        }
        
        if ($this->options['enable_debug']) {
            $this->logger->log_debug("Content cache cleared: {$cache_size} entries removed");
        }
        
        return true;
    }

    /**
     * Scheduled cache cleanup
     */
    public function cleanup_cache() {
        // Remove entries older than 24 hours
        $expire_time = time() - 86400;
        $removed = 0;
        
        foreach ($this->content_cache as $key => $data) {
            if ($data['time'] < $expire_time) {
                unset($this->content_cache[$key]);
                $removed++;
            }
        }
        
        if ($this->options['enable_debug']) {
            $this->logger->log_debug("Scheduled cache cleanup: removed {$removed} expired entries");
        }
    }

    /**
     * Get cache statistics
     *
     * @return array Cache statistics
     */
    public function get_cache_stats() {
        return array(
            'enabled' => $this->cache_enabled,
            'max_entries' => $this->max_cache_entries,
            'current_entries' => count($this->content_cache),
            'hits' => $this->cache_hits,
            'misses' => $this->cache_misses,
            'hit_rate' => ($this->cache_hits + $this->cache_misses > 0) 
                ? round(($this->cache_hits / ($this->cache_hits + $this->cache_misses)) * 100, 2) 
                : 0,
            'version' => $this->cache_version
        );
    }

    /**
     * Enable or disable content caching
     *
     * @param bool $enabled Whether caching should be enabled
     */
    public function set_cache_enabled($enabled) {
        $this->cache_enabled = (bool) $enabled;
        
        if ($this->options['enable_debug']) {
            $this->logger->log_debug("Content cache " . ($this->cache_enabled ? "enabled" : "disabled"));
        }
    }
}