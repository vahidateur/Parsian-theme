<?php
/**
 * Fallback template (blog, pages, archives, search)
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
get_header();
?>
<main class="pm-page">
	<?php if ( have_posts() ) : ?>
		<?php if ( is_home() && ! is_front_page() ) : ?>
			<h1><?php single_post_title(); ?></h1>
		<?php elseif ( is_archive() ) : ?>
			<h1><?php the_archive_title(); ?></h1>
		<?php elseif ( is_search() ) : ?>
			<h1>نتایج جستجو برای: <?php echo esc_html( get_search_query() ); ?></h1>
		<?php endif; ?>

		<?php while ( have_posts() ) : the_post(); ?>
			<article <?php post_class(); ?>>
				<h1><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h1>
				<div class="pm-entry">
					<?php is_singular() ? the_content() : the_excerpt(); ?>
				</div>
			</article>
		<?php endwhile; ?>

		<?php the_posts_pagination(); ?>
	<?php else : ?>
		<h1>مطلبی یافت نشد</h1>
		<p class="pm-entry">متأسفانه چیزی پیدا نشد.</p>
	<?php endif; ?>
</main>
<?php
get_footer();
