<?php
/**
 * One-hop 301 to https and the main host name. The .htaccess block does it on Apache/LiteSpeed;
 * this is the same rule in PHP for other servers (and a safety net).
 *
 * @package MG_Stealth
 */

namespace MG_Stealth;

defined( 'ABSPATH' ) || exit;

/**
 * Redirects.
 */
final class Redirects {

	/**
	 * Redirects the current request when it is not at the main address.
	 */
	public function maybe_redirect(): void {
		if ( Request::is_cli() || ( ! Settings::on( 'redirect_https' ) && ! Settings::on( 'redirect_www' ) ) ) {
			return;
		}
		$server = array();
		foreach ( array( 'HTTP_HOST', 'REQUEST_URI', 'HTTPS', 'HTTP_X_FORWARDED_PROTO', 'HTTP_CF_VISITOR' ) as $key ) {
			if ( isset( $_SERVER[ $key ] ) ) {
				$server[ $key ] = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
			}
		}
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			// The path and query are kept exactly as sent (not sanitised as text).
			$server['REQUEST_URI'] = (string) wp_unslash( $_SERVER['REQUEST_URI'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passed through esc_url_raw() below.
		}
		$target = Redirect_Rules::target( $server, Server_Rules::redirect_config() );
		if ( null === $target || headers_sent() ) {
			return;
		}
		header( 'Location: ' . esc_url_raw( $target ), true, 301 );
		exit;
	}
}
