# Governance Validator Fixtures

These fixtures exercise the zero-dependency validator. They are not active tasks or ownership grants.

Run:

```bash
php .agents/scripts/validate-governance.php --self-test
```

Expected outcomes:

| Fixture | Expected |
|---|---|
| `task-valid.yaml` | valid |
| `task-invalid-missing-acceptance.yaml` | rejected: empty acceptance criteria |
| `task-invalid-permission.yaml` | rejected: unknown permission |
| `task-invalid-owner.yaml` | rejected: unknown owner role |
| `task-invalid-dependency.yaml` | rejected: dependency `TASK-999` is not in the known task set |
| `task-invalid-scope-overlap.yaml` | rejected: allowed/forbidden path overlap |
| `task-transition-valid.yaml` | valid transition |
| `task-transition-invalid.yaml` | rejected transition |
| `ownership-valid.yaml` | valid non-overlapping ownership |
| `ownership-duplicate.yaml` | rejected: duplicate active path |
| `ownership-overlap.yaml` | rejected: scoped directory overlaps an exact file |
| `ownership-forbidden.yaml` | rejected: forbidden runtime path |
| `ownership-invalid-owner.yaml` | rejected: unknown owner role |
| `scope-change-valid.yaml` | valid |
| `scope-change-invalid.yaml` | rejected: missing required fields |
| `decisions-duplicate.md` | rejected: duplicate `MAG-01` identifier |
| `event-log-legacy-valid.yaml` | valid legacy event without transition fields |
| `event-log-transition-valid.yaml` | valid `TASK_TRANSITION` with complete state pair |
| `event-log-transition-missing-from.yaml` | rejected: missing `from_state` |
| `event-log-transition-missing-to.yaml` | rejected: missing `to_state` |
| `event-log-transition-invalid-state.yaml` | rejected: invalid lifecycle state |
| `event-log-transition-invalid-transition.yaml` | rejected: transition not allowed by the lifecycle table |
| `event-log-transition-fields-on-non-transition.yaml` | rejected: transition fields on a non-transition event |
| `event-log-unknown-property.yaml` | rejected: unknown event property |
| `event-log-transition-invalid-type.yaml` | rejected: non-string lifecycle state |
| `event-log-transition-missing-actor.yaml` | rejected: missing required actor |
| `event-log-transition-rejected-superseded.yaml` | rejected: terminal `REJECTED` has no `SUPERSEDED` transition |
| `event-log-transition-invalid-iso-timestamp.yaml` | rejected: timestamp is not ISO-8601 |

See [file ownership](../../policies/file-ownership.md), [task lifecycle](../../policies/task-lifecycle.md), and [enforcement](../../policies/enforcement.md).

Additional fixtures:

- `event-log-task-created-valid.yaml` — valid creation and first transition.
- `event-log-task-created-late-state.yaml` — creation in a non-PROPOSED state (invalid).
- `event-log-task-created-chronology.yaml` — creation after a transition (invalid).
- `event-log-task-created-missing.yaml` — transition without creation (invalid).
- `event-log-task-created-duplicate.yaml` — duplicate creation (invalid).
