<?php
/**
 * Settings: defaults, per-site random names, validation.
 *
 * @package MG_Stealth
 */

namespace MG_Stealth;

defined( 'ABSPATH' ) || exit;

/**
 * Stored in one option, mg_stealth_settings.
 */
final class Settings {

	const OPTION = 'mg_stealth_settings';

	/**
	 * Settings fields per tab (checkboxes are reset to false when their tab is saved without them).
	 */
	const TABS = array(
		'source'    => array( 'clean_head', 'clean_versions', 'clean_emoji', 'clean_oembed', 'clean_headers', 'clean_ids', 'clean_body_classes', 'clean_comments', 'clean_speculation', 'clean_login_branding', 'clean_mail' ),
		'paths'     => array( 'paths_enabled', 'path_content', 'path_themes', 'path_plugins', 'path_uploads', 'path_includes', 'path_ajax', 'path_comments', 'rest_prefix', 'hash_names', 'block_originals' ),
		'login'     => array( 'login_enabled', 'login_slug' ),
		'access'    => array( 'rest_guests_closed', 'rest_allow', 'block_xmlrpc', 'block_core_files', 'block_authors', 'block_feeds', 'block_cron', 'block_shortcuts', 'neutral_sitemap' ),
		'redirects' => array( 'redirect_https', 'redirect_www' ),
		'lockout'   => array( 'lockout_enabled', 'lockout_retries', 'lockout_minutes', 'lockout_escalate', 'lockout_long_hours', 'lockout_reset_hours', 'lockout_by_username', 'lockout_cloudflare', 'lockout_trusted_proxies', 'lockout_allow', 'lockout_deny', 'lockout_notify', 'lockout_anonymize' ),
	);

	/**
	 * Limits of the numeric settings: minimum, maximum.
	 */
	const NUMBERS = array(
		'lockout_retries'     => array( 1, 100 ),
		'lockout_minutes'     => array( 1, 1440 ),
		'lockout_escalate'    => array( 1, 100 ),
		'lockout_long_hours'  => array( 1, 8760 ),
		'lockout_reset_hours' => array( 1, 8760 ),
	);

	/**
	 * Name pools for the neutral paths: every install draws its own combination.
	 */
	const POOLS = array(
		'path_content'  => array( 'assets', 'static', 'res', 'public', 'site' ),
		'path_themes'   => array( 'ui', 'layout', 'skin', 'look', 'view' ),
		'path_plugins'  => array( 'ext', 'modules', 'addons', 'components', 'packs' ),
		'path_uploads'  => array( 'media', 'files', 'img', 'gallery', 'docs' ),
		'path_includes' => array( 'lib', 'common', 'shared', 'base', 'kit' ),
		'path_ajax'     => array( 'xhr', 'async', 'req', 'call', 'fetch' ),
		'path_comments' => array( 'respond', 'reply-post', 'comment-send', 'feedback-post', 'say' ),
		'rest_prefix'   => array( 'api', 'data', 'service', 'svc', 'endpoint' ),
	);

	/**
	 * Cached settings.
	 *
	 * @var array<string,mixed>|null
	 */
	private static $cache = null;

	/**
	 * Default values (random names are filled in by ensure_initialized()).
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			// Clean page source and headers.
			'clean_head'           => true,
			'clean_versions'       => true,
			'clean_emoji'          => true,
			'clean_oembed'         => true,
			'clean_headers'        => true,
			'clean_ids'            => true,
			'clean_body_classes'   => true,
			'clean_comments'       => true,
			'clean_speculation'    => true,
			'clean_login_branding' => true,
			'clean_mail'           => true,
			// Neutral paths.
			'paths_enabled'        => false,
			'path_content'         => '',
			'path_themes'          => '',
			'path_plugins'         => '',
			'path_uploads'         => '',
			'path_includes'        => '',
			'path_ajax'            => '',
			'path_comments'        => '',
			'rest_prefix'          => '',
			'hash_names'           => true,
			'block_originals'      => true,
			// Login.
			'login_enabled'        => false,
			'login_slug'           => '',
			// Access.
			'rest_guests_closed'   => true,
			'rest_allow'           => "contact-form-7\nwpforms\nfluentform\nninja-forms\nwc/store",
			'block_xmlrpc'         => true,
			'block_core_files'     => true,
			'block_authors'        => true,
			'block_feeds'          => false,
			'block_cron'           => true,
			'block_shortcuts'      => true,
			'neutral_sitemap'      => true,
			// Redirects.
			'redirect_https'       => false,
			'redirect_www'         => false,
			// Lockout after failed login attempts (defaults as in Limit Login Attempts Reloaded).
			'lockout_enabled'         => false,
			'lockout_retries'         => 4,
			'lockout_minutes'         => 20,
			'lockout_escalate'        => 4,
			'lockout_long_hours'      => 24,
			'lockout_reset_hours'     => 12,
			'lockout_by_username'     => false,
			'lockout_cloudflare'      => false,
			'lockout_trusted_proxies' => '',
			'lockout_allow'           => '',
			'lockout_deny'            => '',
			'lockout_notify'          => false,
			'lockout_anonymize'       => false,
			// Internal.
			'salt'                 => '',
			'cron_key'             => '',
		);
	}

	/**
	 * All settings.
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
	 * One setting.
	 *
	 * @param string $key Setting name.
	 * @return mixed
	 */
	public static function get( string $key ) {
		$all = self::all();
		return $all[ $key ] ?? null;
	}

	/**
	 * Whether a boolean setting is on.
	 *
	 * @param string $key Setting name.
	 * @return bool
	 */
	public static function on( string $key ): bool {
		return (bool) self::get( $key );
	}

	/**
	 * Forgets the cached settings (after an update).
	 */
	public static function flush_cache(): void {
		self::$cache = null;
	}

	/**
	 * Stores the random per-site values once: path names, salt, cron key, login address.
	 */
	public static function ensure_initialized(): void {
		$stored  = get_option( self::OPTION, null );
		$current = array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );
		$changed = ! is_array( $stored );

		foreach ( self::POOLS as $key => $pool ) {
			if ( '' !== (string) $current[ $key ] ) {
				continue;
			}
			$name = $pool[ wp_rand( 0, count( $pool ) - 1 ) ];
			if ( in_array( $key, array( 'path_themes', 'path_plugins', 'path_uploads' ), true ) ) {
				$name = $current['path_content'] . '/' . $name;
			}
			$current[ $key ] = $name;
			$changed         = true;
		}
		foreach ( array( 'salt' => 32, 'cron_key' => 20 ) as $key => $length ) {
			if ( '' === (string) $current[ $key ] ) {
				$current[ $key ] = strtolower( wp_generate_password( $length, false, false ) );
				$changed         = true;
			}
		}
		if ( '' === (string) $current['login_slug'] ) {
			$current['login_slug'] = self::random_login_slug();
			$changed               = true;
		}
		if ( $changed ) {
			update_option( self::OPTION, $current, true );
			self::flush_cache();
		}
	}

	/**
	 * A login address nobody guesses, e.g. "k3x9-panel-q8m2".
	 *
	 * @return string
	 */
	public static function random_login_slug(): string {
		$words = array( 'panel', 'desk', 'office', 'studio', 'door', 'gate', 'room' );
		return strtolower( wp_generate_password( 4, false, false ) ) . '-' . $words[ wp_rand( 0, count( $words ) - 1 ) ] . '-' . strtolower( wp_generate_password( 4, false, false ) );
	}

	/**
	 * Validates one tab of the settings form and merges it into the stored settings.
	 *
	 * @param mixed $input Submitted values.
	 * @return array<string,mixed>
	 */
	public static function sanitize( $input ): array {
		$current = self::all();
		if ( ! is_array( $input ) ) {
			return $current;
		}
		// The tab name is only a form marker; values of other tabs are left as they are.
		$tab = isset( $input['_tab'] ) ? sanitize_key( (string) $input['_tab'] ) : '';
		if ( ! isset( self::TABS[ $tab ] ) ) {
			// A full array (e.g. update_option() from code): validate every known key.
			$result = $current;
			foreach ( self::defaults() as $key => $default ) {
				if ( array_key_exists( $key, $input ) ) {
					$result[ $key ] = self::sanitize_value( $key, $input[ $key ], $current[ $key ] );
				}
			}
			return self::validate_paths( $result, $current );
		}

		$result   = $current;
		$defaults = self::defaults();
		foreach ( self::TABS[ $tab ] as $key ) {
			if ( is_bool( $defaults[ $key ] ) ) {
				$result[ $key ] = ! empty( $input[ $key ] );
			} elseif ( array_key_exists( $key, $input ) ) {
				$result[ $key ] = self::sanitize_value( $key, $input[ $key ], $current[ $key ] );
			}
		}
		return self::validate_paths( $result, $current );
	}

	/**
	 * Cleans one value.
	 *
	 * @param string $key      Setting name.
	 * @param mixed  $value    Submitted value.
	 * @param mixed  $previous Current value.
	 * @return mixed
	 */
	private static function sanitize_value( string $key, $value, $previous ) {
		$defaults = self::defaults();
		if ( is_bool( $defaults[ $key ] ) ) {
			return (bool) $value;
		}
		if ( 'rest_allow' === $key ) {
			$lines = preg_split( '~[\r\n,]+~', (string) $value );
			$clean = array();
			foreach ( (array) $lines as $line ) {
				$line = trim( strtolower( (string) $line ), " \t/" );
				if ( '' !== $line && preg_match( '~^[a-z0-9][a-z0-9._\-/]*$~', $line ) ) {
					$clean[] = $line;
				}
			}
			return implode( "\n", array_unique( $clean ) );
		}
		if ( isset( self::NUMBERS[ $key ] ) ) {
			$number = is_numeric( $value ) ? (int) $value : (int) $previous;
			return max( self::NUMBERS[ $key ][0], min( self::NUMBERS[ $key ][1], $number ) );
		}
		if ( in_array( $key, array( 'lockout_trusted_proxies', 'lockout_allow', 'lockout_deny' ), true ) ) {
			$clean = array();
			foreach ( (array) preg_split( '~[\r\n,;\s]+~', (string) $value ) as $line ) {
				$entry = self::clean_ip_entry( (string) $line );
				if ( '' !== $entry ) {
					$clean[] = $entry;
				}
			}
			return implode( "\n", array_unique( $clean ) );
		}
		if ( 'login_slug' === $key ) {
			$slug = sanitize_title( (string) $value );
			return '' === $slug ? $previous : $slug;
		}
		if ( 0 === strpos( $key, 'path_' ) || 'rest_prefix' === $key ) {
			return self::clean_path( (string) $value );
		}
		if ( in_array( $key, array( 'salt', 'cron_key' ), true ) ) {
			$v = strtolower( (string) preg_replace( '~[^A-Za-z0-9]~', '', (string) $value ) );
			return '' === $v ? $previous : $v;
		}
		return sanitize_text_field( (string) $value );
	}

	/**
	 * An IP address or CIDR range in its normal form ("192.0.2.0/24", "2001:db8::/32"), or ''.
	 *
	 * @param string $entry Address or range.
	 * @return string
	 */
	public static function clean_ip_entry( string $entry ): string {
		$entry = strtolower( trim( $entry ) );
		$bits  = null;
		if ( false !== strpos( $entry, '/' ) ) {
			list( $entry, $bits ) = explode( '/', $entry, 2 );
			if ( ! preg_match( '~^\d{1,3}$~', $bits ) ) {
				return '';
			}
			$bits = (int) $bits;
		}
		if ( false === filter_var( $entry, FILTER_VALIDATE_IP ) ) {
			return '';
		}
		$ip  = (string) inet_ntop( (string) inet_pton( $entry ) );
		$max = false !== strpos( $ip, ':' ) ? 128 : 32;
		if ( null === $bits || $bits >= $max ) {
			return $ip;
		}
		return $ip . '/' . $bits;
	}

	/**
	 * Normalises a public path: lowercase segments of letters, digits, "-" and "_".
	 *
	 * @param string $path Submitted path.
	 * @return string
	 */
	public static function clean_path( string $path ): string {
		$segments = array();
		foreach ( explode( '/', strtolower( trim( $path ) ) ) as $segment ) {
			$segment = (string) preg_replace( '~[^a-z0-9_\-]~', '', $segment );
			if ( '' !== $segment ) {
				$segments[] = $segment;
			}
		}
		return implode( '/', array_slice( $segments, 0, 3 ) );
	}

	/**
	 * Addresses WordPress itself uses for its own pages, archives and query variables: a login
	 * address there would hide them (or be hidden by them).
	 */
	const RESERVED_SLUGS = array( 'feed', 'rss', 'rss2', 'rdf', 'atom', 'comments', 'page', 'embed', 'trackback', 'author', 'category', 'tag', 'search', 'attachment', 'type', 'date', 'sitemap', 'robots', 'favicon', 'xmlrpc', 'index', 'readme', 'license' );

	/**
	 * Login addresses bots try first: allowed, but with a warning.
	 */
	const GUESSABLE_SLUGS = array( 'login', 'log-in', 'logowanie', 'signin', 'sign-in', 'admin', 'administrator', 'admin-panel', 'panel', 'panel-admin', 'dashboard', 'backend', 'cms', 'user', 'users', 'account', 'konto', 'secure', 'manage', 'manager', 'access', 'auth', 'portal', 'office', 'cpanel', 'webmaster', 'my-account', 'moje-konto' );

	/**
	 * Whether a login address has an acceptable form: at least 3 characters (letters, digits,
	 * hyphens), not a WordPress name ("wp-…") and not a WordPress reserved address.
	 *
	 * @param string $slug Login address.
	 * @return bool
	 */
	public static function is_valid_slug( string $slug ): bool {
		if ( strlen( $slug ) < 3 || strlen( $slug ) > 64 || ! preg_match( '~^[a-z0-9]+(?:-[a-z0-9]+)*$~', $slug ) ) {
			return false;
		}
		if ( 0 === strpos( $slug, 'wp-' ) || 'wp' === $slug || false !== strpos( $slug, 'wordpress' ) ) {
			return false;
		}
		return ! in_array( $slug, self::RESERVED_SLUGS, true );
	}

	/**
	 * Whether a login address is one that bots try first (short or a common word such as "login").
	 *
	 * @param string $slug Login address.
	 * @return bool
	 */
	public static function is_guessable_slug( string $slug ): bool {
		return strlen( $slug ) < 6 || in_array( $slug, self::GUESSABLE_SLUGS, true );
	}

	/**
	 * Why a login address cannot be used on this site, or '' when it can: it must not be one of
	 * the plugin's own public paths, a real file or folder in the site root, a published page,
	 * post or term, or the start of a registered route.
	 *
	 * @param string              $slug     Login address.
	 * @param array<string,mixed> $settings Settings it would be saved with.
	 * @return string
	 */
	public static function slug_conflict( string $slug, array $settings ): string {
		if ( ! self::is_valid_slug( $slug ) ) {
			/* translators: %s: login address */
			return sprintf( __( 'The login address "%s" cannot be used: use at least 3 letters, digits or hyphens, nothing starting with "wp-" and no WordPress address (feed, page, search, sitemap…).', 'mg-stealth' ), $slug );
		}
		foreach ( array_keys( self::POOLS ) as $key ) {
			$first = explode( '/', (string) ( $settings[ $key ] ?? '' ) )[0];
			if ( $slug === $first ) {
				/* translators: %s: login address */
				return sprintf( __( 'The login address "%s" is already one of the neutral paths (Paths tab); choose another one.', 'mg-stealth' ), $slug );
			}
		}
		$root = function_exists( 'get_home_path' ) ? get_home_path() : ABSPATH;
		if ( file_exists( rtrim( (string) $root, '/\\' ) . '/' . $slug ) ) {
			/* translators: %s: login address */
			return sprintf( __( 'A real file or folder "%s" exists in the site root; choose another login address.', 'mg-stealth' ), $slug );
		}
		if ( function_exists( 'get_page_by_path' ) && function_exists( 'get_post_types' ) ) {
			$post = get_page_by_path( $slug, OBJECT, array_values( get_post_types( array( 'public' => true ) ) ) );
			if ( $post && in_array( $post->post_status, array( 'publish', 'private' ), true ) ) {
				/* translators: %s: login address */
				return sprintf( __( 'A page or post with the address "%s" already exists; choose another login address or change its address.', 'mg-stealth' ), $slug );
			}
		}
		if ( function_exists( 'get_taxonomies' ) && function_exists( 'get_term_by' ) ) {
			foreach ( get_taxonomies( array( 'public' => true ) ) as $taxonomy ) {
				if ( get_term_by( 'slug', $slug, $taxonomy ) ) {
					/* translators: %s: login address */
					return sprintf( __( 'A category, tag or other term "%s" already exists; choose another login address.', 'mg-stealth' ), $slug );
				}
			}
		}
		$rules = get_option( 'rewrite_rules' );
		if ( is_array( $rules ) ) {
			$rx = '~^\^?' . preg_quote( $slug, '~' ) . '(?:/|\(|\?|\$|\\\.)~';
			foreach ( array_keys( $rules ) as $rule ) {
				if ( preg_match( $rx, (string) $rule ) ) {
					/* translators: %s: login address */
					return sprintf( __( 'The address "%s" is already used by WordPress or another plugin (a registered route); choose another login address.', 'mg-stealth' ), $slug );
				}
			}
		}
		return '';
	}

	/**
	 * Path names must differ from each other and from WordPress names; on a conflict the previous
	 * values are kept and an error is shown.
	 *
	 * @param array<string,mixed> $result   New settings.
	 * @param array<string,mixed> $previous Current settings.
	 * @return array<string,mixed>
	 */
	private static function validate_paths( array $result, array $previous ): array {
		$keys  = array( 'path_content', 'path_themes', 'path_plugins', 'path_uploads', 'path_includes', 'path_ajax', 'path_comments', 'rest_prefix' );
		$seen  = array();
		$error = '';
		foreach ( $keys as $key ) {
			$value = (string) $result[ $key ];
			if ( '' === $value ) {
				$error = __( 'Every neutral path needs a name.', 'mg-stealth' );
				break;
			}
			if ( preg_match( '~(^|/)wp-|wordpress|^(wp-admin|wp-content|wp-includes|wp-json)$~', $value ) ) {
				/* translators: %s: path name */
				$error = sprintf( __( '"%s" still sounds like WordPress; choose a neutral name.', 'mg-stealth' ), $value );
				break;
			}
			if ( isset( $seen[ $value ] ) ) {
				/* translators: %s: path name */
				$error = sprintf( __( 'The name "%s" is used twice.', 'mg-stealth' ), $value );
				break;
			}
			if ( function_exists( 'get_home_path' ) && is_dir( get_home_path() . $value ) && ! self::is_alias_of_real_path( $key, $value ) ) {
				/* translators: %s: path name */
				$error = sprintf( __( 'A real folder "%s" already exists in the site root; choose another name.', 'mg-stealth' ), $value );
				break;
			}
			$seen[ $value ] = true;
		}
		if ( '' === $error ) {
			foreach ( array( 'path_ajax', 'path_comments', 'rest_prefix' ) as $key ) {
				if ( false !== strpos( (string) $result[ $key ], '/' ) ) {
					$error = __( 'The addresses for AJAX, comments and the REST API must be a single word.', 'mg-stealth' );
					break;
				}
			}
		}
		if ( '' !== $error ) {
			if ( function_exists( 'add_settings_error' ) ) {
				add_settings_error( self::OPTION, 'mg-stealth-paths', $error );
			}
			foreach ( $keys as $key ) {
				$result[ $key ] = $previous[ $key ];
			}
		}
		return self::validate_login_slug( $result, $previous );
	}

	/**
	 * The login address must be usable on this site (see slug_conflict()); a new one that is
	 * easy to guess is saved with a warning. On a conflict the previous address is kept.
	 *
	 * @param array<string,mixed> $result   New settings.
	 * @param array<string,mixed> $previous Current settings.
	 * @return array<string,mixed>
	 */
	private static function validate_login_slug( array $result, array $previous ): array {
		$slug    = (string) $result['login_slug'];
		$old     = (string) $previous['login_slug'];
		$enabled = ! empty( $result['login_enabled'] );
		$errors  = function_exists( 'add_settings_error' );
		if ( $slug === $old ) {
			// Unchanged: never swapped silently for another address while it is in use.
			if ( ! self::is_valid_slug( $slug ) ) {
				$slug = self::random_login_slug();
			} elseif ( $enabled && $errors ) {
				$conflict = self::slug_conflict( $slug, $result );
				if ( '' !== $conflict ) {
					add_settings_error( self::OPTION, 'mg-stealth-login', $conflict, 'warning' );
				} elseif ( empty( $previous['login_enabled'] ) && self::is_guessable_slug( $slug ) ) {
					add_settings_error( self::OPTION, 'mg-stealth-login-guessable', self::guessable_warning( $slug ), 'warning' );
				}
			}
		} else {
			$conflict = self::slug_conflict( $slug, $result );
			if ( '' !== $conflict ) {
				if ( $errors ) {
					add_settings_error( self::OPTION, 'mg-stealth-login', $conflict );
				}
				$slug = self::is_valid_slug( $old ) ? $old : self::random_login_slug();
			} elseif ( $enabled && $errors && self::is_guessable_slug( $slug ) ) {
				add_settings_error( self::OPTION, 'mg-stealth-login-guessable', self::guessable_warning( $slug ), 'warning' );
			}
		}
		$result['login_slug'] = $slug;
		return $result;
	}

	/**
	 * Warning for a login address that is easy to guess.
	 *
	 * @param string $slug Login address.
	 * @return string
	 */
	public static function guessable_warning( string $slug ): string {
		/* translators: %s: login address */
		return sprintf( __( 'The login address "/%s/" is easy to guess: bots try it first, so it hides the login less than a random address. It works and wp-login.php stays the 404 page; use a strong password and brute-force protection: the lockout after failed logins (Login attempts tab), ideally two-factor authentication too.', 'mg-stealth' ), $slug );
	}

	/**
	 * Whether the given name is the folder that already holds that kind of content (for example a
	 * site whose wp-content was moved to /assets before), which is fine.
	 *
	 * @param string $key   Setting name.
	 * @param string $value Path name.
	 * @return bool
	 */
	private static function is_alias_of_real_path( string $key, string $value ): bool {
		$real = Paths::real_relative_paths();
		$map  = array(
			'path_content'  => 'content',
			'path_themes'   => 'themes',
			'path_plugins'  => 'plugins',
			'path_uploads'  => 'uploads',
			'path_includes' => 'includes',
		);
		if ( ! isset( $map[ $key ] ) ) {
			return false;
		}
		$own = trim( (string) ( $real[ $map[ $key ] ] ?? '' ), '/' );
		// The real folder itself, or a folder inside it (e.g. assets/ui while the content is /assets).
		return $own === $value || 0 === strpos( $value . '/', trim( (string) ( $real['content'] ?? '' ), '/' ) . '/' );
	}
}
