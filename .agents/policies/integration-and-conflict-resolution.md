# Integration and Conflict Resolution

**Enforcement:** `DOCUMENTED` + `ORCHESTRATOR_ENFORCED`. No integration branch is created by this baseline.

## Purpose

Combine verified task branches into a controlled integration candidate without losing ownership, review, or test evidence.

## Integration sequence

1. Verify the task's base commit.
2. Verify task ownership and scope.
3. Confirm all required deliverables exist.
4. Review the task branch diff.
5. Check dependency readiness.
6. Integrate into a dedicated integration branch.
7. Resolve conflicts under a designated owner.
8. Run affected tests.
9. Run the complete regression suite.
10. Obtain independent review of the integrated result.
11. Mark the integrated task `VERIFIED`.

## Integration branches

Use one integration branch per cohesive feature or milestone:

```text
integration/<milestone>
```

Do not integrate partially completed task branches into `main`. Only verified integration results may become Pull Request candidates. A human owner must authorize the final merge.

## Conflict resolution

A Git conflict must never be resolved by automatically choosing one agent's version. The Orchestrator must:

- Identify the files in conflict.
- Identify their task owners.
- Understand both intended changes.
- Reconcile behavior against approved contracts.
- Assign a single conflict-resolution owner.
- Run all impacted tests.
- Record the resolution.

If the conflict changes an architecture contract, the Solution Architect must re-evaluate it and the contract must be re-approved.

## Integration ownership

The integration branch has one active writer at a time. Integration agents do not inherit implementation ownership. They receive an explicit `INTEGRATE` permission and a temporary integration ownership lease.

## Rebase and merge restrictions

- Do not force-push.
- Do not rewrite published history.
- Do not merge an unreviewed branch.
- Do not resolve conflicts by discarding another task's work.
- Do not integrate while a blocking review finding is open.

## Related

- [Governance index](../README.md)
- [Task lifecycle](task-lifecycle.md)
- [Independent review](independent-review.md)
- [Release and deployment](release-and-deployment.md)
- [Failure and recovery](failure-and-recovery.md)
