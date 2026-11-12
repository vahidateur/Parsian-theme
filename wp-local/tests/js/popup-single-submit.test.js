const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const REPO_ROOT = path.resolve(__dirname, '../../..');
const THEME_JS = path.join(REPO_ROOT, 'parsian-music/assets/js/ai-chat.js');
const PA_JS = path.join(REPO_ROOT, 'parsian-assistant/assets/pa-popup.js');
const PA_CSS = path.join(REPO_ROOT, 'parsian-assistant/assets/pa-popup.css');

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

function makeDom({ withPlugin, withBale = false, starters = [] }) {
  const calls = [];
  const dom = new JSDOM(`<!DOCTYPE html><html><body>${WIDGET}</body></html>`, {
    url: 'http://127.0.0.1:8081/',
    runScripts: 'outside-only',
  });
  const { window } = dom;
  const formBefore = window.document.querySelector('[data-pm-ai-form]');
  window.fetch = (url, opts) => {
    calls.push({ url, headers: (opts && opts.headers) || {}, body: opts && opts.body ? JSON.parse(opts.body) : null });
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
    starters,
    error: 'err',
  };
  if (withPlugin) {
    window.paChat = { endpoint: 'http://127.0.0.1:8081/wp-json/pa/v1/chat', nonce: 'w1' };
    window.paBale = withBale
      ? {
          bot: 'parsiantestbot',
          tokenEndpoint: 'http://127.0.0.1:8081/wp-json/pa/v1/link-token',
          restNonce: 'w2',
          loggedIn: 1,
          connectLabel: 'ادامه در بله',
          linkLabel: 'اتصال حساب به بله',
        }
      : undefined;
  }
  window.eval(fs.readFileSync(THEME_JS, 'utf8'));
  if (withPlugin) {
    window.eval(fs.readFileSync(PA_JS, 'utf8'));
  }
  window.document.dispatchEvent(new window.Event('DOMContentLoaded', { bubbles: true }));
  return { window, calls, formBefore };
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

async function focusTick() {
  await new Promise((resolve) => setTimeout(resolve, 150));
}

function submit(window) {
  const form = window.document.querySelector('[data-pm-ai-form]');
  form.dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));
}

(async () => {
  // 1. Plugin ON: one submit -> exactly one shared-engine request, one user bubble.
  {
    const { window, calls, formBefore } = makeDom({ withPlugin: true });
    const input = window.document.querySelector('textarea[name="message"]');
    input.value = 'hello engine';
    submit(window);
    await tick();
    await tick();
    const paCalls = calls.filter((call) => call.url.includes('/pa/v1/chat'));
    const themeCalls = calls.filter((call) => call.url.includes('/pm/v1/chat'));
    assert(paCalls.length === 1, 'plugin-on: exactly one shared-engine request');
    assert(themeCalls.length === 0, 'plugin-on: zero theme-endpoint requests');
    assert(window.document.querySelectorAll('.pm-ai-msg.is-user').length === 1, 'plugin-on: exactly one user bubble');
    assert(window.document.querySelectorAll('.pm-ai-msg.is-bot').length === 1, 'plugin-on: bot reply rendered');
    assert(window.document.querySelector('[data-pm-ai-form]') === formBefore, 'plugin-on: original form node preserved');
    assert(paCalls[0].headers['X-WP-Nonce'] === 'w1', 'plugin-on: REST nonce from transport');
    assert(paCalls[0].body && typeof paCalls[0].body.sid === 'string', 'plugin-on: session id sent for continuity');
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
    input.value = 'double';
    submit(window);
    input.value = 'double again';
    submit(window);
    await tick();
    await tick();
    assert(calls.filter((call) => call.url.includes('/pa/v1/chat')).length === 1, 'busy guard blocks duplicate submission');
  }
  // 4. Plugin OFF: theme endpoint used (fallback intact).
  {
    const { window, calls } = makeDom({ withPlugin: false });
    const input = window.document.querySelector('textarea[name="message"]');
    input.value = 'fallback';
    submit(window);
    await tick();
    await tick();
    assert(calls.filter((call) => call.url.includes('/pm/v1/chat')).length === 1, 'plugin-off: theme endpoint still used');
    assert(calls.filter((call) => call.url.includes('/pa/v1/')).length === 0, 'plugin-off: no shared-engine requests');
  }
  // 5. Starter buttons populate the active input.
  {
    const { window } = makeDom({ withPlugin: true, starters: ['starter one'] });
    const input = window.document.querySelector('textarea[name="message"]');
    const starter = window.document.querySelector('.pm-ai-starter');
    assert(!!starter, 'starter button rendered');
    starter.dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
    assert(input.value === 'starter one', 'starter populates the active input');
    assert(window.document.activeElement === input, 'starter focuses the active input');
  }
  // 6. Opening the popup focuses the active input.
  {
    const { window } = makeDom({ withPlugin: true });
    const input = window.document.querySelector('textarea[name="message"]');
    window.document.querySelector('.pm-ai-fab').dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
    await focusTick();
    assert(window.document.activeElement === input, 'opening popup focuses the active input');
  }
  // 7. Feedback rendered after a response.
  {
    const { window } = makeDom({ withPlugin: true });
    const input = window.document.querySelector('textarea[name="message"]');
    input.value = 'feedback please';
    submit(window);
    await tick();
    await tick();
    assert(!!window.document.querySelector('.pm-ai-feedback'), 'feedback controls rendered after response');
  }
  // 8. Autogrow still works.
  {
    const { window } = makeDom({ withPlugin: true });
    const input = window.document.querySelector('textarea[name="message"]');
    Object.defineProperty(input, 'scrollHeight', { value: 42, configurable: true });
    input.dispatchEvent(new window.Event('input', { bubbles: true }));
    assert(input.style.height === '42px', 'autogrow resizes the active input');
  }
  // 9. Bale row renders and its CSS is present.
  {
    const { window } = makeDom({ withPlugin: true, withBale: true });
    const row = window.document.querySelector('.pa-bale-row');
    assert(!!row, 'Bale continuation row rendered');
    assert(!!row.querySelector('.pa-bale-row__btn'), 'Bale row has a usable action');
    const css = fs.readFileSync(PA_CSS, 'utf8');
    assert(css.includes('.pm-ai-panel .pa-bale-row'), 'Bale row CSS is scoped to the panel');
    assert(css.includes('@media'), 'Bale row CSS is responsive');
  }
  // 10. Repeated open/close cycles do not duplicate submit listeners.
  {
    const { window, calls } = makeDom({ withPlugin: true });
    const fab = window.document.querySelector('.pm-ai-fab');
    const close = window.document.querySelector('.pm-ai-close');
    for (let i = 0; i < 3; i++) {
      fab.dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
      close.dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
    }
    const input = window.document.querySelector('textarea[name="message"]');
    input.value = 'after cycles';
    submit(window);
    await tick();
    await tick();
    assert(calls.filter((call) => call.url.includes('/pa/v1/chat')).length === 1, 'open/close cycles keep exactly one submit path');
  }
  console.log(`\nRESULT: ${pass} passed, ${fail} failed.`);
  process.exit(fail ? 1 : 0);
})();
