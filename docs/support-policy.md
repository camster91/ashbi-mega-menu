# Support and maintenance

Ashbi Mega Menu is an open-source GPL-2.0-or-later project. Development and fixes are maintained in the [public repository](https://github.com/camster91/ashbi-mega-menu). Use the latest published candidate or release and read its release notes; prereleases are review candidates for staging evaluation. There is no promised response time, service-level agreement, paid support entitlement or automatic production deployment.

Report reproducible problems through [GitHub issues](https://github.com/camster91/ashbi-mega-menu/issues). Review existing issues first. Include:

- Plugin version, WordPress/PHP/database versions, theme/version, relevant plugins and browser/version.
- Exact steps, expected behavior, actual behavior and whether it reproduces on staging with a standard theme.
- A redacted screenshot or console/server error where useful; specify whether the problem affects saving, published navigation or only preview.
- Whether changes were recovered and the last known working version, without including confidential exports.

Never post passwords, API keys, personal data, private client URLs or unredacted menu exports. Report security issues using [SECURITY.md](../SECURITY.md) instead of a public bug report. Feature requests should explain the user task and the current workaround; a request does not promise a delivery date.

## Updating safely

Export menus and take a full-site/database backup before upgrading. Test on staging with the site's theme, header placement and caches. A menu export does not back up templates, profile assignments or the full database. Keep the previous plugin ZIP for rollback and verify its compatibility with the stored data before restoring it. After an update, reload the editor and public pages and test desktop/mobile links.

Deactivation keeps stored plugin data. Deletion through WordPress removes plugin settings, menus, archives, backups, profiles and branding; export and back up first. Removing the plugin does not recreate theme navigation previously removed from a header.

WordPress.org submission and approval remain pending. GitHub release availability is distinct from directory approval. Claims about user adoption, accessibility conformance and theme compatibility require their own evidence.
