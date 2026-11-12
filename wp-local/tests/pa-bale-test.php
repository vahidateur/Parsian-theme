<?php
/**
 * Bale adapter tests (mocked HTTP, no network, no real token).
 * Run: wp eval-file tests/pa-bale-test.php
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}
$GLOBALS['pa_bale_pass'] = 0;
$GLOBALS['pa_bale_fail'] = 0;
function pa_bale_assert( $condition, $label ) {
	if ( $condition ) {
		$GLOBALS['pa_bale_pass']++;
		WP_CLI::log( 'PASS: ' . $label );
	} else {
		$GLOBALS['pa_bale_fail']++;
		WP_CLI::warning( 'FAIL: ' . $label );
	}
}

$mock_calls = array();
$GLOBALS['pa_bale_mock_mode'] = 'success';
set_theme_mod( 'pm_bale_token', 'test-token-for-mock' );
set_theme_mod( 'pm_bale_chat_id', '4242' );
add_filter(
	'pre_http_request',
	function ( $pre, $args, $url ) use ( &$mock_calls ) {
		$mock_calls[] = $url;
		$mode = isset( $GLOBALS['pa_bale_mock_mode'] ) ? $GLOBALS['pa_bale_mock_mode'] : 'success';
		if ( false !== strpos( $url, 'getMe' ) ) {
			if ( 'timeout' === $mode ) {
				return new WP_Error( 'mock_timeout', 'timeout' );
			}
			if ( 'unauthorized' === $mode ) {
				return array(
					'response' => array( 'code' => 401, 'message' => 'Unauthorized' ),
					'body'     => wp_json_encode( array( 'ok' => false, 'description' => 'Unauthorized' ) ),
					'headers'  => array(),
					'cookies'  => array(),
				);
			}
			$result = array( 'id' => 1 );
			if ( 'no_username' !== $mode ) {
				$result['username'] = 'parsiantestbot';
			}
			return array(
				'response' => array( 'code' => 200, 'message' => 'OK' ),
				'body'     => wp_json_encode( array( 'ok' => true, 'result' => $result ) ),
				'headers'  => array(),
				'cookies'  => array(),
			);
		}
		if ( false !== strpos( $url, 'sendMessage' ) ) {
			return array(
				'response' => array( 'code' => 200, 'message' => 'OK' ),
				'body'     => wp_json_encode( array( 'ok' => true, 'result' => array( 'message_id' => 7 ) ) ),
				'headers'  => array(),
				'cookies'  => array(),
			);
		}
		if ( false !== strpos( $url, 'getUpdates' ) ) {
			return array(
				'response' => array( 'code' => 200, 'message' => 'OK' ),
				'body'     => wp_json_encode(
					array(
						'ok'     => true,
						'result' => array(
							array( 'update_id' => 5001, 'message' => array( 'text' => 'سلام', 'chat' => array( 'id' => 4242 ), 'from' => array( 'id' => 4242 ) ) ),
						),
					)
				),
				'headers'  => array(),
				'cookies'  => array(),
			);
		}
		if ( false !== strpos( $url, 'setWebhook' ) ) {
			return array(
				'response' => array( 'code' => 200, 'message' => 'OK' ),
				'body'     => wp_json_encode( array( 'ok' => true, 'result' => true ) ),
				'headers'  => array(),
				'cookies'  => array(),
			);
		}
		if ( false !== strpos( $url, 'getWebhookInfo' ) ) {
			return array(
				'response' => array( 'code' => 200, 'message' => 'OK' ),
				'body'     => wp_json_encode( array( 'ok' => true, 'result' => array( 'url' => 'https://example.com/hook', 'pending_update_count' => 0 ) ) ),
				'headers'  => array(),
				'cookies'  => array(),
			);
		}
		return $pre;
	},
	10,
	3
);

// Adapter against the theme transport is unavailable here unless the theme
// is active with a token; use injectable HTTP to stay hermetic.
$http = function ( $url, $args ) {
	return apply_filters( 'pre_http_request', false, $args, $url );
};
$adapter = new PA_Bale_Adapter( $http );

$me = $adapter->get_me();
pa_bale_assert( ! empty( $me['success'] ), 'mocked getMe succeeds' );

$send = $adapter->send( '4242', 'سلام تست' );
pa_bale_assert( ! empty( $send['success'] ), 'mocked sendMessage succeeds' );

$updates = $adapter->get_updates( 0 );
pa_bale_assert( ! empty( $updates['success'] ) && 1 === count( $updates['body']['result'] ), 'mocked getUpdates parsed' );

$hook = $adapter->set_webhook( 'https://example.com/hook' );
pa_bale_assert( ! empty( $hook['success'] ), 'mocked setWebhook succeeds' );

// Payload parsing: valid, invalid, missing chat.
$parsed = $adapter->parse_incoming(
	array( 'update_id' => 5001, 'message' => array( 'text' => 'سلام', 'chat' => array( 'id' => 4242 ), 'from' => array( 'id' => 4242 ) ) )
);
pa_bale_assert( $parsed && '4242' === $parsed['chat_id'] && 'سلام' === $parsed['text'], 'valid payload parsed' );
pa_bale_assert( null === $adapter->parse_incoming( array( 'nope' => 1 ) ), 'invalid payload rejected' );
pa_bale_assert( null === $adapter->parse_incoming( array( 'update_id' => 9, 'message' => array( 'text' => 'x' ) ) ), 'missing chat rejected' );
pa_bale_assert( null === $adapter->parse_incoming( array( 'update_id' => 9, 'message' => array( 'chat' => array( 'id' => 1 ) ) ) ), 'non-text update ignored' );

// Bot username cache + cached status (no live HTTP on routine reads).
global $wpdb;
$repo = new PA_Conversation_Repository();
delete_option( 'pa_bale_bot_username' );
delete_option( 'pa_bale_status_cache' );
delete_option( 'pa_bale_identity_checked' );
pa_bale_assert( '' === pa_bale_bot_username( false ), 'empty username cache is reported without live HTTP' );
$mock_calls = array();
$a_status = pa_bale_status_refresh();
pa_bale_assert( ! empty( $a_status['getme_ok'] ) && 'parsiantestbot' === $a_status['username'], 'explicit refresh caches the validated bot username' );
pa_bale_assert( 'parsiantestbot' === pa_bale_bot_username( false ), 'cached username is available to the popup' );
$calls_after_refresh = count( $mock_calls );
$cached_status = pa_bale_status_cached();
pa_bale_assert( $calls_after_refresh === count( $mock_calls ), 'routine status read performs no HTTP calls' );
pa_bale_assert( false === strpos( wp_json_encode( $cached_status ), 'test-token-for-mock' ), 'status cache never contains the bot token' );

// Popup localizes the Bale row once the cache is populated.
wp_enqueue_script( 'pm-ai-chat', 'pm-ai-chat', array(), '1', true );
pa_popup_assets();
$popup_data = (string) wp_scripts()->get_data( 'pa-popup', 'data' );
pa_bale_assert( false !== strpos( $popup_data, 'paBale' ) && false !== strpos( $popup_data, 'parsiantestbot' ), 'popup localizes the Bale row after cache population' );

// Invalid token clears the cache; a timeout keeps the last known value.
$GLOBALS['pa_bale_mock_mode'] = 'unauthorized';
$bad_status = pa_bale_status_refresh();
pa_bale_assert( empty( $bad_status['getme_ok'] ) && '' === pa_bale_bot_username( false ), 'invalid token clears the cached username safely' );
$GLOBALS['pa_bale_mock_mode'] = 'no_username';
$missing_status = pa_bale_status_refresh();
pa_bale_assert( empty( $missing_status['username'] ) && '' === pa_bale_bot_username( false ), 'missing username produces an unavailable state' );
$GLOBALS['pa_bale_mock_mode'] = 'success';
pa_bale_status_refresh();
$GLOBALS['pa_bale_mock_mode'] = 'timeout';
pa_bale_status_refresh();
pa_bale_assert( 'parsiantestbot' === pa_bale_bot_username( false ), 'network timeout keeps the last known username' );
$GLOBALS['pa_bale_mock_mode'] = 'success';

// Escalated conversations stay human-owned and visible to the operator queue.
$wpdb->delete( pa_table( 'messages' ), array( 'conversation_id' => 0 ) );
$op_cid = $repo->find_or_create( 'bale', 'bale:bale-op', 0 );
$repo->set_status( $op_cid, 'escalated' );
$op_mid = $repo->add_message( $op_cid, 'user', 'پیام نیازمند اپراتور' );
$op_queue = pa_operator_queue();
$op_found = false;
foreach ( $op_queue as $job ) {
	if ( $job['conversation_id'] === $op_cid && $job['message_id'] === $op_mid ) {
		$op_found = true;
		break;
	}
}
pa_bale_assert( $op_found, 'escalated user message appears in the operator queue' );
$open_pending = pa_pending_replies( 0 );
$op_in_ai_queue = false;
foreach ( $open_pending as $job ) {
	if ( $job['conversation_id'] === $op_cid ) {
		$op_in_ai_queue = true;
		break;
	}
}
pa_bale_assert( ! $op_in_ai_queue, 'escalated conversation is excluded from automatic AI recovery' );
$bale_before = count( $mock_calls );
pa_process_bale_reply( $op_cid, 'bale-op' );
$op_assistant = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'messages' ) . " WHERE conversation_id = %d AND role = 'assistant'", $op_cid ) );
pa_bale_assert( 0 === $op_assistant && $bale_before === count( $mock_calls ), 'escalated worker produces no AI reply and no outbound call' );
pa_recover_pending( 0 );
pa_recover_pending( 0 );
$op_events = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'events' ) . " WHERE conversation_id = %d AND event = 'operator_pending' AND payload LIKE %s", $op_cid, '%"message_id":' . $op_mid . '%' ) );
pa_bale_assert( 1 === $op_events, 'operator queue marking is idempotent across recovery runs' );
$op_status = (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM " . pa_table( 'conversations' ) . " WHERE id = %d", $op_cid ) );
pa_bale_assert( 'escalated' === $op_status, 'human handoff state is preserved' );

// Scheduling failure in an escalated conversation leaves a visible message.
$wpdb->delete( pa_table( 'processed_updates' ), array( 'provider' => 'bale', 'update_id' => 'bale-op-fail' ) );
$fail_cid = $repo->find_or_create( 'bale', 'bale:bale-op-fail', 0 );
$repo->set_status( $fail_cid, 'escalated' );
$GLOBALS['pa_bale_block_schedule'] = true;
add_filter(
	'pre_schedule_event',
	function ( $pre, $event, $wp_error ) {
		if ( ! empty( $GLOBALS['pa_bale_block_schedule'] ) && isset( $event->hook ) && 'pa_bale_reply' === $event->hook ) {
			return false;
		}
		return $pre;
	},
	10,
	3
);
$stored = pa_store_bale_update( array( 'update_id' => 'bale-op-fail', 'chat_id' => 'bale-op-fail', 'text' => 'پیام بدون زمان‌بند', 'from_id' => 'bale-op-fail' ) );
$GLOBALS['pa_bale_block_schedule'] = false;
pa_bale_assert( is_int( $stored ) && $stored === $fail_cid, 'failed scheduling still persists an escalated message' );
$fail_queue = pa_operator_queue();
$fail_visible = false;
foreach ( $fail_queue as $job ) {
	if ( $job['conversation_id'] === $fail_cid ) {
		$fail_visible = true;
		break;
	}
}
pa_bale_assert( $fail_visible, 'failed scheduling never hides the escalated message' );
$fail_assistant = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'messages' ) . " WHERE conversation_id = %d AND role = 'assistant'", $fail_cid ) );
pa_bale_assert( 0 === $fail_assistant, 'failed scheduling does not trigger an automatic reply' );

foreach ( array( $op_cid => 'bale-op', $fail_cid => 'bale-op-fail' ) as $cid => $chat ) {
	$wpdb->delete( pa_table( 'messages' ), array( 'conversation_id' => $cid ) );
	$wpdb->delete( pa_table( 'events' ), array( 'conversation_id' => $cid ) );
	$wpdb->delete( pa_table( 'conversations' ), array( 'id' => $cid ) );
	delete_option( 'pa_worker_lock_' . $cid );
	delete_transient( 'pa_rl_bale_' . md5( $chat ) );
	$wpdb->delete( pa_table( 'processed_updates' ), array( 'provider' => 'bale', 'update_id' => 'bale-op-fail' ) );
}
delete_option( 'pa_bale_bot_username' );
delete_option( 'pa_bale_status_cache' );
delete_option( 'pa_bale_identity_checked' );

// Update dedupe.
global $wpdb;
$wpdb->query( "DELETE FROM " . pa_table( 'processed_updates' ) . " WHERE provider = 'bale' AND update_id IN ('901','902')" );
pa_bale_assert( true === pa_claim_update( '901' ), 'first claim accepted' );
pa_bale_assert( false === pa_claim_update( '901' ), 'duplicate claim rejected' );
pa_bale_assert( true === pa_claim_update( '902' ), 'distinct id accepted' );
$wpdb->query( "DELETE FROM " . pa_table( 'processed_updates' ) . " WHERE provider = 'bale' AND update_id IN ('901','902')" );

// Unconfigured adapter refuses without leaking.
remove_theme_mod( 'pm_bale_token' );
remove_theme_mod( 'pm_bale_chat_id' );
$bare = new PA_Bale_Adapter(
	function () {
		return new WP_Error( 'x', 'must not be called' );
	}
);
if ( ! defined( 'PA_BALE_TOKEN' ) ) {
	$no_token = $bare->call( 'getMe' );
	pa_bale_assert( empty( $no_token['success'] ), 'unconfigured adapter refuses safely' );
} else {
	pa_bale_assert( true, 'token constant defined; skip unconfigured check' );
}

WP_CLI::log( '' );
WP_CLI::log( 'RESULT: ' . $GLOBALS['pa_bale_pass'] . ' passed, ' . $GLOBALS['pa_bale_fail'] . ' failed.' );
exit( $GLOBALS['pa_bale_fail'] > 0 ? 1 : 0 );
