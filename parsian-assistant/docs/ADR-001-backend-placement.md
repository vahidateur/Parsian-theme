# ADR-001 — Assistant & Messaging Backend Placement

Date: 2026-11-07
Status: accepted
Context: Parsian Music Academy v1.6.1 (theme `parsian-music`)

## Decision

Conversation state, Bale webhook ingestion, account-link tokens, update
deduplication, the migration helper, and the provider-independent assistant
contracts live in a **companion plugin `parsian-assistant`** (`PA\v1`
contracts + `pa/v1` REST). The theme keeps presentation (popup UI, settings
tabs, Knowledge Base governance UI, form notifications) and remains fully
functional without the plugin.

## Rationale

- Critical messaging infrastructure (webhook endpoint, conversation tables,
  link tokens) must survive a theme switch. Anything under
  `wp-content/themes/` goes dark the moment another theme activates.
- The theme already owns working, tested pieces (retrieval, injection guard,
  redaction, Bale/Rubika HTTP parsing, notification queue). The plugin
  **delegates** to them via `function_exists()` guards instead of
  re-implementing — no engine duplication.
- Single active theme assumption: `theme_mods_*` migration only makes sense
  as plugin code that works regardless of which theme is active.

## Deployment implications

- Copy `parsian-assistant/` to `wp-content/plugins/` and activate. No
  database migration of existing theme data is required; tables are created
  on plugin activation (`pa_install`, versioned `pa_db_version`).
- Without the plugin: website popup, Knowledge Base, and form notifications
  behave exactly as v1.6.1. Bale bot, linking, conversation history, and the
  migration screen are unavailable; the popup hides Bale UI (feature-detected
  via `window.paBale`).
- Secrets: bot token and AI key stay server-side. Preferred source is
  environment/`wp-config.php` constants (`PA_BALE_TOKEN`, `PA_AI_API_KEY`);
  otherwise the existing masked theme settings are reused. Nothing secret is
  ever localized to JavaScript.

## Future Laravel boundary

`PA\v1` interfaces (`AssistantService`, `ConversationRepository`,
`KnowledgeRetriever`, `AIProviderInterface`, `ChannelAdapterInterface`,
`OperatorHandoffService`) plus `pa/v1` REST resources (conversations,
messages, delivery, linking) are the port boundary. A Laravel backend would
re-implement these contracts; the Bale adapter depends only on an
HTTP callable + the repository interfaces, never on theme functions.
