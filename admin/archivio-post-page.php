<?php
/**
 * Archivio Post Admin Page
 *
 * @package ArchivioMD
 * @since   1.2.0
 * @updated 1.4.0 – HMAC Integrity Mode toggle + status panel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Current settings - default to false (unchecked)
$auto_generate    = get_option( 'archivio_post_auto_generate', false );
$show_badge       = get_option( 'archivio_post_show_badge', false );
$show_badge_posts = get_option( 'archivio_post_show_badge_posts', false );
$show_badge_pages = get_option( 'archivio_post_show_badge_pages', false );

// Ensure boolean values for proper checked() comparison
$auto_generate    = filter_var( $auto_generate, FILTER_VALIDATE_BOOLEAN );
$show_badge       = filter_var( $show_badge, FILTER_VALIDATE_BOOLEAN );
$show_badge_posts = filter_var( $show_badge_posts, FILTER_VALIDATE_BOOLEAN );
$show_badge_pages = filter_var( $show_badge_pages, FILTER_VALIDATE_BOOLEAN );

// Algorithm settings
$active_algorithm  = MDSM_Hash_Helper::get_active_algorithm();
$allowed_algos     = MDSM_Hash_Helper::allowed_algorithms();
$blake2b_available = MDSM_Hash_Helper::is_blake2b_available();
$sha3_available    = MDSM_Hash_Helper::is_sha3_available();

// HMAC settings
$hmac_status = MDSM_Hash_Helper::hmac_status();

// Active tab
$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'settings';
?>

<div class="wrap archivio-post-admin">
	<h1><?php esc_html_e( 'Archivio Post – Content Hash Verification', 'archiviomd' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'Generate and verify deterministic cryptographic hashes for your posts to ensure content integrity. Supports SHA-256, SHA-512, SHA3-256, SHA3-512, and BLAKE2b in Standard and HMAC modes.', 'archiviomd' ); ?>
	</p>

	<nav class="nav-tab-wrapper wp-clearfix" style="margin-top:20px;">
		<a href="?page=archivio-post&tab=settings"
		   class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Settings', 'archiviomd' ); ?>
		</a>
		<a href="?page=archivio-post&tab=audit"
		   class="nav-tab <?php echo $active_tab === 'audit' ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Audit Log', 'archiviomd' ); ?>
		</a>
		<a href="?page=archivio-post&tab=help"
		   class="nav-tab <?php echo $active_tab === 'help' ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Help & Documentation', 'archiviomd' ); ?>
		</a>
	</nav>

	<div class="archivio-post-content">

	<?php if ( $active_tab === 'settings' ) : ?>
	<!-- ================================================================
	     SETTINGS TAB
	     ================================================================ -->
	<div class="archivio-post-tab-content">

		<!-- ── HMAC Integrity Mode ──────────────────────────────────── -->
		<h2><?php esc_html_e( 'HMAC Integrity Mode', 'archiviomd' ); ?></h2>

		<div style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:4px;margin-bottom:30px;">

			<?php
			// ── HMAC status banner ──────────────────────────────────
			if ( $hmac_status['mode_enabled'] ) {
				if ( $hmac_status['notice_level'] === 'error' ) {
					echo '<div style="padding:12px 15px;background:#fde8e8;border-left:4px solid #d73a49;border-radius:4px;margin-bottom:15px;">';
					echo '<strong>' . esc_html__( 'Error:', 'archiviomd' ) . '</strong> ';
					echo wp_kses( $hmac_status['notice_message'], array( 'code' => array() ) );
					echo '</div>';
				} elseif ( $hmac_status['notice_level'] === 'warning' ) {
					echo '<div style="padding:12px 15px;background:#fff8e5;border-left:4px solid #dba617;border-radius:4px;margin-bottom:15px;">';
					echo '<strong>' . esc_html__( 'Warning:', 'archiviomd' ) . '</strong> ';
					echo esc_html( $hmac_status['notice_message'] );
					echo '</div>';
				} else {
					echo '<div style="padding:12px 15px;background:#edfaed;border-left:4px solid #0a7537;border-radius:4px;margin-bottom:15px;">';
					echo '<strong>✓ </strong>';
					echo esc_html( $hmac_status['notice_message'] );
					echo '</div>';
				}
			}
			?>

			<p style="margin-top:0;">
				<?php esc_html_e( 'When enabled, all new hashes are produced using <code>hash_hmac()</code> with a secret key defined in <code>wp-config.php</code>. Existing standard hashes remain fully verifiable.', 'archiviomd' ); ?>
			</p>

			<!-- Key status checklist -->
			<table style="border-collapse:collapse;margin-bottom:20px;">
				<tr>
					<td style="padding:4px 10px 4px 0;">
						<?php if ( $hmac_status['key_defined'] ) : ?>
							<span style="color:#0a7537;font-weight:600;">✓ <?php esc_html_e( 'Key constant defined', 'archiviomd' ); ?></span>
						<?php else : ?>
							<span style="color:#d73a49;font-weight:600;">✗ <?php esc_html_e( 'Key constant missing', 'archiviomd' ); ?></span>
						<?php endif; ?>
					</td>
					<td style="color:#646970;font-size:12px;">
						<?php echo sprintf( '<code>%s</code>', esc_html( MDSM_Hash_Helper::HMAC_KEY_CONSTANT ) ); ?>
						<?php esc_html_e( 'in wp-config.php', 'archiviomd' ); ?>
					</td>
				</tr>
				<tr>
					<td style="padding:4px 10px 4px 0;">
						<?php if ( $hmac_status['key_strong'] ) : ?>
							<span style="color:#0a7537;font-weight:600;">✓ <?php esc_html_e( 'Key length sufficient', 'archiviomd' ); ?></span>
						<?php elseif ( $hmac_status['key_defined'] ) : ?>
							<span style="color:#dba617;font-weight:600;">⚠ <?php esc_html_e( 'Key too short', 'archiviomd' ); ?></span>
						<?php else : ?>
							<span style="color:#646970;">— <?php esc_html_e( 'Key length unknown', 'archiviomd' ); ?></span>
						<?php endif; ?>
					</td>
					<td style="color:#646970;font-size:12px;">
						<?php echo sprintf( esc_html__(  'Minimum recommended: %d characters', 'archiviomd' ), MDSM_Hash_Helper::HMAC_KEY_MIN_LENGTH ); ?>
					</td>
				</tr>
				<tr>
					<td style="padding:4px 10px 4px 0;">
						<?php if ( $hmac_status['hmac_available'] ) : ?>
							<span style="color:#0a7537;font-weight:600;">✓ <?php esc_html_e( 'hash_hmac() available', 'archiviomd' ); ?></span>
						<?php else : ?>
							<span style="color:#d73a49;font-weight:600;">✗ <?php esc_html_e( 'hash_hmac() not available', 'archiviomd' ); ?></span>
						<?php endif; ?>
					</td>
					<td style="color:#646970;font-size:12px;"><?php esc_html_e( 'Built-in PHP function', 'archiviomd' ); ?></td>
				</tr>
			</table>

			<?php if ( ! $hmac_status['key_defined'] ) : ?>
			<!-- wp-config.php snippet -->
			<div style="background:#f5f5f5;padding:12px 15px;border-radius:4px;margin-bottom:20px;border:1px solid #ddd;">
				<p style="margin:0 0 8px;font-weight:600;"><?php esc_html_e( 'Add this to your wp-config.php (before "stop editing"):', 'archiviomd' ); ?></p>
				<pre style="margin:0;font-size:13px;overflow-x:auto;white-space:pre-wrap;">define( '<?php echo esc_html( MDSM_Hash_Helper::HMAC_KEY_CONSTANT ); ?>', 'replace-with-a-long-random-secret-key' );</pre>
				<p style="margin:8px 0 0;font-size:12px;color:#646970;">
					<?php esc_html_e( 'Generate a strong key: <code>openssl rand -base64 48</code>', 'archiviomd' ); ?>
				</p>
			</div>
			<?php endif; ?>

			<!-- Toggle form -->
			<form id="archivio-hmac-form">
				<label style="display:flex;align-items:center;gap:10px;cursor:<?php echo ( ! $hmac_status['key_defined'] || ! $hmac_status['hmac_available'] ) ? 'not-allowed' : 'pointer'; ?>;">
					<input type="checkbox"
					       id="hmac-mode-toggle"
					       name="hmac_mode"
					       value="1"
					       <?php checked( $hmac_status['mode_enabled'], true ); ?>
					       <?php disabled( ! $hmac_status['key_defined'] || ! $hmac_status['hmac_available'], true ); ?>>
					<span>
						<strong><?php esc_html_e( 'Enable HMAC Integrity Mode', 'archiviomd' ); ?></strong>
						<span style="font-size:12px;color:#646970;display:block;">
							<?php esc_html_e( 'Uses hash_hmac() instead of hash() for all new hashes.', 'archiviomd' ); ?>
						</span>
					</span>
				</label>

				<div style="margin-top:15px;">
					<button type="submit" class="button button-primary" id="save-hmac-btn"
					        <?php disabled( ! $hmac_status['key_defined'] || ! $hmac_status['hmac_available'], true ); ?>>
						<?php esc_html_e( 'Save HMAC Setting', 'archiviomd' ); ?>
					</button>
					<span class="archivio-hmac-status" style="margin-left:10px;"></span>
				</div>
			</form>

			<div style="margin-top:15px;padding:10px 15px;background:#f0f6ff;border-left:3px solid #2271b1;border-radius:4px;font-size:12px;color:#1d2327;">
				<strong><?php esc_html_e( 'Key rotation note:', 'archiviomd' ); ?></strong>
				<?php esc_html_e( 'Changing ARCHIVIOMD_HMAC_KEY invalidates all existing HMAC hashes. After rotating the key, republish affected posts to regenerate their HMAC hashes.', 'archiviomd' ); ?>
			</div>
		</div>

		<!-- ── Hash Algorithm ────────────────────────────────────────── -->
		<h2><?php esc_html_e( 'Hash Algorithm', 'archiviomd' ); ?></h2>

		<div style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:4px;margin-bottom:30px;">
			<p style="margin-top:0;">
				<?php esc_html_e( 'Select the algorithm used for new hashes. Existing hashes are never re-computed; they remain verifiable using the algorithm recorded at the time they were created.', 'archiviomd' ); ?>
			</p>

			<form id="archivio-algorithm-form">
				<fieldset style="border:0;padding:0;margin:0;">
					<legend class="screen-reader-text"><?php esc_html_e( 'Hash Algorithm', 'archiviomd' ); ?></legend>

					<!-- Standard Algorithms -->
					<div class="algorithm-section" style="margin-bottom:25px;">
						<h3 style="margin-top:0;margin-bottom:12px;font-size:14px;font-weight:600;color:#1d2327;">
							<?php esc_html_e( 'Standard Algorithms', 'archiviomd' ); ?>
						</h3>
						<?php
						$standard_algos = MDSM_Hash_Helper::standard_algorithms();
						$algo_meta = array(
							'sha256'   => array( 'desc' => __( 'Default, universally supported, 64-char hex', 'archiviomd' ) ),
							'sha512'   => array( 'desc' => __( 'Stronger collision resistance, 128-char hex', 'archiviomd' ) ),
							'sha3-256' => array( 'desc' => __( 'SHA-3 / Keccak sponge, 64-char hex (PHP 7.1+)', 'archiviomd' ) ),
							'sha3-512' => array( 'desc' => __( 'SHA-3 / Keccak sponge, 128-char hex (PHP 7.1+)', 'archiviomd' ) ),
							'blake2b'  => array( 'desc' => __( 'Modern, fast, 128-char hex (PHP 7.2+)', 'archiviomd' ) ),
						);
						foreach ( $standard_algos as $algo_key => $algo_label ) :
							$avail       = MDSM_Hash_Helper::get_algorithm_availability( $algo_key );
							$desc        = isset( $algo_meta[ $algo_key ] ) ? $algo_meta[ $algo_key ]['desc'] : '';
							$unavailable = ! $avail;
						?>
						<label style="display:block;margin-bottom:10px;cursor:<?php echo $unavailable ? 'not-allowed' : 'pointer'; ?>;padding-left:22px;position:relative;">
							<input type="radio"
							       name="algorithm"
							       value="<?php echo esc_attr( $algo_key ); ?>"
							       <?php checked( $active_algorithm, $algo_key ); ?>
							       <?php disabled( $unavailable, true ); ?>
							       style="position:absolute;left:0;top:3px;margin:0;">
							<strong style="font-weight:500;"><?php echo esc_html( $algo_label ); ?></strong>
							<br>
							<span style="color:#646970;font-size:12px;line-height:1.6;">
								<?php echo esc_html( $desc ); ?>
								<?php if ( $unavailable ) : ?>
									<span style="color:#d73a49;">(<?php esc_html_e( 'not available on this PHP build', 'archiviomd' ); ?>)</span>
								<?php else : ?>
									<span style="color:#0a7537;">(<?php esc_html_e( 'available', 'archiviomd' ); ?>)</span>
								<?php endif; ?>
							</span>
						</label>
						<?php endforeach; ?>
					</div>

					<!-- Experimental / Advanced Algorithms -->
					<div class="algorithm-section" style="margin-bottom:20px;padding:15px;background:#fff8e5;border:1px solid #dba617;border-radius:4px;">
						<h3 style="margin-top:0;margin-bottom:8px;font-size:14px;font-weight:600;color:#1d2327;">
							<?php esc_html_e( 'Advanced / Experimental Algorithms', 'archiviomd' ); ?>
						</h3>
						<p style="margin:0 0 12px 0;font-size:12px;color:#646970;">
							<strong><?php esc_html_e( 'Warning:', 'archiviomd' ); ?></strong>
							<?php esc_html_e( 'Experimental algorithms may be slower, may not work on all hosts, and will automatically fall back to SHA-256 or BLAKE2b if unavailable. Use standard algorithms for production sites.', 'archiviomd' ); ?>
						</p>
						<?php
						$experimental_algos = MDSM_Hash_Helper::experimental_algorithms();
						$exp_algo_meta = array(
							'blake3'   => array( 'desc' => __( 'BLAKE3 with 256-bit output, extremely fast (PHP 8.1+ or fallback)', 'archiviomd' ) ),
							'shake128' => array( 'desc' => __( 'SHAKE128 XOF with 256-bit output (PHP 7.1+ or fallback)', 'archiviomd' ) ),
							'shake256' => array( 'desc' => __( 'SHAKE256 XOF with 512-bit output (PHP 7.1+ or fallback)', 'archiviomd' ) ),
						);
						foreach ( $experimental_algos as $algo_key => $algo_label ) :
							$avail = MDSM_Hash_Helper::get_algorithm_availability( $algo_key );
							$desc  = isset( $exp_algo_meta[ $algo_key ] ) ? $exp_algo_meta[ $algo_key ]['desc'] : '';
						?>
						<label style="display:block;margin-bottom:10px;cursor:pointer;padding-left:22px;position:relative;">
							<input type="radio"
							       name="algorithm"
							       value="<?php echo esc_attr( $algo_key ); ?>"
							       <?php checked( $active_algorithm, $algo_key ); ?>
							       style="position:absolute;left:0;top:3px;margin:0;">
							<strong style="font-weight:500;color:#8c400b;"><?php echo esc_html( $algo_label ); ?></strong>
							<br>
							<span style="color:#646970;font-size:12px;line-height:1.6;">
								<?php echo esc_html( $desc ); ?>
								<?php if ( $avail ) : ?>
									<span style="color:#0a7537;">(<?php esc_html_e( 'native available', 'archiviomd' ); ?>)</span>
								<?php else : ?>
									<span style="color:#d73a49;">(<?php esc_html_e( 'fallback mode', 'archiviomd' ); ?>)</span>
								<?php endif; ?>
							</span>
						</label>
						<?php endforeach; ?>
					</div>
				</fieldset>

				<div style="margin-top:15px;">
					<button type="submit" class="button button-primary" id="save-algorithm-btn">
						<?php esc_html_e( 'Save Algorithm', 'archiviomd' ); ?>
					</button>
					<span class="archivio-algorithm-status" style="margin-left:10px;"></span>
				</div>
			</form>

			<?php if ( ! $blake2b_available || ! $sha3_available ) : ?>
			<div style="margin-top:15px;padding:10px 15px;background:#fff8e5;border-left:4px solid #dba617;border-radius:4px;">
				<strong><?php esc_html_e( 'Note:', 'archiviomd' ); ?></strong>
				<?php if ( ! $sha3_available ) : ?>
					<?php esc_html_e( 'SHA3-256 and SHA3-512 require PHP 7.1+. They are not available on this server.', 'archiviomd' ); ?>
				<?php endif; ?>
				<?php if ( ! $blake2b_available ) : ?>
					<?php esc_html_e( 'BLAKE2b requires PHP 7.2+ with OpenSSL support. It is not available on this server.', 'archiviomd' ); ?>
				<?php endif; ?>
			</div>
			<?php endif; ?>
		</div>

		<!-- ── Hash Generation Settings ──────────────────────────────── -->
		<h2><?php esc_html_e( 'Hash Generation Settings', 'archiviomd' ); ?></h2>

		<form id="archivio-post-settings-form">
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="auto-generate">
								<?php esc_html_e( 'Automatic Hash Generation', 'archiviomd' ); ?>
							</label>
						</th>
						<td>
							<label>
								<input type="checkbox"
								       id="auto-generate"
								       name="auto_generate"
								       value="1"
								       <?php checked( $auto_generate, true ); ?>>
								<?php esc_html_e( 'Automatically generate hash when posts are published or updated', 'archiviomd' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'When enabled, a hash using the selected algorithm and current mode is generated for each post on publish/update.', 'archiviomd' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Display Verification Badge', 'archiviomd' ); ?></label>
						</th>
						<td>
							<fieldset>
								<legend class="screen-reader-text">
									<span><?php esc_html_e( 'Badge Display Options', 'archiviomd' ); ?></span>
								</legend>

								<label style="display:block;margin-bottom:10px;">
									<input type="checkbox"
									       id="show-badge"
									       name="show_badge"
									       value="1"
									       <?php checked( $show_badge, true ); ?>>
									<?php esc_html_e( 'Display verification badge (master toggle)', 'archiviomd' ); ?>
								</label>

								<div style="margin-left:25px;padding-left:15px;border-left:3px solid #ddd;">
									<label style="display:block;margin-bottom:10px;">
										<input type="checkbox"
										       id="show-badge-posts"
										       name="show_badge_posts"
										       value="1"
										       <?php checked( $show_badge_posts, true ); ?>>
										<?php esc_html_e( 'Show badge on Posts', 'archiviomd' ); ?>
									</label>

									<label style="display:block;">
										<input type="checkbox"
										       id="show-badge-pages"
										       name="show_badge_pages"
										       value="1"
										       <?php checked( $show_badge_pages, true ); ?>>
										<?php esc_html_e( 'Show badge on Pages', 'archiviomd' ); ?>
									</label>
								</div>
							</fieldset>

							<!-- Badge preview -->
							<div style="margin-top:15px;padding:15px;background:#f9f9f9;border-left:4px solid #2271b1;">
								<strong><?php esc_html_e( 'Badge Preview:', 'archiviomd' ); ?></strong>
								<div style="margin-top:10px;">
									<span class="archivio-post-badge archivio-post-badge-verified">
										<svg class="archivio-post-icon" width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
											<path d="M13.78 4.22a.75.75 0 010 1.06l-7.25 7.25a.75.75 0 01-1.06 0L2.22 9.28a.75.75 0 011.06-1.06L6 10.94l6.72-6.72a.75.75 0 011.06 0z"/>
										</svg>
										<span class="archivio-post-badge-text"><?php esc_html_e( 'Verified', 'archiviomd' ); ?></span>
										<button class="archivio-post-download" title="<?php esc_attr_e( 'Download verification file', 'archiviomd' ); ?>">
											<svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
												<path d="M8.5 1.75a.75.75 0 00-1.5 0v6.69L5.03 6.47a.75.75 0 00-1.06 1.06l3.5 3.5a.75.75 0 001.06 0l3.5-3.5a.75.75 0 10-1.06-1.06L8.5 8.44V1.75zM3.5 11.25a.75.75 0 00-1.5 0v2.5c0 .69.56 1.25 1.25 1.25h10.5A1.25 1.25 0 0015 13.75v-2.5a.75.75 0 00-1.5 0v2.5H3.5v-2.5z"/>
											</svg>
										</button>
									</span>
									<span style="margin-left:10px;" class="archivio-post-badge archivio-post-badge-unverified">
										<svg class="archivio-post-icon" width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
											<path d="M3.72 3.72a.75.75 0 011.06 0L8 6.94l3.22-3.22a.75.75 0 111.06 1.06L9.06 8l3.22 3.22a.75.75 0 11-1.06 1.06L8 9.06l-3.22 3.22a.75.75 0 01-1.06-1.06L6.94 8 3.72 4.78a.75.75 0 010-1.06z"/>
										</svg>
										<span class="archivio-post-badge-text"><?php esc_html_e( 'Unverified', 'archiviomd' ); ?></span>
									</span>
									<span style="margin-left:10px;" class="archivio-post-badge archivio-post-badge-not_signed">
										<svg class="archivio-post-icon" width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
											<path d="M8 2a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 018 2zM8 10a1 1 0 100 2 1 1 0 000-2z"/>
										</svg>
										<span class="archivio-post-badge-text"><?php esc_html_e( 'Not Signed', 'archiviomd' ); ?></span>
									</span>
								</div>
							</div>
						</td>
					</tr>
				</tbody>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary" id="save-settings-btn">
					<?php esc_html_e( 'Save Settings', 'archiviomd' ); ?>
				</button>
				<span class="archivio-post-save-status" style="margin-left:10px;"></span>
			</p>
		</form>

		<hr style="margin:40px 0;">

		<!-- Troubleshooting -->
		<h2><?php esc_html_e( 'Troubleshooting', 'archiviomd' ); ?></h2>
		<div style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:4px;margin-bottom:20px;">
			<h3><?php esc_html_e( 'Enable All Settings', 'archiviomd' ); ?></h3>
			<p><?php esc_html_e( 'Click this button to enable Auto-Generate and all badge display options.', 'archiviomd' ); ?></p>
			<p style="font-size:12px;color:#666;">
				<?php 
				$current_value = get_option('archivio_post_auto_generate');
				printf( '<code>%s</code>', esc_html( var_export( $current_value, true ) ) );
				?>
			</p>
			<button type="button" id="fix-settings-btn" class="button button-secondary">
				<?php esc_html_e( 'Enable All Settings', 'archiviomd' ); ?>
			</button>
			<span class="fix-settings-status" style="margin-left:10px;"></span>
		</div>
		
		<div style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:4px;">
			<h3><?php esc_html_e( 'Recreate Audit Log Table', 'archiviomd' ); ?></h3>
			<p><?php esc_html_e( 'If the audit log is not working, recreate the database table. Existing entries are preserved.', 'archiviomd' ); ?></p>
			<button type="button" id="recreate-table-btn" class="button button-secondary">
				<?php esc_html_e( 'Recreate Database Table', 'archiviomd' ); ?>
			</button>
			<span class="recreate-table-status" style="margin-left:10px;"></span>
		</div>

		<hr style="margin:40px 0;">

		<!-- How It Works -->
		<h2><?php esc_html_e( 'How It Works', 'archiviomd' ); ?></h2>
		<div style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:4px;">
			<ol style="line-height:2;">
				<li><strong><?php esc_html_e( 'Content Canonicalization:', 'archiviomd' ); ?></strong>
					<?php esc_html_e( 'Post content is normalized (LF line endings, trimmed whitespace) and prefixed with post_id and author_id.', 'archiviomd' ); ?>
				</li>
				<li><strong><?php esc_html_e( 'Hash Generation:', 'archiviomd' ); ?></strong>
					<?php esc_html_e( 'A hash is computed using the selected algorithm in Standard or HMAC mode. Both the algorithm and mode are stored alongside the hash.', 'archiviomd' ); ?>
				</li>
				<li><strong><?php esc_html_e( 'Storage:', 'archiviomd' ); ?></strong>
					<?php esc_html_e( 'Standard hashes are packed as "algo:hex". HMAC hashes are packed as "hmac-algo:hex". Author ID and timestamp are saved in post meta and the audit log.', 'archiviomd' ); ?>
				</li>
				<li><strong><?php esc_html_e( 'Verification:', 'archiviomd' ); ?></strong>
					<?php esc_html_e( 'The stored packed string determines the algorithm and mode for re-computation. Standard hashes verify with hash(); HMAC hashes verify with hash_hmac() and the configured key. Legacy SHA-256 bare-hex hashes always verify correctly.', 'archiviomd' ); ?>
				</li>
				<li><strong><?php esc_html_e( 'Badge Display:', 'archiviomd' ); ?></strong>
					<?php esc_html_e( 'The badge shows "Verified" (green), "Unverified" (red), or "Not Signed" (gray).', 'archiviomd' ); ?>
				</li>
			</ol>
		</div>
	</div>

	<?php elseif ( $active_tab === 'audit' ) : ?>
	<!-- ================================================================
	     AUDIT LOG TAB
	     ================================================================ -->
	<div class="archivio-post-tab-content">
		<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
			<h2 style="margin:0;"><?php esc_html_e( 'Audit Log', 'archiviomd' ); ?></h2>
			<div style="display:flex;gap:8px;align-items:center;">
			<button type="button" id="refresh-audit-log" class="button button-secondary">
				<span class="dashicons dashicons-update" style="vertical-align:middle;margin-right:5px;"></span>
				<?php esc_html_e( 'Refresh', 'archiviomd' ); ?>
			</button>
			<button type="button" id="export-audit-csv" class="button button-secondary">
				<span class="dashicons dashicons-download" style="vertical-align:middle;margin-right:5px;"></span>
				<?php esc_html_e( 'Export to CSV', 'archiviomd' ); ?>
			</button>
		</div>
		</div>

		<p class="description">
			<?php esc_html_e( 'All hash generation and unverified events are logged here. The Algorithm and Mode columns show how each hash was produced.', 'archiviomd' ); ?>
		</p>

		<div id="audit-log-container">
			<div class="audit-log-loading" style="text-align:center;padding:40px;">
				<span class="spinner is-active" style="float:none;margin:0 auto;"></span>
				<p><?php esc_html_e( 'Loading audit logs...', 'archiviomd' ); ?></p>
			</div>
		</div>
		<div id="audit-log-pagination" style="margin-top:20px;text-align:center;"></div>
	</div>

	<?php elseif ( $active_tab === 'help' ) : ?>
	<!-- ================================================================
	     HELP TAB
	     ================================================================ -->
	<div class="archivio-post-tab-content">
		<h2><?php esc_html_e( 'Help & Documentation', 'archiviomd' ); ?></h2>

		<div class="archivio-post-help-section" style="background:#fff8e5;padding:20px;border-left:4px solid #dba617;border-radius:4px;margin-bottom:30px;">
			<h3 style="margin-top:0;border:none;"><?php esc_html_e( '⚠️ Important: This is NOT PGP/GPG Signing', 'archiviomd' ); ?></h3>
			<p><strong><?php esc_html_e( 'This feature uses cryptographic hashing ONLY.', 'archiviomd' ); ?></strong></p>
			<p><?php esc_html_e( 'It does NOT use PGP, GPG, or any asymmetric cryptographic signing. HMAC mode adds a shared-secret keyed integrity check — it is not a digital signature and does not involve public/private key pairs.', 'archiviomd' ); ?></p>
		</div>

		<div class="archivio-post-help-section">
			<h3><?php esc_html_e( 'HMAC Integrity Mode', 'archiviomd' ); ?></h3>
			<p><?php esc_html_e( 'HMAC (Hash-based Message Authentication Code) binds a secret key to the hash. This means only someone with the ARCHIVIOMD_HMAC_KEY secret can produce or verify the hash — a standard hash can be independently computed by anyone.', 'archiviomd' ); ?></p>
			<h4><?php esc_html_e( 'Setup', 'archiviomd' ); ?></h4>
			<pre style="background:#f5f5f5;padding:15px;border-radius:4px;overflow-x:auto;"><code>// In wp-config.php, before "stop editing":
define( '<?php echo esc_html( MDSM_Hash_Helper::HMAC_KEY_CONSTANT ); ?>', 'your-random-secret-at-least-32-chars' );

// Generate a strong key on the command line:
openssl rand -base64 48</code></pre>
			<p><?php esc_html_e( 'The key is never stored in the database. Only the boolean toggle (on/off) is saved as a WordPress option.', 'archiviomd' ); ?></p>
		</div>

		<div class="archivio-post-help-section">
			<h3><?php esc_html_e( 'Choosing an Algorithm', 'archiviomd' ); ?></h3>
			<h4 style="margin-top:15px;margin-bottom:10px;font-size:13px;font-weight:600;"><?php esc_html_e( 'Standard Algorithms (Recommended for Production)', 'archiviomd' ); ?></h4>
			<ul style="line-height:2;">
				<li><strong>SHA-256</strong> – <?php esc_html_e( 'Default. 256-bit digest, 64 hex chars. Universally supported.', 'archiviomd' ); ?></li>
				<li><strong>SHA-512</strong> – <?php esc_html_e( '512-bit digest, 128 hex chars. Stronger collision resistance.', 'archiviomd' ); ?></li>
				<li><strong>SHA3-256</strong> – <?php esc_html_e( '256-bit SHA-3 (Keccak sponge), 64 hex chars. PHP 7.1+.', 'archiviomd' ); ?></li>
				<li><strong>SHA3-512</strong> – <?php esc_html_e( '512-bit SHA-3 (Keccak sponge), 128 hex chars. PHP 7.1+.', 'archiviomd' ); ?></li>
				<li><strong>BLAKE2b</strong> – <?php esc_html_e( '512-bit digest, 128 hex chars. Modern, fast. PHP 7.2+ with OpenSSL ≥ 1.1.1.', 'archiviomd' ); ?></li>
			</ul>
			<h4 style="margin-top:15px;margin-bottom:10px;font-size:13px;font-weight:600;"><?php esc_html_e( 'Experimental / Advanced Algorithms', 'archiviomd' ); ?></h4>
			<p style="background:#fff8e5;padding:10px;border-left:3px solid #dba617;border-radius:3px;font-size:12px;">
				<strong><?php esc_html_e( 'Warning:', 'archiviomd' ); ?></strong>
				<?php esc_html_e( 'These algorithms may not be available on all PHP builds and will automatically fall back to SHA-256 or BLAKE2b if unavailable.', 'archiviomd' ); ?>
			</p>
			<ul style="line-height:2;">
				<li><strong>BLAKE3</strong> – <?php esc_html_e( '256-bit output. Extremely fast, parallel hashing. PHP 8.1+ or pure-PHP fallback.', 'archiviomd' ); ?></li>
				<li><strong>SHAKE128</strong> – <?php esc_html_e( 'SHA-3 XOF with 256-bit output. Variable-length output. PHP 7.1+ native or fallback.', 'archiviomd' ); ?></li>
				<li><strong>SHAKE256</strong> – <?php esc_html_e( 'SHA-3 XOF with 512-bit output. Variable-length output. PHP 7.1+ native or fallback.', 'archiviomd' ); ?></li>
			</ul>
			<p><?php esc_html_e( 'Changing the algorithm only affects new hashes. Old hashes verify with the algorithm used when they were created.', 'archiviomd' ); ?></p>
		</div>

		<div class="archivio-post-help-section">
			<h3><?php esc_html_e( 'Shortcode Usage', 'archiviomd' ); ?></h3>
			<pre style="background:#f5f5f5;padding:15px;border-radius:4px;overflow-x:auto;"><code>[hash_verify]
[hash_verify post_id="42"]</code></pre>
		</div>

		<div class="archivio-post-help-section">
			<h3><?php esc_html_e( 'Offline Verification', 'archiviomd' ); ?></h3>
			<p><?php esc_html_e( 'Standard mode – run the command for the algorithm shown in the verification file:', 'archiviomd' ); ?></p>
			<pre style="background:#f5f5f5;padding:15px;border-radius:4px;overflow-x:auto;"><code># SHA-256
echo -n "post_id:123\nauthor_id:1\ncontent:\nYour content" | sha256sum

# SHA-512
echo -n "..." | sha512sum

# SHA3-256
echo -n "..." | openssl dgst -sha3-256

# SHA3-512
echo -n "..." | openssl dgst -sha3-512

# BLAKE2b
echo -n "..." | b2sum -l 512</code></pre>
			<p><?php esc_html_e( 'HMAC mode – the verification file includes the openssl hmac command with a placeholder for your secret key:', 'archiviomd' ); ?></p>
			<pre style="background:#f5f5f5;padding:15px;border-radius:4px;overflow-x:auto;"><code>echo -n "..." | openssl dgst -sha256 -hmac "YOUR_SECRET_KEY"</code></pre>
		</div>

		<div class="archivio-post-help-section">
			<h3><?php esc_html_e( 'Badge Status Meanings', 'archiviomd' ); ?></h3>
			<ul style="line-height:2;">
				<li><strong style="color:#0a7537;"><?php esc_html_e( 'Verified:', 'archiviomd' ); ?></strong>
					<?php esc_html_e( 'Current content matches the stored hash.', 'archiviomd' ); ?></li>
				<li><strong style="color:#d73a49;"><?php esc_html_e( 'Unverified:', 'archiviomd' ); ?></strong>
					<?php esc_html_e( 'Content has changed since hash generation.', 'archiviomd' ); ?></li>
				<li><strong style="color:#6a737d;"><?php esc_html_e( 'Not Signed:', 'archiviomd' ); ?></strong>
					<?php esc_html_e( 'No hash generated yet.', 'archiviomd' ); ?></li>
			</ul>
		</div>

		<div class="archivio-post-help-section">
			<h3><?php esc_html_e( 'Backward Compatibility', 'archiviomd' ); ?></h3>
			<p><?php esc_html_e( 'Hashes generated before v1.3.0 are stored as plain SHA-256 hex strings. Hashes from v1.3.0 are stored as "algo:hex". Hashes from v1.4.0 HMAC mode are stored as "hmac-algo:hex". All three formats coexist and verify correctly without any migration.', 'archiviomd' ); ?></p>
		</div>

		<div class="archivio-post-help-section" style="background:#e7f3ff;padding:20px;border-left:4px solid #2271b1;border-radius:4px;">
			<h3><?php esc_html_e( 'Need More Help?', 'archiviomd' ); ?></h3>
			<p><a href="https://mountainviewprovisions.com/ArchivioMD" target="_blank" rel="noopener">https://mountainviewprovisions.com/ArchivioMD</a></p>
		</div>
	</div>
	<?php endif; ?>

	</div><!-- .archivio-post-content -->
</div><!-- .wrap -->

<?php wp_add_inline_style( 'archivio-post-admin', '.archivio-post-admin .archivio-post-content { margin-top: 20px; }\n.archivio-post-tab-content {\n\tbackground: #fff;\n\tpadding: 20px;\n\tborder: 1px solid #ccd0d4;\n\tborder-radius: 4px;\n}\n.archivio-post-help-section { margin-bottom: 30px; }\n.archivio-post-help-section h3 {\n\tmargin-top: 0;\n\tpadding-bottom: 10px;\n\tborder-bottom: 2px solid #2271b1;\n}\n.archivio-post-help-section h4 { margin-top: 20px; color: #2271b1; }\n\n#audit-log-table { width: 100%; border-collapse: collapse; margin-top: 20px; }\n#audit-log-table th,\n#audit-log-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }\n#audit-log-table th { background: #f9f9f9; font-weight: 600; color: #1d2327; }\n#audit-log-table tr:hover { background: #f9f9f9; }\n\n.audit-log-event-generated { color: #0a7537; }\n.audit-log-event-verified   { color: #2271b1; }\n.audit-log-event-unverified { color: #d73a49; }\n.audit-log-hash  { font-family: monospace; font-size: 12px; word-break: break-all; }\n.audit-log-algo  { font-family: monospace; font-size: 11px; }\n.audit-log-mode-hmac     { color: #7c3aed; font-weight: 600; }\n.audit-log-mode-standard { color: #646970; }\n#audit-log-pagination button { margin: 0 5px; }\n.audit-log-type-post  { color: #2271b1; font-size: 11px; font-weight: 600; }\n.audit-log-type-page  { color: #7c3aed; font-size: 11px; font-weight: 600; }' ); ?>

<?php
ob_start();
?>
jQuery(document).ready(function($) {

	// ── Force checkbox states to match stored values ────────────────────
	// Fix for issue where checkboxes appear checked but aren't actually checked
	var checkboxStates = {
		'auto-generate': archivioPostData.checkboxStates['auto-generate'],
		'show-badge': archivioPostData.checkboxStates['show-badge'],
		'show-badge-posts': archivioPostData.checkboxStates['show-badge-posts'],
		'show-badge-pages': archivioPostData.checkboxStates['show-badge-pages']
	};
	
	$.each(checkboxStates, function(id, shouldBeChecked) {
		var $checkbox = $('#' + id);
		if ($checkbox.length) {
			var isChecked = $checkbox.prop('checked');
			if (isChecked !== shouldBeChecked) {
				$checkbox.prop('checked', shouldBeChecked);
			}
		}
	});

	// ── HMAC form ────────────────────────────────────────────────────
	$('#archivio-hmac-form').on('submit', function(e) {
		e.preventDefault();

		var $btn    = $('#save-hmac-btn');
		var $status = $('.archivio-hmac-status');
		var enabled = $('#hmac-mode-toggle').is(':checked');

		$btn.prop('disabled', true);
		$status.html('<span class="spinner is-active" style="float:none;"></span>');

		$.ajax({
			url:  archivioPostData.ajaxUrl,
			type: 'POST',
			data: {
				action:    'archivio_post_save_hmac_settings',
				nonce:     archivioPostData.nonce,
				hmac_mode: enabled ? 'true' : 'false'
			},
			success: function(response) {
				if (response.success) {
					var msg = '<span style="color:#0a7537;">✓ ' + response.data.message + '</span>';
					if (response.data.notice_level === 'warning') {
						msg += '<br><span style="color:#dba617;">⚠ ' + response.data.notice_message + '</span>';
					}
					$status.html(msg);
				} else {
					$status.html('<span style="color:#d73a49;">✗ ' + (response.data.message || archivioPostData.strings.error) + '</span>');
				}
			},
			error: function() {
				$status.html('<span style="color:#d73a49;">✗ ' + archivioPostData.strings.error + '</span>');
			},
			complete: function() {
				$btn.prop('disabled', false);
				setTimeout(function() {
					$status.fadeOut(function() { $(this).html('').show(); });
				}, 5000);
			}
		});
	});

	// ── Algorithm form ───────────────────────────────────────────────
	$('#archivio-algorithm-form').on('submit', function(e) {
		e.preventDefault();

		var $btn    = $('#save-algorithm-btn');
		var $status = $('.archivio-algorithm-status');
		var algo    = $('input[name="algorithm"]:checked').val();

		$btn.prop('disabled', true);
		$status.html('<span class="spinner is-active" style="float:none;"></span>');

		$.ajax({
			url:  archivioPostData.ajaxUrl,
			type: 'POST',
			data: {
				action:    'archivio_post_save_algorithm',
				nonce:     archivioPostData.nonce,
				algorithm: algo
			},
			success: function(response) {
				if (response.success) {
					var msg = '<span style="color:#0a7537;">✓ ' + response.data.message + '</span>';
					if (response.data.warning) {
						msg += '<br><span style="color:#d73a49;">⚠ ' + response.data.warning + '</span>';
					}
					$status.html(msg);
				} else {
					$status.html('<span style="color:#d73a49;">✗ ' + (response.data.message || archivioPostData.strings.error) + '</span>');
				}
			},
			error: function() {
				$status.html('<span style="color:#d73a49;">✗ ' + archivioPostData.strings.error + '</span>');
			},
			complete: function() {
				$btn.prop('disabled', false);
				setTimeout(function() {
					$status.fadeOut(function() { $(this).html('').show(); });
				}, 5000);
			}
		});
	});

	// ── Settings form ────────────────────────────────────────────────
	$('#archivio-post-settings-form').on('submit', function(e) {
		e.preventDefault();

		var $btn    = $('#save-settings-btn');
		var $status = $('.archivio-post-save-status');
		
		var autoGenChecked = $('#auto-generate').is(':checked');

		$btn.prop('disabled', true);
		$status.html('<span class="spinner is-active" style="float:none;"></span>');
		
		var postData = {
			action:           'archivio_post_save_settings',
			nonce:            archivioPostData.nonce,
			auto_generate:    autoGenChecked ? 'true' : 'false',
			show_badge:       $('#show-badge').is(':checked')       ? 'true' : 'false',
			show_badge_posts: $('#show-badge-posts').is(':checked') ? 'true' : 'false',
			show_badge_pages: $('#show-badge-pages').is(':checked') ? 'true' : 'false'
		};

		$.ajax({
			url:  archivioPostData.ajaxUrl,
			type: 'POST',
			data: postData,
			success: function(response) {
				if (response.success) {
					$status.html('<span style="color:#0a7537;">✓ ' + response.data.message + '</span>');
				} else {
					$status.html('<span style="color:#d73a49;">✗ ' + (response.data.message || archivioPostData.strings.error) + '</span>');
				}
			},
			error: function(xhr, status, error) {
				$status.html('<span style="color:#d73a49;">✗ ' + archivioPostData.strings.error + '</span>');
			},
			complete: function() {
				$btn.prop('disabled', false);
				setTimeout(function() {
					$status.fadeOut(function() { $(this).html('').show(); });
				}, 3000);
			}
		});
	});


	// Audit log functions are in archivio-post-admin.js

	// ── CSV Export ───────────────────────────────────────────────────
	$('#export-audit-csv').on('click', function() {
		var $btn         = $(this);
		var originalHtml = $btn.html();

		$btn.prop('disabled', true).html(
			'<span class="spinner is-active" style="float:none;margin:0 5px 0 0;"></span>Exporting...');

		var form = $('<form>', { method: 'POST', action: archivioPostData.ajaxUrl });
		form.append($('<input>', { type: 'hidden', name: 'action', value: 'archivio_post_export_audit_csv' }));
		form.append($('<input>', { type: 'hidden', name: 'nonce',  value: archivioPostData.nonce }));
		$('body').append(form);
		form.submit();
		form.remove();

		setTimeout(function() { $btn.prop('disabled', false).html(originalHtml); }, 2000);
	});

	// ── Fix Settings Button ────────────────────────────────────────
	$('#fix-settings-btn').on('click', function() {
		var $btn = $(this);
		var $status = $('.fix-settings-status');

		if (!confirm('This will enable Auto-Generate and all badge settings. Continue?')) {
			return;
		}

		$btn.prop('disabled', true).text('Enabling...');
		$status.html('<span class="spinner is-active" style="float:none;"></span>');

		$.ajax({
			url:  archivioPostData.ajaxUrl,
			type: 'POST',
			data: {
				action: 'archivio_post_fix_settings',
				nonce:  archivioPostData.nonce
			},
			success: function(response) {
				if (response.success) {
					$status.html('<span style="color:#0a7537;">✓ ' + response.data.message + '</span>');
					// Force update the checkboxes
					$('#auto-generate').prop('checked', true);
					$('#show-badge').prop('checked', true);
					$('#show-badge-posts').prop('checked', true);
					$('#show-badge-pages').prop('checked', true);
					// Reload page after 2 seconds
					setTimeout(function() {
						location.reload();
					}, 2000);
				} else {
					$status.html('<span style="color:#d73a49;">✗ ' + (response.data.message || 'Error') + '</span>');
				}
			},
			error: function() {
				$status.html('<span style="color:#d73a49;">✗ Error occurred</span>');
			},
			complete: function() {
				$btn.prop('disabled', false).text('Enable All Settings');
			}
		});
	});

	// ── Recreate table ───────────────────────────────────────────────
	$('#recreate-table-btn').on('click', function() {
		var $btn    = $(this);
		var $status = $('.recreate-table-status');

		if (!confirm('Recreate the audit log table? Existing entries will be preserved.')) { return; }

		$btn.prop('disabled', true).text('Recreating...');
		$status.html('<span class="spinner is-active" style="float:none;"></span>');

		$.ajax({
			url:  archivioPostData.ajaxUrl,
			type: 'POST',
			data: {
				action: 'archivio_post_recreate_table',
				nonce:  archivioPostData.nonce
			},
			success: function(response) {
				if (response.success) {
					$status.html('<span style="color:#0a7537;">✓ ' + response.data.message + '</span>');
				} else {
					$status.html('<span style="color:#d73a49;">✗ ' + (response.data.message || 'Error') + '</span>');
				}
			},
			error: function() {
				$status.html('<span style="color:#d73a49;">✗ Error occurred</span>');
			},
			complete: function() {
				$btn.prop('disabled', false).text('Recreate Database Table');
				setTimeout(function() {
					$status.fadeOut(function() { $(this).html('').show(); });
				}, 5000);
			}
		});
	});

});
<?php
$_archivio_inline_js = ob_get_clean();
wp_add_inline_script( 'archivio-post-admin', $_archivio_inline_js );
?>
