<?php
/**
 * Platform baseline tests: PHP, WordPress, DB and required extensions.
 * Run: wp eval-file tests/pa-platform-test.php
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}
$GLOBALS['pa_plat_pass'] = 0;
$GLOBALS['pa_plat_fail'] = 0;
function pa_plat_assert( $condition, $label ) {
	if ( $condition ) {
		$GLOBALS['pa_plat_pass']++;
		WP_CLI::log( 'PASS: ' . $label );
	} else {
		$GLOBALS['pa_plat_fail']++;
		WP_CLI::warning( 'FAIL: ' . $label );
	}
}

global $wpdb;
pa_plat_assert( PHP_VERSION_ID >= 80427, 'PHP >= 8.4.27 (actual ' . PHP_VERSION . ')' );
pa_plat_assert( version_compare( get_bloginfo( 'version' ), '7.1', '>=' ), 'WordPress >= 7.1 (actual ' . get_bloginfo( 'version' ) . ')' );

$required = array( 'curl', 'json', 'mbstring', 'mysqli', 'openssl', 'zip', 'xml', 'gd', 'Zend OPcache' );
foreach ( $required as $extension ) {
	pa_plat_assert( extension_loaded( $extension ), 'extension loaded: ' . $extension );
}

$db_version = (string) $wpdb->db_version();
pa_plat_assert(
	(bool) preg_match( '/^8\.4\./', $db_version ) || (bool) preg_match( '/^(11\.8|12\.3)\./', $db_version ),
	'supported DB branch (actual ' . $db_version . ')'
);
pa_plat_assert( pa_store_ready(), 'plugin tables exist' );
pa_plat_assert( '1.0.1' === (string) get_option( 'pa_db_version' ), 'pa_db_version is 1.0.1' );
pa_plat_assert( (bool) $wpdb->get_var( "SHOW TABLES LIKE '" . pa_table( 'privacy_subjects' ) . "'" ), 'privacy subject table exists' );
$privacy_col = $wpdb->get_row( "SHOW COLUMNS FROM " . pa_table( 'messages' ) . " LIKE 'privacy_subject'", ARRAY_A );
pa_plat_assert( ! empty( $privacy_col ), 'messages.privacy_subject column exists' );

$json = wp_json_encode( array( 'سلام' => 'دنیا' ) );
pa_plat_assert( is_string( $json ) && 'دنیا' === json_decode( $json, true )['سلام'], 'UTF-8 JSON round trip' );
pa_plat_assert( 4 === mb_strlen( 'سلام' ), 'mbstring UTF-8 length' );

foreach ( array( 'conversations' => 'channel_external', 'processed_updates' => 'provider_update', 'link_tokens' => 'token_hash', 'privacy_subjects' => 'subject_hash' ) as $table => $index ) {
	$indexes = $wpdb->get_results( "SHOW INDEX FROM " . pa_table( $table ), ARRAY_A );
	$found = false;
	foreach ( (array) $indexes as $row ) {
		if ( isset( $row['Key_name'] ) && $index === $row['Key_name'] && 0 === (int) $row['Non_unique'] ) {
			$found = true;
			break;
		}
	}
	pa_plat_assert( $found, 'unique index: ' . $table . '.' . $index );
}

$routes = array_keys( rest_get_server()->get_routes() );
pa_plat_assert( in_array( '/pa/v1/chat', $routes, true ), 'shared chat REST route present' );
pa_plat_assert( (bool) wp_next_scheduled( 'pa_recover_pending' ), 'recovery cron scheduled' );

$wpdb->delete( pa_table( 'events' ), array( 'event' => 'pa_tx_probe' ) );
$wpdb->query( 'START TRANSACTION' );
$inserted = $wpdb->insert(
	pa_table( 'events' ),
	array( 'conversation_id' => 0, 'event' => 'pa_tx_probe', 'payload' => null, 'created_at' => current_time( 'mysql' ) ),
	array( '%d', '%s', '%s', '%s' )
);
$wpdb->query( 'ROLLBACK' );
$rolled_back = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . pa_table( 'events' ) . " WHERE event = 'pa_tx_probe'" );
pa_plat_assert( false !== $inserted && 0 === $rolled_back, 'transaction rollback leaves no row' );

$wpdb->query( 'START TRANSACTION' );
$wpdb->insert(
	pa_table( 'events' ),
	array( 'conversation_id' => 0, 'event' => 'pa_tx_probe', 'payload' => null, 'created_at' => current_time( 'mysql' ) ),
	array( '%d', '%s', '%s', '%s' )
);
$wpdb->query( 'COMMIT' );
$committed = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . pa_table( 'events' ) . " WHERE event = 'pa_tx_probe'" );
pa_plat_assert( 1 === $committed, 'transaction commit persists exactly one row' );
$wpdb->delete( pa_table( 'events' ), array( 'event' => 'pa_tx_probe' ) );

// Upgrade path: simulate a 1.0.0 schema, then run the idempotent 1.0.1
// upgrade and verify existing conversations/messages are preserved and
// backfilled with a pseudonymous privacy subject.
$up_user = wp_create_user( 'pa_plat_upgrade', wp_generate_password( 12, false ), 'pa_plat_upgrade@example.com' );
$up_repo = new PA_Conversation_Repository();
$up_cid = $up_repo->find_or_create( 'bale', 'bale:plat-upgrade', $up_user );
$up_repo->add_message( $up_cid, 'user', 'متن پیش از ارتقا' );
$conversations_before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . pa_table( 'conversations' ) );
$wpdb->query( 'ALTER TABLE ' . pa_table( 'messages' ) . ' DROP COLUMN privacy_subject' );
$wpdb->query( 'DROP TABLE IF EXISTS ' . pa_table( 'privacy_subjects' ) );
update_option( 'pa_db_version', '1.0.0', false );
pa_maybe_install();
$up_col = $wpdb->get_row( "SHOW COLUMNS FROM " . pa_table( 'messages' ) . " LIKE 'privacy_subject'", ARRAY_A );
$up_table = $wpdb->get_var( "SHOW TABLES LIKE '" . pa_table( 'privacy_subjects' ) . "'" );
$conversations_after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . pa_table( 'conversations' ) );
$up_subject = (string) $wpdb->get_var( $wpdb->prepare( "SELECT privacy_subject FROM " . pa_table( 'messages' ) . " WHERE conversation_id = %d ORDER BY id ASC LIMIT 1", $up_cid ) );
$up_mapping = (string) $wpdb->get_var( $wpdb->prepare( "SELECT subject_hash FROM " . pa_table( 'privacy_subjects' ) . " WHERE wp_user_id = %d", $up_user ) );
pa_plat_assert( '1.0.1' === (string) get_option( 'pa_db_version' ), 'upgrade sets pa_db_version 1.0.1' );
pa_plat_assert( ! empty( $up_col ) && $up_table === pa_table( 'privacy_subjects' ), 'upgrade recreates privacy column and table' );
pa_plat_assert( $conversations_after >= $conversations_before, 'upgrade preserves existing conversations' );
pa_plat_assert( '' !== $up_subject && $up_subject === $up_mapping, 'upgrade backfills the pseudonymous subject' );
wp_delete_user( $up_user );
$wpdb->delete( pa_table( 'messages' ), array( 'conversation_id' => $up_cid ) );
$wpdb->delete( pa_table( 'events' ), array( 'conversation_id' => $up_cid ) );
$wpdb->delete( pa_table( 'conversations' ), array( 'id' => $up_cid ) );
$wpdb->delete( pa_table( 'privacy_subjects' ), array( 'wp_user_id' => $up_user ) );

WP_CLI::log( '' );
WP_CLI::log( 'RESULT: ' . $GLOBALS['pa_plat_pass'] . ' passed, ' . $GLOBALS['pa_plat_fail'] . ' failed.' );
exit( $GLOBALS['pa_plat_fail'] > 0 ? 1 : 0 );
