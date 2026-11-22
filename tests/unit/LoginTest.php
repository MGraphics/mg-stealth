<?php
/**
 * Which requests for the login address skip the form for a signed-in user.
 *
 * @package MG_Stealth
 */

use MG_Stealth\Login;
use PHPUnit\Framework\TestCase;

final class LoginTest extends TestCase {

	public function test_plain_login_form_is_skipped(): void {
		$this->assertTrue( Login::skips_form( array(), 'GET' ) );
		$this->assertTrue( Login::skips_form( array( 'action' => 'login' ), 'GET' ) );
		$this->assertTrue( Login::skips_form( array( 'redirect_to' => 'http://example.test/wp-admin/edit.php' ), 'GET' ) );
		$this->assertTrue( Login::skips_form( array(), 'head' ) );
	}

	public function test_other_actions_keep_the_form(): void {
		foreach ( array( 'logout', 'lostpassword', 'retrievepassword', 'rp', 'resetpass', 'register', 'postpass', 'confirmaction', 'checkemail', 'confirm_admin_email', 'entered_recovery_mode' ) as $action ) {
			$this->assertFalse( Login::skips_form( array( 'action' => $action ), 'GET' ), $action );
		}
		$this->assertFalse( Login::skips_form( array( 'action' => array( 'login' ) ), 'GET' ) );
	}

	public function test_reauth_and_interim_login_keep_the_form(): void {
		$this->assertFalse( Login::skips_form( array( 'reauth' => '1' ), 'GET' ) );
		$this->assertFalse( Login::skips_form( array( 'interim-login' => '1' ), 'GET' ) );
		$this->assertFalse( Login::skips_form( array( 'customize-login' => '1' ), 'GET' ) );
		$this->assertFalse( Login::skips_form( array( 'checkemail' => 'confirm' ), 'GET' ) );
		$this->assertTrue( Login::skips_form( array( 'reauth' => '0' ), 'GET' ) );
	}

	public function test_posts_keep_the_form(): void {
		$this->assertFalse( Login::skips_form( array(), 'POST' ) );
	}
}
