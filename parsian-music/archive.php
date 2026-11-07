<?php
/**
 * Archive template.
 *
 * @package Parsian_Music
 */

get_header();

pm_page_hero( get_the_archive_title(), get_the_archive_description(), array( 'eyebrow' => 'آرشیو' ) );
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
				<h2>مطلبی پیدا نشد</h2>
				<p>در این بخش هنوز مطلبی منتشر نشده است.</p>
				<a class="pm-btn pm-btn--gold" href="<?php echo esc_url( home_url( '/' ) ); ?>">بازگشت به خانه</a>
			</div>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
