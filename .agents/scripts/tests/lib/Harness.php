<?php
declare(strict_types=1);

/**
 * Zero-dependency harness for the Agent Scope Guard regression suite.
 *
 * Everything in this file is test infrastructure. It creates throwaway Git
 * repositories and YAML fixtures under a temporary directory. It never touches
 * the repository that contains the suite, WordPress data, backups, or any
 * production database.
 */

final class ScopeTestFailure extends RuntimeException {
}

final class ScopeTestSetupError extends RuntimeException {
}

final class ScopeTestProcessResult {
	public function __construct(
		public readonly int $exitCode,
		public readonly string $stdout,
		public readonly string $stderr,
		public readonly bool $timedOut,
		public readonly float $durationSeconds,
	) {
	}
}

final class ScopeTestProcess {
	/**
	 * Run a command without a shell and return its captured output.
	 *
	 * @param list<string>          $command
	 * @param array<string, string> $envOverrides
	 */
	public static function run(
		array $command,
		string $cwd,
		array $envOverrides = array(),
		int $timeoutSeconds = 30,
	): ScopeTestProcessResult {
		$descriptors = array(
			0 => array( 'pipe', 'r' ),
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		);

		$environment = getenv();
		if ( ! is_array( $environment ) ) {
			$environment = array();
		}
		foreach ( $envOverrides as $key => $value ) {
			$environment[ $key ] = $value;
		}
		$environment['LC_ALL']                 = 'C';
		$environment['LANG']                   = 'C';
		$environment['GIT_TERMINAL_PROMPT']    = '0';
		$environment['GIT_CONFIG_NOSYSTEM']    = '1';

		$process = @proc_open( $command, $descriptors, $pipes, $cwd, $environment );
		if ( ! is_resource( $process ) ) {
			throw new ScopeTestFailure( 'Unable to start process: ' . implode( ' ', $command ) );
		}

		fclose( $pipes[0] );
		stream_set_blocking( $pipes[1], false );
		stream_set_blocking( $pipes[2], false );

		$stdout   = '';
		$stderr   = '';
		$timedOut = false;
		$exitCode = null;
		$started  = hrtime( true );

		while ( true ) {
			$stdout .= (string) stream_get_contents( $pipes[1] );
			$stderr .= (string) stream_get_contents( $pipes[2] );

			$status = proc_get_status( $process );
			if ( ! $status['running'] ) {
				$exitCode = (int) $status['exitcode'];
				break;
			}

			if ( ( hrtime( true ) - $started ) / 1_000_000_000 > $timeoutSeconds ) {
				$timedOut = true;
				proc_terminate( $process, 9 );
				break;
			}

			usleep( 5_000 );
		}

		$stdout .= (string) stream_get_contents( $pipes[1] );
		$stderr .= (string) stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );

		$closed = proc_close( $process );
		if ( $timedOut ) {
			$exitCode = -1;
		} elseif ( null === $exitCode || $exitCode < 0 ) {
			$exitCode = $closed;
		}

		return new ScopeTestProcessResult(
			exitCode: (int) $exitCode,
			stdout: $stdout,
			stderr: $stderr,
			timedOut: $timedOut,
			durationSeconds: ( hrtime( true ) - $started ) / 1_000_000_000,
		);
	}
}

final class ScopeTestTempRoot {
	public readonly string $path;
	public readonly string $gitConfigPath;
	private bool $cleaned = false;

	public function __construct() {
		$requested = getenv( 'SCOPE_TEST_TMPDIR' );
		if ( is_string( $requested ) && '' !== trim( $requested ) ) {
			$base = rtrim( trim( $requested ), DIRECTORY_SEPARATOR );
			if ( ! is_dir( $base ) && ! @mkdir( $base, 0700, true ) && ! is_dir( $base ) ) {
				throw new ScopeTestSetupError( 'Cannot create SCOPE_TEST_TMPDIR: ' . $base );
			}
			if ( ! is_writable( $base ) ) {
				throw new ScopeTestSetupError( 'SCOPE_TEST_TMPDIR is not writable: ' . $base );
			}
		} else {
			$base = ( is_dir( '/tmp/opencode' ) && is_writable( '/tmp/opencode' ) )
				? '/tmp/opencode'
				: sys_get_temp_dir();
		}

		$this->path = $base . DIRECTORY_SEPARATOR . 'parsian-scope-tests-'
			. gmdate( 'Ymd-His' ) . '-' . getmypid() . '-' . bin2hex( random_bytes( 4 ) );
		if ( ! @mkdir( $this->path, 0700, true ) && ! is_dir( $this->path ) ) {
			throw new ScopeTestSetupError( 'Cannot create temporary test directory: ' . $this->path );
		}

		$this->gitConfigPath = $this->path . DIRECTORY_SEPARATOR . 'gitconfig-empty';
		if ( false === @file_put_contents( $this->gitConfigPath, '' ) ) {
			throw new ScopeTestSetupError( 'Cannot create temporary Git config: ' . $this->gitConfigPath );
		}
	}

	/**
	 * @return array<string, string>
	 */
	public function environment(): array {
		return array(
			'GIT_CONFIG_GLOBAL'      => $this->gitConfigPath,
			'GIT_CEILING_DIRECTORIES' => $this->path,
			'GIT_TERMINAL_PROMPT'    => '0',
		);
	}

	public function createCaseDirectory( string $name ): string {
		$safe = preg_replace( '/[^A-Za-z0-9_-]+/', '-', $name );
		$path = $this->path . DIRECTORY_SEPARATOR . $safe;
		if ( ! @mkdir( $path, 0700, true ) && ! is_dir( $path ) ) {
			throw new ScopeTestSetupError( 'Cannot create case directory: ' . $path );
		}
		return $path;
	}

	public function cleanup(): void {
		if ( $this->cleaned ) {
			return;
		}
		self::removeTree( $this->path );
		$this->cleaned = true;
	}

	/**
	 * Recursively remove a tree without following symlinks.
	 */
	public static function removeTree( string $path ): void {
		if ( is_link( $path ) || is_file( $path ) ) {
			@chmod( $path, 0600 );
			@unlink( $path );
			return;
		}
		if ( ! is_dir( $path ) ) {
			return;
		}
		@chmod( $path, 0700 );
		$entries = @scandir( $path );
		if ( is_array( $entries ) ) {
			foreach ( $entries as $entry ) {
				if ( '.' === $entry || '..' === $entry ) {
					continue;
				}
				self::removeTree( $path . DIRECTORY_SEPARATOR . $entry );
			}
		}
		@rmdir( $path );
	}
}

final class ScopeTestRepo {
	public readonly string $path;
	public ?string $baseSha = null;
	private ScopeTestTempRoot $tempRoot;

	public function __construct( string $path, ScopeTestTempRoot $tempRoot ) {
		$this->path     = $path;
		$this->tempRoot = $tempRoot;

		if ( ! @mkdir( $path, 0700, true ) && ! is_dir( $path ) ) {
			throw new ScopeTestSetupError( 'Cannot create fixture repository: ' . $path );
		}

		$this->git( array( 'init', '-q', '--initial-branch=main' ) );
		$this->git( array( 'config', 'user.name', 'Scope Guard Regression Tests' ) );
		$this->git( array( 'config', 'user.email', 'scope-guard-tests@example.invalid' ) );
		$this->git( array( 'config', 'commit.gpgsign', 'false' ) );
		$this->git( array( 'config', 'core.autocrlf', 'false' ) );
	}

	/**
	 * @param list<string> $args
	 */
	public function git( array $args, bool $allowFailure = false ): ScopeTestProcessResult {
		$result = ScopeTestProcess::run(
			array_merge( array( 'git' ), $args ),
			$this->path,
			$this->tempRoot->environment(),
		);

		if ( ! $allowFailure && 0 !== $result->exitCode ) {
			throw new ScopeTestFailure(
				sprintf(
					"git %s failed with exit %d\nstdout: %s\nstderr: %s",
					implode( ' ', $args ),
					$result->exitCode,
					self::excerpt( $result->stdout ),
					self::excerpt( $result->stderr ),
				),
			);
		}

		return $result;
	}

	public function write( string $relativePath, string $content ): void {
		$absolute = $this->absolute( $relativePath );
		$parent   = dirname( $absolute );
		if ( ! is_dir( $parent ) && ! @mkdir( $parent, 0700, true ) && ! is_dir( $parent ) ) {
			throw new ScopeTestFailure( 'Cannot create fixture directory: ' . $parent );
		}
		if ( false === @file_put_contents( $absolute, $content ) ) {
			throw new ScopeTestFailure( 'Cannot write fixture file: ' . $absolute );
		}
	}

	public function remove( string $relativePath ): void {
		$absolute = $this->absolute( $relativePath );
		if ( ! @unlink( $absolute ) ) {
			throw new ScopeTestFailure( 'Cannot delete fixture file: ' . $absolute );
		}
	}

	public function rename( string $from, string $to ): void {
		$this->git( array( 'mv', '--', $from, $to ) );
	}

	public function stage( string $relativePath ): void {
		$this->git( array( 'add', '--', $relativePath ) );
	}

	public function symlink( string $target, string $linkRelativePath ): void {
		$absolute = $this->absolute( $linkRelativePath );
		$parent   = dirname( $absolute );
		if ( ! is_dir( $parent ) && ! @mkdir( $parent, 0700, true ) && ! is_dir( $parent ) ) {
			throw new ScopeTestFailure( 'Cannot create symlink parent: ' . $parent );
		}
		if ( ! @symlink( $target, $absolute ) ) {
			throw new ScopeTestFailure( 'Cannot create fixture symlink: ' . $absolute );
		}
	}

	/**
	 * @param array<string, string> $files
	 */
	public function commitBase( array $files, string $message = 'base' ): string {
		foreach ( $files as $relativePath => $content ) {
			$this->write( $relativePath, $content );
		}
		$this->git( array( 'add', '--all' ) );
		$this->git( array( 'commit', '-q', '-m', $message ) );
		$this->baseSha = trim( $this->git( array( 'rev-parse', 'HEAD' ) )->stdout );
		return $this->baseSha;
	}

	public function absolute( string $relativePath ): string {
		return $this->path . DIRECTORY_SEPARATOR . $relativePath;
	}

	private static function excerpt( string $value, int $limit = 2000 ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '<empty>';
		}
		if ( strlen( $value ) <= $limit ) {
			return $value;
		}
		return substr( $value, 0, $limit ) . ' ... [truncated]';
	}
}

final class ScopeTestFixture {
	public readonly string $caseDirectory;
	public readonly string $repoPath;
	public readonly ScopeTestRepo $repo;
	public readonly ScopeTestTempRoot $tempRoot;
	public readonly string $toolPath;
	public string $taskPath = '';
	public string $registryPath = '';
	public ?string $baseSha = null;

	public function __construct(
		string $caseDirectory,
		string $toolPath,
		ScopeTestTempRoot $tempRoot,
	) {
		$this->caseDirectory = $caseDirectory;
		$this->toolPath      = $toolPath;
		$this->tempRoot      = $tempRoot;
		$this->repoPath      = $caseDirectory . DIRECTORY_SEPARATOR . 'repo';
		$this->repo          = new ScopeTestRepo( $this->repoPath, $tempRoot );
	}

	/**
	 * @param array<string, string> $files
	 */
	public function commitBase( array $files ): string {
		$this->baseSha = $this->repo->commitBase( $files );
		return $this->baseSha;
	}

	/**
	 * Write the task contract fixture.
	 *
	 * @param array<string, mixed> $options
	 */
	public function writeTask( array $options = array() ): string {
		if ( null === $this->baseSha ) {
			throw new ScopeTestFailure( 'commitBase() must run before writeTask().' );
		}

		$id              = (string) ( $options['id'] ?? 'PILOT-002' );
		$title           = (string) ( $options['title'] ?? 'Scope Guard regression fixture' );
		$status          = (string) ( $options['status'] ?? 'IN_PROGRESS' );
		$branch          = (string) ( $options['branch'] ?? 'main' );
		$workspacePath   = (string) ( $options['workspace_path'] ?? $this->repoPath );
		$baseCommit      = (string) ( $options['base_commit'] ?? $this->baseSha );
		$allowed         = array_values( $options['allowed'] ?? array( 'allowed/' ) );
		$forbidden       = array_values(
			$options['forbidden'] ?? array(
				'.agents/scripts/validate-task-scope.php',
				'.agents/state/',
				'wp-local/',
			)
		);
		$permissions     = array_values( $options['permissions'] ?? array( 'TEST_SCOPED' ) );
		$dependencies    = array_values( $options['dependencies'] ?? array() );
		$contracts       = array_values( $options['contracts'] ?? array( 'CONTRACT-SCOPE-001' ) );
		$acceptance      = array_values( $options['acceptance_criteria'] ?? array( 'Temporary regression fixture.' ) );
		$requiredTests   = array_values(
			$options['required_tests'] ?? array( 'php -l .agents/scripts/tests/validate-task-scope-test.php' )
		);
		$deliverables    = array_values( $options['deliverables'] ?? array( 'Temporary regression fixture.' ) );

		$lines   = array();
		$lines[] = 'id: ' . $this->yamlScalar( $id );
		$lines[] = 'title: ' . $this->yamlScalar( $title );
		$lines[] = 'status: ' . $this->yamlScalar( $status );
		$lines[] = 'owner:';
		$lines[] = '  agent: "qa-engineer"';
		$lines[] = '  role: "QA/Tooling Engineer"';
		$lines[] = 'workspace:';
		$lines[] = '  branch: ' . $this->yamlScalar( $branch );
		$lines[] = '  path: ' . $this->yamlScalar( $workspacePath );
		$lines[] = '  base_commit: ' . $this->yamlScalar( $baseCommit );
		$lines   = array_merge( $lines, $this->yamlSequence( 'permissions', $permissions, 0 ) );
		$lines[] = 'scope:';
		$lines   = array_merge( $lines, $this->yamlSequence( 'allowed', $allowed, 2 ) );
		$lines   = array_merge( $lines, $this->yamlSequence( 'forbidden', $forbidden, 2 ) );
		$lines[] = 'dependencies: ' . $this->yamlScalar( $dependencies );
		$lines   = array_merge( $lines, $this->yamlSequence( 'contracts', $contracts, 0 ) );
		$lines   = array_merge( $lines, $this->yamlSequence( 'acceptance_criteria', $acceptance, 0 ) );
		$lines[] = 'tests:';
		$lines   = array_merge( $lines, $this->yamlSequence( 'required', $requiredTests, 2 ) );
		$lines[] = '  evidence: []';
		$lines   = array_merge( $lines, $this->yamlSequence( 'deliverables', $deliverables, 0 ) );
		$lines[] = 'approvals:';
		$lines[] = '  architecture: "approved"';
		$lines[] = '  security: "required"';
		$lines[] = '  integration: "required"';

		$this->taskPath = $this->caseDirectory . DIRECTORY_SEPARATOR . 'task.yaml';
		$this->writeFile( $this->taskPath, implode( "\n", $lines ) . "\n" );
		return $this->taskPath;
	}

	/**
	 * @param list<array<string, mixed>> $entries
	 */
	public function writeRegistry( array $entries ): string {
		$lines   = array();
		$lines[] = 'version: 1';
		$lines[] = 'updated_at: "2026-11-14T00:00:00+00:00"';
		if ( array() === $entries ) {
			$lines[] = 'entries: []';
		} else {
			$lines[] = 'entries:';
			foreach ( $entries as $entry ) {
				$first = true;
				foreach ( $entry as $key => $value ) {
					$prefix  = $first ? '  - ' : '    ';
					$lines[] = $prefix . $key . ': ' . $this->yamlScalar( $value );
					$first   = false;
				}
			}
		}

		$this->registryPath = $this->caseDirectory . DIRECTORY_SEPARATOR . 'ownership-registry.yaml';
		$this->writeFile( $this->registryPath, implode( "\n", $lines ) . "\n" );
		return $this->registryPath;
	}

	public function writeRawRegistry( string $yaml ): string {
		$this->registryPath = $this->caseDirectory . DIRECTORY_SEPARATOR . 'ownership-registry.yaml';
		$this->writeFile( $this->registryPath, $yaml );
		return $this->registryPath;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function entry(
		string $path,
		string $taskId,
		string $mode = 'exclusive',
		?string $baseCommit = null,
	): array {
		$baseCommit = $baseCommit ?? $this->baseSha;
		if ( null === $baseCommit ) {
			throw new ScopeTestFailure( 'commitBase() must run before entry().' );
		}

		return array(
			'path'              => $path,
			'task_id'           => $taskId,
			'owner_agent'       => 'qa-engineer',
			'mode'              => $mode,
			'workspace'         => $this->repoPath,
			'base_commit'       => $baseCommit,
			'status'            => 'active',
			'lease_started_at'  => '2026-11-14T00:00:00+00:00',
			'lease_expires_at'  => '2099-01-01T00:00:00+00:00',
			'last_heartbeat_at' => '2026-11-14T00:00:00+00:00',
			'dependencies'      => array(),
			'integration_status' => 'pending',
		);
	}

	public function invoke(
		string $base,
		bool $separateArguments = false,
		?string $repoPath = null,
		?string $registryPath = null,
	): ScopeTestProcessResult {
		if ( '' === $this->taskPath || '' === $this->registryPath ) {
			throw new ScopeTestFailure( 'Task and registry fixtures must be written before invoke().' );
		}

		$pairs = array(
			array( '--task', $this->taskPath ),
			array( '--base', $base ),
			array( '--repo', $repoPath ?? $this->repoPath ),
			array( '--registry', $registryPath ?? $this->registryPath ),
		);

		$arguments = array();
		foreach ( $pairs as $pair ) {
			if ( $separateArguments ) {
				$arguments[] = $pair[0];
				$arguments[] = $pair[1];
			} else {
				$arguments[] = $pair[0] . '=' . $pair[1];
			}
		}
		$arguments[] = '--json';

		$command = array_merge( array( PHP_BINARY ?: 'php', $this->toolPath ), $arguments );

		// Run from the repository under inspection. The contract requires the
		// tool to honour --repo, but this also keeps simple `git` subprocess
		// implementations working without weakening the non-Git case.
		return ScopeTestProcess::run(
			$command,
			$repoPath ?? $this->repoPath,
			$this->tempRoot->environment(),
		);
	}

	public function writeCaseFile( string $relativePath, string $content ): string {
		$absolute = $this->caseDirectory . DIRECTORY_SEPARATOR . $relativePath;
		$parent   = dirname( $absolute );
		if ( ! is_dir( $parent ) && ! @mkdir( $parent, 0700, true ) && ! is_dir( $parent ) ) {
			throw new ScopeTestFailure( 'Cannot create case directory: ' . $parent );
		}
		if ( false === @file_put_contents( $absolute, $content ) ) {
			throw new ScopeTestFailure( 'Cannot write case file: ' . $absolute );
		}
		return $absolute;
	}

	public function makeCaseDirectory( string $relativePath ): string {
		$absolute = $this->caseDirectory . DIRECTORY_SEPARATOR . $relativePath;
		if ( ! @mkdir( $absolute, 0700, true ) && ! is_dir( $absolute ) ) {
			throw new ScopeTestFailure( 'Cannot create case directory: ' . $absolute );
		}
		return $absolute;
	}

	private function writeFile( string $path, string $content ): void {
		if ( false === @file_put_contents( $path, $content ) ) {
			throw new ScopeTestFailure( 'Cannot write fixture file: ' . $path );
		}
	}

	/**
	 * @param list<string> $items
	 * @return list<string>
	 */
	private function yamlSequence( string $key, array $items, int $indent ): array {
		$prefix = str_repeat( ' ', $indent );
		if ( array() === $items ) {
			return array( $prefix . $key . ': []' );
		}

		$lines   = array( $prefix . $key . ':' );
		$itemPad = str_repeat( ' ', $indent + 2 );
		foreach ( $items as $item ) {
			$lines[] = $itemPad . '- ' . $this->yamlScalar( $item );
		}
		return $lines;
	}

	private function yamlScalar( mixed $value ): string {
		if ( is_array( $value ) ) {
			if ( array() === $value ) {
				return '[]';
			}
			$encoded = array();
			foreach ( $value as $item ) {
				$encoded[] = $this->yamlScalar( $item );
			}
			return '[' . implode( ', ', $encoded ) . ']';
		}
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}
		if ( is_int( $value ) ) {
			return (string) $value;
		}
		if ( null === $value ) {
			return 'null';
		}

		$encoded = json_encode( (string) $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $encoded ) {
			throw new ScopeTestFailure( 'Cannot encode YAML scalar.' );
		}
		return $encoded;
	}
}

final class ScopeTestExpectation {
	/**
	 * @param list<string> $acceptableViolations
	 * @param list<string> $expectedChangedPaths
	 * @param list<string> $changedPathAlternatives
	 * @param list<string> $violationPathAlternatives
	 */
	public function __construct(
		public readonly int $exitCode,
		public readonly array $acceptableViolations = array(),
		public readonly array $expectedChangedPaths = array(),
		public readonly bool $exactChangedPaths = false,
		public readonly array $changedPathAlternatives = array(),
		public readonly array $violationPathAlternatives = array(),
		public readonly ?string $expectedTaskId = 'PILOT-002',
		public readonly ?string $expectedBaseSha = null,
		public readonly ?bool $expectedBaseMatches = null,
		public readonly ?bool $expectedInspectionComplete = null,
		public readonly bool $requireViolationDetails = false,
		public readonly ?string $expectedRepositoryPath = null,
	) {
	}
}

final class ScopeTestCaseOutcome {
	public function __construct(
		public readonly ScopeTestExpectation $expectation,
		public readonly ScopeTestProcessResult $result,
	) {
	}
}

final class ScopeTestTool {
	public static function resolve( string $repoRoot ): string {
		$override = getenv( 'SCOPE_GUARD_PATH' );
		if ( is_string( $override ) && '' !== trim( $override ) ) {
			$candidate = trim( $override );
			if ( ! self::isAbsolute( $candidate ) ) {
				$candidate = getcwd() . DIRECTORY_SEPARATOR . $candidate;
			}
			$resolved = realpath( $candidate );
			if ( false === $resolved || ! is_file( $resolved ) ) {
				throw new ScopeTestSetupError(
					'SCOPE_GUARD_PATH does not point to a readable file: ' . $override,
				);
			}
			return $resolved;
		}

		$candidate = $repoRoot . DIRECTORY_SEPARATOR . '.agents' . DIRECTORY_SEPARATOR
			. 'scripts' . DIRECTORY_SEPARATOR . 'validate-task-scope.php';
		if ( ! is_file( $candidate ) ) {
			throw new ScopeTestSetupError(
				'Scope Guard not found at ' . $candidate . "\n"
				. 'This suite cannot run without the tool and does not skip.' . "\n"
				. 'Before integration this failure is expected because PILOT-001 owns that file.' . "\n"
				. 'After integration, run the suite from the integrated checkout, or set '
				. 'SCOPE_GUARD_PATH to the absolute tool path.',
			);
		}

		$resolved = realpath( $candidate );
		if ( false === $resolved ) {
			throw new ScopeTestSetupError( 'Cannot resolve Scope Guard path: ' . $candidate );
		}
		return $resolved;
	}

	private static function isAbsolute( string $path ): bool {
		return str_starts_with( $path, '/' )
			|| str_starts_with( $path, '\\' )
			|| 1 === preg_match( '/^[A-Za-z]:[\\\\\\/]/', $path );
	}
}

final class ScopeTestAssertions {
	public static function validate( ScopeTestFixture $fixture, ScopeTestCaseOutcome $outcome ): void {
		$expectation = $outcome->expectation;
		$result      = $outcome->result;

		if ( $result->timedOut ) {
			throw new ScopeTestFailure( 'Scope Guard timed out after the per-case limit.' );
		}

		if ( $result->exitCode !== $expectation->exitCode ) {
			throw new ScopeTestFailure(
				sprintf(
					"Expected exit code %d, got %d.%s",
					$expectation->exitCode,
					$result->exitCode,
					self::outputExcerpt( $result ),
				),
			);
		}

		try {
			$json = json_decode( $result->stdout, true, 512, JSON_THROW_ON_ERROR );
		} catch ( JsonException $exception ) {
			throw new ScopeTestFailure(
				'--json output is not valid JSON: ' . $exception->getMessage()
				. self::outputExcerpt( $result ),
			);
		}

		if ( ! is_array( $json ) ) {
			throw new ScopeTestFailure( '--json output must decode to a JSON object.' );
		}

		$requiredKeys = array(
			'ok',
			'task_id',
			'base_sha',
			'repository',
			'base_commit_matches_contract',
			'changed_paths',
			'violations',
			'inspection',
		);
		foreach ( $requiredKeys as $key ) {
			if ( ! array_key_exists( $key, $json ) ) {
				throw new ScopeTestFailure( 'JSON output is missing required key: ' . $key );
			}
		}

		self::assertType( $json, 'ok', 'boolean' );
		self::assertType( $json, 'task_id', 'string' );
		self::assertType( $json, 'base_sha', 'string' );
		self::assertType( $json, 'repository', 'string' );
		self::assertType( $json, 'base_commit_matches_contract', 'boolean' );
		self::assertType( $json, 'changed_paths', 'array' );
		self::assertType( $json, 'violations', 'array' );
		self::assertType( $json, 'inspection', 'array' );

		if ( ! array_is_list( $json['changed_paths'] ) ) {
			throw new ScopeTestFailure( 'changed_paths must be a JSON array.' );
		}
		if ( ! array_is_list( $json['violations'] ) ) {
			throw new ScopeTestFailure( 'violations must be a JSON array.' );
		}

		$expectedOk = 0 === $expectation->exitCode;
		if ( $json['ok'] !== $expectedOk ) {
			throw new ScopeTestFailure(
				sprintf( 'Expected ok=%s for exit %d, got ok=%s.', $expectedOk ? 'true' : 'false', $expectation->exitCode, $json['ok'] ? 'true' : 'false' ),
			);
		}

		if ( null !== $expectation->expectedTaskId && $json['task_id'] !== $expectation->expectedTaskId ) {
			throw new ScopeTestFailure(
				sprintf( 'Expected task_id "%s", got "%s".', $expectation->expectedTaskId, $json['task_id'] ),
			);
		}

		if ( null !== $expectation->expectedBaseSha && $json['base_sha'] !== $expectation->expectedBaseSha ) {
			throw new ScopeTestFailure(
				sprintf( 'Expected base_sha "%s", got "%s".', $expectation->expectedBaseSha, $json['base_sha'] ),
			);
		}

		$expectedRepository = $expectation->expectedRepositoryPath ?? $fixture->repoPath;
		if ( 2 !== $expectation->exitCode ) {
			$actualRepository = realpath( $json['repository'] );
			$wantedRepository = realpath( $expectedRepository );
			if ( false === $actualRepository || false === $wantedRepository || $actualRepository !== $wantedRepository ) {
				throw new ScopeTestFailure(
					sprintf( 'Expected repository "%s", got "%s".', $expectedRepository, $json['repository'] ),
				);
			}
		}

		if ( null !== $expectation->expectedBaseMatches
			&& $json['base_commit_matches_contract'] !== $expectation->expectedBaseMatches ) {
			throw new ScopeTestFailure(
				sprintf(
					'Expected base_commit_matches_contract=%s, got %s.',
					$expectation->expectedBaseMatches ? 'true' : 'false',
					$json['base_commit_matches_contract'] ? 'true' : 'false',
				),
			);
		}

		$changedPaths = self::changedPaths( $json['changed_paths'] );
		if ( $expectation->exactChangedPaths ) {
			$actual = $changedPaths;
			$wanted = $expectation->expectedChangedPaths;
			sort( $actual );
			sort( $wanted );
			if ( $actual !== $wanted ) {
				throw new ScopeTestFailure(
					'Changed paths do not match exactly. Expected: '
					. self::formatList( $wanted ) . '; actual: ' . self::formatList( $actual ) . '.',
				);
			}
		} else {
			foreach ( $expectation->expectedChangedPaths as $path ) {
				if ( ! in_array( $path, $changedPaths, true ) ) {
					throw new ScopeTestFailure(
						'Changed paths are missing "' . $path . '". Actual: ' . self::formatList( $changedPaths ) . '.',
					);
				}
			}
		}

		if ( array() !== $expectation->changedPathAlternatives
			&& ! self::containsAny( $changedPaths, $expectation->changedPathAlternatives ) ) {
			throw new ScopeTestFailure(
				'Changed paths do not contain any of: '
				. self::formatList( $expectation->changedPathAlternatives )
				. '. Actual: ' . self::formatList( $changedPaths ) . '.',
			);
		}

		if ( null !== $expectation->expectedInspectionComplete ) {
			foreach ( array( 'tracked', 'staged', 'untracked' ) as $key ) {
				if ( ! array_key_exists( $key, $json['inspection'] ) || ! is_int( $json['inspection'][ $key ] ) ) {
					throw new ScopeTestFailure( 'inspection.' . $key . ' must be an integer.' );
				}
			}
			if ( ! array_key_exists( 'complete', $json['inspection'] ) || ! is_bool( $json['inspection']['complete'] ) ) {
				throw new ScopeTestFailure( 'inspection.complete must be a boolean.' );
			}
			if ( $json['inspection']['complete'] !== $expectation->expectedInspectionComplete ) {
				throw new ScopeTestFailure(
					sprintf(
						'Expected inspection.complete=%s, got %s.',
						$expectation->expectedInspectionComplete ? 'true' : 'false',
						$json['inspection']['complete'] ? 'true' : 'false',
					),
				);
			}
		}

		$violationNames = array();
		foreach ( $json['violations'] as $index => $violation ) {
			if ( ! is_array( $violation ) ) {
				throw new ScopeTestFailure( 'violations[' . $index . '] must be a JSON object.' );
			}
			if ( ! array_key_exists( 'violation', $violation ) || ! is_string( $violation['violation'] ) || '' === $violation['violation'] ) {
				throw new ScopeTestFailure( 'violations[' . $index . '].violation must be a non-empty string.' );
			}
			$violationNames[] = $violation['violation'];

			if ( $expectation->requireViolationDetails ) {
				self::assertViolationDetails( $violation, $index, $expectation );
			}
		}

		if ( array() === $expectation->acceptableViolations ) {
			if ( array() !== $violationNames ) {
				throw new ScopeTestFailure( 'Expected no violations, got: ' . self::formatList( $violationNames ) . '.' );
			}
		} elseif ( array() === array_intersect( $expectation->acceptableViolations, $violationNames ) ) {
			throw new ScopeTestFailure(
				'Expected at least one of: ' . self::formatList( $expectation->acceptableViolations )
				. '. Actual violations: ' . self::formatList( $violationNames ) . '.',
			);
		}

		if ( array() !== $expectation->violationPathAlternatives ) {
			$violationPaths = array();
			foreach ( $json['violations'] as $violation ) {
				if ( isset( $violation['path'] ) && is_string( $violation['path'] ) ) {
					$violationPaths[] = $violation['path'];
				}
			}
			if ( ! self::containsAny( $violationPaths, $expectation->violationPathAlternatives ) ) {
				throw new ScopeTestFailure(
					'Violation paths do not contain any of: '
					. self::formatList( $expectation->violationPathAlternatives )
					. '. Actual: ' . self::formatList( $violationPaths ) . '.',
				);
			}
		}

		if ( 0 === $result->exitCode && false === $json['inspection']['complete'] ) {
			throw new ScopeTestFailure( 'Scope Guard returned exit 0 with incomplete inspection.' );
		}
	}

	/**
	 * @param array<string, mixed> $violation
	 */
	private static function assertViolationDetails(
		array $violation,
		int $index,
		ScopeTestExpectation $expectation,
	): void {
		$prefix = 'violations[' . $index . ']';

		if ( ! array_key_exists( 'task_id', $violation ) || ! is_string( $violation['task_id'] ) || '' === $violation['task_id'] ) {
			throw new ScopeTestFailure( $prefix . '.task_id must be a non-empty string.' );
		}
		if ( ! array_key_exists( 'path', $violation ) || ! is_string( $violation['path'] ) || '' === $violation['path'] ) {
			throw new ScopeTestFailure( $prefix . '.path must be a non-empty string.' );
		}
		if ( ! array_key_exists( 'expected_owner', $violation ) || ! is_string( $violation['expected_owner'] ) || '' === trim( $violation['expected_owner'] ) ) {
			throw new ScopeTestFailure( $prefix . '.expected_owner must be a non-empty string.' );
		}
		if ( ! array_key_exists( 'base_sha', $violation ) || ! is_string( $violation['base_sha'] ) ) {
			throw new ScopeTestFailure( $prefix . '.base_sha must be a string.' );
		}
		if ( 1 !== preg_match( '/^[0-9a-f]{40}$/', $violation['base_sha'] ) ) {
			throw new ScopeTestFailure( $prefix . '.base_sha must be a 40-character lowercase hexadecimal SHA.' );
		}
		if ( null !== $expectation->expectedBaseSha && $violation['base_sha'] !== $expectation->expectedBaseSha ) {
			throw new ScopeTestFailure(
				sprintf(
					'%s.base_sha must equal the inspected base "%s", got "%s".',
					$prefix,
					$expectation->expectedBaseSha,
					$violation['base_sha'],
				),
			);
		}
		if ( ! array_key_exists( 'remediation', $violation ) || ! is_string( $violation['remediation'] ) || '' === trim( $violation['remediation'] ) ) {
			throw new ScopeTestFailure( $prefix . '.remediation must be a non-empty string.' );
		}
		if ( array_key_exists( 'actual_owner', $violation )
			&& null !== $violation['actual_owner']
			&& ! is_string( $violation['actual_owner'] ) ) {
			throw new ScopeTestFailure( $prefix . '.actual_owner must be a string or null.' );
		}
	}

	/**
	 * @param array<int, mixed> $changedPaths
	 * @return list<string>
	 */
	private static function changedPaths( array $changedPaths ): array {
		$paths = array();
		foreach ( $changedPaths as $index => $item ) {
			if ( ! is_array( $item ) ) {
				throw new ScopeTestFailure( 'changed_paths[' . $index . '] must be a JSON object.' );
			}
			if ( ! array_key_exists( 'path', $item ) || ! is_string( $item['path'] ) || '' === $item['path'] ) {
				throw new ScopeTestFailure( 'changed_paths[' . $index . '].path must be a non-empty string.' );
			}
			if ( ! array_key_exists( 'status', $item ) || ! is_string( $item['status'] ) || '' === $item['status'] ) {
				throw new ScopeTestFailure( 'changed_paths[' . $index . '].status must be a non-empty string.' );
			}
			$paths[] = $item['path'];
		}
		return $paths;
	}

	/**
	 * @param list<string> $haystack
	 * @param list<string> $needles
	 */
	private static function containsAny( array $haystack, array $needles ): bool {
		foreach ( $haystack as $value ) {
			foreach ( $needles as $needle ) {
				if ( str_contains( $value, $needle ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function assertType( array $data, string $key, string $type ): void {
		$valid = match ( $type ) {
			'string'  => is_string( $data[ $key ] ),
			'boolean' => is_bool( $data[ $key ] ),
			'array'   => is_array( $data[ $key ] ),
			default   => false,
		};
		if ( ! $valid ) {
			throw new ScopeTestFailure( 'JSON key ' . $key . ' must be of type ' . $type . '.' );
		}
	}

	/**
	 * @param list<string> $values
	 */
	private static function formatList( array $values ): string {
		return array() === $values ? '<none>' : implode( ', ', $values );
	}

	private static function outputExcerpt( ScopeTestProcessResult $result ): string {
		$stdout = trim( $result->stdout );
		$stderr = trim( $result->stderr );
		if ( strlen( $stdout ) > 4000 ) {
			$stdout = substr( $stdout, 0, 4000 ) . ' ... [truncated]';
		}
		if ( strlen( $stderr ) > 2000 ) {
			$stderr = substr( $stderr, 0, 2000 ) . ' ... [truncated]';
		}
		return "\nstdout: " . ( '' === $stdout ? '<empty>' : $stdout )
			. "\nstderr: " . ( '' === $stderr ? '<empty>' : $stderr );
	}
}

final class ScopeTestRunner {
	public function __construct(
		private string $toolPath,
		private ScopeTestTempRoot $tempRoot,
	) {
	}

	/**
	 * @param array<string, Closure(ScopeTestFixture): ScopeTestCaseOutcome> $cases
	 */
	public function run( array $cases ): int {
		$started = hrtime( true );

		fwrite( STDOUT, "Parsian Agent Scope Guard regression suite\n" );
		fwrite( STDOUT, 'PHP: ' . PHP_VERSION . "\n" );
		fwrite( STDOUT, 'Tool: ' . $this->toolPath . "\n" );
		fwrite( STDOUT, 'Temp root: ' . $this->tempRoot->path . "\n" );
		fwrite( STDOUT, 'Cases: ' . count( $cases ) . "\n\n" );

		$passed = 0;
		$failed = 0;

		foreach ( $cases as $name => $case ) {
			$caseDirectory = $this->tempRoot->createCaseDirectory( $name );
			try {
				$fixture = new ScopeTestFixture( $caseDirectory, $this->toolPath, $this->tempRoot );
				$outcome = $case( $fixture );
				if ( ! $outcome instanceof ScopeTestCaseOutcome ) {
					throw new ScopeTestFailure( 'Case did not return a ScopeTestCaseOutcome.' );
				}
				ScopeTestAssertions::validate( $fixture, $outcome );
				fwrite( STDOUT, 'PASS  ' . $name . "\n" );
				++$passed;
			} catch ( ScopeTestFailure $failure ) {
				fwrite( STDOUT, 'FAIL  ' . $name . "\n" );
				self::writeIndented( $failure->getMessage() );
				++$failed;
			} catch ( Throwable $throwable ) {
				fwrite( STDOUT, 'FAIL  ' . $name . "\n" );
				self::writeIndented( 'Unexpected ' . $throwable::class . ': ' . $throwable->getMessage() );
				++$failed;
			}
		}

		$elapsed = ( hrtime( true ) - $started ) / 1_000_000_000;
		fwrite(
			STDOUT,
			sprintf(
				"\nSummary: %d passed, %d failed, %d total in %.2fs\n",
				$passed,
				$failed,
				count( $cases ),
				$elapsed,
			),
		);
		fwrite( STDOUT, 0 === $failed ? "RESULT: PASS\n" : "RESULT: FAIL\n" );

		return 0 === $failed ? 0 : 1;
	}

	private static function writeIndented( string $message ): void {
		$lines = preg_split( "/\r\n|\n|\r/", $message ) ?: array( $message );
		foreach ( $lines as $line ) {
			fwrite( STDOUT, '      ' . $line . "\n" );
		}
	}
}
