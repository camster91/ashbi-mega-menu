# Switch from Easy Mega Menu

You can move from Easy Mega Menu to Ashbi Mega Menu. Your menus keep their look, and you can switch back in about a minute. Nothing in Easy Mega Menu gets changed or deleted.

## The simple way

Take a full backup of your site first. Then:

1. **Install** Ashbi Mega Menu. Keep Easy Mega Menu active. Your site doesn't change.
2. **Import.** Go to **Mega Menu**, then **Easy Mega Menu import**. Check the list, then click **Import ticked menus**.
3. **Test.** Put the new menu on a private draft page, then compare it with your live header.
4. **Switch.** Tick **Handle [easy_mega_menu] shortcodes** and click **Save switch setting**. Then deactivate Easy Mega Menu. **Don't delete it.**
5. **Clear your cache** and check your site while logged out.

Your headers keep working with no edits, because Ashbi now answers Easy Mega Menu's shortcodes.

<!-- screenshot: 40-emm-import.png -->

## Step by step

### Before you start

1. Back up your database and your plugins folder.
2. Install and activate Ashbi Mega Menu with Easy Mega Menu still active. Your live pages stay the same.
3. If you use a cache or speed plugin that delays scripts, skip Ashbi's files the same way you skip Easy Mega Menu's. These are `ashbi-mega-menu/assets/classic/emm-frontend.js` and the stylesheet in `uploads/ashbi-mega-menu/classic/`. NitroPack is already told to skip them.

### Import

1. Open **Mega Menu**, then **Easy Mega Menu import**.
2. Read the table. It shows each menu's name, ID, link count and **Not copied exactly**.
3. Leave a menu ticked to copy it.
4. Click **Import ticked menus**.

**Not copied exactly** lists anything that won't carry over unchanged. For example, a setting Ashbi doesn't have, or a missing icon. "Nothing, everything matches" means the menu is an exact copy.

Your existing work is safe:

- A menu row is left **unticked** if an Ashbi menu already uses its ID, or if you edited the Ashbi copy. It stays as it is unless you tick it. Ticking it replaces your edits.
- A backup of your Ashbi menus is saved before each import. You can restore it from **Recovery snapshots**.
- Each replaced menu keeps a version in its [History](undo-and-history.md).

You can import again whenever you like. This refreshes what earlier imports made. Things you created yourself in Ashbi are kept unless you choose to replace them. If anything is kept or fails, the screen tells you which and why.

### What gets copied

| In Easy Mega Menu | In Ashbi Mega Menu |
| --- | --- |
| Menus and their IDs | Menus with the same IDs, in the Classic look |
| Product profiles | Product Navigation, with main pages and buttons |
| Product branding | Product Branding, with product names and logos |
| Page assignments | Page assignments. Yours are kept. |
| Stylesheet and icons | Copies in `wp-content/uploads/ashbi-mega-menu/classic/` |

Branding copies only when its menu was also imported. If Easy Mega Menu's files can't be found, Ashbi uses its own styles and plain icons. The screen tells you before you import.

### Test

1. Create a **private draft** page.
2. Add a menu with Ashbi's shortcode, using a menu's real ID:

   ```text
   [ashbi_mega_menu id="your_menu_id"]
   ```

3. Use the same layout as your live header. A narrow page can open dropdowns in a different spot.
4. Compare it with your live header on a computer and a phone.
5. Try each dropdown and category, the phone drawer, **Back** and **Close**.
6. Try **Tab**, **Enter** and **Esc** on your keyboard.

The import screen lists the menus your pages use, so you know which ones to test.

### Switch

Do this at a quiet time.

1. On the import screen, tick **Handle [easy_mega_menu] shortcodes**.
2. Click **Save switch setting**. Nothing changes yet, because Easy Mega Menu is still active.
3. Go to **Plugins** and deactivate Easy Mega Menu. **Don't delete it.**
4. Clear your page and CDN caches. A CDN is a service that stores copies of your site around the world.
5. Check your live pages while logged out.

<!-- screenshot: 41-emm-switch.png -->

**If a warning appears:** your pages use a menu that you haven't imported. With the switch on, those headers will be empty. Import those menus first. You can tick a box to turn the switch on anyway, but those headers stay empty until you do.

## Switch back

This takes about a minute.

1. Go to **Plugins** and activate Easy Mega Menu. It takes over right away, and Ashbi steps aside.
2. Clear your page and CDN caches.

Easy Mega Menu comes back just as it was. Edits you made in Ashbi after the switch don't copy back.

## What visitors will notice

Your closed menus look the same as before. These parts improved on purpose, so you may see them:

- Panels open after a short pause, and they close sooner.
- A click on a label keeps its panel open.
- On phones, tapping a link closes the drawer.
- On phones, long lists scroll to their last link. In Easy Mega Menu, the last links could sit off the screen.
- On phones, rows fill the width of the drawer, and **Back** has a label.
- Long single lists can flow into two columns, and panels stay within the menu bar's width.

These fixes come from a small extra file, `emm-fixes.css`. It works without importing again. If you changed the look with your own styles, check them on your site.

## Keep or change the look

Imported menus use the **Classic** look. They use Easy Mega Menu's own code and styles, so your site's own styles keep working. Your own icons stay too.

Color, size and width changes in **Design** still apply.

To move a menu to Ashbi's own style, click **Design** and set **Site look** to **Ashbi**. This changes how the menu looks on your site as soon as you save. To undo it, set it back to **Classic** and save.

### Options for Classic menus

- **Phone menu button label.** Tells two phone menus apart. See [Phone menus](phone-menus.md#phone-menu-button-label).

Developers have more choices. See [Extending the plugin](extending.md#rendering).

### The preview

The phone and tablet previews show the Classic look. The preview for large screens may not, so check that on your site.

## Later

Once you're sure, you can edit your menus in Ashbi. You can also move them to Ashbi's style one at a time.

Keep Easy Mega Menu installed but inactive until you no longer need to switch back. Deleting it later doesn't affect Classic menus, because their styles and icons were copied.

**Don't uninstall Ashbi Mega Menu to fix a problem.** Uninstalling deletes its menus and settings.

<!-- TODO: confirm with the simplification PR whether the desktop preview shows the Classic look. Today it shows Ashbi's style with a toolbar note; the plan moves it to the real Classic frame. -->
