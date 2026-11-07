<?php
/**
 * Assets.
 *
 * @package Parsian_Music
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cache-busting version for a theme asset based on its modification time.
 *
 * @param string $relative Relative path inside the theme.
 * @return string
 */
function pm_asset_version( $relative ) {
	$file = PM_DIR . '/' . ltrim( $relative, '/' );

	return file_exists( $file ) ? (string) filemtime( $file ) : PM_VERSION;
}

/**
 * Front-end styles and scripts.
 */
function pm_assets() {
	wp_enqueue_style(
		'pm-font',
		'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800;900&display=swap',
		array(),
		null
	);

	wp_enqueue_style( 'pm-main', PM_URI . '/assets/css/main.css', array( 'pm-font' ), pm_asset_version( 'assets/css/main.css' ) );
	wp_enqueue_style( 'pm-refine', PM_URI . '/assets/css/refine.css', array( 'pm-main' ), pm_asset_version( 'assets/css/refine.css' ) );

	wp_enqueue_script( 'pm-main', PM_URI . '/assets/js/main.js', array(), pm_asset_version( 'assets/js/main.js' ), true );
	wp_localize_script(
		'pm-main',
		'pmSite',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'homeUrl' => home_url( '/' ),
			'isFront' => is_front_page() ? '1' : '0',
		)
	);

	if ( is_singular( 'pm_teacher' ) ) {
		wp_enqueue_style( 'pm-teacher', PM_URI . '/assets/css/teacher.css', array( 'pm-main', 'pm-refine' ), pm_asset_version( 'assets/css/teacher.css' ) );
		wp_enqueue_script( 'pm-teacher', PM_URI . '/assets/js/teacher.js', array( 'pm-main' ), pm_asset_version( 'assets/js/teacher.js' ), true );
	}

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'pm_assets' );

/**
 * Admin assets for the teacher meta box.
 *
 * @param string $hook Current admin page.
 */
function pm_admin_assets( $hook ) {
	global $post_type;

	if ( ( 'post.php' === $hook || 'post-new.php' === $hook ) && 'pm_teacher' === $post_type ) {
		wp_enqueue_media();
		wp_enqueue_style( 'pm-admin', PM_URI . '/assets/css/admin.css', array(), pm_asset_version( 'assets/css/admin.css' ) );
		wp_enqueue_script( 'pm-admin', PM_URI . '/assets/js/admin.js', array( 'jquery' ), pm_asset_version( 'assets/js/admin.js' ), true );
	}
}
add_action( 'admin_enqueue_scripts', 'pm_admin_assets' );
