// Right-to-left and long translations. The seed installs a test-only
// must-use plugin (tests/e2e/mu-plugins/abmm-test-locale.php) that switches
// the site to RTL, or makes every plugin string about 40% longer, from a cookie.
const { test, expect } = require('@playwright/test');

const pagePath = (browserName) => `/?pagename=e2e-${browserName}`;

async function useLocale(context, value, baseURL) {
	await context.addCookies([{ name: 'abmm_test_locale', value, url: baseURL }]);
}

async function noHorizontalScroll(page) {
	const [scroll, client] = await page.evaluate(() => [document.documentElement.scrollWidth, document.documentElement.clientWidth]);
	expect(scroll, 'page scrolls sideways').toBeLessThanOrEqual(client + 1);
}

test.describe('right to left', () => {
	test('desktop panel mirrors without overlapping, phone drawer works', async ({ page, context, browserName, baseURL }) => {
		await useLocale(context, 'rtl', baseURL);
		await page.setViewportSize({ width: 1280, height: 800 });
		await page.goto(pagePath(browserName));
		await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
		await noHorizontalScroll(page);
		const header = page.locator('.abmm-header').first();
		await header.locator('[data-abmm-mega-trigger]').first().click();
		const category = header.locator('.abmm-mega__section.is-open .abmm-mega__cat').first();
		const panel = header.locator('.abmm-mega__section.is-open .abmm-mega__panel').first();
		await expect(panel).toBeVisible();
		const [c, p] = [await category.boundingBox(), await panel.boundingBox()];
		// The category list is on the start side (right) and does not cover the links.
		expect(c.x + c.width / 2, 'categories on the right').toBeGreaterThan(p.x + p.width / 2);
		expect(c.x, 'categories do not overlap the links').toBeGreaterThanOrEqual(p.x + p.width - 1);

		await page.setViewportSize({ width: 390, height: 844 });
		await page.reload();
		await header.locator('.abmm-nav__toggle').click();
		await header.locator('[data-abmm-mega-trigger]').first().click();
		await expect(header).toHaveAttribute('data-drill-level', '2');
		await noHorizontalScroll(page);
	});

	test('editor and dashboard have no sideways scrolling', async ({ page, context, browserName, baseURL }) => {
		await useLocale(context, 'rtl', baseURL);
		await page.setViewportSize({ width: 1440, height: 900 });
		await page.goto('/wp-admin/admin.php?page=ashbi-mega-menu');
		await noHorizontalScroll(page);
		await page.goto(pagePath(browserName));
		const menuId = await page.locator('.abmm-header').first().getAttribute('data-abmm-id');
		await page.goto(`/wp-admin/admin.php?page=ashbi-mega-menu&edit=${encodeURIComponent(menuId)}`);
		await expect(page.locator('#abmm-preview .abmm-nav')).toBeVisible();
		await noHorizontalScroll(page);
	});
});

test.describe('long translations', () => {
	test('labels wrap instead of overflowing', async ({ page, context, browserName, baseURL }) => {
		await useLocale(context, 'long', baseURL);
		for (const width of [1440, 1024]) {
			await page.setViewportSize({ width, height: 900 });
			await page.goto('/wp-admin/admin.php?page=ashbi-mega-menu');
			await expect(page.locator('.abmm-page-tabs, .abmm-dash-header').first()).toContainText('Über');
			await noHorizontalScroll(page);
			await page.goto(pagePath(browserName));
			const menuId = await page.locator('.abmm-header').first().getAttribute('data-abmm-id');
			await page.goto(`/wp-admin/admin.php?page=ashbi-mega-menu&edit=${encodeURIComponent(menuId)}`);
			await expect(page.locator('#abmm-preview .abmm-nav')).toBeVisible();
			await noHorizontalScroll(page);
			// No plugin button clips its text.
			const clipped = await page.evaluate(() => [...document.querySelectorAll('#wpbody-content .button, #wpbody-content button')]
				.filter((b) => b.offsetParent && b.scrollWidth > b.clientWidth + 2 && getComputedStyle(b).overflow !== 'visible')
				.map((b) => b.textContent.trim().slice(0, 40)));
			expect(clipped).toEqual([]);
		}
	});
});
