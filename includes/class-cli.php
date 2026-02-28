<?php
/**
 * ArchivioMD WP-CLI Commands
 *
 * Registered only when WP-CLI is active — completely invisible at runtime.
 *
 * Usage:
 *   wp archiviomd process-queue
 *   wp archiviomd anchor-post <post_id>
 *   wp archiviomd verify <post_id>
 *   wp archiviomd prune-log [--days=<days>]
 *
 * @package ArchivioMD
 * @since   1.6.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Manage ArchivioMD anchoring and verification from the command line.
 */
class MDSM_CLI_Commands extends WP_CLI_Command {

	/**
	 * Process all due anchor queue jobs immediately (same as cron).
	 *
	 * ## EXAMPLES
	 *
	 *   wp archiviomd process-queue
	 *
	 * @when after_wp_load
	 */
	public function process_queue() {
		$anchoring = MDSM_External_Anchoring::get_instance();

		if ( ! $anchoring->is_enabled() ) {
			WP_CLI::error( 'External anchoring is not enabled. Configure a provider first.' );
		}

		$before = MDSM_Anchor_Queue::count();
		WP_CLI::log( "Queue has {$before} pending job(s). Processing now..." );

		$anchoring->process_queue();

		$after = MDSM_Anchor_Queue::count();
		$done  = $before - $after;
		WP_CLI::success( "Processed {$done} job(s). {$after} job(s) remaining (retry or empty)." );
	}

	/**
	 * Queue a specific post for anchoring immediately.
	 *
	 * ## OPTIONS
	 *
	 * <post_id>
	 * : The WordPress post ID to anchor.
	 *
	 * ## EXAMPLES
	 *
	 *   wp archiviomd anchor-post 42
	 *
	 * @when after_wp_load
	 */
	public function anchor_post( $args ) {
		$post_id = (int) $args[0];

		if ( ! $post_id ) {
			WP_CLI::error( 'Please provide a valid post ID.' );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			WP_CLI::error( "Post {$post_id} not found." );
		}

		$anchoring = MDSM_External_Anchoring::get_instance();

		if ( ! $anchoring->is_enabled() ) {
			WP_CLI::error( 'External anchoring is not enabled. Configure a provider first.' );
		}

		// Re-use existing hash or compute fresh.
		$stored_packed = get_post_meta( $post_id, '_archivio_post_hash', true );

		if ( ! empty( $stored_packed ) ) {
			$unpacked    = MDSM_Hash_Helper::unpack( $stored_packed );
			$hash_result = array(
				'packed'           => $stored_packed,
				'hash'             => $unpacked['hash'],
				'algorithm'        => $unpacked['algorithm'],
				'hmac_unavailable' => false,
			);
			WP_CLI::log( "Using existing hash ({$unpacked['algorithm']}): {$unpacked['hash']}" );
		} else {
			$archivio    = MDSM_Archivio_Post::get_instance();
			$canonical   = $archivio->canonicalize_content(
				$post->post_content,
				$post_id,
				$post->post_author
			);
			$hash_result = MDSM_Hash_Helper::compute_packed( $canonical );
			$unpacked    = MDSM_Hash_Helper::unpack( $hash_result['packed'] );
			$hash_result['hash']      = $unpacked['hash'];
			$hash_result['algorithm'] = $unpacked['algorithm'];
			WP_CLI::log( "Computed fresh hash ({$unpacked['algorithm']}): {$unpacked['hash']}" );
		}

		// Clear dedup transient so this forced queue call is never skipped.
		$dedup_key = 'mdsm_anchor_q_' . $post_id . '_' . substr( md5( (string) $stored_packed ), 0, 8 );
		delete_transient( $dedup_key );

		$job_id = $anchoring->queue_post_anchor( $post_id, $hash_result );

		if ( $job_id ) {
			WP_CLI::success( "Post {$post_id} queued for anchoring. Job ID: {$job_id}" );
		} else {
			WP_CLI::warning( "Post {$post_id} could not be queued (queue may be full or provider disabled)." );
		}
	}

	/**
	 * Verify the stored hash for a post against its current content.
	 *
	 * ## OPTIONS
	 *
	 * <post_id>
	 * : The WordPress post ID to verify.
	 *
	 * ## EXAMPLES
	 *
	 *   wp archiviomd verify 42
	 *
	 * @when after_wp_load
	 */
	public function verify( $args ) {
		$post_id = (int) $args[0];

		if ( ! $post_id ) {
			WP_CLI::error( 'Please provide a valid post ID.' );
		}

		$archivio = MDSM_Archivio_Post::get_instance();
		$result   = $archivio->verify_hash( $post_id );

		if ( false === $result['stored_hash'] ) {
			WP_CLI::warning( "Post {$post_id}: no hash stored. Run anchor-post to queue it." );
			return;
		}

		$status = $result['verified'] ? 'PASSED' : 'FAILED';
		$color  = $result['verified'] ? '%G' : '%R';

		WP_CLI::log( "Post ID:    {$post_id}" );
		WP_CLI::log( "Algorithm:  {$result['algorithm']}" );
		WP_CLI::log( "Mode:       {$result['mode']}" );
		WP_CLI::log( "Stored:     {$result['stored_hash']}" );
		WP_CLI::log( "Current:    " . ( $result['current_hash'] ?: '(could not compute)' ) );
		WP_CLI::log( WP_CLI::colorize( "{$color}Verification: {$status}%n" ) );

		if ( ! $result['verified'] ) {
			if ( $result['hmac_key_missing'] ) {
				WP_CLI::warning( 'HMAC key (ARCHIVIOMD_HMAC_KEY) is not defined in wp-config.php.' );
			}
			WP_CLI::halt( 1 );
		}
	}

	/**
	 * Prune old anchor log entries.
	 *
	 * ## OPTIONS
	 *
	 * [--days=<days>]
	 * : Delete entries older than this many days. Defaults to the configured retention period.
	 *
	 * ## EXAMPLES
	 *
	 *   wp archiviomd prune-log
	 *   wp archiviomd prune-log --days=30
	 *
	 * @when after_wp_load
	 */
	public function prune_log( $args, $assoc_args ) {
		global $wpdb;

		$anchoring = MDSM_External_Anchoring::get_instance();
		$settings  = $anchoring->get_settings();

		$days = isset( $assoc_args['days'] )
			? max( 1, (int) $assoc_args['days'] )
			: (int) $settings['log_retention_days'];

		if ( $days <= 0 ) {
			WP_CLI::error( 'Retention is set to 0 (keep forever). Pass --days=<n> to override.' );
		}

		$table_name = MDSM_Anchor_Log::get_table_name();
		$cutoff     = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$count_before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} WHERE created_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$cutoff
			)
		);

		$count_after  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$deleted      = $count_before - $count_after;

		WP_CLI::success( "Pruned {$deleted} log entries older than {$days} days. {$count_after} entries remaining." );
	}
}

WP_CLI::add_command( 'archiviomd', 'MDSM_CLI_Commands' );
