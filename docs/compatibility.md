# Compatibility and verification

Requirements: WordPress 6.6+, PHP 8.2+, standard MySQL/MariaDB. SQLite editing is unsupported because collection writes require advisory locks. WordPress.org approval and broad production compatibility are not established by a passing build.

## Evidence recorded before the current fixes

| Configuration or workflow | Evidence | Limit |
| --- | --- | --- |
| WordPress 6.6 / PHP 8.2 / standard WordPress database | Automated database smoke test passed in the 1.0.1 release CI | Selected backend operations; not a complete theme/browser journey |
| CI default WordPress / PHP 8.2 | Official Plugin Check and database smoke tests passed in the 1.0.1 release CI | CI version can change; consult the exact run |
| Local WordPress Playground visual captures | Admin screenshots were captured for 1.0.1 | SQLite cannot establish supported save/import/restore behavior; old editor screenshots require replacement |
| Classic-theme header placement | Recipe available in the user guide | Interactive end-to-end verification pending |
| Block-theme Header template placement | Recipe available in the user guide | Interactive end-to-end verification pending |
| Desktop/mobile browsers, keyboard and screen reader | Checks described in the user guide | Full interactive coverage pending; no WCAG conformance claim |
| Multisite, translated/RTL sites, caching/page builders | No verified matrix yet | Test on the intended installation; no blanket compatibility claim |

Historical release validation: [1.0.1 CI](https://github.com/camster91/ashbi-mega-menu/actions/runs/37983883613). Current changes require a new passing run and fresh evidence before updating the matrix.

## Record a verified configuration

For each additional tested combination, record plugin commit/version, WordPress/PHP/database versions, exact theme/version, browser/version, date, and a link to the test report. Exercise create → edit → save → reload → header placement → desktop/mobile destination → export/import → backup recovery. Include keyboard and screen-reader coverage separately; do not infer it from screenshots.

Theme recipes are starting instructions, not claims of native theme-menu-location integration. The plugin uses its own menu collection and explicit block/widget/shortcode/helper placement. It does not automatically replace a theme header or migrate another menu plugin's data.
