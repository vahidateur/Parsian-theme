<?php
/**
 * Teacher meta fields.
 *
 * @package Parsian_Music
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Field definitions for a teacher.
 *
 * @return array
 */
function pm_teacher_field_definitions() {
	return array(
		'english_name'        => array( 'label' => 'نام انگلیسی', 'type' => 'text', 'placeholder' => 'Mohammad Reza Karimi' ),
		'role'                => array( 'label' => 'سمت / عنوان', 'type' => 'text', 'placeholder' => 'مدیر مسئول و استاد ویولن' ),
		'instrument'          => array( 'label' => 'ساز / تخصص', 'type' => 'text', 'placeholder' => 'ویولن' ),
		'degree'              => array( 'label' => 'مدرک تحصیلی', 'type' => 'text', 'placeholder' => 'DMA' ),
		'experience'          => array( 'label' => 'سابقه', 'type' => 'text', 'placeholder' => '۳۵+ سال' ),
		'presence_days'       => array( 'label' => 'روزهای حضور', 'type' => 'text', 'placeholder' => 'یکشنبه، سه‌شنبه، پنجشنبه', 'desc' => 'روزها را با کاما جدا کنید.' ),
		'portrait'            => array( 'label' => 'تصویر پرتره', 'type' => 'media', 'desc' => 'در صورت خالی بودن، تصویر شاخص استفاده می‌شود.' ),
		'video_intro'         => array( 'label' => 'لینک ویدئوی معرفی', 'type' => 'url', 'placeholder' => 'https://...' ),
		'audio'               => array( 'label' => 'لینک نمونهٔ صوتی', 'type' => 'url', 'placeholder' => 'https://...' ),
		'bio'                 => array( 'label' => 'بیوگرافی کوتاه', 'type' => 'textarea', 'wide' => true, 'rows' => 3 ),
		'stats'               => array( 'label' => 'آمار', 'type' => 'textarea', 'wide' => true, 'rows' => 4, 'desc' => 'هر خط: مقدار|برچسب — مثلاً: ۳۵+ سال|تجربه آموزش' ),
		'achievements'        => array( 'label' => 'دستاوردها', 'type' => 'textarea', 'wide' => true, 'rows' => 4, 'desc' => 'هر خط: مقدار|برچسب — مثلاً: +۱۲۰|رتبهٔ اول جشنواره' ),
		'videos'              => array( 'label' => 'ویدئوها', 'type' => 'textarea', 'wide' => true, 'rows' => 4, 'desc' => 'هر خط: عنوان|مدت|لینک|تصویر — لینک می‌تواند خالی باشد.' ),
		'testimonial_name'    => array( 'label' => 'نام هنرجو در نظر', 'type' => 'text' ),
		'testimonial_role'    => array( 'label' => 'عنوان هنرجو', 'type' => 'text', 'placeholder' => 'هنرجوی ویولن' ),
		'testimonial_avatar'  => array( 'label' => 'تصویر هنرجو', 'type' => 'media' ),
		'testimonial_text'    => array( 'label' => 'متن نظر هنرجو', 'type' => 'textarea', 'wide' => true, 'rows' => 3 ),
		'before_image'        => array( 'label' => 'تصویر قبل از آموزش', 'type' => 'media' ),
		'after_image'         => array( 'label' => 'تصویر بعد از آموزش', 'type' => 'media' ),
	);
}

/**
 * Register the meta box.
 */
function pm_add_teacher_meta_box() {
	add_meta_box(
		'pm_teacher_details',
		'جزئیات استاد',
		'pm_render_teacher_meta_box',
		'pm_teacher',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'pm_add_teacher_meta_box' );

/**
 * Render the teacher meta box.
 *
 * @param WP_Post $post Current post.
 */
function pm_render_teacher_meta_box( $post ) {
	wp_nonce_field( 'pm_teacher_meta', 'pm_teacher_meta_nonce' );
	$fields = pm_teacher_field_definitions();

	echo '<div class="pm-meta">';
	echo '<p class="pm-meta__intro">این فیلدها در صفحهٔ اختصاصی استاد و کارت‌های اساتید نمایش داده می‌شوند. برای متن اصلی بیوگرافی می‌توانید از ویرایشگر بالای صفحه هم استفاده کنید.</p>';
	echo '<div class="pm-meta__grid">';

	foreach ( $fields as $key => $field ) {
		$value = get_post_meta( $post->ID, '_pm_teacher_' . $key, true );
		$type  = $field['type'];
		$id    = 'pm_teacher_' . $key;
		$class = 'pm-meta__field pm-meta__field--' . $type;
		if ( ! empty( $field['wide'] ) ) {
			$class .= ' pm-meta__field--wide';
		}

		echo '<div class="' . esc_attr( $class ) . '">';
		echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . '</label>';

		if ( 'textarea' === $type ) {
			printf(
				'<textarea id="%1$s" name="pm_teacher[%2$s]" rows="%3$d" placeholder="%4$s">%5$s</textarea>',
				esc_attr( $id ),
				esc_attr( $key ),
				isset( $field['rows'] ) ? (int) $field['rows'] : 3,
				isset( $field['placeholder'] ) ? esc_attr( $field['placeholder'] ) : '',
				esc_textarea( $value )
			);
		} elseif ( 'media' === $type ) {
			$preview = $value ? pm_media_url( $value, 'thumbnail' ) : '';
			printf(
				'<div class="pm-media-field"><input type="text" id="%1$s" name="pm_teacher[%2$s]" value="%3$s" placeholder="آدرس تصویر یا انتخاب از کتابخانه" class="pm-media-field__input" /><button type="button" class="button pm-media-field__button" data-pm-media data-target="%1$s">انتخاب تصویر</button><button type="button" class="button-link pm-media-field__clear" data-pm-media-clear data-target="%1$s">حذف</button><div class="pm-media-field__preview">%4$s</div></div>',
				esc_attr( $id ),
				esc_attr( $key ),
				esc_attr( $value ),
				$preview ? '<img src="' . esc_url( $preview ) . '" alt="" />' : ''
			);
		} else {
			printf(
				'<input type="%1$s" id="%2$s" name="pm_teacher[%3$s]" value="%4$s" placeholder="%5$s" />',
				'url' === $type ? 'url' : 'text',
				esc_attr( $id ),
				esc_attr( $key ),
				esc_attr( $value ),
				isset( $field['placeholder'] ) ? esc_attr( $field['placeholder'] ) : ''
			);
		}

		if ( ! empty( $field['desc'] ) ) {
			echo '<p class="description">' . esc_html( $field['desc'] ) . '</p>';
		}
		echo '</div>';
	}

	echo '</div></div>';
}

/**
 * Save the teacher meta fields.
 *
 * @param int $post_id Post ID.
 */
function pm_save_teacher_meta( $post_id ) {
	if ( ! isset( $_POST['pm_teacher_meta_nonce'] ) ) {
		return;
	}
	$nonce = sanitize_text_field( wp_unslash( $_POST['pm_teacher_meta_nonce'] ) );
	if ( ! wp_verify_nonce( $nonce, 'pm_teacher_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( 'pm_teacher' !== get_post_type( $post_id ) ) {
		return;
	}

	$fields = pm_teacher_field_definitions();
	$data   = isset( $_POST['pm_teacher'] ) ? (array) wp_unslash( $_POST['pm_teacher'] ) : array();

	foreach ( $fields as $key => $field ) {
		$raw   = isset( $data[ $key ] ) ? $data[ $key ] : '';
		$value = '';

		if ( 'textarea' === $field['type'] ) {
			$value = sanitize_textarea_field( $raw );
		} elseif ( 'url' === $field['type'] ) {
			$value = esc_url_raw( $raw );
		} elseif ( 'media' === $field['type'] ) {
			$value = sanitize_text_field( $raw );
		} else {
			$value = sanitize_text_field( $raw );
		}

		if ( '' === $value ) {
			delete_post_meta( $post_id, '_pm_teacher_' . $key );
		} else {
			update_post_meta( $post_id, '_pm_teacher_' . $key, $value );
		}
	}
}
add_action( 'save_post', 'pm_save_teacher_meta' );
