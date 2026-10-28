<?php
/**
 * Frontend rendering of mega menus.
 *
 * @package Ashbi_Mega_Menu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABMM_Frontend {

	/**
	 * @var ABMM_Frontend|null
	 */
	private static $instance = null;

	/**
	 * @var bool
	 */
	private $assets_enqueued = false;

	/**
	 * Request-local render counter used to keep DOM IDs unique.
	 *
	 * @var int
	 */
	private $render_sequence = 0;

	/**
	 * Whether the current render is the editor's device preview.
	 *
	 * @var bool
	 */
	private $preview_mode = false;

	/**
	 * @return ABMM_Frontend
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_filter( 'script_loader_tag', array( $this, 'add_nitropack_exclusion_attribute' ), 10, 3 );
	}

	/**
	 * Keep the interactive menu script outside NitroPack's delayed resource loader.
	 *
	 * NitroPack documents the boolean nitro-exclude attribute as the developer-side
	 * mechanism for scripts that must bind before the visitor's first interaction.
	 * Other optimizers and browsers safely ignore the unknown boolean attribute.
	 *
	 * @param string $tag    Complete script tag.
	 * @param string $handle Registered WordPress script handle.
	 * @param string $src    Script source URL.
	 * @return string
	 */
	public function add_nitropack_exclusion_attribute( $tag, $handle, $src = '' ) {
		if ( 'abmm-frontend' !== $handle || false !== stripos( $tag, ' nitro-exclude' ) ) {
			return $tag;
		}

		$excluded_tag = preg_replace( '/<script\b/i', '<script nitro-exclude', $tag, 1 );
		return is_string( $excluded_tag ) ? $excluded_tag : $tag;
	}

	/**
	 * Register front assets and queue the stylesheet early enough for wp_head.
	 *
	 * Elementor renders template shortcodes after wp_head. Waiting until render()
	 * to enqueue the stylesheet makes WordPress print it in the footer, causing
	 * an unstyled header and a large layout shift on first paint. The script can
	 * remain conditional because it is intentionally loaded in the footer. Sites
	 * without any saved menus do not need the early stylesheet.
	 */
	public function register_assets() {
		wp_register_style(
			'abmm-frontend',
			ABMM_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			ABMM_VERSION
		);

		wp_register_script(
			'abmm-frontend',
			ABMM_PLUGIN_URL . 'assets/js/frontend.js',
			array(),
			ABMM_VERSION,
			true
		);
		wp_localize_script( 'abmm-frontend', 'abmmRuntime', array( 'publicDistribution' => defined( 'ABMM_PUBLIC_DISTRIBUTION' ) && ABMM_PUBLIC_DISTRIBUTION, 'searchStrings' => abmm_menu_search_strings() ) );

		if ( ABMM_Data::instance()->get_all() ) {
			wp_enqueue_style( 'abmm-frontend' );
		}
	}

	/**
	 * Enqueue assets once when a menu is rendered.
	 */
	public function enqueue_assets() {
		if ( $this->assets_enqueued ) {
			return;
		}
		wp_enqueue_style( 'abmm-frontend' );
		wp_enqueue_script( 'abmm-frontend' );
		$this->assets_enqueued = true;
	}

	/**
	 * Render a full menu by ID.
	 *
	 * @param string $menu_id Menu ID.
	 * @param array  $args    Optional args.
	 * @return string HTML
	 */
	public function render( $menu_id, $args = array() ) {
		$menu = ABMM_Data::instance()->get( $menu_id );
		if ( ! $menu ) {
			return '';
		}
		return $this->render_menu( $menu, $menu_id, $args );
	}

	/**
	 * Render unsaved menu data for the editor's device preview.
	 *
	 * Uses the same markup as the live site, plus a data attribute on each link
	 * so a click in the preview can select the matching link in the editor.
	 *
	 * @param array  $menu    Sanitized menu data.
	 * @param string $menu_id Menu ID.
	 * @return string HTML
	 */
	public function render_preview( $menu, $menu_id ) {
		$this->preview_mode = true;
		try {
			return $this->render_menu( $menu, $menu_id, array( 'presentation' => 'stacked' ) );
		} finally {
			$this->preview_mode = false;
		}
	}

	/**
	 * Render menu data.
	 *
	 * @param array  $menu    Menu data.
	 * @param string $menu_id Menu ID.
	 * @param array  $args    Optional args.
	 * @return string HTML
	 */
	private function render_menu( $menu, $menu_id, $args ) {
		$settings = wp_parse_args(
			$menu['settings'] ?? array(),
			ABMM_Data::default_settings()
		);

		// A context-aware placement always keeps the same shared menu. Only the
		// small, reusable product profile changes for the current request.
		$resolved_profile = function_exists( 'abmm_contextual_navigation_enabled' ) && abmm_contextual_navigation_enabled() && 'page' === ( $args['context'] ?? '' )
			? ABMM_Page_Context::instance()->resolve_profile()
			: array( 'profile' => null, 'source' => 'static', 'post_id' => 0 );
		$profile = $resolved_profile['profile'] ?? null;
		$requested_presentation = sanitize_key( (string) ( $args['presentation'] ?? '' ) );
		if ( '' === $requested_presentation ) {
			$requested_presentation = sanitize_key( (string) ( $settings['presentation'] ?? 'stacked' ) );
		}
		$post_id                = absint( $resolved_profile['post_id'] ?? 0 );
		$is_draft_review        = $post_id && ( 'draft' === get_post_status( $post_id ) || is_preview() );
		$presentation           = ( 'unified' === $requested_presentation && ! empty( $profile ) && $is_draft_review ) ? 'unified' : 'stacked';
		$is_unified             = 'unified' === $presentation;

		$this->enqueue_assets();

		$cta = $menu['cta'] ?? array();
		// In a unified review row, keep exactly one CTA. If the shared menu has
		// none, use the product profile CTA as the header action instead of
		// creating a second product rail action.
		if (
			$is_unified &&
			( empty( $cta['show'] ) || empty( $cta['label'] ) || empty( $cta['url'] ) ) &&
			! empty( $profile['cta']['show'] ) &&
			! empty( $profile['cta']['label'] ) &&
			! empty( $profile['cta']['url'] )
		) {
			$cta = $profile['cta'];
		}
		$brand = $menu['brand'] ?? array();
		$context = $menu['context'] ?? array();
		$utility_links = is_array( $menu['utility_links'] ?? null ) ? $menu['utility_links'] : array();
		// Product Branding injects product_text into a static shared menu before
		// this renderer runs. Treat that rendered identity like a resolved
		// product profile so mobile sizing and context suppression remain
		// consistent for shortcode/widget placements that do not pass context=page.
		$has_product_identity = $profile || ( ! empty( $brand['product_text'] ) && ! empty( $brand['show'] ) );
		$brand['use_dark_logo'] = 'dark' === ( $settings['preset'] ?? '' );
		$nav_items = $menu['items'] ?? array();
		$unified_switcher_index = $is_unified ? $this->unified_switcher_index() : -1;
		$this->render_sequence++;
		$instance_id = 'abmm-' . sanitize_html_class( $menu_id ) . '-' . $this->render_sequence;

		$classes = array(
			'abmm-header',
			'abmm-presentation--' . $presentation,
			'abmm-layout--' . sanitize_html_class( $settings['layout'] ),
			'abmm-shadow--' . sanitize_html_class( $settings['shadow'] ),
			'abmm-nav-align--' . sanitize_html_class( $settings['nav_align'] ),
			'abmm-cta--' . sanitize_html_class( $settings['cta_style'] ),
			'abmm-title--' . sanitize_html_class( $settings['panel_title_align'] ),
		);
		if ( ! empty( $settings['uppercase_cats'] ) ) {
			$classes[] = 'abmm-cats-upper';
		}
		if ( ! empty( $settings['mobile_enhancements'] ) ) {
			$classes[] = 'abmm-mobile-enhanced';
		}
		if ( empty( $settings['show_cat_desc'] ) ) {
			$classes[] = 'abmm-hide-cat-desc';
		}
		if ( ! empty( $settings['full_width'] ) ) {
			$classes[] = 'abmm-full-width';
		}
		if ( $has_product_identity ) {
			$classes[] = 'abmm-has-product-profile';
		}
		if ( ! empty( $settings['header_transparent'] ) ) {
			$classes[] = 'abmm-header--transparent';
		}
		/**
		 * Whether an open desktop panel dims the page behind it.
		 *
		 * The scrim is purely visual: clicks pass through it and close the panel
		 * like any other outside click. Return false to turn it off.
		 *
		 * @param bool   $show     Whether to show the scrim. Default true.
		 * @param string $menu_id  Menu ID.
		 * @param array  $settings Menu settings.
		 */
		if ( apply_filters( 'abmm_desktop_scrim', true, $menu_id, $settings ) ) {
			$classes[] = 'abmm-has-scrim';
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" id="<?php echo esc_attr( $instance_id ); ?>" style="<?php echo esc_attr( $this->css_vars( $settings ) ); ?>" data-abmm-id="<?php echo esc_attr( $menu_id ); ?>" data-abmm-instance="<?php echo esc_attr( $instance_id ); ?>" data-abmm-presentation="<?php echo esc_attr( $presentation ); ?>" data-abmm-desktop-open="<?php echo esc_attr( 'click' === ( $settings['desktop_interaction'] ?? '' ) ? 'click' : 'hover' ); ?>" data-abmm-back-label="<?php echo esc_attr_x( 'Back', 'mobile menu button', 'ashbi-mega-menu' ); ?>">
			<?php echo $this->render_utility_links( $utility_links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<nav class="abmm-nav" aria-label="<?php echo esc_attr( $menu['title'] ); ?>">
				<div class="abmm-nav__bar">
					<?php echo $this->render_brand( $brand, 'bar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php echo $this->render_product_context( $has_product_identity ? array() : $context, 'bar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php if ( ! empty( $cta['show'] ) && ! empty( $cta['label'] ) ) : ?>
						<a <?php echo $this->link_attrs( $cta, 'abmm-nav__cta abmm-nav__cta--bar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> href="<?php echo esc_url( $cta['url'] ?: '#' ); ?>">
							<?php echo esc_html( $cta['label'] ); ?>
						</a>
					<?php endif; ?>

					<?php // The toggle sits at the far right, after the CTA, and turns into the close button in place. ?>
					<button type="button" class="abmm-nav__toggle" aria-expanded="false" aria-controls="<?php echo esc_attr( $instance_id ); ?>-drawer" aria-label="<?php esc_attr_e( 'Open menu', 'ashbi-mega-menu' ); ?>">
						<span class="abmm-nav__toggle-box" aria-hidden="true">
							<span class="abmm-nav__toggle-bar"></span>
						</span>
					</button>
				</div>

				<div class="abmm-nav__drawer" id="<?php echo esc_attr( $instance_id ); ?>-drawer">
					<div class="abmm-nav__drawer-head">
						<span class="abmm-nav__drawer-title"><?php esc_html_e( 'Menu', 'ashbi-mega-menu' ); ?></span>
						<button type="button" class="abmm-nav__drawer-close" data-abmm-drawer-close aria-label="<?php esc_attr_e( 'Close menu', 'ashbi-mega-menu' ); ?>">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="abmm-nav__inner">
						<?php echo $this->render_brand( $brand, 'drawer' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo $this->render_product_context( $has_product_identity ? array() : $context, 'drawer' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo $this->render_product_navigation( $profile, 'drawer', $cta, $brand ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<ul class="abmm-nav__list">
							<?php foreach ( $nav_items as $item_index => $item ) : ?>
								<?php $unified_role = $is_unified ? ( $item_index === $unified_switcher_index ? 'switcher' : 'secondary' ) : ''; ?>
								<?php echo $this->render_nav_item( $item, $settings, $instance_id, $item_index, $unified_role ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php endforeach; ?>
						</ul>

						<?php if ( $is_unified ) : ?>
							<?php echo $this->render_product_navigation( $profile, 'unified', array(), $brand ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php endif; ?>

						<?php if ( ! empty( $cta['show'] ) && ! empty( $cta['label'] ) ) : ?>
							<?php // On phones this block sticks to the bottom of the drawer; on desktop it is just the bar's CTA. ?>
							<div class="abmm-nav__drawer-foot">
								<a <?php echo $this->link_attrs( $cta, 'abmm-nav__cta abmm-nav__cta--drawer' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> href="<?php echo esc_url( $cta['url'] ?: '#' ); ?>">
									<?php echo esc_html( $cta['label'] ); ?>
								</a>
							</div>
						<?php endif; ?>
					</div>
				</div>

				<div class="abmm-nav__backdrop" data-abmm-backdrop hidden></div>
			</nav>
			<?php if ( ! $is_unified ) : ?>
				<?php echo $this->render_product_navigation( $profile, 'rail', $cta, $brand ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render the optional site identity. Disabled menus retain their existing output.
	 *
	 * @param array  $brand    Brand data.
	 * @param string $location bar or drawer.
	 * @return string
	 */
	private function render_brand( $brand, $location ) {
		if ( ! empty( $brand['product_text'] ) && ! empty( $brand['show'] ) ) {
			$product_text = $brand['product_text'];
			$product_url  = $brand['product_url'] ?? '';
			$product_wordmark_url = ! empty( $brand['use_dark_logo'] ) && ! empty( $brand['product_wordmark_dark_url'] )
				? $brand['product_wordmark_dark_url']
				: ( $brand['product_wordmark_url'] ?? '' );
			$product_wordmark_height = min( 80, max( 16, absint( $brand['product_wordmark_height'] ?? $brand['logo_height'] ?? 40 ) ) );
			unset( $brand['product_text'], $brand['product_url'], $brand['product_wordmark_url'], $brand['product_wordmark_dark_url'], $brand['product_wordmark_height'] );
			$corporate = $this->render_brand( $brand, $location );
			if ( '' === $corporate ) {
				return '';
			}
			$product_content = esc_html( $product_text );
			$product_class = 'abmm-nav__product-name';
			if ( '' !== $product_wordmark_url ) {
				$product_class .= ' abmm-nav__product-name--wordmark';
				$product_content = sprintf(
					'<img class="abmm-nav__product-wordmark" src="%1$s" alt="%2$s" style="--abmm-product-wordmark-height:%3$dpx" />',
					esc_url( $product_wordmark_url ),
					esc_attr( $product_text ),
					$product_wordmark_height
				);
			}
			$product = '<span class="' . esc_attr( $product_class ) . '">' . $product_content . '</span>';
			if ( '' !== $product_url ) {
				$product = '<a class="' . esc_attr( $product_class ) . '" href="' . esc_url( $product_url ) . '">' . $product_content . '</a>';
			}
			return '<div class="abmm-nav__identity abmm-nav__identity--' . esc_attr( $location ) . '">' . $corporate . $product . '</div>';
		}
		if ( empty( $brand['show'] ) || ( empty( $brand['logo_url'] ) && empty( $brand['logo_dark_url'] ) && empty( $brand['title'] ) ) ) {
			return '';
		}

		$content = '';
		$logo_url = ! empty( $brand['use_dark_logo'] ) && ! empty( $brand['logo_dark_url'] )
			? $brand['logo_dark_url']
			: ( $brand['logo_url'] ?? '' );
		if ( ! empty( $logo_url ) ) {
			$alt = '' !== ( $brand['alt'] ?? '' ) ? $brand['alt'] : ( $brand['title'] ?? '' );
			$content .= sprintf(
				'<img class="abmm-nav__brand-logo" src="%1$s" alt="%2$s" style="--abmm-brand-logo-height:%3$dpx" />',
				esc_url( $logo_url ),
				esc_attr( $alt ),
				min( 80, max( 16, absint( $brand['logo_height'] ?? 40 ) ) )
			);
			if ( empty( $brand['title'] ) && '' !== $alt ) {
				$content .= '<span class="abmm-nav__brand-fallback" aria-hidden="true">' . esc_html( $alt ) . '</span>';
			}
		}
		if ( ! empty( $brand['title'] ) ) {
			$content .= '<span class="abmm-nav__brand-title">' . esc_html( $brand['title'] ) . '</span>';
		}

		$class = 'abmm-nav__brand abmm-nav__brand--' . sanitize_html_class( $location );
		if ( ! empty( $brand['url'] ) ) {
			return sprintf(
				'<a %1$s href="%2$s">%3$s</a>',
				$this->link_attrs( $brand, $class ),
				esc_url( $brand['url'] ),
				$content
			);
		}

		return '<span class="' . esc_attr( $class ) . '">' . $content . '</span>';
	}

	/**
	 * Render optional profile-scoped corporate links above the desktop header.
	 *
	 * @param array $links Utility links.
	 * @return string
	 */
	private function render_utility_links( $links ) {
		$items = array();
		foreach ( $links as $link ) {
			$label = is_array( $link ) ? sanitize_text_field( $link['label'] ?? '' ) : '';
			$url   = is_array( $link ) ? esc_url( $link['url'] ?? '' ) : '';
			if ( '' !== $label && '' !== $url ) {
				$items[] = '<a href="' . $url . '">' . esc_html( $label ) . '</a>';
			}
		}
		if ( empty( $items ) ) {
			return '';
		}
		return '<nav class="abmm-nav__utility" aria-label="' . esc_attr( __( 'Corporate links', 'ashbi-mega-menu' ) ) . '"><div class="abmm-nav__utility-inner">' . implode( '<span aria-hidden="true">|</span>', $items ) . '</div></nav>';
	}

	/**
	 * Render the optional current-product label beside the shared brand.
	 *
	 * @param array  $context  Product context data.
	 * @param string $location bar or drawer.
	 * @return string
	 */
	private function render_product_context( $context, $location ) {
		if ( empty( $context['show'] ) || empty( $context['label'] ) ) {
			return '';
		}

		$class   = 'abmm-nav__context abmm-nav__context--' . sanitize_html_class( $location );
		$content = '<span class="screen-reader-text">' . esc_html__( 'Current product: ', 'ashbi-mega-menu' ) . '</span><span class="abmm-nav__context-label">' . esc_html( $context['label'] ) . '</span>';
		if ( ! empty( $context['url'] ) ) {
			return sprintf(
				'<a %1$s href="%2$s">%3$s</a>',
				$this->link_attrs( $context, $class ),
				esc_url( $context['url'] ),
				$content
			);
		}

		return '<span class="' . esc_attr( $class ) . '">' . $content . '</span>';
	}

	/**
	 * Render product-local wayfinding separately from the shared site header.
	 * The rail deliberately contains no second logo or corporate links.
	 *
	 * @param array|null $profile Product profile.
	 * @param string     $location rail, drawer, or unified.
	 * @param array      $shared_cta Shared header CTA.
	 * @param array      $brand      Resolved shared/product brand data.
	 * @return string
	 */
	private function render_product_navigation( $profile, $location, $shared_cta = array(), $brand = array() ) {
		if ( empty( $profile ) || empty( $profile['label'] ) ) {
			return '';
		}

		$is_drawer = 'drawer' === $location;
		$is_unified = 'unified' === $location;
		if ( $is_unified && empty( $profile['links'] ) ) {
			return '';
		}
		$rail_style = in_array( $profile['rail_style'] ?? '', array( 'underline', 'pills' ), true ) ? $profile['rail_style'] : 'underline';
		$class     = ( $is_drawer ? 'abmm-product-nav abmm-product-nav--drawer' : ( $is_unified ? 'abmm-product-nav abmm-product-nav--unified' : 'abmm-product-nav abmm-product-nav--rail' ) ) . ' abmm-product-nav--' . $rail_style;
		$label     = sprintf(
/* translators: %s: Product name. */
__( '%s navigation', 'ashbi-mega-menu' ), $profile['label'] );
		$show_cta  = ! $is_unified && $this->should_render_product_cta( $profile, $shared_cta );
		ob_start();
		?>
		<nav class="<?php echo esc_attr( $class ); ?>" aria-label="<?php echo esc_attr( $label ); ?>">
			<div class="abmm-product-nav__inner">
				<?php if ( ! $is_unified ) : ?>
					<?php
					$identity = '<span class="screen-reader-text">' . esc_html__( 'Current product: ', 'ashbi-mega-menu' ) . '</span>' . esc_html( $profile['label'] );
					if ( 'rail' === $location ) {
						$product_wordmark_url = ! empty( $brand['use_dark_logo'] ) && ! empty( $brand['product_wordmark_dark_url'] )
							? $brand['product_wordmark_dark_url']
							: ( $brand['product_wordmark_url'] ?? '' );
						if ( '' !== $product_wordmark_url ) {
							$product_wordmark_height = min( 80, max( 16, absint( $brand['product_wordmark_height'] ?? $brand['logo_height'] ?? 40 ) ) );
							$identity = sprintf(
								'<span class="screen-reader-text">%1$s</span><img class="abmm-product-nav__wordmark" src="%2$s" alt="%3$s" style="--abmm-product-wordmark-height:%4$dpx" />',
								esc_html__( 'Current product: ', 'ashbi-mega-menu' ),
								esc_url( $product_wordmark_url ),
								esc_attr( $profile['label'] ),
								$product_wordmark_height
							);
						}
					}
					?>
					<?php if ( ! empty( $profile['url'] ) ) : ?>
						<a class="abmm-product-nav__identity" href="<?php echo esc_url( $profile['url'] ); ?>"<?php echo $this->is_current_url( $profile['url'] ) ? ' aria-current="page"' : ''; ?>><?php echo $identity; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
					<?php else : ?>
						<span class="abmm-product-nav__identity"><?php echo $identity; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<?php endif; ?>
				<?php endif; ?>
				<?php if ( ! empty( $profile['links'] ) ) : ?>
					<ul class="abmm-product-nav__list">
						<?php foreach ( $profile['links'] as $link ) : ?>
							<li><a class="abmm-product-nav__link<?php echo $this->is_current_url( $link['url'] ?? '' ) ? ' is-current' : ''; ?>" href="<?php echo esc_url( $link['url'] ?? '' ); ?>"<?php echo $this->is_current_url( $link['url'] ?? '' ) ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $link['label'] ?? '' ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if ( $show_cta ) : ?>
					<a class="abmm-product-nav__cta" href="<?php echo esc_url( $profile['cta']['url'] ); ?>"><?php echo esc_html( $profile['cta']['label'] ); ?></a>
				<?php endif; ?>
			</div>
		</nav>
		<?php
		return ob_get_clean();
	}

	/**
	 * A product rail should not repeat the global conversion action. Editors can
	 * still intentionally use a local CTA when it represents a different task.
	 *
	 * @param array $profile Product profile.
	 * @param array $shared_cta Shared header CTA.
	 * @return bool
	 */
	private function should_render_product_cta( $profile, $shared_cta ) {
		$product_cta = $profile['cta'] ?? array();
		if ( empty( $product_cta['show'] ) || empty( $product_cta['label'] ) || empty( $product_cta['url'] ) ) {
			return false;
		}

		if ( empty( $shared_cta['show'] ) || empty( $shared_cta['label'] ) || empty( $shared_cta['url'] ) ) {
			return true;
		}

		return strtolower( trim( (string) $product_cta['label'] ) ) !== strtolower( trim( (string) $shared_cta['label'] ) )
			|| rtrim( (string) $product_cta['url'], '/' ) !== rtrim( (string) $shared_cta['url'], '/' );
	}

	/**
	 * Build safe attributes shared by every rendered link.
	 *
	 * @param array  $link       Link record.
	 * @param string $base_class Required component classes.
	 * @return string
	 */
	private function link_attrs( $link, $base_class ) {
		$is_current = $this->is_current_url( $link['url'] ?? '' );
		$classes = trim( $base_class . ' ' . ( $link['class'] ?? '' ) . ( $is_current ? ' is-current' : '' ) );
		$attrs   = 'class="' . esc_attr( $classes ) . '"';
		if ( '_blank' === ( $link['target'] ?? '' ) ) {
			$attrs .= ' target="_blank"';
		}
		if ( ! empty( $link['rel'] ) ) {
			$attrs .= ' rel="' . esc_attr( $link['rel'] ) . '"';
		}
		if ( $is_current ) {
			$attrs .= ' aria-current="page"';
		}
		return $attrs;
	}

	/**
	 * Determine whether an internal menu URL represents the current request.
	 *
	 * @param string $url Candidate URL.
	 * @return bool
	 */
	private function is_current_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url || '#' === $url || 0 === strpos( $url, '#' ) ) {
			return false;
		}

		$home = wp_parse_url( home_url( '/' ) );
		$target = wp_parse_url( $url );
		if ( false === $target ) {
			return false;
		}
		if ( ! empty( $target['host'] ) && strtolower( $target['host'] ) !== strtolower( $home['host'] ?? '' ) ) {
			return false;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$current = wp_parse_url( home_url( $request_uri ) );
		$target_path = '/' . trim( $target['path'] ?? '/', '/' );
		$current_path = '/' . trim( $current['path'] ?? '/', '/' );
		if ( $target_path !== $current_path ) {
			return false;
		}

		parse_str( $target['query'] ?? '', $target_query );
		parse_str( $current['query'] ?? '', $current_query );
		if ( empty( $target_query ) ) {
			// With plain permalinks every page lives at "/?page_id=…", so a bare
			// home link only matches when the request names no other content.
			$routing = array( 'p', 'page_id', 'pagename', 'name', 'cat', 'tag', 'tag_id', 'category_name', 'author', 'author_name', 'post_type', 'attachment_id', 's', 'm', 'year', 'monthnum', 'day' );
			return ! array_intersect_key( $current_query, array_flip( $routing ) );
		}
		ksort( $target_query );
		ksort( $current_query );
		return $target_query === $current_query;
	}

	/**
	 * Check whether a mega item contains the current destination.
	 *
	 * @param array $item Item data.
	 * @return bool
	 */
	private function item_has_current_descendant( $item ) {
		foreach ( $item['links'] ?? array() as $link ) {
			if ( $this->is_current_url( $link['url'] ?? '' ) ) {
				return true;
			}
		}
		foreach ( $item['categories'] ?? array() as $category ) {
			if ( $this->category_has_current_descendant( $category ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check whether a category contains the current destination.
	 *
	 * @param array $category Category data.
	 * @return bool
	 */
	private function category_has_current_descendant( $category ) {
		if ( $this->is_current_url( $category['panel_url'] ?? '' ) ) {
			return true;
		}
		foreach ( $this->category_groups( $category ) as $group ) {
			if ( $this->is_current_url( $group['url'] ?? '' ) ) {
				return true;
			}
			foreach ( $group['links'] ?? array() as $link ) {
				if ( $this->is_current_url( $link['url'] ?? '' ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Build CSS custom properties string.
	 *
	 * @param array $settings Settings.
	 * @return string
	 */
	private function css_vars( $settings ) {
		$s = wp_parse_args( $settings, ABMM_Data::default_settings() );
		return sprintf(
			'--abmm-sidebar-bg:%1$s;--abmm-active-bg:%2$s;--abmm-panel-bg:%3$s;--abmm-header-bg:%4$s;--abmm-accent:%5$s;--abmm-text:%6$s;--abmm-nav-link:%7$s;--abmm-nav-hover:%8$s;--abmm-muted:%9$s;--abmm-border:%10$s;--abmm-cta-text:%11$s;--abmm-grid-cols:%12$d;--abmm-panel-width:%13$dpx;--abmm-sidebar-width:%14$dpx;--abmm-radius:%15$dpx;--abmm-icon-default:%16$s;--abmm-icon-default-hover:%17$s;--abmm-icon-default-active:%18$s;--abmm-icon-default-size:%19$dpx;--abmm-icon-default-background:%20$s;--abmm-icon-default-border:%21$s;--abmm-icon-default-radius:%22$dpx;--abmm-z-index:%23$d;',
			$s['sidebar_bg'],
			$s['active_bg'],
			$s['panel_bg'],
			$s['header_bg'],
			$s['accent'],
			$s['text_color'],
			$s['nav_link_color'] ?? $s['text_color'],
			$s['nav_hover_color'] ?? $s['accent'],
			$s['muted_color'],
			$s['border_color'],
			$s['cta_text'],
			(int) $s['grid_columns'],
			(int) $s['panel_width'],
			(int) $s['sidebar_width'],
			(int) $s['border_radius'],
			! empty( $s['icon_inherit_text'] ) ? 'currentColor' : $s['icon_color'],
			$s['icon_hover_color'],
			$s['icon_active_color'],
			(int) $s['icon_size'],
			$s['icon_background'] ?: 'transparent',
			$s['icon_border_color'] ?: 'transparent',
			(int) $s['icon_radius'],
			(int) $s['header_z_index']
		);
	}

	/**
	 * Build optional section-level CSS variables.
	 *
	 * @param array $style Section style data.
	 * @return string
	 */
	private function icon_override_vars( $style ) {
		$style = is_array( $style ) ? $style : array();
		$vars = array();
		$map  = array(
			'icon_color'        => '--abmm-icon-color',
			'icon_hover_color'  => '--abmm-icon-hover',
			'icon_active_color' => '--abmm-icon-active',
			'icon_background'   => '--abmm-icon-background',
			'icon_border_color' => '--abmm-icon-border',
		);
		foreach ( $map as $field => $variable ) {
			$value = is_scalar( $style[ $field ] ?? null ) ? sanitize_hex_color( (string) $style[ $field ] ) : null;
			if ( $value ) {
				$vars[] = $variable . ':' . $value;
			}
		}
		$size = absint( $style['icon_size'] ?? 0 );
		if ( $size ) {
			$vars[] = '--abmm-icon-size:' . min( 64, max( 12, $size ) ) . 'px';
		}
		if ( isset( $style['icon_radius'] ) && is_scalar( $style['icon_radius'] ) && '' !== $style['icon_radius'] ) {
			$vars[] = '--abmm-icon-radius:' . min( 24, max( 0, absint( $style['icon_radius'] ) ) ) . 'px';
		}
		return $vars ? implode( ';', $vars ) . ';' : '';
	}

	/**
	 * Render one top-level nav item (link or mega).
	 *
	 * @param array $item     Item data.
	 * @param array $settings Menu settings.
	 * @param string $instance_id Unique render-instance prefix.
	 * @param int    $item_index  Item position within this instance.
	 * @param string $unified_role Optional switcher or secondary role in draft unified mode.
	 * @return string
	 */
	private function render_nav_item( $item, $settings, $instance_id, $item_index, $unified_role = '' ) {
		$is_mega             = ( 'mega' === ( $item['type'] ?? '' ) );
		$is_compact_mega     = $is_mega && $this->is_compact_mega_item( $item );
		$is_current          = $this->is_current_url( $item['url'] ?? '' );
		$has_current         = $is_mega && $this->item_has_current_descendant( $item );
		$display_label       = 'switcher' === $unified_role ? __( 'Products', 'ashbi-mega-menu' ) : ( $item['label'] ?? '' );
		$classes             = 'abmm-nav__item' . ( $is_mega ? ' abmm-nav__item--mega' : '' ) . ( $is_compact_mega ? ' abmm-nav__item--compact-mega' : '' ) . ( $is_current ? ' is-current' : '' ) . ( $has_current ? ' is-current-ancestor' : '' );
		if ( in_array( $unified_role, array( 'switcher', 'secondary' ), true ) ) {
			$classes .= ' abmm-nav__item--unified-' . $unified_role;
		}
		$item_id = sanitize_html_class( $item['id'] ?? uniqid( 'i' ) );
		$uid     = $instance_id . '-item-' . absint( $item_index ) . '-' . $item_id;

		ob_start();
		?>
		<li class="<?php echo esc_attr( $classes ); ?>" data-abmm-item-id="<?php echo esc_attr( $item_id ); ?>">
			<?php if ( $is_mega ) : ?>
				<button
					type="button"
					class="abmm-nav__link abmm-nav__link--toggle"
					aria-expanded="false"
					aria-controls="<?php echo esc_attr( $uid ); ?>-panel"
					data-abmm-mega-trigger
				>
					<?php echo esc_html( $display_label ); ?>
					<svg class="abmm-chevron" width="12" height="12" viewBox="0 0 12 12" aria-hidden="true"><path d="M2.5 4.5L6 8l3.5-3.5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</button>
				<?php echo $this->render_mega_panel( $item, $settings, $uid ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php else : ?>
				<a <?php echo $this->link_attrs( $item, 'abmm-nav__link' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> href="<?php echo esc_url( $item['url'] ?: '#' ); ?>">
					<?php echo esc_html( $display_label ); ?>
				</a>
			<?php endif; ?>
		</li>
		<?php
		return ob_get_clean();
	}

	/**
	 * Contextual layouts do not infer behavior from a user-visible label.
	 *
	 * @return int Item index, or -1 when no mega item exists.
	 */
	private function unified_switcher_index() {
		return -1;
	}

	/**
	 * Whether a Platforms item is actually a compact one-column dropdown.
	 *
	 * @param array $item Menu item data.
	 * @return bool
	 */
	private function is_compact_mega_item( $item ) {
		if ( 'features' === ( $item['mega_style'] ?? 'platforms' ) ) {
			return 1 === min( 4, max( 1, absint( $item['columns'] ?? 2 ) ) );
		}

		if ( empty( $item['hide_category_bar'] ) ) {
			return false;
		}

		$categories = $item['categories'] ?? array();
		if ( 1 !== count( $categories ) ) {
			return false;
		}

		return 1 >= count( $this->category_groups( $categories[0] ) );
	}

	/**
	 * Render mega dropdown panel (sidebar + grid).
	 *
	 * @param array $item     Mega item.
	 * @param array $settings Settings.
	 * @param string $uid      Unique item prefix.
	 * @return string
	 */
	private function render_mega_panel( $item, $settings, $uid ) {
		$style = $item['mega_style'] ?? 'platforms';
		if ( 'features' === $style ) {
			return $this->render_features_panel( $item, $settings, $uid );
		}
		return $this->render_platforms_panel( $item, $settings, $uid );
	}

	/**
	 * Platforms mega: left sidebar categories + right feature grid.
	 *
	 * @param array $item     Mega item.
	 * @param array $settings Settings.
	 * @param string $uid      Unique item prefix.
	 * @return string
	 */
	private function render_platforms_panel( $item, $settings, $uid ) {
		$categories      = $item['categories'] ?? array();
		$icons           = ABMM_Icons::instance();
		$columns_only    = ! empty( $item['hide_category_bar'] ) && 1 === count( $categories );
		$columns_compact = $this->is_compact_mega_item( $item );
		// One category needs no category rail: its columns use the full width.
		$single_category = 1 === count( $categories );
		$featured        = $this->render_featured_card( $item );
		// Several categories collapse to an accordion on phones; the first starts open.
		$collapsible     = count( $categories ) > 1 && ! $columns_only;

		ob_start();
		?>
		<div class="abmm-mega abmm-mega--platforms<?php echo $columns_only ? ' abmm-mega--columns-only' : ''; ?><?php echo $columns_compact ? ' abmm-mega--columns-compact' : ''; ?><?php echo $single_category ? ' abmm-mega--single' : ''; ?><?php echo '' !== $featured ? ' abmm-mega--has-featured' : ''; ?>" id="<?php echo esc_attr( $uid ); ?>-panel" hidden data-abmm-mega-panel data-abmm-style="platforms">
			<div class="abmm-mega__inner">
				<?php if ( ! empty( $categories ) ) : ?>
					<div class="abmm-mega__accordion" style="--abmm-section-count: <?php echo (int) max( 1, count( $categories ) ); ?>">
						<?php foreach ( $categories as $index => $cat ) : ?>
							<?php
							$cat_id = sanitize_html_class( $cat['id'] ?? 'cat' . $index );
							$cat_uid = $uid . '-cat-' . absint( $index ) . '-' . $cat_id;
							$active = 0 === $index;
							$has_current = $this->category_has_current_descendant( $cat );
							?>
							<section class="abmm-mega__section<?php echo $active ? ' is-open' : ''; ?><?php echo $collapsible ? ' abmm-mega__section--collapsible' : ''; ?><?php echo $has_current ? ' is-current-ancestor' : ''; ?>" data-abmm-section="<?php echo esc_attr( $cat_id ); ?>" style="<?php echo esc_attr( $this->icon_override_vars( $cat ) ); ?>">
								<button
									type="button"
									class="abmm-mega__cat<?php echo $active ? ' is-active' : ''; ?>"
									id="<?php echo esc_attr( $cat_uid ); ?>-trigger"
									aria-expanded="<?php echo $active ? 'true' : 'false'; ?>"
									aria-controls="<?php echo esc_attr( $cat_uid ); ?>-panel"
									data-abmm-cat="<?php echo esc_attr( $cat_id ); ?>"
								>
									<span class="abmm-mega__cat-icon">
										<?php echo $icons->render( $cat['icon'] ?? '', $cat['icon_url'] ?? '', 'abmm-icon abmm-icon--lg', $cat ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									</span>
									<span class="abmm-mega__cat-text">
										<span class="abmm-mega__cat-title"><?php echo esc_html( $cat['title'] ); ?></span>
										<?php if ( ! empty( $cat['description'] ) ) : ?>
											<span class="abmm-mega__cat-desc"><?php echo esc_html( $cat['description'] ); ?></span>
										<?php endif; ?>
									</span>
									<span class="abmm-mega__cat-chevron" aria-hidden="true">
										<svg width="14" height="14" viewBox="0 0 12 12"><path d="M2.5 4.5L6 8l3.5-3.5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
									</span>
								</button>

								<div
									class="abmm-mega__panel<?php echo $active ? ' is-active' : ''; ?>"
									id="<?php echo esc_attr( $cat_uid ); ?>-panel"
									role="region"
									aria-labelledby="<?php echo esc_attr( $cat_uid ); ?>-trigger"
									data-abmm-panel="<?php echo esc_attr( $cat_id ); ?>"
									<?php echo $active ? '' : 'hidden'; ?>
								>
									<?php
									// Phone layout: every category is a heading followed by its links in one
									// scroll view. With several categories the heading is an accordion button.
									$section_body_ids = $cat_uid . '-links' . ( ! empty( $cat['panel_title'] ) ? ' ' . $cat_uid . '-more' : '' );
									?>
									<div class="abmm-mega__section-title">
										<?php if ( $collapsible ) : ?>
											<button type="button" class="abmm-mega__section-toggle" aria-expanded="true" aria-controls="<?php echo esc_attr( $section_body_ids ); ?>" data-abmm-section-toggle>
										<?php endif; ?>
										<span class="abmm-mega__section-icon" aria-hidden="true"><?php echo $icons->render( $cat['icon'] ?? '', $cat['icon_url'] ?? '', 'abmm-icon abmm-icon--sm', $cat ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										<span class="abmm-mega__section-label"><?php echo esc_html( $cat['title'] ); ?></span>
										<?php if ( $collapsible ) : ?>
												<svg class="abmm-mega__section-chevron" width="16" height="16" viewBox="0 0 12 12" aria-hidden="true" focusable="false"><path d="M2.5 4.5L6 8l3.5-3.5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
											</button>
										<?php endif; ?>
									</div>

									<?php echo $this->render_category_columns( $cat, $cat_uid . '-links' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

									<?php if ( ! empty( $cat['panel_title'] ) ) : ?>
										<?php // The "see all" link closes the panel as a footer strip, after the links it summarises. ?>
										<p class="abmm-mega__panel-title" id="<?php echo esc_attr( $cat_uid . '-more' ); ?>">
											<?php if ( ! empty( $cat['panel_url'] ) ) : ?>
												<a <?php echo $this->link_attrs( array_merge( $cat['panel_link'] ?? $cat, array( 'url' => $cat['panel_url'] ) ), '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> href="<?php echo esc_url( $cat['panel_url'] ); ?>"><?php echo esc_html( $cat['panel_title'] ); ?></a>
											<?php else : ?>
												<?php echo esc_html( $cat['panel_title'] ); ?>
											<?php endif; ?>
										</p>
									<?php endif; ?>
								</div>
							</section>
						<?php endforeach; ?>
						<?php echo $featured; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render the optional featured card of a mega item.
	 *
	 * Desktop shows it as a column on the right of the panel; phones show it as
	 * a compact card at the end of the item's screen.
	 *
	 * @param array $item Mega item.
	 * @return string Empty when the card is off or has no title.
	 */
	private function render_featured_card( $item ) {
		$card = is_array( $item['featured'] ?? null ) ? $item['featured'] : array();
		$title = trim( (string) ( $card['title'] ?? '' ) );
		if ( empty( $card['show'] ) || '' === $title ) {
			return '';
		}

		$text  = trim( (string) ( $card['text'] ?? '' ) );
		$url   = trim( (string) ( $card['url'] ?? '' ) );
		$image = trim( (string) ( $card['image_url'] ?? '' ) );
		$label = trim( (string) ( $card['label'] ?? '' ) );
		$has_link = '' !== $url && '#' !== $url;
		if ( $has_link && '' === $label ) {
			$label = __( 'Learn more', 'ashbi-mega-menu' );
		}

		$html = '';
		if ( '' !== $image ) {
			// Decorative: the title next to it carries the meaning.
			$html .= '<span class="abmm-mega__featured-media"><img src="' . esc_url( $image ) . '" alt="" loading="lazy" decoding="async" /></span>';
		}
		$html .= '<span class="abmm-mega__featured-body">';
		$html .= '<span class="abmm-mega__featured-title">' . esc_html( $title ) . '</span>';
		if ( '' !== $text ) {
			$html .= '<span class="abmm-mega__featured-text">' . esc_html( $text ) . '</span>';
		}
		if ( $has_link ) {
			$html .= '<span class="abmm-mega__featured-link">' . esc_html( $label ) . '<svg width="14" height="14" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M3 8h9.5M8.5 4l4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>';
		}
		$html .= '</span>';

		if ( $has_link ) {
			return '<a ' . $this->link_attrs( array( 'url' => $url ), 'abmm-mega__featured' ) . ' href="' . esc_url( $url ) . '">' . $html . '</a>';
		}
		return '<div class="abmm-mega__featured">' . $html . '</div>';
	}

	/**
	 * Normalize category groups (columns), including legacy flat links.
	 *
	 * @param array $cat Category data.
	 * @return array
	 */
	private function category_groups( $cat ) {
		if ( ! empty( $cat['groups'] ) && is_array( $cat['groups'] ) ) {
			return $cat['groups'];
		}

		if ( ! empty( $cat['links'] ) && is_array( $cat['links'] ) ) {
			return array(
				array(
					'id'    => 'legacy',
					'title' => '',
					'links' => $cat['links'],
				),
			);
		}

		return array();
	}

	/**
	 * Render column groups inside a category panel.
	 *
	 * @param array  $cat Category data.
	 * @param string $id  Optional element ID for the columns wrapper.
	 * @return string
	 */
	private function render_category_columns( $cat, $id = '' ) {
		$groups = $this->category_groups( $cat );
		if ( empty( $groups ) ) {
			return '';
		}

		$count = count( $groups );
		ob_start();
		?>
		<div class="abmm-mega__columns"<?php echo '' !== $id ? ' id="' . esc_attr( $id ) . '"' : ''; ?> style="--abmm-col-count: <?php echo (int) $count; ?>">
			<?php foreach ( $groups as $group ) : ?>
				<?php $col_title_id = ! empty( $group['title'] ) ? ( function_exists( 'wp_unique_id' ) ? wp_unique_id( 'abmm-col-title-' ) : uniqid( 'abmm-col-title-' ) ) : ''; ?>
				<div class="abmm-mega__col">
					<?php if ( ! empty( $group['title'] ) ) : ?>
						<div class="abmm-mega__col-title" id="<?php echo esc_attr( $col_title_id ); ?>">
							<?php if ( ! empty( $group['url'] ) ) : ?>
								<a <?php echo $this->link_attrs( $group, '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> href="<?php echo esc_url( $group['url'] ); ?>"><?php echo esc_html( $group['title'] ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $group['title'] ); ?>
							<?php endif; ?>
						</div>
					<?php endif; ?>
					<ul class="abmm-mega__col-list"<?php echo $col_title_id ? ' aria-labelledby="' . esc_attr( $col_title_id ) . '"' : ''; ?>>
						<?php foreach ( ( $group['links'] ?? array() ) as $link ) : ?>
							<?php echo $this->render_col_link( $link ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render one column list link.
	 *
	 * @param array $link Link data.
	 * @return string
	 */
	private function render_col_link( $link ) {
		$icons     = ABMM_Icons::instance();
		$has_icon  = ! empty( $link['icon'] ) || ! empty( $link['icon_url'] );
		$has_url   = ! empty( $link['url'] );
		ob_start();
		?>
		<li class="abmm-mega__col-item"<?php echo $this->preview_link_attr( $link ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php if ( $has_url ) : ?>
				<a <?php echo $this->link_attrs( $link, 'abmm-mega__col-link' . ( $has_icon ? ' has-icon' : '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> href="<?php echo esc_url( $link['url'] ); ?>">
			<?php else : ?>
				<span class="abmm-mega__col-link is-disabled<?php echo $has_icon ? ' has-icon' : ''; ?>" aria-disabled="true">
			<?php endif; ?>
				<?php if ( $has_icon ) : ?>
					<span class="abmm-mega__col-icon">
						<?php echo $icons->render( $link['icon'] ?? '', $link['icon_url'] ?? '', 'abmm-icon abmm-icon--sm', $link ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</span>
				<?php endif; ?>
				<span class="abmm-mega__col-copy">
					<span class="abmm-mega__col-label"><?php echo esc_html( $link['label'] ); ?></span>
					<?php if ( ! empty( $link['description'] ) ) : ?>
						<span class="abmm-mega__col-description"><?php echo esc_html( $link['description'] ); ?></span>
					<?php endif; ?>
				</span>
			<?php echo $has_url ? '</a>' : '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</li>
		<?php
		return ob_get_clean();
	}

	/**
	 * Features mega: simple multi-column icon + label grid (no sidebar).
	 *
	 * @param array $item     Mega item.
	 * @param array $settings Settings.
	 * @param string $uid      Unique item prefix.
	 * @return string
	 */
	private function render_features_panel( $item, $settings, $uid ) {
		$links         = $item['links'] ?? array();
		$cols          = min( 4, max( 1, (int) ( $item['columns'] ?? $settings['grid_columns'] ?? 3 ) ) );
		$compact_class = 1 === $cols ? ' abmm-mega--compact' : '';
		$featured      = $this->render_featured_card( $item );
		if ( '' !== $featured ) {
			$compact_class .= ' abmm-mega--has-featured';
		}

		ob_start();
		?>
		<div class="abmm-mega abmm-mega--features<?php echo esc_attr( $compact_class ); ?>" id="<?php echo esc_attr( $uid ); ?>-panel" hidden data-abmm-mega-panel data-abmm-style="features">
			<div class="abmm-mega__inner abmm-mega__inner--features">
				<ul class="abmm-mega__grid abmm-mega__grid--features" style="--abmm-grid-cols: <?php echo (int) $cols; ?>">
					<?php foreach ( $links as $link ) : ?>
						<?php echo $this->render_grid_link( $link ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endforeach; ?>
				</ul>
				<?php echo $featured; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Preview-only link ID attribute, empty on the live site.
	 *
	 * @param array $link Link data.
	 * @return string Escaped attribute with a leading space, or ''.
	 */
	private function preview_link_attr( $link ) {
		if ( ! $this->preview_mode || empty( $link['id'] ) ) {
			return '';
		}
		return ' data-abmm-link-id="' . esc_attr( (string) $link['id'] ) . '"';
	}

	/**
	 * Render one grid link row.
	 *
	 * @param array $link Link data.
	 * @return string
	 */
	private function render_grid_link( $link ) {
		$icons   = ABMM_Icons::instance();
		$has_url = ! empty( $link['url'] );
		ob_start();
		?>
		<li class="abmm-mega__grid-item"<?php echo $this->preview_link_attr( $link ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php if ( $has_url ) : ?>
				<a <?php echo $this->link_attrs( $link, 'abmm-mega__grid-link' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> href="<?php echo esc_url( $link['url'] ); ?>">
			<?php else : ?>
				<span class="abmm-mega__grid-link is-disabled" aria-disabled="true">
			<?php endif; ?>
				<span class="abmm-mega__grid-icon">
					<?php echo $icons->render( $link['icon'] ?? '', $link['icon_url'] ?? '', 'abmm-icon abmm-icon--sm', $link ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
				<span class="abmm-mega__grid-label"><?php echo esc_html( $link['label'] ); ?></span>
			<?php echo $has_url ? '</a>' : '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</li>
		<?php
		return ob_get_clean();
	}
}
