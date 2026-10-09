<?php
/**
 * Menu data storage and helpers.
 *
 * @package Ashbi_Mega_Menu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABMM_Data {

	/**
	 * Singleton instance.
	 *
	 * @var ABMM_Data|null
	 */
	private static $instance = null;

	/**
	 * @return ABMM_Data
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Get all menus.
	 *
	 * @return array
	 */
	public function get_all() {
		$menus = get_option( ABMM_OPTION_KEY, array() );
		return is_array( $menus ) ? $menus : array();
	}

	/**
	 * Get a single menu by ID.
	 *
	 * @param string $id Menu ID.
	 * @return array|null
	 */
	public function get( $id ) {
		$menus = $this->get_all();
		if ( ! isset( $menus[ $id ] ) ) {
			return null;
		}
		$menu             = $menus[ $id ];
		$menu['settings'] = wp_parse_args( $menu['settings'] ?? array(), self::default_settings() );
		return $menu;
	}

	/**
	 * Save a menu.
	 *
	 * @param string $id   Menu ID.
	 * @param array  $data Menu data.
	 * @return bool
	 */
	public function save( $id, $data ) {
		$menus     = $this->get_all();
		$sanitized = $this->sanitize_menu( $data );

		if ( isset( $menus[ $id ] ) && $menus[ $id ] === $sanitized ) {
			return true;
		}

		$menus[ $id ] = $sanitized;
		return update_option( ABMM_OPTION_KEY, $menus );
	}

	/**
	 * Save a menu while holding a database-level lock and checking its revision.
	 *
	 * The editor sends the revision it loaded.  The check and full-option write
	 * must happen under the same lock or two editors can both pass the check and
	 * overwrite one another.
	 *
	 * @param string $id                Menu ID.
	 * @param array  $data              Menu data.
	 * @param string $expected_revision Revision supplied by the editor.
	 * @return bool|WP_Error
	 */
	public function save_if_revision( $id, $data, $expected_revision ) {
		if ( ! is_string( $expected_revision ) || '' === $expected_revision ) {
			return new WP_Error( 'abmm_menu_revision_required', __( 'Reload this menu before saving so changes are not overwritten.', 'ashbi-mega-menu' ) );
		}

		return $this->with_save_lock(
			function () use ( $id, $data, $expected_revision ) {
				// Another request may have saved after this request populated its
				// options cache but before it acquired the database lock.
				wp_cache_delete( ABMM_OPTION_KEY, 'options' );
				wp_cache_delete( 'alloptions', 'options' );
				wp_cache_delete( 'notoptions', 'options' );
				$menus   = $this->get_all();
				$current = $menus[ $id ] ?? null;

				if ( ! $current ) {
					return new WP_Error( 'abmm_menu_not_found', __( 'This menu no longer exists.', 'ashbi-mega-menu' ) );
				}

				$current_revision = $this->revision( $current );
				if ( ! hash_equals( $current_revision, $expected_revision ) ) {
					return new WP_Error(
						'abmm_menu_conflict',
						__( 'This menu changed on the server. Your local draft was not overwritten.', 'ashbi-mega-menu' ),
						array(
							'conflict' => true,
							'revision' => $current_revision,
						)
					);
				}

				$sanitized = $this->sanitize_menu( $data );
				if ( $current === $sanitized ) {
					return true;
				}

				$menus[ $id ] = $sanitized;
				return update_option( ABMM_OPTION_KEY, $menus );
			}
		);
	}

	/**
	 * Serialize writes to the shared menu option with a MySQL advisory lock.
	 *
	 * @param callable $callback Write operation.
	 * @return mixed
	 */
	private function with_save_lock( $callback ) {
		global $wpdb;

		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return new WP_Error( 'abmm_save_lock_unavailable', __( 'The menu save lock is unavailable.', 'ashbi-mega-menu' ) );
		}

		$lock_name = 'abmm_menus_save_' . md5( (string) ( $wpdb->options ?? '' ) . '|' . ABMM_OPTION_KEY );
		$acquired  = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock_name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Advisory locks must run on this database connection and cannot be cached; the name is prepared.
		if ( null === $acquired || false === $acquired ) {
			return new WP_Error( 'abmm_save_lock_unavailable', __( 'The database could not provide the menu save lock. Your changes were not saved. This installation requires a database that supports MySQL advisory locks.', 'ashbi-mega-menu' ) );
		}
		if ( '1' !== (string) $acquired ) {
			return new WP_Error( 'abmm_save_lock_timeout', __( 'Another menu save is in progress. Please try again.', 'ashbi-mega-menu' ) );
		}

		try {
			return call_user_func( $callback );
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Advisory locks must run on this database connection and cannot be cached; the name is prepared.
		}
	}

	/**
	 * Replace the complete menu collection.
	 *
	 * @param array $menus Menu ID => menu data map.
	 * @return bool
	 */
	public function replace_all( $menus ) {
		$sanitized = array();
		foreach ( $menus as $id => $menu ) {
			$sanitized[ $id ] = $this->sanitize_menu( $menu );
		}

		if ( $this->get_all() === $sanitized ) {
			return true;
		}

		return update_option( ABMM_OPTION_KEY, $sanitized );
	}

	/**
	 * Delete a menu.
	 *
	 * @param string $id Menu ID.
	 * @return bool
	 */
	public function delete( $id ) {
		$menus = $this->get_all();
		if ( ! isset( $menus[ $id ] ) ) {
			return false;
		}
		unset( $menus[ $id ] );
		return update_option( ABMM_OPTION_KEY, $menus );
	}

	/**
	 * Move a menu into recoverable archive storage.
	 *
	 * @param string $id Menu ID.
	 * @return bool
	 */
	public function archive( $id ) {
		$menus = $this->get_all();
		if ( ! isset( $menus[ $id ] ) ) {
			return false;
		}

		$archives        = $this->get_archived();
		$archives[ $id ] = array(
			'menu'        => $menus[ $id ],
			'archived_at' => time(),
		);
		$original_menus  = $menus;
		unset( $menus[ $id ] );

		if ( ! update_option( ABMM_ARCHIVE_OPTION, $archives ) ) {
			return false;
		}
		if ( update_option( ABMM_OPTION_KEY, $menus ) ) {
			return true;
		}

		update_option( ABMM_ARCHIVE_OPTION, array_diff_key( $archives, array( $id => true ) ) );
		update_option( ABMM_OPTION_KEY, $original_menus );
		return false;
	}

	/**
	 * Get recoverable archived menus.
	 *
	 * @return array
	 */
	public function get_archived() {
		$archives = get_option( ABMM_ARCHIVE_OPTION, array() );
		return is_array( $archives ) ? $archives : array();
	}

	/**
	 * Restore an archived menu using its original ID.
	 *
	 * @param string $id Menu ID.
	 * @return bool
	 */
	public function restore( $id ) {
		$archives = $this->get_archived();
		$menus    = $this->get_all();
		if ( ! isset( $archives[ $id ]['menu'] ) || isset( $menus[ $id ] ) ) {
			return false;
		}

		$menus[ $id ] = $archives[ $id ]['menu'];
		unset( $archives[ $id ] );
		if ( ! update_option( ABMM_OPTION_KEY, $menus ) ) {
			return false;
		}
		if ( update_option( ABMM_ARCHIVE_OPTION, $archives ) || empty( $archives ) ) {
			return true;
		}

		unset( $menus[ $id ] );
		update_option( ABMM_OPTION_KEY, $menus );
		return false;
	}

	/**
	 * Permanently delete an archived menu.
	 *
	 * @param string $id Menu ID.
	 * @return bool
	 */
	public function permanently_delete( $id ) {
		$archives = $this->get_archived();
		if ( ! isset( $archives[ $id ] ) ) {
			return false;
		}
		unset( $archives[ $id ] );
		return update_option( ABMM_ARCHIVE_OPTION, $archives ) || empty( $archives );
	}

	/**
	 * Create a unique user-owned copy of the bundled starter.
	 *
	 * @return string|false New menu ID or false.
	 */
	public function create_from_starter() {
		$demo = self::demo_menus();
		$menu = $demo['menu_demo'] ?? array();
		if ( ! is_array( $menu ) ) {
			return false;
		}

		$id                     = $this->unique_menu_id( 'menu-starter' );
		$menu['starter_source'] = 'generic';
		$menus                  = $this->get_all();
		$menus[ $id ]           = $this->sanitize_menu( $menu );
		return update_option( ABMM_OPTION_KEY, $menus ) ? $id : false;
	}

	/**
	 * Derive whether a menu is ready for placement.
	 *
	 * @param array $menu Menu payload.
	 * @return array
	 */
	public function readiness( $menu ) {
		$issues = array();
		if ( '' === trim( (string) ( $menu['title'] ?? '' ) ) ) {
			$issues[] = array( 'field' => 'abmm-menu-title', 'message' => __( 'Add a menu name.', 'ashbi-mega-menu' ) );
		}

		$usable = 0;
		foreach ( $menu['items'] ?? array() as $item ) {
			if ( '' === trim( (string) ( $item['label'] ?? '' ) ) ) {
				continue;
			}
			if ( 'mega' !== ( $item['type'] ?? 'link' ) ) {
				if ( $this->is_usable_destination( $item['url'] ?? '' ) ) {
					++$usable;
				}
				continue;
			}
			foreach ( $item['links'] ?? array() as $link ) {
				if ( ! empty( $link['label'] ) && $this->is_usable_destination( $link['url'] ?? '' ) ) {
					++$usable;
				}
			}
			foreach ( $item['categories'] ?? array() as $category ) {
				foreach ( $category['groups'] ?? array() as $group ) {
					foreach ( $group['links'] ?? array() as $link ) {
						if ( ! empty( $link['label'] ) && $this->is_usable_destination( $link['url'] ?? '' ) ) {
							++$usable;
						}
					}
				}
			}
		}
		if ( 0 === $usable ) {
			$issues[] = array( 'field' => 'abmm-nav-items', 'message' => __( 'Add at least one labelled item with a real destination.', 'ashbi-mega-menu' ) );
		}
		if ( ! empty( $menu['cta']['show'] ) && ( empty( $menu['cta']['label'] ) || ! $this->is_usable_destination( $menu['cta']['url'] ?? '' ) ) ) {
			$issues[] = array( 'field' => 'abmm-cta-url', 'message' => __( 'Complete the enabled call-to-action label and destination.', 'ashbi-mega-menu' ) );
		}

		return array(
			'ready'  => empty( $issues ),
			'issues' => $issues,
		);
	}

	/**
	 * Stable revision for conflict-safe saves and draft recovery.
	 *
	 * @param array $menu Menu data.
	 * @return string
	 */
	public function revision( $menu ) {
		return hash( 'sha256', wp_json_encode( $menu ) );
	}

	/**
	 * Whether a destination performs navigation.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	private function is_usable_destination( $url ) {
		$url = trim( (string) $url );
		return '' !== $url && '#' !== $url;
	}

	/**
	 * Default design settings.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'preset'          => 'classic',
			'mobile_enhancements' => false,
			'presentation'    => 'stacked',
			'layout'          => 'sidebar-left',
			'sidebar_bg'      => '#f0f0f5',
			'active_bg'       => '#ffffff',
			'panel_bg'        => '#ffffff',
			'header_bg'       => '#ffffff',
			'header_transparent' => false,
			'header_z_index'  => 1000,
			'accent'          => '#1a73e8',
			'text_color'      => '#2c2c2c',
			'nav_link_color'  => '#2c2c2c',
			'nav_hover_color' => '#1a73e8',
			'muted_color'     => '#6b6b6b',
			'border_color'    => '#e5e5ea',
			'cta_text'        => '#ffffff',
			'icon_inherit_text' => true,
			'icon_color'      => '#2c2c2c',
			'icon_hover_color' => '#1a73e8',
			'icon_active_color' => '#1a73e8',
			'icon_size'       => 22,
			'icon_background' => '',
			'icon_border_color' => '',
			'icon_radius'     => 0,
			'grid_columns'    => 3,
			'panel_width'     => 1000,
			'full_width'      => true,
			'sidebar_width'   => 280,
			'border_radius'   => 8,
			'shadow'          => 'medium',
			'nav_align'       => 'center',
			'cta_style'       => 'rounded',
			'uppercase_cats'  => true,
			'show_cat_desc'   => true,
			'panel_title_align' => 'center',
		);
	}

	/**
	 * Built-in design presets (easy one-click themes).
	 *
	 * @return array
	 */
	public static function design_presets() {
		return array(
			'classic' => array(
				'label'  => __( 'Classic', 'ashbi-mega-menu' ),
				'values' => array(
					'sidebar_bg'      => '#f0f0f5',
					'active_bg'       => '#ffffff',
					'panel_bg'        => '#ffffff',
					'header_bg'       => '#ffffff',
					'accent'          => '#1a73e8',
					'text_color'      => '#2c2c2c',
					'nav_link_color'  => '#2c2c2c',
					'nav_hover_color' => '#1a73e8',
					'muted_color'     => '#6b6b6b',
					'border_color'    => '#e5e5ea',
					'cta_text'        => '#ffffff',
					'shadow'          => 'medium',
					'border_radius'   => 8,
				),
			),
			'dark'    => array(
				'label'  => __( 'Dark', 'ashbi-mega-menu' ),
				'values' => array(
					'sidebar_bg'      => '#1e1e24',
					'active_bg'       => '#2a2a32',
					'panel_bg'        => '#16161a',
					'header_bg'       => '#111114',
					'accent'          => '#5b9fff',
					'text_color'      => '#f2f2f5',
					'nav_link_color'  => '#e8e8ed',
					'nav_hover_color' => '#5b9fff',
					'muted_color'     => '#a0a0ab',
					'border_color'    => '#2e2e36',
					'cta_text'        => '#111114',
					'shadow'          => 'strong',
					'border_radius'   => 10,
				),
			),
			'soft'    => array(
				'label'  => __( 'Soft', 'ashbi-mega-menu' ),
				'values' => array(
					'sidebar_bg'      => '#eef6f3',
					'active_bg'       => '#ffffff',
					'panel_bg'        => '#ffffff',
					'header_bg'       => '#fbfffd',
					'accent'          => '#0f766e',
					'text_color'      => '#134e4a',
					'nav_link_color'  => '#134e4a',
					'nav_hover_color' => '#0f766e',
					'muted_color'     => '#5f7a76',
					'border_color'    => '#d5ebe4',
					'cta_text'        => '#ffffff',
					'shadow'          => 'soft',
					'border_radius'   => 14,
				),
			),
			'bold'    => array(
				'label'  => __( 'Bold', 'ashbi-mega-menu' ),
				'values' => array(
					'sidebar_bg'      => '#fff1e8',
					'active_bg'       => '#ffffff',
					'panel_bg'        => '#ffffff',
					'header_bg'       => '#ffffff',
					'accent'          => '#c2410c',
					'text_color'      => '#1a1a1a',
					'nav_link_color'  => '#1a1a1a',
					'nav_hover_color' => '#c2410c',
					'muted_color'     => '#6b6b6b',
					'border_color'    => '#f0d9c8',
					'cta_text'        => '#ffffff',
					'shadow'          => 'medium',
					'border_radius'   => 4,
				),
			),
			'minimal' => array(
				'label'  => __( 'Minimal', 'ashbi-mega-menu' ),
				'values' => array(
					'sidebar_bg'      => '#fafafa',
					'active_bg'       => '#ffffff',
					'panel_bg'        => '#ffffff',
					'header_bg'       => '#ffffff',
					'accent'          => '#111111',
					'text_color'      => '#111111',
					'nav_link_color'  => '#111111',
					'nav_hover_color' => '#111111',
					'muted_color'     => '#767676',
					'border_color'    => '#e8e8e8',
					'cta_text'        => '#ffffff',
					'shadow'          => 'none',
					'border_radius'   => 0,
				),
			),
		);
	}

	/**
	 * Create a new empty menu and return its ID.
	 *
	 * @param string $title Menu title.
	 * @return string|false New menu ID, or false when storage failed.
	 */
	public function create( $title = '' ) {
		$id    = 'menu_' . wp_generate_password( 8, false, false );
		$menus = $this->get_all();

		$menus[ $id ] = $this->sanitize_menu(
			array(
				'title'    => $title ? $title : __( 'New Mega Menu', 'ashbi-mega-menu' ),
				'items'    => array(),
				'cta'      => array(
					'label' => __( 'Get a Demo', 'ashbi-mega-menu' ),
					'url'   => '#',
					'show'  => true,
				),
				'brand'    => array(
					'show'          => false,
					'title'         => get_bloginfo( 'name' ),
					'url'           => home_url( '/' ),
					'logo_url'      => '',
					'logo_dark_url' => '',
					'alt'           => '',
					'logo_height'   => 40,
				),
				'settings' => self::default_settings(),
			)
		);

		if ( ! update_option( ABMM_OPTION_KEY, $menus ) ) {
			return false;
		}

		return $id;
	}

	/**
	 * Duplicate a menu with independent nested IDs.
	 *
	 * @param string $id Existing menu ID.
	 * @return string|WP_Error New menu ID or an error.
	 */
	public function duplicate( $id ) {
		$menu = $this->get( $id );
		if ( null === $menu ) {
			return new WP_Error( 'abmm_menu_not_found', __( 'Menu not found.', 'ashbi-mega-menu' ) );
		}
		$copy   = $this->duplicate_menu_data( $menu, sprintf(
/* translators: %s: Original menu title. */
__( 'Copy of %s', 'ashbi-mega-menu' ), $menu['title'] ) );
		$new_id = $this->unique_menu_id( $id . '-copy' );
		return $this->save( $new_id, $copy ) ? $new_id : new WP_Error( 'abmm_duplicate_failed', __( 'The menu copy could not be saved.', 'ashbi-mega-menu' ) );
	}

	/**
	 * Deep-copy menu data and regenerate every nested ID.
	 *
	 * @param array  $menu  Menu data.
	 * @param string $title Optional replacement title.
	 * @return array
	 */
	public function duplicate_menu_data( $menu, $title = '' ) {
		$copy = $this->sanitize_menu( $menu );
		if ( '' !== $title ) {
			$copy['title'] = $this->sanitize_text( $title );
		}
		$used = array();
		foreach ( $copy['items'] as &$item ) {
			$item['id'] = $this->copy_id( 'item', $used );
			if ( isset( $item['categories'] ) ) {
				foreach ( $item['categories'] as &$category ) {
					$category['id'] = $this->copy_id( 'category', $used );
					foreach ( $category['groups'] as &$group ) {
						$group['id'] = $this->copy_id( 'column', $used );
						foreach ( $group['links'] as &$link ) {
							$link['id'] = $this->copy_id( 'link', $used );
						}
						unset( $link );
					}
					unset( $group );
				}
				unset( $category );
			}
			if ( isset( $item['links'] ) ) {
				foreach ( $item['links'] as &$link ) {
					$link['id'] = $this->copy_id( 'link', $used );
				}
				unset( $link );
			}
		}
		unset( $item );
		return $copy;
	}

	/**
	 * Generate a conflict-safe menu ID.
	 *
	 * @param string $base Preferred base.
	 * @return string
	 */
	public function unique_menu_id( $base = 'menu-copy' ) {
		$base     = sanitize_key( $base ) ?: 'menu-copy';
		$existing = $this->get_all();
		$id       = $base;
		$suffix   = 2;
		while ( isset( $existing[ $id ] ) ) {
			$id = $base . '-' . $suffix++;
		}
		return $id;
	}

	/**
	 * Generate a unique copied nested ID.
	 *
	 * @param string $prefix ID prefix.
	 * @param array  $used   IDs already allocated.
	 * @return string
	 */
	private function copy_id( $prefix, &$used ) {
		$base   = rtrim( $prefix . '_' . sanitize_key( wp_generate_password( 8, false, false ) ), '_' );
		$id     = $base;
		$suffix = 2;
		while ( isset( $used[ $id ] ) ) {
			$id = $base . '_' . $suffix++;
		}
		$used[ $id ] = true;
		return $id;
	}

	/**
	 * Sanitize full menu payload.
	 *
	 * @param array $data Raw data.
	 * @return array
	 */
	public function sanitize_menu( $data ) {
		$data = is_array( $data ) ? $data : array();
		$defaults = self::default_settings();
		$raw      = is_array( $data['settings'] ?? null ) ? $data['settings'] : array();
		$cta      = is_array( $data['cta'] ?? null ) ? $data['cta'] : array();
		$brand    = is_array( $data['brand'] ?? null ) ? $data['brand'] : array();
		$context  = is_array( $data['context'] ?? null ) ? $data['context'] : array();
		$s        = wp_parse_args( $raw, $defaults );

		$layouts = array( 'sidebar-left', 'sidebar-right', 'stacked' );
		$layout  = sanitize_key( $this->scalar_string( $s['layout'] ?? '' ) );
		if ( ! in_array( $layout, $layouts, true ) ) {
			$layout = 'sidebar-left';
		}

		$presets = array_keys( self::design_presets() );
		$preset  = sanitize_key( $this->scalar_string( $s['preset'] ?? '' ) );
		if ( ! in_array( $preset, $presets, true ) && 'custom' !== $preset ) {
			$preset = 'classic';
		}

		$presentation = sanitize_key( $s['presentation'] ?? 'stacked' );
		if ( ! in_array( $presentation, array( 'stacked', 'unified' ), true ) ) {
			$presentation = 'stacked';
		}

		$shadows = array( 'none', 'soft', 'medium', 'strong' );
		$shadow  = sanitize_key( $this->scalar_string( $s['shadow'] ?? '' ) );
		if ( ! in_array( $shadow, $shadows, true ) ) {
			$shadow = 'medium';
		}

		$nav_align = sanitize_key( $this->scalar_string( $s['nav_align'] ?? '' ) );
		if ( ! in_array( $nav_align, array( 'left', 'center', 'right' ), true ) ) {
			$nav_align = 'center';
		}

		$cta_style = sanitize_key( $this->scalar_string( $s['cta_style'] ?? '' ) );
		if ( ! in_array( $cta_style, array( 'square', 'rounded', 'pill' ), true ) ) {
			$cta_style = 'rounded';
		}

		$title_align = sanitize_key( $this->scalar_string( $s['panel_title_align'] ?? '' ) );
		if ( ! in_array( $title_align, array( 'left', 'center' ), true ) ) {
			$title_align = 'center';
		}

		$grid = absint( $this->scalar_string( $s['grid_columns'] ?? 0 ) );
		if ( $grid < 1 || $grid > 4 ) {
			$grid = 3;
		}

		$panel_width   = min( 1400, max( 640, absint( $this->scalar_string( $s['panel_width'] ?? 0 ) ) ) );
		$sidebar_width = min( 420, max( 180, absint( $this->scalar_string( $s['sidebar_width'] ?? 0 ) ) ) );
		$radius        = min( 24, max( 0, absint( $this->scalar_string( $s['border_radius'] ?? 0 ) ) ) );
		$icon_size     = min( 64, max( 12, absint( $this->scalar_string( $s['icon_size'] ?? 0 ) ) ) );
		$icon_radius   = min( 24, max( 0, absint( $this->scalar_string( $s['icon_radius'] ?? 0 ) ) ) );

		$menu = array(
			'title'    => $this->sanitize_text( $data['title'] ?? '' ),
			'starter_source' => in_array( $data['starter_source'] ?? '', array( 'generic' ), true ) ? $data['starter_source'] : '',
			'items'    => array(),
			'cta'      => array(
				'label' => $this->sanitize_text( $cta['label'] ?? '' ),
				'url'   => $this->sanitize_menu_url( $cta['url'] ?? '' ),
				'show'  => ! empty( $cta['show'] ),
			),
			'brand'    => array(
				'show'          => ! empty( $brand['show'] ),
				'title'         => $this->sanitize_text( $brand['title'] ?? '' ),
				'url'           => $this->sanitize_menu_url( $brand['url'] ?? '' ),
				'logo_url'      => $this->sanitize_menu_url( $brand['logo_url'] ?? '' ),
				'logo_dark_url' => $this->sanitize_menu_url( $brand['logo_dark_url'] ?? '' ),
				'alt'           => $this->sanitize_text( $brand['alt'] ?? '' ),
				'logo_height'   => min( 80, max( 16, absint( $this->scalar_string( $brand['logo_height'] ?? 40 ) ) ) ),
			),
			'context'  => array(
				'show'  => ! empty( $context['show'] ),
				'label' => $this->sanitize_text( $context['label'] ?? '' ),
				'url'   => $this->sanitize_menu_url( $context['url'] ?? '' ),
			),
			'settings' => array(
				'preset'            => $preset,
				'mobile_enhancements' => ! empty( $s['mobile_enhancements'] ),
				'presentation'      => $presentation,
				'layout'            => $layout,
				'sidebar_bg'        => $this->sanitize_color( $s['sidebar_bg'] ) ?: $defaults['sidebar_bg'],
				'active_bg'         => $this->sanitize_color( $s['active_bg'] ) ?: $defaults['active_bg'],
				'panel_bg'          => $this->sanitize_color( $s['panel_bg'] ) ?: $defaults['panel_bg'],
				'header_bg'         => $this->sanitize_color( $s['header_bg'] ) ?: $defaults['header_bg'],
				'header_transparent' => ! empty( $s['header_transparent'] ),
				'header_z_index'    => min( 99999, max( 0, absint( $this->scalar_string( $s['header_z_index'] ?? $defaults['header_z_index'] ) ) ) ),
				'accent'            => $this->sanitize_color( $s['accent'] ) ?: $defaults['accent'],
				'text_color'        => $this->sanitize_color( $s['text_color'] ) ?: $defaults['text_color'],
				'nav_link_color'    => $this->sanitize_color( $s['nav_link_color'] ?? '' ) ?: ( $this->sanitize_color( $s['text_color'] ) ?: $defaults['nav_link_color'] ),
				'nav_hover_color'   => $this->sanitize_color( $s['nav_hover_color'] ?? '' ) ?: ( $this->sanitize_color( $s['accent'] ) ?: $defaults['nav_hover_color'] ),
				'muted_color'       => $this->sanitize_color( $s['muted_color'] ) ?: $defaults['muted_color'],
				'border_color'      => $this->sanitize_color( $s['border_color'] ) ?: $defaults['border_color'],
				'cta_text'          => $this->sanitize_color( $s['cta_text'] ) ?: $defaults['cta_text'],
				'icon_inherit_text' => ! empty( $s['icon_inherit_text'] ),
				'icon_color'        => $this->sanitize_color( $s['icon_color'] ) ?: $defaults['icon_color'],
				'icon_hover_color'  => $this->sanitize_color( $s['icon_hover_color'] ) ?: $defaults['icon_hover_color'],
				'icon_active_color' => $this->sanitize_color( $s['icon_active_color'] ) ?: $defaults['icon_active_color'],
				'icon_size'         => $icon_size,
				'icon_background'   => $this->sanitize_color( $s['icon_background'] ) ?: '',
				'icon_border_color' => $this->sanitize_color( $s['icon_border_color'] ) ?: '',
				'icon_radius'       => $icon_radius,
				'grid_columns'      => $grid,
				'panel_width'       => $panel_width,
				'full_width'        => ! empty( $s['full_width'] ),
				'sidebar_width'     => $sidebar_width,
				'border_radius'     => $radius,
				'shadow'            => $shadow,
				'nav_align'         => $nav_align,
				'cta_style'         => $cta_style,
				'uppercase_cats'    => ! empty( $s['uppercase_cats'] ),
				'show_cat_desc'     => ! empty( $s['show_cat_desc'] ),
				'panel_title_align' => $title_align,
			),
		);
		$menu['cta']   = array_merge( $menu['cta'], $this->sanitize_link_meta( $cta ) );
		$menu['brand'] = array_merge( $menu['brand'], $this->sanitize_link_meta( $brand ) );

		if ( ! empty( $data['items'] ) && is_array( $data['items'] ) ) {
			foreach ( $data['items'] as $item ) {
				if ( is_array( $item ) ) {
					$menu['items'][] = $this->sanitize_top_item( $item );
				}
			}
		}

		return $menu;
	}

	/**
	 * Sanitize a top-level nav item.
	 *
	 * @param array $item Item data.
	 * @return array
	 */
	private function sanitize_top_item( $item ) {
		$item = is_array( $item ) ? $item : array();
		$type = sanitize_key( $this->scalar_string( $item['type'] ?? 'link' ) );
		if ( ! in_array( $type, array( 'link', 'mega' ), true ) ) {
			$type = 'link';
		}

		$out = array(
			'id'    => $this->sanitize_text( $item['id'] ?? wp_generate_password( 6, false, false ) ),
			'label' => $this->sanitize_text( $item['label'] ?? '' ),
			'url'   => $this->sanitize_menu_url( $item['url'] ?? '' ),
			'type'  => $type,
		);
		$out = array_merge( $out, $this->sanitize_link_meta( $item ) );

		if ( 'mega' === $type ) {
			$style = sanitize_key( $this->scalar_string( $item['mega_style'] ?? 'platforms' ) );
			if ( ! in_array( $style, array( 'platforms', 'features' ), true ) ) {
				$style = 'platforms';
			}
			$out['mega_style'] = $style;
			$out['hide_category_bar'] = ! empty( $item['hide_category_bar'] );

			$cols = absint( $this->scalar_string( $item['columns'] ?? 0 ) );
			if ( $cols < 1 || $cols > 4 ) {
				$cols = ( 'features' === $style ) ? 2 : 3;
			}
			$out['columns'] = $cols;

			$out['categories'] = array();
			if ( ! empty( $item['categories'] ) && is_array( $item['categories'] ) ) {
				foreach ( $item['categories'] as $cat ) {
					if ( is_array( $cat ) ) {
						$out['categories'][] = $this->sanitize_category( $cat );
					}
				}
			}

			$out['links'] = array();
			if ( ! empty( $item['links'] ) && is_array( $item['links'] ) ) {
				foreach ( $item['links'] as $link ) {
					if ( is_array( $link ) ) {
						$out['links'][] = $this->sanitize_link( $link );
					}
				}
			}
		}

		return $out;
	}

	/**
	 * Sanitize a single grid link.
	 *
	 * @param array $link Link data.
	 * @return array
	 */
	private function sanitize_link( $link ) {
		$link = is_array( $link ) ? $link : array();
		return array_merge(
			array(
				'id'          => $this->sanitize_text( $link['id'] ?? wp_generate_password( 6, false, false ) ),
				'label'       => $this->sanitize_text( $link['label'] ?? '' ),
				'description' => function_exists( 'sanitize_textarea_field' )
					? ( is_scalar( $link['description'] ?? null ) ? sanitize_textarea_field( (string) $link['description'] ) : '' )
					: $this->sanitize_text( $link['description'] ?? '' ),
				'url'         => $this->sanitize_menu_url( $link['url'] ?? '' ),
				'icon'        => $this->sanitize_text( $link['icon'] ?? '' ),
				'icon_url'    => $this->sanitize_menu_url( $link['icon_url'] ?? '' ),
			),
			$this->sanitize_icon_overrides( $link ),
			$this->sanitize_link_meta( $link )
		);
	}

	/**
	 * Sanitize safe link presentation and relationship metadata.
	 *
	 * @param array $source Raw link-like record.
	 * @return array
	 */
	private function sanitize_link_meta( $source ) {
		$source = is_array( $source ) ? $source : array();
		$target      = '_blank' === ( $source['target'] ?? '' ) ? '_blank' : '';
		$allowed_rel = array( 'nofollow', 'sponsored', 'ugc', 'noopener', 'noreferrer' );
		$rel         = array();
		foreach ( preg_split( '/\s+/', strtolower( $this->scalar_string( $source['rel'] ?? '' ) ) ) as $token ) {
			$token = sanitize_key( $token );
			if ( in_array( $token, $allowed_rel, true ) ) {
				$rel[] = $token;
			}
		}
		if ( '_blank' === $target ) {
			$rel[] = 'noopener';
		}

		$classes = array();
		foreach ( preg_split( '/\s+/', $this->scalar_string( $source['class'] ?? '' ) ) as $token ) {
			$token = sanitize_html_class( $token );
			if ( '' !== $token ) {
				$classes[] = $token;
			}
		}

		return array(
			'target' => $target,
			'rel'    => implode( ' ', array_unique( $rel ) ),
			'class'  => implode( ' ', array_slice( array_unique( $classes ), 0, 8 ) ),
		);
	}

	/**
	 * Sanitize optional section/item icon-style overrides.
	 *
	 * Empty values intentionally mean "inherit from the parent scope".
	 *
	 * @param array $source Raw section or item data.
	 * @return array
	 */
	private function sanitize_icon_overrides( $source ) {
		$source = is_array( $source ) ? $source : array();
		$size   = absint( $this->scalar_string( $source['icon_size'] ?? 0 ) );
		$radius = isset( $source['icon_radius'] ) && '' !== $source['icon_radius']
			? min( 24, max( 0, absint( $this->scalar_string( $source['icon_radius'] ) ) ) )
			: '';

		return array(
			'icon_color'        => $this->sanitize_color( $source['icon_color'] ?? '' ) ?: '',
			'icon_hover_color'  => $this->sanitize_color( $source['icon_hover_color'] ?? '' ) ?: '',
			'icon_active_color' => $this->sanitize_color( $source['icon_active_color'] ?? '' ) ?: '',
			'icon_size'         => $size ? min( 64, max( 12, $size ) ) : 0,
			'icon_background'   => $this->sanitize_color( $source['icon_background'] ?? '' ) ?: '',
			'icon_border_color' => $this->sanitize_color( $source['icon_border_color'] ?? '' ) ?: '',
			'icon_radius'       => $radius,
		);
	}

	/**
	 * Sanitize scalar text values while rejecting structured input.
	 *
	 * @param mixed $value Raw text value.
	 * @return string
	 */
	private function sanitize_text( $value ) {
		return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	}

	/**
	 * Convert untrusted metadata to a scalar string without array warnings.
	 *
	 * @param mixed $value Raw metadata value.
	 * @return string
	 */
	private function scalar_string( $value ) {
		return is_scalar( $value ) ? (string) $value : '';
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

	/**
	 * Sanitize menu URLs, preserving site-relative paths like /about-us.html.
	 *
	 * @param string $url Raw URL.
	 * @return string
	 */
	private function sanitize_menu_url( $url ) {
		if ( ! is_scalar( $url ) ) {
			return '';
		}

		$url = trim( (string) $url );
		if ( '' === $url || '#' === $url ) {
			return $url;
		}

		// Keep root-relative paths (common for site-style site links).
		if ( 0 === strpos( $url, '/' ) && 0 !== strpos( $url, '//' ) ) {
			$path = wp_kses_no_null( $url );
			$path = str_replace( array( "\r", "\n", "\t", ' ' ), '', $path );
			return $path;
		}

		return esc_url_raw( $url );
	}

	/**
	 * Sanitize a sidebar category.
	 *
	 * Supports titled column groups (`groups`) under each category.
	 * Legacy flat `links` are migrated into a single untitled group.
	 *
	 * @param array $cat Category data.
	 * @return array
	 */
	private function sanitize_category( $cat ) {
		$cat = is_array( $cat ) ? $cat : array();
		$out = array_merge(
			array(
			'id'          => $this->sanitize_text( $cat['id'] ?? wp_generate_password( 6, false, false ) ),
			'title'       => $this->sanitize_text( $cat['title'] ?? '' ),
			'description' => $this->sanitize_text( $cat['description'] ?? '' ),
			'icon'        => $this->sanitize_text( $cat['icon'] ?? '' ),
			'icon_url'    => $this->sanitize_menu_url( $cat['icon_url'] ?? '' ),
			'panel_title' => $this->sanitize_text( $cat['panel_title'] ?? '' ),
			'panel_url'   => $this->sanitize_menu_url( $cat['panel_url'] ?? '' ),
			'groups'      => array(),
			'links'       => array(),
			),
			$this->sanitize_icon_overrides( $cat )
		);
		$out['panel_link'] = $this->sanitize_link_meta( $cat['panel_link'] ?? $cat );

		if ( ! empty( $cat['groups'] ) && is_array( $cat['groups'] ) ) {
			foreach ( $cat['groups'] as $group ) {
				if ( is_array( $group ) ) {
					$out['groups'][] = $this->sanitize_group( $group );
				}
			}
		} elseif ( ! empty( $cat['links'] ) && is_array( $cat['links'] ) ) {
			$legacy_links = array();
			foreach ( $cat['links'] as $link ) {
				if ( is_array( $link ) ) {
					$legacy_links[] = $this->sanitize_link( $link );
				}
			}
			$out['groups'][] = array(
				'id'    => 'col_' . wp_generate_password( 6, false, false ),
				'title' => '',
				'url'   => '',
				'links' => $legacy_links,
			);
		}

		return $out;
	}

	/**
	 * Sanitize a column group inside a category.
	 *
	 * @param array $group Group data.
	 * @return array
	 */
	private function sanitize_group( $group ) {
		$group = is_array( $group ) ? $group : array();
		$out = array(
			'id'    => $this->sanitize_text( $group['id'] ?? wp_generate_password( 6, false, false ) ),
			'title' => $this->sanitize_text( $group['title'] ?? '' ),
			'url'   => $this->sanitize_menu_url( $group['url'] ?? '' ),
			'links' => array(),
		);
		$out = array_merge( $out, $this->sanitize_link_meta( $group ) );

		if ( ! empty( $group['links'] ) && is_array( $group['links'] ) ) {
			foreach ( $group['links'] as $link ) {
				if ( is_array( $link ) ) {
					$out['links'][] = $this->sanitize_link( $link );
				}
			}
		}

		return $out;
	}

	/**
	 * Generic sample navigation.
	 *
	 * @return array
	 */
	public static function generic_starter() {
		$settings = self::default_settings();
		$settings['mobile_enhancements'] = true;
		return array( 'menu_demo' => array(
			'title' => __( 'Main navigation sample', 'ashbi-mega-menu' ),
			'items' => array(
				array( 'id' => 'home', 'label' => __( 'Home', 'ashbi-mega-menu' ), 'url' => home_url( '/' ), 'type' => 'link' ),
				array( 'id' => 'products', 'label' => __( 'Products', 'ashbi-mega-menu' ), 'url' => '#', 'type' => 'mega', 'mega_style' => 'platforms', 'categories' => array(
					array( 'id' => 'solutions', 'label' => __( 'Solutions', 'ashbi-mega-menu' ), 'title' => __( 'Explore your products', 'ashbi-mega-menu' ), 'groups' => array(
						array( 'title' => __( 'Getting started', 'ashbi-mega-menu' ), 'links' => array(
							array( 'label' => __( 'Replace with your product', 'ashbi-mega-menu' ), 'url' => '', 'icon' => 'package' ),
						) ),
					) ),
				) ),
				array( 'id' => 'contact', 'label' => __( 'Add your contact page', 'ashbi-mega-menu' ), 'url' => '', 'type' => 'link' ),
			),
			'cta' => array( 'show' => false, 'label' => '', 'url' => '' ),
			'settings' => $settings,
		) );
	}

	public static function demo_menus() {
		return self::generic_starter();
	}
}
