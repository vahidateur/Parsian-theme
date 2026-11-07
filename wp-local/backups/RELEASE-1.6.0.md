# Release 1.6.0 — Parsian Music Academy

Date: 2026-11-07
Theme: parsian-music 1.6.0 (`style.css`, `PM_VERSION`, admin footer)
WordPress: 7.1.4 | PHP: 8.5.4 | MySQL: 8.4.11
Baseline preserved: `wp-local/backups/parsian-wp-1.5.0-baseline.sql`, `parsian-music-1.5.0-baseline.zip`, `BASELINE-1.5.0.md`

## Artifacts (this release)

- `parsian-music.zip` (root, installable, 1.6.0)
- `wp-local/backups/parsian-music-1.6.0.zip`
- `wp-local/backups/parsian-wp-1.6.0.sql`
- `wp-local/tests/pm-1.6.0-smoke.php` — 15 assertions, all green on 2026-11-07
- AI runtime: `site/wp-content/uploads/pm-ai-index.json` (11 entries), `pm-ai-approved.json`

## What changed vs 1.5.0

### 1. Centralized Bale/Rubika gateway (`inc/notifications.php`)

- New table `wp_pm_notifications` with `submission_id` unique guard (pre-check in insert + unique key).
- All form sources call `pm_notify_submission()`; direct `wp_mail()` removed from consultation flow.
- Platforms: `disabled` (new default) / `bale` / `rubika` / `both`. No Chat ID → no send.
- Official endpoints, configurable: Bale `https://tapi.bale.ai/bot{token}/{method}`, Rubika `https://botapi.rubika.ir/v3/{token}/{method}`.
- Async queue: WP-Cron `pm_process_notification_queue` + opportunistic frontend processing, retry with exponential backoff (`pm_notify_max_attempts`), delivery status, logs, manual retry, retention cleanup.
- Phone number is display-only reference; Chat ID required.
- Admin page **Parsian → اعلان‌ها**: stats, filters, status table, connection test, Chat-ID helper, queue processing, retention.

### 2. Settings (`inc/theme-settings.php`)

- New tab **اعلان‌ها و پیام‌رسان‌ها** (platform, tokens, Chat IDs, phone refs, email fallback, retries).
- New `secret` field type: masked in UI, empty submit preserves stored value, never printed in HTML/REST/log.
- Form tab (labels/placeholders/required/options/title/button), phone-consultation tab with hidden fields + `tel:` card.

### 3. Forms (`inc/forms.php`, `template-parts/forms/consult-form.php`)

- Server validation, Persian mobile normalization, nonce/CSRF, rate-limit, duplicate prevention.
- Unique `PM-YYYYMMDD-XXXXXX` submission IDs, retention policy, admin access, save-before-notify.

### 4. AI knowledge governance (`inc/ai-assistant.php`)

- New table `wp_pm_ai_knowledge` (pending/approved/rejected, versions, hash dedupe, reviewer).
- Feedback (`/pm/v1/feedback`) always `pending`; only `approved` rows sync to `pm-ai-approved.json` and enter `pm_ai_search_index()`.
- Admin page **Parsian → دانش دستیار**: search, filter, add/edit/approve/reject/delete, versioning, rebuild.
- Runtime reads only JSON files (`pm-ai-index.json` + `pm-ai-approved.json`), never content DB.
- Prompt-injection detector (FA/EN), `<untrusted_data>` delimiters, security rules in system prompt, `pm_ai_guard_response()` redaction.
- Legacy `pm-ai-learned.json` migrated/deleted; unapproved feedback no longer searchable.

### 5. Map/widgets/footer

- `pm_map_url()`: validated URL, numeric lat/lng range check (-90..90, -180..180), fallback to Google Maps search.
- FAQ widget fallback, footer widgets, contact/panel-footer merged, `tel:` phone form preserved.

## Security fixes verified

- Secrets never leak: settings page, notifications page, and frontend return 0 matches for planted `SECRET-*` tokens (curl evidence 2026-11-07).
- Nonce checks on all admin-post actions and REST routes; capability `manage_options`.
- Output escaping on admin tables/forms; `secret` fields render as masked password inputs.
- AI responses redacted for `sk-*`, `bot*`, `v3/*` patterns.

## Tests (evidence, local stack http://127.0.0.1:8081)

`./wp eval-file tests/pm-1.6.0-smoke.php` → **15 passed, 0 failed**:

- notification + knowledge tables exist
- EN/FA injection blocked, normal question allowed, API-key redaction
- knowledge insert → pending → approve → searchable → delete → removed
- duplicate notification prevented (same `submission_id`)
- mocked Bale delivery marked `sent`
- email fallback marked `sent`
- index built/readable, no duplicate fingerprints

Manual/E2E (2026-11-07):

- Fresh login OK; `/wp-admin/admin.php?page=pm-settings|pm-notifications|pm-ai-knowledge` all HTTP 200, `debug.log` clean.
- Consultation submit → booking `PM-…` created, 2 notification rows (`bale`+`rubika` pending); resubmit blocked with duplicate message; cleanup done.
- Bale mock success, Bale failure → `failed` + `next_attempt` scheduled; Rubika mock `sendMessage` + `test_connection` OK; email fallback mock `sent`.
- Knowledge admin add (nonce from page) → `approved` + searchable; sync/rebuild OK.
- Frontend `/` + `/teachers/` HTTP 200; consult form, in-person/phone panels, FAQ, map (`nshn.ir`), footer, AI FAB/panel all present; no `SECRET-*` in HTML; `main.js`/`ai-chat.js`/`admin-ui.js` pass `node --check`; PHP lint sweep clean; viewport meta present, no >1400px inline widths.

## Real-delivery status (honest)

- **Not verified with real credentials.** No real Bale/Rubika bot tokens were provided and local DNS resolves their API hosts to `198.18.x.x` (proxy/blackhole), causing TLS reset. Implementation follows official API shapes and is mock-verified only.
- AI live completion likewise untested (no API key configured → chat correctly returns `pm_ai_setup` 503 "در حال آماده‌سازی"). Index exists (11 entries), panel renders.

## Configuration (production)

1. **Parsian → تنظیمات → اعلان‌ها و پیام‌رسان‌ها**: platform, Bale token + Chat ID, Rubika token + Chat ID, email fallback.
2. **Parsian → اعلان‌ها → تست اتصال** for each platform; use Chat-ID helper if needed.
3. Ensure WP-Cron runs (or server cron hitting `wp-cron.php`); queue also processes opportunistically.
4. **Parsian → دانش دستیار**: review `pending` feedback, approve/reject, rebuild index.
5. AI: fill API URL + key + model in **دستیار هوشمند** tab, then rebuild index.

## Limitations / not implemented

- No Action Scheduler dependency (WP-Cron used per decision).
- No real-message delivery proof (see above).
- No AI retraining; approved knowledge only augments retrieval context.
- `/contact/` slug does not exist locally (404); consultation tested on `/teachers/`.
- No load/perf testing; no new image assets.
