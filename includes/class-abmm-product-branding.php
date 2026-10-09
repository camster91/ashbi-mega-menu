<?php
/**
 * Context-aware product branding for shared Ashbi Mega Menu headers.
 *
 * @package Ashbi_Mega_Menu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABMM_Product_Branding {

	const OPTION_KEY = 'abmm_product_branding';

	/** @var ABMM_Product_Branding|null */
	private static $instance = null;

	/** @var string */
	private $page_hook = '';

	/** @var bool */
	private $filtering_menus = false;

	/**
	 * @return ABMM_Product_Branding
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_page' ), 25 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_post_abmm_save_product_branding', array( $this, 'save' ) );
		add_action( 'admin_post_abmm_delete_product_profile', array( $this, 'cleanup_deleted_profile' ), 1 );
		add_action( 'admin_notices', array( $this, 'product_navigation_notice' ) );
		add_filter( 'option_' . ABMM_OPTION_KEY, array( $this, 'filter_menus_for_product_brand' ), 20 );
		add_filter( 'body_class', array( $this, 'body_class' ) );
	}

	/**
	 * @return array<string,array>
	 */
	public function get_all() {
		$raw = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$clean = array();
		foreach ( $raw as $profile_id => $config ) {
			$profile_id = sanitize_key( $profile_id );
			if ( '' !== $profile_id ) {
				$clean[ $profile_id ] = $this->sanitize_config( $config );
			}
		}
		return $clean;
	}

	/**
	 * @param string $profile_id Profile ID.
	 * @return array
	 */
	public function get( $profile_id ) {
		$all        = $this->get_all();
		$profile_id = sanitize_key( $profile_id );
		return $all[ $profile_id ] ?? $this->sanitize_config( array() );
	}

	/**
	 * @param array $raw Raw branding configuration.
	 * @return array
	 */
	public function sanitize_config( $raw ) {
		$raw = is_array( $raw ) ? $raw : array();
		return array(
			'enabled'                 => ! empty( $raw['enabled'] ),
			'mode'                    => 'product_text' === ( is_scalar( $raw['mode'] ?? null ) ? (string) $raw['mode'] : '' ) ? 'product_text' : 'image',
			'desktop_interaction'     => 'click' === ( is_scalar( $raw['desktop_interaction'] ?? null ) ? (string) $raw['desktop_interaction'] : '' ) ? 'click' : 'hover',
			'shared_menu_id'          => sanitize_key( is_scalar( $raw['shared_menu_id'] ?? null ) ? (string) $raw['shared_menu_id'] : '' ),
			'logo_attachment_id'      => absint( is_scalar( $raw['logo_attachment_id'] ?? null ) ? (string) $raw['logo_attachment_id'] : 0 ),
			'logo_url'                => $this->sanitize_url( $raw['logo_url'] ?? '' ),
			'logo_dark_attachment_id' => absint( is_scalar( $raw['logo_dark_attachment_id'] ?? null ) ? (string) $raw['logo_dark_attachment_id'] : 0 ),
			'logo_dark_url'           => $this->sanitize_url( $raw['logo_dark_url'] ?? '' ),
			'alt'                     => $this->sanitize_text( $raw['alt'] ?? '' ),
			'logo_height'             => min( 80, max( 16, absint( is_scalar( $raw['logo_height'] ?? null ) ? (string) $raw['logo_height'] : 40 ) ) ),
			'wordmark_attachment_id'  => absint( is_scalar( $raw['wordmark_attachment_id'] ?? null ) ? (string) $raw['wordmark_attachment_id'] : 0 ),
			'wordmark_url'            => $this->sanitize_url( $raw['wordmark_url'] ?? '' ),
			'wordmark_dark_attachment_id' => absint( is_scalar( $raw['wordmark_dark_attachment_id'] ?? null ) ? (string) $raw['wordmark_dark_attachment_id'] : 0 ),
			'wordmark_dark_url'       => $this->sanitize_url( $raw['wordmark_dark_url'] ?? '' ),
			'wordmark_height'         => min( 80, max( 16, absint( is_scalar( $raw['wordmark_height'] ?? null ) ? (string) $raw['wordmark_height'] : 40 ) ) ),
			'show_utility_links'      => ! empty( $raw['show_utility_links'] ),
			'about_url'               => $this->sanitize_url( $raw['about_url'] ?? '' ),
			'contact_url'             => $this->sanitize_url( $raw['contact_url'] ?? '' ),
		);
	}

	/**
	 * Resolve the current profile, its stable ID, and branding configuration.
	 *
	 * @return array{profile_id:string,profile:array,config:array}|null
	 */
	public function resolve_for_request() {
		$profiles = ABMM_Product_Profiles::instance()->get_all();
		$resolved = ABMM_Product_Profiles::instance()->resolve_for_request();
		$profile  = $resolved['profile'] ?? null;
		if ( empty( $profile ) ) {
			return null;
		}

		foreach ( $profiles as $profile_id => $candidate ) {
			if ( $candidate === $profile ) {
				return array(
					'profile_id' => $profile_id,
					'profile'    => $profile,
					'config'     => $this->get( $profile_id ),
				);
			}
		}
		return null;
	}

	/**
	 * Replace one shared menu brand server-side for the active product.
	 *
	 * @param mixed $menus Stored Ashbi Mega Menu data.
	 * @return mixed
	 */
	public function filter_menus_for_product_brand( $menus ) {
		$is_rest = defined( 'REST_REQUEST' ) && REST_REQUEST;
		if (
			$this->filtering_menus ||
			is_admin() ||
			wp_doing_ajax() ||
			$is_rest ||
			! did_action( 'wp' ) ||
			! is_array( $menus )
		) {
			return $menus;
		}

		$this->filtering_menus = true;
		$resolved              = $this->resolve_for_request();
		$this->filtering_menus = false;
		if ( empty( $resolved ) ) {
			return $menus;
		}

		$config  = $resolved['config'];
		$menu_id = $config['shared_menu_id'] ?? '';
		if ( empty( $config['enabled'] ) || '' === $menu_id || empty( $menus[ $menu_id ] ) ) {
			return $menus;
		}

		if ( 'product_text' === $config['mode'] ) {
			$brand = $menus[ $menu_id ]['brand'] ?? array();
			$logo_url = $this->attachment_url( $config['logo_attachment_id'], $config['logo_url'] );
			if ( '' !== $logo_url ) {
				if ( '' === $config['alt'] ) {
					return $menus;
				}
				$brand['show']          = true;
				$brand['title']         = '';
				$brand['url']           = home_url( '/' );
				$brand['logo_url']      = $logo_url;
				$brand['logo_dark_url'] = $this->attachment_url( $config['logo_dark_attachment_id'], $config['logo_dark_url'] );
				$brand['alt']           = $config['alt'];
				$brand['logo_height']   = $config['logo_height'];
			} elseif ( ! $this->has_shared_brand( $brand ) ) {
				return $menus;
			}
			if ( empty( $resolved['profile']['label'] ) ) {
				return $menus;
			}
			$brand['product_text']    = $resolved['profile']['label'];
			$brand['product_url']     = $resolved['profile']['url'] ?? '';
			$brand['product_wordmark_url'] = $this->attachment_url( $config['wordmark_attachment_id'], $config['wordmark_url'] );
			$brand['product_wordmark_dark_url'] = $this->attachment_url( $config['wordmark_dark_attachment_id'], $config['wordmark_dark_url'] );
			$brand['product_wordmark_height'] = $config['wordmark_height'];
			$menus[ $menu_id ]['brand'] = $brand;
			$menus[ $menu_id ]['settings']['desktop_interaction'] = $config['desktop_interaction'];
			if ( ! empty( $config['show_utility_links'] ) ) {
				$menus[ $menu_id ]['utility_links'] = array(
					array( 'label' => __( 'About', 'ashbi-mega-menu' ), 'url' => $config['about_url'] ),
					array( 'label' => __( 'Contact', 'ashbi-mega-menu' ), 'url' => $config['contact_url'] ),
				);
			}
			return $menus;
		}

		$logo_url = $this->attachment_url( $config['logo_attachment_id'], $config['logo_url'] );
		if ( '' === $logo_url ) {
			return $menus;
		}

		$profile = $resolved['profile'];
		$brand   = is_array( $menus[ $menu_id ]['brand'] ?? null ) ? $menus[ $menu_id ]['brand'] : array();

		$brand['show']          = true;
		$brand['title']         = '';
		$brand['url']           = $profile['url'] ?? '';
		$brand['logo_url']      = $logo_url;
		$brand['logo_dark_url'] = $this->attachment_url( $config['logo_dark_attachment_id'], $config['logo_dark_url'] );
		$brand['alt']           = '' !== $config['alt'] ? $config['alt'] : ( $profile['label'] ?? '' );
		$brand['logo_height']   = $config['logo_height'];
		$brand['class']         = trim( ( $brand['class'] ?? '' ) . ' abmm-nav__brand--product-lockup' );

		$menus[ $menu_id ]['brand'] = $brand;
		return $menus;
	}

	/**
	 * @param mixed $brand Shared menu brand.
	 * @return bool
	 */
	private function has_shared_brand( $brand ) {
		return is_array( $brand ) && ! empty( $brand['show'] ) &&
			! empty( $brand['logo_url'] ) && ! empty( $brand['alt'] );
	}

	/**
	 * @param int    $attachment_id Attachment ID.
	 * @param string $fallback_url Fallback URL.
	 * @return string
	 */
	private function attachment_url( $attachment_id, $fallback_url ) {
		if ( $attachment_id ) {
			$url = wp_get_attachment_image_url( $attachment_id, 'full' );
			if ( is_string( $url ) && '' !== $url ) {
				return $url;
			}
		}
		return $this->sanitize_url( $fallback_url );
	}

	/**
	 * Sanitize scalar branding URLs without passing structured input to WordPress.
	 *
	 * @param mixed $url Raw URL.
	 * @return string
	 */
	private function sanitize_url( $url ) {
		return is_scalar( $url ) ? esc_url_raw( (string) $url ) : '';
	}

	/**
	 * Sanitize scalar branding text without passing structured input to WordPress.
	 *
	 * @param mixed $value Raw text value.
	 * @return string
	 */
	private function sanitize_text( $value ) {
		return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	}

	/**
	 * Register the product-branding editor.
	 */
	public function register_page() {
		$this->page_hook = add_submenu_page(
			'ashbi-mega-menu',
			__( 'Product Branding', 'ashbi-mega-menu' ),
			__( 'Product Branding', 'ashbi-mega-menu' ),
			'manage_options',
			'ashbi-mega-menu-product-branding',
			array( $this, 'render_page' )
		);
	}

	/**
	 * @param string $hook_suffix Current admin hook.
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( $this->page_hook !== $hook_suffix ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style(
			'abmm-product-branding-admin',
			ABMM_PLUGIN_URL . 'assets/css/product-branding-admin.css',
			array(),
			ABMM_VERSION
		);
		wp_enqueue_script(
			'abmm-product-branding-admin',
			ABMM_PLUGIN_URL . 'assets/js/product-branding-admin.js',
			array(),
			ABMM_VERSION,
			true
		);
		wp_localize_script(
			'abmm-product-branding-admin',
			'abmmProductBranding',
			array(
				'mediaTitle'  => __( 'Choose a product lockup', 'ashbi-mega-menu' ),
				'mediaButton' => __( 'Use this image', 'ashbi-mega-menu' ),
			)
		);
	}

	/**
	 * Render a focused, non-technical product-branding workflow.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$profiles = ABMM_Product_Profiles::instance()->get_all();
		$menus    = ABMM_Data::instance()->get_all();
		$selected = isset( $_GET['profile'] ) ? sanitize_key( wp_unslash( $_GET['profile'] ) ) : '';
		if ( '' === $selected && ! empty( $profiles ) ) {
			$selected = (string) array_key_first( $profiles );
		}

		$profile = $profiles[ $selected ] ?? null;
		$config  = $this->get( $selected );
		$states  = $this->profile_states( $profiles, $menus );
		$primary = $this->attachment_url( $config['logo_attachment_id'], $config['logo_url'] );
		$dark    = $this->attachment_url( $config['logo_dark_attachment_id'], $config['logo_dark_url'] );
		$wordmark = $this->attachment_url( $config['wordmark_attachment_id'], $config['wordmark_url'] );
		$wordmark_dark = $this->attachment_url( $config['wordmark_dark_attachment_id'], $config['wordmark_dark_url'] );
		?>
		<div class="wrap abmm-branding-admin">
			<div class="abmm-branding-admin__hero">
				<div>
					<p class="abmm-branding-admin__eyebrow"><?php esc_html_e( 'Shared-header product system', 'ashbi-mega-menu' ); ?></p>
					<h1><?php esc_html_e( 'Product Branding', 'ashbi-mega-menu' ); ?></h1>
					<p><?php esc_html_e( 'Give each product one approved lockup while keeping a single shared header. Child pages inherit the same identity automatically.', 'ashbi-mega-menu' ); ?></p>
				</div>
				<div class="abmm-branding-admin__hero-actions">
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=ashbi-mega-menu-products' ) ); ?>"><?php esc_html_e( 'Product navigation', 'ashbi-mega-menu' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=ashbi-mega-menu-coverage' ) ); ?>"><?php esc_html_e( 'Coverage audit', 'ashbi-mega-menu' ); ?></a>
				</div>
			</div>

			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Product branding saved.', 'ashbi-mega-menu' ); ?></p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['error'] ) ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $this->error_message( sanitize_key( wp_unslash( $_GET['error'] ) ) ) ); ?></p></div>
			<?php endif; ?>

			<?php if ( empty( $profiles ) ) : ?>
				<div class="abmm-branding-admin__empty">
					<h2><?php esc_html_e( 'Create a product profile first', 'ashbi-mega-menu' ); ?></h2>
					<p><?php esc_html_e( 'Product branding follows the same product hubs and page exceptions as Product Navigation.', 'ashbi-mega-menu' ); ?></p>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=ashbi-mega-menu-products' ) ); ?>"><?php esc_html_e( 'Create product profile', 'ashbi-mega-menu' ); ?></a>
				</div>
			<?php else : ?>
				<div class="abmm-branding-admin__summary" aria-label="<?php esc_attr_e( 'Product branding readiness', 'ashbi-mega-menu' ); ?>">
					<span><strong><?php echo esc_html( count( $profiles ) ); ?></strong><?php esc_html_e( 'products', 'ashbi-mega-menu' ); ?></span>
					<span><strong><?php echo esc_html( $states['ready'] ); ?></strong><?php esc_html_e( 'ready', 'ashbi-mega-menu' ); ?></span>
					<span><strong><?php echo esc_html( $states['off'] ); ?></strong><?php esc_html_e( 'using shared logo', 'ashbi-mega-menu' ); ?></span>
					<span><strong><?php echo esc_html( $states['attention'] ); ?></strong><?php esc_html_e( 'need attention', 'ashbi-mega-menu' ); ?></span>
				</div>

				<div class="abmm-branding-admin__layout">
					<nav class="abmm-branding-admin__profiles" aria-label="<?php esc_attr_e( 'Product profiles', 'ashbi-mega-menu' ); ?>">
						<h2><?php esc_html_e( 'Products', 'ashbi-mega-menu' ); ?></h2>
						<ul>
						<?php foreach ( $profiles as $profile_id => $item ) : ?>
							<?php $state = $this->profile_state( $profile_id, $item, $menus ); ?>
							<li>
								<a class="<?php echo $profile_id === $selected ? 'is-current' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=ashbi-mega-menu-product-branding&profile=' . rawurlencode( $profile_id ) ) ); ?>"<?php echo $profile_id === $selected ? ' aria-current="page"' : ''; ?>>
									<span><strong><?php echo esc_html( $item['label'] ?: $item['title'] ?: $profile_id ); ?></strong><small><?php echo esc_html( $state['label'] ); ?></small></span>
									<i class="abmm-branding-status abmm-branding-status--<?php echo esc_attr( $state['key'] ); ?>" aria-hidden="true"></i>
								</a>
							</li>
						<?php endforeach; ?>
						</ul>
					</nav>

					<?php if ( $profile ) : ?>
						<form class="abmm-branding-admin__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'abmm_save_product_branding' ); ?>
							<input type="hidden" name="action" value="abmm_save_product_branding" />
							<input type="hidden" name="profile_id" value="<?php echo esc_attr( $selected ); ?>" />

							<div class="abmm-branding-admin__form-head">
								<div><p><?php esc_html_e( 'Editing', 'ashbi-mega-menu' ); ?></p><h2><?php echo esc_html( $profile['label'] ?: $profile['title'] ); ?></h2></div>
								<span><?php esc_html_e( 'One-logo mode', 'ashbi-mega-menu' ); ?></span>
							</div>

							<label class="abmm-branding-admin__toggle">
								<input type="checkbox" name="branding[enabled]" value="1" <?php checked( ! empty( $config['enabled'] ) ); ?> />
								<span><strong><?php esc_html_e( 'Enable product branding', 'ashbi-mega-menu' ); ?></strong><small><?php esc_html_e( 'Apply the selected treatment to this product and inherited child pages.', 'ashbi-mega-menu' ); ?></small></span>
							</label>
							<label for="abmm-branding-mode"><?php esc_html_e( 'Brand treatment', 'ashbi-mega-menu' ); ?></label>
							<select id="abmm-branding-mode" name="branding[mode]" data-abmm-branding-mode>
								<option value="image" <?php selected( $config['mode'], 'image' ); ?>><?php esc_html_e( 'Combined logo image', 'ashbi-mega-menu' ); ?></option>
								<option value="product_text" <?php selected( $config['mode'], 'product_text' ); ?>><?php esc_html_e( 'Shared logo + product name', 'ashbi-mega-menu' ); ?></option>
							</select>
							<p><?php esc_html_e( 'Shared logo + product name keeps the corporate logo and its link, then adds a divider and the Product Navigation name linking to the product home. The brand image below is applied only on pages in this product profile.', 'ashbi-mega-menu' ); ?></p>

							<fieldset>
								<legend><?php esc_html_e( 'Shared header', 'ashbi-mega-menu' ); ?></legend>
								<label for="abmm-product-brand-menu"><?php esc_html_e( 'Menu to brand', 'ashbi-mega-menu' ); ?></label>
								<select id="abmm-product-brand-menu" name="branding[shared_menu_id]">
									<option value=""><?php esc_html_e( 'Choose the shared header menu', 'ashbi-mega-menu' ); ?></option>
									<?php foreach ( $menus as $menu_id => $menu ) : ?>
										<option value="<?php echo esc_attr( $menu_id ); ?>" <?php selected( $config['shared_menu_id'], $menu_id ); ?>><?php echo esc_html( $menu['title'] ?? $menu_id ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'In combined-image mode the entire image links to the product home. In shared-logo mode only the product name uses that destination.', 'ashbi-mega-menu' ); ?></p>
							</fieldset>

							<fieldset data-abmm-image-branding>
								<legend><?php esc_html_e( 'Brand image', 'ashbi-mega-menu' ); ?></legend>
								<div class="abmm-branding-admin__preview" data-abmm-brand-preview<?php echo '' === $primary ? ' hidden' : ''; ?>>
									<img src="<?php echo esc_url( $primary ); ?>" alt="" data-abmm-brand-preview-image />
									<span><?php esc_html_e( 'Shared-header preview', 'ashbi-mega-menu' ); ?></span>
								</div>
								<?php $this->render_media_field( 'primary', __( 'Primary brand image', 'ashbi-mega-menu' ), $primary, $config['logo_attachment_id'], true ); ?>
								<?php $this->render_media_field( 'dark', __( 'Dark-header brand image (optional)', 'ashbi-mega-menu' ), $dark, $config['logo_dark_attachment_id'], false ); ?>
								<div class="abmm-branding-admin__grid">
									<label><?php esc_html_e( 'Alternative text', 'ashbi-mega-menu' ); ?><input class="regular-text" name="branding[alt]" value="<?php echo esc_attr( $config['alt'] ); ?>" placeholder="<?php esc_attr_e( 'site', 'ashbi-mega-menu' ); ?>" /><small><?php esc_html_e( 'Describe the selected image accurately. This is required when product branding is enabled.', 'ashbi-mega-menu' ); ?></small></label>
									<label><?php esc_html_e( 'Logo height', 'ashbi-mega-menu' ); ?><span class="abmm-branding-admin__number"><input type="number" min="16" max="80" name="branding[logo_height]" value="<?php echo esc_attr( $config['logo_height'] ); ?>" /> px</span></label>
								</div>
							</fieldset>

							<fieldset data-abmm-product-text-branding>
								<legend><?php esc_html_e( 'Product wordmark', 'ashbi-mega-menu' ); ?></legend>
								<p><?php esc_html_e( 'Optional. Use an approved wordmark image when the product name must match the corporate logo typography exactly. The accessible product name remains available to screen readers.', 'ashbi-mega-menu' ); ?></p>
								<?php $this->render_media_field( 'wordmark', __( 'Primary product wordmark', 'ashbi-mega-menu' ), $wordmark, $config['wordmark_attachment_id'], false ); ?>
								<?php $this->render_media_field( 'wordmark-dark', __( 'Dark-header product wordmark (optional)', 'ashbi-mega-menu' ), $wordmark_dark, $config['wordmark_dark_attachment_id'], false ); ?>
								<div class="abmm-branding-admin__grid">
									<label><?php esc_html_e( 'Wordmark height', 'ashbi-mega-menu' ); ?><span class="abmm-branding-admin__number"><input type="number" min="16" max="80" name="branding[wordmark_height]" value="<?php echo esc_attr( $config['wordmark_height'] ); ?>" /> px</span><small><?php esc_html_e( 'Match the corporate logo height for a balanced single identity.', 'ashbi-mega-menu' ); ?></small></label>
									<label><?php esc_html_e( 'Desktop menu behavior', 'ashbi-mega-menu' ); ?><select name="branding[desktop_interaction]"><option value="hover" <?php selected( $config['desktop_interaction'], 'hover' ); ?>><?php esc_html_e( 'Open on hover and click', 'ashbi-mega-menu' ); ?></option><option value="click" <?php selected( $config['desktop_interaction'], 'click' ); ?>><?php esc_html_e( 'Open and close on click', 'ashbi-mega-menu' ); ?></option></select></label>
								</div>
							</fieldset>

							<fieldset>
								<legend><?php esc_html_e( 'Corporate utility bar', 'ashbi-mega-menu' ); ?></legend>
								<label class="abmm-branding-admin__toggle"><input type="checkbox" name="branding[show_utility_links]" value="1" <?php checked( ! empty( $config['show_utility_links'] ) ); ?> /><span><strong><?php esc_html_e( 'Show About and Contact above the header', 'ashbi-mega-menu' ); ?></strong><small><?php esc_html_e( 'Adds a slim corporate-links bar on desktop; it stays hidden in the mobile drawer layout.', 'ashbi-mega-menu' ); ?></small></span></label>
								<div class="abmm-branding-admin__grid">
									<label><?php esc_html_e( 'About URL', 'ashbi-mega-menu' ); ?><input class="regular-text" type="url" name="branding[about_url]" value="<?php echo esc_attr( $config['about_url'] ); ?>" /></label>
									<label><?php esc_html_e( 'Contact URL', 'ashbi-mega-menu' ); ?><input class="regular-text" type="url" name="branding[contact_url]" value="<?php echo esc_attr( $config['contact_url'] ); ?>" /></label>
								</div>
							</fieldset>

							<div class="abmm-branding-admin__inheritance">
								<strong><?php esc_html_e( 'Automatic inheritance', 'ashbi-mega-menu' ); ?></strong>
								<p><?php esc_html_e( 'This uses the same product hub and page-exception rules as Product Navigation. Corporate pages keep the normal shared logo.', 'ashbi-mega-menu' ); ?></p>
							</div>

							<div class="abmm-branding-admin__footer"><?php submit_button( __( 'Save product branding', 'ashbi-mega-menu' ), 'primary', 'submit', false ); ?></div>
						</form>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render one Media Library URL/attachment field.
	 *
	 * @param string $key Key suffix.
	 * @param string $label Field label.
	 * @param string $url Current URL.
	 * @param int    $attachment_id Current attachment ID.
	 * @param bool   $is_preview Whether this field drives the preview.
	 */
	private function render_media_field( $key, $label, $url, $attachment_id, $is_preview ) {
		$url_id  = 'abmm-product-logo-' . sanitize_html_class( $key ) . '-url';
		$id_id   = 'abmm-product-logo-' . sanitize_html_class( $key ) . '-id';
		$url_keys = array(
			'primary'       => 'logo_url',
			'dark'          => 'logo_dark_url',
			'wordmark'      => 'wordmark_url',
			'wordmark-dark' => 'wordmark_dark_url',
		);
		$id_keys = array(
			'primary'       => 'logo_attachment_id',
			'dark'          => 'logo_dark_attachment_id',
			'wordmark'      => 'wordmark_attachment_id',
			'wordmark-dark' => 'wordmark_dark_attachment_id',
		);
		$url_key = $url_keys[ $key ] ?? 'logo_url';
		$id_key  = $id_keys[ $key ] ?? 'logo_attachment_id';
		?>
		<div class="abmm-branding-admin__media-field">
			<label for="<?php echo esc_attr( $url_id ); ?>"><?php echo esc_html( $label ); ?></label>
			<div>
				<input id="<?php echo esc_attr( $url_id ); ?>" class="regular-text" type="url" name="branding[<?php echo esc_attr( $url_key ); ?>]" value="<?php echo esc_attr( $url ); ?>"<?php echo $is_preview ? ' data-abmm-brand-url' : ''; ?> />
				<input id="<?php echo esc_attr( $id_id ); ?>" type="hidden" name="branding[<?php echo esc_attr( $id_key ); ?>]" value="<?php echo esc_attr( $attachment_id ); ?>" />
				<button class="button" type="button" data-abmm-media-target="<?php echo esc_attr( $url_id ); ?>" data-abmm-media-id-target="<?php echo esc_attr( $id_id ); ?>"><?php esc_html_e( 'Choose image', 'ashbi-mega-menu' ); ?></button>
				<button class="button-link-delete" type="button" data-abmm-media-clear="<?php echo esc_attr( $url_id ); ?>" data-abmm-media-id-clear="<?php echo esc_attr( $id_id ); ?>"><?php esc_html_e( 'Clear', 'ashbi-mega-menu' ); ?></button>
			</div>
		</div>
		<?php
	}

	/**
	 * Save one product-branding configuration.
	 */
	public function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage product branding.', 'ashbi-mega-menu' ) );
		}
		check_admin_referer( 'abmm_save_product_branding' );

		$profile_id = isset( $_POST['profile_id'] ) ? sanitize_key( wp_unslash( $_POST['profile_id'] ) ) : '';
		$profiles   = ABMM_Product_Profiles::instance()->get_all();
		if ( '' === $profile_id || empty( $profiles[ $profile_id ] ) ) {
			$this->redirect_with_error( $profile_id, 'profile' );
		}

		$raw    = isset( $_POST['branding'] ) ? wp_unslash( $_POST['branding'] ) : array();
		$config = $this->sanitize_config( $raw );
		$menus  = ABMM_Data::instance()->get_all();
		if ( ! empty( $config['enabled'] ) ) {
			if ( '' === $config['shared_menu_id'] || empty( $menus[ $config['shared_menu_id'] ] ) ) {
				$this->redirect_with_error( $profile_id, 'menu' );
			}
			$configured_logo = $this->attachment_url( $config['logo_attachment_id'], $config['logo_url'] );
			if ( 'product_text' === $config['mode'] && '' === $configured_logo && ! $this->has_shared_brand( $menus[ $config['shared_menu_id'] ]['brand'] ?? array() ) ) {
				$this->redirect_with_error( $profile_id, 'shared_brand' );
			}
			if ( 'product_text' === $config['mode'] && '' !== $configured_logo && '' === $config['alt'] ) {
				$this->redirect_with_error( $profile_id, 'alt' );
			}
			if ( 'image' === $config['mode'] && '' === $this->attachment_url( $config['logo_attachment_id'], $config['logo_url'] ) ) {
				$this->redirect_with_error( $profile_id, 'logo' );
			}
			if ( 'image' === $config['mode'] && '' === $config['alt'] ) {
				$this->redirect_with_error( $profile_id, 'alt' );
			}
			if ( ! empty( $config['show_utility_links'] ) && ( '' === $config['about_url'] || '' === $config['contact_url'] ) ) {
				$this->redirect_with_error( $profile_id, 'utility_links' );
			}
		}

		$all                = $this->get_all();
		$all[ $profile_id ] = $config;
		update_option( self::OPTION_KEY, $all, false );

		wp_safe_redirect( admin_url( 'admin.php?page=ashbi-mega-menu-product-branding&profile=' . rawurlencode( $profile_id ) . '&updated=1' ) );
		exit;
	}

	/**
	 * Remove orphaned branding before the existing profile deletion handler exits.
	 */
	public function cleanup_deleted_profile() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$profile_id = isset( $_GET['profile'] ) ? sanitize_key( wp_unslash( $_GET['profile'] ) ) : '';
		if ( '' === $profile_id ) {
			return;
		}

		check_admin_referer( 'abmm_delete_product_profile_' . $profile_id );
		$all = $this->get_all();
		if ( isset( $all[ $profile_id ] ) ) {
			unset( $all[ $profile_id ] );
			update_option( self::OPTION_KEY, $all, false );
		}
	}

	/**
	 * Add a discoverable path from Product Navigation to Product Branding.
	 */
	public function product_navigation_notice() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( ! current_user_can( 'manage_options' ) || 'ashbi-mega-menu-products' !== $page ) {
			return;
		}
		?>
		<div class="notice notice-info"><p><strong><?php esc_html_e( 'Need one product-specific logo in the shared header?', 'ashbi-mega-menu' ); ?></strong> <a href="<?php echo esc_url( admin_url( 'admin.php?page=ashbi-mega-menu-product-branding' ) ); ?>"><?php esc_html_e( 'Manage product branding', 'ashbi-mega-menu' ); ?></a></p></div>
		<?php
	}

	/**
	 * @param array $classes Body classes.
	 * @return array
	 */
	public function body_class( $classes ) {
		$resolved = $this->resolve_for_request();
		if ( ! empty( $resolved['config']['enabled'] ) ) {
			$classes[] = 'abmm-product-branding-active';
			$classes[] = 'abmm-product-branding-' . sanitize_html_class( $resolved['profile_id'] );
		}
		return $classes;
	}

	/**
	 * @param array $profiles Profiles.
	 * @param array $menus Menus.
	 * @return array{ready:int,off:int,attention:int}
	 */
	private function profile_states( $profiles, $menus ) {
		$summary = array( 'ready' => 0, 'off' => 0, 'attention' => 0 );
		foreach ( $profiles as $profile_id => $profile ) {
			$key = $this->profile_state( $profile_id, $profile, $menus )['key'];
			if ( 'ready' === $key ) {
				++$summary['ready'];
			} elseif ( 'off' === $key ) {
				++$summary['off'];
			} else {
				++$summary['attention'];
			}
		}
		return $summary;
	}

	/**
	 * @param string $profile_id Profile ID.
	 * @param array  $profile Profile.
	 * @param array  $menus Menus.
	 * @return array{key:string,label:string}
	 */
	private function profile_state( $profile_id, $profile, $menus ) {
		$config = $this->get( $profile_id );
		if ( empty( $config['enabled'] ) ) {
			return array( 'key' => 'off', 'label' => __( 'Shared logo', 'ashbi-mega-menu' ) );
		}
		if ( empty( $profile['root_page_id'] ) ) {
			return array( 'key' => 'warning', 'label' => __( 'Needs product hub', 'ashbi-mega-menu' ) );
		}
		if ( '' === $config['shared_menu_id'] || empty( $menus[ $config['shared_menu_id'] ] ) ) {
			return array( 'key' => 'warning', 'label' => __( 'Needs shared menu', 'ashbi-mega-menu' ) );
		}
		if ( 'product_text' === $config['mode'] ) {
			$configured_logo = $this->attachment_url( $config['logo_attachment_id'], $config['logo_url'] );
			$has_logo = ( '' !== $configured_logo && '' !== $config['alt'] ) || $this->has_shared_brand( $menus[ $config['shared_menu_id'] ]['brand'] ?? array() );
			return $has_logo
				? array( 'key' => 'ready', 'label' => __( 'Ready', 'ashbi-mega-menu' ) )
				: array( 'key' => 'warning', 'label' => __( 'Needs shared logo and alt text', 'ashbi-mega-menu' ) );
		}
		if ( '' === $this->attachment_url( $config['logo_attachment_id'], $config['logo_url'] ) ) {
			return array( 'key' => 'warning', 'label' => __( 'Needs lockup', 'ashbi-mega-menu' ) );
		}
		if ( '' === $config['alt'] ) {
			return array( 'key' => 'warning', 'label' => __( 'Needs alt text', 'ashbi-mega-menu' ) );
		}
		return array( 'key' => 'ready', 'label' => __( 'Ready', 'ashbi-mega-menu' ) );
	}

	/**
	 * @param string $profile_id Profile ID.
	 * @param string $error Error key.
	 */
	private function redirect_with_error( $profile_id, $error ) {
		$url = admin_url( 'admin.php?page=ashbi-mega-menu-product-branding&error=' . rawurlencode( $error ) );
		if ( '' !== $profile_id ) {
			$url .= '&profile=' . rawurlencode( $profile_id );
		}
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * @param string $error Error key.
	 * @return string
	 */
	private function error_message( $error ) {
		$messages = array(
			'shared_brand' => __( 'Enable a corporate logo with alternative text in the selected shared menu first.', 'ashbi-mega-menu' ),
			'profile' => __( 'Choose a valid product profile and try again.', 'ashbi-mega-menu' ),
			'menu'    => __( 'Choose the shared Ashbi Mega Menu header before enabling product branding.', 'ashbi-mega-menu' ),
			'logo'    => __( 'Choose a product lockup before enabling product branding.', 'ashbi-mega-menu' ),
			'alt'     => __( 'Add alternative text that accurately describes the product lockup.', 'ashbi-mega-menu' ),
			'utility_links' => __( 'Add both About and Contact destinations before enabling the corporate utility bar.', 'ashbi-mega-menu' ),
		);
		return $messages[ $error ] ?? __( 'Product branding could not be saved. Review the fields and try again.', 'ashbi-mega-menu' );
	}
}
