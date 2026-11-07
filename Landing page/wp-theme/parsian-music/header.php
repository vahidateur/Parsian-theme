<?php
/**
 * Header
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'pm-body' ); ?>>
<?php wp_body_open(); ?>
<?php readfile( get_template_directory() . '/assets/sprite.svg.html' ); ?>

<header class="pm-header" id="home">
	<div class="pm-container pm-header__in">
		<a class="pm-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php
			if ( has_custom_logo() ) {
				the_custom_logo();
			} else {
				echo pm_icon( 'wave' ); // phpcs:ignore
			}
			?>
			<span class="pm-brand__t">
				<strong>کنسرواتوار موسیقی پارسیان</strong>
				<small>Parsian Music Conservatory</small>
			</span>
		</a>

		<nav class="pm-nav" id="pm-nav" aria-label="منوی اصلی">
			<?php
			wp_nav_menu( array(
				'theme_location' => 'primary',
				'container'      => false,
				'items_wrap'     => '<ul>%3$s</ul>',
				'fallback_cb'    => 'pm_fallback_menu',
			) );
			?>
		</nav>

		<div class="pm-actions">
			<button class="pm-search" type="button" aria-label="جستجو"><?php echo pm_icon( 'search' ); // phpcs:ignore ?></button>
			<a class="pm-login" href="<?php echo esc_url( wp_login_url() ); ?>"><?php echo pm_icon( 'user' ); // phpcs:ignore ?> ورود / ثبت‌نام</a>
			<a class="pm-consult" href="#contact"><?php echo pm_icon( 'calendar' ); // phpcs:ignore ?> رزرو مشاوره</a>
			<button class="pm-burger" id="pm-burger" type="button" aria-label="منو" aria-controls="pm-nav"><?php echo pm_icon( 'menu' ); // phpcs:ignore ?></button>
		</div>
	</div>
</header>
