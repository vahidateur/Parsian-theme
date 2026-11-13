# Initial Pilot Plan — Persistent Settings Architecture

**Status:** `PROPOSED`; no pilot task is active and no Persistent Settings implementation is authorized by this baseline.
**Enforcement:** `DOCUMENTED` + `ORCHESTRATOR_ENFORCED`.

## Purpose

Validate the governance operating model on a bounded, real project without starting the Persistent Settings implementation during governance bootstrap.

## Pilot team

- 1 Central Orchestrator.
- 1 Product Analyst / Solution Architect (may be the same model sequentially).
- 2 implementation agents with non-overlapping file ownership.
- 1 QA Engineer.
- 1 independent Code Reviewer.
- Security Engineer engaged at the security gate.

## Proposed task graph

These are illustrative task IDs, not created tasks:

```text
PS-001  Requirements and acceptance criteria
   |
PS-002  Architecture contracts and ADR
   |
   +----------------------+
   |                      |
PS-003 Backend/Data      PS-004 Frontend/UI
   |                      |
   +----------+-----------+
              |
           PS-005 QA and integration regression
              |
           PS-006 Independent review and security
```

`PS-003` and `PS-004` may run in parallel only after `PS-002` freezes the shared contracts and file ownership is split at exact-path level.

## Required pilot evidence

- Approved requirements and acceptance criteria.
- Frozen interface contracts and ADR.
- Two isolated worktrees with recorded base commits.
- Non-overlapping ownership registry entries.
- Scoped diffs for each task.
- Independent QA evidence on the integrated revision.
- Independent review and security disposition.
- Full regression verification.
- Handoff reports and event log.
- Ownership release records.

## Rollout stages

1. **Governance foundation** — complete this baseline and validate it.
2. **Pilot** — run the bounded Persistent Settings design/implementation with the team above.
3. **Controlled expansion** — add Data, Security, DevOps, and Documentation roles only when the work requires them.
4. **Operational standard** — make the workflow the default for substantial tasks after the pilot is reviewed, and update onboarding and the ownership registry.

Do not activate all roles or all agents simultaneously.

## Measurable indicators

Track per pilot:

- File conflict count.
- Scope violations.
- Rework rate (review/QA cycles per task).
- Test failures and flaky tests.
- Integration time.
- Blocked task duration.
- Out-of-scope changed files.
- Ownership overlap incidents.

## Exit criteria

The pilot is successful only if:

- No unresolved `BLOCKER` finding remains.
- No overlapping active write ownership occurred.
- Every task has a complete contract and evidence.
- The integrated revision passes the required regression matrix.
- The owner accepts the result.
- Governance friction is documented with proposed improvements.

## Stop conditions

Stop the pilot and escalate if:

- Two tasks require the same write scope.
- A shared contract changes after freeze without approval.
- A protected path is touched.
- Tests cannot reproduce.
- A security `HIGH`/`BLOCKER` finding remains.
- The integration requires history rewriting or destructive operations.

## Related

- [Governance index](../README.md)
- [Central Orchestrator](../orchestrator.md)
- [Task lifecycle](task-lifecycle.md)
- [File ownership and leases](file-ownership.md)
- [Workspace isolation](workspace-isolation.md)
