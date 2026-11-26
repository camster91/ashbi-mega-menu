# Ashbi UI kit

The shared look and builder for every free Ashbi plugin: Mega Menu, Testimonials, Portfolio Grid and Subscriptions. The rules behind it are in [DESIGN-SYSTEM.md](DESIGN-SYSTEM.md). The mockups are on the Ashbi Plugins design canvas.

This folder is source only. It is not part of the Mega Menu ZIP.

## What's inside

| File | What it does |
| --- | --- |
| `src/front.css` | Front-end tokens, the four presets (Minimal, Editorial, Bold, Glass), light, dark and auto themes, layouts (grid, masonry, bento, slider, list) and shared parts (card, pill, button, badge, avatar, placeholder). Every selector is inside `:where()`, so any theme can override it. |
| `src/admin.css` | The Studio admin screen: header, tabs, cards, steps, live preview, inspector, presets, swatches and the "Add to your site" box. |
| `src/studio.js` | The builder. Give it a list of fields and it draws the controls, writes the shortcode as you change them, and loads a live preview from your plugin. It has no dependencies. |
| `build.js` | Copies the kit into a plugin and swaps `__P__` for that plugin's prefix. |

## Add it to a plugin

```sh
node kit/build.js --prefix ashbpg --out ../ashbi-portfolio-grid/assets/ashbi-ui
```

Each plugin ships its own prefixed copy. With two Ashbi plugins active, `.ashbpg-card` and `.ashbte-card` never clash, and each plugin can update on its own schedule.

### Front end

Wrap the output in the kit class, then set the look, theme and accent:

```php
printf(
	'<div class="ashbpg-ui" data-ashbpg-look="%s" data-ashbpg-theme="%s" style="--ashbpg-accent:%s;--ashbpg-cols:%d">',
	esc_attr( $look ), esc_attr( $theme ), esc_attr( $accent ), (int) $columns
);
```

Then use the parts: `ashbpg-layout-grid`, `ashbpg-card`, `ashbpg-pill` (`aria-pressed`), `ashbpg-button`, `ashbpg-muted`.

### Admin Studio

```js
window.ashbpgStudio.init(document.getElementById('ashbpg-studio'), {
	shortcode: 'ashbi_portfolio',
	fields: [
		{ key: 'layout', type: 'seg', label: 'Layout', options: [['grid', 'Grid'], ['bento', 'Bento'], ['list', 'List']], default: 'grid' },
		{ key: 'look', type: 'preset', label: 'Look', default: 'minimal' },
		{ key: 'accent', type: 'swatch', label: 'Accent', default: '#2457ff' }
	],
	preview: { url: ajaxurl, action: 'ashbpg_preview', nonce: '…' }
});
```

The preview endpoint must check a nonce and the user's capability, sanitize every attribute, and return `wp_send_json_success( array( 'html' => do_shortcode( … ) ) )`.

## Shared shortcode attributes

Use the same names in every plugin: `layout`, `columns`, `limit`, `look`, `theme`, `accent`, `filters`, `heading`, `class`. Keep older names working as aliases.

## Test

```sh
node --test kit/test/*.test.js
```
