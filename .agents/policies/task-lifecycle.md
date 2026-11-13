# Task Lifecycle

**Enforcement:** `DOCUMENTED` + `ORCHESTRATOR_ENFORCED`; status and transition structure is `SCRIPT_VALIDATED`.

## Purpose

Define one canonical task lifecycle, required evidence, and terminal states.

## State machine

```text
PROPOSED
   |
   v
SPECIFIED
   |
   v
READY
   |
   v
ASSIGNED
   |
   v
IN_PROGRESS
   |
   +------> BLOCKED
   |           |
   |           v
   |        ASSIGNED
   |
   v
IMPLEMENTATION_COMPLETE
   |
   v
CODE_REVIEW
   |
   v
QA
   |
   v
SECURITY_REVIEW
   |
   v
INTEGRATION_READY
   |
   v
INTEGRATING
   |
   v
INTEGRATED
   |
   v
VERIFIED
   |
   v
COMPLETE
```

Terminal states: `CANCELLED`, `REJECTED`, `SUPERSEDED`.

## Allowed transitions

| From | To |
|---|---|
| `PROPOSED` | `SPECIFIED`, `CANCELLED` |
| `SPECIFIED` | `READY`, `CANCELLED`, `SUPERSEDED` |
| `READY` | `ASSIGNED`, `BLOCKED`, `CANCELLED` |
| `ASSIGNED` | `IN_PROGRESS`, `BLOCKED`, `CANCELLED` |
| `IN_PROGRESS` | `IMPLEMENTATION_COMPLETE`, `BLOCKED`, `CANCELLED` |
| `IMPLEMENTATION_COMPLETE` | `CODE_REVIEW`, `BLOCKED` |
| `CODE_REVIEW` | `QA`, `IN_PROGRESS`, `REJECTED`, `BLOCKED` |
| `QA` | `SECURITY_REVIEW`, `IN_PROGRESS`, `REJECTED`, `BLOCKED` |
| `SECURITY_REVIEW` | `INTEGRATION_READY`, `IN_PROGRESS`, `REJECTED`, `BLOCKED` |
| `INTEGRATION_READY` | `INTEGRATING`, `BLOCKED` |
| `INTEGRATING` | `INTEGRATED`, `BLOCKED`, `REJECTED` |
| `INTEGRATED` | `VERIFIED`, `REJECTED` |
| `VERIFIED` | `COMPLETE`, `SUPERSEDED` |
| `BLOCKED` | `ASSIGNED`, `IN_PROGRESS`, `CANCELLED`, `SUPERSEDED` |

No other transition is valid. Every transition is recorded in the event log with actor, timestamp, task ID, from-state, and to-state.

## Task contract

Every task uses [the task contract template](../templates/task-contract.yaml) and must include:

- Task ID.
- Title.
- Owner and role.
- Workspace and base commit.
- Permissions.
- Allowed and forbidden files.
- Dependencies.
- Approved contracts.
- Acceptance criteria.
- Required tests.
- Deliverables.
- Approval requirements.

## Completion evidence

An agent cannot declare a task complete because it finished editing files. Completion requires:

- Deliverables submitted.
- Scope compliance checked.
- Required tests passed on the exact commit.
- Independent review completed.
- QA and security evidence where required.
- Integration accepted.
- Evidence attached to the task.
- Ownership released.

## Blocked tasks

A blocked task records:

- Blocking reason.
- Missing dependency or decision.
- Responsible party.
- Impact on the execution plan.
- Suggested resolution.

Blocked tasks are not silently bypassed. Dependent tasks are re-evaluated.

## Cancellation and rejection

- `CANCELLED` — work stopped by the owner or Orchestrator.
- `REJECTED` — review, QA, or security rejected the deliverable.
- `SUPERSEDED` — replaced by another task or decision.

Terminal states preserve the workspace and evidence for recovery.

## Related

- [Governance index](../README.md)
- [Central Orchestrator](../orchestrator.md)
- [Dependency management](dependency-management.md)
- [Independent review](independent-review.md)
- [Task schema](../schemas/task.schema.json)
