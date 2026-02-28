<?php

/**
 * Archivio Anchor -- Admin Page Template
 *
 * Rendered by MDSM_External_Anchoring::render_admin_page() as a standalone
 * Tools submenu page. All output is escaped. Token is never echoed in cleartext.
 *
 * @package ArchivioMD
 * @since   1.5.0
 * @updated 1.6.0 -- RFC 3161 TSA support
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( __( 'You do not have sufficient permissions to access this page.', 'archiviomd' ) );
}

$anchoring = MDSM_External_Anchoring::get_instance();
$settings  = $anchoring->get_settings();

$rfc3161_enabled   = ! empty( $settings['rfc3161_enabled'] ) && '1' === (string) $settings['rfc3161_enabled'];
$rfc3161_provider  = $settings['rfc3161_provider'];
$active_providers  = $anchoring->get_active_providers();
$rfc3161_custom    = $settings['rfc3161_custom_url'];
$rfc3161_username  = $settings['rfc3161_username'];
$has_rfc3161_pass  = ! empty( $settings['rfc3161_password'] );
// Git provider state — read-only on this page.
$git_provider      = $settings['provider'];
$has_git_token     = ! empty( $settings['token'] );
$visibility        = $settings['visibility'];
$queue_count       = MDSM_Anchor_Queue::count();
$is_enabled        = $anchoring->is_enabled();
$tsa_profiles      = MDSM_TSA_Profiles::all();
?>
<div class="wrap mdsm-anchor-wrap">
	<h1 class="mdsm-anchor-title">
		<span class="dashicons dashicons-shield" style="font-size:26px;margin-right:8px;vertical-align:middle;color:#2271b1;"></span>
		<?php esc_html_e( 'Trusted Timestamps (RFC 3161)', 'archiviomd' ); ?>
	</h1>

	<p class="mdsm-anchor-intro">
		<?php esc_html_e( 'Anchor document integrity hashes to an external repository or Trusted Timestamping Authority (TSA) for tamper-evident, independent verification. Anchoring runs asynchronously and never interrupts document saving.', 'archiviomd' ); ?>
	</p>

	<?php if ( 'public' === $visibility && in_array( $git_provider, array( 'github', 'gitlab' ), true ) ) : ?>
	<div class="notice notice-warning mdsm-anchor-notice" id="mdsm-visibility-warning">
		<p>
			<strong><?php esc_html_e( 'Public Repository Warning:', 'archiviomd' ); ?></strong>
			<?php esc_html_e( 'You have selected a public repository. All anchored hash records — including document IDs, timestamps, hash values, and author names — will be publicly visible on your repository. Consider switching to a private repository unless public transparency is intentional.', 'archiviomd' ); ?>
		</p>
	</div>
	<?php endif; ?>

	<p style="font-size:13px;margin-bottom:16px;"><?php esc_html_e( 'Looking for Git-based anchoring?', 'archiviomd' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=archivio-git-distribution' ) ); ?>"><?php esc_html_e( 'Git Distribution (GitHub / GitLab)', 'archiviomd' ); ?> &rarr;</a></p>

	<?php if ( $is_enabled ) : ?>
	<div class="notice notice-success mdsm-anchor-notice" style="border-left-color:#00a32a;">
		<p>
			<strong><?php esc_html_e( 'Anchoring Active', 'archiviomd' ); ?></strong> —
			<?php
			$_ap_labels = array();
			foreach ( $active_providers as $_apk ) {
				if ( 'rfc3161' === $_apk ) {
					$_apr = MDSM_TSA_Profiles::get( $rfc3161_provider );
					$_ap_labels[] = 'RFC 3161 (' . ( $_apr ? $_apr['label'] : esc_html__( 'Custom TSA', 'archiviomd' ) ) . ')';
				} else {
					$_ap_labels[] = strtoupper( $_apk );
				}
			}
			/* translators: %s: comma-separated list of provider names */
			echo esc_html( sprintf( __( 'Documents will be anchored to %s asynchronously via WP-Cron.', 'archiviomd' ), implode( ' + ', $_ap_labels ) ) );
			?>
		</p>
	</div>
	<?php endif; ?>

	<!-- Status bar -->
	<div class="mdsm-anchor-status-bar">
		<div class="mdsm-anchor-status-item">
			<span class="mdsm-anchor-label"><?php esc_html_e( 'Provider:', 'archiviomd' ); ?></span>
			<strong>
				<?php
				$provider_labels = array();

				// Show Git provider if one is active.
				if ( ! empty( $git_provider ) && 'none' !== $git_provider && 'rfc3161' !== $git_provider ) {
					$provider_labels[] = strtoupper( $git_provider );
				}

				// Show RFC 3161 TSA provider if the checkbox is enabled.
				if ( $rfc3161_enabled ) {
					$profile           = MDSM_TSA_Profiles::get( $rfc3161_provider );
					$provider_labels[] = 'RFC 3161 — ' . ( $profile ? $profile['label'] : __( 'Custom TSA', 'archiviomd' ) );
				}

				if ( empty( $provider_labels ) ) {
					echo esc_html__( 'None (disabled)', 'archiviomd' );
				} else {
					echo esc_html( implode( ' + ', $provider_labels ) );
				}
				?>
			</strong>
		</div>
		<div class="mdsm-anchor-status-item">
			<span class="mdsm-anchor-label"><?php esc_html_e( 'Queue:', 'archiviomd' ); ?></span>
			<strong id="mdsm-queue-count"><?php echo esc_html( $queue_count ); ?></strong>
			<?php esc_html_e( 'pending job(s)', 'archiviomd' ); ?>
		</div>
		<div class="mdsm-anchor-status-item">
			<span class="mdsm-anchor-label"><?php esc_html_e( 'Next cron run:', 'archiviomd' ); ?></span>
			<?php
			$next = wp_next_scheduled( MDSM_External_Anchoring::CRON_HOOK );
			if ( $next ) {
				$diff = $next - time();
				echo esc_html( $diff > 0
					? sprintf( esc_html__(  'in %d minute(s)', 'archiviomd' ), max( 1, ceil( $diff / 60 ) ) )
					: __( 'imminent', 'archiviomd' )
				);
			} else {
				esc_html_e( 'not scheduled', 'archiviomd' );
			}
			?>
		</div>
	</div>

	<!-- Settings card -->
	<div class="mdsm-anchor-card">
		<h2 class="mdsm-anchor-card-title"><?php esc_html_e( 'Anchoring Configuration', 'archiviomd' ); ?></h2>

		<div id="mdsm-anchor-feedback" class="mdsm-anchor-feedback" style="display:none;"></div>

		<table class="form-table mdsm-anchor-table" role="presentation">

			<!-- RFC 3161 enable toggle -->
			<tr>
				<th scope="row">
					<label for="mdsm-rfc3161-enabled"><?php esc_html_e( 'RFC 3161 Timestamping', 'archiviomd' ); ?></label>
				</th>
				<td>
					<label>
						<input type="checkbox" id="mdsm-rfc3161-enabled" name="rfc3161_enabled" value="1"
							<?php checked( $rfc3161_enabled ); ?> />
						<?php esc_html_e( 'Enable RFC 3161 Trusted Timestamps', 'archiviomd' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'When enabled, every anchor job is sent to the selected TSA independently of any Git provider. Both can be active simultaneously.', 'archiviomd' ); ?>
						<?php if ( ! empty( $git_provider ) && 'none' !== $git_provider ) : ?>
							<br><em><?php echo esc_html( sprintf(
								/* translators: %s: provider name */
								__( 'Git provider active: %s', 'archiviomd' ),
								strtoupper( $git_provider )
							) ); ?> — <a href="<?php echo esc_url( admin_url( 'admin.php?page=archivio-git-distribution' ) ); ?>"><?php esc_html_e( 'Git Distribution settings', 'archiviomd' ); ?></a></em>
						<?php endif; ?>
					</p>
				</td>
			</tr>

			<!-- ═══════════════════════════════════════════════════════════════ -->
			<!-- RFC 3161 fields — shown only when provider = rfc3161           -->
			<!-- ═══════════════════════════════════════════════════════════════ -->

			<!-- TSA sub-provider -->
			<tr class="mdsm-anchor-rfc3161-field">
				<th scope="row">
					<label for="mdsm-rfc3161-provider"><?php esc_html_e( 'Timestamping Authority', 'archiviomd' ); ?></label>
				</th>
				<td>
					<select id="mdsm-rfc3161-provider" name="rfc3161_provider" class="regular-text">
						<?php foreach ( $tsa_profiles as $slug => $profile ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>"
								data-url="<?php echo esc_attr( $profile['url'] ); ?>"
								data-auth="<?php echo esc_attr( $profile['auth'] ); ?>"
								data-notes="<?php echo esc_attr( $profile['notes'] ); ?>"
								<?php selected( $rfc3161_provider, $slug ); ?>>
								<?php echo esc_html( $profile['label'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description" id="mdsm-tsa-notes">
						<?php
						$selected_profile = MDSM_TSA_Profiles::get( $rfc3161_provider );
						echo esc_html( $selected_profile ? $selected_profile['notes'] : '' );
						?>
					</p>
				</td>
			</tr>

			<!-- Custom TSA URL — visible only when "custom" is selected -->
			<tr class="mdsm-anchor-rfc3161-field mdsm-rfc3161-custom-field">
				<th scope="row">
					<label for="mdsm-rfc3161-custom-url"><?php esc_html_e( 'Custom TSA URL', 'archiviomd' ); ?></label>
				</th>
				<td>
					<input type="url" id="mdsm-rfc3161-custom-url" name="rfc3161_custom_url"
						class="large-text"
						value="<?php echo esc_attr( $rfc3161_custom ); ?>"
						placeholder="https://your-tsa.example.com/tsa" />
					<p class="description">
						<?php esc_html_e( 'Enter the full URL of your RFC 3161-compliant TSA endpoint. Must accept POST requests with Content-Type: application/timestamp-query.', 'archiviomd' ); ?>
					</p>
				</td>
			</tr>

			<!-- TSA username (optional, commercial TSAs) -->
			<tr class="mdsm-anchor-rfc3161-field mdsm-rfc3161-auth-field">
				<th scope="row">
					<label for="mdsm-rfc3161-username"><?php esc_html_e( 'TSA Username', 'archiviomd' ); ?></label>
				</th>
				<td>
					<input type="text" id="mdsm-rfc3161-username" name="rfc3161_username"
						class="regular-text"
						autocomplete="off"
						value="<?php echo esc_attr( $rfc3161_username ); ?>"
						placeholder="<?php esc_attr_e( 'Leave blank if not required', 'archiviomd' ); ?>" />
					<p class="description">
						<?php esc_html_e( 'HTTP Basic auth username. Leave blank for public TSAs (FreeTSA, Sectigo, DigiCert, GlobalSign — none require credentials).', 'archiviomd' ); ?>
					</p>
				</td>
			</tr>

			<!-- TSA password (optional, commercial TSAs) -->
			<tr class="mdsm-anchor-rfc3161-field mdsm-rfc3161-auth-field">
				<th scope="row">
					<label for="mdsm-rfc3161-password"><?php esc_html_e( 'TSA Password', 'archiviomd' ); ?></label>
				</th>
				<td>
					<input
						type="password"
						id="mdsm-rfc3161-password"
						name="rfc3161_password"
						class="regular-text"
						autocomplete="new-password"
						placeholder="<?php echo $has_rfc3161_pass ? esc_attr__( '(password saved — enter new value to replace)', 'archiviomd' ) : esc_attr__( 'Leave blank if not required', 'archiviomd' ); ?>"
						value=""
					/>
					<?php if ( $has_rfc3161_pass ) : ?>
						<span class="mdsm-token-saved"><?php esc_html_e( '✓ Password saved', 'archiviomd' ); ?></span>
					<?php endif; ?>
					<p class="description">
						<?php esc_html_e( 'HTTP Basic auth password. Stored securely and never printed in source or logs. Leave blank to keep the current value.', 'archiviomd' ); ?>
					</p>
				</td>
			</tr>

		</table>

		<!-- Action buttons -->
		<div class="mdsm-anchor-actions">
			<button type="button" id="mdsm-anchor-save" class="button button-primary">
				<?php esc_html_e( 'Save Settings', 'archiviomd' ); ?>
			</button>

			<button type="button" id="mdsm-anchor-test" class="button button-secondary" id="mdsm-anchor-test">
				<?php esc_html_e( 'Test Connection', 'archiviomd' ); ?>
			</button>
		</div>

		<!-- Test result area -->
		<div id="mdsm-test-result" class="mdsm-anchor-test-result" style="display:none;"></div>
	</div>

	<!-- RFC 3161 explainer card — shown only when rfc3161 is the active provider -->
	<?php if ( $git_provider === 'rfc3161' ) : ?>
	<div class="mdsm-anchor-card mdsm-anchor-card-info">
		<h2 class="mdsm-anchor-card-title"><?php esc_html_e( 'About RFC 3161 Timestamps', 'archiviomd' ); ?></h2>
		<p>
			<?php esc_html_e( 'RFC 3161 is the IETF standard for Trusted Timestamping. When a document is anchored, ArchivioMD sends a cryptographic hash of the anchor record to the TSA. The TSA returns a signed TimeStampToken (TST) that proves the hash existed at a specific point in time, signed by the TSA\'s certificate.', 'archiviomd' ); ?>
		</p>
		<p>
			<?php esc_html_e( 'The .tsr response files are stored in your WordPress uploads directory under meta-docs/tsr-timestamps/ and can be verified offline at any time using OpenSSL:', 'archiviomd' ); ?>
		</p>
		<pre style="background:#f6f7f7;padding:10px 14px;border-radius:4px;overflow-x:auto;font-size:12px;">openssl ts -verify -in response.tsr -queryfile request.tsq -CAfile tsa.crt</pre>
		<p>
			<?php esc_html_e( 'FreeTSA.org is free with no account required and is a good starting point. DigiCert, GlobalSign, and Sectigo all provide free public endpoints with no credentials needed. Use "Custom TSA" if your organisation operates its own RFC 3161-compliant TSA.', 'archiviomd' ); ?>
		</p>
	</div>
	<?php endif; ?>


	<!-- Queue management card -->
	<div class="mdsm-anchor-card">
		<h2 class="mdsm-anchor-card-title"><?php esc_html_e( 'Anchor Queue', 'archiviomd' ); ?></h2>

		<p>
			<?php esc_html_e( 'The queue holds pending anchor jobs. Jobs run every 5 minutes via WP-Cron. Failed jobs are retried automatically using exponential back-off (up to 5 attempts).', 'archiviomd' ); ?>
		</p>

		<div class="mdsm-anchor-status-item" style="margin-bottom:12px;">
			<span class="mdsm-anchor-label"><?php esc_html_e( 'Pending jobs:', 'archiviomd' ); ?></span>
			<strong id="mdsm-queue-count-detail"><?php echo esc_html( $queue_count ); ?></strong>
		</div>

		<button type="button" id="mdsm-anchor-clear-queue" class="button button-secondary" <?php echo $queue_count === 0 ? 'disabled' : ''; ?>>
			<?php esc_html_e( 'Clear Anchor Queue', 'archiviomd' ); ?>
		</button>
		<p class="description" style="margin-top:6px;">
			<?php esc_html_e( 'Clears all pending and failed anchor jobs. Hashes already stored in the database are unaffected.', 'archiviomd' ); ?>
		</p>

		<div id="mdsm-queue-feedback" class="mdsm-anchor-feedback" style="display:none;margin-top:10px;"></div>
	</div>

	<!-- Activity Log card -->
	<div class="mdsm-anchor-card">
		<h2 class="mdsm-anchor-card-title"><?php esc_html_e( 'Anchor Activity Log', 'archiviomd' ); ?></h2>

		<?php
		$log_counts    = MDSM_Anchor_Log::get_counts( 'rfc3161' );
		$is_rfc3161    = in_array( 'rfc3161', $active_providers, true );
		$zip_available = class_exists( 'ZipArchive' );
		$upload_dir    = wp_upload_dir();
		$tsr_dir       = trailingslashit( $upload_dir['basedir'] ) . 'meta-docs/tsr-timestamps';
		$tsr_count     = is_dir( $tsr_dir ) ? count( glob( $tsr_dir . '/*.tsr' ) ?: array() ) : 0;
		?>

		<!-- Summary badges -->
		<div class="mdsm-anchor-log-summary">
			<span class="mdsm-log-badge mdsm-log-badge--all active" data-filter="all">
				<?php esc_html_e( 'All', 'archiviomd' ); ?>
				<strong><?php echo esc_html( $log_counts['total'] ); ?></strong>
			</span>
			<span class="mdsm-log-badge mdsm-log-badge--anchored" data-filter="anchored">
				<?php esc_html_e( 'Anchored', 'archiviomd' ); ?>
				<strong><?php echo esc_html( $log_counts['anchored'] ); ?></strong>
			</span>
			<span class="mdsm-log-badge mdsm-log-badge--retry" data-filter="retry">
				<?php esc_html_e( 'Retry', 'archiviomd' ); ?>
				<strong><?php echo esc_html( $log_counts['retry'] ); ?></strong>
			</span>
			<span class="mdsm-log-badge mdsm-log-badge--failed" data-filter="failed">
				<?php esc_html_e( 'Failed', 'archiviomd' ); ?>
				<strong><?php echo esc_html( $log_counts['failed'] ); ?></strong>
			</span>
		</div>

		<!-- Inline log table -->
		<div id="mdsm-log-table-wrap" style="margin-top:16px;margin-left:-28px;width:calc(100% + 56px);overflow-x:auto;-webkit-overflow-scrolling:touch;">
			<table id="mdsm-log-table" style="border-collapse:collapse;white-space:nowrap;font-size:12.5px;width:max-content;min-width:100%;">
				<thead>
					<tr style="background:#f6f7f7;border-bottom:2px solid #c3c4c7;">
						<th style="padding:8px 14px;text-align:left;font-weight:600;color:#1d2327;"><?php esc_html_e( 'Timestamp (UTC)', 'archiviomd' ); ?></th>
						<th style="padding:8px 14px;text-align:left;font-weight:600;color:#1d2327;"><?php esc_html_e( 'Status', 'archiviomd' ); ?></th>
						<th style="padding:8px 14px;text-align:left;font-weight:600;color:#1d2327;"><?php esc_html_e( 'Document ID', 'archiviomd' ); ?></th>
						<th style="padding:8px 14px;text-align:left;font-weight:600;color:#1d2327;"><?php esc_html_e( 'Provider', 'archiviomd' ); ?></th>
						<th style="padding:8px 14px;text-align:left;font-weight:600;color:#1d2327;"><?php esc_html_e( 'Algorithm', 'archiviomd' ); ?></th>
						<th style="padding:8px 14px;text-align:left;font-weight:600;color:#1d2327;"><?php esc_html_e( 'Hash (truncated)', 'archiviomd' ); ?></th>
						<th style="padding:8px 14px;text-align:left;font-weight:600;color:#1d2327;"><?php esc_html_e( 'Anchor / TSR', 'archiviomd' ); ?></th>
					</tr>
				</thead>
				<tbody id="mdsm-log-tbody">
					<tr><td colspan="7" style="text-align:center;padding:20px;color:#666;white-space:normal;">
						<?php esc_html_e( 'Loading…', 'archiviomd' ); ?>
					</td></tr>
				</tbody>
			</table>

			<div id="mdsm-log-pagination" style="margin-top:10px;display:flex;align-items:center;gap:8px;">
				<button type="button" id="mdsm-log-prev" class="button button-secondary" disabled>
					&laquo; <?php esc_html_e( 'Prev', 'archiviomd' ); ?>
				</button>
				<span id="mdsm-log-page-info" style="font-size:12px;color:#666;"></span>
				<button type="button" id="mdsm-log-next" class="button button-secondary" disabled>
					<?php esc_html_e( 'Next', 'archiviomd' ); ?> &raquo;
				</button>
			</div>

			<div id="mdsm-log-feedback" class="mdsm-anchor-feedback" style="display:none;margin-top:8px;"></div>
		</div>

		<!-- Export actions -->
		<div class="mdsm-anchor-actions" style="margin-top:18px;flex-wrap:wrap;gap:8px;">

			<a href="<?php echo esc_url( add_query_arg( array(
				'action' => 'mdsm_anchor_download_log',
				'nonce'  => wp_create_nonce( 'mdsm_anchor_nonce' ),
			), admin_url( 'admin-ajax.php' ) ) ); ?>"
			   class="button button-secondary">
				<span class="dashicons dashicons-media-text" style="vertical-align:middle;margin-top:-2px;"></span>
				<?php esc_html_e( 'Export .txt Log', 'archiviomd' ); ?>
			</a>

			<a href="<?php echo esc_url( add_query_arg( array(
				'action' => 'mdsm_anchor_download_csv',
				'nonce'  => wp_create_nonce( 'mdsm_anchor_nonce' ),
			), admin_url( 'admin-ajax.php' ) ) ); ?>"
			   class="button button-secondary">
				<span class="dashicons dashicons-list-view" style="vertical-align:middle;margin-top:-2px;"></span>
				<?php esc_html_e( 'Export .csv (Auditor)', 'archiviomd' ); ?>
			</a>

			<?php if ( $is_rfc3161 ) : ?>
			<a href="<?php echo esc_url( add_query_arg( array(
				'action' => 'mdsm_anchor_download_tsr_zip',
				'nonce'  => wp_create_nonce( 'mdsm_anchor_nonce' ),
			), admin_url( 'admin-ajax.php' ) ) ); ?>"
			   class="button button-secondary"
			   <?php echo ( ! $zip_available || $tsr_count === 0 ) ? 'disabled title="' . esc_attr( ! $zip_available ? __( 'PHP ZipArchive extension not available on this server', 'archiviomd' ) : __( 'No .tsr files stored yet', 'archiviomd' ) ) . '"' : ''; ?>>
				<span class="dashicons dashicons-archive" style="vertical-align:middle;margin-top:-2px;"></span>
				<?php
				echo esc_html( sprintf(
					/* translators: %d: number of .tsr files */
					__( 'Download .tsr Archive (%d files)', 'archiviomd' ),
					$tsr_count
				) );
				?>
			</a>
			<?php endif; ?>

		</div>

		<p class="description" style="margin-top:8px;">
			<?php esc_html_e( 'The .txt and .csv exports contain the complete log history with all hash values. The .csv is formatted for Excel and Google Sheets and suitable for auditor handoff. For RFC 3161: the .tsr archive contains all timestamp token binary files verifiable offline with OpenSSL, plus a MANIFEST.txt with SHA-256 checksums of every file.', 'archiviomd' ); ?>
		</p>

		<!-- Danger zone -->
		<div style="margin-top:24px;padding:14px 16px;border:1px solid #d63638;border-radius:4px;background:#fef6f6;">
			<h3 style="margin:0 0 6px;color:#d63638;font-size:13px;">
				<?php esc_html_e( 'Danger Zone', 'archiviomd' ); ?>
			</h3>
			<p style="margin:0 0 10px;font-size:12px;color:#50575e;">
				<?php esc_html_e( 'Clearing the log permanently deletes all anchor activity records from the database. TSR files on disk are not deleted. Export before clearing if you need to retain a record.', 'archiviomd' ); ?>
			</p>
			<button type="button" id="mdsm-anchor-clear-log" class="button"
				style="border-color:#d63638;color:#d63638;"
				<?php echo $log_counts['total'] === 0 ? 'disabled' : ''; ?>>
				<?php esc_html_e( 'Clear Entire Log…', 'archiviomd' ); ?>
			</button>
		</div>

		<!-- Confirmation modal (hidden) -->
		<div id="mdsm-clear-log-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:100000;align-items:center;justify-content:center;">
			<div style="background:#fff;border-radius:6px;padding:28px 32px;max-width:420px;width:90%;box-shadow:0 8px 32px rgba(0,0,0,.25);">
				<h2 style="margin:0 0 10px;font-size:16px;color:#d63638;">
					&#9888; <?php esc_html_e( 'Clear Anchor Log?', 'archiviomd' ); ?>
				</h2>
				<p style="margin:0 0 6px;font-size:13px;color:#50575e;">
					<?php
					echo esc_html( sprintf(
						/* translators: %d: number of log entries */
						__( 'This will permanently delete all %d log entries from the database. This cannot be undone.', 'archiviomd' ),
						(int) $log_counts['total']
					) );
					?>
				</p>
				<p style="margin:0 0 14px;font-size:13px;color:#50575e;">
					<?php esc_html_e( 'Type CLEAR LOG below to confirm:', 'archiviomd' ); ?>
				</p>
				<input type="text" id="mdsm-clear-log-confirm-input"
					placeholder="<?php esc_attr_e( 'CLEAR LOG', 'archiviomd' ); ?>"
					style="width:100%;margin-bottom:16px;font-size:13px;"
					class="regular-text" autocomplete="off" />
				<div style="display:flex;gap:10px;justify-content:flex-end;">
					<button type="button" id="mdsm-clear-log-cancel" class="button button-secondary">
						<?php esc_html_e( 'Cancel', 'archiviomd' ); ?>
					</button>
					<button type="button" id="mdsm-clear-log-confirm" class="button" disabled
						style="border-color:#d63638;color:#fff;background:#d63638;opacity:.5;">
						<?php esc_html_e( 'Yes, Clear Log', 'archiviomd' ); ?>
					</button>
				</div>
				<div id="mdsm-clear-log-modal-feedback" class="mdsm-anchor-feedback" style="display:none;margin-top:12px;"></div>
			</div>
		</div>

	</div>

	<!-- How anchoring works card -->
	<div class="mdsm-anchor-card mdsm-anchor-card-info">
		<h2 class="mdsm-anchor-card-title"><?php esc_html_e( 'How External Anchoring Works', 'archiviomd' ); ?></h2>

		<ol class="mdsm-anchor-howto">
			<li><?php esc_html_e( 'When a WordPress post/page is saved or a Markdown document is updated, ArchivioMD computes its integrity hash (SHA-256/SHA-512/BLAKE2b, with optional HMAC).', 'archiviomd' ); ?></li>
			<li><?php esc_html_e( 'A lightweight JSON anchor record is placed in the queue — this takes milliseconds and never delays saving.', 'archiviomd' ); ?></li>
			<li><?php esc_html_e( 'WP-Cron dispatches the queue every 5 minutes, pushing each record as a JSON file to your chosen repository.', 'archiviomd' ); ?></li>
			<li><?php esc_html_e( 'If the provider API is unreachable, the job is automatically retried with exponential back-off. All failures are written to the ArchivioMD audit log.', 'archiviomd' ); ?></li>
			<li><?php esc_html_e( 'Each anchored JSON file contains: Document ID, Post ID (if applicable), post type, hash algorithm, hash value, HMAC value (if enabled), author, timestamp, plugin version, and integrity mode.', 'archiviomd' ); ?></li>
		</ol>

		<div class="mdsm-anchor-note">
			<strong><?php esc_html_e( 'Security note:', 'archiviomd' ); ?></strong>
			<?php esc_html_e( 'Your API token is stored in the WordPress database (wp_options) and is never printed in HTML source, JavaScript, or log files. Ensure your database connection is secured via SSL and that wp-config.php has appropriate file permissions.', 'archiviomd' ); ?>
		</div>
	</div>

</div><!-- .mdsm-anchor-wrap -->
