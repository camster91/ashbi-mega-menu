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

function load({ copies = 1, config, mobile = false } = {}) {
	const dom = new JSDOM(`<!doctype html><html><body>${fixture}</body></html>`, { runScripts: 'outside-only', pretendToBeVisual: true, url: 'https://example.test/' });
	const { window } = dom;
	window.matchMedia = (query) => ({ matches: mobile ? query.includes('max-width') : !query.includes('max-width'), media: query, addEventListener() {}, removeEventListener() {}, addListener() {}, removeListener() {} });
	window.requestAnimationFrame = (callback) => callback();
	window.ResizeObserver = class { observe() {} disconnect() {} };
	if (config) window.abmmClassic = config;
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

// The edge bleed is configuration, not a hard-coded menu ID.
assert.ok(!/menu_demo/.test(script), 'No site-specific menu ID in the script.');
assert.ok(/window\.abmmClassic/.test(script), 'The bleed comes from window.abmmClassic.');
{
	const window = load({ config: { edgeBleed: { main_nav: 8 } } });
	assert.equal(window.document.querySelector('header.emm-header').getAttribute('data-emm-initialized'), 'true', 'A bleed config does not break initialisation.');
}

// No client names in the bundled classic assets.
for (const file of ['emm-frontend.js', 'emm-frontend.css', 'emm-sprite.svg']) {
	const text = fs.readFileSync(path.join(__dirname, '..', 'assets', 'classic', file), 'utf8');
	assert.ok(!/suti/i.test(text), `${file} has no client-specific content.`);
}

console.log('Classic script: single binding, open/close, measurable phone header, bleed config and clean bundled assets passed.');
