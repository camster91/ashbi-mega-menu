// Execute the shipped archive controls with translated text and real jQuery.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { JSDOM } = require('jsdom');
const source = fs.readFileSync('assets/js/admin.js', 'utf8');
const title = '<img src=x onerror="alert(1)"> A & B $& $` $\' %2$d';
const strings = {
	archiveMenu: 'Archiver le menu',
	archiveChecking: 'Vérification <b>des emplacements</b> & "liens"…',
	archiveConfirmTitle: 'Archiver « %1$s » ? <script>alerte</script> %1$s',
	archivePlacements: 'Widgets : %2$d ; blocs : %1$d ; encore %2$d. <img src=x>',
	archiveConfirm: 'Archiver & conserver <b>récupérable</b>',
	cancel: 'Annuler <img src=x>',
};

(async () => {
	const dom = new JSDOM('<body><div class="abmm-menu-card" data-menu-id="demo"><button class="abmm-delete-menu" data-menu-id="demo">Archiver</button></div><div id="abmm-list-status"></div></body>', { url: 'http://localhost/', runScripts: 'outside-only' });
	const { window } = dom;
	try {
		const $ = require('jquery')(window);
		window.jQuery = $;
		window.abmmAdmin = { ajaxUrl: '/ajax', nonce: 'fixture', strings };
		const requests = [];
		$.post = (_, data) => { const d = $.Deferred(); requests.push({ data, d }); return d.promise(); };
		$('.abmm-delete-menu').attr('data-menu-title', title);
		window.eval(source);
		window.document.dispatchEvent(new window.Event('DOMContentLoaded'));
		await new Promise(resolve => window.setTimeout(resolve, 20));
		const $archive = $('.abmm-delete-menu');
		$archive.trigger('click');
		assert.equal($archive.text(), strings.archiveChecking, 'placement checking uses literal translated text');
		assert.equal($archive.prop('disabled'), true);
		assert.equal(requests[0].data.action, 'abmm_menu_references');
		assert.equal(requests[0].data.menu_id, 'demo');
		requests[0].d.resolve({ success: true, data: { blocks: 2, widgets: 5, warning: '<svg onload="alert(1)">A & B</svg>' } });
		const $confirm = $('.abmm-archive-confirm');
		assert.equal($confirm.find('strong').text(), 'Archiver « ' + title + ' » ? <script>alerte</script> ' + title, 'title substitutions preserve literal dollar tokens and repeat safely');
		assert.equal($confirm.find('p').first().text(), 'Widgets : 5 ; blocs : 2 ; encore 5. <img src=x>', 'translated count placeholders can reorder and repeat');
		assert.equal($confirm.find('p').last().text(), '<svg onload="alert(1)">A & B</svg>');
		assert.equal($confirm.find('.abmm-confirm-archive').text(), strings.archiveConfirm);
		assert.equal($confirm.find('.abmm-cancel-archive').text(), strings.cancel);
		assert.equal(window.document.activeElement, $confirm.find('.abmm-confirm-archive')[0], 'confirmation receives focus');
		assert.equal($archive.text(), strings.archiveMenu);
		assert.equal($archive.prop('disabled'), false);
		assert.equal($('img, svg, script, b, [onerror], [onload]').length, 0, 'titles, translations and warnings cannot create markup');
		$confirm.find('.abmm-cancel-archive').trigger('click');
		assert.equal($('.abmm-archive-confirm').length, 0);
		assert.equal(window.document.activeElement, $archive[0], 'cancel returns focus to the archive control');
		assert.equal(requests.length, 1, 'cancel never sends an archive mutation');
		$archive.trigger('click');
		requests[1].d.resolve({ success: true, data: {} });
		assert.equal($('.abmm-archive-confirm p').first().text(), 'Widgets : 0 ; blocs : 0 ; encore 0. <img src=x>', 'missing reference counts retain zero defaults');
		$('.abmm-confirm-archive').trigger('click');
		assert.equal(requests[2].data.action, 'abmm_delete_menu');
		assert.equal(requests[2].data.menu_id, 'demo');
		assert.equal($('.abmm-confirm-archive').prop('disabled'), true, 'confirmed archive prevents duplicate requests');
	} finally {
		window.close();
	}
	console.log('Translated archive controls, safe substitutions, DOM escaping and focus passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
