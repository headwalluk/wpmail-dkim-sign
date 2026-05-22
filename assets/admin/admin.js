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
})();
