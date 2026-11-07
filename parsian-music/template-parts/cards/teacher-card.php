<?php
/**
 * Teacher card – wide academy layout.
 *
 * @package Parsian_Music
 *
 * @var array $args Card args.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pm_teacher_post = isset( $args['post'] ) ? $args['post'] : get_post();
if ( ! $pm_teacher_post ) {
	return;
}

$pm_teacher_id      = $pm_teacher_post->ID;
$pm_teacher_name    = get_the_title( $pm_teacher_id );
$pm_teacher_role    = pm_teacher_meta( 'role', $pm_teacher_id, 'استاد آموزشگاه' );
$pm_teacher_degree  = pm_teacher_meta( 'degree', $pm_teacher_id );
$pm_teacher_dept    = pm_teacher_department( $pm_teacher_id );
$pm_teacher_dept_s  = $pm_teacher_dept ? $pm_teacher_dept->slug : 'other';
$pm_teacher_photo   = pm_teacher_portrait_url( $pm_teacher_id );
$pm_teacher_link    = get_permalink( $pm_teacher_id );
$pm_teacher_icon    = pm_teacher_icon( $pm_teacher_dept_s );
$pm_teacher_meta    = trim( $pm_teacher_degree . ' — ' . $pm_teacher_role, ' —' );
?>
<article class="pm-teacher pm-sq" data-pm-cat="<?php echo esc_attr( $pm_teacher_dept_s ); ?>">
	<a class="pm-teacher__media" href="<?php echo esc_url( $pm_teacher_link ); ?>" aria-label="<?php echo esc_attr( $pm_teacher_name ); ?>">
		<img src="<?php echo esc_url( $pm_teacher_photo ); ?>" alt="<?php echo esc_attr( $pm_teacher_name ); ?>" loading="lazy">
	</a>
	<div class="pm-teacher__body">
		<span class="pm-teacher__icon"><?php echo pm_icon( $pm_teacher_icon ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<h3><a href="<?php echo esc_url( $pm_teacher_link ); ?>"><?php echo esc_html( $pm_teacher_name ); ?></a></h3>
		<span class="pm-teacher__degree"><?php echo esc_html( $pm_teacher_meta ); ?></span>
		<a class="pm-teacher__action" href="<?php echo esc_url( $pm_teacher_link ); ?>" aria-label="مشاهدهٔ پروفایل <?php echo esc_attr( $pm_teacher_name ); ?>">
			<?php echo pm_icon( 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</a>
	</div>
</article>
