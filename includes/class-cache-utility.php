<?php
/**
 * Cache utility class for the Word Markup Cleaner plugin
 *
 * @package WordPress_Word_Markup_Cleaner
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class WP_Word_Markup_Cleaner_Cache_Utility
 * 
 * Provides centralized cache management for the plugin
 */
class WP_Word_Markup_Cleaner_Cache_Utility {
    
    /**
     * Plugin version
     *
     * @var string
     */
    private $version;
    
    /**
     * Cache group
     *
     * @var string
     */
    private $cache_group = 'wp_word_cleaner';
    
    /**
     * Cache enabled flag
     *
     * @var bool
     */
    private $enabled = true;
    
    /**
     * Default cache TTL
     *
     * @var int
     */
    private $default_ttl = 3600; // 1 hour
    
    /**
     * Cache statistics
     *
     * @var array
     */
    private $stats = array(
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0
    );
    
    /**
     * Logger instance
     *
     * @var WP_Word_Markup_Cleaner_Logger
     */
    private $logger;

    /**
     * Debug enabled flag
     *
     * @var bool
     */
    private $debug_enabled = false;
    
    /**
     * Initialize the cache utility
     *
     * @param string $version Plugin version
     * @param WP_Word_Markup_Cleaner_Logger $logger Logger instance
     */
    public function __construct($version, $logger = null) {
        $this->version = $version;
        $this->logger = $logger;
        
        // Check if debug is enabled
        if ($this->logger) {
            $options = get_option('wp_word_cleaner_options', array());
            $this->debug_enabled = !empty($options['enable_debug']);
        }
        
        // Get cache options
        $cache_options = get_option('wp_word_cleaner_cache_options', array(
            'enabled' => true,
            'ttl' => 3600
        ));
        
        $this->enabled = isset($cache_options['enabled']) ? (bool) $cache_options['enabled'] : true;
        $this->default_ttl = isset($cache_options['ttl']) ? (int) $cache_options['ttl'] : 3600;
        
        // Log initialization
        $this->log_debug("Cache utility initialized (enabled: " . ($this->enabled ? 'yes' : 'no') . ", TTL: {$this->default_ttl}s)");
        
        // Register shutdown function to log stats
        add_action('shutdown', array($this, 'log_stats_on_shutdown'));
    }
    
    /**
     * Get a versioned cache key
     *
     * @param string $key Base key
     * @return string Versioned key
     */
    public function get_key($key) {
        return "wpmsc_{$key}_v{$this->version}";
    }
    
    /**
     * Get a value from cache
     *
     * @param string $key Cache key
     * @param mixed $default Default value if not found
     * @return mixed Cached value or default
     */
    public function get($key, $default = false) {
        if (!$this->enabled || !function_exists('wp_cache_get')) {
            $this->stats['misses']++;
            return $default;
        }
        
        $versioned_key = $this->get_key($key);
        $value = wp_cache_get($versioned_key, $this->cache_group);
        
        if ($value === false) {
            $this->stats['misses']++;
            $this->log_debug("Cache MISS: {$key}");
            return $default;
        }
        
        $this->stats['hits']++;
        $this->log_debug("Cache HIT: {$key}");
        return $value;
    }
    
    /**
     * Set a value in cache
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Cache TTL in seconds
     * @return bool Success
     */
    public function set($key, $value, $ttl = null) {
        if (!$this->enabled || !function_exists('wp_cache_set')) {
            return false;
        }
        
        $ttl = ($ttl === null) ? $this->default_ttl : (int) $ttl;
        $versioned_key = $this->get_key($key);
        
        $this->stats['sets']++;
        $this->log_debug("Cache SET: {$key} (TTL: {$ttl}s)");
        
        return wp_cache_set($versioned_key, $value, $this->cache_group, $ttl);
    }
    
    /**
     * Delete a value from cache
     *
     * @param string $key Cache key
     * @return bool Success
     */
    public function delete($key) {
        if (!function_exists('wp_cache_delete')) {
            return false;
        }
        
        $versioned_key = $this->get_key($key);
        
        $this->stats['deletes']++;
        $this->log_debug("Cache DELETE: {$key}");
        
        return wp_cache_delete($versioned_key, $this->cache_group);
    }
    
    /**
     * Flush all cache entries for this plugin
     *
     * @return bool Success
     */
    public function flush() {
        $this->log_debug("Cache FLUSH requested");
        
        if (function_exists('wp_cache_flush_group') && method_exists('WP_Object_Cache', 'flush_group')) {
            // Some caching plugins support group flushing
            $result = wp_cache_flush_group($this->cache_group);
            $this->log_debug("Cache FLUSH via wp_cache_flush_group: " . ($result ? 'success' : 'failed'));
            return $result;
        }
        
        $this->log_debug("Cache FLUSH: wp_cache_flush_group not available, individual keys must be deleted manually");
        return false;
    }
    
    /**
     * Enable or disable caching
     *
     * @param bool $enabled Whether caching is enabled
     */
    public function set_enabled($enabled) {
        $this->enabled = (bool) $enabled;
        $this->log_debug("Cache " . ($this->enabled ? 'enabled' : 'disabled'));
    }
    
    /**
     * Check if caching is enabled
     *
     * @return bool Whether caching is enabled
     */
    public function is_enabled() {
        return $this->enabled;
    }
    
    /**
     * Set default TTL
     *
     * @param int $ttl TTL in seconds
     */
    public function set_default_ttl($ttl) {
        $this->default_ttl = max(60, (int) $ttl);
        $this->log_debug("Default TTL set to {$this->default_ttl}s");
    }
    
    /**
     * Get default TTL
     *
     * @return int TTL in seconds
     */
    public function get_default_ttl() {
        return $this->default_ttl;
    }
    
    /**
     * Get cache statistics
     *
     * @return array Cache statistics
     */
    public function get_stats() {
        $total = $this->stats['hits'] + $this->stats['misses'];
        $hit_rate = ($total > 0) ? round(($this->stats['hits'] / $total) * 100, 2) : 0;
        
        return array(
            'enabled' => $this->enabled,
            'ttl' => $this->default_ttl,
            'hits' => $this->stats['hits'],
            'misses' => $this->stats['misses'],
            'sets' => $this->stats['sets'],
            'deletes' => $this->stats['deletes'],
            'hit_rate' => $hit_rate,
            'version' => $this->version
        );
    }
    
    /**
     * Log statistics on shutdown
     */
    public function log_stats_on_shutdown() {
        if ($this->debug_enabled && $this->logger) {
            $stats = $this->get_stats();
            
            // Only log if there was some cache activity
            if ($stats['hits'] > 0 || $stats['misses'] > 0 || $stats['sets'] > 0) {
                $this->logger->log_debug("Cache statistics: " . 
                    "Hits: {$stats['hits']}, " . 
                    "Misses: {$stats['misses']}, " . 
                    "Sets: {$stats['sets']}, " . 
                    "Deletes: {$stats['deletes']}, " . 
                    "Hit rate: {$stats['hit_rate']}%");
            }
        }
    }
    
    /**
     * Log debug message if debug is enabled
     *
     * @param string $message Debug message
     */
    private function log_debug($message) {
        if ($this->debug_enabled && $this->logger) {
            $this->logger->log_debug($message);
        }
    }
}