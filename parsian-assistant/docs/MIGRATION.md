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
3. Review: **supported** keys (in the current theme schema), **conflicts**
   (destination already has a different value — kept, never overwritten),
   **unsupported** keys (preserved in the backup only).
4. Press **اجرای مهاجرت**. A backup option
   `pa_migration_backup_<timestamp>_<hash>` snapshots source + destination
   first; only empty/default destination keys are filled; the source option
   is left untouched. Re-running without `force` is a verified no-op once
   the migrated keys read back correctly.

## Rollback

On the same screen, paste the backup option name into **بازیابی** and
submit. This restores the exact destination snapshot and clears the
completion record so migration can run again. The source option is never
deleted by any step — deleting the old theme directory or its option, if
ever desired, is a manual, explicitly confirmed administrator action
outside this tool.

## Recovery notes

- Partial success is reported per key (`migrated`, `skipped_conflicts`,
  `preserved_unsupported`) — re-run after resolving conflicts; completed
  keys are skipped, not duplicated.
- `pa_migration_status()` returns `clean` when no candidates remain.
- Backups are plain options; export them before major work
  (`wp option get pa_migration_backup_… --format=json`).
