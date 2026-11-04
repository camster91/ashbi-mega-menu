# Fix a problem

Most problems have one of three causes: a page cache, an unsaved change or a duplicate menu.

## Try these first

1. **Save your menu.** Your site shows the last saved menu only.
2. **Clear your cache.** A cache is a stored copy of your pages that loads faster. It can show an old menu. Clear it in your cache plugin or with your host.
3. **Look at your site logged out.** Use a private browser window. Then you see what visitors see.

## My menu doesn't show

- Make sure the menu is saved.
- Look at its card. **No placement found** means you may not have placed it yet. See [Place your menu](place-your-menu.md).
- Check that you picked the right menu in the block or widget.
- If you pasted a shortcode, check that the ID matches exactly. Copy it again from the menu's card.
- See if the menu was archived. If so, click **Restore**.
- Clear your cache.

## I see two menus

Your theme's old menu is still there. Remove it once the new one works. You will find it in your theme's settings or header.

<!-- screenshot: 46-two-menus.png -->

## My changes don't show on my site

- Check that you clicked **Save changes**. The status next to the button tells you.
- Clear your cache, then reload the page.
- Your page builder may have set styles on that placement. Look at the widget's own settings.
- Your theme's styles may win over the plugin's. See [Style your menu](style-your-menu.md#when-your-sites-styles-win).

## The preview looks different from my site

The preview doesn't load your theme's styles. So the final look can differ. Always check your real site.

## The dropdown is cut off or hidden

Something in your header is likely hiding what runs outside it. Ask whoever built your site to check the header's overflow, sticky and stacking settings. To start, try the menu in a full-width section.

If another part of the page covers the panel, raise **Header stacking order**. See [Style your menu](style-your-menu.md#layout-and-style).

## The phone menu shows on a computer

The menu may be in a narrow column or sidebar. Give it the full page width. If the menu bar is very full, shorten some labels. See [Phone menus](phone-menus.md).

## A phone menu section won't open

Update the plugin to the newest version first. Then clear your cache. If this still happens, [report it](support-policy.md).

## My menu card says Needs attention

The checklist found something to fix. Your menu may still be live. Click the issue count on the card to see what's wrong. Then click an item in the list to go to the right spot.

<!-- screenshot: 25-checklist-issue.png -->

The checklist can tell you to:

- Add a menu name.
- Add a label to an item.
- Add a real link to a dropdown or a link that goes nowhere.
- Rename two items that have the same label.
- Add a link to your button, or turn the button off.
- Add a link to a featured card.
- Shorten a label that's too long for the bar.
- Add a logo or site title, or turn the logo off.
- Check a logo address that looks wrong.

A greyed item with no link will also show this label. If you want that item to have no link, you can leave it. Visitors see no warning.

The checklist doesn't test whether your links work, or whether your menu works with your theme. You still need to check your site.

## A save reports a conflict

Someone else saved this menu after you opened it. Keep your page open, reload the menu in a new tab and make your changes again. See [Undo mistakes](undo-and-history.md#if-someone-else-saved-first).

## A save says another save is in progress

Wait a few seconds and save again. A stuck save clears by itself within a minute. If it keeps happening, ask your host to check the database.

## The block doesn't list my new menu

Save the menu, then reload the page editor. Your account also needs permission to manage menus.

## An import or restore failed

Read the message. Don't assume any of it worked. Reload the page to see what's there. Export your current menus before you try again.

## I removed my old menu and want it back

Removing the Ashbi menu won't bring back a menu you removed from your theme. Put it back in your theme's settings or header. Restore an earlier header if your theme lets you.

## The menu doesn't work for a few seconds after the page loads

Your menu needs its script to load before it can open. Some speed plugins delay scripts on purpose. If you use one, ask it to skip the menu's script. Its address ends in `frontend.js` or `emm-frontend.js`, inside the `ashbi-mega-menu` plugin folder. Ask whoever runs your site if you are not sure how.

## Before you remove your old menu

Test these on your real site:

- Every link.
- Dropdowns with a mouse and the keyboard.
- The phone menu: open, go back and close.
- Search, if you turned it on.

Keep another way to get around your site until these all pass.

## Still stuck?

See [support](support-policy.md) for how to write a useful bug report. Don't share passwords or private files.

**Don't delete the plugin to fix a problem.** Deleting it removes all your menus, products and settings. Turning it off keeps them.
