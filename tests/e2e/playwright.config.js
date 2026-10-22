// Browser tests for the editor and the public menu in Chromium, Firefox and WebKit (Safari's engine).
// They need a disposable WordPress site seeded with tests/e2e/seed.php.
const { defineConfig, devices } = require('@playwright/test');

const chromiumPath = process.env.ABMM_CHROMIUM_PATH;

module.exports = defineConfig({
	testDir: __dirname,
	testMatch: '*.spec.js',
	// One browser at a time: every project edits its own menu, but they share one site.
	workers: 1,
	fullyParallel: false,
	retries: 0,
	reporter: process.env.CI ? [['list'], ['html', { open: 'never', outputFolder: '../../playwright-report' }]] : 'list',
	outputDir: '../../test-results',
	globalSetup: require.resolve('./global-setup.js'),
	use: {
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8888',
		storageState: require('node:path').join(__dirname, '.auth', 'admin.json'),
		screenshot: 'only-on-failure',
		trace: 'retain-on-failure',
	},
	projects: [
		{ name: 'chromium', use: { ...devices['Desktop Chrome'], ...(chromiumPath ? { launchOptions: { executablePath: chromiumPath } } : {}) } },
		{ name: 'firefox', use: { ...devices['Desktop Firefox'] } },
		{ name: 'webkit', use: { ...devices['Desktop Safari'] } },
	],
});
