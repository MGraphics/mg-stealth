<?php
/**
 * Plugin Name:       MG Stealth
 * Plugin URI:        https://mgraphics.com.pl/mg-stealth/
 * Description:       Makes it hard to tell that a site runs WordPress: clean page source and headers, neutral public paths, a private login address, closed REST API for guests, the normal 404 page for typical WordPress addresses and one-hop 301 redirects to https and the main host name.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            MGraphics
 * Author URI:        https://mgraphics.com.pl
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mg-stealth
 * Domain Path:       /languages
 * Update URI:        https://gitlab.com/mgraphics/mg-stealth
 *
 * @package MG_Stealth
 */

defined( 'ABSPATH' ) || exit;

define( 'MG_STEALTH_VERSION', '1.0.0' );
define( 'MG_STEALTH_FILE', __FILE__ );
define( 'MG_STEALTH_DIR', plugin_dir_path( __FILE__ ) );
define( 'MG_STEALTH_BASENAME', plugin_basename( __FILE__ ) );

// gitlab-build:start (removed from the WordPress.org build by bin/build.sh)
/*
 * Public GitLab project whose Releases deliver updates of this plugin. Can be overridden in
 * wp-config.php; define MG_STEALTH_DISABLE_UPDATES to switch update checks off.
 */
if ( ! defined( 'MG_STEALTH_UPDATE_REPOSITORY' ) ) {
	define( 'MG_STEALTH_UPDATE_REPOSITORY', 'https://gitlab.com/mgraphics/mg-stealth/' );
}
require_once MG_STEALTH_DIR . 'includes/class-updater.php';
// gitlab-build:end

require_once MG_STEALTH_DIR . 'includes/class-output-filter.php';
require_once MG_STEALTH_DIR . 'includes/class-htaccess.php';
require_once MG_STEALTH_DIR . 'includes/class-redirect-rules.php';
require_once MG_STEALTH_DIR . 'includes/class-settings.php';
require_once MG_STEALTH_DIR . 'includes/class-state.php';
require_once MG_STEALTH_DIR . 'includes/class-request.php';
require_once MG_STEALTH_DIR . 'includes/class-paths.php';
require_once MG_STEALTH_DIR . 'includes/class-server-rules.php';
require_once MG_STEALTH_DIR . 'includes/class-cleaner.php';
require_once MG_STEALTH_DIR . 'includes/class-access.php';
require_once MG_STEALTH_DIR . 'includes/class-login.php';
require_once MG_STEALTH_DIR . 'includes/class-redirects.php';
require_once MG_STEALTH_DIR . 'includes/class-self-check.php';
require_once MG_STEALTH_DIR . 'includes/class-admin.php';
require_once MG_STEALTH_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'MG_Stealth\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MG_Stealth\\Plugin', 'deactivate' ) );

MG_Stealth\Plugin::instance()->boot();
