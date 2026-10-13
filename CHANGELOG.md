# Changelog

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
