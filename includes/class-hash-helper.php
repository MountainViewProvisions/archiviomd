<?php
/**
 * Archivio Hash Helper – v1.5.0
 *
 * Centralised, algorithm-agnostic hashing for ALL ArchivioMD hashing calls.
 * 
 * STANDARD ALGORITHMS (Production-ready):
 *   - SHA-256, SHA-512, SHA3-256, SHA3-512, BLAKE2b-512
 * 
 * EXPERIMENTAL ALGORITHMS (May require fallback):
 *   - BLAKE3-256: Parallel hashing, extremely fast (PHP 8.1+ or fallback)
 *   - SHAKE128-256: SHA-3 XOF with 256-bit output (PHP 7.1+ or fallback)
 *   - SHAKE256-512: SHA-3 XOF with 512-bit output (PHP 7.1+ or fallback)
 *
 * All algorithms support both Standard (hash()) and HMAC (hash_hmac()) modes.
 *
 * PACKED STRING FORMAT
 * --------------------
 *   Standard:  "sha256:abcdef…"        (algorithm:hex)
 *   HMAC:      "hmac-sha256:abcdef…"   (hmac-algorithm:hex)
 *   Legacy:    "abcdef…"               (bare hex → treated as sha256/standard)
 *
 * HMAC MODE REQUIREMENTS
 * ----------------------
 *   1. Add to wp-config.php (BEFORE "stop editing" line):
 *        define( 'ARCHIVIOMD_HMAC_KEY', 'your-long-random-secret' );
 *   2. Enable in Tools → Archivio Post → Settings → HMAC Integrity Mode.
 *   The constant is NEVER stored in the database.
 *   KEY ROTATION: changing the constant invalidates all HMAC hashes.
 *
 * BACKWARD COMPATIBILITY CONTRACT
 * --------------------------------
 *   Legacy bare-hex SHA-256 hashes  → verified with hash()
 *   v1.3 "sha256:xxx" hashes        → verified with hash()
 *   v1.4 "hmac-sha256:xxx" hashes   → verified with hash_hmac()
 *   v1.5 experimental algorithms    → verified with native or pure-PHP fallback
 *   All formats coexist without any migration.
 *
 * @package ArchivioMD
 * @since   1.4.0
 * @updated 1.5.0 – Added experimental algorithms with automatic fallback
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MDSM_Hash_Helper {

	const DEFAULT_ALGORITHM  = 'sha256';
	const HMAC_KEY_MIN_LENGTH = 32;
	const HMAC_KEY_CONSTANT   = 'ARCHIVIOMD_HMAC_KEY';
	const MODE_HMAC           = 'hmac';
	const MODE_STANDARD       = 'standard';

	// ── Algorithm registry ─────────────────────────────────────────────

	public static function allowed_algorithms() {
		return array(
			'sha256'       => 'SHA-256',
			'sha224'       => 'SHA-224',
			'sha384'       => 'SHA-384',
			'sha512'       => 'SHA-512',
			'sha512-224'   => 'SHA-512/224',
			'sha512-256'   => 'SHA-512/256',
			'sha3-256'     => 'SHA3-256',
			'sha3-512'     => 'SHA3-512',
			'blake2b'      => 'BLAKE2b-512',
			'blake2s'      => 'BLAKE2s-256',
			'sha256d'      => 'SHA-256d (Bitcoin)',
			'ripemd160'    => 'RIPEMD-160',
			'whirlpool'    => 'Whirlpool-512',
			'blake3'       => 'BLAKE3-256',
			'shake128'     => 'SHAKE128-256',
			'shake256'     => 'SHAKE256-512',
			'gost'         => 'GOST R 34.11-94',
			'gost-crypto'  => 'GOST R 34.11-94 (CryptoPro)',
			'md5'          => 'MD5',
			'sha1'         => 'SHA-1',
		);
	}

	public static function standard_algorithms() {
		return array(
			'sha256'     => 'SHA-256',
			'sha224'     => 'SHA-224',
			'sha384'     => 'SHA-384',
			'sha512'     => 'SHA-512',
			'sha512-224' => 'SHA-512/224',
			'sha512-256' => 'SHA-512/256',
			'sha3-256'   => 'SHA3-256',
			'sha3-512'   => 'SHA3-512',
			'blake2b'    => 'BLAKE2b-512',
			'blake2s'    => 'BLAKE2s-256',
			'sha256d'    => 'SHA-256d (Bitcoin)',
			'ripemd160'  => 'RIPEMD-160',
			'whirlpool'  => 'Whirlpool-512',
		);
	}

	public static function experimental_algorithms() {
		return array(
			'blake3'   => 'BLAKE3-256',
			'shake128' => 'SHAKE128-256',
			'shake256' => 'SHAKE256-512',
		);
	}

	public static function deprecated_algorithms() {
		return array(
			'md5'  => 'MD5',
			'sha1' => 'SHA-1',
		);
	}

	public static function regional_algorithms() {
		return array(
			'gost'        => 'GOST R 34.11-94',
			'gost-crypto' => 'GOST R 34.11-94 (CryptoPro)',
		);
	}

	public static function is_experimental( $algorithm ) {
		return array_key_exists( $algorithm, self::experimental_algorithms() );
	}

	public static function is_deprecated( $algorithm ) {
		return array_key_exists( $algorithm, self::deprecated_algorithms() );
	}

	public static function is_regional( $algorithm ) {
		return array_key_exists( $algorithm, self::regional_algorithms() );
	}

	public static function algorithm_label( $algorithm ) {
		$map = self::allowed_algorithms();
		return isset( $map[ $algorithm ] ) ? $map[ $algorithm ] : strtoupper( $algorithm );
	}

	public static function mode_label( $mode ) {
		return ( $mode === self::MODE_HMAC ) ? 'HMAC' : 'Standard';
	}

	// ── Active algorithm ────────────────────────────────────────────────

	public static function get_active_algorithm() {
		$algo    = get_option( 'archivio_hash_algorithm', self::DEFAULT_ALGORITHM );
		$allowed = array_keys( self::allowed_algorithms() );
		return in_array( $algo, $allowed, true ) ? $algo : self::DEFAULT_ALGORITHM;
	}

	public static function set_active_algorithm( $algorithm ) {
		$algorithm = sanitize_key( $algorithm );
		if ( ! array_key_exists( $algorithm, self::allowed_algorithms() ) ) {
			return false;
		}
		update_option( 'archivio_hash_algorithm', $algorithm );
		return true;
	}

	// ── HMAC mode management ────────────────────────────────────────────

	public static function is_hmac_mode_enabled() {
		return (bool) get_option( 'archivio_hmac_mode', false );
	}

	public static function set_hmac_mode( $enabled ) {
		update_option( 'archivio_hmac_mode', (bool) $enabled );
	}

	public static function is_hmac_key_defined() {
		if ( ! defined( self::HMAC_KEY_CONSTANT ) ) {
			return false;
		}
		$key = constant( self::HMAC_KEY_CONSTANT );
		return ( null !== $key && false !== $key && '' !== $key );
	}

	public static function get_hmac_key() {
		if ( ! defined( self::HMAC_KEY_CONSTANT ) ) {
			return false;
		}
		$key = constant( self::HMAC_KEY_CONSTANT );
		if ( null === $key || false === $key || '' === $key ) {
			return false;
		}
		return (string) $key;
	}

	public static function is_hmac_key_strong() {
		$key = self::get_hmac_key();
		return $key !== false && strlen( $key ) >= self::HMAC_KEY_MIN_LENGTH;
	}

	public static function is_hmac_ready() {
		return self::is_hmac_mode_enabled() && self::is_hmac_key_defined();
	}

	// ── Standard hash ───────────────────────────────────────────────────

	/**
	 * Compute a standard (non-HMAC) hash.
	 *
	 * @param  string      $data
	 * @param  string|null $algorithm
	 * @return array{ hash: string, algorithm: string, mode: string, fallback: bool }
	 */
	public static function compute( $data, $algorithm = null ) {
		if ( null === $algorithm ) {
			$algorithm = self::get_active_algorithm();
		}

		$fallback = false;

		switch ( $algorithm ) {
			case 'sha512':
				$hash = hash( 'sha512', $data );
				break;
			case 'sha3-256':
				if ( in_array( 'sha3-256', hash_algos(), true ) ) {
					$hash = hash( 'sha3-256', $data );
				} else {
					$hash      = hash( 'sha256', $data );
					$algorithm = 'sha256';
					$fallback  = true;
				}
				break;
			case 'sha3-512':
				if ( in_array( 'sha3-512', hash_algos(), true ) ) {
					$hash = hash( 'sha3-512', $data );
				} else {
					$hash      = hash( 'sha256', $data );
					$algorithm = 'sha256';
					$fallback  = true;
				}
				break;
			case 'blake2b':
				if ( in_array( 'blake2b512', hash_algos(), true ) ) {
					$hash = hash( 'blake2b512', $data );
				} elseif ( in_array( 'blake2b', hash_algos(), true ) ) {
					$hash      = hash( 'blake2b', $data );
					$algorithm = 'blake2b';
				} else {
					$hash      = hash( 'sha256', $data );
					$algorithm = 'sha256';
					$fallback  = true;
				}
				break;
			case 'blake3':
				if ( self::is_blake3_available() ) {
					$hash = self::compute_blake3( $data );
				} else {
					$hash      = hash( 'blake2b512', $data );
					$algorithm = 'blake2b';
					$fallback  = true;
					if ( ! in_array( 'blake2b512', hash_algos(), true ) ) {
						$hash      = hash( 'sha256', $data );
						$algorithm = 'sha256';
					}
				}
				break;
			case 'shake128':
				if ( in_array( 'shake128', hash_algos(), true ) ) {
					// PHP's hash() outputs 256 bits (64 hex chars) for shake128 by default
					$hash = hash( 'shake128', $data );
				} else {
					$hash      = hash( 'sha256', $data );
					$algorithm = 'sha256';
					$fallback  = true;
				}
				break;
			case 'shake256':
				if ( in_array( 'shake256', hash_algos(), true ) ) {
					// PHP's hash() outputs 512 bits (128 hex chars) for shake256 by default
					$hash = hash( 'shake256', $data );
				} else {
					$hash      = hash( 'sha256', $data );
					$algorithm = 'sha256';
					$fallback  = true;
				}
				break;
			case 'sha224':
				$hash = hash( 'sha224', $data );
				break;
			case 'sha384':
				$hash = hash( 'sha384', $data );
				break;
			case 'sha512-224':
				$hash = hash( 'sha512/224', $data );
				break;
			case 'sha512-256':
				$hash = hash( 'sha512/256', $data );
				break;
			case 'ripemd160':
				$hash = hash( 'ripemd160', $data );
				break;
			case 'whirlpool':
				$hash = hash( 'whirlpool', $data );
				break;
			case 'sha256d':
				// Double SHA-256: SHA256(SHA256(data)) – Bitcoin-compatible.
				// SHA-256 is always available; no fallback needed.
				$hash = hash( 'sha256', hex2bin( hash( 'sha256', $data ) ) );
				break;
			case 'blake2s':
				if ( in_array( 'blake2s256', hash_algos(), true ) ) {
					$hash = hash( 'blake2s256', $data );
				} else {
					$hash      = hash( 'sha256', $data );
					$algorithm = 'sha256';
					$fallback  = true;
				}
				break;
			case 'gost':
				if ( in_array( 'gost', hash_algos(), true ) ) {
					$hash = hash( 'gost', $data );
				} else {
					$hash      = hash( 'sha256', $data );
					$algorithm = 'sha256';
					$fallback  = true;
				}
				break;
			case 'gost-crypto':
				if ( in_array( 'gost-crypto', hash_algos(), true ) ) {
					$hash = hash( 'gost-crypto', $data );
				} else {
					$hash      = hash( 'sha256', $data );
					$algorithm = 'sha256';
					$fallback  = true;
				}
				break;
			case 'md5':
				$hash = hash( 'md5', $data );
				break;
			case 'sha1':
				$hash = hash( 'sha1', $data );
				break;
			case 'sha256':
			default:
				$hash      = hash( 'sha256', $data );
				$algorithm = 'sha256';
				break;
		}

		return array(
			'hash'      => $hash,
			'algorithm' => $algorithm,
			'mode'      => self::MODE_STANDARD,
			'fallback'  => $fallback,
		);
	}

	// ── HMAC hash ───────────────────────────────────────────────────────

	/**
	 * Compute an HMAC of $data using the wp-config.php key.
	 *
	 * Returns false when the key constant is not defined so callers can
	 * surface the error rather than silently generating a wrong hash.
	 *
	 * @param  string      $data
	 * @param  string|null $algorithm
	 * @return array{ hash: string, algorithm: string, mode: string, fallback: bool }|false
	 */
	public static function compute_hmac( $data, $algorithm = null ) {
		$key = self::get_hmac_key();
		if ( $key === false ) {
			return false;
		}

		if ( null === $algorithm ) {
			$algorithm = self::get_active_algorithm();
		}

		$fallback = false;

		switch ( $algorithm ) {
			case 'sha512':
				$php_algo = 'sha512';
				break;
			case 'sha3-256':
				if ( function_exists( 'hash_hmac_algos' ) && in_array( 'sha3-256', hash_hmac_algos(), true ) ) {
					$php_algo = 'sha3-256';
				} else {
					$php_algo  = 'sha256';
					$algorithm = 'sha256';
					$fallback  = true;
				}
				break;
			case 'sha3-512':
				if ( function_exists( 'hash_hmac_algos' ) && in_array( 'sha3-512', hash_hmac_algos(), true ) ) {
					$php_algo = 'sha3-512';
				} else {
					$php_algo  = 'sha256';
					$algorithm = 'sha256';
					$fallback  = true;
				}
				break;
			case 'blake2b':
				if ( function_exists( 'hash_hmac_algos' ) ) {
					$hmac_algos = hash_hmac_algos();
					if ( in_array( 'blake2b512', $hmac_algos, true ) ) {
						$php_algo = 'blake2b512';
					} elseif ( in_array( 'blake2b', $hmac_algos, true ) ) {
						$php_algo  = 'blake2b';
						$algorithm = 'blake2b';
					} else {
						$php_algo  = 'sha256';
						$algorithm = 'sha256';
						$fallback  = true;
					}
				} else {
					$php_algo  = 'sha256';
					$algorithm = 'sha256';
					$fallback  = true;
				}
				break;
			case 'blake3':
				if ( self::is_blake3_available() ) {
					$hash = self::compute_blake3_hmac( $data, $key );
					return array(
						'hash'      => $hash,
						'algorithm' => 'blake3',
						'mode'      => self::MODE_HMAC,
						'fallback'  => false,
					);
				} else {
					if ( function_exists( 'hash_hmac_algos' ) && in_array( 'blake2b512', hash_hmac_algos(), true ) ) {
						$php_algo  = 'blake2b512';
						$algorithm = 'blake2b';
					} else {
						$php_algo  = 'sha256';
						$algorithm = 'sha256';
					}
					$fallback = true;
				}
				break;
			case 'shake128':
				if ( function_exists( 'hash_hmac_algos' ) && in_array( 'shake128', hash_hmac_algos(), true ) ) {
					$hash = hash_hmac( 'shake128', $data, $key );
					return array(
						'hash'      => $hash,
						'algorithm' => 'shake128',
						'mode'      => self::MODE_HMAC,
						'fallback'  => false,
					);
				} else {
					$php_algo  = 'sha256';
					$algorithm = 'sha256';
					$fallback  = true;
				}
				break;
			case 'shake256':
				if ( function_exists( 'hash_hmac_algos' ) && in_array( 'shake256', hash_hmac_algos(), true ) ) {
					$hash = hash_hmac( 'shake256', $data, $key );
					return array(
						'hash'      => $hash,
						'algorithm' => 'shake256',
						'mode'      => self::MODE_HMAC,
						'fallback'  => false,
					);
				} else {
					$php_algo  = 'sha256';
					$algorithm = 'sha256';
					$fallback  = true;
				}
				break;
			case 'sha224':
				$php_algo = 'sha224';
				break;
			case 'sha384':
				$php_algo = 'sha384';
				break;
			case 'sha512-224':
				$php_algo = 'sha512/224';
				break;
			case 'sha512-256':
				$php_algo = 'sha512/256';
				break;
			case 'ripemd160':
				$php_algo = 'ripemd160';
				break;
			case 'whirlpool':
				$php_algo = 'whirlpool';
				break;
			case 'sha256d':
				// Manual HMAC construction using SHA-256d as the hash primitive.
				$blocksize = 64;
				$k = $key;
				if ( strlen( $k ) > $blocksize ) {
					$k = hex2bin( hash( 'sha256', hex2bin( hash( 'sha256', $k ) ) ) );
				}
				if ( strlen( $k ) < $blocksize ) {
					$k = str_pad( $k, $blocksize, chr( 0x00 ) );
				}
				$ipad  = str_repeat( chr( 0x36 ), $blocksize );
				$opad  = str_repeat( chr( 0x5c ), $blocksize );
				$inner = hash( 'sha256', hex2bin( hash( 'sha256', ( $k ^ $ipad ) . $data ) ) );
				$hash  = hash( 'sha256', hex2bin( hash( 'sha256', ( $k ^ $opad ) . hex2bin( $inner ) ) ) );
				return array(
					'hash'      => $hash,
					'algorithm' => 'sha256d',
					'mode'      => self::MODE_HMAC,
					'fallback'  => false,
				);
			case 'blake2s':
				if ( function_exists( 'hash_hmac_algos' ) && in_array( 'blake2s256', hash_hmac_algos(), true ) ) {
					$hash = hash_hmac( 'blake2s256', $data, $key );
					return array(
						'hash'      => $hash,
						'algorithm' => 'blake2s',
						'mode'      => self::MODE_HMAC,
						'fallback'  => false,
					);
				} else {
					$php_algo  = 'sha256';
					$algorithm = 'sha256';
					$fallback  = true;
				}
				break;
			case 'gost':
				if ( function_exists( 'hash_hmac_algos' ) && in_array( 'gost', hash_hmac_algos(), true ) ) {
					$php_algo = 'gost';
				} else {
					$php_algo  = 'sha256';
					$algorithm = 'sha256';
					$fallback  = true;
				}
				break;
			case 'gost-crypto':
				if ( function_exists( 'hash_hmac_algos' ) && in_array( 'gost-crypto', hash_hmac_algos(), true ) ) {
					$php_algo = 'gost-crypto';
				} else {
					$php_algo  = 'sha256';
					$algorithm = 'sha256';
					$fallback  = true;
				}
				break;
			case 'md5':
				$php_algo = 'md5';
				break;
			case 'sha1':
				$php_algo = 'sha1';
				break;
			case 'sha256':
			default:
				$php_algo  = 'sha256';
				$algorithm = 'sha256';
				break;
		}

		$hash = hash_hmac( $php_algo, $data, $key );

		return array(
			'hash'      => $hash,
			'algorithm' => $algorithm,
			'mode'      => self::MODE_HMAC,
			'fallback'  => $fallback,
		);
	}

	// ── Unified compute_packed – THE single entry point for all callers ──

	/**
	 * Compute a hash (Standard or HMAC depending on global settings) and
	 * return it in packed form ready for storage.
	 *
	 * callers MUST check ['hmac_unavailable'] before persisting the result:
	 *   if ( $result['hmac_unavailable'] ) { ... show error, abort ... }
	 *
	 * @param  string      $data
	 * @param  string|null $algorithm
	 * @return array{
	 *   packed:           string,
	 *   hash:             string,
	 *   algorithm:        string,
	 *   mode:             string,
	 *   fallback:         bool,
	 *   hmac_unavailable: bool
	 * }
	 */
	public static function compute_packed( $data, $algorithm = null ) {
		$hmac_unavailable = false;

		if ( self::is_hmac_mode_enabled() ) {
			$result = self::compute_hmac( $data, $algorithm );
			if ( $result === false ) {
				// Key constant missing – flag and degrade gracefully.
				$hmac_unavailable = true;
				$result           = self::compute( $data, $algorithm );
			}
		} else {
			$result = self::compute( $data, $algorithm );
		}

		$result['packed']           = self::pack( $result['hash'], $result['algorithm'], $result['mode'] );
		$result['hmac_unavailable'] = $hmac_unavailable;

		return $result;
	}

	/**
	 * Wrapper for compute_packed to maintain compatibility.
	 * Returns array with 'packed', 'mode', and 'hmac_unavailable' keys.
	 *
	 * @param  string      $data
	 * @param  string|null $algorithm
	 * @return array|false
	 */
	public static function compute_hash( $data, $algorithm = null ) {
		$result = self::compute_packed( $data, $algorithm );
		return array(
			'packed'           => $result['packed'],
			'mode'             => $result['mode'],
			'hmac_unavailable' => $result['hmac_unavailable'],
		);
	}

	/**
	 * Compute hash for verification (recomputes with specified algorithm/mode).
	 *
	 * @param  string $data
	 * @param  string $algorithm
	 * @param  string $mode
	 * @return array{ hash: string }|false
	 */
	public static function compute_hash_for_verification( $data, $algorithm, $mode ) {
		if ( $mode === self::MODE_HMAC ) {
			$result = self::compute_hmac( $data, $algorithm );
			if ( $result === false ) {
				return false;
			}
			return array( 'hash' => $result['hash'] );
		}
		$result = self::compute( $data, $algorithm );
		return array( 'hash' => $result['hash'] );
	}

	// ── pack / unpack ───────────────────────────────────────────────────

	/**
	 * Pack a hash, algorithm, and mode into one storable string.
	 *
	 * @param  string $hash
	 * @param  string $algorithm
	 * @param  string $mode
	 * @return string
	 */
	public static function pack( $hash, $algorithm, $mode = self::MODE_STANDARD ) {
		if ( $mode === self::MODE_HMAC ) {
			return 'hmac-' . $algorithm . ':' . $hash;
		}
		return $algorithm . ':' . $hash;
	}

	/**
	 * Decode a stored packed string.
	 *
	 * Handles all three formats:
	 *   "hmac-sha256:hex"  → algorithm=sha256, mode=hmac
	 *   "sha256:hex"       → algorithm=sha256, mode=standard
	 *   "hex" (legacy)     → algorithm=sha256, mode=standard
	 *
	 * @param  string $packed
	 * @return array{ hash: string, algorithm: string, mode: string }
	 */
	public static function unpack( $packed ) {
		if ( strpos( $packed, ':' ) === false ) {
			return array(
				'algorithm' => 'sha256',
				'hash'      => $packed,
				'mode'      => self::MODE_STANDARD,
			);
		}

		$colon_pos = strpos( $packed, ':' );
		$prefix    = substr( $packed, 0, $colon_pos );
		$hex       = substr( $packed, $colon_pos + 1 );

		if ( strncmp( $prefix, 'hmac-', 5 ) === 0 ) {
			return array(
				'algorithm' => sanitize_key( substr( $prefix, 5 ) ),
				'hash'      => $hex,
				'mode'      => self::MODE_HMAC,
			);
		}

		return array(
			'algorithm' => sanitize_key( $prefix ),
			'hash'      => $hex,
			'mode'      => self::MODE_STANDARD,
		);
	}

	// ── Verification helper ─────────────────────────────────────────────

	/**
	 * Recompute and constant-time compare for the given mode.
	 *
	 * @param  string $data
	 * @param  string $stored_hex
	 * @param  string $algorithm
	 * @param  string $mode
	 * @return bool|null  true=match, false=mismatch, null=hmac-key-missing
	 */
	public static function verify_data( $data, $stored_hex, $algorithm, $mode ) {
		if ( $mode === self::MODE_HMAC ) {
			$result = self::compute_hmac( $data, $algorithm );
			if ( $result === false ) {
				return null;
			}
			return hash_equals( $stored_hex, $result['hash'] );
		}
		$result = self::compute( $data, $algorithm );
		return hash_equals( $stored_hex, $result['hash'] );
	}

	// ── Experimental algorithm implementations ─────────────────────────

	/**
	 * Pure-PHP BLAKE3 implementation (256-bit output).
	 * Uses MDSM_BLAKE3 class which provides native support if available,
	 * otherwise falls back to BLAKE2b-512 (truncated) or SHA-256.
	 *
	 * @param  string $data
	 * @return string Hex-encoded hash
	 */
	private static function compute_blake3( $data ) {
		// Use MDSM_BLAKE3 class if available (should always be loaded)
		if ( class_exists( 'MDSM_BLAKE3' ) ) {
			return MDSM_BLAKE3::hash( $data );
		}
		
		// Emergency fallback if class isn't loaded
		if ( in_array( 'blake3', hash_algos(), true ) ) {
			return hash( 'blake3', $data );
		}
		
		// Use BLAKE2b-512 truncated to 256 bits
		if ( in_array( 'blake2b512', hash_algos(), true ) ) {
			return substr( hash( 'blake2b512', $data ), 0, 64 );
		}
		
		// Final fallback to SHA-256
		return hash( 'sha256', $data );
	}

	/**
	 * Pure-PHP BLAKE3 HMAC implementation.
	 * Uses MDSM_BLAKE3 class which provides native support if available,
	 * otherwise falls back to BLAKE2b-512 (truncated) or SHA-256 HMAC.
	 *
	 * @param  string $data
	 * @param  string $key
	 * @return string Hex-encoded hash
	 */
	private static function compute_blake3_hmac( $data, $key ) {
		// Use MDSM_BLAKE3 class if available (should always be loaded)
		if ( class_exists( 'MDSM_BLAKE3' ) ) {
			return MDSM_BLAKE3::hmac( $data, $key );
		}
		
		// Emergency fallback if class isn't loaded
		if ( function_exists( 'hash_hmac_algos' ) && in_array( 'blake3', hash_hmac_algos(), true ) ) {
			return hash_hmac( 'blake3', $data, $key );
		}
		
		// Manual HMAC construction using compute_blake3 as the hash function
		$blocksize = 64;
		if ( strlen( $key ) > $blocksize ) {
			$key = self::compute_blake3( $key );
			$key = hex2bin( $key );
		}
		if ( strlen( $key ) < $blocksize ) {
			$key = str_pad( $key, $blocksize, chr( 0x00 ) );
		}
		$ipad = str_repeat( chr( 0x36 ), $blocksize );
		$opad = str_repeat( chr( 0x5c ), $blocksize );
		$ikey = $key ^ $ipad;
		$okey = $key ^ $opad;
		$inner = self::compute_blake3( $ikey . $data );
		$outer = self::compute_blake3( $okey . hex2bin( $inner ) );
		return $outer;
	}

	// ── Platform checks ────────────────────────────────────────────────

	public static function is_blake2b_available() {
		$algos = hash_algos();
		return in_array( 'blake2b512', $algos, true ) || in_array( 'blake2b', $algos, true );
	}

	public static function is_blake2s_available() {
		return in_array( 'blake2s256', hash_algos(), true );
	}

	public static function is_sha256d_available() {
		// SHA-256d only requires SHA-256, which is always present.
		return true;
	}

	public static function is_sha2_truncated_available() {
		// SHA-224, SHA-384, SHA-512/224, SHA-512/256 are present in all
		// PHP builds since 5.4 via the bundled hash extension.
		return true;
	}

	public static function is_sha3_available() {
		$algos = hash_algos();
		return in_array( 'sha3-256', $algos, true ) && in_array( 'sha3-512', $algos, true );
	}

	public static function is_hmac_available() {
		return function_exists( 'hash_hmac' );
	}

	public static function is_blake3_available() {
		// BLAKE3 is always "available" because we have fallback implementation
		// Check if native BLAKE3 is available, or if our fallback class exists
		if ( class_exists( 'MDSM_BLAKE3' ) ) {
			return true; // Always available via fallback
		}
		return in_array( 'blake3', hash_algos(), true );
	}

	public static function is_shake_available() {
		$algos = hash_algos();
		return in_array( 'shake128', $algos, true ) && in_array( 'shake256', $algos, true );
	}

	public static function get_algorithm_availability( $algorithm ) {
		switch ( $algorithm ) {
			case 'sha224':
			case 'sha384':
			case 'sha512-224':
			case 'sha512-256':
				return self::is_sha2_truncated_available();
			case 'ripemd160':
			case 'whirlpool':
			case 'md5':
			case 'sha1':
				return true;
			case 'gost':
				return in_array( 'gost', hash_algos(), true );
			case 'gost-crypto':
				return in_array( 'gost-crypto', hash_algos(), true );
			case 'sha256d':
				return self::is_sha256d_available();
			case 'blake2s':
				return self::is_blake2s_available();
			case 'blake3':
				return self::is_blake3_available();
			case 'shake128':
			case 'shake256':
				return self::is_shake_available();
			case 'blake2b':
				return self::is_blake2b_available();
			case 'sha3-256':
			case 'sha3-512':
				return self::is_sha3_available();
			default:
				return true;
		}
	}

	// ── Admin status helper ────────────────────────────────────────────

	/**
	 * Return a structured HMAC status array for the admin UI and notices.
	 *
	 * @return array{
	 *   mode_enabled: bool, key_defined: bool, key_strong: bool,
	 *   ready: bool, hmac_available: bool,
	 *   notice_level: string, notice_message: string
	 * }
	 */
	public static function hmac_status() {
		$mode_enabled   = self::is_hmac_mode_enabled();
		$key_defined    = self::is_hmac_key_defined();
		$key_strong     = self::is_hmac_key_strong();
		$hmac_available = self::is_hmac_available();
		$ready          = $mode_enabled && $key_defined && $hmac_available;

		$notice_level   = 'ok';
		$notice_message = '';

		if ( $mode_enabled ) {
			if ( ! $hmac_available ) {
				$notice_level   = 'error';
				$notice_message = __( 'HMAC is not available on this PHP build. Disable HMAC Integrity Mode.', 'archiviomd' );
			} elseif ( ! $key_defined ) {
				$notice_level   = 'error';
				$notice_message = sprintf(
					/* translators: %s: constant name */
					__( 'HMAC Integrity Mode is enabled but %s is not defined in wp-config.php. Hash generation is blocked until the key is added.', 'archiviomd' ),
					'<code>' . esc_html( self::HMAC_KEY_CONSTANT ) . '</code>'
				);
			} elseif ( ! $key_strong ) {
				$notice_level   = 'warning';
				$notice_message = sprintf(
					/* translators: %d: minimum character count */
					__( 'The HMAC key is shorter than %d characters. Use a longer random key for production security.', 'archiviomd' ),
					self::HMAC_KEY_MIN_LENGTH
				);
			} else {
				$notice_message = __( 'HMAC Integrity Mode is active. All new hashes are HMAC-signed with the configured key.', 'archiviomd' );
			}
		}

		return array(
			'mode_enabled'   => $mode_enabled,
			'key_defined'    => $key_defined,
			'key_strong'     => $key_strong,
			'ready'          => $ready,
			'hmac_available' => $hmac_available,
			'notice_level'   => $notice_level,
			'notice_message' => $notice_message,
		);
	}
}
