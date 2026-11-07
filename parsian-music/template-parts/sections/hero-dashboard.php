<?php
/**
 * Homepage dashboard hero.
 *
 * @package Parsian_Music
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pm_featured = get_posts(
	array(
		'post_type'      => 'pm_teacher',
		'posts_per_page' => 1,
		'orderby'        => array(
			'menu_order' => 'ASC',
			'date'       => 'DESC',
		),
	)
);

$pm_featured_id     = ! empty( $pm_featured ) ? $pm_featured[0]->ID : 0;
$pm_featured_name   = $pm_featured_id ? get_the_title( $pm_featured_id ) : 'استاد محمدرضا کریمی';
$pm_featured_role   = $pm_featured_id ? pm_teacher_meta( 'role', $pm_featured_id, 'مدیر مسئول و استاد ویولن' ) : 'مدیر مسئول و استاد ویولن';
$pm_featured_photo  = $pm_featured_id ? pm_teacher_portrait_url( $pm_featured_id ) : pm_img( 'teacher-karimi.webp' );
$pm_featured_url    = $pm_featured_id ? get_permalink( $pm_featured_id ) : pm_get_teacher_archive_url();
$pm_hero_image      = pm_get_option( 'hero_image', '' );
$pm_hero_image      = $pm_hero_image ? $pm_hero_image : pm_img( 'hero.jpg' );
$pm_hero_title      = pm_get_option( 'hero_title', "مسیر حرفه‌ای\n<b class=\"pm-gold\">موسیقی</b> از اینجا آغاز می‌شود" );
$pm_hero_text       = pm_get_option( 'hero_text', 'در آکادمی موسیقی پارسیان، با اساتید برجسته، برنامه‌های آموزشی استاندارد و فضای تخصصی، استعداد خود را به اجرای حرفه‌ای تبدیل کنید.' );
$pm_hero_cta_text   = pm_get_option( 'hero_cta_text', 'شروع مسیر هنری من' );
$pm_hero_cta_url    = pm_get_option( 'hero_cta_url', '#contact' );
$pm_hero_eyebrow    = pm_get_option( 'hero_eyebrow', 'آموزش آکادمیک موسیقی با رویکرد بین‌المللی' );
$pm_hero_badge      = pm_get_option( 'hero_badge', 'پذیرش هنرجوی جدید' );
$pm_secondary_text  = pm_get_option( 'hero_secondary_text', 'معرفی آموزشگاه' );
$pm_secondary_url   = pm_get_option( 'hero_secondary_url', '#masterclass' );
$pm_hero_points     = pm_get_hero_points();
$pm_hero_numbers    = pm_get_hero_numbers();
?>
<section class="pm-hero-dash" id="home">
	<div class="pm-container">
		<div class="pm-hero-dash__grid">
			<aside class="pm-hero-dash__media" aria-label="معرفی آموزشگاه">
				<div class="pm-hero-dash__image">
					<img src="<?php echo esc_url( $pm_hero_image ); ?>" alt="فضای آکادمی موسیقی پارسیان" fetchpriority="high">
					<span class="pm-hero-dash__shade"></span>
					<span class="pm-hero-dash__badge"><?php echo pm_icon( 'sparkles' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( $pm_hero_badge ); ?></span>

					<a class="pm-hero-dash__teacher" href="<?php echo esc_url( $pm_featured_url ); ?>">
						<img src="<?php echo esc_url( $pm_featured_photo ); ?>" alt="<?php echo esc_attr( $pm_featured_name ); ?>" loading="lazy">
						<span class="pm-hero-dash__teacher-copy">
							<small>مدیر مسئول آموزشگاه</small>
							<strong><?php echo esc_html( $pm_featured_name ); ?></strong>
							<span><?php echo esc_html( $pm_featured_role ); ?></span>
						</span>
						<?php echo pm_arrow(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</a>
				</div>

				<div class="pm-hero-dash__numbers">
					<?php foreach ( $pm_hero_numbers as $pm_number ) : ?>
						<div><strong><?php echo esc_html( $pm_number['value'] ); ?></strong><span><?php echo esc_html( $pm_number['label'] ); ?></span></div>
					<?php endforeach; ?>
				</div>
			</aside>

			<div class="pm-hero-dash__content">
				<div class="pm-eyebrow"><?php echo esc_html( $pm_hero_eyebrow ); ?></div>
				<h1><?php echo wp_kses_post( nl2br( $pm_hero_title ) ); ?></h1>
				<p class="pm-hero-dash__text"><?php echo esc_html( $pm_hero_text ); ?></p>

				<div class="pm-hero-dash__btns">
					<a class="pm-btn pm-btn--gold" href="<?php echo esc_url( $pm_hero_cta_url ); ?>">
						<?php echo esc_html( $pm_hero_cta_text ); ?>
						<?php echo pm_arrow(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</a>
					<a class="pm-btn pm-btn--ghost" href="<?php echo esc_url( $pm_secondary_url ); ?>">
						<?php echo pm_icon( 'play-circle' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo esc_html( $pm_secondary_text ); ?>
					</a>
				</div>

				<div class="pm-hero-dash__quick">
					<?php foreach ( $pm_hero_points as $pm_point ) : ?>
						<span><?php echo pm_icon( $pm_point['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( $pm_point['text'] ); ?></span>
					<?php endforeach; ?>
				</div>
			</div>

			<aside class="pm-hero-dash__booking" aria-label="رزرو مشاوره">
				<?php
				pm_consult_form(
					array(
						'id'          => 'pm-hero-consult',
						'context'     => 'hero',
						'compact'     => true,
						'show_tabs'   => false,
						'title'       => 'رزرو مشاورهٔ سریع',
						'description' => 'در چند دقیقه مسیر آموزشی خودت را مشخص کن.',
						'button'      => 'رزرو مشاوره',
					)
				);
				?>
				<div class="pm-hero-dash__trust">
					<span><?php echo pm_icon( 'headset' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> پاسخ‌گویی سریع</span>
					<span><?php echo pm_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> مشاورهٔ رایگان</span>
				</div>
			</aside>
		</div>
	</div>
</section>
