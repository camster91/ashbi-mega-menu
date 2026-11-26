#!/usr/bin/env node
// Builds the Ashbi UI kit for one plugin.
//   node kit/build.js --prefix ashbpg --out ../ashbi-portfolio-grid/assets/ashbi-ui
// Every "__P__" in kit/src becomes the plugin's own prefix, so two Ashbi
// plugins on one site each load their own copy without clashing.
'use strict';
const fs = require('node:fs');
const path = require('node:path');

const KIT_VERSION = '1.0.0';
const SOURCES = ['front.css', 'admin.css', 'studio.js'];

function parseArgs(argv) {
	const args = {};
	for (let i = 0; i < argv.length; i++) {
		if (argv[i] === '--prefix') args.prefix = argv[++i];
		else if (argv[i] === '--out') args.out = argv[++i];
	}
	return args;
}

function build(prefix, out) {
	if (!/^[a-z][a-z0-9]{2,11}$/.test(prefix || '')) {
		throw new Error('Prefix must be 3–12 lowercase letters or digits, starting with a letter.');
	}
	if (!out) throw new Error('Missing --out folder.');
	fs.mkdirSync(out, { recursive: true });
	const written = [];
	for (const file of SOURCES) {
		const source = fs.readFileSync(path.join(__dirname, 'src', file), 'utf8');
		const result = source.split('__P__').join(prefix).split('__VERSION__').join(KIT_VERSION);
		if (/__P__|__VERSION__/.test(result)) throw new Error('Unreplaced token in ' + file);
		fs.writeFileSync(path.join(out, file), result);
		written.push(path.join(out, file));
	}
	fs.writeFileSync(path.join(out, 'VERSION'), KIT_VERSION + '\n');
	return written;
}

if (require.main === module) {
	try {
		const { prefix, out } = parseArgs(process.argv.slice(2));
		const files = build(prefix, out && path.resolve(out));
		console.log('Ashbi UI kit ' + KIT_VERSION + ' built for "' + prefix + '":\n  ' + files.join('\n  '));
	} catch (error) {
		console.error(error.message);
		process.exit(1);
	}
}

module.exports = { build, KIT_VERSION, SOURCES };
