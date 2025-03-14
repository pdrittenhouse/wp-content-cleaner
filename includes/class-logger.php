<?php
/**
 * Logging functionality for the Word Markup Cleaner plugin
 *
 * @package WordPress_Word_Markup_Cleaner
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class WP_Word_Markup_Cleaner_Logger
 * 
 * Handles debug logging for the plugin
 */
class WP_Word_Markup_Cleaner_Logger {
    
    /**
     * Log file path
     *
     * @var string
     */
    private $log_file;
    
    /**
     * Maximum log file size in bytes
     *
     * @var int
     */
    private $max_log_size = 5242880; // 5MB default
    
    /**
     * Whether debug is enabled
     * 
     * @var boolean|null
     */
    private $debug_enabled = null;
    
    /**
     * Rate limit tracking for log operations
     *
     * @var array
     */
    private $rate_limit_data = array(
        'last_time' => 0,
        'count' => 0
    );

    /**
     * Rate limit configuration
     *
     * @var array
     */
    private $rate_limit_config = array(
        'max_entries' => 100,   // Maximum entries per time period
        'period' => 60          // Time period in seconds
    );
    
    /**
     * Initialize the logger
     */
    public function __construct() {
        // Set up debug log file
        $upload_dir = wp_upload_dir();
        $this->log_file = $upload_dir['basedir'] . '/word_cleaner_debug.log';
        
        // Add AJAX handlers for log operations
        add_action('wp_ajax_word_markup_cleaner_get_log', array($this, 'ajax_get_log'));
        add_action('wp_ajax_word_markup_cleaner_clear_log', array($this, 'ajax_clear_log'));
    }
    
    /**
     * AJAX handler to get log content
     */
    public function ajax_get_log() {
        // Check nonce for security
        check_ajax_referer('word_markup_cleaner_log_nonce', 'nonce');
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have permission to view the log.'));
            exit;
        }
        
        // Get log file content
        $log_content = $this->get_log_content();
        
        // Get log file size and modified time
        $file_size = file_exists($this->log_file) ? filesize($this->log_file) : 0;
        $file_time = file_exists($this->log_file) ? filemtime($this->log_file) : 0;
        
        // Send response
        wp_send_json_success(array(
            'content' => $log_content,
            'size' => $file_size,
            'size_formatted' => size_format($file_size),
            'last_updated' => $file_time ? date('Y-m-d H:i:s', $file_time) : 'N/A',
            'is_debug_enabled' => $this->is_debug_enabled()
        ));
    }
    
    /**
     * AJAX handler to clear log
     */
    public function ajax_clear_log() {
        // Check nonce for security
        check_ajax_referer('word_markup_cleaner_log_nonce', 'nonce');
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have permission to clear the log.'));
            exit;
        }
        
        // Clear the log
        $result = $this->clear_log_file();
        
        // Send response
        if ($result) {
            wp_send_json_success(array('message' => 'Log cleared successfully.'));
        } else {
            wp_send_json_error(array('message' => 'Failed to clear log.'));
        }
    }
    
    /**
     * Initialize log file
     * 
     * @return bool Whether the log file was initialized
     */
    public function initialize_log_file() {
        $timestamp = date('Y-m-d H:i:s');
        $result = @file_put_contents(
            $this->log_file, 
            "[{$timestamp}] Word Markup Cleaner Log initialized\n"
        );
        
        return $result !== false;
    }
    
    /**
     * Clear log file
     * 
     * @return bool Whether the log file was cleared
     */
    public function clear_log_file() {
        $timestamp = date('Y-m-d H:i:s');
        $result = @file_put_contents(
            $this->log_file, 
            "[{$timestamp}] Log cleared and reinitialized\n"
        );
        
        return $result !== false;
    }
    
    /**
     * Get log content
     * 
     * @param int $lines Maximum number of lines to return (0 for all)
     * @return string Log content
     */
    public function get_log_content($lines = 0) {
        // Check if log file exists
        if (!file_exists($this->log_file) || !is_readable($this->log_file)) {
            return '';
        }
        
        // Get file size
        $file_size = filesize($this->log_file);
        
        // If the file is empty, return empty string
        if ($file_size <= 0) {
            return '';
        }
        
        // For very large files, read only the tail
        if ($file_size > $this->max_log_size) {
            return $this->get_last_lines($lines);
        }
        
        // For smaller files, read the whole content
        $content = @file_get_contents($this->log_file);
        
        // If failed to read, return empty string
        if ($content === false) {
            return '';
        }
        
        // If we need to limit lines
        if ($lines > 0) {
            $content_array = explode("\n", $content);
            if (count($content_array) > $lines) {
                $content_array = array_slice($content_array, -$lines);
                return implode("\n", $content_array);
            }
        }
        
        return $content;
    }
    
    /**
     * Get the last N lines of the log file
     * 
     * @param int $lines Number of lines to get
     * @return string The last N lines of the file
     */
    private function get_last_lines($lines = 1000) {
        if (!file_exists($this->log_file) || !is_readable($this->log_file)) {
            return '';
        }
        
        // For small files, just use get_log_content
        $file_size = filesize($this->log_file);
        if ($file_size < 500000) { // For files smaller than 500KB
            $content = @file_get_contents($this->log_file);
            $content_array = explode("\n", $content);
            $total_lines = count($content_array);
            
            if ($total_lines <= $lines) {
                return $content;
            }
            
            return implode("\n", array_slice($content_array, -$lines));
        }
        
        // For larger files, use a more memory-efficient approach
        try {
            $handle = fopen($this->log_file, "r");
            if ($handle) {
                // Initialize an array to store the last N lines
                $last_lines = array();
                $line_count = 0;
                
                // Start reading from the end of the file
                $chunk_size = 4096; // 4KB chunks
                $pos = -$chunk_size;
                $current_line = '';
                $eof = false;
                
                while ($line_count < $lines && !$eof) {
                    // Adjust position for small files
                    if (abs($pos) > $file_size) {
                        $pos = -$file_size;
                        $eof = true;
                    }
                    
                    // Seek to position
                    fseek($handle, $pos, SEEK_END);
                    
                    // Read a chunk
                    $chunk = fread($handle, $chunk_size);
                    
                    // Split into lines and process in reverse order
                    $chunk_lines = explode("\n", $chunk);
                    $chunk_lines_count = count($chunk_lines);
                    
                    // The first line might be incomplete
                    if ($pos < 0) {
                        $current_line = $chunk_lines[$chunk_lines_count - 1] . $current_line;
                        array_pop($chunk_lines);
                    }
                    
                    // Process lines in reverse order
                    for ($i = $chunk_lines_count - 1; $i >= 0; $i--) {
                        $line = $chunk_lines[$i];
                        if ($i == 0 && $pos < 0) {
                            $current_line = $line . $current_line;
                        } else {
                            if ($line_count < $lines) {
                                $last_lines[] = $line . $current_line;
                                $line_count++;
                                $current_line = '';
                            } else {
                                break 2; // Exit both loops
                            }
                        }
                    }
                    
                    // Move position back
                    $pos -= $chunk_size;
                }
                
                // Add the final line if there is one
                if (!empty($current_line) && $line_count < $lines) {
                    $last_lines[] = $current_line;
                }
                
                fclose($handle);
                
                // Reverse the array to get chronological order
                $last_lines = array_reverse($last_lines);
                
                // Add a note at the beginning
                if (!$eof) {
                    array_unshift($last_lines, "... (showing last $lines lines of log) ...");
                }
                
                return implode("\n", $last_lines);
            }
        } catch (Exception $e) {
            // If something goes wrong, fall back to the simpler approach
            $content = @file_get_contents($this->log_file);
            $content_array = explode("\n", $content);
            return implode("\n", array_slice($content_array, -$lines));
        }
        
        return '';
    }
    
    /**
     * Log debug information to file
     * 
     * @param string $message The message to log
     * @return bool Whether the message was logged
     */
    public function log_debug($message) {
        // Only log if debug is enabled
        if (!$this->is_debug_enabled()) {
            return false;
        }
        
        // Check rate limiting
        if ($this->is_rate_limited()) {
            // Only log rate limit warnings occasionally
            if ($this->rate_limit_data['count'] % 50 === 0) {
                $log_message = "[RATE LIMITED] Logging throttled - exceeded {$this->rate_limit_config['max_entries']} entries per {$this->rate_limit_config['period']} seconds";
                @file_put_contents($this->log_file, $log_message . "\n", FILE_APPEND);
            }
            return false;
        }
        
        // Ensure log file exists
        if (!file_exists($this->log_file)) {
            $this->initialize_log_file();
        }
        
        // Check if log file is writable
        if (!is_writable($this->log_file)) {
            return false;
        }
        
        // Check log file size and rotate if needed
        $this->maybe_rotate_log();
        
        // Format the message
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[{$timestamp}] {$message}\n";
        
        // Use FILE_APPEND to ensure we don't overwrite the file
        $result = @file_put_contents($this->log_file, $log_message, FILE_APPEND);
        
        return $result !== false;
    }
    
    /**
     * Check if debug logging is enabled
     *
     * @return bool Whether debug logging is enabled
     */
    public function is_debug_enabled() {
        // Use cached value if available
        if ($this->debug_enabled !== null) {
            return $this->debug_enabled;
        }
        
        // Get from options
        $options = get_option('wp_word_cleaner_options', array());
        $this->debug_enabled = !empty($options['enable_debug']);
        
        return $this->debug_enabled;
    }
    
    /**
     * Set debug enabled status
     * 
     * @param bool $enabled Whether debug is enabled
     */
    public function set_debug_enabled($enabled) {
        $this->debug_enabled = (bool)$enabled;
    }
    
    /**
     * Check if log file needs to be rotated and handle rotation
     */
    private function maybe_rotate_log() {
        if (!file_exists($this->log_file)) {
            return;
        }
        
        $file_size = filesize($this->log_file);
        
        // If file is larger than the maximum size, rotate it
        if ($file_size > $this->max_log_size) {
            $this->rotate_log();
        }
    }
    
    /**
     * Rotate log file
     */
    private function rotate_log() {
        $timestamp = date('Y-m-d-H-i-s');
        $new_log_file = $this->log_file . '.' . $timestamp;
        
        // Copy current log to archive
        if (@copy($this->log_file, $new_log_file)) {
            // Clear the current log and reinitialize
            $this->clear_log_file();
            
            // Log the rotation
            $this->log_debug("Log rotated. Old log saved as: " . basename($new_log_file));
            
            // Clean up old log files
            $this->cleanup_old_logs();
        }
    }
    
    /**
     * Clean up old log files (keep only the 5 most recent)
     */
    private function cleanup_old_logs() {
        $log_dir = dirname($this->log_file);
        $log_base = basename($this->log_file);
        
        $files = glob($log_dir . '/' . $log_base . '.*');
        if (!$files || !is_array($files)) {
            return;
        }
        
        // Sort files by modified time
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        // Keep only the 5 most recent files
        $max_logs = 5;
        
        if (count($files) > $max_logs) {
            for ($i = $max_logs; $i < count($files); $i++) {
                @unlink($files[$i]);
                $this->log_debug("Removed old log file: " . basename($files[$i]));
            }
        }
    }
    
    /**
     * Get log file path
     * 
     * @return string Log file path
     */
    public function get_log_file_path() {
        return $this->log_file;
    }
    
    /**
     * Check if logging is being rate limited
     *
     * @return bool Whether logging is currently rate limited
     */
    private function is_rate_limited() {
        $current_time = time();
        
        // Reset counter if period has passed
        if ($current_time - $this->rate_limit_data['last_time'] > $this->rate_limit_config['period']) {
            $this->rate_limit_data = array(
                'last_time' => $current_time,
                'count' => 0
            );
            return false;
        }
        
        // Increment counter
        $this->rate_limit_data['count']++;
        
        // Check if limit exceeded
        return $this->rate_limit_data['count'] > $this->rate_limit_config['max_entries'];
    }
}