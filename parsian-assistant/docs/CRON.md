# Cron & Queue Operations — Parsian Assistant

Production target: PHP **8.5.12** (minimum 8.4.27), WordPress **7.1.4**.
WP-CLI **2.12.0** is recommended for the system-cron variant.

## WP-Cron limitations (read this first)

WordPress cron is **not** a real scheduler: queued events (`pa_bale_reply`,
`pa_bale_retry`, `pa_prune`) run only when **some HTTP request triggers
`wp-cron.php`**. Consequences:

- On low-traffic sites a Bale reply can sit queued for minutes/hours until
  the next visit. The webhook itself always answers Bale promptly; only the
  AI processing + outbound send wait for cron.
- PHP request timeouts can kill a worker mid-answer. The recovery worker
  (`pa_recover_pending`) redrives stale unanswered updates and persisted
  replies that were never confirmed sent. It runs every 15 minutes from
  WP-Cron (up to 5 items per run), or on demand from the admin **بازیابی**
  button / WP-CLI. A recovery run never regenerates an answer that already
  exists and never re-sends a message with a `bale_sent` record.
- Only one worker runs per conversation: an atomic options-table lock
  blocks overlapping events, duplicate `pa_bale_reply` events for the same
  conversation are deduplicated by WP core, and an existing AI answer after
  the latest user message makes a duplicate worker a no-op. A worker event
  that is more than a minute overdue is treated as stuck, so recovery can
  process it instead of waiting forever.
- Scheduling a worker never runs it inline:
  - `duplicate_event` → verified with `wp_next_scheduled()` and treated as
    `queued` (the existing event owns it).
  - Genuine failure (`pre_schedule_event_false`, `schedule_event_false`,
    `could_not_set`, or an invisible hijacked event) → audited as
    `bale_schedule_failed`; the persisted user message stays pending for
    the recovery worker.
  - The webhook never calls the AI provider or Bale `sendMessage`, even for
    `/start` and `/unlink` confirmations (those are stored and queued).
- Outbound `sendMessage` retries at +60s/+300s/+1800s, then escalates the
  conversation for a human instead of retrying forever. Retries target the
  exact assistant message id, and a failed retry schedule is audited and
  left to recovery.
- Every send records `bale_sending` before the HTTP call and
  `bale_sent`/`bale_failed` afterwards. If the process dies mid-send, Bale
  offers no idempotency key: recovery escalates
  (`bale_delivery_ambiguous`) instead of risking a duplicate reply. This is
  the only outcome that is deliberately left to a human; a crash before the
  send marker is safely retried.

## Recommended: system cron (hosts with cron/SSH)

Run WP-Cron from the system scheduler and disable loopback spawning:

1. In `wp-config.php`: `define( 'DISABLE_WP_CRON', true );`
2. Crontab (every 5 minutes):
   `*/5 * * * * /usr/bin/php8.5 /path/to/wordpress/wp-cron.php >/dev/null 2>&1`
   or, where WP-CLI is available (more observable):
   `*/5 * * * * /usr/bin/php8.5 /path/to/wp-cli.phar --path=/path/to/wordpress cron event run --due-now >/dev/null 2>&1`
3. Verify: **Assistant** dashboard shows no growing pending count;
   `wp cron event list` shows `pa_bale_reply`/`pa_bale_retry`/
   `pa_recover_pending`/`pa_prune` firing.
4. `pa_recover_pending` is a 15-minute event (custom `pa_fifteen_minutes`
   schedule). If WP-Cron is disabled entirely and no system cron runs, only
   the admin **بازیابی** button can drain the queue.

## Fallback: no system cron (ordinary shared hosting)

- Leave loopback WP-Cron enabled (default). Replies are processed on the
  next site visit after the webhook arrives.
- The **بازیابی پاسخ‌های معوق** button on the Assistant dashboard runs the
  same recovery immediately for currently pending items (up to 20 answers +
  20 deliveries; live workers are skipped by the lock).
- For polling mode (no webhook): the admin **poll** button or any scheduled
  visit drives `getUpdates` with a persisted offset; re-deliveries are
  deduplicated by `pa_processed_updates`.

## What is NOT claimed

Prompt AI delivery is not guaranteed by a prompt webhook acknowledgement:
the acknowledgement only confirms durable persistence. End-to-end latency
equals webhook receipt + next cron tick + model time + send time. No AI
completion or Bale `sendMessage` executes inside the webhook request.
