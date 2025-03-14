<?php
// Find WordPress installation directory by searching for wp-load.php
$wp_load_path = '';
$dir = dirname(__FILE__);
do {
    if (file_exists($dir . '/wp-load.php')) {
        $wp_load_path = $dir . '/wp-load.php';
        break;
    }
} while ($dir = realpath($dir . '/..'));

// If wp-load.php was found, load it
if ($wp_load_path) {
    require_once($wp_load_path);
} else {
    die("Could not find WordPress installation. Please run this test from the admin AJAX endpoint instead.");
}

// Verify user is logged in with appropriate permissions
if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
    die('You do not have sufficient permissions to access this page.');
}

// Run the test
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Word Markup Cleaner Cache Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.5; }
        pre { background: #f5f5f5; padding: 10px; overflow: auto; }
        .success { color: green; }
        .error { color: red; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>Word Markup Cleaner Cache Test</h1>
    
    <?php
    // Get plugin instance
    global $wp_word_markup_cleaner;
    
    if (!$wp_word_markup_cleaner) {
        echo '<p class="error">Plugin not properly initialized. Please run test from WordPress admin.</p>';
        exit;
    }
    
    $cleaner = $wp_word_markup_cleaner->get_content_cleaner();
    $logger = $wp_word_markup_cleaner->get_logger();
    
    // Enable debugging
    $original_debug = $logger->is_debug_enabled();
    $logger->set_debug_enabled(true);
    
    echo "<h2>Cache Test Results</h2>";
    
    // Test with Word markup
    $test_content = '<p style="mso-spacerun:yes">This is a test with <o:p>Word</o:p> markup</p>';
    
    // First clean - should be cache miss
    $logger->log_debug("CACHE TEST: First content clean attempt (should be cache miss)");
    $time_start = microtime(true);
    $cleaned1 = $cleaner->clean_content($test_content, 'cache_test');
    $time_end = microtime(true);
    $time1 = ($time_end - $time_start) * 1000; // ms
    
    // Second clean with same content - should be cache hit
    $logger->log_debug("CACHE TEST: Second content clean attempt (should be cache hit)");
    $time_start = microtime(true);
    $cleaned2 = $cleaner->clean_content($test_content, 'cache_test');
    $time_end = microtime(true);
    $time2 = ($time_end - $time_start) * 1000; // ms
    
    // Results
    $improvement = $time1 > 0 ? round((($time1 - $time2) / $time1) * 100, 2) : 0;
    
    echo "<table>";
    echo "<tr><th>Test</th><th>Time (ms)</th><th>Content Length</th><th>Result</th></tr>";
    echo "<tr><td>First Clean (Cache Miss)</td><td>" . round($time1, 2) . "</td><td>" . strlen($cleaned1) . "</td><td>" . htmlspecialchars(substr($cleaned1, 0, 50)) . "...</td></tr>";
    echo "<tr><td>Second Clean (Expected Cache Hit)</td><td>" . round($time2, 2) . "</td><td>" . strlen($cleaned2) . "</td><td>" . htmlspecialchars(substr($cleaned2, 0, 50)) . "...</td></tr>";
    echo "<tr><td colspan='4'><strong>Performance Improvement: " . $improvement . "%</strong></td></tr>";
    echo "</table>";
    
    // Get cache statistics
    if (method_exists($cleaner, 'get_cache_stats')) {
        echo "<h2>Cache Statistics</h2>";
        echo "<pre>";
        print_r($cleaner->get_cache_stats());
        echo "</pre>";
    } else {
        echo "<p class='error'>Cache statistics method not available. Make sure get_cache_stats() is implemented in the Content Cleaner class.</p>";
    }
    
    // Restore original debug setting
    $logger->log_debug("CACHE TEST: Test completed");
    $logger->set_debug_enabled($original_debug);
    ?>
    
    <h2>Debug Tips</h2>
    <ul>
        <li>Check the debug log for entries showing "CACHE HIT" or "CACHE MISS"</li>
        <li>The second clean should be significantly faster than the first</li>
        <li>Cache hits should increment in the cache statistics</li>
        <li>If you don't see cache hits, check if the content cleaning method is storing content in the cache</li>
    </ul>
</body>
</html>