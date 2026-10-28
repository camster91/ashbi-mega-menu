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
	<?php
	$abmm_page_header = array(
		'title'   => __( 'Ashbi Mega Menu', 'ashbi-mega-menu' ),
		'intro'   => __( 'Build responsive mega menus with a visual editor.', 'ashbi-mega-menu' ),
		'current' => 'menus',
		'tabs'    => ! empty( $contextual_navigation_enabled ),
		'actions' => empty( $menus ) ? array() : array(
			array(
				'label'   => __( 'Create menu', 'ashbi-mega-menu' ),
				'class'   => 'abmm-create-menu',
				'primary' => true,
				'icon'    => 'plus-alt2',
			),
		),
	);
	require ABMM_PLUGIN_DIR . 'admin/views/brand-strip.php';
	?>

	<div class="abmm-list-status" id="abmm-list-status" role="status" aria-live="polite" tabindex="-1"></div>

	<?php if ( ! empty( $menus ) && ! empty( $setup_guide ) && empty( $setup_guide['dismissed'] ) ) : ?>
		<?php
		$abmm_guide_edit = admin_url( 'admin.php?page=ashbi-mega-menu&edit=' . rawurlencode( $setup_guide['menu_id'] ) );
		$abmm_guide_steps = $setup_guide['steps'];
		$abmm_guide_next  = array_search( false, $abmm_guide_steps, true );
		$abmm_guide_done  = count( array_filter( $abmm_guide_steps ) );
		?>
		<section class="abmm-setup-guide<?php echo $setup_guide['complete'] ? ' is-complete' : ''; ?>" id="abmm-setup-guide" aria-labelledby="abmm-setup-guide-title">
			<div class="abmm-setup-guide__head">
				<h2 id="abmm-setup-guide-title"><?php echo $setup_guide['complete'] ? esc_html__( 'Your menu is set up', 'ashbi-mega-menu' ) : esc_html__( 'Setup guide', 'ashbi-mega-menu' ); ?></h2>
				<span class="abmm-setup-guide__progress">
					<?php
					/* translators: 1: steps done, 2: total steps. */
					echo esc_html( sprintf( __( '%1$d of %2$d done', 'ashbi-mega-menu' ), $abmm_guide_done, count( $abmm_guide_steps ) ) );
					?>
				</span>
				<button type="button" class="button-link abmm-setup-guide__hide" data-guide-action="dismiss"><?php esc_html_e( 'Hide guide', 'ashbi-mega-menu' ); ?></button>
			</div>
			<ol class="abmm-setup-guide__steps">
				<li class="<?php echo $abmm_guide_steps['create'] ? 'is-done' : ''; ?>">
					<span class="abmm-setup-guide__status"><?php echo $abmm_guide_steps['create'] ? esc_html__( 'Done:', 'ashbi-mega-menu' ) : esc_html__( 'To do:', 'ashbi-mega-menu' ); ?></span>
					<strong><?php esc_html_e( 'Create a menu', 'ashbi-mega-menu' ); ?></strong>
				</li>
				<li class="<?php echo $abmm_guide_steps['edit'] ? 'is-done' : ''; ?><?php echo 'edit' === $abmm_guide_next ? ' is-next' : ''; ?>">
					<span class="abmm-setup-guide__status"><?php echo $abmm_guide_steps['edit'] ? esc_html__( 'Done:', 'ashbi-mega-menu' ) : esc_html__( 'To do:', 'ashbi-mega-menu' ); ?></span>
					<strong><?php esc_html_e( 'Add your links and save', 'ashbi-mega-menu' ); ?></strong>
					<p><?php esc_html_e( 'Replace any sample links with your own pages and clear the checklist in the editor. Done when a menu shows Ready.', 'ashbi-mega-menu' ); ?></p>
					<?php if ( ! $abmm_guide_steps['edit'] ) : ?><a class="button" href="<?php echo esc_url( $abmm_guide_edit ); ?>"><?php esc_html_e( 'Edit menu', 'ashbi-mega-menu' ); ?></a><?php endif; ?>
				</li>
				<li class="<?php echo $abmm_guide_steps['place'] ? 'is-done' : ''; ?><?php echo 'place' === $abmm_guide_next ? ' is-next' : ''; ?>">
					<span class="abmm-setup-guide__status"><?php echo $abmm_guide_steps['place'] ? esc_html__( 'Done:', 'ashbi-mega-menu' ) : esc_html__( 'To do:', 'ashbi-mega-menu' ); ?></span>
					<strong><?php esc_html_e( 'Place it on your site', 'ashbi-mega-menu' ); ?></strong>
					<p>
						<?php
						/* translators: %s: recommended placement method, such as "Block in your Header". */
						echo esc_html( sprintf( __( 'Recommended for your theme: %s.', 'ashbi-mega-menu' ), $placement_recommendation['label'] ) );
						?>
						<?php echo esc_html( $placement_recommendation['steps'] ); ?>
					</p>
					<p class="abmm-setup-guide__note"><?php esc_html_e( 'Placed it in a theme file or a page builder? Those can\'t be detected: check it on your site in the next step.', 'ashbi-mega-menu' ); ?></p>
					<p class="abmm-setup-guide__note"><?php esc_html_e( 'Keep your theme\'s menu while you test, so visitors never lose navigation. Once the new menu works, remove the old one from the header to avoid two menus. To roll back, remove the Ashbi block, widget or shortcode: your theme\'s menu and settings are never changed by this plugin.', 'ashbi-mega-menu' ); ?></p>
					<?php if ( ! $abmm_guide_steps['place'] ) : ?><a class="button" href="<?php echo esc_url( $abmm_guide_edit . '#placement' ); ?>"><?php esc_html_e( 'Get the block, widget or shortcode', 'ashbi-mega-menu' ); ?></a><?php endif; ?>
				</li>
				<li class="<?php echo $abmm_guide_steps['verify'] ? 'is-done' : ''; ?><?php echo ( 'verify' === $abmm_guide_next || ( 'place' === $abmm_guide_next && $abmm_guide_steps['edit'] ) ) ? ' is-next' : ''; ?>">
					<span class="abmm-setup-guide__status"><?php echo $abmm_guide_steps['verify'] ? esc_html__( 'Done:', 'ashbi-mega-menu' ) : esc_html__( 'To do:', 'ashbi-mega-menu' ); ?></span>
					<strong><?php esc_html_e( 'Check it on your site', 'ashbi-mega-menu' ); ?></strong>
					<p><?php esc_html_e( 'Open a page with the menu. Try every link, the dropdowns with a mouse and the keyboard, and the menu on a phone.', 'ashbi-mega-menu' ); ?></p>
					<a class="button" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View site', 'ashbi-mega-menu' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'ashbi-mega-menu' ); ?></span></a>
					<?php if ( $abmm_guide_steps['verify'] ) : ?>
						<button type="button" class="button-link" data-guide-action="unverify"><?php esc_html_e( 'Mark as not checked', 'ashbi-mega-menu' ); ?></button>
					<?php else : ?>
						<button type="button" class="button" data-guide-action="verify"><?php esc_html_e( 'I\'ve checked it', 'ashbi-mega-menu' ); ?></button>
					<?php endif; ?>
				</li>
			</ol>
		</section>
	<?php endif; ?>

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
				<?php if ( ! empty( $onboarding_pages ) ) : ?>
					<button type="button" class="abmm-choice abmm-pages-choice" aria-expanded="false" aria-controls="abmm-page-picker">
						<span class="abmm-choice__icon dashicons dashicons-admin-page" aria-hidden="true"></span>
						<strong><?php esc_html_e( 'Start from your pages', 'ashbi-mega-menu' ); ?></strong>
						<span><?php esc_html_e( 'Pick pages you already have. Child pages go in their parent\'s dropdown.', 'ashbi-mega-menu' ); ?></span>
					</button>
				<?php endif; ?>
			</div>
			<?php if ( ! empty( $onboarding_pages ) ) : ?>
				<fieldset class="abmm-page-picker" id="abmm-page-picker" hidden>
					<legend><?php esc_html_e( 'Pages to add to your menu', 'ashbi-mega-menu' ); ?></legend>
					<p class="description"><?php esc_html_e( 'Only published pages are listed. You can add, remove and reorder links in the editor afterwards.', 'ashbi-mega-menu' ); ?></p>
					<ul class="abmm-page-picker__list">
						<?php foreach ( $onboarding_pages as $abmm_page ) : ?>
							<?php $abmm_depth = count( get_post_ancestors( $abmm_page ) ); ?>
							<li style="--abmm-depth: <?php echo (int) min( $abmm_depth, 4 ); ?>">
								<label>
									<input type="checkbox" name="abmm_page_ids[]" value="<?php echo esc_attr( (string) $abmm_page->ID ); ?>" <?php checked( 0 === $abmm_depth ); ?> />
									<?php echo esc_html( '' !== trim( $abmm_page->post_title ) ? $abmm_page->post_title : __( '(no title)', 'ashbi-mega-menu' ) ); ?>
								</label>
							</li>
						<?php endforeach; ?>
					</ul>
					<div class="abmm-page-picker__actions">
						<button type="button" class="button button-primary abmm-pages-create"><?php esc_html_e( 'Create menu from these pages', 'ashbi-mega-menu' ); ?></button>
						<span class="abmm-page-picker__count" aria-live="polite"></span>
					</div>
				</fieldset>
			<?php endif; ?>
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
		<?php $abmm_has_toolbar = count( $menus ) > 6; ?>
		<div class="abmm-section-head abmm-menus-head">
			<h2 id="abmm-menus-title"><?php esc_html_e( 'Your menus', 'ashbi-mega-menu' ); ?></h2>
			<span class="abmm-count" data-abmm-menu-count><?php echo esc_html( number_format_i18n( count( $menus ) ) ); ?></span>
			<?php if ( $abmm_has_toolbar ) : ?>
				<?php /* Search, sort and view need JavaScript, so they stay hidden until admin.js shows them. */ ?>
				<div class="abmm-menu-toolbar" data-abmm-menu-toolbar data-abmm-user="<?php echo esc_attr( (string) get_current_user_id() ); ?>" hidden>
					<div class="abmm-menu-search">
						<label class="screen-reader-text" for="abmm-menu-search"><?php esc_html_e( 'Search menus', 'ashbi-mega-menu' ); ?></label>
						<span class="dashicons dashicons-search" aria-hidden="true"></span>
						<input type="search" id="abmm-menu-search" placeholder="<?php esc_attr_e( 'Search menus…', 'ashbi-mega-menu' ); ?>" autocomplete="off" data-abmm-menu-search />
					</div>
					<label class="screen-reader-text" for="abmm-menu-sort"><?php esc_html_e( 'Sort menus', 'ashbi-mega-menu' ); ?></label>
					<select id="abmm-menu-sort" class="abmm-menu-sort" data-abmm-menu-sort>
						<option value="recent"><?php esc_html_e( 'Newest first', 'ashbi-mega-menu' ); ?></option>
						<option value="name"><?php esc_html_e( 'Name (A–Z)', 'ashbi-mega-menu' ); ?></option>
					</select>
					<div class="abmm-view-toggle" role="group" aria-label="<?php esc_attr_e( 'Layout', 'ashbi-mega-menu' ); ?>">
						<button type="button" class="abmm-view-toggle__button is-current" data-abmm-view="grid" aria-pressed="true" title="<?php esc_attr_e( 'Grid view', 'ashbi-mega-menu' ); ?>"><span class="dashicons dashicons-grid-view" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Grid view', 'ashbi-mega-menu' ); ?></span></button>
						<button type="button" class="abmm-view-toggle__button" data-abmm-view="list" aria-pressed="false" title="<?php esc_attr_e( 'List view', 'ashbi-mega-menu' ); ?>"><span class="dashicons dashicons-list-view" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'List view', 'ashbi-mega-menu' ); ?></span></button>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php if ( $abmm_has_toolbar ) : ?>
			<p class="screen-reader-text" role="status" aria-live="polite" data-abmm-menu-results></p>
			<div class="abmm-menu-noresults" data-abmm-menu-noresults hidden>
				<span class="dashicons dashicons-search" aria-hidden="true"></span>
				<strong><?php esc_html_e( 'No menus match your search', 'ashbi-mega-menu' ); ?></strong>
				<button type="button" class="button-link" data-abmm-menu-clear><?php esc_html_e( 'Clear search', 'ashbi-mega-menu' ); ?></button>
			</div>
		<?php endif; ?>
		<div class="abmm-menu-cards" data-abmm-menu-cards>
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
				if ( empty( $items ) ) {
					// An empty menu gets a calm "nothing here yet" illustration instead of a broken-looking bar.
					$svg  = '<svg class="abmm-menu-thumb abmm-menu-thumb--empty" viewBox="0 0 320 50" preserveAspectRatio="xMidYMid meet" focusable="false" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">';
					$svg .= '<rect x="40" y="14" width="240" height="32" rx="7" fill="#ffffff" stroke="#e3e8f1" opacity=".7"/>';
					$svg .= '<rect x="24" y="6" width="272" height="34" rx="7" fill="#ffffff" stroke="#d7deea"/>';
					$svg .= '<rect x="36" y="16" width="14" height="14" rx="4" fill="#e6ebf3"/>';
					$svg .= '<rect x="55" y="20" width="26" height="6" rx="3" fill="#e6ebf3"/>';
					$svg .= '<rect x="112" y="20" width="26" height="6" rx="3" fill="#eef1f6"/>';
					$svg .= '<rect x="182" y="20" width="26" height="6" rx="3" fill="#eef1f6"/>';
					$svg .= '<rect x="240" y="17" width="44" height="12" rx="6" fill="#eef1f6"/>';
					$svg .= '<circle cx="160" cy="23" r="11" fill="' . $accent . '"/>';
					$svg .= '<path d="M160 18v10M155 23h10" stroke="#ffffff" stroke-width="2" stroke-linecap="round"/>';
					return $svg . '</svg>';
				}
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
			<?php $abmm_order = count( $menus ); ?>
			<?php foreach ( $menus as $id => $menu ) : ?>
				<?php
				$abmm_menu_readiness = $readiness[ $id ] ?? array(
					'ready'  => false,
					'issues' => array(),
				);
				$abmm_is_ready       = ! empty( $abmm_menu_readiness['ready'] );
				$abmm_issues         = is_array( $abmm_menu_readiness['issues'] ?? null ) ? array_values( $abmm_menu_readiness['issues'] ) : array();
				$abmm_issue_count    = count( $abmm_issues );
				$abmm_title          = $menu['title'] ?: __( 'Untitled', 'ashbi-mega-menu' );
				$abmm_shortcode      = '[ashbi_mega_menu id="' . $id . '"]';
				$abmm_actions_id     = 'abmm-card-actions-' . sanitize_html_class( $id );
				$abmm_issues_id      = 'abmm-card-issues-' . sanitize_html_class( $id );
				$abmm_edit_url       = admin_url( 'admin.php?page=ashbi-mega-menu&edit=' . rawurlencode( $id ) );
				$abmm_count          = count( $menu['items'] ?? array() );
				$abmm_search_title   = function_exists( 'mb_strtolower' ) ? mb_strtolower( $abmm_title ) : strtolower( $abmm_title );
				--$abmm_order;
				?>
				<div class="abmm-menu-card <?php echo $abmm_is_ready ? 'is-ready' : 'is-draft'; ?><?php echo $abmm_count ? '' : ' is-empty'; ?>" data-menu-id="<?php echo esc_attr( $id ); ?>" data-abmm-title="<?php echo esc_attr( $abmm_search_title ); ?>" data-abmm-order="<?php echo esc_attr( (string) $abmm_order ); ?>">
					<div class="abmm-menu-card__thumb">
						<?php echo $abmm_thumbnail( $menu ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from validated colours and numbers; attributes escaped above. ?>
						<?php if ( ! $abmm_count ) : ?>
							<span class="abmm-menu-card__thumb-label"><?php esc_html_e( 'Empty menu', 'ashbi-mega-menu' ); ?></span>
						<?php endif; ?>
					</div>
					<div class="abmm-menu-card__body">
						<div class="abmm-menu-card__head">
							<h2 class="abmm-menu-card__title"><?php echo esc_html( $abmm_title ); ?></h2>
							<span class="abmm-status <?php echo $abmm_is_ready ? 'abmm-status--ready' : 'abmm-status--neutral'; ?>"><?php echo esc_html( $abmm_is_ready ? __( 'Ready', 'ashbi-mega-menu' ) : __( 'Draft', 'ashbi-mega-menu' ) ); ?></span>
						</div>
						<p class="abmm-menu-card__meta">
							<span>
								<?php
								printf(
									/* translators: %d: number of top-level items */
									esc_html( _n( '%d menu item', '%d menu items', $abmm_count, 'ashbi-mega-menu' ) ),
									(int) $abmm_count
								);
								?>
							</span>
							<?php if ( ! empty( $menu['starter_source'] ) ) : ?>
								<span class="abmm-menu-card__tag"><?php esc_html_e( 'Sample', 'ashbi-mega-menu' ); ?></span>
							<?php endif; ?>
							<?php if ( ! $abmm_is_ready && $abmm_issue_count ) : ?>
								<span class="abmm-menu-card__issues">
									<a class="abmm-menu-card__issues-link" href="<?php echo esc_url( $abmm_edit_url . '#abmm-readiness-panel' ); ?>" aria-describedby="<?php echo esc_attr( $abmm_issues_id ); ?>">
										<span class="abmm-menu-card__issues-dot" aria-hidden="true"></span>
										<?php
										printf(
											/* translators: %d: number of setup issues still to fix. */
											esc_html( _n( '%d issue', '%d issues', $abmm_issue_count, 'ashbi-mega-menu' ) ),
											(int) $abmm_issue_count
										);
										?>
									</a>
									<span class="abmm-menu-card__tip" role="tooltip" id="<?php echo esc_attr( $abmm_issues_id ); ?>">
										<?php foreach ( array_slice( $abmm_issues, 0, 3 ) as $abmm_issue ) : ?>
											<span class="abmm-menu-card__tip-item"><?php echo esc_html( (string) ( $abmm_issue['message'] ?? '' ) ); ?></span>
										<?php endforeach; ?>
										<?php if ( $abmm_issue_count > 3 ) : ?>
											<span class="abmm-menu-card__tip-more">
												<?php
												printf(
													/* translators: %d: number of further setup issues. */
													esc_html( _n( 'and %d more', 'and %d more', $abmm_issue_count - 3, 'ashbi-mega-menu' ) ),
													(int) ( $abmm_issue_count - 3 )
												);
												?>
											</span>
										<?php endif; ?>
										<span class="abmm-menu-card__tip-hint"><?php esc_html_e( 'Open the checklist to fix it.', 'ashbi-mega-menu' ); ?></span>
									</span>
								</span>
							<?php endif; ?>
						</p>
						<?php if ( $abmm_is_ready ) : ?>
							<div class="abmm-menu-card__shortcode">
								<code><?php echo esc_html( $abmm_shortcode ); ?></code>
								<button type="button" class="button button-small abmm-copy-shortcode" data-shortcode="<?php echo esc_attr( $abmm_shortcode ); ?>" title="<?php esc_attr_e( 'Copy shortcode', 'ashbi-mega-menu' ); ?>"><?php esc_html_e( 'Copy', 'ashbi-mega-menu' ); ?></button>
							</div>
						<?php endif; ?>
					</div>
					<div class="abmm-menu-card__actions">
						<a class="button abmm-menu-card__main" href="<?php echo esc_url( $abmm_edit_url ); ?>">
							<?php echo esc_html( $abmm_is_ready ? __( 'Edit menu', 'ashbi-mega-menu' ) : __( 'Finish setup', 'ashbi-mega-menu' ) ); ?>
						</a>
						<?php if ( $abmm_is_ready ) : ?>
							<a class="button" href="<?php echo esc_url( $abmm_edit_url . '#placement' ); ?>"><?php esc_html_e( 'Place & verify', 'ashbi-mega-menu' ); ?></a>
						<?php endif; ?>
						<div class="abmm-card-menu">
							<button type="button" class="button abmm-card-menu__toggle" aria-haspopup="menu" aria-expanded="false" aria-controls="<?php echo esc_attr( $abmm_actions_id ); ?>">
								<span class="abmm-card-menu__dots" aria-hidden="true">&#8943;</span>
								<span class="screen-reader-text">
									<?php
									/* translators: %s: name of a menu or product. */
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
					<button type="button" class="button-link button-link-delete abmm-permanent-delete" data-menu-id="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Permanently delete', 'ashbi-mega-menu' ); ?></button>
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
				<h3><?php esc_html_e( 'Import from JSON file', 'ashbi-mega-menu' ); ?></h3>
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
				<h3><?php esc_html_e( 'For developers & AI agents', 'ashbi-mega-menu' ); ?></h3>
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

	<div class="abmm-dash-footer">
		<a href="https://github.com/camster91/ashbi-mega-menu/blob/main/docs/getting-started.md" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Getting started', 'ashbi-mega-menu' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'ashbi-mega-menu' ); ?></span></a>
		<a href="https://github.com/camster91/ashbi-mega-menu/blob/main/docs/user-guide.md" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'User guide', 'ashbi-mega-menu' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'ashbi-mega-menu' ); ?></span></a>
		<?php if ( ! empty( $menus ) && ! empty( $setup_guide['dismissed'] ) ) : ?>
			<button type="button" class="button-link" data-guide-action="reopen"><?php esc_html_e( 'Show setup guide', 'ashbi-mega-menu' ); ?></button>
		<?php endif; ?>
		<a href="https://github.com/camster91/ashbi-mega-menu/issues" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Report an issue', 'ashbi-mega-menu' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'ashbi-mega-menu' ); ?></span></a>
		<span class="abmm-dash-footer__version"><?php
		/* translators: %s: plugin version number. */
		printf( esc_html__( 'Version %s', 'ashbi-mega-menu' ), esc_html( ABMM_VERSION ) );
		?></span>
	</div>
</div>
