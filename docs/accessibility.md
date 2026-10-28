# Accessibility test results

This page records what has actually been tested, how, and what is still open. It is evidence, not a conformance claim: the plugin does **not** claim WCAG 2.1 AA conformance until the pending manual checks below are done. The procedure for running each check is in [accessibility-compatibility-qa.md](accessibility-compatibility-qa.md).

Last updated 2026-10-28 for issue #3, against the editor and public menu as of the `claude/a11y-audit` branch (WordPress 7.1.3, PHP 8.5, Twenty Twenty-Five, SQLite).

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
| Windows high contrast | `forced-colors: active` emulation in Chromium, visible-focus check repeated | **Pass** in emulation. **Not tested on Windows** with a real high-contrast theme. |
| Reduced motion | `prefers-reduced-motion: reduce` emulation | **Pass**: no transitions or animations longer than 20 ms in the public menu; canvas zoom and the inspector highlight respect it. |
| Colour contrast of default colours | axe, plus manual ratio checks | **Pass** after the fixes below. Custom colours chosen in the Design tab are the site owner's responsibility; the editor does not yet warn about low contrast. |

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

## Retesting

Re-run `tests/e2e/a11y.spec.js` (CI does this on every pull request) and repeat the pending manual checks after changes to the editor layout, dialogs, the public menu markup or the default colours. Record new results in the table with the date, versions and commit.
