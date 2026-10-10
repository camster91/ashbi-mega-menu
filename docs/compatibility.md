# Compatibility and verification

Requirements: WordPress 6.6+, PHP 8.2+, standard MySQL/MariaDB. SQLite editing is unsupported because collection writes require advisory locks. WordPress.org approval and broad production compatibility are not established by a passing build.

## Verified release candidate 1.0.2 — 2026-10-10

| Configuration or workflow | Evidence | Limit |
| --- | --- | --- |
| WordPress 6.6 / PHP 8.2 / standard WordPress database | Automated database tests passed in the 1.0.2 release CI | Create/save/reload, stale revisions, reactivation, CLI imports, competing connection lock, archive/restore, exact import-backup recovery, failed-backup abort and anonymous REST denial; not a complete theme/browser journey |
| CI default WordPress / PHP 8.2 | Official Plugin Check and the same database tests passed in the 1.0.2 release CI | Zero Plugin Check errors; one reviewed read-only notice warning. CI version can change; consult the exact run |
| Shipped editor/frontend scripts in JSDOM | Automated regression tests passed in the 1.0.2 release CI | Preview switching, mobile drawer reset, keyboard tabs, Escape, local drafts, save failures, malformed transport, backup confirmation/stale recovery and translated controls; no browser layout or screen-reader claim |
| Local WordPress Playground visual captures | Admin screenshots were captured for 1.0.1 | SQLite cannot establish supported save/import/restore behavior; old editor screenshots require replacement |
| Classic-theme header placement | Recipe available in the user guide | Interactive end-to-end verification pending |
| Block-theme Header template placement | Recipe available in the user guide | Interactive end-to-end verification pending |
| Desktop/mobile browsers, keyboard and screen reader | Checks described in the user guide | Full interactive coverage pending; no WCAG conformance claim |
| Multisite, translated/RTL sites, caching/page builders | No verified matrix yet | Test on the intended installation; no blanket compatibility claim |

Release validation: [1.0.2 CI](https://github.com/camster91/ashbi-mega-menu/actions/runs/38067219278), runtime commit `3903f94e15cf88fa8fbeb1a52cf460f7ced74fdb`. Local backup-handler regressions also exercise denied capability and nonce access; these are PHP harness tests, not authenticated browser/HTTP tests. Fresh interactive captures and theme/accessibility verification remain pending.

## Record a verified configuration

For each additional tested combination, record plugin commit/version, WordPress/PHP/database versions, exact theme/version, browser/version, date, and a link to the test report. Exercise create → edit → save → reload → header placement → desktop/mobile destination → export/import → backup recovery. Include keyboard and screen-reader coverage separately; do not infer it from screenshots.

Theme recipes are starting instructions, not claims of native theme-menu-location integration. The plugin uses its own menu collection and explicit block/widget/shortcode/helper placement. It does not automatically replace a theme header or migrate another menu plugin's data.
