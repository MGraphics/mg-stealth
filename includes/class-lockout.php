<?php
/**
 * Lockout after failed login attempts: counts failures per IP address (and optionally per user
 * name) on the login form, XML-RPC and application passwords, and refuses further attempts for
 * a while.
 *
 * @package MG_Stealth
 */

namespace MG_Stealth;

defined( 'ABSPATH' ) || exit;

/**
 * Failed login lockout. The counting itself (record_failure(), locked_until(), prune()) works on
 * a plain array, so it is tested without WordPress.
 */
final class Lockout {

	/**
	 * Option with counters, active lockouts and the log (not autoloaded).
	 */
	const OPTION = 'mg_stealth_lockout';

	/**
	 * Entries kept in the log.
	 */
	const LOG_SIZE = 100;

	/**
	 * Counters kept at most (a distributed attack must not grow the option without end).
	 */
	const MAX_ENTRIES = 5000;

	/**
	 * Cloudflare's published address ranges (https://www.cloudflare.com/ips/).
	 */
	const CLOUDFLARE = array(
		'173.245.48.0/20',
		'103.21.244.0/22',
		'103.22.200.0/22',
		'103.31.4.0/22',
		'141.101.64.0/18',
		'108.162.192.0/18',
		'190.93.240.0/20',
		'188.114.96.0/20',
		'197.234.240.0/22',
		'198.41.128.0/17',
		'162.158.0.0/15',
		'104.16.0.0/13',
		'104.24.0.0/14',
		'172.64.0.0/13',
		'131.0.72.0/22',
		'2400:cb00::/32',
		'2606:4700::/32',
		'2803:f800::/32',
		'2405:b500::/32',
		'2405:8100::/32',
		'2a06:98c0::/29',
		'2c0f:f248::/32',
	);

	/**
	 * Address of the visitor of this request (cached).
	 *
	 * @var string|null
	 */
	private $ip = null;

	/**
	 * Whether the module is on.
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		return Settings::on( 'lockout_enabled' );
	}

	/**
	 * Hooks.
	 */
	public function register(): void {
		if ( ! self::enabled() ) {
			return;
		}
		add_filter( 'authenticate', array( $this, 'filter_authenticate' ), 99, 3 );
		add_action( 'wp_login_failed', array( $this, 'on_login_failed' ), 10, 2 );
		add_action( 'wp_login', array( $this, 'on_login' ), 10, 1 );
		add_action( 'wp_authenticate_application_password_errors', array( $this, 'on_application_password' ), 10, 2 );
		add_action( 'application_password_failed_authentication', array( $this, 'on_application_password_failed' ), 10, 1 );
		add_action( 'login_init', array( $this, 'guard_login_page' ), 0 );
		add_filter( 'shake_error_codes', array( $this, 'shake_codes' ) );
	}

	/*
	 * ---------------------------------------------------------------------------------------------
	 * Pure logic.
	 * ---------------------------------------------------------------------------------------------
	 */

	/**
	 * Rules from the settings.
	 *
	 * @param array<string,mixed> $s Settings.
	 * @return array{retries:int,minutes:int,lockouts:int,long_hours:int,reset_hours:int}
	 */
	public static function rules( array $s ): array {
		return array(
			'retries'     => max( 1, (int) ( $s['lockout_retries'] ?? 4 ) ),
			'minutes'     => max( 1, (int) ( $s['lockout_minutes'] ?? 20 ) ),
			'lockouts'    => max( 1, (int) ( $s['lockout_escalate'] ?? 4 ) ),
			'long_hours'  => max( 1, (int) ( $s['lockout_long_hours'] ?? 24 ) ),
			'reset_hours' => max( 1, (int) ( $s['lockout_reset_hours'] ?? 12 ) ),
		);
	}

	/**
	 * Empty data.
	 *
	 * @return array{retries:array,lockouts:array,log:array}
	 */
	public static function empty_data(): array {
		return array(
			'retries'  => array(),
			'lockouts' => array(),
			'log'      => array(),
		);
	}

	/**
	 * Until when any of the keys is locked out (0: not locked).
	 *
	 * @param array    $data Data.
	 * @param string[] $keys Counter keys.
	 * @param int      $now  Current time.
	 * @return int
	 */
	public static function locked_until( array $data, array $keys, int $now ): int {
		$until = 0;
		foreach ( $keys as $key ) {
			$entry = $data['lockouts'][ $key ] ?? null;
			if ( is_array( $entry ) && (int) $entry['until'] > $now ) {
				$until = max( $until, (int) $entry['until'] );
			}
		}
		return $until;
	}

	/**
	 * Counts one failed attempt for every key; locks a key out when it reaches the limit, for
	 * longer after repeated lockouts.
	 *
	 * @param array    $data  Data.
	 * @param string[] $keys  Counter keys.
	 * @param int      $now   Current time.
	 * @param array    $rules Rules (see rules()).
	 * @return array{data:array,locked:array<string,array{until:int,long:bool}>}
	 */
	public static function record_failure( array $data, array $keys, int $now, array $rules ): array {
		$data   = self::prune( array_merge( self::empty_data(), $data ), $now, $rules );
		$reset  = $rules['reset_hours'] * 3600;
		$locked = array();
		foreach ( $keys as $key ) {
			if ( self::locked_until( $data, array( $key ), $now ) > 0 ) {
				continue; // Attempts during a lockout are refused, not counted.
			}
			$count                    = (int) ( $data['retries'][ $key ]['count'] ?? 0 ) + 1;
			$data['retries'][ $key ] = array(
				'count'   => $count,
				'expires' => $now + $reset,
			);
			if ( $count < $rules['retries'] ) {
				continue;
			}
			unset( $data['retries'][ $key ] );
			$previous = $data['lockouts'][ $key ] ?? array();
			$times    = (int) ( $previous['times'] ?? 0 ) + 1;
			$long     = $times >= $rules['lockouts'];
			$until    = $now + ( $long ? $rules['long_hours'] * 3600 : $rules['minutes'] * 60 );
			$data['lockouts'][ $key ] = array(
				'until'   => $until,
				'times'   => $long ? 0 : $times,
				'expires' => max( $until, $now + $reset ),
				'label'   => (string) ( $previous['label'] ?? '' ),
			);
			$locked[ $key ] = array(
				'until' => $until,
				'long'  => $long,
			);
		}
		return array(
			'data'   => self::trim_entries( $data ),
			'locked' => $locked,
		);
	}

	/**
	 * Drops counters and lockouts whose time is over.
	 *
	 * @param array $data  Data.
	 * @param int   $now   Current time.
	 * @param array $rules Rules.
	 * @return array
	 */
	public static function prune( array $data, int $now, array $rules ): array {
		foreach ( array( 'retries', 'lockouts' ) as $group ) {
			foreach ( (array) ( $data[ $group ] ?? array() ) as $key => $entry ) {
				if ( ! is_array( $entry ) || (int) ( $entry['expires'] ?? 0 ) <= $now ) {
					unset( $data[ $group ][ $key ] );
				}
			}
		}
		return $data;
	}

	/**
	 * Keeps the counters below MAX_ENTRIES (the oldest go first) and the log at LOG_SIZE.
	 *
	 * @param array $data Data.
	 * @return array
	 */
	private static function trim_entries( array $data ): array {
		foreach ( array( 'retries', 'lockouts' ) as $group ) {
			if ( count( $data[ $group ] ) > self::MAX_ENTRIES ) {
				uasort(
					$data[ $group ],
					static function ( $a, $b ) {
						return (int) $b['expires'] <=> (int) $a['expires'];
					}
				);
				$data[ $group ] = array_slice( $data[ $group ], 0, self::MAX_ENTRIES, true );
			}
		}
		$data['log'] = array_slice( (array) $data['log'], 0, self::LOG_SIZE );
		return $data;
	}

	/**
	 * The visitor's address: REMOTE_ADDR, or the client address a trusted proxy (or Cloudflare)
	 * passes on. Forwarded headers from anyone else are ignored, as they can be forged.
	 *
	 * @param array<string,mixed> $server     $_SERVER.
	 * @param string[]            $trusted    Trusted proxy addresses or ranges.
	 * @param bool                $cloudflare Whether Cloudflare's ranges are trusted.
	 * @return string
	 */
	public static function client_ip( array $server, array $trusted, bool $cloudflare ): string {
		$remote = self::valid_ip( (string) ( $server['REMOTE_ADDR'] ?? '' ) );
		if ( '' === $remote ) {
			return '';
		}
		$from_cloudflare = $cloudflare && self::ip_in_list( $remote, self::CLOUDFLARE );
		if ( ! $from_cloudflare && ! self::ip_in_list( $remote, $trusted ) ) {
			return $remote;
		}
		if ( $from_cloudflare && ! empty( $server['HTTP_CF_CONNECTING_IP'] ) ) {
			$ip = self::valid_ip( (string) $server['HTTP_CF_CONNECTING_IP'] );
			if ( '' !== $ip ) {
				return $ip;
			}
		}
		if ( ! empty( $server['HTTP_X_FORWARDED_FOR'] ) ) {
			// The right-most address that is not a trusted proxy is the one the proxies saw.
			$hops = array_reverse( array_map( 'trim', explode( ',', (string) $server['HTTP_X_FORWARDED_FOR'] ) ) );
			foreach ( $hops as $hop ) {
				$ip = self::valid_ip( $hop );
				if ( '' === $ip ) {
					break;
				}
				if ( ! self::ip_in_list( $ip, $trusted ) && ! ( $cloudflare && self::ip_in_list( $ip, self::CLOUDFLARE ) ) ) {
					return $ip;
				}
			}
		}
		return $remote;
	}

	/**
	 * A valid IP address in its normal form, or ''.
	 *
	 * @param string $ip Address.
	 * @return string
	 */
	public static function valid_ip( string $ip ): string {
		$ip = trim( $ip, " \t[]" );
		if ( false === filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return '';
		}
		$packed = inet_pton( $ip );
		return false === $packed ? '' : (string) inet_ntop( $packed );
	}

	/**
	 * Whether an address matches one of the addresses or CIDR ranges.
	 *
	 * @param string   $ip   Address.
	 * @param string[] $list Addresses and ranges ("192.0.2.0/24", "2001:db8::/32").
	 * @return bool
	 */
	public static function ip_in_list( string $ip, array $list ): bool {
		$packed = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid input only means "no match".
		if ( false === $packed ) {
			return false;
		}
		foreach ( $list as $entry ) {
			$entry = trim( (string) $entry );
			if ( '' === $entry ) {
				continue;
			}
			$bits = null;
			if ( false !== strpos( $entry, '/' ) ) {
				list( $entry, $bits ) = explode( '/', $entry, 2 );
				$bits                 = (int) $bits;
			}
			$range = @inet_pton( $entry ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- See above.
			if ( false === $range || strlen( $range ) !== strlen( $packed ) ) {
				continue;
			}
			$length = strlen( $packed ) * 8;
			$bits   = null === $bits ? $length : max( 0, min( $length, $bits ) );
			$bytes  = intdiv( $bits, 8 );
			if ( substr( $packed, 0, $bytes ) !== substr( $range, 0, $bytes ) ) {
				continue;
			}
			$rest = $bits % 8;
			if ( 0 === $rest ) {
				return true;
			}
			$mask = ( 0xff << ( 8 - $rest ) ) & 0xff;
			if ( ( ord( $packed[ $bytes ] ) & $mask ) === ( ord( $range[ $bytes ] ) & $mask ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * An address without its last part (IPv4 /24, IPv6 /48), for the log.
	 *
	 * @param string $ip Address.
	 * @return string
	 */
	public static function anonymize_ip( string $ip ): string {
		$packed = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid input gives ''.
		if ( false === $packed ) {
			return '';
		}
		$keep   = 4 === strlen( $packed ) ? 3 : 6;
		$packed = substr( $packed, 0, $keep ) . str_repeat( "\0", strlen( $packed ) - $keep );
		return (string) inet_ntop( $packed );
	}

	/**
	 * Lines of a list setting.
	 *
	 * @param string $value Setting value.
	 * @return string[]
	 */
	public static function lines( string $value ): array {
		return array_values( array_filter( array_map( 'trim', (array) preg_split( '~[\r\n,]+~', $value ) ) ) );
	}

	/*
	 * ---------------------------------------------------------------------------------------------
	 * WordPress side.
	 * ---------------------------------------------------------------------------------------------
	 */

	/**
	 * The visitor's address in this request.
	 *
	 * @return string
	 */
	public function ip(): string {
		if ( null === $this->ip ) {
			$this->ip = self::client_ip( $_SERVER, self::lines( (string) Settings::get( 'lockout_trusted_proxies' ) ), Settings::on( 'lockout_cloudflare' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Every address is validated with filter_var().
		}
		return $this->ip;
	}

	/**
	 * Stored data.
	 *
	 * @return array
	 */
	public static function data(): array {
		$data = get_option( self::OPTION, array() );
		return array_merge( self::empty_data(), is_array( $data ) ? $data : array() );
	}

	/**
	 * Stores the data (not autoloaded: only login requests and the settings screen read it).
	 *
	 * @param array $data Data.
	 */
	private static function save( array $data ): void {
		update_option( self::OPTION, $data, false );
	}

	/**
	 * Counter key of an address (a keyed hash, so the stored counters do not list addresses).
	 *
	 * @param string $ip Address.
	 * @return string
	 */
	public static function ip_key( string $ip ): string {
		return 'ip:' . substr( hash_hmac( 'sha256', strtolower( $ip ), (string) Settings::get( 'salt' ) ), 0, 32 );
	}

	/**
	 * Counter key of a user name.
	 *
	 * @param string $login User name or e-mail address.
	 * @return string
	 */
	public static function user_key( string $login ): string {
		return 'user:' . substr( hash_hmac( 'sha256', strtolower( trim( $login ) ), (string) Settings::get( 'salt' ) ), 0, 32 );
	}

	/**
	 * Whether the address is on the allow-list (never counted, never locked out).
	 *
	 * @param string $ip Address.
	 * @return bool
	 */
	public static function is_allowed( string $ip ): bool {
		return '' !== $ip && self::ip_in_list( $ip, self::lines( (string) Settings::get( 'lockout_allow' ) ) );
	}

	/**
	 * Whether the address is on the deny-list (never signs in).
	 *
	 * @param string $ip Address.
	 * @return bool
	 */
	public static function is_denied( string $ip ): bool {
		return '' !== $ip && ! self::is_allowed( $ip ) && self::ip_in_list( $ip, self::lines( (string) Settings::get( 'lockout_deny' ) ) );
	}

	/**
	 * Counter keys of an attempt.
	 *
	 * @param string $login User name tried.
	 * @return string[]
	 */
	private function keys( string $login ): array {
		$keys = array();
		if ( '' !== $this->ip() ) {
			$keys[] = self::ip_key( $this->ip() );
		}
		if ( Settings::on( 'lockout_by_username' ) && '' !== trim( $login ) ) {
			$keys[] = self::user_key( $login );
		}
		return $keys;
	}

	/**
	 * Until when this attempt is locked out (0: it is not).
	 *
	 * @param string $login User name tried.
	 * @return int
	 */
	private function current_lock( string $login ): int {
		if ( Request::is_cli() || self::is_allowed( $this->ip() ) ) {
			return 0;
		}
		return self::locked_until( self::data(), $this->keys( $login ), time() );
	}

	/**
	 * Message for a locked-out visitor (says nothing about the account or the software).
	 *
	 * @param int $until Lockout end.
	 * @return string
	 */
	public static function locked_message( int $until ): string {
		$minutes = max( 1, (int) ceil( ( $until - time() ) / 60 ) );
		/* translators: %d: minutes */
		return sprintf( _n( 'Too many failed login attempts. Try again in %d minute.', 'Too many failed login attempts. Try again in %d minutes.', $minutes, 'mg-stealth' ), $minutes );
	}

	/**
	 * The same message for an unknown user, a wrong password and an unknown e-mail address.
	 *
	 * @return string
	 */
	public static function generic_message(): string {
		return __( 'The user name or password is incorrect.', 'mg-stealth' );
	}

	/**
	 * Login form and XML-RPC: refuses locked-out and denied visitors (even with the right
	 * password) and makes every other failure look the same.
	 *
	 * @param mixed  $user     User or error so far.
	 * @param string $username User name.
	 * @param string $password Password.
	 * @return mixed
	 */
	public function filter_authenticate( $user, $username = '', $password = '' ) {
		if ( Request::is_cli() ) {
			return $user;
		}
		$username = (string) $username;
		if ( self::is_denied( $this->ip() ) ) {
			return new \WP_Error( 'mgs_failed', self::generic_message() );
		}
		if ( '' !== $username || '' !== (string) $password ) {
			$until = $this->current_lock( $username );
			if ( $until > 0 ) {
				return new \WP_Error( 'mgs_locked', self::locked_message( $until ) );
			}
		}
		if ( is_wp_error( $user ) && array_intersect( $user->get_error_codes(), array( 'invalid_username', 'incorrect_password', 'invalid_email' ) ) ) {
			return new \WP_Error( 'mgs_failed', self::generic_message() );
		}
		return $user;
	}

	/**
	 * A failed sign-in on the login form or over XML-RPC.
	 *
	 * @param string $username User name.
	 * @param mixed  $error    Error.
	 */
	public function on_login_failed( $username, $error = null ): void {
		if ( $error instanceof \WP_Error && 'mgs_locked' === $error->get_error_code() ) {
			return;
		}
		$this->count_failure( (string) $username, ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) ? 'xmlrpc' : 'login' );
	}

	/**
	 * A successful sign-in clears the address' failed attempts (not its lockouts).
	 *
	 * @param string $username User name.
	 */
	public function on_login( $username ): void {
		$data    = self::data();
		$changed = false;
		foreach ( $this->keys( (string) $username ) as $key ) {
			if ( isset( $data['retries'][ $key ] ) ) {
				unset( $data['retries'][ $key ] );
				$changed = true;
			}
		}
		if ( $changed ) {
			self::save( $data );
		}
	}

	/**
	 * Application passwords (REST API, XML-RPC): a locked-out or denied visitor is refused even
	 * with the right password.
	 *
	 * @param \WP_Error $error Errors so far.
	 * @param mixed     $user  User.
	 */
	public function on_application_password( $error, $user = null ): void {
		if ( ! $error instanceof \WP_Error || Request::is_cli() ) {
			return;
		}
		$login = $user instanceof \WP_User ? (string) $user->user_login : '';
		if ( self::is_denied( $this->ip() ) ) {
			$error->add( 'mgs_failed', self::generic_message() );
			return;
		}
		$until = $this->current_lock( $login );
		if ( $until > 0 ) {
			$error->add( 'mgs_locked', self::locked_message( $until ) );
		}
	}

	/**
	 * A failed application password.
	 *
	 * @param mixed $error Error.
	 */
	public function on_application_password_failed( $error ): void {
		if ( $error instanceof \WP_Error && in_array( 'mgs_locked', $error->get_error_codes(), true ) ) {
			return;
		}
		$login = isset( $_SERVER['PHP_AUTH_USER'] ) ? sanitize_user( wp_unslash( (string) $_SERVER['PHP_AUTH_USER'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitize_user().
		$this->count_failure( $login, 'rest' );
	}

	/**
	 * Counts a failure; on a new lockout writes the log entry and the optional e-mail.
	 *
	 * @param string $login User name tried.
	 * @param string $via   login | xmlrpc | rest.
	 */
	private function count_failure( string $login, string $via ): void {
		if ( Request::is_cli() || self::is_allowed( $this->ip() ) ) {
			return;
		}
		$keys = $this->keys( $login );
		if ( empty( $keys ) ) {
			return;
		}
		$now    = time();
		$result = self::record_failure( self::data(), $keys, $now, self::rules( Settings::all() ) );
		$data   = $result['data'];
		if ( ! empty( $result['locked'] ) ) {
			$ip    = Settings::on( 'lockout_anonymize' ) ? self::anonymize_ip( $this->ip() ) : $this->ip();
			$until = 0;
			$long  = false;
			foreach ( $result['locked'] as $key => $lock ) {
				$until = max( $until, $lock['until'] );
				$long  = $long || $lock['long'];
				$data['lockouts'][ $key ]['label'] = 0 === strpos( $key, 'user:' ) ? $this->log_login( $login ) : $ip;
			}
			array_unshift(
				$data['log'],
				array(
					'time'  => $now,
					'ip'    => $ip,
					'user'  => $this->log_login( $login ),
					'via'   => $via,
					'until' => $until,
					'long'  => $long,
					'keys'  => array_keys( $result['locked'] ),
				)
			);
			$data['log'] = array_slice( $data['log'], 0, self::LOG_SIZE );
			if ( Settings::on( 'lockout_notify' ) ) {
				$this->notify( $ip, $login, $until, $via );
			}
		}
		self::save( $data );
	}

	/**
	 * User name as stored in the log (never a password; at most 60 characters).
	 *
	 * @param string $login User name tried.
	 * @return string
	 */
	private function log_login( string $login ): string {
		return function_exists( 'mb_substr' ) ? mb_substr( sanitize_user( $login ), 0, 60 ) : substr( sanitize_user( $login ), 0, 60 );
	}

	/**
	 * E-mail to the site administrator about a lockout.
	 *
	 * @param string $ip    Address (as logged).
	 * @param string $login User name tried.
	 * @param int    $until Lockout end.
	 * @param string $via   Where it happened.
	 */
	private function notify( string $ip, string $login, int $until, string $via ): void {
		$site = wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES );
		/* translators: %s: site name */
		$subject = sprintf( __( '[%s] Login lockout', 'mg-stealth' ), $site );
		$lines   = array(
			/* translators: %s: IP address */
			sprintf( __( 'Address: %s', 'mg-stealth' ), $ip ),
			/* translators: %s: user name */
			sprintf( __( 'User name tried: %s', 'mg-stealth' ), $this->log_login( $login ) ),
			/* translators: %s: login, xmlrpc or rest */
			sprintf( __( 'Where: %s', 'mg-stealth' ), $via ),
			/* translators: %s: date and time */
			sprintf( __( 'Locked out until: %s', 'mg-stealth' ), wp_date( 'Y-m-d H:i', $until ) ),
			'',
			__( 'Unlock: Settings → MG Stealth → Login attempts, or WP-CLI "wp mg-stealth unlock <address>".', 'mg-stealth' ),
		);
		wp_mail( (string) get_option( 'admin_email' ), $subject, implode( "\n", $lines ) );
	}

	/**
	 * The login page: the normal 404 page for denied addresses.
	 */
	public function guard_login_page(): void {
		if ( ! Request::is_cli() && self::is_denied( $this->ip() ) ) {
			Request::serve_404();
		}
	}

	/**
	 * The login form shakes on the plugin's errors too.
	 *
	 * @param string[] $codes Error codes.
	 * @return string[]
	 */
	public function shake_codes( $codes ): array {
		$codes   = (array) $codes;
		$codes[] = 'mgs_locked';
		$codes[] = 'mgs_failed';
		return $codes;
	}

	/**
	 * Lifts a lockout (and clears the failed attempts) for the given counter keys.
	 *
	 * @param string[] $keys Counter keys.
	 * @return int Number of lockouts lifted.
	 */
	public static function unlock( array $keys ): int {
		$data  = self::data();
		$count = 0;
		foreach ( $keys as $key ) {
			if ( isset( $data['lockouts'][ $key ] ) ) {
				unset( $data['lockouts'][ $key ] );
				++$count;
			}
			unset( $data['retries'][ $key ] );
		}
		self::save( $data );
		return $count;
	}

	/**
	 * Lifts every lockout and clears every counter (the log stays).
	 *
	 * @return int Number of lockouts lifted.
	 */
	public static function unlock_all(): int {
		$data             = self::data();
		$count            = count( $data['lockouts'] );
		$data['lockouts'] = array();
		$data['retries']  = array();
		self::save( $data );
		return $count;
	}

	/**
	 * Empties the log.
	 */
	public static function clear_log(): void {
		$data        = self::data();
		$data['log'] = array();
		self::save( $data );
	}

	/**
	 * Active lockouts, newest end first.
	 *
	 * @return array<string,array{until:int,label:string}>
	 */
	public static function active(): array {
		$now    = time();
		$active = array();
		foreach ( self::data()['lockouts'] as $key => $entry ) {
			if ( is_array( $entry ) && (int) $entry['until'] > $now ) {
				$active[ $key ] = array(
					'until' => (int) $entry['until'],
					'label' => (string) ( $entry['label'] ?? '' ),
				);
			}
		}
		uasort(
			$active,
			static function ( $a, $b ) {
				return $b['until'] <=> $a['until'];
			}
		);
		return $active;
	}

	/*
	 * ---------------------------------------------------------------------------------------------
	 * Limit Login Attempts Reloaded.
	 * ---------------------------------------------------------------------------------------------
	 */

	/**
	 * Whether Limit Login Attempts Reloaded is active.
	 *
	 * @return bool
	 */
	public static function llar_active(): bool {
		return defined( 'LLA_PLUGIN_URL' ) || defined( 'LLA_PLUGIN_DIR' ) || class_exists( '\LLAR\Core\LimitLoginAttempts' );
	}

	/**
	 * Settings of Limit Login Attempts Reloaded that can be taken over, or an empty array when it
	 * has none stored.
	 *
	 * @return array<string,mixed>
	 */
	public static function llar_settings(): array {
		$get = static function ( string $name ) {
			$value = get_option( 'limit_login_' . $name, null );
			return null === $value ? get_option( 'llar_' . $name, null ) : $value;
		};
		$out = array();
		$map = array(
			'allowed_retries'  => array( 'lockout_retries', 1 ),
			'lockout_duration' => array( 'lockout_minutes', 60 ),
			'allowed_lockouts' => array( 'lockout_escalate', 1 ),
			'long_duration'    => array( 'lockout_long_hours', 3600 ),
			'valid_duration'   => array( 'lockout_reset_hours', 3600 ),
		);
		foreach ( $map as $name => $target ) {
			$value = $get( $name );
			if ( is_numeric( $value ) && (int) $value > 0 ) {
				$out[ $target[0] ] = max( 1, (int) round( (int) $value / $target[1] ) );
			}
		}
		foreach ( array( 'whitelist' => 'lockout_allow', 'blacklist' => 'lockout_deny' ) as $name => $target ) {
			$value = $get( $name );
			$list  = array();
			foreach ( (array) ( is_string( $value ) ? self::lines( $value ) : $value ) as $line ) {
				$line = trim( (string) $line );
				// LLAR also accepts ranges like 10.0.0.1-10.0.0.9; only plain addresses and CIDR ranges are taken over.
				if ( '' !== Settings::clean_ip_entry( $line ) ) {
					$list[] = Settings::clean_ip_entry( $line );
				}
			}
			if ( ! empty( $list ) ) {
				$out[ $target ] = implode( "\n", array_unique( $list ) );
			}
		}
		$notify = $get( 'lockout_notify' );
		if ( is_string( $notify ) && false !== strpos( $notify, 'email' ) ) {
			$out['lockout_notify'] = true;
		}
		$origins = $get( 'trusted_ip_origins' );
		if ( ( is_string( $origins ) && false !== stripos( $origins, 'CF_CONNECTING' ) ) || ( is_array( $origins ) && false !== stripos( implode( ',', $origins ), 'CF_CONNECTING' ) ) ) {
			$out['lockout_cloudflare'] = true;
		}
		return $out;
	}
}
