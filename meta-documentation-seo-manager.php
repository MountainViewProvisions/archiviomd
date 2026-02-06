<?php
/**
 * Plugin Name: ArchivioMD
 * Plugin URI: https://mountainviewprovisions.com/ArchivioMD
 * Description: Professional management of meta-documentation files, SEO files (robots.txt, llms.txt), and sitemaps with a beautiful admin interface.
 * Version: 1.0
 * Author: Mountain View Provisions LLC
 * Author URI: https://mountainviewprovisions.com/ArchivioMD
 * Requires at least: 5.0
 * Tested up to: 6.7
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: archivio-md
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MDSM_VERSION', '1.0.0');
define('MDSM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MDSM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MDSM_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Plugin Class
 */
class Meta_Documentation_SEO_Manager {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize plugin
     */
    private function init() {
        // Load required files
        $this->load_dependencies();
        
        // Initialize admin
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
            add_action('admin_notices', array($this, 'show_permalink_notice'));
            add_action('wp_ajax_mdsm_dismiss_permalink_notice', array($this, 'dismiss_permalink_notice'));
        }
        
        // Handle AJAX requests
        add_action('wp_ajax_mdsm_save_file', array($this, 'ajax_save_file'));
        add_action('wp_ajax_mdsm_delete_file', array($this, 'ajax_delete_file'));
        add_action('wp_ajax_mdsm_get_file_content', array($this, 'ajax_get_file_content'));
        add_action('wp_ajax_mdsm_get_file_counts', array($this, 'ajax_get_file_counts'));
        add_action('wp_ajax_mdsm_generate_sitemap', array($this, 'ajax_generate_sitemap'));
        
        // Auto-update sitemaps if enabled
        add_action('save_post', array($this, 'maybe_auto_update_sitemap'));
        add_action('delete_post', array($this, 'maybe_auto_update_sitemap'));
        
        // Add rewrite rules and serve files
        add_action('init', array($this, 'add_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'serve_files'), 1);
        
        // Activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once MDSM_PLUGIN_DIR . 'includes/class-file-manager.php';
        require_once MDSM_PLUGIN_DIR . 'includes/class-sitemap-generator.php';
        require_once MDSM_PLUGIN_DIR . 'includes/file-definitions.php';
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Meta Documentation & SEO', 'meta-doc-seo'),
            __('Meta Docs & SEO', 'meta-doc-seo'),
            'manage_options',
            'meta-doc-seo',
            array($this, 'render_admin_page'),
            'dashicons-media-document',
            30
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if ('toplevel_page_meta-doc-seo' !== $hook) {
            return;
        }
        
        wp_enqueue_style(
            'mdsm-admin-styles',
            MDSM_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            MDSM_VERSION
        );
        
        wp_enqueue_script(
            'mdsm-admin-scripts',
            MDSM_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            MDSM_VERSION,
            true
        );
        
        wp_localize_script('mdsm-admin-scripts', 'mdsmData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mdsm_nonce'),
            'siteUrl' => get_site_url(),
            'strings' => array(
                'saving' => __('Saving...', 'meta-doc-seo'),
                'saved' => __('Saved successfully!', 'meta-doc-seo'),
                'error' => __('Error occurred. Please try again.', 'meta-doc-seo'),
                'confirmDelete' => __('This file will be deleted because it is empty. Continue?', 'meta-doc-seo'),
                'generating' => __('Generating sitemap...', 'meta-doc-seo'),
                'generated' => __('Sitemap generated successfully!', 'meta-doc-seo'),
                'copied' => __('Link copied to clipboard!', 'meta-doc-seo'),
            )
        ));
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        require_once MDSM_PLUGIN_DIR . 'admin/admin-page.php';
    }
    
    /**
     * Show permalink flush notice
     */
    public function show_permalink_notice() {
        // Only show on our plugin page
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'toplevel_page_meta-doc-seo') {
            return;
        }
        
        // Check if notice has been dismissed
        if (get_option('mdsm_permalink_notice_dismissed', false)) {
            return;
        }
        
        ?>
        <div class="notice notice-warning is-dismissible" id="mdsm-permalink-notice">
            <p><strong>Go to Settings → Permalinks and click 'Save Changes' ← CRITICAL!</strong></p>
        </div>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#mdsm-permalink-notice').on('click', '.notice-dismiss', function() {
                $.post(ajaxurl, {
                    action: 'mdsm_dismiss_permalink_notice',
                    nonce: '<?php echo wp_create_nonce('mdsm_dismiss_notice'); ?>'
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Dismiss permalink notice
     */
    public function dismiss_permalink_notice() {
        check_ajax_referer('mdsm_dismiss_notice', 'nonce');
        
        if (current_user_can('manage_options')) {
            update_option('mdsm_permalink_notice_dismissed', true);
            wp_send_json_success();
        }
        
        wp_send_json_error();
    }
    
    /**
     * AJAX: Save file
     */
    public function ajax_save_file() {
        check_ajax_referer('mdsm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $file_type = sanitize_text_field($_POST['file_type']);
        $file_name = sanitize_text_field($_POST['file_name']);
        $content = wp_unslash($_POST['content']); // Don't sanitize content yet, preserve formatting
        
        $file_manager = new MDSM_File_Manager();
        $result = $file_manager->save_file($file_type, $file_name, $content);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX: Delete file
     */
    public function ajax_delete_file() {
        check_ajax_referer('mdsm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $file_type = sanitize_text_field($_POST['file_type']);
        $file_name = sanitize_text_field($_POST['file_name']);
        
        $file_manager = new MDSM_File_Manager();
        $result = $file_manager->delete_file($file_type, $file_name);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX: Get file content
     */
    public function ajax_get_file_content() {
        check_ajax_referer('mdsm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $file_type = sanitize_text_field($_POST['file_type']);
        $file_name = sanitize_text_field($_POST['file_name']);
        
        $file_manager = new MDSM_File_Manager();
        $file_info = $file_manager->get_file_info($file_type, $file_name);
        
        wp_send_json_success($file_info);
    }
    
    /**
     * AJAX: Get file counts
     */
    public function ajax_get_file_counts() {
        check_ajax_referer('mdsm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $file_manager = new MDSM_File_Manager();
        
        $meta_files = mdsm_get_meta_files();
        $meta_total = 0;
        foreach ($meta_files as $category => $files) {
            $meta_total += count($files);
        }
        
        $seo_files = mdsm_get_seo_files();
        $seo_total = count($seo_files);
        
        wp_send_json_success(array(
            'meta_exists' => $file_manager->get_existing_files_count('meta'),
            'meta_total' => $meta_total,
            'seo_exists' => $file_manager->get_existing_files_count('seo'),
            'seo_total' => $seo_total
        ));
    }
    
    /**
     * AJAX: Generate sitemap
     */
    public function ajax_generate_sitemap() {
        check_ajax_referer('mdsm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $sitemap_type = sanitize_text_field($_POST['sitemap_type']);
        $auto_update = isset($_POST['auto_update']) ? (bool) $_POST['auto_update'] : false;
        
        // Save auto-update preference
        update_option('mdsm_auto_update_sitemap', $auto_update);
        update_option('mdsm_sitemap_type', $sitemap_type);
        
        $generator = new MDSM_Sitemap_Generator();
        $result = $generator->generate($sitemap_type);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Maybe auto-update sitemap
     */
    public function maybe_auto_update_sitemap() {
        if (get_option('mdsm_auto_update_sitemap', false)) {
            $sitemap_type = get_option('mdsm_sitemap_type', 'small');
            $generator = new MDSM_Sitemap_Generator();
            $generator->generate($sitemap_type);
        }
    }
    
    /**
     * Add rewrite rules for our files
     */
    public function add_rewrite_rules() {
        // Get all managed files
        $meta_files = mdsm_get_meta_files();
        $seo_files = mdsm_get_seo_files();
        
        $all_files = array();
        
        // Add meta files
        foreach ($meta_files as $category => $files) {
            $all_files = array_merge($all_files, array_keys($files));
        }
        
        // Add SEO files
        $all_files = array_merge($all_files, array_keys($seo_files));
        
        // Add sitemap files
        $all_files[] = 'sitemap.xml';
        $all_files[] = 'sitemap_index.xml';
        
        // Add rewrite rule for each file
        foreach ($all_files as $file) {
            add_rewrite_rule(
                '^' . preg_quote($file) . '$',
                'index.php?mdsm_file=' . $file,
                'top'
            );
        }
        
        // Add pattern for sitemap-*.xml files
        add_rewrite_rule(
            '^sitemap-([^/]+)\.xml$',
            'index.php?mdsm_file=sitemap-$matches[1].xml',
            'top'
        );
    }
    
    /**
     * Add query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'mdsm_file';
        return $vars;
    }
    
    /**
     * Serve files when requested
     */
    public function serve_files() {
        $file = get_query_var('mdsm_file');
        
        if (empty($file)) {
            return; // Not a request for our files
        }
        
        // Check if file exists in root first (physical file takes precedence)
        $root_file = ABSPATH . $file;
        if (file_exists($root_file) && is_readable($root_file)) {
            // Let the physical file be served
            return;
        }
        
        // Determine file type
        $file_type = null;
        if (preg_match('/\.md$/', $file)) {
            $file_type = 'meta';
        } elseif (preg_match('/\.(txt)$/', $file)) {
            $file_type = 'seo';
        } elseif (preg_match('/\.xml$/', $file)) {
            $file_type = 'sitemap';
        }
        
        if (!$file_type) {
            return;
        }
        
        // Get file path
        if ($file_type === 'sitemap') {
            $upload_dir = wp_upload_dir();
            $file_path = $upload_dir['basedir'] . '/meta-docs/' . $file;
        } else {
            $file_manager = new MDSM_File_Manager();
            $file_path = $file_manager->get_file_path($file_type, $file);
        }
        
        // Check if file exists
        if (!$file_path || !file_exists($file_path) || !is_readable($file_path)) {
            status_header(404);
            nocache_headers();
            include(get_404_template());
            exit;
        }
        
        // Serve the file
        $this->output_file($file_path, $file);
    }
    
    /**
     * Output file with proper headers
     */
    private function output_file($filepath, $filename) {
        // Get extension
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        
        // Content types
        $content_types = array(
            'md' => 'text/markdown; charset=utf-8',
            'txt' => 'text/plain; charset=utf-8',
            'xml' => 'application/xml; charset=utf-8',
        );
        
        $content_type = isset($content_types[$ext]) ? $content_types[$ext] : 'text/plain; charset=utf-8';
        
        // Get content
        $content = file_get_contents($filepath);
        
        if ($content === false) {
            status_header(500);
            exit('Error reading file');
        }
        
        // Clear output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Set status and headers
        status_header(200);
        header('Content-Type: ' . $content_type);
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: public, max-age=3600');
        
        // Output
        echo $content;
        exit;
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create directories if needed
        $upload_dir = wp_upload_dir();
        $plugin_dir = $upload_dir['basedir'] . '/meta-docs';
        
        if (!file_exists($plugin_dir)) {
            wp_mkdir_p($plugin_dir);
        }
        
        // Set default options
        add_option('mdsm_auto_update_sitemap', false);
        add_option('mdsm_sitemap_type', 'small');
        
        // Add rewrite rules and flush
        $this->add_rewrite_rules();
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules to remove our custom rules
        flush_rewrite_rules();
    }
}

// Initialize plugin
function mdsm_init() {
    return Meta_Documentation_SEO_Manager::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'mdsm_init');
