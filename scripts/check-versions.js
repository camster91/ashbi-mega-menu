// The version must agree everywhere WordPress.org and users read it.
//   node scripts/check-versions.js          all files agree
//   node scripts/check-versions.js v1.2.3   ...and match this release tag
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const pick = (text, re, label) => {
	const m = text.match(re);
	if (!m) throw new Error(`Could not find ${label}.`);
	return m[1].trim();
};
const main = read('ashbi-mega-menu.php');
const found = {
	'package.json version': JSON.parse(read('package.json')).version,
	'plugin header Version': pick(main, /^\s*\*\s*Version:\s*(.+)$/m, 'the plugin header Version'),
	ABMM_VERSION: pick(main, /define\(\s*'ABMM_VERSION',\s*'([^']+)'/, 'ABMM_VERSION'),
	'readme.txt Stable tag': pick(read('readme.txt'), /^Stable tag:\s*(.+)$/m, 'the readme Stable tag'),
	'block.json version': JSON.parse(read('src/blocks/mega-menu/block.json')).version,
};
const tag = process.argv[2];
if (tag) found['release tag'] = tag.replace(/^v/, '');
const values = new Set(Object.values(found));
if (values.size !== 1) {
	console.error('Versions disagree:\n' + Object.entries(found).map(([k, v]) => `  ${k}: ${v}`).join('\n'));
	process.exit(1);
}
const readme = read('readme.txt');
const version = [...values][0];
if (!new RegExp(`^= ${version.replace(/\./g, '\\.')} =$`, 'm').test(readme)) {
	console.error(`readme.txt has no "= ${version} =" changelog entry.`);
	process.exit(1);
}
console.log(`Version ${version} agrees in ${Object.keys(found).join(', ')}, with a changelog entry.`);
