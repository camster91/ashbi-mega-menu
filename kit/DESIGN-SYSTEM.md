# Ashbi plugin design system

One look and one way of working for every free Ashbi plugin: Ashbi Mega Menu, Ashbi Testimonials, Ashbi Portfolio Grid and Ashbi Subscriptions.

**The promise:** powerful features with a simple screen. Each plugin works on any theme and any builder, and nobody has to write code.

Ashbi Mega Menu is the reference. When this guide and Mega Menu disagree, follow Mega Menu and update this guide.

## 1. Principles

1. **Blend in on the front end.** Shortcodes and blocks use the site's own font, text color and link color. The plugin only adds layout, spacing and one accent color.
2. **Look like Ashbi in the admin.** Every admin screen uses the same header, cards, buttons and focus style, so users know straight away it's an Ashbi plugin.
3. **One way to place it, three ways to add it.** Each feature ships as a block, a shortcode and an Elementor-friendly shortcode. All three show the same output.
4. **Show, don't explain.** Shortcode builders show a live preview and a copy button. Empty states say what to do next in one sentence.
5. **Free and private.** No upsells, no tracking, no remote requests, no account needed.
6. **Safe to try.** Deactivating keeps content. Uninstall removes settings only when the user asks for it.

## 2. Naming

| Item | Rule | Example |
| --- | --- | --- |
| Product name | "Ashbi" + plain noun | Ashbi Portfolio Grid |
| Slug / folder / text domain | `ashbi-` + noun, all the same | `ashbi-portfolio-grid` |
| PHP prefix (functions, options, hooks, nonces) | 6-character `ashb**` code, unique per plugin | `ashbpg_`, `ashbte_`, `abmm_` (legacy) |
| CSS class and custom property prefix | Same code | `.ashbpg-grid`, `--ashbpg-accent` |
| Shortcode | `ashbi_` + noun | `[ashbi_portfolio]` |
| Block | `ashbi/<noun>` | `ashbi/portfolio-grid` |

Keep a renamed shortcode as an alias, so pages already using it don't break.

## 3. Brand tokens (admin and directory art)

| Token | Value | Use |
| --- | --- | --- |
| Cobalt | `#2457FF` | Primary buttons, links, active states, icon |
| Cobalt dark | `#1744DF` | Hover on primary buttons |
| Ink | `#192238` | Headings, banner background |
| Slate | `#57657E` | Secondary text |
| Line | `#DCE2EF` | Borders, dividers |
| Paper | `#F8F9FD` | Light surfaces, code chips |
| Apricot | `#FFB568` | Focus ring, small accents (never text on white) |
| OK / Warn / Danger | `#0F8A4B` / `#B45309` / `#C62828` | Status only |

Each plugin defines these on its own admin wrapper using its prefix (for example `--ashbte-blue`). Never put them on `:root` or `body`, so they never leak onto other plugins' screens.

**Admin type:** system UI font (`-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif`). We don't ship font files.

## 4. Admin components

All admin CSS is scoped to the plugin wrapper (`.ashbpg-wrap`, `.ashbte-wrap`, `.abmm-wrap`).

| Component | Spec |
| --- | --- |
| Header | 44px icon + H1 23px/700, letter-spacing -0.4px, ink. Intro line 13px slate. Primary action on the right, 36–38px tall. |
| Page links (tabs) | Pill group: white, 1px line border, 10px radius, 4px padding. Current item: `#EEF3FF` background + cobalt border. |
| Card | White, 1px line border, 12px radius, 18–24px padding, shadow `0 3px 14px #19223805`. |
| Stat card | Big number 28px/700 ink, label 12px slate. |
| Buttons | WordPress `.button`, radius 7px. Primary: cobalt with white text, no text shadow. |
| Focus | `outline: 3px solid #FFB568; outline-offset: 3px` plus a 1px ink ring. |
| Code / shortcode chip | Paper background, 1px line border, 6px radius, 12px mono. Always has a **Copy** button next to it. |
| Steps | Numbered list. Each step has a 3px cobalt left border. |
| Empty state | Icon, one sentence, one primary button. |
| Notices | Use WordPress notices inside the wrapper. Don't build custom alert colors. |

Responsive: under 782px the header wraps and buttons go full width. Support `forced-colors`: swap gradients for `Canvas`.

## 5. Front-end rules (shortcodes and blocks)

These are what make the plugins fit any theme.

1. **Inherit.** `font-family: inherit; color: inherit;`. Headings use the theme's heading styles: output real `h2`/`h3` tags with no font sizes set.
2. **One accent.** `--<prefix>-accent` defaults to `currentColor`-friendly cobalt `#2457FF` and can be changed per shortcode (`accent="#e11d48"`) or in settings. Use it only for active filters, stars, buttons and focus.
3. **Theme modes.** `theme="auto|light|dark"`. `auto` follows `prefers-color-scheme` only when the user picks it. The default is `light`, which inherits the page colors.
4. **Shared scale.**

| Token | Default |
| --- | --- |
| `--<p>-radius` | `12px` (cards), `999px` (pills) |
| `--<p>-gap` | `clamp(16px, 2.5vw, 24px)` |
| `--<p>-pad` | `clamp(16px, 3vw, 24px)` |
| `--<p>-cols` | `3`. Drops to 2 under 1024px and 1 under 640px |
| `--<p>-surface` | `color-mix(in srgb, currentColor 4%, transparent)` |
| `--<p>-border` | `color-mix(in srgb, currentColor 14%, transparent)` |
| `--<p>-shadow` | `0 1px 2px rgb(0 0 0 / .04), 0 6px 20px rgb(0 0 0 / .05)` |

`color-mix` with `currentColor` is what lets cards look right on both light and dark themes with no settings.

5. **Low specificity.** Wrap selectors in `:where()` so a theme or page builder can always override them with one simple rule.
6. **Load only when used.** Register assets, and enqueue them only when the shortcode or block renders.
7. **No layout side effects.** Never style `body`, `html`, theme headers or global elements.
8. **Accessible by default.** Real buttons, visible focus (apricot ring), `aria-pressed` on filters, native `<dialog>` for popups, `prefers-reduced-motion` respected, and WCAG AA contrast.
9. **Builder placeholders.** In Elementor/editor previews, render a simple placeholder card using the same tokens. Don't use inline grey boxes.

## 6. Shortcode attribute conventions

Use the same attribute names in every plugin:

| Attribute | Values |
| --- | --- |
| `layout` | `grid`, `masonry`, `slider`, `list` (plugin picks a subset) |
| `columns` | `1`–`6` |
| `limit` | number (`-1` = all) |
| `theme` | `light`, `dark`, `auto` |
| `accent` | hex color |
| `filters` | `show`, `hide` |
| `heading` | text |
| `class` | extra CSS classes |

Keep any older attribute names working as aliases.

## 7. Directory listing

- Icon: Ashbi mark style with the product's own pictogram, on Cobalt or Ink. 128px and 256px.
- Banner: Ink background, product name in white, a small Apricot accent. 772×250 and 1544×500.
- Screenshots: real captures from a clean WordPress site with neutral sample content. No client names.
- Readme: plain words, short sentences. Start with what it does, then three "Simple to start / Powerful / Safe" sections, then FAQ.
- "Tested up to" stays on the newest WordPress release. Update it when the release email arrives.

## 8. Release checklist (every plugin)

- [ ] Plugin Check: zero errors
- [ ] Unique prefix everywhere; text domain = slug
- [ ] All input sanitized, all output escaped, nonces + capability checks on every action
- [ ] No remote calls, tracking or upsells
- [ ] GPL-2.0-or-later license and third-party notices
- [ ] readme.txt matches header (versions, Requires at least/PHP, Stable tag)
- [ ] Works on a block theme, a classic theme and inside Elementor
- [ ] Screenshots and directory art from the shared templates
