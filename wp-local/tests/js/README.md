# Popup DOM regression tests

These tests exercise the real theme `ai-chat.js` and plugin `pa-popup.js` in
jsdom. They verify single submission, Enter-to-send, the busy guard, and the
plugin-off fallback to the theme endpoint.

## Requirements

- Node.js **26.13.0 LTS**
- npm **12.2.2** (Node 26.13.0 ships npm 11.20.0; install the tested npm
  version with `npm install -g npm@12.2.2` or an isolated prefix)
- jsdom is a development dependency only; production assets never load it.

## Commands

```bash
cd wp-local/tests/js
npm ci
npm test
npm audit
```

`npm ci` must be used for reproducible installs from `package-lock.json`.
Run these from the repository checkout; no `/tmp` files are required.
