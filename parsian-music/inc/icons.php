<?php
/**
 * Inline SVG sprite.
 *
 * @package Parsian_Music
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Print the icon sprite once in the document.
 */
function pm_sprite() {
	static $printed = false;

	if ( $printed ) {
		return;
	}
	$printed = true;

	$file = PM_DIR . '/assets/icons.svg';
	if ( is_readable( $file ) ) {
		readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
	}
}
