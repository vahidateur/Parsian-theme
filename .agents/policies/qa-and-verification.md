# QA and Verification

**Enforcement:** `DOCUMENTED` + `ORCHESTRATOR_ENFORCED`; test evidence and acceptance fields are `SCRIPT_VALIDATED` where included in task data.

## Purpose

Verify actual integrated behavior with reproducible evidence, independently of the implementer's claims.

## QA independence

QA must verify the integrated behavior, not merely run the developer's preferred tests. QA may read source but writes only assigned test files.

## Required evidence

Depending on the task, evidence may include:

- Unit tests.
- Integration tests.
- Regression tests.
- End-to-end tests.
- Cross-theme compatibility tests.
- Database migration and rollback tests.
- Failure and recovery tests.
- Security tests.
- Performance measurements.

Tests must be reproducible from a clean checkout. No test may depend on an undocumented temporary file, personal environment, or secret.

## Test execution

- Run targeted tests first.
- Run the full relevant suite at integration and release gates.
- Record exact commands and exact results.
- Record PHP, WordPress, database, Node, and tool versions where relevant.
- Do not claim a test passed without execution evidence.

## Acceptance rule

A task cannot be marked `VERIFIED` if:

- A blocker remains unresolved.
- A required test is missing.
- A test failure is suppressed without an approved explanation.
- The final integrated code differs from the reviewed revision.
- Review evidence refers to an older commit.

## Failure handling

A failing test is evidence, not an inconvenience. Record the failure, reproduce it, and route it to the task owner. Flaky tests must be identified and fixed or explicitly quarantined with owner approval.

## Related

- [Governance index](../README.md)
- [QA Engineer](../roles/qa-engineer.md)
- [Independent review](independent-review.md)
- [Task lifecycle](task-lifecycle.md)
- [Existing test suites](../../wp-local/tests)
