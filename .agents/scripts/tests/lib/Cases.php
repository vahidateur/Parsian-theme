<?php
declare(strict_types=1);

/**
 * Regression cases for CONTRACT-SCOPE-001.
 *
 * Every case builds a fresh temporary Git repository and writes its own task
 * contract and ownership registry. The suite never reads the live registry or
 * the live task contract from this repository.
 *
 * @return array<string, Closure(ScopeTestFixture): ScopeTestCaseOutcome>
 */

/**
 * @param list<string> $changedPaths
 */
function scope_expect_pass( string $base, array $changedPaths, bool $exact = true ): ScopeTestExpectation {
	return new ScopeTestExpectation(
		exitCode: 0,
		expectedChangedPaths: $changedPaths,
		exactChangedPaths: $exact,
		expectedBaseSha: $base,
		expectedBaseMatches: true,
		expectedInspectionComplete: true,
	);
}

/**
 * @param list<string> $changedPaths
 * @param list<string> $changedPathAlternatives
 * @param list<string> $violationPathAlternatives
 */
function scope_expect_violation(
	string $base,
	string $violation,
	array $expectedChangedPaths = array(),
	array $changedPathAlternatives = array(),
	array $violationPathAlternatives = array(),
	bool $exactChangedPaths = false,
): ScopeTestExpectation {
	return new ScopeTestExpectation(
		exitCode: 1,
		acceptableViolations: array( $violation ),
		expectedChangedPaths: $expectedChangedPaths,
		exactChangedPaths: $exactChangedPaths,
		changedPathAlternatives: $changedPathAlternatives,
		violationPathAlternatives: $violationPathAlternatives,
		expectedBaseSha: $base,
		expectedBaseMatches: true,
		expectedInspectionComplete: true,
		requireViolationDetails: true,
	);
}

function scope_expect_config_error(
	string $violation,
	?string $expectedTaskId = 'PILOT-002',
	?bool $expectedBaseMatches = null,
): ScopeTestExpectation {
	return new ScopeTestExpectation(
		exitCode: 2,
		acceptableViolations: array( $violation ),
		expectedTaskId: $expectedTaskId,
		expectedBaseMatches: $expectedBaseMatches,
	);
}

function scope_expect_inspection_failure( string $base, string $repositoryPath ): ScopeTestExpectation {
	return new ScopeTestExpectation(
		exitCode: 3,
		acceptableViolations: array( 'git_inspection_failed' ),
		expectedBaseSha: $base,
		expectedInspectionComplete: false,
		expectedRepositoryPath: $repositoryPath,
	);
}

function scope_test_cases(): array {
	$cases = array();

	// ---------------------------------------------------------------------
	// Positive cases.
	// ---------------------------------------------------------------------

	$cases['positive_single_allowed_file'] = static function ( ScopeTestFixture $fixture ): ScopeTestCaseOutcome {
		$base = $fixture->commitBase( array( 'allowed/one.txt' => "base\n" ) );
		$fixture->repo->write( 'allowed/one.txt', "changed\n" );
		$fixture->writeTask(
			array(
				'base_commit' => $base,
				'allowed'     => array( 'allowed/one.txt' ),
			),
		);
		$fixture->writeRegistry(
			array(
				$fixture->entry( 'allowed/one.txt', 'PILOT-002', 'exclusive', $base ),
			),
		);

		return new ScopeTestCaseOutcome(
			scope_expect_pass( $base, array( 'allowed/one.txt' ) ),
			$fixture->invoke( $base ),
		);
	};

	$cases['positive_multiple_allowed_files'] = static function ( ScopeTestFixture $fixture ): ScopeTestCaseOutcome {
		$base = $fixture->commitBase(
			array(
				'allowed/a.txt' => "a\n",
				'allowed/b.txt' => "b\n",
			),
		);
		$fixture->repo->write( 'allowed/a.txt', "a changed\n" );
		$fixture->repo->write( 'allowed/b.txt', "b changed\n" );
		$fixture->writeTask(
			array(
				'base_commit' => $base,
				'allowed'     => array( 'allowed/a.txt', 'allowed/b.txt' ),
			),
		);
		$fixture->writeRegistry(
			array(
				$fixture->entry( 'allowed/a.txt', 'PILOT-002', 'exclusive', $base ),
				$fixture->entry( 'allowed/b.txt', 'PILOT-002', 'exclusive', $base ),
			),
		);

		return new ScopeTestCaseOutcome(
			scope_expect_pass( $base, array( 'allowed/a.txt', 'allowed/b.txt' ) ),
			$fixture->invoke( $base, true ),
		);
	};

	$cases['positive_nested_directory_scope'] = static function ( ScopeTestFixture $fixture ): ScopeTestCaseOutcome {
		$base = $fixture->commitBase(
			array(
				'nested/dir/deep/file.txt' => "base\n",
			),
		);
		$fixture->repo->write( 'nested/dir/deep/file.txt', "changed\n" );
		$fixture->writeTask(
			array(
				'base_commit' => $base,
				'allowed'     => array( 'nested/' ),
			),
		);
		$fixture->writeRegistry(
			array(
				$fixture->entry( 'nested/', 'PILOT-002', 'scoped', $base ),
			),
		);

		return new ScopeTestCaseOutcome(
			scope_expect_pass( $base, array( 'nested/dir/deep/file.txt' ) ),
			$fixture->invoke( $base ),
		);
	};

	$cases['positive_no_changes'] = static function ( ScopeTestFixture $fixture ): ScopeTestCaseOutcome {
		$base = $fixture->commitBase( array( 'allowed/keep.txt' => "keep\n" ) );
		$fixture->writeTask(
			array(
				'base_commit' => $base,
				'allowed'     => array( 'allowed/' ),
			),
		);
		$fixture->writeRegistry(
			array(
				$fixture->entry( 'allowed/', 'PILOT-002', 'scoped', $base ),
			),
		);

		return new ScopeTestCaseOutcome(
			scope_expect_pass( $base, array() ),
			$fixture->invoke( $base ),
		);
	};

	$cases['positive_valid_base_commit'] = static function ( ScopeTestFixture $fixture ): ScopeTestCaseOutcome {
		$base = $fixture->commitBase( array( 'allowed/base.txt' => "base\n" ) );
		$fixture->repo->write( 'allowed/base.txt', "changed\n" );
		$fixture->writeTask(
			array(
				'base_commit' => $base,
				'allowed'     => array( 'allowed/base.txt' ),
			),
		);
		$fixture->writeRegistry(
			array(
				$fixture->entry( 'allowed/base.txt', 'PILOT-002', 'exclusive', $base ),
			),
		);

		return new ScopeTestCaseOutcome(
			scope_expect_pass( $base, array( 'allowed/base.txt' ) ),
			$fixture->invoke( $base ),
		);
	};

	// ---------------------------------------------------------------------
	// Negative cases: changed paths outside the allowed scope.
	// ---------------------------------------------------------------------

	$cases['negative_modified_file_outside_scope'] = static function ( ScopeTestFixture $fixture ): ScopeTestCaseOutcome {
		$base = $fixture->commitBase(
			array(
				'allowed/keep.txt'     => "keep\n",
				'outside/modified.txt' => "base\n",
			),
		);
		$fixture->repo->write( 'outside/modified.txt', "changed\n" );
		$fixture->writeTask(
			array(
				'base_commit' => $base,
				'allowed'     => array( 'allowed/' ),
			),
		);
		$fixture->writeRegistry(
			array(
				$fixture->entry( 'allowed/', 'PILOT-002', 'scoped', $base ),
				// Active ownership is not the same thing as contract scope. The
				// contract example shows an out_of_scope violation whose path is
				// still owned by the task.
				$fixture->entry( 'outside/modified.txt', 'PILOT-002', 'exclusive', $base ),
			),
		);

		return new ScopeTestCaseOutcome(
			scope_expect_violation(
				base: $base,
				violation: 'out_of_scope',
				expectedChangedPaths: array( 'outside/modified.txt' ),
			),
			$fixture->invoke( $base ),
		);
	};

	$cases['negative_untracked_file_outside_scope'] = static function ( ScopeTestFixture $fixture ): ScopeTestCaseOutcome {
		$base = $fixture->commitBase( array( 'allowed/keep.txt' => "keep\n" ) );
		$fixture->repo->write( 'outside/untracked.txt', "new\n" );
		$fixture->writeTask(
			array(
				'base_commit' => $base,
				'allowed'     => array( 'allowed/' ),
			),
		);
		$fixture->writeRegistry(
			array(
				$fixture->entry( 'allowed/', 'PILOT-002', 'scoped', $base ),
				$fixture->entry( 'outside/untracked.txt', 'PILOT-002', 'exclusive', $base ),
			),
		);

		return new ScopeTestCaseOutcome(
			scope_expect_violation(
				base: $base,
				violation: 'out_of_scope',
				expectedChangedPaths: array( 'outside/untracked.txt' ),
			),
			$fixture->invoke( $base ),
		);
	};

	$cases['negative_expired_active_lease'] = static function ( ScopeTestFixture $fixture ): ScopeTestCaseOutcome {
		$base = $fixture->commitBase( array( 'allowed/keep.txt' => "keep\n" ) );
		$fixture->repo->write( 'outside/expired.txt', "new\n" );
		$expired = $fixture->entry( 'outside/expired.txt', 'PILOT-002', 'exclusive', $base );
		$expired['lease_expires_at'] = '2020-01-01T00:00:00+00:00';
		$fixture->writeTask(
			array(
				'base_commit' => $base,
				'allowed'     => array( 'allowed/', 'outside/expired.txt' ),
			),
		);
		$fixture->writeRegistry(
			array(
				$fixture->entry( 'allowed/', 'PILOT-002', 'scoped', $base ),
				$expired,
			),
		);

		return new ScopeTestCaseOutcome(
			scope_expect_violation(
				base: $base,
				violation: 'unowned_path',
				expectedChangedPaths: array( 'outside/expired.txt' ),
			),
			$fixture->invoke( $base ),
		);
	};

	$cases['negative_hidden_file_outside_scope'] = static function ( ScopeTestFixture $fixture ): ScopeTestCaseOutcome {
		$base = $fixture->commitBase( array( 'allowed/keep.txt' => "keep\n" ) );
		$fixture->repo->write( '.hidden/secret.txt', "secret\n" );
		$fixture->writeTask(
			array(
				'base_commit' => $base,
				'allowed'     => array( 'allowed/' ),
			),
		);
		$fixture->writeRegistry(
			array(
				$fixture->entry( 'allowed/', 'PILOT-002', 'scoped', $base ),
				$fixture->entry( '.hidden/secret.txt', 'PILOT-002', 'exclusive', $base ),
			),
		);

		return new ScopeTestCaseOutcome(
			scope_expect_violation(
				base: $base,
				violation: 'out_of_scope',
				expectedChangedPaths: array( '.hidden/secret.txt' ),
			),
			$fixture->invoke( $base ),
		);
	};

	$cases['negative_staged_file_outside_scope'] = static function ( ScopeTestFixture $fixture ): ScopeTestCaseOutcome {
		$base = $fixture->commitBase(
			array(
				'allowed/keep.txt'   => "keep\n",
				'outside/staged.txt' => "base\n",
			),
		);
		$fixture->repo->write( 'outside/staged.txt', "changed\n" );
		$fixture->repo->stage( 'outside/staged.txt' );
		$fixture->writeTask(
			array(
				'base_commit' => $base,
				'allowed'     => array( 'allowed/' ),
			),
		);
		$fixture->writeRegistry(
			array(
				$fixture->entry( 'allowed/', 'PILOT-002', 'scoped', $base ),
				$fixture->entry( 'outside/staged.txt', 'PILOT-002', 'exclusive', $base ),
			),
		);

		return new ScopeTestCaseOutcome(
			scope_expect_violation(
				base: $base,
				violation: 'out_of_scope',
				expectedChangedPaths: array( 'outside/staged.txt' ),
			),
			$fixture->invoke( $base ),
		);
	};

	$cases['negative_deleted_file_outside_scope'] = static function ( ScopeTestFixture $fixture ): ScopeTestCaseOutcome {
		$base = $fixture->commitBase(
			array(
				'allowed/keep.txt'    => "keep\n",
				'outside/deleted.txt' => "base\n",
			),
		);
		$fixture->repo->remove( 'outside/deleted.txt' );
		$fixture->writeTask(
			array(
				'base_commit' => $base,
				'allowed'     => array( 'allowed/' ),
			),
		);
		$fixture->writeRegistry(
			array(
				$fixture->entry( 'allowed/', 'PILOT-002', 'scoped', $base ),
				$fixture->entry( 'outside/deleted.txt', 'PILOT-002', 'exclusive', $base ),
			),
		);

		return new ScopeTestCaseOutcome(
			scope_expect_violation(
				base: $base,
				violation: 'out_of_scope',
				expectedChangedPaths: array( 'outside/deleted.txt' ),
			),
			$fixture->invoke( $base ),
		);
	};

	$cases['negative_renamed_file_outside_scope'] = static function ( ScopeTestFixture $fixture ): ScopeTestCaseOutcome {
		$base = $fixture->commitBase(
			array(
				'allowed/keep.txt' => "keep\n",
				'outside/old.txt'  => "base\n",
			),
		);
		$fixture->repo->rename( 'outside/old.txt', 'outside/new.txt' );
		$fixture->writeTask(
			array(
				'base_commit' => $base,
				'allowed'     => array( 'allowed/' ),
			),
		);
		$fixture->writeRegistry(
			array(
				$fixture->entry( 'allowed/', 'PILOT-002', 'scoped', $base ),
				$fixture->entry( 'outside/old.txt', 'PILOT-002', 'exclusive', $base ),
				$fixture->entry( 'outside/new.txt', 'PILOT-002', 'exclusive', $base ),
			),
		);

		return new ScopeTestCaseOutcome(
			scope_expect_violation(
				base: $base,
				violation: 'out_of_scope',
				changedPathAlternatives: array( 'outside/new.txt', 'outside/old.txt' ),
				violationPathAlternatives: array( 'outside/new.txt', 'outside/old.txt' ),
			),
			$fixture->invoke( $base ),
		);
	};

	// ---------------------------------------------------------------------
	// Negative cases: scope and ownership semantics.
	// ---------------------------------------------------------------------

	$cases['negative_forbidden_file'] = static function ( ScopeTestFixture $fixture ): ScopeTestCaseOutcome {
		$base = $fixture->commitBase(
			array(
				'allowed/keep.txt'   => "keep\n",
				'blocked/secret.txt' => "base\n",
			),
		);
		$fixture->repo->write( 'blocked/secret.txt', "changed\n" );
		$fixture->writeTask(
			array(
				'base_commit' => $base,
				// The repository governance validator rejects allowed/forbidden
				// overlap, so the forbidden path lives outside the allowed tree.
				'allowed'     => array( 'allowed/' ),
				'forbidden'   => array( 'blocked/secret.txt' ),
			),
		);
		$fixture->writeRegistry(
			array(
				$fixture->entry( 'allowed/', 'PILOT-002', 'scoped', $base ),
				$fixture->entry( 'blocked/secret.txt', 'PILOT-002', 'exclusive', $base ),
			),
		);

		return new ScopeTestCaseOutcome(
			scope_expect_violation(
				base: $base,
				violation: 'forbidden_path',
				expectedChangedPaths: array( 'blocked/secret.txt' ),
				exactChangedPaths: true,
			),
			$fixture->invoke( $base ),
		);
	};

	$cases['negative_file_owned_by_other_task'] = static function ( ScopeTestFixture $fixture ): ScopeTestCaseOutcome {
		$base = $fixture->commitBase( array( 'shared/file.txt' => "base\n" ) );
		$fixture->repo->write( 'shared/file.txt', "changed\n" );
		$fixture->writeTask(
			array(
				'base_commit' => $base,
				'allowed'     => array( 'shared/' ),
			),
		);
		$fixture->writeRegistry(
			array(
				$fixture->entry( 'shared/file.txt', 'OTHER-TASK-001', 'exclusive', $base ),
			),
		);

		return new ScopeTestCaseOutcome(
			scope_expect_violation(
				base: $base,
				violation: 'other_task_owner',
				expectedChangedPaths: array( 'shared/file.txt' ),
			),
			$fixture->invoke( $base ),
		);
	};

	$cases['negative_overlapping_active_ownership'] = static function ( ScopeTestFixture $fixture ): ScopeTestCaseOutcome {
		$base = $fixture->commitBase( array( 'scope/file.txt' => "base\n" ) );
		$fixture->repo->write( 'scope/file.txt', "changed\n" );
		$fixture->writeTask(
			array(
				'base_commit' => $base,
				'allowed'     => array( 'scope/' ),
			),
		);
		$fixture->writeRegistry(
			array(
				$fixture->entry( 'scope/file.txt', 'PILOT-002', 'exclusive', $base ),
				$fixture->entry( 'scope/', 'OTHER-TASK-002', 'scoped', $base ),
			),
		);

		return new ScopeTestCaseOutcome(
			scope_expect_violation(
				base: $base,
				violation: 'ownership_overlap',
				expectedChangedPaths: array( 'scope/file.txt' ),
			),
			$fixture->invoke( $base ),
		);
	};

	$cases['negative_component_boundary_not_prefix'] = static function ( ScopeTestFixture $fixture ): ScopeTestCaseOutcome {
		$base = $fixture->commitBase( array( 'allowed-archive/file.txt' => "base\n" ) );
		$fixture->repo->write( 'allowed-archive/file.txt', "changed\n" );
		$fixture->writeTask(
			array(
				'base_commit' => $base,
				'allowed'     => array( 'allowed/' ),
			),
		);
		$fixture->writeRegistry(
			array(
				// Ownership is component-aware for the sibling directory, so the
				// only correct failure is the contract's narrower allowed scope.
				$fixture->entry( 'allowed-archive/', 'PILOT-002', 'scoped', $base ),
			),
		);

		return new ScopeTestCaseOutcome(
			scope_expect_violation(
				base: $base,
				violation: 'out_of_scope',
				expectedChangedPaths: array( 'allowed-archive/file.txt' ),
			),
			$fixture->invoke( $base ),
		);
	};

	// ---------------------------------------------------------------------
	// Negative cases: invalid input and incomplete inspection.
	// ---------------------------------------------------------------------

	$cases['negative_invalid_task_id'] = static function ( ScopeTestFixture $fixture ): ScopeTestCaseOutcome {
		$base = $fixture->commitBase( array( 'allowed/keep.txt' => "keep\n" ) );
		$fixture->writeTask(
			array(
				'id'          => 'PILOT 002',
				'base_commit' => $base,
				'allowed'     => array( 'allowed/' ),
			),
		);
		$fixture->writeRegistry(
			array(
				$fixture->entry( 'allowed/', 'PILOT-002', 'scoped', $base ),
			),
		);

		return new ScopeTestCaseOutcome(
			scope_expect_config_error( 'invalid_task', null ),
			$fixture->invoke( $base ),
		);
	};

	$cases['negative_invalid_base_sha'] = static function ( ScopeTestFixture $fixture ): ScopeTestCaseOutcome {
		$base = $fixture->commitBase( array( 'allowed/keep.txt' => "keep\n" ) );
		$fixture->writeTask(
			array(
				'base_commit' => $base,
				'allowed'     => array( 'allowed/' ),
			),
		);
		$fixture->writeRegistry(
			array(
				$fixture->entry( 'allowed/', 'PILOT-002', 'scoped', $base ),
			),
		);

		return new ScopeTestCaseOutcome(
			scope_expect_config_error( 'base_commit_mismatch', 'PILOT-002', false ),
			$fixture->invoke( 'not-a-valid-base-sha' ),
		);
	};

	$cases['negative_path_traversal_in_task_scope'] = static function ( ScopeTestFixture $fixture ): ScopeTestCaseOutcome {
		$base = $fixture->commitBase( array( 'allowed/keep.txt' => "keep\n" ) );
		$fixture->writeTask(
			array(
				'base_commit' => $base,
				'allowed'     => array( 'allowed/', '../outside/secret.txt' ),
			),
		);
		$fixture->writeRegistry(
			array(
				$fixture->entry( 'allowed/', 'PILOT-002', 'scoped', $base ),
			),
		);

		return new ScopeTestCaseOutcome(
			scope_expect_config_error( 'invalid_scope' ),
			$fixture->invoke( $base ),
		);
	};

	$cases['negative_symlink_escape'] = static function ( ScopeTestFixture $fixture ): ScopeTestCaseOutcome {
		$base = $fixture->commitBase( array( 'scoped/keep.txt' => "keep\n" ) );
		$outsideTarget = $fixture->writeCaseFile( 'outside-target.txt', "outside repository\n" );
		$fixture->repo->symlink( $outsideTarget, 'scoped/escape' );
		$fixture->writeTask(
			array(
				'base_commit' => $base,
				'allowed'     => array( 'scoped/' ),
			),
		);
		$fixture->writeRegistry(
			array(
				$fixture->entry( 'scoped/', 'PILOT-002', 'scoped', $base ),
			),
		);

		return new ScopeTestCaseOutcome(
			scope_expect_violation(
				base: $base,
				violation: 'symlink_escape',
				expectedChangedPaths: array( 'scoped/escape' ),
				violationPathAlternatives: array( 'scoped/escape' ),
				exactChangedPaths: true,
			),
			$fixture->invoke( $base ),
		);
	};

	$cases['negative_invalid_ownership_registry'] = static function ( ScopeTestFixture $fixture ): ScopeTestCaseOutcome {
		$base = $fixture->commitBase( array( 'allowed/keep.txt' => "keep\n" ) );
		$fixture->writeTask(
			array(
				'base_commit' => $base,
				'allowed'     => array( 'allowed/' ),
			),
		);
		$fixture->writeRawRegistry(
			"version: 1\n"
			. "updated_at: \"2026-11-14T00:00:00+00:00\"\n"
			. "entries: \"not-an-array\"\n",
		);

		return new ScopeTestCaseOutcome(
			scope_expect_config_error( 'invalid_registry' ),
			$fixture->invoke( $base ),
		);
	};

	$cases['negative_non_git_repository'] = static function ( ScopeTestFixture $fixture ): ScopeTestCaseOutcome {
		$base    = $fixture->commitBase( array( 'allowed/keep.txt' => "keep\n" ) );
		$nonRepo = $fixture->makeCaseDirectory( 'not-a-git-repository' );
		$fixture->writeTask(
			array(
				'base_commit'    => $base,
				'workspace_path' => $nonRepo,
				'allowed'        => array( 'allowed/' ),
			),
		);
		$fixture->writeRegistry( array() );

		return new ScopeTestCaseOutcome(
			scope_expect_inspection_failure( $base, $nonRepo ),
			$fixture->invoke( $base, false, $nonRepo ),
		);
	};

	return $cases;
}
