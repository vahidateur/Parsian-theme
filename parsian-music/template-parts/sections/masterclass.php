<?php
/**
 * Masterclass and hall slider.
 *
 * @package Parsian_Music
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pm_masterclass = pm_default_masterclass();
$pm_tag         = pm_get_option( 'masterclass_tag', $pm_masterclass['tag'] );
$pm_title       = pm_get_option( 'masterclass_title', $pm_masterclass['title'] );
$pm_text        = pm_get_option( 'masterclass_text', $pm_masterclass['text'] );
$pm_image       = pm_get_option( 'masterclass_image', $pm_masterclass['image'] );
$pm_cta_text    = pm_get_option( 'masterclass_cta_text', $pm_masterclass['cta_text'] );
$pm_cta_url     = pm_get_option( 'masterclass_cta_url', '' );
$pm_cta_url     = $pm_cta_url ? $pm_cta_url : pm_get_contact_url();
?>
<section class="pm-section" id="masterclass">
	<div class="pm-container pm-feature-row">
		<div class="pm-slider" data-pm-slider>
			<div class="pm-slider__slide is-on"><img src="<?php echo esc_url( pm_img( 'hall.jpg' ) ); ?>" alt="سالن اجرای آموزشگاه" loading="lazy"></div>
			<div class="pm-slider__slide"><img src="<?php echo esc_url( pm_img( 'hero.jpg' ) ); ?>" alt="فضای آکادمی موسیقی پارسیان" loading="lazy"></div>
			<div class="pm-dots">
				<button class="is-on" type="button" aria-label="اسلاید ۱"></button>
				<button type="button" aria-label="اسلاید ۲"></button>
			</div>
			<div class="pm-slider__nav">
				<button class="pm-circle-btn" type="button" data-pm-prev aria-label="قبلی"><?php echo pm_icon( 'chev-right' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
				<button class="pm-circle-btn" type="button" data-pm-next aria-label="بعدی"><?php echo pm_icon( 'chev-left' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
			</div>
		</div>

		<div class="pm-master">
			<img src="<?php echo esc_url( pm_media_url( $pm_image ) ); ?>" alt="رهبر ارکستر" loading="lazy">
			<div class="pm-master__body">
				<div class="pm-master__tag"><?php echo esc_html( $pm_tag ); ?></div>
				<h3><?php echo nl2br( esc_html( $pm_title ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h3>
				<p><?php echo nl2br( esc_html( $pm_text ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
				<a class="pm-btn pm-btn--gold" href="<?php echo esc_url( $pm_cta_url ); ?>"><?php echo esc_html( $pm_cta_text ); ?> <?php echo pm_arrow(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
			</div>
			<a class="pm-master__play" href="<?php echo esc_url( $pm_cta_url ); ?>" aria-label="اطلاعات مسترکلاس"><?php echo pm_icon( 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
		</div>
	</div>
</section>
