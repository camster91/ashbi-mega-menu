const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const { test } = require('node:test');
const yaml = require('js-yaml');

const workflow = yaml.load(fs.readFileSync(path.join(__dirname, '../.github/workflows/deploy-wporg.yml'), 'utf8'));
const job = workflow.jobs.deploy;
const publishIndex = job.steps.findIndex((step) => step.name === 'Publish to WordPress.org SVN');
assert.ok(publishIndex > 0);
const preparation = job.steps.slice(0, publishIndex).filter((step) => step.run);

// Execute the actual workflow preparation with the runner's Bash flags, but
// replace external commands with deterministic failures and a command trace.
function prepare(failure = '') {
	const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'abmm-workflow-'));
	try {
		const bin = path.join(dir, 'bin');
		fs.mkdirSync(bin);
		const trace = path.join(dir, 'trace');
		for (const command of ['npm', 'git', 'node', 'unzip']) {
			fs.writeFileSync(path.join(bin, command), `#!/bin/bash
command="${command} $*"
echo "$command" >> "$TRACE"
if [[ "$command" == "$FAIL_COMMAND" ]]; then exit 23; fi
if [[ "$command" == 'node -p '* ]]; then echo 1.0.4; fi
`, { mode: 0o755 });
		}
		let status = 0;
		for (const step of preparation) {
			assert.ok(!step['continue-on-error'], 'preparation must block publication on failure');
			const result = spawnSync('bash', ['--noprofile', '--norc', '-e', '-o', 'pipefail', '-c',
				step.run.replaceAll('${{ github.event_name }}', 'workflow_dispatch')], {
				cwd: dir,
				env: { ...process.env, PATH: `${bin}:${process.env.PATH}`, TRACE: trace, FAIL_COMMAND: failure,
					VERSION: '1.0.4', GITHUB_ENV: path.join(dir, 'env') },
				encoding: 'utf8',
			});
			assert.ifError(result.error);
			status = result.status;
			if (status !== 0) break;
		}
		const commands = fs.readFileSync(trace, 'utf8').trim().split('\n');
		if (status === 0) commands.push('publish');
		return { status, commands };
	} finally {
		fs.rmSync(dir, { recursive: true, force: true });
	}
}

for (const failure of ['npm ci', 'node scripts/check-versions.js', 'npm run build:brand', 'npm run build:icons',
	'npm run build', 'git diff --exit-code -- build src assets', 'npm test', 'npm run test:php',
	'npm run package', 'unzip -q dist/ashbi-mega-menu-1.0.4.zip -d release']) {
	test(`${failure} failure stops preparation before publication`, () => {
		const { status, commands } = prepare(failure);
		assert.notEqual(status, 0, commands.join('\n'));
		assert.equal(commands.at(-1), failure, 'no later command may mask a failure');
		assert.ok(!commands.includes('publish'));
	});
}

test('successful preparation builds, tests and unpacks before publication', () => {
	const { status, commands } = prepare();
	assert.equal(status, 0);
	assert.deepEqual(commands.slice(-9), ['npm run build:brand', 'npm run build:icons', 'npm run build',
		'git diff --exit-code -- build src assets', 'npm test', 'npm run test:php', 'npm run package',
		'unzip -q dist/ashbi-mega-menu-1.0.4.zip -d release', 'publish']);
	assert.equal(job.steps[publishIndex].if, undefined, 'publishing uses the default success() condition');
	assert.equal(job.steps[publishIndex].with['dry-run'], '${{ env.DRY_RUN }}');
	assert.equal(workflow.on.workflow_dispatch.inputs['dry-run'].default, true);
});
