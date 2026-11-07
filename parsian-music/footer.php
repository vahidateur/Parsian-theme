<?php
/**
 * Footer.
 *
 * @package Parsian_Music
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pm_footer_text   = pm_get_option( 'footer_text', 'آکادمی موسیقی پارسیان؛ مسیر حرفه‌ای موسیقی از اینجا آغاز می‌شود.' );
$pm_footer_credit = pm_get_option( 'footer_credit', 'طراحی مسیر آموزشی با مشاورهٔ رایگان' );
$pm_socials       = pm_get_social_links();
$pm_phone         = pm_get_option( 'phone', '۰۲۱-۱۲۳۴۵۶۷۸' );
$pm_email         = pm_get_option( 'email', 'info@parsianmusic.ir' );
$pm_address       = pm_get_option( 'address', 'تهران، خیابان ولیعصر، پلاک ۱' );
$pm_map_url       = pm_get_map_url();
$pm_map_label     = pm_get_map_label();
?>
</main>

<?php
$pm_panel_has_footer = is_front_page() || is_post_type_archive( 'pm_teacher' ) || is_tax( 'pm_department' );
if ( ! $pm_panel_has_footer ) :
	?>
<footer class="pm-footer">
	<div class="pm-container">
		<div class="pm-footer__bar">
			<div class="pm-footer__brand">
				<a class="pm-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
					<?php echo pm_icon( 'wave', 'pm-brand__mark' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span class="pm-brand__t">
						<strong><?php echo esc_html( pm_brand_name() ); ?></strong>
						<small><?php echo esc_html( pm_brand_subtitle() ); ?></small>
					</span>
				</a>
				<p><?php echo esc_html( $pm_footer_text ); ?></p>

				<?php if ( ! empty( $pm_socials ) ) : ?>
					<div class="pm-social">
						<?php foreach ( $pm_socials as $social ) : ?>
							<a href="<?php echo esc_url( $social['url'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( $social['label'] ); ?>">
								<?php echo pm_icon( $social['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="pm-footer__links">
				<h3>دسترسی سریع</h3>
				<?php pm_primary_menu( 'footer' ); ?>
			</div>

			<div class="pm-footer__contact">
				<h3>ارتباط با ما</h3>
				<a href="tel:<?php echo esc_attr( pm_to_en_digits( $pm_phone ) ); ?>"><?php echo pm_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span dir="ltr"><?php echo esc_html( $pm_phone ); ?></span></a>
				<a href="mailto:<?php echo esc_attr( $pm_email ); ?>"><?php echo pm_icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span dir="ltr"><?php echo esc_html( $pm_email ); ?></span></a>
				<p><?php echo pm_icon( 'location' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php echo esc_html( $pm_address ); ?></span></p>
				<a class="pm-footer__map" href="<?php echo esc_url( $pm_map_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo pm_icon( 'road' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php echo esc_html( $pm_map_label ); ?></span></a>
			</div>
		</div>

		<div class="pm-footer__bottom">
			<div class="pm-footer__bottom-in">
				<span>© <?php echo esc_html( pm_fa( gmdate( 'Y' ) ) ); ?> <?php echo esc_html( pm_brand_name() ); ?> — تمامی حقوق محفوظ است.</span>
				<a href="<?php echo esc_url( home_url( '/#contact' ) ); ?>"><?php echo esc_html( $pm_footer_credit ); ?></a>
			</div>
		</div>
	</div>
</footer>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
