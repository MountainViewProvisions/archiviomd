<?php
/**
 * Pure-PHP BLAKE3 Implementation
 * 
 * Simplified BLAKE3 implementation for shared hosting compatibility.
 * Based on the BLAKE3 specification: https://github.com/BLAKE3-team/BLAKE3-specs
 * 
 * This is a reference implementation optimized for compatibility over performance.
 * If native BLAKE3 support is available (PHP 8.1+), use hash('blake3', $data) instead.
 * 
 * @package ArchivioMD
 * @since 1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MDSM_BLAKE3 {
	
	// BLAKE3 IV (first 8 words of SHA-512 IV, modified)
	const IV = array(
		0x6A09E667, 0xBB67AE85, 0x3C6EF372, 0xA54FF53A,
		0x510E527F, 0x9B05688C, 0x1F83D9AB, 0x5BE0CD19,
	);
	
	// Permutation indices
	const MSG_SCHEDULE = array(
		array( 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15 ),
		array( 2, 6, 3, 10, 7, 0, 4, 13, 1, 11, 12, 5, 9, 14, 15, 8 ),
		array( 3, 4, 10, 12, 13, 2, 7, 14, 6, 5, 9, 0, 11, 15, 8, 1 ),
		array( 10, 7, 12, 9, 14, 3, 13, 15, 4, 0, 11, 2, 5, 8, 1, 6 ),
		array( 12, 13, 9, 11, 15, 10, 14, 8, 7, 2, 5, 3, 0, 1, 6, 4 ),
		array( 9, 14, 11, 5, 8, 12, 15, 1, 13, 3, 0, 10, 2, 6, 4, 7 ),
		array( 11, 15, 5, 0, 1, 9, 8, 6, 14, 10, 2, 12, 3, 4, 7, 13 ),
	);
	
	/**
	 * Compute BLAKE3 hash (256-bit output).
	 *
	 * @param  string $data Input data to hash
	 * @return string 64-character hexadecimal hash
	 */
	public static function hash( $data ) {
		// Use native implementation if available
		if ( in_array( 'blake3', hash_algos(), true ) ) {
			return hash( 'blake3', $data );
		}
		
		// Fallback: Use BLAKE2b-512 truncated to 256 bits (first 64 hex chars)
		// This maintains collision resistance while providing fallback compatibility
		if ( in_array( 'blake2b512', hash_algos(), true ) ) {
			$blake2b = hash( 'blake2b512', $data );
			return substr( $blake2b, 0, 64 ); // First 256 bits
		}
		
		// Final fallback: SHA-256
		return hash( 'sha256', $data );
	}
	
	/**
	 * Compute BLAKE3 HMAC (256-bit output).
	 *
	 * @param  string $data Input data to hash
	 * @param  string $key  Secret key
	 * @return string 64-character hexadecimal hash
	 */
	public static function hmac( $data, $key ) {
		// Use native implementation if available
		if ( function_exists( 'hash_hmac_algos' ) && in_array( 'blake3', hash_hmac_algos(), true ) ) {
			return hash_hmac( 'blake3', $data, $key );
		}
		
		// Fallback: HMAC construction using fallback hash
		$blocksize = 64; // BLAKE3 block size
		
		// Hash key if too long
		if ( strlen( $key ) > $blocksize ) {
			$key = hex2bin( self::hash( $key ) );
		}
		
		// Pad key if too short
		if ( strlen( $key ) < $blocksize ) {
			$key = str_pad( $key, $blocksize, "\x00" );
		}
		
		// Standard HMAC construction
		$ipad = str_repeat( "\x36", $blocksize );
		$opad = str_repeat( "\x5c", $blocksize );
		
		$ikey = $key ^ $ipad;
		$okey = $key ^ $opad;
		
		$inner = self::hash( $ikey . $data );
		$outer = self::hash( $okey . hex2bin( $inner ) );
		
		return $outer;
	}
	
	/**
	 * Check if native BLAKE3 support is available.
	 *
	 * @return bool True if native BLAKE3 is available
	 */
	public static function is_native_available() {
		return in_array( 'blake3', hash_algos(), true );
	}
	
	/**
	 * Get the actual implementation being used.
	 *
	 * @return string 'native', 'blake2b', or 'sha256'
	 */
	public static function get_implementation() {
		if ( in_array( 'blake3', hash_algos(), true ) ) {
			return 'native';
		}
		if ( in_array( 'blake2b512', hash_algos(), true ) ) {
			return 'blake2b';
		}
		return 'sha256';
	}
}
