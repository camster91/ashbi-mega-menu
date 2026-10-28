/**
 * Ashbi Mega Menu — Frontend interactions
 * Desktop: hover mega + category tabs
 * Mobile: drill-down screens (root → one screen of category headings and their links)
 */
(function () {
	'use strict';

	var MOBILE_MQ = '(max-width: 900px)';
	var OVERFLOW_HYSTERESIS = 16;
	var STACK_HOST_CLASS = 'abmm-stack-host--active';
	var OBSCURED_HEADER_CLASS = 'abmm-obscured-by-open-menu';

	function closest(el, sel) {
		while (el && el.nodeType === 1) {
			if (el.matches(sel)) return el;
			el = el.parentElement;
		}
		return null;
	}

	function isMobile(subject) {
		var header = subject && subject.classList && subject.classList.contains('abmm-header')
			? subject
			: closest(subject, '.abmm-header');
		return window.matchMedia(MOBILE_MQ).matches ||
			!!(header && header.classList.contains('abmm-force-mobile'));
	}

	function syncActiveStackHost() {
		document.querySelectorAll('.' + STACK_HOST_CLASS).forEach(function (host) {
			host.classList.remove(STACK_HOST_CLASS);
		});

		var activeHeader = document.querySelector(
			'.abmm-header.abmm-has-open-menu, .abmm-header.is-mobile-open'
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
		document.querySelectorAll('.abmm-header.' + OBSCURED_HEADER_CLASS).forEach(function (header) {
			header.classList.remove(OBSCURED_HEADER_CLASS);
		});
	}

	function syncObscuredHeaders(activeHeader) {
		clearObscuredHeaders();
		if (!activeHeader || isMobile(activeHeader)) return;

		var openPanel = activeHeader.querySelector(
			'.abmm-nav__item--mega.is-open [data-abmm-mega-panel]:not([hidden])'
		);
		if (!openPanel) return;

		var panelRect = openPanel.getBoundingClientRect();
		if (panelRect.width <= 0 || panelRect.height <= 0) return;

		document.querySelectorAll('.abmm-header').forEach(function (header) {
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
		var activeHeader = document.querySelector('.abmm-header.abmm-has-open-menu');
		if (activeHeader) {
			syncObscuredHeaders(activeHeader);
		} else {
			clearObscuredHeaders();
		}
	}

	function visibleFocusable(container) {
		return Array.prototype.filter.call(
			container.querySelectorAll(
				'a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])'
			),
			function (element) {
				if (element.hidden || !element.getClientRects().length) return false;
				// The bar's CTA stays in place but invisible while the drawer is open.
				var style = window.getComputedStyle ? window.getComputedStyle(element) : null;
				return !style || style.visibility !== 'hidden';
			}
		);
	}

	function focusFirst(container, fallback) {
		var focusable = container ? visibleFocusable(container) : [];
		var target = focusable[0] || fallback;
		if (target && typeof target.focus === 'function') target.focus();
	}

	/**
	 * Public events for add-ons (analytics, logging), dispatched on the menu
	 * root and bubbling. The plugin itself never sends them anywhere.
	 *   abmm:open   detail { menuId, itemId, label, layout: 'desktop'|'mobile' }
	 *   abmm:close  detail { menuId, itemId, label }
	 *   abmm:drawer detail { menuId, open }
	 */
	function emit(header, name, detail) {
		if (!header || typeof window.CustomEvent !== 'function') return;
		detail.menuId = header.getAttribute('data-abmm-id') || '';
		header.dispatchEvent(new window.CustomEvent(name, { bubbles: true, detail: detail }));
	}

	function itemDetail(item) {
		var btn = item.querySelector('[data-abmm-mega-trigger]');
		return {
			itemId: item.getAttribute('data-abmm-item-id') || '',
			label: (btn && btn.textContent ? btn.textContent : '').replace(/\s+/g, ' ').trim(),
		};
	}

	function closeAllMegas(root) {
		root.querySelectorAll('.abmm-nav__item--mega.is-open').forEach(function (item) {
			emit(closest(item, '.abmm-header'), 'abmm:close', itemDetail(item));
			item.__ashbiMegaMenuClickOpen = false;
			item.classList.remove('is-open');
			item.removeAttribute('data-drill');
			var btn = item.querySelector('[data-abmm-mega-trigger]');
			var panel = item.querySelector('[data-abmm-mega-panel]');
			if (btn) btn.setAttribute('aria-expanded', 'false');
			if (panel) {
				panel.hidden = true;
				positionDesktopPanel(panel, null);
				resetDrillPanels(panel);
				syncDesktopAccordionHeight(panel);
			}
		});
		root.classList.remove('abmm-is-drilling');
		root.classList.remove('abmm-has-open-menu');
		root.removeAttribute('data-drill-level');
		syncOpenHeaderLayers();
	}

	function resetDrillPanels(mega) {
		mega.querySelectorAll('.abmm-mega__section').forEach(function (section) {
			setSectionOpen(section, false);
			setSectionCollapsed(section, false);
		});
		var bar = mega.querySelector('[data-abmm-mobile-bar]');
		if (bar) bar.hidden = true;
	}

	function setSectionOpen(section, open) {
		var btn = section.querySelector('.abmm-mega__cat');
		var panel = section.querySelector('.abmm-mega__panel');
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

	/**
	 * Phone accordion: a category heading shows or hides its links. This is
	 * separate from the desktop "open category" state, which stays on for every
	 * category of the one-screen phone layout.
	 */
	function setSectionCollapsed(section, collapsed) {
		var toggle = section.querySelector('[data-abmm-section-toggle]');
		if (!toggle) return;
		section.classList.toggle('is-collapsed', collapsed);
		toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
	}

	function activateCategoryDesktop(catBtn) {
		var mega = closest(catBtn, '.abmm-mega');
		if (!mega) return;
		var id = catBtn.getAttribute('data-abmm-cat');

		mega.querySelectorAll('.abmm-mega__section').forEach(function (section) {
			var on = section.getAttribute('data-abmm-section') === id;
			setSectionOpen(section, on);
		});
		syncDesktopAccordionHeight(mega);
	}

	function ensureMobileBar(mega) {
		var bar = mega.querySelector('[data-abmm-mobile-bar]');
		if (bar) return bar;

		bar = document.createElement('div');
		bar.className = 'abmm-mobile-bar';
		bar.setAttribute('data-abmm-mobile-bar', '');
		bar.innerHTML =
			'<button type="button" class="abmm-mobile-back" data-abmm-back aria-label="Back">' +
			'<svg width="18" height="18" viewBox="0 0 12 12" aria-hidden="true"><path d="M7.5 2.5L4 6l3.5 3.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>' +
			'<span></span></button>' +
			'<span class="abmm-mobile-title" data-abmm-mobile-title></span>' +
			'<button type="button" class="abmm-nav__drawer-close abmm-mobile-close" data-abmm-drawer-close aria-label="Close menu">' +
			'<span aria-hidden="true">&times;</span></button>';
		// The visible label is shown in every drawer, so use the translated word when the page provides it.
		var header = closest(mega, '.abmm-header');
		bar.querySelector('.abmm-mobile-back span').textContent =
			(header && header.getAttribute('data-abmm-back-label')) || runtimeString('back', 'Back');
		mega.insertBefore(bar, mega.firstChild);
		return bar;
	}

	function runtimeString(key, fallback) {
		var strings = (window.abmmRuntime && window.abmmRuntime.searchStrings) || {};
		return strings[key] || fallback;
	}

	function setMobileBar(mega, title, visible) {
		var bar = ensureMobileBar(mega);
		bar.hidden = !visible;
		var titleEl = bar.querySelector('[data-abmm-mobile-title]');
		if (titleEl) titleEl.textContent = title || '';
		var back = bar.querySelector('[data-abmm-back]');
		// A mega item opens straight to its links, so Back always returns to the menu.
		if (back) back.setAttribute('aria-label', runtimeString('backTo', 'Back to %s').replace('%s', runtimeString('backToMenu', 'menu')));
	}

	function initMenuSearch(header) {
		var strings = (window.abmmRuntime && window.abmmRuntime.searchStrings) || {};
		var drawer = header.querySelector('.abmm-nav__drawer');
		if (!drawer) return;
		// Sticky-header clones copy markup but not listeners. Rebuild their search.
		drawer.querySelectorAll('[data-abmm-search]').forEach(function (old) { old.remove(); });
		header.classList.remove('abmm-is-searching');
		if (!header.classList.contains('abmm-mobile-enhanced') || header.getAttribute('data-abmm-search') === 'off') return;
		var entries = [];
		var seen = {};
		drawer.querySelectorAll('a[href]').forEach(function (link) {
			var href = link.getAttribute('href');
			if (!href || href.charAt(0) === '#' || link.classList.contains('is-disabled')) return;
			// A promotional card is not a menu destination of its own.
			if (link.classList.contains('abmm-mega__featured')) return;
			var url;
			try { url = new URL(href, document.baseURI); } catch (err) { return; }
			if (!/^https?:$/.test(url.protocol)) return;
			var title = link.textContent.replace(/\s+/g, ' ').trim();
			if (!title) return;
			var section = closest(link, '.abmm-mega__section');
			var cat = section && section.querySelector('.abmm-mega__cat-title');
			var item = closest(link, '.abmm-nav__item');
			var trigger = item && item.querySelector('[data-abmm-mega-trigger]');
			var context = (cat || trigger || {}).textContent || strings.menu || 'Menu';
			context = context.replace(/\s+/g, ' ').trim();
			var key = url.href + '|' + title;
			if (seen[key]) return;
			seen[key] = true;
			entries.push({ title: title, context: context, href: href, source: link });
			if (url.origin === location.origin && url.pathname === location.pathname && url.search === location.search && !url.hash) {
				link.setAttribute('aria-current', 'page');
			}
		});
		if (!entries.length) return;
		var search = document.createElement('div');
		search.className = 'abmm-menu-search';
		search.setAttribute('data-abmm-search', '');
		var label = document.createElement('label');
		label.className = 'abmm-menu-search__label';
		label.textContent = strings.label || 'Find a product or page';
		var input = document.createElement('input');
		input.type = 'search';
		input.placeholder = strings.placeholder || 'Search this menu';
		input.autocomplete = 'off';
		label.appendChild(input);
		var clear = document.createElement('button');
		clear.type = 'button';
		clear.className = 'abmm-menu-search__clear';
		clear.textContent = strings.clear || 'Clear';
		clear.setAttribute('aria-label', strings.clearLabel || 'Clear menu search');
		clear.hidden = true;
		var status = document.createElement('p');
		status.className = 'abmm-menu-search__status';
		status.setAttribute('role', 'status');
		var results = document.createElement('ul');
		results.className = 'abmm-menu-search__results';
		results.setAttribute('aria-label', strings.resultsLabel || 'Menu search results');
		results.hidden = true;
		search.appendChild(label);
		search.appendChild(clear);
		search.appendChild(status);
		search.appendChild(results);
		var head = drawer.querySelector('.abmm-nav__drawer-head');
		if (head) head.after(search); else drawer.insertBefore(search, drawer.firstChild);
		function render() {
			var terms = input.value.toLocaleLowerCase().trim().split(/\s+/).filter(Boolean);
			var active = terms.length > 0;
			header.classList.toggle('abmm-is-searching', active);
			clear.hidden = !active;
			results.hidden = !active;
			results.textContent = '';
			status.textContent = '';
			if (!active) return;
			var matches = entries.filter(function (entry) {
				var text = (entry.title + ' ' + entry.context).toLocaleLowerCase();
				return terms.every(function (term) { return text.indexOf(term) !== -1; });
			});
			var countText = matches.length === 1 ? (strings.oneResult || '%d result') : (strings.manyResults || '%d results');
			status.textContent = matches.length ? countText.replace('%d', String(matches.length)) : (strings.empty || 'No matches. Try another word or clear your search.');
			matches.forEach(function (entry) {
				var row = document.createElement('li');
				var link = document.createElement('a');
				link.href = entry.href;
				['target', 'rel', 'download', 'aria-current'].forEach(function (attr) {
					if (entry.source.hasAttribute(attr)) link.setAttribute(attr, entry.source.getAttribute(attr));
				});
				var title = document.createElement('span');
				title.textContent = entry.title;
				var context = document.createElement('small');
				context.textContent = entry.context;
				link.appendChild(title);
				link.appendChild(context);
				row.appendChild(link);
				results.appendChild(row);
			});
		}
		function reset() { input.value = ''; render(); }
		input.addEventListener('input', render);
		clear.addEventListener('click', function () { reset(); input.focus(); });
		search.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && input.value) {
				e.preventDefault(); e.stopPropagation(); reset(); input.focus();
			}
		});
		header.__ashbiMegaMenuResetSearch = reset;
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
		var accordion = mega.querySelector('.abmm-mega__accordion');
		var header = closest(mega, '.abmm-header');
		if (!accordion) return;
		if (!header || isMobile(header) || header.classList.contains('abmm-layout--stacked') || mega.hidden) {
			accordion.style.removeProperty('--abmm-active-panel-height');
			return;
		}
		var panel = mega.querySelector('.abmm-mega__panel.is-active:not([hidden])');
		if (!panel) {
			accordion.style.removeProperty('--abmm-active-panel-height');
			return;
		}
		// Use the tallest category so switching categories does not make the panel jump.
		// The panel column stretches to the accordion's height so its footer strip
		// sits on the bottom edge. Measure without the previous value, then use the
		// taller of the category rail and the tallest category's content so
		// switching categories does not make the panel jump.
		accordion.style.removeProperty('--abmm-active-panel-height');
		var height = Math.ceil(accordion.getBoundingClientRect().height);
		mega.querySelectorAll('.abmm-mega__panel').forEach(function (other) {
			var wasHidden = other.hidden;
			other.hidden = false;
			height = Math.max(height, panelContentHeight(other));
			other.hidden = wasHidden;
		});
		// The featured card is positioned beside the columns, so it adds no height
		// of its own. Keep the panel tall enough for it.
		var featured = accordion.querySelector(':scope > .abmm-mega__featured');
		if (featured && featured.getClientRects().length && window.getComputedStyle(featured).position === 'absolute') {
			// Leave the usual bottom padding, plus the "see all" strip that runs under the card.
			var reserve = Number.parseFloat(window.getComputedStyle(accordion).getPropertyValue('--abmm-panel-pad-bottom')) || 32;
			var footer = panel.querySelector('.abmm-mega__panel-title');
			if (footer && footer.getClientRects().length) reserve += footer.getBoundingClientRect().height;
			height = Math.max(height, Math.ceil(featured.offsetTop + featured.getBoundingClientRect().height + reserve));
		}
		var maxHeight = window.getComputedStyle ? Number.parseFloat(window.getComputedStyle(panel).maxHeight) : NaN;
		if (maxHeight > 0) height = Math.min(height, Math.ceil(maxHeight));
		if (height > 0) accordion.style.setProperty('--abmm-active-panel-height', height + 'px');
	}

	function panelContentHeight(panel) {
		var height = 0;
		Array.prototype.forEach.call(panel.children, function (child) {
			if (child.hidden || !child.getClientRects().length) return;
			// scrollHeight includes content that a shrunken, scrolling column hides.
			height += Math.max(child.scrollHeight || 0, Math.ceil(child.getBoundingClientRect().height));
		});
		return Math.max(height, Math.ceil(panel.getBoundingClientRect().height));
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
		var nav = header.querySelector('.abmm-nav') || header;
		var navRect = nav.getBoundingClientRect();

		if (!header.classList.contains('abmm-full-width')) {
			panel.style.left = '';
			panel.style.right = '';
			panel.style.width = '';
			panel.style.maxWidth = '';
			panel.style.transform = '';
			var naturalWidth = Math.round(panel.getBoundingClientRect().width);
			var container = preferredPanelContainerRect(header, naturalWidth);
			// The page hero begins slightly outside Elementor's inner header
			// container. Bleed the shared menu eight pixels past that container on
			// each side so page lettering cannot peek through as stray edge marks.
			var edgeBleed = header.getAttribute('data-abmm-id') === 'menu_demo' ? 8 : 0;
			var containerLeft = container.left - edgeBleed;
			var containerWidth = container.width + edgeBleed * 2;
			var width = Math.max(0, Math.min(naturalWidth, containerWidth, window.innerWidth - 32));
			var item = closest(panel, '.abmm-nav__item--mega');
			var trigger = item ? item.querySelector('[data-abmm-mega-trigger]') : null;
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

	// The optional desktop scrim starts below the menu bar so the bar stays bright.
	function syncScrim(header) {
		if (!header || !header.classList.contains('abmm-has-scrim')) return;
		var nav = header.querySelector('.abmm-nav') || header;
		var bottom = Math.max(0, Math.round(nav.getBoundingClientRect().bottom));
		header.style.setProperty('--abmm-scrim-top', bottom + 'px');
	}

	function repositionOpenPanels(header) {
		if (!header) return;
		syncScrim(header);
		header.querySelectorAll('[data-abmm-mega-panel]').forEach(function (panel) {
			var open = closest(panel, '.abmm-nav__item--mega.is-open');
			if (!open || isMobile(header)) {
				positionDesktopPanel(panel, null);
				return;
			}
			positionDesktopPanel(panel, header);
			syncDesktopAccordionHeight(panel);
		});
		if (header.classList.contains('abmm-has-open-menu')) syncObscuredHeaders(header);
	}

	function openMegaDesktop(item) {
		var header = closest(item, '.abmm-header');
		document.querySelectorAll('.abmm-header').forEach(function (menuHeader) {
			closeAllMegas(menuHeader);
		});
		item.classList.add('is-open');
		if (header) {
			syncScrim(header);
			header.classList.add('abmm-has-open-menu');
		}
		syncOpenHeaderLayers();
		var btn = item.querySelector('[data-abmm-mega-trigger]');
		var panel = item.querySelector('[data-abmm-mega-panel]');
		if (btn) btn.setAttribute('aria-expanded', 'true');
		if (panel) {
			panel.hidden = false;
			positionDesktopPanel(panel, header);
			var sections = panel.querySelectorAll('.abmm-mega__section');
			if (sections.length) {
				var active = panel.querySelector('.abmm-mega__section.is-open') || sections[0];
				sections.forEach(function (s) {
					setSectionOpen(s, s === active);
				});
			}
			syncDesktopAccordionHeight(panel);
			syncOpenHeaderLayers();
		}
		var openDetail = itemDetail(item);
		openDetail.layout = 'desktop';
		emit(header, 'abmm:open', openDetail);
	}

	/** Mobile: open mega at categories screen (or links for features layout) */
	function openMegaMobile(item) {
		var header = closest(item, '.abmm-header');
		if (!header) return;

		closeAllMegas(header);
		item.classList.add('is-open');
		var mobileDetail = itemDetail(item);
		mobileDetail.layout = 'mobile';
		emit(header, 'abmm:open', mobileDetail);
		header.classList.add('abmm-has-open-menu');
		syncActiveStackHost();

		var btn = item.querySelector('[data-abmm-mega-trigger]');
		var panel = item.querySelector('[data-abmm-mega-panel]');
		var label = (btn && btn.textContent ? btn.textContent : '').replace(/\s+/g, ' ').trim();

		if (btn) btn.setAttribute('aria-expanded', 'true');
		if (!panel) return;

		panel.hidden = false;
		syncDesktopAccordionHeight(panel);
		header.classList.add('abmm-is-drilling');

		// One screen per mega item: every category is a heading followed by its
		// links (a columns-only item has just the one category), so a link is
		// two taps away instead of three.
		var isFeatures = panel.getAttribute('data-abmm-style') === 'features';
		var isColumnsOnly = panel.classList.contains('abmm-mega--columns-only');
		panel.querySelectorAll('.abmm-mega__section').forEach(function (section, index) {
			setSectionOpen(section, !isColumnsOnly || index === 0);
			// Long menus would run off the screen, so only the first category
			// starts expanded. Its links stay two taps from the closed menu.
			setSectionCollapsed(section, index > 0);
		});
		item.setAttribute('data-drill', 'links');
		header.setAttribute('data-drill-level', '2');
		setMobileBar(panel, label, true);

		header.querySelector('.abmm-nav__drawer').scrollTop = 0;
		window.requestAnimationFrame(function () {
			if (isFeatures) {
				focusFirst(panel, panel.querySelector('[data-abmm-back]'));
				return;
			}
			var firstPanel = panel.querySelector('.abmm-mega__section.is-open .abmm-mega__panel');
			// Start on the first link, not on the category's accordion button.
			var firstLinks = firstPanel && firstPanel.querySelector('.abmm-mega__columns');
			focusFirst(firstLinks && visibleFocusable(firstLinks).length ? firstLinks : firstPanel, panel.querySelector('[data-abmm-back]'));
		});
	}

	/** Mobile: a category button (normally hidden on the one-screen layout) moves to its links. */
	function showCategoryMobile(catBtn) {
		var section = closest(catBtn, '.abmm-mega__section');
		var panel = section && section.querySelector('.abmm-mega__panel');
		if (!panel) return;
		if (typeof section.scrollIntoView === 'function') section.scrollIntoView({ block: 'start' });
		focusFirst(panel, catBtn);
	}

	function drillBack(item) {
		var header = closest(item, '.abmm-header');
		var panel = item.querySelector('[data-abmm-mega-panel]');
		if (!header || !panel) return;

		var trigger = item.querySelector('[data-abmm-mega-trigger]');

		// Back to root menu
		closeAllMegas(header);
		header.querySelector('.abmm-nav__drawer').scrollTop = 0;
		if (trigger && typeof trigger.focus === 'function') trigger.focus();
	}


	function setDrawerOpen(header, open, restoreFocus) {
		var toggle = header.querySelector('.abmm-nav__toggle');
		var backdrop = header.querySelector('[data-abmm-backdrop]');
		var drawer = header.querySelector('.abmm-nav__drawer');
		if (open && isMobile(header)) {
			document.querySelectorAll('.abmm-header.is-mobile-open').forEach(function (otherHeader) {
				if (otherHeader !== header) setDrawerOpen(otherHeader, false, false);
			});
		}
		var wasOpen = header.classList.contains('is-mobile-open');
		header.classList.toggle('is-mobile-open', open);
		if (wasOpen !== !!open) emit(header, 'abmm:drawer', { open: !!open });
		syncActiveStackHost();
		if (toggle) {
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			toggle.setAttribute('aria-label', open ? runtimeString('closeMenu', 'Close menu') : runtimeString('openMenu', 'Open menu'));
		}
		if (backdrop) backdrop.hidden = !open;
		document.documentElement.classList.toggle(
			'abmm-drawer-open',
			open || !!document.querySelector('.abmm-header.is-mobile-open')
		);
		if (!open) {
			if (header.__ashbiMegaMenuResetSearch) header.__ashbiMegaMenuResetSearch();
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
		header.style.setProperty('--abmm-admin-offset', adminOffset + 'px');

		if (!isMobile(header)) {
			header.style.removeProperty('--abmm-mobile-offset');
			return;
		}

		var bar = header.querySelector('.abmm-nav__bar');
		var offset = bar ? Math.max(adminOffset, Math.round(bar.getBoundingClientRect().bottom)) : adminOffset;
		// A bar low on the screen, such as a footer or sidebar widget, would
		// leave no room for the drawer below it. Open from the top instead.
		var viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
		var coversBar = false;
		if (viewportHeight && offset > viewportHeight - Math.min(320, viewportHeight / 2)) {
			// The drawer now covers the bar and its close button: show the drawer's own.
			coversBar = offset > adminOffset;
			offset = adminOffset;
		}
		header.classList.toggle('abmm-drawer-covers-bar', coversBar);
		header.style.setProperty('--abmm-mobile-offset', offset + 'px');
	}

	function updateOverflowRisk(header) {
		if (window.matchMedia(MOBILE_MQ).matches) {
			header.classList.remove('abmm-force-mobile', 'abmm-has-overflow-risk');
			return;
		}
		var wasForced = header.classList.contains('abmm-force-mobile');
		header.classList.remove('abmm-force-mobile');
		var inner = header.querySelector('.abmm-nav__inner');
		var list = header.querySelector('.abmm-nav__list');
		if (!inner || !list) return;
		var required = 0;
		Array.prototype.forEach.call(list.children, function (item) {
			var widthTarget = item;
			Array.prototype.some.call(item.children, function (child) {
				if (!child.matches('.abmm-nav__link, [data-abmm-mega-trigger]')) return false;
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
		header.classList.toggle('abmm-force-mobile', forced);
		header.classList.toggle('abmm-has-overflow-risk', forced);
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

		header.classList.remove('abmm-is-drilling');
		header.removeAttribute('data-drill-level');

		header.querySelectorAll('.abmm-mega--platforms').forEach(function (mega) {
			var bar = mega.querySelector('[data-abmm-mobile-bar]');
			if (bar) bar.hidden = true;
			mega.querySelectorAll('.abmm-mega__section').forEach(function (section) {
				setSectionCollapsed(section, false);
			});

			var sections = mega.querySelectorAll('.abmm-mega__section');
			if (!sections.length) return;
			var active = mega.querySelector('.abmm-mega__section.is-open');
			if (!active) active = sections[0];
			sections.forEach(function (s) {
				setSectionOpen(s, s === active);
			});
			syncDesktopAccordionHeight(mega);
		});

		header.querySelectorAll('.abmm-nav__item--mega').forEach(function (item) {
			item.removeAttribute('data-drill');
		});
	}

	function remapCollidingIds(header) {
		var idMap = {};
		var instance = (header.getAttribute('data-abmm-instance') || 'clone')
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
		header.querySelectorAll('.abmm-nav__brand-logo').forEach(function (logo) {
			var brand = closest(logo, '.abmm-nav__brand');
			if (!brand) return;
			var sync = function () {
				brand.classList.toggle(
					'abmm-nav__brand--logo-failed',
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
		if (header.__ashbiMegaMenuInitialized === true) return;
		remapCollidingIds(header);
		syncBrandLogos(header);
		header.__ashbiMegaMenuInitialized = true;
		header.setAttribute('data-abmm-initialized', 'true');
		initMenuSearch(header);

		var hoverMq = window.matchMedia('(hover: hover) and (pointer: fine)');
		var desktopOpenMode = header.getAttribute('data-abmm-desktop-open') === 'click' ? 'click' : 'hover';
		var desktopCloseDelay = 800;
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
				var openItem = header.querySelector('.abmm-nav__item--mega.is-open');
				if (
					(openItem && openItem.__ashbiMegaMenuClickOpen === true) ||
					(header.matches && header.matches(':hover')) ||
					header.contains(document.activeElement)
				) return;
				closeAllMegas(header);
			}, desktopCloseDelay);
		}

		function isWithinHeader(element) {
			return !!(element && element.nodeType === 1 && header.contains(element));
		}

		header.querySelectorAll('.abmm-nav__item').forEach(function (item) {
			item.addEventListener('mouseenter', function () {
				if (desktopOpenMode === 'click' || isMobile(header) || !hoverMq.matches) return;
				clearCloseTimer();
				if (item.classList.contains('abmm-nav__item--mega')) {
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

		// Tabbing out of the menu closes an open desktop panel and its scrim.
		// Only a focus move to another element counts: a null relatedTarget is a
		// click on blank space or a window switch, which must not close it.
		header.addEventListener('focusout', function (event) {
			if (isMobile(header) || isWithinHeader(event.relatedTarget) || !event.relatedTarget) return;
			if (!header.querySelector('.abmm-nav__item--mega.is-open')) return;
			clearCloseTimer();
			closeAllMegas(header);
		});

		header.querySelectorAll('.abmm-nav__item--mega').forEach(function (item) {
			var trigger = item.querySelector('[data-abmm-mega-trigger]');
			var panel = item.querySelector('[data-abmm-mega-panel]');
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
					// Hovering already opened this panel. A click that follows
					// should keep it open, not close it under the pointer.
					if (desktopOpenMode === 'hover' && hoverMq.matches && item.__ashbiMegaMenuClickOpen !== true) {
						item.__ashbiMegaMenuClickOpen = true;
						return;
					}
					closeAllMegas(header);
				} else {
					openMegaDesktop(item);
					item.__ashbiMegaMenuClickOpen = true;
				}
			});
		});

		header.querySelectorAll('.abmm-mega__cat').forEach(function (btn) {
			btn.addEventListener('mouseenter', function () {
				if (!isMobile(header) && hoverMq.matches) activateCategoryDesktop(btn);
			});
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				if (isMobile(header)) {
					showCategoryMobile(btn);
				} else {
					activateCategoryDesktop(btn);
				}
			});
			btn.addEventListener('keydown', function (e) {
				if (['ArrowDown', 'ArrowUp', 'Home', 'End'].indexOf(e.key) === -1) return;
				var mega = closest(btn, '.abmm-mega');
				if (!mega) return;
				var buttons = Array.prototype.slice.call(mega.querySelectorAll('.abmm-mega__cat'));
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

		header.querySelectorAll('[data-abmm-section-toggle]').forEach(function (btn) {
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				var section = closest(btn, '.abmm-mega__section');
				if (section) setSectionCollapsed(section, btn.getAttribute('aria-expanded') === 'true');
			});
		});

		header.addEventListener('click', function (e) {
			var back = e.target.closest ? e.target.closest('[data-abmm-back]') : null;
			if (!back || !header.contains(back)) return;
			e.preventDefault();
			e.stopPropagation();
			var item = closest(back, '.abmm-nav__item--mega');
			if (item) drillBack(item);
		});

		var toggle = header.querySelector('.abmm-nav__toggle');
		// Dismiss before native navigation so a slow response does not leave the
		// drawer covering the page. Keep drill controls and new-tab clicks intact.
		header.addEventListener('click', function (e) {
			if (!header.classList.contains('is-mobile-open') || e.defaultPrevented) return;
			if (e.button > 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
			var link = closest(e.target, 'a[href]');
			if (!link || !header.contains(link)) return;
			if (link.matches('[data-abmm-mega-trigger], [data-abmm-back], [data-abmm-drawer-close]')) return;
			var href = (link.getAttribute('href') || '').trim();
			var target = (link.getAttribute('target') || '').toLowerCase();
			if (!href || href === '#' || /^(javascript|data):/i.test(href)) return;
			if (link.hasAttribute('download') || (target && target !== '_self' && target !== '_top' && target !== '_parent')) return;
			setDrawerOpen(header, false, false);
		});

		// Back/Forward can restore the outgoing document without reinitializing
		// its scripts. Never restore a stale drawer or its scroll/stacking locks.
		window.addEventListener('pagehide', function () {
			setDrawerOpen(header, false, false);
		});
		window.addEventListener('pageshow', function (e) {
			if (e.persisted) setDrawerOpen(header, false, false);
		});

		if (toggle) {
			toggle.addEventListener('click', function (e) {
				e.stopPropagation();
				var open = toggle.getAttribute('aria-expanded') !== 'true';
				setDrawerOpen(header, open, true);
			});
		}

		header.addEventListener('click', function (e) {
			var closeBtn = e.target.closest ? e.target.closest('[data-abmm-drawer-close]') : null;
			if (!closeBtn || !header.contains(closeBtn)) return;
			e.preventDefault();
			e.stopPropagation();
			setDrawerOpen(header, false, true);
		});

		var backdrop = header.querySelector('[data-abmm-backdrop]');
		if (backdrop) {
			backdrop.addEventListener('click', function () {
				setDrawerOpen(header, false, true);
			});
		}

		document.addEventListener('keydown', function (e) {
			var focusedHeader = closest(document.activeElement, '.abmm-header');
			if (focusedHeader && focusedHeader !== header) return;
			if (e.key === 'Tab' && isMobile(header) && header.classList.contains('is-mobile-open')) {
				var drawer = header.querySelector('.abmm-nav__drawer');
				// The bar stays above the open drawer and its toggle is the close
				// button, so keep the bar's controls in the focus loop.
				var bar = header.querySelector('.abmm-nav__bar');
				var focusable = (bar ? visibleFocusable(bar) : []).concat(visibleFocusable(drawer));
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
				var openItem = header.querySelector('.abmm-nav__item--mega.is-open');
				if (openItem) {
					drillBack(openItem);
					return;
				}
			}
			var openTrigger = header.querySelector('.abmm-nav__item--mega.is-open [data-abmm-mega-trigger]');
			closeAllMegas(header);
			if (openTrigger && typeof openTrigger.focus === 'function') openTrigger.focus();
			if (header.classList.contains('is-mobile-open')) {
				setDrawerOpen(header, false, true);
			}
		});

		function isOutsideHeaderEvent(e) {
			return !header.contains(e.target) && !closest(e.target, '.abmm-header');
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
				header.querySelector('.abmm-nav__item--mega.is-open:not([data-drill])')
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

		window.addEventListener('scroll', function () {
			if (header.classList.contains('abmm-has-open-menu') && !isMobile(header)) syncScrim(header);
		}, { passive: true });

		if (window.ResizeObserver) {
			var geometryObserver = new ResizeObserver(function () {
				updateMobileGeometry(header);
				updateOverflowRisk(header);
				repositionOpenPanels(header);
			});
			geometryObserver.observe(header.querySelector('.abmm-nav__bar') || header);
			header.querySelectorAll('.abmm-mega__panel').forEach(function (panel) {
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
		if (root.nodeType === 1 && root.matches && root.matches('.abmm-header')) {
			initHeader(root);
		}
		if (root.querySelectorAll) {
			root.querySelectorAll('.abmm-header').forEach(initHeader);
		}
	}

	function init() {
		initWithin(document);
	}




	function observeDynamicHeaders() {
		if (!window.MutationObserver || document.__ashbiMegaMenuObserverActive === true) return;
		document.__ashbiMegaMenuObserverActive = true;

		var observer = new MutationObserver(function (records) {
			records.forEach(function (record) {
				record.addedNodes.forEach(initWithin);
			});
		});
		observer.observe(document.documentElement, { childList: true, subtree: true });
	}


	window.ABMMMenuSearch = { init: initMenuSearch };
	// The builder reuses search while retaining its own preview interactions.
	if (window.abmmRuntime && window.abmmRuntime.adminPreview) return;
	observeDynamicHeaders();

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
