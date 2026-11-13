# Execution State

**Enforcement:** `SCRIPT_VALIDATED` for structure; `ORCHESTRATOR_ENFORCED` for accuracy.

## Purpose

Maintain one recoverable source of project execution state outside conversational memory.

## State files

- [project-state.yaml](../state/project-state.yaml) — mode, milestone, active/blocked tasks, contracts, approvals, last validation.
- [ownership-registry.yaml](../state/ownership-registry.yaml) — active and released file ownership.
- [event-log.yaml](../state/event-log.yaml) — significant events.
- [protected-work.yaml](../state/protected-work.yaml) — owner-declared protected paths (empty by default).

The schemas are:

- [execution-state.schema.json](../schemas/execution-state.schema.json)
- [ownership.schema.json](../schemas/ownership.schema.json)
- [event.schema.json](../schemas/event.schema.json)

## Required state

The state identifies:

- Current milestone.
- Current Orchestrator mode.
- Active tasks.
- Assigned agents.
- Workspace paths.
- Branch names.
- File ownership.
- Dependencies.
- Blocked tasks.
- Review state.
- Test state.
- Integration state.
- Approval status.

## Event log

Record significant events:

- Task created.
- Task assigned.
- Ownership granted or released.
- Workspace created.
- Contract approved.
- Scope changed.
- Task blocked.
- Review completed.
- Test failed or passed.
- Integration accepted.
- Approval requested.
- Task completed.
- Validation passed or failed.

Each event has a timestamp, actor, task ID, type, and concise description. Do not store secrets or message contents.

## Recovery

After an interrupted session, the Orchestrator reconstructs state from these files and the repository. If the state conflicts with evidence, evidence wins and the state is corrected with a recorded event.

## Related

- [Governance index](../README.md)
- [Central Orchestrator](../orchestrator.md)
- [File ownership and leases](file-ownership.md)
- [Execution-state schema](../schemas/execution-state.schema.json)
