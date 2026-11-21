<?php
/**
 * Helpers about the current request and the site's normal 404 page.
 *
 * @package MG_Stealth
 */

namespace MG_Stealth;

defined( 'ABSPATH' ) || exit;

/**
 * Request helpers.
 */
final class Request {

	const NOT_FOUND_TRANSIENT = 'mg_stealth_404_page';

	/**
	 * URL path of the request (without the query string), e.g. "/wp-login.php".
	 *
	 * @return string
	 */
	public static function path(): string {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only compared, never printed.
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$path = rawurldecode( $path );
		return '' === $path ? '/' : (string) preg_replace( '~/{2,}~', '/', $path );
	}

	/**
	 * URL path of the request relative to the home address, without slashes at the ends,
	 * e.g. "wp-login.php" or "" for the home page.
	 *
	 * @return string
	 */
	public static function relative_path(): string {
		$home = self::home_path();
		$path = self::path();
		if ( 0 === strpos( $path, $home ) ) {
			$path = substr( $path, strlen( $home ) );
		}
		return trim( $path, '/' );
	}

	/**
	 * URL path of the home address with slashes at both ends, e.g. "/" or "/blog/".
	 *
	 * @return string
	 */
	public static function home_path(): string {
		$path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		return '/' . ltrim( trailingslashit( $path ), '/' );
	}

	/**
	 * File name of the PHP script that handles the request, e.g. "index.php".
	 *
	 * @return string
	 */
	public static function script(): string {
		$script = isset( $_SERVER['SCRIPT_NAME'] ) ? (string) wp_unslash( $_SERVER['SCRIPT_NAME'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only compared.
		return strtolower( basename( $script ) );
	}

	/**
	 * Whether the request comes from WP-CLI.
	 *
	 * @return bool
	 */
	public static function is_cli(): bool {
		return defined( 'WP_CLI' ) && WP_CLI;
	}

	/**
	 * Whether the request carries credentials other than the session cookie (application
	 * passwords, Basic or Bearer authentication). Such requests are judged after authentication.
	 *
	 * @return bool
	 */
	public static function has_credentials(): bool {
		return ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) || ! empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) || ! empty( $_SERVER['PHP_AUTH_USER'] );
	}

	/**
	 * Whether the visitor is not signed in.
	 *
	 * @return bool
	 */
	public static function is_guest(): bool {
		return function_exists( 'is_user_logged_in' ) && ! is_user_logged_in();
	}

	/**
	 * Ends the request with the site's normal 404 page. Used where WordPress cannot render its
	 * theme (admin area, login file, XML-RPC, cron): the page is fetched once from the site
	 * itself, anonymously, and kept for a while.
	 */
	public static function serve_404(): void {
		$html = get_transient( self::NOT_FOUND_TRANSIENT );
		if ( ! is_string( $html ) || '' === $html ) {
			$html = self::fetch_404_page();
			set_transient( self::NOT_FOUND_TRANSIENT, $html, 10 * MINUTE_IN_SECONDS );
		}
		if ( ! headers_sent() ) {
			status_header( 404 );
			nocache_headers();
			header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset', 'UTF-8' ) );
			header_remove( 'X-Powered-By' );
			header_remove( 'X-Pingback' );
			header_remove( 'Link' );
			header_remove( 'X-Robots-Tag' );
			header_remove( 'X-Content-Type-Options' );
			header_remove( 'X-Frame-Options' );
			header_remove( 'Allow' );
		}
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The site's own rendered 404 page.
		exit;
	}

	/**
	 * Fetches the normal 404 page of the site (a random address that does not exist).
	 *
	 * @return string
	 */
	public static function fetch_404_page(): string {
		$url      = home_url( '/' . strtolower( wp_generate_password( 12, false, false ) ) . '/' );
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 5,
				'redirection' => 0,
				'sslverify'   => apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter for loopback requests.
				'headers'     => array( 'Accept' => 'text/html' ),
			)
		);
		$body     = is_wp_error( $response ) ? '' : (string) wp_remote_retrieve_body( $response );
		if ( 404 !== (int) wp_remote_retrieve_response_code( $response ) || '' === $body ) {
			$title = esc_html( get_bloginfo( 'name' ) );
			$body  = '<!doctype html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><title>404 | ' . $title . '</title></head><body><h1>404</h1><p>' . esc_html__( 'The page you are looking for does not exist.', 'mg-stealth' ) . '</p><p><a href="' . esc_url( home_url( '/' ) ) . '">' . $title . '</a></p></body></html>';
		}
		return $body;
	}

	/**
	 * Forgets the stored 404 page (after a theme or settings change).
	 */
	public static function forget_404_page(): void {
		delete_transient( self::NOT_FOUND_TRANSIENT );
	}
}
