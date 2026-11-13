# Governance Decision Register — `MAG` namespace

**Recorded:** 2026-11-13
**Repository:** `vahidateur/Parsian-theme`
**Branch:** `feat/multi-agent-governance`
**Base:** `main` at `9193fd8e7bcb11ddfa3f1923d0d9efbc0c30c9e9`
**Scope:** repository-specific multi-agent operating governance.

> This is a new, independent namespace. It does not reuse or amend `OCG-*`, `D*`, `T-02`, `O-*`, or any decision namespace from the sibling `parsian-music-dashboard-opus` repository. The sibling is out of scope and must not be modified.

## Status vocabulary

- `PROPOSED` — documented but not in force.
- `ACCEPTED` — approved by the Repository Owner and in force.
- `SUPERSEDED` — replaced by a later decision.
- `REJECTED` — not adopted.

The detailed decisions below were **ACCEPTED by the Repository Owner on 2026-11-13**.

---

## MAG-01 — Central Orchestrator authority and operating modes
- **Status:** ACCEPTED
- **Approval state:** Approved and in force.
- **Approved by:** Repository Owner
- **Approval date:** 2026-11-13
- **Context:** Multiple agents require one coordination authority for task assignment, ownership, dependencies, integration, and evidence.
- **Decision:** Establish one logical Central Orchestrator with the authority and prohibitions in [orchestrator.md](../orchestrator.md), and the eight modes `DISCOVERY`, `PLANNING`, `ARCHITECTURE`, `EXECUTION`, `INTEGRATION`, `VERIFICATION`, `RELEASE_PREPARATION`, and `AWAITING_APPROVAL`.
- **Rationale:** A single source of execution state prevents competing assignments, overlapping writes, and unverified integration.
- **Consequences:** The Orchestrator coordinates but does not implement by default and cannot merge, release, deploy, or perform destructive operations without human approval.
- **Revision history:** 2026-11-13 — initial proposal; 2026-11-13 — accepted by Repository Owner.

## MAG-02 — Specialized agent roles and permissions
- **Status:** ACCEPTED
- **Approval state:** Approved and in force.
- **Approved by:** Repository Owner
- **Approval date:** 2026-11-13
- **Context:** Implementation, product analysis, architecture, security, QA, review, operations, and documentation require separate responsibilities.
- **Decision:** Adopt the ten roles in [roles/](../roles) and the permission levels in [permissions-and-approvals.md](../policies/permissions-and-approvals.md). Permissions are task-scoped and revocable.
- **Rationale:** Separation of duties prevents an implementer from being the sole approver of their own work.
- **Consequences:** One model may perform multiple roles sequentially, but role permissions and review independence remain separate.
- **Revision history:** 2026-11-13 — initial proposal; 2026-11-13 — accepted by Repository Owner.

## MAG-03 — Single-writer ownership and leases
- **Status:** ACCEPTED
- **Approval state:** Approved and in force.
- **Approved by:** Repository Owner
- **Approval date:** 2026-11-13
- **Context:** Parallel agents must not write the same file or overlapping path scope.
- **Decision:** Enforce one active write owner per file/scope through [file-ownership.md](../policies/file-ownership.md) and the machine-readable [ownership registry](../state/ownership-registry.yaml). Ownership is time-bounded and tied to a task, workspace, and base commit.
- **Rationale:** File-level ownership makes conflicts visible before integration.
- **Consequences:** YAML entries are procedural; the validator detects duplicates and overlap but does not prevent writes. Worktree isolation and tool restrictions are required for stronger enforcement.
- **Revision history:** 2026-11-13 — initial proposal; 2026-11-13 — accepted by Repository Owner.

## MAG-04 — Isolated workspaces and Git worktrees
- **Status:** ACCEPTED
- **Approval state:** Approved and in force.
- **Approved by:** Repository Owner
- **Approval date:** 2026-11-13
- **Context:** A shared writable working directory creates races and untraceable changes.
- **Decision:** Require a dedicated Git worktree and task branch per implementation task, as specified in [workspace-isolation.md](../policies/workspace-isolation.md). Write to `main` only through the approved integration workflow.
- **Rationale:** Isolation preserves a recorded base commit and prevents one agent from switching or modifying another agent's workspace.
- **Consequences:** Worktree creation and cleanup are Orchestrator procedures. No worktree is created by this baseline.
- **Revision history:** 2026-11-13 — initial proposal; 2026-11-13 — accepted by Repository Owner.

## MAG-05 — Task contracts, lifecycle, and dependencies
- **Status:** ACCEPTED
- **Approval state:** Approved and in force.
- **Approved by:** Repository Owner
- **Approval date:** 2026-11-13
- **Context:** Tasks need explicit scope, acceptance criteria, dependencies, and evidence before work starts.
- **Decision:** Adopt the task contract in [task-contract.yaml](../templates/task-contract.yaml), the state machine in [task-lifecycle.md](../policies/task-lifecycle.md), and dependency rules in [dependency-management.md](../policies/dependency-management.md).
- **Rationale:** A canonical contract and lifecycle make scope and completion auditable.
- **Consequences:** A task cannot reach `VERIFIED` without independent review, QA, security disposition, and exact-commit evidence.
- **Revision history:** 2026-11-13 — initial proposal; 2026-11-13 — accepted by Repository Owner.

## MAG-06 — Independent QA, review, and security gates
- **Status:** ACCEPTED
- **Approval state:** Approved and in force.
- **Approved by:** Repository Owner
- **Approval date:** 2026-11-13
- **Context:** Tests and self-review by the implementer are insufficient acceptance evidence.
- **Decision:** Require independent code review, QA verification, and security review where applicable, using [independent-review.md](../policies/independent-review.md), [qa-and-verification.md](../policies/qa-and-verification.md), and [security-and-data-governance.md](../policies/security-and-data-governance.md).
- **Rationale:** Independent evidence reduces regression, security, and self-approval risk.
- **Consequences:** The implementer cannot be the sole reviewer. Review evidence must reference the exact commit under acceptance.
- **Revision history:** 2026-11-13 — initial proposal; 2026-11-13 — accepted by Repository Owner.

## MAG-07 — Controlled integration and human approval
- **Status:** ACCEPTED
- **Approval state:** Approved and in force.
- **Approved by:** Repository Owner
- **Approval date:** 2026-11-13
- **Context:** Integrating unverified branches or performing protected operations can corrupt shared code or production state.
- **Decision:** Integrate only through a controlled integration branch under Orchestrator coordination, per [integration-and-conflict-resolution.md](../policies/integration-and-conflict-resolution.md). Require explicit human approval for the gates in [permissions-and-approvals.md](../policies/permissions-and-approvals.md) and [release-and-deployment.md](../policies/release-and-deployment.md).
- **Rationale:** A controlled integration order preserves review evidence and prevents unauthorized release or deployment.
- **Consequences:** The Orchestrator cannot merge to `main`, publish a release/tag, deploy, or perform destructive operations without owner approval.
- **Revision history:** 2026-11-13 — initial proposal; 2026-11-13 — accepted by Repository Owner.

## MAG-08 — Enforcement mechanisms and known limitations
- **Status:** ACCEPTED
- **Approval state:** Approved and in force.
- **Approved by:** Repository Owner
- **Approval date:** 2026-11-13
- **Context:** Documentation alone does not enforce single-writer ownership, approvals, or workspace isolation.
- **Decision:** Provide a zero-dependency structural validator for documents, links, decisions, task fields, ownership collisions, dependencies, transitions, protected work, and ignore rules, as specified in [enforcement.md](../policies/enforcement.md). Label all other controls honestly. No CI enforcement, branch protection, filesystem ACL, or tool-level approval gate is added by this baseline.
- **Rationale:** Machine-checkable invariants reduce structural errors without overstating enforcement.
- **Consequences:** The validator is `SCRIPT_VALIDATED` only. Worktree isolation and single-writer behavior remain `ORCHESTRATOR_ENFORCED`/`DOCUMENTED` until stronger mechanisms are implemented and tested.
- **Revision history:** 2026-11-13 — initial proposal; 2026-11-13 — accepted by Repository Owner.
