const fs=require('node:fs');
const path=require('node:path');
const Zip=require('adm-zip');
const root=path.resolve(__dirname,'..');
const version=JSON.parse(fs.readFileSync(path.join(root,'package.json'))).version;
const files=['ashbi-mega-menu.php','uninstall.php','readme.txt','LICENSE'];
const dirs=['admin','assets','build','includes','languages','src'];
function walk(dir){for(const e of fs.readdirSync(path.join(root,dir),{withFileTypes:true})){const p=dir+'/'+e.name;if(e.isDirectory())walk(p);else files.push(p);}}
dirs.forEach(walk);
const zip=new Zip();
for(const file of files){const data=fs.readFileSync(path.join(root,file));if(/sutisoft|WordPress-Mega-Menu|BEGIN (?:RSA |OPENSSH )?PRIVATE KEY/i.test(data.toString()))throw new Error('Private content in '+file);zip.addFile('ashbi-mega-menu/'+file,data);}
const out=path.join(root,'dist');fs.mkdirSync(out,{recursive:true});
const dest=path.join(out,`ashbi-mega-menu-${version}.zip`);zip.writeZip(dest);
fs.writeFileSync(path.join(out,'manifest.json'),JSON.stringify({version,files:files.sort()},null,2)+'\n');
console.log(dest);
