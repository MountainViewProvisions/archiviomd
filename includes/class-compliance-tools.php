<?php
/**
 * Compliance Tools Handler
 * 
 * Handles backend operations for compliance tools:
 * - Metadata Export (CSV)
 * - Backup & Restore
 * - Metadata Verification
 */

if (!defined('ABSPATH')) {
    exit;
}

class MDSM_Compliance_Tools {
    
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
        // Add tools submenu
        add_action('admin_menu', array($this, 'add_tools_menu'), 20);
        
        // Register AJAX handlers
        add_action('wp_ajax_mdsm_export_metadata_csv', array($this, 'ajax_export_metadata_csv'));
        add_action('wp_ajax_mdsm_create_backup_archive', array($this, 'ajax_create_backup_archive'));
        add_action('wp_ajax_mdsm_restore_dryrun', array($this, 'ajax_restore_dryrun'));
        add_action('wp_ajax_mdsm_execute_restore', array($this, 'ajax_execute_restore'));
        add_action('wp_ajax_mdsm_verify_checksums', array($this, 'ajax_verify_checksums'));
        add_action('wp_ajax_mdsm_download_csv', array($this, 'ajax_download_csv'));
        add_action('wp_ajax_mdsm_download_backup', array($this, 'ajax_download_backup'));
        add_action('wp_ajax_mdsm_save_uninstall_cleanup', array($this, 'ajax_save_uninstall_cleanup'));
        
        // Add admin notice about backups
        add_action('admin_notices', array($this, 'show_backup_notice'));
        add_action('wp_ajax_mdsm_dismiss_backup_notice', array($this, 'dismiss_backup_notice'));
    }
    
    /**
     * Add Tools submenu
     */
    public function add_tools_menu() {
        add_submenu_page(
            'archiviomd',
            __('ArchivioMD Compliance', 'archivio-md'),
            __('Metadata Engine', 'archivio-md'),
            'manage_options',
            'archivio-md-compliance',
            array($this, 'render_tools_page')
        );
    }
    
    /**
     * Render tools page
     */
    public function render_tools_page() {
        require_once MDSM_PLUGIN_DIR . 'admin/compliance-tools-page.php';
    }
    
    /**
     * Show dismissible admin notice about backups
     */
    public function show_backup_notice() {
        // Check if notice has been dismissed
        if (get_option('mdsm_backup_notice_dismissed', false)) {
            return;
        }
        
        // Only show to admins
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Show on admin pages
        $screen = get_current_screen();
        if (!$screen || $screen->parent_base === 'options-general') {
            return; // Don't show on settings pages
        }
        
        ?>
        <div class="notice notice-info is-dismissible" id="mdsm-backup-notice">
            <p><strong>ArchivioMD:</strong> Metadata (UUIDs, checksums, changelogs) is stored in your WordPress database. 
            Regular database backups are required for complete data protection. 
            <a href="<?php echo admin_url('tools.php?page=archivio-md-compliance'); ?>">View compliance tools</a></p>
        </div>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#mdsm-backup-notice').on('click', '.notice-dismiss', function() {
                $.post(ajaxurl, {
                    action: 'mdsm_dismiss_backup_notice',
                    nonce: '<?php echo wp_create_nonce('mdsm_dismiss_backup_notice'); ?>'
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Dismiss backup notice
     */
    public function dismiss_backup_notice() {
        check_ajax_referer('mdsm_dismiss_backup_notice', 'nonce');
        
        if (current_user_can('manage_options')) {
            update_option('mdsm_backup_notice_dismissed', true);
            wp_send_json_success();
        }
        
        wp_send_json_error();
    }
    
    /**
     * AJAX: Export metadata to CSV
     */
    public function ajax_export_metadata_csv() {
        check_ajax_referer('mdsm_export_metadata', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        try {
            $csv_data = $this->generate_metadata_csv();
            
            // Save to temp file
            $upload_dir = wp_upload_dir();
            $temp_dir = $upload_dir['basedir'] . '/archivio-md-temp';
            
            if (!file_exists($temp_dir)) {
                wp_mkdir_p($temp_dir);
            }
            
            $timestamp = gmdate('Y-m-d_H-i-s');
            $filename = 'archivio-md-metadata-' . $timestamp . '.csv';
            $filepath = $temp_dir . '/' . $filename;
            
            file_put_contents($filepath, $csv_data);
            
            // Create download URL with nonce
            $download_nonce = wp_create_nonce('mdsm_download_csv_' . $filename);
            $download_url = admin_url('admin-ajax.php?action=mdsm_download_csv&file=' . urlencode($filename) . '&nonce=' . $download_nonce);
            
            wp_send_json_success(array(
                'download_url' => $download_url,
                'filename' => $filename
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Generate metadata CSV content
     */
    private function generate_metadata_csv() {
        $file_manager = new MDSM_File_Manager();
        $metadata_manager = new MDSM_Document_Metadata();
        
        // Get all managed files
        $meta_files = mdsm_get_meta_files();
        $custom_files = mdsm_get_custom_markdown_files();
        
        $csv_rows = array();
        
        // CSV header
        $csv_rows[] = array(
            'UUID',
            'Filename',
            'File Path',
            'Last Modified (UTC)',
            'SHA-256 Checksum',
            'Changelog Count',
            'Changelog Entries (JSON)'
        );
        
        // Process meta files
        foreach ($meta_files as $category => $files) {
            foreach ($files as $file_name => $description) {
                if ($file_manager->file_exists('meta', $file_name)) {
                    $metadata = $metadata_manager->get_metadata($file_name);
                    if (!empty($metadata['uuid'])) {
                        $file_path = $file_manager->get_file_path('meta', $file_name);
                        $csv_rows[] = array(
                            $metadata['uuid'],
                            $file_name,
                            $file_path,
                            $metadata['modified_at'] ?? '',
                            $metadata['checksum'] ?? '',
                            count($metadata['changelog']),
                            json_encode($metadata['changelog'])
                        );
                    }
                }
            }
        }
        
        // Process custom files
        foreach ($custom_files as $file_name => $description) {
            if ($file_manager->file_exists('meta', $file_name)) {
                $metadata = $metadata_manager->get_metadata($file_name);
                if (!empty($metadata['uuid'])) {
                    $file_path = $file_manager->get_file_path('meta', $file_name);
                    $csv_rows[] = array(
                        $metadata['uuid'],
                        $file_name,
                        $file_path,
                        $metadata['modified_at'] ?? '',
                        $metadata['checksum'] ?? '',
                        count($metadata['changelog']),
                        json_encode($metadata['changelog'])
                    );
                }
            }
        }
        
        // Convert to CSV format
        $output = '';
        foreach ($csv_rows as $row) {
            $output .= $this->csv_row($row);
        }
        
        return $output;
    }
    
    /**
     * Format CSV row
     */
    private function csv_row($fields) {
        $escaped = array();
        foreach ($fields as $field) {
            $escaped[] = '"' . str_replace('"', '""', $field) . '"';
        }
        return implode(',', $escaped) . "\n";
    }
    
    /**
     * AJAX: Download CSV file
     */
    public function ajax_download_csv() {
        $filename = isset($_GET['file']) ? sanitize_file_name($_GET['file']) : '';
        $nonce = isset($_GET['nonce']) ? $_GET['nonce'] : '';
        
        if (empty($filename) || !wp_verify_nonce($nonce, 'mdsm_download_csv_' . $filename)) {
            wp_die('Invalid request');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $upload_dir = wp_upload_dir();
        $filepath = $upload_dir['basedir'] . '/archivio-md-temp/' . $filename;
        
        if (!file_exists($filepath)) {
            wp_die('File not found');
        }
        
        // Send file
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        readfile($filepath);
        
        // Delete temp file
        @unlink($filepath);
        
        exit;
    }
    
    /**
     * AJAX: Create backup archive
     */
    public function ajax_create_backup_archive() {
        check_ajax_referer('mdsm_create_backup', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        try {
            $backup_file = $this->create_backup_archive();
            
            if (!$backup_file) {
                wp_send_json_error(array('message' => 'Failed to create backup archive'));
            }
            
            // Create download URL
            $filename = basename($backup_file);
            $download_nonce = wp_create_nonce('mdsm_download_backup_' . $filename);
            $download_url = admin_url('admin-ajax.php?action=mdsm_download_backup&file=' . urlencode($filename) . '&nonce=' . $download_nonce);
            
            wp_send_json_success(array(
                'download_url' => $download_url,
                'filename' => $filename
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Create backup archive
     */
    private function create_backup_archive() {
        $file_manager = new MDSM_File_Manager();
        $metadata_manager = new MDSM_Document_Metadata();
        
        // Get all files with metadata
        $meta_files = mdsm_get_meta_files();
        $custom_files = mdsm_get_custom_markdown_files();
        
        $backup_data = array(
            'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'wordpress_version' => get_bloginfo('version'),
            'plugin_version' => MDSM_VERSION,
            'site_url' => get_site_url(),
            'documents' => array()
        );
        
        // Collect all documents and metadata
        foreach ($meta_files as $category => $files) {
            foreach ($files as $file_name => $description) {
                if ($file_manager->file_exists('meta', $file_name)) {
                    $metadata = $metadata_manager->get_metadata($file_name);
                    if (!empty($metadata['uuid'])) {
                        $content = $file_manager->read_file('meta', $file_name);
                        $backup_data['documents'][$file_name] = array(
                            'metadata' => $metadata,
                            'content' => $content,
                            'category' => $category,
                            'description' => $description
                        );
                    }
                }
            }
        }
        
        foreach ($custom_files as $file_name => $description) {
            if ($file_manager->file_exists('meta', $file_name)) {
                $metadata = $metadata_manager->get_metadata($file_name);
                if (!empty($metadata['uuid'])) {
                    $content = $file_manager->read_file('meta', $file_name);
                    $backup_data['documents'][$file_name] = array(
                        'metadata' => $metadata,
                        'content' => $content,
                        'category' => 'Custom',
                        'description' => $description
                    );
                }
            }
        }
        
        // Create temp directory
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/archivio-md-temp';
        
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }
        
        $timestamp = gmdate('Y-m-d_H-i-s');
        $backup_id = 'backup-' . $timestamp . '-' . substr(md5(uniqid()), 0, 8);
        $backup_dir = $temp_dir . '/' . $backup_id;
        
        wp_mkdir_p($backup_dir);
        wp_mkdir_p($backup_dir . '/documents');
        
        // Save manifest
        $manifest = array(
            'backup_id' => $backup_id,
            'created_at' => $backup_data['created_at'],
            'wordpress_version' => $backup_data['wordpress_version'],
            'plugin_version' => $backup_data['plugin_version'],
            'site_url' => $backup_data['site_url'],
            'document_count' => count($backup_data['documents']),
            'documents' => array()
        );
        
        // Save individual documents and build manifest
        foreach ($backup_data['documents'] as $file_name => $doc_data) {
            // Save metadata
            $metadata_file = $backup_dir . '/documents/' . $file_name . '.meta.json';
            file_put_contents($metadata_file, json_encode($doc_data['metadata'], JSON_PRETTY_PRINT));
            
            // Save content
            $content_file = $backup_dir . '/documents/' . $file_name;
            file_put_contents($content_file, $doc_data['content']);
            
            // Add to manifest
            $manifest['documents'][$file_name] = array(
                'uuid' => $doc_data['metadata']['uuid'],
                'checksum' => $doc_data['metadata']['checksum'],
                'modified_at' => $doc_data['metadata']['modified_at'],
                'category' => $doc_data['category']
            );
        }
        
        file_put_contents($backup_dir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
        
        // Create README
        $readme = "ArchivioMD Backup Archive\n";
        $readme .= "==========================\n\n";
        $readme .= "Created: " . $backup_data['created_at'] . "\n";
        $readme .= "Documents: " . count($backup_data['documents']) . "\n";
        $readme .= "Plugin Version: " . $backup_data['plugin_version'] . "\n\n";
        $readme .= "This archive contains:\n";
        $readme .= "- manifest.json: Backup metadata and checksums\n";
        $readme .= "- documents/: All Markdown files and their metadata\n\n";
        $readme .= "To restore, upload this ZIP file to Tools → ArchivioMD in your WordPress admin.\n";
        
        file_put_contents($backup_dir . '/README.txt', $readme);
        
        // Create ZIP archive
        $zip_file = $temp_dir . '/' . $backup_id . '.zip';
        
        if (!$this->create_zip($backup_dir, $zip_file)) {
            return false;
        }
        
        // Clean up temp directory
        $this->delete_directory($backup_dir);
        
        return $zip_file;
    }
    
    /**
     * Create ZIP archive
     */
    private function create_zip($source, $destination) {
        if (!extension_loaded('zip')) {
            return false;
        }
        
        $zip = new ZipArchive();
        if (!$zip->open($destination, ZipArchive::CREATE)) {
            return false;
        }
        
        $source = realpath($source);
        
        if (is_dir($source)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            
            foreach ($files as $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($source) + 1);
                    $zip->addFile($filePath, $relativePath);
                }
            }
        }
        
        return $zip->close();
    }
    
    /**
     * Delete directory recursively
     */
    private function delete_directory($dir) {
        if (!file_exists($dir)) {
            return true;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->delete_directory($path) : unlink($path);
        }
        
        return rmdir($dir);
    }
    
    /**
     * AJAX: Download backup file
     */
    public function ajax_download_backup() {
        $filename = isset($_GET['file']) ? sanitize_file_name($_GET['file']) : '';
        $nonce = isset($_GET['nonce']) ? $_GET['nonce'] : '';
        
        if (empty($filename) || !wp_verify_nonce($nonce, 'mdsm_download_backup_' . $filename)) {
            wp_die('Invalid request');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $upload_dir = wp_upload_dir();
        $filepath = $upload_dir['basedir'] . '/archivio-md-temp/' . $filename;
        
        if (!file_exists($filepath)) {
            wp_die('File not found');
        }
        
        // Send file
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        readfile($filepath);
        
        // Delete temp file
        @unlink($filepath);
        
        exit;
    }
    
    /**
     * AJAX: Restore dry run (analyze backup)
     */
    public function ajax_restore_dryrun() {
        check_ajax_referer('mdsm_restore_dryrun', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        if (empty($_FILES['backup_file'])) {
            wp_send_json_error(array('message' => 'No backup file uploaded'));
        }
        
        try {
            $analysis = $this->analyze_backup($_FILES['backup_file']);
            wp_send_json_success($analysis);
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Analyze backup file (dry run)
     */
    private function analyze_backup($uploaded_file) {
        // Validate uploaded file
        if ($uploaded_file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Upload failed');
        }
        
        // Extract ZIP to temp directory
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/archivio-md-temp';
        
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }
        
        $extract_dir = $temp_dir . '/restore-' . uniqid();
        wp_mkdir_p($extract_dir);
        
        $zip = new ZipArchive();
        if ($zip->open($uploaded_file['tmp_name']) !== true) {
            throw new Exception('Failed to open backup archive');
        }
        
        $zip->extractTo($extract_dir);
        $zip->close();
        
        // Read manifest
        $manifest_file = $extract_dir . '/manifest.json';
        if (!file_exists($manifest_file)) {
            $this->delete_directory($extract_dir);
            throw new Exception('Invalid backup: manifest.json not found');
        }
        
        $manifest = json_decode(file_get_contents($manifest_file), true);
        
        if (empty($manifest['backup_id']) || empty($manifest['documents'])) {
            $this->delete_directory($extract_dir);
            throw new Exception('Invalid backup manifest');
        }
        
        // Analyze each document
        $file_manager = new MDSM_File_Manager();
        $metadata_manager = new MDSM_Document_Metadata();
        
        $actions = array(
            'restore' => array(),   // New documents
            'overwrite' => array(), // Existing documents that will be overwritten
            'conflict' => array()   // Documents with issues
        );
        
        foreach ($manifest['documents'] as $file_name => $doc_info) {
            $existing_metadata = $metadata_manager->get_metadata($file_name);
            $file_exists = $file_manager->file_exists('meta', $file_name);
            
            if (empty($existing_metadata['uuid'])) {
                // New document - will be restored
                $actions['restore'][] = array(
                    'filename' => $file_name,
                    'uuid' => $doc_info['uuid'],
                    'checksum' => $doc_info['checksum']
                );
            } else {
                // Existing document - will be overwritten
                $actions['overwrite'][] = array(
                    'filename' => $file_name,
                    'existing_checksum' => $existing_metadata['checksum'],
                    'new_checksum' => $doc_info['checksum']
                );
            }
        }
        
        // Store extracted backup info for later use
        $backup_id = $manifest['backup_id'];
        set_transient('mdsm_restore_data_' . $backup_id, array(
            'extract_dir' => $extract_dir,
            'manifest' => $manifest
        ), HOUR_IN_SECONDS);
        
        return array(
            'backup_info' => array(
                'backup_id' => $backup_id,
                'created_at' => $manifest['created_at'],
                'document_count' => $manifest['document_count'],
                'plugin_version' => $manifest['plugin_version'] ?? 'unknown'
            ),
            'actions' => $actions
        );
    }
    
    /**
     * AJAX: Execute restore
     */
    public function ajax_execute_restore() {
        check_ajax_referer('mdsm_execute_restore', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $backup_id = isset($_POST['backup_id']) ? sanitize_text_field($_POST['backup_id']) : '';
        
        if (empty($backup_id)) {
            wp_send_json_error(array('message' => 'Invalid backup ID'));
        }
        
        try {
            $result = $this->execute_restore($backup_id);
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Execute restore operation
     */
    private function execute_restore($backup_id) {
        // Get restore data from transient
        $restore_data = get_transient('mdsm_restore_data_' . $backup_id);
        
        if (empty($restore_data)) {
            throw new Exception('Restore session expired. Please re-upload the backup file.');
        }
        
        $extract_dir = $restore_data['extract_dir'];
        $manifest = $restore_data['manifest'];
        
        if (!file_exists($extract_dir)) {
            throw new Exception('Backup files not found');
        }
        
        $file_manager = new MDSM_File_Manager();
        $metadata_manager = new MDSM_Document_Metadata();
        
        $restored_count = 0;
        $overwritten_count = 0;
        $failed_count = 0;
        
        foreach ($manifest['documents'] as $file_name => $doc_info) {
            try {
                // Read metadata and content from backup
                $metadata_file = $extract_dir . '/documents/' . $file_name . '.meta.json';
                $content_file = $extract_dir . '/documents/' . $file_name;
                
                if (!file_exists($metadata_file) || !file_exists($content_file)) {
                    $failed_count++;
                    continue;
                }
                
                $metadata = json_decode(file_get_contents($metadata_file), true);
                $content = file_get_contents($content_file);
                
                // Check if document exists
                $existing_metadata = $metadata_manager->get_metadata($file_name);
                $is_overwrite = !empty($existing_metadata['uuid']);
                
                // Restore file content
                $result = $file_manager->save_file('meta', $file_name, $content);
                
                if (!$result['success']) {
                    $failed_count++;
                    continue;
                }
                
                // Restore metadata (overwrite with backup metadata, preserving UUIDs)
                $option_name = 'mdsm_doc_meta_' . sanitize_key(str_replace(array('.', '/'), '_', $file_name));
                update_option($option_name, $metadata, false);
                
                if ($is_overwrite) {
                    $overwritten_count++;
                } else {
                    $restored_count++;
                }
                
            } catch (Exception $e) {
                $failed_count++;
            }
        }
        
        // Clean up
        $this->delete_directory($extract_dir);
        delete_transient('mdsm_restore_data_' . $backup_id);
        
        return array(
            'restored_count' => $restored_count,
            'overwritten_count' => $overwritten_count,
            'failed_count' => $failed_count
        );
    }
    
    /**
     * AJAX: Verify checksums
     */
    public function ajax_verify_checksums() {
        check_ajax_referer('mdsm_verify_metadata', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        try {
            $results = $this->verify_all_checksums();
            wp_send_json_success($results);
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Verify all document checksums
     */
    private function verify_all_checksums() {
        $file_manager = new MDSM_File_Manager();
        $metadata_manager = new MDSM_Document_Metadata();
        
        $meta_files = mdsm_get_meta_files();
        $custom_files = mdsm_get_custom_markdown_files();
        
        $results = array();
        $verified = 0;
        $mismatch = 0;
        $missing = 0;
        
        // Check meta files
        foreach ($meta_files as $category => $files) {
            foreach ($files as $file_name => $description) {
                $metadata = $metadata_manager->get_metadata($file_name);
                
                if (empty($metadata['uuid'])) {
                    continue; // Skip files without metadata
                }
                
                if (!$file_manager->file_exists('meta', $file_name)) {
                    $missing++;
                    $results[] = array(
                        'filename' => $file_name,
                        'status' => 'missing',
                        'stored_checksum' => $metadata['checksum']
                    );
                    continue;
                }
                
                // Compute current checksum using the algorithm recorded in stored metadata
                $content = $file_manager->read_file('meta', $file_name);
                $stored_unpacked = MDSM_Hash_Helper::unpack($metadata['checksum']);
                $computed = MDSM_Hash_Helper::compute($content, $stored_unpacked['algorithm']);
                $current_checksum = MDSM_Hash_Helper::pack($computed['hash'], $computed['algorithm']);
                
                if ($current_checksum === $metadata['checksum']) {
                    $verified++;
                    $results[] = array(
                        'filename' => $file_name,
                        'status' => 'verified',
                        'stored_checksum' => $metadata['checksum']
                    );
                } else {
                    $mismatch++;
                    $results[] = array(
                        'filename' => $file_name,
                        'status' => 'mismatch',
                        'stored_checksum' => $metadata['checksum'],
                        'current_checksum' => $current_checksum
                    );
                }
            }
        }
        
        // Check custom files
        foreach ($custom_files as $file_name => $description) {
            $metadata = $metadata_manager->get_metadata($file_name);
            
            if (empty($metadata['uuid'])) {
                continue;
            }
            
            if (!$file_manager->file_exists('meta', $file_name)) {
                $missing++;
                $results[] = array(
                    'filename' => $file_name,
                    'status' => 'missing',
                    'stored_checksum' => $metadata['checksum']
                );
                continue;
            }
            
            $content = $file_manager->read_file('meta', $file_name);
            $stored_unpacked = MDSM_Hash_Helper::unpack($metadata['checksum']);
            $computed = MDSM_Hash_Helper::compute($content, $stored_unpacked['algorithm']);
            $current_checksum = MDSM_Hash_Helper::pack($computed['hash'], $computed['algorithm']);
            
            if ($current_checksum === $metadata['checksum']) {
                $verified++;
                $results[] = array(
                    'filename' => $file_name,
                    'status' => 'verified',
                    'stored_checksum' => $metadata['checksum']
                );
            } else {
                $mismatch++;
                $results[] = array(
                    'filename' => $file_name,
                    'status' => 'mismatch',
                    'stored_checksum' => $metadata['checksum'],
                    'current_checksum' => $current_checksum
                );
            }
        }
        
        return array(
            'verified' => $verified,
            'mismatch' => $mismatch,
            'missing' => $missing,
            'results' => $results
        );
    }
    
    /**
     * AJAX: Save uninstall cleanup settings
     * 
     * COMPLIANCE-CRITICAL: This handler processes the opt-in metadata cleanup preference.
     * It requires explicit administrator permission and proper nonce verification.
     */
    public function ajax_save_uninstall_cleanup() {
        // Verify nonce
        check_ajax_referer('mdsm_uninstall_cleanup_settings', 'nonce');
        
        // Check permissions - admin only
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => 'Insufficient permissions. Only administrators can modify cleanup settings.'
            ));
        }
        
        // Get cleanup preference (sanitize as boolean)
        $cleanup_enabled = isset($_POST['cleanup_enabled']) && $_POST['cleanup_enabled'] === '1';
        
        // Save the opt-in preference
        $result = update_option('mdsm_uninstall_cleanup_enabled', $cleanup_enabled);
        
        if ($result || get_option('mdsm_uninstall_cleanup_enabled') == $cleanup_enabled) {
            // Log the change for audit trail
            $user = wp_get_current_user();
            $action = $cleanup_enabled ? 'ENABLED' : 'DISABLED';
            $log_message = sprintf(
                '[ArchivioMD] Metadata cleanup on uninstall %s by user %s (ID: %d) at %s UTC',
                $action,
                $user->user_login,
                $user->ID,
                gmdate('Y-m-d H:i:s')
            );
            error_log($log_message);
            
            // Prepare response message
            if ($cleanup_enabled) {
                $message = 'Metadata cleanup ENABLED. All ArchivioMD database options will be deleted when the plugin is uninstalled.';
            } else {
                $message = 'Metadata cleanup DISABLED. All metadata will be preserved on uninstall (default behavior).';
            }
            
            wp_send_json_success(array(
                'message' => $message,
                'cleanup_enabled' => $cleanup_enabled
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Failed to save cleanup settings. Please try again.'
            ));
        }
    }
}
