<?php
/**
 * Parsian Music Conservatory – theme functions
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PM_VERSION', '1.0.0' );

/* ---------------------------------------------------------------------
 * Setup
 * ------------------------------------------------------------------- */
function pm_setup() {
	load_theme_textdomain( 'parsian-music', get_template_directory() . '/languages' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'custom-logo', array( 'height' => 60, 'width' => 220, 'flex-width' => true, 'flex-height' => true ) );
	register_nav_menus( array(
		'primary' => __( 'منوی اصلی', 'parsian-music' ),
		'footer'  => __( 'منوی فوتر', 'parsian-music' ),
	) );
}
add_action( 'after_setup_theme', 'pm_setup' );

/* ---------------------------------------------------------------------
 * Assets
 * ------------------------------------------------------------------- */
function pm_assets() {
	wp_enqueue_style( 'pm-font', 'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&display=swap', array(), null );
	wp_enqueue_style( 'pm-main', get_template_directory_uri() . '/assets/css/main.css', array( 'pm-font' ), PM_VERSION );
	wp_enqueue_script( 'pm-main', get_template_directory_uri() . '/assets/js/main.js', array(), PM_VERSION, true );
}
add_action( 'wp_enqueue_scripts', 'pm_assets' );

/* ---------------------------------------------------------------------
 * Helpers
 * ------------------------------------------------------------------- */
function pm_img( $file ) {
	return esc_url( get_template_directory_uri() . '/assets/images/' . $file );
}

function pm_icon( $name, $class = '' ) {
	return '<svg class="ico ' . esc_attr( $class ) . '" aria-hidden="true"><use href="#i-' . esc_attr( $name ) . '"/></svg>';
}

/** Convert latin digits to Persian digits. */
function pm_fa( $str ) {
	return strtr( (string) $str, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) );
}

function pm_fallback_menu() {
	$items = array(
		'خانه'          => '#home',
		'دوره‌ها'        => '#courses',
		'اساتید'        => '#teachers',
		'مسترکلاس‌ها'   => '#masterclass',
		'پذیرش و کنکور' => '#faq',
		'درباره ما'     => '#stats',
		'ارتباط با ما'  => '#contact',
	);
	echo '<ul>';
	$i = 0;
	foreach ( $items as $label => $href ) {
		printf( '<li class="%s"><a href="%s">%s</a></li>', 0 === $i ? 'is-active' : '', esc_url( is_front_page() ? $href : home_url( '/' . $href ) ), esc_html( $label ) );
		$i++;
	}
	echo '</ul>';
}

/* ---------------------------------------------------------------------
 * Consultation form handler (admin-post)
 * ------------------------------------------------------------------- */
function pm_handle_consult() {
	if ( ! isset( $_POST['pm_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pm_nonce'] ) ), 'pm_consult' ) ) {
		wp_die( 'درخواست نامعتبر است.' );
	}
	$name       = sanitize_text_field( wp_unslash( $_POST['pm_name'] ?? '' ) );
	$phone      = sanitize_text_field( wp_unslash( $_POST['pm_phone'] ?? '' ) );
	$instrument = sanitize_text_field( wp_unslash( $_POST['pm_instrument'] ?? '' ) );
	$type       = sanitize_text_field( wp_unslash( $_POST['pm_type'] ?? 'inperson' ) );

	$to      = get_option( 'admin_email' );
	$subject = 'درخواست مشاوره جدید – ' . $name;
	$body    = "نام: $name\nموبایل: $phone\nساز: $instrument\nنوع مشاوره: " . ( 'phone' === $type ? 'تلفنی' : 'حضوری' );
	wp_mail( $to, $subject, $body );

	wp_safe_redirect( add_query_arg( 'pm_sent', '1', home_url( '/#contact' ) ) );
	exit;
}
add_action( 'admin_post_nopriv_pm_consult', 'pm_handle_consult' );
add_action( 'admin_post_pm_consult', 'pm_handle_consult' );
