<?php
/**
 * Feature strip.
 *
 * @package Parsian_Music
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="pm-features" aria-label="مزیت‌های آموزشگاه">
	<div class="pm-container">
		<ul class="pm-features__list">
			<?php foreach ( pm_get_features() as $feature ) : ?>
				<li>
					<?php echo pm_icon( $feature['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span><?php echo esc_html( $feature['title'] ); ?><br><?php echo esc_html( $feature['subtitle'] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
