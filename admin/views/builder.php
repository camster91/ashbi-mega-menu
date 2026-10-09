<?php
/**
 * Admin: visual mega menu builder.
 *
 * @package Ashbi_Mega_Menu
 *
 * @var string $edit_id Menu ID being edited.
 * @var array  $menus   All menus.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$menu          = $menus[ $edit_id ];
$abmm_menu_revision = ABMM_Data::instance()->revision( $menu );
$menu['settings'] = wp_parse_args( $menu['settings'] ?? array(), ABMM_Data::default_settings() );
$menu['brand'] = wp_parse_args(
	$menu['brand'] ?? array(),
	array(
		'show'          => false,
		'title'         => get_bloginfo( 'name' ),
		'url'           => home_url( '/' ),
		'logo_url'      => '',
		'logo_dark_url' => '',
		'alt'           => '',
		'logo_height'   => 40,
		'target'        => '',
		'rel'           => '',
		'class'         => '',
	)
);
$menu['context'] = wp_parse_args(
	$menu['context'] ?? array(),
	array(
		'show'  => false,
		'label' => '',
		'url'   => '',
	)
);
$abmm_menu_readiness = ABMM_Data::instance()->readiness( $menu );
?>
<div class="wrap abmm-wrap abmm-builder-wrap">
	<?php require ABMM_PLUGIN_DIR . 'admin/views/brand-strip.php'; ?>
	<div class="abmm-builder-load-error" id="abmm-builder-load-error" role="alert" tabindex="-1" hidden>
		<h2 id="abmm-builder-load-error-title" tabindex="-1"><?php esc_html_e( 'This menu could not be loaded safely', 'ashbi-mega-menu' ); ?></h2>
		<p id="abmm-builder-load-error-message"><?php esc_html_e( 'Editing and saving are disabled. Your stored menu content was not changed.', 'ashbi-mega-menu' ); ?></p>
		<p>
			<button type="button" class="button button-primary abmm-retry-builder-load"><?php esc_html_e( 'Reload menu', 'ashbi-mega-menu' ); ?></button>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=ashbi-mega-menu' ) ); ?>"><?php esc_html_e( 'Return to All Menus', 'ashbi-mega-menu' ); ?></a>
		</p>
	</div>

	<div class="abmm-draft-recovery" id="abmm-draft-recovery" role="status" hidden>
		<h2><?php esc_html_e( 'Unsaved local draft found', 'ashbi-mega-menu' ); ?></h2>
		<p class="abmm-draft-recovery__message"></p>
		<button type="button" class="button button-primary abmm-restore-draft"><?php esc_html_e( 'Restore local draft', 'ashbi-mega-menu' ); ?></button>
		<button type="button" class="button abmm-discard-draft"><?php esc_html_e( 'Discard local draft', 'ashbi-mega-menu' ); ?></button>
	</div>

	<div class="abmm-builder-topbar">
		<a class="abmm-back abmm-guarded-exit" href="<?php echo esc_url( admin_url( 'admin.php?page=ashbi-mega-menu' ) ); ?>">
			← <?php esc_html_e( 'All Menus', 'ashbi-mega-menu' ); ?>
		</a>
		<div class="abmm-builder-topbar__center">
			<label class="screen-reader-text" for="abmm-menu-title"><?php esc_html_e( 'Menu name', 'ashbi-mega-menu' ); ?></label>
			<input type="text" id="abmm-menu-title" class="abmm-menu-title-input" value="<?php echo esc_attr( $menu['title'] ); ?>" placeholder="<?php esc_attr_e( 'Menu name', 'ashbi-mega-menu' ); ?>" />
		</div>
		<div class="abmm-builder-topbar__actions">
			<button type="button" class="button abmm-export-current-menu" data-menu-id="<?php echo esc_attr( $edit_id ); ?>" title="<?php esc_attr_e( 'Export this menu as JSON', 'ashbi-mega-menu' ); ?>">
				<span class="dashicons dashicons-download" style="margin-top:3px"></span>
				<?php esc_html_e( 'Export', 'ashbi-mega-menu' ); ?>
			</button>
			<span class="abmm-save-status is-visible" role="status" aria-live="polite"></span>
			<button type="button" class="button button-primary button-hero abmm-save-menu">
				<?php esc_html_e( 'Save Menu', 'ashbi-mega-menu' ); ?>
			</button>
		</div>
	</div>

	<script type="application/json" id="abmm-menu-data"><?php echo wp_json_encode( $menu, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
	<div class="abmm-builder" id="abmm-builder"
		data-menu-id="<?php echo esc_attr( $edit_id ); ?>"
		data-menu-revision="<?php echo esc_attr( $abmm_menu_revision ); ?>">

		<aside class="abmm-builder__sidebar" id="abmm-builder-sidebar" aria-label="<?php esc_attr_e( 'Menu settings', 'ashbi-mega-menu' ); ?>">
			<div class="abmm-builder-sidebar__mobile-head">
				<strong><?php esc_html_e( 'Menu settings', 'ashbi-mega-menu' ); ?></strong>
				<button type="button" class="button-link abmm-builder-panel-close"><?php esc_html_e( 'Close', 'ashbi-mega-menu' ); ?></button>
			</div>
			<nav class="abmm-builder-sections" role="tablist" aria-label="<?php esc_attr_e( 'Editor sections', 'ashbi-mega-menu' ); ?>">
				<button type="button" class="is-active" role="tab" id="abmm-section-tab-content" data-abmm-builder-section="content" aria-controls="abmm-section-panel-content" aria-selected="true" tabindex="0"><?php esc_html_e( 'Content', 'ashbi-mega-menu' ); ?></button>
				<button type="button" role="tab" id="abmm-section-tab-design" data-abmm-builder-section="design" aria-controls="abmm-section-panel-design" aria-selected="false" tabindex="-1"><?php esc_html_e( 'Design', 'ashbi-mega-menu' ); ?></button>
				<button type="button" role="tab" id="abmm-section-tab-settings" data-abmm-builder-section="settings" aria-controls="abmm-section-panel-settings" aria-selected="false" tabindex="-1"><?php esc_html_e( 'Settings', 'ashbi-mega-menu' ); ?></button>
				<button type="button" role="tab" id="abmm-section-tab-help" data-abmm-builder-section="help" aria-controls="abmm-section-panel-help" aria-selected="false" tabindex="-1"><?php esc_html_e( 'Help', 'ashbi-mega-menu' ); ?></button>
			</nav>
			<div class="abmm-builder-section-group" id="abmm-section-panel-settings" data-abmm-builder-section-panel="settings" role="tabpanel" aria-labelledby="abmm-section-tab-settings" hidden>
			<section class="abmm-builder-panel">
				<h2><?php esc_html_e( 'Mobile navigation', 'ashbi-mega-menu' ); ?></h2>
				<label class="abmm-field"><span><?php esc_html_e( 'Enhanced mobile menu', 'ashbi-mega-menu' ); ?></span><input type="checkbox" id="abmm-mobile-enhancements" <?php checked( ! empty( $menu['settings']['mobile_enhancements'] ) ); ?> /></label>
				<p class="description"><?php esc_html_e( 'Add menu search, full-width product rows, and a visible Back label. Disabled by default to preserve existing menus. Review your mobile preview before enabling.', 'ashbi-mega-menu' ); ?></p>
			</section>
			<section class="abmm-builder-panel abmm-brand-panel">
				<h2><?php esc_html_e( 'Brand Identity', 'ashbi-mega-menu' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Optional. Disabled by default so existing menus keep their current output.', 'ashbi-mega-menu' ); ?></p>
				<label class="abmm-field"><span><?php esc_html_e( 'Show brand', 'ashbi-mega-menu' ); ?></span><input type="checkbox" id="abmm-brand-show" <?php checked( ! empty( $menu['brand']['show'] ) ); ?> /></label>
				<div class="abmm-optional-fields abmm-brand-fields">
					<label class="abmm-field"><span><?php esc_html_e( 'Site title', 'ashbi-mega-menu' ); ?></span><input type="text" id="abmm-brand-title" value="<?php echo esc_attr( $menu['brand']['title'] ); ?>" /></label>
					<label class="abmm-field"><span><?php esc_html_e( 'Brand URL', 'ashbi-mega-menu' ); ?></span><input type="url" id="abmm-brand-url" value="<?php echo esc_attr( $menu['brand']['url'] ); ?>" /></label>
					<label class="abmm-field"><span><?php esc_html_e( 'Logo image URL', 'ashbi-mega-menu' ); ?></span><input type="url" id="abmm-brand-logo-url" value="<?php echo esc_attr( $menu['brand']['logo_url'] ); ?>" placeholder="https://…" /></label>
					<label class="abmm-field"><span><?php esc_html_e( 'Dark-preset logo URL', 'ashbi-mega-menu' ); ?></span><input type="url" id="abmm-brand-logo-dark-url" value="<?php echo esc_attr( $menu['brand']['logo_dark_url'] ); ?>" placeholder="<?php esc_attr_e( 'Optional alternate logo', 'ashbi-mega-menu' ); ?>" /></label>
					<label class="abmm-field"><span><?php esc_html_e( 'Logo alternative text', 'ashbi-mega-menu' ); ?></span><input type="text" id="abmm-brand-alt" value="<?php echo esc_attr( $menu['brand']['alt'] ); ?>" /></label>
					<label class="abmm-field"><span><?php esc_html_e( 'Logo height (px)', 'ashbi-mega-menu' ); ?></span><input type="number" id="abmm-brand-logo-height" min="16" max="80" value="<?php echo esc_attr( $menu['brand']['logo_height'] ); ?>" /></label>
				</div>
			</section>
			<section class="abmm-builder-panel abmm-product-navigation-panel">
				<h2><?php esc_html_e( 'Product Navigation', 'ashbi-mega-menu' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Keep this shared menu focused on site-wide navigation. Manage reusable product links and product hubs separately, then use the context-aware shortcode in one header placement.', 'ashbi-mega-menu' ); ?></p>
				<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=ashbi-mega-menu-products' ) ); ?>"><?php esc_html_e( 'Manage product navigation', 'ashbi-mega-menu' ); ?></a></p>
			</section>
			</div>

			<div class="abmm-builder-section-group" id="abmm-section-panel-content" data-abmm-builder-section-panel="content" role="tabpanel" aria-labelledby="abmm-section-tab-content">
			<?php if ( ! empty( $menu['starter_source'] ) ) : ?>
				<section class="abmm-builder-panel abmm-sample-warning">
					<h2><?php esc_html_e( 'Sample template', 'ashbi-mega-menu' ); ?></h2>
					<p><?php esc_html_e( 'Replace the sample labels, URLs, logo, and call-to-action. Verify every destination before placement.', 'ashbi-mega-menu' ); ?></p>
				</section>
			<?php endif; ?>
			<section class="abmm-builder-panel abmm-menu-structure-panel">
				<h2><?php esc_html_e( 'Top Menu Items', 'ashbi-mega-menu' ); ?></h2>
				<p class="description"><?php esc_html_e( 'These appear in the header bar. Drag to reorder.', 'ashbi-mega-menu' ); ?></p>
				<ul class="abmm-sortable abmm-nav-items" id="abmm-nav-items" tabindex="-1"></ul>
				<button type="button" class="button abmm-add-nav-item">
					+ <?php esc_html_e( 'Add Menu Item', 'ashbi-mega-menu' ); ?>
				</button>
			</section>

			<section class="abmm-builder-panel abmm-cta-panel">
				<h2><?php esc_html_e( 'Call-to-Action Button', 'ashbi-mega-menu' ); ?></h2>
				<label class="abmm-field">
					<span><?php esc_html_e( 'Show button', 'ashbi-mega-menu' ); ?></span>
					<input type="checkbox" id="abmm-cta-show" <?php checked( ! empty( $menu['cta']['show'] ) ); ?> />
				</label>
				<div class="abmm-optional-fields abmm-cta-fields">
				<label class="abmm-field">
					<span><?php esc_html_e( 'Button text', 'ashbi-mega-menu' ); ?></span>
					<input type="text" id="abmm-cta-label" value="<?php echo esc_attr( $menu['cta']['label'] ?? '' ); ?>" />
				</label>
				<label class="abmm-field">
					<span><?php esc_html_e( 'Button URL', 'ashbi-mega-menu' ); ?></span>
					<span class="abmm-wp-link">
						<input type="text" id="abmm-cta-url" class="abmm-url-field" value="<?php echo esc_attr( $menu['cta']['url'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Search or type URL', 'ashbi-mega-menu' ); ?>" />
						<button type="button" class="button abmm-pick-url" title="<?php esc_attr_e( 'Select / edit link', 'ashbi-mega-menu' ); ?>" data-abmm-url="#abmm-cta-url" data-abmm-text="#abmm-cta-label">
							<span class="dashicons dashicons-admin-links" aria-hidden="true"></span>
						</button>
					</span>
				</label>
				<details class="abmm-link-options">
					<summary><?php esc_html_e( 'Link options', 'ashbi-mega-menu' ); ?></summary>
					<label class="abmm-field"><span><?php esc_html_e( 'Open in new tab', 'ashbi-mega-menu' ); ?></span><input type="checkbox" id="abmm-cta-target" <?php checked( '_blank' === ( $menu['cta']['target'] ?? '' ) ); ?> /></label>
					<label class="abmm-field"><span><?php esc_html_e( 'Relationship', 'ashbi-mega-menu' ); ?></span><input type="text" id="abmm-cta-rel" value="<?php echo esc_attr( $menu['cta']['rel'] ?? '' ); ?>" placeholder="nofollow sponsored" /></label>
					<label class="abmm-field"><span><?php esc_html_e( 'CSS classes', 'ashbi-mega-menu' ); ?></span><input type="text" id="abmm-cta-class" value="<?php echo esc_attr( $menu['cta']['class'] ?? '' ); ?>" /></label>
				</details>
				</div>
			</section>

			<section class="abmm-builder-panel abmm-readiness-panel" id="abmm-readiness-panel" aria-labelledby="abmm-readiness-title">
				<h2 id="abmm-readiness-title"><?php esc_html_e( 'Menu readiness', 'ashbi-mega-menu' ); ?></h2>
				<p class="abmm-readiness-summary" role="status" aria-live="polite"></p>
				<ul class="abmm-readiness-issues"></ul>
			</section>
			</div>

			<div class="abmm-builder-section-group" id="abmm-section-panel-design" data-abmm-builder-section-panel="design" role="tabpanel" aria-labelledby="abmm-section-tab-design" hidden>
			<section class="abmm-builder-panel abmm-design-panel">
				<h2><?php esc_html_e( 'Design', 'ashbi-mega-menu' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Pick a preset, then fine-tune colors and layout. Preview updates live.', 'ashbi-mega-menu' ); ?></p>

				<?php
				$abmm_settings = wp_parse_args( $menu['settings'] ?? array(), ABMM_Data::default_settings() );
				$abmm_presets  = ABMM_Data::design_presets();
				?>

				<label class="abmm-field">
					<span><?php esc_html_e( 'Header composition', 'ashbi-mega-menu' ); ?></span>
					<select id="abmm-presentation">
						<option value="stacked" <?php selected( $abmm_settings['presentation'], 'stacked' ); ?>><?php esc_html_e( 'Classic stacked (default)', 'ashbi-mega-menu' ); ?></option>
						<option value="unified" <?php selected( $abmm_settings['presentation'], 'unified' ); ?>><?php esc_html_e( 'Unified product row', 'ashbi-mega-menu' ); ?></option>
					</select>
					<span class="description"><?php esc_html_e( 'Unified product row is used only on a contextual product draft or preview. Published pages safely retain the Classic stacked header until release approval.', 'ashbi-mega-menu' ); ?></span>
				</label>

				<div class="abmm-field">
					<span><?php esc_html_e( 'Style preset', 'ashbi-mega-menu' ); ?></span>
					<div class="abmm-preset-grid" id="abmm-preset-grid">
						<?php foreach ( $abmm_presets as $abmm_key => $abmm_preset ) : ?>
							<?php $abmm_v = $abmm_preset['values']; ?>
							<button
								type="button"
								class="abmm-preset-card<?php echo ( $abmm_settings['preset'] === $abmm_key ) ? ' is-active' : ''; ?>"
								data-preset="<?php echo esc_attr( $abmm_key ); ?>"
								data-values="<?php echo esc_attr( wp_json_encode( $abmm_v ) ); ?>"
								title="<?php echo esc_attr( $abmm_preset['label'] ); ?>"
							>
								<span class="abmm-preset-card__swatches" aria-hidden="true">
									<span style="background:<?php echo esc_attr( $abmm_v['header_bg'] ); ?>"></span>
									<span style="background:<?php echo esc_attr( $abmm_v['sidebar_bg'] ); ?>"></span>
									<span style="background:<?php echo esc_attr( $abmm_v['accent'] ); ?>"></span>
								</span>
								<span class="abmm-preset-card__label"><?php echo esc_html( $abmm_preset['label'] ); ?></span>
							</button>
						<?php endforeach; ?>
						<div class="abmm-preset-card abmm-preset-card--custom<?php echo ( 'custom' === $abmm_settings['preset'] ) ? ' is-active' : ''; ?>" data-preset="custom" title="<?php esc_attr_e( 'Custom styling appears after you edit preset colors.', 'ashbi-mega-menu' ); ?>">
							<span class="abmm-preset-card__swatches abmm-preset-card__swatches--custom" aria-hidden="true">
								<span></span><span></span><span></span>
							</span>
							<span class="abmm-preset-card__label"><?php esc_html_e( 'Custom', 'ashbi-mega-menu' ); ?></span>
						</div>
					</div>
					<input type="hidden" id="abmm-preset" value="<?php echo esc_attr( $abmm_settings['preset'] ); ?>" />
				</div>

				<label class="abmm-field">
					<span><?php esc_html_e( 'Category bar position', 'ashbi-mega-menu' ); ?></span>
					<select id="abmm-layout">
						<option value="sidebar-left" <?php selected( $abmm_settings['layout'], 'sidebar-left' ); ?>><?php esc_html_e( 'Left (classic Platforms)', 'ashbi-mega-menu' ); ?></option>
						<option value="sidebar-right" <?php selected( $abmm_settings['layout'], 'sidebar-right' ); ?>><?php esc_html_e( 'Right', 'ashbi-mega-menu' ); ?></option>
						<option value="stacked" <?php selected( $abmm_settings['layout'], 'stacked' ); ?>><?php esc_html_e( 'Top (horizontal tabs)', 'ashbi-mega-menu' ); ?></option>
					</select>
					<span class="description"><?php esc_html_e( 'Only applies to Platforms mega items. For a simple 2/3-column mega with no category bar, open Edit Mega Content and choose "Simple columns".', 'ashbi-mega-menu' ); ?></span>
				</label>

				<label class="abmm-field">
					<span><?php esc_html_e( 'Nav link alignment', 'ashbi-mega-menu' ); ?></span>
					<select id="abmm-nav-align">
						<option value="left" <?php selected( $abmm_settings['nav_align'], 'left' ); ?>><?php esc_html_e( 'Left', 'ashbi-mega-menu' ); ?></option>
						<option value="center" <?php selected( $abmm_settings['nav_align'], 'center' ); ?>><?php esc_html_e( 'Center', 'ashbi-mega-menu' ); ?></option>
						<option value="right" <?php selected( $abmm_settings['nav_align'], 'right' ); ?>><?php esc_html_e( 'Right', 'ashbi-mega-menu' ); ?></option>
					</select>
				</label>

				<label class="abmm-field">
					<span><?php esc_html_e( 'Default grid columns', 'ashbi-mega-menu' ); ?></span>
					<select id="abmm-grid-cols">
						<?php for ( $abmm_i = 1; $abmm_i <= 4; $abmm_i++ ) : ?>
							<option value="<?php echo (int) $abmm_i; ?>" <?php selected( (int) $abmm_settings['grid_columns'], $abmm_i ); ?>><?php echo (int) $abmm_i; ?></option>
						<?php endfor; ?>
					</select>
					<span class="description"><?php esc_html_e( 'Fallback default. Each mega item can override columns in Edit Mega Content.', 'ashbi-mega-menu' ); ?></span>
				</label>

				<details class="abmm-design-more">
					<summary><?php esc_html_e( 'Top menu item colors', 'ashbi-mega-menu' ); ?></summary>
					<label class="abmm-field">
						<span><?php esc_html_e( 'Menu link color', 'ashbi-mega-menu' ); ?></span>
						<input type="text" class="abmm-color" id="abmm-nav-link-color" value="<?php echo esc_attr( $abmm_settings['nav_link_color'] ); ?>" />
					</label>
					<label class="abmm-field">
						<span><?php esc_html_e( 'Menu hover / open color', 'ashbi-mega-menu' ); ?></span>
						<input type="text" class="abmm-color" id="abmm-nav-hover-color" value="<?php echo esc_attr( $abmm_settings['nav_hover_color'] ); ?>" />
					</label>
				</details>

				<details class="abmm-design-more">
					<summary><?php esc_html_e( 'Icon styling', 'ashbi-mega-menu' ); ?></summary>
					<label class="abmm-field abmm-field--toggle">
						<span><?php esc_html_e( 'Inherit menu text colour', 'ashbi-mega-menu' ); ?></span>
						<input type="checkbox" id="abmm-icon-inherit-text" <?php checked( ! empty( $abmm_settings['icon_inherit_text'] ) ); ?> />
					</label>
					<label class="abmm-field" id="abmm-icon-color-field">
						<span><?php esc_html_e( 'Default icon colour', 'ashbi-mega-menu' ); ?></span>
						<input type="text" class="abmm-color" id="abmm-icon-color" value="<?php echo esc_attr( $abmm_settings['icon_color'] ); ?>" />
					</label>
					<label class="abmm-field">
						<span><?php esc_html_e( 'Hover / focus colour', 'ashbi-mega-menu' ); ?></span>
						<input type="text" class="abmm-color" id="abmm-icon-hover-color" value="<?php echo esc_attr( $abmm_settings['icon_hover_color'] ); ?>" />
					</label>
					<label class="abmm-field">
						<span><?php esc_html_e( 'Current / active colour', 'ashbi-mega-menu' ); ?></span>
						<input type="text" class="abmm-color" id="abmm-icon-active-color" value="<?php echo esc_attr( $abmm_settings['icon_active_color'] ); ?>" />
					</label>
					<label class="abmm-field">
						<span><?php esc_html_e( 'Icon size (px)', 'ashbi-mega-menu' ); ?></span>
						<input type="number" id="abmm-icon-size" min="12" max="64" step="1" value="<?php echo esc_attr( $abmm_settings['icon_size'] ); ?>" />
					</label>
					<label class="abmm-field">
						<span><?php esc_html_e( 'Icon background', 'ashbi-mega-menu' ); ?></span>
						<input type="text" class="abmm-color" id="abmm-icon-background" value="<?php echo esc_attr( $abmm_settings['icon_background'] ); ?>" />
					</label>
					<label class="abmm-field">
						<span><?php esc_html_e( 'Icon border colour', 'ashbi-mega-menu' ); ?></span>
						<input type="text" class="abmm-color" id="abmm-icon-border-color" value="<?php echo esc_attr( $abmm_settings['icon_border_color'] ); ?>" />
					</label>
					<label class="abmm-field">
						<span><?php esc_html_e( 'Icon corner radius (px)', 'ashbi-mega-menu' ); ?></span>
						<input type="number" id="abmm-icon-radius" min="0" max="24" step="1" value="<?php echo esc_attr( $abmm_settings['icon_radius'] ); ?>" />
					</label>
					<p class="description"><?php esc_html_e( 'SVG icons use these colours. Uploaded PNG, JPG and WebP artwork keeps its original pixels while using the shared size, background, border and radius.', 'ashbi-mega-menu' ); ?></p>
				</details>

				<details class="abmm-design-more">
					<summary><?php esc_html_e( 'Panel & brand colors', 'ashbi-mega-menu' ); ?></summary>
					<label class="abmm-field">
						<span><?php esc_html_e( 'Header background', 'ashbi-mega-menu' ); ?></span>
						<input type="text" class="abmm-color" id="abmm-header-bg" value="<?php echo esc_attr( $abmm_settings['header_bg'] ); ?>" />
					</label>
					<label class="abmm-field">
						<span><?php esc_html_e( 'Sidebar background', 'ashbi-mega-menu' ); ?></span>
						<input type="text" class="abmm-color" id="abmm-sidebar-bg" value="<?php echo esc_attr( $abmm_settings['sidebar_bg'] ); ?>" />
					</label>
					<label class="abmm-field">
						<span><?php esc_html_e( 'Active category background', 'ashbi-mega-menu' ); ?></span>
						<input type="text" class="abmm-color" id="abmm-active-bg" value="<?php echo esc_attr( $abmm_settings['active_bg'] ); ?>" />
					</label>
					<label class="abmm-field">
						<span><?php esc_html_e( 'Panel background', 'ashbi-mega-menu' ); ?></span>
						<input type="text" class="abmm-color" id="abmm-panel-bg" value="<?php echo esc_attr( $abmm_settings['panel_bg'] ); ?>" />
					</label>
					<label class="abmm-field">
						<span><?php esc_html_e( 'Accent / button color', 'ashbi-mega-menu' ); ?></span>
						<input type="text" class="abmm-color" id="abmm-accent" value="<?php echo esc_attr( $abmm_settings['accent'] ); ?>" />
					</label>
					<label class="abmm-field">
						<span><?php esc_html_e( 'Button text color', 'ashbi-mega-menu' ); ?></span>
						<input type="text" class="abmm-color" id="abmm-cta-text" value="<?php echo esc_attr( $abmm_settings['cta_text'] ); ?>" />
					</label>
					<label class="abmm-field">
						<span><?php esc_html_e( 'Panel text color', 'ashbi-mega-menu' ); ?></span>
						<input type="text" class="abmm-color" id="abmm-text-color" value="<?php echo esc_attr( $abmm_settings['text_color'] ); ?>" />
					</label>
					<label class="abmm-field">
						<span><?php esc_html_e( 'Muted / description color', 'ashbi-mega-menu' ); ?></span>
						<input type="text" class="abmm-color" id="abmm-muted-color" value="<?php echo esc_attr( $abmm_settings['muted_color'] ); ?>" />
					</label>
					<label class="abmm-field">
						<span><?php esc_html_e( 'Border color', 'ashbi-mega-menu' ); ?></span>
						<input type="text" class="abmm-color" id="abmm-border-color" value="<?php echo esc_attr( $abmm_settings['border_color'] ); ?>" />
					</label>
				</details>

				<details class="abmm-design-more">
					<summary><?php esc_html_e( 'Layout & style', 'ashbi-mega-menu' ); ?></summary>
					<label class="abmm-field abmm-field--toggle">
						<span><?php esc_html_e( 'Full width mega menu', 'ashbi-mega-menu' ); ?></span>
						<input type="checkbox" id="abmm-full-width" <?php checked( ! empty( $abmm_settings['full_width'] ) ); ?> />
					</label>
					<label class="abmm-field abmm-field--toggle">
						<span><?php esc_html_e( 'Transparent header surface', 'ashbi-mega-menu' ); ?></span>
						<input type="checkbox" id="abmm-header-transparent" <?php checked( ! empty( $abmm_settings['header_transparent'] ) ); ?> />
					</label>
					<p class="description"><?php esc_html_e( 'Use a transparent surface when this menu sits inside an existing hero or product-header background.', 'ashbi-mega-menu' ); ?></p>
					<label class="abmm-field">
						<span><?php esc_html_e( 'Header stacking order', 'ashbi-mega-menu' ); ?></span>
						<input type="number" id="abmm-header-z-index" min="0" max="99999" step="1" value="<?php echo esc_attr( $abmm_settings['header_z_index'] ); ?>" />
						<small class="description"><?php esc_html_e( 'Use a lower value for a secondary product menu so the main site navigation opens above it.', 'ashbi-mega-menu' ); ?></small>
					</label>
					<label class="abmm-field" id="abmm-panel-width-field">
						<span><?php esc_html_e( 'Panel width (px)', 'ashbi-mega-menu' ); ?></span>
						<input type="number" id="abmm-panel-width" min="640" max="1400" step="20" value="<?php echo esc_attr( $abmm_settings['panel_width'] ); ?>" <?php disabled( ! empty( $abmm_settings['full_width'] ) ); ?> />
					</label>
					<label class="abmm-field">
						<span><?php esc_html_e( 'Sidebar width (px)', 'ashbi-mega-menu' ); ?></span>
						<input type="number" id="abmm-sidebar-width" min="180" max="420" step="10" value="<?php echo esc_attr( $abmm_settings['sidebar_width'] ); ?>" />
					</label>
					<label class="abmm-field">
						<span><?php esc_html_e( 'Corner radius (px)', 'ashbi-mega-menu' ); ?></span>
						<input type="number" id="abmm-border-radius" min="0" max="24" step="1" value="<?php echo esc_attr( $abmm_settings['border_radius'] ); ?>" />
					</label>
					<label class="abmm-field">
						<span><?php esc_html_e( 'Shadow', 'ashbi-mega-menu' ); ?></span>
						<select id="abmm-shadow">
							<option value="none" <?php selected( $abmm_settings['shadow'], 'none' ); ?>><?php esc_html_e( 'None', 'ashbi-mega-menu' ); ?></option>
							<option value="soft" <?php selected( $abmm_settings['shadow'], 'soft' ); ?>><?php esc_html_e( 'Soft', 'ashbi-mega-menu' ); ?></option>
							<option value="medium" <?php selected( $abmm_settings['shadow'], 'medium' ); ?>><?php esc_html_e( 'Medium', 'ashbi-mega-menu' ); ?></option>
							<option value="strong" <?php selected( $abmm_settings['shadow'], 'strong' ); ?>><?php esc_html_e( 'Strong', 'ashbi-mega-menu' ); ?></option>
						</select>
					</label>
					<label class="abmm-field">
						<span><?php esc_html_e( 'CTA button shape', 'ashbi-mega-menu' ); ?></span>
						<select id="abmm-cta-style">
							<option value="square" <?php selected( $abmm_settings['cta_style'], 'square' ); ?>><?php esc_html_e( 'Square', 'ashbi-mega-menu' ); ?></option>
							<option value="rounded" <?php selected( $abmm_settings['cta_style'], 'rounded' ); ?>><?php esc_html_e( 'Rounded', 'ashbi-mega-menu' ); ?></option>
							<option value="pill" <?php selected( $abmm_settings['cta_style'], 'pill' ); ?>><?php esc_html_e( 'Pill', 'ashbi-mega-menu' ); ?></option>
						</select>
					</label>
					<label class="abmm-field">
						<span><?php esc_html_e( 'Panel title alignment', 'ashbi-mega-menu' ); ?></span>
						<select id="abmm-panel-title-align">
							<option value="left" <?php selected( $abmm_settings['panel_title_align'], 'left' ); ?>><?php esc_html_e( 'Left', 'ashbi-mega-menu' ); ?></option>
							<option value="center" <?php selected( $abmm_settings['panel_title_align'], 'center' ); ?>><?php esc_html_e( 'Center', 'ashbi-mega-menu' ); ?></option>
						</select>
					</label>
					<label class="abmm-field abmm-field--toggle">
						<span><?php esc_html_e( 'Uppercase category titles', 'ashbi-mega-menu' ); ?></span>
						<input type="checkbox" id="abmm-uppercase-cats" <?php checked( ! empty( $abmm_settings['uppercase_cats'] ) ); ?> />
					</label>
					<label class="abmm-field abmm-field--toggle">
						<span><?php esc_html_e( 'Show category descriptions', 'ashbi-mega-menu' ); ?></span>
						<input type="checkbox" id="abmm-show-cat-desc" <?php checked( ! empty( $abmm_settings['show_cat_desc'] ) ); ?> />
					</label>
				</details>
			</section>
			</div>

			<div class="abmm-builder-section-group" id="abmm-section-panel-help" data-abmm-builder-section-panel="help" role="tabpanel" aria-labelledby="abmm-section-tab-help" hidden>
			<section class="abmm-builder-panel abmm-builder-panel--hint abmm-placement-panel" <?php echo ! empty( $abmm_menu_readiness['ready'] ) ? '' : 'hidden'; ?>>
				<h2><?php esc_html_e( 'Ready to place', 'ashbi-mega-menu' ); ?></h2>
				<code class="abmm-shortcode-block">[ashbi_mega_menu id="<?php echo esc_attr( $edit_id ); ?>"]</code>
				<p><?php esc_html_e( 'Use this shortcode, the Ashbi Mega Menu block, or the widget.', 'ashbi-mega-menu' ); ?></p>
			</section>

			<section class="abmm-builder-panel abmm-builder-help" aria-labelledby="abmm-builder-help-title">
				<h2 id="abmm-builder-help-title"><?php esc_html_e( 'Help & Recovery', 'ashbi-mega-menu' ); ?></h2>
				<p>
					<?php
					printf(
						/* translators: %s is the installed plugin version. */
						esc_html__( 'Guidance for Ashbi Mega Menu %s.', 'ashbi-mega-menu' ),
						esc_html( ABMM_VERSION )
					);
					?>
				</p>
				<details>
					<summary><?php esc_html_e( 'Place this menu', 'ashbi-mega-menu' ); ?></summary>
					<p><?php esc_html_e( 'Use the shortcode above, the Ashbi Mega Menu block, the widget, or the PHP helper documented on the All Menus screen.', 'ashbi-mega-menu' ); ?></p>
				</details>
				<details>
					<summary><?php esc_html_e( 'Recover from a load or save problem', 'ashbi-mega-menu' ); ?></summary>
					<ol>
						<li><?php esc_html_e( 'Keep this browser tab open and use Retry if the save status reports a failure.', 'ashbi-mega-menu' ); ?></li>
						<li><?php esc_html_e( 'Export the current menu before making additional changes.', 'ashbi-mega-menu' ); ?></li>
						<li><?php esc_html_e( 'Return to All Menus to import a validated backup or recovery snapshot.', 'ashbi-mega-menu' ); ?></li>
					</ol>
				</details>
				<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=ashbi-mega-menu' ) ); ?>"><?php esc_html_e( 'Manage menus and backups', 'ashbi-mega-menu' ); ?></a></p>
				<p><a href="https://github.com/camster91/ashbi-mega-menu/issues/new" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Report a problem on GitHub (opens in a new tab)', 'ashbi-mega-menu' ); ?></a></p>
			</section>
			</div>
		</aside>

		<main class="abmm-builder__main">
			<div class="abmm-editor-chrome">
				<p class="abmm-editor-chrome__label"><?php esc_html_e( 'Live preview — choose a mega menu, then edit its content', 'ashbi-mega-menu' ); ?></p>
				<button type="button" class="button abmm-builder-panel-toggle" aria-controls="abmm-builder-sidebar" aria-expanded="false">
					<?php esc_html_e( 'Edit menu settings', 'ashbi-mega-menu' ); ?>
				</button>
				<button type="button" class="button abmm-open-selected-mega" hidden></button>
				<div class="abmm-preview-viewports" role="group" aria-label="<?php esc_attr_e( 'Preview viewport', 'ashbi-mega-menu' ); ?>">
					<button type="button" class="button is-active" data-abmm-preview-viewport="desktop" aria-pressed="true"><?php esc_html_e( 'Desktop', 'ashbi-mega-menu' ); ?></button>
					<button type="button" class="button" data-abmm-preview-viewport="tablet" aria-pressed="false"><?php esc_html_e( 'Tablet', 'ashbi-mega-menu' ); ?></button>
					<button type="button" class="button" data-abmm-preview-viewport="mobile" aria-pressed="false"><?php esc_html_e( 'Mobile', 'ashbi-mega-menu' ); ?></button>
				</div>
			</div>

			<!-- Preview + inline editor -->
			<div class="abmm-preview-shell" data-abmm-preview-viewport="desktop">
				<div class="abmm-preview" id="abmm-preview" data-abmm-preview-state="desktop-root"></div>
			</div>

			<!-- Detail editor for selected mega item -->
			<div class="abmm-mega-editor" id="abmm-mega-editor" hidden>
				<div class="abmm-mega-editor__header">
					<h2 id="abmm-mega-editor-title"><?php esc_html_e( 'Edit Mega Menu', 'ashbi-mega-menu' ); ?></h2>
					<button type="button" class="button abmm-close-mega-editor"><?php esc_html_e( 'Done', 'ashbi-mega-menu' ); ?></button>
				</div>

				<div class="abmm-mega-style-picker" id="abmm-mega-style-picker">
					<p class="abmm-mega-style-picker__label"><?php esc_html_e( 'Choose layout for this mega menu', 'ashbi-mega-menu' ); ?></p>
					<div class="abmm-mega-style-cards">
						<button type="button" class="abmm-mega-style-card" data-style="platforms">
							<span class="abmm-mega-style-card__preview abmm-mega-style-card__preview--platforms" aria-hidden="true">
								<span></span><span></span>
							</span>
							<strong><?php esc_html_e( 'With category bar', 'ashbi-mega-menu' ); ?></strong>
							<small><?php esc_html_e( 'Left/right sidebar categories (SPEND, SIGN…) + links grid', 'ashbi-mega-menu' ); ?></small>
						</button>
						<button type="button" class="abmm-mega-style-card" data-style="features">
							<span class="abmm-mega-style-card__preview abmm-mega-style-card__preview--features" aria-hidden="true">
								<span></span><span></span><span></span><span></span>
							</span>
							<strong><?php esc_html_e( 'Simple columns (no category bar)', 'ashbi-mega-menu' ); ?></strong>
							<small><?php esc_html_e( 'Just icon + links in 2, 3, or 4 columns — like a Features mega menu', 'ashbi-mega-menu' ); ?></small>
						</button>
					</div>
					<label class="abmm-field abmm-mega-columns-field">
						<span><?php esc_html_e( 'Columns for this mega menu', 'ashbi-mega-menu' ); ?></span>
						<select id="abmm-mega-item-columns">
							<option value="1">1</option>
							<option value="2">2</option>
							<option value="3">3</option>
							<option value="4">4</option>
						</select>
					</label>
					<div class="abmm-field abmm-category-bar-field" id="abmm-category-bar-field">
						<span><?php esc_html_e( 'Category bar', 'ashbi-mega-menu' ); ?></span>
						<label class="abmm-checkbox-label">
							<input type="checkbox" id="abmm-hide-category-bar" value="1" />
							<?php esc_html_e( 'Hide the category bar and show labeled columns edge to edge', 'ashbi-mega-menu' ); ?>
						</label>
						<small class="description"><?php esc_html_e( 'Best for a single section such as Resources / Learn / Compare. The option is disabled when this item has more than one category.', 'ashbi-mega-menu' ); ?></small>
					</div>
				</div>

				<!-- Platforms layout editor -->
				<div class="abmm-mega-editor__layout" id="abmm-editor-platforms">
					<div class="abmm-mega-editor__cats">
						<h3><?php esc_html_e( 'Left Sidebar Categories', 'ashbi-mega-menu' ); ?></h3>
						<p class="description"><?php esc_html_e( 'Like SPEND, SIGN, HR — icon + title + description.', 'ashbi-mega-menu' ); ?></p>
						<ul class="abmm-sortable abmm-cat-list" id="abmm-cat-list"></ul>
						<button type="button" class="button abmm-add-category">+ <?php esc_html_e( 'Add Category', 'ashbi-mega-menu' ); ?></button>
					</div>

					<div class="abmm-mega-editor__cat-detail" id="abmm-cat-detail">
						<p class="abmm-placeholder"><?php esc_html_e( 'Select a category on the left to edit its columns and links.', 'ashbi-mega-menu' ); ?></p>
					</div>
				</div>

				<!-- Features grid layout editor -->
				<div class="abmm-mega-editor__features" id="abmm-editor-features" hidden>
					<h3><?php esc_html_e( 'Feature Links', 'ashbi-mega-menu' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Add icon + label links. They appear in a simple column grid like a Features mega menu.', 'ashbi-mega-menu' ); ?></p>
					<ul class="abmm-sortable abmm-link-list" id="abmm-features-link-list"></ul>
					<button type="button" class="button abmm-add-feature-link">+ <?php esc_html_e( 'Add Link', 'ashbi-mega-menu' ); ?></button>
				</div>
			</div>
		</main>
		<div class="abmm-builder-panel-backdrop" id="abmm-builder-panel-backdrop" hidden aria-hidden="true"></div>
	</div>
</div>

<div class="abmm-exit-dialog" id="abmm-exit-dialog" hidden>
	<div class="abmm-exit-dialog__backdrop"></div>
	<div class="abmm-exit-dialog__panel" role="dialog" aria-modal="true" aria-labelledby="abmm-exit-dialog-title" aria-describedby="abmm-exit-dialog-description" tabindex="-1">
		<h2 id="abmm-exit-dialog-title"><?php esc_html_e( 'Leave with unsaved changes?', 'ashbi-mega-menu' ); ?></h2>
		<p id="abmm-exit-dialog-description"><?php esc_html_e( 'A local recovery draft has been saved in this browser. You can stay and save, or leave and recover it later.', 'ashbi-mega-menu' ); ?></p>
		<button type="button" class="button button-primary abmm-stay-builder"><?php esc_html_e( 'Stay and save', 'ashbi-mega-menu' ); ?></button>
		<button type="button" class="button abmm-leave-builder"><?php esc_html_e( 'Leave with recovery draft', 'ashbi-mega-menu' ); ?></button>
	</div>
</div>

<div id="abmm-icon-modal" class="abmm-modal" hidden>
	<div class="abmm-modal__backdrop"></div>
	<div class="abmm-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="abmm-icon-modal-title" aria-describedby="abmm-icon-modal-description" tabindex="-1">
		<div class="abmm-modal__header">
			<h2 id="abmm-icon-modal-title"><?php esc_html_e( 'Choose an icon', 'ashbi-mega-menu' ); ?></h2>
			<button type="button" class="abmm-modal__close" aria-label="<?php esc_attr_e( 'Close', 'ashbi-mega-menu' ); ?>">&times;</button>
		</div>
		<div class="abmm-modal__body">
		<p id="abmm-icon-modal-description" class="screen-reader-text"><?php esc_html_e( 'Search, choose, upload, or clear the icon for the selected menu item.', 'ashbi-mega-menu' ); ?></p>
			<label class="screen-reader-text" for="abmm-icon-search"><?php esc_html_e( 'Search icons', 'ashbi-mega-menu' ); ?></label>
			<input type="search" id="abmm-icon-search" class="abmm-icon-search" placeholder="<?php esc_attr_e( 'Search icons…', 'ashbi-mega-menu' ); ?>" autocomplete="off" />
			<div class="abmm-icon-grid" id="abmm-icon-grid"></div>
			<div class="abmm-icon-upload">
				<button type="button" class="button abmm-upload-icon"><?php esc_html_e( 'Upload custom icon', 'ashbi-mega-menu' ); ?></button>
				<button type="button" class="button-link abmm-clear-icon"><?php esc_html_e( 'Clear icon', 'ashbi-mega-menu' ); ?></button>
			</div>
		</div>
	</div>
</div>
