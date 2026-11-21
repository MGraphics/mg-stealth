<?php
/**
 * Text transformations of the final response body. Pure functions without WordPress calls,
 * so they can be unit tested on their own.
 *
 * @package MG_Stealth
 */

namespace MG_Stealth;

defined( 'ABSPATH' ) || exit;

/**
 * Rewrites paths and removes tell-tale markup from HTML, XML, JSON and CSS output.
 */
final class Output_Filter {

	/**
	 * Replaces real public paths with their aliases.
	 *
	 * The map holds absolute URL paths: '/wp-content/plugins/' => '/assets/ext/'. Directory entries
	 * end with a slash, file entries (e.g. '/wp-admin/admin-ajax.php') do not. Matches are replaced
	 * after one of the given hosts (absolute and protocol-relative URLs) and after an attribute or
	 * CSS delimiter (root-relative URLs), both plain and JSON-escaped ("\/wp-content\/").
	 *
	 * @param string               $body  Response body.
	 * @param array<string,string> $map   Real path => alias path.
	 * @param string[]             $hosts Host names with an optional port, e.g. 'example.com:8080'.
	 * @return string
	 */
	public static function rewrite_paths( string $body, array $map, array $hosts ): string {
		if ( '' === $body || empty( $map ) ) {
			return $body;
		}

		$absolute = array();
		$relative = array();
		foreach ( $map as $real => $alias ) {
			$real  = (string) $real;
			$alias = (string) $alias;
			if ( '' === $real || $real === $alias ) {
				continue;
			}
			$real_escaped  = str_replace( '/', '\\/', $real );
			$alias_escaped = str_replace( '/', '\\/', $alias );
			foreach ( $hosts as $host ) {
				$absolute[ '//' . $host . $real ]                       = '//' . $host . $alias;
				$absolute[ '\\/\\/' . $host . $real_escaped ]           = '\\/\\/' . $host . $alias_escaped;
				$absolute[ '%2F%2F' . $host . self::url_encode_path( $real ) ] = '%2F%2F' . $host . self::url_encode_path( $alias );
			}
			$relative[ $real ]         = $alias;
			$relative[ $real_escaped ] = $alias_escaped;
		}
		if ( empty( $relative ) ) {
			return $body;
		}

		// strtr() tries the longest keys first, so specific entries win over their parents.
		$body = strtr( $body, $absolute );

		$keys = array_keys( $relative );
		usort(
			$keys,
			static function ( $a, $b ) {
				return strlen( (string) $b ) - strlen( (string) $a );
			}
		);
		$pattern = '~(^|["\'(=\s,])(' . implode(
			'|',
			array_map(
				static function ( $key ) {
					return preg_quote( (string) $key, '~' );
				},
				$keys
			)
		) . ')~';

		$result = preg_replace_callback(
			$pattern,
			static function ( array $m ) use ( $relative ) {
				return $m[1] . $relative[ $m[2] ];
			},
			$body
		);

		return is_string( $result ) ? $result : $body;
	}

	/**
	 * Percent-encodes the slashes of a path (as in redirect_to=…%2Fwp-content%2F…).
	 *
	 * @param string $path URL path.
	 * @return string
	 */
	private static function url_encode_path( string $path ): string {
		return str_replace( '/', '%2F', $path );
	}

	/**
	 * Removes HTML comments outside <script>, <style>, <textarea> and <pre>; keeps conditional
	 * comments (<!--[if IE]>) and empty-looking comments that some code relies on are not special.
	 *
	 * @param string $html HTML document.
	 * @return string
	 */
	public static function strip_comments( string $html ): string {
		if ( false === strpos( $html, '<!--' ) ) {
			return $html;
		}
		$parts = preg_split( '~(<(script|style|textarea|pre)\b[^>]*>.*?</\2\s*>)~is', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( ! is_array( $parts ) ) {
			return $html;
		}
		$out   = '';
		$count = count( $parts );
		for ( $i = 0; $i < $count; $i++ ) {
			$part = $parts[ $i ];
			if ( preg_match( '~^<(script|style|textarea|pre)\b~i', $part ) ) {
				$out .= $part;
				++$i; // Skip the captured tag name.
				continue;
			}
			$clean = preg_replace( '~<!--(?!\[if|<!|>)(?:(?!-->).)*-->[ \t]*(\r?\n)?~s', '', $part );
			$out  .= is_string( $clean ) ? $clean : $part;
		}
		return $out;
	}

	/**
	 * Removes the "sourceURL" comments WordPress adds to inline scripts and styles
	 * ("//# sourceURL=contact-form-7-js-before"): they name the handles of the scripts.
	 *
	 * @param string $html HTML document.
	 * @return string
	 */
	public static function strip_source_urls( string $html ): string {
		if ( false === strpos( $html, 'sourceURL=' ) ) {
			return $html;
		}
		$result = preg_replace( array( '~\R?[ \t]*//# sourceURL=[^\r\n<]*~', '~\R?[ \t]*/\*# sourceURL=[^*]*\*/~' ), '', $html );
		return is_string( $result ) ? $result : $html;
	}

	/**
	 * Removes the WordPress-style id attributes of asset tags: id="name-css", "name-inline-css",
	 * "name-js", "name-js-extra", "name-js-before", "name-js-after", "name-js-translations",
	 * "name-js-module", "name-js-modulepreload".
	 *
	 * @param string $html HTML document.
	 * @return string
	 */
	public static function strip_asset_ids( string $html ): string {
		$result = preg_replace_callback(
			'~<(?:link|style|script)\b[^>]*>~i',
			static function ( array $m ) {
				$tag = preg_replace( '~\s+id=(["\'])[^"\']*?-(?:css|inline-css|js|js-extra|js-before|js-after|js-translations|js-module|js-modulepreload)\1~i', '', $m[0] );
				return is_string( $tag ) ? $tag : $m[0];
			},
			$html
		);
		return is_string( $result ) ? $result : $html;
	}

	/**
	 * Removes head tags that name the software: generator meta tags (of any plugin), RSD,
	 * wlwmanifest, shortlink, pingback, the REST API discovery link and oEmbed discovery links.
	 *
	 * @param string $html HTML document.
	 * @return string
	 */
	public static function strip_head_tags( string $html ): string {
		$patterns = array(
			'~<meta\s[^>]*name=(["\'])generator\1[^>]*>[ \t]*(\r?\n)?~i',
			'~<link\s[^>]*rel=(["\'])(?:https://api\.w\.org/|EditURI|wlwmanifest|shortlink|pingback)\1[^>]*>[ \t]*(\r?\n)?~i',
			'~<link\s[^>]*type=(["\'])(?:application/json\+oembed|text/xml\+oembed|application/xml\+oembed)\1[^>]*>[ \t]*(\r?\n)?~i',
		);
		$result = preg_replace( $patterns, '', $html );
		return is_string( $result ) ? $result : $html;
	}

	/**
	 * Replaces "ver=<version>" in asset URLs with a short salted hash ("v=1a2b3c4d"): browsers still
	 * get a new address after an update, but the address no longer tells which version is installed.
	 *
	 * @param string $body Response body.
	 * @param string $salt Per-site secret.
	 * @return string
	 */
	public static function neutralize_versions( string $body, string $salt ): string {
		if ( false === stripos( $body, 'ver=' ) ) {
			return $body;
		}
		$result = preg_replace_callback(
			'~(\.(?:css|js|mjs)\?(?:[^"\'\s<>]*?(?:&amp;|&#038;|&|\\\\u0026))?)ver=([A-Za-z0-9._\-]+)~i',
			static function ( array $m ) use ( $salt ) {
				return $m[1] . 'v=' . self::hash_version( $m[2], $salt );
			},
			$body
		);
		return is_string( $result ) ? $result : $body;
	}

	/**
	 * Short salted hash of a version string.
	 *
	 * @param string $version Version.
	 * @param string $salt    Per-site secret.
	 * @return string
	 */
	public static function hash_version( string $version, string $salt ): string {
		return substr( md5( $salt . '|' . $version ), 0, 8 );
	}

	/**
	 * Counts case-insensitive occurrences of each needle and returns a few short excerpts.
	 *
	 * @param string   $body    Text to search.
	 * @param string[] $needles Needles.
	 * @param int      $limit   Excerpts per needle.
	 * @return array<string,array{count:int,excerpts:string[]}>
	 */
	public static function find_traces( string $body, array $needles, int $limit = 3 ): array {
		$found = array();
		foreach ( $needles as $needle ) {
			$count = substr_count( strtolower( $body ), strtolower( $needle ) );
			if ( 0 === $count ) {
				continue;
			}
			$excerpts = array();
			$offset   = 0;
			while ( count( $excerpts ) < $limit ) {
				$pos = stripos( $body, $needle, $offset );
				if ( false === $pos ) {
					break;
				}
				$start      = max( 0, $pos - 50 );
				$excerpts[] = trim( (string) preg_replace( '~\s+~', ' ', substr( $body, $start, strlen( $needle ) + 100 ) ) );
				$offset     = $pos + strlen( $needle );
			}
			$found[ $needle ] = array(
				'count'    => $count,
				'excerpts' => $excerpts,
			);
		}
		return $found;
	}
}
