<?php
/**
 * Decides the one-hop 301 to the main address (https, with or without www). The same rules as
 * the .htaccess block, for servers that do not read .htaccess. Pure functions, unit tested.
 *
 * @package MG_Stealth
 */

namespace MG_Stealth;

defined( 'ABSPATH' ) || exit;

/**
 * Redirect decision.
 */
final class Redirect_Rules {

	/**
	 * Returns the address to redirect to, or null when the request is already at the main address.
	 *
	 * @param array<string,mixed> $server $_SERVER-like array.
	 * @param array<string,mixed> $config host, port, scheme, https (bool), www (bool).
	 * @return string|null
	 */
	public static function target( array $server, array $config ): ?string {
		$want_https = ! empty( $config['https'] );
		$want_www   = ! empty( $config['www'] );
		$main_host  = strtolower( (string) ( $config['host'] ?? '' ) );
		if ( ( ! $want_https && ! $want_www ) || '' === $main_host ) {
			return null;
		}

		$uri = (string) ( $server['REQUEST_URI'] ?? '/' );
		if ( '' === $uri || '/' !== $uri[0] ) {
			$uri = '/' . $uri;
		}
		if ( 0 === strpos( $uri, '/.well-known/' ) ) {
			return null;
		}

		$host = strtolower( (string) ( $server['HTTP_HOST'] ?? '' ) );
		$host = (string) preg_replace( '~:[0-9]+$~', '', $host );
		$host = rtrim( $host, '.' );
		$bare = (string) preg_replace( '~^www\.~', '', $main_host );
		if ( $host !== $bare && $host !== 'www.' . $bare ) {
			return null; // Another host name (a local copy, an IP address): not ours to change.
		}

		$needs = false;
		if ( $want_www && $host !== $main_host ) {
			$needs = true;
		}
		if ( $want_https && ! self::is_https( $server ) ) {
			$needs = true;
		}
		if ( ! $needs ) {
			return null;
		}

		$scheme = $want_https ? 'https' : ( empty( $config['scheme'] ) ? 'https' : (string) $config['scheme'] );
		$port   = empty( $config['port'] ) ? '' : ':' . (int) $config['port'];
		return $scheme . '://' . $main_host . $port . $uri;
	}

	/**
	 * Whether the visitor used https, directly or through a proxy.
	 *
	 * @param array<string,mixed> $server $_SERVER-like array.
	 * @return bool
	 */
	public static function is_https( array $server ): bool {
		$https = strtolower( (string) ( $server['HTTPS'] ?? '' ) );
		if ( 'on' === $https || '1' === $https ) {
			return true;
		}
		if ( 0 === stripos( (string) ( $server['HTTP_X_FORWARDED_PROTO'] ?? '' ), 'https' ) ) {
			return true;
		}
		return false !== stripos( (string) ( $server['HTTP_CF_VISITOR'] ?? '' ), 'https' );
	}
}
