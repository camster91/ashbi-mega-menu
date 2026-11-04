# Accessibility test results

This page says what has been tested, how it was tested, and what is still open. WCAG is the main standard for making websites usable by people with disabilities. **The plugin does not claim to meet WCAG 2.1 AA.** That won't change until the manual checks listed below are done.

In short: automated tools and browser tests pass. **No one has yet tested the plugin with a real screen reader or on real touch devices.** Screen readers are tools that read a page out loud.

The steps for running each check are in [accessibility-compatibility-qa.md](accessibility-compatibility-qa.md).

The table below was last updated 2026-10-28 for issue #3, against the editor and public menu as of the `claude/a11y-audit` branch (WordPress 7.1.3, PHP 8.5, Twenty Twenty-Five, SQLite). Later fixes are listed at the end. They were tested in Chromium browser tests and on local copies of the menus only.

## Results

| Area | Method | Result |
| --- | --- | --- |
| Automated rules (WCAG 2.0/2.1 A and AA, axe best practices) | axe-core 4.14.0 in Chromium 156 and WebKit 27.2 (Playwright 1.64). Dashboard, editor (Content, Design, Settings), content editor, link popover, zoom menu, Place, Check, Products, Branding, Coverage; public menu closed, open on desktop, phone drawer, drilled level and search | **Pass** after the fixes below. Remaining reports are WordPress core's admin bar and menu, outside the plugin. Runs in CI on every pull request (`tests/e2e/a11y.spec.js`) in Chromium, Firefox and WebKit. |
| Keyboard only: editor | Playwright keyboard journeys, plus existing JSDOM tests for section tabs, reordering without drag, sheets and save/retry | **Pass.** Tabs (arrows, Home/End), add/reorder/remove, icon picker (focus moves in, Tab stays inside, Escape returns focus to the opener), zoom menu (menu-button pattern), content editor (focus moves to its title, back to the opener on close), unsaved-changes dialog, Ctrl/⌘ + S. |
| Keyboard only: public menu | Playwright in Chromium, Firefox and WebKit (CI), plus JSDOM | **Pass.** Desktop: Tab order, Enter/Space open, Escape closes and returns focus, focus leaving the menu closes it. Phone drawer: opening moves focus in, Tab stays inside, Back and Escape close the right level, closing returns focus to the toggle. Search has a label and a live result count. |
| Visible focus | Every Tab stop on the dashboard, editor, content editor, Products, Coverage, public desktop panel and phone drawer, comparing pixels with and without focus | **Pass**: every stop shows a change. The check was validated by stripping all focus styles, which it then flagged. |
| Names, roles, states | axe rules plus targeted assertions (expanded state on triggers, toggles and the zoom menu; labelled dialogs; labelled link lists) | **Pass** in automated checks. Not yet confirmed with a screen reader. |
| Live announcements | Inspection of live regions: save status (polite), load errors (alert), readiness summary, placement hint, icon search empty state, menu search result count, WordPress `wp.a11y.speak` | Present. **Not yet confirmed with a screen reader.** |
| 200% zoom (720 × 450) and 400% zoom (320 × 256) | Viewport emulation in Chromium and WebKit | **Pass** after the fixes below: no horizontal scrolling on the dashboard, editor, Products, Coverage or public page; the phone drawer is usable with a visible close button. |
| Windows high contrast | `forced-colors: active` emulation in Chromium, visible-focus check repeated | **Pass** in emulation after the fixes below: this row previously passed while the phone menu button's bars and the active category's marker were invisible, because the check only covered focus. **Not tested on Windows** with a real high-contrast theme. |
| Reduced motion | `prefers-reduced-motion: reduce` emulation | **Pass**: no transitions or animations longer than 20 ms in the public menu; canvas zoom and the inspector highlight respect it. |
| Colour contrast of built-in presets | axe on all five presets, desktop panel open with a link hovered, from hand-built markup | **Pass** after the fixes below. The earlier result covered closed and drawer states only, and missed hovered links, category descriptions and featured cards. Custom colours chosen in Design are the site owner's responsibility. The editor now warns when colours may be hard to read, but the warning does not stop a save and is not a full contrast check. |

## Pending

These need a person with the assistive technology or device. Until they are recorded here with a date and versions, treat them as untested:

- NVDA with Firefox and Chrome on Windows: editor and public menu.
- VoiceOver with Safari on macOS and iOS: editor and public menu.
- A real Windows high-contrast theme (emulation does not cover every system colour).
- Real touch devices (emulation is not a substitute).
- TalkBack with Chrome on Android (optional).

## Fixes made for issue #3

- **Landmarks:** the admin pages used `<header>`, `<main>` and `<footer>` inside WordPress's own main region, giving two main landmarks. The public menu used a `<header>` element, which HTML does not allow inside a theme's `<header>` and which created a nested banner landmark; its `<nav>` is the landmark now.
- **Heading outline:** the public menu inserted `h3`/`h4` headings into whatever page it was placed on, usually before the page's `h1`. Category and column titles are no longer headings; each column's link list is labelled by its title (`aria-labelledby`). Admin sub-headings now follow the page's heading levels.
- **Contrast:** muted admin text (#8892a6, 2.7–3.1:1) now uses the brand muted colour (≥ 5.2:1). On the public menu, the open top-level item mixes the accent toward the text colour (3.95:1 → above 4.5:1 with the default colours), and active category descriptions are no longer faded.
- **Keyboard:** the zoom readout follows the menu-button pattern (Enter, Space or ↓ open it on the first preset, ↑ on the last, Escape closes it from the button too). Opening the content editor moves focus to its title.
- **Reflow:** dashboard issue tooltips no longer extend past the screen edge on narrow screens. On short screens (400% zoom, landscape phones) the drawer's call-to-action scrolls with the menu instead of covering it, and when the drawer opens over the menu bar it shows its own close button.

## Later public menu fixes

- **High contrast:** the phone menu button keeps its bars and a border, and the active category keeps a marker.
- **Drawer:** page content outside the open drawer is inert, so assistive technology stays inside it. Opening a drilled-in screen announces its name and labels the group. Focus and scrolling stop short of the sticky Back row and call-to-action.
- **Contrast:** hovered link labels, category descriptions and featured card text and links lean toward the text colour on tinted surfaces.
- **Names and labels:** a logo that fails to load exposes its fallback text as the link's name. Back and Close use the translated labels. Category panels are groups rather than landmarks, and links without a destination have a link role with their disabled state.
- **Focus:** Escape only returns focus to the menu if it was already inside it, and hovering another category no longer hides the link a keyboard user is on.
- **Without scripts:** every link shows as a plain list, using `@media (scripting: none)`.

- **New tabs and logos:** links that open a new tab now say so to screen readers. A linked logo with no alt text and no title now uses the site name.

## Later Classic menu fixes

These apply to menus imported from Easy Mega Menu. They have not been tested with a screen reader either.

- **High contrast:** the phone menu button and the active category stay visible in emulation.
- **Keyboard:** a panel closes when focus leaves it, and it no longer covers the focused link. Escape only returns focus if it was already in the menu.
- **Drawer:** the page behind an open drawer is inert, and the drawer is a dialog. Other menus on the page stay live, so a logo beside another menu stays reachable. Level changes are announced, and Back says where it goes.
- **Names:** a logo that fails to load has a name, and script labels are translated. A labelled phone button keeps its label in its accessible name.
- **Landmarks and headings:** still open by default. Each Classic menu is a `header` with `h3` and `h4` titles, to keep Easy Mega Menu's exact markup. Developers can change that with the `abmm_classic_plain_markup` filter.

Still open: the Classic desktop preview, the time before scripts load, and the manual checks under Pending.

## Retesting

Re-run `tests/e2e/a11y.spec.js` (CI does this on every pull request) and repeat the pending manual checks after changes to the editor layout, dialogs, the public menu markup or the default colours. Record new results in the table with the date, versions and commit.
