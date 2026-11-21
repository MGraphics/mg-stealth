<?php
/**
 * Unit tests: the pure classes, with a few WordPress functions stubbed.
 *
 * @package MG_Stealth
 */

define( 'ABSPATH', __DIR__ . '/' );
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

$GLOBALS['mgs_options']  = array();
$GLOBALS['mgs_errors']   = array();
$GLOBALS['mgs_warnings'] = array();

function __( $text ) {
	return $text;
}
function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}
function sanitize_text_field( $text ) {
	return trim( strip_tags( (string) $text ) );
}
function sanitize_title( $title ) {
	$title = strtolower( trim( (string) $title ) );
	$title = preg_replace( '/[^a-z0-9\-]+/', '-', $title );
	return trim( (string) $title, '-' );
}
function wp_rand( $min = 0, $max = 0 ) {
	return random_int( (int) $min, (int) $max );
}
function wp_generate_password( $length = 12, $special = true, $extra = false ) {
	$chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
	$out   = '';
	for ( $i = 0; $i < $length; $i++ ) {
		$out .= $chars[ random_int( 0, strlen( $chars ) - 1 ) ];
	}
	return $out;
}
function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['mgs_options'] ) ? $GLOBALS['mgs_options'][ $name ] : $default;
}
function update_option( $name, $value, $autoload = null ) {
	$GLOBALS['mgs_options'][ $name ] = $value;
	return true;
}
function add_settings_error( $setting, $code, $message, $type = 'error' ) {
	if ( 'error' === $type ) {
		$GLOBALS['mgs_errors'][] = $message;
	} else {
		$GLOBALS['mgs_warnings'][] = $message;
	}
}
function _n( $single, $plural, $number ) {
	return 1 === (int) $number ? $single : $plural;
}

require dirname( __DIR__, 2 ) . '/includes/class-output-filter.php';
require dirname( __DIR__, 2 ) . '/includes/class-htaccess.php';
require dirname( __DIR__, 2 ) . '/includes/class-redirect-rules.php';
require dirname( __DIR__, 2 ) . '/includes/class-settings.php';
require dirname( __DIR__, 2 ) . '/includes/class-lockout.php';
