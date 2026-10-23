<?php
/**
 * Reusable product navigation profiles.
 *
 * @package Ashbi_Mega_Menu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABMM_Product_Profiles {

	const OPTION_KEY = 'abmm_product_profiles';

	/**
	 * @var ABMM_Product_Profiles|null
	 */
	private static $instance = null;

	/**
	 * @var string[] Admin hooks of the Product Navigation and Coverage pages.
	 */
	private $page_hooks = array();

	/**
	 * @return ABMM_Product_Profiles
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_pages' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_post_abmm_save_product_profile', array( $this, 'save_profile' ) );
		add_action( 'admin_post_abmm_delete_product_profile', array( $this, 'delete_profile' ) );
	}

	/**
	 * @return array<string,array>
	 */
	public function get_all() {
		$profiles = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $profiles ) ) {
			return array();
		}

		$clean = array();
		foreach ( $profiles as $id => $profile ) {
			$id = sanitize_key( $id );
			if ( $id ) {
				$clean[ $id ] = $this->sanitize_profile( $profile );
			}
		}
		return $clean;
	}

	/**
	 * @param string $id Profile ID.
	 * @return array|null
	 */
	public function get( $id ) {
		$profiles = $this->get_all();
		$id       = sanitize_key( $id );
		return $profiles[ $id ] ?? null;
	}

	/**
	 * Keep product-specific information separate from the shared header menu.
	 *
	 * @param array $raw Raw profile data.
	 * @return array
	 */
	public function sanitize_profile( $raw ) {
		$raw   = is_array( $raw ) ? $raw : array();
		$links = array();
		$rail_style = sanitize_key( $this->scalar_string( $raw['rail_style'] ?? 'underline' ) );
		if ( ! in_array( $rail_style, array( 'underline', 'pills' ), true ) ) {
			$rail_style = 'underline';
		}
		foreach ( is_array( $raw['links'] ?? null ) ? $raw['links'] : array() as $link ) {
			if ( ! is_array( $link ) ) {
				continue;
			}
			$label = $this->sanitize_text( $link['label'] ?? '' );
			$url   = $this->sanitize_url( $link['url'] ?? '' );
			if ( '' !== $label && '' !== $url ) {
				$links[] = array(
					'label' => $label,
					'url'   => $url,
				);
			}
			if ( 6 === count( $links ) ) {
				break;
			}
		}

		$cta = is_array( $raw['cta'] ?? null ) ? $raw['cta'] : array();
		return array(
			'title'        => $this->sanitize_text( $raw['title'] ?? '' ),
			'label'        => $this->sanitize_text( $raw['label'] ?? '' ),
			'url'          => $this->sanitize_url( $raw['url'] ?? '' ),
			'root_page_id' => absint( $this->scalar_string( $raw['root_page_id'] ?? 0 ) ),
			'rail_style'   => $rail_style,
			'links'        => $links,
			'cta'          => array(
				'show'  => ! empty( $cta['show'] ),
				'label' => $this->sanitize_text( $cta['label'] ?? '' ),
				'url'   => $this->sanitize_url( $cta['url'] ?? '' ),
			),
		);
	}

	/**
	 * Resolve a profile with explicit page assignments taking priority over a
	 * product hub ancestor. Integrations such as an Elementor template may set
	 * a profile through the documented filter without copying header data.
	 *
	 * @param int $post_id Post ID.
	 * @return array{profile:array|null,source:string,post_id:int}
	 */
	public function resolve_for_post( $post_id ) {
		$post_id = absint( $post_id );
		$profiles = $this->get_all();
		if ( empty( $profiles ) ) {
			return array( 'profile' => null, 'source' => 'fallback', 'post_id' => $post_id );
		}

		$assigned = $post_id ? sanitize_key( (string) get_post_meta( $post_id, ABMM_Page_Context::META_KEY, true ) ) : '';
		$source   = $assigned ? 'page' : 'fallback';
		if ( $post_id && ( ! $assigned || ! isset( $profiles[ $assigned ] ) ) ) {
			$assigned = '';
			$source   = 'ancestor';
			foreach ( array_merge( array( $post_id ), get_post_ancestors( $post_id ) ) as $candidate_id ) {
				foreach ( $profiles as $profile_id => $profile ) {
					if ( absint( $profile['root_page_id'] ) === absint( $candidate_id ) ) {
						$assigned = $profile_id;
						break 2;
					}
				}
			}
		}

		/**
		 * Resolve a reusable product profile from an integration or template.
		 * Return a stored profile ID only; the shared header menu is never swapped.
		 *
		 * @param string $assigned Profile ID.
		 * @param int    $post_id  Current post ID.
		 * @param string $source   page, ancestor, or fallback. This runs even
		 *                         without a queried post so integrations can
		 *                         route archive and Elementor-template views.
		 */
		$assigned = sanitize_key( (string) apply_filters( 'abmm_product_profile_id', $assigned, $post_id, $source ) );
		if ( ! $assigned || ! isset( $profiles[ $assigned ] ) ) {
			return array( 'profile' => null, 'source' => 'fallback', 'post_id' => $post_id );
		}

		return array( 'profile' => $profiles[ $assigned ], 'source' => $source, 'post_id' => $post_id );
	}

	/**
	 * @return array{profile:array|null,source:string,post_id:int}
	 */
	public function resolve_for_request() {
		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}
		return $this->resolve_for_post( $post_id );
	}

	/**
	 * Register purpose-built editor screens instead of exposing duplicate menu
	 * records on every page.
	 */
	public function register_pages() {
		$this->page_hooks[] = add_submenu_page(
			'ashbi-mega-menu',
			__( 'Product Navigation', 'ashbi-mega-menu' ),
			__( 'Product Navigation', 'ashbi-mega-menu' ),
			'manage_options',
			'ashbi-mega-menu-products',
			array( $this, 'render_products_page' )
		);
		$this->page_hooks[] = add_submenu_page(
			'ashbi-mega-menu',
			__( 'Navigation Coverage', 'ashbi-mega-menu' ),
			__( 'Navigation Coverage', 'ashbi-mega-menu' ),
			'manage_options',
			'ashbi-mega-menu-coverage',
			array( $this, 'render_coverage_page' )
		);
	}

	/**
	 * Load the shared script for the product pages: the "More actions" menus,
	 * the logo picker and the live coverage filter.
	 */
	public static function enqueue_admin_script() {
		wp_enqueue_script(
			'abmm-product-admin',
			ABMM_PLUGIN_URL . 'assets/js/product-admin.js',
			array( 'wp-i18n' ),
			ABMM_VERSION,
			true
		);
		wp_set_script_translations( 'abmm-product-admin', 'ashbi-mega-menu', ABMM_PLUGIN_DIR . 'languages' );
	}

	/**
	 * @param string $hook_suffix Current admin hook.
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( in_array( $hook_suffix, $this->page_hooks, true ) ) {
			self::enqueue_admin_script();
		}
	}

	/**
	 * Render a plain-language profile editor suitable for non-technical users.
	 */
	public function render_products_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$profiles = $this->get_all();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only selection/filter; admin capability is checked and output is escaped.
		$edit_id  = isset( $_GET['profile'] ) ? sanitize_key( wp_unslash( $_GET['profile'] ) ) : '';
		$profile  = $profiles[ $edit_id ] ?? $this->sanitize_profile( array() );
		$revision = ABMM_Option_Store::revision( $profiles[ $edit_id ] ?? null );
		$draft    = ABMM_Option_Store::draft( 'ashbi-mega-menu-products' );
		if ( $draft ) {
			$edit_id  = $draft['id'];
			$profile  = $this->recovery_profile( $draft['record'] );
			$revision = ABMM_Option_Store::revision( $profiles[ $edit_id ] ?? null );
		}
		$pages       = get_pages( array( 'post_status' => 'publish', 'sort_column' => 'post_title' ) );
		$page_titles = array();
		foreach ( $pages as $page ) {
			$page_titles[ (int) $page->ID ] = $page->post_title;
		}
		$branding     = ABMM_Product_Branding::instance();
		$all_branding = $branding->get_all();
		$unbranded    = 0;
		foreach ( array_keys( $profiles ) as $profile_id ) {
			if ( empty( $all_branding[ $profile_id ]['enabled'] ) ) {
				++$unbranded;
			}
		}
		$products_url     = admin_url( 'admin.php?page=ashbi-mega-menu-products' );
		$abmm_page_header = array(
			'title'   => __( 'Product Navigation', 'ashbi-mega-menu' ),
			'intro'   => __( 'Show extra links under your main menu on a product\'s pages. Pages below the product\'s main page get the same links.', 'ashbi-mega-menu' ),
			'current' => 'products',
			'actions' => empty( $profiles ) ? array() : array(
				array(
					'label'   => __( 'Add product', 'ashbi-mega-menu' ),
					'url'     => $products_url,
					'primary' => true,
					'icon'    => 'plus-alt2',
				),
			),
		);
		?>
		<div class="wrap abmm-profile-admin">
			<?php require ABMM_PLUGIN_DIR . 'admin/views/brand-strip.php'; ?>
			<?php if ( $draft ) : ?><div class="notice notice-error"><p><?php echo esc_html( $draft['error'] ); ?> <?php esc_html_e( 'Your entered values are retained below. Review them against the latest saved profile before retrying.', 'ashbi-mega-menu' ); ?></p></div><?php endif; ?>
			<?php if ( isset( $_GET['updated'], $_GET['_abmm_notice'] ) && is_string( $_GET['_abmm_notice'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_abmm_notice'] ) ), 'abmm_profile_notice' ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Product navigation saved.', 'ashbi-mega-menu' ); ?></p></div>
			<?php endif; ?>
			<?php if ( $unbranded > 0 ) : ?>
				<aside class="abmm-callout">
					<span class="abmm-callout__icon dashicons dashicons-format-image" aria-hidden="true"></span>
					<div class="abmm-callout__text">
						<strong><?php esc_html_e( 'Want a product logo in the menu too?', 'ashbi-mega-menu' ); ?></strong>
						<span>
							<?php
							printf(
								/* translators: %d: number of products that still show the main logo. */
								esc_html( _n( '%d product still shows your main logo. Give it its own logo in Product Branding.', '%d products still show your main logo. Give them their own logo in Product Branding.', $unbranded, 'ashbi-mega-menu' ) ),
								(int) $unbranded
							);
							?>
						</span>
					</div>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=ashbi-mega-menu-product-branding' ) ); ?>"><?php esc_html_e( 'Set up branding', 'ashbi-mega-menu' ); ?></a>
				</aside>
			<?php endif; ?>
			<div class="abmm-profile-admin__grid">
				<section class="abmm-profile-admin__products" aria-labelledby="abmm-product-list-title">
					<div class="abmm-section-head">
						<h2 id="abmm-product-list-title"><?php esc_html_e( 'Products', 'ashbi-mega-menu' ); ?></h2>
						<span class="abmm-count"><?php echo esc_html( number_format_i18n( count( $profiles ) ) ); ?></span>
					</div>
					<?php if ( empty( $profiles ) ) : ?>
						<div class="abmm-product-empty">
							<span class="abmm-product-empty__icon dashicons dashicons-screenoptions" aria-hidden="true"></span>
							<strong><?php esc_html_e( 'No products yet', 'ashbi-mega-menu' ); ?></strong>
							<p><?php esc_html_e( 'Fill in the form to add your first product. It will show up here.', 'ashbi-mega-menu' ); ?></p>
						</div>
					<?php else : ?>
						<ul class="abmm-product-cards">
						<?php foreach ( $profiles as $profile_id => $item ) : ?>
							<?php
							$delete_url  = wp_nonce_url( admin_url( 'admin-post.php?action=abmm_delete_product_profile&profile=' . rawurlencode( $profile_id ) . '&revision=' . ABMM_Option_Store::revision( $item ) . '&branding_revision=' . ABMM_Option_Store::revision( $all_branding[ $profile_id ] ?? null ) ), 'abmm_delete_product_profile_' . $profile_id );
							$profile_url = admin_url( 'admin.php?page=ashbi-mega-menu-products&profile=' . rawurlencode( $profile_id ) );
							$is_selected = $edit_id === $profile_id;
							$item_title  = $item['title'] ?: $item['label'] ?: $profile_id;
							$link_count  = count( $item['links'] ?? array() );
							$root_title  = $page_titles[ (int) $item['root_page_id'] ] ?? '';
							$logo_url    = ! empty( $all_branding[ $profile_id ]['enabled'] ) ? $branding->logo_preview_url( $profile_id ) : '';
							$menu_id     = 'abmm-product-actions-' . sanitize_html_class( $profile_id );
							if ( 0 === $link_count ) {
								$status = array( 'key' => 'draft', 'label' => __( 'No links yet', 'ashbi-mega-menu' ), 'hint' => __( 'Add at least one link so the product has something to show.', 'ashbi-mega-menu' ) );
							} elseif ( ! $item['root_page_id'] ) {
								$status = array( 'key' => 'draft', 'label' => __( 'No main page', 'ashbi-mega-menu' ), 'hint' => __( 'Choose a main product page so the pages below it show these links.', 'ashbi-mega-menu' ) );
							} else {
								$status = array( 'key' => 'ready', 'label' => __( 'Active', 'ashbi-mega-menu' ), 'hint' => '' );
							}
							?>
							<li class="abmm-product-card<?php echo $is_selected ? ' is-selected' : ''; ?>">
								<span class="abmm-product-card__avatar<?php echo '' !== $logo_url ? ' has-logo' : ''; ?>" aria-hidden="true">
									<?php if ( '' !== $logo_url ) : ?>
										<img src="<?php echo esc_url( $logo_url ); ?>" alt="" />
									<?php else : ?>
										<?php echo esc_html( function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( $item_title, 0, 1 ) ) : strtoupper( substr( $item_title, 0, 1 ) ) ); ?>
									<?php endif; ?>
								</span>
								<div class="abmm-product-card__body">
									<div class="abmm-product-card__head">
										<a class="abmm-product-card__title" href="<?php echo esc_url( $profile_url ); ?>"<?php echo $is_selected ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $item_title ); ?></a>
										<span class="abmm-status abmm-status--<?php echo esc_attr( $status['key'] ); ?>"<?php echo '' !== $status['hint'] ? ' title="' . esc_attr( $status['hint'] ) . '"' : ''; ?>><?php echo esc_html( $status['label'] ); ?></span>
									</div>
									<p class="abmm-product-card__meta">
										<?php
										printf(
											/* translators: %d: Number of local navigation links. */
											esc_html( _n( '%d link', '%d links', $link_count, 'ashbi-mega-menu' ) ),
											(int) $link_count
										);
										?>
										<?php if ( '' !== $root_title ) : ?>
											<span aria-hidden="true">·</span> <span class="abmm-product-card__page"><?php echo esc_html( $root_title ); ?></span>
										<?php endif; ?>
									</p>
								</div>
								<div class="abmm-card-menu">
									<button type="button" class="button abmm-card-menu__toggle" aria-haspopup="menu" aria-expanded="false" aria-controls="<?php echo esc_attr( $menu_id ); ?>">
										<span class="abmm-card-menu__dots" aria-hidden="true">&#8943;</span>
										<span class="screen-reader-text">
											<?php
											/* translators: %s: product name. */
											echo esc_html( sprintf( __( 'More actions for %s', 'ashbi-mega-menu' ), $item_title ) );
											?>
										</span>
									</button>
									<div class="abmm-card-menu__list" id="<?php echo esc_attr( $menu_id ); ?>" role="menu" aria-label="<?php esc_attr_e( 'More actions', 'ashbi-mega-menu' ); ?>" hidden>
										<a role="menuitem" tabindex="-1" class="button abmm-card-menu__item abmm-card-menu__item--edit" href="<?php echo esc_url( $profile_url ); ?>"><?php esc_html_e( 'Edit', 'ashbi-mega-menu' ); ?></a>
										<a role="menuitem" tabindex="-1" class="button abmm-card-menu__item abmm-card-menu__item--branding" href="<?php echo esc_url( admin_url( 'admin.php?page=ashbi-mega-menu-product-branding&profile=' . rawurlencode( $profile_id ) ) ); ?>"><?php esc_html_e( 'Branding', 'ashbi-mega-menu' ); ?></a>
										<a role="menuitem" tabindex="-1" class="button abmm-card-menu__item abmm-card-menu__item--delete is-danger" href="<?php echo esc_url( $delete_url ); ?>" data-abmm-confirm="<?php echo esc_attr( sprintf( /* translators: %s: product name. */ __( 'Delete “%s”? Its pages will go back to showing only the main menu.', 'ashbi-mega-menu' ), $item_title ) ); ?>"><?php esc_html_e( 'Delete', 'ashbi-mega-menu' ); ?></a>
									</div>
								</div>
							</li>
						<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</section>
				<form class="abmm-profile-admin__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'abmm_save_product_profile' ); ?>
					<input type="hidden" name="action" value="abmm_save_product_profile" />
					<input type="hidden" name="profile_id" value="<?php echo esc_attr( $edit_id ); ?>" />
					<input type="hidden" name="revision" value="<?php echo esc_attr( $revision ); ?>" />
					<div class="abmm-profile-admin__form-head">
						<div>
							<p class="abmm-profile-admin__form-eyebrow"><?php echo esc_html( $edit_id ? __( 'Edit product', 'ashbi-mega-menu' ) : __( 'New product', 'ashbi-mega-menu' ) ); ?></p>
							<h2><?php echo esc_html( $edit_id ? ( $profile['title'] ?: $profile['label'] ?: $edit_id ) : __( 'Add a product', 'ashbi-mega-menu' ) ); ?></h2>
						</div>
						<?php if ( $edit_id && '' !== $profile['url'] ) : ?>
							<a class="abmm-profile-admin__view" href="<?php echo esc_url( $profile['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View page', 'ashbi-mega-menu' ); ?><span class="dashicons dashicons-external" aria-hidden="true"></span><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'ashbi-mega-menu' ); ?></span></a>
						<?php endif; ?>
					</div>
					<fieldset class="abmm-profile-admin__fieldset">
						<legend><?php esc_html_e( 'Product', 'ashbi-mega-menu' ); ?></legend>
						<p class="abmm-profile-admin__intro"><?php esc_html_e( 'The product\'s name and its main page.', 'ashbi-mega-menu' ); ?></p>
						<div class="abmm-profile-admin__field-grid">
							<label><?php esc_html_e( 'Name (only you see this)', 'ashbi-mega-menu' ); ?><input class="regular-text" type="text" required name="profile[title]" value="<?php echo esc_attr( $profile['title'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Analytics', 'ashbi-mega-menu' ); ?>" /></label>
							<label><?php esc_html_e( 'Display name', 'ashbi-mega-menu' ); ?><input class="regular-text" type="text" required name="profile[label]" value="<?php echo esc_attr( $profile['label'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Acme Analytics', 'ashbi-mega-menu' ); ?>" /></label>
							<label><?php esc_html_e( 'Product page URL', 'ashbi-mega-menu' ); ?><input class="regular-text" required type="url" name="profile[url]" value="<?php echo esc_attr( $profile['url'] ); ?>" placeholder="https://example.com/product/" /></label>
							<label><?php esc_html_e( 'Main product page', 'ashbi-mega-menu' ); ?><select name="profile[root_page_id]" aria-describedby="abmm-profile-root-help"><option value="0"><?php esc_html_e( 'Choose a page', 'ashbi-mega-menu' ); ?></option><?php foreach ( $pages as $page ) : ?><option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( $profile['root_page_id'], $page->ID ); ?>><?php echo esc_html( $page->post_title ); ?></option><?php endforeach; ?></select><span class="abmm-profile-admin__help" id="abmm-profile-root-help"><?php esc_html_e( 'Pages below the main product page show the same links.', 'ashbi-mega-menu' ); ?></span></label>
						</div>
					</fieldset>
					<?php
					/*
					 * All six rows are printed so the form works without JavaScript.
					 * product-admin.js hides the unused ones and reveals them one at
					 * a time with "Add link"; the field names never change.
					 */
					$abmm_link_row = static function ( $index, $link ) {
						$filled = '' !== (string) ( $link['label'] ?? '' ) || '' !== (string) ( $link['url'] ?? '' );
						?>
						<div class="abmm-profile-admin__link-row" data-abmm-link-row<?php echo $filled ? ' data-abmm-filled' : ''; ?>>
							<label><span class="screen-reader-text">
								<?php
								/* translators: %d: Link position. */
								printf( esc_html__( 'Link %d label', 'ashbi-mega-menu' ), esc_html( $index + 1 ) );
								?>
							</span><input class="regular-text" type="text" name="profile[links][<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( $link['label'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Link label', 'ashbi-mega-menu' ); ?>" /></label>
							<label><span class="screen-reader-text">
								<?php
								/* translators: %d: Link position. */
								printf( esc_html__( 'Link %d destination', 'ashbi-mega-menu' ), esc_html( $index + 1 ) );
								?>
							</span><input class="regular-text" type="url" name="profile[links][<?php echo esc_attr( $index ); ?>][url]" value="<?php echo esc_attr( $link['url'] ?? '' ); ?>" placeholder="https://…" /></label>
						</div>
						<?php
					};
					?>
					<fieldset class="abmm-profile-admin__fieldset">
						<legend><?php esc_html_e( 'Links', 'ashbi-mega-menu' ); ?></legend>
						<p class="abmm-profile-admin__intro"><?php esc_html_e( 'Shown under your main menu, only on this product\'s pages.', 'ashbi-mega-menu' ); ?></p>
						<div class="abmm-profile-admin__field-grid abmm-profile-admin__style-grid">
							<label class="abmm-profile-admin__style-field"><?php esc_html_e( 'Style', 'ashbi-mega-menu' ); ?><select name="profile[rail_style]"><option value="underline" <?php selected( $profile['rail_style'], 'underline' ); ?>><?php esc_html_e( 'Underlined links', 'ashbi-mega-menu' ); ?></option><option value="pills" <?php selected( $profile['rail_style'], 'pills' ); ?>><?php esc_html_e( 'Pill links', 'ashbi-mega-menu' ); ?></option></select></label>
						</div>
						<div class="abmm-profile-admin__links" data-abmm-links>
							<div class="abmm-profile-admin__link-head"><span><?php esc_html_e( 'Label', 'ashbi-mega-menu' ); ?></span><span><?php esc_html_e( 'Destination', 'ashbi-mega-menu' ); ?></span></div>
							<?php
							for ( $index = 0; $index < 6; $index++ ) {
								$abmm_link_row( $index, $profile['links'][ $index ] ?? array() );
							}
							?>
							<div class="abmm-profile-admin__links-empty" data-abmm-links-empty hidden>
								<span class="abmm-profile-admin__links-empty-icon dashicons dashicons-admin-links" aria-hidden="true"></span>
								<span><strong><?php esc_html_e( 'No links yet', 'ashbi-mega-menu' ); ?></strong><small><?php esc_html_e( 'Add up to six links, such as Overview, Features and Pricing.', 'ashbi-mega-menu' ); ?></small></span>
							</div>
						</div>
						<div class="abmm-profile-admin__links-actions" data-abmm-links-actions hidden>
							<button type="button" class="button abmm-profile-admin__add-link" data-abmm-add-link><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span><?php esc_html_e( 'Add link', 'ashbi-mega-menu' ); ?></button>
							<span class="abmm-profile-admin__links-note" data-abmm-links-note aria-live="polite"></span>
						</div>
					</fieldset>
					<fieldset class="abmm-profile-admin__fieldset abmm-profile-admin__fieldset--cta">
						<legend><?php esc_html_e( 'Button (optional)', 'ashbi-mega-menu' ); ?></legend>
						<p class="abmm-profile-admin__intro"><?php esc_html_e( 'Add a button for this product only, such as "Book a demo".', 'ashbi-mega-menu' ); ?></p>
						<label class="abmm-switch-row"><input class="abmm-switch" type="checkbox" name="profile[cta][show]" value="1" data-abmm-controls="abmm-profile-cta-fields" <?php checked( ! empty( $profile['cta']['show'] ) ); ?> /> <span><strong><?php esc_html_e( 'Show a button', 'ashbi-mega-menu' ); ?></strong><small><?php esc_html_e( 'It appears at the end of the product links.', 'ashbi-mega-menu' ); ?></small></span></label>
						<div class="abmm-profile-admin__field-grid abmm-dependent-fields" id="abmm-profile-cta-fields">
							<label><?php esc_html_e( 'Button text', 'ashbi-mega-menu' ); ?><input class="regular-text" type="text" name="profile[cta][label]" value="<?php echo esc_attr( $profile['cta']['label'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Book a demo', 'ashbi-mega-menu' ); ?>" /></label>
							<label><?php esc_html_e( 'Button link', 'ashbi-mega-menu' ); ?><input class="regular-text" type="url" name="profile[cta][url]" value="<?php echo esc_attr( $profile['cta']['url'] ); ?>" placeholder="https://…" /></label>
						</div>
					</fieldset>
					<div class="abmm-profile-admin__form-footer">
						<p class="abmm-profile-admin__footnote"><?php esc_html_e( 'Changes show on your site as soon as you save.', 'ashbi-mega-menu' ); ?></p>
						<?php submit_button( $edit_id ? __( 'Save product', 'ashbi-mega-menu' ) : __( 'Add product', 'ashbi-mega-menu' ), 'primary', 'submit', false ); ?>
					</div>
				</form>
			</div>
		</div>
		<?php
		self::print_dependent_fields_script();
	}

	/**
	 * Mute and disable fields that only apply while a checkbox is on.
	 *
	 * A checkbox with data-abmm-controls="<id>" controls the inputs inside the
	 * element with that ID. Fields are re-enabled just before the form submits,
	 * so their saved values are kept even while the option is off.
	 */
	public static function print_dependent_fields_script() {
		$script = <<<'JS'
(function () {
	var toggles = document.querySelectorAll('input[type="checkbox"][data-abmm-controls]');
	function sync(toggle) {
		var target = document.getElementById(toggle.getAttribute('data-abmm-controls'));
		if (!target) return;
		var off = !toggle.checked || toggle.disabled;
		target.classList.toggle('is-muted', off);
		target.setAttribute('aria-disabled', off ? 'true' : 'false');
		target.querySelectorAll('input, select, textarea, button').forEach(function (field) { field.disabled = off; });
		// A switch inside the group (such as the top bar) keeps its own fields off while it is off.
		target.querySelectorAll('input[type="checkbox"][data-abmm-controls]').forEach(sync);
	}
	toggles.forEach(function (toggle) {
		sync(toggle);
		toggle.addEventListener('change', function () { sync(toggle); });
		if (toggle.form) {
			toggle.form.addEventListener('submit', function () {
				var target = document.getElementById(toggle.getAttribute('data-abmm-controls'));
				if (target) target.querySelectorAll(':disabled').forEach(function (field) { field.disabled = false; });
			});
		}
	});
}());
JS;
		wp_print_inline_script_tag( $script );
	}

	/**
	 * Render a concise audit showing page, inherited, and global states.
	 *
	 * The filters work as a plain GET form without JavaScript. With JavaScript,
	 * product-admin.js filters the rows in place as you type or pick a filter;
	 * for that every row is printed, and the rows outside the server-side
	 * filter and page start out hidden.
	 */
	public function render_coverage_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only selection/filter; admin capability is checked and output is escaped.
		$source_filter = isset( $_GET['abmm_source'] ) && is_scalar( $_GET['abmm_source'] ) ? sanitize_key( wp_unslash( $_GET['abmm_source'] ) ) : 'all';
		if ( ! in_array( $source_filter, array( 'all', 'page', 'ancestor', 'fallback' ), true ) ) {
			$source_filter = 'all';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only selection/filter; admin capability is checked and output is escaped.
		$search = isset( $_GET['abmm_search'] ) && is_scalar( $_GET['abmm_search'] ) ? sanitize_text_field( wp_unslash( $_GET['abmm_search'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only selection/filter; admin capability is checked and output is escaped.
		$page_number = isset( $_GET['abmm_page'] ) && is_scalar( $_GET['abmm_page'] ) ? max( 1, absint( wp_unslash( $_GET['abmm_page'] ) ) ) : 1;
		$per_page    = 50;
		$post_types  = get_post_types( array( 'public' => true ), 'names' );
		$pages       = get_posts(
			array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$profile_count = count( $this->get_all() );
		$coverage      = array(
			'all'      => 0,
			'ancestor' => 0,
			'page'     => 0,
			'fallback' => 0,
		);
		$all_rows      = array();
		$matching      = 0;
		foreach ( $pages as $page ) {
			$resolved = $this->resolve_for_post( $page->ID );
			$source   = $resolved['source'] ?? 'fallback';
			if ( ! isset( $coverage[ $source ] ) || 'all' === $source ) {
				$source = 'fallback';
			}
			++$coverage[ $source ];
			++$coverage['all'];
			$matches    = ( 'all' === $source_filter || $source_filter === $source ) && ( '' === $search || false !== stripos( $page->post_title, $search ) );
			$all_rows[] = array(
				'page'     => $page,
				'resolved' => $resolved,
				'source'   => $source,
				'matches'  => $matches,
				'index'    => $matches ? $matching++ : -1,
			);
		}
		$filtered_count = $matching;
		$page_count     = max( 1, (int) ceil( $filtered_count / $per_page ) );
		$page_number    = min( $page_number, $page_count );
		$first_index    = ( $page_number - 1 ) * $per_page;
		$last_index     = $first_index + $per_page - 1;
		/*
		 * Print every row for live filtering on typical sites. Very large sites
		 * get the server-side page only, and the search waits for Enter.
		 */
		$live = count( $all_rows ) <= 1000;
		$coverage_url = admin_url( 'admin.php?page=ashbi-mega-menu-coverage&abmm_source=' . rawurlencode( $source_filter ) . '&abmm_search=' . rawurlencode( $search ) . '&abmm_page=' );
		$source_labels = array(
			'page'     => __( 'Set by hand', 'ashbi-mega-menu' ),
			'ancestor' => __( 'Inside a product', 'ashbi-mega-menu' ),
			'fallback' => __( 'Main menu only', 'ashbi-mega-menu' ),
		);
		$chips = array(
			'all'      => __( 'All pages', 'ashbi-mega-menu' ),
			'ancestor' => $source_labels['ancestor'],
			'page'     => $source_labels['page'],
			'fallback' => $source_labels['fallback'],
		);
		$abmm_page_header = array(
			'title'   => __( 'Navigation Coverage', 'ashbi-mega-menu' ),
			'intro'   => __( 'See which product links each page shows. Pages that are not part of a product show only your main menu.', 'ashbi-mega-menu' ),
			'current' => 'coverage',
		);
		?>
		<div class="wrap abmm-profile-coverage">
			<?php require ABMM_PLUGIN_DIR . 'admin/views/brand-strip.php'; ?>
			<?php if ( 0 === $profile_count ) : ?>
				<aside class="abmm-callout">
					<span class="abmm-callout__icon dashicons dashicons-screenoptions" aria-hidden="true"></span>
					<div class="abmm-callout__text">
						<strong><?php esc_html_e( 'No products yet', 'ashbi-mega-menu' ); ?></strong>
						<span><?php esc_html_e( 'Every page shows your main menu only. Add a product to give its pages their own links.', 'ashbi-mega-menu' ); ?></span>
					</div>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=ashbi-mega-menu-products' ) ); ?>"><?php esc_html_e( 'Add a product', 'ashbi-mega-menu' ); ?></a>
				</aside>
			<?php endif; ?>
			<form method="get" class="abmm-profile-coverage__filters" data-abmm-coverage-filters data-live="<?php echo $live ? '1' : '0'; ?>">
				<input type="hidden" name="page" value="ashbi-mega-menu-coverage" />
				<input type="hidden" name="abmm_source" value="<?php echo esc_attr( $source_filter ); ?>" data-abmm-source-input />
				<div class="abmm-coverage-chips" role="group" aria-label="<?php esc_attr_e( 'Show pages that', 'ashbi-mega-menu' ); ?>">
					<?php foreach ( $chips as $chip_key => $chip_label ) : ?>
						<a class="abmm-coverage-chip abmm-coverage-chip--<?php echo esc_attr( $chip_key ); ?><?php echo $chip_key === $source_filter ? ' is-current' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=ashbi-mega-menu-coverage&abmm_source=' . rawurlencode( $chip_key ) . ( '' !== $search ? '&abmm_search=' . rawurlencode( $search ) : '' ) ) ); ?>" data-abmm-source-chip="<?php echo esc_attr( $chip_key ); ?>"<?php echo $chip_key === $source_filter ? ' aria-current="true"' : ''; ?>>
							<?php echo esc_html( $chip_label ); ?>
							<span class="abmm-coverage-chip__count"><?php echo esc_html( number_format_i18n( $coverage[ $chip_key ] ) ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>
				<div class="abmm-coverage-search">
					<label class="screen-reader-text" for="abmm-coverage-search"><?php esc_html_e( 'Search pages', 'ashbi-mega-menu' ); ?></label>
					<span class="dashicons dashicons-search" aria-hidden="true"></span>
					<input type="search" id="abmm-coverage-search" name="abmm_search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search pages…', 'ashbi-mega-menu' ); ?>" autocomplete="off" data-abmm-coverage-search />
					<?php if ( $live ) : ?>
						<noscript><button type="submit" class="button"><?php esc_html_e( 'Search', 'ashbi-mega-menu' ); ?></button></noscript>
					<?php else : ?>
						<button type="submit" class="button"><?php esc_html_e( 'Search', 'ashbi-mega-menu' ); ?></button>
					<?php endif; ?>
				</div>
			</form>
			<p class="abmm-profile-coverage__count" aria-live="polite" data-abmm-coverage-count>
				<?php
				if ( $page_count > 1 ) {
					echo esc_html(
						sprintf(
							/* translators: 1: Matching page count, 2: Current page, 3: Total pages. */
							__( '%1$d matching pages; page %2$d of %3$d', 'ashbi-mega-menu' ),
							$filtered_count,
							$page_number,
							$page_count
						)
					);
				} else {
					echo esc_html(
						sprintf(
							/* translators: %d: Matching page count. */
							_n( '%d matching page', '%d matching pages', $filtered_count, 'ashbi-mega-menu' ),
							$filtered_count
						)
					);
				}
				?>
			</p>
			<div class="abmm-coverage-table">
				<table class="widefat">
					<caption class="screen-reader-text"><?php esc_html_e( 'Navigation coverage results', 'ashbi-mega-menu' ); ?></caption>
					<thead><tr><th scope="col"><?php esc_html_e( 'Page', 'ashbi-mega-menu' ); ?></th><th scope="col"><?php esc_html_e( 'Product links', 'ashbi-mega-menu' ); ?></th><th scope="col"><?php esc_html_e( 'Why', 'ashbi-mega-menu' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $all_rows as $row ) : ?>
						<?php
						$in_page = $row['matches'] && $row['index'] >= $first_index && $row['index'] <= $last_index;
						if ( ! $live && ! $in_page ) {
							continue;
						}
						$page        = $row['page'];
						$row_title   = $page->post_title ?: __( '(no title)', 'ashbi-mega-menu' );
						$row_product = $row['resolved']['profile']['label'] ?? '';
						?>
						<tr data-source="<?php echo esc_attr( $row['source'] ); ?>" data-title="<?php echo esc_attr( function_exists( 'mb_strtolower' ) ? mb_strtolower( $row_title ) : strtolower( $row_title ) ); ?>"<?php echo $in_page ? '' : ' hidden'; ?>>
							<td class="abmm-coverage-table__page"><a href="<?php echo esc_url( get_edit_post_link( $page->ID ) ); ?>"><?php echo esc_html( $row_title ); ?></a></td>
							<td data-label="<?php esc_attr_e( 'Product links', 'ashbi-mega-menu' ); ?>">
								<?php if ( '' !== $row_product ) : ?>
									<span class="abmm-coverage-product"><?php echo esc_html( $row_product ); ?></span>
								<?php else : ?>
									<span class="abmm-coverage-none"><?php esc_html_e( 'None', 'ashbi-mega-menu' ); ?></span>
								<?php endif; ?>
							</td>
							<td data-label="<?php esc_attr_e( 'Why', 'ashbi-mega-menu' ); ?>"><span class="abmm-source abmm-source--<?php echo esc_attr( $row['source'] ); ?>"><?php echo esc_html( $source_labels[ $row['source'] ] ); ?></span></td>
						</tr>
					<?php endforeach; ?>
					<tr class="abmm-coverage-table__empty" data-abmm-coverage-empty<?php echo $filtered_count > 0 ? ' hidden' : ''; ?>>
						<td colspan="3">
							<strong><?php esc_html_e( 'No pages match these filters.', 'ashbi-mega-menu' ); ?></strong>
							<span><?php esc_html_e( 'Try another search or show all pages.', 'ashbi-mega-menu' ); ?></span>
						</td>
					</tr>
					</tbody>
				</table>
			</div>
			<?php if ( $page_count > 1 ) : ?>
				<nav class="abmm-profile-coverage__pages" aria-label="<?php esc_attr_e( 'Coverage pages', 'ashbi-mega-menu' ); ?>" data-abmm-coverage-pages>
					<?php if ( $page_number > 1 ) : ?><a class="button" href="<?php echo esc_url( $coverage_url . ( $page_number - 1 ) ); ?>"><?php esc_html_e( 'Previous', 'ashbi-mega-menu' ); ?></a><?php endif; ?>
					<span>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: Current page, 2: Total pages. */
								__( 'Page %1$d of %2$d', 'ashbi-mega-menu' ),
								$page_number,
								$page_count
							)
						);
						?>
					</span>
					<?php if ( $page_number < $page_count ) : ?><a class="button" href="<?php echo esc_url( $coverage_url . ( $page_number + 1 ) ); ?>"><?php esc_html_e( 'Next', 'ashbi-mega-menu' ); ?></a><?php endif; ?>
				</nav>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @return void
	 */
	public function save_profile() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage product navigation.', 'ashbi-mega-menu' ) );
		}
		check_admin_referer( 'abmm_save_product_profile' );
		$raw      = isset( $_POST['profile'] ) ? wp_unslash( $_POST['profile'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Structured input is validated and sanitized field by field before persistence; upload paths are verified with is_uploaded_file().
		$profile  = $this->sanitize_profile( $raw );
		$id = isset( $_POST['profile_id'] ) && is_scalar( $_POST['profile_id'] ) ? sanitize_key( wp_unslash( $_POST['profile_id'] ) ) : '';
		$revision = isset( $_POST['revision'] ) && is_string( $_POST['revision'] ) ? sanitize_text_field( wp_unslash( $_POST['revision'] ) ) : '';
		$result = $this->store_profile( $id, $profile, $revision );
		if ( is_wp_error( $result ) ) {
			ABMM_Option_Store::recover( 'ashbi-mega-menu-products', $id, $this->recovery_profile( $raw ), $result->get_error_message() );
		}
		wp_safe_redirect( add_query_arg( '_abmm_notice', wp_create_nonce( 'abmm_profile_notice' ), admin_url( 'admin.php?page=ashbi-mega-menu-products&profile=' . rawurlencode( $result ) . '&updated=1' ) ) );
		exit;
	}

	/**
	 * @return void
	 */
	public function delete_profile() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage product navigation.', 'ashbi-mega-menu' ) );
		}
		$profile_id = isset( $_REQUEST['profile'] ) && is_scalar( $_REQUEST['profile'] ) ? sanitize_key( wp_unslash( $_REQUEST['profile'] ) ) : '';
		check_admin_referer( 'abmm_delete_product_profile_' . $profile_id );
		$revision = isset( $_REQUEST['revision'] ) && is_string( $_REQUEST['revision'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['revision'] ) ) : '';
		$branding_revision = isset( $_REQUEST['branding_revision'] ) && is_string( $_REQUEST['branding_revision'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['branding_revision'] ) ) : '';
		$result = $this->remove_profile( $profile_id, $revision, $branding_revision );
		if ( is_wp_error( $result ) ) {
			ABMM_Option_Store::recover( 'ashbi-mega-menu-products', $profile_id, $this->get( $profile_id ) ?? array(), $result->get_error_message() );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=ashbi-mega-menu-products' ) );
		exit;
	}

	/** Preserve incomplete link rows in an escaped recovery form, without publishing them. */
	private function recovery_profile( $raw ) {
		$profile = $this->sanitize_profile( $raw );
		$profile['links'] = array();
		foreach ( array_slice( is_array( $raw['links'] ?? null ) ? $raw['links'] : array(), 0, 6 ) as $link ) {
			if ( ! is_array( $link ) ) { continue; }
			$profile['links'][] = array( 'label' => $this->sanitize_text( $link['label'] ?? '' ), 'url' => $this->sanitize_url( $link['url'] ?? '' ) );
		}
		return $profile;
	}

	/** Save one record under the common collection lock, preserving other products. */
	public function store_profile( $id, $profile, $revision ) {
		return ABMM_Option_Store::locked( function () use ( $id, $profile, $revision ) {
			$profiles = $this->get_all();
			$is_new = '' === $id;
			if ( ! hash_equals( ABMM_Option_Store::revision( $is_new ? null : ( $profiles[ $id ] ?? null ) ), $revision ) || ( ! $is_new && ! isset( $profiles[ $id ] ) ) ) {
				return new WP_Error( 'abmm_product_conflict', __( 'This profile was changed or deleted by another administrator. Review the latest values before saving again.', 'ashbi-mega-menu' ) );
			}
			$profile = $this->sanitize_profile( $profile );
			// Use the same key normalization as reads, requests and deletes before collision checks.
			if ( $is_new ) { $id = sanitize_key( sanitize_title( $profile['title'] ?: $profile['label'] ) ); }
			if ( ! $id || '' === $profile['label'] || '' === $profile['url'] ) {
				return new WP_Error( 'abmm_product_invalid', __( 'Add a product name and product home URL before saving.', 'ashbi-mega-menu' ) );
			}
			if ( $is_new && isset( $profiles[ $id ] ) ) {
				return new WP_Error( 'abmm_product_duplicate', __( 'A product with this name already exists. Open that profile to edit it.', 'ashbi-mega-menu' ) );
			}
			$profiles[ $id ] = $profile;
			$result = ABMM_Option_Store::persist( self::OPTION_KEY, $profiles );
			return is_wp_error( $result ) ? $result : $id;
		} );
	}

	/** Delete the profile and its branding together; stale links never delete fresh edits. */
	public function remove_profile( $id, $revision, $branding_revision ) {
		return ABMM_Option_Store::locked( function () use ( $id, $revision, $branding_revision ) {
			$profiles = $this->get_all();
			$branding = ABMM_Product_Branding::instance()->get_all();
			if ( ! isset( $profiles[ $id ] ) || ! hash_equals( ABMM_Option_Store::revision( $profiles[ $id ] ), $revision ) || ! hash_equals( ABMM_Option_Store::revision( $branding[ $id ] ?? null ), $branding_revision ) ) {
				return new WP_Error( 'abmm_product_conflict', __( 'This product changed since the delete link was opened. Reload and review it before deleting.', 'ashbi-mega-menu' ) );
			}
			$previous = $profiles;
			unset( $profiles[ $id ], $branding[ $id ] );
			$result = ABMM_Option_Store::persist( self::OPTION_KEY, $profiles );
			if ( is_wp_error( $result ) ) { return $result; }
			$result = ABMM_Option_Store::persist( ABMM_Product_Branding::OPTION_KEY, $branding );
			if ( is_wp_error( $result ) ) {
				$rollback = ABMM_Option_Store::persist( self::OPTION_KEY, $previous );
				return is_wp_error( $rollback ) ? new WP_Error( 'abmm_product_delete_incomplete', __( 'Deletion was interrupted and recovery failed. Check the product profile and branding before retrying.', 'ashbi-mega-menu' ) ) : $result;
			}
			return true;
		} );
	}

	/**
	 * @param string $url URL.
	 * @return string
	 */
	private function sanitize_url( $url ) {
		if ( ! is_scalar( $url ) ) {
			return '';
		}

		$url = trim( (string) $url );
		if ( 0 === strpos( $url, '//' ) ) {
			return '';
		}
		if ( '' === $url || '/' === substr( $url, 0, 1 ) ) {
			return $url;
		}
		return esc_url_raw( $url );
	}

	/**
	 * Sanitize scalar profile text without passing structured input to WordPress.
	 *
	 * @param mixed $value Raw text value.
	 * @return string
	 */
	private function sanitize_text( $value ) {
		return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	}

	/**
	 * Convert profile metadata to a scalar string before key validation.
	 *
	 * @param mixed $value Raw metadata value.
	 * @return string
	 */
	private function scalar_string( $value ) {
		return is_scalar( $value ) ? (string) $value : '';
	}
}
