<?php
/**
 * Theme setup.
 *
 * @package Parsian_Music
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register theme supports, menus and image sizes.
 */
function pm_setup() {
	load_theme_textdomain( 'parsian-music', PM_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'customize-selective-refresh-widgets' );
	add_theme_support(
		'html5',
		array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' )
	);
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 64,
			'width'       => 240,
			'flex-width'  => true,
			'flex-height' => true,
		)
	);

	register_nav_menus(
		array(
			'primary' => __( 'منوی اصلی', 'parsian-music' ),
			'footer'  => __( 'منوی فوتر', 'parsian-music' ),
		)
	);

	add_image_size( 'pm-teacher-card', 600, 800, true );
	add_image_size( 'pm-teacher-portrait', 900, 760, true );
	add_image_size( 'pm-teacher-avatar', 160, 160, true );
	add_image_size( 'pm-video-card', 640, 420, true );
	add_image_size( 'pm-blog-card', 860, 560, true );
}
add_action( 'after_setup_theme', 'pm_setup' );

/**
 * Content width.
 */
function pm_content_width() {
	$GLOBALS['content_width'] = 1280;
}
add_action( 'after_setup_theme', 'pm_content_width', 0 );

/**
 * Add page-specific body classes used by the stylesheet.
 *
 * @param array $classes Existing classes.
 * @return array
 */
function pm_body_classes( $classes ) {
	if ( is_front_page() ) {
		$classes[] = 'pm-front-page';
	}
	if ( is_singular( 'pm_teacher' ) ) {
		$classes[] = 'pm-teacher-single';
	}
	if ( is_post_type_archive( 'pm_teacher' ) ) {
		$classes[] = 'pm-teacher-archive';
	}
	if ( is_page_template( 'page-templates/full-width.php' ) ) {
		$classes[] = 'pm-full-width';
	}
	return $classes;
}
add_filter( 'body_class', 'pm_body_classes' );

/**
 * Remove the default "Category:" / "Tag:" prefixes from archive titles.
 *
 * @param string $title Archive title.
 * @return string
 */
function pm_archive_title( $title ) {
	if ( is_category() || is_tag() || is_tax() ) {
		$title = single_term_title( '', false );
	} elseif ( is_post_type_archive() ) {
		$title = post_type_archive_title( '', false );
	}
	return $title;
}
add_filter( 'get_the_archive_title', 'pm_archive_title' );
