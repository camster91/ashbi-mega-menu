// Menus imported from Easy Mega Menu, shown through the old [easy_mega_menu]
// shortcode with Easy Mega Menu not installed: the classic header, its own
// styles and script, and the same mouse, keyboard and phone behaviour.
const { test, expect } = require('@playwright/test');

const classicPage = '/?pagename=e2e-classic';

function watchErrors(page) {
	const errors = [];
	page.on('pageerror', (error) => errors.push(error.message));
	page.on('console', (message) => {
		// The sample menu's logo and uploaded icon point at example.test, which
		// does not resolve here; only script errors matter for these checks.
		if (message.type() === 'error' && !/^Failed to load resource/.test(message.text())) errors.push(message.text());
	});
	return errors;
}

test.describe('classic menu on desktop', () => {
	test.use({ viewport: { width: 1280, height: 800 } });

	test('the old shortcode renders the classic header with only classic assets', async ({ page }) => {
		const errors = watchErrors(page);
		await page.goto(classicPage);
		const header = page.locator('header.emm-header[data-emm-id="main_nav"]');
		await expect(header).toHaveCount(1);
		await expect(header).toHaveAttribute('data-emm-initialized', 'true');
		await expect(page.locator('link#abmm-classic-css')).toHaveCount(1);
		await expect(page.locator('script#abmm-classic-js')).toHaveCount(1);
		// No Ashbi-style menu renders here, so Ashbi's own script stays away. (Its
		// stylesheet is queued site-wide because this test site also has Ashbi
		// menus; the PHP tests cover sites whose menus are all classic.)
		await expect(page.locator('script#abmm-frontend-js')).toHaveCount(0);
		// The bundled sprite draws the icons.
		const use = header.locator('.emm-icon--svg use').first();
		await expect(use).toHaveAttribute('href', /assets\/classic\/emm-sprite\.svg\?ver=[^#]+#emm-icon-/);
		expect(errors).toEqual([]);
	});

	test('hover opens the panel, categories switch and Escape closes', async ({ page }) => {
		const errors = watchErrors(page);
		await page.goto(classicPage);
		const header = page.locator('header.emm-header').first();
		const trigger = header.locator('[data-emm-mega-trigger]').first();
		const panel = page.locator('#' + (await trigger.getAttribute('aria-controls')));

		await trigger.hover();
		await expect(trigger).toHaveAttribute('aria-expanded', 'true');
		await expect(panel).toBeVisible();
		await expect(panel.locator('.emm-mega__panel.is-active')).toContainText('Invoicing');

		const people = panel.locator('.emm-mega__cat[data-emm-cat="people"]');
		await people.hover();
		await expect(people).toHaveAttribute('aria-expanded', 'true');
		await expect(panel.locator('.emm-mega__panel.is-active')).toContainText('Analytics');

		await page.keyboard.press('Escape');
		await expect(trigger).toHaveAttribute('aria-expanded', 'false');
		await expect(panel).toBeHidden();
		expect(errors).toEqual([]);
	});

	test('keyboard: Enter opens, Escape closes and returns focus', async ({ page }) => {
		await page.goto(classicPage);
		const trigger = page.locator('header.emm-header [data-emm-mega-trigger]').first();
		await trigger.focus();
		await page.keyboard.press('Enter');
		await expect(trigger).toHaveAttribute('aria-expanded', 'true');
		await page.keyboard.press('Escape');
		await expect(trigger).toHaveAttribute('aria-expanded', 'false');
		await expect(trigger).toBeFocused();
	});

	test('both shortcodes print the same classic header', async ({ page }) => {
		const markup = async (path) => {
			await page.goto(path);
			return page.locator('header.emm-header').first().evaluate((el) => {
				const copy = el.cloneNode(true);
				copy.removeAttribute('data-emm-initialized');
				return copy.outerHTML.replace(/emm-main_nav-\d+/g, 'emm-main_nav-N');
			});
		};
		expect(await markup('/?pagename=e2e-classic-ashbi')).toBe(await markup(classicPage));
	});
});

test.describe('classic menu in the editor', () => {
	test.use({ viewport: { width: 1440, height: 900 } });

	test('shows the Classic notice, keeps the look on save and previews the classic phone menu', async ({ page }) => {
		const errors = watchErrors(page);
		await page.goto('/wp-admin/admin.php?page=ashbi-mega-menu&edit=main_nav');
		await page.locator('[data-abmm-builder-section="design"]').click();
		await expect(page.locator('#abmm-render-mode')).toHaveValue('emm-classic');
		await expect(page.locator('.abmm-classic-notice').first()).toBeVisible();

		await page.locator('[data-abmm-preview-viewport="mobile"]').click();
		const frame = page.frameLocator('#abmm-preview iframe.abmm-preview-device');
		await expect(frame.locator('header.emm-header')).toHaveCount(1);
		await expect(frame.locator('link[href*="assets/classic/emm-frontend.css"]')).toHaveCount(1);
		await frame.locator('.emm-nav__toggle').click();
		await frame.locator('[data-emm-mega-trigger]').first().click();
		await frame.locator('.emm-mega__cat[data-emm-cat="people"]').click();
		// Selecting a category in the frame opens it in the editor.
		await expect(page.locator('#abmm-mega-editor')).toBeVisible();

		// Turning on the phone scroll fix and saving keeps the menu classic.
		await page.locator('[data-abmm-builder-section="design"]').click();
		const fix = page.locator('#abmm-classic-phone-scroll-fix');
		if (!(await fix.isChecked())) {
			await fix.check();
			const saved = page.waitForResponse((response) =>
				response.url().includes('admin-ajax.php') && (response.request().postData() || '').includes('action=abmm_save_menu'));
			await page.locator('.abmm-save-menu').first().click();
			expect((await (await saved).json()).success).toBe(true);
		}
		await page.goto('/?pagename=e2e-classic');
		await expect(page.locator('header.emm-header[data-emm-id="main_nav"]')).toHaveClass(/\bemm-phone-scroll-fix\b/);
		expect(errors).toEqual([]);
	});
});

test.describe('classic menu on a phone', () => {
	test.use({ viewport: { width: 360, height: 800 }, hasTouch: true });

	test('drawer opens, drills into a category, goes back and closes', async ({ page }) => {
		const errors = watchErrors(page);
		await page.goto(classicPage);
		const header = page.locator('header.emm-header').first();
		const toggle = header.locator('.emm-nav__toggle');
		await toggle.click();
		await expect(toggle).toHaveAttribute('aria-expanded', 'true');
		await expect(header.locator('.emm-nav__drawer')).toBeVisible();

		await header.locator('[data-emm-mega-trigger]').first().click();
		const finance = header.locator('.emm-mega__cat[data-emm-cat="finance"]');
		await expect(finance).toBeVisible();
		await finance.click();
		await expect(header.locator('a.emm-mega__col-link', { hasText: 'Invoicing' })).toBeVisible();

		const back = header.locator('[data-emm-back]').first();
		await back.click();
		await expect(finance).toBeVisible();

		// While drilled in, the drawer covers the bar, so its own close button is the one in view.
		await header.locator('[data-emm-drawer-close]:visible, .emm-nav__toggle:visible').first().click();
		await expect(toggle).toHaveAttribute('aria-expanded', 'false');
		expect(errors).toEqual([]);
	});
});
