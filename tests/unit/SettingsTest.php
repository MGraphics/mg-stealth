<?php
/**
 * Settings validation tests.
 *
 * @package MG_Stealth
 */

use MG_Stealth\Settings;
use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['mgs_options'] = array();
		$GLOBALS['mgs_errors']   = array();
		$GLOBALS['mgs_warnings'] = array();
		Settings::flush_cache();
		Settings::ensure_initialized();
	}

	public function test_initial_random_names(): void {
		$s = Settings::all();
		foreach ( array_keys( Settings::POOLS ) as $key ) {
			$this->assertNotSame( '', $s[ $key ], $key );
		}
		$this->assertStringStartsWith( $s['path_content'] . '/', $s['path_plugins'] );
		$this->assertStringStartsWith( $s['path_content'] . '/', $s['path_uploads'] );
		$this->assertSame( 32, strlen( $s['salt'] ) );
		$this->assertTrue( Settings::is_valid_slug( $s['login_slug'] ) );
		$this->assertFalse( $s['paths_enabled'], 'Risky modules start switched off.' );
		$this->assertFalse( $s['login_enabled'] );
		$this->assertFalse( $s['redirect_https'] );
		$this->assertTrue( $s['clean_head'] );
	}

	public function test_initialisation_runs_once(): void {
		$first = Settings::all();
		Settings::flush_cache();
		Settings::ensure_initialized();
		$this->assertSame( $first, Settings::all() );
	}

	public function test_clean_path(): void {
		$this->assertSame( 'assets/ext', Settings::clean_path( ' /Assets//ext/ ' ) );
		$this->assertSame( 'a/b/c', Settings::clean_path( 'a/b/c/d' ) );
		$this->assertSame( 'media', Settings::clean_path( 'me<d>ia' ) );
	}

	public function test_login_slug_rules(): void {
		foreach ( array( 'login', 'admin', 'panel', 'dashboard', 'abc', 'moje-biuro-7' ) as $slug ) {
			$this->assertTrue( Settings::is_valid_slug( $slug ), $slug );
		}
		foreach ( array( 'wp-panel-1', 'wp-login', 'wp-admin', 'wp', 'ab', '', 'feed', 'page', 'sitemap', 'search', 'my-wordpress', '-login', 'log--in', 'Login' ) as $slug ) {
			$this->assertFalse( Settings::is_valid_slug( $slug ), $slug );
		}
	}

	public function test_guessable_slugs(): void {
		foreach ( array( 'login', 'admin', 'panel', 'dashboard', 'logowanie', 'abc' ) as $slug ) {
			$this->assertTrue( Settings::is_guessable_slug( $slug ), $slug );
		}
		$this->assertFalse( Settings::is_guessable_slug( 'moje-biuro-7' ) );
		$this->assertFalse( Settings::is_guessable_slug( Settings::random_login_slug() ) );
	}

	public function test_simple_login_slug_is_saved_with_a_warning(): void {
		$result = Settings::sanitize( array( '_tab' => 'login', 'login_enabled' => '1', 'login_slug' => 'Login' ) );
		$this->assertSame( 'login', $result['login_slug'] );
		$this->assertTrue( $result['login_enabled'] );
		$this->assertSame( array(), $GLOBALS['mgs_errors'] );
		$this->assertNotEmpty( $GLOBALS['mgs_warnings'] );
	}

	public function test_random_login_slug_is_saved_without_a_warning(): void {
		$result = Settings::sanitize( array( '_tab' => 'login', 'login_enabled' => '1', 'login_slug' => 'moje-biuro-7' ) );
		$this->assertSame( 'moje-biuro-7', $result['login_slug'] );
		$this->assertSame( array(), $GLOBALS['mgs_errors'] );
		$this->assertSame( array(), $GLOBALS['mgs_warnings'] );
	}

	public function test_login_slug_must_not_be_a_neutral_path(): void {
		$before = Settings::all();
		$first  = explode( '/', $before['path_content'] )[0];
		$result = Settings::sanitize( array( '_tab' => 'login', 'login_enabled' => '1', 'login_slug' => $first ) );
		$this->assertSame( $before['login_slug'], $result['login_slug'] );
		$this->assertNotEmpty( $GLOBALS['mgs_errors'] );

		$GLOBALS['mgs_errors'] = array();
		$result                = Settings::sanitize( array( '_tab' => 'login', 'login_enabled' => '1', 'login_slug' => $before['rest_prefix'] ) );
		$this->assertSame( $before['login_slug'], $result['login_slug'] );
		$this->assertNotEmpty( $GLOBALS['mgs_errors'] );
	}

	public function test_login_slug_must_not_be_a_registered_route(): void {
		$GLOBALS['mgs_options']['rewrite_rules'] = array( 'shop/?$' => 'index.php?post_type=product', '(.?.+?)(?:/([0-9]+))?/?$' => 'index.php?pagename=$matches[1]' );
		$before                                  = Settings::all();
		$result                                  = Settings::sanitize( array( '_tab' => 'login', 'login_enabled' => '1', 'login_slug' => 'shop' ) );
		$this->assertSame( $before['login_slug'], $result['login_slug'] );
		$this->assertNotEmpty( $GLOBALS['mgs_errors'] );
		$GLOBALS['mgs_errors'] = array();
		$result                = Settings::sanitize( array( '_tab' => 'login', 'login_enabled' => '1', 'login_slug' => 'login' ) );
		$this->assertSame( 'login', $result['login_slug'], 'the catch-all page rule is no conflict' );
	}

	public function test_lockout_settings_are_cleaned(): void {
		$s = Settings::all();
		$this->assertFalse( $s['lockout_enabled'] );
		$this->assertSame( 4, $s['lockout_retries'] );
		$this->assertSame( 20, $s['lockout_minutes'] );
		$this->assertSame( 24, $s['lockout_long_hours'] );
		$this->assertSame( 12, $s['lockout_reset_hours'] );
		$result = Settings::sanitize(
			array(
				'_tab'            => 'lockout',
				'lockout_enabled' => '1',
				'lockout_retries' => '0',
				'lockout_minutes' => '99999',
				'lockout_allow'   => " 203.0.113.7\nnonsense, 198.51.100.0/24 ; 2001:DB8::1/128\n10.0.0.0/abc",
			)
		);
		$this->assertTrue( $result['lockout_enabled'] );
		$this->assertSame( 1, $result['lockout_retries'] );
		$this->assertSame( 1440, $result['lockout_minutes'] );
		$this->assertSame( "203.0.113.7\n198.51.100.0/24\n2001:db8::1", $result['lockout_allow'] );
		$this->assertFalse( $result['lockout_notify'], 'off by default' );
	}

	public function test_tab_save_resets_missing_checkboxes_and_keeps_other_tabs(): void {
		$before = Settings::all();
		$result = Settings::sanitize(
			array(
				'_tab'       => 'source',
				'clean_head' => '1',
			)
		);
		$this->assertTrue( $result['clean_head'] );
		$this->assertFalse( $result['clean_emoji'], 'unchecked box on the saved tab' );
		$this->assertSame( $before['path_content'], $result['path_content'], 'other tab untouched' );
		$this->assertSame( $before['rest_guests_closed'], $result['rest_guests_closed'] );
	}

	public function test_rejects_wordpress_names_and_duplicates(): void {
		$before = Settings::all();
		$result = Settings::sanitize( array( '_tab' => 'paths', 'path_content' => 'wp-files', 'path_includes' => 'lib' ) );
		$this->assertSame( $before['path_content'], $result['path_content'] );
		$this->assertNotEmpty( $GLOBALS['mgs_errors'] );

		$GLOBALS['mgs_errors'] = array();
		$result                = Settings::sanitize( array( '_tab' => 'paths', 'path_includes' => $before['path_content'] ) );
		$this->assertSame( $before['path_includes'], $result['path_includes'] );
		$this->assertNotEmpty( $GLOBALS['mgs_errors'] );

		$GLOBALS['mgs_errors'] = array();
		$result                = Settings::sanitize( array( '_tab' => 'paths', 'path_ajax' => 'a/b' ) );
		$this->assertSame( $before['path_ajax'], $result['path_ajax'] );
		$this->assertNotEmpty( $GLOBALS['mgs_errors'] );
	}

	public function test_accepts_valid_paths(): void {
		$result = Settings::sanitize(
			array(
				'_tab'          => 'paths',
				'paths_enabled' => '1',
				'path_content'  => 'static',
				'path_themes'   => 'static/ui',
				'path_plugins'  => 'static/ext',
				'path_uploads'  => 'media',
				'path_includes' => 'lib',
				'path_ajax'     => 'xhr',
				'path_comments' => 'respond',
				'rest_prefix'   => 'api',
			)
		);
		$this->assertTrue( $result['paths_enabled'] );
		$this->assertSame( 'media', $result['path_uploads'] );
		$this->assertSame( array(), $GLOBALS['mgs_errors'] );
	}

	public function test_rest_allow_list_is_cleaned(): void {
		$result = Settings::sanitize( array( '_tab' => 'access', 'rest_allow' => " /Contact-Form-7/ \nwc/store\n<script>\n\nwc/store" ) );
		$this->assertSame( "contact-form-7\nwc/store", $result['rest_allow'] );
	}

	public function test_invalid_login_slug_keeps_previous(): void {
		$before = Settings::all();
		foreach ( array( 'wp-admin', 'feed', 'ab' ) as $slug ) {
			$GLOBALS['mgs_errors'] = array();
			$result                = Settings::sanitize( array( '_tab' => 'login', 'login_enabled' => '1', 'login_slug' => $slug ) );
			$this->assertSame( $before['login_slug'], $result['login_slug'], $slug );
			$this->assertTrue( $result['login_enabled'] );
			$this->assertNotEmpty( $GLOBALS['mgs_errors'], $slug );
		}
	}

	public function test_default_login_slug_stays_random(): void {
		$slug = Settings::all()['login_slug'];
		$this->assertMatchesRegularExpression( '~^[a-z0-9]{4}-[a-z]+-[a-z0-9]{4}$~', $slug );
		$this->assertFalse( Settings::is_guessable_slug( $slug ) );
	}
}
