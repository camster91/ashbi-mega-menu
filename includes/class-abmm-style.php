<?php
/**
 * Font and dropdown-height settings for a menu.
 *
 * Stored under `settings.style`, only when something is set, and printed as a
 * small stylesheet scoped to that menu's ID. The menu's markup never changes,
 * so a menu without these settings renders exactly as before. Works for Ashbi
 * menus (`abmm-` classes) and classic menus (`emm-` classes) alike.
 *
 * @package Ashbi_Mega_Menu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABMM_Style {

	/** Text groups: setting prefix => the elements it styles (class suffixes). */
	const GROUPS = array(
		'top'  => array( 'nav__list > .{p}-nav__item > .{p}-nav__link' ),
		'cat'  => array( 'mega__cat-title', 'mega__section-title' ),
		'desc' => array( 'mega__cat-desc', 'mega__col-description' ),
		'link' => array( 'mega__col-label', 'mega__grid-label' ),
	);

	const WEIGHTS    = array( '100', '200', '300', '400', '500', '600', '700', '800', '900' );
	const TRANSFORMS = array( 'none', 'uppercase', 'lowercase', 'capitalize' );

	/**
	 * Keep only valid, non-empty style settings.
	 *
	 * @param mixed $raw Raw `settings.style` value.
	 * @return array
	 */
	public static function sanitize( $raw ) {
		$raw   = is_array( $raw ) ? $raw : array();
		$value = function ( $key ) use ( $raw ) {
			return is_scalar( $raw[ $key ] ?? null ) ? trim( (string) $raw[ $key ] ) : '';
		};
		$out = array();

		$mode = $value( 'panel_height_mode' );
		if ( in_array( $mode, array( 'auto', 'fixed', 'max' ), true ) ) {
			$out['panel_height_mode'] = $mode;
			if ( 'auto' !== $mode ) {
				$height               = absint( $value( 'panel_height' ) );
				$out['panel_height'] = $height ? min( 1200, max( 200, $height ) ) : 520;
			}
		}

		$font_mode = $value( 'font_family_mode' );
		if ( 'inherit' === $font_mode ) {
			$out['font_family_mode'] = 'inherit';
		} elseif ( 'custom' === $font_mode ) {
			$family = self::sanitize_font_family( $value( 'font_family' ) );
			if ( '' !== $family ) {
				$out['font_family_mode'] = 'custom';
				$out['font_family']      = $family;
			}
		}

		foreach ( array_keys( self::GROUPS ) as $group ) {
			$size = absint( $value( $group . '_font_size' ) );
			if ( $size ) {
				$out[ $group . '_font_size' ] = min( 40, max( 10, $size ) );
			}
			$weight = $value( $group . '_font_weight' );
			if ( in_array( $weight, self::WEIGHTS, true ) ) {
				$out[ $group . '_font_weight' ] = $weight;
			}
		}

		$spacing = $value( 'top_letter_spacing' );
		if ( '' !== $spacing && is_numeric( $spacing ) ) {
			$out['top_letter_spacing'] = (string) round( min( 10, max( -2, (float) $spacing ) ), 1 );
		}
		$transform = $value( 'top_text_transform' );
		if ( in_array( $transform, self::TRANSFORMS, true ) ) {
			$out['top_text_transform'] = $transform;
		}
		return $out;
	}

	/** Generic font families, written without quotes. */
	const GENERIC_FAMILIES = array( 'serif', 'sans-serif', 'monospace', 'cursive', 'fantasy', 'system-ui', 'ui-serif', 'ui-sans-serif', 'ui-monospace', 'ui-rounded', 'emoji', 'math', 'fangsong' );

	/**
	 * A CSS font-family list. Each name is reduced to letters, digits, spaces,
	 * dots, hyphens and underscores and written in double quotes (generic
	 * families stay bare), so a stray quote can never open a CSS string.
	 *
	 * @param string $family Raw value, e.g. `Supreme, "Helvetica Neue", sans-serif`.
	 * @return string
	 */
	public static function sanitize_font_family( $family ) {
		$names = array();
		foreach ( explode( ',', substr( (string) $family, 0, 200 ) ) as $name ) {
			$name = trim( preg_replace( '/\s+/', ' ', preg_replace( '/[^A-Za-z0-9 ._-]/', '', $name ) ) );
			if ( '' === $name ) {
				continue;
			}
			$names[] = in_array( strtolower( $name ), self::GENERIC_FAMILIES, true ) ? strtolower( $name ) : '"' . $name . '"';
		}
		return implode( ', ', $names );
	}

	/**
	 * Scoped CSS for one menu, or '' when it sets nothing.
	 *
	 * @param array  $style  Sanitized style settings.
	 * @param string $scope  Selector for this menu's header element.
	 * @param string $prefix Class prefix: 'abmm' or 'emm'.
	 * @param bool   $media  Wrap desktop rules in a min-width media query. The
	 *                       editor's desktop preview passes false: it is a
	 *                       desktop view whatever the admin window's width.
	 * @return string
	 */
	public static function css( $style, $scope, $prefix, $media = true ) {
		$style = is_array( $style ) ? $style : array();
		$p     = 'emm' === $prefix ? 'emm' : 'abmm';
		$rules = array();
		$desk  = array();
		// Narrow placements switch a header to the phone drawer at any width;
		// desktop sizes and heights must not reach that drawer.
		$desk_scope = $scope . ':not(.' . $p . '-force-mobile)';

		if ( 'inherit' === ( $style['font_family_mode'] ?? '' ) ) {
			$rules[] = $scope . ',' . $scope . ' *{font-family:inherit}';
		} elseif ( 'custom' === ( $style['font_family_mode'] ?? '' ) && '' !== ( $style['font_family'] ?? '' ) ) {
			$family  = self::sanitize_font_family( $style['font_family'] );
			$rules[] = $scope . ',' . $scope . ' *{font-family:' . $family . '}';
		}

		// Sizes, weights and case apply to the desktop menu; the phone drawer
		// keeps its larger, touch-friendly text.
		foreach ( self::GROUPS as $group => $targets ) {
			$declarations = array();
			if ( ! empty( $style[ $group . '_font_size' ] ) ) {
				$declarations[] = 'font-size:' . (int) $style[ $group . '_font_size' ] . 'px';
			}
			if ( ! empty( $style[ $group . '_font_weight' ] ) && in_array( (string) $style[ $group . '_font_weight' ], self::WEIGHTS, true ) ) {
				$declarations[] = 'font-weight:' . $style[ $group . '_font_weight' ];
			}
			if ( 'top' === $group && isset( $style['top_letter_spacing'] ) && is_numeric( $style['top_letter_spacing'] ) ) {
				$declarations[] = 'letter-spacing:' . (float) $style['top_letter_spacing'] . 'px';
			}
			if ( 'top' === $group && in_array( $style['top_text_transform'] ?? '', self::TRANSFORMS, true ) ) {
				$declarations[] = 'text-transform:' . $style['top_text_transform'];
			}
			if ( $declarations ) {
				$selectors = array();
				foreach ( $targets as $target ) {
					$selectors[] = $desk_scope . ' .' . $p . '-' . str_replace( '{p}', $p, $target );
				}
				$desk[] = implode( ',', $selectors ) . '{' . implode( ';', $declarations ) . '}';
			}
		}

		$mode  = $style['panel_height_mode'] ?? '';
		$panel = $desk_scope . ' .' . $p . '-mega__panel';
		if ( 'auto' === $mode ) {
			$desk[] = $panel . '{max-height:none}';
		} elseif ( 'fixed' === $mode ) {
			$height = (int) ( $style['panel_height'] ?? 520 );
			$desk[] = $panel . '{height:' . $height . 'px;max-height:' . $height . 'px;overflow-y:auto}';
		} elseif ( 'max' === $mode ) {
			$desk[] = $panel . '{max-height:' . (int) ( $style['panel_height'] ?? 520 ) . 'px;overflow-y:auto}';
		}

		if ( $desk ) {
			$rules[] = $media ? '@media (min-width:901px){' . implode( '', $desk ) . '}' : implode( '', $desk );
		}
		return implode( '', $rules );
	}

	/**
	 * Selector for a rendered menu: repeated classes outrank the plugin's own
	 * state rules (such as uppercase category titles) without !important.
	 *
	 * @param string $menu_id Menu ID.
	 * @param string $prefix  'abmm' or 'emm'.
	 * @return string
	 */
	public static function scope( $menu_id, $prefix ) {
		$p  = 'emm' === $prefix ? 'emm' : 'abmm';
		$id = str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), (string) $menu_id );
		return '.' . $p . '-header.' . $p . '-header.' . $p . '-header[data-' . $p . '-id="' . $id . '"]';
	}

	/**
	 * CSS for every stored menu of one renderer that sets style options.
	 *
	 * @param bool $classic True for classic menus, false for Ashbi menus.
	 * @return string
	 */
	public static function site_css( $classic ) {
		$css = '';
		foreach ( ABMM_Data::instance()->get_all() as $id => $menu ) {
			$style = $menu['settings']['style'] ?? array();
			if ( $style && ABMM_Classic::is_classic( $menu ) === $classic ) {
				$prefix = $classic ? 'emm' : 'abmm';
				$css   .= self::css( $style, self::scope( (string) $id, $prefix ), $prefix );
			}
		}
		return $css;
	}
}
