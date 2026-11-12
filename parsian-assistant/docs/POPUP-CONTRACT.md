# Popup integration contract — theme ↔ Parsian Assistant

The theme owns the popup DOM and interaction behavior. The companion plugin
must not clone, replace, or re-bind the form.

## Transport

Before the first submit, the plugin sets:

```js
window.pmAi.transport = {
  endpoint: 'https://example.test/wp-json/pa/v1/chat',
  nonce: '<wp_rest nonce>'
};
```

`parsian-music/assets/js/ai-chat.js` reads this object at submit time (not at
init time). If present, it sends:

```json
{ "message": "<user text>", "sid": "<optional local session id>" }
```

with `X-WP-Nonce`. The shared route returns `reply`, `sources`, `sid`, and
`conversation_id`. The theme stores `sid` in `localStorage.pa_sid` and keeps
rendering replies, sources, errors, feedback controls, starters, focus,
autogrow, and the busy/disabled state itself.

When the transport is absent (plugin inactive or popup script not loaded),
the theme sends the original `pm/v1/chat` request with `X-PM-Nonce` and no
plugin code path is active.

## Rules

- Exactly one submit handler exists: the theme's.
- The plugin may add its own UI (for example the Bale row) without moving or
  replacing the form/input/button nodes.
- Adding the transport must not change starter, focus, Enter, autogrow,
  feedback, or busy behavior.
- `pa-popup.js` must remain idempotent: repeated open/close or re-execution
  must not duplicate listeners or rows.
- Bale-row styling stays scoped under `.pm-ai-panel .pa-bale-row`; no global
  selectors.

## Regression coverage

`wp-local/tests/js/popup-single-submit.test.js` covers one-request routing,
Enter, busy guard, starter fill/focus, panel focus, autogrow, feedback,
plugin-off fallback, Bale-row rendering/CSS, original-form preservation, and
repeated open/close cycles.
