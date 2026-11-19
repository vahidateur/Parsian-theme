<?php
/**
 * Centralized notification gateway for Bale and Rubika.
 *
 * Form submissions are always saved first. Messaging is queued independently
 * and can be retried without losing the original submission.
 *
 * @package Parsian_Music
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Notification defaults.
 *
 * @return array
 */
function pm_notify_defaults() {
	return array(
		'notify_platform'            => 'disabled',
		'bale_enabled'               => '1',
		'bale_token'                 => '',
		'bale_chat_id'               => '',
		'bale_phone'                 => '',
		'bale_base'                  => 'https://tapi.bale.ai',
		'rubika_enabled'             => '1',
		'rubika_token'               => '',
		'rubika_chat_id'             => '',
		'rubika_phone'               => '',
		'rubika_base'                => 'https://botapi.rubika.ir/v3',
		'notify_email_fallback'      => '0',
		'notify_email'               => '',
		'notify_log_retention_days'  => '90',
		'notify_max_attempts'        => '5',
	);
}

/**
 * Read a notification setting.
 *
 * @param string $key Key without the pm_ prefix.
 * @return string
 */
function pm_notify_get( $key ) {
	$defaults = pm_notify_defaults();
	return (string) pm_get_option( $key, isset( $defaults[ $key ] ) ? $defaults[ $key ] : '' );
}

/**
 * Notification log table name.
 *
 * @return string
 */
function pm_notify_table() {
	global $wpdb;
	return $wpdb->prefix . 'pm_notifications';
}

/**
 * Check whether the log table exists.
 *
 * @return bool
 */
function pm_notify_table_exists() {
	global $wpdb;
	$table = pm_notify_table();
	return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
}

/**
 * Create/update the notification log table.
 */
function pm_notify_install() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table   = pm_notify_table();
	$charset = $wpdb->get_charset_collate();
	$sql     = "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		submission_id varchar(64) NOT NULL,
		source varchar(120) NOT NULL,
		platform varchar(20) NOT NULL,
		recipient varchar(190) NOT NULL DEFAULT '',
		message longtext NOT NULL,
		context longtext NULL,
		status varchar(20) NOT NULL DEFAULT 'pending',
		attempts int(10) unsigned NOT NULL DEFAULT 0,
		last_error text NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		sent_at datetime NULL,
		next_attempt datetime NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY submission_platform (submission_id, platform),
		KEY status_next (status, next_attempt)
	) {$charset};";

	dbDelta( $sql );
	update_option( 'pm_notify_db_version', '1.0.0', false );
}

add_action( 'after_switch_theme', 'pm_notify_install' );
add_action(
	'init',
	function () {
		if ( '1.0.0' !== get_option( 'pm_notify_db_version' ) ) {
			pm_notify_install();
		}
	},
	5
);
add_action(
	'admin_init',
	function () {
		if ( '1.0.0' !== get_option( 'pm_notify_db_version' ) ) {
			pm_notify_install();
		}
	}
);

/**
 * Enabled platforms based on the master switch and per-platform toggles.
 *
 * @return array
 */
function pm_notify_enabled_platforms() {
	$selection = pm_notify_get( 'notify_platform' );
	if ( 'disabled' === $selection ) {
		return array();
	}

	$platforms = array();
	if ( in_array( $selection, array( 'both', 'bale' ), true ) && '1' === pm_notify_get( 'bale_enabled' ) ) {
		$platforms[] = 'bale';
	}
	if ( in_array( $selection, array( 'both', 'rubika' ), true ) && '1' === pm_notify_get( 'rubika_enabled' ) ) {
		$platforms[] = 'rubika';
	}

	return $platforms;
}

/**
 * Redact secrets from error messages before storing or displaying them.
 *
 * @param string $text Raw text.
 * @return string
 */
function pm_notify_redact( $text ) {
	$text = (string) $text;

	foreach ( array( 'bale_token', 'rubika_token', 'ai_api_key' ) as $key ) {
		$secret = pm_get_option( $key, '' );
		if ( $secret ) {
			$text = str_replace( $secret, '••••••', $text );
		}
	}

	$text = preg_replace( '/bot[0-9A-Za-z:_-]{12,}/', 'bot••••••', $text );
	$text = preg_replace( '/v3\/[0-9A-Za-z:_-]{12,}/', 'v3/••••••', $text );

	return trim( wp_strip_all_tags( $text ) );
}

/**
 * Format a compact Persian notification message.
 *
 * @param array $args Submission args.
 * @return string
 */
function pm_notify_format_message( $args ) {
	$fields  = isset( $args['fields'] ) && is_array( $args['fields'] ) ? $args['fields'] : array();
	$message = array(
		'🔔 درخواست جدید — ' . pm_brand_name(),
		'',
		'شناسه درخواست: #' . ( isset( $args['submission_id'] ) ? $args['submission_id'] : '—' ),
		'منبع: ' . ( isset( $args['source'] ) ? $args['source'] : 'فرم سایت' ),
	);

	foreach ( $fields as $label => $value ) {
		$value = trim( wp_strip_all_tags( (string) $value ) );
		if ( '' === $value ) {
			continue;
		}
		$value = preg_replace( '/\s+/u', ' ', $value );
		if ( mb_strlen( $value ) > 500 ) {
			$value = mb_substr( $value, 0, 500 ) . '…';
		}
		$message[] = $label . ': ' . $value;
	}

	$message[] = '';
	$message[] = 'زمان ثبت: ' . date_i18n( 'Y/m/d H:i', current_time( 'timestamp' ) );

	return mb_substr( implode( "\n", $message ), 0, 3800 );
}

/**
 * Insert a notification row.
 *
 * @param array $args Row args.
 * @return int|false
 */
function pm_notify_insert_row( $args ) {
	global $wpdb;

	if ( ! pm_notify_table_exists() ) {
		return false;
	}

	$table = pm_notify_table();
	$now   = current_time( 'mysql' );

	$existing = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT id FROM {$table} WHERE submission_id = %s AND platform = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			sanitize_text_field( $args['submission_id'] ),
			sanitize_key( $args['platform'] )
		)
	);
	if ( $existing ) {
		return $existing;
	}

	$data = array(
		'submission_id' => sanitize_text_field( $args['submission_id'] ),
		'source'        => sanitize_text_field( $args['source'] ),
		'platform'      => sanitize_key( $args['platform'] ),
		'recipient'     => sanitize_text_field( $args['recipient'] ),
		'message'       => (string) $args['message'],
		'context'       => wp_json_encode( isset( $args['context'] ) ? $args['context'] : array() ),
		'status'        => sanitize_key( $args['status'] ),
		'attempts'      => isset( $args['attempts'] ) ? absint( $args['attempts'] ) : 0,
		'last_error'    => isset( $args['last_error'] ) ? pm_notify_redact( $args['last_error'] ) : '',
		'created_at'    => $now,
		'updated_at'    => $now,
		'next_attempt'  => ! empty( $args['next_attempt'] ) ? $args['next_attempt'] : $now,
	);

	$inserted = $wpdb->insert( $table, $data );
	return $inserted ? (int) $wpdb->insert_id : false;
}

/**
 * Queue a submission for all enabled platforms.
 *
 * @param array $args Submission args: submission_id, source, fields, email.
 * @return string Submission ID.
 */
function pm_notify_submission( $args ) {
	$args = wp_parse_args(
		$args,
		array(
			'submission_id' => '',
			'source'        => 'فرم سایت',
			'fields'        => array(),
			'email'         => '',
		)
	);

	$args['submission_id'] = $args['submission_id'] ? sanitize_text_field( $args['submission_id'] ) : 'PM-' . gmdate( 'Ymd' ) . '-' . strtoupper( wp_generate_password( 6, false, false ) );
	$message               = pm_notify_format_message( $args );
	$context               = array(
		'submission_id' => $args['submission_id'],
		'source'        => $args['source'],
		'fields'        => $args['fields'],
		'email'         => $args['email'],
	);
	$platforms             = pm_notify_enabled_platforms();

	if ( empty( $platforms ) ) {
		pm_notify_insert_row(
			array(
				'submission_id' => $args['submission_id'],
				'source'        => $args['source'],
				'platform'      => 'system',
				'recipient'     => '',
				'message'       => $message,
				'context'       => $context,
				'status'        => 'skipped',
				'last_error'    => 'هیچ کانال پیام‌رسانی فعالی تنظیم نشده است.',
				'next_attempt'  => null,
			)
		);
		pm_notify_email_fallback( $args['submission_id'], $context, $message, 'هیچ کانال پیام‌رسانی فعالی تنظیم نشده است.' );
		return $args['submission_id'];
	}

	foreach ( $platforms as $platform ) {
		$recipient = pm_notify_get( $platform . '_chat_id' );
		pm_notify_insert_row(
			array(
				'submission_id' => $args['submission_id'],
				'source'        => $args['source'],
				'platform'      => $platform,
				'recipient'     => $recipient,
				'message'       => $message,
				'context'       => $context,
				'status'        => 'pending',
				'next_attempt'  => current_time( 'mysql' ),
			)
		);
	}

	pm_notify_schedule();
	return $args['submission_id'];
}

/**
 * Schedule the queue worker.
 */
function pm_notify_schedule() {
	if ( ! wp_next_scheduled( 'pm_process_notification_queue' ) ) {
		wp_schedule_single_event( time() + 10, 'pm_process_notification_queue' );
	}
}
add_action( 'pm_process_notification_queue', 'pm_notify_process_queue' );

/**
 * Process due notification rows.
 *
 * @param int $limit Maximum rows.
 * @return int
 */
function pm_notify_process_queue( $limit = 20 ) {
	global $wpdb;

	if ( ! pm_notify_table_exists() || get_transient( 'pm_notify_processing' ) ) {
		return 0;
	}
	set_transient( 'pm_notify_processing', 1, 120 );

	$table = pm_notify_table();
	$now   = current_time( 'mysql' );
	$max   = max( 1, (int) pm_notify_get( 'notify_max_attempts' ) );

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE status IN ('pending','failed') AND attempts < %d AND (next_attempt IS NULL OR next_attempt <= %s) ORDER BY id ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$max,
			$now,
			$limit
		)
	);

	foreach ( $rows as $row ) {
		pm_notify_process_row( $row );
	}

	delete_transient( 'pm_notify_processing' );

	$remaining = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status IN ('pending','failed')" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( $remaining > 0 && ! wp_next_scheduled( 'pm_process_notification_queue' ) ) {
		wp_schedule_single_event( time() + 60, 'pm_process_notification_queue' );
	}

	return count( $rows );
}

/**
 * Process one notification row.
 *
 * @param object $row Row.
 */
function pm_notify_process_row( $row ) {
	global $wpdb;

	$table = pm_notify_table();
	$now   = current_time( 'mysql' );

	if ( 'email' === $row->platform ) {
		pm_notify_send_email_row( $row );
		return;
	}

	if ( 'system' === $row->platform ) {
		return;
	}

	$token = pm_notify_get( $row->platform . '_token' );
	$chat  = pm_notify_get( $row->platform . '_chat_id' );
	if ( '' === $token || '' === $chat ) {
		pm_notify_mark_failed( $row, 'توکن یا Chat ID برای این پیام‌رسان تنظیم نشده است.', true );
		return;
	}

	$result = pm_notify_api_request(
		$row->platform,
		'sendMessage',
		array(
			'chat_id' => $chat,
			'text'    => $row->message,
		)
	);

	if ( $result['success'] ) {
		$wpdb->update(
			$table,
			array(
				'status'       => 'sent',
				'attempts'     => (int) $row->attempts + 1,
				'sent_at'      => $now,
				'updated_at'   => $now,
				'last_error'   => '',
				'next_attempt' => $now,
			),
			array( 'id' => (int) $row->id )
		);
		return;
	}

	pm_notify_mark_failed( $row, $result['message'] );
}

/**
 * Mark a row failed and apply exponential backoff.
 *
 * @param object $row       Row.
 * @param string $error     Error.
 * @param bool   $permanent Permanent failure (bad config).
 */
function pm_notify_mark_failed( $row, $error, $permanent = false ) {
	global $wpdb;

	$table    = pm_notify_table();
	$attempts = (int) $row->attempts + 1;
	$max      = max( 1, (int) pm_notify_get( 'notify_max_attempts' ) );
	$status   = 'failed';
	$next     = null;

	if ( ! $permanent && $attempts < $max ) {
		$delays = array( 60, 300, 1800, 7200, 21600 );
		$delay  = isset( $delays[ $attempts - 1 ] ) ? $delays[ $attempts - 1 ] : 21600;
		$next   = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) + $delay );
	}

	$wpdb->update(
		$table,
		array(
			'status'       => $status,
			'attempts'     => $attempts,
			'last_error'   => pm_notify_redact( $error ),
			'updated_at'   => current_time( 'mysql' ),
			'next_attempt' => $next ? $next : current_time( 'mysql' ),
		),
		array( 'id' => (int) $row->id )
	);

	if ( ! $permanent && $attempts < $max ) {
		wp_schedule_single_event( time() + ( isset( $delays[ $attempts - 1 ] ) ? $delays[ $attempts - 1 ] : 21600 ), 'pm_process_notification_queue' );
		return;
	}

	$context = json_decode( (string) $row->context, true );
	pm_notify_email_fallback(
		$row->submission_id,
		is_array( $context ) ? $context : array(),
		$row->message,
		$error
	);
}

/**
 * Send an optional email fallback once per submission.
 *
 * @param string $submission_id Submission ID.
 * @param array  $context       Context.
 * @param string $message       Message.
 * @param string $reason        Failure reason.
 */
function pm_notify_email_fallback( $submission_id, $context, $message, $reason = '' ) {
	if ( '1' !== pm_notify_get( 'notify_email_fallback' ) ) {
		return;
	}

	$email = pm_notify_get( 'notify_email' );
	if ( ! $email ) {
		$email = pm_get_option( 'booking_email', '' );
	}
	if ( ! $email ) {
		$email = get_option( 'admin_email' );
	}
	if ( ! is_email( $email ) ) {
		return;
	}

	global $wpdb;
	if ( ! pm_notify_table_exists() ) {
		return;
	}
	$table = pm_notify_table();

	$exists = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT id FROM {$table} WHERE submission_id = %s AND platform = 'email' LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$submission_id
		)
	);
	if ( $exists ) {
		return;
	}

	$id = pm_notify_insert_row(
		array(
			'submission_id' => $submission_id,
			'source'        => isset( $context['source'] ) ? $context['source'] : 'فرم سایت',
			'platform'      => 'email',
			'recipient'     => $email,
			'message'       => $message,
			'context'       => $context,
			'status'        => 'pending',
			'next_attempt'  => current_time( 'mysql' ),
		)
	);
	if ( ! $id ) {
		return;
	}

	$subject = '[پشتیبان ایمیل] ' . ( isset( $context['source'] ) ? $context['source'] : 'درخواست جدید' ) . ' — ' . pm_brand_name();
	$body    = $message . "\n\n—\nارسال از طریق پیام‌رسان انجام نشد: " . pm_notify_redact( $reason );
	$sent    = wp_mail( $email, $subject, $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );

	$update = array(
		'status'       => $sent ? 'sent' : 'failed',
		'attempts'     => 1,
		'last_error'   => $sent ? '' : 'ارسال ایمیل پشتیبان ناموفق بود.',
		'updated_at'   => current_time( 'mysql' ),
		'next_attempt' => current_time( 'mysql' ),
	);
	if ( $sent ) {
		$update['sent_at'] = current_time( 'mysql' );
	}
	$wpdb->update( $table, $update, array( 'id' => $id ) );
}

/**
 * Send a queued email row.
 *
 * @param object $row Row.
 */
function pm_notify_send_email_row( $row ) {
	global $wpdb;

	$table = pm_notify_table();
	$sent  = wp_mail(
		$row->recipient,
		'[پشتیبان ایمیل] درخواست جدید — ' . pm_brand_name(),
		$row->message,
		array( 'Content-Type: text/plain; charset=UTF-8' )
	);

	$update = array(
		'status'       => $sent ? 'sent' : 'failed',
		'attempts'     => (int) $row->attempts + 1,
		'last_error'   => $sent ? '' : 'ارسال ایمیل ناموفق بود.',
		'updated_at'   => current_time( 'mysql' ),
		'next_attempt' => current_time( 'mysql' ),
	);
	if ( $sent ) {
		$update['sent_at'] = current_time( 'mysql' );
	}
	$wpdb->update( $table, $update, array( 'id' => (int) $row->id ) );
}

/**
 * API request helper for Bale/Rubika.
 *
 * @param string     $platform Platform.
 * @param string     $method   API method.
 * @param array|null $payload  JSON payload or null for GET.
 * @return array
 */
function pm_notify_api_request( $platform, $method, $payload = null ) {
	$token = pm_notify_get( $platform . '_token' );
	if ( '' === $token ) {
		return array(
			'success' => false,
			'message' => 'توکن API تنظیم نشده است.',
			'body'    => array(),
			'http'    => 0,
		);
	}

	$base = pm_notify_get( $platform . '_base' );
	if ( ! $base ) {
		$base = 'bale' === $platform ? 'https://tapi.bale.ai' : 'https://botapi.rubika.ir/v3';
	}

	// Enforce the destination policy before the token is placed in any URL.
	$destination = pm_outbound_validate_endpoint( $base, $platform );
	if ( is_wp_error( $destination ) ) {
		return array(
			'success' => false,
			'message' => $destination->get_error_message(),
			'body'    => array(),
			'http'    => 0,
		);
	}

	if ( false !== strpos( $base, '{token}' ) ) {
		$url = str_replace( '{token}', rawurlencode( $token ), rtrim( $base, '/' ) ) . '/' . rawurlencode( $method );
	} elseif ( 'bale' === $platform ) {
		$url = rtrim( $base, '/' ) . '/bot' . $token . '/' . rawurlencode( $method );
	} else {
		$url = rtrim( $base, '/' ) . '/' . $token . '/' . rawurlencode( $method );
	}

	$args = pm_outbound_safe_request_args(
		array(
			'timeout' => 20,
			'headers' => array(
				'Accept'       => 'application/json',
				'Content-Type' => 'application/json',
			),
		)
	);

	if ( null !== $payload ) {
		$args['method'] = 'POST';
		$args['body']   = wp_json_encode( $payload );
	} else {
		$args['method'] = 'GET';
	}

	$response = wp_remote_request( $url, $args );
	if ( is_wp_error( $response ) ) {
		return array(
			'success' => false,
			'message' => pm_notify_redact( $response->get_error_message() ),
			'body'    => array(),
			'http'    => 0,
		);
	}

	$http = (int) wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	$body = is_array( $body ) ? $body : array();

	$ok = false;
	if ( $http >= 200 && $http < 300 ) {
		if ( 'bale' === $platform ) {
			$ok = ! empty( $body['ok'] );
		} else {
			$ok = ( isset( $body['status'] ) && in_array( $body['status'], array( 'OK', 'ok', true, 200 ), true ) ) || isset( $body['data'] );
		}
	}

	$error = '';
	if ( ! $ok ) {
		foreach ( array( 'description', 'message', 'error', 'status_det' ) as $key ) {
			if ( ! empty( $body[ $key ] ) && is_scalar( $body[ $key ] ) ) {
				$error = (string) $body[ $key ];
				break;
			}
		}
		if ( ! $error && isset( $body['error']['message'] ) ) {
			$error = (string) $body['error']['message'];
		}
		if ( ! $error ) {
			$error = 'پاسخ معتبری از سرویس دریافت نشد (HTTP ' . $http . ').';
		}
	}

	return array(
		'success' => $ok,
		'message' => $ok ? 'موفق' : pm_notify_redact( $error ),
		'body'    => $body,
		'http'    => $http,
	);
}

/**
 * Test a platform connection using getMe.
 *
 * @param string $platform Platform.
 * @return array
 */
function pm_notify_test_connection( $platform ) {
	$result = pm_notify_api_request( $platform, 'getMe' );
	if ( $result['success'] ) {
		$name = '';
		if ( isset( $result['body']['result']['username'] ) ) {
			$name = $result['body']['result']['username'];
		} elseif ( isset( $result['body']['data']['bot']['username'] ) ) {
			$name = $result['body']['data']['bot']['username'];
		}
		return array(
			'success' => true,
			'message' => 'اتصال موفق بود' . ( $name ? ' — ' . $name : '' ) . '.',
		);
	}

	return array(
		'success' => false,
		'message' => $result['message'],
	);
}

/**
 * Fetch recent chat IDs from getUpdates for administrator reference.
 *
 * @param string $platform Platform.
 * @return array
 */
function pm_notify_fetch_chat_ids( $platform ) {
	$result = pm_notify_api_request( $platform, 'getUpdates' );
	if ( ! $result['success'] ) {
		return array(
			'success' => false,
			'message' => $result['message'],
			'ids'     => array(),
		);
	}

	$found = array();
	$walk  = function ( $data ) use ( &$walk, &$found ) {
		if ( ! is_array( $data ) ) {
			return;
		}
		foreach ( $data as $key => $value ) {
			if ( in_array( $key, array( 'chat_id', 'chatId' ), true ) && is_scalar( $value ) ) {
				$found[ (string) $value ] = true;
			}
			if ( 'chat' === $key && is_array( $value ) && isset( $value['id'] ) ) {
				$found[ (string) $value['id'] ] = true;
			}
			if ( in_array( $key, array( 'sender_id', 'senderId' ), true ) && is_scalar( $value ) ) {
				$found[ (string) $value ] = true;
			}
			if ( is_array( $value ) ) {
				$walk( $value );
			}
		}
	};
	$walk( $result['body'] );

	return array(
		'success' => true,
		'message' => empty( $found ) ? 'Chat ID جدیدی پیدا نشد. ابتدا ربات را استارت کنید یا در گروه عضو شوید.' : 'Chat IDها پیدا شدند.',
		'ids'     => array_keys( $found ),
	);
}

/**
 * Process queue opportunistically on front-end requests.
 */
function pm_notify_maybe_process_queue() {
	if ( ( defined( 'WP_CLI' ) && WP_CLI ) || is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
		return;
	}
	if ( get_transient( 'pm_notify_front_lock' ) ) {
		return;
	}
	set_transient( 'pm_notify_front_lock', 1, 60 );
	pm_notify_process_queue( 5 );
}
add_action( 'init', 'pm_notify_maybe_process_queue', 25 );

/**
 * Admin actions: test connection, fetch IDs, retry one, process queue.
 */
function pm_notify_admin_action() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی غیرمجاز', 'خطا', array( 'response' => 403 ) );
	}
	check_admin_referer( 'pm_notify_admin' );

	if ( ! pm_notify_table_exists() ) {
		pm_notify_install();
	}

	$action   = isset( $_POST['pm_action'] ) ? sanitize_key( wp_unslash( $_POST['pm_action'] ) ) : '';
	$platform = isset( $_POST['platform'] ) ? sanitize_key( wp_unslash( $_POST['platform'] ) ) : '';
	$result   = array();

	if ( 'test' === $action && in_array( $platform, array( 'bale', 'rubika' ), true ) ) {
		$result = pm_notify_test_connection( $platform );
	} elseif ( 'chats' === $action && in_array( $platform, array( 'bale', 'rubika' ), true ) ) {
		$result = pm_notify_fetch_chat_ids( $platform );
	} elseif ( 'retry' === $action ) {
		$id  = isset( $_POST['row_id'] ) ? absint( $_POST['row_id'] ) : 0;
		$row = $id ? $GLOBALS['wpdb']->get_row( $GLOBALS['wpdb']->prepare( 'SELECT * FROM ' . pm_notify_table() . ' WHERE id = %d', $id ) ) : null;
		if ( $row ) {
			$GLOBALS['wpdb']->update( pm_notify_table(), array( 'status' => 'pending', 'next_attempt' => current_time( 'mysql' ) ), array( 'id' => $id ) );
			pm_notify_process_row( $row );
			$result = array( 'success' => true, 'message' => 'تلاش مجدد انجام شد.' );
		}
	} elseif ( 'process' === $action ) {
		$count  = pm_notify_process_queue( 50 );
		$result = array( 'success' => true, 'message' => 'صف پردازش شد (' . $count . ' ردیف).' );
	}

	if ( $result ) {
		set_transient( 'pm_notify_admin_result_' . get_current_user_id(), $result, 60 );
	}

	wp_safe_redirect( admin_url( 'admin.php?page=pm-notifications' ) );
	exit;
}
add_action( 'admin_post_pm_notify_admin', 'pm_notify_admin_action' );

/**
 * Notification admin menu.
 */
function pm_notify_admin_menu() {
	add_submenu_page(
		'pm-settings',
		'اعلان‌ها و پیام‌رسان‌ها',
		'اعلان‌ها و پیام‌رسان‌ها',
		'manage_options',
		'pm-notifications',
		'pm_render_notifications_page'
	);
}
add_action( 'admin_menu', 'pm_notify_admin_menu', 20 );

/**
 * Gate for notification admin assets.
 *
 * @param string $hook Hook.
 * @return bool
 */
function pm_notify_is_admin_page( $hook ) {
	return false !== strpos( (string) $hook, 'pm-notifications' );
}

/**
 * Render notification dashboard.
 */
function pm_render_notifications_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی غیرمجاز', 'خطا', array( 'response' => 403 ) );
	}

	global $wpdb;
	pm_sprite();

	if ( ! pm_notify_table_exists() ) {
		pm_notify_install();
	}

	$table = pm_notify_table();
	$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC LIMIT 100" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$stats = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status", OBJECT_K ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$result = get_transient( 'pm_notify_admin_result_' . get_current_user_id() );
	delete_transient( 'pm_notify_admin_result_' . get_current_user_id() );
	$settings_url = add_query_arg( array( 'page' => 'pm-settings', 'pm_tab' => 'notifications' ), admin_url( 'admin.php' ) );
	?>
	<div class="wrap pm-settings-wrap pm-notify-wrap" dir="rtl">
		<div class="pm-admin-hero">
			<div class="pm-admin-hero__brand">
				<span class="pm-admin-hero__mark"><?php echo pm_icon( 'send' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<div>
					<span class="pm-admin-eyebrow">Notification Gateway</span>
					<h1>اعلان‌ها و پیام‌رسان‌ها</h1>
					<p>ارسال درخواست‌های فرم به بله یا روبیکا، با صف، تلاش مجدد و پشتیبان ایمیل.</p>
				</div>
			</div>
			<div class="pm-admin-hero__meta">
				<a class="pm-admin-btn pm-admin-btn--gold" href="<?php echo esc_url( $settings_url ); ?>">تنظیمات پیام‌رسان‌ها</a>
			</div>
		</div>

		<?php if ( $result ) : ?>
			<div class="pm-admin-notice <?php echo ! empty( $result['success'] ) ? 'is-success' : 'is-error'; ?>">
				<?php echo esc_html( $result['message'] ); ?>
				<?php if ( ! empty( $result['ids'] ) ) : ?>
					<br><strong>Chat IDها:</strong>
					<code dir="ltr"><?php echo esc_html( implode( ' , ', $result['ids'] ) ); ?></code>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<div class="pm-notify-platforms">
			<?php foreach ( array( 'bale' => 'بله', 'rubika' => 'روبیکا' ) as $platform => $label ) : ?>
				<?php
				$enabled = '1' === pm_notify_get( $platform . '_enabled' );
				$token   = pm_notify_get( $platform . '_token' );
				$chat    = pm_notify_get( $platform . '_chat_id' );
				?>
				<div class="pm-admin-card pm-notify-platform">
					<div class="pm-admin-card__head">
						<h3><?php echo esc_html( $label ); ?></h3>
						<p>
							<?php echo $enabled ? 'فعال' : 'غیرفعال'; ?> —
							توکن: <?php echo $token ? 'ثبت شده' : 'ثبت نشده'; ?> —
							Chat ID: <?php echo $chat ? esc_html( $chat ) : 'ثبت نشده'; ?>
						</p>
					</div>
					<div class="pm-notify-actions">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="pm_notify_admin">
							<input type="hidden" name="pm_action" value="test">
							<input type="hidden" name="platform" value="<?php echo esc_attr( $platform ); ?>">
							<?php wp_nonce_field( 'pm_notify_admin' ); ?>
							<button type="submit" class="pm-admin-btn">تست اتصال</button>
						</form>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="pm_notify_admin">
							<input type="hidden" name="pm_action" value="chats">
							<input type="hidden" name="platform" value="<?php echo esc_attr( $platform ); ?>">
							<?php wp_nonce_field( 'pm_notify_admin' ); ?>
							<button type="submit" class="pm-admin-btn">دریافت Chat ID</button>
						</form>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="pm-notify-stats">
			<?php foreach ( array( 'pending' => 'در صف', 'sent' => 'ارسال‌شده', 'failed' => 'ناموفق', 'skipped' => 'ردشده' ) as $key => $label ) : ?>
				<div><strong><?php echo esc_html( pm_fa( isset( $stats[ $key ] ) ? $stats[ $key ]->total : 0 ) ); ?></strong><span><?php echo esc_html( $label ); ?></span></div>
			<?php endforeach; ?>
		</div>

		<div class="pm-notify-toolbar">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="pm_notify_admin">
				<input type="hidden" name="pm_action" value="process">
				<?php wp_nonce_field( 'pm_notify_admin' ); ?>
				<button type="submit" class="pm-admin-btn pm-admin-btn--gold">پردازش صف</button>
			</form>
			<a class="pm-admin-btn" href="<?php echo esc_url( $settings_url ); ?>">تنظیمات</a>
		</div>

		<div class="pm-admin-card pm-notify-log">
			<div class="pm-admin-card__head">
				<h3>تاریخچهٔ ارسال</h3>
				<p>۱۰۰ ردیف آخر. خطاها بدون نمایش توکن ذخیره می‌شوند.</p>
			</div>
			<div class="pm-notify-table-wrap">
				<table class="pm-notify-table">
					<thead>
						<tr>
							<th>#</th>
							<th>شناسه درخواست</th>
							<th>منبع</th>
							<th>پلتفرم</th>
							<th>وضعیت</th>
							<th>تلاش</th>
							<th>آخرین خطا</th>
							<th>تاریخ</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $rows ) ) : ?>
							<tr><td colspan="9">هنوز اعلانی ثبت نشده است.</td></tr>
						<?php else : ?>
							<?php foreach ( $rows as $row ) : ?>
								<tr>
									<td><?php echo esc_html( pm_fa( $row->id ) ); ?></td>
									<td><code dir="ltr"><?php echo esc_html( $row->submission_id ); ?></code></td>
									<td><?php echo esc_html( $row->source ); ?></td>
									<td><?php echo esc_html( $row->platform ); ?></td>
									<td><span class="pm-notify-status is-<?php echo esc_attr( $row->status ); ?>"><?php echo esc_html( $row->status ); ?></span></td>
									<td><?php echo esc_html( pm_fa( $row->attempts ) ); ?></td>
									<td><?php echo esc_html( $row->last_error ? $row->last_error : '—' ); ?></td>
									<td><?php echo esc_html( date_i18n( 'Y/m/d H:i', strtotime( $row->created_at ) ) ); ?></td>
									<td>
										<?php if ( in_array( $row->status, array( 'failed', 'pending' ), true ) ) : ?>
											<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
												<input type="hidden" name="action" value="pm_notify_admin">
												<input type="hidden" name="pm_action" value="retry">
												<input type="hidden" name="row_id" value="<?php echo esc_attr( $row->id ); ?>">
												<?php wp_nonce_field( 'pm_notify_admin' ); ?>
												<button type="submit" class="button button-small">تلاش مجدد</button>
											</form>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>

		<div class="pm-admin-card pm-notify-guide">
			<h3>راهنمای اتصال</h3>
			<ol>
				<li>در بله یا روبیکا یک ربات بسازید و توکن آن را در تنظیمات پیام‌رسان‌ها وارد کنید.</li>
				<li>گیرنده باید ربات را استارت کند یا ربات در گروه/کانال موردنظر عضو باشد.</li>
				<li>با دکمهٔ «دریافت Chat ID» شناسهٔ مقصد را از پیام‌های اخیر ربات پیدا کنید و در تنظیمات ذخیره کنید.</li>
				<li>شمارهٔ گیرنده فقط مرجع مدیریتی است و به‌تنهایی امکان ارسال پیام خصوصی را نمی‌دهد.</li>
				<li>ابتدا «تست اتصال» و سپس یک فرم آزمایشی ارسال کنید.</li>
			</ol>
		</div>
	</div>
	<?php
}

/**
 * Add submission ID and notification status columns to bookings.
 *
 * @param array $columns Existing columns.
 * @return array
 */
function pm_notify_booking_columns( $columns ) {
	$new = array();
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'title' === $key ) {
			$new['pm_submission'] = 'شناسه';
			$new['pm_notify']     = 'اعلان';
		}
	}
	return $new;
}
add_filter( 'manage_pm_booking_posts_columns', 'pm_notify_booking_columns', 20 );

/**
 * Render booking notification columns.
 *
 * @param string $column  Column.
 * @param int    $post_id Post ID.
 */
function pm_notify_booking_column_content( $column, $post_id ) {
	global $wpdb;
	$submission_id = get_post_meta( $post_id, '_pm_submission_id', true );

	if ( 'pm_submission' === $column ) {
		echo $submission_id ? '<code dir="ltr">' . esc_html( $submission_id ) . '</code>' : '—';
	}

	if ( 'pm_notify' === $column ) {
		if ( ! $submission_id || ! pm_notify_table_exists() ) {
			echo '—';
			return;
		}
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT platform, status FROM ' . pm_notify_table() . ' WHERE submission_id = %s ORDER BY id ASC',
				$submission_id
			)
		);
		if ( empty( $rows ) ) {
			echo '—';
			return;
		}
		$parts = array();
		foreach ( $rows as $row ) {
			$parts[] = $row->platform . ': ' . $row->status;
		}
		echo esc_html( implode( ' | ', $parts ) );
	}
}
add_action( 'manage_pm_booking_posts_custom_column', 'pm_notify_booking_column_content', 10, 2 );

/**
 * Booking notification meta box.
 */
function pm_notify_booking_meta_box() {
	add_meta_box(
		'pm_notify_booking_status',
		'وضعیت اعلان‌ها',
		'pm_notify_booking_meta_box_render',
		'pm_booking',
		'side',
		'high'
	);
}
add_action( 'add_meta_boxes_pm_booking', 'pm_notify_booking_meta_box' );

/**
 * Render booking notification meta box.
 *
 * @param WP_Post $post Post.
 */
function pm_notify_booking_meta_box_render( $post ) {
	global $wpdb;

	$submission_id = get_post_meta( $post->ID, '_pm_submission_id', true );
	$source        = get_post_meta( $post->ID, '_pm_form_source', true );
	echo '<p><strong>شناسه:</strong> <code dir="ltr">' . esc_html( $submission_id ? $submission_id : '—' ) . '</code></p>';
	echo '<p><strong>منبع:</strong> ' . esc_html( $source ? $source : '—' ) . '</p>';

	if ( ! $submission_id || ! pm_notify_table_exists() ) {
		echo '<p>هنوز اعلانی برای این درخواست ثبت نشده است.</p>';
		return;
	}

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT platform, status, attempts, last_error, created_at FROM ' . pm_notify_table() . ' WHERE submission_id = %s ORDER BY id ASC',
			$submission_id
		)
	);

	if ( empty( $rows ) ) {
		echo '<p>هنوز اعلانی برای این درخواست ثبت نشده است.</p>';
		return;
	}

	echo '<table class="widefat striped" style="font-size:11px"><thead><tr><th>پلتفرم</th><th>وضعیت</th><th>تلاش</th></tr></thead><tbody>';
	foreach ( $rows as $row ) {
		echo '<tr><td>' . esc_html( $row->platform ) . '</td><td>' . esc_html( $row->status ) . '</td><td>' . esc_html( $row->attempts ) . '</td></tr>';
		if ( $row->last_error ) {
			echo '<tr><td colspan="3" style="color:#b32d2e">' . esc_html( $row->last_error ) . '</td></tr>';
		}
	}
	echo '</tbody></table>';
}

/**
 * Daily retention cleanup for bookings and notification logs.
 */
function pm_maintenance_cleanup() {
	$days = absint( pm_get_option( 'consult_retention_days', '365' ) );
	if ( $days > 0 ) {
		$cutoff = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( $days * DAY_IN_SECONDS ) );
		$old    = get_posts(
			array(
				'post_type'      => 'pm_booking',
				'post_status'    => 'any',
				'posts_per_page' => 100,
				'date_query'     => array(
					array( 'before' => $cutoff, 'inclusive' => true ),
				),
				'fields'         => 'ids',
			)
		);
		foreach ( $old as $post_id ) {
			wp_delete_post( $post_id, true );
		}
	}

	$log_days = absint( pm_notify_get( 'notify_log_retention_days' ) );
	if ( $log_days > 0 && pm_notify_table_exists() ) {
		global $wpdb;
		$table  = pm_notify_table();
		$cutoff = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( $log_days * DAY_IN_SECONDS ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
add_action( 'pm_daily_maintenance', 'pm_maintenance_cleanup' );

/**
 * Schedule daily maintenance.
 */
function pm_schedule_maintenance() {
	if ( ! wp_next_scheduled( 'pm_daily_maintenance' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'pm_daily_maintenance' );
	}
}
add_action( 'init', 'pm_schedule_maintenance', 30 );
