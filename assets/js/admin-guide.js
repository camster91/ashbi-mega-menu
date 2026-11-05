/**
 * Ashbi Mega Menu: the editor's quiet guide.
 *
 * A short tour that gets a new user to a placed menu, plus small "What's this?"
 * buttons. It never opens over a dialog, only starts by itself on an empty menu,
 * and stays away once dismissed. All wording comes from PHP (window.abmmGuide),
 * so a step or hint with no text is simply left out.
 */
(function (window) {
	'use strict';

	/* Edit these tables when the editor changes. A step whose target is missing or
	 * hidden is skipped. `reveal` lists controls that can show a hidden target. */
	var TOUR_STEPS = [
		{ id: 'links', selectors: ['.abmm-add-nav-item', '#abmm-nav-items'], reveal: ['[data-abmm-workflow="content"]'] },
		{ id: 'save', selectors: ['.abmm-save-menu'] },
		{ id: 'place', selectors: ['[data-abmm-workflow="help"]'] },
		{ id: 'check', selectors: ['[data-abmm-workflow="verify"]'] },
	];

	/* Only for what the screen can't say itself. `where` is relative to the target:
	 * append, before or after. `closest` moves the target outward first, because a
	 * button inside a <label> would take its control. */
	var HINTS = [
		{ id: 'advanced', selectors: ['#abmm-advanced-settings'], where: 'before', doc: 'style-your-menu.md#advanced-settings' },
	];

	/* The editor's own panel, which is a dialog on narrow screens. The tour walks
	 * through it, so it never counts as something to stay away from. */
	var OWN_SHEET = '#abmm-builder-sidebar';

	/* The guide stays away while any of these shows. */
	var BLOCKERS = [
		'#abmm-builder-load-error',
		'#abmm-draft-recovery',
		'.abmm-exit-dialog',
		'#abmm-icon-modal',
		'.media-modal',
		'[role="dialog"][aria-modal="true"]',
	];

	var STORAGE_KEY = 'abmmGuideTour';
	var DOCK_WIDTH = 600;
	var GAP = 10;
	var EDGE = 12;
	var SLIDE_MS = 200;

	function readState(win) {
		try {
			var saved = JSON.parse(win.localStorage.getItem(STORAGE_KEY) || '{}');
			return saved && 'object' === typeof saved ? saved : {};
		} catch (error) {
			return {};
		}
	}

	function writeState(win, state) {
		try {
			win.localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
		} catch (error) { /* Private window: the choice lasts for this visit. */ }
	}

	function storageWorks(win) {
		try {
			win.localStorage.setItem(STORAGE_KEY + 'Test', '1');
			win.localStorage.removeItem(STORAGE_KEY + 'Test');
			return true;
		} catch (error) {
			return false;
		}
	}

	function isVisible(win, element) {
		if (!element || !element.isConnected || element.closest('[hidden]')) return false;
		var style = win.getComputedStyle(element);
		// The panel slides in, and reads as hidden until the slide has started.
		var sliding = element.closest(OWN_SHEET + '.is-open');
		if ('none' === style.display || ('hidden' === style.visibility && !sliding)) return false;
		var rect = element.getBoundingClientRect();
		return rect.width > 0 && rect.height > 0;
	}

	function isBlocked(win) {
		return BLOCKERS.some(function (selector) {
			return Array.prototype.some.call(win.document.querySelectorAll(selector), function (element) {
				return !element.matches(OWN_SHEET) && isVisible(win, element);
			});
		});
	}

	/* A sample counts as empty: its links still have to be replaced. When the menu
	 * can't be read the answer is "has content", so the guide stays quiet. */
	function hasMenuContent(doc) {
		var node = doc.getElementById('abmm-menu-data');
		if (!node) return true;
		try {
			var menu = JSON.parse(node.textContent);
			if (menu && menu.starter_source) return false;
			return !!(menu && menu.items && menu.items.length);
		} catch (error) {
			return true;
		}
	}

	/* The page picker's redirect marks a menu made a moment ago. It has links in it,
	 * but its owner is just as new, and later visits to the menu don't carry the mark. */
	function justCreated(win) {
		return /[?&]abmm_new=1(&|$)/.test(win.location.search);
	}

	/* Without storage a dismissal can't be remembered, so the tour would nag. */
	function shouldAutoStart(win) {
		var doc = win.document;
		if (!doc.getElementById('abmm-builder') || !storageWorks(win)) return false;
		var status = readState(win).status;
		if ('dismissed' === status || 'done' === status) return false;
		return (justCreated(win) || !hasMenuContent(doc)) && !isBlocked(win);
	}

	/** Below the target, else above, else wherever fits. Narrow screens dock in CSS. */
	function placePopup(rect, size, viewport) {
		if (viewport.width <= DOCK_WIDTH) return { docked: true };
		var below = rect.bottom + GAP;
		var above = rect.top - GAP - size.height;
		var top = below;
		if (below + size.height > viewport.height - EDGE) {
			top = above >= EDGE ? above : Math.max(EDGE, viewport.height - size.height - EDGE);
		}
		var left = Math.max(EDGE, Math.min(rect.left, viewport.width - size.width - EDGE));
		return { docked: false, top: Math.round(top), left: Math.round(left) };
	}

	function fill(template, values) {
		return String(template || '').replace(/%(?:(\d+)\$)?[ds]/g, function (match, position) {
			return String(values[position ? Number(position) - 1 : 0]);
		});
	}

	function create(options) {
		var settings = options || {};
		var win = settings.window || window;
		var doc = win.document;
		var data = settings.data || win.abmmGuide || {};
		var labels = data.labels || {};
		var stepText = data.steps || {};
		var hintText = data.hints || {};
		var steps = TOUR_STEPS.filter(function (step) { return stepText[step.id]; });
		var tour = null;
		var hint = null;
		var frame = 0;

		function firstVisible(selectors) {
			for (var i = 0; i < selectors.length; i++) {
				var element = doc.querySelector(selectors[i]);
				if (isVisible(win, element)) return element;
			}
			return null;
		}

		function revealFor(step) {
			return firstVisible(step.reveal || []);
		}

		function isAvailable(step) {
			return !!(firstVisible(step.selectors) || revealFor(step));
		}

		function targetFor(step) {
			var target = firstVisible(step.selectors);
			var tried = [];
			var reveal = target ? null : revealFor(step);
			while (reveal && !target && -1 === tried.indexOf(reveal)) {
				tried.push(reveal);
				reveal.click();
				target = firstVisible(step.selectors);
				reveal = target ? null : (step.reveal || []).map(function (selector) { return doc.querySelector(selector); }).filter(function (element) {
					return isVisible(win, element) && -1 === tried.indexOf(element);
				})[0];
			}
			return target;
		}

		function element(tag, className, text) {
			var node = doc.createElement(tag);
			if (className) node.className = className;
			if (text) node.textContent = text;
			return node;
		}

		function button(className, text) {
			var node = element('button', className, text);
			node.type = 'button';
			return node;
		}

		function dialog(extraClass, titleId, bodyId, title, body) {
			var panel = element('div', 'abmm-guide ' + extraClass);
			panel.setAttribute('role', 'dialog');
			panel.setAttribute('aria-modal', 'false');
			panel.setAttribute('aria-labelledby', titleId);
			panel.setAttribute('aria-describedby', bodyId);
			var heading = element('h2', 'abmm-guide__title', title);
			heading.id = titleId;
			heading.tabIndex = -1;
			var copy = element('p', 'abmm-guide__body', body);
			copy.id = bodyId;
			panel.appendChild(heading);
			panel.appendChild(copy);
			return panel;
		}

		function focusTitle(panel) {
			panel.querySelector('.abmm-guide__title').focus({ preventScroll: true });
		}

		function position(panel, anchor) {
			var viewport = { width: win.innerWidth, height: win.innerHeight };
			var usable = anchor && isVisible(win, anchor);
			var place = usable
				? placePopup(anchor.getBoundingClientRect(), { width: panel.offsetWidth, height: panel.offsetHeight }, viewport)
				: { docked: true };
			panel.classList.toggle('is-docked', place.docked);
			panel.style.top = place.docked ? '' : place.top + 'px';
			panel.style.left = place.docked ? '' : place.left + 'px';
		}

		function reposition() {
			frame = 0;
			if (tour) position(tour.panel, tour.target);
			if (hint) {
				if (isVisible(win, hint.opener)) position(hint.panel, hint.opener);
				else closeHint(false);
			}
		}

		function schedule() {
			if (frame || (!tour && !hint)) return;
			frame = (win.requestAnimationFrame || win.setTimeout)(reposition);
		}

		function restoreFocus(opener) {
			var fallback = doc.querySelector('[data-abmm-guide-start]');
			if (isVisible(win, opener)) opener.focus();
			else if (isVisible(win, fallback)) fallback.focus();
		}

		function save(status, stepId) {
			writeState(win, { status: status, step: stepId || '' });
		}

		function closeTour(status, returnFocus) {
			if (!tour) return;
			var opener = tour.opener;
			if (tour.target) tour.target.classList.remove('abmm-guide-target');
			tour.panel.remove();
			tour = null;
			// No status means the guide stepped aside: nobody chose to dismiss it.
			if (status) save(status);
			if (returnFocus) restoreFocus(opener);
		}

		/* The open panel covers the page and makes it inert, so a step outside it
		 * can only be shown once the panel has closed. */
		function closeSheetAround(target) {
			var sheet = doc.querySelector(OWN_SHEET + '.is-open');
			if (!sheet || sheet.contains(target)) return;
			var close = sheet.querySelector('.abmm-builder-panel-close');
			if (close) close.click();
		}

		function showStep(index, direction) {
			var i = index;
			var target = null;
			while (i >= 0 && i < steps.length) {
				target = targetFor(steps[i]);
				if (target) break;
				i += direction;
			}
			if (!target) return false;
			closeSheetAround(target);
			if (tour.target) tour.target.classList.remove('abmm-guide-target');
			target.classList.add('abmm-guide-target');
			// When docked along the bottom, centre the target so the panel doesn't cover it.
			if (target.scrollIntoView) target.scrollIntoView({ block: win.innerWidth <= DOCK_WIDTH ? 'center' : 'nearest', inline: 'nearest' });
			tour.index = i;
			tour.target = target;

			var available = steps.filter(isAvailable);
			var place = Math.max(0, available.indexOf(steps[i]));
			var text = stepText[steps[i].id];
			var isLast = place === available.length - 1;
			tour.panel.querySelector('.abmm-guide__count').textContent = fill(labels.stepCount, [place + 1, available.length]);
			tour.panel.querySelector('.abmm-guide__title').textContent = text.title;
			tour.panel.querySelector('.abmm-guide__body').textContent = text.body;
			tour.panel.querySelector('.abmm-guide__back').hidden = 0 === place;
			tour.panel.querySelector('.abmm-guide__next').textContent = isLast ? labels.done : labels.next;
			save('active', steps[i].id);
			position(tour.panel, target);
			focusTitle(tour.panel);
			// Opening or closing the panel moves focus on the next tick. Take it back.
			var panel = tour.panel;
			win.setTimeout(function () {
				if (tour && tour.panel === panel) focusTitle(panel);
			}, 0);
			// And the target has only reached its final place once the slide ends.
			win.setTimeout(schedule, SLIDE_MS);
			return true;
		}

		function next() {
			if (!tour) return;
			if (!showStep(tour.index + 1, 1)) closeTour('done', true);
		}

		function back() {
			if (tour) showStep(tour.index - 1, -1);
		}

		/** Begin the tour, or carry on from a saved step when `resume` is set. */
		function start(resume) {
			if (tour || !steps.length || !doc.getElementById('abmm-builder') || isBlocked(win)) return false;
			closeHint(false);
			var active = doc.activeElement;
			var panel = dialog('abmm-guide--tour', 'abmm-guide-title', 'abmm-guide-body', '', '');
			panel.insertBefore(element('p', 'abmm-guide__count'), panel.firstChild);
			var actions = element('div', 'abmm-guide__actions');
			var skip = button('button-link abmm-guide__skip', labels.skip);
			var previous = button('button abmm-guide__back', labels.back);
			var forward = button('button button-primary abmm-guide__next', labels.next);
			skip.addEventListener('click', function () { closeTour('dismissed', true); });
			previous.addEventListener('click', back);
			forward.addEventListener('click', next);
			actions.appendChild(skip);
			actions.appendChild(previous);
			actions.appendChild(forward);
			panel.appendChild(actions);
			doc.body.appendChild(panel);
			tour = { panel: panel, index: 0, target: null, opener: active && active !== doc.body ? active : null };

			var first = 0;
			if (resume) {
				var saved = readState(win).step;
				steps.forEach(function (step, i) { if (step.id === saved) first = i; });
			}
			if (showStep(first, 1)) return true;
			panel.remove();
			tour = null;
			return false;
		}

		function maybeAutoStart() {
			return shouldAutoStart(win) ? start(true) : false;
		}

		function hintAnchor(item) {
			for (var i = 0; i < item.selectors.length; i++) {
				var found = doc.querySelector(item.selectors[i]);
				if (found) return item.closest ? found.closest(item.closest) : found;
			}
			return null;
		}

		function addHints() {
			HINTS.forEach(function (item) {
				var text = hintText[item.id];
				if (!text || doc.querySelector('[data-abmm-guide-hint="' + item.id + '"]')) return;
				var anchor = hintAnchor(item);
				if (!anchor) return;
				var label = fill(labels.whatsThis, [text.title]);
				var trigger = button('abmm-guide-hint');
				trigger.setAttribute('data-abmm-guide-hint', item.id);
				trigger.setAttribute('aria-label', label);
				trigger.setAttribute('aria-haspopup', 'dialog');
				trigger.setAttribute('aria-expanded', 'false');
				trigger.title = label;
				var mark = element('span', '', '?');
				mark.setAttribute('aria-hidden', 'true');
				trigger.appendChild(mark);
				if ('before' === item.where) trigger.classList.add('abmm-guide-hint--corner');
				if ('append' === item.where) anchor.appendChild(trigger);
				else anchor.parentNode.insertBefore(trigger, 'before' === item.where ? anchor : anchor.nextSibling);
			});
		}

		function closeHint(returnFocus) {
			if (!hint) return;
			var opener = hint.opener;
			opener.setAttribute('aria-expanded', 'false');
			opener.removeAttribute('aria-controls');
			hint.panel.remove();
			hint = null;
			if (returnFocus) restoreFocus(opener);
		}

		function openHint(trigger) {
			closeTour('dismissed', false);
			var id = trigger.getAttribute('data-abmm-guide-hint');
			var item = HINTS.filter(function (candidate) { return candidate.id === id; })[0];
			var text = hintText[id];
			var panel = dialog('abmm-guide--hint', 'abmm-guide-hint-title', 'abmm-guide-hint-body', text.title, text.body);
			panel.id = 'abmm-guide-hint-panel';
			var actions = element('div', 'abmm-guide__actions');
			if (data.docsUrl && item && item.doc) {
				var link = element('a', 'abmm-guide__more', labels.readMore);
				link.href = data.docsUrl + item.doc;
				link.target = '_blank';
				link.rel = 'noopener noreferrer';
				link.appendChild(element('span', 'screen-reader-text', ' ' + (labels.newTab || '')));
				actions.appendChild(link);
			}
			var close = button('button abmm-guide__close', labels.close);
			close.addEventListener('click', function () { closeHint(true); });
			actions.appendChild(close);
			panel.appendChild(actions);
			doc.body.appendChild(panel);
			trigger.setAttribute('aria-expanded', 'true');
			trigger.setAttribute('aria-controls', panel.id);
			hint = { panel: panel, opener: trigger };
			position(panel, trigger);
			focusTitle(panel);
		}

		function onClick(event) {
			var origin = event.target;
			if (!origin || !origin.closest) return;
			var trigger = origin.closest('[data-abmm-guide-hint]');
			if (trigger) {
				var isSame = hint && hint.opener === trigger;
				closeHint(isSame);
				if (!isSame) openHint(trigger);
				return;
			}
			if (origin.closest('[data-abmm-guide-start]')) start(false);
		}

		function onPointerDown(event) {
			if (!hint || !event.target || !event.target.closest) return;
			if (!hint.panel.contains(event.target) && !event.target.closest('[data-abmm-guide-hint]')) closeHint(false);
		}

		function onKeyDown(event) {
			var open = hint || tour;
			if (!open) return;
			if ('Escape' === event.key) {
				event.preventDefault();
				event.stopPropagation();
				if (hint) closeHint(true);
				else closeTour('dismissed', true);
				return;
			}
			if ('Tab' !== event.key || !open.panel.contains(event.target)) return;
			// The open panel keeps Tab inside itself. While in the guide, the guide decides.
			event.stopPropagation();
			var stops = Array.prototype.filter.call(open.panel.querySelectorAll('a[href], button'), function (node) {
				return !node.hidden;
			});
			if (!stops.length) return;
			var first = stops[0];
			var last = stops[stops.length - 1];
			var onTitle = event.target.classList.contains('abmm-guide__title');
			if (event.shiftKey && (event.target === first || onTitle)) {
				event.preventDefault();
				last.focus();
			} else if (!event.shiftKey && event.target === last) {
				event.preventDefault();
				first.focus();
			}
		}

		/* If a dialog opens during the tour or a hint, the guide steps aside. That is
		 * not a dismissal, so it can carry on from the same step next time. */
		function onFocusIn() {
			if ((tour || hint) && isBlocked(win)) {
				closeTour(null, false);
				closeHint(false);
			}
		}

		function destroy() {
			closeHint(false);
			if (tour) {
				if (tour.target) tour.target.classList.remove('abmm-guide-target');
				tour.panel.remove();
				tour = null;
			}
			doc.removeEventListener('click', onClick);
			doc.removeEventListener('pointerdown', onPointerDown);
			doc.removeEventListener('keydown', onKeyDown, true);
			doc.removeEventListener('focusin', onFocusIn, true);
			win.removeEventListener('scroll', schedule, true);
			win.removeEventListener('resize', schedule);
		}

		doc.addEventListener('click', onClick);
		doc.addEventListener('pointerdown', onPointerDown);
		doc.addEventListener('keydown', onKeyDown, true);
		doc.addEventListener('focusin', onFocusIn, true);
		win.addEventListener('scroll', schedule, true);
		win.addEventListener('resize', schedule);
		addHints();
		if (steps.length && doc.getElementById('abmm-builder')) {
			Array.prototype.forEach.call(doc.querySelectorAll('[data-abmm-guide-start]'), function (node) {
				node.hidden = false;
			});
		}

		return {
			start: start,
			maybeAutoStart: maybeAutoStart,
			next: next,
			back: back,
			addHints: addHints,
			isOpen: function () { return !!tour; },
			destroy: destroy,
		};
	}

	window.ABMMAdminGuide = {
		TOUR_STEPS: TOUR_STEPS,
		HINTS: HINTS,
		STORAGE_KEY: STORAGE_KEY,
		hasMenuContent: hasMenuContent,
		isBlocked: isBlocked,
		placePopup: placePopup,
		shouldAutoStart: shouldAutoStart,
		create: create,
	};

	if (window.abmmGuide) {
		var guide = create({ window: window });
		// Wait for the page to finish, so the editor has decided about any dialog.
		// A fast page can reach load before jQuery's ready, and the tour clicks controls
		// the editor wires up there, so start after the editor's own ready callback.
		var startWhenReady = function () {
			if (window.jQuery) window.jQuery(guide.maybeAutoStart);
			else guide.maybeAutoStart();
		};
		if ('complete' === window.document.readyState) startWhenReady();
		else window.addEventListener('load', startWhenReady);
	}
})(window);
