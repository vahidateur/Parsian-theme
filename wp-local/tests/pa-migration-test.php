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

$slug_a = 'parsian-music-TESTAA';
$slug_b = 'parsian-music-TESTBB';
update_option( 'theme_mods_' . $slug_a, array( 'phone' => '09120000000', 'brand_name' => 'تست', 'unknown_key_xyz' => 'keep-me' ), false );
update_option( 'theme_mods_' . $slug_b, array( 'phone' => '09120000001' ), false );

$candidates = pa_migration_candidates();
pa_mig_assert( isset( $candidates[ $slug_a ] ) && isset( $candidates[ $slug_b ] ), 'both synthetic legacy slugs detected' );
pa_mig_assert( ! isset( $candidates['parsian-music'] ), 'canonical slug never listed as candidate' );

$preview = pa_migration_preview( $slug_a );
pa_mig_assert( ! is_wp_error( $preview ), 'preview builds' );
pa_mig_assert( in_array( 'phone', $preview['supported'], true ), 'phone is schema-supported' );
pa_mig_assert( in_array( 'unknown_key_xyz', $preview['unsupported'], true ), 'unknown key flagged unsupported' );

$dest_before = get_option( 'theme_mods_parsian-music', array() );
$report = pa_migration_run( $slug_a );
pa_mig_assert( ! is_wp_error( $report ) && ! empty( $report['completed'] ), 'migration completes' );
pa_mig_assert( in_array( 'unknown_key_xyz', $report['preserved_unsupported'], true ), 'unknown key preserved via backup, not migrated' );
$after = get_option( 'theme_mods_parsian-music', array() );
pa_mig_assert( isset( $after['phone'] ), 'supported key migrated to canonical' );
pa_mig_assert( ! array_key_exists( 'unknown_key_xyz', $after ), 'unsupported key not copied to destination' );
pa_mig_assert( get_option( 'theme_mods_' . $slug_a ) !== false, 'source option preserved' );
pa_mig_assert( get_option( $report['backup'] ) !== false, 'backup option exists' );

// Idempotency: second run reports no changes.
$again = pa_migration_run( $slug_a );
pa_mig_assert( ! is_wp_error( $again ) && ! empty( $again['idempotent'] ), 'second run is idempotent no-op' );

// Conflict: pre-fill destination with different value, migrate other slug.
update_option( 'theme_mods_parsian-music', array_merge( get_option( 'theme_mods_parsian-music', array() ), array( 'phone' => 'CONFLICT' ) ), false );
$report_b = pa_migration_run( $slug_b, true );
pa_mig_assert( in_array( 'phone', $report_b['skipped_conflicts'], true ), 'conflicting dest value not overwritten' );
pa_mig_assert( 'CONFLICT' === get_option( 'theme_mods_parsian-music', array() )['phone'], 'destination conflict preserved' );

// Restore from backup.
$restored = pa_migration_restore( $report['backup'] );
pa_mig_assert( true === $restored, 'backup restores' );

// Canonical no-op status.
delete_option( 'theme_mods_' . $slug_a );
delete_option( 'theme_mods_' . $slug_b );
delete_option( $report['backup'] );
delete_option( $report_b['backup'] );
update_option( 'theme_mods_parsian-music', is_array( $dest_before ) ? $dest_before : array(), false );
$status = pa_migration_status();
pa_mig_assert( 'clean' === $status['state'], 'canonical install reports no migration needed' );

WP_CLI::log( '' );
WP_CLI::log( 'RESULT: ' . $GLOBALS['pa_mig_pass'] . ' passed, ' . $GLOBALS['pa_mig_fail'] . ' failed.' );
exit( $GLOBALS['pa_mig_fail'] > 0 ? 1 : 0 );
