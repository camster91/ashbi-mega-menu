# Ashbi Mega Menu

An open-source WordPress plugin for building responsive navigation with a visual editor. Licensed under GPL-2.0-or-later.

**Status:** the [1.0.0 review candidate](https://github.com/camster91/ashbi-mega-menu/releases/tag/v1.0.0) is available. WordPress.org submission is pending account login; directory approval has not been granted.

[Release validation](https://github.com/camster91/ashbi-mega-menu/actions/runs/37960510713) passed: build, regressions, official Plugin Check and WordPress database tests, including WordPress 6.6/PHP 8.2. Plugin Check has zero errors and one reviewed read-only status-notice warning. See [security review](docs/security-review.md).

## Features

- Visual menus with categories, links, icons, calls to action and design presets.
- Desktop and mobile previews, plus optional browser-local mobile menu search.
- Shortcode, block and widget placement.
- Menu import, export and archives.
- Product navigation profiles for a hub page and its child pages.
- Revision checks prevent a stale editor from silently replacing newer changes.

Activation starts with an empty menu list. An optional generic sample helps you get started; replace its placeholder links before using it. The plugin never automatically replaces your theme's header.

## Requirements and installation

WordPress 6.6 or later, PHP 8.2 or later, and a standard MySQL/MariaDB WordPress database. SQLite installations are not supported because saving uses database advisory locks.

Upload the release ZIP through **Plugins → Add New → Upload Plugin**, activate it, then open **Ashbi Mega Menu**. Create a menu and place it with the provided shortcode, block or widget. Test placement, keyboard navigation and mobile links on staging before enabling it on a client site.

This plugin uses independent `abmm_` storage and identifiers. It is a separate plugin, not an automatic migration or replacement for another menu plugin.

## Development

```sh
npm ci
npm run build:icons
npm run build
npm test
npm run test:php
npm run package
```

Use Node.js 24.18 or later and PHP 8.2 or later. Block source lives in `src/`; compiled files are in `build/`. Packaging uses an explicit runtime allowlist and produces `dist/ashbi-mega-menu-1.0.0.zip`. Build dependencies and CI configuration are available here, and are excluded from the install ZIP.

CI builds the block, runs regression tests and invokes the official WordPress Plugin Check action against the packaged files. A successful CI run does not guarantee WordPress.org approval or compatibility with every theme.

## Privacy and removal

No telemetry, external updater or service account is included. Search runs in the visitor's browser. Administrators can enter external links or image URLs, which may cause visitor requests to those destinations. Deactivation retains plugin data; deleting the plugin removes its settings, menus, archives, profiles and branding. Export menus before deletion.

## Contributing and support

See [CONTRIBUTING.md](CONTRIBUTING.md), [SECURITY.md](SECURITY.md) and [THIRD-PARTY-NOTICES.md](THIRD-PARTY-NOTICES.md). Report reproducible problems through [GitHub issues](https://github.com/camster91/ashbi-mega-menu/issues). Never include credentials, private menu exports or client information.
