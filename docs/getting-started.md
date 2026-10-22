# Getting started with Ashbi Mega Menu

This is the short version. It takes about 10 minutes. For the full details, see the [user guide](user-guide.md).

## 1. Install

1. In WordPress, go to **Plugins → Add New → Upload Plugin**.
2. Upload the plugin ZIP, then click **Activate**.
3. A new **Mega Menu** item appears in the admin sidebar.

You need WordPress 6.6+, PHP 8.2+ and a MySQL or MariaDB database.

Installing the plugin does **not** change your site's header. Nothing shows on your site until you place a menu yourself.

## 2. Create a menu

1. Open **Mega Menu**.
2. Pick one:
   - **Start blank** — an empty menu.
   - **Use sample template** — a small example menu to edit. Replace every sample label and link.
3. Give the menu a clear name, like "Main navigation".

## 3. Add your links

The editor has a live preview on the left and settings on the right.

- **Simple Link** — one link in the top bar (for example, "About").
- **Mega Menu** — a top-bar item that opens a big panel with columns of links. Click **Edit content** to fill it in.

Tips:

- Use the link button next to a URL field to search your own pages.
- Use **Move up** / **Move down** (or drag the ⋮⋮ handle) to reorder.
- Switch the preview between **Desktop**, **Tablet** and **Mobile**.
- The **Design** tab changes colors, fonts and layout.

## 4. Save

Click **Save Menu**. The status next to the button says when everything is saved.

If the editor lists checks that need attention (for example, a link with no destination), fix those first.

## 5. Put the menu on your site

Try it on a test page first. Pick the method that fits your theme.

| Your theme | How to place the menu |
| --- | --- |
| Block theme (like Twenty Twenty-Five) | Add the **Ashbi Mega Menu** block and pick your menu. To use it in the header, edit the Header in **Appearance → Editor**. |
| Classic theme with a header widget area | **Appearance → Widgets** → add **Ashbi Mega Menu** to the header area. |
| Any theme or page builder | Paste the shortcode, for example `[ashbi_mega_menu id="your_menu_id"]`. Copy the exact code from the **Place** step in the editor. |

Once the new menu works, remove your theme's old menu so you don't have two headers.

## 6. Check it

On the real page:

- Hover or click each mega menu item. The panel should open and stay open while you use it.
- Click every link.
- Press **Tab** to move through the menu and **Escape** to close a panel.
- On a phone, open the menu, open a panel, go back, and close the menu.

## Common questions

**The menu doesn't show up.**
Check that the menu is saved, that you picked the right menu in the block or widget, and that the shortcode ID matches exactly. Clear any page cache.

**I see two menus.**
Your theme's own menu is still there. Remove it once the new one works.

**The panel is cut off.**
Your theme's header may be hiding overflow. Try placing the block in a full-width header section.

**How do I back up my menus?**
Use **Export** in the editor, or **Import / Export & developer tools** on the main screen. Keep the file somewhere private.

**What happens if I delete the plugin?**
Deactivating keeps your menus. Deleting the plugin removes all its menus and settings, so export first.

## Need help?

Report a problem on [GitHub issues](https://github.com/camster91/ashbi-mega-menu/issues). Include your WordPress version, theme and the steps to reproduce. Never post passwords or private data.
