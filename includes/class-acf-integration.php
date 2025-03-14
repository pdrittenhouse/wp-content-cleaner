<?php
/**
 * ACF integration for the Word Markup Cleaner plugin
 *
 * @package WordPress_Word_Markup_Cleaner
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class WP_Word_Markup_Cleaner_ACF_Integration
 * 
 * Handles integration with Advanced Custom Fields
 */
class WP_Word_Markup_Cleaner_ACF_Integration {
    
    /**
     * Settings manager instance
     *
     * @var WP_Word_Markup_Cleaner_Settings_Manager
     */
    private $settings_manager;
    
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
     * Flag to track if ACF is active
     *
     * @var bool
     */
    private $acf_active = false;
    
    /**
     * Text field types that need cleaning
     *
     * @var array
     */
    private $text_field_types = [
        'text',
        'textarea',
        'wysiwyg',
        'url',
        'email'
    ];
    
    /**
     * Container field types that can have nested text fields
     *
     * @var array
     */
    private $container_field_types = [
        'repeater',
        'group',
        'flexible_content'
    ];
    
    /**
     * Field type cache to avoid repeated lookups
     *
     * @var array
     */
    private $field_type_cache = [];

     /**
     * Processed block cache to avoid processing unchanged blocks
     *
     * @var array
     */
    private $processed_blocks_cache = [];

    /**
     * Flag to track if ACF Blocks are supported
     *
     * @var bool
     */
    private $acf_blocks_supported = false;
    
    /**
     * Initialize the ACF integration
     *
     * @param WP_Word_Markup_Cleaner_Logger $logger Logger instance
     * @param WP_Word_Markup_Cleaner_Content $cleaner Content cleaner instance
     * @param WP_Word_Markup_Cleaner_Settings_Manager $settings_manager Settings manager instance
     */
    public function __construct($logger, $cleaner, $settings_manager) {
        $this->logger = $logger;
        $this->cleaner = $cleaner;
        $this->settings_manager = $settings_manager;
        
        // Check if ACF is active
        $this->acf_active = class_exists('ACF');
        
        // Load field type settings
        $this->load_field_type_settings();
        
        // Only hook into ACF if it's active and ACF cleaning is enabled
        if ($this->acf_active && $this->settings_manager->get_option('enable_acf_cleaning', false)) {
            // Hook directly into database operations for ACF
            add_filter('acf/update_value', array($this, 'clean_acf_value'), 10, 3);

            // Check if ACF is active and has Blocks support
            $this->acf_blocks_supported = false;
            
            if ($this->acf_active) {
                // Check ACF version for Blocks support (5.8.0+)
                $acf_version = acf_get_setting('version');
                $this->acf_blocks_supported = version_compare($acf_version, '5.8.0', '>=');
                
                if ($this->settings_manager->get_option('enable_debug', false)) {
                    $this->logger->log_debug("ACF Version: {$acf_version}");
                    $this->logger->log_debug("ACF Blocks Support: " . ($this->acf_blocks_supported ? 'Yes' : 'No'));
                }
            }

            // Only add block-specific hooks if both ACF cleaning is enabled and blocks are supported
            if ($this->acf_blocks_supported) {
                // Add hook for Gutenberg block content
                add_filter('content_save_pre', array($this, 'process_gutenberg_content'), 5); // Run before standard content cleaner

                // Add hook for REST API content
                add_filter('rest_pre_insert_post', array($this, 'clean_rest_content'), 10, 2);
            }
            
            // Advanced debugging: hooks that fire when ACF saves data
            if ($this->settings_manager->get_option('enable_debug', false)) {
                add_action('acf/save_post', array($this, 'log_acf_save'), 20);
            }
            
            // Add admin scripts and styles for ACF fields
            add_action('acf/input/admin_enqueue_scripts', array($this, 'enqueue_acf_admin_assets'));
            
            // Log initialization
            if ($this->settings_manager->get_option('enable_debug', false)) {
                $this->logger->log_debug("ACF Integration initialized - ACF is active");
                $this->logger->log_debug("Text field types: " . implode(', ', $this->text_field_types));
                $this->logger->log_debug("Container field types: " . implode(', ', $this->container_field_types));
            }
        } else if ($this->settings_manager->get_option('enable_debug', false)) {
            if (!$this->acf_active) {
                $this->logger->log_debug("ACF Integration not active - ACF plugin not detected");
            } else {
                $this->logger->log_debug("ACF Integration not active - ACF cleaning is disabled in settings");
            }
        }
    }
    
    /**
     * Load field type settings from the database
     */
    private function load_field_type_settings() {
        // Get field type settings from the settings manager
        $field_types = $this->settings_manager->get_field_type_settings();
        
        // Set text field types (with defaults if not set)
        if (isset($field_types['text_field_types']) && !empty($field_types['text_field_types'])) {
            $this->text_field_types = $field_types['text_field_types'];
        } else {
            $this->text_field_types = array(
                'text',
                'textarea',
                'wysiwyg',
                'url',
                'email'
            );
        }
        
        // Set container field types (with defaults if not set)
        if (isset($field_types['container_field_types']) && !empty($field_types['container_field_types'])) {
            $this->container_field_types = $field_types['container_field_types'];
        } else {
            $this->container_field_types = array(
                'repeater',
                'group',
                'flexible_content'
            );
        }
    }
    
    /**
     * Enqueue admin scripts and styles specifically for ACF fields
     */
    public function enqueue_acf_admin_assets() {
        // Use constants from main plugin file for consistency
        $version = defined('WP_WORD_MARKUP_CLEANER_VERSION') ? WP_WORD_MARKUP_CLEANER_VERSION : '1.0.0';
        
        // Check if files exist first
        $css_file = plugin_dir_path(dirname(__FILE__)) . 'assets/css/acf-integration.css';
        $js_file = plugin_dir_path(dirname(__FILE__)) . 'assets/js/acf-integration.js';
        
        // Enqueue the CSS for ACF integration
        if (file_exists($css_file)) {
            wp_enqueue_style(
                'wp-word-markup-cleaner-acf',
                plugin_dir_url(dirname(__FILE__)) . 'assets/css/acf-integration.css',
                array(),
                $version
            );
        }
        
        // Enqueue the JS for ACF integration
        if (file_exists($js_file)) {
            wp_enqueue_script(
                'wp-word-markup-cleaner-acf',
                plugin_dir_url(dirname(__FILE__)) . 'assets/js/acf-integration.js',
                array('jquery', 'acf-input'),
                $version,
                true
            );
            
            // Pass settings to JavaScript
            wp_localize_script(
                'wp-word-markup-cleaner-acf',
                'wordMarkupCleanerSettings',
                array(
                    'acfCleaningEnabled' => $this->settings_manager->get_option('enable_acf_cleaning', false),
                    'textFieldTypes' => $this->text_field_types,
                    'containerFieldTypes' => $this->container_field_types
                )
            );
        }
    }
    
    /**
     * Clean ACF field values
     * 
     * @param mixed $value The field value
     * @param int $post_id The post ID
     * @param array $field The field array
     * @return mixed The cleaned value
     */
    public function clean_acf_value($value, $post_id, $field) {
        // Only process if ACF cleaning is enabled
        if (!$this->settings_manager->get_option('enable_acf_cleaning', false)) {
            return $value;
        }
        
        // Get field type
        $field_type = isset($field['type']) ? $field['type'] : '';
        
        // Log the field being processed
        if ($this->settings_manager->get_option('enable_debug', false)) {
            $this->logger->log_debug("PROCESSING ACF FIELD: {$field['name']} (Type: {$field_type})");
        }
        
        // Process field based on type
        return $this->process_field_by_type($value, $field_type, $field);
    }
    
    /**
     * Process field based on its type
     * 
     * @param mixed $value The field value
     * @param string $field_type The field type
     * @param array $field The field array
     * @return mixed The processed value
     */
    private function process_field_by_type($value, $field_type, $field) {
        // Skip processing if value is not a string or array
        if (!is_string($value) && !is_array($value)) {
            if ($this->settings_manager->get_option('enable_debug', false)) {
                $this->logger->log_debug("SKIPPING FIELD {$field['name']} - Not a string or array (type: " . gettype($value) . ")");
            }
            return $value;
        }
        
        // Handle text field types (direct processing)
        if (in_array($field_type, $this->text_field_types) && is_string($value)) {
            if ($this->settings_manager->get_option('enable_debug', false)) {
                $this->logger->log_debug("CLEANING TEXT-BASED ACF FIELD: {$field['name']} (Type: {$field_type})");
            }
            
            // Store original value for comparison
            $original_value = $value;
            
            // Clean the content
            $value = $this->cleaner->clean_content($value);
            
            // Log detailed changes if the content was modified
            if ($original_value !== $value && $this->settings_manager->get_option('enable_debug', false)) {
                $this->logger->log_debug("CHANGES DETECTED IN ACF FIELD: {$field['name']}");
                $this->logger->log_debug("ORIGINAL LENGTH: " . strlen($original_value));
                $this->logger->log_debug("CLEANED LENGTH: " . strlen($value));
            }
            
            return $value;
        }
        
        // Handle repeater fields
        if ($field_type === 'repeater' && is_array($value)) {
            return $this->process_repeater_field($value, $field);
        }
        
        // Handle flexible content fields
        if ($field_type === 'flexible_content' && is_array($value)) {
            return $this->process_flexible_content_field($value, $field);
        }
        
        // Handle group fields
        if ($field_type === 'group' && is_array($value)) {
            return $this->process_group_field($value, $field);
        }
        
        // Skip processing for unsupported field types
        if ($this->settings_manager->get_option('enable_debug', false)) {
            $this->logger->log_debug("SKIPPING FIELD {$field['name']} - Unsupported field type: {$field_type}");
        }
        
        return $value;
    }
    
    /**
     * Process repeater field
     * 
     * @param array $value The field value
     * @param array $field The field array
     * @return array The processed value
     */
    private function process_repeater_field($value, $field) {
        if ($this->settings_manager->get_option('enable_debug', false)) {
            $this->logger->log_debug("PROCESSING REPEATER FIELD: {$field['name']}");
        }
        
        // Skip processing if no sub-fields are defined
        if (empty($field['sub_fields']) || !is_array($field['sub_fields'])) {
            if ($this->settings_manager->get_option('enable_debug', false)) {
                $this->logger->log_debug("SKIPPING REPEATER {$field['name']} - No sub-fields defined");
            }
            return $value;
        }
        
        // Map sub-fields by key for quick lookup
        $sub_fields_by_key = [];
        foreach ($field['sub_fields'] as $sub_field) {
            if (isset($sub_field['key']) && isset($sub_field['type'])) {
                $sub_fields_by_key[$sub_field['key']] = $sub_field;
            }
        }
        
        // Process each row in the repeater
        foreach ($value as $i => $row) {
            if ($this->settings_manager->get_option('enable_debug', false)) {
                $this->logger->log_debug("PROCESSING REPEATER ROW: $i in {$field['name']}");
            }
            
            if (is_array($row)) {
                // Process each field in the row
                foreach ($row as $key => $sub_value) {
                    // Get the sub-field type if available
                    $sub_field_key = acf_get_field_key($key, $field['key']);
                    
                    if ($sub_field_key && isset($sub_fields_by_key[$sub_field_key])) {
                        $sub_field = $sub_fields_by_key[$sub_field_key];
                        
                        // Process based on sub-field type
                        if (in_array($sub_field['type'], $this->text_field_types) && is_string($sub_value)) {
                            // Clean text-based field
                            if ($this->settings_manager->get_option('enable_debug', false)) {
                                $this->logger->log_debug("CLEANING REPEATER TEXT VALUE: {$sub_field['name']} (Type: {$sub_field['type']})");
                            }
                            $value[$i][$key] = $this->cleaner->clean_content($sub_value);
                        } elseif (in_array($sub_field['type'], $this->container_field_types) && is_array($sub_value)) {
                            // Process nested container
                            $value[$i][$key] = $this->process_field_by_type($sub_value, $sub_field['type'], $sub_field);
                        } else {
                            // Skip non-text, non-container fields
                            if ($this->settings_manager->get_option('enable_debug', false)) {
                                $this->logger->log_debug("SKIPPING REPEATER SUB-FIELD: {$sub_field['name']} - Type: {$sub_field['type']}");
                            }
                        }
                    } elseif (is_string($sub_value)) {
                        // Fallback for when we can't get the field type - only clean strings
                        if ($this->settings_manager->get_option('enable_debug', false)) {
                            $this->logger->log_debug("CLEANING REPEATER TEXT VALUE (FALLBACK): $key - No type info available");
                        }
                        $value[$i][$key] = $this->cleaner->clean_content($sub_value);
                    }
                }
            }
        }
        
        return $value;
    }
    
    /**
     * Process flexible content field
     * 
     * @param array $value The field value
     * @param array $field The field array
     * @return array The processed value
     */
    private function process_flexible_content_field($value, $field) {
        if ($this->settings_manager->get_option('enable_debug', false)) {
            $this->logger->log_debug("PROCESSING FLEXIBLE CONTENT FIELD: {$field['name']}");
        }
        
        // Skip processing if no layouts are defined
        if (empty($field['layouts']) || !is_array($field['layouts'])) {
            if ($this->settings_manager->get_option('enable_debug', false)) {
                $this->logger->log_debug("SKIPPING FLEXIBLE CONTENT {$field['name']} - No layouts defined");
            }
            return $value;
        }
        
        // Map layouts and their sub-fields for quick lookup
        $layouts = [];
        foreach ($field['layouts'] as $layout) {
            if (isset($layout['name']) && isset($layout['sub_fields']) && is_array($layout['sub_fields'])) {
                $sub_fields = [];
                foreach ($layout['sub_fields'] as $sub_field) {
                    if (isset($sub_field['key']) && isset($sub_field['name'])) {
                        $sub_fields[$sub_field['name']] = $sub_field;
                    }
                }
                $layouts[$layout['name']] = $sub_fields;
            }
        }
        
        // Process each layout in the flexible content
        foreach ($value as $i => $layout) {
            if (!is_array($layout) || empty($layout['acf_fc_layout'])) {
                continue;
            }
            
            $layout_name = $layout['acf_fc_layout'];
            
            if ($this->settings_manager->get_option('enable_debug', false)) {
                $this->logger->log_debug("PROCESSING FLEXIBLE CONTENT LAYOUT: $layout_name");
            }
            
            if (isset($layouts[$layout_name])) {
                $layout_fields = $layouts[$layout_name];
                
                // Process each field in the layout
                foreach ($layout as $key => $sub_value) {
                    if ($key === 'acf_fc_layout') {
                        continue;
                    }
                    
                    // Get the sub-field type if available
                    if (isset($layout_fields[$key])) {
                        $sub_field = $layout_fields[$key];
                        
                        // Process based on sub-field type
                        if (in_array($sub_field['type'], $this->text_field_types) && is_string($sub_value)) {
                            // Clean text-based field
                            if ($this->settings_manager->get_option('enable_debug', false)) {
                                $this->logger->log_debug("CLEANING FLEXIBLE CONTENT TEXT VALUE: {$sub_field['name']} (Type: {$sub_field['type']})");
                            }
                            $value[$i][$key] = $this->cleaner->clean_content($sub_value);
                        } elseif (in_array($sub_field['type'], $this->container_field_types) && is_array($sub_value)) {
                            // Process nested container
                            $value[$i][$key] = $this->process_field_by_type($sub_value, $sub_field['type'], $sub_field);
                        } else {
                            // Skip non-text, non-container fields
                            if ($this->settings_manager->get_option('enable_debug', false)) {
                                $this->logger->log_debug("SKIPPING FLEXIBLE CONTENT SUB-FIELD: {$sub_field['name']} - Type: {$sub_field['type']}");
                            }
                        }
                    } elseif (is_string($sub_value)) {
                        // Fallback for when we can't get the field type - only clean strings
                        if ($this->settings_manager->get_option('enable_debug', false)) {
                            $this->logger->log_debug("CLEANING FLEXIBLE CONTENT TEXT VALUE (FALLBACK): $key - No type info available");
                        }
                        $value[$i][$key] = $this->cleaner->clean_content($sub_value);
                    }
                }
            } else {
                if ($this->settings_manager->get_option('enable_debug', false)) {
                    $this->logger->log_debug("SKIPPING UNKNOWN LAYOUT: $layout_name - No layout definition found");
                }
            }
        }
        
        return $value;
    }
    
    /**
     * Process group field
     * 
     * @param array $value The field value
     * @param array $field The field array
     * @return array The processed value
     */
    private function process_group_field($value, $field) {
        if ($this->settings_manager->get_option('enable_debug', false)) {
            $this->logger->log_debug("PROCESSING GROUP FIELD: {$field['name']}");
        }
        
        // Skip processing if no sub-fields are defined
        if (empty($field['sub_fields']) || !is_array($field['sub_fields'])) {
            if ($this->settings_manager->get_option('enable_debug', false)) {
                $this->logger->log_debug("SKIPPING GROUP {$field['name']} - No sub-fields defined");
            }
            return $value;
        }
        
        // Map sub-fields by name for quick lookup
        $sub_fields_by_name = [];
        foreach ($field['sub_fields'] as $sub_field) {
            if (isset($sub_field['name']) && isset($sub_field['type'])) {
                $sub_fields_by_name[$sub_field['name']] = $sub_field;
            }
        }
        
        // Process each field in the group
        foreach ($value as $key => $sub_value) {
            // Get the sub-field type if available
            if (isset($sub_fields_by_name[$key])) {
                $sub_field = $sub_fields_by_name[$key];
                
                // Process based on sub-field type
                if (in_array($sub_field['type'], $this->text_field_types) && is_string($sub_value)) {
                    // Clean text-based field
                    if ($this->settings_manager->get_option('enable_debug', false)) {
                        $this->logger->log_debug("CLEANING GROUP TEXT VALUE: {$sub_field['name']} (Type: {$sub_field['type']})");
                    }
                    $value[$key] = $this->cleaner->clean_content($sub_value);
                } elseif (in_array($sub_field['type'], $this->container_field_types) && is_array($sub_value)) {
                    // Process nested container
                    $value[$key] = $this->process_field_by_type($sub_value, $sub_field['type'], $sub_field);
                } else {
                    // Skip non-text, non-container fields
                    if ($this->settings_manager->get_option('enable_debug', false)) {
                        $this->logger->log_debug("SKIPPING GROUP SUB-FIELD: {$sub_field['name']} - Type: {$sub_field['type']}");
                    }
                }
            } elseif (is_string($sub_value)) {
                // Fallback for when we can't get the field type - only clean strings
                if ($this->settings_manager->get_option('enable_debug', false)) {
                    $this->logger->log_debug("CLEANING GROUP TEXT VALUE (FALLBACK): $key - No type info available");
                }
                $value[$key] = $this->cleaner->clean_content($sub_value);
            }
        }
        
        return $value;
    }
    
    /**
     * Log when ACF saves a post
     * 
     * @param int $post_id The post ID being saved
     */
    public function log_acf_save($post_id) {
        if ($this->settings_manager->get_option('enable_debug', false)) {
            $this->logger->log_debug("==== ACF SAVE ACTION TRIGGERED ====");
            $this->logger->log_debug("POST ID: $post_id");
            $this->logger->log_debug("FIELDS UPDATED AFTER THIS LINE");
        }
    }
    
    /**
     * Check if ACF is active
     *
     * @return bool Whether ACF is active
     */
    public function is_acf_active() {
        return $this->acf_active;
    }
    
    /**
     * Get field types that can be cleaned
     *
     * @return array List of ACF field types that can be cleaned
     */
    public function get_cleanable_field_types() {
        $cleanable_types = [];
        
        // Add text field types
        foreach ($this->text_field_types as $type) {
            $cleanable_types[$type] = ucfirst($type) . ' fields';
        }
        
        // Add container field types
        foreach ($this->container_field_types as $type) {
            $cleanable_types[$type] = ucfirst($type) . ' fields (text-based sub-fields)';
        }
        
        return $cleanable_types;
    }

    /**
     *  Gutenberg Block Content Hook
     * 
     *  Filter to intercept Gutenberg block content during save
     */
    public function process_gutenberg_content($content) {
        // Check if content has blocks
        if (has_blocks($content)) {
            return $this->process_acf_blocks($content);
        }
        
        return $content;
    }

    /**
     * Process ACF Gutenberg blocks
     * 
     * @param string $content The content containing blocks
     * @return string The processed content
     */
    private function process_acf_blocks($content) {
        // Parse blocks to find ACF blocks
        $blocks = parse_blocks($content);
        $modified = false;

        // Log block data
        if ($this->settings_manager->get_option('enable_debug', false)) {
            $this->logger->log_debug("FOUND " . count($blocks) . " BLOCKS IN CONTENT");
            $this->logger->log_debug("ACF BLOCKS: " . count(array_filter($blocks, function($block) {
                return $block['blockName'] !== null && strpos($block['blockName'], 'acf/') === 0;
            })));
        }
        
        foreach ($blocks as $i => $block) {
            // Check cache
            $cache_key = md5($block['blockName'] . serialize($block['attrs']['data']));
            if (isset($this->processed_blocks_cache[$cache_key])) {
                $blocks[$i]['attrs']['data'] = $this->processed_blocks_cache[$cache_key];
                $modified = true;
                continue;
            }

            // Process block
            if ($block['blockName'] !== null && strpos($block['blockName'], 'acf/') === 0) {
                // Process ACF block attributes
                if (!empty($block['attrs']['data'])) {
                    $block_data = $this->clean_acf_block_data($block['attrs']['data'], $block['blockName']);
                    if ($block_data !== $block['attrs']['data']) {
                        $blocks[$i]['attrs']['data'] = $block_data;
                        $modified = true;
                    }
                }
                
                // Process inner content that might contain Word markup
                if (!empty($block['innerContent'])) {
                    foreach ($block['innerContent'] as $j => $inner_content) {
                        if (!empty($inner_content)) {
                            $cleaned_content = $this->cleaner->clean_content($inner_content, 'acf_block_content');
                            if ($cleaned_content !== $inner_content) {
                                $blocks[$i]['innerContent'][$j] = $cleaned_content;
                                $modified = true;
                            }
                        }
                    }
                }
            }

            // Update cache
            if (isset($block_data)) {
                $this->processed_blocks_cache[$cache_key] = $block_data;
            }
        }
        
        // Only reserialize if we made changes
        if ($modified) {
            $serialized = serialize_blocks($blocks);
            // Verify serialization didn't break block structure
            if (parse_blocks($serialized)) {
                return $serialized;
            } else {
                // Log failure and return original content
                $this->logger->log_debug("BLOCK SERIALIZATION FAILED - RETURNING ORIGINAL CONTENT");
                return $content;
            }
        }
        
        return $content;
    }

    /**
     * Clean ACF Gutenberg block data
     * 
     * @param array $data Block data
     * @param string $block_name Block name
     * @return array Cleaned block data
     */
    private function clean_acf_block_data($data, $block_name) {
        if (empty($data) || !is_array($data)) {
            return $data;
        }
        
        // Log block processing
        if ($this->settings_manager->get_option('enable_debug', false)) {
            $this->logger->log_debug("PROCESSING ACF BLOCK: {$block_name}");
        }
        
        // Process each field in the block
        foreach ($data as $field_name => $field_value) {
            if (is_string($field_value)) {
                // Process text fields that might contain Word markup
                $data[$field_name] = $this->cleaner->clean_content($field_value, 'acf_block_field');
            } elseif (is_array($field_value)) {
                // Handle nested arrays (repeaters, groups)
                $data[$field_name] = $this->clean_acf_block_data($field_value, "{$block_name}/{$field_name}");
            }
        }
        
        return $data;
    }

    /**
     * Clean content from REST API requests (Gutenberg)
     * 
     * @param stdClass $prepared_post The post being inserted
     * @param WP_REST_Request $request The request object
     * @return stdClass The modified post object
     */
    public function clean_rest_content($prepared_post, $request) {
        if (!empty($prepared_post->post_content) && has_blocks($prepared_post->post_content)) {
            $prepared_post->post_content = $this->process_acf_blocks($prepared_post->post_content);
            
            if ($this->settings_manager->get_option('enable_debug', false)) {
                $this->logger->log_debug("CLEANED CONTENT FROM REST API REQUEST");
            }
        }
        
        return $prepared_post;
    }
}