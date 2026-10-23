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
		add_submenu_page(
			'ashbi-mega-menu',
			__( 'Product Navigation', 'ashbi-mega-menu' ),
			__( 'Product Navigation', 'ashbi-mega-menu' ),
			'manage_options',
			'ashbi-mega-menu-products',
			array( $this, 'render_products_page' )
		);
		add_submenu_page(
			'ashbi-mega-menu',
			__( 'Navigation Coverage', 'ashbi-mega-menu' ),
			__( 'Navigation Coverage', 'ashbi-mega-menu' ),
			'manage_options',
			'ashbi-mega-menu-coverage',
			array( $this, 'render_coverage_page' )
		);
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
		$draft = ABMM_Option_Store::draft( 'ashbi-mega-menu-products' );
		if ( $draft ) {
			$edit_id = $draft['id'];
			$profile = $this->recovery_profile( $draft['record'] );
			$revision = ABMM_Option_Store::revision( $profiles[ $edit_id ] ?? null );
		}
		$pages    = get_pages( array( 'post_status' => 'publish', 'sort_column' => 'post_title' ) );
		?>
		<div class="wrap abmm-profile-admin">
			<?php require ABMM_PLUGIN_DIR . 'admin/views/brand-strip.php'; ?>
			<div class="abmm-profile-admin__hero">
				<div>
					<h1><?php esc_html_e( 'Product Navigation', 'ashbi-mega-menu' ); ?></h1>
					<p><?php esc_html_e( 'Show extra links under your main menu on a product\'s pages. Pages below the product\'s main page get the same links.', 'ashbi-mega-menu' ); ?></p>
				</div>
				<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=ashbi-mega-menu-coverage' ) ); ?>"><?php esc_html_e( 'See which pages use it', 'ashbi-mega-menu' ); ?></a>
			</div>
			<?php if ( $draft ) : ?><div class="notice notice-error"><p><?php echo esc_html( $draft['error'] ); ?> <?php esc_html_e( 'Your entered values are retained below. Review them against the latest saved profile before retrying.', 'ashbi-mega-menu' ); ?></p></div><?php endif; ?>
			<?php if ( isset( $_GET['updated'], $_GET['_abmm_notice'] ) && is_string( $_GET['_abmm_notice'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_abmm_notice'] ) ), 'abmm_profile_notice' ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Product navigation saved.', 'ashbi-mega-menu' ); ?></p></div>
			<?php endif; ?>
			<div class="abmm-profile-admin__grid">
				<section class="abmm-profile-admin__list" aria-labelledby="abmm-product-list-title">
					<div class="abmm-profile-admin__section-head"><div><h2 id="abmm-product-list-title"><?php esc_html_e( 'Products', 'ashbi-mega-menu' ); ?></h2></div><a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=ashbi-mega-menu-products' ) ); ?>"><?php esc_html_e( 'Add product', 'ashbi-mega-menu' ); ?></a></div>
					<?php if ( empty( $profiles ) ) : ?>
						<div class="abmm-profile-admin__empty"><strong><?php esc_html_e( 'No products yet', 'ashbi-mega-menu' ); ?></strong><p><?php esc_html_e( 'Add your first product with the form.', 'ashbi-mega-menu' ); ?></p></div>
					<?php else : ?>
						<ul class="abmm-profile-admin__cards">
						<?php foreach ( $profiles as $profile_id => $item ) : ?>
							<?php $delete_url = wp_nonce_url( admin_url( 'admin-post.php?action=abmm_delete_product_profile&profile=' . rawurlencode( $profile_id ) . '&revision=' . ABMM_Option_Store::revision( $item ) . '&branding_revision=' . ABMM_Option_Store::revision( ABMM_Product_Branding::instance()->get_all()[ $profile_id ] ?? null ) ), 'abmm_delete_product_profile_' . $profile_id ); $profile_url = admin_url( 'admin.php?page=ashbi-mega-menu-products&profile=' . rawurlencode( $profile_id ) ); $is_selected = $edit_id === $profile_id; ?>
							<li class="<?php echo $is_selected ? 'is-selected' : ''; ?>"><a class="abmm-profile-admin__card" href="<?php echo esc_url( $profile_url ); ?>"<?php echo $is_selected ? ' aria-current="page"' : ''; ?>><strong><?php echo esc_html( $item['title'] ?: $item['label'] ?: $profile_id ); ?></strong><span><?php printf( esc_html(
/* translators: %d: Number of local navigation links. */
_n( '%d local link', '%d local links', count( $item['links'] ?? array() ), 'ashbi-mega-menu' ) ), esc_html( count( $item['links'] ?? array() ) ) ); ?></span></a><a class="abmm-profile-admin__delete" href="<?php echo esc_url( $delete_url ); ?>" onclick="return window.confirm('<?php echo esc_js( __( 'Delete this product? Its pages will go back to showing only the main menu.', 'ashbi-mega-menu' ) ); ?>');"><?php esc_html_e( 'Delete', 'ashbi-mega-menu' ); ?></a></li>
						<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</section>
				<form class="abmm-profile-admin__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'abmm_save_product_profile' ); ?>
					<input type="hidden" name="action" value="abmm_save_product_profile" />
					<input type="hidden" name="profile_id" value="<?php echo esc_attr( $edit_id ); ?>" />
					<input type="hidden" name="revision" value="<?php echo esc_attr( $revision ); ?>" />
					<div class="abmm-profile-admin__form-head"><div><h2><?php echo esc_html( $edit_id ? __( 'Edit product', 'ashbi-mega-menu' ) : __( 'Add product', 'ashbi-mega-menu' ) ); ?></h2></div></div>
					<fieldset class="abmm-profile-admin__fieldset">
						<legend><?php esc_html_e( 'Product', 'ashbi-mega-menu' ); ?></legend>
						<p class="abmm-profile-admin__intro"><?php esc_html_e( 'The product\'s name and its main page.', 'ashbi-mega-menu' ); ?></p>
						<div class="abmm-profile-admin__field-grid">
							<label><?php esc_html_e( 'Name (only you see this)', 'ashbi-mega-menu' ); ?><input class="regular-text" type="text" required name="profile[title]" value="<?php echo esc_attr( $profile['title'] ); ?>" placeholder="Example Product" /></label>
							<label><?php esc_html_e( 'Display name', 'ashbi-mega-menu' ); ?><input class="regular-text" type="text" required name="profile[label]" value="<?php echo esc_attr( $profile['label'] ); ?>" placeholder="Example Product" /></label>
							<label><?php esc_html_e( 'Product page URL', 'ashbi-mega-menu' ); ?><input class="regular-text" required type="url" name="profile[url]" value="<?php echo esc_attr( $profile['url'] ); ?>" placeholder="https://example.com/product/" /></label>
							<label><?php esc_html_e( 'Main product page', 'ashbi-mega-menu' ); ?><select name="profile[root_page_id]" aria-describedby="abmm-profile-root-help"><option value="0"><?php esc_html_e( 'Choose a page', 'ashbi-mega-menu' ); ?></option><?php foreach ( $pages as $page ) : ?><option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( $profile['root_page_id'], $page->ID ); ?>><?php echo esc_html( $page->post_title ); ?></option><?php endforeach; ?></select><span class="abmm-profile-admin__help" id="abmm-profile-root-help"><?php esc_html_e( 'Pages below the main product page show the same links.', 'ashbi-mega-menu' ); ?></span></label>
						</div>
					</fieldset>
					<?php
					$abmm_extra_open = false;
					for ( $abmm_extra = 2; $abmm_extra < 6; $abmm_extra++ ) {
						$abmm_extra_open = $abmm_extra_open || ! empty( $profile['links'][ $abmm_extra ]['label'] ) || ! empty( $profile['links'][ $abmm_extra ]['url'] );
					}
					$abmm_link_row = static function ( $index, $link ) {
						?>
						<div class="abmm-profile-admin__link-row">
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
						<label class="abmm-profile-admin__style-field"><?php esc_html_e( 'Style', 'ashbi-mega-menu' ); ?><select name="profile[rail_style]"><option value="underline" <?php selected( $profile['rail_style'], 'underline' ); ?>><?php esc_html_e( 'Underlined links', 'ashbi-mega-menu' ); ?></option><option value="pills" <?php selected( $profile['rail_style'], 'pills' ); ?>><?php esc_html_e( 'Pill links', 'ashbi-mega-menu' ); ?></option></select></label>
						<div class="abmm-profile-admin__links">
							<div class="abmm-profile-admin__link-head"><span><?php esc_html_e( 'Label', 'ashbi-mega-menu' ); ?></span><span><?php esc_html_e( 'Destination', 'ashbi-mega-menu' ); ?></span></div>
							<?php
							for ( $index = 0; $index < 2; $index++ ) {
								$abmm_link_row( $index, $profile['links'][ $index ] ?? array() );
							}
							?>
						</div>
						<details class="abmm-profile-admin__more-links"<?php echo $abmm_extra_open ? ' open' : ''; ?>>
							<summary><?php esc_html_e( 'Add more links', 'ashbi-mega-menu' ); ?> <span class="abmm-profile-admin__summary-note"><?php esc_html_e( 'Up to 4 more', 'ashbi-mega-menu' ); ?></span></summary>
							<div class="abmm-profile-admin__links">
								<?php
								for ( $index = 2; $index < 6; $index++ ) {
									$abmm_link_row( $index, $profile['links'][ $index ] ?? array() );
								}
								?>
							</div>
						</details>
					</fieldset>
					<fieldset class="abmm-profile-admin__fieldset abmm-profile-admin__fieldset--cta">
						<legend><?php esc_html_e( 'Button (optional)', 'ashbi-mega-menu' ); ?></legend>
						<p class="abmm-profile-admin__intro"><?php esc_html_e( 'Add a button for this product only, such as "Book a demo".', 'ashbi-mega-menu' ); ?></p>
						<label class="abmm-profile-admin__checkbox"><input type="checkbox" name="profile[cta][show]" value="1" data-abmm-controls="abmm-profile-cta-fields" <?php checked( ! empty( $profile['cta']['show'] ) ); ?> /> <?php esc_html_e( 'Show a button', 'ashbi-mega-menu' ); ?></label>
						<div class="abmm-profile-admin__field-grid abmm-dependent-fields" id="abmm-profile-cta-fields">
							<label><?php esc_html_e( 'Button text', 'ashbi-mega-menu' ); ?><input class="regular-text" type="text" name="profile[cta][label]" value="<?php echo esc_attr( $profile['cta']['label'] ); ?>" placeholder="Get a demo" /></label>
							<label><?php esc_html_e( 'Button link', 'ashbi-mega-menu' ); ?><input class="regular-text" type="url" name="profile[cta][url]" value="<?php echo esc_attr( $profile['cta']['url'] ); ?>" placeholder="https://…" /></label>
						</div>
					</fieldset>
					<div class="abmm-profile-admin__form-footer"><span></span><?php submit_button( $edit_id ? __( 'Save product', 'ashbi-mega-menu' ) : __( 'Add product', 'ashbi-mega-menu' ), 'primary', 'submit', false ); ?></div>
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
		var off = !toggle.checked;
		target.classList.toggle('is-muted', off);
		target.querySelectorAll('input, select, textarea, button').forEach(function (field) { field.disabled = off; });
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
		$per_page = 50;
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		$pages = get_posts(
			array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$coverage_rows = array();
		$coverage      = array(
			'profiles'  => count( $this->get_all() ),
			'page'      => 0,
			'ancestor'  => 0,
			'fallback'  => 0,
		);
		foreach ( $pages as $page ) {
			$resolved = $this->resolve_for_post( $page->ID );
			$source   = $resolved['source'] ?? 'fallback';
			if ( isset( $coverage[ $source ] ) ) {
				++$coverage[ $source ];
			} else {
				++$coverage['fallback'];
			}
			if ( ( 'all' === $source_filter || $source_filter === $source ) && ( '' === $search || false !== stripos( $page->post_title, $search ) ) ) {
				$coverage_rows[] = array(
					'page'     => $page,
					'resolved' => $resolved,
				);
			}
		}
		$filtered_count = count( $coverage_rows );
		$page_count = max( 1, (int) ceil( $filtered_count / $per_page ) );
		$page_number = min( $page_number, $page_count );
		$coverage_rows = array_slice( $coverage_rows, ( $page_number - 1 ) * $per_page, $per_page );
		$coverage_url = admin_url( 'admin.php?page=ashbi-mega-menu-coverage&abmm_source=' . rawurlencode( $source_filter ) . '&abmm_search=' . rawurlencode( $search ) . '&abmm_page=' );
		?>
		<div class="wrap abmm-profile-coverage">
			<?php require ABMM_PLUGIN_DIR . 'admin/views/brand-strip.php'; ?>
			<h1><?php esc_html_e( 'Navigation Coverage', 'ashbi-mega-menu' ); ?></h1>
			<p><?php esc_html_e( 'See which product links each page shows. Pages that are not part of a product show only your main menu.', 'ashbi-mega-menu' ); ?></p>
			<?php if ( 0 === $coverage['profiles'] ) : ?>
				<div class="notice notice-info inline"><p><strong><?php esc_html_e( 'No products yet.', 'ashbi-mega-menu' ); ?></strong> <?php esc_html_e( 'Every page shows your main menu only.', 'ashbi-mega-menu' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=ashbi-mega-menu-products' ) ); ?>"><?php esc_html_e( 'Add a product', 'ashbi-mega-menu' ); ?></a></p></div>
			<?php endif; ?>
			<div class="abmm-profile-coverage__summary" aria-label="<?php esc_attr_e( 'Navigation coverage summary', 'ashbi-mega-menu' ); ?>">
				<span><strong><?php echo esc_html( $coverage['profiles'] ); ?></strong> <?php esc_html_e( 'products', 'ashbi-mega-menu' ); ?></span>
				<span><strong><?php echo esc_html( $coverage['ancestor'] ); ?></strong> <?php esc_html_e( 'pages inside a product', 'ashbi-mega-menu' ); ?></span>
				<span><strong><?php echo esc_html( $coverage['page'] ); ?></strong> <?php esc_html_e( 'pages set by hand', 'ashbi-mega-menu' ); ?></span>
				<span><strong><?php echo esc_html( $coverage['fallback'] ); ?></strong> <?php esc_html_e( 'main menu only', 'ashbi-mega-menu' ); ?></span>
			</div>
			<form method="get" class="abmm-profile-coverage__filters">
				<input type="hidden" name="page" value="ashbi-mega-menu-coverage" />
				<label for="abmm-coverage-search"><?php esc_html_e( 'Search pages', 'ashbi-mega-menu' ); ?></label>
				<input type="search" id="abmm-coverage-search" name="abmm_search" value="<?php echo esc_attr( $search ); ?>" />
				<label for="abmm-coverage-source"><?php esc_html_e( 'Shows', 'ashbi-mega-menu' ); ?></label>
				<select id="abmm-coverage-source" name="abmm_source">
					<option value="all" <?php selected( $source_filter, 'all' ); ?>><?php esc_html_e( 'All pages', 'ashbi-mega-menu' ); ?></option>
					<option value="page" <?php selected( $source_filter, 'page' ); ?>><?php esc_html_e( 'Set by hand', 'ashbi-mega-menu' ); ?></option>
					<option value="ancestor" <?php selected( $source_filter, 'ancestor' ); ?>><?php esc_html_e( 'Inside a product', 'ashbi-mega-menu' ); ?></option>
					<option value="fallback" <?php selected( $source_filter, 'fallback' ); ?>><?php esc_html_e( 'Main menu only', 'ashbi-mega-menu' ); ?></option>
				</select>
				<button type="submit" class="button"><?php esc_html_e( 'Filter', 'ashbi-mega-menu' ); ?></button>
			</form>
			<p class="abmm-profile-coverage__count"><?php echo esc_html( sprintf(
/* translators: 1: Matching page count, 2: Current page, 3: Total pages. */
__( '%1$d matching pages; page %2$d of %3$d', 'ashbi-mega-menu' ), $filtered_count, $page_number, $page_count ) ); ?></p>
			<table class="widefat striped"><caption class="screen-reader-text"><?php esc_html_e( 'Navigation coverage results', 'ashbi-mega-menu' ); ?></caption><thead><tr><th scope="col"><?php esc_html_e( 'Page', 'ashbi-mega-menu' ); ?></th><th scope="col"><?php esc_html_e( 'Product links', 'ashbi-mega-menu' ); ?></th><th scope="col"><?php esc_html_e( 'Why', 'ashbi-mega-menu' ); ?></th></tr></thead><tbody>
			<?php
			$source_labels = array(
				'page'     => __( 'Set by hand', 'ashbi-mega-menu' ),
				'ancestor' => __( 'Inside a product', 'ashbi-mega-menu' ),
				'fallback' => __( 'Main menu only', 'ashbi-mega-menu' ),
			);
			?>
			<?php foreach ( $coverage_rows as $row ) : $page = $row['page']; $resolved = $row['resolved']; ?>
				<tr><td><a href="<?php echo esc_url( get_edit_post_link( $page->ID ) ); ?>"><?php echo esc_html( $page->post_title ?: __( '(no title)', 'ashbi-mega-menu' ) ); ?></a></td><td><?php echo esc_html( $resolved['profile']['label'] ?? '—' ); ?></td><td><?php echo esc_html( $source_labels[ $resolved['source'] ] ?? $source_labels['fallback'] ); ?></td></tr>
			<?php endforeach; ?>
			<?php if ( 0 === $filtered_count ) : ?><tr><td colspan="3"><?php esc_html_e( 'No pages match these filters.', 'ashbi-mega-menu' ); ?></td></tr><?php endif; ?>
			</tbody></table>
			<?php if ( $page_count > 1 ) : ?>
				<nav class="abmm-profile-coverage__pages" aria-label="<?php esc_attr_e( 'Coverage pages', 'ashbi-mega-menu' ); ?>">
					<?php if ( $page_number > 1 ) : ?><a class="button" href="<?php echo esc_url( $coverage_url . ( $page_number - 1 ) ); ?>"><?php esc_html_e( 'Previous', 'ashbi-mega-menu' ); ?></a><?php endif; ?>
					<span><?php echo esc_html( sprintf(
/* translators: 1: Current page, 2: Total pages. */
__( 'Page %1$d of %2$d', 'ashbi-mega-menu' ), $page_number, $page_count ) ); ?></span>
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
