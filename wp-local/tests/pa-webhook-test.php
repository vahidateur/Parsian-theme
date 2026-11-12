<?php
/**
 * Webhook non-blocking + queue recovery tests.
 * Run: wp eval-file tests/pa-webhook-test.php
 *
 * Proves, through the real REST route and real worker functions:
 * - duplicate worker scheduling is queued, never executed inline;
 * - genuine scheduling failure persists + audits + recovers;
 * - recovery and duplicate workers are exactly-once;
 * - /start confirmations are deferred, never sent inside the webhook.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}
$GLOBALS['pa_wh_pass'] = 0;
$GLOBALS['pa_wh_fail'] = 0;
$GLOBALS['pa_wh_bale_calls'] = array();
$GLOBALS['pa_wh_other_calls'] = array();
$GLOBALS['pa_wh_block_schedule'] = false;
$GLOBALS['pa_wh_fail_send'] = false;

function pa_wh_assert( $condition, $label ) {
	if ( $condition ) {
		$GLOBALS['pa_wh_pass']++;
		WP_CLI::log( 'PASS: ' . $label );
	} else {
		$GLOBALS['pa_wh_fail']++;
		WP_CLI::warning( 'FAIL: ' . $label );
	}
}

function pa_wh_bale_calls_for( $chat_id ) {
	$count = 0;
	foreach ( $GLOBALS['pa_wh_bale_calls'] as $call ) {
		$body = json_decode( (string) $call['body'], true );
		if ( is_array( $body ) && isset( $body['chat_id'] ) && (string) $body['chat_id'] === (string) $chat_id ) {
			$count++;
		}
	}
	return $count;
}

global $wpdb;
add_filter(
	'pre_http_request',
	function ( $pre, $args, $url ) {
		$url = (string) $url;
		if ( false !== strpos( $url, 'tapi.bale.ai' ) ) {
			$GLOBALS['pa_wh_bale_calls'][] = array(
				'url'  => $url,
				'body' => isset( $args['body'] ) ? $args['body'] : '',
			);
			if ( ! empty( $GLOBALS['pa_wh_fail_send'] ) ) {
				return new WP_Error( 'pa_bale_test_down', 'mock send failure' );
			}
			return array(
				'response' => array( 'code' => 200, 'message' => 'OK' ),
				'body'     => wp_json_encode( array( 'ok' => true, 'result' => array( 'message_id' => 1 ) ) ),
				'headers'  => array(),
				'cookies'  => array(),
			);
		}
		// Any non-Bale HTTP here is an AI call. Block it and record it.
		$GLOBALS['pa_wh_other_calls'][] = $url;
		return new WP_Error( 'pa_webhook_test_http', 'blocked by test' );
	},
	10,
	3
);
add_filter(
	'pre_schedule_event',
	function ( $pre, $event, $wp_error ) {
		if ( ! empty( $GLOBALS['pa_wh_block_schedule'] ) && isset( $event->hook ) && 'pa_bale_reply' === $event->hook ) {
			return false;
		}
		return $pre;
	},
	10,
	3
);

$GLOBALS['pa_wh_orig_token'] = get_theme_mod( 'pm_bale_token', null );
$GLOBALS['pa_wh_orig_chat'] = get_theme_mod( 'pm_bale_chat_id', null );
set_theme_mod( 'pm_bale_token', 'pa-webhook-test-token' );
set_theme_mod( 'pm_bale_chat_id', '4242' );
register_shutdown_function(
	function () {
		if ( null === $GLOBALS['pa_wh_orig_token'] ) {
			remove_theme_mod( 'pm_bale_token' );
		} else {
			set_theme_mod( 'pm_bale_token', $GLOBALS['pa_wh_orig_token'] );
		}
		if ( null === $GLOBALS['pa_wh_orig_chat'] ) {
			remove_theme_mod( 'pm_bale_chat_id' );
		} else {
			set_theme_mod( 'pm_bale_chat_id', $GLOBALS['pa_wh_orig_chat'] );
		}
	}
);

/**
 * Post an update through the registered REST route.
 */
function pa_wh_post( array $update ) {
	$request = new WP_REST_Request( 'POST', '/pa/v1/bale/' . pa_webhook_secret() );
	$request->set_header( 'Content-Type', 'application/json' );
	$request->set_body( wp_json_encode( $update ) );
	return rest_do_request( $request );
}

function pa_wh_update( $update_id, $chat_id, $text ) {
	return array(
		'update_id' => $update_id,
		'message'   => array(
			'text' => $text,
			'chat' => array( 'id' => $chat_id ),
			'from' => array( 'id' => $chat_id ),
		),
	);
}

function pa_wh_cleanup_chat( $chat_id ) {
	global $wpdb;
	$cid = (int) $wpdb->get_var(
		$wpdb->prepare( "SELECT id FROM " . pa_table( 'conversations' ) . " WHERE channel = 'bale' AND external_key = %s", 'bale:' . $chat_id )
	);
	if ( $cid ) {
		$message_ids = $wpdb->get_col(
			$wpdb->prepare( "SELECT id FROM " . pa_table( 'messages' ) . " WHERE conversation_id = %d", $cid )
		);
		wp_clear_scheduled_hook( 'pa_bale_reply', array( $cid, (string) $chat_id ) );
		foreach ( (array) $message_ids as $message_id ) {
			for ( $attempt = 1; $attempt <= 3; $attempt++ ) {
				wp_clear_scheduled_hook( 'pa_bale_retry', array( $cid, (string) $chat_id, $attempt, (int) $message_id ) );
			}
		}
		$wpdb->delete( pa_table( 'messages' ), array( 'conversation_id' => $cid ) );
		$wpdb->delete( pa_table( 'events' ), array( 'conversation_id' => $cid ) );
		$wpdb->delete( pa_table( 'conversations' ), array( 'id' => $cid ) );
		delete_option( 'pa_worker_lock_' . $cid );
	}
	delete_transient( 'pa_rl_bale_' . md5( (string) $chat_id ) );
}

function pa_wh_audit_count( $cid, $event ) {
	global $wpdb;
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM " . pa_table( 'events' ) . " WHERE conversation_id = %d AND event = %s",
			$cid,
			$event
		)
	);
}

$repo = new PA_Conversation_Repository();

// 1. Duplicate scheduled worker: webhook acks, stores once, never runs AI/send.
pa_wh_cleanup_chat( 'wh-dup' );
$dup_cid = $repo->find_or_create( 'bale', 'bale:wh-dup', 0 );
wp_schedule_single_event( time(), 'pa_bale_reply', array( $dup_cid, 'wh-dup' ) );
$bale_before = count( $GLOBALS['pa_wh_bale_calls'] );
$other_before = count( $GLOBALS['pa_wh_other_calls'] );
$response = pa_wh_post( pa_wh_update( 'wh-dup-1', 'wh-dup', 'سلام تست صف' ) );
$data = $response->get_data();
pa_wh_assert( 200 === $response->get_status() && ! empty( $data['ok'] ), 'webhook acknowledges duplicate-scheduled update' );
$user_rows = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'messages' ) . " WHERE conversation_id = %d AND role = 'user'", $dup_cid ) );
$assistant_rows = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'messages' ) . " WHERE conversation_id = %d AND role = 'assistant'", $dup_cid ) );
pa_wh_assert( 1 === $user_rows, 'duplicate-scheduled update is persisted exactly once' );
pa_wh_assert( 0 === $assistant_rows, 'no AI answer executes inside the webhook' );
pa_wh_assert( $bale_before === count( $GLOBALS['pa_wh_bale_calls'] ), 'no Bale send executes inside the webhook' );
pa_wh_assert( $other_before === count( $GLOBALS['pa_wh_other_calls'] ), 'no AI HTTP request executes inside the webhook' );
pa_wh_assert( false !== wp_next_scheduled( 'pa_bale_reply', array( $dup_cid, 'wh-dup' ) ), 'existing worker stays queued' );
$queued_payload = (string) $wpdb->get_var( $wpdb->prepare( "SELECT payload FROM " . pa_table( 'events' ) . " WHERE conversation_id = %d AND event = 'bale_worker_queued' ORDER BY id DESC LIMIT 1", $dup_cid ) );
pa_wh_assert( false !== strpos( $queued_payload, 'duplicate_event' ), 'duplicate scheduling reason is observable' );
$wpdb->query( "DELETE FROM " . pa_table( 'processed_updates' ) . " WHERE provider = 'bale' AND update_id IN ('wh-dup-1')" );
pa_wh_cleanup_chat( 'wh-dup' );

// 2. Genuine scheduling failure: persisted, audited, recoverable, no inline work.
pa_wh_cleanup_chat( 'wh-fail' );
$GLOBALS['pa_wh_block_schedule'] = true;
$bale_before = count( $GLOBALS['pa_wh_bale_calls'] );
$other_before = count( $GLOBALS['pa_wh_other_calls'] );
$response = pa_wh_post( pa_wh_update( 'wh-fail-1', 'wh-fail', 'سلام تست شکست' ) );
$GLOBALS['pa_wh_block_schedule'] = false;
$data = $response->get_data();
$fail_cid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM " . pa_table( 'conversations' ) . " WHERE channel = 'bale' AND external_key = %s", 'bale:wh-fail' ) );
pa_wh_assert( 200 === $response->get_status() && ! empty( $data['ok'] ), 'webhook acknowledges persisted update after scheduling failure' );
pa_wh_assert( $fail_cid > 0, 'failed-scheduled update left a conversation row' );
$user_rows = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'messages' ) . " WHERE conversation_id = %d AND role = 'user'", $fail_cid ) );
$assistant_rows = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'messages' ) . " WHERE conversation_id = %d AND role = 'assistant'", $fail_cid ) );
pa_wh_assert( 1 === $user_rows && 0 === $assistant_rows, 'failed scheduling leaves the user message pending, not answered' );
pa_wh_assert( $bale_before === count( $GLOBALS['pa_wh_bale_calls'] ), 'failed scheduling performs no Bale send' );
pa_wh_assert( $other_before === count( $GLOBALS['pa_wh_other_calls'] ), 'failed scheduling performs no AI call' );
pa_wh_assert( false === wp_next_scheduled( 'pa_bale_reply', array( $fail_cid, 'wh-fail' ) ), 'no worker event survives a genuine failure' );
$failure_payload = (string) $wpdb->get_var( $wpdb->prepare( "SELECT payload FROM " . pa_table( 'events' ) . " WHERE conversation_id = %d AND event = 'bale_schedule_failed' ORDER BY id DESC LIMIT 1", $fail_cid ) );
pa_wh_assert( false !== strpos( $failure_payload, 'pre_schedule_event_false' ), 'genuine failure reason is audited' );

// Duplicate webhook delivery of the persisted update stays a no-op.
$response = pa_wh_post( pa_wh_update( 'wh-fail-1', 'wh-fail', 'سلام تست شکست' ) );
$data = $response->get_data();
$user_rows = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'messages' ) . " WHERE conversation_id = %d AND role = 'user'", $fail_cid ) );
pa_wh_assert( ! empty( $data['duplicate'] ) && 1 === $user_rows, 'duplicate webhook delivery does not duplicate the user message' );

// Recovery answers and sends exactly once, then becomes a no-op.
$wpdb->query( $wpdb->prepare( "UPDATE " . pa_table( 'messages' ) . " SET created_at = %s WHERE conversation_id = %d AND role = 'user'", '2000-01-01 00:00:00', $fail_cid ) );
$bale_before = pa_wh_bale_calls_for( 'wh-fail' );
pa_recover_pending( 15 );
$assistant_rows = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'messages' ) . " WHERE conversation_id = %d AND role = 'assistant'", $fail_cid ) );
$bale_after = pa_wh_bale_calls_for( 'wh-fail' );
pa_wh_assert( 1 === $assistant_rows && 1 === ( $bale_after - $bale_before ), 'recovery answers and delivers the pending update once' );
pa_recover_pending( 15 );
$assistant_again = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'messages' ) . " WHERE conversation_id = %d AND role = 'assistant'", $fail_cid ) );
pa_wh_assert( 1 === $assistant_again && $bale_after === pa_wh_bale_calls_for( 'wh-fail' ), 'second recovery run is exactly a no-op' );
$wpdb->query( "DELETE FROM " . pa_table( 'processed_updates' ) . " WHERE provider = 'bale' AND update_id IN ('wh-fail-1')" );
pa_wh_cleanup_chat( 'wh-fail' );

// 3. Duplicate worker invocation and lock exclusion.
pa_wh_cleanup_chat( 'wh-lock' );
$lock_cid = $repo->find_or_create( 'bale', 'bale:wh-lock', 0 );
$repo->add_message( $lock_cid, 'user', 'سلام تست کارگر' );
$bale_before = pa_wh_bale_calls_for( 'wh-lock' );
pa_process_bale_reply( $lock_cid, 'wh-lock' );
$after_first = pa_wh_bale_calls_for( 'wh-lock' );
$assistant_rows = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'messages' ) . " WHERE conversation_id = %d AND role = 'assistant'", $lock_cid ) );
pa_wh_assert( 1 === $assistant_rows && 1 === ( $after_first - $bale_before ), 'worker answers and sends once' );
pa_process_bale_reply( $lock_cid, 'wh-lock' );
$assistant_rows = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'messages' ) . " WHERE conversation_id = %d AND role = 'assistant'", $lock_cid ) );
pa_wh_assert( 1 === $assistant_rows && $after_first === pa_wh_bale_calls_for( 'wh-lock' ), 'duplicate worker invocation is a no-op' );
pa_wh_cleanup_chat( 'wh-lock' );

// 3b. A slow answer to message A must not suppress the answer to newer message B.
pa_wh_cleanup_chat( 'wh-race' );
$race_cid = $repo->find_or_create( 'bale', 'bale:wh-race', 0 );
$user_a = $repo->add_message( $race_cid, 'user', 'پیام اول' );
$user_b = $repo->add_message( $race_cid, 'user', 'پیام دوم' );
$service = new PA_Assistant_Service();
$service->answer(
	array(
		'channel'             => 'bale',
		'external_key'        => 'bale:wh-race',
		'user_id'             => 0,
		'text'                => 'پیام اول',
		'log_user'            => false,
		'reply_to_message_id' => (int) $user_a,
	)
);
pa_process_bale_reply( $race_cid, 'wh-race' );
$assistant_rows = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'messages' ) . " WHERE conversation_id = %d AND role = 'assistant'", $race_cid ) );
$second_meta = (string) $wpdb->get_var( $wpdb->prepare( "SELECT meta FROM " . pa_table( 'messages' ) . " WHERE conversation_id = %d AND role = 'assistant' ORDER BY id DESC LIMIT 1", $race_cid ) );
pa_wh_assert( 2 === $assistant_rows && false !== strpos( $second_meta, '"reply_to":' . (int) $user_b ), 'answer to A does not suppress newer message B' );
pa_wh_cleanup_chat( 'wh-race' );

// 3c. Recovery delivers a persisted answer whose worker crashed before send.
pa_wh_cleanup_chat( 'wh-undelivered' );
$undelivered_cid = $repo->find_or_create( 'bale', 'bale:wh-undelivered', 0 );
$undelivered_mid = $repo->add_message( $undelivered_cid, 'assistant', 'پاسخ ذخیره‌شده' );
$wpdb->query( $wpdb->prepare( "UPDATE " . pa_table( 'messages' ) . " SET created_at = %s WHERE id = %d", '2000-01-01 00:00:00', $undelivered_mid ) );
$bale_before = pa_wh_bale_calls_for( 'wh-undelivered' );
pa_recover_pending( 15 );
$sent_payload = (string) $wpdb->get_var( $wpdb->prepare( "SELECT payload FROM " . pa_table( 'events' ) . " WHERE conversation_id = %d AND event = 'bale_sent' ORDER BY id DESC LIMIT 1", $undelivered_cid ) );
pa_wh_assert( 1 === ( pa_wh_bale_calls_for( 'wh-undelivered' ) - $bale_before ), 'persisted unsent answer is delivered by recovery' );
pa_wh_assert( false !== strpos( $sent_payload, '"message_id":' . $undelivered_mid ), 'recovered delivery records the exact message id' );
$bale_after = pa_wh_bale_calls_for( 'wh-undelivered' );
pa_recover_pending( 15 );
pa_wh_assert( $bale_after === pa_wh_bale_calls_for( 'wh-undelivered' ), 'second delivery recovery is a no-op' );
pa_wh_cleanup_chat( 'wh-undelivered' );

// 3c-2. Ambiguous send (started, no result) is escalated, never re-sent.
pa_wh_cleanup_chat( 'wh-ambig' );
$ambig_cid = $repo->find_or_create( 'bale', 'bale:wh-ambig', 0 );
$ambig_mid = $repo->add_message( $ambig_cid, 'assistant', 'پاسخ نامشخص' );
$wpdb->query( $wpdb->prepare( "UPDATE " . pa_table( 'messages' ) . " SET created_at = %s WHERE id = %d", '2000-01-01 00:00:00', $ambig_mid ) );
$repo->audit( $ambig_cid, 'bale_sending', array( 'attempt' => 1, 'message_id' => $ambig_mid ) );
$bale_before = pa_wh_bale_calls_for( 'wh-ambig' );
pa_recover_pending( 15 );
$ambig_status = (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM " . pa_table( 'conversations' ) . " WHERE id = %d", $ambig_cid ) );
pa_wh_assert( 0 === ( pa_wh_bale_calls_for( 'wh-ambig' ) - $bale_before ), 'ambiguous send is not re-sent by recovery' );
pa_wh_assert( 'escalated' === $ambig_status && 1 === pa_wh_audit_count( $ambig_cid, 'bale_delivery_ambiguous' ), 'ambiguous send is escalated for a human' );
pa_recover_pending( 15 );
pa_wh_assert( 1 === pa_wh_audit_count( $ambig_cid, 'bale_delivery_ambiguous' ), 'ambiguous escalation is recorded once' );
pa_wh_cleanup_chat( 'wh-ambig' );

// 3d. Outbound failure schedules a retry; the retry sends the exact answer once.
pa_wh_cleanup_chat( 'wh-retry' );
$retry_cid = $repo->find_or_create( 'bale', 'bale:wh-retry', 0 );
$repo->add_message( $retry_cid, 'user', 'سلام تست تلاش دوباره' );
$GLOBALS['pa_wh_fail_send'] = true;
pa_process_bale_reply( $retry_cid, 'wh-retry' );
$GLOBALS['pa_wh_fail_send'] = false;
$retry_mid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM " . pa_table( 'messages' ) . " WHERE conversation_id = %d AND role = 'assistant' ORDER BY id DESC LIMIT 1", $retry_cid ) );
pa_wh_assert( false !== wp_next_scheduled( 'pa_bale_retry', array( $retry_cid, 'wh-retry', 2, $retry_mid ) ), 'failed send schedules an exact-message retry' );
$bale_before = pa_wh_bale_calls_for( 'wh-retry' );
do_action( 'pa_bale_retry', $retry_cid, 'wh-retry', 2, $retry_mid );
pa_wh_assert( 1 === ( pa_wh_bale_calls_for( 'wh-retry' ) - $bale_before ), 'retry worker delivers the stored answer' );
$retry_sent = (string) $wpdb->get_var( $wpdb->prepare( "SELECT payload FROM " . pa_table( 'events' ) . " WHERE conversation_id = %d AND event = 'bale_sent' ORDER BY id DESC LIMIT 1", $retry_cid ) );
pa_wh_assert( false !== strpos( $retry_sent, '"message_id":' . $retry_mid ), 'retry success is recorded for the exact message' );
wp_clear_scheduled_hook( 'pa_bale_retry', array( $retry_cid, 'wh-retry', 2, $retry_mid ) );
pa_wh_cleanup_chat( 'wh-retry' );

// 3e. A scheduled-but-overdue worker is treated as stuck and recovered.
pa_wh_cleanup_chat( 'wh-stuck' );
$stuck_cid = $repo->find_or_create( 'bale', 'bale:wh-stuck', 0 );
$repo->add_message( $stuck_cid, 'user', 'سلام تست رویداد کهنه' );
$wpdb->query( $wpdb->prepare( "UPDATE " . pa_table( 'messages' ) . " SET created_at = %s WHERE conversation_id = %d AND role = 'user'", '2000-01-01 00:00:00', $stuck_cid ) );
wp_schedule_single_event( time() - HOUR_IN_SECONDS, 'pa_bale_reply', array( $stuck_cid, 'wh-stuck' ) );
$bale_before = pa_wh_bale_calls_for( 'wh-stuck' );
pa_recover_pending( 15 );
$assistant_rows = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'messages' ) . " WHERE conversation_id = %d AND role = 'assistant'", $stuck_cid ) );
pa_wh_assert( 1 === $assistant_rows && 1 === ( pa_wh_bale_calls_for( 'wh-stuck' ) - $bale_before ), 'overdue worker event does not block recovery' );
wp_clear_scheduled_hook( 'pa_bale_reply', array( $stuck_cid, 'wh-stuck' ) );
pa_wh_cleanup_chat( 'wh-stuck' );

pa_wh_cleanup_chat( 'wh-locked' );
$locked_cid = $repo->find_or_create( 'bale', 'bale:wh-locked', 0 );
$repo->add_message( $locked_cid, 'user', 'سلام تست قفل' );
add_option( 'pa_worker_lock_' . $locked_cid, time(), '', false );
pa_process_bale_reply( $locked_cid, 'wh-locked' );
$assistant_rows = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'messages' ) . " WHERE conversation_id = %d AND role = 'assistant'", $locked_cid ) );
pa_wh_assert( 0 === $assistant_rows && 1 === pa_wh_audit_count( $locked_cid, 'bale_worker_locked' ), 'overlapping worker is excluded by the lock' );
delete_option( 'pa_worker_lock_' . $locked_cid );
pa_process_bale_reply( $locked_cid, 'wh-locked' );
$assistant_rows = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'messages' ) . " WHERE conversation_id = %d AND role = 'assistant'", $locked_cid ) );
pa_wh_assert( 1 === $assistant_rows, 'worker proceeds after the lock is released' );
pa_wh_cleanup_chat( 'wh-locked' );

// 4. /start link confirmation is deferred, never sent inside the webhook.
$link_user = wp_create_user( 'pa_wh_link', wp_generate_password( 12, false ), 'pa_wh_link@example.com' );
$link_token = pa_link_token_create( $link_user );
pa_wh_cleanup_chat( 'wh-link' );
$bale_before = pa_wh_bale_calls_for( 'wh-link' );
$other_before = count( $GLOBALS['pa_wh_other_calls'] );
$response = pa_wh_post( pa_wh_update( 'wh-link-1', 'wh-link', '/start link_' . $link_token ) );
$data = $response->get_data();
$link_cid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM " . pa_table( 'conversations' ) . " WHERE channel = 'bale' AND external_key = %s", 'bale:wh-link' ) );
pa_wh_assert( 200 === $response->get_status() && ! empty( $data['ok'] ), 'link webhook acknowledges promptly' );
pa_wh_assert( 0 === ( pa_wh_bale_calls_for( 'wh-link' ) - $bale_before ), 'link confirmation is not sent inside the webhook' );
pa_wh_assert( $other_before === count( $GLOBALS['pa_wh_other_calls'] ), 'link webhook performs no AI call' );
$link_msg = $wpdb->get_row(
	$wpdb->prepare( "SELECT id, text FROM " . pa_table( 'messages' ) . " WHERE conversation_id = %d AND role = 'assistant' ORDER BY id DESC LIMIT 1", $link_cid ),
	ARRAY_A
);
pa_wh_assert( $link_msg && false !== wp_next_scheduled( 'pa_bale_retry', array( $link_cid, 'wh-link', 1, (int) $link_msg['id'] ) ), 'link confirmation is persisted and queued' );
do_action( 'pa_bale_retry', $link_cid, 'wh-link', 1, (int) $link_msg['id'] );
pa_wh_assert( 1 === ( pa_wh_bale_calls_for( 'wh-link' ) - $bale_before ), 'queued link confirmation delivers on the worker' );
$wpdb->query( "DELETE FROM " . pa_table( 'processed_updates' ) . " WHERE provider = 'bale' AND update_id IN ('wh-link-1')" );
pa_wh_cleanup_chat( 'wh-link' );
$wpdb->delete( pa_table( 'link_tokens' ), array( 'wp_user_id' => $link_user ) );
wp_delete_user( $link_user );

WP_CLI::log( '' );
WP_CLI::log( 'RESULT: ' . $GLOBALS['pa_wh_pass'] . ' passed, ' . $GLOBALS['pa_wh_fail'] . ' failed.' );
exit( $GLOBALS['pa_wh_fail'] > 0 ? 1 : 0 );
