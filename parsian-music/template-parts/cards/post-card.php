<?php
/**
 * Post card.
 *
 * @package Parsian_Music
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<article <?php post_class( 'pm-post-card' ); ?>>
	<a class="pm-post-card__media" href="<?php the_permalink(); ?>" aria-label="<?php the_title_attribute(); ?>">
		<?php if ( has_post_thumbnail() ) : ?>
			<?php the_post_thumbnail( 'pm-blog-card', array( 'loading' => 'lazy' ) ); ?>
		<?php else : ?>
			<span class="pm-post-card__placeholder"><?php echo pm_icon( 'music' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<?php endif; ?>
	</a>
	<div class="pm-post-card__body">
		<div class="pm-post-card__meta">
			<?php $pm_category = get_the_category(); ?>
			<?php if ( ! empty( $pm_category ) ) : ?>
				<span><?php echo esc_html( $pm_category[0]->name ); ?></span>
			<?php endif; ?>
			<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
		</div>
		<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
		<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></p>
		<a class="pm-post-card__more" href="<?php the_permalink(); ?>">
			ادامهٔ مطلب
			<?php echo pm_icon( 'arrow-left' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</a>
	</div>
</article>
