/**
 * Ashbi Mega Menu — Classic (Easy Mega Menu compatible) interactions.
 * Ported from Easy Mega Menu 1.6.76; shares its per-header guard so the two
 * scripts never bind the same header twice.
 *
 * Easy Mega Menu — Frontend interactions
 * Desktop: hover mega + category tabs
 * Mobile: drill-down screens (root → categories → links)
 */
(function () {
	'use strict';

	var MOBILE_MQ = '(max-width: 900px)';
	var OVERFLOW_HYSTERESIS = 16;
	var STACK_HOST_CLASS = 'emm-stack-host--active';
	var OBSCURED_HEADER_CLASS = 'emm-obscured-by-open-menu';
	var EXTERNAL_DRAWER_CLASS = 'emm-external-drawer-open';
	var EXTERNAL_MEGA_CLASS = 'emm-external-mega-open';

	function classicEdgeBleed(menuId) {
		var config = window.abmmClassic && window.abmmClassic.edgeBleed;
		var value = config && Object.prototype.hasOwnProperty.call(config, menuId) ? parseInt(config[menuId], 10) : 0;
		return isFinite(value) ? Math.max(0, Math.min(64, value)) : 0;
	}

	function closest(el, sel) {
		while (el && el.nodeType === 1) {
			if (el.matches(sel)) return el;
			el = el.parentElement;
		}
		return null;
	}

	function isMobile(subject) {
		var header = subject && subject.classList && subject.classList.contains('emm-header')
			? subject
			: closest(subject, '.emm-header');
		return window.matchMedia(MOBILE_MQ).matches ||
			!!(header && header.classList.contains('emm-force-mobile'));
	}

	function syncActiveStackHost() {
		document.querySelectorAll('.' + STACK_HOST_CLASS).forEach(function (host) {
			host.classList.remove(STACK_HOST_CLASS);
		});

		var activeHeader = document.querySelector(
			'.emm-header.emm-has-open-menu, .emm-header.is-mobile-open'
		);
		if (!activeHeader) return;

		/* Elementor can wrap a template in several nested containers, each of
		 * which may establish its own stacking context. Lift the complete active
		 * branch through the outer Elementor document so sibling product/shared
		 * header branches cannot paint through the open panel. */
		var host = activeHeader.parentElement;
		while (host && host !== document.body && host !== document.documentElement) {
			if (
				host.matches &&
				host.matches('.elementor, .elementor-element, .elementor-template, .elementor-widget-container')
			) {
				host.classList.add(STACK_HOST_CLASS);
			}
			host = host.parentElement;
		}
	}

	function clearObscuredHeaders() {
		document.querySelectorAll('.emm-header.' + OBSCURED_HEADER_CLASS).forEach(function (header) {
			header.classList.remove(OBSCURED_HEADER_CLASS);
		});
	}

	function syncObscuredHeaders(activeHeader) {
		clearObscuredHeaders();
		if (!activeHeader || isMobile(activeHeader)) return;

		var openPanel = activeHeader.querySelector(
			'.emm-nav__item--mega.is-open [data-emm-mega-panel]:not([hidden])'
		);
		if (!openPanel) return;

		var panelRect = openPanel.getBoundingClientRect();
		if (panelRect.width <= 0 || panelRect.height <= 0) return;

		document.querySelectorAll('.emm-header').forEach(function (header) {
			if (header === activeHeader) return;
			var headerRect = header.getBoundingClientRect();
			var intersects =
				panelRect.left < headerRect.right &&
				panelRect.right > headerRect.left &&
				panelRect.top < headerRect.bottom &&
				panelRect.bottom > headerRect.top;
			if (intersects) header.classList.add(OBSCURED_HEADER_CLASS);
		});
	}

	function syncOpenHeaderLayers() {
		syncActiveStackHost();
		var activeHeader = document.querySelector('.emm-header.emm-has-open-menu');
		if (activeHeader) {
			syncObscuredHeaders(activeHeader);
		} else {
			clearObscuredHeaders();
		}
	}

	function visibleFocusable(container) {
		return Array.prototype.filter.call(
			container.querySelectorAll(
				'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])'
			),
			function (element) {
				return !element.hidden && element.getClientRects().length > 0;
			}
		);
	}

	function focusFirst(container, fallback) {
		var focusable = container ? visibleFocusable(container) : [];
		var target = focusable[0] || fallback;
		if (target && typeof target.focus === 'function') target.focus();
	}

	function closeAllMegas(root) {
		root.querySelectorAll('.emm-nav__item--mega.is-open').forEach(function (item) {
			item.__easyMegaMenuClickOpen = false;
			item.classList.remove('is-open');
			item.removeAttribute('data-drill');
			var btn = item.querySelector('[data-emm-mega-trigger]');
			var panel = item.querySelector('[data-emm-mega-panel]');
			if (btn) btn.setAttribute('aria-expanded', 'false');
			if (panel) {
				panel.hidden = true;
				positionDesktopPanel(panel, null);
				resetDrillPanels(panel);
				syncDesktopAccordionHeight(panel);
			}
		});
		root.classList.remove('emm-is-drilling');
		root.classList.remove('emm-has-open-menu');
		root.removeAttribute('data-drill-level');
		syncOpenHeaderLayers();
	}

	function resetDrillPanels(mega) {
		mega.querySelectorAll('.emm-mega__section').forEach(function (section) {
			setSectionOpen(section, false);
		});
		var bar = mega.querySelector('[data-emm-mobile-bar]');
		if (bar) bar.hidden = true;
	}

	function setSectionOpen(section, open) {
		var btn = section.querySelector('.emm-mega__cat');
		var panel = section.querySelector('.emm-mega__panel');
		section.classList.toggle('is-open', open);
		if (btn) {
			btn.classList.toggle('is-active', open);
			btn.setAttribute('aria-expanded', open ? 'true' : 'false');
		}
		if (panel) {
			panel.classList.toggle('is-active', open);
			panel.hidden = !open;
		}
	}

	function activateCategoryDesktop(catBtn) {
		var mega = closest(catBtn, '.emm-mega');
		if (!mega) return;
		var id = catBtn.getAttribute('data-emm-cat');

		mega.querySelectorAll('.emm-mega__section').forEach(function (section) {
			var on = section.getAttribute('data-emm-section') === id;
			setSectionOpen(section, on);
		});
		syncDesktopAccordionHeight(mega);
	}

	function ensureMobileBar(mega) {
		var bar = mega.querySelector('[data-emm-mobile-bar]');
		if (bar) return bar;

		bar = document.createElement('div');
		bar.className = 'emm-mobile-bar';
		bar.setAttribute('data-emm-mobile-bar', '');
		bar.innerHTML =
			'<button type="button" class="emm-mobile-back" data-emm-back aria-label="Back">' +
			'<svg width="18" height="18" viewBox="0 0 12 12" aria-hidden="true"><path d="M7.5 2.5L4 6l3.5 3.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>' +
			'<span>Back</span></button>' +
			'<span class="emm-mobile-title" data-emm-mobile-title></span>' +
			'<button type="button" class="emm-nav__drawer-close emm-mobile-close" data-emm-drawer-close aria-label="Close menu">' +
			'<span aria-hidden="true">&times;</span></button>';
		mega.insertBefore(bar, mega.firstChild);
		return bar;
	}

	function setMobileBar(mega, title, visible) {
		var bar = ensureMobileBar(mega);
		bar.hidden = !visible;
		var titleEl = bar.querySelector('[data-emm-mobile-title]');
		if (titleEl) titleEl.textContent = title || '';
	}

	function preferredPanelContainerRect(header, panelWidth) {
		var headerRect = header.getBoundingClientRect();
		var preferred = '.e-con-inner, .elementor-container, .site-header__inner, .header-inner, .container';
		var fallback = null;
		var fullWidthFallback = null;
		var node = header.parentElement;

		while (node && node !== document.documentElement) {
			var rect = node.getBoundingClientRect();
			var styles = window.getComputedStyle ? window.getComputedStyle(node) : null;
			var paddingLeft = styles ? Number.parseFloat(styles.paddingLeft) || 0 : 0;
			var paddingRight = styles ? Number.parseFloat(styles.paddingRight) || 0 : 0;
			var contentRect = {
				left: rect.left + paddingLeft,
				width: Math.max(0, rect.width - paddingLeft - paddingRight)
			};
			var isUsable = contentRect.width > headerRect.width + 64 && contentRect.width <= window.innerWidth - 16;
			if (isUsable && node.matches && node.matches(preferred)) return contentRect;
			if (
				isUsable &&
				!fullWidthFallback &&
				rect.left <= 16 &&
				rect.right >= window.innerWidth - 16
			) {
				fullWidthFallback = contentRect;
			}
			if (isUsable && contentRect.width <= panelWidth + 32 && (!fallback || contentRect.width > fallback.width)) {
				fallback = contentRect;
			}
			node = node.parentElement;
		}

		return fullWidthFallback || fallback || {
			left: 16,
			width: Math.max(0, window.innerWidth - 32)
		};
	}

	function syncDesktopAccordionHeight(mega) {
		if (!mega) return;
		var accordion = mega.querySelector('.emm-mega__accordion');
		var header = closest(mega, '.emm-header');
		if (!accordion) return;
		if (!header || isMobile(header) || header.classList.contains('emm-layout--stacked') || mega.hidden) {
			accordion.style.removeProperty('--emm-active-panel-height');
			return;
		}
		var panel = mega.querySelector('.emm-mega__panel.is-active:not([hidden])');
		if (!panel) {
			accordion.style.removeProperty('--emm-active-panel-height');
			return;
		}
		var height = Math.ceil(panel.getBoundingClientRect().height);
		if (height > 0) accordion.style.setProperty('--emm-active-panel-height', height + 'px');
	}

	function positionDesktopPanel(panel, header) {
		if (!panel) return;
		if (!header || isMobile(header)) {
			panel.style.left = '';
			panel.style.right = '';
			panel.style.width = '';
			panel.style.maxWidth = '';
			panel.style.transform = '';
			return;
		}
		var nav = header.querySelector('.emm-nav') || header;
		var navRect = nav.getBoundingClientRect();

		if (!header.classList.contains('emm-full-width')) {
			panel.style.left = '';
			panel.style.right = '';
			panel.style.width = '';
			panel.style.maxWidth = '';
			panel.style.transform = '';
			var naturalWidth = Math.round(panel.getBoundingClientRect().width);
			var container = preferredPanelContainerRect(header, naturalWidth);
			// Optional per-menu bleed past the host container, so page lettering
			// cannot peek through at the panel edges (set per menu in Ashbi).
			var edgeBleed = classicEdgeBleed(header.getAttribute('data-emm-id'));
			var containerLeft = container.left - edgeBleed;
			var containerWidth = container.width + edgeBleed * 2;
			var width = Math.max(0, Math.min(naturalWidth, containerWidth, window.innerWidth - 32));
			var item = closest(panel, '.emm-nav__item--mega');
			var trigger = item ? item.querySelector('[data-emm-mega-trigger]') : null;
			var triggerRect = trigger ? trigger.getBoundingClientRect() : null;
			var globalRight = Math.min(window.innerWidth - 16, containerLeft + containerWidth);
			var globalLeftBound = Math.max(16, containerLeft);
			var globalLeft = Math.max(globalLeftBound, Math.min(triggerRect ? triggerRect.left : globalRight - width, globalRight - width));
			var offsetRect = item && window.getComputedStyle(item).position !== 'static' ? item.getBoundingClientRect() : navRect;
			panel.style.left = Math.round(globalLeft - offsetRect.left) + 'px';
			panel.style.right = 'auto';
			panel.style.width = Math.round(width) + 'px';
			panel.style.maxWidth = 'none';
			panel.style.transform = 'none';
			return;
		}

		panel.style.left = -Math.round(navRect.left) + 'px';
		panel.style.right = 'auto';
		panel.style.width = Math.round(window.innerWidth) + 'px';
		panel.style.maxWidth = 'none';
		panel.style.transform = 'none';
	}

	function repositionOpenPanels(header) {
		if (!header) return;
		header.querySelectorAll('[data-emm-mega-panel]').forEach(function (panel) {
			var open = closest(panel, '.emm-nav__item--mega.is-open');
			if (!open || isMobile(header)) {
				positionDesktopPanel(panel, null);
				return;
			}
			positionDesktopPanel(panel, header);
			syncDesktopAccordionHeight(panel);
		});
		if (header.classList.contains('emm-has-open-menu')) syncObscuredHeaders(header);
	}

	function openMegaDesktop(item) {
		var header = closest(item, '.emm-header');
		document.querySelectorAll('.emm-header').forEach(function (menuHeader) {
			closeAllMegas(menuHeader);
		});
		item.classList.add('is-open');
		if (header) header.classList.add('emm-has-open-menu');
		syncOpenHeaderLayers();
		var btn = item.querySelector('[data-emm-mega-trigger]');
		var panel = item.querySelector('[data-emm-mega-panel]');
		if (btn) btn.setAttribute('aria-expanded', 'true');
		if (panel) {
			panel.hidden = false;
			positionDesktopPanel(panel, header);
			var sections = panel.querySelectorAll('.emm-mega__section');
			if (sections.length) {
				var active = panel.querySelector('.emm-mega__section.is-open') || sections[0];
				sections.forEach(function (s) {
					setSectionOpen(s, s === active);
				});
			}
			syncDesktopAccordionHeight(panel);
			syncOpenHeaderLayers();
		}
	}

	/** Mobile: open mega at categories screen (or links for features layout) */
	function openMegaMobile(item) {
		var header = closest(item, '.emm-header');
		if (!header) return;

		closeAllMegas(header);
		item.classList.add('is-open');
		header.classList.add('emm-has-open-menu');
		syncActiveStackHost();

		var btn = item.querySelector('[data-emm-mega-trigger]');
		var panel = item.querySelector('[data-emm-mega-panel]');
		var label = (btn && btn.textContent ? btn.textContent : '').replace(/\s+/g, ' ').trim();

		if (btn) btn.setAttribute('aria-expanded', 'true');
		if (!panel) return;

		panel.hidden = false;
		syncDesktopAccordionHeight(panel);
		header.classList.add('emm-is-drilling');

		var isFeatures = panel.getAttribute('data-emm-style') === 'features';
		var isColumnsOnly = panel.classList.contains('emm-mega--columns-only');
		var opensDirectlyToLinks = isFeatures || isColumnsOnly;
		if (opensDirectlyToLinks) {
			if (isColumnsOnly) {
				var directSections = panel.querySelectorAll('.emm-mega__section');
				directSections.forEach(function (section, index) {
					setSectionOpen(section, index === 0);
				});
			}
			item.setAttribute('data-drill', 'links');
			header.setAttribute('data-drill-level', '2');
			setMobileBar(panel, label, true);
		} else {
			item.setAttribute('data-drill', 'cats');
			header.setAttribute('data-drill-level', '1');
			setMobileBar(panel, label, true);
			panel.querySelectorAll('.emm-mega__section').forEach(function (s) {
				setSectionOpen(s, false);
			});
		}

		header.querySelector('.emm-nav__drawer').scrollTop = 0;
		window.requestAnimationFrame(function () {
			if (isFeatures) {
				focusFirst(panel, panel.querySelector('[data-emm-back]'));
				return;
			}
			if (isColumnsOnly) {
				var directPanel = panel.querySelector('.emm-mega__section.is-open .emm-mega__panel');
				focusFirst(directPanel, panel.querySelector('[data-emm-back]'));
				return;
			}
			focusFirst(panel.querySelector('.emm-mega__accordion'), panel.querySelector('[data-emm-back]'));
		});
	}

	function drillIntoCategory(item, catBtn) {
		var header = closest(item, '.emm-header');
		var mega = closest(catBtn, '.emm-mega');
		var section = closest(catBtn, '.emm-mega__section');
		if (!header || !mega || !section) return;

		mega.querySelectorAll('.emm-mega__section').forEach(function (s) {
			setSectionOpen(s, s === section);
		});

		item.setAttribute('data-drill', 'links');
		item._emmLastCategoryTrigger = catBtn;
		header.setAttribute('data-drill-level', '2');

		var title =
			(catBtn.querySelector('.emm-mega__cat-title') || {}).textContent ||
			catBtn.getAttribute('data-emm-cat') ||
			'';
		setMobileBar(mega, title.trim(), true);
		header.querySelector('.emm-nav__drawer').scrollTop = 0;
		window.requestAnimationFrame(function () {
			var activePanel = section.querySelector('.emm-mega__panel');
			focusFirst(activePanel, mega.querySelector('[data-emm-back]'));
		});
	}

	function drillBack(item) {
		var header = closest(item, '.emm-header');
		var panel = item.querySelector('[data-emm-mega-panel]');
		if (!header || !panel) return;

		var drill = item.getAttribute('data-drill');
		var isFeatures = panel.getAttribute('data-emm-style') === 'features';
		var isColumnsOnly = panel.classList.contains('emm-mega--columns-only');
		var opensDirectlyToLinks = isFeatures || isColumnsOnly;
		var trigger = item.querySelector('[data-emm-mega-trigger]');
		var rootLabel = (trigger && trigger.textContent ? trigger.textContent : '').replace(/\s+/g, ' ').trim();

		if (drill === 'links' && !opensDirectlyToLinks) {
			item.setAttribute('data-drill', 'cats');
			header.setAttribute('data-drill-level', '1');
			panel.querySelectorAll('.emm-mega__section').forEach(function (s) {
				setSectionOpen(s, false);
			});
			setMobileBar(panel, rootLabel, true);
			header.querySelector('.emm-nav__drawer').scrollTop = 0;
			window.requestAnimationFrame(function () {
				var categoryTrigger = item._emmLastCategoryTrigger;
				if (categoryTrigger && typeof categoryTrigger.focus === 'function') {
					categoryTrigger.focus();
				} else {
					focusFirst(panel.querySelector('.emm-mega__accordion'), panel.querySelector('[data-emm-back]'));
				}
			});
			return;
		}

		// Back to root menu
		closeAllMegas(header);
		header.querySelector('.emm-nav__drawer').scrollTop = 0;
		if (trigger && typeof trigger.focus === 'function') trigger.focus();
	}

	function closeExternalProductDrawers() {
		document.querySelectorAll('.elementskit-menu-container.active').forEach(function (container) {
			var closeButton = container.querySelector('.elementskit-menu-close');
			if (closeButton && typeof closeButton.click === 'function') closeButton.click();
			// Keep a deterministic fallback for Elementor/ElementsKit releases whose
			// close control is absent or whose listener has not initialized yet.
			container.classList.remove('active');
		});
	}

	function setDrawerOpen(header, open, restoreFocus) {
		var toggle = header.querySelector('.emm-nav__toggle');
		var backdrop = header.querySelector('[data-emm-backdrop]');
		var drawer = header.querySelector('.emm-nav__drawer');
		if (open && isMobile(header)) {
			closeExternalProductDrawers();
			document.querySelectorAll('.emm-header.is-mobile-open').forEach(function (otherHeader) {
				if (otherHeader !== header) setDrawerOpen(otherHeader, false, false);
			});
		}
		header.classList.toggle('is-mobile-open', open);
		/* The header can measure zero wide on a phone, its button overflowing a
		 * shrunken layout column. Site scripts that reset open headers with no
		 * width as hidden would close each section as it opens. */
		if (open && header.getBoundingClientRect().width === 0) {
			header.style.minWidth = '1px';
		} else if (!open) {
			header.style.removeProperty('min-width');
		}
		syncActiveStackHost();
		if (toggle) {
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
		}
		if (backdrop) backdrop.hidden = !open;
		document.documentElement.classList.toggle(
			'emm-drawer-open',
			open || !!document.querySelector('.emm-header.is-mobile-open')
		);
		if (!open) {
			closeAllMegas(header);
			if (restoreFocus !== false && toggle && typeof toggle.focus === 'function') toggle.focus();
		} else {
			updateMobileGeometry(header);
			window.requestAnimationFrame(function () {
				focusFirst(drawer, toggle);
			});
		}
	}

	function updateMobileGeometry(header) {
		var adminBar = document.getElementById('wpadminbar');
		var adminOffset = 0;
		if (adminBar && window.getComputedStyle(adminBar).display !== 'none') {
			adminOffset = Math.max(0, Math.round(adminBar.getBoundingClientRect().height));
		}
		header.style.setProperty('--emm-admin-offset', adminOffset + 'px');

		if (!isMobile(header)) {
			header.style.removeProperty('--emm-mobile-offset');
			return;
		}

		var bar = header.querySelector('.emm-nav__bar');
		var offset = bar ? Math.max(adminOffset, Math.round(bar.getBoundingClientRect().bottom)) : adminOffset;
		header.style.setProperty('--emm-mobile-offset', offset + 'px');
	}

	function updateOverflowRisk(header) {
		if (window.matchMedia(MOBILE_MQ).matches) {
			header.classList.remove('emm-force-mobile', 'emm-has-overflow-risk');
			return;
		}
		var wasForced = header.classList.contains('emm-force-mobile');
		header.classList.remove('emm-force-mobile');
		var inner = header.querySelector('.emm-nav__inner');
		var list = header.querySelector('.emm-nav__list');
		if (!inner || !list) return;
		var required = 0;
		Array.prototype.forEach.call(list.children, function (item) {
			var widthTarget = item;
			Array.prototype.some.call(item.children, function (child) {
				if (!child.matches('.emm-nav__link, [data-emm-mega-trigger]')) return false;
				widthTarget = child;
				return true;
			});
			required += Math.ceil(widthTarget.getBoundingClientRect().width);
		});
		required += Math.max(0, list.children.length - 1) * 4;
		var innerStyle = window.getComputedStyle(inner);
		var innerGap = parseFloat(innerStyle.columnGap || innerStyle.gap) || 0;
		Array.prototype.forEach.call(inner.children, function (child) {
			if (child === list || window.getComputedStyle(child).display === 'none') return;
			required += Math.ceil(child.getBoundingClientRect().width);
		});
		required += Math.max(0, inner.children.length - 1) * innerGap;
		// Font and container measurements can wobble by a few pixels while an
		// Elementor header settles. Use hysteresis so that transient changes do not
		// switch modes and reset an open desktop mega menu.
		var overflow = required - inner.clientWidth;
		var forced = overflow > (wasForced ? -OVERFLOW_HYSTERESIS : OVERFLOW_HYSTERESIS);
		header.classList.toggle('emm-force-mobile', forced);
		header.classList.toggle('emm-has-overflow-risk', forced);
		if (forced !== wasForced) {
			setDrawerOpen(header, false, false);
			closeAllMegas(header);
			updateMobileGeometry(header);
		}
	}

	function syncMode(header) {
		if (isMobile(header)) {
			return;
		}

		header.classList.remove('emm-is-drilling');
		header.removeAttribute('data-drill-level');

		header.querySelectorAll('.emm-mega--platforms').forEach(function (mega) {
			var bar = mega.querySelector('[data-emm-mobile-bar]');
			if (bar) bar.hidden = true;

			var sections = mega.querySelectorAll('.emm-mega__section');
			if (!sections.length) return;
			var active = mega.querySelector('.emm-mega__section.is-open');
			if (!active) active = sections[0];
			sections.forEach(function (s) {
				setSectionOpen(s, s === active);
			});
			syncDesktopAccordionHeight(mega);
		});

		header.querySelectorAll('.emm-nav__item--mega').forEach(function (item) {
			item.removeAttribute('data-drill');
		});
	}

	function remapCollidingIds(header) {
		var idMap = {};
		var instance = (header.getAttribute('data-emm-instance') || 'clone')
			.replace(/[^A-Za-z0-9_-]+/g, '-')
			.replace(/^-+|-+$/g, '') || 'clone';

		header.querySelectorAll('[id]').forEach(function (element) {
			var originalId = element.id;
			if (!originalId || document.getElementById(originalId) === element) return;

			var baseId = originalId + '--' + instance;
			var uniqueId = baseId;
			var suffix = 2;
			while (document.getElementById(uniqueId)) {
				uniqueId = baseId + '-' + suffix;
				suffix += 1;
			}

			idMap[originalId] = uniqueId;
			element.id = uniqueId;
		});

		var originalIds = Object.keys(idMap);
		if (!originalIds.length) return;

		['aria-controls', 'aria-labelledby', 'aria-describedby', 'aria-owns', 'for'].forEach(function (attribute) {
			header.querySelectorAll('[' + attribute + ']').forEach(function (element) {
				var value = element.getAttribute(attribute);
				if (!value) return;
				var remapped = value.split(/\s+/).map(function (token) {
					return idMap[token] || token;
				}).join(' ');
				if (remapped !== value) element.setAttribute(attribute, remapped);
			});
		});

		header.querySelectorAll('[href^="#"]').forEach(function (element) {
			var targetId = element.getAttribute('href').slice(1);
			if (idMap[targetId]) element.setAttribute('href', '#' + idMap[targetId]);
		});
	}

	function syncBrandLogos(header) {
		header.querySelectorAll('.emm-nav__brand-logo').forEach(function (logo) {
			var brand = closest(logo, '.emm-nav__brand');
			if (!brand) return;
			var sync = function () {
				brand.classList.toggle(
					'emm-nav__brand--logo-failed',
					logo.complete && logo.naturalWidth === 0
				);
			};
			logo.addEventListener('load', sync);
			logo.addEventListener('error', sync);
			sync();
		});
	}

	function initHeader(header) {
		// Optimizers can concatenate a script more than once, or a page builder can
		// render the same fragment again. Binding a second set of listeners makes a
		// tap open and immediately close the menu, so initialise each DOM node once.
		// Use an element property for the guard because cloneNode() copies attributes
		// but not properties or event listeners. A cloned header that carries the
		// diagnostic data attribute must still receive its own listeners.
		if (header.__easyMegaMenuInitialized === true) return;
		remapCollidingIds(header);
		syncBrandLogos(header);
		header.__easyMegaMenuInitialized = true;
		header.setAttribute('data-emm-initialized', 'true');

		var hoverMq = window.matchMedia('(hover: hover) and (pointer: fine)');
		var desktopOpenMode = header.getAttribute('data-emm-desktop-open') === 'click' ? 'click' : 'hover';
		var unifiedHover = header.getAttribute('data-emm-presentation') === 'unified' && desktopOpenMode === 'hover';
		var desktopCloseDelay = unifiedHover ? 150 : 800;
		var closeTimer = null;

		function clearCloseTimer() {
			if (closeTimer) {
				clearTimeout(closeTimer);
				closeTimer = null;
			}
		}

		function scheduleClose() {
			clearCloseTimer();
			closeTimer = setTimeout(function () {
				closeTimer = null;
				// Absolutely positioned panels can extend beyond the header's
				// visual box. Do not close while the pointer is still over any
				// descendant in the complete header/menu interaction region.
				var openItem = header.querySelector('.emm-nav__item--mega.is-open');
				if (
					(!unifiedHover && openItem && openItem.__easyMegaMenuClickOpen === true) ||
					(header.matches && header.matches(':hover')) ||
					(header.contains(document.activeElement) && (!unifiedHover || document.activeElement.matches(':focus-visible')))
				) return;
				closeAllMegas(header);
			}, desktopCloseDelay);
		}

		function isWithinHeader(element) {
			return !!(element && element.nodeType === 1 && header.contains(element));
		}

		header.querySelectorAll('.emm-nav__item').forEach(function (item) {
			item.addEventListener('mouseenter', function () {
				if (desktopOpenMode === 'click' || isMobile(header) || !hoverMq.matches) return;
				clearCloseTimer();
				if (item.classList.contains('emm-nav__item--mega')) {
					if (!item.classList.contains('is-open')) openMegaDesktop(item);
				} else {
					closeAllMegas(header);
				}
			});
		});

		header.addEventListener('mouseleave', function (event) {
			if (desktopOpenMode === 'click' || isMobile(header) || !hoverMq.matches) return;
			if (isWithinHeader(event.relatedTarget)) {
				clearCloseTimer();
				return;
			}
			scheduleClose();
		});

		header.addEventListener('mouseenter', function () {
			if (desktopOpenMode === 'click') return;
			clearCloseTimer();
		});

		// Product links share the same row but sit outside the corporate list.
		// Moving to one must dismiss the Products panel without another click.
		header.querySelectorAll('.emm-product-nav__link').forEach(function (link) {
			link.addEventListener('mouseenter', function () {
				if (unifiedHover && !isMobile(header) && hoverMq.matches) {
					clearCloseTimer();
					closeAllMegas(header);
				}
			});
		});

		header.querySelectorAll('.emm-nav__item--mega').forEach(function (item) {
			var trigger = item.querySelector('[data-emm-mega-trigger]');
			var panel = item.querySelector('[data-emm-mega-panel]');
			if (!trigger) return;

			if (panel) {
				panel.addEventListener('mouseenter', function () {
					if (desktopOpenMode !== 'click' && !isMobile(header) && hoverMq.matches) clearCloseTimer();
				});
				panel.addEventListener('mouseleave', function (event) {
					if (desktopOpenMode === 'click' || isMobile(header) || !hoverMq.matches) return;
					if (isWithinHeader(event.relatedTarget)) {
						clearCloseTimer();
						return;
					}
					scheduleClose();
				});
			}

			trigger.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				clearCloseTimer();

				if (isMobile(header)) {
					if (item.classList.contains('is-open')) {
						drillBack(item);
					} else {
						openMegaMobile(item);
					}
					return;
				}

				if (item.classList.contains('is-open')) {
					closeAllMegas(header);
				} else {
					openMegaDesktop(item);
					item.__easyMegaMenuClickOpen = true;
				}
			});
		});

		header.querySelectorAll('.emm-mega__cat').forEach(function (btn) {
			btn.addEventListener('mouseenter', function () {
				if (!isMobile(header) && hoverMq.matches) activateCategoryDesktop(btn);
			});
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				var item = closest(btn, '.emm-nav__item--mega');
				if (isMobile(header)) {
					if (item) drillIntoCategory(item, btn);
				} else {
					activateCategoryDesktop(btn);
				}
			});
			btn.addEventListener('keydown', function (e) {
				if (['ArrowDown', 'ArrowUp', 'Home', 'End'].indexOf(e.key) === -1) return;
				var mega = closest(btn, '.emm-mega');
				if (!mega) return;
				var buttons = Array.prototype.slice.call(mega.querySelectorAll('.emm-mega__cat'));
				var index = buttons.indexOf(btn);
				if (index < 0) return;
				e.preventDefault();
				if (e.key === 'Home') index = 0;
				if (e.key === 'End') index = buttons.length - 1;
				if (e.key === 'ArrowDown') index = (index + 1) % buttons.length;
				if (e.key === 'ArrowUp') index = (index - 1 + buttons.length) % buttons.length;
				buttons[index].focus();
				if (!isMobile(header)) activateCategoryDesktop(buttons[index]);
			});
		});

		header.addEventListener('click', function (e) {
			var back = e.target.closest ? e.target.closest('[data-emm-back]') : null;
			if (!back || !header.contains(back)) return;
			e.preventDefault();
			e.stopPropagation();
			var item = closest(back, '.emm-nav__item--mega');
			if (item) drillBack(item);
		});

		var toggle = header.querySelector('.emm-nav__toggle');
		if (toggle) {
			toggle.addEventListener('click', function (e) {
				e.stopPropagation();
				var open = toggle.getAttribute('aria-expanded') !== 'true';
				setDrawerOpen(header, open, true);
			});
		}

		header.addEventListener('click', function (e) {
			var closeBtn = e.target.closest ? e.target.closest('[data-emm-drawer-close]') : null;
			if (!closeBtn || !header.contains(closeBtn)) return;
			e.preventDefault();
			e.stopPropagation();
			setDrawerOpen(header, false, true);
		});

		var backdrop = header.querySelector('[data-emm-backdrop]');
		if (backdrop) {
			backdrop.addEventListener('click', function () {
				setDrawerOpen(header, false, true);
			});
		}

		document.addEventListener('keydown', function (e) {
			var focusedHeader = closest(document.activeElement, '.emm-header');
			if (focusedHeader && focusedHeader !== header) return;
			if (e.key === 'Tab' && isMobile(header) && header.classList.contains('is-mobile-open')) {
				var drawer = header.querySelector('.emm-nav__drawer');
				var focusable = visibleFocusable(drawer);
				if (!focusable.length) return;
				var first = focusable[0];
				var last = focusable[focusable.length - 1];
				if (e.shiftKey && document.activeElement === first) {
					e.preventDefault();
					last.focus();
				} else if (!e.shiftKey && document.activeElement === last) {
					e.preventDefault();
					first.focus();
				}
				return;
			}
			if (e.key !== 'Escape') return;
			clearCloseTimer();
			if (isMobile(header)) {
				var openItem = header.querySelector('.emm-nav__item--mega.is-open');
				if (openItem) {
					drillBack(openItem);
					return;
				}
			}
			var openTrigger = header.querySelector('.emm-nav__item--mega.is-open [data-emm-mega-trigger]');
			closeAllMegas(header);
			if (openTrigger && typeof openTrigger.focus === 'function') openTrigger.focus();
			if (header.classList.contains('is-mobile-open')) {
				setDrawerOpen(header, false, true);
			}
		});

		function isOutsideHeaderEvent(e) {
			return !header.contains(e.target) && !closest(e.target, '.emm-header');
		}

		// Some page-builder widgets dispatch delayed synthetic click events on the
		// document. Treating those as an outside tap resets an open mobile drill-down
		// even though the visitor has not touched outside the drawer. A pointer press
		// represents the actual dismissal gesture and still precedes a normal click.
		var outsidePressEvent = window.PointerEvent ? 'pointerdown' : 'mousedown';
		document.addEventListener(outsidePressEvent, function (e) {
			if (!isOutsideHeaderEvent(e)) return;
			clearCloseTimer();
			if (isMobile(header)) {
				if (header.classList.contains('is-mobile-open')) {
					setDrawerOpen(header, false, false);
				}
				return;
			}
			closeAllMegas(header);
		});

		document.addEventListener('click', function (e) {
			if (!isOutsideHeaderEvent(e)) return;
			// Elementor autoplay widgets call element.click() as they rotate slides.
			// Those untrusted synthetic events bubble to document and must not close
			// an unrelated navigation menu. Genuine mouse, touch and keyboard clicks
			// are trusted; pointerdown above also provides immediate pointer dismissal.
			if (e.isTrusted === false) return;
			clearCloseTimer();
			if (!isMobile(header)) closeAllMegas(header);
		});

		window.addEventListener('resize', function () {
			updateOverflowRisk(header);
			if (
				isMobile(header) &&
				header.querySelector('.emm-nav__item--mega.is-open:not([data-drill])')
			) {
				// Desktop panels do not carry a mobile drill state. Clear one when a
				// resize or device rotation crosses into the drawer layout so the
				// desktop two-panel grid cannot leak beside the mobile categories.
				closeAllMegas(header);
			} else if (!isMobile(header) && header.classList.contains('is-mobile-open')) {
				setDrawerOpen(header, false, false);
			}
			updateMobileGeometry(header);
			syncMode(header);
			repositionOpenPanels(header);
		});

		if (window.ResizeObserver) {
			var geometryObserver = new ResizeObserver(function () {
				updateMobileGeometry(header);
				updateOverflowRisk(header);
				repositionOpenPanels(header);
			});
			geometryObserver.observe(header.querySelector('.emm-nav__bar') || header);
			header.querySelectorAll('.emm-mega__panel').forEach(function (panel) {
				geometryObserver.observe(panel);
			});
			var adminBar = document.getElementById('wpadminbar');
			if (adminBar) geometryObserver.observe(adminBar);
		}
		if (window.visualViewport) {
			window.visualViewport.addEventListener('resize', function () {
				updateMobileGeometry(header);
			});
		}

		updateMobileGeometry(header);
		updateOverflowRisk(header);
		if (document.fonts && document.fonts.ready) {
			document.fonts.ready.then(function () {
				updateOverflowRisk(header);
				updateMobileGeometry(header);
				repositionOpenPanels(header);
			});
		}
		syncMode(header);
	}

	function initWithin(root) {
		if (!root) return;
		if (root.nodeType === 1 && root.matches && root.matches('.emm-header')) {
			initHeader(root);
		}
		if (root.querySelectorAll) {
			root.querySelectorAll('.emm-header').forEach(initHeader);
		}
	}

	function init() {
		initWithin(document);
		syncExternalProductDrawerState();
		fitExternalMegaPanels();
	}

	function syncExternalProductDrawerState() {
		var open = !!document.querySelector('.elementskit-menu-container.active');
		document.documentElement.classList.toggle(EXTERNAL_DRAWER_CLASS, open);
	}

	function fitExternalMegaPanels() {
		var panels = document.querySelectorAll('.elementskit-megamenu-panel');
		var hasVisiblePanel = false;
		panels.forEach(function (panel) {
			if (window.matchMedia(MOBILE_MQ).matches) {
				panel.style.removeProperty('--emm-external-panel-shift');
				return;
			}

			panel.style.setProperty('--emm-external-panel-shift', '0px');
			var rect = panel.getBoundingClientRect();
			if (rect.width <= 0 || rect.height <= 0) return;
			hasVisiblePanel = true;

			var gutter = 16;
			var shift = 0;
			if (rect.right > window.innerWidth - gutter) {
				shift -= rect.right - (window.innerWidth - gutter);
			}
			if (rect.left + shift < gutter) {
				shift += gutter - (rect.left + shift);
			}
			panel.style.setProperty('--emm-external-panel-shift', Math.round(shift) + 'px');
		});
		document.documentElement.classList.toggle(EXTERNAL_MEGA_CLASS, hasVisiblePanel);
	}

	function scheduleExternalPanelFit() {
		window.requestAnimationFrame(function () {
			fitExternalMegaPanels();
		});
		/* ElementsKit may reveal its panel after the click/hover handler returns.
		 * Recheck after both its short transition and its delayed inline-style
		 * update so viewport fitting and chatbot suppression use the final state. */
		window.setTimeout(fitExternalMegaPanels, 120);
		window.setTimeout(fitExternalMegaPanels, 360);
	}

	function observeDynamicHeaders() {
		if (!window.MutationObserver || document.__easyMegaMenuObserverActive === true) return;
		document.__easyMegaMenuObserverActive = true;

		var observer = new MutationObserver(function (records) {
			records.forEach(function (record) {
				record.addedNodes.forEach(initWithin);
			});
		});
		observer.observe(document.documentElement, { childList: true, subtree: true });
	}

	function observeExternalProductDrawers() {
		if (!window.MutationObserver || document.__easyMegaMenuExternalDrawerObserverActive === true) return;
		document.__easyMegaMenuExternalDrawerObserverActive = true;

		var observer = new MutationObserver(function (records) {
			var shouldSync = records.some(function (record) {
				return record.type === 'attributes' || record.addedNodes.length > 0 || record.removedNodes.length > 0;
			});
			if (shouldSync) {
				syncExternalProductDrawerState();
				scheduleExternalPanelFit();
			}
		});
		observer.observe(document.documentElement, {
			attributes: true,
			attributeFilter: ['class'],
			childList: true,
			subtree: true
		});
	}

	observeDynamicHeaders();
	observeExternalProductDrawers();
	document.addEventListener('click', scheduleExternalPanelFit, true);
	document.addEventListener('pointerover', scheduleExternalPanelFit, true);
	window.addEventListener('resize', scheduleExternalPanelFit);

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
