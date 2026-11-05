// Executes the shipped admin script with real jQuery and a disposable DOM.
// WordPress widgets/AJAX are adapters; no browser, network or production state.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { JSDOM } = require('jsdom');
const source = fs.readFileSync('assets/js/admin.js', 'utf8');
const menu = { title: 'Demo', items: [{ id: 'home', label: 'Home', type: 'link', url: '/' }], cta: {}, settings: { presentation:'unified' } };
const sections = ['content', 'design', 'help', 'verify'];
async function fixture(narrow = false, malformed = false, draft = null, menuId = 'demo', menuData = menu, extraMain = '', url = 'http://localhost/') {
 const dom = new JSDOM(`<body><div class="abmm-builder-topbar"><button class="abmm-save-menu">Save</button><button class="abmm-export-current-menu" data-menu-id="${menuId}">Export</button><span class="abmm-save-status"></span></div>
 <div id="abmm-draft-recovery" hidden><p class="abmm-draft-recovery__message"></p><button class="abmm-restore-draft">Restore</button><button class="abmm-download-draft">Download</button><button class="abmm-reload-server">Reload</button><button class="abmm-discard-draft">Discard</button></div>
 <button data-abmm-workflow="content">Content</button><button data-abmm-workflow="design">Design</button><button data-abmm-workflow="help">Place</button><button data-abmm-workflow="verify">Check</button>
 <section class="abmm-placement-panel"><p class="abmm-placement-save-hint"></p><button class="abmm-copy-placement" data-shortcode='[ashbi_mega_menu id="demo"]'>Copy</button></section><div id="abmm-verification-panel" tabindex="-1"></div>
 <button class="abmm-builder-panel-toggle">Settings</button><div id="abmm-builder-panel-backdrop" hidden></div>
 <div id="abmm-builder" data-menu-id="${menuId}" data-menu-revision="revision-1"><aside id="abmm-builder-sidebar"><button class="abmm-builder-panel-close">Close</button>
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
  assert.equal(row('b').find('.abmm-row-menu__panel').prop('hidden'),false,'a move keeps the row menu open, so focus stays on a usable control');
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
 // The stepper is the only navigation: arrow keys move along it and each step opens its panel.
 $('[data-abmm-workflow="content"]').trigger($.Event('keydown',{key:'End'}));
 assert.equal($('[data-abmm-workflow="verify"]').attr('aria-current'),'step');
 assert.equal($('[data-abmm-builder-section-panel="verify"]').prop('hidden'),false);
 assert.equal($('[data-abmm-builder-section-panel="content"]').prop('hidden'),true);
 assert.equal($('[data-abmm-workflow="verify"]')[0].ownerDocument.activeElement,$('[data-abmm-workflow="verify"]')[0],'focus follows the arrow keys');
 $('[data-abmm-workflow="verify"]').trigger($.Event('keydown',{key:'ArrowRight'}));
 assert.equal($('[data-abmm-workflow="content"]').attr('aria-current'),'step','the arrow keys wrap around');
 $('[data-abmm-workflow="content"]').trigger($.Event('keydown',{key:'ArrowRight'}));
 assert.equal($('[data-abmm-builder-section-panel="design"]').prop('hidden'),false);
 $('[data-abmm-workflow="help"]').trigger('click');
 assert.equal($('[data-abmm-builder-section-panel="help"]').prop('hidden'),false,'the Place step drives the inspector');
 assert.equal($('[data-abmm-builder-section-panel="design"]').prop('hidden'),true,'one panel at a time');
 assert.equal($('[data-abmm-workflow="help"]').attr('aria-current'),'step');
 $('[data-abmm-workflow="design"]').trigger('click');
 assert.equal($('[data-abmm-builder-section-panel="design"]').prop('hidden'),false,'Design is a step of its own');
 $('[data-abmm-workflow="content"]').trigger('click');
 assert.equal($('[data-abmm-builder-section-panel="content"]').prop('hidden'),false,'Content is a step of its own');
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
 assert.equal($('.abmm-load-backups').prop('disabled'),false);
 assert.equal($('.abmm-load-backups').prop('hidden'),false,'a failed restore offers a fresh list');
 f.close();
 // The backup list loads by itself, so its button only shows to try again.
 f=await backupFixture(); $=f.$; requests=f.requests;
 $('.abmm-load-backups').prop('hidden',true).trigger('click');
 requests[0].d.reject({});
 assert.equal($('#abmm-backup-status').attr('role'),'alert');
 assert.equal($('.abmm-load-backups').prop('hidden'),false,'a failed load offers Try again');
 $('.abmm-load-backups').trigger('click');
 requests[1].d.resolve({success:false,data:{message:'Nope'}});
 assert.equal($('.abmm-load-backups').prop('hidden'),false,'so does a refused load');
 $('.abmm-load-backups').trigger('click');
 requests[2].d.resolve({success:true,data:{backups:[],revision:'r'}});
 assert.equal($('.abmm-load-backups').prop('hidden'),true,'a good load hides it again');
 f.close();
 f=await backupFixture({backupLoading:'Chargement',backupExport:'<b>Exporter</b>',backupSummary:'%2$s menus — %1$s'}); $=f.$;
 $('.abmm-load-backups').trigger('click'); assert.equal($('#abmm-backup-status').text(),'Chargement');
 f.requests[0].d.resolve({success:true,data:{backups:[{id:'backup-1',created_at:'2026-10-10',menu_count:2}],revision:'collection-1'}});
 assert.equal($('.abmm-backup-row strong').text(),'2 menus — '+new Date('2026-10-10').toLocaleString());
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
 assert.equal(f.window.document.activeElement.id,'abmm-verification-panel','Check step moves keyboard focus to real-page checklist');
 // The checklist comes first on Place, so Check always goes to the real-page list.
 $('#abmm-verification-panel').before('<section id="abmm-readiness-panel" tabindex="-1"></section>');
 $('[data-abmm-workflow="verify"]').trigger('click');
 assert.equal(f.window.document.activeElement.id,'abmm-verification-panel','Check goes to the real-page list even when a checklist exists');
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
 const featuredEditor = `<div id="abmm-mega-editor" hidden><h2 id="abmm-mega-editor-title"></h2><button class="abmm-close-mega-editor">Back</button><details id="abmm-featured-details"><summary>Featured card</summary><ul id="abmm-features-link-list"></ul>
  <section id="abmm-featured-editor"><input type="checkbox" id="abmm-featured-show"><div id="abmm-featured-fields" hidden>
  <input id="abmm-featured-title" class="abmm-featured-input" data-featured-key="title"><textarea id="abmm-featured-text" class="abmm-featured-input" data-featured-key="text"></textarea>
  <input id="abmm-featured-label" class="abmm-featured-input" data-featured-key="label"><input id="abmm-featured-url" class="abmm-featured-input" data-featured-key="url">
  <div id="abmm-featured-media"><span class="abmm-featured-media__thumb"></span><button class="abmm-featured-choose-image">Choose</button><button class="abmm-featured-remove-image" hidden>Remove</button></div>
  <input id="abmm-featured-image" class="abmm-featured-input" data-featured-key="image_url"></div></section></details></div>`;
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
 assert.equal($('#abmm-featured-details').prop('open'),false,'a dropdown with no card starts with its section closed');
 $('#abmm-featured-details').prop('open',true);
 $('#abmm-featured-show').prop('checked',true).trigger('change');
 assert.equal($('#abmm-featured-fields').prop('hidden'),false,'the switch reveals the card fields');
 $('#abmm-featured-show').prop('checked',false).trigger('change');
 assert.equal($('#abmm-featured-details').prop('open'),true,'turning the card off does not close the section under the user');
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
 assert.equal($('#abmm-featured-details').prop('open'),true,'a dropdown with a card opens its section, so nothing is hidden and active');
 $('#abmm-featured-details').prop('open',false);
 $('#abmm-featured-url').val('').trigger('input');
 $('.abmm-close-mega-editor').trigger('click');
 const featuredIssue = $('.abmm-readiness-issues button').filter((_, b) => $(b).text().includes('featured card'));
 assert.equal(featuredIssue.attr('data-item'),'1','the issue knows which dropdown it is about');
 featuredIssue.trigger('click');
 assert.equal($('#abmm-mega-editor').prop('hidden'),false,'clicking it opens that dropdown');
 assert.equal($('#abmm-mega-editor-title').text(),'Feat');
 await new Promise((resolve) => f.window.setTimeout(resolve, 300));
 assert.equal(f.window.document.activeElement.id,'abmm-featured-url','and moves to the featured link field');
 assert.equal($('#abmm-featured-details').prop('open'),true,'with its section open');
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
 // Dashboard deep links: "Place" opens the Place step, and the card's
 // "N issues" pill opens the checklist at the top of the Place step.
 f=await fixture(false,false,null,'demo',menu,'',"http://localhost/#placement"); $=f.$;
 assert.equal($('[data-abmm-builder-section-panel="help"]').prop('hidden'),false,'#placement shows the Place panel');
 assert.equal($('[data-abmm-builder-section-panel="content"]').prop('hidden'),true);
 assert.equal($('[data-abmm-workflow="help"]').attr('aria-current'),'step','#placement marks the Place step');
 f.close();
 f=await fixture(false,false,null,'demo',menu,'<section id="abmm-readiness-panel"><h2>Checklist</h2></section>',"http://localhost/#abmm-readiness-panel"); $=f.$;
 assert.equal($('[data-abmm-builder-section-panel="help"]').prop('hidden'),false,'the checklist link opens the Place step');
 assert.equal($('[data-abmm-builder-section-panel="content"]').prop('hidden'),true);
 assert.equal($('[data-abmm-workflow="help"]').attr('aria-current'),'step');
 assert.equal(f.window.document.activeElement,$('#abmm-readiness-panel')[0],'focus lands on the checklist');
 f.close();
 console.log('Admin viewport, drawer, keyboard steps, sheet, save/retry, malformed load and explicit backup restore tests passed.');
 // Dashboard deep links: "Place" opens the Place step, and the card's
 // "N issues" pill opens the checklist on the Check step.
 f=await fixture(false,false,null,'demo',menu,'',"http://localhost/#placement"); $=f.$;
 assert.equal($('[data-abmm-builder-section-panel="help"]').prop('hidden'),false,'#placement shows the Place panel');
 assert.equal($('[data-abmm-builder-section-panel="content"]').prop('hidden'),true);
 assert.equal($('[data-abmm-workflow="help"]').attr('aria-current'),'step','#placement marks the Place step');
 f.close();
 f=await fixture(false,false,null,'demo',menu,'<section id="abmm-readiness-panel"><h2>Checklist</h2></section>',"http://localhost/#abmm-readiness-panel"); $=f.$;
 assert.equal($('[data-abmm-builder-section-panel="help"]').prop('hidden'),false,'the checklist link opens the Place step');
 assert.equal($('[data-abmm-builder-section-panel="content"]').prop('hidden'),true);
 assert.equal($('[data-abmm-workflow="help"]').attr('aria-current'),'step');
 assert.equal(f.window.document.activeElement,$('#abmm-readiness-panel')[0],'focus lands on the checklist');
 f.close();
 // On a narrow screen a deep link opens the panel it points into.
 f=await fixture(true,false,null,'demo',menu,'',"http://localhost/#placement"); $=f.$;
 assert.equal($('#abmm-builder-sidebar').hasClass('is-open'),true,'#placement opens the panel on a narrow screen');
 assert.equal($('[data-abmm-builder-section-panel="help"]').prop('hidden'),false);
 f.close();
 await auditRegressions();
 await simplicityRegressions();
})().catch(error=>{console.error(error);process.exitCode=1;});

// Findings from the admin UX audit.
async function auditRegressions(){
 const wait=ms=>new Promise(resolve=>setTimeout(resolve,ms));
 const issueText=$=>$('.abmm-readiness-issues li').map((_,li)=>$(li).text()).get();
 const payload=request=>JSON.parse(request.data.menu_data);

 // Every settings control marks the menu dirty: static, so a new control cannot be forgotten.
 const builderView=fs.readFileSync('admin/views/builder.php','utf8');
 const panels=builderView.slice(builderView.indexOf('id="abmm-section-panel-content"'),builderView.indexOf('id="abmm-section-panel-help"'));
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
 assert.equal($('[data-abmm-workflow="help"] .abmm-issue-count').text(),String(issues.length),'the Place step shows the issue count');
 assert.equal($('[data-abmm-workflow="content"] .abmm-issue-count').length,0,'the count sits on one step only');
 // A link issue opens that row.
 $('.abmm-readiness-issues button').filter((_,b)=>$(b).text().includes('"Dead"')).trigger('click');
 assert.equal(f.window.document.activeElement,$('#abmm-nav-items .abmm-nav-item-row').eq(1).find('.abmm-nav-url')[0],'a top link issue focuses that row\'s URL');
 assert.ok($('#abmm-nav-items .abmm-nav-item-row').eq(1).hasClass('is-expanded'),'the row opens');
 // The checklist is on Place, so an issue switches to the step holding its field.
 $('[data-abmm-builder-section-panel="content"]').append('<input id="abmm-brand-title">');
 $('[data-abmm-workflow="help"]').trigger('click');
 $('.abmm-readiness-issues').append('<li><button type="button" data-field="abmm-brand-title">Logo</button></li>');
 $('.abmm-readiness-issues button[data-field="abmm-brand-title"]').trigger('click');
 assert.equal($('[data-abmm-builder-section-panel="content"]').prop('hidden'),false,'a logo issue opens the Content step');
 assert.equal($('[data-abmm-builder-section-panel="help"]').prop('hidden'),true);
 assert.equal(f.window.document.activeElement,$('#abmm-brand-title')[0],'and focuses the field');
 // A field in a closed disclosure opens first, or it could not take focus.
 $('[data-abmm-builder-section-panel="design"]').append('<details id="abmm-test-more"><summary>More</summary><input id="abmm-test-hidden"></details>');
 $('.abmm-readiness-issues').append('<li><button type="button" data-field="abmm-test-hidden">Hidden</button></li>');
 $('.abmm-readiness-issues button[data-field="abmm-test-hidden"]').trigger('click');
 assert.equal($('[data-abmm-builder-section-panel="design"]').prop('hidden'),false,'the issue opens the step holding its field');
 assert.equal($('#abmm-test-more').prop('open'),true,'and the disclosure around it');
 // An issue with no field of its own goes to Content, where menu items are edited.
 $('[data-abmm-workflow="verify"]').trigger('click');
 $('.abmm-readiness-issues').append('<li><button type="button" data-field="abmm-test-missing">Missing</button></li>');
 $('.abmm-readiness-issues button[data-field="abmm-test-missing"]').trigger('click');
 assert.equal($('[data-abmm-builder-section-panel="content"]').prop('hidden'),false,'it never strands the user on Check');
 f.close();

 // Removing a row keeps keyboard focus nearby, and Undo does not expire on a timer.
 assert.ok(!/statusTimer = setTimeout\(renderSaveState, 6000\)/.test(source),'Undo has no time limit');
 const rows={...menu,items:['a','b','c'].map(id=>({id,label:id.toUpperCase(),type:'link',url:'/'+id}))};
 f=await fixture(false,false,null,'demo',rows); $=f.$;
 $('#abmm-nav-items .abmm-nav-item-row').eq(0).find('.abmm-remove-nav').trigger('click');
 assert.equal($('#abmm-nav-items .abmm-nav-item-row').length,2);
 assert.equal(f.window.document.activeElement.closest('.abmm-nav-item-row'),$('#abmm-nav-items .abmm-nav-item-row')[0],'focus moves to the next row');
 assert.equal(f.window.document.activeElement,$('#abmm-nav-items .abmm-nav-item-row').eq(0).find('.abmm-row-menu__toggle')[0],'focus lands on a control that can be seen: the next row\'s menu button');
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
 assert.match(f.$('.abmm-draft-recovery__age').text(),/Unsaved changes from/); f.close();

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
 const classic='<select id="abmm-render-mode"><option value="emm-classic">Classic</option><option value="">Ashbi</option></select><input id="abmm-classic-toggle-label" value="Products">';
 f=await fixture(false,false,null,'demo',{...menu,settings:{render_mode:'emm-classic',classic_edge_bleed:8,classic_phone_scroll_fix:true,classic_toggle_label:'Products',classic_bar_padding:'8px 16px',classic_plain_markup:true}},classic); $=f.$;
 assert.equal($('#abmm-classic-toggle-label').val(),'Products');
 $('#abmm-render-mode').val('').trigger('change'); $('.abmm-save-menu').trigger('click');
 let saved=payload(f.requests[f.requests.length-1]).settings;
 assert.equal(saved.render_mode,undefined,'the menu uses the Ashbi look');
 assert.equal(saved.classic_origin,true,'its origin is recorded');
 assert.equal(saved.classic_edge_bleed,8); assert.equal(saved.classic_phone_scroll_fix,true); assert.equal(saved.classic_toggle_label,'Products'); assert.equal(saved.classic_bar_padding,'8px 16px'); assert.equal(saved.classic_plain_markup,true);
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

 // A new dropdown starts on Simple columns; stored types and existing layouts stay as they are.
 f=await fixture(false,false,null,'demo',{...menu,items:[
  {id:'plain',label:'Plain',type:'link',url:'/plain'},
  {id:'kept',label:'Kept',type:'link',url:'/kept',categories:[{id:'c9',title:'Kept',groups:[]}]},
  {id:'was',label:'Was',type:'link',url:'/was',mega_style:'platforms',columns:4,categories:[],links:[]},
 ]}); $=f.$;
 assert.deepEqual(Array.from($('#abmm-nav-items .abmm-nav-type')[0].options,o=>[o.value,o.textContent]),[['link','Link'],['mega','Dropdown']],'the labels change, the stored values do not');
 [0,1,2].forEach(i=>$('#abmm-nav-items .abmm-nav-type').eq(i).val('mega').trigger('change'));
 assert.equal($('#abmm-nav-items .abmm-nav-type-chip').first().text(),'Dropdown');
 $('.abmm-save-menu').trigger('click');
 saved=JSON.parse(f.requests[0].data.menu_data);
 assert.equal(saved.items[0].type,'mega');
 assert.equal(saved.items[0].mega_style,'features','a new dropdown starts on Simple columns');
 assert.equal(saved.items[0].columns,2);
 assert.equal(saved.items[1].mega_style,'platforms','an item with categories keeps them');
 assert.equal(saved.items[2].mega_style,'platforms','a layout the item already has is kept');
 assert.equal(saved.items[2].columns,4);
 f.close();

 // "More options" starts closed, and open when something in it is in use. Nothing hidden is lost.
 const moreEditor='<div id="abmm-mega-editor" hidden><h2 id="abmm-mega-editor-title"></h2><button class="abmm-close-mega-editor">Back</button><details id="abmm-mega-more-options" hidden><summary>More</summary><div id="abmm-category-bar-field" hidden><input type="checkbox" id="abmm-hide-category-bar"></div></details><ul id="abmm-features-link-list"></ul><ul id="abmm-cat-list"></ul><div id="abmm-cat-detail"></div></div>'
  +'<div id="abmm-icon-modal" hidden><input id="abmm-icon-search"><div id="abmm-icon-grid"></div><details id="abmm-icon-appearance" hidden><summary>More</summary><div id="abmm-icon-appearance-fields"></div></details></div>';
 const oneCat=id=>({id,title:id,groups:[{id:id+'g',title:'',links:[]}]});
 const moreMenu={...menu,items:[
  {id:'p',label:'P',type:'link',url:'/p'},
  {id:'r',label:'R',type:'link',url:'/r',rel:'nofollow',class:'hi',target:'_blank'},
  {id:'one',label:'One',type:'mega',mega_style:'platforms',columns:3,categories:[oneCat('a')]},
  {id:'hid',label:'Hid',type:'mega',mega_style:'platforms',columns:3,hide_category_bar:true,categories:[oneCat('b')]},
  {id:'two',label:'Two',type:'mega',mega_style:'platforms',columns:3,categories:[oneCat('c'),oneCat('d')]},
  {id:'col',label:'Col',type:'mega',mega_style:'features',columns:2,links:[{id:'f1',label:'A',url:'/a'},{id:'f2',label:'B',url:'/b',icon_color:'#111111'},{id:'f3',label:'C',url:'/c',rel:'ugc'},{id:'f4',label:'D',url:'/d',icon_radius:0}]},
  {id:'old',label:'Old',type:'mega',categories:[oneCat('e')]},
 ]};
 f=await fixture(false,false,null,'demo',moreMenu,moreEditor); $=f.$;
 const navRow=i=>$('#abmm-nav-items .abmm-nav-item-row').eq(i);
 assert.equal(navRow(0).find('.abmm-link-options .abmm-more-options').prop('open'),false,'closed when rel and classes are empty');
 assert.equal(navRow(1).find('.abmm-link-options .abmm-more-options').prop('open'),true,'open when they have a value');
 assert.equal(navRow(0).find('.abmm-link-target').closest('.abmm-more-options').length,0,'new tab stays one click away');
 navRow(0).find('.abmm-link-rel').val('sponsored').trigger('input');
 assert.equal(navRow(0).find('.abmm-link-options').hasClass('has-options'),true,'the options button shows a value is set');
 const megaMore=$('#abmm-mega-more-options');
 navRow(2).find('.abmm-edit-mega').trigger('click');
 assert.equal(megaMore.prop('hidden'),false,'the option shows with one category');
 assert.equal(megaMore.prop('open'),false);
 navRow(3).find('.abmm-edit-mega').trigger('click');
 assert.equal(megaMore.prop('open'),true,'a hidden category bar opens More options');
 assert.equal($('#abmm-hide-category-bar').prop('checked'),true);
 navRow(4).find('.abmm-edit-mega').trigger('click');
 assert.equal(megaMore.prop('hidden'),true,'with no option to show, More options is left out');
 navRow(2).find('.abmm-edit-mega').trigger('click');
 assert.equal(megaMore.prop('open'),false,'the next dropdown starts closed again');
 navRow(5).find('.abmm-edit-mega').trigger('click');
 assert.equal(megaMore.prop('hidden'),true,'Simple columns has no category bar');
 const featureRow=i=>$('#abmm-features-link-list .abmm-link-row').eq(i);
 assert.equal(featureRow(0).find('.abmm-link-edit .abmm-more-options').prop('open'),false);
 assert.equal(featureRow(2).find('.abmm-link-edit .abmm-more-options').prop('open'),true);
 const iconMore=$('#abmm-icon-appearance');
 const pickIcon=i=>featureRow(i).find('.abmm-pick-link-icon').trigger('click');
 pickIcon(0);
 assert.equal(iconMore.prop('hidden'),false);
 assert.equal(iconMore.prop('open'),false,'the icon style starts closed');
 pickIcon(1);
 assert.equal(iconMore.prop('open'),true,'a custom icon colour opens it');
 pickIcon(0);
 assert.equal(iconMore.prop('open'),false,'and the next item starts closed again');
 pickIcon(3);
 assert.equal(iconMore.prop('open'),true,'a radius of 0 counts as in use');
 navRow(6).find('.abmm-edit-mega').trigger('click');
 $('.abmm-save-menu').trigger('click');
 saved=JSON.parse(f.requests[0].data.menu_data);
 assert.equal(saved.items[0].rel,'sponsored','a closed field still saves');
 assert.equal(saved.items[1].rel,'nofollow');
 assert.equal(saved.items[1].class,'hi');
 assert.equal(saved.items[1].target,'_blank');
 assert.equal(saved.items[3].hide_category_bar,true);
 assert.equal(saved.items[5].links[1].icon_color,'#111111');
 assert.equal(saved.items[5].links[2].rel,'ugc');
 assert.equal(saved.items[6].mega_style,'platforms','an existing dropdown with no stored layout stays on categories');
 f.close();

 // History brings versions and backups together, reachable from each card and the editor.
 const listView=fs.readFileSync('admin/views/list.php','utf8');
 assert.match(builderView,/abmm-open-history" href="[^"]*'abmm_history'/,'the editor links to History');
 assert.match(listView,/abmm-menu-versions"[^\n]*'History'/,'the card menu says History');
 assert.match(listView,/id="abmm-backup-panel"[\s\S]*'History'[\s\S]*id="abmm-history-menu"[\s\S]*abmm-load-backups/,'one panel holds both');
 assert.doesNotMatch(listView,/'Recovery snapshots'|'Saved versions'/);
 assert.match(listView,/<details class="abmm-import-api abmm-more-options">/,'the developer panel starts closed');

 // List page.
 const list=async(body,url='http://localhost/')=>{
  const dom=new JSDOM('<body>'+body+'</body>',{url,runScripts:'outside-only',pretendToBeVisual:true});
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
  if(known) assert.match($('.abmm-archive-confirm').text(),new RegExp('0 0 0 '+known),'page builder placements count');
   else assert.ok(!/0 0 0 0/.test($('.abmm-archive-confirm').text()),'no list of zero counts when nothing is found');
  f.close();
 }
 // A card's History opens the shared panel on that menu, with its versions and restore.
 const historyPanel='<details id="abmm-backup-panel"><h3 id="abmm-history-title" tabindex="-1">One menu</h3><select id="abmm-history-menu"><option value="w">W</option><option value="x">X</option><option value="y">Y</option></select><div id="abmm-history-versions"></div></details>';
 f=await list(card+historyPanel); $=f.$;
 assert.equal(f.requests.length,0,'nothing loads until History opens');
 $('.abmm-menu-versions').trigger('click');
 assert.equal($('#abmm-backup-panel').prop('open'),true);
 assert.equal($('#abmm-history-menu').val(),'x','it opens on the card it came from');
 assert.equal(f.requests[0].data.action,'abmm_menu_versions');
 assert.equal(f.requests[0].data.menu_id,'x');
 await wait(5);
 assert.equal(f.window.document.activeElement.id,'abmm-history-title','focus moves to History');
 f.requests[0].d.resolve({success:true,data:{revision:'rev-now',versions:[{id:'v1',saved_at:1,saved_label:'Today',items:3}]}});
 assert.match($('.abmm-version-list').text(),/Today, 3 menu items/);
 $('.abmm-restore-version').trigger('click');
 assert.equal(f.requests.length,1,'the first click only asks');
 assert.match($('.abmm-restore-version').text(),/Click again to restore/);
 $('.abmm-restore-version').trigger('click');
 assert.equal(f.requests[1].data.action,'abmm_restore_menu_version');
 assert.equal(f.requests[1].data.version_id,'v1'); assert.equal(f.requests[1].data.base_revision,'rev-now');
 // Switching menus shows the new menu, and a slow answer for the old one is ignored.
 $('#abmm-history-menu').val('w').trigger('change');
 $('#abmm-history-menu').val('y').trigger('change');
 assert.equal(f.requests[3].data.menu_id,'y');
 f.requests[3].d.resolve({success:true,data:{revision:'r',versions:[{id:'v9',saved_at:1,saved_label:'Newer',items:1}]}});
 f.requests[2].d.resolve({success:true,data:{revision:'r',versions:[{id:'v8',saved_at:1,saved_label:'Stale',items:1}]}});
 assert.match($('.abmm-version-list').text(),/Newer/);
 assert.doesNotMatch($('.abmm-version-list').text(),/Stale/);
 f.close();
 // The editor's History link lands on the same panel, on the menu it came from.
 f=await list(card+historyPanel,'http://localhost/?abmm_history=y'); $=f.$;
 assert.equal($('#abmm-backup-panel').prop('open'),true);
 assert.equal($('#abmm-history-menu').val(),'y');
 assert.equal(f.requests[0].data.menu_id,'y');
 f.close();
 // An ID that isn't a menu here only opens the panel on the first menu.
 f=await list(card+historyPanel,'http://localhost/?abmm_history=gone'); $=f.$;
 assert.equal($('#abmm-history-menu').val(),'w');
 assert.equal(f.requests[0].data.menu_id,'w');
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
 // The checklist speaks to a menu that is already on the site.
 f=await fixture(false,false,null,'demo',{...menu,items:[{id:'home',label:'Home',type:'link',url:'#'}]}); $=f.$;
 assert.match($('.abmm-readiness-summary').text(),/before you place this menu/,'an unplaced menu is told to fix things before placing it');
 f.close();
 {
  const placed=new JSDOM('<body><div id="abmm-builder" data-menu-id="demo" data-menu-revision="r" data-menu-placed="1"><ul class="abmm-readiness-issues"></ul><div class="abmm-readiness-summary"></div></div><script id="abmm-menu-data" type="application/json">'+JSON.stringify({...menu,items:[{id:'home',label:'Home',type:'link',url:'#'}]})+'</script></body>',{url:'http://localhost/',runScripts:'outside-only'});
  const w=placed.window; const jq=require('jquery')(w); w.jQuery=jq; w.matchMedia=()=>({matches:false}); jq.fn.wpColorPicker=jq.fn.sortable=function(){return this;};
  w.abmmAdmin={ajaxUrl:'/ajax',nonce:'test',strings:new Proxy({},{get:(_,key)=>String(key)}),icons:[]}; jq.post=()=>jq.Deferred().promise();
  w.eval(source); w.document.dispatchEvent(new w.Event('DOMContentLoaded')); await wait(20);
  assert.match(jq('.abmm-readiness-summary').text(),/to fix on this menu\.$/,'a placed menu is not told to place it');
  w.close();
 }

 // A save with no edits changes nothing, using the real editor form.
 const editorForm=fs.readFileSync('admin/views/builder.php','utf8').replace(/<\?php[\s\S]*?\?>/g,'');
 const defaults={preset:'classic',mobile_enhancements:true,presentation:'stacked',layout:'sidebar-right',sidebar_bg:'#f0f0f5',active_bg:'#ffffff',panel_bg:'#ffffff',header_bg:'#ffffff',header_transparent:true,header_z_index:2000,accent:'#1a73e8',text_color:'#2c2c2c',nav_link_color:'#2c2c2c',nav_hover_color:'#1a73e8',muted_color:'#6b6b6b',border_color:'#e5e5ea',cta_text:'#ffffff',icon_inherit_text:false,icon_color:'#2c2c2c',icon_hover_color:'#1a73e8',icon_active_color:'#1a73e8',icon_size:24,icon_background:'',icon_border_color:'',icon_radius:4,grid_columns:4,panel_width:900,full_width:false,sidebar_width:300,border_radius:6,shadow:'large',nav_align:'left',cta_style:'square',uppercase_cats:true,show_cat_desc:false,panel_title_align:'left',style:{panel_height_mode:'fixed',panel_height:600,font_family_mode:'inherit'}};
 const stored={title:'Main',items:[{id:'home',label:'Home',type:'link',url:'/',target:'_blank',rel:'nofollow',class:'x'},{id:'prod',label:'Products',type:'mega',url:'',columns:2,categories:[{id:'c1',title:'One',groups:[{id:'g1',title:'',links:[{id:'l1',label:'A',url:'/a'}]}]}]}],cta:{show:true,label:'Go',url:'/go',target:'_blank',rel:'nofollow',class:'cta'},brand:{show:true,title:'Brand',url:'/',logo_url:'/l.png',logo_dark_url:'/d.png',alt:'Brand',logo_height:48,target:'_blank',rel:'noopener',class:'site-logo'},context:{label:'Old'}};
 const roundTrip=async(settings,classic)=>{
  const data={...stored,settings};
  const dom=new JSDOM('<body>'+editorForm+'</body>',{url:'http://localhost/',runScripts:'outside-only'});
  const w=dom.window; const jq=require('jquery')(w); w.jQuery=jq; w.matchMedia=()=>({matches:false});
  jq.expr.pseudos.visible=el=>!el.hidden&&!el.closest('[hidden]'); jq.fn.wpColorPicker=jq.fn.sortable=function(){return this;};
  jq('#abmm-builder').attr({'data-menu-id':'demo','data-menu-revision':'r','data-menu-placed':'1'});
  // PHP prints the stored values into the form. It is stripped here, so a restored draft fills the form instead.
  jq('#abmm-menu-data').text(JSON.stringify({...data,title:'Before the draft'}));
  w.localStorage.setItem('abmmDraft:demo',JSON.stringify({revision:'r',updated:Date.now(),menu:data}));
  // The PHP that prints Classic controls and option lists is gone: match what it would print.
  if(!classic){ jq('#abmm-render-mode').closest('label').remove(); jq('.abmm-classic-notice').remove(); }
  ['abmm-brand-logo-url','abmm-brand-logo-dark-url'].forEach(id=>{ if(!jq('#'+id).length) jq('#abmm-builder').append(jq('<input>').attr('id',id)); });
  const sel=(id,v)=>{const el=jq(id); if(el.is('select')&&!el.find('option').filter((_,o)=>o.value===String(v)).length) el.append(jq('<option>').val(String(v)));};
  sel('#abmm-layout',settings.layout); sel('#abmm-nav-align',settings.nav_align); sel('#abmm-grid-cols',settings.grid_columns); sel('#abmm-shadow',settings.shadow); sel('#abmm-cta-style',settings.cta_style); sel('#abmm-panel-title-align',settings.panel_title_align);
  w.abmmAdmin={ajaxUrl:'/ajax',nonce:'test',strings:new Proxy({},{get:(_,key)=>String(key)}),icons:[]};
  const posts=[]; jq.post=(_,body)=>{posts.push(body);return jq.Deferred().promise();};
  w.eval(source); w.document.dispatchEvent(new w.Event('DOMContentLoaded')); await wait(30);
  jq('.abmm-restore-draft').trigger('click');
  jq('.abmm-save-menu').first().trigger('click');
  const body=posts.filter(p=>p.action==='abmm_save_menu').pop();
  assert.ok(body,'the editor posts a save');
  const sent=JSON.parse(body.menu_data);
  w.close();
  return sent;
 };
 const sorted=v=>Array.isArray(v)?v.map(sorted):v&&typeof v==='object'?Object.fromEntries(Object.keys(v).sort().map(k=>[k,sorted(v[k])])):v;
 let sent=await roundTrip(defaults,false);
 assert.deepEqual(sorted(sent),sorted({...stored,settings:defaults}),'a native menu saves unchanged');
 const classicSettings={...defaults,render_mode:'emm-classic',classic_edge_bleed:8,classic_phone_scroll_fix:true,classic_toggle_label:'Products',classic_bar_padding:'8px 16px',classic_plain_markup:true};
 sent=await roundTrip(classicSettings,true);
 assert.deepEqual(sorted(sent),sorted({...stored,settings:classicSettings}),'a Classic menu saves unchanged, keeping settings with no control');

 // Controls that were taken out of the editor stay out.
 for(const id of ['abmm-classic-phone-scroll-fix','abmm-classic-bar-padding','abmm-classic-plain-markup']) assert.ok(!editorForm.includes(id),id+' is not offered in the editor');

 // Import errors stay until the next attempt.
 assert.ok(/if \(msg && !isError\) \{\s+state\.importStatusTimer/.test(source),'import errors do not time out');
}

// One clear path through create, place and check, with nothing lost.
async function simplicityRegressions(){
 const builderView=fs.readFileSync('admin/views/builder.php','utf8');
 const listView=fs.readFileSync('admin/views/list.php','utf8');

 // A row can have no link on purpose: the checklist stops asking, the row says so, and it saves.
 {
  const stored={title:'Main',items:[{id:'home',label:'Home',type:'link',url:'/h'},{id:'soon',label:'Soon',type:'link',url:'#',no_link:true},{id:'dead',label:'Dead',type:'link',url:'#'},{id:'real',label:'Real',type:'link',url:'/r',no_link:true},{id:'prod',label:'Prod',type:'mega',url:'',mega_style:'features',columns:2,links:[{id:'l1',label:'A',url:'/a'}],categories:[]}],cta:{show:false,label:'',url:''},brand:{show:false},settings:{presentation:'stacked'}};
  const dom=new JSDOM('<body><span class="abmm-save-status"></span><button class="abmm-save-menu">Save</button><button class="abmm-undo">Undo</button><div id="abmm-builder" data-menu-id="demo" data-menu-revision="r" data-menu-placed="1"><input id="abmm-menu-title" value="Main"><ul class="abmm-readiness-issues"></ul><div id="abmm-nav-items"></div><main class="abmm-builder__main"><div class="abmm-preview-shell" data-abmm-preview-viewport="desktop"><div id="abmm-preview"></div></div></main></div><script id="abmm-menu-data" type="application/json">'+JSON.stringify(stored)+'</script></body>',{url:'http://localhost/',runScripts:'outside-only'});
  const w=dom.window; const jq=require('jquery')(w); w.jQuery=jq; w.matchMedia=()=>({matches:false}); jq.fn.wpColorPicker=jq.fn.sortable=function(){return this;};
  jq.expr.pseudos.visible=el=>!el.hidden&&!el.closest('[hidden]');
  w.abmmAdmin={ajaxUrl:'/ajax',nonce:'test',strings:new Proxy({},{get:(_,key)=>String(key)}),icons:[]};
  const posts=[]; jq.post=(_,data)=>{const d=jq.Deferred();posts.push({data,d});return d.promise();};
  w.eval(source); w.document.dispatchEvent(new w.Event('DOMContentLoaded')); await new Promise(resolve=>setTimeout(resolve,30));
  const issues=()=>jq('.abmm-readiness-issues').text();
  const row=id=>jq('.abmm-nav-item-row[data-id="'+id+'"]');
  assert.ok(!/"Soon"/.test(issues()),'no link on purpose raises no issue');
  assert.match(issues(),/"Dead"/,'other rows are still flagged');
  assert.equal(row('soon').find('.abmm-nav-item-row__url').text(),'No link','the row says it has no link');
  assert.ok(row('soon').find('.abmm-nav-item-row__url').hasClass('is-no-link'),'the row shows a chip');
  assert.ok(row('soon').find('.abmm-more-options').prop('open'),'More options opens when this is on');
  assert.ok(!row('dead').find('.abmm-nav-item-row__url').hasClass('is-no-link'),'an ordinary empty row has no chip');
  assert.ok(!row('real').find('.abmm-nav-item-row__url').hasClass('is-no-link'),'a real address wins over the flag');
  assert.equal(row('prod').find('.abmm-link-no-link').length,0,'a dropdown has no such option: its own page is optional');
  assert.match(source,/linkMoreOptionsHtml\(link, true\)/,'links inside a dropdown offer it too');
  row('dead').find('.abmm-link-no-link').prop('checked',true).trigger('change');
  assert.ok(!/"Dead"/.test(issues()),'ticking it clears the issue');
  assert.ok(row('dead').find('.abmm-nav-item-row__url').hasClass('is-no-link'),'ticking it shows the chip');
  row('soon').find('.abmm-link-no-link').prop('checked',false).trigger('change');
  assert.match(issues(),/"Soon"/,'unticking it brings the issue back');
  jq('.abmm-save-menu').first().trigger('click');
  const sent=JSON.parse(posts.filter(p=>p.data.action==='abmm_save_menu').pop().data.menu_data);
  const saved=id=>sent.items.find(i=>i.id===id);
  assert.strictEqual(saved('dead').no_link,true,'it saves as true');
  assert.ok(!('no_link' in saved('soon')),'it is removed when off, not stored as false');
  assert.ok(!('no_link' in saved('home')),'rows that never had it stay without it');
  w.close();
 }
 const panelOf=id=>{
  const at=builderView.indexOf('id="'+id+'"');
  assert.ok(at>=0,id+' is still in the editor');
  const found=[...builderView.slice(0,at).matchAll(/data-abmm-builder-section-panel="(\w+)"/g)].pop();
  return found && found[1];
 };

 // The stepper is the only navigation.
 assert.ok(!/role="tablist"|role="tab"|role="tabpanel"/.test(builderView),'no tabs beside the stepper');
 assert.deepEqual([...builderView.matchAll(/data-abmm-workflow="(\w+)"/g)].map(m=>m[1]),sections,'Content, Design, Place, Check, in that order');
 assert.deepEqual([...builderView.matchAll(/data-abmm-builder-section-panel="(\w+)"/g)].map(m=>m[1]),sections,'each step has one panel');

 // Everything from the old Settings tab still has a home, with its ID unchanged.
 for(const id of ['abmm-brand-show','abmm-brand-title','abmm-brand-url','abmm-brand-alt','abmm-brand-logo-height']) assert.equal(panelOf(id),'content',id+' moved to Content');
 assert.match(builderView,/abmm_logo_field\( 'abmm-brand-logo-url'/,'the logo image field stays');
 assert.match(builderView,/abmm_logo_field\( 'abmm-brand-logo-dark-url'/,'the dark logo field stays');
 assert.equal(panelOf('abmm-mobile-enhancements'),'design','Enhanced mobile moved to Design');
 assert.ok(builderView.indexOf('abmm-product-navigation-panel')>builderView.indexOf('id="abmm-section-panel-design"') && builderView.indexOf('abmm-product-navigation-panel')<builderView.indexOf('id="abmm-section-panel-help"'),'Product Navigation moved to Design');

 // Deep links keep their targets, on the steps they name.
 assert.equal(panelOf('placement'),'help');
 assert.equal(panelOf('abmm-readiness-panel'),'help','the checklist is on Place');
 assert.ok(builderView.indexOf('id="abmm-readiness-panel"')<builderView.indexOf('id="placement"'),'the checklist comes before the ways to place the menu');
 assert.equal(panelOf('abmm-verification-panel'),'verify');
 assert.match(listView,/#placement/);
 assert.match(listView,/#abmm-readiness-panel/);

 // Place leads with the recommended method; the others stay one click away.
 assert.ok(builderView.indexOf('abmm-place-recommended')<builderView.indexOf('abmm-place-others'),'the recommendation comes first');
 const others=builderView.slice(builderView.indexOf('abmm-place-others'),builderView.indexOf('id="abmm-section-panel-verify"'));
 assert.match(others,/abmm_place_others as/,'the other methods are listed under the disclosure');
 assert.match(others,/For developers: PHP/,'the PHP helper is still there');
 assert.match(builderView,/unset\( \$abmm_place_others\[ \$abmm_place_method \] \)/,'the recommended method is not listed twice');
 assert.equal((builderView.match(/\$abmm_shortcode_field\((?: true )?\);/g)||[]).length,2,'the shortcode shows with the recommendation or under the others');
 assert.ok(!/abmm-builder-header__subtitle[^\n]*ashbi_mega_menu/.test(builderView),'the header no longer repeats the shortcode');
 // Small leftovers: nothing extra on the default path, and nothing lost.
 assert.match(listView,/class="button abmm-load-backups" hidden/,'the backup button starts hidden');
 assert.match(builderView,/<details class="abmm-more-options abmm-featured-details" id="abmm-featured-details">[\s\S]*id="abmm-featured-editor"[\s\S]*<\/section>\s*<\/details>/,'the featured card sits in a closed section');
 assert.match(builderView,/abmm-builder-header__subtitle<\?php echo \$abmm_is_placed \? ' is-placed'/,'the header line knows when the status says the same');
 {
  const brand=fs.readFileSync('assets/css/brand-admin.css','utf8');
  const line=brand.match(/\.abmm-builder-header__subtitle \{[^}]*\}/)[0];
  assert.ok(!/nowrap|overflow:hidden/.test(line),'the header line wraps, so long translations cannot clip');
  assert.match(brand,/\(min-width:783px\) and \(max-width:1319px\), \(max-width:480px\) \{[^@]*__subtitle\.is-placed \{display:block\}/,'it returns where the status has no room');
 }
 assert.match(builderView,/abmm-place-rollback[^\n]*Keep your theme[^\n]*never changes your theme/,'the rollback advice keeps its meaning');
 assert.match(builderView,/abmm-placement-state/,'Check still says what cannot be detected');

 // The saved state says where the menu is live, and admits what cannot be detected.
 let f=await fixture(); let $=f.$;
 assert.equal($('.abmm-save-status__placement').text(),'No placement found');
 assert.match($('.abmm-save-status__placement').attr('title'),/theme templates or PHP cannot be detected/);
 assert.equal($('.abmm-placement-save-hint').prop('hidden'),true,'with nothing to say the Place hint stays out of the way');
 $('#abmm-menu-title').val('Changed').trigger('input');
 assert.equal($('.abmm-save-status__placement').length,0,'nothing claims to be live while changes are unsaved');
 assert.equal($('.abmm-placement-save-hint').prop('hidden'),false,'unsaved changes bring the hint back');
 $('.abmm-save-menu').trigger('click');
 f.requests[0].d.resolve({success:true,data:{revision:'revision-2'}});
 assert.equal($('.abmm-save-status__placement').text(),'No placement found','an unplaced menu stays honest after saving');
 $('#abmm-builder').attr('data-menu-placed','1');
 $('#abmm-menu-title').val('Changed again').trigger('input');
 $('.abmm-save-menu').trigger('click');
 f.requests[1].d.resolve({success:true,data:{revision:'revision-3'}});
 assert.equal($('.abmm-save-status__placement').text(),'Live where placed','a placed menu says the save is live');
 assert.equal($('.abmm-save-status__placement').hasClass('is-live'),true);
 assert.match($('.abmm-save-status').text(),/savedState/,'next to the usual saved message');
 // The button already says Saved, so the CSS hides those words and never the placement.
 const brandCss=fs.readFileSync('assets/css/brand-admin.css','utf8');
 assert.match(brandCss,/\.abmm-save-status\.is-saved \.abmm-save-status__message \{position:absolute/);
 assert.ok(!/\.abmm-save-status\.is-saved \{position:absolute/.test(brandCss),'the saved state stays in view');
 f.close();

 const wait=ms=>new Promise(resolve=>setTimeout(resolve,ms));
 // A cached "not placed" is checked again, so a live menu keeps its warning.
 {
  const load=async(placedAttr)=>{
   const dom=new JSDOM('<body><span class="abmm-save-status"></span><button class="abmm-save-menu">Save</button><p class="abmm-placement-state">Not found</p><div id="abmm-builder" data-menu-id="demo" data-menu-revision="r" data-menu-placed="'+placedAttr+'"><ul class="abmm-readiness-issues"></ul><div class="abmm-readiness-summary"></div></div><script id="abmm-menu-data" type="application/json">'+JSON.stringify(menu)+'</script></body>',{url:'http://localhost/',runScripts:'outside-only'});
   const w=dom.window; const jq=require('jquery')(w); w.jQuery=jq; w.matchMedia=()=>({matches:false}); jq.fn.wpColorPicker=jq.fn.sortable=function(){return this;};
   w.abmmAdmin={ajaxUrl:'/ajax',nonce:'test',strings:new Proxy({},{get:(_,key)=>String(key)}),icons:[]};
   const posts=[]; jq.post=(_,data)=>{const d=jq.Deferred();posts.push({data,d});return d.promise();};
   w.eval(source); w.document.dispatchEvent(new w.Event('DOMContentLoaded')); await wait(20);
   return {w,jq,posts};
  };
  let p=await load('1');
  assert.equal(p.posts.length,0,'a menu known to be placed needs no second look');
  p.w.close();
  p=await load('0');
  assert.equal(p.posts.length,1,'a menu reported as not placed is checked again');
  assert.equal(p.posts[0].data.action,'abmm_menu_references');
  assert.equal(p.posts[0].data.menu_id,'demo');
  assert.equal(p.jq('.abmm-save-status__placement').text(),'No placement found');
  p.posts[0].d.resolve({success:true,data:{known:2}});
  assert.equal(p.jq('#abmm-builder').attr('data-menu-placed'),'1');
  assert.equal(p.jq('.abmm-save-status__placement').text(),'Live where placed','the fresh count replaces the stale answer');
  assert.match(p.jq('.abmm-placement-state').text(),/Found on your site/);
  p.w.close();
  p=await load('0');
  p.posts[0].d.resolve({success:true,data:{known:0}});
  assert.equal(p.jq('#abmm-builder').attr('data-menu-placed'),'0','a menu that really is not placed stays that way');
  p.w.close();
  p=await load('0');
  p.posts[0].d.reject();
  assert.equal(p.jq('#abmm-builder').attr('data-menu-placed'),'0','a failed check changes nothing');
  p.w.close();
 }

 // A stored setting with no control can be removed through the normal path, and undone.
 {
  const stored={...menu,brand:{...(menu.brand||{}),rel:'nofollow',class:'keep'},settings:{presentation:'unified',render_mode:'emm-classic',classic_bar_padding:'0',classic_plain_markup:true,classic_edge_bleed:8}};
  const dom=new JSDOM('<body><span class="abmm-save-status"></span><button class="abmm-save-menu">Save</button><button class="abmm-undo">Undo</button><div id="abmm-builder" data-menu-id="demo" data-menu-revision="r" data-menu-placed="1"><input id="abmm-menu-title" value="Demo"><div id="abmm-nav-items"></div><main class="abmm-builder__main"><div class="abmm-preview-shell" data-abmm-preview-viewport="desktop"><div id="abmm-preview"></div></div></main></div><script id="abmm-menu-data" type="application/json">'+JSON.stringify(stored)+'</script></body>',{url:'http://localhost/',runScripts:'outside-only'});
  const w=dom.window; const jq=require('jquery')(w); w.jQuery=jq; w.matchMedia=()=>({matches:false}); jq.fn.wpColorPicker=jq.fn.sortable=function(){return this;};
  jq.expr.pseudos.visible=el=>!el.hidden&&!el.closest('[hidden]');
  w.abmmAdmin={ajaxUrl:'/ajax',nonce:'test',strings:new Proxy({},{get:(_,key)=>String(key)}),icons:[]};
  const posts=[]; jq.post=(_,data)=>{const d=jq.Deferred();posts.push({data,d});return d.promise();};
  w.ABMMAdvanced={refresh(){}};
  w.eval(source); w.document.dispatchEvent(new w.Event('DOMContentLoaded')); await wait(30);
  assert.equal(typeof w.ABMMAdvanced.onRemove,'function','the editor takes Remove requests');
  w.ABMMAdvanced.onRemove('classic_bar_padding');
  assert.equal(jq('.abmm-save-status').hasClass('is-dirty'),true,'removing marks the menu as changed');
  jq('.abmm-save-menu').first().trigger('click');
  let sent=JSON.parse(posts.filter(p=>p.data.action==='abmm_save_menu').pop().data.menu_data);
  assert.equal(sent.settings.classic_bar_padding,undefined,'the removed setting is not saved');
  assert.equal(sent.settings.classic_plain_markup,true,'the others stay');
  assert.equal(sent.settings.classic_edge_bleed,8,'the others stay');
  assert.equal(sent.brand.rel,'nofollow','a stored logo option is kept until removed');
  posts.filter(p=>p.data.action==='abmm_save_menu').pop().d.resolve({success:true,data:{revision:'r2'}});
  jq('.abmm-undo').trigger('click');
  jq('.abmm-save-menu').first().trigger('click');
  sent=JSON.parse(posts.filter(p=>p.data.action==='abmm_save_menu').pop().data.menu_data);
  assert.equal(sent.settings.classic_bar_padding,'0','Undo brings it back');
  posts.filter(p=>p.data.action==='abmm_save_menu').pop().d.resolve({success:true,data:{revision:'r3'}});
  w.ABMMAdvanced.onRemove('brand.rel');
  assert.equal(jq('.abmm-save-status').hasClass('is-dirty'),true,'removing a logo option marks the menu as changed');
  jq('.abmm-save-menu').first().trigger('click');
  sent=JSON.parse(posts.filter(p=>p.data.action==='abmm_save_menu').pop().data.menu_data);
  assert.ok(!sent.brand.rel,'the removed logo option is not saved');
  assert.equal(sent.brand.class,'keep','other logo options stay');
  w.close();
 }

 // Calm rows: move, duplicate and remove sit in one menu that follows the popover pattern.
 {
  const calm={...menu,items:['a','b'].map(id=>({id,label:id.toUpperCase(),type:'link',url:'/'+id}))};
  const g=await fixture(false,false,null,'demo',calm); const q=g.$;
  const head=q('#abmm-nav-items .abmm-nav-item-row').eq(0).find('.abmm-nav-item-row__head');
  const toggle=head.find('.abmm-row-menu__toggle'), panel=head.find('.abmm-row-menu__panel');
  assert.equal(panel.prop('hidden'),true,'the row menu starts closed');
  for (const name of ['.abmm-move-up','.abmm-move-down','.abmm-duplicate-nav','.abmm-remove-nav']) assert.equal(panel.find(name).length,1,name+' is in the row menu');
  const shown=head.find('button').filter((_,el)=>!q(el).closest('[hidden]').length);
  assert.equal(shown.length,2,'a closed nav row shows two buttons: its menu and its expand toggle');
  toggle.trigger('click');
  assert.equal(panel.prop('hidden'),false,'the button opens the menu');
  assert.equal(toggle.attr('aria-expanded'),'true');
  await wait(10);
  assert.equal(g.window.document.activeElement,panel.find('.abmm-move-down')[0],'focus goes to the first enabled action, not a disabled one');
  panel.find('.abmm-move-down').trigger(q.Event('keydown',{key:'Escape'}));
  assert.equal(panel.prop('hidden'),true,'Escape closes the menu');
  assert.equal(g.window.document.activeElement,toggle[0],'and returns focus to its button');
  const label=head.closest('.abmm-nav-item-row').find('.abmm-nav-label');
  label.val('Renamed').trigger('input');
  assert.equal(q('#abmm-nav-items .abmm-nav-item-row').eq(0).find('.abmm-row-menu__toggle').attr('aria-label'),'Actions for navigation item Renamed','the menu button follows the label');
  g.close();
 }

 // "I've checked it" lives on the Check step and keeps unsaved edits on the page.
 {
  const g=await fixture(false,false,null,'demo',menu,'<button id="chk" data-abmm-check="verify"></button><button id="unchk" data-abmm-check="unverify" hidden></button>'); const q=g.$;
  q('#chk').trigger('click');
  const sent=g.requests.filter(r=>r.data.action==='abmm_setup_guide').pop();
  assert.equal(sent.data.guide_action,'verify','the Check step saves through the setup guide action');
  sent.d.resolve({success:true,data:{}});
  assert.equal(q('#chk').prop('hidden'),true,'checked: the button goes away');
  assert.equal(q('#unchk').prop('hidden'),false,'and it can be undone');
  g.close();
 }
 assert.match(builderView,/data-abmm-check="verify"/,'the editor offers the check');
 assert.equal(builderView.slice(builderView.indexOf('id="abmm-verification-panel"')).indexOf('data-abmm-check="verify"')<builderView.slice(builderView.indexOf('id="abmm-verification-panel"')).indexOf('abmm-builder-help'),true,'on the Check step, next to the real-page list');
 assert.ok(builderView.indexOf('id="abmm-editor-features"')<builderView.indexOf('id="abmm-mega-style-picker"'),'links come before Layout in the dropdown editor');
 assert.ok(builderView.indexOf('id="abmm-mega-style-picker"')<builderView.indexOf('id="abmm-featured-details"'),'and Layout before the Featured card');

 // One progress path: the dashboard shows one next action, and the stepper is the only step list.
 assert.ok(!/abmm-setup-guide__steps/.test(listView),'no step list on the dashboard');
 assert.match(listView,/data-guide-action="dismiss"/,'the card can be hidden');
 assert.match(listView,/#abmm-verification-panel/,'an undetectable placement can still reach Check');
 assert.ok(!/data-guide-action="verify"/.test(listView),'the check itself moved to the Check step');
 for (const value of ['edit','place','verify']) assert.match(listView,new RegExp("'"+value+"' === \\$abmm_guide_next"),'the card has a next action for '+value);

 // The dashboard card keeps Place in view and tucks the shortcode behind a reveal.
 const card=listView.slice(listView.indexOf('abmm-menu-card__body'),listView.indexOf('abmm-card-menu__toggle'));
 const reveal=card.slice(card.indexOf('<details class="abmm-menu-card__reveal">'),card.indexOf('</details>'));
 assert.match(reveal,/abmm-copy-shortcode/,'the shortcode and its copy button are inside the reveal');
 assert.ok(card.indexOf('#placement')>card.indexOf('</details>'),'Place stays outside the reveal, on every card');
 assert.ok(!/abmm-how/.test(listView),'one progress list on the dashboard: the next step card');
 assert.match(listView,/abmm-setup-guide__tips/,'the setup notes are folded, not lost');

 // Place ends in a button, not a paragraph, and names the Elementor route.
 assert.match(builderView,/'block'\s*=> array\( 'site-editor\.php'/,'a block theme gets a button to the Site Editor');
 assert.match(builderView,/'widget' => array\( 'widgets\.php'/,'a classic theme gets a button to Widgets');
 assert.match(builderView,/class="button button-primary abmm-place-open"/);
 assert.match(builderView,/abmm-place-elementor/);
 assert.match(builderView,/\$abmm_shortcode_field\( true \)/,'where the shortcode is the way, copying it is the main action');

 // With no menus, Tools is one closed line. With menus it stays open as before.
 assert.match(listView,/empty\( \$menus \)[^\n]*\n\s*<details class="abmm-tools__closed">/);
 assert.equal((listView.match(/<details class="abmm-tools__closed">/g)||[]).length,1);
 assert.match(listView,/<\?php if \( empty\( \$menus \) \) : \?>\s*<\/details>\s*<\?php endif; \?>\s*<\/section>/,'the closed section ends with the Tools section');
 assert.ok(!/abmm-tools__closed[^>]* open/.test(listView),'it starts closed');

 // Words that no longer match the editor are gone.
 assert.ok(!/Design tab|mega menus? that|Each mega menu|Full width mega menu/.test(builderView));

 // A dropdown keeps its own page through a rename, and shows it under More options.
 const dropdown={id:'d',label:'Products',type:'mega',url:'/products/',target:'_blank',rel:'nofollow',mega_style:'features',links:[{id:'l',label:'One',url:'/one'}]};
 const bare={id:'b',label:'Bare',type:'mega',url:'#',mega_style:'features',links:[{id:'m',label:'Two',url:'/two'}]};
 f=await fixture(false,false,null,'demo',{...menu,items:[dropdown,bare]}); $=f.$;
 const rowOf=id=>$('#abmm-nav-items .abmm-nav-item-row').filter((_,el)=>$(el).data('id')===id);
 assert.equal(rowOf('d').find('.abmm-nav-item-row__more').prop('open'),true,'a dropdown with its own page shows it');
 assert.equal(rowOf('b').find('.abmm-nav-item-row__more').prop('open'),false,'a dropdown without one keeps it tucked away');
 assert.equal(rowOf('d').find('.abmm-nav-url').val(),'/products/');
 rowOf('d').find('.abmm-nav-label').val('Renamed').trigger('input');
 $('.abmm-save-menu').trigger('click');
 let sent=JSON.parse(f.requests[0].data.menu_data).items[0];
 assert.equal(sent.label,'Renamed');
 assert.equal(sent.url,'/products/','renaming a dropdown keeps its page');
 assert.equal(sent.target,'_blank','and its link options');
 assert.equal(sent.rel,'nofollow');
 f.close();
 // A row that shows no URL field still can't lose the value.
 f=await fixture(false,false,null,'demo',{...menu,items:[dropdown]}); $=f.$;
 $('#abmm-nav-items .abmm-nav-url').closest('details').remove();
 $('#abmm-nav-items .abmm-nav-label').val('Renamed').trigger('input');
 $('.abmm-save-menu').trigger('click');
 sent=JSON.parse(f.requests[0].data.menu_data).items[0];
 assert.equal(sent.url,'/products/','no field means nothing to overwrite');
 assert.equal(sent.target,'_blank');
 f.close();

 // A link still on the "#" placeholder is prompted for, in the row and the chip.
 f=await fixture(false,false,null,'demo',{...menu,items:[{id:'a',label:'Home',type:'link',url:'#'},{id:'b',label:'About',type:'link',url:'/about'}]}); $=f.$;
 assert.equal($('#abmm-nav-items .abmm-nav-item-row').eq(0).find('.abmm-nav-item-row__url').text(),'No link yet','a link on the placeholder says it has no link yet');
 assert.equal($('#abmm-nav-items .abmm-nav-item-row').eq(1).find('.abmm-nav-item-row__url').text(),'/about');
 f.close();

 // The sample warning goes as soon as the saved menu stops being one.
 const warning='<section class="abmm-sample-warning"><h2>This is a sample</h2></section>';
 f=await fixture(false,false,null,'demo',menu,warning); $=f.$;
 $('#abmm-menu-title').val('Changed').trigger('input');
 $('.abmm-save-menu').trigger('click');
 f.requests[0].d.resolve({success:true,data:{revision:'r2',sample:true}});
 assert.equal($('.abmm-sample-warning').length,1,'still a sample: the warning stays');
 $('#abmm-menu-title').val('Changed again').trigger('input');
 $('.abmm-save-menu').trigger('click');
 f.requests[1].d.resolve({success:true,data:{revision:'r3',sample:false}});
 assert.equal($('.abmm-sample-warning').length,0,'no longer a sample: the warning goes');
 f.close();
 console.log('Stepper-only navigation, moved settings, placement path, publish wording, dashboard reveal, dropdown pages and sample warning passed.');
}
