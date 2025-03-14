<?php
/**
 * Plugin Name: WordPress Word Markup Cleaner
 * Description: Automatically cleans Microsoft Word markup from content when saved in WordPress.
 * Version: 3.5
 * Author: Patrick Rittenhouse
 * Author URI: https://pdrittenhouse.com
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-word-markup-cleaner
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Define plugin constants
 */
define('WP_WORD_MARKUP_CLEANER_VERSION', '3.5');
define('WP_WORD_MARKUP_CLEANER_DIR', plugin_dir_path(__FILE__));
define('WP_WORD_MARKUP_CLEANER_URL', plugin_dir_url(__FILE__));

/**
 * The main plugin class to initialize everything
 */
class WP_Word_Markup_Cleaner_Plugin { 
    
    /**
     * Plugin instance
     *
     * @var WP_Word_Markup_Cleaner_Plugin
     */
    private static $instance = null;
    
    /**
     * Settings manager instance
     *
     * @var WP_Word_Markup_Cleaner_Settings_Manager
     */
    private $settings_manager;
    
    /**
     * Settings UI instance
     *
     * @var WP_Word_Markup_Cleaner_Settings
     */
    private $settings;
    
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
    private $content_cleaner;
    
    /**
     * ACF integration instance
     *
     * @var WP_Word_Markup_Cleaner_ACF_Integration
     */
    private $acf_integration;
    
    /**
     * Get plugin instance
     *
     * @return WP_Word_Markup_Cleaner_Plugin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Private constructor to prevent multiple instances
     */
    private function __construct() {
        // Load dependencies
        $this->load_dependencies();

        // Setup assets
        $this->setup_assets();
        
        // Initialize components in the correct order to avoid circular dependencies
        $this->initialize_components();
        
        // Register activation hook
        register_activation_hook(__FILE__, array($this, 'activate'));
        
        // Register deactivation hook
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Add initialization hook
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    /**
     * Load dependencies
     */
    private function load_dependencies() {
        // Include required files in the correct order
        require_once WP_WORD_MARKUP_CLEANER_DIR . 'includes/class-settings-manager.php';
        require_once WP_WORD_MARKUP_CLEANER_DIR . 'includes/class-logger.php';
        require_once WP_WORD_MARKUP_CLEANER_DIR . 'includes/class-content-cleaner.php';
        require_once WP_WORD_MARKUP_CLEANER_DIR . 'includes/class-acf-integration.php';
        require_once WP_WORD_MARKUP_CLEANER_DIR . 'includes/class-settings.php';
    }

    /**
     * Load common assets and set up paths
     */
    private function setup_assets() {
        // Register common action for assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our settings page
        if ('tools_page_word-markup-cleaner' !== $hook) {
            return;
        }
        
        // Version for cache busting
        $version = WP_WORD_MARKUP_CLEANER_VERSION;

        // Add this to your enqueue_scripts function in the plugin
        wp_enqueue_script(
            'wp-word-markup-cleaner-clipboard',
            WP_WORD_MARKUP_CLEANER_URL . 'assets/js/clipboard.js',
            array('jquery'),
            WP_WORD_MARKUP_CLEANER_VERSION,
            true
        );
        
        // Enqueue base CSS
        if (file_exists(WP_WORD_MARKUP_CLEANER_DIR . 'assets/css/settings.css')) {
            wp_enqueue_style(
                'wp-word-markup-cleaner-settings',
                WP_WORD_MARKUP_CLEANER_URL . 'assets/css/settings.css',
                array(),
                $version
            );
        }
        
        // Enqueue logger CSS
        if (file_exists(WP_WORD_MARKUP_CLEANER_DIR . 'assets/css/logger.css')) {
            wp_enqueue_style(
                'wp-word-markup-cleaner-logger',
                WP_WORD_MARKUP_CLEANER_URL . 'assets/css/logger.css',
                array(),
                $version
            );
        }
        
        // Enqueue base JS
        if (file_exists(WP_WORD_MARKUP_CLEANER_DIR . 'assets/js/settings.js')) {
            wp_enqueue_script(
                'wp-word-markup-cleaner-settings',
                WP_WORD_MARKUP_CLEANER_URL . 'assets/js/settings.js',
                array('jquery'),
                $version,
                true
            );
        }
        
        // Enqueue logger JS
        if (file_exists(WP_WORD_MARKUP_CLEANER_DIR . 'assets/js/logger.js')) {
            wp_enqueue_script(
                'wp-word-markup-cleaner-logger',
                WP_WORD_MARKUP_CLEANER_URL . 'assets/js/logger.js',
                array('jquery'),
                $version,
                true
            );
            
            // Pass data to logger script - get debug enabled status directly from options
            $options = get_option('wp_word_cleaner_options', array());
            $debug_enabled = !empty($options['enable_debug']);
            
            wp_localize_script(
                'wp-word-markup-cleaner-logger',
                'wordMarkupLoggerData',
                array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('word_markup_cleaner_log_nonce'),
                    'isDebugEnabled' => $debug_enabled,
                    'logPath' => $this->logger ? $this->logger->get_log_file_path() : '',
                    'maxLogSize' => 5242880, // 5MB
                    'maxLogSizeFormatted' => size_format(5242880)
                )
            );
        }
    }
    
    /**
     * Initialize plugin components in the correct order to avoid circular dependencies
     */
    private function initialize_components() {
        // First, get settings manager (singleton)
        $this->settings_manager = WP_Word_Markup_Cleaner_Settings_Manager::get_instance();
        
        // Next, initialize logger
        $this->logger = new WP_Word_Markup_Cleaner_Logger();
        
        // Set debug status in logger from settings
        $debug_enabled = $this->settings_manager->get_option('enable_debug', false);
        $this->logger->set_debug_enabled($debug_enabled);

        // Initialize content cleaner with logger and settings manager
        $this->content_cleaner = new WP_Word_Markup_Cleaner_Content(
            $this->logger, 
            $this->settings_manager
        );
        
        // Initialize settings UI with all required components
        $this->settings = new WP_Word_Markup_Cleaner_Settings(
            $this->logger,
            $this->content_cleaner,
            $this->settings_manager,
            WP_WORD_MARKUP_CLEANER_DIR,
            WP_WORD_MARKUP_CLEANER_URL
        );
        
        // Initialize ACF integration last
        $this->acf_integration = new WP_Word_Markup_Cleaner_ACF_Integration(
            $this->logger,
            $this->content_cleaner,
            $this->settings_manager
        );
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Get current plugin version
        $current_version = WP_WORD_MARKUP_CLEANER_VERSION;
        
        // Get previously stored version (if any)
        $stored_version = get_option('wp_word_cleaner_version');
        
        // Default options for first install
        $default_options = array(
            'enable_content_cleaning' => 1,
            'enable_acf_cleaning' => 1,
            'protect_tables' => 1,
            'protect_lists' => 1,
            'enable_debug' => 0
        );

        // Default cache options
        $default_cache_options = array(
            'enable_settings_cache' => 1,
            'enable_content_cache' => 1,
            'max_cache_entries' => 100,
            'cache_ttl' => 3600
        );
        
        // Check if this is a new installation or upgrade
        if (!$stored_version) {
            // New installation
            if (!get_option('wp_word_cleaner_options')) {
                update_option('wp_word_cleaner_options', $default_options);
            }

            // Initialize cache options
            if (!get_option('wp_word_cleaner_cache_options')) {
                update_option('wp_word_cleaner_cache_options', $default_cache_options);
            }
                    
            // Initialize the logger instance if not already done
            if (!$this->logger) {
                $this->logger = new WP_Word_Markup_Cleaner_Logger();
            }
            
            // Log new installation if debug is enabled
            $options = get_option('wp_word_cleaner_options');
            if (!empty($options['enable_debug'])) {
                $this->logger->set_debug_enabled(true);
                $this->logger->initialize_log_file();
                $this->logger->log_debug("Plugin activated - New installation version: {$current_version}");
            }
        } else {
            // This is an upgrade
            if (version_compare($stored_version, $current_version, '<')) {
                // Perform version-specific upgrade tasks
                $this->handle_upgrade($stored_version, $current_version);
                
                // Initialize the logger instance if not already done
                if (!$this->logger) {
                    $this->logger = new WP_Word_Markup_Cleaner_Logger();
                }
                
                // Log upgrade if debug is enabled
                $options = get_option('wp_word_cleaner_options');
                if (!empty($options['enable_debug'])) {
                    $this->logger->set_debug_enabled(true);
                    $this->logger->log_debug("Plugin upgraded from {$stored_version} to {$current_version}");
                }
            }
        }
        
        // Update version in database
        update_option('wp_word_cleaner_version', $current_version);
        
        // Add activation timestamp
        update_option('wp_word_cleaner_activated', time());
    }

    /**
     * Handle upgrade tasks when plugin is updated
     *
     * @param string $old_version Previous version
     * @param string $new_version New version
     */
    private function handle_upgrade($old_version, $new_version) {
        // Version-specific upgrade tasks

        // If upgrading to the v3.5 with cache functionality
    if (version_compare($old_version, '3.5', '<')) {
        // Initialize cache options if not already set
        $default_cache_options = array(
            'enable_settings_cache' => 1,
            'enable_content_cache' => 1,
            'max_cache_entries' => 100,
            'cache_ttl' => 3600
        );
        
        if (!get_option('wp_word_cleaner_cache_options')) {
            update_option('wp_word_cleaner_cache_options', $default_cache_options);
        }
        
        // Log upgrade info if debug is enabled
        if (!$this->logger) {
            $this->logger = new WP_Word_Markup_Cleaner_Logger();
        }
        
        $options = get_option('wp_word_cleaner_options');
        if (!empty($options['enable_debug'])) {
            $this->logger->set_debug_enabled(true);
            $this->logger->log_debug("Upgraded to version 3.5: Added caching functionality");
        }
    }

        // If upgrading from before 3.4
        if (version_compare($old_version, '3.4', '<')) {
            // Update content type settings to include strip_all_styles option
            $groups = array('core_types', 'acf_types', 'custom_post_types', 'special_types');
            
            foreach ($groups as $group) {
                $group_settings = get_option("wp_word_cleaner_{$group}", array());
                
                // Add strip_all_styles setting to all content types in this group
                foreach ($group_settings as $type => $settings) {
                    if (!isset($settings['strip_all_styles'])) {
                        $group_settings[$type]['strip_all_styles'] = 0;
                    }
                }
                
                update_option("wp_word_cleaner_{$group}", $group_settings);
            }
            
            // Initialize logger if needed
            if (!$this->logger) {
                $this->logger = new WP_Word_Markup_Cleaner_Logger();
            }
            
            $options = get_option('wp_word_cleaner_options');
            if (!empty($options['enable_debug'])) {
                $this->logger->set_debug_enabled(true);
                $this->logger->log_debug("Upgraded to version 3.4: Added 'strip_all_styles' option to all content types");
            }
        }
        
        // If upgrading from before 3.0
        if (version_compare($old_version, '3.0', '<')) {
            // Perform 3.0-specific upgrades
            $options = get_option('wp_word_cleaner_options', array());
            
            // Add any new options introduced in 3.0
            $options = wp_parse_args($options, array(
                'new_option_in_v3' => 1
            ));
            
            update_option('wp_word_cleaner_options', $options);
            
            // Maybe perform database updates or other migration tasks
        }
        
        // If upgrading from before 3.1
        if (version_compare($old_version, '3.2', '<')) {
            // Create default field type settings
            if (!get_option('wp_word_cleaner_field_types')) {
                $default_field_types = array(
                    'text_field_types' => array('text', 'textarea', 'wysiwyg', 'url', 'email'),
                    'container_field_types' => array('repeater', 'group', 'flexible_content')
                );
                
                update_option('wp_word_cleaner_field_types', $default_field_types);
            }
        }

        // If upgrading from before 3.3
        if (version_compare($old_version, '3.3', '<')) {
            // Add default settings for ACF Blocks
            $content_type_group = 'acf_types';
            $group_settings = get_option("wp_word_cleaner_{$content_type_group}", array());
            
            // Add default settings for block content types if they don't exist
            if (!isset($group_settings['acf_block_field']) && !isset($group_settings['acf_block_content'])) {
                $block_settings = array(
                    'xml_namespaces' => 1,
                    'conditional_comments' => 1,
                    'mso_classes' => 1,
                    'mso_styles' => 1,
                    'font_attributes' => 1,
                    'style_attributes' => 1,
                    'lang_attributes' => 1,
                    'empty_elements' => 1,
                    'protect_tables' => 1,
                    'protect_lists' => 1
                );
                
                $group_settings['acf_block_field'] = $block_settings;
                $group_settings['acf_block_content'] = $block_settings;
                
                update_option("wp_word_cleaner_{$content_type_group}", $group_settings);
            }
            
            // Initialize logger if needed
            if (!$this->logger) {
                $this->logger = new WP_Word_Markup_Cleaner_Logger();
            }
            
            $options = get_option('wp_word_cleaner_options');
            if (!empty($options['enable_debug'])) {
                $this->logger->set_debug_enabled(true);
                $this->logger->log_debug("Upgraded to version 3.3: Added ACF Blocks support settings");
            }
        }
        
        // You can add more version checks as the plugin evolves
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Initialize logger if needed
        if (!$this->logger) {
            $this->logger = new WP_Word_Markup_Cleaner_Logger();
        }
        
        // Log deactivation if debug is enabled
        $options = get_option('wp_word_cleaner_options');
        if (!empty($options['enable_debug'])) {
            $this->logger->set_debug_enabled(true);
            $this->logger->log_debug("Plugin deactivated");
        }

        // Don't remove the version option so we know it was previously installed
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain for internationalization
        load_plugin_textdomain(
            'wp-word-markup-cleaner',
            false,
            basename(dirname(__FILE__)) . '/languages'
        );
        
        // Initialize logger if needed
        if (!$this->logger) {
            $this->logger = new WP_Word_Markup_Cleaner_Logger();
        }
        
        // Log plugin initialization if debug is enabled
        $options = get_option('wp_word_cleaner_options');
        if (!empty($options['enable_debug'])) {
            $this->logger->set_debug_enabled(true);
            $this->logger->log_debug("Plugin initialized");
        }
    }
    
    /**
     * Get settings manager instance
     *
     * @return WP_Word_Markup_Cleaner_Settings_Manager
     */
    public function get_settings_manager() {
        return $this->settings_manager;
    }
    
    /**
     * Get logger instance
     *
     * @return WP_Word_Markup_Cleaner_Logger
     */
    public function get_logger() {
        return $this->logger;
    }
    
    /**
     * Get content cleaner instance
     *
     * @return WP_Word_Markup_Cleaner_Content
     */
    public function get_content_cleaner() {
        return $this->content_cleaner;
    }
    
    /**
     * Get ACF integration instance
     *
     * @return WP_Word_Markup_Cleaner_ACF_Integration
     */
    public function get_acf_integration() {
        return $this->acf_integration;
    }
    
    /**
     * Get settings instance
     *
     * @return WP_Word_Markup_Cleaner_Settings
     */
    public function get_settings() {
        return $this->settings;
    }
}

// Initialize the plugin
$wp_word_markup_cleaner = WP_Word_Markup_Cleaner_Plugin::get_instance();