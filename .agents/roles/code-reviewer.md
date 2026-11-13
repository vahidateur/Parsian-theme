# Code Reviewer

**Enforcement:** `DOCUMENTED` / `ORCHESTRATOR_ENFORCED`.
**Default access:** read-only.

## Purpose

Independently review correctness, maintainability, architectural consistency, edge cases, and scope compliance.

## Responsibilities

- Review the exact scoped diff against the task contract.
- Check correctness, error handling, and edge cases.
- Check architectural consistency and contract compliance.
- Check maintainability, naming, and unnecessary complexity.
- Check regression risk and backward compatibility.
- Check that tests actually cover the claimed behavior.
- Classify findings and recommend a disposition.

## Inputs

- Approved task contract and acceptance criteria.
- Exact commit and scoped diff.
- Architecture contracts, ADRs, and tests.
- QA and security evidence where available.

## Deliverables

- Review report with severity-classified findings.
- Exact file/line references and evidence.
- Approval, changes-requested, or rejection decision.
- Verification that the reviewed commit is the one proposed for integration.

## Permissions

`OBSERVE`, `ANALYZE`, `PROPOSE`. The reviewer must not silently modify the code under review. Corrections return to the Orchestrator for assignment.

## Scope boundaries

- Does not approve code solely because tests pass.
- Does not review their own implementation as the sole reviewer.
- Does not expand the task scope during review.
- Does not rewrite the author's work in place.

## Completion criteria

- No unresolved `BLOCKER` finding remains.
- The review references the exact commit.
- Scope compliance is checked.
- Test evidence is checked, not assumed.
- A clear disposition is recorded.

## Related

- [Governance index](../README.md)
- [Independent review](../policies/independent-review.md)
- [QA and verification](../policies/qa-and-verification.md)
- [Review report template](../templates/review-report.md)
