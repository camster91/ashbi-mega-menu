/** Editable vector artwork and exact-size WordPress directory exports. */
const fs = require('node:fs');
const path = require('node:path');
const sharp = require('sharp');
const root = path.resolve(__dirname, '..');
const source = path.join(root, 'design/brand');
const directory = path.join(root, '.wordpress-org');
const runtime = path.join(root, 'assets/brand');
for (const folder of [source, directory, runtime]) fs.mkdirSync(folder, {recursive:true});
const ink='#192238', blue='#2457ff', apricot='#ffb568', paper='#f8f9fd';
const mark=`<rect width="256" height="256" rx="60" fill="${blue}"/><rect x="54" y="58" width="148" height="24" rx="8" fill="white"/><rect x="54" y="108" width="58" height="24" rx="8" fill="white"/><rect x="54" y="158" width="58" height="24" rx="8" fill="white"/><rect x="138" y="108" width="64" height="74" rx="12" fill="${apricot}"/>`;
const icon=`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" width="256" height="256">${mark}</svg>`;
const banner=`<svg xmlns="http://www.w3.org/2000/svg" width="1544" height="500" viewBox="0 0 1544 500">
<rect width="1544" height="500" fill="${ink}"/>
<circle cx="1450" cy="20" r="360" fill="#222f4e"/><circle cx="1480" cy="40" r="275" fill="none" stroke="#354667" stroke-width="1"/>
<g transform="translate(66 52) scale(.24)">${mark}</g>
<g font-family="Segoe UI,Arial,sans-serif" fill="white">
<text x="148" y="91" font-size="27" font-weight="700">ASHBI MEGA MENU</text>
<text x="66" y="216" font-size="73" font-weight="700" letter-spacing="-2">Navigation,</text>
<text x="66" y="300" font-size="73" font-weight="700" letter-spacing="-2">made clear.</text>
<text x="70" y="361" font-size="26" fill="#cbd3e5">Build responsive menus visually in WordPress.</text>
<rect x="70" y="407" width="162" height="38" rx="19" fill="#303e5b"/><text x="151" y="432" text-anchor="middle" font-size="17" font-weight="600">OPEN SOURCE</text>
<text x="254" y="431" font-size="19" fill="#cbd3e5">Desktop · Mobile · Your way</text>
</g>
<g transform="translate(851 92)">
<rect x="14" y="15" width="606" height="322" rx="23" fill="#10182b"/>
<rect width="606" height="322" rx="23" fill="${paper}"/>
<rect width="606" height="68" rx="23" fill="white"/><path d="M0 67H606" stroke="#e4e8f1"/>
<rect x="26" y="23" width="32" height="22" rx="6" fill="${blue}"/>
<g font-family="Segoe UI,Arial,sans-serif" font-size="17" fill="${ink}" font-weight="600"><text x="79" y="44">Your brand</text><text x="268" y="44" fill="${blue}">Products</text><text x="381" y="44">Resources</text></g>
<rect x="494" y="20" width="88" height="30" rx="8" fill="${blue}"/>
<rect x="27" y="98" width="166" height="195" rx="14" fill="#eaf0ff"/>
<rect x="43" y="116" width="132" height="41" rx="9" fill="${blue}"/>
<g font-family="Segoe UI,Arial,sans-serif" font-size="17"><text x="59" y="143" fill="white" font-weight="600">Explore</text><text x="59" y="194" fill="#52607c">Solutions</text><text x="59" y="240" fill="#52607c">Support</text></g>
<g transform="translate(220 114)"><rect width="154" height="72" rx="11" fill="white" stroke="#e1e6f0"/><rect x="174" width="154" height="72" rx="11" fill="white" stroke="#e1e6f0"/><rect y="90" width="154" height="72" rx="11" fill="white" stroke="#e1e6f0"/><rect x="174" y="90" width="154" height="72" rx="11" fill="white" stroke="#e1e6f0"/>
<g fill="${apricot}"><rect x="16" y="16" width="26" height="26" rx="7"/><rect x="190" y="16" width="26" height="26" rx="7"/><rect x="16" y="106" width="26" height="26" rx="7"/><rect x="190" y="106" width="26" height="26" rx="7"/></g>
<g fill="#c8d0df"><rect x="55" y="20" width="72" height="8" rx="4"/><rect x="55" y="36" width="48" height="6" rx="3"/><rect x="229" y="20" width="72" height="8" rx="4"/><rect x="229" y="36" width="48" height="6" rx="3"/><rect x="55" y="110" width="72" height="8" rx="4"/><rect x="55" y="126" width="48" height="6" rx="3"/><rect x="229" y="110" width="72" height="8" rx="4"/><rect x="229" y="126" width="48" height="6" rx="3"/></g></g>
</g></svg>`;
const social=`<svg xmlns="http://www.w3.org/2000/svg" width="1280" height="640" viewBox="0 0 1280 640"><rect width="1280" height="640" fill="${ink}"/><g transform="translate(76 78) scale(.32)">${mark}</g><g fill="white" font-family="Segoe UI,Arial,sans-serif"><text x="182" y="134" font-size="34" font-weight="700">Ashbi Mega Menu</text><text x="76" y="300" font-size="88" font-weight="700" letter-spacing="-3">Navigation, made clear.</text><text x="80" y="375" font-size="33" fill="#cbd3e5">A visual menu builder for WordPress.</text><text x="80" y="530" font-size="24" fill="${apricot}">OPEN SOURCE / GPL-2.0-OR-LATER</text></g><g transform="translate(1046 453) scale(.48)">${mark}</g></svg>`;
async function writeSvg(name,svg){fs.writeFileSync(path.join(source,name+'.svg'),svg);}
(async()=>{
 await writeSvg('icon',icon);await writeSvg('banner',banner);await writeSvg('social',social);
 fs.writeFileSync(path.join(runtime,'icon.svg'),icon);fs.writeFileSync(path.join(directory,'icon.svg'),icon);
 for(const n of [128,256]) await sharp(Buffer.from(icon)).resize(n,n).png().toFile(path.join(directory,`icon-${n}x${n}.png`));
 for(const [w,h] of [[772,250],[1544,500]]) await sharp(Buffer.from(banner)).resize(w,h).png().toFile(path.join(directory,`banner-${w}x${h}.png`));
 await sharp(Buffer.from(social)).png().toFile(path.join(source,'social-preview.png'));
 console.log('Brand sources, WordPress icons/banners and social preview exported.');
})().catch(error=>{console.error(error);process.exit(1);});
