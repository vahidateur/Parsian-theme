# Failure and Recovery

**Enforcement:** `DOCUMENTED` + `ORCHESTRATOR_ENFORCED`.

## Purpose

Define safe behavior when an agent fails, times out, exceeds scope, or becomes unavailable.

## Failure response

The Orchestrator must:

1. Freeze the affected task's write ownership.
2. Preserve the workspace and commits.
3. Record the failure event.
4. Identify uncommitted and committed changes.
5. Evaluate whether the work is recoverable.
6. Assign a replacement only after inspecting the workspace.
7. Transfer the complete task context.
8. Re-run affected tests.

Never delete a failed agent's workspace before recovery or archival.

## Scope violations

If an agent writes outside its scope or overlaps another owner:

- Stop the agent.
- Revoke write access.
- Preserve the workspace.
- Identify every affected path.
- Notify the owning tasks.
- Revert or re-integrate only under a recorded decision.
- Add a regression check or ownership validation if the violation reveals a gap.

## Cancellation

A cancelled task must:

- Release active ownership.
- Record its final repository state.
- Mark dependent tasks appropriately.
- Preserve useful work for review.
- Leave no active lease.

## Timeouts and unavailable agents

A timed-out or unavailable agent's lease may be marked `expired` only after inspection. Expiry does not authorize overwriting the workspace. The Orchestrator records the reassignment decision and grants a new lease.

## Integration failure

If integration tests fail:

- Keep the integration branch.
- Do not merge to `main`.
- Record the failing command and output.
- Route the failure to the owning task or integration owner.
- Re-run the full affected matrix after a fix.

## Related

- [Governance index](../README.md)
- [Workspace isolation](workspace-isolation.md)
- [File ownership and leases](file-ownership.md)
- [Execution state](execution-state.md)
- [Context and handoff](context-and-handoff.md)
