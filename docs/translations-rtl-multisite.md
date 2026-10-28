# Translations, right-to-left languages and multisite

## Translations

All plugin text, in PHP, the editor's JavaScript and the block, uses the `ashbi-mega-menu` text domain. The translation template is `languages/ashbi-mega-menu.pot`, generated from the packaged ZIP, so it contains exactly what ships:

```bash
npm run package && npm run i18n
```

CI runs `npm run i18n:check` on every change. It fails when a string is missing the text domain, when a string with placeholders has no `translators:` comment (or two conflicting ones), or when the committed template is out of date.

Once the plugin is in the WordPress.org directory, translations are managed on translate.wordpress.org and delivered as language packs; the template here is for translators and for sites that load their own `.mo` files.

Menu content (labels, links, descriptions) is the site owner's own text and is not translated by the plugin. For a multilingual site, create one menu per language and place each one where that language is shown.

## Right-to-left languages

When WordPress runs in a right-to-left language (Arabic, Hebrew, Persian, Urdu), the editor, the dashboard and the public menu mirror: the category list sits on the right of the dropdown, link columns flow right to left, and the phone drawer's Back button points the reading direction. The desktop dropdown uses CSS logical properties, so it mirrors without a separate stylesheet.

Tested: right-to-left direction in Chromium and WebKit at 1280 px and 390 px (desktop dropdown, phone drawer, editor, dashboard), in `tests/e2e/i18n.spec.js`. The test switches text direction with a test-only plugin rather than installing a language pack, so translated text itself was not reviewed by a native reader.

## Long translations

Translations are often 30–40% longer than English (German, Finnish, Russian). The tests make every PHP-side plugin string about 40% longer and check that the dashboard and editor have no sideways scrolling and that no button clips its text, at 1440 px and 1024 px. Strings translated in the editor's JavaScript are not stretched by that test; menu items are the site's own text.

Keep menu-bar labels short in every language: the editor's checklist flags labels too long for the bar.

## Multisite

The plugin can be activated per site or across the network. **Everything is per site:**

| Data | Where it is stored |
| --- | --- |
| Menus, archived menus, import recovery backups | Each site's own options |
| Product navigation, product branding, the contextual navigation module | Each site's own options |
| Widget placements and block placements | Each site's widgets and content |
| Setup guide progress (hidden, "checked it") | Per administrator **and** per site (a user option) |
| First-run choice (blank, template, pages) | Per administrator across the network (user meta) |

There are no network-wide settings. A menu created on one site cannot be placed on another: a shortcode or block with another site's menu ID renders nothing. To reuse a menu on another site, export it there and import it (the import creates an independent copy).

Only users who can `manage_options` on a site can edit its menus. Network administrators can, on every site.

**Uninstall:** deleting the plugin from the network removes its data from every site in that network and leaves other networks alone. Deactivating keeps all data.

Tested in CI on a disposable network on every change: `tests/wordpress-multisite.php` (per-site menus, editing, placement, import/export between sites, setup guide isolation) and `tests/wordpress-multisite-uninstall.php` (site isolation and network uninstall, with and without a second network).
