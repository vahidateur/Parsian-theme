<?php
/**
 * Departments.
 *
 * @package Parsian_Music
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$pm_archive = pm_get_teacher_archive_url();
$pm_title   = pm_get_option( 'departments_title', 'دپارتمان‌های آموزشی' );
?>
<section class="pm-section" id="courses">
	<div class="pm-container">
		<div class="pm-title"><h2><?php echo esc_html( $pm_title ); ?></h2></div>
		<div class="pm-depts">
			<?php foreach ( pm_get_departments() as $department ) : ?>
				<a class="pm-dept" href="<?php echo esc_url( add_query_arg( 'department', $department['slug'], $pm_archive ) ); ?>">
					<img src="<?php echo esc_url( pm_media_url( $department['image'] ) ); ?>" alt="<?php echo esc_attr( trim( $department['title'] . ' ' . $department['subtitle'] ) ); ?>" loading="lazy">
					<span class="pm-dept__t">
						<?php echo esc_html( $department['title'] ); ?>
						<?php if ( $department['subtitle'] ) : ?><small><?php echo esc_html( $department['subtitle'] ); ?></small><?php endif; ?>
					</span>
					<?php echo pm_arrow(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
