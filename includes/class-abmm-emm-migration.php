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
	 * @param bool $overwrite Replace Ashbi menus that share an ID.
	 * @return array|WP_Error Summary.
	 */
	public function import( $overwrite = false ) {
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
			if ( isset( $existing[ $id ] ) && ! $overwrite && ! ABMM_Classic::is_classic( $existing[ $id ] ) ) {
				$skipped[] = $id;
				continue;
			}
			$menus[ $id ] = $this->map_menu( $id, $menu )['menu'];
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

		$contextual = $this->import_context( $source );
		return array(
			'menus'    => $result,
			'skipped'  => $skipped,
			'assets'   => $assets,
			'profiles' => $contextual['profiles'],
			'branding' => $contextual['branding'],
			'pages'    => $contextual['pages'],
		);
	}

	/**
	 * Copy product profiles, branding and page assignments into Ashbi's storage.
	 *
	 * @param array $source Source data.
	 * @return array{profiles:int,branding:int,pages:int}
	 */
	private function import_context( $source ) {
		$counts      = array( 'profiles' => 0, 'branding' => 0, 'pages' => 0 );
		$assignments = $this->page_assignments();
		if ( empty( $source['profiles'] ) && empty( $source['branding'] ) && empty( $assignments ) ) {
			return $counts;
		}
		update_option( ABMM_CONTEXTUAL_NAV_OPTION, true );
		$profiles_store = ABMM_Product_Profiles::instance();
		$profiles       = $profiles_store->get_all();
		foreach ( $source['profiles'] as $id => $profile ) {
			$id = sanitize_key( (string) $id );
			if ( '' !== $id && is_array( $profile ) && ! isset( $profiles[ $id ] ) ) {
				$profiles[ $id ] = $profiles_store->sanitize_profile( $profile );
				++$counts['profiles'];
			}
		}
		update_option( ABMM_Product_Profiles::OPTION_KEY, $profiles );

		$branding_store = ABMM_Product_Branding::instance();
		$branding       = $branding_store->get_all();
		foreach ( $source['branding'] as $id => $config ) {
			$id = sanitize_key( (string) $id );
			if ( '' !== $id && is_array( $config ) && ! isset( $branding[ $id ] ) ) {
				$branding[ $id ] = $branding_store->sanitize_config( $config );
				++$counts['branding'];
			}
		}
		update_option( ABMM_Product_Branding::OPTION_KEY, $branding, false );

		foreach ( $assignments as $post_id => $profile ) {
			if ( '' === (string) get_post_meta( $post_id, ABMM_Page_Context::META_KEY, true ) ) {
				update_post_meta( $post_id, ABMM_Page_Context::META_KEY, $profile );
				++$counts['pages'];
			}
		}
		return $counts;
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
		$hash    = substr( md5( $css_text . $sprite_text ), 0, 10 );
		$files   = array(
			'css'    => 'emm-frontend-' . $hash . '.css',
			'sprite' => 'emm-sprite.svg',
		);
		if ( ! $this->write_file( $dir . '/' . $files['css'], $css_text ) || ! $this->write_file( $dir . '/' . $files['sprite'], $sprite_text ) ) {
			return array( 'copied' => false, 'message' => __( 'The copied styles could not be saved.', 'ashbi-mega-menu' ) );
		}
		update_option(
			ABMM_Classic::ASSETS_OPTION,
			array(
				'css'     => $files['css'],
				'sprite'  => $files['sprite'],
				'version' => '' !== $version ? $version : $hash,
				'source'  => $plugin['version'],
			)
		);
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
		$result = $this->import( ! empty( $_POST['overwrite'] ) );
		$notice = is_wp_error( $result ) ? 'error' : 'imported';
		if ( ! is_wp_error( $result ) ) {
			set_transient( 'abmm_emm_import_result_' . get_current_user_id(), $result, HOUR_IN_SECONDS );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'abmm_notice' => $notice, '_abmm_nonce' => wp_create_nonce( 'abmm_emm_notice' ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_takeover() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change this setting.', 'ashbi-mega-menu' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'abmm_emm_takeover' );
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
		$result = 'imported' === $notice ? get_transient( 'abmm_emm_import_result_' . get_current_user_id() ) : null;
		include ABMM_PLUGIN_DIR . 'admin/views/emm-import.php';
	}
}
