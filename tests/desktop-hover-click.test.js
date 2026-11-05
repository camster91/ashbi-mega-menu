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
						<li class="abmm-nav__item abmm-nav__item--mega" id="products">
							<button data-abmm-mega-trigger aria-expanded="false" aria-controls="abmm-hover-1-panel">Products</button>
							<div class="abmm-mega" id="abmm-hover-1-panel" hidden data-abmm-mega-panel>
								<section class="abmm-mega__section is-open" data-abmm-section="one">
									<button class="abmm-mega__cat is-active" data-abmm-cat="one" aria-expanded="true">One</button>
									<div class="abmm-mega__panel is-active"><a id="one-link" href="/one">One</a></div>
								</section>
								<section class="abmm-mega__section" data-abmm-section="two">
									<button class="abmm-mega__cat" data-abmm-cat="two" aria-expanded="false">Two</button>
									<div class="abmm-mega__panel" hidden><a href="/two">Two</a></div>
								</section>
							</div>
						</li>
						<li class="abmm-nav__item abmm-nav__item--mega" id="learn">
							<button data-abmm-mega-trigger aria-expanded="false" aria-controls="abmm-hover-1-learn">Learn</button>
							<div class="abmm-mega" id="abmm-hover-1-learn" hidden data-abmm-mega-panel><a href="/learn">Learn</a></div>
						</li>
					</ul>
				</nav>
			</header>
			<a id="after" href="/after">After the menu</a>
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

const wait = (ms) => new Promise((resolve) => window.setTimeout(resolve, ms));
const hover = (element) => element.dispatchEvent(new window.MouseEvent('mouseenter'));
const unhover = (element) => element.dispatchEvent(new window.MouseEvent('mouseleave'));

(async () => {
	const item = window.document.getElementById('products');
	const learn = window.document.getElementById('learn');
	const trigger = item.querySelector('[data-abmm-mega-trigger]');
	const learnTrigger = learn.querySelector('[data-abmm-mega-trigger]');
	const header = window.document.querySelector('.abmm-header');
	const [firstCat, secondCat] = item.querySelectorAll('.abmm-mega__cat');
	const inside = window.document.getElementById('one-link');
	const after = window.document.getElementById('after');

	// A pointer only passing over the bar opens nothing.
	hover(item);
	assert.equal(trigger.getAttribute('aria-expanded'), 'false', 'hover does not open the panel at once');
	unhover(item);
	await wait(150);
	assert.equal(trigger.getAttribute('aria-expanded'), 'false', 'a pointer passing over the item opens nothing');

	hover(item);
	after.dispatchEvent(new window.MouseEvent('mousedown', { bubbles: true }));
	await wait(150);
	assert.equal(trigger.getAttribute('aria-expanded'), 'false', 'an outside press cancels the pending hover');

	// A pointer that stays opens the panel.
	hover(item);
	await wait(150);
	assert.equal(trigger.getAttribute('aria-expanded'), 'true', 'hover opens the panel');

	trigger.click();
	assert.equal(trigger.getAttribute('aria-expanded'), 'true', 'click after hover keeps the panel open');

	trigger.click();
	assert.equal(trigger.getAttribute('aria-expanded'), 'false', 'a second click closes the panel');

	trigger.click();
	assert.equal(trigger.getAttribute('aria-expanded'), 'true', 'click without hover opens the panel');

	// Tabbing out of the menu closes the panel and its scrim.
	inside.focus();
	assert.equal(trigger.getAttribute('aria-expanded'), 'true', 'focus inside the panel keeps it open');
	after.focus();
	assert.equal(trigger.getAttribute('aria-expanded'), 'false', 'tabbing out of the menu closes the panel');
	assert.equal(item.querySelector('[data-abmm-mega-panel]').hidden, true);
	assert.equal(header.classList.contains('abmm-has-open-menu'), false, 'and removes the page scrim');

	// Clicking blank space (no new focus target) leaves an open panel alone.
	trigger.click();
	assert.equal(trigger.getAttribute('aria-expanded'), 'true');
	inside.focus();
	inside.blur();
	assert.equal(trigger.getAttribute('aria-expanded'), 'true', 'a blur with no new focus target keeps the panel open');

	// A click before the hover delay ends opens at once and does not reopen later.
	trigger.click();
	assert.equal(trigger.getAttribute('aria-expanded'), 'false');
	hover(item);
	trigger.click();
	assert.equal(trigger.getAttribute('aria-expanded'), 'true', 'a tap during the delay opens the panel');
	await wait(150);
	assert.equal(trigger.getAttribute('aria-expanded'), 'true', 'and the pending hover does not undo it');
	trigger.click();
	assert.equal(trigger.getAttribute('aria-expanded'), 'false');

	// Moving between open panels is immediate.
	hover(item);
	await wait(150);
	hover(learn);
	assert.equal(learnTrigger.getAttribute('aria-expanded'), 'true', 'moving along the bar switches an open panel at once');
	assert.equal(trigger.getAttribute('aria-expanded'), 'false');
	hover(item);
	assert.equal(trigger.getAttribute('aria-expanded'), 'true');

	// The category rail switches only when the pointer rests on a category.
	hover(secondCat);
	assert.equal(secondCat.getAttribute('aria-expanded'), 'false', 'the category does not switch at once');
	unhover(secondCat);
	await wait(170);
	assert.equal(secondCat.getAttribute('aria-expanded'), 'false', 'crossing a category on the way to a link does not switch it');
	hover(secondCat);
	await wait(170);
	assert.equal(secondCat.getAttribute('aria-expanded'), 'true', 'resting on a category switches it');
	firstCat.click();
	assert.equal(firstCat.getAttribute('aria-expanded'), 'true', 'clicking a category switches at once');

	// Keyboard focus on a link is not pulled away by hovering another category.
	const focusVisible = window.Element.prototype.matches;
	window.Element.prototype.matches = function (selector) {
		return selector === ':focus-visible' ? true : focusVisible.call(this, selector);
	};
	inside.focus();
	hover(secondCat);
	await wait(170);
	assert.equal(firstCat.getAttribute('aria-expanded'), 'true', 'hover does not hide the link a keyboard user is on');
	unhover(secondCat);

	// Keyboard focus also keeps a hover-opened panel open after the pointer leaves.
	unhover(header);
	await wait(350);
	assert.equal(trigger.getAttribute('aria-expanded'), 'true', 'a keyboard user keeps the panel when the pointer leaves');

	// Focus left by a click does not: the panel closes once the pointer has gone.
	window.Element.prototype.matches = function (selector) {
		return selector === ':focus-visible' ? false : focusVisible.call(this, selector);
	};
	unhover(header);
	await wait(350);
	assert.equal(trigger.getAttribute('aria-expanded'), 'false', 'focus left by a click does not hold the panel open');
	window.Element.prototype.matches = focusVisible;

	// Escape after a hover-opened panel leaves focus where it was on the page.
	hover(item);
	await wait(150);
	after.focus();
	if (trigger.getAttribute('aria-expanded') !== 'true') {
		hover(item);
		await wait(150);
	}
	assert.equal(trigger.getAttribute('aria-expanded'), 'true');
	after.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
	assert.equal(trigger.getAttribute('aria-expanded'), 'false', 'Escape closes the panel');
	assert.equal(window.document.activeElement, after, 'and does not pull focus from outside the menu');

	// A cloned header gets its own root id too.
	header.id = 'abmm-hover-1';
	trigger.click();
	const clone = header.cloneNode(true);
	clone.setAttribute('data-abmm-instance', 'abmm-hover-2');
	window.document.body.appendChild(clone);
	await wait(20);
	assert.equal(clone.id, 'abmm-hover-1--abmm-hover-2', 'a clone does not repeat the root id');

	const cloneTrigger = clone.querySelector('[data-abmm-mega-trigger]');
	assert.equal(cloneTrigger.getAttribute('aria-expanded'), 'false', 'a clone does not inherit an open desktop panel');
	trigger.click();
	cloneTrigger.focus();
	hover(item);
	await wait(150);
	assert.equal(trigger.getAttribute('aria-expanded'), 'true');
	cloneTrigger.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
	assert.equal(trigger.getAttribute('aria-expanded'), 'false', 'Escape closes a hover panel while another header has focus');
	assert.equal(window.document.activeElement, cloneTrigger);

	console.log('Desktop hover then click keeps the panel open, hover waits for intent, and tabbing out closes it.');
	dom.window.close();
})().catch((error) => {
	console.error(error);
	process.exit(1);
});
