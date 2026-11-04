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
$abmm_is_placed      = ! empty( $placements[ $edit_id ]['known'] );
?>
<div class="wrap abmm-wrap abmm-builder-wrap">
	<div class="abmm-page-header abmm-builder-header abmm-builder-topbar">
		<img class="abmm-builder-header__logo" src="<?php echo esc_url( ABMM_PLUGIN_URL . 'assets/brand/icon.svg' ); ?>" width="40" height="40" alt="" />
		<div class="abmm-builder-header__titles">
			<a class="abmm-back abmm-guarded-exit" href="<?php echo esc_url( admin_url( 'admin.php?page=ashbi-mega-menu' ) ); ?>">
				<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span><?php esc_html_e( 'All Menus', 'ashbi-mega-menu' ); ?>
			</a>
			<h1 class="abmm-builder-header__title">
				<label class="screen-reader-text" for="abmm-menu-title"><?php esc_html_e( 'Menu name', 'ashbi-mega-menu' ); ?></label>
				<input type="text" id="abmm-menu-title" class="abmm-menu-title-input" value="<?php echo esc_attr( $menu['title'] ); ?>" placeholder="<?php esc_attr_e( 'Menu name', 'ashbi-mega-menu' ); ?>" title="<?php esc_attr_e( 'Rename this menu', 'ashbi-mega-menu' ); ?>" />
			</h1>
			<p class="abmm-builder-header__subtitle"><?php esc_html_e( 'Saved changes go live wherever this menu is placed.', 'ashbi-mega-menu' ); ?></p>
			</div>
			<nav class="abmm-setup-flow abmm-workflow-nav" aria-label="<?php esc_attr_e( 'Menu setup steps', 'ashbi-mega-menu' ); ?>">
			<button type="button" data-abmm-workflow="content"><span aria-hidden="true">1</span><?php esc_html_e( 'Content', 'ashbi-mega-menu' ); ?></button>
			<button type="button" data-abmm-workflow="design" data-abmm-builder-section="design"><span aria-hidden="true">2</span><?php esc_html_e( 'Design', 'ashbi-mega-menu' ); ?></button>
			<button type="button" data-abmm-workflow="help"><span aria-hidden="true">3</span><?php esc_html_e( 'Place', 'ashbi-mega-menu' ); ?></button>
			<button type="button" data-abmm-workflow="verify"><span aria-hidden="true">4</span><?php esc_html_e( 'Check', 'ashbi-mega-menu' ); ?></button>
		</nav>
		<div class="abmm-builder-topbar__actions">
			<span class="abmm-save-status is-visible" role="status" aria-live="polite"></span>
			<button type="button" class="abmm-icon-button abmm-undo" disabled title="<?php esc_attr_e( 'Undo (Ctrl+Z)', 'ashbi-mega-menu' ); ?>">
				<span class="dashicons dashicons-undo" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Undo', 'ashbi-mega-menu' ); ?></span>
			</button>
			<button type="button" class="abmm-icon-button abmm-redo" disabled title="<?php esc_attr_e( 'Redo (Ctrl+Shift+Z)', 'ashbi-mega-menu' ); ?>">
				<span class="dashicons dashicons-redo" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Redo', 'ashbi-mega-menu' ); ?></span>
			</button>
			<div class="abmm-more-menu">
				<button type="button" class="abmm-icon-button abmm-more-menu__toggle" aria-haspopup="true" aria-expanded="false" aria-controls="abmm-more-menu-list" title="<?php esc_attr_e( 'More actions', 'ashbi-mega-menu' ); ?>">
					<span class="dashicons dashicons-ellipsis" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'More actions', 'ashbi-mega-menu' ); ?></span>
				</button>
				<div class="abmm-more-menu__list" id="abmm-more-menu-list" role="menu" aria-label="<?php esc_attr_e( 'More actions', 'ashbi-mega-menu' ); ?>" hidden>
					<a role="menuitem" class="abmm-more-menu__item abmm-open-history" href="<?php echo esc_url( add_query_arg( 'abmm_history', rawurlencode( $edit_id ), admin_url( 'admin.php?page=ashbi-mega-menu' ) ) ); ?>">
						<span class="dashicons dashicons-backup" aria-hidden="true"></span><?php esc_html_e( 'History', 'ashbi-mega-menu' ); ?>
					</a>
					<button type="button" role="menuitem" class="abmm-more-menu__item abmm-export-current-menu" data-menu-id="<?php echo esc_attr( $edit_id ); ?>">
						<span class="dashicons dashicons-download" aria-hidden="true"></span><?php esc_html_e( 'Export as JSON', 'ashbi-mega-menu' ); ?>
					</button>
					<a role="menuitem" class="abmm-more-menu__item" href="https://github.com/camster91/ashbi-mega-menu/blob/main/docs/getting-started.md" target="_blank" rel="noopener noreferrer">
						<span class="dashicons dashicons-editor-help" aria-hidden="true"></span><?php esc_html_e( 'Getting started guide', 'ashbi-mega-menu' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'ashbi-mega-menu' ); ?></span>
					</a>
				</div>
			</div>
			<button type="button" class="button button-primary abmm-save-menu">
				<span class="dashicons dashicons-yes" aria-hidden="true" hidden></span><span class="abmm-save-menu__label"><?php esc_html_e( 'Save changes', 'ashbi-mega-menu' ); ?></span>
			</button>
		</div>
	</div>
	<hr class="wp-header-end" />

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
		<p class="abmm-draft-recovery__age"></p>
		<p class="abmm-draft-recovery__message"></p>
		<button type="button" class="button button-primary abmm-restore-draft"><?php esc_html_e( 'Restore local draft', 'ashbi-mega-menu' ); ?></button>
		<button type="button" class="button abmm-download-draft"><?php esc_html_e( 'Download a copy', 'ashbi-mega-menu' ); ?></button>
		<button type="button" class="button abmm-discard-draft"><?php esc_html_e( 'Discard local draft', 'ashbi-mega-menu' ); ?></button>
	</div>

	<script type="application/json" id="abmm-menu-data"><?php echo wp_json_encode( $menu, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
	<div class="abmm-builder" id="abmm-builder"
		data-menu-id="<?php echo esc_attr( $edit_id ); ?>"
		data-menu-revision="<?php echo esc_attr( $abmm_menu_revision ); ?>"
		data-menu-placed="<?php echo $abmm_is_placed ? '1' : '0'; ?>">

		<aside class="abmm-builder__sidebar" id="abmm-builder-sidebar" aria-label="<?php esc_attr_e( 'Menu settings', 'ashbi-mega-menu' ); ?>">
			<div class="abmm-builder-sidebar__mobile-head">
				<strong><?php esc_html_e( 'Content', 'ashbi-mega-menu' ); ?></strong>
				<button type="button" class="abmm-icon-button abmm-builder-panel-close" title="<?php esc_attr_e( 'Close', 'ashbi-mega-menu' ); ?>">
					<span class="dashicons dashicons-no-alt" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Close', 'ashbi-mega-menu' ); ?></span>
				</button>
			</div>
			<div class="abmm-builder-section-group" id="abmm-section-panel-content" data-abmm-builder-section-panel="content" role="region" aria-label="<?php esc_attr_e( 'Content', 'ashbi-mega-menu' ); ?>">
			<?php if ( ! empty( $menu['starter_source'] ) ) : ?>
				<section class="abmm-builder-panel abmm-sample-warning">
					<h2><?php esc_html_e( 'This is a sample', 'ashbi-mega-menu' ); ?></h2>
					<p><?php esc_html_e( 'Replace its labels and links with your own before you place it.', 'ashbi-mega-menu' ); ?></p>
				</section>
			<?php endif; ?>
			<section class="abmm-builder-panel abmm-menu-structure-panel">
				<h2><?php esc_html_e( 'Menu items', 'ashbi-mega-menu' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Shown in the menu bar. Drag or use the arrows to reorder.', 'ashbi-mega-menu' ); ?></p>
				<ul class="abmm-sortable abmm-nav-items" id="abmm-nav-items" tabindex="-1"></ul>
				<button type="button" class="button abmm-add-nav-item">
					+ <?php esc_html_e( 'Add item', 'ashbi-mega-menu' ); ?>
				</button>
			</section>

			<section class="abmm-builder-panel abmm-cta-panel">
				<h2><?php esc_html_e( 'Button', 'ashbi-mega-menu' ); ?></h2>
				<label class="abmm-field abmm-field--toggle">
					<span><?php esc_html_e( 'Show button', 'ashbi-mega-menu' ); ?></span>
					<input type="checkbox" id="abmm-cta-show" <?php checked( ! empty( $menu['cta']['show'] ) ); ?> />
				</label>
				<div class="abmm-optional-fields abmm-cta-fields">
				<label class="abmm-field">
					<span><?php esc_html_e( 'Button text', 'ashbi-mega-menu' ); ?></span>
					<input type="text" id="abmm-cta-label" value="<?php echo esc_attr( $menu['cta']['label'] ?? '' ); ?>" />
				</label>
				<?php
				$abmm_cta_has_options = '_blank' === ( $menu['cta']['target'] ?? '' ) || '' !== trim( (string) ( $menu['cta']['rel'] ?? '' ) ) || '' !== trim( (string) ( $menu['cta']['class'] ?? '' ) );
				?>
				<div class="abmm-field">
					<label for="abmm-cta-url"><?php esc_html_e( 'Button URL', 'ashbi-mega-menu' ); ?></label>
					<div class="abmm-wp-link has-link-options">
						<input type="text" id="abmm-cta-url" class="abmm-url-field" value="<?php echo esc_attr( $menu['cta']['url'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Search or type URL', 'ashbi-mega-menu' ); ?>" />
						<button type="button" class="button abmm-pick-url" title="<?php esc_attr_e( 'Select / edit link', 'ashbi-mega-menu' ); ?>" aria-label="<?php esc_attr_e( 'Select / edit link', 'ashbi-mega-menu' ); ?>" data-abmm-url="#abmm-cta-url" data-abmm-text="#abmm-cta-label">
							<span class="dashicons dashicons-admin-links" aria-hidden="true"></span>
						</button>
						<div class="abmm-link-options abmm-popover<?php echo $abmm_cta_has_options ? ' has-options' : ''; ?>">
							<button type="button" class="abmm-link-options__toggle abmm-popover__toggle" aria-expanded="false" aria-controls="abmm-cta-link-options" title="<?php esc_attr_e( 'Link options', 'ashbi-mega-menu' ); ?>">
								<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Link options', 'ashbi-mega-menu' ); ?></span>
							</button>
							<div class="abmm-link-options__panel abmm-popover__panel" id="abmm-cta-link-options" role="group" aria-label="<?php esc_attr_e( 'Link options', 'ashbi-mega-menu' ); ?>" hidden>
								<p class="abmm-popover__title" aria-hidden="true"><?php esc_html_e( 'Link options', 'ashbi-mega-menu' ); ?></p>
								<label class="abmm-popover__check"><input type="checkbox" id="abmm-cta-target" class="abmm-link-target" <?php checked( '_blank' === ( $menu['cta']['target'] ?? '' ) ); ?> /> <?php esc_html_e( 'Open in new tab', 'ashbi-mega-menu' ); ?></label>
								<label class="abmm-popover__field"><span class="abmm-popover__label"><?php esc_html_e( 'Relationship', 'ashbi-mega-menu' ); ?></span><input type="text" id="abmm-cta-rel" class="abmm-link-rel" value="<?php echo esc_attr( $menu['cta']['rel'] ?? '' ); ?>" placeholder="nofollow sponsored" /></label>
								<label class="abmm-popover__field"><span class="abmm-popover__label"><?php esc_html_e( 'CSS classes', 'ashbi-mega-menu' ); ?></span><input type="text" id="abmm-cta-class" class="abmm-link-class" value="<?php echo esc_attr( $menu['cta']['class'] ?? '' ); ?>" /></label>
							</div>
						</div>
					</div>
				</div>
				</div>
			</section>

			<section class="abmm-builder-panel abmm-brand-panel">
				<h2><?php esc_html_e( 'Logo', 'ashbi-mega-menu' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Show your logo or site name at the start of the menu bar.', 'ashbi-mega-menu' ); ?></p>
				<label class="abmm-field abmm-field--toggle"><span><?php esc_html_e( 'Show logo', 'ashbi-mega-menu' ); ?></span><input type="checkbox" id="abmm-brand-show" <?php checked( ! empty( $menu['brand']['show'] ) ); ?> /></label>
				<div class="abmm-optional-fields abmm-brand-fields">
					<label class="abmm-field"><span><?php esc_html_e( 'Site title', 'ashbi-mega-menu' ); ?></span><input type="text" id="abmm-brand-title" value="<?php echo esc_attr( $menu['brand']['title'] ); ?>" /></label>
					<?php
					$abmm_logo_field = static function ( $abmm_id, $abmm_label, $abmm_value, $abmm_placeholder ) {
						?>
					<div class="abmm-field">
						<label for="<?php echo esc_attr( $abmm_id ); ?>"><?php echo esc_html( $abmm_label ); ?></label>
						<div class="abmm-wp-link">
							<input type="url" id="<?php echo esc_attr( $abmm_id ); ?>" class="abmm-url-field" value="<?php echo esc_attr( $abmm_value ); ?>" placeholder="<?php echo esc_attr( $abmm_placeholder ); ?>" />
							<button type="button" class="button abmm-choose-logo" data-abmm-target="#<?php echo esc_attr( $abmm_id ); ?>" title="<?php esc_attr_e( 'Choose from the Media Library', 'ashbi-mega-menu' ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: field name, e.g. Logo image URL. */ __( 'Choose an image for %s', 'ashbi-mega-menu' ), $abmm_label ) ); ?>">
								<span class="dashicons dashicons-format-image" aria-hidden="true"></span>
							</button>
						</div>
					</div>
						<?php
					};
					$abmm_logo_field( 'abmm-brand-logo-url', __( 'Logo image URL', 'ashbi-mega-menu' ), $menu['brand']['logo_url'], 'https://…' );
					?>
					<label class="abmm-field"><span><?php esc_html_e( 'Logo alternative text', 'ashbi-mega-menu' ); ?></span><input type="text" id="abmm-brand-alt" aria-describedby="abmm-logo-alt-warning" value="<?php echo esc_attr( $menu['brand']['alt'] ); ?>" /></label>
					<p class="description is-warning" id="abmm-logo-alt-warning" hidden><?php esc_html_e( 'Add alternative text or a site title. Without either, screen readers announce the logo link with no name.', 'ashbi-mega-menu' ); ?></p>
					<?php
					// Opens by itself when it holds a value, so nothing active stays out of sight.
					$abmm_brand_more_open = '' !== trim( (string) $menu['brand']['logo_dark_url'] ) || 40 !== (int) $menu['brand']['logo_height'] || untrailingslashit( (string) $menu['brand']['url'] ) !== untrailingslashit( home_url( '/' ) );
					?>
					<details class="abmm-design-more abmm-disclosure abmm-brand-more"<?php echo $abmm_brand_more_open ? ' open' : ''; ?>>
						<summary><?php esc_html_e( 'More logo options', 'ashbi-mega-menu' ); ?></summary>
						<label class="abmm-field"><span><?php esc_html_e( 'Brand URL', 'ashbi-mega-menu' ); ?></span><input type="url" id="abmm-brand-url" value="<?php echo esc_attr( $menu['brand']['url'] ); ?>" /></label>
						<?php $abmm_logo_field( 'abmm-brand-logo-dark-url', __( 'Dark-preset logo URL', 'ashbi-mega-menu' ), $menu['brand']['logo_dark_url'], __( 'Optional alternate logo', 'ashbi-mega-menu' ) ); ?>
						<label class="abmm-field"><span><?php esc_html_e( 'Logo height (px)', 'ashbi-mega-menu' ); ?></span><input type="number" id="abmm-brand-logo-height" min="16" max="80" value="<?php echo esc_attr( $menu['brand']['logo_height'] ); ?>" /></label>
					</details>
				</div>
			</section>
			</div>

			<div class="abmm-builder-section-group" id="abmm-section-panel-design" data-abmm-builder-section-panel="design" role="region" aria-label="<?php esc_attr_e( 'Design', 'ashbi-mega-menu' ); ?>" hidden>
			<section class="abmm-builder-panel abmm-design-panel">
				<h2><?php esc_html_e( 'Design', 'ashbi-mega-menu' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Pick a preset. The preview updates as you go.', 'ashbi-mega-menu' ); ?></p>
			<div class="notice notice-warning inline abmm-contrast-warning" id="abmm-contrast-warning" role="status" hidden>
				<p><?php esc_html_e( 'Some colors may be hard to read:', 'ashbi-mega-menu' ); ?></p>
				<ul class="ul-disc"></ul>
				<p><button type="button" class="button-link" data-abmm-advanced-open="colors"><?php esc_html_e( 'Change the colors', 'ashbi-mega-menu' ); ?></button></p>
			</div>

				<?php
				$abmm_settings = wp_parse_args( $menu['settings'] ?? array(), ABMM_Data::default_settings() );
				$abmm_presets  = ABMM_Data::design_presets();
				$abmm_classic  = ABMM_Classic::is_classic( $menu );
				// Site look stays available for any menu of Classic origin, so switching
				// to Ashbi can be undone. Origin: Classic now, the classic_origin flag a
				// switch stores, or (for menus switched before the flag existed) an
				// Easy Mega Menu menu with the same ID, since imports keep their IDs.
				$abmm_classic_origin = $abmm_classic || ! empty( $abmm_settings['classic_origin'] ) || ( class_exists( 'ABMM_EMM_Migration' ) && ABMM_EMM_Migration::instance()->is_source_menu( $edit_id ) );
				?>

				<?php if ( $abmm_classic_origin ) : ?>
					<label class="abmm-field">
						<span><?php esc_html_e( 'Site look', 'ashbi-mega-menu' ); ?></span>
						<select id="abmm-render-mode" aria-describedby="abmm-render-mode-help">
							<option value="emm-classic" <?php selected( $abmm_classic ); ?>><?php esc_html_e( 'Classic (Easy Mega Menu)', 'ashbi-mega-menu' ); ?></option>
							<option value="" <?php selected( ! $abmm_classic ); ?>><?php esc_html_e( 'Ashbi', 'ashbi-mega-menu' ); ?></option>
						</select>
						<span class="description" id="abmm-render-mode-help"><?php esc_html_e( 'Classic keeps the exact look, markup and site CSS of Easy Mega Menu. Saving a switch changes how the menu looks on your site right away. You can switch back here at any time.', 'ashbi-mega-menu' ); ?></span>
					</label>
					<label class="abmm-field abmm-classic-notice">
						<span><?php esc_html_e( 'Phone menu button label', 'ashbi-mega-menu' ); ?></span>
						<input type="text" id="abmm-classic-toggle-label" maxlength="40" aria-describedby="abmm-classic-toggle-label-help" value="<?php echo esc_attr( $abmm_settings['classic_toggle_label'] ?? '' ); ?>" />
						<span class="description" id="abmm-classic-toggle-label-help"><?php esc_html_e( 'Optional. Tells two phone menus on one page apart, for example "Products". Empty keeps the icon on its own.', 'ashbi-mega-menu' ); ?></span>
					</label>
					<div class="notice notice-info inline abmm-classic-notice">
						<p><?php esc_html_e( 'Color and size changes below still apply to the Classic look.', 'ashbi-mega-menu' ); ?></p>
					</div>
				<?php endif; ?>

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
						<?php $abmm_is_custom = 'custom' === $abmm_settings['preset']; ?>
						<button type="button" class="abmm-preset-card abmm-preset-card--custom<?php echo $abmm_is_custom ? ' is-active' : ' is-empty'; ?>" data-preset="custom" title="<?php esc_attr_e( 'Your own colors. Available after you edit preset colors.', 'ashbi-mega-menu' ); ?>"<?php disabled( ! $abmm_is_custom ); ?>>
							<span class="abmm-preset-card__swatches abmm-preset-card__swatches--custom" aria-hidden="true">
								<?php foreach ( array( 'header_bg', 'sidebar_bg', 'accent' ) as $abmm_swatch_key ) : ?>
									<span<?php echo $abmm_is_custom ? ' style="background:' . esc_attr( $abmm_settings[ $abmm_swatch_key ] ) . '"' : ''; ?>></span>
								<?php endforeach; ?>
								<span class="abmm-preset-card__empty dashicons dashicons-admin-customizer"></span>
							</span>
							<span class="abmm-preset-card__label"><?php esc_html_e( 'Custom', 'ashbi-mega-menu' ); ?></span>
						</button>
					</div>
					<input type="hidden" id="abmm-preset" value="<?php echo esc_attr( $abmm_settings['preset'] ); ?>" />
				</div>

				<label class="abmm-field">
					<span><?php esc_html_e( 'Nav link alignment', 'ashbi-mega-menu' ); ?></span>
					<select id="abmm-nav-align">
						<option value="left" <?php selected( $abmm_settings['nav_align'], 'left' ); ?>><?php esc_html_e( 'Left', 'ashbi-mega-menu' ); ?></option>
						<option value="center" <?php selected( $abmm_settings['nav_align'], 'center' ); ?>><?php esc_html_e( 'Center', 'ashbi-mega-menu' ); ?></option>
						<option value="right" <?php selected( $abmm_settings['nav_align'], 'right' ); ?>><?php esc_html_e( 'Right', 'ashbi-mega-menu' ); ?></option>
					</select>
				</label>

				<div class="abmm-advanced-summary" id="abmm-advanced-summary">
					<p class="abmm-advanced-summary__count" role="status"></p>
					<ul class="abmm-advanced-summary__list" hidden></ul>
				</div>

				<details class="abmm-design-more abmm-advanced" id="abmm-advanced-settings" data-abmm-baselines="<?php echo esc_attr( wp_json_encode( array( 'native' => ABMM_Data::default_settings(), 'classic' => ABMM_Classic::default_settings() ) ) ); ?>">
				<summary><?php esc_html_e( 'Advanced settings', 'ashbi-mega-menu' ); ?></summary>
				<p class="description"><?php esc_html_e( 'Optional. Most menus do not need these.', 'ashbi-mega-menu' ); ?></p>

				<details class="abmm-design-more" data-abmm-advanced-group="top-colors">
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

				<details class="abmm-design-more" data-abmm-advanced-group="icons">
					<summary><?php esc_html_e( 'Icon styling', 'ashbi-mega-menu' ); ?></summary>
					<label class="abmm-field abmm-field--toggle">
						<span><?php esc_html_e( 'Inherit menu text color', 'ashbi-mega-menu' ); ?></span>
						<input type="checkbox" id="abmm-icon-inherit-text" <?php checked( ! empty( $abmm_settings['icon_inherit_text'] ) ); ?> />
					</label>
					<label class="abmm-field" id="abmm-icon-color-field">
						<span><?php esc_html_e( 'Default icon color', 'ashbi-mega-menu' ); ?></span>
						<input type="text" class="abmm-color" id="abmm-icon-color" value="<?php echo esc_attr( $abmm_settings['icon_color'] ); ?>" />
					</label>
					<label class="abmm-field">
						<span><?php esc_html_e( 'Hover / focus color', 'ashbi-mega-menu' ); ?></span>
						<input type="text" class="abmm-color" id="abmm-icon-hover-color" value="<?php echo esc_attr( $abmm_settings['icon_hover_color'] ); ?>" />
					</label>
					<label class="abmm-field">
						<span><?php esc_html_e( 'Current / active color', 'ashbi-mega-menu' ); ?></span>
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
						<span><?php esc_html_e( 'Icon border color', 'ashbi-mega-menu' ); ?></span>
						<input type="text" class="abmm-color" id="abmm-icon-border-color" value="<?php echo esc_attr( $abmm_settings['icon_border_color'] ); ?>" />
					</label>
					<label class="abmm-field">
						<span><?php esc_html_e( 'Icon corner radius (px)', 'ashbi-mega-menu' ); ?></span>
						<input type="number" id="abmm-icon-radius" min="0" max="24" step="1" value="<?php echo esc_attr( $abmm_settings['icon_radius'] ); ?>" />
					</label>
					<p class="description"><?php esc_html_e( 'SVG icons use these colors. Uploaded PNG, JPG and WebP artwork keeps its original pixels while using the shared size, background, border and radius.', 'ashbi-mega-menu' ); ?></p>
				</details>

				<details class="abmm-design-more" data-abmm-advanced-group="colors">
					<summary><?php esc_html_e( 'Panel & brand colors', 'ashbi-mega-menu' ); ?></summary>
					<label class="abmm-field">
						<span><?php esc_html_e( 'Header background', 'ashbi-mega-menu' ); ?></span>
						<input type="text" class="abmm-color" id="abmm-header-bg" value="<?php echo esc_attr( $abmm_settings['header_bg'] ); ?>" />
					</label>
					<label class="abmm-field">
						<span><?php esc_html_e( 'Category bar background', 'ashbi-mega-menu' ); ?></span>
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

				<details class="abmm-design-more" data-abmm-advanced-group="layout">
					<summary><?php esc_html_e( 'Layout & style', 'ashbi-mega-menu' ); ?></summary>
					<label class="abmm-field">
						<span><?php esc_html_e( 'Category bar position', 'ashbi-mega-menu' ); ?></span>
						<select id="abmm-layout">
							<option value="sidebar-left" <?php selected( $abmm_settings['layout'], 'sidebar-left' ); ?>><?php esc_html_e( 'Category bar on the left', 'ashbi-mega-menu' ); ?></option>
							<option value="sidebar-right" <?php selected( $abmm_settings['layout'], 'sidebar-right' ); ?>><?php esc_html_e( 'Right', 'ashbi-mega-menu' ); ?></option>
							<option value="stacked" <?php selected( $abmm_settings['layout'], 'stacked' ); ?>><?php esc_html_e( 'Top (horizontal tabs)', 'ashbi-mega-menu' ); ?></option>
						</select>
						<span class="description"><?php esc_html_e( 'Only applies to mega menus that use categories.', 'ashbi-mega-menu' ); ?></span>
					</label>
					<label class="abmm-field">
						<span><?php esc_html_e( 'Default grid columns', 'ashbi-mega-menu' ); ?></span>
						<select id="abmm-grid-cols">
							<?php for ( $abmm_i = 1; $abmm_i <= 4; $abmm_i++ ) : ?>
								<option value="<?php echo (int) $abmm_i; ?>" <?php selected( (int) $abmm_settings['grid_columns'], $abmm_i ); ?>><?php echo (int) $abmm_i; ?></option>
							<?php endfor; ?>
						</select>
						<span class="description"><?php esc_html_e( 'Each mega menu can change this in its own layout settings.', 'ashbi-mega-menu' ); ?></span>
					</label>
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
						<span><?php esc_html_e( 'Category bar width (px)', 'ashbi-mega-menu' ); ?></span>
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
						<span><?php esc_html_e( 'Button shape', 'ashbi-mega-menu' ); ?></span>
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

				<?php
				$abmm_style  = is_array( $abmm_settings['style'] ?? null ) ? $abmm_settings['style'] : array();
				$abmm_weight = function ( $key, $label ) use ( $abmm_style ) {
					$options = array( '' => __( 'Default', 'ashbi-mega-menu' ) );
					foreach ( ABMM_Style::WEIGHTS as $abmm_w ) {
						$options[ $abmm_w ] = $abmm_w;
					}
					echo '<label class="abmm-field"><span>' . esc_html( $label ) . '</span><select data-abmm-style-key="' . esc_attr( $key ) . '">';
					foreach ( $options as $abmm_value => $abmm_text ) {
						echo '<option value="' . esc_attr( $abmm_value ) . '"' . selected( (string) ( $abmm_style[ $key ] ?? '' ), (string) $abmm_value, false ) . '>' . esc_html( $abmm_text ) . '</option>';
					}
					echo '</select></label>';
				};
				$abmm_size = function ( $key, $label ) use ( $abmm_style ) {
					echo '<label class="abmm-field"><span>' . esc_html( $label ) . '</span><input type="number" min="10" max="40" step="1" placeholder="' . esc_attr__( 'Default', 'ashbi-mega-menu' ) . '" data-abmm-style-key="' . esc_attr( $key ) . '" value="' . esc_attr( (string) ( $abmm_style[ $key ] ?? '' ) ) . '" /></label>';
				};
				?>
				<details class="abmm-design-more" data-abmm-style-group="fonts" data-abmm-advanced-group="fonts">
					<summary><?php esc_html_e( 'Fonts', 'ashbi-mega-menu' ); ?></summary>
					<label class="abmm-field">
						<span><?php esc_html_e( 'Font', 'ashbi-mega-menu' ); ?></span>
						<select data-abmm-style-key="font_family_mode" aria-describedby="abmm-font-help">
							<option value="" <?php selected( $abmm_style['font_family_mode'] ?? '', '' ); ?>><?php esc_html_e( 'Menu default', 'ashbi-mega-menu' ); ?></option>
							<option value="inherit" <?php selected( $abmm_style['font_family_mode'] ?? '', 'inherit' ); ?>><?php esc_html_e( 'Same as my site (theme or Elementor font)', 'ashbi-mega-menu' ); ?></option>
							<option value="custom" <?php selected( $abmm_style['font_family_mode'] ?? '', 'custom' ); ?>><?php esc_html_e( 'Custom font', 'ashbi-mega-menu' ); ?></option>
						</select>
						<span class="description" id="abmm-font-help"><?php esc_html_e( 'Choose "Same as my site" to use the font your theme or Elementor already loads on every page.', 'ashbi-mega-menu' ); ?></span>
					</label>
					<label class="abmm-field" data-abmm-style-when="font_family_mode=custom">
						<span><?php esc_html_e( 'Custom font names', 'ashbi-mega-menu' ); ?></span>
						<input type="text" data-abmm-style-key="font_family" value="<?php echo esc_attr( $abmm_style['font_family'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Supreme, Arial, sans-serif', 'ashbi-mega-menu' ); ?>" />
						<span class="description"><?php esc_html_e( 'The font must already be loaded on your site, for example by your theme or Elementor.', 'ashbi-mega-menu' ); ?></span>
					</label>
					<p class="description"><?php esc_html_e( 'Sizes and weights apply to the desktop menu. Leave a field empty to keep the menu default.', 'ashbi-mega-menu' ); ?></p>
					<?php
					$abmm_size( 'top_font_size', __( 'Menu bar size (px)', 'ashbi-mega-menu' ) );
					$abmm_weight( 'top_font_weight', __( 'Menu bar weight', 'ashbi-mega-menu' ) );
					?>
					<label class="abmm-field">
						<span><?php esc_html_e( 'Menu bar letter spacing (px)', 'ashbi-mega-menu' ); ?></span>
						<input type="number" min="-2" max="10" step="0.1" placeholder="<?php esc_attr_e( 'Default', 'ashbi-mega-menu' ); ?>" data-abmm-style-key="top_letter_spacing" value="<?php echo esc_attr( $abmm_style['top_letter_spacing'] ?? '' ); ?>" />
					</label>
					<label class="abmm-field">
						<span><?php esc_html_e( 'Menu bar letter case', 'ashbi-mega-menu' ); ?></span>
						<select data-abmm-style-key="top_text_transform">
							<option value=""><?php esc_html_e( 'Default', 'ashbi-mega-menu' ); ?></option>
							<option value="none" <?php selected( $abmm_style['top_text_transform'] ?? '', 'none' ); ?>><?php esc_html_e( 'As typed', 'ashbi-mega-menu' ); ?></option>
							<option value="uppercase" <?php selected( $abmm_style['top_text_transform'] ?? '', 'uppercase' ); ?>><?php esc_html_e( 'UPPERCASE', 'ashbi-mega-menu' ); ?></option>
							<option value="capitalize" <?php selected( $abmm_style['top_text_transform'] ?? '', 'capitalize' ); ?>><?php esc_html_e( 'Capitalize Each Word', 'ashbi-mega-menu' ); ?></option>
							<option value="lowercase" <?php selected( $abmm_style['top_text_transform'] ?? '', 'lowercase' ); ?>><?php esc_html_e( 'lowercase', 'ashbi-mega-menu' ); ?></option>
						</select>
					</label>
					<?php
					$abmm_size( 'cat_font_size', __( 'Category title size (px)', 'ashbi-mega-menu' ) );
					$abmm_weight( 'cat_font_weight', __( 'Category title weight', 'ashbi-mega-menu' ) );
					$abmm_size( 'link_font_size', __( 'Link size (px)', 'ashbi-mega-menu' ) );
					$abmm_weight( 'link_font_weight', __( 'Link weight', 'ashbi-mega-menu' ) );
					$abmm_size( 'desc_font_size', __( 'Description size (px)', 'ashbi-mega-menu' ) );
					$abmm_weight( 'desc_font_weight', __( 'Description weight', 'ashbi-mega-menu' ) );
					?>
					<button type="button" class="button-link abmm-style-reset" data-abmm-style-reset="fonts"><?php esc_html_e( 'Reset fonts to default', 'ashbi-mega-menu' ); ?></button>
				</details>

				<details class="abmm-design-more" data-abmm-style-group="height" data-abmm-advanced-group="height">
					<summary><?php esc_html_e( 'Dropdown height', 'ashbi-mega-menu' ); ?></summary>
					<label class="abmm-field">
						<span><?php esc_html_e( 'Height of the open dropdown (desktop)', 'ashbi-mega-menu' ); ?></span>
						<select data-abmm-style-key="panel_height_mode" aria-describedby="abmm-panel-height-help">
							<option value="" <?php selected( $abmm_style['panel_height_mode'] ?? '', '' ); ?>><?php esc_html_e( 'Default (scrolls when taller than the screen allows)', 'ashbi-mega-menu' ); ?></option>
							<option value="auto" <?php selected( $abmm_style['panel_height_mode'] ?? '', 'auto' ); ?>><?php esc_html_e( 'Grow to fit all links', 'ashbi-mega-menu' ); ?></option>
							<option value="max" <?php selected( $abmm_style['panel_height_mode'] ?? '', 'max' ); ?>><?php esc_html_e( 'Up to a height, then scroll', 'ashbi-mega-menu' ); ?></option>
							<option value="fixed" <?php selected( $abmm_style['panel_height_mode'] ?? '', 'fixed' ); ?>><?php esc_html_e( 'Always the same height', 'ashbi-mega-menu' ); ?></option>
						</select>
						<span class="description" id="abmm-panel-height-help"><?php esc_html_e( '"Grow to fit" can run below the bottom of short screens.', 'ashbi-mega-menu' ); ?></span>
					</label>
					<label class="abmm-field" data-abmm-style-when="panel_height_mode=max,fixed">
						<span><?php esc_html_e( 'Height (px)', 'ashbi-mega-menu' ); ?></span>
						<input type="number" min="200" max="1200" step="10" data-abmm-style-key="panel_height" value="<?php echo esc_attr( (string) ( $abmm_style['panel_height'] ?? 520 ) ); ?>" />
					</label>
					<button type="button" class="button-link abmm-style-reset" data-abmm-style-reset="height"><?php esc_html_e( 'Reset height to default', 'ashbi-mega-menu' ); ?></button>
				</details>
				</details>
			</section>

			<section class="abmm-builder-panel">
				<h2><?php esc_html_e( 'Mobile navigation', 'ashbi-mega-menu' ); ?></h2>
				<label class="abmm-field abmm-field--toggle"><span><?php esc_html_e( 'Enhanced mobile menu', 'ashbi-mega-menu' ); ?></span><input type="checkbox" id="abmm-mobile-enhancements" <?php checked( ! empty( $menu['settings']['mobile_enhancements'] ) ); ?> /></label>
				<p class="description"><?php esc_html_e( 'Adds search, full-width rows and a Back label to the phone menu.', 'ashbi-mega-menu' ); ?></p>
			</section>
			<?php if ( abmm_contextual_navigation_enabled() ) : ?><section class="abmm-builder-panel abmm-product-navigation-panel">
				<h2><?php esc_html_e( 'Product Navigation', 'ashbi-mega-menu' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Show extra links on specific sections of your site, such as a product or department.', 'ashbi-mega-menu' ); ?></p>
				<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=ashbi-mega-menu-products' ) ); ?>"><?php esc_html_e( 'Manage product navigation', 'ashbi-mega-menu' ); ?></a></p>
			</section><?php endif; ?>
			</div>

			<div class="abmm-builder-section-group abmm-builder-section-group--place" id="abmm-section-panel-help" data-abmm-builder-section-panel="help" role="region" aria-labelledby="abmm-placement-title" hidden>
			<?php
			$abmm_place_method = $placement_recommendation['method'] ?? 'shortcode';
			$abmm_place_others = array(
				'block'     => array( __( 'Block theme', 'ashbi-mega-menu' ), __( 'In Appearance → Editor, add the Ashbi Mega Menu block to your Header and pick this menu.', 'ashbi-mega-menu' ) ),
				'widget'    => array( __( 'Classic theme', 'ashbi-mega-menu' ), ABMM_Admin::uses_block_widgets() ? __( 'In Appearance → Widgets, add the Ashbi Mega Menu block to a header area, or paste the shortcode.', 'ashbi-mega-menu' ) : __( 'Add the "Ashbi Mega Menu (classic widget)" widget to a header area, or paste the shortcode.', 'ashbi-mega-menu' ) ),
				'shortcode' => array( __( 'Page builder', 'ashbi-mega-menu' ), __( 'Paste the shortcode into a shortcode element in a full-width section.', 'ashbi-mega-menu' ) ),
			);
			unset( $abmm_place_others[ $abmm_place_method ] );
			$abmm_shortcode_field = static function () use ( $edit_id ) {
				?>
				<code class="abmm-shortcode-block">[ashbi_mega_menu id="<?php echo esc_attr( $edit_id ); ?>"]</code>
				<p><button type="button" class="button abmm-copy-placement" data-shortcode='[ashbi_mega_menu id="<?php echo esc_attr( $edit_id ); ?>"]'><?php esc_html_e( 'Copy shortcode', 'ashbi-mega-menu' ); ?></button></p>
				<?php
			};
			?>
			<section class="abmm-builder-panel abmm-placement-panel" id="placement">
				<h2 id="abmm-placement-title"><?php esc_html_e( 'Place this menu', 'ashbi-mega-menu' ); ?></h2>
				<p class="abmm-placement-save-hint" role="status" hidden></p>
				<div class="abmm-place-recommended">
					<span class="abmm-badge abmm-badge--recommended"><?php esc_html_e( 'Recommended for your theme', 'ashbi-mega-menu' ); ?></span>
					<strong><?php echo esc_html( $placement_recommendation['label'] ?? '' ); ?></strong>
					<p><?php echo esc_html( $placement_recommendation['steps'] ?? '' ); ?></p>
					<?php
					if ( 'shortcode' === $abmm_place_method ) {
						$abmm_shortcode_field();
					}
					?>
				</div>
				<p class="description abmm-place-width"><?php esc_html_e( 'Give the menu the full width of the page. In a narrow column or sidebar it shows the phone menu, even on a wide screen.', 'ashbi-mega-menu' ); ?></p>
				<p class="description abmm-place-rollback"><?php esc_html_e( 'Keep your theme\'s menu while you test. When the new menu works, remove the old one from the header so there is only one. To roll back, remove the Ashbi block, widget or shortcode: this plugin never changes your theme\'s menu.', 'ashbi-mega-menu' ); ?></p>
				<details class="abmm-design-more abmm-disclosure abmm-place-others">
					<summary><?php esc_html_e( 'Other ways to place it', 'ashbi-mega-menu' ); ?></summary>
					<ul class="abmm-place-options">
						<?php foreach ( $abmm_place_others as $abmm_place_other ) : ?>
							<li><strong><?php echo esc_html( $abmm_place_other[0] ); ?></strong> <?php echo esc_html( $abmm_place_other[1] ); ?></li>
						<?php endforeach; ?>
					</ul>
					<?php
					if ( 'shortcode' !== $abmm_place_method ) {
						$abmm_shortcode_field();
					}
					?>
					<details class="abmm-design-more abmm-disclosure"><summary><?php esc_html_e( 'For developers: PHP', 'ashbi-mega-menu' ); ?></summary><p><?php esc_html_e( 'Add this to your child theme header.', 'ashbi-mega-menu' ); ?></p><code class="abmm-shortcode-block">&lt;?php if ( function_exists( 'abmm_render_menu' ) ) { abmm_render_menu( '<?php echo esc_html( $edit_id ); ?>' ); } ?&gt;</code></details>
				</details>
			</section>
			</div>

			<div class="abmm-builder-section-group abmm-builder-section-group--check" id="abmm-section-panel-verify" data-abmm-builder-section-panel="verify" role="region" aria-label="<?php esc_attr_e( 'Check', 'ashbi-mega-menu' ); ?>" hidden>
			<section class="abmm-builder-panel abmm-readiness-panel" id="abmm-readiness-panel" tabindex="-1" aria-labelledby="abmm-readiness-title">
				<h2 id="abmm-readiness-title"><?php esc_html_e( 'Checklist', 'ashbi-mega-menu' ); ?></h2>
				<p class="abmm-readiness-summary" role="status" aria-live="polite"></p>
				<ul class="abmm-readiness-issues"></ul>
			</section>

			<section class="abmm-builder-panel abmm-verification-panel" id="abmm-verification-panel" tabindex="-1" aria-labelledby="abmm-verification-title">
				<h2 id="abmm-verification-title"><?php esc_html_e( 'Check it on your site', 'ashbi-mega-menu' ); ?></h2>
				<p class="description abmm-placement-state"><?php echo esc_html( $abmm_is_placed ? __( 'Found on your site. Every save updates it there right away.', 'ashbi-mega-menu' ) : __( 'Not found in your pages, widgets or page builder content. Placements in theme templates or PHP cannot be detected.', 'ashbi-mega-menu' ) ); ?></p>
				<ul class="abmm-checklist"><li><?php esc_html_e( 'Every link goes to the right page', 'ashbi-mega-menu' ); ?></li><li><?php esc_html_e( 'Dropdowns open with a mouse and the keyboard', 'ashbi-mega-menu' ); ?></li><li><?php esc_html_e( 'The phone menu opens, goes back and closes', 'ashbi-mega-menu' ); ?></li><li><?php esc_html_e( 'Your theme\'s old menu is removed, so there is only one', 'ashbi-mega-menu' ); ?></li></ul>
				<a class="button" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View site', 'ashbi-mega-menu' ); ?></a>
			</section>

			<section class="abmm-builder-panel abmm-builder-help" aria-labelledby="abmm-builder-help-title">
				<h2 id="abmm-builder-help-title"><?php esc_html_e( 'Need help?', 'ashbi-mega-menu' ); ?></h2>
				<ul class="abmm-help-links">
					<li><button type="button" class="button-link" data-abmm-guide-start hidden><?php esc_html_e( 'Show the guide again', 'ashbi-mega-menu' ); ?></button></li>
					<li><a href="https://github.com/camster91/ashbi-mega-menu/blob/main/docs/getting-started.md" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Getting started guide', 'ashbi-mega-menu' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'ashbi-mega-menu' ); ?></span></a></li>
					<li><a href="https://github.com/camster91/ashbi-mega-menu/blob/main/docs/user-guide.md" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Placement and troubleshooting', 'ashbi-mega-menu' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'ashbi-mega-menu' ); ?></span></a></li>
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=ashbi-mega-menu' ) ); ?>"><?php esc_html_e( 'Backups and import', 'ashbi-mega-menu' ); ?></a></li>
					<li><a href="https://github.com/camster91/ashbi-mega-menu/issues/new" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Report a problem', 'ashbi-mega-menu' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'ashbi-mega-menu' ); ?></span></a></li>
				</ul>
				<details class="abmm-design-more abmm-disclosure">
					<summary><?php esc_html_e( 'A save failed?', 'ashbi-mega-menu' ); ?></summary>
					<p><?php esc_html_e( 'Keep this tab open and press Retry. Your changes are also kept in this browser, so you can export them first if you need to.', 'ashbi-mega-menu' ); ?></p>
				</details>
			</section>
			</div>
		</aside>

		<div class="abmm-builder__main">
			<div class="abmm-editor-chrome">
				<button type="button" class="button abmm-builder-panel-toggle" aria-controls="abmm-builder-sidebar" aria-expanded="false">
					<span class="dashicons dashicons-edit" aria-hidden="true"></span><?php esc_html_e( 'Edit menu', 'ashbi-mega-menu' ); ?>
				</button>
				<p class="abmm-editor-chrome__label"><?php esc_html_e( 'Preview', 'ashbi-mega-menu' ); ?></p>
				<p class="abmm-editor-chrome__note abmm-classic-preview-note" hidden><?php esc_html_e( 'Classic look. Your theme\'s styles aren\'t loaded here, so check the result on your site.', 'ashbi-mega-menu' ); ?></p>
				<p class="abmm-editor-chrome__note abmm-classic-preview-fallback" hidden><?php esc_html_e( 'The Classic preview couldn\'t load, so this shows Ashbi\'s style. Reload the page to try again.', 'ashbi-mega-menu' ); ?></p>
				<div class="abmm-preview-viewports" role="group" aria-label="<?php esc_attr_e( 'Preview viewport', 'ashbi-mega-menu' ); ?>">
					<button type="button" class="button is-active" data-abmm-preview-viewport="desktop" aria-pressed="true" title="<?php esc_attr_e( 'Desktop', 'ashbi-mega-menu' ); ?>"><span class="dashicons dashicons-desktop" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Desktop', 'ashbi-mega-menu' ); ?></span></button>
					<button type="button" class="button" data-abmm-preview-viewport="tablet" aria-pressed="false" title="<?php esc_attr_e( 'Tablet', 'ashbi-mega-menu' ); ?>"><span class="dashicons dashicons-tablet" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Tablet', 'ashbi-mega-menu' ); ?></span></button>
					<button type="button" class="button" data-abmm-preview-viewport="mobile" aria-pressed="false" title="<?php esc_attr_e( 'Mobile', 'ashbi-mega-menu' ); ?>"><span class="dashicons dashicons-smartphone" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Mobile', 'ashbi-mega-menu' ); ?></span></button>
				</div>
				<div class="abmm-preview-zoom"><button type="button" class="button" data-abmm-zoom="fit" aria-pressed="true" title="<?php esc_attr_e( 'Zoom to fit (Ctrl/Cmd + 0). Actual size: Ctrl/Cmd + 1', 'ashbi-mega-menu' ); ?>"><?php esc_html_e( 'Fit', 'ashbi-mega-menu' ); ?></button></div>
			</div>

			<!-- Preview + inline editor -->
			<div class="abmm-preview-shell" data-abmm-preview-viewport="desktop">
				<div class="abmm-preview-sizer">
					<div class="abmm-preview-stage">
						<div class="abmm-preview" id="abmm-preview" data-abmm-preview-state="desktop-root"></div>
					</div>
				</div>
				<p class="abmm-canvas-hint abmm-canvas-ui"><?php esc_html_e( 'Drag or scroll to move around. Ctrl/Cmd + scroll or pinch to zoom.', 'ashbi-mega-menu' ); ?></p>
			</div>

			<!-- Detail editor for selected mega item -->
			<div class="abmm-mega-editor" id="abmm-mega-editor" hidden>
				<div class="abmm-mega-editor__header">
					<button type="button" class="abmm-close-mega-editor" aria-describedby="abmm-mega-editor-title">
						<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span><span class="abmm-close-mega-editor__label"><?php esc_html_e( 'Back to menu', 'ashbi-mega-menu' ); ?></span>
					</button>
					<span class="abmm-mega-editor__divider" aria-hidden="true"></span>
					<div class="abmm-mega-editor__heading">
						<p class="abmm-mega-editor__eyebrow" id="abmm-mega-editor-eyebrow"><?php esc_html_e( 'Dropdown content', 'ashbi-mega-menu' ); ?></p>
						<h2 id="abmm-mega-editor-title" tabindex="-1"><?php esc_html_e( 'Edit dropdown', 'ashbi-mega-menu' ); ?></h2>
					</div>
					<p class="abmm-mega-editor__note"><?php esc_html_e( 'Changes show in the preview right away. Save from the top bar.', 'ashbi-mega-menu' ); ?></p>
				</div>

				<div class="abmm-mega-style-picker" id="abmm-mega-style-picker">
					<p class="abmm-mega-style-picker__label"><?php esc_html_e( 'Layout', 'ashbi-mega-menu' ); ?></p>
					<div class="abmm-mega-style-cards">
						<button type="button" class="abmm-mega-style-card" data-style="features">
							<span class="abmm-mega-style-card__preview abmm-mega-style-card__preview--features" aria-hidden="true">
								<span></span><span></span><span></span><span></span>
							</span>
							<strong><?php esc_html_e( 'Simple columns', 'ashbi-mega-menu' ); ?></strong>
							<small><?php esc_html_e( 'Links in 2, 3 or 4 columns', 'ashbi-mega-menu' ); ?></small>
						</button>
						<button type="button" class="abmm-mega-style-card" data-style="platforms">
							<span class="abmm-mega-style-card__preview abmm-mega-style-card__preview--platforms" aria-hidden="true">
								<span></span><span></span>
							</span>
							<strong><?php esc_html_e( 'Categories + links', 'ashbi-mega-menu' ); ?></strong>
							<small><?php esc_html_e( 'A list of categories, each with its own links', 'ashbi-mega-menu' ); ?></small>
						</button>
					</div>
					<label class="abmm-field abmm-mega-columns-field">
						<span><?php esc_html_e( 'Columns', 'ashbi-mega-menu' ); ?></span>
						<select id="abmm-mega-item-columns">
							<option value="1">1</option>
							<option value="2">2</option>
							<option value="3">3</option>
							<option value="4">4</option>
						</select>
					</label>
					<details class="abmm-more-options" id="abmm-mega-more-options" hidden>
						<summary><?php esc_html_e( 'More options', 'ashbi-mega-menu' ); ?></summary>
						<div class="abmm-field abmm-category-bar-field" id="abmm-category-bar-field" hidden>
							<span><?php esc_html_e( 'Category bar', 'ashbi-mega-menu' ); ?></span>
							<label class="abmm-checkbox-label">
								<input type="checkbox" id="abmm-hide-category-bar" value="1" />
								<?php esc_html_e( 'Hide the category bar and use the full width for columns', 'ashbi-mega-menu' ); ?>
							</label>
						</div>
					</details>
				</div>

				<!-- Category-bar layout editor -->
				<div class="abmm-mega-editor__layout" id="abmm-editor-platforms">
					<div class="abmm-mega-editor__cats">
						<h3><?php esc_html_e( 'Categories', 'ashbi-mega-menu' ); ?></h3>
						<p class="description"><?php esc_html_e( 'Each category shows its own links when selected.', 'ashbi-mega-menu' ); ?></p>
						<ul class="abmm-sortable abmm-cat-list" id="abmm-cat-list"></ul>
						<button type="button" class="button abmm-add-category"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span><?php esc_html_e( 'Add category', 'ashbi-mega-menu' ); ?></button>
					</div>

					<div class="abmm-mega-editor__cat-detail" id="abmm-cat-detail">
						<p class="abmm-placeholder"><?php esc_html_e( 'Select a category to edit its links.', 'ashbi-mega-menu' ); ?></p>
					</div>
				</div>

				<!-- Features grid layout editor -->
				<div class="abmm-mega-editor__features" id="abmm-editor-features" hidden>
					<h3><?php esc_html_e( 'Links', 'ashbi-mega-menu' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Each link can have an icon and a short description.', 'ashbi-mega-menu' ); ?></p>
					<ul class="abmm-sortable abmm-link-list" id="abmm-features-link-list"></ul>
					<button type="button" class="button abmm-add-feature-link"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span><?php esc_html_e( 'Add link', 'ashbi-mega-menu' ); ?></button>
				</div>

				<!-- Featured card: an optional highlighted card after the link columns -->
				<section class="abmm-featured-editor" id="abmm-featured-editor" aria-labelledby="abmm-featured-heading">
					<div class="abmm-featured-editor__head">
						<div class="abmm-featured-editor__intro">
							<h3 id="abmm-featured-heading"><?php esc_html_e( 'Featured card', 'ashbi-mega-menu' ); ?></h3>
							<p class="description" id="abmm-featured-help"><?php esc_html_e( 'An optional card after the link columns. Use it for a launch, a guide or an offer.', 'ashbi-mega-menu' ); ?></p>
						</div>
						<label class="abmm-switch">
							<input type="checkbox" id="abmm-featured-show" role="switch" aria-describedby="abmm-featured-help" aria-controls="abmm-featured-fields" />
							<span class="abmm-switch__track" aria-hidden="true"></span>
							<span class="screen-reader-text"><?php esc_html_e( 'Show a featured card', 'ashbi-mega-menu' ); ?></span>
						</label>
					</div>
					<div class="abmm-featured-editor__body" id="abmm-featured-fields" hidden>
						<div class="abmm-featured-editor__fields">
							<div class="abmm-featured-field">
								<label for="abmm-featured-title"><?php esc_html_e( 'Title', 'ashbi-mega-menu' ); ?></label>
								<input type="text" id="abmm-featured-title" class="abmm-featured-input" data-featured-key="title" placeholder="<?php esc_attr_e( 'e.g. New: Analytics 2.0', 'ashbi-mega-menu' ); ?>" />
							</div>
							<div class="abmm-featured-field">
								<label for="abmm-featured-text"><?php esc_html_e( 'Short text', 'ashbi-mega-menu' ); ?></label>
								<textarea id="abmm-featured-text" class="abmm-featured-input" data-featured-key="text" rows="2" placeholder="<?php esc_attr_e( 'One or two lines about it', 'ashbi-mega-menu' ); ?>"></textarea>
							</div>
							<div class="abmm-featured-field-row">
								<div class="abmm-featured-field">
									<label for="abmm-featured-label"><?php esc_html_e( 'Link label', 'ashbi-mega-menu' ); ?></label>
									<input type="text" id="abmm-featured-label" class="abmm-featured-input" data-featured-key="label" placeholder="<?php esc_attr_e( 'e.g. Learn more', 'ashbi-mega-menu' ); ?>" />
								</div>
								<div class="abmm-featured-field">
									<label for="abmm-featured-url"><?php esc_html_e( 'Link', 'ashbi-mega-menu' ); ?></label>
									<div class="abmm-wp-link">
										<input type="text" id="abmm-featured-url" class="abmm-url-field abmm-featured-input" data-featured-key="url" placeholder="<?php esc_attr_e( 'Search or type URL', 'ashbi-mega-menu' ); ?>" />
										<button type="button" class="button abmm-pick-url" title="<?php esc_attr_e( 'Select / edit link', 'ashbi-mega-menu' ); ?>" aria-label="<?php esc_attr_e( 'Select / edit link', 'ashbi-mega-menu' ); ?>" data-abmm-text="#abmm-featured-label">
											<span class="dashicons dashicons-admin-links" aria-hidden="true"></span>
										</button>
									</div>
								</div>
							</div>
						</div>
						<div class="abmm-featured-editor__media">
							<span class="abmm-featured-field__label" id="abmm-featured-image-label"><?php esc_html_e( 'Image', 'ashbi-mega-menu' ); ?></span>
							<div class="abmm-featured-media" id="abmm-featured-media">
								<span class="abmm-featured-media__thumb" aria-hidden="true"><span class="dashicons dashicons-format-image"></span></span>
								<span class="abmm-featured-media__actions">
									<button type="button" class="button abmm-featured-choose-image" aria-describedby="abmm-featured-image-label"><?php esc_html_e( 'Choose image', 'ashbi-mega-menu' ); ?></button>
									<button type="button" class="button-link abmm-featured-remove-image" hidden><?php esc_html_e( 'Remove', 'ashbi-mega-menu' ); ?></button>
								</span>
							</div>
							<label class="screen-reader-text" for="abmm-featured-image"><?php esc_html_e( 'Image URL', 'ashbi-mega-menu' ); ?></label>
							<input type="url" id="abmm-featured-image" class="abmm-featured-input" data-featured-key="image_url" placeholder="<?php esc_attr_e( 'Or paste an image URL', 'ashbi-mega-menu' ); ?>" />
							<p class="description"><?php esc_html_e( 'Landscape images work best, about 16:9.', 'ashbi-mega-menu' ); ?></p>
						</div>
					</div>
				</section>
			</div>
		</div>
		<div class="abmm-builder-panel-backdrop" id="abmm-builder-panel-backdrop" hidden aria-hidden="true"></div>
	</div>
</div>

<div class="abmm-exit-dialog" id="abmm-exit-dialog" hidden>
	<div class="abmm-exit-dialog__backdrop"></div>
	<div class="abmm-exit-dialog__panel" role="dialog" aria-modal="true" aria-labelledby="abmm-exit-dialog-title" aria-describedby="abmm-exit-dialog-description" tabindex="-1">
		<h2 id="abmm-exit-dialog-title"><?php esc_html_e( 'Leave with unsaved changes?', 'ashbi-mega-menu' ); ?></h2>
		<p id="abmm-exit-dialog-description"><?php esc_html_e( 'Save them now, or leave and recover them later from a draft kept in this browser.', 'ashbi-mega-menu' ); ?></p>
		<button type="button" class="button button-primary abmm-save-and-leave"><?php esc_html_e( 'Save and leave', 'ashbi-mega-menu' ); ?></button>
		<button type="button" class="button abmm-leave-builder"><?php esc_html_e( 'Leave, keep draft', 'ashbi-mega-menu' ); ?></button>
		<button type="button" class="button abmm-stay-builder"><?php esc_html_e( 'Keep editing', 'ashbi-mega-menu' ); ?></button>
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
			<p id="abmm-icon-empty" class="abmm-icon-empty" role="status" hidden><?php esc_html_e( 'No icons match. Try another word, or upload your own icon.', 'ashbi-mega-menu' ); ?></p>
			<div class="abmm-icon-upload">
				<button type="button" class="button abmm-upload-icon"><?php esc_html_e( 'Upload custom icon', 'ashbi-mega-menu' ); ?></button>
				<button type="button" class="button-link abmm-clear-icon"><?php esc_html_e( 'Clear icon', 'ashbi-mega-menu' ); ?></button>
			</div>
			<details class="abmm-icon-appearance abmm-more-options" id="abmm-icon-appearance" hidden>
				<summary><?php esc_html_e( 'More options', 'ashbi-mega-menu' ); ?></summary>
				<div class="abmm-icon-appearance__head">
					<h3><?php esc_html_e( 'Icon style for this item', 'ashbi-mega-menu' ); ?></h3>
					<button type="button" class="button-link abmm-reset-icon-style"><?php esc_html_e( 'Reset to menu style', 'ashbi-mega-menu' ); ?></button>
				</div>
				<p class="description"><?php esc_html_e( 'Overrides the icon style from the Design tab for this item only.', 'ashbi-mega-menu' ); ?></p>
				<div class="abmm-icon-appearance__fields" id="abmm-icon-appearance-fields"></div>
			</details>
		</div>
	</div>
</div>
