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
			'logo_height'             => min( 80, max( 16, intval( is_scalar( $raw['logo_height'] ?? null ) ? (string) $raw['logo_height'] : 40 ) ) ),
			'wordmark_attachment_id'  => absint( is_scalar( $raw['wordmark_attachment_id'] ?? null ) ? (string) $raw['wordmark_attachment_id'] : 0 ),
			'wordmark_url'            => $this->sanitize_url( $raw['wordmark_url'] ?? '' ),
			'wordmark_dark_attachment_id' => absint( is_scalar( $raw['wordmark_dark_attachment_id'] ?? null ) ? (string) $raw['wordmark_dark_attachment_id'] : 0 ),
			'wordmark_dark_url'       => $this->sanitize_url( $raw['wordmark_dark_url'] ?? '' ),
			'wordmark_height'         => min( 80, max( 16, intval( is_scalar( $raw['wordmark_height'] ?? null ) ? (string) $raw['wordmark_height'] : 40 ) ) ),
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
		ABMM_Product_Profiles::enqueue_admin_script();
	}

	/**
	 * The product's own logo, for previews in the admin. Empty when it has none.
	 *
	 * @param string $profile_id Profile ID.
	 * @return string
	 */
	public function logo_preview_url( $profile_id ) {
		$config = $this->get( $profile_id );
		return $this->attachment_url( $config['logo_attachment_id'], $config['logo_url'] );
	}

	/**
	 * Render a focused, non-technical product-branding workflow.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$profiles = ABMM_Product_Profiles::instance()->get_all();
		$notice   = $this->notice_state();
		$menus    = ABMM_Data::instance()->get_all();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only selection/filter; admin capability is checked and output is escaped.
		$selected = isset( $_GET['profile'] ) ? sanitize_key( wp_unslash( $_GET['profile'] ) ) : '';
		if ( '' === $selected && ! empty( $profiles ) ) {
			$selected = (string) array_key_first( $profiles );
		}

		$profile          = $profiles[ $selected ] ?? null;
		$config           = $this->get( $selected );
		$revision         = ABMM_Option_Store::revision( $this->get_all()[ $selected ] ?? null );
		$profile_revision = ABMM_Option_Store::revision( $profile );
		$draft            = ABMM_Option_Store::draft( 'ashbi-mega-menu-product-branding' );
		if ( $draft && $selected === $draft['id'] ) {
			$config = $this->sanitize_config( $draft['record'] );
		}
		$states           = $this->profile_states( $profiles, $menus );
		$primary          = $this->attachment_url( $config['logo_attachment_id'], $config['logo_url'] );
		$dark             = $this->attachment_url( $config['logo_dark_attachment_id'], $config['logo_dark_url'] );
		$wordmark         = $this->attachment_url( $config['wordmark_attachment_id'], $config['wordmark_url'] );
		$wordmark_dark    = $this->attachment_url( $config['wordmark_dark_attachment_id'], $config['wordmark_dark_url'] );
		$abmm_page_header = array(
			'title'   => __( 'Product Branding', 'ashbi-mega-menu' ),
			'intro'   => __( 'Show a product\'s own logo in the menu on that product\'s pages.', 'ashbi-mega-menu' ),
			'current' => 'branding',
		);
		?>
		<div class="wrap abmm-branding-admin">
			<?php require ABMM_PLUGIN_DIR . 'admin/views/brand-strip.php'; ?>

			<?php if ( $draft ) : ?><div class="notice notice-error"><p><?php echo esc_html( $draft['error'] ); ?> <?php esc_html_e( 'Your changes are kept below. Check them, then save again.', 'ashbi-mega-menu' ); ?></p></div><?php endif; ?>
			<?php if ( $notice['updated'] ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Product branding saved.', 'ashbi-mega-menu' ); ?></p></div>
			<?php endif; ?>
			<?php if ( '' !== $notice['error'] ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $this->error_message( $notice['error'] ) ); ?></p></div>
			<?php endif; ?>

			<?php if ( empty( $profiles ) ) : ?>
				<div class="abmm-product-empty abmm-product-empty--page">
					<span class="abmm-product-empty__icon dashicons dashicons-format-image" aria-hidden="true"></span>
					<strong><?php esc_html_e( 'Add a product first', 'ashbi-mega-menu' ); ?></strong>
					<p><?php esc_html_e( 'Product logos are set per product. Add a product, then come back to give it its own logo.', 'ashbi-mega-menu' ); ?></p>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=ashbi-mega-menu-products' ) ); ?>"><?php esc_html_e( 'Add a product', 'ashbi-mega-menu' ); ?></a>
				</div>
			<?php else : ?>
				<div class="abmm-branding-admin__summary" aria-label="<?php esc_attr_e( 'Product branding readiness', 'ashbi-mega-menu' ); ?>">
					<span><strong><?php echo esc_html( number_format_i18n( count( $profiles ) ) ); ?></strong><?php esc_html_e( 'products', 'ashbi-mega-menu' ); ?></span>
					<span class="is-ready"><strong><?php echo esc_html( number_format_i18n( $states['ready'] ) ); ?></strong><?php esc_html_e( 'ready', 'ashbi-mega-menu' ); ?></span>
					<span class="is-off"><strong><?php echo esc_html( number_format_i18n( $states['off'] ) ); ?></strong><?php esc_html_e( 'using shared logo', 'ashbi-mega-menu' ); ?></span>
					<span class="<?php echo $states['attention'] ? 'is-attention' : ''; ?>"><strong><?php echo esc_html( number_format_i18n( $states['attention'] ) ); ?></strong><?php esc_html_e( 'need attention', 'ashbi-mega-menu' ); ?></span>
				</div>

				<div class="abmm-branding-admin__layout">
					<nav class="abmm-branding-admin__profiles" aria-labelledby="abmm-branding-products-title">
						<div class="abmm-section-head">
							<h2 id="abmm-branding-products-title"><?php esc_html_e( 'Products', 'ashbi-mega-menu' ); ?></h2>
							<span class="abmm-count"><?php echo esc_html( number_format_i18n( count( $profiles ) ) ); ?></span>
						</div>
						<ul class="abmm-product-cards">
						<?php foreach ( $profiles as $profile_id => $item ) : ?>
							<?php
							$state      = $this->profile_state( $profile_id, $item, $menus );
							$item_title = $item['label'] ?: $item['title'] ?: $profile_id;
							$item_logo  = $this->logo_preview_url( $profile_id );
							$status_key = array(
								'ready'   => 'ready',
								'off'     => 'off',
								'warning' => 'draft',
							);
							?>
							<li class="abmm-product-card<?php echo $profile_id === $selected ? ' is-selected' : ''; ?>">
								<span class="abmm-product-card__avatar<?php echo '' !== $item_logo ? ' has-logo' : ''; ?>" aria-hidden="true">
									<?php if ( '' !== $item_logo ) : ?>
										<img src="<?php echo esc_url( $item_logo ); ?>" alt="" />
									<?php else : ?>
										<?php echo esc_html( function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( $item_title, 0, 1 ) ) : strtoupper( substr( $item_title, 0, 1 ) ) ); ?>
									<?php endif; ?>
								</span>
								<div class="abmm-product-card__body">
									<a class="abmm-product-card__title" href="<?php echo esc_url( admin_url( 'admin.php?page=ashbi-mega-menu-product-branding&profile=' . rawurlencode( $profile_id ) ) ); ?>"<?php echo $profile_id === $selected ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $item_title ); ?></a>
									<span class="abmm-status abmm-status--<?php echo esc_attr( $status_key[ $state['key'] ] ?? 'draft' ); ?>"><?php echo esc_html( $state['label'] ); ?></span>
								</div>
							</li>
						<?php endforeach; ?>
						</ul>
					</nav>

					<?php if ( $profile ) : ?>
						<?php $current_state = $this->profile_state( $selected, $profile, $menus ); ?>
						<form class="abmm-branding-admin__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'abmm_save_product_branding' ); ?>
							<input type="hidden" name="action" value="abmm_save_product_branding" />
							<input type="hidden" name="profile_id" value="<?php echo esc_attr( $selected ); ?>" />
							<input type="hidden" name="revision" value="<?php echo esc_attr( $revision ); ?>" />
							<input type="hidden" name="profile_revision" value="<?php echo esc_attr( $profile_revision ); ?>" />

							<div class="abmm-branding-admin__form-head">
								<div>
									<p class="abmm-profile-admin__form-eyebrow"><?php esc_html_e( 'Branding for', 'ashbi-mega-menu' ); ?></p>
									<h2><?php echo esc_html( $profile['label'] ?: $profile['title'] ); ?></h2>
								</div>
								<span class="abmm-status abmm-status--<?php echo esc_attr( array( 'ready' => 'ready', 'off' => 'off' )[ $current_state['key'] ] ?? 'draft' ); ?>"><?php echo esc_html( $current_state['label'] ); ?></span>
							</div>

							<label class="abmm-branding-admin__toggle abmm-switch-row">
								<input class="abmm-switch" type="checkbox" name="branding[enabled]" value="1" <?php checked( ! empty( $config['enabled'] ) ); ?> />
								<span><strong><?php esc_html_e( 'Use a product logo', 'ashbi-mega-menu' ); ?></strong><small><?php esc_html_e( 'Shown on this product\'s pages. Other pages keep your main logo.', 'ashbi-mega-menu' ); ?></small></span>
							</label>

							<fieldset>
								<legend><?php esc_html_e( 'Logo style', 'ashbi-mega-menu' ); ?></legend>
								<p><?php esc_html_e( '"Shared logo + product name" keeps your main logo and adds the product name after it.', 'ashbi-mega-menu' ); ?></p>
								<label for="abmm-branding-mode" class="screen-reader-text"><?php esc_html_e( 'Logo style', 'ashbi-mega-menu' ); ?></label>
								<select id="abmm-branding-mode" name="branding[mode]" data-abmm-branding-mode>
									<option value="image" <?php selected( $config['mode'], 'image' ); ?>><?php esc_html_e( 'One combined logo image', 'ashbi-mega-menu' ); ?></option>
									<option value="product_text" <?php selected( $config['mode'], 'product_text' ); ?>><?php esc_html_e( 'Shared logo + product name', 'ashbi-mega-menu' ); ?></option>
								</select>
							</fieldset>

							<fieldset>
								<legend><?php esc_html_e( 'Menu', 'ashbi-mega-menu' ); ?></legend>
								<p><?php esc_html_e( 'The menu that shows the product logo. The logo links to the product page.', 'ashbi-mega-menu' ); ?></p>
								<label for="abmm-product-brand-menu" class="screen-reader-text"><?php esc_html_e( 'Show it in this menu', 'ashbi-mega-menu' ); ?></label>
								<select id="abmm-product-brand-menu" name="branding[shared_menu_id]">
									<option value=""><?php esc_html_e( 'Choose a menu', 'ashbi-mega-menu' ); ?></option>
									<?php foreach ( $menus as $menu_id => $menu ) : ?>
										<option value="<?php echo esc_attr( $menu_id ); ?>" <?php selected( $config['shared_menu_id'], $menu_id ); ?>><?php echo esc_html( $menu['title'] ?? $menu_id ); ?></option>
									<?php endforeach; ?>
								</select>
							</fieldset>

							<fieldset data-abmm-image-branding>
								<legend><?php esc_html_e( 'Logo', 'ashbi-mega-menu' ); ?></legend>
								<p><?php esc_html_e( 'An SVG or a PNG with a transparent background looks sharpest.', 'ashbi-mega-menu' ); ?></p>
								<div class="abmm-media-grid">
									<?php $this->render_media_field( 'primary', __( 'Light menus', 'ashbi-mega-menu' ), __( 'Logo image URL', 'ashbi-mega-menu' ), $primary, $config['logo_attachment_id'], true ); ?>
									<?php $this->render_media_field( 'dark', __( 'Dark menus (optional)', 'ashbi-mega-menu' ), __( 'Dark-menu logo image URL', 'ashbi-mega-menu' ), $dark, $config['logo_dark_attachment_id'], false ); ?>
								</div>
								<div class="abmm-branding-admin__grid abmm-branding-admin__fields">
									<label class="abmm-branding-admin__field abmm-branding-admin__field--grow"><?php esc_html_e( 'Alternative text', 'ashbi-mega-menu' ); ?><input class="regular-text" type="text" name="branding[alt]" value="<?php echo esc_attr( $config['alt'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Acme logo', 'ashbi-mega-menu' ); ?>" /><small><?php esc_html_e( 'Describe the logo for screen readers. Required.', 'ashbi-mega-menu' ); ?></small></label>
									<label class="abmm-branding-admin__field"><?php esc_html_e( 'Logo height', 'ashbi-mega-menu' ); ?><span class="abmm-branding-admin__number"><input type="number" min="16" max="80" name="branding[logo_height]" value="<?php echo esc_attr( $config['logo_height'] ); ?>" /> <span>px</span></span><small><?php esc_html_e( 'Between 16 and 80.', 'ashbi-mega-menu' ); ?></small></label>
								</div>
							</fieldset>

							<fieldset data-abmm-product-text-branding>
								<legend><?php esc_html_e( 'Product wordmark', 'ashbi-mega-menu' ); ?></legend>
								<p><?php esc_html_e( 'Optional. Use an image for the product name if it must match your logo\'s lettering.', 'ashbi-mega-menu' ); ?></p>
								<div class="abmm-media-grid">
									<?php $this->render_media_field( 'wordmark', __( 'Light menus', 'ashbi-mega-menu' ), __( 'Wordmark image URL', 'ashbi-mega-menu' ), $wordmark, $config['wordmark_attachment_id'], false ); ?>
									<?php $this->render_media_field( 'wordmark-dark', __( 'Dark menus (optional)', 'ashbi-mega-menu' ), __( 'Dark-menu wordmark image URL', 'ashbi-mega-menu' ), $wordmark_dark, $config['wordmark_dark_attachment_id'], false ); ?>
								</div>
								<div class="abmm-branding-admin__grid abmm-branding-admin__fields">
									<label class="abmm-branding-admin__field"><?php esc_html_e( 'Wordmark height', 'ashbi-mega-menu' ); ?><span class="abmm-branding-admin__number"><input type="number" min="16" max="80" name="branding[wordmark_height]" value="<?php echo esc_attr( $config['wordmark_height'] ); ?>" /> <span>px</span></span><small><?php esc_html_e( 'Usually the same height as your main logo.', 'ashbi-mega-menu' ); ?></small></label>
									<label class="abmm-branding-admin__field abmm-branding-admin__field--grow"><?php esc_html_e( 'Desktop menu behavior', 'ashbi-mega-menu' ); ?><select name="branding[desktop_interaction]"><option value="hover" <?php selected( $config['desktop_interaction'], 'hover' ); ?>><?php esc_html_e( 'Open on hover and click', 'ashbi-mega-menu' ); ?></option><option value="click" <?php selected( $config['desktop_interaction'], 'click' ); ?>><?php esc_html_e( 'Open and close on click', 'ashbi-mega-menu' ); ?></option></select></label>
								</div>
							</fieldset>

							<fieldset>
								<legend><?php esc_html_e( 'Top bar', 'ashbi-mega-menu' ); ?></legend>
								<p><?php esc_html_e( 'A slim bar of links above the menu, on desktop only.', 'ashbi-mega-menu' ); ?></p>
								<label class="abmm-branding-admin__toggle abmm-switch-row"><input class="abmm-switch" type="checkbox" name="branding[show_utility_links]" value="1" data-abmm-controls="abmm-branding-utility-fields" <?php checked( ! empty( $config['show_utility_links'] ) ); ?> /><span><strong><?php esc_html_e( 'Show About and Contact above the header', 'ashbi-mega-menu' ); ?></strong><small><?php esc_html_e( 'Both links are required while this is on.', 'ashbi-mega-menu' ); ?></small></span></label>
								<div class="abmm-branding-admin__grid abmm-branding-admin__fields abmm-dependent-fields" id="abmm-branding-utility-fields">
									<label class="abmm-branding-admin__field abmm-branding-admin__field--grow"><?php esc_html_e( 'About URL', 'ashbi-mega-menu' ); ?><input class="regular-text" type="url" name="branding[about_url]" value="<?php echo esc_attr( $config['about_url'] ); ?>" placeholder="https://example.com/about/" /></label>
									<label class="abmm-branding-admin__field abmm-branding-admin__field--grow"><?php esc_html_e( 'Contact URL', 'ashbi-mega-menu' ); ?><input class="regular-text" type="url" name="branding[contact_url]" value="<?php echo esc_attr( $config['contact_url'] ); ?>" placeholder="https://example.com/contact/" /></label>
								</div>
							</fieldset>

							<div class="abmm-branding-admin__footer">
								<p class="abmm-profile-admin__footnote"><?php esc_html_e( 'Changes show on your site as soon as you save.', 'ashbi-mega-menu' ); ?></p>
								<?php submit_button( __( 'Save product branding', 'ashbi-mega-menu' ), 'primary', 'submit', false ); ?>
							</div>
						</form>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		ABMM_Product_Profiles::print_dependent_fields_script();
	}

	/**
	 * Render one Media Library picker: a thumbnail with Replace and Remove, or
	 * an empty drop zone. The URL field stays available under "Use an image
	 * URL" for images that are not in the Media Library.
	 *
	 * @param string $key Key suffix.
	 * @param string $label Field label.
	 * @param string $url_label Accessible name of the URL field.
	 * @param string $url Current URL.
	 * @param int    $attachment_id Current attachment ID.
	 * @param bool   $is_preview Whether this is the main logo field.
	 */
	private function render_media_field( $key, $label, $url_label, $url, $attachment_id, $is_preview ) {
		$url_id   = 'abmm-product-logo-' . sanitize_html_class( $key ) . '-url';
		$id_id    = 'abmm-product-logo-' . sanitize_html_class( $key ) . '-id';
		$label_id = 'abmm-product-logo-' . sanitize_html_class( $key ) . '-label';
		$url_keys = array(
			'primary'       => 'logo_url',
			'dark'          => 'logo_dark_url',
			'wordmark'      => 'wordmark_url',
			'wordmark-dark' => 'wordmark_dark_url',
		);
		$id_keys  = array(
			'primary'       => 'logo_attachment_id',
			'dark'          => 'logo_dark_attachment_id',
			'wordmark'      => 'wordmark_attachment_id',
			'wordmark-dark' => 'wordmark_dark_attachment_id',
		);
		$url_key  = $url_keys[ $key ] ?? 'logo_url';
		$id_key   = $id_keys[ $key ] ?? 'logo_attachment_id';
		$is_dark  = in_array( $key, array( 'dark', 'wordmark-dark' ), true );
		$has      = '' !== $url;
		$targets  = ' data-abmm-media-target="' . esc_attr( $url_id ) . '" data-abmm-media-id-target="' . esc_attr( $id_id ) . '"';
		?>
		<div class="abmm-media-field<?php echo $is_dark ? ' abmm-media-field--dark' : ''; ?><?php echo $has ? ' has-image' : ''; ?>" data-abmm-media-field role="group" aria-labelledby="<?php echo esc_attr( $label_id ); ?>">
			<span class="abmm-media-field__label" id="<?php echo esc_attr( $label_id ); ?>"><?php echo esc_html( $label ); ?></span>
			<div class="abmm-media-field__preview" data-abmm-media-preview<?php echo $has ? '' : ' hidden'; ?>>
				<img src="<?php echo esc_url( $url ); ?>" alt="" data-abmm-media-image<?php echo $is_preview ? ' data-abmm-brand-preview-image' : ''; ?> />
			</div>
			<button type="button" class="abmm-media-field__drop" data-abmm-media-drop<?php echo $targets; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attribute values escaped above. ?><?php echo $has ? ' hidden' : ''; ?>>
				<span class="dashicons dashicons-format-image" aria-hidden="true"></span>
				<strong><?php esc_html_e( 'Choose an image', 'ashbi-mega-menu' ); ?></strong>
				<small><?php esc_html_e( 'or drop a file here', 'ashbi-mega-menu' ); ?></small>
			</button>
			<div class="abmm-media-field__actions" data-abmm-media-actions<?php echo $has ? '' : ' hidden'; ?>>
				<button class="button" type="button"<?php echo $targets; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attribute values escaped above. ?>><?php esc_html_e( 'Replace', 'ashbi-mega-menu' ); ?></button>
				<button class="button abmm-button-ghost" type="button" data-abmm-media-clear="<?php echo esc_attr( $url_id ); ?>" data-abmm-media-id-clear="<?php echo esc_attr( $id_id ); ?>"><?php esc_html_e( 'Remove', 'ashbi-mega-menu' ); ?></button>
			</div>
			<details class="abmm-media-field__url"<?php echo $has && ! $attachment_id ? ' open' : ''; ?>>
				<summary><?php esc_html_e( 'Use an image URL', 'ashbi-mega-menu' ); ?></summary>
				<label class="screen-reader-text" for="<?php echo esc_attr( $url_id ); ?>"><?php echo esc_html( $url_label ); ?></label>
				<input id="<?php echo esc_attr( $url_id ); ?>" class="regular-text" type="url" name="branding[<?php echo esc_attr( $url_key ); ?>]" value="<?php echo esc_attr( $url ); ?>" placeholder="https://"<?php echo $is_preview ? ' data-abmm-brand-url' : ''; ?> />
			</details>
			<input id="<?php echo esc_attr( $id_id ); ?>" type="hidden" name="branding[<?php echo esc_attr( $id_key ); ?>]" value="<?php echo esc_attr( $attachment_id ); ?>" />
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

		$profile_id = isset( $_POST['profile_id'] ) && is_scalar( $_POST['profile_id'] ) ? sanitize_key( wp_unslash( $_POST['profile_id'] ) ) : '';
		$raw = isset( $_POST['branding'] ) ? wp_unslash( $_POST['branding'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized field by field below.
		$config = $this->sanitize_config( $raw );
		$revision = isset( $_POST['revision'] ) && is_string( $_POST['revision'] ) ? sanitize_text_field( wp_unslash( $_POST['revision'] ) ) : '';
		$profile_revision = isset( $_POST['profile_revision'] ) && is_string( $_POST['profile_revision'] ) ? sanitize_text_field( wp_unslash( $_POST['profile_revision'] ) ) : '';
		$result = $this->store_config( $profile_id, $config, $revision, $profile_revision );
		if ( is_wp_error( $result ) ) {
			ABMM_Option_Store::recover( 'ashbi-mega-menu-product-branding', $profile_id, $config, $result->get_error_message() );
		}
		wp_safe_redirect( add_query_arg( '_abmm_notice', wp_create_nonce( 'abmm_branding_notice' ), admin_url( 'admin.php?page=ashbi-mega-menu-product-branding&profile=' . rawurlencode( $profile_id ) . '&updated=1' ) ) );
		exit;
	}

	/** Save branding against the exact profile and branding revisions loaded by its editor. */
	public function store_config( $profile_id, $config, $revision, $profile_revision ) {
		return ABMM_Option_Store::locked( function () use ( $profile_id, $config, $revision, $profile_revision ) {
			$profiles = ABMM_Product_Profiles::instance()->get_all();
			$all = $this->get_all();
			if ( ! isset( $profiles[ $profile_id ] ) || ! hash_equals( ABMM_Option_Store::revision( $profiles[ $profile_id ] ), $profile_revision ) || ! hash_equals( ABMM_Option_Store::revision( $all[ $profile_id ] ?? null ), $revision ) ) {
				return new WP_Error( 'abmm_product_conflict', __( 'This product profile or its branding changed in another editor. Review the latest settings before saving again.', 'ashbi-mega-menu' ) );
			}
			$config = $this->sanitize_config( $config );
		$menus  = ABMM_Data::instance()->get_all();
		if ( ! empty( $config['enabled'] ) ) {
			if ( '' === $config['shared_menu_id'] || empty( $menus[ $config['shared_menu_id'] ] ) ) {
				return new WP_Error( 'abmm_branding_invalid', $this->error_message( 'menu' ) );
			}
			$configured_logo = $this->attachment_url( $config['logo_attachment_id'], $config['logo_url'] );
			if ( 'product_text' === $config['mode'] && '' === $configured_logo && ! $this->has_shared_brand( $menus[ $config['shared_menu_id'] ]['brand'] ?? array() ) ) {
				return new WP_Error( 'abmm_branding_invalid', $this->error_message( 'shared_brand' ) );
			}
			if ( 'product_text' === $config['mode'] && '' !== $configured_logo && '' === $config['alt'] ) {
				return new WP_Error( 'abmm_branding_invalid', $this->error_message( 'alt' ) );
			}
			if ( 'image' === $config['mode'] && '' === $this->attachment_url( $config['logo_attachment_id'], $config['logo_url'] ) ) {
				return new WP_Error( 'abmm_branding_invalid', $this->error_message( 'logo' ) );
			}
			if ( 'image' === $config['mode'] && '' === $config['alt'] ) {
				return new WP_Error( 'abmm_branding_invalid', $this->error_message( 'alt' ) );
			}
			if ( ! empty( $config['show_utility_links'] ) && ( '' === $config['about_url'] || '' === $config['contact_url'] ) ) {
				return new WP_Error( 'abmm_branding_invalid', $this->error_message( 'utility_links' ) );
			}
		}

			$all[ $profile_id ] = $config;
			return ABMM_Option_Store::persist( self::OPTION_KEY, $all );
		} );
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
		wp_safe_redirect( add_query_arg( '_abmm_notice', wp_create_nonce( 'abmm_branding_notice' ), $url ) );
		exit;
	}

	/** Read save notices only when they belong to the current administrator session. */
	private function notice_state() {
		$empty = array( 'updated' => false, 'error' => '' );
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_GET['_abmm_notice'] ) || ! is_string( $_GET['_abmm_notice'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_abmm_notice'] ) ), 'abmm_branding_notice' ) ) {
			return $empty;
		}
		return array(
			'updated' => isset( $_GET['updated'] ) && '1' === $_GET['updated'],
			'error'   => isset( $_GET['error'] ) && is_string( $_GET['error'] ) ? sanitize_key( wp_unslash( $_GET['error'] ) ) : '',
		);
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
