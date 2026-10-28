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
	 * Per-administrator, per-site setup guide state: array( 'dismissed' => bool, 'verified' => bool ).
	 * Stored as a user option, so on multisite each site keeps its own.
	 */
	const SETUP_GUIDE_META = 'abmm_setup_guide';

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
		add_action( 'wp_ajax_abmm_preview_markup', array( $this, 'ajax_preview_markup' ) );
		add_action( 'wp_ajax_abmm_onboarding', array( $this, 'ajax_onboarding' ) );
		add_action( 'wp_ajax_abmm_setup_guide', array( $this, 'ajax_setup_guide' ) );
		add_action( 'wp_ajax_abmm_menu_references', array( $this, 'ajax_menu_references' ) );
		add_action( 'wp_ajax_abmm_restore_menu', array( $this, 'ajax_restore_menu' ) );
		add_action( 'wp_ajax_abmm_permanently_delete_menu', array( $this, 'ajax_permanently_delete_menu' ) );
		add_action( 'admin_post_abmm_save_modules', array( $this, 'save_modules' ) );
	}

	/** Save optional module visibility. */
	public function save_modules() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage plugin modules.', 'ashbi-mega-menu' ) );
		}
		check_admin_referer( 'abmm_save_modules' );
		update_option( ABMM_CONTEXTUAL_NAV_OPTION, isset( $_POST['contextual_navigation'] ) ? '1' : '0', false );
		wp_safe_redirect( admin_url( 'admin.php?page=ashbi-mega-menu&modules-updated=1' ) );
		exit;
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
		wp_enqueue_style( 'abmm-brand-admin', ABMM_PLUGIN_URL . 'assets/css/brand-admin.css', array( 'abmm-admin' ), ABMM_VERSION );
		if ( ! $is_builder_page ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wplink' );
		wp_enqueue_style( 'editor-buttons' );
		wp_enqueue_script( 'abmm-menu-search-preview', ABMM_PLUGIN_URL . 'assets/js/frontend.js', array(), ABMM_VERSION, true );
		wp_localize_script( 'abmm-menu-search-preview', 'abmmRuntime', array( 'adminPreview' => true, 'searchStrings' => abmm_menu_search_strings() ) );

		wp_enqueue_script( 'abmm-preview-canvas', ABMM_PLUGIN_URL . 'assets/js/preview-canvas.js', array(), ABMM_VERSION, true );
		wp_enqueue_script(
			'abmm-admin',
			ABMM_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'jquery-ui-sortable', 'wp-color-picker', 'wplink', 'wp-i18n', 'abmm-menu-search-preview', 'abmm-preview-canvas' ),
			ABMM_VERSION,
			true
		);
		wp_set_script_translations( 'abmm-admin', 'ashbi-mega-menu', ABMM_PLUGIN_DIR . 'languages' );

		$icons = ABMM_Icons::instance();

		wp_localize_script(
			'abmm-admin',
			'abmmAdmin',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'abmm_admin' ),
				'icons'     => $icons->all(),
				'iconLabels'=> $icons->labels(),
				'importMaxBytes' => ABMM_Import_Export::MAX_IMPORT_BYTES,
				'importMaxLabel' => size_format( ABMM_Import_Export::MAX_IMPORT_BYTES ),
				'restUrl'   => rest_url( ABMM_Import_Export::REST_NAMESPACE ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
				'userId'    => get_current_user_id(),
				// The phone and tablet previews load the live stylesheet and script
				// in a frame at the device width, so its media queries apply.
				'previewAssets' => array(
					'css'     => add_query_arg( 'ver', ABMM_VERSION, ABMM_PLUGIN_URL . 'assets/css/frontend.css' ),
					'js'      => add_query_arg( 'ver', ABMM_VERSION, ABMM_PLUGIN_URL . 'assets/js/frontend.js' ),
					'runtime' => array( 'publicDistribution' => defined( 'ABMM_PUBLIC_DISTRIBUTION' ) && ABMM_PUBLIC_DISTRIBUTION, 'searchStrings' => abmm_menu_search_strings() ),
				),
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
					'addLink'         => __( 'Add link', 'ashbi-mega-menu' ),
					'addNavItem'      => __( 'Add Menu Item', 'ashbi-mega-menu' ),
					'copyShortcode'   => __( 'Shortcode copied!', 'ashbi-mega-menu' ),
					'copyError'       => __( 'Automatic copy failed. The shortcode is selected below; press Ctrl+C or Command+C.', 'ashbi-mega-menu' ),
					'creatingMenu'    => __( 'Creating menu…', 'ashbi-mega-menu' ),
					'createError'     => __( 'The menu could not be created. Check your connection and permissions, then try again.', 'ashbi-mega-menu' ),
					'deletingMenu'    => __( 'Deleting menu…', 'ashbi-mega-menu' ),
					'deleteSuccess'   => __( 'Menu deleted.', 'ashbi-mega-menu' ),
					'deleteError'     => __( 'The menu could not be deleted. Nothing was removed. Try again.', 'ashbi-mega-menu' ),
					'archiveMenu'     => __( 'Archive menu', 'ashbi-mega-menu' ),
					'archiveChecking' => __( 'Checking placements…', 'ashbi-mega-menu' ),
					/* translators: %1$s: menu title. */
					'archiveConfirmTitle' => __( 'Archive “%1$s”?', 'ashbi-mega-menu' ),
					/* translators: 1: number of block placements, 2: number of widget placements, 3: number of shortcodes in pages. */
					'archivePlacements' => __( 'Used in: %1$d block(s), %2$d widget(s), %3$d shortcode(s).', 'ashbi-mega-menu' ),
					'archiveConfirm'  => __( 'Archive and keep recoverable', 'ashbi-mega-menu' ),
					'archiveSuccess'  => __( 'Menu archived. It can be restored below.', 'ashbi-mega-menu' ),
					'restoreSuccess'  => __( 'Menu restored with its original ID.', 'ashbi-mega-menu' ),
					'conflictError'   => __( 'This menu changed on the server. Your local draft was preserved; reload or export it before continuing.', 'ashbi-mega-menu' ),
					'selectIcon'      => __( 'Choose an icon', 'ashbi-mega-menu' ),
					'uploadIcon'      => __( 'Upload custom icon', 'ashbi-mega-menu' ),
					'categoryTitle'   => __( 'Category title', 'ashbi-mega-menu' ),
					'categoryDesc'    => __( 'Short description', 'ashbi-mega-menu' ),
					'panelTitle'      => __( '"See all" link text (optional)', 'ashbi-mega-menu' ),
					'panelTitleHelp'  => __( 'Shown at the bottom of this category\'s panel. Add a link to make it clickable.', 'ashbi-mega-menu' ),
					'panelTitleHint'  => __( 'e.g. See all products', 'ashbi-mega-menu' ),
					'panelUrl'        => __( 'Heading link (optional)', 'ashbi-mega-menu' ),
					'groupUrl'        => __( 'Column heading link (optional)', 'ashbi-mega-menu' ),
					'linkLabel'       => __( 'Link label', 'ashbi-mega-menu' ),
					'linkUrl'         => __( 'Search or type URL', 'ashbi-mega-menu' ),
					'selectLink'      => __( 'Select / edit link', 'ashbi-mega-menu' ),
					'megaMenu'        => __( 'Mega Menu', 'ashbi-mega-menu' ),
					'simpleLink'      => __( 'Simple Link', 'ashbi-mega-menu' ),
					'dragHint'        => __( 'Drag to reorder', 'ashbi-mega-menu' ),
					'moveUp'          => __( 'Move up', 'ashbi-mega-menu' ),
					'moveDown'        => __( 'Move down', 'ashbi-mega-menu' ),
					/* translators: %s: name of the item being moved. */
					'moveUpLabel'     => __( 'Move %s up', 'ashbi-mega-menu' ),
					/* translators: %s: name of the item being moved. */
					'moveDownLabel'   => __( 'Move %s down', 'ashbi-mega-menu' ),
					/* translators: %s: name of the item being moved. */
					'positionControls' => __( '%s position controls', 'ashbi-mega-menu' ),
					'exportSuccess'   => __( 'Menu exported successfully!', 'ashbi-mega-menu' ),
					'exportError'     => __( 'Export failed.', 'ashbi-mega-menu' ),
					'importSuccess'   => __( 'Menu imported successfully!', 'ashbi-mega-menu' ),
					'importError'     => __( 'Import failed. Check the file format.', 'ashbi-mega-menu' ),
					'importInvalid'   => __( 'Invalid JSON file.', 'ashbi-mega-menu' ),
					'importNoMenus'   => __( 'No valid menus found in file.', 'ashbi-mega-menu' ),
					'importPreview'   => __( 'Review these changes before importing:', 'ashbi-mega-menu' ),
					'importConfirm'   => __( 'Confirm Import', 'ashbi-mega-menu' ),
					'importCancel'    => __( 'Cancel', 'ashbi-mega-menu' ),
					'draftStale' => __( 'This local draft is based on an older saved version. Download it before continuing. You may restore it for review, but saving is disabled; reload the saved version and reapply the changes you want.', 'ashbi-mega-menu' ),
					'draftAvailable' => __( 'Restore the unsaved changes saved in this browser, or download them before discarding and continuing with the saved version.', 'ashbi-mega-menu' ),
					'checkName' => __( 'Add a menu name.', 'ashbi-mega-menu' ),
					/* translators: %s: item number, label, count, or formatted file size. */
					'checkLabel' => __( 'Add a label to top menu item %s.', 'ashbi-mega-menu' ),
					/* translators: %s: item number, label, count, or formatted file size. */
					'checkMega' => __( 'Add at least one labelled link with a real destination to %s.', 'ashbi-mega-menu' ),
					'thisMega' => __( 'this mega menu', 'ashbi-mega-menu' ),
					/* translators: %s: item number, label, count, or formatted file size. */
					'checkDuplicate' => __( 'Rename duplicate top menu item "%s".', 'ashbi-mega-menu' ),
					'checkDestination' => __( 'Add at least one labelled item with a real destination.', 'ashbi-mega-menu' ),
					'checkCta' => __( 'Complete the enabled call-to-action label and destination.', 'ashbi-mega-menu' ),
					/* translators: %s: menu item label. */
					'checkLong' => __( 'Shorten "%s" so it fits in the menu bar.', 'ashbi-mega-menu' ),
					/* translators: %s: link label. */
					'checkUrl' => __( 'Check the link for "%s". It does not look like a web address.', 'ashbi-mega-menu' ),
					'checkBrand' => __( 'Add a logo image or site title, or turn off "Show logo".', 'ashbi-mega-menu' ),
					'checkLogo' => __( 'Check the logo image link. It does not look like a web address.', 'ashbi-mega-menu' ),
					'logoBroken' => __( 'Logo image not found', 'ashbi-mega-menu' ),
					'checkPassed' => __( 'Ready to place.', 'ashbi-mega-menu' ),
					/* translators: %s: item number, label, count, or formatted file size. */
					'checkPending' => __( '%s thing(s) to fix before you place this menu.', 'ashbi-mega-menu' ),
					'placementUnsaved' => __( 'You have unsaved changes. Save first: the shortcode shows the saved version.', 'ashbi-mega-menu' ),
					'placementSaved' => __( 'Copy the shortcode, or use the block or widget.', 'ashbi-mega-menu' ),
					'placementDraft' => __( 'This menu still has items in its checklist. You can place it, but fix those first.', 'ashbi-mega-menu' ),
					'importing' => __( 'Importing…', 'ashbi-mega-menu' ),
					'preparingImport' => __( 'Preparing preview…', 'ashbi-mega-menu' ),
					/* translators: %s: item number, label, count, or formatted file size. */
					'importTooLarge' => __( 'The import file exceeds the %s limit.', 'ashbi-mega-menu' ),
					'backupLoading'   => __( 'Loading recovery snapshots…', 'ashbi-mega-menu' ),
					'backupLoadError' => __( 'Snapshots could not be loaded.', 'ashbi-mega-menu' ),
					/* translators: 1: snapshot date, 2: number of menus. */
					'backupSummary'   => __( '%1$s — %2$s menu(s)', 'ashbi-mega-menu' ),
					'backupExport'    => __( 'Export snapshot', 'ashbi-mega-menu' ),
					'backupRestore'   => __( 'Restore snapshot', 'ashbi-mega-menu' ),
					'backupGuidance'  => __( 'Export a snapshot before restoring. Restore replaces every current menu.', 'ashbi-mega-menu' ),
					'backupEmpty'     => __( 'No recovery snapshots are available yet.', 'ashbi-mega-menu' ),
					'backupExportError' => __( 'Snapshot export failed.', 'ashbi-mega-menu' ),
					'backupExported'  => __( 'Snapshot exported. Keep the downloaded JSON file for recovery.', 'ashbi-mega-menu' ),
					'backupConfirmWarning' => __( 'Replace ALL current menus with this snapshot? Current menus will be backed up first. Published placements may change. Export your current menus before continuing.', 'ashbi-mega-menu' ),
					'backupConfirm'   => __( 'Replace menus with snapshot', 'ashbi-mega-menu' ),
					'backupRestoring' => __( 'Restoring snapshot…', 'ashbi-mega-menu' ),
					'backupRestoreError' => __( 'Snapshot restore failed. Reload snapshots before retrying.', 'ashbi-mega-menu' ),
					'backupRestored'  => __( 'Snapshot restored. Reloading menus…', 'ashbi-mega-menu' ),
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
		$placement_recommendation = $this->placement_recommendation();
		$setup_guide              = $this->setup_guide_state( $menus, $readiness );
		$onboarding_pages         = $show_onboarding ? get_pages( array( 'sort_column' => 'menu_order,post_title', 'post_status' => 'publish', 'number' => 200 ) ) : array();
		$contextual_navigation_enabled = abmm_contextual_navigation_enabled();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only selection/filter; admin capability is checked and output is escaped.
		$edit_id = isset( $_GET['edit'] ) ? sanitize_text_field( wp_unslash( $_GET['edit'] ) ) : '';

		if ( '' !== $edit_id && isset( $menus[ $edit_id ] ) ) {
			include ABMM_PLUGIN_DIR . 'admin/views/builder.php';
		} else {
			include ABMM_PLUGIN_DIR . 'admin/views/list.php';
		}
	}

	/**
	 * Render unsaved menu data with the live markup for the device preview.
	 * Nothing is stored: the data is sanitized like a save and rendered.
	 */
	public function ajax_preview_markup() {
		check_ajax_referer( 'abmm_admin', 'nonce' );
		$this->verify_ajax();

		$id  = isset( $_POST['menu_id'] ) ? sanitize_key( wp_unslash( $_POST['menu_id'] ) ) : '';
		$raw = isset( $_POST['menu_data'] ) ? wp_unslash( $_POST['menu_data'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Decoded below and passed through sanitize_menu() before rendering.
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid menu data.', 'ashbi-mega-menu' ) ), 400 );
		}
		if ( strlen( $raw ) > ABMM_Import_Export::MAX_MENU_BYTES ) {
			wp_send_json_error( array( 'message' => __( 'Menu data exceeds the 1 MB limit.', 'ashbi-mega-menu' ) ), 413 );
		}
		$data = json_decode( $raw, true, 32 );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid menu data.', 'ashbi-mega-menu' ) ), 400 );
		}

		$menu = ABMM_Data::instance()->sanitize_menu( $data );
		wp_send_json_success( array( 'html' => ABMM_Frontend::instance()->render_preview( $menu, '' !== $id ? $id : 'preview' ) ) );
	}

	/**
	 * AJAX: save menu.
	 */
	public function ajax_save_menu() {
		check_ajax_referer( 'abmm_admin', 'nonce' );
		$this->verify_ajax();

		$id  = isset( $_POST['menu_id'] ) ? sanitize_text_field( wp_unslash( $_POST['menu_id'] ) ) : '';
		$raw = isset( $_POST['menu_data'] ) ? wp_unslash( $_POST['menu_data'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Structured input is validated and sanitized field by field before persistence; upload paths are verified with is_uploaded_file().
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid menu data.', 'ashbi-mega-menu' ) ), 400 );
		}
		if ( strlen( $raw ) > ABMM_Import_Export::MAX_MENU_BYTES ) {
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
		if ( '' === $id ) {
			wp_send_json_error( array( 'message' => 'Missing ID' ), 400 );
		}

		$result = ABMM_Data::instance()->archive( $id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 503 );
		}
		if ( ! $result ) {
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
		} elseif ( 'pages' === $choice ) {
			$page_ids = isset( $_POST['page_ids'] ) && is_array( $_POST['page_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['page_ids'] ) ) : array();
			$id       = $data->create_from_pages( $page_ids );
			if ( is_wp_error( $id ) && 'abmm_no_pages' === $id->get_error_code() ) {
				wp_send_json_error( array( 'message' => $id->get_error_message() ), 400 );
			}
		} elseif ( 'dismiss' !== $choice ) {
			wp_send_json_error( array( 'message' => __( 'Choose a valid first-run option.', 'ashbi-mega-menu' ) ), 400 );
		}
		if ( is_wp_error( $id ) ) {
			wp_send_json_error( array( 'message' => $id->get_error_message() ), 503 );
		}
		if ( in_array( $choice, array( 'blank', 'starter', 'pages' ), true ) && ! $id ) {
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
	 * The safest placement for the active theme.
	 *
	 * @return array { method: block|widget|shortcode, label, steps }
	 */
	public function placement_recommendation() {
		if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			return array(
				'method' => 'block',
				'label'  => __( 'Block in your Header', 'ashbi-mega-menu' ),
				'steps'  => __( 'Your theme is a block theme. In Appearance → Editor, open the Header template part, add the Ashbi Mega Menu block next to the existing Navigation block and pick your menu.', 'ashbi-mega-menu' ),
			);
		}
		global $wp_registered_sidebars;
		if ( current_theme_supports( 'widgets' ) && ! empty( $wp_registered_sidebars ) ) {
			return array(
				'method' => 'widget',
				'label'  => __( 'Widget', 'ashbi-mega-menu' ),
				'steps'  => __( 'Your theme has widget areas. In Appearance → Widgets, add the Ashbi Mega Menu widget to a header area if your theme has one. Otherwise paste the shortcode into a page or use the PHP helper in a child theme.', 'ashbi-mega-menu' ),
			);
		}
		return array(
			'method' => 'shortcode',
			'label'  => __( 'Shortcode or PHP', 'ashbi-mega-menu' ),
			'steps'  => __( 'Your theme has no block header or widget areas. Paste the shortcode into a page or page builder, or ask a developer to call abmm_render_menu() in a child theme header.', 'ashbi-mega-menu' ),
		);
	}

	/**
	 * Setup guide progress for the current administrator.
	 *
	 * Create, edit and place are read from the site so they stay true; only
	 * "checked on the live site" and hiding the guide are stored per user.
	 *
	 * @param array $menus     Active menus.
	 * @param array $readiness Readiness per menu ID.
	 * @return array
	 */
	public function setup_guide_state( $menus, $readiness ) {
		$stored = get_user_option( self::SETUP_GUIDE_META, get_current_user_id() );
		$stored = is_array( $stored ) ? $stored : array();
		$ready  = '';
		foreach ( $readiness as $menu_id => $result ) {
			if ( ! empty( $result['ready'] ) ) {
				$ready = (string) $menu_id;
				break;
			}
		}
		$placed = '';
		foreach ( array_keys( $menus ) as $menu_id ) {
			if ( $this->reference_summary( (string) $menu_id )['known'] > 0 ) {
				$placed = (string) $menu_id;
				break;
			}
		}
		$first = $menus ? (string) array_key_first( $menus ) : '';
		$steps = array(
			'create' => ! empty( $menus ),
			'edit'   => '' !== $ready,
			// Theme files and page builders can't be detected; checking it live counts.
			'place'  => '' !== $placed || ! empty( $stored['verified'] ),
			'verify' => ! empty( $stored['verified'] ),
		);
		return array(
			'steps'     => $steps,
			'complete'  => ! in_array( false, $steps, true ),
			'dismissed' => ! empty( $stored['dismissed'] ),
			'menu_id'   => '' !== $placed ? $placed : ( '' !== $ready ? $ready : $first ),
		);
	}

	/**
	 * AJAX: hide, reopen or tick the setup guide for the current administrator.
	 */
	public function ajax_setup_guide() {
		check_ajax_referer( 'abmm_admin', 'nonce' );
		$this->verify_ajax();
		$action = isset( $_POST['guide_action'] ) ? sanitize_key( wp_unslash( $_POST['guide_action'] ) ) : '';
		$state  = get_user_option( self::SETUP_GUIDE_META, get_current_user_id() );
		$state  = array_merge( array( 'dismissed' => false, 'verified' => false ), is_array( $state ) ? $state : array() );
		switch ( $action ) {
			case 'dismiss':
				$state['dismissed'] = true;
				break;
			case 'reopen':
				$state['dismissed'] = false;
				break;
			case 'verify':
				$state['verified'] = true;
				break;
			case 'unverify':
				$state['verified'] = false;
				break;
			default:
				wp_send_json_error( array( 'message' => __( 'Unknown setup guide action.', 'ashbi-mega-menu' ) ), 400 );
		}
		update_user_option( get_current_user_id(), self::SETUP_GUIDE_META, array( 'dismissed' => (bool) $state['dismissed'], 'verified' => (bool) $state['verified'] ) );
		wp_send_json_success( $state );
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
		$result = ABMM_Data::instance()->restore( $id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 503 );
		}
		if ( ! $result ) {
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
		$result = ABMM_Data::instance()->permanently_delete( $id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 503 );
		}
		if ( ! $result ) {
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
		$blocks     = 0;
		$widgets    = 0;
		$shortcodes = 0;
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

			// Block-theme headers live in template parts, which 'any' leaves out.
			$post_types = array_merge( array_values( get_post_types( array( 'public' => true ) ) ), array( 'wp_template', 'wp_template_part', 'wp_block' ) );
			$posts = get_posts(
				array(
					'post_type'      => $post_types,
					'post_status'    => 'any',
					'posts_per_page' => -1,
					's'              => $menu_id,
				)
			);
			foreach ( $posts as $post ) {
				$blocks     += $this->count_menu_blocks( parse_blocks( $post->post_content ?? '' ), $menu_id );
				$shortcodes += $this->count_menu_shortcodes( (string) ( $post->post_content ?? '' ), $menu_id );
			}
		}

		return array(
			'blocks'     => $blocks,
			'widgets'    => $widgets,
			'shortcodes' => $shortcodes,
			'known'      => $blocks + $widgets + $shortcodes,
			'warning'    => __( 'Menus placed in theme templates, page builders or PHP cannot be detected. Check those before archiving.', 'ashbi-mega-menu' ),
		);
	}

	/**
	 * Count [ashbi_mega_menu id="…"] shortcodes for one menu in post content.
	 *
	 * @param string $content Post content.
	 * @param string $menu_id Menu ID.
	 * @return int
	 */
	private function count_menu_shortcodes( $content, $menu_id ) {
		if ( false === strpos( $content, '[ashbi_mega_menu' ) || ! function_exists( 'get_shortcode_regex' ) ) {
			return 0;
		}
		$count = 0;
		if ( preg_match_all( '/' . get_shortcode_regex( array( 'ashbi_mega_menu' ) ) . '/', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$atts = shortcode_parse_atts( $match[3] );
				if ( is_array( $atts ) && isset( $atts['id'] ) && (string) $menu_id === (string) $atts['id'] ) {
					++$count;
				}
			}
		}
		return $count;
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
		if ( is_wp_error( $id ) ) {
			wp_send_json_error( array( 'message' => $id->get_error_message() ), 503 );
		}
		if ( false === $id || '' === $id ) {
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
		if ( '' === $id ) {
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

		if ( ! isset( $_FILES['import_file'] ) || ! is_array( $_FILES['import_file'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded or upload error.', 'ashbi-mega-menu' ) ), 400 );
		}

		$file = $_FILES['import_file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Structured input is validated and sanitized field by field before persistence; upload paths are verified with is_uploaded_file().
		// A single PHP upload has string paths and integer size/error metadata.
		// Reject nested/multi-file fields before any sanitizer or filesystem call.
		if ( ! isset( $file['name'], $file['size'], $file['error'], $file['tmp_name'] )
			|| ! is_string( $file['name'] ) || ! is_string( $file['tmp_name'] )
			|| ! is_int( $file['size'] ) || ! is_int( $file['error'] )
			|| UPLOAD_ERR_OK !== $file['error'] ) {
			wp_send_json_error( array( 'message' => __( 'Invalid upload metadata.', 'ashbi-mega-menu' ) ), 400 );
		}
		$mode = isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : 'merge';

		if ( ! in_array( $mode, array( 'merge', 'replace', 'copy' ), true ) ) {
			$mode = 'merge';
		}

		$filename = sanitize_file_name( $file['name'] );
		$size     = $file['size'];
		if ( 'json' !== strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Choose a .json export file.', 'ashbi-mega-menu' ) ), 400 );
		}
		if ( $size <= 0 || $size > ABMM_Import_Export::MAX_IMPORT_BYTES ) {
			wp_send_json_error( array( 'message' => sprintf( /* translators: %s: maximum formatted import size. */ __( 'The import file is empty or exceeds the %s limit.', 'ashbi-mega-menu' ), size_format( ABMM_Import_Export::MAX_IMPORT_BYTES ) ) ), 400 );
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
