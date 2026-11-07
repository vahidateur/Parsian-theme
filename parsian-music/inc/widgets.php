<?php
/**
 * Widget areas used by the bottom panel.
 *
 * @package Parsian_Music
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register panel widget areas.
 */
function pm_register_panel_sidebars() {
	register_sidebar(
		array(
			'name'          => 'فوتر پنل پایینی',
			'id'            => 'pm-panel-footer',
			'description'   => 'اگر در این بخش ویجت اضافه کنید، جایگزین محتوای پیش‌فرض برند، دسترسی سریع و تماس در پنل پایینی می‌شود.',
			'before_widget' => '<div id="%1$s" class="pm-panel-widget %2$s">',
			'after_widget'  => '</div>',
			'before_title'  => '<h4 class="pm-panel-widget__title">',
			'after_title'   => '</h4>',
		)
	);

	register_sidebar(
		array(
			'name'          => 'سوالات متداول (ویجت)',
			'id'            => 'pm-faq-widgets',
			'description'   => 'اگر ویجت اضافه کنید، جایگزین سوالات متداول تنظیمات پوسته در پنل پایینی می‌شود.',
			'before_widget' => '<div id="%1$s" class="pm-panel-widget %2$s">',
			'after_widget'  => '</div>',
			'before_title'  => '<h4 class="pm-panel-widget__title">',
			'after_title'   => '</h4>',
		)
	);
}
add_action( 'widgets_init', 'pm_register_panel_sidebars' );
