/**
 * Product Navigation, Product Branding and Navigation Coverage screens:
 * "More actions" menus, the logo picker and the live coverage filter.
 * Every feature also works without this script.
 */
(function () {
	'use strict';

	var i18n = (window.wp && window.wp.i18n) || {};
	var __ = i18n.__ || function (text) { return text; };
	var _n = i18n._n || function (single, plural, number) { return number === 1 ? single : plural; };
	var sprintf = i18n.sprintf || function (format, value) { return String(format).replace('%d', value); };

	/* ---- "More actions" menus ---- */

	function menuItems(wrap) {
		return Array.prototype.slice.call(wrap.querySelectorAll('[role="menuitem"]'));
	}

	function closeMenu(wrap, focusToggle) {
		var toggle = wrap.querySelector('.abmm-card-menu__toggle');
		var list = wrap.querySelector('.abmm-card-menu__list');
		wrap.classList.remove('is-open');
		if (list) {
			list.hidden = true;
		}
		if (toggle) {
			toggle.setAttribute('aria-expanded', 'false');
			if (focusToggle) {
				toggle.focus();
			}
		}
	}

	function closeAllMenus(except) {
		document.querySelectorAll('.abmm-card-menu.is-open').forEach(function (wrap) {
			if (wrap !== except) {
				closeMenu(wrap, false);
			}
		});
	}

	function openMenu(wrap, focusLast) {
		closeAllMenus(wrap);
		var list = wrap.querySelector('.abmm-card-menu__list');
		if (!list) {
			return;
		}
		wrap.classList.add('is-open');
		list.hidden = false;
		wrap.querySelector('.abmm-card-menu__toggle').setAttribute('aria-expanded', 'true');
		var items = menuItems(wrap);
		if (items.length) {
			items[focusLast ? items.length - 1 : 0].focus();
		}
	}

	document.addEventListener('click', function (event) {
		var toggle = event.target.closest('.abmm-card-menu__toggle');
		if (toggle) {
			var wrap = toggle.closest('.abmm-card-menu');
			if (wrap.classList.contains('is-open')) {
				closeMenu(wrap, false);
			} else {
				openMenu(wrap, false);
			}
			return;
		}
		if (!event.target.closest('.abmm-card-menu')) {
			closeAllMenus(null);
		}
	});

	document.addEventListener('keydown', function (event) {
		var toggle = event.target.closest && event.target.closest('.abmm-card-menu__toggle');
		if (toggle && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
			event.preventDefault();
			openMenu(toggle.closest('.abmm-card-menu'), event.key === 'ArrowUp');
			return;
		}
		var list = event.target.closest && event.target.closest('.abmm-card-menu__list');
		if (!list) {
			return;
		}
		var wrap = list.closest('.abmm-card-menu');
		var items = menuItems(wrap);
		var index = items.indexOf(document.activeElement);
		if (event.key === 'Escape') {
			event.preventDefault();
			closeMenu(wrap, true);
		} else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
			event.preventDefault();
			var step = event.key === 'ArrowDown' ? 1 : -1;
			items[(index + step + items.length) % items.length].focus();
		} else if (event.key === 'Home' || event.key === 'End') {
			event.preventDefault();
			items[event.key === 'Home' ? 0 : items.length - 1].focus();
		} else if (event.key === 'Tab') {
			closeMenu(wrap, false);
		}
	});

	// Destructive links ask first. The server still checks the nonce and revision.
	document.addEventListener('click', function (event) {
		var link = event.target.closest('[data-abmm-confirm]');
		if (link && !window.confirm(link.getAttribute('data-abmm-confirm'))) {
			event.preventDefault();
		}
	});

	/* ---- Logo picker ---- */

	function mediaParts(field) {
		return {
			preview: field.querySelector('[data-abmm-media-preview]'),
			image: field.querySelector('[data-abmm-media-image]'),
			drop: field.querySelector('[data-abmm-media-drop]'),
			actions: field.querySelector('[data-abmm-media-actions]'),
			url: field.querySelector('input[type="url"]')
		};
	}

	function syncMediaField(field) {
		var parts = mediaParts(field);
		if (!parts.url) {
			return;
		}
		var value = parts.url.value.trim();
		var has = value !== '';
		field.classList.toggle('has-image', has);
		if (parts.image) {
			if (has) {
				parts.image.src = value;
			} else {
				parts.image.removeAttribute('src');
			}
		}
		if (parts.preview) {
			parts.preview.hidden = !has;
		}
		if (parts.actions) {
			parts.actions.hidden = !has;
		}
		if (parts.drop) {
			parts.drop.hidden = has;
		}
	}

	function openMediaFrame(trigger, files) {
		if (!window.wp || !window.wp.media) {
			// Without the Media Library, typing a URL still works.
			var field = trigger.closest('[data-abmm-media-field]');
			var details = field && field.querySelector('details');
			if (details) {
				details.open = true;
				details.querySelector('input').focus();
			}
			return;
		}
		var target = document.getElementById(trigger.getAttribute('data-abmm-media-target'));
		var idTarget = document.getElementById(trigger.getAttribute('data-abmm-media-id-target'));
		if (!target) {
			return;
		}
		var frame = window.wp.media({
			title: __('Choose a logo', 'ashbi-mega-menu'),
			button: { text: __('Use this image', 'ashbi-mega-menu') },
			multiple: false,
			library: { type: 'image' }
		});
		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			target.value = attachment.url || '';
			if (idTarget) {
				idTarget.value = attachment.id || '';
			}
			var field = target.closest('[data-abmm-media-field]');
			syncMediaField(field);
			var replace = field.querySelector('[data-abmm-media-actions] [data-abmm-media-target]');
			if (replace) {
				replace.focus();
			}
		});
		frame.open();
		// A file dropped on the picker goes straight into the Media Library upload.
		if (files && files.length) {
			try {
				frame.uploader.uploader.uploader.addFile(files[0]);
			} catch (error) {
				// The frame is open; the file can be dropped again inside it.
			}
		}
	}

	document.addEventListener('click', function (event) {
		var choose = event.target.closest('[data-abmm-media-target]');
		if (choose) {
			event.preventDefault();
			openMediaFrame(choose, null);
			return;
		}
		var clear = event.target.closest('[data-abmm-media-clear]');
		if (clear) {
			event.preventDefault();
			var urlField = document.getElementById(clear.getAttribute('data-abmm-media-clear'));
			var idField = document.getElementById(clear.getAttribute('data-abmm-media-id-clear'));
			if (idField) {
				idField.value = '';
			}
			if (urlField) {
				urlField.value = '';
				var field = urlField.closest('[data-abmm-media-field]');
				syncMediaField(field);
				var drop = field.querySelector('[data-abmm-media-drop]');
				if (drop) {
					drop.focus();
				}
			}
		}
	});

	['dragenter', 'dragover'].forEach(function (type) {
		document.addEventListener(type, function (event) {
			var drop = event.target.closest && event.target.closest('[data-abmm-media-drop]');
			if (drop) {
				event.preventDefault();
				drop.classList.add('is-dragover');
			}
		});
	});
	['dragleave', 'drop'].forEach(function (type) {
		document.addEventListener(type, function (event) {
			var drop = event.target.closest && event.target.closest('[data-abmm-media-drop]');
			if (!drop) {
				return;
			}
			drop.classList.remove('is-dragover');
			if (type === 'drop') {
				event.preventDefault();
				openMediaFrame(drop, event.dataTransfer && event.dataTransfer.files);
			}
		});
	});

	document.addEventListener('input', function (event) {
		if (!event.target.matches('input[type="url"][id^="abmm-product-logo-"]')) {
			return;
		}
		// A typed URL wins over a previously chosen Media Library image.
		var idField = document.getElementById(event.target.id.replace(/-url$/, '-id'));
		if (idField) {
			idField.value = '';
		}
		syncMediaField(event.target.closest('[data-abmm-media-field]'));
	});

	function syncBrandingMode() {
		var mode = document.querySelector('[data-abmm-branding-mode]');
		document.querySelectorAll('[data-abmm-product-text-branding]').forEach(function (fieldset) {
			fieldset.hidden = !mode || mode.value !== 'product_text';
		});
	}

	document.addEventListener('change', function (event) {
		if (event.target.matches('[data-abmm-branding-mode]')) {
			syncBrandingMode();
		}
	});

	document.querySelectorAll('[data-abmm-media-field]').forEach(function (field) {
		var image = field.querySelector('[data-abmm-media-image]');
		if (image) {
			// A broken URL shows the empty picker instead of a broken image icon.
			image.addEventListener('error', function () {
				if (image.getAttribute('src')) {
					field.classList.add('is-broken');
				}
			});
			image.addEventListener('load', function () {
				field.classList.remove('is-broken');
			});
		}
	});
	syncBrandingMode();

	/* ---- Product links: show used rows, reveal the rest one at a time ---- */

	var links = document.querySelector('[data-abmm-links]');
	if (links) {
		var linkRows = Array.prototype.slice.call(links.querySelectorAll('[data-abmm-link-row]'));
		var linksEmpty = links.querySelector('[data-abmm-links-empty]');
		var linksActions = document.querySelector('[data-abmm-links-actions]');
		var addLink = document.querySelector('[data-abmm-add-link]');
		var linksNote = document.querySelector('[data-abmm-links-note]');

		var syncLinks = function () {
			var shown = linkRows.filter(function (row) { return !row.hidden; }).length;
			links.classList.toggle('is-empty', shown === 0);
			if (linksEmpty) {
				linksEmpty.hidden = shown > 0;
			}
			if (addLink) {
				addLink.hidden = shown >= linkRows.length;
			}
			if (linksNote) {
				linksNote.textContent = shown === 0 ? '' : shown >= linkRows.length ?
					__('That is the maximum of six links.', 'ashbi-mega-menu') :
					/* translators: %d: Number of links that can still be added. */
					sprintf(_n('%d more link available', '%d more links available', linkRows.length - shown, 'ashbi-mega-menu'), linkRows.length - shown);
			}
		};

		linkRows.forEach(function (row, index) {
			row.hidden = !row.hasAttribute('data-abmm-filled');
			var remove = document.createElement('button');
			remove.type = 'button';
			remove.className = 'abmm-profile-admin__remove-link';
			/* translators: %d: Link position. */
			remove.setAttribute('aria-label', sprintf(__('Remove link %d', 'ashbi-mega-menu'), index + 1));
			remove.title = __('Remove link', 'ashbi-mega-menu');
			remove.innerHTML = '<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>';
			remove.addEventListener('click', function () {
				row.querySelectorAll('input').forEach(function (input) { input.value = ''; });
				row.hidden = true;
				// Empty rows are skipped on save, so moving this one last keeps the order tidy.
				links.insertBefore(row, linksEmpty);
				linkRows.splice(linkRows.indexOf(row), 1);
				linkRows.push(row);
				syncLinks();
				if (addLink && !addLink.hidden) {
					addLink.focus();
				}
			});
			row.appendChild(remove);
		});

		if (addLink) {
			addLink.addEventListener('click', function () {
				var next = linkRows.filter(function (row) { return row.hidden; })[0];
				if (!next) {
					return;
				}
				next.hidden = false;
				syncLinks();
				var first = next.querySelector('input');
				if (first) {
					first.focus();
				}
			});
		}
		if (linksActions) {
			linksActions.hidden = false;
		}
		links.classList.add('is-enhanced');
		syncLinks();
	}

	/* ---- Navigation Coverage: live filter ---- */

	var filters = document.querySelector('[data-abmm-coverage-filters]');
	if (filters && filters.getAttribute('data-live') === '1') {
		var search = filters.querySelector('[data-abmm-coverage-search]');
		var sourceInput = filters.querySelector('[data-abmm-source-input]');
		var chips = Array.prototype.slice.call(filters.querySelectorAll('[data-abmm-source-chip]'));
		var rows = Array.prototype.slice.call(document.querySelectorAll('.abmm-profile-coverage tbody tr[data-source]'));
		var empty = document.querySelector('[data-abmm-coverage-empty]');
		var count = document.querySelector('[data-abmm-coverage-count]');
		var pages = document.querySelector('[data-abmm-coverage-pages]');
		var timer = null;

		var apply = function () {
			var term = search.value.trim().toLowerCase();
			var source = sourceInput.value || 'all';
			var visible = 0;
			rows.forEach(function (row) {
				var show = (source === 'all' || row.getAttribute('data-source') === source) &&
					(term === '' || row.getAttribute('data-title').indexOf(term) !== -1);
				row.hidden = !show;
				if (show) {
					visible++;
				}
			});
			if (empty) {
				empty.hidden = visible > 0;
			}
			if (pages) {
				// Live results list every match, so server-side paging no longer applies.
				pages.hidden = true;
			}
			if (count) {
				/* translators: %d: Matching page count. */
				count.textContent = sprintf(_n('%d matching page', '%d matching pages', visible, 'ashbi-mega-menu'), visible);
			}
			chips.forEach(function (chip) {
				var current = chip.getAttribute('data-abmm-source-chip') === source;
				chip.classList.toggle('is-current', current);
				if (current) {
					chip.setAttribute('aria-current', 'true');
				} else {
					chip.removeAttribute('aria-current');
				}
			});
			// Keep the address shareable and the Back button sensible.
			if (window.history && window.history.replaceState && window.URL) {
				var url = new window.URL(window.location.href);
				url.searchParams.set('abmm_source', source);
				if (term) {
					url.searchParams.set('abmm_search', search.value.trim());
				} else {
					url.searchParams.delete('abmm_search');
				}
				url.searchParams.delete('abmm_page');
				try {
					window.history.replaceState(null, '', url.toString());
				} catch (error) {
					// Some embedded previews block history changes; filtering still works.
				}
			}
		};

		chips.forEach(function (chip) {
			chip.addEventListener('click', function (event) {
				event.preventDefault();
				sourceInput.value = chip.getAttribute('data-abmm-source-chip');
				apply();
			});
		});
		search.addEventListener('input', function () {
			window.clearTimeout(timer);
			timer = window.setTimeout(apply, 120);
		});
		filters.addEventListener('submit', function (event) {
			event.preventDefault();
			window.clearTimeout(timer);
			apply();
		});
	}
}());
