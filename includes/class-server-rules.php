<?php
/**
 * Writes, verifies and removes the managed .htaccess block.
 *
 * @package MG_Stealth
 */

namespace MG_Stealth;

defined( 'ABSPATH' ) || exit;

/**
 * Server rules lifecycle.
 */
final class Server_Rules {

	/**
	 * Whether the server reads .htaccess files (Apache, LiteSpeed).
	 *
	 * @return bool
	 */
	public static function supported(): bool {
		global $is_apache;
		if ( $is_apache ) {
			return true;
		}
		// WP-CLI knows no server software: an existing .htaccess means Apache or LiteSpeed.
		return Request::is_cli() && is_file( self::file() );
	}

	/**
	 * Absolute path of the site's .htaccess file.
	 *
	 * @return string
	 */
	public static function file(): string {
		if ( ! function_exists( 'get_home_path' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		return get_home_path() . '.htaccess';
	}

	/**
	 * Configuration for Htaccess::build() from the current settings.
	 *
	 * @param bool $with_paths Whether to include the path aliases.
	 * @return array<string,mixed>
	 */
	public static function config( bool $with_paths ): array {
		$s    = Settings::all();
		$real = Paths::real_relative_paths();
		$pre  = static function ( string $key, string $file ) use ( $real ): string {
			$dir = (string) ( $real[ $key ] ?? '' );
			return ( '' === $dir ? '' : $dir . '/' ) . $file;
		};
		$c    = array(
			'base'       => Request::home_path(),
			'guard_file' => wp_normalize_path( MG_STEALTH_FILE ),
			'cookie_rx'  => '(?:' . preg_quote( LOGGED_IN_COOKIE, '~' ) . '|' . preg_quote( TEST_COOKIE, '~' ) . ')=',
		);

		if ( ! empty( $s['redirect_https'] ) || ! empty( $s['redirect_www'] ) ) {
			$c['redirect'] = self::redirect_config();
		}

		$with_paths = $with_paths && ! empty( $s['paths_enabled'] );
		if ( $with_paths ) {
			$c['aliases'] = Paths::aliases();
			if ( ! empty( $s['block_originals'] ) && isset( $real['content'], $real['includes'] ) ) {
				$trees = array( $real['includes'] );
				foreach ( array( 'themes', 'plugins', 'mu' ) as $key ) {
					if ( isset( $real[ $key ] ) && '' !== $real[ $key ] ) {
						$trees[] = $real[ $key ];
					}
				}
				foreach ( array( 'languages', 'upgrade', 'upgrade-temp-backup', 'cache', 'wflogs', 'ai1wm-backups', 'updraft' ) as $folder ) {
					$trees[] = $real['content'] . '/' . $folder;
				}
				$c['block_trees'] = array_values( array_unique( array_filter( $trees ) ) );
				$c['block_dirs']  = array_values( array_filter( array( $real['content'], $real['uploads'] ?? '' ) ) );
				$c['core_files']  = array( $pre( 'site', 'wp-comments-post.php' ) );
				if ( ! empty( $s['login_enabled'] ) ) {
					// admin-ajax.php is reached through its alias.
					$c['login_admin_allow'] = array( 'admin-post.php' );
				}
			}
		}

		$core = array();
		if ( ! empty( $s['block_core_files'] ) ) {
			$core = array( 'readme.html', 'license.txt', 'licencja.txt', 'wp-config-sample.php', 'wp-config.php', 'wp-load.php', 'wp-settings.php', 'wp-blog-header.php', 'wp-links-opml.php', 'wp-trackback.php', 'wp-mail.php' );
			if ( ! is_multisite() ) {
				$core[] = 'wp-signup.php';
				$core[] = 'wp-activate.php';
			}
			$core = array_map(
				static function ( $file ) use ( $pre ) {
					return $pre( 'site', $file );
				},
				$core
			);
		}
		if ( ! empty( $s['block_xmlrpc'] ) ) {
			$core[] = $pre( 'site', 'xmlrpc.php' );
		}
		$c['core_files'] = array_values( array_unique( array_merge( $c['core_files'] ?? array(), $core ) ) );

		if ( ! empty( $s['block_cron'] ) && '' !== (string) $s['cron_key'] ) {
			$c['cron'] = array(
				'file' => $pre( 'site', 'wp-cron.php' ),
				'key'  => (string) $s['cron_key'],
			);
		}

		if ( ! empty( $s['login_enabled'] ) && ! is_multisite() ) {
			$allow      = $c['login_admin_allow'] ?? array( 'admin-ajax.php', 'admin-post.php' );
			$c['login'] = array(
				'file'        => $pre( 'site', 'wp-login.php' ),
				'admin'       => $real['admin'] ?? 'wp-admin',
				'admin_allow' => $allow,
			);
		}
		unset( $c['login_admin_allow'] );

		$c['directory_slash_off'] = ! empty( $c['login'] ) || ! empty( $c['block_trees'] );

		return $c;
	}

	/**
	 * Main address for the redirect rules: the host and scheme of the home address.
	 *
	 * @return array<string,mixed>
	 */
	public static function redirect_config(): array {
		$home = home_url( '/' );
		return array(
			'host'   => strtolower( (string) wp_parse_url( $home, PHP_URL_HOST ) ),
			'port'   => (int) wp_parse_url( $home, PHP_URL_PORT ),
			'scheme' => (string) wp_parse_url( $home, PHP_URL_SCHEME ),
			'https'  => Settings::on( 'redirect_https' ) && 'https' === wp_parse_url( $home, PHP_URL_SCHEME ),
			'www'    => Settings::on( 'redirect_www' ),
		);
	}

	/**
	 * The rules the site needs right now ('' when nothing needs the server).
	 *
	 * @param bool $with_paths Whether to include the path aliases.
	 * @return string
	 */
	public static function desired( bool $with_paths = true ): string {
		$c = self::config( $with_paths );
		if ( empty( $c['redirect'] ) && empty( $c['aliases'] ) && empty( $c['block_trees'] ) && empty( $c['core_files'] ) && empty( $c['cron'] ) && empty( $c['login'] ) ) {
			return '';
		}
		return Htaccess::build( $c );
	}

	/**
	 * Reads the .htaccess file ('' when missing).
	 *
	 * @return string
	 */
	public static function read(): string {
		$file = self::file();
		if ( ! is_readable( $file ) ) {
			return '';
		}
		$fs = self::filesystem();
		if ( $fs ) {
			return (string) $fs->get_contents( $file );
		}
		return '';
	}

	/**
	 * Direct filesystem access, or null (e.g. FTP-only hosting).
	 *
	 * @return \WP_Filesystem_Base|null
	 */
	private static function filesystem() {
		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( 'direct' !== get_filesystem_method( array(), dirname( self::file() ) ) ) {
			return null;
		}
		if ( ! $wp_filesystem ) {
			WP_Filesystem();
		}
		return $wp_filesystem ? $wp_filesystem : null;
	}

	/**
	 * Writes the .htaccess contents.
	 *
	 * @param string $contents New contents.
	 * @return bool
	 */
	private static function write( string $contents ): bool {
		$file = self::file();
		$fs   = self::filesystem();
		if ( ! $fs ) {
			return false;
		}
		if ( file_exists( $file ) ? ! $fs->is_writable( $file ) : ! $fs->is_writable( dirname( $file ) ) ) {
			return false;
		}
		return (bool) $fs->put_contents( $file, $contents, FS_CHMOD_FILE );
	}

	/**
	 * Brings the managed block in line with the settings and verifies it. When the neutral paths
	 * do not answer, the block is written without them and the original paths stay in use.
	 *
	 * @return array{status:string,error:string}
	 */
	public static function sync(): array {
		if ( defined( 'MG_STEALTH_DISABLE' ) && MG_STEALTH_DISABLE ) {
			self::remove();
			return array(
				'status' => 'none',
				'error'  => '',
			);
		}
		if ( ! self::supported() ) {
			State::update(
				array(
					'rules_status'   => 'manual',
					'rules_error'    => __( 'This server does not read .htaccess files (for example nginx). Rules that need the server are not active.', 'mg-stealth' ),
					'paths_verified' => false,
					'map'            => array(),
				)
			);
			return array(
				'status' => 'manual',
				'error'  => (string) State::get( 'rules_error' ),
			);
		}

		$aliases = Settings::on( 'paths_enabled' ) ? Paths::aliases() : array();
		$result  = self::apply( self::desired( true ) );
		$state   = array(
			'rules_status'   => $result['status'],
			'rules_error'    => $result['error'],
			'paths_verified' => false,
			'map'            => array(),
			'checked_at'     => time(),
			'site'           => State::site_fingerprint(),
		);

		if ( 'written' === $result['status'] && ! empty( $aliases ) ) {
			if ( self::verify_aliases( $aliases ) ) {
				$state['paths_verified'] = true;
				$state['map']            = Paths::output_map( $aliases );
			} else {
				$fallback             = self::apply( self::desired( false ) );
				$state['rules_status'] = $fallback['status'];
				$state['rules_error']  = __( 'The neutral paths do not answer on this server, so they were left out of the rules and the original paths stay in use. See the help tab.', 'mg-stealth' );
			}
		} elseif ( 'manual' === $result['status'] && ! empty( $aliases ) && State::get( 'paths_verified' ) && self::verify_aliases( $aliases ) ) {
			// Rules copied by hand: keep using them while they answer.
			$state['paths_verified'] = true;
			$state['map']            = Paths::output_map( $aliases );
		}

		State::update( $state );
		Request::forget_404_page();
		return array(
			'status' => (string) $state['rules_status'],
			'error'  => (string) $state['rules_error'],
		);
	}

	/**
	 * Re-checks hand-copied rules (the "check again" button).
	 *
	 * @return bool Whether the neutral paths answer.
	 */
	public static function recheck_manual(): bool {
		$aliases = Settings::on( 'paths_enabled' ) ? Paths::aliases() : array();
		$ok      = ! empty( $aliases ) && self::verify_aliases( $aliases );
		State::update(
			array(
				'paths_verified' => $ok,
				'map'            => $ok ? Paths::output_map( $aliases ) : array(),
				'checked_at'     => time(),
				'site'           => State::site_fingerprint(),
			)
		);
		return $ok;
	}

	/**
	 * Writes the block when it differs and checks that the site still answers.
	 *
	 * @param string $rules Rules ('' removes the block).
	 * @return array{status:string,error:string}
	 */
	private static function apply( string $rules ): array {
		$before  = self::read();
		$current = Htaccess::extract_block( $before );
		if ( ( null === $current && '' === $rules ) || (string) $current === $rules ) {
			return array(
				'status' => '' === $rules ? 'none' : 'written',
				'error'  => '',
			);
		}
		if ( ! self::write( Htaccess::replace_block( $before, $rules ) ) ) {
			return array(
				'status' => '' === $rules ? 'none' : 'manual',
				'error'  => __( 'The .htaccess file is not writable. Copy the rules below into it by hand, at the very top.', 'mg-stealth' ),
			);
		}
		if ( '' !== $rules && ! self::site_answers() ) {
			self::write( $before );
			return array(
				'status' => 'failed',
				'error'  => __( 'After writing the rules the site stopped answering (server error), so the previous .htaccess was restored. The server probably does not allow some directives; see the help tab.', 'mg-stealth' ),
			);
		}
		return array(
			'status' => '' === $rules ? 'none' : 'written',
			'error'  => '',
		);
	}

	/**
	 * Removes the managed block (deactivation, uninstall, emergency constant).
	 */
	public static function remove(): void {
		$before = self::read();
		if ( null !== Htaccess::extract_block( $before ) ) {
			self::write( Htaccess::remove_block( $before ) );
		}
		State::update(
			array(
				'rules_status'   => 'none',
				'rules_hash'     => '',
				'paths_verified' => false,
				'map'            => array(),
			)
		);
	}

	/**
	 * Whether the home page answers without a server error (anonymous loopback request).
	 *
	 * @return bool
	 */
	public static function site_answers(): bool {
		$response = self::loopback( home_url( '/' ) );
		if ( is_wp_error( $response ) ) {
			// A host that blocks loopback requests: cannot tell, assume the rules are fine.
			return true;
		}
		return (int) wp_remote_retrieve_response_code( $response ) < 500;
	}

	/**
	 * Whether the aliases answer: the plugin's own stylesheet through its neutral address, and a
	 * WordPress core file through the includes alias.
	 *
	 * @param array<int,array{alias:string,real:string,type:string}> $aliases Aliases.
	 * @return bool
	 */
	public static function verify_aliases( array $aliases ): bool {
		$map     = Paths::output_map( $aliases );
		$plugins = plugins_url( 'assets/admin.css', MG_STEALTH_FILE );
		$hosts   = Paths::hosts();
		$url     = Output_Filter::rewrite_paths( $plugins, $map, $hosts );
		if ( $url === $plugins ) {
			return false;
		}
		$response = self::loopback( $url );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}
		if ( false === strpos( (string) wp_remote_retrieve_body( $response ), 'mg-stealth-admin' ) ) {
			return false;
		}
		$core     = Output_Filter::rewrite_paths( includes_url( 'css/dashicons.min.css' ), $map, $hosts );
		$response = self::loopback( $core );
		return ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response );
	}

	/**
	 * Anonymous request to the site itself.
	 *
	 * @param string $url Address.
	 * @param array  $args Extra arguments.
	 * @return array|\WP_Error
	 */
	public static function loopback( string $url, array $args = array() ) {
		return wp_remote_request(
			$url,
			array_merge(
				array(
					'method'      => 'GET',
					'timeout'     => 10,
					'redirection' => 0,
					'sslverify'   => apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter for loopback requests.
					'cookies'     => array(),
					'headers'     => array( 'Cache-Control' => 'no-cache' ),
				),
				$args
			)
		);
	}
}
