'use strict';

// Classic (Easy Mega Menu compatible) script: binds each header once even when
// Easy Mega Menu's own copy also runs, opens and closes the classic markup, and
// reads the per-menu panel edge bleed from window.abmmClassic.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');

const fixture = fs.readFileSync(path.join(__dirname, 'fixtures', 'emm', 'expected-home-main_nav.html'), 'utf8');
const script = fs.readFileSync(path.join(__dirname, '..', 'assets', 'classic', 'emm-frontend.js'), 'utf8');

function load({ copies = 1, config, mobile = false, timers = false, wrap = (html) => html } = {}) {
	const dom = new JSDOM(`<!doctype html><html><body>${wrap(fixture)}</body></html>`, { runScripts: 'outside-only', pretendToBeVisual: true, url: 'https://example.test/' });
	const { window } = dom;
	window.matchMedia = (query) => ({ matches: mobile ? query.includes('max-width') : !query.includes('max-width'), media: query, addEventListener() {}, removeEventListener() {}, addListener() {}, removeListener() {} });
	window.requestAnimationFrame = (callback) => callback();
	window.ResizeObserver = class { observe() {} disconnect() {} };
	if (config) window.abmmClassic = config;
	if (timers) {
		// Timers run when a test says so, in order, so delays need no waiting.
		window.pendingTimers = [];
		window.setTimeout = (callback) => window.pendingTimers.push(callback);
		window.clearTimeout = (id) => { if (id) window.pendingTimers[id - 1] = null; };
		window.runTimers = () => {
			const due = window.pendingTimers.slice();
			due.forEach((callback, index) => { if (callback) { window.pendingTimers[index] = null; callback(); } });
		};
	}
	for (let i = 0; i < copies; i++) window.eval(script);
	window.document.dispatchEvent(new window.Event('DOMContentLoaded'));
	return window;
}

// One copy: the header is initialised and a click toggles the first panel.
{
	const window = load();
	const header = window.document.querySelector('header.emm-header');
	assert.equal(header.getAttribute('data-emm-initialized'), 'true');
	const trigger = header.querySelector('[data-emm-mega-trigger]');
	trigger.click();
	assert.equal(trigger.getAttribute('aria-expanded'), 'true', 'A click opens the panel.');
	trigger.click();
	assert.equal(trigger.getAttribute('aria-expanded'), 'false', 'A second click closes it.');
}

// Two copies (Easy Mega Menu's and Ashbi's, or an optimizer concatenating
// twice): the per-header guard keeps a single set of listeners, so one click
// still opens the panel instead of opening and immediately closing it.
{
	const window = load({ copies: 2 });
	const trigger = window.document.querySelector('header.emm-header [data-emm-mega-trigger]');
	trigger.click();
	assert.equal(trigger.getAttribute('aria-expanded'), 'true', 'Loading the script twice still opens on one click.');
}

// A header can measure zero wide on a phone, its button overflowing a shrunken
// layout column. Some site scripts reset any open header with no width as
// hidden, so it keeps a measurable width while its drawer is open, and only then.
{
	const window = load({ mobile: true });
	const header = window.document.querySelector('header.emm-header');
	const toggle = header.querySelector('.emm-nav__toggle');
	toggle.click();
	assert.ok(header.classList.contains('is-mobile-open'), 'The toggle opens the drawer.');
	assert.equal(header.style.minWidth, '1px', 'The header stays measurable while its drawer is open.');
	toggle.click();
	assert.ok(!header.classList.contains('is-mobile-open'), 'The toggle closes the drawer.');
	assert.equal(header.style.minWidth, '', 'Closing the drawer restores the header.');
}

// A header with width when the drawer opens can still collapse later, such as
// when a phone rotates, so the minimum does not depend on its width at opening.
{
	const window = load({ mobile: true });
	const header = window.document.querySelector('header.emm-header');
	header.getBoundingClientRect = () => ({ width: 32, height: 52, top: 0, left: 0, right: 32, bottom: 52 });
	header.querySelector('.emm-nav__toggle').click();
	assert.equal(header.style.minWidth, '1px', 'A header with width is covered if it collapses while open.');
}

// A min-width the plugin did not set is left alone, opening and closing.
{
	const window = load({ mobile: true });
	const header = window.document.querySelector('header.emm-header');
	const toggle = header.querySelector('.emm-nav__toggle');
	header.style.minWidth = '200px';
	toggle.click();
	assert.equal(header.style.minWidth, '200px', 'Opening keeps a min-width set by the site.');
	toggle.click();
	assert.equal(header.style.minWidth, '200px', 'Closing keeps a min-width set by the site.');
}

// Hover: a panel opens after a short delay, and leaving first cancels it.
{
	const window = load({ timers: true });
	const header = window.document.querySelector('header.emm-header');
	const trigger = header.querySelector('[data-emm-mega-trigger]');
	const item = trigger.closest('.emm-nav__item--mega');
	const hover = (element, type) => element.dispatchEvent(new window.MouseEvent(type, { bubbles: false }));
	hover(item, 'mouseenter');
	assert.equal(trigger.getAttribute('aria-expanded'), 'false', 'Hover does not open a panel at once.');
	hover(item, 'mouseleave');
	window.runTimers();
	assert.equal(trigger.getAttribute('aria-expanded'), 'false', 'Leaving within the delay cancels the opening.');
	hover(item, 'mouseenter');
	window.runTimers();
	assert.equal(trigger.getAttribute('aria-expanded'), 'true', 'Staying opens the panel.');
	trigger.click();
	assert.equal(trigger.getAttribute('aria-expanded'), 'true', 'A click after hover keeps the panel open.');
	hover(header, 'mouseleave');
	window.runTimers();
	assert.equal(trigger.getAttribute('aria-expanded'), 'true', 'That click pins it past mouse-out.');
	trigger.click();
	assert.equal(trigger.getAttribute('aria-expanded'), 'false', 'The next click closes it.');
}

// Hover then a quick click: one open, and no late timer closes or reopens it.
{
	const window = load({ timers: true });
	const trigger = window.document.querySelector('header.emm-header [data-emm-mega-trigger]');
	trigger.closest('.emm-nav__item--mega').dispatchEvent(new window.MouseEvent('mouseenter', { bubbles: false }));
	trigger.click();
	window.runTimers();
	assert.equal(trigger.getAttribute('aria-expanded'), 'true', 'A tap on a touch laptop opens the panel and it stays.');
}

// Categories switch on a rest, not while the pointer crosses them; a click is immediate.
{
	const window = load({ timers: true });
	const trigger = window.document.querySelector('header.emm-header [data-emm-mega-trigger]');
	trigger.click();
	const panel = window.document.getElementById(trigger.getAttribute('aria-controls'));
	const people = panel.querySelector('.emm-mega__cat[data-emm-cat="people"]');
	people.dispatchEvent(new window.MouseEvent('mouseenter', { bubbles: false }));
	assert.equal(people.getAttribute('aria-expanded'), 'false', 'Crossing a category does not switch to it.');
	people.dispatchEvent(new window.MouseEvent('mouseleave', { bubbles: false }));
	window.runTimers();
	assert.equal(people.getAttribute('aria-expanded'), 'false', 'Moving on cancels the switch.');
	people.dispatchEvent(new window.MouseEvent('mouseenter', { bubbles: false }));
	window.runTimers();
	assert.equal(people.getAttribute('aria-expanded'), 'true', 'Resting switches.');
	const finance = panel.querySelector('.emm-mega__cat[data-emm-cat="finance"]');
	finance.click();
	assert.equal(finance.getAttribute('aria-expanded'), 'true', 'A click switches at once.');
}

// Tabbing out closes a desktop panel; a click on blank space (no target) does not.
{
	const window = load();
	const header = window.document.querySelector('header.emm-header');
	const trigger = header.querySelector('[data-emm-mega-trigger]');
	trigger.click();
	header.dispatchEvent(new window.FocusEvent('focusout', { bubbles: true, relatedTarget: null }));
	assert.equal(trigger.getAttribute('aria-expanded'), 'true', 'Losing focus to nothing keeps the panel.');
	header.dispatchEvent(new window.FocusEvent('focusout', { bubbles: true, relatedTarget: window.document.body }));
	assert.equal(trigger.getAttribute('aria-expanded'), 'false', 'Focus moving out of the menu closes the panel.');
}

// Escape after a hover-opened panel leaves focus where it was.
{
	const window = load();
	const header = window.document.querySelector('header.emm-header');
	const trigger = header.querySelector('[data-emm-mega-trigger]');
	const outside = window.document.createElement('button');
	window.document.body.appendChild(outside);
	trigger.click();
	outside.focus();
	window.document.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
	assert.equal(trigger.getAttribute('aria-expanded'), 'false', 'Escape closes the panel.');
	assert.equal(window.document.activeElement, outside, 'Focus outside the menu stays there.');
}

// Tapping a link closes the drawer and unlocks the page; a same-page # or new-tab link follows the rules.
{
	const window = load({ mobile: true });
	const header = window.document.querySelector('header.emm-header');
	const toggle = header.querySelector('.emm-nav__toggle');
	const link = window.document.createElement('a');
	link.href = '#section';
	link.addEventListener('click', (event) => event.preventDefault);
	header.querySelector('.emm-nav__inner').appendChild(link);
	window.document.addEventListener('click', (event) => { if (event.target === link) event.preventDefault(); });
	toggle.click();
	assert.ok(window.document.documentElement.classList.contains('emm-drawer-open'), 'Opening locks the page.');
	link.click();
	assert.ok(!header.classList.contains('is-mobile-open'), 'A link closes the drawer.');
	assert.ok(!window.document.documentElement.classList.contains('emm-drawer-open'), 'A link unlocks the page.');

	toggle.click();
	link.setAttribute('target', '_blank');
	link.click();
	assert.ok(header.classList.contains('is-mobile-open'), 'A new-tab link leaves the drawer open.');
	link.removeAttribute('target');
	link.setAttribute('href', '#');
	link.click();
	assert.ok(header.classList.contains('is-mobile-open'), 'A bare # leaves the drawer open.');

	window.dispatchEvent(new window.Event('pagehide'));
	assert.ok(!header.classList.contains('is-mobile-open'), 'Leaving the page never keeps an open drawer for Back.');
}

// While the drawer is open, only it is reachable, and it reads as a dialog.
{
	const window = load({ mobile: true, wrap: (html) => '<div id="outer"><main id="page"></main><script id="s"></script><div id="host">' + html + '</div></div><aside id="side"></aside>' });
	const header = window.document.querySelector('header.emm-header');
	const toggle = header.querySelector('.emm-nav__toggle');
	const drawer = header.querySelector('.emm-nav__drawer');
	toggle.click();
	assert.ok(window.document.getElementById('page').hasAttribute('inert'), 'A sibling of the menu branch is inert.');
	assert.ok(window.document.getElementById('side').hasAttribute('inert'), 'Outer content is inert.');
	assert.ok(!window.document.getElementById('host').hasAttribute('inert'), 'The menu branch stays live.');
	assert.ok(!window.document.getElementById('s').hasAttribute('inert'), 'Scripts are skipped.');
	assert.equal(drawer.getAttribute('role'), 'dialog', 'The open drawer is a dialog.');
	toggle.click();
	assert.ok(!window.document.getElementById('page').hasAttribute('inert'), 'Closing restores the page.');
	assert.ok(!drawer.hasAttribute('role'), 'A closed drawer has no dialog role.');
}

// Words come from the page's settings, and the server's translated labels survive use.
{
	const window = load({ mobile: true, config: { i18n: { back: 'Retour', backTo: 'Retour vers %s', backToMenu: 'Retour au menu' } } });
	const header = window.document.querySelector('header.emm-header');
	const toggle = header.querySelector('.emm-nav__toggle');
	const printed = toggle.getAttribute('aria-label');
	toggle.click();
	const trigger = header.querySelector('[data-emm-mega-trigger]');
	trigger.click();
	const panel = window.document.getElementById(trigger.getAttribute('aria-controls'));
	const back = panel.querySelector('[data-emm-back]');
	assert.equal(back.getAttribute('aria-label'), 'Retour au menu', 'First level says it returns to the menu.');
	assert.equal(back.textContent.trim(), 'Retour', 'The visible word is translated.');
	assert.equal(header.querySelector('[role="status"]').textContent, panel.querySelector('[data-emm-mobile-title]').textContent, 'The new level is announced.');
	panel.querySelector('.emm-mega__cat[data-emm-cat="finance"]').click();
	assert.ok(/^Retour vers \S/.test(back.getAttribute('aria-label')), 'A deeper level names where it returns to.');
	toggle.click();
	assert.equal(toggle.getAttribute('aria-label'), printed, 'Closing restores the printed label, not English text.');
}

// The same fragment twice: every id is unique, including the header's own.
{
	const window = load({ wrap: (html) => html + html });
	const ids = Array.from(window.document.querySelectorAll('[id]'), (element) => element.id);
	assert.equal(new Set(ids).size, ids.length, 'No id repeats when a menu renders twice.');
}

// A failed logo stops naming the link, so its fallback must.
{
	const window = load();
	const logo = window.document.querySelector('.emm-nav__brand-logo');
	const fallback = window.document.querySelector('.emm-nav__brand-fallback');
	if (logo && fallback) {
		Object.defineProperty(logo, 'complete', { value: true, configurable: true });
		Object.defineProperty(logo, 'naturalWidth', { value: 0, configurable: true });
		logo.dispatchEvent(new window.Event('error'));
		assert.ok(!fallback.hasAttribute('aria-hidden'), 'The fallback text is exposed when the logo fails.');
		Object.defineProperty(logo, 'naturalWidth', { value: 40, configurable: true });
		logo.dispatchEvent(new window.Event('load'));
		assert.equal(fallback.getAttribute('aria-hidden'), 'true', 'And hidden again when it loads.');
	}
}

// A visible button label stays in the button's accessible name as it opens and closes.
{
	const window = load({ mobile: true, wrap: (html) => html.replace(/(class="emm-nav__toggle"[^>]*>)/, '$1<span class="emm-nav__toggle-label">Products</span>') });
	const toggle = window.document.querySelector('header.emm-header .emm-nav__toggle');
	toggle.click();
	assert.equal(toggle.getAttribute('aria-label'), 'Products: Close menu', 'The open button keeps its label in its name.');
	toggle.click();
	assert.equal(toggle.getAttribute('aria-label'), 'Products: Open menu', 'The closed button keeps its label in its name.');
}
{
	const window = load({ mobile: true });
	const toggle = window.document.querySelector('header.emm-header .emm-nav__toggle');
	toggle.click();
	toggle.click();
	assert.equal(toggle.getAttribute('aria-label'), 'Open menu', 'With no label the name stays as printed.');
}

// Links that open a new tab say so, once, in the site's language.
{
	const window = load({ copies: 2, config: { i18n: { newTab: '(nouvel onglet)' } }, wrap: (html) => html.replace(/<a (?![^>]*target=)/, '<a target="_blank" ') });
	const link = window.document.querySelector('header.emm-header a[target="_blank"]');
	const notes = link.querySelectorAll('.emm-new-tab-note');
	assert.equal(notes.length, 1, 'A new-tab link gets one note.');
	assert.equal(notes[0].textContent.trim(), '(nouvel onglet)', 'The note uses the translated string.');
	assert.equal(window.document.querySelectorAll('.emm-new-tab-note').length, window.document.querySelectorAll('header.emm-header a[target="_blank"]').length, 'Only new-tab links get a note.');
}

// The edge bleed is configuration, not a hard-coded menu ID.
assert.ok(!/menu_demo/.test(script), 'No site-specific menu ID in the script.');
assert.ok(/window\.abmmClassic/.test(script), 'The bleed comes from window.abmmClassic.');
{
	const window = load({ config: { edgeBleed: { main_nav: 8 } } });
	assert.equal(window.document.querySelector('header.emm-header').getAttribute('data-emm-initialized'), 'true', 'A bleed config does not break initialisation.');
}

// No client names in the bundled classic assets.
for (const file of ['emm-frontend.js', 'emm-frontend.css', 'emm-fixes.css', 'emm-sprite.svg']) {
	const text = fs.readFileSync(path.join(__dirname, '..', 'assets', 'classic', file), 'utf8');
	assert.ok(!/suti/i.test(text), `${file} has no client-specific content.`);
}

// Imported sites keep an old copy of the stylesheet, so the fixes need their own file.
{
	const fixes = fs.readFileSync(path.join(__dirname, '..', 'assets', 'classic', 'emm-fixes.css'), 'utf8');
	const php = fs.readFileSync(path.join(__dirname, '..', 'includes', 'class-abmm-classic.php'), 'utf8');
	assert.ok(/assets\/classic\/emm-fixes\.css/.test(php), 'The fixes stylesheet is registered.');
	assert.ok(/@media \(scripting: none\)/.test(fixes), 'The links stay reachable without JavaScript.');
	assert.ok(/@media \(forced-colors: active\)/.test(fixes), 'High contrast is covered.');
	assert.ok(/max-height: none !important/.test(fixes), 'Long phone lists are not nested scroll boxes.');
	assert.ok(/\.emm-mobile-bar \.emm-mobile-back \{[^}]*display: inline-flex;[^}]*width: auto;/.test(fixes), 'The phone Back button sets its own layout.');
	assert.ok(/'fixesCss'\s*=> add_query_arg\( 'ver', ABMM_VERSION, ABMM_PLUGIN_URL \. 'assets\/classic\/emm-fixes\.css' \)/.test(php), 'The preview frame gets the fixes stylesheet.');
}


// An open drawer can end from outside: a script removing its class, a visibility
// rule hiding it, or a builder replacing it. The page must not stay inert.
(async () => {
	const settle = () => new Promise((resolve) => setImmediate(resolve));
	const open = () => {
		const window = load({ mobile: true, wrap: (html) => '<main id="page"></main><div id="host">' + html + '</div>' });
		window.HTMLElement.prototype.getClientRects = function () { return this.dataset.noBox !== undefined ? [] : [{}]; };
		const header = window.document.querySelector('header.emm-header');
		const toggle = header.querySelector('.emm-nav__toggle');
		toggle.click();
		assert.ok(window.document.getElementById('page').hasAttribute('inert'), 'The page is inert while the drawer is open.');
		return { window, header, toggle, page: window.document.getElementById('page') };
	};
	const released = (ctx, why) => {
		assert.ok(!ctx.page.hasAttribute('inert'), why + ': the page is reachable again.');
		assert.ok(!ctx.window.document.documentElement.classList.contains('emm-drawer-open'), why + ': scrolling is unlocked.');
		assert.equal(ctx.toggle.getAttribute('aria-expanded'), 'false', why + ': the button reads as closed.');
	};

	let ctx = open();
	ctx.header.classList.remove('is-mobile-open');
	await settle();
	released(ctx, 'Class removed from outside');

	ctx = open();
	ctx.header.dataset.noBox = '';
	ctx.window.document.body.classList.add('rotated');
	await settle();
	released(ctx, 'Header hidden by a rule');

	ctx = open();
	ctx.header.dataset.noBox = '';
	ctx.window.dispatchEvent(new ctx.window.Event('resize'));
	await settle();
	released(ctx, 'Header hidden on rotation');

	ctx = open();
	ctx.header.remove();
	await settle();
	released(ctx, 'Header removed from the page');

	ctx = open();
	ctx.window.document.body.classList.add('unrelated');
	ctx.header.classList.add('something-else');
	await settle();
	assert.ok(ctx.header.classList.contains('is-mobile-open'), 'Unrelated changes leave the drawer open.');
	assert.ok(ctx.page.hasAttribute('inert'), 'Unrelated changes keep the page inert.');
	ctx.toggle.click();
	released(ctx, 'Closed by the button');
	ctx.window.document.body.classList.add('after-close');
	await settle();
	assert.ok(!ctx.header.classList.contains('is-mobile-open'), 'The watcher stops once the drawer is closed.');

	// A clone of the open header, such as Elementor's sticky spacer, isn't open.
	ctx = open();
	const clone = ctx.header.cloneNode(true);
	ctx.header.parentNode.appendChild(clone);
	await settle();
	assert.ok(!clone.classList.contains('is-mobile-open'), 'A clone of an open header starts closed.');
	assert.equal(clone.querySelector('.'+ctx.toggle.className.split(' ')[0]).getAttribute('aria-expanded'), 'false', 'A cloned button reads as closed.');
	assert.ok(ctx.header.classList.contains('is-mobile-open'), 'The real drawer stays open.');
	ctx.toggle.click();
	released(ctx, 'Closed with a clone on the page');
})().catch((error) => { console.error(error); process.exit(1); });

console.log('Classic script: single binding, open/close, measurable phone header, hover and rail timing, drawer links, inert page, labels, ids, bleed config and clean bundled assets passed.');
