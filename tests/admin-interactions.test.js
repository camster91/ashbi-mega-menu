// Executes the shipped admin script with real jQuery and a disposable DOM.
// WordPress widgets/AJAX are adapters; no browser, network or production state.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { JSDOM } = require('jsdom');
const source = fs.readFileSync('assets/js/admin.js', 'utf8');
const menu = { title: 'Demo', items: [{ id: 'home', label: 'Home', type: 'link', url: '/' }], cta: {}, settings: { presentation:'unified' } };
const sections = ['content', 'design', 'settings', 'help'];
async function fixture(narrow = false, malformed = false) {
 const dom = new JSDOM(`<body><div class="abmm-builder-topbar"><button class="abmm-save-menu">Save</button><span class="abmm-save-status"></span></div>
 <button class="abmm-builder-panel-toggle">Settings</button><div id="abmm-builder-panel-backdrop" hidden></div>
 <div id="abmm-builder" data-menu-id="demo" data-menu-revision="revision-1"><aside id="abmm-builder-sidebar"><button class="abmm-builder-panel-close">Close</button>
 <div class="abmm-builder-sections">${sections.map(s=>`<button data-abmm-builder-section="${s}">${s}</button>`).join('')}</div>
 ${sections.map(s=>`<div class="abmm-builder-section-group" data-abmm-builder-section-panel="${s}"></div>`).join('')}
 <input id="abmm-menu-title" value="Demo"><div id="abmm-nav-items"></div><ul class="abmm-readiness-issues"></ul><div class="abmm-readiness-summary"></div></aside>
 <main class="abmm-builder__main"><div class="abmm-preview-viewports">${['desktop','tablet','mobile'].map(s=>`<button data-abmm-preview-viewport="${s}">${s}</button>`).join('')}</div><div class="abmm-preview-shell" data-abmm-preview-viewport="desktop"><div id="abmm-preview"></div></div></main></div>
 <script id="abmm-menu-data" type="application/json">${malformed ? '{invalid' : JSON.stringify(menu)}</script></body>`, { url:'http://localhost/', runScripts:'outside-only' });
 const { window } = dom;
 const $ = require('jquery')(window);
 window.jQuery = $;
 window.matchMedia = () => ({ matches: narrow });
 // Layout is absent in jsdom: retain focusability for keyboard contract tests.
 $.expr.pseudos.visible = el => !el.hidden && !el.closest('[hidden]');
 $.fn.wpColorPicker = $.fn.sortable = function() { return this; };
 window.abmmAdmin = { ajaxUrl:'/ajax', nonce:'test', strings: new Proxy({}, {get:(_,key)=>String(key)}), icons:[] };
 const requests = [];
 $.post = (_,data) => { const d=$.Deferred(); requests.push({data,d}); return d.promise(); };
 window.eval(source);
 window.document.dispatchEvent(new window.Event('DOMContentLoaded'));
 await new Promise(resolve=>window.setTimeout(resolve,20));
 return {window,$,requests,close:()=>window.close()};
}
async function backupFixture() {
 const dom=new JSDOM('<body><button class="abmm-load-backups">Load</button><div id="abmm-backup-list"></div><div id="abmm-backup-status"></div></body>',{url:'http://localhost/',runScripts:'outside-only'});
 const {window}=dom; const $=require('jquery')(window); window.jQuery=$;
 window.abmmAdmin={ajaxUrl:'/ajax',nonce:'test',strings:{}};
 const requests=[]; $.post=(_,data)=>{const d=$.Deferred();requests.push({data,d});return d.promise();};
 window.eval(source); window.document.dispatchEvent(new window.Event('DOMContentLoaded'));
 await new Promise(resolve=>window.setTimeout(resolve,20));
 return {window,$,requests,close:()=>window.close()};
}
(async()=>{
 let f=await fixture(); let {$,requests}=f;
 assert.equal($('#abmm-preview .abmm-nav__link').text(),'Home','loaded transport renders actual preview');
 $('[data-abmm-preview-viewport="mobile"]').trigger('click');
 assert.equal($('.abmm-preview-shell').attr('data-abmm-preview-viewport'),'mobile');
 assert.equal($('[data-abmm-preview-viewport="mobile"]').attr('aria-pressed'),'true');
 assert.equal($('[data-abmm-preview-viewport="desktop"]').attr('aria-pressed'),'false');
 $('#abmm-preview .abmm-nav__toggle').trigger('click');
 assert.equal($('#abmm-preview .abmm-nav__toggle').attr('aria-expanded'),'true');
 $('[data-abmm-preview-viewport="desktop"]').trigger('click');
 assert.equal($('#abmm-preview .abmm-nav__toggle').attr('aria-expanded'),'false','switching viewport closes drawer');
 $('[data-abmm-builder-section="content"]').trigger($.Event('keydown',{key:'End'}));
 assert.equal($('[data-abmm-builder-section="help"]').attr('aria-selected'),'true');
 assert.equal($('[data-abmm-builder-section-panel="help"]').prop('hidden'),false);
 assert.equal($('[data-abmm-builder-section-panel="content"]').prop('hidden'),true);
 $('#abmm-menu-title').val('Changed').trigger('input');
 $('.abmm-save-menu').trigger('click');
 assert.equal(requests.length,1); assert.equal(requests[0].data.base_revision,'revision-1');
 assert.equal(JSON.parse(requests[0].data.menu_data).title,'Changed');
 assert.equal(JSON.parse(requests[0].data.menu_data).settings.presentation,'unified','hidden imported metadata survives ordinary edits');
 assert.equal($('.abmm-save-menu').prop('disabled'),true);
 requests[0].d.reject({responseJSON:{data:{message:'Server unavailable'}}});
 assert.equal($('.abmm-save-status').attr('role'),'alert');
 assert.match($('.abmm-save-status').text(),/Server unavailable/);
 assert.equal($('.abmm-save-menu').prop('disabled'),false);
 assert.ok(f.window.localStorage.getItem('abmmDraft:demo'),'failed save retains local draft');
 $('.abmm-retry-save').trigger('click');
 assert.equal(requests.length,2); requests[1].d.resolve({success:true,data:{revision:'revision-2'}});
 assert.equal($('.abmm-save-status').hasClass('is-saved'),true);
 assert.equal(f.window.localStorage.getItem('abmmDraft:demo'),null,'successful retry clears draft');
 f.close();
 f=await fixture(true); $=f.$;
 $('.abmm-builder-panel-toggle').trigger('click');
 assert.equal($('#abmm-builder-sidebar').attr('role'),'dialog');
 assert.equal($('.abmm-builder__main').prop('inert'),true);
 $(f.window.document).trigger($.Event('keydown',{key:'Escape'}));
 assert.equal($('#abmm-builder-sidebar').hasClass('is-open'),false);
 assert.equal($('.abmm-builder__main').prop('inert'),false);
 f.close();
 f=await fixture(false,true); $=f.$;
 assert.equal($('.abmm-save-menu').prop('disabled'),true,'malformed transport cannot overwrite saved data');
 $('.abmm-save-menu').trigger('click'); assert.equal(f.requests.length,0); f.close();
 f=await backupFixture(); $=f.$; requests=f.requests;
 $('.abmm-load-backups').trigger('click');
 assert.equal(requests[0].data.action,'abmm_list_backups');
 requests[0].d.resolve({success:true,data:{backups:[{id:'backup-1',created_at:'2026-10-10',menu_count:2}],revision:'collection-1'}});
 assert.equal($('.abmm-backup-row').length,1);
 $('.abmm-restore-backup').trigger('click');
 assert.equal(requests.length,1,'choosing restore does not mutate before confirmation');
 assert.match($('.abmm-backup-confirm').text(),/ALL current menus/);
 $('.abmm-cancel-backup').trigger('click');
 assert.equal($('.abmm-backup-confirm').length,0); assert.equal(requests.length,1);
 $('.abmm-restore-backup').trigger('click'); $('.abmm-confirm-backup').trigger('click');
 assert.equal(requests[1].data.action,'abmm_restore_backup');
 assert.equal(requests[1].data.revision,'collection-1');
 assert.equal(requests[1].data.backup_id,'backup-1');
 requests[1].d.reject({responseJSON:{data:{message:'Menus changed. Reload snapshots.'}}});
 assert.equal($('#abmm-backup-status').attr('role'),'alert');
 assert.match($('#abmm-backup-status').text(),/Menus changed/);
 assert.equal($('.abmm-load-backups').prop('disabled'),false); f.close();
 console.log('Admin viewport, drawer, keyboard tabs, sheet, save/retry, malformed load and explicit backup restore tests passed.');
})().catch(error=>{console.error(error);process.exitCode=1;});
