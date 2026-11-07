<?php
/**
 * Footer
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<footer class="pm-footer">
	<div class="pm-container pm-footer__in">
		<a class="pm-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php echo pm_icon( 'music' ); // phpcs:ignore ?>
			<span class="pm-brand__t">
				<strong>کنسرواتوار موسیقی پارسیان</strong>
				<small>Parsian Music Conservatory</small>
			</span>
		</a>

		<nav class="pm-nav" aria-label="منوی فوتر">
			<?php
			wp_nav_menu( array(
				'theme_location' => 'footer',
				'container'      => false,
				'items_wrap'     => '<ul>%3$s</ul>',
				'fallback_cb'    => 'pm_fallback_menu',
			) );
			?>
		</nav>

		<div class="pm-social">
			<a href="#" aria-label="Instagram"><?php echo pm_icon( 'instagram' ); // phpcs:ignore ?></a>
			<a href="#" aria-label="YouTube"><?php echo pm_icon( 'youtube' ); // phpcs:ignore ?></a>
			<a href="#" aria-label="Telegram"><?php echo pm_icon( 'telegram' ); // phpcs:ignore ?></a>
		</div>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
