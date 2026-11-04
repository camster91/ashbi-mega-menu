<?php
/**
 * Classic mode: renders a menu with Easy Mega Menu's markup, styles and script.
 *
 * Menus imported from Easy Mega Menu use this mode so visitors see exactly the
 * same header: the same `emm-` classes and `--emm-*` variables, so any site CSS
 * written for Easy Mega Menu keeps matching. The markup is a port of Easy Mega
 * Menu 1.6.76 (GPL, same author). Menus created in Ashbi never use it.
 *
 * @package Ashbi_Mega_Menu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABMM_Classic {

	/**
	 * Settings value that switches a menu to this renderer.
	 */
	const RENDER_MODE = 'emm-classic';

	/**
	 * Option recording the site's classic assets copied by the importer.
	 */
	const ASSETS_OPTION = 'abmm_classic_assets';

	/**
	 * Upload subfolder for copied classic assets.
	 */
	const UPLOAD_DIR = 'ashbi-mega-menu/classic';

	/**
	 * @var ABMM_Classic|null
	 */
	private static $instance = null;

	/**
	 * @var bool
	 */
	private $assets_enqueued = false;

	/**
	 * Request-local render counter, separate from Ashbi's, so instance IDs
	 * match what Easy Mega Menu printed (`emm-<id>-1`).
	 *
	 * @var int
	 */
	private $render_sequence = 0;

	/**
	 * Whether the menu being rendered uses plain elements instead of banner and headings.
	 *
	 * @var bool
	 */
	private $plain_markup = false;

	/**
	 * Whether the current render is for the editor's preview frame.
	 *
	 * @var bool
	 */
	private $preview_mode = false;

	/**
	 * @var ABMM_Classic_Icons|null
	 */
	private $icons = null;

	/**
	 * @return ABMM_Classic
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		// Block themes render the page, and so the menu, before this hook runs.
		add_action( 'wp_enqueue_scripts', array( $this, 'defer_to_source_stylesheet' ), 999 );
		add_filter( 'script_loader_tag', array( $this, 'add_nitropack_exclusion' ), 10, 2 );
		add_filter( 'style_loader_tag', array( $this, 'add_nitropack_exclusion' ), 10, 2 );
		add_filter( 'body_class', array( $this, 'body_class' ) );
	}

	/**
	 * Whether a stored menu renders in classic mode.
	 *
	 * @param array $menu Menu data.
	 * @return bool
	 */
	public static function is_classic( $menu ) {
		return is_array( $menu ) && self::RENDER_MODE === ( $menu['settings']['render_mode'] ?? '' );
	}

	/**
	 * Whether any stored menu uses classic mode.
	 *
	 * @return bool
	 */
	public static function site_uses_classic() {
		foreach ( ABMM_Data::instance()->get_all() as $menu ) {
			if ( self::is_classic( $menu ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether Easy Mega Menu itself is running in this request.
	 *
	 * @return bool
	 */
	public static function source_active() {
		return defined( 'EMM_VERSION' ) || class_exists( 'EMM_Data', false );
	}

	/**
	 * Easy Mega Menu's own setting defaults. Classic menus fall back to these,
	 * not to Ashbi's, so a missing value renders as it did before.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'preset'            => 'classic',
			'presentation'      => 'stacked',
			'layout'            => 'sidebar-left',
			'sidebar_bg'        => '#f0f0f5',
			'active_bg'         => '#ffffff',
			'panel_bg'          => '#ffffff',
			'header_bg'         => '#ffffff',
			'header_transparent' => false,
			'header_z_index'    => 1000,
			'accent'            => '#1a73e8',
			'text_color'        => '#2c2c2c',
			'nav_link_color'    => '#2c2c2c',
			'nav_hover_color'   => '#1a73e8',
			'muted_color'       => '#6b6b6b',
			'border_color'      => '#e5e5ea',
			'cta_text'          => '#ffffff',
			'icon_inherit_text' => true,
			'icon_color'        => '#2c2c2c',
			'icon_hover_color'  => '#1a73e8',
			'icon_active_color' => '#1a73e8',
			'icon_size'         => 22,
			'icon_background'   => '',
			'icon_border_color' => '',
			'icon_radius'       => 0,
			'grid_columns'      => 3,
			'panel_width'       => 1000,
			'full_width'        => true,
			'sidebar_width'     => 280,
			'border_radius'     => 8,
			'shadow'            => 'medium',
			'nav_align'         => 'center',
			'cta_style'         => 'rounded',
			'uppercase_cats'    => true,
			'show_cat_desc'     => true,
			'panel_title_align' => 'center',
		);
	}

	/**
	 * Site assets (stylesheet and sprite file names) copied by the importer.
	 *
	 * @return array{css:string,sprite:string,version:string,keys:string[]}
	 */
	public function site_assets() {
		$stored = get_option( self::ASSETS_OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		return array(
			'css'     => is_string( $stored['css'] ?? null ) ? sanitize_file_name( $stored['css'] ) : '',
			'sprite'  => is_string( $stored['sprite'] ?? null ) ? sanitize_file_name( $stored['sprite'] ) : '',
			'version' => is_string( $stored['version'] ?? null ) ? preg_replace( '/[^A-Za-z0-9._-]/', '', $stored['version'] ) : '',
			'keys'    => array_values( array_filter( array_map( 'sanitize_key', array_filter( (array) ( $stored['keys'] ?? array() ), 'is_scalar' ) ) ) ),
		);
	}

	/**
	 * Absolute path of the classic upload folder, or '' when uploads are unavailable.
	 *
	 * @return string
	 */
	public static function upload_dir() {
		if ( ! function_exists( 'wp_upload_dir' ) ) {
			return '';
		}
		$uploads = wp_upload_dir( null, false );
		return empty( $uploads['error'] ) ? trailingslashit( $uploads['basedir'] ) . self::UPLOAD_DIR : '';
	}

	/**
	 * Public URL of a copied classic file, or '' when it is missing.
	 *
	 * @param string $file   File name inside the folder.
	 * @param bool   $origin_relative Return a root-relative path (for SVG <use>, which must stay same-origin when a CDN rewrites asset hosts).
	 * @return string
	 */
	public function upload_url( $file, $origin_relative = false ) {
		$dir = self::upload_dir();
		if ( '' === $file || '' === $dir || ! file_exists( $dir . '/' . $file ) ) {
			return '';
		}
		$uploads = wp_upload_dir( null, false );
		$url     = trailingslashit( $uploads['baseurl'] ) . self::UPLOAD_DIR . '/' . $file;
		if ( $origin_relative ) {
			$path = wp_parse_url( $url, PHP_URL_PATH );
			return is_string( $path ) ? $path : $url;
		}
		return $url;
	}

	/**
	 * Register classic assets. The stylesheet is queued early, as Easy Mega Menu
	 * did, so Elementor headers rendered after wp_head are styled on first paint,
	 * but only on sites that have a classic menu.
	 */
	public function register_assets() {
		$site    = $this->site_assets();
		$css_url = $this->upload_url( $site['css'] );
		wp_register_style(
			'abmm-classic',
			$css_url ? $css_url : ABMM_PLUGIN_URL . 'assets/classic/emm-frontend.css',
			array(),
			$css_url && $site['version'] ? $site['version'] : ABMM_VERSION
		);
		wp_register_style(
			'abmm-classic-fixes',
			ABMM_PLUGIN_URL . 'assets/classic/emm-fixes.css',
			array( 'abmm-classic' ),
			ABMM_VERSION
		);
		wp_register_script(
			'abmm-classic',
			ABMM_PLUGIN_URL . 'assets/classic/emm-frontend.js',
			array(),
			ABMM_VERSION,
			true
		);
		$summary = $this->classic_summary();
		if ( $summary['classic'] ) {
			$config = array( 'i18n' => self::script_strings() );
			if ( $summary['bleed'] ) {
				$config['edgeBleed'] = $summary['bleed'];
			}
			wp_add_inline_script( 'abmm-classic', 'window.abmmClassic=' . wp_json_encode( $config ) . ';', 'before' );
		}
		if ( $summary['phone_fix'] ) {
			wp_add_inline_style( 'abmm-classic', self::phone_scroll_fix_css() );
		}
		$style_css = ABMM_Style::site_css( true );
		if ( '' !== $style_css ) {
			wp_add_inline_style( 'abmm-classic', $style_css );
		}
		// While Easy Mega Menu is active its own stylesheet (the same rules) is
		// already queued, so live pages stay byte for byte as they were.
		if ( $summary['classic'] && ! self::source_active() ) {
			wp_enqueue_style( 'abmm-classic' );
			wp_enqueue_style( 'abmm-classic-fixes' );
		}
	}

	/**
	 * Words the classic script creates itself, in the site's language.
	 *
	 * @return array<string,string>
	 */
	private static function script_strings() {
		return array(
			'back'       => __( 'Back', 'ashbi-mega-menu' ),
			/* translators: %s: where the Back button returns to, such as a section name. */
			'backTo'     => __( 'Back to %s', 'ashbi-mega-menu' ),
			'backToMenu' => __( 'Back to menu', 'ashbi-mega-menu' ),
			'openMenu'   => __( 'Open menu', 'ashbi-mega-menu' ),
			'closeMenu'  => __( 'Close menu', 'ashbi-mega-menu' ),
			'newTab'     => __( '(opens in a new tab)', 'ashbi-mega-menu' ),
		);
	}

	/**
	 * What the stored menus need from the classic assets, in one pass:
	 * whether any menu is classic, per-menu panel edge bleed, and whether any
	 * classic menu turns on the phone scroll fix.
	 *
	 * @return array{classic:bool,bleed:array<string,int>,phone_fix:bool}
	 */
	private function classic_summary() {
		$summary = array( 'classic' => false, 'bleed' => array(), 'phone_fix' => false );
		foreach ( ABMM_Data::instance()->get_all() as $id => $menu ) {
			if ( ! self::is_classic( $menu ) ) {
				continue;
			}
			$summary['classic'] = true;
			$bleed              = absint( $menu['settings']['classic_edge_bleed'] ?? 0 );
			if ( $bleed ) {
				$summary['bleed'][ (string) $id ] = min( 64, $bleed );
			}
			$summary['phone_fix'] = $summary['phone_fix'] || ! empty( $menu['settings']['classic_phone_scroll_fix'] );
		}
		return $summary;
	}

	/**
	 * Optional fix for long phone submenus. In Easy Mega Menu's phone drawer a
	 * category's link list is its own scroll box, sized to the screen but
	 * starting below the drawer's header, so its last links end up below the
	 * screen and cannot be scrolled into view. This lets the list grow and the
	 * drawer scroll instead. Printed only when a menu turns it on.
	 *
	 * @return string
	 */
	public static function phone_scroll_fix_css() {
		$rule = '.emm-header.emm-phone-scroll-fix .emm-mega__panel{max-height:none!important;overflow:visible!important}';
		return '@media (max-width:900px){' . $rule . '}' . str_replace( '.emm-header.emm-phone-scroll-fix', '.emm-header.emm-force-mobile.emm-phone-scroll-fix', $rule );
	}

	/**
	 * Render unsaved menu data for the editor's preview frames: the live markup
	 * plus link IDs, so a click on a link selects it in the editor.
	 *
	 * @param array  $menu    Menu being previewed (may be unsaved).
	 * @param string $menu_id ID the preview renders with.
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
	 * Stylesheet, script and extra CSS for the editor's preview frames.
	 *
	 * @param array  $menu    Menu being previewed (may be unsaved).
	 * @param string $menu_id ID the preview renders with.
	 * @return array{css:string,js:string,inlineCss:string}
	 */
	public function preview_assets( $menu, $menu_id = 'preview' ) {
		$site    = $this->site_assets();
		$css_url = $this->upload_url( $site['css'] );
		return array(
			'css'       => $css_url ? add_query_arg( 'ver', $site['version'] ? $site['version'] : ABMM_VERSION, $css_url ) : add_query_arg( 'ver', ABMM_VERSION, ABMM_PLUGIN_URL . 'assets/classic/emm-frontend.css' ),
			'js'        => add_query_arg( 'ver', ABMM_VERSION, ABMM_PLUGIN_URL . 'assets/classic/emm-frontend.js' ),
			'inlineCss' => ( ! empty( $menu['settings']['classic_phone_scroll_fix'] ) ? self::phone_scroll_fix_css() : '' )
				. ABMM_Style::css( $menu['settings']['style'] ?? array(), ABMM_Style::scope( $menu_id, 'emm' ), 'emm' ),
		);
	}

	/**
	 * Enqueue assets once when a classic menu renders.
	 */
	public function enqueue_assets() {
		if ( $this->assets_enqueued ) {
			return;
		}
		$this->defer_to_source_stylesheet();
		wp_enqueue_style( 'abmm-classic' );
		// While Easy Mega Menu is active, test pages stay as they will look with its
		// styles alone, apart from what the script does.
		if ( ! self::source_active() ) {
			wp_enqueue_style( 'abmm-classic-fixes' );
		}
		wp_enqueue_script( 'abmm-classic' );
		$this->assets_enqueued = true;
	}

	/**
	 * Easy Mega Menu's stylesheet (the same rules) loads in the head, before the
	 * theme's. Linking the copy later would let it beat the theme's styles, so a
	 * test page would look unlike the site after the switch. While that stylesheet
	 * is on the page, only this one's inline CSS is printed.
	 */
	public function defer_to_source_stylesheet() {
		if ( self::source_active() && ( wp_style_is( 'emm-frontend', 'enqueued' ) || wp_style_is( 'emm-frontend', 'done' ) ) && isset( wp_styles()->registered['abmm-classic'] ) ) {
			wp_styles()->registered['abmm-classic']->src = false;
		}
	}

	/**
	 * Keep the classic stylesheet and script out of NitroPack's delayed loader,
	 * exactly as Easy Mega Menu did. Other optimizers ignore the attribute.
	 *
	 * @param string $tag    Complete tag.
	 * @param string $handle Registered handle.
	 * @return string
	 */
	public function add_nitropack_exclusion( $tag, $handle ) {
		if ( ! in_array( $handle, array( 'abmm-classic', 'abmm-classic-fixes' ), true ) || false !== stripos( $tag, ' nitro-exclude' ) ) {
			return $tag;
		}
		$excluded = 0 === stripos( ltrim( $tag ), '<link' )
			? preg_replace( '#\s*/>#', ' nitro-exclude />', $tag, 1 )
			// The tag includes the inline settings script, which must not be the only one marked.
			: preg_replace( '/<script\b/i', '<script nitro-exclude', $tag );
		return is_string( $excluded ) ? $excluded : $tag;
	}

	/**
	 * Keep Easy Mega Menu's product-branding body classes for site CSS that
	 * targets them. Only applies when product branding is active on the page.
	 *
	 * @param array $classes Body classes.
	 * @return array
	 */
	public function body_class( $classes ) {
		// Easy Mega Menu adds these itself while it is active.
		if ( self::source_active() || ! class_exists( 'ABMM_Product_Branding' ) || ! abmm_contextual_navigation_enabled() || ! self::site_uses_classic() ) {
			return $classes;
		}
		$resolved = ABMM_Product_Branding::instance()->resolve_for_request();
		if ( ! empty( $resolved['config']['enabled'] ) ) {
			$classes[] = 'emm-product-branding-active';
			$classes[] = 'emm-product-branding-' . sanitize_html_class( $resolved['profile_id'] );
		}
		return $classes;
	}

	/**
	 * @return ABMM_Classic_Icons
	 */
	private function icons() {
		if ( null === $this->icons ) {
			$this->icons = new ABMM_Classic_Icons( $this );
		}
		return $this->icons;
	}

	/**
	 * Render menu data with Easy Mega Menu's markup.
	 *
	 * @param array  $menu    Menu data, already loaded and filtered by the caller.
	 * @param string $menu_id Menu ID.
	 * @param array  $args    context, presentation, review_post_id.
	 * @return string HTML
	 */
	public function render_menu( $menu, $menu_id, $args = array() ) {
		$settings = wp_parse_args(
			$menu['settings'] ?? array(),
			self::default_settings()
		);

		// A context-aware placement always keeps the same shared menu. Only the
		// small, reusable product profile changes for the current request.
		$resolved_profile = 'page' === ( $args['context'] ?? '' ) && class_exists( 'ABMM_Page_Context' ) && abmm_contextual_navigation_enabled()
			? ABMM_Page_Context::instance()->resolve_profile()
			: array( 'profile' => null, 'source' => 'static', 'post_id' => 0 );
		$profile = $resolved_profile['profile'] ?? null;
		$requested_presentation = sanitize_key( (string) ( $args['presentation'] ?? '' ) );
		if ( '' === $requested_presentation ) {
			$requested_presentation = sanitize_key( (string) ( $settings['presentation'] ?? 'stacked' ) );
		}
		$post_id                = absint( $resolved_profile['post_id'] ?? 0 );
		// review_post_id approves the unified review row on that one published page.
		$review_post_id         = absint( $args['review_post_id'] ?? 0 );
		$is_draft_review        = $post_id && ( 'draft' === get_post_status( $post_id ) || is_preview() || $review_post_id === $post_id );
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
		$unified_switcher_index = $is_unified ? $this->unified_switcher_index( $nav_items ) : -1;
		$this->render_sequence++;
		$instance_id = 'emm-' . sanitize_html_class( $menu_id ) . '-' . $this->render_sequence;

		/**
		 * Render this menu without banner and heading elements: a div instead of
		 * header, and div titles instead of h3 and h4. Off by default so imported
		 * menus keep Easy Mega Menu's exact markup. Defaults to the menu's setting.
		 *
		 * @param bool   $plain   Whether to use plain elements.
		 * @param string $menu_id Menu ID.
		 */
		$this->plain_markup = (bool) apply_filters( 'abmm_classic_plain_markup', ! empty( $menu['settings']['classic_plain_markup'] ), $menu_id );
		$wrapper_tag        = $this->plain_markup ? 'div' : 'header';

		/**
		 * Visible words beside this menu's phone button, for pages that stack two
		 * menus and so show two identical buttons. Defaults to the menu's setting.
		 *
		 * @param string $label   Label text.
		 * @param string $menu_id Menu ID.
		 */
		$toggle_label = trim( (string) apply_filters( 'abmm_classic_toggle_label', $menu['settings']['classic_toggle_label'] ?? '', $menu_id ) );

		/**
		 * Padding of this menu's phone bar, such as "0" or "8px 16px". Beats a site
		 * override whatever order an optimizer loads the stylesheets in. Empty keeps
		 * the stylesheet's padding. Defaults to the menu's setting.
		 *
		 * @param string $padding CSS padding.
		 * @param string $menu_id Menu ID.
		 */
		$bar_padding = trim( (string) apply_filters( 'abmm_classic_bar_padding', $menu['settings']['classic_bar_padding'] ?? '', $menu_id ) );
		$bar_padding = preg_match( '/^[0-9.a-z%\s-]+$/i', $bar_padding ) ? $bar_padding : '';
		$header_vars = $this->css_vars( $settings ) . ( '' !== $bar_padding ? ';--emm-bar-padding:' . $bar_padding : '' );

		$classes = array(
			'emm-header',
			'emm-presentation--' . $presentation,
			'emm-layout--' . sanitize_html_class( $settings['layout'] ),
			'emm-shadow--' . sanitize_html_class( $settings['shadow'] ),
			'emm-nav-align--' . sanitize_html_class( $settings['nav_align'] ),
			'emm-cta--' . sanitize_html_class( $settings['cta_style'] ),
			'emm-title--' . sanitize_html_class( $settings['panel_title_align'] ),
		);
		if ( ! empty( $settings['uppercase_cats'] ) ) {
			$classes[] = 'emm-cats-upper';
		}
		if ( empty( $settings['show_cat_desc'] ) ) {
			$classes[] = 'emm-hide-cat-desc';
		}
		if ( ! empty( $settings['full_width'] ) ) {
			$classes[] = 'emm-full-width';
		}
		if ( $has_product_identity ) {
			$classes[] = 'emm-has-product-profile';
		}
		if ( ! empty( $settings['header_transparent'] ) ) {
			$classes[] = 'emm-header--transparent';
		}
		// Ashbi addition, off unless chosen: see phone_scroll_fix_css().
		if ( ! empty( $settings['classic_phone_scroll_fix'] ) ) {
			$classes[] = 'emm-phone-scroll-fix';
		}

		ob_start();
		?>
		<<?php echo esc_html( $wrapper_tag ); ?> class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" id="<?php echo esc_attr( $instance_id ); ?>" style="<?php echo esc_attr( $header_vars ); ?>" data-emm-id="<?php echo esc_attr( $menu_id ); ?>" data-emm-instance="<?php echo esc_attr( $instance_id ); ?>" data-emm-presentation="<?php echo esc_attr( $presentation ); ?>" data-emm-desktop-open="<?php echo esc_attr( 'click' === ( $settings['desktop_interaction'] ?? '' ) ? 'click' : 'hover' ); ?>">
			<?php echo $this->render_utility_links( $utility_links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<nav class="emm-nav" aria-label="<?php echo esc_attr( $menu['title'] ); ?>">
				<div class="emm-nav__bar">
					<?php echo $this->render_brand( $brand, 'bar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php echo $this->render_product_context( $has_product_identity ? array() : $context, 'bar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<button type="button" class="emm-nav__toggle" aria-expanded="false" aria-controls="<?php echo esc_attr( $instance_id ); ?>-drawer" aria-label="<?php echo esc_attr( ( '' !== $toggle_label ? $toggle_label . ': ' : '' ) . __( 'Open menu', 'ashbi-mega-menu' ) ); ?>">
						<span class="emm-nav__toggle-box" aria-hidden="true">
							<span class="emm-nav__toggle-bar"></span>
						</span><?php echo ( '' !== $toggle_label ? '<span class="emm-nav__toggle-label">' . esc_html( $toggle_label ) . '</span>' : '' ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</button>

					<?php if ( ! empty( $cta['show'] ) && ! empty( $cta['label'] ) ) : ?>
						<a <?php echo $this->link_attrs( $cta, 'emm-nav__cta emm-nav__cta--bar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> href="<?php echo esc_url( $cta['url'] ?: '#' ); ?>">
							<?php echo esc_html( $cta['label'] ); ?>
						</a>
					<?php endif; ?>
				</div>

				<div class="emm-nav__drawer" id="<?php echo esc_attr( $instance_id ); ?>-drawer">
					<div class="emm-nav__drawer-head">
						<span class="emm-nav__drawer-title"><?php esc_html_e( 'Menu', 'ashbi-mega-menu' ); ?></span>
						<button type="button" class="emm-nav__drawer-close" data-emm-drawer-close aria-label="<?php esc_attr_e( 'Close menu', 'ashbi-mega-menu' ); ?>">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="emm-nav__inner">
						<?php echo $this->render_brand( $brand, 'drawer' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo $this->render_product_context( $has_product_identity ? array() : $context, 'drawer' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo $this->render_product_navigation( $profile, 'drawer', $cta, $brand ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<ul class="emm-nav__list">
							<?php foreach ( $nav_items as $item_index => $item ) : ?>
								<?php $unified_role = $is_unified ? ( $item_index === $unified_switcher_index ? 'switcher' : 'secondary' ) : ''; ?>
								<?php echo $this->render_nav_item( $item, $settings, $instance_id, $item_index, $unified_role ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php endforeach; ?>
						</ul>

						<?php if ( $is_unified ) : ?>
							<?php echo $this->render_product_navigation( $profile, 'unified', array(), $brand ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php endif; ?>

						<?php if ( ! empty( $cta['show'] ) && ! empty( $cta['label'] ) ) : ?>
							<a <?php echo $this->link_attrs( $cta, 'emm-nav__cta emm-nav__cta--drawer' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> href="<?php echo esc_url( $cta['url'] ?: '#' ); ?>">
								<?php echo esc_html( $cta['label'] ); ?>
							</a>
						<?php endif; ?>
					</div>
				</div>

				<div class="emm-nav__backdrop" data-emm-backdrop hidden></div>
			</nav>
			<?php if ( ! $is_unified ) : ?>
				<?php echo $this->render_product_navigation( $profile, 'rail', $cta, $brand ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
		</<?php echo esc_html( $wrapper_tag ); ?>>
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
			$product_class = 'emm-nav__product-name';
			if ( '' !== $product_wordmark_url ) {
				$product_class .= ' emm-nav__product-name--wordmark';
				$product_content = sprintf(
					'<img class="emm-nav__product-wordmark" src="%1$s" alt="%2$s" style="--emm-product-wordmark-height:%3$dpx" />',
					esc_url( $product_wordmark_url ),
					esc_attr( $product_text ),
					$product_wordmark_height
				);
			}
			$product = '<span class="' . esc_attr( $product_class ) . '">' . $product_content . '</span>';
			if ( '' !== $product_url ) {
				$product = '<a class="' . esc_attr( $product_class ) . '" href="' . esc_url( $product_url ) . '">' . $product_content . '</a>';
			}
			return '<div class="emm-nav__identity emm-nav__identity--' . esc_attr( $location ) . '">' . $corporate . $product . '</div>';
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
			// A linked logo is the link's only content, so it always needs a name.
			if ( '' === $alt && ! empty( $brand['url'] ) ) {
				$alt = (string) get_bloginfo( 'name' );
			}
			$content .= sprintf(
				'<img class="emm-nav__brand-logo" src="%1$s" alt="%2$s" style="--emm-brand-logo-height:%3$dpx" />',
				esc_url( $logo_url ),
				esc_attr( $alt ),
				min( 80, max( 16, absint( $brand['logo_height'] ?? 40 ) ) )
			);
			if ( empty( $brand['title'] ) && '' !== $alt ) {
				$content .= '<span class="emm-nav__brand-fallback" aria-hidden="true">' . esc_html( $alt ) . '</span>';
			}
		}
		if ( ! empty( $brand['title'] ) ) {
			$content .= '<span class="emm-nav__brand-title">' . esc_html( $brand['title'] ) . '</span>';
		}

		$class = 'emm-nav__brand emm-nav__brand--' . sanitize_html_class( $location );
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
		return '<nav class="emm-nav__utility" aria-label="' . esc_attr( __( 'Corporate links', 'ashbi-mega-menu' ) ) . '"><div class="emm-nav__utility-inner">' . implode( '<span aria-hidden="true">|</span>', $items ) . '</div></nav>';
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

		$class   = 'emm-nav__context emm-nav__context--' . sanitize_html_class( $location );
		$content = '<span class="screen-reader-text">' . esc_html__( 'Current product: ', 'ashbi-mega-menu' ) . '</span><span class="emm-nav__context-label">' . esc_html( $context['label'] ) . '</span>';
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
		$class     = ( $is_drawer ? 'emm-product-nav emm-product-nav--drawer' : ( $is_unified ? 'emm-product-nav emm-product-nav--unified' : 'emm-product-nav emm-product-nav--rail' ) ) . ' emm-product-nav--' . $rail_style;
		$label     = sprintf( /* translators: %s: Product name. */ __( '%s navigation', 'ashbi-mega-menu' ), $profile['label'] );
		$show_cta  = ! $is_unified && $this->should_render_product_cta( $profile, $shared_cta );
		ob_start();
		?>
		<nav class="<?php echo esc_attr( $class ); ?>" aria-label="<?php echo esc_attr( $label ); ?>">
			<div class="emm-product-nav__inner">
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
								'<span class="screen-reader-text">%1$s</span><img class="emm-product-nav__wordmark" src="%2$s" alt="%3$s" style="--emm-product-wordmark-height:%4$dpx" />',
								esc_html__( 'Current product: ', 'ashbi-mega-menu' ),
								esc_url( $product_wordmark_url ),
								esc_attr( $profile['label'] ),
								$product_wordmark_height
							);
						}
					}
					?>
					<?php if ( ! empty( $profile['url'] ) ) : ?>
						<a class="emm-product-nav__identity" href="<?php echo esc_url( $profile['url'] ); ?>"<?php echo $this->is_current_url( $profile['url'] ) ? ' aria-current="page"' : ''; ?>><?php echo $identity; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
					<?php else : ?>
						<span class="emm-product-nav__identity"><?php echo $identity; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<?php endif; ?>
				<?php endif; ?>
				<?php if ( ! empty( $profile['links'] ) ) : ?>
					<ul class="emm-product-nav__list">
						<?php foreach ( $profile['links'] as $link ) : ?>
							<li><a class="emm-product-nav__link<?php echo $this->is_current_url( $link['url'] ?? '' ) ? ' is-current' : ''; ?>" href="<?php echo esc_url( $link['url'] ?? '' ); ?>"<?php echo $this->is_current_url( $link['url'] ?? '' ) ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $link['label'] ?? '' ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if ( $show_cta ) : ?>
					<a class="emm-product-nav__cta" href="<?php echo esc_url( $profile['cta']['url'] ); ?>"><?php echo esc_html( $profile['cta']['label'] ); ?></a>
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

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/';
		$current = wp_parse_url( home_url( $request_uri ) );
		$target_path = '/' . trim( $target['path'] ?? '/', '/' );
		$current_path = '/' . trim( $current['path'] ?? '/', '/' );
		if ( $target_path !== $current_path ) {
			return false;
		}

		if ( empty( $target['query'] ) ) {
			return true;
		}
		parse_str( $target['query'], $target_query );
		parse_str( $current['query'] ?? '', $current_query );
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
		$s = wp_parse_args( $settings, self::default_settings() );
		return sprintf(
			'--emm-sidebar-bg:%1$s;--emm-active-bg:%2$s;--emm-panel-bg:%3$s;--emm-header-bg:%4$s;--emm-accent:%5$s;--emm-text:%6$s;--emm-nav-link:%7$s;--emm-nav-hover:%8$s;--emm-muted:%9$s;--emm-border:%10$s;--emm-cta-text:%11$s;--emm-grid-cols:%12$d;--emm-panel-width:%13$dpx;--emm-sidebar-width:%14$dpx;--emm-radius:%15$dpx;--emm-icon-default:%16$s;--emm-icon-default-hover:%17$s;--emm-icon-default-active:%18$s;--emm-icon-default-size:%19$dpx;--emm-icon-default-background:%20$s;--emm-icon-default-border:%21$s;--emm-icon-default-radius:%22$dpx;--emm-z-index:%23$d;',
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
			'icon_color'        => '--emm-icon-color',
			'icon_hover_color'  => '--emm-icon-hover',
			'icon_active_color' => '--emm-icon-active',
			'icon_background'   => '--emm-icon-background',
			'icon_border_color' => '--emm-icon-border',
		);
		foreach ( $map as $field => $variable ) {
			$value = is_scalar( $style[ $field ] ?? null ) ? sanitize_hex_color( (string) $style[ $field ] ) : null;
			if ( $value ) {
				$vars[] = $variable . ':' . $value;
			}
		}
		$size = absint( $style['icon_size'] ?? 0 );
		if ( $size ) {
			$vars[] = '--emm-icon-size:' . min( 64, max( 12, $size ) ) . 'px';
		}
		if ( isset( $style['icon_radius'] ) && is_scalar( $style['icon_radius'] ) && '' !== $style['icon_radius'] ) {
			$vars[] = '--emm-icon-radius:' . min( 24, max( 0, absint( $style['icon_radius'] ) ) ) . 'px';
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
		$classes             = 'emm-nav__item' . ( $is_mega ? ' emm-nav__item--mega' : '' ) . ( $is_compact_mega ? ' emm-nav__item--compact-mega' : '' ) . ( $is_current ? ' is-current' : '' ) . ( $has_current ? ' is-current-ancestor' : '' );
		if ( in_array( $unified_role, array( 'switcher', 'secondary' ), true ) ) {
			$classes .= ' emm-nav__item--unified-' . $unified_role;
		}
		$item_id = sanitize_html_class( $item['id'] ?? uniqid( 'i' ) );
		$uid     = $instance_id . '-item-' . absint( $item_index ) . '-' . $item_id;

		ob_start();
		?>
		<li class="<?php echo esc_attr( $classes ); ?>" data-emm-item-id="<?php echo esc_attr( $item_id ); ?>">
			<?php if ( $is_mega ) : ?>
				<button
					type="button"
					class="emm-nav__link emm-nav__link--toggle"
					aria-expanded="false"
					aria-controls="<?php echo esc_attr( $uid ); ?>-panel"
					data-emm-mega-trigger
				>
					<?php echo esc_html( $display_label ); ?>
					<svg class="emm-chevron" width="12" height="12" viewBox="0 0 12 12" aria-hidden="true"><path d="M2.5 4.5L6 8l3.5-3.5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</button>
				<?php echo $this->render_mega_panel( $item, $settings, $uid ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php else : ?>
				<a <?php echo $this->link_attrs( $item, 'emm-nav__link' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> href="<?php echo esc_url( $item['url'] ?: '#' ); ?>">
					<?php echo esc_html( $display_label ); ?>
				</a>
			<?php endif; ?>
		</li>
		<?php
		return ob_get_clean();
	}

	/**
	 * Pick the existing Platforms mega item as the product-family switcher.
	 *
	 * The label match keeps the draft treatment aligned with the approved
	 * information architecture. Falling back to the first mega item avoids a
	 * brittle empty row when an editor renames that label.
	 *
	 * @param array $items Top-level shared navigation items.
	 * @return int Item index, or -1 when no mega item exists.
	 */
	private function unified_switcher_index( $items ) {
		$fallback = -1;
		foreach ( $items as $index => $item ) {
			if ( 'mega' !== ( $item['type'] ?? '' ) ) {
				continue;
			}
			if ( -1 === $fallback ) {
				$fallback = $index;
			}
			if ( 'platforms' === sanitize_key( $item['label'] ?? '' ) ) {
				return $index;
			}
		}
		return $fallback;
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
		$icons           = $this->icons();
		$columns_only    = ! empty( $item['hide_category_bar'] ) && 1 === count( $categories );
		$columns_compact = $this->is_compact_mega_item( $item );

		ob_start();
		?>
		<div class="emm-mega emm-mega--platforms<?php echo $columns_only ? ' emm-mega--columns-only' : ''; ?><?php echo $columns_compact ? ' emm-mega--columns-compact' : ''; ?>" id="<?php echo esc_attr( $uid ); ?>-panel" hidden data-emm-mega-panel data-emm-style="platforms">
			<div class="emm-mega__inner">
				<?php if ( ! empty( $categories ) ) : ?>
					<div class="emm-mega__accordion" style="--emm-section-count: <?php echo (int) max( 1, count( $categories ) ); ?>">
						<?php foreach ( $categories as $index => $cat ) : ?>
							<?php
							$cat_id = sanitize_html_class( $cat['id'] ?? 'cat' . $index );
							$cat_uid = $uid . '-cat-' . absint( $index ) . '-' . $cat_id;
							$active = 0 === $index;
							$has_current = $this->category_has_current_descendant( $cat );
							?>
							<section class="emm-mega__section<?php echo $active ? ' is-open' : ''; ?><?php echo $has_current ? ' is-current-ancestor' : ''; ?>" data-emm-section="<?php echo esc_attr( $cat_id ); ?>" style="<?php echo esc_attr( $this->icon_override_vars( $cat ) ); ?>">
								<button
									type="button"
									class="emm-mega__cat<?php echo $active ? ' is-active' : ''; ?>"
									id="<?php echo esc_attr( $cat_uid ); ?>-trigger"
									aria-expanded="<?php echo $active ? 'true' : 'false'; ?>"
									aria-controls="<?php echo esc_attr( $cat_uid ); ?>-panel"
									data-emm-cat="<?php echo esc_attr( $cat_id ); ?>"
								>
									<span class="emm-mega__cat-icon">
										<?php echo $icons->render( $cat['icon'] ?? '', $cat['icon_url'] ?? '', 'emm-icon emm-icon--lg', $cat ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									</span>
									<span class="emm-mega__cat-text">
										<span class="emm-mega__cat-title"><?php echo esc_html( $cat['title'] ); ?></span>
										<?php if ( ! empty( $cat['description'] ) ) : ?>
											<span class="emm-mega__cat-desc"><?php echo esc_html( $cat['description'] ); ?></span>
										<?php endif; ?>
									</span>
									<span class="emm-mega__cat-chevron" aria-hidden="true">
										<svg width="14" height="14" viewBox="0 0 12 12"><path d="M2.5 4.5L6 8l3.5-3.5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
									</span>
								</button>

								<div
									class="emm-mega__panel<?php echo $active ? ' is-active' : ''; ?>"
									id="<?php echo esc_attr( $cat_uid ); ?>-panel"
									role="region"
									aria-labelledby="<?php echo esc_attr( $cat_uid ); ?>-trigger"
									data-emm-panel="<?php echo esc_attr( $cat_id ); ?>"
									<?php echo $active ? '' : 'hidden'; ?>
								>
									<?php if ( ! empty( $cat['panel_title'] ) ) : ?>
										<<?php echo $this->plain_markup ? 'div' : 'h3'; ?> class="emm-mega__panel-title">
											<?php if ( ! empty( $cat['panel_url'] ) ) : ?>
												<a <?php echo $this->link_attrs( array_merge( $cat['panel_link'] ?? $cat, array( 'url' => $cat['panel_url'] ) ), '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> href="<?php echo esc_url( $cat['panel_url'] ); ?>"><?php echo esc_html( $cat['panel_title'] ); ?></a>
											<?php else : ?>
												<?php echo esc_html( $cat['panel_title'] ); ?>
											<?php endif; ?>
										</<?php echo $this->plain_markup ? 'div' : 'h3'; ?>>
									<?php endif; ?>

									<?php echo $this->render_category_columns( $cat ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</div>
							</section>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
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
	 * @param array $cat Category data.
	 * @return string
	 */
	private function render_category_columns( $cat ) {
		$groups = $this->category_groups( $cat );
		if ( empty( $groups ) ) {
			return '';
		}

		$count = count( $groups );
		ob_start();
		?>
		<div class="emm-mega__columns" style="--emm-col-count: <?php echo (int) $count; ?>">
			<?php foreach ( $groups as $group ) : ?>
				<div class="emm-mega__col">
					<?php if ( ! empty( $group['title'] ) ) : ?>
						<<?php echo $this->plain_markup ? 'div' : 'h4'; ?> class="emm-mega__col-title">
							<?php if ( ! empty( $group['url'] ) ) : ?>
								<a <?php echo $this->link_attrs( $group, '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> href="<?php echo esc_url( $group['url'] ); ?>"><?php echo esc_html( $group['title'] ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $group['title'] ); ?>
							<?php endif; ?>
						</<?php echo $this->plain_markup ? 'div' : 'h4'; ?>>
					<?php endif; ?>
					<ul class="emm-mega__col-list">
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
		$icons     = $this->icons();
		$has_icon  = ! empty( $link['icon'] ) || ! empty( $link['icon_url'] );
		$has_url   = ! empty( $link['url'] );
		ob_start();
		?>
		<li class="emm-mega__col-item"<?php echo $this->preview_link_attr( $link ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php if ( $has_url ) : ?>
				<a <?php echo $this->link_attrs( $link, 'emm-mega__col-link' . ( $has_icon ? ' has-icon' : '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> href="<?php echo esc_url( $link['url'] ); ?>">
			<?php else : ?>
				<span class="emm-mega__col-link is-disabled<?php echo $has_icon ? ' has-icon' : ''; ?>" aria-disabled="true">
			<?php endif; ?>
				<?php if ( $has_icon ) : ?>
					<span class="emm-mega__col-icon">
						<?php echo $icons->render( $link['icon'] ?? '', $link['icon_url'] ?? '', 'emm-icon emm-icon--sm', $link ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</span>
				<?php endif; ?>
				<span class="emm-mega__col-copy">
					<span class="emm-mega__col-label"><?php echo esc_html( $link['label'] ); ?></span>
					<?php if ( ! empty( $link['description'] ) ) : ?>
						<span class="emm-mega__col-description"><?php echo esc_html( $link['description'] ); ?></span>
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
		$compact_class = 1 === $cols ? ' emm-mega--compact' : '';

		ob_start();
		?>
		<div class="emm-mega emm-mega--features<?php echo esc_attr( $compact_class ); ?>" id="<?php echo esc_attr( $uid ); ?>-panel" hidden data-emm-mega-panel data-emm-style="features">
			<div class="emm-mega__inner emm-mega__inner--features">
				<ul class="emm-mega__grid emm-mega__grid--features" style="--emm-grid-cols: <?php echo (int) $cols; ?>">
					<?php foreach ( $links as $link ) : ?>
						<?php echo $this->render_grid_link( $link ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render one grid link row.
	 *
	 * @param array $link Link data.
	 * @return string
	 */
	private function render_grid_link( $link ) {
		$icons   = $this->icons();
		$has_url = ! empty( $link['url'] );
		ob_start();
		?>
		<li class="emm-mega__grid-item"<?php echo $this->preview_link_attr( $link ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php if ( $has_url ) : ?>
				<a <?php echo $this->link_attrs( $link, 'emm-mega__grid-link' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> href="<?php echo esc_url( $link['url'] ); ?>">
			<?php else : ?>
				<span class="emm-mega__grid-link is-disabled" aria-disabled="true">
			<?php endif; ?>
				<span class="emm-mega__grid-icon">
					<?php echo $icons->render( $link['icon'] ?? '', $link['icon_url'] ?? '', 'emm-icon emm-icon--sm', $link ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
				<span class="emm-mega__grid-label"><?php echo esc_html( $link['label'] ); ?></span>
			<?php echo $has_url ? '</a>' : '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</li>
		<?php
		return ob_get_clean();
	}
}
