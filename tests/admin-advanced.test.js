'use strict';

// Advanced settings: every setting is classified, nothing active stays hidden
// with no hint, and hiding a control never changes a stored value.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { JSDOM } = require('jsdom');

const moduleSource = fs.readFileSync('assets/js/admin-advanced.js', 'utf8');
const adminSource = fs.readFileSync('assets/js/admin.js', 'utf8');
const builder = fs.readFileSync('admin/views/builder.php', 'utf8');
const dataPhp = fs.readFileSync('includes/class-abmm-data.php', 'utf8');
const classicPhp = fs.readFileSync('includes/class-abmm-classic.php', 'utf8');

function defaultsFrom(php) {
	const start = php.indexOf('function default_settings()');
	const body = php.slice(start, php.indexOf('return array(', start) + php.slice(php.indexOf('return array(', start)).indexOf(');'));
	const settings = {};
	for (const [, key, raw] of body.matchAll(/'([a-z_]+)'\s*=>\s*([^,\n]+),/g)) {
		settings[key] = /^(true|false)$/.test(raw) ? raw === 'true' : /^\d+$/.test(raw) ? Number(raw) : raw.replace(/^'|'$/g, '');
	}
	return settings;
}

const nativeDefaults = defaultsFrom(dataPhp);
const classicDefaults = defaultsFrom(classicPhp);
const baselines = { native: nativeDefaults, classic: classicDefaults };
const dark = { sidebar_bg: '#1e1e24', active_bg: '#2a2a32', panel_bg: '#16161a', header_bg: '#111114', accent: '#5b9fff', text_color: '#f2f2f5', nav_link_color: '#e8e8ed', nav_hover_color: '#5b9fff', muted_color: '#a0a0ab', border_color: '#2e2e36', cta_text: '#ffffff', shadow: 'strong', border_radius: 8 };
const presets = { classic: {}, dark };

function moduleWindow(html = '', options = {}) {
	const dom = new JSDOM(`<!doctype html><body>${html}</body>`, { url: 'http://localhost/', runScripts: 'outside-only' });
	if (options.blockStorage) {
		Object.defineProperty(dom.window, 'localStorage', { get() { throw new Error('blocked'); } });
	}
	dom.window.eval(moduleSource);
	return dom.window;
}

const { SETTINGS, activeKeys, baselineFor } = moduleWindow().ABMMAdvanced;
const entries = Object.keys(SETTINGS);

// Every setting has a tier, wherever it is defined. A new one fails here until it is classified.
{
	const adminSettings = adminSource.slice(adminSource.indexOf('m.settings = {'), adminSource.indexOf('};', adminSource.indexOf('m.settings = {')));
	const found = new Set([
		...Object.keys(nativeDefaults),
		...Object.keys(classicDefaults),
		...[...adminSettings.matchAll(/^\s*([a-z_]+):/gm)].map((match) => match[1]),
		...[...adminSource.matchAll(/m\.settings\.([a-z_]+)\s*=[^=]/g)].map((match) => match[1]),
		...[...dataPhp.matchAll(/\['settings'\]\[\s*'([a-z_]+)'\s*\]/g)].map((match) => match[1]),
	]);
	found.delete('style');
	assert.ok(found.size > 35, 'The scan found the settings.');
	for (const key of found) assert.ok(SETTINGS[key], `${key} needs a tier in SETTINGS (assets/js/admin-advanced.js).`);

	const styleKeys = new Set([
		...[...builder.matchAll(/data-abmm-style-key="([a-z_]+)"/g)].map((match) => match[1]),
		...[...builder.matchAll(/\$abmm_(?:size|weight)\(\s*'([a-z_]+)'/g)].map((match) => match[1]),
	]);
	assert.ok(styleKeys.size > 10, 'The scan found the style keys.');
	for (const key of styleKeys) assert.ok(SETTINGS[`style.${key}`], `style.${key} needs a tier in SETTINGS.`);

	for (const key of entries) assert.ok(['basic', 'advanced', 'internal'].includes(SETTINGS[key].tier), `${key} has a known tier.`);
}

// The closed section holds the advanced controls, and the basic ones stay out of it.
{
	const start = builder.indexOf('id="abmm-advanced-settings"');
	const end = builder.indexOf('</section>', start);
	assert.ok(start > 0 && end > start, 'The section exists on the Design panel.');
	const section = builder.slice(start, end);
	const opens = (section.match(/<details\b/g) || []).length;
	const closes = (section.match(/<\/details>/g) || []).length;
	assert.equal(closes, opens + 1, 'The section closes once, after all its groups.');
	assert.equal(opens, 8, 'It holds the eight groups.');
	assert.doesNotMatch(builder.slice(start - 200, start), /<details[^>]*\bopen\b/, 'It starts closed.');

	// Bar padding and plain markup have no control; the logo's fine-tuning sits with the logo on Content.
	const elsewhere = ['classic_bar_padding', 'classic_plain_markup', 'brand.logo_dark_url', 'brand.logo_height'];
	for (const key of entries) {
		const entry = SETTINGS[key];
		if (!entry.field) continue;
		const id = /^#(.+)$/.exec(entry.field);
		const marker = id ? `id="${id[1]}"` : null;
		const styleKey = key.startsWith('style.') ? key.slice(6) : null;
		const inside = marker ? section.includes(marker) : section.includes(`data-abmm-style-key="${styleKey}"`) || new RegExp(`\\$abmm_(?:size|weight)\\(\\s*'${styleKey}'`).test(section);
		if (entry.tier === 'advanced' && !elsewhere.includes(key)) assert.ok(inside, `${key} sits inside Advanced settings.`);
		if (entry.tier === 'basic') assert.ok(!inside, `${key} stays outside Advanced settings.`);
	}
}

// Honest labels: only a setting the Classic renderer never reads is marked unused.
{
	const body = classicPhp.replace(classicPhp.slice(classicPhp.indexOf('function default_settings()'), classicPhp.indexOf('function site_assets()')), '');
	for (const key of Object.keys(nativeDefaults)) {
		const entry = SETTINGS[key];
		if (entry.tier === 'internal') continue;
		const read = new RegExp(`\\[\\s*'${key}'\\s*\\]`).test(body);
		assert.equal(!!entry.classicUnused, !read, entry.classicUnused ? `${key} is marked unused but Classic reads it.` : `${key} does nothing on Classic, so mark it classicUnused.`);
	}
}

// New menus start in the site's font; existing menus keep what they have.
{
	assert.doesNotMatch(dataPhp.slice(dataPhp.indexOf('function default_settings()'), dataPhp.indexOf('function new_menu_settings()')), /'style'/, 'default_settings fills gaps in existing menus, so it has no style.');
	assert.match(dataPhp.slice(dataPhp.indexOf('function create_unlocked('), dataPhp.indexOf('function create_from_pages(')), /new_menu_settings\(\)/, 'A blank menu uses the new-menu settings.');
	assert.match(dataPhp.slice(dataPhp.indexOf('function generic_starter()')), /new_menu_settings\(\)/, 'The sample menu does too.');
}

const menu = (settings, extra = {}) => ({ settings: { ...nativeDefaults, ...settings }, ...extra });
const active = (settings, extra) => [...activeKeys(menu(settings, extra), baselines, presets)];

// A menu on its preset has nothing to report; a change does.
{
	assert.deepEqual(active({}), [], 'Defaults are not customisations.');
	assert.deepEqual(active({ preset: 'dark', ...dark }), [], 'Choosing a preset is not customising.');
	assert.deepEqual(active({ preset: 'dark', ...dark, accent: '#FF0000' }), ['accent'], 'One changed color is reported.');
	assert.deepEqual(active({ preset: 'dark', ...dark, accent: '#5B9FFF' }), [], 'Color case is not a change.');
	assert.deepEqual(active({ preset: 'custom', accent: '#ff0000' }), ['accent'], 'A custom preset compares with the defaults.');
	// Editing one color turns the preset to Custom; the other colors it set aren't edits.
	assert.deepEqual(active({ preset: 'custom', ...dark, accent: '#FF0000' }), ['accent'], 'One edit on a preset counts as one, not every color it set.');
	assert.deepEqual(active({ preset: 'custom', ...dark }), [], 'A custom menu matching a preset has nothing to report.');
	assert.deepEqual(active({ layout: 'stacked', uppercase_cats: true, shadow: 'none' }).sort(), ['layout', 'shadow', 'uppercase_cats'], 'Layout and style count too.');
	assert.deepEqual(active({ nav_align: 'left', preset: 'classic' }), [], 'Always-visible controls are not advanced.');
}

// A preset without its own menu link colors uses the ones applying it would set.
{
	const base = baselineFor({ preset: 'bare' }, baselines, { bare: { text_color: '#111111', accent: '#222222' } });
	assert.equal(base.nav_link_color, '#111111');
	assert.equal(base.nav_hover_color, '#222222');
}

// Settings that are switched off by another one do nothing, so they do not count.
{
	assert.deepEqual(active({ icon_color: '#123456' }), [], 'Icon color is unused while icons inherit the text color.');
	assert.deepEqual(active({ icon_inherit_text: false, icon_color: '#123456' }).sort(), ['icon_color', 'icon_inherit_text']);
	assert.deepEqual(active({ panel_width: 1200 }), [], 'Panel width is unused at full width.');
	assert.deepEqual(active({ full_width: false, panel_width: 1200 }).sort(), ['full_width', 'panel_width']);
	assert.deepEqual(active({ style: { panel_height: 700 } }), [], 'A height without a mode does nothing.');
	assert.deepEqual(active({ style: { panel_height_mode: 'max', panel_height: 700 } }), ['style.panel_height_mode', 'style.panel_height']);
	assert.deepEqual(active({ style: { panel_height_mode: 'max', panel_height: 520 } }), ['style.panel_height_mode'], 'The default height needs no mention.');
}

// Fonts: the new-menu default is quiet, a real choice is not.
{
	assert.deepEqual(active({ style: { font_family_mode: 'inherit' } }), []);
	assert.deepEqual(active({ style: { font_family_mode: 'custom', font_family: '"Supreme"' } }).sort(), ['style.font_family', 'style.font_family_mode']);
	assert.deepEqual(active({ style: { top_font_size: 18 } }), ['style.top_font_size']);
}

// Classic menus compare with Classic's defaults, and ignore what Classic ignores.
{
	const classic = (settings, extra) => [...activeKeys({ settings: { ...classicDefaults, render_mode: 'emm-classic', ...settings }, ...extra }, baselines, presets)];
	assert.deepEqual(classic({}), [], 'Classic defaults are not customisations.');
	assert.deepEqual(classic({ panel_title_align: 'left' }), ['panel_title_align']);
	assert.deepEqual(classic({ mobile_enhancements: true }), [], 'A setting Classic ignores is not active.');
	assert.deepEqual(active({ mobile_enhancements: true }), ['mobile_enhancements'], 'It is on an Ashbi menu.');
	assert.deepEqual(classic({ classic_edge_bleed: 8, classic_toggle_label: 'Products' }).sort(), ['classic_edge_bleed', 'classic_toggle_label']);
	assert.deepEqual(active({ classic_origin: true, classic_toggle_label: 'Products', classic_edge_bleed: 8 }), [], 'Classic options do nothing on the Ashbi look.');
	assert.deepEqual(classic({ classic_phone_scroll_fix: true }), [], 'The phone scroll fix applies to everyone, so it is not reported.');
}

// Legacy product details on a menu are reported; the empty record is not.
{
	assert.deepEqual(active({}, { context: { show: false, label: '', url: '' } }), []);
	assert.deepEqual(active({}, { context: { show: true, label: 'Payroll', url: '/payroll' } }), ['context']);
}

const page = `
	<nav class="abmm-workflow-nav"><button data-abmm-workflow="content"></button><button data-abmm-workflow="design"></button></nav>
	<div data-abmm-builder-section-panel="content" hidden>
		<label class="abmm-field abmm-field--toggle"><span>Enhanced mobile menu</span><input type="checkbox" id="abmm-mobile-enhancements"></label>
		<details><summary>More logo options</summary><label class="abmm-field"><span>Logo height (px)</span><input type="number" id="abmm-brand-logo-height"></label></details>
	</div>
	<div data-abmm-builder-section-panel="design">
		<button class="abmm-preset-card" data-preset="dark" data-values='${JSON.stringify(dark)}'></button>
		<button class="abmm-preset-card" data-preset="broken" data-values="{oops"></button>
		<button id="open-colors" data-abmm-advanced-open="colors"></button>
		<div id="abmm-advanced-summary"><p class="abmm-advanced-summary__count" role="status"></p><ul class="abmm-advanced-summary__list" hidden></ul></div>
		<details id="abmm-advanced-settings" data-abmm-baselines='${JSON.stringify(baselines)}'>
			<summary>Advanced settings</summary>
			<details data-abmm-advanced-group="colors"><summary>Colors</summary>
				<label class="abmm-field"><span>Accent / button color</span><div class="wp-picker-container"><button type="button" class="wp-color-result">Pick</button><input id="abmm-accent"></div></label>
			</details>
			<details data-abmm-advanced-group="layout"><summary>Layout</summary>
				<label class="abmm-field"><span>Shadow</span><select id="abmm-shadow"><option>none</option></select></label>
				<label class="abmm-field"><span>Corner radius (px)</span><input id="abmm-border-radius"></label>
			</details>
		</details>
	</div>`;

const buttonFor = (window, key) => window.document.querySelector(`[data-abmm-advanced-key="${key}"]`);

// Closed by default, with what is active named outside it.
{
	const window = moduleWindow(page);
	const { refresh } = window.ABMMAdvanced;
	const section = window.document.getElementById('abmm-advanced-settings');
	const count = window.document.querySelector('.abmm-advanced-summary__count');
	const list = window.document.querySelector('.abmm-advanced-summary__list');

	refresh(menu({}));
	assert.equal(section.open, false, 'The section starts closed.');
	assert.equal(count.textContent, '', 'Nothing active: nothing to say.');
	assert.equal(list.hidden, true);

	refresh(menu({ shadow: 'none' }));
	assert.equal(section.open, false, 'A change does not open the section.');
	assert.equal(count.textContent, 'Advanced: 1 setting active');
	assert.equal(buttonFor(window, 'shadow').textContent, 'Shadow', 'The name comes from the control.');

	refresh(menu({ shadow: 'none', border_radius: 2, accent: '#ff0000' }));
	assert.equal(count.textContent, 'Advanced: 3 settings active');
	const badge = (group) => window.document.querySelector(`[data-abmm-advanced-group="${group}"] .abmm-advanced__count`);
	assert.equal(badge('layout').textContent, '2 active', 'A group shows its own count.');
	assert.equal(badge('colors').textContent, '1 active');

	// The name opens the section and the group, and goes to the control.
	buttonFor(window, 'shadow').click();
	assert.equal(section.open, true, 'The name opens the section.');
	assert.equal(window.document.querySelector('[data-abmm-advanced-group="layout"]').open, true, 'And the group.');
	assert.equal(window.document.activeElement, window.document.getElementById('abmm-shadow'), 'And focuses the control.');

	// A color picker hides its input, so its button takes the focus.
	buttonFor(window, 'accent').click();
	assert.equal(window.document.activeElement, window.document.querySelector('.wp-color-result'));

	// Refreshing with the same state keeps focus on a name.
	buttonFor(window, 'shadow').focus();
	const name = buttonFor(window, 'shadow');
	refresh(menu({ shadow: 'none', border_radius: 2, accent: '#ff0000' }));
	assert.equal(buttonFor(window, 'shadow'), name, 'The same state does not rebuild the list.');

	refresh(menu({}));
	assert.equal(badge('layout'), null, 'A group with nothing active loses its count.');
	assert.equal(list.hidden, true);
	window.close();
}

// A setting on another step is still reachable from the summary.
{
	const window = moduleWindow(page);
	window.document.querySelector('[data-abmm-workflow="content"]').addEventListener('click', () => {
		window.document.querySelector('[data-abmm-builder-section-panel="content"]').hidden = false;
	});
	window.ABMMAdvanced.refresh(menu({ mobile_enhancements: true }));
	buttonFor(window, 'mobile_enhancements').click();
	assert.equal(window.document.querySelector('[data-abmm-builder-section-panel="content"]').hidden, false, 'It switches to the step.');
	assert.equal(window.document.activeElement, window.document.getElementById('abmm-mobile-enhancements'));
	window.close();
}

// The logo's fine-tuning is named too, but only while the logo shows.
{
	assert.deepEqual(active({}, { brand: { show: true, logo_dark_url: '', logo_height: 40 } }), [], 'Logo defaults are not customisations.');
	// Stored on an imported logo link with no control: named, never invisible and active.
	assert.deepEqual(active({}, { brand: { show: true, target: '_blank', rel: 'nofollow', class: 'x' } }), ['brand.target', 'brand.rel', 'brand.class'], 'Stored logo link details are listed.');
	assert.deepEqual(active({}, { brand: { show: true, target: '', rel: '', class: '' } }), [], 'Empty logo link details are not.');
	assert.deepEqual(active({}, { brand: { show: false, target: '_blank' } }), [], 'They only count while the logo shows.');
	assert.deepEqual(active({}, { brand: { show: true, logo_dark_url: 'https://example.test/dark.png', logo_height: 60 } }), ['brand.logo_dark_url', 'brand.logo_height'], 'Changed logo options are reported.');
	assert.deepEqual(active({}, { brand: { show: false, logo_dark_url: 'https://example.test/dark.png', logo_height: 60 } }), [], 'A hidden logo has nothing active.');

	const window = moduleWindow(page);
	window.document.querySelector('[data-abmm-workflow="content"]').addEventListener('click', () => {
		window.document.querySelector('[data-abmm-builder-section-panel="content"]').hidden = false;
	});
	window.ABMMAdvanced.refresh(menu({}, { brand: { show: true, logo_dark_url: '', logo_height: 60 } }));
	const button = buttonFor(window, 'brand.logo_height');
	assert.equal(button.textContent, 'Logo height (px)');
	button.click();
	assert.equal(window.document.querySelector('[data-abmm-builder-section-panel="content"]').hidden, false, 'It opens the Content step.');
	assert.equal(window.document.getElementById('abmm-brand-logo-height').closest('details').open, true, 'And the logo options.');
	assert.equal(window.document.activeElement, window.document.getElementById('abmm-brand-logo-height'));
	window.close();
}

// The two phone settings moved into Advanced, in their own group, and the old attribute is gone.
{
	const group = builder.slice(builder.indexOf('data-abmm-advanced-group="mobile"'), builder.indexOf('data-abmm-advanced-group="product-navigation"'));
	assert.ok(group.includes('id="abmm-mobile-enhancements"') && group.includes('id="abmm-classic-toggle-label"'), 'Mobile navigation holds both phone settings.');
	assert.ok(!builder.includes('data-abmm-builder-section="'), 'The stepper is the only navigation.');
	for (const id of ['abmm-classic-bar-padding', 'abmm-classic-plain-markup']) assert.ok(!builder.includes('id="' + id + '"'), id + ' stays out of the editor.');
}

// Active with no control: listed as text, never hidden and never a dead button.
{
	const window = moduleWindow(page);
	window.ABMMAdvanced.refresh({ settings: { ...classicDefaults, render_mode: 'emm-classic', classic_edge_bleed: 8, classic_bar_padding: '0', classic_plain_markup: true }, context: { show: true } });
	const items = [...window.document.querySelectorAll('.abmm-advanced-summary__list li')];
	assert.equal(items.length, 4);
	assert.equal(window.document.querySelectorAll('.abmm-advanced-summary__list button').length, 0, 'Nothing to open, so no button.');
	assert.deepEqual(items.map((item) => item.textContent).sort(), ['Panel edge offset from the import', 'Phone menu bar padding', 'Plain elements instead of header and headings', 'Product details saved on this menu']);
	window.close();
}

// The open state is remembered per browser, and blocked storage does no harm.
{
	let window = moduleWindow(page);
	const key = window.ABMMAdvanced.STORAGE_KEY;
	window.localStorage.setItem(key, '1');
	window.ABMMAdvanced.refresh(menu({}));
	assert.equal(window.document.getElementById('abmm-advanced-settings').open, true, 'It restores an open section.');
	window.close();

	window = moduleWindow(page, { blockStorage: true });
	window.ABMMAdvanced.refresh(menu({ shadow: 'none' }));
	const section = window.document.getElementById('abmm-advanced-settings');
	assert.equal(section.open, false, 'Blocked storage leaves it closed.');
	section.open = true;
	section.dispatchEvent(new window.Event('toggle'));
	assert.equal(buttonFor(window, 'shadow').textContent, 'Shadow', 'And the summary still works.');
	window.close();
}

// The contrast warning goes straight to the colors it is about.
{
	const window = moduleWindow(page);
	window.ABMMAdvanced.refresh(menu({}));
	window.document.getElementById('open-colors').click();
	assert.equal(window.document.getElementById('abmm-advanced-settings').open, true);
	assert.equal(window.document.querySelector('[data-abmm-advanced-group="colors"]').open, true);
	assert.equal(window.document.activeElement, window.document.querySelector('[data-abmm-advanced-group="colors"] summary'));
	window.close();
}

// Classic honesty: the note appears on the Classic look only, and keeps the value.
{
	const window = moduleWindow(page);
	const control = window.document.getElementById('abmm-mobile-enhancements');
	control.checked = true;
	window.ABMMAdvanced.refresh(menu({ mobile_enhancements: true }));
	assert.equal(window.document.querySelector('.abmm-classic-unused'), null, 'No note on the Ashbi look.');
	window.ABMMAdvanced.refresh({ settings: { ...classicDefaults, render_mode: 'emm-classic', mobile_enhancements: true } });
	const note = window.document.querySelector('.abmm-classic-unused');
	assert.equal(note.hidden, false);
	assert.equal(note.textContent, 'Not used by the Classic look.');
	assert.equal(control.checked, true, 'The value is kept.');
	window.ABMMAdvanced.refresh(menu({ mobile_enhancements: true }));
	assert.equal(note.hidden, true, 'Switching back hides it.');
	assert.equal(window.document.querySelectorAll('.abmm-classic-unused').length, 1, 'Only ever one.');
	window.close();
}

// Text goes through wp.i18n with the plugin text domain.
{
	const dom = new JSDOM(`<!doctype html><body>${page}</body>`, { url: 'http://localhost/', runScripts: 'outside-only' });
	const { window } = dom;
	window.wp = { i18n: {
		__: (text, domain) => { assert.equal(domain, 'ashbi-mega-menu'); return `[xx] ${text}`; },
		_n: (single, plural, number, domain) => { assert.equal(domain, 'ashbi-mega-menu'); return `[xx] ${number === 1 ? single : plural}`; },
	} };
	window.eval(moduleSource);
	window.ABMMAdvanced.refresh({ settings: { ...classicDefaults, render_mode: 'emm-classic', mobile_enhancements: true, classic_edge_bleed: 8, shadow: 'none' } });
	assert.equal(window.document.querySelector('.abmm-advanced-summary__count').textContent, '[xx] Advanced: 2 settings active');
	assert.equal(window.document.querySelector('.abmm-advanced__count').textContent, '[xx] 1 active');
	assert.equal(window.document.querySelector('.abmm-classic-unused').textContent, '[xx] Not used by the Classic look.');
	assert.match(window.document.querySelector('.abmm-advanced-summary__list').textContent, /\[xx\] Panel edge offset/);
	window.close();
}

// A page without the section is left alone.
{
	const window = moduleWindow('<p>Other screen</p>');
	window.ABMMAdvanced.refresh(menu({ shadow: 'none' }));
	window.close();
}

// The editor keeps the summary current and opens closed sections for checklist jumps.
(async () => {
	const seed = { title: 'Demo', items: [], cta: {}, settings: { ...nativeDefaults } };
	const dom = new JSDOM(`<body><div class="abmm-builder-topbar"><span class="abmm-save-status"></span></div>
		<div id="abmm-builder" data-menu-id="demo" data-menu-revision="revision-1"><aside id="abmm-builder-sidebar">
		<input id="abmm-menu-title" value="Demo"><div id="abmm-nav-items"></div><ul class="abmm-readiness-issues"></ul><div class="abmm-readiness-summary"></div>
		<details id="outer"><details id="inner"><input id="abmm-shadow" value="none"></details></details></aside>
		<main class="abmm-builder__main"><div class="abmm-preview-shell" data-abmm-preview-viewport="desktop"><div id="abmm-preview"></div></div></main></div>
		<script id="abmm-menu-data" type="application/json">${JSON.stringify(seed)}</script></body>`, { url: 'http://localhost/', runScripts: 'outside-only' });
	const { window } = dom;
	const $ = require('jquery')(window);
	window.jQuery = $;
	window.matchMedia = () => ({ matches: false });
	$.fn.wpColorPicker = $.fn.sortable = function () { return this; };
	window.abmmAdmin = { ajaxUrl: '/ajax', nonce: 'test', strings: new Proxy({}, { get: (_, key) => String(key) }), icons: [] };
	$.post = () => $.Deferred().promise();
	const refreshed = [];
	window.ABMMAdvanced = { refresh: (menuData) => refreshed.push(menuData.settings.shadow) };
	window.eval(adminSource);
	window.document.dispatchEvent(new window.Event('DOMContentLoaded'));
	await new Promise((resolve) => window.setTimeout(resolve, 20));
	assert.ok(refreshed.length > 0, 'The editor refreshes the summary when it renders.');

	$('.abmm-readiness-issues').append('<li><button type="button" data-field="abmm-shadow">Fix</button></li>');
	$('.abmm-readiness-issues button').trigger('click');
	assert.equal(window.document.getElementById('outer').open, true, 'A checklist jump opens the section.');
	assert.equal(window.document.getElementById('inner').open, true, 'And the group.');
	window.close();

	console.log('Advanced settings: every setting classified, active settings named and reachable, Classic labels honest, new-menu font default.');
})().catch((error) => {
	console.error(error);
	process.exit(1);
});
