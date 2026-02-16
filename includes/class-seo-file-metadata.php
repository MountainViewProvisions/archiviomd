<?php
/**
 * SEO File Metadata Manager
 * 
 * Handles integrity verification hashes for SEO files (robots.txt, llms.txt).
 * Uses HMAC when properly configured, otherwise falls back to standard hashing.
 * All metadata is stored in WordPress options and logged to audit table.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MDSM_SEO_File_Metadata {
	
	private $audit_table;
	
	const META_OPTION_PREFIX = 'mdsm_seo_meta_';
	
	public function __construct() {
		global $wpdb;
		$this->audit_table = $wpdb->prefix . 'archivio_seo_integrity_log';
	}
	
	/**
	 * Get metadata for an SEO file
	 * 
	 * @param string $file_name The filename (e.g., 'robots.txt')
	 * @return array Metadata array with checksum, algorithm, mode, modified_at
	 */
	public function get_metadata( $file_name ) {
		$option_name = $this->get_option_name( $file_name );
		$metadata = get_option( $option_name, array() );
		
		if ( ! isset( $metadata['checksum'] ) ) {
			$metadata['checksum'] = null;
		}
		if ( ! isset( $metadata['algorithm'] ) ) {
			$metadata['algorithm'] = 'sha256';
		}
		if ( ! isset( $metadata['mode'] ) ) {
			$metadata['mode'] = 'standard';
		}
		if ( ! isset( $metadata['modified_at'] ) ) {
			$metadata['modified_at'] = null;
		}
		
		return $metadata;
	}
	
	/**
	 * Compute hash for SEO file content and update metadata
	 * 
	 * @param string $file_name The filename
	 * @param string $content The file content (without hash comment)
	 * @return array Result with success, metadata, and hash_comment
	 */
	public function compute_and_store_hash( $file_name, $content ) {
		// Normalize line endings for consistent hashing
		$canonical_content = $this->canonicalize_content( $content );
		
		// Compute hash using current algorithm and mode
		$result = MDSM_Hash_Helper::compute_packed( $canonical_content );
		
		// Check if HMAC was requested but unavailable
		if ( $result['hmac_unavailable'] ) {
			return array(
				'success' => false,
				'error'   => 'hmac_unavailable',
				'message' => 'HMAC mode is enabled but ARCHIVIOMD_HMAC_KEY constant is not defined in wp-config.php.'
			);
		}
		
		$checksum  = $result['packed'];
		$algorithm = $result['algorithm'];
		$mode      = $result['mode'];
		$timestamp = $this->get_utc_timestamp();
		$user_id   = get_current_user_id();
		
		// Create metadata
		$metadata = array(
			'checksum'    => $checksum,
			'algorithm'   => $algorithm,
			'mode'        => $mode,
			'modified_at' => $timestamp,
		);
		
		// Save metadata
		$this->save_metadata( $file_name, $metadata );
		
		// Log to audit table
		$this->log_integrity_event(
			$file_name,
			$user_id,
			$checksum,
			$algorithm,
			$mode,
			'generated',
			'success'
		);
		
		// Generate hash comment for file
		$hash_comment = $this->generate_hash_comment( $checksum, $algorithm, $mode );
		
		return array(
			'success'      => true,
			'metadata'     => $metadata,
			'hash_comment' => $hash_comment,
			'mode_used'    => $mode,
		);
	}
	
	/**
	 * Verify file content against stored hash
	 * 
	 * @param string $file_name The filename
	 * @param string $content The file content (without hash comment)
	 * @return array Verification result
	 */
	public function verify_hash( $file_name, $content ) {
		$metadata = $this->get_metadata( $file_name );
		
		if ( empty( $metadata['checksum'] ) ) {
			return array(
				'verified'  => false,
				'message'   => 'No stored hash found',
				'algorithm' => null,
				'mode'      => null,
			);
		}
		
		$canonical_content = $this->canonicalize_content( $content );
		$stored_hash       = $metadata['checksum'];
		$unpacked          = MDSM_Hash_Helper::unpack( $stored_hash );
		
		// Recompute hash with stored algorithm and mode
		$current = MDSM_Hash_Helper::compute_hash_for_verification(
			$canonical_content,
			$unpacked['algorithm'],
			$unpacked['mode']
		);
		
		if ( false === $current ) {
			return array(
				'verified'  => false,
				'message'   => 'Could not recompute hash',
				'algorithm' => $unpacked['algorithm'],
				'mode'      => $unpacked['mode'],
			);
		}
		
		$current_packed = MDSM_Hash_Helper::pack(
			$current['hash'],
			$unpacked['algorithm'],
			$unpacked['mode']
		);
		
		$verified = hash_equals( $stored_hash, $current_packed );
		
		// Log verification
		$this->log_integrity_event(
			$file_name,
			get_current_user_id(),
			$stored_hash,
			$unpacked['algorithm'],
			$unpacked['mode'],
			'verified',
			$verified ? 'success' : 'failed'
		);
		
		return array(
			'verified'      => $verified,
			'message'       => $verified ? 'Hash verified successfully' : 'Hash verification failed',
			'algorithm'     => $unpacked['algorithm'],
			'mode'          => $unpacked['mode'],
			'stored_hash'   => $stored_hash,
			'current_hash'  => $current_packed,
		);
	}
	
	/**
	 * Generate hash comment line for SEO file
	 * 
	 * @param string $packed_hash The packed hash string
	 * @param string $algorithm The algorithm key
	 * @param string $mode The mode (standard or hmac)
	 * @return string The comment line
	 */
	private function generate_hash_comment( $packed_hash, $algorithm, $mode ) {
		$unpacked   = MDSM_Hash_Helper::unpack( $packed_hash );
		$algo_label = MDSM_Hash_Helper::algorithm_label( $algorithm );
		
		if ( $mode === 'hmac' ) {
			return "# HMAC-{$algo_label}: {$unpacked['hash']}";
		}
		
		return "# HASH-{$algo_label}: {$unpacked['hash']}";
	}
	
	/**
	 * Extract hash comment from file content
	 * 
	 * @param string $content The file content
	 * @return string|null The hash comment line or null
	 */
	public function extract_hash_comment( $content ) {
		$lines = explode( "\n", $content );
		
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( preg_match( '/^#\s+(HMAC|HASH)-([A-Z0-9-]+):\s+([a-f0-9]+)$/i', $line ) ) {
				return $line;
			}
		}
		
		return null;
	}
	
	/**
	 * Remove hash comment from content
	 * 
	 * @param string $content The file content
	 * @return string Content without hash comment
	 */
	public function remove_hash_comment( $content ) {
		$lines = explode( "\n", $content );
		$filtered = array();
		
		foreach ( $lines as $line ) {
			$trimmed = trim( $line );
			if ( ! preg_match( '/^#\s+(HMAC|HASH)-([A-Z0-9-]+):\s+([a-f0-9]+)$/i', $trimmed ) ) {
				$filtered[] = $line;
			}
		}
		
		return implode( "\n", $filtered );
	}
	
	/**
	 * Canonicalize content for consistent hashing
	 * 
	 * @param string $content The raw content
	 * @return string Canonicalized content
	 */
	private function canonicalize_content( $content ) {
		// Remove any existing hash comments
		$content = $this->remove_hash_comment( $content );
		
		// Normalize line endings
		$content = str_replace( "\r\n", "\n", $content );
		$content = str_replace( "\r", "\n", $content );
		
		// Trim trailing whitespace from each line
		$lines = explode( "\n", $content );
		$lines = array_map( 'rtrim', $lines );
		
		// Remove trailing empty lines
		while ( count( $lines ) > 0 && trim( $lines[ count( $lines ) - 1 ] ) === '' ) {
			array_pop( $lines );
		}
		
		return implode( "\n", $lines );
	}
	
	/**
	 * Append hash comment to content
	 * 
	 * @param string $content The file content
	 * @param string $hash_comment The hash comment line
	 * @return string Content with hash comment appended
	 */
	public function append_hash_comment( $content, $hash_comment ) {
		// Remove any existing hash comments first
		$content = $this->remove_hash_comment( $content );
		
		// Ensure content ends with newline
		$content = rtrim( $content ) . "\n";
		
		// Append hash comment
		return $content . "\n" . $hash_comment;
	}
	
	/**
	 * Log integrity event to audit table
	 * 
	 * @param string $file_name File name
	 * @param int    $user_id User ID
	 * @param string $checksum The packed checksum
	 * @param string $algorithm Algorithm key
	 * @param string $mode Mode (standard or hmac)
	 * @param string $event_type Event type (generated, verified)
	 * @param string $result Result (success, failed)
	 */
	private function log_integrity_event( $file_name, $user_id, $checksum, $algorithm, $mode, $event_type, $result ) {
		global $wpdb;
		
		// Check if table exists
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $this->audit_table ) ) !== $this->audit_table ) {
			$this->create_audit_table();
		}
		
		$wpdb->insert(
			$this->audit_table,
			array(
				'file_name'  => $file_name,
				'user_id'    => $user_id,
				'checksum'   => $checksum,
				'algorithm'  => $algorithm,
				'mode'       => $mode,
				'event_type' => $event_type,
				'result'     => $result,
				'timestamp'  => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}
	
	/**
	 * Create audit table for SEO file integrity logging
	 */
	public function create_audit_table() {
		global $wpdb;
		
		$charset_collate = $wpdb->get_charset_collate();
		
		$sql = "CREATE TABLE IF NOT EXISTS {$this->audit_table} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			file_name varchar(255) NOT NULL,
			user_id bigint(20) NOT NULL,
			checksum varchar(210) NOT NULL,
			algorithm varchar(20) NOT NULL DEFAULT 'sha256',
			mode varchar(8) NOT NULL DEFAULT 'standard',
			event_type varchar(20) NOT NULL,
			result varchar(20) NOT NULL,
			timestamp datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY file_name (file_name),
			KEY user_id (user_id),
			KEY timestamp (timestamp)
		) {$charset_collate};";
		
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
	
	/**
	 * Get audit logs for a specific file
	 * 
	 * @param string $file_name File name
	 * @param int    $limit Number of records to retrieve
	 * @return array Audit log entries
	 */
	public function get_audit_logs( $file_name = null, $limit = 50 ) {
		global $wpdb;
		
		if ( $file_name ) {
			$logs = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$this->audit_table} WHERE file_name = %s ORDER BY timestamp DESC LIMIT %d",
					$file_name,
					$limit
				)
			);
		} else {
			$logs = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$this->audit_table} ORDER BY timestamp DESC LIMIT %d",
					$limit
				)
			);
		}
		
		return $logs;
	}
	
	/**
	 * Get current timestamp in UTC ISO 8601 format
	 * 
	 * @return string Timestamp in ISO 8601 format
	 */
	private function get_utc_timestamp() {
		return gmdate( 'Y-m-d\TH:i:s\Z' );
	}
	
	/**
	 * Save metadata to WordPress options
	 * 
	 * @param string $file_name The filename
	 * @param array  $metadata The metadata array
	 * @return bool True on success
	 */
	private function save_metadata( $file_name, $metadata ) {
		$option_name = $this->get_option_name( $file_name );
		return update_option( $option_name, $metadata, false );
	}
	
	/**
	 * Get the WordPress option name for a file's metadata
	 * 
	 * @param string $file_name The filename
	 * @return string The option name
	 */
	private function get_option_name( $file_name ) {
		$safe_name = sanitize_key( str_replace( array( '.', '/' ), '_', $file_name ) );
		return self::META_OPTION_PREFIX . $safe_name;
	}
	
	/**
	 * Delete metadata for an SEO file
	 * 
	 * @param string $file_name The filename
	 * @return bool True on success
	 */
	public function delete_metadata( $file_name ) {
		$option_name = $this->get_option_name( $file_name );
		return delete_option( $option_name );
	}
}
