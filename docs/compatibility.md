# Compatibility and verification

Requirements: WordPress 6.6+, PHP 8.2+, standard MySQL/MariaDB. SQLite editing is unsupported because collection writes require advisory locks. WordPress.org approval and broad production compatibility are not established by a passing build.

## Verified release candidate 1.0.4 — 2026-10-13

| Configuration or workflow | Evidence | Limit |
| --- | --- | --- |
| WordPress 6.6 / PHP 8.2 / standard WordPress database | Automated database tests passed in the 1.0.4 release CI | Create/save/reload, stale revisions, reactivation, CLI imports, competing connection lock, archive/restore, exact import-backup recovery, failed-backup abort, signed/malformed notices and anonymous REST denial; not a complete theme/browser journey |
| CI default WordPress / PHP 8.2 | Official Plugin Check and the same database tests passed in the 1.0.4 release CI | CI version can change; consult the exact run |
| Shipped editor/frontend scripts in JSDOM | Automated regression tests passed in the 1.0.4 release CI | Preview switching, mobile drawer reset, keyboard tabs, Escape, local drafts, save failures, malformed transport, backup confirmation/stale recovery and translated controls; no browser layout or screen-reader claim |
| Local WordPress 7.1.3 / PHP 8.2.33 / MariaDB 11.4.8 / Twenty Twenty-Five / Chrome | Core 1.0.4 journey exercised in Chrome with the packaged plugin | Dashboard defaults, blank-menu creation, save/reload, desktop link navigation, 390x844 mobile drawer, Escape close/focus return and clean admin/frontend consoles; not broad theme or assistive-technology coverage |
| Chromium, Firefox and WebKit (Safari's engine) / WordPress latest / PHP 8.2 / Twenty Twenty-Five | Automated Playwright tests run on every push in the `browsers` CI job, one check per engine | Desktop hover then click, keyboard Enter and Escape with focus return, 390 px mobile drawer open/drill/back/close, and editor save and reload. WebKit on Linux is close to Safari but is not Safari on macOS or iOS; real Apple devices remain pending |
| Classic-theme header placement | Recipe available in the user guide | Broad interactive theme coverage pending |
| Block-theme Header template placement | Local block-theme placement covered with Twenty Twenty-Five | Other block themes and customized headers remain site-specific |
| Desktop/mobile browsers, keyboard and screen reader | Chrome desktop and responsive viewport checks completed; core journeys automated in Chromium, Firefox and WebKit | Real Safari, Edge, touch devices and screen readers pending; no WCAG conformance claim |
| Multisite, translated/RTL sites, caching/page builders | No verified matrix yet | Test on the intended installation; no blanket compatibility claim |

Release validation: [1.0.4 CI](https://github.com/camster91/ashbi-mega-menu/actions/runs/38441103680), runtime commit `9f398d1cf61c6baed7a91fc6f1e9cb2a79279a2c`. Local backup-handler regressions also exercise denied capability and nonce access; these are PHP harness tests, not authenticated browser/HTTP tests. Broader theme, browser and accessibility verification remain pending.

## Record a verified configuration

For each additional tested combination, record plugin commit/version, WordPress/PHP/database versions, exact theme/version, browser/version, date, and a link to the test report. Exercise create → edit → save → reload → header placement → desktop/mobile destination → export/import → backup recovery. Include keyboard and screen-reader coverage separately; do not infer it from screenshots.

Theme recipes are starting instructions, not claims of native theme-menu-location integration. The plugin uses its own menu collection and explicit block/widget/shortcode/helper placement. It does not automatically replace a theme header or migrate another menu plugin's data.
