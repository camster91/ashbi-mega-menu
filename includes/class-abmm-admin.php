<?php
/**
 * Admin panel: list + visual mega menu builder.
 *
 * @package Ashbi_Mega_Menu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABMM_Admin {

	/**
	 * @var ABMM_Admin|null
	 */
	private static $instance = null;

	/**
	 * @return ABMM_Admin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_abmm_save_menu', array( $this, 'ajax_save_menu' ) );
		add_action( 'wp_ajax_abmm_delete_menu', array( $this, 'ajax_delete_menu' ) );
		add_action( 'wp_ajax_abmm_create_menu', array( $this, 'ajax_create_menu' ) );
		add_action( 'wp_ajax_abmm_duplicate_menu', array( $this, 'ajax_duplicate_menu' ) );
		add_action( 'wp_ajax_abmm_export_menu', array( $this, 'ajax_export_menu' ) );
		add_action( 'wp_ajax_abmm_import_menu', array( $this, 'ajax_import_menu' ) );
		add_action( 'wp_ajax_abmm_get_menu', array( $this, 'ajax_get_menu' ) );
		add_action( 'wp_ajax_abmm_onboarding', array( $this, 'ajax_onboarding' ) );
		add_action( 'wp_ajax_abmm_menu_references', array( $this, 'ajax_menu_references' ) );
		add_action( 'wp_ajax_abmm_restore_menu', array( $this, 'ajax_restore_menu' ) );
		add_action( 'wp_ajax_abmm_permanently_delete_menu', array( $this, 'ajax_permanently_delete_menu' ) );
	}

	/**
	 * Register admin pages.
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Ashbi Mega Menu', 'ashbi-mega-menu' ),
			__( 'Mega Menu', 'ashbi-mega-menu' ),
			'manage_options',
			'ashbi-mega-menu',
			array( $this, 'render_page' ),
			'dashicons-menu-alt3',
			58
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue( $hook ) {
		$is_builder_page = 'toplevel_page_ashbi-mega-menu' === $hook;
		$is_product_page = 0 === strpos( $hook, 'mega-menu_page_ashbi-mega-menu-' );
		if ( ! $is_builder_page && ! $is_product_page ) {
			return;
		}

		wp_enqueue_style(
			'abmm-admin',
			ABMM_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			ABMM_VERSION
		);
		if ( ! $is_builder_page ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wplink' );
		wp_enqueue_style( 'editor-buttons' );
		wp_enqueue_script( 'abmm-menu-search-preview', ABMM_PLUGIN_URL . 'assets/js/frontend.js', array(), ABMM_VERSION, true );
		wp_localize_script( 'abmm-menu-search-preview', 'abmmRuntime', array( 'adminPreview' => true, 'searchStrings' => abmm_menu_search_strings() ) );

		wp_enqueue_script(
			'abmm-admin',
			ABMM_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'jquery-ui-sortable', 'wp-color-picker', 'wplink', 'abmm-menu-search-preview' ),
			ABMM_VERSION,
			true
		);

		$icons = ABMM_Icons::instance();

		wp_localize_script(
			'abmm-admin',
			'abmmAdmin',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'abmm_admin' ),
				'icons'     => $icons->all(),
				'iconLabels'=> $icons->labels(),
				'restUrl'   => rest_url( ABMM_Import_Export::REST_NAMESPACE ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
				'strings'   => array(
					'saved'           => __( 'Menu saved successfully!', 'ashbi-mega-menu' ),
					'savedState'      => __( 'All changes saved.', 'ashbi-mega-menu' ),
					'unsavedState'    => __( 'Unsaved changes.', 'ashbi-mega-menu' ),
					'savingState'     => __( 'Saving…', 'ashbi-mega-menu' ),
					'retrySave'       => __( 'Retry save', 'ashbi-mega-menu' ),
					'saveError'       => __( 'Could not save. Your changes are still unsaved.', 'ashbi-mega-menu' ),
					'loadMalformed'   => __( 'The menu data was incomplete or malformed. Reload to try again. Your stored content was not changed.', 'ashbi-mega-menu' ),
					'loadIncompatible'=> __( 'This menu uses an incompatible data format. Update the plugin or restore a compatible export before editing. Your stored content was not changed.', 'ashbi-mega-menu' ),
					'confirmDelete'   => __( 'Delete this menu? This cannot be undone.', 'ashbi-mega-menu' ),
					'confirmRemove'   => __( 'Remove this item?', 'ashbi-mega-menu' ),
					'confirmImport'   => __( 'This will overwrite the current menu. Continue?', 'ashbi-mega-menu' ),
					'newMenu'         => __( 'New Mega Menu', 'ashbi-mega-menu' ),
					'menuName'        => __( 'Menu name', 'ashbi-mega-menu' ),
					'createMenu'      => __( 'Create Menu', 'ashbi-mega-menu' ),
					'duplicateError'  => __( 'The menu could not be duplicated.', 'ashbi-mega-menu' ),
					'cancel'          => __( 'Cancel', 'ashbi-mega-menu' ),
					'deleteAgain'     => __( 'Click again to delete', 'ashbi-mega-menu' ),
					'untitled'        => __( 'Untitled', 'ashbi-mega-menu' ),
					'addCategory'     => __( 'Add Category', 'ashbi-mega-menu' ),
					'addLink'         => __( 'Add Link', 'ashbi-mega-menu' ),
					'addNavItem'      => __( 'Add Menu Item', 'ashbi-mega-menu' ),
					'copyShortcode'   => __( 'Shortcode copied!', 'ashbi-mega-menu' ),
					'copyError'       => __( 'Automatic copy failed. The shortcode is selected below; press Ctrl+C or Command+C.', 'ashbi-mega-menu' ),
					'creatingMenu'    => __( 'Creating menu…', 'ashbi-mega-menu' ),
					'createError'     => __( 'The menu could not be created. Check your connection and permissions, then try again.', 'ashbi-mega-menu' ),
					'deletingMenu'    => __( 'Deleting menu…', 'ashbi-mega-menu' ),
					'deleteSuccess'   => __( 'Menu deleted.', 'ashbi-mega-menu' ),
					'deleteError'     => __( 'The menu could not be deleted. Nothing was removed. Try again.', 'ashbi-mega-menu' ),
					'archiveMenu'     => __( 'Archive menu', 'ashbi-mega-menu' ),
					'archiveSuccess'  => __( 'Menu archived. It can be restored below.', 'ashbi-mega-menu' ),
					'restoreSuccess'  => __( 'Menu restored with its original ID.', 'ashbi-mega-menu' ),
					'conflictError'   => __( 'This menu changed on the server. Your local draft was preserved; reload or export it before continuing.', 'ashbi-mega-menu' ),
					'selectIcon'      => __( 'Choose an icon', 'ashbi-mega-menu' ),
					'uploadIcon'      => __( 'Upload custom icon', 'ashbi-mega-menu' ),
					'categoryTitle'   => __( 'Category title', 'ashbi-mega-menu' ),
					'categoryDesc'    => __( 'Short description', 'ashbi-mega-menu' ),
					'panelTitle'      => __( 'Panel heading (right side)', 'ashbi-mega-menu' ),
					'panelUrl'        => __( 'Panel heading link URL', 'ashbi-mega-menu' ),
					'groupUrl'        => __( 'Column heading link URL', 'ashbi-mega-menu' ),
					'linkLabel'       => __( 'Link label', 'ashbi-mega-menu' ),
					'linkUrl'         => __( 'Search or type URL', 'ashbi-mega-menu' ),
					'selectLink'      => __( 'Select / edit link', 'ashbi-mega-menu' ),
					'megaMenu'        => __( 'Mega Menu', 'ashbi-mega-menu' ),
					'simpleLink'      => __( 'Simple Link', 'ashbi-mega-menu' ),
					'dragHint'        => __( 'Drag to reorder', 'ashbi-mega-menu' ),
					'exportSuccess'   => __( 'Menu exported successfully!', 'ashbi-mega-menu' ),
					'exportError'     => __( 'Export failed.', 'ashbi-mega-menu' ),
					'importSuccess'   => __( 'Menu imported successfully!', 'ashbi-mega-menu' ),
					'importError'     => __( 'Import failed. Check the file format.', 'ashbi-mega-menu' ),
					'importInvalid'   => __( 'Invalid JSON file.', 'ashbi-mega-menu' ),
					'importNoMenus'   => __( 'No valid menus found in file.', 'ashbi-mega-menu' ),
					'importPreview'   => __( 'Review these changes before importing:', 'ashbi-mega-menu' ),
					'importConfirm'   => __( 'Confirm Import', 'ashbi-mega-menu' ),
					'importCancel'    => __( 'Cancel', 'ashbi-mega-menu' ),
				),
			)
		);

		add_action( 'admin_footer', array( $this, 'print_link_dialog' ) );
	}

	/**
	 * Output the core Insert/Edit Link dialog markup.
	 */
	public function print_link_dialog() {
		if ( ! class_exists( '_WP_Editors', false ) ) {
			require_once ABSPATH . WPINC . '/class-wp-editor.php';
		}
		\_WP_Editors::wp_link_dialog();
	}

	/**
	 * Render the admin app shell.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$data    = ABMM_Data::instance();
		$menus   = $data->get_all();
		$archives = $data->get_archived();
		$readiness = array();
		foreach ( $menus as $menu_id => $menu_data ) {
			$readiness[ $menu_id ] = $data->readiness( $menu_data );
		}
		$user_id = get_current_user_id();
		if ( ! empty( $menus ) && $user_id && ! get_user_meta( $user_id, ABMM_ONBOARDING_META, true ) ) {
			update_user_meta( $user_id, ABMM_ONBOARDING_META, 'returning-user' );
		}
		$show_onboarding = empty( $menus ) && ( ! $user_id || ! get_user_meta( $user_id, ABMM_ONBOARDING_META, true ) );
		$edit_id = isset( $_GET['edit'] ) ? sanitize_text_field( wp_unslash( $_GET['edit'] ) ) : '';

		if ( $edit_id && isset( $menus[ $edit_id ] ) ) {
			include ABMM_PLUGIN_DIR . 'admin/views/builder.php';
		} else {
			include ABMM_PLUGIN_DIR . 'admin/views/list.php';
		}
	}

	/**
	 * AJAX: save menu.
	 */
	public function ajax_save_menu() {
		check_ajax_referer( 'abmm_admin', 'nonce' );
		$this->verify_ajax();

		$id  = isset( $_POST['menu_id'] ) ? sanitize_text_field( wp_unslash( $_POST['menu_id'] ) ) : '';
		$raw = isset( $_POST['menu_data'] ) ? wp_unslash( $_POST['menu_data'] ) : '';
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid menu data.', 'ashbi-mega-menu' ) ), 400 );
		}
		if ( strlen( $raw ) > ABMM_Import_Export::MAX_IMPORT_BYTES ) {
			wp_send_json_error( array( 'message' => __( 'Menu data exceeds the 1 MB limit.', 'ashbi-mega-menu' ) ), 413 );
		}
		$data = json_decode( $raw, true, 32 );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid menu data.', 'ashbi-mega-menu' ) ), 400 );
		}

		$store    = ABMM_Data::instance();
		$menus    = $store->get_all();
		$current  = $menus[ $id ] ?? null;
		$revision = isset( $_POST['base_revision'] ) ? sanitize_text_field( wp_unslash( $_POST['base_revision'] ) ) : '';
		if ( '' === $revision ) {
			wp_send_json_error( array( 'message' => __( 'Reload this menu before saving so changes are not overwritten.', 'ashbi-mega-menu' ) ), 400 );
		}
		if ( ! $current ) {
			wp_send_json_error( array( 'message' => __( 'This menu no longer exists.', 'ashbi-mega-menu' ) ), 404 );
		}
		if ( $revision && ! hash_equals( $store->revision( $current ), $revision ) ) {
			wp_send_json_error(
				array(
					'message'  => __( 'This menu changed on the server. Your local draft was not overwritten.', 'ashbi-mega-menu' ),
					'conflict' => true,
					'revision' => $store->revision( $current ),
				),
				409
			);
		}

		$saved = $store->save_if_revision( $id, $data, $revision );
		if ( is_wp_error( $saved ) ) {
			$error_data = $saved->get_error_data();
			if ( 'abmm_menu_conflict' === $saved->get_error_code() ) {
				wp_send_json_error(
					array(
						'message'  => $saved->get_error_message(),
						'conflict' => true,
						'revision' => is_array( $error_data ) ? ( $error_data['revision'] ?? '' ) : '',
					),
					409
				);
			}
			if ( 'abmm_menu_not_found' === $saved->get_error_code() ) {
				wp_send_json_error( array( 'message' => $saved->get_error_message() ), 404 );
			}
			wp_send_json_error( array( 'message' => $saved->get_error_message() ), 503 );
		}
		if ( ! $saved ) {
			wp_send_json_error( array( 'message' => __( 'Database save failed.', 'ashbi-mega-menu' ) ), 500 );
		}
		$saved_menus = $store->get_all();
		$saved_menu  = $saved_menus[ $id ] ?? $data;
		wp_send_json_success(
			array(
				'message'   => 'saved',
				'revision'  => $store->revision( $saved_menu ),
				'readiness' => $store->readiness( $saved_menu ),
			)
		);
	}

	/**
	 * AJAX: delete menu.
	 */
	public function ajax_delete_menu() {
		check_ajax_referer( 'abmm_admin', 'nonce' );
		$this->verify_ajax();

		$id = isset( $_POST['menu_id'] ) ? sanitize_text_field( wp_unslash( $_POST['menu_id'] ) ) : '';
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => 'Missing ID' ), 400 );
		}

		if ( ! ABMM_Data::instance()->archive( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'The menu was not archived. Nothing was removed.', 'ashbi-mega-menu' ) ), 500 );
		}
		wp_send_json_success( array( 'message' => __( 'Menu archived. It can be restored below.', 'ashbi-mega-menu' ) ) );
	}

	/**
	 * AJAX: fetch a verified menu for load recovery.
	 */
	public function ajax_get_menu() {
		check_ajax_referer( 'abmm_admin', 'nonce' );
		$this->verify_ajax();
		$id   = isset( $_POST['menu_id'] ) ? sanitize_text_field( wp_unslash( $_POST['menu_id'] ) ) : '';
		$data        = ABMM_Data::instance();
		$stored_menu = $data->get_all()[ $id ] ?? null;
		$menu        = $data->get( $id );
		if ( ! $stored_menu || ! $menu ) {
			wp_send_json_error( array( 'message' => __( 'The stored menu could not be found.', 'ashbi-mega-menu' ) ), 404 );
		}
		wp_send_json_success(
			array(
				'menu'      => $menu,
				'revision'  => $data->revision( $stored_menu ),
				'readiness' => $data->readiness( $menu ),
			)
		);
	}

	/**
	 * AJAX: complete or dismiss first-run onboarding.
	 */
	public function ajax_onboarding() {
		check_ajax_referer( 'abmm_admin', 'nonce' );
		$this->verify_ajax();
		$choice = isset( $_POST['choice'] ) ? sanitize_key( wp_unslash( $_POST['choice'] ) ) : '';
		$data   = ABMM_Data::instance();
		$id     = '';
		if ( 'blank' === $choice ) {
			$id = $data->create( __( 'New Mega Menu', 'ashbi-mega-menu' ) );
		} elseif ( 'starter' === $choice ) {
			$id = $data->create_from_starter();
		} elseif ( 'dismiss' !== $choice ) {
			wp_send_json_error( array( 'message' => __( 'Choose a valid first-run option.', 'ashbi-mega-menu' ) ), 400 );
		}
		if ( in_array( $choice, array( 'blank', 'starter' ), true ) && ! $id ) {
			wp_send_json_error( array( 'message' => __( 'The first menu could not be created.', 'ashbi-mega-menu' ) ), 500 );
		}
		update_user_meta( get_current_user_id(), ABMM_ONBOARDING_META, $choice );
		wp_send_json_success(
			array(
				'id'  => $id,
				'url' => $id ? admin_url( 'admin.php?page=ashbi-mega-menu&edit=' . rawurlencode( $id ) ) : admin_url( 'admin.php?page=ashbi-mega-menu' ),
			)
		);
	}

	/**
	 * AJAX: return known placement references before archive.
	 */
	public function ajax_menu_references() {
		check_ajax_referer( 'abmm_admin', 'nonce' );
		$this->verify_ajax();
		$id = isset( $_POST['menu_id'] ) ? sanitize_text_field( wp_unslash( $_POST['menu_id'] ) ) : '';
		wp_send_json_success( $this->reference_summary( $id ) );
	}

	/**
	 * AJAX: restore an archived menu.
	 */
	public function ajax_restore_menu() {
		check_ajax_referer( 'abmm_admin', 'nonce' );
		$this->verify_ajax();
		$id = isset( $_POST['menu_id'] ) ? sanitize_text_field( wp_unslash( $_POST['menu_id'] ) ) : '';
		if ( ! ABMM_Data::instance()->restore( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'The menu could not be restored. Its ID may already be in use.', 'ashbi-mega-menu' ) ), 409 );
		}
		wp_send_json_success( array( 'message' => __( 'Menu restored with its original ID.', 'ashbi-mega-menu' ) ) );
	}

	/**
	 * AJAX: permanently remove an archived menu.
	 */
	public function ajax_permanently_delete_menu() {
		check_ajax_referer( 'abmm_admin', 'nonce' );
		$this->verify_ajax();
		$id = isset( $_POST['menu_id'] ) ? sanitize_text_field( wp_unslash( $_POST['menu_id'] ) ) : '';
		if ( ! ABMM_Data::instance()->permanently_delete( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permanent deletion failed.', 'ashbi-mega-menu' ) ), 500 );
		}
		wp_send_json_success( array( 'message' => __( 'Archived menu permanently deleted.', 'ashbi-mega-menu' ) ) );
	}

	/**
	 * Count known block and widget references. Shortcodes and PHP calls cannot be discovered reliably.
	 *
	 * @param string $menu_id Menu ID.
	 * @return array
	 */
	private function reference_summary( $menu_id ) {
		$blocks  = 0;
		$widgets = 0;
		$widget_options = get_option( 'widget_abmm_widget', array() );
		foreach ( is_array( $widget_options ) ? $widget_options : array() as $instance ) {
			if ( is_array( $instance ) && $menu_id === ( $instance['menu_id'] ?? '' ) ) {
				++$widgets;
			}
		}

		if ( function_exists( 'get_posts' ) && function_exists( 'parse_blocks' ) ) {
			$block_widgets = get_option( 'widget_block', array() );
			foreach ( is_array( $block_widgets ) ? $block_widgets : array() as $instance ) {
				if ( is_array( $instance ) && ! empty( $instance['content'] ) ) {
					$blocks += $this->count_menu_blocks( parse_blocks( $instance['content'] ), $menu_id );
				}
			}

			$posts = get_posts(
				array(
					'post_type'      => 'any',
					'post_status'    => 'any',
					'posts_per_page' => -1,
					's'              => $menu_id,
				)
			);
			foreach ( $posts as $post ) {
				$blocks += $this->count_menu_blocks( parse_blocks( $post->post_content ?? '' ), $menu_id );
			}
		}

		return array(
			'blocks'  => $blocks,
			'widgets' => $widgets,
			'known'   => $blocks + $widgets,
			'warning' => __( 'Shortcodes in text, templates, and PHP calls cannot be detected reliably. Check those placements before archiving.', 'ashbi-mega-menu' ),
		);
	}

	/**
	 * Recursively count menu blocks.
	 *
	 * @param array  $blocks  Parsed blocks.
	 * @param string $menu_id Menu ID.
	 * @return int
	 */
	private function count_menu_blocks( $blocks, $menu_id ) {
		$count = 0;
		foreach ( $blocks as $block ) {
			if ( 'ashbi-mega-menu/mega-menu' === ( $block['blockName'] ?? '' ) && $menu_id === ( $block['attrs']['menuId'] ?? '' ) ) {
				++$count;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$count += $this->count_menu_blocks( $block['innerBlocks'], $menu_id );
			}
		}
		return $count;
	}

	/**
	 * AJAX: create menu.
	 */
	public function ajax_create_menu() {
		check_ajax_referer( 'abmm_admin', 'nonce' );
		$this->verify_ajax();

		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$id    = ABMM_Data::instance()->create( $title );
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Database create failed.', 'ashbi-mega-menu' ) ), 500 );
		}

		wp_send_json_success(
			array(
				'id'  => $id,
				'url' => admin_url( 'admin.php?page=ashbi-mega-menu&edit=' . rawurlencode( $id ) ),
			)
		);
	}

	/**
	 * AJAX: duplicate a menu and open its independent copy.
	 */
	public function ajax_duplicate_menu() {
		check_ajax_referer( 'abmm_admin', 'nonce' );
		$this->verify_ajax();
		$id     = isset( $_POST['menu_id'] ) ? sanitize_key( wp_unslash( $_POST['menu_id'] ) ) : '';
		$new_id = ABMM_Data::instance()->duplicate( $id );
		if ( is_wp_error( $new_id ) ) {
			wp_send_json_error( array( 'message' => $new_id->get_error_message() ), 400 );
		}
		wp_send_json_success( array( 'id' => $new_id, 'url' => admin_url( 'admin.php?page=ashbi-mega-menu&edit=' . rawurlencode( $new_id ) ) ) );
	}

	/**
	 * AJAX: export a single menu as JSON.
	 */
	public function ajax_export_menu() {
		check_ajax_referer( 'abmm_admin', 'nonce' );
		$this->verify_ajax();

		$id = isset( $_POST['menu_id'] ) ? sanitize_text_field( wp_unslash( $_POST['menu_id'] ) ) : '';
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => 'Missing ID' ), 400 );
		}

		$data = ABMM_Import_Export::instance()->export_single_menu( $id );
		if ( is_wp_error( $data ) ) {
			wp_send_json_error( array( 'message' => $data->get_error_message() ), 404 );
		}

		wp_send_json_success( array( 'json' => wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ) );
	}

	/**
	 * AJAX: import a single menu from JSON file upload.
	 */
	public function ajax_import_menu() {
		check_ajax_referer( 'abmm_admin', 'nonce' );
		$this->verify_ajax();

		if ( empty( $_FILES['import_file'] ) || ! empty( $_FILES['import_file']['error'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded or upload error.', 'ashbi-mega-menu' ) ), 400 );
		}

		$file = $_FILES['import_file'];
		$mode = isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : 'merge';

		if ( ! in_array( $mode, array( 'merge', 'replace', 'copy' ), true ) ) {
			$mode = 'merge';
		}

		$filename = sanitize_file_name( $file['name'] ?? '' );
		$size     = isset( $file['size'] ) ? absint( $file['size'] ) : 0;
		if ( 'json' !== strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Choose a .json export file.', 'ashbi-mega-menu' ) ), 400 );
		}
		if ( 0 === $size || $size > ABMM_Import_Export::MAX_IMPORT_BYTES ) {
			wp_send_json_error( array( 'message' => __( 'The import file is empty or exceeds the 1 MB limit.', 'ashbi-mega-menu' ) ), 400 );
		}
		$tmp = $file['tmp_name'] ?? '';
		if ( ! is_string( $tmp ) || ! is_uploaded_file( $tmp ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid upload.', 'ashbi-mega-menu' ) ), 400 );
		}
		$raw = file_get_contents( $tmp, false, null, 0, ABMM_Import_Export::MAX_IMPORT_BYTES + 1 );
		if ( false === $raw || strlen( $raw ) > ABMM_Import_Export::MAX_IMPORT_BYTES ) {
			wp_send_json_error( array( 'message' => __( 'The import file could not be read safely.', 'ashbi-mega-menu' ) ), 400 );
		}
		$payload = json_decode( $raw, true, 32 );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $payload ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid JSON file.', 'ashbi-mega-menu' ) ), 400 );
		}

		$exporter = ABMM_Import_Export::instance();
		$errors   = $exporter->validate_payload( $payload );

		if ( ! empty( $errors ) ) {
			wp_send_json_error( array( 'message' => implode( ' ', $errors ) ), 400 );
		}

		if ( ! empty( $_POST['preview'] ) ) {
			$preview = $exporter->preview_import( $payload, $mode );
			if ( is_wp_error( $preview ) ) {
				wp_send_json_error( array( 'message' => $preview->get_error_message() ), 400 );
			}
			wp_send_json_success( array( 'preview' => $preview ) );
		}

		$result = $exporter->import_json( $payload, $mode );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success( array( 'results' => $result ) );
	}

	/**
	 * Verify nonce and capability.
	 */
	private function verify_ajax() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
		}
	}
}
