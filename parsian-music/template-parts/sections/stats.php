<?php
/**
 * Stats strip.
 *
 * @package Parsian_Music
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="pm-section" id="stats">
	<div class="pm-container">
		<div class="pm-stats">
			<?php echo pm_icon( 'laurel', 'pm-laurel pm-laurel--r' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php foreach ( pm_get_stats() as $stat ) : ?>
				<div><strong dir="ltr"><?php echo esc_html( $stat['value'] ); ?></strong><span><?php echo esc_html( $stat['label'] ); ?></span></div>
			<?php endforeach; ?>
			<?php echo pm_icon( 'laurel', 'pm-laurel pm-laurel--l' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	</div>
</section>
