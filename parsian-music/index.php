<?php
/**
 * Fallback template.
 *
 * @package Parsian_Music
 */

get_header();

$pm_title = is_home() && ! is_front_page() ? single_post_title( '', false ) : 'مجلهٔ آموزشگاه';
pm_page_hero( $pm_title, 'یادداشت‌ها، مقالات و اخبار آکادمی موسیقی پارسیان.', array( 'eyebrow' => 'مجله' ) );
?>
<section class="pm-section">
	<div class="pm-container">
		<?php if ( have_posts() ) : ?>
			<h2 class="pm-sr-only">لیست مطالب</h2>
			<div class="pm-posts-grid">
				<?php
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/cards/post-card' );
				endwhile;
				?>
			</div>
			<?php pm_pagination(); ?>
		<?php else : ?>
			<div class="pm-empty">
				<?php echo pm_icon( 'edit' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<h2>هنوز مطلبی منتشر نشده است</h2>
				<p>به‌زودی یادداشت‌ها و مقالات آموزشگاه در این بخش منتشر می‌شوند.</p>
				<a class="pm-btn pm-btn--gold" href="<?php echo esc_url( home_url( '/' ) ); ?>">بازگشت به خانه</a>
			</div>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
