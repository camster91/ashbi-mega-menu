# Changelog

## 1.2.1

Found by building a full menu in the editor from scratch, as a site owner would.

- **Simple-column links** can have a short description, like category links. It is shown under the link on the site and in the preview, and can be styled with the Description font settings and the Elementor widget's Descriptions controls. Links without one keep their exact markup.
- **Logo**: a Media Library button next to the logo and dark-preset logo fields; the image's alternative text fills the logo alternative text when that is empty.
- **Checklist**: a featured card with link text but no link is flagged, because the site hides link text that goes nowhere. The checklist count now uses a proper plural ("1 thing", "2 things").
- **Undo and redo** no longer turn the chosen style preset into Custom: restoring colours is not treated as a colour edit.
- **Editing a dropdown's content** keeps the preview on screen: the canvas gets shorter while the content editor is open, so the open dropdown and the editor fit together.
- **Accessibility**: move, duplicate, remove and icon buttons of menu items, categories, columns and links are renamed as soon as their label changes, instead of announcing the old name until the list is redrawn.
- **Placement help** says to give the menu the full page width: in a narrow column or sidebar it shows the phone menu, even on a wide screen.
- New categories are named "New category" instead of "NEW"; the create-menu button reads "Create menu"; an expanded menu item no longer has a large empty area under its URL.
- The editor's font and height fields use a `data-abmm-style-key` attribute, so they can no longer be confused with the dropdown panels' own `data-abmm-style` attribute.

## 1.2.0

- **Fonts** (Design tab): use the site's own font (theme or Elementor global font) or a custom font list, and set sizes and weights for the menu bar, category titles, links and descriptions, plus menu bar letter spacing and letter case. Sizes apply to the desktop menu; the phone drawer keeps its touch-friendly text.
- **Dropdown height** (Design tab): keep the default, grow to fit all links, cap at a height and scroll, or use one fixed height.
- Both work for Ashbi menus and imported Classic menus, are saved only when set, and print a small stylesheet scoped to that menu: menus that don't use them render exactly as before. Each group has a "Reset to default" button, and the editor preview updates live.
- **Elementor widget** "Ashbi Mega Menu" (loaded only when Elementor is active): pick a menu, optionally show product navigation, and style the menu bar, category titles, links and descriptions with Elementor's typography and colour controls, so Elementor global fonts apply. The dropdown background and height can be set per widget. Shortcodes, the block and the WordPress widget are unchanged.
- **Edit mega menu** in the toolbar on pages that show a menu, for administrators, linking to that menu's editor (one link per menu when a page shows several).
- **Undo and redo** in the menu editor for the current session (buttons, Ctrl/Cmd+Z and Ctrl/Cmd+Shift+Z or Ctrl+Y outside text fields).

## 1.1.0

- Switch from Easy Mega Menu without visible changes. **Easy Mega Menu import** (under Ashbi Mega Menu) reads Easy Mega Menu's menus, product profiles, product branding and page assignments without changing them, shows what will be copied and anything that cannot be copied exactly, keeps menu IDs, and saves a snapshot first. Existing Ashbi menus, profiles, branding and page assignments with the same ID are kept unless you choose to replace them; re-running the import refreshes what earlier imports created. Product settings are saved with the same locking and verification as the Product Navigation screens.
- Classic look: imported menus render with Easy Mega Menu's own markup (`emm-` classes and `--emm-*` variables), stylesheet and script, so site CSS written for Easy Mega Menu keeps working. The importer copies the site's Easy Mega Menu stylesheet and icon sprite into uploads, so custom icons keep their exact artwork after Easy Mega Menu is deactivated. A **Site look** setting switches a menu to Ashbi's style deliberately.
- **Handle [easy_mega_menu] shortcodes**: after Easy Mega Menu is deactivated, Ashbi answers its shortcode (with `id`, `context`, `presentation` and `review_post_id`) and the `emm_render_menu()` / `emm_get_menu()` theme helpers, so header templates need no edits. Reactivating Easy Mega Menu switches back at once.
- Classic assets keep NitroPack's `nitro-exclude` marker, like Easy Mega Menu's. Sites whose menus are all classic no longer load Ashbi's own stylesheet.
- Optional fix for classic menus: **Let long phone submenus scroll to the last link** (off by default) for Easy Mega Menu's phone drawer, where the last links of a long category could not be reached.
- The editor's phone and tablet previews show classic menus with their own markup and styles, and clicking a category or top item there selects it.
- Menus created in Ashbi are unchanged.

## 1.0.4

- Start ordinary sites with neutral defaults: blank menus have no visible call to action, and contextual navigation is an optional module.
- Generalize contextual navigation for products, services, departments, documentation and locations without inferring behavior from menu labels.
- Preserve the contextual module automatically for upgrades that already contain profile or branding data.
- Complete removal of dynamic recovery drafts and package third-party notices in the WordPress.org-compatible text format.
- Preserve numeric menu IDs, including zero, across imports, administration, block/widget and shortcode placement.
- Validate every portable export against bounded importer limits (500 menus, 10 MiB overall, 1 MiB per menu, 100,000 nodes, depth 32). Oversized downloads fail clearly; stored recovery snapshots still restore in place.
- Serialize product-profile and branding mutations, refresh option caches under lock, reject stale revisions, and verify saved data before confirming success.
- Preserve administrator-specific recovery drafts on failures and prevent non-Latin duplicate profile IDs from replacing existing records.
- Guide menu setup through Create, Edit & save, Place and Verify; separate configured content from actual live-site verification, collapse advanced tools, and preserve/download stale drafts.

## 1.0.3

- Product-branding save redirects now carry a session nonce; unsigned or invalid status notices are ignored while the editor remains usable.
- Reject structured query values and verify notice handling in real WordPress regression tests.

## 1.0.2

- Serialize all menu collection mutations and refresh caches inside the shared lock.
- Fix CLI import limits and stop imports if a recovery backup cannot be saved.
- Add administrator backup export and explicitly confirmed, revision-checked restoration.
- Align dashboard/editor readiness and hide the unpublished composition choice.
- Correct URL controls across stacked editor fields; add save/retry and interaction regressions.
- Add placement, recovery, compatibility and maintenance documentation.
- Remove unloaded/clipped editor screenshots pending fresh browser captures.

## 1.0.1

- Add a consistent navigation mark and scoped admin identity.
- Add WordPress directory banners/icons, editable artwork and a social preview.
- Add genuine local admin screenshots and brand documentation.
- Preserve stored menus and frontend navigation behavior.

## 1.0.0

- Initial independent open-source distribution with generic starter content.
- Visual menu editor, block, shortcode, widget, import/export and product navigation.
- Optional enhanced mobile navigation and local menu search.
- Isolated storage, licensed icons and reproducible release packaging.

WordPress.org submission and approval are pending.
