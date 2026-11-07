<?php
/**
 * Lightweight SEO fallbacks.
 *
 * @package Parsian_Music
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Print a meta description when no major SEO plugin is active.
 */
function pm_meta_description() {
	if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || defined( 'AIOSEO_VERSION' ) ) {
		return;
	}

	$description = '';

	if ( is_front_page() ) {
		$description = get_bloginfo( 'description' );
		if ( ! $description ) {
			$description = pm_brand_name() . '؛ آموزش آکادمیک موسیقی با رویکرد بین‌المللی.';
		}
	} elseif ( is_singular( 'pm_teacher' ) ) {
		$teacher_id  = get_the_ID();
		$description = pm_teacher_meta( 'bio', $teacher_id );
		if ( ! $description ) {
			$description = get_the_excerpt();
		}
	} elseif ( is_post_type_archive( 'pm_teacher' ) ) {
		$description = pm_get_option( 'teachers_archive_intro', 'با اساتید برجستهٔ آکادمی موسیقی پارسیان آشنا شوید.' );
	} elseif ( is_singular() ) {
		$description = get_the_excerpt();
		if ( ! $description ) {
			$description = wp_strip_all_tags( get_the_content() );
		}
	} elseif ( is_archive() ) {
		$description = get_the_archive_description();
	} elseif ( is_search() ) {
		$description = 'نتایج جستجو برای: ' . get_search_query();
	}

	$description = trim( wp_strip_all_tags( (string) $description ) );
	if ( ! $description ) {
		return;
	}

	echo '<meta name="description" content="' . esc_attr( wp_trim_words( $description, 30, '…' ) ) . '">' . "\n";
}
add_action( 'wp_head', 'pm_meta_description', 1 );
