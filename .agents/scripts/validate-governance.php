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

	private const EVENT_KEYS = array(
		'id', 'timestamp', 'actor', 'task_id', 'type', 'description', 'from_state', 'to_state', 'initial_state',
	);

	private const TRANSITION_EVENT_TYPES = array( 'TASK_TRANSITION' );
	private const CREATION_EVENT_TYPES = array( 'TASK_CREATED' );
	private const APPROVAL_EVENT_TYPES = array( 'APPROVAL_RECORDED' );
	private const APPROVAL_GATES = array( 'architecture', 'security', 'integration' );
	private const APPROVERS = array( 'repository-owner', 'orchestrator', 'security-engineer' );

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
		'REJECTED' => array(),
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
		$this->checkTaskContractIntegrity();
		$this->checkTaskTransitionConsistency();
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

		$eventLegacy = $this->loadYaml($fixtures . '/event-log-legacy-valid.yaml');
		$cases[] = array( 'event-log-legacy-valid', $this->validateEventLog($eventLegacy), true );

		$eventTransitionValid = $this->loadYaml($fixtures . '/event-log-transition-valid.yaml');
		$cases[] = array( 'event-log-transition-valid', $this->validateEventLog($eventTransitionValid), true );

		$eventMissingFrom = $this->loadYaml($fixtures . '/event-log-transition-missing-from.yaml');
		$cases[] = array( 'event-log-transition-missing-from', $this->validateEventLog($eventMissingFrom), false );

		$eventMissingTo = $this->loadYaml($fixtures . '/event-log-transition-missing-to.yaml');
		$cases[] = array( 'event-log-transition-missing-to', $this->validateEventLog($eventMissingTo), false );

		$eventInvalidState = $this->loadYaml($fixtures . '/event-log-transition-invalid-state.yaml');
		$cases[] = array( 'event-log-transition-invalid-state', $this->validateEventLog($eventInvalidState), false );

		$eventInvalidTransition = $this->loadYaml($fixtures . '/event-log-transition-invalid-transition.yaml');
		$cases[] = array( 'event-log-transition-invalid-transition', $this->validateEventLog($eventInvalidTransition), false );

		$eventFieldsOnNonTransition = $this->loadYaml($fixtures . '/event-log-transition-fields-on-non-transition.yaml');
		$cases[] = array( 'event-log-transition-fields-on-non-transition', $this->validateEventLog($eventFieldsOnNonTransition), false );

		$eventUnknownProperty = $this->loadYaml($fixtures . '/event-log-unknown-property.yaml');
		$cases[] = array( 'event-log-unknown-property', $this->validateEventLog($eventUnknownProperty), false );

		$eventInvalidType = $this->loadYaml($fixtures . '/event-log-transition-invalid-type.yaml');
		$cases[] = array( 'event-log-transition-invalid-type', $this->validateEventLog($eventInvalidType), false );

		$eventMissingActor = $this->loadYaml($fixtures . '/event-log-transition-missing-actor.yaml');
		$cases[] = array( 'event-log-transition-missing-actor', $this->validateEventLog($eventMissingActor), false );

		$eventRejectedSuperseded = $this->loadYaml($fixtures . '/event-log-transition-rejected-superseded.yaml');
		$cases[] = array( 'event-log-transition-rejected-superseded', $this->validateEventLog($eventRejectedSuperseded), false );

		$eventInvalidIsoTimestamp = $this->loadYaml($fixtures . '/event-log-transition-invalid-iso-timestamp.yaml');
		$cases[] = array( 'event-log-transition-invalid-iso-timestamp', $this->validateEventLog($eventInvalidIsoTimestamp), false );

		$eventSchema = json_decode( (string) @file_get_contents( $this->root . '/.agents/schemas/event.schema.json' ), true );
		$cases[] = array( 'event-schema-transition-contract', $this->validateEventSchemaContract( is_array( $eventSchema ) ? $eventSchema : array() ), true );

		$policy = (string) @file_get_contents( $this->root . '/.agents/policies/task-lifecycle.md' );
		$cases[] = array( 'event-lifecycle-policy-contract', $this->validateLifecyclePolicyContract( $policy ), true );

		$cases[] = array( 'transition-table-complete', $this->validateTransitionTableCompleteness(), true );

		$schema_base = is_array( $eventSchema ) ? $eventSchema : array();
		$schema_missing_state = $schema_base;
		$schema_missing_state['$defs']['lifecycleState']['enum'] = array_values( array_diff( $schema_missing_state['$defs']['lifecycleState']['enum'], array( 'BLOCKED' ) ) );
		$cases[] = array( 'event-schema-missing-state', $this->validateEventSchemaContract( $schema_missing_state ), false );

		$schema_extra_state = $schema_base;
		$schema_extra_state['$defs']['lifecycleState']['enum'][] = 'DONE';
		$cases[] = array( 'event-schema-unexpected-state', $this->validateEventSchemaContract( $schema_extra_state ), false );

		$schema_missing_field = $schema_base;
		unset( $schema_missing_field['properties']['events']['items']['properties']['from_state'] );
		$cases[] = array( 'event-schema-missing-transition-field', $this->validateEventSchemaContract( $schema_missing_field ), false );

		$schema_missing_required = $schema_base;
		$schema_missing_required['properties']['events']['items']['required'] = array_values(
			array_diff( $schema_missing_required['properties']['events']['items']['required'], array( 'actor' ) )
		);
		$cases[] = array( 'event-schema-missing-required-field', $this->validateEventSchemaContract( $schema_missing_required ), false );

		$schema_missing_rule = $schema_base;
		$schema_missing_rule['properties']['events']['items']['allOf'] = array();
		$cases[] = array( 'event-schema-missing-transition-rule', $this->validateEventSchemaContract( $schema_missing_rule ), false );

		$schema_additional = $schema_base;
		$schema_additional['properties']['events']['items']['additionalProperties'] = true;
		$cases[] = array( 'event-schema-additional-properties', $this->validateEventSchemaContract( $schema_additional ), false );

		$policy_missing_transition = preg_replace( '/^\| `INTEGRATED` \|.*\n/m', '', $policy, 1 );
		$cases[] = array( 'lifecycle-policy-missing-transition', $this->validateLifecyclePolicyContract( (string) $policy_missing_transition ), false );

		$policy_extra_transition = str_replace(
			'| `INTEGRATED` | `VERIFIED`, `REJECTED` |',
			'| `INTEGRATED` | `VERIFIED`, `REJECTED`, `SUPERSEDED` |',
			$policy
		);
		$cases[] = array( 'lifecycle-policy-unexpected-transition', $this->validateLifecyclePolicyContract( $policy_extra_transition ), false );

		$policy_rejected = str_replace(
			"| `BLOCKED` | `ASSIGNED`, `IN_PROGRESS`, `CANCELLED`, `SUPERSEDED` |",
			"| `BLOCKED` | `ASSIGNED`, `IN_PROGRESS`, `CANCELLED`, `SUPERSEDED` |\n| `REJECTED` | `SUPERSEDED` |",
			$policy
		);
		$cases[] = array( 'lifecycle-policy-rejected-superseded', $this->validateLifecyclePolicyContract( $policy_rejected ), false );

		$policy_missing_legacy = preg_replace( '/^### Legacy events\s*$/m', '### Legacy notes', $policy, 1 );
		$cases[] = array( 'lifecycle-policy-missing-legacy-section', $this->validateLifecyclePolicyContract( (string) $policy_missing_legacy ), false );

		$approvalsRequired = array( 'architecture' => 'not-required', 'security' => 'required', 'integration' => 'required' );
		$approvalsApproved = array( 'architecture' => 'not-required', 'security' => 'approved', 'integration' => 'approved' );
		$base = '7b5d36d7dd50752d47de0aaa50fc2262ea26b8dd';
		$create = static function ( string $id, string $task, string $timestamp = '2026-01-01T00:00:00+00:00', string $initial = 'PROPOSED' ): array {
			return array( 'id' => $id, 'timestamp' => $timestamp, 'actor' => 'orchestrator', 'task_id' => $task, 'type' => 'TASK_CREATED', 'description' => 'Task created', 'initial_state' => $initial );
		};
		$transition = static function ( string $id, string $task, string $from, string $to, string $timestamp ): array {
			return array( 'id' => $id, 'timestamp' => $timestamp, 'actor' => 'orchestrator', 'task_id' => $task, 'type' => 'TASK_TRANSITION', 'description' => 'State changed', 'from_state' => $from, 'to_state' => $to );
		};

		$taskProposed = array( 'id' => 'T-100', 'status' => 'PROPOSED', 'approvals' => $approvalsRequired );
		$cases[] = array( 'task-created-only', $this->validateTaskTransitionConsistency( array( 'T-100' => $taskProposed ), array( $create( 'EV-9101', 'T-100' ) ) )[0], true );

		$taskSpecified = array( 'id' => 'T-101', 'status' => 'SPECIFIED', 'approvals' => $approvalsRequired );
		$eventsSpecified = array( $create( 'EV-9110', 'T-101' ), $transition( 'EV-9111', 'T-101', 'PROPOSED', 'SPECIFIED', '2026-01-02T00:00:00+00:00' ) );
		$cases[] = array( 'task-transition-valid', $this->validateTaskTransitionConsistency( array( 'T-101' => $taskSpecified ), $eventsSpecified )[0], true );

		$cases[] = array( 'task-missing-creation', $this->validateTaskTransitionConsistency( array( 'T-102' => $taskProposed ), array() )[0], false );
		$cases[] = array( 'task-created-late-state', $this->validateTaskTransitionConsistency( array( 'T-103' => array( 'id' => 'T-103', 'status' => 'INTEGRATION_READY', 'approvals' => $approvalsApproved ) ), array( $create( 'EV-9112', 'T-103', '2026-01-01T00:00:00+00:00', 'INTEGRATION_READY' ) ) )[0], false );
		$cases[] = array( 'task-duplicate-creation', $this->validateTaskTransitionConsistency( array( 'T-104' => $taskProposed ), array( $create( 'EV-9113', 'T-104' ), $create( 'EV-9114', 'T-104', '2026-01-02T00:00:00+00:00' ) ) )[0], false );
		$cases[] = array( 'task-invalid-from-state', $this->validateTaskTransitionConsistency( array( 'T-105' => array( 'id' => 'T-105', 'status' => 'SPECIFIED', 'approvals' => $approvalsRequired ) ), array( $create( 'EV-9115', 'T-105' ), $transition( 'EV-9116', 'T-105', 'DONE', 'SPECIFIED', '2026-01-02T00:00:00+00:00' ) ) )[0], false );
		$cases[] = array( 'task-invalid-to-state', $this->validateTaskTransitionConsistency( array( 'T-106' => array( 'id' => 'T-106', 'status' => 'SPECIFIED', 'approvals' => $approvalsRequired ) ), array( $create( 'EV-9117', 'T-106' ), $transition( 'EV-9118', 'T-106', 'PROPOSED', 'DONE', '2026-01-02T00:00:00+00:00' ) ) )[0], false );
		$cases[] = array( 'task-broken-chain', $this->validateTaskTransitionConsistency( array( 'T-107' => array( 'id' => 'T-107', 'status' => 'ASSIGNED', 'approvals' => $approvalsRequired ) ), array( $create( 'EV-9119', 'T-107' ), $transition( 'EV-9120', 'T-107', 'PROPOSED', 'SPECIFIED', '2026-01-02T00:00:00+00:00' ), $transition( 'EV-9121', 'T-107', 'READY', 'ASSIGNED', '2026-01-03T00:00:00+00:00' ) ) )[0], false );
		$cases[] = array( 'task-unknown-transition', $this->validateTaskTransitionConsistency( array( 'T-108' => $taskProposed ), array( $create( 'EV-9122', 'T-108' ), $transition( 'EV-9123', 'T-999', 'PROPOSED', 'SPECIFIED', '2026-01-02T00:00:00+00:00' ) ) )[0], false );
		$cases[] = array( 'task-terminal-transition', $this->validateTaskTransitionConsistency( array( 'T-109' => array( 'id' => 'T-109', 'status' => 'VERIFIED', 'approvals' => $approvalsApproved ) ), array( $create( 'EV-9124', 'T-109' ), $transition( 'EV-9125', 'T-109', 'PROPOSED', 'COMPLETE', '2026-01-02T00:00:00+00:00' ), $transition( 'EV-9126', 'T-109', 'COMPLETE', 'VERIFIED', '2026-01-03T00:00:00+00:00' ) ) )[0], false );
		$cases[] = array( 'task-final-status-mismatch', $this->validateTaskTransitionConsistency( array( 'T-110' => array( 'id' => 'T-110', 'status' => 'INTEGRATED', 'approvals' => $approvalsApproved ) ), array( $create( 'EV-9127', 'T-110' ), $transition( 'EV-9128', 'T-110', 'PROPOSED', 'SPECIFIED', '2026-01-02T00:00:00+00:00' ) ) )[0], false );

		$calendarInvalid = $create( 'EV-9129', 'T-111', '2026-02-30T00:00:00+00:00' );
		$cases[] = array( 'event-calendar-invalid', $this->validateEventLog( array( 'version' => 1, 'events' => array( $calendarInvalid ) ) ), false );
		$outOfOrder = array(
			array( 'id' => 'EV-9130', 'timestamp' => '2026-01-02T00:00:00+00:00', 'actor' => 'orchestrator', 'task_id' => 'MAG-BOOTSTRAP', 'type' => 'VALIDATION_PASSED', 'description' => 'later' ),
			array( 'id' => 'EV-9131', 'timestamp' => '2026-01-01T00:00:00+00:00', 'actor' => 'orchestrator', 'task_id' => 'MAG-BOOTSTRAP', 'type' => 'VALIDATION_PASSED', 'description' => 'earlier' ),
		);
		$cases[] = array( 'event-out-of-order', $this->validateEventLog( array( 'version' => 1, 'events' => $outOfOrder ) ), false );

		$approvalEvidence = array(
			array( 'id' => 'EV-9200', 'timestamp' => '2026-01-01T00:00:00+00:00', 'actor' => 'repository-owner', 'task_id' => 'T-200', 'type' => 'APPROVAL_RECORDED', 'description' => 'security approved by repository owner' ),
			array( 'id' => 'EV-9202', 'timestamp' => '2026-01-02T00:00:00+00:00', 'actor' => 'repository-owner', 'task_id' => 'T-200', 'type' => 'APPROVAL_RECORDED', 'description' => 'integration approved by repository owner' ),
		);
		$cases[] = array( 'approval-evidence-valid', $this->validateApprovalEvidence( array( array( 'task' => array( 'id' => 'T-200', 'approvals' => $approvalsApproved ) ) ), $approvalEvidence ), true );
		$cases[] = array( 'approval-evidence-missing', $this->validateApprovalEvidence( array( array( 'task' => array( 'id' => 'T-201', 'approvals' => $approvalsApproved ) ) ), $approvalEvidence ), false );
		$cases[] = array( 'approval-actor-invalid', $this->validateApprovalEvidence( array( array( 'task' => array( 'id' => 'T-200', 'approvals' => $approvalsApproved ) ) ), array( array( 'id' => 'EV-9201', 'timestamp' => '2026-01-01T00:00:00+00:00', 'actor' => 'backend-engineer', 'task_id' => 'T-200', 'type' => 'APPROVAL_RECORDED', 'description' => 'security approved by repository owner' ) ) ), false );
		$cases[] = array( 'gate-security-required', $this->validateTaskApprovalGates( array( 'id' => 'T-202', 'status' => 'INTEGRATION_READY', 'approvals' => $approvalsRequired ) ), false );
		$cases[] = array( 'gate-integration-required', $this->validateTaskApprovalGates( array( 'id' => 'T-203', 'status' => 'INTEGRATED', 'approvals' => $approvalsRequired ) ), false );

		$leaseBase = array( 'path' => '.agents/policies/example.md', 'task_id' => 'T-300', 'owner_agent' => 'documentation-engineer', 'mode' => 'exclusive', 'workspace' => 'worktree-label:t-300', 'base_commit' => $base, 'status' => 'active', 'lease_started_at' => '2026-01-01T00:00:00+00:00', 'lease_expires_at' => '2099-01-01T00:00:00+00:00', 'last_heartbeat_at' => '2026-01-01T00:00:00+00:00', 'dependencies' => array(), 'integration_status' => 'pending' );
		$registryOk = array( 'version' => 1, 'updated_at' => '2026-01-01T00:00:00+00:00', 'entries' => array( $leaseBase ) );
		$taskScope = array( 'scope' => array( 'allowed' => array( '.agents/policies/example.md' ) ) );
		$authorityWrite = array( 'T-300' => array_merge( $taskScope, array( 'permissions' => array( 'WRITE_SCOPED' ) ) ) );
		$cases[] = array( 'ownership-write-authority', $this->validateOwnership( $registryOk, array( 'T-300' ), $authorityWrite ), true );
		$cases[] = array( 'ownership-unknown-task', $this->validateOwnership( $registryOk, array(), array() ), false );
		$cases[] = array( 'ownership-no-authority', $this->validateOwnership( $registryOk, array( 'T-300' ), array( 'T-300' => array_merge( $taskScope, array( 'permissions' => array( 'OBSERVE' ) ) ) ) ), false );
		$integrateIntegrated = $leaseBase;
		$integrateIntegrated['integration_status'] = 'integrated';
		$registryIntegrate = array( 'version' => 1, 'updated_at' => '2026-01-01T00:00:00+00:00', 'entries' => array( $integrateIntegrated ) );
		$cases[] = array( 'ownership-integrate-integrated', $this->validateOwnership( $registryIntegrate, array( 'T-300' ), array( 'T-300' => array_merge( $taskScope, array( 'permissions' => array( 'INTEGRATE' ) ) ) ) ), false );
		$expired = $leaseBase;
		$expired['lease_expires_at'] = '2020-01-01T00:00:00+00:00';
		$registryExpired = array( 'version' => 1, 'updated_at' => '2026-01-01T00:00:00+00:00', 'entries' => array( $expired ) );
		$cases[] = array( 'ownership-expired-active', $this->validateOwnership( $registryExpired, array( 'T-300' ), $authorityWrite ), false );

		$createdValid = $this->loadYaml( $fixtures . '/event-log-task-created-valid.yaml' );
		$cases[] = array( 'event-task-created-valid', $this->validateEventLog( $createdValid ), true );
		$createdLate = $this->loadYaml( $fixtures . '/event-log-task-created-late-state.yaml' );
		$cases[] = array( 'event-task-created-late-state', $this->validateEventLog( $createdLate ), false );
		$createdChronology = $this->loadYaml( $fixtures . '/event-log-task-created-chronology.yaml' );
		$cases[] = array( 'event-task-created-chronology', $this->validateEventLog( $createdChronology ), false );
		$createdMissing = $this->loadYaml( $fixtures . '/event-log-task-created-missing.yaml' );
		$cases[] = array( 'event-task-created-missing', $this->validateTaskTransitionConsistency( array( 'TASK-CREATE-004' => array( 'id' => 'TASK-CREATE-004', 'status' => 'SPECIFIED' ) ), $createdMissing['events'] )[0], false );
		$createdDuplicate = $this->loadYaml( $fixtures . '/event-log-task-created-duplicate.yaml' );
		$cases[] = array( 'event-task-created-duplicate', $this->validateTaskTransitionConsistency( array( 'TASK-CREATE-005' => array( 'id' => 'TASK-CREATE-005', 'status' => 'PROPOSED' ) ), $createdDuplicate['events'] )[0], false );

		$cases[] = array( 'gate-security-verified', $this->validateTaskApprovalGates( array( 'id' => 'T-204', 'status' => 'VERIFIED', 'approvals' => $approvalsRequired ) ), false );
		$cases[] = array( 'approval-evidence-pending-wording', $this->validateApprovalEvidence( array( array( 'task' => array( 'id' => 'T-205', 'approvals' => array( 'architecture' => 'not-required', 'security' => 'approved', 'integration' => 'not-required' ) ) ) ), array( array( 'id' => 'EV-9203', 'timestamp' => '2026-01-01T00:00:00+00:00', 'actor' => 'repository-owner', 'task_id' => 'T-205', 'type' => 'APPROVAL_RECORDED', 'description' => 'security disapproved' ) ) ), false );
		$schema_no_timestamp_pattern = is_array( $eventSchema ) ? $eventSchema : array();
		unset( $schema_no_timestamp_pattern['properties']['events']['items']['properties']['timestamp']['pattern'] );
		$cases[] = array( 'event-schema-missing-timestamp-pattern', $this->validateEventSchemaContract( $schema_no_timestamp_pattern ), false );

		$cases[] = array( 'approval-evidence-disapproved', $this->validateApprovalEvidence( array( array( 'task' => array( 'id' => 'T-206', 'approvals' => array( 'architecture' => 'not-required', 'security' => 'approved', 'integration' => 'not-required' ) ) ) ), array( array( 'id' => 'EV-9204', 'timestamp' => '2026-01-01T00:00:00+00:00', 'actor' => 'repository-owner', 'task_id' => 'T-206', 'type' => 'APPROVAL_RECORDED', 'description' => 'security disapproved' ) ) ), false );
		$schema_wrong_pattern = is_array( $eventSchema ) ? $eventSchema : array();
		$schema_wrong_pattern['properties']['events']['items']['properties']['timestamp']['pattern'] = 'd{4}';
		$cases[] = array( 'event-schema-wrong-timestamp-pattern', $this->validateEventSchemaContract( $schema_wrong_pattern ), false );

		$leaseForF2 = static function ( string $path, string $integrationStatus = 'pending' ) use ( $leaseBase ): array {
			$lease = $leaseBase;
			$lease['path'] = $path;
			$lease['integration_status'] = $integrationStatus;
			return $lease;
		};
		$registryForF2 = static function ( array $lease ): array {
			return array( 'version' => 1, 'updated_at' => '2026-01-01T00:00:00+00:00', 'entries' => array( $lease ) );
		};
		$knownF2 = array( 'T-300' );
		$cases[] = array( 'scope-exact-file', $this->validateOwnership( $registryForF2( $leaseForF2( '.agents/policies/example.md' ) ), $knownF2, array( 'T-300' => array( 'permissions' => array( 'WRITE_SCOPED' ), 'scope' => array( 'allowed' => array( '.agents/policies/example.md' ) ) ) ) ), true );
		$cases[] = array( 'scope-inside-directory', $this->validateOwnership( $registryForF2( $leaseForF2( '.agents/policies/example.md' ) ), $knownF2, array( 'T-300' => array( 'permissions' => array( 'WRITE_SCOPED' ), 'scope' => array( 'allowed' => array( '.agents/policies/' ) ) ) ) ), true );
		$cases[] = array( 'scope-outside', $this->validateOwnership( $registryForF2( $leaseForF2( '.agents/policies/example.md' ) ), $knownF2, array( 'T-300' => array( 'permissions' => array( 'WRITE_SCOPED' ), 'scope' => array( 'allowed' => array( '.agents/state/' ) ) ) ) ), false );
		$cases[] = array( 'scope-prefix-confusion', $this->validateOwnership( $registryForF2( $leaseForF2( 'src/store-old' ) ), $knownF2, array( 'T-300' => array( 'permissions' => array( 'WRITE_SCOPED' ), 'scope' => array( 'allowed' => array( 'src/store' ) ) ) ) ), false );
		$cases[] = array( 'scope-parent-traversal', $this->validateOwnership( $registryForF2( $leaseForF2( '../evil.php' ) ), $knownF2, array( 'T-300' => array( 'permissions' => array( 'WRITE_SCOPED' ), 'scope' => array( 'allowed' => array( '.agents/policies/' ) ) ) ) ), false );
		$cases[] = array( 'scope-malformed-absolute', $this->validateOwnership( $registryForF2( $leaseForF2( '/etc/passwd' ) ), $knownF2, array( 'T-300' => array( 'permissions' => array( 'WRITE_SCOPED' ), 'scope' => array( 'allowed' => array( '.agents/policies/' ) ) ) ) ), false );
		$cases[] = array( 'scope-integrate-valid', $this->validateOwnership( $registryForF2( $leaseForF2( '.agents/policies/example.md', 'pending' ) ), $knownF2, array( 'T-300' => array( 'permissions' => array( 'INTEGRATE' ), 'scope' => array( 'allowed' => array( '.agents/policies/' ) ) ) ) ), true );
		$cases[] = array( 'scope-multiple-allowed', $this->validateOwnership( $registryForF2( $leaseForF2( '.agents/policies/example.md' ) ), $knownF2, array( 'T-300' => array( 'permissions' => array( 'WRITE_SCOPED' ), 'scope' => array( 'allowed' => array( '.agents/other/file.md', '.agents/policies/example.md' ) ) ) ) ), true );

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
			'.agents/scripts/fixtures/event-log-legacy-valid.yaml',
			'.agents/scripts/fixtures/event-log-transition-valid.yaml',
			'.agents/scripts/fixtures/event-log-transition-missing-from.yaml',
			'.agents/scripts/fixtures/event-log-transition-missing-to.yaml',
			'.agents/scripts/fixtures/event-log-transition-invalid-state.yaml',
			'.agents/scripts/fixtures/event-log-transition-invalid-transition.yaml',
			'.agents/scripts/fixtures/event-log-transition-fields-on-non-transition.yaml',
			'.agents/scripts/fixtures/event-log-unknown-property.yaml',
			'.agents/scripts/fixtures/event-log-transition-invalid-type.yaml',
			'.agents/scripts/fixtures/event-log-transition-missing-actor.yaml',
			'.agents/scripts/fixtures/event-log-transition-rejected-superseded.yaml',
			'.agents/scripts/fixtures/event-log-transition-invalid-iso-timestamp.yaml',
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
		$known = array();
		$authority = array();
		foreach ( $this->loadTaskRecords() as $record ) {
			$id = (string) ( $record['task']['id'] ?? '' );
			if ( '' === $id ) {
				continue;
			}
			$known[ $id ] = true;
			$authority[ $id ] = $record['task'];
		}
		$errors = $this->validateOwnership( $registry, array_keys( $known ), $authority );
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
			if ( 'AGENTS.md' === $path || 0 === strpos( $path, '.agents/' ) || '.github/workflows/governance.yml' === $path ) {
				continue;
			}
			$bad[] = $path;
		}
		if ( array() === $bad ) {
			$this->pass( 'bootstrap scope: changes limited to governance paths' );
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

	private function validateOwnership(array $registry, ?array $knownTaskIds = null, ?array $authorityMap = null): array {
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
			if ( is_array( $knownTaskIds ) && ! in_array( $entry['task_id'] ?? '', $knownTaskIds, true ) ) {
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
				$task_id = (string) ( $entry['task_id'] ?? '' );
				if ( is_array( $authorityMap ) && isset( $authorityMap[ $task_id ] ) ) {
					$task = $authorityMap[ $task_id ];
					$permissions = is_array( $task['permissions'] ?? null ) ? $task['permissions'] : array();
					$hasWrite = in_array( 'WRITE_SCOPED', $permissions, true );
					$hasIntegrate = in_array( 'INTEGRATE', $permissions, true );
					if ( ! $hasWrite && ! $hasIntegrate ) {
						$errors[] = $label . ' active lease requires WRITE_SCOPED or INTEGRATE on task ' . $task_id;
					}
					if ( ! $hasWrite && $hasIntegrate && 'pending' !== ( $entry['integration_status'] ?? '' ) ) {
						$errors[] = $label . ' integration-only active lease must have integration_status pending';
					}
					$allowedPaths = is_array( $task['scope']['allowed'] ?? null ) ? $task['scope']['allowed'] : array();
					$path         = (string) ( $entry['path'] ?? '' );
					if ( array() === $allowedPaths ) {
						$errors[] = $label . ' active lease task ' . $task_id . ' has no allowed scope';
					} else {
						$withinScope = false;
						foreach ( $allowedPaths as $allowedPath ) {
							if ( ! is_string( $allowedPath ) || ! $this->safePath( $allowedPath ) ) {
								continue;
							}
							if ( $this->pathWithinScope( $allowedPath, $path ) ) {
								$withinScope = true;
								break;
							}
						}
						if ( ! $withinScope ) {
							$errors[] = $label . ' path "' . $path . '" is outside the allowed scope of task ' . $task_id;
						}
					}
				}
				$expires = strtotime( (string) ( $entry['lease_expires_at'] ?? '' ) );
				if ( false !== $expires && $expires < time() ) {
					$errors[] = $label . ' active lease is expired';
				}
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

	private function validateEventSchemaContract(array $schema): array {
		$errors = array();
		if ( ($schema['$schema'] ?? '') !== 'https://json-schema.org/draft/2020-12/schema' ) {
			$errors[] = 'event schema must declare JSON Schema draft 2020-12';
		}
		if ( ($schema['additionalProperties'] ?? null) !== false ) {
			$errors[] = 'event log schema must reject unknown top-level properties';
		}
		$state_enum = $schema['$defs']['lifecycleState']['enum'] ?? null;
		if ( ! is_array( $state_enum ) ) {
			$errors[] = 'event schema lifecycleState enum is missing';
		} else {
			$enum_sorted = $state_enum;
			sort( $enum_sorted );
			$states_sorted = self::TASK_STATES;
			sort( $states_sorted );
			if ( $enum_sorted !== $states_sorted ) {
				$errors[] = 'event schema lifecycleState enum does not semantically match the task lifecycle states';
			}
		}
		$item = $schema['properties']['events']['items'] ?? null;
		if ( ! is_array( $item ) ) {
			return array_merge( $errors, array( 'event schema items definition is missing' ) );
		}
		if ( ($item['additionalProperties'] ?? null) !== false ) {
			$errors[] = 'event schema items must reject unknown event properties';
		}
		$required = is_array( $item['required'] ?? null ) ? $item['required'] : array();
		foreach ( array( 'id', 'timestamp', 'actor', 'task_id', 'type', 'description' ) as $field ) {
			if ( ! in_array( $field, $required, true ) ) {
				$errors[] = 'event schema is missing required field ' . $field;
			}
		}
		$properties = is_array( $item['properties'] ?? null ) ? $item['properties'] : array();
		$expectedTimestampPattern = '^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}(?:\\.\\d+)?(?:Z|[+-]\\d{2}:\\d{2})$';
		if ( ($properties['timestamp']['pattern'] ?? '') !== $expectedTimestampPattern ) {
			$errors[] = 'event schema timestamp must declare the strict ISO-8601 pattern';
		}
		foreach ( array( 'from_state', 'to_state', 'initial_state' ) as $field ) {
			if ( ! isset( $properties[ $field ] ) ) {
				$errors[] = 'event schema must define ' . $field;
				continue;
			}
			if ( ($properties[ $field ]['$ref'] ?? '') !== '#/$defs/lifecycleState' ) {
				$errors[] = 'event schema ' . $field . ' must reference the lifecycleState definition';
			}
		}
		$all_of = is_array( $item['allOf'] ?? null ) ? $item['allOf'] : array();
		$has_transition_rule = false;
		$has_pair_rule       = false;
		$has_exclusive_rule  = false;
		$has_creation_rule = false;
		$has_initial_rule = false;
		foreach ( $all_of as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$if   = is_array( $rule['if'] ?? null ) ? $rule['if'] : array();
			$then = is_array( $rule['then'] ?? null ) ? $rule['then'] : array();
			$then_required = is_array( $then['required'] ?? null ) ? $then['required'] : array();
			if ( ($if['properties']['type']['const'] ?? null) === 'TASK_TRANSITION'
				&& in_array( 'from_state', $then_required, true )
				&& in_array( 'to_state', $then_required, true ) ) {
				$has_transition_rule = true;
			}
			if ( is_array( $if['anyOf'] ?? null )
				&& in_array( 'from_state', $then_required, true )
				&& in_array( 'to_state', $then_required, true ) ) {
				$has_pair_rule = true;
			}
			if ( ($if['properties']['type']['not']['const'] ?? null) === 'TASK_TRANSITION'
				&& is_array( $then['not']['anyOf'] ?? null ) ) {
				$has_exclusive_rule = true;
			}
			if ( ($if['properties']['type']['const'] ?? null) === 'TASK_CREATED'
				&& in_array( 'initial_state', $then_required, true ) ) {
				$has_creation_rule = true;
			}
			if ( is_array( $if['anyOf'] ?? null )
				&& in_array( 'initial_state', array_map( static function ( $entry ) { return is_array( $entry ) ? (string) ( $entry['required'][0] ?? '' ) : ''; }, $if['anyOf'] ), true )
				&& ($then['properties']['type']['const'] ?? null) === 'TASK_CREATED' ) {
				$has_initial_rule = true;
			}
		}
		if ( ! $has_transition_rule ) {
			$errors[] = 'event schema must require from_state and to_state for TASK_TRANSITION';
		}
		if ( ! $has_pair_rule ) {
			$errors[] = 'event schema must require both fields when either transition field is present';
		}
		if ( ! $has_exclusive_rule ) {
			$errors[] = 'event schema must reject transition fields on non-transition events';
		}
		if ( ! $has_creation_rule ) {
			$errors[] = 'event schema must require initial_state for TASK_CREATED';
		}
		if ( ! $has_initial_rule ) {
			$errors[] = 'event schema must allow initial_state only on TASK_CREATED';
		}
		return $errors;
	}

	/**
	 * Parse the policy's allowed-transitions table and terminal-state declaration.
	 *
	 * @return array{0: array<string, list<string>>|null, 1: list<string>}
	 */
	private function parsePolicyTransitions(string $policy): array {
		$errors = array();
		if ( ! preg_match( '/^## Allowed transitions\s*$(.*?)(?=^##\s)/ms', $policy, $match ) ) {
			return array( null, array( 'task lifecycle policy is missing the Allowed transitions section' ) );
		}
		$transitions = array();
		$found       = false;
		foreach ( preg_split( '/\r\n|\n|\r/', $match[1] ) as $line ) {
			if ( ! preg_match( '/^\|\s*`([A-Z_]+)`\s*\|\s*(.*?)\s*\|$/', trim( $line ), $row ) ) {
				continue;
			}
			$found = true;
			$from  = $row[1];
			$tos   = array();
			$cell  = trim( $row[2] );
			if ( '' !== $cell && '—' !== $cell && '-' !== $cell ) {
				foreach ( preg_split( '/,/', $cell ) as $part ) {
					$part = trim( $part );
					if ( ! preg_match( '/^`([A-Z_]+)`$/', $part, $target ) ) {
						$errors[] = 'task lifecycle policy has an invalid transition target for ' . $from . ': ' . $part;
						continue;
					}
					$tos[] = $target[1];
				}
			}
			if ( isset( $transitions[ $from ] ) ) {
				$errors[] = 'task lifecycle policy has duplicate transition rows for ' . $from;
			}
			$transitions[ $from ] = array_values( array_unique( $tos ) );
		}
		if ( ! $found ) {
			$errors[] = 'task lifecycle policy transition table has no parsable rows';
		}
		if ( ! preg_match( '/^Terminal states:\s*(.*)$/m', $policy, $terminal ) ) {
			$errors[] = 'task lifecycle policy is missing the terminal-state declaration';
		} else {
			preg_match_all( '/`([A-Z_]+)`/', $terminal[1], $states );
			foreach ( $states[1] as $state ) {
				if ( ! isset( $transitions[ $state ] ) ) {
					$transitions[ $state ] = array();
				}
			}
		}
		foreach ( array_keys( $transitions ) as $state ) {
			if ( ! in_array( $state, self::TASK_STATES, true ) ) {
				$errors[] = 'task lifecycle policy contains unknown state ' . $state;
			}
		}
		ksort( $transitions );
		foreach ( $transitions as &$tos ) {
			sort( $tos );
		}
		unset( $tos );
		return array( $transitions, $errors );
	}

	/**
	 * @param  array<string, list<string>>  $map
	 * @return array<string, list<string>>
	 */
	private function normalizeTransitionMap(array $map): array {
		$normalized = array();
		foreach ( $map as $from => $tos ) {
			$tos = array_values( array_unique( array_map( 'strval', (array) $tos ) ) );
			sort( $tos );
			$normalized[ (string) $from ] = $tos;
		}
		ksort( $normalized );
		return $normalized;
	}

	/**
	 * @param  array<string, list<string>>  $policy_transitions
	 * @return list<string>
	 */
	private function validateTransitionTableContract(array $policy_transitions): array {
		$errors   = array();
		$policy   = $this->normalizeTransitionMap( $policy_transitions );
		$validator = $this->normalizeTransitionMap( self::TRANSITIONS );
		foreach ( array_diff( array_keys( $validator ), array_keys( $policy ) ) as $missing ) {
			$errors[] = 'task lifecycle policy is missing transition row for ' . $missing;
		}
		foreach ( array_diff( array_keys( $policy ), array_keys( $validator ) ) as $unexpected ) {
			$errors[] = 'task lifecycle policy contains unexpected transition row for ' . $unexpected;
		}
		foreach ( array_intersect( array_keys( $validator ), array_keys( $policy ) ) as $state ) {
			if ( $validator[ $state ] !== $policy[ $state ] ) {
				$errors[] = sprintf(
					'task lifecycle policy transition mismatch for %s: policy [%s] vs validator [%s]',
					$state,
					implode( ',', $policy[ $state ] ),
					implode( ',', $validator[ $state ] )
				);
			}
		}
		return $errors;
	}

	private function validateLifecyclePolicyContract(string $policy): array {
		$errors = array();
		foreach (
			array(
				'/^## Task creation events\s*$/m'                      => 'Task creation events section',
				'/^## Lifecycle transition events\s*$/m'                => 'Lifecycle transition events section',
				'/^### Legacy events\s*$/m'                             => 'Legacy events section',
				'/^### Corrections and exceptional transitions\s*$/m'  => 'corrections section',
				'/^### Evidence before `VERIFIED` or `COMPLETE`\s*$/m' => 'evidence section',
			) as $pattern => $label
		) {
			if ( ! preg_match( $pattern, $policy ) ) {
				$errors[] = 'task lifecycle policy is missing ' . $label;
			}
		}
		foreach ( array( 'TASK_CREATED', 'TASK_TRANSITION', 'initial_state', 'from_state', 'to_state' ) as $token ) {
			if ( ! preg_match( '/\b' . preg_quote( $token, '/' ) . '\b/', $policy ) ) {
				$errors[] = 'task lifecycle policy is missing ' . $token;
			}
		}
		list( $transitions, $parse_errors ) = $this->parsePolicyTransitions( $policy );
		$errors = array_merge( $errors, $parse_errors );
		if ( is_array( $transitions ) ) {
			$errors = array_merge( $errors, $this->validateTransitionTableContract( $transitions ) );
		}
		return $errors;
	}

	private function validateTransitionTableCompleteness(): array {
		$errors = array();
		foreach ( self::TASK_STATES as $from ) {
			foreach ( self::TASK_STATES as $to ) {
				$expected = in_array( $to, self::TRANSITIONS[ $from ] ?? array(), true );
				if ( $expected !== $this->isTransitionAllowed( $from, $to ) ) {
					$errors[] = 'transition table fallback mismatch for ' . $from . ' -> ' . $to;
				}
			}
		}
		if ( $this->isTransitionAllowed( 'REJECTED', 'SUPERSEDED' ) ) {
			$errors[] = 'terminal REJECTED state must not transition to SUPERSEDED';
		}
		if ( $this->isTransitionAllowed( 'DONE', 'COMPLETE' ) ) {
			$errors[] = 'unknown source state must not be accepted';
		}
		return $errors;
	}

	private function loadEvents(): array {
		$log = $this->loadYaml( $this->root . '/.agents/state/event-log.yaml' );
		return is_array( $log['events'] ?? null ) ? $log['events'] : array();
	}

	/** @return list<array{path:string,task:array<string,mixed>}> */
	private function loadTaskRecords(): array {
		$records = array();
		$dir = $this->root . '/.agents/state';
		if ( ! is_dir( $dir ) ) {
			return $records;
		}
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS )
		);
		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() || ! preg_match( '/\.task\.yaml$/', $file->getFilename() ) ) {
				continue;
			}
			$task = $this->loadYaml( $file->getPathname() );
			if ( is_array( $task ) && ! empty( $task['id'] ) ) {
				$records[] = array( 'path' => $file->getPathname(), 'task' => $task );
			}
		}
		return $records;
	}

	private function checkTaskContractIntegrity(): void {
		$records = $this->loadTaskRecords();
		$known = array();
		$errors = array();
		foreach ( $records as $record ) {
			$id = (string) ( $record['task']['id'] ?? '' );
			if ( isset( $known[ $id ] ) ) {
				$errors[] = 'duplicate task id: ' . $id;
			}
			$known[ $id ] = true;
		}
		$knownIds = array_keys( $known );
		foreach ( $records as $record ) {
			$prefix = $this->relative( $record['path'] ) . ': ';
			foreach ( $this->validateTask( $record['task'], $knownIds ) as $error ) {
				$errors[] = $prefix . $error;
			}
			foreach ( $this->validateTaskApprovalGates( $record['task'] ) as $error ) {
				$errors[] = $prefix . $error;
			}
		}
		$errors = array_merge( $errors, $this->validateApprovalEvidence( $records, $this->loadEvents() ) );
		if ( array() !== $errors ) {
			foreach ( $errors as $error ) {
				$this->error( 'task contract integrity: ' . $error );
			}
			return;
		}
		$this->pass( 'task contract integrity: ' . count( $records ) . ' task(s) validated' );
	}

	private function validateTaskApprovalGates(array $task): array {
		$errors = array();
		$id = (string) ( $task['id'] ?? 'task' );
		$status = (string) ( $task['status'] ?? '' );
		$approvals = is_array( $task['approvals'] ?? null ) ? $task['approvals'] : array();
		$security = (string) ( $approvals['security'] ?? 'required' );
		$integration = (string) ( $approvals['integration'] ?? 'required' );
		if ( in_array( $status, array( 'INTEGRATION_READY', 'INTEGRATING', 'INTEGRATED', 'VERIFIED', 'COMPLETE' ), true )
			&& ! in_array( $security, array( 'approved', 'not-required' ), true ) ) {
			$errors[] = $id . ' cannot be ' . $status . ' with security approval ' . $security;
		}
		if ( in_array( $status, array( 'INTEGRATING', 'INTEGRATED', 'VERIFIED', 'COMPLETE' ), true )
			&& ! in_array( $integration, array( 'approved', 'not-required' ), true ) ) {
			$errors[] = $id . ' cannot be ' . $status . ' with integration approval ' . $integration;
		}
		return $errors;
	}

	private function validateApprovalEvidence(array $records, array $events): array {
		$errors = array();
		foreach ( $records as $record ) {
			$task = $record['task'];
			$id = (string) ( $task['id'] ?? '' );
			$approvals = is_array( $task['approvals'] ?? null ) ? $task['approvals'] : array();
			foreach ( self::APPROVAL_GATES as $gate ) {
				if ( 'approved' !== ( $approvals[ $gate ] ?? '' ) ) {
					continue;
				}
				$found = false;
				foreach ( $events as $event ) {
					if ( ! is_array( $event ) ) {
						continue;
					}
					if ( ! in_array( (string) ( $event['type'] ?? '' ), self::APPROVAL_EVENT_TYPES, true ) ) {
						continue;
					}
					if ( $id !== (string) ( $event['task_id'] ?? '' ) ) {
						continue;
					}
					if ( ! in_array( (string) ( $event['actor'] ?? '' ), self::APPROVERS, true ) ) {
						continue;
					}
					$description = (string) ( $event['description'] ?? '' );
					$hasNegation = false;
					foreach ( array( 'pending', 'not ', 'rejected', 'disapproved', 'denied', 'refused', 'withheld' ) as $negation ) {
						if ( false !== stripos( $description, $negation ) ) {
							$hasNegation = true;
							break;
						}
					}
					if ( $hasNegation
						|| ! preg_match( '/\b' . preg_quote( $gate, '/' ) . '\b/i', $description )
						|| ! preg_match( '/\bapproved\b/i', $description ) ) {
						continue;
					}
					$found = true;
					break;
				}
				if ( ! $found ) {
					$errors[] = $id . ' has approvals.' . $gate . '=approved without a valid APPROVAL_RECORDED event';
				}
			}
		}
		return $errors;
	}

	private function checkTaskTransitionConsistency(): void {
		$records = $this->loadTaskRecords();
		$tasks = array();
		foreach ( $records as $record ) {
			$tasks[ (string) $record['task']['id'] ] = $record['task'];
		}
		list( $errors, $checked ) = $this->validateTaskTransitionConsistency( $tasks, $this->loadEvents() );
		if ( array() !== $errors ) {
			foreach ( $errors as $error ) {
				$this->error( 'task transition consistency: ' . $error );
			}
			return;
		}
		$this->pass( 'task transition consistency: ' . $checked . ' task(s) checked' );
	}

	private function isValidTimestamp(string $timestamp): bool {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $timestamp ) ) {
			return false;
		}
		try {
			$dt = new DateTimeImmutable( str_replace( 'Z', '+00:00', $timestamp ) );
		} catch ( Throwable $e ) {
			return false;
		}
		return $dt->format( 'Y-m-d' ) === substr( $timestamp, 0, 10 )
			&& $dt->format( 'H:i:s' ) === substr( $timestamp, 11, 8 );
	}

	/**
	 * @param  array<string, array<string,mixed>>  $tasks
	 * @param  list<array<string,mixed>>  $events
	 * @return array{0:list<string>,1:int} errors, checked count
	 */
	private function validateTaskTransitionConsistency(array $tasks, array $events): array {
		$errors = array();
		$creations = array();
		$transitions = array();
		foreach ( $events as $event ) {
			if ( ! is_array( $event ) ) {
				continue;
			}
			$type = (string) ( $event['type'] ?? '' );
			$task_id = (string) ( $event['task_id'] ?? '' );
			$event_id = (string) ( $event['id'] ?? 'event' );
			if ( ! isset( $tasks[ $task_id ] ) ) {
				if ( in_array( $type, array_merge( self::TRANSITION_EVENT_TYPES, self::CREATION_EVENT_TYPES ), true ) ) {
					$errors[] = $event_id . ' references unknown task ' . $task_id;
				}
				continue;
			}
			$timestamp = (string) ( $event['timestamp'] ?? '' );
			if ( ! $this->isValidTimestamp( $timestamp ) ) {
				$errors[] = $event_id . ' has an invalid ISO-8601 timestamp';
				continue;
			}
			$time = strtotime( str_replace( 'Z', '+00:00', $timestamp ) );
			if ( in_array( $type, self::CREATION_EVENT_TYPES, true ) ) {
				$initial = (string) ( $event['initial_state'] ?? '' );
				if ( ! in_array( $initial, self::TASK_STATES, true ) ) {
					$errors[] = $event_id . ' has invalid initial_state ' . $initial;
					continue;
				}
				if ( 'PROPOSED' !== $initial ) {
					$errors[] = $event_id . ' must create task ' . $task_id . ' in PROPOSED';
				}
				$creations[ $task_id ][] = array( 'id' => $event_id, 'time' => $time, 'initial' => $initial );
				continue;
			}
			if ( in_array( $type, self::TRANSITION_EVENT_TYPES, true ) ) {
				$from = (string) ( $event['from_state'] ?? '' );
				$to = (string) ( $event['to_state'] ?? '' );
				if ( ! in_array( $from, self::TASK_STATES, true ) ) {
					$errors[] = $event_id . ' has invalid from_state ' . $from;
					continue;
				}
				if ( ! in_array( $to, self::TASK_STATES, true ) ) {
					$errors[] = $event_id . ' has invalid to_state ' . $to;
					continue;
				}
				if ( ! $this->isTransitionAllowed( $from, $to ) ) {
					$errors[] = $event_id . ' has invalid lifecycle transition ' . $from . ' -> ' . $to;
				}
				$transitions[ $task_id ][] = array( 'id' => $event_id, 'time' => $time, 'from' => $from, 'to' => $to );
			}
		}
		foreach ( $tasks as $task_id => $task ) {
			$status = (string) ( $task['status'] ?? '' );
			if ( ! in_array( $status, self::TASK_STATES, true ) ) {
				$errors[] = 'task ' . $task_id . ' has invalid declared status ' . $status;
				continue;
			}
			if ( ! isset( $creations[ $task_id ] ) || array() === $creations[ $task_id ] ) {
				$errors[] = 'task ' . $task_id . ' is missing TASK_CREATED';
				continue;
			}
			if ( count( $creations[ $task_id ] ) > 1 ) {
				$errors[] = 'task ' . $task_id . ' has duplicate TASK_CREATED events';
				continue;
			}
			$created = $creations[ $task_id ][0];
			$chain = $transitions[ $task_id ] ?? array();
			usort( $chain, static function ( array $a, array $b ): int { return $a['time'] <=> $b['time']; } );
			foreach ( $chain as $transition ) {
				if ( $transition['time'] < $created['time'] ) {
					$errors[] = 'task ' . $task_id . ' transition ' . $transition['id'] . ' precedes creation';
				}
			}
			if ( array() === $chain ) {
				if ( 'PROPOSED' !== $status ) {
					$errors[] = 'task ' . $task_id . ' declares status ' . $status . ' but has no transitions from PROPOSED';
				}
				continue;
			}
			$expectedFrom = 'PROPOSED';
			$last = end( $chain );
			$latest = array_values( array_filter( $chain, static function ( array $entry ) use ( $last ): bool { return $entry['time'] === $last['time']; } ) );
			if ( count( $latest ) > 1 ) {
				$errors[] = 'task ' . $task_id . ' has ambiguous latest transitions at the same timestamp';
				continue;
			}
			foreach ( $chain as $transition ) {
				if ( $transition['from'] !== $expectedFrom ) {
					$errors[] = 'task ' . $task_id . ' has a broken transition chain at ' . $transition['id'];
					break;
				}
				$expectedFrom = $transition['to'];
			}
			if ( $last['to'] !== $status ) {
				$errors[] = 'task ' . $task_id . ' declares status ' . $status . ' but latest transition results in ' . $last['to'];
			}
		}
		return array( $errors, count( $tasks ) );
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
		$previousTime = null;
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
			foreach ( array_keys( $event ) as $key ) {
				if ( ! in_array( $key, self::EVENT_KEYS, true ) ) {
					$errors[] = $label . ' unknown property ' . $key;
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
			if ( ! empty( $event['timestamp'] ) && ! $this->isValidTimestamp( (string) $event['timestamp'] ) ) {
				$errors[] = $label . ' timestamp is not a valid ISO-8601 date/time';
			}
			$currentTime = empty( $event['timestamp'] ) ? null : strtotime( str_replace( 'Z', '+00:00', (string) $event['timestamp'] ) );
			if ( null !== $currentTime && null !== $previousTime && $currentTime < $previousTime ) {
				$errors[] = $label . ' timestamp is out of chronological order';
			}
			if ( null !== $currentTime ) {
				$previousTime = $currentTime;
			}
			$type      = isset( $event['type'] ) && is_string( $event['type'] ) ? $event['type'] : '';
			$has_from  = array_key_exists( 'from_state', $event );
			$has_to    = array_key_exists( 'to_state', $event );
			$valid_from = $has_from && is_string( $event['from_state'] ) && in_array( $event['from_state'], self::TASK_STATES, true );
			$valid_to   = $has_to && is_string( $event['to_state'] ) && in_array( $event['to_state'], self::TASK_STATES, true );
			$event_ref  = $label;
			if ( ! empty( $event['id'] ) ) {
				$event_ref .= ' ' . $event['id'];
			}
			if ( ! empty( $event['task_id'] ) ) {
				$event_ref .= ' task ' . $event['task_id'];
			}

			$hasInitial = array_key_exists( 'initial_state', $event );
			$validInitial = $hasInitial && is_string( $event['initial_state'] ) && in_array( $event['initial_state'], self::TASK_STATES, true );
			if ( in_array( $type, self::CREATION_EVENT_TYPES, true ) ) {
				if ( ! $hasInitial ) {
					$errors[] = $event_ref . ' missing initial_state';
				} elseif ( ! $validInitial ) {
					$errors[] = $event_ref . ' invalid initial_state';
				} elseif ( 'PROPOSED' !== $event['initial_state'] ) {
					$errors[] = $event_ref . ' initial_state must be PROPOSED';
				}
				if ( $has_from || $has_to ) {
					$errors[] = $event_ref . ' TASK_CREATED must not contain from_state or to_state';
				}
			} elseif ( $hasInitial ) {
				$errors[] = $event_ref . ' initial_state is only allowed on TASK_CREATED events';
			}
			if ( in_array( $type, self::TRANSITION_EVENT_TYPES, true ) ) {
				if ( ! $has_from ) {
					$errors[] = $event_ref . ' missing from_state';
				}
				if ( ! $has_to ) {
					$errors[] = $event_ref . ' missing to_state';
				}
				if ( $has_from && ! $valid_from ) {
					$errors[] = $event_ref . ' invalid from_state ' . ( is_scalar( $event['from_state'] ) ? (string) $event['from_state'] : gettype( $event['from_state'] ) );
				}
				if ( $has_to && ! $valid_to ) {
					$errors[] = $event_ref . ' invalid to_state ' . ( is_scalar( $event['to_state'] ) ? (string) $event['to_state'] : gettype( $event['to_state'] ) );
				}
				if ( $valid_from && $valid_to && ! $this->isTransitionAllowed( $event['from_state'], $event['to_state'] ) ) {
					$errors[] = $event_ref . ' invalid lifecycle transition ' . $event['from_state'] . ' -> ' . $event['to_state'];
				}
			} elseif ( $has_from || $has_to ) {
				$errors[] = $event_ref . ' transition fields are only allowed on TASK_TRANSITION events';
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

	/**
	 * Component-aware containment using the same semantics as Scope Guard:
	 * an allowed path matches the same path or any descendant separated by
	 * "/". A trailing slash in a contract is the conventional way to mark a
	 * directory but is not required by this function; every allowed path is
	 * treated as a prefix boundary. Substring/prefix confusion such as
	 * `src/store` vs `src/store-old` is rejected by requiring a `/` boundary.
	 */
	private function pathWithinScope(string $allowed, string $path): bool {
		$allowed = rtrim( str_replace( '\\', '/', trim( $allowed ) ), '/' );
		$path    = rtrim( str_replace( '\\', '/', trim( $path ) ), '/' );
		if ( '' === $allowed || '' === $path ) {
			return false;
		}
		return $allowed === $path || 0 === strpos( $path, $allowed . '/' );
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
