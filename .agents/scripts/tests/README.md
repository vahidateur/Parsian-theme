# Agent Scope Guard regression suite

Independent, zero-dependency PHP 8.4 regression tests for the Agent Scope Guard
defined by `CONTRACT-SCOPE-001`.

The suite builds throwaway Git repositories and YAML fixtures in a temporary
directory. It never reads or writes the live ownership registry, the live task
contract, WordPress data, backups, or production databases.

## Run

From the repository root:

```bash
php .agents/scripts/tests/validate-task-scope-test.php
```

Lint every PHP file first:

```bash
php -l .agents/scripts/tests/validate-task-scope-test.php
php -l .agents/scripts/tests/lib/Harness.php
php -l .agents/scripts/tests/lib/Cases.php
```

Expected case count: **22** (5 positive, 17 negative).

Current result on `feat/governance-hardening`: **22 passed, 0 failed, 22 total** (`RESULT: PASS`).

## Tool location

The runner looks for the tool at:

```text
.agents/scripts/validate-task-scope.php
```

relative to the checkout that contains the tests. For integration runs against a
different checkout, override it with an absolute path:

```bash
SCOPE_GUARD_PATH="/absolute/path/to/validate-task-scope.php" \
  php .agents/scripts/tests/validate-task-scope-test.php
```

If the tool is absent, the suite exits `2` with a `SETUP FAILURE` message. It
does **not** skip and never reports `PASS` or exit `0` without the tool. On
`feat/governance-hardening` the tool is present at the path above, so the suite
runs to completion; the absent-tool setup failure is an integration precondition
check, not an expected state for this branch.

## What every case asserts

- Expected exit code from the contract: `0`, `1`, `2`, or `3`.
- `--json` output is valid JSON with the contract's top-level keys.
- `ok` matches the exit status; `task_id`, `base_sha`, `repository`, and
  `base_commit_matches_contract` match the fixture.
- `changed_paths` contains `{path, status}` objects and the expected paths.
- `inspection.complete` is `true` for valid/violation runs and `false` for
  inspection failure. No non-zero case may look like a pass.
- Negative cases contain at least the named machine-readable violation.
- Exit `1` violations contain `task_id`, `path`, `violation`, `expected_owner`,
  `base_sha`, and non-empty `remediation`; `actual_owner`, when present, is a
  string or `null`.
- Invalid task, registry, and base inputs return `2`; a non-Git workspace
  returns `3`.

## Cases

| # | Case | Exit | Expected violation |
|---|---|---|---|
| P1 | One explicitly allowed file | 0 | — |
| P2 | Multiple explicitly allowed files (separate-argument CLI form) | 0 | — |
| P3 | Permitted nested directory scope | 0 | — |
| P4 | No changes | 0 | — |
| P5 | Valid base commit | 0 | — |
| N1 | Modified file outside allowed scope | 1 | `out_of_scope` |
| N2 | Untracked file outside allowed scope | 1 | `out_of_scope` |
| N3 | Staged file outside allowed scope | 1 | `out_of_scope` |
| N4 | Deleted file outside allowed scope | 1 | `out_of_scope` |
| N5 | Renamed file outside allowed scope | 1 | `out_of_scope` |
| N6 | Forbidden file | 1 | `forbidden_path` |
| N7 | File owned by another active task | 1 | `other_task_owner` |
| N8 | Overlapping active ownership | 1 | `ownership_overlap` |
| N9 | Invalid task ID | 2 | `invalid_task` |
| N10 | Invalid base SHA | 2 | `base_commit_mismatch` |
| N11 | Path traversal in task scope | 2 | `invalid_scope` |
| N12 | Symlink escape | 1 | `symlink_escape` |
| N13 | Invalid ownership registry | 2 | `invalid_registry` |
| N14 | Incomplete Git inspection (non-Git directory) | 3 | `git_inspection_failed` |
| N15 | Component boundary is not a string prefix | 1 | `out_of_scope` |
| N16 | Expired active lease is not active ownership | 1 | `unowned_path` |
| N17 | Hidden file outside allowed scope | 1 | `out_of_scope` |

N15 is additional coverage for the contract's component-aware matching rule:
an allowed scope of `allowed/` must not cover `allowed-archive/file.txt`.
N16 verifies that an expired lease marked `active` is not treated as active
ownership, producing `unowned_path`. N17 verifies that a hidden file outside
the allowed scope produces `out_of_scope`.

## Environment variables

| Variable | Purpose |
|---|---|
| `SCOPE_GUARD_PATH` | Absolute (or cwd-relative) path to the Scope Guard under test. |
| `SCOPE_TEST_TMPDIR` | Base directory for temporary repositories. Defaults to `/tmp/opencode` when available, otherwise the system temp directory. |
| `SCOPE_TEST_KEEP_TMP` | When non-empty, keep temporary repositories and print their path. |

Temporary directories are removed on exit unless `SCOPE_TEST_KEEP_TMP` is set.
Symlink cleanup never follows a symlink outside the temporary tree.

## Contract-derived assumptions

The suite is intentionally written against `CONTRACT-SCOPE-001` only, not a
specific implementation. The main interpretation choices are:

- `ownership_overlap` is a scope violation with exit `1` because the contract
  lists it as one of the JSON violation names.
- A malformed or mismatched `--base` is configuration error `2` and reports
  `base_commit_mismatch`.
- An unsafe entry inside the task's `scope.allowed` is `invalid_scope`, not a
  changed-path violation.
- A symlink that resolves outside the repository is `symlink_escape` with
  exit `1`.
- JSON scope violations carry the same path detail shape shown in the contract.
- Cases N1–N5 grant the task active ownership of the out-of-scope changed path.
  This isolates the contract-scope check, so the expected failure is
  `out_of_scope`, not `unowned_path`.
- The forbidden-path case places the forbidden path outside the allowed tree
  because the repository governance validator rejects allowed/forbidden
  overlap. The expected violation remains `forbidden_path`.

If the implementation intentionally chooses a different mapping, the
independent suite will fail and the contract or implementation should be
reconciled during integration review.

## Limitations

- The symlink case needs operating-system symlink support.
- The suite validates the machine-readable interface. It does not grade the
  human-readable output or the security of process execution beyond observable
  behavior.
