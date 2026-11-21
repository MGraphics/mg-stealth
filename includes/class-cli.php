<?php
/**
 * WP-CLI commands: status, server rules, login address, lockouts, self-check.
 *
 * @package MG_Stealth
 */

namespace MG_Stealth;

defined( 'ABSPATH' ) || exit;

/**
 * Manages MG Stealth.
 */
final class CLI {

	/**
	 * Shows the state of the server rules and the main settings.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function status( $args, $assoc_args ): void {
		$state = State::all();
		\WP_CLI::line( 'Server rules:   ' . $state['rules_status'] . ( $state['rules_error'] ? ' (' . $state['rules_error'] . ')' : '' ) );
		\WP_CLI::line( 'Neutral paths:  ' . ( Paths::active() ? 'active' : ( Settings::on( 'paths_enabled' ) ? 'enabled, not verified' : 'off' ) ) );
		\WP_CLI::line( 'Private login:  ' . ( Login::enabled() ? 'on' : 'off' ) );
		\WP_CLI::line( 'Login lockout:  ' . ( Lockout::enabled() ? 'on (' . count( Lockout::active() ) . ' active)' : 'off' ) );
		\WP_CLI::line( 'Emergency off:  ' . ( Plugin::disabled_by_constant() ? 'yes (MG_STEALTH_DISABLE)' : 'no' ) );
	}

	/**
	 * Writes (or removes) the .htaccess block according to the settings and verifies it.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function sync( $args, $assoc_args ): void {
		Settings::flush_cache();
		$result = Server_Rules::sync();
		\WP_CLI::line( $result['status'] . ( $result['error'] ? ': ' . $result['error'] : '' ) );
		if ( 'failed' === $result['status'] ) {
			\WP_CLI::halt( 1 );
		}
	}

	/**
	 * Removes the .htaccess block (emergency).
	 *
	 * @subcommand remove-rules
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function remove_rules( $args, $assoc_args ): void {
		Server_Rules::remove();
		\WP_CLI::success( 'The MG Stealth block was removed from .htaccess.' );
	}

	/**
	 * Prints the rules the settings need.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function rules( $args, $assoc_args ): void {
		\WP_CLI::line( Server_Rules::desired() );
	}

	/**
	 * Prints the private login address.
	 *
	 * @subcommand login-url
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function login_url( $args, $assoc_args ): void {
		\WP_CLI::line( Login::enabled() ? Login::url() : wp_login_url() );
	}

	/**
	 * Lifts the lockout of an address or a user name after failed login attempts.
	 *
	 * [<address>]
	 * : IP address of the visitor.
	 *
	 * [--user=<login>]
	 * : User name (when lockouts per user name are on).
	 *
	 * [--all]
	 * : Lift every lockout and clear every counter.
	 *
	 * ## EXAMPLES
	 *
	 *     wp mg-stealth unlock 203.0.113.7
	 *     wp mg-stealth unlock --user=admin
	 *     wp mg-stealth unlock --all
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function unlock( $args, $assoc_args ): void {
		if ( ! empty( $assoc_args['all'] ) ) {
			\WP_CLI::success( sprintf( 'Lockouts lifted: %d.', Lockout::unlock_all() ) );
			return;
		}
		$keys = array();
		if ( ! empty( $args[0] ) ) {
			$ip = Lockout::valid_ip( (string) $args[0] );
			if ( '' === $ip ) {
				\WP_CLI::error( 'Not a valid IP address: ' . $args[0] );
			}
			$keys[] = Lockout::ip_key( $ip );
		}
		if ( ! empty( $assoc_args['user'] ) ) {
			$keys[] = Lockout::user_key( (string) $assoc_args['user'] );
		}
		if ( empty( $keys ) ) {
			\WP_CLI::error( 'Give an IP address, --user=<login> or --all.' );
		}
		$count = Lockout::unlock( $keys );
		\WP_CLI::success( $count > 0 ? sprintf( 'Lockouts lifted: %d.', $count ) : 'No active lockout; failed attempts cleared.' );
	}

	/**
	 * Lists the active lockouts after failed login attempts.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function lockouts( $args, $assoc_args ): void {
		$rows = array();
		foreach ( Lockout::active() as $key => $lock ) {
			$rows[] = array(
				'who'   => ( 0 === strpos( $key, 'user:' ) ? 'user ' : '' ) . $lock['label'],
				'until' => wp_date( 'Y-m-d H:i', $lock['until'] ),
			);
		}
		if ( empty( $rows ) ) {
			\WP_CLI::line( 'No active lockouts.' );
			return;
		}
		\WP_CLI\Utils\format_items( 'table', $rows, array( 'who', 'until' ) );
	}

	/**
	 * Runs the self-check (anonymous requests to the site) and prints the results.
	 *
	 * [--format=<format>]
	 * : table or json.
	 * ---
	 * default: table
	 * ---
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function check( $args, $assoc_args ): void {
		$result = ( new Self_Check() )->run();
		if ( 'json' === ( $assoc_args['format'] ?? 'table' ) ) {
			\WP_CLI::line( (string) wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			return;
		}
		$rows = array();
		foreach ( $result['urls'] as $row ) {
			$rows[] = array(
				'url'      => $row['path'],
				'code'     => $row['code'],
				'location' => $row['location'],
				'verdict'  => $row['verdict'],
			);
		}
		\WP_CLI\Utils\format_items( 'table', $rows, array( 'url', 'code', 'location', 'verdict' ) );
		foreach ( $result['traces'] as $page ) {
			\WP_CLI::line( $page['label'] . ': ' . ( empty( $page['found'] ) ? 'clean' : implode( ', ', array_map( static fn( $k, $v ) => $k . ' ×' . $v['count'], array_keys( $page['found'] ), $page['found'] ) ) ) );
		}
	}
}
