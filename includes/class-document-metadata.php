<?php
/**
 * Document Metadata Manager
 * 
 * Handles canonical document IDs (UUID v4), checksums (SHA-256), 
 * timestamps, and append-only change logs for Markdown documents.
 * All metadata is stored separately from the Markdown files.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MDSM_Document_Metadata {
    
    /**
     * Metadata option prefix
     */
    const META_OPTION_PREFIX = 'mdsm_doc_meta_';
    
    /**
     * Get metadata for a document
     * 
     * @param string $file_name The filename (e.g., 'security.txt.md')
     * @return array Metadata array with uuid, checksum, algorithm, modified_at, changelog
     */
    public function get_metadata($file_name) {
        $option_name = $this->get_option_name($file_name);
        $metadata = get_option($option_name, array());
        
        // Ensure proper structure (backward-compatible defaults)
        if (!isset($metadata['uuid'])) {
            $metadata['uuid'] = null;
        }
        if (!isset($metadata['checksum'])) {
            $metadata['checksum'] = null;
        }
        // Legacy rows that predate 1.3.0 have no 'algorithm' field; default sha256.
        if (!isset($metadata['algorithm'])) {
            $metadata['algorithm'] = 'sha256';
        }
        // Legacy rows that predate 1.4.2 have no 'mode' field; default standard.
        if (!isset($metadata['mode'])) {
            $metadata['mode'] = 'standard';
        }
        if (!isset($metadata['modified_at'])) {
            $metadata['modified_at'] = null;
        }
        if (!isset($metadata['changelog'])) {
            $metadata['changelog'] = array();
        }
        
        return $metadata;
    }
    
    /**
     * Initialize metadata for a new document
     * 
     * @param string $file_name The filename
     * @param string $content The file content
     * @return array The initialized metadata
     */
    public function initialize_metadata($file_name, $content) {
        $uuid   = $this->generate_uuid_v4();
        $result = MDSM_Hash_Helper::compute_packed($content);
        
        // CRITICAL: Check if HMAC was requested but unavailable
        if ($result['hmac_unavailable']) {
            // Don't save - return error to caller
            return array(
                'success' => false,
                'error' => 'hmac_unavailable',
                'message' => 'HMAC mode is enabled but ARCHIVIOMD_HMAC_KEY constant is not defined in wp-config.php.'
            );
        }
        
        $checksum  = $result['packed'];   // algo:hex or hmac-algo:hex
        $algorithm = $result['algorithm'];
        $mode      = $result['mode'];
        $timestamp = $this->get_utc_timestamp();
        $user_id   = get_current_user_id();
        
        $metadata = array(
            'uuid'      => $uuid,
            'checksum'  => $checksum,
            'algorithm' => $algorithm,
            'mode'      => $mode,
            'modified_at' => $timestamp,
            'changelog' => array(
                array(
                    'timestamp' => $timestamp,
                    'user_id'   => $user_id,
                    'action'    => 'created',
                    'checksum'  => $checksum,
                    'algorithm' => $algorithm,
                    'mode'      => $mode,
                )
            )
        );
        
        $this->save_metadata($file_name, $metadata);
        
        return $metadata;
    }
    
    /**
     * Update metadata after a successful file write
     * 
     * @param string $file_name The filename
     * @param string $content The new file content
     * @return array The updated metadata
     */
    public function update_metadata($file_name, $content) {
        $metadata = $this->get_metadata($file_name);
        
        // If no UUID exists, this is a first-time save - initialize
        if (empty($metadata['uuid'])) {
            return $this->initialize_metadata($file_name, $content);
        }
        
        // Compute new checksum with the currently-selected algorithm
        $result = MDSM_Hash_Helper::compute_packed($content);
        
        // CRITICAL: Check if HMAC was requested but unavailable
        if ($result['hmac_unavailable']) {
            // Don't save - return error to caller
            return array(
                'success' => false,
                'error' => 'hmac_unavailable',
                'message' => 'HMAC mode is enabled but ARCHIVIOMD_HMAC_KEY constant is not defined in wp-config.php.'
            );
        }
        
        $checksum  = $result['packed'];
        $algorithm = $result['algorithm'];
        $mode      = $result['mode'];
        $timestamp = $this->get_utc_timestamp();
        $user_id   = get_current_user_id();
        
        // Update metadata
        $metadata['checksum']  = $checksum;
        $metadata['algorithm'] = $algorithm;
        $metadata['mode']      = $mode;
        $metadata['modified_at'] = $timestamp;
        
        // Append to changelog (append-only; each entry records its own algorithm and mode)
        $metadata['changelog'][] = array(
            'timestamp' => $timestamp,
            'user_id'   => $user_id,
            'action'    => 'updated',
            'checksum'  => $checksum,
            'algorithm' => $algorithm,
            'mode'      => $mode,
        );
        
        $this->save_metadata($file_name, $metadata);
        
        return $metadata;
    }
    
    /**
     * Delete metadata for a document
     * 
     * @param string $file_name The filename
     * @return bool True on success
     */
    public function delete_metadata($file_name) {
        $option_name = $this->get_option_name($file_name);
        return delete_option($option_name);
    }
    
    /**
     * Get the UUID for a document (read-only)
     * 
     * @param string $file_name The filename
     * @return string|null The UUID or null if not set
     */
    public function get_uuid($file_name) {
        $metadata = $this->get_metadata($file_name);
        return $metadata['uuid'];
    }
    
    /**
     * Get the checksum for a document
     * 
     * @param string $file_name The filename
     * @return string|null The checksum or null if not set
     */
    public function get_checksum($file_name) {
        $metadata = $this->get_metadata($file_name);
        return $metadata['checksum'];
    }
    
    /**
     * Get the last modified timestamp for a document
     * 
     * @param string $file_name The filename
     * @return string|null The timestamp in ISO 8601 format or null if not set
     */
    public function get_modified_at($file_name) {
        $metadata = $this->get_metadata($file_name);
        return $metadata['modified_at'];
    }
    
    /**
     * Get the changelog for a document (admin only)
     * 
     * @param string $file_name The filename
     * @return array Array of changelog entries
     */
    public function get_changelog($file_name) {
        if (!current_user_can('manage_options')) {
            return array();
        }
        
        $metadata = $this->get_metadata($file_name);
        return $metadata['changelog'];
    }
    
    /**
     * Generate a UUID v4
     * 
     * @return string UUID v4
     */
    private function generate_uuid_v4() {
        // Generate 16 random bytes
        $data = random_bytes(16);
        
        // Set version to 0100 (UUID v4)
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        
        // Set bits 6-7 to 10
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        
        // Format as UUID string
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
    
    /**
     * Get current timestamp in UTC ISO 8601 format
     * 
     * @return string Timestamp in ISO 8601 format
     */
    private function get_utc_timestamp() {
        return gmdate('Y-m-d\TH:i:s\Z');
    }
    
    /**
     * Save metadata to WordPress options
     * 
     * @param string $file_name The filename
     * @param array $metadata The metadata array
     * @return bool True on success
     */
    private function save_metadata($file_name, $metadata) {
        $option_name = $this->get_option_name($file_name);
        return update_option($option_name, $metadata, false);
    }
    
    /**
     * Get the WordPress option name for a file's metadata
     * 
     * @param string $file_name The filename
     * @return string The option name
     */
    private function get_option_name($file_name) {
        // Sanitize filename to create a valid option name
        $safe_name = sanitize_key(str_replace(array('.', '/'), '_', $file_name));
        return self::META_OPTION_PREFIX . $safe_name;
    }
}
