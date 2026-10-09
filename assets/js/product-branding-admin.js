(function () {
	'use strict';

	function updatePreview() {
		var input = document.querySelector('[data-abmm-brand-url]');
		var preview = document.querySelector('[data-abmm-brand-preview]');
		var image = document.querySelector('[data-abmm-brand-preview-image]');
		if (!input || !preview || !image) {
			return;
		}

		var value = input.value.trim();
		if (!value) {
			preview.hidden = true;
			image.removeAttribute('src');
			return;
		}

		image.src = value;
		preview.hidden = false;
	}

	function attachmentIdFieldFor(urlField) {
		if (!urlField || !urlField.id || !urlField.id.match(/^abmm-product-logo-.+-url$/)) {
			return null;
		}
		return document.getElementById(urlField.id.replace(/-url$/, '-id'));
	}

	function updateMode() {
		var mode = document.querySelector('[data-abmm-branding-mode]');
		document.querySelectorAll('[data-abmm-product-text-branding]').forEach(function (field) {
			field.hidden = !mode || mode.value !== 'product_text';
		});
	}

	document.addEventListener('click', function (event) {
		var choose = event.target.closest('[data-abmm-media-target]');
		if (choose) {
			event.preventDefault();
			if (!window.wp || !wp.media) {
				return;
			}

			var target = document.getElementById(choose.getAttribute('data-abmm-media-target'));
			var idTarget = document.getElementById(choose.getAttribute('data-abmm-media-id-target'));
			if (!target) {
				return;
			}

			var frame = wp.media({
				title: (window.abmmProductBranding && abmmProductBranding.mediaTitle) || 'Choose a product lockup',
				button: { text: (window.abmmProductBranding && abmmProductBranding.mediaButton) || 'Use this image' },
				multiple: false,
				library: { type: 'image' }
			});

			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				target.value = attachment.url || '';
				if (idTarget) {
					idTarget.value = attachment.id || '';
				}
				updatePreview();
				target.focus();
			});
			frame.open();
			return;
		}

		var clear = event.target.closest('[data-abmm-media-clear]');
		if (clear) {
			event.preventDefault();
			var urlField = document.getElementById(clear.getAttribute('data-abmm-media-clear'));
			var idField = document.getElementById(clear.getAttribute('data-abmm-media-id-clear'));
			if (urlField) {
				urlField.value = '';
				updatePreview();
				urlField.focus();
			}
			if (idField) {
				idField.value = '';
			}
		}
	});

	document.addEventListener('input', function (event) {
		if (!event.target.matches('input[type="url"][id^="abmm-product-logo-"]')) {
			return;
		}

		// A manually edited URL must become authoritative. Otherwise a previously
		// selected Media Library attachment would silently override the value the
		// editor can see in this field when the frontend resolves the lockup.
		var idField = attachmentIdFieldFor(event.target);
		if (idField) {
			idField.value = '';
		}
		updatePreview();
	});

	document.addEventListener('change', function (event) {
		if (event.target.matches('[data-abmm-branding-mode]')) {
			updateMode();
		}
	});

	updatePreview();
	updateMode();
})();
