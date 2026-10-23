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
console.log('Mobile drawer stays on screen wherever the menu bar is placed.');
