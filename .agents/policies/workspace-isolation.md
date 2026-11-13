# Workspace Isolation

**Enforcement:** `DOCUMENTED` + `WORKSPACE_ENFORCED` when the Orchestrator actually creates isolated worktrees. No worktree is created by this baseline.

## Purpose

Prevent concurrent agents from sharing a writable working directory and ensure every task has a traceable base commit and branch.

## Mandatory isolation

Every implementation task must use:

- A dedicated Git worktree.
- A dedicated task branch.
- A recorded base commit.
- A bounded task scope.
- A recorded owner.
- A defined integration target.

Agents must not concurrently modify one shared writable working directory.

## Worktree location

The default worktree root is outside the repository working tree:

```text
../parsian-theme-worktrees/<task-id>/
```

An alternative location is allowed if it is recorded in the task contract and is not inside a tracked application directory. Do not create worktrees under `parsian-music/`, `parsian-assistant/`, `wp-local/site/`, or `wp-local/mysql/`.

## Branch naming

Use predictable names:

```text
task/<TASK-ID>-<slug>
integration/<milestone>
```

Examples:

```text
task/PS-001-settings-contracts
task/PS-002-settings-data
integration/persistent-settings
```

`main` is protected by policy. Agents do not write directly to `main`.

## Creation process

The Orchestrator:

1. Verifies the task is `READY`.
2. Records the base commit.
3. Creates the task branch from the approved base.
4. Creates the worktree outside the main working tree.
5. Grants file ownership.
6. Records the workspace, branch, base commit, and lease.
7. Records an assignment event.

## Agent rules

An agent may:

- Read approved shared project files.
- Modify files assigned to its task.
- Create task-local temporary files where permitted.
- Commit to its own task branch only when authorized.

An agent may not:

- Switch another agent's active branch.
- Modify another worktree.
- Write to `main` or the integration branch directly.
- Rebase or reset another agent's work.
- Force-push.
- Delete another workspace.
- Modify shared files outside its ownership boundary.

## Completion and cleanup

A worktree may be archived or removed only after:

1. Its changes are integrated or formally rejected.
2. Its final commit is recorded.
3. Its test and review evidence is stored.
4. No unresolved task depends on it.
5. Ownership is released.

Never run broad cleanup commands such as `git clean -fdx` without explicit approval. Preserve failed workspaces for recovery.

## Enforcement limitations

- Worktree separation is procedural until the Orchestrator and tool permissions enforce it.
- This baseline does not create worktrees, modify Git configuration, or add filesystem ACLs.
- The validator checks workspace fields in task contracts and ownership records, not actual process isolation.

## Related

- [Governance index](../README.md)
- [File ownership and leases](file-ownership.md)
- [Git and branch governance](git-and-branch-governance.md)
- [Failure and recovery](failure-and-recovery.md)
