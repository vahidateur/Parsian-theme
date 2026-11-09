<?php
/**
 * Bale Bot API adapter (real API shapes, Telegram-compatible).
 *
 * Implements only verified methods: getMe, sendMessage, getUpdates,
 * setWebhook (+ getWebhookInfo for status). Payloads match the shapes
 * already used by the theme's notification adapters; docs.bale.ai was
 * unreachable from this environment, so no new behavior is assumed —
 * every method/field used here already exists in the shipped theme code.
 *
 * Transport reuses pm_notify_api_request() when the theme is active
 * (same URL building, parsing, and redaction). Standalone fallback uses
 * wp_remote_* directly. The adapter never implements AI logic.
 *
 * @package Parsian_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adapter: HTTP + method builders. Depends only on settings + HTTP.
 */
class PA_Bale_Adapter implements PA_ChannelAdapterInterface {
	protected $http;

	/**
	 * @param callable|null $http function( $url, $args ) -> array|WP_Error.
	 */
	public function __construct( $http = null ) {
		$this->http = $http;
	}

	public function channel() {
		return 'bale';
	}

	public function base_url() {
		$base = trim( (string) pa_setting( 'bale_base', 'https://tapi.bale.ai' ) );
		if ( '' === $base ) {
			$base = 'https://tapi.bale.ai';
		}
		return rtrim( $base, '/' );
	}

	public function token() {
		if ( defined( 'PA_BALE_TOKEN' ) && PA_BALE_TOKEN ) {
			return PA_BALE_TOKEN;
		}
		if ( function_exists( 'pm_notify_get' ) ) {
			return (string) pm_notify_get( 'bale_token' );
		}
		return (string) get_option( 'pa_bale_token', '' );
	}

	public function configured() {
		return '' !== $this->token();
	}

	/**
	 * Low-level call. Returns {success, http, body, message}.
	 */
	public function call( $method, $payload = null ) {
		$token = $this->token();
		if ( '' === $token ) {
			return array( 'success' => false, 'http' => 0, 'body' => array(), 'message' => 'توکن بات تنظیم نشده است.' );
		}
		if ( function_exists( 'pm_notify_api_request' ) ) {
			return pm_notify_api_request( 'bale', $method, $payload );
		}
		$url = $this->base_url() . '/bot' . $token . '/' . rawurlencode( $method );
		$args = array( 'timeout' => 20, 'redirection' => 2, 'headers' => array( 'Accept' => 'application/json' ) );
		if ( null === $payload ) {
			$result = $this->request( $url, $args + array( 'method' => 'GET' ) );
		} else {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body'] = wp_json_encode( $payload );
			$result = $this->request( $url, $args + array( 'method' => 'POST' ) );
		}
		if ( is_wp_error( $result ) ) {
			return array( 'success' => false, 'http' => 0, 'body' => array(), 'message' => 'خطای ارتباط با بله.' );
		}
		$http = (int) wp_remote_retrieve_response_code( $result );
		$body = json_decode( (string) wp_remote_retrieve_body( $result ), true );
		if ( ! is_array( $body ) ) {
			$body = array();
		}
		if ( $http >= 200 && $http < 300 && ! empty( $body['ok'] ) ) {
			return array( 'success' => true, 'http' => $http, 'body' => $body, 'message' => 'ok' );
		}
		return array( 'success' => false, 'http' => $http, 'body' => $body, 'message' => 'پاسخ معتبری از بله دریافت نشد.' );
	}

	protected function request( $url, $args ) {
		if ( is_callable( $this->http ) ) {
			return call_user_func( $this->http, $url, $args );
		}
		return wp_remote_request( $url, $args );
	}

	public function get_me() {
		return $this->call( 'getMe' );
	}

	/**
	 * Send a text message. Returns adapter result array.
	 */
	public function send( $recipient_key, $text, array $options = array() ) {
		$res = $this->call(
			'sendMessage',
			array(
				'chat_id' => (string) $recipient_key,
				'text'    => mb_substr( (string) $text, 0, 4000 ),
			)
		);
		if ( ! empty( $options['conversation_id'] ) ) {
			$repo = new PA_Conversation_Repository();
			$repo->audit(
				absint( $options['conversation_id'] ),
				$res['success'] ? 'bale_sent' : 'bale_failed',
				array( 'chat' => (string) $recipient_key )
			);
		}
		return $res;
	}

	public function get_updates( $offset = 0, $timeout = 0 ) {
		$params = array();
		if ( $offset ) {
			$params['offset'] = absint( $offset );
		}
		if ( $timeout ) {
			$params['timeout'] = absint( $timeout );
		}
		$query = $params ? '?' . http_build_query( $params ) : '';
		$token = $this->token();
		if ( function_exists( 'pm_notify_api_request' ) ) {
			return pm_notify_api_request( 'bale', 'getUpdates' . $query, null );
		}
		$url = $this->base_url() . '/bot' . $token . '/getUpdates' . $query;
		$result = $this->request( $url, array( 'method' => 'GET', 'timeout' => 20 + absint( $timeout ) ) );
		if ( is_wp_error( $result ) ) {
			return array( 'success' => false, 'http' => 0, 'body' => array(), 'message' => 'خطای ارتباط با بله.' );
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $result ), true );
		if ( ! is_array( $body ) || empty( $body['ok'] ) ) {
			return array( 'success' => false, 'http' => (int) wp_remote_retrieve_response_code( $result ), 'body' => array(), 'message' => 'پاسخ معتبری دریافت نشد.' );
		}
		return array( 'success' => true, 'http' => 200, 'body' => $body, 'message' => 'ok' );
	}

	public function set_webhook( $url ) {
		return $this->call( 'setWebhook', array( 'url' => (string) $url ) );
	}

	public function webhook_info() {
		return $this->call( 'getWebhookInfo' );
	}

	/**
	 * Parse an incoming update into a normalized array or null.
	 * Accepts Telegram-compatible message objects (message.text,
	 * message.chat.id, message.from.id, update_id).
	 */
	public function parse_incoming( $raw ) {
		if ( ! is_array( $raw ) || ! isset( $raw['update_id'] ) ) {
			return null;
		}
		$message = null;
		foreach ( array( 'message', 'edited_message', 'channel_post' ) as $key ) {
			if ( isset( $raw[ $key ] ) && is_array( $raw[ $key ] ) ) {
				$message = $raw[ $key ];
				break;
			}
		}
		if ( ! $message || ! isset( $message['text'] ) ) {
			return null;
		}
		$chat_id = '';
		if ( isset( $message['chat']['id'] ) ) {
			$chat_id = (string) $message['chat']['id'];
		} elseif ( isset( $message['from']['id'] ) ) {
			$chat_id = (string) $message['from']['id'];
		}
		if ( '' === $chat_id ) {
			return null;
		}
		return array(
			'update_id' => (string) $raw['update_id'],
			'chat_id'   => $chat_id,
			'text'      => sanitize_textarea_field( (string) $message['text'] ),
			'from_id'   => isset( $message['from']['id'] ) ? (string) $message['from']['id'] : $chat_id,
		);
	}
}

/**
 * Read a Bale setting: constant > theme > plugin option.
 */
function pa_setting( $key, $default = '' ) {
	if ( function_exists( 'pm_notify_get' ) && in_array( $key, array( 'bale_base', 'bale_chat_id', 'bale_enabled' ), true ) ) {
		return pm_notify_get( $key );
	}
	return get_option( 'pa_' . $key, $default );
}

/**
 * Webhook secret (path segment). Generated once, rotatable.
 */
function pa_webhook_secret() {
	$secret = get_option( 'pa_bale_webhook_secret', '' );
	if ( ! is_string( $secret ) || strlen( $secret ) < 16 ) {
		$secret = wp_generate_password( 32, false, false );
		update_option( 'pa_bale_webhook_secret', $secret, false );
	}
	return $secret;
}

/**
 * Full webhook URL for setWebhook.
 */
function pa_webhook_url() {
	return rest_url( 'pa/v1/bale/' . pa_webhook_secret() );
}

/**
 * Record a processed update id; returns false if already seen.
 * INSERT-first so concurrent deliveries cannot both win: the UNIQUE key
 * decides, and the loser re-checks rather than trusting a racy SELECT.
 */
function pa_claim_update( $update_id ) {
	global $wpdb;
	$table = pa_table( 'processed_updates' );
	$update_id = (string) $update_id;
	$was_suppressed = $wpdb->suppress_errors( true );
	$result = $wpdb->insert(
		$table,
		array( 'provider' => 'bale', 'update_id' => $update_id, 'created_at' => current_time( 'mysql' ) ),
		array( '%s', '%s', '%s' )
	);
	$wpdb->suppress_errors( $was_suppressed );
	if ( false !== $result ) {
		return true;
	}
	$exists = (int) $wpdb->get_var(
		$wpdb->prepare( "SELECT id FROM {$table} WHERE provider = %s AND update_id = %s", 'bale', $update_id )
	);
	if ( $exists ) {
		return false;
	}
	return true;
}

/**
 * Ingest one parsed update (polling path, synchronous): persist, answer
 * via shared engine, reply. Returns reply text. Never throws.
 */
function pa_ingest_bale_update( array $parsed ) {
	$repo = new PA_Conversation_Repository();
	$channel_key = 'bale:' . $parsed['chat_id'];
	$conversation_id = $repo->find_or_create( 'bale', $channel_key, 0 );
	$repo->add_message( $conversation_id, 'user', $parsed['text'] );

	$service = new PA_Assistant_Service();
	$answer = $service->answer(
		array(
			'channel'      => 'bale',
			'external_key' => $channel_key,
			'user_id'      => 0,
			'text'         => $parsed['text'],
			'log_user'     => false,
		)
	);
	$repo->audit( $conversation_id, 'bale_reply_queued', array() );

	pa_queue_bale_reply( $parsed['chat_id'], $answer['reply'], $conversation_id );
	return $answer['reply'];
}

/**
 * Schedule a Bale queue worker without ever executing it inline.
 *
 * WP-Cron reports duplicates with WP_Error `duplicate_event` (5.7+); the
 * cron option write can also fail (`could_not_set`). Neither case may run
 * the worker inside the webhook. Presence is re-verified because WP-Cron's
 * duplicate window is only ten minutes and a concurrent request may have
 * stored the same event while update_option() reported "unchanged".
 *
 * @param string $hook      Action hook.
 * @param array  $args      Event arguments.
 * @param int    $timestamp Unix timestamp; defaults to now.
 * @return array{status:string,code:string} status = scheduled|queued|failed.
 */
function pa_schedule_bale_worker( $hook, array $args, $timestamp = 0 ) {
	$timestamp = $timestamp ? absint( $timestamp ) : time();
	$result = wp_schedule_single_event( $timestamp, $hook, $args, true );
	if ( true === $result ) {
		// A pre_schedule_event hijack can claim success without storing the
		// event; only trust a successful write that is actually visible.
		if ( wp_next_scheduled( $hook, $args ) ) {
			return array( 'status' => 'scheduled', 'code' => '' );
		}
		return array( 'status' => 'failed', 'code' => 'event_not_visible' );
	}
	$code = is_wp_error( $result ) ? $result->get_error_code() : 'schedule_false';
	if ( wp_next_scheduled( $hook, $args ) ) {
		return array( 'status' => 'queued', 'code' => $code );
	}
	return array( 'status' => 'failed', 'code' => $code );
}

/**
 * Remove an update claim after persistence failed, so the provider's retry
 * is not discarded as a duplicate.
 */
function pa_release_update( $update_id ) {
	global $wpdb;
	$wpdb->delete(
		pa_table( 'processed_updates' ),
		array( 'provider' => 'bale', 'update_id' => (string) $update_id ),
		array( '%s', '%s' )
	);
}

/**
 * Webhook fast path: persist the incoming message and defer the slow AI
 * work to a cron event so Bale gets an immediate acknowledgement.
 *
 * This function never calls the AI provider, pa_process_bale_reply(), or
 * Bale sendMessage(). A scheduler failure leaves the stored user message
 * pending for the recovery worker.
 *
 * @return int|WP_Error Conversation ID (0 on rate-limit drop), or error
 *                      when the message could not be persisted.
 */
function pa_store_bale_update( array $parsed ) {
	if ( pa_bale_rate_limited( $parsed['chat_id'] ) ) {
		$repo = new PA_Conversation_Repository();
		$cid = $repo->find_or_create( 'bale', 'bale:' . $parsed['chat_id'], 0 );
		$repo->audit( $cid, 'bale_rate_limited', array() );
		return 0;
	}
	$repo = new PA_Conversation_Repository();
	$channel_key = 'bale:' . $parsed['chat_id'];
	$conversation_id = $repo->find_or_create( 'bale', $channel_key, 0 );
	if ( ! $conversation_id ) {
		return new WP_Error( 'pa_persist_failed', 'ذخیرهٔ گفتگو انجام نشد.', array( 'status' => 500 ) );
	}
	$message_id = $repo->add_message( $conversation_id, 'user', $parsed['text'] );
	if ( ! $message_id ) {
		return new WP_Error( 'pa_persist_failed', 'ذخیرهٔ پیام انجام نشد.', array( 'status' => 500 ) );
	}
	$repo->audit( $conversation_id, 'bale_received', array( 'message_id' => (int) $message_id ) );

	$scheduled = pa_schedule_bale_worker( 'pa_bale_reply', array( $conversation_id, (string) $parsed['chat_id'] ) );
	if ( 'failed' === $scheduled['status'] ) {
		// Persisted but unscheduled: the recovery worker owns it now.
		$repo->audit(
			$conversation_id,
			'bale_schedule_failed',
			array( 'hook' => 'pa_bale_reply', 'error' => $scheduled['code'], 'message_id' => (int) $message_id )
		);
	} elseif ( 'queued' === $scheduled['status'] ) {
		// Duplicate delivery or concurrent writer: the worker is already queued.
		$repo->audit(
			$conversation_id,
			'bale_worker_queued',
			array( 'hook' => 'pa_bale_reply', 'error' => $scheduled['code'], 'message_id' => (int) $message_id )
		);
	}
	return $conversation_id;
}

/**
 * Persist a fixed outbound reply and queue its delivery via cron.
 *
 * Used by webhook commands (/start, /unlink) whose confirmation must not be
 * sent synchronously inside the webhook request.
 *
 * @return int|WP_Error Conversation ID.
 */
function pa_defer_bale_reply( $chat_id, $text ) {
	$repo = new PA_Conversation_Repository();
	$chat_id = sanitize_text_field( (string) $chat_id );
	$conversation_id = $repo->find_or_create( 'bale', 'bale:' . $chat_id, 0 );
	if ( ! $conversation_id ) {
		return new WP_Error( 'pa_persist_failed', 'ذخیرهٔ گفتگو انجام نشد.', array( 'status' => 500 ) );
	}
	$message_id = $repo->add_message( $conversation_id, 'assistant', $text );
	if ( ! $message_id ) {
		return new WP_Error( 'pa_persist_failed', 'ذخیرهٔ پاسخ انجام نشد.', array( 'status' => 500 ) );
	}
	$scheduled = pa_schedule_bale_worker( 'pa_bale_retry', array( $conversation_id, $chat_id, 1, (int) $message_id ) );
	if ( 'failed' === $scheduled['status'] ) {
		$repo->audit(
			$conversation_id,
			'bale_delivery_schedule_failed',
			array( 'hook' => 'pa_bale_retry', 'error' => $scheduled['code'], 'message_id' => (int) $message_id )
		);
	} elseif ( 'queued' === $scheduled['status'] ) {
		$repo->audit(
			$conversation_id,
			'bale_delivery_queued',
			array( 'hook' => 'pa_bale_retry', 'error' => $scheduled['code'], 'message_id' => (int) $message_id )
		);
	}
	return $conversation_id;
}

/**
 * Open conversations whose latest message is an unanswered user message
 * older than $stale_minutes. Rate-limited drops are excluded.
 *
 * @return array List of {conversation_id, chat_id, message_id}.
 */
function pa_pending_replies( $stale_minutes = 15, $limit = 100 ) {
	global $wpdb;
	// Stored timestamps use the blog timezone (current_time), so the
	// cutoff must too — gmdate() would wrongly exclude recent rows.
	$cutoff = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - absint( $stale_minutes ) * MINUTE_IN_SECONDS );
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT m.conversation_id, MAX(m.id) AS last_id FROM " . pa_table( 'messages' ) . " m
			 INNER JOIN " . pa_table( 'conversations' ) . " c ON c.id = m.conversation_id
			 WHERE c.status = 'open' AND c.channel = 'bale'
			 GROUP BY m.conversation_id ORDER BY last_id DESC LIMIT %d",
			absint( $limit )
		),
		ARRAY_A
	);
	$pending = array();
	foreach ( (array) $rows as $row ) {
		$last = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT role, created_at FROM " . pa_table( 'messages' ) . " WHERE id = %d",
				$row['last_id']
			),
			ARRAY_A
		);
		if ( ! $last || 'user' !== $last['role'] || $last['created_at'] > $cutoff ) {
			continue;
		}
		$limited = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM " . pa_table( 'events' ) . " WHERE conversation_id = %d AND event = 'bale_rate_limited' AND created_at >= %s",
				$row['conversation_id'],
				$last['created_at']
			)
		);
		if ( $limited ) {
			continue;
		}
		$chat = $wpdb->get_var(
			$wpdb->prepare( "SELECT external_key FROM " . pa_table( 'conversations' ) . " WHERE id = %d", $row['conversation_id'] )
		);
		$chat_id = preg_replace( '/^bale:/', '', (string) $chat );
		// A queued worker owns this conversation while its event is still
		// fresh; an overdue event is treated as stuck so recovery can run.
		$next_event = wp_next_scheduled( 'pa_bale_reply', array( (int) $row['conversation_id'], $chat_id ) );
		if ( $next_event && $next_event > time() - MINUTE_IN_SECONDS ) {
			continue;
		}
		$pending[] = array(
			'conversation_id' => (int) $row['conversation_id'],
			'chat_id'         => $chat_id,
			'message_id'      => (int) $row['last_id'],
		);
	}
	return $pending;
}

/**
 * Assistant messages that were persisted but have no delivery record and
 * no queued retry (worker crashed between answer storage and send).
 *
 * Delivery is matched by assistant message id in the event payload, never
 * by message text. Terminally failed sends (bale_gave_up) are not retried.
 *
 * @return array List of {conversation_id, chat_id, message_id, text}.
 */
function pa_undelivered_replies( $stale_minutes = 15, $limit = 100 ) {
	global $wpdb;
	$cutoff = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - absint( $stale_minutes ) * MINUTE_IN_SECONDS );
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT m.conversation_id, m.id, m.text, c.external_key
			 FROM " . pa_table( 'messages' ) . " m
			 INNER JOIN " . pa_table( 'conversations' ) . " c ON c.id = m.conversation_id
			 INNER JOIN (
				SELECT conversation_id, MAX(id) AS last_id FROM " . pa_table( 'messages' ) . " GROUP BY conversation_id
			 ) latest ON latest.conversation_id = m.conversation_id AND latest.last_id = m.id
			 WHERE c.channel = 'bale' AND m.role = 'assistant' AND m.created_at < %s
			 ORDER BY m.id DESC LIMIT %d",
			$cutoff,
			absint( $limit )
		),
		ARRAY_A
	);
	$pending = array();
	foreach ( (array) $rows as $row ) {
		$message_id = (int) $row['id'];
		$chat_id = preg_replace( '/^bale:/', '', (string) $row['external_key'] );
		$events = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT event, payload FROM " . pa_table( 'events' ) . " WHERE conversation_id = %d ORDER BY id DESC LIMIT 200",
				(int) $row['conversation_id']
			)
		);
		$sent = false;
		$terminal = false;
		$failures = 0;
		$latest = '';
		foreach ( (array) $events as $event ) {
			$payload = json_decode( (string) $event->payload, true );
			if ( ! is_array( $payload ) || ! isset( $payload['message_id'] ) || (int) $payload['message_id'] !== $message_id ) {
				continue;
			}
			if ( '' === $latest && in_array( $event->event, array( 'bale_sending', 'bale_sent', 'bale_failed', 'bale_gave_up' ), true ) ) {
				$latest = $event->event;
			}
			if ( 'bale_sent' === $event->event ) {
				$sent = true;
				break;
			}
			if ( 'bale_failed' === $event->event ) {
				$failures++;
			}
			if ( 'bale_gave_up' === $event->event ) {
				$terminal = true;
			}
			if ( 'bale_delivery_ambiguous' === $event->event ) {
				$terminal = true;
			}
		}
		if ( $sent || $terminal || $failures >= 3 ) {
			continue;
		}
		$queued = false;
		for ( $attempt = 1; $attempt <= 3; $attempt++ ) {
			$next_event = wp_next_scheduled( 'pa_bale_retry', array( (int) $row['conversation_id'], $chat_id, $attempt, $message_id ) );
			if ( $next_event && $next_event > time() - MINUTE_IN_SECONDS ) {
				$queued = true;
				break;
			}
		}
		if ( $queued ) {
			continue;
		}
		$pending[] = array(
			'conversation_id' => (int) $row['conversation_id'],
			'chat_id'         => $chat_id,
			'message_id'      => $message_id,
			'text'            => (string) $row['text'],
			'state'           => 'bale_sending' === $latest ? 'ambiguous' : 'send',
		);
	}
	return $pending;
}

/**
 * Recovery worker: re-drive persisted-but-unanswered updates (worker
 * crash, timeout, or lost cron event) and persisted-but-undelivered
 * replies. Synchronous — run from cron, admin, or WP-CLI, never inside
 * the webhook.
 *
 * @return int Number of recovered conversations/messages.
 */
function pa_recover_pending( $stale_minutes = 15, $limit = 100 ) {
	$recovered = 0;
	foreach ( pa_pending_replies( $stale_minutes, $limit ) as $job ) {
		$repo = new PA_Conversation_Repository();
		$repo->audit( $job['conversation_id'], 'bale_recovered', array( 'message_id' => (int) $job['message_id'] ) );
		pa_process_bale_reply( $job['conversation_id'], $job['chat_id'] );
		$recovered++;
	}
	foreach ( pa_undelivered_replies( $stale_minutes, $limit ) as $job ) {
		$lock = pa_worker_lock_acquire( $job['conversation_id'] );
		if ( ! $lock ) {
			continue;
		}
		try {
			if ( ! empty( $job['state'] ) && 'ambiguous' === $job['state'] ) {
				// A send started but never recorded success/failure. Bale has
				// no idempotency key, so re-sending could duplicate. Escalate
				// for a human instead; the message text is never logged.
				$repo = new PA_Conversation_Repository();
				$repo->set_status( $job['conversation_id'], 'escalated' );
				$repo->audit( $job['conversation_id'], 'bale_delivery_ambiguous', array( 'message_id' => (int) $job['message_id'] ) );
				continue;
			}
			$repo = new PA_Conversation_Repository();
			$repo->audit( $job['conversation_id'], 'bale_redelivered', array( 'message_id' => (int) $job['message_id'] ) );
			pa_queue_bale_reply( $job['chat_id'], $job['text'], $job['conversation_id'], 1, $job['message_id'] );
		} finally {
			pa_worker_lock_release( $lock );
		}
		$recovered++;
	}
	return $recovered;
}

/**
 * Recovery cadence: a server-side cron runs every 15 minutes; the admin
 * button and WP-CLI are the no-system-cron fallback.
 */
function pa_recovery_schedules( $schedules ) {
	$schedules['pa_fifteen_minutes'] = array(
		'interval' => 15 * MINUTE_IN_SECONDS,
		'display'  => 'هر ۱۵ دقیقه (دستیار پارسیان)',
	);
	return $schedules;
}
add_filter( 'cron_schedules', 'pa_recovery_schedules' );

/**
 * Ensure the recovery worker has a recurring event.
 */
function pa_schedule_recovery() {
	if ( ! wp_next_scheduled( 'pa_recover_pending' ) ) {
		wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'pa_fifteen_minutes', 'pa_recover_pending' );
	}
}
add_action( 'init', 'pa_schedule_recovery', 31 );

/**
 * Cron callback: recover a small batch so one run cannot exceed PHP limits.
 */
function pa_recovery_cron() {
	pa_recover_pending( 15, 5 );
}
add_action( 'pa_recover_pending', 'pa_recovery_cron' );

/**
 * Number of conversations awaiting a reply (admin observability).
 */
function pa_pending_count() {
	return count( pa_pending_replies( 0 ) );
}

/**
 * Per-chat webhook rate limit: 30 AI answers per hour (mirrors the theme
 * popup limit). Returns true when the update must be dropped.
 */
function pa_bale_rate_limited( $chat_id ) {
	$key = 'pa_rl_bale_' . md5( (string) $chat_id );
	$count = (int) get_transient( $key );
	if ( $count >= 30 ) {
		return true;
	}
	set_transient( $key, $count + 1, HOUR_IN_SECONDS );
	return false;
}

add_action( 'pa_bale_reply', 'pa_process_bale_reply', 10, 2 );

/**
 * Atomic per-conversation worker lock (options table unique key).
 * A stale lock from a crashed worker is stolen after five minutes.
 *
 * @return string|false Lock option name, or false when another worker owns it.
 */
function pa_worker_lock_acquire( $conversation_id ) {
	$key = 'pa_worker_lock_' . absint( $conversation_id );
	if ( add_option( $key, time(), '', false ) ) {
		return $key;
	}
	$started = (int) get_option( $key, 0 );
	if ( $started && ( time() - $started ) < 5 * MINUTE_IN_SECONDS ) {
		return false;
	}
	delete_option( $key );
	return add_option( $key, time(), '', false ) ? $key : false;
}

/**
 * Release a worker lock.
 */
function pa_worker_lock_release( $key ) {
	if ( $key ) {
		delete_option( $key );
	}
}

/**
 * Whether an assistant message was produced by the AI engine (meta carries
 * action/confidence) rather than a fixed system confirmation.
 */
function pa_is_ai_answer( $meta ) {
	$decoded = json_decode( (string) $meta, true );
	return is_array( $decoded ) && isset( $decoded['action'] );
}

/**
 * Cron worker: answer a persisted Bale message and send the reply.
 *
 * Idempotent per user message: a per-conversation lock blocks overlapping
 * workers, and an existing AI answer after the latest user message makes
 * duplicate/recovered events a no-op.
 */
function pa_process_bale_reply( $conversation_id, $chat_id ) {
	global $wpdb;
	$conversation_id = absint( $conversation_id );
	$chat_id = sanitize_text_field( (string) $chat_id );
	if ( ! $conversation_id || '' === $chat_id ) {
		return;
	}
	$lock = pa_worker_lock_acquire( $conversation_id );
	if ( ! $lock ) {
		$repo = new PA_Conversation_Repository();
		$repo->audit( $conversation_id, 'bale_worker_locked', array() );
		return;
	}
	try {
		$message = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, text FROM " . pa_table( 'messages' ) . " WHERE conversation_id = %d AND role = 'user' ORDER BY id DESC LIMIT 1",
				$conversation_id
			),
			ARRAY_A
		);
		if ( ! $message || '' === trim( (string) $message['text'] ) ) {
			return;
		}
		$last_assistant = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, meta FROM " . pa_table( 'messages' ) . " WHERE conversation_id = %d AND role = 'assistant' ORDER BY id DESC LIMIT 1",
				$conversation_id
			),
			ARRAY_A
		);
		$last_meta = $last_assistant ? json_decode( (string) $last_assistant['meta'], true ) : null;
		if (
			$last_assistant
			&& is_array( $last_meta )
			&& isset( $last_meta['reply_to'] )
			&& (int) $last_meta['reply_to'] === (int) $message['id']
			&& pa_is_ai_answer( $last_assistant['meta'] )
		) {
			$repo = new PA_Conversation_Repository();
			$repo->audit( $conversation_id, 'bale_already_answered', array( 'message_id' => (int) $message['id'] ) );
			return;
		}
		$service = new PA_Assistant_Service();
		$answer = $service->answer(
			array(
				'channel'             => 'bale',
				'external_key'        => 'bale:' . $chat_id,
				'user_id'             => 0,
				'text'                => $message['text'],
				'log_user'            => false,
				'reply_to_message_id' => (int) $message['id'],
			)
		);
		$assistant_id = isset( $answer['message_id'] ) ? (int) $answer['message_id'] : 0;
		pa_queue_bale_reply( $chat_id, $answer['reply'], $conversation_id, 1, $assistant_id );
	} finally {
		pa_worker_lock_release( $lock );
	}
}

add_action( 'pa_bale_retry', 'pa_process_bale_retry', 10, 4 );

/**
 * Send a Bale reply to the conversation's own chat (never to the admin's
 * notification Chat ID). Reuses the theme transport when available for
 * identical parsing/redaction. Bounded retry (3 attempts, 60/300/1800s);
 * delivery is audited on the conversation.
 *
 * @param int $message_id Assistant message id, used for exact retry and
 *                        delivery recovery (0 for legacy callers).
 */
function pa_queue_bale_reply( $chat_id, $text, $conversation_id, $attempt = 1, $message_id = 0 ) {
	$text = mb_substr( (string) $text, 0, 4000 );
	$attempt = max( 1, absint( $attempt ) );
	$message_id = absint( $message_id );
	$audit_payload = array( 'attempt' => $attempt );
	if ( $message_id ) {
		$audit_payload['message_id'] = $message_id;
	}
	$sent = false;
	$repo = new PA_Conversation_Repository();
	// Persisted before the HTTP call: if the process dies mid-send, recovery
	// must not blindly re-send (Bale has no idempotency key).
	$repo->audit( absint( $conversation_id ), 'bale_sending', $audit_payload );
	if ( function_exists( 'pm_notify_api_request' ) ) {
		$res = pm_notify_api_request( 'bale', 'sendMessage', array( 'chat_id' => (string) $chat_id, 'text' => $text ) );
		$sent = ! empty( $res['success'] );
	} else {
		$adapter = new PA_Bale_Adapter();
		$res = $adapter->send( $chat_id, $text, array( 'conversation_id' => $conversation_id ) );
		$sent = ! empty( $res['success'] );
	}
	if ( $sent ) {
		$repo->audit( absint( $conversation_id ), 'bale_sent', $audit_payload );
		return true;
	}
	$repo->audit( absint( $conversation_id ), 'bale_failed', $audit_payload );
	if ( $attempt < 3 ) {
		$delays = array( 1 => 60, 2 => 300, 3 => 1800 );
		$scheduled = pa_schedule_bale_worker(
			'pa_bale_retry',
			array( absint( $conversation_id ), (string) $chat_id, $attempt + 1, $message_id ),
			time() + $delays[ $attempt ]
		);
		if ( 'failed' === $scheduled['status'] ) {
			// No retry event: the recovery worker will redeliver it.
			$repo->audit(
				absint( $conversation_id ),
				'bale_retry_schedule_failed',
				array( 'attempt' => $attempt + 1, 'error' => $scheduled['code'], 'message_id' => $message_id )
			);
		}
	} else {
		$repo->set_status( absint( $conversation_id ), 'escalated' );
		$repo->audit( absint( $conversation_id ), 'bale_gave_up', $audit_payload );
	}
	return false;
}

/**
 * Cron worker for a failed reply retry (also used for deferred system
 * confirmations). When a message id is present, exactly that assistant
 * message is retried, never a newer one.
 */
function pa_process_bale_retry( $conversation_id, $chat_id, $attempt, $message_id = 0 ) {
	global $wpdb;
	$conversation_id = absint( $conversation_id );
	$message_id = absint( $message_id );
	$lock = pa_worker_lock_acquire( $conversation_id );
	if ( ! $lock ) {
		return;
	}
	try {
		if ( $message_id ) {
			$text = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT text FROM " . pa_table( 'messages' ) . " WHERE id = %d AND conversation_id = %d AND role = 'assistant'",
					$message_id,
					$conversation_id
				)
			);
		} else {
			$text = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT text FROM " . pa_table( 'messages' ) . " WHERE conversation_id = %d AND role = 'assistant' ORDER BY id DESC LIMIT 1",
					$conversation_id
				)
			);
		}
		if ( null === $text ) {
			return;
		}
		pa_queue_bale_reply( $chat_id, $text, $conversation_id, absint( $attempt ), $message_id );
	} finally {
		pa_worker_lock_release( $lock );
	}
}
