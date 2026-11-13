# Git and Branch Governance

**Enforcement:** parts are `DOCUMENTED`; this baseline adds no Git hooks or branch protection. `main` protection is not `GITHUB_ENFORCED` by this work.

## Purpose

Keep branch history predictable, scoped, and recoverable.

## Branch naming

```text
feat/<feature-name>
fix/<issue-name>
refactor/<module-name>
test/<test-scope>
docs/<documentation-scope>
task/<TASK-ID>-<slug>
integration/<milestone>
```

Each task branch must:

- Have one clear purpose.
- Be based on a recorded commit.
- Contain only task-scoped changes.
- Preserve existing history.
- Avoid force-push.
- Avoid unrelated formatting changes.

## Commit policy

Commits should be small, focused, clearly named, and traceable to task IDs:

```text
<type>(<scope>): <description>
```

Examples:

```text
feat(settings): add persistent settings repository
fix(privacy): preserve erasure ownership after unlink
test(migration): cover secret-safe backup restoration
docs(governance): define multi-agent execution policy
```

## Protected main

- `main` is protected by policy from direct implementation writes.
- Agents work on task branches or worktrees.
- Only the approved integration workflow may prepare changes for `main`.
- Merge requires explicit human approval.
- Release and deployment require separate approval.

The repository's actual GitHub branch protection is not verified or changed by this baseline. Do not claim `GITHUB_ENFORCED` protection without evidence.

## Staging and history

- Stage explicit paths, never `git add .`.
- Do not commit generated archives, local databases, backups, secrets, or runtime files.
- Do not amend, rebase, reset, or rewrite published history without explicit approval.
- Do not force-push.
- Preserve failed workspaces for recovery.

## Repository boundary

Do not modify the sibling `parsian-music-dashboard-opus` repository. Do not import its branches, commits, governance, or WIP.

## Related

- [Governance index](../README.md)
- [Workspace isolation](workspace-isolation.md)
- [Failure and recovery](failure-and-recovery.md)
- [Permissions and approvals](permissions-and-approvals.md)
