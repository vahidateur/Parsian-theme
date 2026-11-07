<?php
/**
 * Template Name: تمام عرض (بدون کانتینر)
 * Template Post Type: page
 *
 * Suitable for page builders such as Elementor.
 *
 * @package Parsian_Music
 */

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<div class="pm-full-width">
		<?php the_content(); ?>
	</div>
	<?php
endwhile;

get_footer();
