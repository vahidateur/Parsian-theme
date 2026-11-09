# Deployment & Credential Guide — Parsian Assistant 1.0.0

## Platform requirements

| Component | Minimum | Recommended / verified |
|---|---|---|
| PHP | 8.4.27 | 8.5.12 |
| WordPress | 7.1 | 7.1.4 |
| MySQL | 8.4 LTS | 8.4.13 |
| MariaDB | 12.3 LTS | 12.3.3 (primary) / 11.8.9 (backward) |
| WP-CLI | 2.12.0 | 2.12.0 |
| Node.js / npm | development only | 26.13.0 LTS / 12.2.2 |

Required PHP extensions: `curl`, `json`, `mbstring`, `mysqli` (or
`pdo_mysql`), `openssl`, `zip`, `xml`, and `gd` or `imagick`; `opcache` is
strongly recommended. Composer is not required (`composer.json` is absent).
See `docs/PLATFORM-COMPATIBILITY.md` for sources, release dates, and evidence.
The theme and plugin headers declare `Requires PHP: 8.4.27` and
`Requires at least: 7.1`.

## Install order

- After updating plugin files, flush PHP OPcache (restart PHP-FPM / the
  PHP server). A stale bytecode cache can keep serving the previous REST
  route table (observed once with `php -S` + OPcache: new routes 404 until
  restart).

1. WordPress 7.1.x with the Parsian Music Academy theme `v1.6.1` (required for
   the website popup, Knowledge Base, and notification transport).
2. Copy `parsian-assistant/` to `wp-content/plugins/` and activate it in
   **Plugins**. Tables (`wp_pa_*`, version `pa_db_version=1.0.0`) are created
   on activation; without the theme the plugin still activates but AI answers
   fall back to "preparing" messages.
3. No commit, push, tag, or release is part of this procedure.

## Credentials (server-side only — never in JS, never in chat)

| Secret | Preferred source | Fallback |
|---|---|---|
| Bale bot token | `define( 'PA_BALE_TOKEN', '...' );` in `wp-config.php` | Theme setting **Parsian → اعلان‌ها** → Bale token (masked `secret` field, never rendered) |
| AI API key | `define( 'PA_AI_API_KEY', '...' );` in `wp-config.php` | Theme setting **Parsian → دستیار هوشمند** → API key (masked) |

`wp-config.php` constants win when defined. Rotation: replace the constant
(or re-save the masked theme field — empty submit preserves the old value),
then use **Assistant → Poll/Test** to confirm `getMe` again.

## Environment checklist

- Production Bale bot: HTTPS site required for `setWebhook`. Register at
  **Assistant → ثبت وب‌هوک**; the URL contains a 32-char secret path segment
  (`pa/v1/bale/<secret>`). Rotate it with **تازه‌سازی مسیر محرمانه** and
  re-register the webhook immediately afterwards.
- Local development: no webhook; use **دریافت پیام‌ها (polling)** or
  `wp pa bale-poll` (WP-CLI) with a stored `pa_bale_offset`.
- Async queue: the webhook only validates, dedupes, persists, and schedules;
  it never runs AI or Bale delivery. `pa_recover_pending` is scheduled every
  15 minutes (custom `pa_fifteen_minutes` cron interval) and drains up to 5
  pending answers plus 5 undelivered replies per run. Run system cron for
  reliable delivery and always keep the admin **بازیابی پاسخ‌های معوق**
  button available as the fallback.
- Retention: closed (non-open) conversations older than `pa_retention_days`
  (default 90; set to 0 to keep explicitly, max 500/run) are pruned daily
  with their messages and events. Expired link tokens (>7 days) and
  processed update ids (>30 days) are pruned daily by `pa_prune`.
  `delete_user` detaches + closes that user's conversations, drops their
  tokens, and audits; full text removal is via Tools → Erase Personal Data
  (registered eraser) or the retention policy.
- Without the plugin: popup, Knowledge Base, and form notifications behave
  exactly as theme v1.6.1. Bale bot, linking, history, and migration are
  unavailable; the popup hides Bale UI automatically.

## What is NOT verified

Real Bale delivery and live AI completions were **mock-tested only** in this
environment (no production credentials; sandbox DNS blocks Bale API hosts).
Do not claim live integration until tested with a real bot token.
