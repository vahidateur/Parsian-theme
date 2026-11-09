# Laravel Integration Boundary — PA v1

WordPress is the current source of truth. No Laravel implementation exists
or is introduced here.

## Port surface (stable, versioned)

PHP interfaces in `inc/contracts.php` (`PA\v1`):

- `AssistantService::answer()` — conversation creation + response generation.
- `ConversationRepository` — conversations, messages, history, status, user
  linking, audit events.
- `KnowledgeRetriever::retrieve()` — approved-entries + FAQ lookup.
- `AIProviderInterface` — `configured()` + `complete()`.
- `ChannelAdapterInterface` — `channel()`, `send()`, `parse_incoming()`.
- `OperatorHandoffService` — escalation decision + handoff.

REST equivalents under `pa/v1` (same shapes a Laravel backend would serve):

- `POST pa/v1/bale/<secret>` — message ingestion (webhook).
- `POST pa/v1/link-token` — user identity linking (auth).
- `GET  pa/v1/conversations` — conversation list (admin).
- `GET  pa/v1/status` — delivery/provider status (admin).

Notification events are the `wp_pa_events` rows (`answered`, `bale_sent`,
`bale_failed`, `handoff`, `account_linked`, …) plus the theme's existing
`wp_pm_notifications` delivery log for form submissions.

## Rules for the future backend

- Re-implement the six interfaces; keep method names, input keys
  (`channel`, `external_key`, `text`, `user_id`), and answer keys (`reply`,
  `sources`, `confidence`, `action`, `conversation_id`) byte-compatible.
- The Bale adapter must keep depending only on an HTTP callable + the
  repository interfaces — never on theme functions (today it *prefers* the
  theme transport when present, with a standalone fallback; the Laravel port
  drops the theme branch).
- Conversation/message/event table names and unique keys
  (`channel+external_key`, `provider+update_id`, `token_hash`) are part of
  the contract — keep them to allow data migration.
- Webhook secret-path auth and one-time hashed link tokens transfer as-is.
