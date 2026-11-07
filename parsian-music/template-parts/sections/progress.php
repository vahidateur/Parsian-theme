<?php
/**
 * Student progress and testimonial.
 *
 * @package Parsian_Music
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pm_default     = pm_default_testimonial();
$pm_testimonial = array(
	'name'   => pm_get_option( 'testimonial_name', $pm_default['name'] ),
	'role'   => pm_get_option( 'testimonial_role', $pm_default['role'] ),
	'text'   => pm_get_option( 'testimonial_text', $pm_default['text'] ),
	'avatar' => pm_get_option( 'testimonial_avatar', $pm_default['avatar'] ),
);
$pm_before      = pm_media_url( pm_get_option( 'progress_before_image', 'student.jpg' ) );
$pm_after       = pm_media_url( pm_get_option( 'progress_after_image', 'hero.jpg' ) );
$pm_title       = pm_get_option( 'progress_title', 'نمونه‌ای از مسیر پیشرفت هنرجویان' );
?>
<section class="pm-section" id="progress">
	<div class="pm-container">
		<div class="pm-title"><h2><?php echo esc_html( $pm_title ); ?></h2></div>

		<div class="pm-progress">
			<div class="pm-compare" data-pm-compare data-before="<?php echo esc_url( $pm_before ); ?>" data-after="<?php echo esc_url( $pm_after ); ?>">
				<div class="pm-compare__tabs" role="tablist" aria-label="مقایسهٔ قبل و بعد از آموزش">
					<button class="is-on" type="button" role="tab" aria-selected="true" data-view="after">بعد از آموزش</button>
					<button type="button" role="tab" aria-selected="false" data-view="before">قبل از آموزش</button>
				</div>
				<div class="pm-compare__stage">
					<img data-pm-compare-image src="<?php echo esc_url( $pm_after ); ?>" alt="مقایسهٔ پیشرفت هنرجوی ویولن" loading="lazy">
					<span class="pm-compare__label pm-compare__label--before">قبل</span>
					<span class="pm-compare__label pm-compare__label--after">بعد</span>
				</div>
			</div>

			<div class="pm-testi">
				<span class="pm-testi__q" aria-hidden="true">”</span>
				<div class="pm-testi__head">
					<img class="pm-testi__avatar" src="<?php echo esc_url( pm_media_url( $pm_testimonial['avatar'] ) ); ?>" alt="<?php echo esc_attr( $pm_testimonial['name'] ); ?>" loading="lazy">
					<div>
						<div class="pm-testi__name"><?php echo esc_html( $pm_testimonial['name'] ); ?></div>
						<div class="pm-testi__role"><?php echo esc_html( $pm_testimonial['role'] ); ?></div>
						<div class="pm-stars" role="img" aria-label="امتیاز ۵ از ۵">
							<?php for ( $pm_i = 0; $pm_i < 5; $pm_i++ ) : ?>
								<?php echo pm_icon( 'star' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php endfor; ?>
						</div>
					</div>
				</div>
				<p><?php echo esc_html( $pm_testimonial['text'] ); ?></p>
			</div>
		</div>
	</div>
</section>
