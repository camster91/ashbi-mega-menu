'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');

// Hover opens a desktop panel. A click that follows must keep it open.
const dom = new JSDOM(
	`<!doctype html>
	<html>
		<body>
			<header class="abmm-header" data-abmm-instance="abmm-hover-1" data-abmm-desktop-open="hover">
				<nav class="abmm-nav">
					<ul class="abmm-nav__list">
						<li class="abmm-nav__item abmm-nav__item--mega">
							<button data-abmm-mega-trigger aria-expanded="false" aria-controls="abmm-hover-1-panel">Products</button>
							<div class="abmm-mega" id="abmm-hover-1-panel" hidden data-abmm-mega-panel>
								<a href="/one">One</a>
							</div>
						</li>
					</ul>
				</nav>
			</header>
		</body>
	</html>`,
	{ runScripts: 'outside-only', pretendToBeVisual: true, url: 'https://example.test/' }
);

const { window } = dom;
window.matchMedia = (query) => ({
	matches: !query.includes('max-width'),
	media: query,
	addEventListener() {},
	removeEventListener() {},
});
window.requestAnimationFrame = (callback) => callback();
window.ResizeObserver = class {
	observe() {}
};

window.eval(fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', 'frontend.js'), 'utf8'));
window.document.dispatchEvent(new window.Event('DOMContentLoaded'));

const item = window.document.querySelector('.abmm-nav__item--mega');
const trigger = item.querySelector('[data-abmm-mega-trigger]');

item.dispatchEvent(new window.MouseEvent('mouseenter'));
assert.equal(trigger.getAttribute('aria-expanded'), 'true', 'hover opens the panel');

trigger.click();
assert.equal(trigger.getAttribute('aria-expanded'), 'true', 'click after hover keeps the panel open');

trigger.click();
assert.equal(trigger.getAttribute('aria-expanded'), 'false', 'a second click closes the panel');

trigger.click();
assert.equal(trigger.getAttribute('aria-expanded'), 'true', 'click without hover opens the panel');

console.log('Desktop hover then click keeps the panel open.');
dom.window.close();
