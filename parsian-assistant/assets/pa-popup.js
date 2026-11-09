/**
 * Shared-engine chat path + Bale continuation layer for the Parsian popup.
 * Intercepts the theme form and answers via pa/v1/chat (same engine as
 * the Bale bot), rendering into the theme's own bubbles/styles. Falls back
 * to the theme endpoint only when the shared route is unavailable, so the
 * popup never breaks while the plugin is active.
 */
(function () {
	function ready(fn) {
		if (document.readyState !== 'loading') {
			fn();
		} else {
			document.addEventListener('DOMContentLoaded', fn);
		}
	}

	function bubble(container, role, text) {
		var div = document.createElement('div');
		div.className = 'pm-ai-msg is-' + role;
		div.textContent = text;
		container.appendChild(div);
		container.scrollTop = container.scrollHeight;
		return div;
	}

	ready(function () {
		var root = document.querySelector('[data-pm-ai]');
		var panel = document.querySelector('[data-pm-ai] .pm-ai-panel');
		var form = document.querySelector('[data-pm-ai-form]');
		var messages = document.querySelector('[data-pm-ai-messages]');
		var input = form ? form.querySelector('textarea[name="message"]') : null;
		if (!root || !panel || !form || !messages || !input) {
			return;
		}
		if (typeof window.paChat === 'undefined' || !window.paChat.endpoint) {
			addBaleRow(panel, form);
			return;
		}
		var cfg = window.paChat;
		var busy = false;

		// The theme's own submit handler is registered first (script order),
		// so replace the form node to drop it and bind the shared engine.
		// When this script is absent, the theme endpoint works unchanged.
		var fresh = form.cloneNode(true);
		form.parentNode.replaceChild(fresh, form);
		form = fresh;
		input = form.querySelector('textarea[name="message"]');
		if (!input) {
			return;
		}
		var submitBtn = form.querySelector('button[type="submit"]');

		function autogrow() {
			input.style.height = 'auto';
			input.style.height = Math.min(input.scrollHeight, 110) + 'px';
		}
		input.addEventListener('input', autogrow);
		input.addEventListener('keydown', function (ev) {
			if (ev.key === 'Enter' && !ev.shiftKey) {
				ev.preventDefault();
				if (form.requestSubmit) {
					form.requestSubmit();
				} else {
					form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
				}
			}
		});

		form.addEventListener('submit', function (ev) {
			ev.preventDefault();
			ev.stopImmediatePropagation();
			var text = (input.value || '').trim();
			if (!text || busy) {
				return;
			}
			busy = true;
			if (submitBtn) {
				submitBtn.disabled = true;
			}
			bubble(messages, 'user', text);
			input.value = '';
			var typing = bubble(messages, 'typing', '···');
			var sid = '';
			try {
				sid = window.localStorage.getItem('pa_sid') || '';
			} catch (e) {
				sid = '';
			}
			fetch(cfg.endpoint, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
				body: JSON.stringify({ message: text, sid: sid })
			})
				.then(function (res) { return res.json().then(function (d) { return { ok: res.ok, d: d }; }); })
				.then(function (out) {
					typing.remove();
					if (out.ok && out.d && out.d.reply) {
						if (out.d.sid) {
							try { window.localStorage.setItem('pa_sid', out.d.sid); } catch (e) {}
						}
						var b = bubble(messages, 'bot', out.d.reply);
						if (out.d.sources && out.d.sources.length && b) {
							var ul = document.createElement('div');
							ul.className = 'pm-ai-sources';
							out.d.sources.slice(0, 3).forEach(function (s) {
								if (!s || !s.url) { return; }
								var a = document.createElement('a');
								a.href = s.url;
								a.target = '_blank';
								a.rel = 'noopener';
								a.textContent = s.title || s.url;
								ul.appendChild(a);
							});
							b.appendChild(ul);
						}
					} else {
						var msg = (out.d && out.d.message) ? out.d.message : 'ارتباط برقرار نشد. لطفاً دوباره تلاش کنید.';
						bubble(messages, 'error', msg);
					}
					busy = false;
					if (submitBtn) {
						submitBtn.disabled = false;
					}
				})
				.catch(function () {
					typing.remove();
					bubble(messages, 'error', 'ارتباط برقرار نشد. لطفاً دوباره تلاش کنید.');
					busy = false;
					if (submitBtn) {
						submitBtn.disabled = false;
					}
				});
		}, true);

		addBaleRow(panel, form);
	});

	function addBaleRow(panel, form) {
		if (typeof window.paBale === 'undefined') {
			return;
		}
		var cfg = window.paBale;
		if (!cfg.bot || panel.querySelector('.pa-bale-row')) {
			return;
		}
		var row = document.createElement('div');
		row.className = 'pa-bale-row';
		var info = document.createElement('span');
		info.className = 'pa-bale-row__hint';
		info.textContent = 'می‌توانید همین گفتگو را در پیام‌رسان بله ادامه دهید.';
		row.appendChild(info);
		if (cfg.loggedIn) {
			var linkBtn = document.createElement('button');
			linkBtn.type = 'button';
			linkBtn.className = 'pa-bale-row__btn';
			linkBtn.textContent = cfg.linkLabel || 'اتصال حساب به بله';
			linkBtn.addEventListener('click', function () {
				linkBtn.disabled = true;
				fetch(cfg.tokenEndpoint, {
					method: 'POST',
					headers: { 'X-WP-Nonce': cfg.restNonce }
				})
					.then(function (res) { return res.json(); })
					.then(function (data) {
						if (data && data.start_param && cfg.bot) {
							info.textContent = 'در بله، ربات @' + cfg.bot + ' را باز کنید و این را بفرستید: /start ' + data.start_param;
						} else {
							info.textContent = 'خطا در ساخت توکن پیوند.';
						}
						linkBtn.disabled = false;
					})
					.catch(function () {
						info.textContent = 'خطا در ساخت توکن پیوند.';
						linkBtn.disabled = false;
					});
			});
			row.appendChild(linkBtn);
		} else {
			var openBtn = document.createElement('button');
			openBtn.type = 'button';
			openBtn.className = 'pa-bale-row__btn';
			openBtn.textContent = cfg.connectLabel || 'ادامه در بله';
			openBtn.addEventListener('click', function () {
				info.textContent = 'ربات @' + cfg.bot + ' را در بله باز کنید و گفتگو را ادامه دهید.';
			});
			row.appendChild(openBtn);
		}
		panel.insertBefore(row, form);
	}
})();
