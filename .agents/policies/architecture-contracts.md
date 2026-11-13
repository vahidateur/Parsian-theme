# Architecture Contracts and ADRs

**Enforcement:** `DOCUMENTED` + `ORCHESTRATOR_ENFORCED`; contract fields and references are `SCRIPT_VALIDATED` where included in task data.

## Purpose

Define shared interfaces before parallel implementation and record significant architectural decisions.

## Architecture contracts

A contract must define:

- Contract ID.
- Version.
- Owner.
- Consumers.
- Purpose and scope.
- Inputs and outputs.
- Error semantics.
- Database ownership and transaction boundaries.
- API route and authentication requirements.
- Event semantics.
- Stability level.
- Approval status.
- Required tests.
- Backward-compatibility notes.

Contract IDs use `CONTRACT-<AREA>-<NNN>`, for example `CONTRACT-POPUP-001`.

## Contract lifecycle

```text
DRAFT → PROPOSED → APPROVED → FROZEN → SUPERSEDED
```

- `DRAFT` — author is still designing; no dependent implementation.
- `PROPOSED` — ready for architecture review.
- `APPROVED` — Orchestrator approved for use.
- `FROZEN` — dependent implementation may proceed; unilateral change is prohibited.
- `SUPERSEDED` — replaced by a new version.

A frozen contract can be changed only through a contract-change proposal with impact analysis, dependent-task notification, approval, version increment, and test updates.

## ADRs

Every significant architectural choice needs an ADR. At minimum record:

- Context.
- Problem.
- Considered alternatives.
- Decision.
- Consequences.
- Security impact.
- Migration impact.
- Reversibility.
- Status.

Use [the ADR template](../templates/adr.md). ADR files are task artifacts whose path is recorded in the task contract; accepted ADRs may be copied into the appropriate `docs/` area by a documentation task.

## Contract registry

The authoritative list of active contract IDs is recorded in [project state](../state/project-state.yaml) under `active_contracts` and in each task contract's `contracts` field. Do not invent contract IDs that are not recorded.

## Related

- [Governance index](../README.md)
- [Solution Architect](../roles/solution-architect.md)
- [Dependency management](dependency-management.md)
- [Task lifecycle](task-lifecycle.md)
- [ADR template](../templates/adr.md)
