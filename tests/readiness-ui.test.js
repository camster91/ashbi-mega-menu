// The editor checklist flags links typed as plain text and labels too long for the menu bar.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { JSDOM } = require('jsdom');
const source = fs.readFileSync('assets/js/admin.js', 'utf8');
const menu = {
 title: 'Demo',
 items: [
  { id: 'a', label: 'About', type: 'link', url: 'about' },
  { id: 'b', label: 'Contact', type: 'link', url: 'www.example.com/contact' },
  { id: 'c', label: 'x'.repeat(45), type: 'link', url: '/long/' },
  { id: 'd', label: 'Mail', type: 'link', url: 'mailto:hello@example.com' },
 ],
 cta: {},
 settings: { presentation: 'unified' },
};
const dom = new JSDOM(`<body><div class="abmm-builder-topbar"><span class="abmm-save-status"></span></div>
 <div id="abmm-builder" data-menu-id="demo" data-menu-revision="r1"><aside id="abmm-builder-sidebar">
 <input id="abmm-menu-title" value="Demo"><div id="abmm-nav-items"></div><ul class="abmm-readiness-issues"></ul><div class="abmm-readiness-summary"></div></aside>
 <main class="abmm-builder__main"><button class="abmm-open-selected-mega" hidden></button><div class="abmm-preview-shell" data-abmm-preview-viewport="desktop"><div id="abmm-preview"></div></div></main></div>
 <script id="abmm-menu-data" type="application/json">${JSON.stringify(menu)}</script></body>`, { url: 'http://localhost/', runScripts: 'outside-only' });
const { window } = dom;
const $ = require('jquery')(window);
window.jQuery = $;
window.matchMedia = () => ({ matches: false });
$.fn.wpColorPicker = $.fn.sortable = function () { return this; };
const strings = { checkUrl: 'Check the link for "%s".', checkLong: 'Shorten "%s".', checkPending: '%s to fix' };
window.abmmAdmin = { ajaxUrl: '/ajax', nonce: 'test', strings: new Proxy(strings, { get: (target, key) => target[key] || String(key) }), icons: [] };
$.post = () => $.Deferred().promise();
window.eval(source);
window.document.dispatchEvent(new window.Event('DOMContentLoaded'));

setTimeout(() => {
 const issues = $('.abmm-readiness-issues li').map((_, li) => $(li).text()).get();
 assert.ok(issues.includes('Check the link for "About".'), 'plain text "about" is flagged: ' + JSON.stringify(issues));
 assert.ok(!issues.some((text) => text.includes('Contact')), 'a domain without a scheme is accepted');
 assert.ok(!issues.some((text) => text.includes('Mail')), 'mailto links are accepted');
 assert.ok(issues.includes('Shorten "' + 'x'.repeat(24) + '…".'), 'a label over 40 characters is flagged, shortened: ' + JSON.stringify(issues));
 console.log('Editor checklist flags plain-text links and long menu bar labels.');
 window.close();
}, 20);
