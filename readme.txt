=== Ashbi Mega Menu ===
Contributors: camster91
Tags: mega menu, navigation, responsive menu, menu builder
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 1.2.6
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build responsive navigation with a visual editor, local previews and portable menu exports.

== Description ==

Ashbi Mega Menu helps you build a clear menu for your site, with no code. Start from your pages, pick a style and save. Then add the menu to your site with a block, a widget or a shortcode.

**Simple to start**

* Build your menu from pages you already have, from a template or from nothing.
* Pick one of five styles. They are made to work together.
* See your menu on a computer, tablet and phone as you work.
* Follow a short guide that recommends the best way to add the menu to your theme.

**Safe to change**

* Nothing changes on your site until you place a menu.
* Undo your changes, or go back to an earlier saved version of a menu.
* A checklist points out links that go nowhere.
* You get a warning before you leave with unsaved changes.
* Your theme's menu is never changed.

**Powerful when you need it**

* Dropdowns with columns, categories, icons and a featured card.
* Colors, fonts, layout and height controls, kept out of the way until you need them.
* A phone menu with an optional search box.
* An Elementor widget that uses your global fonts and colors.
* Different links on different parts of your site, for products, services or departments.
* Move from Easy Mega Menu and keep the same look. You can switch back at any time.
* Export and import menus, plus tools for developers.

**How saving works**

There is no separate publish step. Once your menu is on your site, each save updates your site right away.

**Your privacy**

The plugin needs no outside account and has no outside updater. It holds no client data. Menu search runs in the visitor's browser. Any links and images you add load from wherever you point them.

The complete block source is included in the src directory of this package. To rebuild, clone the source repository and run npm ci and npm run build. The maintained source and build tools are available at https://github.com/camster91/ashbi-mega-menu.

== Installation ==

1. Install the plugin and activate it.
2. Open Mega Menu and choose Start from scratch or Use a template.
3. Edit links and design, then save your menu.
4. Add the Ashbi Mega Menu block to a test page and select your menu, or copy its exact shortcode into a Shortcode block or use the included widget.
5. Verify links, keyboard navigation and mobile display on a staging site before placing it in your theme header. The theme's existing navigation remains until you remove it deliberately.

Quick start guide: https://github.com/camster91/ashbi-mega-menu/blob/main/docs/getting-started.md
Detailed placement, backup recovery and troubleshooting: https://github.com/camster91/ashbi-mega-menu/blob/main/docs/user-guide.md
Verified coverage and pending interactive tests: https://github.com/camster91/ashbi-mega-menu/blob/main/docs/compatibility.md

== Frequently Asked Questions ==

= Will this replace my theme's menu? =
No. You choose where your menu goes. Remove your theme's old menu only after the new one works.

= Do I have to save and then publish? =
No. There is only one step, which is saving. Once a menu is on your site, each save shows up there right away. A menu that isn't placed yet can't be seen by visitors.

= How do I put the menu on my site? =
Open your menu and click Place. The plugin recommends the best method for your theme. You can use a block, a widget, a shortcode or the Elementor widget.

= Does it work on phones? =
Yes. On screens up to 900 pixels wide, the menu becomes a button that opens a drawer. You don't have to set anything up. Always test on a real phone.

= Can I undo a mistake? =
Yes. Undo works while you edit. Each menu also keeps its last 10 saved versions. For bigger mistakes, you can restore a backup of all your menus. Restoring a backup replaces all current menus, and the plugin first saves them as a new backup.

= Do copied menus replace the ones on my site? =
No. A copy is a separate menu with a new ID. Blocks, widgets and shortcodes keep showing the menus they already use until you change them.

= Can I move from Easy Mega Menu? =
Yes. The import only reads Easy Mega Menu's data and doesn't change it. Your headers keep working with no edits, and you can switch back in about a minute. Test on a staging copy or a private page first. Visitors may see small improvements when panels open and in the phone menu.

= Is it accessible? =
It passes automated checks and browser tests for keyboard use, focus and contrast. It has not yet been fully tested with real screen readers or on real touch devices, so the plugin does not claim to meet WCAG. Test results are in docs/accessibility.md.

= Which databases work? =
Standard MySQL and MariaDB WordPress sites work. SQLite has not been tested.

= What support can I get? =
Report problems you can repeat on the public GitHub page. There is no promised reply time. Do not share passwords, private exports or client data. Tested themes and known limits are listed in the docs.

= What happens when I delete the plugin? =
Deleting removes all its menus, archived menus, saved versions, products, branding and settings. Export your menus first. Turning the plugin off keeps everything.

== Screenshots ==

1. Menu dashboard with menu cards and tools.
2. Menu editor with a live preview and the menu item list.
3. Responsive mobile navigation open in Chrome.

== Changelog ==

= 1.2.6 =
* A simpler editor. Four steps replace the tabs: Content, Design, Place and Check. Presets come first, and everything else sits in one closed Advanced settings section that names what you changed.
* New dropdowns start on Simple columns, History brings saved versions and backups together, and a short optional guide helps new users.
* The Classic desktop preview now shows the real Classic look.
* Admin changes only. Visitors see no change.

= 1.2.5 =
* Visitors: easier hover and tap behaviour in both menu styles, phone drawers that close on link taps and reach every link, and keyboard, high-contrast and screen-reader fixes.
* Editor: clearer placed and needs-attention status, saved versions per menu, safer archive, delete and Easy Mega Menu import, and warnings before leaving with unsaved changes.

= 1.2.4 =
* Classic menus: phone menu sections open on sites whose scripts reset menus that measure zero wide.

= 1.2.3 =
* "Edit mega menu" now shows in the toolbar on themes that print the toolbar before their header, such as Hello Elementor.

= 1.2.2 =
* While Easy Mega Menu is active, classic menus on test pages match how they will look after the switch.
* The Easy Mega Menu import screen lists the menus your pages actually use.

= 1.2.1 =
* Warning when Easy Mega Menu is inactive and content still uses its [easy_mega_menu] shortcode, which would show as text.
* Product navigation: the block has a "Use page product navigation" setting, and the product name can link to the main product page.
* The classic widget is named "Ashbi Mega Menu (classic widget)", so it is not confused with the block or the Elementor widget.
* Copies made by importing are numbered like duplicates; clearer counts in import, archive and snapshot messages.
* The block's Wide and Full width alignment (and extra CSS classes) now apply on the site.
* Simple-column links can have a short description, shown under the link.
* Choose the logo from the Media Library.
* The checklist flags featured-card link text that has no link (the text is hidden on the site), and an item about one dropdown opens that dropdown's editor.
* Undo and redo no longer switch the chosen style preset to Custom.
* While a dropdown's content is edited, the preview stays in view above it.
* Screen readers announce a row's new name as soon as it is renamed.
* Placement help explains that a narrow column shows the phone menu.
* Smaller wording and layout fixes in the editor.

= 1.2.0 =
* Fonts: use your site's font or a custom one, with sizes and weights for the menu bar, categories, links and descriptions.
* Dropdown height: default, grow to fit, cap and scroll, or fixed.
* Elementor widget with Elementor typography and colour controls, so global fonts apply.
* "Edit mega menu" toolbar link on pages that show a menu.
* Undo and redo in the menu editor.
* Menus that don't use the new settings render exactly as before.

= 1.1.0 =
* Import menus, product navigation and branding from Easy Mega Menu, read-only, with a preview and a list of anything not copied exactly.
* Imported menus keep Easy Mega Menu's exact look, markup and site CSS (Classic look), including the site's own icons.
* Optionally answer [easy_mega_menu] shortcodes and emm_render_menu() after Easy Mega Menu is deactivated, so headers need no edits.
* Optional fix so long phone submenus in classic menus scroll to the last link.
* Phone and tablet previews show classic menus as they look on the site.
* Menus created in Ashbi are unchanged.

= 1.0.4 =
* Keep a desktop mega menu open when it is clicked after opening on hover.
* Fix cramped reorder, icon size and category fields in the editor, and add the missing right margin.
* Show one clear set of create options on a new install.
* Make all menu editor text and the mobile menu's screen-reader labels translatable.
* Keep the mobile drawer on screen when the menu sits low on the page, such as in a footer widget.
* Stop themes such as Twenty Twenty-One from repainting menu section buttons and drawer controls.
* Tell menus with the same name apart in the block and widget menu lists.
* Remove the widget's saved settings when the plugin is deleted.
* Save reliably on every host: menu saves now use a per-site lock row instead of a MySQL advisory lock, which could block saves on shared or pooled databases.
* Redesign the dashboard: simpler welcome, one Tools panel and shorter text.
* Redesign the menu editor: three clear steps (Edit, Place, Check), a Place tab that always shows the shortcode, a lighter preview toolbar, tidier rows and folded icon styling.
* Bring Product Navigation, Navigation Coverage and Product Branding in line with the new look and use plainer wording throughout.
* Preview menus on a zoomable canvas at real desktop, tablet and phone widths instead of squeezing them into the editor.
* Flag links typed as plain text (such as "about") and menu bar names that are too long, before you place the menu.
* Count shortcodes in pages when warning before a menu is archived.
* Show the real reason when an import file is rejected, and give repeated duplicates distinct names.
* Fix the editor preview for menus with several mega items, and open the drawer in tablet and phone previews.
* Refresh the desktop mega panel: rounded category rows, icon tiles, a clear "see all" link and a stable height between categories.
* Ship a fuller starter template with sample products, a site name and a button; the checklist counts sample links still to replace.
* Flag a logo with nothing to show, keep dark styles readable and stop a home link from looking current on every page with plain permalinks.
* Clamp negative number settings to their minimum and fix icon search.
* Start ordinary sites with neutral defaults and make contextual navigation an optional module.
* Generalize contextual navigation for products, services, departments, documentation and locations.
* Remove retained recovery drafts on uninstall and package third-party notices in a directory-compatible format.
* Preserve numeric menu IDs through imports, editing and placement.
* Verify export recoverability within bounded limits: 500 menus, 10 MiB total and 1 MiB per menu.
* Protect product profiles and branding from stale edits, concurrent writes and false save confirmations.
* Retain entered product fields after failures and normalize non-Latin profile IDs consistently.
* Add guided Create, Edit & save, Place and Verify steps with translated help and safe draft recovery.

= 1.0.3 =
* Verify product-branding save notices against the current administrator session.
* Ignore malformed notice values and add WordPress regressions for signed notices.

= 1.0.2 =
* Serialize menu collection changes and prevent imports when recovery backups fail.
* Add administrator backup export/restore with stale-state protection.
* Fix WP-CLI imports, readiness consistency and stacked URL controls.
* Remove the unpublished composition choice and improve placement/recovery help.

= 1.0.1 =
* Original Ashbi identity, branded admin screens and block icon.
* Improved sidebar link field sizing and preview control contrast.
* WordPress directory artwork and screenshots from the actual plugin.

= 1.0.0 =
* Initial independent open-source release; directory review pending.
* Optional enhanced mobile navigation and shared preview search.
* Generic sample content and public package isolation.

== Upgrade Notice ==

= 1.2.0 =
Adds font and dropdown-height settings, an Elementor widget, a toolbar edit link and undo/redo. Existing menus render as before.

= 1.1.0 =
Adds an Easy Mega Menu importer and an optional [easy_mega_menu] shortcode takeover. Existing Ashbi menus render as before.

= 1.0.4 =
Back up before upgrading. Product editors opened before this upgrade must be reloaded before saving. Existing menu IDs and mobile settings are preserved.
