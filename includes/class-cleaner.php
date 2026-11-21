<?php
/**
 * Clean page source and headers, and the output filter that applies the neutral paths.
 *
 * @package MG_Stealth
 */

namespace MG_Stealth;

defined( 'ABSPATH' ) || exit;

/**
 * Page source and response headers.
 */
final class Cleaner {

	/**
	 * Hooks.
	 */
	public function register(): void {
		$s = Settings::all();

		if ( ! empty( $s['clean_head'] ) ) {
			add_action( 'init', array( $this, 'remove_head_actions' ), 20 );
			add_action( 'do_faviconico', array( $this, 'favicon' ), 99 );
			add_filter( 'the_generator', '__return_empty_string' );
		}
		if ( ! empty( $s['clean_emoji'] ) ) {
			add_action( 'init', array( $this, 'remove_emoji' ), 20 );
		}
		if ( ! empty( $s['clean_oembed'] ) ) {
			add_action( 'init', array( $this, 'remove_oembed_discovery' ), 20 );
		}
		if ( ! empty( $s['clean_speculation'] ) ) {
			add_filter( 'wp_speculation_rules_configuration', '__return_null', 99 );
		}
		if ( ! empty( $s['clean_headers'] ) ) {
			add_action( 'plugins_loaded', array( $this, 'remove_php_headers' ), 0 );
			add_action( 'send_headers', array( $this, 'remove_php_headers' ), 99 );
			add_filter( 'wp_headers', array( $this, 'filter_wp_headers' ), 99 );
			add_filter( 'x_redirect_by', '__return_false', 99 );
			add_filter( 'nocache_headers', array( $this, 'filter_nocache_headers' ), 99 );
			add_action( 'init', array( $this, 'remove_link_headers' ), 20 );
		}
		if ( ! empty( $s['clean_versions'] ) ) {
			foreach ( array( 'style_loader_src', 'script_loader_src', 'script_module_loader_src' ) as $hook ) {
				add_filter( $hook, array( $this, 'filter_asset_version' ), 999 );
			}
		}
		if ( ! empty( $s['clean_body_classes'] ) ) {
			add_filter( 'body_class', array( $this, 'filter_body_class' ), 999 );
			add_filter( 'comment_class', array( $this, 'filter_comment_class' ), 999 );
		}
		if ( ! empty( $s['clean_login_branding'] ) ) {
			add_filter( 'login_headerurl', array( $this, 'login_header_url' ) );
			add_filter( 'login_headertext', array( $this, 'login_header_text' ) );
			add_filter( 'login_title', array( $this, 'login_title' ), 99, 2 );
			add_action( 'login_enqueue_scripts', array( $this, 'login_logo_style' ) );
			add_filter( 'admin_title', array( $this, 'admin_title' ), 99 );
		}
		if ( ! empty( $s['clean_mail'] ) ) {
			add_filter( 'wp_mail_from_name', array( $this, 'mail_from_name' ), 99 );
			add_action( 'phpmailer_init', array( $this, 'mail_x_mailer' ), 99 );
		}

		add_action( 'template_redirect', array( $this, 'start_buffer' ), PHP_INT_MIN );
		add_action( 'init', array( $this, 'start_ajax_buffer' ), 0 );
		add_filter( 'rest_pre_echo_response', array( $this, 'filter_rest_response' ), 999 );
		add_filter( 'wp_redirect', array( $this, 'filter_redirect' ), 999 );
	}

	/**
	 * Head tags and HTTP headers that name the software.
	 */
	public function remove_head_actions(): void {
		foreach ( array( 'wp_generator', 'rsd_link', 'wlwmanifest_link', 'wp_shortlink_wp_head', 'rest_output_link_wp_head' ) as $callback ) {
			$priority = has_action( 'wp_head', $callback );
			if ( false !== $priority ) {
				remove_action( 'wp_head', $callback, $priority );
			}
		}
		if ( Settings::on( 'block_feeds' ) ) {
			remove_action( 'wp_head', 'feed_links', 2 );
			remove_action( 'wp_head', 'feed_links_extra', 3 );
		}
	}

	/**
	 * /favicon.ico without a site icon: WordPress would redirect to its own logo in wp-includes.
	 */
	public function favicon(): void {
		if ( ! has_site_icon() ) {
			Request::serve_404();
		}
	}

	/**
	 * Emoji detection script and styles.
	 */
	public function remove_emoji(): void {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
		remove_action( 'admin_enqueue_scripts', 'wp_enqueue_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
		add_filter( 'emoji_svg_url', '__return_false' );
		// With no emoji address WordPress also drops its dns-prefetch hint for the emoji server.
	}

	/**
	 * oEmbed discovery links and the host script.
	 */
	public function remove_oembed_discovery(): void {
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		add_filter( 'oembed_discovery_links', '__return_empty_string' );
	}

	/**
	 * Link: headers of the REST API and shortlinks.
	 */
	public function remove_link_headers(): void {
		remove_action( 'template_redirect', 'rest_output_link_header', 11 );
		remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
	}

	/**
	 * PHP's own headers.
	 */
	public function remove_php_headers(): void {
		if ( ! headers_sent() && ! Request::is_cli() ) {
			header_remove( 'X-Powered-By' );
			header_remove( 'X-Pingback' );
		}
	}

	/**
	 * Headers sent by WordPress.
	 *
	 * @param array<string,string> $headers Headers.
	 * @return array<string,string>
	 */
	public function filter_wp_headers( $headers ): array {
		$headers = (array) $headers;
		unset( $headers['X-Pingback'], $headers['X-Powered-By'] );
		return $headers;
	}

	/**
	 * WordPress' no-cache headers carry a well-known date (Wed, 11 Jan 1984).
	 *
	 * @param array<string,string> $headers Headers.
	 * @return array<string,string>
	 */
	public function filter_nocache_headers( $headers ): array {
		$headers = (array) $headers;
		if ( isset( $headers['Expires'] ) ) {
			$headers['Expires'] = 'Thu, 01 Jan 1970 00:00:00 GMT';
		}
		return $headers;
	}

	/**
	 * Replaces "ver=<version>" with a short salted hash for visitors.
	 *
	 * @param mixed $src Asset address.
	 * @return mixed
	 */
	public function filter_asset_version( $src ) {
		if ( ! is_string( $src ) || is_admin() || ! Request::is_guest() || false === strpos( $src, 'ver=' ) ) {
			return $src;
		}
		$query = (string) wp_parse_url( $src, PHP_URL_QUERY );
		parse_str( $query, $args );
		if ( ! isset( $args['ver'] ) || ! is_string( $args['ver'] ) ) {
			return $src;
		}
		$hash = Output_Filter::hash_version( $args['ver'], (string) Settings::get( 'salt' ) );
		return add_query_arg( 'v', $hash, remove_query_arg( 'ver', $src ) );
	}

	/**
	 * Body classes that name the software or the theme (styles do not use them).
	 *
	 * @param string[] $classes Classes.
	 * @return string[]
	 */
	public function filter_body_class( $classes ): array {
		if ( ! Request::is_guest() ) {
			return (array) $classes;
		}
		return array_values(
			array_filter(
				(array) $classes,
				static function ( $class ) {
					return ! preg_match( '~^(wp-theme-|wp-child-theme-|wp-singular$|author-)~', (string) $class );
				}
			)
		);
	}

	/**
	 * Comment classes with the author's login name.
	 *
	 * @param string[] $classes Classes.
	 * @return string[]
	 */
	public function filter_comment_class( $classes ): array {
		if ( ! Request::is_guest() ) {
			return (array) $classes;
		}
		return array_values(
			array_filter(
				(array) $classes,
				static function ( $class ) {
					return 0 !== strpos( (string) $class, 'comment-author-' );
				}
			)
		);
	}

	/**
	 * Login page: the logo links to the site.
	 *
	 * @return string
	 */
	public function login_header_url(): string {
		return home_url( '/' );
	}

	/**
	 * Login page: the site name instead of "Powered by WordPress".
	 *
	 * @return string
	 */
	public function login_header_text(): string {
		return get_bloginfo( 'name' );
	}

	/**
	 * Login page title without "WordPress".
	 *
	 * @param string $login_title Full title.
	 * @param string $title       Page title.
	 * @return string
	 */
	public function login_title( $login_title, $title ): string {
		return wp_strip_all_tags( (string) $title ) . ' ‹ ' . get_bloginfo( 'name' );
	}

	/**
	 * Admin titles without "— WordPress".
	 *
	 * @param string $admin_title Title.
	 * @return string
	 */
	public function admin_title( $admin_title ): string {
		return (string) preg_replace( '~\s+(?:&#8212;|—|&mdash;)\s+WordPress$~u', '', (string) $admin_title );
	}

	/**
	 * Login page: the site icon (or the site name as text) instead of the WordPress logo.
	 */
	public function login_logo_style(): void {
		$icon = get_site_icon_url( 160 );
		if ( $icon ) {
			$css = '.login h1 a{background-image:url(' . esc_url( $icon ) . ');background-size:contain;width:80px;height:80px}';
		} else {
			$css = '.login h1 a{background:none;width:auto;height:auto;text-indent:0;font-size:24px;line-height:1.3;font-weight:600;color:#1d2327;overflow:visible}';
		}
		wp_register_style( 'login-brand', false, array(), MG_STEALTH_VERSION );
		wp_enqueue_style( 'login-brand' );
		wp_add_inline_style( 'login-brand', $css );
	}

	/**
	 * E-mails from "WordPress": the site name instead.
	 *
	 * @param string $name Sender name.
	 * @return string
	 */
	public function mail_from_name( $name ): string {
		return 'WordPress' === $name ? wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) : (string) $name;
	}

	/**
	 * E-mails without the "X-Mailer: PHPMailer" header.
	 *
	 * @param object $phpmailer PHPMailer instance.
	 */
	public function mail_x_mailer( $phpmailer ): void {
		if ( is_object( $phpmailer ) && property_exists( $phpmailer, 'XMailer' ) ) {
			$phpmailer->XMailer = ' '; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property; a single space leaves the header out.
		}
	}

	/**
	 * Output filter for front-end pages, feeds, sitemaps and robots.txt.
	 */
	public function start_buffer(): void {
		if ( is_admin() || Request::is_cli() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		if ( ! $this->has_work() ) {
			return;
		}
		ob_start( array( $this, 'filter_output' ) );
	}

	/**
	 * Output filter for AJAX answers to guests (they can carry asset addresses).
	 */
	public function start_ajax_buffer(): void {
		if ( wp_doing_ajax() && Paths::active() ) {
			ob_start( array( $this, 'filter_output' ) );
		}
	}

	/**
	 * Whether the output filter has anything to do.
	 *
	 * @return bool
	 */
	private function has_work(): bool {
		if ( Paths::active() || Access::neutral_sitemap_active() ) {
			return true;
		}
		if ( ! Request::is_guest() ) {
			return false;
		}
		$s = Settings::all();
		return ! empty( $s['clean_head'] ) || ! empty( $s['clean_ids'] ) || ! empty( $s['clean_comments'] ) || ! empty( $s['clean_versions'] );
	}

	/**
	 * Applies the neutral paths and the source cleaning to the response body.
	 *
	 * @param string $body Body.
	 * @return string
	 */
	public function filter_output( $body ) {
		if ( ! is_string( $body ) || '' === $body ) {
			return $body;
		}
		$type = $this->content_type();
		if ( '' !== $type && ! preg_match( '~(html|xml|json|javascript|css|text/plain)~i', $type ) ) {
			return $body; // Files, images and other binary answers stay as they are.
		}
		$body = $this->rewrite( $body );

		if ( Request::is_guest() && ( '' === $type || false !== stripos( $type, 'html' ) ) ) {
			$s = Settings::all();
			if ( ! empty( $s['clean_head'] ) ) {
				$body = Output_Filter::strip_head_tags( $body );
			}
			if ( ! empty( $s['clean_ids'] ) ) {
				$body = Output_Filter::strip_asset_ids( $body );
			}
			if ( ! empty( $s['clean_versions'] ) ) {
				$body = Output_Filter::neutralize_versions( $body, (string) $s['salt'] );
			}
			if ( ! empty( $s['clean_comments'] ) ) {
				$body = Output_Filter::strip_comments( $body );
				$body = Output_Filter::strip_source_urls( $body );
				$body = Output_Filter::strip_source_urls( $body );
			}
		}
		return $body;
	}

	/**
	 * Neutral paths and sitemap names in any text.
	 *
	 * @param string $body Text.
	 * @return string
	 */
	public function rewrite( string $body ): string {
		$map = Paths::active() ? (array) State::get( 'map' ) : array();
		if ( Access::neutral_sitemap_active() ) {
			$map[ Request::home_path() . 'wp-sitemap' ] = Request::home_path() . 'sitemap';
		}
		return empty( $map ) ? $body : Output_Filter::rewrite_paths( $body, $map, Paths::hosts() );
	}

	/**
	 * Redirect targets on the front end (e.g. /favicon.ico to the site icon in uploads).
	 *
	 * @param string $location Address.
	 * @return string
	 */
	public function filter_redirect( $location ): string {
		if ( is_admin() || ! is_string( $location ) || ! Paths::active() ) {
			return (string) $location;
		}
		return $this->rewrite( $location );
	}

	/**
	 * REST answers (allowed for guests, e.g. a contact form) with the neutral paths.
	 *
	 * @param mixed $result Response data.
	 * @return mixed
	 */
	public function filter_rest_response( $result ) {
		if ( ! Paths::active() ) {
			return $result;
		}
		return $this->rewrite_recursive( $result );
	}

	/**
	 * Applies rewrite() to every string in a structure.
	 *
	 * @param mixed $data Data.
	 * @return mixed
	 */
	private function rewrite_recursive( $data ) {
		if ( is_string( $data ) ) {
			return $this->rewrite( $data );
		}
		if ( is_array( $data ) ) {
			foreach ( $data as $key => $value ) {
				$data[ $key ] = $this->rewrite_recursive( $value );
			}
		}
		return $data;
	}

	/**
	 * Content-Type of the response being sent ('' when not set yet).
	 *
	 * @return string
	 */
	private function content_type(): string {
		foreach ( headers_list() as $header ) {
			if ( 0 === stripos( $header, 'content-type:' ) ) {
				return trim( substr( $header, 13 ) );
			}
		}
		return '';
	}
}
