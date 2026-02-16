<?php
/**
 * ArchivioMD Diagnostic Check
 * 
 * Place this file in your WordPress root directory and visit it in your browser.
 * It will show you exactly what's happening with the audit logging system.
 */

// Load WordPress
require_once('wp-load.php');

if (!current_user_can('manage_options')) {
    die('You must be an administrator to run this diagnostic.');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>ArchivioMD Diagnostic Check</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1200px; margin: 20px auto; padding: 20px; }
        h1 { color: #333; }
        .section { margin: 20px 0; padding: 15px; background: #f5f5f5; border-radius: 5px; }
        .pass { color: green; font-weight: bold; }
        .fail { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        pre { background: #fff; padding: 10px; border: 1px solid #ddd; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
        th { background: #666; color: white; }
        .code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>
    <h1>ArchivioMD Diagnostic Check</h1>
    <p>This diagnostic will check all components of the Archivio Post audit logging system.</p>

    <?php
    global $wpdb;
    $table_name = $wpdb->prefix . 'archivio_post_audit';
    
    // Check 1: Plugin Active
    echo '<div class="section">';
    echo '<h2>1. Plugin Status</h2>';
    if (class_exists('MDSM_Archivio_Post')) {
        echo '<p class="pass">✓ ArchivioMD plugin is active and loaded</p>';
    } else {
        echo '<p class="fail">✗ ArchivioMD plugin class not found</p>';
        die('</div></body></html>');
    }
    echo '</div>';
    
    // Check 2: Table Existence
    echo '<div class="section">';
    echo '<h2>2. Database Table</h2>';
    $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;
    if ($table_exists) {
        echo '<p class="pass">✓ Audit table exists: ' . esc_html($table_name) . '</p>';
        
        // Check table structure
        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}");
        echo '<h3>Table Structure:</h3>';
        echo '<table>';
        echo '<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>';
        $has_post_type = false;
        foreach ($columns as $col) {
            echo '<tr>';
            echo '<td>' . esc_html($col->Field) . '</td>';
            echo '<td>' . esc_html($col->Type) . '</td>';
            echo '<td>' . esc_html($col->Null) . '</td>';
            echo '<td>' . esc_html($col->Key) . '</td>';
            echo '<td>' . esc_html($col->Default) . '</td>';
            echo '</tr>';
            if ($col->Field === 'post_type') {
                $has_post_type = true;
            }
        }
        echo '</table>';
        
        if ($has_post_type) {
            echo '<p class="pass">✓ post_type column exists</p>';
        } else {
            echo '<p class="fail">✗ post_type column is MISSING!</p>';
            echo '<p>This is the problem! Run this SQL to fix it:</p>';
            echo '<pre>ALTER TABLE ' . esc_html($table_name) . ' ADD COLUMN post_type varchar(20) NOT NULL DEFAULT \'post\' AFTER post_id, ADD KEY post_type (post_type);</pre>';
        }
        
        // Check row count
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        echo '<p>Total audit log entries: <strong>' . esc_html($count) . '</strong></p>';
        
        // Show last 5 entries
        $recent = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY timestamp DESC LIMIT 5");
        if ($recent) {
            echo '<h3>Most Recent Entries:</h3>';
            echo '<table>';
            echo '<tr><th>ID</th><th>Post ID</th><th>Post Type</th><th>Algorithm</th><th>Event</th><th>Result</th><th>Timestamp</th></tr>';
            foreach ($recent as $row) {
                echo '<tr>';
                echo '<td>' . esc_html($row->id) . '</td>';
                echo '<td>' . esc_html($row->post_id) . '</td>';
                echo '<td>' . esc_html($row->post_type ?? 'N/A') . '</td>';
                echo '<td>' . esc_html($row->algorithm) . '</td>';
                echo '<td>' . esc_html($row->event_type) . '</td>';
                echo '<td>' . esc_html($row->result) . '</td>';
                echo '<td>' . esc_html($row->timestamp) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }
        
    } else {
        echo '<p class="fail">✗ Audit table does NOT exist</p>';
        echo '<p>The table should be created automatically. Try deactivating and reactivating the plugin.</p>';
    }
    echo '</div>';
    
    // Check 3: Settings
    echo '<div class="section">';
    echo '<h2>3. Plugin Settings</h2>';
    $auto_gen = get_option('archivio_post_auto_generate', false);
    $show_badge = get_option('archivio_post_show_badge', false);
    $show_posts = get_option('archivio_post_show_badge_posts', true);
    $show_pages = get_option('archivio_post_show_badge_pages', true);
    $algorithm = get_option('archivio_hash_algorithm', 'sha256');
    
    echo '<table>';
    echo '<tr><th>Setting</th><th>Value</th><th>Status</th></tr>';
    
    echo '<tr><td>Auto Generate</td><td>' . ($auto_gen ? 'Enabled' : 'Disabled') . '</td>';
    echo '<td>' . ($auto_gen ? '<span class="pass">✓</span>' : '<span class="fail">✗ This must be enabled!</span>') . '</td></tr>';
    
    echo '<tr><td>Show Badge</td><td>' . ($show_badge ? 'Yes' : 'No') . '</td><td>-</td></tr>';
    echo '<tr><td>Show on Posts</td><td>' . ($show_posts ? 'Yes' : 'No') . '</td><td>-</td></tr>';
    echo '<tr><td>Show on Pages</td><td>' . ($show_pages ? 'Yes' : 'No') . '</td><td>-</td></tr>';
    echo '<tr><td>Algorithm</td><td>' . esc_html($algorithm) . '</td><td>-</td></tr>';
    echo '</table>';
    
    if (!$auto_gen) {
        echo '<p class="warning"><strong>⚠ Auto Generate is DISABLED. This is why posts aren\'t being logged!</strong></p>';
        echo '<p>Enable it here: Meta Docs & SEO → Cryptographic Verification → Settings tab</p>';
    }
    echo '</div>';
    
    // Check 4: Hooks
    echo '<div class="section">';
    echo '<h2>4. WordPress Hooks</h2>';
    global $wp_filter;
    
    if (isset($wp_filter['save_post'])) {
        echo '<p class="pass">✓ save_post hooks are registered</p>';
        echo '<h3>Registered Callbacks:</h3>';
        echo '<table>';
        echo '<tr><th>Priority</th><th>Callback</th></tr>';
        foreach ($wp_filter['save_post']->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                $func_name = 'Unknown';
                if (is_array($callback['function'])) {
                    if (is_object($callback['function'][0])) {
                        $func_name = get_class($callback['function'][0]) . '::' . $callback['function'][1];
                    } else {
                        $func_name = $callback['function'][0] . '::' . $callback['function'][1];
                    }
                } else if (is_string($callback['function'])) {
                    $func_name = $callback['function'];
                }
                
                $is_our_hook = strpos($func_name, 'MDSM_Archivio_Post') !== false;
                echo '<tr' . ($is_our_hook ? ' style="background: #ffffcc;"' : '') . '>';
                echo '<td>' . esc_html($priority) . '</td>';
                echo '<td>' . esc_html($func_name) . '</td>';
                echo '</tr>';
            }
        }
        echo '</table>';
    } else {
        echo '<p class="fail">✗ No save_post hooks registered!</p>';
    }
    echo '</div>';
    
    // Check 5: Test Hash Generation
    echo '<div class="section">';
    echo '<h2>5. Test Hash Generation</h2>';
    
    // Get a recent published post
    $test_post = get_posts(array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'numberposts' => 1,
        'orderby' => 'date',
        'order' => 'DESC'
    ));
    
    if ($test_post && !empty($test_post)) {
        $post = $test_post[0];
        echo '<p>Testing with most recent post: <strong>#' . $post->ID . ' - ' . esc_html($post->post_title) . '</strong></p>';
        
        // Check if hash exists
        $existing_hash = get_post_meta($post->ID, '_archivio_post_hash', true);
        if ($existing_hash) {
            echo '<p class="pass">✓ Post has a hash: ' . esc_html(substr($existing_hash, 0, 50)) . '...</p>';
        } else {
            echo '<p class="warning">⚠ Post does not have a hash</p>';
        }
        
        // Try to generate a hash
        try {
            $archivio = MDSM_Archivio_Post::get_instance();
            $result = $archivio->generate_hash($post->ID);
            
            if ($result && is_array($result)) {
                echo '<p class="pass">✓ Hash generation works! Result:</p>';
                echo '<pre>' . print_r($result, true) . '</pre>';
            } else {
                echo '<p class="fail">✗ Hash generation returned false or invalid result</p>';
            }
        } catch (Exception $e) {
            echo '<p class="fail">✗ Error generating hash: ' . esc_html($e->getMessage()) . '</p>';
        }
        
    } else {
        echo '<p class="warning">⚠ No published posts found to test with</p>';
    }
    echo '</div>';
    
    // Check 6: PHP/WordPress Environment
    echo '<div class="section">';
    echo '<h2>6. Environment</h2>';
    echo '<table>';
    echo '<tr><td>PHP Version</td><td>' . phpversion() . '</td></tr>';
    echo '<tr><td>WordPress Version</td><td>' . get_bloginfo('version') . '</td></tr>';
    echo '<tr><td>WP_DEBUG</td><td>' . (defined('WP_DEBUG') && WP_DEBUG ? 'Enabled' : 'Disabled') . '</td></tr>';
    echo '<tr><td>WP_DEBUG_LOG</td><td>' . (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ? 'Enabled' : 'Disabled') . '</td></tr>';
    echo '<tr><td>Current User</td><td>' . wp_get_current_user()->user_login . ' (ID: ' . get_current_user_id() . ')</td></tr>';
    echo '</table>';
    
    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        $log_file = WP_CONTENT_DIR . '/debug.log';
        if (file_exists($log_file)) {
            echo '<p class="pass">✓ Debug log exists at: <span class="code">' . esc_html($log_file) . '</span></p>';
            echo '<p>Check this file for ArchivioMD debug messages when you create/update posts.</p>';
        }
    } else {
        echo '<p class="warning">⚠ Debug logging is disabled. Enable it to see what\'s happening.</p>';
        echo '<p>Add to wp-config.php:</p>';
        echo '<pre>define(\'WP_DEBUG\', true);
define(\'WP_DEBUG_LOG\', true);
define(\'WP_DEBUG_DISPLAY\', false);</pre>';
    }
    echo '</div>';
    
    // Summary and Recommendations
    echo '<div class="section">';
    echo '<h2>Summary & Recommendations</h2>';
    
    $issues = array();
    
    if (!$table_exists) {
        $issues[] = 'The audit table does not exist. Deactivate and reactivate the plugin.';
    } else if (!$has_post_type) {
        $issues[] = 'The post_type column is missing from the audit table. This is the main issue!';
    }
    
    if (!$auto_gen) {
        $issues[] = 'Auto Generate is disabled. Enable it in the plugin settings.';
    }
    
    if (empty($issues)) {
        echo '<p class="pass"><strong>✓ All checks passed!</strong> The system should be working.</p>';
        echo '<p>If logging still doesn\'t work:</p>';
        echo '<ol>';
        echo '<li>Enable