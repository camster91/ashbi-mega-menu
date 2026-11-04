'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');

// The mobile drawer opens below the menu bar. When the bar sits low on the
// screen (a footer or sidebar widget), the drawer must open from the top.
function openDrawerWithBarAt(barBottom) {
	const dom = new JSDOM(
		`<!doctype html><html><body>
			<header class="abmm-header abmm-mobile-enhanced" data-abmm-instance="abmm-offset-1">
				<nav class="abmm-nav">
					<div class="abmm-nav__bar">
						<button class="abmm-nav__toggle" aria-expanded="false" aria-controls="abmm-offset-1-drawer">Open</button>
					</div>
					<div class="abmm-nav__drawer" id="abmm-offset-1-drawer">
						<button data-abmm-drawer-close>Close</button>
						<ul class="abmm-nav__list"><li class="abmm-nav__item"><a href="/a">A</a></li></ul>
					</div>
					<div data-abmm-backdrop hidden></div>
				</nav>
			</header>
		</body></html>`,
		{ runScripts: 'outside-only', pretendToBeVisual: true, url: 'https://example.test/' }
	);
	const { window } = dom;
	Object.defineProperty(window, 'innerHeight', { value: 800, configurable: true });
	window.matchMedia = (query) => ({ matches: query.includes('max-width'), media: query, addEventListener() {}, removeEventListener() {} });
	window.requestAnimationFrame = (callback) => callback();
	window.ResizeObserver = class { observe() {} };
	window.document.querySelector('.abmm-nav__bar').getBoundingClientRect = () => ({ bottom: barBottom });
	window.eval(fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', 'frontend.js'), 'utf8'));
	window.document.dispatchEvent(new window.Event('DOMContentLoaded'));
	const header = window.document.querySelector('.abmm-header');
	header.querySelector('.abmm-nav__toggle').click();
	const offset = header.style.getPropertyValue('--abmm-mobile-offset');
	window.close();
	return offset;
}

assert.equal(openDrawerWithBarAt(64), '64px', 'a bar near the top keeps the drawer below it');
assert.equal(openDrawerWithBarAt(760), '0px', 'a bar near the bottom opens the drawer from the top');
// While a drawer is open, everything outside it is inert, at every level up to
// the body; the admin toolbar stays usable. Closing restores all of it.
{
	const dom = new JSDOM(`<!doctype html><html><body>
		<div id="wpadminbar"></div>
		<div id="wrap"><p id="beside">Beside</p>
			<div class="abmm-header" data-abmm-instance="abmm-inert-1" data-abmm-back-label="Retour">
				<nav class="abmm-nav"><div class="abmm-nav__bar">
					<a class="abmm-nav__brand" href="/"><img class="abmm-nav__brand-logo" src="x.png" alt="Co"><span class="abmm-nav__brand-fallback" aria-hidden="true">Co</span></a>
					<button class="abmm-nav__toggle" aria-expanded="false" aria-controls="d">Menu</button></div>
					<div class="abmm-nav__drawer" id="d"><ul class="abmm-nav__list">
						<li class="abmm-nav__item abmm-nav__item--mega">
							<button data-abmm-mega-trigger aria-expanded="false" aria-controls="p">Products</button>
							<div class="abmm-mega" id="p" hidden data-abmm-mega-panel>
								<section class="abmm-mega__section"><div class="abmm-mega__panel">
									<button data-abmm-section-toggle aria-expanded="true">One</button>
									<a id="first" href="/one">One</a></div></section>
								<section class="abmm-mega__section" id="second"><div class="abmm-mega__panel">
									<button id="second-toggle" data-abmm-section-toggle aria-expanded="true">Two</button>
									<a href="/two">Two</a></div></section>
							</div>
						</li>
					</ul></div>
				</nav>
			</div>
		</div>
		<main id="page"></main>
		<script id="s"></script></body></html>`, { runScripts: 'outside-only', pretendToBeVisual: true, url: 'https://example.test/' });
	const { window } = dom;
	window.matchMedia = (query) => ({ matches: query.includes('max-width'), media: query, addEventListener() {}, removeEventListener() {} });
	window.requestAnimationFrame = (callback) => callback();
	window.ResizeObserver = class { observe() {} };
	window.abmmRuntime = { searchStrings: { closeMenu: 'Fermer', backTo: 'Retour vers %s', backToMenu: 'menu' } };
	window.HTMLElement.prototype.getClientRects = function () { return this.hidden ? [] : [{}]; };
	const logo = window.document.querySelector('.abmm-nav__brand-logo');
	Object.defineProperty(logo, 'complete', { value: true });
	Object.defineProperty(logo, 'naturalWidth', { value: 0, configurable: true });
	window.eval(fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', 'frontend.js'), 'utf8'));
	window.document.dispatchEvent(new window.Event('DOMContentLoaded'));
	const byId = (id) => window.document.getElementById(id);
	const header = window.document.querySelector('.abmm-header');
	const toggle = header.querySelector('.abmm-nav__toggle');
	const fallback = header.querySelector('.abmm-nav__brand-fallback');

	// A logo that failed exposes its fallback text as the link's name.
	assert.equal(fallback.hasAttribute('aria-hidden'), false, 'A failed logo exposes its fallback text.');
	Object.defineProperty(logo, 'naturalWidth', { value: 120 });
	logo.dispatchEvent(new window.Event('load'));
	assert.equal(fallback.getAttribute('aria-hidden'), 'true', 'A loaded logo hides it again.');

	toggle.click();
	for (const id of ['beside', 'page']) assert.equal(byId(id).hasAttribute('inert'), true, `${id} is inert while the drawer is open.`);
	assert.equal(byId('wrap').hasAttribute('inert'), false, 'The header\'s own ancestors stay reachable.');
	assert.equal(byId('wpadminbar').hasAttribute('inert'), false, 'The admin toolbar stays reachable.');
	assert.equal(byId('s').hasAttribute('inert'), false, 'Scripts are left alone.');

	// A drilled-in screen is announced and labelled, with translated controls.
	const trigger = header.querySelector('[data-abmm-mega-trigger]');
	const mega = byId('p');
	const first = header.querySelector('.abmm-mega__section');
	const drawer = byId('d');
	first.getBoundingClientRect = () => ({ height: 1200, bottom: 1300 });
	Object.defineProperty(drawer, 'clientHeight', { value: 700, configurable: true });
	trigger.click();
	assert.equal(header.querySelector('[data-abmm-live]').textContent, 'Products', 'The screen is announced.');
	assert.equal(mega.getAttribute('role'), 'group');
	assert.equal(mega.getAttribute('aria-label'), 'Products');
	assert.equal(header.querySelector('.abmm-mobile-close').getAttribute('aria-label'), 'Fermer', 'Close uses the translated label.');
	assert.equal(header.querySelector('[data-abmm-back] span').textContent, 'Retour', 'Back uses the translated label.');
	assert.equal(first.classList.contains('is-collapsed'), true, 'A first category taller than the screen starts collapsed.');

	// A heading whose links open below the fold scrolls into view; closing one does not.
	const second = byId('second');
	const scrolled = [];
	second.scrollIntoView = (options) => scrolled.push(options);
	second.getBoundingClientRect = () => ({ bottom: 900, height: 200 });
	drawer.getBoundingClientRect = () => ({ bottom: 800 });
	assert.equal(second.classList.contains('is-collapsed'), true);
	byId('second-toggle').click();
	assert.equal(scrolled.length, 1, 'Links that opened below the fold scroll into view.');
	assert.equal(scrolled[0].block, 'start', 'Heading first.');
	byId('second-toggle').click();
	assert.equal(scrolled.length, 1, 'Collapsing does not scroll.');

	// Leaving the screen clears its label.
	header.querySelector('[data-abmm-back]').click();
	assert.equal(mega.hasAttribute('role'), false);
	assert.equal(mega.hasAttribute('aria-label'), false);

	toggle.click();
	assert.equal(window.document.querySelectorAll('[inert]').length, 0, 'Closing the drawer restores everything.');
	window.close();
}

// Opening the drawer does not focus the search box, which would raise a phone keyboard.
{
	const dom = new JSDOM(`<!doctype html><html><body>
		<div class="abmm-header abmm-mobile-enhanced" data-abmm-instance="abmm-search-1">
			<nav class="abmm-nav"><div class="abmm-nav__bar"><button class="abmm-nav__toggle" aria-expanded="false" aria-controls="d">Menu</button></div>
				<div class="abmm-nav__drawer" id="d"><ul class="abmm-nav__list"><li class="abmm-nav__item"><a id="home" class="abmm-nav__link" href="/home">Home</a></li></ul></div>
			</nav>
		</div></body></html>`, { runScripts: 'outside-only', pretendToBeVisual: true, url: 'https://example.test/' });
	const { window } = dom;
	window.matchMedia = (query) => ({ matches: query.includes('max-width'), media: query, addEventListener() {}, removeEventListener() {} });
	window.requestAnimationFrame = (callback) => callback();
	window.ResizeObserver = class { observe() {} };
	window.HTMLElement.prototype.getClientRects = function () { return this.hidden ? [] : [{}]; };
	window.eval(fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', 'frontend.js'), 'utf8'));
	window.document.dispatchEvent(new window.Event('DOMContentLoaded'));
	assert.ok(window.document.querySelector('[data-abmm-search] input'), 'The search box is present.');
	window.document.querySelector('.abmm-nav__toggle').click();
	assert.equal(window.document.activeElement, window.document.getElementById('home'), 'Focus goes to the first link, not the search box.');
	window.close();
}

console.log('Mobile drawer stays on screen wherever the menu bar is placed.');
