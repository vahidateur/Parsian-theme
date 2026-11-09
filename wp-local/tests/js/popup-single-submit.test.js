const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const REPO_ROOT = path.resolve(__dirname, '../../..');
const THEME_JS = path.join(REPO_ROOT, 'parsian-music/assets/js/ai-chat.js');
const PA_JS = path.join(REPO_ROOT, 'parsian-assistant/assets/pa-popup.js');

const WIDGET = `
<div class="pm-ai-widget" data-pm-ai>
  <button class="pm-ai-fab" aria-expanded="false" aria-controls="pm-ai-panel">FAB</button>
  <section class="pm-ai-panel" id="pm-ai-panel" aria-hidden="true">
    <header class="pm-ai-head">
      <button class="pm-ai-close" type="button">x</button>
    </header>
    <div class="pm-ai-messages" data-pm-ai-messages></div>
    <div class="pm-ai-starters" data-pm-ai-starters></div>
    <form class="pm-ai-form" data-pm-ai-form>
      <textarea name="message"></textarea>
      <button type="submit">send</button>
    </form>
  </section>
</div>`;

function makeDom({ withPlugin }) {
  const calls = [];
  const dom = new JSDOM(`<!DOCTYPE html><html><body>${WIDGET}</body></html>`, {
    url: 'http://127.0.0.1:8081/',
    runScripts: 'outside-only',
  });
  const { window } = dom;
  window.fetch = (url, opts) => {
    calls.push({ url, body: opts && opts.body ? JSON.parse(opts.body) : null });
    return Promise.resolve({
      ok: true,
      json: () => Promise.resolve({ reply: 'shared answer', sid: 'SID1', sources: [], conversation_id: 1 }),
    });
  };
  window.pmAi = {
    endpoint: 'http://127.0.0.1:8081/wp-json/pm/v1/chat',
    feedbackEndpoint: 'http://127.0.0.1:8081/wp-json/pm/v1/feedback',
    nonce: 'n1',
    feedbackNonce: 'n2',
    greeting: 'hi',
    starters: [],
    error: 'err',
  };
  if (withPlugin) {
    window.paChat = { endpoint: 'http://127.0.0.1:8081/wp-json/pa/v1/chat', nonce: 'w1' };
    window.paBale = undefined;
  }
  window.eval(fs.readFileSync(THEME_JS, 'utf8'));
  if (withPlugin) {
    window.eval(fs.readFileSync(PA_JS, 'utf8'));
  }
  window.document.dispatchEvent(new window.Event('DOMContentLoaded', { bubbles: true }));
  return { window, calls };
}

let pass = 0;
let fail = 0;
function assert(condition, label) {
  if (condition) {
    pass++;
    console.log('PASS: ' + label);
  } else {
    fail++;
    console.log('FAIL: ' + label);
  }
}

async function tick() {
  await new Promise((resolve) => setTimeout(resolve, 20));
}

(async () => {
  // 1. Plugin ON: one submit -> exactly one shared-engine fetch, one user bubble.
  {
    const { window, calls } = makeDom({ withPlugin: true });
    const input = window.document.querySelector('textarea[name="message"]');
    const form = window.document.querySelector('[data-pm-ai-form]');
    input.value = 'hello engine';
    form.dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));
    await tick();
    await tick();
    const paCalls = calls.filter((call) => call.url.includes('/pa/v1/chat'));
    const themeCalls = calls.filter((call) => call.url.includes('/pm/v1/chat'));
    assert(paCalls.length === 1, 'plugin-on: exactly one shared-engine request');
    assert(themeCalls.length === 0, 'plugin-on: zero theme-endpoint requests');
    assert(window.document.querySelectorAll('.pm-ai-msg.is-user').length === 1, 'plugin-on: exactly one user bubble');
    assert(window.document.querySelectorAll('.pm-ai-msg.is-bot').length === 1, 'plugin-on: bot reply rendered');
  }
  // 2. Enter key submits once.
  {
    const { window, calls } = makeDom({ withPlugin: true });
    const input = window.document.querySelector('textarea[name="message"]');
    input.value = 'enter test';
    input.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true }));
    await tick();
    await tick();
    assert(calls.filter((call) => call.url.includes('/pa/v1/chat')).length === 1, 'enter key submits exactly once');
  }
  // 3. Double submit while busy -> still one request.
  {
    const { window, calls } = makeDom({ withPlugin: true });
    const input = window.document.querySelector('textarea[name="message"]');
    const form = window.document.querySelector('[data-pm-ai-form]');
    input.value = 'double';
    form.dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));
    input.value = 'double again';
    form.dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));
    await tick();
    await tick();
    assert(calls.filter((call) => call.url.includes('/pa/v1/chat')).length === 1, 'busy guard blocks duplicate submission');
  }
  // 4. Plugin OFF: theme endpoint used (fallback intact).
  {
    const { window, calls } = makeDom({ withPlugin: false });
    const input = window.document.querySelector('textarea[name="message"]');
    const form = window.document.querySelector('[data-pm-ai-form]');
    input.value = 'fallback';
    form.dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));
    await tick();
    await tick();
    assert(calls.filter((call) => call.url.includes('/pm/v1/chat')).length === 1, 'plugin-off: theme endpoint still used');
    assert(calls.filter((call) => call.url.includes('/pa/v1/')).length === 0, 'plugin-off: no shared-engine requests');
  }
  console.log(`\nRESULT: ${pass} passed, ${fail} failed.`);
  process.exit(fail ? 1 : 0);
})();
