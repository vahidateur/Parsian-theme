<?php
/**
 * Security acceptance tests for audit fixes.
 * Run: wp eval-file tests/pa-security-test.php
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}
$GLOBALS['pa_sec_pass'] = 0;
$GLOBALS['pa_sec_fail'] = 0;
function pa_sec_assert( $condition, $label ) {
	if ( $condition ) {
		$GLOBALS['pa_sec_pass']++;
		WP_CLI::log( 'PASS: ' . $label );
	} else {
		$GLOBALS['pa_sec_fail']++;
		WP_CLI::warning( 'FAIL: ' . $label );
	}
}

global $wpdb;
$GLOBALS['pa_sec_mode'] = 'success';
add_filter(
	'pre_http_request',
	function ( $pre, $args, $url ) {
		if ( false === strpos( (string) $url, 'tapi.bale.ai' ) && false === strpos( (string) $url, 'sendMessage' ) && false === strpos( (string) $url, 'getMe' ) && false === strpos( (string) $url, 'getUpdates' ) ) {
			return $pre;
		}
		if ( 'fail-send' === $GLOBALS['pa_sec_mode'] && false !== strpos( (string) $url, 'sendMessage' ) ) {
			return new WP_Error( 'mock', 'mock failure' );
		}
		return array(
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'body'     => wp_json_encode( array( 'ok' => true, 'result' => array( 'message_id' => 1 ) ) ),
			'headers'  => array(),
			'cookies'  => array(),
		);
	},
	10,
	3
);
set_theme_mod( 'pm_bale_token', 'sec-test-token' );
set_theme_mod( 'pm_bale_chat_id', '4242' );

function pa_sec_cleanup_chat( $chat_id ) {
	global $wpdb;
	$cid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM " . pa_table( 'conversations' ) . " WHERE channel = %s AND external_key = %s", 'bale', 'bale:' . $chat_id ) );
	if ( $cid ) {
		$wpdb->delete( pa_table( 'messages' ), array( 'conversation_id' => $cid ) );
		$wpdb->delete( pa_table( 'events' ), array( 'conversation_id' => $cid ) );
		$wpdb->delete( pa_table( 'conversations' ), array( 'id' => $cid ) );
	}
	$wpdb->query( "DELETE FROM " . pa_table( 'processed_updates' ) . " WHERE provider = 'bale' AND update_id LIKE 'sec-%'" );
}

// 1. No double user-message logging on ingest.
pa_sec_cleanup_chat( '9001' );
pa_ingest_bale_update( array( 'update_id' => 'sec-1', 'chat_id' => '9001', 'text' => 'سلام', 'from_id' => '9001' ) );
$cid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM " . pa_table( 'conversations' ) . " WHERE channel = %s AND external_key = %s", 'bale', 'bale:9001' ) );
$user_msgs = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'messages' ) . " WHERE conversation_id = %d AND role = 'user'", $cid ) );
pa_sec_assert( 1 === $user_msgs, 'ingest stores exactly one user message' );
pa_sec_cleanup_chat( '9001' );

// 2. Webhook fast path persists + defers (no AI inline).
pa_sec_cleanup_chat( '9002' );
$parsed = array( 'update_id' => 'sec-2', 'chat_id' => '9002', 'text' => 'سوال', 'from_id' => '9002' );
pa_claim_update( 'sec-2' );
$stored_cid = pa_store_bale_update( $parsed );
pa_sec_assert( $stored_cid > 0, 'webhook fast path persists' );
pa_sec_assert( false !== wp_next_scheduled( 'pa_bale_reply', array( $stored_cid, '9002' ) ), 'AI work deferred to cron' );
$user_msgs2 = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'messages' ) . " WHERE conversation_id = %d AND role = 'user'", $stored_cid ) );
pa_sec_assert( 1 === $user_msgs2, 'fast path stores exactly one user message' );
wp_clear_scheduled_hook( 'pa_bale_reply', array( $stored_cid, '9002' ) );
pa_sec_cleanup_chat( '9002' );

// 3. Per-chat rate limit.
pa_sec_cleanup_chat( '9003' );
$limited_at = 0;
for ( $i = 0; $i < 31; $i++ ) {
	if ( pa_bale_rate_limited( '9003' ) ) {
		$limited_at = $i + 1;
		break;
	}
}
pa_sec_assert( 31 === $limited_at, '31st answer in an hour is rate-limited' );
delete_transient( 'pa_rl_bale_' . md5( '9003' ) );
pa_sec_assert( false === pa_bale_rate_limited( '9003' ), 'limit resets after window clear' );
delete_transient( 'pa_rl_bale_' . md5( '9003' ) );
pa_sec_cleanup_chat( '9003' );

// 4. Outbound retry scheduled on failure, none on success.
pa_sec_cleanup_chat( '9004' );
$repo = new PA_Conversation_Repository();
$rcid = $repo->find_or_create( 'bale', 'bale:9004', 0 );
$GLOBALS['pa_sec_mode'] = 'fail-send';
pa_queue_bale_reply( '9004', 'retry me', $rcid, 1 );
pa_sec_assert( false !== wp_next_scheduled( 'pa_bale_retry', array( $rcid, '9004', 2, 0 ) ), 'failed send schedules retry' );
$audit_fail = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'events' ) . " WHERE conversation_id = %d AND event = 'bale_failed'", $rcid ) );
pa_sec_assert( 1 === $audit_fail, 'failure audited with attempt number' );
$GLOBALS['pa_sec_mode'] = 'success';
wp_clear_scheduled_hook( 'pa_bale_retry', array( $rcid, '9004', 2, 0 ) );
pa_sec_cleanup_chat( '9004' );

// 5. Slug sanitizer preserves case (admin migration path).
pa_sec_assert( 'parsian-music-FLUXabc123' === pa_sanitize_slug( 'parsian-music-FLUXabc123' ), 'legacy slug case preserved' );
$evil = pa_sanitize_slug( '../../etc/passwd' );
pa_sec_assert( false === strpos( $evil, '/' ) && false === strpos( $evil, '.' ), 'path traversal stripped' );

// 6. Link token cap.
$cap_user = wp_create_user( 'pa_sectest', wp_generate_password( 12, false ), 'pa_sectest@example.com' );
$ok_count = 0;
for ( $i = 0; $i < 6; $i++ ) {
	$t = pa_link_token_create( $cap_user );
	if ( ! is_wp_error( $t ) ) {
		$ok_count++;
	}
}
pa_sec_assert( 5 === $ok_count, '6th active token rejected (cap 5)' );

// 7. Prune removes metadata, keeps conversations by default.
$wpdb->insert( pa_table( 'link_tokens' ), array( 'token_hash' => 'oldhash', 'wp_user_id' => $cap_user, 'expires_at' => '2000-01-01 00:00:00', 'used_at' => null, 'created_at' => '2000-01-01 00:00:00' ), array( '%s', '%d', '%s', '%s', '%s' ) );
$wpdb->insert( pa_table( 'processed_updates' ), array( 'provider' => 'bale', 'update_id' => 'sec-old', 'created_at' => '2000-01-01 00:00:00' ), array( '%s', '%s', '%s' ) );
pa_prune();
$left_tokens = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'link_tokens' ) . " WHERE token_hash = %s", 'oldhash' ) );
$left_updates = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'processed_updates' ) . " WHERE update_id = %s", 'sec-old' ) );
pa_sec_assert( 0 === $left_tokens && 0 === $left_updates, 'expired tokens and old update ids pruned' );

// 8. User deletion detaches conversations and drops tokens.
$chat_repo = new PA_Conversation_Repository();
$link_cid = $chat_repo->find_or_create( 'bale', 'bale:9005', $cap_user );
wp_delete_user( $cap_user );
$detached = (int) $wpdb->get_var( $wpdb->prepare( "SELECT wp_user_id FROM " . pa_table( 'conversations' ) . " WHERE id = %d", $link_cid ) );
$tokens_left = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'link_tokens' ) . " WHERE wp_user_id = %d", $cap_user ) );
pa_sec_assert( 0 === $detached, 'deleted user detached from conversations' );
pa_sec_assert( 0 === $tokens_left, 'deleted user tokens removed' );
$wpdb->delete( pa_table( 'messages' ), array( 'conversation_id' => $link_cid ) );
$wpdb->delete( pa_table( 'events' ), array( 'conversation_id' => $link_cid ) );
$wpdb->delete( pa_table( 'conversations' ), array( 'id' => $link_cid ) );

remove_theme_mod( 'pm_bale_token' );
remove_theme_mod( 'pm_bale_chat_id' );

WP_CLI::log( '' );
WP_CLI::log( 'RESULT: ' . $GLOBALS['pa_sec_pass'] . ' passed, ' . $GLOBALS['pa_sec_fail'] . ' failed.' );
exit( $GLOBALS['pa_sec_fail'] > 0 ? 1 : 0 );
