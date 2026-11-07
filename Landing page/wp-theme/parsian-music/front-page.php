<?php
/**
 * Front page – Parsian Music Conservatory
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
get_header();

$features = array(
	array( 'wave-laurel', 'با سابقه تربیت<br>هنرمندان موفق' ),
	array( 'award', 'آمادگی برای<br>اجرا، آزمون و کنکور' ),
	array( 'wave', 'فضا و تجهیزات<br>استاندارد حرفه‌ای' ),
	array( 'users', 'اساتید دانشگاهی<br>و بین‌المللی' ),
	array( 'cap', 'مسیر آکادمیک<br>تا مقاطع عالی' ),
);

$depts = array(
	array( 'ویولن و', 'سازهای زهی', 'dept-strings.jpg' ),
	array( 'پیانو', '', 'dept-piano.jpg' ),
	array( 'موسیقی', 'ایرانی', 'dept-persian.jpg' ),
	array( 'آهنگسازی', 'و تئوری', 'dept-theory.jpg' ),
	array( 'صداسازی و', 'آواز', 'dept-vocal.jpg' ),
	array( 'موسیقی کودک', '(متد ارف)', 'dept-kids.jpg' ),
);

$stats = array(
	array( '+۱۵', 'سال تجربه تخصصی' ),
	array( '+۳۰۰۰', 'هنرجوی فعال' ),
	array( '+۱۲۰', 'اجرای صحنه‌ای و کنسرت' ),
	array( '%۹۰', 'رضایت هنرجویان' ),
);

$filters = array(
	'all'     => 'همه',
	'strings' => 'سازهای زهی',
	'piano'   => 'پیانو',
	'persian' => 'موسیقی ایرانی',
	'theory'  => 'تئوری و ارشد',
	'vocal'   => 'آواز',
	'kids'    => 'موسیقی کودک',
);

$teachers = array(
	array( 'دکتر آرش شایگان', 'ویولن', 'PhD', 'strings', 'conductor.jpg' ),
	array( 'استاد پیمان آزاد', 'موسیقی ایرانی', 'DMA', 'persian', 'teacher-3.jpg' ),
	array( 'دکتر لیلا فرهمند', 'پیانو', 'PhD', 'piano', 'teacher-2.jpg' ),
	array( 'دکتر مهدی رستگار', 'آهنگسازی', 'PhD', 'theory', 'teacher-1.jpg' ),
	array( 'دکتر سیاوش نادری', 'آهنگسازی', 'MA', 'theory', 'teacher-7.jpg' ),
	array( 'استاد نازنین کمالی', 'موسیقی کودک', 'MA', 'kids', 'teacher-4.jpg' ),
);

$faqs = array(
	array( 'آیا شروع موسیقی در بزرگسالی امکان‌پذیر است؟', 'بله. برای بزرگسالان کلاس‌ها و برنامه‌های آموزشی ویژه با سرعت و روش متناسب با شرایط شما طراحی شده است.' ),
	array( 'آیا تعیین سطح رایگان است؟', 'بله، جلسه تعیین سطح و مشاوره اولیه برای تمام متقاضیان کاملاً رایگان برگزار می‌شود.' ),
	array( 'برای آمادگی کنکور کارشناسی ارشد چه دوره‌هایی دارید؟', 'دوره‌های تخصصی تئوری، سلفژ، ریتم، آنالیز و اجرای تخصصی ساز با اساتید باتجربه کنکور.' ),
);
?>

<main>
	<!-- Hero -->
	<section class="pm-hero">
		<img class="pm-hero__bg" src="<?php echo pm_img( 'hero.jpg' ); // phpcs:ignore ?>" alt="نوازنده ویولن‌سل">
		<div class="pm-hero__shade"></div>
		<div class="pm-container pm-hero__in">
			<div class="pm-hero__content">
				<div class="pm-eyebrow">آموزش آکادمیک موسیقی با رویکرد بین‌المللی</div>
				<h1><span>مسیر حرفه‌ای</span><span><b class="pm-gold">موسیقی</b> از اینجا آغاز می‌شود</span></h1>
				<p>در کنسرواتوار موسیقی پارسیان، با اساتید برجسته، برنامه‌های آموزشی استاندارد و فضای تخصصی، استعداد خود را به اجرای حرفه‌ای تبدیل کنید.</p>
				<div class="pm-hero__btns">
					<a class="pm-btn pm-btn--gold" href="#contact">شروع مسیر هنری من <?php echo pm_icon( 'arrow-left' ); // phpcs:ignore ?></a>
					<a class="pm-btn pm-btn--ghost" href="#masterclass"><?php echo pm_icon( 'play-circle' ); // phpcs:ignore ?> معرفی کنسرواتوار</a>
				</div>
			</div>
		</div>
	</section>

	<!-- Features -->
	<section class="pm-features">
		<div class="pm-container">
			<ul class="pm-features__list">
				<?php foreach ( $features as $f ) : ?>
					<li><?php echo pm_icon( 'wave-laurel' === $f[0] ? 'laurel' : $f[0] ); // phpcs:ignore ?><span><?php echo wp_kses( $f[1], array( 'br' => array() ) ); ?></span></li>
				<?php endforeach; ?>
			</ul>
		</div>
	</section>

	<!-- Departments -->
	<section class="pm-section" id="courses">
		<div class="pm-container">
			<div class="pm-title"><h2>دپارتمان‌های آموزشی</h2></div>
			<div class="pm-depts">
				<?php foreach ( $depts as $d ) : ?>
					<a class="pm-dept" href="#">
						<img src="<?php echo pm_img( $d[2] ); // phpcs:ignore ?>" alt="<?php echo esc_attr( trim( $d[0] . ' ' . $d[1] ) ); ?>" loading="lazy">
						<span class="pm-dept__t"><?php echo esc_html( $d[0] ); ?><?php if ( $d[1] ) : ?><small><?php echo esc_html( $d[1] ); ?></small><?php endif; ?></span>
						<?php echo pm_icon( 'arrow-left' ); // phpcs:ignore ?>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<!-- Masterclass + hall slider -->
	<section class="pm-section" id="masterclass">
		<div class="pm-container pm-feature-row">
			<div class="pm-slider" data-slider>
				<div class="pm-slider__slide is-on"><img src="<?php echo pm_img( 'hall.jpg' ); // phpcs:ignore ?>" alt="سالن اجرا"></div>
				<div class="pm-slider__slide"><img src="<?php echo pm_img( 'hero.jpg' ); // phpcs:ignore ?>" alt="کنسرواتوار پارسیان"></div>
				<div class="pm-dots"><button class="is-on" aria-label="1"></button><button aria-label="2"></button></div>
				<div class="pm-slider__nav">
					<button class="pm-circle-btn" data-prev aria-label="قبلی"><?php echo pm_icon( 'chev-right' ); // phpcs:ignore ?></button>
					<button class="pm-circle-btn" data-next aria-label="بعدی"><?php echo pm_icon( 'chev-left' ); // phpcs:ignore ?></button>
				</div>
			</div>

			<div class="pm-master">
				<img src="<?php echo pm_img( 'conductor.jpg' ); // phpcs:ignore ?>" alt="رهبر ارکستر">
				<div class="pm-master__body">
					<div class="pm-master__tag">FEATURED MASTERCLASS</div>
					<h3>مستر کلاس ویژه<br>تفسیر و اجرای آثار کلاسیک</h3>
					<p>با حضور دکتر آرش شایگان<br>(دکتری نوارندگی ویولن)</p>
					<a class="pm-btn pm-btn--gold" href="#contact">مشاهده و ثبت‌نام <?php echo pm_icon( 'arrow-left' ); // phpcs:ignore ?></a>
				</div>
				<button class="pm-master__play" aria-label="پخش"><?php echo pm_icon( 'play' ); // phpcs:ignore ?></button>
			</div>
		</div>
	</section>

	<!-- Stats -->
	<section class="pm-section" id="stats">
		<div class="pm-container">
			<div class="pm-stats">
				<?php echo pm_icon( 'laurel', 'pm-laurel pm-laurel--r' ); // phpcs:ignore ?>
				<?php foreach ( $stats as $s ) : ?>
					<div><strong dir="ltr"><?php echo esc_html( $s[0] ); ?></strong><span><?php echo esc_html( $s[1] ); ?></span></div>
				<?php endforeach; ?>
				<?php echo pm_icon( 'laurel', 'pm-laurel pm-laurel--l' ); // phpcs:ignore ?>
			</div>
		</div>
	</section>

	<!-- Teachers -->
	<section class="pm-section" id="teachers">
		<div class="pm-container">
			<div class="pm-title"><h2>اساتید برجسته کنسرواتوار</h2></div>
			<div class="pm-chips">
				<?php foreach ( $filters as $key => $label ) : ?>
					<button class="pm-chip<?php echo 'all' === $key ? ' is-on' : ''; ?>" data-filter="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></button>
				<?php endforeach; ?>
			</div>
			<div class="pm-teachers">
				<?php foreach ( $teachers as $t ) : ?>
					<article class="pm-teacher" data-cat="<?php echo esc_attr( $t[3] ); ?>">
						<img src="<?php echo pm_img( $t[4] ); // phpcs:ignore ?>" alt="<?php echo esc_attr( $t[0] ); ?>" loading="lazy">
						<div class="pm-teacher__body">
							<h3><?php echo esc_html( $t[0] ); ?></h3>
							<div class="pm-teacher__row">
								<span><?php echo esc_html( $t[1] ); ?> <bdi><?php echo esc_html( $t[2] ); ?></bdi></span>
								<button class="pm-circle-btn" aria-label="پخش"><?php echo pm_icon( 'play' ); // phpcs:ignore ?></button>
							</div>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<!-- Progress -->
	<section class="pm-section">
		<div class="pm-container">
			<div class="pm-title"><h2>نمونه‌ای از مسیر پیشرفت هنرجویان</h2></div>
			<div class="pm-progress">
				<div class="pm-ba">
					<div class="pm-ba__half pm-ba__half--after"><img src="<?php echo pm_img( 'student.jpg' ); // phpcs:ignore ?>" alt="پس از یک سال آموزش"><span class="pm-ba__label">پس از یک سال آموزش</span></div>
					<div class="pm-ba__half pm-ba__half--before"><img src="<?php echo pm_img( 'student.jpg' ); // phpcs:ignore ?>" alt="قبل از آموزش"><span class="pm-ba__label">قبل از آموزش</span></div>
					<button class="pm-circle-btn pm-ba__center" aria-label="پخش"><?php echo pm_icon( 'play' ); // phpcs:ignore ?></button>
					<button class="pm-circle-btn pm-ba__prev" aria-label="قبلی"><?php echo pm_icon( 'chev-right' ); // phpcs:ignore ?></button>
					<button class="pm-circle-btn pm-ba__next" aria-label="بعدی"><?php echo pm_icon( 'chev-left' ); // phpcs:ignore ?></button>
				</div>

				<div class="pm-testi">
					<span class="pm-testi__q">”</span>
					<div class="pm-testi__head">
						<img class="pm-testi__avatar" src="<?php echo pm_img( 'teacher-5.jpg' ); // phpcs:ignore ?>" alt="مهرداد کیانی">
						<div>
							<div class="pm-testi__name">مهرداد کیانی</div>
							<div class="pm-testi__role">هنرجوی ویولن</div>
							<div class="pm-stars"><?php for ( $i = 0; $i < 5; $i++ ) { echo pm_icon( 'star' ); } // phpcs:ignore ?></div>
						</div>
					</div>
					<p>در مدت یک سال، از سطح مبتدی به اجرای صحنه‌ای رسیدم. فضای حرفه‌ای و حمایت اساتید بزرگ‌ترین نقطه قوت پارسیان است.</p>
				</div>
			</div>
		</div>
	</section>

	<!-- Booking / FAQ -->
	<section class="pm-section" id="contact">
		<div class="pm-container">
			<div class="pm-book" id="faq">
				<div class="pm-book__col pm-faq">
					<h3><?php echo pm_icon( 'help' ); // phpcs:ignore ?> سوالات پرتکرار</h3>
					<?php foreach ( $faqs as $q ) : ?>
						<details>
							<summary><?php echo pm_icon( 'chev-down' ); // phpcs:ignore ?><span><?php echo esc_html( $q[0] ); ?></span></summary>
							<p><?php echo esc_html( $q[1] ); ?></p>
						</details>
					<?php endforeach; ?>
				</div>

				<form class="pm-book__col pm-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<h3>رزرو مشاوره و تعیین سطح</h3>
					<?php if ( isset( $_GET['pm_sent'] ) ) : // phpcs:ignore ?>
						<div class="pm-notice">درخواست شما ثبت شد. به زودی با شما تماس می‌گیریم.</div>
					<?php endif; ?>
					<div class="pm-tabs">
						<button type="button" class="pm-tab is-on" data-type="inperson"><?php echo pm_icon( 'radio' ); // phpcs:ignore ?> مشاوره حضوری</button>
						<button type="button" class="pm-tab" data-type="phone">مشاوره تلفنی</button>
					</div>
					<input type="hidden" name="action" value="pm_consult">
					<input type="hidden" name="pm_type" id="pm-type" value="inperson">
					<?php wp_nonce_field( 'pm_consult', 'pm_nonce' ); ?>
					<div class="pm-field"><?php echo pm_icon( 'user' ); // phpcs:ignore ?><input type="text" name="pm_name" placeholder="نام و نام خانوادگی" required></div>
					<div class="pm-field"><?php echo pm_icon( 'mobile' ); // phpcs:ignore ?><input type="tel" name="pm_phone" placeholder="شماره موبایل" required></div>
					<div class="pm-field">
						<?php echo pm_icon( 'music' ); // phpcs:ignore ?>
						<select name="pm_instrument" required>
							<option value="">ساز مورد علاقه</option>
							<option>ویولن</option><option>ویولن‌سل</option><option>پیانو</option><option>گیتار</option>
							<option>تار و سه‌تار</option><option>سنتور</option><option>آواز</option><option>موسیقی کودک</option>
						</select>
						<?php echo pm_icon( 'chev-down', 'pm-field__chev' ); // phpcs:ignore ?>
					</div>
					<button type="submit" class="pm-btn pm-btn--gold"><?php echo pm_icon( 'calendar' ); // phpcs:ignore ?> ثبت درخواست مشاوره و تعیین سطح</button>
				</form>

				<div class="pm-book__col">
					<div class="pm-assist">
						<div class="pm-assist__t">
							<h4>دستیار هوشمند<br>پارسیان</h4>
							<p>پاسخ فوری به<br>سوالات شما</p>
							<a class="pm-circle-btn" href="#contact" aria-label="شروع گفتگو"><?php echo pm_icon( 'arrow-left' ); // phpcs:ignore ?></a>
						</div>
						<div class="pm-assist__orb"><?php echo pm_icon( 'wave' ); // phpcs:ignore ?></div>
					</div>
				</div>
			</div>
		</div>
	</section>
</main>

<?php
get_footer();
