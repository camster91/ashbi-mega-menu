# Compatibility and verification

Requirements: WordPress 6.6+, PHP 8.2+, standard MySQL/MariaDB. SQLite has not been tested. WordPress.org approval and broad production compatibility are not established by a passing build.

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
| Multisite, translated/RTL sites, caching/page builders | Automated: see the matrix below, [translations, RTL and multisite](translations-rtl-multisite.md) | Representative setups, not a blanket compatibility claim |

Release validation: [1.0.4 CI](https://github.com/camster91/ashbi-mega-menu/actions/runs/38441103680), runtime commit `9f398d1cf61c6baed7a91fc6f1e9cb2a79279a2c`. Local backup-handler regressions also exercise denied capability and nonce access; these are PHP harness tests, not authenticated browser/HTTP tests. Broader theme, browser and accessibility verification remain pending.

## Automated compatibility matrix — 2026-10-28

Run on every pull request and push (`browsers` and `compat` jobs in `.github/workflows/validate.yml`), each on a fresh disposable site. CI logs the exact versions of every run; the versions and results below are from [this run](https://github.com/camster91/ashbi-mega-menu/actions/runs/40754353624) on commit `518bbd0`.

Environment: WordPress 7.1.4, PHP 8.2, MariaDB (wp-env), Playwright 1.64.

| Setup | Versions | Placement | What is exercised | Result |
| --- | --- | --- | --- | --- |
| Chromium | 156.0.8078.4 | Shortcode in a full-width group, Twenty Twenty-Five 1.5 | Desktop hover and click, keyboard Enter/Escape and focus return, phone drawer drill/back/close, editor save and reload, canvas and phone frame, axe accessibility rules, RTL and long strings | Pass |
| Firefox | 157.0 | as above | as above | Pass |
| WebKit (Safari's engine) | 27.2 | as above | as above | Pass |
| Microsoft Edge | current stable channel | as above | as above | Pass |
| Classic theme | Twenty Twenty-One 2.9 | Widget in `sidebar-1`, and shortcode in page content | Two menus on one page with unique IDs; each opens in the layout its width allows (both narrow placements use the phone layout, as designed); logged-out visitor; no console errors | Pass |
| Page builder | Elementor (latest stable) on Twenty Twenty-Five | Elementor's Shortcode widget on an "Elementor Full Width" page; no Elementor-specific integration | Desktop bar and phone drawer as a logged-out visitor; plus the generic desktop and editor journeys on the same site | Pass |
| Page cache + optimization + object cache | Cache Enabler 1.8.17, Autoptimize 3.1.17 (JS and CSS aggregation), SQLite Object Cache 1.6.6 (drop-in active) | Shortcode page | Second visit served from the page cache with aggregated scripts; the menu binds exactly once (one click opens, one closes); desktop and phone; the editor save journey with the object cache | Pass |

What this does **not** cover:

- **Safari on macOS and iOS** and **Chrome or Edge on Windows**: the engines run on Linux. Rendering is close but not identical; real-device checks remain pending.
- **Other themes and builders**: these are representative, not exhaustive. Placement inside a theme's narrow content column correctly shows the phone layout; put the menu in a full-width header area for the desktop bar.
- **Cache purging after a save**: page caches keep serving the old menu until they are cleared. Clear the page cache after saving a menu (see the `abmm_menu_saved` example in `docs/extending.md` to automate this).
- **Redis or Memcached** object caches: the SQLite Object Cache drop-in exercises the same persistent-cache code paths in WordPress, but not those servers.

## Record a verified configuration

For each additional tested combination, record plugin commit/version, WordPress/PHP/database versions, exact theme/version, browser/version, date, and a link to the test report. Exercise create → edit → save → reload → header placement → desktop/mobile destination → export/import → backup recovery. Include keyboard and screen-reader coverage separately; do not infer it from screenshots.

Theme recipes are starting instructions, not claims of native theme-menu-location integration. The plugin uses its own menu collection and explicit block/widget/shortcode/helper placement. It does not automatically replace a theme header or migrate another menu plugin's data.
