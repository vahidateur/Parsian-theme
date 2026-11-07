<?php
/**
 * Single post.
 *
 * @package Parsian_Music
 */

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article <?php post_class( 'pm-single' ); ?>>
		<header class="pm-page-hero pm-page-hero--post">
			<div class="pm-container">
				<div class="pm-post-meta">
					<?php $pm_category = get_the_category(); ?>
					<?php if ( ! empty( $pm_category ) ) : ?>
						<a href="<?php echo esc_url( get_category_link( $pm_category[0] ) ); ?>"><?php echo esc_html( $pm_category[0]->name ); ?></a>
					<?php endif; ?>
					<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
				</div>
				<h1><?php the_title(); ?></h1>
			</div>
		</header>

		<?php if ( has_post_thumbnail() ) : ?>
			<div class="pm-container pm-single__media">
				<?php the_post_thumbnail( 'full' ); ?>
			</div>
		<?php endif; ?>

		<div class="pm-container pm-entry pm-entry--single">
			<?php the_content(); ?>
			<?php wp_link_pages( array( 'before' => '<div class="pm-page-links">', 'after' => '</div>' ) ); ?>
		</div>

		<div class="pm-container pm-single__footer">
			<?php the_tags( '<div class="pm-tags">', '', '</div>' ); ?>
			<?php the_post_navigation( array( 'prev_text' => '<span>قبلی</span><strong>%title</strong>', 'next_text' => '<span>بعدی</span><strong>%title</strong>' ) ); ?>
		</div>
	</article>
	<?php
	if ( comments_open() || get_comments_number() ) {
		comments_template();
	}
endwhile;

get_footer();
