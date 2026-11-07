<?php
/**
 * Customizer options.
 *
 * @package Parsian_Music
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitize the hero title while allowing line breaks and the gold highlight.
 *
 * @param string $value Raw value.
 * @return string
 */
function pm_sanitize_hero_title( $value ) {
	return wp_kses(
		$value,
		array(
			'br' => array(),
			'b'  => array( 'class' => array() ),
		)
	);
}

/**
 * Add a setting + control pair.
 *
 * @param WP_Customize_Manager $wp_customize Customizer.
 * @param string               $key          Key without the pm_ prefix.
 * @param string               $label        Label.
 * @param mixed                $default      Default.
 * @param string               $section      Section ID.
 * @param string               $type         Control type.
 */
function pm_add_customizer_field( $wp_customize, $key, $label, $default, $section, $type = 'text' ) {
	$setting_id = 'pm_' . $key;

	$sanitize = 'sanitize_text_field';
	if ( 'textarea' === $type ) {
		$sanitize = 'sanitize_textarea_field';
	} elseif ( 'email' === $type ) {
		$sanitize = 'sanitize_email';
	} elseif ( 'url' === $type || 'image' === $type ) {
		$sanitize = 'esc_url_raw';
	} elseif ( 'hero_title' === $type ) {
		$sanitize = 'pm_sanitize_hero_title';
	}

	$wp_customize->add_setting(
		$setting_id,
		array(
			'default'           => $default,
			'sanitize_callback' => $sanitize,
			'transport'         => 'refresh',
		)
	);

	if ( 'image' === $type ) {
		$wp_customize->add_control(
			new WP_Customize_Image_Control(
				$wp_customize,
				$setting_id,
				array(
					'label'   => $label,
					'section' => $section,
				)
			)
		);
		return;
	}

	$control_type = 'text';
	if ( 'textarea' === $type ) {
		$control_type = 'textarea';
	} elseif ( 'email' === $type ) {
		$control_type = 'email';
	} elseif ( 'url' === $type ) {
		$control_type = 'url';
	} elseif ( 'hero_title' === $type ) {
		$control_type = 'textarea';
	}

	$wp_customize->add_control(
		$setting_id,
		array(
			'label'       => $label,
			'section'     => $section,
			'type'        => $control_type,
			'input_attrs' => 'textarea' === $control_type ? array( 'rows' => 3 ) : array(),
		)
	);
}

/**
 * Register theme options.
 *
 * @param WP_Customize_Manager $wp_customize Customizer.
 */
function pm_customize_register( $wp_customize ) {
	$wp_customize->add_panel(
		'pm_theme_options',
		array(
			'title'    => 'تنظیمات آموزشگاه پارسیان',
			'priority' => 30,
		)
	);

	$wp_customize->add_section(
		'pm_brand_section',
		array(
			'title' => 'برند و هویت',
			'panel' => 'pm_theme_options',
		)
	);
	pm_add_customizer_field( $wp_customize, 'brand_name', 'نام برند (هدر و فوتر)', 'آکادمی موسیقی پارسیان', 'pm_brand_section' );
	pm_add_customizer_field( $wp_customize, 'brand_subtitle', 'زیرعنوان لاتین برند', 'Parsian Music Academy', 'pm_brand_section' );

	$wp_customize->add_section(
		'pm_contact_section',
		array(
			'title' => 'اطلاعات تماس و شبکه‌های اجتماعی',
			'panel' => 'pm_theme_options',
		)
	);
	pm_add_customizer_field( $wp_customize, 'phone', 'تلفن آموزشگاه', '۰۲۱-۱۲۳۴۵۶۷۸', 'pm_contact_section' );
	pm_add_customizer_field( $wp_customize, 'email', 'ایمیل عمومی', 'info@parsianmusic.ir', 'pm_contact_section', 'email' );
	pm_add_customizer_field( $wp_customize, 'address', 'آدرس', 'تهران، خیابان ولیعصر، پلاک ۱', 'pm_contact_section', 'textarea' );
	pm_add_customizer_field( $wp_customize, 'booking_email', 'ایمیل دریافت درخواست‌های مشاوره', '', 'pm_contact_section', 'email' );
	pm_add_customizer_field( $wp_customize, 'instagram', 'لینک اینستاگرام', '', 'pm_contact_section', 'url' );
	pm_add_customizer_field( $wp_customize, 'youtube', 'لینک یوتیوب', '', 'pm_contact_section', 'url' );
	pm_add_customizer_field( $wp_customize, 'telegram', 'لینک تلگرام', '', 'pm_contact_section', 'url' );

	$wp_customize->add_section(
		'pm_hero_section',
		array(
			'title' => 'بخش هیرو صفحهٔ اصلی',
			'panel' => 'pm_theme_options',
		)
	);
	pm_add_customizer_field( $wp_customize, 'hero_eyebrow', 'متن بالای عنوان', 'آموزش آکادمیک موسیقی با رویکرد بین‌المللی', 'pm_hero_section' );
	pm_add_customizer_field( $wp_customize, 'hero_title', 'عنوان اصلی', "مسیر حرفه‌ای\n<b class=\"pm-gold\">موسیقی</b> از اینجا آغاز می‌شود", 'pm_hero_section', 'hero_title' );
	pm_add_customizer_field( $wp_customize, 'hero_text', 'متن معرفی', 'در آکادمی موسیقی پارسیان، با اساتید برجسته، برنامه‌های آموزشی استاندارد و فضای تخصصی، استعداد خود را به اجرای حرفه‌ای تبدیل کنید.', 'pm_hero_section', 'textarea' );
	pm_add_customizer_field( $wp_customize, 'hero_cta_text', 'متن دکمهٔ اصلی', 'شروع مسیر هنری من', 'pm_hero_section' );
	pm_add_customizer_field( $wp_customize, 'hero_cta_url', 'لینک دکمهٔ اصلی', '#contact', 'pm_hero_section', 'url' );
	pm_add_customizer_field( $wp_customize, 'hero_image', 'تصویر هیرو', '', 'pm_hero_section', 'image' );

	$wp_customize->add_section(
		'pm_teachers_section',
		array(
			'title' => 'صفحهٔ اساتید',
			'panel' => 'pm_theme_options',
		)
	);
	pm_add_customizer_field( $wp_customize, 'teachers_archive_intro', 'معرفی کوتاه آرشیو اساتید', 'با اساتید برجستهٔ آکادمی موسیقی پارسیان آشنا شوید؛ از ویولن و پیانو تا موسیقی ایرانی، آواز و موسیقی کودک.', 'pm_teachers_section', 'textarea' );

	$wp_customize->add_section(
		'pm_footer_section',
		array(
			'title' => 'فوتر',
			'panel' => 'pm_theme_options',
		)
	);
	pm_add_customizer_field( $wp_customize, 'footer_text', 'متن کوتاه فوتر', 'آکادمی موسیقی پارسیان؛ مسیر حرفه‌ای موسیقی از اینجا آغاز می‌شود.', 'pm_footer_section', 'textarea' );
}
add_action( 'customize_register', 'pm_customize_register' );
