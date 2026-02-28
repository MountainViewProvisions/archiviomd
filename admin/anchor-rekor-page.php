<?php
/**
 * Rekor / Sigstore Transparency Log -- Admin Settings Page
 *
 * Rendered by MDSM_External_Anchoring::render_rekor_page().
 *
 * @package ArchivioMD
 * @since   1.7.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( __( 'You do not have sufficient permissions to access this page.', 'archiviomd' ) );
}

$anchoring        = MDSM_External_Anchoring::get_instance();
$settings         = $anchoring->get_settings();
$rekor_enabled    = ! empty( $settings['rekor_enabled'] ) && '1' === (string) $settings['rekor_enabled'];
$active_providers = $anchoring->get_active_providers();
$is_enabled       = $anchoring->is_enabled();
$queue_count      = MDSM_Anchor_Queue::count();
$sodium_ok        = function_exists( 'sodium_crypto_sign_keypair' );
$openssl_ok       = extension_loaded( 'openssl' );
$has_site_keys    = defined( 'ARCHIVIOMD_ED25519_PRIVATE_KEY' ) && defined( 'ARCHIVIOMD_ED25519_PUBLIC_KEY' )
	&& ! empty( constant( 'ARCHIVIOMD_ED25519_PRIVATE_KEY' ) )
	&& ! empty( constant( 'ARCHIVIOMD_ED25519_PUBLIC_KEY' ) );
?>
<div class="wrap mdsm-anchor-wrap">
	<h1 class="mdsm-anchor-title">
		<span class="dashicons dashicons-networking" style="font-size:26px;margin-right:8px;vertical-align:middle;color:#2271b1;"></span>
		<?php esc_html_e( 'Rekor / Sigstore Transparency Log', 'archiviomd' ); ?>
	</h1>

	<p class="mdsm-anchor-intro">
		<?php esc_html_e( 'Submit document integrity hashes to the Sigstore public Rekor transparency log -- a Linux Foundation project providing an immutable, append-only audit trail for digital signatures. Verification requires no pre-trusted key.', 'archiviomd' ); ?>
	</p>

	<p style="font-size:13px;margin-bottom:16px;">
		<?php esc_html_e( 'Looking for other anchoring methods?', 'archiviomd' ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=archivio-git-distribution' ) ); ?>"><?php esc_html_e( 'Git Distribution', 'archiviomd' ); ?> &rarr;</a>
		&nbsp;|&nbsp;
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=archivio-timestamps' ) ); ?>"><?php esc_html_e( 'RFC 3161 Trusted Timestamps', 'archiviomd' ); ?> &rarr;</a>
	</p>

	<?php if ( $is_enabled && $rekor_enabled ) : ?>
	<div class="notice notice-success mdsm-anchor-notice">
		<p>
			<strong><?php esc_html_e( 'Rekor anchoring is active.', 'archiviomd' ); ?></strong>
			<?php if ( $queue_count > 0 ) : ?>
				<?php echo esc_html( sprintf( _n( '%d job pending in queue.', '%d jobs pending in queue.', $queue_count, 'archiviomd' ), $queue_count ) ); ?>
			<?php else : ?>
				<?php esc_html_e( 'Queue is empty -- all submissions are up to date.', 'archiviomd' ); ?>
			<?php endif; ?>
		</p>
	</div>
	<?php endif; ?>

	<!-- Server requirements -->
	<div class="mdsm-anchor-card" style="margin-bottom:24px;">
		<h2 style="margin-top:0;"><?php esc_html_e( 'Server Requirements', 'archiviomd' ); ?></h2>
		<table class="widefat striped" style="max-width:560px;">
			<tbody>
				<tr>
					<td><?php esc_html_e( 'PHP Sodium (ext-sodium)', 'archiviomd' ); ?></td>
					<td><?php if ( $sodium_ok ) : ?><span style="color:#00a32a;">&#10004; <?php esc_html_e( 'Available', 'archiviomd' ); ?></span><?php else : ?><span style="color:#d63638;">&#10008; <?php esc_html_e( 'Not available -- required for Ed25519 signing', 'archiviomd' ); ?></span><?php endif; ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'PHP OpenSSL (ext-openssl)', 'archiviomd' ); ?></td>
					<td><?php if ( $openssl_ok ) : ?><span style="color:#00a32a;">&#10004; <?php esc_html_e( 'Available', 'archiviomd' ); ?></span><?php else : ?><span style="color:#d63638;">&#10008; <?php esc_html_e( 'Not available -- required', 'archiviomd' ); ?></span><?php endif; ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Site Ed25519 keys (wp-config.php)', 'archiviomd' ); ?></td>
					<td><?php if ( $has_site_keys ) : ?><span style="color:#00a32a;">&#10004; <?php esc_html_e( 'Configured -- long-lived site keys will be used', 'archiviomd' ); ?></span><?php else : ?><span style="color:#dba617;">&#9888; <?php esc_html_e( 'Not set -- ephemeral keypairs will be generated per submission (still valid)', 'archiviomd' ); ?></span><?php endif; ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Rekor API endpoint', 'archiviomd' ); ?></td>
					<td><code>https://rekor.sigstore.dev/api/v1</code></td>
				</tr>
			</tbody>
		</table>
		<?php if ( ! $sodium_ok || ! $openssl_ok ) : ?>
		<div class="notice notice-error inline" style="margin-top:12px;">
			<p><?php esc_html_e( 'One or more required PHP extensions are missing. Contact your hosting provider to enable them before activating Rekor anchoring.', 'archiviomd' ); ?></p>
		</div>
		<?php endif; ?>
	</div>

	<!-- How it works -->
	<details class="mdsm-anchor-card" style="margin-bottom:24px;">
		<summary style="font-weight:600;cursor:pointer;font-size:14px;"><?php esc_html_e( 'How Rekor anchoring works', 'archiviomd' ); ?></summary>
		<div style="margin-top:12px;font-size:13px;line-height:1.7;">
			<ol>
				<li><?php esc_html_e( 'When a document is published or updated, ArchivioMD computes its SHA-256 hash and queues an anchor job.', 'archiviomd' ); ?></li>
				<li><?php esc_html_e( 'The cron processor takes the JSON anchor record, computes its SHA-256, signs the hash bytes with an Ed25519 key, and encodes the public key as a PEM PKIX block.', 'archiviomd' ); ?></li>
				<li><?php esc_html_e( 'A hashedrekord v0.0.1 entry is POSTed to the Rekor public instance (rekor.sigstore.dev). This is a plain HTTPS JSON call -- no special library needed.', 'archiviomd' ); ?></li>
				<li><?php esc_html_e( 'Rekor returns a log index and a signed inclusion proof. The log index is stored in the anchor activity log and can be looked up by anyone.', 'archiviomd' ); ?></li>
			</ol>
			<p><strong><?php esc_html_e( 'Verification:', 'archiviomd' ); ?></strong> <?php esc_html_e( "Anyone can verify a Rekor entry without pre-trusting the signer's key:", 'archiviomd' ); ?></p>
			<pre style="background:#f0f0f1;padding:10px;border-radius:4px;overflow:auto;font-size:12px;">rekor-cli get --log-index &lt;INDEX&gt;
rekor-cli verify --artifact-hash sha256:&lt;HASH&gt; --log-index &lt;INDEX&gt;

# Or via the REST API:
curl https://rekor.sigstore.dev/api/v1/log/entries?logIndex=&lt;INDEX&gt;</pre>
			<p><?php esc_html_e( 'Entries can also be browsed at:', 'archiviomd' ); ?> <a href="https://search.sigstore.dev/" target="_blank" rel="noopener noreferrer">search.sigstore.dev</a></p>
		</div>
	</details>

	<!-- Settings -->
	<div class="mdsm-anchor-card" style="margin-bottom:24px;">
		<h2 style="margin-top:0;"><?php esc_html_e( 'Settings', 'archiviomd' ); ?></h2>
		<form id="mdsm-rekor-settings-form" autocomplete="off">
			<?php wp_nonce_field( 'mdsm_anchor_nonce', 'nonce' ); ?>
			<input type="hidden" name="action" value="mdsm_anchor_save_settings">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable Rekor Anchoring', 'archiviomd' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="rekor_enabled" value="1" <?php checked( $rekor_enabled ); ?> id="mdsm-rekor-enabled" <?php echo ( ! $sodium_ok || ! $openssl_ok ) ? 'disabled' : ''; ?>>
							<?php esc_html_e( 'Submit anchor records to the Sigstore Rekor transparency log', 'archiviomd' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'When enabled, every anchor job also POSTs a hashedrekord entry to rekor.sigstore.dev. This runs asynchronously via WP-Cron and never delays document saves. Can be combined with GitHub/GitLab and RFC 3161.', 'archiviomd' ); ?></p>
					</td>
				</tr>
			</table>
			<p class="submit" style="margin-top:16px;">
				<button type="button" id="mdsm-rekor-save-btn" class="button button-primary" <?php echo ( ! $sodium_ok || ! $openssl_ok ) ? 'disabled' : ''; ?>><?php esc_html_e( 'Save Settings', 'archiviomd' ); ?></button>
				<button type="button" id="mdsm-rekor-test-btn" class="button button-secondary" style="margin-left:8px;" <?php echo ( ! $sodium_ok || ! $openssl_ok ) ? 'disabled' : ''; ?>><?php esc_html_e( 'Test Rekor Connection', 'archiviomd' ); ?></button>
				<span id="mdsm-rekor-feedback" style="margin-left:12px;vertical-align:middle;font-size:13px;"></span>
			</p>
		</form>
	</div>

	<!-- Activity log -->
	<div class="mdsm-anchor-card" style="margin-bottom:24px;">
		<h2 style="margin-top:0;"><?php esc_html_e( 'Rekor Activity Log', 'archiviomd' ); ?></h2>
		<p class="description" style="margin-bottom:12px;">
			<?php esc_html_e( 'Every Rekor submission attempt is recorded below. Use "View on Sigstore" to open the entry in the Sigstore search UI, or click "Verify" to pull the live inclusion proof from the Rekor API without leaving this page.', 'archiviomd' ); ?>
		</p>
		<div style="margin-bottom:10px;display:flex;align-items:center;gap:12px;">
			<select id="mdsm-log-filter" style="width:auto;">
				<option value="all"><?php esc_html_e( 'All Statuses', 'archiviomd' ); ?></option>
				<option value="anchored"><?php esc_html_e( 'Anchored', 'archiviomd' ); ?></option>
				<option value="retry"><?php esc_html_e( 'Pending Retry', 'archiviomd' ); ?></option>
				<option value="failed"><?php esc_html_e( 'Failed', 'archiviomd' ); ?></option>
			</select>
			<button type="button" id="mdsm-log-refresh-btn" class="button button-secondary"><?php esc_html_e( 'Refresh', 'archiviomd' ); ?></button>
		</div>
		<div id="mdsm-anchor-log-container">
			<p style="color:#888;"><?php esc_html_e( 'Loading...', 'archiviomd' ); ?></p>
		</div>
	</div>
</div>

<style>
.mdsm-verify-row > td { padding: 0 !important; border-top: none !important; background: transparent !important; }
.mdsm-verify-panel {
	margin: 0; padding: 14px 16px 14px 40px;
	background: #f6f7f7; border-left: 4px solid #2271b1; font-size: 12px; line-height: 1.7;
}
.mdsm-verify-panel.is-ok  { border-left-color: #00a32a; background: #f0faf0; }
.mdsm-verify-panel.is-err { border-left-color: #d63638; background: #fdf0f0; }
.mdsm-verify-panel table  { border-collapse: collapse; width: 100%; max-width: 680px; }
.mdsm-verify-panel table td { padding: 3px 8px 3px 0; vertical-align: top; border: none !important; background: transparent !important; }
.mdsm-verify-panel table td:first-child { color: #666; white-space: nowrap; width: 160px; font-weight: 600; }
.mdsm-verify-btn { font-size: 11px !important; height: 22px !important; line-height: 20px !important; padding: 0 8px !important; margin-left: 6px !important; vertical-align: middle; }
.mdsm-spin { display:inline-block; width:10px; height:10px; border:2px solid #2271b1; border-top-color:transparent; border-radius:50%; animation:mdsmspin 0.6s linear infinite; vertical-align:middle; margin-right:4px; }
@keyframes mdsmspin { to { transform: rotate(360deg); } }
.ok  { color: #00a32a; font-weight: 700; }
.err { color: #d63638; font-weight: 700; }
</style>

<script>
(function($){
	var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
	var nonce   = <?php echo wp_json_encode( wp_create_nonce( 'mdsm_anchor_nonce' ) ); ?>;

	function esc(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

	function logIndex(url){
		if(!url) return 0;
		var m = url.match(/[?&]logIndex=(\d+)/);
		return m ? parseInt(m[1],10) : 0;
	}

	// Save
	$('#mdsm-rekor-save-btn').on('click',function(){
		var $b=$(this),$f=$('#mdsm-rekor-feedback');
		$b.prop('disabled',true); $f.text('<?php echo esc_js(__('Saving...','archiviomd')); ?>').css('color','#555');
		$.post(ajaxUrl,{action:'mdsm_anchor_save_settings',nonce:nonce,rekor_enabled:$('#mdsm-rekor-enabled').is(':checked')?'1':''},function(r){
			$b.prop('disabled',false);
			$f.text(r.success?r.data.message:'<?php echo esc_js(__('Error saving.','archiviomd')); ?>').css('color',r.success?'#00a32a':'#d63638');
		}).fail(function(){ $b.prop('disabled',false); $f.text('<?php echo esc_js(__('Request failed.','archiviomd')); ?>').css('color','#d63638'); });
	});

	// Test connection
	$('#mdsm-rekor-test-btn').on('click',function(){
		var $b=$(this),$f=$('#mdsm-rekor-feedback');
		$b.prop('disabled',true); $f.text('<?php echo esc_js(__('Testing...','archiviomd')); ?>').css('color','#555');
		$.post(ajaxUrl,{action:'mdsm_anchor_test_connection',nonce:nonce,provider:'rekor',rekor_enabled:'1'},function(r){
			$b.prop('disabled',false);
			$f.text(r.success?r.data.message:(r.data&&r.data.message?r.data.message:'<?php echo esc_js(__('Failed.','archiviomd')); ?>')).css('color',r.success?'#00a32a':'#d63638');
		}).fail(function(){ $b.prop('disabled',false); $f.text('<?php echo esc_js(__('Request failed.','archiviomd')); ?>').css('color','#d63638'); });
	});

	// Verify button (delegated -- rows are built dynamically)
	$(document).on('click','.mdsm-verify-btn',function(){
		var $btn=$(this), $row=$btn.closest('tr');
		var idx=$btn.data('log-index'), localHash=$btn.data('local-hash')||'';
		var panelId='mdsm-vp-'+idx, $existing=$('#'+panelId);

		// Toggle off if already open
		if($existing.length){ $existing.remove(); $btn.html('<?php echo esc_js(__('Verify &#10003;','archiviomd')); ?>').prop('disabled',false); return; }

		$btn.html('<span class="mdsm-spin"></span><?php echo esc_js(__('Verifying...','archiviomd')); ?>').prop('disabled',true);

		$.post(ajaxUrl,{action:'mdsm_anchor_rekor_verify',nonce:nonce,log_index:idx,local_hash:localHash},function(r){
			$btn.html('<?php echo esc_js(__('Verify &#10003;','archiviomd')); ?>').prop('disabled',false);
			var colspan=$row.find('td').length, panelClass, inner;

			if(r.success){
				var d=r.data, ok=d.index_matches;
				panelClass = ok ? 'is-ok' : 'is-err';
				var headline = ok
					? '<strong class="ok">&#10004; <?php echo esc_js(__('Entry confirmed on Rekor transparency log','archiviomd')); ?></strong>'
					: '<strong class="err">&#10008; <?php echo esc_js(__('Could not confirm log index match','archiviomd')); ?></strong>';

				var rows = [
					['<?php echo esc_js(__('Entry UUID','archiviomd')); ?>',         '<code style="font-size:11px;word-break:break-all;">'+esc(d.uuid)+'</code>'],
					['<?php echo esc_js(__('Log index','archiviomd')); ?>',           esc(d.log_index)],
					['<?php echo esc_js(__('Integrated at','archiviomd')); ?>',       esc(d.integrated_time||'--')],
					['<?php echo esc_js(__('Artifact hash (Rekor)','archiviomd')); ?>','<code style="font-size:11px;word-break:break-all;">'+esc(d.rekor_algorithm+': '+d.rekor_hash)+'</code>'],
					['<?php echo esc_js(__('Inclusion proof','archiviomd')); ?>',     d.has_inclusion_proof?'<span class="ok">&#10004; <?php echo esc_js(__('Present','archiviomd')); ?></span>':'<span class="err">&#10008; <?php echo esc_js(__('Missing','archiviomd')); ?></span>'],
					['<?php echo esc_js(__('Signed entry timestamp','archiviomd')); ?>',d.signed_entry_ts?'<span class="ok">&#10004; <?php echo esc_js(__('Present','archiviomd')); ?></span>':'<span class="err">&#10008; <?php echo esc_js(__('Missing','archiviomd')); ?></span>'],
				];
				if(d.tree_size)       rows.push(['<?php echo esc_js(__('Tree size at inclusion','archiviomd')); ?>',esc(d.tree_size)]);
				if(d.checkpoint_hash) rows.push(['<?php echo esc_js(__('Checkpoint','archiviomd')); ?>','<code style="font-size:11px;word-break:break-all;">'+esc(d.checkpoint_hash)+'</code>']);
				rows.push(['<?php echo esc_js(__('Open in Sigstore','archiviomd')); ?>','<a href="'+esc(d.sigstore_url)+'" target="_blank" rel="noopener noreferrer">'+esc(d.sigstore_url)+' &#8599;</a>']);

				// Provenance block -- customProperties embedded in the Rekor entry.
				var provenanceHtml = '';
				if(d.custom_props && Object.keys(d.custom_props).length){
					var cp = d.custom_props;
					var keyType = cp['archiviomd.key_type'] || '';
					var fingerprint = cp['archiviomd.pubkey_fingerprint'] || '';
					var pubkeyUrl   = cp['archiviomd.pubkey_url'] || '';

					var fpDisplay = fingerprint === 'ephemeral'
						? '<span style="color:#888;"><?php echo esc_js(__('ephemeral -- generated for this submission only','archiviomd')); ?></span>'
						: '<code style="font-size:11px;word-break:break-all;">'+esc(fingerprint)+'</code>';

					var provenanceRows = [
						['<?php echo esc_js(__('Site URL','archiviomd')); ?>',         esc(cp['archiviomd.site_url']||'')],
						['<?php echo esc_js(__('Document ID','archiviomd')); ?>',      esc(cp['archiviomd.document_id']||'')],
						['<?php echo esc_js(__('Post type','archiviomd')); ?>',        esc(cp['archiviomd.post_type']||'')],
						['<?php echo esc_js(__('Hash algorithm','archiviomd')); ?>',   esc(cp['archiviomd.hash_algorithm']||'')],
						['<?php echo esc_js(__('Plugin version','archiviomd')); ?>',   esc(cp['archiviomd.plugin_version']||'')],
						['<?php echo esc_js(__('Key type','archiviomd')); ?>',         esc(keyType)],
						['<?php echo esc_js(__('Pubkey fingerprint','archiviomd')); ?>',fpDisplay],
					];

					if(pubkeyUrl){
						provenanceRows.push([
							'<?php echo esc_js(__('Verify key at','archiviomd')); ?>',
							'<a href="'+esc(pubkeyUrl)+'" target="_blank" rel="noopener noreferrer">'+esc(pubkeyUrl)+' &#8599;</a>'
								+'<span style="color:#888;font-size:11px;display:block;margin-top:2px;">'
								+'<?php echo esc_js(__('Fetch this URL, hex-decode its contents, SHA-256 hash the bytes, and compare against the pubkey fingerprint above to verify site identity.','archiviomd')); ?>'
								+'</span>'
						]);
					}

					provenanceHtml = '<div style="margin-top:14px;padding-top:10px;border-top:1px solid #ddd;">'
						+ '<strong style="font-size:12px;color:#444;"><?php echo esc_js(__('Provenance (embedded in Rekor entry)','archiviomd')); ?></strong>'
						+ '<p style="margin:4px 0 8px;color:#666;font-size:11px;"><?php echo esc_js(__('These fields are stored inside the Rekor entry body. They are human-readable metadata -- not cryptographically verified by Rekor itself.','archiviomd')); ?></p>'
						+ '<table>'
						+ provenanceRows.map(function(p){ return '<tr><td>'+p[0]+'</td><td>'+p[1]+'</td></tr>'; }).join('')
						+ '</table>'
						+ '</div>';
				}

				inner = headline + '<table style="margin-top:10px;">'
					+ rows.map(function(p){ return '<tr><td>'+p[0]+'</td><td>'+p[1]+'</td></tr>'; }).join('')
					+ '</table>'
					+ provenanceHtml;
			} else {
				panelClass='is-err';
				inner='<strong class="err">&#10008; <?php echo esc_js(__('Verification failed','archiviomd')); ?></strong><br>'
					+ esc(r.data&&r.data.message?r.data.message:'Unknown error');
			}

			var $panel=$('<tr id="'+panelId+'" class="mdsm-verify-row"><td colspan="'+colspan+'"><div class="mdsm-verify-panel '+panelClass+'">'+inner+'</div></td></tr>');
			$row.after($panel);
		}).fail(function(){
			$btn.html('<?php echo esc_js(__('Verify &#10003;','archiviomd')); ?>').prop('disabled',false);
		});
	});

	// Activity log
	function loadLog(page){
		page=page||1;
		var filter=$('#mdsm-log-filter').val();
		$('#mdsm-anchor-log-container').html('<p style="color:#888;"><?php echo esc_js(__('Loading...','archiviomd')); ?></p>');

		$.post(ajaxUrl,{action:'mdsm_anchor_get_log',nonce:nonce,page:page,filter:filter,log_scope:'rekor'},function(r){
			if(!r.success){ $('#mdsm-anchor-log-container').html('<p style="color:#d63638;"><?php echo esc_js(__('Failed to load log.','archiviomd')); ?></p>'); return; }

			var entries=r.data.entries, pages=r.data.pages;
			if(!entries||!entries.length){ $('#mdsm-anchor-log-container').html('<p style="color:#888;"><?php echo esc_js(__('No log entries found.','archiviomd')); ?></p>'); return; }

			var colors={anchored:'#00a32a',retry:'#dba617',failed:'#d63638'};
			var html='<table class="widefat striped"><thead><tr>'
				+'<th><?php echo esc_js(__('Time (UTC)','archiviomd')); ?></th>'
				+'<th><?php echo esc_js(__('Status','archiviomd')); ?></th>'
				+'<th><?php echo esc_js(__('Document','archiviomd')); ?></th>'
				+'<th><?php echo esc_js(__('Hash','archiviomd')); ?></th>'
				+'<th><?php echo esc_js(__('Rekor Entry','archiviomd')); ?></th>'
				+'<th><?php echo esc_js(__('Details','archiviomd')); ?></th>'
				+'</tr></thead><tbody>';

			entries.forEach(function(e){
				var idx=logIndex(e.anchor_url);
				var hash=e.hash_value?e.hash_value.substr(0,12)+'&hellip;':'--';

				// Rekor entry cell
				var rekorCell='--';
				if(e.anchor_url && e.status==='anchored'){
					rekorCell='<a href="'+esc(e.anchor_url)+'" target="_blank" rel="noopener noreferrer"><?php echo esc_js(__('View on Sigstore &#8599;','archiviomd')); ?></a>';
					if(idx>0){
						rekorCell+=' <button type="button" class="button mdsm-verify-btn"'
							+' data-log-index="'+idx+'"'
							+' data-local-hash="'+esc(e.hash_value||'')+'">'
							+'<?php echo esc_js(__('Verify &#10003;','archiviomd')); ?>'
							+'</button>';
					}
				} else if(e.anchor_url){
					rekorCell='<a href="'+esc(e.anchor_url)+'" target="_blank" rel="noopener noreferrer"><?php echo esc_js(__('View on Sigstore &#8599;','archiviomd')); ?></a>';
				}

				html+='<tr>'
					+'<td style="white-space:nowrap;">'+esc(e.created_at)+'</td>'
					+'<td><strong style="color:'+(colors[e.status]||'#555')+';">'+esc(e.status.toUpperCase())+'</strong></td>'
					+'<td>'+esc(e.document_id)+'</td>'
					+'<td><code title="'+esc(e.hash_value||'')+'">'+hash+'</code></td>'
					+'<td style="white-space:nowrap;">'+rekorCell+'</td>'
					+'<td style="max-width:260px;font-size:12px;word-break:break-all;">'+esc(e.error_message||'')+'</td>'
					+'</tr>';
			});

			html+='</tbody></table>';

			if(pages>1){
				html+='<div style="margin-top:10px;">';
				for(var p=1;p<=pages;p++){
					html+='<button type="button" class="button button-small mdsm-log-page" data-page="'+p+'" style="margin-right:4px;'+(p===page?'font-weight:700;':'')+'">'+ p +'</button>';
				}
				html+='</div>';
			}

			$('#mdsm-anchor-log-container').html(html);
			$('.mdsm-log-page').on('click',function(){ loadLog($(this).data('page')); });
		});
	}

	$('#mdsm-log-filter').on('change',function(){ loadLog(1); });
	$('#mdsm-log-refresh-btn').on('click',function(){ loadLog(1); });
	$(document).ready(function(){ loadLog(1); });
}(jQuery));
</script>
