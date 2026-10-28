// Compatibility journeys for the CI "compat" job: a classic theme with a
// widget placement, an Elementor page, and page cache + script optimization +
// object cache. ABMM_E2E_SETUP names the setup; without it these tests skip.
const { test, expect } = require('@playwright/test');

const setup = process.env.ABMM_E2E_SETUP || '';
const visitor = { storageState: { cookies: [], origins: [] } };

function watchErrors(page) {
	const errors = [];
	page.on('pageerror', (error) => errors.push(error.message));
	page.on('console', (message) => {
		if (message.type() === 'error') errors.push(message.text());
	});
	return errors;
}

// Desktop: one click opens, a second closes. A menu bound twice (for example
// by an optimizer loading the script twice) would toggle twice and stay shut.
async function desktopWorks(page, header) {
	const trigger = header.locator('[data-abmm-mega-trigger]').first();
	await trigger.click();
	await expect(trigger).toHaveAttribute('aria-expanded', 'true');
	await expect(page.locator('#' + (await trigger.getAttribute('aria-controls')))).toBeVisible();
	await trigger.click();
	await expect(trigger).toHaveAttribute('aria-expanded', 'false');
}

async function phoneWorks(page, header) {
	await header.locator('.abmm-nav__toggle').click();
	await expect(header).toHaveClass(/is-mobile-open/);
	await header.locator('[data-abmm-mega-trigger]').first().click();
	await expect(header).toHaveAttribute('data-drill-level', '2');
	await page.keyboard.press('Escape');
	await page.keyboard.press('Escape');
	await expect(header).not.toHaveClass(/is-mobile-open/);
}

test.describe(`compatibility: ${setup || 'none'}`, () => {
	test.skip(!setup, 'Set ABMM_E2E_SETUP to run the compatibility journeys.');
	test.use(visitor);

	const path = setup === 'elementor' ? '/?pagename=e2e-elementor' : '/?pagename=e2e-chromium';

	test('a visitor can use the menu on desktop and on a phone', async ({ page }) => {
		const errors = watchErrors(page);
		await page.setViewportSize({ width: 1280, height: 800 });
		const first = await page.goto(path);
		expect(first.status()).toBe(200);
		const header = page.locator('.abmm-header').first();
		await expect(header).toBeVisible();
		if (setup === 'elementor') await expect(page.locator('.elementor .abmm-header')).toHaveCount(1);
		await desktopWorks(page, header);

		await page.setViewportSize({ width: 390, height: 844 });
		await page.goto(path);
		await phoneWorks(page, page.locator('.abmm-header').first());
		expect(errors).toEqual([]);
	});

	test('setup-specific placement', async ({ page }) => {
		const errors = watchErrors(page);
		await page.setViewportSize({ width: 1280, height: 800 });
		if (setup === 'classic') {
			// The widget placement renders a second, independent menu.
			await page.goto(path);
			const menus = page.locator('.abmm-header');
			await expect(menus).toHaveCount(2);
			const widget = page.locator('.widget .abmm-header, .widget-area .abmm-header, footer .abmm-header').first();
			await widget.scrollIntoViewIfNeeded();
			await expect(widget).toBeVisible();
			// A narrow widget area shows the phone-style toggle; either layout must open.
			const toggle = widget.locator('.abmm-nav__toggle');
			if (await toggle.isVisible()) await phoneWorks(page, widget);
			else await desktopWorks(page, widget);
			const ids = await menus.evaluateAll((els) => els.map((el) => el.id));
			expect(new Set(ids).size, 'each placement has unique IDs').toBe(ids.length);
		} else if (setup === 'cache') {
			// The second visit is served from the page cache, with optimized scripts.
			await page.goto(path);
			const cached = await page.goto(path);
			const html = await cached.text();
			expect(html, 'served by Cache Enabler').toMatch(/Cache Enabler/i);
			expect(html, 'scripts aggregated by Autoptimize').toMatch(/autoptimize/i);
			await desktopWorks(page, page.locator('.abmm-header').first());
		} else {
			await page.goto(path);
			await expect(page.locator('.abmm-header')).toHaveCount(1);
		}
		expect(errors).toEqual([]);
	});
});
