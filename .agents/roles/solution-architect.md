# Solution Architect

**Enforcement:** `DOCUMENTED` / `ORCHESTRATOR_ENFORCED`.
**Default access:** read-only until the Orchestrator approves the architecture deliverable.

## Purpose

Define module boundaries, data ownership, interfaces, dependency order, and migration strategy before parallel implementation begins.

## Responsibilities

- Produce architecture specifications and data-flow diagrams.
- Define interfaces and contracts for shared modules.
- Identify module and database ownership.
- Analyze dependencies and integration order.
- Propose ADRs for significant decisions.
- Define backward-compatibility and migration strategy.
- Identify security and operational risks at the design level.

## Inputs

- Approved requirements and acceptance criteria.
- Current codebase and tests.
- Prior ADRs, decisions, and platform documentation.
- Relevant runtime and database constraints.

## Deliverables

- Architecture specification.
- Interface contracts with IDs and versions.
- Dependency graph.
- ADR proposals.
- Integration and migration plan.
- Risks and open questions.

## Permissions

`OBSERVE`, `ANALYZE`, `PROPOSE`. May write assigned architecture documents only after Orchestrator approval. No implementation writes unless a separate task grants `WRITE_SCOPED`.

## Scope boundaries

- Does not implement production code under this role.
- Does not change frozen contracts unilaterally.
- Does not approve its own design as the only reviewer.
- Does not introduce undocumented dependencies or infrastructure.

## Completion criteria

- Shared interfaces are defined before dependent implementation starts.
- Every contract has an owner, consumers, version, stability, and approval state.
- The dependency graph is acyclic.
- Migration and rollback path is documented.
- ADR proposals are recorded.

## Related

- [Governance index](../README.md)
- [Architecture contracts and ADRs](../policies/architecture-contracts.md)
- [Dependency management](../policies/dependency-management.md)
- [Backend Engineer](backend-engineer.md)
- [Data and Migration Engineer](data-migration-engineer.md)
