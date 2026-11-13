# Enforcement and Validation

**Enforcement:** `SCRIPT_VALIDATED` for the checks implemented by `validate-governance.php`; all other claims are explicitly `DOCUMENTED`, `ORCHESTRATOR_ENFORCED`, or not enforced.

## Purpose

State honestly which governance rules are mechanically checked and which depend on human/Orchestrator discipline.

## Validator

The zero-dependency validator is [validate-governance.php](../scripts/validate-governance.php). It checks:

- Required governance documents exist.
- Internal Markdown references resolve.
- `MAG-01..MAG-08` are present, unique, and structurally complete.
- JSON schemas parse and expose required keys.
- Task template and fixtures satisfy required fields.
- Missing acceptance criteria, invalid permissions, and missing dependencies are rejected.
- Duplicate and overlapping ownership fixtures are rejected.
- Invalid task-state transitions are rejected.
- Ownership paths are relative and not in forbidden runtime/build directories.
- Protected-work register structure and `WP_002` distinction.
- `.agents/` is not ignored by Git.
- Optionally, bootstrap changes stay inside `AGENTS.md` and `.agents/`.

Run:

```bash
php .agents/scripts/validate-governance.php --all
php .agents/scripts/validate-governance.php --self-test
```

## Enforcement matrix

| Rule | Current enforcement |
|---|---|
| Required documents and links | `SCRIPT_VALIDATED` |
| Decision IDs and required fields | `SCRIPT_VALIDATED` |
| Task required fields and acceptance criteria | `SCRIPT_VALIDATED` when a task file is supplied |
| Permission enum | `SCRIPT_VALIDATED` in task contracts |
| Dependency ID shape and self-reference | `SCRIPT_VALIDATED`; full graph is `ORCHESTRATOR_ENFORCED` |
| Ownership duplicate/overlap/forbidden paths | `SCRIPT_VALIDATED` on the registry |
| Task-state transition table | `SCRIPT_VALIDATED` in the validator self-test; runtime transitions are `ORCHESTRATOR_ENFORCED` |
| Single-writer behavior | `DOCUMENTED` + `ORCHESTRATOR_ENFORCED`; not filesystem-enforced |
| Worktree isolation | `DOCUMENTED`; no worktree/ACL enforcement added |
| Human approval gates | `DOCUMENTED`; no tool-level gate added |
| Git/GitHub branch protection | Not verified; not `GIT_ENFORCED`/`GITHUB_ENFORCED` |
| Secret isolation | `DOCUMENTED`; no secret scanner added |

## Limitations

- YAML ownership entries do not prevent another process from editing a file.
- The validator is structural; it does not execute tests or inspect application behavior.
- The validator does not enforce approvals, worktrees, or tool permissions.
- No CI job is added by this baseline.
- No claim is made that multi-agent execution is technically enforced.

## Related

- [Governance index](../README.md)
- [Central Orchestrator](../orchestrator.md)
- [Permissions and approvals](permissions-and-approvals.md)
- [Validator](../scripts/validate-governance.php)
- [Fixture guide](../scripts/fixtures/README.md)
