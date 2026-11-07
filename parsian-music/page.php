<?php
/**
 * Page template.
 *
 * @package Parsian_Music
 */

get_header();

while ( have_posts() ) :
	the_post();
	pm_page_hero( get_the_title(), '', array( 'eyebrow' => pm_brand_name() ) );
	?>
	<article <?php post_class( 'pm-page' ); ?>>
		<div class="pm-container pm-entry">
			<?php the_content(); ?>
			<?php wp_link_pages( array( 'before' => '<div class="pm-page-links">', 'after' => '</div>' ) ); ?>
		</div>
	</article>
	<?php
	if ( comments_open() || get_comments_number() ) {
		comments_template();
	}
endwhile;

get_footer();
