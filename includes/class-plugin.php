<?php
/**
 * Bootstrap and lifecycle.
 *
 * @package MG_Stealth
 */

namespace MG_Stealth;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin object.
 */
final class Plugin {

	/**
	 * Instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Whether the plugin is being deactivated in this request.
	 *
	 * @var bool
	 */
	private static $deactivating = false;

	/**
	 * Whether the server rules should be synchronised at the end of this request.
	 *
	 * @var bool
	 */
	private $sync_pending = false;

	/**
	 * Output filter.
	 *
	 * @var Cleaner
	 */
	private $cleaner;

	/**
	 * The instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Output filter.
	 *
	 * @return Cleaner
	 */
	public function cleaner(): Cleaner {
		return $this->cleaner;
	}

	/**
	 * Whether the emergency constant switches everything off.
	 *
	 * @return bool
	 */
	public static function disabled_by_constant(): bool {
		return defined( 'MG_STEALTH_DISABLE' ) && MG_STEALTH_DISABLE;
	}

	/**
	 * Registers everything.
	 */
	public function boot(): void {
		$this->cleaner = new Cleaner();

		add_action( 'init', array( $this, 'load_textdomain' ), 0 );

		if ( is_admin() ) {
			( new Admin() )->register();
		}
		if ( class_exists( __NAMESPACE__ . '\\Updater' ) ) {
			( new Updater() )->register(); // Only in the GitLab build.
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once MG_STEALTH_DIR . 'includes/class-cli.php';
			\WP_CLI::add_command( 'mg-stealth', __NAMESPACE__ . '\\CLI' );
		}

		if ( self::disabled_by_constant() ) {
			// Emergency mode: no rules at all, and the server block goes away on the next request.
			add_action( 'init', array( $this, 'emergency_cleanup' ), 1 );
			return;
		}

		$redirects = new Redirects();
		add_action( 'plugins_loaded', array( $redirects, 'maybe_redirect' ), PHP_INT_MIN );

		$access = new Access();
		$access->register();
		add_action( 'plugins_loaded', array( $access, 'guard_entry_points' ), 0 );

		( new Login() )->register();
		$this->cleaner->register();

		add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ), 999 );
		add_action( 'init', array( $this, 'after_emergency' ), 1000 );
		add_action( 'update_option_' . Settings::OPTION, array( $this, 'on_settings_updated' ), 10, 2 );
		add_action( 'add_option_' . Settings::OPTION, array( $this, 'on_settings_added' ), 10, 2 );
		foreach ( array( 'activated_plugin', 'deactivated_plugin', 'switch_theme', 'upgrader_process_complete' ) as $hook ) {
			add_action( $hook, array( $this, 'request_sync' ) );
		}
		add_action( 'shutdown', array( $this, 'run_pending_sync' ) );
		add_action( 'init', array( $this, 'detect_moved_site' ), 1001 );
	}

	/**
	 * A copy of the site on another server or address (database and .htaccess deployed from a
	 * local site): the stored rules belong to the old place, so the original paths are used and
	 * the rules are rewritten and verified here at the end of this request (at most every 5 minutes).
	 */
	public function detect_moved_site(): void {
		if ( 'none' === State::get( 'rules_status' ) && ! Settings::on( 'paths_enabled' ) ) {
			return;
		}
		if ( State::matches_site() || Request::is_cli() || get_transient( 'mg_stealth_resync' ) ) {
			return;
		}
		set_transient( 'mg_stealth_resync', 1, 5 * MINUTE_IN_SECONDS );
		$this->sync_pending = true;
	}

	/**
	 * Translations (the GitLab build is not served by translate.wordpress.org).
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'mg-stealth', false, dirname( MG_STEALTH_BASENAME ) . '/languages' ); // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Needed outside WordPress.org.
	}

	/**
	 * Activation: per-site names, server rules.
	 */
	public static function activate(): void {
		Settings::flush_cache();
		Settings::ensure_initialized();
		// A site that used WPS Hide Login keeps its private address (still off until enabled here).
		$whl = sanitize_title( (string) get_option( 'whl_page', '' ) );
		if ( '' !== $whl && Settings::is_valid_slug( $whl ) && ! Settings::on( 'login_enabled' ) ) {
			$all               = Settings::all();
			$all['login_slug'] = $whl;
			update_option( Settings::OPTION, $all, true );
			Settings::flush_cache();
		}
		State::update( array( 'flush_rewrite' => true ) );
		if ( ! self::disabled_by_constant() ) {
			Server_Rules::sync();
		}
	}

	/**
	 * Deactivation: everything back to WordPress' defaults (the settings stay for later).
	 */
	public static function deactivate(): void {
		self::$deactivating = true;
		Server_Rules::remove();
		delete_option( 'rewrite_rules' ); // WordPress rebuilds its rules (REST prefix, sitemap) without this plugin.
		Request::forget_404_page();
	}

	/**
	 * Emergency constant: remove the server block once.
	 */
	public function emergency_cleanup(): void {
		if ( ! State::get( 'emergency' ) || null !== Htaccess::extract_block( Server_Rules::read() ) ) {
			Server_Rules::remove();
			delete_option( 'rewrite_rules' );
			Request::forget_404_page();
			State::update( array( 'emergency' => true ) );
		}
	}

	/**
	 * The emergency constant was removed again: write the server rules back once.
	 */
	public function after_emergency(): void {
		if ( State::get( 'emergency' ) ) {
			State::update(
				array(
					'emergency'     => false,
					'flush_rewrite' => true,
				)
			);
			Server_Rules::sync();
		}
	}

	/**
	 * Rebuilds WordPress' rewrite rules after a change of the REST prefix or the sitemap names.
	 */
	public function maybe_flush_rewrite_rules(): void {
		if ( State::get( 'flush_rewrite' ) ) {
			State::update( array( 'flush_rewrite' => false ) );
			flush_rewrite_rules( false );
		}
	}

	/**
	 * Settings saved: server rules now (the admin sees the result on the next screen).
	 *
	 * @param mixed $old_value Old settings.
	 * @param mixed $value     New settings.
	 */
	public function on_settings_updated( $old_value, $value ): void {
		Settings::flush_cache();
		$old = is_array( $old_value ) ? $old_value : array();
		$new = is_array( $value ) ? $value : array();
		foreach ( array( 'paths_enabled', 'rest_prefix', 'neutral_sitemap' ) as $key ) {
			if ( ( $old[ $key ] ?? null ) !== ( $new[ $key ] ?? null ) ) {
				State::update( array( 'flush_rewrite' => true ) );
				break;
			}
		}
		Request::forget_404_page();
		if ( ! self::$deactivating ) {
			Server_Rules::sync();
		}
		$this->sync_pending = false;
	}

	/**
	 * Settings stored for the first time.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Settings.
	 */
	public function on_settings_added( $option, $value ): void {
		Settings::flush_cache();
	}

	/**
	 * Plugins or theme changed: the hashed folder names change with them.
	 */
	public function request_sync(): void {
		$this->sync_pending = true;
	}

	/**
	 * Runs a requested synchronisation at the end of the request.
	 */
	public function run_pending_sync(): void {
		if ( ! $this->sync_pending || self::$deactivating || ! is_plugin_active_check() ) {
			return;
		}
		$this->sync_pending = false;
		Settings::flush_cache();
		Server_Rules::sync();
	}
}

/**
 * Whether this plugin is still active (it may have been deactivated in this request).
 *
 * @return bool
 */
function is_plugin_active_check(): bool {
	return in_array( MG_STEALTH_BASENAME, (array) get_option( 'active_plugins', array() ), true );
}
