<?php
/**
 * Icons for classic mode, rendered exactly as Easy Mega Menu rendered them:
 * a `<use>` reference into a sprite of `emm-icon-<key>` symbols.
 *
 * The sprite is the one the importer copied from Easy Mega Menu into this
 * site's uploads (so a site's own icons keep their exact artwork), or the
 * bundled sprite of generic icons when nothing was copied.
 *
 * @package Ashbi_Mega_Menu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABMM_Classic_Icons {

	/**
	 * @var ABMM_Classic
	 */
	private $classic;

	/**
	 * @var array{url:string,keys:array<string,bool>}|null
	 */
	private $sprite = null;

	/**
	 * @param ABMM_Classic $classic Classic renderer, for the copied site assets.
	 */
	public function __construct( $classic ) {
		$this->classic = $classic;
	}

	/**
	 * Sprite reference and the icon keys it contains.
	 *
	 * @return array{url:string,keys:array<string,bool>}
	 */
	public function sprite() {
		if ( null !== $this->sprite ) {
			return $this->sprite;
		}
		$site = $this->classic->site_assets();
		$url     = $this->classic->upload_url( $site['sprite'], true );
		$version = $url && '' !== $site['version'] ? $site['version'] : ABMM_VERSION;
		$file = $url ? ABMM_Classic::upload_dir() . '/' . $site['sprite'] : ABMM_PLUGIN_DIR . 'assets/classic/emm-sprite.svg';
		if ( ! $url ) {
			// Root-relative like Easy Mega Menu: SVG <use> must stay same-origin when a CDN rewrites asset hosts.
			$plugin_path = wp_parse_url( ABMM_PLUGIN_URL, PHP_URL_PATH );
			$url         = ( is_string( $plugin_path ) ? rtrim( $plugin_path, '/' ) . '/' : '/wp-content/plugins/ashbi-mega-menu/' ) . 'assets/classic/emm-sprite.svg';
		}
		$this->sprite = array(
			'url'  => $url . '?ver=' . rawurlencode( $version ),
			'keys' => self::sprite_keys( $file ),
		);
		return $this->sprite;
	}

	/**
	 * Icon keys defined in a sprite file.
	 *
	 * @param string $file Sprite path.
	 * @return array<string,bool>
	 */
	public static function sprite_keys( $file ) {
		$svg = is_readable( $file ) ? file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin or uploads file.
		preg_match_all( '/<symbol\b[^>]*\bid="emm-icon-([a-z0-9_-]+)"/i', (string) $svg, $matches );
		return array_fill_keys( $matches[1], true );
	}

	/**
	 * Render an icon by key or custom URL, with Easy Mega Menu's markup.
	 *
	 * @param string       $key   Icon key.
	 * @param string       $url   Optional custom image URL.
	 * @param string       $class CSS class.
	 * @param string|array $style Legacy colour string or scoped style overrides.
	 * @return string HTML
	 */
	public function render( $key, $url = '', $class = 'emm-icon', $style = array() ) {
		$key   = is_scalar( $key ) ? (string) $key : '';
		$url   = is_scalar( $url ) ? (string) $url : '';
		$class = is_scalar( $class ) ? (string) $class : 'emm-icon';
		if ( is_string( $style ) ) {
			$style = array( 'icon_color' => $style );
		}
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

		$class     .= ! empty( $style['icon_color'] ) ? ' emm-icon--colored' : '';
		$style_attr = $vars ? ' style="' . esc_attr( implode( ';', $vars ) . ';' ) . '"' : '';

		if ( $url ) {
			return sprintf(
				'<span class="%1$s emm-icon--media emm-icon--raster" data-emm-icon-mode="original"%2$s><img src="%3$s" alt="" class="emm-icon__image" decoding="async" /></span>',
				esc_attr( $class ),
				$style_attr,
				esc_url( $url )
			);
		}

		$sprite = $this->sprite();
		$key    = $key && isset( $sprite['keys'][ $key ] ) ? $key : 'link';

		return sprintf(
			'<span class="%1$s emm-icon--svg" data-emm-icon-mode="tintable"%2$s><svg aria-hidden="true" focusable="false"><use href="%3$s"></use></svg></span>',
			esc_attr( $class ),
			$style_attr,
			esc_url( $sprite['url'] . '#emm-icon-' . sanitize_key( $key ) )
		);
	}
}
