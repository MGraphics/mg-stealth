<?php
/**
 * Typical WordPress addresses: the normal 404 page for visitors; REST API closed for guests.
 *
 * @package MG_Stealth
 */

namespace MG_Stealth;

defined( 'ABSPATH' ) || exit;

/**
 * Access rules.
 */
final class Access {

	/**
	 * Hooks.
	 */
	public function register(): void {
		$s = Settings::all();

		$prefix = Paths::rest_prefix();
		if ( '' !== $prefix ) {
			add_filter(
				'rest_url_prefix',
				static function () use ( $prefix ) {
					return $prefix;
				},
				99
			);
		}
		if ( ! empty( $s['rest_guests_closed'] ) ) {
			add_action( 'parse_request', array( $this, 'rest_gate_parse' ), 0 );
			add_filter( 'rest_pre_dispatch', array( $this, 'rest_gate_dispatch' ), 0, 3 );
		}

		if ( ! empty( $s['block_xmlrpc'] ) ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
			add_filter( 'xmlrpc_methods', '__return_empty_array', 99 );
			add_filter( 'pings_open', '__return_false', 99 );
		}

		if ( ! empty( $s['block_authors'] ) ) {
			add_filter( 'wp_sitemaps_add_provider', array( $this, 'drop_users_sitemap' ), 10, 2 );
			add_filter( 'oembed_response_data', array( $this, 'drop_oembed_author' ), 99 );
			add_filter( 'rest_endpoints', array( $this, 'drop_users_endpoints' ), 99 );
		}

		if ( ! empty( $s['block_cron'] ) && '' !== (string) $s['cron_key'] ) {
			add_filter( 'cron_request', array( $this, 'sign_cron_request' ), 99 );
		}

		if ( ! empty( $s['block_shortcuts'] ) || ! empty( $s['login_enabled'] ) ) {
			add_action( 'init', array( $this, 'remove_admin_shortcuts' ), 20 );
		}

		if ( ! empty( $s['neutral_sitemap'] ) ) {
			add_action( 'init', array( $this, 'add_sitemap_rules' ), 20 );
			add_filter( 'home_url', array( $this, 'neutral_sitemap_url' ), 99, 2 );
			add_filter( 'wp_sitemaps_stylesheet_url', '__return_empty_string', 99 );
			add_filter( 'wp_sitemaps_stylesheet_index_url', '__return_empty_string', 99 );
		}

		if ( ! empty( $s['login_enabled'] ) ) {
			add_filter( 'robots_txt', array( $this, 'filter_robots' ), 99 );
		}

		if ( ! empty( $s['block_core_files'] ) ) {
			// After admin_init: handlers registered there are known by then.
			add_action( 'admin_init', array( $this, 'guard_guest_endpoints' ), PHP_INT_MAX );
			add_action( 'wp_loaded', array( $this, 'guard_empty_ajax' ), 5 );
		}

		add_filter( 'request', array( $this, 'filter_request' ), 100 );
		add_action( 'template_redirect', array( $this, 'late_404' ), -1 );
		add_filter( 'redirect_canonical', array( $this, 'no_redirect_from_404' ), 100 );
	}

	/**
	 * Requests that never reach the theme: core entry points loaded directly. Runs early
	 * (plugins_loaded), so nothing of WordPress' own answer is sent.
	 */
	public function guard_entry_points(): void {
		if ( Request::is_cli() ) {
			return;
		}
		$s      = Settings::all();
		$script = Request::script();

		if ( ! empty( $s['block_xmlrpc'] ) && defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			Request::serve_404();
		}
		if ( ! empty( $s['block_core_files'] ) ) {
			$files = array( 'wp-links-opml.php', 'wp-trackback.php', 'wp-mail.php' );
			if ( ! is_multisite() ) {
				$files[] = 'wp-signup.php';
				$files[] = 'wp-activate.php';
			}
			if ( in_array( $script, $files, true ) ) {
				Request::serve_404();
			}
		}
		if ( ! empty( $s['block_cron'] ) && '' !== (string) $s['cron_key'] && 'wp-cron.php' === $script ) {
			$key = isset( $_GET['k'] ) ? sanitize_text_field( wp_unslash( $_GET['k'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A shared secret, not a form.
			if ( ! hash_equals( (string) $s['cron_key'], $key ) ) {
				Request::serve_404();
			}
		}
	}

	/**
	 * admin-ajax.php without an action, for guests: the normal 404 page instead of "0" (WordPress
	 * answers that before admin_init).
	 */
	public function guard_empty_ajax(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only the presence of the action is checked, as core does.
		if ( wp_doing_ajax() && empty( $_REQUEST['action'] ) && ! Request::is_cli() && ! is_user_logged_in() ) {
			Request::serve_404();
		}
	}

	/**
	 * admin-ajax.php and admin-post.php for guests: when nothing handles the requested action,
	 * the normal 404 page instead of WordPress' "0" or empty page. The check runs as the last
	 * handler of the action, so handlers registered late still count.
	 */
	public function guard_guest_endpoints(): void {
		if ( Request::is_cli() || is_user_logged_in() ) {
			return;
		}
		global $pagenow;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only the action name is read, as core does.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
		if ( wp_doing_ajax() && '' !== $action ) {
			add_action( 'wp_ajax_nopriv_' . $action, array( $this, 'unhandled_action' ), PHP_INT_MAX );
		} elseif ( 'admin-post.php' === $pagenow ) {
			add_action( '' === $action ? 'admin_post_nopriv' : 'admin_post_nopriv_' . $action, array( $this, 'unhandled_action' ), PHP_INT_MAX );
		}
	}

	/**
	 * Last handler of a guest AJAX / admin-post action: the 404 page when it is the only one.
	 */
	public function unhandled_action(): void {
		global $wp_filter;
		$hook  = current_filter();
		$count = 0;
		if ( isset( $wp_filter[ $hook ] ) ) {
			foreach ( $wp_filter[ $hook ]->callbacks as $callbacks ) {
				$count += count( $callbacks );
			}
		}
		if ( $count <= 1 ) {
			Request::serve_404();
		}
	}

	/**
	 * Adds the secret key to WordPress' own call of wp-cron.php.
	 *
	 * @param array<string,mixed> $request Cron request.
	 * @return array<string,mixed>
	 */
	public function sign_cron_request( $request ): array {
		$request = (array) $request;
		if ( isset( $request['url'] ) ) {
			$request['url'] = add_query_arg( 'k', (string) Settings::get( 'cron_key' ), (string) $request['url'] );
		}
		return $request;
	}

	/**
	 * Address of wp-cron.php for a server cron job.
	 *
	 * @return string
	 */
	public static function cron_url(): string {
		return add_query_arg( 'k', (string) Settings::get( 'cron_key' ), site_url( 'wp-cron.php' ) );
	}

	/**
	 * Whether a REST route is open for guests.
	 *
	 * @param string $route Route, e.g. "/contact-form-7/v1/contact-forms/5/feedback".
	 * @return bool
	 */
	public static function rest_route_allowed( string $route ): bool {
		$route = trim( strtolower( $route ), '/' );
		if ( '' === $route ) {
			return false;
		}
		$allow = preg_split( '~\R~', (string) Settings::get( 'rest_allow' ) );
		foreach ( (array) apply_filters( 'mg_stealth_rest_allow', (array) $allow ) as $entry ) {
			$entry = trim( strtolower( (string) $entry ), '/' );
			if ( '' !== $entry && ( $route === $entry || 0 === strpos( $route, $entry . '/' ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * REST API for guests: the normal 404 page unless the route is allowed. Requests with other
	 * credentials (application passwords) are judged after authentication in rest_gate_dispatch().
	 *
	 * @param \WP $wp Request.
	 */
	public function rest_gate_parse( $wp ): void {
		if ( ! isset( $wp->query_vars['rest_route'] ) || '' === (string) $wp->query_vars['rest_route'] ) {
			return;
		}
		if ( ! Request::is_guest() || Request::has_credentials() ) {
			return;
		}
		if ( self::rest_route_allowed( (string) $wp->query_vars['rest_route'] ) ) {
			return;
		}
		$wp->query_vars = array( 'error' => '404' );
	}

	/**
	 * Second line: after authentication, a request without a user only reaches allowed routes.
	 *
	 * @param mixed            $result  Response so far.
	 * @param \WP_REST_Server  $server  Server.
	 * @param \WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function rest_gate_dispatch( $result, $server, $request ) {
		if ( null !== $result || ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_user_logged_in() ) {
			return $result;
		}
		if ( self::rest_route_allowed( (string) $request->get_route() ) ) {
			return $result;
		}
		return new \WP_Error( 'rest_no_route', 'No route was found matching the URL and request method.', array( 'status' => 404 ) );
	}

	/**
	 * Users endpoints of the REST API only for signed-in users.
	 *
	 * @param array<string,mixed> $endpoints Endpoints.
	 * @return array<string,mixed>
	 */
	public function drop_users_endpoints( $endpoints ): array {
		$endpoints = (array) $endpoints;
		if ( Request::is_guest() ) {
			foreach ( array_keys( $endpoints ) as $route ) {
				if ( 0 === strpos( (string) $route, '/wp/v2/users' ) ) {
					unset( $endpoints[ $route ] );
				}
			}
		}
		return $endpoints;
	}

	/**
	 * No users sitemap (it lists the author archives, i.e. login names).
	 *
	 * @param mixed  $provider Provider.
	 * @param string $name     Provider name.
	 * @return mixed
	 */
	public function drop_users_sitemap( $provider, $name ) {
		return 'users' === $name ? false : $provider;
	}

	/**
	 * oEmbed answers without the author's name and archive address.
	 *
	 * @param array<string,mixed> $data oEmbed data.
	 * @return array<string,mixed>
	 */
	public function drop_oembed_author( $data ): array {
		$data = (array) $data;
		unset( $data['author_name'], $data['author_url'] );
		return $data;
	}

	/**
	 * /login, /admin and /dashboard stay missing pages (WordPress would redirect them to the
	 * login form, revealing it, and the private login address with it).
	 */
	public function remove_admin_shortcuts(): void {
		remove_action( 'template_redirect', 'wp_redirect_admin_locations', 1000 );
	}

	/**
	 * Whether the core sitemap is served under neutral names right now.
	 *
	 * @return bool
	 */
	public static function neutral_sitemap_active(): bool {
		if ( ! Settings::on( 'neutral_sitemap' ) || ! did_action( 'init' ) || ! get_option( 'permalink_structure' ) ) {
			return false;
		}
		return (bool) apply_filters( 'wp_sitemaps_enabled', (bool) get_option( 'blog_public' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter, read only.
	}

	/**
	 * The core sitemap under /sitemap.xml, /sitemap-posts-page-1.xml … (without "wp-").
	 */
	public function add_sitemap_rules(): void {
		add_rewrite_rule( '^sitemap\.xml$', 'index.php?sitemap=index', 'top' );
		add_rewrite_rule( '^sitemap-([a-z]+?)-([a-z\d_-]+?)-(\d+?)\.xml$', 'index.php?sitemap=$matches[1]&sitemap-subtype=$matches[2]&paged=$matches[3]', 'top' );
		add_rewrite_rule( '^sitemap-([a-z]+?)-(\d+?)\.xml$', 'index.php?sitemap=$matches[1]&paged=$matches[2]', 'top' );
	}

	/**
	 * Sitemap addresses built by WordPress itself (index, canonical redirects, robots.txt).
	 *
	 * @param string $url  Address.
	 * @param string $path Requested path.
	 * @return string
	 */
	public function neutral_sitemap_url( $url, $path = '' ): string {
		$url = (string) $url;
		if ( 0 !== strpos( ltrim( (string) $path, '/' ), 'wp-sitemap' ) || ! self::neutral_sitemap_active() ) {
			return $url;
		}
		return (string) preg_replace( '~/wp-sitemap~', '/sitemap', $url, 1 );
	}

	/**
	 * robots.txt without the lines that name the admin folder.
	 *
	 * @param string $output robots.txt.
	 * @return string
	 */
	public function filter_robots( $output ): string {
		$output = (string) preg_replace( '~^(?:Dis)?allow:\s*\S*/wp-admin/.*\R?~mi', '', (string) $output );
		if ( ! preg_match( '~^(?:Dis)?allow:~mi', $output ) ) {
			$output = (string) preg_replace( '~^(User-agent:\s*\*)\R~mi', "$1\nDisallow:\n", $output, 1 );
		}
		return $output;
	}

	/**
	 * Turns requests for unused WordPress features into the normal 404 page before the query runs.
	 *
	 * @param array<string,mixed> $query_vars Query variables.
	 * @return array<string,mixed>
	 */
	public function filter_request( $query_vars ): array {
		$query_vars = (array) $query_vars;
		$s          = Settings::all();
		$missing    = false;

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only checks of the address.
		if ( ! empty( $s['block_authors'] ) && ( isset( $query_vars['author'] ) || isset( $query_vars['author_name'] ) || isset( $_GET['author'] ) ) ) {
			$missing = true;
		}
		// phpcs:enable
		if ( ! empty( $s['block_feeds'] ) && isset( $query_vars['feed'] ) ) {
			$missing = true;
		}
		if ( ! empty( $s['clean_oembed'] ) && isset( $query_vars['embed'] ) ) {
			$missing = true;
		}
		if ( ! empty( $s['block_core_files'] ) && isset( $query_vars['tb'] ) ) {
			$missing = true;
		}
		if ( ! empty( $s['neutral_sitemap'] ) && 0 === strpos( Request::relative_path(), 'wp-sitemap' ) ) {
			$missing = true;
		}

		return $missing ? array( 'error' => '404' ) : $query_vars;
	}

	/**
	 * Safety net when another plugin sets these flags later.
	 */
	public function late_404(): void {
		$s = Settings::all();
		if (
			( ! empty( $s['block_authors'] ) && is_author() )
			|| ( ! empty( $s['block_feeds'] ) && is_feed() )
			|| ( ! empty( $s['clean_oembed'] ) && is_embed() )
		) {
			global $wp_query;
			$wp_query->set_404();
			$wp_query->is_feed      = false;
			$wp_query->is_embed     = false;
			$wp_query->is_trackback = false;
			status_header( 404 );
			nocache_headers();
		}
	}

	/**
	 * No canonical redirects from a 404 (?author=1 → /author/<login>/, guessed addresses).
	 *
	 * @param mixed $redirect Redirect address.
	 * @return mixed
	 */
	public function no_redirect_from_404( $redirect ) {
		return is_404() ? false : $redirect;
	}
}
