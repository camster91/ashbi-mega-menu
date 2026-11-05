<?php
/**
 * Switching from Easy Mega Menu.
 *
 * Reads Easy Mega Menu's stored data (menus, product profiles, product
 * branding and page assignments) without ever writing to it, copies it into
 * Ashbi's own storage with the same menu IDs, and can take over the
 * [easy_mega_menu] shortcode once Easy Mega Menu is deactivated so header
 * templates need no edits. Imported menus use classic mode (ABMM_Classic), so
 * visitors see the same header.
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
		add_action( 'init', array( $this, 'maybe_take_over' ), 20 );
		add_action( 'admin_menu', array( $this, 'register_page' ), 30 );
		add_action( 'admin_post_abmm_emm_import', array( $this, 'handle_import' ) );
		add_action( 'admin_post_abmm_emm_takeover', array( $this, 'handle_takeover' ) );
		add_action( 'admin_notices', array( $this, 'unanswered_shortcode_notice' ) );
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
				'edited'   => isset( $existing[ $id ] ) && ABMM_Classic::is_classic( $existing[ $id ] ) && ABMM_Data::instance()->revision( ABMM_Data::instance()->sanitize_menu( $mapped['menu'] ) ) !== ABMM_Data::instance()->revision( $existing[ $id ] ),
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
		);
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
			return array( 'copied' => false, 'message' => __( 'Easy Mega Menu files were not found, so the bundled classic styles and generic icons are used.', 'ashbi-mega-menu' ) );
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
	 * Shortcode takeover
	 * ---------------------------------------------------------------- */

	/**
	 * Whether the takeover setting is on.
	 *
	 * @return bool
	 */
	public static function takeover_enabled() {
		return (bool) get_option( self::TAKEOVER_OPTION, false );
	}

	/**
	 * Whether any post, Elementor layout or widget still contains
	 * [easy_mega_menu]. Cached for an hour so admin screens stay fast.
	 *
	 * @return bool
	 */
	private function shortcode_in_content() {
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
	 * With the takeover on, those headers would render empty.
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
	 * Whether Easy Mega Menu has a menu with this ID.
	 *
	 * @param string $id Menu ID.
	 * @return bool
	 */
	public function is_source_menu( $id ) {
		foreach ( array_keys( $this->read_source()['menus'] ) as $source_id ) {
			if ( sanitize_key( (string) $source_id ) === (string) $id ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Warn when Easy Mega Menu is inactive but its menus are still stored and
	 * nothing answers [easy_mega_menu]: WordPress then prints the shortcode as
	 * plain text on the site. Shown on the Dashboard, Plugins and this plugin's
	 * screens, to administrators only.
	 */
	public function unanswered_shortcode_notice() {
		if ( ! current_user_can( 'manage_options' ) || self::source_active() ) {
			return;
		}
		$takeover = self::takeover_enabled();
		if ( ! $takeover && shortcode_exists( 'easy_mega_menu' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$id     = $screen ? (string) $screen->id : '';
		// The import screen explains this itself.
		if ( false !== strpos( $id, self::PAGE_SLUG ) || ( ! in_array( $id, array( 'dashboard', 'plugins' ), true ) && false === strpos( $id, 'ashbi-mega-menu' ) ) ) {
			return;
		}
		if ( $takeover ) {
			$missing = $this->placed_but_not_imported();
			if ( $missing ) {
				printf(
					'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
					/* translators: %s: comma-separated menu IDs. */
					esc_html( sprintf( __( 'Ashbi is answering [easy_mega_menu] shortcodes, but these placed menus were not imported, so their headers are empty: %s.', 'ashbi-mega-menu' ), implode( ', ', $missing ) ) ),
					esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ),
					esc_html__( 'Open Easy Mega Menu import', 'ashbi-mega-menu' )
				);
			}
			return;
		}
		if ( empty( $this->read_source()['menus'] ) || ! $this->shortcode_in_content() ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'Easy Mega Menu is inactive, so pages that use its [easy_mega_menu] shortcode show the shortcode as text. Import its menus and turn on "Use Ashbi Mega Menu in place of Easy Mega Menu" to show them again, or reactivate Easy Mega Menu.', 'ashbi-mega-menu' ),
			esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ),
			esc_html__( 'Open Easy Mega Menu import', 'ashbi-mega-menu' )
		);
	}

	/**
	 * Answer [easy_mega_menu] (and the old theme helpers) once Easy Mega Menu
	 * is inactive. While it is active it keeps its own shortcode.
	 */
	public function maybe_take_over() {
		if ( ! self::takeover_enabled() || self::source_active() || shortcode_exists( 'easy_mega_menu' ) ) {
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

	public function register_page() {
		if ( ! $this->has_source() && ! self::takeover_enabled() ) {
			return;
		}
		add_submenu_page(
			'ashbi-mega-menu',
			__( 'Import from Easy Mega Menu', 'ashbi-mega-menu' ),
			__( 'Easy Mega Menu import', 'ashbi-mega-menu' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
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
		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'abmm_notice' => $notice, '_abmm_nonce' => wp_create_nonce( 'abmm_emm_notice' ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_takeover() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change this setting.', 'ashbi-mega-menu' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'abmm_emm_takeover' );
		// Switching on with placed menus missing would blank those headers: ask first.
		if ( ! empty( $_POST['takeover'] ) && empty( $_POST['confirm_missing'] ) && $this->placed_but_not_imported( true ) ) {
			wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'abmm_notice' => 'takeover_blocked', '_abmm_nonce' => wp_create_nonce( 'abmm_emm_notice' ) ), admin_url( 'admin.php' ) ) );
			exit;
		}
		update_option( self::TAKEOVER_OPTION, ! empty( $_POST['takeover'] ) );
		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'abmm_notice' => 'saved', '_abmm_nonce' => wp_create_nonce( 'abmm_emm_notice' ) ), admin_url( 'admin.php' ) ) );
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
		$stored = in_array( $notice, array( 'imported', 'error' ), true ) ? get_transient( 'abmm_emm_import_result_' . get_current_user_id() ) : null;
		$result = 'imported' === $notice && is_array( $stored ) && ! isset( $stored['error'] ) ? $stored : null;
		$error  = 'error' === $notice && is_array( $stored ) && is_string( $stored['error'] ?? null ) ? $stored['error'] : '';
		include ABMM_PLUGIN_DIR . 'admin/views/emm-import.php';
	}
}
