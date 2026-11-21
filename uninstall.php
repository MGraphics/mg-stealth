<?php
/**
 * Deleting the plugin: removes its .htaccess block, settings and stored data.
 *
 * @package MG_Stealth
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! defined( 'MG_STEALTH_FILE' ) ) {
	define( 'MG_STEALTH_FILE', __DIR__ . '/mg-stealth.php' );
}
require_once __DIR__ . '/includes/class-htaccess.php';

require_once ABSPATH . 'wp-admin/includes/file.php';
$mg_stealth_file = get_home_path() . '.htaccess';
if ( is_readable( $mg_stealth_file ) && 'direct' === get_filesystem_method( array(), dirname( $mg_stealth_file ) ) && WP_Filesystem() ) {
	global $wp_filesystem;
	$mg_stealth_contents = (string) $wp_filesystem->get_contents( $mg_stealth_file );
	if ( null !== MG_Stealth\Htaccess::extract_block( $mg_stealth_contents ) && $wp_filesystem->is_writable( $mg_stealth_file ) ) {
		$wp_filesystem->put_contents( $mg_stealth_file, MG_Stealth\Htaccess::remove_block( $mg_stealth_contents ), FS_CHMOD_FILE );
	}
}

delete_option( 'mg_stealth_settings' );
delete_option( 'mg_stealth_state' );
delete_transient( 'mg_stealth_404_page' );
delete_option( 'rewrite_rules' );
