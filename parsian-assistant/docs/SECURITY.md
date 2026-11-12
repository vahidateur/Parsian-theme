# Security Notes — Parsian Assistant 1.0.1

Accepted audit state. Anything marked NOT IMPLEMENTED is a documented
limitation, not a claim.

## Supported platform

Tested on PHP **8.4.27** and **8.5.12**, WordPress **7.1.4**, MySQL
**8.4.13** LTS, MariaDB **12.3.3** LTS (primary; **11.8.9** retained as
backward compatibility), and WP-CLI **2.12.0**. PHP 7.4 is no longer
supported. See `docs/PLATFORM-COMPATIBILITY.md` for the extension list
and evidence. Keep the PHP/WordPress/DB patch level current with upstream
security releases. PHP 8.5.12/8.4.27 include fixes for public security
advisories; the prior 8.5.11/8.4.26 test line must not be used for production.

## Webhook

- Auth = 32-char (`wp_generate_password`, ~190-bit) secret path segment,
  compared with `hash_equals`. No Telegram-style signed headers are assumed
  for Bale.
- Wrong secret → 403; malformed JSON → 400; unknown shapes → `ignored:true`.
  The 403/400 distinction is an acceptable oracle given the secret space.
- The secret URL may appear in HTTP access logs — standard tradeoff for
  path-secret webhooks. Rotate via **تازه‌سازی مسیر محرمانه** and
  re-register; treat log files as sensitive.
- Rate limit: 30 answers/hour per chat id (mirrors the theme popup).
  Over-limit updates are persisted-audited and dropped with `ok:true`
  (no retry storm, no cost).
- Fast path: claim update id → persist user message → schedule
  `pa_bale_reply` → immediate `ok`. The webhook never executes AI completion
  or outbound `sendMessage`; `/start` and `/unlink` confirmations are stored
  and queued too. Duplicate scheduling is verified against
  `wp_next_scheduled()` and treated as already queued; genuine scheduling
  failure leaves a persisted pending row plus a `bale_schedule_failed`
  audit event for the recovery worker. AI work (45s upstream timeout) and
  outbound `sendMessage` retries (3 attempts, 60/300/1800s, then escalate +
  audit) run only in cron/recovery/admin contexts. Every send records
  `bale_sending` before HTTP; an interrupted send is escalated as
  `bale_delivery_ambiguous` rather than re-sent, because the provider has no
  idempotency key.

## Secrets

- Precedence: `PA_BALE_TOKEN` / `PA_AI_API_KEY` constants → masked theme
  settings → (never) frontend. Nothing secret is localized to JavaScript;
  admin UI shows only ثبت‌شده/ثبت‌نشده; transport errors are redacted.
- `pa_redact()` strips tokens/keys from every audit/event payload.

## Authorization

- `pa/v1/chat` and webhook: public + nonce/secret, per-IP/per-chat limits.
- `pa/v1/link-token`: any logged-in user (intended), capped at 5 active
  tokens, 15-minute TTL, SHA-256 stored, atomic single-consume, replay and
  expiry explicitly rejected and tested.
- `pa/v1/conversations|status`, all `admin-post.php` actions, both admin
  pages: `manage_options` + nonces. Migration preview/run/restore and
  unlink are admin-only.

## Privacy

- Stored: conversation text, channel id (Bale chat id / `web:<sid>`),
  a pseudonymous `privacy_subject` on each message, a
  `wp_pa_privacy_subjects` mapping, and audit events (no message bodies).
  External transmission is limited to the configured AI endpoint (question +
  retrieved context) and Bale `sendMessage` (reply to the user's own chat).
  WordPress personal data exporter/eraser are registered.
- Active account linking is separate from privacy ownership:
  - `/unlink` clears `wp_user_id`, revokes unused link tokens, and closes an
    open conversation. It **keeps** the pseudonymous subject so the
    exporter/eraser can still find history; it deliberately does not delete
    message content. Re-linking requires a new one-time token.
  - The exporter resolves the subject and exports only messages carrying it,
    plus a just-linked conversation with no messages yet. It never exports a
    different subject's messages from a shared/reused chat.
  - The eraser deletes every message with the subject (including unlinked
    conversations), removes the mapping and tokens, and is idempotent. Other
    users sharing a channel are unaffected.
  - `delete_user` performs the same immediate erasure. Account deletion does
    not leave a hidden support copy; export first if a support window is
    required.
- Daily `pa_prune`: expired link tokens (>7d), processed update ids (>30d),
  and closed/escalated conversations older than `pa_retention_days`
  (default 90; 0 = keep explicitly; max 500/run). Unlinked conversations are
  closed, so retention applies. Orphaned privacy subjects are removed once
  no message or active account link references them.
- Migration backups contain only allowlisted non-secret settings, retain at
  most 10 snapshots for 30 days, and are sanitized on upgrade. Restore
  preserves currently configured credentials.
- Admin status reads use cached `getMe`/webhook data. An explicit
  **بررسی اتصال** action refreshes the cache; ordinary page loads and REST
  status reads make no Bale HTTP calls. The bot token is never cached in
  the status option or localized to JavaScript.

## Known limitations

- Real Bale delivery and live AI completions are mock-tested only.
- `docs.bale.ai` was unreachable during development; only already-shipped
  method shapes are used. `getWebhookInfo` is displayed, not depended upon.
- Web admin should run over HTTPS in production (nonces, tokens, webhooks).
- WP-CLI 2.12.0 (latest stable) emits a PHP 8.5 deprecation from its bundled
  `php-cli-tools/Colors.php` on some colorized commands. Project code is
  `E_ALL`-clean; this is an upstream tool limitation, not a plugin defect.
- No WAF/bot-detection beyond rate limits; front large public traffic with
  standard WordPress hardening.
