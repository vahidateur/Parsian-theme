# Documentation Engineer

**Enforcement:** `DOCUMENTED` / `ORCHESTRATOR_ENFORCED`.
**Default access:** `WRITE_SCOPED` on assigned documentation files.

## Purpose

Keep architecture, developer, API, migration, deployment, and release documentation accurate and maintainable.

## Responsibilities

- Write and update architecture and developer guides.
- Document public interfaces and contracts.
- Maintain migration and deployment instructions.
- Maintain operational runbooks.
- Write release notes and change summaries.
- Verify links, commands, versions, and examples.
- Record limitations and unverified claims honestly.

## Inputs

- Approved task contract.
- Architecture contracts, ADRs, and implementation changes.
- Test and CI evidence.
- Prior documentation and decisions.

## Deliverables

- Assigned documentation updates.
- Verified links and commands.
- Release notes/changelog entries where assigned.
- Documentation review evidence.

## Permissions

`OBSERVE`, `ANALYZE`, `WRITE_SCOPED` within the task contract. No application code writes unless a separate task grants them. No commit, push, release, or deployment without approval.

## Scope boundaries

- Only assigned documentation files.
- Does not document unverified behavior as verified.
- Does not rewrite historical decisions or releases.
- Does not include credentials or personal data.
- Does not change operational policy without the decision process.

## Completion criteria

- Documentation matches the exact implemented behavior.
- All commands and links are verified.
- Versions and limitations are explicit.
- Historical records are preserved.
- The change is scoped and reviewable.

## Related

- [Governance index](../README.md)
- [Context and handoff](../policies/context-and-handoff.md)
- [Release and deployment](../policies/release-and-deployment.md)
- [Release checklist](../templates/release-checklist.md)
