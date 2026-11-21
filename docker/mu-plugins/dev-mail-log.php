<?php
/**
 * Development only: e-mails are written to wp-content/dev-mail.log instead of being sent.
 *
 * @package MG_Stealth
 */

add_filter(
	'pre_wp_mail',
	static function ( $result, $atts ) {
		$line = gmdate( 'c' ) . ' to=' . wp_json_encode( $atts['to'] ) . ' subject=' . wp_json_encode( $atts['subject'] ) . "\n";
		file_put_contents( WP_CONTENT_DIR . '/dev-mail.log', $line, FILE_APPEND ); // phpcs:ignore
		return true;
	},
	10,
	2
);
