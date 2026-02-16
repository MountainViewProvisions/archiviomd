<?php
/**
 * Archivio Anchor — Admin Page Template
 *
 * Rendered by MDSM_External_Anchoring::render_admin_page() as a standalone
 * Tools submenu page. All output is escaped. Token is never echoed in cleartext.
 *
 * @package ArchivioMD
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( __( 'You do not have sufficient permissions to access this page.', 'archiviomd' ) );
}

$anchoring = MDSM_External_Anchoring::get_instance();
$settings  = $anchoring->get_settings();

$provider    = $settings['provider'];
$visibility  = $settings['visibility'];
$has_token   = ! empty( $settings['token'] );
$repo_owner  = $settings['repo_owner'];
$repo_name   = $settings['repo_name'];
$branch      = $settings['branch'];
$folder_path = $settings['folder_path'];
$commit_msg  = $settings['commit_message'];
$queue_count = MDSM_Anchor_Queue::count();
$is_enabled  = $anchoring->is_enabled();
?>
<div class="wrap mdsm-anchor-wrap">
	<h1 class="mdsm-anchor-title">
		<span class="dashicons dashicons-admin-links" style="font-size:26px;margin-right:8px;vertical-align:middle;color:#2271b1;"></span>
		<?php esc_html_e( 'Archivio Anchor', 'archiviomd' ); ?>
	</h1>

	<p class="mdsm-anchor-intro">
		<?php esc_html_e( 'Anchor document integrity hashes to an external Git repository (GitHub or GitLab) for tamper-evident, independent verification. Anchoring runs asynchronously and never interrupts document saving.', 'archiviomd' ); ?>
	</p>

	<?php if ( 'public' === $visibility && 'none' !== $provider ) : ?>
	<div class="notice notice-warning mdsm-anchor-notice" id="mdsm-visibility-warning">
		<p>
			<strong><?php esc_html_e( 'Public Repository Warning:', 'archiviomd' ); ?></strong>
			<?php esc_html_e( 'You have selected a public repository. All anchored hash records — including document IDs, timestamps, hash values, and author names — will be publicly visible on your repository. Consider switching to a private repository unless public transparency is intentional.', 'archiviomd' ); ?>
		</p>
	</div>
	<?php endif; ?>

	<?php if ( $is_enabled ) : ?>
	<div class="notice notice-success mdsm-anchor-notice" style="border-left-color:#00a32a;">
		<p>
			<strong><?php esc_html_e( 'Anchoring Active', 'archiviomd' ); ?></strong> —
			<?php
			echo esc_html( sprintf(
				/* translators: %s: provider name */
				__( 'Documents will be anchored to %s asynchronously via WP-Cron.', 'archiviomd' ),
				strtoupper( $provider )
			) );
			?>
		</p>
	</div>
	<?php endif; ?>

	<!-- Status bar -->
	<div class="mdsm-anchor-status-bar">
		<div class="mdsm-anchor-status-item">
			<span class="mdsm-anchor-label"><?php esc_html_e( 'Provider:', 'archiviomd' ); ?></span>
			<strong><?php echo esc_html( 'none' === $provider ? __( 'None (disabled)', 'archiviomd' ) : strtoupper( $provider ) ); ?></strong>
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
					? sprintf( __( 'in %d minute(s)', 'archiviomd' ), max( 1, ceil( $diff / 60 ) ) )
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

			<!-- Provider -->
			<tr>
				<th scope="row">
					<label for="mdsm-provider"><?php esc_html_e( 'Anchoring Provider', 'archiviomd' ); ?></label>
				</th>
				<td>
					<select id="mdsm-provider" name="provider" class="regular-text">
						<option value="none"   <?php selected( $provider, 'none' ); ?>><?php esc_html_e( 'None (disabled)', 'archiviomd' ); ?></option>
						<option value="github" <?php selected( $provider, 'github' ); ?>><?php esc_html_e( 'GitHub', 'archiviomd' ); ?></option>
						<option value="gitlab" <?php selected( $provider, 'gitlab' ); ?>><?php esc_html_e( 'GitLab', 'archiviomd' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Select None to disable anchoring entirely. No data will leave your server.', 'archiviomd' ); ?></p>
				</td>
			</tr>

			<!-- Visibility -->
			<tr class="mdsm-anchor-requires-provider">
				<th scope="row">
					<label for="mdsm-visibility"><?php esc_html_e( 'Repository Visibility', 'archiviomd' ); ?></label>
				</th>
				<td>
					<select id="mdsm-visibility" name="visibility" class="regular-text">
						<option value="private" <?php selected( $visibility, 'private' ); ?>><?php esc_html_e( 'Private (recommended)', 'archiviomd' ); ?></option>
						<option value="public"  <?php selected( $visibility, 'public' ); ?>><?php esc_html_e( 'Public — metadata will be publicly exposed', 'archiviomd' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'This setting is informational — it documents your intent and controls the public-visibility warning. The repository must already exist with the correct visibility on the provider.', 'archiviomd' ); ?></p>
				</td>
			</tr>

			<!-- Token -->
			<tr class="mdsm-anchor-requires-provider">
				<th scope="row">
					<label for="mdsm-token"><?php esc_html_e( 'Personal Access Token', 'archiviomd' ); ?></label>
				</th>
				<td>
					<input
						type="password"
						id="mdsm-token"
						name="token"
						class="regular-text"
						autocomplete="new-password"
						placeholder="<?php echo $has_token ? esc_attr__( '(token saved — enter new value to replace)', 'archiviomd' ) : esc_attr__( 'Paste your PAT here', 'archiviomd' ); ?>"
						value=""
					/>
					<?php if ( $has_token ) : ?>
						<span class="mdsm-token-saved"><?php esc_html_e( '✓ Token saved', 'archiviomd' ); ?></span>
					<?php endif; ?>
					<p class="description">
						<?php esc_html_e( 'The token is stored securely in the WordPress database and never printed in source code, JavaScript, or logs. Leave blank to keep the current token.', 'archiviomd' ); ?><br>
						<strong><?php esc_html_e( 'GitHub:', 'archiviomd' ); ?></strong> <?php esc_html_e( 'Requires the "Contents" repository permission (read & write).', 'archiviomd' ); ?><br>
						<strong><?php esc_html_e( 'GitLab:', 'archiviomd' ); ?></strong> <?php esc_html_e( 'Requires the "api" scope.', 'archiviomd' ); ?>
					</p>
				</td>
			</tr>

			<!-- Repo Owner -->
			<tr class="mdsm-anchor-requires-provider">
				<th scope="row">
					<label for="mdsm-repo-owner"><?php esc_html_e( 'Repository Owner / Group', 'archiviomd' ); ?></label>
				</th>
				<td>
					<input type="text" id="mdsm-repo-owner" name="repo_owner" class="regular-text"
						value="<?php echo esc_attr( $repo_owner ); ?>"
						placeholder="<?php esc_attr_e( 'e.g. myusername or my-org', 'archiviomd' ); ?>" />
					<p class="description"><?php esc_html_e( 'GitHub: your username or organisation. GitLab: your username or group path.', 'archiviomd' ); ?></p>
				</td>
			</tr>

			<!-- Repo Name -->
			<tr class="mdsm-anchor-requires-provider">
				<th scope="row">
					<label for="mdsm-repo-name"><?php esc_html_e( 'Repository Name', 'archiviomd' ); ?></label>
				</th>
				<td>
					<input type="text" id="mdsm-repo-name" name="repo_name" class="regular-text"
						value="<?php echo esc_attr( $repo_name ); ?>"
						placeholder="<?php esc_attr_e( 'e.g. document-hashes', 'archiviomd' ); ?>" />
				</td>
			</tr>

			<!-- Branch -->
			<tr class="mdsm-anchor-requires-provider">
				<th scope="row">
					<label for="mdsm-branch"><?php esc_html_e( 'Branch', 'archiviomd' ); ?></label>
				</th>
				<td>
					<input type="text" id="mdsm-branch" name="branch" class="regular-text"
						value="<?php echo esc_attr( $branch ); ?>"
						placeholder="main" />
					<p class="description"><?php esc_html_e( 'The branch must already exist in the repository.', 'archiviomd' ); ?></p>
				</td>
			</tr>

			<!-- Folder Path -->
			<tr class="mdsm-anchor-requires-provider">
				<th scope="row">
					<label for="mdsm-folder-path"><?php esc_html_e( 'Folder Path', 'archiviomd' ); ?></label>
				</th>
				<td>
					<input type="text" id="mdsm-folder-path" name="folder_path" class="regular-text"
						value="<?php echo esc_attr( $folder_path ); ?>"
						placeholder="hashes/YYYY-MM-DD" />
					<p class="description">
						<?php esc_html_e( 'Path within the repository where anchor files are stored. Supports date tokens: YYYY, MM, DD.', 'archiviomd' ); ?>
						<?php esc_html_e( 'Example: hashes/YYYY-MM-DD → hashes/2025-06-15/', 'archiviomd' ); ?>
					</p>
				</td>
			</tr>

			<!-- Commit message -->
			<tr class="mdsm-anchor-requires-provider">
				<th scope="row">
					<label for="mdsm-commit-message"><?php esc_html_e( 'Commit Message Template', 'archiviomd' ); ?></label>
				</th>
				<td>
					<input type="text" id="mdsm-commit-message" name="commit_message" class="large-text"
						value="<?php echo esc_attr( $commit_msg ); ?>"
						placeholder="chore: anchor {doc_id}" />
					<p class="description"><?php esc_html_e( 'Use {doc_id} as a placeholder for the document identifier.', 'archiviomd' ); ?></p>
				</td>
			</tr>

		</table>

		<!-- Action buttons -->
		<div class="mdsm-anchor-actions">
			<button type="button" id="mdsm-anchor-save" class="button button-primary">
				<?php esc_html_e( 'Save Settings', 'archiviomd' ); ?>
			</button>

			<button type="button" id="mdsm-anchor-test" class="button button-secondary mdsm-anchor-requires-provider">
				<?php esc_html_e( 'Test API Connection', 'archiviomd' ); ?>
			</button>
		</div>

		<!-- Test result area -->
		<div id="mdsm-test-result" class="mdsm-anchor-test-result" style="display:none;"></div>
	</div>

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
		$log_counts = MDSM_Anchor_Log::get_counts();
		?>

		<!-- Summary badges -->
		<div class="mdsm-anchor-log-summary">
			<span class="mdsm-log-badge mdsm-log-badge--all" data-filter="all">
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

		<!-- Download action -->
		<div class="mdsm-anchor-actions" style="margin-top:14px;">
			<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'mdsm_anchor_download_log', 'nonce' => wp_create_nonce( 'mdsm_anchor_nonce' ) ), admin_url( 'admin-ajax.php' ) ) ); ?>"
			   id="mdsm-anchor-download-log"
			   class="button button-secondary">
				<span class="dashicons dashicons-download" style="vertical-align:middle;margin-top:-2px;"></span>
				<?php esc_html_e( 'Download Full Log (.txt)', 'archiviomd' ); ?>
			</a>
		</div>
		<p class="description" style="margin-top:6px;">
			<?php esc_html_e( 'The .txt download includes the complete anchor history with full hash values and error details.', 'archiviomd' ); ?>
		</p>
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
