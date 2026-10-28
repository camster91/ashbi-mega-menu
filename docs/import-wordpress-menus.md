# Import a WordPress menu

If you already built a navigation menu under **Appearance → Menus**, you can start from it instead of rebuilding it by hand.

1. Go to **Mega Menu** and open **Tools → Import from WordPress menu**.
2. Pick the menu and select **Preview**. Nothing is saved yet.
3. Read the preview, then select **Import as draft**. The new menu opens in the editor.

The WordPress menu, your theme and its menu locations are not changed. The imported menu is a separate draft named "*Menu name* (imported)", and it is not placed anywhere until you place it (see [Place it in a page first](user-guide.md#place-it-in-a-page-first)).

## How menus are converted

| In the WordPress menu | In the imported menu |
| --- | --- |
| A top-level item with no children | A link in the menu bar |
| A top-level item with children | A mega menu |
| Its children, when none of them has children | One set of link columns, without a category list |
| Its children, when some have children | Categories. A child's own page becomes the category's "see all" link |
| Grandchildren (level 3) | Links in that category |
| Level 4 and deeper | Links in the same category, after their parent. The preview lists them under **Moved up a level** |

Each item keeps its label, URL, "open in new tab" setting, CSS classes and description. Links to pages, posts, categories and custom URLs are all imported with the URL WordPress shows for them.

## What the preview reports

- **Moved up a level:** items deeper than level 3, and where they now appear.
- **Skipped:** items that are not imported, with the reason:
  - it links to a page or post that is not published (draft, pending, private or scheduled);
  - the page or post it linked to was deleted;
  - it has no label;
  - its URL is not a safe web address (for example a `javascript:` link).

  When an item is skipped, its children are skipped too.
- **Check after importing:**
  - a link has no URL (WordPress also stores an empty URL when it removed an unsafe one);
  - several items at the same level share a label;
  - a top-level item had a description (the menu bar has no place for one);
  - a link relationship (XFN) other than `nofollow`, `sponsored`, `ugc`, `noopener` or `noreferrer` was used.

## Limits

- Only classic menus (Appearance → Menus) can be imported. Navigation built in the Site Editor of a block theme is not supported yet.
- The same limits as a JSON import apply: a converted menu must stay under 1 MB.
- Only administrators (`manage_options`) can import.
- Icons, the call-to-action button, the logo and design settings are not part of a WordPress menu. Set them in the editor after importing.
