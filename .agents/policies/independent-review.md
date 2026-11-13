# Independent Review

**Enforcement:** `DOCUMENTED` + `ORCHESTRATOR_ENFORCED`; review-report structure may be `SCRIPT_VALIDATED` when included in task evidence.

## Purpose

Ensure code is reviewed by someone other than its implementer and that findings are evidence-based.

## Independence rule

The implementing agent cannot be the sole reviewer of its own work. A reviewer may not silently modify the reviewed code. Proposed corrections return to the Orchestrator for assignment.

## Review scope

The reviewer examines:

- Correctness.
- Security.
- Edge cases and error handling.
- Performance and resource use.
- Data integrity.
- Backward compatibility.
- Maintainability.
- Architectural and contract consistency.
- Scope compliance.
- Test adequacy.

A reviewer must not approve code solely because its tests pass.

## Findings

Classify every finding:

- `BLOCKER` — must be fixed before integration.
- `HIGH` — significant security or data-integrity risk; must be fixed or explicitly accepted.
- `MEDIUM` — should be addressed; justify whether it blocks.
- `LOW` — maintainability or documentation improvement.
- `INFORMATIONAL` — note only.

Each finding includes:

- Exact file and line reference.
- Evidence or reproduction.
- Impact.
- Recommended fix.
- Whether it blocks integration.

## Exact-commit rule

Review evidence must refer to the exact commit under acceptance. If the code changes after review, the review is stale and must be repeated for the changed scope.

## Disposition

The reviewer records one of:

- `APPROVED` — no blocking findings.
- `CHANGES_REQUESTED` — blocking findings remain.
- `REJECTED` — the approach is not acceptable.
- `APPROVED_WITH_CONDITIONS` — non-blocking items must be tracked.

## Related

- [Governance index](../README.md)
- [Code Reviewer](../roles/code-reviewer.md)
- [QA and verification](qa-and-verification.md)
- [Review report template](../templates/review-report.md)
- [Integration and conflict resolution](integration-and-conflict-resolution.md)
