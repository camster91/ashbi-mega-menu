const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const { test } = require('node:test');
const Zip = require('adm-zip');

const files = {
	'ashbi-mega-menu.php': Buffer.from('<?php // current release\n'),
	'assets/menu.js': Buffer.from('console.log("current release");\n'),
	'assets/icon.png': Buffer.from([0, 255, 128, 1]),
};
function archive(contents, repack = false) {
	const zip = new Zip({ noSort: true });
	const entries = Object.entries(contents);
	for (const [name, bytes] of repack ? entries.reverse() : entries) {
		zip.addFile(`ashbi-mega-menu/${name}`, bytes);
		const entry = zip.getEntry(`ashbi-mega-menu/${name}`);
		entry.header.time = new Date(repack ? '2025-03-04T00:00:00Z' : '2024-01-02T00:00:00Z');
		if (repack) entry.header.method = 0; // stored instead of deflated
	}
	if (repack) {
		zip.addFile('ashbi-mega-menu/assets/', Buffer.alloc(0));
		zip.addZipComment('Repackaged by the directory');
	}
	return zip.toBuffer();
}

// Exercise the unmodified CLI entry point with an isolated release and a
// fabricated directory API/ZIP. No requests reach WordPress.org.
function verify(publicZip, { localZip = archive(files), listedVersion = '1.0.4' } = {}) {
	const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'abmm-wporg-'));
	try {
		for (const name of ['scripts', 'dist', '.wordpress-org']) fs.mkdirSync(path.join(dir, name));
		fs.symlinkSync(path.resolve(__dirname, '../node_modules'), path.join(dir, 'node_modules'), 'dir');
		fs.copyFileSync(path.resolve(__dirname, '../scripts/verify-wporg.js'), path.join(dir, 'scripts/verify-wporg.js'));
		fs.writeFileSync(path.join(dir, 'dist/manifest.json'), JSON.stringify({ version: '1.0.4', files: Object.keys(files) }));
		if (localZip) fs.writeFileSync(path.join(dir, 'dist/ashbi-mega-menu-1.0.4.zip'), localZip);
		fs.writeFileSync(path.join(dir, '.wordpress-org/screenshot-1.png'), 'fixture');
		fs.writeFileSync(path.join(dir, 'public.zip'), publicZip);
		fs.writeFileSync(path.join(dir, 'fetch.cjs'), `
const fs = require('node:fs');
global.fetch = async (url) => {
 if (url.startsWith('https://api.wordpress.org/')) return { json: async () => ({
  version: ${JSON.stringify(listedVersion)}, banners: { high: 'banner' }, icons: { default: 'icon' }, screenshots: { 1: 'shot' }
 }) };
 if (url === 'https://downloads.wordpress.org/plugin/ashbi-mega-menu.1.0.4.zip')
  return { ok: true, arrayBuffer: async () => fs.readFileSync(__dirname + '/public.zip') };
 throw new Error('Unexpected network request: ' + url);
};
`);
		const result = spawnSync(process.execPath, ['--require', path.join(dir, 'fetch.cjs'), path.join(dir, 'scripts/verify-wporg.js')], { encoding: 'utf8' });
		assert.ifError(result.error);
		return result;
	} finally {
		fs.rmSync(dir, { recursive: true, force: true });
	}
}

test('identical release file bytes pass', () => assert.equal(verify(archive(files)).status, 0));
test('ZIP timestamps, compression, comments and directory entries may differ', () => {
	const repacked = archive(files, true);
	assert.ok(!repacked.equals(archive(files)), 'container bytes differ');
	const result = verify(repacked);
	assert.equal(result.status, 0, result.stderr);
});
for (const file of Object.keys(files)) {
	test(`same filenames with changed ${file} bytes fail`, () => {
		const result = verify(archive({ ...files, [file]: Buffer.from('stale or modified bytes') }));
		assert.equal(result.status, 1);
		assert.match(result.stderr, /Contents differ from the release/);
		assert.ok(result.stderr.includes(file));
	});
}
test('missing and extra public files fail', () => {
	const { 'assets/menu.js': removed, ...remaining } = files;
	const result = verify(archive({ ...remaining, 'unexpected.php': Buffer.from('extra') }));
	assert.equal(result.status, 1);
	assert.match(result.stderr, /Missing from the public ZIP/);
	assert.match(result.stderr, /Not in the release but in the public ZIP/);
});
test('a public file outside the plugin directory fails', () => {
	const zip = new Zip(archive(files));
	zip.addFile('outside.php', Buffer.from('unexpected'));
	const result = verify(zip.toBuffer());
	assert.equal(result.status, 1);
	assert.match(result.stderr, /Unexpected ZIP path/);
});
test('a missing local release ZIP fails closed', () => {
	const result = verify(archive(files), { localZip: null });
	assert.equal(result.status, 1);
	assert.match(result.stderr, /local release ZIP is missing/);
});
test('a local ZIP inconsistent with the manifest fails closed', () => {
	const result = verify(archive(files), { localZip: archive({ ...files, 'extra.php': Buffer.from('extra') }) });
	assert.equal(result.status, 1);
	assert.match(result.stderr, /local release ZIP and dist\/manifest.json disagree/);
});
test('listed version still must match', () => {
	const result = verify(archive(files), { listedVersion: '1.0.3' });
	assert.equal(result.status, 1);
	assert.match(result.stderr, /Listed version is 1.0.3, expected 1.0.4/);
});
