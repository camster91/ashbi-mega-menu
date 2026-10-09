const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const mappings = {wallet:'wallet',signature:'signature',users:'users',handshake:'handshake',dollar:'dollar-sign',airplane:'plane',card:'credit-card','search-gear':'search',document:'file-text',building:'building',clipboard:'clipboard',monitor:'monitor',chart:'chart-column',link:'link',star:'star',grid:'grid-2x2',heart:'heart',shield:'shield',globe:'globe',mail:'mail',phone:'phone',settings:'settings',briefcase:'briefcase',calendar:'calendar',clock:'clock',cloud:'cloud',database:'database',folder:'folder',key:'key',lock:'lock',package:'package',rocket:'rocket',tag:'tag','map-pin':'map-pin',bell:'bell','check-circle':'circle-check','user-plus':'user-plus',filter:'list-filter',lightbulb:'lightbulb','pie-chart':'chart-pie'};
const out = path.join(root,'assets/icons/generic');
fs.mkdirSync(out,{recursive:true});
const symbols=[];
for (const [key,name] of Object.entries(mappings)) {
 const svg=fs.readFileSync(path.join(root,'node_modules/lucide-static/icons',name+'.svg'),'utf8');
 fs.writeFileSync(path.join(out,key+'.svg'),svg);
 const body=svg.match(/<svg\b[^>]*>([\s\S]*?)<\/svg>/)[1];
 symbols.push(`<symbol id="abmm-icon-${key}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${body}</symbol>`);
}
fs.writeFileSync(path.join(root,'assets/icons/abmm-sprite.svg'),'<svg xmlns="http://www.w3.org/2000/svg">\n'+symbols.join('\n')+'\n</svg>\n');
fs.copyFileSync(path.join(root,'node_modules/lucide-static/LICENSE'),path.join(root,'assets/icons/LICENSE-lucide.txt'));
console.log(`Built ${symbols.length} licensed Lucide icons.`);
