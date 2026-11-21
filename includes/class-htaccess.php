<?php
/**
 * Builds the managed .htaccess block (Apache / LiteSpeed) and edits it between its markers.
 * Pure functions without WordPress calls, so they can be unit tested on their own.
 *
 * @package MG_Stealth
 */

namespace MG_Stealth;

defined( 'ABSPATH' ) || exit;

/**
 * Server rules for Apache and LiteSpeed.
 */
final class Htaccess {

	const MARKER = 'MG Stealth';

	/**
	 * File extensions served through the theme, plugin, includes and content aliases.
	 */
	const STATIC_EXTENSIONS = 'css|js|mjs|png|jpe?g|gif|webp|avif|svg|ico|bmp|woff2?|ttf|otf|eot|mp4|webm|ogv|ogg|mp3|wav|m4a|pdf';

	/**
	 * File extensions never served through the uploads alias.
	 */
	const BLOCKED_UPLOAD_EXTENSIONS = 'php[0-9]?|phtml|phar|phps|pht|cgi|pl|py|sh|asp|aspx|jsp|htaccess|htpasswd|ini|log|sql';

	/**
	 * Builds the rules (without the BEGIN/END markers).
	 *
	 * @param array<string,mixed> $c {
	 *     Configuration.
	 *
	 *     @type string     $base        URL path of the directory with the .htaccess file, '/' or '/sub/'.
	 *     @type string     $guard_file  Absolute path of the plugin's main file: every rule applies only while it exists.
	 *     @type string     $cookie_rx   Regular expression matching the session or login-test cookie.
	 *     @type array|null $redirect    host, port, scheme, https (bool), www (bool).
	 *     @type array[]    $aliases     alias, real, type ('static', 'uploads' or 'file'), post_only (bool, files).
	 *     @type string[]   $block_trees Real paths (relative, no slashes at the ends) hidden from guests with everything below.
	 *     @type string[]   $block_dirs  Real directories (relative) hidden from guests as directories only.
	 *     @type string[]   $core_files  Real files (relative) that answer with the 404 page for everyone.
	 *     @type array|null $cron        file, key: the file answers with the 404 page unless the query has k=<key>.
	 *     @type array|null $login       file, admin, admin_allow (file names allowed for guests).
	 *     @type bool       $directory_slash_off Whether to stop mod_dir from adding the final slash.
	 * }
	 * @return string
	 */
	public static function build( array $c ): string {
		$c = array_merge(
			array(
				'base'                => '/',
				'guard_file'          => '',
				'cookie_rx'           => 'wordpress_(?:logged_in_[0-9a-f]+|test_cookie)=',
				'redirect'            => null,
				'aliases'             => array(),
				'block_trees'         => array(),
				'block_dirs'          => array(),
				'core_files'          => array(),
				'cron'                => null,
				'login'               => null,
				'directory_slash_off' => false,
			),
			$c
		);

		$r   = array();
		$r[] = '# Managed by the MG Stealth plugin: it rewrites this block, edit the settings instead.';
		$r[] = '# Emergency: rename the plugin folder (every rule below stops at once) or remove this block.';
		if ( $c['directory_slash_off'] ) {
			$r[] = '# Folders asked for without the final slash are not redirected by the server, so a probe';
			$r[] = '# cannot see which ones exist; the rules below add the slash for everything that is not hidden.';
			$r[] = '<IfModule mod_dir.c>';
			$r[] = 'DirectorySlash Off';
			$r[] = '</IfModule>';
		}
		$r[] = '<IfModule mod_rewrite.c>';
		$r[] = 'RewriteEngine On';
		$r[] = 'RewriteBase ' . $c['base'];
		$r[] = 'RewriteCond "' . str_replace( '"', '', (string) $c['guard_file'] ) . '" -f';
		$r[] = 'RewriteRule ^ - [E=MGS:1]';

		$cookie = 'RewriteCond %{HTTP_COOKIE} !(^|;\s*)' . $c['cookie_rx'] . ' [NC]';
		$first  = 'RewriteCond %{ENV:REDIRECT_STATUS} ^$';
		$on     = 'RewriteCond %{ENV:MGS} =1';

		// 1. One 301 to the main address.
		$redirect = $c['redirect'];
		if ( is_array( $redirect ) && ( ! empty( $redirect['https'] ) || ! empty( $redirect['www'] ) ) && ! empty( $redirect['host'] ) ) {
			$host     = strtolower( (string) $redirect['host'] );
			$bare     = preg_replace( '~^www\.~', '', $host );
			$is_www   = 0 === strpos( $host, 'www.' );
			$port     = empty( $redirect['port'] ) ? '' : ':' . (int) $redirect['port'];
			$scheme   = ! empty( $redirect['https'] ) ? 'https' : ( empty( $redirect['scheme'] ) ? 'https' : (string) $redirect['scheme'] );
			$r[]      = '';
			$r[]      = '# 1. One 301 to ' . $scheme . '://' . $host . $port . ' with the path and query kept. Only for this host';
			$r[]      = '#    name with or without www (other names, e.g. a local copy, are not affected). https is';
			$r[]      = '#    recognised directly or from a proxy (X-Forwarded-Proto, Cloudflare CF-Visitor);';
			$r[]      = '#    /.well-known/ stays reachable as it is (certificate renewal).';
			if ( ! empty( $redirect['https'] ) ) {
				$r[] = 'RewriteCond %{HTTPS} =on [OR]';
				$r[] = 'RewriteCond %{HTTP:X-Forwarded-Proto} ^https [NC,OR]';
				$r[] = 'RewriteCond %{HTTP:CF-Visitor} https [NC]'; // {"scheme":"https"} or {"scheme":"http"}.
				$r[] = 'RewriteRule ^ - [E=MGS_HTTPS:1]';
			}
			$r[] = $on;
			$r[] = 'RewriteCond %{REQUEST_URI} !^/\.well-known/';
			$r[] = 'RewriteCond %{HTTP_HOST} ^(www\.)?' . self::rx( (string) $bare ) . '\.?(:[0-9]+)?$ [NC]';
			$alts = array();
			if ( ! empty( $redirect['www'] ) ) {
				$alts[] = $is_www ? 'RewriteCond %{HTTP_HOST} !^www\. [NC]' : 'RewriteCond %{HTTP_HOST} ^www\. [NC]';
			}
			if ( ! empty( $redirect['https'] ) ) {
				$alts[] = 'RewriteCond %{ENV:MGS_HTTPS} !=1';
			}
			$last = count( $alts ) - 1;
			foreach ( $alts as $i => $alt ) {
				$r[] = $alt . ( $i < $last ? ' [OR]' : '' );
			}
			// Conditions with their own flags get [NC,OR]: merge the flags.
			$r   = array_map(
				static function ( $line ) {
					return str_replace( ' [NC] [OR]', ' [NC,OR]', $line );
				},
				$r
			);
			$r[] = 'RewriteRule ^ ' . $scheme . '://' . $host . $port . '%{REQUEST_URI} [R=301,L]';
		}

		// 2. Neutral public paths.
		if ( ! empty( $c['aliases'] ) ) {
			$r[] = '';
			$r[] = '# 2. Neutral public paths: the same files under other names (static files only).';
			foreach ( $c['aliases'] as $alias ) {
				$from = trim( (string) $alias['alias'], '/' );
				$to   = ltrim( (string) $alias['real'], '/' );
				$type = (string) ( $alias['type'] ?? 'static' );
				if ( '' === $from || '' === $to ) {
					continue;
				}
				$r[] = $on;
				if ( 'file' === $type ) {
					if ( ! empty( $alias['post_only'] ) ) {
						$r[] = 'RewriteCond %{REQUEST_METHOD} =POST';
					}
					$r[] = 'RewriteRule ^' . self::rx( $from ) . '$ ' . $to . ' [L]';
				} elseif ( 'uploads' === $type ) {
					$r[] = 'RewriteCond %{REQUEST_URI} !\.(' . self::BLOCKED_UPLOAD_EXTENSIONS . ')$ [NC]';
					$r[] = 'RewriteRule ^' . self::rx( $from ) . '/(.+\.[A-Za-z0-9]+)$ ' . rtrim( $to, '/' ) . '/$1 [L]';
				} else {
					$r[] = 'RewriteRule ^' . self::rx( $from ) . '/(.+\.(?:' . self::STATIC_EXTENSIONS . '))$ ' . rtrim( $to, '/' ) . '/$1 [L,NC]';
				}
			}
		}

		// 3. Hidden real paths: guests get the site's normal 404 page (WordPress' index.php).
		$hidden = array();
		foreach ( $c['block_trees'] as $tree ) {
			$hidden[] = self::rx( trim( (string) $tree, '/' ) ) . '(/|$)';
		}
		foreach ( $c['block_dirs'] as $dir ) {
			$hidden[] = self::rx( trim( (string) $dir, '/' ) ) . '/?$';
		}
		if ( ! empty( $hidden ) ) {
			$r[] = '';
			$r[] = '# 3. Real paths of WordPress: for a visitor without a session the normal 404 page.';
			$r[] = $on;
			$r[] = $first;
			$r[] = $cookie;
			$r[] = 'RewriteRule ^(' . implode( '|', $hidden ) . ') index.php [L,NC]';
		}

		$login = $c['login'];
		if ( is_array( $login ) && ! empty( $login['file'] ) ) {
			$admin = trim( (string) ( $login['admin'] ?? 'wp-admin' ), '/' );
			$r[]   = '';
			$r[]   = '# 4. Login only at the private address: the standard login file is the 404 page and the';
			$r[]   = '#    admin area is the 404 page for visitors without a session.';
			$r[]   = $on;
			$r[]   = $first;
			$r[]   = 'RewriteRule ^' . self::rx( ltrim( (string) $login['file'], '/' ) ) . '$ index.php [L,NC]';
			$r[]   = $on;
			$r[]   = $first;
			$r[]   = $cookie;
			if ( ! empty( $login['admin_allow'] ) ) {
				$r[] = 'RewriteCond %{REQUEST_URI} !/' . self::rx( $admin ) . '/(' . implode( '|', array_map( array( __CLASS__, 'rx' ), (array) $login['admin_allow'] ) ) . ')$ [NC]';
			}
			$r[] = 'RewriteRule ^' . self::rx( $admin ) . '(/|$) index.php [L,NC]';
		}

		$core = array_values( array_filter( array_map( 'strval', (array) $c['core_files'] ) ) );
		if ( ! empty( $core ) ) {
			$r[] = '';
			$r[] = '# 5. Core files a visitor never needs: the normal 404 page.';
			$r[] = $on;
			$r[] = $first;
			$r[] = 'RewriteRule ^(' . implode( '|', array_map( array( __CLASS__, 'rx' ), array_map( 'ltrim', $core, array_fill( 0, count( $core ), '/' ) ) ) ) . ')$ index.php [L,NC]';
		}

		$cron = $c['cron'];
		if ( is_array( $cron ) && ! empty( $cron['file'] ) && ! empty( $cron['key'] ) ) {
			$r[] = '';
			$r[] = '# 6. Scheduled tasks only through the site\'s own call (with its key).';
			$r[] = $on;
			$r[] = $first;
			$r[] = 'RewriteCond %{QUERY_STRING} !(^|&)k=' . self::rx( (string) $cron['key'] ) . '(&|$)';
			$r[] = 'RewriteRule ^' . self::rx( ltrim( (string) $cron['file'], '/' ) ) . '$ index.php [L,NC]';
		}

		if ( $c['directory_slash_off'] ) {
			$r[] = '';
			$r[] = '# 7. Every other real folder still gets its final slash.';
			$r[] = $on;
			$r[] = 'RewriteCond %{REQUEST_FILENAME} -d';
			$r[] = 'RewriteRule ^(.*[^/])$ ' . $c['base'] . '$1/ [R=301,L]';
		}

		$r[] = '</IfModule>';

		return implode( "\n", $r );
	}

	/**
	 * Escapes a literal for a mod_rewrite regular expression.
	 *
	 * @param string $text Literal text.
	 * @return string
	 */
	public static function rx( string $text ): string {
		return preg_quote( $text, '~' );
	}

	/**
	 * Returns the contents with the managed block removed and, when given, the new block placed at
	 * the very top (before the WordPress block, whose catch-all rule would otherwise win).
	 *
	 * @param string $contents Current .htaccess contents.
	 * @param string $rules    New rules without markers, '' to only remove the block.
	 * @return string
	 */
	public static function replace_block( string $contents, string $rules ): string {
		$without = self::remove_block( $contents );
		if ( '' === trim( $rules ) ) {
			return $without;
		}
		$block = '# BEGIN ' . self::MARKER . "\n" . rtrim( $rules ) . "\n# END " . self::MARKER . "\n";
		return $block . ( '' === trim( $without ) ? '' : "\n" . ltrim( $without ) );
	}

	/**
	 * Removes the managed block (and the blank line after it).
	 *
	 * @param string $contents Current .htaccess contents.
	 * @return string
	 */
	public static function remove_block( string $contents ): string {
		$result = preg_replace( '~^# BEGIN ' . preg_quote( self::MARKER, '~' ) . '\R.*?^# END ' . preg_quote( self::MARKER, '~' ) . '\R?(\R)?~ms', '', $contents );
		return is_string( $result ) ? $result : $contents;
	}

	/**
	 * Returns the rules inside the managed block, or null without a block.
	 *
	 * @param string $contents Current .htaccess contents.
	 * @return string|null
	 */
	public static function extract_block( string $contents ): ?string {
		if ( preg_match( '~^# BEGIN ' . preg_quote( self::MARKER, '~' ) . '\R(.*?)\R?^# END ' . preg_quote( self::MARKER, '~' ) . '~ms', $contents, $m ) ) {
			return rtrim( $m[1] );
		}
		return null;
	}
}
