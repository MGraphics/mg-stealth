<?php
/**
 * Self-check: asks the site anonymously (loopback requests, no cookies) for its pages and the
 * typical WordPress addresses, and lists what still gives WordPress away.
 *
 * @package MG_Stealth
 */

namespace MG_Stealth;

defined( 'ABSPATH' ) || exit;

/**
 * Self-check.
 */
final class Self_Check {

	/**
	 * Words searched for in the answers.
	 */
	const NEEDLES = array( 'wordpress', 'wp-content', 'wp-includes', 'wp-json', 'wp-admin', 'generator', 'ver=', 'xmlrpc', 'wp-emoji', 'api.w.org', '/author/', 'gravatar' );

	/**
	 * Runs every probe.
	 *
	 * @return array{urls:array,traces:array,headers:array,reference:array,time:int}
	 */
	public function run(): array {
		$reference = $this->fetch( '/' . strtolower( wp_generate_password( 10, false, false ) ) . '-' . wp_rand( 100, 999 ) . '/' );
		$result    = array(
			'reference' => array(
				'code'   => $reference['code'],
				'length' => strlen( $reference['body'] ),
			),
			'urls'      => array(),
			'traces'    => array(),
			'headers'   => array(),
			'time'      => time(),
		);

		foreach ( $this->probes() as $probe ) {
			$answer = $this->fetch( $probe['path'], $probe['method'] );
			$same   = $this->looks_like_404( $answer, $reference );
			if ( 'allowed' === $probe['expect'] ) {
				$verdict = 'allowed';
			} elseif ( 'open' === $probe['expect'] ) {
				$verdict = ( $answer['code'] >= 200 && $answer['code'] < 400 ) ? 'ok' : 'error';
			} elseif ( $same ) {
				$verdict = 'hidden';
			} elseif ( 404 === $answer['code'] || 403 === $answer['code'] ) {
				$verdict = 'different';
			} else {
				$verdict = 'visible';
			}
			$result['urls'][] = array(
				'label'    => $probe['label'],
				'path'     => $probe['path'],
				'method'   => $probe['method'],
				'code'     => $answer['code'],
				'location' => $answer['location'],
				'verdict'  => $verdict,
			);
		}

		foreach ( $this->pages() as $page ) {
			$answer             = $this->fetch( $page['path'] );
			$text               = $answer['raw_headers'] . "\n\n" . $answer['body'];
			$result['traces'][] = array(
				'label' => $page['label'],
				'path'  => $page['path'],
				'code'  => $answer['code'],
				'found' => Output_Filter::find_traces( $text, self::NEEDLES ),
			);
			if ( '/' === $page['path'] ) {
				$result['headers'] = $this->header_findings( $answer['headers'] );
			}
		}

		return $result;
	}

	/**
	 * Addresses probed. "hidden": should look exactly like the 404 page; "open": should work.
	 *
	 * @return array<int,array{label:string,path:string,method:string,expect:string}>
	 */
	private function probes(): array {
		$real   = Paths::real_relative_paths();
		$rest   = rest_get_url_prefix();
		$list   = array(
			array( 'login', 'wp-login.php' ),
			array( 'admin', 'wp-admin/' ),
			array( 'admin', 'wp-admin' ),
			array( 'admin', 'wp-admin/admin-ajax.php' ),
			array( 'admin', 'wp-admin/install.php' ),
			array( 'admin', 'wp-admin/css/login.min.css' ),
			array( 'xmlrpc', 'xmlrpc.php' ),
			array( 'core', 'readme.html' ),
			array( 'core', 'license.txt' ),
			array( 'core', 'wp-config-sample.php' ),
			array( 'core', 'wp-load.php' ),
			array( 'core', 'wp-cron.php' ),
			array( 'core', 'wp-signup.php' ),
			array( 'core', 'wp-activate.php' ),
			array( 'core', 'wp-trackback.php' ),
			array( 'core', 'wp-links-opml.php' ),
			array( 'core', 'wp-mail.php' ),
			array( 'core', 'wp-comments-post.php' ),
			array( 'rest', 'wp-json/' ),
			array( 'rest', 'wp-json/wp/v2/users' ),
			array( 'rest', '?rest_route=/' ),
			array( 'rest', $rest . '/' ),
			array( 'rest', $rest . '/wp/v2/users' ),
			array( 'authors', '?author=1' ),
			array( 'authors', 'author/admin/' ),
			array( 'feeds', 'feed/' ),
			array( 'feeds', '?feed=rss2' ),
			array( 'sitemap', 'wp-sitemap.xml' ),
			array( 'paths', ( $real['content'] ?? 'wp-content' ) . '/' ),
			array( 'paths', ( $real['plugins'] ?? 'wp-content/plugins' ) . '/' ),
			array( 'paths', ( $real['uploads'] ?? 'wp-content/uploads' ) . '/' ),
			array( 'paths', ( $real['includes'] ?? 'wp-includes' ) . '/' ),
			array( 'paths', ( $real['includes'] ?? 'wp-includes' ) . '/js/jquery/jquery.min.js' ),
			array( 'paths', ( $real['includes'] ?? 'wp-includes' ) . '/css/dashicons.min.css' ),
			array( 'paths', ( $real['plugins'] ?? 'wp-content/plugins' ) . '/' . dirname( MG_STEALTH_BASENAME ) . '/readme.txt' ),
			array( 'shortcuts', 'login' ),
			array( 'shortcuts', 'admin' ),
			array( 'shortcuts', 'dashboard' ),
		);
		$probes = array();
		foreach ( $list as $item ) {
			$probes[] = array(
				'label'  => $item[0],
				'path'   => '/' . ltrim( $item[1], '/' ),
				'method' => 'GET',
				'expect' => ( 'feeds' === $item[0] && ! Settings::on( 'block_feeds' ) ) ? 'allowed' : 'hidden',
			);
		}
		$probes[] = array(
			'label'  => 'xmlrpc',
			'path'   => '/xmlrpc.php',
			'method' => 'POST',
			'expect' => 'hidden',
		);
		if ( Login::enabled() ) {
			$probes[] = array(
				'label'  => 'private-login',
				'path'   => '/' . user_trailingslashit( (string) Settings::get( 'login_slug' ) ),
				'method' => 'GET',
				'expect' => 'open',
			);
		}
		if ( Access::neutral_sitemap_active() ) {
			$probes[] = array(
				'label'  => 'sitemap',
				'path'   => '/sitemap.xml',
				'method' => 'GET',
				'expect' => 'open',
			);
		}
		$probes[] = array(
			'label'  => 'home',
			'path'   => '/',
			'method' => 'GET',
			'expect' => 'open',
		);
		return $probes;
	}

	/**
	 * Pages searched for traces.
	 *
	 * @return array<int,array{label:string,path:string}>
	 */
	private function pages(): array {
		$pages = array(
			array(
				'label' => __( 'Home page', 'mg-stealth' ),
				'path'  => '/',
			),
		);
		$labels = array(
			'post' => __( 'Newest post', 'mg-stealth' ),
			'page' => __( 'Newest page', 'mg-stealth' ),
		);
		foreach ( $labels as $type => $label ) {
			$ids = get_posts(
				array(
					'post_type'      => $type,
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'has_password'   => false,
				)
			);
			if ( ! empty( $ids ) ) {
				$pages[] = array(
					'label' => $label,
					'path'  => $this->relative( (string) get_permalink( (int) $ids[0] ) ),
				);
			}
		}
		$pages[] = array(
			'label' => __( '404 page', 'mg-stealth' ),
			'path'  => '/' . strtolower( wp_generate_password( 8, false, false ) ) . '/',
		);
		$pages[] = array(
			'label' => 'robots.txt',
			'path'  => '/robots.txt',
		);
		if ( ! Settings::on( 'block_feeds' ) ) {
			$pages[] = array(
				'label' => __( 'RSS feed', 'mg-stealth' ),
				'path'  => '/' . ( get_option( 'permalink_structure' ) ? 'feed/' : '?feed=rss2' ),
			);
		}
		if ( Access::neutral_sitemap_active() ) {
			$pages[] = array(
				'label' => __( 'Sitemap', 'mg-stealth' ),
				'path'  => '/sitemap.xml',
			);
		}
		return $pages;
	}

	/**
	 * Path and query of an address relative to the home address, with a leading slash.
	 *
	 * @param string $url Address.
	 * @return string
	 */
	private function relative( string $url ): string {
		$home = untrailingslashit( home_url() );
		return 0 === strpos( $url, $home ) ? '/' . ltrim( substr( $url, strlen( $home ) ), '/' ) : '/';
	}

	/**
	 * Anonymous request.
	 *
	 * @param string $path   Path relative to the home address.
	 * @param string $method HTTP method.
	 * @return array{code:int,location:string,body:string,headers:array,raw_headers:string}
	 */
	private function fetch( string $path, string $method = 'GET' ): array {
		$url      = untrailingslashit( home_url() ) . $path;
		$args     = array( 'method' => $method );
		if ( 'POST' === $method ) {
			$args['body']    = '<?xml version="1.0"?><methodCall><methodName>system.listMethods</methodName></methodCall>';
			$args['headers'] = array( 'Content-Type' => 'text/xml' );
		}
		$response = Server_Rules::loopback( $url, $args );
		if ( is_wp_error( $response ) ) {
			return array(
				'code'        => 0,
				'location'    => $response->get_error_message(),
				'body'        => '',
				'headers'     => array(),
				'raw_headers' => '',
			);
		}
		$headers = wp_remote_retrieve_headers( $response );
		$list    = array();
		$raw     = '';
		foreach ( $headers as $name => $value ) {
			$list[ strtolower( (string) $name ) ] = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
			$raw                                .= $name . ': ' . $list[ strtolower( (string) $name ) ] . "\n";
		}
		return array(
			'code'        => (int) wp_remote_retrieve_response_code( $response ),
			'location'    => (string) ( $list['location'] ?? '' ),
			'body'        => (string) wp_remote_retrieve_body( $response ),
			'headers'     => $list,
			'raw_headers' => $raw,
		);
	}

	/**
	 * Whether an answer is the site's normal 404 page (same status, about the same size: the page
	 * may repeat the requested address).
	 *
	 * @param array $answer    Answer.
	 * @param array $reference The 404 page of a random address.
	 * @return bool
	 */
	private function looks_like_404( array $answer, array $reference ): bool {
		if ( $answer['code'] !== $reference['code'] || 404 !== $answer['code'] ) {
			return false;
		}
		$a = strlen( $answer['body'] );
		$b = strlen( $reference['body'] );
		return abs( $a - $b ) <= max( 120, (int) ( 0.02 * $b ) );
	}

	/**
	 * Headers of the home page that name the software.
	 *
	 * @param array<string,string> $headers Headers.
	 * @return array<int,string>
	 */
	private function header_findings( array $headers ): array {
		$found = array();
		foreach ( array( 'x-powered-by', 'x-pingback', 'x-redirect-by', 'x-generator' ) as $name ) {
			if ( isset( $headers[ $name ] ) ) {
				$found[] = $name . ': ' . $headers[ $name ];
			}
		}
		if ( isset( $headers['link'] ) && false !== strpos( $headers['link'], 'api.w.org' ) ) {
			$found[] = 'link: ' . $headers['link'];
		}
		if ( isset( $headers['expires'] ) && false !== strpos( $headers['expires'], '1984' ) ) {
			$found[] = 'expires: ' . $headers['expires'];
		}
		return $found;
	}
}
