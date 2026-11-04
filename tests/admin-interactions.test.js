// Executes the shipped admin script with real jQuery and a disposable DOM.
// WordPress widgets/AJAX are adapters; no browser, network or production state.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { JSDOM } = require('jsdom');
const source = fs.readFileSync('assets/js/admin.js', 'utf8');
const menu = { title: 'Demo', items: [{ id: 'home', label: 'Home', type: 'link', url: '/' }], cta: {}, settings: { presentation:'unified' } };
const sections = ['content', 'design', 'settings', 'help'];
const tabs = ['content', 'design', 'settings'];
async function fixture(narrow = false, malformed = false, draft = null, menuId = 'demo', menuData = menu, extraMain = '', url = 'http://localhost/') {
 const dom = new JSDOM(`<body><div class="abmm-builder-topbar"><button class="abmm-save-menu">Save</button><button class="abmm-export-current-menu" data-menu-id="${menuId}">Export</button><span class="abmm-save-status"></span></div>
 <div id="abmm-draft-recovery" hidden><p class="abmm-draft-recovery__message"></p><button class="abmm-restore-draft">Restore</button><button class="abmm-download-draft">Download</button><button class="abmm-reload-server">Reload</button><button class="abmm-discard-draft">Discard</button></div>
 <button data-abmm-workflow="content">Edit</button><button data-abmm-workflow="help">Place</button><button data-abmm-workflow="verify">Verify</button>
 <section class="abmm-placement-panel"><p class="abmm-placement-save-hint"></p><button class="abmm-copy-placement" data-shortcode='[ashbi_mega_menu id="demo"]'>Copy</button></section><div id="abmm-verification-panel" tabindex="-1"></div>
 <button class="abmm-builder-panel-toggle">Settings</button><div id="abmm-builder-panel-backdrop" hidden></div>
 <div id="abmm-builder" data-menu-id="${menuId}" data-menu-revision="revision-1"><aside id="abmm-builder-sidebar"><button class="abmm-builder-panel-close">Close</button>
 <div class="abmm-builder-sections">${tabs.map(s=>`<button data-abmm-builder-section="${s}">${s}</button>`).join('')}</div>
 ${sections.map(s=>`<div class="abmm-builder-section-group" data-abmm-builder-section-panel="${s}"></div>`).join('')}
 <input id="abmm-menu-title" value="Demo"><div id="abmm-nav-items"></div><ul class="abmm-readiness-issues"></ul><div class="abmm-readiness-summary"></div></aside>
 <main class="abmm-builder__main"><div class="abmm-preview-viewports">${['desktop','tablet','mobile'].map(s=>`<button data-abmm-preview-viewport="${s}">${s}</button>`).join('')}</div><div class="abmm-preview-shell" data-abmm-preview-viewport="desktop"><div id="abmm-preview"></div></div>${extraMain}</main></div>
 <script id="abmm-menu-data" type="application/json">${malformed ? '{invalid' : JSON.stringify(menuData)}</script></body>`, { url, runScripts:'outside-only' });
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
 if (draft) window.localStorage.setItem('abmmDraft:' + menuId, JSON.stringify(draft));
 window.eval(source);
 window.document.dispatchEvent(new window.Event('DOMContentLoaded'));
 await new Promise(resolve=>window.setTimeout(resolve,20));
 return {window,$,requests,close:()=>window.close()};
}
async function backupFixture(strings = {}) {
 const dom=new JSDOM('<body><button class="abmm-load-backups">Load</button><div id="abmm-backup-list"></div><div id="abmm-backup-status"></div></body>',{url:'http://localhost/',runScripts:'outside-only'});
 const {window}=dom; const $=require('jquery')(window); window.jQuery=$;
 window.abmmAdmin={ajaxUrl:'/ajax',nonce:'test',strings};
 const requests=[]; $.post=(_,data)=>{const d=$.Deferred();requests.push({data,d});return d.promise();};
 window.eval(source); window.document.dispatchEvent(new window.Event('DOMContentLoaded'));
 await new Promise(resolve=>window.setTimeout(resolve,20));
 return {window,$,requests,close:()=>window.close()};
}
(async()=>{
 const reorderMenu = {...menu, items:['a','b','c','d'].map(id=>({id,label:id.toUpperCase(),type:'link',url:'/'+id}))};
 let reorderFixture = await fixture(false,false,null,'demo',reorderMenu);
 try {
  const rq = reorderFixture.$;
  const row = id => rq('#abmm-nav-items > .abmm-nav-item-row').filter((_,el)=>rq(el).data('id')===id);
  const order = () => rq('#abmm-nav-items > .abmm-nav-item-row').map((_,el)=>rq(el).data('id')).get();
  const move = async(id,direction) => {
   row(id).find('.abmm-move-'+direction).trigger('focus').trigger('click');
   await new Promise(resolve=>reorderFixture.window.setTimeout(resolve,20));
  };
  await move('b','up');
  assert.deepEqual(order(),['b','a','c','d'],'moving up into first position preserves other items');
  assert.equal(reorderFixture.window.document.activeElement,row('b').find('.abmm-move-down')[0],'first-position move retains focus on the same item through its enabled control');
  assert.equal(row('b').find('.abmm-move-up').prop('disabled'),true);
  await move('b','down');
  assert.deepEqual(order(),['a','b','c','d'],'moving into an interior position restores expected order');
  assert.equal(reorderFixture.window.document.activeElement,row('b').find('.abmm-move-down')[0],'interior moves retain the requested direction control');
  await move('c','down');
  assert.deepEqual(order(),['a','b','d','c'],'moving down into last position preserves other items');
  assert.equal(reorderFixture.window.document.activeElement,row('c').find('.abmm-move-up')[0],'last-position move retains focus on the same item through its enabled control');
  assert.equal(row('c').find('.abmm-move-down').prop('disabled'),true);
  assert.deepEqual(rq('#abmm-preview .abmm-nav__link').map((_,el)=>rq(el).text()).get(),['A','B','D','C'],'preview matches the reordered menu');
  assert.equal(reorderFixture.requests.length,0,'keyboard reordering never implicitly saves or sends a mutation');
  assert.equal(rq('#abmm-menu-title').val(),menu.title,'reordering does not change other menu fields');
  // Clicking an item in the preview selects it in the inspector, with an outline.
  rq('#abmm-preview [data-abmm-preview-item="2"] > .abmm-nav__link').trigger('click');
  await new Promise(resolve=>reorderFixture.window.setTimeout(resolve,20));
  assert.equal(row('d').find('.abmm-nav-item-row__toggle').attr('aria-expanded'),'true','the clicked preview item opens in the inspector');
  assert.equal(row('a').find('.abmm-nav-item-row__toggle').attr('aria-expanded'),'false','other rows close');
  assert.equal(rq('#abmm-preview [data-abmm-preview-item="2"] > .abmm-nav__link').hasClass('abmm-canvas-selected'),true,'the selected preview element is outlined');
 } finally { reorderFixture.close(); }
 let rowsFixture = await fixture(false,false,null,'demo',reorderMenu);
 try {
  const rq = rowsFixture.$;
  const rows = () => rq('#abmm-nav-items > .abmm-nav-item-row');
  const toggle = i => rows().eq(i).find('.abmm-nav-item-row__toggle');
  assert.equal(toggle(0).attr('aria-expanded'),'true','the first item opens on load');
  assert.equal(rows().eq(0).find('.abmm-nav-item-row__fields').prop('hidden'),false);
  assert.equal(rows().eq(1).find('.abmm-nav-item-row__fields').prop('hidden'),true,'other items start collapsed');
  assert.equal(rows().eq(0).find('.abmm-nav-item-row__fields label').first().text(),'Label','expanded form labels its fields');
  toggle(1).trigger('click');
  assert.equal(toggle(1).attr('aria-expanded'),'true');
  assert.equal(toggle(0).attr('aria-expanded'),'false','one item is open at a time');
  rows().eq(1).find('.abmm-nav-label').val('Renamed').trigger('input');
  assert.equal(rows().eq(1).find('.abmm-nav-item-row__title').text(),'Renamed','collapsed title follows the label field');
  assert.equal(rows().eq(1).find('.abmm-remove-nav').attr('aria-label'),'Remove navigation item Renamed','row buttons are renamed with the label, for screen readers');
  assert.equal(rows().eq(1).find('.abmm-duplicate-nav').attr('aria-label'),'Duplicate navigation item Renamed');
  rows().eq(1).find('.abmm-duplicate-nav').trigger('click');
  assert.equal(toggle(2).attr('aria-expanded'),'true','a duplicated item opens straight away');
  assert.equal(toggle(1).attr('aria-expanded'),'false');
  // Link options are a popover: the toggle reports its state and Escape closes it and returns focus.
  const popover = rows().eq(0).find('.abmm-link-options');
  const popToggle = popover.find('.abmm-link-options__toggle');
  popToggle.trigger('click');
  assert.equal(popToggle.attr('aria-expanded'),'true');
  assert.equal(popover.find('.abmm-link-options__panel').prop('hidden'),false);
  popover.find('.abmm-link-target').prop('checked',true).trigger('change');
  assert.equal(popover.hasClass('has-options'),true,'a set option marks the toggle');
  popover.find('.abmm-link-rel').trigger(rq.Event('keydown',{key:'Escape'}));
  assert.equal(popToggle.attr('aria-expanded'),'false');
  assert.equal(popover.find('.abmm-link-options__panel').prop('hidden'),true);
  assert.equal(rowsFixture.window.document.activeElement,popToggle[0],'Escape returns focus to the popover button');
 } finally { rowsFixture.close(); }
 let f=await fixture(); let {$,requests}=f;
 assert.equal($('#abmm-preview .abmm-nav__link').text(),'Home','loaded transport renders actual preview');
 $('[data-abmm-preview-viewport="mobile"]').trigger('click');
 assert.equal($('.abmm-preview-shell').attr('data-abmm-preview-viewport'),'mobile');
 assert.equal($('[data-abmm-preview-viewport="mobile"]').attr('aria-pressed'),'true');
 assert.equal($('[data-abmm-preview-viewport="desktop"]').attr('aria-pressed'),'false');
 assert.equal($('#abmm-preview .abmm-nav__toggle').attr('aria-expanded'),'true','phone preview opens with the drawer showing');
 $('#abmm-preview .abmm-nav__toggle').trigger('click');
 assert.equal($('#abmm-preview .abmm-nav__toggle').attr('aria-expanded'),'false','the preview toggle still closes the drawer');
 $('#abmm-preview .abmm-nav__toggle').trigger('click');
 assert.equal($('#abmm-preview .abmm-nav__toggle').attr('aria-expanded'),'true');
 $('[data-abmm-preview-viewport="desktop"]').trigger('click');
 assert.equal($('#abmm-preview .abmm-nav__toggle').attr('aria-expanded'),'false','switching viewport closes drawer');
 $('[data-abmm-builder-section="content"]').trigger($.Event('keydown',{key:'End'}));
 // Place is a step in the stepper, not a fourth tab: End moves to the last Edit tab.
 assert.equal($('[data-abmm-builder-section="settings"]').attr('aria-selected'),'true');
 assert.equal($('[data-abmm-builder-section-panel="settings"]').prop('hidden'),false);
 assert.equal($('[data-abmm-builder-section-panel="content"]').prop('hidden'),true);
 $('[data-abmm-workflow="help"]').trigger('click');
 assert.equal($('[data-abmm-builder-section-panel="help"]').prop('hidden'),false,'the Place step drives the inspector');
 assert.equal($('.abmm-builder-sections').prop('hidden'),true,'Edit tabs step aside while placing');
 $('[data-abmm-workflow="content"]').trigger('click');
 assert.equal($('[data-abmm-builder-section-panel="settings"]').prop('hidden'),false,'Edit returns to the last Edit tab');
 assert.equal($('.abmm-builder-sections').prop('hidden'),false);
 assert.equal($('.abmm-save-menu').hasClass('is-saved'),true,'with nothing to save the button rests in its quiet Saved state');
 $('#abmm-menu-title').val('Changed').trigger('input');
 assert.equal($('.abmm-save-menu').hasClass('is-saved'),false,'a change turns Save back into the primary action');
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
 f=await backupFixture({backupLoading:'Chargement',backupExport:'<b>Exporter</b>',backupSummary:'%2$s menus — %1$s'}); $=f.$;
 $('.abmm-load-backups').trigger('click'); assert.equal($('#abmm-backup-status').text(),'Chargement');
 f.requests[0].d.resolve({success:true,data:{backups:[{id:'backup-1',created_at:'2026-10-10',menu_count:2}],revision:'collection-1'}});
 assert.equal($('.abmm-backup-row strong').text(),'2 menus — 2026-10-10');
 assert.equal($('.abmm-export-backup').text(),'<b>Exporter</b>');
 assert.equal($('.abmm-export-backup b').length,0,'translated labels are text, never HTML'); f.close();
 f=await fixture(false,false,{revision:'revision-old',menu:{...menu,title:'Recovered older draft'}}); $=f.$;
 assert.equal($('#abmm-draft-recovery').prop('hidden'),false,'stale local draft offers explicit recovery');
 const downloads=[];
 f.window.URL.createObjectURL=blob=>{downloads.push(blob);return 'blob:local-draft';};
 f.window.URL.revokeObjectURL=()=>{};
 f.window.document.addEventListener('click',event=>{if(event.target.tagName==='A')event.preventDefault();});
 $('.abmm-download-draft').trigger('click'); assert.equal(downloads.length,1,'stale draft remains downloadable before restoration');
 $('.abmm-restore-draft').trigger('click');
 assert.equal($('#abmm-menu-title').val(),'Recovered older draft');
 assert.equal($('.abmm-save-menu').prop('disabled'),true,'stale restoration cannot silently overwrite newer content');
 $('.abmm-save-menu').trigger('click'); assert.equal(f.requests.length,0);
 assert.equal($('#abmm-draft-recovery').prop('hidden'),false,'recovery actions remain visible while reviewing stale content');
 $('.abmm-discard-draft').trigger('click');
 assert.equal($('#abmm-menu-title').val(),'Demo','discard returns to saved content');
 assert.equal($('.abmm-save-menu').prop('disabled'),false);
 $('#abmm-menu-title').val('New intentional change').trigger('input'); $('.abmm-save-menu').trigger('click');
 assert.equal(f.requests[0].data.base_revision,'revision-1','discard preserves original saved revision rather than blessing stale content');
 f.close();
 f=await fixture(); $=f.$;
 $('[data-abmm-workflow="help"]').trigger('click');
 assert.equal($('[data-abmm-builder-section-panel="help"]').prop('hidden'),false,'Place step opens contextual placement');
 $('[data-abmm-workflow="verify"]').trigger('click');
 assert.equal(f.window.document.activeElement.id,'abmm-verification-panel','Verify step moves keyboard focus to real-page checklist');
 $('.abmm-copy-placement').trigger('click');
 assert.equal($('.abmm-copy-fallback').val(),'[ashbi_mega_menu id="demo"]','copy fallback selects exact saved-menu shortcode');
 assert.equal(f.window.document.activeElement.className,'abmm-copy-fallback');
 $('#abmm-menu-title').val('Unsaved').trigger('input');
 assert.equal($('.abmm-placement-save-hint').text(),'placementUnsaved','placement distinguishes unsaved preview from saved version');
 f.close();
 f=await fixture(false,false,null,'0'); $=f.$;
 const zeroDownloads=[];
 f.window.URL.createObjectURL=blob=>{zeroDownloads.push(blob);return 'blob:zero-menu';};
 f.window.URL.revokeObjectURL=()=>{};
 let zeroFilename='';
 f.window.document.addEventListener('click',event=>{if(event.target.tagName==='A'){zeroFilename=event.target.download;event.preventDefault();}});
 $('#abmm-menu-title').val('Zero ID unsaved changes').trigger('input');
 $('.abmm-save-menu').trigger('click');
 assert.equal(f.requests[0].data.menu_id,'0','numeric zero menu ID stays a string during save');
 f.requests[0].d.reject({responseJSON:{data:{message:'Connection lost'}}});
 assert.ok(f.window.localStorage.getItem('abmmDraft:0'),'zero ID preserves failed-save draft');
 assert.equal(JSON.parse(f.window.localStorage.getItem('abmmDraft:0')).menu.title,'Zero ID unsaved changes');
 $('.abmm-export-current-menu').trigger('click');
 assert.equal(zeroDownloads.length,1,'zero ID exports unsaved builder content locally');
 assert.equal(zeroFilename,'0.abmm.json');
 assert.equal(f.requests.length,1,'zero ID export never fetches an older saved server copy');
 f.close();
 f=await fixture(false,false,{revision:'revision-1',menu:{...menu,title:'Zero ID recovered draft'}},'0'); $=f.$;
 $('.abmm-restore-draft').trigger('click'); assert.equal($('#abmm-menu-title').val(),'Zero ID recovered draft','zero ID draft recovery reads the correct local key');
 $('.abmm-save-menu').trigger('click'); assert.equal(f.requests[0].data.menu_id,'0');
 f.requests[0].d.resolve({success:true,data:{revision:'revision-2'}});
 assert.equal(f.window.localStorage.getItem('abmmDraft:0'),null,'zero ID successful save clears recovery draft');
 f.close();
 // Featured card: row summaries count content, the card renders as the panel's
 // last child, and items without a card never gain a `featured` key.
 const featuredEditor = `<div id="abmm-mega-editor" hidden><h2 id="abmm-mega-editor-title"></h2><button class="abmm-close-mega-editor">Back</button><ul id="abmm-features-link-list"></ul>
  <section id="abmm-featured-editor"><input type="checkbox" id="abmm-featured-show"><div id="abmm-featured-fields" hidden>
  <input id="abmm-featured-title" class="abmm-featured-input" data-featured-key="title"><textarea id="abmm-featured-text" class="abmm-featured-input" data-featured-key="text"></textarea>
  <input id="abmm-featured-label" class="abmm-featured-input" data-featured-key="label"><input id="abmm-featured-url" class="abmm-featured-input" data-featured-key="url">
  <div id="abmm-featured-media"><span class="abmm-featured-media__thumb"></span><button class="abmm-featured-choose-image">Choose</button><button class="abmm-featured-remove-image" hidden>Remove</button></div>
  <input id="abmm-featured-image" class="abmm-featured-input" data-featured-key="image_url"></div></section></div>`;
 const megaMenu = {...menu, items:[
  {id:'shop',label:'Shop',type:'mega',mega_style:'platforms',columns:3,categories:[{id:'c1',title:'Cat',groups:[{id:'g1',title:'Col',links:[{id:'l1',label:'One',url:'/one'},{id:'l2',label:'Two',url:'https://elsewhere.example/two'}]}]}]},
  {id:'feat',label:'Feat',type:'mega',mega_style:'features',links:[{id:'f1',label:'A',url:'/a'}],featured:{show:true,title:'New',text:'Short',label:'Learn more',url:'/new',image_url:'/img.jpg'}},
 ]};
 f=await fixture(false,false,null,'demo',megaMenu,featuredEditor); $=f.$;
 assert.equal($('#abmm-nav-items .abmm-mega-style-badge').eq(0).text(),'%1$s · %2$s'.replace('%1$s','1 category').replace('%2$s','2 links'),'a mega row summarises its categories and links');
 assert.equal($('#abmm-nav-items .abmm-mega-style-badge').eq(1).text(),'1 link');
 const card = $('#abmm-preview [data-abmm-preview-item="1"] .abmm-mega__inner').children().last();
 assert.ok(card.is('a.abmm-mega__featured'),'the featured card is the last child of the panel content');
 assert.equal(card.attr('href'),'/new');
 assert.deepEqual(card.children().map((_,el)=>el.className).get(),['abmm-mega__featured-media','abmm-mega__featured-body'],'the card uses the front end markup');
 assert.deepEqual(card.find('.abmm-mega__featured-body').children().map((_,el)=>el.className).get(),['abmm-mega__featured-title','abmm-mega__featured-text','abmm-mega__featured-link']);
 assert.equal(card.find('img').attr('src'),'/img.jpg');
 assert.equal($('#abmm-preview [data-abmm-preview-item="0"] .abmm-mega__featured').length,0,'no card without a featured key');
 $('#abmm-nav-items .abmm-nav-item-row').eq(0).find('.abmm-edit-mega').trigger('click');
 assert.equal($('#abmm-featured-show').prop('checked'),false);
 $('#abmm-featured-show').prop('checked',true).trigger('change');
 assert.equal($('#abmm-featured-fields').prop('hidden'),false,'the switch reveals the card fields');
 $('#abmm-featured-show').prop('checked',false).trigger('change');
 $('.abmm-save-menu').trigger('click');
 let saved = JSON.parse(f.requests[0].data.menu_data);
 assert.equal('featured' in saved.items[0],false,'switching an empty card on and off leaves no featured key');
 assert.deepEqual(saved.items[1].featured,megaMenu.items[1].featured,'an existing card saves unchanged');
 f.requests[0].d.resolve({success:true,data:{revision:'revision-2'}});
 $('#abmm-featured-show').prop('checked',true).trigger('change');
 $('#abmm-featured-title').val('Launch').trigger('input');
 assert.equal($('#abmm-preview [data-abmm-preview-item="0"] .abmm-mega__panel').first().children().last().find('.abmm-mega__featured-title').text(),'Launch','the preview follows the card fields');
 $('.abmm-close-mega-editor').trigger('click');
 $('.abmm-save-menu').trigger('click');
 saved = JSON.parse(f.requests[1].data.menu_data);
 assert.deepEqual(saved.items[0].featured,{show:true,title:'Launch',text:'',label:'',url:'',image_url:''});
 f.requests[1].d.resolve({success:true,data:{revision:'revision-3'}});
 // A checklist item about one dropdown opens that dropdown's editor at the field to fix.
 $('#abmm-nav-items .abmm-nav-item-row').eq(1).find('.abmm-edit-mega').trigger('click');
 $('#abmm-featured-url').val('').trigger('input');
 $('.abmm-close-mega-editor').trigger('click');
 const featuredIssue = $('.abmm-readiness-issues button').filter((_, b) => $(b).text().includes('featured card'));
 assert.equal(featuredIssue.attr('data-item'),'1','the issue knows which dropdown it is about');
 featuredIssue.trigger('click');
 assert.equal($('#abmm-mega-editor').prop('hidden'),false,'clicking it opens that dropdown');
 assert.equal($('#abmm-mega-editor-title').text(),'Feat');
 await new Promise((resolve) => f.window.setTimeout(resolve, 300));
 assert.equal(f.window.document.activeElement.id,'abmm-featured-url','and moves to the featured link field');
 $('#abmm-featured-url').val('/new').trigger('input');
 $('.abmm-close-mega-editor').trigger('click');
 // Mega link rows: the URL is a quiet chip, and one button opens link editing and options.
 $('#abmm-nav-items .abmm-nav-item-row').eq(1).find('.abmm-edit-mega').trigger('click');
 const linkEdit = $('#abmm-features-link-list .abmm-link-edit').first();
 const chip = linkEdit.find('.abmm-link-edit__toggle');
 assert.equal(linkEdit.find('.abmm-link-edit__url').text(),'/a');
 chip.trigger('click');
 assert.equal(chip.attr('aria-expanded'),'true');
 linkEdit.find('.abmm-link-url').val('').trigger('input');
 assert.equal(linkEdit.find('.abmm-link-edit__url').hasClass('is-empty'),true,'an empty URL invites adding a link');
 linkEdit.find('.abmm-link-url').val('/b').trigger('input');
 assert.equal(linkEdit.find('.abmm-link-edit__url').text(),'/b');
 linkEdit.find('.abmm-link-target').prop('checked',true).trigger('change');
 assert.equal(linkEdit.hasClass('has-options'),true);
 linkEdit.find('.abmm-link-rel').trigger($.Event('keydown',{key:'Escape'}));
 assert.equal(chip.attr('aria-expanded'),'false');
 assert.equal(f.window.document.activeElement,chip[0],'Escape returns focus to the chip');
 // Simple-column links take a description, shown in the preview; their buttons follow the label.
 const featRow = $('#abmm-features-link-list .abmm-link-row').first();
 featRow.find('.abmm-link-description').val('All the news').trigger('input');
 featRow.find('.abmm-link-label').val('Blog').trigger('input');
 assert.equal(featRow.find('.abmm-remove-link').attr('aria-label'),'Remove link Blog','link buttons follow the label');
 assert.equal($('#abmm-preview [data-abmm-preview-item="1"] .abmm-mega__grid-description').text(),'All the news','simple-column descriptions show in the preview');
 $('.abmm-save-menu').trigger('click');
 saved = JSON.parse(f.requests[2].data.menu_data);
 assert.equal(saved.items[1].links[0].description,'All the news','the description is saved');
 assert.equal(saved.items[1].links[0].url,'/b');
 assert.equal(saved.items[1].links[0].target,'_blank');
 f.close();
 // Ctrl/Cmd + S saves the menu, also while typing in a field, and the browser's own save is blocked.
 f=await fixture(); $=f.$;
 $('#abmm-menu-title').val('Shortcut').trigger('input');
 const saveKey=new f.window.KeyboardEvent('keydown',{key:'s',ctrlKey:true,bubbles:true,cancelable:true});
 $('#abmm-menu-title')[0].dispatchEvent(saveKey);
 assert.equal(saveKey.defaultPrevented,true,'Ctrl+S does not open the browser save dialog');
 assert.equal(f.requests.length,1,'Ctrl+S saves the menu');
 assert.equal(JSON.parse(f.requests[0].data.menu_data).title,'Shortcut');
 const metaKey=new f.window.KeyboardEvent('keydown',{key:'S',metaKey:true,bubbles:true,cancelable:true});
 f.window.document.body.dispatchEvent(metaKey);
 assert.equal(f.requests.length,1,'a second shortcut while saving does not send a duplicate save');
 assert.match($('.abmm-save-menu').attr('aria-keyshortcuts'),/Control\+S/);
 f.close();
 // Dashboard deep links: "Place & verify" opens the Place step, and the card's
 // "N issues" pill opens the checklist on the Content tab.
 f=await fixture(false,false,null,'demo',menu,'',"http://localhost/#placement"); $=f.$;
 assert.equal($('[data-abmm-builder-section-panel="help"]').prop('hidden'),false,'#placement shows the Place panel');
 assert.equal($('[data-abmm-builder-section-panel="content"]').prop('hidden'),true);
 assert.equal($('[data-abmm-workflow="help"]').attr('aria-current'),'step','#placement marks the Place step');
 f.close();
 f=await fixture(false,false,null,'demo',menu,'<section id="abmm-readiness-panel"><h2>Checklist</h2></section>',"http://localhost/#abmm-readiness-panel"); $=f.$;
 assert.equal($('[data-abmm-builder-section-panel="content"]').prop('hidden'),false,'the checklist link opens the Content tab');
 assert.equal($('[data-abmm-workflow="content"]').attr('aria-current'),'step');
 assert.equal(f.window.document.activeElement,$('#abmm-readiness-panel')[0],'focus lands on the checklist');
 f.close();
 console.log('Admin viewport, drawer, keyboard tabs, sheet, save/retry, malformed load and explicit backup restore tests passed.');
 // Dashboard deep links: "Place & verify" opens the Place step, and the card's
 // "N issues" pill opens the checklist on the Content tab.
 f=await fixture(false,false,null,'demo',menu,'',"http://localhost/#placement"); $=f.$;
 assert.equal($('[data-abmm-builder-section-panel="help"]').prop('hidden'),false,'#placement shows the Place panel');
 assert.equal($('[data-abmm-builder-section-panel="content"]').prop('hidden'),true);
 assert.equal($('[data-abmm-workflow="help"]').attr('aria-current'),'step','#placement marks the Place step');
 f.close();
 f=await fixture(false,false,null,'demo',menu,'<section id="abmm-readiness-panel"><h2>Checklist</h2></section>',"http://localhost/#abmm-readiness-panel"); $=f.$;
 assert.equal($('[data-abmm-builder-section-panel="content"]').prop('hidden'),false,'the checklist link opens the Content tab');
 assert.equal($('[data-abmm-workflow="content"]').attr('aria-current'),'step');
 assert.equal(f.window.document.activeElement,$('#abmm-readiness-panel')[0],'focus lands on the checklist');
 f.close();
 await auditRegressions();
})().catch(error=>{console.error(error);process.exitCode=1;});

// Findings from the admin UX audit.
async function auditRegressions(){
 const wait=ms=>new Promise(resolve=>setTimeout(resolve,ms));
 const issueText=$=>$('.abmm-readiness-issues li').map((_,li)=>$(li).text()).get();
 const payload=request=>JSON.parse(request.data.menu_data);

 // Every settings control marks the menu dirty: static, so a new control cannot be forgotten.
 const builderView=fs.readFileSync('admin/views/builder.php','utf8');
 const panels=builderView.slice(builderView.indexOf('id="abmm-section-panel-settings"'),builderView.indexOf('id="abmm-section-panel-help"'));
 const wired=source.slice(source.indexOf('var designSelectors = ['),source.indexOf("].join(', ');",source.indexOf('var designSelectors = [')));
 for(const [tag] of panels.matchAll(/<(?:input|select)\b[^>]*\bid="abmm-[^"]+"[^>]*>/g)){
  const id=/\bid="([^"]+)"/.exec(tag)[1];
  if(/class="[^"]*abmm-color|type="hidden"|abmm-url-field|abmm-link-(target|rel|class)/.test(tag)) continue;
  assert.ok(wired.includes("'#"+id+"'") || /abmm-(cta|brand)-/.test(id) && source.includes("'#"+id+"'"),id+' is missing from designSelectors, so changing it would not mark the menu dirty');
 }
 const settings=['<label><input type="checkbox" id="abmm-mobile-enhancements"></label>','<input type="checkbox" id="abmm-header-transparent">','<input type="number" id="abmm-header-z-index" value="1000">'];
 for(const [selector,edit] of [['#abmm-mobile-enhancements',$el=>$el.prop('checked',true)],['#abmm-header-transparent',$el=>$el.prop('checked',true)],['#abmm-header-z-index',$el=>$el.val('50')]]){
  const f=await fixture(false,false,null,'demo',menu,settings.join(''));
  assert.match(f.$('.abmm-save-status').text(),/savedState/);
  edit(f.$(selector)); f.$(selector).trigger('change');
  assert.match(f.$('.abmm-save-status').text(),/unsavedState/,selector+' marks the menu dirty');
  f.close();
 }

 // A placed menu says that saving updates the site; others keep the plain label.
 let f=await fixture(); f.$('#abmm-builder').attr('data-menu-placed','1'); f.$('#abmm-menu-title').val('Changed').trigger('input');
 assert.equal(f.$('.abmm-save-menu__label').text(),'Save and update site'); f.close();
 f=await fixture(); f.$('#abmm-menu-title').val('Changed').trigger('input');
 assert.equal(f.$('.abmm-save-menu__label').text(),'Save changes'); f.close();

 // The checklist flags links that go nowhere, in the layout visitors see only.
 const deadMenu={...menu,items:[
  {id:'real',label:'Real',type:'link',url:'/real'},
  {id:'dead',label:'Dead',type:'link',url:'#'},
  {id:'mega',label:'Mega',type:'mega',mega_style:'features',links:[{id:'f1',label:'Shown',url:'/shown'},{id:'f2',label:'Empty feature',url:''}],categories:[{id:'c',title:'C',groups:[{id:'g',title:'G',links:[{id:'h1',label:'Hidden dead',url:'#'},{id:'h2',label:'Hidden broken',url:'about'}]}]}]},
  {id:'only',label:'Only hidden',type:'mega',mega_style:'features',links:[],categories:[{id:'c2',title:'C2',groups:[{id:'g2',title:'G2',links:[{id:'h3',label:'Real but hidden',url:'/hidden'}]}]}]},
 ]};
 f=await fixture(false,false,null,'demo',deadMenu); let $=f.$;
 let issues=issueText($);
 assert.ok(issues.some(text=>text.includes('"Dead"')),'a top link pointing at # is flagged: '+JSON.stringify(issues));
 assert.ok(issues.some(text=>text.includes('"Empty feature"')),'an empty link in the active layout is flagged');
 assert.ok(!issues.some(text=>/Hidden (dead|broken)/.test(text)),'links in the hidden layout are not flagged');
 assert.ok(issues.includes('checkMega'),'links hidden by the layout do not satisfy a mega item');
 assert.equal($('[data-abmm-builder-section="content"] .abmm-issue-count').text(),String(issues.length),'the Content tab shows the issue count');
 assert.equal($('[data-abmm-workflow="content"] .abmm-issue-count').text(),String(issues.length),'the Edit step shows the issue count');
 // A link issue opens that row.
 $('.abmm-readiness-issues button').filter((_,b)=>$(b).text().includes('"Dead"')).trigger('click');
 assert.equal(f.window.document.activeElement,$('#abmm-nav-items .abmm-nav-item-row').eq(1).find('.abmm-nav-url')[0],'a top link issue focuses that row\'s URL');
 assert.ok($('#abmm-nav-items .abmm-nav-item-row').eq(1).hasClass('is-expanded'),'the row opens');
 // An issue whose field is on another tab switches to it first.
 $('[data-abmm-builder-section-panel="settings"]').append('<input id="abmm-brand-title">');
 $('.abmm-readiness-issues').append('<li><button type="button" data-field="abmm-brand-title">Logo</button></li>');
 $('.abmm-readiness-issues button[data-field="abmm-brand-title"]').trigger('click');
 assert.equal($('[data-abmm-builder-section-panel="settings"]').prop('hidden'),false,'a logo issue opens the Settings tab');
 assert.equal(f.window.document.activeElement,$('#abmm-brand-title')[0],'and focuses the field');
 f.close();

 // Removing a row keeps keyboard focus nearby, and Undo does not expire on a timer.
 assert.ok(!/statusTimer = setTimeout\(renderSaveState, 6000\)/.test(source),'Undo has no time limit');
 const rows={...menu,items:['a','b','c'].map(id=>({id,label:id.toUpperCase(),type:'link',url:'/'+id}))};
 f=await fixture(false,false,null,'demo',rows); $=f.$;
 $('#abmm-nav-items .abmm-nav-item-row').eq(0).find('.abmm-remove-nav').trigger('click');
 assert.equal($('#abmm-nav-items .abmm-nav-item-row').length,2);
 assert.equal(f.window.document.activeElement.closest('.abmm-nav-item-row'),$('#abmm-nav-items .abmm-nav-item-row')[0],'focus moves to the next row');
 await wait(30);
 assert.equal($('.abmm-status-undo').length,1,'Undo is offered');
 $('.abmm-status-undo').trigger('click');
 assert.equal($('#abmm-nav-items .abmm-nav-item-row').length,3,'Undo restores the row');
 f.close();

 // Leaving with unsaved changes asks first, by every route.
 f=await fixture(false,false,null,'demo',menu,'<button class="abmm-save-and-leave">Save and leave</button><div id="abmm-exit-dialog" hidden></div>'); $=f.$;
 const leave=()=>{const e=new f.window.Event('beforeunload',{cancelable:true}); f.window.dispatchEvent(e); return e.defaultPrevented;};
 assert.equal(leave(),false,'a clean menu leaves freely');
 $('#abmm-menu-title').val('Changed').trigger('input');
 assert.equal(leave(),true,'an unsaved menu asks before unload');
 $('.abmm-save-and-leave').trigger('click');
 assert.equal(f.requests.filter(r=>r.data.action==='abmm_save_menu').length,1,'Save and leave really saves');
 f.close();

 // A failed draft write is known, so the exit dialog can warn instead of promising a draft.
 assert.ok(/state\.draftStoreFailed = true/.test(source));

 // After a save conflict, Discard continues from the latest saved version.
 f=await fixture(); $=f.$;
 $('#abmm-menu-title').val('Mine').trigger('input'); $('.abmm-save-menu').trigger('click');
 f.requests[f.requests.length-1].d.reject({responseJSON:{data:{conflict:true,message:'changed'}}});
 assert.equal($('.abmm-save-menu').prop('disabled'),true);
 $('.abmm-discard-draft').trigger('click');
 const reload=f.requests[f.requests.length-1];
 assert.equal(reload.data.action,'abmm_get_menu','discarding fetches the latest saved copy');
 reload.d.resolve({success:true,data:{menu:{...menu,title:'Server copy'},revision:'revision-2'}});
 assert.equal($('#abmm-menu-title').val(),'Server copy');
 assert.equal($('.abmm-save-menu').prop('disabled'),false,'saving works again');
 $('#abmm-menu-title').val('Next edit').trigger('input'); $('.abmm-save-menu').trigger('click');
 assert.equal(f.requests[f.requests.length-1].data.base_revision,'revision-2','the next save cannot conflict on the old revision');
 f.close();

 // The draft panel says how old the draft is.
 f=await fixture(false,false,{revision:'revision-1',menu:{...menu,title:'Draft'},updated:Date.now()},'demo',menu,'<p class="abmm-draft-recovery__age"></p>');
 assert.match(f.$('.abmm-draft-recovery__age').text(),/Unsaved draft from/); f.close();

 // A preset can no longer lose custom colors for good.
 const presetGrid='<input type="hidden" id="abmm-preset" value="custom"><input id="abmm-accent" class="abmm-color" value="#111111"><div id="abmm-preset-grid"><button type="button" class="abmm-preset-card" data-preset="bold" data-values=\'{"accent":"#222222"}\'>Bold</button><button type="button" class="abmm-preset-card abmm-preset-card--custom" data-preset="custom" disabled>Custom</button></div>';
 f=await fixture(false,false,null,'demo',{...menu,settings:{preset:'custom',accent:'#111111'}},presetGrid); $=f.$;
 await wait(5);
 assert.equal($('.abmm-preset-card--custom').prop('disabled'),false,'a custom menu has an active Custom card');
 $('.abmm-preset-card[data-preset="bold"]').trigger('click');
 assert.equal($('#abmm-accent').val(),'#222222');
 assert.equal($('.abmm-status-undo').length,1,'replacing custom colors offers Undo');
 assert.equal($('.abmm-preset-card--custom').prop('disabled'),false,'the Custom card stays available');
 await wait(5);
 $('.abmm-preset-card--custom').trigger('click');
 assert.equal($('#abmm-accent').val(),'#111111','the Custom card brings the colors back');
 assert.equal($('#abmm-preset').val(),'custom');
 f.close();

 // Low contrast and an unnamed logo get a hint, without blocking anything.
 const hints='<div id="abmm-contrast-warning" hidden><ul></ul></div><p id="abmm-logo-alt-warning" hidden></p><input id="abmm-cta-text" class="abmm-color" value="#999999"><input id="abmm-accent" class="abmm-color" value="#aaaaaa"><input type="checkbox" id="abmm-brand-show" checked><input id="abmm-brand-logo-url" value="https://example.test/logo.png"><input id="abmm-brand-title"><input id="abmm-brand-alt">';
 f=await fixture(false,false,null,'demo',{...menu,settings:{cta_text:'#999999',accent:'#aaaaaa'},brand:{show:true,logo_url:'https://example.test/logo.png',title:'',alt:''}},hints); $=f.$;
 assert.equal($('#abmm-contrast-warning').prop('hidden'),false,'low contrast warns');
 assert.match($('#abmm-contrast-warning li').text(),/contrast 1\.\d:1/);
 assert.equal($('#abmm-logo-alt-warning').prop('hidden'),false,'a logo with no alt or title warns');
 $('#abmm-brand-alt').val('Acme').trigger('change');
 assert.equal($('#abmm-logo-alt-warning').prop('hidden'),true,'adding alt text clears it');
 $('#abmm-cta-text').val('#ffffff').trigger('change'); $('#abmm-accent').val('#000000').trigger('change');
 f.close();

 // Switching a Classic menu to Ashbi keeps what switching back needs.
 const classic='<select id="abmm-render-mode"><option value="emm-classic">Classic</option><option value="">Ashbi</option></select><input type="checkbox" id="abmm-classic-phone-scroll-fix" checked><input id="abmm-classic-toggle-label" value="Products">';
 f=await fixture(false,false,null,'demo',{...menu,settings:{render_mode:'emm-classic',classic_edge_bleed:8,classic_phone_scroll_fix:true,classic_toggle_label:'Products'}},classic); $=f.$;
 assert.equal($('#abmm-classic-toggle-label').val(),'Products');
 $('#abmm-render-mode').val('').trigger('change'); $('.abmm-save-menu').trigger('click');
 let saved=payload(f.requests[f.requests.length-1]).settings;
 assert.equal(saved.render_mode,undefined,'the menu uses the Ashbi look');
 assert.equal(saved.classic_origin,true,'its origin is recorded');
 assert.equal(saved.classic_edge_bleed,8); assert.equal(saved.classic_phone_scroll_fix,true); assert.equal(saved.classic_toggle_label,'Products');
 f.close();
 f=await fixture(false,false,null,'demo',{...menu,settings:{classic_origin:true,classic_edge_bleed:8}},classic.replace('<option value="">','<option value="" selected>')); $=f.$;
 assert.equal($('#abmm-render-mode').val(),'','a switched menu loads as Ashbi');
 $('#abmm-render-mode').val('emm-classic').trigger('change'); $('.abmm-save-menu').trigger('click');
 saved=payload(f.requests[f.requests.length-1]).settings;
 assert.equal(saved.render_mode,'emm-classic','and can switch back'); assert.equal(saved.classic_origin,undefined); assert.equal(saved.classic_edge_bleed,8);
 f.close();
 // An ordinary menu gains no Classic settings.
 f=await fixture(); $=f.$; $('#abmm-menu-title').val('Changed').trigger('input'); $('.abmm-save-menu').trigger('click');
 assert.ok(!Object.keys(payload(f.requests[f.requests.length-1]).settings).some(key=>/classic|render_mode/.test(key)),'other menus keep exactly their data');
 f.close();

 // Switching layout says the other layout's links are kept.
 f=await fixture(false,false,null,'demo',deadMenu,'<button class="abmm-mega-style-card" data-style="platforms">C</button>'); $=f.$;
 $('[data-abmm-preview-item="2"] > .abmm-nav__link').trigger('click');
 await wait(5);
 assert.equal(f.window.document.activeElement,$('[data-abmm-preview-item="2"] > .abmm-nav__link')[0],'a re-rendered preview gives focus back to the same item');
 f.close();

 // List page.
 const list=async body=>{
  const dom=new JSDOM('<body>'+body+'</body>',{url:'http://localhost/',runScripts:'outside-only',pretendToBeVisual:true});
  const {window}=dom; const $=require('jquery')(window); window.jQuery=$;
  window.abmmAdmin={ajaxUrl:'/ajax',nonce:'test',strings:new Proxy({archivePlacements:'%1$d %2$d %3$d %4$d',archiveConfirmTitle:'%1$s'},{get:(t,k)=>t[k]||String(k)})};
  const requests=[]; $.post=(_,data)=>{const d=$.Deferred();requests.push({data,d});return d.promise();};
  window.eval(source); window.document.dispatchEvent(new window.Event('DOMContentLoaded'));
  await wait(20);
  return {window,$,requests,close:()=>window.close()};
 };
 const card='<div id="abmm-list-status"></div><div class="abmm-menu-card" data-menu-id="x"><button class="abmm-delete-menu" data-menu-id="x">Archive</button><button class="abmm-menu-versions" data-menu-id="x">Versions</button></div>';
 for(const known of [2,0]){
  f=await list(card); $=f.$;
  $('.abmm-delete-menu').trigger('click');
  f.requests[0].d.resolve({success:true,data:{blocks:0,widgets:0,shortcodes:0,other:known,known,warning:'w'}});
  assert.equal($('.abmm-archive-confirm').hasClass('is-danger'),known>0,'a placed menu gets a danger confirm');
  assert.equal($('.abmm-confirm-archive').hasClass('abmm-button-danger'),known>0);
  assert.equal($('.abmm-confirm-archive').hasClass('button-primary'),known===0);
  assert.equal(/disappear/.test($('.abmm-archive-confirm').text()),known>0,'the consequence is named');
  assert.match($('.abmm-archive-confirm').text(),new RegExp('0 0 0 '+known),'page builder placements count');
  f.close();
 }
 // Saved versions list and restore.
 f=await list(card); $=f.$;
 $('.abmm-menu-versions').trigger('click');
 assert.equal(f.requests[0].data.action,'abmm_menu_versions');
 f.requests[0].d.resolve({success:true,data:{revision:'rev-now',versions:[{id:'v1',saved_at:1,saved_label:'Today',items:3}]}});
 assert.match($('.abmm-version-list').text(),/Today, 3 menu items/);
 $('.abmm-restore-version').trigger('click');
 assert.equal(f.requests[1].data.action,'abmm_restore_menu_version');
 assert.equal(f.requests[1].data.version_id,'v1'); assert.equal(f.requests[1].data.base_revision,'rev-now');
 f.close();
 // A double-click cannot permanently delete, and the armed state expires.
 f=await list('<div id="abmm-list-status"></div><div class="abmm-archive-row"><button class="abmm-permanent-delete" data-menu-id="x">Permanently delete</button></div>'); $=f.$;
 const button=$('.abmm-permanent-delete')[0];
 button.dispatchEvent(new f.window.MouseEvent('click',{bubbles:true,detail:1}));
 button.dispatchEvent(new f.window.MouseEvent('click',{bubbles:true,detail:2}));
 assert.equal(f.requests.length,0,'the second click of a double-click is ignored');
 button.dispatchEvent(new f.window.MouseEvent('click',{bubbles:true,detail:1}));
 assert.equal(f.requests[0].data.action,'abmm_permanently_delete_menu','a separate second click confirms');
 f.close();
 // Opening the recovery panel loads its list, once.
 f=await list('<details id="abmm-backup-panel" open><button class="abmm-load-backups">Refresh</button><div id="abmm-backup-list"></div><div id="abmm-backup-status"></div></details>'); $=f.$;
 $('#abmm-backup-panel').trigger('toggle');
 assert.equal(f.requests[0].data.action,'abmm_list_backups');
 f.close();
 // Import errors stay until the next attempt.
 assert.ok(/if \(msg && !isError\) \{\s+state\.importStatusTimer/.test(source),'import errors do not time out');
}
