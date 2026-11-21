<?php
/**
 * Redirect decision tests (the PHP twin of the .htaccess redirect rules).
 *
 * @package MG_Stealth
 */

use MG_Stealth\Redirect_Rules;
use PHPUnit\Framework\TestCase;

final class RedirectRulesTest extends TestCase {

	private const BARE = array(
		'host'   => 'example.com',
		'port'   => 0,
		'scheme' => 'https',
		'https'  => true,
		'www'    => true,
	);

	/**
	 * @return array<string,array{0:array,1:?string}>
	 */
	public static function cases(): array {
		$q = '/kontakt?utm_source=fb&x=a%20b';
		return array(
			'http bare'                    => array( array( 'HTTP_HOST' => 'example.com', 'REQUEST_URI' => $q ), 'https://example.com' . $q ),
			'http www'                     => array( array( 'HTTP_HOST' => 'www.example.com', 'REQUEST_URI' => $q ), 'https://example.com' . $q ),
			'https www'                    => array( array( 'HTTP_HOST' => 'www.example.com', 'REQUEST_URI' => '/', 'HTTPS' => 'on' ), 'https://example.com/' ),
			'https bare stays'             => array( array( 'HTTP_HOST' => 'example.com', 'REQUEST_URI' => $q, 'HTTPS' => 'on' ), null ),
			'upper case host'              => array( array( 'HTTP_HOST' => 'WWW.Example.COM', 'REQUEST_URI' => '/a' ), 'https://example.com/a' ),
			'host with port and dot'       => array( array( 'HTTP_HOST' => 'example.com.:80', 'REQUEST_URI' => '/a' ), 'https://example.com/a' ),
			'proxy https bare stays'       => array( array( 'HTTP_HOST' => 'example.com', 'REQUEST_URI' => '/a', 'HTTP_X_FORWARDED_PROTO' => 'https' ), null ),
			'proxy list https'             => array( array( 'HTTP_HOST' => 'example.com', 'REQUEST_URI' => '/a', 'HTTP_X_FORWARDED_PROTO' => 'https, http' ), null ),
			'proxy https www'              => array( array( 'HTTP_HOST' => 'www.example.com', 'REQUEST_URI' => '/a', 'HTTP_X_FORWARDED_PROTO' => 'https' ), 'https://example.com/a' ),
			'cloudflare https'             => array( array( 'HTTP_HOST' => 'example.com', 'REQUEST_URI' => '/a', 'HTTP_CF_VISITOR' => '{"scheme":"https"}' ), null ),
			'cloudflare http'              => array( array( 'HTTP_HOST' => 'example.com', 'REQUEST_URI' => '/a', 'HTTP_CF_VISITOR' => '{"scheme":"http"}' ), 'https://example.com/a' ),
			'acme challenge'               => array( array( 'HTTP_HOST' => 'www.example.com', 'REQUEST_URI' => '/.well-known/acme-challenge/abc' ), null ),
			'other host (local copy)'      => array( array( 'HTTP_HOST' => 'example.local', 'REQUEST_URI' => '/' ), null ),
			'look-alike host'              => array( array( 'HTTP_HOST' => 'example.com.evil.org', 'REQUEST_URI' => '/' ), null ),
			'ip address'                   => array( array( 'HTTP_HOST' => '127.0.0.1', 'REQUEST_URI' => '/' ), null ),
			'polish path kept'             => array( array( 'HTTP_HOST' => 'www.example.com', 'REQUEST_URI' => '/reprezentacja-s%C4%85dy?q=%C5%BC' ), 'https://example.com/reprezentacja-s%C4%85dy?q=%C5%BC' ),
		);
	}

	/**
	 * @dataProvider cases
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'cases' )]
	public function test_target( array $server, ?string $expected ): void {
		$this->assertSame( $expected, Redirect_Rules::target( $server, self::BARE ) );
	}

	public function test_www_target_without_https(): void {
		$config = array( 'host' => 'www.example.com', 'port' => 0, 'scheme' => 'http', 'https' => false, 'www' => true );
		$this->assertSame( 'http://www.example.com/a?b=1', Redirect_Rules::target( array( 'HTTP_HOST' => 'example.com', 'REQUEST_URI' => '/a?b=1' ), $config ) );
		$this->assertNull( Redirect_Rules::target( array( 'HTTP_HOST' => 'www.example.com', 'REQUEST_URI' => '/a' ), $config ), 'http is fine when only the host is unified' );
	}

	public function test_https_only_keeps_www_variant_when_already_https(): void {
		$config = array_merge( self::BARE, array( 'www' => false ) );
		$this->assertNull( Redirect_Rules::target( array( 'HTTP_HOST' => 'www.example.com', 'REQUEST_URI' => '/', 'HTTPS' => 'on' ), $config ) );
		$this->assertSame( 'https://example.com/', Redirect_Rules::target( array( 'HTTP_HOST' => 'www.example.com', 'REQUEST_URI' => '/' ), $config ), 'one hop straight to the main host' );
	}

	public function test_disabled(): void {
		$config = array_merge( self::BARE, array( 'https' => false, 'www' => false ) );
		$this->assertNull( Redirect_Rules::target( array( 'HTTP_HOST' => 'www.example.com', 'REQUEST_URI' => '/' ), $config ) );
	}

	public function test_port_is_kept(): void {
		$config = array_merge( self::BARE, array( 'port' => 8443 ) );
		$this->assertSame( 'https://example.com:8443/a', Redirect_Rules::target( array( 'HTTP_HOST' => 'www.example.com:8443', 'REQUEST_URI' => '/a' ), $config ) );
	}
}
