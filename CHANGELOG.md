# Changelog

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
