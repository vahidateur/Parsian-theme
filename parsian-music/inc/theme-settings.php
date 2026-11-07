<?php
/**
 * Parsian theme settings panel.
 *
 * @package Parsian_Music
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings page URL.
 *
 * @param array $args Query args.
 * @return string
 */
function pm_settings_url( $args = array() ) {
	return add_query_arg( array_merge( array( 'page' => 'pm-settings' ), $args ), admin_url( 'admin.php' ) );
}

/**
 * Default values for every theme setting.
 *
 * @return array
 */
function pm_settings_defaults() {
	$titles      = pm_default_section_titles();
	$testimonial = pm_default_testimonial();
	$masterclass = pm_default_masterclass();

	$settings = array(
		'brand_name'             => 'آکادمی موسیقی پارسیان',
		'brand_subtitle'         => 'Parsian Music Academy',
		'phone'                  => '۰۲۱-۱۲۳۴۵۶۷۸',
		'email'                  => 'info@parsianmusic.ir',
		'address'                => 'تهران، خیابان ولیعصر، پلاک ۱',
		'map_url'                => '',
		'map_lat'                => '',
		'map_lng'                => '',
		'map_label'              => 'مسیریابی تا آکادمی',
		'booking_email'          => '',
		'instagram'              => '',
		'youtube'                => '',
		'telegram'               => '',
		'hero_eyebrow'           => 'آموزش آکادمیک موسیقی با رویکرد بین‌المللی',
		'hero_title'             => "مسیر حرفه‌ای\n<b class=\"pm-gold\">موسیقی</b> از اینجا آغاز می‌شود",
		'hero_text'              => 'در آکادمی موسیقی پارسیان، با اساتید برجسته، برنامه‌های آموزشی استاندارد و فضای تخصصی، استعداد خود را به اجرای حرفه‌ای تبدیل کنید.',
		'hero_badge'             => 'پذیرش هنرجوی جدید',
		'hero_cta_text'          => 'شروع مسیر هنری من',
		'hero_cta_url'           => '#contact',
		'hero_secondary_text'    => 'معرفی آموزشگاه',
		'hero_secondary_url'     => '#masterclass',
		'hero_image'             => '',
		'hero_points'            => pm_default_hero_points(),
		'hero_numbers'           => pm_default_hero_numbers(),
		'departments_title'      => $titles['departments_title'],
		'teachers_title'         => $titles['teachers_title'],
		'progress_title'         => $titles['progress_title'],
		'contact_title'          => $titles['contact_title'],
		'consult_title'          => 'رزرو مشاوره و تعیین سطح',
		'consult_desc'           => 'فرم را تکمیل کنید؛ کارشناسان آکادمی برای هماهنگی جلسهٔ مشاوره با شما تماس می‌گیرند.',
		'consult_button'         => 'ثبت درخواست مشاوره و تعیین سطح',
		'consult_name_label'     => 'نام و نام خانوادگی',
		'consult_name_placeholder' => 'نام و نام خانوادگی',
		'consult_name_required'  => '1',
		'consult_phone_label'    => 'شمارهٔ موبایل',
		'consult_phone_placeholder' => 'شمارهٔ موبایل',
		'consult_phone_required' => '1',
		'consult_instrument_label' => 'ساز مورد علاقه',
		'consult_instrument_required' => '1',
		'consult_instruments'    => "ویولن\nویولن‌سل\nپیانو\nگیتار\nتار و سه‌تار\nسنتور\nآواز\nموسیقی کودک\nآهنگسازی\nسایر",
		'consult_topic_label'    => 'موضوع مشاوره',
		'consult_topics'         => "level|تعیین سطح و مشاوره\ncareer|مسیر حرفه‌ای و دانشگاه\ninfo|اطلاعات دوره‌ها و ثبت‌نام",
		'consult_message_label'  => 'توضیحات',
		'consult_message_placeholder' => 'توضیحات (اختیاری)',
		'consult_message_enabled' => '1',
		'consult_phone_tab_enabled' => '1',
		'consult_phone_number'   => '',
		'consult_phone_note'     => 'برای رزرو مشاورهٔ تلفنی با آکادمی تماس بگیرید.',
		'consult_phone_hours'    => 'شنبه تا پنجشنبه، ۱۰ تا ۲۰',
		'consult_retention_days' => '365',
		'features'               => pm_default_feature_rows(),
		'departments'            => pm_default_department_rows(),
		'stats'                  => pm_default_stat_rows(),
		'masterclass_tag'        => $masterclass['tag'],
		'masterclass_title'      => $masterclass['title'],
		'masterclass_text'       => $masterclass['text'],
		'masterclass_image'      => $masterclass['image'],
		'masterclass_cta_text'   => $masterclass['cta_text'],
		'masterclass_cta_url'    => '',
		'progress_before_image'  => 'student.jpg',
		'progress_after_image'   => 'hero.jpg',
		'testimonial_name'       => $testimonial['name'],
		'testimonial_role'       => $testimonial['role'],
		'testimonial_text'       => $testimonial['text'],
		'testimonial_avatar'     => $testimonial['avatar'],
		'faqs'                   => pm_default_faq_rows(),
		'teachers_archive_intro' => 'با اساتید برجستهٔ آکادمی موسیقی پارسیان آشنا شوید؛ از ویولن و پیانو تا موسیقی ایرانی، آواز و موسیقی کودک.',
		'footer_text'            => 'آکادمی موسیقی پارسیان؛ مسیر حرفه‌ای موسیقی از اینجا آغاز می‌شود.',
		'footer_credit'          => 'طراحی مسیر آموزشی با مشاورهٔ رایگان',
		'login_bg'               => '',
		'login_message'          => 'برای مدیریت محتوا، اساتید و تنظیمات قالب وارد شوید.',
		'dashboard_title'        => 'به پنل مدیریت پارسیان خوش آمدید',
		'dashboard_text'         => 'از اینجا محتوا، اساتید، درخواست‌های مشاوره و ظاهر سایت را مدیریت کنید.',
		'dashboard_notice'       => '',
	);

	return array_merge( $settings, pm_ai_defaults(), pm_notify_defaults() );
}

/**
 * Icon choices used by the settings panel.
 *
 * @return array
 */
function pm_settings_icon_options() {
	return array(
		'laurel'    => 'برگ افتخار',
		'award'     => 'نشان و جایزه',
		'wave'      => 'موج موسیقی',
		'users'     => 'اساتید و هنرجویان',
		'cap'       => 'مسیر آکادمیک',
		'star'      => 'ستاره',
		'music'     => 'ساز موسیقی',
		'trophy'    => 'جام',
		'building'  => 'ساختمان',
		'education' => 'آموزش',
		'target'    => 'هدف',
		'level'     => 'سطح',
		'headset'   => 'پشتیبانی',
		'clock'     => 'زمان',
		'mail'      => 'ایمیل',
		'phone'     => 'تلفن',
		'location'  => 'موقعیت',
		'check'     => 'تیک',
		'sparkles'  => 'درخشش',
		'calendar'  => 'تقویم',
		'chart'     => 'نمودار',
		'road'      => 'مسیر',
		'quote'     => 'نقل قول',
		'edit'      => 'ویرایش',
		'help'      => 'راهنما',
		'send'      => 'ارسال',
	);
}

/**
 * Settings schema.
 *
 * @return array
 */
function pm_settings_schema() {
	$icons = pm_settings_icon_options();
	$icon  = array(
		'label'   => 'آیکون',
		'type'    => 'icon',
		'options' => $icons,
	);

	return array(
		'brand'   => array(
			'label'    => 'برند و تماس',
			'icon'     => 'building',
			'desc'     => 'هویت برند، اطلاعات تماس و شبکه‌های اجتماعی.',
			'sections' => array(
				array(
					'title'  => 'هویت برند',
					'desc'   => 'نام و زیرعنوان در هدر، فوتر و صفحهٔ ورود نمایش داده می‌شوند.',
					'fields' => array(
						'brand_name'     => array(
							'label' => 'نام برند',
							'type'  => 'text',
						),
						'brand_subtitle' => array(
							'label' => 'زیرعنوان لاتین',
							'type'  => 'text',
						),
					),
				),
				array(
					'title'  => 'اطلاعات تماس',
					'desc'   => 'این اطلاعات در فوتر و فرم‌های مشاوره استفاده می‌شود.',
					'fields' => array(
						'phone'         => array(
							'label' => 'تلفن آموزشگاه',
							'type'  => 'text',
						),
						'email'         => array(
							'label' => 'ایمیل عمومی',
							'type'  => 'email',
						),
						'address'       => array(
							'label' => 'آدرس',
							'type'  => 'textarea',
						),
						'booking_email' => array(
							'label' => 'ایمیل دریافت درخواست‌ها',
							'type'  => 'email',
							'desc'  => 'اگر خالی باشد، ایمیل مدیر وردپرس استفاده می‌شود.',
						),
						'map_url'       => array(
							'label' => 'لینک دلخواه نقشه',
							'type'  => 'url',
							'desc'  => 'اگر لینک آمادهٔ نشان یا گوگل‌مپ دارید، اینجا بگذارید. خالی بماند، از مختصات یا آدرس ساخته می‌شود.',
						),
						'map_lat'       => array(
							'label' => 'عرض جغرافیایی (lat)',
							'type'  => 'text',
							'desc'  => 'برای ساخت لینک نشان؛ مثال: 35.7077402',
						),
						'map_lng'       => array(
							'label' => 'طول جغرافیایی (lng)',
							'type'  => 'text',
							'desc'  => 'برای ساخت لینک نشان؛ مثال: 51.1828453',
						),
						'map_label'     => array(
							'label' => 'متن دکمهٔ مسیریابی',
							'type'  => 'text',
						),
					),
				),
				array(
					'title'  => 'شبکه‌های اجتماعی',
					'desc'   => 'لینک‌های خالی نمایش داده نمی‌شوند.',
					'fields' => array(
						'instagram' => array(
							'label' => 'اینستاگرام',
							'type'  => 'url',
						),
						'youtube'   => array(
							'label' => 'یوتیوب',
							'type'  => 'url',
						),
						'telegram'  => array(
							'label' => 'تلگرام',
							'type'  => 'url',
						),
					),
				),
			),
		),
		'home'    => array(
			'label'    => 'صفحه اصلی',
			'icon'     => 'wave',
			'desc'     => 'هیرو، بخش‌های صفحه اصلی، آمار، سوالات متداول و نظرات.',
			'sections' => array(
				array(
					'title'  => 'بخش هیرو',
					'desc'   => 'پیام اصلی بالای صفحه و دکمه‌های اقدام.',
					'fields' => array(
						'hero_eyebrow'        => array(
							'label' => 'متن بالای عنوان',
							'type'  => 'text',
						),
						'hero_title'          => array(
							'label' => 'عنوان اصلی',
							'type'  => 'hero_title',
							'desc'  => 'برای خط جدید Enter بزنید. متن داخل <b class="pm-gold">…</b> طلایی نمایش داده می‌شود.',
						),
						'hero_text'           => array(
							'label' => 'متن معرفی',
							'type'  => 'textarea',
						),
						'hero_badge'          => array(
							'label' => 'برچسب روی تصویر',
							'type'  => 'text',
						),
						'hero_cta_text'       => array(
							'label' => 'متن دکمهٔ اصلی',
							'type'  => 'text',
						),
						'hero_cta_url'        => array(
							'label' => 'لینک دکمهٔ اصلی',
							'type'  => 'url',
						),
						'hero_secondary_text' => array(
							'label' => 'متن دکمهٔ دوم',
							'type'  => 'text',
						),
						'hero_secondary_url'  => array(
							'label' => 'لینک دکمهٔ دوم',
							'type'  => 'url',
						),
						'hero_image'          => array(
							'label' => 'تصویر هیرو',
							'type'  => 'image',
							'desc'  => 'اگر خالی باشد، تصویر پیش‌فرض پوسته استفاده می‌شود.',
						),
					),
				),
				array(
					'title'  => 'نوارهای هیرو',
					'desc'   => 'نکات کوتاه و اعداد اعتمادساز کنار هیرو.',
					'fields' => array(
						'hero_points'  => array(
							'label'     => 'نکات کوتاه',
							'type'      => 'rows',
							'row_label' => 'نکته',
							'fields'    => array(
								'icon' => $icon,
								'text' => array(
									'label' => 'متن',
									'type'  => 'text',
								),
							),
						),
						'hero_numbers' => array(
							'label'     => 'اعداد هیرو',
							'type'      => 'rows',
							'row_label' => 'عدد',
							'fields'    => array(
								'value' => array(
									'label' => 'عدد',
									'type'  => 'text',
								),
								'label' => array(
									'label' => 'برچسب',
									'type'  => 'text',
								),
							),
						),
					),
				),
				array(
					'title'  => 'عنوان بخش‌ها',
					'desc'   => 'عنوان‌های اصلی بخش‌های صفحه اول.',
					'fields' => array(
						'departments_title' => array(
							'label' => 'عنوان دپارتمان‌ها',
							'type'  => 'text',
						),
						'teachers_title'    => array(
							'label' => 'عنوان اساتید',
							'type'  => 'text',
						),
						'progress_title'    => array(
							'label' => 'عنوان پیشرفت هنرجویان',
							'type'  => 'text',
						),
						'contact_title'     => array(
							'label' => 'عنوان سوالات متداول',
							'type'  => 'text',
						),
					),
				),
				array(
					'title'  => 'نوار ویژگی‌ها',
					'desc'   => 'پنج ویژگی کوتاه زیر هیرو.',
					'fields' => array(
						'features' => array(
							'label'     => 'ویژگی‌ها',
							'type'      => 'rows',
							'row_label' => 'ویژگی',
							'fields'    => array(
								'icon'     => $icon,
								'title'    => array(
									'label' => 'عنوان',
									'type'  => 'text',
								),
								'subtitle' => array(
									'label' => 'زیرعنوان',
									'type'  => 'text',
								),
							),
						),
					),
				),
				array(
					'title'  => 'دپارتمان‌ها',
					'desc'   => 'کارت‌های دپارتمان آموزشی. اسلاگ باید با اسلاگ دستهٔ دپارتمان یکی باشد.',
					'fields' => array(
						'departments' => array(
							'label'     => 'دپارتمان‌ها',
							'type'      => 'rows',
							'row_label' => 'دپارتمان',
							'fields'    => array(
								'title'    => array(
									'label' => 'عنوان',
									'type'  => 'text',
								),
								'subtitle' => array(
									'label' => 'زیرعنوان',
									'type'  => 'text',
								),
								'image'    => array(
									'label' => 'تصویر',
									'type'  => 'image',
								),
								'slug'     => array(
									'label' => 'اسلاگ دسته',
									'type'  => 'text',
									'desc'  => 'مثال: strings',
								),
							),
						),
					),
				),
				array(
					'title'  => 'آمار',
					'desc'   => 'اعداد و برچسب‌های نوار آمار.',
					'fields' => array(
						'stats' => array(
							'label'     => 'آمار',
							'type'      => 'rows',
							'row_label' => 'آمار',
							'fields'    => array(
								'value' => array(
									'label' => 'عدد',
									'type'  => 'text',
								),
								'label' => array(
									'label' => 'برچسب',
									'type'  => 'text',
								),
							),
						),
					),
				),
				array(
					'title'  => 'مسترکلاس',
					'desc'   => 'کارت مسترکلاس کنار اسلایدر سالن.',
					'fields' => array(
						'masterclass_tag'      => array(
							'label' => 'برچسب بالای کارت',
							'type'  => 'text',
						),
						'masterclass_title'    => array(
							'label' => 'عنوان',
							'type'  => 'textarea',
						),
						'masterclass_text'     => array(
							'label' => 'متن',
							'type'  => 'textarea',
						),
						'masterclass_image'    => array(
							'label' => 'تصویر',
							'type'  => 'image',
						),
						'masterclass_cta_text' => array(
							'label' => 'متن دکمه',
							'type'  => 'text',
						),
						'masterclass_cta_url'  => array(
							'label' => 'لینک دکمه',
							'type'  => 'url',
							'desc'  => 'اگر خالی باشد، به بخش تماس صفحه اصلی می‌رود.',
						),
					),
				),
				array(
					'title'  => 'پیشرفت و نظر هنرجو',
					'desc'   => 'تصاویر قبل/بعد و نظر هنرجوی نمونه.',
					'fields' => array(
						'progress_before_image' => array(
							'label' => 'تصویر قبل از آموزش',
							'type'  => 'image',
						),
						'progress_after_image'  => array(
							'label' => 'تصویر بعد از آموزش',
							'type'  => 'image',
						),
						'testimonial_name'      => array(
							'label' => 'نام هنرجو',
							'type'  => 'text',
						),
						'testimonial_role'      => array(
							'label' => 'عنوان / ساز',
							'type'  => 'text',
						),
						'testimonial_text'      => array(
							'label' => 'متن نظر',
							'type'  => 'textarea',
						),
						'testimonial_avatar'    => array(
							'label' => 'تصویر هنرجو',
							'type'  => 'image',
						),
					),
				),
			),
		),
		'consult' => array(
			'label'    => 'فرم مشاوره',
			'icon'     => 'calendar',
			'desc'     => 'متن‌ها، فیلدهای مشاورهٔ حضوری و اطلاعات تماس مشاورهٔ تلفنی.',
			'sections' => array(
				array(
					'title'  => 'متن‌های فرم',
					'desc'   => 'عنوان، توضیح و متن دکمهٔ فرم مشاوره.',
					'fields' => array(
						'consult_title'  => array(
							'label' => 'عنوان فرم',
							'type'  => 'text',
						),
						'consult_desc'   => array(
							'label' => 'توضیح فرم',
							'type'  => 'textarea',
						),
						'consult_button' => array(
							'label' => 'متن دکمهٔ ارسال',
							'type'  => 'text',
						),
					),
				),
				array(
					'title'  => 'فیلدهای مشاورهٔ حضوری',
					'desc'   => 'برچسب، متن راهنما و اجباری‌بودن هر فیلد را مشخص کنید.',
					'fields' => array(
						'consult_name_label'       => array(
							'label' => 'برچسب نام',
							'type'  => 'text',
						),
						'consult_name_placeholder' => array(
							'label' => 'متن جای‌نگهدار نام',
							'type'  => 'text',
						),
						'consult_name_required'    => array(
							'label' => 'نام اجباری باشد',
							'type'  => 'checkbox',
						),
						'consult_phone_label'      => array(
							'label' => 'برچسب موبایل',
							'type'  => 'text',
						),
						'consult_phone_placeholder' => array(
							'label' => 'متن جای‌نگهدار موبایل',
							'type'  => 'text',
						),
						'consult_phone_required'   => array(
							'label' => 'موبایل اجباری باشد',
							'type'  => 'checkbox',
						),
						'consult_instrument_label' => array(
							'label' => 'برچسب ساز',
							'type'  => 'text',
						),
						'consult_instrument_required' => array(
							'label' => 'انتخاب ساز اجباری باشد',
							'type'  => 'checkbox',
						),
						'consult_instruments'      => array(
							'label' => 'گزینه‌های ساز',
							'type'  => 'textarea',
							'desc'  => 'هر ساز در یک خط.',
						),
						'consult_topic_label'      => array(
							'label' => 'برچسب موضوع مشاوره',
							'type'  => 'text',
						),
						'consult_topics'           => array(
							'label' => 'گزینه‌های موضوع مشاوره',
							'type'  => 'textarea',
							'desc'  => 'هر خط به شکل value|label؛ مثال: level|تعیین سطح',
						),
						'consult_message_label'    => array(
							'label' => 'برچسب توضیحات',
							'type'  => 'text',
						),
						'consult_message_placeholder' => array(
							'label' => 'متن جای‌نگهدار توضیحات',
							'type'  => 'text',
						),
						'consult_message_enabled'  => array(
							'label' => 'فیلد توضیحات نمایش داده شود',
							'type'  => 'checkbox',
						),
					),
				),
				array(
					'title'  => 'مشاورهٔ تلفنی',
					'desc'   => 'با انتخاب تب «مشاوره تلفنی» در سایت، فرم پنهان می‌شود و شمارهٔ تماس نمایش داده می‌شود.',
					'fields' => array(
						'consult_phone_tab_enabled' => array(
							'label' => 'تب مشاورهٔ تلفنی فعال باشد',
							'type'  => 'checkbox',
						),
						'consult_phone_number'     => array(
							'label' => 'شمارهٔ تماس',
							'type'  => 'text',
							'desc'  => 'اگر خالی باشد، شمارهٔ اصلی آکادمی استفاده می‌شود.',
						),
						'consult_phone_note'       => array(
							'label' => 'توضیح مشاورهٔ تلفنی',
							'type'  => 'textarea',
						),
						'consult_phone_hours'      => array(
							'label' => 'ساعات پاسخ‌گویی',
							'type'  => 'text',
						),
					),
				),
				array(
					'title'  => 'نگهداری داده‌ها',
					'desc'   => 'درخواست‌های قدیمی‌تر از این تعداد روز به‌صورت خودکار پاک می‌شوند. عدد 0 پاک‌سازی خودکار را غیرفعال می‌کند.',
					'fields' => array(
						'consult_retention_days' => array(
							'label' => 'نگهداری درخواست‌ها (روز)',
							'type'  => 'text',
						),
					),
				),
			),
		),
		'faq' => array(
			'label'    => 'سوالات متداول',
			'icon'     => 'help',
			'desc'     => 'افزودن، حذف و ویرایش سوالات پرتکرار. اگر در ویجت‌ها «سوالات متداول» ویجت اضافه شود، همان نمایش داده می‌شود.',
			'sections' => array(
				array(
					'title'  => 'سوالات پرتکرار',
					'desc'   => 'این سوال‌ها در ستون راست پنل پایینی نمایش داده می‌شوند.',
					'fields' => array(
						'faqs' => array(
							'label'     => 'سوالات',
							'type'      => 'rows',
							'row_label' => 'سوال',
							'fields'    => array(
								'question' => array(
									'label' => 'سوال',
									'type'  => 'text',
								),
								'answer'   => array(
									'label' => 'پاسخ',
									'type'  => 'textarea',
								),
							),
						),
					),
				),
			),
		),
		'teachers' => array(
			'label'    => 'اساتید',
			'icon'     => 'users',
			'desc'     => 'تنظیمات مربوط به آرشیو اساتید.',
			'sections' => array(
				array(
					'title'  => 'آرشیو اساتید',
					'desc'   => 'معرفی کوتاه بالای صفحهٔ اساتید.',
					'fields' => array(
						'teachers_archive_intro' => array(
							'label' => 'معرفی کوتاه',
							'type'  => 'textarea',
						),
					),
				),
			),
		),
		'footer'  => array(
			'label'    => 'فوتر',
			'icon'     => 'wave',
			'desc'     => 'متن‌های پایین سایت.',
			'sections' => array(
				array(
					'title'  => 'متن‌های فوتر',
					'desc'   => 'پیام کوتاه برند و متن لینک پایین صفحه.',
					'fields' => array(
						'footer_text'     => array(
							'label' => 'متن کوتاه فوتر',
							'type'  => 'textarea',
						),
						'footer_credit'   => array(
							'label' => 'متن لینک پایین',
							'type'  => 'text',
						),
					),
				),
			),
		),
		'notifications' => array(
			'label'    => 'اعلان‌ها و پیام‌رسان‌ها',
			'icon'     => 'send',
			'desc'     => 'ارسال درخواست‌های فرم به بله یا روبیکا. شمارهٔ گیرنده فقط مرجع مدیریتی است؛ برای دریافت پیام، گیرنده باید ربات را استارت کند یا ربات در گروه/کانال باشد.',
			'sections' => array(
				array(
					'title'  => 'کانال اصلی',
					'desc'   => 'توکن‌ها فقط در پیشخوان ذخیره می‌شوند و در سایت نمایش داده نمی‌شوند.',
					'fields' => array(
						'notify_platform' => array(
							'label'   => 'پلتفرم فعال',
							'type'    => 'select',
							'options' => array(
								'disabled' => 'غیرفعال',
								'bale'     => 'بله',
								'rubika'   => 'روبیکا',
								'both'     => 'هر دو',
							),
						),
						'bale_enabled'    => array(
							'label' => 'بله فعال باشد',
							'type'  => 'checkbox',
						),
						'bale_token'      => array(
							'label' => 'توکن ربات بله',
							'type'  => 'secret',
						),
						'bale_chat_id'    => array(
							'label' => 'Chat ID مقصد بله',
							'type'  => 'text',
							'desc'  => 'از دکمهٔ «دریافت Chat ID» در صفحهٔ اعلان‌ها استفاده کنید.',
						),
						'bale_phone'      => array(
							'label' => 'شمارهٔ گیرنده (مرجع)',
							'type'  => 'text',
						),
						'bale_base'       => array(
							'label' => 'آدرس پایهٔ API بله',
							'type'  => 'text',
							'desc'  => 'پیش‌فرض: https://tapi.bale.ai — فقط در صورت استفاده از پروکسی تغییر دهید.',
						),
						'rubika_enabled'  => array(
							'label' => 'روبیکا فعال باشد',
							'type'  => 'checkbox',
						),
						'rubika_token'    => array(
							'label' => 'توکن ربات روبیکا',
							'type'  => 'secret',
						),
						'rubika_chat_id'  => array(
							'label' => 'Chat ID مقصد روبیکا',
							'type'  => 'text',
							'desc'  => 'از دکمهٔ «دریافت Chat ID» در صفحهٔ اعلان‌ها استفاده کنید.',
						),
						'rubika_phone'    => array(
							'label' => 'شمارهٔ گیرنده (مرجع)',
							'type'  => 'text',
						),
						'rubika_base'     => array(
							'label' => 'آدرس پایهٔ API روبیکا',
							'type'  => 'text',
							'desc'  => 'پیش‌فرض: https://botapi.rubika.ir/v3 — فقط در صورت استفاده از پروکسی تغییر دهید.',
						),
					),
				),
				array(
					'title'  => 'پشتیبان ایمیل و نگهداری',
					'desc'   => 'ایمیل پیش‌فرض نیست؛ فقط در صورت فعال‌کردن و ناموفق‌بودن پیام‌رسان‌ها استفاده می‌شود.',
					'fields' => array(
						'notify_email_fallback' => array(
							'label' => 'ارسال ایمیل پشتیبان در صورت شکست',
							'type'  => 'checkbox',
						),
						'notify_email'          => array(
							'label' => 'ایمیل پشتیبان',
							'type'  => 'email',
							'desc'  => 'خالی باشد، ایمیل دریافت درخواست‌ها یا ایمیل مدیر استفاده می‌شود.',
						),
						'notify_log_retention_days' => array(
							'label' => 'نگهداری لاگ اعلان (روز)',
							'type'  => 'text',
						),
						'notify_max_attempts'   => array(
							'label' => 'حداکثر تلاش ارسال',
							'type'  => 'text',
						),
					),
				),
			),
		),
		'assistant' => array(
			'label'    => 'دستیار هوشمند',
			'icon'     => 'headset',
			'desc'     => 'اتصال به API و ایندکس سایت. دستیار در زمان پاسخ‌دهی فقط فایل ایندکس را می‌خواند و به دیتابیس کوئری نمی‌زند.',
			'sections' => array(
				array(
					'title'  => 'اتصال API',
					'desc'   => 'یک سرویس سازگار با OpenAI (Chat Completions) وارد کنید. کلید API فقط برای درخواست‌های همین سایت استفاده می‌شود.',
					'fields' => array(
						'ai_enabled'       => array(
							'label' => 'دستیار فعال باشد',
							'type'  => 'checkbox',
						),
						'ai_api_url'       => array(
							'label' => 'آدرس API',
							'type'  => 'url',
							'desc'  => 'مثال: https://api.openai.com/v1/chat/completions',
						),
						'ai_api_key'       => array(
							'label' => 'کلید API',
							'type'  => 'secret',
							'desc'  => 'این کلید در تنظیمات همین سایت ذخیره می‌شود و هرگز به مرورگر کاربران ارسال نمی‌شود.',
						),
						'ai_model'         => array(
							'label' => 'مدل',
							'type'  => 'text',
							'desc'  => 'مثال: gpt-4o-mini',
						),
						'ai_system_prompt' => array(
							'label' => 'دستور سیستم',
							'type'  => 'textarea',
							'desc'  => 'لحن و محدودهٔ پاسخ‌گویی دستیار را مشخص می‌کند.',
						),
						'ai_greeting'      => array(
							'label' => 'پیام خوش‌آمد',
							'type'  => 'textarea',
						),
					),
				),
				array(
					'title'  => 'پیشنهاد سوال‌ها',
					'desc'   => 'این سوال‌ها به شکل چیپ در پنل چت نمایش داده می‌شوند و کاربر می‌تواند آن‌ها را انتخاب یا ویرایش کند.',
					'fields' => array(
						'ai_starters' => array(
							'label'     => 'سوال‌های پیشنهادی',
							'type'      => 'rows',
							'row_label' => 'سوال',
							'fields'    => array(
								'text' => array(
									'label' => 'متن سوال',
									'type'  => 'text',
								),
							),
						),
					),
				),
				array(
					'title'  => 'دانشنامه دستیار',
					'desc'   => 'اطلاعات ثابتی که می‌خواهی دستیار از آن‌ها استفاده کند. بعد از ذخیره، ایندکس را بازسازی کن.',
					'fields' => array(
						'ai_knowledge' => array(
							'label'     => 'مدخل‌های دانش',
							'type'      => 'rows',
							'row_label' => 'مدخل',
							'fields'    => array(
								'title'   => array(
									'label' => 'موضوع',
									'type'  => 'text',
								),
								'content' => array(
									'label' => 'محتوا / پاسخ',
									'type'  => 'textarea',
								),
							),
						),
					),
				),
				array(
					'title'  => 'یادگیری',
					'desc'   => 'با فعال بودن این گزینه، پاسخ‌هایی که کاربران مثبت ارزیابی کنند به دانش دستیار اضافه می‌شوند.',
					'fields' => array(
						'ai_learning' => array(
							'label' => 'یادگیری از بازخورد کاربران',
							'type'  => 'checkbox',
						),
					),
				),
			),
		),
		'admin'   => array(
			'label'    => 'ورود و پیشخوان',
			'icon'     => 'user',
			'desc'     => 'ظاهر صفحهٔ ورود و پیام خوش‌آمد پیشخوان.',
			'sections' => array(
				array(
					'title'  => 'صفحهٔ ورود',
					'desc'   => 'پس‌زمینه و پیام اختصاصی صفحهٔ ورود.',
					'fields' => array(
						'login_bg'      => array(
							'label' => 'تصویر پس‌زمینهٔ ورود',
							'type'  => 'image',
							'desc'  => 'اگر خالی باشد، تصویر هیرو استفاده می‌شود.',
						),
						'login_message' => array(
							'label' => 'پیام زیر عنوان ورود',
							'type'  => 'textarea',
						),
					),
				),
				array(
					'title'  => 'پیشخوان',
					'desc'   => 'متن خوش‌آمد و اطلاعیهٔ بالای داشبورد.',
					'fields' => array(
						'dashboard_title'  => array(
							'label' => 'عنوان خوش‌آمد',
							'type'  => 'text',
						),
						'dashboard_text'   => array(
							'label' => 'متن خوش‌آمد',
							'type'  => 'textarea',
						),
						'dashboard_notice' => array(
							'label' => 'اطلاعیهٔ پیشخوان',
							'type'  => 'textarea',
							'desc'  => 'اختیاری؛ در کارت خوش‌آمد نمایش داده می‌شود.',
						),
					),
				),
			),
		),
	);
}

/**
 * Current value for a setting.
 *
 * @param string $key Option key.
 * @return mixed
 */
function pm_settings_value( $key ) {
	$defaults = pm_settings_defaults();
	$default  = isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
	$value    = get_theme_mod( 'pm_' . $key, null );

	if ( null === $value ) {
		return $default;
	}
	if ( is_array( $value ) && empty( $value ) && is_array( $default ) ) {
		return $default;
	}

	return $value;
}

/**
 * Sanitize a single setting value.
 *
 * @param array $field Field definition.
 * @param mixed $value Raw value.
 * @return mixed
 */
function pm_settings_sanitize_field( $field, $value ) {
	$type = isset( $field['type'] ) ? $field['type'] : 'text';

	switch ( $type ) {
		case 'rows':
			return pm_settings_sanitize_rows( $field, $value );
		case 'hero_title':
			return pm_sanitize_hero_title( (string) $value );
		case 'textarea':
			return sanitize_textarea_field( (string) $value );
		case 'email':
			return sanitize_email( (string) $value );
		case 'url':
			return esc_url_raw( (string) $value );
		case 'password':
		case 'secret':
			return sanitize_text_field( (string) $value );
		case 'select':
			$value   = sanitize_key( (string) $value );
			$options = isset( $field['options'] ) ? $field['options'] : array();
			return isset( $options[ $value ] ) ? $value : '';
		case 'checkbox':
			return '1' === (string) $value ? '1' : '0';
		case 'image':
			$value = trim( (string) $value );
			if ( '' === $value ) {
				return '';
			}
			// Repair an old corruption where a bundled filename became http://filename.jpg.
			if ( preg_match( '#^https?://[^/]+\.(?:jpe?g|png|gif|webp|svg)$#i', $value ) ) {
				return sanitize_file_name( preg_replace( '#^https?://#i', '', $value ) );
			}
			if ( preg_match( '#^https?://#i', $value ) || 0 === strpos( $value, '/' ) ) {
				return esc_url_raw( $value );
			}
			return sanitize_text_field( $value );
		case 'icon':
			$value  = sanitize_key( (string) $value );
			$icons  = pm_settings_icon_options();
			return isset( $icons[ $value ] ) ? $value : 'wave';
		default:
			return sanitize_text_field( (string) $value );
	}
}

/**
 * Sanitize repeatable rows.
 *
 * @param array $field Field definition.
 * @param mixed $value Raw rows.
 * @return array
 */
function pm_settings_sanitize_rows( $field, $value ) {
	if ( ! is_array( $value ) || empty( $field['fields'] ) ) {
		return array();
	}

	$out = array();
	foreach ( $value as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$item = array();
		foreach ( $field['fields'] as $key => $subfield ) {
			$raw          = isset( $row[ $key ] ) ? $row[ $key ] : '';
			$item[ $key ] = pm_settings_sanitize_field( $subfield, $raw );
		}
		if ( '' === implode( '', array_map( 'strval', $item ) ) ) {
			continue;
		}
		$out[] = $item;
	}

	return $out;
}

/**
 * Save settings.
 */
function pm_handle_theme_settings_save() {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( 'شما اجازهٔ تغییر تنظیمات قالب را ندارید.', 'دسترسی غیرمجاز', array( 'response' => 403 ) );
	}

	check_admin_referer( 'pm_save_theme_settings' );

	$input  = isset( $_POST['pm_settings'] ) && is_array( $_POST['pm_settings'] ) ? wp_unslash( $_POST['pm_settings'] ) : array();
	$schema = pm_settings_schema();

	foreach ( $schema as $group ) {
		if ( empty( $group['sections'] ) ) {
			continue;
		}
		foreach ( $group['sections'] as $section ) {
			if ( empty( $section['fields'] ) ) {
				continue;
			}
			foreach ( $section['fields'] as $key => $field ) {
				$raw = isset( $input[ $key ] ) ? $input[ $key ] : '';
				if ( 'secret' === ( isset( $field['type'] ) ? $field['type'] : '' ) ) {
					$raw = trim( (string) $raw );
					if ( '' === $raw ) {
						continue;
					}
				}
				set_theme_mod( 'pm_' . $key, pm_settings_sanitize_field( $field, $raw ) );
			}
		}
	}

	pm_ai_build_index();

	wp_safe_redirect( pm_settings_url( array( 'pm_saved' => '1' ) ) );
	exit;
}
add_action( 'admin_post_pm_save_theme_settings', 'pm_handle_theme_settings_save' );

/**
 * Reset settings to defaults.
 */
function pm_handle_theme_settings_reset() {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( 'شما اجازهٔ تغییر تنظیمات قالب را ندارید.', 'دسترسی غیرمجاز', array( 'response' => 403 ) );
	}

	check_admin_referer( 'pm_reset_theme_settings' );

	foreach ( pm_settings_schema() as $group ) {
		if ( empty( $group['sections'] ) ) {
			continue;
		}
		foreach ( $group['sections'] as $section ) {
			if ( empty( $section['fields'] ) ) {
				continue;
			}
			foreach ( array_keys( $section['fields'] ) as $key ) {
				remove_theme_mod( 'pm_' . $key );
			}
		}
	}

	wp_safe_redirect( pm_settings_url( array( 'pm_reset' => '1' ) ) );
	exit;
}
add_action( 'admin_post_pm_reset_theme_settings', 'pm_handle_theme_settings_reset' );

/**
 * Re-run the safe demo content importer.
 */
function pm_handle_theme_settings_demo() {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( 'شما اجازهٔ تغییر تنظیمات قالب را ندارید.', 'دسترسی غیرمجاز', array( 'response' => 403 ) );
	}

	check_admin_referer( 'pm_seed_demo_content' );

	delete_option( 'pm_demo_seeded' );
	pm_seed_demo_content();

	wp_safe_redirect( pm_settings_url( array( 'pm_demo' => '1' ) ) );
	exit;
}
add_action( 'admin_post_pm_seed_demo_content', 'pm_handle_theme_settings_demo' );

/**
 * Register the settings menu.
 */
function pm_settings_menu() {
	add_menu_page(
		'تنظیمات پارسیان',
		'پارسیان',
		'edit_theme_options',
		'pm-settings',
		'pm_render_settings_page',
		'dashicons-format-audio',
		3
	);
}
add_action( 'admin_menu', 'pm_settings_menu' );

/**
 * Admin bar shortcut.
 *
 * @param WP_Admin_Bar $bar Admin bar.
 */
function pm_settings_admin_bar( $bar ) {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}
	$bar->add_node(
		array(
			'id'    => 'pm-settings',
			'title' => 'تنظیمات پارسیان',
			'href'  => pm_settings_url(),
			'meta'  => array( 'title' => 'تنظیمات قالب پارسیان' ),
		)
	);
}
add_action( 'admin_bar_menu', 'pm_settings_admin_bar', 80 );

/**
 * Load panel assets on our admin pages.
 *
 * @param string $hook Current admin page hook.
 */
function pm_admin_ui_assets( $hook ) {
	$screen           = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	$is_settings      = 'toplevel_page_pm-settings' === $hook;
	$is_dashboard     = $screen && 'dashboard' === $screen->id;
	$is_notifications = false !== strpos( (string) $hook, 'pm-notifications' ) || ( $screen && false !== strpos( (string) $screen->id, 'pm-notifications' ) );
	$is_knowledge     = false !== strpos( (string) $hook, 'pm-ai-knowledge' ) || ( $screen && false !== strpos( (string) $screen->id, 'pm-ai-knowledge' ) );

	if ( ! $is_settings && ! $is_dashboard && ! $is_notifications && ! $is_knowledge ) {
		return;
	}

	wp_enqueue_style(
		'pm-admin-font',
		'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800;900&display=swap',
		array(),
		null
	);

	wp_enqueue_style(
		'pm-admin-ui',
		PM_URI . '/assets/css/admin-ui.css',
		array( 'pm-admin-font' ),
		pm_asset_version( 'assets/css/admin-ui.css' )
	);
	wp_enqueue_script(
		'pm-admin-ui',
		PM_URI . '/assets/js/admin-ui.js',
		array( 'jquery' ),
		pm_asset_version( 'assets/js/admin-ui.js' ),
		true
	);

	if ( $is_settings ) {
		wp_enqueue_media();
	}
}
add_action( 'admin_enqueue_scripts', 'pm_admin_ui_assets' );

/**
 * Print the icon sprite on our pages.
 */
function pm_admin_sprite() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen ) {
		return;
	}
	if ( 'dashboard' === $screen->id || 'toplevel_page_pm-settings' === $screen->id ) {
		pm_sprite();
	}
}
add_action( 'admin_footer', 'pm_admin_sprite' );

/**
 * Render a media picker.
 *
 * @param string $name  Input name.
 * @param string $id    Input ID.
 * @param mixed  $value Current value.
 */
function pm_render_media_control( $name, $id, $value ) {
	$preview = $value ? pm_media_url( $value ) : '';
	?>
	<div class="pm-media" data-pm-media>
		<input type="text" class="pm-media__input" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" dir="ltr" placeholder="https://…">
		<div class="pm-media__actions">
			<button type="button" class="button" data-pm-media-select>انتخاب از رسانه</button>
			<button type="button" class="button-link" data-pm-media-clear>حذف</button>
		</div>
		<div class="pm-media__preview" data-pm-media-preview>
			<?php if ( $preview ) : ?>
				<img src="<?php echo esc_url( $preview ); ?>" alt="">
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/**
 * Render a field control.
 *
 * @param string $name  Input name.
 * @param string $id    Input ID.
 * @param array  $field Field definition.
 * @param mixed  $value Current value.
 */
function pm_render_field_control( $name, $id, $field, $value ) {
	$type = isset( $field['type'] ) ? $field['type'] : 'text';

	if ( 'checkbox' === $type ) {
		printf(
			'<label class="pm-checkbox"><input type="checkbox" id="%s" name="%s" value="1" %s><span>فعال</span></label>',
			esc_attr( $id ),
			esc_attr( $name ),
			checked( '1', (string) $value, false )
		);
		return;
	}

	if ( 'textarea' === $type || 'hero_title' === $type ) {
		$rows = isset( $field['rows'] ) ? (int) $field['rows'] : 3;
		printf(
			'<textarea id="%s" name="%s" rows="%d">%s</textarea>',
			esc_attr( $id ),
			esc_attr( $name ),
			$rows,
			esc_textarea( (string) $value )
		);
		return;
	}

	if ( 'secret' === $type ) {
		printf(
			'<input type="password" id="%s" name="%s" value="" placeholder="%s" autocomplete="new-password" dir="ltr">',
			esc_attr( $id ),
			esc_attr( $name ),
			esc_attr( $value ? 'ذخیره شده — برای تغییر مقدار جدید وارد کنید' : 'مقدار را وارد کنید' )
		);
		return;
	}

	if ( 'select' === $type ) {
		$options = isset( $field['options'] ) ? $field['options'] : array();
		?>
		<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>">
			<?php foreach ( $options as $option_key => $option_label ) : ?>
				<option value="<?php echo esc_attr( $option_key ); ?>" <?php selected( $value, $option_key ); ?>><?php echo esc_html( $option_label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
		return;
	}

	if ( 'image' === $type ) {
		pm_render_media_control( $name, $id, $value );
		return;
	}

	if ( 'icon' === $type ) {
		$options = isset( $field['options'] ) ? $field['options'] : pm_settings_icon_options();
		?>
		<div class="pm-icon-field">
			<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" data-pm-icon-select>
				<?php foreach ( $options as $icon_key => $icon_label ) : ?>
					<option value="<?php echo esc_attr( $icon_key ); ?>" <?php selected( $value, $icon_key ); ?>><?php echo esc_html( $icon_label ); ?></option>
				<?php endforeach; ?>
			</select>
			<span class="pm-icon-preview" data-pm-icon-preview><?php echo pm_icon( $value ? $value : 'wave' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		</div>
		<?php
		return;
	}

	$input_type = 'text';
	if ( 'email' === $type ) {
		$input_type = 'email';
	} elseif ( 'url' === $type ) {
		$input_type = 'url';
	} elseif ( 'password' === $type ) {
		$input_type = 'password';
	}
	$ltr = in_array( $type, array( 'email', 'url', 'password' ), true ) ? ' dir="ltr"' : '';
	printf(
		'<input type="%s" id="%s" name="%s" value="%s"%s>',
		esc_attr( $input_type ),
		esc_attr( $id ),
		esc_attr( $name ),
		esc_attr( $value ),
		$ltr // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	);
}

/**
 * Render one repeatable row.
 *
 * @param string     $key   Setting key.
 * @param array      $field Field definition.
 * @param int|string $index Row index.
 * @param array      $row   Row values.
 */
function pm_render_rows_item( $key, $field, $index, $row ) {
	$subfields = isset( $field['fields'] ) ? $field['fields'] : array();
	$row       = wp_parse_args( $row, array_fill_keys( array_keys( $subfields ), '' ) );
	$label     = is_numeric( $index ) ? 'ردیف ' . pm_fa( $index + 1 ) : 'ردیف جدید';
	?>
	<div class="pm-row" data-pm-row>
		<div class="pm-row__top">
			<span class="pm-row__num"><?php echo esc_html( $label ); ?></span>
			<div class="pm-row__actions">
				<button type="button" data-pm-row-up aria-label="انتقال به بالا">↑</button>
				<button type="button" data-pm-row-down aria-label="انتقال به پایین">↓</button>
				<button type="button" class="is-danger" data-pm-row-remove aria-label="حذف ردیف">×</button>
			</div>
		</div>
		<div class="pm-row__grid">
			<?php foreach ( $subfields as $subkey => $subfield ) : ?>
				<?php
				$subname = 'pm_settings[' . $key . '][' . $index . '][' . $subkey . ']';
				$subid   = 'pm-' . $key . '-' . $index . '-' . $subkey;
				$wide    = in_array( $subfield['type'], array( 'image', 'textarea', 'rows' ), true ) ? ' pm-row__field--wide' : '';
				?>
				<div class="pm-row__field<?php echo esc_attr( $wide ); ?>">
					<span class="pm-row__label"><?php echo esc_html( $subfield['label'] ); ?></span>
					<?php pm_render_field_control( $subname, $subid, $subfield, $row[ $subkey ] ); ?>
					<?php if ( ! empty( $subfield['desc'] ) ) : ?>
						<small class="pm-row__desc"><?php echo esc_html( $subfield['desc'] ); ?></small>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}

/**
 * Render repeatable rows container.
 *
 * @param string $key   Setting key.
 * @param array  $field Field definition.
 * @param mixed  $value Current rows.
 */
function pm_render_rows_field( $key, $field, $value ) {
	$value = is_array( $value ) ? $value : array();
	?>
	<div class="pm-rows" data-pm-rows data-key="<?php echo esc_attr( $key ); ?>">
		<div class="pm-rows__body" data-pm-rows-body>
			<?php foreach ( $value as $index => $row ) : ?>
				<?php pm_render_rows_item( $key, $field, $index, $row ); ?>
			<?php endforeach; ?>
		</div>
		<template data-pm-row-template>
			<?php pm_render_rows_item( $key, $field, '__INDEX__', array() ); ?>
		</template>
		<button type="button" class="pm-add-row" data-pm-add-row>
			<span>+</span> افزودن <?php echo esc_html( isset( $field['row_label'] ) ? $field['row_label'] : 'ردیف' ); ?>
		</button>
		<small class="pm-rows__note">اگر همهٔ ردیف‌ها حذف شوند، مقادیر پیش‌فرض پوسته نمایش داده می‌شود.</small>
	</div>
	<?php
}

/**
 * Render one settings field wrapper.
 *
 * @param string $key   Setting key.
 * @param array  $field Field definition.
 */
function pm_render_settings_field( $key, $field ) {
	$value = pm_settings_value( $key );
	$type  = isset( $field['type'] ) ? $field['type'] : 'text';
	$wide  = in_array( $type, array( 'textarea', 'hero_title', 'image', 'rows' ), true );
	$id    = 'pm-setting-' . $key;
	?>
	<div class="pm-form-field<?php echo $wide ? ' pm-form-field--wide' : ''; ?>">
		<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
		<?php if ( 'rows' === $type ) : ?>
			<?php if ( ! empty( $field['desc'] ) ) : ?>
				<p class="pm-form-field__desc"><?php echo esc_html( $field['desc'] ); ?></p>
			<?php endif; ?>
			<?php pm_render_rows_field( $key, $field, $value ); ?>
		<?php else : ?>
			<?php pm_render_field_control( 'pm_settings[' . $key . ']', $id, $field, $value ); ?>
			<?php if ( ! empty( $field['desc'] ) ) : ?>
				<p class="pm-form-field__desc"><?php echo wp_kses_post( $field['desc'] ); ?></p>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Render the settings page.
 */
function pm_render_settings_page() {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( 'شما اجازهٔ دسترسی به این صفحه را ندارید.', 'دسترسی غیرمجاز', array( 'response' => 403 ) );
	}

	$schema   = pm_settings_schema();
	$defaults = pm_settings_defaults();
	$first    = array_key_first( $schema );
	$theme    = wp_get_theme();
	$saved     = isset( $_GET['pm_saved'] ) ? sanitize_key( wp_unslash( $_GET['pm_saved'] ) ) : '';
	$reset     = isset( $_GET['pm_reset'] ) ? sanitize_key( wp_unslash( $_GET['pm_reset'] ) ) : '';
	$demo      = isset( $_GET['pm_demo'] ) ? sanitize_key( wp_unslash( $_GET['pm_demo'] ) ) : '';
	$ai_notice  = isset( $_GET['pm_ai_index'] ) ? sanitize_key( wp_unslash( $_GET['pm_ai_index'] ) ) : '';
	$ai_cleared = isset( $_GET['pm_ai_cleared'] ) ? sanitize_key( wp_unslash( $_GET['pm_ai_cleared'] ) ) : '';
	$ai_index   = pm_ai_index_status();
	$ai_pending = pm_ai_knowledge_count( 'pending' );
	$ai_approved = pm_ai_knowledge_count( 'approved' );
	$ai_count  = isset( $_GET['pm_ai_count'] ) ? absint( wp_unslash( $_GET['pm_ai_count'] ) ) : $ai_index['count'];
	$ai_built  = $ai_index['built'] ? date_i18n( 'Y/m/d H:i', $ai_index['built'] ) : '—';
	$ai_error  = get_option( 'pm_ai_index_error', '' );
	$teachers  = wp_count_posts( 'pm_teacher' );
	$bookings  = wp_count_posts( 'pm_booking' );

	pm_sprite();
	?>
	<div class="wrap pm-settings-wrap" dir="rtl">
		<div class="pm-admin-hero">
			<div class="pm-admin-hero__brand">
				<span class="pm-admin-hero__mark"><?php echo pm_icon( 'wave' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<div>
					<span class="pm-admin-eyebrow">Parsian Music Academy</span>
					<h1>تنظیمات قالب پارسیان</h1>
					<p>محتوای صفحه اصلی، برند، تماس، فوتر و صفحهٔ ورود را بدون دست زدن به کد مدیریت کنید.</p>
				</div>
			</div>
			<div class="pm-admin-hero__meta">
				<span class="pm-admin-chip">نسخهٔ <?php echo esc_html( $theme->get( 'Version' ) ); ?></span>
				<span class="pm-admin-chip">WordPress <?php echo esc_html( get_bloginfo( 'version' ) ); ?></span>
				<a class="pm-admin-btn pm-admin-btn--ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener">مشاهدهٔ سایت</a>
				<a class="pm-admin-btn pm-admin-btn--ghost" href="<?php echo esc_url( admin_url( 'customize.php' ) ); ?>">سفارشی‌ساز</a>
			</div>
		</div>

		<?php if ( '1' === $saved ) : ?>
			<div class="pm-admin-notice is-success">تنظیمات با موفقیت ذخیره شد.</div>
		<?php endif; ?>
		<?php if ( '1' === $reset ) : ?>
			<div class="pm-admin-notice is-success">همهٔ تنظیمات قالب به مقادیر پیش‌فرض بازگشت.</div>
		<?php endif; ?>
		<?php if ( '1' === $demo ) : ?>
			<div class="pm-admin-notice is-success">محتوای نمایشی بررسی شد؛ در صورت خالی بودن، اساتید و دپارتمان‌ها ساخته شدند.</div>
		<?php endif; ?>
		<?php if ( '1' === $ai_notice ) : ?>
			<div class="pm-admin-notice is-success">ایندکس دستیار هوشمند با <?php echo esc_html( pm_fa( $ai_count ) ); ?> مدخل ساخته شد.</div>
		<?php endif; ?>
		<?php if ( '1' === $ai_cleared ) : ?>
			<div class="pm-admin-notice is-success">دانش آموخته‌شدهٔ دستیار پاک شد.</div>
		<?php endif; ?>

		<nav class="pm-admin-tabs" aria-label="بخش‌های تنظیمات">
			<?php foreach ( $schema as $group_key => $group ) : ?>
				<button type="button" class="pm-admin-tab<?php echo $group_key === $first ? ' is-active' : ''; ?>" data-pm-tab="<?php echo esc_attr( $group_key ); ?>">
					<?php echo pm_icon( $group['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span><?php echo esc_html( $group['label'] ); ?></span>
				</button>
			<?php endforeach; ?>
			<button type="button" class="pm-admin-tab" data-pm-tab="tools">
				<?php echo pm_icon( 'edit' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span>ابزارها</span>
			</button>
		</nav>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pm-settings-form" id="pm-settings-form">
			<input type="hidden" name="action" value="pm_save_theme_settings">
			<?php wp_nonce_field( 'pm_save_theme_settings' ); ?>

			<?php foreach ( $schema as $group_key => $group ) : ?>
				<section class="pm-admin-panel" data-pm-panel="<?php echo esc_attr( $group_key ); ?>"<?php echo $group_key === $first ? '' : ' hidden'; ?>>
					<div class="pm-admin-panel__head">
						<div>
							<span class="pm-admin-eyebrow"><?php echo esc_html( $group['label'] ); ?></span>
							<h2><?php echo esc_html( $group['label'] ); ?></h2>
							<p><?php echo esc_html( $group['desc'] ); ?></p>
						</div>
						<span class="pm-admin-panel__icon"><?php echo pm_icon( $group['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					</div>

					<?php foreach ( $group['sections'] as $section ) : ?>
						<div class="pm-admin-card">
							<div class="pm-admin-card__head">
								<h3><?php echo esc_html( $section['title'] ); ?></h3>
								<?php if ( ! empty( $section['desc'] ) ) : ?>
									<p><?php echo esc_html( $section['desc'] ); ?></p>
								<?php endif; ?>
							</div>
							<div class="pm-form-grid">
								<?php foreach ( $section['fields'] as $key => $field ) : ?>
									<?php pm_render_settings_field( $key, $field ); ?>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</section>
			<?php endforeach; ?>

			<div class="pm-savebar" data-pm-savebar>
				<div>
					<strong>آمادهٔ ذخیره</strong>
					<span>تغییرات همهٔ تب‌ها با یک کلیک ذخیره می‌شوند.</span>
				</div>
				<button type="submit" class="pm-admin-btn pm-admin-btn--gold">ذخیرهٔ تنظیمات</button>
			</div>
		</form>

		<section class="pm-admin-panel pm-tools-panel" data-pm-panel="tools" hidden>
			<div class="pm-admin-panel__head">
				<div>
					<span class="pm-admin-eyebrow">ابزارها</span>
					<h2>وضعیت و ابزارهای نگهداری</h2>
					<p>بازگشت به پیش‌فرض، ساخت محتوای نمایشی و مشاهدهٔ وضعیت محیط.</p>
				</div>
				<span class="pm-admin-panel__icon"><?php echo pm_icon( 'edit' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			</div>

			<div class="pm-tools-grid">
				<div class="pm-admin-card">
					<div class="pm-admin-card__head">
						<h3>وضعیت سیستم</h3>
						<p>اطلاعات پایهٔ محیط اجرا.</p>
					</div>
					<ul class="pm-status-list">
						<li><span>وردپرس</span><strong><?php echo esc_html( get_bloginfo( 'version' ) ); ?></strong></li>
						<li><span>PHP</span><strong dir="ltr"><?php echo esc_html( PHP_VERSION ); ?></strong></li>
						<li><span>MySQL</span><strong dir="ltr"><?php echo esc_html( $GLOBALS['wpdb']->db_server_info() ); ?></strong></li>
						<li><span>پوسته</span><strong><?php echo esc_html( $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) ); ?></strong></li>
						<li><span>اساتید منتشرشده</span><strong><?php echo esc_html( pm_fa( isset( $teachers->publish ) ? $teachers->publish : 0 ) ); ?></strong></li>
						<li><span>درخواست‌های مشاوره</span><strong><?php echo esc_html( pm_fa( isset( $bookings->private ) ? $bookings->private : 0 ) ); ?></strong></li>
						<li><span>افزونه‌های فعال</span><strong><?php echo esc_html( pm_fa( count( (array) get_option( 'active_plugins', array() ) ) ) ); ?></strong></li>
					</ul>
				</div>

				<div class="pm-admin-card">
					<div class="pm-admin-card__head">
						<h3>محتوای نمایشی</h3>
						<p>اگر اساتید یا دپارتمان‌ها خالی هستند، محتوای نمونه ساخته می‌شود. محتوای موجود حذف نمی‌شود.</p>
					</div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="pm_seed_demo_content">
						<?php wp_nonce_field( 'pm_seed_demo_content' ); ?>
						<button type="submit" class="pm-admin-btn">ساخت / بررسی محتوای نمایشی</button>
					</form>
				</div>

				<div class="pm-admin-card">
					<div class="pm-admin-card__head">
						<h3>ایندکس دستیار هوشمند</h3>
						<p>دستیار فقط این فایل را می‌خواند و در زمان پاسخ‌دهی به دیتابیس دسترسی ندارد.</p>
					</div>
					<ul class="pm-status-list">
						<li><span>وضعیت فایل</span><strong><?php echo $ai_index['exists'] ? 'آماده' : 'ساخته نشده'; ?></strong></li>
						<li><span>تعداد مدخل</span><strong><?php echo esc_html( pm_fa( $ai_index['count'] ) ); ?></strong></li>
						<li><span>آخرین ساخت</span><strong><?php echo esc_html( $ai_built ); ?></strong></li>
						<li><span>در انتظار بررسی</span><strong><?php echo esc_html( pm_fa( $ai_pending ) ); ?> مدخل</strong></li>
						<li><span>دانش تأییدشده</span><strong><?php echo esc_html( pm_fa( $ai_approved ) ); ?> مدخل</strong></li>
						<?php if ( $ai_error ) : ?><li><span>خطای ایندکس</span><strong style="color:#f0b7b2"><?php echo esc_html( $ai_error ); ?></strong></li><?php endif; ?>
					</ul>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="pm_build_ai_index">
						<?php wp_nonce_field( 'pm_build_ai_index' ); ?>
						<button type="submit" class="pm-admin-btn">بازسازی ایندکس سایت</button>
					</form>
					<p><a class="pm-admin-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=pm-ai-knowledge' ) ); ?>">مدیریت دانش دستیار</a></p>
				</div>

				<div class="pm-admin-card is-danger">
					<div class="pm-admin-card__head">
						<h3>بازگشت به پیش‌فرض</h3>
						<p>همهٔ تنظیمات این پنل پاک می‌شوند و مقادیر پیش‌فرض پوسته برمی‌گردند. محتوا و اساتید حذف نمی‌شوند.</p>
					</div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('همهٔ تنظیمات قالب به پیش‌فرض برگردد؟');">
						<input type="hidden" name="action" value="pm_reset_theme_settings">
						<?php wp_nonce_field( 'pm_reset_theme_settings' ); ?>
						<button type="submit" class="pm-admin-btn is-danger">بازنشانی تنظیمات</button>
					</form>
				</div>
			</div>
		</section>

		<p class="pm-settings-foot">
			<?php echo pm_icon( 'help' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			تنظیمات در بخش «Appearance → Customize» هم در دسترس هستند؛ این پنل برای ویرایش سریع و کامل‌تر ساخته شده است.
		</p>
	</div>
	<?php
}
