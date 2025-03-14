<?php
/**
 * DOM-based HTML Processing functionality for the Word Markup Cleaner plugin
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
 * Handles advanced DOM-based cleaning of Word markup
 */
class WP_Word_Markup_Cleaner_DOM_Processor {
    
    /**
     * Logger instance
     *
     * @var WP_Word_Markup_Cleaner_Logger
     */
    private $logger;
    
    /**
     * Settings manager instance
     *
     * @var WP_Word_Markup_Cleaner_Settings_Manager
     */
    private $settings_manager;
    
    /**
     * Initialize the DOM processor
     *
     * @param WP_Word_Markup_Cleaner_Logger $logger Logger instance
     * @param WP_Word_Markup_Cleaner_Settings_Manager $settings_manager Settings manager instance
     */
    public function __construct($logger, $settings_manager) {
        $this->logger = $logger;
        $this->settings_manager = $settings_manager;
        
        if ($this->settings_manager->get_option('enable_debug', false)) {
            $this->logger->log_debug("DOM Processor initialized");
        }
    }
    
    /**
     * Process HTML content using DOM methods
     *
     * @param string $content The HTML content to clean
     * @param array $cleaning_level The cleaning level settings
     * @return string The cleaned content
     */
    public function clean_content_dom($content, $cleaning_level = array()) {
        if (empty($content)) {
            return $content;
        }
        
        // Only process if content has HTML
        if (!preg_match('/<[^>]+>/', $content)) {
            return $content;
        }
        
        // Check if we need to clean anything
        $requires_cleaning = $this->requires_dom_cleaning($content);
        
        if (!$requires_cleaning) {
            if ($this->settings_manager->get_option('enable_debug', false)) {
                $this->logger->log_debug("DOM Processor: Content doesn't require cleaning");
            }
            return $content;
        }
        
        if ($this->settings_manager->get_option('enable_debug', false)) {
            $this->logger->log_debug("DOM Processor: Starting DOM-based cleaning");
        }
        
        // Load HTML into DOMDocument
        $dom = new DOMDocument('1.0', 'UTF-8');
        
        // Preserve entities that might be in the content
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        
        // Don't pre-process content with entity encoding
        $content = stripslashes($content);
        
        // Track errors during loading
        $errors = libxml_use_internal_errors(true);
        
        // Wrap content in a div to preserve HTML structure
        $success = @$dom->loadHTML('<div>' . $content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        // Handle errors loading HTML
        if (!$success) {
            $xml_errors = libxml_get_errors();
            libxml_clear_errors();
            libxml_use_internal_errors($errors);
            
            if ($this->settings_manager->get_option('enable_debug', false)) {
                $this->logger->log_debug("DOM Processor: Failed to load HTML - " . count($xml_errors) . " errors");
                foreach ($xml_errors as $error) {
                    $this->logger->log_debug("XML Error: " . $error->message);
                }
            }
            
            // Fallback to regex cleaning
            return $content;
        }
        
        // Create a DOMXPath object for easier navigation
        $xpath = new DOMXPath($dom);
        
        // Apply cleaning operations based on settings
        if (!empty($cleaning_level['xml_namespaces'])) {
            $this->clean_word_xml_elements($xpath);
        }
        
        if (!empty($cleaning_level['mso_classes'])) {
            $this->clean_elements_with_mso_classes($xpath);
        }
        
        if (!empty($cleaning_level['mso_styles'])) {
            $this->clean_elements_with_mso_styles($xpath);
        }
        
        if (!empty($cleaning_level['font_attributes'])) {
            $this->clean_font_attributes($xpath);
        }
        
        if (!empty($cleaning_level['style_attributes']) && !empty($cleaning_level['strip_all_styles'])) {
            $this->remove_all_style_attributes($xpath);
        } else if (!empty($cleaning_level['style_attributes'])) {
            $this->clean_style_attributes($xpath);
        }
        
        if (!empty($cleaning_level['lang_attributes'])) {
            $this->clean_lang_attributes($xpath);
        }
        
        if (!empty($cleaning_level['empty_elements'])) {
            $this->remove_empty_elements($xpath);
        }
        
        // Handle tables separately if needed
        if (!empty($cleaning_level['protect_tables'])) {
            $this->clean_tables($xpath);
        }
        
        // Handle lists separately if needed
        if (!empty($cleaning_level['protect_lists'])) {
            $this->clean_lists($xpath);
        }
        
        // Get the cleaned content back
        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body) {
            if ($this->settings_manager->get_option('enable_debug', false)) {
                $this->logger->log_debug("DOM Processor: Failed to find body element");
            }
            libxml_use_internal_errors($errors);
            return $content;
        }
        
        $div = $body->getElementsByTagName('div')->item(0);
        if (!$div) {
            if ($this->settings_manager->get_option('enable_debug', false)) {
                $this->logger->log_debug("DOM Processor: Failed to find wrapper div");
            }
            libxml_use_internal_errors($errors);
            return $content;
        }
        
        // Get all child nodes of the wrapping div
        $cleaned_content = '';
        foreach ($div->childNodes as $node) {
            $cleaned_content .= $dom->saveHTML($node);
        }
        
        // Restore error handling
        libxml_use_internal_errors($errors);
        
        if ($this->settings_manager->get_option('enable_debug', false)) {
            $this->logger->log_debug("DOM Processor: DOM-based cleaning completed");
        }

        // Fix double encoding issues
        $cleaned_content = html_entity_decode($cleaned_content, ENT_QUOTES, 'UTF-8');
        // Fix potential backslash escaping
        $cleaned_content = stripslashes($cleaned_content);
        
        return $cleaned_content;
    }
    
    /**
     * Check if content requires DOM-based cleaning
     *
     * @param string $content The content to check
     * @return bool Whether the content requires DOM-based cleaning
     */
    private function requires_dom_cleaning($content) {
        // Check for common Word markers
        $word_markers = array(
            'mso-',                // MSO styles
            'class="Mso',          // MSO classes
            '<o:p>',               // Office XML tags
            '<!--[if',             // Word conditional comments
            'w:WordDocument',      // Word XML namespace
            'panose-1:',           // Word typography metadata
        );
        
        foreach ($word_markers as $marker) {
            if (strpos($content, $marker) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Clean elements with MSO classes
     *
     * @param DOMXPath $xpath The XPath object
     */
    private function clean_elements_with_mso_classes($xpath) {
        // Find elements with MSO classes
        $elements = $xpath->query('//*[contains(@class, "Mso") or contains(@class, "mso")]');
        
        if ($elements === false || $elements->length === 0) {
            return;
        }
        
        if ($this->settings_manager->get_option('enable_debug', false)) {
            $this->logger->log_debug("DOM Processor: Cleaning " . $elements->length . " elements with MSO classes");
        }
        
        foreach ($elements as $element) {
            // Get current class
            $class = $element->getAttribute('class');
            
            // Remove MSO classes
            $cleaned_class = preg_replace('/\s*\b(Mso|mso)[^\s]*/i', '', $class);
            
            // Set cleaned class or remove attribute if empty
            if (trim($cleaned_class) !== '') {
                $element->setAttribute('class', trim($cleaned_class));
            } else {
                $element->removeAttribute('class');
            }
        }
    }
    
    /**
     * Clean elements with MSO styles
     *
     * @param DOMXPath $xpath The XPath object
     */
    private function clean_elements_with_mso_styles($xpath) {
        // Find elements with style attributes
        $elements = $xpath->query('//*[@style]');
        
        if ($elements === false || $elements->length === 0) {
            return;
        }
        
        $cleaned_count = 0;
        
        foreach ($elements as $element) {
            // Get current style
            $style = $element->getAttribute('style');
            
            // Check if it has MSO styles
            if (strpos($style, 'mso-') !== false) {
                // Remove MSO styles
                $cleaned_style = preg_replace('/\s*mso-[^:;]+:[^;]+;?/i', '', $style);
                
                // Set cleaned style or remove attribute if empty
                if (trim($cleaned_style) !== '') {
                    $element->setAttribute('style', trim($cleaned_style));
                } else {
                    $element->removeAttribute('style');
                }
                
                $cleaned_count++;
            }
        }
        
        if ($this->settings_manager->get_option('enable_debug', false) && $cleaned_count > 0) {
            $this->logger->log_debug("DOM Processor: Cleaned MSO styles from $cleaned_count elements");
        }
    }
    
    /**
     * Clean Word XML namespace elements
     *
     * @param DOMXPath $xpath The XPath object
     */
    private function clean_word_xml_elements($xpath) {
        // Find Word XML namespace elements
        $elements = $xpath->query('//o:p | //w:* | //m:* | //v:*');
        
        if ($elements === false || $elements->length === 0) {
            return;
        }
        
        if ($this->settings_manager->get_option('enable_debug', false)) {
            $this->logger->log_debug("DOM Processor: Cleaning " . $elements->length . " Word XML namespace elements");
        }
        
        // We need to collect the elements first because removing them changes the NodeList
        $elements_to_remove = array();
        
        if ($elements !== false) {
            foreach ($elements as $element) {
                $elements_to_remove[] = $element;
            }
        }
        
        foreach ($elements_to_remove as $element) {
            // Get the parent node
            $parent = $element->parentNode;
            if (!$parent) {
                continue;
            }
            
            // Move all child nodes to the parent
            while ($element->firstChild) {
                $parent->insertBefore($element->firstChild, $element);
            }
            
            // Remove the empty element
            $parent->removeChild($element);
        }
    }
    
    /**
     * Clean font attributes from style attributes
     *
     * @param DOMXPath $xpath The XPath object
     */
    private function clean_font_attributes($xpath) {
        // Find elements with style attributes
        $elements = $xpath->query('//*[@style]');
        
        if ($elements === false || $elements->length === 0) {
            return;
        }
        
        $cleaned_count = 0;
        
        foreach ($elements as $element) {
            // Get current style
            $style = $element->getAttribute('style');
            
            // Check if it has font attributes
            if (preg_match('/(font-family|font-size|font-weight|font-style):/i', $style)) {
                // Remove font attributes
                $cleaned_style = preg_replace('/\s*(font-family|font-size|font-weight|font-style):[^;]+;?/i', '', $style);
                
                // Set cleaned style or remove attribute if empty
                if (trim($cleaned_style) !== '') {
                    $element->setAttribute('style', trim($cleaned_style));
                } else {
                    $element->removeAttribute('style');
                }
                
                $cleaned_count++;
            }
        }
        
        if ($this->settings_manager->get_option('enable_debug', false) && $cleaned_count > 0) {
            $this->logger->log_debug("DOM Processor: Cleaned font attributes from $cleaned_count elements");
        }
    }
    
    /**
     * Clean style attributes
     *
     * @param DOMXPath $xpath The XPath object
     */
    private function clean_style_attributes($xpath) {
        // Find paragraph, span, list, table elements with style attributes
        $selector = '//p[@style] | //span[@style] | //li[@style] | //ul[@style] | //ol[@style] | //table[@style] | //tr[@style] | //td[@style] | //th[@style] | //div[@style]';
        $elements = $xpath->query($selector);
        
        if ($elements === false || $elements->length === 0) {
            return;
        }
        
        if ($this->settings_manager->get_option('enable_debug', false)) {
            $this->logger->log_debug("DOM Processor: Cleaning style attributes from " . $elements->length . " elements");
        }
        
        foreach ($elements as $element) {
            $element->removeAttribute('style');
        }
    }
    
    /**
     * Remove all style attributes
     *
     * @param DOMXPath $xpath The XPath object
     */
    private function remove_all_style_attributes($xpath) {
        // Find all elements with style attributes
        $elements = $xpath->query('//*[@style]');
        
        if ($elements === false || $elements->length === 0) {
            return;
        }
        
        if ($this->settings_manager->get_option('enable_debug', false)) {
            $this->logger->log_debug("DOM Processor: Removing all style attributes from " . $elements->length . " elements");
        }
        
        foreach ($elements as $element) {
            $element->removeAttribute('style');
        }
    }
    
    /**
     * Clean lang attributes
     *
     * @param DOMXPath $xpath The XPath object
     */
    private function clean_lang_attributes($xpath) {
        // Find elements with lang attributes
        $elements = $xpath->query('//*[@lang]');
        
        if ($elements === false || $elements->length === 0) {
            return;
        }
        
        if ($this->settings_manager->get_option('enable_debug', false)) {
            $this->logger->log_debug("DOM Processor: Cleaning lang attributes from " . $elements->length . " elements");
        }
        
        foreach ($elements as $element) {
            $element->removeAttribute('lang');
        }
    }
    
    /**
     * Remove empty elements (spans, divs, paragraphs with no content)
     *
     * @param DOMXPath $xpath The XPath object
     */
    private function remove_empty_elements($xpath) {
        // Find potentially empty elements
        $elements = $xpath->query('//span[not(node())] | //div[not(node())] | //p[not(node())]');
        
        if ($elements === false || $elements->length === 0) {
            return;
        }
        
        if ($this->settings_manager->get_option('enable_debug', false)) {
            $this->logger->log_debug("DOM Processor: Removing " . $elements->length . " empty elements");
        }
        
        // We need to collect the elements first because removing them changes the NodeList
        $elements_to_remove = array();
        
        if ($elements !== false) {
            foreach ($elements as $element) {
                $elements_to_remove[] = $element;
            }
        }
        
        foreach ($elements_to_remove as $element) {
            // Get the parent node
            $parent = $element->parentNode;
            if (!$parent) {
                continue;
            }
            
            // Remove the empty element
            $parent->removeChild($element);
        }
    }
    
    /**
     * Clean tables - preserve structure while removing unnecessary attributes
     *
     * @param DOMXPath $xpath The XPath object
     */
    private function clean_tables($xpath) {
        // Find all tables
        $tables = $xpath->query('//table');
        
        if ($tables === false || $tables->length === 0) {
            return;
        }
        
        if ($this->settings_manager->get_option('enable_debug', false)) {
            $this->logger->log_debug("DOM Processor: Cleaning " . $tables->length . " tables");
        }
        
        foreach ($tables as $table) {
            // Remove all attributes except for essential ones
            $border = $table->getAttribute('border');
            $cellspacing = $table->getAttribute('cellspacing');
            $cellpadding = $table->getAttribute('cellpadding');
            $width = $table->getAttribute('width');
            
            // Remove all attributes
            while ($table->hasAttributes()) {
                $attr = $table->attributes->item(0);
                $table->removeAttributeNode($attr);
            }
            
            // Add back essential attributes with defaults if not set
            $table->setAttribute('border', $border ? $border : '1');
            $table->setAttribute('cellspacing', $cellspacing ? $cellspacing : '0');
            $table->setAttribute('cellpadding', $cellpadding ? $cellpadding : '0');
            if ($width) {
                $table->setAttribute('width', $width);
            }
            
            // Clean table rows
            $rows = $xpath->query('.//tr', $table);
            if ($rows !== false) {
                foreach ($rows as $row) {
                    // Remove all attributes from rows
                    while ($row->hasAttributes()) {
                        $attr = $row->attributes->item(0);
                        $row->removeAttributeNode($attr);
                    }
                    
                    // Clean cells in this row
                    $cells = $xpath->query('.//td|.//th', $row);
                    if ($cells !== false) {
                        foreach ($cells as $cell) {
                            // Keep only width and valign attributes
                            $width = $cell->getAttribute('width');
                            $valign = $cell->getAttribute('valign');
                            
                            // Remove all attributes
                            while ($cell->hasAttributes()) {
                                $attr = $cell->attributes->item(0);
                                $cell->removeAttributeNode($attr);
                            }
                            
                            // Add back essential attributes if they had values
                            if ($width) {
                                $cell->setAttribute('width', $width);
                            }
                            if ($valign) {
                                $cell->setAttribute('valign', $valign);
                            }
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Clean lists - preserve structure while removing unnecessary attributes
     *
     * @param DOMXPath $xpath The XPath object
     */
    private function clean_lists($xpath) {
        // Find all lists
        $lists = $xpath->query('//ul | //ol');
        
        if ($lists === false || $lists->length === 0) {
            return;
        }
        
        if ($this->settings_manager->get_option('enable_debug', false)) {
            $this->logger->log_debug("DOM Processor: Cleaning " . $lists->length . " lists");
        }
        
        foreach ($lists as $list) {
            // Remove all attributes from list
            while ($list->hasAttributes()) {
                $attr = $list->attributes->item(0);
                $list->removeAttributeNode($attr);
            }
            
            // Clean list items
            $items = $xpath->query('.//li', $list);
            if ($items !== false) {
                foreach ($items as $item) {
                    // Remove all attributes from list items
                    while ($item->hasAttributes()) {
                        $attr = $item->attributes->item(0);
                        $item->removeAttributeNode($attr);
                    }
                }
            }
        }
        
        // Look for Word's special list format (MsoListParagraph paragraphs)
        $msoListParas = $xpath->query('//p[contains(@class, "MsoListParagraph")]');
        
        if ($msoListParas !== false && $msoListParas->length > 0) {
            $this->convert_mso_list_paragraphs($xpath, $msoListParas);
        }
    }
    
    /**
     * Convert Word's list paragraphs to proper HTML lists
     *
     * @param DOMXPath $xpath The XPath object
     * @param DOMNodeList $listParas The list paragraph elements
     */
    private function convert_mso_list_paragraphs($xpath, $listParas) {
        if ($this->settings_manager->get_option('enable_debug', false)) {
            $this->logger->log_debug("DOM Processor: Converting " . $listParas->length . " MSO list paragraphs to proper lists");
        }
        
        // Group consecutive list paragraphs
        $listGroups = array();
        $currentGroup = array();
        
        $previousPara = null;
        
        foreach ($listParas as $para) {
            if ($previousPara === null || !$this->are_siblings($previousPara, $para)) {
                // Start a new group
                if (!empty($currentGroup)) {
                    $listGroups[] = $currentGroup;
                }
                $currentGroup = array($para);
            } else {
                // Add to current group
                $currentGroup[] = $para;
            }
            
            $previousPara = $para;
        }
        
        // Add the last group
        if (!empty($currentGroup)) {
            $listGroups[] = $currentGroup;
        }
        
        // Process each group
        foreach ($listGroups as $group) {
            $this->create_list_from_paragraphs($xpath, $group);
        }
    }
    
    /**
     * Check if two nodes are siblings (same parent, adjacent in DOM)
     *
     * @param DOMNode $node1 First node
     * @param DOMNode $node2 Second node
     * @return bool Whether the nodes are siblings
     */
    private function are_siblings($node1, $node2) {
        if (!$node1 || !$node2) {
            return false;
        }
        
        // Check if they have the same parent
        if ($node1->parentNode !== $node2->parentNode) {
            return false;
        }
        
        // Check if they are adjacent (node2 follows node1)
        return $node1->nextSibling === $node2 || $node1->nextSibling === $node2->previousSibling;
    }
    
    /**
     * Create a proper HTML list from a group of MSO list paragraphs
     *
     * @param DOMXPath $xpath The XPath object
     * @param array $paragraphs Array of paragraph nodes
     */
    private function create_list_from_paragraphs($xpath, $paragraphs) {
        if (empty($paragraphs)) {
            return;
        }
        
        $dom = $xpath->document;
        $firstPara = $paragraphs[0];
        $parent = $firstPara->parentNode;
        
        // Create a new list element (default to unordered list)
        $list = $dom->createElement('ul');
        
        // Insert the list before the first paragraph
        $parent->insertBefore($list, $firstPara);
        
        // Process each paragraph into a list item
        foreach ($paragraphs as $para) {
            // Create a new list item
            $li = $dom->createElement('li');
            
            // Move the content from the paragraph to the list item
            while ($para->firstChild) {
                // Remove any Word list markers (usually in conditional comments)
                if ($para->firstChild->nodeType === XML_COMMENT_NODE) {
                    $comment = $para->firstChild;
                    $para->removeChild($comment);
                    continue;
                }
                
                // Remove list markers at the beginning (usually there's some text node with a bullet or number)
                if ($para->firstChild->nodeType === XML_TEXT_NODE) {
                    $text = $para->firstChild->nodeValue;
                    
                    // Check if this is likely a list marker (bullet, number followed by tab or space)
                    if (preg_match('/^[\s\xA0]*([0-9]+\.|\p{Po})[\s\xA0]+/u', $text, $matches)) {
                        // Remove just the marker part
                        $para->firstChild->nodeValue = preg_replace('/^[\s\xA0]*([0-9]+\.|\p{Po})[\s\xA0]+/u', '', $text);
                        
                        // Detect if this is likely an ordered list
                        if (isset($matches[1]) && preg_match('/[0-9]+\./', $matches[1])) {
                            // Change the list type to ordered if this is the first item
                            if ($list->tagName === 'ul' && $list->childNodes->length === 0) {
                                // Replace the UL with an OL
                                $ol = $dom->createElement('ol');
                                $parent->insertBefore($ol, $list);
                                $parent->removeChild($list);
                                $list = $ol;
                            }
                        }
                    }
                }
                
                $li->appendChild($para->firstChild);
            }
            
            // Add the list item to the list
            $list->appendChild($li);
            
            // Remove the now-empty paragraph
            $parent->removeChild($para);
        }
    }
}