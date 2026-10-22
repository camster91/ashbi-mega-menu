// Log in once and save the admin session for every browser project.
const fs = require('node:fs');
const path = require('node:path');
const { request } = require('@playwright/test');

module.exports = async (config) => {
	const baseURL = config.projects[0].use.baseURL;
	const file = path.join(__dirname, '.auth', 'admin.json');
	fs.mkdirSync(path.dirname(file), { recursive: true });
	const context = await request.newContext({ baseURL });
	// WordPress refuses a login without its test cookie.
	await context.get('/wp-login.php');
	const response = await context.post('/wp-login.php', {
		form: {
			log: process.env.WP_USERNAME || 'admin',
			pwd: process.env.WP_PASSWORD || 'password',
			'wp-submit': 'Log In',
			redirect_to: baseURL + '/wp-admin/',
			testcookie: '1',
		},
	});
	if (!response.url().includes('/wp-admin')) {
		throw new Error('WordPress login failed for the browser tests. Check WP_USERNAME and WP_PASSWORD.');
	}
	await context.storageState({ path: file });
	await context.dispose();
};
