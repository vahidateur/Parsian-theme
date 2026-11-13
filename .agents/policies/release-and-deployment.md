# Release and Deployment

**Enforcement:** `DOCUMENTED` + human approval. No release, tag, deployment, or CI gate is added by this baseline.

## Purpose

Define the evidence required before preparing or publishing a release.

## Release gates

| Gate | Required evidence |
|---|---|
| Requirements | Approved scope and acceptance criteria |
| Architecture | Approved contracts and ADRs |
| Implementation | Scoped diff and deliverables |
| Code review | Independent review with no blockers |
| QA | Required tests passing on the candidate SHA |
| Security | Findings dispositioned and `HIGH`/`BLOCKER` resolved or accepted |
| Integration | Conflict-free integrated branch |
| CI | All required checks passing on the exact candidate SHA |
| Release | Version, changelog, artifacts, checksums, and rollback plan |
| Deployment | Explicit human authorization and rollback plan |

A green CI result alone does not authorize release or deployment. A successful implementation report does not replace review evidence.

## Versioning and artifacts

- Record the version and the exact source commit.
- Generate a changelog from verified changes.
- Build artifacts from a clean checkout.
- Exclude tests, development dependencies, local databases, backups, and secrets.
- Record artifact hashes.
- Inspect archive contents before publication.
- Preserve previous release assets; never overwrite them.

## Approval

The human owner must explicitly approve:

- Release creation.
- Tag creation.
- Asset upload.
- Production deployment.
- Production database migration.
- Rollback or destructive recovery.

## Prohibited in this baseline

This governance bootstrap does not create a release, tag, deployment, or production change. See [the release checklist template](../templates/release-checklist.md) for the future process.

## Related

- [Governance index](../README.md)
- [DevOps Engineer](../roles/devops-engineer.md)
- [Security and data governance](security-and-data-governance.md)
- [Release checklist](../templates/release-checklist.md)
