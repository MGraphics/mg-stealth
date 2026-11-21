<?php
/**
 * Updates from the Releases of the public GitHub repository (plugin-update-checker, MIT).
 * The build for WordPress.org leaves the library out, and then nothing here runs.
 *
 * @package MG_Stealth
 */

namespace MG_Stealth;

defined( 'ABSPATH' ) || exit;

/**
 * Update checker.
 */
final class Updater {

	/**
	 * Starts the update checker when the library is bundled.
	 */
	public function register(): void {
		$library = MG_STEALTH_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';
		if ( ! is_readable( $library ) || ( defined( 'MG_STEALTH_DISABLE_UPDATES' ) && MG_STEALTH_DISABLE_UPDATES ) ) {
			return;
		}
		require_once $library;
		if ( ! class_exists( '\YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
			return;
		}
		$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			MG_STEALTH_UPDATE_REPOSITORY,
			MG_STEALTH_FILE,
			'mg-stealth'
		);
		$api     = $checker->getVcsApi();
		if ( $api && method_exists( $api, 'enableReleaseAssets' ) ) {
			// Only the release asset mg-stealth-1.2.3.zip (not -wporg.zip, never GitHub's source
			// archive): 2 = Api::REQUIRE_RELEASE_ASSETS, a release without that asset is skipped.
			$api->enableReleaseAssets( '/^mg-stealth-[0-9][0-9.]*\.zip$/i', 2 );
		}
	}
}
