<?php
/**
 * Runtime state: what the plugin wrote to the server and verified (separate from the settings,
 * so saving the settings form never overwrites it).
 *
 * @package MG_Stealth
 */

namespace MG_Stealth;

defined( 'ABSPATH' ) || exit;

/**
 * Stored in one option, mg_stealth_state.
 */
final class State {

	const OPTION = 'mg_stealth_state';

	/**
	 * Cached state.
	 *
	 * @var array<string,mixed>|null
	 */
	private static $cache = null;

	/**
	 * Default state.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			'rules_status'   => 'none', // none | written | manual | failed.
			'rules_hash'     => '',
			'rules_error'    => '',
			'paths_verified' => false,
			'map'            => array(), // Real URL path => alias URL path, as written to the server.
			'flush_rewrite'  => false,
			'emergency'      => false, // MG_STEALTH_DISABLE was seen; the rules are written back once it is gone.
			'site'           => '', // Install the rules were written and verified for (see site_fingerprint()).
			'checked_at'     => 0,
		);
	}

	/**
	 * Whole state.
	 *
	 * @return array<string,mixed>
	 */
	public static function all(): array {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );
		}
		return self::$cache;
	}

	/**
	 * One value.
	 *
	 * @param string $key Name.
	 * @return mixed
	 */
	public static function get( string $key ) {
		$all = self::all();
		return $all[ $key ] ?? null;
	}

	/**
	 * Updates some values.
	 *
	 * @param array<string,mixed> $values New values.
	 */
	public static function update( array $values ): void {
		$state       = array_merge( self::all(), $values );
		self::$cache = $state;
		update_option( self::OPTION, $state, true );
	}

	/**
	 * Identifies this install: a copy moved to another server or address (e.g. deployed from a
	 * local site together with its database and .htaccess) gets new rules before it uses them.
	 *
	 * @return string
	 */
	public static function site_fingerprint(): string {
		return md5( ABSPATH . '|' . home_url( '/' ) . '|' . MG_STEALTH_FILE );
	}

	/**
	 * Whether the stored rules and verification belong to this install.
	 *
	 * @return bool
	 */
	public static function matches_site(): bool {
		return self::get( 'site' ) === self::site_fingerprint();
	}

	/**
	 * Forgets the cached state.
	 */
	public static function flush_cache(): void {
		self::$cache = null;
	}
}
