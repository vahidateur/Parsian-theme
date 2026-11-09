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
set_theme_mod( 'pm_bale_token', 'test-token-for-mock' );
set_theme_mod( 'pm_bale_chat_id', '4242' );
add_filter(
	'pre_http_request',
	function ( $pre, $args, $url ) use ( &$mock_calls ) {
		$mock_calls[] = $url;
		if ( false !== strpos( $url, 'getMe' ) ) {
			return array(
				'response' => array( 'code' => 200, 'message' => 'OK' ),
				'body'     => wp_json_encode( array( 'ok' => true, 'result' => array( 'id' => 1, 'username' => 'parsiantestbot' ) ) ),
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
