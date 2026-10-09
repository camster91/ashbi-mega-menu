<?php
/**
 * Built-in SVG icon library for mega menu items.
 *
 * @package Ashbi_Mega_Menu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABMM_Icons {

	/**
	 * @var ABMM_Icons|null
	 */
	private static $instance = null;

	/**
	 * Request-local cache of the assembled icon library.
	 *
	 * @var array|null
	 */
	private $icons = null;

	/**
	 * @return ABMM_Icons
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Icon key => SVG markup map.
	 *
	 * @return array
	 */
	public function all() {
		if ( null !== $this->icons ) { return $this->icons; }
		$this->icons = array();
		foreach ( glob( ABMM_PLUGIN_DIR . 'assets/icons/generic/*.svg' ) as $file ) {
			$svg = file_get_contents( $file );
			if ( false !== $svg ) {
				$svg = $this->normalize_product_svg( $svg );
				if ( $svg ) { $this->icons[ basename( $file, '.svg' ) ] = $svg; }
			}
		}
		return $this->icons;
	}

	/**
	 * Labels for the icon picker UI.
	 *
	 * @return array
	 */
	public function labels() {
		$labels = array(
			'wallet'      => __( 'Wallet', 'ashbi-mega-menu' ),
			'signature'   => __( 'Signature', 'ashbi-mega-menu' ),
			'users'       => __( 'Users', 'ashbi-mega-menu' ),
			'handshake'   => __( 'Handshake', 'ashbi-mega-menu' ),
			'dollar'      => __( 'Dollar', 'ashbi-mega-menu' ),
			'airplane'    => __( 'Airplane', 'ashbi-mega-menu' ),
			'card'        => __( 'Card', 'ashbi-mega-menu' ),
			'search-gear' => __( 'Search', 'ashbi-mega-menu' ),
			'document'    => __( 'Document', 'ashbi-mega-menu' ),
			'building'    => __( 'Building', 'ashbi-mega-menu' ),
			'clipboard'   => __( 'Clipboard', 'ashbi-mega-menu' ),
			'monitor'     => __( 'Monitor', 'ashbi-mega-menu' ),
			'chart'       => __( 'Chart', 'ashbi-mega-menu' ),
			'link'        => __( 'Link', 'ashbi-mega-menu' ),
			'star'        => __( 'Star', 'ashbi-mega-menu' ),
			'grid'        => __( 'Grid', 'ashbi-mega-menu' ),
			'heart'       => __( 'Heart', 'ashbi-mega-menu' ),
			'shield'      => __( 'Shield', 'ashbi-mega-menu' ),
			'globe'       => __( 'Globe', 'ashbi-mega-menu' ),
			'mail'        => __( 'Mail', 'ashbi-mega-menu' ),
			'phone'       => __( 'Phone', 'ashbi-mega-menu' ),
			'settings'    => __( 'Settings', 'ashbi-mega-menu' ),
			'briefcase'   => __( 'Briefcase', 'ashbi-mega-menu' ),
			'calendar'    => __( 'Calendar', 'ashbi-mega-menu' ),
			'clock'       => __( 'Clock', 'ashbi-mega-menu' ),
			'cloud'       => __( 'Cloud', 'ashbi-mega-menu' ),
			'database'    => __( 'Database', 'ashbi-mega-menu' ),
			'folder'      => __( 'Folder', 'ashbi-mega-menu' ),
			'key'         => __( 'Key', 'ashbi-mega-menu' ),
			'lock'        => __( 'Lock', 'ashbi-mega-menu' ),
			'package'     => __( 'Package', 'ashbi-mega-menu' ),
			'rocket'      => __( 'Rocket', 'ashbi-mega-menu' ),
			'tag'         => __( 'Tag', 'ashbi-mega-menu' ),
			'map-pin'     => __( 'Map pin', 'ashbi-mega-menu' ),
			'bell'        => __( 'Bell', 'ashbi-mega-menu' ),
			'check-circle'=> __( 'Check circle', 'ashbi-mega-menu' ),
			'user-plus'   => __( 'Add user', 'ashbi-mega-menu' ),
			'filter'      => __( 'Filter', 'ashbi-mega-menu' ),
			'lightbulb'   => __( 'Lightbulb', 'ashbi-mega-menu' ),
			'pie-chart'   => __( 'Pie chart', 'ashbi-mega-menu' ),
		);


		return $labels;
	}


	/**
	 * Sanitize bundled product SVGs and normalize eligible paint values.
	 *
	 * @param string $svg Raw SVG markup.
	 * @return string Safe decorative SVG markup, or an empty string.
	 */
	private function normalize_product_svg( $svg ) {
		$svg = preg_replace( '#<(?:script|style|foreignObject)\b[^>]*>.*?</(?:script|style|foreignObject)\s*>#is', '', $svg );
		$svg = preg_replace( '#<(?:script|style|foreignObject)\b[^>]*/\s*>#is', '', $svg );

		$allowed = array(
			'svg'      => array( 'xmlns' => true, 'width' => true, 'height' => true, 'viewbox' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'fill-rule' => true, 'clip-rule' => true ),
			'g'        => array( 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'fill-rule' => true, 'clip-rule' => true, 'transform' => true ),
			'path'     => array( 'd' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'fill-rule' => true, 'clip-rule' => true, 'transform' => true ),
			'circle'   => array( 'cx' => true, 'cy' => true, 'r' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true ),
			'ellipse'  => array( 'cx' => true, 'cy' => true, 'rx' => true, 'ry' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true ),
			'rect'     => array( 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true ),
			'line'     => array( 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true ),
			'polyline' => array( 'points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true ),
			'polygon'  => array( 'points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true ),
		);

		$svg = wp_kses( $svg, $allowed );
		if ( false === strpos( strtolower( $svg ), '<svg' ) ) {
			return '';
		}

		$svg = preg_replace( '/\s(?:aria-hidden|focusable|role)=(["\']).*?\1/i', '', $svg );
		$svg = preg_replace( '/<svg\b/i', '<svg aria-hidden="true" focusable="false"', $svg, 1 );
		$svg = preg_replace_callback(
			'/\s(fill|stroke)=(["\'])([^"\']*)\2/i',
			function( $matches ) {
				$value = strtolower( trim( $matches[3] ) );
				if ( in_array( $value, array( 'none', 'transparent', 'currentcolor' ), true ) ) {
					return $matches[0];
				}
				return ' ' . $matches[1] . '=' . $matches[2] . 'currentColor' . $matches[2];
			},
			$svg
		);

		return $svg;
	}

	/**
	 * Render an icon by key or custom URL.
	 *
	 * @param string $key     Built-in icon key.
	 * @param string $url     Optional custom image URL.
	 * @param string $class   CSS class.
	 * @param string|array $style Optional legacy colour string or scoped style overrides.
	 * @return string HTML
	 */
	public function render( $key, $url = '', $class = 'abmm-icon', $style = array() ) {
		$key   = is_scalar( $key ) ? (string) $key : '';
		$url   = is_scalar( $url ) ? (string) $url : '';
		$class = is_scalar( $class ) ? (string) $class : 'abmm-icon';
		if ( is_string( $style ) ) {
			$style = array( 'icon_color' => $style );
		}
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
			$value = $this->sanitize_color( $style[ $field ] ?? '' );
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

		$class .= ! empty( $style['icon_color'] ) ? ' abmm-icon--colored' : '';
		$style_attr = $vars ? ' style="' . esc_attr( implode( ';', $vars ) . ';' ) . '"' : '';

		if ( $url ) {
			return sprintf(
				'<span class="%1$s abmm-icon--media abmm-icon--raster" data-abmm-icon-mode="original"%2$s><img src="%3$s" alt="" class="abmm-icon__image" decoding="async" /></span>',
				esc_attr( $class ),
				$style_attr,
				esc_url( $url )
			);
		}

		$icons = $this->all();
		$key = $key && isset( $icons[ $key ] ) ? $key : 'link';
		$plugin_path = wp_parse_url( ABMM_PLUGIN_URL, PHP_URL_PATH );
		$plugin_path = is_string( $plugin_path ) ? rtrim( $plugin_path, '/' ) . '/' : '/wp-content/plugins/ashbi-mega-menu/';
		$sprite_reference = $plugin_path . 'assets/icons/abmm-sprite.svg?ver=' . rawurlencode( ABMM_VERSION ) . '#abmm-icon-' . sanitize_key( $key );

		return sprintf(
			'<span class="%1$s abmm-icon--svg" data-abmm-icon-mode="tintable"%2$s><svg aria-hidden="true" focusable="false"><use href="%3$s"></use></svg></span>',
			esc_attr( $class ),
			$style_attr,
			esc_url( $sprite_reference )
		);
	}

	/**
	 * Accept only scalar colour values before calling the WordPress validator.
	 *
	 * @param mixed $value Raw colour value.
	 * @return string|null
	 */
	private function sanitize_color( $value ) {
		return is_scalar( $value ) ? sanitize_hex_color( (string) $value ) : null;
	}
}
