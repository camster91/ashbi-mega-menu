<?php
/**
 * Switching from Easy Mega Menu.
 *
 * Reads Easy Mega Menu's stored data (menus, product profiles, product
 * branding and page assignments) without ever writing to it and copies it into
 * Ashbi's own storage with the same menu IDs. Imported menus use classic mode
 * (ABMM_Classic), so visitors see the same header. Finish switching rewrites
 * [easy_mega_menu] to [ashbi_mega_menu] in site content, with a backup. Any
 * code it misses is still answered whenever Easy Mega Menu is not running.
 *
 * @package Ashbi_Mega_Menu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABMM_EMM_Migration {

	const SOURCE_MENUS     = 'emm_menus';
	const SOURCE_PROFILES  = 'emm_product_profiles';
	const SOURCE_BRANDING  = 'emm_product_branding';
	const SOURCE_META      = '_emm_product_profile';
	const TAKEOVER_OPTION  = 'abmm_emm_takeover';
	const STATE_OPTION     = 'abmm_emm_import_state';
	const PAGE_SLUG        = 'ashbi-mega-menu-easy-import';
	const BACKUP_OPTION    = 'abmm_emm_switch_backup';
	const ORIGIN_OPTION    = 'abmm_emm_menu_ids';

	/**
	 * Easy Mega Menu nudged this one menu's open panel 8px past its host
	 * container in its script. Imported menus keep that as a setting.
	 */
	const LEGACY_EDGE_BLEED = array( 'menu_demo' => 8 );

	/**
	 * @var ABMM_EMM_Migration|null
	 */
	private static $instance = null;

	/**
	 * @return ABMM_EMM_Migration
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'answer_old_shortcodes' ), 20 );
		add_action( 'admin_menu', array( $this, 'register_page' ), 30 );
		add_action( 'admin_post_abmm_emm_import', array( $this, 'handle_import' ) );
		add_action( 'admin_post_abmm_emm_finish', array( $this, 'handle_finish' ) );
		add_action( 'admin_post_abmm_emm_undo', array( $this, 'handle_undo' ) );
		add_action( 'admin_notices', array( $this, 'missing_menus_notice' ) );
		add_action( 'deleted_plugin', array( $this, 'forget_source_plugin' ) );
		add_action( 'activated_plugin', array( $this, 'forget_source_plugin' ) );
		add_action( 'upgrader_process_complete', array( $this, 'forget_source_plugin' ) );
	}

	/* ------------------------------------------------------------------
	 * Source (read-only)
	 * ---------------------------------------------------------------- */

	/**
	 * Whether Easy Mega Menu is running in this request.
	 *
	 * @return bool
	 */
	public static function source_active() {
		return ABMM_Classic::source_active();
	}

	/**
	 * Easy Mega Menu's stored data. Never modified.
	 *
	 * @return array{menus:array,profiles:array,branding:array}
	 */
	public function read_source() {
		$menus    = get_option( self::SOURCE_MENUS, array() );
		$profiles = get_option( self::SOURCE_PROFILES, array() );
		$branding = get_option( self::SOURCE_BRANDING, array() );
		return array(
			'menus'    => is_array( $menus ) ? $menus : array(),
			'profiles' => is_array( $profiles ) ? $profiles : array(),
			'branding' => is_array( $branding ) ? $branding : array(),
		);
	}

	/**
	 * Whether there is anything to import.
	 *
	 * @return bool
	 */
	public function has_source() {
		$source = $this->read_source();
		return ! empty( $source['menus'] ) || ! empty( $source['profiles'] );
	}

	/**
	 * Easy Mega Menu's plugin folder on disk, located by its header.
	 *
	 * @return array{dir:string,version:string}|null
	 */
	public function source_plugin() {
		if ( ! function_exists( 'get_plugins' ) ) {
			$file = ABSPATH . 'wp-admin/includes/plugin.php';
			if ( ! is_readable( $file ) ) {
				return null;
			}
			require_once $file;
		}
		foreach ( get_plugins() as $file => $data ) {
			if ( 'Easy Mega Menu' === ( $data['Name'] ?? '' ) ) {
				return array(
					'dir'     => trailingslashit( WP_PLUGIN_DIR ) . dirname( $file ),
					'version' => (string) ( $data['Version'] ?? '' ),
				);
			}
		}
		return null;
	}

	/**
	 * Whether Easy Mega Menu's files are on disk. Asked on every admin screen, so
	 * the answer is kept until a plugin is added, changed or deleted.
	 *
	 * @return bool
	 */
	public function source_installed() {
		$cached = get_transient( 'abmm_emm_source_installed' );
		if ( 'yes' === $cached || 'no' === $cached ) {
			return 'yes' === $cached;
		}
		$installed = null !== $this->source_plugin();
		set_transient( 'abmm_emm_source_installed', $installed ? 'yes' : 'no', HOUR_IN_SECONDS );
		return $installed;
	}

	/**
	 * Check again after a plugin is added, changed or deleted.
	 */
	public function forget_source_plugin() {
		delete_transient( 'abmm_emm_source_installed' );
	}

	/**
	 * Keep what still lives only with Easy Mega Menu, so it can be deleted. Runs
	 * once on upgrade and again at any time: it never replaces existing data.
	 */
	public function upgrade() {
		$this->remember_source_menus();
		$menus   = ABMM_Data::instance()->get_all();
		$classic = array_filter( $menus, array( 'ABMM_Classic', 'is_classic' ) );
		if ( ! $classic && empty( get_option( self::STATE_OPTION ) ) ) {
			return;
		}
		if ( $classic && ! $this->assets_copied() ) {
			$this->copy_assets();
		}
		$state    = $this->import_state();
		$profiles = ABMM_Product_Profiles::instance()->get_all();
		$changed  = false;
		foreach ( $this->page_assignments() as $post_id => $profile ) {
			// An assignment that is gone from a page imported earlier was removed on purpose.
			if ( isset( $state['pages'][ $post_id ] ) || ! isset( $profiles[ $profile ] ) || '' !== (string) get_post_meta( $post_id, ABMM_Page_Context::META_KEY, true ) ) {
				continue;
			}
			if ( update_post_meta( $post_id, ABMM_Page_Context::META_KEY, $profile ) ) {
				$state['pages'][ $post_id ] = $profile;
				$changed                    = true;
			}
		}
		if ( $changed ) {
			update_option( self::STATE_OPTION, $state, false );
		}
	}

	/**
	 * Record which menus came from Easy Mega Menu, which the editor uses to offer
	 * the look they started with. Its own list may not outlive the plugin.
	 */
	private function remember_source_menus() {
		$known = get_option( self::ORIGIN_OPTION, array() );
		$known = is_array( $known ) ? $known : array();
		$ids   = array_filter( array_map( 'sanitize_key', array_map( 'strval', array_keys( $this->read_source()['menus'] ) ) ) );
		$merge = array_values( array_unique( array_merge( $known, $ids ) ) );
		if ( $merge !== $known ) {
			update_option( self::ORIGIN_OPTION, $merge, false );
		}
	}

	/**
	 * Whether this site's own copies of the styles and icons are in place.
	 *
	 * @return bool
	 */
	public function assets_copied() {
		$site = ABMM_Classic::instance()->site_assets();
		$dir  = ABMM_Classic::upload_dir();
		return '' !== $dir && '' !== $site['css'] && '' !== $site['sprite'] && is_readable( $dir . '/' . $site['css'] ) && is_readable( $dir . '/' . $site['sprite'] );
	}

	/* ------------------------------------------------------------------
	 * Mapping
	 * ---------------------------------------------------------------- */

	/**
	 * Convert one Easy Mega Menu menu to an Ashbi classic menu.
	 *
	 * @param string $id   Menu ID (kept).
	 * @param array  $menu Easy Mega Menu data.
	 * @param array  $icon_keys Icons available in the classic sprite.
	 * @return array{menu:array,issues:string[]}
	 */
	public function map_menu( $id, $menu, $icon_keys = array() ) {
		$menu     = is_array( $menu ) ? $menu : array();
		$settings = is_array( $menu['settings'] ?? null ) ? $menu['settings'] : array();
		// Fill Easy Mega Menu's own defaults first: Ashbi's differ for a few keys.
		$settings                = array_merge( ABMM_Classic::default_settings(), $settings );
		$settings['render_mode'] = ABMM_Classic::RENDER_MODE;
		if ( isset( self::LEGACY_EDGE_BLEED[ $id ] ) ) {
			$settings['classic_edge_bleed'] = self::LEGACY_EDGE_BLEED[ $id ];
		}
		$source             = $menu;
		$source['settings'] = $settings;
		$mapped             = ABMM_Data::instance()->sanitize_menu( $source );

		// Plain links can carry leftover dropdown fields that neither plugin uses.
		$compared = $source;
		foreach ( (array) ( $compared['items'] ?? array() ) as $index => $item ) {
			if ( is_array( $item ) && 'mega' !== ( $item['type'] ?? 'link' ) ) {
				unset( $compared['items'][ $index ]['mega_style'], $compared['items'][ $index ]['hide_category_bar'], $compared['items'][ $index ]['columns'], $compared['items'][ $index ]['categories'], $compared['items'][ $index ]['links'] );
			}
		}
		$issues = array();
		$this->compare( $compared, $mapped, '', $issues );
		if ( $icon_keys ) {
			foreach ( $this->icon_names( $mapped ) as $icon ) {
				if ( ! isset( $icon_keys[ $icon ] ) ) {
					$issues[] = sprintf( /* translators: %s: icon name. */ __( 'Icon "%s" is not in the copied icon set; the link icon is shown instead.', 'ashbi-mega-menu' ), $icon );
				}
			}
		}
		return array(
			'menu'   => $mapped,
			'issues' => array_values( array_unique( $issues ) ),
		);
	}

	/**
	 * Record values that did not survive sanitizing unchanged.
	 *
	 * @param mixed  $source Source value.
	 * @param mixed  $mapped Sanitized value.
	 * @param string $path   Readable path.
	 * @param array  $issues Collected issues.
	 */
	private function compare( $source, $mapped, $path, &$issues ) {
		if ( is_array( $source ) ) {
			foreach ( $source as $key => $value ) {
				$child = '' === $path ? (string) $key : $path . '.' . $key;
				if ( ! is_array( $mapped ) || ! array_key_exists( $key, $mapped ) ) {
					if ( ! $this->is_empty_value( $value ) ) {
						$issues[] = sprintf( /* translators: %s: data path such as items.0.label. */ __( '%s was not copied (Ashbi has no matching setting).', 'ashbi-mega-menu' ), $child );
					}
					continue;
				}
				$this->compare( $value, $mapped[ $key ], $child, $issues );
			}
			return;
		}
		if ( is_array( $mapped ) || ! $this->same_scalar( $source, $mapped ) ) {
			$issues[] = sprintf( /* translators: %s: data path such as items.0.url. */ __( '%s changed while copying.', 'ashbi-mega-menu' ), $path );
		}
	}

	private function is_empty_value( $value ) {
		return null === $value || '' === $value || false === $value || 0 === $value || '0' === $value || array() === $value;
	}

	private function same_scalar( $a, $b ) {
		if ( is_bool( $a ) || is_bool( $b ) ) {
			return (bool) $a === (bool) $b;
		}
		if ( is_numeric( $a ) && is_numeric( $b ) ) {
			return (float) $a === (float) $b;
		}
		return (string) $a === (string) $b;
	}

	/**
	 * Icon names used by a menu.
	 *
	 * @param array $menu Menu.
	 * @return string[]
	 */
	private function icon_names( $menu ) {
		$names = array();
		$walk  = function ( $node ) use ( &$walk, &$names ) {
			if ( ! is_array( $node ) ) {
				return;
			}
			if ( ! empty( $node['icon'] ) && is_string( $node['icon'] ) && empty( $node['icon_url'] ) ) {
				$names[ $node['icon'] ] = true;
			}
			foreach ( $node as $child ) {
				$walk( $child );
			}
		};
		$walk( $menu['items'] ?? array() );
		return array_keys( $names );
	}

	/**
	 * Everything an import would do, without changing anything.
	 *
	 * @return array
	 */
	public function preview() {
		$source   = $this->read_source();
		$existing = ABMM_Data::instance()->get_all();
		$plugin   = $this->source_plugin();
		$sprite   = $plugin ? $plugin['dir'] . '/assets/icons/emm-sprite.svg' : '';
		$keys     = $sprite && is_readable( $sprite ) ? ABMM_Classic_Icons::sprite_keys( $sprite ) : ABMM_Classic_Icons::sprite_keys( ABMM_PLUGIN_DIR . 'assets/classic/emm-sprite.svg' );
		$menus    = array();
		foreach ( $source['menus'] as $id => $menu ) {
			$id = sanitize_key( (string) $id );
			if ( '' === $id || ! is_array( $menu ) ) {
				continue;
			}
			$mapped  = $this->map_menu( $id, $menu, $keys );
			$menus[] = array(
				'id'       => $id,
				'title'    => $mapped['menu']['title'],
				'items'    => count( $mapped['menu']['items'] ),
				'links'    => $this->count_links( $mapped['menu'] ),
				'conflict' => isset( $existing[ $id ] ) && ! ABMM_Classic::is_classic( $existing[ $id ] ),
				'exists'   => isset( $existing[ $id ] ),
				// An earlier import that no longer matches Easy Mega Menu: usually Ashbi edits.
				'edited'   => isset( $existing[ $id ] ) && ABMM_Classic::is_classic( $existing[ $id ] ) && ABMM_Data::instance()->revision( self::without_ids( ABMM_Data::instance()->sanitize_menu( $mapped['menu'] ) ) ) !== ABMM_Data::instance()->revision( self::without_ids( $existing[ $id ] ) ),
				'issues'   => $mapped['issues'],
			);
		}
		return array(
			'menus'        => $menus,
			'profiles'     => count( $source['profiles'] ),
			'branding'     => count( $source['branding'] ),
			'assignments'  => count( $this->page_assignments() ),
			'plugin'       => $plugin,
			'assets_ready' => $plugin && is_readable( $plugin['dir'] . '/assets/css/frontend.css' ) && is_readable( $sprite ),
			'assets_kept'  => $this->assets_copied(),
		);
	}

	/**
	 * A menu without its item IDs. Items that have none get a new random ID each
	 * time they are read, which says nothing about whether anyone edited them.
	 *
	 * @param array $menu Menu or part of one.
	 * @return array
	 */
	private static function without_ids( $menu ) {
		unset( $menu['id'] );
		foreach ( $menu as $key => $value ) {
			if ( is_array( $value ) ) {
				$menu[ $key ] = self::without_ids( $value );
			}
		}
		return $menu;
	}

	private function count_links( $menu ) {
		$count = 0;
		$walk  = function ( $node ) use ( &$walk, &$count ) {
			if ( ! is_array( $node ) ) {
				return;
			}
			if ( isset( $node['label'], $node['url'] ) ) {
				++$count;
			}
			foreach ( $node as $child ) {
				$walk( $child );
			}
		};
		$walk( $menu['items'] ?? array() );
		return $count;
	}

	/**
	 * Posts that Easy Mega Menu assigned to a product profile.
	 *
	 * @return array<int,string> Post ID => profile ID.
	 */
	public function page_assignments() {
		global $wpdb;
		if ( ! isset( $wpdb ) ) {
			return array();
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-off read of another plugin's post meta; no API lists it.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s", self::SOURCE_META ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$profile = sanitize_key( (string) $row['meta_value'] );
			if ( '' !== $profile ) {
				$out[ absint( $row['post_id'] ) ] = $profile;
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------
	 * Import
	 * ---------------------------------------------------------------- */

	/**
	 * Copy Easy Mega Menu's data into Ashbi. Existing Ashbi menus with the same
	 * ID are kept unless $overwrite is true; earlier classic imports are updated.
	 *
	 * @param bool          $overwrite Replace Ashbi menus that share an ID.
	 * @param string[]|null $only      Copy only these menu IDs; null copies every menu.
	 * @return array|WP_Error Summary.
	 */
	public function import( $overwrite = false, $only = null ) {
		$source = $this->read_source();
		if ( empty( $source['menus'] ) ) {
			return new WP_Error( 'abmm_emm_empty', __( 'Easy Mega Menu has no menus to import.', 'ashbi-mega-menu' ) );
		}

		$this->remember_source_menus();
		$assets   = $this->copy_assets();
		$existing = ABMM_Data::instance()->get_all();
		$menus    = array();
		$skipped  = array();
		foreach ( $source['menus'] as $id => $menu ) {
			$id = sanitize_key( (string) $id );
			if ( '' === $id || ! is_array( $menu ) ) {
				continue;
			}
			if ( is_array( $only ) && ! in_array( $id, $only, true ) ) {
				if ( isset( $existing[ $id ] ) ) {
					$skipped[] = $id;
				}
				continue;
			}
			if ( isset( $existing[ $id ] ) && ! $overwrite && ! ABMM_Classic::is_classic( $existing[ $id ] ) ) {
				$skipped[] = $id;
				continue;
			}
			$menus[ $id ] = $this->map_menu( $id, $menu )['menu'];
			if ( isset( $existing[ $id ] ) ) {
				// Lets this one menu be rolled back, without restoring a whole snapshot.
				ABMM_Data::instance()->remember_version( $id, $existing[ $id ], true );
			}
		}

		$result = array();
		if ( $menus ) {
			$result = ABMM_Import_Export::instance()->import_json(
				array(
					'format'         => 'ashbi-mega-menu',
					'format_version' => ABMM_Import_Export::FORMAT_VERSION,
					'menus'          => $menus,
				),
				'merge'
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		// Ticking a menu row replaces that menu only, never product records made in Ashbi.
		$contextual = $this->import_context( $source, null === $only && $overwrite );
		if ( is_wp_error( $contextual ) ) {
			return $contextual;
		}
		return array(
			'menus'         => $result,
			'skipped'       => $skipped,
			'assets'        => $assets,
			'profiles'      => $contextual['profiles'],
			'branding'      => $contextual['branding'],
			'pages'         => $contextual['pages'],
			'kept_profiles' => $contextual['kept_profiles'],
			'kept_branding' => $contextual['kept_branding'],
		);
	}

	/**
	 * Copy product profiles, branding and page assignments into Ashbi's storage.
	 *
	 * Records this import made earlier are refreshed on every run, so later
	 * changes in Easy Mega Menu come across. Records an administrator created
	 * in Ashbi are kept unless $overwrite is true. Branding is only copied when
	 * the menu it decorates was imported, so it never attaches to an unrelated
	 * Ashbi menu that happens to share an ID.
	 *
	 * @param array $source    Source data.
	 * @param bool  $overwrite Replace Ashbi records that share an ID.
	 * @return array|WP_Error Counts and kept IDs.
	 */
	private function import_context( $source, $overwrite = false ) {
		$counts      = array( 'profiles' => 0, 'branding' => 0, 'pages' => 0, 'kept_profiles' => array(), 'kept_branding' => array() );
		$assignments = $this->page_assignments();
		if ( empty( $source['profiles'] ) && empty( $source['branding'] ) && empty( $assignments ) ) {
			return $counts;
		}
		update_option( ABMM_CONTEXTUAL_NAV_OPTION, true );
		$state = $this->import_state();
		$menus = ABMM_Data::instance()->get_all();

		$stored = ABMM_Option_Store::locked(
			function () use ( $source, $overwrite, &$state, &$counts, $menus ) {
				$profiles_store = ABMM_Product_Profiles::instance();
				$profiles       = $profiles_store->get_all();
				foreach ( $source['profiles'] as $id => $profile ) {
					$id = sanitize_key( (string) $id );
					if ( '' === $id || ! is_array( $profile ) ) {
						continue;
					}
					if ( isset( $profiles[ $id ] ) && ! $overwrite && ! in_array( $id, $state['profiles'], true ) ) {
						$counts['kept_profiles'][] = $id;
						continue;
					}
					$profiles[ $id ]     = $profiles_store->sanitize_profile( $profile );
					$state['profiles'][] = $id;
					++$counts['profiles'];
				}

				$branding_store = ABMM_Product_Branding::instance();
				$branding       = $branding_store->get_all();
				foreach ( $source['branding'] as $id => $config ) {
					$id = sanitize_key( (string) $id );
					if ( '' === $id || ! is_array( $config ) ) {
						continue;
					}
					$config = $branding_store->sanitize_config( $config );
					$shared = $config['shared_menu_id'];
					$own    = isset( $branding[ $id ] ) && ! $overwrite && ! in_array( $id, $state['branding'], true );
					if ( $own || ! isset( $profiles[ $id ] ) || ( '' !== $shared && ! ABMM_Classic::is_classic( $menus[ $shared ] ?? null ) ) ) {
						$counts['kept_branding'][] = $id;
						continue;
					}
					$branding[ $id ]     = $config;
					$state['branding'][] = $id;
					++$counts['branding'];
				}

				$saved = ABMM_Option_Store::persist( ABMM_Product_Profiles::OPTION_KEY, $profiles );
				return is_wp_error( $saved ) ? $saved : ABMM_Option_Store::persist( ABMM_Product_Branding::OPTION_KEY, $branding );
			}
		);
		if ( is_wp_error( $stored ) ) {
			return new WP_Error( $stored->get_error_code(), __( 'The menus were imported, but Product Navigation could not be saved. Run the import again.', 'ashbi-mega-menu' ) );
		}

		foreach ( $assignments as $post_id => $profile ) {
			$current = (string) get_post_meta( $post_id, ABMM_Page_Context::META_KEY, true );
			$ours    = isset( $state['pages'][ $post_id ] ) && $state['pages'][ $post_id ] === $current;
			if ( $current === $profile || ( '' !== $current && ! $ours && ! $overwrite ) ) {
				continue;
			}
			if ( update_post_meta( $post_id, ABMM_Page_Context::META_KEY, $profile ) ) {
				$state['pages'][ $post_id ] = $profile;
				++$counts['pages'];
			}
		}

		$state['profiles'] = array_values( array_unique( $state['profiles'] ) );
		$state['branding'] = array_values( array_unique( $state['branding'] ) );
		update_option( self::STATE_OPTION, $state, false );
		return $counts;
	}

	/**
	 * Which profiles, branding and page assignments earlier imports created.
	 *
	 * @return array{profiles:string[],branding:string[],pages:array<int,string>}
	 */
	private function import_state() {
		$state = get_option( self::STATE_OPTION, array() );
		$state = is_array( $state ) ? $state : array();
		return array(
			'profiles' => array_map( 'sanitize_key', (array) ( $state['profiles'] ?? array() ) ),
			'branding' => array_map( 'sanitize_key', (array) ( $state['branding'] ?? array() ) ),
			'pages'    => array_map( 'sanitize_key', (array) ( $state['pages'] ?? array() ) ),
		);
	}

	/**
	 * Copy Easy Mega Menu's stylesheet and icon sprite into this site's uploads
	 * so classic menus keep the exact styles and icon artwork after Easy Mega
	 * Menu is deactivated. Easy Mega Menu's own files are only read.
	 *
	 * @return array{copied:bool,message:string}
	 */
	public function copy_assets() {
		$plugin = $this->source_plugin();
		if ( ! $plugin ) {
			if ( $this->assets_copied() ) {
				return array( 'copied' => true, 'message' => __( 'The styles and icons copied earlier are still in place.', 'ashbi-mega-menu' ) );
			}
			return array( 'copied' => false, 'message' => __( 'Easy Mega Menu files were not found, so the bundled styles and generic icons are used.', 'ashbi-mega-menu' ) );
		}
		$css    = $plugin['dir'] . '/assets/css/frontend.css';
		$sprite = $plugin['dir'] . '/assets/icons/emm-sprite.svg';
		if ( ! is_readable( $css ) || ! is_readable( $sprite ) ) {
			return array( 'copied' => false, 'message' => __( 'Easy Mega Menu styles or icons could not be read.', 'ashbi-mega-menu' ) );
		}
		$dir = ABMM_Classic::upload_dir();
		if ( '' === $dir || ! wp_mkdir_p( $dir ) ) {
			return array( 'copied' => false, 'message' => __( 'The uploads folder is not writable.', 'ashbi-mega-menu' ) );
		}
		// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin files.
		$css_text    = (string) file_get_contents( $css );
		$sprite_text = self::sanitize_sprite( (string) file_get_contents( $sprite ) );
		// phpcs:enable
		if ( '' === $css_text || '' === $sprite_text || false !== stripos( $css_text, '</style' ) ) {
			return array( 'copied' => false, 'message' => __( 'Easy Mega Menu styles or icons were not usable.', 'ashbi-mega-menu' ) );
		}
		$version = preg_replace( '/[^A-Za-z0-9._-]/', '', $plugin['version'] );
		// Content-hashed names: a changed file always gets a new URL, so page
		// caches and CDNs never keep serving an older copy.
		$files = array(
			'css'    => 'emm-frontend-' . substr( md5( $css_text ), 0, 10 ) . '.css',
			'sprite' => 'emm-sprite-' . substr( md5( $sprite_text ), 0, 10 ) . '.svg',
		);
		if ( ! $this->write_file( $dir . '/' . $files['css'], $css_text ) || ! $this->write_file( $dir . '/' . $files['sprite'], $sprite_text ) ) {
			return array( 'copied' => false, 'message' => __( 'The copied styles could not be saved.', 'ashbi-mega-menu' ) );
		}
		update_option(
			ABMM_Classic::ASSETS_OPTION,
			array(
				'css'     => $files['css'],
				'sprite'  => $files['sprite'],
				'version' => '' !== $version ? $version : substr( md5( $css_text ), 0, 10 ),
				'source'  => $plugin['version'],
				// Read once here so pages never parse the sprite to know its icons.
				'keys'    => array_keys( ABMM_Classic_Icons::sprite_keys( $dir . '/' . $files['sprite'] ) ),
			)
		);
		// Remove copies from earlier imports; only the files just recorded are used.
		foreach ( (array) glob( $dir . '/emm-*' ) as $old ) {
			if ( is_string( $old ) && ! in_array( basename( $old ), $files, true ) ) {
				wp_delete_file( $old );
			}
		}
		return array( 'copied' => true, 'message' => __( 'Easy Mega Menu styles and icons were copied.', 'ashbi-mega-menu' ) );
	}

	/**
	 * Keep only drawing elements in a sprite: no scripts, event handlers or
	 * external references.
	 *
	 * @param string $svg Sprite markup.
	 * @return string
	 */
	public static function sanitize_sprite( $svg ) {
		$svg = preg_replace( '#<(script|foreignObject|style|iframe|image)\b[^>]*>.*?</\1\s*>#is', '', $svg );
		$svg = preg_replace( '#<(script|foreignObject|style|iframe|image)\b[^>]*/\s*>#is', '', $svg );
		$svg = preg_replace( '/\s(on[a-z]+|xlink:href|href)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $svg );
		return false !== stripos( (string) $svg, '<svg' ) ? (string) $svg : '';
	}

	/**
	 * @param string $path File path.
	 * @param string $data Contents.
	 * @return bool
	 */
	private function write_file( $path, $data ) {
		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! WP_Filesystem() || ! $wp_filesystem ) {
			return false;
		}
		return (bool) $wp_filesystem->put_contents( $path, $data, FS_CHMOD_FILE );
	}

	/* ------------------------------------------------------------------
	 * Finish switching
	 * ---------------------------------------------------------------- */

	/**
	 * Change the old shortcode's name in stored text. Handles the escaped quotes
	 * and slashes in Elementor's JSON, and leaves everything else byte for byte.
	 *
	 * @param string $text Post content, Elementor JSON or widget text.
	 * @return string
	 */
	public static function rewrite_codes( $text ) {
		$rewritten = preg_replace( '#\[(\\\\?/)?easy_mega_menu(?=[\s\]/\\\\])#', '[$1ashbi_mega_menu', (string) $text );
		return null === $rewritten ? (string) $text : $rewritten;
	}

	/**
	 * Rewrite every string inside a stored widget setting.
	 *
	 * @param mixed $value Option value.
	 * @return mixed
	 */
	private static function rewrite_value( $value ) {
		if ( is_string( $value ) ) {
			return self::rewrite_codes( $value );
		}
		if ( is_array( $value ) ) {
			return array_map( array( __CLASS__, 'rewrite_value' ), $value );
		}
		return $value;
	}

	/**
	 * Every stored place that holds the old shortcode, with its rewritten text.
	 * Trashed posts stay as they are, and old revisions are history, so neither
	 * is rewritten. If one comes back, its code is still answered.
	 *
	 * @return array{posts:array<int,array>,meta:array<int,array>,widgets:array<string,array>}
	 */
	private function old_code_rows() {
		global $wpdb;
		$like = '%' . $wpdb->esc_like( 'easy_mega_menu' ) . '%';
		$rows = array( 'posts' => array(), 'meta' => array(), 'widgets' => array() );
		// Site content has no API that searches and writes it without changing dates or slashes.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$posts = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_content FROM {$wpdb->posts} WHERE post_status <> %s AND post_type <> %s AND post_content LIKE %s", 'trash', 'revision', $like ), ARRAY_A );
		$metas = $wpdb->get_results( $wpdb->prepare( "SELECT pm.meta_id, pm.post_id, pm.meta_value FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND p.post_status <> %s AND p.post_type <> %s AND pm.meta_value LIKE %s", '_elementor_data', 'trash', 'revision', $like ), ARRAY_A );
		$names = $wpdb->get_results( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value LIKE %s", $wpdb->esc_like( 'widget_' ) . '%', $like ), ARRAY_A );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		foreach ( (array) $posts as $row ) {
			$after = self::rewrite_codes( $row['post_content'] );
			if ( $after !== $row['post_content'] ) {
				$rows['posts'][ (int) $row['ID'] ] = array( 'before' => $row['post_content'], 'after' => $after );
			}
		}
		foreach ( (array) $metas as $row ) {
			$after = self::rewrite_codes( $row['meta_value'] );
			if ( $after !== $row['meta_value'] ) {
				$rows['meta'][ (int) $row['meta_id'] ] = array( 'post' => (int) $row['post_id'], 'before' => $row['meta_value'], 'after' => $after );
			}
		}
		foreach ( (array) $names as $row ) {
			$before = get_option( $row['option_name'] );
			$after  = self::rewrite_value( $before );
			if ( $after !== $before ) {
				$rows['widgets'][ $row['option_name'] ] = array( 'before' => $before, 'after' => $after );
			}
		}
		return $rows;
	}

	/**
	 * What would keep Finish switching from being safe right now.
	 *
	 * @return string Reason, or '' when it can run.
	 */
	public function finish_blocked_reason() {
		if ( self::source_active() ) {
			return __( 'Deactivate Easy Mega Menu first. While it is active it still answers its own codes.', 'ashbi-mega-menu' );
		}
		$missing = $this->placed_but_not_imported( true );
		if ( $missing ) {
			/* translators: %s: comma-separated menu IDs. */
			return sprintf( __( 'Your pages use these menus, but they are not copied yet: %s. Copy them first.', 'ashbi-mega-menu' ), implode( ', ', $missing ) );
		}
		return '';
	}

	/**
	 * Rewrite [easy_mega_menu] to [ashbi_mega_menu] across the site. The originals
	 * are saved first, and nothing is written unless that backup saved.
	 *
	 * @return array|WP_Error Counts.
	 */
	public function finish_switching() {
		$reason = $this->finish_blocked_reason();
		if ( '' !== $reason ) {
			return new WP_Error( 'abmm_emm_finish_blocked', $reason );
		}
		global $wpdb;
		$rows   = $this->old_code_rows();
		$counts = array( 'posts' => 0, 'elementor' => 0, 'widgets' => 0, 'failed' => 0 );
		if ( $rows['posts'] || $rows['meta'] || $rows['widgets'] ) {
			$backup = $this->read_backup();
			// A place rewritten by an earlier run keeps its entry unless this run changes it too.
			$backup['posts']   = array_replace( $backup['posts'], $rows['posts'] );
			$backup['meta']    = array_replace( $backup['meta'], $rows['meta'] );
			$backup['widgets'] = array_replace( $backup['widgets'], $rows['widgets'] );
			$backup['created'] = gmdate( 'c' );
			update_option( self::BACKUP_OPTION, $backup, false );
			if ( get_option( self::BACKUP_OPTION ) !== $backup ) {
				return new WP_Error( 'abmm_emm_backup_failed', __( 'The backup could not be saved, so nothing was changed.', 'ashbi-mega-menu' ) );
			}
			foreach ( $rows['posts'] as $id => $row ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keeps the post's dates and revisions as they are.
				if ( false === $wpdb->update( $wpdb->posts, array( 'post_content' => $row['after'] ), array( 'ID' => $id ) ) ) {
					++$counts['failed'];
					continue;
				}
				clean_post_cache( $id );
				++$counts['posts'];
			}
			foreach ( $rows['meta'] as $id => $row ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- the meta API would strip the JSON's backslashes.
				if ( false === $wpdb->update( $wpdb->postmeta, array( 'meta_value' => $row['after'] ), array( 'meta_id' => $id ) ) ) {
					++$counts['failed'];
					continue;
				}
				wp_cache_delete( $row['post'], 'post_meta' );
				++$counts['elementor'];
			}
			foreach ( $rows['widgets'] as $name => $row ) {
				update_option( $name, $row['after'] );
				++$counts['widgets'];
			}
		}
		// Older versions of this plugin read this if the site rolls back to one.
		update_option( self::TAKEOVER_OPTION, true );
		$counts['files'] = $this->clear_generated_files();
		$this->forget_found_codes();
		return $counts;
	}

	/**
	 * Put back what the last Finish switching changed. A place edited since then
	 * is left alone, so newer work is never lost.
	 *
	 * @return array|WP_Error Counts.
	 */
	public function undo_switching() {
		global $wpdb;
		$backup = $this->read_backup();
		if ( ! $backup['posts'] && ! $backup['meta'] && ! $backup['widgets'] ) {
			return new WP_Error( 'abmm_emm_no_backup', __( 'There is no switch to undo.', 'ashbi-mega-menu' ) );
		}
		$counts = array( 'restored' => 0, 'skipped' => 0 );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- the same direct writes as finish_switching().
		foreach ( $backup['posts'] as $id => $row ) {
			$current = $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $id ) );
			if ( $current !== $row['after'] || false === $wpdb->update( $wpdb->posts, array( 'post_content' => $row['before'] ), array( 'ID' => $id ) ) ) {
				++$counts['skipped'];
				continue;
			}
			clean_post_cache( $id );
			++$counts['restored'];
		}
		foreach ( $backup['meta'] as $id => $row ) {
			$current = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_id = %d", $id ) );
			if ( $current !== $row['after'] || false === $wpdb->update( $wpdb->postmeta, array( 'meta_value' => $row['before'] ), array( 'meta_id' => $id ) ) ) {
				++$counts['skipped'];
				continue;
			}
			wp_cache_delete( $row['post'], 'post_meta' );
			++$counts['restored'];
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		foreach ( $backup['widgets'] as $name => $row ) {
			if ( get_option( $name ) !== $row['after'] ) {
				++$counts['skipped'];
				continue;
			}
			update_option( $name, $row['before'] );
			++$counts['restored'];
		}
		delete_option( self::BACKUP_OPTION );
		$counts['files'] = $this->clear_generated_files();
		$this->forget_found_codes();
		return $counts;
	}

	/**
	 * @return array{posts:array,meta:array,widgets:array}
	 */
	private function read_backup() {
		$backup = get_option( self::BACKUP_OPTION, array() );
		$backup = is_array( $backup ) ? $backup : array();
		foreach ( array( 'posts', 'meta', 'widgets' ) as $key ) {
			$backup[ $key ] = is_array( $backup[ $key ] ?? null ) ? $backup[ $key ] : array();
		}
		return $backup;
	}

	/**
	 * Whether a Finish switching can be undone.
	 *
	 * @return bool
	 */
	public function has_backup() {
		$backup = $this->read_backup();
		return $backup['posts'] || $backup['meta'] || $backup['widgets'];
	}

	/**
	 * Clear the styles and cached widget output Elementor generated from the old content.
	 *
	 * @return bool Whether Elementor was there to clear.
	 */
	private function clear_generated_files() {
		if ( ! class_exists( '\Elementor\Plugin' ) || empty( \Elementor\Plugin::$instance->files_manager ) ) {
			return false;
		}
		\Elementor\Plugin::$instance->files_manager->clear_cache();
		return true;
	}

	private function forget_found_codes() {
		delete_transient( 'abmm_emm_shortcode_in_use' );
		delete_transient( 'abmm_emm_menus_in_use' );
		delete_transient( 'abmm_menu_reference_summaries' );
	}

	/* ------------------------------------------------------------------
	 * Old shortcode
	 * ---------------------------------------------------------------- */

	/**
	 * Whether any post, Elementor layout or widget still contains
	 * [easy_mega_menu]. Cached for an hour so admin screens stay fast.
	 *
	 * @return bool
	 */
	public function shortcode_in_content() {
		$cached = get_transient( 'abmm_emm_shortcode_in_use' );
		if ( 'yes' === $cached || 'no' === $cached ) {
			return 'yes' === $cached;
		}
		global $wpdb;
		$like   = '%' . $wpdb->esc_like( 'easy_mega_menu' ) . '%';
		$widget = $wpdb->esc_like( 'widget_' ) . '%';
		// One-off searches of site content (cached above); no API searches post content, Elementor data and widgets.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status NOT IN ( %s, %s, %s ) AND post_content LIKE %s LIMIT 1", 'trash', 'auto-draft', 'inherit', '%' . $wpdb->esc_like( '[easy_mega_menu' ) . '%' ) )
			|| $wpdb->get_var( $wpdb->prepare( "SELECT pm.meta_id FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND p.post_status NOT IN ( %s, %s, %s ) AND pm.meta_value LIKE %s LIMIT 1", '_elementor_data', 'trash', 'auto-draft', 'inherit', $like ) )
			|| $wpdb->get_var( $wpdb->prepare( "SELECT option_id FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value LIKE %s LIMIT 1", $widget, $like ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		/** Filters whether site content still uses [easy_mega_menu]. */
		$found = (bool) apply_filters( 'abmm_emm_shortcode_in_use', (bool) $found );
		set_transient( 'abmm_emm_shortcode_in_use', $found ? 'yes' : 'no', HOUR_IN_SECONDS );
		return $found;
	}

	/**
	 * Which of these menus site content places with [easy_mega_menu]. Cached for
	 * an hour, like shortcode_in_content().
	 *
	 * @param string[] $ids   Menu IDs.
	 * @param bool     $fresh Search again instead of using the cache.
	 * @return string[] The IDs in use, in the given order.
	 */
	public function menus_in_use( $ids, $fresh = false ) {
		$found = array();
		foreach ( array_map( 'strval', $ids ) as $id ) {
			if ( $this->content_uses_shortcode( 'easy_mega_menu', $id, $fresh ) ) {
				$found[] = $id;
			}
		}
		return $found;
	}

	/**
	 * Whether posts, page builder data or widgets place one menu with a shortcode.
	 * Answers are cached per menu for an hour, so callers can ask about any IDs.
	 *
	 * @param string $tag   Shortcode tag.
	 * @param string $id    Menu ID.
	 * @param bool   $fresh Search again instead of using the cache.
	 * @return bool
	 */
	public function content_uses_shortcode( $tag, $id, $fresh = false ) {
		global $wpdb;
		if ( ! isset( $wpdb ) ) {
			return false;
		}
		$cache = get_transient( 'abmm_emm_menus_in_use' );
		$cache = is_array( $cache ) ? $cache : array();
		$key   = $tag . ':' . $id;
		if ( ! $fresh && isset( $cache[ $key ] ) ) {
			return (bool) $cache[ $key ];
		}
		$shortcode = '%' . $wpdb->esc_like( $tag ) . '%';
		// Elementor stores the shortcode's quotes with backslashes. The closing quote keeps menu_demo from matching menu_demo-copy.
		$plain   = '%' . $wpdb->esc_like( 'id="' . $id . '"' ) . '%';
		$slashed = '%' . $wpdb->esc_like( 'id=\\"' . $id . '\\"' ) . '%';
		$single  = '%' . $wpdb->esc_like( "id='" . $id . "'" ) . '%';
		// One-off searches of site content, as in shortcode_in_content().
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status NOT IN ( %s, %s, %s ) AND post_content LIKE %s AND ( post_content LIKE %s OR post_content LIKE %s ) LIMIT 1", 'trash', 'auto-draft', 'inherit', $shortcode, $plain, $single ) )
			|| $wpdb->get_var( $wpdb->prepare( "SELECT pm.meta_id FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND p.post_status NOT IN ( %s, %s, %s ) AND pm.meta_value LIKE %s AND ( pm.meta_value LIKE %s OR pm.meta_value LIKE %s OR pm.meta_value LIKE %s ) LIMIT 1", '_elementor_data', 'trash', 'auto-draft', 'inherit', $shortcode, $plain, $slashed, $single ) )
			|| $wpdb->get_var( $wpdb->prepare( "SELECT option_id FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value LIKE %s AND ( option_value LIKE %s OR option_value LIKE %s OR option_value LIKE %s ) LIMIT 1", $wpdb->esc_like( 'widget_' ) . '%', $shortcode, $plain, $slashed, $single ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$cache[ $key ] = $found ? 1 : 0;
		set_transient( 'abmm_emm_menus_in_use', $cache, HOUR_IN_SECONDS );
		return (bool) $found;
	}

	/**
	 * Whether the plugin's Elementor widget places one menu. Cached for an hour
	 * with the other searches, so a page of cards runs the search once.
	 *
	 * @param string $id    Menu ID.
	 * @param bool   $fresh Search again instead of using the cache.
	 * @return bool
	 */
	public function elementor_widget_uses_menu( $id, $fresh = false ) {
		global $wpdb;
		if ( ! isset( $wpdb ) ) {
			return false;
		}
		$cache = get_transient( 'abmm_emm_menus_in_use' );
		$cache = is_array( $cache ) ? $cache : array();
		if ( $fresh || ! isset( $cache['elementor_widget_ids'] ) || ! is_array( $cache['elementor_widget_ids'] ) ) {
			$like = '%' . $wpdb->esc_like( '"widgetType":"ashbi-mega-menu"' ) . '%';
			// One-off search of page builder data, as in shortcode_in_content().
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_col( $wpdb->prepare( "SELECT pm.meta_value FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND p.post_status NOT IN ( %s, %s, %s ) AND pm.meta_value LIKE %s", '_elementor_data', 'trash', 'auto-draft', 'inherit', $like ) );
			$ids  = array();
			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				$elements = json_decode( (string) $row, true );
				if ( is_array( $elements ) ) {
					$this->collect_elementor_widget_menus( $elements, $ids );
				}
			}
			$cache['elementor_widget_ids'] = array_map( 'strval', array_keys( $ids ) );
			set_transient( 'abmm_emm_menus_in_use', $cache, HOUR_IN_SECONDS );
		}
		return in_array( (string) $id, $cache['elementor_widget_ids'], true );
	}

	/**
	 * Add the menus that Elementor widgets in this element tree show. Elementor
	 * leaves out a setting at its default, which for this widget is the first menu.
	 *
	 * @param array $elements Elementor elements.
	 * @param array $ids      Found menu IDs, as keys.
	 */
	private function collect_elementor_widget_menus( $elements, &$ids ) {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			if ( 'ashbi-mega-menu' === ( $element['widgetType'] ?? '' ) ) {
				$id = $element['settings']['menu_id'] ?? '';
				if ( ! is_scalar( $id ) || '' === (string) $id ) {
					$id = (string) array_key_first( ABMM_Data::instance()->get_all() );
				}
				if ( '' !== (string) $id ) {
					$ids[ (string) $id ] = true;
				}
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$this->collect_elementor_widget_menus( $element['elements'], $ids );
			}
		}
	}

	/**
	 * Easy Mega Menu menus that site content places but Ashbi has not imported.
	 * Those headers render empty until they are copied.
	 *
	 * @param bool $fresh Search again instead of using the cache.
	 * @return string[]
	 */
	public function placed_but_not_imported( $fresh = false ) {
		$ids = array();
		foreach ( array_keys( $this->read_source()['menus'] ) as $id ) {
			$ids[] = sanitize_key( (string) $id );
		}
		return array_values( array_diff( $this->menus_in_use( array_filter( $ids ), $fresh ), array_map( 'strval', array_keys( ABMM_Data::instance()->get_all() ) ) ) );
	}

	/**
	 * Whether a menu started in Easy Mega Menu. The IDs are remembered, because
	 * its own list may be gone once it is deleted.
	 *
	 * @param string $id Menu ID.
	 * @return bool
	 */
	public function is_source_menu( $id ) {
		$known = get_option( self::ORIGIN_OPTION, array() );
		if ( is_array( $known ) && in_array( (string) $id, $known, true ) ) {
			return true;
		}
		foreach ( array_keys( $this->read_source()['menus'] ) as $source_id ) {
			if ( sanitize_key( (string) $source_id ) === (string) $id ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Warn when pages use an Easy Mega Menu menu that was never copied, because
	 * that header is empty. Shown on the Dashboard, Plugins and this plugin's
	 * screens, to administrators only.
	 */
	public function missing_menus_notice() {
		if ( ! current_user_can( 'manage_options' ) || self::source_active() ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$id     = $screen ? (string) $screen->id : '';
		// The import screen explains this itself.
		if ( false !== strpos( $id, self::PAGE_SLUG ) || ( ! in_array( $id, array( 'dashboard', 'plugins' ), true ) && false === strpos( $id, 'ashbi-mega-menu' ) ) ) {
			return;
		}
		$missing = $this->placed_but_not_imported();
		if ( ! $missing ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
			/* translators: %s: comma-separated menu IDs. */
			esc_html( sprintf( __( 'Your pages use these menus, but they are not copied yet, so their headers are empty: %s.', 'ashbi-mega-menu' ), implode( ', ', $missing ) ) ),
			esc_url( $this->page_url() ),
			esc_html__( 'Copy them now', 'ashbi-mega-menu' )
		);
	}

	/**
	 * Answer [easy_mega_menu] (and the old theme helpers) whenever Easy Mega Menu
	 * is not running, so a missed code can never show as text. There is no switch
	 * for this. While Easy Mega Menu runs it keeps its own shortcode.
	 */
	public function answer_old_shortcodes() {
		if ( self::source_active() || shortcode_exists( 'easy_mega_menu' ) ) {
			return;
		}
		add_shortcode( 'easy_mega_menu', array( $this, 'shortcode' ) );
		require_once ABMM_PLUGIN_DIR . 'includes/emm-compat-functions.php';
	}

	/**
	 * [easy_mega_menu id="menu_demo" context="page" presentation="unified" review_post_id="123"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'             => '',
				'context'        => '',
				'presentation'   => '',
				'review_post_id' => '',
			),
			$atts,
			'easy_mega_menu'
		);
		if ( ! is_scalar( $atts['id'] ) ) {
			return '';
		}
		$id = (string) $atts['id'];
		if ( '' === $id ) {
			$menus = ABMM_Data::instance()->get_all();
			if ( empty( $menus ) ) {
				return '';
			}
			$id = (string) array_key_first( $menus );
		}
		return ABMM_Frontend::instance()->render(
			$id,
			array(
				'context'        => sanitize_key( (string) $atts['context'] ),
				'presentation'   => sanitize_key( (string) $atts['presentation'] ),
				'review_post_id' => absint( $atts['review_post_id'] ),
			)
		);
	}

	/* ------------------------------------------------------------------
	 * Admin screen
	 * ---------------------------------------------------------------- */

	/**
	 * Whether the page belongs in the plugin's own menu: only while there is
	 * still something to switch. Afterwards it moves under Tools.
	 *
	 * @return bool
	 */
	private function page_in_sidebar() {
		return $this->source_installed() || $this->shortcode_in_content();
	}

	/**
	 * @return string The page's address, wherever it is listed.
	 */
	public function page_url() {
		return admin_url( ( $this->page_in_sidebar() ? 'admin.php' : 'tools.php' ) . '?page=' . self::PAGE_SLUG );
	}

	public function register_page() {
		if ( $this->page_in_sidebar() ) {
			add_submenu_page(
				'ashbi-mega-menu',
				__( 'Switch from Easy Mega Menu', 'ashbi-mega-menu' ),
				__( 'Easy Mega Menu import', 'ashbi-mega-menu' ),
				'manage_options',
				self::PAGE_SLUG,
				array( $this, 'render_page' )
			);
			return;
		}
		// A site that never had Easy Mega Menu sees nothing about it.
		if ( $this->has_source() || $this->has_backup() || false !== get_option( self::ORIGIN_OPTION ) ) {
			add_management_page(
				__( 'Switch from Easy Mega Menu', 'ashbi-mega-menu' ),
				__( 'Easy Mega Menu switch', 'ashbi-mega-menu' ),
				'manage_options',
				self::PAGE_SLUG,
				array( $this, 'render_page' )
			);
		}
	}

	public function handle_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to import menus.', 'ashbi-mega-menu' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'abmm_emm_import' );
		// The form lists each menu. Ticking one that exists is the choice to replace it.
		$only = null;
		if ( ! empty( $_POST['abmm_select_menus'] ) ) {
			$only = isset( $_POST['menus'] ) && is_array( $_POST['menus'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['menus'] ) ) : array();
		}
		$result = $this->import( null !== $only || ! empty( $_POST['overwrite'] ), $only );
		$notice = is_wp_error( $result ) ? 'error' : 'imported';
		// The result or the real reason it failed, for this administrator only.
		set_transient( 'abmm_emm_import_result_' . get_current_user_id(), is_wp_error( $result ) ? array( 'error' => $result->get_error_message() ) : $result, HOUR_IN_SECONDS );
		$this->back_to_page( $notice );
	}

	/**
	 * Rewrite the old shortcodes. Needs an administrator and a valid nonce.
	 */
	public function handle_finish() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change site content.', 'ashbi-mega-menu' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'abmm_emm_finish' );
		$this->finish_result( $this->finish_switching(), 'finished' );
	}

	/**
	 * Undo the rewrite from its backup. Needs an administrator and a valid nonce.
	 */
	public function handle_undo() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change site content.', 'ashbi-mega-menu' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'abmm_emm_undo' );
		$this->finish_result( $this->undo_switching(), 'undone' );
	}

	/**
	 * Keep a result for this administrator and return to the page.
	 *
	 * @param array|WP_Error $result  Counts or failure.
	 * @param string         $success Notice for a success.
	 */
	private function finish_result( $result, $success ) {
		set_transient( 'abmm_emm_import_result_' . get_current_user_id(), is_wp_error( $result ) ? array( 'error' => $result->get_error_message() ) : $result, HOUR_IN_SECONDS );
		$this->back_to_page( is_wp_error( $result ) ? 'error' : $success );
	}

	/**
	 * @param string $notice Notice key.
	 */
	private function back_to_page( $notice ) {
		wp_safe_redirect( add_query_arg( array( 'abmm_notice' => $notice, '_abmm_nonce' => wp_create_nonce( 'abmm_emm_notice' ) ), $this->page_url() ) );
		exit;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$preview = $this->preview();
		$notice  = '';
		if ( isset( $_GET['abmm_notice'], $_GET['_abmm_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_abmm_nonce'] ) ), 'abmm_emm_notice' ) ) {
			$notice = sanitize_key( wp_unslash( $_GET['abmm_notice'] ) );
		}
		$stored = in_array( $notice, array( 'imported', 'error', 'finished', 'undone' ), true ) ? get_transient( 'abmm_emm_import_result_' . get_current_user_id() ) : null;
		$result = in_array( $notice, array( 'imported', 'finished', 'undone' ), true ) && is_array( $stored ) && ! isset( $stored['error'] ) ? $stored : null;
		$error  = 'error' === $notice && is_array( $stored ) && is_string( $stored['error'] ?? null ) ? $stored['error'] : '';
		include ABMM_PLUGIN_DIR . 'admin/views/emm-import.php';
	}
}
