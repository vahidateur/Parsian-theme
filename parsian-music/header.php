<?php
/**
 * Header.
 *
 * @package Parsian_Music
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pm_login_redirect = is_front_page() ? home_url( '/' ) : ( get_permalink() ? get_permalink() : home_url( '/' ) );
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#080909">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'pm-body' ); ?>>
<?php wp_body_open(); ?>
<?php pm_sprite(); ?>
<a class="pm-skip-link" href="#pm-content">رفتن به محتوای اصلی</a>

<header class="pm-header <?php echo is_front_page() ? 'pm-header--overlay' : 'pm-header--solid'; ?>" id="home">
	<div class="pm-container pm-header__in">
		<a class="pm-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php
			$logo_id = (int) get_theme_mod( 'custom_logo' );
			if ( $logo_id ) {
				echo wp_get_attachment_image( $logo_id, 'full', false, array( 'class' => 'pm-brand__logo', 'alt' => pm_brand_name() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				echo pm_icon( 'wave', 'pm-brand__mark' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			?>
			<span class="pm-brand__t">
				<strong><?php echo esc_html( pm_brand_name() ); ?></strong>
				<small><?php echo esc_html( pm_brand_subtitle() ); ?></small>
			</span>
		</a>

		<nav class="pm-nav" id="pm-primary-nav" aria-label="منوی اصلی">
			<?php pm_primary_menu( 'primary' ); ?>
		</nav>

		<div class="pm-actions">
			<button class="pm-search" type="button" aria-label="جستجو" aria-expanded="false" aria-controls="pm-search-panel" data-pm-search-toggle>
				<?php echo pm_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</button>

			<?php if ( is_user_logged_in() ) : ?>
				<a class="pm-login" href="<?php echo esc_url( admin_url() ); ?>"><?php echo pm_icon( 'user' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> پیشخوان</a>
			<?php else : ?>
				<a class="pm-login" href="<?php echo esc_url( wp_login_url( $pm_login_redirect ) ); ?>"><?php echo pm_icon( 'user' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> ورود / ثبت‌نام</a>
			<?php endif; ?>

			<a class="pm-consult" href="<?php echo esc_url( pm_get_contact_url() ); ?>"><?php echo pm_icon( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> رزرو مشاوره</a>

			<button class="pm-burger" id="pm-burger" type="button" aria-label="منو" aria-controls="pm-primary-nav" aria-expanded="false" data-pm-burger>
				<?php echo pm_icon( 'menu' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</button>
		</div>
	</div>

	<div class="pm-search-panel" id="pm-search-panel" hidden>
		<div class="pm-container">
			<?php get_search_form(); ?>
		</div>
	</div>
</header>

<main id="pm-content" class="pm-site-main">
