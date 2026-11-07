<?php
/**
 * Kids department banner.
 *
 * @package Parsian_Music
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pm_kids_url = add_query_arg( 'department', 'kids', pm_get_teacher_archive_url() );
?>
<section class="pm-section pm-kids-wrap">
	<div class="pm-container">
		<div class="pm-kids-banner pm-sq">
			<div class="pm-kids-banner__media">
				<img src="<?php echo esc_url( pm_img( 'dept-kids.jpg' ) ); ?>" alt="دپارتمان موسیقی کودک" loading="lazy">
			</div>
			<div class="pm-kids-banner__copy">
				<span class="pm-eyebrow">دیپارتمان تخصصی</span>
				<h2>دیپارتمان تخصصی موسیقی کودک</h2>
				<p>متد ارف و روانشناسی هنری</p>
				<a class="pm-btn pm-btn--gold" href="<?php echo esc_url( $pm_kids_url ); ?>">
					مشاهده مسیر آموزشی کودکان
					<?php echo pm_arrow(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</a>
			</div>
			<div class="pm-kids-banner__deco" aria-hidden="true">
				<?php echo pm_icon( 'music' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span></span><span></span><span></span>
			</div>
		</div>
	</div>
</section>
