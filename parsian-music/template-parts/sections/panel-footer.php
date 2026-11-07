<?php
/**
 * Footer content used inside the bottom contact panel.
 *
 * @package Parsian_Music
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pm_panel_phone   = pm_get_option( 'phone', '۰۲۱-۱۲۳۴۵۶۷۸' );
$pm_panel_email   = pm_get_option( 'email', 'info@parsianmusic.ir' );
$pm_panel_address = pm_get_option( 'address', 'تهران، خیابان ولیعصر، پلاک ۱' );
$pm_panel_text    = pm_get_option( 'footer_text', 'آکادمی موسیقی پارسیان؛ مسیر حرفه‌ای موسیقی از اینجا آغاز می‌شود.' );
$pm_panel_socials = pm_get_social_links();
$pm_map_url       = pm_get_map_url();
$pm_map_label     = pm_get_map_label();

if ( is_active_sidebar( 'pm-panel-footer' ) ) {
	echo '<div class="pm-panel-footer pm-panel-footer--widgets">';
	dynamic_sidebar( 'pm-panel-footer' );
	echo '</div>';
	return;
}
?>
<div class="pm-panel-footer">
	<a class="pm-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
		<?php echo pm_icon( 'wave', 'pm-brand__mark' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<span class="pm-brand__t">
			<strong><?php echo esc_html( pm_brand_name() ); ?></strong>
			<small><?php echo esc_html( pm_brand_subtitle() ); ?></small>
		</span>
	</a>

	<p class="pm-panel-footer__tagline"><?php echo esc_html( $pm_panel_text ); ?></p>

	<div class="pm-panel-footer__links">
		<h4>دسترسی سریع</h4>
		<?php pm_primary_menu( 'footer' ); ?>
	</div>

	<div class="pm-panel-footer__contact">
		<h4>ارتباط با ما</h4>
		<a href="tel:<?php echo esc_attr( pm_to_en_digits( $pm_panel_phone ) ); ?>">
			<?php echo pm_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<span dir="ltr"><?php echo esc_html( $pm_panel_phone ); ?></span>
		</a>
		<a href="mailto:<?php echo esc_attr( $pm_panel_email ); ?>">
			<?php echo pm_icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<span dir="ltr"><?php echo esc_html( $pm_panel_email ); ?></span>
		</a>
		<p>
			<?php echo pm_icon( 'location' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<span><?php echo esc_html( $pm_panel_address ); ?></span>
		</p>
		<a class="pm-panel-footer__map" href="<?php echo esc_url( $pm_map_url ); ?>" target="_blank" rel="noopener noreferrer">
			<?php echo pm_icon( 'road' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<span><?php echo esc_html( $pm_map_label ); ?></span>
		</a>
	</div>

	<?php if ( ! empty( $pm_panel_socials ) ) : ?>
		<div class="pm-social">
			<?php foreach ( $pm_panel_socials as $social ) : ?>
				<a href="<?php echo esc_url( $social['url'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( $social['label'] ); ?>">
					<?php echo pm_icon( $social['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<p class="pm-panel-footer__copy">
		© <?php echo esc_html( pm_fa( gmdate( 'Y' ) ) ); ?> <?php echo esc_html( pm_brand_name() ); ?> — تمامی حقوق محفوظ است.
	</p>
</div>
