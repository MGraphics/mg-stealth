<?php
/**
 * Lockout after failed login attempts: counting, escalation, reset, address lists, proxies.
 *
 * @package MG_Stealth
 */

use MG_Stealth\Lockout;
use PHPUnit\Framework\TestCase;

final class LockoutTest extends TestCase {

	private function rules(): array {
		return Lockout::rules(
			array(
				'lockout_retries'     => 4,
				'lockout_minutes'     => 20,
				'lockout_escalate'    => 4,
				'lockout_long_hours'  => 24,
				'lockout_reset_hours' => 12,
			)
		);
	}

	/**
	 * Fails $times times at $now for the keys.
	 */
	private function failures( array $data, array $keys, int $now, int $times = 1 ): array {
		$locked = array();
		for ( $i = 0; $i < $times; $i++ ) {
			$r      = Lockout::record_failure( $data, $keys, $now, $this->rules() );
			$data   = $r['data'];
			$locked = $r['locked'] ?: $locked;
		}
		return array( $data, $locked );
	}

	public function test_locks_out_after_the_allowed_attempts(): void {
		$now = 1000000;
		list( $data, $locked ) = $this->failures( Lockout::empty_data(), array( 'ip:a' ), $now, 3 );
		$this->assertSame( array(), $locked, 'three failures are allowed' );
		$this->assertSame( 0, Lockout::locked_until( $data, array( 'ip:a' ), $now ) );

		list( $data, $locked ) = $this->failures( $data, array( 'ip:a' ), $now );
		$this->assertSame( $now + 20 * 60, $locked['ip:a']['until'] );
		$this->assertFalse( $locked['ip:a']['long'] );
		$this->assertSame( $now + 1200, Lockout::locked_until( $data, array( 'ip:a' ), $now + 60 ) );
		$this->assertSame( 0, Lockout::locked_until( $data, array( 'ip:b' ), $now + 60 ), 'other addresses are not affected' );
		$this->assertSame( 0, Lockout::locked_until( $data, array( 'ip:a' ), $now + 1201 ), 'the lockout ends' );
	}

	public function test_attempts_during_a_lockout_are_not_counted(): void {
		$now = 1000000;
		list( $data ) = $this->failures( Lockout::empty_data(), array( 'ip:a' ), $now, 4 );
		list( $data, $locked ) = $this->failures( $data, array( 'ip:a' ), $now + 10, 20 );
		$this->assertSame( array(), $locked );
		$this->assertSame( $now + 1200, Lockout::locked_until( $data, array( 'ip:a' ), $now + 10 ), 'the lockout is not extended' );
	}

	public function test_repeated_lockouts_escalate_to_the_long_lockout(): void {
		$now  = 1000000;
		$data = Lockout::empty_data();
		for ( $round = 1; $round <= 3; $round++ ) {
			list( $data, $locked ) = $this->failures( $data, array( 'ip:a' ), $now, 4 );
			$this->assertFalse( $locked['ip:a']['long'], "lockout $round is short" );
			$now += 1201;
		}
		list( $data, $locked ) = $this->failures( $data, array( 'ip:a' ), $now, 4 );
		$this->assertTrue( $locked['ip:a']['long'], 'the fourth lockout is the long one' );
		$this->assertSame( $now + 24 * 3600, Lockout::locked_until( $data, array( 'ip:a' ), $now ) );

		// After the long lockout the count starts again from the short one.
		$now += 24 * 3600 + 1;
		list( , $locked ) = $this->failures( $data, array( 'ip:a' ), $now, 4 );
		$this->assertFalse( $locked['ip:a']['long'] );
	}

	public function test_counters_reset_after_the_reset_time(): void {
		$now = 1000000;
		list( $data ) = $this->failures( Lockout::empty_data(), array( 'ip:a' ), $now, 3 );
		list( $data, $locked ) = $this->failures( $data, array( 'ip:a' ), $now + 12 * 3600 + 1 );
		$this->assertSame( array(), $locked, 'old failures are forgotten' );
		$this->assertSame( 1, $data['retries']['ip:a']['count'] );

		// Lockouts in a row are forgotten too.
		$data = Lockout::empty_data();
		for ( $round = 1; $round <= 3; $round++ ) {
			list( $data ) = $this->failures( $data, array( 'ip:a' ), $now, 4 );
			$now += 12 * 3600 + 1;
		}
		list( , $locked ) = $this->failures( $data, array( 'ip:a' ), $now, 4 );
		$this->assertFalse( $locked['ip:a']['long'] );
	}

	public function test_user_name_key_locks_across_addresses(): void {
		$now  = 1000000;
		$data = Lockout::empty_data();
		foreach ( array( 'ip:1', 'ip:2', 'ip:3', 'ip:4' ) as $ip ) {
			list( $data, $locked ) = $this->failures( $data, array( $ip, 'user:x' ), $now );
		}
		$this->assertArrayHasKey( 'user:x', $locked );
		$this->assertArrayNotHasKey( 'ip:4', $locked );
		$this->assertGreaterThan( 0, Lockout::locked_until( $data, array( 'ip:5', 'user:x' ), $now ) );
		$this->assertSame( 0, Lockout::locked_until( $data, array( 'ip:5', 'user:y' ), $now ) );
	}

	public function test_ip_lists(): void {
		$this->assertTrue( Lockout::ip_in_list( '203.0.113.7', array( '203.0.113.7' ) ) );
		$this->assertTrue( Lockout::ip_in_list( '198.51.100.200', array( '10.0.0.1', '198.51.100.0/24' ) ) );
		$this->assertFalse( Lockout::ip_in_list( '198.51.101.1', array( '198.51.100.0/24' ) ) );
		$this->assertTrue( Lockout::ip_in_list( '10.1.2.3', array( '10.0.0.0/9' ) ) );
		$this->assertFalse( Lockout::ip_in_list( '10.200.2.3', array( '10.0.0.0/9' ) ) );
		$this->assertTrue( Lockout::ip_in_list( '2001:db8:abcd::1', array( '2001:db8::/32' ) ) );
		$this->assertFalse( Lockout::ip_in_list( '2001:db9::1', array( '2001:db8::/32' ) ) );
		$this->assertFalse( Lockout::ip_in_list( '203.0.113.7', array( '2001:db8::/32', 'nonsense', '' ) ) );
		$this->assertFalse( Lockout::ip_in_list( 'not-an-ip', array( '0.0.0.0/0' ) ) );
	}

	public function test_client_ip_ignores_forwarded_headers_from_strangers(): void {
		$server = array(
			'REMOTE_ADDR'           => '203.0.113.7',
			'HTTP_X_FORWARDED_FOR'  => '1.2.3.4',
			'HTTP_CF_CONNECTING_IP' => '5.6.7.8',
		);
		$this->assertSame( '203.0.113.7', Lockout::client_ip( $server, array(), false ) );
		$this->assertSame( '203.0.113.7', Lockout::client_ip( $server, array(), true ), 'not a Cloudflare address' );
	}

	public function test_client_ip_behind_a_trusted_proxy(): void {
		$server = array(
			'REMOTE_ADDR'          => '10.0.0.2',
			'HTTP_X_FORWARDED_FOR' => '9.9.9.9, 198.51.100.4, 10.0.0.3',
		);
		// The right-most hop that is not a trusted proxy; the forged left part is ignored.
		$this->assertSame( '198.51.100.4', Lockout::client_ip( $server, array( '10.0.0.0/8' ), false ) );
		$server['HTTP_X_FORWARDED_FOR'] = 'garbage';
		$this->assertSame( '10.0.0.2', Lockout::client_ip( $server, array( '10.0.0.0/8' ), false ) );
	}

	public function test_client_ip_behind_cloudflare(): void {
		$server = array(
			'REMOTE_ADDR'           => '172.68.1.10',
			'HTTP_CF_CONNECTING_IP' => '2001:db8::7',
		);
		$this->assertSame( '2001:db8::7', Lockout::client_ip( $server, array(), true ) );
		$this->assertSame( '172.68.1.10', Lockout::client_ip( $server, array(), false ), 'Cloudflare option off' );
		$server['HTTP_CF_CONNECTING_IP'] = 'bad';
		$server['HTTP_X_FORWARDED_FOR']  = '198.51.100.9';
		$this->assertSame( '198.51.100.9', Lockout::client_ip( $server, array(), true ) );
	}

	public function test_anonymize_ip(): void {
		$this->assertSame( '203.0.113.0', Lockout::anonymize_ip( '203.0.113.77' ) );
		$this->assertSame( '2001:db8:abcd::', Lockout::anonymize_ip( '2001:db8:abcd:12::1' ) );
		$this->assertSame( '', Lockout::anonymize_ip( 'x' ) );
	}

	public function test_counters_are_capped(): void {
		$data = Lockout::empty_data();
		for ( $i = 0; $i < Lockout::MAX_ENTRIES + 10; $i++ ) {
			$data['retries'][ 'ip:' . $i ] = array( 'count' => 1, 'expires' => 2000000 + $i );
		}
		$r = Lockout::record_failure( $data, array( 'ip:new' ), 1000000, $this->rules() );
		$this->assertCount( Lockout::MAX_ENTRIES, $r['data']['retries'] );
	}
}
