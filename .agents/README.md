# Parsian Theme — Multi-Agent Governance

**Status:** Accepted governance baseline, 2026-11-13.
**Branch:** `feat/multi-agent-governance`
**Base:** `main` at `9193fd8e7bcb11ddfa3f1923d0d9efbc0c30c9e9`
**Decision namespace:** `MAG-01..MAG-08` (repository-specific; never reuse `OCG-*`, `D*`, `T-02`, `O-*`, or any sibling-repository namespace).

This directory is the single governance authority for multi-agent work in `vahidateur/Parsian-theme`. It is a new baseline, not an extension or copy of the governance system in `vahidateur/parsian-music-dashboard-opus`.

## Operating model

**One Central Orchestrator + Specialized Agents + Isolated Workspaces + Explicit File Ownership + Independent Verification + Controlled Integration.**

Roles are logical and model-agnostic. No model, vendor, or runtime is required by this governance. One model may perform multiple roles sequentially, but the permission boundaries of each role still apply. The Orchestrator coordinates execution. Specialized agents remain responsible for their deliverables. No agent receives unrestricted repository write access merely because of its role.

## Authority order

When instructions conflict, use this order:

1. Explicit human owner instructions and approvals.
2. This repository's `AGENTS.md` and accepted `MAG-*` decisions.
3. The current approved task contract.
4. Approved architecture contracts and ADRs for the task.
5. The policies in this directory.
6. General conventions and suggestions.

A lower item never overrides a higher item. The Orchestrator cannot expand product scope or approve its own implementation without independent review.

## Index

### Orchestration

- [Central Orchestrator](orchestrator.md)
- [Permissions and approvals](policies/permissions-and-approvals.md)
- [Execution state](policies/execution-state.md)
- [Enforcement and validation](policies/enforcement.md)

### Roles

- [Product Analyst](roles/product-analyst.md)
- [Solution Architect](roles/solution-architect.md)
- [Backend Engineer](roles/backend-engineer.md)
- [Frontend Engineer](roles/frontend-engineer.md)
- [Data and Migration Engineer](roles/data-migration-engineer.md)
- [Security Engineer](roles/security-engineer.md)
- [QA Engineer](roles/qa-engineer.md)
- [Code Reviewer](roles/code-reviewer.md)
- [DevOps Engineer](roles/devops-engineer.md)
- [Documentation Engineer](roles/documentation-engineer.md)

### Policies

- [Permissions and approvals](policies/permissions-and-approvals.md)
- [Workspace isolation](policies/workspace-isolation.md)
- [File ownership and leases](policies/file-ownership.md)
- [Task lifecycle](policies/task-lifecycle.md)
- [Dependency management](policies/dependency-management.md)
- [Architecture contracts and ADRs](policies/architecture-contracts.md)
- [Independent review](policies/independent-review.md)
- [QA and verification](policies/qa-and-verification.md)
- [Integration and conflict resolution](policies/integration-and-conflict-resolution.md)
- [Security and data governance](policies/security-and-data-governance.md)
- [Git and branch governance](policies/git-and-branch-governance.md)
- [Release and deployment](policies/release-and-deployment.md)
- [Failure and recovery](policies/failure-and-recovery.md)
- [Context and handoff](policies/context-and-handoff.md)
- [Execution state](policies/execution-state.md)
- [Enforcement and validation](policies/enforcement.md)
- [Initial pilot plan](policies/pilot-plan.md)

### Templates and schemas

- [Task contract template](templates/task-contract.yaml)
- [Scope-change template](templates/scope-change.yaml)
- [Handoff report template](templates/handoff-report.md)
- [Review report template](templates/review-report.md)
- [ADR template](templates/adr.md)
- [Release checklist](templates/release-checklist.md)
- [Task schema](schemas/task.schema.json)
- [Ownership schema](schemas/ownership.schema.json)
- [Execution-state schema](schemas/execution-state.schema.json)
- [Event schema](schemas/event.schema.json)

### State and decisions

- [Project state](state/project-state.yaml)
- [Ownership registry](state/ownership-registry.yaml)
- [Event log](state/event-log.yaml)
- [Protected-work register](state/protected-work.yaml)
- [Governance Decision Register](decisions/GOVERNANCE_DECISION_REGISTER.md)

### Validation

- [Governance validator](scripts/validate-governance.php)
- [Fixture guide](scripts/fixtures/README.md)

## Single-writer invariant

At any moment, exactly one active task may hold write ownership of a specific file. Overlapping active write scopes are prohibited. Read access may be shared. The normative rules are in [file ownership and leases](policies/file-ownership.md); the machine-readable registry is [state/ownership-registry.yaml](state/ownership-registry.yaml).

A YAML registry does not prevent a process from writing a file. Actual prevention requires worktree isolation, scoped tool permissions, review, and the validator. No stronger claim is made.

## Protected work and WP_002

The target repository has no `.agents` history and no `WP_002`. `WP_002` was a historical reference in a different repository; do not claim it is active here and do not fabricate protected paths.

The new register is [state/protected-work.yaml](state/protected-work.yaml). It starts empty. The owner must add explicit paths before any protected-work claim is valid.

## Current state

`state/project-state.yaml` is `PLANNING`:

- `MAG-01..MAG-08` are `ACCEPTED`.
- No pilot task is active.
- This baseline is committed on the feature branch; merge, release, and deployment require separate approval.
- The next required action is explicit authorization to start the Persistent Settings pilot.

## Enforcement levels

| Level | Meaning | Current examples |
|---|---|---|
| `DOCUMENTED` | Written rule only | Role responsibilities, ownership policy, lifecycle policy |
| `ORCHESTRATOR_ENFORCED` | Procedural coordination by the Orchestrator | Task assignment, ownership grant/release, integration order |
| `SCRIPT_VALIDATED` | Checked by `validate-governance.php` | Required files, links, decision IDs, ownership collisions, complete task contracts, task creation/transition chains, approval evidence, dependencies |
| `WORKSPACE_ENFORCED` | Enforced by worktree isolation and scoped agent access | Separate writable worktrees per task |
| `GIT_ENFORCED` | Enforced by Git configuration or branch protection | None added by this baseline |
| `GITHUB_ENFORCED` | Enforced by GitHub rulesets or branch protection | None verified or changed by this baseline |

No claim is made that multi-agent execution is technically enforced. The validator is structural. Worktree isolation and single-writer ownership are procedural until the corresponding workspace/tool restrictions are implemented and tested.

## Validation checklist

Run:

```bash
php .agents/scripts/validate-governance.php --all
php .agents/scripts/validate-governance.php --self-test
```

Acceptance evidence for this baseline:

- [x] All required governance documents exist.
- [x] Internal Markdown references resolve.
- [x] `MAG-01..MAG-08` are unique and accepted.
- [x] Schemas parse and required schema keys exist.
- [x] Task template and valid fixture pass.
- [x] Missing acceptance criteria, invalid permissions, and missing dependencies are rejected.
- [x] Duplicate and overlapping ownership fixtures are rejected.
- [x] Missing `TASK_CREATED`, duplicate creation, and invalid task-state transitions are rejected.
- [x] Active ownership leases are bound to known tasks with valid write/integration authority.
- [x] Approval evidence is checked for approved gates; gates are not inferred from status.
- [x] Protected-work register is empty and `WP_002` is explicitly marked not applicable.
- [x] `.agents/` is not ignored by Git.
- [x] No application runtime, release, database, backup, or dashboard file was modified.

**Bootstrap validation (2026-11-13):**

```text
php .agents/scripts/validate-governance.php --all --bootstrap
RESULT: PASS — governance (17 checks)
RESULT: PASS — self-test (82 checks)
```

The validator is structural. It does not execute application tests, create worktrees, make approval decisions, or prevent file writes. A Scope Guard regression suite (22 cases) and a governance CI workflow are prepared; neither is a GitHub-required check until the owner configures branch protection.

## Scope of this baseline

This baseline is governance engineering only. It does not implement Persistent Settings, modify application behavior, create a release, deploy, or commit/push. See [the initial pilot plan](policies/pilot-plan.md) for the next controlled step.
