<?php
/**
 * Neutral public paths: which real folders get which public names.
 *
 * @package MG_Stealth
 */

namespace MG_Stealth;

defined( 'ABSPATH' ) || exit;

/**
 * Path aliases. The server maps an alias back to the real folder (managed .htaccess block);
 * the output filter replaces real paths with aliases in what visitors receive.
 */
final class Paths {

	/**
	 * Real public folders relative to the home address, without slashes at the ends
	 * (e.g. 'content' => 'wp-content'), or null when one is outside the home address.
	 *
	 * @return array<string,string>
	 */
	public static function real_relative_paths(): array {
		$upload = wp_get_upload_dir();
		$urls   = array(
			'site'     => site_url( '/' ),
			'content'  => content_url( '/' ),
			'themes'   => trailingslashit( get_theme_root_uri() ),
			'plugins'  => plugins_url( '/' ),
			'mu'       => defined( 'WPMU_PLUGIN_URL' ) ? trailingslashit( WPMU_PLUGIN_URL ) : content_url( '/mu-plugins/' ),
			'uploads'  => trailingslashit( (string) ( $upload['baseurl'] ?? '' ) ),
			'includes' => includes_url( '/' ),
			'admin'    => admin_url( '/' ),
		);
		$home   = Request::home_path();
		$paths  = array();
		foreach ( $urls as $key => $url ) {
			$path = '/' . ltrim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
			if ( 0 !== strpos( $path, $home ) ) {
				continue;
			}
			$paths[ $key ] = trim( substr( $path, strlen( $home ) ), '/' );
		}
		return $paths;
	}

	/**
	 * Short per-site name for a folder (plugin or theme folder names give the plugins away).
	 *
	 * @param string $name Folder name.
	 * @return string
	 */
	public static function hashed( string $name ): string {
		return substr( hash_hmac( 'sha256', $name, (string) Settings::get( 'salt' ) ), 0, 8 );
	}

	/**
	 * Aliases for the server rules, most specific first.
	 *
	 * @return array<int,array{alias:string,real:string,type:string}>
	 */
	public static function aliases(): array {
		$real = self::real_relative_paths();
		foreach ( array( 'site', 'content', 'themes', 'plugins', 'uploads', 'includes', 'admin' ) as $needed ) {
			if ( ! isset( $real[ $needed ] ) ) {
				return array(); // A folder lives outside the home address: not supported.
			}
		}
		$s       = Settings::all();
		$prefix  = static function ( string $path ): string {
			return '' === $path ? '' : $path . '/';
		};
		$aliases = array();

		if ( ! empty( $s['hash_names'] ) ) {
			$themes = array_unique( array( get_stylesheet(), get_template() ) );
			foreach ( $themes as $theme ) {
				$aliases[] = array(
					'alias' => $s['path_themes'] . '/' . self::hashed( 'theme:' . $theme ),
					'real'  => $prefix( $real['themes'] ) . $theme,
					'type'  => 'static',
				);
			}
			foreach ( self::active_plugin_folders() as $folder ) {
				$aliases[] = array(
					'alias' => $s['path_plugins'] . '/' . self::hashed( 'plugin:' . $folder ),
					'real'  => $prefix( $real['plugins'] ) . $folder,
					'type'  => 'static',
				);
			}
			if ( isset( $real['mu'] ) ) {
				$aliases[] = array(
					'alias' => $s['path_plugins'] . '/' . self::hashed( 'mu-plugins' ),
					'real'  => $real['mu'],
					'type'  => 'static',
				);
			}
		}
		$aliases[] = array(
			'alias' => $s['path_themes'],
			'real'  => $real['themes'],
			'type'  => 'static',
		);
		$aliases[] = array(
			'alias' => $s['path_plugins'],
			'real'  => $real['plugins'],
			'type'  => 'static',
		);
		$aliases[] = array(
			'alias' => $s['path_uploads'],
			'real'  => $real['uploads'],
			'type'  => 'uploads',
		);
		$aliases[] = array(
			'alias' => $s['path_content'],
			'real'  => $real['content'],
			'type'  => 'static',
		);
		$aliases[] = array(
			'alias' => $s['path_includes'],
			'real'  => $real['includes'],
			'type'  => 'static',
		);
		$aliases[] = array(
			'alias' => $s['path_ajax'],
			'real'  => $prefix( $real['admin'] ) . 'admin-ajax.php',
			'type'  => 'file',
		);
		$aliases[] = array(
			'alias'     => $s['path_comments'],
			'real'      => $prefix( $real['site'] ) . 'wp-comments-post.php',
			'type'      => 'file',
			'post_only' => true, // A GET would answer "405 Method Not Allowed".
		);

		return array_values(
			array_filter(
				$aliases,
				static function ( $a ) {
					return '' !== $a['alias'] && '' !== $a['real'] && trim( $a['alias'], '/' ) !== trim( $a['real'], '/' );
				}
			)
		);
	}

	/**
	 * Output map (real URL path => alias URL path) for a list of aliases.
	 *
	 * @param array<int,array{alias:string,real:string,type:string}> $aliases Aliases.
	 * @return array<string,string>
	 */
	public static function output_map( array $aliases ): array {
		$home = Request::home_path();
		$map  = array();
		foreach ( $aliases as $a ) {
			$slash = 'file' === $a['type'] ? '' : '/';
			$map[ $home . trim( $a['real'], '/' ) . $slash ] = $home . trim( $a['alias'], '/' ) . $slash;
		}
		return $map;
	}

	/**
	 * Folders of the active plugins (single-file plugins have none).
	 *
	 * @return string[]
	 */
	public static function active_plugin_folders(): array {
		$folders = array();
		foreach ( (array) get_option( 'active_plugins', array() ) as $plugin ) {
			$folder = dirname( (string) $plugin );
			if ( '.' !== $folder && '' !== $folder ) {
				$folders[] = $folder;
			}
		}
		sort( $folders );
		return array_values( array_unique( $folders ) );
	}

	/**
	 * Whether the output filter replaces paths right now (enabled and verified on the server).
	 *
	 * @return bool
	 */
	public static function active(): bool {
		return Settings::on( 'paths_enabled' ) && State::get( 'paths_verified' ) && ! empty( State::get( 'map' ) );
	}

	/**
	 * Host names (with port) of the home and site addresses.
	 *
	 * @return string[]
	 */
	public static function hosts(): array {
		$hosts = array();
		foreach ( array( home_url( '/' ), site_url( '/' ) ) as $url ) {
			$host = (string) wp_parse_url( $url, PHP_URL_HOST );
			$port = wp_parse_url( $url, PHP_URL_PORT );
			if ( '' !== $host ) {
				$hosts[] = $host . ( $port ? ':' . $port : '' );
			}
		}
		return array_values( array_unique( $hosts ) );
	}

	/**
	 * The neutral REST API prefix while the paths are enabled.
	 *
	 * @return string
	 */
	public static function rest_prefix(): string {
		return Settings::on( 'paths_enabled' ) ? (string) Settings::get( 'rest_prefix' ) : '';
	}

	/**
	 * A wp-config.php snippet for a real move of the folders (instead of aliases).
	 *
	 * @return string
	 */
	public static function wp_config_snippet(): string {
		$s    = Settings::all();
		$home = untrailingslashit( home_url() );
		$out  = array(
			'/* MG Stealth: real neutral folders. Before saving, move the folders as described below. */',
			"define( 'WP_CONTENT_DIR', __DIR__ . '/" . $s['path_content'] . "' );",
			"define( 'WP_CONTENT_URL', '" . $home . '/' . $s['path_content'] . "' );",
			"define( 'WP_PLUGIN_DIR', __DIR__ . '/" . $s['path_plugins'] . "' );",
			"define( 'WP_PLUGIN_URL', '" . $home . '/' . $s['path_plugins'] . "' );",
			"define( 'WPMU_PLUGIN_DIR', __DIR__ . '/" . $s['path_content'] . "/mu-plugins' );",
			"define( 'WPMU_PLUGIN_URL', '" . $home . '/' . $s['path_content'] . "/mu-plugins' );",
			"define( 'UPLOADS', '" . $s['path_uploads'] . "' );",
		);
		return implode( "\n", $out );
	}

	/**
	 * Shell commands that go with the wp-config.php snippet.
	 *
	 * @return string
	 */
	public static function move_commands(): string {
		$s    = Settings::all();
		$real = self::real_relative_paths();
		$out  = array(
			'# ' . __( 'Back up the files and the database first. Run in the WordPress folder:', 'mg-stealth' ),
			'mv ' . ( $real['content'] ?? 'wp-content' ) . ' ' . $s['path_content'],
			'mv ' . $s['path_content'] . '/plugins ' . $s['path_plugins'],
			'mkdir -p ' . dirname( $s['path_uploads'] ) . ' && mv ' . $s['path_content'] . '/uploads ' . $s['path_uploads'],
			'wp search-replace \'/' . ( $real['uploads'] ?? 'wp-content/uploads' ) . '/\' \'/' . $s['path_uploads'] . '/\' --skip-columns=guid',
			'wp search-replace \'/' . ( $real['content'] ?? 'wp-content' ) . '/\' \'/' . $s['path_content'] . '/\' --skip-columns=guid',
		);
		return implode( "\n", $out );
	}
}
