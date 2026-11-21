<?php
/**
 * WP-CLI commands: status, server rules, login address, self-check.
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
