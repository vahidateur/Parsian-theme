# Platform Upgrade Report — November 2026

Branch: `feat/platform-current-stable`

Baseline: `main @ 2e4479dae1531c9ff470d60a8b3540856c85dd27`

Verification date: **2026-11-09**

## Baseline (before changes)

| Component | Before |
|---|---|
| PHP CLI (local service) | 8.5.4 |
| PHP CLI (`php` default) | 8.4.23 |
| WordPress | 7.1.4 |
| MySQL | 8.4.11 LTS |
| MariaDB | not installed (host) |
| WP-CLI | 2.12.0 |
| Node.js / npm | 22.22.3 / 10.9.8 |
| Composer | 2.10.3, unused (no composer.json) |
| JS tests | only `/tmp/opencode/jsdom-test/` |
| CI workflows | none |
| Theme `Requires PHP` | 7.4 |
| Plugin PHP requirement | none declared |

Repository state: clean tracked tree; only the accepted 19 untracked plugin
files and 6 PHP test files. No commit, tag, release, or push was performed.

## Corrected final tested matrix

The previous gate tested PHP 8.5.11/8.4.26, MariaDB 11.8.9 and npm 11.20.0.
This correction gate replaced those selections with the current patch/LTS
releases below. The prior MariaDB 11.8.9 line is retained as
backward-compatibility evidence.

| Component | Final installed/tested | Source | Result |
|---|---|---|---|
| PHP 8.5 | **8.5.12** | php.net release, 2026-10-22 | 169/169; `E_ALL` clean |
| PHP 8.4 | **8.4.27** | php.net release, 2026-10-22 | 169/169; `E_ALL` clean |
| WordPress | **7.1.4** | wordpress.org, 2026-10-28 | Already latest; checksums + DB check verified |
| MySQL | **8.4.13 LTS** | MySQL 8.4 notes, 2026-10-20 | 169/169 per PHP version; fresh schema |
| MariaDB (primary) | **12.3.3 LTS** | mariadb.org, 2026-10-27 image publish | 169/169 per PHP version; fresh `dbDelta` |
| MariaDB (backward) | **11.8.9 LTS** | mariadb.org, retained | 169/169 per PHP version |
| WP-CLI | **2.12.0** | GitHub release, 2025-05-07 | Latest; PHAR SHA-512 matched |
| Node.js | **26.13.0 LTS** | nodejs.org, 2026-10-28 | 8/8 popup tests |
| npm | **12.2.2** | npm registry | `npm ci`, 8/8, 0 audit findings |
| Composer | **2.11.1** (verified, not required) | getcomposer.org, 2026-10-30 | No PHP dependencies |

## Components already current

- WordPress 7.1.4 was already the latest stable; `wp core verify-checksums`,
  `wp core check-update`, and `wp core update-db` (DB version 61833) passed.
- WP-CLI 2.12.0 was already the latest stable; the existing
  `wp-local/wp-cli.phar` SHA-512 matches the upstream release exactly.
- Composer is not used by the project; no Composer manifest was introduced.

## Components upgraded in the lab/test toolchain

- PHP 8.5.12 and 8.4.27 isolated CLI images (mysqli, pdo_mysql, mbstring,
  curl, openssl, zip, xml, gd, fileinfo, sodium, opcache).
- MySQL 8.4.13, MariaDB 12.3.3 (primary LTS), and MariaDB 11.8.9 (backward
  compatibility) isolated servers.
- Node 26.13.0 LTS and npm 12.2.2.
- Popup jsdom suite moved into the repository with `jsdom@31.0.0`,
  `package-lock.json`, and `npm ci`/`npm audit` workflow.
- Theme/plugin compatibility declarations raised to PHP 8.4.27 / WordPress 7.1.
- CI workflow added with exact PHP 8.4.27/8.5.12, WordPress 7.1.4, MySQL
  8.4.13, MariaDB 12.3.3 and 11.8.9, Node 26.13.0, npm 12.2.2, lint, tests,
  npm audit, CodeQL, and production archives.

## Breaking changes and fixes

- No PHP 8.4/8.5 source incompatibilities were found in project code. The
  `E_ALL` runs under both corrected patch releases produced zero project
  deprecations, warnings, notices, or fatals.
- No WordPress 7.1 API breakage was found. Theme/plugin activation, admin
  pages, REST, cron, frontend rendering, and persistence passed.
- No database migration was needed; schema and indexes are identical on
  MySQL 8.4.13, MariaDB 12.3.3, and MariaDB 11.8.9.
- Node 22 → 26 required a new lockfile and in-repo test path; behavior is 8/8.
- npm 11.20.0 → 12.2.2 is compatible with Node 26.13.0, preserves the
  lockfile hash, and passes `npm ci`, tests, and audit.
- The old PHP 7.4 declaration was removed; the minimum is now **8.4.27**
  (security-patched 8.4 branch), with 8.5.12 recommended.

## Test evidence

Environment: fresh WordPress 7.1.4, theme + plugin only. Reported counts are
from isolated fixture resets; an initial run with stale lab fixtures was
discarded and re-run cleanly.

```text
PHP 8.5.12 + MySQL 8.4.13:
pa-platform-test        23 passed, 0 failed
pa-migration-test       16 passed, 0 failed
pa-assistant-test       12 passed, 0 failed
pa-bale-test            12 passed, 0 failed
pa-security-test        14 passed, 0 failed
pa-durability-test      24 passed, 0 failed
pa-webhook-test         37 passed, 0 failed
pm-1.6.0-smoke          15 passed, 0 failed
pa-theme-compat         16 passed, 0 failed
TOTAL                  169 passed, 0 failed

PHP 8.4.27 + MySQL 8.4.13: identical 169/169.
PHP 8.5.12 + MariaDB 12.3.3: identical 169/169.
PHP 8.4.27 + MariaDB 12.3.3: identical 169/169.
PHP 8.5.12 + MariaDB 11.8.9: identical 169/169 (backward compatibility).
PHP 8.4.27 + MariaDB 11.8.9: identical 169/169 (backward compatibility).
E_ALL (both PHP versions, MySQL 8.4.13 and MariaDB 12.3.3): 169/169, zero
project deprecations/warnings.
Node 26.13.0 / npm 12.2.2: 8 passed, 0 failed; npm audit 0 vulnerabilities.
Theme compatibility: 16/16 under Parsian, Twenty Twenty-Five, Astra, GeneratePress.
```

The previously reported 138 tests remain green (130 PHP + 8 JS). The new
platform/theme tests add 39 assertions, giving **169 PHP assertions per
matrix cell**. The 8 popup DOM tests are reported separately under Node
26.13.0/npm 12.2.2 and are **not** included in any PHP-only cell total.

Additional checks: PHP lint over all project PHP files, `node --check`,
`git diff --check`, Core checksums, `wp db check`, admin page render, frontend
HTTP 200, REST 200, anonymous status 401, chat-without-nonce 403, webhook bad
secret 403, plugin deactivation/reactivation with data intact.

## CI workflow

`.github/workflows/ci.yml` has five jobs:

- `php`: exact PHP 8.4.27/8.5.12 matrix, WordPress 7.1.4, MySQL 8.4.13, all
  PHP suites, third-party theme compatibility, activation/data persistence,
  E_ALL deprecation audit, lint, checks.
- `mariadb`: PHP 8.4.27 and 8.5.12 on **MariaDB 12.3.3 (current LTS)**,
  plus PHP 8.5.12 on **MariaDB 11.8.9 (backward compatibility)**, all PHP
  suites.
- `node`: Node 26.13.0, npm 12.2.2, `npm ci`, 8 popup tests, `npm audit`.
- `codeql`: JavaScript/TypeScript analysis (CodeQL has no PHP language).
- `package`: production theme/plugin ZIPs with no development files.

All actions are pinned to full commit SHAs (comments record the release):
checkout v7.0.1, setup-php 2.40.0, setup-node v7.1.0, CodeQL v4.39.3,
upload-artifact v7.0.2. The workflow also warns when upstream WordPress is
newer than the pinned, verified version.

## Remaining risks / limitations

- **Real Bale/AI integration is still unverified.** No production credentials
  were used; Bale hosts were unreachable in the sandbox. All delivery/AI
  evidence is mocked.
- **WP-CLI 2.12.0 vendor deprecation:** its bundled `php-cli-tools` emits
  `Using null as an array offset is deprecated` on PHP 8.5 for some colorized
  commands. This is upstream PHAR code, not project code. Project tests were
  run through a direct WordPress bootstrap with `E_ALL` and were clean.
- WP-Cron remains traffic-driven; system cron is required for prompt queue
  processing (see `parsian-assistant/docs/CRON.md`).
- The existing personal `wp-local/site` and `wp-local/mysql` were **not**
  modified. The local services still run PHP 8.5.4/MySQL 8.4.11; the tested
  PHP 8.5.12/8.4.27 environment is isolated. Updating the personal local
  service requires explicit approval.
- MySQL/MariaDB dumps are not portable across every collation without
  conversion; MariaDB testing used a lab-only collation conversion.
- No Composer dependency audit is applicable because the project has no
  Composer manifest.
- The GitHub Actions workflow is validated with `actionlint` and every job's
  steps were reproduced locally with Podman, but it has not executed on
  GitHub yet because no commit/push/PR was created.
