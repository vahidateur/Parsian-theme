# Parsian Assistant

Companion plugin for the Parsian Music Academy theme.

The plugin holds infrastructure that must survive a theme switch: the shared
assistant engine, Bale webhook ingestion, account linking, update deduplication,
privacy export/erasure, notification recovery, and legacy settings migration. The
website popup and Knowledge Base UI remain in the theme; the plugin delegates to
them when the theme is active and degrades gracefully when it is not.

## Requirements

- WordPress 7.1 or later (tested with 7.1.4).
- PHP 8.4.27 or later (tested with 8.5.12).
- MySQL 8.4 LTS or MariaDB 12.3 LTS.
- The Parsian Music Academy theme is recommended but not strictly required.
- No Composer dependencies.

## Installation

1. Copy `parsian-assistant/` into `wp-content/plugins/`, or upload the plugin ZIP
   from **Plugins → Add New**.
2. Activate **Parsian Assistant**.
3. On activation, the plugin creates its tables and schedules maintenance and
   recovery hooks. The theme's **Parsian** settings panel is the primary
   configuration surface when the theme is active.

## Configuration

- **Assistant engine** — configure the OpenAI-compatible API URL, model, and API
  key in the theme settings (or the corresponding `pa_ai_*` options in standalone
  mode).
- **Bale** — set the bot token and destination Chat ID, choose webhook or polling
  ingestion, and use the connection test. The webhook path contains a
  high-entropy secret; rotate it from the admin screen if it is exposed.
- **Cron** — WP-Cron is traffic-driven. Configure a system cron job for reliable
  retries and recovery in production. See `docs/CRON.md`.
- **Privacy** — the plugin registers a WordPress privacy exporter and eraser.
  Export/erasure is scoped to a pseudonymous per-user subject.

## Database tables

The plugin creates and maintains:

- `{prefix}pa_conversations`
- `{prefix}pa_messages`
- `{prefix}pa_privacy_subjects`
- `{prefix}pa_events`
- `{prefix}pa_processed_updates`
- `{prefix}pa_link_tokens`

## REST routes

- `POST /wp-json/pa/v1/bale/{secret}` — Bale webhook (path secret required).
- `POST /wp-json/pa/v1/chat` — public chat endpoint (nonce + per-IP limit).
- `POST /wp-json/pa/v1/link-token` — authenticated link-token creation.
- `GET /wp-json/pa/v1/conversations` — admin-only conversation list.
- `GET /wp-json/pa/v1/status` — admin-only connection/queue status.

## Security notes

- The webhook secret is compared with `hash_equals` and is rotatable.
- Link tokens are one-time, SHA-256 hashed, expire after 15 minutes, and are
  consumed atomically.
- Notification replies use bounded retries, deduplication, and human escalation.
- AI output is redacted for credential patterns; provider errors are scrubbed.
- Outbound credential-bearing requests validate the destination against an exact
  provider hostname allowlist before attaching a token or key.
- See `docs/SECURITY.md` for the full security notes and known limitations.

## Documentation

- `docs/DEPLOYMENT.md` — deployment and credential guide.
- `docs/CRON.md` — queue and WP-Cron operations.
- `docs/SECURITY.md` — security architecture and limitations.
- `docs/MIGRATION.md` — migration and rollback.
- `docs/BALE-SETUP.md` — Bale bot setup.
- `docs/POPUP-CONTRACT.md` — theme/plugin popup contract.
- `docs/LARAVEL-BOUNDARY.md` — future backend boundary.

## License

Original plugin source code is dual-licensed at your option under the
**Apache License 2.0** (see `LICENSE`) or the **GNU General Public License,
version 2 or later** (see `LICENSE-GPL-2.0.txt`). Third-party components and
assets are not relicensed by this project; see the repository's
`THIRD-PARTY-NOTICES.md` and `NOTICE`.

The GPL option is provided for compatibility with WordPress, which is distributed
under GPL-2.0-or-later. Apache-2.0 is compatible with GPLv3 but not GPLv2.
