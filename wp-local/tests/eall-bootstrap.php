<?php
/**
 * Direct WordPress bootstrap for E_ALL test runs.
 *
 * Usage:
 *   WP_LOAD=/path/to/wp-load.php PA_TEST_FILE=/path/to/test.php \
 *     php -d error_reporting=E_ALL -d display_errors=1 eall-bootstrap.php
 */
if ( ! defined( 'WP_CLI' ) ) {
	define( 'WP_CLI', true );
}
if ( ! class_exists( 'WP_CLI', false ) ) {
	class WP_CLI {
		public static function log( $message = '' ) {
			echo $message, "\n";
		}
		public static function warning( $message ) {
			fwrite( STDERR, 'WARNING: ' . $message . "\n" );
		}
	}
}

error_reporting( E_ALL );
ini_set( 'display_errors', '1' );

$wp_load = getenv( 'WP_LOAD' );
$test_file = getenv( 'PA_TEST_FILE' );
if ( ! $wp_load || ! is_file( $wp_load ) ) {
	fwrite( STDERR, "WP_LOAD must point to wp-load.php\n" );
	exit( 2 );
}
if ( ! $test_file || ! is_file( $test_file ) ) {
	fwrite( STDERR, "PA_TEST_FILE must point to a test file\n" );
	exit( 2 );
}

require $wp_load;
require_once ABSPATH . 'wp-admin/includes/user.php';
include $test_file;
