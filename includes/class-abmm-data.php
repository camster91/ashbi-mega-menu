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

	/** @var bool Whether this instance owns the collection lock. */
	private $lock_owned = false;

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
	 * @return bool|WP_Error
	 */
	public function save( $id, $data ) {
		return $this->with_collection_lock( function () use ( $id, $data ) {
			return $this->save_unlocked( $id, $data );
		} );
	}

	private function save_unlocked( $id, $data ) {
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

		return $this->with_collection_lock(
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
	 * Name of the options row that serializes collection writes on this site.
	 */
	const LOCK_OPTION = 'abmm_collection_lock';

	/**
	 * Seconds to wait for another save to finish before giving up.
	 */
	const LOCK_WAIT_SECONDS = 5;

	/**
	 * Seconds after which an abandoned lock (for example from a crashed request) is cleared.
	 */
	const LOCK_TTL_SECONDS = 60;

	/**
	 * Serialize writes to the shared menu option.
	 *
	 * The lock is a row in this site's options table, inserted atomically with
	 * INSERT IGNORE like core's WP_Upgrader::create_lock(). Unlike a MySQL
	 * advisory lock it is scoped to this site, works behind connection poolers
	 * and expires on its own if a request dies while holding it.
	 *
	 * @param callable $callback Write operation.
	 * @return mixed
	 */
	public function with_collection_lock( $callback ) {
		global $wpdb;

		if ( $this->lock_owned ) {
			return call_user_func( $callback );
		}

		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'query' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return new WP_Error( 'abmm_save_lock_unavailable', __( 'The menu save lock is unavailable.', 'ashbi-mega-menu' ) );
		}

		$token = $this->acquire_collection_lock();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$this->lock_owned = true;
		// Refresh every option participating in a collection mutation after acquiring the lock.
		foreach ( array( ABMM_OPTION_KEY, ABMM_ARCHIVE_OPTION, 'abmm_import_backups', 'alloptions', 'notoptions' ) as $option ) {
			wp_cache_delete( $option, 'options' );
		}
		try {
			return call_user_func( $callback );
		} finally {
			$this->lock_owned = false;
			// Delete only our own lock row, never one another request took over after expiry.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", self::LOCK_OPTION, $token ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Atomic compare-and-delete of the lock row; the table name is core-owned.
		}
	}

	/**
	 * Insert the lock row, waiting briefly for another save and clearing an expired lock.
	 *
	 * @return string|WP_Error Lock token, or an error.
	 */
	private function acquire_collection_lock() {
		global $wpdb;

		$deadline = microtime( true ) + self::LOCK_WAIT_SECONDS;
		do {
			$token    = ( time() + self::LOCK_TTL_SECONDS ) . '|' . wp_generate_uuid4();
			$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} ( option_name, option_value, autoload ) VALUES ( %s, %s, 'no' )", self::LOCK_OPTION, $token ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Atomic lock insert, as in core's WP_Upgrader::create_lock(); the table name is core-owned.
			if ( false === $inserted || null === $inserted ) {
				return new WP_Error( 'abmm_save_lock_unavailable', __( 'The database could not provide the menu save lock. Your changes were not saved. Try again, or ask your host to check the database.', 'ashbi-mega-menu' ) );
			}
			if ( (int) $inserted > 0 ) {
				return $token;
			}

			$current = (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::LOCK_OPTION ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The lock row must be read uncached; the table name is core-owned.
			$expires = (int) strtok( $current, '|' );
			if ( '' !== $current && $expires < time() ) {
				// A request died while saving. Clear its lock, but only if nobody replaced it meanwhile.
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", self::LOCK_OPTION, $current ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Atomic compare-and-delete of an expired lock row; the table name is core-owned.
				continue;
			}
			usleep( 100000 );
		} while ( microtime( true ) < $deadline );

		return new WP_Error( 'abmm_save_lock_timeout', __( 'Another menu save is in progress. Please try again.', 'ashbi-mega-menu' ) );
	}

	/**
	 * Replace the complete menu collection.
	 *
	 * @param array $menus Menu ID => menu data map.
	 * @return bool|WP_Error
	 */
	public function replace_all( $menus ) {
		return $this->with_collection_lock( function () use ( $menus ) {
			return $this->replace_all_unlocked( $menus );
		} );
	}

	private function replace_all_unlocked( $menus ) {
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
	 * @return bool|WP_Error
	 */
	public function delete( $id ) {
		return $this->with_collection_lock( function () use ( $id ) {
			return $this->delete_unlocked( $id );
		} );
	}

	private function delete_unlocked( $id ) {
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
	 * @return bool|WP_Error
	 */
	public function archive( $id ) {
		return $this->with_collection_lock( function () use ( $id ) {
			return $this->archive_unlocked( $id );
		} );
	}

	private function archive_unlocked( $id ) {
		$menus = $this->get_all();
		if ( ! isset( $menus[ $id ] ) ) {
			return false;
		}

		$original_archives = $this->get_archived();
		$archives        = $original_archives;
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

		update_option( ABMM_ARCHIVE_OPTION, $original_archives );
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
	 * @return bool|WP_Error
	 */
	public function restore( $id ) {
		return $this->with_collection_lock( function () use ( $id ) {
			return $this->restore_unlocked( $id );
		} );
	}

	private function restore_unlocked( $id ) {
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
		if ( update_option( ABMM_ARCHIVE_OPTION, $archives ) ) {
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
	 * @return bool|WP_Error
	 */
	public function permanently_delete( $id ) {
		return $this->with_collection_lock( function () use ( $id ) {
			return $this->permanently_delete_unlocked( $id );
		} );
	}

	private function permanently_delete_unlocked( $id ) {
		$archives = $this->get_archived();
		if ( ! isset( $archives[ $id ] ) ) {
			return false;
		}
		unset( $archives[ $id ] );
		return update_option( ABMM_ARCHIVE_OPTION, $archives );
	}

	/**
	 * Create a unique user-owned copy of the bundled starter.
	 *
	 * @return string|false|WP_Error New menu ID, write failure, or lock error.
	 */
	public function create_from_starter() {
		return $this->with_collection_lock( function () {
			return $this->create_from_starter_unlocked();
		} );
	}

	private function create_from_starter_unlocked() {
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
		$labels = array();
		$usable_link = function ( $link ) {
			return '' !== trim( (string) ( $link['label'] ?? '' ) ) && $this->is_usable_destination( $link['url'] ?? '' );
		};
		foreach ( $menu['items'] ?? array() as $index => $item ) {
			$label = trim( (string) ( $item['label'] ?? '' ) );
			if ( '' === $label ) {
				/* translators: %d: item position. */
				$issues[] = array( 'field' => 'abmm-nav-items', 'message' => sprintf( __( 'Add a label to top menu item %d.', 'ashbi-mega-menu' ), $index + 1 ) );
			} else {
				$key = function_exists( 'mb_strtolower' ) ? mb_strtolower( $label, 'UTF-8' ) : strtolower( $label );
				$labels[ $key ] = ( $labels[ $key ] ?? 0 ) + 1;
			}
			if ( 'mega' !== ( $item['type'] ?? 'link' ) ) {
				if ( $usable_link( $item ) ) { ++$usable; }
				continue;
			}
			$before = $usable;
			foreach ( $item['links'] ?? array() as $link ) { if ( $usable_link( $link ) ) { ++$usable; } }
			foreach ( $item['categories'] ?? array() as $category ) {
				foreach ( $category['groups'] ?? array() as $group ) {
					foreach ( $group['links'] ?? array() as $link ) { if ( $usable_link( $link ) ) { ++$usable; } }
				}
			}
			if ( $usable === $before ) {
				/* translators: %s: mega menu label. */
				$issues[] = array( 'field' => 'abmm-nav-items', 'message' => sprintf( __( 'Add at least one labelled link with a real destination to %s.', 'ashbi-mega-menu' ), $label ?: __( 'this mega menu', 'ashbi-mega-menu' ) ) );
			}
		}
		foreach ( $labels as $label => $count ) {
			if ( $count > 1 ) {
				/* translators: %s: duplicated label. */
				$issues[] = array( 'field' => 'abmm-nav-items', 'message' => sprintf( __( 'Rename duplicate top menu item "%s".', 'ashbi-mega-menu' ), $label ) );
			}
		}
		if ( 0 === $usable ) { $issues[] = array( 'field' => 'abmm-nav-items', 'message' => __( 'Add at least one labelled item with a real destination.', 'ashbi-mega-menu' ) ); }
		if ( ! empty( $menu['cta']['show'] ) && ! $usable_link( $menu['cta'] ) ) {
			$issues[] = array( 'field' => 'abmm-cta-url', 'message' => __( 'Complete the enabled call-to-action label and destination.', 'ashbi-mega-menu' ) );
		}
		foreach ( $menu['items'] ?? array() as $item ) {
			$label = trim( (string) ( $item['label'] ?? '' ) );
			$length = function_exists( 'mb_strlen' ) ? mb_strlen( $label, 'UTF-8' ) : strlen( $label );
			if ( $length > self::MAX_BAR_LABEL_LENGTH ) {
				$short = ( function_exists( 'mb_substr' ) ? mb_substr( $label, 0, 24, 'UTF-8' ) : substr( $label, 0, 24 ) ) . '…';
				/* translators: %s: the start of a menu item label. */
				$issues[] = array( 'field' => 'abmm-nav-items', 'message' => sprintf( __( 'Shorten "%s" so it fits in the menu bar.', 'ashbi-mega-menu' ), $short ) );
			}
		}
		$brand = is_array( $menu['brand'] ?? null ) ? $menu['brand'] : array();
		if ( ! empty( $brand['show'] ) ) {
			$logo      = trim( (string) ( $brand['logo_url'] ?? '' ) );
			$logo_dark = trim( (string) ( $brand['logo_dark_url'] ?? '' ) );
			if ( '' === $logo && '' === $logo_dark && '' === trim( (string) ( $brand['title'] ?? '' ) ) ) {
				$issues[] = array( 'field' => 'abmm-brand-title', 'message' => __( 'Add a logo image or site title, or turn off "Show logo".', 'ashbi-mega-menu' ) );
			} elseif ( $this->looks_like_broken_url( $logo ) || $this->looks_like_broken_url( $logo_dark ) ) {
				$issues[] = array( 'field' => 'abmm-brand-logo-url', 'message' => __( 'Check the logo image link. It does not look like a web address.', 'ashbi-mega-menu' ) );
			}
		}
		$samples = count( array_filter( $this->menu_links( $menu ), function ( $link ) {
			return $this->is_sample_url( $link['url'] ?? '' );
		} ) );
		if ( $samples ) {
			/* translators: %d: number of template links. */
			$issues[] = array( 'field' => 'abmm-nav-items', 'message' => sprintf( _n( 'Replace %d sample link with your own page.', 'Replace %d sample links with your own pages.', $samples, 'ashbi-mega-menu' ), $samples ) );
		}
		foreach ( $this->menu_links( $menu ) as $link ) {
			if ( $this->looks_like_broken_url( $link['url'] ?? '' ) ) {
				/* translators: %s: link label. */
				$issues[] = array( 'field' => 'abmm-nav-items', 'message' => sprintf( __( 'Check the link for "%s". It does not look like a web address.', 'ashbi-mega-menu' ), $link['label'] ?? '' ) );
			}
		}
		return array( 'ready' => empty( $issues ), 'issues' => $issues );
	}

	/**
	 * Longest top-level label that reliably fits in a menu bar.
	 */
	const MAX_BAR_LABEL_LENGTH = 40;

	/**
	 * Every labelled link in a menu: top-level links, mega links and the CTA.
	 *
	 * @param array $menu Menu payload.
	 * @return array
	 */
	private function menu_links( $menu ) {
		$links = array();
		foreach ( $menu['items'] ?? array() as $item ) {
			if ( 'mega' !== ( $item['type'] ?? 'link' ) ) {
				$links[] = $item;
				continue;
			}
			foreach ( $item['links'] ?? array() as $link ) { $links[] = $link; }
			foreach ( $item['categories'] ?? array() as $category ) {
				foreach ( $category['groups'] ?? array() as $group ) {
					foreach ( $group['links'] ?? array() as $link ) { $links[] = $link; }
				}
			}
		}
		if ( ! empty( $menu['cta']['show'] ) ) {
			$links[] = $menu['cta'];
		}
		return $links;
	}

	/**
	 * Whether a saved URL was probably typed as plain text, such as "about" saved as http://about.
	 *
	 * @param string $url Saved URL.
	 * @return bool
	 */
	public function looks_like_broken_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url || '#' === $url[0] || '/' === $url[0] || '?' === $url[0] ) {
			return false;
		}
		if ( ! preg_match( '#^https?://([^/?\#:]*)#i', $url, $match ) ) {
			return false;
		}
		$host = strtolower( rawurldecode( $match[1] ) );
		return '' === $host || false !== strpos( $host, ' ' ) || ( false === strpos( $host, '.' ) && 'localhost' !== $host );
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
			'uppercase_cats'  => false,
			'show_cat_desc'   => true,
			'panel_title_align' => 'left',
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
	 * @return string|false|WP_Error New menu ID, write failure, or lock error.
	 */
	public function create( $title = '' ) {
		return $this->with_collection_lock( function () use ( $title ) {
			return $this->create_unlocked( $title );
		} );
	}

	private function create_unlocked( $title = '' ) {
		$id    = $this->unique_menu_id( 'menu_' . wp_generate_password( 8, false, false ) );
		$menus = $this->get_all();

		$menus[ $id ] = $this->sanitize_menu(
			array(
				'title'    => $title ? $title : __( 'New Mega Menu', 'ashbi-mega-menu' ),
				'items'    => array(),
				'cta'      => array(
					'label' => '',
					'url'   => '',
					'show'  => false,
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
	 * Create a draft menu from published pages chosen on first run.
	 *
	 * Pages keep the chosen order. A page whose parent was also chosen goes
	 * into that parent's dropdown (one set of link columns); the others are
	 * links in the menu bar. Nothing is placed on the site.
	 *
	 * @param int[] $page_ids Page IDs, in the order chosen.
	 * @return string|false|WP_Error New menu ID, write failure, or an error.
	 */
	public function create_from_pages( $page_ids ) {
		$pages = array();
		foreach ( array_slice( array_unique( array_map( 'absint', (array) $page_ids ) ), 0, 50 ) as $page_id ) {
			$page = $page_id ? get_post( $page_id ) : null;
			if ( $page && 'page' === $page->post_type && 'publish' === $page->post_status ) {
				$pages[ $page_id ] = $page;
			}
		}
		if ( ! $pages ) {
			return new WP_Error( 'abmm_no_pages', __( 'Choose at least one published page.', 'ashbi-mega-menu' ) );
		}

		$children = array();
		foreach ( $pages as $page_id => $page ) {
			$parent = (int) $page->post_parent;
			$children[ isset( $pages[ $parent ] ) ? $parent : 0 ][] = $page_id;
		}
		$link = function ( $page ) {
			$title = trim( wp_strip_all_tags( (string) $page->post_title ) );
			return array(
				'id'    => 'page_' . $page->ID,
				'label' => '' !== $title ? $title : __( '(no title)', 'ashbi-mega-menu' ),
				'url'   => (string) get_permalink( $page ),
			);
		};
		$items = array();
		foreach ( $children[0] ?? array() as $page_id ) {
			$item = array_merge( $link( $pages[ $page_id ] ), array( 'type' => 'link' ) );
			// Grandchildren follow their parent in the same dropdown.
			$below   = array();
			$collect = function ( $id ) use ( &$collect, &$below, $children, $pages, $link ) {
				foreach ( $children[ $id ] ?? array() as $child ) {
					$below[] = array_merge( $link( $pages[ $child ] ), array( 'description' => '', 'icon' => '' ) );
					$collect( $child );
				}
			};
			$collect( $page_id );
			if ( $below ) {
				$columns = max( 1, min( 4, (int) ceil( count( $below ) / 6 ) ) );
				$groups  = array();
				foreach ( array_chunk( $below, (int) ceil( count( $below ) / $columns ) ) as $index => $chunk ) {
					$groups[] = array( 'id' => 'col_' . $page_id . '_' . $index, 'title' => '', 'links' => $chunk );
				}
				$item = array_merge(
					$item,
					array(
						'type'              => 'mega',
						'mega_style'        => 'platforms',
						'hide_category_bar' => true,
						'columns'           => $columns,
						'categories'        => array( array( 'id' => 'cat_' . $page_id, 'title' => $item['label'], 'groups' => $groups ) ),
						'links'             => array(),
					)
				);
			}
			$items[] = $item;
		}

		return $this->with_collection_lock(
			function () use ( $items ) {
				$id = $this->create_unlocked( __( 'Main menu', 'ashbi-mega-menu' ) );
				if ( ! $id ) {
					return false;
				}
				$menus               = $this->get_all();
				$menus[ $id ]['items'] = $items;
				$menus[ $id ]          = $this->sanitize_menu( $menus[ $id ] );
				return update_option( ABMM_OPTION_KEY, $menus ) ? $id : false;
			}
		);
	}

	/**
	 * Duplicate a menu with independent nested IDs.
	 *
	 * @param string $id Existing menu ID.
	 * @return string|WP_Error New menu ID or an error.
	 */
	public function duplicate( $id ) {
		return $this->with_collection_lock( function () use ( $id ) {
			return $this->duplicate_unlocked( $id );
		} );
	}

	private function duplicate_unlocked( $id ) {
		$menu = $this->get( $id );
		if ( null === $menu ) {
			return new WP_Error( 'abmm_menu_not_found', __( 'Menu not found.', 'ashbi-mega-menu' ) );
		}
		/* translators: %s: Original menu title. */
		$title = sprintf( __( 'Copy of %s', 'ashbi-mega-menu' ), $menu['title'] );
		// Number repeated copies so the dashboard, block and widget lists can tell them apart.
		$titles = array_column( $this->get_all(), 'title' );
		$unique = $title;
		for ( $n = 2; in_array( $unique, $titles, true ); $n++ ) {
			$unique = $title . ' ' . $n;
		}
		$copy   = $this->duplicate_menu_data( $menu, $unique );
		$new_id = $this->unique_menu_id( $id . '-copy' );
		$saved = $this->save( $new_id, $copy );
		if ( is_wp_error( $saved ) ) { return $saved; }
		return $saved ? $new_id : new WP_Error( 'abmm_duplicate_failed', __( 'The menu copy could not be saved.', 'ashbi-mega-menu' ) );
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

		$panel_width   = min( 1400, max( 640, intval( $this->scalar_string( $s['panel_width'] ?? 0 ) ) ) );
		$sidebar_width = min( 420, max( 180, intval( $this->scalar_string( $s['sidebar_width'] ?? 0 ) ) ) );
		$radius        = min( 24, max( 0, intval( $this->scalar_string( $s['border_radius'] ?? 0 ) ) ) );
		$icon_size     = min( 64, max( 12, intval( $this->scalar_string( $s['icon_size'] ?? 0 ) ) ) );
		$icon_radius   = min( 24, max( 0, intval( $this->scalar_string( $s['icon_radius'] ?? 0 ) ) ) );

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
				'logo_height'   => min( 80, max( 16, intval( $this->scalar_string( $brand['logo_height'] ?? 40 ) ) ) ),
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
				'header_z_index'    => min( 99999, max( 0, intval( $this->scalar_string( $s['header_z_index'] ?? $defaults['header_z_index'] ) ) ) ),
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

			$out['featured'] = $this->sanitize_featured( $item['featured'] ?? array() );
		}

		return $out;
	}

	/**
	 * Sanitize the optional featured card of a mega item.
	 *
	 * Older menus have no card, so a missing or malformed value becomes an
	 * empty, hidden card.
	 *
	 * @param mixed $featured Raw featured card data.
	 * @return array
	 */
	private function sanitize_featured( $featured ) {
		$featured = is_array( $featured ) ? $featured : array();
		return array(
			'show'      => ! empty( $featured['show'] ) && 'false' !== $featured['show'] && '0' !== $featured['show'],
			'title'     => $this->sanitize_text( $featured['title'] ?? '' ),
			'text'      => $this->sanitize_text( $featured['text'] ?? '' ),
			'label'     => $this->sanitize_text( $featured['label'] ?? '' ),
			'url'       => $this->sanitize_menu_url( $featured['url'] ?? '' ),
			'image_url' => $this->sanitize_menu_url( $featured['image_url'] ?? '' ),
		);
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
		$allowed_rel = self::LINK_REL_TOKENS;
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
		$size   = intval( $this->scalar_string( $source['icon_size'] ?? 0 ) );
		$radius = isset( $source['icon_radius'] ) && '' !== $source['icon_radius']
			? min( 24, max( 0, intval( $this->scalar_string( $source['icon_radius'] ) ) ) )
			: '';

		return array(
			'icon_color'        => $this->sanitize_color( $source['icon_color'] ?? '' ) ?: '',
			'icon_hover_color'  => $this->sanitize_color( $source['icon_hover_color'] ?? '' ) ?: '',
			'icon_active_color' => $this->sanitize_color( $source['icon_active_color'] ?? '' ) ?: '',
			'icon_size'         => $size > 0 ? min( 64, max( 12, $size ) ) : 0,
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
		$sample = '#' . self::SAMPLE_FRAGMENT;
		$link   = function ( $label, $icon, $description ) use ( $sample ) {
			return array( 'label' => $label, 'url' => $sample, 'icon' => $icon, 'description' => $description );
		};
		$site = trim( wp_strip_all_tags( (string) get_bloginfo( 'name' ) ) );
		return array( 'menu_demo' => array(
			'title' => __( 'Main navigation sample', 'ashbi-mega-menu' ),
			'items' => array(
				array( 'id' => 'products', 'label' => __( 'Products', 'ashbi-mega-menu' ), 'url' => '#', 'type' => 'mega', 'mega_style' => 'platforms', 'columns' => 2, 'categories' => array(
					array( 'id' => 'core', 'title' => __( 'Core products', 'ashbi-mega-menu' ), 'description' => __( 'What most customers start with', 'ashbi-mega-menu' ), 'icon' => 'package', 'groups' => array(
						array( 'title' => __( 'Popular', 'ashbi-mega-menu' ), 'links' => array(
							$link( __( 'Product one', 'ashbi-mega-menu' ), 'rocket', __( 'A short line about what it does', 'ashbi-mega-menu' ) ),
							$link( __( 'Product two', 'ashbi-mega-menu' ), 'chart', __( 'Who it is for, in a few words', 'ashbi-mega-menu' ) ),
							$link( __( 'Product three', 'ashbi-mega-menu' ), 'shield', __( 'The main benefit, kept short', 'ashbi-mega-menu' ) ),
						) ),
						array( 'title' => __( 'Add-ons', 'ashbi-mega-menu' ), 'links' => array(
							$link( __( 'Integrations', 'ashbi-mega-menu' ), 'grid', __( 'Connect the tools you use', 'ashbi-mega-menu' ) ),
							$link( __( 'Support plans', 'ashbi-mega-menu' ), 'handshake', __( 'Help when you need it', 'ashbi-mega-menu' ) ),
						) ),
					) ),
					array( 'id' => 'industries', 'title' => __( 'Industries', 'ashbi-mega-menu' ), 'description' => __( 'Solutions for your field', 'ashbi-mega-menu' ), 'icon' => 'building', 'groups' => array(
						array( 'title' => __( 'Who we help', 'ashbi-mega-menu' ), 'links' => array(
							$link( __( 'Retail', 'ashbi-mega-menu' ), 'tag', __( 'Stores and online shops', 'ashbi-mega-menu' ) ),
							$link( __( 'Healthcare', 'ashbi-mega-menu' ), 'heart', __( 'Clinics and care teams', 'ashbi-mega-menu' ) ),
						) ),
						array( 'title' => __( 'By size', 'ashbi-mega-menu' ), 'links' => array(
							$link( __( 'Small business', 'ashbi-mega-menu' ), 'briefcase', __( 'Get started quickly', 'ashbi-mega-menu' ) ),
							$link( __( 'Enterprise', 'ashbi-mega-menu' ), 'globe', __( 'Scale with confidence', 'ashbi-mega-menu' ) ),
						) ),
					) ),
					array( 'id' => 'learn', 'title' => __( 'Resources', 'ashbi-mega-menu' ), 'description' => __( 'Guides, stories and help', 'ashbi-mega-menu' ), 'icon' => 'lightbulb', 'groups' => array(
						array( 'title' => __( 'Learn', 'ashbi-mega-menu' ), 'links' => array(
							$link( __( 'Guides', 'ashbi-mega-menu' ), 'document', __( 'Step-by-step how-tos', 'ashbi-mega-menu' ) ),
							$link( __( 'Customer stories', 'ashbi-mega-menu' ), 'star', __( 'See what others built', 'ashbi-mega-menu' ) ),
						) ),
						array( 'title' => __( 'Get help', 'ashbi-mega-menu' ), 'links' => array(
							$link( __( 'Help center', 'ashbi-mega-menu' ), 'search-gear', __( 'Answers to common questions', 'ashbi-mega-menu' ) ),
							$link( __( 'Contact support', 'ashbi-mega-menu' ), 'mail', __( 'Talk to a real person', 'ashbi-mega-menu' ) ),
						) ),
					) ),
				) ),
				array( 'id' => 'pricing', 'label' => __( 'Pricing', 'ashbi-mega-menu' ), 'url' => $sample, 'type' => 'link' ),
				array( 'id' => 'about', 'label' => __( 'About', 'ashbi-mega-menu' ), 'url' => $sample, 'type' => 'link' ),
				array( 'id' => 'contact', 'label' => __( 'Contact', 'ashbi-mega-menu' ), 'url' => $sample, 'type' => 'link' ),
			),
			'brand' => array( 'show' => '' !== $site, 'title' => $site, 'url' => home_url( '/' ) ),
			'cta' => array( 'show' => true, 'label' => __( 'Get started', 'ashbi-mega-menu' ), 'url' => $sample ),
			'settings' => $settings,
		) );
	}

	/**
	 * Link rel values a menu link may carry.
	 */
	const LINK_REL_TOKENS = array( 'nofollow', 'sponsored', 'ugc', 'noopener', 'noreferrer' );

	/**
	 * Marks template links that still need a real destination.
	 */
	const SAMPLE_FRAGMENT = 'abmm-sample';

	/**
	 * Whether a URL is a starter-template placeholder.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public function is_sample_url( $url ) {
		return (bool) preg_match( '/#' . self::SAMPLE_FRAGMENT . '$/', trim( (string) $url ) );
	}

	public static function demo_menus() {
		return self::generic_starter();
	}

	/**
	 * Convert a classic WordPress navigation menu into an Ashbi menu draft.
	 *
	 * A dry run: nothing is stored and the source menu is not changed.
	 * Top-level items without children become links; items with children
	 * become mega items. Their children become categories, or one columns-only
	 * category when none of them has children. Deeper levels are flattened
	 * into links, and the report lists what was flattened or skipped.
	 *
	 * @param int $term_id Navigation menu term ID.
	 * @return array|WP_Error { menu: array, report: { imported, flattened, skipped, warnings } }
	 */
	public function import_from_nav_menu( $term_id ) {
		$term_id = absint( $term_id );
		$nav     = $term_id ? wp_get_nav_menu_object( $term_id ) : false;
		if ( ! $nav ) {
			return new WP_Error( 'abmm_nav_menu_missing', __( 'That WordPress menu no longer exists.', 'ashbi-mega-menu' ) );
		}
		$items = wp_get_nav_menu_items( $nav->term_id, array( 'update_post_term_cache' => false ) );
		$rows  = array();
		foreach ( is_array( $items ) ? $items : array() as $item ) {
			$type   = (string) ( $item->type ?? '' );
			$status = 'publish';
			if ( 'post_type' === $type && ! empty( $item->object_id ) ) {
				$status = (string) get_post_status( (int) $item->object_id );
			}
			$rows[] = array(
				'id'          => (int) ( $item->db_id ?? $item->ID ?? 0 ),
				'parent'      => (int) ( $item->menu_item_parent ?? 0 ),
				'title'       => (string) ( $item->title ?? '' ),
				'url'         => (string) ( $item->url ?? '' ),
				'target'      => (string) ( $item->target ?? '' ),
				'xfn'         => (string) ( $item->xfn ?? '' ),
				'classes'     => is_array( $item->classes ?? null ) ? $item->classes : array(),
				'description' => (string) ( $item->description ?? '' ),
				'type'        => $type,
				'status'      => $status,
				'invalid'     => ! empty( $item->_invalid ),
			);
		}

		return $this->finish_nav_import( $this->convert_nav_menu_items( $rows, (string) $nav->name ) );
	}

	/**
	 * Convert a Site Editor navigation (a wp_navigation post from a block theme)
	 * with the same rules and report as a classic menu.
	 *
	 * @param int $post_id wp_navigation post ID.
	 * @return array|WP_Error { menu, report }
	 */
	public function import_from_navigation_post( $post_id ) {
		$post = get_post( absint( $post_id ) );
		if ( ! $post || 'wp_navigation' !== $post->post_type || 'trash' === $post->post_status ) {
			return new WP_Error( 'abmm_nav_menu_missing', __( 'That navigation no longer exists.', 'ashbi-mega-menu' ) );
		}
		$rows        = array();
		$next        = 0;
		$unsupported = array();
		$this->navigation_block_rows( parse_blocks( (string) $post->post_content ), 0, $rows, $next, $unsupported );
		$title  = '' !== trim( (string) $post->post_title ) ? (string) $post->post_title : __( 'Navigation', 'ashbi-mega-menu' );
		$result = $this->convert_nav_menu_items( $rows, $title );
		if ( ! is_wp_error( $result ) ) {
			foreach ( array_keys( $unsupported ) as $name ) {
				$type = class_exists( 'WP_Block_Type_Registry' ) ? WP_Block_Type_Registry::get_instance()->get_registered( $name ) : null;
				$result['report']['skipped'][] = array(
					/* translators: %s: block name, such as Search. */
					'label'  => $type && ! empty( $type->title ) ? sprintf( __( '%s block', 'ashbi-mega-menu' ), $type->title ) : $name,
					'reason' => __( 'This block is not a link, so it was not imported.', 'ashbi-mega-menu' ),
				);
			}
		}
		return $this->finish_nav_import( $result );
	}

	/** Apply the JSON importer's limits to a converted menu. */
	private function finish_nav_import( $result ) {
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( class_exists( 'ABMM_Import_Export' ) ) {
			$errors = ABMM_Import_Export::instance()->validate_payload( array( 'imported' => $result['menu'] ) );
			if ( $errors ) {
				return new WP_Error( 'abmm_nav_menu_limits', implode( ' ', $errors ) );
			}
		}
		return $result;
	}

	/**
	 * Turn navigation blocks into the rows convert_nav_menu_items() reads.
	 *
	 * @param array $blocks      Parsed blocks.
	 * @param int   $parent      Parent row ID (0 for the top level).
	 * @param array $rows        Rows, appended to.
	 * @param int   $next        Last row ID used.
	 * @param array $unsupported Names of blocks that are not links, as keys.
	 * @param int   $depth       Nesting guard.
	 */
	private function navigation_block_rows( $blocks, $parent, &$rows, &$next, &$unsupported, $depth = 0 ) {
		if ( $depth > 32 ) {
			return;
		}
		foreach ( (array) $blocks as $block ) {
			$name  = (string) ( $block['blockName'] ?? '' );
			$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
			if ( '' === $name ) {
				continue;
			}
			if ( 'core/navigation-link' === $name || 'core/navigation-submenu' === $name ) {
				$id     = ++$next;
				$rows[] = $this->navigation_link_row( $id, $parent, $attrs );
				if ( 'core/navigation-submenu' === $name ) {
					$this->navigation_block_rows( $block['innerBlocks'] ?? array(), $id, $rows, $next, $unsupported, $depth + 1 );
				}
			} elseif ( 'core/home-link' === $name ) {
				$label  = trim( wp_strip_all_tags( (string) ( $attrs['label'] ?? '' ) ) );
				$rows[] = $this->navigation_row( ++$next, $parent, '' !== $label ? $label : __( 'Home', 'ashbi-mega-menu' ), home_url( '/' ), $attrs );
			} elseif ( 'core/page-list' === $name ) {
				// The page list block shows the published page tree.
				$pages    = get_pages( array( 'sort_column' => 'menu_order,post_title', 'post_status' => 'publish' ) );
				$children = array();
				foreach ( (array) $pages as $page ) {
					$children[ (int) $page->post_parent ][] = $page;
				}
				$add = function ( $page_parent, $row_parent ) use ( &$add, &$children, &$rows, &$next ) {
					foreach ( $children[ $page_parent ] ?? array() as $page ) {
						$id     = ++$next;
						$rows[] = $this->navigation_row( $id, $row_parent, (string) $page->post_title, (string) get_permalink( $page ), array(), 'post_type' );
						$add( (int) $page->ID, $id );
					}
				};
				$add( absint( $attrs['parentPageID'] ?? 0 ), $parent );
			} else {
				$unsupported[ $name ] = true;
			}
		}
	}

	/** A row for a navigation link or submenu block. */
	private function navigation_link_row( $id, $parent, $attrs ) {
		$kind   = (string) ( $attrs['kind'] ?? '' );
		$object = absint( $attrs['id'] ?? 0 );
		$url    = (string) ( $attrs['url'] ?? '' );
		$row    = $this->navigation_row( $id, $parent, (string) ( $attrs['label'] ?? '' ), $url, $attrs, 'post-type' === $kind ? 'post_type' : 'custom' );
		// A link to a post or term follows its current address, as the block does.
		if ( $object && 'post-type' === $kind ) {
			$status = get_post_status( $object );
			if ( false === $status ) {
				$row['invalid'] = true;
			} else {
				$row['status'] = (string) $status;
				$row['url']    = (string) get_permalink( $object );
			}
		} elseif ( $object && 'taxonomy' === $kind ) {
			$link = get_term_link( $object );
			if ( is_wp_error( $link ) ) {
				$row['invalid'] = true;
			} else {
				$row['url'] = (string) $link;
			}
		}
		return $row;
	}

	private function navigation_row( $id, $parent, $label, $url, $attrs, $type = 'custom' ) {
		return array(
			'id'          => $id,
			'parent'      => $parent,
			'title'       => trim( wp_strip_all_tags( $label ) ),
			'url'         => $url,
			'target'      => ! empty( $attrs['opensInNewTab'] ) ? '_blank' : '',
			'xfn'         => (string) ( $attrs['rel'] ?? '' ),
			'classes'     => preg_split( '/\s+/', trim( (string) ( $attrs['className'] ?? '' ) ), -1, PREG_SPLIT_NO_EMPTY ),
			'description' => (string) ( $attrs['description'] ?? '' ),
			'type'        => $type,
			'status'      => 'publish',
			'invalid'     => false,
		);
	}

	/**
	 * Convert flattened navigation rows (see import_from_nav_menu()).
	 *
	 * @param array  $rows  Rows with id, parent, title, url, target, xfn,
	 *                      classes, description, type, status and invalid.
	 * @param string $title Source menu name.
	 * @return array|WP_Error
	 */
	public function convert_nav_menu_items( $rows, $title ) {
		$report = array(
			'imported'  => 0,
			'flattened' => array(),
			'skipped'   => array(),
			'warnings'  => array(),
		);
		$by_id    = array();
		$children = array( 0 => array() );
		foreach ( $rows as $row ) {
			$by_id[ $row['id'] ] = $row;
		}
		foreach ( $rows as $row ) {
			// An item whose parent is missing is shown at the top level, as WordPress does.
			$parent = isset( $by_id[ $row['parent'] ] ) ? $row['parent'] : 0;
			$children[ $parent ][] = $row['id'];
			if ( ! isset( $children[ $row['id'] ] ) ) {
				$children[ $row['id'] ] = array();
			}
		}
		if ( empty( $children[0] ) ) {
			return new WP_Error( 'abmm_nav_menu_empty', __( 'That WordPress menu has no items to import.', 'ashbi-mega-menu' ) );
		}

		// Decide which items can be imported; a skipped item takes its children with it.
		$usable = array();
		$skip   = function ( $id, $reason ) use ( &$skip, &$report, &$children, $by_id ) {
			$report['skipped'][] = array( 'label' => $this->nav_row_label( $by_id[ $id ] ), 'reason' => $reason );
			foreach ( $children[ $id ] as $child ) {
				$skip( $child, __( 'Its parent item was skipped.', 'ashbi-mega-menu' ) );
			}
		};
		$check = function ( $id ) use ( &$check, &$skip, &$usable, &$children, $by_id ) {
			$row    = $by_id[ $id ];
			$reason = $this->nav_row_problem( $row );
			if ( '' !== $reason ) {
				$skip( $id, $reason );
				return;
			}
			$usable[ $id ] = true;
			foreach ( $children[ $id ] as $child ) {
				$check( $child );
			}
		};
		foreach ( $children[0] as $id ) {
			$check( $id );
		}
		$kids = function ( $id ) use ( &$children, &$usable ) {
			return array_values( array_filter( $children[ $id ], function ( $child ) use ( &$usable ) {
				return isset( $usable[ $child ] );
			} ) );
		};

		// Flatten a subtree into links, depth first, noting levels below depth 3.
		$flatten = function ( $id, $depth, $under ) use ( &$flatten, &$report, $kids, $by_id ) {
			$links = array();
			foreach ( $kids( $id ) as $child ) {
				$links[] = $this->nav_row_link( $by_id[ $child ] );
				$report['imported']++;
				if ( $depth > 3 ) {
					$report['flattened'][] = array(
						'label' => $this->nav_row_label( $by_id[ $child ] ),
						'depth' => $depth,
						/* translators: %s: category name. */
						'into'  => sprintf( __( 'a link in "%s"', 'ashbi-mega-menu' ), $under ),
					);
				}
				$links = array_merge( $links, $flatten( $child, $depth + 1, $under ) );
			}
			return $links;
		};

		// Only a few rel values are supported; say which XFN values are dropped.
		foreach ( array_keys( $usable ) as $id ) {
			if ( '' === trim( $by_id[ $id ]['url'] ) && ! $kids( $id ) ) {
				$report['warnings'][] = sprintf(
					/* translators: %s: menu item label. */
					__( '"%s" has no URL. Add one in the editor, or it shows as plain text.', 'ashbi-mega-menu' ),
					$this->nav_row_label( $by_id[ $id ] )
				);
			}
			$dropped = array_diff( preg_split( '/\s+/', strtolower( trim( $by_id[ $id ]['xfn'] ) ), -1, PREG_SPLIT_NO_EMPTY ), self::LINK_REL_TOKENS );
			if ( $dropped ) {
				$report['warnings'][] = sprintf(
					/* translators: 1: rel values, 2: menu item label. */
					__( 'The link relationship "%1$s" on "%2$s" is not supported and was not imported.', 'ashbi-mega-menu' ),
					implode( ' ', $dropped ),
					$this->nav_row_label( $by_id[ $id ] )
				);
			}
		}

		$items = array();
		$top   = $kids( 0 );
		$this->flag_duplicate_labels( $top, $by_id, '', $report );
		foreach ( $top as $id ) {
			$row  = $by_id[ $id ];
			$item = array_merge(
				array(
					'id'    => 'wp_' . $id,
					'label' => trim( $row['title'] ),
					'url'   => trim( $row['url'] ),
				),
				$this->nav_row_meta( $row )
			);
			$report['imported']++;
			if ( '' !== trim( $row['description'] ) ) {
				$report['warnings'][] = sprintf(
					/* translators: %s: menu item label. */
					__( 'The description of "%s" was not imported: top-level items have no description.', 'ashbi-mega-menu' ),
					$this->nav_row_label( $row )
				);
			}
			$second = $kids( $id );
			if ( ! $second ) {
				$items[] = array_merge( $item, array( 'type' => 'link' ) );
				continue;
			}

			$this->flag_duplicate_labels( $second, $by_id, $row['title'], $report );
			$item = array_merge( $item, array( 'type' => 'mega', 'mega_style' => 'platforms', 'columns' => 3, 'categories' => array(), 'links' => array() ) );
			$has_third = false;
			foreach ( $second as $child ) {
				if ( $kids( $child ) ) {
					$has_third = true;
					break;
				}
			}

			if ( ! $has_third ) {
				// One category without a category bar: the children are its links.
				$links = array();
				foreach ( $second as $child ) {
					$links[] = $this->nav_row_link( $by_id[ $child ] );
					$report['imported']++;
				}
				// The renderer draws one column per group: split long lists evenly
				// into up to four columns of about six links.
				$columns = max( 1, min( 4, (int) ceil( count( $links ) / 6 ) ) );
				$groups  = array();
				foreach ( array_chunk( $links, (int) ceil( count( $links ) / $columns ) ) as $index => $chunk ) {
					$groups[] = array( 'id' => 'wpcol_' . $id . ( $index ? '_' . $index : '' ), 'title' => '', 'links' => $chunk );
				}
				$item['hide_category_bar'] = true;
				$item['columns']           = $columns;
				$item['categories'][]      = array(
					'id'     => 'wpcat_' . $id,
					'title'  => trim( $row['title'] ),
					'groups' => $groups,
				);
			} else {
				foreach ( $second as $child ) {
					$cat_row = $by_id[ $child ];
					$report['imported']++;
					$this->flag_duplicate_labels( $kids( $child ), $by_id, $cat_row['title'], $report );
					$cat_url = trim( $cat_row['url'] );
					$item['categories'][] = array_merge(
						array(
							'id'          => 'wpcat_' . $child,
							'title'       => trim( $cat_row['title'] ),
							'description' => trim( $cat_row['description'] ),
							// The category's own page becomes its "see all" link.
							'panel_title' => '' !== $cat_url && '#' !== $cat_url ? trim( $cat_row['title'] ) : '',
							'panel_url'   => '#' !== $cat_url ? $cat_url : '',
							'panel_link'  => $this->nav_row_meta( $cat_row ),
							'groups'      => array( array( 'id' => 'wpcol_' . $child, 'title' => '', 'links' => $flatten( $child, 3, trim( $cat_row['title'] ) ) ) ),
						)
					);
				}
			}
			$items[] = $item;
		}

		$menu = $this->sanitize_menu(
			array(
				/* translators: %s: WordPress menu name. */
				'title'    => sprintf( __( '%s (imported)', 'ashbi-mega-menu' ), '' !== trim( $title ) ? trim( $title ) : __( 'WordPress menu', 'ashbi-mega-menu' ) ),
				'items'    => $items,
				'cta'      => array( 'label' => '', 'url' => '', 'show' => false ),
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
		return array( 'menu' => $menu, 'report' => $report );
	}

	/**
	 * Store a converted menu as a new, unplaced draft.
	 *
	 * @param array $menu Converted menu data.
	 * @return string|WP_Error New menu ID.
	 */
	public function create_from_import( $menu ) {
		return $this->with_collection_lock( function () use ( $menu ) {
			$id    = $this->unique_menu_id( 'menu_' . wp_generate_password( 8, false, false ) );
			$menus = $this->get_all();
			$menus[ $id ] = $this->sanitize_menu( $menu );
			if ( ! update_option( ABMM_OPTION_KEY, $menus ) ) {
				return new WP_Error( 'abmm_nav_menu_save', __( 'The imported menu could not be saved.', 'ashbi-mega-menu' ) );
			}
			return $id;
		} );
	}

	/** Why a navigation menu row cannot be imported, or '' when it can. */
	private function nav_row_problem( $row ) {
		if ( ! empty( $row['invalid'] ) ) {
			return __( 'The page or post it links to no longer exists.', 'ashbi-mega-menu' );
		}
		if ( 'publish' !== $row['status'] ) {
			return __( 'It links to content that is not published.', 'ashbi-mega-menu' );
		}
		if ( '' === trim( wp_strip_all_tags( $row['title'] ) ) ) {
			return __( 'It has no label.', 'ashbi-mega-menu' );
		}
		$url = trim( $row['url'] );
		if ( '' !== $url && '#' !== $url && '' === $this->sanitize_menu_url( $url ) ) {
			return __( 'Its URL is not a safe web address.', 'ashbi-mega-menu' );
		}
		return '';
	}

	private function nav_row_label( $row ) {
		$label = trim( wp_strip_all_tags( (string) $row['title'] ) );
		/* translators: %d: navigation menu item ID. */
		return '' !== $label ? $label : sprintf( __( 'Untitled item #%d', 'ashbi-mega-menu' ), (int) $row['id'] );
	}

	private function nav_row_meta( $row ) {
		$classes = array_filter( array_map( 'trim', (array) $row['classes'] ), 'strlen' );
		return array(
			'target' => '_blank' === $row['target'] ? '_blank' : '',
			'rel'    => trim( $row['xfn'] ),
			'class'  => implode( ' ', $classes ),
		);
	}

	private function nav_row_link( $row ) {
		return array_merge(
			array(
				'id'          => 'wp_' . $row['id'],
				'label'       => trim( $row['title'] ),
				'url'         => trim( $row['url'] ),
				'description' => trim( $row['description'] ),
				'icon'        => '',
			),
			$this->nav_row_meta( $row )
		);
	}

	/** Warn about sibling items that share a label, which visitors cannot tell apart. */
	private function flag_duplicate_labels( $ids, $by_id, $parent_label, &$report ) {
		$seen = array();
		foreach ( $ids as $id ) {
			$key = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( $by_id[ $id ]['title'] ) ) : strtolower( trim( $by_id[ $id ]['title'] ) );
			if ( isset( $seen[ $key ] ) && 1 === $seen[ $key ] ) {
				$report['warnings'][] = '' === $parent_label
					/* translators: %s: menu item label. */
					? sprintf( __( 'More than one top-level item is called "%s".', 'ashbi-mega-menu' ), $this->nav_row_label( $by_id[ $id ] ) )
					/* translators: 1: menu item label, 2: parent item label. */
					: sprintf( __( 'More than one item under "%2$s" is called "%1$s".', 'ashbi-mega-menu' ), $this->nav_row_label( $by_id[ $id ] ), trim( $parent_label ) );
			}
			$seen[ $key ] = ( $seen[ $key ] ?? 0 ) + 1;
		}
	}
}
