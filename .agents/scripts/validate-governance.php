#!/usr/bin/env php
<?php
/**
 * Parsian Theme governance validator.
 *
 * Zero-dependency structural validation for the .agents governance baseline.
 * It does not execute tests, enforce approvals, create worktrees, or prevent writes.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "This validator is CLI-only.\n");
	exit(2);
}

/**
 * Minimal YAML reader for the governance subset used in this repository.
 *
 * Supported: nested mappings, sequences of scalars/mappings, inline scalar
 * lists, quoted strings, booleans, null, integers, floats, full-line comments.
 * Not supported: anchors, aliases, multi-document streams, block scalars,
 * flow mappings, or inline comments after values.
 */
final class GovernanceYaml {
	public static function parseFile(string $path): array {
		$raw = @file_get_contents($path);
		if (false === $raw) {
			throw new RuntimeException('Cannot read YAML file: ' . $path);
		}
		return self::parse($raw, $path);
	}

	public static function parse(string $raw, string $source = '<memory>'): array {
		$tokens = array();
		$lines = preg_split("/\r\n|\n|\r/", $raw);
		foreach ($lines as $index => $line) {
			$line = rtrim($line);
			if ('' === trim($line)) {
				continue;
			}
			if (preg_match('/^\s*#/', $line)) {
				continue;
			}
			if (false !== strpos($line, "\t")) {
				throw new RuntimeException(sprintf('%s:%d: tabs are not allowed', $source, $index + 1));
			}
			$indent = strlen($line) - strlen(ltrim($line, ' '));
			if (0 !== $indent % 2) {
				throw new RuntimeException(sprintf('%s:%d: indentation must be a multiple of 2', $source, $index + 1));
			}
			$tokens[] = array(
				'indent' => $indent,
				'text'   => substr($line, $indent),
				'line'   => $index + 1,
			);
		}
		if (array() === $tokens) {
			return array();
		}
		$i = 0;
		$value = self::parseBlock($tokens, $i, 0, $source);
		if ($i < count($tokens)) {
			throw new RuntimeException(sprintf('%s:%d: unexpected trailing content', $source, $tokens[ $i ]['line']));
		}
		return is_array($value) ? $value : array();
	}

	private static function parseBlock(array $tokens, int &$i, int $indent, string $source) {
		$token = $tokens[ $i ];
		if ($token['indent'] !== $indent) {
			throw new RuntimeException(sprintf('%s:%d: unexpected indentation', $source, $token['line']));
		}
		if (0 === strpos($token['text'], '-') && (strlen($token['text']) === 1 || ' ' === $token['text'][1])) {
			return self::parseSequence($tokens, $i, $indent, $source);
		}
		return self::parseMapping($tokens, $i, $indent, $source);
	}

	private static function parseMapping(array $tokens, int &$i, int $indent, string $source): array {
		$map = array();
		while ($i < count($tokens)) {
			$token = $tokens[ $i ];
			if ($token['indent'] < $indent) {
				break;
			}
			if ($token['indent'] > $indent) {
				throw new RuntimeException(sprintf('%s:%d: unexpected indentation', $source, $token['line']));
			}
			if (0 === strpos($token['text'], '-')) {
				break;
			}
			$pos = strpos($token['text'], ':');
			if (false === $pos) {
				throw new RuntimeException(sprintf('%s:%d: expected key: value', $source, $token['line']));
			}
			$key = trim(substr($token['text'], 0, $pos));
			$rest = trim(substr($token['text'], $pos + 1));
			$key = self::unquote($key);
			if ('' === $key) {
				throw new RuntimeException(sprintf('%s:%d: empty key', $source, $token['line']));
			}
			$i++;
			if ('' === $rest) {
				if ($i < count($tokens) && $tokens[ $i ]['indent'] > $indent) {
					$map[ $key ] = self::parseBlock($tokens, $i, $tokens[ $i ]['indent'], $source);
				} else {
					$map[ $key ] = null;
				}
				continue;
			}
			$map[ $key ] = self::scalar($rest);
		}
		return $map;
	}

	private static function parseSequence(array $tokens, int &$i, int $indent, string $source): array {
		$list = array();
		while ($i < count($tokens)) {
			$token = $tokens[ $i ];
			if ($token['indent'] < $indent) {
				break;
			}
			if ($token['indent'] > $indent) {
				throw new RuntimeException(sprintf('%s:%d: unexpected indentation', $source, $token['line']));
			}
			if (0 !== strpos($token['text'], '-')) {
				break;
			}
			$rest = trim(substr($token['text'], 1));
			$i++;
			if ('' === $rest) {
				if ($i < count($tokens) && $tokens[ $i ]['indent'] > $indent) {
					$list[] = self::parseBlock($tokens, $i, $tokens[ $i ]['indent'], $source);
				} else {
					$list[] = null;
				}
				continue;
			}
			if (preg_match('/^([^:]+):(.*)$/', $rest, $matches) && ! preg_match('#^https?://#', $rest)) {
				$key = self::unquote(trim($matches[1]));
				$value = trim($matches[2]);
				$item = array();
				if ('' === $value) {
					if ($i < count($tokens) && $tokens[ $i ]['indent'] > $indent) {
						$item[ $key ] = self::parseBlock($tokens, $i, $tokens[ $i ]['indent'], $source);
					} else {
						$item[ $key ] = null;
					}
				} else {
					$item[ $key ] = self::scalar($value);
				}
				if ($i < count($tokens) && $tokens[ $i ]['indent'] === $indent + 2) {
					$extra = self::parseMapping($tokens, $i, $indent + 2, $source);
					$item = array_merge($item, $extra);
				}
				$list[] = $item;
				continue;
			}
			$list[] = self::scalar($rest);
		}
		return $list;
	}

	private static function scalar(string $value) {
		$value = trim($value);
		if ('' === $value) {
			return '';
		}
		if ('[]' === $value) {
			return array();
		}
		if (preg_match('/^\[(.*)\]$/', $value, $matches)) {
			$inner = trim($matches[1]);
			if ('' === $inner) {
				return array();
			}
			$out = array();
			foreach (str_getcsv($inner, ',', '"', '\\') as $item) {
				$out[] = self::scalar(trim($item));
			}
			return $out;
		}
		if ('null' === strtolower($value) || '~' === $value) {
			return null;
		}
		if ('true' === strtolower($value)) {
			return true;
		}
		if ('false' === strtolower($value)) {
			return false;
		}
		if (preg_match('/^-?\d+$/', $value)) {
			return (int) $value;
		}
		if (preg_match('/^-?\d+\.\d+$/', $value)) {
			return (float) $value;
		}
		return self::unquote($value);
	}

	private static function unquote(string $value): string {
		$value = trim($value);
		if (strlen($value) >= 2 && '"' === $value[0] && '"' === substr($value, -1)) {
			return str_replace('\\"', '"', substr($value, 1, -1));
		}
		if (strlen($value) >= 2 && "'" === $value[0] && "'" === substr($value, -1)) {
			return str_replace("''", "'", substr($value, 1, -1));
		}
		return $value;
	}
}

final class GovernanceValidator {
	private string $root;
	private array $errors = array();
	private array $passes = array();
	private bool $bootstrapScope;

	private const TASK_STATES = array(
		'PROPOSED', 'SPECIFIED', 'READY', 'ASSIGNED', 'IN_PROGRESS',
		'IMPLEMENTATION_COMPLETE', 'CODE_REVIEW', 'QA', 'SECURITY_REVIEW',
		'INTEGRATION_READY', 'INTEGRATING', 'INTEGRATED', 'VERIFIED', 'COMPLETE',
		'BLOCKED', 'CANCELLED', 'REJECTED', 'SUPERSEDED',
	);

	private const PERMISSIONS = array(
		'OBSERVE', 'ANALYZE', 'PROPOSE', 'WRITE_SCOPED', 'TEST_SCOPED', 'INTEGRATE', 'RELEASE_PREPARE',
	);

	private const ROLE_SLUGS = array(
		'orchestrator',
		'product-analyst',
		'solution-architect',
		'backend-engineer',
		'frontend-engineer',
		'data-migration-engineer',
		'security-engineer',
		'qa-engineer',
		'code-reviewer',
		'devops-engineer',
		'documentation-engineer',
	);

	private const MODES = array(
		'DISCOVERY', 'PLANNING', 'ARCHITECTURE', 'EXECUTION', 'INTEGRATION',
		'VERIFICATION', 'RELEASE_PREPARATION', 'AWAITING_APPROVAL',
	);

	private const TRANSITIONS = array(
		'PROPOSED' => array( 'SPECIFIED', 'CANCELLED' ),
		'SPECIFIED' => array( 'READY', 'CANCELLED', 'SUPERSEDED' ),
		'READY' => array( 'ASSIGNED', 'BLOCKED', 'CANCELLED' ),
		'ASSIGNED' => array( 'IN_PROGRESS', 'BLOCKED', 'CANCELLED' ),
		'IN_PROGRESS' => array( 'IMPLEMENTATION_COMPLETE', 'BLOCKED', 'CANCELLED' ),
		'IMPLEMENTATION_COMPLETE' => array( 'CODE_REVIEW', 'BLOCKED' ),
		'CODE_REVIEW' => array( 'QA', 'IN_PROGRESS', 'REJECTED', 'BLOCKED' ),
		'QA' => array( 'SECURITY_REVIEW', 'IN_PROGRESS', 'REJECTED', 'BLOCKED' ),
		'SECURITY_REVIEW' => array( 'INTEGRATION_READY', 'IN_PROGRESS', 'REJECTED', 'BLOCKED' ),
		'INTEGRATION_READY' => array( 'INTEGRATING', 'BLOCKED' ),
		'INTEGRATING' => array( 'INTEGRATED', 'BLOCKED', 'REJECTED' ),
		'INTEGRATED' => array( 'VERIFIED', 'REJECTED' ),
		'VERIFIED' => array( 'COMPLETE', 'SUPERSEDED' ),
		'BLOCKED' => array( 'ASSIGNED', 'IN_PROGRESS', 'CANCELLED', 'SUPERSEDED' ),
		'CANCELLED' => array(),
		'REJECTED' => array( 'SUPERSEDED' ),
		'SUPERSEDED' => array(),
	);

	private const FORBIDDEN_WRITE_PREFIXES = array(
		'.git/', 'node_modules/', 'vendor/', 'dist/', 'wp-local/site/', 'wp-local/mysql/', 'wp-local/backups/',
	);

	public function __construct(string $root, bool $bootstrapScope = false) {
		$this->root = rtrim($root, DIRECTORY_SEPARATOR);
		$this->bootstrapScope = $bootstrapScope;
	}

	public function runMain(): bool {
		$this->checkRequiredFiles();
		$this->checkMarkdownLinks();
		$this->checkDecisions();
		$this->checkSchemas();
		$this->checkTemplates();
		$this->checkOwnershipRegistry();
		$this->checkExecutionState();
		$this->checkEventLog();
		$this->checkProtectedWork();
		$this->checkIgnoreRules();
		if ($this->bootstrapScope) {
			$this->checkBootstrapScope();
		}
		return $this->summary('governance');
	}

	public function runSelfTest(): bool {
		$fixtures = $this->root . '/.agents/scripts/fixtures';
		$cases = array();

		$taskValid = $this->loadYaml($fixtures . '/task-valid.yaml');
		$cases[] = array( 'task-valid', $this->validateTask($taskValid, array( 'TASK-001' )), true );

		$taskMissingAcceptance = $this->loadYaml($fixtures . '/task-invalid-missing-acceptance.yaml');
		$cases[] = array( 'task-invalid-missing-acceptance', $this->validateTask($taskMissingAcceptance, array( 'TASK-002' )), false );

		$taskInvalidPermission = $this->loadYaml($fixtures . '/task-invalid-permission.yaml');
		$cases[] = array( 'task-invalid-permission', $this->validateTask($taskInvalidPermission, array( 'TASK-003' )), false );

		$taskInvalidOwner = $this->loadYaml($fixtures . '/task-invalid-owner.yaml');
		$cases[] = array( 'task-invalid-owner', $this->validateTask($taskInvalidOwner, array( 'TASK-006' )), false );

		$taskInvalidDependency = $this->loadYaml($fixtures . '/task-invalid-dependency.yaml');
		$cases[] = array( 'task-invalid-dependency', $this->validateTask($taskInvalidDependency, array( 'TASK-004' )), false );

		$taskScopeOverlap = $this->loadYaml($fixtures . '/task-invalid-scope-overlap.yaml');
		$cases[] = array( 'task-invalid-scope-overlap', $this->validateTask($taskScopeOverlap, array( 'TASK-005' )), false );

		$transitionValid = $this->loadYaml($fixtures . '/task-transition-valid.yaml');
		$cases[] = array(
			'task-transition-valid',
			$this->isTransitionAllowed((string) $transitionValid['from'], (string) $transitionValid['to']) ? array() : array( 'valid transition rejected' ),
			true,
		);

		$transitionInvalid = $this->loadYaml($fixtures . '/task-transition-invalid.yaml');
		$invalidTransitionAllowed = $this->isTransitionAllowed((string) $transitionInvalid['from'], (string) $transitionInvalid['to']);
		$cases[] = array(
			'task-transition-invalid',
			$invalidTransitionAllowed ? array( 'invalid transition accepted' ) : array( 'invalid transition rejected as expected' ),
			false,
		);

		$ownershipValid = $this->loadYaml($fixtures . '/ownership-valid.yaml');
		$cases[] = array( 'ownership-valid', $this->validateOwnership($ownershipValid, array( 'TASK-001', 'TASK-002' )), true );

		$ownershipDuplicate = $this->loadYaml($fixtures . '/ownership-duplicate.yaml');
		$cases[] = array( 'ownership-duplicate', $this->validateOwnership($ownershipDuplicate, array( 'TASK-001', 'TASK-002' )), false );

		$ownershipOverlap = $this->loadYaml($fixtures . '/ownership-overlap.yaml');
		$cases[] = array( 'ownership-overlap', $this->validateOwnership($ownershipOverlap, array( 'TASK-001', 'TASK-002' )), false );

		$ownershipForbidden = $this->loadYaml($fixtures . '/ownership-forbidden.yaml');
		$cases[] = array( 'ownership-forbidden', $this->validateOwnership($ownershipForbidden, array( 'TASK-001' )), false );

		$ownershipInvalidOwner = $this->loadYaml($fixtures . '/ownership-invalid-owner.yaml');
		$cases[] = array( 'ownership-invalid-owner', $this->validateOwnership($ownershipInvalidOwner, array( 'TASK-001' )), false );

		$scopeValid = $this->loadYaml($fixtures . '/scope-change-valid.yaml');
		$cases[] = array( 'scope-change-valid', $this->validateScopeChange($scopeValid), true );

		$scopeInvalid = $this->loadYaml($fixtures . '/scope-change-invalid.yaml');
		$cases[] = array( 'scope-change-invalid', $this->validateScopeChange($scopeInvalid), false );

		$decisionFixture = @file_get_contents($fixtures . '/decisions-duplicate.md');
		$cases[] = array( 'decisions-duplicate', $this->validateDecisionRegister((string) $decisionFixture), false );

		foreach ( $cases as $case ) {
			list( $label, $errors, $expectValid ) = $case;
			$valid = array() === $errors;
			if ( $valid === $expectValid ) {
				$this->pass( 'self-test: ' . $label . ' (expected ' . ( $expectValid ? 'valid' : 'invalid' ) . ')' );
			} else {
				$this->error( 'self-test: ' . $label . ' expected ' . ( $expectValid ? 'valid' : 'invalid' ) . ', got ' . ( $valid ? 'valid' : 'invalid' ) . ' — ' . implode( '; ', $errors ) );
			}
		}
		return $this->summary('self-test');
	}

	private function checkRequiredFiles(): void {
		$required = array(
			'AGENTS.md',
			'.agents/README.md',
			'.agents/orchestrator.md',
			'.agents/roles/product-analyst.md',
			'.agents/roles/solution-architect.md',
			'.agents/roles/backend-engineer.md',
			'.agents/roles/frontend-engineer.md',
			'.agents/roles/data-migration-engineer.md',
			'.agents/roles/security-engineer.md',
			'.agents/roles/qa-engineer.md',
			'.agents/roles/code-reviewer.md',
			'.agents/roles/devops-engineer.md',
			'.agents/roles/documentation-engineer.md',
			'.agents/policies/permissions-and-approvals.md',
			'.agents/policies/workspace-isolation.md',
			'.agents/policies/file-ownership.md',
			'.agents/policies/task-lifecycle.md',
			'.agents/policies/dependency-management.md',
			'.agents/policies/architecture-contracts.md',
			'.agents/policies/independent-review.md',
			'.agents/policies/qa-and-verification.md',
			'.agents/policies/integration-and-conflict-resolution.md',
			'.agents/policies/security-and-data-governance.md',
			'.agents/policies/git-and-branch-governance.md',
			'.agents/policies/release-and-deployment.md',
			'.agents/policies/failure-and-recovery.md',
			'.agents/policies/context-and-handoff.md',
			'.agents/policies/execution-state.md',
			'.agents/policies/enforcement.md',
			'.agents/policies/pilot-plan.md',
			'.agents/templates/task-contract.yaml',
			'.agents/templates/scope-change.yaml',
			'.agents/templates/handoff-report.md',
			'.agents/templates/review-report.md',
			'.agents/templates/adr.md',
			'.agents/templates/release-checklist.md',
			'.agents/schemas/task.schema.json',
			'.agents/schemas/ownership.schema.json',
			'.agents/schemas/execution-state.schema.json',
			'.agents/schemas/event.schema.json',
			'.agents/state/project-state.yaml',
			'.agents/state/ownership-registry.yaml',
			'.agents/state/event-log.yaml',
			'.agents/state/protected-work.yaml',
			'.agents/decisions/GOVERNANCE_DECISION_REGISTER.md',
			'.agents/scripts/validate-governance.php',
			'.agents/scripts/fixtures/README.md',
			'.agents/scripts/fixtures/task-valid.yaml',
			'.agents/scripts/fixtures/task-invalid-missing-acceptance.yaml',
			'.agents/scripts/fixtures/task-invalid-permission.yaml',
			'.agents/scripts/fixtures/task-invalid-owner.yaml',
			'.agents/scripts/fixtures/task-invalid-dependency.yaml',
			'.agents/scripts/fixtures/task-invalid-scope-overlap.yaml',
			'.agents/scripts/fixtures/task-transition-valid.yaml',
			'.agents/scripts/fixtures/task-transition-invalid.yaml',
			'.agents/scripts/fixtures/ownership-valid.yaml',
			'.agents/scripts/fixtures/ownership-duplicate.yaml',
			'.agents/scripts/fixtures/ownership-overlap.yaml',
			'.agents/scripts/fixtures/ownership-forbidden.yaml',
			'.agents/scripts/fixtures/ownership-invalid-owner.yaml',
			'.agents/scripts/fixtures/scope-change-valid.yaml',
			'.agents/scripts/fixtures/scope-change-invalid.yaml',
			'.agents/scripts/fixtures/decisions-duplicate.md',
		);
		$missing = array();
		foreach ( $required as $relative ) {
			if ( ! is_file( $this->root . '/' . $relative ) ) {
				$missing[] = $relative;
			}
		}
		if ( array() === $missing ) {
			$this->pass( 'required files present (' . count( $required ) . ')' );
		} else {
			foreach ( $missing as $relative ) {
				$this->error( 'missing required file: ' . $relative );
			}
		}
	}

	private function checkMarkdownLinks(): void {
		$files = array( $this->root . '/AGENTS.md' );
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $this->root . '/.agents', FilesystemIterator::SKIP_DOTS )
		);
		foreach ( $iterator as $file ) {
			if ( $file->isFile() && 'md' === strtolower( $file->getExtension() ) ) {
				$files[] = $file->getPathname();
			}
		}
		$checked = 0;
		foreach ( $files as $file ) {
			$content = (string) @file_get_contents( $file );
			if ( ! preg_match_all( '/\[[^\]]*\]\(([^)]+)\)/u', $content, $matches ) ) {
				continue;
			}
			foreach ( $matches[1] as $link ) {
				$link = trim( $link );
				if ( '' === $link || preg_match( '~^(https?://|mailto:|#)~i', $link ) ) {
					continue;
				}
				$path = preg_split( '/[?#]/', $link, 2 )[0];
				if ( '' === $path || '/' === $path[0] ) {
					continue;
				}
				$target = dirname( $file ) . '/' . $path;
				$real = realpath( $target );
				$checked++;
				if ( false === $real || ! file_exists( $real ) ) {
					$this->error( 'broken internal reference in ' . $this->relative( $file ) . ': ' . $link );
				}
			}
		}
		$this->pass( 'internal Markdown references checked (' . $checked . ')' );
	}

	private function checkDecisions(): void {
		$path = $this->root . '/.agents/decisions/GOVERNANCE_DECISION_REGISTER.md';
		$content = (string) @file_get_contents( $path );
		$errors = $this->validateDecisionRegister( $content );
		if ( array() === $errors ) {
			$this->pass( 'decision register MAG-01..MAG-08 accepted' );
		} else {
			foreach ( $errors as $error ) {
				$this->error( $error );
			}
		}
	}

	private function validateDecisionRegister(string $content): array {
		$errors = array();
		preg_match_all( '/^##\s+(MAG-\d{2})\s+[\x{2014}\x{2013}-]\s+(.+)$/mu', $content, $matches, PREG_SET_ORDER );
		$ids = array();
		foreach ( $matches as $match ) {
			$ids[] = $match[1];
		}
		if ( count( $ids ) !== count( array_unique( $ids ) ) ) {
			$errors[] = 'duplicate MAG decision identifier';
		}
		$expected = array( 'MAG-01', 'MAG-02', 'MAG-03', 'MAG-04', 'MAG-05', 'MAG-06', 'MAG-07', 'MAG-08' );
		foreach ( $expected as $id ) {
			if ( ! in_array( $id, $ids, true ) ) {
				$errors[] = 'missing decision ' . $id;
			}
		}
		foreach ( $ids as $id ) {
			if ( ! in_array( $id, $expected, true ) ) {
				$errors[] = 'unexpected MAG decision identifier: ' . $id;
			}
		}
		if ( preg_match_all( '/^##\s+MAG-\d{2}\s+[\x{2014}\x{2013}-]\s+/mu', $content, $sectionMatches, PREG_OFFSET_CAPTURE ) ) {
			$starts = array();
			foreach ( $sectionMatches[0] as $match ) {
				$starts[] = $match[1];
			}
			$starts[] = strlen( $content );
			for ( $i = 0; $i < count( $starts ) - 1; $i++ ) {
				$section = substr( $content, $starts[ $i ], $starts[ $i + 1 ] - $starts[ $i ] );
				preg_match( '/^##\s+(MAG-\d{2})/u', $section, $idMatch );
				$id = $idMatch[1] ?? 'MAG-??';
				foreach ( array( 'Status', 'Approval state', 'Context', 'Decision', 'Rationale', 'Consequences', 'Revision history' ) as $label ) {
					if ( false === strpos( $section, '**' . $label . ':**' ) ) {
						$errors[] = $id . ' missing required field: ' . $label;
					}
				}
				if ( preg_match( '/\*\*Status:\*\*\s+([A-Z]+)/u', $section, $statusMatch ) ) {
					if ( ! in_array( $statusMatch[1], array( 'PROPOSED', 'ACCEPTED', 'SUPERSEDED', 'REJECTED' ), true ) ) {
						$errors[] = $id . ' has invalid status: ' . $statusMatch[1];
					} elseif ( 'ACCEPTED' !== $statusMatch[1] ) {
						$errors[] = $id . ' is not accepted: ' . $statusMatch[1];
					}
				}
			}
		}
		if ( preg_match( '/^##\s+(OCG-\d+|D\d+|T-\d+|O-\d+)\s+/mu', $content, $other ) ) {
			$errors[] = 'foreign decision namespace in MAG register: ' . $other[1];
		}
		return $errors;
	}

	private function checkSchemas(): void {
		$schemas = array( 'task', 'ownership', 'execution-state', 'event' );
		foreach ( $schemas as $name ) {
			$path = $this->root . '/.agents/schemas/' . $name . '.schema.json';
			$raw = (string) @file_get_contents( $path );
			try {
				$schema = json_decode( $raw, true, 512, JSON_THROW_ON_ERROR );
			} catch ( JsonException $e ) {
				$this->error( 'invalid JSON schema ' . $name . ': ' . $e->getMessage() );
				continue;
			}
			if ( ! is_array( $schema ) || empty( $schema['$schema'] ) || empty( $schema['required'] ) || ! is_array( $schema['required'] ) ) {
				$this->error( 'schema ' . $name . ' lacks $schema or required fields' );
				continue;
			}
			$this->pass( 'schema parses: ' . $name );
		}
	}

	private function checkTemplates(): void {
		$task = $this->loadYaml( $this->root . '/.agents/templates/task-contract.yaml' );
		$errors = $this->validateTask( $task, array() );
		if ( array() === $errors ) {
			$this->pass( 'task template validates against task rules' );
		} else {
			foreach ( $errors as $error ) {
				$this->error( 'task template: ' . $error );
			}
		}
		$scope = $this->loadYaml( $this->root . '/.agents/templates/scope-change.yaml' );
		$errors = $this->validateScopeChange( $scope );
		if ( array() === $errors ) {
			$this->pass( 'scope-change template validates' );
		} else {
			foreach ( $errors as $error ) {
				$this->error( 'scope-change template: ' . $error );
			}
		}
	}

	private function checkOwnershipRegistry(): void {
		$registry = $this->loadYaml( $this->root . '/.agents/state/ownership-registry.yaml' );
		$errors = $this->validateOwnership( $registry, array() );
		if ( array() === $errors ) {
			$this->pass( 'ownership registry validates' );
		} else {
			foreach ( $errors as $error ) {
				$this->error( 'ownership registry: ' . $error );
			}
		}
	}

	private function checkExecutionState(): void {
		$state = $this->loadYaml( $this->root . '/.agents/state/project-state.yaml' );
		$errors = $this->validateExecutionState( $state );
		if ( array() === $errors ) {
			$this->pass( 'execution state validates' );
		} else {
			foreach ( $errors as $error ) {
				$this->error( 'execution state: ' . $error );
			}
		}
	}

	private function checkEventLog(): void {
		$log = $this->loadYaml( $this->root . '/.agents/state/event-log.yaml' );
		$errors = $this->validateEventLog( $log );
		if ( array() === $errors ) {
			$this->pass( 'event log validates' );
		} else {
			foreach ( $errors as $error ) {
				$this->error( 'event log: ' . $error );
			}
		}
	}

	private function checkProtectedWork(): void {
		$protected = $this->loadYaml( $this->root . '/.agents/state/protected-work.yaml' );
		$errors = $this->validateProtectedWork( $protected );
		if ( array() === $errors ) {
			$this->pass( 'protected-work register validates (empty, WP_002 explicitly not active)' );
		} else {
			foreach ( $errors as $error ) {
				$this->error( 'protected work: ' . $error );
			}
		}
	}

	private function checkIgnoreRules(): void {
		$path = $this->root . '/.agents/README.md';
		$command = 'git -C ' . escapeshellarg( $this->root ) . ' check-ignore -q ' . escapeshellarg( $path ) . ' 2>/dev/null';
		$output = array();
		$code = 0;
		exec( $command, $output, $code );
		if ( 0 === $code ) {
			$this->error( '.agents/README.md is ignored by Git' );
		} elseif ( 1 === $code ) {
			$this->pass( '.agents/ is not ignored by Git' );
		} else {
			$this->error( 'could not verify whether .agents/ is ignored (git check-ignore exit ' . $code . ')' );
		}
	}

	private function checkBootstrapScope(): void {
		$output = array();
		$code = 0;
		exec( 'git -C ' . escapeshellarg( $this->root ) . ' status --porcelain=v1', $output, $code );
		if ( 0 !== $code ) {
			$this->error( 'could not inspect Git status for bootstrap scope' );
			return;
		}
		$bad = array();
		foreach ( $output as $line ) {
			$path = trim( substr( $line, 3 ) );
			if ( false !== strpos( $path, ' -> ' ) ) {
				$path = trim( explode( ' -> ', $path, 2 )[1] );
			}
			if ( 'AGENTS.md' === $path || 0 === strpos( $path, '.agents/' ) ) {
				continue;
			}
			$bad[] = $path;
		}
		if ( array() === $bad ) {
			$this->pass( 'bootstrap scope: changes limited to AGENTS.md and .agents/' );
		} else {
			$this->error( 'bootstrap scope violation: ' . implode( ', ', $bad ) );
		}
	}

	private function validateTask(array $task, ?array $knownTaskIds = null): array {
		$errors = array();
		$required = array( 'id', 'title', 'status', 'owner', 'workspace', 'permissions', 'scope', 'dependencies', 'contracts', 'acceptance_criteria', 'tests', 'deliverables', 'approvals' );
		foreach ( $required as $key ) {
			if ( ! array_key_exists( $key, $task ) ) {
				$errors[] = 'missing task field: ' . $key;
			}
		}
		if ( array() !== $errors ) {
			return $errors;
		}
		if ( ! is_string( $task['id'] ) || ! preg_match( '/^[A-Z][A-Z0-9-]{2,63}$/', $task['id'] ) ) {
			$errors[] = 'invalid task id';
		}
		if ( ! is_string( $task['title'] ) || '' === trim( $task['title'] ) ) {
			$errors[] = 'task title is empty';
		}
		if ( ! in_array( $task['status'], self::TASK_STATES, true ) ) {
			$errors[] = 'invalid task status: ' . (string) $task['status'];
		}
		if ( ! is_array( $task['owner'] ) || empty( $task['owner']['agent'] ) || empty( $task['owner']['role'] ) ) {
			$errors[] = 'task owner requires agent and role';
		} elseif ( ! in_array( $task['owner']['agent'], self::ROLE_SLUGS, true ) ) {
			$errors[] = 'unknown owner agent: ' . (string) $task['owner']['agent'];
		}
		if ( ! is_array( $task['workspace'] ) ) {
			$errors[] = 'task workspace must be a mapping';
		} else {
			foreach ( array( 'branch', 'path', 'base_commit' ) as $key ) {
				if ( empty( $task['workspace'][ $key ] ) ) {
					$errors[] = 'workspace missing ' . $key;
				}
			}
			if ( ! empty( $task['workspace']['base_commit'] ) && ! preg_match( '/^[0-9a-f]{40}$/', (string) $task['workspace']['base_commit'] ) ) {
				$errors[] = 'workspace base_commit must be 40 lowercase hex characters';
			}
		}
		if ( ! is_array( $task['permissions'] ) || array() === $task['permissions'] ) {
			$errors[] = 'permissions must be a non-empty array';
		} else {
			foreach ( $task['permissions'] as $permission ) {
				if ( ! in_array( $permission, self::PERMISSIONS, true ) ) {
					$errors[] = 'invalid permission: ' . (string) $permission;
				}
			}
			if ( count( $task['permissions'] ) !== count( array_unique( $task['permissions'] ) ) ) {
				$errors[] = 'duplicate permission';
			}
		}
		if ( ! is_array( $task['scope'] ) || empty( $task['scope']['allowed'] ) || ! is_array( $task['scope']['allowed'] ) ) {
			$errors[] = 'scope.allowed must be a non-empty array';
		} else {
			$forbidden = is_array( $task['scope']['forbidden'] ?? null ) ? $task['scope']['forbidden'] : array();
			foreach ( array_merge( $task['scope']['allowed'], $forbidden ) as $path ) {
				if ( ! $this->safePath( (string) $path ) ) {
					$errors[] = 'unsafe scope path: ' . (string) $path;
				}
			}
			foreach ( $task['scope']['allowed'] as $allowed ) {
				foreach ( $forbidden as $denied ) {
					if ( $this->pathsOverlap( (string) $allowed, (string) $denied ) ) {
						$errors[] = 'allowed and forbidden paths overlap: ' . $allowed . ' / ' . $denied;
					}
				}
			}
		}
		if ( ! is_array( $task['dependencies'] ) ) {
			$errors[] = 'dependencies must be an array';
		} else {
			foreach ( $task['dependencies'] as $dependency ) {
				if ( ! is_string( $dependency ) || ! preg_match( '/^[A-Z][A-Z0-9-]{2,63}$/', $dependency ) ) {
					$errors[] = 'invalid dependency id: ' . (string) $dependency;
				}
				if ( $dependency === $task['id'] ) {
					$errors[] = 'task cannot depend on itself';
				}
				if ( is_array( $knownTaskIds ) && array() !== $knownTaskIds && ! in_array( $dependency, $knownTaskIds, true ) ) {
					$errors[] = 'unknown dependency: ' . $dependency;
				}
			}
		}
		if ( ! is_array( $task['contracts'] ) ) {
			$errors[] = 'contracts must be an array';
		}
		if ( ! is_array( $task['acceptance_criteria'] ) || array() === $task['acceptance_criteria'] ) {
			$errors[] = 'acceptance_criteria must be a non-empty array';
		}
		if ( ! is_array( $task['tests'] ) || empty( $task['tests']['required'] ) || ! is_array( $task['tests']['required'] ) ) {
			$errors[] = 'tests.required must be a non-empty array';
		}
		if ( ! is_array( $task['deliverables'] ) || array() === $task['deliverables'] ) {
			$errors[] = 'deliverables must be a non-empty array';
		}
		if ( ! is_array( $task['approvals'] ) ) {
			$errors[] = 'approvals must be a mapping';
		} else {
			foreach ( array( 'architecture', 'security', 'integration' ) as $key ) {
				if ( ! isset( $task['approvals'][ $key ] ) || ! in_array( $task['approvals'][ $key ], array( 'required', 'not-required', 'approved' ), true ) ) {
					$errors[] = 'invalid approval value for ' . $key;
				}
			}
		}
		return $errors;
	}

	private function validateScopeChange(array $scope): array {
		$errors = array();
		$required = array( 'task_id', 'requested_by', 'current_scope', 'requested_additional_files', 'reason', 'impact', 'ownership_changes', 'tests_required', 'risk', 'status' );
		foreach ( $required as $key ) {
			if ( ! array_key_exists( $key, $scope ) ) {
				$errors[] = 'missing scope-change field: ' . $key;
			}
		}
		if ( array() !== $errors ) {
			return $errors;
		}
		if ( ! is_string( $scope['task_id'] ) || ! preg_match( '/^[A-Z][A-Z0-9-]{2,63}$/', $scope['task_id'] ) ) {
			$errors[] = 'invalid scope-change task_id';
		}
		if ( ! is_string( $scope['requested_by'] ) || ! in_array( $scope['requested_by'], self::ROLE_SLUGS, true ) ) {
			$errors[] = 'scope-change requested_by is not a known role';
		}
		foreach ( array( 'current_scope', 'requested_additional_files', 'ownership_changes', 'tests_required' ) as $key ) {
			if ( ! is_array( $scope[ $key ] ) ) {
				$errors[] = $key . ' must be an array';
			}
		}
		if ( ! is_array( $scope['requested_additional_files'] ) || array() === $scope['requested_additional_files'] ) {
			$errors[] = 'requested_additional_files must be non-empty';
		}
		if ( ! is_string( $scope['reason'] ) || '' === trim( $scope['reason'] ) ) {
			$errors[] = 'reason is required';
		}
		if ( ! in_array( $scope['risk'], array( 'LOW', 'MEDIUM', 'HIGH', 'CRITICAL' ), true ) ) {
			$errors[] = 'invalid risk level';
		}
		if ( ! in_array( $scope['status'], array( 'PROPOSED', 'APPROVED', 'REJECTED' ), true ) ) {
			$errors[] = 'invalid scope-change status';
		}
		return $errors;
	}

	private function validateOwnership(array $registry, ?array $knownTaskIds = null): array {
		$errors = array();
		if ( 1 !== (int) ( $registry['version'] ?? 0 ) ) {
			$errors[] = 'ownership version must be 1';
		}
		if ( empty( $registry['updated_at'] ) ) {
			$errors[] = 'ownership updated_at is required';
		}
		$entries = $registry['entries'] ?? null;
		if ( ! is_array( $entries ) ) {
			return array_merge( $errors, array( 'entries must be an array' ) );
		}
		$active = array();
		foreach ( $entries as $index => $entry ) {
			$label = 'entry ' . ( $index + 1 );
			if ( ! is_array( $entry ) ) {
				$errors[] = $label . ' must be a mapping';
				continue;
			}
			$required = array( 'path', 'task_id', 'owner_agent', 'mode', 'workspace', 'base_commit', 'status', 'lease_started_at', 'lease_expires_at', 'last_heartbeat_at', 'dependencies', 'integration_status' );
			foreach ( $required as $key ) {
				if ( ! array_key_exists( $key, $entry ) ) {
					$errors[] = $label . ' missing ' . $key;
				}
			}
			if ( ! empty( $entry['path'] ) && ! $this->safePath( (string) $entry['path'] ) ) {
				$errors[] = $label . ' has unsafe path: ' . $entry['path'];
			}
			if ( ! empty( $entry['path'] ) && $this->isForbiddenWritePath( (string) $entry['path'] ) ) {
				$errors[] = $label . ' targets forbidden write path: ' . $entry['path'];
			}
			if ( ! empty( $entry['task_id'] ) && ! preg_match( '/^[A-Z][A-Z0-9-]{2,63}$/', (string) $entry['task_id'] ) ) {
				$errors[] = $label . ' has invalid task_id';
			}
			if ( ! empty( $entry['owner_agent'] ) && ! in_array( $entry['owner_agent'], self::ROLE_SLUGS, true ) ) {
				$errors[] = $label . ' has unknown owner_agent: ' . (string) $entry['owner_agent'];
			}
			if ( is_array( $knownTaskIds ) && array() !== $knownTaskIds && ! in_array( $entry['task_id'] ?? '', $knownTaskIds, true ) ) {
				$errors[] = $label . ' references unknown task: ' . (string) ( $entry['task_id'] ?? '' );
			}
			if ( ! in_array( $entry['mode'] ?? '', array( 'exclusive', 'scoped' ), true ) ) {
				$errors[] = $label . ' has invalid mode';
			}
			if ( ! in_array( $entry['status'] ?? '', array( 'active', 'released', 'expired', 'revoked' ), true ) ) {
				$errors[] = $label . ' has invalid status';
			}
			if ( ! in_array( $entry['integration_status'] ?? '', array( 'pending', 'integrated', 'rejected', 'not_applicable' ), true ) ) {
				$errors[] = $label . ' has invalid integration_status';
			}
			if ( ! empty( $entry['base_commit'] ) && ! preg_match( '/^[0-9a-f]{40}$/', (string) $entry['base_commit'] ) ) {
				$errors[] = $label . ' base_commit must be 40 lowercase hex characters';
			}
			if ( ! empty( $entry['lease_expires_at'] ) && false === strtotime( (string) $entry['lease_expires_at'] ) ) {
				$errors[] = $label . ' lease_expires_at is not a valid time';
			}
			if ( ! empty( $entry['lease_started_at'] ) && false === strtotime( (string) $entry['lease_started_at'] ) ) {
				$errors[] = $label . ' lease_started_at is not a valid time';
			}
			if ( ! empty( $entry['last_heartbeat_at'] ) && false === strtotime( (string) $entry['last_heartbeat_at'] ) ) {
				$errors[] = $label . ' last_heartbeat_at is not a valid time';
			}
			if ( ! is_array( $entry['dependencies'] ?? null ) ) {
				$errors[] = $label . ' dependencies must be an array';
			}
			if ( 'active' === ( $entry['status'] ?? '' ) ) {
				$active[] = $entry;
			}
		}
		for ( $i = 0; $i < count( $active ); $i++ ) {
			for ( $j = $i + 1; $j < count( $active ); $j++ ) {
				if ( $this->ownershipOverlap( $active[ $i ], $active[ $j ] ) ) {
					$errors[] = 'overlapping active ownership: ' . $active[ $i ]['path'] . ' / ' . $active[ $j ]['path'];
				}
			}
		}
		return $errors;
	}

	private function ownershipOverlap(array $a, array $b): bool {
		$pathA = rtrim( (string) $a['path'], '/' );
		$pathB = rtrim( (string) $b['path'], '/' );
		$modeA = (string) $a['mode'];
		$modeB = (string) $b['mode'];
		if ( 'exclusive' === $modeA && 'exclusive' === $modeB ) {
			return $pathA === $pathB;
		}
		if ( $pathA === $pathB ) {
			return true;
		}
		if ( 'scoped' === $modeA && ( $pathB === $pathA || 0 === strpos( $pathB, $pathA . '/' ) ) ) {
			return true;
		}
		if ( 'scoped' === $modeB && ( $pathA === $pathB || 0 === strpos( $pathA, $pathB . '/' ) ) ) {
			return true;
		}
		return false;
	}

	private function validateExecutionState(array $state): array {
		$errors = array();
		$required = array( 'version', 'updated_at', 'mode', 'milestone', 'active_tasks', 'blocked_tasks', 'active_contracts', 'awaiting_approval', 'references', 'last_validation' );
		foreach ( $required as $key ) {
			if ( ! array_key_exists( $key, $state ) ) {
				$errors[] = 'missing state field: ' . $key;
			}
		}
		if ( array() !== $errors ) {
			return $errors;
		}
		if ( 1 !== (int) $state['version'] ) {
			$errors[] = 'state version must be 1';
		}
		if ( ! in_array( $state['mode'], self::MODES, true ) ) {
			$errors[] = 'invalid orchestrator mode';
		}
		if ( ! is_array( $state['active_tasks'] ) || ! is_array( $state['blocked_tasks'] ) ) {
			$errors[] = 'active_tasks and blocked_tasks must be arrays';
		}
		if ( ! is_array( $state['active_contracts'] ) ) {
			$errors[] = 'active_contracts must be an array';
		}
		if ( ! is_array( $state['awaiting_approval'] ) ) {
			$errors[] = 'awaiting_approval must be an array';
		}
		if ( 'AWAITING_APPROVAL' === $state['mode'] && array() === $state['awaiting_approval'] ) {
			$errors[] = 'AWAITING_APPROVAL requires at least one approval item';
		}
		foreach ( array( 'active_tasks', 'blocked_tasks' ) as $key ) {
			foreach ( $state[ $key ] as $taskId ) {
				if ( ! is_string( $taskId ) || ! preg_match( '/^[A-Z][A-Z0-9-]{2,63}$/', $taskId ) ) {
					$errors[] = 'invalid task id in ' . $key . ': ' . (string) $taskId;
				}
			}
		}
		if ( ! is_array( $state['references'] ) ) {
			$errors[] = 'references must be a mapping';
		} else {
			foreach ( array( 'ownership_registry', 'event_log', 'protected_work', 'decision_register' ) as $key ) {
				if ( empty( $state['references'][ $key ] ) ) {
					$errors[] = 'references missing ' . $key;
					continue;
				}
				if ( ! file_exists( $this->root . '/' . $state['references'][ $key ] ) ) {
					$errors[] = 'state reference does not exist: ' . $state['references'][ $key ];
				}
			}
		}
		if ( ! is_array( $state['last_validation'] ) ) {
			$errors[] = 'last_validation must be a mapping';
		} else {
			foreach ( array( 'status', 'timestamp', 'validator' ) as $key ) {
				if ( ! array_key_exists( $key, $state['last_validation'] ) ) {
					$errors[] = 'last_validation missing ' . $key;
				}
			}
			if ( ! in_array( $state['last_validation']['status'] ?? '', array( 'PENDING', 'PASS', 'FAIL' ), true ) ) {
				$errors[] = 'invalid last_validation status';
			}
			if ( ! empty( $state['last_validation']['validator'] ) && ! file_exists( $this->root . '/' . $state['last_validation']['validator'] ) ) {
				$errors[] = 'validator path does not exist';
			}
		}
		return $errors;
	}

	private function validateEventLog(array $log): array {
		$errors = array();
		if ( 1 !== (int) ( $log['version'] ?? 0 ) ) {
			$errors[] = 'event log version must be 1';
		}
		if ( ! is_array( $log['events'] ?? null ) ) {
			return array_merge( $errors, array( 'events must be an array' ) );
		}
		$ids = array();
		foreach ( $log['events'] as $index => $event ) {
			$label = 'event ' . ( $index + 1 );
			if ( ! is_array( $event ) ) {
				$errors[] = $label . ' must be a mapping';
				continue;
			}
			foreach ( array( 'id', 'timestamp', 'actor', 'task_id', 'type', 'description' ) as $key ) {
				if ( empty( $event[ $key ] ) ) {
					$errors[] = $label . ' missing ' . $key;
				}
			}
			if ( ! empty( $event['id'] ) ) {
				if ( ! preg_match( '/^EV-[0-9]{4}$/', (string) $event['id'] ) ) {
					$errors[] = $label . ' invalid id';
				}
				if ( in_array( $event['id'], $ids, true ) ) {
					$errors[] = $label . ' duplicate id';
				}
				$ids[] = $event['id'];
			}
			if ( ! empty( $event['type'] ) && ! preg_match( '/^[A-Z][A-Z0-9_]+$/', (string) $event['type'] ) ) {
				$errors[] = $label . ' invalid type';
			}
		}
		return $errors;
	}

	private function validateProtectedWork(array $data): array {
		$errors = array();
		if ( 1 !== (int) ( $data['version'] ?? 0 ) ) {
			$errors[] = 'protected-work version must be 1';
		}
		if ( empty( $data['updated_at'] ) ) {
			$errors[] = 'protected-work updated_at is required';
		}
		if ( ! is_array( $data['entries'] ?? null ) ) {
			return array_merge( $errors, array( 'entries must be an array' ) );
		}
		foreach ( $data['entries'] as $index => $entry ) {
			$label = 'protected entry ' . ( $index + 1 );
			foreach ( array( 'path', 'task_id', 'owner', 'reason', 'approved_by', 'approved_at', 'status', 'owner_approved' ) as $key ) {
				if ( ! array_key_exists( $key, $entry ) ) {
					$errors[] = $label . ' missing ' . $key;
				}
			}
			if ( true !== ( $entry['owner_approved'] ?? false ) ) {
				$errors[] = $label . ' requires owner_approved: true';
			}
			if ( empty( $entry['approved_by'] ) ) {
				$errors[] = $label . ' requires approved_by';
			}
			if ( ! empty( $entry['path'] ) && ! $this->safePath( (string) $entry['path'] ) ) {
				$errors[] = $label . ' has unsafe path';
			}
			if ( isset( $entry['status'] ) && ! in_array( $entry['status'], array( 'active', 'released' ), true ) ) {
				$errors[] = $label . ' invalid status';
			}
		}
		$notes = is_array( $data['notes'] ?? null ) ? implode( ' ', $data['notes'] ) : '';
		if ( false === stripos( $notes, 'WP_002' ) || false === stripos( $notes, 'not present' ) ) {
			$errors[] = 'protected-work notes must state that WP_002 is not present';
		}
		return $errors;
	}

	private function isTransitionAllowed(string $from, string $to): bool {
		return isset( self::TRANSITIONS[ $from ] ) && in_array( $to, self::TRANSITIONS[ $from ], true );
	}

	private function safePath(string $path): bool {
		$path = str_replace( '\\', '/', trim( $path ) );
		if ( '' === $path || '/' === $path[0] || preg_match( '#^[A-Za-z]:#', $path ) ) {
			return false;
		}
		if ( false !== strpos( $path, '://' ) || false !== strpos( $path, '..' ) ) {
			return false;
		}
		return true;
	}

	private function isForbiddenWritePath(string $path): bool {
		$path = ltrim( str_replace( '\\', '/', $path ), '/' );
		foreach ( self::FORBIDDEN_WRITE_PREFIXES as $prefix ) {
			if ( $path === rtrim( $prefix, '/' ) || 0 === strpos( $path, $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	private function pathsOverlap(string $a, string $b): bool {
		$a = rtrim( str_replace( '\\', '/', $a ), '/' );
		$b = rtrim( str_replace( '\\', '/', $b ), '/' );
		if ( $a === $b ) {
			return true;
		}
		return 0 === strpos( $a, $b . '/' ) || 0 === strpos( $b, $a . '/' );
	}

	private function loadYaml(string $path): array {
		try {
			return GovernanceYaml::parseFile( $path );
		} catch ( Throwable $e ) {
			$this->error( 'YAML parse failure: ' . $this->relative( $path ) . ' — ' . $e->getMessage() );
			return array();
		}
	}

	private function relative(string $path): string {
		$prefix = $this->root . DIRECTORY_SEPARATOR;
		return 0 === strpos( $path, $prefix ) ? substr( $path, strlen( $prefix ) ) : $path;
	}

	private function pass(string $message): void {
		$this->passes[] = $message;
		echo 'PASS: ' . $message . PHP_EOL;
	}

	private function error(string $message): void {
		$this->errors[] = $message;
		echo 'FAIL: ' . $message . PHP_EOL;
	}

	private function summary(string $label): bool {
		if ( array() === $this->errors ) {
			echo 'RESULT: PASS — ' . $label . ' (' . count( $this->passes ) . ' checks)' . PHP_EOL;
			return true;
		}
		echo 'RESULT: FAIL — ' . $label . ' (' . count( $this->errors ) . ' errors, ' . count( $this->passes ) . ' passes)' . PHP_EOL;
		return false;
	}
}

$root = dirname( __DIR__, 2 );
$args = array_slice( $argv, 1 );
$selfTest = in_array( '--self-test', $args, true );
$all = in_array( '--all', $args, true );
$bootstrap = in_array( '--bootstrap', $args, true );
$help = in_array( '--help', $args, true ) || in_array( '-h', $args, true );

if ( $help ) {
	echo "Usage: php .agents/scripts/validate-governance.php [--all] [--self-test] [--bootstrap]\n";
	echo "  --all        Run main governance validation and fixture self-tests.\n";
	echo "  --self-test  Run only the positive/negative fixture self-tests.\n";
	echo "  --bootstrap  Also require changes to stay within AGENTS.md and .agents/.\n";
	exit(0);
}

$validator = new GovernanceValidator( $root, $bootstrap );

if ( $selfTest && ! $all ) {
	$ok = $validator->runSelfTest();
	exit( $ok ? 0 : 1 );
}

$ok = $validator->runMain();
if ( $all ) {
	$self = new GovernanceValidator( $root, $bootstrap );
	$ok = $self->runSelfTest() && $ok;
}
exit( $ok ? 0 : 1 );
