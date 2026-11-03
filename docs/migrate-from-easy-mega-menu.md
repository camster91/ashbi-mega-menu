# Migrating from Easy Mega Menu

Ashbi Mega Menu 1.1 can take over from Easy Mega Menu without visitors seeing a difference, and you can switch back in about a minute. Nothing in Easy Mega Menu is changed or deleted at any point.

## What gets copied

| Easy Mega Menu | Ashbi Mega Menu |
|---|---|
| Menus (`emm_menus`), with their IDs | Ashbi menus with the **same IDs**, in the Classic look |
| Product profiles (`emm_product_profiles`) | Product Navigation profiles, including hub pages and CTAs |
| Product branding (`emm_product_branding`) | Product Branding, including product text and wordmarks |
| Page assignments (`_emm_product_profile` post meta) | Ashbi page assignments (existing Ashbi assignments are kept) |
| Its stylesheet and icon sprite (from its plugin folder) | Copies in `wp-content/uploads/ashbi-mega-menu/classic/` |

**Classic look.** Imported menus render with Easy Mega Menu's own markup (`<header class="emm-header …">`, `emm-` classes, `--emm-*` variables), its stylesheet and its script. Site CSS or snippets that target `emm-` classes keep working. Because the stylesheet and sprite are copied from your installed Easy Mega Menu, any site-specific styles and your own icons stay exactly as they were. If Easy Mega Menu's files are not found, Ashbi uses its bundled classic stylesheet and generic icons and says so before you import.

Colour, size and width changes made later in Ashbi's Design tab still apply to classic menus. The phone and tablet previews in the editor show the Classic look; the desktop preview shows the content in Ashbi's style.

**Long phone submenus.** In Easy Mega Menu's phone menu, the last links of a long category (for example the last few links under a large "Human Resources" group) can sit below the screen and cannot be scrolled into view. Imported menus keep that behaviour so nothing changes on the switch. To fix it, tick **Design → Let long phone submenus scroll to the last link** on the menu: the phone menu then scrolls as one list. To move a menu to Ashbi's own style, change **Design → Site look** to **Ashbi**; that changes how the menu looks on your site.

**Not copied exactly.** The import screen lists, per menu, any value that would not survive unchanged (for example a setting Ashbi does not have, or an icon missing from the copied icon set). An empty list means the menu is copied exactly.

## Before you start

1. Take a full backup (database and `wp-content/plugins`).
2. Install and activate Ashbi Mega Menu 1.1 **with Easy Mega Menu still active**. Nothing on the site changes: Easy Mega Menu keeps answering its shortcodes, and Ashbi loads no front-end files until it renders a menu.
3. If a cache or optimizer delays scripts, exclude Ashbi's classic files the same way as Easy Mega Menu's: `ashbi-mega-menu/assets/classic/emm-frontend.js` and the stylesheet in `uploads/ashbi-mega-menu/classic/`. Both carry NitroPack's `nitro-exclude` marker already.

## Import and test

1. Open **Ashbi Mega Menu → Easy Mega Menu import**.
2. Check the table: menu names, IDs, link counts and "Not copied exactly". If an Ashbi menu already uses one of the IDs, it is kept unless you tick **Replace Ashbi menus that use the same ID**.
3. Choose **Import menus**. A snapshot of your Ashbi menus is saved first (restore it from **Tools → Import backups**).
4. Create a **private draft** page that renders a menu with Ashbi's shortcode, for example `[ashbi_mega_menu id="menu_demo"]`, in the same header or template context. Compare it with the live header on desktop and phone: open each dropdown and category, the phone drawer, Back and Close, and use Tab, Enter and Escape.

Re-running the import updates earlier imports, so you can change something in Easy Mega Menu and import again.

## Switch

At a quiet time:

1. On the import screen, tick **Handle [easy_mega_menu] shortcodes** and save. (While Easy Mega Menu is active it still answers its own shortcodes, so nothing changes yet.)
2. Deactivate Easy Mega Menu. **Do not delete it.**
3. Clear page and CDN caches.
4. Check the live pages logged out.

Ashbi now answers `[easy_mega_menu id="…" context="…" presentation="…" review_post_id="…"]` and the theme helpers `emm_render_menu()` and `emm_get_menu()`, so header templates need no edits.

## Switch back (about a minute)

1. **Plugins → Easy Mega Menu → Activate.** Easy Mega Menu answers its shortcodes again immediately; Ashbi steps aside on its own.
2. Clear page and CDN caches.

Easy Mega Menu's data was never changed, so it comes back exactly as it was. Edits made in Ashbi after the switch are not copied back.

## Later

Once you are confident, you can edit menus in Ashbi and, if you like, move them to Ashbi's style one at a time with **Site look**. Keep Easy Mega Menu installed but inactive until you no longer need the switch-back option. Deleting it does not affect classic menus, because their styles and icons were copied.
