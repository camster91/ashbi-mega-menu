'use strict';

// The editor's guide: when it starts by itself, dismissal, skipped steps, keyboard
// use and focus, storage failure and the "What's this?" hints, using the shipped
// script in a disposable DOM.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');

const root = path.join(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'assets', 'js', 'admin-guide.js'), 'utf8');
const builderView = fs.readFileSync(path.join(root, 'admin', 'views', 'builder.php'), 'utf8');
const adminPhp = fs.readFileSync(path.join(root, 'includes', 'class-abmm-admin.php'), 'utf8');

const DATA = {
	docsUrl: 'https://example.test/docs/',
	labels: { next: 'Next', back: 'Back', skip: 'Skip', done: 'Done', close: 'Close', readMore: 'Read more', newTab: '(new tab)', stepCount: 'Step %1$d of %2$d', whatsThis: "What's this? %s" },
	steps: {
		links: { title: 'Add your links', body: 'Links body' },
		save: { title: 'Save your work', body: 'Save body' },
		place: { title: 'Put it on your site', body: 'Place body' },
		check: { title: 'Check it', body: 'Check body' },
	},
	hints: {
		advanced: { title: 'Advanced', body: 'Advanced hint' },
		// Test-only entries, so each way of placing a hint keeps its coverage.
		place: { title: 'Placing a menu', body: 'Place hint' },
		dropdown: { title: 'Dropdowns', body: 'Dropdown hint' },
		'site-look': { title: 'Site look', body: 'Look hint' },
		history: { title: 'History', body: 'History hint' },
	},
};

const PAGE = `<!doctype html><body>
<input id="outside">
<div id="abmm-builder-load-error" hidden></div>
<div id="abmm-draft-recovery" hidden></div>
<div id="abmm-builder">
	<div id="content-panel"><button class="abmm-add-nav-item">Add item</button><ul id="abmm-nav-items"></ul></div>
	<button class="abmm-save-menu">Save</button>
	<button data-abmm-workflow="content">Edit</button>
	<button data-abmm-workflow="help">Place</button>
	<button data-abmm-workflow="verify">Check</button>
	<h2 id="abmm-placement-title">Place this menu</h2>
	<p class="abmm-mega-style-picker__label">Layout</p>
	<label id="look-label"><span>Site look</span><select id="abmm-render-mode"></select></label>
	<details id="abmm-backup-panel"><summary>Snapshots</summary><div>List</div></details>
	<button id="start" data-abmm-guide-start hidden>Show the guide again</button>
</div>
<div class="abmm-exit-dialog" hidden></div>
<div id="abmm-icon-modal" hidden></div>
</body>`;

function page({ menu = { items: [] }, html = PAGE, url = 'https://example.test/wp-admin/' } = {}) {
	const dom = new JSDOM(html, { runScripts: 'outside-only', url, pretendToBeVisual: true });
	const { window } = dom;
	// jsdom has no layout: everything measures as shown unless it is display:none.
	window.HTMLElement.prototype.getBoundingClientRect = () => ({ width: 100, height: 30, top: 20, bottom: 50, left: 20, right: 120 });
	if (menu) {
		const node = window.document.createElement('script');
		node.type = 'application/json';
		node.id = 'abmm-menu-data';
		node.textContent = typeof menu === 'string' ? menu : JSON.stringify(menu);
		window.document.body.appendChild(node);
	}
	window.eval(source);
	return window;
}

// The shipped table has one hint. These exercise the other placements it supports.
const TEST_HINTS = [
	{ id: 'place', selectors: ['#abmm-placement-title'], where: 'append', doc: 'user-guide.md#put-it-in-a-header' },
	{ id: 'dropdown', selectors: ['.abmm-mega-style-picker__label'], where: 'append', doc: 'user-guide.md' },
	{ id: 'history', selectors: ['#abmm-backup-panel > summary'], where: 'after', doc: 'undo-and-history.md' },
	{ id: 'site-look', selectors: ['#abmm-render-mode'], closest: 'label', where: 'before', doc: 'migrate-from-easy-mega-menu.md' },
];
const pageWithHints = (options) => {
	const window = page(options);
	window.ABMMAdminGuide.HINTS.push(...TEST_HINTS);
	return window;
};

const key = (window, name, extra = {}) => {
	const event = new window.KeyboardEvent('keydown', { key: name, bubbles: true, cancelable: true, ...extra });
	window.document.activeElement.dispatchEvent(event);
	return event;
};
const dialog = (window) => window.document.querySelector('.abmm-guide--tour');
const state = (window) => JSON.parse(window.localStorage.getItem(window.ABMMAdminGuide.STORAGE_KEY) || '{}');
const breakStorage = (window) => Object.defineProperty(window, 'localStorage', { configurable: true, get() { throw new window.Error('blocked'); } });

// The tables stay short, and each tour step points at something in the editor.
{
	const G = page().ABMMAdminGuide;
	assert.ok(G.TOUR_STEPS.length <= 6, 'The tour stays short.');
	for (const step of G.TOUR_STEPS) {
		const found = step.selectors.some((selector) => builderView.includes(selector.replace(/^[.#[]|\]$/g, '')));
		assert.ok(found, `Tour step ${step.id} points at an element in the editor.`);
	}
	for (const item of [...G.TOUR_STEPS, ...G.HINTS]) {
		assert.ok(new RegExp(`'${item.id}'\\s*=>\\s*array\\(`).test(adminPhp), `${item.id} has its wording in PHP.`);
	}
	// Quiet by design: a hint only where the screen can't say it itself.
	assert.deepEqual([...G.HINTS].map((item) => item.id), ['advanced'], 'Only the Advanced settings hint ships.');
	assert.equal(G.HINTS[0].selectors[0], '#abmm-advanced-settings');
	assert.ok(builderView.includes('id="abmm-advanced-settings"'), 'The hint points at an element in the editor.');
	for (const item of G.HINTS) {
		const file = path.join(root, 'docs', item.doc.split('#')[0]);
		assert.ok(fs.existsSync(file), `${item.doc} exists.`);
		const anchor = item.doc.split('#')[1];
		if (anchor) assert.ok(new RegExp(`^#+ ${anchor.replace(/-/g, '[ -]')}\\s*$`, 'im').test(fs.readFileSync(file, 'utf8')), `${item.doc} has that heading.`);
	}
	for (const id of ['place', 'dropdown', 'site-look', 'history']) assert.ok(!new RegExp(`'hints'[\\s\\S]*'${id}'\\s*=>\\s*array\\(`).test(adminPhp), `The ${id} hint's wording is gone.`);
	assert.ok(/data-abmm-guide-start/.test(builderView), 'The Help area has the show-again control.');
	assert.ok(/hidden/.test(builderView.split('data-abmm-guide-start')[1].split('>')[0]), 'It stays hidden until the script is running.');
}

// Empty or sample menus count as having no content; anything unclear stays quiet.
{
	const check = (menu) => page({ menu }).ABMMAdminGuide.hasMenuContent(page({ menu }).document);
	assert.equal(check({ items: [] }), false, 'An empty menu has no content.');
	assert.equal(check({ items: [{ id: 'a' }] }), true, 'A menu with items has content.');
	assert.equal(check({ items: [{ id: 'a' }], starter_source: 'sample' }), false, 'A sample still needs replacing.');
	assert.equal(check('{not json'), true, 'Unreadable data stays quiet.');
	assert.equal(check(null), true, 'Missing data stays quiet.');
}

// Auto-start rules.
{
	const auto = (window) => window.ABMMAdminGuide.create({ window, data: DATA }).maybeAutoStart();

	let window = page();
	assert.equal(auto(window), true, 'An empty menu starts the tour.');
	assert.ok(dialog(window), 'The tour shows.');

	window = page({ menu: { items: [{ id: 'a' }] } });
	assert.equal(auto(window), false, 'A menu with content does not start the tour.');
	assert.equal(dialog(window), null);

	window = page();
	window.localStorage.setItem(window.ABMMAdminGuide.STORAGE_KEY, JSON.stringify({ status: 'dismissed' }));
	assert.equal(auto(window), false, 'A dismissed tour does not start again.');

	window = page();
	window.localStorage.setItem(window.ABMMAdminGuide.STORAGE_KEY, JSON.stringify({ status: 'done' }));
	assert.equal(auto(window), false, 'A finished tour does not start again.');

	for (const blocker of ['abmm-builder-load-error', 'abmm-draft-recovery', 'abmm-icon-modal']) {
		window = page();
		window.document.getElementById(blocker).hidden = false;
		assert.equal(auto(window), false, `The tour does not open over ${blocker}.`);
		assert.equal(state(window).status, undefined, 'Being blocked does not count as a dismissal.');
	}

	window = page();
	window.document.querySelector('.abmm-exit-dialog').hidden = false;
	assert.equal(auto(window), false, 'The tour does not open over the exit dialog.');

	window = page({ html: '<!doctype html><body><p>Menu list</p></body>' });
	assert.equal(auto(window), false, 'The tour needs the editor.');

	// A saved step carries on after a reload.
	window = page();
	window.localStorage.setItem(window.ABMMAdminGuide.STORAGE_KEY, JSON.stringify({ status: 'active', step: 'place' }));
	assert.equal(auto(window), true);
	assert.equal(dialog(window).querySelector('.abmm-guide__title').textContent, 'Put it on your site');
}

// The dialog itself: labelled, focused, and pointing at its target.
{
	const window = page();
	const doc = window.document;
	const opener = doc.getElementById('outside');
	opener.focus();
	const guide = window.ABMMAdminGuide.create({ window, data: DATA });
	assert.equal(guide.start(false), true);
	const panel = dialog(window);
	assert.equal(panel.getAttribute('role'), 'dialog');
	assert.equal(panel.getAttribute('aria-modal'), 'false', 'The page stays usable during the tour.');
	assert.equal(doc.getElementById(panel.getAttribute('aria-labelledby')).textContent, 'Add your links');
	assert.equal(doc.getElementById(panel.getAttribute('aria-describedby')).textContent, 'Links body');
	assert.equal(doc.activeElement, panel.querySelector('.abmm-guide__title'), 'Focus moves into the guide.');
	assert.equal(panel.querySelector('.abmm-guide__count').textContent, 'Step 1 of 4');
	assert.equal(panel.querySelector('.abmm-guide__back').hidden, true, 'The first step has no Back.');
	assert.ok(doc.querySelector('.abmm-add-nav-item').classList.contains('abmm-guide-target'));
	for (const control of panel.querySelectorAll('button')) assert.equal(control.type, 'button', 'Controls are real buttons.');

	panel.querySelector('.abmm-guide__next').click();
	assert.equal(panel.querySelector('.abmm-guide__count').textContent, 'Step 2 of 4');
	assert.equal(state(window).step, 'save', 'Progress is saved.');
	assert.equal(state(window).status, 'active');
	assert.ok(!doc.querySelector('.abmm-add-nav-item').classList.contains('abmm-guide-target'), 'The highlight moves on.');
	assert.ok(doc.querySelector('.abmm-save-menu').classList.contains('abmm-guide-target'));
	assert.equal(doc.activeElement, panel.querySelector('.abmm-guide__title'), 'Focus stays in the guide on each step.');

	panel.querySelector('.abmm-guide__back').click();
	assert.equal(panel.querySelector('.abmm-guide__title').textContent, 'Add your links', 'Back returns a step.');

	// Tab stays inside the guide.
	const next = panel.querySelector('.abmm-guide__next');
	next.focus();
	const forward = key(window, 'Tab');
	assert.ok(forward.defaultPrevented, 'Tab wraps at the end.');
	assert.equal(doc.activeElement, panel.querySelector('.abmm-guide__skip'));
	key(window, 'Tab', { shiftKey: true });
	assert.equal(doc.activeElement, next, 'Shift+Tab wraps at the start.');

	// Done on the last step ends it and returns focus.
	panel.querySelector('.abmm-guide__next').click();
	panel.querySelector('.abmm-guide__next').click();
	panel.querySelector('.abmm-guide__next').click();
	assert.equal(panel.querySelector('.abmm-guide__next').textContent, 'Done');
	panel.querySelector('.abmm-guide__next').click();
	assert.equal(dialog(window), null, 'Done closes the tour.');
	assert.equal(state(window).status, 'done');
	assert.equal(doc.activeElement, opener, 'Focus returns to where it was.');
	assert.equal(doc.querySelector('.abmm-guide-target'), null, 'No highlight is left behind.');
	assert.equal(guide.maybeAutoStart(), false, 'A finished tour stays away.');
}

// Escape and Skip both dismiss for good, and focus returns.
{
	let window = page();
	let opener = window.document.getElementById('outside');
	opener.focus();
	let guide = window.ABMMAdminGuide.create({ window, data: DATA });
	guide.start(false);
	const event = key(window, 'Escape');
	assert.ok(event.defaultPrevented);
	assert.equal(dialog(window), null, 'Escape closes the tour.');
	assert.equal(window.document.activeElement, opener, 'Escape returns focus.');
	assert.equal(state(window).status, 'dismissed');
	assert.equal(guide.maybeAutoStart(), false, 'A dismissed tour stays away.');

	window = page();
	opener = window.document.getElementById('outside');
	opener.focus();
	guide = window.ABMMAdminGuide.create({ window, data: DATA });
	guide.start(false);
	dialog(window).querySelector('.abmm-guide__skip').click();
	assert.equal(dialog(window), null, 'Skip closes the tour.');
	assert.equal(state(window).status, 'dismissed');
	assert.equal(window.document.activeElement, opener);

	// Nothing to close: Escape belongs to the page.
	window = page();
	window.ABMMAdminGuide.create({ window, data: DATA });
	window.document.getElementById('outside').focus();
	assert.equal(key(window, 'Escape').defaultPrevented, false, 'A closed guide ignores Escape.');
}

// A missing or hidden target is skipped, and the count stays honest.
{
	let window = page();
	window.document.querySelector('.abmm-save-menu').remove();
	let guide = window.ABMMAdminGuide.create({ window, data: DATA });
	guide.start(false);
	assert.equal(dialog(window).querySelector('.abmm-guide__count').textContent, 'Step 1 of 3');
	guide.next();
	assert.equal(dialog(window).querySelector('.abmm-guide__title').textContent, 'Put it on your site', 'A missing target is skipped.');

	window = page();
	window.document.querySelector('[data-abmm-workflow="help"]').style.display = 'none';
	window.HTMLElement.prototype.getBoundingClientRect = function () {
		return this.style.display === 'none' ? { width: 0, height: 0, top: 0, bottom: 0, left: 0, right: 0 } : { width: 100, height: 30, top: 20, bottom: 50, left: 20, right: 120 };
	};
	guide = window.ABMMAdminGuide.create({ window, data: DATA });
	guide.start(false);
	guide.next();
	guide.next();
	assert.equal(dialog(window).querySelector('.abmm-guide__title').textContent, 'Check it', 'A hidden target is skipped.');

	// The last steps gone: the tour ends instead of sticking.
	window = page();
	window.document.querySelector('[data-abmm-workflow="help"]').remove();
	window.document.querySelector('[data-abmm-workflow="verify"]').remove();
	guide = window.ABMMAdminGuide.create({ window, data: DATA });
	guide.start(false);
	guide.next();
	guide.next();
	assert.equal(dialog(window), null, 'Running out of targets ends the tour.');
	assert.equal(state(window).status, 'done');

	// No targets at all: nothing opens.
	window = page({ html: '<!doctype html><body><div id="abmm-builder"></div></body>' });
	guide = window.ABMMAdminGuide.create({ window, data: DATA });
	assert.equal(guide.start(false), false, 'With no targets the tour does not open.');
	assert.equal(dialog(window), null);
}

// A hidden first target can be revealed by its control.
{
	const window = page();
	const doc = window.document;
	doc.getElementById('content-panel').hidden = true;
	let clicks = 0;
	doc.querySelector('[data-abmm-workflow="content"]').addEventListener('click', () => {
		clicks += 1;
		doc.getElementById('content-panel').hidden = false;
	});
	window.ABMMAdminGuide.create({ window, data: DATA }).start(false);
	assert.equal(clicks, 1, 'The control that shows the target is used.');
	assert.equal(dialog(window).querySelector('.abmm-guide__title').textContent, 'Add your links');
}

// A dialog opening mid-tour wins, without focus being pulled away from it.
{
	const window = page();
	const doc = window.document;
	window.ABMMAdminGuide.create({ window, data: DATA }).start(false);
	const modal = doc.getElementById('abmm-icon-modal');
	modal.hidden = false;
	modal.tabIndex = -1;
	modal.focus();
	assert.equal(dialog(window), null, 'The tour steps aside for a dialog.');
	assert.equal(doc.activeElement, modal, 'Focus stays in the dialog.');
}

// Storage failure: no errors, manual use works, and it doesn't nag.
{
	const window = page();
	breakStorage(window);
	const guide = window.ABMMAdminGuide.create({ window, data: DATA });
	assert.equal(window.ABMMAdminGuide.shouldAutoStart(window), false, 'With no storage a dismissal cannot be remembered, so no auto-start.');
	assert.equal(guide.maybeAutoStart(), false);
	assert.equal(guide.start(false), true, 'The tour still opens by hand.');
	guide.next();
	assert.equal(dialog(window).querySelector('.abmm-guide__title').textContent, 'Save your work');
	key(window, 'Escape');
	assert.equal(dialog(window), null, 'It still closes.');
}

// The show-again control appears once the script runs, and overrides a dismissal.
{
	const window = page();
	const doc = window.document;
	const start = doc.getElementById('start');
	window.localStorage.setItem(window.ABMMAdminGuide.STORAGE_KEY, JSON.stringify({ status: 'dismissed' }));
	assert.equal(start.hidden, true);
	window.ABMMAdminGuide.create({ window, data: DATA });
	assert.equal(start.hidden, false, 'It shows once the script is running.');
	start.focus();
	start.click();
	assert.equal(dialog(window).querySelector('.abmm-guide__title').textContent, 'Add your links', 'It starts from the beginning.');
	key(window, 'Escape');
	assert.equal(doc.activeElement, start, 'Focus returns to it.');
}

// Hints: placed safely, one at a time, with a real link, and Escape returns focus.
{
	const window = pageWithHints();
	const doc = window.document;
	const guide = window.ABMMAdminGuide.create({ window, data: DATA });
	const hint = (id) => doc.querySelector(`[data-abmm-guide-hint="${id}"]`);
	const panel = () => doc.getElementById('abmm-guide-hint-panel');

	assert.ok(doc.getElementById('abmm-placement-title').contains(hint('place')), 'The place hint sits in its heading.');
	assert.ok(doc.querySelector('.abmm-mega-style-picker__label').contains(hint('dropdown')));
	assert.equal(doc.getElementById('look-label').contains(hint('site-look')), false, 'A hint never goes inside a label, where it would take over the control.');
	assert.ok(hint('site-look').classList.contains('abmm-guide-hint--corner'), 'It sits on the label row of the field after it.');
	assert.equal(hint('site-look').nextElementSibling, doc.getElementById('look-label'));
	assert.equal(doc.querySelector('#abmm-backup-panel > summary').contains(hint('history')), false, 'A hint never goes inside a summary, where a click would toggle it.');
	assert.equal(hint('advanced'), null, 'A hint with no target is left out.');
	{
		const shipped = page({ html: '<!doctype html><body><div id="abmm-builder"><details id="abmm-advanced-settings"><summary>Advanced settings</summary></details></div></body>' });
		shipped.ABMMAdminGuide.create({ window: shipped, data: DATA });
		const real = shipped.document.querySelector('[data-abmm-guide-hint="advanced"]');
		assert.ok(real, 'The shipped hint finds its target.');
		assert.equal(real.nextElementSibling, shipped.document.getElementById('abmm-advanced-settings'), 'It sits just before the section, not inside its summary.');
		real.click();
		assert.equal(shipped.document.getElementById('abmm-guide-hint-panel').querySelector('a').href, 'https://example.test/docs/style-your-menu.md#advanced-settings');
	}
	guide.addHints();
	assert.equal(doc.querySelectorAll('[data-abmm-guide-hint="place"]').length, 1, 'Hints are not added twice.');

	const trigger = hint('place');
	assert.equal(trigger.tagName, 'BUTTON');
	assert.equal(trigger.getAttribute('aria-label'), "What's this? Placing a menu");
	assert.equal(trigger.getAttribute('aria-expanded'), 'false');
	assert.equal(trigger.textContent, '?');

	trigger.focus();
	trigger.click();
	assert.equal(panel().getAttribute('role'), 'dialog');
	assert.equal(trigger.getAttribute('aria-expanded'), 'true');
	assert.equal(trigger.getAttribute('aria-controls'), 'abmm-guide-hint-panel');
	assert.equal(doc.getElementById(panel().getAttribute('aria-labelledby')).textContent, 'Placing a menu');
	assert.equal(doc.activeElement, panel().querySelector('.abmm-guide__title'), 'Focus moves into the hint.');
	const link = panel().querySelector('a');
	assert.equal(link.href, 'https://example.test/docs/user-guide.md#put-it-in-a-header');
	assert.equal(link.target, '_blank');
	assert.equal(link.rel, 'noopener noreferrer');
	assert.ok(link.textContent.includes('(new tab)'), 'The link says it opens a new tab.');

	key(window, 'Escape');
	assert.equal(panel(), null, 'Escape closes the hint.');
	assert.equal(doc.activeElement, trigger, 'Escape returns focus to the hint.');
	assert.equal(trigger.getAttribute('aria-expanded'), 'false');
	assert.equal(trigger.hasAttribute('aria-controls'), false);

	// Opening another replaces the first, a second click closes, and so does a click away.
	trigger.click();
	hint('dropdown').click();
	assert.equal(doc.querySelectorAll('.abmm-guide--hint').length, 1, 'One hint at a time.');
	assert.equal(trigger.getAttribute('aria-expanded'), 'false');
	hint('dropdown').click();
	assert.equal(panel(), null, 'A second click closes it.');
	hint('dropdown').click();
	doc.getElementById('outside').dispatchEvent(new window.Event('pointerdown', { bubbles: true }));
	assert.equal(panel(), null, 'A press outside closes it.');

	// The close button.
	trigger.click();
	panel().querySelector('.abmm-guide__close').click();
	assert.equal(panel(), null);
	assert.equal(doc.activeElement, trigger);

	// The tour replaces an open hint.
	trigger.click();
	assert.equal(guide.start(false), true);
	assert.equal(panel(), null, 'Starting the tour closes a hint.');
	key(window, 'Escape');
}

// No wording, no guide: nothing opens and nothing is added.
{
	const window = page();
	const guide = window.ABMMAdminGuide.create({ window, data: {} });
	assert.equal(guide.start(false), false);
	assert.equal(window.document.querySelector('[data-abmm-guide-hint]'), null);
	assert.equal(window.document.getElementById('start').hidden, true, 'No wording keeps the control hidden.');
}

// No wording in the script itself: translators get everything through PHP.
assert.doesNotMatch(source, /Add your links|Save your work|What's this\?\s*%/, 'The script has no built-in wording.');

// Placement.
{
	const guideScript = page().ABMMAdminGuide;
	// A plain copy, because the result is an object from the page's realm.
	const placePopup = (...args) => JSON.parse(JSON.stringify(guideScript.placePopup(...args)));
	const size = { width: 320, height: 200 };
	const viewport = { width: 1280, height: 800 };
	assert.deepEqual(placePopup({ top: 100, bottom: 130, left: 40 }, size, viewport), { docked: false, top: 140, left: 40 }, 'Below the target when it fits.');
	assert.deepEqual(placePopup({ top: 700, bottom: 730, left: 40 }, size, viewport), { docked: false, top: 490, left: 40 }, 'Above the target near the bottom.');
	assert.deepEqual(placePopup({ top: 100, bottom: 130, left: 1200 }, size, viewport), { docked: false, top: 140, left: 948 }, 'Kept inside the right edge.');
	assert.deepEqual(placePopup({ top: 100, bottom: 130, left: -50 }, size, viewport), { docked: false, top: 140, left: 12 }, 'Kept inside the left edge.');
	assert.deepEqual(placePopup({ top: 100, bottom: 130, left: 40 }, { width: 320, height: 700 }, { width: 1280, height: 720 }), { docked: false, top: 12, left: 40 }, 'Fits as best it can when neither side has room.');
	assert.deepEqual(placePopup({ top: 100, bottom: 130, left: 40 }, size, { width: 390, height: 844 }), { docked: true }, 'Docks on a narrow screen.');
}

// The script starts itself when PHP provides the wording.
{
	const dom = new JSDOM(PAGE.replace('</div>\n<div class="abmm-exit-dialog"', '<details id="abmm-advanced-settings"><summary>Advanced</summary></details></div>\n<div class="abmm-exit-dialog"'), { runScripts: 'outside-only', url: 'https://example.test/wp-admin/', pretendToBeVisual: true });
	const { window } = dom;
	window.HTMLElement.prototype.getBoundingClientRect = () => ({ width: 100, height: 30, top: 20, bottom: 50, left: 20, right: 120 });
	const node = window.document.createElement('script');
	node.type = 'application/json';
	node.id = 'abmm-menu-data';
	node.textContent = '{"items":[]}';
	window.document.body.appendChild(node);
	window.abmmGuide = DATA;
	window.eval(source);
	setTimeout(() => {
		assert.ok(window.document.querySelector('.abmm-guide--tour'), 'It starts once the page has loaded.');
		assert.ok(window.document.querySelector('[data-abmm-guide-hint="advanced"]'), 'Hints are added.');
		console.log('Admin guide: auto-start rules, dismissal, skipped steps, focus, storage failure and hints passed.');
		window.close();
	}, 30);
}
