// Editor: font and dropdown-height controls (settings.style) and session
// undo/redo, with the shipped admin script, real jQuery and a disposable DOM.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { JSDOM } = require('jsdom');
const source = fs.readFileSync('assets/js/admin.js', 'utf8');
const menu = { title: 'Demo', items: [{ id: 'home', label: 'Home', type: 'link', url: '/' }], cta: {}, settings: {} };

const styleFields = `
<details data-abmm-style-group="fonts">
	<select data-abmm-style="font_family_mode"><option value=""></option><option value="inherit"></option><option value="custom"></option></select>
	<label data-abmm-style-when="font_family_mode=custom"><input data-abmm-style="font_family"></label>
	<input type="number" data-abmm-style="top_font_size">
	<select data-abmm-style="top_font_weight"><option value=""></option><option value="500">500</option></select>
	<input type="number" data-abmm-style="top_letter_spacing">
	<select data-abmm-style="top_text_transform"><option value=""></option><option value="uppercase"></option></select>
	<input type="number" data-abmm-style="link_font_size">
	<button type="button" data-abmm-style-reset="fonts">Reset fonts</button>
</details>
<details data-abmm-style-group="height">
	<select data-abmm-style="panel_height_mode"><option value=""></option><option value="auto"></option><option value="max"></option><option value="fixed"></option></select>
	<label data-abmm-style-when="panel_height_mode=max,fixed"><input type="number" data-abmm-style="panel_height" value="520"></label>
	<button type="button" data-abmm-style-reset="height">Reset height</button>
</details>
<button type="button" class="abmm-undo" disabled>Undo</button><button type="button" class="abmm-redo" disabled>Redo</button>`;

async function fixture() {
	const dom = new JSDOM(`<body><div class="abmm-builder-topbar"><button class="abmm-save-menu">Save</button><span class="abmm-save-status"></span></div>
	<div id="abmm-builder" data-menu-id="demo" data-menu-revision="revision-1"><aside id="abmm-builder-sidebar">
	<div class="abmm-builder-sections"><button data-abmm-builder-section="content">content</button><button data-abmm-builder-section="design">design</button></div>
	<div class="abmm-builder-section-group" data-abmm-builder-section-panel="content"></div><div class="abmm-builder-section-group" data-abmm-builder-section-panel="design">${styleFields}</div>
	<input id="abmm-menu-title" value="Demo"><div id="abmm-nav-items"></div><ul class="abmm-readiness-issues"></ul><div class="abmm-readiness-summary"></div></aside>
	<main class="abmm-builder__main"><div class="abmm-preview-shell" data-abmm-preview-viewport="desktop"><div id="abmm-preview"></div></div></main></div>
	<script id="abmm-menu-data" type="application/json">${JSON.stringify(menu)}</script></body>`, { url: 'http://localhost/', runScripts: 'outside-only' });
	const { window } = dom;
	const $ = require('jquery')(window);
	window.jQuery = $;
	window.matchMedia = () => ({ matches: false });
	$.expr.pseudos.visible = (el) => !el.hidden && !el.closest('[hidden]');
	$.fn.wpColorPicker = $.fn.sortable = function () { return this; };
	window.abmmAdmin = { ajaxUrl: '/ajax', nonce: 'test', strings: new Proxy({}, { get: (_, key) => String(key) }), icons: [] };
	const requests = [];
	$.post = (_, data) => { const d = $.Deferred(); requests.push({ data, d }); return d.promise(); };
	window.eval(source);
	window.document.dispatchEvent(new window.Event('DOMContentLoaded'));
	await wait(window, 20);
	return { window, $, requests };
}

function wait(window, ms) { return new Promise((resolve) => window.setTimeout(resolve, ms)); }

function savedSettings($, requests) {
	$('.abmm-save-menu').trigger('click');
	const last = requests.filter((r) => r.data.action === 'abmm_save_menu').pop();
	assert.ok(last, 'a save request is sent');
	last.d.resolve({ success: true, data: { revision: 'r' + requests.length } });
	return JSON.parse(last.data.menu_data).settings;
}

const set = ($, key, value) => $(`[data-abmm-style="${key}"]`).val(value).trigger('change');

(async () => {
	// A menu without style settings gains none from loading or saving.
	let f = await fixture();
	let { window, $, requests } = f;
	assert.equal($('[data-abmm-style-when="font_family_mode=custom"]').prop('hidden'), true, 'Custom font names stay hidden until "Custom font" is chosen.');
	assert.equal($('[data-abmm-style-when="panel_height_mode=max,fixed"]').prop('hidden'), true, 'The height field stays hidden for the default height.');
	assert.equal(savedSettings($, requests).style, undefined, 'An untouched menu saves no style group, even though the height field shows 520.');
	assert.equal($('#abmm-preview-style').length, 0, 'No preview style without settings.');

	// The editor builds exactly the CSS the server prints (see tests/style-settings.php).
	set($, 'font_family_mode', 'inherit');
	set($, 'top_font_size', '18');
	set($, 'top_font_weight', '500');
	set($, 'top_letter_spacing', '0.5');
	set($, 'top_text_transform', 'uppercase');
	set($, 'link_font_size', '15');
	set($, 'panel_height_mode', 'fixed');
	set($, 'panel_height', '420');
	assert.equal($('[data-abmm-style-when="panel_height_mode=max,fixed"]').prop('hidden'), false, 'The height field appears for a fixed height.');
	const scope = '#abmm-preview .abmm-header.abmm-header';
	const expected = 'SCOPE,SCOPE *{font-family:inherit}@media (min-width:901px){SCOPE .abmm-nav__list > .abmm-nav__item > .abmm-nav__link{font-size:18px;font-weight:500;letter-spacing:0.5px;text-transform:uppercase}SCOPE .abmm-mega__col-label,SCOPE .abmm-mega__grid-label{font-size:15px}SCOPE .abmm-mega__panel{height:420px;max-height:420px;overflow-y:auto}}'.split('SCOPE').join(scope);
	assert.equal($('#abmm-preview-style').text(), expected, 'The preview CSS matches the CSS printed on the site.');
	assert.deepEqual(savedSettings($, requests).style, { panel_height_mode: 'fixed', panel_height: 420, font_family_mode: 'inherit', top_font_size: 18, top_font_weight: '500', link_font_size: 15, top_letter_spacing: '0.5', top_text_transform: 'uppercase' }, 'Only the values that were set are saved.');

	// Custom font names are cleaned the same way as on the server.
	set($, 'font_family_mode', 'custom');
	set($, 'font_family', 'Supreme, Arial; } body { color: red');
	assert.equal(savedSettings($, requests).style.font_family, 'Supreme, Arial body color red', 'A font list cannot break out of its CSS rule.');

	// Reset clears one group only.
	$('[data-abmm-style-reset="fonts"]').trigger('click');
	assert.equal($('[data-abmm-style="top_font_size"]').val(), '', 'Reset empties the font fields.');
	assert.deepEqual(savedSettings($, requests).style, { panel_height_mode: 'fixed', panel_height: 420 }, 'Reset fonts keeps the height settings.');
	$('[data-abmm-style-reset="height"]').trigger('click');
	assert.equal(savedSettings($, requests).style, undefined, 'Resetting everything leaves no style group.');
	window.close();

	// Undo and redo for this session.
	f = await fixture();
	({ window, $, requests } = f);
	assert.equal($('.abmm-undo').prop('disabled'), true, 'Nothing to undo after loading.');
	set($, 'top_font_size', '20');
	await wait(window, 450);
	set($, 'top_font_size', '22');
	set($, 'panel_height_mode', 'auto');
	await wait(window, 450);
	assert.equal($('.abmm-undo').prop('disabled'), false, 'Undo is available after a change.');
	$('.abmm-undo').trigger('click');
	assert.equal($('[data-abmm-style="top_font_size"]').val(), '20', 'Undo steps back one pause-separated change.');
	assert.equal($('[data-abmm-style="panel_height_mode"]').val(), '', 'Changes made together are undone together.');
	assert.equal($('.abmm-redo').prop('disabled'), false, 'Redo is available after undo.');
	$('.abmm-redo').trigger('click');
	assert.equal($('[data-abmm-style="top_font_size"]').val(), '22', 'Redo restores the change.');

	const key = (target, init) => target.dispatchEvent(new window.KeyboardEvent('keydown', Object.assign({ bubbles: true, cancelable: true }, init)));
	key(window.document.body, { key: 'z', ctrlKey: true });
	assert.equal($('[data-abmm-style="top_font_size"]').val(), '20', 'Ctrl+Z undoes outside text fields.');
	key(window.document.body, { key: 'z', ctrlKey: true, shiftKey: true });
	assert.equal($('[data-abmm-style="top_font_size"]').val(), '22', 'Ctrl+Shift+Z redoes.');
	key($('#abmm-menu-title')[0], { key: 'z', ctrlKey: true });
	assert.equal($('[data-abmm-style="top_font_size"]').val(), '22', 'Ctrl+Z inside a text field is left to the browser.');

	$('.abmm-undo').trigger('click');
	$('.abmm-undo').trigger('click');
	assert.equal($('[data-abmm-style="top_font_size"]').val(), '', 'Undo goes back to the loaded menu.');
	assert.equal($('.abmm-undo').prop('disabled'), true, 'Undo stops at the loaded menu.');
	set($, 'link_font_size', '14');
	await wait(window, 450);
	assert.equal($('.abmm-redo').prop('disabled'), true, 'A new change after undo clears redo.');
	window.close();

	console.log('Editor style controls and undo/redo: saved values, exact preview CSS, reset, buttons and shortcuts passed.');
})().catch((error) => { console.error(error); process.exit(1); });
