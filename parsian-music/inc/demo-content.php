<?php
/**
 * Demo content and homepage defaults.
 *
 * @package Parsian_Music
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Homepage feature strip.
 *
 * @return array
 */
function pm_default_features() {
	return array(
		array( 'laurel', 'با سابقهٔ تربیت', 'هنرمندان موفق' ),
		array( 'award', 'آمادگی برای', 'اجرا، آزمون و کنکور' ),
		array( 'wave', 'فضا و تجهیزات', 'استاندارد حرفه‌ای' ),
		array( 'users', 'اساتید دانشگاهی', 'و بین‌المللی' ),
		array( 'cap', 'مسیر آکادمیک', 'تا مقاطع عالی' ),
	);
}

/**
 * Homepage departments.
 *
 * @return array
 */
function pm_default_departments() {
	return array(
		array( 'ویولن و', 'سازهای زهی', 'dept-strings.jpg', 'strings' ),
		array( 'پیانو', '', 'dept-piano.jpg', 'piano' ),
		array( 'موسیقی', 'ایرانی', 'dept-persian.jpg', 'persian' ),
		array( 'آهنگسازی', 'و تئوری', 'dept-theory.jpg', 'theory' ),
		array( 'صداسازی و', 'آواز', 'dept-vocal.jpg', 'vocal' ),
		array( 'موسیقی کودک', '(متد ارف)', 'dept-kids.jpg', 'kids' ),
	);
}

/**
 * Homepage stats.
 *
 * @return array
 */
function pm_default_stats() {
	return array(
		array( '+۱۵', 'سال تجربهٔ تخصصی' ),
		array( '+۳۰۰۰', 'هنرجوی فعال' ),
		array( '+۱۲۰', 'اجرای صحنه‌ای و کنسرت' ),
		array( '%۹۰', 'رضایت هنرجویان' ),
	);
}

/**
 * Homepage FAQs.
 *
 * @return array
 */
function pm_default_faqs() {
	return array(
		array( 'آیا شروع موسیقی در بزرگسالی امکان‌پذیر است؟', 'بله. برای بزرگسالان کلاس‌ها و برنامه‌های آموزشی ویژه با سرعت و روش متناسب با شرایط شما طراحی شده است.' ),
		array( 'آیا تعیین سطح رایگان است؟', 'بله، جلسهٔ تعیین سطح و مشاورهٔ اولیه برای تمام متقاضیان کاملاً رایگان برگزار می‌شود.' ),
		array( 'برای آمادگی کنکور کارشناسی ارشد چه دوره‌هایی دارید؟', 'دوره‌های تخصصی تئوری، سلفژ، ریتم، آنالیز و اجرای تخصصی ساز با اساتید باتجربهٔ کنکور.' ),
	);
}

/**
 * Homepage testimonial.
 *
 * @return array
 */
function pm_default_testimonial() {
	return array(
		'name'   => 'مهرداد کیانی',
		'role'   => 'هنرجوی ویولن',
		'text'   => 'در مدت یک سال، از سطح مبتدی به اجرای صحنه‌ای رسیدم. فضای حرفه‌ای و حمایت اساتید بزرگ‌ترین نقطهٔ قوت آموزشگاه پارسیان است.',
		'avatar' => 'portrait-vocal.webp',
	);
}

/**
 * Homepage hero quick points.
 *
 * @return array
 */
function pm_default_hero_points() {
	return array(
		array( 'icon' => 'users', 'text' => 'اساتید دانشگاهی' ),
		array( 'icon' => 'award', 'text' => 'آمادگی کنکور' ),
		array( 'icon' => 'wave', 'text' => 'فضای استاندارد' ),
		array( 'icon' => 'cap', 'text' => 'مسیر آکادمیک' ),
	);
}

/**
 * Homepage hero numbers.
 *
 * @return array
 */
function pm_default_hero_numbers() {
	return array(
		array( 'value' => '۱۵+', 'label' => 'سال تجربه' ),
		array( 'value' => '۳۰۰۰+', 'label' => 'هنرجوی فعال' ),
		array( 'value' => '۹۰٪', 'label' => 'رضایت هنرجویان' ),
	);
}

/**
 * Default homepage section titles.
 *
 * @return array
 */
function pm_default_section_titles() {
	return array(
		'departments_title' => 'دپارتمان‌های آموزشی',
		'teachers_title'    => 'اساتید تراز اول آکادمی موسیقی',
		'progress_title'    => 'نمونه‌ای از مسیر پیشرفت هنرجویان',
		'contact_title'     => 'سوالات پرتکرار و رزرو مشاوره',
	);
}

/**
 * Default masterclass block content.
 *
 * @return array
 */
function pm_default_masterclass() {
	return array(
		'tag'      => 'FEATURED MASTERCLASS',
		'title'    => "مسترکلاس ویژه\nتفسیر و اجرای آثار کلاسیک",
		'text'     => "با حضور استاد محمدرضا کریمی\n(دکتری نوازندگی ویولن)",
		'image'    => 'conductor.jpg',
		'cta_text' => 'مشاهده و ثبت‌نام',
		'cta_url'  => '',
	);
}

/**
 * Default assistant card content.
 *
 * @return array
 */
function pm_default_assistant() {
	return array(
		'title' => "دستیار هوشمند\nپارسیان",
		'text'  => "پاسخ فوری به\nسوالات شما",
	);
}

/**
 * Normalize repeatable option rows.
 *
 * @param mixed $rows Rows.
 * @param array $keys Expected keys.
 * @return array
 */
function pm_normalize_rows( $rows, $keys ) {
	if ( ! is_array( $rows ) ) {
		return array();
	}

	$out = array();
	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$item = array();
		foreach ( $keys as $key ) {
			$item[ $key ] = isset( $row[ $key ] ) ? trim( (string) $row[ $key ] ) : '';
		}
		if ( '' === implode( '', $item ) ) {
			continue;
		}
		$out[] = $item;
	}

	return $out;
}

/**
 * Read repeatable rows from theme mods.
 *
 * @param string $key      Option key without the pm_ prefix.
 * @param array  $defaults Default rows.
 * @param array  $keys     Expected keys.
 * @return array
 */
function pm_get_rows( $key, $defaults, $keys ) {
	$saved = get_theme_mod( 'pm_' . $key, null );
	$rows  = null === $saved ? $defaults : $saved;
	$rows  = pm_normalize_rows( $rows, $keys );

	return ! empty( $rows ) ? $rows : pm_normalize_rows( $defaults, $keys );
}

/**
 * Default feature rows with named keys.
 *
 * @return array
 */
function pm_default_feature_rows() {
	$rows = array();
	foreach ( pm_default_features() as $feature ) {
		$rows[] = array(
			'icon'     => $feature[0],
			'title'    => $feature[1],
			'subtitle' => $feature[2],
		);
	}
	return $rows;
}

/**
 * Features shown on the front page.
 *
 * @return array
 */
function pm_get_features() {
	return pm_get_rows( 'features', pm_default_feature_rows(), array( 'icon', 'title', 'subtitle' ) );
}

/**
 * Default department rows with named keys.
 *
 * @return array
 */
function pm_default_department_rows() {
	$rows = array();
	foreach ( pm_default_departments() as $department ) {
		$rows[] = array(
			'title'    => $department[0],
			'subtitle' => $department[1],
			'image'    => $department[2],
			'slug'     => $department[3],
		);
	}
	return $rows;
}

/**
 * Departments shown on the front page.
 *
 * @return array
 */
function pm_get_departments() {
	return pm_get_rows( 'departments', pm_default_department_rows(), array( 'title', 'subtitle', 'image', 'slug' ) );
}

/**
 * Default stat rows with named keys.
 *
 * @return array
 */
function pm_default_stat_rows() {
	$rows = array();
	foreach ( pm_default_stats() as $stat ) {
		$rows[] = array( 'value' => $stat[0], 'label' => $stat[1] );
	}
	return $rows;
}

/**
 * Stats shown on the front page.
 *
 * @return array
 */
function pm_get_stats() {
	return pm_get_rows( 'stats', pm_default_stat_rows(), array( 'value', 'label' ) );
}

/**
 * Default FAQ rows with named keys.
 *
 * @return array
 */
function pm_default_faq_rows() {
	$rows = array();
	foreach ( pm_default_faqs() as $faq ) {
		$rows[] = array( 'question' => $faq[0], 'answer' => $faq[1] );
	}
	return $rows;
}

/**
 * FAQs shown on the front page.
 *
 * @return array
 */
function pm_get_faqs() {
	return pm_get_rows( 'faqs', pm_default_faq_rows(), array( 'question', 'answer' ) );
}

/**
 * Hero quick points.
 *
 * @return array
 */
function pm_get_hero_points() {
	return pm_get_rows( 'hero_points', pm_default_hero_points(), array( 'icon', 'text' ) );
}

/**
 * Hero number strip.
 *
 * @return array
 */
function pm_get_hero_numbers() {
	return pm_get_rows( 'hero_numbers', pm_default_hero_numbers(), array( 'value', 'label' ) );
}

/**
 * Default stats shown on a teacher profile when no custom values exist.
 *
 * @return array
 */
function pm_default_teacher_stats() {
	return array(
		array( 'value' => 'برنده جوایز', 'label' => 'بین‌المللی اجرا و آموزش' ),
		array( 'value' => '۳۵+ سال', 'label' => 'تجربهٔ آموزش و اجرا' ),
		array( 'value' => 'استاد دانشگاه', 'label' => 'و مدرس آکادمیک' ),
		array( 'value' => 'دکترای هنر', 'label' => 'اجرای ویولن' ),
	);
}

/**
 * Default achievements shown on a teacher profile.
 *
 * @return array
 */
function pm_default_teacher_achievements() {
	return array(
		array( 'value' => '+۱۲۰', 'label' => 'رتبهٔ اول جشنوارهٔ موسیقی' ),
		array( 'value' => '+۲۸', 'label' => 'قبولی در دانشگاه‌های معتبر موسیقی' ),
		array( 'value' => '+۱۵', 'label' => 'برندهٔ جشنواره‌های ملی و بین‌المللی' ),
	);
}

/**
 * Demo teachers used on first activation.
 *
 * The first item is the real director; the rest are clearly labelled demo
 * faculty members that the site owner can replace or delete.
 *
 * @return array
 */
function pm_demo_teachers() {
	$common_stats = "برنده جوایز|بین‌المللی اجرا و آموزش\n۱۰+ سال|تجربهٔ آموزش و اجرا\nاستاد دانشگاه|و مدرس آکادمیک\nعضو انجمن|موسیقی ایران";
	$common_achievements = "+۸۰|رتبهٔ اول جشنوارهٔ موسیقی\n+۲۰|قبولی در دانشگاه‌های معتبر موسیقی\n+۱۰|برندهٔ جشنواره‌های ملی";
	$videos = "اجرای دونوازی استاد و هنرجو|12:19||portrait-persian.webp\nاجرای هنرجوی برتر|07:38||student.jpg\nمسترکلاس تفسیر سونات باخ|14:26||portrait-theory.webp";

	return array(
		array(
			'title'          => 'استاد محمدرضا کریمی',
			'slug'           => 'mohammad-reza-karimi',
			'english_name'   => 'Mohammad Reza Karimi',
			'role'           => 'مدیر مسئول و استاد ویولن',
			'department'     => 'strings',
			'degree'         => 'DMA',
			'experience'     => '۳۵+ سال',
			'portrait'       => 'teacher-karimi.webp',
			'bio'            => 'نوازندهٔ بین‌المللی ویولن، پژوهشگر موسیقی کلاسیک و مدیر مسئول آکادمی موسیقی پارسیان با بیش از ۳۵ سال تجربه در آموزش حرفه‌ای، تربیت نوازندگان ممتاز و اجرای کنسرت در ایران و اروپا.',
			'presence_days'  => 'یکشنبه، سه‌شنبه، پنجشنبه',
			'stats'          => "برنده جوایز|بین‌المللی اجرا و آموزش\n۳۵+ سال|تجربهٔ آموزش و اجرا\nاستاد دانشگاه|و مدرس آکادمیک\nدکترای هنر|اجرای ویولن",
			'achievements'   => $common_achievements,
			'videos'         => $videos,
			'testimonial_name'   => 'مهرداد کیانی',
			'testimonial_role'   => 'هنرجوی ویولن',
			'testimonial_text'   => 'در مدت یک سال، از سطح مبتدی به اجرای صحنه‌ای رسیدم. فضای حرفه‌ای و حمایت اساتید بزرگ‌ترین نقطهٔ قوت آموزشگاه پارسیان است.',
			'testimonial_avatar' => 'portrait-vocal.webp',
			'before_image'   => 'student.jpg',
			'after_image'    => 'hero.jpg',
		),
		array(
			'title'          => 'استاد پیانو',
			'slug'           => 'piano-faculty',
			'english_name'   => 'Piano Faculty',
			'role'           => 'استاد پیانو',
			'department'     => 'piano',
			'degree'         => 'PhD',
			'experience'     => '۱۵+ سال',
			'portrait'       => 'conductor.jpg',
			'bio'            => 'مدرس پیانو با تمرکز بر تکنیک، تفسیر و اجرای صحنه‌ای؛ همراه هنرجویان در مسیر آمادگی برای آزمون‌های هنری و کنکور موسیقی.',
			'presence_days'  => 'شنبه، دوشنبه، چهارشنبه',
			'stats'          => $common_stats,
			'achievements'   => $common_achievements,
			'videos'         => $videos,
		),
		array(
			'title'          => 'استاد موسیقی ایرانی',
			'slug'           => 'persian-music-faculty',
			'english_name'   => 'Persian Music Faculty',
			'role'           => 'استاد موسیقی ایرانی',
			'department'     => 'persian',
			'degree'         => 'MA',
			'experience'     => '۲۰+ سال',
			'portrait'       => 'portrait-persian.webp',
			'bio'            => 'مدرس تار و سه‌تار و پژوهشگر موسیقی ایرانی؛ آموزش ردیف، بداهه‌نوازی و اجرای گروهی با نگاهی آکادمیک به موسیقی سنتی.',
			'presence_days'  => 'یکشنبه، سه‌شنبه، پنجشنبه',
			'stats'          => $common_stats,
			'achievements'   => $common_achievements,
			'videos'         => $videos,
		),
		array(
			'title'          => 'استاد آواز',
			'slug'           => 'vocal-faculty',
			'english_name'   => 'Vocal Faculty',
			'role'           => 'استاد آواز و صداسازی',
			'department'     => 'vocal',
			'degree'         => 'MA',
			'experience'     => '۱۲+ سال',
			'portrait'       => 'student.jpg',
			'bio'            => 'مدرس آواز و صداسازی با رویکرد علمی به تنفس، رزونانس و بیان هنری؛ همراه هنرجویان در اجرای صحنه‌ای و ضبط حرفه‌ای.',
			'presence_days'  => 'شنبه، دوشنبه، چهارشنبه',
			'stats'          => $common_stats,
			'achievements'   => $common_achievements,
			'videos'         => $videos,
		),
		array(
			'title'          => 'استاد موسیقی کودک',
			'slug'           => 'kids-music-faculty',
			'english_name'   => 'Kids Music Faculty',
			'role'           => 'استاد موسیقی کودک',
			'department'     => 'kids',
			'degree'         => 'MA',
			'experience'     => '۱۰+ سال',
			'portrait'       => 'dept-kids.jpg',
			'bio'            => 'مدرس موسیقی کودک با متد ارف؛ طراحی بازی‌های موسیقایی، رشد شنیداری و ساختن نخستین تجربه‌های اجرا برای کودکان.',
			'presence_days'  => 'یکشنبه، سه‌شنبه، پنجشنبه',
			'stats'          => $common_stats,
			'achievements'   => $common_achievements,
			'videos'         => $videos,
		),
		array(
			'title'          => 'استاد آهنگسازی',
			'slug'           => 'composition-faculty',
			'english_name'   => 'Composition Faculty',
			'role'           => 'استاد آهنگسازی و تئوری',
			'department'     => 'theory',
			'degree'         => 'PhD',
			'experience'     => '۱۸+ سال',
			'portrait'       => 'hero.jpg',
			'bio'            => 'مدرس آهنگسازی، هارمونی و فرم؛ آماده‌سازی هنرجویان برای کنکور کارشناسی ارشد و پرورش زبان شخصی در آهنگسازی.',
			'presence_days'  => 'شنبه، دوشنبه، چهارشنبه',
			'stats'          => $common_stats,
			'achievements'   => $common_achievements,
			'videos'         => $videos,
		),
	);
}

/**
 * Create department terms.
 */
function pm_create_department_terms() {
	$terms = array(
		'strings' => 'سازهای زهی',
		'piano'   => 'پیانو',
		'persian' => 'موسیقی ایرانی',
		'theory'  => 'آهنگسازی و تئوری',
		'vocal'   => 'آواز',
		'kids'    => 'موسیقی کودک',
	);

	foreach ( $terms as $slug => $name ) {
		if ( ! term_exists( $slug, 'pm_department' ) ) {
			wp_insert_term( $name, 'pm_department', array( 'slug' => $slug ) );
		}
	}
}

/**
 * Seed the demo teachers once after theme activation.
 */
function pm_seed_demo_content() {
	if ( get_option( 'pm_demo_seeded' ) ) {
		return;
	}

	pm_create_department_terms();

	$existing = get_posts(
		array(
			'post_type'      => 'pm_teacher',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
		)
	);

	if ( empty( $existing ) ) {
		foreach ( pm_demo_teachers() as $index => $teacher ) {
			$post_id = wp_insert_post(
				array(
					'post_type'    => 'pm_teacher',
					'post_status'  => 'publish',
					'post_title'   => $teacher['title'],
					'post_name'    => $teacher['slug'],
					'post_content' => $teacher['bio'],
					'post_excerpt' => wp_trim_words( $teacher['bio'], 26 ),
					'menu_order'   => $index,
				)
			);

			if ( ! $post_id || is_wp_error( $post_id ) ) {
				continue;
			}

			$meta = array(
				'english_name'      => $teacher['english_name'],
				'role'              => $teacher['role'],
				'degree'            => $teacher['degree'],
				'experience'        => $teacher['experience'],
				'portrait'          => $teacher['portrait'],
				'bio'               => $teacher['bio'],
				'presence_days'     => $teacher['presence_days'],
				'stats'             => $teacher['stats'],
				'achievements'      => $teacher['achievements'],
				'videos'            => $teacher['videos'],
				'testimonial_name'  => isset( $teacher['testimonial_name'] ) ? $teacher['testimonial_name'] : '',
				'testimonial_role'  => isset( $teacher['testimonial_role'] ) ? $teacher['testimonial_role'] : '',
				'testimonial_text'  => isset( $teacher['testimonial_text'] ) ? $teacher['testimonial_text'] : '',
				'testimonial_avatar' => isset( $teacher['testimonial_avatar'] ) ? $teacher['testimonial_avatar'] : '',
				'before_image'      => isset( $teacher['before_image'] ) ? $teacher['before_image'] : 'student.jpg',
				'after_image'       => isset( $teacher['after_image'] ) ? $teacher['after_image'] : 'hero.jpg',
			);

			foreach ( $meta as $key => $value ) {
				if ( '' !== $value ) {
					update_post_meta( $post_id, '_pm_teacher_' . $key, $value );
				}
			}

			wp_set_object_terms( $post_id, $teacher['department'], 'pm_department' );
		}
	}

	update_option( 'pm_demo_seeded', 1 );
	flush_rewrite_rules();
	set_transient( 'pm_demo_seeded_notice', 1, 60 );
}
add_action( 'after_switch_theme', 'pm_seed_demo_content' );

/**
 * Tell the administrator that demo content was created.
 */
function pm_demo_content_notice() {
	if ( ! get_transient( 'pm_demo_seeded_notice' ) || ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	delete_transient( 'pm_demo_seeded_notice' );
	$url = admin_url( 'edit.php?post_type=pm_teacher' );
	echo '<div class="notice notice-info is-dismissible"><p>محتوای نمایشی اساتید ایجاد شد. می‌توانید از <a href="' . esc_url( $url ) . '">بخش اساتید</a> نام‌ها، تصاویر و متن‌ها را با اطلاعات واقعی جایگزین کنید.</p></div>';
}
add_action( 'admin_notices', 'pm_demo_content_notice' );
