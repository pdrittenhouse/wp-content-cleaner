<?php

/**
 * Enhanced DOM-based content processing utility for the Word Markup Cleaner plugin
 *
 * @package WordPress_Word_Markup_Cleaner
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class WP_Word_Markup_Cleaner_DOM_Processor
 * 
 * Provides DOM-based processing for Word markup cleaning
 */
class WP_Word_Markup_Cleaner_DOM_Processor
{

    /**
     * Logger instance
     *
     * @var WP_Word_Markup_Cleaner_Logger
     */
    private $logger;

    /**
     * Content cleaner instance
     *
     * @var WP_Word_Markup_Cleaner_Content
     */
    private $cleaner;

    /**
     * Debug enabled flag
     *
     * @var bool
     */
    private $debug_enabled = false;

    /**
     * Count of elements processed
     *
     * @var int
     */
    private $elements_processed = 0;

    /**
     * Count of elements cleaned
     *
     * @var int
     */
    private $elements_cleaned = 0;

    /**
     * Count of elements skipped (no Word markup)
     *
     * @var int
     */
    private $elements_skipped = 0;

    /**
     * Cleaning levels configuration
     *
     * @var array
     */
    private $cleaning_level = array();

    /**
     * Content type being processed
     *
     * @var string
     */
    private $content_type = 'post';

    /**
     * Processing start time
     *
     * @var float
     */
    private $start_time = 0;

    /**
     * Detailed statistics for Word markup patterns
     *
     * @var array
     */
    private $pattern_statistics = array();

    /**
     * Node types map for debugging
     * 
     * @var array
     */
    private $node_type_map = array(
        XML_ELEMENT_NODE => 'Element',
        XML_ATTRIBUTE_NODE => 'Attribute',
        XML_TEXT_NODE => 'Text',
        XML_CDATA_SECTION_NODE => 'CDATA',
        XML_ENTITY_REF_NODE => 'Entity Ref',
        XML_ENTITY_NODE => 'Entity',
        XML_PI_NODE => 'Processing Instruction',
        XML_COMMENT_NODE => 'Comment',
        XML_DOCUMENT_NODE => 'Document',
        XML_DOCUMENT_TYPE_NODE => 'Document Type',
        XML_DOCUMENT_FRAG_NODE => 'Document Fragment',
        XML_NOTATION_NODE => 'Notation',
        XML_HTML_DOCUMENT_NODE => 'HTML Document',
        XML_DTD_NODE => 'DTD',
        XML_ELEMENT_DECL_NODE => 'Element Declaration',
        XML_ATTRIBUTE_DECL_NODE => 'Attribute Declaration',
        XML_ENTITY_DECL_NODE => 'Entity Declaration',
        XML_NAMESPACE_DECL_NODE => 'Namespace Declaration'
    );

    /**
     * Initialize the DOM processor
     *
     * @param WP_Word_Markup_Cleaner_Logger $logger Logger instance
     * @param WP_Word_Markup_Cleaner_Content $cleaner Content cleaner instance
     */
    public function __construct($logger, $cleaner)
    {
        $this->logger = $logger;
        $this->cleaner = $cleaner;

        // Check if debug is enabled
        $options = get_option('wp_word_cleaner_options', array());
        $this->debug_enabled = !empty($options['enable_debug']);

        // Initialize pattern statistics
        $this->init_pattern_statistics();

        if ($this->debug_enabled) {
            $this->logger->log_debug("DOM Processor initialized with debug enabled");
        }
    }

    /**
     * Initialize pattern statistics trackers
     */
    private function init_pattern_statistics()
    {
        $this->pattern_statistics = array(
            'mso_classes' => 0,
            'mso_styles' => 0,
            'xml_namespaces' => 0,
            'conditional_comments' => 0,
            'font_attributes' => 0,
            'style_attributes' => 0,
            'lang_attributes' => 0,
            'empty_elements' => 0
        );
    }

    /**
     * Process content using DOM-based approach
     * 
     * @param string $content The content to process
     * @param string $content_type Content type identifier
     * @param array $cleaning_level Cleaning levels configuration
     * @return string Processed content
     */
    public function process_content($content, $content_type = 'post', $cleaning_level = array())
    {
        if (empty($content)) {
            return $content;
        }

        // Reset counters and statistics
        $this->elements_processed = 0;
        $this->elements_cleaned = 0;
        $this->elements_skipped = 0;
        $this->init_pattern_statistics();
        $this->start_time = microtime(true);

        // Store parameters
        $this->content_type = $content_type;
        $this->cleaning_level = $cleaning_level;

        // Initial logs
        if ($this->debug_enabled) {
            $this->logger->log_debug("DOM: Starting processing for {$content_type} content");
            $this->logger->log_debug("DOM: Input content size: " . strlen($content) . " bytes");

            // Log cleaning levels being applied
            $active_cleaners = array();
            foreach ($cleaning_level as $cleaner => $enabled) {
                if ($enabled) {
                    $active_cleaners[] = $cleaner;
                }
            }
            $this->logger->log_debug("DOM: Active cleaning options: " . implode(', ', $active_cleaners));

            // Log first 100 chars of content for debugging
            $content_preview = substr($content, 0, 100);
            $this->logger->log_debug("DOM: Content preview: " . $content_preview . (strlen($content) > 100 ? "..." : ""));
        }

        // First extract tables and lists for special processing
        $extracted_elements = $this->extract_special_elements($content);

        // Initialize DOM document with utf-8 encoding
        $dom = $this->create_dom_document($extracted_elements['content']);

        if (!$dom) {
            // If DOM creation fails, fall back to legacy processing
            if ($this->debug_enabled) {
                $this->logger->log_debug("DOM: Creation failed, falling back to legacy processing for {$content_type}");
            }

            // Pass extracted elements info to the content cleaner
            return $this->cleaner->legacy_clean_content(
                $content,
                $content_type,
                $cleaning_level,
                $extracted_elements['tables'],
                $extracted_elements['lists']
            );
        }

        if ($this->debug_enabled) {
            $this->logger->log_debug("DOM: Document successfully created");

            // Log document structure overview
            $structure_overview = $this->get_dom_structure_overview($dom);
            $this->logger->log_debug("DOM: Document structure: " . $structure_overview);
        }

        // Process the DOM tree
        $this->process_node($dom->documentElement);

        // Get processed content
        $processed_content = $this->get_dom_html($dom);

        if ($this->debug_enabled) {
            $this->logger->log_debug("DOM: Raw processed content size: " . strlen($processed_content) . " bytes");

            // Calculate processing time
            $process_time = microtime(true) - $this->start_time;
            $this->logger->log_debug(sprintf("DOM: Processing time: %.4f seconds", $process_time));
        }

        // Restore tables and lists
        $final_content = $this->restore_special_elements(
            $processed_content,
            $extracted_elements['tables'],
            $extracted_elements['lists']
        );

        if ($this->debug_enabled) {
            $this->logger->log_debug("DOM: Final content size after restoring special elements: " . strlen($final_content) . " bytes");
            $this->logger->log_debug("DOM: Size difference: " . (strlen($content) - strlen($final_content)) . " bytes");

            // Log detailed pattern statistics
            $this->logger->log_debug("DOM: Pattern removal statistics:");
            foreach ($this->pattern_statistics as $pattern => $count) {
                if ($count > 0) {
                    $this->logger->log_debug("DOM:   - {$pattern}: {$count} instances removed");
                }
            }
        }

        // Log processing statistics
        if ($this->debug_enabled) {
            $this->logger->log_debug("DOM Processing Statistics for {$content_type}:");
            $this->logger->log_debug("  Elements processed: {$this->elements_processed}");
            $this->logger->log_debug("  Elements cleaned: {$this->elements_cleaned}");
            $this->logger->log_debug("  Elements skipped: {$this->elements_skipped}");

            // Calculate efficiency
            $efficiency = ($this->elements_processed > 0)
                ? round(($this->elements_skipped / $this->elements_processed) * 100, 2)
                : 0;

            $this->logger->log_debug("  Efficiency rate: {$efficiency}% (higher is better)");
            $this->logger->log_debug("DOM: Processing complete for {$content_type}");
        }

        return $final_content;
    }

    /**
     * Create a DOM document from HTML content
     * 
     * @param string $content HTML content
     * @return DOMDocument|false DOM document or false on failure
     */
    private function create_dom_document($content)
    {
        if (empty($content)) {
            return false;
        }

        // Try to create a DOM document
        try {
            // Create new DOM document
            $dom = new DOMDocument('1.0', 'UTF-8');

            // Prevent XML parsing errors from being displayed
            libxml_use_internal_errors(true);

            // Check for escaped quotes and unescape before processing
            $has_escaped_quotes = (strpos($content, '\"') !== false || strpos($content, "\'") !== false);
            if ($has_escaped_quotes) {
                if ($this->debug_enabled) {
                    $this->logger->log_debug("DOM: Content has escaped quotes - unescaping for DOM processing");
                }
                $content = stripslashes($content);
            }

            // If the content doesn't have HTML structure, wrap it
            if (strpos($content, '<html') === false) {
                $content = '<html><body>' . $content . '</body></html>';
            }

            // Set flags for loadHTML
            $flags = LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD;

            // Handle UTF-8 correctly
            $content = htmlspecialchars_decode(htmlentities($content, ENT_QUOTES, 'UTF-8', false));

            // Load the HTML
            $success = $dom->loadHTML($content, $flags);

            // Get any errors
            $errors = libxml_get_errors();
            libxml_clear_errors();

            if (!$success || count($errors) > 10) {
                // Too many errors, might not be worth processing with DOM
                if ($this->debug_enabled) {
                    $this->logger->log_debug("DOM: Loading encountered " . count($errors) . " errors");

                    // Log first 5 errors for debugging
                    $i = 0;
                    foreach ($errors as $error) {
                        if ($i++ < 5) {
                            $this->logger->log_debug("DOM: Error " . $i . ": " . trim($error->message) .
                                " at line " . $error->line . ", column " . $error->column);
                        }
                    }

                    if (count($errors) > 5) {
                        $this->logger->log_debug("DOM: " . (count($errors) - 5) . " more errors not shown");
                    }
                }
                return false;
            }

            return $dom;
        } catch (Exception $e) {
            if ($this->debug_enabled) {
                $this->logger->log_debug("DOM: Creation exception: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Get HTML from DOM document
     * 
     * @param DOMDocument $dom DOM document
     * @return string HTML content
     */
    private function get_dom_html($dom)
    {
        if (!$dom) {
            return '';
        }

        try {
            // Save the document as HTML
            $html = $dom->saveHTML();

            // Remove the DOCTYPE and html/body tags that DOM adds
            $html = preg_replace('/^<!DOCTYPE.+?>/', '', $html);
            $html = preg_replace('/<html><body>/', '', $html);
            $html = preg_replace('/<\/body><\/html>$/', '', $html);

            return $html;
        } catch (Exception $e) {
            if ($this->debug_enabled) {
                $this->logger->log_debug("DOM: HTML extraction exception: " . $e->getMessage());
            }
            return '';
        }
    }

    /**
     * Extract tables and lists for special processing
     * 
     * @param string $content HTML content
     * @return array Extracted elements and modified content
     */
    private function extract_special_elements($content)
    {
        $result = array(
            'content' => $content,
            'tables' => array(),
            'lists' => array()
        );

        // Skip extraction if content doesn't have tables or lists
        if (
            strpos($content, '<table') === false &&
            strpos($content, '<ul') === false &&
            strpos($content, '<ol') === false &&
            strpos($content, 'MsoListParagraph') === false
        ) {
            return $result;
        }

        // Extract tables if enabled
        if (!empty($this->cleaning_level['protect_tables']) && strpos($content, '<table') !== false) {
            preg_match_all(WP_Word_Markup_Cleaner_Content::PATTERN_TABLES, $content, $matches);

            foreach ($matches[0] as $index => $table) {
                $marker = "TABLE_MARKER_" . $index;
                $result['tables'][$marker] = $table;
                $result['content'] = str_replace($table, $marker, $result['content']);

                if ($this->debug_enabled) {
                    $table_size = strlen($table);
                    $this->logger->log_debug("DOM: Protected table #{$index} ({$table_size} bytes)");
                }
            }
        }

        // Extract lists if enabled
        if (
            !empty($this->cleaning_level['protect_lists']) &&
            (strpos($content, '<ul') !== false ||
                strpos($content, '<ol') !== false ||
                strpos($content, 'MsoListParagraph') !== false)
        ) {

            // Extract standard lists
            preg_match_all(WP_Word_Markup_Cleaner_Content::PATTERN_LISTS, $result['content'], $list_matches);

            foreach ($list_matches[0] as $index => $list) {
                $marker = "LIST_MARKER_" . $index;
                $result['lists'][$marker] = $list;
                $result['content'] = str_replace($list, $marker, $result['content']);

                if ($this->debug_enabled) {
                    $list_size = strlen($list);
                    $list_items = substr_count($list, '<li');
                    $this->logger->log_debug("DOM: Protected list #{$index} with {$list_items} items ({$list_size} bytes)");
                }
            }

            // Extract Word-specific lists
            if (strpos($result['content'], 'MsoListParagraph') !== false) {
                preg_match_all(WP_Word_Markup_Cleaner_Content::PATTERN_MSO_LISTS, $result['content'], $mso_list_matches);

                foreach ($mso_list_matches[0] as $index => $mso_list) {
                    $marker = "MSOLIST_MARKER_" . ($index + count($result['lists']));
                    $result['lists'][$marker] = $mso_list;
                    $result['content'] = str_replace($mso_list, $marker, $result['content']);

                    if ($this->debug_enabled) {
                        $mso_list_size = strlen($mso_list);
                        $paragraphs = substr_count($mso_list, '<p');
                        $this->logger->log_debug("DOM: Protected MSO list #{$index} with {$paragraphs} paragraphs ({$mso_list_size} bytes)");
                    }
                }
            }
        }

        if ($this->debug_enabled) {
            $this->logger->log_debug("DOM: Extracted " . count($result['tables']) . " tables and " . count($result['lists']) . " lists");
        }

        return $result;
    }

    /**
     * Restore tables and lists after processing
     * 
     * @param string $content Processed content
     * @param array $tables Extracted tables
     * @param array $lists Extracted lists
     * @return string Content with tables and lists restored
     */
    private function restore_special_elements($content, $tables, $lists)
    {
        // First process each table and list separately using the cleaner's methods
        if (!empty($this->cleaning_level['protect_tables']) && !empty($tables)) {
            foreach ($tables as $marker => $table) {
                // Clean the table content with the specialized method
                $cleaned_table = $this->cleaner->clean_table($table, $this->cleaning_level);
                $tables[$marker] = $cleaned_table;

                if ($this->debug_enabled) {
                    $original_size = strlen($table);
                    $cleaned_size = strlen($cleaned_table);
                    $diff = $original_size - $cleaned_size;
                    $percentage = ($original_size > 0) ? round(($diff / $original_size) * 100, 2) : 0;

                    $this->logger->log_debug("DOM: Table {$marker} cleaned - reduced by {$diff} bytes ({$percentage}%)");
                }
            }
        }

        if (!empty($this->cleaning_level['protect_lists']) && !empty($lists)) {
            foreach ($lists as $marker => $list) {
                // Process the list with the specialized method
                $cleaned_list = $this->cleaner->clean_list($list, $marker, $this->cleaning_level);
                $lists[$marker] = $cleaned_list;

                if ($this->debug_enabled) {
                    $original_size = strlen($list);
                    $cleaned_size = strlen($cleaned_list);
                    $diff = $original_size - $cleaned_size;
                    $percentage = ($original_size > 0) ? round(($diff / $original_size) * 100, 2) : 0;

                    $this->logger->log_debug("DOM: List {$marker} cleaned - reduced by {$diff} bytes ({$percentage}%)");
                }
            }
        }

        // Restore cleaned special elements to the content
        if (!empty($tables)) {
            foreach ($tables as $marker => $cleaned_table) {
                $content = str_replace($marker, $cleaned_table, $content);
            }
        }

        if (!empty($lists)) {
            foreach ($lists as $marker => $cleaned_list) {
                $content = str_replace($marker, $cleaned_list, $content);
            }
        }

        return $content;
    }

    /**
     * Process a DOM node and its children
     * 
     * @param DOMNode $node The node to process
     * @param int $depth Current depth in the DOM tree (for logging)
     */
    private function process_node($node, $depth = 0)
    {
        if (!$node) {
            return;
        }

        // Skip comments and special nodes
        if ($node->nodeType !== XML_ELEMENT_NODE && $node->nodeType !== XML_TEXT_NODE) {
            if ($this->debug_enabled && $depth <= 2) {
                $node_type = isset($this->node_type_map[$node->nodeType]) ?
                    $this->node_type_map[$node->nodeType] : "Unknown ({$node->nodeType})";
                $this->logger->log_debug("DOM: Skipping " . $node_type . " node at depth {$depth}");
            }
            return;
        }

        // Process text nodes
        if ($node->nodeType === XML_TEXT_NODE) {
            $this->elements_processed++;

            $text_content = $node->nodeValue;
            $text_length = strlen($text_content);

            // Skip text nodes without Word markup
            if (!$this->contains_word_markup($text_content)) {
                $this->elements_skipped++;

                if ($this->debug_enabled && $depth <= 2 && $text_length > 10) {
                    $this->logger->log_debug("DOM: Skipped text node at depth {$depth} ({$text_length} bytes)");
                }
                return;
            }

            // Clean the text node content
            $original_text = $node->nodeValue;
            $cleaned_text = $this->clean_text_content($original_text);

            if ($original_text !== $cleaned_text) {
                $node->nodeValue = $cleaned_text;
                $this->elements_cleaned++;

                if ($this->debug_enabled && $depth <= 3) {
                    $orig_len = strlen($original_text);
                    $cleaned_len = strlen($cleaned_text);
                    $preview = (strlen($original_text) > 30) ? substr($original_text, 0, 30) . "..." : $original_text;
                    $this->logger->log_debug("DOM: Cleaned text node at depth {$depth} - '{$preview}' ({$orig_len} → {$cleaned_len} bytes)");
                }
            }
            return;
        }

        // Process element nodes
        $this->elements_processed++;

        $tagName = $node->nodeName;
        $hasChildren = $node->hasChildNodes();
        $childCount = $hasChildren ? $node->childNodes->length : 0;

        if ($this->debug_enabled && $depth <= 2) {
            $this->logger->log_debug("DOM: Processing <{$tagName}> element at depth {$depth} with {$childCount} children");

            // Log attributes for important elements
            if ($node->hasAttributes() && $depth <= 2) {
                $attrs = array();
                foreach ($node->attributes as $attr) {
                    if (strlen($attr->value) < 50) {
                        $attrs[] = $attr->name . '="' . $attr->value . '"';
                    } else {
                        $attrs[] = $attr->name . '="' . substr($attr->value, 0, 50) . '..."';
                    }
                }
                if (!empty($attrs)) {
                    $this->logger->log_debug("DOM: <{$tagName}> attributes: " . implode(', ', $attrs));
                }
            }
        }

        $element_html = $this->get_node_html($node);

        // Skip elements without Word markup
        if (!$this->contains_word_markup($element_html)) {
            $this->elements_skipped++;

            if ($this->debug_enabled && $depth <= 2) {
                $this->logger->log_debug("DOM: No Word markup in <{$tagName}> at depth {$depth}, skipping direct processing");
            }

            // Still process children even if the parent node doesn't have Word markup
            if ($node->hasChildNodes()) {
                foreach ($node->childNodes as $child) {
                    $this->process_node($child, $depth + 1);
                }
            }
            return;
        }

        // Element has Word markup - clean attributes
        $attr_before = $this->count_element_attributes($node);
        $this->clean_element_attributes($node);
        $attr_after = $this->count_element_attributes($node);

        if ($attr_before != $attr_after && $this->debug_enabled && $depth <= 3) {
            $this->logger->log_debug("DOM: Cleaned <{$tagName}> attributes at depth {$depth} ({$attr_before} → {$attr_after})");
        }

        $this->elements_cleaned++;

        // Process children recursively
        if ($node->hasChildNodes()) {
            // Create a snapshot of child nodes as they may change during processing
            $children = array();
            foreach ($node->childNodes as $child) {
                $children[] = $child;
            }

            foreach ($children as $child) {
                $this->process_node($child, $depth + 1);
            }
        }
    }

    /**
     * Get HTML representation of a DOM node
     * 
     * @param DOMNode $node The node
     * @return string Node HTML
     */
    private function get_node_html($node)
    {
        if (!$node) {
            return '';
        }

        try {
            return $node->ownerDocument->saveHTML($node);
        } catch (Exception $e) {
            if ($this->debug_enabled) {
                $this->logger->log_debug("DOM: Node HTML extraction exception: " . $e->getMessage());
            }
            return '';
        }
    }

    /**
     * Check if content has Word markup
     * 
     * @param string $content Content to check
     * @return bool True if content likely has Word markup
     */
    private function contains_word_markup($content)
    {
        // Common markers of Word content
        $word_markers = array(
            'mso-',
            'Mso',
            '<o:p>',
            '<!--[if',
            'urn:schemas-microsoft-com:',
            'panose-1:',
            'w:WordDocument',
            'font-family:"Cambria Math"',
            '<m:',
            '<v:'
        );

        foreach ($word_markers as $marker) {
            if (strpos($content, $marker) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clean text content
     * 
     * @param string $content Text content
     * @return string Cleaned text content
     */
    private function clean_text_content($content)
    {
        // For plain text nodes, we only clean Word-specific patterns
        // that might appear in text content
        $patterns = array();
        $replacements = array();

        // Add patterns based on cleaning levels
        if (!empty($this->cleaning_level['xml_namespaces'])) {
            $patterns[] = WP_Word_Markup_Cleaner_Content::PATTERN_O_TAGS;
            $replacements[] = '';

            if (preg_match(WP_Word_Markup_Cleaner_Content::PATTERN_O_TAGS, $content)) {
                $this->pattern_statistics['xml_namespaces']++;
            }
        }

        if (!empty($this->cleaning_level['conditional_comments'])) {
            $patterns[] = WP_Word_Markup_Cleaner_Content::PATTERN_CONDITIONAL_COMMENTS;
            $replacements[] = '';

            $patterns[] = WP_Word_Markup_Cleaner_Content::PATTERN_CONDITIONAL_TAGS;
            $replacements[] = '';

            if (
                preg_match(WP_Word_Markup_Cleaner_Content::PATTERN_CONDITIONAL_COMMENTS, $content) ||
                preg_match(WP_Word_Markup_Cleaner_Content::PATTERN_CONDITIONAL_TAGS, $content)
            ) {
                $this->pattern_statistics['conditional_comments']++;
            }
        }

        // Apply patterns
        foreach ($patterns as $i => $pattern) {
            $content = $this->safe_preg_replace($pattern, $replacements[$i], $content, 'text_node');
        }

        return $content;
    }

    /**
     * Clean element attributes
     * 
     * @param DOMElement $element The element to clean
     */
    private function clean_element_attributes($element)
    {
        if (!$element || !($element instanceof DOMElement)) {
            return;
        }

        // Get all attributes
        $attributes = array();
        if ($element->hasAttributes()) {
            foreach ($element->attributes as $attr) {
                $attributes[$attr->name] = $attr->value;
            }
        }

        // Process attributes based on cleaning levels
        $modified_attributes = $attributes;

        // Clean MSO classes
        if (!empty($this->cleaning_level['mso_classes']) && isset($modified_attributes['class'])) {
            $class_value = $modified_attributes['class'];
            if (preg_match('/\b(Mso|mso)[A-Za-z0-9]+\b/', $class_value)) {
                // Remove MSO-specific classes while preserving others
                $classes = explode(' ', $class_value);
                $filtered_classes = array();
                $removed_classes = 0;

                foreach ($classes as $class) {
                    if (!preg_match('/^(Mso|mso)[A-Za-z0-9]+$/', $class)) {
                        $filtered_classes[] = $class;
                    } else {
                        $removed_classes++;
                    }
                }

                $modified_attributes['class'] = implode(' ', $filtered_classes);

                if ($this->debug_enabled && $removed_classes > 0) {
                    $this->logger->log_debug("DOM: Removed {$removed_classes} MSO classes from <{$element->nodeName}>");
                    $this->pattern_statistics['mso_classes'] += $removed_classes;
                }

                // Remove class attribute if it's empty
                if (empty($modified_attributes['class'])) {
                    unset($modified_attributes['class']);
                }
            }
        }

        // Clean style attributes
        if (!empty($this->cleaning_level['style_attributes']) && isset($modified_attributes['style'])) {
            $style_value = $modified_attributes['style'];
            $original_style = $style_value;

            // Clean MSO styles first if enabled
            if (!empty($this->cleaning_level['mso_styles'])) {
                $mso_count = preg_match_all('/\s*mso-[^:]+:[^;]+;?/i', $style_value);
                $style_value = preg_replace('/\s*mso-[^:]+:[^;]+;?/i', '', $style_value);

                if ($mso_count > 0) {
                    $this->pattern_statistics['mso_styles'] += $mso_count;
                }
            }

            // Clean font attributes if enabled
            if (!empty($this->cleaning_level['font_attributes'])) {
                $font_count = 0;
                $font_count += preg_match_all('/\s*font-family:[^;]+;?/i', $style_value);
                $font_count += preg_match_all('/\s*font-size:[^;]+;?/i', $style_value);
                $font_count += preg_match_all('/\s*font-weight:[^;]+;?/i', $style_value);
                $font_count += preg_match_all('/\s*font-style:[^;]+;?/i', $style_value);
                $font_count += preg_match_all('/\s*line-height:[^;]+;?/i', $style_value);

                $style_value = preg_replace('/\s*font-family:[^;]+;?/i', '', $style_value);
                $style_value = preg_replace('/\s*font-size:[^;]+;?/i', '', $style_value);
                $style_value = preg_replace('/\s*font-weight:[^;]+;?/i', '', $style_value);
                $style_value = preg_replace('/\s*font-style:[^;]+;?/i', '', $style_value);
                $style_value = preg_replace('/\s*line-height:[^;]+;?/i', '', $style_value);

                if ($font_count > 0) {
                    $this->pattern_statistics['font_attributes'] += $font_count;
                }
            }

            // Strip all styles if enabled
            if (!empty($this->cleaning_level['strip_all_styles'])) {
                $style_value = '';
                $this->pattern_statistics['style_attributes']++;
            }

            // Update the style attribute
            $modified_attributes['style'] = trim($style_value);

            // Log style changes if significant
            if ($this->debug_enabled && $original_style !== $modified_attributes['style']) {
                $orig_len = strlen($original_style);
                $new_len = strlen($modified_attributes['style']);
                if ($orig_len > 20 || $new_len === 0) {
                    $this->logger->log_debug("DOM: Cleaned style in <{$element->nodeName}> from {$orig_len} to {$new_len} bytes");
                }
            }

            // Remove style attribute if it's empty
            if (empty($modified_attributes['style'])) {
                unset($modified_attributes['style']);
                $this->pattern_statistics['style_attributes']++;
            }
        }

        // Clean language attributes if enabled
        if (!empty($this->cleaning_level['lang_attributes']) && isset($modified_attributes['lang'])) {
            unset($modified_attributes['lang']);
            $this->pattern_statistics['lang_attributes']++;

            if ($this->debug_enabled) {
                $this->logger->log_debug("DOM: Removed lang attribute from <{$element->nodeName}>");
            }
        }

        // Apply changes to the element
        foreach ($attributes as $name => $value) {
            // Remove attribute if it's been unset
            if (!isset($modified_attributes[$name])) {
                $element->removeAttribute($name);
            }
            // Update attribute if it's changed
            elseif ($modified_attributes[$name] !== $value) {
                $element->setAttribute($name, $modified_attributes[$name]);
            }
        }
    }

    /**
     * Count attributes in an element
     * 
     * @param DOMElement $element The element
     * @return int Number of attributes
     */
    private function count_element_attributes($element)
    {
        if (!$element || !($element instanceof DOMElement) || !$element->hasAttributes()) {
            return 0;
        }

        return $element->attributes->length;
    }

    /**
     * Get a simplified structure overview of the DOM document
     * 
     * @param DOMDocument $dom The DOM document
     * @return string A simplified structure representation
     */
    private function get_dom_structure_overview($dom)
    {
        if (!$dom || !$dom->documentElement) {
            return "Empty document";
        }

        $output = array();
        $this->build_structure_overview($dom->documentElement, $output);

        // Limit the size of the output
        if (count($output) > 10) {
            $first_5 = array_slice($output, 0, 5);
            $last_5 = array_slice($output, -5);
            return implode(', ', $first_5) . " ... (" . (count($output) - 10) . " more) ... " . implode(', ', $last_5);
        }

        return implode(', ', $output);
    }

    /**
     * Recursively build a structure overview
     * 
     * @param DOMNode $node The node to process
     * @param array &$output The output array
     * @param int $depth Current depth
     * @param int $max_depth Maximum depth to process
     */
    private function build_structure_overview($node, &$output, $depth = 0, $max_depth = 3)
    {
        if ($depth > $max_depth) {
            return;
        }

        if ($node->nodeType === XML_ELEMENT_NODE) {
            $tag = $node->nodeName;
            $class = $node->getAttribute('class');
            $id = $node->getAttribute('id');

            $desc = $tag;
            if ($id) {
                $desc .= "#" . $id;
            }
            if ($class) {
                $desc .= "." . str_replace(' ', '.', $class);
            }

            if ($node->hasChildNodes()) {
                $desc .= " (" . $node->childNodes->length . ")";
            }

            $output[] = $desc;

            // Only process first few children to avoid overwhelming log
            $childCount = 0;
            if ($depth < $max_depth) {
                foreach ($node->childNodes as $child) {
                    if ($child->nodeType === XML_ELEMENT_NODE) {
                        $this->build_structure_overview($child, $output, $depth + 1, $max_depth);
                        $childCount++;

                        // Limit the number of children processed
                        if ($childCount >= 3 && $node->childNodes->length > 3) {
                            $output[] = "...(" . ($node->childNodes->length - $childCount) . " more)";
                            break;
                        }
                    }
                }
            }
        }
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
    private function safe_preg_replace($pattern, $replacement, $subject, $context = 'unknown')
    {
        if (empty($subject)) {
            return $subject;
        }

        try {
            $result = preg_replace($pattern, $replacement, $subject);

            if ($result === null) {
                if ($this->debug_enabled) {
                    $error = preg_last_error();
                    $error_message = $this->get_preg_error_message($error);
                    $this->logger->log_debug("DOM REGEX ERROR in {$context}: {$error_message} (Code: {$error})");
                }
                return $subject;
            }

            return $result;
        } catch (Exception $e) {
            if ($this->debug_enabled) {
                $this->logger->log_debug("DOM Exception in {$context}: " . $e->getMessage());
            }
            return $subject;
        }
    }

    /**
     * Get human-readable preg error message
     * 
     * @param int $error_code Error code from preg_last_error()
     * @return string Error message
     */
    private function get_preg_error_message($error_code)
    {
        $errors = array(
            PREG_NO_ERROR => 'No error',
            PREG_INTERNAL_ERROR => 'Internal PCRE error',
            PREG_BACKTRACK_LIMIT_ERROR => 'Backtrack limit exhausted',
            PREG_RECURSION_LIMIT_ERROR => 'Recursion limit exhausted',
            PREG_BAD_UTF8_ERROR => 'Malformed UTF-8 data',
            PREG_BAD_UTF8_OFFSET_ERROR => 'Bad UTF-8 offset'
        );

        if (defined('PREG_JIT_STACKLIMIT_ERROR')) {
            $errors[PREG_JIT_STACKLIMIT_ERROR] = 'JIT stack limit exhausted';
        }

        return isset($errors[$error_code]) ? $errors[$error_code] : 'Unknown error';
    }

    /**
     * Get processing statistics
     * 
     * @return array Processing statistics
     */
    public function get_statistics()
    {
        $efficiency = ($this->elements_processed > 0)
            ? round(($this->elements_skipped / $this->elements_processed) * 100, 2)
            : 0;

        return array(
            'elements_processed' => $this->elements_processed,
            'elements_cleaned' => $this->elements_cleaned,
            'elements_skipped' => $this->elements_skipped,
            'efficiency' => $efficiency,
            'pattern_statistics' => $this->pattern_statistics,
            'processing_time' => microtime(true) - $this->start_time
        );
    }
}
