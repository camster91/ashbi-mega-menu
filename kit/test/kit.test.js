'use strict';
const test = require('node:test');
const assert = require('node:assert');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const vm = require('node:vm');
const { build } = require('../build.js');

function fresh() {
	return fs.mkdtempSync(path.join(os.tmpdir(), 'ashbi-kit-'));
}

test('build replaces every token with the plugin prefix', () => {
	const out = fresh();
	build('ashbpg', out);
	for (const file of ['front.css', 'admin.css', 'studio.js']) {
		const text = fs.readFileSync(path.join(out, file), 'utf8');
		assert.ok(!text.includes('__P__'), file + ' still has __P__');
		assert.ok(!text.includes('__VERSION__'), file + ' still has __VERSION__');
		assert.ok(text.includes('ashbpg'), file + ' has no prefix');
	}
});

test('two prefixes produce copies that share no class names', () => {
	const a = fresh();
	const b = fresh();
	build('ashbpg', a);
	build('ashbte', b);
	const classes = (dir) => new Set((fs.readFileSync(path.join(dir, 'front.css'), 'utf8').match(/\.[a-z][a-z0-9_-]*/g) || []).filter((c) => c.startsWith('.ash')));
	const overlap = [...classes(a)].filter((c) => classes(b).has(c));
	assert.deepStrictEqual(overlap, []);
});

test('front-end selectors stay inside :where() so themes win', () => {
	const css = fs.readFileSync(path.join(__dirname, '..', 'src', 'front.css'), 'utf8').replace(/\/\*[\s\S]*?\*\//g, '');
	const rules = css.split('}').map((r) => r.split('{')[0].trim()).filter((s) => s && !s.startsWith('@') && !/^(from|to|\d+%)$/.test(s));
	for (const selector of rules) {
		for (const part of selector.split(/,(?![^(]*\))/)) {
			assert.match(part.trim(), /^:where\(/, 'Selector not wrapped in :where(): ' + part.trim());
		}
	}
});

test('front-end CSS never styles html, body or theme elements', () => {
	const css = fs.readFileSync(path.join(__dirname, '..', 'src', 'front.css'), 'utf8');
	assert.doesNotMatch(css, /(^|[\s,}])(html|body|header|nav|footer)\s*[{,]/m);
});

test('bad prefixes are refused', () => {
	assert.throws(() => build('A', fresh()));
	assert.throws(() => build('has-dash', fresh()));
});

test('shortcode builder leaves out defaults and strips brackets and quotes', () => {
	const out = fresh();
	build('ashbpg', out);
	const sandbox = { window: {}, document: {}, globalThis: {} };
	vm.runInNewContext(fs.readFileSync(path.join(out, 'studio.js'), 'utf8'), sandbox);
	const api = sandbox.window.ashbpgStudio;
	const fields = [
		{ key: 'layout', default: 'grid' },
		{ key: 'columns', default: '3' },
		{ key: 'heading', default: '' },
		{ key: 'filters', default: false, on: 'show', off: 'hide' }
	];
	assert.strictEqual(api.buildShortcode('ashbi_portfolio', fields, { layout: 'grid', columns: '3', heading: '', filters: false }), '[ashbi_portfolio]');
	assert.strictEqual(
		api.buildShortcode('ashbi_portfolio', fields, { layout: 'bento', columns: '4', heading: 'Our "best" [work]', filters: true }),
		'[ashbi_portfolio layout="bento" columns="4" heading="Our best work" filters="show"]'
	);
});
