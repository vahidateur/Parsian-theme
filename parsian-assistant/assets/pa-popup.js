/**
 * Shared-engine transport + Bale continuation layer for the Parsian popup.
 *
 * Integration contract: this script never clones or replaces the theme's
 * form. It only registers `window.pmAi.transport = { endpoint, nonce }`
 * (read by ai-chat.js at submit time) and layers the Bale row onto the
 * panel. The theme keeps ownership of starters, focus, Enter handling,
 * autogrow, busy state, error rendering and feedback. When this script is
 * absent, ai-chat.js keeps using the original theme endpoint.
 */
(function () {
	function ready(fn) {
		if (document.readyState !== 'loading') {
			fn();
		} else {
			document.addEventListener('DOMContentLoaded', fn);
		}
	}

	ready(function () {
		if (typeof window.pmAi === 'undefined') {
			return;
		}
		var cfg = typeof window.paChat !== 'undefined' ? window.paChat : null;
		if (cfg && cfg.endpoint) {
			window.pmAi.transport = {
				endpoint: cfg.endpoint,
				nonce: cfg.nonce || window.pmAi.nonce
			};
		}
		var panel = document.querySelector('[data-pm-ai] .pm-ai-panel');
		var form = document.querySelector('[data-pm-ai-form]');
		if (!panel || !form) {
			return;
		}
		addBaleRow(panel, form);
	});

	function addBaleRow(panel, form) {
		if (typeof window.paBale === 'undefined') {
			return;
		}
		var cfg = window.paBale;
		if (!cfg || !cfg.bot || panel.querySelector('.pa-bale-row')) {
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
