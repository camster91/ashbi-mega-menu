// Generate the translation template from the packaged ZIP, as translators get it.
//   node scripts/i18n.js          write languages/ashbi-mega-menu.pot
//   node scripts/i18n.js --check  fail on translator-comment warnings, strings without the
//                                 plugin text domain, or a committed template that is out of date
// Needs WP-CLI (`wp`, or WP_CLI=/path/to/wp-cli.phar) and `npm run package` first.
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { execFileSync, spawnSync } = require('node:child_process');
const Zip = require('adm-zip');

const root = path.resolve(__dirname, '..');
const check = process.argv.includes('--check');
const version = JSON.parse(fs.readFileSync(path.join(root, 'package.json'))).version;
const zipPath = path.join(root, 'dist', `ashbi-mega-menu-${version}.zip`);
if (!fs.existsSync(zipPath)) throw new Error('Run npm run package first: ' + zipPath + ' is missing.');

const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'abmm-i18n-'));
new Zip(zipPath).extractAllTo(tmp, true);
const source = path.join(tmp, 'ashbi-mega-menu');
const wp = process.env.WP_CLI ? ['php', [process.env.WP_CLI]] : ['wp', []];

function makePot(dest, extra) {
	const result = spawnSync(wp[0], [...wp[1], 'i18n', 'make-pot', source, dest, ...extra], { encoding: 'utf8', env: { ...process.env, WP_CLI_PHP_ARGS: '-d error_reporting=0' } });
	if (result.status !== 0) throw new Error(result.stderr || result.stdout);
	return (result.stdout + result.stderr).split('\n').filter((line) => line.startsWith('Warning:'));
}
const msgids = (file) => new Set([...fs.readFileSync(file, 'utf8').matchAll(/^msgid "(.+)"$/gm)].map((m) => m[1]));

const committed = path.join(root, 'languages', 'ashbi-mega-menu.pot');
const generated = check ? path.join(tmp, 'domain.pot') : committed;
const warnings = makePot(generated, ['--domain=ashbi-mega-menu']);
makePot(path.join(tmp, 'all.pot'), ['--ignore-domain']);

const problems = [...warnings];
const domain = msgids(generated);
const missing = [...msgids(path.join(tmp, 'all.pot'))].filter((id) => !domain.has(id));
if (missing.length) problems.push(`Strings without the ashbi-mega-menu text domain:\n  ${missing.join('\n  ')}`);
if (check) {
	const before = msgids(committed);
	const added = [...domain].filter((id) => !before.has(id));
	const removed = [...before].filter((id) => !domain.has(id));
	if (added.length || removed.length) problems.push(`languages/ashbi-mega-menu.pot is out of date (${added.length} new, ${removed.length} removed). Run: npm run package && npm run i18n`);
}
fs.rmSync(tmp, { recursive: true, force: true });
if (problems.length) {
	console.error(problems.join('\n'));
	process.exit(1);
}
console.log(`${domain.size} strings, all in the ashbi-mega-menu domain${check ? '; the committed template is current' : '; wrote languages/ashbi-mega-menu.pot'}.`);
