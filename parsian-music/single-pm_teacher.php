<?php
/**
 * Single teacher profile.
 *
 * @package Parsian_Music
 */

get_header();

while ( have_posts() ) :
	the_post();

	$pm_id         = get_the_ID();
	$pm_name       = get_the_title();
	$pm_english    = pm_teacher_meta( 'english_name', $pm_id );
	$pm_role       = pm_teacher_meta( 'role', $pm_id, 'استاد آموزشگاه' );
	$pm_instrument = pm_teacher_meta( 'instrument', $pm_id );
	$pm_degree     = pm_teacher_meta( 'degree', $pm_id );
	$pm_experience = pm_teacher_meta( 'experience', $pm_id );
	$pm_days       = pm_teacher_presence_days( $pm_id );
	$pm_dept       = pm_teacher_department( $pm_id );
	$pm_portrait   = pm_teacher_portrait_url( $pm_id );
	$pm_stats      = pm_teacher_stats( $pm_id );
	$pm_awards     = pm_teacher_achievements( $pm_id );
	$pm_videos     = pm_teacher_videos( $pm_id );
	$pm_video      = pm_teacher_meta( 'video_intro', $pm_id );
	$pm_audio      = pm_teacher_meta( 'audio', $pm_id );
	$pm_before     = pm_media_url( pm_teacher_meta( 'before_image', $pm_id ), 'large' );
	$pm_after      = pm_media_url( pm_teacher_meta( 'after_image', $pm_id ), 'large' );
	$pm_before     = $pm_before ? $pm_before : pm_img( 'student.jpg' );
	$pm_after      = $pm_after ? $pm_after : pm_img( 'student-after.webp' );
	$pm_bio        = pm_teacher_meta( 'bio', $pm_id );
	$pm_testi_name = pm_teacher_meta( 'testimonial_name', $pm_id );
	$pm_testi_role = pm_teacher_meta( 'testimonial_role', $pm_id, 'هنرجوی آموزشگاه' );
	$pm_testi_text = pm_teacher_meta( 'testimonial_text', $pm_id );
	$pm_testi_img  = pm_media_url( pm_teacher_meta( 'testimonial_avatar', $pm_id ), 'pm-teacher-avatar' );

	if ( ! $pm_bio ) {
		$pm_bio = get_the_excerpt();
	}
	if ( ! $pm_bio ) {
		$pm_bio = wp_trim_words( wp_strip_all_tags( get_the_content() ), 44 );
	}

	$pm_stat_icons = array( 'trophy', 'laurel', 'building', 'education' );
	?>
	<div class="pm-teacher-page">
		<div class="pm-container pm-teacher-breadcrumb">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">خانه</a>
			<span aria-hidden="true">/</span>
			<a href="<?php echo esc_url( pm_get_teacher_archive_url() ); ?>">اساتید</a>
			<span aria-hidden="true">/</span>
			<span><?php echo esc_html( $pm_name ); ?></span>
		</div>

		<div class="pm-teacher-stage">
			<div class="pm-tp-dashboard">
				<aside class="pm-tp-portrait-column">
					<div class="pm-tp-portrait">
						<img src="<?php echo esc_url( $pm_portrait ); ?>" alt="<?php echo esc_attr( $pm_name ); ?>">
						<?php if ( ! empty( $pm_days ) ) : ?>
							<div class="pm-tp-presence">
								<span>روزهای حضور استاد</span>
								<span class="pm-tp-presence__days">
									<?php foreach ( $pm_days as $pm_day ) : ?>
										<b><?php echo esc_html( $pm_day ); ?></b>
									<?php endforeach; ?>
								</span>
							</div>
						<?php endif; ?>
					</div>

					<div class="pm-tp-portrait-lower">
						<section class="pm-tp-card pm-tp-progress-card" aria-labelledby="pm-progress-title">
							<div class="pm-tp-card-title" id="pm-progress-title">
								<span>روند پیشرفت هنرجو</span>
								<?php echo pm_icon( 'chart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>

							<div class="pm-tp-compare" data-pm-compare data-before="<?php echo esc_url( $pm_before ); ?>" data-after="<?php echo esc_url( $pm_after ); ?>">
								<div class="pm-tp-compare-tabs" role="tablist" aria-label="مقایسهٔ قبل و بعد از آموزش">
									<button class="is-on" type="button" role="tab" aria-selected="true" data-view="after">بعد از آموزش</button>
									<button type="button" role="tab" aria-selected="false" data-view="before">قبل از آموزش</button>
								</div>
								<div class="pm-tp-comparison">
									<img data-pm-compare-image src="<?php echo esc_url( $pm_after ); ?>" alt="مقایسهٔ پیشرفت هنرجو" loading="lazy">
									<span class="pm-tp-compare-label pm-tp-compare-label--before">قبل</span>
									<span class="pm-tp-compare-label pm-tp-compare-label--after">بعد</span>
								</div>
							</div>
						</section>

						<section class="pm-tp-card pm-tp-achievements" aria-labelledby="pm-achievements-title">
							<div class="pm-tp-card-title" id="pm-achievements-title">
								<span>دستاوردهای هنرجویان</span>
								<?php echo pm_icon( 'trophy' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
							<div class="pm-tp-achievement-grid">
								<?php foreach ( $pm_awards as $pm_award ) : ?>
									<div class="pm-tp-achievement">
										<strong><?php echo esc_html( $pm_award['value'] ); ?></strong>
										<span><?php echo esc_html( $pm_award['label'] ); ?></span>
									</div>
								<?php endforeach; ?>
							</div>
						</section>

						<?php if ( $pm_testi_name && $pm_testi_text ) : ?>
							<section class="pm-tp-card pm-tp-testimonial" aria-labelledby="pm-testimonial-title">
								<div class="pm-tp-card-title" id="pm-testimonial-title">
									<span>نظر هنرجو</span>
									<?php echo pm_icon( 'quote' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</div>
								<div class="pm-tp-testimonial__head">
									<?php if ( $pm_testi_img ) : ?>
										<img src="<?php echo esc_url( $pm_testi_img ); ?>" alt="<?php echo esc_attr( $pm_testi_name ); ?>" loading="lazy">
									<?php endif; ?>
									<div>
										<strong><?php echo esc_html( $pm_testi_name ); ?></strong>
										<span><?php echo esc_html( $pm_testi_role ); ?></span>
									</div>
								</div>
								<p><?php echo esc_html( $pm_testi_text ); ?></p>
							</section>
						<?php endif; ?>
					</div>
				</aside>

				<section class="pm-tp-content-column">
					<div class="pm-tp-intro">
						<div class="pm-eyebrow"><?php echo esc_html( $pm_role ); ?></div>
						<h1><?php echo esc_html( $pm_name ); ?></h1>
						<?php if ( $pm_english ) : ?>
							<div class="pm-tp-english"><?php echo esc_html( $pm_english ); ?></div>
						<?php endif; ?>
						<p class="pm-tp-bio"><?php echo wp_kses_post( nl2br( esc_html( $pm_bio ) ) ); ?></p>

						<div class="pm-tp-tags">
							<?php if ( $pm_dept ) : ?><span><?php echo pm_icon( 'users' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $pm_dept->name ); ?></span><?php endif; ?>
							<?php if ( $pm_instrument ) : ?><span><?php echo pm_icon( 'music' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $pm_instrument ); ?></span><?php endif; ?>
							<?php if ( $pm_degree ) : ?><span><?php echo pm_icon( 'education' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $pm_degree ); ?></span><?php endif; ?>
							<?php if ( $pm_experience ) : ?><span><?php echo pm_icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $pm_experience ); ?></span><?php endif; ?>
						</div>
					</div>

					<div class="pm-tp-stats">
						<?php foreach ( $pm_stats as $pm_index => $pm_stat ) : ?>
							<div class="pm-tp-stat">
								<?php echo pm_icon( $pm_stat_icons[ $pm_index % count( $pm_stat_icons ) ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<strong><?php echo esc_html( $pm_stat['value'] ); ?></strong>
								<span><?php echo esc_html( $pm_stat['label'] ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>

					<button class="pm-tp-intro-player" type="button" data-pm-video-url="<?php echo esc_url( $pm_video ); ?>" data-pm-video-title="معرفی <?php echo esc_attr( $pm_name ); ?>">
						<time><?php echo $pm_video ? '04:32' : 'به‌زودی'; ?></time>
						<span>معرفی استاد (ویدئوی اختصاصی)</span>
						<span class="pm-tp-play"><?php echo pm_icon( 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					</button>

					<?php if ( ! empty( $pm_videos ) ) : ?>
						<div class="pm-tp-section-heading">
							<div>
								<h2><?php echo pm_icon( 'education' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> موفقیت هنرجویان و مسترکلاس‌ها</h2>
								<p>نمونه‌ای از اجرای هنرجویان برتر، مسترکلاس‌ها و روند پیشرفت آموزشی</p>
							</div>
						</div>

						<div class="pm-tp-video-grid">
							<?php foreach ( $pm_videos as $pm_item ) : ?>
								<article class="pm-tp-video-card" tabindex="0" role="button" data-pm-video-url="<?php echo esc_url( $pm_item['url'] ); ?>" data-pm-video-title="<?php echo esc_attr( $pm_item['title'] ); ?>">
									<div class="pm-tp-video-thumb">
										<img src="<?php echo esc_url( $pm_item['image_url'] ? $pm_item['image_url'] : pm_img( 'masterclass.webp' ) ); ?>" alt="<?php echo esc_attr( $pm_item['title'] ); ?>" loading="lazy">
										<span class="pm-tp-mini-play"><?php echo pm_icon( 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										<?php if ( $pm_item['duration'] ) : ?>
											<time class="pm-tp-duration"><?php echo esc_html( $pm_item['duration'] ); ?></time>
										<?php endif; ?>
									</div>
									<div class="pm-tp-video-info">
										<strong><?php echo esc_html( $pm_item['title'] ); ?></strong>
										<span><?php echo $pm_item['url'] ? 'مشاهدهٔ ویدئو' : 'به‌زودی'; ?></span>
									</div>
								</article>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<section class="pm-tp-audio-card" data-pm-audio aria-labelledby="pm-audio-title">
						<div class="pm-tp-audio-top">
							<div class="pm-tp-audio-title" id="pm-audio-title">
								<?php echo pm_icon( 'music' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								نمونهٔ صوتی اجرا
							</div>
							<span class="pm-tp-audio-subtitle"><?php echo $pm_audio ? 'قطعهٔ اختصاصی استاد' : 'نمونهٔ صوتی به‌زودی'; ?></span>
						</div>
						<div class="pm-tp-audio-row">
							<button class="pm-tp-audio-button" type="button" data-pm-audio-toggle aria-label="پخش نمونهٔ صوتی" aria-pressed="false" <?php disabled( ! $pm_audio ); ?>>
								<?php echo pm_icon( 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</button>
							<div class="pm-tp-waveform" data-pm-waveform aria-hidden="true">
								<?php for ( $pm_i = 0; $pm_i < 64; $pm_i++ ) : ?>
									<?php $pm_height = round( 7 + abs( sin( $pm_i * 0.48 ) * 21 + sin( $pm_i * 0.13 ) * 8 ) ); ?>
									<i style="--h:<?php echo esc_attr( $pm_height ); ?>px"></i>
								<?php endfor; ?>
							</div>
							<span class="pm-tp-audio-time" data-pm-audio-time><?php echo $pm_audio ? '۰۰:۰۰ / ۰۰:۰۰' : 'به‌زودی'; ?></span>
						</div>
						<?php if ( $pm_audio ) : ?>
							<audio data-pm-audio-element preload="none" src="<?php echo esc_url( $pm_audio ); ?>"></audio>
						<?php endif; ?>
					</section>
				</section>

				<aside class="pm-tp-booking-column">
					<section class="pm-tp-booking-card">
						<h2 class="pm-tp-booking-title">
							رزرو مشاورهٔ تخصصی
							<?php echo pm_icon( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</h2>
						<p class="pm-tp-booking-desc">برای انتخاب مسیر آموزشی، ارزیابی سطح و برنامه‌ریزی آکادمیک با استاد مشاوره کنید.</p>
						<?php
						pm_consult_form(
							array(
								'id'            => 'pm-teacher-consult',
								'context'       => 'teacher',
								'teacher_id'    => $pm_id,
								'compact'       => true,
								'show_tabs'     => false,
								'show_benefits' => true,
								'title'         => '',
								'description'   => '',
								'button'        => 'رزرو مشاوره',
							)
						);
						?>
					</section>
				</aside>
			</div>
		</div>

		<div class="pm-tp-modal" id="pm-teacher-video-modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="pm-teacher-video-title">
			<div class="pm-tp-modal__box">
				<div class="pm-tp-modal__head">
					<strong id="pm-teacher-video-title">ویدئوی استاد</strong>
					<button class="pm-tp-modal__close" type="button" data-pm-video-close aria-label="بستن">
						<?php echo pm_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</button>
				</div>
				<div class="pm-tp-modal__body" data-pm-video-body></div>
			</div>
		</div>
	</div>
	<?php
endwhile;

get_footer();
