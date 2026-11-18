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

Terminal states: `CANCELLED`, `REJECTED`, `SUPERSEDED`. No transition may originate from a terminal state; in particular, `REJECTED → SUPERSEDED` is not allowed unless this policy is amended.

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


## Task creation events

A **task creation** is an event whose `type` is exactly `TASK_CREATED`. Every task contract must have exactly one `TASK_CREATED` event. New tasks begin in `PROPOSED`; the event records `id`, `timestamp`, `actor`, `task_id`, `type`, `description`, and `initial_state: PROPOSED`. A `TASK_CREATED` event must not contain transition fields. The first `TASK_TRANSITION` for a task must originate from `PROPOSED`; transition chains must be contiguous, and the final `to_state` must match the declared task status. Duplicate creation events, missing creation events, and chain breaks are invalid.

## Lifecycle transition events

A **lifecycle transition** is an event whose `type` is exactly `TASK_TRANSITION`. No other event type constitutes a lifecycle transition.

Every new `TASK_TRANSITION` event must contain:

- `id` — unique `EV-NNNN` identifier.
- `timestamp` — the time the transition was recorded.
- `actor` — the accountable actor.
- `task_id` — the task whose state changed.
- `type` — exactly `TASK_TRANSITION`.
- `description` — a meaningful explanation of the transition.
- `from_state` — a valid lifecycle state.
- `to_state` — a valid lifecycle state.

The `from_state` and `to_state` pair must appear in the allowed-transitions table above. Missing either field, an invalid state, an unsupported transition, an unknown property, or transition fields on a non-transition event is invalid. The event schema enforces the declared field and state constraints; the governance validator additionally enforces the allowed-transitions table.

### Legacy events

The authoritative MAG baseline has no active task history. This policy therefore defines **no broad legacy exemption for task contracts**: every task contract must have exactly one `TASK_CREATED` event and a valid transition chain. Event types recorded before this rule — including `TASK_ASSIGNED`, `TASK_STARTED`, `IMPLEMENTATION_COMPLETE`, `TASK_INTEGRATED`, `TASK_STATUS_CORRECTED`, `OWNERSHIP_*`, `PILOT_*`, and validation events — remain valid historical records. They are **legacy events** and are not lifecycle transitions.

Legacy events:

- remain unchanged in the event log;
- are not retrofitted with `from_state` or `to_state`;
- must not be assigned inferred states, timestamps, actors, or outcomes;
- do not by themselves establish `VERIFIED` or `COMPLETE`;
- do not authorize a future transition or substitute for `TASK_CREATED`.

A future state change requires a new `TASK_TRANSITION` event, even when a legacy event of a similar name exists. Historical status assertions do not approve security or integration gates.

### Corrections and exceptional transitions

A correction to a lifecycle state also uses `TASK_TRANSITION` with the actual `from_state` and `to_state` and a description of the correction. Historical `TASK_STATUS_CORRECTED` records remain legacy events; they are not rewritten or treated as new transitions.

A transition that is not in the allowed-transitions table is invalid. If an exception is genuinely required, the owner must approve a lifecycle-policy change before the event is recorded; do not invent a one-off transition.

### Evidence before `VERIFIED` or `COMPLETE`

A task may reach `VERIFIED` or `COMPLETE` only when:

- the required implementation and tests are complete on the exact commit;
- independent review is complete;
- QA and security evidence are complete where required;
- integration is accepted;
- required approvals are explicit and attributable; and
- ownership has been released.

`INTEGRATED` is not `VERIFIED` or `COMPLETE`. A transition to `VERIFIED` or `COMPLETE` must be recorded as a `TASK_TRANSITION` with the actual prior state and supporting evidence in the task record.

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
