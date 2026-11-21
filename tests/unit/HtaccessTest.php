<?php
/**
 * .htaccess builder tests.
 *
 * @package MG_Stealth
 */

use MG_Stealth\Htaccess;
use PHPUnit\Framework\TestCase;

final class HtaccessTest extends TestCase {

	private const WP_BLOCK = "# BEGIN WordPress\nRewriteEngine On\nRewriteBase /\nRewriteRule ^index\\.php$ - [L]\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule . /index.php [L]\n# END WordPress\n";

	private function full(): array {
		return array(
			'base'                => '/',
			'guard_file'          => '/var/www/html/wp-content/plugins/mg-stealth/mg-stealth.php',
			'cookie_rx'           => '(?:wordpress_logged_in_abc|wordpress_test_cookie)=',
			'redirect'            => array(
				'host'   => 'example.com',
				'port'   => 0,
				'scheme' => 'https',
				'https'  => true,
				'www'    => true,
			),
			'aliases'             => array(
				array( 'alias' => 'assets/ext/9f3a1c2b', 'real' => 'wp-content/plugins/contact-form-7', 'type' => 'static' ),
				array( 'alias' => 'assets/media', 'real' => 'wp-content/uploads', 'type' => 'uploads' ),
				array( 'alias' => 'xhr', 'real' => 'wp-admin/admin-ajax.php', 'type' => 'file' ),
				array( 'alias' => 'respond', 'real' => 'wp-comments-post.php', 'type' => 'file', 'post_only' => true ),
			),
			'block_trees'         => array( 'wp-includes', 'wp-content/plugins' ),
			'block_dirs'          => array( 'wp-content', 'wp-content/uploads' ),
			'core_files'          => array( 'readme.html', 'xmlrpc.php' ),
			'cron'                => array( 'file' => 'wp-cron.php', 'key' => 'abc123' ),
			'login'               => array( 'file' => 'wp-login.php', 'admin' => 'wp-admin', 'admin_allow' => array( 'admin-post.php' ) ),
			'directory_slash_off' => true,
		);
	}

	public function test_guard_and_sections_in_order(): void {
		$rules = Htaccess::build( $this->full() );
		$this->assertStringContainsString( 'RewriteCond "/var/www/html/wp-content/plugins/mg-stealth/mg-stealth.php" -f', $rules );
		$this->assertStringContainsString( 'RewriteRule ^ - [E=MGS:1]', $rules );
		$order = array( '# 1.', '# 2.', '# 3.', '# 4.', '# 5.', '# 6.', '# 7.' );
		$last  = -1;
		foreach ( $order as $marker ) {
			$pos = strpos( $rules, $marker );
			$this->assertNotFalse( $pos, $marker );
			$this->assertGreaterThan( $last, $pos );
			$last = $pos;
		}
		$this->assertSame( substr_count( $rules, '<IfModule mod_rewrite.c>' ), substr_count( $rules, '</IfModule>' ) - 1, 'mod_dir and mod_rewrite blocks are closed' );
	}

	public function test_redirect_to_bare_host_with_https(): void {
		$rules = Htaccess::build( $this->full() );
		$this->assertStringContainsString( "RewriteCond %{HTTPS} =on [OR]\nRewriteCond %{HTTP:X-Forwarded-Proto} ^https [NC,OR]\nRewriteCond %{HTTP:CF-Visitor} https [NC]\nRewriteRule ^ - [E=MGS_HTTPS:1]", $rules );
		$this->assertStringContainsString( "RewriteCond %{REQUEST_URI} !^/\\.well-known/\nRewriteCond %{HTTP_HOST} ^(www\\.)?example\\.com\\.?(:[0-9]+)?$ [NC]\nRewriteCond %{HTTP_HOST} ^www\\. [NC,OR]\nRewriteCond %{ENV:MGS_HTTPS} !=1\nRewriteRule ^ https://example.com%{REQUEST_URI} [R=301,L]", $rules );
	}

	public function test_redirect_to_www_host_only(): void {
		$c             = $this->full();
		$c['redirect'] = array( 'host' => 'www.example.com', 'port' => 0, 'scheme' => 'http', 'https' => false, 'www' => true );
		$rules         = Htaccess::build( $c );
		$this->assertStringNotContainsString( 'MGS_HTTPS', $rules );
		$this->assertStringContainsString( "RewriteCond %{HTTP_HOST} !^www\\. [NC]\nRewriteRule ^ http://www.example.com%{REQUEST_URI} [R=301,L]", $rules );
	}

	public function test_aliases(): void {
		$rules = Htaccess::build( $this->full() );
		$this->assertMatchesRegularExpression( '~RewriteRule \^assets/ext/9f3a1c2b/\(\.\+\\\\\.\(\?:css\|js\|[^)]*\)\)\$ wp-content/plugins/contact-form-7/\$1 \[L,NC\]~', $rules );
		$this->assertStringContainsString( 'RewriteRule ^assets/media/(.+\.[A-Za-z0-9]+)$ wp-content/uploads/$1 [L]', $rules );
		$this->assertStringContainsString( 'RewriteRule ^xhr$ wp-admin/admin-ajax.php [L]', $rules );
		$this->assertStringContainsString( "RewriteCond %{REQUEST_METHOD} =POST\nRewriteRule ^respond$ wp-comments-post.php [L]", $rules );
		$this->assertStringNotContainsString( 'php)$ wp-content', $rules, 'No PHP through the static aliases.' );
	}

	public function test_hidden_paths_need_no_cookie_and_first_pass(): void {
		$rules = Htaccess::build( $this->full() );
		$this->assertStringContainsString( "RewriteCond %{ENV:REDIRECT_STATUS} ^$\nRewriteCond %{HTTP_COOKIE} !(^|;\\s*)(?:wordpress_logged_in_abc|wordpress_test_cookie)= [NC]\nRewriteRule ^(wp\\-includes(/|$)|wp\\-content/plugins(/|$)|wp\\-content/?$|wp\\-content/uploads/?$) index.php [L,NC]", $rules );
		$this->assertStringContainsString( 'RewriteRule ^wp\-login\.php$ index.php [L,NC]', $rules );
		$this->assertStringContainsString( "RewriteCond %{REQUEST_URI} !/wp\\-admin/(admin\\-post\\.php)$ [NC]\nRewriteRule ^wp\\-admin(/|$) index.php [L,NC]", $rules );
		$this->assertStringContainsString( "RewriteCond %{QUERY_STRING} !(^|&)k=abc123(&|$)\nRewriteRule ^wp\\-cron\\.php$ index.php [L,NC]", $rules );
	}

	public function test_minimal_config_has_no_directory_slash(): void {
		$rules = Htaccess::build( array( 'guard_file' => '/x/mg-stealth.php', 'core_files' => array( 'readme.html' ) ) );
		$this->assertStringNotContainsString( 'DirectorySlash', $rules );
		$this->assertStringContainsString( 'RewriteRule ^(readme\.html)$ index.php [L,NC]', $rules );
	}

	public function test_block_goes_before_wordpress_and_is_idempotent(): void {
		$once  = Htaccess::replace_block( self::WP_BLOCK, "RuleA\nRuleB" );
		$twice = Htaccess::replace_block( $once, "RuleA\nRuleB" );
		$this->assertSame( $once, $twice );
		$this->assertStringStartsWith( "# BEGIN MG Stealth\nRuleA\nRuleB\n# END MG Stealth\n\n# BEGIN WordPress", $once );
		$this->assertSame( "RuleA\nRuleB", Htaccess::extract_block( $once ) );
	}

	public function test_remove_restores_original(): void {
		$with = Htaccess::replace_block( self::WP_BLOCK, 'RuleA' );
		$this->assertSame( self::WP_BLOCK, Htaccess::remove_block( $with ) );
		$this->assertSame( self::WP_BLOCK, Htaccess::replace_block( $with, '' ) );
		$this->assertNull( Htaccess::extract_block( self::WP_BLOCK ) );
	}

	public function test_replace_updates_existing_block_in_place(): void {
		$with    = Htaccess::replace_block( self::WP_BLOCK, 'Old' );
		$updated = Htaccess::replace_block( $with, 'New' );
		$this->assertSame( 1, substr_count( $updated, '# BEGIN MG Stealth' ) );
		$this->assertSame( 'New', Htaccess::extract_block( $updated ) );
	}

	public function test_empty_file(): void {
		$this->assertSame( "# BEGIN MG Stealth\nRule\n# END MG Stealth\n", Htaccess::replace_block( '', 'Rule' ) );
	}
}
