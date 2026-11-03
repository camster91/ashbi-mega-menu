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

function load({ copies = 1, config } = {}) {
	const dom = new JSDOM(`<!doctype html><html><body>${fixture}</body></html>`, { runScripts: 'outside-only', pretendToBeVisual: true, url: 'https://example.test/' });
	const { window } = dom;
	window.matchMedia = (query) => ({ matches: !query.includes('max-width'), media: query, addEventListener() {}, removeEventListener() {}, addListener() {}, removeListener() {} });
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

console.log('Classic script: single binding, open/close, bleed config and clean bundled assets passed.');
