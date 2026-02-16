<?php
/**
 * Archivio Post - Content Hash Verification System
 *
 * @package ArchivioMD
 * @since   1.2.0
 * @updated 1.4.0 – HMAC Integrity Mode (hash_hmac via wp-config.php constant)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MDSM_Archivio_Post
 *
 * Handles deterministic hash generation and verification for WordPress posts.
 *
 * Storage format for hashes:
 *   Standard:  "sha256:hex"        (or legacy bare hex)
 *   HMAC:      "hmac-sha256:hex"
 *
 * The mode tag in the packed string drives every downstream decision
 * (verification, audit log, CSV export, download file) — global settings
 * are never used for verification of existing hashes.
 */
class MDSM_Archivio_Post {

	private static $instance    = null;
	private $audit_table;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		global $wpdb;
		$this->audit_table = $wpdb->prefix . 'archivio_post_audit';
		
		// Ensure table exists and has correct structure
		$this->ensure_table_structure();
		
		$this->init_hooks();
	}
	
	/**
	 * Ensure audit table exists and has correct structure
	 */
	private function ensure_table_structure() {
		global $wpdb;
		
		try {
			$table_name = $wpdb->prefix . 'archivio_post_audit';
			
			// Check if table exists
			if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) !== $table_name ) {
				// Table doesn't exist, create it
				self::create_audit_table();
				return;
			}
			
			// Table exists, check if post_type column exists
			$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table_name}" );
			if ( ! in_array( 'post_type', $columns, true ) ) {
				// Migration needed for v1.5.9+
				$wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN post_type varchar(20) NOT NULL DEFAULT 'post' AFTER post_id, ADD KEY post_type (post_type)" );
			}
		} catch ( Exception $e ) {
			// Silently fail - table will be checked again on next request
		}
	}

	private function init_hooks() {
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 20 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
			add_action( 'admin_notices',         array( $this, 'admin_hmac_notices' ) );
		}

		add_action( 'save_post',      array( $this, 'maybe_generate_hash' ), 10, 3 );
		add_action( 'add_meta_boxes', array( $this, 'add_badge_meta_box' ) );
		add_action( 'save_post',      array( $this, 'save_badge_meta_box' ), 10, 2 );

		add_filter( 'the_content', array( $this, 'maybe_display_badge' ), 20 );
		add_filter( 'the_title',   array( $this, 'maybe_display_title_badge' ), 10, 2 );

		add_shortcode( 'hash_verify', array( $this, 'shortcode_verify_badge' ) );

		add_action( 'wp_ajax_archivio_post_download_verification',        array( $this, 'ajax_download_verification' ) );
		add_action( 'wp_ajax_nopriv_archivio_post_download_verification',  array( $this, 'ajax_download_verification' ) );
		add_action( 'wp_ajax_archivio_post_get_audit_logs',               array( $this, 'ajax_get_audit_logs' ) );
		add_action( 'wp_ajax_archivio_post_save_settings',                array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_archivio_post_fix_settings',                 array( $this, 'ajax_fix_settings' ) );
		add_action( 'wp_ajax_archivio_post_export_audit_csv',             array( $this, 'ajax_export_audit_csv' ) );
		add_action( 'wp_ajax_archivio_post_recreate_table',               array( $this, 'ajax_recreate_table' ) );
		add_action( 'wp_ajax_archivio_post_save_algorithm',               array( $this, 'ajax_save_algorithm' ) );
		add_action( 'wp_ajax_archivio_post_save_hmac_settings',           array( $this, 'ajax_save_hmac_settings' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	public function add_admin_menu() {
		add_submenu_page(
			'archiviomd',
			__( 'Cryptographic Verification', 'archivio-md-build' ),
			__( 'Cryptographic Verification', 'archivio-md-build' ),
			'manage_options',
			'archivio-post',
			array( $this, 'render_admin_page' )
		);
	}

	public function enqueue_admin_assets( $hook ) {
		// Only load on our plugin pages - check if we're on an archiviomd page
		if ( strpos( $hook, 'archivio' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'archivio-post-admin',
			MDSM_PLUGIN_URL . 'assets/css/archivio-post-admin.css',
			array(),
			MDSM_VERSION
		);

		wp_enqueue_script(
			'archivio-post-admin',
			MDSM_PLUGIN_URL . 'assets/js/archivio-post-admin.js',
			array( 'jquery' ),
			MDSM_VERSION,
			true
		);

		wp_localize_script( 'archivio-post-admin', 'archivioPostData', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'archivio_post_nonce' ),
			'strings' => array(
				'saving'  => __( 'Saving...', 'archivio-md-build' ),
				'saved'   => __( 'Settings saved successfully!', 'archivio-md-build' ),
				'error'   => __( 'Error occurred. Please try again.', 'archivio-md-build' ),
				'loading' => __( 'Loading...', 'archivio-md-build' ),
			),
		) );
	}

	public function enqueue_frontend_assets() {
		if ( ! is_singular() ) {
			return;
		}

		wp_enqueue_style(
			'archivio-post-frontend',
			MDSM_PLUGIN_URL . 'assets/css/archivio-post-frontend.css',
			array(),
			MDSM_VERSION
		);

		wp_enqueue_script(
			'archivio-post-frontend',
			MDSM_PLUGIN_URL . 'assets/js/archivio-post-frontend.js',
			array( 'jquery' ),
			MDSM_VERSION,
			true
		);

		wp_localize_script( 'archivio-post-frontend', 'archivioPostFrontend', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'archivio_post_frontend_nonce' ),
			'strings' => array(
				'downloading' => __( 'Downloading...', 'archivio-md-build' ),
				'error'       => __( 'Error downloading verification file.', 'archivio-md-build' ),
			),
		) );
	}

	public function admin_hmac_notices() {
		if ( ! MDSM_Hash_Helper::is_hmac_mode_enabled() ) {
			// Check for algorithm fallback notices even when HMAC is disabled
			$this->display_algorithm_fallback_notice();
			return;
		}

		$status = MDSM_Hash_Helper::hmac_status();

		if ( $status['notice_level'] === 'ok' ) {
			$this->display_algorithm_fallback_notice();
			return;
		}

		$class = ( $status['notice_level'] === 'error' ) ? 'notice-error' : 'notice-warning';

		printf(
			'<div class="notice %s"><p><strong>ArchivioMD HMAC:</strong> %s</p></div>',
			esc_attr( $class ),
			wp_kses( $status['notice_message'], array( 'code' => array() ) )
		);

		$this->display_algorithm_fallback_notice();
	}

	private function display_algorithm_fallback_notice() {
		$user_id = get_current_user_id();
		$fallback_data = get_transient( 'archivio_post_fallback_notice_' . $user_id );

		if ( ! $fallback_data ) {
			return;
		}

		$requested_label = MDSM_Hash_Helper::algorithm_label( $fallback_data['requested'] );
		$fallback_label  = MDSM_Hash_Helper::algorithm_label( $fallback_data['fallback'] );

		printf(
			'<div class="notice notice-warning is-dismissible"><p><strong>%s</strong> %s</p></div>',
			esc_html__( 'Algorithm Fallback:', 'archivio-md-build' ),
			sprintf(
				/* translators: 1: requested algorithm name, 2: fallback algorithm name, 3: post ID */
				esc_html__( 'The requested algorithm %1$s is not available on this server. Hash for post #%3$d was generated using fallback algorithm %2$s instead.', 'archivio-md-build' ),
				'<code>' . esc_html( $requested_label ) . '</code>',
				'<code>' . esc_html( $fallback_label ) . '</code>',
				esc_html( $fallback_data['post_id'] )
			)
		);

		delete_transient( 'archivio_post_fallback_notice_' . $user_id );
	}

	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'archivio-md-build' ) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'archivio_post_audit';
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) !== $table_name ) {
			self::create_audit_table();
		}

		require_once MDSM_PLUGIN_DIR . 'admin/archivio-post-page.php';
	}

	private function canonicalize_content( $content, $post_id, $author_id ) {
		$content = str_replace( "\r\n", "\n", $content );
		$content = str_replace( "\r",   "\n", $content );

		$lines   = explode( "\n", $content );
		$lines   = array_map( 'trim', $lines );
		$content = trim( implode( "\n", $lines ) );

		$canonical  = "post_id:{$post_id}\n";
		$canonical .= "author_id:{$author_id}\n";
		$canonical .= "content:\n{$content}";

		return $canonical;
	}

	public function generate_hash( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post || $post->post_status !== 'publish' ) {
			return false;
		}

		$canonical = $this->canonicalize_content(
			$post->post_content,
			$post_id,
			$post->post_author
		);

		$result = MDSM_Hash_Helper::compute_hash( $canonical );

		if ( false === $result ) {
			return false;
		}

		return array(
			'packed'           => $result['packed'],
			'mode'             => $result['mode'],
			'hmac_unavailable' => $result['hmac_unavailable'],
		);
	}

	public function maybe_generate_hash( $post_id, $post, $update ) {
		// Ensure we have a valid post object
		if ( ! is_object( $post ) || ! isset( $post->post_status ) ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				return;
			}
		}
		
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( $post->post_status !== 'publish' ) {
			return;
		}

		$auto_generate = get_option( 'archivio_post_auto_generate', false );
		
		// Handle both boolean and string values (WordPress sometimes stores as '1'/'0' or ''/1)
		$auto_generate = filter_var( $auto_generate, FILTER_VALIDATE_BOOLEAN );
		
		if ( ! $auto_generate ) {
			return;
		}

		$existing = get_post_meta( $post_id, '_archivio_post_hash', true );
		if ( ! empty( $existing ) && ! $update ) {
			return;
		}

		$result = $this->generate_hash( $post_id );

		if ( false === $result ) {
			$this->log_event(
				$post_id,
				$post->post_author,
				'',
				'sha256',
				'standard',
				'auto_generate',
				'failed'
			);
			return;
		}

		update_post_meta( $post_id, '_archivio_post_hash', $result['packed'] );

		$unpacked = MDSM_Hash_Helper::unpack( $result['packed'] );

		// Check if fallback occurred and log it
		$result_type = 'success';
		if ( $unpacked['algorithm'] !== MDSM_Hash_Helper::get_active_algorithm() ) {
			$result_type = 'fallback';
			$requested_algo = MDSM_Hash_Helper::get_active_algorithm();
			$fallback_algo  = $unpacked['algorithm'];
			set_transient(
				'archivio_post_fallback_notice_' . get_current_user_id(),
				array(
					'requested' => $requested_algo,
					'fallback'  => $fallback_algo,
					'post_id'   => $post_id,
				),
				300
			);
		}

		$this->log_event(
			$post_id,
			$post->post_author,
			$result['packed'],
			$unpacked['algorithm'],
			$unpacked['mode'],
			'auto_generate',
			$result_type
		);
	}

	public function verify_hash( $post_id ) {
		$stored_hash = get_post_meta( $post_id, '_archivio_post_hash', true );

		if ( empty( $stored_hash ) ) {
			return array(
				'verified'          => false,
				'current_hash'      => false,
				'stored_hash'       => false,
				'mode'              => '',
				'algorithm'         => '',
				'hmac_unavailable'  => false,
				'hmac_key_missing'  => false,
			);
		}

		$unpacked = MDSM_Hash_Helper::unpack( $stored_hash );

		$post = get_post( $post_id );
		if ( ! $post ) {
			return array(
				'verified'          => false,
				'current_hash'      => false,
				'stored_hash'       => $stored_hash,
				'mode'              => $unpacked['mode'],
				'algorithm'         => $unpacked['algorithm'],
				'hmac_unavailable'  => false,
				'hmac_key_missing'  => false,
			);
		}

		$canonical = $this->canonicalize_content(
			$post->post_content,
			$post_id,
			$post->post_author
		);

		$current = MDSM_Hash_Helper::compute_hash_for_verification(
			$canonical,
			$unpacked['algorithm'],
			$unpacked['mode']
		);

		if ( false === $current ) {
			return array(
				'verified'          => false,
				'current_hash'      => false,
				'stored_hash'       => $stored_hash,
				'mode'              => $unpacked['mode'],
				'algorithm'         => $unpacked['algorithm'],
				'hmac_unavailable'  => $current === false && $unpacked['mode'] === 'hmac',
				'hmac_key_missing'  => ! MDSM_Hash_Helper::is_hmac_key_defined(),
			);
		}

		$current_packed = MDSM_Hash_Helper::pack( $current['hash'], $unpacked['algorithm'], $unpacked['mode'] );

		return array(
			'verified'          => hash_equals( $stored_hash, $current_packed ),
			'current_hash'      => $current_packed,
			'stored_hash'       => $stored_hash,
			'mode'              => $unpacked['mode'],
			'algorithm'         => $unpacked['algorithm'],
			'hmac_unavailable'  => false,
			'hmac_key_missing'  => ( $unpacked['mode'] === 'hmac' && ! MDSM_Hash_Helper::is_hmac_key_defined() ),
		);
	}

	public function add_badge_meta_box() {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		add_meta_box(
			'archivio_post_badge',
			__( 'ArchivioMD Badge Settings', 'archivio-md-build' ),
			array( $this, 'render_badge_meta_box' ),
			$post_types,
			'side',
			'low'
		);
	}

	public function render_badge_meta_box( $post ) {
		$show_badge        = get_post_meta( $post->ID, '_archivio_post_show_badge',        true );
		$show_title_badge  = get_post_meta( $post->ID, '_archivio_post_show_title_badge',  true );
		$badge_override    = get_post_meta( $post->ID, '_archivio_post_badge_override',    true );

		wp_nonce_field( 'archivio_post_badge_meta_box', 'archivio_post_badge_meta_box_nonce' );
		?>
		<p>
			<label>
				<input type="checkbox" name="archivio_post_show_badge" value="1" <?php checked( $show_badge, '1' ); ?> />
				<?php esc_html_e( 'Also show badge below content', 'archivio-md-build' ); ?>
			</label>
		</p>
		<p>
			<label>
				<input type="checkbox" name="archivio_post_show_title_badge" value="0" <?php checked( $show_title_badge, '0' ); ?> />
				<?php esc_html_e( 'Hide badge from title', 'archivio-md-build' ); ?>
			</label>
		</p>
		<p>
			<label for="archivio_post_badge_override">
				<?php esc_html_e( 'Custom badge text (optional):', 'archivio-md-build' ); ?>
			</label>
			<input type="text" id="archivio_post_badge_override" name="archivio_post_badge_override" value="<?php echo esc_attr( $badge_override ); ?>" style="width:100%;" />
		</p>
		<?php
	}

	public function save_badge_meta_box( $post_id, $post ) {
		if ( ! isset( $_POST['archivio_post_badge_meta_box_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['archivio_post_badge_meta_box_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'archivio_post_badge_meta_box' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$show_badge       = isset( $_POST['archivio_post_show_badge'] )       ? '1' : '';
		$show_title_badge = isset( $_POST['archivio_post_show_title_badge'] ) ? '1' : '';
		$badge_override   = isset( $_POST['archivio_post_badge_override'] )   ? sanitize_text_field( wp_unslash( $_POST['archivio_post_badge_override'] ) ) : '';

		update_post_meta( $post_id, '_archivio_post_show_badge',        $show_badge );
		update_post_meta( $post_id, '_archivio_post_show_title_badge',  $show_title_badge );
		update_post_meta( $post_id, '_archivio_post_badge_override',    $badge_override );
	}

	public function maybe_display_badge( $content ) {
		if ( ! is_singular() ) {
			return $content;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return $content;
		}

		// Badge below content is opt-in only (meta = '1').
		// The title badge is the default display location.
		$show_badge_meta = get_post_meta( $post_id, '_archivio_post_show_badge', true );
		if ( $show_badge_meta !== '1' ) {
			return $content;
		}

		$badge = $this->generate_badge_html( $post_id, 'content' );
		if ( $badge ) {
			$content .= $badge;
		}

		return $content;
	}

	public function maybe_display_title_badge( $title, $post_id = null ) {
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $title;
		}

		if ( ! $post_id ) {
			return $title;
		}

		// Default: show title badge whenever a hash exists.
		// Per-post meta '_archivio_post_show_title_badge' can be set to '0' to suppress it.
		$suppress = get_post_meta( $post_id, '_archivio_post_show_title_badge', true );
		if ( $suppress === '0' ) {
			return $title;
		}

		$badge = $this->generate_badge_html( $post_id, 'title' );
		if ( $badge ) {
			$title .= ' ' . $badge;
		}

		return $title;
	}

	public function shortcode_verify_badge( $atts ) {
		$atts = shortcode_atts( array(
			'post_id' => get_the_ID(),
		), $atts, 'hash_verify' );

		$post_id = intval( $atts['post_id'] );

		if ( ! $post_id ) {
			return '';
		}

		return $this->generate_badge_html( $post_id, 'shortcode' );
	}

	private function generate_badge_html( $post_id, $context = 'content' ) {
		$stored_hash    = get_post_meta( $post_id, '_archivio_post_hash', true );
		$badge_override = get_post_meta( $post_id, '_archivio_post_badge_override', true );

		// SVG: download arrow
		$dl_icon = '<svg class="apb-dl-svg" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M8 2v8M5 7l3 3 3-3M3 13h10" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>';

		// No hash stored — show "Not Signed" pill
		if ( empty( $stored_hash ) ) {
			$label = ! empty( $badge_override ) ? esc_html( $badge_override ) : esc_html__( 'Not Signed', 'archivio-md-build' );
			return '<span class="archivio-post-badge archivio-post-badge-' . esc_attr( $context ) . ' not-signed">'
				. '<span class="apb-icon" aria-hidden="true">&#8212;</span>'
				. '<span class="apb-text">' . $label . '</span>'
				. '</span>';
		}

		$verification = $this->verify_hash( $post_id );
		$verified     = $verification['verified'];

		if ( $verified ) {
			$status_class = 'verified';
			$icon         = '<svg class="apb-svg" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><polyline points="2.5,8.5 6,12 13.5,4" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
			$label        = ! empty( $badge_override ) ? esc_html( $badge_override ) : esc_html__( 'Verified', 'archivio-md-build' );
		} else {
			$status_class = 'unverified';
			$icon         = '<svg class="apb-svg" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><line x1="3" y1="3" x2="13" y2="13" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/><line x1="13" y1="3" x2="3" y2="13" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>';
			$label        = ! empty( $badge_override ) ? esc_html( $badge_override ) : esc_html__( 'Unverified', 'archivio-md-build' );
		}

		$html  = '<span class="archivio-post-badge archivio-post-badge-' . esc_attr( $context ) . ' ' . esc_attr( $status_class ) . '" data-post-id="' . esc_attr( $post_id ) . '">';
		$html .= '<span class="apb-icon">' . $icon . '</span>';
		$html .= '<span class="apb-text">' . $label . '</span>';
		$html .= '<span class="apb-divider" aria-hidden="true"></span>';
		$html .= '<button class="apb-download archivio-post-download" data-post-id="' . esc_attr( $post_id ) . '" title="' . esc_attr__( 'Download Verification File', 'archivio-md-build' ) . '" aria-label="' . esc_attr__( 'Download Verification File', 'archivio-md-build' ) . '">' . $dl_icon . '</button>';
		$html .= '</span>';

		return $html;
	}

	private function log_event( $post_id, $author_id, $hash, $algorithm, $mode, $event_type, $result ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'archivio_post_audit';
		
		// Sanity check - table should exist (created/verified in __construct)
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) !== $table_name ) {
			return;
		}

		$post_type = get_post_type( $post_id );
		if ( ! $post_type ) {
			$post_type = 'post';
		}

		$wpdb->insert(
			$table_name,
			array(
				'post_id'    => $post_id,
				'post_type'  => $post_type,
				'author_id'  => $author_id,
				'hash'       => $hash,
				'algorithm'  => $algorithm,
				'mode'       => $mode,
				'event_type' => $event_type,
				'result'     => $result,
				'timestamp'  => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	public function ajax_download_verification() {
		check_ajax_referer( 'archivio_post_frontend_nonce', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid post ID', 'archivio-md-build' ) ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Post not found', 'archivio-md-build' ) ) );
		}

		$stored_hash = get_post_meta( $post_id, '_archivio_post_hash', true );
		if ( empty( $stored_hash ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No hash found for this post', 'archivio-md-build' ) ) );
		}

		$verification = $this->verify_hash( $post_id );
		$unpacked     = MDSM_Hash_Helper::unpack( $stored_hash );

		$canonical = $this->canonicalize_content(
			$post->post_content,
			$post_id,
			$post->post_author
		);

		$file_content  = "ArchivioMD Content Verification\n";
		$file_content .= "================================\n\n";
		$file_content .= "Post ID:       {$post_id}\n";
		$file_content .= "Post Title:    {$post->post_title}\n";
		$file_content .= "Author ID:     {$post->post_author}\n";
		$file_content .= "Verification:  " . ( $verification['verified'] ? 'PASSED' : 'FAILED' ) . "\n\n";

		$file_content .= "Hash Details:\n";
		$file_content .= "-------------\n";
		$file_content .= "Mode:       " . MDSM_Hash_Helper::mode_label( $unpacked['mode'] ) . "\n";
		$file_content .= "Algorithm:  " . MDSM_Hash_Helper::algorithm_label( $unpacked['algorithm'] ) . "\n";
		$file_content .= "Hash:       {$unpacked['hash']}\n\n";

		$file_content .= "Canonical Content:\n";
		$file_content .= "------------------\n";
		$file_content .= $canonical . "\n\n";

		$file_content .= "Verification Instructions:\n";
		$file_content .= "--------------------------\n";

		$algo_key   = $unpacked['algorithm'];
		$algo_label = MDSM_Hash_Helper::algorithm_label( $algo_key );

		$std_cmd = "echo -n \"<canonical_content>\" | openssl dgst -{$algo_key}";

		if ( $unpacked['mode'] === 'hmac' ) {
			$file_content .= "This hash was produced using HMAC-{$algo_label}.\n";
			$file_content .= "Offline verification requires the ARCHIVIOMD_HMAC_KEY secret.\n";
			$file_content .= "Example (replace KEY with your secret):\n";
			$file_content .= "echo -n \"<canonical_content>\" | openssl dgst -{$algo_key} -hmac \"KEY\"\n";
		} else {
			$file_content .= "To verify offline, compute the {$algo_label} hash of the\n";
			$file_content .= "canonical content above. It must match the hash shown.\n\n";
			$file_content .= "Example:\n";
			$file_content .= $std_cmd . "\n";
		}

		wp_send_json_success( array(
			'content'  => $file_content,
			'filename' => 'post-' . $post_id . '-verification.txt',
		) );
	}

	public function ajax_get_audit_logs() {
		check_ajax_referer( 'archivio_post_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'archivio-md-build' ) ) );
		}

		global $wpdb;

		$page     = isset( $_POST['page'] ) ? max( 1, intval( $_POST['page'] ) ) : 1;
		$per_page = 20;
		$offset   = ( $page - 1 ) * $per_page;

		$total = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->audit_table}" );

		$logs = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$this->audit_table} ORDER BY timestamp DESC LIMIT %d OFFSET %d",
			$per_page,
			$offset
		) );

		wp_send_json_success( array(
			'logs'        => $logs,
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total / $per_page ),
		) );
	}

	public function ajax_save_settings() {
		check_ajax_referer( 'archivio_post_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'archivio-md-build' ) ) );
		}
		
		$auto_generate = isset( $_POST['auto_generate'] ) && $_POST['auto_generate'] === 'true';
		$show_badge = isset( $_POST['show_badge'] ) && $_POST['show_badge'] === 'true';
		$show_badge_posts = isset( $_POST['show_badge_posts'] ) && $_POST['show_badge_posts'] === 'true';
		$show_badge_pages = isset( $_POST['show_badge_pages'] ) && $_POST['show_badge_pages'] === 'true';

		update_option( 'archivio_post_auto_generate',    $auto_generate );
		update_option( 'archivio_post_show_badge',       $show_badge );
		update_option( 'archivio_post_show_badge_posts', $show_badge_posts );
		update_option( 'archivio_post_show_badge_pages', $show_badge_pages );

		wp_send_json_success( array(
			'message' => esc_html__( 'Settings saved successfully!', 'archivio-md-build' ),
		) );
	}

	public function ajax_fix_settings() {
		check_ajax_referer( 'archivio_post_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'archivio-md-build' ) ) );
		}

		// Force all settings to true (enabled) when user clicks fix button
		update_option( 'archivio_post_auto_generate',    true );
		update_option( 'archivio_post_show_badge',       true );
		update_option( 'archivio_post_show_badge_posts', true );
		update_option( 'archivio_post_show_badge_pages', true );

		wp_send_json_success( array(
			'message' => esc_html__( 'Settings enabled! Auto-Generate is now active.', 'archivio-md-build' ),
		) );
	}

	public function ajax_save_algorithm() {
		check_ajax_referer( 'archivio_post_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'archivio-md-build' ) ) );
		}

		$algorithm = isset( $_POST['algorithm'] ) ? sanitize_key( $_POST['algorithm'] ) : '';

		if ( ! MDSM_Hash_Helper::set_active_algorithm( $algorithm ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid algorithm selected.', 'archivio-md-build' ) ) );
		}

		$warning = '';
		$is_experimental = MDSM_Hash_Helper::is_experimental( $algorithm );

		// Check availability and provide fallback warnings
		$available = MDSM_Hash_Helper::get_algorithm_availability( $algorithm );

		if ( ! $available ) {
			if ( $algorithm === 'blake3' ) {
				$warning = esc_html__( 'BLAKE3 is not natively available on this PHP build. Hashes will fall back to BLAKE2b or SHA-256.', 'archivio-md-build' );
			} elseif ( $algorithm === 'shake128' || $algorithm === 'shake256' ) {
				$warning = esc_html__( 'SHAKE algorithm is not available on this PHP build. Hashes will fall back to BLAKE2b or SHA-256.', 'archivio-md-build' );
			} elseif ( $algorithm === 'blake2b' ) {
				$warning = esc_html__( 'BLAKE2b is not available on this PHP build. New hashes will fall back to SHA-256 until the server is updated.', 'archivio-md-build' );
			}
		}

		if ( $is_experimental && empty( $warning ) ) {
			$warning = esc_html__( 'You have selected an experimental algorithm. It is natively available on this server, but may be slower than standard algorithms.', 'archivio-md-build' );
		}

		$active_label = MDSM_Hash_Helper::algorithm_label( MDSM_Hash_Helper::get_active_algorithm() );

		wp_send_json_success( array(
			/* translators: %s: algorithm name */
			'message' => sprintf( esc_html__( 'Algorithm saved. New hashes will use %s.', 'archivio-md-build' ), $active_label ),
			'warning' => $warning,
		) );
	}

	public function ajax_save_hmac_settings() {
		check_ajax_referer( 'archivio_post_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'archivio-md-build' ) ) );
		}

		$enable_hmac = isset( $_POST['hmac_mode'] ) && $_POST['hmac_mode'] === 'true';

		if ( $enable_hmac && ! MDSM_Hash_Helper::is_hmac_key_defined() ) {
			wp_send_json_error( array(
				/* translators: %s: constant name */
				'message' => sprintf(
					esc_html__( 'Cannot enable HMAC Integrity Mode: the %s constant is not defined in wp-config.php.', 'archivio-md-build' ),
					'<code>' . esc_html( MDSM_Hash_Helper::HMAC_KEY_CONSTANT ) . '</code>'
				),
			) );
		}

		MDSM_Hash_Helper::set_hmac_mode( $enable_hmac );

		$status = MDSM_Hash_Helper::hmac_status();

		wp_send_json_success( array(
			'message'        => $enable_hmac
				? esc_html__( 'HMAC Integrity Mode enabled. All new hashes will be HMAC-signed.', 'archivio-md-build' )
				: esc_html__( 'HMAC Integrity Mode disabled. New hashes will use standard mode.', 'archivio-md-build' ),
			'notice_level'   => $status['notice_level'],
			'notice_message' => wp_strip_all_tags( $status['notice_message'] ),
			'key_defined'    => $status['key_defined'],
			'key_strong'     => $status['key_strong'],
		) );
	}

	public function ajax_export_audit_csv() {
		check_ajax_referer( 'archivio_post_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied', 'archivio-md-build' ) );
		}

		global $wpdb;

		$logs = $wpdb->get_results(
			"SELECT * FROM {$this->audit_table} ORDER BY timestamp DESC",
			ARRAY_A
		);

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=archivio-post-audit-log-' . gmdate( 'Y-m-d-H-i-s' ) . '.csv' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

		fputcsv( $output, array( 'ID', 'Post ID', 'Post Type', 'Author ID', 'Algorithm', 'Mode', 'Hash', 'Event Type', 'Result', 'Timestamp' ) );

		if ( ! empty( $logs ) ) {
			foreach ( $logs as $log ) {
				$unpacked = MDSM_Hash_Helper::unpack( $log['hash'] );

				$algo = ! empty( $log['algorithm'] ) ? $log['algorithm'] : $unpacked['algorithm'];
				$mode = ! empty( $log['mode'] )      ? $log['mode']      : $unpacked['mode'];

				fputcsv( $output, array(
					$log['id'],
					$log['post_id'],
					! empty( $log['post_type'] ) ? $log['post_type'] : 'post',
					$log['author_id'],
					MDSM_Hash_Helper::algorithm_label( $algo ),
					MDSM_Hash_Helper::mode_label( $mode ),
					$log['hash'],
					$log['event_type'],
					$log['result'],
					$log['timestamp'],
				) );
			}
		}

		fclose( $output );
		exit;
	}

	public function ajax_recreate_table() {
		check_ajax_referer( 'archivio_post_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'archivio-md-build' ) ) );
		}

		self::create_audit_table();

		global $wpdb;
		$table_name = $wpdb->prefix . 'archivio_post_audit';

		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) === $table_name ) {
			wp_send_json_success( array( 'message' => esc_html__( 'Audit log table recreated successfully!', 'archivio-md-build' ) ) );
		} else {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to create table. Check database permissions.', 'archivio-md-build' ) ) );
		}
	}

	public static function create_audit_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'archivio_post_audit';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			post_id bigint(20) NOT NULL,
			post_type varchar(20) NOT NULL DEFAULT 'post',
			author_id bigint(20) NOT NULL,
			hash varchar(210) NOT NULL,
			algorithm varchar(20) NOT NULL DEFAULT 'sha256',
			mode varchar(8) NOT NULL DEFAULT 'standard',
			event_type varchar(20) NOT NULL,
			result text NOT NULL,
			timestamp datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			KEY post_type (post_type),
			KEY author_id (author_id),
			KEY timestamp (timestamp)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Backfill post_type column for existing installs that predate v1.5.9
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table_name}" );
		if ( ! in_array( 'post_type', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN post_type varchar(20) NOT NULL DEFAULT 'post' AFTER post_id, ADD KEY post_type (post_type)" );
		}
	}

	public static function drop_audit_table() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'archivio_post_audit';
		$wpdb->query( $wpdb->prepare( "DROP TABLE IF EXISTS %i", $table_name ) );
	}
}
