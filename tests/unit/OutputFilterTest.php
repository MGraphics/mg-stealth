<?php
/**
 * Output filter tests.
 *
 * @package MG_Stealth
 */

use MG_Stealth\Output_Filter;
use PHPUnit\Framework\TestCase;

final class OutputFilterTest extends TestCase {

	private const MAP = array(
		'/wp-content/plugins/contact-form-7/' => '/assets/ext/9f3a1c2b/',
		'/wp-content/plugins/'                => '/assets/ext/',
		'/wp-content/uploads/'                => '/assets/media/',
		'/wp-content/'                        => '/assets/',
		'/wp-includes/'                       => '/lib/',
		'/wp-admin/admin-ajax.php'            => '/xhr',
	);

	private const HOSTS = array( 'example.com', 'example.com:8080' );

	private function rewrite( string $html ): string {
		return Output_Filter::rewrite_paths( $html, self::MAP, self::HOSTS );
	}

	public function test_absolute_and_protocol_relative_urls(): void {
		$this->assertSame(
			'<link href="https://example.com/lib/css/a.css"><img src="//example.com/assets/media/2026/11/x.jpg">',
			$this->rewrite( '<link href="https://example.com/wp-includes/css/a.css"><img src="//example.com/wp-content/uploads/2026/11/x.jpg">' )
		);
	}

	public function test_specific_entries_win_over_parents(): void {
		$this->assertSame(
			'<script src="http://example.com/assets/ext/9f3a1c2b/js/index.js"></script><script src="http://example.com/assets/ext/other/x.js"></script>',
			$this->rewrite( '<script src="http://example.com/wp-content/plugins/contact-form-7/js/index.js"></script><script src="http://example.com/wp-content/plugins/other/x.js"></script>' )
		);
	}

	public function test_json_escaped_urls(): void {
		$this->assertSame(
			'{"root":"http:\/\/example.com:8080\/lib\/js\/","ajax":"\/xhr"}',
			$this->rewrite( '{"root":"http:\/\/example.com:8080\/wp-includes\/js\/","ajax":"\/wp-admin\/admin-ajax.php"}' )
		);
	}

	public function test_root_relative_after_delimiters_only(): void {
		$this->assertSame(
			'<a href="/assets/x.pdf">x</a> url(/lib/i.png) see wp-content/ folder',
			$this->rewrite( '<a href="/wp-content/x.pdf">x</a> url(/wp-includes/i.png) see wp-content/ folder' )
		);
	}

	public function test_other_hosts_untouched(): void {
		$html = '<img src="https://cdn.other.org/wp-content/uploads/a.jpg">';
		$this->assertSame( $html, $this->rewrite( $html ) );
	}

	public function test_srcset_and_percent_encoded(): void {
		$this->assertSame(
			'srcset="https://example.com/assets/media/a-300.jpg 300w, https://example.com/assets/media/a.jpg 600w" redirect_to=https%3A%2F%2Fexample.com%2Fassets%2Fmedia%2F',
			$this->rewrite( 'srcset="https://example.com/wp-content/uploads/a-300.jpg 300w, https://example.com/wp-content/uploads/a.jpg 600w" redirect_to=https%3A%2F%2Fexample.com%2Fwp-content%2Fuploads%2F' )
		);
	}

	public function test_empty_map_returns_body(): void {
		$this->assertSame( 'abc /wp-content/', Output_Filter::rewrite_paths( 'abc /wp-content/', array(), self::HOSTS ) );
	}

	public function test_strip_comments_keeps_scripts_styles_and_conditionals(): void {
		$html = "<!-- Plugin X v1.2 -->\n<p>a</p><!--[if lt IE 9]><script src=\"x.js\"></script><![endif]-->"
			. "<script>var s = '<!-- not a comment -->';</script><style>/* <!-- */ a{}</style>"
			. "<textarea><!-- keep --></textarea><p>b<!-- cache: hit --></p>";
		$out  = Output_Filter::strip_comments( $html );
		$this->assertStringNotContainsString( 'Plugin X', $out );
		$this->assertStringNotContainsString( 'cache: hit', $out );
		$this->assertStringContainsString( '<!--[if lt IE 9]>', $out );
		$this->assertStringContainsString( "var s = '<!-- not a comment -->';", $out );
		$this->assertStringContainsString( '<textarea><!-- keep --></textarea>', $out );
		$this->assertStringContainsString( '<p>a</p>', $out );
	}

	public function test_strip_asset_ids(): void {
		$html = "<link rel='stylesheet' id='wp-block-library-css' href='a.css' media='all' />"
			. '<style id="global-styles-inline-css">a{}</style>'
			. '<script src="b.js" id="contact-form-7-js"></script><script id="wpcf7-js-extra">var a;</script>'
			. '<script type="module" src="c.js" id="@wordpress/block-library/navigation/view-js-module"></script>'
			. '<link rel="modulepreload" href="d.js" id="@wordpress/interactivity-js-modulepreload">'
			. '<div id="main-css">keep divs</div><script id="my-app">keep</script>';
		$out  = Output_Filter::strip_asset_ids( $html );
		$this->assertStringNotContainsString( 'wp-block-library-css', $out );
		$this->assertStringNotContainsString( 'global-styles-inline-css', $out );
		$this->assertStringNotContainsString( 'contact-form-7-js', $out );
		$this->assertStringNotContainsString( 'wpcf7-js-extra', $out );
		$this->assertStringNotContainsString( 'view-js-module', $out );
		$this->assertStringNotContainsString( 'js-modulepreload', $out );
		$this->assertStringContainsString( '<div id="main-css">', $out );
		$this->assertStringContainsString( '<script id="my-app">', $out );
	}

	public function test_strip_head_tags(): void {
		$html = "<meta name=\"generator\" content=\"WordPress 7.1\" />\n<meta content=\"Elementor 3\" name='generator'>\n"
			. "<link rel=\"EditURI\" type=\"application/rsd+xml\" href=\"x\">\n<link rel='shortlink' href='/?p=1' />\n"
			. "<link rel=\"https://api.w.org/\" href=\"/wp-json/\">\n<link rel=\"pingback\" href=\"/xmlrpc.php\">\n"
			. "<link rel=\"alternate\" type=\"application/json+oembed\" href=\"x\">\n<link rel=\"alternate\" type=\"text/xml+oembed\" href=\"x\">\n"
			. "<meta name=\"description\" content=\"keep\"><link rel=\"canonical\" href=\"keep\">";
		$out  = Output_Filter::strip_head_tags( $html );
		$this->assertSame( '<meta name="description" content="keep"><link rel="canonical" href="keep">', $out );
	}

	public function test_neutralize_versions(): void {
		$out = Output_Filter::neutralize_versions( '<script src="/a.js?ver=7.1.5"></script><link href="/b.css?x=1&#038;ver=6.2.5"><a href="/page/?ver=2">', 'salt' );
		$this->assertStringNotContainsString( 'ver=7.1.5', $out );
		$this->assertStringNotContainsString( 'ver=6.2.5', $out );
		$this->assertStringContainsString( '/a.js?v=' . Output_Filter::hash_version( '7.1.5', 'salt' ), $out );
		$this->assertStringContainsString( '/b.css?x=1&#038;v=', $out );
		$this->assertStringContainsString( '/page/?ver=2', $out, 'Only asset addresses change.' );
	}

	public function test_hash_version_depends_on_salt(): void {
		$this->assertSame( Output_Filter::hash_version( '1.0', 'a' ), Output_Filter::hash_version( '1.0', 'a' ) );
		$this->assertNotSame( Output_Filter::hash_version( '1.0', 'a' ), Output_Filter::hash_version( '1.0', 'b' ) );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{8}$/', Output_Filter::hash_version( '7.1.5', 'x' ) );
	}

	public function test_strip_source_urls(): void {
		$html = "<script>var a = 1;\n//# sourceURL=contact-form-7-js-before\n</script><style>a{}\n/*# sourceURL=global-styles-inline-css */</style>";
		$this->assertSame( "<script>var a = 1;\n</script><style>a{}</style>", Output_Filter::strip_source_urls( $html ) );
	}

	public function test_find_traces(): void {
		$found = Output_Filter::find_traces( 'Designed with WordPress. <a href="/wp-content/x">', array( 'wordpress', 'wp-content', 'xmlrpc' ) );
		$this->assertSame( 1, $found['wordpress']['count'] );
		$this->assertSame( 1, $found['wp-content']['count'] );
		$this->assertArrayNotHasKey( 'xmlrpc', $found );
		$this->assertStringContainsString( 'WordPress', $found['wordpress']['excerpts'][0] );
	}
}
