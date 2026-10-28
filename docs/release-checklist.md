# Release checklist

Copy this list into the release pull request or tracking issue for each version and tick items only when they have been done for **that** version's commit. Link the evidence (CI run, screenshots, notes) next to each item. Checklist version: 1 (2026-10-28).

Release: `x.y.z` · Commit: `<sha>` · Release pull request: `#…` · Validation run: `<CI run URL>`

## 1. Version and notes

- [ ] Version bumped in `ashbi-mega-menu.php` (header and `ABMM_VERSION`), `package.json`, `src/blocks/mega-menu/block.json` and `readme.txt` (`Stable tag`); `npm run check:versions` passes.
- [ ] `Tested up to` in `readme.txt` matches the newest WordPress version actually tested (see the scheduled compatibility run).
- [ ] `CHANGELOG.md` and the `readme.txt` changelog describe user-facing changes, in plain language, with no claims beyond the evidence in `docs/compatibility.md` and `docs/accessibility.md`.
- [ ] Upgrade notice added to `readme.txt` if existing sites need to do anything.

## 2. Build and package

- [ ] Clean checkout of the release commit: `npm ci`, `npm run build:brand && npm run build:icons && npm run build`. The build changes nothing (`git status` is clean).
- [ ] `npm run package` produces `dist/ashbi-mega-menu-x.y.z.zip`, `manifest.json`, `SHA256SUMS` and `build-info.json`.
- [ ] Package contents reviewed against `dist/manifest.json`: plugin code, built assets, `readme.txt`, `LICENSE`, third-party notices and translations only. No tests, `node_modules`, source maps with private paths, `.wordpress-org/`, credentials or client content.
- [ ] `build-info.json` names the release commit and the CI run that built the artifact, and `shasum -a 256 -c SHA256SUMS` passes on the ZIP that will be published.

## 3. Automated checks (all on the release commit)

- [ ] Validate workflow green: JavaScript and PHP tests, PHP syntax, Plugin Check (no errors; warnings reviewed), WordPress database tests on current WordPress and on the minimum (6.6).
- [ ] Multisite network-uninstall jobs green.
- [ ] Browser tests green in Chromium, Firefox and WebKit, including `tests/e2e/a11y.spec.js`.
- [ ] Latest scheduled compatibility run green (PHP 8.2–8.5, WordPress minimum, latest and nightly), or each failure triaged and noted here.

## 4. Manual QA

On a staging site with MySQL or MariaDB, using the release ZIP (not the repository):

- [ ] Fresh install, activation and the starter menu.
- [ ] Update from the previous release: existing menus, products and branding load unchanged; export before and after match.
- [ ] Create → edit → save → reload → place (block and shortcode) → desktop and phone navigation → export/import → backup recovery.
- [ ] Current Chrome, Firefox and Safari; record exact versions. Edge if the release changes front-end behavior.
- [ ] Keyboard pass of the editor and public menu; spot-check with a screen reader if UI changed (record in `docs/accessibility.md`).
- [ ] Deactivate keeps data; delete removes it (on a disposable site).

## 5. Directory assets

- [ ] `.wordpress-org/` banners, icons and screenshots match the current UI; screenshot captions in `readme.txt` match the images (see `docs/wordpress-directory-assets.md`).

## 6. Publish

- [ ] Git tag `vx.y.z` on the release commit; GitHub release created with the ZIP, `SHA256SUMS` and `build-info.json` attached, and the changelog.
- [ ] WordPress.org (after directory approval): publishing the GitHub release runs **Deploy to WordPress.org**, which commits the unzipped release ZIP to `trunk`, tags it and updates `assets/` (see [wordpress-org-release.md](wordpress-org-release.md)). Approve the `wordpress-org` environment when asked.
- [ ] After publication: the workflow's `verify-wporg.js` step passed (version, banner, icon, screenshots and public ZIP contents match `manifest.json`; WordPress.org repackages, so contents are compared, not the hash), and the plugin page looks right.

## 7. After release

- [ ] Watch new issues and the support forum for a few days; triage with [support-policy.md](support-policy.md).
- [ ] Note any problem found after release here, with the follow-up issue.
