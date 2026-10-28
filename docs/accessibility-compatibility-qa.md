# Accessibility and compatibility verification

This is the verification procedure for issues #3 and #4, not a conformance claim. Results so far are in [accessibility.md](accessibility.md). Record actual results in `docs/compatibility.md`, including commit, date, WordPress/PHP/database versions, theme/version, browser/version, operating system and assistive technology/version. Existing JSDOM tests verify interaction contracts without layout; PHP tests verify storage/rendering without a browser.

## Disposable fixture

Use a staging installation with MySQL/MariaDB. Create a menu with a plain link, a mega item with two categories and multiple column links, a second mega item, a brand link and an optional CTA. Include a long label, a missing destination and a link opening a new tab. Place it on a page with content before and after the navigation. Add a second instance on a separate test page. Keep the original theme header until the replacement is verified.

Exercise both block-theme Header placement and classic-theme shortcode/widget/helper placement using the user guide. Record the exact placement method; do not count a shortcode-only test as successful Header-template integration.

## Keyboard and assistive technology

| Journey | Acceptance criteria |
| --- | --- |
| Editor section tabs | Arrow keys and Home/End select the expected tab; selected state, panel visibility and focus agree. |
| Add/edit/reorder content | Every operation is available without dragging. After moving an item into the first/last position, focus remains on an enabled control belonging to that item. Saved order survives reload. |
| Modal and responsive sheets | Opening moves focus inside; Tab/Shift+Tab stay within the active modal; Escape closes it; focus returns to its opener; background controls are unavailable while modal. |
| Validation/save/recovery | Errors identify the affected field or operation; save/conflict/recovery status is announced; failed saves retain the draft; recovery requires explicit confirmation. |
| Archive confirmation | Checking and confirmation text is translated; confirmation receives focus; Cancel returns focus and sends no mutation; confirming acts on the intended menu only. |
| Desktop public navigation | Tab reaches each visible control in order; Enter/Space operate buttons; destinations are usable; Escape closes open panels and returns focus appropriately. No hover-only destination is required. |
| Mobile drawer | Toggle state and controlled drawer agree; opening moves focus inside; Tab wraps within the active drawer; Escape/back controls close the appropriate level; closing returns focus to the toggle. |
| Search and multiple instances | Search has an accessible name and meaningful result feedback; hidden results are not focusable; operating one menu does not change IDs, focus or expanded state in another. |
| Screen-reader checks | With NVDA/Firefox and VoiceOver/Safari, verify control names, expanded/selected states, relationships, reading order and announcements in the editor and public navigation. Record actual output and any barrier. |

## Visual and browser matrix

Test 320 px and 390 px mobile widths, 768 px tablet and 1440 px desktop. Also test 200% and 400% zoom, long translated labels, RTL, Windows forced colours and reduced motion. Check clipped controls/text, horizontal overflow, visible focus, contrast, pointer targets and readable error states. Responsive emulation is not a substitute for a touch-device check.

Run the create → edit → save → reload → place → desktop/mobile navigation → export/import → backup recovery journey in current Chrome, Edge, Firefox and Safari, recording exact versions. Confirm links, panel positioning, sticky headers, WordPress admin bar, back/forward restoration and the browser console. Browser engines and actual devices must be recorded separately.

## Integration matrix

| Configuration | Required evidence |
| --- | --- |
| Block theme and classic theme | Full journey and header rollback with each exact theme/version. |
| Elementor | Shortcode/widget placement, editor preview and public page; no proprietary-integration claim unless tested. |
| Page caching | Anonymous cached page still opens menus and follows links; saved changes appear after the documented purge. |
| Script optimization | Test enabled defer/delay/minification individually and together; verify one event binding per menu and no new console errors. |
| Persistent object cache | Site isolation, save/reload, import/backup recovery and deactivation; record cache implementation/version and draft-expiry behaviour. |
| Multisite | Per-site isolation, lifecycle and network uninstall; retain the dedicated CI run as evidence without treating it as broad browser compatibility. |

## Recording and retesting

For each finding, record severity, affected journey/configuration, reproduction steps, expected/actual result, code or screenshot evidence, owner and retest result. Distinguish confirmed barriers from untested combinations. Fix blocking navigation, focus loss and data-loss behaviour before expanding compatibility claims. Recheck the affected path and adjacent desktop/mobile behaviour after a fix.

NVDA, VoiceOver, real mobile devices, other browser engines and third-party integrations remain pending unless a dated result explicitly establishes otherwise. Do not infer a pass from source inspection, JSDOM or Plugin Check.
