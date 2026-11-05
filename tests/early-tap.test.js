'use strict';

// A tap on the phone menu button before the menu script has run is kept by a small
// script in the page head, then acted on exactly once when the header is set up.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');

const read = (...parts) => fs.readFileSync(path.join(__dirname, '..', ...parts), 'utf8');
const php = read('includes', 'class-abmm-frontend.php');
const head = php.match(/const EARLY_TAP_SCRIPT = '([^']+)';/)[1];

assert.match(php, /add_action\( 'wp_head', array\( \$this, 'print_early_tap_script' \), 1 \)/, 'It prints early in the head.');
assert.match(php, /wp_print_inline_script_tag\( self::EARLY_TAP_SCRIPT, array\( 'nitro-exclude' => true \) \)/, 'NitroPack does not delay it.');
assert.match(php, /if \( ABMM_Data::instance\(\)->get_all\(\) \) \{\s+wp_print_inline_script_tag/, 'It prints only on sites with a menu.');
assert.ok(head.length < 400, 'It stays very small.');

const styles = [
	{
		name: 'Classic',
		script: read('assets', 'classic', 'emm-frontend.js'),
		toggle: '.emm-nav__toggle',
		html: read('tests', 'fixtures', 'emm', 'expected-home-main_nav.html'),
		header: 'header.emm-header',
	},
	{
		name: 'Native',
		script: read('assets', 'js', 'frontend.js'),
		toggle: '.abmm-nav__toggle',
		html: '<div class="abmm-header" data-abmm-instance="abmm-tap-1"><nav class="abmm-nav"><div class="abmm-nav__bar"><button class="abmm-nav__toggle" aria-expanded="false" aria-controls="tap-drawer">Menu</button></div><div class="abmm-nav__drawer" id="tap-drawer"><ul class="abmm-nav__list"><li class="abmm-nav__item"><a href="/a">A</a></li></ul></div></nav></div>',
		header: '.abmm-header',
	},
];

function page(style, { mobile = true } = {}) {
	const { window } = new JSDOM(`<!doctype html><html><body>${style.html}</body></html>`, { runScripts: 'outside-only', pretendToBeVisual: true, url: 'https://example.test/' });
	window.matchMedia = (query) => ({ matches: mobile ? query.includes('max-width') : !query.includes('max-width'), media: query, addEventListener() {}, removeEventListener() {}, addListener() {}, removeListener() {} });
	window.requestAnimationFrame = (callback) => callback();
	window.ResizeObserver = class { observe() {} disconnect() {} };
	const header = window.document.querySelector(style.header);
	const toggle = header.querySelector(style.toggle);
	return {
		window,
		header,
		toggle,
		isOpen: () => header.classList.contains('is-mobile-open'),
		runHead: () => window.eval(head),
		runMenu: () => {
			window.eval(style.script);
			window.document.dispatchEvent(new window.Event('DOMContentLoaded'));
		},
	};
}

// Counts how many times the drawer's state really changes.
function changes(p) {
	let count = 0;
	new p.window.MutationObserver((records) => {
		const states = records.map((record) => String(record.oldValue || '').split(/\s+/).includes('is-mobile-open'));
		states.push(p.isOpen());
		for (let i = 1; i < states.length; i++) {
			if (states[i] !== states[i - 1]) count++;
		}
	}).observe(p.header, { attributes: true, attributeFilter: ['class'], attributeOldValue: true });
	return () => count;
}

(async () => {
	const settle = () => new Promise((resolve) => setImmediate(resolve));
	for (const style of styles) {
		// A tap before the menu script opens the menu once it runs, and only once.
		{
			const p = page(style);
			const count = changes(p);
			p.runHead();
			p.toggle.click();
			assert.equal(p.isOpen(), false, `${style.name}: nothing happens before the menu script runs.`);
			p.runMenu();
			await settle();
			assert.equal(p.isOpen(), true, `${style.name}: the kept tap opens the menu.`);
			assert.equal(p.toggle.getAttribute('aria-expanded'), 'true', `${style.name}: the button reads as open.`);
			assert.equal(count(), 1, `${style.name}: it changes state once, so it does not open and close.`);
			assert.equal(p.window.document.__abmmEarlyTap, null, `${style.name}: the tap is used up.`);
			p.toggle.click();
			assert.equal(p.isOpen(), false, `${style.name}: the next tap closes it.`);
			assert.equal(p.window.document.__abmmEarlyTap, null, `${style.name}: later taps are not kept.`);
		}

		// Several early taps are still one tap.
		{
			const p = page(style);
			p.runHead();
			p.toggle.click();
			p.toggle.click();
			p.toggle.click();
			p.runMenu();
			assert.equal(p.isOpen(), true, `${style.name}: repeated early taps open the menu once.`);
		}

		// No tap: nothing opens by itself.
		{
			const p = page(style);
			p.runHead();
			p.runMenu();
			assert.equal(p.isOpen(), false, `${style.name}: without a tap the menu stays closed.`);
		}

		// The menu script ran first: the head script changes nothing.
		for (const order of ['menu first', 'head first, no early tap']) {
			const p = page(style);
			if ('menu first' === order) {
				p.runMenu();
				p.runHead();
			} else {
				p.runHead();
				p.runMenu();
			}
			p.toggle.click();
			assert.equal(p.isOpen(), true, `${style.name} (${order}): one tap opens.`);
			assert.equal(p.window.document.__abmmEarlyTap, undefined, `${style.name} (${order}): the tap is not kept.`);
			p.toggle.click();
			assert.equal(p.isOpen(), false, `${style.name} (${order}): the next tap closes.`);
		}

		// If the page has since become wide, a kept tap doesn't open a phone drawer.
		{
			const p = page(style, { mobile: false });
			p.runHead();
			p.toggle.click();
			p.runMenu();
			assert.equal(p.isOpen(), false, `${style.name}: a kept tap is dropped on a wide layout.`);
			assert.equal(p.window.document.__abmmEarlyTap, null, `${style.name}: and it is used up.`);
		}

		// A clone made after the tap doesn't replay it.
		{
			const p = page(style);
			p.runHead();
			p.toggle.click();
			const clone = p.header.cloneNode(true);
			p.header.parentNode.appendChild(clone);
			p.runMenu();
			await settle();
			assert.equal(p.isOpen(), true, `${style.name}: the real header opens.`);
			assert.equal(clone.classList.contains('is-mobile-open'), false, `${style.name}: its clone does not.`);
		}

		// A tap that landed on a hidden sticky clone opens the real header, not the clone.
		for (const order of ['clone set up last', 'clone set up first']) {
			const p = page(style);
			p.runHead();
			const wrap = p.window.document.createElement('div');
			wrap.className = 'elementor-sticky__spacer';
			const clone = p.header.cloneNode(true);
			wrap.appendChild(clone);
			if ('clone set up last' === order) {
				p.header.parentNode.appendChild(wrap);
			} else {
				p.header.parentNode.insertBefore(wrap, p.header);
			}
			clone.querySelector(style.toggle).click();
			p.runMenu();
			await settle();
			assert.equal(p.isOpen(), true, `${style.name} (${order}): the real header opens.`);
			assert.equal(clone.classList.contains('is-mobile-open'), false, `${style.name} (${order}): the clone stays closed.`);
			assert.equal(p.window.document.__abmmEarlyTap, null, `${style.name} (${order}): the tap is used up.`);
		}

		// A clone that appears once the real header is set up still hands its tap over.
		{
			const p = page(style);
			p.runMenu();
			p.runHead();
			const wrap = p.window.document.createElement('div');
			wrap.className = 'elementor-sticky__spacer';
			const clone = p.header.cloneNode(true);
			wrap.appendChild(clone);
			p.header.parentNode.appendChild(wrap);
			clone.querySelector(style.toggle).click();
			await settle();
			await settle();
			assert.equal(p.isOpen(), true, `${style.name}: a tap on a new clone opens the real header.`);
			assert.equal(clone.classList.contains('is-mobile-open'), false, `${style.name}: and not the clone.`);
		}

		// The header was rebuilt between the tap and the menu script.
		{
			const p = page(style);
			p.runHead();
			p.toggle.click();
			const parent = p.header.parentNode;
			parent.innerHTML = parent.innerHTML;
			p.runMenu();
			await settle();
			const rebuilt = p.window.document.querySelector(style.header);
			assert.notEqual(rebuilt, p.header, `${style.name}: the fixture really rebuilt the header.`);
			assert.equal(rebuilt.classList.contains('is-mobile-open'), true, `${style.name}: the rebuilt header opens.`);
			assert.equal(p.window.document.__abmmEarlyTap, null, `${style.name}: the tap is used up.`);
		}

		// A kept tap for a menu no longer on the page opens nothing else.
		{
			const p = page(style);
			p.runHead();
			p.toggle.click();
			p.header.remove();
			const other = p.header.cloneNode(true);
			other.setAttribute('data-emm-id', 'other_menu');
			other.setAttribute('data-abmm-instance', 'other-menu');
			p.window.document.body.appendChild(other);
			p.runMenu();
			await settle();
			assert.equal(other.classList.contains('is-mobile-open'), false, `${style.name}: a different menu stays closed.`);
		}

		// A click elsewhere in the page is not kept.
		{
			const p = page(style);
			p.runHead();
			p.window.document.body.click();
			assert.equal(p.window.document.__abmmEarlyTap, undefined, `${style.name}: other clicks are ignored.`);
		}
	}
	console.log('Early taps on the phone menu button are kept and replayed once, in both menu styles.');
})().catch((error) => {
	console.error(error);
	process.exit(1);
});
