<?php
/**
 * Uninstall WordPress Word Markup Cleaner
 *
 * @package WordPress_Word_Markup_Cleaner
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Delete plugin options for a single site
 */
function word_cleaner_delete_plugin_options() {
    // Main plugin options
    $options_to_delete = array(
        'wp_word_cleaner_options',
        'wp_word_cleaner_activated',
        'wp_word_cleaner_version',
        'wp_word_cleaner_field_types',
        'wp_word_cleaner_cache_options',
        // Content type settings groups
        'wp_word_cleaner_core_types',
        'wp_word_cleaner_acf_types',
        'wp_word_cleaner_custom_post_types',
        'wp_word_cleaner_special_types'
    );
    
    // Delete all plugin options
    foreach ($options_to_delete as $option) {
        delete_option($option);
    }
    
    // Clear any transients we might have created
    delete_transient('wp_word_cleaner_stats');
    
    // Clean up log files
    $upload_dir = wp_upload_dir();
    $log_files = glob($upload_dir['basedir'] . '/word_cleaner_debug.log*');
    
    // Delete all log files
    if (is_array($log_files)) {
        foreach ($log_files as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }
}

// Single site: Delete options directly
if (!is_multisite()) {
    word_cleaner_delete_plugin_options();
} else {
    // Multisite: Delete options for all sites
    global $wpdb;
    
    // Get all blog IDs
    $blog_ids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
    
    if ($blog_ids) {
        foreach ($blog_ids as $blog_id) {
            switch_to_blog($blog_id);
            word_cleaner_delete_plugin_options();
            restore_current_blog();
        }
    }
}