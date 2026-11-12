# Bale Bot Setup Guide

> Platform: PHP **8.4.27+** (8.5.12 recommended), WordPress **7.1.4**,
> WP-CLI **2.12.0**. See `docs/PLATFORM-COMPATIBILITY.md`.
>
> Docs verified against shipped code only — `docs.bale.ai` was unreachable
> from the build environment, so no new API behavior is assumed. Every
> method below already exists in the theme's proven adapters.

## 1. Create the bot and get the token

Create a bot via the official Bale bot father, copy the token, and store it
per `DEPLOYMENT.md` (`PA_BALE_TOKEN` preferred). Never paste it in chat,
issues, or frontend code.

## 2. Verify connectivity

Open **Assistant → دستیار و پیام‌رسان** and press **بررسی اتصال (getMe)**:

- `متصل — @username` → token valid; the username is cached
  (`pa_bale_bot_username`) for the popup's Bale row and link instructions.
- Anything else → the exact (redacted) error is shown; nothing is sent.
- The page itself makes no live Bale request. A network timeout keeps the
  last known username; a definitive token rejection clears it. After
  provisioning a token, run the explicit refresh once so the popup can show
  the Bale row.

## 3. Choose an ingestion mode

- **Production:** press **ثبت وب‌هوک**. Requires public HTTPS. The plugin
  registers `https://<site>/wp-json/pa/v1/bale/<secret>` via `setWebhook`.
  Confirm with the status page (`getWebhookInfo` is queried on demand
  through the same adapter).
- **Local/dev:** press **دریافت پیام‌ها (polling)** or run the equivalent
  `getUpdates` flow; `pa_bale_offset` advances so each update is ingested
  once (`pa_processed_updates` enforces uniqueness even on re-delivery).

## 4. Linking flow for academy users

1. Logged-in user opens the website popup → **اتصال حساب به بله** (visible
   only when a bot username is known). The backend mints a one-time token
   (15-minute TTL, SHA-256 stored, single-consumed).
2. User opens the bot in Bale and sends `/start link_<token>`.
3. The webhook validates + consumes the token atomically, binds the Bale
   chat id to the academy account, and replies with confirmation.
4. `/unlink` in the bot removes the binding. Replayed/expired tokens are
   rejected with explicit errors; anonymous chats stay isolated until linked.

## 5. Answer pipeline (same engine as the popup)

`parse → claim update_id → find/create conversation → Shared Assistant
Service → sendMessage`. Text conversations first; non-text updates are
ignored. Replies are truncated to 4000 chars, audited per conversation,
and never include tokens or keys (response guard + `pa_redact`).

## 6. Do NOT claim

Real delivery until steps 2–3 succeed with a production token from a
non-sandboxed network. All green tests in this repo used `pre_http_request`
mocks.
