/**
 * Ashbi Mega Menu: the editor's preview canvas.
 *
 * A pan-and-zoom surface like a design tool. The view is { x, y, scale }:
 * the frame is drawn at (x, y) in canvas pixels and scaled by `scale`.
 * The math is pure and exported for tests; `create()` wires it to the DOM.
 */
(function (window) {
	'use strict';

	var MIN_SCALE = 0.25;
	var MAX_SCALE = 2;
	var STEPS = [0.25, 0.33, 0.5, 0.67, 0.75, 0.9, 1, 1.25, 1.5, 2];
	var PRESETS = [0.25, 0.5, 0.75, 1, 1.5, 2];
	var FIT_PADDING = 32;
	var GRID = 16;

	function clampScale(scale) {
		var value = Number(scale);
		if (!isFinite(value) || value <= 0) return 1;
		return Math.min(MAX_SCALE, Math.max(MIN_SCALE, value));
	}

	/**
	 * Zoom to `scale` while the canvas point (px, py) stays under the cursor.
	 */
	function zoomAt(view, scale, px, py) {
		var next = clampScale(scale);
		var worldX = (px - view.x) / view.scale;
		var worldY = (py - view.y) / view.scale;
		return { x: px - worldX * next, y: py - worldY * next, scale: next };
	}

	function panBy(view, dx, dy) {
		return { x: view.x + dx, y: view.y + dy, scale: view.scale };
	}

	/**
	 * Fit the content into the viewport, never above 100%. Desktop fits the
	 * width and sits at the top, so a dropdown opening below the bar does not
	 * rescale the frame. Devices also fit the height and are centred.
	 */
	function fitView(viewport, content, fitHeight) {
		var availableWidth = Math.max(1, viewport.width - FIT_PADDING * 2);
		var availableHeight = Math.max(1, viewport.height - FIT_PADDING * 2);
		var scale = Math.min(1, availableWidth / Math.max(1, content.width));
		if (fitHeight) scale = Math.min(scale, availableHeight / Math.max(1, content.height));
		scale = clampScale(Math.floor(scale * 100) / 100);
		var y = fitHeight ? Math.max(FIT_PADDING, (viewport.height - content.height * scale) / 2) : FIT_PADDING;
		return { x: Math.round((viewport.width - content.width * scale) / 2), y: Math.round(y), scale: scale };
	}

	/** The next zoom step in or out from the current scale. */
	function stepScale(scale, direction) {
		if (direction > 0) {
			return STEPS.filter(function (step) { return step > scale + 0.001; })[0] || MAX_SCALE;
		}
		return STEPS.filter(function (step) { return step < scale - 0.001; }).pop() || MIN_SCALE;
	}

	/** Wheel deltas in pixels, whatever unit the browser reports. */
	function wheelDelta(event) {
		var unit = event.deltaMode === 1 ? 16 : event.deltaMode === 2 ? 400 : 1;
		return { x: (event.deltaX || 0) * unit, y: (event.deltaY || 0) * unit };
	}

	/**
	 * Ctrl/Cmd + wheel and trackpad pinch (which browsers send as a Ctrl
	 * wheel) zoom smoothly; a mouse wheel's large notches stay usable.
	 */
	function wheelZoomScale(scale, deltaY) {
		var delta = Math.max(-100, Math.min(100, deltaY));
		// Pinch sends many small deltas; a mouse notch is one large one (~100).
		var rate = Math.abs(delta) < 20 ? 0.01 : 0.002;
		return clampScale(scale * Math.exp(-delta * rate));
	}

	/** Ctrl/Cmd + = / + zooms in, − zooms out, 0 fits, 1 is 100%. */
	function shortcutAction(event) {
		if (!(event.ctrlKey || event.metaKey) || event.altKey) return '';
		switch (event.key) {
			case '=':
			case '+':
				return 'in';
			case '-':
			case '_':
				return 'out';
			case '0':
				return 'fit';
			case '1':
				return 'actual';
		}
		return '';
	}

	/** Whether keyboard focus is in a field, where typing must not be taken over. */
	function isTypingTarget(element) {
		if (!element || element.nodeType !== 1) return false;
		if (element.isContentEditable) return true;
		var tag = element.tagName;
		if (tag === 'TEXTAREA' || tag === 'SELECT') return true;
		if (tag !== 'INPUT') return false;
		var type = String(element.type || 'text').toLowerCase();
		return ['button', 'checkbox', 'radio', 'range', 'color', 'submit', 'reset', 'file', 'image'].indexOf(type) === -1;
	}

	/**
	 * Whether `element` or an ancestor can still scroll by `delta` (pixels),
	 * so a wheel over a live frame scrolls its content before the canvas.
	 */
	function canScroll(element, delta) {
		for (var node = element; node && node.nodeType === 1; node = node.parentElement) {
			var win = node.ownerDocument.defaultView;
			var style = win && win.getComputedStyle ? win.getComputedStyle(node) : null;
			var isRoot = node === node.ownerDocument.scrollingElement;
			var scrollsY = isRoot || (style && /(auto|scroll|overlay)/.test(style.overflowY));
			var scrollsX = isRoot || (style && /(auto|scroll|overlay)/.test(style.overflowX));
			if (scrollsY && delta.y) {
				var maxY = node.scrollHeight - node.clientHeight;
				if (maxY > 1 && ((delta.y > 0 && node.scrollTop < maxY - 1) || (delta.y < 0 && node.scrollTop > 0))) return true;
			}
			if (scrollsX && delta.x) {
				var maxX = node.scrollWidth - node.clientWidth;
				if (maxX > 1 && ((delta.x > 0 && node.scrollLeft < maxX - 1) || (delta.x < 0 && node.scrollLeft > 0))) return true;
			}
		}
		return false;
	}

	/** The dotted grid moves and scales with the view. */
	function gridStyle(view) {
		var size = GRID * view.scale;
		var mod = function (value) { return ((value % size) + size) % size; };
		return { size: size, x: mod(view.x), y: mod(view.y) };
	}

	function storageKey(userId, device) {
		return 'abmmCanvas:' + (parseInt(userId, 10) || 0) + ':' + String(device || 'desktop');
	}

	/** Reads a saved view; private windows and blocked storage give null. */
	function loadView(storage, key) {
		try {
			var raw = storage && storage.getItem(key);
			if (!raw) return null;
			var saved = JSON.parse(raw);
			if (!saved || typeof saved !== 'object') return null;
			if (saved.mode === 'fit') return { mode: 'fit' };
			if (![saved.x, saved.y, saved.scale].every(function (value) { return typeof value === 'number' && isFinite(value); })) return null;
			return { mode: 'manual', x: saved.x, y: saved.y, scale: clampScale(saved.scale) };
		} catch (e) {
			return null;
		}
	}

	function saveView(storage, key, mode, view) {
		try {
			if (!storage) return false;
			storage.setItem(key, JSON.stringify(mode === 'fit' ? { mode: 'fit' } : { mode: 'manual', x: Math.round(view.x), y: Math.round(view.y), scale: view.scale }));
			return true;
		} catch (e) {
			return false;
		}
	}

	/**
	 * Wire the canvas to the DOM.
	 *
	 * options.shell     the canvas viewport element
	 * options.world     the element that is translated and scaled
	 * options.content   function () => { width, height } of the frame
	 * options.fitHeight function () => bool, whether Fit uses the height too
	 * options.device    function () => current device key (for storage)
	 * options.userId    numeric WordPress user ID (for storage)
	 * options.onChange  function (view, mode) after every change
	 */
	function create(options) {
		var shell = options.shell;
		var world = options.world;
		var doc = shell.ownerDocument;
		var win = doc.defaultView || window;
		var storage = null;
		try { storage = win.localStorage; } catch (e) { storage = null; }
		var reduceMotion = win.matchMedia ? win.matchMedia('(prefers-reduced-motion: reduce)') : { matches: false };
		var view = { x: 0, y: 0, scale: 1 };
		var mode = 'fit';
		var pan = null;
		var spaceHeld = false;
		var pointerOver = false;
		var animateTimer = null;
		var overlay = doc.createElement('div');
		overlay.className = 'abmm-canvas-overlay';
		overlay.setAttribute('aria-hidden', 'true');
		shell.appendChild(overlay);

		function viewport() {
			return { width: shell.clientWidth, height: shell.clientHeight };
		}

		function render(animate) {
			if (animate && !reduceMotion.matches) {
				world.classList.add('is-animating');
				clearTimeout(animateTimer);
				animateTimer = setTimeout(function () { world.classList.remove('is-animating'); }, 200);
			}
			world.style.transform = 'translate(' + view.x + 'px, ' + view.y + 'px) scale(' + view.scale + ')';
			var grid = gridStyle(view);
			shell.style.backgroundSize = grid.size + 'px ' + grid.size + 'px';
			shell.style.backgroundPosition = grid.x + 'px ' + grid.y + 'px';
			if (options.onChange) options.onChange(view, mode);
		}

		function persist() {
			saveView(storage, storageKey(options.userId, options.device()), mode, view);
		}

		function set(next, nextMode, animate) {
			view = next;
			mode = nextMode || 'manual';
			render(animate);
			persist();
		}

		function fit(animate) {
			set(fitView(viewport(), options.content(), options.fitHeight()), 'fit', animate);
		}

		function center() {
			var size = viewport();
			return { x: size.width / 2, y: size.height / 2 };
		}

		function zoomTo(scale, point, animate) {
			var at = point || center();
			set(zoomAt(view, scale, at.x, at.y), 'manual', animate);
		}

		/** Keep a fitted canvas fitted, and restore the device's saved view. */
		// Re-fitting keeps the mode, so it is not saved: a refresh during a
		// device switch must not overwrite the next device's remembered view.
		function refresh() {
			if (mode === 'fit') view = fitView(viewport(), options.content(), options.fitHeight());
			render(false);
		}

		function restore() {
			var saved = loadView(storage, storageKey(options.userId, options.device()));
			if (saved && saved.mode === 'manual') {
				view = { x: saved.x, y: saved.y, scale: saved.scale };
				mode = 'manual';
				render(false);
			} else {
				mode = 'fit';
				fit(false);
			}
		}

		function localPoint(clientX, clientY) {
			var rect = shell.getBoundingClientRect();
			return { x: clientX - rect.left, y: clientY - rect.top };
		}

		function startPan(clientX, clientY) {
			pan = { x: clientX, y: clientY, view: view };
			shell.classList.add('is-panning');
		}

		function movePan(clientX, clientY) {
			if (!pan) return;
			view = panBy(pan.view, clientX - pan.x, clientY - pan.y);
			mode = 'manual';
			render(false);
		}

		function endPan() {
			if (!pan) return;
			pan = null;
			shell.classList.remove('is-panning');
			persist();
		}

		function setSpace(held) {
			spaceHeld = held;
			shell.classList.toggle('is-space-held', held);
		}

		function isEmptyCanvas(target) {
			return !(target && target.closest && target.closest('.abmm-preview, .abmm-canvas-ui'));
		}

		shell.addEventListener('pointerdown', function (event) {
			var middle = event.button === 1;
			if (!middle && event.button !== 0) return;
			if (!middle && !spaceHeld && !isEmptyCanvas(event.target)) return;
			event.preventDefault();
			if (shell.setPointerCapture && event.pointerId !== undefined) {
				try { shell.setPointerCapture(event.pointerId); } catch (e) { /* synthetic events */ }
			}
			startPan(event.clientX, event.clientY);
		});
		shell.addEventListener('pointermove', function (event) { movePan(event.clientX, event.clientY); });
		shell.addEventListener('pointerup', endPan);
		shell.addEventListener('pointercancel', endPan);
		shell.addEventListener('lostpointercapture', endPan);
		// Middle-click must not start the browser's autoscroll.
		shell.addEventListener('auxclick', function (event) { if (event.button === 1) event.preventDefault(); });
		shell.addEventListener('mouseenter', function () { pointerOver = true; });
		shell.addEventListener('mouseleave', function () { pointerOver = false; });

		function onWheel(event, clientX, clientY) {
			var delta = wheelDelta(event);
			if (event.ctrlKey || event.metaKey) {
				// Pinch and Ctrl/Cmd + wheel zoom at the pointer, never the page.
				event.preventDefault();
				var point = localPoint(clientX, clientY);
				set(zoomAt(view, wheelZoomScale(view.scale, delta.y), point.x, point.y), 'manual', false);
				return;
			}
			// Two-finger scroll pans, unless the user is typing in a field.
			if (isTypingTarget(doc.activeElement)) return;
			event.preventDefault();
			view = panBy(view, -delta.x, -delta.y);
			mode = 'manual';
			render(false);
			clearTimeout(onWheel.saveTimer);
			onWheel.saveTimer = setTimeout(persist, 250);
		}
		shell.addEventListener('wheel', function (event) { onWheel(event, event.clientX, event.clientY); }, { passive: false });

		doc.addEventListener('keydown', function (event) {
			if (isTypingTarget(event.target)) return;
			var action = shortcutAction(event);
			if (action) {
				event.preventDefault();
				if (action === 'fit') fit(true);
				else if (action === 'actual') zoomTo(1, null, true);
				else zoomTo(stepScale(view.scale, action === 'in' ? 1 : -1), null, true);
				return;
			}
			// Space pans like a design tool, but only over the canvas and never
			// when it would press a focused button or link.
			if ((event.key === ' ' || event.code === 'Space') && pointerOver && !event.repeat) {
				var active = doc.activeElement;
				if (active && active !== doc.body && active.closest && active.closest('button, a[href], [role="button"], summary')) return;
				event.preventDefault();
				setSpace(true);
			} else if ((event.key === ' ' || event.code === 'Space') && spaceHeld) {
				event.preventDefault();
			}
		});
		doc.addEventListener('keyup', function (event) {
			if (event.key === ' ' || event.code === 'Space') {
				if (spaceHeld) event.preventDefault();
				setSpace(false);
			}
		});
		win.addEventListener('blur', function () { setSpace(false); endPan(); });

		/**
		 * Forward pan and zoom gestures from a same-origin iframe inside the
		 * frame, so a device preview behaves like the rest of the canvas.
		 */
		function bindFrame(frame) {
			var frameWin = frame.contentWindow;
			var frameDoc = frameWin && frameWin.document;
			if (!frameDoc) return;
			var toParent = function (event) {
				var rect = frame.getBoundingClientRect();
				return { x: rect.left + event.clientX * view.scale, y: rect.top + event.clientY * view.scale };
			};
			frameDoc.addEventListener('wheel', function (event) {
				// Plain scrolling scrolls the device first, as on a real phone, and
				// pans the canvas once there is nothing left to scroll that way.
				if (!(event.ctrlKey || event.metaKey) && canScroll(event.target, wheelDelta(event))) return;
				var point = toParent(event);
				onWheel(event, point.x, point.y);
			}, { passive: false });
			frameDoc.addEventListener('mousedown', function (event) {
				if (event.button !== 1 && !(event.button === 0 && spaceHeld)) return;
				event.preventDefault();
				var point = toParent(event);
				startPan(point.x, point.y);
			});
			frameDoc.addEventListener('keydown', function (event) {
				if (isTypingTarget(event.target)) return;
				var action = shortcutAction(event);
				if (action) {
					event.preventDefault();
					if (action === 'fit') fit(true);
					else if (action === 'actual') zoomTo(1, null, true);
					else zoomTo(stepScale(view.scale, action === 'in' ? 1 : -1), null, true);
				}
			});
			frameDoc.addEventListener('keyup', function (event) {
				if (event.key === ' ' || event.code === 'Space') setSpace(false);
			});
		}
		// A pan that started in the iframe continues over the overlay.
		doc.addEventListener('mousemove', function (event) { if (pan) movePan(event.clientX, event.clientY); });
		doc.addEventListener('mouseup', endPan);

		return {
			fit: fit,
			zoomTo: zoomTo,
			step: function (direction) { zoomTo(stepScale(view.scale, direction), null, true); },
			refresh: refresh,
			restore: restore,
			bindFrame: bindFrame,
			view: function () { return { x: view.x, y: view.y, scale: view.scale }; },
			mode: function () { return mode; },
		};
	}

	window.ABMMPreviewCanvas = {
		MIN_SCALE: MIN_SCALE,
		MAX_SCALE: MAX_SCALE,
		PRESETS: PRESETS,
		clampScale: clampScale,
		zoomAt: zoomAt,
		panBy: panBy,
		fitView: fitView,
		stepScale: stepScale,
		wheelDelta: wheelDelta,
		wheelZoomScale: wheelZoomScale,
		shortcutAction: shortcutAction,
		isTypingTarget: isTypingTarget,
		canScroll: canScroll,
		gridStyle: gridStyle,
		storageKey: storageKey,
		loadView: loadView,
		saveView: saveView,
		create: create,
	};
})(window);
