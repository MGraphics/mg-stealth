<?php
/**
 * Login at a private address; wp-login.php and the admin area are the normal 404 page for guests.
 *
 * @package MG_Stealth
 */

namespace MG_Stealth;

defined( 'ABSPATH' ) || exit;

/**
 * Private login address.
 */
final class Login {

	/**
	 * Whether this request is for the private login address.
	 *
	 * @var bool
	 */
	private $is_login_request = false;

	/**
	 * Whether the login module is on (single sites only).
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		return Settings::on( 'login_enabled' ) && Settings::is_valid_slug( (string) Settings::get( 'login_slug' ) ) && ! is_multisite();
	}

	/**
	 * The private login address.
	 *
	 * @param string|null $scheme URL scheme.
	 * @return string
	 */
	public static function url( $scheme = null ): string {
		return home_url( user_trailingslashit( (string) Settings::get( 'login_slug' ) ), $scheme );
	}

	/**
	 * Hooks.
	 */
	public function register(): void {
		if ( ! self::enabled() ) {
			return;
		}
		$this->detect();
		add_action( 'plugins_loaded', array( $this, 'guard_login_file' ), 1 );
		add_action( 'wp_loaded', array( $this, 'on_wp_loaded' ), 1 );
		add_filter( 'site_url', array( $this, 'filter_site_url' ), 99, 3 );
		add_filter( 'network_site_url', array( $this, 'filter_site_url' ), 99, 3 );
		add_filter( 'wp_redirect', array( $this, 'filter_redirect' ), 99 );
	}

	/**
	 * Recognises the private address (and the password form of protected posts, which posts to
	 * the home address instead of the login file).
	 */
	private function detect(): void {
		$path = Request::relative_path();
		if ( '' !== $path && trim( (string) Settings::get( 'login_slug' ), '/' ) === $path ) {
			$this->is_login_request = true;
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification -- Same handling as core's own form.
		if ( '' === $path && isset( $_GET['action'] ) && 'postpass' === $_GET['action'] && isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['post_password'] ) ) {
			$this->is_login_request = true;
		}
		// phpcs:enable
	}

	/**
	 * wp-login.php itself answers with the normal 404 page.
	 */
	public function guard_login_file(): void {
		if ( $this->is_login_request ) {
			$GLOBALS['pagenow'] = 'wp-login.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Same as when WordPress serves its login file.
			return;
		}
		if ( 'wp-login.php' === Request::script() && ! Request::is_cli() ) {
			Request::serve_404();
		}
	}

	/**
	 * Guests in the admin area get the normal 404 page; the private address shows the login form.
	 */
	public function on_wp_loaded(): void {
		if ( Request::is_cli() ) {
			return;
		}
		if ( is_admin() && ! is_user_logged_in() ) {
			global $pagenow;
			$ajax_by_name = wp_doing_ajax() && Paths::active() && false !== strpos( Request::path(), '/admin-ajax.php' );
			if ( ( ! wp_doing_ajax() && ! wp_doing_cron() && 'admin-post.php' !== $pagenow ) || $ajax_by_name ) {
				Request::serve_404();
			}
		}
		if ( $this->is_login_request ) {
			$this->render_login();
		}
	}

	/**
	 * Serves WordPress' login file at the private address.
	 */
	private function render_login(): void {
		$this->redirect_signed_in();
		// The login file reads these as globals.
		global $error, $interim_login, $action, $user_login;
		$user_login = '';
		$error      = '';
		if ( Paths::active() || Settings::on( 'clean_head' ) ) {
			ob_start( array( Plugin::instance()->cleaner(), 'filter_output' ) );
		}
		require_once ABSPATH . 'wp-login.php';
		exit;
	}

	/**
	 * Whether a request for the login address should skip the form for a signed-in user: only a
	 * plain GET of the login form, not the re-authentication form, the interim (modal) login, the
	 * "check your e-mail" notice or any other action (logout, lost password, password reset,
	 * registration, protected posts, confirmations).
	 *
	 * @param array  $query  Query arguments ($_GET).
	 * @param string $method Request method.
	 * @return bool
	 */
	public static function skips_form( array $query, string $method ): bool {
		if ( 'GET' !== strtoupper( $method ) && 'HEAD' !== strtoupper( $method ) ) {
			return false;
		}
		$action = $query['action'] ?? '';
		if ( '' !== $action && 'login' !== $action ) {
			return false;
		}
		foreach ( array( 'reauth', 'interim-login', 'customize-login', 'checkemail' ) as $key ) {
			if ( ! empty( $query[ $key ] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * A signed-in user who opens the login address goes straight to the admin area (or to an
	 * allowed redirect_to address), the same place WordPress sends them after signing in.
	 */
	private function redirect_signed_in(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only, the same arguments core's login form reads.
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET';
		if ( ! is_user_logged_in() || ! self::skips_form( wp_unslash( $_GET ), $method ) ) {
			return;
		}
		// Denied addresses keep getting the normal 404 page from the lockout, signed in or not.
		if ( Lockout::enabled() && Lockout::is_denied( ( new Lockout() )->ip() ) ) {
			return;
		}
		$user      = wp_get_current_user();
		$requested = isset( $_GET['redirect_to'] ) && is_string( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '';
		// phpcs:enable
		$target = '' !== $requested ? $requested : admin_url();
		/** This filter is documented in wp-login.php */
		$target = (string) apply_filters( 'login_redirect', $target, $requested, $user ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's own filter.
		if ( '' === $target || 'wp-admin/' === $target || admin_url() === $target ) {
			if ( ! $user->has_cap( 'edit_posts' ) ) {
				$target = $user->has_cap( 'read' ) ? admin_url( 'profile.php' ) : home_url( '/' );
			}
		}
		$target = wp_validate_redirect( $target, admin_url() );
		nocache_headers();
		wp_safe_redirect( $target, 302 );
		exit;
	}

	/**
	 * Whether the private address may appear in this answer: on the login page itself, in the
	 * admin area, for signed-in users and in background jobs; never on public pages for guests.
	 *
	 * @return bool
	 */
	private function may_reveal(): bool {
		if ( $this->is_login_request || is_admin() || Request::is_cli() || wp_doing_cron() ) {
			return true;
		}
		return did_action( 'plugins_loaded' ) && function_exists( 'is_user_logged_in' ) && is_user_logged_in();
	}

	/**
	 * Replaces wp-login.php in an address.
	 *
	 * @param string $url Address.
	 * @return string
	 */
	private function replace( string $url ): string {
		if ( false === strpos( $url, 'wp-login.php' ) ) {
			return $url;
		}
		$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
		parse_str( $query, $args );
		if ( isset( $args['action'] ) && 'postpass' === $args['action'] ) {
			return add_query_arg( 'action', 'postpass', home_url( '/' ) );
		}
		if ( ! $this->may_reveal() ) {
			return home_url( '/' );
		}
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		$target = self::url( is_string( $scheme ) ? $scheme : null );
		return '' === $query ? $target : $target . '?' . $query;
	}

	/**
	 * Login, logout, password and registration addresses.
	 *
	 * @param string $url    Address.
	 * @param string $path   Path.
	 * @param mixed  $scheme Scheme.
	 * @return string
	 */
	public function filter_site_url( $url, $path = '', $scheme = null ): string {
		return $this->replace( (string) $url );
	}

	/**
	 * Redirects to the login file.
	 *
	 * @param string $location Address.
	 * @return string
	 */
	public function filter_redirect( $location ): string {
		return $this->replace( (string) $location );
	}
}
