<?php
/**
 * External Anchoring System
 *
 * Provider-agnostic, asynchronous document anchoring for ArchivioMD.
 * Supports GitHub and GitLab. Designed so future providers (RFC 3161,
 * blockchain, etc.) can be added by implementing MDSM_Anchor_Provider_Interface.
 *
 * Architecture:
 *  - MDSM_External_Anchoring  — singleton facade / entry point
 *  - MDSM_Anchor_Queue        — persistent WP-options queue with exponential backoff
 *  - MDSM_Anchor_Provider_*   — concrete REST-API providers
 *
 * Zero hard dependencies on HMAC. Works in Basic Mode (SHA-256 / SHA-512 / BLAKE2b).
 *
 * @package ArchivioMD
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Provider interface ───────────────────────────────────────────────────────

/**
 * Every provider must implement this interface.
 *
 * push() must return:
 *   [ 'success' => true,  'url' => 'https://...' ]
 *   [ 'success' => false, 'error' => '...', 'retry' => bool, 'rate_limited' => bool ]
 */
interface MDSM_Anchor_Provider_Interface {

	/**
	 * Push an anchor record to the remote repository.
	 *
	 * @param array  $record   Fully-formed anchor record array.
	 * @param array  $settings Provider-specific settings.
	 * @return array           Result array as described above.
	 */
	public function push( array $record, array $settings );

	/**
	 * Test the connection without storing any data.
	 *
	 * @param array $settings Provider-specific settings.
	 * @return array [ 'success' => bool, 'message' => string ]
	 */
	public function test_connection( array $settings );
}

// ── Queue ────────────────────────────────────────────────────────────────────

/**
 * Persistent, ordered anchor queue backed by wp_options.
 * Each item carries retry state and exponential back-off metadata.
 */
class MDSM_Anchor_Queue {

	const OPTION_KEY      = 'mdsm_anchor_queue';
	const MAX_RETRIES     = 5;
	const BASE_DELAY_SECS = 60;   // 1 min → 2 → 4 → 8 → 16 minutes

	/**
	 * Add a new job to the queue.
	 *
	 * @param array $record Anchor record payload.
	 * @return string       Unique job ID.
	 */
	public static function enqueue( array $record ) {
		$queue  = self::load();
		$job_id = self::generate_job_id();

		$queue[ $job_id ] = array(
			'id'           => $job_id,
			'record'       => $record,
			'attempts'     => 0,
			'next_attempt' => time(),
			'created_at'   => time(),
			'last_error'   => '',
		);

		self::save( $queue );
		return $job_id;
	}

	/**
	 * Return jobs that are due for processing (next_attempt <= now).
	 *
	 * @return array Keyed by job_id.
	 */
	public static function get_due_jobs() {
		$queue = self::load();
		$now   = time();
		$due   = array();

		foreach ( $queue as $job_id => $job ) {
			if ( (int) $job['next_attempt'] <= $now ) {
				$due[ $job_id ] = $job;
			}
		}

		return $due;
	}

	/**
	 * Mark a job as successfully completed — removes it from the queue.
	 *
	 * @param string $job_id
	 */
	public static function mark_success( $job_id ) {
		$queue = self::load();
		unset( $queue[ $job_id ] );
		self::save( $queue );
	}

	/**
	 * Record a failed attempt and schedule exponential back-off or discard.
	 *
	 * @param string $job_id
	 * @param string $error_message
	 * @param bool   $retryable     If false the job is discarded immediately.
	 * @return bool                 True if rescheduled, false if discarded.
	 */
	public static function mark_failure( $job_id, $error_message, $retryable = true ) {
		$queue = self::load();

		if ( ! isset( $queue[ $job_id ] ) ) {
			return false;
		}

		$job             = $queue[ $job_id ];
		$job['attempts'] = (int) $job['attempts'] + 1;
		$job['last_error'] = $error_message;

		if ( ! $retryable || $job['attempts'] >= self::MAX_RETRIES ) {
			unset( $queue[ $job_id ] );
			self::save( $queue );
			return false;
		}

		// Exponential back-off: 60s * 2^(attempts-1), capped at 24 h.
		$delay                 = min( self::BASE_DELAY_SECS * pow( 2, $job['attempts'] - 1 ), 86400 );
		$job['next_attempt']   = time() + (int) $delay;
		$queue[ $job_id ]      = $job;

		self::save( $queue );
		return true;
	}

	/**
	 * Return the total number of pending jobs.
	 *
	 * @return int
	 */
	public static function count() {
		return count( self::load() );
	}

	/**
	 * Wipe the entire queue. Use with caution (admin-only action).
	 */
	public static function clear() {
		self::save( array() );
	}

	// ── Private helpers ──────────────────────────────────────────────────────

	private static function load() {
		$data = get_option( self::OPTION_KEY, array() );
		return is_array( $data ) ? $data : array();
	}

	private static function save( array $queue ) {
		update_option( self::OPTION_KEY, $queue, false );
	}

	private static function generate_job_id() {
		return 'anchor_' . uniqid( '', true );
	}
}

// ── GitHub provider ──────────────────────────────────────────────────────────

/**
 * GitHub REST API provider.
 * Docs: https://docs.github.com/en/rest/repos/contents
 */
class MDSM_Anchor_Provider_GitHub implements MDSM_Anchor_Provider_Interface {

	private function api_url( $owner, $repo, $path ) {
		$owner = rawurlencode( $owner );
		$repo  = rawurlencode( $repo );
		return "https://api.github.com/repos/{$owner}/{$repo}/contents/{$path}";
	}

	private function headers( $token ) {
		return array(
			'Authorization' => 'Bearer ' . $token,
			'Accept'        => 'application/vnd.github+json',
			'X-GitHub-Api-Version' => '2022-11-28',
			'User-Agent'    => 'ArchivioMD/' . MDSM_VERSION,
			'Content-Type'  => 'application/json',
		);
	}

	public function push( array $record, array $settings ) {
		$token        = $settings['token'];
		$owner        = $settings['repo_owner'];
		$repo         = $settings['repo_name'];
		$branch       = $settings['branch'];
		$folder       = rtrim( $settings['folder_path'], '/' );
		$commit_tpl   = isset( $settings['commit_message'] ) ? $settings['commit_message'] : 'chore: anchor {doc_id}';

		// Build file path.
		$folder   = $this->resolve_folder( $folder );
		$filename = sanitize_file_name( $record['document_id'] ) . '-' . gmdate( 'YmdHis' ) . '.json';
		$path     = ltrim( $folder . '/' . $filename, '/' );

		$json_body = wp_json_encode( $record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		$content   = base64_encode( $json_body );
		$message   = str_replace( '{doc_id}', $record['document_id'], $commit_tpl );

		$url = $this->api_url( $owner, $repo, $path );

		// Check for existing file SHA (required by GitHub to update an existing file).
		$existing_sha = $this->get_file_sha( $url, $token );

		$payload = array(
			'message' => $message,
			'content' => $content,
			'branch'  => $branch,
		);
		if ( false !== $existing_sha ) {
			$payload['sha'] = $existing_sha;
		}

		// GitHub Contents API uses PUT for both create and update.
		// Body must be JSON. WordPress's HTTP API will honour Content-Type
		// when the body is a pre-encoded string — we set it explicitly here.
		$response = wp_remote_request( $url, array(
			'method'     => 'PUT',
			'headers'    => $this->headers( $token ),
			'body'       => wp_json_encode( $payload ),
			'timeout'    => 25,
			'data_format' => 'body',
		) );

		return $this->parse_response( $response );
	}

	private function get_file_sha( $url, $token ) {
		$response = wp_remote_get( $url, array(
			'headers' => $this->headers( $token ),
			'timeout' => 10,
		) );
		if ( is_wp_error( $response ) ) {
			return false;
		}
		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return false;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return isset( $data['sha'] ) ? $data['sha'] : false;
	}

	public function test_connection( array $settings ) {
		$token = $settings['token'];
		$owner = $settings['repo_owner'];
		$repo  = $settings['repo_name'];
		$branch = $settings['branch'];

		// 1. Verify repo exists.
		$repo_url = "https://api.github.com/repos/" . rawurlencode( $owner ) . '/' . rawurlencode( $repo );
		$response = wp_remote_get( $repo_url, array(
			'headers' => $this->headers( $token ),
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'message' => $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 401 === $code ) {
			return array( 'success' => false, 'message' => __( 'Authentication failed. Check your Personal Access Token.', 'archiviomd' ) );
		}
		if ( 403 === $code ) {
			return array( 'success' => false, 'message' => __( 'Access forbidden. Ensure the token has repo scope.', 'archiviomd' ) );
		}
		if ( 404 === $code ) {
			return array( 'success' => false, 'message' => __( 'Repository not found. Check owner and repository name.', 'archiviomd' ) );
		}
		if ( $code < 200 || $code > 299 ) {
			return array( 'success' => false, 'message' => sprintf( __( 'Unexpected HTTP %d response from GitHub.', 'archiviomd' ), $code ) );
		}

		// 2. Verify branch exists.
		$branch_url = "https://api.github.com/repos/" . rawurlencode( $owner ) . '/' . rawurlencode( $repo ) . '/branches/' . rawurlencode( $branch );
		$b_response = wp_remote_get( $branch_url, array(
			'headers' => $this->headers( $token ),
			'timeout' => 15,
		) );

		if ( is_wp_error( $b_response ) ) {
			return array( 'success' => false, 'message' => $b_response->get_error_message() );
		}
		$b_code = wp_remote_retrieve_response_code( $b_response );
		if ( 404 === $b_code ) {
			return array( 'success' => false, 'message' => sprintf( __( 'Branch "%s" not found in repository.', 'archiviomd' ), esc_html( $branch ) ) );
		}
		if ( $b_code < 200 || $b_code > 299 ) {
			return array( 'success' => false, 'message' => sprintf( __( 'Unexpected HTTP %d verifying branch.', 'archiviomd' ), $b_code ) );
		}

		return array( 'success' => true, 'message' => __( 'Connection successful. Repository and branch verified.', 'archiviomd' ) );
	}

	private function parse_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return array(
				'success'      => false,
				'error'        => $response->get_error_message(),
				'retry'        => true,
				'rate_limited' => false,
				'http_status'  => 0,
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( in_array( $code, array( 200, 201 ), true ) ) {
			$url = isset( $body['content']['html_url'] ) ? $body['content']['html_url'] : '';
			return array( 'success' => true, 'url' => $url, 'http_status' => $code );
		}

		$message = isset( $body['message'] ) ? $body['message'] : "HTTP {$code}";

		if ( 429 === $code ) {
			return array( 'success' => false, 'error' => 'Rate limited: ' . $message, 'retry' => true, 'rate_limited' => true, 'http_status' => $code );
		}
		if ( in_array( $code, array( 401, 403 ), true ) ) {
			return array( 'success' => false, 'error' => 'Auth error: ' . $message, 'retry' => false, 'rate_limited' => false, 'http_status' => $code );
		}
		if ( 409 === $code ) {
			return array( 'success' => false, 'error' => 'Conflict: ' . $message, 'retry' => true, 'rate_limited' => false, 'http_status' => $code );
		}
		if ( 404 === $code ) {
			return array( 'success' => false, 'error' => 'Not found: ' . $message, 'retry' => false, 'rate_limited' => false, 'http_status' => $code );
		}

		return array( 'success' => false, 'error' => "HTTP {$code}: {$message}", 'retry' => true, 'rate_limited' => false, 'http_status' => $code );
	}

	private function resolve_folder( $folder ) {
		// Replace date tokens.
		$folder = str_replace( 'YYYY', gmdate( 'Y' ), $folder );
		$folder = str_replace( 'MM',   gmdate( 'm' ), $folder );
		$folder = str_replace( 'DD',   gmdate( 'd' ), $folder );
		return trim( $folder, '/' );
	}
}

// ── GitLab provider ──────────────────────────────────────────────────────────

/**
 * GitLab REST API provider.
 * Docs: https://docs.gitlab.com/ee/api/repository_files.html
 */
class MDSM_Anchor_Provider_GitLab implements MDSM_Anchor_Provider_Interface {

	private function api_base() {
		return 'https://gitlab.com/api/v4';
	}

	private function encoded_path( $path ) {
		return rawurlencode( $path );
	}

	private function headers( $token ) {
		return array(
			'Authorization' => 'Bearer ' . $token,
			'Content-Type'  => 'application/json',
			'User-Agent'    => 'ArchivioMD/' . MDSM_VERSION,
		);
	}

	public function push( array $record, array $settings ) {
		$token      = $settings['token'];
		$owner      = $settings['repo_owner'];
		$repo       = $settings['repo_name'];
		$branch     = $settings['branch'];
		$folder     = rtrim( $settings['folder_path'], '/' );
		$commit_tpl = isset( $settings['commit_message'] ) ? $settings['commit_message'] : 'chore: anchor {doc_id}';

		$folder   = $this->resolve_folder( $folder );
		$filename = sanitize_file_name( $record['document_id'] ) . '-' . gmdate( 'YmdHis' ) . '.json';
		$path     = ltrim( $folder . '/' . $filename, '/' );
		$message  = str_replace( '{doc_id}', $record['document_id'], $commit_tpl );

		$project_id = $this->get_project_id( $owner, $repo, $token );
		if ( false === $project_id ) {
			return array( 'success' => false, 'error' => 'GitLab project not found.', 'retry' => false, 'rate_limited' => false, 'http_status' => 404 );
		}

		// GitLab accepts plain JSON content directly when encoding = 'text'.
		$json_body = wp_json_encode( $record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		// Check if file exists to determine create (POST) vs update (PUT).
		$exists = $this->file_exists( $project_id, $path, $branch, $token );
		$method = $exists ? 'PUT' : 'POST';

		$url = $this->api_base() . '/projects/' . $project_id . '/repository/files/' . $this->encoded_path( $path );

		$payload = array(
			'branch'         => $branch,
			'commit_message' => $message,
			'content'        => $json_body,
			'encoding'       => 'text',
		);

		// data_format = 'body' tells WP's HTTP API to send the body string as-is,
		// preserving the Content-Type: application/json header we set in headers().
		$response = wp_remote_request( $url, array(
			'method'      => $method,
			'headers'     => $this->headers( $token ),
			'body'        => wp_json_encode( $payload ),
			'timeout'     => 25,
			'data_format' => 'body',
		) );

		return $this->parse_response( $response );
	}

	private function get_project_id( $owner, $repo, $token ) {
		$namespace = rawurlencode( $owner . '/' . $repo );
		$url       = $this->api_base() . '/projects/' . $namespace;
		$response  = wp_remote_get( $url, array(
			'headers' => $this->headers( $token ),
			'timeout' => 10,
		) );
		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return false;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return isset( $data['id'] ) ? (int) $data['id'] : false;
	}

	private function file_exists( $project_id, $path, $branch, $token ) {
		$url      = $this->api_base() . '/projects/' . $project_id . '/repository/files/' . $this->encoded_path( $path ) . '?ref=' . rawurlencode( $branch );
		$response = wp_remote_get( $url, array(
			'headers' => $this->headers( $token ),
			'timeout' => 10,
		) );
		if ( is_wp_error( $response ) ) {
			return false;
		}
		return wp_remote_retrieve_response_code( $response ) === 200;
	}

	public function test_connection( array $settings ) {
		$token  = $settings['token'];
		$owner  = $settings['repo_owner'];
		$repo   = $settings['repo_name'];
		$branch = $settings['branch'];

		$namespace = rawurlencode( $owner . '/' . $repo );
		$url       = $this->api_base() . '/projects/' . $namespace;

		$response = wp_remote_get( $url, array(
			'headers' => $this->headers( $token ),
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'message' => $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 401 === $code ) {
			return array( 'success' => false, 'message' => __( 'Authentication failed. Check your Personal Access Token.', 'archiviomd' ) );
		}
		if ( 403 === $code ) {
			return array( 'success' => false, 'message' => __( 'Access forbidden. Ensure the token has api scope.', 'archiviomd' ) );
		}
		if ( 404 === $code ) {
			return array( 'success' => false, 'message' => __( 'Project not found. Check group/user and project name.', 'archiviomd' ) );
		}
		if ( $code < 200 || $code > 299 ) {
			return array( 'success' => false, 'message' => sprintf( __( 'Unexpected HTTP %d response from GitLab.', 'archiviomd' ), $code ) );
		}

		// Verify branch.
		$data       = json_decode( wp_remote_retrieve_body( $response ), true );
		$project_id = isset( $data['id'] ) ? (int) $data['id'] : 0;

		$b_url      = $this->api_base() . '/projects/' . $project_id . '/repository/branches/' . rawurlencode( $branch );
		$b_response = wp_remote_get( $b_url, array(
			'headers' => $this->headers( $token ),
			'timeout' => 15,
		) );

		if ( is_wp_error( $b_response ) ) {
			return array( 'success' => false, 'message' => $b_response->get_error_message() );
		}
		$b_code = wp_remote_retrieve_response_code( $b_response );
		if ( 404 === $b_code ) {
			return array( 'success' => false, 'message' => sprintf( __( 'Branch "%s" not found in project.', 'archiviomd' ), esc_html( $branch ) ) );
		}
		if ( $b_code < 200 || $b_code > 299 ) {
			return array( 'success' => false, 'message' => sprintf( __( 'Unexpected HTTP %d verifying branch.', 'archiviomd' ), $b_code ) );
		}

		return array( 'success' => true, 'message' => __( 'Connection successful. Project and branch verified.', 'archiviomd' ) );
	}

	private function parse_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'error' => $response->get_error_message(), 'retry' => true, 'rate_limited' => false, 'http_status' => 0 );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( in_array( $code, array( 200, 201 ), true ) ) {
			$url = isset( $body['file_path'] ) ? $body['file_path'] : '';
			return array( 'success' => true, 'url' => $url, 'http_status' => $code );
		}

		$message = isset( $body['message'] ) ? ( is_string( $body['message'] ) ? $body['message'] : wp_json_encode( $body['message'] ) ) : "HTTP {$code}";

		if ( 429 === $code ) {
			return array( 'success' => false, 'error' => 'Rate limited: ' . $message, 'retry' => true, 'rate_limited' => true, 'http_status' => $code );
		}
		if ( in_array( $code, array( 401, 403 ), true ) ) {
			return array( 'success' => false, 'error' => 'Auth error: ' . $message, 'retry' => false, 'rate_limited' => false, 'http_status' => $code );
		}
		if ( 409 === $code ) {
			return array( 'success' => false, 'error' => 'Conflict: ' . $message, 'retry' => true, 'rate_limited' => false, 'http_status' => $code );
		}
		if ( 404 === $code ) {
			return array( 'success' => false, 'error' => 'Not found: ' . $message, 'retry' => false, 'rate_limited' => false, 'http_status' => $code );
		}

		return array( 'success' => false, 'error' => "HTTP {$code}: {$message}", 'retry' => true, 'rate_limited' => false, 'http_status' => $code );
	}

	private function resolve_folder( $folder ) {
		$folder = str_replace( 'YYYY', gmdate( 'Y' ), $folder );
		$folder = str_replace( 'MM',   gmdate( 'm' ), $folder );
		$folder = str_replace( 'DD',   gmdate( 'd' ), $folder );
		return trim( $folder, '/' );
	}
}

// ── Main facade ──────────────────────────────────────────────────────────────

/**
 * MDSM_External_Anchoring
 *
 * Singleton entry point. Callers only ever touch this class.
 *
 * Usage:
 *   MDSM_External_Anchoring::get_instance()->queue_post_anchor( $post_id, $hash_result );
 *   MDSM_External_Anchoring::get_instance()->queue_document_anchor( $file_name, $metadata );
 */
class MDSM_External_Anchoring {

	const CRON_HOOK        = 'mdsm_process_anchor_queue';
	const CRON_INTERVAL    = 'mdsm_anchor_interval';
	const SETTINGS_OPTION  = 'mdsm_anchor_settings';
	const AUDIT_LOG_ACTION = 'mdsm_anchor_audit_log';

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->register_hooks();
	}

	// ── Hooks ─────────────────────────────────────────────────────────────────

	private function register_hooks() {
		// Register custom cron interval.
		add_filter( 'cron_schedules', array( $this, 'register_cron_interval' ) );

		// Cron processor.
		add_action( self::CRON_HOOK, array( $this, 'process_queue' ) );

		// Ensure cron is scheduled.
		add_action( 'init', array( $this, 'ensure_cron_scheduled' ) );

		// Admin AJAX handlers (settings + test).
		add_action( 'wp_ajax_mdsm_anchor_save_settings',   array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_mdsm_anchor_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_mdsm_anchor_clear_queue',     array( $this, 'ajax_clear_queue' ) );
		add_action( 'wp_ajax_mdsm_anchor_queue_status',    array( $this, 'ajax_queue_status' ) );
		add_action( 'wp_ajax_mdsm_anchor_get_log',         array( $this, 'ajax_get_anchor_log' ) );
		add_action( 'wp_ajax_mdsm_anchor_clear_log',       array( $this, 'ajax_clear_anchor_log' ) );
		add_action( 'wp_ajax_mdsm_anchor_download_log',    array( $this, 'ajax_download_anchor_log' ) );

		// Admin menu and asset enqueueing.
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 20 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		}
	}

	// ── Cron ─────────────────────────────────────────────────────────────────

	public function register_cron_interval( $schedules ) {
		$schedules[ self::CRON_INTERVAL ] = array(
			'interval' => 300,   // every 5 minutes
			'display'  => __( 'Every 5 Minutes (ArchivioMD Anchoring)', 'archiviomd' ),
		);
		return $schedules;
	}

	public function ensure_cron_scheduled() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), self::CRON_INTERVAL, self::CRON_HOOK );
		}
	}

	public static function activate_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), self::CRON_INTERVAL, self::CRON_HOOK );
		}
	}

	public static function deactivate_cron() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	// ── Public API: queue anchoring jobs ─────────────────────────────────────

	/**
	 * Queue anchoring for a WordPress post or page.
	 *
	 * @param int   $post_id
	 * @param array $hash_result  Full result from MDSM_Hash_Helper::compute_packed()
	 */
	public function queue_post_anchor( $post_id, array $hash_result ) {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$unpacked   = MDSM_Hash_Helper::unpack( $hash_result['packed'] );
		$is_hmac    = ( $unpacked['mode'] === MDSM_Hash_Helper::MODE_HMAC );
		$hmac_value = $is_hmac ? $hash_result['hash'] : null;

		$record = array(
			'document_id'    => 'post-' . $post_id,
			'post_id'        => $post_id,
			'post_type'      => $post->post_type,
			'post_title'     => $post->post_title,
			'hash_algorithm' => $hash_result['algorithm'],
			'hash_value'     => $hash_result['hash'],
			'hmac_value'     => $hmac_value,
			'integrity_mode' => $is_hmac ? 'HMAC' : 'Basic',
			'author'         => get_the_author_meta( 'display_name', $post->post_author ),
			'timestamp_utc'  => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'plugin_version' => MDSM_VERSION,
			'site_url'       => get_site_url(),
		);

		MDSM_Anchor_Queue::enqueue( $record );
	}

	/**
	 * Queue anchoring for a native Markdown document.
	 *
	 * @param string $file_name   Markdown filename (e.g. 'security.txt.md')
	 * @param array  $metadata    From MDSM_Document_Metadata::update_metadata() or initialize_metadata()
	 * @param array  $hash_result Full packed hash result array
	 */
	public function queue_document_anchor( $file_name, array $metadata, array $hash_result ) {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$unpacked   = MDSM_Hash_Helper::unpack( $hash_result['packed'] );
		$is_hmac    = ( $unpacked['mode'] === MDSM_Hash_Helper::MODE_HMAC );
		$hmac_value = $is_hmac ? $hash_result['hash'] : null;

		$user = wp_get_current_user();

		$record = array(
			'document_id'    => isset( $metadata['uuid'] ) ? $metadata['uuid'] : 'doc-' . sanitize_key( $file_name ),
			'post_id'        => null,
			'post_type'      => 'archivio_document',
			'document_name'  => $file_name,
			'hash_algorithm' => $hash_result['algorithm'],
			'hash_value'     => $hash_result['hash'],
			'hmac_value'     => $hmac_value,
			'integrity_mode' => $is_hmac ? 'HMAC' : 'Basic',
			'author'         => $user ? $user->display_name : 'unknown',
			'timestamp_utc'  => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'plugin_version' => MDSM_VERSION,
			'site_url'       => get_site_url(),
		);

		MDSM_Anchor_Queue::enqueue( $record );
	}

	/**
	 * Queue anchoring for a generated HTML file.
	 *
	 * @param string $html_filename
	 * @param string $html_content  Raw HTML content (for hashing)
	 */
	public function queue_html_anchor( $html_filename, $html_content ) {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$hash_result = MDSM_Hash_Helper::compute_packed( $html_content );
		if ( $hash_result['hmac_unavailable'] ) {
			$hash_result = MDSM_Hash_Helper::compute_packed( $html_content );
		}

		$unpacked   = MDSM_Hash_Helper::unpack( $hash_result['packed'] );
		$is_hmac    = ( $unpacked['mode'] === MDSM_Hash_Helper::MODE_HMAC );
		$hmac_value = $is_hmac ? $hash_result['hash'] : null;

		$user = wp_get_current_user();

		$record = array(
			'document_id'    => 'html-' . sanitize_key( $html_filename ) . '-' . gmdate( 'Ymd' ),
			'post_id'        => null,
			'post_type'      => 'archivio_html_output',
			'document_name'  => $html_filename,
			'hash_algorithm' => $hash_result['algorithm'],
			'hash_value'     => $hash_result['hash'],
			'hmac_value'     => $hmac_value,
			'integrity_mode' => $is_hmac ? 'HMAC' : 'Basic',
			'author'         => $user ? $user->display_name : 'system',
			'timestamp_utc'  => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'plugin_version' => MDSM_VERSION,
			'site_url'       => get_site_url(),
		);

		MDSM_Anchor_Queue::enqueue( $record );
	}

	// ── Queue processor ───────────────────────────────────────────────────────

	/**
	 * Process due anchor jobs. Called by WP-Cron.
	 * Never throws — all errors are caught and logged.
	 */
	public function process_queue() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$settings = $this->get_settings();
		$provider = $this->make_provider( $settings['provider'] );

		if ( null === $provider ) {
			return;
		}

		$due_jobs = MDSM_Anchor_Queue::get_due_jobs();

		if ( empty( $due_jobs ) ) {
			return;
		}

		foreach ( $due_jobs as $job_id => $job ) {
			// Stamp the provider name into the record so the log can show it.
			$record             = $job['record'];
			$record['_provider'] = $settings['provider'];
			$attempt_number     = (int) $job['attempts'] + 1;

			try {
				$result = $provider->push( $record, $settings );

				if ( $result['success'] ) {
					MDSM_Anchor_Queue::mark_success( $job_id );
					$anchor_url = isset( $result['url'] ) ? $result['url'] : '';

					MDSM_Anchor_Log::write(
						$record,
						$job_id,
						$attempt_number,
						'anchored',
						$anchor_url,
						'',
						0
					);

					$this->write_audit_log( $record, 'anchored', $anchor_url, '' );

				} else {
					$error_msg   = isset( $result['error'] ) ? $result['error'] : 'Unknown error';
					$retryable   = isset( $result['retry'] ) ? (bool) $result['retry'] : true;
					$http_code   = isset( $result['http_status'] ) ? (int) $result['http_status'] : 0;
					$rescheduled = MDSM_Anchor_Queue::mark_failure( $job_id, $error_msg, $retryable );
					$log_status  = $rescheduled ? 'retry' : 'failed';

					MDSM_Anchor_Log::write(
						$record,
						$job_id,
						$attempt_number,
						$log_status,
						'',
						$error_msg,
						$http_code
					);

					$this->write_audit_log( $record, $rescheduled ? 'anchor_retry' : 'anchor_failed', '', $error_msg );
				}
			} catch ( \Throwable $e ) {
				MDSM_Anchor_Queue::mark_failure( $job_id, $e->getMessage(), true );

				MDSM_Anchor_Log::write(
					$record,
					$job_id,
					$attempt_number,
					'failed',
					'',
					$e->getMessage() . ' (PHP ' . get_class( $e ) . ')',
					0
				);

				$this->write_audit_log( $record, 'anchor_failed', '', $e->getMessage() );

			} catch ( \Exception $e ) {
				MDSM_Anchor_Queue::mark_failure( $job_id, $e->getMessage(), true );

				MDSM_Anchor_Log::write(
					$record,
					$job_id,
					$attempt_number,
					'failed',
					'',
					$e->getMessage() . ' (PHP ' . get_class( $e ) . ')',
					0
				);

				$this->write_audit_log( $record, 'anchor_failed', '', $e->getMessage() );
			}
		}
	}

	// ── Audit log integration ─────────────────────────────────────────────────

	/**
	 * Write anchoring outcome to the ArchivioMD audit log (archivio_post_audit table).
	 * Falls back silently if the table does not exist.
	 */
	private function write_audit_log( array $record, $event_type, $anchor_url, $error_msg ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'archivio_post_audit';

		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) !== $table_name ) {
			return;
		}

		$result_text = 'anchor_failed' === $event_type
			? 'Anchoring failed: ' . $error_msg
			: ( 'anchor_retry' === $event_type
				? 'Anchoring will be retried: ' . $error_msg
				: 'Anchored successfully. URL: ' . $anchor_url );

		$post_id = isset( $record['post_id'] ) ? (int) $record['post_id'] : 0;

		$wpdb->insert(
			$table_name,
			array(
				'post_id'    => $post_id,
				'author_id'  => 0,
				'hash'       => isset( $record['hash_value'] ) ? $record['hash_value'] : '',
				'algorithm'  => isset( $record['hash_algorithm'] ) ? $record['hash_algorithm'] : '',
				'mode'       => isset( $record['integrity_mode'] ) ? strtolower( $record['integrity_mode'] ) : 'basic',
				'event_type' => $event_type,
				'result'     => $result_text,
				'timestamp'  => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	// ── Settings ──────────────────────────────────────────────────────────────

	/**
	 * Return sanitised settings array. Never exposes raw token outside this class.
	 */
	public function get_settings() {
		$defaults = array(
			'provider'       => 'none',
			'visibility'     => 'private',
			'token'          => '',
			'repo_owner'     => '',
			'repo_name'      => '',
			'branch'         => 'main',
			'folder_path'    => 'hashes/YYYY-MM-DD',
			'commit_message' => 'chore: anchor {doc_id}',
		);

		$stored = get_option( self::SETTINGS_OPTION, array() );
		return wp_parse_args( is_array( $stored ) ? $stored : array(), $defaults );
	}

	public function is_enabled() {
		$settings = $this->get_settings();
		return ! empty( $settings['provider'] ) && $settings['provider'] !== 'none' && ! empty( $settings['token'] );
	}

	private function save_settings( array $data ) {
		$current  = $this->get_settings();
		$allowed  = array( 'provider', 'visibility', 'token', 'repo_owner', 'repo_name', 'branch', 'folder_path', 'commit_message' );
		$sanitized = array();

		foreach ( $allowed as $key ) {
			if ( isset( $data[ $key ] ) ) {
				$sanitized[ $key ] = sanitize_text_field( $data[ $key ] );
			} else {
				$sanitized[ $key ] = $current[ $key ];
			}
		}

		// Never blank the token if an empty field was submitted (preserve existing).
		if ( empty( $sanitized['token'] ) && ! empty( $current['token'] ) ) {
			$sanitized['token'] = $current['token'];
		}

		update_option( self::SETTINGS_OPTION, $sanitized, false );
	}

	// ── Provider factory ──────────────────────────────────────────────────────

	private function make_provider( $provider_key ) {
		switch ( strtolower( $provider_key ) ) {
			case 'github':
				return new MDSM_Anchor_Provider_GitHub();
			case 'gitlab':
				return new MDSM_Anchor_Provider_GitLab();
			default:
				return null;
		}
	}

	// ── Admin menu ────────────────────────────────────────────────────────────

	// Archivio Anchor is a standalone Tools submenu entry, sitting directly
	// under Archivio Post. No separate tab on the main ArchivioMD page.

	public function add_admin_menu() {
		add_submenu_page(
			'archiviomd',
			__( 'Remote Distribution', 'archiviomd' ),
			__( 'Remote Distribution', 'archiviomd' ),
			'manage_options',
			'archivio-anchor',
			array( $this, 'render_admin_page' )
		);
	}

	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'archiviomd' ) );
		}
		require_once MDSM_PLUGIN_DIR . 'admin/anchor-admin-page.php';
	}

	public function enqueue_admin_assets( $hook ) {
		// Load on the Archivio Anchor tools page only.
		if ( strpos( $hook, 'archivio' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'mdsm-anchor-admin',
			MDSM_PLUGIN_URL . 'assets/css/anchor-admin.css',
			array(),
			MDSM_VERSION
		);

		wp_enqueue_script(
			'mdsm-anchor-admin',
			MDSM_PLUGIN_URL . 'assets/js/anchor-admin.js',
			array( 'jquery' ),
			MDSM_VERSION,
			true
		);

		wp_localize_script( 'mdsm-anchor-admin', 'mdsmAnchorData', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'mdsm_anchor_nonce' ),
			'strings' => array(
				'saving'         => __( 'Saving…', 'archiviomd' ),
				'saved'          => __( 'Settings saved.', 'archiviomd' ),
				'testing'        => __( 'Testing connection…', 'archiviomd' ),
				'clearing'       => __( 'Clearing queue…', 'archiviomd' ),
				'queueCleared'   => __( 'Queue cleared.', 'archiviomd' ),
				'error'          => __( 'An error occurred. Please try again.', 'archiviomd' ),
			),
		) );
	}

	// ── AJAX handlers ─────────────────────────────────────────────────────────

	public function ajax_save_settings() {
		check_ajax_referer( 'mdsm_anchor_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'archiviomd' ) ) );
		}

		$this->save_settings( $_POST );

		wp_send_json_success( array( 'message' => __( 'Settings saved successfully.', 'archiviomd' ) ) );
	}

	public function ajax_test_connection() {
		check_ajax_referer( 'mdsm_anchor_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'archiviomd' ) ) );
		}

		$provider_key = sanitize_text_field( $_POST['provider'] ?? '' );
		$provider     = $this->make_provider( $provider_key );

		if ( null === $provider ) {
			wp_send_json_error( array( 'message' => __( 'No provider selected.', 'archiviomd' ) ) );
		}

		// Build a test settings array from POST, falling back to stored token if empty.
		$stored   = $this->get_settings();
		$settings = array(
			'token'       => ! empty( $_POST['token'] ) ? sanitize_text_field( $_POST['token'] ) : $stored['token'],
			'repo_owner'  => sanitize_text_field( $_POST['repo_owner'] ?? '' ),
			'repo_name'   => sanitize_text_field( $_POST['repo_name'] ?? '' ),
			'branch'      => sanitize_text_field( $_POST['branch'] ?? 'main' ),
			'folder_path' => sanitize_text_field( $_POST['folder_path'] ?? 'hashes' ),
		);

		if ( empty( $settings['token'] ) ) {
			wp_send_json_error( array( 'message' => __( 'API token is required to test the connection.', 'archiviomd' ) ) );
		}

		$result = $provider->test_connection( $settings );

		if ( $result['success'] ) {
			wp_send_json_success( array( 'message' => $result['message'] ) );
		} else {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}
	}

	public function ajax_clear_queue() {
		check_ajax_referer( 'mdsm_anchor_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'archiviomd' ) ) );
		}

		MDSM_Anchor_Queue::clear();
		wp_send_json_success( array( 'message' => __( 'Anchor queue cleared.', 'archiviomd' ) ) );
	}

	public function ajax_queue_status() {
		check_ajax_referer( 'mdsm_anchor_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'archiviomd' ) ) );
		}

		wp_send_json_success( array(
			'count'   => MDSM_Anchor_Queue::count(),
			'enabled' => $this->is_enabled(),
		) );
	}

	// ── Anchor log AJAX handlers ──────────────────────────────────────────────

	public function ajax_get_anchor_log() {
		check_ajax_referer( 'mdsm_anchor_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'archiviomd' ) ) );
		}

		$page     = isset( $_POST['page'] ) ? max( 1, intval( $_POST['page'] ) ) : 1;
		$per_page = 25;
		$filter   = isset( $_POST['filter'] ) ? sanitize_key( $_POST['filter'] ) : 'all';

		$result = MDSM_Anchor_Log::get_entries( $page, $per_page, $filter );

		wp_send_json_success( $result );
	}

	public function ajax_clear_anchor_log() {
		check_ajax_referer( 'mdsm_anchor_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'archiviomd' ) ) );
		}

		MDSM_Anchor_Log::clear();
		wp_send_json_success( array( 'message' => __( 'Anchor log cleared.', 'archiviomd' ) ) );
	}

	public function ajax_download_anchor_log() {
		check_ajax_referer( 'mdsm_anchor_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Insufficient permissions.', 'archiviomd' ) );
		}

		$entries = MDSM_Anchor_Log::get_all_for_export();
		$settings = $this->get_settings();

		$lines   = array();
		$lines[] = '========================================';
		$lines[] = 'ARCHIVIOMD EXTERNAL ANCHORING LOG';
		$lines[] = '========================================';
		$lines[] = 'Generated : ' . gmdate( 'Y-m-d H:i:s' ) . ' UTC';
		$lines[] = 'Site      : ' . get_site_url();
		$lines[] = 'Provider  : ' . strtoupper( $settings['provider'] );
		$lines[] = 'Repository: ' . $settings['repo_owner'] . '/' . $settings['repo_name'];
		$lines[] = 'Branch    : ' . $settings['branch'];
		$lines[] = 'Total entries: ' . count( $entries );
		$lines[] = '========================================';
		$lines[] = '';

		foreach ( $entries as $entry ) {
			$lines[] = '----------------------------------------';
			$lines[] = 'Timestamp   : ' . $entry['created_at'] . ' UTC';
			$lines[] = 'Status      : ' . strtoupper( $entry['status'] );
			$lines[] = 'Document ID : ' . $entry['document_id'];
			$lines[] = 'Post Type   : ' . $entry['post_type'];
			$lines[] = 'Provider    : ' . strtoupper( $entry['provider'] );
			$lines[] = 'Algorithm   : ' . strtoupper( $entry['hash_algorithm'] );
			$lines[] = 'Integrity   : ' . $entry['integrity_mode'];
			$lines[] = 'Hash        : ' . $entry['hash_value'];

			if ( ! empty( $entry['hmac_value'] ) ) {
				$lines[] = 'HMAC        : ' . $entry['hmac_value'];
			}

			$lines[] = 'Attempt #   : ' . $entry['attempt_number'];
			$lines[] = 'Job ID      : ' . $entry['job_id'];

			if ( 'anchored' === $entry['status'] && ! empty( $entry['anchor_url'] ) ) {
				$lines[] = 'Anchor URL  : ' . $entry['anchor_url'];
			}

			if ( ! empty( $entry['error_message'] ) ) {
				$lines[] = 'Error       : ' . $entry['error_message'];
			}

			if ( ! empty( $entry['http_status'] ) ) {
				$lines[] = 'HTTP Status : ' . $entry['http_status'];
			}

			$lines[] = '';
		}

		if ( empty( $entries ) ) {
			$lines[] = '(No log entries found.)';
		}

		$filename = 'archiviomd-anchor-log-' . gmdate( 'Y-m-d-H-i-s' ) . '.txt';
		$content  = implode( "\n", $lines );

		header( 'Content-Type: text/plain; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $content ) );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}
}

// ── Anchor Log ───────────────────────────────────────────────────────────────

/**
 * Dedicated anchor activity log stored in its own DB table.
 *
 * Tracks every push attempt — success, retry, or failure — with enough
 * detail to diagnose exactly what went wrong and when.
 *
 * Table: {prefix}archivio_anchor_log
 */
class MDSM_Anchor_Log {

	const TABLE_SUFFIX = 'archivio_anchor_log';

	// ── Table management ──────────────────────────────────────────────────────

	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	public static function create_table() {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id            bigint(20)   NOT NULL AUTO_INCREMENT,
			job_id        varchar(60)  NOT NULL DEFAULT '',
			document_id   varchar(255) NOT NULL DEFAULT '',
			post_type     varchar(50)  NOT NULL DEFAULT '',
			provider      varchar(20)  NOT NULL DEFAULT '',
			hash_algorithm varchar(20) NOT NULL DEFAULT '',
			hash_value    varchar(255) NOT NULL DEFAULT '',
			hmac_value    varchar(255) NOT NULL DEFAULT '',
			integrity_mode varchar(10) NOT NULL DEFAULT 'Basic',
			attempt_number tinyint(3)  NOT NULL DEFAULT 1,
			status        varchar(20)  NOT NULL DEFAULT '',
			anchor_url    text         NOT NULL,
			error_message text         NOT NULL,
			http_status   smallint(5)  NOT NULL DEFAULT 0,
			created_at    datetime     NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY created_at (created_at),
			KEY job_id (job_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public static function drop_table() {
		global $wpdb;
		$table_name = self::get_table_name();
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	// ── Write ─────────────────────────────────────────────────────────────────

	/**
	 * Write a log entry. Called from MDSM_External_Anchoring::process_queue().
	 *
	 * @param array  $record         Anchor record (document_id, hash_value, etc.)
	 * @param string $job_id         Queue job ID.
	 * @param int    $attempt_number Which attempt this is (1-based).
	 * @param string $status         'anchored' | 'retry' | 'failed'
	 * @param string $anchor_url     Remote URL if successful, empty otherwise.
	 * @param string $error_message  Full error text if failed/retry, empty otherwise.
	 * @param int    $http_status    HTTP response code if available, 0 otherwise.
	 */
	public static function write(
		array $record,
		$job_id,
		$attempt_number,
		$status,
		$anchor_url   = '',
		$error_message = '',
		$http_status  = 0
	) {
		global $wpdb;

		$table_name = self::get_table_name();

		// Ensure table exists — safe to call repeatedly, dbDelta is idempotent.
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) !== $table_name ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			self::create_table();
		}

		$wpdb->insert(
			$table_name,
			array(
				'job_id'         => (string) $job_id,
				'document_id'    => isset( $record['document_id'] )    ? (string) $record['document_id']    : '',
				'post_type'      => isset( $record['post_type'] )      ? (string) $record['post_type']      : '',
				'provider'       => isset( $record['_provider'] )      ? (string) $record['_provider']      : '',
				'hash_algorithm' => isset( $record['hash_algorithm'] ) ? (string) $record['hash_algorithm'] : '',
				'hash_value'     => isset( $record['hash_value'] )     ? (string) $record['hash_value']     : '',
				'hmac_value'     => isset( $record['hmac_value'] )     ? (string) $record['hmac_value']     : '',
				'integrity_mode' => isset( $record['integrity_mode'] ) ? (string) $record['integrity_mode'] : 'Basic',
				'attempt_number' => (int) $attempt_number,
				'status'         => (string) $status,
				'anchor_url'     => (string) $anchor_url,
				'error_message'  => (string) $error_message,
				'http_status'    => (int) $http_status,
				'created_at'     => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s' )
		);
	}

	// ── Read ──────────────────────────────────────────────────────────────────

	/**
	 * Get paginated log entries.
	 *
	 * @param int    $page
	 * @param int    $per_page
	 * @param string $filter   'all' | 'anchored' | 'retry' | 'failed'
	 * @return array { entries: array, total: int, pages: int }
	 */
	public static function get_entries( $page = 1, $per_page = 25, $filter = 'all' ) {
		global $wpdb;

		$table_name = self::get_table_name();

		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) !== $table_name ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return array( 'entries' => array(), 'total' => 0, 'pages' => 0 );
		}

		$where  = '';
		$params = array();

		if ( 'all' !== $filter ) {
			$where    = 'WHERE status = %s';
			$params[] = $filter;
		}

		$count_sql = "SELECT COUNT(*) FROM {$table_name} {$where}"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$total     = $params
			? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			: (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$offset  = ( $page - 1 ) * $per_page;
		$data_sql = "SELECT * FROM {$table_name} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$query_params = array_merge( $params, array( $per_page, $offset ) );
		$entries = $wpdb->get_results(
			$wpdb->prepare( $data_sql, $query_params ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		return array(
			'entries' => $entries ?: array(),
			'total'   => $total,
			'pages'   => $per_page > 0 ? (int) ceil( $total / $per_page ) : 0,
		);
	}

	/**
	 * Get all entries for plain-text export (most recent first, capped at 5000).
	 *
	 * @return array
	 */
	public static function get_all_for_export() {
		global $wpdb;

		$table_name = self::get_table_name();

		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) !== $table_name ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return array();
		}

		$results = $wpdb->get_results(
			"SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT 5000", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		return $results ?: array();
	}

	/**
	 * Clear all log entries.
	 */
	public static function clear() {
		global $wpdb;
		$table_name = self::get_table_name();
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( "TRUNCATE TABLE {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	/**
	 * Return counts grouped by status for the summary badges.
	 *
	 * @return array { anchored: int, retry: int, failed: int, total: int }
	 */
	public static function get_counts() {
		global $wpdb;

		$table_name = self::get_table_name();

		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) !== $table_name ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return array( 'anchored' => 0, 'retry' => 0, 'failed' => 0, 'total' => 0 );
		}

		$rows = $wpdb->get_results(
			"SELECT status, COUNT(*) AS cnt FROM {$table_name} GROUP BY status", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		$counts = array( 'anchored' => 0, 'retry' => 0, 'failed' => 0, 'total' => 0 );
		foreach ( (array) $rows as $row ) {
			$key = $row['status'];
			if ( isset( $counts[ $key ] ) ) {
				$counts[ $key ] = (int) $row['cnt'];
			}
			$counts['total'] += (int) $row['cnt'];
		}

		return $counts;
	}
}

