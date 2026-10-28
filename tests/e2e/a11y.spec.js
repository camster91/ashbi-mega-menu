// Accessibility regressions in every browser engine: automated axe-core rules
// on the editor and the public menu, keyboard behaviour that was fixed, and
// reflow at 400% zoom. Automated checks catch a subset of barriers; see
// docs/accessibility.md for the manual matrix.
const fs = require('node:fs');
const path = require('node:path');
const { test, expect } = require('@playwright/test');

const AXE = fs.readFileSync(require.resolve('axe-core/axe.min.js'), 'utf8');
const pagePath = (browserName) => `/?pagename=e2e-${browserName}`;
const RULES = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'best-practice'];

async function axe(page, include) {
	// Measure colours after fades finish, not halfway through them.
	await page.waitForFunction(() => document.getAnimations().every((a) => a.playState !== 'running'));
	await page.addScriptTag({ content: AXE });
	const violations = await page.evaluate(async ({ include, rules }) => {
		const result = await window.axe.run({ include: [include] }, { runOnly: rules });
		return result.violations.map((v) => ({
			id: v.id,
			// WordPress core's admin bar and menu are outside the plugin.
			nodes: v.nodes.map((n) => n.target.join(' ')).filter((t) => !/wpadminbar|wp-admin-bar|adminmenu|collapse-button|wpfooter/.test(t)),
		})).filter((v) => v.nodes.length);
	}, { include, rules: RULES });
	expect(violations, JSON.stringify(violations, null, 2)).toEqual([]);
}

async function noHorizontalScroll(page) {
	const widths = await page.evaluate(() => [document.documentElement.scrollWidth, document.documentElement.clientWidth]);
	expect(widths[0], 'page scrolls sideways').toBeLessThanOrEqual(widths[1] + 1);
}

test.describe('public menu', () => {
	test('desktop panel and phone drawer pass axe', async ({ page, browserName }) => {
		await page.setViewportSize({ width: 1280, height: 800 });
		await page.goto(pagePath(browserName));
		const header = page.locator('.abmm-header').first();
		await axe(page, '.abmm-header');
		await header.locator('[data-abmm-mega-trigger]').first().click();
		await axe(page, '.abmm-header');

		await page.setViewportSize({ width: 390, height: 844 });
		await page.reload();
		await page.locator('.abmm-nav__toggle').first().click();
		await axe(page, '.abmm-header');
		await page.locator('.abmm-header [data-abmm-mega-trigger]').first().click();
		await axe(page, '.abmm-header');
	});

	test('the menu adds no headings to the page outline', async ({ page, browserName }) => {
		await page.goto(pagePath(browserName));
		await expect(page.locator('.abmm-header :is(h1, h2, h3, h4, h5, h6)')).toHaveCount(0);
		// Column titles still label their lists.
		const labelled = page.locator('.abmm-header .abmm-mega__col-list[aria-labelledby]').first();
		if (await labelled.count()) {
			const id = await labelled.getAttribute('aria-labelledby');
			await expect(page.locator('#' + id)).toHaveClass(/abmm-mega__col-title/);
		}
	});

	test.describe('at 400% zoom (320 × 256) as a visitor', () => {
		test.use({ storageState: { cookies: [], origins: [] }, viewport: { width: 320, height: 256 } });

		test('reflows, and the open drawer has a visible close button', async ({ page, browserName }) => {
			await page.goto(pagePath(browserName));
			await noHorizontalScroll(page);
			const header = page.locator('.abmm-header').first();
			await header.locator('.abmm-nav__toggle').click();
			await expect(header).toHaveClass(/is-mobile-open/);
			const close = header.locator('.abmm-nav__drawer-head .abmm-nav__drawer-close');
			await expect(close).toBeVisible();
			// The footer CTA scrolls with the menu instead of covering it.
			const foot = header.locator('.abmm-nav__drawer-foot');
			if (await foot.count()) await expect(foot).toHaveCSS('position', 'static');
			await close.click();
			await expect(header).not.toHaveClass(/is-mobile-open/);
		});
	});
});

test.describe('editor', () => {
	test.use({ viewport: { width: 1440, height: 900 } });

	async function openEditor(page, browserName) {
		await page.goto(pagePath(browserName));
		const menuId = await page.locator('.abmm-header').first().getAttribute('data-abmm-id');
		await page.goto(`/wp-admin/admin.php?page=ashbi-mega-menu&edit=${encodeURIComponent(menuId)}`);
		await expect(page.locator('#abmm-preview .abmm-nav')).toBeVisible();
	}

	test('dashboard, editor tabs and content editor pass axe', async ({ page, browserName }) => {
		await page.goto('/wp-admin/admin.php?page=ashbi-mega-menu');
		await axe(page, '#wpbody-content');
		await openEditor(page, browserName);
		await axe(page, '#wpbody-content');
		for (const section of ['design', 'settings']) {
			await page.locator(`[data-abmm-builder-section="${section}"]`).click();
			await axe(page, '#abmm-builder-sidebar');
		}
		await page.locator('[data-abmm-builder-section="content"]').click();
		await page.locator('#abmm-nav-items .abmm-edit-mega').first().click();
		await expect(page.locator('#abmm-mega-editor')).toBeVisible();
		await axe(page, '#wpbody-content');
	});

	test('keyboard: content editor takes focus, zoom menu is a menu button', async ({ page, browserName }) => {
		await openEditor(page, browserName);

		// Opening the content editor moves focus to its title; closing returns it.
		const opener = page.locator('#abmm-nav-items .abmm-edit-mega').first();
		await opener.focus();
		await page.keyboard.press('Enter');
		await expect(page.locator('#abmm-mega-editor-title')).toBeFocused();
		await page.locator('#abmm-mega-editor .abmm-close-mega-editor').focus();
		await page.keyboard.press('Enter');
		await expect(opener).toBeFocused();

		// Enter opens the zoom menu on its first preset; Escape closes it and returns focus.
		const toggle = page.locator('.abmm-preview-zoom__value');
		await toggle.focus();
		await page.keyboard.press('Enter');
		await expect(toggle).toHaveAttribute('aria-expanded', 'true');
		await expect(page.locator('#abmm-zoom-presets [role="menuitem"]').first()).toBeFocused();
		await page.keyboard.press('Escape');
		await expect(toggle).toHaveAttribute('aria-expanded', 'false');
		await expect(toggle).toBeFocused();
		// ArrowUp opens on the last item.
		await page.keyboard.press('ArrowUp');
		await expect(page.locator('#abmm-zoom-presets [role="menuitem"]').last()).toBeFocused();
		await page.keyboard.press('Escape');
	});

	test('dashboard reflows at 200% and 400% zoom', async ({ page }) => {
		for (const viewport of [{ width: 720, height: 450 }, { width: 320, height: 256 }]) {
			await page.setViewportSize(viewport);
			await page.goto('/wp-admin/admin.php?page=ashbi-mega-menu');
			await noHorizontalScroll(page);
		}
	});
});
