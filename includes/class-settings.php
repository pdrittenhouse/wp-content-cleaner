<?php

/**
 * Settings management for the Word Markup Cleaner plugin
 *
 * @package WordPress_Word_Markup_Cleaner
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class WP_Word_Markup_Cleaner_Settings
 * 
 * Handles all admin settings, menus and options pages for the plugin
 */
class WP_Word_Markup_Cleaner_Settings
{

    /**
     * Plugin options
     *
     * @var array
     */
    private $options;

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
     * Plugin base directory path
     *
     * @var string
     */
    private $plugin_dir;

    /**
     * Plugin base URL
     *
     * @var string
     */
    private $plugin_url;

    /**
     * Loaded option groups cache
     *
     * @var array
     */
    private $loaded_option_groups = array();

    /**
     * Initialize the settings
     *
     * @param WP_Word_Markup_Cleaner_Logger $logger Logger instance
     * @param WP_Word_Markup_Cleaner_Content $cleaner Content cleaner instance
     * @param string $plugin_dir Plugin directory path
     * @param string $plugin_url Plugin URL
     */
    public function __construct($logger, $cleaner, $plugin_dir, $plugin_url)
    {
        $this->logger = $logger;
        $this->cleaner = $cleaner;
        $this->plugin_dir = $plugin_dir;
        $this->plugin_url = $plugin_url;
        $this->maybe_upgrade_options_storage();

        // Set up default options
        $default_options = array(
            'enable_content_cleaning' => 1,
            'enable_acf_cleaning' => 1,
            'protect_tables' => 1,
            'protect_lists' => 1,
            'enable_debug' => 0
        );

        // Get options with defaults for any missing values
        $this->options = wp_parse_args(
            get_option('wp_word_cleaner_options', array()),
            $default_options
        );

        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Register settings
        add_action('admin_init', array($this, 'register_settings'));

        // Add admin notice for debugging if enabled
        if ($this->options['enable_debug']) {
            add_action('admin_notices', array($this, 'debug_notice'));
        }

        // Log initialization if debug is enabled
        if ($this->options['enable_debug']) {
            $this->logger->log_debug("Settings initialized with settings: " . json_encode($this->options));
        }
    }

    /**
     * Get option group key for a content type
     * 
     * @param string $type Content type identifier
     * @return string Option group key
     */
    private function get_option_group_for_type($type)
    {
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
    private function load_option_group($group)
    {
        if (!isset($this->loaded_option_groups[$group])) {
            $option_key = "wp_word_cleaner_{$group}";
            $this->loaded_option_groups[$group] = get_option($option_key, array());
        }
        return $this->loaded_option_groups[$group];
    }

    /**
     * Save settings for a specific content type group
     * 
     * @param string $group Group identifier
     * @param array $settings Group settings
     */
    private function save_option_group($group, $settings)
    {
        $option_key = "wp_word_cleaner_{$group}";
        update_option($option_key, $settings);
        $this->loaded_option_groups[$group] = $settings;
    }

    /**
     * Add a database version check and migration
     */
    private function maybe_upgrade_options_storage()
    {
        $db_version = get_option('wp_word_cleaner_db_version', '1.0');

        if (version_compare($db_version, '1.1', '<')) {
            // Migrate from single option to grouped options
            $old_options = get_option('wp_word_cleaner_options', array());

            if (isset($old_options['content_types']) && is_array($old_options['content_types'])) {
                $grouped_settings = array();

                foreach ($old_options['content_types'] as $type => $settings) {
                    $group = $this->get_option_group_for_type($type);

                    if (!isset($grouped_settings[$group])) {
                        $grouped_settings[$group] = array();
                    }

                    $grouped_settings[$group][$type] = $settings;
                }

                // Save each group
                foreach ($grouped_settings as $group => $settings) {
                    $this->save_option_group($group, $settings);
                }

                // Remove content_types from main options
                unset($old_options['content_types']);
                update_option('wp_word_cleaner_options', $old_options);
            }

            update_option('wp_word_cleaner_db_version', '1.1');
        }
    }

    /**
     * Add plugin admin menu
     */
    public function add_admin_menu()
    {
        add_management_page(
            'Word Markup Cleaner',
            'Word Markup Cleaner',
            'manage_options',
            'word-markup-cleaner',
            array($this, 'admin_page')
        );
    }

    /**
     * Register additional ACF field type settings
     */
    public function register_acf_field_type_settings()
    {
        // Register setting for field type configuration
        register_setting(
            'wp_word_cleaner',
            'wp_word_cleaner_field_types',
            array($this, 'sanitize_field_types')
        );

        // Add ACF field types section
        add_settings_section(
            'wp_word_cleaner_acf_field_types',
            'ACF Field Type Settings',
            array($this, 'acf_field_types_section_callback'),
            'wp_word_cleaner'
        );

        // Add field for configuring text field types
        add_settings_field(
            'acf_text_field_types',
            'Text Field Types',
            array($this, 'acf_text_field_types_callback'),
            'wp_word_cleaner',
            'wp_word_cleaner_acf_field_types'
        );

        // Add field for configuring container field types
        add_settings_field(
            'acf_container_field_types',
            'Container Field Types',
            array($this, 'acf_container_field_types_callback'),
            'wp_word_cleaner',
            'wp_word_cleaner_acf_field_types'
        );
    }

    /**
     * ACF field types section callback
     */
    public function acf_field_types_section_callback()
    {
        echo '<p>Configure which ACF field types will be processed by the Word Markup Cleaner. ' .
            'This helps optimize performance by only cleaning field types that may contain Word markup.</p>';
    }

    /**
     * Text field types callback
     */
    public function acf_text_field_types_callback()
    {
        // Get current settings
        $field_types = get_option('wp_word_cleaner_field_types', array());
        $text_field_types = isset($field_types['text_field_types']) ? $field_types['text_field_types'] : array();

        // Default text field types
        $default_text_field_types = array(
            'text' => 'Text',
            'textarea' => 'Text Area',
            'wysiwyg' => 'WYSIWYG Editor',
            'url' => 'URL',
            'email' => 'Email'
        );

        echo '<p class="description">Select which text-based field types should be processed:</p>';
        echo '<div class="field-type-checkboxes">';

        foreach ($default_text_field_types as $type => $label) {
            $checked = in_array($type, $text_field_types) || empty($text_field_types);

            echo '<label>';
            echo '<input type="checkbox" name="wp_word_cleaner_field_types[text_field_types][]" value="' . esc_attr($type) . '" ' .
                checked($checked, true, false) . '>';
            echo esc_html($label);
            echo '</label><br>';
        }

        echo '</div>';

        // Allow for custom text field types
        echo '<p><label for="custom_text_field_types">Additional text field types (comma-separated):</label></p>';

        $custom_text_field_types = isset($field_types['custom_text_field_types']) ? $field_types['custom_text_field_types'] : '';

        echo '<input type="text" id="custom_text_field_types" name="wp_word_cleaner_field_types[custom_text_field_types]" ' .
            'value="' . esc_attr($custom_text_field_types) . '" class="regular-text">';

        echo '<p class="description">Specify any custom text field types that should be included in cleaning operations.</p>';
    }

    /**
     * Container field types callback
     */
    public function acf_container_field_types_callback()
    {
        // Get current settings
        $field_types = get_option('wp_word_cleaner_field_types', array());
        $container_field_types = isset($field_types['container_field_types']) ? $field_types['container_field_types'] : array();

        // Default container field types
        $default_container_field_types = array(
            'repeater' => 'Repeater',
            'group' => 'Group',
            'flexible_content' => 'Flexible Content'
        );

        echo '<p class="description">Select which container field types should be processed:</p>';
        echo '<div class="field-type-checkboxes">';

        foreach ($default_container_field_types as $type => $label) {
            $checked = in_array($type, $container_field_types) || empty($container_field_types);

            echo '<label>';
            echo '<input type="checkbox" name="wp_word_cleaner_field_types[container_field_types][]" value="' . esc_attr($type) . '" ' .
                checked($checked, true, false) . '>';
            echo esc_html($label);
            echo '</label><br>';
        }

        echo '</div>';

        // Allow for custom container field types
        echo '<p><label for="custom_container_field_types">Additional container field types (comma-separated):</label></p>';

        $custom_container_field_types = isset($field_types['custom_container_field_types']) ? $field_types['custom_container_field_types'] : '';

        echo '<input type="text" id="custom_container_field_types" name="wp_word_cleaner_field_types[custom_container_field_types]" ' .
            'value="' . esc_attr($custom_container_field_types) . '" class="regular-text">';

        echo '<p class="description">Specify any custom container field types that should be included in cleaning operations.</p>';
    }

    /**
     * Sanitize field types settings
     * 
     * @param array $input The input options
     * @return array The sanitized options
     */
    public function sanitize_field_types($input)
    {
        $output = array();

        // Sanitize text field types
        if (isset($input['text_field_types']) && is_array($input['text_field_types'])) {
            $output['text_field_types'] = array_map('sanitize_text_field', $input['text_field_types']);
        } else {
            $output['text_field_types'] = array();
        }

        // Sanitize container field types
        if (isset($input['container_field_types']) && is_array($input['container_field_types'])) {
            $output['container_field_types'] = array_map('sanitize_text_field', $input['container_field_types']);
        } else {
            $output['container_field_types'] = array();
        }

        // Sanitize custom text field types
        if (isset($input['custom_text_field_types'])) {
            $custom_text_field_types = sanitize_text_field($input['custom_text_field_types']);
            $output['custom_text_field_types'] = $custom_text_field_types;

            // Add custom text field types to the text_field_types array
            if (!empty($custom_text_field_types)) {
                $custom_types = array_map('trim', explode(',', $custom_text_field_types));
                $output['text_field_types'] = array_merge($output['text_field_types'], $custom_types);
            }
        }

        // Sanitize custom container field types
        if (isset($input['custom_container_field_types'])) {
            $custom_container_field_types = sanitize_text_field($input['custom_container_field_types']);
            $output['custom_container_field_types'] = $custom_container_field_types;

            // Add custom container field types to the container_field_types array
            if (!empty($custom_container_field_types)) {
                $custom_types = array_map('trim', explode(',', $custom_container_field_types));
                $output['container_field_types'] = array_merge($output['container_field_types'], $custom_types);
            }
        }

        return $output;
    }

    /**
     * Add ACF field type settings section
     */
    private function add_acf_field_type_section()
    {
        // Only show if ACF is active AND ACF cleaning is enabled
        $acf_integration = null;
        $options = get_option('wp_word_cleaner_options', array());
        $acf_cleaning_enabled = !empty($options['enable_acf_cleaning']);

        if (!$acf_cleaning_enabled) {
            return; // Don't show ACF settings if ACF cleaning is disabled
        }

        if (isset($GLOBALS['wp_word_markup_cleaner'])) {
            $plugin = $GLOBALS['wp_word_markup_cleaner'];
            $acf_integration = $plugin->get_acf_integration();
        }

        if ($acf_integration && $acf_integration->is_acf_active()) {
            // Get field type settings
            $field_types = get_option('wp_word_cleaner_field_types', array());
            $text_field_types = isset($field_types['text_field_types']) ? $field_types['text_field_types'] : array('text', 'textarea', 'wysiwyg', 'url', 'email');
            $container_field_types = isset($field_types['container_field_types']) ? $field_types['container_field_types'] : array('repeater', 'group', 'flexible_content');
            $custom_text_field_types = isset($field_types['custom_text_field_types']) ? $field_types['custom_text_field_types'] : '';
            $custom_container_field_types = isset($field_types['custom_container_field_types']) ? $field_types['custom_container_field_types'] : '';

            // Default field types
            $default_text_field_types = array(
                'text' => 'Text',
                'textarea' => 'Text Area',
                'wysiwyg' => 'WYSIWYG Editor',
                'url' => 'URL',
                'email' => 'Email'
            );

            $default_container_field_types = array(
                'repeater' => 'Repeater',
                'group' => 'Group',
                'flexible_content' => 'Flexible Content'
            );
?>
            <div class="postbox">
                <h2 class="postbox-header">ACF Field Type Settings</h2>
                <div class="inside">
                    <p>Configure which ACF field types will be processed by the Word Markup Cleaner. This helps optimize performance by only cleaning field types that may contain Word markup.</p>

                    <div class="acf-field-type-info">
                        <h3>Field Type Processing Information</h3>
                        <p>The Word Markup Cleaner processes the following ACF field types:</p>

                        <div class="layout-cols">
                            <div>
                                <h4>Text-based Fields</h4>
                                <p>These field types may contain direct Word markup and are cleaned directly:</p>
                                <ul class="field-type-list text-field-types">
                                    <?php foreach ($text_field_types as $type): ?>
                                        <li><?php echo esc_html(ucfirst($type)); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <div>
                                <h4>Container Fields</h4>
                                <p>These field types may contain nested text fields that need cleaning:</p>
                                <ul class="field-type-list container-field-types">
                                    <?php foreach ($container_field_types as $type): ?>
                                        <li><?php echo esc_html(ucfirst(str_replace('_', ' ', $type))); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <form method="post" action="options.php">
                        <?php settings_fields('wp_word_cleaner_field_types'); ?>

                        <div class="layout-cols">
                            <div>
                                <h3>Text Field Types</h3>
                                <p class="description">Select which text-based field types should be processed:</p>
                                <div class="field-type-checkboxes">
                                    <?php foreach ($default_text_field_types as $type => $label):
                                        $checked = in_array($type, $text_field_types) || empty($text_field_types);
                                    ?>
                                        <label>
                                            <input type="checkbox" name="wp_word_cleaner_field_types[text_field_types][]" value="<?php echo esc_attr($type); ?>" <?php checked($checked); ?>>
                                            <?php echo esc_html($label); ?>
                                        </label><br>
                                    <?php endforeach; ?>
                                </div>

                                <h4><label for="custom_text_field_types">Additional text field types (comma-separated):</label></h4>
                                <input type="text" id="custom_text_field_types" name="wp_word_cleaner_field_types[custom_text_field_types]" value="<?php echo esc_attr($custom_text_field_types); ?>" class="regular-text">
                            </div>

                            <div>
                                <h3>Container Field Types</h3>
                                <p class="description">Select which container field types should be processed:</p>
                                <div class="field-type-checkboxes">
                                    <?php foreach ($default_container_field_types as $type => $label):
                                        $checked = in_array($type, $container_field_types) || empty($container_field_types);
                                    ?>
                                        <label>
                                            <input type="checkbox" name="wp_word_cleaner_field_types[container_field_types][]" value="<?php echo esc_attr($type); ?>" <?php checked($checked); ?>>
                                            <?php echo esc_html($label); ?>
                                        </label><br>
                                    <?php endforeach; ?>
                                </div>

                                <h4><label for="custom_container_field_types">Additional container field types (comma-separated):</label></h4>
                                <input type="text" id="custom_container_field_types" name="wp_word_cleaner_field_types[custom_container_field_types]" value="<?php echo esc_attr($custom_container_field_types); ?>" class="regular-text">
                            </div>
                        </div>

                        <?php submit_button('Save Field Type Settings'); ?>
                    </form>
                </div>
            </div>
        <?php
        }
    }

    /**
     * Add settings for content type cleaning
     */
    public function register_settings()
    {
        register_setting(
            'wp_word_cleaner',
            'wp_word_cleaner_options',
            array($this, 'sanitize_options')
        );

        add_settings_section(
            'wp_word_cleaner_main',
            '',
            array($this, 'settings_section_callback'),
            'wp_word_cleaner'
        );

        add_settings_field(
            'use_dom_processing',
            'Use DOM-based Processing',
            array($this, 'checkbox_field_callback'),
            'wp_word_cleaner',
            'wp_word_cleaner_main',
            array('label_for' => 'use_dom_processing')
        );

        add_settings_field(
            'enable_content_cleaning',
            'Enable Content Cleaning',
            array($this, 'checkbox_field_callback'),
            'wp_word_cleaner',
            'wp_word_cleaner_main',
            array('label_for' => 'enable_content_cleaning')
        );

        add_settings_field(
            'enable_acf_cleaning',
            'Enable ACF Fields Cleaning',
            array($this, 'checkbox_field_callback'),
            'wp_word_cleaner',
            'wp_word_cleaner_main',
            array('label_for' => 'enable_acf_cleaning')
        );

        // Register cache settings
        $this->register_cache_settings();

        add_settings_field(
            'enable_debug',
            'Enable Debug Logging',
            array($this, 'checkbox_field_callback'),
            'wp_word_cleaner',
            'wp_word_cleaner_main',
            array('label_for' => 'enable_debug')
        );

        // Add content type specific settings
        add_settings_field(
            'content_type_settings',
            'Content Type Cleaning Settings',
            array($this, 'content_type_settings_callback'),
            'wp_word_cleaner',
            'wp_word_cleaner_main'
        );

        // Register ACF field type settings as a separate option
        register_setting(
            'wp_word_cleaner_field_types',
            'wp_word_cleaner_field_types',
            array($this, 'sanitize_field_types')
        );
    }

    /**
     * Sanitize options before saving
     * 
     * @param array $input The input options
     * @return array The sanitized options
     */
    public function sanitize_options($input)
    {
        $output = array();

        // Log the incoming options data
        if (!empty($this->options['enable_debug'])) {
            $this->logger->log_debug("SANITIZING OPTIONS - INPUT: " . json_encode($input));
        }

        // Ensure we have default values
        $defaults = array(
            'enable_content_cleaning' => 0,
            'enable_acf_cleaning' => 0,
            'protect_tables' => 0,
            'protect_lists' => 0,
            'enable_debug' => 0,
            'use_dom_processing' => 1
        );

        // Sanitize each option - important to check if isset before setting to 1
        foreach ($defaults as $key => $default_value) {
            $output[$key] = isset($input[$key]) ? 1 : 0;
        }

        // First, load all existing option groups
        $group_keys = array('core_types', 'acf_types', 'custom_post_types', 'special_types');
        $existing_groups = array();

        foreach ($group_keys as $group) {
            $existing_groups[$group] = $this->load_option_group($group);
        }

        // Process content type settings by group
        if (isset($input['content_types']) && is_array($input['content_types'])) {
            $grouped_settings = array();

            // Group content types
            foreach ($input['content_types'] as $type => $settings) {
                $sanitized_type = sanitize_text_field($type);
                $group = $this->get_option_group_for_type($sanitized_type);

                if (!isset($grouped_settings[$group])) {
                    $grouped_settings[$group] = array();
                }

                // Sanitize settings
                if (is_array($settings)) {
                    $all_settings = array(
                        'xml_namespaces',
                        'conditional_comments',
                        'mso_classes',
                        'mso_styles',
                        'font_attributes',
                        'style_attributes',
                        'lang_attributes',
                        'empty_elements',
                        'protect_tables',
                        'protect_lists',
                        'strip_all_html'
                    );

                    $sanitized_settings = array();

                    // Initialize all settings to 0
                    foreach ($all_settings as $setting_key) {
                        $sanitized_settings[$setting_key] = 0;
                    }

                    // Set submitted settings to 1
                    foreach ($settings as $setting => $value) {
                        $sanitized_setting = sanitize_text_field($setting);
                        $sanitized_settings[$sanitized_setting] = !empty($value) ? 1 : 0;
                    }

                    $grouped_settings[$group][$sanitized_type] = $sanitized_settings;
                }
            }

            // Merge with existing settings and save each group
            foreach ($group_keys as $group) {
                $group_settings = isset($grouped_settings[$group]) ? $grouped_settings[$group] : array();

                // If we have settings for this group, merge with existing and save
                if (!empty($group_settings)) {
                    if (!empty($this->options['enable_debug'])) {
                        $this->logger->log_debug("SAVING GROUP SETTINGS FOR: $group - " . json_encode($group_settings));
                    }
                    $merged_settings = array_merge($existing_groups[$group], $group_settings);
                    $this->save_option_group($group, $merged_settings);
                }
            }
        }

        // Log the final output
        if (!empty($this->options['enable_debug']) || !empty($output['enable_debug'])) {
            $this->logger->log_debug("SANITIZED OPTIONS - OUTPUT: " . json_encode($output));
        }

        return $output;
    }

    /**
     * Render content type settings
     */
    public function content_type_settings_callback()
    {
        $content_types = array(
            'post' => 'Posts',
            'page' => 'Pages',
            'wp_content' => 'Generic WordPress Content',
            'acf_wysiwyg' => 'ACF WYSIWYG Fields',
            'acf_text' => 'ACF Text Fields',
            'acf_textarea' => 'ACF Textarea Fields',
            'excerpt' => 'Excerpts'
        );

        // Add custom post types
        $post_types = get_post_types(array('_builtin' => false), 'objects');
        foreach ($post_types as $post_type) {
            $content_types[$post_type->name] = $post_type->label;
        }

        echo '<div class="content-type-settings">';
        echo '<p class="description">Configure specific cleaning operations for each content type. These settings override the default cleaning behavior.</p>';

        // Dropdown for content type selection
        echo '<div class="content-type-selector">';
        echo '<label for="content-type-select">Select Content Type: </label>';
        echo '<select id="content-type-select" class="content-type-select">';
        foreach ($content_types as $type => $label) {
            echo '<option value="' . esc_attr($type) . '">' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        // Content panels
        echo '<div class="content-panels">';
        foreach ($content_types as $type => $label) {
            echo '<div id="panel-' . esc_attr($type) . '" class="content-panel" style="display: none;">';
            $this->render_content_type_settings($type, $label);
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render settings for a specific content type
     * 
     * @param string $type Content type key
     * @param string $label Content type label
     */
    private function render_content_type_settings($type, $label)
    {
        // Get type settings using new method instead of direct access
        $settings = $this->get_content_type_settings($type);

        // Get default cleaning levels for this type
        $defaults = $this->get_default_cleaning_levels($type);

        echo '<h4>' . esc_html($label) . ' Cleaning Settings</h4>';
        echo '<p>Select which cleaning operations to apply to ' . esc_html($label) . ':</p>';

        echo '<table class="form-table cleaning-options">';

        // Render checkboxes for each cleaning level
        foreach ($this->get_cleaning_levels_descriptions() as $key => $description) {
            $default = isset($defaults[$key]) ? $defaults[$key] : false;
            $checked = isset($settings[$key]) ? $settings[$key] : $default;

            echo '<tr>';
            echo '<th><label for="' . esc_attr("setting-{$type}-{$key}") . '">' . $this->get_cleaning_level_label($key) . '</label></th>';
            echo '<td><input type="checkbox" id="' . esc_attr("setting-{$type}-{$key}") . '" name="wp_word_cleaner_options[content_types][' . esc_attr($type) . '][' . esc_attr($key) . ']" value="1" ' . checked($checked, true, false) . '></td>';
            echo '</tr>';
            echo '<tr class="description-row">';
            echo '<td colspan="2" class="setting-description"><em>' . esc_html($description) . '</em></td>';
            echo '</tr>';
        }

        echo '</table>';

        // Add special options for certain content types
        if ($type === 'excerpt') {
            // Get strip_html from new settings
            $strip_html = isset($settings['strip_all_html']) ? $settings['strip_all_html'] : true;
            echo '<p><label><input type="checkbox" name="wp_word_cleaner_options[content_types][' . esc_attr($type) . '][strip_all_html]" value="1" ' . checked($strip_html, true, false) . '> Strip all HTML from excerpts</label> <span class="description">Recommended for most themes</span></p>';
        }
    }

    /**
     * Get settings for a specific content type
     * 
     * @param string $type Content type identifier
     * @return array Content type settings
     */
    public function get_content_type_settings($type)
    {
        $group = $this->get_option_group_for_type($type);
        $group_settings = $this->load_option_group($group);

        if (isset($group_settings[$type])) {
            return $group_settings[$type];
        }

        // Return defaults if no specific settings found
        return $this->get_default_cleaning_levels($type);
    }

    /**
     * Set content cleaner reference
     *
     * @param WP_Word_Markup_Cleaner_Content $cleaner Content cleaner instance
     */
    public function set_content_cleaner($cleaner)
    {
        $this->cleaner = $cleaner;
    }

    /**
     * Get default cleaning levels for a content type
     * 
     * @param string $content_type Content type identifier
     * @return array Default cleaning levels
     */
    private function get_default_cleaning_levels($content_type)
    {
        switch ($content_type) {
            // WordPress core content types
            case 'post':
            case 'page':
            case 'wp_content':
                return array(
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

                // ACF field types
            case 'acf_wysiwyg':
                return array(
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

            case 'acf_text':
            case 'acf_textarea':
                return array(
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

            case 'acf_block_field':
            case 'acf_block_content':
                return array(
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

                // WordPress excerpts
            case 'excerpt':
                return array(
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

            default:
                return array(
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
        }
    }

    /**
     * Get cleaning level labels
     * 
     * @param string $key Cleaning level key
     * @return string Label
     */
    private function get_cleaning_level_label($key)
    {
        $labels = array(
            'use_dom_processing' => 'Use DOM-based Processing',
            'xml_namespaces' => 'XML Namespaces',
            'conditional_comments' => 'Conditional Comments',
            'mso_classes' => 'MSO Classes',
            'mso_styles' => 'MSO Styles',
            'font_attributes' => 'Font Attributes',
            'style_attributes' => 'Style Attributes',
            'lang_attributes' => 'Language Attributes',
            'empty_elements' => 'Empty Elements',
            'protect_tables' => 'Protect Tables',
            'protect_lists' => 'Protect Lists',
            'strip_all_styles' => 'Strip All  Styles',
            'strip_all_html' => 'Strip All HTML'
        );

        return isset($labels[$key]) ? $labels[$key] : ucfirst(str_replace('_', ' ', $key));
    }

    /**
     * Get cleaning levels descriptions
     * 
     * @return array Descriptions for each cleaning level
     */
    private function get_cleaning_levels_descriptions()
    {
        return array(
            'use_dom_processing' => 'Process content using DOM-based element cleaning for more targeted results. When disabled, uses regex-based approach.',
            'xml_namespaces' => 'Remove Word-specific XML namespaces and tags',
            'conditional_comments' => 'Remove Word conditional comments',
            'mso_classes' => 'Remove MSO-specific CSS classes',
            'mso_styles' => 'Remove MSO-specific inline styles',
            'font_attributes' => 'Clean font family, size and style attributes',
            'style_attributes' => 'Remove style attributes from HTML tags',
            'lang_attributes' => 'Remove language attributes from HTML tags',
            'empty_elements' => 'Remove empty spans and other unnecessary elements',
            'protect_tables' => 'Preserve table structure while cleaning',
            'protect_lists' => 'Preserve list structure while cleaning',
            'strip_all_styles' => 'Remove all style attributes from all elements'
        );
    }

    /**
     * Settings section callback
     */
    public function settings_section_callback()
    {
        echo '<p>Configure the Word Markup Cleaner plugin settings.</p>';
    }

    /**
     * Checkbox field callback
     */
    public function checkbox_field_callback($args)
    {
        $field_id = $args['label_for'];
        $checked = isset($this->options[$field_id]) ? $this->options[$field_id] : 0;

        echo '<input type="checkbox" id="' . esc_attr($field_id) . '" name="wp_word_cleaner_options[' . esc_attr($field_id) . ']" value="1" ' . checked(1, $checked, false) . '>';

        // Add descriptions for each option
        switch ($field_id) {
            case 'enable_content_cleaning':
                echo '<p class="description">Enable cleaning of Microsoft Word markup from post content.</p>';
                break;
            case 'use_dom_processing':
                echo '<p class="description">Enable DOM-based element processing for more efficient cleaning. This processes only elements containing Word markup.</p>';
                break;
            case 'enable_acf_cleaning':
                echo '<p class="description">Enable cleaning of Microsoft Word markup from Advanced Custom Fields.</p>';
                break;
            case 'protect_tables':
                echo '<p class="description">Preserve table structure while cleaning Word markup.</p>';
                break;
            case 'protect_lists':
                echo '<p class="description">Preserve list structure while cleaning Word markup.</p>';
                break;
            case 'enable_debug':
                echo '<p class="description">Log detailed information about the cleaning process.</p>';
                break;
            case 'use_dom_processing':
                echo '<p class="description">Enable DOM-based element processing for more efficient cleaning. This processes only elements containing Word markup (recommended for most sites).</p>';
                break;
        }
    }

    /**
     * Admin page callback with tab-based layout
     */
    public function admin_page()
    {
        // Process cache clearing action if submitted
        $this->process_clear_cache_action();

        // Get the previous debug setting from transient
        $previous_debug_state = get_transient('wp_word_cleaner_previous_debug_state');

        // Check for log clearing action first - ONLY when clear_log parameter is present
        if (isset($_GET['clear_log']) && $_GET['clear_log'] == 1 && current_user_can('manage_options')) {
            // Clear the log file
            $this->logger->clear_log_file();

            // Show success message
            echo '<div class="notice notice-success is-dismissible"><p>Log file cleared.</p></div>';

            // Redirect to remove the clear_log parameter from URL to prevent clearing on refresh
            echo '<script>window.history.replaceState({}, document.title, "' .
                admin_url('tools.php?page=word-markup-cleaner') . '");</script>';
        }

        // Get the latest options to ensure we have the current debug setting
        $this->options = get_option('wp_word_cleaner_options', array());
        $debug_enabled = isset($this->options['enable_debug']) ? (bool)$this->options['enable_debug'] : false;

        // Check if debug was just enabled
        $debug_just_enabled = false;
        if ($debug_enabled && $previous_debug_state === false) {
            $debug_just_enabled = true;
        }

        // Store current debug state for next page load
        set_transient('wp_word_cleaner_previous_debug_state', $debug_enabled, DAY_IN_SECONDS);

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <!-- Tab Navigation -->
            <div class="word-markup-tabs nav-tab-wrapper" data-debug-enabled="<?php echo $debug_enabled ? 'true' : 'false'; ?>" data-debug-just-enabled="<?php echo $debug_just_enabled ? 'true' : 'false'; ?>">
                <a href="#tab-about" class="nav-tab">About</a>
                <a href="#tab-settings" class="nav-tab">Settings</a>
                <?php if (isset($GLOBALS['wp_word_markup_cleaner'])) :
                    $plugin = $GLOBALS['wp_word_markup_cleaner'];
                    $acf_integration = $plugin->get_acf_integration();
                    if ($acf_integration && $acf_integration->is_acf_active() && !empty($this->options['enable_acf_cleaning'])) : ?>
                        <a href="#tab-acf" class="nav-tab">ACF Settings</a>
                    <?php endif; ?>
                <?php endif; ?>
                <a href="#tab-cache" class="nav-tab">Cache</a>
                <a href="#tab-test" class="nav-tab">Test Cleaner</a>
                <?php if ($debug_enabled) : ?>
                    <a href="#tab-debug" class="nav-tab">Debug Log</a>
                <?php endif; ?>
            </div>

            <!-- Tab Content Areas -->
            <div class="word-markup-tabs-container">
                <!-- About Tab -->
                <div id="tab-about" class="word-markup-tab-content">
                    <div class="postbox">
                        <h2 class="postbox-header">About This Plugin</h2>
                        <div class="inside">
                            <p>WordPress Word Markup Cleaner automatically removes Microsoft Word formatting and markup from your content when it's saved. This helps maintain clean, consistent HTML throughout your site.</p>
                            <div class="layout-cols">
                                <div>
                                    <h3><strong>Key Benefits:</strong></h3>
                                    <ul>
                                        <li>Removes unnecessary Microsoft Word markup and attributes</li>
                                        <li>Preserves the structure of tables and lists</li>
                                        <li>Works with regular post content and Advanced Custom Fields</li>
                                        <li>Maintains clean HTML without manual editing</li>
                                    </ul>
                                </div>
                                <div>
                                    <h3><strong>Fields That Are Cleaned:</strong></h3>
                                    <ul>
                                        <li>Standard WordPress content</li>
                                        <li>ACF text fields</li>
                                        <li>ACF textarea fields</li>
                                        <li>ACF WYSIWYG fields</li>
                                        <li>ACF flexible content fields (all text-based sub-fields)</li>
                                        <li>ACF repeater fields (all text-based sub-fields)</li>
                                        <li>ACF blocks in Gutenberg editor</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="tips-box">
                                <h4><strong>Tips for Content Team:</strong></h4>
                                <p>Even with this plugin, the best practice is to use the "Paste as text" option in WordPress when copying from Word. This plugin serves as a safety net when that step is forgotten.</p>
                                <p><strong>Keyboard shortcut:</strong> Paste as text using <code>Ctrl+Shift+V</code> (Windows) or <code>Cmd+Shift+V</code> (Mac).</p>
                                <p><strong>Important:</strong> Always inspect your content after saving to ensure the cleaning process preserved all necessary formatting and content structure.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Settings Tab -->
                <div id="tab-settings" class="word-markup-tab-content">
                    <div class="postbox">
                        <h2 class="postbox-header">Plugin Settings</h2>
                        <div class="inside">
                            <form method="post" action="options.php">
                                <?php
                                settings_fields('wp_word_cleaner');
                                do_settings_sections('wp_word_cleaner');
                                submit_button('Save Settings');
                                ?>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- ACF Settings Tab (conditionally displayed) -->
                <?php if (isset($GLOBALS['wp_word_markup_cleaner'])) :
                    $plugin = $GLOBALS['wp_word_markup_cleaner'];
                    $acf_integration = $plugin->get_acf_integration();
                    if ($acf_integration && $acf_integration->is_acf_active() && !empty($this->options['enable_acf_cleaning'])) : ?>
                        <div id="tab-acf" class="word-markup-tab-content">
                            <?php $this->add_acf_field_type_section(); ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Cache Tab -->
                <div id="tab-cache" class="word-markup-tab-content">
                    <?php $this->add_cache_tab_content(); ?>
                </div>

                <!-- Test Cleaner Tab -->
                <div id="tab-test" class="word-markup-tab-content">
                    <?php $this->add_test_cleaning_section(); ?>
                </div>

                <!-- Debug Log Tab (conditionally displayed) -->
                <?php if ($debug_enabled) : ?>
                    <div id="tab-debug" class="word-markup-tab-content">
                        <div class="postbox">
                            <h2 class="postbox-header">Debug Log</h2>
                            <div class="inside">
                                <?php $this->display_debug_log(); ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php
    }

    /**
     * Add a test cleaning tool to the admin page
     * Enhanced version with option-specific testing
     */
    private function add_test_cleaning_section()
    {
    ?>
        <div class="postbox">
            <h2 class="postbox-header">Test Cleaner</h2>
            <div class="inside">
                <p>Paste Word content here to see what the cleaner will do. You can test with all default settings or customize which cleaning options to apply.</p>

                <form method="post" action="" id="test-cleaner-form">
                    <?php wp_nonce_field('word_cleaner_test', 'word_cleaner_test_nonce'); ?>

                    <div class="test-content-container">
                        <label for="test_content"><strong>Content to Clean:</strong></label>
                        <textarea name="test_content" id="test_content" placeholder="Paste Word content here to see what gets cleaned..."><?php echo isset($_POST['test_content']) ? esc_textarea(stripslashes($_POST['test_content'])) : ''; ?></textarea>
                    </div>

                    <div class="test-options">
                        <label for="advanced_mode" class="toggle-advanced">
                            <input type="checkbox" id="advanced_mode" name="advanced_mode" value="1" <?php checked(isset($_POST['advanced_mode'])); ?>>
                            Advanced testing mode (test specific options)
                        </label>

                        <div class="advanced-options" style="<?php echo isset($_POST['advanced_mode']) ? '' : 'display:none;'; ?>">
                            <h3>Cleaning Options to Test</h3>
                            <p class="description">Select which cleaning operations you want to apply:</p>

                            <div class="options-grid">
                                <?php
                                // Get cleaning levels descriptions from existing method
                                $cleaning_levels = $this->get_cleaning_levels_descriptions();

                                // Check which options were previously selected
                                $selected_options = isset($_POST['cleaning_options']) ? $_POST['cleaning_options'] : array_keys($cleaning_levels);

                                foreach ($cleaning_levels as $key => $description) {
                                    $checked = in_array($key, $selected_options) ? 'checked' : '';
                                ?>
                                    <div class="option-item">
                                        <label>
                                            <input type="checkbox" name="cleaning_options[]" value="<?php echo esc_attr($key); ?>" <?php echo $checked; ?>>
                                            <strong><?php echo esc_html($this->get_cleaning_level_label($key)); ?></strong>
                                        </label>
                                        <p class="option-description"><?php echo esc_html($description); ?></p>
                                    </div>
                                <?php
                                }
                                ?>
                            </div>

                            <div class="content-type-selection">
                                <label for="content_type"><strong>Simulate Content Type:</strong></label>
                                <select name="content_type" id="content_type">
                                    <option value="custom" <?php selected(isset($_POST['content_type']) && $_POST['content_type'] === 'custom'); ?>>Custom Config (Use Options Above)</option>
                                    <option value="post" <?php selected(!isset($_POST['content_type']) || $_POST['content_type'] === 'post'); ?>>Post</option>
                                    <option value="page" <?php selected(isset($_POST['content_type']) && $_POST['content_type'] === 'page'); ?>>Page</option>
                                    <option value="acf_wysiwyg" <?php selected(isset($_POST['content_type']) && $_POST['content_type'] === 'acf_wysiwyg'); ?>>ACF WYSIWYG</option>
                                    <option value="acf_text" <?php selected(isset($_POST['content_type']) && $_POST['content_type'] === 'acf_text'); ?>>ACF Text</option>
                                    <option value="acf_textarea" <?php selected(isset($_POST['content_type']) && $_POST['content_type'] === 'acf_textarea'); ?>>ACF Textarea</option>
                                    <option value="excerpt" <?php selected(isset($_POST['content_type']) && $_POST['content_type'] === 'excerpt'); ?>>Excerpt</option>
                                </select>
                                <p class="description">Select a content type to use its default settings, or choose "Custom Config" to use the options selected above.</p>
                            </div>

                            <div class="option-actions">
                                <button type="button" class="button toggle-options">Toggle All Options</button>
                            </div>
                        </div>
                    </div>

                    <div class="test-actions">
                        <input type="submit" name="test_cleaner" class="button button-primary" value="Test Cleaning">
                    </div>
                </form>

                <?php
                // Process the test cleaning if submitted
                if (isset($_POST['test_cleaner']) && isset($_POST['word_cleaner_test_nonce']) && wp_verify_nonce($_POST['word_cleaner_test_nonce'], 'word_cleaner_test')) {
                    if (!empty($_POST['test_content'])) {
                        $original = $_POST['test_content'];

                        // Store original DOM processing setting to restore later
                        $original_dom_setting = $this->cleaner->is_dom_processing_enabled();

                        // Check if advanced mode is enabled
                        if (isset($_POST['advanced_mode'])) {
                            $content_type = isset($_POST['content_type']) ? sanitize_text_field($_POST['content_type']) : 'custom';

                            // Set DOM processing mode based on checkbox
                            $use_dom = isset($_POST['use_dom_processing']) ? true : false;
                            $this->cleaner->set_dom_processing_enabled($use_dom);

                            // Get cleaning options
                            $cleaning_options = isset($_POST['cleaning_options']) ? $_POST['cleaning_options'] : array();

                            // Build cleaning level configuration
                            $cleaning_level = array();
                            foreach ($this->get_cleaning_levels_descriptions() as $key => $description) {
                                $cleaning_level[$key] = in_array($key, $cleaning_options);
                            }

                            // If not using custom config, get the default settings for the content type
                            if ($content_type !== 'custom') {
                                // Get the content type's default settings
                                $content_type_settings = $this->get_content_type_settings($content_type);

                                // Only apply specific content type settings if we're not using custom
                                $cleaning_level = $content_type_settings;
                            }

                            // Clean the content with the specific cleaning level
                            $cleaned = $this->cleaner->clean_content($original, $content_type, $cleaning_level);

                            // Display the results
                            $this->display_diff_view($original, $cleaned, $cleaning_level, $content_type);
                        } else {
                            // Standard mode - use default cleaning
                            $cleaned = $this->cleaner->clean_content($original);

                            // Display the results without specific cleaning level info
                            $this->display_diff_view($original, $cleaned);
                        }

                        // Restore original DOM processing setting
                        $this->cleaner->set_dom_processing_enabled($original_dom_setting);
                    }
                }
                ?>
            </div>
        </div>
    <?php
    }

    /**
     * Enhance the existing test cleaner with option-specific testing capabilities
     * This modifies the existing display_diff_view and add_test_cleaning_section methods
     */

    /**
     * Display a diff view between original and cleaned content
     * Enhanced version that shows which options were applied
     * 
     * @param string $original The original content
     * @param string $cleaned The cleaned content
     * @param array $cleaning_level The cleaning level configuration used
     * @param string $content_type The content type that was tested
     */
    private function display_diff_view($original, $cleaned, $cleaning_level = array(), $content_type = 'custom')
    {
        // Calculate stats
        $orig_length = strlen($original);
        $clean_length = strlen($cleaned);
        $diff = $orig_length - $clean_length;
        $percent = $orig_length > 0 ? round(($diff / $orig_length) * 100, 2) : 0;

        // Add stats with visualization
    ?>
        <div class="cleaning-results">
            <h3>Cleaning Results</h3>

            <?php if (!empty($cleaning_level) && $content_type !== 'post'): ?>
                <div class="results-meta">
                    <div class="content-type-info">
                        <span class="content-type-label">Content Type Tested:</span>
                        <span class="content-type-value"><?php echo esc_html(ucfirst(str_replace('_', ' ', $content_type))); ?></span>

                        <span class="processing-method">
                            <strong>Processing Method:</strong>
                            <?php echo isset($_POST['use_dom_processing']) ? 'DOM-based Element Processing' : 'Legacy Regex Processing'; ?>
                        </span>
                    </div>

                    <?php if (!empty($cleaning_level)): ?>
                        <div class="options-applied">
                            <span class="options-label">Options Applied:</span>
                            <ul class="options-list">
                                <?php foreach ($cleaning_level as $option => $enabled): ?>
                                    <li class="<?php echo $enabled ? 'enabled' : 'disabled'; ?>">
                                        <?php echo esc_html($this->get_cleaning_level_label($option)); ?>
                                        <span class="option-status"><?php echo $enabled ? '✓' : '✗'; ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="results-grid">
                <div class="stats-column">
                    <h4>Statistics</h4>
                    <ul>
                        <li><strong>Original Size:</strong> <?php echo $orig_length; ?> characters</li>
                        <li><strong>Cleaned Size:</strong> <?php echo $clean_length; ?> characters</li>
                        <li><strong>Removed:</strong> <?php echo $diff; ?> characters (<?php echo $percent; ?>% reduction)</li>
                    </ul>
                </div>
                <div class="markup-column">
                    <h4>Word Markup Found</h4>
                    <?php
                    // Unescape content for accurate counting
                    $unescaped = stripslashes($original);

                    // Find specific Word markup patterns to highlight
                    $patterns = [
                        'MSO Style Attributes' => '/mso-[^:=\s>]*:[^;>]*;?/i',
                        'MSO Class Attributes' => '/class\s*=\s*["\'][^"\']*(?:Mso|mso)[^"\']*["\']/i',
                        'Word XML Tags' => '/<\/?(o:p|w:WordDocument|w:[^>]*|m:[^>]*|v:[^>]*)>/i',
                        'Word Conditionals' => '/<!--\[if.*?\]>.*?<!\[endif\]-->/is',
                        'Style Attributes' => '/style\s*=\s*["\'][^"\']*["\']/i',
                        'Font Attributes' => '/font-[^:=\s>]*:[^;>]*;?/'
                    ];

                    echo '<ul>';
                    $total = 0;
                    foreach ($patterns as $name => $pattern) {
                        $count = preg_match_all($pattern, $unescaped, $matches);
                        $total += $count;
                        echo '<li><strong>' . esc_html($name) . ':</strong> ' . $count . '</li>';
                    }
                    echo '<li><strong>Total Word Markers:</strong> ' . $total . '</li>';
                    echo '</ul>';
                    ?>
                </div>
            </div>

            <?php if ($orig_length > 0 && $clean_length > 0): ?>
                <div class="effectiveness-chart">
                    <h4>Cleaning Effectiveness</h4>
                    <div class="chart-container">
                        <div class="chart-bar">
                            <div class="bar-original" style="width: 100%;"><?php echo $orig_length; ?> chars</div>
                            <div class="bar-cleaned" style="width: <?php echo $clean_length / $orig_length * 100; ?>%;"><?php echo $clean_length; ?> chars</div>
                        </div>
                        <div class="chart-labels">
                            <span class="label-original">Original</span>
                            <span class="label-cleaned">Cleaned</span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="code-comparison">
                <div class="original-code">
                    <h4>Original HTML (with Word markup)</h4>
                    <div class="code-container">
                        <?php echo esc_html(stripslashes($original)); ?>
                    </div>
                </div>
                <div class="cleaned-code">
                    <h4>Cleaned HTML</h4>
                    <div class="code-container">
                        <?php echo esc_html(stripslashes($cleaned)); ?>
                    </div>
                </div>
            </div>

            <div class="highlighted-changes">
                <h4>Highlighted Changes</h4>
                <p>Red text shows what was removed by the cleaner:</p>
                <div class="highlighted-container">
                    <?php
                    // Simple visual diff - highlight what's removed in red
                    foreach ($patterns as $name => $pattern) {
                        $highlighted = preg_replace($pattern, '<span class="removed-markup">$0</span>', $original);
                        if ($highlighted !== $original) {
                            $original = $highlighted;
                        }
                    }
                    echo $original;
                    ?>
                </div>
            </div>
        </div>
        <?php
    }


    /**
     * Display debug log
     */
    private function display_debug_log()
    {
        // Check debug is enabled
        if (!$this->options['enable_debug']) {
            echo '<p>Debug logging is currently disabled. Enable it in settings to view logs.</p>';
            return;
        }

        // Get log file path and check if it exists
        $log_file = $this->logger->get_log_file_path();
        if (!file_exists($log_file)) {
            $this->logger->initialize_log_file();
            echo '<p>Log file was just initialized. No log entries yet.</p>';
            return;
        }

        // Check file permissions
        if (!is_readable($log_file)) {
            echo '<div class="log-error"><p>Cannot read log file. Please check file permissions.<br>Path: ' . esc_html($log_file) . '</p></div>';
            return;
        }

        // Check if the log file has content
        $file_size = filesize($log_file);
        if ($file_size <= 0) {
            // Initialize with a test entry if empty
            $this->logger->initialize_log_file();
            $file_size = filesize($log_file);
        }

        // Read the log file
        $log_content = '';
        $max_size = 1048576; // 1MB limit for display

        if ($file_size > $max_size) {
            // For large files, read only the last part
            $handle = @fopen($log_file, 'r');
            if ($handle) {
                fseek($handle, -$max_size, SEEK_END);
                // Skip the first line (which might be incomplete)
                fgets($handle);
                // Read the rest
                $log_content = stream_get_contents($handle);
                fclose($handle);

                $log_content = "... (showing last " . size_format($max_size) . " of log) ...\n\n" . $log_content;
            } else {
                $log_content = "Error: Could not open log file for reading.";
            }
        } else {
            // For smaller files, read the whole content
            $log_content = @file_get_contents($log_file);
            if ($log_content === false) {
                $log_content = "Error: Could not read log file content.";
            }
        }

        // Generate a unique ID for the log content
        $log_id = 'word-cleaner-log-' . wp_rand();

        // Display the log content
        if (!empty($log_content)) {
        ?>
            <div class="log-container">
                <pre id="<?php echo esc_attr($log_id); ?>"><?php echo esc_html($log_content); ?></pre>
            </div>

            <div class="log-actions">
                <div class="log-buttons">
                    <form method="get">
                        <input type="hidden" name="page" value="word-markup-cleaner">
                        <input type="hidden" name="clear_log" value="1">
                        <button type="submit" class="button button-secondary">Clear Log</button>
                    </form>

                    <button type="button" class="button button-secondary copy-log-button" data-log-id="<?php echo esc_attr($log_id); ?>">Copy Log</button>
                </div>

                <span class="log-size">Log size: <?php echo size_format($file_size); ?></span>
            </div>
        <?php
        } else {
            echo '<div class="log-error"><p>Log file is empty or could not be read.<br>Path: ' . esc_html($log_file) . '</p></div>';
        }
    }

    /**
     * Display admin notice about debug mode
     */
    public function debug_notice()
    {
        global $pagenow;

        if ($pagenow == 'post.php' || $pagenow == 'post-new.php') {
        ?>
            <div class="notice notice-warning">
                <p><strong>Word Markup Cleaner Debug Mode:</strong> This plugin is logging all content cleaning operations to <code><?php echo $this->logger->get_log_file_path(); ?></code>. You can view the log in the <a href="<?php echo admin_url('tools.php?page=word-markup-cleaner'); ?>">Word Markup Cleaner</a> settings page.</p>
            </div>
        <?php
        }
    }

    /**
     * Get plugin options
     *
     * @return array Plugin options
     */
    public function get_options()
    {
        return $this->options;
    }

    /**
     * Register cache settings
     */
    private function register_cache_settings()
    {
        register_setting(
            'wp_word_cleaner_cache',
            'wp_word_cleaner_cache_options',
            array($this, 'sanitize_cache_options')
        );

        add_settings_section(
            'wp_word_cleaner_cache_section',
            'Cache Settings',
            array($this, 'cache_settings_section_callback'),
            'wp_word_cleaner_cache'
        );

        add_settings_field(
            'enable_settings_cache',
            'Enable Settings Cache',
            array($this, 'cache_checkbox_field_callback'),
            'wp_word_cleaner_cache',
            'wp_word_cleaner_cache_section',
            array('label_for' => 'enable_settings_cache')
        );

        add_settings_field(
            'enable_content_cache',
            'Enable Content Cache',
            array($this, 'cache_checkbox_field_callback'),
            'wp_word_cleaner_cache',
            'wp_word_cleaner_cache_section',
            array('label_for' => 'enable_content_cache')
        );

        add_settings_field(
            'max_cache_entries',
            'Maximum Cache Entries',
            array($this, 'cache_number_field_callback'),
            'wp_word_cleaner_cache',
            'wp_word_cleaner_cache_section',
            array('label_for' => 'max_cache_entries')
        );

        add_settings_field(
            'cache_ttl',
            'Cache TTL (seconds)',
            array($this, 'cache_number_field_callback'),
            'wp_word_cleaner_cache',
            'wp_word_cleaner_cache_section',
            array('label_for' => 'cache_ttl')
        );
    }

    /**
     * Sanitize cache options
     *
     * @param array $input The input options
     * @return array The sanitized options
     */
    public function sanitize_cache_options($input)
    {
        $output = array(
            'enable_settings_cache' => !empty($input['enable_settings_cache']) ? 1 : 0,
            'enable_content_cache' => !empty($input['enable_content_cache']) ? 1 : 0,
            'max_cache_entries' => isset($input['max_cache_entries']) ?
                max(10, min(1000, intval($input['max_cache_entries']))) : 100,
            'cache_ttl' => isset($input['cache_ttl']) ?
                max(60, min(86400, intval($input['cache_ttl']))) : 3600
        );

        // Apply cache settings
        if (isset($GLOBALS['wp_word_markup_cleaner'])) {
            $plugin = $GLOBALS['wp_word_markup_cleaner'];

            // Apply to settings manager
            $settings_manager = $plugin->get_settings_manager();
            if ($settings_manager) {
                $settings_manager->set_cache_enabled($output['enable_settings_cache']);
                $settings_manager->set_cache_ttl($output['cache_ttl']);
            }

            // Apply to content cleaner
            $content_cleaner = $plugin->get_content_cleaner();
            if ($content_cleaner) {
                $content_cleaner->set_cache_enabled($output['enable_content_cache']);
                $content_cleaner->set_max_cache_entries($output['max_cache_entries']);
            }
        }

        return $output;
    }

    /**
     * Cache settings section callback
     */
    public function cache_settings_section_callback()
    {
        echo '<p>Configure the caching behavior of the Word Markup Cleaner plugin to improve performance.</p>';
    }

    /**
     * Cache checkbox field callback
     */
    public function cache_checkbox_field_callback($args)
    {
        $field_id = $args['label_for'];
        $options = get_option('wp_word_cleaner_cache_options', array(
            'enable_settings_cache' => 1,
            'enable_content_cache' => 1,
            'max_cache_entries' => 100,
            'cache_ttl' => 3600
        ));

        $checked = isset($options[$field_id]) ? $options[$field_id] : 1;

        echo '<input type="checkbox" id="' . esc_attr($field_id) . '" name="wp_word_cleaner_cache_options[' . esc_attr($field_id) . ']" value="1" ' . checked(1, $checked, false) . '>';

        // Add descriptions for each option
        switch ($field_id) {
            case 'enable_settings_cache':
                echo '<p class="description">Enable caching of plugin settings to reduce database queries.</p>';
                break;
            case 'enable_content_cache':
                echo '<p class="description">Enable caching of cleaned content to avoid redundant processing of identical content.</p>';
                break;
        }
    }

    /**
     * Cache number field callback
     */
    public function cache_number_field_callback($args)
    {
        $field_id = $args['label_for'];
        $options = get_option('wp_word_cleaner_cache_options', array(
            'enable_settings_cache' => 1,
            'enable_content_cache' => 1,
            'max_cache_entries' => 100,
            'cache_ttl' => 3600
        ));

        $value = isset($options[$field_id]) ? $options[$field_id] : ($field_id === 'max_cache_entries' ? 100 : 3600);

        echo '<input type="number" id="' . esc_attr($field_id) . '" name="wp_word_cleaner_cache_options[' . esc_attr($field_id) . ']" value="' . esc_attr($value) . '" class="small-text">';

        // Add descriptions for each option
        switch ($field_id) {
            case 'max_cache_entries':
                echo '<p class="description">Maximum number of content items to keep in memory cache (10-1000).</p>';
                break;
            case 'cache_ttl':
                echo '<p class="description">Time in seconds to keep items in persistent cache (60-86400).</p>';
                break;
        }
    }

    /**
     * Add cache tab to admin UI
     */
    private function add_cache_tab_content()
    {
        // Get cache statistics
        $cache_stats = $this->get_cache_statistics();

        // Get cache options
        $cache_options = get_option('wp_word_cleaner_cache_options', array(
            'enable_settings_cache' => 1,
            'enable_content_cache' => 1,
            'max_cache_entries' => 100,
            'cache_ttl' => 3600
        ));
        ?>
        <div class="postbox">
            <h2 class="postbox-header">Cache Management</h2>
            <div class="inside">
                <div class="cache-statistics">
                    <h3>Cache Statistics</h3>

                    <div class="layout-cols">
                        <div>
                            <h4>Settings Cache</h4>
                            <ul>
                                <li><strong>Status:</strong> <?php echo $cache_stats['settings_cache']['enabled'] ? 'Enabled' : 'Disabled'; ?></li>
                                <li><strong>TTL:</strong> <?php echo $cache_stats['settings_cache']['ttl']; ?> seconds</li>
                            </ul>
                        </div>
                        <div>
                            <h4>Content Cache</h4>
                            <ul>
                                <li><strong>Status:</strong> <?php echo $cache_stats['content_cache']['enabled'] ? 'Enabled' : 'Disabled'; ?></li>
                                <li><strong>Entries:</strong> <?php echo $cache_stats['content_cache']['current_entries']; ?> / <?php echo $cache_stats['content_cache']['max_entries']; ?></li>
                                <li><strong>Hit Rate:</strong> <?php echo $cache_stats['content_cache']['hit_rate']; ?>%
                                    (<?php echo $cache_stats['content_cache']['hits']; ?> hits,
                                    <?php echo $cache_stats['content_cache']['misses']; ?> misses)</li>
                            </ul>
                        </div>
                    </div>

                    <form method="post" action="" class="cache-actions">
                        <?php wp_nonce_field('clear_cache_action', 'clear_cache_nonce'); ?>
                        <input type="hidden" name="action" value="clear_cache">
                        <input type="submit" name="clear_cache" class="button button-secondary" value="Clear All Caches">
                    </form>
                </div>

                <div class="cache-settings">
                    <h3>Cache Settings</h3>
                    <form method="post" action="options.php" class="cache-settings-form">
                        <?php
                        settings_fields('wp_word_cleaner_cache');
                        do_settings_sections('wp_word_cleaner_cache');
                        submit_button('Save Cache Settings');
                        ?>
                    </form>
                </div>
            </div>
        </div>
<?php
    }

    /**
     * Process the clear cache action
     */
    private function process_clear_cache_action()
    {
        if (
            isset($_POST['action']) &&
            $_POST['action'] === 'clear_cache' &&
            isset($_POST['clear_cache_nonce']) &&
            wp_verify_nonce($_POST['clear_cache_nonce'], 'clear_cache_action')
        ) {
            // Clear all caches
            if (isset($GLOBALS['wp_word_markup_cleaner'])) {
                $plugin = $GLOBALS['wp_word_markup_cleaner'];

                // Clear settings cache
                $settings_manager = $plugin->get_settings_manager();
                if ($settings_manager) {
                    $settings_manager->clear_cache();
                }

                // Clear content cache
                $content_cleaner = $plugin->get_content_cleaner();
                if ($content_cleaner) {
                    $content_cleaner->clear_content_cache();
                }

                // Show success message
                add_settings_error(
                    'word_markup_cleaner_cache',
                    'cache_cleared',
                    'All caches have been cleared successfully.',
                    'updated'
                );
            }
        }
    }

    /**
     * Get cache statistics from all components
     *
     * @return array Cache statistics
     */
    private function get_cache_statistics()
    {
        $stats = array(
            'settings_cache' => array(
                'enabled' => true,
                'ttl' => 3600
            ),
            'content_cache' => array(
                'enabled' => true,
                'max_entries' => 100,
                'current_entries' => 0,
                'hits' => 0,
                'misses' => 0,
                'hit_rate' => 0,
                'version' => '1.0'
            )
        );

        // Get actual statistics from components if available
        if (isset($GLOBALS['wp_word_markup_cleaner'])) {
            $plugin = $GLOBALS['wp_word_markup_cleaner'];

            // Get settings manager stats
            $settings_manager = $plugin->get_settings_manager();
            if ($settings_manager) {
                $stats['settings_cache']['enabled'] = $settings_manager->is_cache_enabled();
                $stats['settings_cache']['ttl'] = $settings_manager->get_cache_ttl();
            }

            // Get content cleaner stats
            $content_cleaner = $plugin->get_content_cleaner();
            if ($content_cleaner && method_exists($content_cleaner, 'get_cache_stats')) {
                $cleaner_stats = $content_cleaner->get_cache_stats();
                $stats['content_cache'] = $cleaner_stats;
            }
        }

        return $stats;
    }
}
