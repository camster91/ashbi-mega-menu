// End-to-end checks in every browser engine: the editor saves, and the public
// menu opens and closes with a mouse, a keyboard and on a phone-sized screen.
const { test, expect } = require('@playwright/test');

// pagename works with every permalink setting.
const pagePath = (browserName) => `/?pagename=e2e-${browserName}`;

function watchErrors(page) {
	const errors = [];
	page.on('pageerror', (error) => errors.push(error.message));
	page.on('console', (message) => {
		if (message.type() === 'error') errors.push(message.text());
	});
	return errors;
}

test.describe('public menu on desktop', () => {
	test.use({ viewport: { width: 1280, height: 800 } });

	test('hover opens a panel and a following click keeps it open', async ({ page, browserName }) => {
		const errors = watchErrors(page);
		await page.goto(pagePath(browserName));
		const header = page.locator('.abmm-header').first();
		const trigger = header.locator('[data-abmm-mega-trigger]').first();
		const panel = page.locator('#' + (await trigger.getAttribute('aria-controls')));

		await expect(trigger).toHaveAttribute('aria-expanded', 'false');
		await trigger.hover();
		await expect(trigger).toHaveAttribute('aria-expanded', 'true');
		await expect(panel).toBeVisible();

		await trigger.click();
		await expect(trigger).toHaveAttribute('aria-expanded', 'true');
		await expect(panel).toBeVisible();
		await expect(panel).toContainText(/\S/);

		await trigger.click();
		await expect(trigger).toHaveAttribute('aria-expanded', 'false');
		expect(errors).toEqual([]);
	});

	test('keyboard opens a panel and Escape closes it and returns focus', async ({ page, browserName }) => {
		await page.goto(pagePath(browserName));
		const trigger = page.locator('.abmm-header [data-abmm-mega-trigger]').first();
		const panel = page.locator('#' + (await trigger.getAttribute('aria-controls')));

		await trigger.focus();
		await page.keyboard.press('Enter');
		await expect(trigger).toHaveAttribute('aria-expanded', 'true');
		await expect(panel).toBeVisible();

		await page.keyboard.press('Escape');
		await expect(trigger).toHaveAttribute('aria-expanded', 'false');
		await expect(panel).toBeHidden();
		await expect(trigger).toBeFocused();
	});
});

test.describe('public menu on a phone-sized screen', () => {
	test.use({ viewport: { width: 390, height: 844 } });

	test('drawer opens, drills into a panel, goes back and closes', async ({ page, browserName }) => {
		const errors = watchErrors(page);
		await page.goto(pagePath(browserName));
		const header = page.locator('.abmm-header').first();
		const toggle = header.locator('.abmm-nav__toggle');
		const trigger = header.locator('[data-abmm-mega-trigger]').first();

		await toggle.click();
		await expect(toggle).toHaveAttribute('aria-expanded', 'true');
		await expect(header).toHaveClass(/is-mobile-open/);
		await expect(trigger).toBeVisible();

		await trigger.click();
		await expect(trigger).toHaveAttribute('aria-expanded', 'true');
		const back = header.locator('[data-abmm-back]:visible').first();
		await expect(back).toBeVisible();

		await back.click();
		await expect(trigger).toHaveAttribute('aria-expanded', 'false');

		await header.locator('[data-abmm-drawer-close]:visible').first().click();
		await expect(toggle).toHaveAttribute('aria-expanded', 'false');
		await expect(header).not.toHaveClass(/is-mobile-open/);
		await expect(toggle).toBeFocused();
		expect(errors).toEqual([]);
	});
});

test.describe('menu editor', () => {
	test.use({ viewport: { width: 1440, height: 900 } });

	test('edits, saves and reloads a menu label', async ({ page, browserName }) => {
		await page.goto(pagePath(browserName));
		const menuId = await page.locator('.abmm-header').first().getAttribute('data-abmm-id');
		expect(menuId).toBeTruthy();

		const errors = watchErrors(page);
		const editorUrl = `/wp-admin/admin.php?page=ashbi-mega-menu&edit=${encodeURIComponent(menuId)}`;
		await page.goto(editorUrl);
		const firstLabel = page.locator('#abmm-nav-items .abmm-nav-label').first();
		await expect(firstLabel).toBeVisible();

		await page.locator('[data-abmm-builder-section="design"]').click();
		await expect(page.locator('[data-abmm-builder-section="design"]')).toHaveAttribute('aria-selected', 'true');
		await page.locator('[data-abmm-builder-section="content"]').click();

		const label = `Home ${browserName}`;
		await firstLabel.fill(label);
		const saved = page.waitForResponse((response) =>
			response.url().includes('admin-ajax.php') && (response.request().postData() || '').includes('action=abmm_save_menu'));
		await page.locator('.abmm-save-menu').first().click();
		const response = await saved;
		expect((await response.json()).success).toBe(true);

		await page.goto(editorUrl);
		await expect(page.locator('#abmm-nav-items .abmm-nav-label').first()).toHaveValue(label);

		await page.goto(pagePath(browserName));
		await expect(page.locator('.abmm-header .abmm-nav__list').first()).toContainText(label);
		expect(errors).toEqual([]);
	});
});
