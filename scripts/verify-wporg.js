// After publishing, check what WordPress.org actually serves:
// the listed version, banner, icon and screenshots, and that the public ZIP
// has the same file bytes as the local release ZIP from npm run package.
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
	const releasePath = path.join(root, 'dist', `${slug}-${manifest.version}.zip`);
	if (!fs.existsSync(releasePath)) throw new Error('Run npm run package first: the local release ZIP is missing.');
	const release = zipFiles(fs.readFileSync(releasePath));
	if (release.size !== manifest.files.length || manifest.files.some((file) => !release.has(file))) {
		throw new Error('The local release ZIP and dist/manifest.json disagree. Run npm run package again.');
	}
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
	const served = zipFiles(Buffer.from(await response.arrayBuffer()));
	const expected = new Set(manifest.files);
	const missing = manifest.files.filter((f) => !served.has(f));
	const extra = [...served.keys()].filter((f) => !expected.has(f));
	// Compare uncompressed bytes, ignoring ZIP timestamps, compression and entry order.
	const changed = manifest.files.filter((f) => served.has(f) && !release.get(f).equals(served.get(f)));
	if (missing.length) problems.push(`Missing from the public ZIP:\n  ${missing.join('\n  ')}`);
	if (extra.length) problems.push(`Not in the release but in the public ZIP:\n  ${extra.join('\n  ')}`);
	if (changed.length) problems.push(`Contents differ from the release in the public ZIP:\n  ${changed.join('\n  ')}`);
	return problems;
}

function zipFiles(buffer) {
	const files = new Map();
	for (const entry of new Zip(buffer).getEntries()) {
		if (entry.isDirectory) continue;
		if (!entry.entryName.startsWith(`${slug}/`)) throw new Error(`Unexpected ZIP path: ${entry.entryName}`);
		const name = entry.entryName.slice(slug.length + 1);
		if (files.has(name)) throw new Error(`Duplicate ZIP file: ${name}`);
		files.set(name, entry.getData());
	}
	return files;
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
