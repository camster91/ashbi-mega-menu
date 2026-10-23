// Builder text must go through wp.i18n so translators can localize it.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { JSDOM } = require('jsdom');
const source = fs.readFileSync('assets/js/admin.js', 'utf8');
const menu = {
 title: 'Demo',
 items: [
  { id: 'home', label: 'Home', type: 'link', url: '/' },
  { id: 'products', label: 'Products', type: 'mega', categories: [] },
 ],
 cta: {},
 settings: { presentation: 'unified' },
};
const dom = new JSDOM(`<body><div class="abmm-builder-topbar"><span class="abmm-save-status"></span></div>
 <div id="abmm-builder" data-menu-id="demo" data-menu-revision="revision-1"><aside id="abmm-builder-sidebar">
 <input id="abmm-menu-title" value="Demo"><div id="abmm-nav-items"></div><ul class="abmm-readiness-issues"></ul><div class="abmm-readiness-summary"></div></aside>
 <main class="abmm-builder__main"><button class="abmm-open-selected-mega" hidden></button><div class="abmm-preview-shell" data-abmm-preview-viewport="desktop"><div id="abmm-preview"></div></div></main></div>
 <script id="abmm-menu-data" type="application/json">${JSON.stringify(menu)}</script></body>`, { url: 'http://localhost/', runScripts: 'outside-only' });
const { window } = dom;
const $ = require('jquery')(window);
window.jQuery = $;
window.matchMedia = () => ({ matches: false });
$.fn.wpColorPicker = $.fn.sortable = function () { return this; };
window.abmmAdmin = { ajaxUrl: '/ajax', nonce: 'test', strings: new Proxy({}, { get: (_, key) => String(key) }), icons: [] };
$.post = () => $.Deferred().promise();
// Only __ is provided, so the built-in sprintf fallback is exercised too.
window.wp = { i18n: { __: (text, domain) => {
 assert.equal(domain, 'ashbi-mega-menu', 'every builder string uses the plugin text domain');
 return '[xx] ' + text;
} } };
window.eval(source);
window.document.dispatchEvent(new window.Event('DOMContentLoaded'));

setTimeout(() => {
 const linkRow = $('#abmm-nav-items > .abmm-nav-item-row').first();
 const megaRow = $('#abmm-nav-items > .abmm-nav-item-row').eq(1);
 // Link options moved from a <details> box into a popover off the URL field.
 assert.equal(linkRow.find('.abmm-link-options__toggle .screen-reader-text').text(), '[xx] Link options');
 assert.equal(linkRow.find('.abmm-link-options__toggle').attr('title'), '[xx] Link options');
 assert.equal(linkRow.find('.abmm-link-options__panel').attr('aria-label'), '[xx] Link options');
 assert.equal(linkRow.find('.abmm-duplicate-nav').text(), '[xx] Duplicate');
 assert.equal(linkRow.find('.abmm-duplicate-nav').attr('aria-label'), '[xx] Duplicate navigation item Home');
 assert.equal(linkRow.find('.abmm-remove-nav').attr('title'), '[xx] Remove navigation item');
 assert.equal(megaRow.find('.abmm-edit-mega').text(), '[xx] Edit content');
 assert.equal(megaRow.find('.abmm-mega-style-badge').text(), '[xx] With categories');
 assert.doesNotMatch($('#abmm-nav-items').html(), />(Edit content|Duplicate|Link options)</, 'no untranslated builder labels remain');
 console.log('Builder labels use wp.i18n with the plugin text domain.');
 window.close();
}, 20);
