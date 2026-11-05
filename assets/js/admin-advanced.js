/**
 * Ashbi Mega Menu: the editor's Advanced settings.
 *
 * Presets suit most menus, so the fine controls sit in one closed section. A
 * changed setting must never be hidden and active with no hint: the summary
 * beside the section names each one and opens it.
 *
 * SETTINGS classifies every menu setting. Basic is always shown, advanced is
 * tucked away (in the section, or under "More logo options" on Content), internal
 * has no control. A test fails when a setting has no entry. The logic is pure and
 * exported for tests; `refresh()` wires it to the DOM.
 */
(function (window) {
	'use strict';

	var STORAGE_KEY = 'abmmAdvancedOpen';
	var CLASSIC = 'emm-classic';

	function byId(id) {
		return { tier: 'advanced', field: '#abmm-' + id };
	}

	function classicOption(id) {
		var entry = byId(id);
		entry.when = function (settings) { return settings.render_mode === CLASSIC; };
		return entry;
	}

	var SETTINGS = {
		preset: { tier: 'basic', field: '#abmm-preset' },
		render_mode: { tier: 'basic', field: '#abmm-render-mode' },
		nav_align: { tier: 'basic', field: '#abmm-nav-align' },

		presentation: { tier: 'internal' },
		classic_origin: { tier: 'internal' },
		// The phone scroll fix now applies to every Classic menu, so this does nothing.
		classic_phone_scroll_fix: { tier: 'internal' },

		mobile_enhancements: { tier: 'advanced', field: '#abmm-mobile-enhancements', classicUnused: true },
		layout: byId('layout'),
		grid_columns: byId('grid-cols'),

		nav_link_color: byId('nav-link-color'),
		nav_hover_color: byId('nav-hover-color'),

		icon_inherit_text: byId('icon-inherit-text'),
		icon_color: { tier: 'advanced', field: '#abmm-icon-color', when: function (settings) { return !settings.icon_inherit_text; } },
		icon_hover_color: byId('icon-hover-color'),
		icon_active_color: byId('icon-active-color'),
		icon_size: byId('icon-size'),
		icon_background: byId('icon-background'),
		icon_border_color: byId('icon-border-color'),
		icon_radius: byId('icon-radius'),

		header_bg: byId('header-bg'),
		sidebar_bg: byId('sidebar-bg'),
		active_bg: byId('active-bg'),
		panel_bg: byId('panel-bg'),
		accent: byId('accent'),
		cta_text: byId('cta-text'),
		text_color: byId('text-color'),
		muted_color: byId('muted-color'),
		border_color: byId('border-color'),

		full_width: byId('full-width'),
		header_transparent: byId('header-transparent'),
		header_z_index: byId('header-z-index'),
		panel_width: { tier: 'advanced', field: '#abmm-panel-width', when: function (settings) { return !settings.full_width; } },
		sidebar_width: byId('sidebar-width'),
		border_radius: byId('border-radius'),
		shadow: byId('shadow'),
		cta_style: byId('cta-style'),
		panel_title_align: byId('panel-title-align'),
		uppercase_cats: byId('uppercase-cats'),
		show_cat_desc: byId('show-cat-desc'),

		classic_toggle_label: classicOption('classic-toggle-label'),
		// These two may have no control; a stored value then shows as read-only text.
		classic_bar_padding: classicOption('classic-bar-padding'),
		classic_plain_markup: classicOption('classic-plain-markup'),
		classic_edge_bleed: { tier: 'advanced', when: function (settings) { return settings.render_mode === CLASSIC; } },
	};

	// Fonts and dropdown height, stored in settings.style.
	[
		'font_family', 'top_font_size', 'top_font_weight', 'top_letter_spacing', 'top_text_transform',
		'cat_font_size', 'cat_font_weight', 'link_font_size', 'link_font_weight',
		'desc_font_size', 'desc_font_weight', 'panel_height_mode',
	].forEach(function (key) {
		SETTINGS['style.' + key] = { tier: 'advanced', field: '[data-abmm-style-key="' + key + '"]' };
	});
	SETTINGS['style.font_family'].when = function (settings) { return (settings.style || {}).font_family_mode === 'custom'; };
	// New menus start on "Same as my site", so it is not a customisation.
	SETTINGS['style.font_family_mode'] = { tier: 'advanced', field: '[data-abmm-style-key="font_family_mode"]', normal: ['', 'inherit'] };
	SETTINGS['style.panel_height'] = {
		tier: 'advanced',
		field: '[data-abmm-style-key="panel_height"]',
		normal: ['520'],
		when: function (settings) { return ['max', 'fixed'].indexOf((settings.style || {}).panel_height_mode) !== -1; },
	};

	// Logo fine-tuning, stored in menu.brand. They only count while the logo shows.
	SETTINGS['brand.logo_dark_url'] = { tier: 'advanced', field: '#abmm-brand-logo-dark-url', normal: [''] };
	SETTINGS['brand.logo_height'] = { tier: 'advanced', field: '#abmm-brand-logo-height', normal: ['40'] };
	// Imported with the menu and still used on the logo link, but with no control.
	SETTINGS['brand.target'] = { tier: 'advanced', normal: [''] };
	SETTINGS['brand.rel'] = { tier: 'advanced', normal: [''] };
	SETTINGS['brand.class'] = { tier: 'advanced', normal: [''] };

	function normalize(value) {
		if (value === null || value === undefined || value === false) return '';
		if (value === true) return '1';
		return String(value).trim().toLowerCase();
	}

	function isBrandKey(key) {
		return key.indexOf('brand.') === 0;
	}

	function valueOf(menu, key) {
		var settings = menu.settings || {};
		if (isBrandKey(key)) return (menu.brand || {})[key.slice(6)];
		if (key.indexOf('style.') !== 0) return settings[key];
		return (settings.style || {})[key.slice(6)];
	}

	/** Defaults for the menu's look, then the chosen preset, as applying that preset would set them. */
	function baselineFor(settings, baselines, presets) {
		var defaults = (baselines || {})[settings.render_mode === CLASSIC ? 'classic' : 'native'] || {};
		var preset = (presets || {})[settings.preset] || {};
		var base = {};
		[defaults, preset].forEach(function (source) {
			Object.keys(source).forEach(function (key) { base[key] = source[key]; });
		});
		if (!preset.nav_link_color && preset.text_color) base.nav_link_color = preset.text_color;
		if (!preset.nav_hover_color && preset.accent) base.nav_hover_color = preset.accent;
		return base;
	}

	function hasLegacyContext(menu) {
		var context = menu.context || {};
		return !!(context.show || normalize(context.label) || normalize(context.url));
	}

	function keysDifferingFrom(menu, base) {
		var settings = menu.settings || {};
		var classic = settings.render_mode === CLASSIC;
		var keys = Object.keys(SETTINGS).filter(function (key) {
			var entry = SETTINGS[key];
			if (entry.tier !== 'advanced') return false;
			if (entry.classicUnused && classic) return false;
			if (entry.when && !entry.when(settings)) return false;
			if (isBrandKey(key) && !(menu.brand || {}).show) return false;
			var value = valueOf(menu, key);
			if (value === undefined) return false;
			if (entry.normal && entry.normal.indexOf(normalize(value)) !== -1) return false;
			return normalize(value) !== normalize(base[key]);
		});
		if (hasLegacyContext(menu)) keys.push('context');
		return keys;
	}

	/**
	 * Keys of the advanced settings that change how this menu looks or works. Editing
	 * a color turns the preset to Custom, which has no values of its own, so compare
	 * with the closest preset: one change must not count every color a preset set.
	 */
	function activeKeys(menu, baselines, presets) {
		var settings = menu.settings || {};
		var known = presets || {};
		if (known[settings.preset]) return keysDifferingFrom(menu, baselineFor(settings, baselines, known));
		var closest = keysDifferingFrom(menu, baselineFor({ render_mode: settings.render_mode }, baselines, known));
		Object.keys(known).forEach(function (name) {
			var keys = keysDifferingFrom(menu, baselineFor({ render_mode: settings.render_mode, preset: name }, baselines, known));
			if (keys.length < closest.length) closest = keys;
		});
		return closest;
	}

	function strings() {
		var i18n = window.wp && window.wp.i18n ? window.wp.i18n : {};
		return {
			__: i18n.__ || function (text) { return text; },
			_n: i18n._n || function (single, plural, number) { return number === 1 ? single : plural; },
		};
	}

	/** Settings with no control have no label on the page to read. */
	function readOnlyLabel(key) {
		var __ = strings().__;
		var labels = {
			context: __('Product details saved on this menu', 'ashbi-mega-menu'),
			classic_edge_bleed: __('Panel edge offset from the import', 'ashbi-mega-menu'),
			classic_bar_padding: __('Phone menu bar padding', 'ashbi-mega-menu'),
			classic_plain_markup: __('Plain elements instead of header and headings', 'ashbi-mega-menu'),
			'brand.target': __('Logo link opens in a new tab', 'ashbi-mega-menu'),
			'brand.rel': __('Logo link relationship', 'ashbi-mega-menu'),
			'brand.class': __('Logo link CSS classes', 'ashbi-mega-menu'),
		};
		return labels[key] || key;
	}

	function controlFor(key) {
		var entry = SETTINGS[key];
		return entry && entry.field ? window.document.querySelector(entry.field) : null;
	}

	function fieldWrapper(control) {
		return control.closest('.abmm-field') || control.parentNode;
	}

	function labelFor(key) {
		var control = controlFor(key);
		if (!control) return readOnlyLabel(key);
		var wrapper = fieldWrapper(control);
		var label = wrapper.querySelector('span, label');
		return (label && label.textContent.trim()) || readOnlyLabel(key);
	}

	/** A color picker hides its input, so its button takes the focus. */
	function focusTarget(control) {
		var picker = control.closest('.wp-picker-container');
		return (picker && picker.querySelector('.wp-color-result')) || control;
	}

	function reveal(element) {
		var panel = element.closest('[data-abmm-builder-section-panel]');
		if (panel && panel.hidden) {
			var section = panel.getAttribute('data-abmm-builder-section-panel');
			var step = window.document.querySelector('[data-abmm-workflow="' + section + '"]');
			if (step) step.click();
		}
		for (var node = element.parentNode; node && node.nodeType === 1; node = node.parentNode) {
			if (node.tagName === 'DETAILS') node.open = true;
		}
	}

	function goTo(element) {
		reveal(element);
		if (element.scrollIntoView) element.scrollIntoView({ block: 'center' });
		element.focus();
	}

	function loadOpen() {
		try { return window.localStorage.getItem(STORAGE_KEY) === '1'; } catch (error) { return false; }
	}

	function saveOpen(open) {
		try { window.localStorage.setItem(STORAGE_KEY, open ? '1' : '0'); } catch (error) { /* Private mode: the choice lasts for this page. */ }
	}

	var view = null;

	function readPresets(document) {
		var presets = {};
		Array.prototype.forEach.call(document.querySelectorAll('.abmm-preset-card[data-values]'), function (card) {
			try { presets[card.getAttribute('data-preset')] = JSON.parse(card.getAttribute('data-values')); } catch (error) { /* A broken card simply has no baseline. */ }
		});
		return presets;
	}

	function bind() {
		var document = window.document;
		var section = document.getElementById('abmm-advanced-settings');
		var summary = document.getElementById('abmm-advanced-summary');
		if (!section || !summary) return null;
		var baselines = {};
		try { baselines = JSON.parse(section.getAttribute('data-abmm-baselines')) || {}; } catch (error) { baselines = {}; }
		section.open = loadOpen();
		section.addEventListener('toggle', function () { saveOpen(section.open); });
		summary.addEventListener('click', function (event) {
			var button = event.target.closest('[data-abmm-advanced-key]');
			if (!button) return;
			var control = controlFor(button.getAttribute('data-abmm-advanced-key'));
			if (control) goTo(focusTarget(control));
		});
		document.addEventListener('click', function (event) {
			var opener = event.target.closest('[data-abmm-advanced-open]');
			if (!opener) return;
			var group = document.querySelector('[data-abmm-advanced-group="' + opener.getAttribute('data-abmm-advanced-open') + '"]');
			if (group) goTo(group.querySelector('summary'));
		});
		return {
			section: section,
			summary: summary,
			count: summary.querySelector('.abmm-advanced-summary__count'),
			list: summary.querySelector('.abmm-advanced-summary__list'),
			baselines: baselines,
			presets: readPresets(document),
			signature: null,
		};
	}

	function renderSummary(keys) {
		var document = window.document;
		var text = strings();
		var signature = keys.join();
		if (view.signature === signature) return;
		view.signature = signature;
		/* translators: %d: how many advanced settings differ from the preset. */
		view.count.textContent = keys.length ? text._n('Advanced: %d setting active', 'Advanced: %d settings active', keys.length, 'ashbi-mega-menu').replace('%d', keys.length) : '';
		view.list.textContent = '';
		keys.forEach(function (key) {
			var item = document.createElement('li');
			var name;
			if (controlFor(key)) {
				name = document.createElement('button');
				name.type = 'button';
				name.className = 'button-link';
				name.setAttribute('data-abmm-advanced-key', key);
			} else {
				name = document.createElement('span');
			}
			name.textContent = labelFor(key);
			item.appendChild(name);
			view.list.appendChild(item);
		});
		view.list.hidden = !keys.length;

		var counts = new Map();
		keys.forEach(function (key) {
			var control = controlFor(key);
			var group = control ? control.closest('[data-abmm-advanced-group]') : null;
			if (group) counts.set(group, (counts.get(group) || 0) + 1);
		});
		Array.prototype.forEach.call(view.section.querySelectorAll('[data-abmm-advanced-group]'), function (group) {
			var title = group.querySelector('summary');
			var badge = title.querySelector('.abmm-advanced__count');
			var count = counts.get(group) || 0;
			if (!count) {
				if (badge) badge.parentNode.removeChild(badge);
				return;
			}
			if (!badge) {
				badge = document.createElement('span');
				badge.className = 'abmm-advanced__count';
				title.appendChild(badge);
			}
			/* translators: %d: how many settings in this group differ from the preset. */
			badge.textContent = text.__('%d active', 'ashbi-mega-menu').replace('%d', count);
		});
	}

	/** Say so beside controls the Classic look ignores, keeping their values. */
	function renderClassicNotes(settings) {
		var document = window.document;
		var classic = settings.render_mode === CLASSIC;
		Object.keys(SETTINGS).forEach(function (key) {
			if (!SETTINGS[key].classicUnused) return;
			var control = controlFor(key);
			if (!control) return;
			var wrapper = fieldWrapper(control);
			var note = wrapper.nextElementSibling;
			if (!note || !note.classList.contains('abmm-classic-unused')) {
				if (!classic) return;
				note = document.createElement('p');
				note.className = 'description abmm-classic-unused';
				note.textContent = strings().__('Not used by the Classic look.', 'ashbi-mega-menu');
				wrapper.parentNode.insertBefore(note, wrapper.nextSibling);
			}
			note.hidden = !classic;
		});
	}

	/** Called whenever the form or the menu changes. */
	function refresh(menu) {
		if (!view) view = bind();
		if (!view) return;
		renderSummary(activeKeys(menu, view.baselines, view.presets));
		renderClassicNotes(menu.settings || {});
	}

	window.ABMMAdvanced = {
		SETTINGS: SETTINGS,
		STORAGE_KEY: STORAGE_KEY,
		baselineFor: baselineFor,
		activeKeys: activeKeys,
		refresh: refresh,
	};
})(window);
