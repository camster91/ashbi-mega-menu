# Extending Ashbi Mega Menu

This is the supported developer API: what add-ons, themes and site code may rely on, how it changes over time, and where optional add-ons fit. Anything not listed here is internal and may change in any release.

## Inventory

### Placement

| Interface | Details | Status |
| --- | --- | --- |
| Shortcode `[ashbi_mega_menu]` | `id` (menu ID; empty renders the first menu), `context` (`page` for product navigation when that module is on), `presentation` | Stable |
| Block `ashbi-mega-menu/mega-menu` | Attributes `menuId` (string), `context` (string) | Stable |
| Widget `ABMM_Widget` | Settings `title`, `menu_id`, `context` | Stable |
| PHP `abmm_get_menu( $menu_id, $args = array() )` | Returns HTML; `$args` as for the shortcode | Stable |
| PHP `abmm_render_menu( $menu_id, $args = array() )` | Echoes `abmm_get_menu()` | Stable |

### Data and tools

| Interface | Details | Status |
| --- | --- | --- |
| REST `ashbi-mega-menu/v1` | `GET /menus`, `GET /menus/<id>`, `POST /menus/import`, `POST /menus/export`; `manage_options` only | Stable |
| WP-CLI `wp abmm` | `export`, `import --file=… --mode=merge|replace|copy [--dry-run]`, `list` | Stable |
| Export format | JSON with `format: "ashbi-mega-menu"` and `format_version`; see the import docs | Stable; versioned by `format_version` |
| Options `abmm_menus`, `abmm_archived_menus`, `abmm_import_backups`, `abmm_product_profiles`, `abmm_product_branding`, `abmm_contextual_navigation_enabled`, `abmm_plugin_version` | Storage | **Internal.** Read or write menus through the REST API, WP-CLI, the export format or the hooks below, never the options directly; their layout may change between versions. |
| PHP classes (`ABMM_*`) and their methods | Implementation | **Internal**, except the functions and hooks listed on this page. |
| CSS classes and `data-abmm-*` attributes | Markup | Class names used for theming (`.abmm-header`, `.abmm-nav__*`, `.abmm-mega__*`) are kept stable where possible and changes are noted in the changelog; the markup structure is not an API. |

## Hooks

All hooks are prefixed `abmm_`. Since 1.1.0 unless noted.

### Rendering

**`abmm_menu_data`** (filter): change a menu for one request before it is rendered. Changes are not saved.

```php
apply_filters( 'abmm_menu_data', array $menu, string $menu_id, array $args );
```

Use it for conditional visibility, per-page variations or personalization. Return an empty array to render nothing. The result is sanitized again, so only the stored menu format is supported (the same shape as an exported menu). If a page cache is in use, menus that vary per visitor need a cache rule.

**`abmm_menu_html`** (filter): change the rendered markup.

```php
apply_filters( 'abmm_menu_html', string $html, string $menu_id, array $menu, array $args );
```

Output is not escaped again: escape anything you add. Keep the classes and `data-abmm-*` attributes the front-end script uses.

### Data and validation

**`abmm_sanitize_menu`** (filter): runs after the plugin sanitizes a menu, whenever one is saved, imported, previewed or compared.

```php
apply_filters( 'abmm_sanitize_menu', array $menu, array $raw );
```

The plugin drops keys it does not know. An add-on that stores its own data in a menu keeps it under **`extensions.<add-on-slug>`**: read `$raw['extensions'][ $slug ]`, sanitize it, and set `$menu['extensions'][ $slug ]`. The plugin never reads that data, exports and imports it with the menu, and removes it if no add-on keeps it. A filter cannot change the type of a core key or add other top-level keys; a non-array return is ignored.

**`abmm_menu_readiness_issues`** (filter): add checks to the readiness used by the dashboard's Ready badge.

```php
apply_filters( 'abmm_menu_readiness_issues', array $issues, array $menu );
// Each issue: array( 'field' => string, 'message' => string )
```

Issues are sanitized; malformed ones are dropped. The editor's live checklist runs in the browser and does not call this filter.

**`abmm_product_profile_id`** (filter, since 1.0.0): choose the product profile for a page when contextual navigation is on.

```php
apply_filters( 'abmm_product_profile_id', string $profile_id, int $post_id, string $source );
```

### Lifecycle

**`abmm_menu_saved`** (action): after a menu is created or changed in storage, by any path: editor, import, WP-CLI, REST, create, duplicate.

```php
do_action( 'abmm_menu_saved', string $menu_id, array $menu, ?array $previous );
```

**`abmm_menu_deleted`** (action): after a menu leaves the active collection (deleted, archived, or replaced by an import).

```php
do_action( 'abmm_menu_deleted', string $menu_id, array $menu );
```

Both run inside the plugin's save lock. Use them to purge caches or sync elsewhere; do not save menus from them.

### Front-end events

The front-end script dispatches bubbling `CustomEvent`s on the menu's root element (`.abmm-header`):

| Event | `event.detail` |
| --- | --- |
| `abmm:open` | `{ menuId, itemId, label, layout: 'desktop' | 'mobile' }` |
| `abmm:close` | `{ menuId, itemId, label }` |
| `abmm:drawer` | `{ menuId, open }` |

```js
document.addEventListener( 'abmm:open', ( event ) => console.log( event.detail.itemId ) );
```

The plugin itself sends no events, analytics or requests anywhere.

## Examples

Working examples, each a small standalone plugin, are in [`docs/examples/`](examples/):

- `abmm-members-only-items.php`: conditional visibility with extension data (`abmm_sanitize_menu` + `abmm_menu_data`). It is exercised by `tests/extension-hooks.php`.
- `abmm-analytics-events.php`: forwarding front-end events to an analytics tool.
- `abmm-purge-cache-on-save.php`: purging a page cache on `abmm_menu_saved` / `abmm_menu_deleted`.

## Compatibility and deprecation

- Everything marked **Stable** above follows semantic versioning: no breaking change in a fix (`x.y.Z`) or feature (`x.Y.0`) release.
- A hook's name, arguments (order and type) and documented constraints are its contract. New arguments are only ever appended.
- To remove or change a stable interface, it is first deprecated for at least one feature release: it keeps working, `_deprecated_hook()` / `_deprecated_function()` reports it when `WP_DEBUG` is on, and the changelog names the replacement. Removal happens in a major release (`X.0.0`).
- Security fixes may change behavior without a deprecation period when there is no safe alternative; the release notes say so.
- `tests/extension-hooks.php` and `tests/frontend-events.test.js` are the contract tests. A change that needs them edited is a breaking change and needs a deprecation.

## Add-on boundaries

The directory plugin stays complete on its own: menu building, placement, import/export, product navigation, accessibility and all settings work with no add-on, account, licence key, remote service, telemetry, upsell notice or "pro" teaser. Optional add-ons are separate GPL-compatible plugins that use only the API above.

| Area | How an add-on fits | What stays in core |
| --- | --- | --- |
| Templates and starter menus | Provide menu data through the export format (import it, or create via REST/WP-CLI) | The neutral starter menu and import/export |
| Conditional visibility | Store rules under `extensions.<slug>` with `abmm_sanitize_menu`; apply them with `abmm_menu_data` | Rendering, sanitizing and storage |
| Cross-site tools (copying menus between sites or across a network) | Use the export format and REST/WP-CLI, plus `abmm_menu_saved` to sync | Per-site storage, export and import |
| Analytics | Listen to the front-end events, honouring the site's consent rules | No tracking of any kind |

Add-ons must not modify the plugin's files or options directly, must not show notices or upsells on the plugin's screens, and must keep working data readable if they are deactivated (the plugin ignores and eventually drops data nobody keeps).
