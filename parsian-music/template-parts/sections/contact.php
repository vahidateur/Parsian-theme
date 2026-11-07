<?php
/**
 * FAQ, consultation form and assistant.
 *
 * @package Parsian_Music
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pm_title     = pm_get_option( 'contact_title', 'سوالات پرتکرار و رزرو مشاوره' );
$pm_compact   = isset( $args['compact'] ) ? (bool) $args['compact'] : false;
$pm_redirect  = home_url( '/#contact' );
if ( is_post_type_archive( 'pm_teacher' ) || is_tax( 'pm_department' ) ) {
	$pm_redirect = get_post_type_archive_link( 'pm_teacher' ) . '#contact';
}
?>
<section class="pm-section<?php echo $pm_compact ? ' pm-section--compact' : ''; ?>" id="contact">
	<div class="pm-container">
		<?php if ( ! $pm_compact ) : ?>
			<div class="pm-title"><h2><?php echo esc_html( $pm_title ); ?></h2></div>
		<?php endif; ?>
		<div class="pm-book">
			<div class="pm-book__col pm-faq" id="faq">
				<h3><?php echo pm_icon( 'help' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> سوالات پرتکرار</h3>
				<?php if ( is_active_sidebar( 'pm-faq-widgets' ) ) : ?>
					<?php dynamic_sidebar( 'pm-faq-widgets' ); ?>
				<?php else : ?>
					<?php foreach ( pm_get_faqs() as $pm_faq ) : ?>
						<details>
							<summary><?php echo pm_icon( 'chev-down' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php echo esc_html( $pm_faq['question'] ); ?></span></summary>
							<p><?php echo esc_html( $pm_faq['answer'] ); ?></p>
						</details>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>

			<div class="pm-book__col pm-book__form">
				<?php
				pm_consult_form(
					array(
						'id'            => 'pm-contact-consult',
						'context'       => 'contact',
						'compact'       => false,
						'show_tabs'     => true,
						'show_benefits' => false,
						'redirect'      => $pm_redirect,
					)
				);
				?>
			</div>

			<div class="pm-book__col pm-panel-footer-col">
				<?php get_template_part( 'template-parts/sections/panel-footer' ); ?>
			</div>
		</div>
	</div>
</section>
