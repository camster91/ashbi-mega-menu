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
<div class="wrap abmm-wrap abmm-dashboard">
	<header class="abmm-dash-header">
		<div class="abmm-logo-title">
			<img src="<?php echo esc_url( ABMM_PLUGIN_URL . 'assets/brand/icon.svg' ); ?>" width="44" height="44" alt="" />
			<div>
				<h1><?php esc_html_e( 'Ashbi Mega Menu', 'ashbi-mega-menu' ); ?></h1>
				<p class="abmm-admin-intro"><?php esc_html_e( 'Build responsive mega menus with a visual editor.', 'ashbi-mega-menu' ); ?></p>
			</div>
		</div>
		<?php if ( ! empty( $menus ) ) : ?>
			<div class="abmm-admin-header__actions">
				<button type="button" class="button button-primary abmm-create-menu">
					<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
					<?php esc_html_e( 'Create menu', 'ashbi-mega-menu' ); ?>
				</button>
			</div>
		<?php endif; ?>
	</header>

	<div class="abmm-list-status" id="abmm-list-status" role="status" aria-live="polite" tabindex="-1"></div>

	<?php if ( empty( $menus ) && ! empty( $show_onboarding ) ) : ?>
		<section class="abmm-welcome" aria-labelledby="abmm-onboarding-title">
			<h2 id="abmm-onboarding-title"><?php esc_html_e( 'Create your first menu', 'ashbi-mega-menu' ); ?></h2>
			<p><?php esc_html_e( 'Pick a starting point. Nothing changes on your site until you place the menu.', 'ashbi-mega-menu' ); ?></p>
			<div class="abmm-welcome__choices">
				<button type="button" class="abmm-choice abmm-onboarding-choice" data-choice="blank">
					<span class="abmm-choice__icon dashicons dashicons-plus-alt2" aria-hidden="true"></span>
					<strong><?php esc_html_e( 'Start from scratch', 'ashbi-mega-menu' ); ?></strong>
					<span><?php esc_html_e( 'An empty menu you build yourself.', 'ashbi-mega-menu' ); ?></span>
				</button>
				<button type="button" class="abmm-choice abmm-onboarding-choice" data-choice="starter">
					<span class="abmm-choice__icon dashicons dashicons-layout" aria-hidden="true"></span>
					<strong><?php esc_html_e( 'Use a template', 'ashbi-mega-menu' ); ?></strong>
					<span><?php esc_html_e( 'A ready-made example menu to edit.', 'ashbi-mega-menu' ); ?></span>
				</button>
			</div>
			<ol class="abmm-how">
				<li><span aria-hidden="true">1</span><?php esc_html_e( 'Build your menu', 'ashbi-mega-menu' ); ?></li>
				<li><span aria-hidden="true">2</span><?php esc_html_e( 'Place it with a block, widget or shortcode', 'ashbi-mega-menu' ); ?></li>
				<li><span aria-hidden="true">3</span><?php esc_html_e( 'Check it on desktop and mobile', 'ashbi-mega-menu' ); ?></li>
			</ol>
			<button type="button" class="button-link abmm-onboarding-choice abmm-welcome__skip" data-choice="dismiss"><?php esc_html_e( 'Skip for now', 'ashbi-mega-menu' ); ?></button>
		</section>
	<?php elseif ( empty( $menus ) ) : ?>
		<div class="abmm-empty-state">
			<span class="abmm-empty-state__icon dashicons dashicons-menu-alt3" aria-hidden="true"></span>
			<h2><?php esc_html_e( 'No menus yet', 'ashbi-mega-menu' ); ?></h2>
			<p><?php esc_html_e( 'Create a menu, then place it with a block, widget or shortcode.', 'ashbi-mega-menu' ); ?></p>
			<button type="button" class="button button-primary abmm-create-menu"><?php esc_html_e( 'Create menu', 'ashbi-mega-menu' ); ?></button>
		</div>
	<?php else : ?>
		<div class="abmm-section-head">
			<h2><?php esc_html_e( 'Your menus', 'ashbi-mega-menu' ); ?></h2>
			<span class="abmm-count"><?php echo esc_html( number_format_i18n( count( $menus ) ) ); ?></span>
		</div>
		<div class="abmm-menu-cards">
			<?php
			/*
			 * A small, decorative schematic of each menu's header bar: a brand
			 * block, one pill per top-level item, a CTA chip and, when the menu
			 * has a mega item, a hint of its panel. Only validated colours and
			 * numbers reach the markup.
			 */
			$abmm_color     = static function ( $value, $fallback ) {
				$value = is_string( $value ) ? trim( $value ) : '';
				return preg_match( '/^#(?:[0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value ) ? $value : $fallback;
			};
			$abmm_thumbnail = static function ( $menu ) use ( $abmm_color ) {
				$settings = is_array( $menu['settings'] ?? null ) ? $menu['settings'] : array();
				$items    = is_array( $menu['items'] ?? null ) ? array_values( $menu['items'] ) : array();
				$header   = esc_attr( $abmm_color( $settings['header_bg'] ?? '', '#ffffff' ) );
				$accent   = esc_attr( $abmm_color( $settings['accent'] ?? '', '#2457ff' ) );
				$ink      = esc_attr( $abmm_color( $settings['nav_link_color'] ?? '', '#2c2c2c' ) );
				$line     = esc_attr( $abmm_color( $settings['border_color'] ?? '', '#e5e5ea' ) );
				$align    = in_array( $settings['nav_align'] ?? '', array( 'left', 'center', 'right' ), true ) ? $settings['nav_align'] : 'center';
				$radii    = array(
					'square'  => 1,
					'rounded' => 4,
					'pill'    => 8,
				);
				$cta_r    = $radii[ $settings['cta_style'] ?? 'rounded' ] ?? 4;
				$has_cta  = ! empty( $menu['cta']['show'] );
				$bar_x    = 12;
				$bar_w    = 296;
				$bar_y    = 12;
				$bar_h    = 34;
				$mid      = $bar_y + ( $bar_h / 2 );
				$start    = $bar_x + 66;
				$end      = $bar_x + $bar_w - ( $has_cta ? 64 : 14 );
				$pills    = array();
				$used     = 0;
				foreach ( $items as $item ) {
					$label   = is_array( $item ) && is_scalar( $item['label'] ?? '' ) ? (string) ( $item['label'] ?? '' ) : '';
					$length  = function_exists( 'mb_strlen' ) ? mb_strlen( $label ) : strlen( $label );
					$width   = max( 16, min( 42, 8 + ( $length * 3 ) ) );
					$is_mega = is_array( $item ) && 'mega' === ( $item['type'] ?? 'link' );
					$needed  = ( $pills ? 10 : 0 ) + $width + ( $is_mega ? 9 : 0 );
					if ( $used + $needed > $end - $start ) {
						break;
					}
					$pills[] = array(
						'w'    => $width,
						'mega' => $is_mega,
					);
					$used   += $needed;
				}
				if ( 'left' === $align ) {
					$x = $start;
				} elseif ( 'right' === $align ) {
					$x = $end - $used;
				} else {
					$x = $start + ( ( $end - $start - $used ) / 2 );
				}
				$svg  = '<svg class="abmm-menu-thumb" viewBox="0 0 320 92" preserveAspectRatio="xMidYMid meet" focusable="false" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">';
				$svg .= '<rect x="' . $bar_x . '" y="' . $bar_y . '" width="' . $bar_w . '" height="' . $bar_h . '" rx="7" fill="' . $header . '" stroke="' . $line . '"/>';
				$svg .= '<rect x="' . ( $bar_x + 12 ) . '" y="' . ( $mid - 7 ) . '" width="14" height="14" rx="4" fill="' . $accent . '"/>';
				$svg .= '<rect x="' . ( $bar_x + 31 ) . '" y="' . ( $mid - 3 ) . '" width="24" height="6" rx="3" fill="' . $ink . '" opacity=".8"/>';
				if ( empty( $pills ) ) {
					$svg .= '<rect x="' . ( $start + ( ( $end - $start - 96 ) / 2 ) ) . '" y="' . ( $mid - 4 ) . '" width="96" height="8" rx="4" fill="none" stroke="' . $ink . '" stroke-dasharray="3 3" opacity=".35"/>';
				}
				$panel_x = null;
				foreach ( $pills as $index => $pill ) {
					$x   += $index ? 10 : 0;
					$svg .= '<rect x="' . round( $x, 1 ) . '" y="' . ( $mid - 3 ) . '" width="' . $pill['w'] . '" height="6" rx="3" fill="' . $ink . '" opacity=".55"/>';
					if ( $pill['mega'] ) {
						$svg .= '<path d="M' . round( $x + $pill['w'] + 3, 1 ) . ' ' . ( $mid - 1.5 ) . 'l2.5 3 2.5-3" fill="none" stroke="' . $ink . '" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" opacity=".55"/>';
						if ( null === $panel_x ) {
							$panel_x = $x;
						}
					}
					$x += $pill['w'] + ( $pill['mega'] ? 9 : 0 );
				}
				if ( $has_cta ) {
					$cta_x = $bar_x + $bar_w - 54;
					$svg  .= '<rect x="' . $cta_x . '" y="' . ( $mid - 8 ) . '" width="42" height="16" rx="' . $cta_r . '" fill="' . $accent . '"/>';
					$svg  .= '<rect x="' . ( $cta_x + 10 ) . '" y="' . ( $mid - 2 ) . '" width="22" height="4" rx="2" fill="#fff" opacity=".85"/>';
				}
				if ( null !== $panel_x ) {
					$panel_x = round( max( $bar_x + 8, min( $panel_x - 12, $bar_x + $bar_w - 158 ) ), 1 );
					$py      = $bar_y + $bar_h + 5;
					$svg    .= '<rect x="' . $panel_x . '" y="' . $py . '" width="150" height="34" rx="6" fill="' . $header . '" stroke="' . $line . '"/>';
					$svg    .= '<rect x="' . ( $panel_x + 10 ) . '" y="' . ( $py + 9 ) . '" width="34" height="5" rx="2.5" fill="' . $accent . '" opacity=".75"/>';
					$svg    .= '<rect x="' . ( $panel_x + 10 ) . '" y="' . ( $py + 20 ) . '" width="24" height="4" rx="2" fill="' . $ink . '" opacity=".3"/>';
					$svg    .= '<rect x="' . ( $panel_x + 58 ) . '" y="' . ( $py + 9 ) . '" width="30" height="4" rx="2" fill="' . $ink . '" opacity=".3"/>';
					$svg    .= '<rect x="' . ( $panel_x + 58 ) . '" y="' . ( $py + 19 ) . '" width="38" height="4" rx="2" fill="' . $ink . '" opacity=".3"/>';
					$svg    .= '<rect x="' . ( $panel_x + 106 ) . '" y="' . ( $py + 9 ) . '" width="34" height="16" rx="3" fill="' . $ink . '" opacity=".08"/>';
				}
				return $svg . '</svg>';
			};
			?>
			<?php foreach ( $menus as $id => $menu ) : ?>
				<?php
				$abmm_menu_readiness = $readiness[ $id ] ?? array(
					'ready'  => false,
					'issues' => array(),
				);
				$abmm_is_ready       = ! empty( $abmm_menu_readiness['ready'] );
				$abmm_issues         = is_array( $abmm_menu_readiness['issues'] ?? null ) ? array_values( $abmm_menu_readiness['issues'] ) : array();
				$abmm_issue_count    = count( $abmm_issues );
				$abmm_first_issue    = $abmm_issue_count ? (string) ( $abmm_issues[0]['message'] ?? '' ) : '';
				$abmm_title          = $menu['title'] ?: __( 'Untitled', 'ashbi-mega-menu' );
				$abmm_shortcode      = '[ashbi_mega_menu id="' . $id . '"]';
				$abmm_actions_id     = 'abmm-card-actions-' . sanitize_html_class( $id );
				if ( $abmm_is_ready ) {
					$abmm_status_label = __( 'Ready', 'ashbi-mega-menu' );
				} elseif ( $abmm_issue_count ) {
					$abmm_status_label = sprintf(
						/* translators: %d: number of setup issues still to fix. */
						_n( 'Draft · %d to fix', 'Draft · %d to fix', $abmm_issue_count, 'ashbi-mega-menu' ),
						$abmm_issue_count
					);
				} else {
					$abmm_status_label = __( 'Draft', 'ashbi-mega-menu' );
				}
				?>
				<div class="abmm-menu-card <?php echo $abmm_is_ready ? 'is-ready' : 'is-draft'; ?>" data-menu-id="<?php echo esc_attr( $id ); ?>">
					<div class="abmm-menu-card__thumb">
						<?php echo $abmm_thumbnail( $menu ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from validated colours and numbers; attributes escaped above. ?>
					</div>
					<div class="abmm-menu-card__body">
						<div class="abmm-menu-card__head">
							<h2 class="abmm-menu-card__title"><?php echo esc_html( $abmm_title ); ?></h2>
							<span class="abmm-status <?php echo $abmm_is_ready ? 'abmm-status--ready' : 'abmm-status--draft'; ?>"<?php echo '' !== $abmm_first_issue ? ' title="' . esc_attr( $abmm_first_issue ) . '"' : ''; ?>><?php echo esc_html( $abmm_status_label ); ?></span>
						</div>
						<p class="abmm-menu-card__meta">
							<?php
							$abmm_count = count( $menu['items'] ?? array() );
							printf(
								/* translators: %d: number of top-level items */
								esc_html( _n( '%d menu item', '%d menu items', $abmm_count, 'ashbi-mega-menu' ) ),
								(int) $abmm_count
							);
							?>
							<?php if ( ! empty( $menu['starter_source'] ) ) : ?>
								<span class="abmm-menu-card__tag"><?php esc_html_e( 'Sample', 'ashbi-mega-menu' ); ?></span>
							<?php endif; ?>
						</p>
						<?php if ( $abmm_is_ready ) : ?>
							<div class="abmm-menu-card__shortcode">
								<code><?php echo esc_html( $abmm_shortcode ); ?></code>
								<button type="button" class="button button-small abmm-copy-shortcode" data-shortcode="<?php echo esc_attr( $abmm_shortcode ); ?>" title="<?php esc_attr_e( 'Copy shortcode', 'ashbi-mega-menu' ); ?>"><?php esc_html_e( 'Copy', 'ashbi-mega-menu' ); ?></button>
							</div>
						<?php else : ?>
							<p class="abmm-menu-card__issue"><?php echo esc_html( '' !== $abmm_first_issue ? $abmm_first_issue : __( 'Finish setup in the editor before placing it.', 'ashbi-mega-menu' ) ); ?></p>
						<?php endif; ?>
					</div>
					<div class="abmm-menu-card__actions">
						<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=ashbi-mega-menu&edit=' . rawurlencode( $id ) ) ); ?>">
							<?php echo esc_html( $abmm_is_ready ? __( 'Edit Menu', 'ashbi-mega-menu' ) : __( 'Finish setup', 'ashbi-mega-menu' ) ); ?>
						</a>
						<?php if ( $abmm_is_ready ) : ?>
							<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=ashbi-mega-menu&edit=' . rawurlencode( $id ) . '#placement' ) ); ?>"><?php esc_html_e( 'Place & verify', 'ashbi-mega-menu' ); ?></a>
						<?php endif; ?>
						<div class="abmm-card-menu">
							<button type="button" class="button abmm-card-menu__toggle" aria-haspopup="menu" aria-expanded="false" aria-controls="<?php echo esc_attr( $abmm_actions_id ); ?>">
								<span class="abmm-card-menu__dots" aria-hidden="true">&#8943;</span>
								<span class="screen-reader-text">
									<?php
									/* translators: %s: menu title. */
									echo esc_html( sprintf( __( 'More actions for %s', 'ashbi-mega-menu' ), $abmm_title ) );
									?>
								</span>
							</button>
							<div class="abmm-card-menu__list" id="<?php echo esc_attr( $abmm_actions_id ); ?>" role="menu" aria-label="<?php esc_attr_e( 'More actions', 'ashbi-mega-menu' ); ?>" hidden>
								<button type="button" role="menuitem" tabindex="-1" class="button abmm-export-menu" data-menu-id="<?php echo esc_attr( $id ); ?>" title="<?php esc_attr_e( 'Export as JSON', 'ashbi-mega-menu' ); ?>"><?php esc_html_e( 'Export', 'ashbi-mega-menu' ); ?></button>
								<button type="button" role="menuitem" tabindex="-1" class="button abmm-duplicate-menu" data-menu-id="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Duplicate', 'ashbi-mega-menu' ); ?></button>
								<button type="button" role="menuitem" tabindex="-1" class="button abmm-delete-menu" data-menu-id="<?php echo esc_attr( $id ); ?>" data-menu-title="<?php echo esc_attr( $abmm_title ); ?>"><?php esc_html_e( 'Archive', 'ashbi-mega-menu' ); ?></button>
							</div>
						</div>
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

	<section class="abmm-tools" aria-labelledby="abmm-tools-title">
	<h2 id="abmm-tools-title"><?php esc_html_e( 'Tools', 'ashbi-mega-menu' ); ?></h2>
	<details class="abmm-tool" id="abmm-transfer-panel">
		<summary>
			<span class="abmm-tool__icon dashicons dashicons-migrate" aria-hidden="true"></span>
			<span class="abmm-tool__text"><strong><?php esc_html_e( 'Import and export', 'ashbi-mega-menu' ); ?></strong><small><?php esc_html_e( 'Move menus between sites, or keep a backup file.', 'ashbi-mega-menu' ); ?></small></span>
		</summary>
		<div class="abmm-import-panel__body">
			<div class="abmm-import-upload">
				<h4><?php esc_html_e( 'Import from JSON file', 'ashbi-mega-menu' ); ?></h4>
				<p class="description"><?php esc_html_e( 'Upload a .json file to preview changes before importing. Your current menus are backed up before replacement.', 'ashbi-mega-menu' ); ?></p>
				<p class="description"><?php printf( /* translators: %s: maximum formatted import size. */ esc_html__( 'Maximum import file size: %s.', 'ashbi-mega-menu' ), esc_html( size_format( ABMM_Import_Export::MAX_IMPORT_BYTES ) ) ); ?></p>
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
						<code>wp abmm export &gt; menu.json</code>
						<code>wp abmm import --file=menu.json --mode=merge</code>
					</div>
				</div>
			</div>
		</div>
	</details>

	<details class="abmm-tool" id="abmm-backup-panel">
		<summary>
			<span class="abmm-tool__icon dashicons dashicons-backup" aria-hidden="true"></span>
			<span class="abmm-tool__text"><strong><?php esc_html_e( 'Recovery snapshots', 'ashbi-mega-menu' ); ?></strong><small><?php esc_html_e( 'Undo an import by restoring an automatic backup.', 'ashbi-mega-menu' ); ?></small></span>
		</summary>
		<div class="abmm-tool__body">
		<p class="description"><?php esc_html_e( 'The last five backups are kept. Restoring replaces all current menus, after saving them as a new backup first.', 'ashbi-mega-menu' ); ?></p>
		<button type="button" class="button abmm-load-backups"><?php esc_html_e( 'Refresh backups', 'ashbi-mega-menu' ); ?></button>
		<div id="abmm-backup-status" role="status" aria-live="polite" tabindex="-1"></div>
		<div id="abmm-backup-list"></div>
		</div>
	</details>

	<details class="abmm-tool" id="abmm-modules-panel">
		<summary>
			<span class="abmm-tool__icon dashicons dashicons-admin-settings" aria-hidden="true"></span>
			<span class="abmm-tool__text"><strong><?php esc_html_e( 'Advanced options', 'ashbi-mega-menu' ); ?></strong><small><?php esc_html_e( 'Section-specific navigation for larger sites.', 'ashbi-mega-menu' ); ?></small></span>
		</summary>
		<form class="abmm-tool__body" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'abmm_save_modules' ); ?>
			<input type="hidden" name="action" value="abmm_save_modules" />
			<label><input type="checkbox" name="contextual_navigation" value="1" <?php checked( $contextual_navigation_enabled ); ?> /> <strong><?php esc_html_e( 'Contextual navigation for multi-section sites', 'ashbi-mega-menu' ); ?></strong></label>
			<p class="description"><?php esc_html_e( 'Advanced: add inherited local navigation and identity overrides for products, services, departments, documentation areas or locations. Leave disabled for a standard site menu.', 'ashbi-mega-menu' ); ?></p>
			<?php submit_button( __( 'Save modules', 'ashbi-mega-menu' ), 'secondary', 'submit', false ); ?>
		</form>
	</details>

	</section>

	<footer class="abmm-dash-footer">
		<a href="https://github.com/camster91/ashbi-mega-menu/blob/main/docs/getting-started.md" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Getting started', 'ashbi-mega-menu' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'ashbi-mega-menu' ); ?></span></a>
		<a href="https://github.com/camster91/ashbi-mega-menu/blob/main/docs/user-guide.md" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'User guide', 'ashbi-mega-menu' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'ashbi-mega-menu' ); ?></span></a>
		<a href="https://github.com/camster91/ashbi-mega-menu/issues" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Report an issue', 'ashbi-mega-menu' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'ashbi-mega-menu' ); ?></span></a>
		<span class="abmm-dash-footer__version"><?php
		/* translators: %s: plugin version number. */
		printf( esc_html__( 'Version %s', 'ashbi-mega-menu' ), esc_html( ABMM_VERSION ) );
		?></span>
	</footer>
</div>
