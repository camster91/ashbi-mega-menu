'use strict';

// Contract for the public front-end events add-ons may listen to:
// abmm:open, abmm:close and abmm:drawer on the menu root, bubbling.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');

function load(desktop) {
	const dom = new JSDOM(
		`<!doctype html><html><body>
			<div class="abmm-header" data-abmm-id="main-menu" data-abmm-instance="abmm-ev-1" data-abmm-desktop-open="click">
				<nav class="abmm-nav"><div class="abmm-nav__bar"><button class="abmm-nav__toggle" aria-expanded="false" aria-controls="abmm-ev-1-drawer">Menu</button></div>
					<div class="abmm-nav__drawer" id="abmm-ev-1-drawer"><ul class="abmm-nav__list">
						<li class="abmm-nav__item abmm-nav__item--mega" data-abmm-item-id="products">
							<button data-abmm-mega-trigger aria-expanded="false" aria-controls="abmm-ev-1-panel">  Products
							</button>
							<div class="abmm-mega" id="abmm-ev-1-panel" hidden data-abmm-mega-panel><a href="/one">One</a></div>
						</li>
					</ul></div>
					<div data-abmm-backdrop hidden></div>
				</nav>
			</div></body></html>`,
		{ runScripts: 'outside-only', pretendToBeVisual: true, url: 'https://example.test/' }
	);
	const { window } = dom;
	window.matchMedia = (query) => ({ matches: desktop ? !query.includes('max-width') : query.includes('max-width'), media: query, addEventListener() {}, removeEventListener() {} });
	window.requestAnimationFrame = (callback) => callback();
	window.ResizeObserver = class { observe() {} };
	window.eval(fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', 'frontend.js'), 'utf8'));
	window.document.dispatchEvent(new window.Event('DOMContentLoaded'));
	const events = [];
	for (const name of ['abmm:open', 'abmm:close', 'abmm:drawer']) {
		// Listening on document proves the events bubble.
		window.document.addEventListener(name, (event) => events.push({ name, target: event.target.className.split(' ')[0], ...event.detail }));
	}
	return { window, events, trigger: window.document.querySelector('[data-abmm-mega-trigger]') };
}

// Desktop: open and close.
{
	const { window, events, trigger } = load(true);
	trigger.click();
	assert.deepEqual(events, [{ name: 'abmm:open', target: 'abmm-header', menuId: 'main-menu', itemId: 'products', label: 'Products', layout: 'desktop' }], 'opening a panel fires abmm:open with the item');
	trigger.click();
	assert.deepEqual(events[1], { name: 'abmm:close', target: 'abmm-header', menuId: 'main-menu', itemId: 'products', label: 'Products' }, 'closing fires abmm:close');
	assert.equal(events.length, 2, 'no duplicate events');
	window.close();
}

// Phone: drawer and drill-in.
{
	const { window, events, trigger } = load(false);
	window.document.querySelector('.abmm-nav__toggle').click();
	assert.deepEqual(events[0], { name: 'abmm:drawer', target: 'abmm-header', menuId: 'main-menu', open: true }, 'opening the drawer fires abmm:drawer');
	trigger.click();
	assert.equal(events[1].name, 'abmm:open');
	assert.equal(events[1].layout, 'mobile', 'drilling in on a phone reports the mobile layout');
	window.document.querySelector('.abmm-nav__toggle').click();
	const closing = events.slice(2).map((e) => e.name + (e.name === 'abmm:drawer' ? ':' + e.open : '')).sort();
	assert.deepEqual(closing, ['abmm:close', 'abmm:drawer:false'], 'closing the drawer fires abmm:drawer (closed) and closes the open item once');
	window.close();
}

console.log('Front-end events: abmm:open, abmm:close and abmm:drawer fire once with their details and bubble.');
