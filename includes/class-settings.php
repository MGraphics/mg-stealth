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
		if ( 'login_slug' === $key ) {
			$slug = sanitize_title( (string) $value );
			return self::is_valid_slug( $slug ) ? $slug : $previous;
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
	 * Whether a login address is acceptable (not empty, not a WordPress name, not too short).
	 *
	 * @param string $slug Login address.
	 * @return bool
	 */
	public static function is_valid_slug( string $slug ): bool {
		if ( strlen( $slug ) < 6 || 0 === strpos( $slug, 'wp-' ) ) {
			return false;
		}
		return ! in_array( $slug, array( 'login', 'admin', 'dashboard', 'wp-admin', 'wp-login', 'panel', 'administrator', 'signin', 'log-in' ), true );
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
		if ( ! empty( $result['login_enabled'] ) && ! self::is_valid_slug( (string) $result['login_slug'] ) ) {
			$result['login_slug'] = self::random_login_slug();
		}
		return $result;
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
