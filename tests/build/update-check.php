<?php
/**
 * Runs inside WordPress (wp eval-file) with the BUILT plugin active: asks the bundled update
 * checker for the latest GitHub release, answered from a fixture with a Markdown body (no network).
 * Prints "OK <version> <package>" or "FAIL <reason>"; exits 1 on failure.
 *
 * @package MG_Stealth
 */

// phpcs:ignoreFile -- Test script.

$fixture = (string) file_get_contents( getenv( 'MGS_RELEASE_FIXTURE' ) );
add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) use ( $fixture ) {
		$json = static function ( $body ) {
			return array( 'headers' => array(), 'body' => $body, 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
		};
		if ( false !== strpos( $url, 'api.github.com/repos/MGraphics/mg-stealth/releases/latest' ) ) {
			return $json( $fixture );
		}
		if ( preg_match( '~api\.github\.com/repos/MGraphics/mg-stealth/contents/([^?]+)~', $url, $m ) ) {
			$file = WP_PLUGIN_DIR . '/mg-stealth/' . $m[1];
			if ( is_readable( $file ) ) {
				$text = str_replace( array( 'Version:           ' . MG_STEALTH_VERSION, 'Stable tag: ' . MG_STEALTH_VERSION ), array( 'Version:           9.9.9', 'Stable tag: 9.9.9' ), (string) file_get_contents( $file ) );
				return $json( wp_json_encode( array( 'content' => base64_encode( $text ), 'encoding' => 'base64' ) ) );
			}
		}
		return array( 'headers' => array(), 'body' => '{"message":"Not Found"}', 'response' => array( 'code' => 404, 'message' => 'Not Found' ), 'cookies' => array(), 'filename' => null );
	},
	10,
	3
);

$library = WP_PLUGIN_DIR . '/mg-stealth/lib/plugin-update-checker/plugin-update-checker.php';
if ( ! is_readable( $library ) ) {
	echo "FAIL the update checker is not bundled\n";
	exit( 1 );
}
require_once $library;
$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker( MG_STEALTH_UPDATE_REPOSITORY, WP_PLUGIN_DIR . '/mg-stealth/mg-stealth.php', 'mg-stealth-test' );
$api = $checker->getVcsApi();
$api->enableReleaseAssets( '/^mg-stealth-[0-9][0-9.]*\.zip$/i', 2 );

// The release notes (Markdown) are rendered by the bundled Parsedown.
$release = $api->chooseReference( 'master' );
if ( ! $release || false === strpos( (string) $release->changelog, '<strong>Private login</strong>' ) ) {
	echo "FAIL the Markdown release notes were not rendered\n";
	exit( 1 );
}

// The whole update check, including readme.txt (parsed by the bundled readme parser).
$info = $checker->requestInfo();
if ( ! $info ) {
	echo "FAIL no update information\n";
	exit( 1 );
}
if ( '9.9.9' !== $info->version || false === strpos( (string) $info->download_url, '/mg-stealth-9.9.9.zip' ) ) {
	echo 'FAIL unexpected answer: ' . $info->version . ' ' . $info->download_url . "\n";
	exit( 1 );
}
if ( empty( $info->sections['changelog'] ) || empty( $info->requires_php ) ) {
	echo "FAIL readme.txt was not read\n";
	exit( 1 );
}
echo 'OK ' . $info->version . ' ' . $info->download_url . "\n";
