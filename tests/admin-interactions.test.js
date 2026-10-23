// Executes the shipped admin script with real jQuery and a disposable DOM.
// WordPress widgets/AJAX are adapters; no browser, network or production state.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { JSDOM } = require('jsdom');
const source = fs.readFileSync('assets/js/admin.js', 'utf8');
const menu = { title: 'Demo', items: [{ id: 'home', label: 'Home', type: 'link', url: '/' }], cta: {}, settings: { presentation:'unified' } };
const sections = ['content', 'design', 'settings', 'help'];
const tabs = ['content', 'design', 'settings'];
async function fixture(narrow = false, malformed = false, draft = null, menuId = 'demo', menuData = menu, extraMain = '') {
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
 <script id="abmm-menu-data" type="application/json">${malformed ? '{invalid' : JSON.stringify(menuData)}</script></body>`, { url:'http://localhost/', runScripts:'outside-only' });
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
 assert.deepEqual(card.children().map((_,el)=>el.className).get(),['abmm-mega__featured-media','abmm-mega__featured-title','abmm-mega__featured-text','abmm-mega__featured-link']);
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
 $('.abmm-save-menu').trigger('click');
 saved = JSON.parse(f.requests[2].data.menu_data);
 assert.equal(saved.items[1].links[0].url,'/b');
 assert.equal(saved.items[1].links[0].target,'_blank');
 f.close();
 console.log('Admin viewport, drawer, keyboard tabs, sheet, save/retry, malformed load and explicit backup restore tests passed.');
})().catch(error=>{console.error(error);process.exitCode=1;});
