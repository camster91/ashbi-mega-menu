const fs=require('node:fs');
const path=require('node:path');
const Zip=require('adm-zip');
const root=path.resolve(__dirname,'..');
const version=JSON.parse(fs.readFileSync(path.join(root,'package.json'))).version;
const files=['ashbi-mega-menu.php','uninstall.php','readme.txt','LICENSE','third-party-notices.txt'];
const dirs=['admin','assets','build','includes','languages','src'];
function walk(dir){for(const e of fs.readdirSync(path.join(root,dir),{withFileTypes:true})){const p=dir+'/'+e.name;if(e.isDirectory())walk(p);else files.push(p);}}
dirs.forEach(walk);
const zip=new Zip();
for(const file of files){const data=fs.readFileSync(path.join(root,file));if(/sutisoft|WordPress-Mega-Menu|BEGIN (?:RSA |OPENSSH )?PRIVATE KEY/i.test(data.toString()))throw new Error('Private content in '+file);zip.addFile('ashbi-mega-menu/'+file,data);}
const out=path.join(root,'dist');fs.mkdirSync(out,{recursive:true});
const dest=path.join(out,`ashbi-mega-menu-${version}.zip`);zip.writeZip(dest);
fs.writeFileSync(path.join(out,'manifest.json'),JSON.stringify({version,files:files.sort()},null,2)+'\n');
// Tie the ZIP to the exact commit and CI run that built it.
const sha256=require('node:crypto').createHash('sha256').update(fs.readFileSync(dest)).digest('hex');
fs.writeFileSync(path.join(out,'SHA256SUMS'),`${sha256}  ${path.basename(dest)}\n`);
let commit=process.env.GITHUB_SHA||'';
if(!commit){try{commit=require('node:child_process').execSync('git rev-parse HEAD',{cwd:root,stdio:['ignore','pipe','ignore']}).toString().trim();}catch(e){commit='unknown';}}
const run=process.env.GITHUB_RUN_ID?`${process.env.GITHUB_SERVER_URL}/${process.env.GITHUB_REPOSITORY}/actions/runs/${process.env.GITHUB_RUN_ID}`:null;
fs.writeFileSync(path.join(out,'build-info.json'),JSON.stringify({version,zip:path.basename(dest),sha256,commit,run},null,2)+'\n');
console.log(dest);
