<?php
/**
 * Teachers grid.
 *
 * @package Parsian_Music
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pm_teacher_query = new WP_Query(
	array(
		'post_type'      => 'pm_teacher',
		'posts_per_page' => 6,
		'orderby'        => array(
			'menu_order' => 'ASC',
			'date'       => 'DESC',
		),
	)
);

$pm_terms = get_terms(
	array(
		'taxonomy'   => 'pm_department',
		'hide_empty' => true,
	)
);
$pm_terms = is_wp_error( $pm_terms ) ? array() : $pm_terms;
?>
<section class="pm-section" id="teachers">
	<div class="pm-container">
		<div class="pm-title"><h2><?php echo esc_html( pm_get_option( 'teachers_title', 'اساتید تراز اول آکادمی موسیقی' ) ); ?></h2></div>

		<div class="pm-teachers-filter" data-pm-filter-root>
			<?php if ( ! empty( $pm_terms ) ) : ?>
				<div class="pm-chips" role="group" aria-label="فیلتر اساتید بر اساس دپارتمان">
					<button class="pm-chip is-on" type="button" data-pm-filter="all" aria-pressed="true">همه</button>
					<?php foreach ( $pm_terms as $pm_term ) : ?>
						<button class="pm-chip" type="button" data-pm-filter="<?php echo esc_attr( $pm_term->slug ); ?>" aria-pressed="false"><?php echo esc_html( $pm_term->name ); ?></button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( $pm_teacher_query->have_posts() ) : ?>
				<div class="pm-teachers">
					<?php
					while ( $pm_teacher_query->have_posts() ) :
						$pm_teacher_query->the_post();
						get_template_part( 'template-parts/cards/teacher-card', null, array( 'post' => get_post() ) );
					endwhile;
					wp_reset_postdata();
					?>
				</div>
			<?php else : ?>
				<div class="pm-empty">
					<?php echo pm_icon( 'users' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<h3>هنوز استادی ثبت نشده است</h3>
					<p>از پیشخوان وردپرس، بخش «اساتید»، اولین استاد را اضافه کنید.</p>
					<?php if ( current_user_can( 'edit_posts' ) ) : ?>
						<a class="pm-btn pm-btn--gold" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=pm_teacher' ) ); ?>">افزودن استاد</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="pm-section__foot">
			<a class="pm-btn pm-btn--outline" href="<?php echo esc_url( pm_get_teacher_archive_url() ); ?>">
				مشاهدهٔ همهٔ اساتید
				<?php echo pm_arrow(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</a>
		</div>
	</div>
</section>
