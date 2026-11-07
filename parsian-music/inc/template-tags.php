<?php
/**
 * Template helpers.
 *
 * @package Parsian_Music
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read a theme option with a default.
 *
 * @param string $key     Option key without the pm_ prefix.
 * @param mixed  $default Default value.
 * @return mixed
 */
function pm_get_option( $key, $default = '' ) {
	return get_theme_mod( 'pm_' . $key, $default );
}

/**
 * Brand name shown in the header and footer.
 *
 * @return string
 */
function pm_brand_name() {
	return pm_get_option( 'brand_name', 'آکادمی موسیقی پارسیان' );
}

/**
 * Latin brand line.
 *
 * @return string
 */
function pm_brand_subtitle() {
	return pm_get_option( 'brand_subtitle', 'Parsian Music Academy' );
}

/**
 * Resolve a bundled image filename or an absolute URL.
 *
 * @param string $file Filename or URL.
 * @return string
 */
function pm_img( $file ) {
	if ( ! $file ) {
		return '';
	}
	if ( preg_match( '#^https?://#i', $file ) ) {
		return esc_url( $file );
	}
	return esc_url( PM_URI . '/assets/images/' . ltrim( $file, '/' ) );
}

/**
 * Resolve a media value that may be an attachment ID, a filename or a URL.
 *
 * @param mixed  $value Media value.
 * @param string $size  Image size.
 * @return string
 */
function pm_media_url( $value, $size = 'full' ) {
	if ( ! $value ) {
		return '';
	}
	if ( is_numeric( $value ) ) {
		$url = wp_get_attachment_image_url( (int) $value, $size );
		return $url ? esc_url( $url ) : '';
	}
	// Repair legacy values of the form http://dept-strings.jpg.
	if ( is_string( $value ) && preg_match( '#^https?://[^/]+\.(?:jpe?g|png|gif|webp|svg)$#i', $value ) ) {
		$value = preg_replace( '#^https?://#i', '', $value );
	}
	return pm_img( $value );
}

/**
 * Inline SVG icon from the theme sprite.
 *
 * @param string $name  Icon name without the i- prefix.
 * @param string $class Extra classes.
 * @param array  $attrs Extra attributes.
 * @return string
 */
function pm_icon( $name, $class = '', $attrs = array() ) {
	$classes = trim( 'pm-ico ' . $class );
	$extra   = '';
	foreach ( $attrs as $key => $value ) {
		$extra .= ' ' . $key . '="' . esc_attr( $value ) . '"';
	}
	return '<svg class="' . esc_attr( $classes ) . '" aria-hidden="true" focusable="false"' . $extra . '><use href="#i-' . esc_attr( $name ) . '"></use></svg>';
}

/**
 * Animated directional arrow used in buttons and cards.
 *
 * @param string $class Extra classes.
 * @return string
 */
function pm_arrow( $class = '' ) {
	$classes = trim( 'pm-arrow ' . $class );

	return '<svg class="' . esc_attr( $classes ) . '" viewBox="0 0 24 16" aria-hidden="true" focusable="false">'
		. '<path class="pm-arrow__line" d="M21 8H5"></path>'
		. '<path class="pm-arrow__head" d="M11 2 5 8l6 6"></path>'
		. '</svg>';
}

/**
 * Convert Latin digits to Persian digits.
 *
 * @param string $str Input.
 * @return string
 */
function pm_fa( $str ) {
	return strtr(
		(string) $str,
		array(
			'0' => '۰',
			'1' => '۱',
			'2' => '۲',
			'3' => '۳',
			'4' => '۴',
			'5' => '۵',
			'6' => '۶',
			'7' => '۷',
			'8' => '۸',
			'9' => '۹',
		)
	);
}

/**
 * Convert Persian/Arabic digits to Latin digits.
 *
 * @param string $str Input.
 * @return string
 */
function pm_to_en_digits( $str ) {
	$str = strtr(
		(string) $str,
		array(
			'۰' => '0',
			'۱' => '1',
			'۲' => '2',
			'۳' => '3',
			'۴' => '4',
			'۵' => '5',
			'۶' => '6',
			'۷' => '7',
			'۸' => '8',
			'۹' => '9',
		)
	);
	return strtr(
		$str,
		array(
			'٠' => '0',
			'١' => '1',
			'٢' => '2',
			'٣' => '3',
			'٤' => '4',
			'٥' => '5',
			'٦' => '6',
			'٧' => '7',
			'٨' => '8',
			'٩' => '9',
		)
	);
}

/**
 * Split a textarea into trimmed non-empty lines.
 *
 * @param string $text Input.
 * @return array
 */
function pm_parse_lines( $text ) {
	if ( ! $text ) {
		return array();
	}
	$lines = preg_split( '/\r\n|\r|\n/', (string) $text );
	$lines = array_map( 'trim', (array) $lines );
	return array_values( array_filter( $lines, 'strlen' ) );
}

/**
 * Parse "value|label" lines.
 *
 * @param string $text Input.
 * @return array
 */
function pm_parse_pairs( $text ) {
	$out = array();
	foreach ( pm_parse_lines( $text ) as $line ) {
		$parts = array_map( 'trim', explode( '|', $line, 2 ) );
		$out[] = array(
			'value' => $parts[0],
			'label' => isset( $parts[1] ) ? $parts[1] : '',
		);
	}
	return $out;
}

/**
 * Parse "title|duration|url|image" video lines.
 *
 * @param string $text Input.
 * @return array
 */
function pm_parse_videos( $text ) {
	$out = array();
	foreach ( pm_parse_lines( $text ) as $line ) {
		$parts = array_map( 'trim', explode( '|', $line ) );
		$out[] = array(
			'title'    => isset( $parts[0] ) ? $parts[0] : '',
			'duration' => isset( $parts[1] ) ? $parts[1] : '',
			'url'      => isset( $parts[2] ) ? $parts[2] : '',
			'image'    => isset( $parts[3] ) ? $parts[3] : '',
		);
	}
	return $out;
}

/**
 * Teacher meta value.
 *
 * @param string   $key     Key without the _pm_teacher_ prefix.
 * @param int|null $post_id Post ID.
 * @param string   $default Default.
 * @return string
 */
function pm_teacher_meta( $key, $post_id = null, $default = '' ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$value   = get_post_meta( $post_id, '_pm_teacher_' . $key, true );
	return ( '' !== $value && null !== $value ) ? $value : $default;
}

/**
 * Teacher portrait URL.
 *
 * @param int|null $post_id Post ID.
 * @return string
 */
function pm_teacher_portrait_url( $post_id = null ) {
	$post_id  = $post_id ? $post_id : get_the_ID();
	$portrait = get_post_meta( $post_id, '_pm_teacher_portrait', true );

	if ( $portrait ) {
		return pm_media_url( $portrait, 'pm-teacher-card' );
	}

	$thumb = get_the_post_thumbnail_url( $post_id, 'pm-teacher-card' );
	return $thumb ? esc_url( $thumb ) : pm_img( 'teacher-placeholder.svg' );
}

/**
 * First department term of a teacher.
 *
 * @param int|null $post_id Post ID.
 * @return WP_Term|null
 */
function pm_teacher_department( $post_id = null ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$terms   = get_the_terms( $post_id, 'pm_department' );
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return null;
	}
	return $terms[0];
}

/**
 * Department label for a teacher.
 *
 * @param int|null $post_id Post ID.
 * @param string   $default Default.
 * @return string
 */
function pm_teacher_department_name( $post_id = null, $default = 'اساتید آموزشگاه' ) {
	$term = pm_teacher_department( $post_id );
	return $term ? $term->name : $default;
}

/**
 * Line icon used on a teacher card, based on the department.
 *
 * @param string $department_slug Department slug.
 * @return string
 */
function pm_teacher_icon( $department_slug ) {
	$map = array(
		'strings' => 'music',
		'piano'   => 'piano',
		'persian' => 'music',
		'theory'  => 'metronome',
		'vocal'   => 'mic',
		'kids'    => 'star',
	);

	return isset( $map[ $department_slug ] ) ? $map[ $department_slug ] : 'music';
}

/**
 * Teachers archive URL.
 *
 * @return string
 */
function pm_get_teacher_archive_url() {
	$url = get_post_type_archive_link( 'pm_teacher' );
	return $url ? $url : home_url( '/teachers/' );
}

/**
 * Contact section URL, with an anchor on the front page.
 *
 * @return string
 */
function pm_get_contact_url() {
	$has_local_contact = is_front_page() || is_post_type_archive( 'pm_teacher' ) || is_tax( 'pm_department' );

	return $has_local_contact ? '#contact' : home_url( '/#contact' );
}

/**
 * Map/directions URL. Prefers a custom Neshan/Google link, then coordinates,
 * then an address search fallback.
 *
 * @return string
 */
function pm_get_map_url() {
	$url = pm_get_option( 'map_url', '' );
	if ( $url && filter_var( $url, FILTER_VALIDATE_URL ) ) {
		return esc_url_raw( $url );
	}

	$lat = preg_replace( '/[^0-9.\-]/', '', pm_get_option( 'map_lat', '' ) );
	$lng = preg_replace( '/[^0-9.\-]/', '', pm_get_option( 'map_lng', '' ) );
	if (
		is_numeric( $lat ) && is_numeric( $lng ) &&
		(float) $lat >= -90 && (float) $lat <= 90 &&
		(float) $lng >= -180 && (float) $lng <= 180
	) {
		return 'https://nshn.ir/?lat=' . rawurlencode( $lat ) . '&lng=' . rawurlencode( $lng );
	}

	$address = pm_get_option( 'address', 'تهران، خیابان ولیعصر، پلاک ۱' );
	return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $address );
}

/**
 * Directions button label.
 *
 * @return string
 */
function pm_get_map_label() {
	return pm_get_option( 'map_label', 'مسیریابی تا آکادمی' );
}

/**
 * Teacher stats, with a useful default.
 *
 * @param int|null $post_id Post ID.
 * @return array
 */
function pm_teacher_stats( $post_id = null ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$stats   = pm_parse_pairs( pm_teacher_meta( 'stats', $post_id ) );
	return ! empty( $stats ) ? $stats : pm_default_teacher_stats();
}

/**
 * Teacher achievements, with a useful default.
 *
 * @param int|null $post_id Post ID.
 * @return array
 */
function pm_teacher_achievements( $post_id = null ) {
	$post_id      = $post_id ? $post_id : get_the_ID();
	$achievements = pm_parse_pairs( pm_teacher_meta( 'achievements', $post_id ) );
	return ! empty( $achievements ) ? $achievements : pm_default_teacher_achievements();
}

/**
 * Teacher videos.
 *
 * @param int|null $post_id Post ID.
 * @return array
 */
function pm_teacher_videos( $post_id = null ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$videos  = pm_parse_videos( pm_teacher_meta( 'videos', $post_id ) );
	if ( empty( $videos ) ) {
		return array();
	}
	foreach ( $videos as &$video ) {
		$video['image_url'] = pm_media_url( $video['image'], 'pm-video-card' );
	}
	unset( $video );
	return $videos;
}

/**
 * Teacher presence days.
 *
 * @param int|null $post_id Post ID.
 * @return array
 */
function pm_teacher_presence_days( $post_id = null ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$days    = pm_teacher_meta( 'presence_days', $post_id, 'یکشنبه، سه‌شنبه، پنجشنبه' );
	$days    = preg_split( '/[،,]+/', $days );
	$days    = array_map( 'trim', (array) $days );
	return array_values( array_filter( $days, 'strlen' ) );
}

/**
 * Render the consultation form.
 *
 * @param array $args Form arguments.
 */
function pm_consult_form( $args = array() ) {
	$defaults = array(
		'id'            => 'pm-consult',
		'context'       => 'general',
		'teacher_id'    => 0,
		'compact'       => false,
		'show_benefits' => false,
		'show_tabs'     => true,
		'title'         => pm_get_option( 'consult_title', 'رزرو مشاوره و تعیین سطح' ),
		'description'   => pm_get_option( 'consult_desc', 'فرم را تکمیل کنید؛ کارشناسان آکادمی برای هماهنگی جلسهٔ مشاوره با شما تماس می‌گیرند.' ),
		'button'        => pm_get_option( 'consult_button', 'ثبت درخواست مشاوره و تعیین سطح' ),
		'redirect'      => is_front_page() ? home_url( '/' ) : ( get_permalink() ? get_permalink() : home_url( '/' ) ),
	);
	$args = wp_parse_args( $args, $defaults );
	get_template_part( 'template-parts/forms/consult-form', null, $args );
}

/**
 * Primary menu with a resilient fallback.
 *
 * @param string $location Menu location.
 */
function pm_primary_menu( $location = 'primary' ) {
	wp_nav_menu(
		array(
			'theme_location' => $location,
			'container'      => false,
			'items_wrap'     => '<ul class="pm-menu">%3$s</ul>',
			'fallback_cb'    => 'pm_fallback_menu',
			'depth'          => 1,
		)
	);
}

/**
 * Fallback menu used before the user creates a WordPress menu.
 *
 * @param array $args Menu arguments.
 */
function pm_fallback_menu( $args = array() ) {
	$front   = is_front_page();
	$archive = pm_get_teacher_archive_url();
	$teacher = is_post_type_archive( 'pm_teacher' ) || is_singular( 'pm_teacher' );

	$items = array(
		array( 'درباره ما', $front ? '#stats' : home_url( '/#stats' ), false ),
		array( 'دیپارتمان‌ها', $front ? '#courses' : home_url( '/#courses' ), false ),
		array( 'اساتید', $archive, $teacher ),
		array( 'فضای آکادمیک', $front ? '#masterclass' : home_url( '/#masterclass' ), false ),
		array( 'ارتباط با ما', pm_get_contact_url(), false ),
	);

	echo '<ul class="pm-menu">';
	foreach ( $items as $item ) {
		printf(
			'<li class="pm-menu__item%1$s"><a href="%2$s">%3$s</a></li>',
			$item[2] ? ' is-active' : '',
			esc_url( $item[1] ),
			esc_html( $item[0] )
		);
	}
	echo '</ul>';
}

/**
 * Inner page hero.
 *
 * @param string $title    Title.
 * @param string $subtitle Subtitle.
 * @param array  $args     Extra args.
 */
function pm_page_hero( $title, $subtitle = '', $args = array() ) {
	$eyebrow = isset( $args['eyebrow'] ) ? $args['eyebrow'] : 'آکادمی موسیقی پارسیان';
	?>
	<section class="pm-page-hero">
		<div class="pm-container">
			<div class="pm-eyebrow"><?php echo esc_html( $eyebrow ); ?></div>
			<h1><?php echo esc_html( $title ); ?></h1>
			<?php if ( $subtitle ) : ?>
				<p><?php echo esc_html( $subtitle ); ?></p>
			<?php endif; ?>
		</div>
	</section>
	<?php
}

/**
 * Pagination wrapper.
 */
function pm_pagination() {
	the_posts_pagination(
		array(
			'mid_size'  => 1,
			'prev_text' => pm_icon( 'chev-right' ) . '<span>قبلی</span>',
			'next_text' => '<span>بعدی</span>' . pm_icon( 'chev-left' ),
			'class'     => 'pm-pagination',
		)
	);
}

/**
 * Social links configured in the Customizer.
 *
 * @return array
 */
function pm_get_social_links() {
	$networks = array(
		'instagram' => array( 'label' => 'اینستاگرام', 'icon' => 'instagram' ),
		'youtube'   => array( 'label' => 'یوتیوب', 'icon' => 'youtube' ),
		'telegram'  => array( 'label' => 'تلگرام', 'icon' => 'telegram' ),
	);
	$links = array();
	foreach ( $networks as $key => $network ) {
		$url = pm_get_option( $key, '' );
		if ( $url ) {
			$links[] = array(
				'url'   => $url,
				'label' => $network['label'],
				'icon'  => $network['icon'],
			);
		}
	}
	return $links;
}
