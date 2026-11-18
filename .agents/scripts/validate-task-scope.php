#!/usr/bin/env php
<?php
/**
 * Parsian Theme - Agent Scope Guard (CONTRACT-SCOPE-001).
 *
 * Read-only, zero-dependency PHP 8.4 CLI that checks whether changes in a Git
 * worktree remain inside a task contract's allowed paths and the repository's
 * active file-ownership registry.
 *
 * IMPORTANT LIMITATION
 * --------------------
 * This tool DETECTS scope violations; it does not PREVENT writes. An agent or
 * process can still modify files outside its assignment. Worktree isolation,
 * scoped tool permissions, review, and approvals remain the operational
 * controls. A PASS means "no violation was detected in the inspected
 * snapshot", not "the workspace is trustworthy".
 *
 * USAGE
 * -----
 *   php .agents/scripts/validate-task-scope.php \
 *     --task=.agents/state/pilot/PILOT-001.task.yaml \
 *     --base=7b5d36d7dd50752d47de0aaa50fc2262ea26b8dd \
 *     --repo=/path/to/worktree \
 *     --registry=.agents/state/ownership-registry.yaml \
 *     [--json]
 *
 * Options also accept the separate form: --task <path>.
 *
 * EXIT CODES
 * ----------
 *   0  scope valid
 *   1  scope violation
 *   2  invalid task contract, base configuration, or ownership registry
 *   3  Git/workspace inspection failure or incomplete inspection
 *
 * Incomplete inspection never returns 0.
 *
 * SECURITY NOTES
 * --------------
 * - Task contracts, registries, repository paths, and Git output are treated
 *   as untrusted input. No value is interpolated into a shell: processes are
 *   started with proc_open() and an argument array, which bypasses the shell.
 * - The tool runs only read-only Git commands (rev-parse, cat-file, diff,
 *   ls-files). It never stages, commits, checks out, resets, cleans, writes to
 *   the worktree, or executes inspected files.
 * - Repository-relative paths are normalized. Absolute paths, drive paths,
 *   URL-like paths, backslash paths, control characters, empty components,
 *   "." components, and ".." traversal are rejected.
 * - Symlinks whose resolved target leaves the repository are reported. The
 *   tool never follows such a link to read its target.
 * - Git config that could execute external programs for these read-only
 *   queries is disabled where possible (core.fsmonitor=false, --no-ext-diff,
 *   --no-textconv). The commands used here do not invoke Git hooks.
 * - Scope matching is component-aware. "store.php" does not match
 *   "storage.php"; a directory scope matches itself or a descendant separated
 *   by "/". Scope entries are literal repository-relative paths; glob patterns
 *   are not expanded.
 *
 * KNOWN LIMITATIONS (documented rather than hidden)
 * -------------------------------------------------
 * - Detection only; no write prevention.
 * - Ignored files (`.gitignore`) and files inside ignored directories are not
 *   inspected because Git does not report them as untracked.
 * - Changes inside submodules are not inspected; only the gitlink path is
 *   visible to the superproject.
 * - Sparse-checkout, skip-worktree, and assume-unchanged entries can hide
 *   worktree changes from Git; the guard can only evaluate what Git reports.
 * - "active" status is authoritative, but an active lease whose expiry has
 *   passed is not treated as active ownership. Expired leases must be removed
 *   or reissued by the Orchestrator; no ownership is silently transferred.
 * - Task-contract validation covers the fields this guard consumes: id,
 *   workspace.base_commit, and scope.allowed/forbidden. The broader task
 *   schema is owned by validate-governance.php.
 * - Registry workspace and base-commit fields are structurally validated, but
 *   ownership matching uses path, task, mode, and status. The guard does not
 *   refuse a valid lease merely because its recorded workspace differs.
 * - A clean snapshot can still be followed by an out-of-scope write. Run the
 *   guard immediately before integration; it is a detector, not a sandbox.
 * - A consistency pass reduces, but cannot eliminate, race windows in a
 *   worktree that is being modified concurrently.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "This tool is CLI-only.\n");
	exit(2);
}

const SG_EXIT_OK = 0;
const SG_EXIT_SCOPE = 1;
const SG_EXIT_CONFIG = 2;
const SG_EXIT_INSPECTION = 3;

const SG_TASK_ID_PATTERN = '/^[A-Z][A-Z0-9-]{2,63}$/';
const SG_SHA_PATTERN = '/^[0-9a-f]{40}$/';

/**
 * Write scopes that no task may own (policy: file-ownership.md).
 * Matching is component-aware.
 */
const SG_GLOBAL_FORBIDDEN = array(
	'.git',
	'node_modules',
	'vendor',
	'dist',
	'wp-local/site',
	'wp-local/mysql',
	'wp-local/backups',
);

final class ScopeGuardFailure extends RuntimeException {
	public function __construct(
		string $message,
		private readonly int $failureExitCode,
		private readonly string $failureViolation,
	) {
		parent::__construct($message);
	}

	public function failureExitCode(): int {
		return $this->failureExitCode;
	}

	public function failureViolation(): string {
		return $this->failureViolation;
	}
}

/**
 * Minimal YAML reader for the governance subset used by task contracts and
 * ownership registries.
 *
 * Supported: nested mappings, sequences of scalars/mappings, inline scalar
 * lists (`[]` and `[a, b]`), quoted strings, booleans, null, integers, floats,
 * full-line comments, and an optional leading `---`.
 * Not supported: anchors, aliases, multi-document streams, block scalars,
 * flow mappings, and inline comments after values. JSON input is accepted as a
 * bonus when the first non-whitespace character is `{` or `[`.
 *
 * Input is untrusted: size, depth, token count, indentation, and duplicate keys
 * are checked before values are used.
 */
final class MiniYaml {
	private const MAX_BYTES = 4194304;
	private const MAX_DEPTH = 64;
	private const MAX_TOKENS = 200000;

	public static function parseFile(string $path): array {
		if (! is_file($path) || ! is_readable($path)) {
			throw new RuntimeException('cannot read file: ' . $path);
		}
		$size = @filesize($path);
		if (false === $size || $size > self::MAX_BYTES) {
			throw new RuntimeException('file exceeds the 4 MiB input limit: ' . $path);
		}
		$raw = @file_get_contents($path);
		if (false === $raw) {
			throw new RuntimeException('cannot read file: ' . $path);
		}
		return self::parse($raw, $path);
	}

	public static function parse(string $raw, string $source = '<memory>'): array {
		if (str_starts_with($raw, "\xEF\xBB\xBF")) {
			$raw = substr($raw, 3);
		}
		$trimmed = ltrim($raw);
		if ('' !== $trimmed && ('{' === $trimmed[0] || '[' === $trimmed[0])) {
			try {
				$decoded = json_decode($raw, true, self::MAX_DEPTH, JSON_THROW_ON_ERROR);
			} catch (JsonException $exception) {
				throw new RuntimeException($source . ': invalid JSON: ' . $exception->getMessage());
			}
			if (! is_array($decoded)) {
				throw new RuntimeException($source . ': JSON root must be an object or array');
			}
			return $decoded;
		}

		$tokens = array();
		$lines = preg_split("/\r\n|\n|\r/", $raw);
		if (false === $lines) {
			throw new RuntimeException($source . ': unable to split input into lines');
		}
		$seenContent = false;
		foreach ($lines as $index => $line) {
			$line = rtrim($line, " \t");
			if ('' === trim($line)) {
				continue;
			}
			$left = ltrim($line, ' ');
			$indent = strlen($line) - strlen($left);
			if (str_starts_with($left, "\t")) {
				throw new RuntimeException(sprintf('%s:%d: tabs are not allowed for indentation', $source, $index + 1));
			}
			if (str_starts_with($left, '#')) {
				continue;
			}
			if ('---' === $left) {
				if ($seenContent) {
					throw new RuntimeException(sprintf('%s:%d: multi-document YAML is not supported', $source, $index + 1));
				}
				$seenContent = true;
				continue;
			}
			if ('...' === $left) {
				throw new RuntimeException(sprintf('%s:%d: YAML document end markers are not supported', $source, $index + 1));
			}
			$seenContent = true;
			$tokens[] = array(
				'indent' => $indent,
				'text'   => $left,
				'line'   => $index + 1,
			);
			if (count($tokens) > self::MAX_TOKENS) {
				throw new RuntimeException($source . ': input exceeds the token limit');
			}
		}
		if (array() === $tokens) {
			return array();
		}
		$i = 0;
		$value = self::parseBlock($tokens, $i, $tokens[0]['indent'], $source, 1);
		if ($i < count($tokens)) {
			throw new RuntimeException(sprintf('%s:%d: unexpected trailing content', $source, $tokens[ $i ]['line']));
		}
		if (! is_array($value)) {
			throw new RuntimeException($source . ': root must be a mapping or sequence');
		}
		return $value;
	}

	private static function parseBlock(array $tokens, int &$i, int $indent, string $source, int $depth): array {
		if ($depth > self::MAX_DEPTH) {
			throw new RuntimeException($source . ': nesting depth exceeds the limit');
		}
		$token = $tokens[ $i ];
		if ($token['indent'] !== $indent) {
			throw new RuntimeException(sprintf('%s:%d: unexpected indentation', $source, $token['line']));
		}
		if (self::isSequenceItem($token['text'])) {
			return self::parseSequence($tokens, $i, $indent, $source, $depth);
		}
		return self::parseMapping($tokens, $i, $indent, $source, $depth);
	}

	private static function isSequenceItem(string $text): bool {
		return '-' === $text || str_starts_with($text, '- ');
	}

	private static function parseMapping(array $tokens, int &$i, int $indent, string $source, int $depth): array {
		$map = array();
		while ($i < count($tokens)) {
			$token = $tokens[ $i ];
			if ($token['indent'] < $indent) {
				break;
			}
			if ($token['indent'] > $indent) {
				throw new RuntimeException(sprintf('%s:%d: unexpected indentation', $source, $token['line']));
			}
			if (self::isSequenceItem($token['text'])) {
				break;
			}
			[$key, $rest] = self::splitMappingEntry($token['text'], $source, $token['line'], false);
			if (array_key_exists($key, $map)) {
				throw new RuntimeException(sprintf('%s:%d: duplicate key "%s"', $source, $token['line'], $key));
			}
			$i++;
			if ('' === $rest) {
				if ($i < count($tokens) && $tokens[ $i ]['indent'] > $indent) {
					$map[ $key ] = self::parseBlock($tokens, $i, $tokens[ $i ]['indent'], $source, $depth + 1);
				} else {
					$map[ $key ] = null;
				}
				continue;
			}
			$map[ $key ] = self::scalar($rest, $source, $token['line']);
		}
		return $map;
	}

	private static function parseSequence(array $tokens, int &$i, int $indent, string $source, int $depth): array {
		$list = array();
		while ($i < count($tokens)) {
			$token = $tokens[ $i ];
			if ($token['indent'] < $indent) {
				break;
			}
			if ($token['indent'] > $indent) {
				throw new RuntimeException(sprintf('%s:%d: unexpected indentation', $source, $token['line']));
			}
			if (! self::isSequenceItem($token['text'])) {
				break;
			}
			$rest = trim(substr($token['text'], 1));
			$i++;
			if ('' === $rest) {
				if ($i < count($tokens) && $tokens[ $i ]['indent'] > $indent) {
					$list[] = self::parseBlock($tokens, $i, $tokens[ $i ]['indent'], $source, $depth + 1);
				} else {
					$list[] = null;
				}
				continue;
			}
			if (self::looksLikeMappingEntry($rest, $source, $token['line'])) {
				[$key, $valueText] = self::splitMappingEntry($rest, $source, $token['line'], true);
				$item = array();
				if ('' === $valueText) {
					if ($i < count($tokens) && $tokens[ $i ]['indent'] > $indent) {
						$item[ $key ] = self::parseBlock($tokens, $i, $tokens[ $i ]['indent'], $source, $depth + 1);
					} else {
						$item[ $key ] = null;
					}
				} else {
					$item[ $key ] = self::scalar($valueText, $source, $token['line']);
				}
				if ($i < count($tokens) && $tokens[ $i ]['indent'] > $indent) {
					$extra = self::parseMapping($tokens, $i, $tokens[ $i ]['indent'], $source, $depth + 1);
					foreach ($extra as $extraKey => $extraValue) {
						if (array_key_exists($extraKey, $item)) {
							throw new RuntimeException(sprintf('%s:%d: duplicate key "%s"', $source, $token['line'], $extraKey));
						}
						$item[ $extraKey ] = $extraValue;
					}
				}
				$list[] = $item;
				continue;
			}
			$list[] = self::scalar($rest, $source, $token['line']);
			if ($i < count($tokens) && $tokens[ $i ]['indent'] > $indent) {
				throw new RuntimeException(sprintf('%s:%d: unexpected indentation after a scalar sequence item', $source, $tokens[ $i ]['line']));
			}
		}
		return $list;
	}

	private static function looksLikeMappingEntry(string $rest, string $source, int $line): bool {
		try {
			[$key] = self::splitMappingEntry($rest, $source, $line, true);
		} catch (RuntimeException) {
			return false;
		}
		return 1 === preg_match('/^[A-Za-z0-9_.-]+$/', $key);
	}

	/**
	 * Split "key: value" while ignoring colons inside quotes.
	 *
	 * @return array{0:string,1:string}
	 */
	private static function splitMappingEntry(string $text, string $source, int $line, bool $requireSpaceAfterColon): array {
		$length = strlen($text);
		$quote = null;
		$colon = null;
		for ($position = 0; $position < $length; $position++) {
			$character = $text[ $position ];
			if (null !== $quote) {
				if ('\\' === $character && '"' === $quote) {
					$position++;
					continue;
				}
				if ($character === $quote) {
					if ("'" === $quote && $position + 1 < $length && "'" === $text[ $position + 1 ]) {
						$position++;
						continue;
					}
					$quote = null;
				}
				continue;
			}
			if ('"' === $character || "'" === $character) {
				$quote = $character;
				continue;
			}
			if (':' === $character) {
				if ($requireSpaceAfterColon && $position + 1 < $length && ' ' !== $text[ $position + 1 ]) {
					continue;
				}
				$colon = $position;
				break;
			}
		}
		if (null === $colon) {
			throw new RuntimeException(sprintf('%s:%d: expected "key: value"', $source, $line));
		}
		$keyText = trim(substr($text, 0, $colon));
		$valueText = trim(substr($text, $colon + 1));
		if ('' === $keyText) {
			throw new RuntimeException(sprintf('%s:%d: empty key', $source, $line));
		}
		$key = self::unquote($keyText);
		if ('' === $key) {
			throw new RuntimeException(sprintf('%s:%d: empty key', $source, $line));
		}
		return array($key, $valueText);
	}

	private static function scalar(string $value, string $source, int $line) {
		$value = trim($value);
		if ('' === $value) {
			return '';
		}
		if ('[]' === $value || '{}' === $value) {
			return array();
		}
		if (str_starts_with($value, '[') && str_ends_with($value, ']')) {
			$inner = trim(substr($value, 1, -1));
			if ('' === $inner) {
				return array();
			}
			$out = array();
			foreach (str_getcsv($inner, ',', '"', '\\') as $item) {
				$out[] = self::scalar(trim($item), $source, $line);
			}
			return $out;
		}
		if (str_starts_with($value, '{') && str_ends_with($value, '}')) {
			throw new RuntimeException(sprintf('%s:%d: inline flow mappings are not supported', $source, $line));
		}
		if (0 === strcasecmp($value, 'null') || '~' === $value) {
			return null;
		}
		if (0 === strcasecmp($value, 'true')) {
			return true;
		}
		if (0 === strcasecmp($value, 'false')) {
			return false;
		}
		if (preg_match('/^-?(?:0|[1-9][0-9]*)$/', $value)) {
			return (int) $value;
		}
		if (preg_match('/^-?(?:[0-9]+\.[0-9]*|\.[0-9]+)$/', $value)) {
			return (float) $value;
		}
		return self::unquote($value);
	}

	private static function unquote(string $value): string {
		$value = trim($value);
		$length = strlen($value);
		if ($length >= 2 && '"' === $value[0] && '"' === $value[ $length - 1 ]) {
			return stripcslashes(substr($value, 1, -1));
		}
		if ($length >= 2 && "'" === $value[0] && "'" === $value[ $length - 1 ]) {
			return str_replace("''", "'", substr($value, 1, -1));
		}
		return $value;
	}
}

/**
 * Execute a process with an argument array so no task-controlled value is ever
 * interpolated into a shell command line.
 */
final class ProcessRunner {
	private const MAX_OUTPUT_BYTES = 67108864;
	private const TIMEOUT_SECONDS = 120;

	/**
	 * @param list<string> $command
	 * @return array{exit:int,stdout:string,stderr:string}
	 */
	public static function run(array $command, ?string $cwd = null): array {
		if (array() === $command) {
			throw new ScopeGuardFailure('Refusing to execute an empty command.', SG_EXIT_INSPECTION, 'git_inspection_failed');
		}
		$environment = getenv();
		if (! is_array($environment)) {
			$environment = array();
		}
		$blocked = array(
			'GIT_DIR',
			'GIT_WORK_TREE',
			'GIT_INDEX_FILE',
			'GIT_OBJECT_DIRECTORY',
			'GIT_ALTERNATE_OBJECT_DIRECTORIES',
			'GIT_COMMON_DIR',
			'GIT_NAMESPACE',
			'GIT_PREFIX',
			'GIT_CEILING_DIRECTORIES',
			'GIT_DISCOVERY_ACROSS_FILESYSTEM',
			'GIT_CONFIG',
			'GIT_CONFIG_GLOBAL',
			'GIT_CONFIG_SYSTEM',
			'GIT_CONFIG_COUNT',
		);
		foreach (array_keys($environment) as $key) {
			if (in_array($key, $blocked, true) || 1 === preg_match('/^GIT_CONFIG_(?:KEY|VALUE)_[0-9]+$/', $key)) {
				unset($environment[ $key ]);
			}
		}
		$environment['GIT_TERMINAL_PROMPT'] = '0';
		$environment['GIT_OPTIONAL_LOCKS'] = '0';
		$environment['GIT_PAGER'] = 'cat';
		$environment['PAGER'] = 'cat';
		$environment['LC_ALL'] = 'C';
		$environment['LANG'] = 'C';
		if (! isset($environment['PATH'])) {
			$environment['PATH'] = '/usr/local/bin:/usr/bin:/bin';
		}

		$descriptors = array(
			0 => array('pipe', 'r'),
			1 => array('pipe', 'w'),
			2 => array('pipe', 'w'),
		);
		$pipes = array();
		$process = @proc_open($command, $descriptors, $pipes, $cwd, $environment);
		if (! is_resource($process)) {
			throw new ScopeGuardFailure('Unable to start process: ' . $command[0], SG_EXIT_INSPECTION, 'git_inspection_failed');
		}
		stream_set_blocking($pipes[1], false);
		stream_set_blocking($pipes[2], false);
		fclose($pipes[0]);

		$stdout = '';
		$stderr = '';
		$startedAt = microtime(true);
		while (true) {
			$read = array();
			if (! feof($pipes[1])) {
				$read[] = $pipes[1];
			}
			if (! feof($pipes[2])) {
				$read[] = $pipes[2];
			}
			if (array() === $read) {
				break;
			}
			$write = null;
			$except = null;
			$ready = @stream_select($read, $write, $except, 1);
			if (false === $ready) {
				@proc_terminate($process, 9);
				@fclose($pipes[1]);
				@fclose($pipes[2]);
				throw new ScopeGuardFailure('Failed while reading process output.', SG_EXIT_INSPECTION, 'git_inspection_failed');
			}
			foreach ($read as $stream) {
				$chunk = fread($stream, 65536);
				if (false === $chunk || '' === $chunk) {
					continue;
				}
				if ($stream === $pipes[1]) {
					$stdout .= $chunk;
				} else {
					$stderr .= $chunk;
				}
				if (strlen($stdout) + strlen($stderr) > self::MAX_OUTPUT_BYTES) {
					@proc_terminate($process, 9);
					@fclose($pipes[1]);
					@fclose($pipes[2]);
					throw new ScopeGuardFailure('Process output exceeded the 64 MiB safety limit.', SG_EXIT_INSPECTION, 'git_inspection_failed');
				}
			}
			if (microtime(true) - $startedAt > self::TIMEOUT_SECONDS) {
				@proc_terminate($process, 9);
				@fclose($pipes[1]);
				@fclose($pipes[2]);
				throw new ScopeGuardFailure('Process timed out during workspace inspection.', SG_EXIT_INSPECTION, 'git_inspection_failed');
			}
		}
		@fclose($pipes[1]);
		@fclose($pipes[2]);
		$exitCode = proc_close($process);
		if (-1 === $exitCode) {
			throw new ScopeGuardFailure('Process did not report a usable exit status.', SG_EXIT_INSPECTION, 'git_inspection_failed');
		}
		return array(
			'exit'   => $exitCode,
			'stdout' => $stdout,
			'stderr' => $stderr,
		);
	}
}

/**
 * Read-only Git workspace inspection.
 */
final class GitInspector {
	private array $baseCommand;
	private string $root = '';
	private string $inputPath;

	public function __construct(string $path) {
		if ('' === $path || ! is_dir($path)) {
			throw new ScopeGuardFailure('Repository path is not an existing directory: ' . $path, SG_EXIT_INSPECTION, 'git_inspection_failed');
		}
		$resolved = realpath($path);
		if (false === $resolved) {
			throw new ScopeGuardFailure('Repository path cannot be resolved: ' . $path, SG_EXIT_INSPECTION, 'git_inspection_failed');
		}
		$this->inputPath = $resolved;
		$this->baseCommand = array(
			'git',
			'--no-optional-locks',
			'--literal-pathspecs',
			'-c',
			'core.quotePath=false',
			'-c',
			'core.fsmonitor=false',
			'-C',
			$resolved,
		);
	}

	public function open(): string {
		$inside = $this->tryGit(array('rev-parse', '--is-inside-work-tree'));
		if (0 !== $inside['exit'] || 'true' !== trim($inside['stdout'])) {
			throw new ScopeGuardFailure('Path is not a Git worktree: ' . $this->inputPath, SG_EXIT_INSPECTION, 'git_inspection_failed');
		}
		$toplevel = $this->tryGit(array('rev-parse', '--show-toplevel'));
		if (0 !== $toplevel['exit']) {
			throw new ScopeGuardFailure('Unable to resolve the Git worktree root: ' . self::short($toplevel['stderr']), SG_EXIT_INSPECTION, 'git_inspection_failed');
		}
		$root = realpath(trim($toplevel['stdout']));
		if (false === $root || ! is_dir($root)) {
			throw new ScopeGuardFailure('Git reported an unusable worktree root.', SG_EXIT_INSPECTION, 'git_inspection_failed');
		}
		if ($this->inputPath !== $root && ! str_starts_with($this->inputPath, $root . '/')) {
			throw new ScopeGuardFailure('Repository path is outside the reported worktree root.', SG_EXIT_INSPECTION, 'git_inspection_failed');
		}
		$this->root = $root;
		// Run every subsequent command from the worktree root so untracked-file
		// listings and diffs cover the whole repository even when --repo points
		// at a subdirectory.
		$last = count($this->baseCommand) - 1;
		$this->baseCommand[ $last ] = $root;
		return $root;
	}

	/**
	 * @return string|null "commit", another object type, or null when missing.
	 */
	public function commitType(string $base): ?string {
		$result = $this->tryGit(array('cat-file', '-t', $base));
		if (0 !== $result['exit']) {
			$message = trim($result['stderr']);
			if (str_contains($message, 'Not a valid object name')
				|| str_contains($message, 'bad object')
				|| str_contains($message, 'could not get object info')
				|| str_contains($message, 'invalid object name')
			) {
				return null;
			}
			throw new ScopeGuardFailure('Unable to inspect the base commit: ' . self::short($message), SG_EXIT_INSPECTION, 'git_inspection_failed');
		}
		return trim($result['stdout']);
	}

	/**
	 * Collect tracked, staged, unstaged, renamed/copied, and untracked changes
	 * relative to the base commit. A second pass verifies the snapshot did not
	 * change while it was being read.
	 *
	 * @return array{changed:list<array<string,mixed>>,tracked:int,staged:int,untracked:int}
	 */
	public function snapshot(string $base): array {
		$trackedRaw = $this->diffEntries($base, false);
		$stagedRaw = $this->diffEntries($base, true);
		$unstagedRaw = $this->diffEntries(null, false);
		$untracked = $this->untrackedPaths();

		$tracked = self::expand($trackedRaw);
		$staged = self::expand($stagedRaw);
		$unstaged = self::expand($unstagedRaw);

		$changed = $tracked;
		foreach (array($staged, $unstaged) as $extraSet) {
			foreach ($extraSet as $key => $entry) {
				if (! isset($changed[ $key ])) {
					$changed[ $key ] = $entry;
				}
			}
		}
		foreach ($untracked as $path) {
			$key = 'p:' . $path;
			if (! isset($changed[ $key ])) {
				$changed[ $key ] = array(
					'path'   => $path,
					'status' => '??',
				);
			}
		}

		$trackedAgain = self::expand($this->diffEntries($base, false));
		$untrackedAgain = $this->untrackedPaths();
		if (! self::sameEntrySet($tracked, $trackedAgain) || $untracked !== $untrackedAgain) {
			throw new ScopeGuardFailure(
				'The worktree changed while it was being inspected; the snapshot is incomplete. Stop writes and re-run the guard.',
				SG_EXIT_INSPECTION,
				'git_inspection_failed',
			);
		}

		$list = array_values($changed);
		usort($list, static fn (array $a, array $b): int => strcmp($a['path'], $b['path']));

		return array(
			'changed'   => $list,
			'tracked'   => count($tracked),
			'staged'    => count($staged),
			'untracked' => count($untracked),
		);
	}

	/**
	 * @return list<array{status:string,path:string,from:?string}>
	 */
	private function diffEntries(?string $base, bool $cached): array {
		$args = array(
			'diff',
			'--name-status',
			'-z',
			'--find-renames',
			'--find-copies',
			'--find-copies-harder',
			'--no-ext-diff',
			'--no-textconv',
			'--no-relative',
			'--ignore-submodules=none',
		);
		if ($cached) {
			$args[] = '--cached';
		}
		if (null !== $base) {
			$args[] = $base;
		}
		$args[] = '--';
		$result = $this->tryGit($args);
		if (0 !== $result['exit']) {
			throw new ScopeGuardFailure('Git diff failed: ' . self::short($result['stderr']), SG_EXIT_INSPECTION, 'git_inspection_failed');
		}
		return self::parseNameStatus($result['stdout']);
	}

	/**
	 * @return list<string>
	 */
	private function untrackedPaths(): array {
		$result = $this->tryGit(array('ls-files', '--others', '--exclude-standard', '-z', '--'));
		if (0 !== $result['exit']) {
			throw new ScopeGuardFailure('Git untracked-file listing failed: ' . self::short($result['stderr']), SG_EXIT_INSPECTION, 'git_inspection_failed');
		}
		$tokens = explode("\0", $result['stdout']);
		if (array() !== $tokens && '' === end($tokens)) {
			array_pop($tokens);
		}
		$paths = array();
		foreach ($tokens as $path) {
			if ('' === $path) {
				continue;
			}
			$paths[ 'p:' . $path ] = $path;
		}
		ksort($paths, SORT_STRING);
		return array_values($paths);
	}

	/**
	 * @return list<array{status:string,path:string,from:?string}>
	 */
	private static function parseNameStatus(string $output): array {
		$tokens = explode("\0", $output);
		if (array() !== $tokens && '' === end($tokens)) {
			array_pop($tokens);
		}
		$entries = array();
		$count = count($tokens);
		$i = 0;
		while ($i < $count) {
			$statusToken = $tokens[ $i ];
			$i++;
			if ('' === $statusToken || 1 !== preg_match('/^[A-Z][0-9]*$/', $statusToken)) {
				throw new ScopeGuardFailure('Git returned an unrecognized name-status record; inspection is incomplete.', SG_EXIT_INSPECTION, 'git_inspection_failed');
			}
			$letter = $statusToken[0];
			if ('R' === $letter || 'C' === $letter) {
				if ($i + 1 >= $count) {
					throw new ScopeGuardFailure('Git rename/copy record is truncated; inspection is incomplete.', SG_EXIT_INSPECTION, 'git_inspection_failed');
				}
				$from = $tokens[ $i ];
				$to = $tokens[ $i + 1 ];
				$i += 2;
				if ('' === $from || '' === $to) {
					throw new ScopeGuardFailure('Git rename/copy record contains an empty path; inspection is incomplete.', SG_EXIT_INSPECTION, 'git_inspection_failed');
				}
				$entries[] = array(
					'status' => $letter,
					'path'   => $to,
					'from'   => $from,
				);
				continue;
			}
			if ($i >= $count) {
				throw new ScopeGuardFailure('Git name-status record is truncated; inspection is incomplete.', SG_EXIT_INSPECTION, 'git_inspection_failed');
			}
			$path = $tokens[ $i ];
			$i++;
			if ('' === $path) {
				throw new ScopeGuardFailure('Git name-status record contains an empty path; inspection is incomplete.', SG_EXIT_INSPECTION, 'git_inspection_failed');
			}
			$entries[] = array(
				'status' => $letter,
				'path'   => $path,
				'from'   => null,
			);
		}
		return $entries;
	}

	/**
	 * Expand rename records into both endpoints. Copy sources are unchanged and
	 * are not reported as changed paths.
	 *
	 * @param list<array{status:string,path:string,from:?string}> $entries
	 * @return array<string,array<string,mixed>>
	 */
	private static function expand(array $entries): array {
		$map = array();
		foreach ($entries as $entry) {
			$status = $entry['status'];
			if ('R' === $status) {
				self::addEntry($map, $entry['from'], 'R', array('renamed_to' => $entry['path']));
				self::addEntry($map, $entry['path'], 'R', array('renamed_from' => $entry['from']));
				continue;
			}
			if ('C' === $status) {
				self::addEntry($map, $entry['path'], 'C', array('copied_from' => $entry['from']));
				continue;
			}
			self::addEntry($map, $entry['path'], $status, array());
		}
		ksort($map, SORT_STRING);
		return $map;
	}

	/**
	 * @param array<string,array<string,mixed>> $map
	 * @param array<string,string> $extra
	 */
	private static function addEntry(array &$map, string $path, string $status, array $extra): void {
		$key = 'p:' . $path;
		if (isset($map[ $key ])) {
			return;
		}
		$map[ $key ] = array_merge(
			array(
				'path'   => $path,
				'status' => $status,
			),
			$extra,
		);
	}

	/**
	 * @param array<string,array<string,mixed>> $a
	 * @param array<string,array<string,mixed>> $b
	 */
	private static function sameEntrySet(array $a, array $b): bool {
		if (array_keys($a) !== array_keys($b)) {
			return false;
		}
		foreach ($a as $key => $entry) {
			if ($entry !== $b[ $key ]) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @return array{exit:int,stdout:string,stderr:string}
	 */
	private function tryGit(array $args): array {
		return ProcessRunner::run(array_merge($this->baseCommand, $args));
	}

	private static function short(string $value): string {
		$value = trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value) ?? '');
		if (strlen($value) > 500) {
			return substr($value, 0, 497) . '...';
		}
		return $value;
	}
}

final class CliOptions {
	public ?string $task = null;
	public ?string $base = null;
	public ?string $repo = null;
	public ?string $registry = null;
	public bool $json = false;
	public bool $help = false;
	/** @var list<string> */
	public array $errors = array();
}

function sg_parse_arguments(array $argv): CliOptions {
	$options = new CliOptions();
	$count = count($argv);
	for ($i = 1; $i < $count; $i++) {
		$argument = $argv[ $i ];
		if ('--help' === $argument || '-h' === $argument) {
			$options->help = true;
			continue;
		}
		if ('--json' === $argument) {
			$options->json = true;
			continue;
		}
		$handled = false;
		foreach (array('task', 'base', 'repo', 'registry') as $name) {
			$prefix = '--' . $name . '=';
			if (str_starts_with($argument, $prefix)) {
				$value = substr($argument, strlen($prefix));
				$options->{$name} = $value;
				sg_validate_option_value($name, $value, $options);
				$handled = true;
				break;
			}
			if ('--' . $name === $argument) {
				if ($i + 1 >= $count || str_starts_with($argv[ $i + 1 ], '--')) {
					$options->errors[] = 'Option --' . $name . ' requires a value.';
				} else {
					$value = $argv[ ++$i ];
					$options->{$name} = $value;
					sg_validate_option_value($name, $value, $options);
				}
				$handled = true;
				break;
			}
		}
		if ($handled) {
			continue;
		}
		if (str_starts_with($argument, '--')) {
			$options->errors[] = 'Unknown option: ' . $argument;
		} else {
			$options->errors[] = 'Unexpected argument: ' . $argument;
		}
	}
	return $options;
}

function sg_validate_option_value(string $name, string $value, CliOptions $options): void {
	if ('' === $value) {
		$options->errors[] = 'Option --' . $name . ' requires a non-empty value.';
		return;
	}
	if (strlen($value) > 4096) {
		$options->errors[] = 'Option --' . $name . ' exceeds the 4096-byte limit.';
		return;
	}
	if (1 === preg_match('/[\x00-\x1F\x7F]/', $value)) {
		$options->errors[] = 'Option --' . $name . ' contains control characters.';
	}
}

final class ScopeGuard {
	private CliOptions $options;
	private string $taskId = '';
	private string $baseSha = '';
	private string $repository = '';
	private bool $baseMatches = false;
	/** @var list<array<string,mixed>> */
	private array $violations = array();
	/** @var array<string,true> */
	private array $violationKeys = array();
	/** @var list<array<string,mixed>> */
	private array $changedPaths = array();
	/** @var array{tracked:int,staged:int,untracked:int,complete:bool} */
	private array $inspection = array(
		'tracked'   => 0,
		'staged'    => 0,
		'untracked' => 0,
		'complete'  => false,
	);
	private bool $configError = false;
	private bool $inspectionError = false;
	/** @var list<string> */
	private array $taskAllowed = array();
	/** @var list<string> */
	private array $taskForbidden = array();
	/** @var list<array<string,mixed>> */
	private array $ownership = array();

	public function __construct(CliOptions $options) {
		$this->options = $options;
	}

	public function run(): int {
		if (is_string($this->options->base)) {
			$this->baseSha = strlen($this->options->base) > 128 ? substr($this->options->base, 0, 128) : $this->options->base;
		}
		if (is_string($this->options->repo) && '' !== $this->options->repo) {
			$this->repository = $this->options->repo;
		}

		foreach ($this->options->errors as $error) {
			$this->configError = true;
			$this->addViolation('invalid_arguments', null, null, null, $error);
		}

		$baseOption = $this->options->base;
		$baseValid = is_string($baseOption) && 1 === preg_match(SG_SHA_PATTERN, $baseOption);
		if (! $baseValid) {
			$this->configError = true;
			$this->addViolation('base_commit_mismatch', null, null, null, 'Option --base must be a 40-character lowercase hexadecimal commit SHA matching workspace.base_commit.');
		}

		if (is_string($this->options->task) && '' !== $this->options->task) {
			$this->loadTask($this->options->task);
		} else {
			$this->configError = true;
			$this->addViolation('invalid_task', null, null, null, 'Option --task is required and must point to a task contract.');
		}

		if (is_string($this->options->registry) && '' !== $this->options->registry) {
			$this->loadRegistry($this->options->registry);
		} else {
			$this->configError = true;
			$this->addViolation('invalid_registry', null, null, null, 'Option --registry is required and must point to the ownership registry.');
		}

		if ($this->configError) {
			return SG_EXIT_CONFIG;
		}
		if (! $this->baseMatches || ! $baseValid) {
			$this->configError = true;
			$this->addViolation('base_commit_mismatch', null, null, null, 'Task workspace.base_commit must match --base.');
			return SG_EXIT_CONFIG;
		}

		$repoOption = $this->options->repo;
		if (! is_string($repoOption) || '' === $repoOption) {
			$this->configError = true;
			$this->addViolation('invalid_arguments', null, null, null, 'Option --repo is required and must point to the Git worktree.');
			return SG_EXIT_CONFIG;
		}

		try {
			$inspector = new GitInspector($repoOption);
			$root = $inspector->open();
			$this->repository = $root;
			$type = $inspector->commitType($baseOption);
			if (null === $type) {
				$this->configError = true;
				$this->addViolation('base_commit_mismatch', null, null, null, 'The base commit does not exist in the repository object database.');
				return SG_EXIT_CONFIG;
			}
			if ('commit' !== $type) {
				$this->configError = true;
				$this->addViolation('base_commit_mismatch', null, null, null, 'The base object is not a commit (found: ' . self::oneLine($type) . ').');
				return SG_EXIT_CONFIG;
			}
			$snapshot = $inspector->snapshot($baseOption);
		} catch (ScopeGuardFailure $failure) {
			$this->inspectionError = true;
			$this->addViolation($failure->failureViolation(), null, null, null, $failure->getMessage());
			return $failure->failureExitCode();
		}

		$this->changedPaths = $snapshot['changed'];
		$this->inspection = array(
			'tracked'   => $snapshot['tracked'],
			'staged'    => $snapshot['staged'],
			'untracked' => $snapshot['untracked'],
			'complete'  => true,
		);
		$this->enforce();
		return $this->currentExitCode();
	}

	private function loadTask(string $path): void {
		try {
			$task = MiniYaml::parseFile($path);
		} catch (RuntimeException $exception) {
			$this->configError = true;
			$this->addViolation('invalid_task', $path, null, null, 'Cannot load task contract: ' . $exception->getMessage());
			return;
		}

		[$errors, $allowed, $forbidden] = $this->validateTask($task);
		if (array() !== $errors) {
			$this->configError = true;
			foreach (array_slice($errors, 0, 25) as $error) {
				$this->addViolation($error['violation'], $path, null, null, $error['message']);
			}
			if (count($errors) > 25) {
				$this->addViolation('invalid_task', $path, null, null, 'Additional task-contract errors were omitted.');
			}
		}
		$this->taskAllowed = $allowed;
		$this->taskForbidden = $forbidden;

		$workspace = $task['workspace'] ?? null;
		$contractBase = is_array($workspace) ? ($workspace['base_commit'] ?? null) : null;
		$cliBase = $this->options->base;
		if (is_string($contractBase) && is_string($cliBase)
			&& 1 === preg_match(SG_SHA_PATTERN, $contractBase)
			&& 1 === preg_match(SG_SHA_PATTERN, $cliBase)
		) {
			$this->baseMatches = $contractBase === $cliBase;
			if (! $this->baseMatches) {
				$this->configError = true;
				$this->addViolation(
					'base_commit_mismatch',
					$path,
					null,
					null,
					'Task workspace.base_commit (' . $contractBase . ') does not match --base (' . $cliBase . '). Use the approved base commit.',
				);
			}
		}
	}

	/**
	 * Task-contract errors are classified so scope problems are reported as
	 * invalid_scope (exit 2) and base problems as base_commit_mismatch, rather
	 * than as generic task errors.
	 *
	 * @return array{0:list<array{violation:string,message:string}>,1:list<string>,2:list<string>}
	 */
	private function validateTask(array $task): array {
		$errors = array();
		$allowed = array();
		$forbidden = array();

		if (array() === $task || ! self::isMapping($task)) {
			return array(
				array(self::validationError('invalid_task', 'task root must be a mapping.')),
				array(),
				array(),
			);
		}

		$id = $task['id'] ?? null;
		if (! is_string($id) || 1 !== preg_match(SG_TASK_ID_PATTERN, $id)) {
			$errors[] = self::validationError('invalid_task', 'id must match ^[A-Z][A-Z0-9-]{2,63}$.');
		} else {
			$this->taskId = $id;
		}

		$workspace = $task['workspace'] ?? null;
		if (! is_array($workspace) || ! self::isMapping($workspace)) {
			$errors[] = self::validationError('invalid_task', 'workspace must be a mapping.');
		} else {
			$contractBase = $workspace['base_commit'] ?? null;
			if (! is_string($contractBase) || 1 !== preg_match(SG_SHA_PATTERN, $contractBase)) {
				$errors[] = self::validationError('base_commit_mismatch', 'workspace.base_commit must be a 40-character lowercase hexadecimal SHA.');
			}
			foreach (array('branch', 'path') as $key) {
				if (array_key_exists($key, $workspace) && (! is_string($workspace[ $key ]) || '' === trim($workspace[ $key ]))) {
					$errors[] = self::validationError('invalid_task', 'workspace.' . $key . ' must be a non-empty string when present.');
				}
			}
		}

		$scope = $task['scope'] ?? null;
		if (! is_array($scope) || ! self::isMapping($scope)) {
			$errors[] = self::validationError('invalid_scope', 'scope must be a mapping.');
		} else {
			$allowedRaw = $scope['allowed'] ?? null;
			if (! is_array($allowedRaw) || array() === $allowedRaw || ! array_is_list($allowedRaw)) {
				$errors[] = self::validationError('invalid_scope', 'scope.allowed must be a non-empty list.');
			} else {
				$seen = array();
				foreach ($allowedRaw as $index => $raw) {
					if (! is_string($raw)) {
						$errors[] = self::validationError('invalid_scope', 'scope.allowed[' . $index . '] must be a string.');
						continue;
					}
					[$normalized, $pathError] = $this->normalizeScopePath($raw);
					if (null !== $pathError) {
						$errors[] = self::validationError('invalid_scope', 'scope.allowed[' . $index . '] is unsafe: ' . $pathError);
						continue;
					}
					$key = 'p:' . $normalized;
					if (isset($seen[ $key ])) {
						$errors[] = self::validationError('invalid_scope', 'scope.allowed contains a duplicate path: ' . $normalized);
						continue;
					}
					$seen[ $key ] = true;
					if ($this->isGloballyForbidden($normalized)) {
						$errors[] = self::validationError('invalid_scope', 'scope.allowed targets a globally forbidden write path: ' . $normalized);
						continue;
					}
					$allowed[] = $normalized;
				}
			}

			$forbiddenRaw = $scope['forbidden'] ?? null;
			if (null !== $forbiddenRaw) {
				if (! is_array($forbiddenRaw) || ! array_is_list($forbiddenRaw)) {
					$errors[] = self::validationError('invalid_scope', 'scope.forbidden must be a list when present.');
				} else {
					$seenForbidden = array();
					foreach ($forbiddenRaw as $index => $raw) {
						if (! is_string($raw)) {
							$errors[] = self::validationError('invalid_scope', 'scope.forbidden[' . $index . '] must be a string.');
							continue;
						}
						[$normalized, $pathError] = $this->normalizeScopePath($raw);
						if (null !== $pathError) {
							$errors[] = self::validationError('invalid_scope', 'scope.forbidden[' . $index . '] is unsafe: ' . $pathError);
							continue;
						}
						$key = 'p:' . $normalized;
						if (isset($seenForbidden[ $key ])) {
							$errors[] = self::validationError('invalid_scope', 'scope.forbidden contains a duplicate path: ' . $normalized);
							continue;
						}
						$seenForbidden[ $key ] = true;
						$forbidden[] = $normalized;
					}
				}
			}

			foreach ($allowed as $allowedPath) {
				foreach ($forbidden as $forbiddenPath) {
					if ($this->pathCovers($allowedPath, $forbiddenPath) || $this->pathCovers($forbiddenPath, $allowedPath)) {
						$errors[] = self::validationError('invalid_scope', 'scope.allowed and scope.forbidden overlap: ' . $allowedPath . ' / ' . $forbiddenPath);
					}
				}
			}
		}

		return array($errors, $allowed, $forbidden);
	}

	/**
	 * @return array{violation:string,message:string}
	 */
	private static function validationError(string $violation, string $message): array {
		return array(
			'violation' => $violation,
			'message'   => $message,
		);
	}

	private function loadRegistry(string $path): void {
		try {
			$registry = MiniYaml::parseFile($path);
		} catch (RuntimeException $exception) {
			$this->configError = true;
			$this->addViolation('invalid_registry', $path, null, null, 'Cannot load ownership registry: ' . $exception->getMessage());
			return;
		}

		[$entries, $errors] = $this->validateRegistry($registry);
		if (array() !== $errors) {
			$this->configError = true;
			foreach (array_slice($errors, 0, 25) as $error) {
				$this->addViolation('invalid_registry', $path, null, null, $error);
			}
			if (count($errors) > 25) {
				$this->addViolation('invalid_registry', $path, null, null, 'Additional registry errors were omitted.');
			}
			return;
		}

		$this->ownership = $entries;
		$this->detectOwnershipOverlaps();
	}

	/**
	 * @return array{0:list<array<string,mixed>>,1:list<string>}
	 */
	private function validateRegistry(array $registry): array {
		$errors = array();
		$entries = array();

		if (array() === $registry || ! self::isMapping($registry)) {
			return array(array(), array('registry root must be a mapping.'));
		}

		$version = $registry['version'] ?? null;
		if (! is_int($version) || 1 !== $version) {
			$errors[] = 'version must be the integer 1.';
		}
		$updatedAt = $registry['updated_at'] ?? null;
		if (! is_string($updatedAt) || '' === trim($updatedAt)) {
			$errors[] = 'updated_at must be a non-empty string.';
		}

		$rawEntries = $registry['entries'] ?? null;
		if (! is_array($rawEntries) || ! array_is_list($rawEntries)) {
			return array(array(), array_merge($errors, array('entries must be a list.')));
		}

		$required = array(
			'path',
			'task_id',
			'owner_agent',
			'mode',
			'workspace',
			'base_commit',
			'status',
			'lease_started_at',
			'lease_expires_at',
			'last_heartbeat_at',
			'dependencies',
			'integration_status',
		);
		$activeKeys = array();

		foreach ($rawEntries as $index => $entry) {
			$label = 'entries[' . $index . ']';
			if (! is_array($entry) || ! self::isMapping($entry)) {
				$errors[] = $label . ' must be a mapping.';
				continue;
			}
			foreach ($required as $key) {
				if (! array_key_exists($key, $entry)) {
					$errors[] = $label . ' is missing ' . $key . '.';
				}
			}

			$pathRaw = $entry['path'] ?? null;
			$path = null;
			if (! is_string($pathRaw) || '' === trim($pathRaw)) {
				$errors[] = $label . '.path must be a non-empty string.';
			} else {
				[$path, $pathError] = $this->normalizeScopePath($pathRaw);
				if (null !== $pathError) {
					$errors[] = $label . '.path is unsafe: ' . $pathError;
					$path = null;
				} elseif ($this->isGloballyForbidden($path)) {
					$errors[] = $label . '.path targets a globally forbidden write path: ' . $path;
				} elseif (isset($entry['mode']) && 'exclusive' === $entry['mode'] && str_ends_with($pathRaw, '/')) {
					$errors[] = $label . '.path uses a directory trailing slash with exclusive mode.';
				}
			}

			$taskId = $entry['task_id'] ?? null;
			if (! is_string($taskId) || 1 !== preg_match(SG_TASK_ID_PATTERN, $taskId)) {
				$errors[] = $label . '.task_id is invalid.';
				$taskId = null;
			}

			$ownerAgent = $entry['owner_agent'] ?? null;
			if (! is_string($ownerAgent) || '' === trim($ownerAgent) || 1 === preg_match('/[\x00-\x1F\x7F]/', $ownerAgent)) {
				$errors[] = $label . '.owner_agent must be a non-empty string without control characters.';
			}

			$mode = $entry['mode'] ?? null;
			if (! in_array($mode, array('exclusive', 'scoped'), true)) {
				$errors[] = $label . '.mode must be "exclusive" or "scoped".';
			}

			$workspace = $entry['workspace'] ?? null;
			if (! is_string($workspace) || '' === trim($workspace) || 1 === preg_match('/[\x00-\x1F\x7F]/', $workspace)) {
				$errors[] = $label . '.workspace must be a non-empty string without control characters.';
			}

			$baseCommit = $entry['base_commit'] ?? null;
			if (! is_string($baseCommit) || 1 !== preg_match(SG_SHA_PATTERN, $baseCommit)) {
				$errors[] = $label . '.base_commit must be a 40-character lowercase hexadecimal SHA.';
			}

			$status = $entry['status'] ?? null;
			if (! in_array($status, array('active', 'released', 'expired', 'revoked'), true)) {
				$errors[] = $label . '.status is invalid.';
			}

			$integrationStatus = $entry['integration_status'] ?? null;
			if (! in_array($integrationStatus, array('pending', 'integrated', 'rejected', 'not_applicable'), true)) {
				$errors[] = $label . '.integration_status is invalid.';
			}

			foreach (array('lease_started_at', 'lease_expires_at', 'last_heartbeat_at') as $key) {
				$timestamp = $entry[ $key ] ?? null;
				if (! is_string($timestamp) || '' === trim($timestamp) || false === @strtotime($timestamp)) {
					$errors[] = $label . '.' . $key . ' must be a parseable timestamp.';
				}
			}

			$dependencies = $entry['dependencies'] ?? null;
			if (! is_array($dependencies) || ! array_is_list($dependencies)) {
				$errors[] = $label . '.dependencies must be a list.';
			} else {
				$seenDependencies = array();
				foreach ($dependencies as $dependency) {
					if (! is_string($dependency) || 1 !== preg_match(SG_TASK_ID_PATTERN, $dependency)) {
						$errors[] = $label . '.dependencies contains an invalid task id.';
						continue;
					}
					if (isset($seenDependencies[ $dependency ])) {
						$errors[] = $label . '.dependencies contains a duplicate task id: ' . $dependency;
					}
					$seenDependencies[ $dependency ] = true;
				}
			}

			$expiresAt = @strtotime((string) ($entry['lease_expires_at'] ?? ''));
			if (null !== $path && null !== $taskId && in_array($mode, array('exclusive', 'scoped'), true)) {
				if ('active' === $status) {
					$activeKey = $taskId . '|' . $path . '|' . $mode;
					if (isset($activeKeys[ $activeKey ])) {
						$errors[] = $label . ' duplicates an active ownership entry for ' . $path . '.';
					}
					$activeKeys[ $activeKey ] = true;
				}
				if (in_array($status, array('active', 'released', 'expired', 'revoked'), true)) {
					$entries[] = array(
						'path'        => $path,
						'task_id'     => $taskId,
						'owner_agent' => is_string($ownerAgent) ? $ownerAgent : '',
						'mode'        => $mode,
						'status'      => $status,
						'expires'     => $expiresAt,
					);
				}
			}
		}

		return array($entries, $errors);
	}

	private function detectOwnershipOverlaps(): void {
		$active = array_values(array_filter(
			$this->ownership,
			static fn (array $entry): bool => 'active' === $entry['status'] && (false === $entry['expires'] || $entry['expires'] >= time()),
		));
		$count = count($active);
		for ($i = 0; $i < $count; $i++) {
			for ($j = $i + 1; $j < $count; $j++) {
				$a = $active[ $i ];
				$b = $active[ $j ];
				if (! $this->ownershipPairOverlaps($a, $b)) {
					continue;
				}
				$this->addViolation(
					'ownership_overlap',
					$a['path'],
					$a['task_id'],
					$b['task_id'],
					'Active ownership leases overlap ("' . $a['path'] . '" / "' . $b['path'] . '"). Serialize the tasks or split the scope into non-overlapping paths.',
				);
			}
		}
	}

	private function enforce(): void {
		$active = array_values(array_filter(
			$this->ownership,
			static fn (array $entry): bool => 'active' === $entry['status'] && (false === $entry['expires'] || $entry['expires'] >= time()),
		));

		foreach ($this->changedPaths as $entry) {
			$rawPath = (string) $entry['path'];
			[$normalized, $pathError] = $this->normalizeRepositoryPath($rawPath);
			if (null !== $pathError) {
				$this->addViolation(
					'unsafe_path',
					$rawPath,
					$this->taskId,
					null,
					'Changed path is not a safe repository-relative path (' . $pathError . '). Remove it and re-run the guard.',
				);
				continue;
			}

			$escape = $this->findSymlinkEscape($normalized);
			if (null !== $escape) {
				$this->addViolation(
					'symlink_escape',
					$normalized,
					$this->taskId,
					null,
					'Symlink "' . $escape . '" resolves outside the repository. Remove the link and keep changes inside the worktree.',
				);
			}

			$covering = array();
			foreach ($active as $ownershipEntry) {
				if ($this->entryCovers($ownershipEntry, $normalized)) {
					$covering[] = $ownershipEntry;
				}
			}
			$taskCover = array_values(array_filter(
				$covering,
				fn (array $ownershipEntry): bool => $ownershipEntry['task_id'] === $this->taskId,
			));
			$otherCover = array_values(array_filter(
				$covering,
				fn (array $ownershipEntry): bool => $ownershipEntry['task_id'] !== $this->taskId,
			));
			$actualOwner = array() !== $taskCover
				? $this->taskId
				: (array() !== $otherCover ? $otherCover[0]['task_id'] : null);

			if ($this->isGloballyForbidden($normalized) || $this->matchesAny($this->taskForbidden, $normalized)) {
				$this->addViolation(
					'forbidden_path',
					$normalized,
					$this->taskId,
					$actualOwner,
					'Path is forbidden by global policy or the task contract. Revert the change and write only inside the allowed scope.',
				);
			} elseif (! $this->matchesAny($this->taskAllowed, $normalized)) {
				$this->addViolation(
					'out_of_scope',
					$normalized,
					$this->taskId,
					$actualOwner,
					'Path is outside the task scope.allowed entries. Revert it or obtain an approved scope-change contract before integrating.',
				);
			}

			if (array() === $covering) {
				$this->addViolation(
					'unowned_path',
					$normalized,
					$this->taskId,
					null,
					'No active ownership lease covers this path. Ask the Orchestrator to grant ownership before changing it.',
				);
			} elseif (array() !== $otherCover) {
				$this->addViolation(
					'other_task_owner',
					$normalized,
					$this->taskId,
					$otherCover[0]['task_id'],
					'Another active task owns this path. Revert it or coordinate a serialized reassignment with the Orchestrator.',
				);
			}
		}
	}

	private function currentExitCode(): int {
		if ($this->configError) {
			return SG_EXIT_CONFIG;
		}
		if ($this->inspectionError) {
			return SG_EXIT_INSPECTION;
		}
		if (array() !== $this->violations) {
			return SG_EXIT_SCOPE;
		}
		if (! $this->inspection['complete']) {
			return SG_EXIT_INSPECTION;
		}
		return SG_EXIT_OK;
	}

	private function addViolation(string $type, ?string $path, ?string $expectedOwner, ?string $actualOwner, string $remediation): void {
		$remediation = self::oneLine($remediation);
		$key = $type . "\0" . ($path ?? '') . "\0" . ($expectedOwner ?? '') . "\0" . ($actualOwner ?? '') . "\0" . $remediation;
		if (isset($this->violationKeys[ $key ])) {
			return;
		}
		$this->violationKeys[ $key ] = true;
		$this->violations[] = array(
			'task_id'        => $this->taskId,
			'path'           => $path,
			'violation'      => $type,
			'expected_owner' => $expectedOwner,
			'actual_owner'   => $actualOwner,
			'base_sha'       => $this->baseSha,
			'remediation'    => $remediation,
		);
	}

	/**
	 * @return array{0:?string,1:?string}
	 */
	private function normalizeScopePath(string $raw): array {
		return $this->normalizePath($raw, true);
	}

	/**
	 * @return array{0:?string,1:?string}
	 */
	private function normalizeRepositoryPath(string $raw): array {
		return $this->normalizePath($raw, false);
	}

	/**
	 * @return array{0:?string,1:?string}
	 */
	private function normalizePath(string $raw, bool $allowTrailingSlash): array {
		if ('' === $raw) {
			return array(null, 'path is empty');
		}
		if ($raw !== trim($raw)) {
			return array(null, 'path has leading or trailing whitespace');
		}
		if (1 === preg_match('/[\x00-\x1F\x7F]/', $raw)) {
			return array(null, 'path contains control characters');
		}
		if (str_contains($raw, '\\')) {
			return array(null, 'path contains a backslash');
		}
		if (str_starts_with($raw, '/')) {
			return array(null, 'absolute paths are not allowed');
		}
		if (1 === preg_match('#^[A-Za-z]:#', $raw)) {
			return array(null, 'drive-letter paths are not allowed');
		}
		if (str_contains($raw, '://')) {
			return array(null, 'URL-like paths are not allowed');
		}
		$normalized = rtrim($raw, '/');
		if ('' === $normalized) {
			return array(null, 'path is empty after normalization');
		}
		if (! $allowTrailingSlash && $normalized !== $raw) {
			return array(null, 'trailing slash is not allowed for a changed path');
		}
		foreach (explode('/', $normalized) as $component) {
			if ('' === $component || '.' === $component || '..' === $component) {
				return array(null, 'path contains an empty, "." or ".." component');
			}
		}
		return array($normalized, null);
	}

	private function isGloballyForbidden(string $path): bool {
		if (in_array('.git', explode('/', $path), true)) {
			return true;
		}
		return $this->matchesAny(SG_GLOBAL_FORBIDDEN, $path);
	}

	/**
	 * @param list<string> $scopeEntries
	 */
	private function matchesAny(array $scopeEntries, string $path): bool {
		foreach ($scopeEntries as $scopeEntry) {
			if ($this->pathCovers($scopeEntry, $path)) {
				return true;
			}
		}
		return false;
	}

	private function pathCovers(string $scope, string $path): bool {
		return $scope === $path || str_starts_with($path, $scope . '/');
	}

	/**
	 * @param array<string,mixed> $entry
	 */
	private function entryCovers(array $entry, string $path): bool {
		if ('exclusive' === $entry['mode']) {
			return $entry['path'] === $path;
		}
		return $this->pathCovers($entry['path'], $path);
	}

	/**
	 * @param array<string,mixed> $a
	 * @param array<string,mixed> $b
	 */
	private function ownershipPairOverlaps(array $a, array $b): bool {
		if ($a['path'] === $b['path']) {
			return true;
		}
		if ('scoped' === $a['mode'] && $this->pathCovers($a['path'], $b['path'])) {
			return true;
		}
		if ('scoped' === $b['mode'] && $this->pathCovers($b['path'], $a['path'])) {
			return true;
		}
		return false;
	}

	private function findSymlinkEscape(string $path): ?string {
		if ('' === $this->repository) {
			return null;
		}
		$root = $this->repository;
		$prefix = '';
		foreach (explode('/', $path) as $component) {
			$prefix = '' === $prefix ? $component : $prefix . '/' . $component;
			$absolute = $root . '/' . $prefix;
			if (! is_link($absolute)) {
				continue;
			}
			$target = @readlink($absolute);
			if (false === $target) {
				return $prefix;
			}
			$resolved = realpath($absolute);
			if (false !== $resolved) {
				if (! $this->isWithinRoot($resolved, $root)) {
					return $prefix;
				}
				continue;
			}
			$lexical = str_starts_with($target, '/')
				? self::normalizeAbsoluteLexically($target)
				: self::normalizeAbsoluteLexically(dirname($absolute) . '/' . $target);
			if (null === $lexical || ! $this->isWithinRoot($lexical, $root)) {
				return $prefix;
			}
		}
		return null;
	}

	private function isWithinRoot(string $path, string $root): bool {
		$root = rtrim($root, '/');
		if ('' === $root) {
			return str_starts_with($path, '/');
		}
		return $path === $root || str_starts_with($path, $root . '/');
	}

	private static function normalizeAbsoluteLexically(string $path): ?string {
		if (! str_starts_with($path, '/')) {
			return null;
		}
		$out = array();
		foreach (explode('/', $path) as $component) {
			if ('' === $component || '.' === $component) {
				continue;
			}
			if ('..' === $component) {
				if (array() === $out) {
					return null;
				}
				array_pop($out);
				continue;
			}
			$out[] = $component;
		}
		return '/' . implode('/', $out);
	}

	private static function isMapping($value): bool {
		return is_array($value) && (array() === $value || ! array_is_list($value));
	}

	private static function oneLine(string $value): string {
		$value = trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value) ?? '');
		if (strlen($value) > 600) {
			return substr($value, 0, 597) . '...';
		}
		return $value;
	}

	public function emit(bool $json): void {
		$exitCode = $this->currentExitCode();
		$changed = array();
		foreach ($this->changedPaths as $entry) {
			$changed[] = array(
				'path'   => $entry['path'],
				'status' => $entry['status'],
			);
		}
		$violations = array();
		foreach ($this->violations as $violation) {
			// Fill the task ID discovered after an early argument/base error.
			$violation['task_id'] = $this->taskId;
			$violations[] = $violation;
		}
		$result = array(
			'ok'                          => SG_EXIT_OK === $exitCode,
			'task_id'                     => $this->taskId,
			'base_sha'                    => $this->baseSha,
			'repository'                  => $this->repository,
			'base_commit_matches_contract' => $this->baseMatches,
			'changed_paths'               => $changed,
			'violations'                  => $violations,
			'inspection'                  => $this->inspection,
		);

		if ($json) {
			$encoded = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
			if (false === $encoded) {
				fwrite(STDERR, "Unable to encode JSON output.\n");
				echo '{"ok":false,"task_id":"","base_sha":"","repository":"","base_commit_matches_contract":false,"changed_paths":[],"violations":[],"inspection":{"tracked":0,"staged":0,"untracked":0,"complete":false}}' . "\n";
				return;
			}
			echo $encoded . "\n";
			return;
		}

		echo "Parsian Agent Scope Guard (CONTRACT-SCOPE-001)\n";
		echo 'Task: ' . self::display($this->taskId) . "\n";
		echo 'Base SHA: ' . self::display($this->baseSha) . "\n";
		echo 'Repository: ' . self::display($this->repository) . "\n";
		echo 'Base commit matches contract: ' . ($this->baseMatches ? 'yes' : 'no') . "\n";
		echo 'Changed paths: ' . count($this->changedPaths)
			. sprintf(' (tracked %d, staged %d, untracked %d)', $this->inspection['tracked'], $this->inspection['staged'], $this->inspection['untracked'])
			. "\n";
		echo 'Inspection complete: ' . ($this->inspection['complete'] ? 'yes' : 'no') . "\n";

		if (array() === $this->violations) {
			echo "Violations: none\n";
		} else {
			echo 'Violations: ' . count($this->violations) . "\n";
			foreach ($this->violations as $index => $violation) {
				echo sprintf("  %d. %s\n", $index + 1, self::oneLine((string) $violation['violation']));
				echo '     path: ' . self::display(is_string($violation['path']) ? $violation['path'] : null) . "\n";
				echo '     expected owner: ' . self::display(is_string($violation['expected_owner']) ? $violation['expected_owner'] : null) . "\n";
				echo '     actual owner: ' . self::display(is_string($violation['actual_owner']) ? $violation['actual_owner'] : null) . "\n";
				echo '     remediation: ' . self::oneLine((string) $violation['remediation']) . "\n";
			}
		}

		$summary = match ($exitCode) {
			SG_EXIT_OK => 'PASS',
			SG_EXIT_SCOPE => 'FAIL (scope violation)',
			SG_EXIT_CONFIG => 'INVALID (configuration error)',
			SG_EXIT_INSPECTION => 'INCOMPLETE (workspace inspection failure)',
			default => 'ERROR',
		};
		echo 'RESULT: ' . $summary . "\n";
		echo "Detection only: this tool reports violations; it does not prevent writes.\n";
	}

	private static function display(?string $value): string {
		if (null === $value) {
			return '(none)';
		}
		$encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
		return false === $encoded ? '(unprintable)' : $encoded;
	}
}

function sg_print_usage(): void {
	echo "Usage: php .agents/scripts/validate-task-scope.php \\\n";
	echo "  --task=<path> --base=<40-hex-sha> --repo=<git-worktree> --registry=<path> [--json]\n";
	echo "\n";
	echo "Options may also be supplied as separate arguments (--task <path>).\n";
	echo "Exit codes: 0 valid, 1 scope violation, 2 invalid task/config/registry, 3 Git inspection failure.\n";
	echo "Detection only: this tool does not prevent writes.\n";
}

$options = sg_parse_arguments($argv);
if ($options->help) {
	sg_print_usage();
	exit(SG_EXIT_OK);
}
$guard = new ScopeGuard($options);
$exitCode = $guard->run();
$guard->emit($options->json);
exit($exitCode);
