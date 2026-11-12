<?php
/**
 * Migration tests (synthetic legacy rows, fully cleaned up).
 * Run: wp eval-file tests/pa-migration-test.php
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}
$GLOBALS['pa_mig_pass'] = 0;
$GLOBALS['pa_mig_fail'] = 0;
function pa_mig_assert( $condition, $label ) {
	if ( $condition ) {
		$GLOBALS['pa_mig_pass']++;
		WP_CLI::log( 'PASS: ' . $label );
	} else {
		$GLOBALS['pa_mig_fail']++;
		WP_CLI::warning( 'FAIL: ' . $label );
	}
}

global $wpdb;
$slug_a = 'parsian-music-TESTAA';
$slug_b = 'parsian-music-TESTBB';
$secret_values = array(
	'bale_token'     => 'LEGACY-BALE-SECRET-A',
	'rubika_token'   => 'LEGACY-RUBIKA-SECRET-A',
	'ai_api_key'     => 'LEGACY-AI-SECRET-A',
	'custom_password' => 'LEGACY-CUSTOM-PASSWORD-A',
);
$original_dest = get_option( 'theme_mods_parsian-music', array() );
update_option(
	'theme_mods_' . $slug_a,
	array_merge(
		array(
			'phone'            => '09120000000',
			'brand_name'       => 'تست',
			'unknown_key_xyz'  => 'keep-me',
		),
		$secret_values
	),
	false
);
update_option( 'theme_mods_' . $slug_b, array( 'phone' => '09120000001' ), false );
update_option(
	'theme_mods_parsian-music',
	array(
		'phone'        => '09121111111',
		'brand_name'   => '',
		'bale_token'   => 'CURRENT-BALE-SECRET',
		'ai_api_key'   => 'CURRENT-AI-SECRET',
		'rubika_token' => 'CURRENT-RUBIKA-SECRET',
	),
	false
);

$candidates = pa_migration_candidates();
pa_mig_assert( isset( $candidates[ $slug_a ] ) && isset( $candidates[ $slug_b ] ), 'both synthetic legacy slugs detected' );
pa_mig_assert( ! isset( $candidates['parsian-music'] ), 'canonical slug never listed as candidate' );

$preview = pa_migration_preview( $slug_a );
pa_mig_assert( ! is_wp_error( $preview ), 'preview builds' );
pa_mig_assert( in_array( 'phone', $preview['supported'], true ), 'phone is schema-supported' );
pa_mig_assert( in_array( 'unknown_key_xyz', $preview['unsupported'], true ), 'unknown key flagged unsupported' );
foreach ( array_keys( $secret_values ) as $secret_key ) {
	pa_mig_assert( in_array( $secret_key, $preview['secrets'], true ), 'secret key excluded from migration: ' . $secret_key );
	pa_mig_assert( ! in_array( $secret_key, $preview['supported'], true ), 'secret key not offered as supported: ' . $secret_key );
}

$report = pa_migration_run( $slug_a );
pa_mig_assert( ! is_wp_error( $report ) && ! empty( $report['completed'] ), 'migration completes' );
pa_mig_assert( in_array( 'unknown_key_xyz', $report['preserved_unsupported'], true ), 'unknown key reported, not migrated' );
pa_mig_assert( in_array( 'brand_name', $report['migrated'], true ), 'supported non-secret key migrated' );
pa_mig_assert( in_array( 'phone', $report['skipped_conflicts'], true ), 'conflicting destination value preserved' );
foreach ( array_keys( $secret_values ) as $secret_key ) {
	pa_mig_assert( in_array( $secret_key, $report['secrets_skipped'], true ), 'report requires secure credential provisioning: ' . $secret_key );
}
$after = get_option( 'theme_mods_parsian-music', array() );
pa_mig_assert( isset( $after['phone'] ) && '09121111111' === $after['phone'], 'destination conflict kept' );
pa_mig_assert( isset( $after['brand_name'] ) && 'تست' === $after['brand_name'], 'empty destination key filled' );
pa_mig_assert( 'CURRENT-BALE-SECRET' === $after['bale_token'] && 'CURRENT-AI-SECRET' === $after['ai_api_key'], 'current credentials untouched by migration' );
pa_mig_assert( ! array_key_exists( 'unknown_key_xyz', $after ), 'unsupported key not copied to destination' );
pa_mig_assert( get_option( 'theme_mods_' . $slug_a ) !== false, 'source option preserved' );

$backup = get_option( $report['backup'], array() );
$backup_blob = wp_json_encode( $backup ) . serialize( $backup ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
foreach ( $secret_values as $secret_value ) {
	pa_mig_assert( false === strpos( $backup_blob, $secret_value ), 'backup never contains legacy secret value' );
}
foreach ( array( 'CURRENT-BALE-SECRET', 'CURRENT-AI-SECRET', 'CURRENT-RUBIKA-SECRET' ) as $current_secret ) {
	pa_mig_assert( false === strpos( $backup_blob, $current_secret ), 'backup never contains current secret value' );
}
pa_mig_assert( ! array_key_exists( 'bale_token', $backup['dest_before'] ) && ! array_key_exists( 'ai_api_key', $backup['source_mods'] ), 'backup snapshots omit secret keys' );

// Idempotency: second run reports no changes.
$again = pa_migration_run( $slug_a );
pa_mig_assert( ! is_wp_error( $again ) && ! empty( $again['idempotent'] ), 'second run is idempotent no-op' );

// Conflict: pre-fill destination with different value, migrate other slug.
$report_b = pa_migration_run( $slug_b, true );
pa_mig_assert( in_array( 'phone', $report_b['skipped_conflicts'], true ), 'conflicting dest value not overwritten' );
pa_mig_assert( '09121111111' === get_option( 'theme_mods_parsian-music', array() )['phone'], 'destination conflict preserved' );

// Restore non-secret values while preserving currently configured credentials.
$current = get_option( 'theme_mods_parsian-music', array() );
$current['phone'] = 'CHANGED-AFTER-BACKUP';
$current['bale_token'] = 'ROTATED-BALE-SECRET';
update_option( 'theme_mods_parsian-music', $current, false );
$restored = pa_migration_restore( $report['backup'] );
$restored_mods = get_option( 'theme_mods_parsian-music', array() );
pa_mig_assert( true === $restored, 'backup restores' );
pa_mig_assert( '09121111111' === $restored_mods['phone'], 'restore reverts non-secret values' );
pa_mig_assert( 'ROTATED-BALE-SECRET' === $restored_mods['bale_token'], 'restore preserves rotated credential' );
pa_mig_assert( 'CURRENT-AI-SECRET' === $restored_mods['ai_api_key'], 'restore preserves current API key' );

// Legacy backups are sanitized in place without logging values.
$legacy_id = 'pa_migration_backup_' . gmdate( 'YmdHis', time() - DAY_IN_SECONDS ) . '_legacy';
update_option(
	$legacy_id,
	array(
		'source_slug'   => $slug_a,
		'source_option' => 'theme_mods_' . $slug_a,
		'source_mods'   => array_merge( array( 'phone' => '09120000000', 'brand_name' => 'legacy' ), $secret_values ),
		'dest_before'   => array_merge( array( 'phone' => '09121111111' ), $secret_values ),
	),
	false
);
$legacy_changed = pa_migration_sanitize_legacy_backups();
$legacy = get_option( $legacy_id, array() );
$legacy_blob = wp_json_encode( $legacy ) . serialize( $legacy ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
pa_mig_assert( $legacy_changed >= 1, 'legacy backup sanitization reports a change' );
pa_mig_assert( isset( $legacy['source_mods']['phone'] ) && '09120000000' === $legacy['source_mods']['phone'], 'legacy sanitization preserves non-secret values' );
pa_mig_assert( ! empty( $legacy['dest_keys_before'] ) && ! empty( $legacy['created_at'] ), 'legacy sanitization adds rollback metadata' );
foreach ( $secret_values as $secret_value ) {
	pa_mig_assert( false === strpos( $legacy_blob, $secret_value ), 'legacy backup secret removed: ' . substr( $secret_value, 0, 12 ) );
}
$legacy_again = pa_migration_sanitize_legacy_backups();
pa_mig_assert( 0 === $legacy_again, 'legacy backup sanitization is idempotent' );

// Restoring a sanitized legacy backup cannot blank credentials.
$current = get_option( 'theme_mods_parsian-music', array() );
$current['bale_token'] = 'KEEP-ME-BALE';
$current['ai_api_key'] = 'KEEP-ME-AI';
update_option( 'theme_mods_parsian-music', $current, false );
pa_migration_restore( $legacy_id );
$after_legacy_restore = get_option( 'theme_mods_parsian-music', array() );
pa_mig_assert( 'KEEP-ME-BALE' === $after_legacy_restore['bale_token'] && 'KEEP-ME-AI' === $after_legacy_restore['ai_api_key'], 'legacy restore keeps live credentials' );

// Retention: age expiry and a hard maximum count.
$all_backups = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'pa_migration_backup_%'" );
foreach ( $all_backups as $backup_name ) {
	delete_option( $backup_name );
}
for ( $i = 0; $i < 12; $i++ ) {
	$name = 'pa_migration_backup_' . gmdate( 'YmdHis', time() - $i * DAY_IN_SECONDS ) . '_r' . $i;
	update_option(
		$name,
		array(
			'source_slug'   => $slug_a,
			'source_option' => 'theme_mods_' . $slug_a,
			'source_mods'   => array( 'phone' => '09120000000' ),
			'dest_before'   => array( 'phone' => '09121111111' ),
			'created_at'    => gmdate( 'Y-m-d H:i:s', time() - $i * DAY_IN_SECONDS ),
		),
		false
	);
}
$old_name = 'pa_migration_backup_' . gmdate( 'YmdHis', time() - 40 * DAY_IN_SECONDS ) . '_old1';
update_option( $old_name, array( 'source_slug' => $slug_a, 'dest_before' => array(), 'created_at' => gmdate( 'Y-m-d H:i:s', time() - 40 * DAY_IN_SECONDS ) ), false );
$pruned = pa_migration_prune_backups();
$remaining = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'pa_migration_backup_%'" );
pa_mig_assert( $pruned >= 3, 'retention deletes expired and excess backups' );
pa_mig_assert( $remaining <= 10, 'retained backup count is bounded' );
pa_mig_assert( null === get_option( $old_name, null ), 'expired backup removed' );

// Cleanup.
foreach ( (array) $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'pa_migration_backup_%'" ) as $backup_name ) {
	delete_option( $backup_name );
}
delete_option( 'theme_mods_' . $slug_a );
delete_option( 'theme_mods_' . $slug_b );
delete_option( 'pa_migration_done_' . md5( $slug_a ) );
delete_option( 'pa_migration_done_' . md5( $slug_b ) );
update_option( 'theme_mods_parsian-music', is_array( $original_dest ) ? $original_dest : array(), false );
$status = pa_migration_status();
pa_mig_assert( 'clean' === $status['state'], 'canonical install reports no migration needed' );

WP_CLI::log( '' );
WP_CLI::log( 'RESULT: ' . $GLOBALS['pa_mig_pass'] . ' passed, ' . $GLOBALS['pa_mig_fail'] . ' failed.' );
exit( $GLOBALS['pa_mig_fail'] > 0 ? 1 : 0 );
