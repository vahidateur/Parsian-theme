<?php
/**
 * Search results.
 *
 * @package Parsian_Music
 */

get_header();

pm_page_hero(
	'نتایج جستجو برای: ' . get_search_query(),
	'اگر نتیجهٔ مورد نظر را پیدا نکردید، عبارت دیگری را امتحان کنید.',
	array( 'eyebrow' => 'جستجو' )
);
?>
<section class="pm-section">
	<div class="pm-container">
		<div class="pm-search-page__form"><?php get_search_form(); ?></div>

		<?php if ( have_posts() ) : ?>
			<h2 class="pm-sr-only">نتایج جستجو</h2>
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
				<?php echo pm_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<h2>نتیجه‌ای پیدا نشد</h2>
				<p>عبارت دیگری را جستجو کنید یا از منوی سایت استفاده کنید.</p>
				<a class="pm-btn pm-btn--gold" href="<?php echo esc_url( home_url( '/' ) ); ?>">بازگشت به خانه</a>
			</div>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
