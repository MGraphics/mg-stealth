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
		$GLOBALS['mgs_errors']  = array();
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
		$this->assertFalse( Settings::is_valid_slug( 'login' ) );
		$this->assertFalse( Settings::is_valid_slug( 'wp-panel-1' ) );
		$this->assertFalse( Settings::is_valid_slug( 'abc' ) );
		$this->assertTrue( Settings::is_valid_slug( 'moje-biuro-7' ) );
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
		$result = Settings::sanitize( array( '_tab' => 'login', 'login_enabled' => '1', 'login_slug' => 'admin' ) );
		$this->assertSame( $before['login_slug'], $result['login_slug'] );
		$this->assertTrue( $result['login_enabled'] );
	}
}
