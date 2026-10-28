// After publishing, check what WordPress.org actually serves:
// the listed version, banner, icon and screenshots, and that the public ZIP
// has the same files as the release (dist/manifest.json from npm run package).
//   node scripts/verify-wporg.js            check now
//   node scripts/verify-wporg.js --wait     retry for up to 30 minutes (the directory updates with a delay)
const fs = require('node:fs');
const path = require('node:path');
const Zip = require('adm-zip');

const root = path.resolve(__dirname, '..');
const slug = 'ashbi-mega-menu';
const manifestPath = path.join(root, 'dist', 'manifest.json');
const wait = process.argv.includes('--wait');

async function check() {
	if (!fs.existsSync(manifestPath)) throw new Error('Run npm run package first: dist/manifest.json is missing.');
	const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
	const api = `https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&slug=${slug}&fields[banners]=1&fields[icons]=1&fields[screenshots]=1`;
	const info = await (await fetch(api)).json();
	if (info.error) return [`Not listed on WordPress.org yet (${info.error}).`];

	const problems = [];
	if (info.version !== manifest.version) problems.push(`Listed version is ${info.version}, expected ${manifest.version}.`);
	if (!info.banners || !(info.banners.high || info.banners.low)) problems.push('No banner is shown.');
	if (!info.icons || !Object.values(info.icons).some(Boolean)) problems.push('No icon is shown.');
	const expectedShots = fs.readdirSync(path.join(root, '.wordpress-org')).filter((f) => /^screenshot-\d+\./.test(f)).length;
	const shots = Object.keys(info.screenshots || {}).length;
	if (shots < expectedShots) problems.push(`${shots} screenshots shown, expected ${expectedShots}.`);

	const zipUrl = `https://downloads.wordpress.org/plugin/${slug}.${manifest.version}.zip`;
	const response = await fetch(zipUrl);
	if (!response.ok) {
		problems.push(`${zipUrl} returned ${response.status}.`);
		return problems;
	}
	const served = new Zip(Buffer.from(await response.arrayBuffer())).getEntries()
		.filter((e) => !e.isDirectory)
		.map((e) => e.entryName.replace(/^ashbi-mega-menu\//, ''));
	const expected = new Set(manifest.files);
	const missing = manifest.files.filter((f) => !served.includes(f));
	// WordPress.org may add nothing but its own metadata; anything else is a surprise.
	const extra = served.filter((f) => !expected.has(f));
	if (missing.length) problems.push(`Missing from the public ZIP:\n  ${missing.join('\n  ')}`);
	if (extra.length) problems.push(`Not in the release but in the public ZIP:\n  ${extra.join('\n  ')}`);
	return problems;
}

(async () => {
	const deadline = Date.now() + (wait ? 30 * 60 * 1000 : 0);
	for (;;) {
		const problems = await check();
		if (!problems.length) {
			console.log('WordPress.org serves the expected version, banner, icon, screenshots and ZIP contents.');
			return;
		}
		if (Date.now() >= deadline) {
			console.error(problems.join('\n'));
			process.exit(1);
		}
		console.log('Not matching yet, retrying in 60 s:\n  ' + problems.join('\n  '));
		await new Promise((resolve) => setTimeout(resolve, 60 * 1000));
	}
})().catch((error) => {
	console.error(error.message);
	process.exit(1);
});
