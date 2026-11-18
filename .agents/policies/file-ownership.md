# File Ownership and Leases

**Enforcement:** `DOCUMENTED` + `ORCHESTRATOR_ENFORCED`; structural checks are `SCRIPT_VALIDATED`. The validator rejects active leases that reference unknown tasks, lack `WRITE_SCOPED`/`INTEGRATE` authority, or whose path is outside the owning task's `scope.allowed`. Containment uses component-aware prefixes: an allowed path matches the same path or any descendant separated by `/` (a trailing slash is the conventional way to mark a directory). Substring/prefix confusion such as `src/store` vs `src/store-old` is rejected. YAML entries do not prevent writes by themselves.

## Purpose

Maintain the single-writer invariant and a machine-readable record of which task may write which files.

## Single-writer invariant

At any moment, exactly one active task may hold write ownership of a specific file or overlapping path scope. Read access may be shared. Write access may not be shared.

Two tasks with overlapping write scopes are not allowed to execute concurrently unless the Orchestrator serializes their work or splits the scope into non-overlapping exact files.

## Registry

The authoritative registry is [ownership-registry.yaml](../state/ownership-registry.yaml). Each entry has:

- Exact path or scoped path.
- Task ID.
- Owner agent.
- Mode: `exclusive` or `scoped`.
- Workspace.
- Base commit.
- Status: `active`, `released`, `expired`, or `revoked`.
- Lease expiry.
- Dependencies.
- Integration status: `pending`, `integrated`, `rejected`, or `not_applicable`.

## Modes

- `exclusive` — owns one specific file path.
- `scoped` — owns a directory or path prefix; only one active scoped owner may cover a path.

A scoped ownership does not authorize writing outside the scope. It also does not authorize editing the registry itself.

## Conflict detection

Before granting ownership, compare the requested scope with all active entries:

1. Expand directory scopes.
2. Detect exact file collisions.
3. Detect path-prefix overlap.
4. Detect shared configuration and generated-file dependencies.
5. Reject or serialize conflicting assignments.

Examples:

- Task A owns `parsian-assistant/inc/store.php`.
- Task B requests `parsian-assistant/inc/` as a scoped write.
- These overlap and cannot be active concurrently.

## Leases

Every active ownership grant has:

- Task ID.
- Owner.
- Start time.
- Expiry.
- Last heartbeat.
- Status.

The Orchestrator may renew a lease while the task remains active. An expired lease does not automatically authorize another agent to overwrite the workspace. The Orchestrator must inspect the workspace and record a reassignment decision first.

## Shared files

Shared files include dependency manifests, lockfiles, bootstrap files, shared interfaces, global configuration, global CSS/JS entry points, and CI workflows.

Shared files have one designated owner per integration cycle. Tasks that need a change submit a proposal to the designated owner; they do not edit their own copies and rely on Git conflict resolution.

## Forbidden write scopes

Active ownership must not be granted for:

- `.git/`
- `node_modules/`
- `vendor/`
- `dist/`
- `wp-local/site/`
- `wp-local/mysql/`
- `wp-local/backups/`
- Any path outside the repository root.

## Release of ownership

Ownership is released when the task is integrated, rejected, cancelled, or reassigned. Release is recorded in the registry and event log. An integrated task's registry entry moves to `released`; it is not deleted.

## Enforcement limitations

- The validator rejects duplicate active paths, overlapping active scopes, invalid modes, missing fields, forbidden paths, and unknown task references.
- The validator cannot prevent a process from writing a file.
- Actual prevention requires worktree isolation, scoped tool permissions, and review. Those are not fully implemented by this baseline.

## Related

- [Governance index](../README.md)
- [Workspace isolation](workspace-isolation.md)
- [Task lifecycle](task-lifecycle.md)
- [Ownership schema](../schemas/ownership.schema.json)
- [Ownership registry](../state/ownership-registry.yaml)
