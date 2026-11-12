# Migration & Rollback Guide — v1.6.0 randomized slug → `parsian-music`

Tested on PHP 8.4.27/8.5.12, WordPress 7.1.4, MySQL 8.4.13, MariaDB 12.3.3 (primary) and MariaDB 11.8.9 (backward compatibility).
The migration is idempotent and never deletes the source option.

## When this applies

Only when a `theme_mods_parsian-music-<suffix>` option exists (v1.6.0
installed from the root-level ZIP). Canonical `parsian-music` installs
report `clean` and need nothing — the screen says so explicitly.

## Procedure (admin only, `manage_options` + nonce per action)

1. Open **Assistant → مهاجرت تنظیمات** (under **Parsian**, or Settings if the
   theme is inactive).
2. Pick the source slug from the dropdown (every candidate shows its key
   count; the canonical slug is never listed). Press **پیش‌نمایش**.
3. Review: **supported** non-secret keys (in the current theme schema),
   **conflicts** (destination already has a different value — kept, never
   overwritten), **credentials** (never migrated), and **unsupported** keys
   (never migrated).
4. Press **اجرای مهاجرت**. A backup option
   `pa_migration_backup_<timestamp>_<hash>` is written first; only
   empty/default destination keys are filled; the source option is left
   untouched. Re-running without `force` is a verified no-op once the
   migrated keys read back correctly.

## Credentials are never migrated or backed up

The snapshot is allowlisted to non-secret fields from the current theme
schema. Secret-typed fields and secret-like names (`*token*`, `*api_key*`,
`*secret*`, `*password*`, webhook/signing keys, credentials) are excluded.
Unknown keys are reported but are not copied.

After migration, provision any skipped credentials through the secure
settings flow (**Parsian → اعلان‌ها** for Bale/Rubika,
**Parsian → دستیار هوشمند** for the AI key) or `wp-config.php` constants.
The migration report lists only key names, never values.

## Backup retention

- Default retention: **30 days**.
- Maximum retained backups: **10** (newest kept).
- `pa_prune` calls `pa_migration_prune_backups()` daily, so expired/excess
  backups are removed even when no migration runs.
- Legacy backups created by older plugin versions are sanitized in place on
  upgrade: secret fields are removed, non-secret rollback data is preserved,
  and missing `created_at`/`dest_keys_before` metadata is added. Cleanup is
  idempotent and never logs values.

## Rollback

On the same screen, paste the backup option name into **بازیابی** and
submit. This restores the destination's non-secret snapshot and clears the
completion record so migration can run again. **Currently configured
credentials are preserved** — a redacted or empty backup value can never
overwrite a live token/API key. The source option is never deleted by any
step; deleting the old theme directory or its option, if ever desired, is a
manual, explicitly confirmed administrator action outside this tool.

Do not dump backup options to the console as JSON or SQL. Backups are not
designed as credential escrow; if an audit copy is required, treat the
database as sensitive and use your normal encrypted backup process.

## Recovery notes

- Partial success is reported per key (`migrated`, `skipped_conflicts`,
  `preserved_unsupported`, `secrets_skipped`) — re-run after resolving
  conflicts; completed keys are skipped, not duplicated.
- `pa_migration_status()` returns `clean` when no candidates remain.
- If a credential was skipped, confirm `getMe` from
  **Assistant → بررسی اتصال** after provisioning it.
