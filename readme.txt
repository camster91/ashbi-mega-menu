=== Ashbi Mega Menu ===
Contributors: camster91
Tags: mega menu, navigation, responsive menu, menu builder
Requires at least: 6.6
Requires PHP: 8.2
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build responsive navigation with a visual editor, local previews, reusable product navigation and portable menu exports.

== Description ==

Ashbi Mega Menu provides a visual builder for multi-column menus and product navigation. Place a saved menu using a shortcode, the included block, or the included widget.

* Build categories, links and calls to action.
* Preview desktop and mobile layouts before placing a menu.
* Use design presets and color controls.
* Opt into mobile menu search, wider product rows and a visible Back label.
* Export menus before changes; import and archive menus from the management screen.
* Use product profiles for navigation associated with a product hub and its child pages.

Activation starts with an empty menu list. The generic sample is optional and does not place content on your site. Replace all sample labels and destinations before use. Existing menus retain their mobile appearance unless enhanced mobile navigation is enabled.

The plugin contains no external updater. It contains no client starter data or client icon collection and does not require an external service account. Menu search runs locally in the browser. URLs and images entered by administrators may point to external services; use assets you have permission to display.

The complete block source is included in the src directory of this package. Build it with npm ci and npm run build. The maintained source and build tools are available at https://github.com/camster91/ashbi-mega-menu.

== Installation ==

1. Install the plugin and activate it.
2. Open Ashbi Mega Menu and choose a blank menu or the optional sample.
3. Edit links and design, then save your menu.
4. Copy its shortcode into a shortcode-capable area, or use the block or widget.
5. Verify links, keyboard navigation and mobile display on a staging site.

== Frequently Asked Questions ==

= Does activation replace my theme navigation? =
No. Place your menu explicitly. Remove duplicate theme navigation only after checking the placement.

= Which database engines are supported? =
Standard MySQL and MariaDB WordPress installations are supported. SQLite is not supported because safe editing uses database advisory locks.

= What happens when I delete the plugin? =
Uninstallation removes the plugin's stored menus, archived menus, product profiles, branding and settings. Export your menus before deleting it. Deactivation keeps stored data.

== Changelog ==

= 1.0.0 =
* Initial independent open-source release; directory review pending.
* Optional enhanced mobile navigation and shared preview search.
* Generic sample content and public package isolation.

== Upgrade Notice ==

= 1.0.0 =
Review candidate. Back up and test on staging before use. Existing mobile enhancement settings default to disabled.
