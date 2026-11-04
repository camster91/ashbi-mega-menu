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

	test('a click after hover pins the panel, and tabbing out closes it', async ({ page }) => {
		await page.goto(classicPage);
		const trigger = page.locator('header.emm-header [data-emm-mega-trigger]').first();
		await trigger.hover();
		await expect(trigger).toHaveAttribute('aria-expanded', 'true');
		await trigger.click();
		await expect(trigger).toHaveAttribute('aria-expanded', 'true');
		await page.mouse.move(5, 790);
		await page.waitForTimeout(500);
		await expect(trigger).toHaveAttribute('aria-expanded', 'true');
		await trigger.click();
		await expect(trigger).toHaveAttribute('aria-expanded', 'false');

		await trigger.focus();
		await page.keyboard.press('Enter');
		await expect(trigger).toHaveAttribute('aria-expanded', 'true');
		await page.evaluate(() => {
			const outside = document.createElement('button');
			outside.id = 'e2e-outside';
			document.body.appendChild(outside);
			outside.focus();
		});
		await expect(trigger).toHaveAttribute('aria-expanded', 'false');
	});

	test('the fixes stylesheet loads with the classic stylesheet', async ({ page }) => {
		await page.goto(classicPage);
		await expect(page.locator('link#abmm-classic-fixes-css')).toHaveCount(1);
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
		// Like Ashbi menus, the phone preview reopens the drawer itself once the
		// frame has loaded; open it by hand only if that has not happened.
		const frameHeader = frame.locator('header.emm-header');
		const openedByPreview = await expect(frameHeader).toHaveClass(/\bis-mobile-open\b/, { timeout: 5000 }).then(() => true, () => false);
		if (!openedByPreview) await frame.locator('.emm-nav__toggle').click();
		await expect(frameHeader).toHaveClass(/\bis-mobile-open\b/);
		await frame.locator('[data-emm-mega-trigger]').first().click();
		await frame.locator('.emm-mega__cat[data-emm-cat="people"]').click();
		// Selecting a category in the frame opens it in the editor.
		await expect(page.locator('#abmm-mega-editor')).toBeVisible();

		// An edit re-renders the frame; the open drawer comes back as it was.
		await page.locator('[data-abmm-builder-section="design"]').click();
		const label = page.locator('#abmm-classic-toggle-label');
		await label.fill('Products');
		await expect(frame.locator('header.emm-header')).toHaveClass(/\bis-mobile-open\b/);
		await expect(frame.locator('.emm-nav__list > .emm-nav__item.is-open')).toHaveCount(1);

		// Setting a phone button label and saving keeps the menu classic.
		const saved = page.waitForResponse((response) =>
			response.url().includes('admin-ajax.php') && (response.request().postData() || '').includes('action=abmm_save_menu'));
		await page.locator('.abmm-save-menu').first().click();
		expect((await (await saved).json()).success).toBe(true);
		await page.goto('/?pagename=e2e-classic');
		await expect(page.locator('header.emm-header[data-emm-id="main_nav"] .emm-nav__toggle')).toHaveAttribute('aria-label', /^Products: /);
		expect(errors).toEqual([]);
	});
});

test.describe('classic menu desktop preview', () => {
	test.use({ viewport: { width: 1440, height: 900 } });

	test('shows the real Classic markup, selects what you click and keeps the open panel through an edit', async ({ page }) => {
		const errors = watchErrors(page);
		await page.goto('/wp-admin/admin.php?page=ashbi-mega-menu&edit=main_nav');
		const frame = page.frameLocator('#abmm-preview iframe.abmm-preview-device');
		await expect(frame.locator('header.emm-header')).toHaveCount(1);
		await expect(frame.locator('link[href*="assets/classic/emm-fixes.css"]')).toHaveCount(1);
		await expect(page.locator('.abmm-classic-preview-note')).toBeVisible();
		await expect(page.locator('.abmm-classic-preview-fallback')).toBeHidden();

		const trigger = frame.locator('[data-emm-mega-trigger]').first();
		await trigger.click();
		await expect(trigger).toHaveAttribute('aria-expanded', 'true');
		await frame.locator('.emm-mega__cat[data-emm-cat="people"]').click();
		await expect(page.locator('#abmm-mega-editor')).toBeVisible();

		const link = frame.locator('.emm-nav__item.is-open [data-abmm-link-id] a:visible').first();
		await link.click();
		await expect(frame.locator('.abmm-canvas-selected')).toHaveCount(1);

		// An edit re-renders the frame; the open dropdown comes back. Nothing is saved.
		await page.locator('[data-abmm-builder-section="design"]').click();
		const label = page.locator('#abmm-classic-toggle-label');
		await label.fill('Menu');
		await expect(frame.locator('.emm-nav__toggle-label')).toHaveCount(1);
		await expect(frame.locator('.emm-nav__list > .emm-nav__item.is-open')).toHaveCount(1);
		await expect(label).toBeFocused();
		await label.fill('');
		await expect(frame.locator('.emm-nav__toggle-label')).toHaveCount(0);
		expect(errors).toEqual([]);
	});

	test('falls back to the built-in preview and says so when the frame cannot load', async ({ page }) => {
		await page.route('**/admin-ajax.php', (route) => ((route.request().postData() || '').includes('abmm_preview_markup') ? route.abort() : route.continue()));
		await page.goto('/wp-admin/admin.php?page=ashbi-mega-menu&edit=main_nav');
		await expect(page.locator('#abmm-preview .abmm-nav')).toBeVisible();
		await expect(page.locator('#abmm-preview iframe.abmm-preview-device')).toHaveCount(0);
		await expect(page.locator('.abmm-classic-preview-fallback')).toBeVisible();
		await expect(page.locator('.abmm-classic-preview-note')).toBeHidden();
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

	test('the page is inert while the drawer is open, and a tap on a link closes it', async ({ page }) => {
		await page.goto(classicPage);
		const header = page.locator('header.emm-header').first();
		const toggle = header.locator('.emm-nav__toggle');
		await toggle.click();
		await expect(header.locator('.emm-nav__drawer')).toHaveAttribute('role', 'dialog');
		await header.locator('[data-emm-mega-trigger]').first().click();
		await expect(header.locator('[data-emm-back]').first()).toHaveAttribute('aria-label', /./);
		await header.locator('.emm-mega__cat[data-emm-cat="finance"]').click();
		const link = header.locator('a.emm-mega__col-link', { hasText: 'Invoicing' });
		await expect(link).toBeVisible();
		await link.evaluate((element) => element.setAttribute('href', '#e2e-target'));
		await link.click();
		await expect(toggle).toHaveAttribute('aria-expanded', 'false');
		await expect(page.locator('html')).not.toHaveClass(/\bemm-drawer-open\b/);
	});
});
