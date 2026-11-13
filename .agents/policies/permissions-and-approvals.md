# Permissions and Approvals

**Enforcement:** `DOCUMENTED` + `ORCHESTRATOR_ENFORCED`. The validator checks permission enum membership but cannot grant or revoke access.

## Purpose

Define task-scoped permissions and the human-approval gates that no agent may bypass.

## Permission levels

| Permission | Meaning |
|---|---|
| `OBSERVE` | Read source, logs, documentation, and test results |
| `ANALYZE` | Produce findings, diagrams, and specifications |
| `PROPOSE` | Suggest changes without modifying implementation |
| `WRITE_SCOPED` | Modify explicitly assigned files |
| `TEST_SCOPED` | Execute tests and modify assigned test files |
| `INTEGRATE` | Integrate approved task branches under Orchestrator coordination |
| `RELEASE_PREPARE` | Prepare candidate artifacts, version notes, and release documentation |

Permissions are task-scoped, temporary, and revocable. They expire when the task is completed, cancelled, blocked and reassigned, or when the lease expires. An agent must stop writing immediately when permission is revoked.

## Default role permissions

| Role | Default permissions |
|---|---|
| Central Orchestrator | `OBSERVE`, `ANALYZE`, `PROPOSE`, `INTEGRATE`, `RELEASE_PREPARE` (coordination only; no implementation writes) |
| Product Analyst | `OBSERVE`, `ANALYZE`, `PROPOSE` |
| Solution Architect | `OBSERVE`, `ANALYZE`, `PROPOSE` |
| Backend Engineer | `OBSERVE`, `ANALYZE`, `PROPOSE`, `WRITE_SCOPED`, `TEST_SCOPED` |
| Frontend Engineer | `OBSERVE`, `ANALYZE`, `PROPOSE`, `WRITE_SCOPED`, `TEST_SCOPED` |
| Data and Migration Engineer | `OBSERVE`, `ANALYZE`, `PROPOSE`, `WRITE_SCOPED`, `TEST_SCOPED` |
| Security Engineer | `OBSERVE`, `ANALYZE`, `PROPOSE`; `TEST_SCOPED` only for assigned security tests |
| QA Engineer | `OBSERVE`, `ANALYZE`, `TEST_SCOPED` |
| Code Reviewer | `OBSERVE`, `ANALYZE`, `PROPOSE` |
| DevOps Engineer | `OBSERVE`, `ANALYZE`, `PROPOSE`, `WRITE_SCOPED`, `TEST_SCOPED` (assigned infrastructure only) |
| Documentation Engineer | `OBSERVE`, `ANALYZE`, `WRITE_SCOPED` (assigned docs only) |

A role name does not grant access. The task contract and ownership registry grant access.

## Human approval gates

Explicit human approval is required before:

1. Git commit.
2. Merge.
3. Push.
4. Any change to protected work.
5. Production deployment.
6. Production database migration.
7. Irreversible deletion.
8. Secret rotation.
9. Release or tag publication.
10. Governance-policy change.

Additional gates:

- Scope expansion.
- Architecture-contract freeze or change.
- High-risk schema or data migration.
- Creation of a new integration branch.
- Any destructive Git operation.
- Any action that changes repository history.

No agent may bypass a gate by splitting a prohibited operation into smaller tasks. No agent may approve its own protected action.

## Approval record

An approval must identify:

- The action.
- The exact commit or artifact.
- The approving human.
- The timestamp.
- The scope and conditions.
- The expiry, if any.

Approvals are recorded in the task handoff and, when material to execution, in the [event log](../state/event-log.yaml).

## Revocation

The Orchestrator may revoke a permission or ownership lease at any time. Revocation is recorded as an event. The affected agent must stop writing and preserve its workspace for inspection.

## Enforcement limitations

- Permission membership is `SCRIPT_VALIDATED` for task contracts.
- Grant, revocation, and approval are `ORCHESTRATOR_ENFORCED`.
- This baseline does not add tool-level filesystem or shell restrictions.
- Git/GitHub gates are not `GIT_ENFORCED` or `GITHUB_ENFORCED` by this baseline.

## Related

- [Governance index](../README.md)
- [Central Orchestrator](../orchestrator.md)
- [File ownership and leases](file-ownership.md)
- [Task lifecycle](task-lifecycle.md)
- [Security and data governance](security-and-data-governance.md)
