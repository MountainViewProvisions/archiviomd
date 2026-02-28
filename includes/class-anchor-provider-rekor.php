<?php
/**
 * Sigstore / Rekor Transparency Log Provider
 *
 * Submits a hashedrekord entry to the public Rekor append-only transparency
 * log (https://rekor.sigstore.dev). Every document hash anchored via GitHub
 * or GitLab can simultaneously be registered on Rekor so verifiers can
 * look up the entry without trusting any long-lived key.
 *
 * ── What Rekor / Sigstore is ─────────────────────────────────────────────────
 * Sigstore (Linux Foundation) eliminates long-lived signing keys. The flow is:
 *   1. Obtain a short-lived OIDC identity token (Google, GitHub, etc.)
 *   2. Fulcio (Sigstore's CA) issues an ephemeral certificate binding that
 *      identity to an ephemeral Ed25519 keypair.
 *   3. Sign with the ephemeral key and submit to Rekor.
 *   4. Throw away the private key — verification goes through the cert chain.
 *
 * ── This implementation ───────────────────────────────────────────────────────
 * The full keyless OIDC dance is impractical in a WordPress/PHP context
 * (no natural trigger for an interactive OIDC flow). Instead this provider
 * implements Rekor-only log submission for existing Ed25519 signatures:
 *
 *   POST /api/v1/log/entries  (hashedrekord v0.0.1)
 *
 * The entry contains:
 *   • The SHA-256 hash of the anchor JSON record (the "artifact" being logged)
 *   • The site's Ed25519 public key encoded as a PEM PKIX block
 *   • The Ed25519 signature over that hash, base64-encoded
 *
 * If no Ed25519 key pair is configured the provider generates a per-request
 * ephemeral keypair using PHP's Sodium extension, signs the hash, and submits.
 * Ephemeral keys give you a Rekor entry (immutable transparency proof) even
 * without long-lived site keys — the hash itself is what matters for integrity.
 *
 * Rekor returns a log index (UUID) and a signed tree hash (inclusion proof).
 * The UUID is stored as the anchor_url so it can be looked up later at:
 *   https://search.sigstore.dev/?logIndex=<INDEX>
 *
 * ── Verification ─────────────────────────────────────────────────────────────
 * Anyone can verify a Rekor entry without pre-trusting the signer's key:
 *   rekor-cli get --log-index <INDEX>
 *   rekor-cli verify --artifact-hash sha256:<HASH> --log-index <INDEX>
 *
 * Or via the REST API:
 *   GET https://rekor.sigstore.dev/api/v1/log/entries?logIndex=<INDEX>
 *
 * ── Requirements ─────────────────────────────────────────────────────────────
 *   • PHP 7.2+ with the Sodium extension (ext-sodium) — standard on PHP 7.2+
 *   • OpenSSL extension (ext-openssl) — used to encode the PEM PKIX block
 *   • Outbound HTTPS to rekor.sigstore.dev on port 443
 *
 * @package ArchivioMD
 * @since   1.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MDSM_Anchor_Provider_Rekor
 *
 * Implements MDSM_Anchor_Provider_Interface.
 * Registered as provider key 'rekor' in MDSM_External_Anchoring::make_provider().
 */
class MDSM_Anchor_Provider_Rekor implements MDSM_Anchor_Provider_Interface {

	// ── Constants ─────────────────────────────────────────────────────────────

	/** Public Rekor API endpoint (Sigstore public good instance). */
	const REKOR_API_BASE = 'https://rekor.sigstore.dev/api/v1';

	/** hashedrekord kind + API version string. */
	const REKORD_KIND    = 'hashedrekord';
	const REKORD_VERSION = '0.0.1';

	/** Look-up URL template (human-readable search UI). */
	const SEARCH_URL_TEMPLATE = 'https://search.sigstore.dev/?logIndex=%d';

	// ── Public interface ──────────────────────────────────────────────────────

	/**
	 * Push an anchor record as a hashedrekord entry to the Rekor transparency log.
	 *
	 * The "artifact" being logged is the SHA-256 hash of the JSON-encoded anchor
	 * record (the same record committed to GitHub / GitLab). This means a verifier
	 * can independently re-derive the hash from the committed JSON file and look it
	 * up on Rekor without trusting the plugin.
	 *
	 * @param array $record   Anchor record from the queue.
	 * @param array $settings Plugin anchor settings (may contain rekor_* keys).
	 * @return array Provider result array.
	 */
	public function push( array $record, array $settings ) {
		// ── 1. Preflight checks ────────────────────────────────────────────────
		if ( ! $this->sodium_available() ) {
			return $this->error( 'Rekor: PHP Sodium extension (ext-sodium) is required but not available.', false );
		}
		if ( ! $this->openssl_available() ) {
			return $this->error( 'Rekor: PHP OpenSSL extension (ext-openssl) is required but not available.', false );
		}

		// ── 2. Derive the artifact hash ────────────────────────────────────────
		// We hash the canonical JSON representation of the anchor record.
		// Using the same JSON the Git providers commit ensures the Rekor entry
		// is cryptographically bound to the exact same artifact.
		$artifact_json = wp_json_encode( $record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		if ( false === $artifact_json ) {
			return $this->error( 'Rekor: Failed to JSON-encode anchor record.', false );
		}
		$artifact_hash = hash( 'sha256', $artifact_json );

		// ── 3. Resolve signing keypair ─────────────────────────────────────────
		// Prefer the site's long-lived Ed25519 keys (set in wp-config.php).
		// Fall back to a per-request ephemeral keypair so the push always works
		// regardless of whether the Ed25519 feature is configured.
		$keypair_result = $this->resolve_keypair( $settings );
		if ( is_wp_error( $keypair_result ) ) {
			return $this->error( 'Rekor: ' . $keypair_result->get_error_message(), false );
		}

		list( $private_key_bytes, $public_key_bytes, $is_ephemeral ) = $keypair_result;

		// ── 4. Sign the artifact hash (raw bytes of the hex string) ───────────
		// Rekor's hashedrekord verifies the signature over the *content bytes*,
		// but the API accepts a base64-encoded signature. We sign the raw binary
		// hash bytes (not the hex string) for correctness.
		$hash_bytes = hex2bin( $artifact_hash );
		try {
			$signature_bytes = sodium_crypto_sign_detached( $hash_bytes, $private_key_bytes );
		} catch ( \Exception $e ) {
			return $this->error( 'Rekor: Signing failed — ' . $e->getMessage(), false );
		}

		// ── 5. Encode public key as PEM PKIX ──────────────────────────────────
		$pem_pubkey = $this->ed25519_public_key_to_pem( $public_key_bytes );
		if ( null === $pem_pubkey ) {
			return $this->error( 'Rekor: Failed to encode public key as PEM PKIX.', false );
		}

		// ── 6. Build hashedrekord request body ────────────────────────────────
		$body = $this->build_hashedrekord_body(
			$artifact_hash,
			base64_encode( $signature_bytes ),
			$pem_pubkey,
			$public_key_bytes,
			$record,
			$is_ephemeral
		);

		// ── 7. POST to Rekor ───────────────────────────────────────────────────
		$api_url  = self::REKOR_API_BASE . '/log/entries';
		$response = wp_remote_post( $api_url, array(
			'headers'     => array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
				'User-Agent'   => 'ArchivioMD/' . MDSM_VERSION,
			),
			'body'        => wp_json_encode( $body ),
			'timeout'     => 30,
			'data_format' => 'body',
		) );

		// ── 8. Parse response ──────────────────────────────────────────────────
		return $this->parse_response( $response, $is_ephemeral );
	}

	/**
	 * Test connectivity to Rekor by fetching the transparency log's public info.
	 * No dummy entries are ever written — this is a read-only GET request.
	 *
	 * @param array $settings Plugin anchor settings.
	 * @return array { success: bool, message: string }
	 */
	public function test_connection( array $settings ) {
		if ( ! $this->sodium_available() ) {
			return array(
				'success' => false,
				'message' => __( 'PHP Sodium extension (ext-sodium) is required but not loaded on this server.', 'archiviomd' ),
			);
		}
		if ( ! $this->openssl_available() ) {
			return array(
				'success' => false,
				'message' => __( 'PHP OpenSSL extension (ext-openssl) is required but not loaded on this server.', 'archiviomd' ),
			);
		}

		$url      = self::REKOR_API_BASE . '/log';
		$response = wp_remote_get( $url, array(
			'headers' => array(
				'Accept'     => 'application/json',
				'User-Agent' => 'ArchivioMD/' . MDSM_VERSION,
			),
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'message' => $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code > 299 ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %d: HTTP status code */
					esc_html__( 'Rekor returned HTTP %d. Check outbound HTTPS access to rekor.sigstore.dev.', 'archiviomd' ),
					$code
				),
			);
		}

		$data      = json_decode( wp_remote_retrieve_body( $response ), true );
		$tree_size = isset( $data['treeSize'] ) ? (int) $data['treeSize'] : 0;

		// Determine key mode for the success message.
		$key_info = '';
		$has_site_keys = $this->has_site_ed25519_keys();
		if ( $has_site_keys ) {
			$key_info = __( ' Site Ed25519 keys detected — entries will use your site key.', 'archiviomd' );
		} else {
			$key_info = __( ' No site Ed25519 keys configured — ephemeral keypairs will be used per submission.', 'archiviomd' );
		}

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: %d: Rekor tree size (number of log entries) */
				__( 'Rekor connection successful. Transparency log contains %s entries.', 'archiviomd' ),
				number_format_i18n( $tree_size )
			) . $key_info,
		);
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	/**
	 * Build the hashedrekord v0.0.1 request body.
	 *
	 * Spec: https://github.com/sigstore/rekor/blob/main/pkg/types/hashedrekord/v0.0.1/hashedrekord_v0_0_1_schema.json
	 *
	 * The spec allows arbitrary extra fields alongside 'signature' and 'data'.
	 * We add a 'customProperties' map carrying human-readable provenance so that
	 * anyone looking up this entry on search.sigstore.dev can see which site and
	 * document it originated from, and cross-reference the public key fingerprint
	 * against the site's /.well-known/ed25519-pubkey.txt.
	 *
	 * These fields are NOT cryptographically verified by Rekor -- they are plain
	 * metadata. Their value is auditability and human-readable provenance, not
	 * authentication. A verifier who wants to confirm the site identity should:
	 *   1. Fetch /.well-known/ed25519-pubkey.txt from the claimed site_url.
	 *   2. Compare its SHA-256 fingerprint against pubkey_fingerprint in this entry.
	 *   3. If they match, the entry was submitted by a party controlling that site key.
	 *
	 * @param string $artifact_hash      Lowercase hex SHA-256 of the artifact.
	 * @param string $sig_b64            Base64-encoded Ed25519 signature over the hash bytes.
	 * @param string $pem_pubkey         PEM-encoded PKIX Ed25519 public key (with header/footer).
	 * @param string $public_key_bytes   Raw 32-byte Ed25519 public key (for fingerprint).
	 * @param array  $record             Anchor record (document_id, site_url, etc.)
	 * @param bool   $is_ephemeral       Whether an ephemeral keypair was used.
	 * @return array                     Associative array ready for wp_json_encode().
	 */
	private function build_hashedrekord_body( $artifact_hash, $sig_b64, $pem_pubkey, $public_key_bytes, array $record, $is_ephemeral ) {
		// Build a SHA-256 fingerprint of the raw public key bytes.
		// Auditors can fetch /.well-known/ed25519-pubkey.txt, hex-decode it,
		// and hash it to verify this fingerprint independently.
		$pubkey_fingerprint = $is_ephemeral
			? 'ephemeral'
			: hash( 'sha256', $public_key_bytes );

		// Well-known URL where the long-lived public key is published (if any).
		$site_url     = isset( $record['site_url'] )    ? (string) $record['site_url']    : '';
		$well_known   = $is_ephemeral || empty( $site_url )
			? ''
			: rtrim( $site_url, '/' ) . '/.well-known/ed25519-pubkey.txt';

		$custom_props = array(
			// Human-readable provenance -- not cryptographically verified by Rekor.
			'archiviomd.site_url'          => $site_url,
			'archiviomd.document_id'       => isset( $record['document_id'] )    ? (string) $record['document_id']    : '',
			'archiviomd.post_type'         => isset( $record['post_type'] )      ? (string) $record['post_type']      : '',
			'archiviomd.hash_algorithm'    => isset( $record['hash_algorithm'] ) ? (string) $record['hash_algorithm'] : '',
			'archiviomd.plugin_version'    => isset( $record['plugin_version'] ) ? (string) $record['plugin_version'] : MDSM_VERSION,
			'archiviomd.pubkey_fingerprint'=> $pubkey_fingerprint,
			'archiviomd.key_type'          => $is_ephemeral ? 'ephemeral' : 'site-longterm',
			// If long-lived key: auditors can fetch this URL, hex-decode the
			// contents, SHA-256 hash them, and compare against pubkey_fingerprint.
			'archiviomd.pubkey_url'        => $well_known,
		);

		// Strip empty strings so the entry body stays clean.
		$custom_props = array_filter( $custom_props, function( $v ) { return $v !== ''; } );

		return array(
			'kind'       => self::REKORD_KIND,
			'apiVersion' => self::REKORD_VERSION,
			'spec'       => array(
				'signature' => array(
					'format'  => 'ed25519',
					'content' => $sig_b64,
					'publicKey' => array(
						'content' => base64_encode( $pem_pubkey ),
					),
				),
				'data' => array(
					'hash' => array(
						'algorithm' => 'sha256',
						'value'     => $artifact_hash,
					),
				),
				'customProperties' => $custom_props,
			),
		);
	}

	/**
	 * Parse Rekor's POST /log/entries response.
	 *
	 * A successful 201 response body is a JSON object keyed by UUID:
	 *   { "<uuid>": { "body": "...", "integratedTime": N, "logIndex": N, "logID": "...", "verification": {...} } }
	 *
	 * @param array|\WP_Error $response     wp_remote_post() return value.
	 * @param bool            $is_ephemeral Whether an ephemeral keypair was used.
	 * @return array Provider result array.
	 */
	private function parse_response( $response, $is_ephemeral ) {
		if ( is_wp_error( $response ) ) {
			return array(
				'success'      => false,
				'error'        => 'Rekor: ' . $response->get_error_message(),
				'retry'        => true,
				'rate_limited' => false,
				'http_status'  => 0,
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		// ── Success ────────────────────────────────────────────────────────────
		if ( 201 === $code && is_array( $body ) ) {
			// The response is a single-key map: { "<uuid>": { "logIndex": N, ... } }
			$entry     = reset( $body );
			$uuid      = key( $body );
			$log_index = isset( $entry['logIndex'] ) ? (int) $entry['logIndex'] : null;

			if ( null !== $log_index ) {
				$search_url = sprintf( self::SEARCH_URL_TEMPLATE, $log_index );
			} else {
				// Fallback — shouldn't normally happen, but safe to handle.
				$search_url = 'https://search.sigstore.dev/?uuid=' . rawurlencode( (string) $uuid );
			}

			$ephemeral_note = $is_ephemeral ? ' (ephemeral keypair)' : '';

			return array(
				'success'     => true,
				'url'         => $search_url,
				'http_status' => $code,
				'rekor_uuid'  => (string) $uuid,
				'log_index'   => $log_index,
				'note'        => 'Rekor log entry created' . $ephemeral_note,
			);
		}

		// ── Error handling ─────────────────────────────────────────────────────
		$message = '';
		if ( isset( $body['message'] ) && is_string( $body['message'] ) ) {
			$message = $body['message'];
		} elseif ( isset( $body['code'] ) && isset( $body['message'] ) ) {
			$message = $body['message'];
		} else {
			$message = "HTTP {$code}";
		}

		// 409 = entry already exists in the log (idempotent — treat as success).
		if ( 409 === $code ) {
			// Rekor 409 body contains the existing entry UUID in a detail field.
			$existing_uuid = isset( $body['uuid'] ) ? $body['uuid'] : '';
			$search_url    = $existing_uuid
				? 'https://search.sigstore.dev/?uuid=' . rawurlencode( $existing_uuid )
				: 'https://search.sigstore.dev/';
			return array(
				'success'     => true,
				'url'         => $search_url,
				'http_status' => $code,
				'note'        => 'Rekor: identical entry already exists (409 — treated as success)',
			);
		}

		// 429 = rate limited.
		if ( 429 === $code ) {
			return array(
				'success'      => false,
				'error'        => 'Rekor rate limited: ' . $message,
				'retry'        => true,
				'rate_limited' => true,
				'http_status'  => $code,
			);
		}

		// 400 / 422 = bad request — likely a malformed entry; do not retry.
		if ( in_array( $code, array( 400, 422 ), true ) ) {
			return array(
				'success'      => false,
				'error'        => 'Rekor rejected entry: ' . $message,
				'retry'        => false,
				'rate_limited' => false,
				'http_status'  => $code,
			);
		}

		// 5xx = server error — retry.
		if ( $code >= 500 ) {
			return array(
				'success'      => false,
				'error'        => 'Rekor server error: ' . $message,
				'retry'        => true,
				'rate_limited' => false,
				'http_status'  => $code,
			);
		}

		return array(
			'success'      => false,
			'error'        => "Rekor HTTP {$code}: {$message}",
			'retry'        => true,
			'rate_limited' => false,
			'http_status'  => $code,
		);
	}

	/**
	 * Resolve the Ed25519 keypair to use for signing.
	 *
	 * Priority:
	 *   1. Site long-lived keys from wp-config.php constants
	 *      (ARCHIVIOMD_ED25519_PRIVATE_KEY / ARCHIVIOMD_ED25519_PUBLIC_KEY)
	 *   2. Ephemeral per-request keypair (sodium_crypto_sign_keypair)
	 *
	 * @param array $settings Plugin settings array (unused currently, kept for extensibility).
	 * @return array|\WP_Error  [private_bytes, public_bytes, is_ephemeral] or WP_Error.
	 */
	private function resolve_keypair( array $settings ) {
		if ( $this->has_site_ed25519_keys() ) {
			$priv_hex = constant( 'ARCHIVIOMD_ED25519_PRIVATE_KEY' );
			$pub_hex  = constant( 'ARCHIVIOMD_ED25519_PUBLIC_KEY' );

			if ( strlen( $priv_hex ) !== 128 || ! ctype_xdigit( $priv_hex ) ) {
				return new \WP_Error( 'rekor_bad_privkey', 'ARCHIVIOMD_ED25519_PRIVATE_KEY must be 128 hex characters (64 bytes).' );
			}
			if ( strlen( $pub_hex ) !== 64 || ! ctype_xdigit( $pub_hex ) ) {
				return new \WP_Error( 'rekor_bad_pubkey', 'ARCHIVIOMD_ED25519_PUBLIC_KEY must be 64 hex characters (32 bytes).' );
			}

			return array( hex2bin( $priv_hex ), hex2bin( $pub_hex ), false );
		}

		// Ephemeral fallback.
		try {
			$keypair     = sodium_crypto_sign_keypair();
			$priv_bytes  = sodium_crypto_sign_secretkey( $keypair );
			$pub_bytes   = sodium_crypto_sign_publickey( $keypair );
			return array( $priv_bytes, $pub_bytes, true );
		} catch ( \Exception $e ) {
			return new \WP_Error( 'rekor_keypair_gen', 'Failed to generate ephemeral keypair: ' . $e->getMessage() );
		}
	}

	/**
	 * Encode a 32-byte Ed25519 raw public key as a PEM PKIX block.
	 *
	 * Ed25519 PKIX encoding (RFC 8410):
	 *   SEQUENCE {
	 *     SEQUENCE {
	 *       OID 1.3.101.112  (id-Ed25519)
	 *     }
	 *     BIT STRING <00> <32-byte-pubkey>
	 *   }
	 *
	 * This is built in pure PHP so there is no dependency on a third-party
	 * ASN.1 library. The byte sequences below are fixed-length for Ed25519;
	 * they do not need to be computed dynamically.
	 *
	 * @param string $raw_public_key_bytes 32 raw bytes of the Ed25519 public key.
	 * @return string|null PEM string on success, null on failure.
	 */
	private function ed25519_public_key_to_pem( $raw_public_key_bytes ) {
		if ( strlen( $raw_public_key_bytes ) !== 32 ) {
			return null;
		}

		// Ed25519 OID: 1.3.101.112 → DER: 06 03 2B 65 70
		$oid_der = "\x06\x03\x2B\x65\x70";

		// AlgorithmIdentifier SEQUENCE { OID }
		// SEQUENCE { \x06\x03\x2B\x65\x70 } = 05 00 is absent (Ed25519 has no params)
		$algorithm_identifier = "\x30" . chr( strlen( $oid_der ) ) . $oid_der;

		// BIT STRING: leading 0x00 byte (no unused bits) + raw key bytes
		$bit_string_content = "\x00" . $raw_public_key_bytes;
		$bit_string         = "\x03" . chr( strlen( $bit_string_content ) ) . $bit_string_content;

		// SubjectPublicKeyInfo SEQUENCE { AlgorithmIdentifier, BIT STRING }
		$spki_content = $algorithm_identifier . $bit_string;
		$spki_der     = "\x30" . $this->der_length( strlen( $spki_content ) ) . $spki_content;

		// Encode as PEM.
		$b64  = base64_encode( $spki_der );
		$pem  = "-----BEGIN PUBLIC KEY-----\n";
		$pem .= chunk_split( $b64, 64, "\n" );
		$pem .= "-----END PUBLIC KEY-----\n";

		return $pem;
	}

	/**
	 * Encode a DER length field.
	 * Supports short form (< 128) and long-form two-byte (< 65536).
	 *
	 * @param int $length
	 * @return string
	 */
	private function der_length( $length ) {
		if ( $length < 128 ) {
			return chr( $length );
		}
		if ( $length < 256 ) {
			return "\x81" . chr( $length );
		}
		return "\x82" . chr( $length >> 8 ) . chr( $length & 0xFF );
	}

	/**
	 * Return a standardised failure result array.
	 *
	 * @param string $message   Human-readable error.
	 * @param bool   $retryable Whether the queue should retry this job.
	 * @return array
	 */
	private function error( $message, $retryable = true ) {
		return array(
			'success'      => false,
			'error'        => $message,
			'retry'        => $retryable,
			'rate_limited' => false,
			'http_status'  => 0,
		);
	}

	/**
	 * True if PHP Sodium (libsodium) is available.
	 *
	 * @return bool
	 */
	private function sodium_available() {
		return function_exists( 'sodium_crypto_sign_keypair' );
	}

	/**
	 * True if PHP OpenSSL is available (only used for key encoding validation).
	 * Actually we do the PKIX encoding in pure PHP — OpenSSL is not strictly
	 * required for our path, but we keep the check to ensure the environment is
	 * sane before making external calls.
	 *
	 * @return bool
	 */
	private function openssl_available() {
		return extension_loaded( 'openssl' );
	}

	/**
	 * True if the site has long-lived Ed25519 keys defined in wp-config.php.
	 *
	 * @return bool
	 */
	private function has_site_ed25519_keys() {
		return defined( 'ARCHIVIOMD_ED25519_PRIVATE_KEY' )
			&& defined( 'ARCHIVIOMD_ED25519_PUBLIC_KEY' )
			&& ! empty( constant( 'ARCHIVIOMD_ED25519_PRIVATE_KEY' ) )
			&& ! empty( constant( 'ARCHIVIOMD_ED25519_PUBLIC_KEY' ) );
	}
}
