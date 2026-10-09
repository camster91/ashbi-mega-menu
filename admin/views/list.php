<?php
/**
 * Admin: menu list view.
 *
 * @package Ashbi_Mega_Menu
 *
 * @var array $menus All menus.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap abmm-wrap">
	<div class="abmm-admin-header">
		<div>
			<div class="abmm-logo-title"><img src="<?php echo esc_url( ABMM_PLUGIN_URL . 'assets/brand/icon.svg' ); ?>" width="52" height="52" alt="" /><h1><?php esc_html_e( 'Ashbi Mega Menu', 'ashbi-mega-menu' ); ?></h1></div>
			<p class="abmm-admin-intro"><?php esc_html_e( 'Build professional mega menus visually — no coding needed. Edit a menu, then place it on your site with a shortcode.', 'ashbi-mega-menu' ); ?></p>
		</div>
		<div class="abmm-admin-header__actions">
			<button type="button" class="button button-primary button-hero abmm-create-menu">
				<span class="dashicons dashicons-plus-alt2" style="margin-top:4px"></span>
				<?php esc_html_e( 'Create New Menu', 'ashbi-mega-menu' ); ?>
			</button>
		</div>
	</div>

	<div class="abmm-list-status" id="abmm-list-status" role="status" aria-live="polite" tabindex="-1"></div>

	<?php if ( ! empty( $show_onboarding ) ) : ?>
		<section class="abmm-onboarding" aria-labelledby="abmm-onboarding-title">
			<h2 id="abmm-onboarding-title"><?php esc_html_e( 'Create your first menu', 'ashbi-mega-menu' ); ?></h2>
			<p><?php esc_html_e( 'Start with an empty draft, or copy the clearly labelled sample template into a new menu with its own unique ID.', 'ashbi-mega-menu' ); ?></p>
			<div class="abmm-onboarding__choices">
				<button type="button" class="button button-primary abmm-onboarding-choice" data-choice="blank"><?php esc_html_e( 'Start blank', 'ashbi-mega-menu' ); ?></button>
				<button type="button" class="button abmm-onboarding-choice" data-choice="starter"><?php esc_html_e( 'Use sample template', 'ashbi-mega-menu' ); ?></button>
				<button type="button" class="button-link abmm-onboarding-choice" data-choice="dismiss"><?php esc_html_e( 'Dismiss for now', 'ashbi-mega-menu' ); ?></button>
			</div>
			<p class="description"><?php esc_html_e( 'The sample is a starting point. Replace its labels and destinations, then verify every link before placing it on a live site.', 'ashbi-mega-menu' ); ?></p>
		</section>
	<?php endif; ?>

	<?php if ( empty( $menus ) ) : ?>
		<div class="abmm-empty-state">
			<div class="abmm-empty-state__icon">☰</div>
			<h2><?php esc_html_e( 'No menus yet', 'ashbi-mega-menu' ); ?></h2>
			<p><?php esc_html_e( 'Create an empty menu or choose the sample template above. Nothing is placed on your site automatically.', 'ashbi-mega-menu' ); ?></p>
			<button type="button" class="button button-primary abmm-create-menu"><?php esc_html_e( 'Create New Menu', 'ashbi-mega-menu' ); ?></button>
		</div>
	<?php else : ?>
		<div class="abmm-menu-cards">
			<?php foreach ( $menus as $id => $menu ) : ?>
				<?php $abmm_menu_readiness = $readiness[ $id ] ?? array( 'ready' => false, 'issues' => array() ); ?>
				<div class="abmm-menu-card" data-menu-id="<?php echo esc_attr( $id ); ?>">
					<div class="abmm-menu-card__body">
						<h2 class="abmm-menu-card__title">
							<?php echo esc_html( $menu['title'] ?: __( 'Untitled', 'ashbi-mega-menu' ) ); ?>
							<?php if ( ! empty( $menu['starter_source'] ) ) : ?>
								<span class="abmm-badge abmm-badge--sample"><?php esc_html_e( 'Sample', 'ashbi-mega-menu' ); ?></span>
							<?php endif; ?>
							<span class="abmm-badge <?php echo ! empty( $abmm_menu_readiness['ready'] ) ? 'abmm-badge--ready' : 'abmm-badge--draft'; ?>">
								<?php echo ! empty( $abmm_menu_readiness['ready'] ) ? esc_html__( 'Ready', 'ashbi-mega-menu' ) : esc_html__( 'Draft', 'ashbi-mega-menu' ); ?>
							</span>
						</h2>
						<p class="abmm-menu-card__meta">
							<?php
							$abmm_count = count( $menu['items'] ?? array() );
							printf(
								/* translators: %d: number of top-level items */
								esc_html( _n( '%d menu item', '%d menu items', $abmm_count, 'ashbi-mega-menu' ) ),
								(int) $abmm_count
							);
							?>
						</p>
						<?php if ( ! empty( $abmm_menu_readiness['ready'] ) ) : ?>
							<div class="abmm-menu-card__shortcode">
								<code>[ashbi_mega_menu id="<?php echo esc_attr( $id ); ?>"]</code>
								<button type="button" class="button button-small abmm-copy-shortcode" data-shortcode='[ashbi_mega_menu id="<?php echo esc_attr( $id ); ?>"]' title="<?php esc_attr_e( 'Copy shortcode', 'ashbi-mega-menu' ); ?>">
									<?php esc_html_e( 'Copy', 'ashbi-mega-menu' ); ?>
								</button>
							</div>
						<?php else : ?>
							<p class="abmm-readiness-hint"><?php esc_html_e( 'Saved as a draft. Complete the readiness items in the editor before placement.', 'ashbi-mega-menu' ); ?></p>
						<?php endif; ?>
					</div>
					<div class="abmm-menu-card__actions">
						<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=ashbi-mega-menu&edit=' . rawurlencode( $id ) ) ); ?>">
							<?php esc_html_e( 'Edit Menu', 'ashbi-mega-menu' ); ?>
						</a>
						<button type="button" class="button abmm-export-menu" data-menu-id="<?php echo esc_attr( $id ); ?>" title="<?php esc_attr_e( 'Export as JSON', 'ashbi-mega-menu' ); ?>">
							<span class="dashicons dashicons-download" style="margin-top:3px"></span>
							<?php esc_html_e( 'Export', 'ashbi-mega-menu' ); ?>
						</button>
						<button type="button" class="button abmm-duplicate-menu" data-menu-id="<?php echo esc_attr( $id ); ?>">
							<?php esc_html_e( 'Duplicate', 'ashbi-mega-menu' ); ?>
						</button>
						<button type="button" class="button abmm-delete-menu" data-menu-id="<?php echo esc_attr( $id ); ?>" data-menu-title="<?php echo esc_attr( $menu['title'] ?: __( 'Untitled', 'ashbi-mega-menu' ) ); ?>">
							<?php esc_html_e( 'Archive', 'ashbi-mega-menu' ); ?>
						</button>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $archives ) ) : ?>
		<section class="abmm-archives" aria-labelledby="abmm-archives-title">
			<h2 id="abmm-archives-title"><?php esc_html_e( 'Archived menus', 'ashbi-mega-menu' ); ?></h2>
			<p><?php esc_html_e( 'Archived menus keep their original IDs so known placements work again after Restore.', 'ashbi-mega-menu' ); ?></p>
			<?php foreach ( $archives as $id => $abmm_entry ) : ?>
				<div class="abmm-archive-row" data-menu-id="<?php echo esc_attr( $id ); ?>">
					<strong><?php echo esc_html( $abmm_entry['menu']['title'] ?? $id ); ?></strong>
					<code><?php echo esc_html( $id ); ?></code>
					<button type="button" class="button abmm-restore-menu" data-menu-id="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Restore', 'ashbi-mega-menu' ); ?></button>
					<button type="button" class="button-link-delete abmm-permanent-delete" data-menu-id="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Permanently delete', 'ashbi-mega-menu' ); ?></button>
				</div>
			<?php endforeach; ?>
		</section>
	<?php endif; ?>

	<!-- Import Panel -->
	<div class="abmm-import-panel">
		<h3><?php esc_html_e( 'Import / Export', 'ashbi-mega-menu' ); ?></h3>
		<div class="abmm-import-panel__body">
			<div class="abmm-import-upload">
				<h4><?php esc_html_e( 'Import from JSON file', 'ashbi-mega-menu' ); ?></h4>
				<p class="description"><?php esc_html_e( 'Upload a .json file to import menus. You can also use the REST API or WP-CLI for automated imports.', 'ashbi-mega-menu' ); ?></p>
				<form class="abmm-import-form" id="abmm-import-form" enctype="multipart/form-data">
					<?php wp_nonce_field( 'abmm_admin', 'abmm_import_nonce' ); ?>
					<div class="abmm-import-form__row">
						<label class="abmm-field">
							<span><?php esc_html_e( 'Import mode', 'ashbi-mega-menu' ); ?></span>
							<select id="abmm-import-mode" name="mode">
								<option value="merge"><?php esc_html_e( 'Merge — add new menus, update existing', 'ashbi-mega-menu' ); ?></option>
								<option value="replace"><?php esc_html_e( 'Replace — clear all menus first', 'ashbi-mega-menu' ); ?></option>
								<option value="copy"><?php esc_html_e( 'Copy — always create independent menus', 'ashbi-mega-menu' ); ?></option>
							</select>
						</label>
						<label class="abmm-field">
							<span><?php esc_html_e( 'JSON file', 'ashbi-mega-menu' ); ?></span>
							<input type="file" id="abmm-import-file" name="import_file" accept=".json,application/json" required />
						</label>
					</div>
					<button type="submit" class="button button-secondary">
						<span class="dashicons dashicons-upload" style="margin-top:3px"></span>
						<?php esc_html_e( 'Import Menus', 'ashbi-mega-menu' ); ?>
					</button>
					<span class="abmm-import-status" id="abmm-import-status" aria-live="polite"></span>
					<div id="abmm-import-preview" class="abmm-import-preview" hidden aria-live="polite"></div>
				</form>
			</div>
			<div class="abmm-import-api">
				<h4><?php esc_html_e( 'For developers & AI agents', 'ashbi-mega-menu' ); ?></h4>
				<p class="description"><?php esc_html_e( 'Programmatically manage menus using the REST API or WP-CLI.', 'ashbi-mega-menu' ); ?></p>
				<div class="abmm-api-refs">
					<div class="abmm-api-ref">
						<strong>REST API</strong>
						<code>GET <?php echo esc_html( rest_url( ABMM_Import_Export::REST_NAMESPACE . '/menus' ) ); ?></code>
						<code>POST <?php echo esc_html( rest_url( ABMM_Import_Export::REST_NAMESPACE . '/menus/import' ) ); ?></code>
					</div>
					<div class="abmm-api-ref">
						<strong>WP-CLI</strong>
						<code>wp abmm export --file=/path/to/menu.json</code>
						<code>wp abmm import --file=/path/to/menu.json --mode=merge</code>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="abmm-help-box">
		<h3><?php esc_html_e( 'How to use', 'ashbi-mega-menu' ); ?></h3>
		<ol>
			<li><?php esc_html_e( 'Create or edit a menu in this panel.', 'ashbi-mega-menu' ); ?></li>
			<li><?php esc_html_e( 'Set a top-level item to "Mega Menu", then pick "With category bar" or "Simple columns (no category bar)".', 'ashbi-mega-menu' ); ?></li>
			<li><?php esc_html_e( 'Copy the shortcode and paste it into a page, header template, or widget.', 'ashbi-mega-menu' ); ?></li>
			<li><?php esc_html_e( 'Or add this in your theme: <?php abmm_render_menu( \'menu_demo\' ); ?>', 'ashbi-mega-menu' ); ?></li>
		</ol>
	</div>
</div>
