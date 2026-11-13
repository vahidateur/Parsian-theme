# Dependency Management

**Enforcement:** `DOCUMENTED` + `ORCHESTRATOR_ENFORCED`; dependency references and cycles are `SCRIPT_VALIDATED` where task data is available.

## Purpose

Maintain an acyclic dependency graph so tasks start only when their prerequisites are satisfied.

## Dependency graph

The Orchestrator maintains a directed acyclic graph. A task may start only when all mandatory dependencies are satisfied.

A dependency is satisfied when the depended-on task is `VERIFIED` or `COMPLETE`, or when a documented owner-approved exception exists. Architecture contracts must be frozen before dependent implementation starts.

## Required analysis

Before scheduling, consider:

- Shared files and ownership.
- Shared interfaces and contracts.
- Database schema dependencies.
- Generated artifacts.
- Test fixtures.
- Global configuration.
- Cross-module behavior.
- Migration ordering.
- Security and privacy approvals.

## Parallelism limits

Do not maximize agent count by default. Use the smallest number of concurrent agents that can work independently.

Recommended starting policy:

- 1 Orchestrator.
- 1 Analyst/Architect during discovery.
- 2–3 implementation agents for independent tasks.
- 1 independent QA agent.
- 1 independent Security/Review agent.

Parallelism may increase only when ownership and dependency analysis prove it is safe.

## Cycle detection

A cycle is invalid. The Orchestrator must break the cycle by:

- Splitting a task.
- Introducing an explicit contract task.
- Deferring one branch.
- Escalating to the human owner.

The validator checks dependency IDs for validity and self-reference. It does not build a full graph from task files unless a task registry is provided; the Orchestrator remains responsible for the authoritative graph.

## Blocked dependencies

If a dependency is missing or blocked:

- The dependent task remains `BLOCKED` or `READY` but unassigned.
- The block is recorded in the event log.
- The Orchestrator updates dependent task status.
- No agent invents a substitute dependency.

## Related

- [Governance index](../README.md)
- [Task lifecycle](task-lifecycle.md)
- [Architecture contracts and ADRs](architecture-contracts.md)
- [Central Orchestrator](../orchestrator.md)
