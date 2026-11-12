<?php
/**
 * Durability + privacy acceptance tests.
 * Run: wp eval-file tests/pa-durability-test.php
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}
$GLOBALS['pa_dur_pass'] = 0;
$GLOBALS['pa_dur_fail'] = 0;
function pa_dur_assert( $condition, $label ) {
	if ( $condition ) {
		$GLOBALS['pa_dur_pass']++;
		WP_CLI::log( 'PASS: ' . $label );
	} else {
		$GLOBALS['pa_dur_fail']++;
		WP_CLI::warning( 'FAIL: ' . $label );
	}
}

global $wpdb;
$GLOBALS['pa_dur_bale_calls'] = 0;
add_filter(
	'pre_http_request',
	function ( $pre, $args, $url ) {
		if ( false === strpos( (string) $url, 'tapi.bale.ai' ) ) {
			return $pre;
		}
		$GLOBALS['pa_dur_bale_calls']++;
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
set_theme_mod( 'pm_bale_token', 'dur-test-token' );

function pa_dur_cleanup_chat( $chat_key ) {
	global $wpdb;
	$cid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM " . pa_table( 'conversations' ) . " WHERE channel = %s AND external_key = %s", 'bale', $chat_key ) );
	if ( $cid ) {
		$wpdb->delete( pa_table( 'messages' ), array( 'conversation_id' => $cid ) );
		$wpdb->delete( pa_table( 'events' ), array( 'conversation_id' => $cid ) );
		$wpdb->delete( pa_table( 'conversations' ), array( 'id' => $cid ) );
	}
	return $cid;
}

// 1. Concurrent duplicate claim: loser is rejected even on race.
$wpdb->query( "DELETE FROM " . pa_table( 'processed_updates' ) . " WHERE provider = 'bale' AND update_id IN ('dur-race')" );
pa_dur_assert( true === pa_claim_update( 'dur-race' ), 'first claim wins' );
pa_dur_assert( false === pa_claim_update( 'dur-race' ), 'concurrent duplicate rejected' );
$wpdb->query( "DELETE FROM " . pa_table( 'processed_updates' ) . " WHERE provider = 'bale' AND update_id IN ('dur-race')" );

// 2. Duplicate worker is treated as queued, never executed inline.
pa_dur_cleanup_chat( 'bale:dur-sched' );
$parsed = array( 'update_id' => 'dur-sched', 'chat_id' => 'dur-sched', 'text' => 'تست زمان‌بندی', 'from_id' => 'dur-sched' );
$repo = new PA_Conversation_Repository();
$cid = $repo->find_or_create( 'bale', 'bale:dur-sched', 0 );
wp_schedule_single_event( time(), 'pa_bale_reply', array( $cid, 'dur-sched' ) );
$bale_before = $GLOBALS['pa_dur_bale_calls'];
$queued = pa_schedule_bale_worker( 'pa_bale_reply', array( $cid, 'dur-sched' ) );
pa_dur_assert( 'queued' === $queued['status'], 'duplicate event is classified as already queued' );
pa_dur_assert( 'duplicate_event' === $queued['code'], 'duplicate classification uses WP error information' );
$assistant_before = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'messages' ) . " WHERE conversation_id = %d AND role = 'assistant'", $cid ) );
pa_dur_assert( 0 === $assistant_before, 'duplicate scheduling does not execute the worker inline' );
pa_dur_assert( $bale_before === $GLOBALS['pa_dur_bale_calls'], 'duplicate scheduling performs no outbound call' );
wp_clear_scheduled_hook( 'pa_bale_reply', array( $cid, 'dur-sched' ) );
pa_dur_cleanup_chat( 'bale:dur-sched' );

// Genuine scheduling failure: persisted + audited + recoverable, no inline work.
pa_dur_cleanup_chat( 'bale:dur-fail' );
$parsed = array( 'update_id' => 'dur-fail', 'chat_id' => 'dur-fail', 'text' => 'تست شکست زمان‌بندی', 'from_id' => 'dur-fail' );
pa_claim_update( 'dur-fail' );
$GLOBALS['pa_dur_block_schedule'] = true;
add_filter(
	'pre_schedule_event',
	function ( $pre, $event, $wp_error ) {
		if ( ! empty( $GLOBALS['pa_dur_block_schedule'] ) && isset( $event->hook ) && 'pa_bale_reply' === $event->hook ) {
			return false;
		}
		return $pre;
	},
	10,
	3
);
$bale_before = $GLOBALS['pa_dur_bale_calls'];
$stored = pa_store_bale_update( $parsed );
$GLOBALS['pa_dur_block_schedule'] = false;
pa_dur_assert( is_int( $stored ) && $stored > 0, 'failed scheduling still persists the update' );
$user_rows = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'messages' ) . " WHERE conversation_id = %d AND role = 'user'", $stored ) );
$assistant_rows = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'messages' ) . " WHERE conversation_id = %d AND role = 'assistant'", $stored ) );
pa_dur_assert( 1 === $user_rows && 0 === $assistant_rows, 'genuine failure leaves one persisted user message and no inline answer' );
pa_dur_assert( $bale_before === $GLOBALS['pa_dur_bale_calls'], 'genuine failure performs no outbound call in the webhook path' );
$failure_payload = (string) $wpdb->get_var( $wpdb->prepare( "SELECT payload FROM " . pa_table( 'events' ) . " WHERE conversation_id = %d AND event = 'bale_schedule_failed' ORDER BY id DESC LIMIT 1", $stored ) );
pa_dur_assert( false !== strpos( $failure_payload, 'pre_schedule_event_false' ), 'genuine failure reason is audited' );
pa_dur_assert( false === wp_next_scheduled( 'pa_bale_reply', array( $stored, 'dur-fail' ) ), 'failed worker is not left half-scheduled' );

// Recovery answers the pending update exactly once.
$wpdb->query( $wpdb->prepare( "UPDATE " . pa_table( 'messages' ) . " SET created_at = %s WHERE conversation_id = %d AND role = 'user'", '2000-01-01 00:00:00', $stored ) );
$bale_before = $GLOBALS['pa_dur_bale_calls'];
pa_recover_pending( 15 );
$assistant_after = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'messages' ) . " WHERE conversation_id = %d AND role = 'assistant'", $stored ) );
$bale_after = $GLOBALS['pa_dur_bale_calls'];
pa_dur_assert( 1 === $assistant_after && 1 === ( $bale_after - $bale_before ), 'recovery answers and sends once' );
pa_recover_pending( 15 );
$assistant_again = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'messages' ) . " WHERE conversation_id = %d AND role = 'assistant'", $stored ) );
pa_dur_assert( 1 === $assistant_again && $bale_after === $GLOBALS['pa_dur_bale_calls'], 'second recovery run is a no-op' );
$wpdb->query( "DELETE FROM " . pa_table( 'processed_updates' ) . " WHERE provider = 'bale' AND update_id IN ('dur-fail')" );
pa_dur_cleanup_chat( 'bale:dur-fail' );

// 3. Recovery worker redrives stale unanswered updates.
pa_dur_cleanup_chat( 'bale:dur-rec' );
$rcid = $repo->find_or_create( 'bale', 'bale:dur-rec', 0 );
$repo->add_message( $rcid, 'user', 'پیام بی‌پاسخ' );
// Backdate the user message past the staleness cutoff.
$wpdb->query( $wpdb->prepare( "UPDATE " . pa_table( 'messages' ) . " SET created_at = %s WHERE conversation_id = %d AND role = 'user'", '2000-01-01 00:00:00', $rcid ) );
$pending = pa_pending_replies( 15 );
$found = false;
foreach ( $pending as $job ) {
	if ( $job['conversation_id'] === $rcid ) {
		$found = true;
		break;
	}
}
pa_dur_assert( $found, 'stale unanswered update is discoverable' );
$recovered = pa_recover_pending( 15 );
pa_dur_assert( $recovered >= 1, 'recovery worker processes pending updates' );
$assistant_rec = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'messages' ) . " WHERE conversation_id = %d AND role = 'assistant'", $rcid ) );
pa_dur_assert( 1 === $assistant_rec, 'recovered update gets exactly one reply' );
pa_dur_cleanup_chat( 'bale:dur-rec' );

// 4. Rate-limited drops are excluded from recovery.
pa_dur_cleanup_chat( 'bale:dur-rl' );
$rlcid = $repo->find_or_create( 'bale', 'bale:dur-rl', 0 );
$repo->add_message( $rlcid, 'user', 'spam' );
$wpdb->query( $wpdb->prepare( "UPDATE " . pa_table( 'messages' ) . " SET created_at = %s WHERE conversation_id = %d AND role = 'user'", '2000-01-01 00:00:00', $rlcid ) );
$repo->audit( $rlcid, 'bale_rate_limited', array() );
$pending2 = pa_pending_replies( 15 );
$rl_found = false;
foreach ( $pending2 as $job ) {
	if ( $job['conversation_id'] === $rlcid ) {
		$rl_found = true;
		break;
	}
}
pa_dur_assert( ! $rl_found, 'rate-limited drops are not recovered' );
pa_dur_cleanup_chat( 'bale:dur-rl' );

// 5. Exporter + eraser.
$exp_user = wp_create_user( 'pa_durtest', wp_generate_password( 12, false ), 'pa_durtest@example.com' );
$exp_cid = $repo->find_or_create( 'bale', 'bale:dur-exp', $exp_user );
$repo->add_message( $exp_cid, 'user', 'متن شخصی' );
$repo->add_message( $exp_cid, 'assistant', 'پاسخ' );
$export = pa_data_exporter( 'pa_durtest@example.com' );
pa_dur_assert( ! empty( $export['data'] ) && false !== strpos( $export['data'][0]['data'][2]['value'], 'متن شخصی' ), 'exporter returns user transcript' );
$erased = pa_data_eraser( 'pa_durtest@example.com' );
pa_dur_assert( ! empty( $erased['items_removed'] ), 'eraser removes user data' );
$gone = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'conversations' ) . " WHERE id = %d", $exp_cid ) );
pa_dur_assert( 0 === $gone, 'erased conversation fully removed' );
wp_delete_user( $exp_user );

// 6. Retention policy: closed old conversations pruned, open kept.
update_option( 'pa_retention_days', 1, false );
$old_cid = $repo->find_or_create( 'bale', 'bale:dur-old', 0 );
$repo->add_message( $old_cid, 'user', 'قدیمی' );
$repo->set_status( $old_cid, 'closed' );
$wpdb->query( $wpdb->prepare( "UPDATE " . pa_table( 'conversations' ) . " SET updated_at = %s WHERE id = %d", '2000-01-01 00:00:00', $old_cid ) );
$open_cid = $repo->find_or_create( 'bale', 'bale:dur-open', 0 );
$repo->add_message( $open_cid, 'user', 'باز' );
$wpdb->query( $wpdb->prepare( "UPDATE " . pa_table( 'conversations' ) . " SET updated_at = %s WHERE id = %d", '2000-01-01 00:00:00', $open_cid ) );
pa_prune();
$old_gone = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'conversations' ) . " WHERE id = %d", $old_cid ) );
$open_kept = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'conversations' ) . " WHERE id = %d", $open_cid ) );
pa_dur_assert( 0 === $old_gone, 'closed conversation past retention is pruned' );
pa_dur_assert( 1 === $open_kept, 'open conversation is never pruned by retention' );
delete_option( 'pa_retention_days' );
pa_dur_cleanup_chat( 'bale:dur-old' );
pa_dur_cleanup_chat( 'bale:dur-open' );

// 7. delete_user performs documented immediate erasure.
$du_email = 'pa_dutest_' . wp_generate_password( 6, false, false ) . '@example.com';
$du_user = wp_create_user( 'pa_dutest_' . wp_generate_password( 6, false, false ), wp_generate_password( 12, false ), $du_email );
$du_cid = $repo->find_or_create( 'bale', 'bale:dur-du', $du_user );
$repo->add_message( $du_cid, 'user', 'حساب حذف‌شونده' );
wp_delete_user( $du_user );
$du_conv = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'conversations' ) . " WHERE id = %d", $du_cid ) );
$du_text = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'messages' ) . " WHERE conversation_id = %d", $du_cid ) );
$du_map = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'privacy_subjects' ) . " WHERE wp_user_id = %d", $du_user ) );
pa_dur_assert( 0 === $du_conv && 0 === $du_text && 0 === $du_map, 'deleted user data is erased immediately' );
pa_dur_cleanup_chat( 'bale:dur-du' );

// 8. Link -> unlink -> export/erase across multiple conversations, with
// an unrelated user whose data must survive.
$lc_email = 'pa_lifecycle_' . wp_generate_password( 6, false, false ) . '@example.com';
$lc_user = wp_create_user( 'pa_lifecycle_' . wp_generate_password( 6, false, false ), wp_generate_password( 12, false ), $lc_email );
$lc_a = $repo->find_or_create( 'bale', 'bale:dur-life-a', $lc_user );
$repo->add_message( $lc_a, 'user', 'لایف‌سایکل یک' );
$repo->add_message( $lc_a, 'assistant', 'پاسخ یک' );
$lc_b = $repo->find_or_create( 'bale', 'bale:dur-life-b', $lc_user );
$repo->add_message( $lc_b, 'user', 'لایف‌سایکل دو' );
$lc_subject = (string) $wpdb->get_var( $wpdb->prepare( "SELECT subject_hash FROM " . pa_table( 'privacy_subjects' ) . " WHERE wp_user_id = %d", $lc_user ) );
pa_dur_assert( '' !== $lc_subject, 'linked user has a pseudonymous privacy subject' );

$other_email = 'pa_other_' . wp_generate_password( 6, false, false ) . '@example.com';
$other_user = wp_create_user( 'pa_other_' . wp_generate_password( 6, false, false ), wp_generate_password( 12, false ), $other_email );
$other_cid = $repo->find_or_create( 'bale', 'bale:dur-life-other', $other_user );
$repo->add_message( $other_cid, 'user', 'دادهٔ کاربر دیگر' );

pa_link_remove( 'dur-life-a' );
pa_link_remove( 'dur-life-b' );
$lc_row = $wpdb->get_row( $wpdb->prepare( "SELECT wp_user_id, status FROM " . pa_table( 'conversations' ) . " WHERE id = %d", $lc_a ), ARRAY_A );
pa_dur_assert( '0' === (string) $lc_row['wp_user_id'] && 'closed' === $lc_row['status'], 'unlink detaches the account and closes the conversation' );
$lc_map_after_unlink = (string) $wpdb->get_var( $wpdb->prepare( "SELECT subject_hash FROM " . pa_table( 'privacy_subjects' ) . " WHERE wp_user_id = %d", $lc_user ) );
pa_dur_assert( $lc_map_after_unlink === $lc_subject, 'unlink keeps the privacy mapping for export/erasure' );

$export_after = pa_data_exporter( $lc_email );
$export_text = '';
foreach ( (array) $export_after['data'] as $export_item ) {
	foreach ( (array) $export_item['data'] as $export_field ) {
		if ( isset( $export_field['name'] ) && 'متن گفتگو' === $export_field['name'] ) {
			$export_text .= (string) $export_field['value'];
		}
	}
}
pa_dur_assert( false !== strpos( $export_text, 'لایف‌سایکل یک' ) && false !== strpos( $export_text, 'لایف‌سایکل دو' ), 'exporter finds history after unlink' );
pa_dur_assert( false === strpos( $export_text, 'دادهٔ کاربر دیگر' ), 'exporter never includes another user data' );

$erased_lifecycle = pa_data_eraser( $lc_email );
$lc_messages_left = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'messages' ) . " WHERE privacy_subject = %s", $lc_subject ) );
$lc_map_left = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'privacy_subjects' ) . " WHERE wp_user_id = %d", $lc_user ) );
pa_dur_assert( ! empty( $erased_lifecycle['items_removed'] ) && 0 === $lc_messages_left && 0 === $lc_map_left, 'eraser removes unlinked history and the mapping' );
$other_text = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'messages' ) . " WHERE conversation_id = %d AND text = %s", $other_cid, 'دادهٔ کاربر دیگر' ) );
pa_dur_assert( 1 === $other_text, 'erasure does not affect another user conversation' );
$repeat_erase = pa_data_eraser( $lc_email );
pa_dur_assert( 0 === (int) $repeat_erase['items_removed'], 'repeated erasure is idempotent' );

// 9. Unlinked conversations remain covered by retention.
$ret_email = 'pa_ret_' . wp_generate_password( 6, false, false ) . '@example.com';
$ret_user = wp_create_user( 'pa_ret_' . wp_generate_password( 6, false, false ), wp_generate_password( 12, false ), $ret_email );
$ret_cid = $repo->find_or_create( 'bale', 'bale:dur-life-ret', $ret_user );
$repo->add_message( $ret_cid, 'user', 'گفتگوی رهاشده' );
pa_link_remove( 'dur-life-ret' );
$wpdb->query( $wpdb->prepare( "UPDATE " . pa_table( 'conversations' ) . " SET updated_at = %s WHERE id = %d", '2000-01-01 00:00:00', $ret_cid ) );
update_option( 'pa_retention_days', 1, false );
pa_prune();
delete_option( 'pa_retention_days' );
$ret_gone = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'conversations' ) . " WHERE id = %d", $ret_cid ) );
pa_dur_assert( 0 === $ret_gone, 'retention prunes unlinked conversations' );

wp_delete_user( $lc_user );
wp_delete_user( $other_user );
wp_delete_user( $ret_user );
pa_dur_cleanup_chat( 'bale:dur-life-a' );
pa_dur_cleanup_chat( 'bale:dur-life-b' );
pa_dur_cleanup_chat( 'bale:dur-life-other' );
pa_dur_cleanup_chat( 'bale:dur-life-ret' );

remove_theme_mod( 'pm_bale_token' );

WP_CLI::log( '' );
WP_CLI::log( 'RESULT: ' . $GLOBALS['pa_dur_pass'] . ' passed, ' . $GLOBALS['pa_dur_fail'] . ' failed.' );
exit( $GLOBALS['pa_dur_fail'] > 0 ? 1 : 0 );
