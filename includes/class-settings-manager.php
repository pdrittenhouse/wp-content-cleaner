<?php
/**
 * Settings manager for the Word Markup Cleaner plugin
 *
 * @package WordPress_Word_Markup_Cleaner
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class WP_Word_Markup_Cleaner_Settings_Manager
 * 
 * Centralized settings management for the plugin using a singleton pattern
 */
class WP_Word_Markup_Cleaner_Settings_Manager {
    
    /**
     * Plugin options
     *
     * @var array
     */
    private $options = array();
    
    /**
     * Loaded option groups
     *
     * @var array
     */
    private $option_groups = array();
    
    /**
     * Cache of field type settings
     *
     * @var array|null
     */
    private $field_type_settings = null;
    
    /**
     * Instance of this class
     *
     * @var WP_Word_Markup_Cleaner_Settings_Manager
     */
    private static $instance = null;
    
    /**
     * Whether the main options have been loaded
     *
     * @var bool
     */
    private $options_loaded = false;

    /**
     * Cache version - increment when plugin version changes
     *
     * @var string
     */
    private $cache_version = '1';

    /**
     * Cache group prefix for this plugin
     *
     * @var string
     */
    private $cache_group = 'wp_word_cleaner';

    /**
     * Cache TTL in seconds (default: 1 hour)
     *
     * @var int
     */
    private $cache_ttl = 3600;

    /**
     * Flag to indicate if caching is enabled
     *
     * @var bool
     */
    private $cache_enabled = true;

    /**
     * Get a single instance of this class
     *
     * @return WP_Word_Markup_Cleaner_Settings_Manager
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor to implement singleton
     */
    private function __construct() {
        // Set the cache version based on plugin version
        if (defined('WP_WORD_MARKUP_CLEANER_VERSION')) {
            $this->cache_version = WP_WORD_MARKUP_CLEANER_VERSION;
        }

        // Cache invalidation when options are updated
        add_action('update_option_wp_word_cleaner_options', array($this, 'invalidate_cache'), 10, 0);
        add_action('update_option_wp_word_cleaner_field_types', array($this, 'invalidate_cache'), 10, 0);
        
        // Invalidate cache for content type groups
        $groups = array('core_types', 'acf_types', 'custom_post_types', 'special_types');
        foreach ($groups as $group) {
            add_action("update_option_wp_word_cleaner_{$group}", array($this, 'invalidate_cache'), 10, 0);
        }

        // Don't load options in constructor to avoid circular dependencies
        // Options will be loaded on first access via get_option()
    }

    /**
     * Load main plugin options if not already loaded
     */
    private function ensure_options_loaded() {
        if (!$this->options_loaded) {
            $this->load_main_options();
            $this->options_loaded = true;
        }
    }

    /**
     * Load main plugin options
     */
    private function load_main_options() {
        // Check object cache first
        $cache_key = $this->get_cache_key('main_options');
        $cached_options = $this->get_cache($cache_key);
        
        if ($cached_options !== false) {
            $this->options = $cached_options;
            return;
        }

        // Default options
        $default_options = array(
            'enable_content_cleaning' => 1,
            'enable_acf_cleaning' => 1,
            'protect_tables' => 1,
            'protect_lists' => 1,
            'enable_debug' => 0,
            'use_dom_processing' => 1
        );
        
        // Get options with defaults for any missing values
        $this->options = wp_parse_args(
            get_option('wp_word_cleaner_options', array()),
            $default_options
        );

        // Store in cache
        $this->set_cache($cache_key, $this->options);
    }
    
    /**
     * Get option value
     *
     * @param string $key Option key
     * @param mixed $default Default value if option not set
     * @return mixed Option value or default
     */
    public function get_option($key, $default = false) {
        $this->ensure_options_loaded();
        
        if (isset($this->options[$key])) {
            return $this->options[$key];
        }
        return $default;
    }
    
    /**
     * Get all options
     *
     * @return array All options
     */
    public function get_all_options() {
        $this->ensure_options_loaded();
        return $this->options;
    }
    
    /**
     * Update option
     *
     * @param string $key Option key
     * @param mixed $value Option value
     * @return bool Success
     */
    public function update_option($key, $value) {
        $this->ensure_options_loaded();
        
        $this->options[$key] = $value;
        $result = update_option('wp_word_cleaner_options', $this->options);

        // Update cache
        $cache_key = $this->get_cache_key('main_options');
        $this->set_cache($cache_key, $this->options);
        
        return $result;
    }
    
    /**
     * Update all options
     *
     * @param array $options New options
     * @return bool Success
     */
    public function update_options($options) {
        $this->ensure_options_loaded();
        
        $this->options = wp_parse_args($options, $this->options);
        $result = update_option('wp_word_cleaner_options', $this->options);

        // Update cache
        $cache_key = $this->get_cache_key('main_options');
        $this->set_cache($cache_key, $this->options);
        
        return $result;
    }
    
    /**
     * Get option group for a content type
     * 
     * @param string $type Content type identifier
     * @return string Option group key
     */
    public function get_option_group_for_type($type) {
        if (strpos($type, 'acf_') === 0) {
            return 'acf_types';
        } elseif (in_array($type, array('post', 'page', 'attachment', 'revision'))) {
            return 'core_types'; 
        } elseif ($type === 'excerpt') {
            return 'special_types';
        } else {
            return 'custom_post_types';
        }
    }

    /**
     * Load settings for a specific content type group
     * 
     * @param string $group Group identifier
     * @return array Group settings
     */
    public function load_option_group($group) {
        if (!isset($this->option_groups[$group])) {
            $cache_key = $this->get_cache_key('group_' . $group);
            $cached_group = $this->get_cache($cache_key);
            
            if ($cached_group !== false) {
                $this->option_groups[$group] = $cached_group;
            } else {
                $option_key = "wp_word_cleaner_{$group}";
                $this->option_groups[$group] = get_option($option_key, array());
                
                // Store in cache
                $this->set_cache($cache_key, $this->option_groups[$group]);
            }
        }
        return $this->option_groups[$group];
    }

    /**
     * Save settings for a specific content type group
     * 
     * @param string $group Group identifier
     * @param array $settings Group settings
     * @return bool Success
     */
    public function save_option_group($group, $settings) {
        $option_key = "wp_word_cleaner_{$group}";
        $result = update_option($option_key, $settings);
        
        if ($result) {
            $this->option_groups[$group] = $settings;
            
            // Update cache
            $cache_key = $this->get_cache_key('group_' . $group);
            $this->set_cache($cache_key, $settings);
        }
        
        return $result;
    }
    
    /**
     * Get content type settings
     * 
     * @param string $type Content type identifier
     * @return array Content type settings
     */
    public function get_content_type_settings($type) {
        // Check cache first
        $cache_key = $this->get_cache_key('content_type_' . $type);
        $cached_settings = $this->get_cache($cache_key);
        
        if ($cached_settings !== false) {
            return $cached_settings;
        }
        
        $group = $this->get_option_group_for_type($type);
        $group_settings = $this->load_option_group($group);
        
        if (isset($group_settings[$type])) {
            $settings = $group_settings[$type];
        } else {
            // Return defaults if no specific settings found
            $settings = $this->get_default_cleaning_levels($type);
        }
        
        // Store in cache
        $this->set_cache($cache_key, $settings);
        
        return $settings;
    }
    
    /**
     * Get default cleaning levels for a content type
     * 
     * @param string $content_type Content type identifier
     * @return array Default cleaning levels
     */
    public function get_default_cleaning_levels($content_type) {
        // Check cache first
        $cache_key = $this->get_cache_key('default_levels_' . $content_type);
        $cached_levels = $this->get_cache($cache_key);
        
        if ($cached_levels !== false) {
            return $cached_levels;
        }
        
        $levels = array();
        
        switch ($content_type) {
            // WordPress core content types
            case 'post':
            case 'page':
            case 'wp_content':
                $levels = array(
                    'use_dom_processing' => true,
                    'xml_namespaces' => true,
                    'conditional_comments' => true,
                    'mso_classes' => true, 
                    'mso_styles' => true,
                    'font_attributes' => true,
                    'style_attributes' => true,
                    'lang_attributes' => true,
                    'empty_elements' => true,
                    'protect_tables' => true,
                    'protect_lists' => true,
                    'strip_all_styles' => false,
                );
                break;
                
            // ACF field types
            case 'acf_wysiwyg':
                $levels = array(
                    'use_dom_processing' => true,
                    'xml_namespaces' => true,
                    'conditional_comments' => true,
                    'mso_classes' => true, 
                    'mso_styles' => true,
                    'font_attributes' => true,
                    'style_attributes' => true,
                    'lang_attributes' => true,
                    'empty_elements' => true,
                    'protect_tables' => true,
                    'protect_lists' => true,
                    'strip_all_styles' => false,
                );
                break;
                
            case 'acf_text':
            case 'acf_textarea':
                $levels = array(
                    'use_dom_processing' => true,
                    'xml_namespaces' => true,
                    'conditional_comments' => true,
                    'mso_classes' => true, 
                    'mso_styles' => true,
                    'font_attributes' => false,
                    'style_attributes' => false,
                    'lang_attributes' => false,
                    'empty_elements' => false,
                    'protect_tables' => false,
                    'protect_lists' => false,
                    'strip_all_styles' => false,
                );
                break;

            case 'acf_block_field':
            case 'acf_block_content':
                $levels = array(
                    'use_dom_processing' => true,
                    'xml_namespaces' => true,
                    'conditional_comments' => true,
                    'mso_classes' => true, 
                    'mso_styles' => true,
                    'font_attributes' => true,
                    'style_attributes' => true,
                    'lang_attributes' => true,
                    'empty_elements' => true,
                    'protect_tables' => true,
                    'protect_lists' => true,
                    'strip_all_styles' => false,
                );
                break;
                
            // WordPress excerpts
            case 'excerpt':
                $levels = array(
                    'use_dom_processing' => true,
                    'xml_namespaces' => true,
                    'conditional_comments' => true,
                    'mso_classes' => true, 
                    'mso_styles' => true,
                    'font_attributes' => true,
                    'style_attributes' => true,
                    'lang_attributes' => true,
                    'empty_elements' => true,
                    'protect_tables' => false,
                    'protect_lists' => false,
                    'strip_all_styles' => false,
                    'strip_all_html' => true,
                );
                break;
                
            default:
                $levels = array(
                    'use_dom_processing' => true,
                    'xml_namespaces' => true,
                    'conditional_comments' => true,
                    'mso_classes' => true, 
                    'mso_styles' => true,
                    'font_attributes' => true,
                    'style_attributes' => true,
                    'lang_attributes' => true,
                    'empty_elements' => true,
                    'protect_tables' => true,
                    'protect_lists' => true,
                    'strip_all_styles' => false,
                );
                break;
        }
        
        // Store in cache
        $this->set_cache($cache_key, $levels);
        
        return $levels;
    }
    
    /**
     * Get field type settings
     * 
     * @return array Field type settings
     */
    public function get_field_type_settings() {
        if ($this->field_type_settings === null) {
            $cache_key = $this->get_cache_key('field_types');
            $cached_settings = $this->get_cache($cache_key);
            
            if ($cached_settings !== false) {
                $this->field_type_settings = $cached_settings;
            } else {
                $default_field_types = array(
                    'text_field_types' => array('text', 'textarea', 'wysiwyg', 'url', 'email'),
                    'container_field_types' => array('repeater', 'group', 'flexible_content')
                );
                
                $this->field_type_settings = get_option('wp_word_cleaner_field_types', $default_field_types);
                
                // Store in cache
                $this->set_cache($cache_key, $this->field_type_settings);
            }
        }
        
        return $this->field_type_settings;
    }
    
    /**
     * Save field type settings
     * 
     * @param array $field_types Field type settings
     * @return bool Success
     */
    public function save_field_type_settings($field_types) {
        $result = update_option('wp_word_cleaner_field_types', $field_types);
        
        if ($result) {
            $this->field_type_settings = $field_types;
            
            // Update cache
            $cache_key = $this->get_cache_key('field_types');
            $this->set_cache($cache_key, $field_types);
        }
        
        return $result;
    }
    
    /**
     * Clear all caches
     */
    public function clear_cache() {
        $this->options = array();
        $this->option_groups = array();
        $this->field_type_settings = null;
        $this->options_loaded = false;
        
        // Delete all object cache keys related to this plugin
        $this->delete_all_caches();
    }
    
    /**
     * Invalidate all caches when settings are updated
     */
    public function invalidate_cache() {
        $this->clear_cache();
    }
    
    /**
     * Get a standardized cache key with version
     *
     * @param string $key The base cache key
     * @return string Versioned cache key
     */
    private function get_cache_key($key) {
        return "wpmsc_{$key}_v{$this->cache_version}";
    }
    
    /**
     * Get a value from cache
     *
     * @param string $key Cache key
     * @return mixed|false The cached value or false on failure
     */
    private function get_cache($key) {
        if (!$this->cache_enabled || !function_exists('wp_cache_get')) {
            return false;
        }
        
        return wp_cache_get($key, $this->cache_group);
    }
    
    /**
     * Set a value in cache
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @return bool Success
     */
    private function set_cache($key, $value) {
        if (!$this->cache_enabled || !function_exists('wp_cache_set')) {
            return false;
        }
        
        return wp_cache_set($key, $value, $this->cache_group, $this->cache_ttl);
    }
    
    /**
     * Delete a value from cache
     *
     * @param string $key Cache key
     * @return bool Success
     */
    private function delete_cache($key) {
        if (!function_exists('wp_cache_delete')) {
            return false;
        }
        
        return wp_cache_delete($key, $this->cache_group);
    }
    
    /**
     * Delete all caches for this plugin
     * Note: This is a best-effort operation since WordPress doesn't provide
     * a native way to delete all keys with a specific prefix
     *
     * @return bool Success
     */
    private function delete_all_caches() {
        if (function_exists('wp_cache_flush_group') && method_exists('WP_Object_Cache', 'flush_group')) {
            // Some caching plugins support group flushing
            return wp_cache_flush_group($this->cache_group);
        } else {
            // Fallback: we're maintaining our own in-memory cache in this object,
            // which has already been cleared by the clear_cache method
            return true;
        }
    }
    
    /**
     * Enable or disable caching
     *
     * @param bool $enabled Whether caching should be enabled
     */
    public function set_cache_enabled($enabled) {
        $this->cache_enabled = (bool) $enabled;
    }
    
    /**
     * Check if caching is enabled
     *
     * @return bool Whether caching is enabled
     */
    public function is_cache_enabled() {
        return $this->cache_enabled;
    }
    
    /**
     * Set cache TTL (time to live)
     *
     * @param int $ttl Cache TTL in seconds
     */
    public function set_cache_ttl($ttl) {
        $this->cache_ttl = (int) $ttl;
    }
    
    /**
     * Get cache TTL
     *
     * @return int Cache TTL in seconds
     */
    public function get_cache_ttl() {
        return $this->cache_ttl;
    }
}