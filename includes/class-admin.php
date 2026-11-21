<?php
/**
 * Settings screen (Settings → MG Stealth).
 *
 * @package MG_Stealth
 */

namespace MG_Stealth;

defined( 'ABSPATH' ) || exit;

/**
 * Admin screen.
 */
final class Admin {

	const PAGE = 'mg-stealth';

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_mg_stealth_recheck', array( $this, 'handle_recheck' ) );
		add_action( 'admin_post_mg_stealth_unlock', array( $this, 'handle_unlock' ) );
		add_action( 'admin_post_mg_stealth_llar_import', array( $this, 'handle_llar_import' ) );
		add_action( 'admin_notices', array( $this, 'notices' ) );
		add_filter( 'plugin_action_links_' . MG_STEALTH_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Menu entry.
	 */
	public function menu(): void {
		add_options_page( 'MG Stealth', 'MG Stealth', 'manage_options', self::PAGE, array( $this, 'render' ) );
	}

	/**
	 * The option and its validation.
	 */
	public function register_setting(): void {
		register_setting(
			'mg_stealth',
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Styles of the settings screen.
	 *
	 * @param string $hook Screen hook.
	 */
	public function assets( $hook ): void {
		if ( 'settings_page_' . self::PAGE !== $hook ) {
			return;
		}
		wp_enqueue_style( 'mg-stealth-admin', plugins_url( 'assets/admin.css', MG_STEALTH_FILE ), array(), MG_STEALTH_VERSION );
	}

	/**
	 * "Settings" link on the plugins screen.
	 *
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public function action_links( $links ): array {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Settings', 'mg-stealth' ) . '</a>' );
		return (array) $links;
	}

	/**
	 * Address of a tab.
	 *
	 * @param string $tab  Tab.
	 * @param array  $args Extra query arguments.
	 * @return string
	 */
	public static function url( string $tab = 'source', array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => self::PAGE, 'tab' => $tab ), $args ), admin_url( 'options-general.php' ) );
	}

	/**
	 * "Check again" button: rewrites and re-verifies the server rules.
	 */
	public function handle_recheck(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'mg-stealth' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'mg_stealth_recheck' );
		Settings::flush_cache();
		$result = Server_Rules::sync();
		if ( 'manual' === $result['status'] ) {
			Server_Rules::recheck_manual();
		}
		$tab = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'paths';
		wp_safe_redirect( self::url( $tab, array( 'rechecked' => 1 ) ) );
		exit;
	}

	/**
	 * Lockout tab: lifts one or every lockout, or empties the log.
	 */
	public function handle_unlock(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'mg-stealth' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'mg_stealth_unlock' );
		$what = isset( $_POST['what'] ) ? sanitize_text_field( wp_unslash( $_POST['what'] ) ) : '';
		if ( 'all' === $what ) {
			Lockout::unlock_all();
		} elseif ( 'log' === $what ) {
			Lockout::clear_log();
		} elseif ( preg_match( '~^(ip|user):[a-f0-9]{32}$~', $what ) ) {
			Lockout::unlock( array( $what ) );
		}
		wp_safe_redirect( self::url( 'lockout', array( 'unlocked' => 1 ) ) );
		exit;
	}

	/**
	 * Lockout tab: takes over the settings of Limit Login Attempts Reloaded.
	 */
	public function handle_llar_import(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'mg-stealth' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'mg_stealth_llar_import' );
		$import = Lockout::llar_settings();
		if ( ! empty( $import ) ) {
			$import['lockout_enabled'] = true;
			update_option( Settings::OPTION, array_merge( Settings::all(), $import ) );
			Settings::flush_cache();
		}
		wp_safe_redirect( self::url( 'lockout', array( 'imported' => empty( $import ) ? 0 : 1 ) ) );
		exit;
	}

	/**
	 * Notices on every admin screen (emergency mode, conflicting plugin) and on this screen.
	 */
	public function notices(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( Plugin::disabled_by_constant() ) {
			echo '<div class="notice notice-warning"><p>' . wp_kses_post( __( '<strong>MG Stealth is switched off</strong> by the MG_STEALTH_DISABLE constant in wp-config.php: no rules are active and the .htaccess block has been removed. Delete the constant to switch it on again.', 'mg-stealth' ) ) . '</p></div>';
			return;
		}
		if ( Login::enabled() && ( defined( 'WPS_HIDE_LOGIN_BASENAME' ) || class_exists( '\WPS\WPS_Hide_Login\Plugin' ) ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'MG Stealth: WPS Hide Login is active as well. Deactivate it; MG Stealth already serves the login form at the private address.', 'mg-stealth' ) . '</p></div>';
		}
		if ( Lockout::enabled() && Lockout::llar_active() ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'MG Stealth: Limit Login Attempts Reloaded is active as well. Both count failed logins; deactivate Limit Login Attempts Reloaded, MG Stealth already locks out repeated failed attempts.', 'mg-stealth' ) . '</p></div>';
		}
	}

	/**
	 * The screen.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tabs = array(
			'source'    => __( 'Page source', 'mg-stealth' ),
			'paths'     => __( 'Paths', 'mg-stealth' ),
			'login'     => __( 'Login', 'mg-stealth' ),
			'access'    => __( 'Access', 'mg-stealth' ),
			'redirects' => __( 'Redirects', 'mg-stealth' ),
			'lockout'   => __( 'Login attempts', 'mg-stealth' ),
			'check'     => __( 'Check', 'mg-stealth' ),
			'help'      => __( 'Help', 'mg-stealth' ),
		);
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'source'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab selection only.
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'source';
		}

		echo '<div class="wrap mg-stealth-admin">';
		echo '<h1>MG Stealth</h1>';
		echo '<p class="mgs-lead">' . esc_html__( 'Makes it hard to tell from the page source and the usual addresses that this site runs WordPress. Every change can be undone: deactivating the plugin restores WordPress\' defaults.', 'mg-stealth' ) . '</p>';
		settings_errors( Settings::OPTION );
		$this->status_box();

		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $key => $label ) {
			printf( '<a href="%s" class="nav-tab%s">%s</a>', esc_url( self::url( $key ) ), $key === $tab ? ' nav-tab-active' : '', esc_html( $label ) );
		}
		echo '</nav>';

		echo '<div class="mgs-tab">';
		switch ( $tab ) {
			case 'paths':
				$this->tab_paths();
				break;
			case 'login':
				$this->tab_login();
				break;
			case 'access':
				$this->tab_access();
				break;
			case 'redirects':
				$this->tab_redirects();
				break;
			case 'lockout':
				$this->tab_lockout();
				break;
			case 'check':
				$this->tab_check();
				break;
			case 'help':
				$this->tab_help();
				break;
			default:
				$this->tab_source();
		}
		echo '</div>';
		echo '<p class="mgs-footer">MG Stealth ' . esc_html( MG_STEALTH_VERSION ) . ' · <a href="https://mgraphics.com.pl" target="_blank" rel="noopener">MGraphics</a></p>';
		echo '</div>';
	}

	/**
	 * Summary above the tabs.
	 */
	private function status_box(): void {
		$state = State::all();
		$items = array();

		$rules = array(
			'none'    => __( 'not needed', 'mg-stealth' ),
			'written' => __( 'written to .htaccess', 'mg-stealth' ),
			'manual'  => __( 'to be copied by hand', 'mg-stealth' ),
			'failed'  => __( 'rejected by the server', 'mg-stealth' ),
		);
		$items[] = array(
			__( 'Server rules', 'mg-stealth' ),
			$rules[ $state['rules_status'] ] ?? (string) $state['rules_status'],
			in_array( $state['rules_status'], array( 'none', 'written' ), true ) ? 'ok' : 'warn',
		);
		if ( Settings::on( 'paths_enabled' ) ) {
			$items[] = array( __( 'Neutral paths', 'mg-stealth' ), Paths::active() ? __( 'active', 'mg-stealth' ) : __( 'waiting for the server rules', 'mg-stealth' ), Paths::active() ? 'ok' : 'warn' );
		} else {
			$items[] = array( __( 'Neutral paths', 'mg-stealth' ), __( 'off', 'mg-stealth' ), 'off' );
		}
		$items[] = array( __( 'Private login', 'mg-stealth' ), Login::enabled() ? __( 'on', 'mg-stealth' ) : __( 'off', 'mg-stealth' ), Login::enabled() ? 'ok' : 'off' );
		$items[] = array( __( 'Login lockout', 'mg-stealth' ), Lockout::enabled() ? __( 'on', 'mg-stealth' ) : __( 'off', 'mg-stealth' ), Lockout::enabled() ? 'ok' : 'off' );
		$items[] = array( __( 'REST API for guests', 'mg-stealth' ), Settings::on( 'rest_guests_closed' ) ? __( 'closed (allow-list)', 'mg-stealth' ) : __( 'open', 'mg-stealth' ), Settings::on( 'rest_guests_closed' ) ? 'ok' : 'off' );

		echo '<ul class="mgs-status">';
		foreach ( $items as $item ) {
			printf( '<li class="mgs-%s"><span>%s</span> <strong>%s</strong></li>', esc_attr( $item[2] ), esc_html( $item[0] ), esc_html( $item[1] ) );
		}
		echo '</ul>';
		if ( '' !== (string) $state['rules_error'] ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html( (string) $state['rules_error'] ) . '</p></div>';
		}
	}

	/**
	 * Opens a settings form for a tab.
	 *
	 * @param string $tab Tab.
	 */
	private function form_open( string $tab ): void {
		echo '<form method="post" action="options.php">';
		settings_fields( 'mg_stealth' );
		printf( '<input type="hidden" name="%s[_tab]" value="%s">', esc_attr( Settings::OPTION ), esc_attr( $tab ) );
		echo '<table class="form-table" role="presentation"><tbody>';
	}

	/**
	 * Closes a settings form.
	 */
	private function form_close(): void {
		echo '</tbody></table>';
		submit_button();
		echo '</form>';
	}

	/**
	 * Checkbox row.
	 *
	 * @param string $key         Setting.
	 * @param string $label       Label.
	 * @param string $description Description (may contain <code>).
	 */
	private function checkbox( string $key, string $label, string $description = '' ): void {
		$id = 'mgs-' . $key;
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>';
		printf(
			'<label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s[%3$s]" value="1"%4$s> %5$s</label>',
			esc_attr( $id ),
			esc_attr( Settings::OPTION ),
			esc_attr( $key ),
			checked( Settings::on( $key ), true, false ),
			esc_html__( 'On', 'mg-stealth' )
		);
		if ( '' !== $description ) {
			echo '<p class="description">' . wp_kses( $description, array( 'code' => array(), 'strong' => array(), 'br' => array() ) ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Text row.
	 *
	 * @param string $key         Setting.
	 * @param string $label       Label.
	 * @param string $description Description.
	 * @param string $before      Text shown before the field (e.g. the home address).
	 */
	private function text( string $key, string $label, string $description = '', string $before = '' ): void {
		$id = 'mgs-' . $key;
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td>';
		if ( '' !== $before ) {
			echo '<code class="mgs-before">' . esc_html( $before ) . '</code>';
		}
		printf(
			'<input type="text" class="regular-text code" id="%1$s" name="%2$s[%3$s]" value="%4$s" spellcheck="false" autocomplete="off">',
			esc_attr( $id ),
			esc_attr( Settings::OPTION ),
			esc_attr( $key ),
			esc_attr( (string) Settings::get( $key ) )
		);
		if ( '' !== $description ) {
			echo '<p class="description">' . wp_kses( $description, array( 'code' => array(), 'strong' => array() ) ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Number row.
	 *
	 * @param string $key         Setting.
	 * @param string $label       Label.
	 * @param string $description Description.
	 */
	private function number( string $key, string $label, string $description = '' ): void {
		$id     = 'mgs-' . $key;
		$limits = Settings::NUMBERS[ $key ];
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td>';
		printf(
			'<input type="number" class="small-text" id="%1$s" name="%2$s[%3$s]" value="%4$d" min="%5$d" max="%6$d" step="1">',
			esc_attr( $id ),
			esc_attr( Settings::OPTION ),
			esc_attr( $key ),
			(int) Settings::get( $key ),
			(int) $limits[0],
			(int) $limits[1]
		);
		if ( '' !== $description ) {
			echo ' <span class="description">' . esc_html( $description ) . '</span>';
		}
		echo '</td></tr>';
	}

	/**
	 * Textarea row (one entry per line).
	 *
	 * @param string $key         Setting.
	 * @param string $label       Label.
	 * @param string $description Description (may contain <code>).
	 */
	private function textarea( string $key, string $label, string $description = '' ): void {
		$id = 'mgs-' . $key;
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td>';
		printf( '<textarea id="%1$s" class="large-text code" rows="4" name="%2$s[%3$s]" spellcheck="false">%4$s</textarea>', esc_attr( $id ), esc_attr( Settings::OPTION ), esc_attr( $key ), esc_textarea( (string) Settings::get( $key ) ) );
		if ( '' !== $description ) {
			echo '<p class="description">' . wp_kses( $description, array( 'code' => array(), 'strong' => array() ) ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Tab: page source and headers.
	 */
	private function tab_source(): void {
		echo '<p>' . esc_html__( 'What WordPress and plugins print into every page and header. Visitors get the cleaned version; signed-in users see the normal one.', 'mg-stealth' ) . '</p>';
		$this->form_open( 'source' );
		$this->checkbox( 'clean_head', __( 'Head tags', 'mg-stealth' ), __( 'Removes the generator tags (of WordPress and of plugins), RSD, wlwmanifest, shortlink, pingback and the REST API discovery link.', 'mg-stealth' ) );
		$this->checkbox( 'clean_versions', __( 'Versions in asset addresses', 'mg-stealth' ), __( 'Replaces <code>?ver=7.1.5</code> with a short per-site code (<code>?v=1a2b3c4d</code>): browsers still get fresh files after updates, but the address no longer tells the version.', 'mg-stealth' ) );
		$this->checkbox( 'clean_emoji', __( 'Emoji script', 'mg-stealth' ), __( 'Removes the emoji detection script and styles (browsers show emoji on their own).', 'mg-stealth' ) );
		$this->checkbox( 'clean_oembed', __( 'oEmbed', 'mg-stealth' ), __( 'Removes the oEmbed discovery links and the embed script; /embed/ addresses become the 404 page.', 'mg-stealth' ) );
		$this->checkbox( 'clean_headers', __( 'HTTP headers', 'mg-stealth' ), __( 'Removes <code>X-Powered-By</code>, <code>X-Pingback</code>, <code>X-Redirect-By</code>, the REST API and shortlink <code>Link</code> headers and the well-known 1984 <code>Expires</code> date.', 'mg-stealth' ) );
		$this->checkbox( 'clean_ids', __( 'Asset tag ids', 'mg-stealth' ), __( 'Removes the WordPress-style ids such as <code>id="wp-block-library-css"</code> from link, style and script tags.', 'mg-stealth' ) );
		$this->checkbox( 'clean_body_classes', __( 'Body and comment classes', 'mg-stealth' ), __( 'Removes <code>wp-theme-…</code>, <code>wp-child-theme-…</code>, <code>wp-singular</code> and the author login classes. Block classes (<code>wp-block-…</code>) stay, styles depend on them.', 'mg-stealth' ) );
		$this->checkbox( 'clean_comments', __( 'HTML comments', 'mg-stealth' ), __( 'Removes HTML comments (plugin signatures, cache notes); scripts, styles and conditional comments are left untouched.', 'mg-stealth' ) );
		$this->checkbox( 'clean_speculation', __( 'Speculation rules', 'mg-stealth' ), __( 'Removes WordPress\' prefetch rules, which list its folders.', 'mg-stealth' ) );
		$this->checkbox( 'clean_login_branding', __( 'Login page branding', 'mg-stealth' ), __( 'The site icon and name instead of the WordPress logo, titles without "WordPress".', 'mg-stealth' ) );
		$this->checkbox( 'clean_mail', __( 'E-mails', 'mg-stealth' ), __( 'Messages are sent as the site name instead of "WordPress" and without the <code>X-Mailer: PHPMailer</code> header.', 'mg-stealth' ) );
		$this->form_close();
	}

	/**
	 * Tab: neutral paths.
	 */
	private function tab_paths(): void {
		$home = trailingslashit( home_url() );
		$real = Paths::real_relative_paths();
		echo '<p>' . esc_html__( 'Visitors get neutral addresses instead of /wp-content/, /wp-includes/ and /wp-json/. The files stay where they are: the server maps the new names back (a block in .htaccess, Apache and LiteSpeed). The new addresses are used only after the plugin has checked that they answer.', 'mg-stealth' ) . '</p>';
		if ( ! Server_Rules::supported() ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'This server does not read .htaccess files. Use the wp-config.php snippet below for a real move of the folders instead.', 'mg-stealth' ) . '</p></div>';
		}
		$this->form_open( 'paths' );
		$this->checkbox( 'paths_enabled', __( 'Neutral paths', 'mg-stealth' ), __( 'Switches the aliases below on. The names were drawn for this site, so two sites with this plugin do not look alike; change them freely.', 'mg-stealth' ) );
		$rows = array(
			'path_content'  => array( __( 'Content folder', 'mg-stealth' ), ( $real['content'] ?? '' ) . '/' ),
			'path_themes'   => array( __( 'Themes', 'mg-stealth' ), ( $real['themes'] ?? '' ) . '/' ),
			'path_plugins'  => array( __( 'Plugins', 'mg-stealth' ), ( $real['plugins'] ?? '' ) . '/' ),
			'path_uploads'  => array( __( 'Uploads (media)', 'mg-stealth' ), ( $real['uploads'] ?? '' ) . '/' ),
			'path_includes' => array( __( 'Core scripts and styles', 'mg-stealth' ), ( $real['includes'] ?? '' ) . '/' ),
			'path_ajax'     => array( __( 'AJAX address', 'mg-stealth' ), ( $real['admin'] ?? 'wp-admin' ) . '/admin-ajax.php' ),
			'path_comments' => array( __( 'Comment form address', 'mg-stealth' ), 'wp-comments-post.php' ),
			'rest_prefix'   => array( __( 'REST API prefix', 'mg-stealth' ), 'wp-json/' ),
		);
		foreach ( $rows as $key => $row ) {
			/* translators: %s: original path */
			$this->text( $key, $row[0], sprintf( __( 'Instead of <code>/%s</code>.', 'mg-stealth' ), esc_html( (string) $row[1] ) ), $home );
		}
		$this->checkbox( 'hash_names', __( 'Hide folder names', 'mg-stealth' ), __( 'Plugin and theme folders get short per-site codes (e.g. <code>/assets/ext/9f3a1c2b/</code>), so their names do not give the plugins away.', 'mg-stealth' ) );
		$this->checkbox( 'block_originals', __( 'Hide the original paths', 'mg-stealth' ), __( 'For visitors without a session /wp-includes/, /wp-content/ (with the plugin and theme folders) and the admin AJAX file answer with the normal 404 page. Files in uploads stay reachable under their old address too (links in old content, CSS files of page builders).', 'mg-stealth' ) );
		$this->form_close();

		$this->recheck_button( 'paths' );

		echo '<h2>' . esc_html__( 'Server rules', 'mg-stealth' ) . '</h2>';
		echo '<p>' . esc_html__( 'The plugin keeps this block at the very top of .htaccess (between "# BEGIN MG Stealth" and "# END MG Stealth"). If the file is not writable, paste it there by hand and press "Check again".', 'mg-stealth' ) . '</p>';
		echo '<textarea class="large-text code mgs-code" rows="14" readonly>' . esc_textarea( Server_Rules::desired() ) . '</textarea>';

		echo '<h2>' . esc_html__( 'Real move (optional)', 'mg-stealth' ) . '</h2>';
		echo '<p>' . esc_html__( 'Instead of aliases the folders can be really renamed. This needs access to the files and the database; the plugin does not do it for you. After the move the matching aliases are simply not needed.', 'mg-stealth' ) . '</p>';
		echo '<p><strong>wp-config.php</strong> ' . esc_html__( '(above the line "That\'s all, stop editing!")', 'mg-stealth' ) . '</p>';
		echo '<textarea class="large-text code mgs-code" rows="9" readonly>' . esc_textarea( Paths::wp_config_snippet() ) . '</textarea>';
		echo '<p><strong>' . esc_html__( 'Commands (WP-CLI)', 'mg-stealth' ) . '</strong></p>';
		echo '<textarea class="large-text code mgs-code" rows="7" readonly>' . esc_textarea( Paths::move_commands() ) . '</textarea>';
	}

	/**
	 * "Check again" form.
	 *
	 * @param string $tab Tab to return to.
	 */
	private function recheck_button( string $tab ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="mgs-inline-form">';
		wp_nonce_field( 'mg_stealth_recheck' );
		echo '<input type="hidden" name="action" value="mg_stealth_recheck">';
		echo '<input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">';
		submit_button( __( 'Write and check the server rules again', 'mg-stealth' ), 'secondary', 'submit', false );
		echo '</form>';
	}

	/**
	 * Tab: private login.
	 */
	private function tab_login(): void {
		echo '<p>' . esc_html__( 'The login form moves to a private address. For everyone else wp-login.php, and the admin area for visitors without a session, answer with the normal 404 page. The address is never printed on public pages; login links for visitors point to the home page.', 'mg-stealth' ) . '</p>';
		if ( is_multisite() ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'The private login is not available on multisite networks.', 'mg-stealth' ) . '</p></div>';
		}
		if ( Login::enabled() && Settings::is_guessable_slug( (string) Settings::get( 'login_slug' ) ) ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html( Settings::guessable_warning( (string) Settings::get( 'login_slug' ) ) ) . '</p></div>';
		}
		if ( Login::enabled() ) {
			echo '<div class="notice notice-info inline mgs-login-url"><p>' . esc_html__( 'Your login address (save it in your bookmarks and password manager):', 'mg-stealth' ) . '<br><code>' . esc_html( Login::url() ) . '</code></p></div>';
		}
		$this->form_open( 'login' );
		$this->checkbox( 'login_enabled', __( 'Private login address', 'mg-stealth' ), __( 'After saving, the old login address stops working at once. If you lose the new one: WP-CLI <code>wp mg-stealth login-url</code>, or define <code>MG_STEALTH_DISABLE</code> in wp-config.php (Help tab).', 'mg-stealth' ) );
		$this->text( 'login_slug', __( 'Address', 'mg-stealth' ), __( 'Letters, digits and hyphens, at least 3 characters; not starting with "wp-" and not an address already used on the site. A simple word such as <code>login</code> works too, but bots try it first: a random address hides the login better.', 'mg-stealth' ), trailingslashit( home_url() ) );
		$this->form_close();
	}

	/**
	 * Tab: lockout after failed login attempts.
	 */
	private function tab_lockout(): void {
		echo '<p>' . esc_html__( 'After a few failed sign-ins from one address, further attempts are refused for a while, even with the right password. Covers the login form (also at the private address), XML-RPC and application passwords of the REST API. Every failure shows the same message, so nobody learns whether a user name exists.', 'mg-stealth' ) . '</p>';
		if ( Lockout::llar_active() ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Limit Login Attempts Reloaded is active. Do not run both: import its settings below, switch the lockout on here and then deactivate Limit Login Attempts Reloaded.', 'mg-stealth' ) . '</p></div>';
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Messages after a redirect only.
		if ( isset( $_GET['imported'] ) ) {
			$ok = '1' === sanitize_key( wp_unslash( $_GET['imported'] ) );
			echo '<div class="notice notice-' . ( $ok ? 'success' : 'warning' ) . ' inline"><p>' . esc_html( $ok ? __( 'The settings of Limit Login Attempts Reloaded were imported and the lockout is on.', 'mg-stealth' ) : __( 'No settings of Limit Login Attempts Reloaded were found.', 'mg-stealth' ) ) . '</p></div>';
		}
		if ( isset( $_GET['unlocked'] ) ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Done.', 'mg-stealth' ) . '</p></div>';
		}
		// phpcs:enable
		$this->form_open( 'lockout' );
		$this->checkbox( 'lockout_enabled', __( 'Lockout after failed logins', 'mg-stealth' ), __( 'Replaces plugins such as Limit Login Attempts Reloaded. Addresses on the allow-list below are never locked out; add your own fixed address there.', 'mg-stealth' ) );
		$this->number( 'lockout_retries', __( 'Allowed attempts', 'mg-stealth' ), __( 'failed attempts, then a lockout', 'mg-stealth' ) );
		$this->number( 'lockout_minutes', __( 'Lockout', 'mg-stealth' ), __( 'minutes', 'mg-stealth' ) );
		$this->number( 'lockout_escalate', __( 'Longer lockout after', 'mg-stealth' ), __( 'lockouts in a row', 'mg-stealth' ) );
		$this->number( 'lockout_long_hours', __( 'Longer lockout', 'mg-stealth' ), __( 'hours', 'mg-stealth' ) );
		$this->number( 'lockout_reset_hours', __( 'Counters reset after', 'mg-stealth' ), __( 'hours without a failed attempt', 'mg-stealth' ) );
		$this->checkbox( 'lockout_by_username', __( 'Also per user name', 'mg-stealth' ), __( 'Counts failures for each user name too, so an attack from many addresses on one account is stopped. A stranger can then lock that account out for a while; allow-listed addresses still sign in.', 'mg-stealth' ) );
		$this->checkbox( 'lockout_cloudflare', __( 'Site behind Cloudflare', 'mg-stealth' ), __( 'Takes the visitor\'s address from <code>CF-Connecting-IP</code>, only for requests that really come from Cloudflare\'s addresses.', 'mg-stealth' ) );
		/* translators: %s: REMOTE_ADDR of the current request */
		$this->textarea( 'lockout_trusted_proxies', __( 'Trusted proxies', 'mg-stealth' ), sprintf( __( 'Addresses or ranges (CIDR) of your own reverse proxy or load balancer: only requests from them may pass the visitor\'s address in <code>X-Forwarded-For</code>. Leave empty when the site is reached directly. This request comes from <code>%s</code>.', 'mg-stealth' ), esc_html( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' ) ) );
		$this->textarea( 'lockout_allow', __( 'Allow-list', 'mg-stealth' ), __( 'Addresses or ranges that are never counted or locked out, one per line, e.g. <code>203.0.113.7</code> or <code>198.51.100.0/24</code>.', 'mg-stealth' ) );
		$this->textarea( 'lockout_deny', __( 'Deny-list', 'mg-stealth' ), __( 'Addresses or ranges that never sign in: the login page is the normal 404 page for them.', 'mg-stealth' ) );
		$this->checkbox( 'lockout_notify', __( 'E-mail on lockout', 'mg-stealth' ), __( 'Sends a short message to the site\'s administrator address for every lockout.', 'mg-stealth' ) );
		$this->checkbox( 'lockout_anonymize', __( 'Shorten addresses in the log', 'mg-stealth' ), __( 'The log keeps only the start of an address (e.g. <code>203.0.113.0</code>). Lockouts keep working: the plugin stores addresses only as keyed hashes. Passwords are never stored.', 'mg-stealth' ) );
		$this->form_close();

		$llar = Lockout::llar_settings();
		if ( ! empty( $llar ) ) {
			echo '<h2>' . esc_html__( 'Limit Login Attempts Reloaded', 'mg-stealth' ) . '</h2>';
			echo '<p>' . esc_html__( 'Settings of Limit Login Attempts Reloaded were found on this site (limits, allow- and deny-list). Importing them overwrites the values above and switches the lockout on; Limit Login Attempts Reloaded itself is not changed or deactivated.', 'mg-stealth' ) . '</p>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="mgs-inline-form">';
			wp_nonce_field( 'mg_stealth_llar_import' );
			echo '<input type="hidden" name="action" value="mg_stealth_llar_import">';
			submit_button( __( 'Import the settings', 'mg-stealth' ), 'secondary', 'submit', false );
			echo '</form>';
		}

		$active = Lockout::active();
		echo '<h2>' . esc_html__( 'Active lockouts', 'mg-stealth' ) . '</h2>';
		if ( empty( $active ) ) {
			echo '<p>' . esc_html__( 'None.', 'mg-stealth' ) . '</p>';
		} else {
			echo '<table class="widefat striped mgs-results"><thead><tr><th>' . esc_html__( 'Address or user name', 'mg-stealth' ) . '</th><th>' . esc_html__( 'Until', 'mg-stealth' ) . '</th><th></th></tr></thead><tbody>';
			foreach ( $active as $key => $lock ) {
				$who = 0 === strpos( $key, 'user:' ) ? __( 'user', 'mg-stealth' ) . ' ' . $lock['label'] : $lock['label'];
				echo '<tr><td><code>' . esc_html( $who ) . '</code></td><td>' . esc_html( wp_date( 'Y-m-d H:i', $lock['until'] ) ) . '</td><td>';
				$this->unlock_button( $key, __( 'Unlock', 'mg-stealth' ) );
				echo '</td></tr>';
			}
			echo '</tbody></table><p>';
			$this->unlock_button( 'all', __( 'Unlock all', 'mg-stealth' ) );
			echo '</p>';
		}

		$log = Lockout::data()['log'];
		echo '<h2>' . esc_html__( 'Log of lockouts', 'mg-stealth' ) . '</h2>';
		if ( empty( $log ) ) {
			echo '<p>' . esc_html__( 'No lockouts yet.', 'mg-stealth' ) . '</p>';
			return;
		}
		/* translators: %d: number of entries */
		echo '<p class="description">' . esc_html( sprintf( __( 'The last %d lockouts.', 'mg-stealth' ), Lockout::LOG_SIZE ) ) . '</p>';
		echo '<table class="widefat striped mgs-results"><thead><tr><th>' . esc_html__( 'Time', 'mg-stealth' ) . '</th><th>' . esc_html__( 'Address', 'mg-stealth' ) . '</th><th>' . esc_html__( 'User name tried', 'mg-stealth' ) . '</th><th>' . esc_html__( 'Where', 'mg-stealth' ) . '</th><th>' . esc_html__( 'Until', 'mg-stealth' ) . '</th></tr></thead><tbody>';
		foreach ( (array) $log as $row ) {
			printf(
				'<tr><td>%s</td><td><code>%s</code></td><td><code>%s</code></td><td>%s</td><td>%s%s</td></tr>',
				esc_html( wp_date( 'Y-m-d H:i', (int) $row['time'] ) ),
				esc_html( (string) $row['ip'] ),
				esc_html( (string) $row['user'] ),
				esc_html( (string) $row['via'] ),
				esc_html( wp_date( 'Y-m-d H:i', (int) $row['until'] ) ),
				! empty( $row['long'] ) ? ' <strong>(' . esc_html__( 'longer lockout', 'mg-stealth' ) . ')</strong>' : ''
			);
		}
		echo '</tbody></table><p>';
		$this->unlock_button( 'log', __( 'Empty the log', 'mg-stealth' ) );
		echo '</p>';
	}

	/**
	 * Small form that lifts a lockout (or empties the log).
	 *
	 * @param string $what  Counter key, "all" or "log".
	 * @param string $label Button text.
	 */
	private function unlock_button( string $what, string $label ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="mgs-inline-form">';
		wp_nonce_field( 'mg_stealth_unlock' );
		echo '<input type="hidden" name="action" value="mg_stealth_unlock">';
		echo '<input type="hidden" name="what" value="' . esc_attr( $what ) . '">';
		submit_button( $label, 'secondary small', 'submit', false );
		echo '</form>';
	}

	/**
	 * Tab: access rules.
	 */
	private function tab_access(): void {
		echo '<p>' . esc_html__( 'Typical WordPress addresses answer like any address that does not exist: the same 404 page, so a probe cannot tell "blocked" from "missing".', 'mg-stealth' ) . '</p>';
		$this->form_open( 'access' );
		$this->checkbox( 'rest_guests_closed', __( 'REST API only for signed-in users', 'mg-stealth' ), __( 'Visitors get the 404 page for the REST API, except for the routes allowed below. The editor, the admin area and application passwords keep working.', 'mg-stealth' ) );

		$namespaces = function_exists( 'rest_get_server' ) ? rest_get_server()->get_namespaces() : array();
		echo '<tr><th scope="row"><label for="mgs-rest_allow">' . esc_html__( 'Allowed for visitors', 'mg-stealth' ) . '</label></th><td>';
		printf( '<textarea id="mgs-rest_allow" class="large-text code" rows="6" name="%s[rest_allow]">%s</textarea>', esc_attr( Settings::OPTION ), esc_textarea( (string) Settings::get( 'rest_allow' ) ) );
		echo '<p class="description">' . esc_html__( 'One route prefix per line, e.g. contact-form-7 or wc/store. Contact forms and shops that send forms through the REST API need theirs here.', 'mg-stealth' ) . '</p>';
		if ( ! empty( $namespaces ) ) {
			echo '<p class="description">' . esc_html__( 'Namespaces on this site:', 'mg-stealth' ) . ' <code>' . esc_html( implode( '</code> <code>', $namespaces ) ) . '</code></p>';
		}
		echo '</td></tr>';

		$this->checkbox( 'block_xmlrpc', 'XML-RPC', __( '<code>xmlrpc.php</code> (GET and POST) is the 404 page; pingbacks are off.', 'mg-stealth' ) );
		$this->checkbox( 'block_core_files', __( 'Core files', 'mg-stealth' ), __( '<code>readme.html</code>, <code>license.txt</code>, <code>wp-config-sample.php</code>, <code>wp-load.php</code>, <code>wp-signup.php</code>, <code>wp-activate.php</code>, <code>wp-trackback.php</code>, <code>wp-links-opml.php</code>, <code>wp-mail.php</code> and trackback addresses.', 'mg-stealth' ) );
		$this->checkbox( 'block_authors', __( 'Authors', 'mg-stealth' ), __( '<code>?author=1</code>, author archives, the users sitemap and REST endpoint (they reveal login names).', 'mg-stealth' ) );
		$this->checkbox( 'block_feeds', __( 'RSS feeds', 'mg-stealth' ), __( 'All feeds become the 404 page and their links disappear. Leave off if people or services read the feed.', 'mg-stealth' ) );
		$this->checkbox( 'block_cron', __( 'wp-cron.php', 'mg-stealth' ), __( 'Called directly it is the 404 page; WordPress\' own calls carry a secret key and work.', 'mg-stealth' ) );
		echo '<tr><th scope="row"></th><td><p class="description">' . esc_html__( 'For a server cron job use this address:', 'mg-stealth' ) . ' <code>' . esc_html( Access::cron_url() ) . '</code></p></td></tr>';
		$this->checkbox( 'block_shortcuts', __( '/login, /admin, /dashboard', 'mg-stealth' ), __( 'No redirect to the login form from these short addresses (the one chosen as the private login address shows the form).', 'mg-stealth' ) );
		$this->checkbox( 'neutral_sitemap', __( 'Sitemap without "wp-"', 'mg-stealth' ), __( 'WordPress\' own sitemap at <code>/sitemap.xml</code> instead of <code>/wp-sitemap.xml</code>, without its stylesheet. Has no effect when an SEO plugin provides the sitemap. Submit the new address in Search Console.', 'mg-stealth' ) );
		$this->form_close();
	}

	/**
	 * Tab: redirects.
	 */
	private function tab_redirects(): void {
		$cfg   = Server_Rules::redirect_config();
		$https = 'https' === $cfg['scheme'];
		$host  = $cfg['host'];
		$bare  = preg_replace( '~^www\.~', '', $host );
		$other = ( 0 === strpos( $host, 'www.' ) ) ? $bare : 'www.' . $host;

		/* translators: %s: main address */
		echo '<p>' . wp_kses( sprintf( __( 'All addresses of the site in one 301 step to the main address <code>%s</code>: path and query string kept, /.well-known/ left alone (certificates), https behind a proxy or Cloudflare recognised. Only this host name and its www variant are affected, so a local copy keeps working.', 'mg-stealth' ), esc_html( untrailingslashit( home_url() ) ) ), array( 'code' => array() ) ) . '</p>';
		if ( ! $https ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'The site address (Settings → General) starts with http://. Change it to https:// first (with a working certificate); until then the https redirect stays off.', 'mg-stealth' ) . '</p></div>';
		}
		$this->form_open( 'redirects' );
		$this->checkbox( 'redirect_https', __( 'http → https', 'mg-stealth' ), __( 'Every http:// address goes to https://.', 'mg-stealth' ) );
		/* translators: 1: other host variant, 2: main host */
		$this->checkbox( 'redirect_www', __( 'One host name', 'mg-stealth' ), sprintf( __( '<code>%1$s</code> goes to <code>%2$s</code> (the host of the site address decides whether it is with or without www).', 'mg-stealth' ), esc_html( (string) $other ), esc_html( (string) $host ) ) );
		$this->form_close();

		echo '<h2>' . esc_html__( 'Test from a terminal', 'mg-stealth' ) . '</h2>';
		$lines = array(
			'curl -sI http://' . $other . '/?a=1 | grep -iE "^(HTTP|location)"',
			'curl -sI http://' . $host . '/ | grep -iE "^(HTTP|location)"',
			'curl -sI https://' . $host . '/ | grep -iE "^HTTP"',
		);
		echo '<textarea class="large-text code mgs-code" rows="4" readonly>' . esc_textarea( implode( "\n", $lines ) ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Expected: 301 with location https://… for the first two (in one step), 200 for the last.', 'mg-stealth' ) . '</p>';
	}

	/**
	 * Tab: self-check.
	 */
	private function tab_check(): void {
		echo '<p>' . esc_html__( 'The plugin asks the site for its pages and the typical WordPress addresses the way a stranger would: without cookies, from the server itself. It then lists what still gives WordPress away.', 'mg-stealth' ) . '</p>';
		$run = isset( $_GET['run'], $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'mg_stealth_check' );
		echo '<p><a class="button button-primary" href="' . esc_url( wp_nonce_url( self::url( 'check', array( 'run' => 1 ) ), 'mg_stealth_check' ) ) . '">' . esc_html__( 'Run the check', 'mg-stealth' ) . '</a></p>';
		if ( ! $run ) {
			return;
		}
		$result = ( new Self_Check() )->run();

		$verdicts = array(
			'hidden'    => array( __( 'hidden (normal 404 page)', 'mg-stealth' ), 'ok' ),
			'ok'        => array( __( 'works', 'mg-stealth' ), 'ok' ),
			'allowed'   => array( __( 'allowed by the settings', 'mg-stealth' ), 'off' ),
			'different' => array( __( 'blocked, but not like the 404 page', 'mg-stealth' ), 'warn' ),
			'visible'   => array( __( 'answers like WordPress', 'mg-stealth' ), 'bad' ),
			'error'     => array( __( 'does not work', 'mg-stealth' ), 'bad' ),
		);

		/* translators: 1: HTTP status, 2: size in bytes */
		echo '<p>' . esc_html( sprintf( __( 'Reference: the 404 page of a random address answers %1$d with %2$d bytes.', 'mg-stealth' ), $result['reference']['code'], $result['reference']['length'] ) ) . '</p>';
		echo '<h2>' . esc_html__( 'Typical addresses', 'mg-stealth' ) . '</h2>';
		echo '<table class="widefat striped mgs-results"><thead><tr><th>' . esc_html__( 'Address', 'mg-stealth' ) . '</th><th>' . esc_html__( 'Status', 'mg-stealth' ) . '</th><th>' . esc_html__( 'Redirect to', 'mg-stealth' ) . '</th><th>' . esc_html__( 'Result', 'mg-stealth' ) . '</th></tr></thead><tbody>';
		foreach ( $result['urls'] as $row ) {
			$v = $verdicts[ $row['verdict'] ] ?? array( $row['verdict'], 'off' );
			printf(
				'<tr><td><code>%s %s</code></td><td>%d</td><td>%s</td><td class="mgs-%s">%s</td></tr>',
				esc_html( $row['method'] ),
				esc_html( $row['path'] ),
				(int) $row['code'],
				esc_html( $row['location'] ),
				esc_attr( $v[1] ),
				esc_html( $v[0] )
			);
		}
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Traces in pages and headers', 'mg-stealth' ) . '</h2>';
		if ( ! empty( $result['headers'] ) ) {
			echo '<p class="mgs-bad">' . esc_html__( 'Headers of the home page:', 'mg-stealth' ) . ' <code>' . esc_html( implode( '</code> <code>', $result['headers'] ) ) . '</code></p>';
		}
		echo '<table class="widefat striped mgs-results"><thead><tr><th>' . esc_html__( 'Page', 'mg-stealth' ) . '</th><th>' . esc_html__( 'Found', 'mg-stealth' ) . '</th></tr></thead><tbody>';
		foreach ( $result['traces'] as $page ) {
			echo '<tr><td><strong>' . esc_html( $page['label'] ) . '</strong><br><code>' . esc_html( $page['path'] ) . '</code> (' . (int) $page['code'] . ')</td><td>';
			if ( empty( $page['found'] ) ) {
				echo '<span class="mgs-ok">' . esc_html__( 'nothing found', 'mg-stealth' ) . '</span>';
			} else {
				foreach ( $page['found'] as $needle => $info ) {
					echo '<details><summary><code>' . esc_html( $needle ) . '</code> × ' . (int) $info['count'] . '</summary><ul>';
					foreach ( $info['excerpts'] as $excerpt ) {
						echo '<li><code>' . esc_html( $excerpt ) . '</code></li>';
					}
					echo '</ul></details>';
				}
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p class="description">' . esc_html__( 'Words in your own content (a post about WordPress) and theme credits are found too; the theme\'s footer text is edited in the theme or the Site Editor. Block class names (wp-block-…) and the theme stylesheet header stay, as styles depend on them.', 'mg-stealth' ) . '</p>';
	}

	/**
	 * Tab: help and emergency recovery.
	 */
	private function tab_help(): void {
		$file = Server_Rules::file();
		?>
		<h2><?php esc_html_e( 'Emergency: switch everything off', 'mg-stealth' ); ?></h2>
		<p><?php esc_html_e( 'If the site or the admin area stops working, use any one of these:', 'mg-stealth' ); ?></p>
		<ol>
			<li><?php echo wp_kses( __( 'Add <code>define( \'MG_STEALTH_DISABLE\', true );</code> to wp-config.php and open any page of the site: every rule stops, the .htaccess block is removed, wp-login.php works again. Delete the line to switch the plugin on again.', 'mg-stealth' ), array( 'code' => array() ) ); ?></li>
			<li><?php echo wp_kses( __( 'Rename the folder <code>wp-content/plugins/mg-stealth</code> (FTP, file manager). The .htaccess rules check that the plugin file exists, so they stop at once.', 'mg-stealth' ), array( 'code' => array() ) ); ?></li>
			<li><?php echo wp_kses( __( 'WP-CLI: <code>wp mg-stealth remove-rules</code>, <code>wp mg-stealth login-url</code>, <code>wp plugin deactivate mg-stealth</code>.', 'mg-stealth' ), array( 'code' => array() ) ); ?></li>
			<?php /* translators: %s: path of the .htaccess file */ ?>
			<li><?php echo wp_kses( sprintf( __( 'Delete the lines from <code># BEGIN MG Stealth</code> to <code># END MG Stealth</code> in <code>%s</code>.', 'mg-stealth' ), esc_html( $file ) ), array( 'code' => array() ) ); ?></li>
		</ol>
		<h2><?php esc_html_e( 'Deactivation and removal', 'mg-stealth' ); ?></h2>
		<p><?php esc_html_e( 'Deactivating removes the .htaccess block and WordPress\' rewrite rules are rebuilt: the site is back to its defaults (wp-login.php, /wp-json/, /wp-content/ …). The settings stay for a later activation; deleting the plugin removes them too.', 'mg-stealth' ); ?></p>
		<h2><?php esc_html_e( 'What cannot be hidden', 'mg-stealth' ); ?></h2>
		<ul class="ul-disc">
			<li><?php esc_html_e( 'Block class names (wp-block-…, wp-container-…) and the CSS custom properties of block themes (--wp--preset--…): styles depend on them.', 'mg-stealth' ); ?></li>
			<li><?php esc_html_e( 'The header comment of the theme\'s style.css when the theme loads that file, and @wordpress/… names in the import map of blocks that use the Interactivity API.', 'mg-stealth' ); ?></li>
			<li><?php esc_html_e( 'Cookies after signing in (wordpress_logged_in_…), and the login page itself at its private address.', 'mg-stealth' ); ?></li>
			<li><?php esc_html_e( 'Behaviour that a careful tool can still recognise (for example /page/2/ archives). The plugin makes the usual checks find nothing; it cannot promise that no tool ever can.', 'mg-stealth' ); ?></li>
		</ul>
		<h2><?php esc_html_e( 'Servers', 'mg-stealth' ); ?></h2>
		<p><?php esc_html_e( 'Apache and LiteSpeed read the .htaccess block. On nginx the PHP part still works (head and headers, REST API, login, authors, XML-RPC, cron, redirects); the neutral paths and the blocking of static files such as readme.html need server rules or the real move from the Paths tab.', 'mg-stealth' ); ?></p>
		<h2><?php esc_html_e( 'Privacy', 'mg-stealth' ); ?></h2>
		<p><?php esc_html_e( 'The plugin sends no data anywhere and prints no credit link. The self-check only asks this site itself. The GitHub build looks for new versions in the GitHub repository of the plugin; the WordPress.org build does not.', 'mg-stealth' ); ?></p>
		<?php
	}
}
