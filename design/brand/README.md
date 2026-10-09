# Ashbi Mega Menu visual identity

**Product name:** Ashbi Mega Menu  
**Tagline:** Navigation, made clear.

The mark combines a compact navigation list with an unfolding content panel. It is original vector artwork, supplied under the repository's GPL-2.0-or-later license. Keep the name and mark together in first-use contexts; the standalone icon works in square directory listings and WordPress editor controls.

| Token | Color | Use |
| --- | --- | --- |
| Cobalt | `#2457FF` | Mark, primary actions |
| Dark ink | `#192238` | Banner background, headings |
| Apricot | `#FFB568` | Panel motif, focus indicator, small accents |
| Paper | `#F8F9FD` | Light surfaces |
| Slate | `#57657E` | Secondary admin text |

Use system sans-serif typography in the admin: Segoe UI on Windows, the platform UI font elsewhere. Artwork specifies Segoe UI/Arial with a sans-serif fallback; no proprietary font files are distributed. Keep apricot decorative or pair it with dark text. Primary buttons use white text on cobalt. Preserve a clear space of at least one quarter of the icon width and a minimum display size of 24 pixels for the full icon.

## Files and rebuild

`icon.svg`, `banner.svg` and `social.svg` are editable sources. Run `npm run build:brand` to export directory icons at 128/256 pixels, banners at 772×250 and 1544×500, and a 1280×640 social preview. Runtime uses only the compact SVG mark in `assets/brand`; large marketing artwork stays outside the install ZIP.

The banner's navigation illustration is conceptual. Directory screenshots are genuine captures of the plugin running in a disposable local WordPress site with generic sample content. They are visual demonstrations; SQLite-backed preview saving is unsupported, while release save tests run on WordPress/MySQL in CI.

The public repository uses the banner in README. `social-preview.png` was applied as GitHub's social preview on October 9, 2026 and verified after reloading the repository settings. Regenerating the local file does not update that setting automatically.
