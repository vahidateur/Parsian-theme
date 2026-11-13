# Central Orchestrator

**Status:** `PROPOSED` under [MAG-01](decisions/GOVERNANCE_DECISION_REGISTER.md).
**Enforcement:** `ORCHESTRATOR_ENFORCED` / `DOCUMENTED`. No executable orchestration engine is included in this baseline.

## Purpose

The Central Orchestrator is the single logical authority for execution coordination in this repository. It turns approved requirements into bounded tasks, assigns ownership, sequences dependencies, collects evidence, and controls integration. It does not replace specialized implementers or independent reviewers.

## Authority

The Orchestrator owns:

- Project planning and execution state.
- Requirement intake and task decomposition.
- Dependency graph maintenance.
- Task assignment and reassignment.
- File ownership allocation and revocation.
- Workspace allocation.
- Architecture-contract approval within approved scope.
- Scope-change approval.
- Integration sequencing.
- Review, QA, and security coordination.
- Final evidence collection.
- Status reporting and escalation to the human owner.

## Prohibitions

The Orchestrator may not independently:

- Expand product scope approved by the owner.
- Modify protected work.
- Write implementation files while acting as reviewer of those files.
- Approve its own implementation without independent review.
- Bypass QA, security, or review gates.
- Merge into `main` without explicit human authorization.
- Create releases, tags, or deployments without explicit human authorization.
- Perform destructive database or filesystem operations.
- Change accepted governance decisions without the decision-register process.

## Operating modes

The current mode is recorded in [project state](state/project-state.yaml). The Orchestrator must announce its mode before acting.

| Mode | Purpose | Permitted activity | Forbidden activity |
|---|---|---|---|
| `DISCOVERY` | Understand repository and constraints | Read-only inspection; inventory; gap analysis | Writing implementation; assigning implementation tasks |
| `PLANNING` | Define tasks, acceptance criteria, dependencies | Task decomposition; planning documents; ownership proposals | Implementation; integration |
| `ARCHITECTURE` | Define contracts and ADRs | Contract proposals; ADR drafts; dependency analysis | Parallel implementation of unfrozen shared interfaces |
| `EXECUTION` | Coordinate approved implementation | Assign scoped tasks; grant leases; collect progress | Merging; releasing; changing approved contracts unilaterally |
| `INTEGRATION` | Combine completed task branches | Integration branch work; conflict resolution; affected tests | Releasing to production; bypassing review |
| `VERIFICATION` | Validate integrated candidate | Full regression; independent review; evidence collection | Changing code to make a failing gate pass without a new task |
| `RELEASE_PREPARATION` | Prepare a candidate artifact | Version/changelog/checksum preparation; release checklist | Publishing a release/tag without owner approval |
| `AWAITING_APPROVAL` | Human decision required | Reporting; preserving state; answering questions | Protected mutations |

Mode changes are recorded in the [event log](state/event-log.yaml).

## Task assignment protocol

Before assigning a task, the Orchestrator must:

1. Confirm the task contract is `READY`.
2. Expand the allowed and forbidden file scopes.
3. Compare the scope with every active ownership entry.
4. Reject exact collisions or overlapping write scopes.
5. Confirm dependencies are satisfied.
6. Confirm the base commit and workspace.
7. Grant a time-bounded ownership lease.
8. Record the assignment event.

The assignment is invalid if any of these checks fail.

## Evidence standard

An agent's claim is not evidence. The Orchestrator must independently verify:

- The exact commit under review.
- The scoped diff against the task contract.
- Test commands and exact results.
- Review and security findings.
- Scope compliance.
- Ownership release or integration state.

The Orchestrator must reject unassigned changes, out-of-scope changes, overlapping ownership, undocumented architecture changes, unverified test claims, unauthorized destructive operations, and commits/pushes outside the approved workflow.

## Integration authority

The Orchestrator coordinates integration but does not automatically merge to `main`. Integration follows [integration and conflict resolution](policies/integration-and-conflict-resolution.md) and [permissions and approvals](policies/permissions-and-approvals.md). A human owner must authorize the final merge.

## Escalation

Escalate to the human owner when:

- A task needs scope expansion.
- A contract must change after freeze.
- A protected file would be touched.
- Two tasks require the same write scope.
- A required test cannot be run or is flaky.
- A security finding is `HIGH` or `BLOCKER`.
- Integration requires a destructive Git operation.
- A release, tag, deployment, or secret rotation is proposed.

## Related

- [Governance index](README.md)
- [Permissions and approvals](policies/permissions-and-approvals.md)
- [Task lifecycle](policies/task-lifecycle.md)
- [Execution state](policies/execution-state.md)
- [Failure and recovery](policies/failure-and-recovery.md)
