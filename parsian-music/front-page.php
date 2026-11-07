<?php
/**
 * Front page.
 *
 * @package Parsian_Music
 */

get_header();

get_template_part( 'template-parts/sections/hero-dashboard' );
get_template_part( 'template-parts/sections/features' );
get_template_part( 'template-parts/sections/departments' );
get_template_part( 'template-parts/sections/masterclass' );
get_template_part( 'template-parts/sections/stats' );
get_template_part( 'template-parts/sections/teachers' );
get_template_part( 'template-parts/sections/progress' );
get_template_part( 'template-parts/sections/contact' );

get_footer();
