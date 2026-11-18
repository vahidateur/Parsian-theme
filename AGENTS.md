# AGENTS.md — Parsian Theme Engineering Entry Point

This repository is the **Parsian Music Academy WordPress theme and companion assistant plugin**. It is a PHP/WordPress project, not the separate `parsian-music-dashboard-opus` repository.

## Governance status

A new repository-specific multi-agent governance baseline was bootstrapped on branch `feat/multi-agent-governance` from `main` at `9193fd8e7bcb11ddfa3f1923d0d9efbc0c30c9e9`.

- The bootstrap direction is approved by the repository owner.
- The detailed decisions `MAG-01..MAG-08` were **ACCEPTED by the Repository Owner on 2026-11-13**.
- No governance rule is technically enforced beyond the checks explicitly labelled `SCRIPT_VALIDATED`, `WORKSPACE_ENFORCED`, `GIT_ENFORCED`, or `GITHUB_ENFORCED`.
- The current execution state is `PLANNING`.

## Read order

1. [.agents/README.md](.agents/README.md) — governance index, authority, enforcement labels.
2. [.agents/orchestrator.md](.agents/orchestrator.md) — Central Orchestrator authority and modes.
3. [.agents/policies/permissions-and-approvals.md](.agents/policies/permissions-and-approvals.md) — permission and human-approval gates.
4. [.agents/policies/file-ownership.md](.agents/policies/file-ownership.md) — single-writer ownership and leases.
5. [.agents/policies/workspace-isolation.md](.agents/policies/workspace-isolation.md) — worktrees and task isolation.
6. [.agents/policies/task-lifecycle.md](.agents/policies/task-lifecycle.md) — task states and evidence gates.
7. [.agents/state/project-state.yaml](.agents/state/project-state.yaml) — current execution state.
8. [.agents/decisions/GOVERNANCE_DECISION_REGISTER.md](.agents/decisions/GOVERNANCE_DECISION_REGISTER.md) — `MAG-01..MAG-08`.

## Non-negotiable rules

- **One writer per file.** Exactly one active task may hold write ownership of a specific file or overlapping path scope.
- **Isolated workspaces.** Implementation agents work in dedicated Git worktrees and task branches. No agent writes directly to `main`.
- **One accountable owner per task.** Every task has a task contract with an owner, scope, base commit, acceptance criteria, and required evidence.
- **Independent verification.** An implementer cannot be the sole reviewer of their own work. QA and security reviews are separate responsibilities.
- **Evidence over claims.** No task reaches `VERIFIED` without tests, review evidence, and a scope check against the exact commit under review.
- **Human approval for protected actions.** Commit, merge, push, protected-work changes, production deployment, production database migration, irreversible deletion, secret rotation, release/tag publication, and governance-policy changes require explicit human approval. Approval cannot be bypassed by splitting a prohibited action into smaller tasks.
- **No silent scope expansion.** Adding files or changing an approved contract requires a scope-change or contract-change proposal.
- **Preserve protected work.** The protected-work register starts empty. `WP_002` does not exist in this repository; do not claim it as active. See [.agents/state/protected-work.yaml](.agents/state/protected-work.yaml).

## Repository boundary

Do **not** modify the sibling `parsian-music-dashboard-opus` repository or its current uncommitted work. Do not copy its `OCG-*` decisions, governance documents, task records, or local `.agents/skills` into this repository. This repository uses its own `MAG-*` namespace.

## Application safety

- Do not modify theme or assistant runtime behavior while working on governance.
- Do not start the Persistent Settings implementation. The pilot plan is documentation only.
- Do not modify releases, tags, databases, backups, `wp-local/site/`, or `wp-local/mysql/`.
- Do not commit or push without explicit approval.

## Validation

Run the zero-dependency validator from the repository root:

```bash
php .agents/scripts/validate-governance.php --all
php .agents/scripts/validate-governance.php --self-test
```

The validator checks document presence, internal references, decision identifiers, schema/template shape, complete task contracts, task creation/transition chains, ownership authority and collisions, approval evidence, dependencies, protected-work state, and whether `.agents/` is accidentally ignored. It is a structural validator, not a full orchestration engine. A Scope Guard regression suite and governance CI workflow are prepared but are not GitHub-required checks until the owner configures branch protection.

## Enforcement labels

Every normative document identifies its enforcement level. The permitted labels are:

- `DOCUMENTED` — written policy, not mechanically checked.
- `ORCHESTRATOR_ENFORCED` — enforced procedurally by the Orchestrator.
- `SCRIPT_VALIDATED` — checked by `validate-governance.php`.
- `WORKSPACE_ENFORCED` — enforced by worktree isolation and scoped access.
- `GIT_ENFORCED` — enforced by Git configuration or repository protection.
- `GITHUB_ENFORCED` — enforced by GitHub branch/ruleset settings.

Do not claim a stronger level than the evidence supports.
