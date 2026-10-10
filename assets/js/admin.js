/**
 * Ashbi Mega Menu — Admin Builder
 */
(function ($) {
	'use strict';

	var S = abmmAdmin.strings;
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
		copy.label = String(copy.label || 'Menu item').trim() + ' copy';
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

	function wpLinkFieldHtml(urlValue, urlClass, textSelector, disabled) {
		return (
			'<span class="abmm-wp-link">' +
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
			'</span>'
		);
	}

	function linkOptionsHtml(link) {
		link = link || {};
		return (
			'<details class="abmm-link-options"><summary>Link options</summary>' +
			'<label><input type="checkbox" class="abmm-link-target"' +
			(link.target === '_blank' ? ' checked' : '') +
			' /> Open in new tab</label>' +
			'<label>Relationship<input type="text" class="abmm-link-rel" value="' +
			esc(link.rel || '') +
			'" placeholder="nofollow sponsored" /></label>' +
			'<label>CSS classes<input type="text" class="abmm-link-class" value="' +
			esc(link.class || '') +
			'" /></label></details>'
		);
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
			setStatus('The WordPress link dialog is not available.', true);
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
		if (state.saveState === 'failed') {
			$('<button type="button" class="button-link abmm-retry-save" />')
				.text(S.retrySave)
				.on('click', saveMenu)
				.appendTo($el);
		}
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
		var conflict = draft.revision !== state.serverRevision;
		$('#abmm-draft-recovery').prop('hidden', false);
		$('.abmm-draft-recovery__message').text(
			conflict
				? 'This draft was created from an older server version. Restoring it is safe to review, but saving will be blocked until the conflict is resolved.'
				: 'Restore the unsaved changes saved in this browser, or discard them and continue with the server version.'
		);
		$('.abmm-restore-draft').off('click').on('click', function () {
			state.menu = draft.menu;
			state.serverRevision = draft.revision || state.serverRevision;
			populateFormFromMenu();
			renderNavItems();
			renderPreview();
			$('#abmm-draft-recovery').prop('hidden', true);
			$('.abmm-save-menu').trigger('focus');
		});
		$('.abmm-discard-draft').off('click').on('click', function () {
			removeLocalDraft();
			$('#abmm-draft-recovery').prop('hidden', true);
			$('#abmm-menu-title').trigger('focus');
		});
	}

	function menuReadiness(menu) {
		var issues = [];
		if (!String(menu.title || '').trim()) issues.push({ field: 'abmm-menu-title', message: 'Add a menu name.' });
		var usable = 0;
		var labels = {};
		function usableLink(link) {
			var url = String((link && link.url) || '').trim();
			return !!(link && String(link.label || '').trim() && url && url !== '#');
		}
		(menu.items || []).forEach(function (item, index) {
			var label = String(item.label || '').trim();
			if (!label) {
				issues.push({ field: 'abmm-nav-items', message: 'Add a label to top menu item ' + (index + 1) + '.' });
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
				issues.push({ field: 'abmm-nav-items', message: 'Add at least one labelled link with a real destination to ' + (label || 'this mega menu') + '.' });
			}
		});
		Object.keys(labels).forEach(function (label) {
			if (labels[label] > 1) issues.push({ field: 'abmm-nav-items', message: 'Rename duplicate top menu item “' + label + '”.' });
		});
		if (!usable) issues.push({ field: 'abmm-nav-items', message: 'Add at least one labelled item with a real destination.' });
		if (menu.cta && menu.cta.show && !usableLink(menu.cta)) {
			issues.push({ field: 'abmm-cta-url', message: 'Complete the enabled call-to-action label and destination.' });
		}
		return { ready: !issues.length, issues: issues };
	}

	function renderReadiness() {
		if (!state.menu) return;
		var result = menuReadiness(getMenu());
		var $list = $('.abmm-readiness-issues').empty();
		$('.abmm-readiness-summary')
			.text(result.ready ? 'Ready to place. Save changes before publishing.' : 'Draft — ' + result.issues.length + ' item(s) need attention.')
			.toggleClass('is-ready', result.ready);
		result.issues.forEach(function (issue) {
			$('<li><button type="button" class="button-link"></button></li>')
				.find('button').text(issue.message).attr('data-field', issue.field).end()
				.appendTo($list);
		});
		$('.abmm-placement-panel').prop('hidden', !result.ready);
		setBuilderSection(state.builderSection);
	}

	function setBuilderSection(section) {
		section = ['content', 'design', 'settings', 'help'].indexOf(section) !== -1 ? section : 'content';
		state.builderSection = section;
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
	}

	function initBuilderSections() {
		$('.abmm-builder-sections').on('click', '[data-abmm-builder-section]', function () {
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
			{ toggle: '#abmm-brand-show', panel: '#abmm-brand-show', label: 'Brand Identity' },
			{ toggle: '#abmm-cta-show', panel: '#abmm-cta-show', label: 'Call-to-Action Button' },
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
		$('#abmm-preset').val('custom');
		$('.abmm-preset-card').removeClass('is-active');
		$('.abmm-preset-card[data-preset="custom"]').addClass('is-active');
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
		$('<button type="button" class="button-link abmm-status-undo">Undo</button>').on('click', function () {
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
		var $fallback = $('<input type="text" class="abmm-copy-fallback" readonly />').val(shortcode);
		$button.closest('.abmm-menu-card__shortcode').find('.abmm-copy-fallback').remove();
		$button.after($fallback);
		$fallback.trigger('focus').select();
		setListStatus(S.copyError, true, false);
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
			var labels = { create: 'Create', update: 'Update', create_copy: 'Create independent copy' };
			$('<li />').text((labels[change.action] || change.action) + ': ' + change.title + ' (' + change.item_count + ' items)').appendTo($list);
		});
		if (preview.remove_count) {
			$('<p class="description is-warning" />').text(preview.remove_count + ' existing menu(s) will be removed by Replace mode.').appendTo($preview);
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
			status('Loading recovery snapshots…', false);
			$.post(abmmAdmin.ajaxUrl, { action: 'abmm_list_backups', nonce: abmmAdmin.nonce })
				.done(function (res) {
					if (!res || !res.success || !res.data) { status(responseMessage(res, 'Snapshots could not be loaded.'), true); return; }
					revision = String(res.data.revision || '');
					$list.empty();
					(res.data.backups || []).forEach(function (backup) {
						var $row = $('<div class="abmm-backup-row" />').attr('data-backup-id', backup.id);
						$('<strong />').text(backup.created_at + ' — ' + backup.menu_count + ' menu(s)').appendTo($row);
						$('<button type="button" class="button abmm-export-backup">Export snapshot</button>').attr('data-backup-id', backup.id).appendTo($row);
						$('<button type="button" class="button abmm-restore-backup">Restore snapshot</button>').attr('data-backup-id', backup.id).appendTo($row);
						$row.appendTo($list);
					});
					status($list.children().length ? 'Export a snapshot before restoring. Restore replaces every current menu.' : 'No recovery snapshots are available yet.', false);
				}).fail(function (xhr) { status(requestErrorMessage(xhr, 'Snapshots could not be loaded.'), true); })
				.always(function () { $button.prop('disabled', false); });
		});
		$list.on('click', '.abmm-export-backup', function () {
			var $button = $(this).prop('disabled', true);
			var id = String($button.attr('data-backup-id'));
			$.post(abmmAdmin.ajaxUrl, { action: 'abmm_export_backup', nonce: abmmAdmin.nonce, backup_id: id })
				.done(function (res) {
					if (!res || !res.success || !res.data || !res.data.menus) { status(responseMessage(res, 'Snapshot export failed.'), true); return; }
					downloadMenuJson('snapshot-' + id, JSON.stringify(res.data, null, 2));
					status('Snapshot exported. Keep the downloaded JSON file for recovery.', false);
				}).fail(function (xhr) { status(requestErrorMessage(xhr, 'Snapshot export failed.'), true); })
				.always(function () { $button.prop('disabled', false); });
		});
		$list.on('click', '.abmm-restore-backup', function () {
			$list.find('.abmm-backup-confirm').remove();
			var $row = $(this).closest('.abmm-backup-row');
			var $confirm = $('<div class="abmm-backup-confirm" role="alert" />').appendTo($row);
			$('<p />').text('Replace ALL current menus with this snapshot? Current menus will be backed up first. Published placements may change. Export your current menus before continuing.').appendTo($confirm);
			$('<button type="button" class="button button-primary abmm-confirm-backup">Replace menus with snapshot</button>').appendTo($confirm).trigger('focus');
			$('<button type="button" class="button abmm-cancel-backup">Cancel</button>').appendTo($confirm);
		});
		$list.on('click', '.abmm-cancel-backup', function () {
			var $row = $(this).closest('.abmm-backup-row');
			$row.find('.abmm-backup-confirm').remove();
			$row.find('.abmm-restore-backup').trigger('focus');
		});
		$list.on('click', '.abmm-confirm-backup', function () {
			var $row = $(this).closest('.abmm-backup-row');
			var $buttons = $list.find('button').add('.abmm-load-backups').prop('disabled', true);
			status('Restoring snapshot…', false);
			$.post(abmmAdmin.ajaxUrl, { action: 'abmm_restore_backup', nonce: abmmAdmin.nonce, backup_id: $row.attr('data-backup-id'), revision: revision })
				.done(function (res) {
					if (!res || !res.success) { status(responseMessage(res, 'Snapshot restore failed. Reload snapshots before retrying.'), true); return; }
					status(responseMessage(res, 'Snapshot restored. Reloading menus…'), false);
					setTimeout(function () { window.location.reload(); }, 700);
				}).fail(function (xhr) { status(requestErrorMessage(xhr, 'Restore failed. Reload snapshots before retrying.'), true); })
				.always(function () { $buttons.prop('disabled', false); $row.find('.abmm-backup-confirm').remove(); });
		});
	}

	function initListPage() {
		initBackupRecovery();
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
			$button.prop('disabled', true).text('Checking placements…');
			$.post(abmmAdmin.ajaxUrl, {
				action: 'abmm_menu_references',
				nonce: abmmAdmin.nonce,
				menu_id: id,
			}).done(function (res) {
				if (res && res.success) {
					var refs = res.data || {};
					var $confirm = $(
						'<div class="abmm-archive-confirm" role="alert">' +
						'<strong>Archive “' + esc(title) + '”?</strong>' +
						'<p>Known placements: ' + (refs.blocks || 0) + ' block(s), ' + (refs.widgets || 0) + ' widget(s).</p>' +
						'<p>' + esc(refs.warning || '') + '</p>' +
						'<button type="button" class="button button-primary abmm-confirm-archive">Archive and keep recoverable</button> ' +
						'<button type="button" class="button abmm-cancel-archive">Cancel</button>' +
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
			$card.find('.abmm-delete-menu').trigger('focus');
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
				$button.data('confirmed', true).text('Click again to permanently delete');
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
					setListStatus(responseMessage(res, 'Archived menu permanently deleted.'), false, false);
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

			var fd = new FormData();
			fd.append('action', 'abmm_import_menu');
			fd.append('nonce', abmmAdmin.nonce);
			fd.append('mode', mode);
			fd.append('import_file', $file[0].files[0]);
			if (!importConfirmed) fd.append('preview', '1');

			var $btn = $form.find('button[type="submit"]').prop('disabled', true);
			setImportStatus(importConfirmed ? 'Importing…' : 'Preparing preview…', false);

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
			}).fail(function () {
				setImportStatus(S.importError, true);
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
			$button.prop('disabled', false).text('Retry authenticated load');
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
			$('.abmm-builder__main, .abmm-builder-topbar, .abmm-builder-load-error, #abmm-draft-recovery')
				.attr('aria-hidden', 'true')
				.prop('inert', true);
		}
		if ($('#abmm-mega-editor').attr('role') === 'dialog') {
			$('.abmm-builder-topbar, .abmm-builder__sidebar, .abmm-builder__main > *:not(#abmm-mega-editor), .abmm-builder-load-error, #abmm-draft-recovery')
				.attr('aria-hidden', 'true')
				.prop('inert', true);
		}
	}

	function setBuilderBackgroundInert(active) {
		var $background = $('.abmm-builder, .abmm-builder-topbar, .abmm-builder-load-error, #abmm-draft-recovery');
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
		return window.matchMedia && window.matchMedia('(max-width: 1100px)').matches;
	}

	function megaEditorIsNarrow() {
		return window.matchMedia && window.matchMedia('(max-width: 760px)').matches;
	}

	function updateBuilderSheetBodyLock() {
		var sheetOpen = $('#abmm-builder-sidebar').hasClass('is-open') || $('#abmm-mega-editor').attr('role') === 'dialog';
		$('html, body').toggleClass('abmm-builder-sheet-open', sheetOpen);
	}

	function setMegaEditorBackgroundInert(active) {
		var $background = $('.abmm-builder-topbar, .abmm-builder__sidebar, .abmm-builder__main > *:not(#abmm-mega-editor), .abmm-builder-load-error, #abmm-draft-recovery');
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
		var $background = $('.abmm-builder__main, .abmm-builder-topbar, .abmm-builder-load-error, #abmm-draft-recovery');
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
				$editor.attr({ role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'abmm-mega-editor-title' });
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

		state.menuId = $builder.data('menu-id');
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
		initBuilderSections();
		initBuilderPanel();

		$('.abmm-preview-viewports').on('click', '[data-abmm-preview-viewport]', function () {
			state.previewViewport = String($(this).data('abmm-preview-viewport') || 'desktop');
			state.previewDrill = 'root';
			state.previewItemIndex = -1;
			state.previewCategoryId = '';
			state.previewMobileOpen = false;
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
			var label = 'Item';
			if ($row.hasClass('abmm-nav-item-row')) {
				collection = getMenu().items; rerender = renderNavItems; label = 'Navigation item';
			} else if ($row.hasClass('abmm-cat-row')) {
				var current = getMenu().items[state.editingItemIndex];
				collection = current && current.categories; rerender = renderCatList; label = 'Category';
			} else if ($row.hasClass('abmm-group-block')) {
				var item = getMenu().items[state.editingItemIndex];
				var category = item && item.categories[state.editingCatIndex];
				collection = category && category.groups; rerender = renderCatDetail; label = 'Column';
			} else if ($row.hasClass('abmm-link-row')) {
				var $group = $row.closest('.abmm-group-block');
				if ($group.length) {
					var itemForGroup = getMenu().items[state.editingItemIndex];
					var catForGroup = itemForGroup && itemForGroup.categories[state.editingCatIndex];
					var group = catForGroup && (catForGroup.groups || []).find(function (candidate) { return candidate.id === $group.data('id'); });
					collection = group && group.links; rerender = renderCatDetail; label = 'Link';
				} else {
					var featureItem = getMenu().items[state.editingItemIndex];
					collection = featureItem && featureItem.links; rerender = renderFeaturesLinkList; label = 'Feature link';
				}
			}
			if (!moveCollectionItem(collection, index, direction)) return;
			rerender();
			renderPreview();
			announceReorder(label, index + direction + 1, collection.length);
			setTimeout(function () {
				var selector = direction < 0 ? '.abmm-move-up' : '.abmm-move-down';
				var listSelector = $row.hasClass('abmm-nav-item-row') ? '#abmm-nav-items' :
					($row.hasClass('abmm-cat-row') ? '#abmm-cat-list' :
					($row.hasClass('abmm-group-block') ? '#abmm-group-list' :
					($row.closest('.abmm-group-block').length ? '.abmm-group-block[data-id="' + $row.closest('.abmm-group-block').data('id') + '"] .abmm-group-link-list' : '#abmm-features-link-list')));
				$(listSelector).children().eq(index + direction).find(selector).first().trigger('focus');
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
			getMenu().items.push({
				id: uid(),
				label: 'New Item',
				url: '#',
				type: 'link',
			});
			renderNavItems();
			renderPreview();
		});

		$('.abmm-save-menu').on('click', saveMenu);

		$('#abmm-nav-items').on('click', '.abmm-remove-nav', function () {
			var items = getMenu().items, id = $(this).closest('.abmm-nav-item-row').data('id');
			var index = items.findIndex(function (it) { return it.id === id; }); if (index < 0) return;
			var removed = items.splice(index, 1)[0]; if (state.editingItemIndex >= 0) closeMegaEditor();
			renderNavItems(); renderPreview();
			setUndoStatus('Navigation item removed.', function () { getMenu().items.splice(index, 0, removed); renderNavItems(); renderPreview(); setStatus('Navigation item restored.', false); });
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
			var newType = $row.find('.abmm-nav-type').val();
			if (newType !== item.type) {
				item.type = newType;
				if (newType === 'mega') {
					if (!item.mega_style) item.mega_style = 'platforms';
					if (!item.columns) item.columns = item.mega_style === 'features' ? 2 : 3;
					if (!item.categories) item.categories = [];
					if (!item.links) item.links = [];
				}
				renderNavItems();
			}
			renderPreview();
		});

		$('#abmm-nav-items').on('click', '.abmm-edit-mega', function () {
			var id = $(this).closest('.abmm-nav-item-row').data('id');
			var idx = getMenu().items.findIndex(function (it) {
				return it.id === id;
			});
			openMegaEditor(idx);
		});

		$('#abmm-nav-items').on('click', '.abmm-duplicate-nav', function () {
			var items = getMenu().items;
			var id = $(this).closest('.abmm-nav-item-row').data('id');
			var index = items.findIndex(function (item) { return item.id === id; });
			if (index < 0) return;
			var copy = duplicateMenuItem(items[index]);
			items.splice(index + 1, 0, copy);
			renderNavItems();
			renderPreview();
			setStatus('Duplicated ' + (items[index].label || 'menu item') + '. Review the copy before saving.', false);
			setTimeout(function () {
				$('#abmm-nav-items .abmm-nav-item-row').eq(index + 1).find('.abmm-nav-label').trigger('focus').select();
			}, 0);
		});

		$('.abmm-close-mega-editor').on('click', closeMegaEditor);

		$('.abmm-open-selected-mega').on('click', function () {
			if (state.previewItemIndex < 0) return;
			var item = getMenu().items[state.previewItemIndex];
			if (!item || item.type !== 'mega') return;
			openMegaEditor(state.previewItemIndex);
		});

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
				label: 'New Feature',
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
			setUndoStatus('Feature link removed.', function () { item.links.splice(index, 0, removed); renderFeaturesLinkList(); renderPreview(); setStatus('Feature link restored.', false); });
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
				title: 'NEW',
				description: '',
				icon: 'grid',
				icon_url: '',
				icon_color: '',
				icon_size: 0,
				panel_title: 'Overview',
				panel_url: '',
				groups: [
					{
						id: uid(),
						title: 'Column 1',
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
			if ($(e.target).closest('.abmm-remove-cat, .abmm-pick-icon').length) return;
			selectCategory($(this).index());
		});

		$('#abmm-cat-list').on('click', '.abmm-remove-cat', function (e) {
			e.stopPropagation(); var item = getMenu().items[state.editingItemIndex], idx = $(this).closest('.abmm-cat-row').index();
			if (!item || !item.categories || !item.categories[idx]) return;
			var removed = item.categories.splice(idx, 1)[0]; state.editingCatIndex = -1; renderCatList(); showCatPlaceholder(); renderPreview();
			setUndoStatus('Category removed.', function () { item.categories.splice(idx, 0, removed); renderCatList(); renderCatDetail(); renderPreview(); setStatus('Category restored.', false); });
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
			renderPreview();
		});

		$('#abmm-features-link-list').on('input change', '.abmm-link-icon-color, .abmm-link-icon-size', function () {
			var item = getMenu().items[state.editingItemIndex];
			var id = $(this).closest('.abmm-link-row').data('id');
			var link = item && (item.links || []).find(function (entry) { return entry.id === id; });
			if (!link) return;
			if ($(this).hasClass('abmm-link-icon-size')) {
				link.icon_size = parseInt($(this).val(), 10) || 0;
			} else {
				link.icon_color = $(this).val() || '';
			}
			renderPreview();
		});

		$('#abmm-features-link-list').on('click', '.abmm-reset-icon-style', function () {
			var item = getMenu().items[state.editingItemIndex];
			var id = $(this).closest('.abmm-link-row').data('id');
			var link = item && (item.links || []).find(function (entry) { return entry.id === id; });
			resetIconStyle(link);
			renderFeaturesLinkList();
			renderPreview();
		});

		$('#abmm-features-link-list').on('input change', '.abmm-icon-advanced-field', function () {
			var item = getMenu().items[state.editingItemIndex];
			var id = $(this).closest('.abmm-link-row').data('id');
			var link = item && (item.links || []).find(function (entry) { return entry.id === id; });
			applyIconAdvancedField(link, $(this));
			renderPreview();
		});

		$('#abmm-cat-list').on('input change', '.abmm-cat-icon-color, .abmm-cat-icon-size', function () {
			var item = getMenu().items[state.editingItemIndex];
			var idx = $(this).closest('.abmm-cat-row').index();
			var cat = item && item.categories[idx];
			if (!cat) return;
			if ($(this).hasClass('abmm-cat-icon-size')) {
				cat.icon_size = parseInt($(this).val(), 10) || 0;
			} else {
				cat.icon_color = $(this).val() || '';
			}
			renderPreview();
		});

		$('#abmm-cat-list').on('click', '.abmm-reset-icon-style', function (event) {
			event.stopPropagation();
			var item = getMenu().items[state.editingItemIndex];
			var idx = $(this).closest('.abmm-cat-row').index();
			resetIconStyle(item && item.categories[idx]);
			renderCatList();
			renderPreview();
		});

		$('#abmm-cat-list').on('input change', '.abmm-icon-advanced-field', function () {
			var item = getMenu().items[state.editingItemIndex];
			var idx = $(this).closest('.abmm-cat-row').index();
			applyIconAdvancedField(item && item.categories[idx], $(this));
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

	function iconAdvancedFieldsHtml(style) {
		style = style || {};
		var settings = (getMenu() && getMenu().settings) || {};
		return (
			'<details class="abmm-icon-style-editor"><summary>State & container overrides</summary>' +
			'<div class="abmm-icon-style-editor__grid">' +
			'<label>Hover<input type="color" class="abmm-icon-advanced-field" data-icon-field="icon_hover_color" value="' +
			esc(style.icon_hover_color || settings.icon_hover_color || '#1a73e8') +
			'" /></label>' +
			'<label>Active<input type="color" class="abmm-icon-advanced-field" data-icon-field="icon_active_color" value="' +
			esc(style.icon_active_color || settings.icon_active_color || '#1a73e8') +
			'" /></label>' +
			'<label>Background<input type="color" class="abmm-icon-advanced-field" data-icon-field="icon_background" value="' +
			esc(style.icon_background || settings.icon_background || '#ffffff') +
			'" /></label>' +
			'<label>Border<input type="color" class="abmm-icon-advanced-field" data-icon-field="icon_border_color" value="' +
			esc(style.icon_border_color || settings.icon_border_color || '#ffffff') +
			'" /></label>' +
			'<label>Radius<input type="number" min="0" max="24" class="abmm-icon-advanced-field" data-icon-field="icon_radius" value="' +
			esc(style.icon_radius === '' || style.icon_radius == null ? '' : style.icon_radius) +
			'" placeholder="Inherit" /></label>' +
			'</div></details>'
		);
	}

	function showCatPlaceholder() {
		$('#abmm-cat-detail').html(
			'<p class="abmm-placeholder">' +
				'Select a category on the left to edit its links.' +
				'</p>'
		);
	}

	function reorderControlsHtml(label, index, length) {
		return '<span class="abmm-reorder-controls" role="group" aria-label="' + esc(label) + ' position controls">' +
			'<button type="button" class="button-link abmm-move-item abmm-move-up" aria-label="Move ' + esc(label) + ' up"' +
			(index === 0 ? ' disabled' : '') + '>Move up</button>' +
			'<button type="button" class="button-link abmm-move-item abmm-move-down" aria-label="Move ' + esc(label) + ' down"' +
			(index === length - 1 ? ' disabled' : '') + '>Move down</button></span>';
	}

	function moveCollectionItem(collection, from, direction) {
		var to = from + direction;
		if (!collection || from < 0 || to < 0 || to >= collection.length) return false;
		var moved = collection.splice(from, 1)[0];
		collection.splice(to, 0, moved);
		return true;
	}

	function announceReorder(label, position, length) {
		setStatus(label + ' moved to position ' + position + ' of ' + length + '.', false);
	}

	function renderNavItems() {
		var $list = $('#abmm-nav-items').empty();
		getMenu().items.forEach(function (item, itemIndex) {
			var isMega = item.type === 'mega';
			var styleLabel =
				item.mega_style === 'features' ? 'Simple columns' : 'With categories';
			var $row = $(
					'<li class="abmm-nav-item-row ' + (isMega ? 'is-mega' : 'is-link') + '" data-id="' +
					esc(item.id) +
					'">' +
					'<span class="abmm-drag-handle" title="' +
					esc(S.dragHint) +
					'">⋮⋮</span>' +
					'<div class="abmm-nav-item-row__fields">' +
					'<input type="text" class="abmm-nav-label" value="' +
					esc(item.label) +
					'" placeholder="Label" aria-label="Navigation item ' + esc(itemIndex + 1) + ' label" />' +
						(!isMega ? wpLinkFieldHtml(item.url || '', 'abmm-nav-url', '.abmm-nav-label', false) : '') +
						(!isMega ? linkOptionsHtml(item) : '') +
						'<div class="abmm-nav-item-row__meta">' +
						'<select class="abmm-nav-type" aria-label="Navigation item ' + esc(itemIndex + 1) + ' type">' +
					'<option value="link"' +
					(!isMega ? ' selected' : '') +
					'>' +
					esc(S.simpleLink) +
					'</option>' +
					'<option value="mega"' +
					(isMega ? ' selected' : '') +
					'>' +
					esc(S.megaMenu) +
					'</option>' +
					'</select>' +
						(isMega ? '<span class="abmm-mega-style-badge">' + esc(styleLabel) + '</span>' : '') +
						(isMega ? '<button type="button" class="button button-small abmm-edit-mega">Edit content</button>' : '') +
						'<button type="button" class="button button-small abmm-duplicate-nav" aria-label="Duplicate navigation item ' + esc(item.label || itemIndex + 1) + '">Duplicate</button>' +
						'</div>' +
						'</div>' +
					reorderControlsHtml('navigation item ' + (item.label || itemIndex + 1), itemIndex, getMenu().items.length) +
					'<button type="button" class="button-link-delete abmm-remove-nav" aria-label="Remove navigation item ' + esc(item.label || itemIndex + 1) + '" title="Remove navigation item">&times;</button>' +
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

	function openMegaEditor(index) {
		state.editingItemIndex = index;
		state.megaEditorOpener = document.activeElement;
		var item = getMenu().items[index];
		if (!item.mega_style) item.mega_style = 'platforms';
		if (!item.columns) item.columns = item.mega_style === 'features' ? 2 : 3;
		if (!item.categories) item.categories = [];
		if (!item.links) item.links = [];
		$('#abmm-mega-editor-title').text('Edit Mega Menu: ' + (item.label || ''));
		$('#abmm-mega-editor').prop('hidden', false);
		syncMegaEditorUI();
		if (megaEditorIsNarrow()) {
			syncMegaEditorSheetState();
			setTimeout(function () {
				$('#abmm-mega-editor .abmm-close-mega-editor').trigger('focus');
			}, 0);
		} else {
			$('html, body').animate({ scrollTop: $('#abmm-mega-editor').offset().top - 40 }, 200);
		}
	}

	function syncMegaEditorUI() {
		var item = getMenu().items[state.editingItemIndex];
		if (!item) return;
		var style = item.mega_style || 'platforms';

		$('.abmm-mega-style-card').removeClass('is-active');
		$('.abmm-mega-style-card[data-style="' + style + '"]').addClass('is-active');
		$('#abmm-mega-item-columns').val(String(item.columns || (style === 'features' ? 2 : 3)));
		var categoryCount = (item.categories || []).length;
		var canHideCategoryBar = style === 'platforms' && categoryCount === 1;
		$('#abmm-category-bar-field').prop('hidden', style !== 'platforms');
		$('#abmm-hide-category-bar')
			.prop('disabled', !canHideCategoryBar)
			.prop('checked', canHideCategoryBar && !!item.hide_category_bar);

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
		if (state.megaEditorOpener && document.documentElement.contains(state.megaEditorOpener)) {
			setTimeout(function () { $(state.megaEditorOpener).trigger('focus'); }, 0);
		}
		state.megaEditorOpener = null;
	}

	function renderCatList() {
		var item = getMenu().items[state.editingItemIndex];
		var $list = $('#abmm-cat-list').empty();
		if (!item) return;
		var canHideCategoryBar = (item.categories || []).length === 1;
		$('#abmm-hide-category-bar')
			.prop('disabled', !canHideCategoryBar)
			.prop('checked', canHideCategoryBar && !!item.hide_category_bar);

		(item.categories || []).forEach(function (cat, i) {
			var iconHtml = iconMarkup(cat.icon, cat.icon_url, '', cat.icon_color, cat.icon_size, cat);
			var $row = $(
				'<li class="abmm-cat-row' +
					(i === state.editingCatIndex ? ' is-selected' : '') +
					'" data-id="' +
					esc(cat.id) +
					'">' +
					'<span class="abmm-drag-handle">⋮⋮</span>' +
				'<button type="button" class="abmm-select-category" aria-pressed="' + (i === state.editingCatIndex ? 'true' : 'false') + '" aria-label="Edit category ' + esc(cat.title || i + 1) + '">Edit</button>' +
				'<button type="button" class="abmm-pick-icon" title="' +
					esc(S.selectIcon) +
					'">' +
				iconHtml +
				'</button>' +
				'<input type="color" class="abmm-icon-color abmm-cat-icon-color" value="' +
				esc(cat.icon_color || '#2271b1') +
				'" aria-label="Icon color" />' +
				'<input type="number" class="abmm-icon-size abmm-cat-icon-size" min="12" max="64" value="' +
				esc(cat.icon_size || '') +
				'" placeholder="Size" aria-label="Icon size override" />' +
				'<button type="button" class="button-link abmm-reset-icon-style" title="Reset icon style to inherited values">Inherit</button>' +
				iconAdvancedFieldsHtml(cat) +
					'<div class="abmm-cat-row__fields">' +
					'<input type="text" class="abmm-cat-title" aria-label="Category ' + esc(i + 1) + ' title" value="' +
					esc(cat.title) +
					'" placeholder="' +
					esc(S.categoryTitle) +
					'" />' +
					'<input type="text" class="abmm-cat-desc" aria-label="Category ' + esc(i + 1) + ' description" value="' +
					esc(cat.description) +
					'" placeholder="' +
					esc(S.categoryDesc) +
					'" />' +
					'</div>' +
					reorderControlsHtml('category ' + (cat.title || i + 1), i, item.categories.length) +
					'<button type="button" class="button-link-delete abmm-remove-cat" aria-label="Remove category ' + esc(cat.title || i + 1) + '">&times;</button>' +
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
			title: 'Column 1',
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

		var html =
			'<div class="abmm-cat-detail-inner">' +
			'<label class="abmm-field"><span>' +
			esc(S.panelTitle) +
			'</span>' +
			'<input type="text" class="abmm-panel-title" value="' +
			esc(cat.panel_title || '') +
			'" /></label>' +
			'<label class="abmm-field"><span>' +
			esc(S.panelUrl) +
			'</span>' +
			wpLinkFieldHtml(cat.panel_url, 'abmm-panel-url', '.abmm-panel-title', false) +
			'</label>' +
			'<div class="abmm-panel-link-options">' +
			linkOptionsHtml(cat.panel_link || {}) +
			'</div>' +
			'<div class="abmm-col-editor-head">' +
			'<div>' +
			'<h3>Columns</h3>' +
			'<p class="description">Split this category into labeled columns. Drag links between columns to reorganize them.</p>' +
			'</div>' +
			'<button type="button" class="button abmm-add-group">+ Add column</button>' +
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
				title: 'Column ' + (cat.groups.length + 1),
				url: '',
				links: [],
			});
			renderCatDetail();
			renderPreview();
		});

		$detail.on('click.abmmCatDetail', '.abmm-remove-group', function () {
			if (cat.groups.length <= 1) {
				setStatus('Keep at least one column.', true);
				return;
			}
			var gid = $(this).closest('.abmm-group-block').data('id');
			var index = cat.groups.findIndex(function (g) { return g.id === gid; }); if (index < 0) return;
			var removed = cat.groups.splice(index, 1)[0]; renderCatDetail(); renderPreview();
			setUndoStatus('Column removed.', function () { cat.groups.splice(index, 0, removed); renderCatDetail(); renderPreview(); setStatus('Column restored.', false); });
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
				label: 'New Link',
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
			setUndoStatus('Link removed.', function () { group.links.splice(index, 0, removed); renderCatDetail(); renderPreview(); setStatus('Link restored.', false); });
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

		$detail.on('input.abmmCatDetail change.abmmCatDetail', '.abmm-link-icon-color, .abmm-link-icon-size', function () {
			var $row = $(this).closest('.abmm-link-row');
			var $block = $(this).closest('.abmm-group-block');
			var group = cat.groups.find(function (g) { return g.id === $block.data('id'); });
			var link = group && (group.links || []).find(function (l) { return l.id === $row.data('id'); });
			if (!link) return;
			if ($(this).hasClass('abmm-link-icon-size')) {
				link.icon_size = parseInt($(this).val(), 10) || 0;
			} else {
				link.icon_color = $(this).val() || '';
			}
			renderPreview();
		});

		$detail.on('click.abmmCatDetail', '.abmm-reset-icon-style', function () {
			var $row = $(this).closest('.abmm-link-row');
			var $block = $(this).closest('.abmm-group-block');
			var group = cat.groups.find(function (g) { return g.id === $block.data('id'); });
			var link = group && (group.links || []).find(function (l) { return l.id === $row.data('id'); });
			resetIconStyle(link);
			renderCatDetail();
			renderPreview();
		});

		$detail.on('input.abmmCatDetail change.abmmCatDetail', '.abmm-icon-advanced-field', function () {
			var $row = $(this).closest('.abmm-link-row');
			var $block = $(this).closest('.abmm-group-block');
			var group = cat.groups.find(function (g) { return g.id === $block.data('id'); });
			var link = group && (group.links || []).find(function (l) { return l.id === $row.data('id'); });
			applyIconAdvancedField(link, $(this));
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

	function groupBlockHtml(group, groupIndex, groupLength) {
		var linksHtml = '';
		(group.links || []).forEach(function (link, index) {
			linksHtml += linkRowHtml(link, index, group.links.length);
		});

		return (
			'<div class="abmm-group-block" data-id="' +
			esc(group.id) +
			'">' +
			'<div class="abmm-group-block__head">' +
			'<input type="text" class="abmm-group-title" aria-label="Column heading" value="' +
			esc(group.title || '') +
			'" placeholder="Column heading (e.g. Finance)" />' +
			reorderControlsHtml('column ' + (group.title || groupIndex + 1), groupIndex, groupLength) +
			'<button type="button" class="button-link-delete abmm-remove-group" aria-label="Remove column ' + esc(group.title || '') + '" title="Remove column">&times;</button>' +
			'</div>' +
			'<label class="abmm-field abmm-field--compact"><span>' +
			esc(S.groupUrl) +
			'</span>' +
			wpLinkFieldHtml(group.url, 'abmm-group-url', '.abmm-group-title', false) +
			'</label>' +
			'<div class="abmm-group-heading-options">' +
			linkOptionsHtml(group) +
			'</div>' +
			'<ul class="abmm-sortable abmm-link-list abmm-group-link-list">' +
			linksHtml +
			'</ul>' +
			'<button type="button" class="button-link abmm-add-group-link">+ ' +
			esc(S.addLink) +
			'</button>' +
			'</div>'
		);
	}

	function linkRowHtml(link, linkIndex, linkLength) {
		return (
			'<li class="abmm-link-row" data-id="' +
			esc(link.id) +
			'">' +
			'<span class="abmm-drag-handle">⋮⋮</span>' +
			'<button type="button" class="abmm-pick-link-icon" title="' +
			esc(S.selectIcon) +
			'">' +
			iconMarkup(link.icon, link.icon_url, '', link.icon_color, link.icon_size, link) +
			'</button>' +
			'<input type="color" class="abmm-icon-color abmm-link-icon-color" value="' +
			esc(link.icon_color || '#8a8a96') +
			'" aria-label="Icon color" />' +
			'<input type="number" class="abmm-icon-size abmm-link-icon-size" min="12" max="64" value="' +
			esc(link.icon_size || '') +
			'" placeholder="Size" aria-label="Icon size override" />' +
			'<button type="button" class="button-link abmm-reset-icon-style" title="Reset icon style to inherited values">Inherit</button>' +
			iconAdvancedFieldsHtml(link) +
			'<input type="text" class="abmm-link-label" aria-label="Link label" value="' +
			esc(link.label) +
			'" placeholder="' +
			esc(S.linkLabel) +
			'" />' +
			'<textarea class="abmm-link-description" aria-label="Link description" rows="2" placeholder="Short excerpt (optional)">' +
			esc(link.description || '') +
			'</textarea>' +
			wpLinkFieldHtml(link.url || '', 'abmm-link-url', '.abmm-link-label', false) +
			linkOptionsHtml(link) +
			reorderControlsHtml('link ' + (link.label || linkIndex + 1), linkIndex, linkLength) +
			'<button type="button" class="button-link-delete abmm-remove-link" aria-label="Remove link ' + esc(link.label || '') + '">&times;</button>' +
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

	function renderPreview() {
		syncSettingsFromForm();
		var m = getMenu();
		var s = m.settings || {};
		var style = settingsStyleAttr(s);
		var className = settingsClassList(s);
		var previewDrawerId = 'abmm-preview-drawer';

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
				'<span class="screen-reader-text">Current product: </span>' +
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
				? '<div class="abmm-preview-mobile-bar"><button type="button" data-abmm-preview-back aria-label="Back to menu">&larr; <span>Back</span></button><strong>' +
				  esc(state.previewDrill === 'links' ? 'Choose a link' : 'Choose a section') +
				  '</strong></div>'
				: '';
		var mobileToggleLabel = mobileOpen ? 'Close menu' : 'Open menu';

		var html =
			'<div class="' +
			className +
			(mobilePreview ? ' abmm-force-mobile' : '') +
			(mobileOpen ? ' is-mobile-open' : '') +
			(mobilePreview && state.previewDrill !== 'root' ? ' abmm-is-drilling' : '') +
			'" style="' +
			esc(style) +
			'">' +
			'<nav class="abmm-nav" aria-label="' + esc(m.title || 'Menu') + '">' +
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
			'<div class="abmm-nav__drawer-head"><span class="abmm-nav__drawer-title">Menu</span><button type="button" class="abmm-nav__drawer-close" data-abmm-preview-drawer-close aria-label="Close menu"><span aria-hidden="true">&times;</span></button></div>' +
			drillMarkup +
			'<div class="abmm-nav__inner">' +
			previewBrandMarkup('drawer') +
			previewContextMarkup('drawer') +
			'<ul class="abmm-nav__list">' +
			itemsHtml +
			'</ul>' +
			previewCtaMarkup('drawer') +
			'</div></div>' +
			'<button type="button" class="abmm-nav__backdrop" data-abmm-preview-backdrop' + (mobileOpen ? '' : ' hidden') + ' aria-label="Close menu"></button>' +
			'</nav></div>';

		var previewState = state.previewViewport + '-' + state.previewDrill;
		$('#abmm-preview').attr('data-abmm-preview-state', previewState).html(html);
		var $preview = $('#abmm-preview');
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
		var selectedItem = m.items && m.items[state.previewItemIndex];
		var $editSelected = $('.abmm-open-selected-mega');
		if (selectedItem && selectedItem.type === 'mega') {
			$editSelected
				.text('Edit ' + (selectedItem.label || 'selected') + ' content')
				.attr('aria-label', 'Edit ' + (selectedItem.label || 'selected') + ' mega menu content')
				.prop('hidden', false);
		} else {
			$editSelected.prop('hidden', true);
		}
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
				setTimeout(function () {
					var selector = state.previewMobileOpen ? '.abmm-nav__drawer-close' : '.abmm-nav__toggle';
					$('#abmm-preview').find(selector).trigger('focus');
				}, 0);
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
		renderReadiness();
		markDirtyState();
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
				'" style="position:relative;display:block;" data-abmm-mega-panel data-abmm-style="features">' +
				'<div class="abmm-mega__inner abmm-mega__inner--features">' +
				'<ul class="abmm-mega__grid abmm-mega__grid--features" style="--abmm-grid-cols:' +
				cols +
				'">' +
				featLinks +
				'</ul></div></div>'
			);
		}

		var cats = item.categories || [];
		var sections = '';

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
				'</div></div></section>';
		});

		var hideCategoryBar = !!item.hide_category_bar && cats.length === 1;
		return (
			'<div class="abmm-mega abmm-mega--platforms' +
			(hideCategoryBar ? ' abmm-mega--columns-only' : '') +
			'" id="' +
			previewPanelId +
			'" style="position:relative;display:block;" data-abmm-mega-panel data-abmm-style="platforms">' +
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
			$('#abmm-icon-grid .abmm-icon-option').each(function () {
				var label = String($(this).attr('title') || '').toLowerCase();
				$(this).prop('hidden', query && label.indexOf(query) === -1);
			});
		});

		$('.abmm-modal__close, .abmm-modal__backdrop').on('click', closeIconModal);

		$('.abmm-upload-icon').on('click', function () {
			var frame = wp.media({
				title: S.uploadIcon,
				button: { text: 'Use icon' },
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
	}

	function openIconPicker(target, opener) {
		state.iconTarget = target;
		$('#abmm-icon-search').val('').trigger('input');
		openBuilderModal('#abmm-icon-modal', opener || document.activeElement, '#abmm-icon-search');
	}

	function closeIconModal() {
		closeBuilderModal('#abmm-icon-modal');
		state.iconTarget = null;
	}

	function applyIcon(key, url) {
		var t = state.iconTarget;
		if (!t) return;
		var item = getMenu().items[state.editingItemIndex];
		if (!item) return;

		if (t.type === 'cat') {
			var cat = item.categories[t.catIndex];
			if (cat) {
				cat.icon = key;
				cat.icon_url = url;
			}
			renderCatList();
			if (state.editingCatIndex === t.catIndex) renderCatDetail();
		} else if (t.type === 'link') {
			var c = item.categories[t.catIndex];
			var link = c && c.links && c.links[t.linkIndex];
			if (link) {
				link.icon = key;
				link.icon_url = url;
			}
			renderCatDetail();
		} else if (t.type === 'group-link') {
			var gc = item.categories[t.catIndex];
			var group =
				gc &&
				(gc.groups || []).find(function (g) {
					return g.id === t.groupId;
				});
			var gl =
				group &&
				(group.links || []).find(function (l) {
					return l.id === t.linkId;
				});
			if (gl) {
				gl.icon = key;
				gl.icon_url = url;
			}
			renderCatDetail();
		} else if (t.type === 'feature-link') {
			var fl = item.links && item.links[t.linkIndex];
			if (fl) {
				fl.icon = key;
				fl.icon_url = url;
			}
			renderFeaturesLinkList();
		}
		renderPreview();
	}

	function saveMenu() {
		if (state.loadFailed || state.isSaving) return;
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
				}
				updateSaveState('failed', message);
				persistLocalDraft();
			})
			.always(function () {
				state.isSaving = false;
				$btn.prop('disabled', false);
				if (saveSucceeded) {
					markDirtyState();
					if (hasUnsavedChanges()) persistLocalDraft();
					else removeLocalDraft();
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
