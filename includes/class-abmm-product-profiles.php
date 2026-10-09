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
		$edit_id  = isset( $_GET['profile'] ) ? sanitize_key( wp_unslash( $_GET['profile'] ) ) : '';
		$profile  = $profiles[ $edit_id ] ?? $this->sanitize_profile( array() );
		$pages    = get_pages( array( 'post_status' => 'publish', 'sort_column' => 'post_title' ) );
		?>
		<div class="wrap abmm-profile-admin">
			<div class="abmm-profile-admin__hero">
				<div>
					<p class="abmm-profile-admin__eyebrow"><?php esc_html_e( 'Shared-header system', 'ashbi-mega-menu' ); ?></p>
					<h1><?php esc_html_e( 'Product Navigation', 'ashbi-mega-menu' ); ?></h1>
					<p><?php esc_html_e( 'Keep the site header consistent, then give each product a focused local path. Create the local links once and child pages inherit them from the product hub.', 'ashbi-mega-menu' ); ?></p>
				</div>
				<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=ashbi-mega-menu-coverage' ) ); ?>"><?php esc_html_e( 'Review coverage', 'ashbi-mega-menu' ); ?></a>
			</div>
			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Product navigation saved.', 'ashbi-mega-menu' ); ?></p></div>
			<?php endif; ?>
			<ol class="abmm-profile-admin__steps" aria-label="<?php esc_attr_e( 'Product navigation workflow', 'ashbi-mega-menu' ); ?>">
				<li><strong>1</strong><span><?php esc_html_e( 'Create a product profile', 'ashbi-mega-menu' ); ?></span></li>
				<li><strong>2</strong><span><?php esc_html_e( 'Assign its product hub', 'ashbi-mega-menu' ); ?></span></li>
				<li><strong>3</strong><span><?php esc_html_e( 'Review inherited pages', 'ashbi-mega-menu' ); ?></span></li>
			</ol>
			<div class="abmm-profile-admin__grid">
				<section class="abmm-profile-admin__list" aria-labelledby="abmm-product-list-title">
					<div class="abmm-profile-admin__section-head"><div><p class="abmm-profile-admin__kicker"><?php esc_html_e( 'Your system', 'ashbi-mega-menu' ); ?></p><h2 id="abmm-product-list-title"><?php esc_html_e( 'Product profiles', 'ashbi-mega-menu' ); ?></h2></div><a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=ashbi-mega-menu-products' ) ); ?>"><?php esc_html_e( 'New profile', 'ashbi-mega-menu' ); ?></a></div>
					<?php if ( empty( $profiles ) ) : ?>
						<div class="abmm-profile-admin__empty"><strong><?php esc_html_e( 'Start with one product hub', 'ashbi-mega-menu' ); ?></strong><p><?php esc_html_e( 'Create a profile for a product such as Example Product, then select its hub page to cover the child pages below it.', 'ashbi-mega-menu' ); ?></p></div>
					<?php else : ?>
						<ul class="abmm-profile-admin__cards">
						<?php foreach ( $profiles as $profile_id => $item ) : ?>
							<?php $delete_url = wp_nonce_url( admin_url( 'admin-post.php?action=abmm_delete_product_profile&profile=' . rawurlencode( $profile_id ) ), 'abmm_delete_product_profile_' . $profile_id ); $profile_url = admin_url( 'admin.php?page=ashbi-mega-menu-products&profile=' . rawurlencode( $profile_id ) ); $is_selected = $edit_id === $profile_id; ?>
							<li class="<?php echo $is_selected ? 'is-selected' : ''; ?>"><a class="abmm-profile-admin__card" href="<?php echo esc_url( $profile_url ); ?>"<?php echo $is_selected ? ' aria-current="page"' : ''; ?>><strong><?php echo esc_html( $item['title'] ?: $item['label'] ?: $profile_id ); ?></strong><span><?php printf( esc_html( _n( '%d local link', '%d local links', count( $item['links'] ?? array() ), 'ashbi-mega-menu' ) ), esc_html( count( $item['links'] ?? array() ) ) ); ?></span></a><a class="abmm-profile-admin__delete" href="<?php echo esc_url( $delete_url ); ?>" onclick="return window.confirm('<?php echo esc_js( __( 'Delete this product profile? Pages will use their inherited profile or the global fallback.', 'ashbi-mega-menu' ) ); ?>');"><?php esc_html_e( 'Delete', 'ashbi-mega-menu' ); ?></a></li>
						<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</section>
				<form class="abmm-profile-admin__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'abmm_save_product_profile' ); ?>
					<input type="hidden" name="action" value="abmm_save_product_profile" />
					<input type="hidden" name="profile_id" value="<?php echo esc_attr( $edit_id ); ?>" />
					<div class="abmm-profile-admin__form-head"><div><p class="abmm-profile-admin__kicker"><?php esc_html_e( 'Profile setup', 'ashbi-mega-menu' ); ?></p><h2><?php echo esc_html( $edit_id ? __( 'Edit product profile', 'ashbi-mega-menu' ) : __( 'Add product profile', 'ashbi-mega-menu' ) ); ?></h2></div><span><?php esc_html_e( 'Product-local only', 'ashbi-mega-menu' ); ?></span></div>
					<fieldset class="abmm-profile-admin__fieldset"><legend><?php esc_html_e( '1. Product identity', 'ashbi-mega-menu' ); ?></legend><p><?php esc_html_e( 'This establishes the product name and the page that passes its local navigation to child pages.', 'ashbi-mega-menu' ); ?></p><div class="abmm-profile-admin__field-grid"><label><?php esc_html_e( 'Internal name', 'ashbi-mega-menu' ); ?><input class="regular-text" required name="profile[title]" value="<?php echo esc_attr( $profile['title'] ); ?>" placeholder="Example Product" /></label><label><?php esc_html_e( 'Product name visitors see', 'ashbi-mega-menu' ); ?><input class="regular-text" required name="profile[label]" value="<?php echo esc_attr( $profile['label'] ); ?>" placeholder="Example Product" /></label><label><?php esc_html_e( 'Product home URL', 'ashbi-mega-menu' ); ?><input class="regular-text" required type="url" name="profile[url]" value="<?php echo esc_attr( $profile['url'] ); ?>" placeholder="https://example.com/product/" /></label><label><?php esc_html_e( 'Product hub page', 'ashbi-mega-menu' ); ?><select name="profile[root_page_id]"><option value="0"><?php esc_html_e( 'Choose a product hub', 'ashbi-mega-menu' ); ?></option><?php foreach ( $pages as $page ) : ?><option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( $profile['root_page_id'], $page->ID ); ?>><?php echo esc_html( $page->post_title ); ?></option><?php endforeach; ?></select></label></div><span class="description"><?php esc_html_e( 'Child pages inherit this profile. Use a page override only for exceptions.', 'ashbi-mega-menu' ); ?></span></fieldset>
					<fieldset class="abmm-profile-admin__fieldset"><legend><?php esc_html_e( '2. Local navigation', 'ashbi-mega-menu' ); ?></legend><p><?php esc_html_e( 'These links appear beneath the shared header only on this product and its child pages.', 'ashbi-mega-menu' ); ?></p><label class="abmm-profile-admin__style-field"><?php esc_html_e( 'Presentation', 'ashbi-mega-menu' ); ?><select name="profile[rail_style]"><option value="underline" <?php selected( $profile['rail_style'], 'underline' ); ?>><?php esc_html_e( 'Underlined links (recommended)', 'ashbi-mega-menu' ); ?></option><option value="pills" <?php selected( $profile['rail_style'], 'pills' ); ?>><?php esc_html_e( 'Pill links', 'ashbi-mega-menu' ); ?></option></select></label><div class="abmm-profile-admin__links"><div class="abmm-profile-admin__link-head"><span><?php esc_html_e( 'Label', 'ashbi-mega-menu' ); ?></span><span><?php esc_html_e( 'Destination', 'ashbi-mega-menu' ); ?></span></div><?php for ( $index = 0; $index < 6; $index++ ) : $link = $profile['links'][ $index ] ?? array( 'label' => '', 'url' => '' ); ?><div class="abmm-profile-admin__link-row"><label><span class="screen-reader-text"><?php printf(
/* translators: %d: Link position. */
esc_html__( 'Link %d label', 'ashbi-mega-menu' ), esc_html( $index + 1 ) ); ?></span><input class="regular-text" name="profile[links][<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( $link['label'] ); ?>" placeholder="<?php esc_attr_e( 'Link label', 'ashbi-mega-menu' ); ?>" /></label><label><span class="screen-reader-text"><?php printf(
/* translators: %d: Link position. */
esc_html__( 'Link %d destination', 'ashbi-mega-menu' ), esc_html( $index + 1 ) ); ?></span><input class="regular-text" type="url" name="profile[links][<?php echo esc_attr( $index ); ?>][url]" value="<?php echo esc_attr( $link['url'] ); ?>" placeholder="https://…" /></label></div><?php endfor; ?></div></fieldset>
					<fieldset class="abmm-profile-admin__fieldset abmm-profile-admin__fieldset--cta"><legend><?php esc_html_e( '3. Product CTA (optional)', 'ashbi-mega-menu' ); ?></legend><p><?php esc_html_e( 'Use this only for a product-specific conversion. Matching calls to action stay in the shared header.', 'ashbi-mega-menu' ); ?></p><label class="abmm-profile-admin__checkbox"><input type="checkbox" name="profile[cta][show]" value="1" <?php checked( ! empty( $profile['cta']['show'] ) ); ?> /> <?php esc_html_e( 'Show a distinct product CTA', 'ashbi-mega-menu' ); ?></label><div class="abmm-profile-admin__field-grid"><label><?php esc_html_e( 'CTA label', 'ashbi-mega-menu' ); ?><input class="regular-text" name="profile[cta][label]" value="<?php echo esc_attr( $profile['cta']['label'] ); ?>" placeholder="Get a demo" /></label><label><?php esc_html_e( 'CTA destination', 'ashbi-mega-menu' ); ?><input class="regular-text" type="url" name="profile[cta][url]" value="<?php echo esc_attr( $profile['cta']['url'] ); ?>" placeholder="https://…" /></label></div></fieldset>
					<div class="abmm-profile-admin__form-footer"><span><?php esc_html_e( 'The shared header is never duplicated by this profile.', 'ashbi-mega-menu' ); ?></span><?php submit_button( $edit_id ? __( 'Save product profile', 'ashbi-mega-menu' ) : __( 'Create product profile', 'ashbi-mega-menu' ), 'primary', 'submit', false ); ?></div>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a concise audit showing page, inherited, and global states.
	 */
	public function render_coverage_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$source_filter = isset( $_GET['abmm_source'] ) && is_scalar( $_GET['abmm_source'] ) ? sanitize_key( wp_unslash( $_GET['abmm_source'] ) ) : 'all';
		if ( ! in_array( $source_filter, array( 'all', 'page', 'ancestor', 'fallback' ), true ) ) {
			$source_filter = 'all';
		}
		$search = isset( $_GET['abmm_search'] ) && is_scalar( $_GET['abmm_search'] ) ? sanitize_text_field( wp_unslash( $_GET['abmm_search'] ) ) : '';
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
			<h1><?php esc_html_e( 'Navigation Coverage', 'ashbi-mega-menu' ); ?></h1>
			<p><?php esc_html_e( 'Review which product navigation each published page will use with the context-aware header. “Global fallback” is expected for corporate pages.', 'ashbi-mega-menu' ); ?></p>
			<?php if ( 0 === $coverage['profiles'] ) : ?>
				<div class="notice notice-info inline"><p><strong><?php esc_html_e( 'No product profiles yet.', 'ashbi-mega-menu' ); ?></strong> <?php esc_html_e( 'Corporate pages can use the global menu; create a profile when a product section needs local navigation.', 'ashbi-mega-menu' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=ashbi-mega-menu-products' ) ); ?>"><?php esc_html_e( 'Set up product navigation', 'ashbi-mega-menu' ); ?></a></p></div>
			<?php endif; ?>
			<div class="abmm-profile-coverage__summary" aria-label="<?php esc_attr_e( 'Navigation coverage summary', 'ashbi-mega-menu' ); ?>">
				<span><strong><?php echo esc_html( $coverage['profiles'] ); ?></strong> <?php esc_html_e( 'product profiles', 'ashbi-mega-menu' ); ?></span>
				<span><strong><?php echo esc_html( $coverage['ancestor'] ); ?></strong> <?php esc_html_e( 'inherited pages', 'ashbi-mega-menu' ); ?></span>
				<span><strong><?php echo esc_html( $coverage['page'] ); ?></strong> <?php esc_html_e( 'page exceptions', 'ashbi-mega-menu' ); ?></span>
				<span><strong><?php echo esc_html( $coverage['fallback'] ); ?></strong> <?php esc_html_e( 'global fallbacks', 'ashbi-mega-menu' ); ?></span>
			</div>
			<form method="get" class="abmm-profile-coverage__filters">
				<input type="hidden" name="page" value="ashbi-mega-menu-coverage" />
				<label for="abmm-coverage-search"><?php esc_html_e( 'Search pages', 'ashbi-mega-menu' ); ?></label>
				<input type="search" id="abmm-coverage-search" name="abmm_search" value="<?php echo esc_attr( $search ); ?>" />
				<label for="abmm-coverage-source"><?php esc_html_e( 'Navigation source', 'ashbi-mega-menu' ); ?></label>
				<select id="abmm-coverage-source" name="abmm_source">
					<option value="all" <?php selected( $source_filter, 'all' ); ?>><?php esc_html_e( 'All sources', 'ashbi-mega-menu' ); ?></option>
					<option value="page" <?php selected( $source_filter, 'page' ); ?>><?php esc_html_e( 'Page exception', 'ashbi-mega-menu' ); ?></option>
					<option value="ancestor" <?php selected( $source_filter, 'ancestor' ); ?>><?php esc_html_e( 'Inherited', 'ashbi-mega-menu' ); ?></option>
					<option value="fallback" <?php selected( $source_filter, 'fallback' ); ?>><?php esc_html_e( 'Global fallback', 'ashbi-mega-menu' ); ?></option>
				</select>
				<button type="submit" class="button"><?php esc_html_e( 'Filter', 'ashbi-mega-menu' ); ?></button>
			</form>
			<p class="abmm-profile-coverage__count"><?php echo esc_html( sprintf(
/* translators: 1: Matching page count, 2: Current page, 3: Total pages. */
__( '%1$d matching pages; page %2$d of %3$d', 'ashbi-mega-menu' ), $filtered_count, $page_number, $page_count ) ); ?></p>
			<table class="widefat striped"><caption class="screen-reader-text"><?php esc_html_e( 'Navigation coverage results', 'ashbi-mega-menu' ); ?></caption><thead><tr><th scope="col"><?php esc_html_e( 'Page', 'ashbi-mega-menu' ); ?></th><th scope="col"><?php esc_html_e( 'Navigation', 'ashbi-mega-menu' ); ?></th><th scope="col"><?php esc_html_e( 'Source', 'ashbi-mega-menu' ); ?></th></tr></thead><tbody>
			<?php foreach ( $coverage_rows as $row ) : $page = $row['page']; $resolved = $row['resolved']; ?>
				<tr><td><a href="<?php echo esc_url( get_edit_post_link( $page->ID ) ); ?>"><?php echo esc_html( $page->post_title ?: __( '(no title)', 'ashbi-mega-menu' ) ); ?></a></td><td><?php echo esc_html( $resolved['profile']['label'] ?? __( 'Global fallback', 'ashbi-mega-menu' ) ); ?></td><td><?php echo esc_html( ucfirst( $resolved['source'] ) ); ?></td></tr>
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
		$raw      = isset( $_POST['profile'] ) ? wp_unslash( $_POST['profile'] ) : array();
		$profile  = $this->sanitize_profile( $raw );
		$profiles = $this->get_all();
		$id       = isset( $_POST['profile_id'] ) ? sanitize_key( wp_unslash( $_POST['profile_id'] ) ) : '';
		$is_new   = ! $id;
		if ( $is_new ) {
			$id = sanitize_title( $profile['title'] ?: $profile['label'] );
		}
		if ( ! $id || '' === $profile['label'] || '' === $profile['url'] ) {
			wp_die( esc_html__( 'Add a product name and product home URL before saving.', 'ashbi-mega-menu' ) );
		}
		if ( $is_new && isset( $profiles[ $id ] ) ) {
			wp_die( esc_html__( 'A product profile with this name already exists. Open the existing profile to edit it.', 'ashbi-mega-menu' ) );
		}
		$profiles[ $id ] = $profile;
		update_option( self::OPTION_KEY, $profiles );
		wp_safe_redirect( admin_url( 'admin.php?page=ashbi-mega-menu-products&profile=' . rawurlencode( $id ) . '&updated=1' ) );
		exit;
	}

	/**
	 * @return void
	 */
	public function delete_profile() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage product navigation.', 'ashbi-mega-menu' ) );
		}
		$profile_id = isset( $_REQUEST['profile'] ) ? sanitize_key( wp_unslash( $_REQUEST['profile'] ) ) : '';
		check_admin_referer( 'abmm_delete_product_profile_' . $profile_id );
		$profiles = $this->get_all();
		unset( $profiles[ $profile_id ] );
		update_option( self::OPTION_KEY, $profiles );
		wp_safe_redirect( admin_url( 'admin.php?page=ashbi-mega-menu-products' ) );
		exit;
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
