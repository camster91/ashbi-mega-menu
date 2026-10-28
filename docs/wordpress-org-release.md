# WordPress.org directory release

Tracks the first directory release (issue #1) and how every later release is published. The current submission is the single source of truth: answer the reviewer on the existing submission, never by submitting again.

## Status

| Date | Event | Notes |
| --- | --- | --- |
| before 2026-10-13 | Version 1.0.4 uploaded to the existing submission | Awaiting manual review |
| 2026-10-28 | Checked the public directory | `api.wordpress.org` reports "Plugin not found": not approved yet |

Add a row for every reviewer message and every reply. Reviewer emails come to the submitting account; the plugin team expects replies to the same email thread.

## Reviewer feedback log

| Received | Requested change | Fix (PR) | Regression test | Replied |
| --- | --- | --- | --- | --- |
| | | | | |

Handle each request with a narrowly scoped fix: one pull request per request where practical, a regression test where the request is about code, and a short plain reply listing what changed and where. Upload the updated ZIP to the same submission when asked. Keep `readme.txt` claims (compatibility, accessibility, "tested up to") limited to what [compatibility.md](compatibility.md) and [accessibility.md](accessibility.md) show.

## After approval: one-time setup

1. WordPress.org grants SVN access to the plugin's committers. In the GitHub repository settings add the secrets `SVN_USERNAME` and `SVN_PASSWORD` (the WordPress.org username and its SVN password from the profile's account settings).
2. Create a GitHub environment named `wordpress-org` with required reviewers, so every publish waits for a maintainer's approval.
3. Run **Actions → Deploy to WordPress.org → Run workflow** with *dry-run* on. It builds, tests and packages the release and prepares the SVN commit without making it.

The pinned deploy action requires SVN credentials and an existing directory repository even in dry-run mode. Do not add credentials or disable environment approval just to test a pull request. Before directory approval, validate the preparation locally:

```sh
npm ci
npm run check:versions
npm run build:brand
npm run build:icons
npm run build
git diff --exit-code -- build src assets
npm test
npm run test:php
npm run package
(cd dist && shasum -a 256 -c SHA256SUMS)
```

Run these commands in a shell with `set -e` so a failure stops preparation. `npm run test:release` injects failures into the actual workflow commands and checks public ZIP verification against isolated fixtures. It makes no directory requests. This validates preparation and verification, while the full SVN dry run still requires the approved setup above.

## Publishing a release

Follow [release-checklist.md](release-checklist.md). The publish step is automated:

1. Merge the release pull request; `npm run check:versions` (also in CI) confirms the version agrees in the plugin header, `ABMM_VERSION`, `package.json`, `block.json` and the readme's Stable tag, with a changelog entry.
2. Create a GitHub release with tag `vX.Y.Z`. The **Deploy to WordPress.org** workflow (`.github/workflows/deploy-wporg.yml`) then, after the environment approval:
   - checks the tag matches the version;
   - rebuilds from source, confirms the build matches the committed files, runs the tests and packages the ZIP;
   - commits the unzipped package to `trunk`, tags it, and updates the directory `assets/` from `.wordpress-org/`;
   - attaches the ZIP, `SHA256SUMS`, `build-info.json` and `manifest.json` to the GitHub release;
   - runs `scripts/verify-wporg.js --wait`, which waits until WordPress.org shows the new version, banner, icon and screenshots, and the public ZIP has exactly the local release ZIP's filenames and uncompressed file bytes. ZIP timestamps, entry order, compression, comments and directory entries may differ.
3. Look at the plugin page yourself as a final check.

To check the live directory at any time: `npm run package && node scripts/verify-wporg.js`. Keep the local versioned release ZIP together with `dist/manifest.json`; verification fails if either is missing or their file lists disagree.
