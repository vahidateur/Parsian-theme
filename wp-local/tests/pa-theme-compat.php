<?php
/**
 * Theme-independent plugin compatibility tests.
 * Run under any active theme: wp eval-file tests/pa-theme-compat.php
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}
$GLOBALS['pa_tc_pass'] = 0;
$GLOBALS['pa_tc_fail'] = 0;
function pa_tc_assert( $condition, $label ) {
	if ( $condition ) {
		$GLOBALS['pa_tc_pass']++;
		WP_CLI::log( 'PASS: ' . $label );
	} else {
		$GLOBALS['pa_tc_fail']++;
		WP_CLI::warning( 'FAIL: ' . $label );
	}
}

global $wpdb;
$slug = preg_replace( '/[^a-z0-9_-]/', '', strtolower( get_stylesheet() ) );
$chat = 'compat-' . $slug;
$GLOBALS['pa_tc_http'] = array();
add_filter(
	'pre_http_request',
	function ( $pre, $args, $url ) {
		if ( false === strpos( (string) $url, 'tapi.bale.ai' ) ) {
			return $pre;
		}
		$GLOBALS['pa_tc_http'][] = (string) $url;
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

pa_tc_assert( pa_active() && pa_store_ready(), 'plugin boots and owns its tables' );
$routes = array_keys( rest_get_server()->get_routes() );
pa_tc_assert( in_array( '/pa/v1/chat', $routes, true ), 'shared chat route registered' );
pa_tc_assert( in_array( '/pa/v1/status', $routes, true ), 'admin status route registered' );
pa_tc_assert( (bool) preg_grep( '#^/pa/v1/bale/#', $routes ), 'secret webhook route registered' );

$repo = new PA_Conversation_Repository();
$cid = $repo->find_or_create( 'bale', 'bale:' . $chat, 0 );
$repo->add_message( $cid, 'user', 'سلام سازگاری قالب' );
$repo->add_message( $cid, 'assistant', 'پاسخ سازگاری' );
$history = $repo->history( $cid, 10 );
pa_tc_assert( 2 === count( $history ), 'conversation store works without Parsian theme' );

$service = new PA_Assistant_Service();
$answer = $service->answer(
	array(
		'channel'      => 'web',
		'external_key' => 'web:' . $chat,
		'user_id'      => 0,
		'text'         => 'سلام',
	)
);
pa_tc_assert( is_array( $answer ) && ! empty( $answer['reply'] ) && $answer['conversation_id'] > 0, 'shared engine fallback answers without theme helpers' );

update_option( 'pa_bale_token', 'compat-test-token', false );
$orig_theme_token = function_exists( 'pm_notify_get' ) ? get_theme_mod( 'pm_bale_token', null ) : null;
if ( function_exists( 'pm_notify_get' ) ) {
	set_theme_mod( 'pm_bale_token', 'compat-test-token' );
}
$adapter = new PA_Bale_Adapter();
$parsed = $adapter->parse_incoming(
	array( 'update_id' => 1, 'message' => array( 'text' => 'سلام', 'chat' => array( 'id' => $chat ), 'from' => array( 'id' => $chat ) ) )
);
pa_tc_assert( $parsed && $chat === $parsed['chat_id'], 'Bale parser works without theme helpers' );
$sent = $adapter->send( $chat, 'پیام سازگاری' );
pa_tc_assert( ! empty( $sent['success'] ), 'Bale adapter sends with option fallback' );
delete_option( 'pa_bale_token' );
if ( function_exists( 'pm_notify_get' ) ) {
	if ( null === $orig_theme_token ) {
		remove_theme_mod( 'pm_bale_token' );
	} else {
		set_theme_mod( 'pm_bale_token', $orig_theme_token );
	}
}

$http_before = count( $GLOBALS['pa_tc_http'] );
$request = new WP_REST_Request( 'POST', '/pa/v1/bale/' . pa_webhook_secret() );
$request->set_header( 'Content-Type', 'application/json' );
$request->set_body(
	wp_json_encode(
		array(
			'update_id' => 'compat-' . $slug . '-1',
			'message'   => array(
				'text' => 'سلام وب‌هوک',
				'chat' => array( 'id' => $chat ),
				'from' => array( 'id' => $chat ),
			),
		)
	)
);
$response = rest_do_request( $request );
$data = $response->get_data();
$user_messages = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'messages' ) . " WHERE conversation_id = %d AND role = 'user'", $cid ) );
$assistant_messages = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'messages' ) . " WHERE conversation_id = %d AND role = 'assistant'", $cid ) );
pa_tc_assert( 200 === $response->get_status() && ! empty( $data['ok'] ), 'webhook accepts a valid update without theme dependencies' );
pa_tc_assert( 2 === $user_messages && 1 === $assistant_messages, 'webhook persists without synchronous AI work' );
pa_tc_assert( $http_before === count( $GLOBALS['pa_tc_http'] ), 'webhook performs no outbound call' );

$bad = new WP_REST_Request( 'POST', '/pa/v1/bale/wrongsecret' );
$bad->set_header( 'Content-Type', 'application/json' );
$bad->set_body( '{"update_id":2}' );
pa_tc_assert( 403 === rest_do_request( $bad )->get_status(), 'bad webhook secret is rejected' );

wp_set_current_user( 1 );
ob_start();
pa_admin_page();
$admin = ob_get_clean();
pa_tc_assert( false !== strpos( $admin, 'صف پردازش' ), 'assistant admin page renders without Parsian theme' );
ob_start();
pa_migration_page();
$migration = ob_get_clean();
pa_tc_assert( strlen( $migration ) > 100, 'migration page renders without Parsian theme' );

do_action( 'wp_enqueue_scripts' );
if ( 'parsian-music' !== get_stylesheet() ) {
	pa_tc_assert( ! wp_script_is( 'pa-popup', 'enqueued' ), 'popup asset stays isolated under third-party theme' );
} else {
	pa_tc_assert( true, 'popup asset path does not fatal under Parsian theme' );
}

pa_tc_assert( (bool) wp_next_scheduled( 'pa_recover_pending' ), 'recovery cron is scheduled' );

// Cleanup only this test's fixtures.
$web_cid = (int) $wpdb->get_var(
	$wpdb->prepare( "SELECT id FROM " . pa_table( 'conversations' ) . " WHERE channel = 'web' AND external_key = %s", 'web:' . $chat )
);
foreach ( array_filter( array( $cid, $web_cid ) ) as $clean_id ) {
	$wpdb->delete( pa_table( 'messages' ), array( 'conversation_id' => $clean_id ) );
	$wpdb->delete( pa_table( 'events' ), array( 'conversation_id' => $clean_id ) );
	$wpdb->delete( pa_table( 'conversations' ), array( 'id' => $clean_id ) );
	delete_option( 'pa_worker_lock_' . $clean_id );
}
wp_clear_scheduled_hook( 'pa_bale_reply', array( $cid, $chat ) );
$wpdb->delete( pa_table( 'processed_updates' ), array( 'provider' => 'bale', 'update_id' => 'compat-' . $slug . '-1' ) );
delete_transient( 'pa_rl_bale_' . md5( $chat ) );

WP_CLI::log( '' );
WP_CLI::log( 'RESULT: ' . $GLOBALS['pa_tc_pass'] . ' passed, ' . $GLOBALS['pa_tc_fail'] . ' failed.' );
exit( $GLOBALS['pa_tc_fail'] > 0 ? 1 : 0 );
