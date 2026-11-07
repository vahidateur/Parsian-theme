<?php
/**
 * Consultation form handling.
 *
 * @package Parsian_Music
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Instrument options.
 *
 * @return array
 */
function pm_instrument_options() {
	$saved = pm_get_option( 'consult_instruments', '' );
	$lines = pm_parse_lines( $saved );
	if ( ! empty( $lines ) ) {
		return $lines;
	}

	return array(
		'ویولن',
		'ویولن‌سل',
		'پیانو',
		'گیتار',
		'تار و سه‌تار',
		'سنتور',
		'آواز',
		'موسیقی کودک',
		'آهنگسازی',
		'سایر',
	);
}

/**
 * Consultation topic options.
 *
 * @return array
 */
function pm_topic_options() {
	$saved = pm_get_option( 'consult_topics', '' );
	$pairs = pm_parse_pairs( $saved );
	$out   = array();
	foreach ( $pairs as $pair ) {
		if ( '' !== $pair['value'] ) {
			$out[ $pair['value'] ] = $pair['label'] ? $pair['label'] : $pair['value'];
		}
	}
	if ( ! empty( $out ) ) {
		return $out;
	}

	return array(
		'level'  => 'تعیین سطح و مشاوره',
		'career' => 'مسیر حرفه‌ای و دانشگاه',
		'info'   => 'اطلاعات دوره‌ها و ثبت‌نام',
	);
}

/**
 * Handle the consultation form.
 */
function pm_handle_consult() {
	if ( ! isset( $_POST['pm_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pm_nonce'] ) ), 'pm_consult' ) ) {
		wp_die( 'درخواست نامعتبر است.', 'خطا', array( 'response' => 403 ) );
	}

	$form     = isset( $_POST['pm_form'] ) ? sanitize_key( wp_unslash( $_POST['pm_form'] ) ) : 'pm-consult';
	$redirect = isset( $_POST['pm_redirect'] ) ? esc_url_raw( wp_unslash( $_POST['pm_redirect'] ) ) : home_url( '/' );
	$redirect = $redirect ? $redirect : home_url( '/' );

	// Honeypot: bots fill this hidden field.
	if ( ! empty( $_POST['pm_website'] ) ) {
		wp_safe_redirect( add_query_arg( array( 'pm_sent' => '1', 'pm_form' => $form ), $redirect ) );
		exit;
	}

	$name       = isset( $_POST['pm_name'] ) ? sanitize_text_field( wp_unslash( $_POST['pm_name'] ) ) : '';
	$phone_raw  = isset( $_POST['pm_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['pm_phone'] ) ) : '';
	$phone      = preg_replace( '/\D+/', '', pm_to_en_digits( $phone_raw ) );
	if ( 0 === strpos( $phone, '0098' ) ) {
		$phone = '0' . substr( $phone, 4 );
	} elseif ( 0 === strpos( $phone, '98' ) && 12 === strlen( $phone ) ) {
		$phone = '0' . substr( $phone, 2 );
	} elseif ( 0 === strpos( $phone, '9' ) && 10 === strlen( $phone ) ) {
		$phone = '0' . $phone;
	}
	$instrument = isset( $_POST['pm_instrument'] ) ? sanitize_text_field( wp_unslash( $_POST['pm_instrument'] ) ) : '';
	$topic_key  = isset( $_POST['pm_topic'] ) ? sanitize_key( wp_unslash( $_POST['pm_topic'] ) ) : 'level';
	$type       = isset( $_POST['pm_type'] ) ? sanitize_key( wp_unslash( $_POST['pm_type'] ) ) : 'inperson';
	$teacher_id = isset( $_POST['pm_teacher'] ) ? absint( $_POST['pm_teacher'] ) : 0;
	$message    = isset( $_POST['pm_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pm_message'] ) ) : '';

	$type     = in_array( $type, array( 'inperson', 'phone' ), true ) ? $type : 'inperson';
	if ( $teacher_id && 'pm_teacher' !== get_post_type( $teacher_id ) ) {
		$teacher_id = 0;
	}
	$topics   = pm_topic_options();
	$topic    = isset( $topics[ $topic_key ] ) ? $topics[ $topic_key ] : $topics['level'];
	$teacher  = $teacher_id ? get_the_title( $teacher_id ) : '';
	$errors   = array();

	$ip         = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
	$rate_key   = 'pm_consult_rl_' . md5( $ip );
	$rate_count = (int) get_transient( $rate_key );
	if ( $rate_count >= 5 ) {
		$errors[] = 'تعداد درخواست‌ها زیاد است؛ لطفاً چند دقیقه بعد دوباره تلاش کنید.';
	}
	set_transient( $rate_key, $rate_count + 1, 10 * MINUTE_IN_SECONDS );

	$name_length        = function_exists( 'mb_strlen' ) ? mb_strlen( $name ) : strlen( $name );
	$name_required      = '1' === pm_get_option( 'consult_name_required', '1' );
	$phone_required     = '1' === pm_get_option( 'consult_phone_required', '1' );
	$instrument_required = '1' === pm_get_option( 'consult_instrument_required', '1' );

	if ( $name_required && $name_length < 3 ) {
		$errors[] = 'نام کامل را وارد کنید.';
	}
	if ( $phone_required && ! preg_match( '/^09\d{9}$/', $phone ) ) {
		$errors[] = 'شمارهٔ موبایل معتبر نیست.';
	} elseif ( '' !== $phone && ! preg_match( '/^09\d{9}$/', $phone ) ) {
		$errors[] = 'شمارهٔ موبایل معتبر نیست.';
	}
	if ( $instrument_required && '' === $instrument ) {
		$errors[] = 'ساز مورد علاقه را انتخاب کنید.';
	}

	$duplicate_key = 'pm_consult_dup_' . md5( $form . '|' . $name . '|' . $phone . '|' . $instrument . '|' . $message );
	if ( get_transient( $duplicate_key ) ) {
		$errors[] = 'این درخواست قبلاً ثبت شده است؛ لطفاً از ثبت دوباره خودداری کنید.';
	} else {
		set_transient( $duplicate_key, 1, 2 * MINUTE_IN_SECONDS );
	}

	if ( ! empty( $errors ) ) {
		$redirect = add_query_arg(
			array(
				'pm_error' => implode( ' ', $errors ),
				'pm_form'  => $form,
			),
			$redirect
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	$summary = "نام: {$name}\n";
	$summary .= "موبایل: {$phone}\n";
	$summary .= "ساز: {$instrument}\n";
	$summary .= "موضوع: {$topic}\n";
	$summary .= 'نوع مشاوره: ' . ( 'phone' === $type ? 'تلفنی' : 'حضوری' ) . "\n";
	if ( $teacher ) {
		$summary .= "استاد: {$teacher}\n";
	}
	if ( $message ) {
		$summary .= "توضیحات: {$message}\n";
	}

	$submission_id = 'PM-' . gmdate( 'Ymd' ) . '-' . strtoupper( wp_generate_password( 6, false, false ) );
	$source_label  = 'فرم مشاوره';

	$booking_id = wp_insert_post(
		array(
			'post_type'    => 'pm_booking',
			'post_status'  => 'private',
			'post_title'   => 'درخواست مشاوره – ' . $name . ' (' . $submission_id . ')',
			'post_content' => $summary,
		),
		true
	);

	if ( is_wp_error( $booking_id ) || ! $booking_id ) {
		$redirect = add_query_arg(
			array(
				'pm_error' => 'ثبت درخواست با خطا مواجه شد؛ لطفاً دوباره تلاش کنید.',
				'pm_form'  => $form,
			),
			$redirect
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	update_post_meta( $booking_id, '_pm_submission_id', $submission_id );
	update_post_meta( $booking_id, '_pm_form_source', $source_label );
	update_post_meta( $booking_id, '_pm_booking_name', $name );
	update_post_meta( $booking_id, '_pm_booking_phone', $phone );
	update_post_meta( $booking_id, '_pm_booking_instrument', $instrument );
	update_post_meta( $booking_id, '_pm_booking_topic', $topic );
	update_post_meta( $booking_id, '_pm_booking_type', $type );
	update_post_meta( $booking_id, '_pm_booking_teacher', $teacher_id );
	update_post_meta( $booking_id, '_pm_booking_message', $message );

	$to = pm_get_option( 'booking_email', '' );
	if ( ! $to ) {
		$to = get_option( 'admin_email' );
	}

	pm_notify_submission(
		array(
			'submission_id' => $submission_id,
			'source'        => $source_label,
			'fields'        => array(
				'نام و نام خانوادگی' => $name,
				'شماره موبایل'       => $phone,
				'ساز مورد علاقه'     => $instrument,
				'موضوع مشاوره'       => $topic,
				'نوع مشاوره'         => 'phone' === $type ? 'تلفنی' : 'حضوری',
				'استاد'              => $teacher,
				'توضیحات'            => $message,
			),
			'email'         => $to,
		)
	);

	$redirect = add_query_arg(
		array(
			'pm_sent' => '1',
			'pm_form' => $form,
		),
		$redirect
	);
	wp_safe_redirect( $redirect );
	exit;
}
add_action( 'admin_post_nopriv_pm_consult', 'pm_handle_consult' );
add_action( 'admin_post_pm_consult', 'pm_handle_consult' );


