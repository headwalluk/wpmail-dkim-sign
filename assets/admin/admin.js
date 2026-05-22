/*
 * WP Mail DKIM Sign — admin JS
 *
 * - Hash-based tab navigation for the settings page.
 * - JS confirm() on buttons carrying a data-wpdkm-confirm attribute.
 */

(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		initTabs();
		initConfirms();
		initCopyButtons();
	});

	function initTabs() {
		var tabs = document.querySelectorAll('.wpdkm-settings .nav-tab');
		var panels = document.querySelectorAll('.wpdkm-settings .tab-panel');

		if (tabs.length === 0 || panels.length === 0) {
			return;
		}

		function activate(name) {
			tabs.forEach(function (t) {
				t.classList.toggle('nav-tab-active', t.dataset.tab === name);
			});
			panels.forEach(function (p) {
				p.style.display = (p.id === name + '-panel') ? '' : 'none';
			});
		}

		var initial = window.location.hash.replace(/^#/, '') || tabs[0].dataset.tab;
		activate(initial);

		tabs.forEach(function (tab) {
			tab.addEventListener('click', function (e) {
				e.preventDefault();
				var name = tab.dataset.tab;
				window.location.hash = name;
				activate(name);
			});
		});

		window.addEventListener('hashchange', function () {
			var name = window.location.hash.replace(/^#/, '') || tabs[0].dataset.tab;
			activate(name);
		});
	}

	function initConfirms() {
		var buttons = document.querySelectorAll('[data-wpdkm-confirm]');
		buttons.forEach(function (btn) {
			btn.addEventListener('click', function (e) {
				var message = btn.dataset.wpdkmConfirm;
				if (!window.confirm(message)) {
					e.preventDefault();
				}
			});
		});
	}

	function initCopyButtons() {
		var buttons = document.querySelectorAll('.wpdkm-copy-button');
		buttons.forEach(function (btn) {
			btn.addEventListener('click', function () {
				var selector = btn.dataset.wpdkmCopyTarget;
				if (!selector) {
					return;
				}
				var target = document.querySelector(selector);
				if (!target) {
					return;
				}
				copyText(target.value)
					.then(function () { flashButton(btn, 'Copied'); })
					.catch(function () { flashButton(btn, 'Copy failed'); });
			});
		});
	}

	function copyText(text) {
		if (navigator.clipboard && navigator.clipboard.writeText) {
			return navigator.clipboard.writeText(text);
		}
		return new Promise(function (resolve, reject) {
			try {
				var ta = document.createElement('textarea');
				ta.value = text;
				ta.setAttribute('readonly', '');
				ta.style.position = 'absolute';
				ta.style.left = '-9999px';
				document.body.appendChild(ta);
				ta.select();
				var ok = document.execCommand('copy');
				document.body.removeChild(ta);
				if (ok) { resolve(); } else { reject(new Error('execCommand failed')); }
			} catch (err) {
				reject(err);
			}
		});
	}

	function flashButton(btn, label) {
		var original = btn.textContent;
		btn.textContent = label;
		btn.disabled = true;
		setTimeout(function () {
			btn.textContent = original;
			btn.disabled = false;
		}, 1500);
	}
})();
