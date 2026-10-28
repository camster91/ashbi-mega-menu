// Dashboard "Import from WordPress menu": preview first, then import as a draft.
// Runs the shipped admin script with real jQuery in a disposable DOM.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { JSDOM, VirtualConsole } = require('jsdom');
const source = fs.readFileSync('assets/js/admin.js', 'utf8');

(async () => {
	// jsdom cannot navigate; the editor URL is checked on the "Open the draft" link instead.
	const virtualConsole = new VirtualConsole();
	virtualConsole.on('jsdomError', (error) => { if (!/navigation/i.test(error.message)) console.error(error); });
	const dom = new JSDOM(`<body>
		<details class="abmm-tool" id="abmm-nav-import-panel" open><div class="abmm-tool__body">
			<select id="abmm-nav-import-menu"><option value="7">Main (12 items)</option><option value="9">Footer (3 items)</option></select>
			<button type="button" class="button abmm-nav-import-preview">Preview</button>
			<div id="abmm-nav-import-result" hidden></div>
			<button type="button" class="button button-primary abmm-nav-import-confirm" disabled aria-describedby="abmm-nav-import-hint">Import as draft</button>
			<span id="abmm-nav-import-hint">Preview the menu first.</span>
			<p id="abmm-nav-import-status" role="status"></p>
		</div></details></body>`, { url: 'http://localhost/wp-admin/admin.php?page=ashbi-mega-menu', runScripts: 'outside-only', virtualConsole });
	const { window } = dom;
	const $ = require('jquery')(window);
	window.jQuery = $;
	window.matchMedia = () => ({ matches: false });
	window.abmmAdmin = { ajaxUrl: '/ajax', nonce: 'test', strings: new Proxy({}, { get: (_, key) => String(key) }), icons: [] };
	const requests = [];
	$.post = (_, data) => { const d = $.Deferred(); requests.push({ data, d }); return d.promise(); };
	window.eval(source);
	window.document.dispatchEvent(new window.Event('DOMContentLoaded'));
	await new Promise((resolve) => window.setTimeout(resolve, 20));

	const $confirm = $('.abmm-nav-import-confirm');
	assert.equal($confirm.prop('disabled'), true, 'Import is disabled until a preview is shown');
	assert.equal($('#abmm-nav-import-hint').text(), 'Preview the menu first.', 'and the hint says why');

	$('.abmm-nav-import-preview').trigger('click');
	assert.equal(requests.length, 1);
	assert.deepEqual({ ...requests[0].data }, { action: 'abmm_import_nav_menu', nonce: 'test', term_id: '7', mode: 'preview' }, 'Preview asks for a dry run of the chosen menu');
	assert.equal($('.abmm-nav-import-preview').prop('disabled'), true, 'Preview is busy while the request runs');
	requests[0].d.resolve({ success: true, data: {
		title: 'Main (imported)',
		summary: [
			{ label: 'Home', type: 'link', categories: [] },
			{ label: 'Products', type: 'mega', columns_only: false, categories: [{ title: 'Software', links: 3 }, { title: 'Hardware', links: 1 }] },
			{ label: 'Company', type: 'mega', columns_only: true, categories: [{ title: 'Company', links: 2 }] },
		],
		report: {
			imported: 9,
			flattened: [{ label: 'Retail POS', depth: 4, into: 'a link in "Software"' }],
			skipped: [{ label: 'Draft page', reason: 'It links to content that is not published.' }],
			warnings: ['More than one item under "Software" is called "CRM".', '<img src=x onerror=alert(1)>'],
		},
	} });

	const $result = $('#abmm-nav-import-result');
	assert.equal($result.prop('hidden'), false, 'The preview is shown');
	assert.match($result.find('.abmm-nav-import__title').text(), /Main \(imported\)/);
	const outline = $result.find('.is-outline li').map((_, li) => $(li).text()).get();
	assert.deepEqual(outline, ['Home: link', 'Products: mega menu with 2 categories and 4 links', 'Company: mega menu with 2 links'], 'The outline describes each top-level item');
	assert.match($result.find('.abmm-nav-import__count').text(), /9 entries will be imported/);
	assert.match($result.text(), /Retail POS \(level 4\) is now a link in "Software"\./, 'Flattened items are listed');
	assert.match($result.text(), /Draft page: It links to content that is not published\./, 'Skipped items are listed with a reason');
	assert.match($result.text(), /is called "CRM"/, 'Warnings are listed');
	assert.equal($result.find('img').length, 0, 'Report text is escaped');
	assert.equal($confirm.prop('disabled'), false, 'Import is enabled after a preview');
	assert.equal($('#abmm-nav-import-hint').prop('hidden'), true);

	// Picking another menu resets the preview.
	$('#abmm-nav-import-menu').val('9').trigger('change');
	assert.equal($result.prop('hidden'), true, 'Changing the menu clears the preview');
	assert.equal($confirm.prop('disabled'), true, 'and needs a new preview');
	$('#abmm-nav-import-menu').val('7').trigger('change');

	// A failed preview explains itself and keeps import disabled.
	$('.abmm-nav-import-preview').trigger('click');
	requests[1].d.resolve({ success: false, data: { message: 'That WordPress menu has no items to import.' } });
	assert.equal($('#abmm-nav-import-status').text(), 'That WordPress menu has no items to import.');
	assert.equal($('#abmm-nav-import-status').attr('role'), 'alert');
	assert.equal($confirm.prop('disabled'), true);

	// Preview again, then import: the server converts again and returns the editor URL.
	$('.abmm-nav-import-preview').trigger('click');
	requests[2].d.resolve({ success: true, data: { title: 'Main (imported)', summary: [], report: { imported: 1, flattened: [], skipped: [], warnings: [] } } });
	$confirm.trigger('click');
	assert.deepEqual({ ...requests[3].data }, { action: 'abmm_import_nav_menu', nonce: 'test', term_id: '7', mode: 'import' }, 'Import sends only the menu ID, not converted data');
	assert.equal($confirm.prop('disabled'), true, 'Import cannot be sent twice');
	requests[3].d.resolve({ success: true, data: { id: 'menu_new', edit_url: '/wp-admin/admin.php?page=ashbi-mega-menu&edit=menu_new' } });
	assert.equal($('#abmm-nav-import-status .abmm-nav-import__open').attr('href'), '/wp-admin/admin.php?page=ashbi-mega-menu&edit=menu_new', 'A successful import opens the new draft, with a link as a fallback');

	window.close();
	console.log('WordPress menu import: preview first, escaped report, reset on change, failure message and import passed.');
})().catch((error) => { console.error(error); process.exitCode = 1; });
