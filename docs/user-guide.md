# Using Ashbi Mega Menu

Start on a staging copy of your site. Creating a menu does not change the theme header; placement is a separate step. Use a standard MySQL/MariaDB WordPress installation. SQLite is unsupported for editing.

## Build and verify a menu

1. Open **Ashbi Mega Menu** and choose **Start blank** or **Use sample template**. Give the menu a meaningful name.
2. Add top-level links, or select **Mega Menu** for a panel with categories and columns. Replace sample labels and placeholder destinations. A panel toggle opens content; a link needs a real destination.
3. Choose desktop and mobile preview controls. Check labels, contrast, spacing, CTA links and any product-navigation settings.
4. Save, then reload the editor to confirm the saved state. If another editor saved first, keep your current work and reload before reconciling changes; a conflict must not silently overwrite newer work.
5. Follow every link on the actual staged page. The builder preview does not establish theme compatibility or replace keyboard and mobile checks.

Readiness highlights content needing attention. It is not proof of accessibility, working remote destinations or successful theme placement.

## Place it in a page first

In the WordPress page editor, add the **Ashbi Mega Menu** block and select the saved menu. Alternatively add a **Shortcode** block and paste the exact shortcode copied from its dashboard card:

```text
[ashbi_mega_menu id="your_actual_menu_id"]
```

Use an explicit ID; do not copy an example ID into production. Preview the page before publishing. The menu stores its own links and does not automatically synchronize an Appearance → Menus menu or assign a theme menu location.

## Put it in a header

These placement recipes describe supported plugin mechanisms. Theme-specific interactive verification is recorded separately in [compatibility](compatibility.md); an unverified recipe is not a tested-theme claim.

### Block themes with a Header template part

WordPress documents shared headers as [Template Parts](https://wordpress.org/documentation/article/template-part-block/), editable through the [Site Editor](https://wordpress.org/documentation/article/site-editor/). Changes can affect every template that uses that header; review their full scope before saving.

1. In **Appearance → Editor**, open the template or template part that contains your header. Save a copy of the original header markup or use the site's existing revision/backup workflow.
2. Add the **Ashbi Mega Menu** block to a suitable full-width header area and select your menu. Keep the original Navigation block while testing in staging.
3. Check the real page at desktop and phone widths. Make sure the header container does not clip open panels; test sticky-header overlap and the theme's own mobile toggle.
4. Once the new navigation works, remove the old Navigation block from this staging header. Check every template that uses it before publishing that header change.

To reverse placement, remove the Ashbi block and restore the original Navigation block/header revision. Deactivating the plugin alone does not restore navigation you removed from a template.

### Classic themes with a header widget area

Open **Appearance → Widgets** and add **Ashbi Mega Menu** to the theme's actual header widget area, then select the menu. A footer or sidebar widget area will place navigation there, not in the header. Not every theme supplies a header widget area. Test placement before disabling the theme's navigation through its own settings.

To reverse placement, remove the widget and restore the theme's original navigation setting.

### Classic themes without a header widget area

Use a theme-supported header builder with a shortcode element, if it provides one. Otherwise a developer can use the helper in a child theme header at the intended location:

```php
<?php
if ( function_exists( 'abmm_render_menu' ) ) {
    abmm_render_menu( 'your_actual_menu_id' );
}
?>
```

Keep a copy of the original child-theme file and avoid editing the parent theme. The helper does not remove an existing `wp_nav_menu()` call; a developer must deliberately manage that existing placement. Test with the active theme rather than assuming a recipe fits all headers.

## Product navigation

Product Navigation profiles associate navigation with a hub page and its child pages. In the block or widget enable **Use page product navigation** only when that contextual behavior is intended. The equivalent shortcode is:

```text
[ashbi_mega_menu id="your_actual_menu_id" context="page"]
```

Test the hub, a child page and a page outside the profile to confirm the displayed links and active state. Exported menus do not constitute a full backup of product-profile assignments, branding or theme templates.

## Export, import and recover

Export menus before bulk edits and keep the JSON file somewhere separate from the site. Exports can contain private labels, URLs and image references; do not attach them to public issues without removing private content.

Import accepts a JSON file and shows a preview before applying it:

- **Copy** creates new IDs. Existing shortcodes continue pointing to the old menus; choose the copied menu or update the placement deliberately.
- **Merge** adds menus and updates matching IDs. Existing placements for matching IDs can change.
- **Replace** replaces the complete menu collection. Menus omitted from the file disappear from the active collection and existing placements may become empty.

The **Import backups** panel lists the latest retained collection backups. Refresh the list, export the backup for safekeeping, then choose **Restore** only after checking its timestamp and menu count. Restore replaces all active menus with that snapshot and preserves the current collection in another backup first. If the collection changed after loading the list, refresh and review again instead of retrying a stale restore. Only the latest five backups are retained; these are menu-collection snapshots, not full-site backups or per-edit version history.

**Archive** hides a menu while retaining its ID and content. Its placements may become empty. **Restore** in Archived menus restores the original ID so those placements can work again. **Permanently delete** removes the archived copy; retain an export first.

For developers using WP-CLI:

```sh
wp abmm export --file=/safe/path/menu-backup.json
wp abmm import --file=/safe/path/menu-backup.json --mode=copy --dry-run
wp abmm import --file=/safe/path/menu-backup.json --mode=copy
```

Use authenticated administration only. See source/tests for automation contracts; do not expose credentials in command history or public reports.

## Troubleshooting

| Symptom | What to check |
| --- | --- |
| Two headers or two mobile toggles | Both the theme navigation and Ashbi placement are present. Remove the duplicate in staging only after verifying the replacement. |
| Menu is empty | Confirm the exact saved ID, active rather than archived status, selected block/widget menu and the page's profile context. |
| Save reports a conflict | Another change occurred. Preserve local work, reload and reconcile; do not force an old revision over a newer one. |
| Save reports locking/database failure | Confirm MySQL/MariaDB and host advisory-lock support. Ask the host to investigate; bypassing the lock is unsafe. |
| Panel is clipped or hidden | Check the theme header's overflow, stacking context, sticky positioning and page-builder containers on staging. |
| Preview differs from the page | Save and reload first, verify the placement's product context, then clear the site's relevant caches and reload the public page. Report a reproducible mismatch if it remains. |
| Import or restore failed | Read the error, refresh backups/current menus and export the current collection. Do not assume a failed operation applied; reload to verify. |
| Block does not list a newly created menu | Save the menu and reload the page editor; confirm your account has the required management capability. |

Before launch, test keyboard opening/closing, visible focus, phone menu back/close, link destinations and search if enabled. Keep an accessible alternative navigation until these checks pass. See [support](support-policy.md) for a useful bug report.
