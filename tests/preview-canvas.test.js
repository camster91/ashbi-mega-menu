'use strict';

// The editor canvas: zoom at the cursor, clamping, Fit, shortcuts and the
// remembered view, using the shipped script in a disposable DOM.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');

const source = fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', 'preview-canvas.js'), 'utf8');
const dom = new JSDOM('<!doctype html><body><div class="abmm-preview-shell"><div class="abmm-preview-sizer"><div class="abmm-preview"><button id="inside">Item</button></div></div></div><input id="field"><button id="button">Save</button></body>', { runScripts: 'outside-only', url: 'https://example.test/wp-admin/' });
const { window } = dom;
window.eval(source);
const C = window.ABMMPreviewCanvas;
const near = (actual, expected, message) => assert.ok(Math.abs(actual - expected) < 1e-9, `${message}: ${actual} != ${expected}`);

// Zoom at the cursor keeps the point under the pointer still.
{
	const view = { x: 40, y: 20, scale: 0.5 };
	const px = 300;
	const py = 200;
	const before = { x: (px - view.x) / view.scale, y: (py - view.y) / view.scale };
	const next = C.zoomAt(view, 1.5, px, py);
	near(next.scale, 1.5, 'zoomAt applies the new scale');
	near((px - next.x) / next.scale, before.x, 'the world point under the cursor stays put (x)');
	near((py - next.y) / next.scale, before.y, 'the world point under the cursor stays put (y)');
	const same = C.zoomAt(view, view.scale, px, py);
	near(same.x, view.x, 'zooming to the same scale does not move the view');
}

// Clamped to 25–200%, with bad input falling back to 100%.
assert.equal(C.clampScale(0.1), 0.25);
assert.equal(C.clampScale(9), 2);
assert.equal(C.clampScale(0.8), 0.8);
assert.equal(C.clampScale('nope'), 1);
assert.equal(C.clampScale(-1), 1);
assert.equal(C.zoomAt({ x: 0, y: 0, scale: 1 }, 50, 0, 0).scale, 2, 'zoomAt clamps at 200%');
assert.equal(C.zoomAt({ x: 0, y: 0, scale: 1 }, 0.01, 0, 0).scale, 0.25, 'zoomAt clamps at 25%');
assert.equal(C.wheelZoomScale(2, -100), 2, 'pinching out stops at 200%');
assert.equal(C.wheelZoomScale(0.25, 100), 0.25, 'pinching in stops at 25%');
assert.ok(C.wheelZoomScale(1, -10) > 1 && C.wheelZoomScale(1, 10) < 1, 'wheel up zooms in, wheel down zooms out');
assert.ok(C.wheelZoomScale(1, -5000) <= 1.25, 'a huge mouse-wheel notch is capped at about one step');
assert.ok(Math.abs(C.wheelZoomScale(1, -100) - Math.exp(0.2)) < 1e-9, 'one mouse notch zooms about 20%');
assert.ok(Math.abs(C.wheelZoomScale(1, -5) - Math.exp(0.05)) < 1e-9, 'small pinch deltas stay responsive');

// Steps.
assert.equal(C.stepScale(1, 1), 1.25);
assert.equal(C.stepScale(1, -1), 0.9);
assert.equal(C.stepScale(0.8, 1), 0.9, 'from an in-between scale, step to the next preset');
assert.equal(C.stepScale(2, 1), 2);
assert.equal(C.stepScale(0.25, -1), 0.25);

// Fit: never above 100%, width only for desktop, both axes and centred for devices.
{
	const desktop = C.fitView({ width: 864, height: 640 }, { width: 1100, height: 700 }, false);
	assert.equal(desktop.scale, 0.72, 'desktop fits the width (rounded down)');
	assert.equal(desktop.y, 32, 'desktop sits at the top, so an opening dropdown does not rescale it');
	assert.equal(desktop.x, Math.round((864 - 1100 * 0.72) / 2), 'desktop is centred horizontally');
	const phone = C.fitView({ width: 864, height: 640 }, { width: 390, height: 836 }, true);
	assert.equal(phone.scale, Math.floor(((640 - 64) / 836) * 100) / 100, 'devices also fit the height');
	assert.ok(phone.y >= 32, 'devices keep the padding');
	const small = C.fitView({ width: 2000, height: 2000 }, { width: 390, height: 836 }, true);
	assert.equal(small.scale, 1, 'Fit never enlarges past 100%');
	const tiny = C.fitView({ width: 100, height: 100 }, { width: 1100, height: 700 }, true);
	assert.equal(tiny.scale, 0.25, 'Fit respects the 25% floor');
}

// Shortcuts: Ctrl/Cmd + = / + / − / 0 / 1.
const key = (k, mods = {}) => ({ key: k, ctrlKey: !!mods.ctrl, metaKey: !!mods.meta, altKey: !!mods.alt });
assert.equal(C.shortcutAction(key('=', { ctrl: true })), 'in');
assert.equal(C.shortcutAction(key('+', { meta: true })), 'in');
assert.equal(C.shortcutAction(key('-', { ctrl: true })), 'out');
assert.equal(C.shortcutAction(key('0', { meta: true })), 'fit');
assert.equal(C.shortcutAction(key('1', { ctrl: true })), 'actual');
assert.equal(C.shortcutAction(key('0')), '', 'a bare 0 is typing, not a shortcut');
assert.equal(C.shortcutAction(key('1', { ctrl: true, alt: true })), '', 'Alt combinations are left alone');
assert.equal(C.shortcutAction(key('s', { ctrl: true })), '');

// Fields keep their keys and scrolling.
const doc = window.document;
assert.equal(C.isTypingTarget(doc.getElementById('field')), true);
assert.equal(C.isTypingTarget(doc.getElementById('button')), false);
const checkbox = doc.createElement('input');
checkbox.type = 'checkbox';
assert.equal(C.isTypingTarget(checkbox), false);
assert.equal(C.isTypingTarget(doc.createElement('textarea')), true);

// Wheel units: lines and pages become pixels.
assert.deepEqual({ ...C.wheelDelta({ deltaX: 1, deltaY: 2, deltaMode: 1 }) }, { x: 16, y: 32 });
assert.deepEqual({ ...C.wheelDelta({ deltaX: 0, deltaY: 1, deltaMode: 2 }) }, { x: 0, y: 400 });

// A wheel over a live frame scrolls its content first, then pans the canvas.
{
	const box = doc.createElement('div');
	box.style.overflowY = 'auto';
	const inner = doc.createElement('p');
	box.appendChild(inner);
	doc.body.appendChild(box);
	Object.defineProperty(box, 'scrollHeight', { value: 500 });
	Object.defineProperty(box, 'clientHeight', { value: 200 });
	box.scrollTop = 0;
	assert.equal(C.canScroll(inner, { x: 0, y: 40 }), true, 'content that can scroll down takes the wheel');
	assert.equal(C.canScroll(inner, { x: 0, y: -40 }), false, 'at the top, scrolling up pans the canvas');
	box.scrollTop = 300;
	assert.equal(C.canScroll(inner, { x: 0, y: 40 }), false, 'at the bottom, scrolling down pans the canvas');
	assert.equal(C.canScroll(inner, { x: 0, y: -40 }), true);
	assert.equal(C.canScroll(inner, { x: 30, y: 0 }), false, 'sideways scrolling pans when nothing scrolls sideways');
	box.remove();
}

// The dotted grid follows the view.
{
	const grid = C.gridStyle({ x: -10, y: 37, scale: 0.5 });
	assert.equal(grid.size, 8);
	assert.equal(grid.x, 6);
	assert.equal(grid.y, 5);
}

// Remembered view: per user and device, and safe when storage throws.
assert.equal(C.storageKey(7, 'mobile'), 'abmmCanvas:7:mobile');
assert.equal(C.storageKey('x', ''), 'abmmCanvas:0:desktop');
{
	const memory = new Map();
	const storage = { getItem: (k) => (memory.has(k) ? memory.get(k) : null), setItem: (k, v) => memory.set(k, String(v)) };
	assert.equal(C.saveView(storage, 'k', 'manual', { x: 10.4, y: -3.6, scale: 1.5 }), true);
	assert.deepEqual({ ...C.loadView(storage, 'k') }, { mode: 'manual', x: 10, y: -4, scale: 1.5 });
	C.saveView(storage, 'k', 'fit', { x: 1, y: 1, scale: 1 });
	assert.deepEqual({ ...C.loadView(storage, 'k') }, { mode: 'fit' });
	memory.set('bad', '{"mode":"manual","x":"1"}');
	assert.equal(C.loadView(storage, 'bad'), null, 'malformed views are ignored');
	memory.set('huge', '{"mode":"manual","x":0,"y":0,"scale":40}');
	assert.equal(C.loadView(storage, 'huge').scale, 2, 'saved scales are clamped');
	const throwing = { getItem() { throw new Error('denied'); }, setItem() { throw new Error('denied'); } };
	assert.equal(C.loadView(throwing, 'k'), null, 'blocked storage reads as nothing saved');
	assert.equal(C.saveView(throwing, 'k', 'fit', {}), false, 'blocked storage does not throw');
	assert.equal(C.loadView(null, 'k'), null);
}

// The controller: Ctrl+wheel zooms at the cursor, shortcuts, Fit and memory.
{
	const shell = doc.querySelector('.abmm-preview-shell');
	const world = doc.querySelector('.abmm-preview-sizer');
	Object.defineProperty(shell, 'clientWidth', { value: 800 });
	Object.defineProperty(shell, 'clientHeight', { value: 600 });
	shell.getBoundingClientRect = () => ({ left: 100, top: 50, right: 900, bottom: 650, width: 800, height: 600 });
	let device = 'desktop';
	const changes = [];
	window.localStorage.clear();
	const canvas = C.create({
		shell,
		world,
		content: () => (device === 'desktop' ? { width: 1100, height: 600 } : { width: 390, height: 836 }),
		fitHeight: () => device !== 'desktop',
		device: () => device,
		userId: 3,
		onChange: (view, mode) => changes.push(mode),
	});
	canvas.restore();
	assert.equal(canvas.mode(), 'fit', 'with nothing saved, a device starts at Fit');
	assert.equal(canvas.view().scale, 0.66);
	assert.ok(world.style.transform.includes('scale(0.66)'), 'the frame is transformed');
	assert.ok(shell.style.backgroundSize.startsWith(16 * 0.66 + 'px'), 'the grid scales with the view');

	const before = canvas.view();
	const wheel = new window.WheelEvent('wheel', { deltaY: -50, ctrlKey: true, clientX: 400, clientY: 250, bubbles: true, cancelable: true });
	shell.dispatchEvent(wheel);
	assert.equal(wheel.defaultPrevented, true, 'Ctrl+wheel never zooms the whole page');
	const after = canvas.view();
	assert.ok(after.scale > before.scale, 'Ctrl+wheel up zooms in');
	near((300 - after.x) / after.scale, (300 - before.x) / before.scale, 'Ctrl+wheel zooms at the cursor');
	assert.equal(canvas.mode(), 'manual');

	const pan = new window.WheelEvent('wheel', { deltaX: 10, deltaY: 30, bubbles: true, cancelable: true });
	shell.dispatchEvent(pan);
	assert.equal(canvas.view().x, after.x - 10, 'two-finger scroll pans across');
	assert.equal(canvas.view().y, after.y - 30, 'two-finger scroll pans down');

	doc.getElementById('field').focus();
	const typingScroll = new window.WheelEvent('wheel', { deltaY: 30, bubbles: true, cancelable: true });
	shell.dispatchEvent(typingScroll);
	assert.equal(typingScroll.defaultPrevented, false, 'with a field focused, scrolling is left to the page');
	const typingZero = new window.KeyboardEvent('keydown', { key: '0', ctrlKey: true, bubbles: true, cancelable: true });
	doc.getElementById('field').dispatchEvent(typingZero);
	assert.equal(typingZero.defaultPrevented, false, 'shortcuts do not fire while typing');
	doc.getElementById('field').blur();

	const press = (k) => {
		const event = new window.KeyboardEvent('keydown', { key: k, ctrlKey: true, bubbles: true, cancelable: true });
		doc.body.dispatchEvent(event);
		return event;
	};
	assert.equal(press('1').defaultPrevented, true);
	assert.equal(canvas.view().scale, 1, 'Ctrl+1 is 100%');
	press('=');
	assert.equal(canvas.view().scale, 1.25, 'Ctrl+= zooms in one step');
	press('-');
	press('-');
	assert.equal(canvas.view().scale, 0.9, 'Ctrl+− zooms out one step');
	press('0');
	assert.equal(canvas.mode(), 'fit', 'Ctrl+0 fits');
	assert.equal(canvas.view().scale, 0.66);

	// Drag on empty canvas pans; a drag that starts on the frame does not.
	const pointer = (type, target, x, y, button = 0) => target.dispatchEvent(new window.MouseEvent(type, { clientX: x, clientY: y, button, bubbles: true, cancelable: true }));
	const start = canvas.view();
	pointer('pointerdown', shell, 200, 200);
	pointer('pointermove', shell, 260, 180);
	pointer('pointerup', shell, 260, 180);
	assert.equal(canvas.view().x, start.x + 60, 'dragging empty canvas pans');
	assert.equal(canvas.view().y, start.y - 20);
	const afterDrag = canvas.view();
	pointer('pointerdown', doc.getElementById('inside'), 200, 200);
	pointer('pointermove', shell, 300, 300);
	pointer('pointerup', shell, 300, 300);
	assert.deepEqual(canvas.view(), afterDrag, 'a drag on the live frame is left to the menu');
	pointer('pointerdown', doc.getElementById('inside'), 200, 200, 1);
	pointer('pointermove', shell, 210, 200);
	pointer('pointerup', shell, 210, 200);
	assert.equal(canvas.view().x, afterDrag.x + 10, 'middle-button drag pans anywhere');

	// Each device keeps its own view.
	canvas.zoomTo(1.5);
	const saved = JSON.parse(window.localStorage.getItem('abmmCanvas:3:desktop'));
	assert.equal(saved.scale, 1.5, 'the view is saved for this user and device');
	device = 'mobile';
	canvas.restore();
	assert.equal(canvas.mode(), 'fit', 'another device starts at its own Fit');
	device = 'desktop';
	canvas.restore();
	assert.equal(canvas.view().scale, 1.5, 'switching back restores the saved desktop view');
	assert.ok(changes.length > 0);
}

console.log('Canvas zoom at cursor, clamping, Fit, shortcuts, pan and remembered views passed.');
dom.window.close();
