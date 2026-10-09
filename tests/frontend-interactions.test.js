'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');

const dom = new JSDOM(
	`<!doctype html>
	<html>
		<body>
			<div id="wpadminbar"></div>
			<div class="elementskit-menu-container"></div>
			<div id="chatbot-widget-container"></div>
			<header class="abmm-header abmm-mobile-enhanced" data-abmm-instance="abmm-demo-1">
				<nav class="abmm-nav">
					<div class="abmm-nav__bar">
						<button class="abmm-nav__toggle" aria-expanded="false" aria-controls="abmm-demo-1-drawer">Open</button>
					</div>
					<div class="abmm-nav__drawer" id="abmm-demo-1-drawer">
						<div class="abmm-nav__drawer-head">
							<button data-abmm-drawer-close>Close</button>
						</div>
						<ul class="abmm-nav__list">
							<li class="abmm-nav__item abmm-nav__item--mega">
								<button data-abmm-mega-trigger aria-expanded="false" aria-controls="abmm-demo-1-item-products-panel">Products</button>
								<div class="abmm-mega abmm-mega--platforms" id="abmm-demo-1-item-products-panel" hidden data-abmm-mega-panel data-abmm-style="platforms">
									<div class="abmm-mega__accordion">
										<section class="abmm-mega__section" data-abmm-section="finance">
											<button class="abmm-mega__cat" data-abmm-cat="finance" aria-expanded="false" aria-controls="finance-panel">
												<span class="abmm-mega__cat-title">Finance</span>
											</button>
											<div class="abmm-mega__panel" id="finance-panel" hidden>
												<a href="/accounts">Accounts</a>
											</div>
										</section>
										<section class="abmm-mega__section" data-abmm-section="people">
											<button class="abmm-mega__cat" data-abmm-cat="people" aria-expanded="false" aria-controls="people-panel">
												<span class="abmm-mega__cat-title">People</span>
											</button>
											<div class="abmm-mega__panel" id="people-panel" hidden>
												<a href="/people">People link</a>
											</div>
										</section>
									</div>
								</div>
							</li>
							<li class="abmm-nav__item abmm-nav__item--mega abmm-nav__item--resources">
								<button data-abmm-mega-trigger aria-expanded="false" aria-controls="abmm-demo-1-item-resources-panel">Resources</button>
								<div class="abmm-mega abmm-mega--platforms abmm-mega--columns-only" id="abmm-demo-1-item-resources-panel" hidden data-abmm-mega-panel data-abmm-style="platforms">
									<div class="abmm-mega__accordion">
										<section class="abmm-mega__section" data-abmm-section="resources">
											<button class="abmm-mega__cat" data-abmm-cat="resources" aria-expanded="false" aria-controls="resources-panel">
												<span class="abmm-mega__cat-title">Resources</span>
											</button>
											<div class="abmm-mega__panel" id="resources-panel" hidden>
												<a href="/blog">Blog</a>
											</div>
										</section>
									</div>
								</div>
							</li>
						</ul>
					</div>
					<div data-abmm-backdrop hidden></div>
				</nav>
			</header>
		</body>
	</html>`,
	{ runScripts: 'outside-only', pretendToBeVisual: true, url: 'https://example.test/' }
);

const { window } = dom;
let mobileMatches = true;
window.matchMedia = (query) => ({
	matches: query.includes('max-width') ? mobileMatches : !mobileMatches,
	media: query,
	addEventListener() {},
	removeEventListener() {},
});
window.requestAnimationFrame = (callback) => callback();
window.ResizeObserver = class {
	observe() {}
};
window.HTMLElement.prototype.getClientRects = function () {
	return this.hidden ? [] : [{}];
};

const bar = window.document.querySelector('.abmm-nav__bar');
bar.getBoundingClientRect = () => ({ bottom: 84 });
const adminBar = window.document.getElementById('wpadminbar');
adminBar.getBoundingClientRect = () => ({ height: 32 });
const secondHeader = window.document.querySelector('.abmm-header').cloneNode(true);
secondHeader.setAttribute('data-abmm-instance', 'abmm-demo-2');
window.document.body.appendChild(secondHeader);

const script = fs.readFileSync(
	process.env.ABMM_FRONTEND_TEST_FILE || path.join(__dirname, '..', 'assets', 'js', 'frontend.js'),
	'utf8'
);
window.eval(script);
window.document.dispatchEvent(new window.Event('DOMContentLoaded'));
// A duplicate optimized-script execution must not bind a second click handler.
window.eval(script);

const header = window.document.querySelector('.abmm-header');
assert.equal(header.getAttribute('data-abmm-initialized'), 'true');
assert.equal(secondHeader.querySelector('.abmm-nav__drawer').id, 'abmm-demo-1-drawer--abmm-demo-2');
assert.equal(
	secondHeader.querySelector('.abmm-nav__toggle').getAttribute('aria-controls'),
	'abmm-demo-1-drawer--abmm-demo-2'
);
assert.equal(
	new Set(Array.from(window.document.querySelectorAll('[id]'), (element) => element.id)).size,
	window.document.querySelectorAll('[id]').length
);
const toggle = header.querySelector('.abmm-nav__toggle');
const drawerClose = header.querySelector('[data-abmm-drawer-close]');
const trigger = header.querySelector('[data-abmm-mega-trigger]');
const categories = header.querySelectorAll('.abmm-mega__cat');
const firstLink = header.querySelector('.abmm-mega__panel a');
const resourcesItem = header.querySelector('.abmm-nav__item--resources');
const resourcesTrigger = resourcesItem.querySelector('[data-abmm-mega-trigger]');
const resourcesSection = resourcesItem.querySelector('.abmm-mega__section');
const resourcesCategory = resourcesItem.querySelector('.abmm-mega__cat');
const resourcesLink = resourcesItem.querySelector('.abmm-mega__panel a');

toggle.click();
assert.equal(header.classList.contains('is-mobile-open'), true);
assert.equal(toggle.getAttribute('aria-expanded'), 'true');
assert.equal(window.document.activeElement, drawerClose);
assert.equal(header.style.getPropertyValue('--abmm-admin-offset'), '32px');
assert.equal(header.style.getPropertyValue('--abmm-mobile-offset'), '84px');

// Page-builder widgets can emit delayed synthetic clicks outside the header.
// They must not reset an in-progress mobile menu, while an actual outside press
// must continue to dismiss it.
window.document.body.click();
assert.equal(header.classList.contains('is-mobile-open'), true);
window.document.body.dispatchEvent(new window.MouseEvent('mousedown', { bubbles: true }));
assert.equal(header.classList.contains('is-mobile-open'), false);
toggle.click();
assert.equal(header.classList.contains('is-mobile-open'), true);

secondHeader.querySelector('.abmm-nav__toggle').click();
assert.equal(header.classList.contains('is-mobile-open'), false);
assert.equal(secondHeader.classList.contains('is-mobile-open'), true);
secondHeader.querySelector('[data-abmm-drawer-close]').click();
assert.equal(secondHeader.classList.contains('is-mobile-open'), false);
assert.equal(window.document.documentElement.classList.contains('abmm-drawer-open'), false);
toggle.click();
assert.equal(header.classList.contains('is-mobile-open'), true);
assert.equal(window.document.documentElement.classList.contains('abmm-drawer-open'), true);

trigger.click();
assert.equal(trigger.getAttribute('aria-expanded'), 'true');
assert.equal(window.document.activeElement, categories[0]);

categories[0].click();
assert.equal(header.getAttribute('data-drill-level'), '2');
assert.equal(window.document.activeElement, firstLink);

window.document.body.click();
assert.equal(header.classList.contains('is-mobile-open'), true);
assert.equal(header.getAttribute('data-drill-level'), '2');
assert.equal(trigger.getAttribute('aria-expanded'), 'true');

header.querySelector('[data-abmm-back]').click();
assert.equal(header.getAttribute('data-drill-level'), '1');
assert.equal(window.document.activeElement, categories[0]);

categories[0].dispatchEvent(new window.KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }));
assert.equal(window.document.activeElement, categories[1]);

window.document.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
assert.equal(trigger.getAttribute('aria-expanded'), 'false');
assert.equal(window.document.activeElement, trigger);

window.document.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
assert.equal(header.classList.contains('is-mobile-open'), false);
assert.equal(toggle.getAttribute('aria-expanded'), 'false');
assert.equal(window.document.activeElement, toggle);

// A columns-only mega (Resources / Learn / Compare) must open directly to its
// labeled link groups on mobile, without a redundant single-category screen.
toggle.click();
resourcesTrigger.click();
assert.equal(resourcesItem.getAttribute('data-drill'), 'links');
assert.equal(header.getAttribute('data-drill-level'), '2');
assert.equal(resourcesSection.classList.contains('is-open'), true);
assert.equal(resourcesCategory.getAttribute('aria-expanded'), 'true');
assert.equal(resourcesSection.querySelector('.abmm-mega__panel').hidden, false);
assert.equal(window.document.activeElement, resourcesLink);

resourcesItem.querySelector('[data-abmm-back]').click();
assert.equal(resourcesItem.classList.contains('is-open'), false);
assert.equal(resourcesTrigger.getAttribute('aria-expanded'), 'false');
assert.equal(header.hasAttribute('data-drill-level'), false);
toggle.click();
assert.equal(header.classList.contains('is-mobile-open'), false);

// Real destination links dismiss synchronously but keep native navigation.
let destinationDefaultPrevented;
window.document.addEventListener('click', (event) => {
	if (!event.target.closest('a[href]')) return;
	destinationDefaultPrevented = event.defaultPrevented;
	// JSDOM cannot navigate; cancel only after the menu handler was observed.
	event.preventDefault();
});
function openFinance() {
	toggle.click();
	trigger.click();
	categories[0].click();
	assert.equal(header.getAttribute('data-drill-level'), '2');
}
function destinationClick(options = {}) {
	firstLink.dispatchEvent(new window.MouseEvent('click', {
		bubbles: true, cancelable: true, ...options,
	}));
}
openFinance();
destinationClick();
assert.equal(destinationDefaultPrevented, false);
assert.equal(header.classList.contains('is-mobile-open'), false);
assert.equal(header.hasAttribute('data-drill-level'), false);
assert.equal(trigger.getAttribute('aria-expanded'), 'false');
assert.equal(window.document.documentElement.classList.contains('abmm-drawer-open'), false);
assert.equal(window.document.documentElement.classList.contains('abmm-mega-open'), false);
assert.notEqual(window.document.activeElement, toggle);

// Current-page and nonempty fragment destinations also close the drawer.
for (const href of ['https://example.test/', '#content']) {
	firstLink.setAttribute('href', href);
	openFinance();
	destinationClick();
	assert.equal(destinationDefaultPrevented, false);
	assert.equal(header.classList.contains('is-mobile-open'), false);
}
firstLink.setAttribute('href', '/accounts');

// Modified/new-tab/download/placeholder selections do not destroy drill state.
for (const options of [{ctrlKey:true}, {metaKey:true}, {shiftKey:true}, {altKey:true}, {button:1}]) {
	openFinance();
	destinationClick(options);
	assert.equal(header.classList.contains('is-mobile-open'), true);
	drawerClose.click();
}
for (const attributes of [{target:'_blank'}, {download:''}, {href:'#'}, {href:'javascript:void(0)'}]) {
	for (const [name,value] of Object.entries(attributes)) firstLink.setAttribute(name,value);
	openFinance();
	destinationClick();
	assert.equal(header.classList.contains('is-mobile-open'), true);
	drawerClose.click();
	firstLink.removeAttribute('target');
	firstLink.removeAttribute('download');
	firstLink.setAttribute('href','/accounts');
}

openFinance();
window.dispatchEvent(new window.Event('pagehide'));
assert.equal(header.classList.contains('is-mobile-open'), false);
assert.equal(header.hasAttribute('data-drill-level'), false);
assert.equal(window.document.documentElement.classList.contains('abmm-drawer-open'), false);
openFinance();
window.dispatchEvent(new window.PageTransitionEvent('pageshow', {persisted:false}));
assert.equal(header.classList.contains('is-mobile-open'), true);
window.dispatchEvent(new window.PageTransitionEvent('pageshow', {persisted:true}));
assert.equal(header.classList.contains('is-mobile-open'), false);
assert.equal(header.hasAttribute('data-drill-level'), false);
assert.equal(window.document.documentElement.classList.contains('abmm-mega-open'), false);

// Search uses the menu's own destinations, exposes their context and preserves
// navigation. Clearing, Escape and closing must never strand the hidden root.
toggle.click();
const searchInput = header.querySelector('[data-abmm-search] input');
const searchResults = header.querySelector('.abmm-menu-search__results');
const searchStatus = header.querySelector('[role="status"]');
assert.ok(searchInput);
assert.equal(header.querySelectorAll('[data-abmm-search]').length, 1);
searchInput.value = 'finance accounts';
searchInput.dispatchEvent(new window.Event('input'));
assert.equal(header.classList.contains('abmm-is-searching'), true);
assert.equal(searchResults.querySelectorAll('a').length, 1);
assert.equal(searchResults.querySelector('a').getAttribute('href'), '/accounts');
assert.equal(searchResults.querySelector('small').textContent, 'Finance');
assert.equal(searchStatus.textContent, '1 result');
searchInput.value = 'zzzz-no-match';
searchInput.dispatchEvent(new window.Event('input'));
assert.equal(searchResults.querySelectorAll('a').length, 0);
assert.match(searchStatus.textContent, /No matches/);
searchInput.dispatchEvent(new window.KeyboardEvent('keydown', {key:'Escape', bubbles:true}));
assert.equal(header.classList.contains('is-mobile-open'), true);
assert.equal(header.classList.contains('abmm-is-searching'), false);
assert.equal(window.document.activeElement, searchInput);
searchInput.value = 'people';
searchInput.dispatchEvent(new window.Event('input'));
header.querySelector('.abmm-menu-search__clear').click();
assert.equal(searchInput.value, '');
assert.equal(searchResults.hidden, true);
searchInput.value = 'accounts';
searchInput.dispatchEvent(new window.Event('input'));
searchResults.querySelector('a').click();
assert.equal(header.classList.contains('is-mobile-open'), false);
assert.equal(searchInput.value, '');
assert.equal(header.classList.contains('abmm-is-searching'), false);
toggle.click();
trigger.click();
assert.equal(header.querySelector('[data-abmm-back]').getAttribute('aria-label'), 'Back to menu');
categories[0].click();
assert.equal(header.querySelector('[data-abmm-back]').getAttribute('aria-label'), 'Back to Products');
drawerClose.click();

// A menu opened on desktop must not retain its two-panel desktop state after
// the viewport crosses into mobile. Otherwise opening the drawer after a
// rotation or resize exposes the desktop panel beside the mobile categories.
mobileMatches = false;
window.dispatchEvent(new window.Event('resize'));
trigger.click();
assert.equal(header.querySelector('.abmm-nav__item--mega').classList.contains('is-open'), true);
assert.equal(header.querySelector('.abmm-nav__item--mega').hasAttribute('data-drill'), false);
mobileMatches = true;
window.dispatchEvent(new window.Event('resize'));
assert.equal(header.querySelector('.abmm-nav__item--mega').classList.contains('is-open'), false);
assert.equal(trigger.getAttribute('aria-expanded'), 'false');

// Elementor and sticky-header plugins may clone an already-initialized header.
// cloneNode() copies data-abmm-initialized but not event listeners, so the
// observer must initialize the new DOM node despite the copied attribute.
const lateClone = header.cloneNode(true);
lateClone.setAttribute('data-abmm-instance', 'abmm-demo-late-clone');
window.document.body.appendChild(lateClone);

window.setTimeout(() => {
	const lateToggle = lateClone.querySelector('.abmm-nav__toggle');
	const lateTrigger = lateClone.querySelector('[data-abmm-mega-trigger]');
	assert.equal(lateToggle.getAttribute('aria-controls'), 'abmm-demo-1-drawer--abmm-demo-late-clone');
	assert.equal(
		lateTrigger.getAttribute('aria-controls'),
		'abmm-demo-1-item-products-panel--abmm-demo-late-clone'
	);
	assert.equal(
		new Set(Array.from(window.document.querySelectorAll('[id]'), (element) => element.id)).size,
		window.document.querySelectorAll('[id]').length
	);
	lateToggle.click();
	assert.equal(lateClone.classList.contains('is-mobile-open'), true);
	assert.equal(lateToggle.getAttribute('aria-expanded'), 'true');
	lateTrigger.click();
	assert.equal(lateTrigger.getAttribute('aria-expanded'), 'true');

	const externalDrawer = window.document.querySelector('.elementskit-menu-container');
	externalDrawer.classList.add('active');
	window.setTimeout(() => {
		assert.equal(window.document.documentElement.classList.contains('abmm-external-drawer-open'), false);
		externalDrawer.classList.remove('active');
		window.setTimeout(() => {
			assert.equal(window.document.documentElement.classList.contains('abmm-external-drawer-open'), false);
			console.log('Frontend interaction checks passed.');
			dom.window.close();
		}, 0);
	}, 0);
}, 0);
