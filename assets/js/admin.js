/**
 * Ashbi Mega Menu — Admin Builder
 */
(function ($) {
	'use strict';

	var S = abmmAdmin.strings;
	var wpI18n = (window.wp && window.wp.i18n) || {};
	var __ = wpI18n.__ || function (text) { return text; };
	var _n = wpI18n._n || function (single, plural, number) { return number === 1 ? single : plural; };
	var sprintf = wpI18n.sprintf || function (format) {
		var args = Array.prototype.slice.call(arguments, 1);
		var next = 0;
		return String(format).replace(/%(\d+\$)?[sd]/g, function (match, position) {
			return String(position ? args[parseInt(position, 10) - 1] : args[next++]);
		});
	};
	var state = {
		menuId: '',
		menu: null,
		editingItemIndex: -1,
		editingCatIndex: -1,
		iconTarget: null, // { type: 'cat'|'link', catIndex, linkIndex }
		wpLink: null, // { $url, $text }
		savedSnapshot: '',
		trackingChanges: false,
		loadFailed: false,
		isSaving: false,
		saveState: 'saved',
		saveMessage: '',
		serverRevision: '',
		draftTimer: null,
		draftConflict: false,
		pendingExitUrl: '',
		previewViewport: 'desktop',
		previewDrill: 'root',
		previewItemIndex: -1,
		previewCategoryId: '',
		previewMobileOpen: false,
		builderSection: 'content',
		builderPanelOpener: null,
		megaEditorOpener: null,
		activeModal: '',
		modalOpener: null,
	};

	function uid() {
		return 'id_' + Math.random().toString(36).slice(2, 9);
	}

	function duplicateMenuItem(item) {
		var copy = JSON.parse(JSON.stringify(item || {}));
		copy.id = uid();
		/* translators: %s: name of the copied menu item. */
		copy.label = sprintf(__('%s copy', 'ashbi-mega-menu'), String(copy.label || __('Menu item', 'ashbi-mega-menu')).trim());
		(copy.links || []).forEach(function (link) { link.id = uid(); });
		(copy.categories || []).forEach(function (category) {
			category.id = uid();
			(category.groups || []).forEach(function (group) {
				group.id = uid();
				(group.links || []).forEach(function (link) { link.id = uid(); });
			});
		});
		return copy;
	}

	// A URL field with the WordPress link picker. Pass `optionsLink` to add the
	// link options popover (new tab, rel, classes) as a third control.
	function wpLinkFieldHtml(urlValue, urlClass, textSelector, disabled, optionsLink) {
		return (
			'<div class="abmm-wp-link' + (optionsLink ? ' has-link-options' : '') + '">' +
			'<input type="text" class="' +
			esc(urlClass) +
			' abmm-url-field" value="' +
			esc(urlValue || '') +
			'" placeholder="' +
			esc(S.linkUrl) +
			'"' +
			(disabled ? ' disabled' : '') +
			' />' +
			'<button type="button" class="button abmm-pick-url" aria-label="' + esc(S.selectLink) + '" title="' +
			esc(S.selectLink) +
			'"' +
			(disabled ? ' disabled' : '') +
			(textSelector ? ' data-abmm-text-sel="' + esc(textSelector) + '"' : '') +
			'>' +
			'<span class="dashicons dashicons-admin-links" aria-hidden="true"></span>' +
			'</button>' +
			(optionsLink ? linkOptionsHtml(optionsLink === true ? {} : optionsLink) : '') +
			'</div>'
		);
	}

	var popoverSeq = 0;

	function linkHasOptions(link) {
		return !!(link && (link.target === '_blank' || String(link.rel || '').trim() || String(link.class || '').trim()));
	}

	// Link options live in a small popover off the URL field, not in a box under every row.
	function linkOptionsHtml(link) {
		link = link || {};
		var id = 'abmm-link-options-' + (++popoverSeq);
		return (
			'<div class="abmm-link-options abmm-popover' + (linkHasOptions(link) ? ' has-options' : '') + '">' +
			'<button type="button" class="abmm-link-options__toggle abmm-popover__toggle" aria-expanded="false" aria-controls="' + id + '" title="' + esc(__('Link options', 'ashbi-mega-menu')) + '">' +
			iconButtonContent('admin-generic', __('Link options', 'ashbi-mega-menu')) +
			'</button>' +
			'<div class="abmm-link-options__panel abmm-popover__panel" id="' + id + '" role="group" aria-label="' + esc(__('Link options', 'ashbi-mega-menu')) + '" hidden>' +
			'<p class="abmm-popover__title" aria-hidden="true">' + esc(__('Link options', 'ashbi-mega-menu')) + '</p>' +
			'<label class="abmm-popover__check"><input type="checkbox" class="abmm-link-target"' +
			(link.target === '_blank' ? ' checked' : '') +
			' /> ' + esc(__('Open in new tab', 'ashbi-mega-menu')) + '</label>' +
			'<label class="abmm-popover__field"><span class="abmm-popover__label">' + esc(__('Relationship', 'ashbi-mega-menu')) + '</span><input type="text" class="abmm-link-rel" value="' +
			esc(link.rel || '') +
			'" placeholder="nofollow sponsored" /></label>' +
			'<label class="abmm-popover__field"><span class="abmm-popover__label">' + esc(__('CSS classes', 'ashbi-mega-menu')) + '</span><input type="text" class="abmm-link-class" value="' +
			esc(link.class || '') +
			'" /></label></div></div>'
		);
	}

	// A heading link (category panel or column heading) behind one link button.
	function headingLinkPopoverHtml(wrapClass, urlClass, textSelector, link, url) {
		var id = 'abmm-heading-link-' + (++popoverSeq);
		var hasUrl = String(url || '').trim() !== '';
		return (
			'<div class="abmm-heading-link abmm-popover ' + esc(wrapClass) + (hasUrl ? ' has-value' : '') + '">' +
			'<button type="button" class="abmm-heading-link__toggle abmm-popover__toggle" aria-expanded="false" aria-controls="' + id + '" title="' + esc(__('Heading link', 'ashbi-mega-menu')) + '">' +
			iconButtonContent('admin-links', __('Heading link', 'ashbi-mega-menu')) +
			'</button>' +
			'<div class="abmm-heading-link__panel abmm-popover__panel" id="' + id + '" role="group" aria-label="' + esc(__('Heading link', 'ashbi-mega-menu')) + '" hidden>' +
			'<p class="abmm-popover__title" aria-hidden="true">' + esc(__('Heading link', 'ashbi-mega-menu')) + '</p>' +
			'<p class="abmm-popover__hint">' + esc(__('Optional. Makes the heading itself clickable.', 'ashbi-mega-menu')) + '</p>' +
			wpLinkFieldHtml(url || '', urlClass, textSelector, false) +
			'<label class="abmm-popover__check"><input type="checkbox" class="abmm-link-target"' +
			(link && link.target === '_blank' ? ' checked' : '') +
			' /> ' + esc(__('Open in new tab', 'ashbi-mega-menu')) + '</label>' +
			'<label class="abmm-popover__field"><span class="abmm-popover__label">' + esc(__('Relationship', 'ashbi-mega-menu')) + '</span><input type="text" class="abmm-link-rel" value="' +
			esc((link && link.rel) || '') +
			'" placeholder="nofollow sponsored" /></label>' +
			'<label class="abmm-popover__field"><span class="abmm-popover__label">' + esc(__('CSS classes', 'ashbi-mega-menu')) + '</span><input type="text" class="abmm-link-class" value="' +
			esc((link && link.class) || '') +
			'" /></label></div></div>'
		);
	}

	function closePopover($popover, returnFocus) {
		if (!$popover || !$popover.length) return;
		$popover.removeClass('is-open');
		$popover.find('> .abmm-popover__panel').prop('hidden', true);
		var $toggle = $popover.find('> .abmm-popover__toggle').attr('aria-expanded', 'false');
		if (returnFocus) $toggle.trigger('focus');
	}

	function openPopover($popover) {
		$('.abmm-popover.is-open').not($popover).each(function () { closePopover($(this), false); });
		$popover.addClass('is-open');
		var $panel = $popover.find('> .abmm-popover__panel').prop('hidden', false);
		$popover.find('> .abmm-popover__toggle').attr('aria-expanded', 'true');
		// Open upwards when there is no room below, e.g. near the bottom of the inspector.
		$popover.removeClass('is-above');
		var panel = $panel[0];
		if (panel && panel.getBoundingClientRect) {
			var rect = panel.getBoundingClientRect();
			if (rect.height && rect.bottom > window.innerHeight - 8 && rect.top - rect.height > 120) $popover.addClass('is-above');
		}
		setTimeout(function () {
			$panel.find('input, button, select, textarea').filter(':visible').first().trigger('focus');
		}, 0);
	}

	// Popovers follow the disclosure pattern: the button toggles, Escape closes
	// and returns focus, and clicking or tabbing away closes.
	function initPopovers() {
		$(document).on('click', '.abmm-popover__toggle', function (event) {
			event.preventDefault();
			var $popover = $(this).closest('.abmm-popover');
			if ($popover.hasClass('is-open')) closePopover($popover, false);
			else openPopover($popover);
		});
		// Bound below the document so stopping propagation keeps the sheet's own
		// Escape handler (on the document) from closing the whole sheet.
		$('#abmm-builder').on('keydown', '.abmm-popover', function (event) {
			if (event.key !== 'Escape' || !$(this).hasClass('is-open') || wordpressDialogIsOpen()) return;
			event.preventDefault();
			// Escape closes only the popover, not the sheet or dialog around it.
			event.stopPropagation();
			closePopover($(this), true);
		});
		$(document).on('focusout', '.abmm-popover.is-open', function (event) {
			var popover = this;
			var next = event.relatedTarget;
			if (!next || $.contains(popover, next) || wordpressDialogIsOpen()) return;
			closePopover($(popover), false);
		});
		$(document).on('mousedown', function (event) {
			if (wordpressDialogIsOpen()) return;
			$('.abmm-popover.is-open').each(function () {
				if (!$.contains(this, event.target)) closePopover($(this), false);
			});
		});
		$(document).on('input change', '.abmm-link-options input', function () {
			var $popover = $(this).closest('.abmm-link-options');
			$popover.toggleClass('has-options', $popover.find('.abmm-link-target').is(':checked') ||
				String($popover.find('.abmm-link-rel').val() || '').trim() !== '' ||
				String($popover.find('.abmm-link-class').val() || '').trim() !== '');
		});
		$(document).on('input change', '.abmm-link-edit input', function () {
			syncLinkChip($(this).closest('.abmm-link-edit'));
		});
		$(document).on('input change', '.abmm-heading-link .abmm-url-field', function () {
			$(this).closest('.abmm-heading-link').toggleClass('has-value', String($(this).val() || '').trim() !== '');
		});
	}

	// "More actions" menu in the top bar (Export and help).
	function initMoreMenu() {
		var $wrap = $('.abmm-more-menu');
		if (!$wrap.length) return;
		var $toggle = $wrap.find('.abmm-more-menu__toggle');
		var $list = $wrap.find('.abmm-more-menu__list');
		function items() { return $list.find('[role="menuitem"]'); }
		function close(returnFocus) {
			$list.prop('hidden', true);
			$wrap.removeClass('is-open');
			$toggle.attr('aria-expanded', 'false');
			if (returnFocus) $toggle.trigger('focus');
		}
		function open(focusLast) {
			$list.prop('hidden', false);
			$wrap.addClass('is-open');
			$toggle.attr('aria-expanded', 'true');
			var $items = items();
			($items.length ? $items.eq(focusLast ? $items.length - 1 : 0) : $toggle).trigger('focus');
		}
		$toggle.on('click', function () {
			if ($wrap.hasClass('is-open')) close(false);
			else open(false);
		});
		$toggle.on('keydown', function (event) {
			if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
				event.preventDefault();
				open(event.key === 'ArrowUp');
			}
		});
		$list.on('keydown', function (event) {
			var $items = items();
			var index = $items.index(document.activeElement);
			if (event.key === 'Escape') {
				event.preventDefault();
				event.stopPropagation();
				close(true);
			} else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
				event.preventDefault();
				index = (index + (event.key === 'ArrowDown' ? 1 : -1) + $items.length) % $items.length;
				$items.eq(index).trigger('focus');
			} else if (event.key === 'Home' || event.key === 'End') {
				event.preventDefault();
				$items.eq(event.key === 'Home' ? 0 : $items.length - 1).trigger('focus');
			} else if (event.key === 'Tab') {
				close(false);
			}
		});
		$list.on('click', '[role="menuitem"]', function () { close(true); });
		$(document).on('mousedown', function (event) {
			if ($wrap.hasClass('is-open') && !$.contains($wrap[0], event.target)) close(false);
		});
	}

	function readLinkOptions($scope, link) {
		link.target = $scope.find('.abmm-link-target').is(':checked') ? '_blank' : '';
		link.rel = $scope.find('.abmm-link-rel').val() || '';
		link.class = $scope.find('.abmm-link-class').val() || '';
	}

	function initWpLink() {
		$(document).on('click', '.abmm-pick-url', function (e) {
			e.preventDefault();
			e.stopPropagation();
			if ($(this).prop('disabled')) return;

			var $btn = $(this);
			var $wrap = $btn.closest('.abmm-wp-link');
			var $url = $wrap.find('.abmm-url-field').first();
			var $text = null;

			if ($btn.data('abmm-text')) {
				$text = $($btn.data('abmm-text'));
			} else if ($btn.data('abmm-text-sel')) {
				var $row = $btn.closest('.abmm-link-row, .abmm-nav-item-row');
				$text = $row.find($btn.data('abmm-text-sel')).first();
			} else {
				var $rowFallback = $btn.closest('.abmm-link-row, .abmm-nav-item-row');
				$text = $rowFallback.find('.abmm-link-label, .abmm-nav-label').first();
			}

			openWpLink($url, $text.length ? $text : null);
		});

		// Capture-phase so we run before core wplink handlers.
		document.addEventListener(
			'click',
			function (e) {
				var submit = e.target && e.target.closest ? e.target.closest('#wp-link-submit') : null;
				if (!submit || !state.wpLink) return;

				e.preventDefault();
				e.stopImmediatePropagation();

				var urlInput = document.getElementById('wp-link-url');
				var textInput = document.getElementById('wp-link-text');
				var url = urlInput ? urlInput.value : '';
				var text = textInput ? textInput.value : '';

				if (state.wpLink.$url && state.wpLink.$url.length) {
					state.wpLink.$url.val(url).trigger('input').trigger('change');
				}
				if (text && state.wpLink.$text && state.wpLink.$text.length) {
					state.wpLink.$text.val(text).trigger('input').trigger('change');
				}

				if (typeof wpLink !== 'undefined' && wpLink.close) {
					wpLink.close();
				}
				state.wpLink = null;
			},
			true
		);

		$(document).on('wplink-close', function () {
			state.wpLink = null;
		});
	}

	function openWpLink($url, $text) {
		if (typeof wpLink === 'undefined' || !wpLink.open) {
			setStatus(__('The WordPress link dialog is not available.', 'ashbi-mega-menu'), true);
			return;
		}

		state.wpLink = { $url: $url, $text: $text || null };

		if (!$('#abmm-wplink-textarea').length) {
			$('body').append(
				'<textarea id="abmm-wplink-textarea" style="display:none;" aria-hidden="true"></textarea>'
			);
		}

		window.wpActiveEditor = 'abmm-wplink-textarea';
		wpLink.open(
			'abmm-wplink-textarea',
			($url && $url.val()) || '',
			($text && $text.val()) || ''
		);

		setTimeout(function () {
			var urlEl = document.getElementById('wp-link-url');
			var textEl = document.getElementById('wp-link-text');
			if (urlEl && $url) urlEl.value = $url.val() || '';
			if (textEl && $text) textEl.value = $text.val() || '';
			if (urlEl) urlEl.focus();
		}, 50);
	}

	function getMenu() {
		return state.menu;
	}

	function captureSavedState() {
		syncSettingsFromForm();
		state.savedSnapshot = JSON.stringify(getMenu());
		state.trackingChanges = true;
		updateSaveState('saved', S.savedState);
	}

	function hasUnsavedChanges() {
		if (!state.trackingChanges || state.loadFailed) return false;
		syncSettingsFromForm();
		return JSON.stringify(getMenu()) !== state.savedSnapshot;
	}

	function updateSaveState(status, message) {
		state.saveState = status;
		state.saveMessage = message || '';
		renderSaveState();
	}

	function renderSaveState() {
		var $el = $('.abmm-save-status');
		if (!$el.length || state.loadFailed) return;
		$el.empty()
			.removeClass('is-error is-dirty is-saving is-saved')
			.addClass('is-visible is-' + state.saveState)
			.attr('role', state.saveState === 'failed' ? 'alert' : 'status');
		$('<span class="abmm-save-status__message" />').text(state.saveMessage).appendTo($el);
		// A conflict cannot be fixed by retrying; the draft recovery panel offers reload instead.
		if (state.saveState === 'failed' && !state.draftConflict) {
			$('<button type="button" class="button-link abmm-retry-save" />')
				.text(S.retrySave)
				.on('click', saveMenu)
				.appendTo($el);
		}
		renderSaveButton();
	}

	// With nothing to save the button settles into a quiet "Saved" state;
	// any change turns it back into the primary action.
	function renderSaveButton() {
		var $button = $('.abmm-save-menu');
		if (!$button.length) return;
		var saved = state.saveState === 'saved';
		var saving = state.saveState === 'saving';
		var label = saving ? __('Saving…', 'ashbi-mega-menu') : (saved ? __('Saved', 'ashbi-mega-menu') : __('Save changes', 'ashbi-mega-menu'));
		// Update the children in place: rebuilding them between mousedown and
		// mouseup (a field's blur changes the save state) would drop the click.
		var $icon = $button.children('.dashicons');
		var $label = $button.children('.abmm-save-menu__label');
		if (!$icon.length || !$label.length) {
			$button.empty();
			$icon = $('<span class="dashicons dashicons-yes" aria-hidden="true"></span>').appendTo($button);
			$label = $('<span class="abmm-save-menu__label" />').appendTo($button);
		}
		$button.toggleClass('is-saved', saved).toggleClass('is-saving', saving);
		$icon.prop('hidden', !saved);
		$label.text(label);
		$button.attr('title', saved ? __('All changes are saved', 'ashbi-mega-menu') : __('Save this menu', 'ashbi-mega-menu'));
	}

	function markDirtyState() {
		if (!state.trackingChanges || state.loadFailed || state.isSaving) return;
		if (hasUnsavedChanges()) {
			updateSaveState('dirty', S.unsavedState);
			scheduleLocalDraft();
		} else {
			updateSaveState('saved', S.savedState);
		}
	}

	function localDraftKey() {
		return 'abmmDraft:' + state.menuId;
	}

	function scheduleLocalDraft() {
		clearTimeout(state.draftTimer);
		state.draftTimer = setTimeout(persistLocalDraft, 250);
	}

	function persistLocalDraft() {
		if (!state.menuId || state.loadFailed || !hasUnsavedChanges()) return;
		try {
			localStorage.setItem(localDraftKey(), JSON.stringify({
				revision: state.serverRevision,
				menu: getMenu(),
				updated: Date.now(),
			}));
		} catch (error) {
			// Storage can be unavailable in private or locked-down browser contexts.
		}
	}

	function removeLocalDraft() {
		try {
			localStorage.removeItem(localDraftKey());
		} catch (error) {
			// No action needed when storage is unavailable.
		}
	}

	function readLocalDraft() {
		try {
			var raw = localStorage.getItem(localDraftKey());
			return raw ? JSON.parse(raw) : null;
		} catch (error) {
			return null;
		}
	}

	function checkLocalDraft() {
		var draft = readLocalDraft();
		if (!draft || !validateBuilderMenu(draft.menu) || JSON.stringify(draft.menu) === state.savedSnapshot) return;
		var loadedRevision = state.serverRevision;
		var conflict = state.draftConflict || draft.revision !== loadedRevision;
		$('#abmm-draft-recovery').prop('hidden', false);
		$('.abmm-draft-recovery__message').text(
			conflict
				? S.draftStale
				: S.draftAvailable
		);
		$('.abmm-restore-draft').off('click').on('click', function () {
			state.menu = draft.menu;
			state.draftConflict = conflict;
			$('.abmm-save-menu').prop('disabled', conflict);
			state.serverRevision = draft.revision || state.serverRevision;
			populateFormFromMenu();
			renderNavItems();
			renderPreview();
			$('#abmm-draft-recovery').prop('hidden', !conflict);
			$('.abmm-save-menu').trigger('focus');
		});
		$('.abmm-download-draft').off('click').on('click', function () {
			var local = readLocalDraft();
			if (local && local.menu) downloadMenuJson(state.menuId + '-local-draft', JSON.stringify(local.menu, null, 2));
		});
		$('.abmm-reload-server').off('click').on('click', function () {
			persistLocalDraft(); state.trackingChanges = false; window.location.reload();
		});
		$('.abmm-discard-draft').off('click').on('click', function () {
			removeLocalDraft();
			if (state.draftConflict) { state.menu = JSON.parse(state.savedSnapshot); populateFormFromMenu(); renderNavItems(); renderPreview(); }
			state.draftConflict = false;
			state.serverRevision = loadedRevision;
			$('.abmm-save-menu').prop('disabled', false);
			$('#abmm-draft-recovery').prop('hidden', true);
			$('#abmm-menu-title').trigger('focus');
		});
	}

	function menuReadiness(menu) {
		var issues = [];
		if (!String(menu.title || '').trim()) issues.push({ field: 'abmm-menu-title', message: S.checkName });
		var usable = 0;
		var labels = {};
		function usableLink(link) {
			var url = String((link && link.url) || '').trim();
			return !!(link && String(link.label || '').trim() && url && url !== '#');
		}
		(menu.items || []).forEach(function (item, index) {
			var label = String(item.label || '').trim();
			if (!label) {
				issues.push({ field: 'abmm-nav-items', message: S.checkLabel.replace('%s', String(index + 1)) });
			} else {
				var normalizedLabel = label.toLowerCase();
				labels[normalizedLabel] = (labels[normalizedLabel] || 0) + 1;
			}
			if (item.type !== 'mega') {
				if (usableLink(item)) usable++;
				return;
			}
			var usableBefore = usable;
			(item.links || []).forEach(function (link) { if (usableLink(link)) usable++; });
			(item.categories || []).forEach(function (cat) {
				(cat.groups || []).forEach(function (group) {
					(group.links || []).forEach(function (link) { if (usableLink(link)) usable++; });
				});
			});
			if (usable === usableBefore) {
				issues.push({ field: 'abmm-nav-items', message: S.checkMega.replace('%s', label || S.thisMega) });
			}
		});
		Object.keys(labels).forEach(function (label) {
			if (labels[label] > 1) issues.push({ field: 'abmm-nav-items', message: S.checkDuplicate.replace('%s', label) });
		});
		if (!usable) issues.push({ field: 'abmm-nav-items', message: S.checkDestination });
		if (menu.cta && menu.cta.show && !usableLink(menu.cta)) {
			issues.push({ field: 'abmm-cta-url', message: S.checkCta });
		}
		(menu.items || []).forEach(function (item) {
			var label = String(item.label || '').trim();
			if (Array.from(label).length > MAX_BAR_LABEL_LENGTH) {
				var shortLabel = Array.from(label).slice(0, 24).join('') + '…';
				issues.push({ field: 'abmm-nav-items', message: (S.checkLong || 'Shorten "%s" so it fits in the menu bar.').replace('%s', shortLabel) });
			}
		});
		var brand = menu.brand || {};
		if (brand.show) {
			if (!String(brand.logo_url || '').trim() && !String(brand.logo_dark_url || '').trim() && !String(brand.title || '').trim()) {
				issues.push({ field: 'abmm-brand-title', message: S.checkBrand || 'Add a logo image or site title, or turn off "Show logo".' });
			} else if (looksLikeBrokenUrl(brand.logo_url) || looksLikeBrokenUrl(brand.logo_dark_url)) {
				issues.push({ field: 'abmm-brand-logo-url', message: S.checkLogo || 'Check the logo image link. It does not look like a web address.' });
			}
		}
		var samples = menuLinks(menu).filter(function (link) { return /#abmm-sample$/.test(String(link.url || '').trim()); }).length;
		if (samples) {
			/* translators: %d: number of template links. */
			issues.push({ field: 'abmm-nav-items', message: sprintf(_n('Replace %d sample link with your own page.', 'Replace %d sample links with your own pages.', samples, 'ashbi-mega-menu'), samples) });
		}
		menuLinks(menu).forEach(function (link) {
			if (looksLikeBrokenUrl(link.url)) {
				issues.push({ field: 'abmm-nav-items', message: (S.checkUrl || 'Check the link for "%s". It does not look like a web address.').replace('%s', String(link.label || '')) });
			}
		});
		return { ready: !issues.length, issues: issues };
	}

	// Keep in sync with ABMM_Data::MAX_BAR_LABEL_LENGTH.
	var MAX_BAR_LABEL_LENGTH = 40;

	function menuLinks(menu) {
		var links = [];
		(menu.items || []).forEach(function (item) {
			if (item.type !== 'mega') { links.push(item); return; }
			(item.links || []).forEach(function (link) { links.push(link); });
			(item.categories || []).forEach(function (cat) {
				(cat.groups || []).forEach(function (group) {
					(group.links || []).forEach(function (link) { links.push(link); });
				});
			});
		});
		if (menu.cta && menu.cta.show) links.push(menu.cta);
		return links;
	}

	// Plain text such as "about" is saved as http://about; flag it before it ships.
	// Mirrors ABMM_Data::looks_like_broken_url(), plus unsaved text with no scheme.
	function looksLikeBrokenUrl(url) {
		url = String(url || '').trim();
		if (!url || /^[#\/?]/.test(url) || /^(mailto|tel|sms):/i.test(url)) return false;
		var match = /^https?:\/\/([^\/?#:]*)/i.exec(url);
		var host;
		if (match) {
			try { host = decodeURIComponent(match[1]).toLowerCase(); } catch (e) { host = match[1].toLowerCase(); }
		} else if (/^[a-z][a-z0-9+.-]*:/i.test(url)) {
			return false;
		} else {
			host = url.split(/[\/?#]/)[0].toLowerCase();
		}
		return !host || host.indexOf(' ') !== -1 || (host.indexOf('.') === -1 && host !== 'localhost');
	}

	function renderReadiness() {
		if (!state.menu) return;
		var result = menuReadiness(getMenu());
		var $list = $('.abmm-readiness-issues').empty();
		$('.abmm-readiness-summary')
			.text(result.ready ? S.checkPassed : S.checkPending.replace('%s', String(result.issues.length)))
			.toggleClass('is-ready', result.ready);
		result.issues.forEach(function (issue) {
			$('<li><button type="button" class="button-link"></button></li>')
				.find('button').text(issue.message).attr('data-field', issue.field).end()
				.appendTo($list);
		});
		// The shortcode is always available; a draft only gets a reminder.
		$('.abmm-placement-panel').toggleClass('is-draft', !result.ready);
		$('.abmm-placement-save-hint').text(
			hasUnsavedChanges() || state.draftConflict ? S.placementUnsaved :
				(!result.ready && S.placementDraft ? S.placementDraft : S.placementSaved)
		);
		// Edit shows a tick once the saved menu passes its checklist.
		$('[data-abmm-workflow="content"]').toggleClass('is-done', result.ready && !hasUnsavedChanges());
		setBuilderSection(state.builderSection);
	}

	function setBuilderSection(section) {
		section = ['content', 'design', 'settings', 'help'].indexOf(section) !== -1 ? section : 'content';
		state.builderSection = section;
		if (section !== 'help') state.lastEditSection = section;
		$('.abmm-builder-sections [data-abmm-builder-section]').each(function () {
			var $control = $(this);
			var active = $control.data('abmm-builder-section') === section;
			$control.toggleClass('is-active', active)
				.attr('aria-selected', active ? 'true' : 'false')
				.attr('tabindex', active ? '0' : '-1');
		});
		$('.abmm-builder-section-group').each(function () {
			var $panel = $(this);
			$panel.prop('hidden', $panel.data('abmm-builder-section-panel') !== section);
		});
		// One navigation: the stepper picks the stage, the tabs only exist inside
		// Edit (Content, Design, Settings). Place and Check share the place panel.
		$('.abmm-builder-sections').prop('hidden', section === 'help');
		$('#abmm-builder-sidebar').toggleClass('is-place-step', section === 'help')
			.find('.abmm-builder-sidebar__mobile-head strong')
			.text(section === 'help' ? __('Place and check', 'ashbi-mega-menu') : __('Edit menu', 'ashbi-mega-menu'));
		var step = section === 'help' ? (state.workflowStep === 'verify' ? 'verify' : 'help') : 'content';
		$('[data-abmm-workflow]').attr('aria-current', 'false').filter('[data-abmm-workflow="' + step + '"]').attr('aria-current', 'step');
	}

	function initBuilderSections() {
		// "Place & verify" on the dashboard links straight to the Place tab.
		if (window.location.hash === '#placement') state.builderSection = 'help';
		$('.abmm-builder-sections').on('click', '[data-abmm-builder-section]', function () {
			state.workflowStep = '';
			setBuilderSection(String($(this).data('abmm-builder-section') || 'content'));
		});
		$('.abmm-builder-sections').on('keydown', '[data-abmm-builder-section]', function (event) {
			var keys = ['ArrowLeft', 'ArrowRight', 'Home', 'End'];
			if (keys.indexOf(event.key) === -1) return;
			event.preventDefault();
			var $tabs = $('.abmm-builder-sections [data-abmm-builder-section]');
			var index = $tabs.index(this);
			if (event.key === 'Home') index = 0;
			else if (event.key === 'End') index = $tabs.length - 1;
			else index = (index + (event.key === 'ArrowRight' ? 1 : -1) + $tabs.length) % $tabs.length;
			var $next = $tabs.eq(index);
			setBuilderSection(String($next.data('abmm-builder-section') || 'content'));
			$next.trigger('focus');
		});
		setBuilderSection('content');
	}

	function populateFormFromMenu() {
		var m = getMenu();
		var s = m.settings || {};
		$('#abmm-menu-title').val(m.title || '');
		$('#abmm-cta-show').prop('checked', !!(m.cta && m.cta.show));
		$('#abmm-cta-label').val((m.cta && m.cta.label) || '');
		$('#abmm-cta-url').val((m.cta && m.cta.url) || '');
		$('#abmm-cta-target').prop('checked', !!(m.cta && m.cta.target === '_blank'));
		$('#abmm-cta-rel').val((m.cta && m.cta.rel) || '');
		$('#abmm-cta-class').val((m.cta && m.cta.class) || '');
		var brand = m.brand || {};
		$('#abmm-brand-show').prop('checked', !!brand.show);
		$('#abmm-brand-title').val(brand.title || '');
		$('#abmm-brand-url').val(brand.url || '');
		$('#abmm-brand-logo-url').val(brand.logo_url || '');
		$('#abmm-brand-logo-dark-url').val(brand.logo_dark_url || '');
		$('#abmm-brand-alt').val(brand.alt || '');
		$('#abmm-brand-logo-height').val(brand.logo_height || 40);
		var values = {
			'#abmm-preset': s.preset, '#abmm-presentation': s.presentation || 'stacked', '#abmm-layout': s.layout, '#abmm-nav-align': s.nav_align,
			'#abmm-grid-cols': s.grid_columns, '#abmm-icon-size': s.icon_size, '#abmm-icon-radius': s.icon_radius,
			'#abmm-panel-width': s.panel_width, '#abmm-sidebar-width': s.sidebar_width,
			'#abmm-header-z-index': s.header_z_index,
			'#abmm-border-radius': s.border_radius, '#abmm-shadow': s.shadow,
			'#abmm-cta-style': s.cta_style, '#abmm-panel-title-align': s.panel_title_align,
		};
		Object.keys(values).forEach(function (selector) { if (values[selector] != null) $(selector).val(values[selector]); });
		var checks = {
			'#abmm-mobile-enhancements': s.mobile_enhancements,
			'#abmm-icon-inherit-text': s.icon_inherit_text, '#abmm-full-width': s.full_width,
			'#abmm-header-transparent': s.header_transparent,
			'#abmm-uppercase-cats': s.uppercase_cats, '#abmm-show-cat-desc': s.show_cat_desc,
		};
		Object.keys(checks).forEach(function (selector) { $(selector).prop('checked', !!checks[selector]); });
		var colors = {
			'#abmm-sidebar-bg': s.sidebar_bg, '#abmm-active-bg': s.active_bg, '#abmm-panel-bg': s.panel_bg,
			'#abmm-header-bg': s.header_bg, '#abmm-accent': s.accent, '#abmm-text-color': s.text_color,
			'#abmm-nav-link-color': s.nav_link_color, '#abmm-nav-hover-color': s.nav_hover_color,
			'#abmm-muted-color': s.muted_color, '#abmm-border-color': s.border_color, '#abmm-cta-text': s.cta_text,
			'#abmm-icon-color': s.icon_color, '#abmm-icon-hover-color': s.icon_hover_color,
			'#abmm-icon-active-color': s.icon_active_color, '#abmm-icon-background': s.icon_background,
			'#abmm-icon-border-color': s.icon_border_color,
		};
		Object.keys(colors).forEach(function (selector) { setColorInput(selector, colors[selector] || ''); });
		syncFullWidthField();
		syncIconInheritField();
		syncOptionalFeatureFields();
	}

	function syncSettingsFromForm() {
		var m = getMenu();
		var headerZIndex = parseInt($('#abmm-header-z-index').val(), 10);
		m.title = $('#abmm-menu-title').val();
		m.cta = {
			show: $('#abmm-cta-show').is(':checked'),
			label: $('#abmm-cta-label').val(),
			url: $('#abmm-cta-url').val(),
			target: $('#abmm-cta-target').is(':checked') ? '_blank' : '',
			rel: $('#abmm-cta-rel').val() || '',
			class: $('#abmm-cta-class').val() || '',
		};
		m.brand = {
			show: $('#abmm-brand-show').is(':checked'),
			title: $('#abmm-brand-title').val() || '',
			url: $('#abmm-brand-url').val() || '',
			logo_url: $('#abmm-brand-logo-url').val() || '',
			logo_dark_url: $('#abmm-brand-logo-dark-url').val() || '',
			alt: $('#abmm-brand-alt').val() || '',
			logo_height: parseInt($('#abmm-brand-logo-height').val(), 10) || 40,
			target: '',
			rel: '',
			class: '',
		};
		// Product profiles live in their own screen so a shared menu never needs
		// copied product fields. Preserve legacy static context data on older
		// menus until an editor deliberately replaces it with a profile.
		m.context = m.context || {};
		m.settings = {
			mobile_enhancements: $('#abmm-mobile-enhancements').is(':checked'),
			preset: $('#abmm-preset').val() || 'custom',
			// Preserve imported metadata; published headers and this preview are stacked.
			presentation: (m.settings && m.settings.presentation) || 'stacked',
			layout: $('#abmm-layout').val() || 'sidebar-left',
			sidebar_bg: $('#abmm-sidebar-bg').val(),
			active_bg: $('#abmm-active-bg').val(),
			panel_bg: $('#abmm-panel-bg').val(),
			header_bg: $('#abmm-header-bg').val(),
			header_transparent: $('#abmm-header-transparent').is(':checked'),
			header_z_index: isNaN(headerZIndex) ? 1000 : headerZIndex,
			accent: $('#abmm-accent').val(),
			text_color: $('#abmm-text-color').val(),
			nav_link_color: $('#abmm-nav-link-color').val(),
			nav_hover_color: $('#abmm-nav-hover-color').val(),
			muted_color: $('#abmm-muted-color').val(),
			border_color: $('#abmm-border-color').val(),
			cta_text: $('#abmm-cta-text').val(),
			icon_inherit_text: $('#abmm-icon-inherit-text').is(':checked'),
			icon_color: $('#abmm-icon-color').val(),
			icon_hover_color: $('#abmm-icon-hover-color').val(),
			icon_active_color: $('#abmm-icon-active-color').val(),
			icon_size: parseInt($('#abmm-icon-size').val(), 10) || 22,
			icon_background: $('#abmm-icon-background').val(),
			icon_border_color: $('#abmm-icon-border-color').val(),
			icon_radius: parseInt($('#abmm-icon-radius').val(), 10) || 0,
			grid_columns: parseInt($('#abmm-grid-cols').val(), 10) || 3,
			panel_width: parseInt($('#abmm-panel-width').val(), 10) || 1000,
			full_width: $('#abmm-full-width').is(':checked'),
			sidebar_width: parseInt($('#abmm-sidebar-width').val(), 10) || 280,
			border_radius: parseInt($('#abmm-border-radius').val(), 10) || 0,
			shadow: $('#abmm-shadow').val() || 'medium',
			nav_align: $('#abmm-nav-align').val() || 'center',
			cta_style: $('#abmm-cta-style').val() || 'rounded',
			uppercase_cats: $('#abmm-uppercase-cats').is(':checked'),
			show_cat_desc: $('#abmm-show-cat-desc').is(':checked'),
			panel_title_align: $('#abmm-panel-title-align').val() || 'center',
		};
	}

	function syncFullWidthField() {
		var on = $('#abmm-full-width').is(':checked');
		$('#abmm-panel-width').prop('disabled', on);
		$('#abmm-panel-width-field').toggleClass('is-disabled', on);
	}

	function syncIconInheritField() {
		var inherit = $('#abmm-icon-inherit-text').is(':checked');
		$('#abmm-icon-color').prop('disabled', inherit);
		$('#abmm-icon-color-field').toggleClass('is-disabled', inherit);
	}

	function syncOptionalFeatureFields() {
		[
			{ toggle: '#abmm-brand-show', panel: '#abmm-brand-show', label: __('Brand Identity', 'ashbi-mega-menu') },
			{ toggle: '#abmm-cta-show', panel: '#abmm-cta-show', label: __('Call-to-Action Button', 'ashbi-mega-menu') },
		].forEach(function (entry) {
			var $toggle = $(entry.toggle);
			var $panel = $toggle.closest('.abmm-builder-panel');
			if (!$toggle.length || !$panel.length) return;
			var enabled = $toggle.is(':checked');
			$panel.toggleClass('is-feature-disabled', !enabled);
			$panel.attr('data-feature-state', enabled ? 'enabled' : 'disabled');
			$panel.find('input, select, textarea, .abmm-pick-url').not(entry.toggle)
				.prop('disabled', !enabled);
			$panel.find('.abmm-optional-fields').prop('hidden', !enabled);
			$panel.find('.abmm-link-options').prop('hidden', !enabled);
			$panel.find('.description').first().attr('aria-live', 'polite');
		});
	}

	function setColorInput(id, value) {
		var $input = $(id);
		$input.val(value);
		if ($input.hasClass('wp-color-picker') || $input.next('.wp-picker-container').length) {
			$input.wpColorPicker('color', value);
		}
	}

	function applyPreset(key, values) {
		$('#abmm-preset').val(key);
		$('.abmm-preset-card').removeClass('is-active');
		$('.abmm-preset-card[data-preset="' + key + '"]').addClass('is-active');
		if (!values) return;
		state.applyingPreset = true;
		setTimeout(function () { state.applyingPreset = false; }, 0);
		setColorInput('#abmm-sidebar-bg', values.sidebar_bg);
		setColorInput('#abmm-active-bg', values.active_bg);
		setColorInput('#abmm-panel-bg', values.panel_bg);
		setColorInput('#abmm-header-bg', values.header_bg);
		setColorInput('#abmm-accent', values.accent);
		setColorInput('#abmm-text-color', values.text_color);
		if (values.nav_link_color) setColorInput('#abmm-nav-link-color', values.nav_link_color);
		else if (values.text_color) setColorInput('#abmm-nav-link-color', values.text_color);
		if (values.nav_hover_color) setColorInput('#abmm-nav-hover-color', values.nav_hover_color);
		else if (values.accent) setColorInput('#abmm-nav-hover-color', values.accent);
		setColorInput('#abmm-muted-color', values.muted_color);
		setColorInput('#abmm-border-color', values.border_color);
		setColorInput('#abmm-cta-text', values.cta_text);
		if (values.shadow) $('#abmm-shadow').val(values.shadow);
		if (typeof values.border_radius !== 'undefined') {
			$('#abmm-border-radius').val(values.border_radius);
		}
		syncSettingsFromForm();
		renderPreview();
	}

	function markCustomPreset() {
		// Colour pickers report preset colours as changes; only user edits make a preset custom.
		if (state.applyingPreset) return;
		$('#abmm-preset').val('custom');
		$('.abmm-preset-card').removeClass('is-active');
		$('.abmm-preset-card[data-preset="custom"]').addClass('is-active');
	}

	// The Custom card shows the menu's own colours once they differ from a
	// preset, and a quiet empty swatch until then.
	function syncCustomPresetSwatch(s) {
		var $card = $('.abmm-preset-card--custom');
		if (!$card.length) return;
		var custom = s.preset === 'custom';
		$card.toggleClass('is-empty', !custom);
		var colors = [s.header_bg, s.sidebar_bg, s.accent];
		$card.find('.abmm-preset-card__swatches span:not(.abmm-preset-card__empty)').each(function (index) {
			this.style.background = custom ? previewColorValue(colors[index], '') : '';
		});
	}

	function previewColorValue(value, fallback) {
		var candidate = String(value == null ? '' : value).trim();
		return /^(?:#[0-9a-f]{3}(?:[0-9a-f]{3})?|transparent)$/i.test(candidate) ? candidate : fallback;
	}

	function previewNumberValue(value, fallback, min, max) {
		var candidate = parseInt(value, 10);
		if (!isFinite(candidate)) return fallback;
		return Math.min(max, Math.max(min, candidate));
	}

	function settingsStyleAttr(s) {
		return (
			'--abmm-sidebar-bg:' +
			previewColorValue(s.sidebar_bg, '#f0f0f5') +
			';--abmm-active-bg:' +
			previewColorValue(s.active_bg, '#fff') +
			';--abmm-panel-bg:' +
			previewColorValue(s.panel_bg, '#fff') +
			';--abmm-header-bg:' +
			previewColorValue(s.header_bg, '#fff') +
			';--abmm-accent:' +
			previewColorValue(s.accent, '#1a73e8') +
			';--abmm-text:' +
			previewColorValue(s.text_color, '#2c2c2c') +
			';--abmm-nav-link:' +
			previewColorValue(s.nav_link_color || s.text_color, '#2c2c2c') +
			';--abmm-nav-hover:' +
			previewColorValue(s.nav_hover_color || s.accent, '#1a73e8') +
			';--abmm-muted:' +
			previewColorValue(s.muted_color, '#6b6b6b') +
			';--abmm-border:' +
			previewColorValue(s.border_color, '#e5e5ea') +
			';--abmm-cta-text:' +
			previewColorValue(s.cta_text, '#fff') +
			';--abmm-icon-default:' +
			(s.icon_inherit_text ? 'currentColor' : previewColorValue(s.icon_color || s.text_color, '#2c2c2c')) +
			';--abmm-icon-default-hover:' +
			previewColorValue(s.icon_hover_color || s.accent, '#1a73e8') +
			';--abmm-icon-default-active:' +
			previewColorValue(s.icon_active_color || s.accent, '#1a73e8') +
			';--abmm-icon-default-size:' +
			previewNumberValue(s.icon_size, 22, 12, 64) +
			'px;--abmm-icon-default-background:' +
			previewColorValue(s.icon_background, 'transparent') +
			';--abmm-icon-default-border:' +
			previewColorValue(s.icon_border_color, 'transparent') +
			';--abmm-icon-default-radius:' +
			previewNumberValue(s.icon_radius, 0, 0, 24) +
			'px' +
			';--abmm-grid-cols:' +
			previewNumberValue(s.grid_columns, 3, 1, 4) +
			';--abmm-panel-width:' +
			previewNumberValue(s.panel_width, 1000, 640, 1400) +
			'px;--abmm-sidebar-width:' +
			previewNumberValue(s.sidebar_width, 280, 180, 420) +
			'px;--abmm-radius:' +
			previewNumberValue(s.border_radius != null ? s.border_radius : 8, 8, 0, 24) +
			'px;--abmm-z-index:' +
			previewNumberValue(s.header_z_index != null ? s.header_z_index : 1000, 1000, 0, 99999) +
			';'
		);
	}

	function settingsClassList(s) {
		var classes = [
			'abmm-header',
			'abmm-preview-frame',
			'abmm-layout--' + (s.layout || 'sidebar-left'),
			'abmm-shadow--' + (s.shadow || 'medium'),
			'abmm-nav-align--' + (s.nav_align || 'center'),
			'abmm-cta--' + (s.cta_style || 'rounded'),
			'abmm-title--' + (s.panel_title_align || 'center'),
		];
		if (s.uppercase_cats) classes.push('abmm-cats-upper');
		if (s.mobile_enhancements) classes.push('abmm-mobile-enhanced');
		if (!s.show_cat_desc) classes.push('abmm-hide-cat-desc');
		if (s.full_width) classes.push('abmm-full-width');
		if (s.header_transparent) classes.push('abmm-header--transparent');
		return classes.join(' ');
	}

	function setStatus(msg, isError) {
		var $el = $('.abmm-save-status');
		if (!$el.length) return;
		clearTimeout(state.statusTimer);
		$el.text(msg).toggleClass('is-error', !!isError).addClass('is-visible');
		if (msg) {
			state.statusTimer = setTimeout(function () {
				renderSaveState();
			}, 3000);
		}
	}

	function setUndoStatus(msg, onUndo) {
		var $el = $('.abmm-save-status');
		clearTimeout(state.statusTimer);
		$el.empty().removeClass('is-error').addClass('is-visible');
		$('<span class="abmm-save-status__message" />').text(msg).appendTo($el);
		$('<button type="button" class="button-link abmm-status-undo" />').text(__('Undo', 'ashbi-mega-menu')).on('click', function () {
			clearTimeout(state.statusTimer); onUndo(); markDirtyState();
		}).appendTo($el);
		state.statusTimer = setTimeout(renderSaveState, 6000);
	}

	function responseMessage(response, fallback) {
		if (response && response.data && response.data.message) return response.data.message;
		return fallback;
	}

	function requestErrorMessage(xhr, fallback) {
		if (xhr && xhr.responseJSON) {
			return responseMessage(xhr.responseJSON, fallback);
		}
		return fallback;
	}

	function setListStatus(msg, isError, shouldFocus) {
		var $el = $('#abmm-list-status');
		if (!$el.length) return;
		$el.empty()
			.text(msg)
			.toggleClass('is-error', !!isError)
			.toggleClass('is-visible', !!msg)
			.attr('role', isError ? 'alert' : 'status');
		if (shouldFocus && msg) $el.trigger('focus');
	}

	function showClipboardFallback($button, shortcode) {
		var $fallback = $('<input type="text" class="abmm-copy-fallback" readonly />').val(shortcode).attr('aria-label', S.copyError);
		$button.closest('.abmm-menu-card__shortcode, .abmm-placement-panel').find('.abmm-copy-fallback').remove();
		$button.after($fallback);
		$fallback.trigger('focus').select();
		if (state.menuId) setStatus(S.copyError, true);
		else setListStatus(S.copyError, true, false);
	}

	function setImportStatus(msg, isError) {
		var $el = $('#abmm-import-status');
		$el.text(msg).toggleClass('is-error', !!isError).addClass('is-visible');
		if (msg) {
			setTimeout(function () {
				$el.removeClass('is-visible');
			}, 4000);
		}
	}

	function renderImportPreview(preview, onConfirm) {
		var $preview = $('#abmm-import-preview').empty().prop('hidden', false);
		$('<h4 />').text(S.importPreview).appendTo($preview);
		var $list = $('<ul class="abmm-import-preview__list" />').appendTo($preview);
		(preview.changes || []).forEach(function (change) {
			var labels = { create: __('Create', 'ashbi-mega-menu'), update: __('Update', 'ashbi-mega-menu'), create_copy: __('Create independent copy', 'ashbi-mega-menu') };
			/* translators: 1: import action, 2: menu title, 3: number of menu items. */
			$('<li />').text(sprintf(__('%1$s: %2$s (%3$s items)', 'ashbi-mega-menu'), labels[change.action] || change.action, change.title, change.item_count)).appendTo($list);
		});
		if (preview.remove_count) {
			/* translators: %s: number of menus. */
			$('<p class="description is-warning" />').text(sprintf(__('%s existing menu(s) will be removed by Replace mode.', 'ashbi-mega-menu'), preview.remove_count)).appendTo($preview);
		}
		var $actions = $('<div class="abmm-import-preview__actions" />').appendTo($preview);
		$('<button type="button" class="button button-primary" />').text(S.importConfirm).on('click', onConfirm).appendTo($actions);
		$('<button type="button" class="button" />').text(S.importCancel).on('click', function () {
			$preview.empty().prop('hidden', true);
		}).appendTo($actions);
	}

	/* ---------- List page ---------- */
	function initBackupRecovery() {
		var revision = '';
		var $list = $('#abmm-backup-list');
		var $status = $('#abmm-backup-status');
		if (!$list.length) return;
		function status(message, error) {
			$status.text(message).attr('role', error ? 'alert' : 'status');
		}
		$('.abmm-load-backups').on('click', function () {
			var $button = $(this).prop('disabled', true);
			status(S.backupLoading || 'Loading recovery snapshots…', false);
			$.post(abmmAdmin.ajaxUrl, { action: 'abmm_list_backups', nonce: abmmAdmin.nonce })
				.done(function (res) {
					if (!res || !res.success || !res.data) { status(responseMessage(res, S.backupLoadError || 'Snapshots could not be loaded.'), true); return; }
					revision = String(res.data.revision || '');
					$list.empty();
					(res.data.backups || []).forEach(function (backup) {
						var $row = $('<div class="abmm-backup-row" />').attr('data-backup-id', backup.id);
						$('<strong />').text((S.backupSummary || '%1$s — %2$s menu(s)').replace('%1$s', String(backup.created_at)).replace('%2$s', String(backup.menu_count))).appendTo($row);
						$('<button type="button" class="button abmm-export-backup" />').text(S.backupExport || 'Export snapshot').attr('data-backup-id', backup.id).appendTo($row);
						$('<button type="button" class="button abmm-restore-backup" />').text(S.backupRestore || 'Restore snapshot').attr('data-backup-id', backup.id).appendTo($row);
						$row.appendTo($list);
					});
					status($list.children().length ? (S.backupGuidance || 'Export a snapshot before restoring. Restore replaces every current menu.') : (S.backupEmpty || 'No recovery snapshots are available yet.'), false);
				}).fail(function (xhr) { status(requestErrorMessage(xhr, S.backupLoadError || 'Snapshots could not be loaded.'), true); })
				.always(function () { $button.prop('disabled', false); });
		});
		$list.on('click', '.abmm-export-backup', function () {
			var $button = $(this).prop('disabled', true);
			var id = String($button.attr('data-backup-id'));
			$.post(abmmAdmin.ajaxUrl, { action: 'abmm_export_backup', nonce: abmmAdmin.nonce, backup_id: id })
				.done(function (res) {
					if (!res || !res.success || !res.data || !res.data.menus) { status(responseMessage(res, S.backupExportError || 'Snapshot export failed.'), true); return; }
					downloadMenuJson('snapshot-' + id, JSON.stringify(res.data, null, 2));
					status(S.backupExported || 'Snapshot exported. Keep the downloaded JSON file for recovery.', false);
				}).fail(function (xhr) { status(requestErrorMessage(xhr, S.backupExportError || 'Snapshot export failed.'), true); })
				.always(function () { $button.prop('disabled', false); });
		});
		$list.on('click', '.abmm-restore-backup', function () {
			$list.find('.abmm-backup-confirm').remove();
			var $row = $(this).closest('.abmm-backup-row');
			var $confirm = $('<div class="abmm-backup-confirm" role="alert" />').appendTo($row);
			$('<p />').text(S.backupConfirmWarning || 'Replace ALL current menus with this snapshot? Current menus will be backed up first. Published placements may change. Export your current menus before continuing.').appendTo($confirm);
			$('<button type="button" class="button button-primary abmm-confirm-backup" />').text(S.backupConfirm || 'Replace menus with snapshot').appendTo($confirm).trigger('focus');
			$('<button type="button" class="button abmm-cancel-backup" />').text(S.cancel || 'Cancel').appendTo($confirm);
		});
		$list.on('click', '.abmm-cancel-backup', function () {
			var $row = $(this).closest('.abmm-backup-row');
			$row.find('.abmm-backup-confirm').remove();
			$row.find('.abmm-restore-backup').trigger('focus');
		});
		$list.on('click', '.abmm-confirm-backup', function () {
			var $row = $(this).closest('.abmm-backup-row');
			var $buttons = $list.find('button').add('.abmm-load-backups').prop('disabled', true);
			status(S.backupRestoring || 'Restoring snapshot…', false);
			$.post(abmmAdmin.ajaxUrl, { action: 'abmm_restore_backup', nonce: abmmAdmin.nonce, backup_id: $row.attr('data-backup-id'), revision: revision })
				.done(function (res) {
					if (!res || !res.success) { status(responseMessage(res, S.backupRestoreError || 'Snapshot restore failed. Reload snapshots before retrying.'), true); return; }
					status(responseMessage(res, S.backupRestored || 'Snapshot restored. Reloading menus…'), false);
					setTimeout(function () { window.location.reload(); }, 700);
				}).fail(function (xhr) { status(requestErrorMessage(xhr, S.backupRestoreError || 'Snapshot restore failed. Reload snapshots before retrying.'), true); })
				.always(function () { $buttons.prop('disabled', false); $row.find('.abmm-backup-confirm').remove(); });
		});
	}

	// Dashboard card "⋯" menus: a menu button with a small popover of actions.
	function initCardMenus() {
		function items($menu) {
			return $menu.find('[role="menuitem"]').filter(function () { return !this.disabled; });
		}
		function closeMenu($wrap, restoreFocus) {
			var $toggle = $wrap.find('.abmm-card-menu__toggle');
			$wrap.removeClass('is-open').find('.abmm-card-menu__list').prop('hidden', true);
			$toggle.attr('aria-expanded', 'false');
			if (restoreFocus) $toggle.trigger('focus');
		}
		function openMenu($wrap, focusLast) {
			$('.abmm-card-menu.is-open').not($wrap).each(function () { closeMenu($(this), false); });
			var $menu = $wrap.addClass('is-open').find('.abmm-card-menu__list').prop('hidden', false);
			$wrap.find('.abmm-card-menu__toggle').attr('aria-expanded', 'true');
			var $items = items($menu);
			$items.eq(focusLast ? $items.length - 1 : 0).trigger('focus');
		}
		$(document).on('click', '.abmm-card-menu__toggle', function () {
			var $wrap = $(this).closest('.abmm-card-menu');
			if ($wrap.hasClass('is-open')) closeMenu($wrap, true);
			else openMenu($wrap, false);
		});
		$(document).on('keydown', '.abmm-card-menu__toggle', function (e) {
			if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
			e.preventDefault();
			openMenu($(this).closest('.abmm-card-menu'), e.key === 'ArrowUp');
		});
		$(document).on('keydown', '.abmm-card-menu__list', function (e) {
			var $wrap = $(this).closest('.abmm-card-menu');
			var $items = items($(this));
			var index = $items.index(document.activeElement);
			if (e.key === 'Escape') {
				e.preventDefault();
				closeMenu($wrap, true);
			} else if (e.key === 'Tab') {
				closeMenu($wrap, false);
			} else if (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Home' || e.key === 'End') {
				e.preventDefault();
				var next = e.key === 'Home' ? 0 : (e.key === 'End' ? $items.length - 1 : index + (e.key === 'ArrowDown' ? 1 : -1));
				$items.eq((next + $items.length) % $items.length).trigger('focus');
			}
		});
		// Item handlers run first; then the menu closes and focus returns to its button.
		$(document).on('click', '.abmm-card-menu__list [role="menuitem"]', function () {
			closeMenu($(this).closest('.abmm-card-menu'), true);
		});
		$(document).on('click focusin', function (e) {
			$('.abmm-card-menu.is-open').each(function () {
				if (!$.contains(this, e.target)) closeMenu($(this), false);
			});
		});
	}

	// Dashboard search, sort and grid/list view. Only rendered for more than six menus.
	function initMenuToolbar() {
		var $toolbar = $('[data-abmm-menu-toolbar]');
		var $grid = $('[data-abmm-menu-cards]');
		if (!$toolbar.length || !$grid.length) return;
		var key = 'abmmMenuView:' + ($toolbar.attr('data-abmm-user') || '0');
		var $search = $toolbar.find('[data-abmm-menu-search]');
		var $sort = $toolbar.find('[data-abmm-menu-sort]');
		var $cards = $grid.children('.abmm-menu-card');
		var $empty = $('[data-abmm-menu-noresults]');
		var $results = $('[data-abmm-menu-results]');
		var $count = $('[data-abmm-menu-count]');
		var total = $cards.length;
		var timer = null;
		function read() {
			try { return JSON.parse(window.localStorage.getItem(key) || '{}') || {}; } catch (error) { return {}; }
		}
		function write(prefs) {
			try { window.localStorage.setItem(key, JSON.stringify(prefs)); } catch (error) { /* Private mode: the choice lasts for this visit. */ }
		}
		function setView(view) {
			view = view === 'list' ? 'list' : 'grid';
			$grid.toggleClass('is-list', view === 'list');
			$toolbar.find('[data-abmm-view]').each(function () {
				var on = $(this).attr('data-abmm-view') === view;
				$(this).toggleClass('is-current', on).attr('aria-pressed', on ? 'true' : 'false');
			});
		}
		function sortCards(by) {
			var sorted = $cards.get().sort(function (a, b) {
				if (by === 'name') return String($(a).attr('data-abmm-title')).localeCompare(String($(b).attr('data-abmm-title')));
				return Number($(a).attr('data-abmm-order')) - Number($(b).attr('data-abmm-order'));
			});
			$grid.append(sorted);
		}
		function filter() {
			var term = $.trim(String($search.val() || '')).toLowerCase();
			var shown = 0;
			$cards.each(function () {
				var match = term === '' || String($(this).attr('data-abmm-title')).indexOf(term) !== -1;
				this.hidden = !match;
				if (match) shown++;
			});
			$empty.prop('hidden', shown > 0);
			$count.text(term === '' ? String(total) : sprintf(
				/* translators: 1: number of matching menus, 2: total number of menus. */
				__('%1$d of %2$d', 'ashbi-mega-menu'), shown, total
			));
			$results.text(term === '' ? '' : sprintf(
				/* translators: %d: number of menus matching the search. */
				_n('%d menu found', '%d menus found', shown, 'ashbi-mega-menu'), shown
			));
		}
		var prefs = read();
		setView(prefs.view);
		if (prefs.sort === 'name') $sort.val('name');
		sortCards($sort.val());
		$toolbar.prop('hidden', false);
		$toolbar.on('click', '[data-abmm-view]', function () {
			var view = $(this).attr('data-abmm-view');
			setView(view);
			prefs.view = view;
			write(prefs);
		});
		$sort.on('change', function () {
			sortCards($sort.val());
			prefs.sort = $sort.val();
			write(prefs);
		});
		$search.on('input', function () {
			window.clearTimeout(timer);
			timer = window.setTimeout(filter, 80);
		}).on('keydown', function (e) {
			if (e.key === 'Escape' && $search.val()) { e.preventDefault(); $search.val(''); filter(); }
		});
		$('[data-abmm-menu-clear]').on('click', function () {
			$search.val('').trigger('focus');
			filter();
		});
	}

	function initListPage() {
		initBackupRecovery();
		initCardMenus();
		initMenuToolbar();
		$('.abmm-onboarding-choice').on('click', function () {
			var $buttons = $('.abmm-onboarding-choice').prop('disabled', true);
			$.post(abmmAdmin.ajaxUrl, {
				action: 'abmm_onboarding',
				nonce: abmmAdmin.nonce,
				choice: $(this).data('choice'),
			}).done(function (res) {
				if (res && res.success && res.data && res.data.url) {
					window.location = res.data.url;
					return;
				}
				setListStatus(responseMessage(res, S.createError), true, true);
				$buttons.prop('disabled', false);
			}).fail(function (xhr) {
				setListStatus(requestErrorMessage(xhr, S.createError), true, true);
				$buttons.prop('disabled', false);
			});
		});

		$('.abmm-create-menu').on('click', function () {
			$('.abmm-create-menu-form').remove();
			var $button = $(this);
			var $form = $(
				'<form class="abmm-create-menu-form">' +
				'<label class="screen-reader-text" for="abmm-new-menu-title">' + esc(S.menuName) + '</label>' +
				'<input id="abmm-new-menu-title" type="text" required maxlength="100" value="' + esc(S.newMenu) + '" />' +
				'<button type="submit" class="button button-primary">' + esc(S.createMenu) + '</button>' +
				'<button type="button" class="button-link abmm-cancel-create">' + esc(S.cancel) + '</button>' +
				'</form>'
			);
			$button.after($form);
			$form.find('input').trigger('focus').select();
		});

		$(document).on('click', '.abmm-cancel-create', function () {
			$(this).closest('.abmm-create-menu-form').remove();
		});

		$(document).on('submit', '.abmm-create-menu-form', function (e) {
			e.preventDefault();
			var $form = $(this);
			var title = $.trim($form.find('input').val());
			if (!title) return;
			var $controls = $form.find('button, input').add('.abmm-create-menu').prop('disabled', true);
			setListStatus(S.creatingMenu, false, false);
			$.post(abmmAdmin.ajaxUrl, {
				action: 'abmm_create_menu',
				nonce: abmmAdmin.nonce,
				title: title,
			}).done(function (res) {
				if (res && res.success && res.data && res.data.url) {
					window.location = res.data.url;
					return;
				}
				setListStatus(responseMessage(res, S.createError), true, true);
			}).fail(function (xhr) {
				setListStatus(requestErrorMessage(xhr, S.createError), true, true);
			}).always(function () {
				$controls.prop('disabled', false);
			});
		});

		$('.abmm-delete-menu').on('click', function () {
			var $button = $(this);
			var id = $button.data('menu-id');
			var title = $button.data('menu-title') || id;
			var $card = $button.closest('.abmm-menu-card');
			if ($card.find('.abmm-archive-confirm').length) return;
			$button.prop('disabled', true).text(S.archiveChecking);
			$.post(abmmAdmin.ajaxUrl, {
				action: 'abmm_menu_references',
				nonce: abmmAdmin.nonce,
				menu_id: id,
			}).done(function (res) {
				if (res && res.success) {
					var refs = res.data || {};
					// Callback replacements preserve literal $ tokens in menu titles.
					var confirmTitle = S.archiveConfirmTitle.replace(/%1\$s/g, function () { return title; });
					var placements = S.archivePlacements.replace(/%([123])\$d/g, function (_, position) {
						return position === '1' ? (refs.blocks || 0) : (position === '2' ? (refs.widgets || 0) : (refs.shortcodes || 0));
					});
					var $confirm = $(
						'<div class="abmm-archive-confirm" role="alert">' +
						'<strong>' + esc(confirmTitle) + '</strong>' +
						'<p>' + esc(placements) + '</p>' +
						'<p>' + esc(refs.warning || '') + '</p>' +
						'<button type="button" class="button button-primary abmm-confirm-archive">' + esc(S.archiveConfirm) + '</button> ' +
						'<button type="button" class="button abmm-cancel-archive">' + esc(S.cancel) + '</button>' +
						'</div>'
					);
					$card.append($confirm);
					$button.prop('disabled', false).text(S.archiveMenu);
					$confirm.find('.abmm-confirm-archive').trigger('focus');
					return;
				}
				$button.prop('disabled', false).text(S.archiveMenu).trigger('focus');
				setListStatus(responseMessage(res, S.deleteError), true, true);
			}).fail(function (xhr) {
				$button.prop('disabled', false).text(S.archiveMenu).trigger('focus');
				setListStatus(requestErrorMessage(xhr, S.deleteError), true, true);
			});
		});

		$(document).on('click', '.abmm-cancel-archive', function () {
			var $card = $(this).closest('.abmm-menu-card');
			$card.find('.abmm-archive-confirm').remove();
			// Archive lives in the closed "⋯" menu on the dashboard, so focus its button instead.
			var $archive = $card.find('.abmm-delete-menu');
			if ($archive.closest('[hidden]').length) $card.find('.abmm-card-menu__toggle').trigger('focus');
			else $archive.trigger('focus');
		});

		$(document).on('click', '.abmm-confirm-archive', function () {
			var $button = $(this).prop('disabled', true);
			var $card = $button.closest('.abmm-menu-card');
			var id = $card.data('menu-id');
			$.post(abmmAdmin.ajaxUrl, {
				action: 'abmm_delete_menu',
				nonce: abmmAdmin.nonce,
				menu_id: id,
			}).done(function (res) {
				if (res && res.success) {
					setListStatus(responseMessage(res, S.archiveSuccess), false, false);
					$card.fadeOut(200, function () { window.location.reload(); });
					return;
				}
				$button.prop('disabled', false);
				setListStatus(responseMessage(res, S.deleteError), true, true);
			}).fail(function (xhr) {
				$button.prop('disabled', false);
				setListStatus(requestErrorMessage(xhr, S.deleteError), true, true);
			});
		});

		$('.abmm-restore-menu').on('click', function () {
			var $button = $(this).prop('disabled', true);
			$.post(abmmAdmin.ajaxUrl, {
				action: 'abmm_restore_menu',
				nonce: abmmAdmin.nonce,
				menu_id: $button.data('menu-id'),
			}).done(function (res) {
				if (res && res.success) {
					setListStatus(responseMessage(res, S.restoreSuccess), false, false);
					window.location.reload();
					return;
				}
				$button.prop('disabled', false);
				setListStatus(responseMessage(res, S.deleteError), true, true);
			}).fail(function (xhr) {
				$button.prop('disabled', false);
				setListStatus(requestErrorMessage(xhr, S.deleteError), true, true);
			});
		});

		$('.abmm-permanent-delete').on('click', function () {
			var $button = $(this);
			if (!$button.data('confirmed')) {
				$button.data('confirmed', true).text(__('Click again to permanently delete', 'ashbi-mega-menu'));
				return;
			}
			$button.prop('disabled', true);
			$.post(abmmAdmin.ajaxUrl, {
				action: 'abmm_permanently_delete_menu',
				nonce: abmmAdmin.nonce,
				menu_id: $button.data('menu-id'),
			}).done(function (res) {
				if (res && res.success) {
					$button.closest('.abmm-archive-row').remove();
					setListStatus(responseMessage(res, __('Archived menu permanently deleted.', 'ashbi-mega-menu')), false, false);
					return;
				}
				$button.prop('disabled', false);
				setListStatus(responseMessage(res, S.deleteError), true, true);
			}).fail(function (xhr) {
				$button.prop('disabled', false);
				setListStatus(requestErrorMessage(xhr, S.deleteError), true, true);
			});
		});

		$('.abmm-copy-shortcode').on('click', function () {
			var $btn = $(this);
			var sc = $btn.data('shortcode');
			var old = $btn.text();
			var copyPromise;

			$btn.prop('disabled', true);
			if (navigator.clipboard && navigator.clipboard.writeText) {
				try {
					copyPromise = navigator.clipboard.writeText(sc);
				} catch (error) {
					copyPromise = Promise.reject(error);
				}
			} else {
				copyPromise = new Promise(function (resolve, reject) {
					var $tmp = $('<input>').val(sc).appendTo('body').select();
					var copied = false;
					try {
						copied = document.execCommand('copy');
					} catch (error) {
						copied = false;
					}
					$tmp.remove();
					if (copied) resolve();
					else reject(new Error('Clipboard unavailable'));
				});
			}

			Promise.resolve(copyPromise)
				.then(function () {
					$btn.text(S.copyShortcode);
					setListStatus(S.copyShortcode, false, false);
					setTimeout(function () {
						$btn.text(old);
					}, 1500);
				})
				.catch(function () {
					showClipboardFallback($btn, sc);
				})
				.then(function () {
					$btn.prop('disabled', false);
				});
		});

		$('.abmm-duplicate-menu').on('click', function () {
			var $button = $(this).prop('disabled', true);
			$.post(abmmAdmin.ajaxUrl, {
				action: 'abmm_duplicate_menu',
				nonce: abmmAdmin.nonce,
				menu_id: $button.data('menu-id'),
			}).done(function (res) {
				if (res && res.success && res.data && res.data.url) {
					window.location = res.data.url;
					return;
				}
				$button.prop('disabled', false);
				setListStatus(responseMessage(res, S.duplicateError), true, true);
			}).fail(function (xhr) {
				$button.prop('disabled', false);
				setListStatus(requestErrorMessage(xhr, S.duplicateError), true, true);
			});
		});

		// Export from list page
		$('.abmm-export-menu').on('click', function () {
			var id = $(this).data('menu-id');
			exportMenu(id);
		});

		// Import form
		var importConfirmed = false;
		$('#abmm-import-file, #abmm-import-mode').on('change', function () {
			importConfirmed = false;
			$('#abmm-import-preview').empty().prop('hidden', true);
		});
		$('#abmm-import-form').on('submit', function (e) {
			e.preventDefault();
			var $form = $(this);
			var $file = $('#abmm-import-file');
			var mode = $('#abmm-import-mode').val();

			if (!$file[0].files.length) {
				setImportStatus(S.importInvalid, true);
				return;
			}

			if ($file[0].files[0].size > abmmAdmin.importMaxBytes) {
				setImportStatus(S.importTooLarge.replace('%s', abmmAdmin.importMaxLabel), true); return;
			}
			var fd = new FormData();
			fd.append('action', 'abmm_import_menu');
			fd.append('nonce', abmmAdmin.nonce);
			fd.append('mode', mode);
			fd.append('import_file', $file[0].files[0]);
			if (!importConfirmed) fd.append('preview', '1');

			var $btn = $form.find('button[type="submit"]').prop('disabled', true);
			setImportStatus(importConfirmed ? S.importing : S.preparingImport, false);

			$.ajax({
				url: abmmAdmin.ajaxUrl,
				type: 'POST',
				data: fd,
				processData: false,
				contentType: false,
			}).done(function (res) {
				if (res.success && res.data.preview) {
					renderImportPreview(res.data.preview, function () {
						importConfirmed = true;
						$form.trigger('submit');
					});
				} else if (res.success) {
					setImportStatus(S.importSuccess, false);
					setTimeout(function () {
						window.location.reload();
					}, 800);
				} else {
					setImportStatus(res.data && res.data.message ? res.data.message : S.importError, true);
				}
			}).fail(function (xhr) {
				setImportStatus(requestErrorMessage(xhr, S.importError), true);
			}).always(function () {
				$btn.prop('disabled', false);
			});
		});
	}

	/* ---------- Export helper ---------- */
	function downloadMenuJson(id, json) {
		var blob = new Blob([json], { type: 'application/json' });
		var url = URL.createObjectURL(blob);
		var a = document.createElement('a');
		a.href = url;
		a.download = id + '.abmm.json';
		document.body.appendChild(a);
		a.click();
		document.body.removeChild(a);
		URL.revokeObjectURL(url);
		setStatus(S.exportSuccess, false);
	}

	function exportMenu(id) {
		/* The builder must export the visible in-memory state, including unsaved edits. */
		if (state.menuId && String(id) === String(state.menuId) && state.menu) {
			syncSettingsFromForm();
			downloadMenuJson(id, JSON.stringify(getMenu(), null, 2));
			return;
		}
		$.post(abmmAdmin.ajaxUrl, {
			action: 'abmm_export_menu',
			nonce: abmmAdmin.nonce,
			menu_id: id,
		}).done(function (res) {
			if (res.success && res.data.json) {
				downloadMenuJson(id, res.data.json);
			} else {
				setStatus(S.exportError, true);
			}
		}).fail(function () {
			setStatus(S.exportError, true);
		});
	}

	/* ---------- Builder ---------- */
	function isPlainObject(value) {
		return !!value && typeof value === 'object' && !Array.isArray(value);
	}

	function validateBuilderMenu(menu) {
		if (!isPlainObject(menu) || !Array.isArray(menu.items)) return false;
		if (typeof menu.title !== 'undefined' && typeof menu.title !== 'string') return false;
		if (typeof menu.cta !== 'undefined' && !isPlainObject(menu.cta)) return false;
		if (typeof menu.settings !== 'undefined' && !isPlainObject(menu.settings)) return false;

		function validLink(link) {
			return isPlainObject(link);
		}

		function validGroup(group) {
			return isPlainObject(group) &&
				(typeof group.links === 'undefined' || (Array.isArray(group.links) && group.links.every(validLink)));
		}

		function validCategory(category) {
			return isPlainObject(category) &&
				(typeof category.groups === 'undefined' || (Array.isArray(category.groups) && category.groups.every(validGroup))) &&
				(typeof category.links === 'undefined' || (Array.isArray(category.links) && category.links.every(validLink)));
		}

		function validItem(item) {
			return isPlainObject(item) &&
				(typeof item.links === 'undefined' || (Array.isArray(item.links) && item.links.every(validLink))) &&
				(typeof item.categories === 'undefined' || (Array.isArray(item.categories) && item.categories.every(validCategory)));
		}

		return menu.items.every(validItem);
	}

	function failBuilderLoad(message, diagnosticCode) {
		state.loadFailed = true;
		state.menu = null;
		$('.abmm-builder-wrap').addClass('is-load-failed');
		$('#abmm-builder')
			.attr('aria-disabled', 'true')
			.find('input, select, textarea, button')
			.prop('disabled', true);
		$('.abmm-save-menu, #abmm-menu-title').prop('disabled', true);
		$('.abmm-save-status')
			.empty()
			.text(message)
			.addClass('is-visible is-error')
			.attr('role', 'alert');
		$('#abmm-builder-load-error-message').text(message);
		$('#abmm-builder-load-error').prop('hidden', false);
		$('#abmm-builder-load-error-title').trigger('focus');
		if (window.console && console.error) {
			console.error('Ashbi Mega Menu builder load failed: ' + diagnosticCode);
		}
	}

	function refetchBuilderMenu() {
		var $button = $('.abmm-retry-builder-load').prop('disabled', true).text('Retrying…');
		$.post(abmmAdmin.ajaxUrl, {
			action: 'abmm_get_menu',
			nonce: abmmAdmin.nonce,
			menu_id: state.menuId,
		}).done(function (res) {
			if (!res || !res.success || !res.data || !validateBuilderMenu(res.data.menu)) {
				$('#abmm-builder-load-error-message').text(responseMessage(res, S.loadMalformed));
				return;
			}
			try {
				sessionStorage.setItem('abmmRecovered:' + state.menuId, JSON.stringify({
					menu: res.data.menu,
					revision: res.data.revision || '',
				}));
			} catch (error) {
				// The normal server-rendered reload remains available if session storage is unavailable.
			}
			window.location.reload();
		}).fail(function (xhr) {
			$('#abmm-builder-load-error-message').text(requestErrorMessage(xhr, S.loadMalformed));
		}).always(function () {
			$button.prop('disabled', false).text(__('Retry authenticated load', 'ashbi-mega-menu'));
		});
	}

	function modalFocusable($modal) {
		return $modal
			.find('a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')
			.filter(function () { return $(this).is(':visible'); });
	}

	function wordpressDialogIsOpen() {
		return $('.media-modal:visible, #wp-link-wrap:visible').length > 0;
	}

	function restoreResponsiveSheetInertness() {
		if ($('#abmm-builder-sidebar').hasClass('is-open')) {
			$('.abmm-builder__main, .abmm-builder-topbar, .abmm-workflow-nav, .abmm-builder-load-error, #abmm-draft-recovery')
				.attr('aria-hidden', 'true')
				.prop('inert', true);
		}
		if ($('#abmm-mega-editor').attr('role') === 'dialog') {
			$('.abmm-builder-topbar, .abmm-workflow-nav, .abmm-builder__sidebar, .abmm-builder__main > *:not(#abmm-mega-editor), .abmm-builder-load-error, #abmm-draft-recovery')
				.attr('aria-hidden', 'true')
				.prop('inert', true);
		}
	}

	function setBuilderBackgroundInert(active) {
		var $background = $('.abmm-builder, .abmm-builder-topbar, .abmm-workflow-nav, .abmm-builder-load-error, #abmm-draft-recovery');
		$background.each(function () {
			var $element = $(this);
			if (active) {
				$element.data('abmmPreviousAriaHidden', $element.attr('aria-hidden'));
				$element.attr('aria-hidden', 'true').prop('inert', true);
			} else {
				var previous = $element.data('abmmPreviousAriaHidden');
				if (typeof previous === 'undefined' || previous === null) $element.removeAttr('aria-hidden');
				else $element.attr('aria-hidden', previous);
				$element.removeData('abmmPreviousAriaHidden').prop('inert', false);
			}
		});
		$('body').toggleClass('abmm-modal-open', active);
	}

	function openBuilderModal(selector, opener, initialSelector) {
		var $modal = $(selector);
		state.activeModal = selector;
		state.modalOpener = opener || document.activeElement;
		$modal.prop('hidden', false);
		setBuilderBackgroundInert(true);
		setTimeout(function () {
			var $initial = initialSelector ? $modal.find(initialSelector).filter(':visible').first() : $();
			var $focusTarget = $initial.length ? $initial : modalFocusable($modal).first();
			if ($focusTarget.length) $focusTarget.trigger('focus');
		}, 0);
	}

	function closeBuilderModal(selector) {
		var $modal = $(selector);
		$modal.prop('hidden', true);
		if (state.activeModal === selector) {
			var opener = state.modalOpener;
			state.activeModal = '';
			state.modalOpener = null;
			setBuilderBackgroundInert(false);
			restoreResponsiveSheetInertness();
			if (opener && document.documentElement.contains(opener) && !$(opener).prop('disabled')) {
				setTimeout(function () { $(opener).trigger('focus'); }, 0);
			}
		}
	}

	function bindModalKeyboard() {
		$(document).on('keydown.abmmModal', function (event) {
			if (!state.activeModal || wordpressDialogIsOpen()) return;
			var $modal = $(state.activeModal);
			if (!$modal.length || $modal.prop('hidden')) return;
			if (event.key === 'Escape') {
				event.preventDefault();
				if (state.activeModal === '#abmm-icon-modal') closeIconModal();
				else closeBuilderModal(state.activeModal);
				return;
			}
			if (event.key !== 'Tab') return;
			var $focusable = modalFocusable($modal);
			if (!$focusable.length) {
				event.preventDefault();
				return;
			}
			var first = $focusable[0];
			var last = $focusable[$focusable.length - 1];
			if (event.shiftKey && document.activeElement === first) {
				event.preventDefault();
				$(last).trigger('focus');
			} else if (!event.shiftKey && document.activeElement === last) {
				event.preventDefault();
				$(first).trigger('focus');
			}
		});
	}

	function bindExitRecovery() {
		$('.abmm-guarded-exit').on('click', function (event) {
			if (!hasUnsavedChanges()) return;
			event.preventDefault();
			persistLocalDraft();
			state.pendingExitUrl = $(this).attr('href');
			openBuilderModal('#abmm-exit-dialog', this, '.abmm-stay-builder');
		});
		$('.abmm-stay-builder, .abmm-exit-dialog__backdrop').on('click', function () {
			closeBuilderModal('#abmm-exit-dialog');
		});
		$('.abmm-leave-builder').on('click', function () {
			persistLocalDraft();
			window.location = state.pendingExitUrl || $('.abmm-back').attr('href');
		});
		window.addEventListener('pagehide', persistLocalDraft);
	}

	function builderPanelIsNarrow() {
		return window.matchMedia && window.matchMedia('(max-width: 1199px)').matches;
	}

	function megaEditorIsNarrow() {
		return window.matchMedia && window.matchMedia('(max-width: 760px)').matches;
	}

	function updateBuilderSheetBodyLock() {
		var sheetOpen = $('#abmm-builder-sidebar').hasClass('is-open') || $('#abmm-mega-editor').attr('role') === 'dialog';
		$('html, body').toggleClass('abmm-builder-sheet-open', sheetOpen);
	}

	function setMegaEditorBackgroundInert(active) {
		var $background = $('.abmm-builder-topbar, .abmm-workflow-nav, .abmm-builder__sidebar, .abmm-builder__main > *:not(#abmm-mega-editor), .abmm-builder-load-error, #abmm-draft-recovery');
		$background.each(function () {
			var $element = $(this);
			if (active) {
				$element.data('abmmPreviousMegaEditorAriaHidden', $element.attr('aria-hidden'));
				$element.attr('aria-hidden', 'true').prop('inert', true);
			} else {
				var previous = $element.data('abmmPreviousMegaEditorAriaHidden');
				if (typeof previous === 'undefined' || previous === null) $element.removeAttr('aria-hidden');
				else $element.attr('aria-hidden', previous);
				$element.removeData('abmmPreviousMegaEditorAriaHidden').prop('inert', false);
			}
		});
	}

	function setBuilderPanelBackgroundInert(active) {
		var $background = $('.abmm-builder__main, .abmm-builder-topbar, .abmm-workflow-nav, .abmm-builder-load-error, #abmm-draft-recovery');
		$background.each(function () {
			var $element = $(this);
			if (active) {
				$element.data('abmmPreviousPanelAriaHidden', $element.attr('aria-hidden'));
				$element.attr('aria-hidden', 'true').prop('inert', true);
			} else {
				var previous = $element.data('abmmPreviousPanelAriaHidden');
				if (typeof previous === 'undefined' || previous === null) $element.removeAttr('aria-hidden');
				else $element.attr('aria-hidden', previous);
				$element.removeData('abmmPreviousPanelAriaHidden').prop('inert', false);
			}
		});
	}

	function syncMegaEditorSheetState() {
		var $editor = $('#abmm-mega-editor');
		var $backdrop = $('#abmm-builder-panel-backdrop');
		if (!$editor.length || $editor.prop('hidden')) return;

		var narrow = megaEditorIsNarrow();
		var isSheet = $editor.attr('role') === 'dialog';
		if (narrow) {
			if (!isSheet) {
				setMegaEditorBackgroundInert(true);
				$editor.attr({ role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'abmm-mega-editor-eyebrow abmm-mega-editor-title' });
			}
			$backdrop.prop('hidden', false).attr('aria-hidden', 'false');
		} else if (isSheet) {
			setMegaEditorBackgroundInert(false);
			$editor.removeAttr('role aria-modal aria-labelledby');
			if (!$('#abmm-builder-sidebar').hasClass('is-open')) {
				$backdrop.prop('hidden', true).attr('aria-hidden', 'true');
			}
		}
		updateBuilderSheetBodyLock();
	}

	function closeBuilderPanel(returnFocus) {
		var $sidebar = $('#abmm-builder-sidebar');
		var $backdrop = $('#abmm-builder-panel-backdrop');
		if (!$sidebar.length) return;
		if (!$sidebar.hasClass('is-open') && $sidebar.attr('role') !== 'dialog') return;
		$sidebar.removeClass('is-open');
		$sidebar.removeAttr('role aria-modal');
		$backdrop.prop('hidden', true).attr('aria-hidden', 'true');
		$('.abmm-builder-panel-toggle').attr('aria-expanded', 'false');
		setBuilderPanelBackgroundInert(false);
		updateBuilderSheetBodyLock();
		if (returnFocus && state.builderPanelOpener && document.documentElement.contains(state.builderPanelOpener)) {
			setTimeout(function () {
				$(state.builderPanelOpener).trigger('focus');
			}, 0);
		}
		state.builderPanelOpener = null;
	}

	function openBuilderPanel(opener) {
		var $sidebar = $('#abmm-builder-sidebar');
		var $backdrop = $('#abmm-builder-panel-backdrop');
		if (!$sidebar.length || !builderPanelIsNarrow()) return;
		if ($('#abmm-mega-editor').attr('role') === 'dialog') closeMegaEditor();
		state.builderPanelOpener = opener || document.activeElement;
		$sidebar.addClass('is-open');
		$sidebar.attr({ role: 'dialog', 'aria-modal': 'true' });
		$backdrop.prop('hidden', false).attr('aria-hidden', 'false');
		$('.abmm-builder-panel-toggle').attr('aria-expanded', 'true');
		setBuilderPanelBackgroundInert(true);
		updateBuilderSheetBodyLock();
		setTimeout(function () {
			$sidebar.find('.abmm-builder-panel-close').trigger('focus');
		}, 0);
	}

	function initBuilderPanel() {
		var $toggle = $('.abmm-builder-panel-toggle');
		var $sidebar = $('#abmm-builder-sidebar');
		var $backdrop = $('#abmm-builder-panel-backdrop');
		if (!$toggle.length || !$sidebar.length || !$backdrop.length) return;

		$toggle.off('.abmmBuilderPanel').on('click.abmmBuilderPanel', function () {
			if ($sidebar.hasClass('is-open')) closeBuilderPanel(true);
			else openBuilderPanel(this);
		});
		$sidebar.find('.abmm-builder-panel-close').off('.abmmBuilderPanel').on('click.abmmBuilderPanel', function () {
			closeBuilderPanel(true);
		});
		$backdrop.off('.abmmBuilderPanel').on('click.abmmBuilderPanel', function () {
			if ($('#abmm-mega-editor').attr('role') === 'dialog') closeMegaEditor();
			else closeBuilderPanel(true);
		});
		$(document).off('keydown.abmmBuilderPanel').on('keydown.abmmBuilderPanel', function (event) {
			/* Native WordPress dialogs and the plugin's existing dialogs own focus
			 * while they are open; do not let the responsive sheet handler compete. */
			if (wordpressDialogIsOpen() || state.activeModal) return;
			var $sheet = $('#abmm-mega-editor[role="dialog"]');
			if (!$sheet.length) $sheet = $('#abmm-builder-sidebar[role="dialog"]');
			if (event.key === 'Escape') {
				/* Dismiss the topmost nested sheet first when editing starts from the
				 * settings sheet. The settings sheet remains available underneath it. */
				if ($sheet.is('#abmm-mega-editor')) {
					event.preventDefault();
					closeMegaEditor();
				} else if ($sidebar.hasClass('is-open')) {
					event.preventDefault();
					closeBuilderPanel(true);
				}
				return;
			}
			if (event.key !== 'Tab' || !$sheet.length) return;
			var $focusable = modalFocusable($sheet);
			if (!$focusable.length) {
				event.preventDefault();
				return;
			}
			var first = $focusable[0];
			var last = $focusable[$focusable.length - 1];
			var activeInside = document.activeElement && $.contains($sheet[0], document.activeElement);
			if (!activeInside) {
				event.preventDefault();
				$(event.shiftKey ? last : first).trigger('focus');
			} else if (event.shiftKey && document.activeElement === first) {
				event.preventDefault();
				$(last).trigger('focus');
			} else if (!event.shiftKey && document.activeElement === last) {
				event.preventDefault();
				$(first).trigger('focus');
			}
		});
		$(window).off('resize.abmmBuilderPanel').on('resize.abmmBuilderPanel', function () {
			if (!builderPanelIsNarrow()) closeBuilderPanel(false);
			syncMegaEditorSheetState();
		});
	}

	function initBuilder() {
		var $builder = $('#abmm-builder');
		if (!$builder.length) return;

		state.menuId = $builder.attr('data-menu-id') || '';
		state.serverRevision = String($builder.data('menu-revision') || '');
		$('.abmm-retry-builder-load').on('click', function () {
			refetchBuilderMenu();
		});
		var menuJson = document.getElementById('abmm-menu-data');
		var recovered = null;
		try {
			recovered = JSON.parse(sessionStorage.getItem('abmmRecovered:' + state.menuId) || 'null');
			sessionStorage.removeItem('abmmRecovered:' + state.menuId);
		} catch (error) {
			recovered = null;
		}
		if (recovered && validateBuilderMenu(recovered.menu)) {
			state.menu = recovered.menu;
			state.serverRevision = recovered.revision || state.serverRevision;
		} else {
			if (!menuJson) {
				failBuilderLoad(S.loadMalformed, 'missing-transport');
				return;
			}
			try {
				state.menu = JSON.parse(menuJson.textContent || '');
			} catch (e) {
				failBuilderLoad(S.loadMalformed, 'malformed-json');
				return;
			}
		}
		if (!validateBuilderMenu(state.menu)) {
			failBuilderLoad(S.loadIncompatible, 'schema-mismatch');
			return;
		}
		if (typeof state.menu.title === 'undefined') state.menu.title = '';
		if (!state.menu.cta) state.menu.cta = {};
		if (!state.menu.settings) state.menu.settings = {};

		$('.abmm-color').wpColorPicker({
			change: function () {
				markCustomPreset();
				setTimeout(renderPreview, 50);
			},
			clear: function () {
				markCustomPreset();
				setTimeout(renderPreview, 50);
			},
		});

		initWpLink();
		initPopovers();
		initMoreMenu();
		initBuilderSections();
		$('[data-abmm-workflow]').on('click', function () {
			var step = $(this).attr('data-abmm-workflow');
			state.workflowStep = step;
			// Edit returns to the tab the user was last on.
			setBuilderSection(step === 'content' ? (state.lastEditSection || 'content') : 'help');
			if (builderPanelIsNarrow()) openBuilderPanel(this);
			if (step === 'verify') $('#abmm-verification-panel').trigger('focus');
		});
		$('.abmm-copy-placement').on('click', function () {
			var $button = $(this), shortcode = String($button.attr('data-shortcode'));
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(shortcode).then(function () { setStatus(S.copyShortcode, false); }, function () { showClipboardFallback($button, shortcode); });
			} else showClipboardFallback($button, shortcode);
		});
		initBuilderPanel();

		initPreviewZoom();
		$('.abmm-preview-viewports').on('click', '[data-abmm-preview-viewport]', function () {
			state.previewViewport = String($(this).data('abmm-preview-viewport') || 'desktop');
			state.previewDrill = 'root';
			state.previewItemIndex = -1;
			state.previewCategoryId = '';
			// Show the drawer straight away so tablet and phone previews show the menu, not just a hamburger.
			state.previewMobileOpen = state.previewViewport !== 'desktop';
			// Every device starts at Fit, so a phone never overflows the canvas.
			state.previewZoom = 'fit';
			$('.abmm-preview-viewports [data-abmm-preview-viewport]')
				.removeClass('is-active')
				.attr('aria-pressed', 'false');
			$(this).addClass('is-active').attr('aria-pressed', 'true');
			$('.abmm-preview-shell').attr('data-abmm-preview-viewport', state.previewViewport);
			renderPreview();
		});

		var designSelectors = [
			'#abmm-menu-title',
			'#abmm-cta-show',
			'#abmm-cta-label',
			'#abmm-cta-url',
			'#abmm-cta-target',
			'#abmm-cta-rel',
			'#abmm-cta-class',
			'#abmm-brand-show',
			'#abmm-brand-title',
			'#abmm-brand-url',
			'#abmm-brand-logo-url',
			'#abmm-brand-logo-dark-url',
			'#abmm-brand-alt',
			'#abmm-brand-logo-height',
			'#abmm-presentation',
			'#abmm-layout',
			'#abmm-nav-align',
			'#abmm-grid-cols',
			'#abmm-icon-inherit-text',
			'#abmm-icon-size',
			'#abmm-icon-radius',
			'#abmm-full-width',
			'#abmm-panel-width',
			'#abmm-sidebar-width',
			'#abmm-border-radius',
			'#abmm-shadow',
			'#abmm-cta-style',
			'#abmm-panel-title-align',
			'#abmm-uppercase-cats',
			'#abmm-show-cat-desc',
		].join(', ');

		$(designSelectors).on('change input', function () {
			if (
				$(this).is(
					'#abmm-layout, #abmm-nav-align, #abmm-grid-cols, #abmm-full-width, #abmm-panel-width, #abmm-sidebar-width, #abmm-border-radius, #abmm-shadow, #abmm-cta-style, #abmm-panel-title-align, #abmm-uppercase-cats, #abmm-show-cat-desc'
				)
			) {
				/* keep preset unless colors were edited */
			}
			if ($(this).is('#abmm-full-width')) {
				syncFullWidthField();
			}
			if ($(this).is('#abmm-icon-inherit-text')) {
				syncIconInheritField();
			}
			if ($(this).is('#abmm-brand-show, #abmm-cta-show')) {
				syncOptionalFeatureFields();
			}
			syncSettingsFromForm();
			renderPreview();
		});

		$('#abmm-preset-grid').on('click', 'button.abmm-preset-card', function () {
			var key = $(this).data('preset');
			var values = $(this).attr('data-values');
			try {
				values = values ? JSON.parse(values) : null;
			} catch (err) {
				values = null;
			}
			applyPreset(key, values);
		});

		renderNavItems();
		syncFullWidthField();
		syncIconInheritField();
		syncOptionalFeatureFields();
		renderPreview();
		captureSavedState();
		renderReadiness();
		checkLocalDraft();
		bindModalKeyboard();
		bindExitRecovery();
		bindBuilderEvents();
		initFeaturedEditor();
		initIconModal();

		// Export current menu from builder
		$('.abmm-export-current-menu').on('click', function () {
			exportMenu(state.menuId);
		});

		$('.abmm-readiness-issues').on('click', 'button', function () {
			var field = document.getElementById($(this).data('field'));
			if (field) {
				field.scrollIntoView({ behavior: 'smooth', block: 'center' });
				field.focus();
			}
		});
	}

	function bindBuilderEvents() {
		$(document).on('click', '.abmm-move-item', function () {
			var $button = $(this);
			if ($button.prop('disabled')) return;
			var direction = $button.hasClass('abmm-move-up') ? -1 : 1;
			var $row = $button.closest('.abmm-nav-item-row, .abmm-cat-row, .abmm-group-block, .abmm-link-row');
			var index = $row.index();
			var collection = null;
			var rerender = null;
			var label = __('Item', 'ashbi-mega-menu');
			if ($row.hasClass('abmm-nav-item-row')) {
				collection = getMenu().items; rerender = renderNavItems; label = __('Navigation item', 'ashbi-mega-menu');
			} else if ($row.hasClass('abmm-cat-row')) {
				var current = getMenu().items[state.editingItemIndex];
				collection = current && current.categories; rerender = renderCatList; label = __('Category', 'ashbi-mega-menu');
			} else if ($row.hasClass('abmm-group-block')) {
				var item = getMenu().items[state.editingItemIndex];
				var category = item && item.categories[state.editingCatIndex];
				collection = category && category.groups; rerender = renderCatDetail; label = __('Column', 'ashbi-mega-menu');
			} else if ($row.hasClass('abmm-link-row')) {
				var $group = $row.closest('.abmm-group-block');
				if ($group.length) {
					var itemForGroup = getMenu().items[state.editingItemIndex];
					var catForGroup = itemForGroup && itemForGroup.categories[state.editingCatIndex];
					var group = catForGroup && (catForGroup.groups || []).find(function (candidate) { return candidate.id === $group.data('id'); });
					collection = group && group.links; rerender = renderCatDetail; label = __('Link', 'ashbi-mega-menu');
				} else {
					var featureItem = getMenu().items[state.editingItemIndex];
					collection = featureItem && featureItem.links; rerender = renderFeaturesLinkList; label = __('Feature link', 'ashbi-mega-menu');
				}
			}
			if (!moveCollectionItem(collection, index, direction)) return;
			if ($row.hasClass('abmm-cat-row')) {
				if (state.editingCatIndex === index) state.editingCatIndex = index + direction;
				else if (state.editingCatIndex === index + direction) state.editingCatIndex = index;
			}
			rerender();
			renderPreview();
			announceReorder(label, index + direction + 1, collection.length);
			setTimeout(function () {
				var selector = direction < 0 ? '.abmm-move-up' : '.abmm-move-down';
				var listSelector = $row.hasClass('abmm-nav-item-row') ? '#abmm-nav-items' :
					($row.hasClass('abmm-cat-row') ? '#abmm-cat-list' :
					($row.hasClass('abmm-group-block') ? '#abmm-group-list' :
					($row.closest('.abmm-group-block').length ? '.abmm-group-block[data-id="' + $row.closest('.abmm-group-block').data('id') + '"] .abmm-group-link-list' : '#abmm-features-link-list')));
				var $controls = $(listSelector).children().eq(index + direction)
					.find('.abmm-reorder-controls').first().find('.abmm-move-item:not(:disabled)');
				var $preferred = $controls.filter(selector);
				// At the first/last position the clicked direction becomes disabled.
				// Keep focus on this item's remaining move control after rerendering.
				($preferred.length ? $preferred : $controls).first().trigger('focus');
			}, 0);
		});

		$('#abmm-cat-list').on('click keydown', '.abmm-select-category', function (event) {
			if (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ') return;
			event.preventDefault();
			selectCategory($(this).closest('.abmm-cat-row').index());
			$('#abmm-cat-list .abmm-select-category').attr('aria-pressed', 'false');
			$(this).attr('aria-pressed', 'true');
		});

		$('.abmm-add-nav-item').on('click', function () {
			var id = uid();
			getMenu().items.push({
				id: id,
				label: __('New Item', 'ashbi-mega-menu'),
				url: '#',
				type: 'link',
			});
			state.expandedNavId = id;
			renderNavItems();
			renderPreview();
			setTimeout(function () {
				$('#abmm-nav-items .abmm-nav-item-row').last().find('.abmm-nav-label').trigger('focus').select();
			}, 0);
		});

		// Rows open from the chevron (keyboard) or a click on the row's summary.
		$('#abmm-nav-items').on('click', '.abmm-nav-item-row__toggle', function () {
			var $row = $(this).closest('.abmm-nav-item-row');
			expandNavRow($row, !$row.hasClass('is-expanded'));
		});
		$('#abmm-nav-items').on('click', '.abmm-nav-item-row__summary', function () {
			var $row = $(this).closest('.abmm-nav-item-row');
			expandNavRow($row, !$row.hasClass('is-expanded'));
			if ($row.hasClass('is-expanded')) $row.find('.abmm-nav-label').trigger('focus');
		});

		$('.abmm-save-menu').on('click', saveMenu);

		$('#abmm-nav-items').on('click', '.abmm-remove-nav', function () {
			var items = getMenu().items, id = $(this).closest('.abmm-nav-item-row').data('id');
			var index = items.findIndex(function (it) { return it.id === id; }); if (index < 0) return;
			var removed = items.splice(index, 1)[0]; if (state.editingItemIndex >= 0) closeMegaEditor();
			renderNavItems(); renderPreview();
			setUndoStatus(__('Navigation item removed.', 'ashbi-mega-menu'), function () { getMenu().items.splice(index, 0, removed); renderNavItems(); renderPreview(); setStatus(__('Navigation item restored.', 'ashbi-mega-menu'), false); });
		});

		$('#abmm-nav-items').on('change input', '.abmm-nav-label, .abmm-nav-url, .abmm-nav-type, .abmm-link-target, .abmm-link-rel, .abmm-link-class', function () {
			var $row = $(this).closest('.abmm-nav-item-row');
			var id = $row.data('id');
			var item = getMenu().items.find(function (it) {
				return it.id === id;
			});
			if (!item) return;

			item.label = $row.find('.abmm-nav-label').val();
			item.url = $row.find('.abmm-nav-url').val();
			readLinkOptions($row, item);
			$row.find('.abmm-nav-item-row__title').text(item.label || __('Untitled item', 'ashbi-mega-menu'));
			$row.find('.abmm-nav-item-row__url').text(navSummaryText(item));
			$row.find('.abmm-nav-item-row__toggle').attr('aria-label', navToggleLabel(item, $row.index()));
			var newType = $row.find('.abmm-nav-type').val();
			if (newType !== item.type) {
				item.type = newType;
				if (newType === 'mega') {
					if (!item.mega_style) item.mega_style = 'platforms';
					if (!item.columns) item.columns = item.mega_style === 'features' ? 2 : 3;
					if (!item.categories) item.categories = [];
					if (!item.links) item.links = [];
				}
				state.expandedNavId = item.id;
				renderNavItems();
				// The row is rebuilt for the new type; keep focus on its type field.
				$('#abmm-nav-items .abmm-nav-item-row').filter(function () { return $(this).data('id') === id; }).find('.abmm-nav-type').trigger('focus');
			}
			renderPreview();
		});

		$('#abmm-nav-items').on('click', '.abmm-edit-mega', function () {
			var id = $(this).closest('.abmm-nav-item-row').data('id');
			var idx = getMenu().items.findIndex(function (it) {
				return it.id === id;
			});
			if (idx < 0) return;
			// From the inspector drawer, step out of the drawer so the content
			// editor below the preview is visible; Done returns to the drawer toggle.
			var opener = this;
			if ($('#abmm-builder-sidebar').hasClass('is-open') && !megaEditorIsNarrow()) {
				opener = $('.abmm-builder-panel-toggle').filter(':visible')[0] || opener;
				closeBuilderPanel(false);
			}
			openMegaEditor(idx, opener);
		});

		$('#abmm-nav-items').on('click', '.abmm-duplicate-nav', function () {
			var items = getMenu().items;
			var id = $(this).closest('.abmm-nav-item-row').data('id');
			var index = items.findIndex(function (item) { return item.id === id; });
			if (index < 0) return;
			var copy = duplicateMenuItem(items[index]);
			items.splice(index + 1, 0, copy);
			state.expandedNavId = copy.id;
			renderNavItems();
			renderPreview();
			/* translators: %s: name of the duplicated menu item. */
			setStatus(sprintf(__('Duplicated %s. Review the copy before saving.', 'ashbi-mega-menu'), items[index].label || __('menu item', 'ashbi-mega-menu')), false);
			setTimeout(function () {
				$('#abmm-nav-items .abmm-nav-item-row').eq(index + 1).find('.abmm-nav-label').trigger('focus').select();
			}, 0);
		});

		$('.abmm-close-mega-editor').on('click', closeMegaEditor);

		$('.abmm-mega-style-card').on('click', function () {
			var item = getMenu().items[state.editingItemIndex];
			if (!item) return;
			item.mega_style = $(this).data('style');
			if (item.mega_style === 'features' && (!item.columns || item.columns === 3)) {
				item.columns = 2;
			}
			if (item.mega_style === 'platforms' && (!item.columns || item.columns === 2)) {
				item.columns = 3;
			}
			syncMegaEditorUI();
			renderPreview();
		});

		$('#abmm-mega-item-columns').on('change', function () {
			var item = getMenu().items[state.editingItemIndex];
			if (!item) return;
			item.columns = parseInt($(this).val(), 10) || 2;
			renderPreview();
		});

		$('#abmm-hide-category-bar').on('change', function () {
			var item = getMenu().items[state.editingItemIndex];
			if (!item) return;
			item.hide_category_bar = $(this).is(':checked') && (item.categories || []).length === 1;
			renderPreview();
		});

		$('.abmm-add-feature-link').on('click', function () {
			var item = getMenu().items[state.editingItemIndex];
			if (!item) return;
			if (!item.links) item.links = [];
			item.links.push({
				id: uid(),
				label: __('New Feature', 'ashbi-mega-menu'),
				url: '#',
				icon: 'link',
				icon_url: '',
				icon_color: '',
				icon_size: 0,
			});
			renderFeaturesLinkList();
			renderPreview();
		});

		$('#abmm-features-link-list').on('click', '.abmm-remove-link', function () {
			var item = getMenu().items[state.editingItemIndex], id = $(this).closest('.abmm-link-row').data('id');
			var index = (item.links || []).findIndex(function (link) { return link.id === id; }); if (index < 0) return;
			var removed = item.links.splice(index, 1)[0]; renderFeaturesLinkList(); renderPreview();
			setUndoStatus(__('Feature link removed.', 'ashbi-mega-menu'), function () { item.links.splice(index, 0, removed); renderFeaturesLinkList(); renderPreview(); setStatus(__('Feature link restored.', 'ashbi-mega-menu'), false); });
		});

		$('#abmm-features-link-list').on('input change', '.abmm-link-label, .abmm-link-url, .abmm-link-target, .abmm-link-rel, .abmm-link-class', function () {
			var item = getMenu().items[state.editingItemIndex];
			var $row = $(this).closest('.abmm-link-row');
			var id = $row.data('id');
			var link = (item.links || []).find(function (l) {
				return l.id === id;
			});
			if (!link) return;
			link.label = $row.find('.abmm-link-label').val();
			link.url = $row.find('.abmm-link-url').val();
			readLinkOptions($row, link);
			renderPreview();
		});

		$('#abmm-features-link-list').on('click', '.abmm-pick-link-icon', function () {
			var idx = $(this).closest('.abmm-link-row').index();
			openIconPicker({ type: 'feature-link', linkIndex: idx });
		});

		$('.abmm-add-category').on('click', function () {
			var item = getMenu().items[state.editingItemIndex];
			if (!item) return;
			if (!item.categories) item.categories = [];
			item.categories.push({
				id: uid(),
				title: __('NEW', 'ashbi-mega-menu'),
				description: '',
				icon: 'grid',
				icon_url: '',
				icon_color: '',
				icon_size: 0,
				panel_title: __('Overview', 'ashbi-mega-menu'),
				panel_url: '',
				groups: [
					{
						id: uid(),
						title: __('Column 1', 'ashbi-mega-menu'),
						url: '',
						links: [],
					},
				],
				links: [],
			});
			renderCatList();
			selectCategory(item.categories.length - 1);
			renderPreview();
		});

		$('#abmm-cat-list').on('click', '.abmm-cat-row', function (e) {
			if ($(e.target).closest('.abmm-row-actions, .abmm-pick-icon, .abmm-select-category').length) return;
			if ($(this).hasClass('is-selected')) return;
			selectCategory($(this).index());
		});

		$('#abmm-cat-list').on('click', '.abmm-remove-cat', function (e) {
			e.stopPropagation(); var item = getMenu().items[state.editingItemIndex], idx = $(this).closest('.abmm-cat-row').index();
			if (!item || !item.categories || !item.categories[idx]) return;
			var removed = item.categories.splice(idx, 1)[0]; state.editingCatIndex = -1; renderCatList(); showCatPlaceholder(); renderPreview();
			setUndoStatus(__('Category removed.', 'ashbi-mega-menu'), function () { item.categories.splice(idx, 0, removed); renderCatList(); renderCatDetail(); renderPreview(); setStatus(__('Category restored.', 'ashbi-mega-menu'), false); });
		});

		$('#abmm-cat-list').on('click', '.abmm-pick-icon', function (e) {
			e.stopPropagation();
			var idx = $(this).closest('.abmm-cat-row').index();
			openIconPicker({ type: 'cat', catIndex: idx });
		});

		$('#abmm-cat-list').on('input change', '.abmm-cat-title, .abmm-cat-desc', function () {
			var item = getMenu().items[state.editingItemIndex];
			var idx = $(this).closest('.abmm-cat-row').index();
			var cat = item.categories[idx];
			if (!cat) return;
			cat.title = $(this).closest('.abmm-cat-row').find('.abmm-cat-title').val();
			cat.description = $(this).closest('.abmm-cat-row').find('.abmm-cat-desc').val();
			if (idx === state.editingCatIndex) {
				$('#abmm-cat-detail .abmm-cat-detail__title').text(cat.title || __('Untitled category', 'ashbi-mega-menu'));
			}
			renderPreview();
		});

	}

	function resetIconStyle(target) {
		if (!target) return;
		target.icon_color = '';
		target.icon_hover_color = '';
		target.icon_active_color = '';
		target.icon_size = 0;
		target.icon_background = '';
		target.icon_border_color = '';
		target.icon_radius = '';
	}

	function applyIconAdvancedField(target, $field) {
		if (!target) return;
		var key = $field.data('icon-field');
		if (!key) return;
		if (key === 'icon_radius') {
			target[key] = $field.val() === '' ? '' : (parseInt($field.val(), 10) || 0);
		} else {
			target[key] = $field.val() || '';
		}
	}

	// Per-item icon style lives in the icon picker, next to the icon it styles.
	function iconAppearanceHtml(style) {
		style = style || {};
		var settings = (getMenu() && getMenu().settings) || {};
		function colorField(key, label, fallback) {
			return '<label class="abmm-icon-appearance__field"><span>' + esc(label) + '</span>' +
				'<input type="color" class="abmm-icon-advanced-field" data-icon-field="' + key + '" value="' +
				esc(style[key] || settings[key] || fallback) + '" /></label>';
		}
		return (
			colorField('icon_color', __('Colour', 'ashbi-mega-menu'), '#2271b1') +
			colorField('icon_hover_color', __('Hover', 'ashbi-mega-menu'), '#1a73e8') +
			colorField('icon_active_color', __('Active', 'ashbi-mega-menu'), '#1a73e8') +
			colorField('icon_background', __('Background', 'ashbi-mega-menu'), '#ffffff') +
			colorField('icon_border_color', __('Border', 'ashbi-mega-menu'), '#ffffff') +
			'<label class="abmm-icon-appearance__field"><span>' + esc(__('Size (px)', 'ashbi-mega-menu')) + '</span>' +
			'<input type="number" min="12" max="64" class="abmm-icon-advanced-field" data-icon-field="icon_size" value="' +
			esc(style.icon_size || '') + '" placeholder="' + esc(__('Inherit', 'ashbi-mega-menu')) + '" /></label>' +
			'<label class="abmm-icon-appearance__field"><span>' + esc(__('Radius (px)', 'ashbi-mega-menu')) + '</span>' +
			'<input type="number" min="0" max="24" class="abmm-icon-advanced-field" data-icon-field="icon_radius" value="' +
			esc(style.icon_radius === '' || style.icon_radius == null ? '' : style.icon_radius) +
			'" placeholder="' + esc(__('Inherit', 'ashbi-mega-menu')) + '" /></label>'
		);
	}

	function iconTargetObject(t) {
		var item = getMenu() && getMenu().items[state.editingItemIndex];
		if (!t || !item) return null;
		if (t.type === 'cat') return (item.categories || [])[t.catIndex] || null;
		if (t.type === 'feature-link') return (item.links || [])[t.linkIndex] || null;
		if (t.type === 'link') {
			var c = (item.categories || [])[t.catIndex];
			return (c && c.links && c.links[t.linkIndex]) || null;
		}
		if (t.type === 'group-link') {
			var gc = (item.categories || [])[t.catIndex];
			var group = gc && (gc.groups || []).find(function (g) { return g.id === t.groupId; });
			return (group && (group.links || []).find(function (l) { return l.id === t.linkId; })) || null;
		}
		return null;
	}

	function iconTargetButton(t) {
		if (!t) return $();
		if (t.type === 'cat') return $('#abmm-cat-list > .abmm-cat-row').eq(t.catIndex).find('.abmm-pick-icon');
		if (t.type === 'feature-link') return $('#abmm-features-link-list > .abmm-link-row').eq(t.linkIndex).find('.abmm-pick-link-icon');
		if (t.type === 'group-link') {
			return $('#abmm-group-list .abmm-link-row').filter(function () { return $(this).data('id') === t.linkId; }).find('.abmm-pick-link-icon');
		}
		return $();
	}

	// Redraw only the icon tile, so the picker's opener keeps its place and focus.
	function refreshIconTile(t) {
		var target = iconTargetObject(t);
		var $button = iconTargetButton(t);
		if (!target || !$button.length) return false;
		$button.html(iconMarkup(target.icon, target.icon_url, '', target.icon_color, target.icon_size, target));
		return true;
	}

	function renderIconAppearance() {
		var target = iconTargetObject(state.iconTarget);
		$('#abmm-icon-appearance').prop('hidden', !target);
		$('#abmm-icon-appearance-fields').html(target ? iconAppearanceHtml(target) : '');
	}

	function showCatPlaceholder() {
		$('#abmm-cat-detail').html(
			'<p class="abmm-placeholder">' +
				esc(__('Select a category on the left to edit its links.', 'ashbi-mega-menu')) +
				'</p>'
		);
	}

	function reorderControlsHtml(label, index, length) {
		return '<span class="abmm-reorder-controls" role="group" aria-label="' + esc((S.positionControls || '%s position controls').replace('%s', label)) + '">' +
			'<button type="button" class="button-link abmm-move-item abmm-move-up" aria-label="' + esc((S.moveUpLabel || 'Move %s up').replace('%s', label)) + '" title="' + esc(S.moveUp || 'Move up') + '"' +
			(index === 0 ? ' disabled' : '') + '>' + iconButtonContent('arrow-up-alt2', S.moveUp || 'Move up') + '</button>' +
			'<button type="button" class="button-link abmm-move-item abmm-move-down" aria-label="' + esc((S.moveDownLabel || 'Move %s down').replace('%s', label)) + '" title="' + esc(S.moveDown || 'Move down') + '"' +
			(index === length - 1 ? ' disabled' : '') + '>' + iconButtonContent('arrow-down-alt2', S.moveDown || 'Move down') + '</button></span>';
	}

	// Compact icon button body; the text stays available to screen readers and tests.
	function iconButtonContent(icon, text) {
		return '<span class="dashicons dashicons-' + icon + '" aria-hidden="true"></span><span class="screen-reader-text">' + esc(text) + '</span>';
	}

	function moveCollectionItem(collection, from, direction) {
		var to = from + direction;
		if (!collection || from < 0 || to < 0 || to >= collection.length) return false;
		var moved = collection.splice(from, 1)[0];
		collection.splice(to, 0, moved);
		return true;
	}

	function announceReorder(label, position, length) {
		/* translators: 1: item name, 2: new position, 3: total number of items. */
		setStatus(sprintf(__('%1$s moved to position %2$s of %3$s.', 'ashbi-mega-menu'), label, position, length), false);
	}

	// Each menu item is a compact row that expands into a labelled form.
	// One row is open at a time; new and duplicated items open straight away.
	function navItemName(item, itemIndex) {
		return item.label || String(itemIndex + 1);
	}

	function navToggleLabel(item, itemIndex) {
		/* translators: %s: menu item name. */
		return sprintf(__('Show settings for %s', 'ashbi-mega-menu'), navItemName(item, itemIndex));
	}

	// A row summary that says something: "3 categories · 12 links" for a mega
	// menu, the path for a link.
	function megaLinkCount(item) {
		if ((item.mega_style || 'platforms') === 'features') return (item.links || []).length;
		var count = 0;
		(item.categories || []).forEach(function (cat) {
			(cat.groups || []).forEach(function (group) { count += (group.links || []).length; });
			count += (cat.links || []).length;
		});
		return count;
	}

	function linkPathText(url) {
		url = String(url || '').trim();
		if (!url) return '';
		var match = url.match(/^(?:https?:)?\/\/([^\/?#]+)(.*)$/i);
		if (match) {
			var host = match[1].toLowerCase();
			var own = String(window.location && window.location.host || '').toLowerCase();
			var rest = match[2] || '/';
			// Same site: the path is what matters. Elsewhere: show the domain too.
			return host === own ? rest : host.replace(/^www\./, '') + (rest === '/' ? '' : rest);
		}
		return url;
	}

	function navSummaryText(item) {
		if (item.type === 'mega') {
			var links = megaLinkCount(item);
			/* translators: %s: number of links. */
			var linksText = sprintf(_n('%s link', '%s links', links, 'ashbi-mega-menu'), links);
			if ((item.mega_style || 'platforms') === 'features') {
				return links ? linksText : __('No content yet', 'ashbi-mega-menu');
			}
			var cats = (item.categories || []).length;
			if (!cats) return __('No content yet', 'ashbi-mega-menu');
			/* translators: 1: number of categories, for example "3 categories", 2: number of links, for example "12 links". */
			return sprintf(__('%1$s · %2$s', 'ashbi-mega-menu'), /* translators: %s: number of categories. */ sprintf(_n('%s category', '%s categories', cats, 'ashbi-mega-menu'), cats), linksText);
		}
		return linkPathText(item.url) || __('No link yet', 'ashbi-mega-menu');
	}

	function setNavRowExpanded($row, expanded) {
		$row.toggleClass('is-expanded', expanded);
		$row.find('.abmm-nav-item-row__toggle').attr('aria-expanded', expanded ? 'true' : 'false');
		$row.find('.abmm-nav-item-row__fields').prop('hidden', !expanded);
	}

	function expandNavRow($row, expanded) {
		var id = $row.data('id');
		if (expanded) {
			state.expandedNavId = id;
			$row.siblings('.abmm-nav-item-row').each(function () { setNavRowExpanded($(this), false); });
		} else if (state.expandedNavId === id) {
			state.expandedNavId = '';
		}
		setNavRowExpanded($row, expanded);
	}

	function renderNavItems() {
		var $list = $('#abmm-nav-items').empty();
		var items = getMenu().items;
		if (typeof state.expandedNavId === 'undefined') {
			// Open the first item on load so its fields are one click away.
			state.expandedNavId = items.length ? items[0].id : '';
		}
		items.forEach(function (item, itemIndex) {
			var isMega = item.type === 'mega';
			var expanded = item.id === state.expandedNavId;
			var fieldsId = 'abmm-nav-fields-' + itemIndex;
			var labelId = 'abmm-nav-label-' + itemIndex;
			var typeId = 'abmm-nav-type-' + itemIndex;
			var urlId = 'abmm-nav-url-' + itemIndex;
			var name = navItemName(item, itemIndex);
			var $row = $(
				'<li class="abmm-nav-item-row ' + (isMega ? 'is-mega' : 'is-link') + (expanded ? ' is-expanded' : '') + '" data-id="' + esc(item.id) + '">' +
				'<div class="abmm-nav-item-row__head">' +
				'<span class="abmm-drag-handle" title="' + esc(S.dragHint) + '" aria-hidden="true"><span class="dashicons dashicons-menu"></span></span>' +
				'<span class="abmm-nav-item-row__summary">' +
				'<span class="abmm-nav-item-row__title">' + esc(item.label || __('Untitled item', 'ashbi-mega-menu')) + '</span>' +
				'<span class="abmm-nav-item-row__sub">' +
				'<span class="abmm-nav-type-chip">' + esc(isMega ? __('Mega menu', 'ashbi-mega-menu') : __('Link', 'ashbi-mega-menu')) + '</span>' +
				(isMega
					? '<span class="abmm-mega-style-badge">' + esc(navSummaryText(item)) + '</span>'
					: '<span class="abmm-nav-item-row__url">' + esc(navSummaryText(item)) + '</span>') +
				'</span>' +
				'</span>' +
				'<span class="abmm-row-actions">' +
				/* translators: %s: menu item name. */
				reorderControlsHtml(sprintf(__('navigation item %s', 'ashbi-mega-menu'), name), itemIndex, items.length) +
				'<button type="button" class="button-link abmm-duplicate-nav" aria-label="' + /* translators: %s: menu item name. */ esc(sprintf(__('Duplicate navigation item %s', 'ashbi-mega-menu'), name)) + '" title="' + esc(__('Duplicate', 'ashbi-mega-menu')) + '">' + iconButtonContent('admin-page', __('Duplicate', 'ashbi-mega-menu')) + '</button>' +
				'<button type="button" class="button-link-delete abmm-remove-nav" aria-label="' + /* translators: %s: menu item name. */ esc(sprintf(__('Remove navigation item %s', 'ashbi-mega-menu'), name)) + '" title="' + esc(__('Remove navigation item', 'ashbi-mega-menu')) + '">' + iconButtonContent('trash', __('Remove', 'ashbi-mega-menu')) + '</button>' +
				'</span>' +
				'<button type="button" class="button-link abmm-nav-item-row__toggle" aria-expanded="' + (expanded ? 'true' : 'false') + '" aria-controls="' + fieldsId + '" aria-label="' + esc(navToggleLabel(item, itemIndex)) + '" title="' + esc(__('Show or hide settings', 'ashbi-mega-menu')) + '">' +
				'<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>' +
				'</div>' +
				'<div class="abmm-nav-item-row__fields" id="' + fieldsId + '"' + (expanded ? '' : ' hidden') + '>' +
				'<div class="abmm-nav-field">' +
				'<label for="' + labelId + '">' + esc(__('Label', 'ashbi-mega-menu')) + '</label>' +
				'<input type="text" id="' + labelId + '" class="abmm-nav-label" value="' + esc(item.label) + '" placeholder="' + esc(__('Label', 'ashbi-mega-menu')) + '" />' +
				'</div>' +
				'<div class="abmm-nav-field">' +
				'<label for="' + typeId + '">' + esc(__('Type', 'ashbi-mega-menu')) + '</label>' +
				'<select id="' + typeId + '" class="abmm-nav-type">' +
				'<option value="link"' + (!isMega ? ' selected' : '') + '>' + esc(S.simpleLink) + '</option>' +
				'<option value="mega"' + (isMega ? ' selected' : '') + '>' + esc(S.megaMenu) + '</option>' +
				'</select>' +
				'</div>' +
				(isMega
					? '<div class="abmm-nav-field abmm-nav-item-row__meta">' +
						'<span class="abmm-nav-field__label">' + esc(__('Content', 'ashbi-mega-menu')) + '</span>' +
						'<button type="button" class="button abmm-edit-mega"><span class="dashicons dashicons-edit" aria-hidden="true"></span>' + esc(__('Edit content', 'ashbi-mega-menu')) + '</button>' +
						'</div>'
					: '<div class="abmm-nav-field">' +
						'<label for="' + urlId + '">' + esc(__('URL', 'ashbi-mega-menu')) + '</label>' +
						wpLinkFieldHtml(item.url || '', 'abmm-nav-url', '.abmm-nav-label', false, item).replace('<input type="text" class="', '<input type="text" id="' + urlId + '" class="') +
						'</div>') +
				'</div>' +
				'</li>'
			);
			$list.append($row);
		});

		$list.sortable({
			handle: '.abmm-drag-handle',
			update: function () {
				var order = [];
				$list.children().each(function () {
					var id = $(this).data('id');
					var found = getMenu().items.find(function (it) {
						return it.id === id;
					});
					if (found) order.push(found);
				});
				getMenu().items = order;
				renderPreview();
			},
		});
	}

	function openMegaEditor(index, opener) {
		state.editingItemIndex = index;
		state.megaEditorOpener = opener || document.activeElement;
		var item = getMenu().items[index];
		// Filling in editor defaults is not an edit: keep a clean menu clean.
		var wasClean = state.trackingChanges && !hasUnsavedChanges();
		if (!item.mega_style) item.mega_style = 'platforms';
		if (!item.columns) item.columns = item.mega_style === 'features' ? 2 : 3;
		if (!item.categories) item.categories = [];
		if (!item.links) item.links = [];
		(item.categories || []).forEach(ensureCategoryGroups);
		if (wasClean) {
			state.savedSnapshot = JSON.stringify(getMenu());
			markDirtyState();
		}
		$('#abmm-mega-editor-title').text(item.label || __('Untitled item', 'ashbi-mega-menu'));
		$('#abmm-mega-editor').prop('hidden', false);
		$('#abmm-nav-items .abmm-nav-item-row').removeClass('is-selected').eq(index).addClass('is-selected');
		syncMegaEditorUI();
		if (megaEditorIsNarrow()) {
			syncMegaEditorSheetState();
			setTimeout(function () {
				$('#abmm-mega-editor .abmm-close-mega-editor').trigger('focus');
			}, 0);
		} else {
			// Clear the sticky top bar when scrolling the editor into view.
			var $header = $('.abmm-builder-header');
			var stuck = $header.length && $header.css('position') === 'sticky';
			var clearance = 16 + (stuck ? (parseFloat($header.css('top')) || 0) + ($header.outerHeight() || 0) : 32);
			$('html, body').animate({ scrollTop: $('#abmm-mega-editor').offset().top - clearance }, 200);
			// Opened from the drawer, the button that was pressed is now hidden: move focus into the editor.
			if (!$(state.megaEditorOpener).is(':visible') || $(state.megaEditorOpener).is('.abmm-builder-panel-toggle')) {
				setTimeout(function () { $('#abmm-mega-editor-title').trigger('focus'); }, 0);
			}
		}
	}

	// "Hide the category list" only makes sense with exactly one category,
	// so the option is left out entirely rather than shown disabled.
	function syncCategoryBarField(item) {
		var canHideCategoryBar = (item.mega_style || 'platforms') === 'platforms' && (item.categories || []).length === 1;
		$('#abmm-category-bar-field').prop('hidden', !canHideCategoryBar);
		$('#abmm-hide-category-bar')
			.prop('disabled', !canHideCategoryBar)
			.prop('checked', canHideCategoryBar && !!item.hide_category_bar);
	}

	function syncMegaEditorUI() {
		var item = getMenu().items[state.editingItemIndex];
		if (!item) return;
		var style = item.mega_style || 'platforms';

		$('.abmm-mega-style-card').removeClass('is-active');
		$('.abmm-mega-style-card[data-style="' + style + '"]').addClass('is-active');
		$('#abmm-mega-item-columns').val(String(item.columns || (style === 'features' ? 2 : 3)));
		syncCategoryBarField(item);
		syncFeaturedEditor(item);

		if (style === 'features') {
			$('#abmm-editor-platforms').prop('hidden', true);
			$('#abmm-editor-features').prop('hidden', false);
			renderFeaturesLinkList();
		} else {
			$('#abmm-editor-platforms').prop('hidden', false);
			$('#abmm-editor-features').prop('hidden', true);
			renderCatList();
			if (item.categories.length) {
				var catIdx = state.editingCatIndex;
				if (catIdx < 0 || catIdx >= item.categories.length) {
					catIdx = 0;
				}
				selectCategory(catIdx);
			} else {
				state.editingCatIndex = -1;
				showCatPlaceholder();
			}
		}
	}

	/* ---------- Featured card ---------- */
	var FEATURED_KEYS = ['title', 'text', 'label', 'url', 'image_url'];

	function featuredData(item) {
		var f = item && item.featured;
		return f && typeof f === 'object' && !Array.isArray(f) ? f : null;
	}

	function featuredIsEmpty(f) {
		return !f || FEATURED_KEYS.every(function (key) { return String(f[key] || '').trim() === ''; });
	}

	// An item without a card keeps no `featured` key, so older menus and
	// untouched items save exactly as before.
	function updateFeatured(item, changes) {
		var f = featuredData(item) || { show: false, title: '', text: '', label: '', url: '', image_url: '' };
		Object.keys(changes).forEach(function (key) { f[key] = changes[key]; });
		f.show = !!f.show;
		if (!f.show && featuredIsEmpty(f)) delete item.featured;
		else item.featured = f;
	}

	function syncFeaturedMedia(url) {
		url = String(url || '').trim();
		var $media = $('#abmm-featured-media');
		var $thumb = $media.find('.abmm-featured-media__thumb').empty().removeClass('has-image is-broken');
		if (url) {
			$('<img alt="" />').attr('src', url).on('error', function () {
				$thumb.addClass('is-broken').empty().append('<span class="dashicons dashicons-warning"></span>');
				$thumb.attr('title', __('This image could not be loaded.', 'ashbi-mega-menu'));
			}).appendTo($thumb);
			$thumb.addClass('has-image').removeAttr('title');
		} else {
			$thumb.removeAttr('title').append('<span class="dashicons dashicons-format-image"></span>');
		}
		$media.find('.abmm-featured-choose-image').text(url ? __('Replace image', 'ashbi-mega-menu') : __('Choose image', 'ashbi-mega-menu'));
		$media.find('.abmm-featured-remove-image').prop('hidden', !url);
	}

	function syncFeaturedEditor(item) {
		var f = featuredData(item) || {};
		var show = !!f.show;
		$('#abmm-featured-show').prop('checked', show).attr('aria-checked', show ? 'true' : 'false');
		$('#abmm-featured-fields').prop('hidden', !show);
		$('#abmm-featured-editor').toggleClass('is-on', show);
		$('#abmm-featured-editor .abmm-featured-input').each(function () {
			$(this).val(f[$(this).data('featured-key')] || '');
		});
		syncFeaturedMedia(f.image_url);
	}

	function initFeaturedEditor() {
		$('#abmm-featured-show').on('change', function () {
			var item = getMenu().items[state.editingItemIndex];
			if (!item) return;
			var on = $(this).is(':checked');
			updateFeatured(item, { show: on });
			syncFeaturedEditor(item);
			renderPreview();
			if (on) setTimeout(function () { $('#abmm-featured-title').trigger('focus'); }, 0);
		});
		$('#abmm-featured-editor').on('input change', '.abmm-featured-input', function () {
			var item = getMenu().items[state.editingItemIndex];
			if (!item) return;
			var key = String($(this).data('featured-key'));
			var change = {};
			change[key] = String($(this).val() || '');
			updateFeatured(item, change);
			if (key === 'image_url') syncFeaturedMedia(change[key]);
			renderPreview();
		});
		$('#abmm-featured-editor').on('click', '.abmm-featured-choose-image', function () {
			var opener = this;
			if (!window.wp || typeof window.wp.media !== 'function') {
				setStatus(__('The media library is not available. Paste an image URL instead.', 'ashbi-mega-menu'), true);
				$('#abmm-featured-image').trigger('focus');
				return;
			}
			var frame = window.wp.media({
				title: __('Choose a featured image', 'ashbi-mega-menu'),
				button: { text: __('Use image', 'ashbi-mega-menu') },
				library: { type: 'image' },
				multiple: false,
			});
			frame.on('select', function () {
				var att = frame.state().get('selection').first().toJSON();
				var url = (att.sizes && att.sizes.large && att.sizes.large.url) || att.url || '';
				$('#abmm-featured-image').val(url).trigger('change');
			});
			frame.on('close', function () { setTimeout(function () { $(opener).trigger('focus'); }, 0); });
			frame.open();
		});
		$('#abmm-featured-editor').on('click', '.abmm-featured-remove-image', function () {
			$('#abmm-featured-image').val('').trigger('change');
			$('#abmm-featured-editor .abmm-featured-choose-image').trigger('focus');
		});
	}

	// Same markup as the front end: a card after the link columns.
	function featuredPreviewMarkup(item) {
		var f = featuredData(item);
		if (!f || !f.show || featuredIsEmpty(f)) return '';
		var image = String(f.image_url || '').trim();
		var content =
			(image ? '<span class="abmm-mega__featured-media"><img src="' + esc(image) + '" alt="" loading="lazy" /></span>' : '') +
			(f.title ? '<span class="abmm-mega__featured-title">' + esc(f.title) + '</span>' : '') +
			(f.text ? '<span class="abmm-mega__featured-text">' + esc(f.text) + '</span>' : '') +
			(f.label ? '<span class="abmm-mega__featured-link">' + esc(f.label) + '</span>' : '');
		return '<a class="abmm-mega__featured" href="' + esc(String(f.url || '').trim() || '#') + '">' + content + '</a>';
	}

	function renderFeaturesLinkList() {
		var item = getMenu().items[state.editingItemIndex];
		var $list = $('#abmm-features-link-list').empty();
		if (!item) return;

		(item.links || []).forEach(function (link, index) {
			$list.append(linkRowHtml(link, index, item.links.length));
		});

		$list.sortable({
			handle: '.abmm-drag-handle',
			update: function () {
				var order = [];
				$list.children().each(function () {
					var id = $(this).data('id');
					var found = item.links.find(function (l) {
						return l.id === id;
					});
					if (found) order.push(found);
				});
				item.links = order;
				renderPreview();
			},
		});
	}

	function closeMegaEditor() {
		state.editingItemIndex = -1;
		$('#abmm-nav-items .abmm-nav-item-row').removeClass('is-selected');
		state.editingCatIndex = -1;
		var $editor = $('#abmm-mega-editor');
		var wasSheet = $editor.attr('role') === 'dialog';
		$editor.prop('hidden', true).removeAttr('role aria-modal aria-labelledby');
		if (wasSheet) {
			setMegaEditorBackgroundInert(false);
			if (!$('#abmm-builder-sidebar').hasClass('is-open')) {
				$('#abmm-builder-panel-backdrop').prop('hidden', true).attr('aria-hidden', 'true');
			}
			updateBuilderSheetBodyLock();
		}
		// Capture the opener: it is cleared below, before the timeout runs.
		var opener = state.megaEditorOpener;
		if (opener && document.documentElement.contains(opener)) {
			setTimeout(function () { $(opener).trigger('focus'); }, 0);
		}
		state.megaEditorOpener = null;
	}

	function renderCatList() {
		var item = getMenu().items[state.editingItemIndex];
		var $list = $('#abmm-cat-list').empty();
		if (!item) return;
		syncCategoryBarField(item);

		// A flat list: icon, name and description, with row actions on the right.
		(item.categories || []).forEach(function (cat, i) {
			var iconHtml = iconMarkup(cat.icon, cat.icon_url, '', cat.icon_color, cat.icon_size, cat);
			var catName = cat.title || String(i + 1);
			var $row = $(
				'<li class="abmm-cat-row' + (i === state.editingCatIndex ? ' is-selected' : '') + '" data-id="' + esc(cat.id) + '">' +
				'<div class="abmm-cat-row__head">' +
				'<span class="abmm-drag-handle" title="' + esc(S.dragHint) + '" aria-hidden="true"><span class="dashicons dashicons-menu"></span></span>' +
				'<button type="button" class="abmm-pick-icon" title="' + esc(__('Icon and icon style', 'ashbi-mega-menu')) + '" aria-label="' + /* translators: %s: category name. */ esc(sprintf(__('Choose an icon for %s', 'ashbi-mega-menu'), catName)) + '">' + iconHtml + '</button>' +
				'<div class="abmm-cat-row__fields">' +
				'<input type="text" class="abmm-cat-title" aria-label="' + /* translators: %s: category number. */ esc(sprintf(__('Category %s title', 'ashbi-mega-menu'), i + 1)) + '" value="' + esc(cat.title) + '" placeholder="' + esc(S.categoryTitle) + '" />' +
				'<input type="text" class="abmm-cat-desc" aria-label="' + /* translators: %s: category number. */ esc(sprintf(__('Category %s description', 'ashbi-mega-menu'), i + 1)) + '" value="' + esc(cat.description) + '" placeholder="' + esc(S.categoryDesc) + '" />' +
				'</div>' +
				'<span class="abmm-row-actions">' +
				/* translators: %s: category name. */
				reorderControlsHtml(sprintf(__('category %s', 'ashbi-mega-menu'), catName), i, item.categories.length) +
				'<button type="button" class="button-link-delete abmm-remove-cat" aria-label="' + /* translators: %s: category name. */ esc(sprintf(__('Remove category %s', 'ashbi-mega-menu'), catName)) + '" title="' + esc(__('Remove category', 'ashbi-mega-menu')) + '">' + iconButtonContent('trash', __('Remove', 'ashbi-mega-menu')) + '</button>' +
				'</span>' +
				'<button type="button" class="button-link abmm-select-category" aria-pressed="' + (i === state.editingCatIndex ? 'true' : 'false') + '" aria-label="' + /* translators: %s: category name. */ esc(sprintf(__('Edit category %s', 'ashbi-mega-menu'), catName)) + '" title="' + esc(__('Edit links', 'ashbi-mega-menu')) + '">' + iconButtonContent('arrow-right-alt2', __('Edit', 'ashbi-mega-menu')) + '</button>' +
				'</div>' +
				'</li>'
			);
			$list.append($row);
		});

		$list.sortable({
			handle: '.abmm-drag-handle',
			update: function () {
				var order = [];
				$list.children().each(function () {
					var id = $(this).data('id');
					var found = item.categories.find(function (c) {
						return c.id === id;
					});
					if (found) order.push(found);
				});
				item.categories = order;
				renderPreview();
			},
		});
	}

	function selectCategory(index) {
		state.editingCatIndex = index;
		$('#abmm-cat-list .abmm-cat-row').removeClass('is-selected').eq(index).addClass('is-selected');
		$('#abmm-cat-list .abmm-select-category').attr('aria-pressed', 'false').eq(index).attr('aria-pressed', 'true');
		renderCatDetail();
	}

	function ensureCategoryGroups(cat) {
		if (!cat) return;
		if (!Array.isArray(cat.groups)) cat.groups = [];
		if (cat.groups.length) return;
		if (Array.isArray(cat.links) && cat.links.length) {
			cat.groups.push({
				id: uid(),
				title: '',
				url: '',
				links: cat.links.slice(),
			});
			cat.links = [];
			return;
		}
		cat.groups.push({
			id: uid(),
			title: __('Column 1', 'ashbi-mega-menu'),
			url: '',
			links: [],
		});
	}

	function renderCatDetail() {
		var item = getMenu().items[state.editingItemIndex];
		var cat = item && item.categories[state.editingCatIndex];
		var $detail = $('#abmm-cat-detail');
		if (!cat) {
			showCatPlaceholder();
			return;
		}

		ensureCategoryGroups(cat);

		var panelTitleId = 'abmm-panel-title-' + state.editingCatIndex;
		var html =
			'<div class="abmm-cat-detail-inner">' +
			'<div class="abmm-cat-detail__head">' +
			'<p class="abmm-cat-detail__eyebrow">' + esc(__('Category', 'ashbi-mega-menu')) + '</p>' +
			'<h3 class="abmm-cat-detail__title">' + esc(cat.title || __('Untitled category', 'ashbi-mega-menu')) + '</h3>' +
			'</div>' +
			'<div class="abmm-cat-detail__heading">' +
			'<label class="abmm-cat-detail__label" for="' + panelTitleId + '">' + esc(S.panelTitle) + '</label>' +
			'<div class="abmm-cat-detail__heading-row">' +
			'<input type="text" id="' + panelTitleId + '" class="abmm-panel-title" value="' +
			esc(cat.panel_title || '') +
			'" />' +
			headingLinkPopoverHtml('abmm-panel-link-options', 'abmm-panel-url', '.abmm-panel-title', cat.panel_link || {}, cat.panel_url) +
			'</div>' +
			'</div>' +
			'<div class="abmm-col-editor-head">' +
			'<div>' +
			'<h3>' + esc(__('Columns', 'ashbi-mega-menu')) + '</h3>' +
			'<p class="description">' + esc(__('Group links into columns. Drag a link to move it between columns.', 'ashbi-mega-menu')) + '</p>' +
			'</div>' +
			'<button type="button" class="button abmm-add-group"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>' + esc(__('Add column', 'ashbi-mega-menu')) + '</button>' +
			'</div>' +
			'<div class="abmm-group-list" id="abmm-group-list"></div>' +
			'</div>';

		// renderCatDetail() is called after every column/link mutation. Clear the
		// delegated handlers from the previous render before binding the fresh
		// category model, otherwise one click is handled once per prior render.
		$detail.off('.abmmCatDetail').html(html);

		var $groupList = $('#abmm-group-list');
		(cat.groups || []).forEach(function (group, gi) {
			$groupList.append(groupBlockHtml(group, gi, (cat.groups || []).length));
		});

		$detail.find('.abmm-panel-title').on('input', function () {
			cat.panel_title = $(this).val();
			renderPreview();
		});

		$detail.find('.abmm-panel-url').on('input change', function () {
			cat.panel_url = $(this).val();
			renderPreview();
		});

		$detail.find('.abmm-panel-link-options').on('input change', 'input', function () {
			cat.panel_link = cat.panel_link || {};
			readLinkOptions($(this).closest('.abmm-panel-link-options'), cat.panel_link);
			renderPreview();
		});

		$detail.find('.abmm-add-group').on('click', function () {
			cat.groups.push({
				id: uid(),
				/* translators: %s: column number. */
				title: sprintf(__('Column %s', 'ashbi-mega-menu'), cat.groups.length + 1),
				url: '',
				links: [],
			});
			renderCatDetail();
			renderPreview();
		});

		$detail.on('click.abmmCatDetail', '.abmm-remove-group', function () {
			if (cat.groups.length <= 1) {
				setStatus(__('Keep at least one column.', 'ashbi-mega-menu'), true);
				return;
			}
			var gid = $(this).closest('.abmm-group-block').data('id');
			var index = cat.groups.findIndex(function (g) { return g.id === gid; }); if (index < 0) return;
			var removed = cat.groups.splice(index, 1)[0]; renderCatDetail(); renderPreview();
			setUndoStatus(__('Column removed.', 'ashbi-mega-menu'), function () { cat.groups.splice(index, 0, removed); renderCatDetail(); renderPreview(); setStatus(__('Column restored.', 'ashbi-mega-menu'), false); });
		});

		$detail.on('input.abmmCatDetail', '.abmm-group-title', function () {
			var gid = $(this).closest('.abmm-group-block').data('id');
			var group = cat.groups.find(function (g) {
				return g.id === gid;
			});
			if (!group) return;
			group.title = $(this).val();
			renderPreview();
		});

		$detail.on('input.abmmCatDetail change.abmmCatDetail', '.abmm-group-url', function () {
			var gid = $(this).closest('.abmm-group-block').data('id');
			var group = cat.groups.find(function (g) {
				return g.id === gid;
			});
			if (!group) return;
			group.url = $(this).val();
			renderPreview();
		});

		$detail.on('input.abmmCatDetail change.abmmCatDetail', '.abmm-group-heading-options input', function () {
			var $wrap = $(this).closest('.abmm-group-heading-options');
			var gid = $wrap.closest('.abmm-group-block').data('id');
			var group = cat.groups.find(function (g) {
				return g.id === gid;
			});
			if (!group) return;
			readLinkOptions($wrap, group);
			renderPreview();
		});

		$detail.on('click.abmmCatDetail', '.abmm-add-group-link', function () {
			var gid = $(this).closest('.abmm-group-block').data('id');
			var group = cat.groups.find(function (g) {
				return g.id === gid;
			});
			if (!group) return;
			if (!group.links) group.links = [];
			group.links.push({
				id: uid(),
				label: __('New Link', 'ashbi-mega-menu'),
				description: '',
				url: '#',
				icon: '',
				icon_url: '',
				icon_color: '',
				icon_size: 0,
			});
			renderCatDetail();
			renderPreview();
		});

		$detail.on('click.abmmCatDetail', '.abmm-remove-link', function () {
			var $row = $(this).closest('.abmm-link-row');
			var $block = $(this).closest('.abmm-group-block');
			var gid = $block.data('id');
			var lid = $row.data('id');
			var group = cat.groups.find(function (g) {
				return g.id === gid;
			});
			if (!group) return;
			var index = (group.links || []).findIndex(function (l) { return l.id === lid; }); if (index < 0) return;
			var removed = group.links.splice(index, 1)[0]; renderCatDetail(); renderPreview();
			setUndoStatus(__('Link removed.', 'ashbi-mega-menu'), function () { group.links.splice(index, 0, removed); renderCatDetail(); renderPreview(); setStatus(__('Link restored.', 'ashbi-mega-menu'), false); });
		});

		$detail.on('input.abmmCatDetail change.abmmCatDetail', '.abmm-link-label, .abmm-link-description, .abmm-link-url, .abmm-link-target, .abmm-link-rel, .abmm-link-class', function () {
			var $row = $(this).closest('.abmm-link-row');
			var $block = $(this).closest('.abmm-group-block');
			var gid = $block.data('id');
			var lid = $row.data('id');
			var group = cat.groups.find(function (g) {
				return g.id === gid;
			});
			if (!group) return;
			var link = (group.links || []).find(function (l) {
				return l.id === lid;
			});
			if (!link) return;
			link.label = $row.find('.abmm-link-label').val();
			link.description = $row.find('.abmm-link-description').val() || '';
			link.url = $row.find('.abmm-link-url').val();
			readLinkOptions($row, link);
			renderPreview();
		});

		$detail.on('click.abmmCatDetail', '.abmm-pick-link-icon', function () {
			var $row = $(this).closest('.abmm-link-row');
			var $block = $(this).closest('.abmm-group-block');
			openIconPicker({
				type: 'group-link',
				catIndex: state.editingCatIndex,
				groupId: $block.data('id'),
				linkId: $row.data('id'),
			});
		});

		function syncGroupLinksFromDom() {
			var linksById = {};
			(cat.groups || []).forEach(function (group) {
				(group.links || []).forEach(function (link) {
					linksById[link.id] = link;
				});
			});

			$groupList.find('.abmm-group-block').each(function () {
				var $block = $(this);
				var group = cat.groups.find(function (candidate) {
					return candidate.id === $block.data('id');
				});
				if (!group) return;
				group.links = $block.find('.abmm-group-link-list > .abmm-link-row').map(function () {
					return linksById[$(this).data('id')];
				}).get().filter(Boolean);
			});
			renderPreview();
		}

		$groupList.find('.abmm-group-link-list').each(function () {
			var $list = $(this);
			$list.sortable({
				handle: '.abmm-drag-handle',
				connectWith: '#abmm-group-list .abmm-group-link-list',
				placeholder: 'abmm-link-row abmm-sortable-placeholder',
				update: syncGroupLinksFromDom,
				receive: syncGroupLinksFromDom,
			});
		});
	}

	// A column is a flat section: heading, optional heading link, then its links.
	function groupBlockHtml(group, groupIndex, groupLength) {
		var linksHtml = '';
		(group.links || []).forEach(function (link, index) {
			linksHtml += linkRowHtml(link, index, group.links.length, true);
		});
		var groupName = group.title || groupIndex + 1;

		return (
			'<div class="abmm-group-block" data-id="' +
			esc(group.id) +
			'">' +
			'<div class="abmm-group-block__head">' +
			'<input type="text" class="abmm-group-title" aria-label="' + esc(__('Column heading', 'ashbi-mega-menu')) + '" value="' +
			esc(group.title || '') +
			'" placeholder="' + esc(__('Column heading (e.g. Services)', 'ashbi-mega-menu')) + '" />' +
			headingLinkPopoverHtml('abmm-group-heading-options', 'abmm-group-url', '.abmm-group-title', group, group.url) +
			'<span class="abmm-row-actions">' +
			/* translators: %s: column name. */
			reorderControlsHtml(sprintf(__('column %s', 'ashbi-mega-menu'), groupName), groupIndex, groupLength) +
			'<button type="button" class="button-link-delete abmm-remove-group" aria-label="' + /* translators: %s: column name. */ esc(sprintf(__('Remove column %s', 'ashbi-mega-menu'), groupName)) + '" title="' + esc(__('Remove column', 'ashbi-mega-menu')) + '">' + iconButtonContent('trash', __('Remove column', 'ashbi-mega-menu')) + '</button>' +
			'</span>' +
			'</div>' +
			'<ul class="abmm-sortable abmm-link-list abmm-group-link-list" data-empty="' + esc(__('No links yet. Add one, or drag a link here.', 'ashbi-mega-menu')) + '">' +
			linksHtml +
			'</ul>' +
			'<button type="button" class="button-link abmm-add-group-link"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>' +
			esc(S.addLink) +
			'</button>' +
			'</div>'
		);
	}

	// One line per link: icon, label (with an optional description under it),
	// a quiet URL chip, then row actions. The chip is the one button that opens
	// the link popover: URL with the WordPress link picker, and link options.
	function linkEditPopoverHtml(link) {
		link = link || {};
		var id = 'abmm-link-edit-' + (++popoverSeq);
		return (
			'<div class="abmm-link-edit abmm-popover' + (linkHasOptions(link) ? ' has-options' : '') + '">' +
			'<button type="button" class="abmm-link-edit__toggle abmm-popover__toggle" aria-expanded="false" aria-controls="' + id + '" title="' + esc(__('Edit link', 'ashbi-mega-menu')) + '">' +
			linkChipContent(link.url) +
			'</button>' +
			'<div class="abmm-link-edit__panel abmm-popover__panel" id="' + id + '" role="group" aria-label="' + esc(__('Link', 'ashbi-mega-menu')) + '" hidden>' +
			'<p class="abmm-popover__title" aria-hidden="true">' + esc(__('Link', 'ashbi-mega-menu')) + '</p>' +
			wpLinkFieldHtml(link.url || '', 'abmm-link-url', '.abmm-link-label', false) +
			'<div class="abmm-popover__divider" aria-hidden="true"></div>' +
			'<label class="abmm-popover__check"><input type="checkbox" class="abmm-link-target"' +
			(link.target === '_blank' ? ' checked' : '') +
			' /> ' + esc(__('Open in new tab', 'ashbi-mega-menu')) + '</label>' +
			'<div class="abmm-popover__pair">' +
			'<label class="abmm-popover__field"><span class="abmm-popover__label">' + esc(__('Relationship', 'ashbi-mega-menu')) + '</span><input type="text" class="abmm-link-rel" value="' +
			esc(link.rel || '') +
			'" placeholder="nofollow sponsored" /></label>' +
			'<label class="abmm-popover__field"><span class="abmm-popover__label">' + esc(__('CSS classes', 'ashbi-mega-menu')) + '</span><input type="text" class="abmm-link-class" value="' +
			esc(link.class || '') +
			'" /></label></div></div></div>'
		);
	}

	function linkChipContent(url) {
		var path = linkPathText(url);
		return '<span class="dashicons dashicons-admin-links" aria-hidden="true"></span>' +
			'<span class="abmm-link-edit__url' + (path ? '' : ' is-empty') + '">' + esc(path || __('Add a link', 'ashbi-mega-menu')) + '</span>' +
			'<span class="screen-reader-text">' + esc(__('Edit link', 'ashbi-mega-menu')) + '</span>';
	}

	function syncLinkChip($popover) {
		var url = String($popover.find('.abmm-link-url').val() || '');
		var path = linkPathText(url);
		$popover.find('.abmm-link-edit__url').toggleClass('is-empty', !path).text(path || __('Add a link', 'ashbi-mega-menu'));
		$popover.find('.abmm-link-edit__toggle').attr('title', url.trim() ? url.trim() : __('Edit link', 'ashbi-mega-menu'));
		$popover.toggleClass('has-options', $popover.find('.abmm-link-target').is(':checked') ||
			String($popover.find('.abmm-link-rel').val() || '').trim() !== '' ||
			String($popover.find('.abmm-link-class').val() || '').trim() !== '');
	}

	function linkRowHtml(link, linkIndex, linkLength, withDescription) {
		var linkName = link.label || linkIndex + 1;
		return (
			'<li class="abmm-link-row' + (withDescription ? ' has-description' : '') + '" data-id="' +
			esc(link.id) +
			'">' +
			'<span class="abmm-drag-handle" title="' + esc(S.dragHint) + '" aria-hidden="true"><span class="dashicons dashicons-menu"></span></span>' +
			'<button type="button" class="abmm-pick-link-icon" title="' + esc(__('Icon and icon style', 'ashbi-mega-menu')) + '" aria-label="' +
			/* translators: %s: link name. */
			esc(sprintf(__('Choose an icon for %s', 'ashbi-mega-menu'), linkName)) +
			'">' +
			iconMarkup(link.icon, link.icon_url, '', link.icon_color, link.icon_size, link) +
			'</button>' +
			'<div class="abmm-link-row__text">' +
			'<input type="text" class="abmm-link-label" aria-label="' + esc(__('Link label', 'ashbi-mega-menu')) + '" value="' +
			esc(link.label) +
			'" placeholder="' +
			esc(S.linkLabel) +
			'" />' +
			(withDescription
				? '<input type="text" class="abmm-link-description" aria-label="' + esc(__('Link description', 'ashbi-mega-menu')) + '" value="' + esc(link.description || '') + '" placeholder="' + esc(__('Short description (optional)', 'ashbi-mega-menu')) + '" />'
				: '') +
			'</div>' +
			linkEditPopoverHtml(link) +
			'<span class="abmm-row-actions">' +
			/* translators: %s: link name. */
			reorderControlsHtml(sprintf(__('link %s', 'ashbi-mega-menu'), linkName), linkIndex, linkLength) +
			'<button type="button" class="button-link-delete abmm-remove-link" aria-label="' + /* translators: %s: link name. */ esc(sprintf(__('Remove link %s', 'ashbi-mega-menu'), linkName)) + '" title="' + esc(__('Remove link', 'ashbi-mega-menu')) + '">' + iconButtonContent('trash', __('Remove link', 'ashbi-mega-menu')) + '</button>' +
			'</span>' +
			'</li>'
		);
	}

	/* ---------- Preview ---------- */
	function previewLinkAttrs(link, baseClass) {
		link = link || {};
		var classes = (baseClass || '') + (link.class ? ' ' + link.class : '');
		var attrs = 'class="' + esc(classes.trim()) + '"';
		if (link.target === '_blank') attrs += ' target="_blank"';
		if (link.rel) attrs += ' rel="' + esc(link.rel) + '"';
		return attrs;
	}

	function previewLinkMarkup(link, baseClass, content, alwaysAnchor) {
		link = link || {};
		var hasUrl = String(link.url || '').trim() !== '';
		if (!hasUrl && !alwaysAnchor) {
			return '<span class="' + esc((baseClass + ' is-disabled').trim()) + '" aria-disabled="true">' + content + '</span>';
		}
		return '<a ' + previewLinkAttrs(link, baseClass) + ' href="' + esc(link.url || '#') + '">' + content + '</a>';
	}

	// The preview renders at a real screen width and is scaled to fit, so a
	// desktop menu is not squeezed into the space between the sidebars.
	// 1100px keeps the desktop layout (above the 900px mobile breakpoint)
	// while Fit stays around 75%, so preview text is readable.
	var PREVIEW_WIDTHS = { desktop: 1100, tablet: 820, mobile: 390 };
	var PREVIEW_ZOOM_STEPS = [0.25, 0.33, 0.5, 0.67, 0.75, 0.9, 1, 1.25, 1.5, 2];

	function applyPreviewZoom() {
		var shell = $('.abmm-preview-shell')[0];
		var stage = shell && shell.querySelector('.abmm-preview-stage');
		var sizer = shell && shell.querySelector('.abmm-preview-sizer');
		var preview = stage && stage.querySelector('.abmm-preview');
		if (!stage || !sizer) return;
		var device = state.previewViewport;
		var width = PREVIEW_WIDTHS[device] || PREVIEW_WIDTHS.desktop;
		var current = state.previewScale || 1;
		// The browser frame grows to hold an open dropdown, so the panel sits
		// inside the window instead of hanging below it.
		if (preview) {
			preview.style.minHeight = '';
			if (device === 'desktop') {
				var previewTop = preview.getBoundingClientRect().top;
				var needed = 0;
				$(preview).find('.abmm-mega').filter(':visible').each(function () {
					needed = Math.max(needed, (this.getBoundingClientRect().bottom - previewTop) / current + 24);
				});
				if (needed) preview.style.minHeight = Math.ceil(needed) + 'px';
			}
		}
		var style = window.getComputedStyle ? window.getComputedStyle(shell) : null;
		var padX = style ? (parseFloat(style.paddingLeft) || 0) + (parseFloat(style.paddingRight) || 0) : 32;
		var padY = style ? (parseFloat(style.paddingTop) || 0) + (parseFloat(style.paddingBottom) || 0) : 32;
		var available = Math.max(160, shell.clientWidth - padX);
		var scale = state.previewZoom;
		if (state.previewZoom === 'fit' || !state.previewZoom) {
			scale = Math.min(1, available / width);
			if (device !== 'desktop') {
				// Devices also fit the window's height, so the whole phone (and its
				// CTA) is visible without scrolling the canvas.
				var top = Math.max(0, shell.getBoundingClientRect().top);
				var availableHeight = Math.max(360, (window.innerHeight || 900) - Math.min(top, 220) - padY - 24);
				var naturalHeight = stage.offsetHeight || 1;
				scale = Math.min(scale, availableHeight / naturalHeight);
			}
			scale = Math.max(0.25, Math.floor(scale * 100) / 100);
		}
		state.previewScale = scale;
		stage.style.width = width + 'px';
		stage.style.transform = scale === 1 ? '' : 'scale(' + scale + ')';
		sizer.style.width = Math.round(width * scale) + 'px';
		// Open panels hang below the bar, so measure them too.
		var height = Math.max(stage.offsetHeight, stage.scrollHeight);
		var stageTop = stage.getBoundingClientRect().top;
		$(stage).find('.abmm-mega').filter(':visible').each(function () {
			height = Math.max(height, (this.getBoundingClientRect().bottom - stageTop) / scale + 24);
		});
		sizer.style.height = Math.round(height * scale) + 'px';
		$('.abmm-preview-zoom__value').text(Math.round(scale * 100) + '%');
		$('[data-abmm-zoom="fit"]').attr('aria-pressed', state.previewZoom === 'fit' ? 'true' : 'false');
	}

	function stepPreviewZoom(direction) {
		var current = state.previewScale || 1;
		var next = current;
		if (direction > 0) {
			next = PREVIEW_ZOOM_STEPS.filter(function (step) { return step > current + 0.001; })[0] || PREVIEW_ZOOM_STEPS[PREVIEW_ZOOM_STEPS.length - 1];
		} else {
			next = PREVIEW_ZOOM_STEPS.filter(function (step) { return step < current - 0.001; }).pop() || PREVIEW_ZOOM_STEPS[0];
		}
		state.previewZoom = next;
		applyPreviewZoom();
	}

	function initPreviewZoom() {
		state.previewZoom = 'fit';
		$('.abmm-preview-zoom').on('click', '[data-abmm-zoom]', function () {
			var action = $(this).attr('data-abmm-zoom');
			if (action === 'fit') {
				state.previewZoom = 'fit';
				applyPreviewZoom();
			} else {
				stepPreviewZoom(action === 'in' ? 1 : -1);
			}
		});
		// Ctrl/Cmd + scroll zooms the canvas, as in design tools.
		$('.abmm-preview-shell').on('wheel', function (event) {
			var original = event.originalEvent || event;
			if (!original.ctrlKey && !original.metaKey) return;
			event.preventDefault();
			stepPreviewZoom(original.deltaY < 0 ? 1 : -1);
		});
		$(window).on('resize', function () { if (state.previewZoom === 'fit') applyPreviewZoom(); });
		var stage = $('.abmm-preview-stage')[0];
		if (stage && typeof window.ResizeObserver === 'function') {
			// Keep the canvas height in step when a dropdown opens or closes.
			new window.ResizeObserver(function () { applyPreviewZoom(); }).observe(stage);
		}
	}

	function renderPreview() {
		syncSettingsFromForm();
		var m = getMenu();
		var s = m.settings || {};
		var style = settingsStyleAttr(s);
		var className = settingsClassList(s);
		var previewDrawerId = 'abmm-preview-drawer';
		syncCustomPresetSwatch(s);

		var itemsHtml = '';
		(m.items || []).forEach(function (item, idx) {
			var isMega = item.type === 'mega';
			var previewPanelId = 'abmm-preview-panel-' + idx;
			itemsHtml +=
				'<li class="abmm-nav__item' +
				(isMega ? ' abmm-nav__item--mega' : '') +
				'" data-abmm-preview-item="' + idx + '">' +
				(isMega
					? '<button type="button" class="abmm-nav__link abmm-nav__link--toggle" aria-expanded="false" aria-controls="' +
					  previewPanelId +
					  '" data-abmm-mega-trigger>' +
					  esc(item.label) +
					  ' <svg class="abmm-chevron" width="12" height="12" viewBox="0 0 12 12" aria-hidden="true"><path d="M2.5 4.5L6 8l3.5-3.5" fill="none" stroke="currentColor" stroke-width="1.5"/></svg></button>'
					: previewLinkMarkup(item, 'abmm-nav__link', esc(item.label), true));

			if (
				isMega &&
				((item.mega_style === 'features' && item.links && item.links.length) ||
					((!item.mega_style || item.mega_style === 'platforms') &&
						item.categories &&
						item.categories.length))
			) {
				itemsHtml += renderMegaPreview(item, idx);
			}
			itemsHtml += '</li>';
		});

		function previewBrandMarkup(location) {
			if (!m.brand || !m.brand.show || (!m.brand.logo_url && !m.brand.logo_dark_url && !m.brand.title)) return '';
			var brandLogo = s.preset === 'dark' && m.brand.logo_dark_url ? m.brand.logo_dark_url : m.brand.logo_url;
			var brandLogoHeight = Math.min(80, Math.max(16, parseInt(m.brand.logo_height, 10) || 40));
			var content =
				(brandLogo
					? '<img class="abmm-nav__brand-logo" src="' +
					  esc(brandLogo) +
					  '" alt="' +
					  esc(m.brand.alt || m.brand.title || '') +
					  '" style="--abmm-brand-logo-height:' +
					  brandLogoHeight +
					  'px" />'
					: '') +
				(m.brand.title ? '<span class="abmm-nav__brand-title">' + esc(m.brand.title) + '</span>' : '');
			var className = 'abmm-nav__brand abmm-nav__brand--' + location;
			return m.brand.url
				? '<a ' + previewLinkAttrs(m.brand, className) + ' href="' + esc(m.brand.url) + '">' + content + '</a>'
				: '<span class="' + className + '">' + content + '</span>';
		}

		function previewContextMarkup(location) {
			if (!m.context || !m.context.show || !m.context.label) return '';
			var className = 'abmm-nav__context abmm-nav__context--' + location;
			var content =
				'<span class="screen-reader-text">' + esc(__('Current section:', 'ashbi-mega-menu')) + ' </span>' +
				'<span class="abmm-nav__context-label">' +
				 esc(m.context.label) +
				'</span>';
			return m.context.url
				? '<a ' + previewLinkAttrs(m.context, className) + ' href="' + esc(m.context.url) + '">' + content + '</a>'
				: '<span class="' + className + '">' + content + '</span>';
		}

		function previewCtaMarkup(location) {
			if (!m.cta || !m.cta.show || !m.cta.label) return '';
			var href = esc(m.cta.url || '#');
			return '<a ' + previewLinkAttrs(m.cta, 'abmm-nav__cta abmm-nav__cta--' + location) + ' href="' + href + '">' + esc(m.cta.label) + '</a>';
		}

		var mobilePreview = state.previewViewport !== 'desktop';
		var mobileOpen = mobilePreview && state.previewMobileOpen;
		var drillMarkup =
			mobilePreview && state.previewDrill !== 'root'
				? '<div class="abmm-preview-mobile-bar"><button type="button" data-abmm-preview-back aria-label="' + esc(__('Back to menu', 'ashbi-mega-menu')) + '">&larr; <span>' + esc(__('Back', 'ashbi-mega-menu')) + '</span></button><strong>' +
				  esc(state.previewDrill === 'links' ? __('Choose a link', 'ashbi-mega-menu') : __('Choose a section', 'ashbi-mega-menu')) +
				  '</strong></div>'
				: '';
		var mobileToggleLabel = mobileOpen ? __('Close menu', 'ashbi-mega-menu') : __('Open menu', 'ashbi-mega-menu');

		var html =
			'<div class="' +
			className +
			(mobilePreview ? ' abmm-force-mobile' : '') +
			(mobileOpen ? ' is-mobile-open' : '') +
			(mobilePreview && state.previewDrill !== 'root' ? ' abmm-is-drilling' : '') +
			'" style="' +
			esc(style) +
			'">' +
			'<nav class="abmm-nav" aria-label="' + esc(m.title || __('Menu', 'ashbi-mega-menu')) + '">' +
			'<div class="abmm-nav__bar">' +
			previewBrandMarkup('bar') +
			previewContextMarkup('bar') +
			'<button type="button" class="abmm-nav__toggle" aria-expanded="' +
			(mobileOpen ? 'true' : 'false') +
			'" aria-controls="' +
			previewDrawerId +
			'" aria-label="' +
			mobileToggleLabel +
			'"><span class="abmm-nav__toggle-box" aria-hidden="true"><span class="abmm-nav__toggle-bar"></span></span></button>' +
			previewCtaMarkup('bar') +
			'</div>' +
			'<div class="abmm-nav__drawer" id="' +
			previewDrawerId +
			'">' +
			/* No title row: as on the live site, the bar's toggle turns into the close button. */
			drillMarkup +
			'<div class="abmm-nav__inner">' +
			previewBrandMarkup('drawer') +
			previewContextMarkup('drawer') +
			'<ul class="abmm-nav__list">' +
			itemsHtml +
			'</ul>' +
			previewCtaMarkup('drawer') +
			'</div></div>' +
			'<button type="button" class="abmm-nav__backdrop" data-abmm-preview-backdrop' + (mobileOpen ? '' : ' hidden') + ' aria-label="' + esc(__('Close menu', 'ashbi-mega-menu')) + '"></button>' +
			'</nav></div>' +
			// A quiet page under the menu, so the desktop frame reads as a website.
			(mobilePreview ? '' : '<div class="abmm-preview-page" aria-hidden="true"><span class="abmm-preview-page__eyebrow"></span><span class="abmm-preview-page__title"></span><span class="abmm-preview-page__title is-short"></span><span class="abmm-preview-page__line"></span><span class="abmm-preview-page__line is-short"></span><span class="abmm-preview-page__actions"><span></span><span></span></span></div>');

		var previewState = state.previewViewport + '-' + state.previewDrill;
		$('#abmm-preview').attr('data-abmm-preview-state', previewState).html(html);
		applyPreviewZoom();
		var $preview = $('#abmm-preview');
		$preview.find('.abmm-nav__brand-logo').on('error', function () {
			$(this).replaceWith(
				'<span class="abmm-preview-broken-logo" role="img" aria-label="' + esc(S.logoBroken) + '">' + esc(S.logoBroken) + '</span>'
			);
		});
		$preview.find('.abmm-header').toggleClass('abmm-force-mobile', mobilePreview);
		if (mobilePreview && state.previewDrill === 'root' && window.ABMMMenuSearch) {
			window.ABMMMenuSearch.init($preview.find('.abmm-header')[0]);
		}
		if (!mobilePreview && state.previewItemIndex < 0) {
			state.previewItemIndex = Number($preview.find('.abmm-nav__item--mega').first().data('abmm-preview-item'));
		}
		var $selected = $preview.find('[data-abmm-preview-item="' + state.previewItemIndex + '"]');
		if (!mobilePreview || state.previewDrill !== 'root') {
			$selected.addClass('is-open');
			$selected.find('> .abmm-nav__link').attr('aria-expanded', 'true');
		}
		// The open panel changes the stage height, so size the canvas again.
		applyPreviewZoom();

		if (mobilePreview && state.previewDrill !== 'root') {
			$selected.attr('data-drill', state.previewDrill);
		}

		$preview.off('.abmmpreview')
			.on('click.abmmpreview', 'a', function (event) {
				/* Preview links mirror the live destination but must not navigate away
				 * from the builder while a user is inspecting the layout. */
				event.preventDefault();
			})
			.on('click.abmmpreview', '.abmm-nav__toggle', function () {
				var toggle = this;
				state.previewMobileOpen = !state.previewMobileOpen;
				if (!state.previewMobileOpen) {
					state.previewDrill = 'root';
					state.previewItemIndex = -1;
					state.previewCategoryId = '';
				}
				renderPreview();
				setTimeout(function () { $('#abmm-preview').find('.abmm-nav__toggle').trigger('focus'); }, 0);
			})
			.on('click.abmmpreview', '[data-abmm-preview-drawer-close], [data-abmm-preview-backdrop]', function () {
				state.previewMobileOpen = false;
				state.previewDrill = 'root';
				state.previewItemIndex = -1;
				state.previewCategoryId = '';
				renderPreview();
				setTimeout(function () { $('#abmm-preview .abmm-nav__toggle').trigger('focus'); }, 0);
			})
			.on('click.abmmpreview', '[data-abmm-preview-back]', function () {
				state.previewDrill = state.previewDrill === 'links' ? 'cats' : 'root';
				if (state.previewDrill === 'root') {
					state.previewItemIndex = -1;
					state.previewCategoryId = '';
				}
				renderPreview();
				setTimeout(function () { $('#abmm-preview [data-abmm-preview-back]').trigger('focus'); }, 0);
			})
			.on('click.abmmpreview', '[data-abmm-preview-item] > .abmm-nav__link', function () {
				var $item = $(this).parent();
				if (!$item.hasClass('abmm-nav__item--mega')) return;
				state.previewItemIndex = Number($item.data('abmm-preview-item'));
				var selected = m.items && m.items[state.previewItemIndex];
				var directToLinks = selected && (selected.mega_style === 'features' || (selected.hide_category_bar && (selected.categories || []).length === 1));
				state.previewDrill = mobilePreview ? (directToLinks ? 'links' : 'cats') : 'root';
				state.previewCategoryId = '';
				renderPreview();
				if (mobilePreview) {
					setTimeout(function () {
						var selector = state.previewDrill === 'links' ? '.abmm-mega__panel a, .abmm-mega__grid a' : '.abmm-mega__cat';
						$('#abmm-preview').find(selector).filter(':visible').first().trigger('focus');
					}, 0);
				}
			})
			.on('click.abmmpreview mouseenter.abmmpreview', '.abmm-mega__cat', function (event) {
			if (event.type === 'mouseenter' && mobilePreview) return;
			var catId = $(this).data('abmm-cat');
			state.previewCategoryId = String(catId);
			if (mobilePreview && event.type === 'click') state.previewDrill = 'links';
			var $mega = $(this).closest('.abmm-mega');
			$mega.find('.abmm-mega__section').each(function () {
				var on = $(this).data('abmm-section') === catId;
				$(this).toggleClass('is-open', on);
				$(this).find('.abmm-mega__cat').toggleClass('is-active', on).attr('aria-expanded', on ? 'true' : 'false');
				$(this).find('.abmm-mega__panel').toggleClass('is-active', on).prop('hidden', !on);
			});
			if (mobilePreview && event.type === 'click') {
				renderPreview();
				setTimeout(function () {
					$('#abmm-preview').find('.abmm-mega__panel:not([hidden]) a, .abmm-mega__grid a').filter(':visible').first().trigger('focus');
				}, 0);
			}
		});
		refreshNavSummaries();
		renderReadiness();
		markDirtyState();
	}

	// Keep each inspector row's summary ("3 categories · 12 links") current.
	function refreshNavSummaries() {
		var items = (getMenu() && getMenu().items) || [];
		$('#abmm-nav-items > .abmm-nav-item-row').each(function () {
			var id = $(this).data('id');
			var item = items.find(function (candidate) { return candidate.id === id; });
			if (!item) return;
			var text = navSummaryText(item);
			$(this).find('.abmm-mega-style-badge, .abmm-nav-item-row__url').text(text).attr('title', text);
		});
	}

	function renderMegaPreview(item, itemIndex) {
		var style = item.mega_style || 'platforms';
		var cols = previewNumberValue(item.columns, style === 'features' ? 2 : 3, 1, 4);
		var previewPanelId = 'abmm-preview-panel-' + (itemIndex == null ? 0 : itemIndex);

		if (style === 'features') {
			var featLinks = '';
			(item.links || []).forEach(function (link) {
				var featureContent =
					'<span class="abmm-mega__grid-icon">' +
					iconMarkup(link.icon, link.icon_url, 'abmm-icon abmm-icon--sm', link.icon_color, link.icon_size, link) +
					'</span>' +
					'<span class="abmm-mega__grid-label">' +
					esc(link.label) +
					'</span>';
				featLinks +=
					'<li class="abmm-mega__grid-item">' +
					previewLinkMarkup(link, 'abmm-mega__grid-link', featureContent, false) +
					'</li>';
			});
			return (
				'<div class="abmm-mega abmm-mega--features" id="' +
				previewPanelId +
				'" data-abmm-mega-panel data-abmm-style="features">' +
				'<div class="abmm-mega__inner abmm-mega__inner--features">' +
				'<ul class="abmm-mega__grid abmm-mega__grid--features" style="--abmm-grid-cols:' +
				cols +
				'">' +
				featLinks +
				'</ul>' + featuredPreviewMarkup(item) + '</div></div>'
			);
		}

		var cats = item.categories || [];
		var sections = '';
		var featuredHtml = featuredPreviewMarkup(item);

		cats.forEach(function (cat, i) {
			var active = state.previewCategoryId ? String(cat.id) === String(state.previewCategoryId) : i === 0;
			var catId = String(cat.id || 'cat-' + i);
			var catUid = previewPanelId + '-cat-' + i + '-' + catId.replace(/[^A-Za-z0-9_-]/g, '-');
			ensureCategoryGroups(cat);
			var groups = cat.groups || [];
			var colsHtml = '';
			groups.forEach(function (group) {
				var links = '';
				(group.links || []).forEach(function (link) {
					var hasIcon = !!(link.icon || link.icon_url);
					var columnContent =
						(hasIcon
							? '<span class="abmm-mega__col-icon">' +
							  iconMarkup(link.icon, link.icon_url, 'abmm-icon abmm-icon--sm', link.icon_color, link.icon_size, link) +
							  '</span>'
							: '') +
						'<span class="abmm-mega__col-copy">' +
						'<span class="abmm-mega__col-label">' +
						esc(link.label) +
						'</span>' +
						(link.description ? '<span class="abmm-mega__col-description">' + esc(link.description) + '</span>' : '') +
						'</span>';
					links +=
						'<li class="abmm-mega__col-item">' +
						previewLinkMarkup(link, 'abmm-mega__col-link' + (hasIcon ? ' has-icon' : ''), columnContent, false) +
						'</li>';
				});
				var colTitle = '';
				if (group.title) {
					colTitle =
						'<h4 class="abmm-mega__col-title">' +
						(group.url
							? '<a href="' + esc(group.url) + '">' + esc(group.title) + '</a>'
							: esc(group.title)) +
						'</h4>';
				}
				colsHtml +=
					'<div class="abmm-mega__col">' +
					colTitle +
					'<ul class="abmm-mega__col-list">' +
					links +
					'</ul></div>';
			});

			sections +=
				'<section class="abmm-mega__section' +
				(active ? ' is-open' : '') +
				'" data-abmm-section="' +
				esc(catId) +
				'" style="' +
				iconScopeStyle(cat) +
				'">' +
				'<button type="button" class="abmm-mega__cat' +
				(active ? ' is-active' : '') +
				'" id="' +
				esc(catUid + '-trigger') +
				'" aria-controls="' +
				esc(catUid + '-panel') +
				'" data-abmm-cat="' +
				esc(catId) +
				'" aria-expanded="' +
				(active ? 'true' : 'false') +
				'">' +
				'<span class="abmm-mega__cat-icon">' +
				iconMarkup(cat.icon, cat.icon_url, 'abmm-icon abmm-icon--lg', cat.icon_color, cat.icon_size, cat) +
				'</span>' +
				'<span class="abmm-mega__cat-text">' +
				'<span class="abmm-mega__cat-title">' +
				esc(cat.title) +
				'</span>' +
				(cat.description
					? '<span class="abmm-mega__cat-desc">' + esc(cat.description) + '</span>'
					: '') +
				'</span><span class="abmm-mega__cat-chevron" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 12 12"><path d="M2.5 4.5L6 8l3.5-3.5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span></button>' +
				'<div class="abmm-mega__panel' +
				(active ? ' is-active' : '') +
				'" id="' +
				esc(catUid + '-panel') +
				'" role="region" aria-labelledby="' +
				esc(catUid + '-trigger') +
				'" data-abmm-panel="' +
				esc(catId) +
				'"' +
				(active ? '' : ' hidden') +
				'>' +
				(cat.panel_title
					? '<h3 class="abmm-mega__panel-title">' +
					  (cat.panel_url
							? '<a href="' + esc(cat.panel_url) + '">' + esc(cat.panel_title) + '</a>'
							: esc(cat.panel_title)) +
					  '</h3>'
					: '') +
				'<div class="abmm-mega__columns" style="--abmm-col-count:' +
				Math.max(1, groups.length) +
				'">' +
				colsHtml +
				'</div>' + featuredHtml + '</div></section>';
		});

		var hideCategoryBar = !!item.hide_category_bar && cats.length === 1;
		return (
			'<div class="abmm-mega abmm-mega--platforms' +
			(hideCategoryBar ? ' abmm-mega--columns-only' : '') +
			'" id="' +
			previewPanelId +
			'" data-abmm-mega-panel data-abmm-style="platforms">' +
			'<div class="abmm-mega__inner">' +
			'<div class="abmm-mega__accordion">' +
			sections +
			'</div></div></div>'
		);
	}

	/* ---------- Icons ---------- */
	function iconScopeStyle(style) {
		style = style || {};
		var vars = [];
		function addColor(name, value) {
			var safe = previewColorValue(value, '');
			if (safe) vars.push(name + safe);
		}
		addColor('--abmm-icon-color:', style.icon_color);
		addColor('--abmm-icon-hover:', style.icon_hover_color);
		addColor('--abmm-icon-active:', style.icon_active_color);
		if (style.icon_size) vars.push('--abmm-icon-size:' + previewNumberValue(style.icon_size, 22, 12, 64) + 'px');
		addColor('--abmm-icon-background:', style.icon_background);
		addColor('--abmm-icon-border:', style.icon_border_color);
		if (style.icon_radius !== '' && style.icon_radius != null) {
			vars.push('--abmm-icon-radius:' + previewNumberValue(style.icon_radius, 0, 0, 24) + 'px');
		}
		return esc(vars.join(';') + (vars.length ? ';' : ''));
	}

	function iconMarkup(key, url, cls, color, size, styleData) {
		cls = cls || 'abmm-icon';
		var inlineStyle = iconScopeStyle(styleData || {
			icon_color: color,
			icon_size: size,
		});
		var style = inlineStyle ? ' style="' + inlineStyle + '"' : '';
		if (color) cls += ' abmm-icon--colored';
		if (url) {
			return '<img src="' + esc(url) + '" class="' + cls + ' abmm-icon--custom" alt=""' + style + ' />';
		}
		if (key && abmmAdmin.icons[key]) {
			return '<span class="' + cls + '"' + style + '>' + abmmAdmin.icons[key] + '</span>';
		}
		return '<span class="' + cls + '"' + style + '>' + (abmmAdmin.icons.link || '') + '</span>';
	}

	function initIconModal() {
		var $grid = $('#abmm-icon-grid').empty();
		Object.keys(abmmAdmin.icons).forEach(function (key) {
			var label = abmmAdmin.iconLabels[key] || key;
			$grid.append(
				'<button type="button" class="abmm-icon-option" data-icon="' +
					esc(key) +
					'" title="' +
					esc(label) +
					'">' +
					abmmAdmin.icons[key] +
					'<span>' +
					esc(label) +
					'</span></button>'
			);
		});

		$('#abmm-icon-grid').on('click', '.abmm-icon-option', function () {
			applyIcon($(this).data('icon'), '');
			closeIconModal();
		});

		$('#abmm-icon-search').on('input', function () {
			var query = String($(this).val() || '').toLowerCase().trim();
			var shown = 0;
			$('#abmm-icon-grid .abmm-icon-option').each(function () {
				var text = (String($(this).attr('title') || '') + ' ' + String($(this).data('icon') || '')).toLowerCase();
				var match = !query || text.indexOf(query) !== -1;
				$(this).prop('hidden', !match);
				shown += match ? 1 : 0;
			});
			$('#abmm-icon-empty').prop('hidden', shown > 0);
		});

		$('.abmm-modal__close, .abmm-modal__backdrop').on('click', closeIconModal);

		$('.abmm-upload-icon').on('click', function () {
			var frame = wp.media({
				title: S.uploadIcon,
				button: { text: __('Use icon', 'ashbi-mega-menu') },
				multiple: false,
			});
			frame.on('select', function () {
				var att = frame.state().get('selection').first().toJSON();
				applyIcon('', att.url);
				closeIconModal();
			});
			frame.open();
		});

		$('.abmm-clear-icon').on('click', function () {
			applyIcon('link', '');
			closeIconModal();
		});

		$('#abmm-icon-appearance').on('input change', '.abmm-icon-advanced-field', function () {
			var target = iconTargetObject(state.iconTarget);
			if (!target) return;
			if ($(this).data('icon-field') === 'icon_size') {
				target.icon_size = parseInt($(this).val(), 10) || 0;
			} else {
				applyIconAdvancedField(target, $(this));
			}
			refreshIconTile(state.iconTarget);
			renderPreview();
		});

		$('#abmm-icon-appearance').on('click', '.abmm-reset-icon-style', function () {
			var target = iconTargetObject(state.iconTarget);
			if (!target) return;
			resetIconStyle(target);
			renderIconAppearance();
			refreshIconTile(state.iconTarget);
			renderPreview();
			setStatus(__('Icon style reset to the menu style.', 'ashbi-mega-menu'), false);
		});
	}

	function openIconPicker(target, opener) {
		state.iconTarget = target;
		$('#abmm-icon-search').val('').trigger('input');
		renderIconAppearance();
		openBuilderModal('#abmm-icon-modal', opener || document.activeElement, '#abmm-icon-search');
	}

	function closeIconModal() {
		closeBuilderModal('#abmm-icon-modal');
		state.iconTarget = null;
	}

	function applyIcon(key, url) {
		var t = state.iconTarget;
		var target = iconTargetObject(t);
		if (!target) return;
		target.icon = key;
		target.icon_url = url;
		if (!refreshIconTile(t)) {
			if (t.type === 'cat') renderCatList();
			else if (t.type === 'feature-link') renderFeaturesLinkList();
			else renderCatDetail();
		}
		renderPreview();
	}

	function saveMenu() {
		if (state.loadFailed || state.isSaving || state.draftConflict) return;
		syncSettingsFromForm();
		var payload = JSON.stringify(getMenu());
		var saveSucceeded = false;
		state.isSaving = true;
		updateSaveState('saving', S.savingState);
		var $btn = $('.abmm-save-menu').prop('disabled', true);
		$.post(abmmAdmin.ajaxUrl, {
			action: 'abmm_save_menu',
			nonce: abmmAdmin.nonce,
			menu_id: state.menuId,
			menu_data: payload,
			base_revision: state.serverRevision,
		})
			.done(function (res) {
				if (res && res.success) {
					saveSucceeded = true;
					state.savedSnapshot = payload;
					state.serverRevision = (res.data && res.data.revision) || state.serverRevision;
					state.trackingChanges = true;
					updateSaveState('saved', S.savedState);
				} else {
					updateSaveState('failed', responseMessage(res, S.saveError));
				}
			})
			.fail(function (xhr) {
				var message = requestErrorMessage(xhr, S.saveError);
				if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.conflict) {
					message = S.conflictError;
					state.draftConflict = true;
					persistLocalDraft(); checkLocalDraft();
				}
				updateSaveState('failed', message);
				persistLocalDraft();
			})
			.always(function () {
				state.isSaving = false;
				$btn.prop('disabled', state.draftConflict);
				if (saveSucceeded) {
					markDirtyState();
					if (hasUnsavedChanges()) persistLocalDraft();
					else removeLocalDraft();
					renderReadiness();
				}
			});
	}

	function esc(str) {
		if (str == null) return '';
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	$(function () {
		if ($('#abmm-builder').length) {
			initBuilder();
		} else {
			initListPage();
		}
	});
})(jQuery);
