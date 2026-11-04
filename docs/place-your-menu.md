# Place your menu

A saved menu does not show on your site by itself. You choose where it goes. This is called placing the menu.

## The simple way

1. Open your menu and click **Place**.
2. Find the method marked **Recommended for your theme**.
3. Follow the steps shown there.
4. Click **Check** and test the menu on your site.

<!-- screenshot: 19-place-step.png -->

Keep these three tips in mind:

- **Save first.** Your site shows the last saved menu. It does not show changes you have not saved.
- **Give the menu the full page width.** In a narrow column or sidebar, it shows the phone menu even on a big screen.
- **Keep your old menu while you test.** Remove it once the new one works, so you do not have two menus.

## Which method should I use?

| Your site | Use this | Go to |
| --- | --- | --- |
| A block theme, such as Twenty Twenty-Five | The block | [Block themes](#block-themes) |
| A theme with a header widget area | The widget | [Widget areas](#widget-areas) |
| Elementor | The Elementor widget | [Elementor](#elementor) |
| Another page builder | The shortcode | [Shortcode](#shortcode) |
| A theme with none of these | Ask a developer | [For developers](#for-developers) |

Not sure what kind of theme you have? The **Place** step checks for you.

## Try it on a test page first

This is the safest way to start.

1. Create a new page. You do not have to publish it.
2. Add the **Ashbi Mega Menu** block.
3. Pick your menu.
4. Set the block to **Full width**.
5. Preview the page.

If it looks right, move on to your header.

## Block themes

A block theme lets you edit your header in WordPress. A change to the header shows on every page that uses it, so take care.

1. Go to **Appearance**, then **Editor**.
2. Open your **Header**. Save a copy of it first, or make sure you can restore it.
3. Add the **Ashbi Mega Menu** block to a full-width part of the header.
4. Pick your menu in the block's settings.
5. Save, then check your site on a computer and a phone.
6. When it works, remove the old **Navigation** block.

<!-- screenshot: 20-block-header.png -->

**To undo this:** remove the Ashbi block and restore your old Navigation block. Turning off the plugin does not bring back a menu you removed.

## Widget areas

A widget area is a spot in your theme that holds small add-ons. Not every theme has one in its header.

1. Go to **Appearance**, then **Widgets**.
2. Find your header area. A footer or sidebar area will put the menu in the wrong place.
3. Add the **Ashbi Mega Menu** block. On older sites, add **Ashbi Mega Menu (classic widget)** instead.
4. Pick your menu.
5. Check your site. Then turn off your theme's old menu in your theme's settings.

<!-- screenshot: 21-widget.png -->

**To undo this:** remove the widget and turn your theme's old menu back on.

## Elementor

1. In Elementor, search for the **Ashbi Mega Menu** widget.
2. Drag it into a full-width section of your header.
3. Choose your menu on the widget's **Content** tab.

<!-- screenshot: 23-elementor-widget.png -->

You still edit the links in Mega Menu. The widget gives you a link to get there.

The **Style** tab sets fonts and colors for the menu bar, category titles, links and descriptions. It also sets the dropdown background and height. These use Elementor's own controls, so your global fonts and colors work. These choices apply only to this one widget.

## Shortcode

A shortcode is a short piece of text. WordPress swaps it for your menu when the page loads. It looks like this:

```text
[ashbi_mega_menu id="your_menu_id"]
```

1. Click **Copy shortcode** on the **Place** step. You can also click **Copy** on the menu's card.
2. Paste it into a shortcode block or element in a full-width section.

<!-- screenshot: 22-shortcode-block.png -->

Use your menu's own code. The ID in the example above will not work on your site.

**To undo this:** delete the shortcode.

## Show extra links on product pages

This is optional. It only applies if you use [Product Navigation](user-guide.md#product-navigation).

- In the block or widget, turn on **Use page product navigation**.
- In Elementor, turn on **Product navigation on product pages**.
- For a shortcode, add `context="page"`:

```text
[ashbi_mega_menu id="your_menu_id" context="page"]
```

## For developers

If your theme has no block header and no header widget area, a developer can add the menu to a child theme. A child theme is a safe copy that keeps your changes when the main theme updates.

```php
<?php
if ( function_exists( 'abmm_render_menu' ) ) {
    abmm_render_menu( 'your_menu_id' );
}
?>
```

Keep a copy of the first file, and do not edit the parent theme. This code does not remove an existing `wp_nav_menu()` call. The developer has to remove that too.

## How the plugin knows a menu is placed

Each menu's card shows one of two labels:

- **Placed** means the plugin found the menu in a page, widget or page builder.
- **No placement found** means it did not find one.

The plugin can't see inside theme files. So a menu added with PHP or in a theme template can show **No placement found** and still be live. If you are not sure, look at your site.

## Check it

After you place the menu, click **Check** and go through the list:

- Every link goes to the right page.
- Dropdowns open with a mouse and the keyboard.
- The phone menu opens, goes back and closes.
- Your old menu is gone, so there is only one.

If something looks wrong, see [Fix a problem](troubleshooting.md).

More help for specific themes is in [compatibility](compatibility.md).
