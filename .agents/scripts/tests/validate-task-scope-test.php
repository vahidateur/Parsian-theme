#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Independent regression suite for CONTRACT-SCOPE-001 (Agent Scope Guard).
 *
 * The suite creates throwaway Git repositories under a temporary directory and
 * invokes .agents/scripts/validate-task-scope.php with --json for every case.
 * It never uses the live ownership registry, the live task contract, WordPress
 * data, or backups.
 *
 * Without an integrated Scope Guard the suite exits 2 with a clear setup
 * failure. It does not skip and never reports PASS when the tool is absent.
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "This test runner is CLI-only.\n" );
	exit( 2 );
}

if ( PHP_VERSION_ID < 80100 ) {
	fwrite( STDERR, 'PHP 8.1 or newer is required; this runner was tested with PHP 8.4.' . "\n" );
	exit( 2 );
}

require_once __DIR__ . '/lib/Harness.php';
require_once __DIR__ . '/lib/Cases.php';

const SCOPE_TEST_EXPECTED_CASES = 22;

$repoRoot = dirname( __DIR__, 3 );
$tempRoot = null;
$exitCode = 2;

try {
	$toolPath = ScopeTestTool::resolve( $repoRoot );
	$tempRoot = new ScopeTestTempRoot();
	$cases    = scope_test_cases();

	if ( SCOPE_TEST_EXPECTED_CASES !== count( $cases ) ) {
		throw new ScopeTestSetupError(
			sprintf(
				'Case count mismatch: expected %d, found %d. Update the runner and README together.',
				SCOPE_TEST_EXPECTED_CASES,
				count( $cases ),
			),
		);
	}

	$runner   = new ScopeTestRunner( $toolPath, $tempRoot );
	$exitCode = $runner->run( $cases );
} catch ( ScopeTestSetupError $error ) {
	fwrite( STDERR, "SETUP FAILURE\n" . $error->getMessage() . "\n" );
	$exitCode = 2;
} catch ( Throwable $error ) {
	fwrite( STDERR, "SUITE ERROR\n" . $error::class . ': ' . $error->getMessage() . "\n" );
	$exitCode = 2;
} finally {
	if ( null !== $tempRoot ) {
		$keep = getenv( 'SCOPE_TEST_KEEP_TMP' );
		if ( is_string( $keep ) && '' !== trim( $keep ) ) {
			fwrite( STDOUT, "\nTemp artifacts kept: " . $tempRoot->path . "\n" );
		} else {
			$tempRoot->cleanup();
		}
	}
}

exit( $exitCode );
