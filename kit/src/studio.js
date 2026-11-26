/*!
 * Ashbi UI kit: Studio builder (__VERSION__)
 * Turns a small field list into live controls, a shortcode that writes
 * itself, and a preview rendered by the plugin's own shortcode.
 *
 * Usage (from the plugin's admin page):
 *   window.__P__Studio.init(document.getElementById('…'), {
 *     shortcode: 'ashbi_portfolio',
 *     fields: [ { key: 'layout', type: 'seg', label: 'Layout', options: [['grid','Grid']], default: 'grid' } ],
 *     preview: { url: ajaxurl, action: '__P___preview', nonce: '…' },
 *     i18n: { copied: 'Copied', copy: 'Copy shortcode', failed: 'Preview could not load.' }
 *   });
 *
 * Field types: seg, preset, swatch, select, number, toggle, text.
 * No dependencies. Builds DOM with createElement only.
 */
(function (root) {
	'use strict';

	var PRESETS = [
		['minimal', 'Minimal', 'Hairlines, no shadow'],
		['editorial', 'Editorial', 'Big type, rules'],
		['bold', 'Bold', 'Accent cards'],
		['glass', 'Glass', 'Soft, layered']
	];

	var SWATCHES = ['#2457ff', '#e4572e', '#0f8a4b', '#7c3aed', '#192238'];

	function el(tag, attrs, children) {
		var node = document.createElement(tag);
		Object.keys(attrs || {}).forEach(function (name) {
			var value = attrs[name];
			if (value === null || value === undefined || value === false) {
				return;
			}
			if (name === 'text') {
				node.textContent = value;
			} else if (name === 'onclick' || name === 'oninput' || name === 'onchange') {
				node.addEventListener(name.slice(2), value);
			} else {
				node.setAttribute(name, value === true ? '' : String(value));
			}
		});
		(children || []).forEach(function (child) {
			if (child) {
				node.appendChild(typeof child === 'string' ? document.createTextNode(child) : child);
			}
		});
		return node;
	}

	function quote(value) {
		return String(value).replace(/["\[\]]/g, '');
	}

	/**
	 * Shortcode text from state. Values equal to their default are left out,
	 * so the code stays short and readable.
	 */
	function buildShortcode(tag, fields, state) {
		var parts = ['[' + tag];
		fields.forEach(function (field) {
			var value = state[field.key];
			var attr = field.attr || field.key;
			if (field.preview === false && field.code === false) {
				return;
			}
			if (value === undefined || value === '' || value === field.default) {
				return;
			}
			if (typeof value === 'boolean') {
				value = value ? (field.on || 'show') : (field.off || 'hide');
			}
			parts.push(attr + '="' + quote(value) + '"');
		});
		return parts.join(' ') + ']';
	}

	function Studio(rootNode, config) {
		this.root = rootNode;
		this.config = config;
		this.i18n = config.i18n || {};
		this.fields = config.fields || [];
		this.state = {};
		this.timer = null;
		this.request = 0;
		var self = this;
		this.fields.forEach(function (field) {
			self.state[field.key] = field.default;
		});
		this.dark = false;
		this.device = 'desktop';
		this.render();
		this.refresh();
	}

	Studio.prototype.set = function (key, value) {
		this.state[key] = value;
		this.syncControls();
		this.refresh();
	};

	Studio.prototype.refresh = function () {
		var code = buildShortcode(this.config.shortcode, this.fields, this.state);
		this.codeNode.textContent = code;
		this.schedulePreview();
		if (typeof this.config.onChange === 'function') {
			this.config.onChange(this.state, code);
		}
	};

	Studio.prototype.schedulePreview = function () {
		var self = this;
		if (!this.config.preview) {
			return;
		}
		clearTimeout(this.timer);
		this.timer = setTimeout(function () {
			self.loadPreview();
		}, 220);
	};

	Studio.prototype.loadPreview = function () {
		var self = this;
		var preview = this.config.preview;
		var ticket = ++this.request;
		var body = new FormData();
		body.append('action', preview.action);
		body.append('nonce', preview.nonce);
		Object.keys(this.state).forEach(function (key) {
			var value = self.state[key];
			body.append('atts[' + key + ']', typeof value === 'boolean' ? (value ? '1' : '0') : value);
		});
		this.host.classList.add('is-loading');
		this.host.setAttribute('aria-busy', 'true');
		fetch(preview.url, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (response) {
				return response.json();
			})
			.then(function (json) {
				if (ticket !== self.request) {
					return;
				}
				if (!json || !json.success) {
					throw new Error('preview');
				}
				// Server-rendered by the plugin's own escaped shortcode output.
				self.host.innerHTML = json.data.html;
				self.status.textContent = '';
			})
			.catch(function () {
				if (ticket === self.request) {
					self.status.textContent = self.i18n.failed || 'Preview could not load.';
				}
			})
			.then(function () {
				if (ticket === self.request) {
					self.host.classList.remove('is-loading');
					self.host.removeAttribute('aria-busy');
				}
			});
	};

	Studio.prototype.control = function (field) {
		var self = this;
		var value = this.state[field.key];
		var id = this.root.id + '-' + field.key;

		if (field.type === 'seg') {
			return el('fieldset', { class: '__P__-studio__section' }, [
				el('legend', { text: field.label }),
				el('div', { class: '__P__-studio__seg', 'data-key': field.key }, field.options.map(function (option) {
					return el('button', {
						type: 'button',
						'data-value': option[0],
						'aria-pressed': String(value === option[0]),
						text: option[1],
						onclick: function () { self.set(field.key, option[0]); }
					});
				}))
			]);
		}

		if (field.type === 'preset') {
			var options = field.options || PRESETS;
			return el('fieldset', { class: '__P__-studio__section' }, [
				el('legend', { text: field.label }),
				el('div', { class: '__P__-studio__presets', 'data-key': field.key }, options.map(function (option) {
					return el('button', {
						type: 'button',
						class: '__P__-studio__preset',
						'data-preset': option[0],
						'data-value': option[0],
						'aria-pressed': String(value === option[0]),
						onclick: function () { self.set(field.key, option[0]); }
					}, [el('i', { 'aria-hidden': 'true' }), el('strong', { text: option[1] }), el('span', { text: option[2] || '' })]);
				}))
			]);
		}

		if (field.type === 'swatch') {
			var picker = el('input', {
				type: 'color',
				value: value,
				'aria-label': field.customLabel || 'Custom color',
				oninput: function (event) { self.set(field.key, event.target.value); }
			});
			return el('fieldset', { class: '__P__-studio__section' }, [
				el('legend', { text: field.label }),
				el('div', { class: '__P__-studio__swatches', 'data-key': field.key }, (field.options || SWATCHES).map(function (color) {
					return el('button', {
						type: 'button',
						class: '__P__-studio__swatch',
						style: 'background:' + color,
						'data-value': color,
						'aria-label': color,
						'aria-pressed': String(value === color),
						onclick: function () { self.set(field.key, color); }
					});
				}).concat([picker]))
			]);
		}

		if (field.type === 'toggle') {
			return el('label', { class: '__P__-studio__check', for: id }, [
				el('input', {
					type: 'checkbox',
					id: id,
					checked: !!value,
					onchange: function (event) { self.set(field.key, event.target.checked); }
				}),
				field.label
			]);
		}

		var input;
		if (field.type === 'select') {
			input = el('select', {
				id: id,
				onchange: function (event) { self.set(field.key, event.target.value); }
			}, field.options.map(function (option) {
				return el('option', { value: option[0], selected: value === option[0], text: option[1] });
			}));
		} else {
			input = el('input', {
				id: id,
				type: field.type === 'number' ? 'number' : 'text',
				min: field.min,
				max: field.max,
				value: value,
				onchange: function (event) {
					var next = event.target.value;
					if (field.type === 'number') {
						next = Math.max(field.min || 0, Math.min(field.max || 999, parseInt(next, 10) || field.default));
					}
					self.set(field.key, field.type === 'number' ? String(next) : next);
				}
			});
		}
		return el('label', { class: '__P__-studio__field', for: id }, [field.label, input]);
	};

	Studio.prototype.syncControls = function () {
		var self = this;
		this.root.querySelectorAll('[data-key]').forEach(function (group) {
			var value = self.state[group.getAttribute('data-key')];
			group.querySelectorAll('[data-value]').forEach(function (button) {
				button.setAttribute('aria-pressed', String(button.getAttribute('data-value') === value));
			});
		});
	};

	Studio.prototype.toggle = function (label, options, current, onPick) {
		var group = el('div', { class: '__P__-studio__toggle', role: 'group', 'aria-label': label });
		options.forEach(function (option) {
			group.appendChild(el('button', {
				type: 'button',
				'aria-pressed': String(option[0] === current),
				text: option[1],
				onclick: function () {
					group.querySelectorAll('button').forEach(function (button) {
						button.setAttribute('aria-pressed', 'false');
					});
					this.setAttribute('aria-pressed', 'true');
					onPick(option[0]);
				}
			}));
		});
		return group;
	};

	Studio.prototype.render = function () {
		var self = this;
		var t = this.i18n;
		this.host = el('div', { class: '__P__-studio__host', 'data-device': 'desktop', 'aria-live': 'polite' });
		this.status = el('p', { class: '__P__-studio__live', role: 'status' });
		this.codeNode = el('code', { class: '__P__-studio__code' });
		var copyButton = el('button', {
			type: 'button',
			class: 'button button-primary',
			text: t.copy || 'Copy shortcode',
			onclick: function () { self.copy(copyButton); }
		});

		var toolbar = el('div', { class: '__P__-studio__toolbar' }, [
			this.toggle(t.device || 'Device', [['desktop', t.desktop || 'Desktop'], ['tablet', t.tablet || 'Tablet'], ['phone', t.phone || 'Phone']], 'desktop', function (device) {
				self.host.setAttribute('data-device', device);
			}),
			this.toggle(t.background || 'Page background', [['light', t.light || 'Light page'], ['dark', t.dark || 'Dark page']], 'light', function (mode) {
				self.host.classList.toggle('is-dark', mode === 'dark');
			}),
			el('span', { class: '__P__-studio__note', text: t.note || 'Live preview' })
		]);

		var inspector = el('div', { class: '__P__-studio__inspector' }, this.fields.filter(function (field) {
			return field.control !== false;
		}).map(function (field) {
			return self.control(field);
		}).concat([
			el('section', { class: '__P__-studio__place', 'aria-label': t.place || 'Add to your site' }, [
				el('h2', { class: '__P__-studio__heading', text: t.place || 'Add to your site' }),
				this.codeNode,
				copyButton,
				el('p', { text: t.placeHelp || 'Paste it into a Shortcode block, a Shortcode widget in Elementor, or any text area that runs shortcodes.' }),
				this.status
			])
		]));

		this.root.textContent = '';
		this.root.appendChild(el('div', { class: '__P__-studio__builder' }, [
			el('div', { class: '__P__-studio__stage' }, [toolbar, this.host]),
			inspector
		]));
	};

	Studio.prototype.copy = function (button) {
		var text = this.codeNode.textContent;
		var label = this.i18n.copy || 'Copy shortcode';
		var done = this.i18n.copied || 'Copied';
		var self = this;
		function flash() {
			button.textContent = done;
			self.status.textContent = done;
			setTimeout(function () { button.textContent = label; }, 1600);
		}
		function fallback() {
			var range = document.createRange();
			range.selectNodeContents(self.codeNode);
			var selection = window.getSelection();
			selection.removeAllRanges();
			selection.addRange(range);
			self.status.textContent = self.i18n.selectToCopy || 'Press Ctrl+C or Cmd+C to copy.';
		}
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(text).then(flash, fallback);
		} else {
			fallback();
		}
	};

	root.__P__Studio = {
		init: function (node, config) {
			return node ? new Studio(node, config) : null;
		},
		buildShortcode: buildShortcode
	};
})(typeof window !== 'undefined' ? window : globalThis);
