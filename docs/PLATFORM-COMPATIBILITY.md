# Platform Compatibility & Tested Versions

Last verified: **2026-11-09**

This repository is a WordPress theme (`parsian-music`) plus a companion plugin
(`parsian-assistant`). The supported platform is intentionally narrow and is
**only claimed where the full suite has actually run**.

## Supported matrix

| Component | Minimum | Recommended / tested | Notes |
|---|---|---|---|
| PHP | **8.4.27** | **8.5.12** | Both tested with `E_ALL`; 8.5 is the production target. |
| WordPress | **7.1** | **7.1.4** | Latest stable at verification time; Core checksums verified. |
| MySQL | **8.4 LTS** | **8.4.13** | InnoDB, `utf8mb4`, WP collation `utf8mb4_unicode_520_ci`. |
| MariaDB (current LTS) | **12.3 LTS** | **12.3.3** | Primary compatibility target; fresh `dbDelta` tested. |
| MariaDB (backward) | **11.8 LTS** | **11.8.9** | Previous LTS line retained as compatibility evidence. |
| WP-CLI | **2.12.0** | **2.12.0** | Latest stable; existing project PHAR checksum matches upstream. |
| Node.js (development only) | **26.13.0 LTS** | **26.13.0 LTS** | Required only for the jsdom popup tests. |
| npm (development only) | **12.2.2** | **12.2.2** | Node 26.13.0 bundles npm 11.20.0; project tests use npm 12.2.2. |
| Composer | — | **not required** | No `composer.json`/PHP dependencies; Composer 2.11.1 was verified but is not part of the runtime. |

### Upstream sources and release dates

| Component | Version | Release date | Source |
|---|---|---|---|
| PHP | 8.5.12 | 2026-10-22 | <https://www.php.net/releases/8_5_12.php> |
| PHP | 8.4.27 | 2026-10-22 | <https://www.php.net/releases/8_4_27.php> |
| WordPress | 7.1.4 | 2026-10-28 | <https://wordpress.org/download/releases/> |
| Node.js | 26.13.0 LTS | 2026-10-28 | <https://nodejs.org/en/download> |
| npm | 12.2.2 | 2026-11-04 | <https://www.npmjs.com/package/npm> |
| Composer | 2.11.1 | 2026-10-30 | <https://getcomposer.org/download/> |
| WP-CLI | 2.12.0 | 2025-05-07 | <https://github.com/wp-cli/wp-cli/releases/tag/v2.12.0> |
| MySQL | 8.4.13 | 2026-10-20 | <https://dev.mysql.com/doc/relnotes/mysql/8.4/en/> |
| MariaDB | 12.3.3 | 2026-10-27 (container publish) | <https://mariadb.org/download/> |
| MariaDB backward | 11.8.9 | 2026-10-27 (container publish) | <https://mariadb.org/download/> |

Do not treat this table as a permanent pin. Re-check upstream before changing
the matrix. The CI workflow prints the exact patch versions used on every run.

> Correction: the previous report incorrectly stated that PHP 8.5.11/8.4.26
> were the latest stable versions. php.net now publishes **8.5.12** and
> **8.4.27** (2026-10-22), including security fixes in the 8.5.12 release.
> PHP 8.6 remains a release candidate and was excluded.

## Required PHP extensions

The isolated 8.5.12/8.4.27 test images were built with and verified for:

`curl`, `json`, `mbstring`, `mysqli`, `pdo_mysql`, `openssl`, `zip`, `xml`,
`gd`, `fileinfo`, `sodium`, and `Zend OPcache`.

`gd` or `imagick` is needed by WordPress for image handling; the project code
itself does not call image APIs. `intl` is **not** used by the theme or plugin.
`opcache` is strongly recommended in production; flush it after plugin updates.

## Database notes

- Plugin tables are created with WordPress's `dbDelta()` and the site's
  `$wpdb->get_charset_collate()`.
- Tested schema on MySQL 8.4.13, MariaDB 12.3.3, and MariaDB 11.8.9:
  `wp_pa_conversations`, `wp_pa_messages`, `wp_pa_events`,
  `wp_pa_processed_updates`, `wp_pa_link_tokens`.
- All tables use InnoDB and `utf8mb4_unicode_520_ci` under WordPress 7.1.4.
- Unique constraints verified: `conversations(channel, external_key)`,
  `processed_updates(provider, update_id)`, `link_tokens(token_hash)`.
- `messages.meta` and `events.payload` are JSON encoded into `longtext`; no
  database JSON column or MySQL-only function is required, which keeps
  MariaDB compatible.
- No schema/version change was required by the platform upgrade
  (`pa_db_version = 1.0.0`).

## Non-Parsian themes

The plugin must keep working when the Parsian theme is inactive. It was tested
under:

- Twenty Twenty-Five 1.5
- Astra 4.14.2
- GeneratePress 3.6.1

`wp-local/tests/pa-theme-compat.php` verifies plugin boot, tables, REST routes,
conversation storage, engine fallback, Bale adapter option fallback, webhook,
admin/migration rendering, popup asset isolation, and recovery cron under each
theme. Visual styling of the popup remains optimized for Parsian; no theme
assets or popup script are loaded under the other themes.

## Development/test environment

The repository does **not** ship a database dump or a second WordPress tree.
For a reproducible clean-room run:

```bash
# PHP 8.5.12 / 8.4.27 and MySQL 8.4.13 / MariaDB 12.3.3 are provided by the
# pinned test images and service containers in .github/workflows/ci.yml.
# Locally, use an isolated WordPress install; do not modify wp-local/site or
# wp-local/mysql in place.
wp core download --version=7.1.4
wp config create --dbname=parsian_ci --dbuser=... --dbpass=... --dbhost=127.0.0.1
wp core install --url=http://127.0.0.1:8083 ...
ln -s "$PWD/parsian-music" wp-content/themes/parsian-music
ln -s "$PWD/parsian-assistant" wp-content/plugins/parsian-assistant
wp theme activate parsian-music
wp plugin activate parsian-assistant
```

Run the full suite from `wp-local` with the selected PHP binary and WP-CLI:

```bash
for t in pa-platform-test pa-migration-test pa-assistant-test pa-bale-test pa-security-test \
         pa-durability-test pa-webhook-test pm-1.6.0-smoke pa-theme-compat; do
  wp eval-file "tests/$t.php"
done

cd tests/js && npm ci && npm test && npm audit
```

For a direct `E_ALL` audit (bypassing WP-CLI's own vendor deprecations):

```bash
WP_LOAD=/path/to/wordpress/wp-load.php \
PA_TEST_FILE="$PWD/tests/pa-platform-test.php" \
php -d error_reporting=E_ALL -d display_errors=1 tests/eall-bootstrap.php
```

## Verification container images

| Image | Digest |
|---|---|
| `php:8.5.12-cli` | `sha256:50956f094e90a716d43bea28087d447555ad80510e386ab8c9c10d8c70d781a9` |
| `php:8.4.27-cli` | `sha256:37d80ce5e2b44a1a33e4d4aa4cf8e0aaf90532143803e70c3223bd2c1d2b062a` |
| `mysql:8.4.13` | `sha256:72c368d898861f3b3a07af0e6d690400f44f048799ed46822783d9224cc2c86d` |
| `mariadb:12.3.3` | `sha256:71541e69e0a1fbec2fa57d6b3b30d751c7847ca77ce66f5af4ffff48060c37c8` |
| `mariadb:11.8.9` | `sha256:8a4a99eff981d95c3ab12d019b82fe59725fb7761f1f9a5f155bc3f025528f4f` |

The extension build recipe is `tools/platform-lab/Containerfile`.

## Hosting limitations

- **WP-Cron is traffic-driven.** Use system cron for prompt queue processing;
  see `parsian-assistant/docs/CRON.md`.
- The webhook requires public HTTPS and a writable database/cron option.
- Real Bale delivery and live AI completion require real credentials and are
  not verified by mock tests.
- WP-CLI 2.12.0 (latest stable) emits a PHP 8.5 deprecation from its bundled
  `php-cli-tools/Colors.php` on some colorized commands. This is upstream
  vendor code, not project code; use `--no-color` where possible and see
  `docs/PLATFORM-UPGRADE-2026-11.md` for the exact limitation.
