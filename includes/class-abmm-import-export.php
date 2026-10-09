<?php
/**
 * Import / Export + REST API + WP-CLI for Ashbi Mega Menu.
 *
 * @package Ashbi_Mega_Menu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABMM_Import_Export {

	/**
	 * Singleton instance.
	 *
	 * @var ABMM_Import_Export|null
	 */
	private static $instance = null;

	/**
	 * REST API namespace.
	 */
	const REST_NAMESPACE = 'ashbi-mega-menu/v1';

	/**
	 * Portable JSON schema version.
	 */
	const FORMAT_VERSION = 1;

	/**
	 * Defensive limits for admin, REST, and CLI imports.
	 */
	const MAX_IMPORT_BYTES = 1048576;
	const MAX_IMPORT_MENUS = 50;
	const MAX_IMPORT_NODES = 5000;

	/**
	 * @return ABMM_Import_Export
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$this->register_wp_cli();
		}
	}

	/* ================================================================
	   REST API
	   ================================================================ */

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		// GET all menus
		register_rest_route(
			self::REST_NAMESPACE,
			'/menus',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_get_menus' ),
				'permission_callback' => array( $this, 'rest_permission_check' ),
			)
		);

		// GET single menu
		register_rest_route(
			self::REST_NAMESPACE,
			'/menus/(?P<id>[a-zA-Z0-9_-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_get_menu' ),
				'permission_callback' => array( $this, 'rest_permission_check' ),
			)
		);

		// POST import (replace or merge)
		register_rest_route(
			self::REST_NAMESPACE,
			'/menus/import',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_import_menus' ),
				'permission_callback' => array( $this, 'rest_permission_check' ),
				'args'                => array(
					'mode' => array(
						'default'           => 'merge',
						'validate_callback' => function ( $param ) {
							return in_array( $param, array( 'merge', 'replace', 'copy' ), true );
						},
					),
				),
			)
		);

		// POST export (returns JSON payload)
		register_rest_route(
			self::REST_NAMESPACE,
			'/menus/export',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_export_menus' ),
				'permission_callback' => array( $this, 'rest_permission_check' ),
				'args'                => array(
					'ids' => array(
						'required'          => false,
						'validate_callback' => function ( $param ) {
							return ! is_wp_error( $this->normalize_export_ids( $param ) );
						},
						'sanitize_callback' => function ( $param ) {
							$normalized = $this->normalize_export_ids( $param );
							return is_wp_error( $normalized ) ? $param : $normalized;
						},
					),
				),
			)
		);
	}

	/**
	 * REST permission check.
	 *
	 * @return bool
	 */
	public function rest_permission_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Normalize and validate an optional export ID list.
	 *
	 * @param mixed $ids Raw REST parameter.
	 * @return array|null|WP_Error
	 */
	private function normalize_export_ids( $ids ) {
		if ( null === $ids || '' === $ids ) {
			return null;
		}

		if ( ! is_array( $ids ) ) {
			return new WP_Error( 'abmm_invalid_export_ids', __( 'Menu IDs must be provided as an array.', 'ashbi-mega-menu' ), array( 'status' => 400 ) );
		}

		$normalized = array();
		foreach ( $ids as $id ) {
			if ( ! is_scalar( $id ) ) {
				return new WP_Error( 'abmm_invalid_export_ids', __( 'Each menu ID must be a string or number.', 'ashbi-mega-menu' ), array( 'status' => 400 ) );
			}

			$id = sanitize_key( (string) $id );
			if ( '' === $id ) {
				return new WP_Error( 'abmm_invalid_export_ids', __( 'Each menu ID must contain letters, numbers, underscores, or hyphens.', 'ashbi-mega-menu' ), array( 'status' => 400 ) );
			}

			$normalized[] = $id;
		}

		return array_values( array_unique( $normalized ) );
	}

	/**
	 * GET /menus — list all menus.
	 *
	 * @return WP_REST_Response
	 */
	public function rest_get_menus() {
		$menus = ABMM_Data::instance()->get_all();
		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $menus,
			),
			200
		);
	}

	/**
	 * GET /menus/{id} — single menu.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_get_menu( $request ) {
		$id   = $request->get_param( 'id' );
		$menu = ABMM_Data::instance()->get( $id );

		if ( null === $menu ) {
			return new WP_Error( 'abmm_menu_not_found', __( 'Menu not found.', 'ashbi-mega-menu' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $menu,
			),
			200
		);
	}

	/**
	 * POST /menus/import — import menus from JSON.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_import_menus( $request ) {
		$raw = $request->get_body();
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			$body = null;
		} elseif ( strlen( $raw ) > self::MAX_IMPORT_BYTES ) {
			return new WP_Error( 'abmm_import_too_large', __( 'Import files must be 1 MB or smaller.', 'ashbi-mega-menu' ), array( 'status' => 413 ) );
		} else {
			$body = json_decode( $raw, true, 32 );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				return new WP_Error( 'abmm_invalid_json', __( 'The request body is not valid JSON.', 'ashbi-mega-menu' ), array( 'status' => 400 ) );
			}
		}
		$mode = $request->get_param( 'mode' );

		if ( empty( $body ) ) {
			return new WP_Error( 'abmm_empty_body', __( 'No JSON data provided.', 'ashbi-mega-menu' ), array( 'status' => 400 ) );
		}

		$result = $this->import_json( $body, $mode );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $result,
			),
			200
		);
	}

	/**
	 * POST /menus/export — export menus as JSON.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_export_menus( $request ) {
		$ids = $this->normalize_export_ids( $request->get_param( 'ids' ) );
		if ( is_wp_error( $ids ) ) {
			return $ids;
		}
		$data = $this->export_json( $ids );

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			200
		);
	}

	/* ================================================================
	   Import / Export Logic
	   ================================================================ */

	/**
	 * Export menus to a structured JSON array.
	 *
	 * @param array|null $ids Optional list of menu IDs to export. Null = all.
	 * @return array|WP_Error
	 */
	public function export_json( $ids = null ) {
		$ids = $this->normalize_export_ids( $ids );
		if ( is_wp_error( $ids ) ) {
			return $ids;
		}

		$all   = ABMM_Data::instance()->get_all();
		$menus = array();

		foreach ( $all as $id => $menu ) {
			if ( null !== $ids && ! in_array( $id, $ids, true ) ) {
				continue;
			}
			$menus[ $id ] = $menu;
		}

		return array(
			'format'      => 'ashbi-mega-menu',
			'format_version' => self::FORMAT_VERSION,
			'plugin_version' => ABMM_VERSION,
			'version'     => ABMM_VERSION,
			'exported_at' => gmdate( 'c' ),
			'menus'       => $menus,
		);
	}

	/**
	 * Import menus from a structured JSON array.
	 *
	 * @param array  $payload Raw JSON-decoded payload.
	 * @param string $mode    'merge' or 'replace'.
	 * @return array|WP_Error Results per menu.
	 */
	public function import_json( $payload, $mode = 'merge' ) {
		$errors = $this->validate_payload( $payload );
		if ( ! empty( $errors ) ) {
			return new WP_Error( 'abmm_invalid_import', implode( ' ', $errors ) );
		}

		if ( ! in_array( $mode, array( 'merge', 'replace', 'copy' ), true ) ) {
			return new WP_Error( 'abmm_invalid_mode', __( 'Import mode must be merge, replace, or copy.', 'ashbi-mega-menu' ) );
		}

		$menus = array();

		// Accept both wrapped { menus: {...} } and flat { menu_id: {...} } formats.
		if ( isset( $payload['menus'] ) && is_array( $payload['menus'] ) ) {
			$menus = $payload['menus'];
		} elseif ( is_array( $payload ) ) {
			// Heuristic: if keys look like menu IDs and values have 'title' + 'items'.
			foreach ( $payload as $key => $value ) {
				if ( is_array( $value ) && isset( $value['title'] ) && isset( $value['items'] ) ) {
					$menus[ $key ] = $value;
				}
			}
		}

		if ( empty( $menus ) ) {
			return new WP_Error( 'abmm_no_menus', __( 'No valid menu data found in import file.', 'ashbi-mega-menu' ) );
		}

		$existing = ABMM_Data::instance()->get_all();
		$prepared = array();
		$results  = array();

		foreach ( $menus as $id => $menu_data ) {
			$id = sanitize_key( $id );

			if ( empty( $id ) || ! is_array( $menu_data ) ) {
				$results[ $id ] = array( 'status' => 'skipped', 'reason' => 'invalid_data' );
				continue;
			}

			if ( 'copy' === $mode ) {
				$original_id = $id;
				$id          = ABMM_Data::instance()->unique_menu_id( $id . '-copy' );
				$title       = sprintf( __( 'Copy of %s', 'ashbi-mega-menu' ), sanitize_text_field( $menu_data['title'] ?? $original_id ) );
				$menu_data   = ABMM_Data::instance()->duplicate_menu_data( $menu_data, $title );
			}
			$prepared[ $id ] = ABMM_Data::instance()->sanitize_menu( $menu_data );
		}

		if ( empty( $prepared ) ) {
			return $results;
		}

		$this->backup_current_menus();

		$next  = 'replace' === $mode ? $prepared : array_merge( $existing, $prepared );
		$saved = ABMM_Data::instance()->replace_all( $next );

		foreach ( $prepared as $id => $menu_data ) {
			$results[ $id ] = $saved
				? array(
					'status'     => 'imported',
					'action'     => isset( $existing[ $id ] ) ? 'updated' : 'created',
					'title'      => $menu_data['title'],
					'item_count' => count( $menu_data['items'] ),
				)
				: array( 'status' => 'failed', 'reason' => 'save_error' );
		}

		return $results;
	}

	/**
	 * Preview an import without changing options.
	 *
	 * @param array  $payload Validated import payload.
	 * @param string $mode    merge, replace, or copy.
	 * @return array|WP_Error
	 */
	public function preview_import( $payload, $mode = 'merge' ) {
		$errors = $this->validate_payload( $payload );
		if ( ! empty( $errors ) ) {
			return new WP_Error( 'abmm_invalid_import', implode( ' ', $errors ) );
		}
		if ( ! in_array( $mode, array( 'merge', 'replace', 'copy' ), true ) ) {
			return new WP_Error( 'abmm_invalid_mode', __( 'Import mode must be merge, replace, or copy.', 'ashbi-mega-menu' ) );
		}

		$menus    = isset( $payload['menus'] ) ? $payload['menus'] : $payload;
		$existing = ABMM_Data::instance()->get_all();
		$changes  = array();
		$normalized_ids = array();
		foreach ( $menus as $id => $menu ) {
			$id     = sanitize_key( $id );
			$normalized_ids[ $id ] = true;
			$action = 'copy' === $mode ? 'create_copy' : ( isset( $existing[ $id ] ) ? 'update' : 'create' );
			$target = 'copy' === $mode ? ABMM_Data::instance()->unique_menu_id( $id . '-copy' ) : $id;
			$changes[] = array(
				'id'         => $id,
				'target_id'  => $target,
				'title'      => sanitize_text_field( $menu['title'] ?? $id ),
				'action'     => $action,
				'item_count' => count( $menu['items'] ?? array() ),
			);
		}

		return array(
			'mode'            => $mode,
			'changes'         => $changes,
			'remove_count'    => 'replace' === $mode ? count( array_diff_key( $existing, $normalized_ids ) ) : 0,
			'existing_count'  => count( $existing ),
			'import_count'    => count( $changes ),
		);
	}

	/**
	 * Save a rolling snapshot before an import changes any menu.
	 *
	 * Snapshots are deliberately kept separate from the live menu option so an
	 * import failure or accidental replace does not overwrite its own backup.
	 *
	 * @return void
	 */
	private function backup_current_menus() {
		$backups = get_option( 'abmm_import_backups', array() );
		if ( ! is_array( $backups ) ) {
			$backups = array();
		}

		array_unshift(
			$backups,
			array(
				'created_at' => gmdate( 'c' ),
				'menus'      => ABMM_Data::instance()->get_all(),
			)
		);

		// Keep recent recovery points without growing the options table forever.
		$backups = array_slice( $backups, 0, 5 );
		update_option( 'abmm_import_backups', $backups );
	}

	/**
	 * Export a single menu to downloadable JSON.
	 *
	 * @param string $id Menu ID.
	 * @return array|WP_Error
	 */
	public function export_single_menu( $id ) {
		$menu = ABMM_Data::instance()->get( $id );
		if ( null === $menu ) {
			return new WP_Error( 'abmm_menu_not_found', __( 'Menu not found.', 'ashbi-mega-menu' ) );
		}

		return array(
			'format'      => 'ashbi-mega-menu',
			'format_version' => self::FORMAT_VERSION,
			'plugin_version' => ABMM_VERSION,
			'version'     => ABMM_VERSION,
			'exported_at' => gmdate( 'c' ),
			'menus'       => array( $id => $menu ),
		);
	}

	/**
	 * Validate an import payload before saving.
	 *
	 * @param array $payload
	 * @return array List of validation errors (empty = valid).
	 */
	public function validate_payload( $payload ) {
		$errors = array();

		if ( ! is_array( $payload ) ) {
			$errors[] = __( 'Import data must be a JSON object.', 'ashbi-mega-menu' );
			return $errors;
		}

		if ( isset( $payload['format'] ) && 'ashbi-mega-menu' !== $payload['format'] ) {
			$errors[] = __( 'This file is not an Ashbi Mega Menu export.', 'ashbi-mega-menu' );
		}

		if ( isset( $payload['format_version'] ) && self::FORMAT_VERSION !== absint( $payload['format_version'] ) ) {
			$errors[] = __( 'This export format version is not supported.', 'ashbi-mega-menu' );
		}

		$menus = isset( $payload['menus'] ) && is_array( $payload['menus'] )
			? $payload['menus']
			: $payload;

		if ( empty( $menus ) ) {
			$errors[] = __( 'No menus found in import data.', 'ashbi-mega-menu' );
			return $errors;
		}

		if ( count( $menus ) > self::MAX_IMPORT_MENUS ) {
			$errors[] = sprintf(
				/* translators: %d: maximum menus per import */
				__( 'An import can contain at most %d menus.', 'ashbi-mega-menu' ),
				self::MAX_IMPORT_MENUS
			);
			return $errors;
		}

		if ( $this->count_payload_nodes( $menus ) > self::MAX_IMPORT_NODES ) {
			$errors[] = __( 'The import contains too many nested items.', 'ashbi-mega-menu' );
			return $errors;
		}

		$normalized_ids = array();
		foreach ( $menus as $id => $menu ) {
			$normalized_id = sanitize_key( $id );
			if ( empty( $normalized_id ) ) {
				$errors[] = __( 'Every imported menu must have a valid ID.', 'ashbi-mega-menu' );
				continue;
			}
			if ( isset( $normalized_ids[ $normalized_id ] ) ) {
				$errors[] = sprintf(
					/* translators: %s: normalized menu ID */
					__( 'Multiple imported menus resolve to the same ID "%s". Rename them before importing.', 'ashbi-mega-menu' ),
					$normalized_id
				);
				continue;
			}
			$normalized_ids[ $normalized_id ] = true;

			if ( ! is_array( $menu ) ) {
				$errors[] = sprintf(
					/* translators: %s: menu ID */
					__( 'Menu "%s" is not a valid object.', 'ashbi-mega-menu' ),
					$id
				);
				continue;
			}
			if ( empty( $menu['title'] ) ) {
				$errors[] = sprintf(
					/* translators: %s: menu ID */
					__( 'Menu "%s" is missing a title.', 'ashbi-mega-menu' ),
					$id
				);
			}
			if ( ! isset( $menu['items'] ) || ! is_array( $menu['items'] ) ) {
				$errors[] = sprintf(
					/* translators: %s: menu ID */
					__( 'Menu "%s" is missing items array.', 'ashbi-mega-menu' ),
					$id
				);
			}
		}

		return $errors;
	}

	/**
	 * Count nested array values without executing or rendering imported content.
	 *
	 * @param array $value Value to inspect.
	 * @return int
	 */
	private function count_payload_nodes( $value ) {
		$count = 0;
		$stack = array( $value );

		while ( ! empty( $stack ) ) {
			$current = array_pop( $stack );
			foreach ( $current as $child ) {
				$count++;
				if ( is_array( $child ) ) {
					$stack[] = $child;
				}
				if ( $count > self::MAX_IMPORT_NODES ) {
					return $count;
				}
			}
		}

		return $count;
	}

	/* ================================================================
	   WP-CLI
	   ================================================================ */

	/**
	 * Register WP-CLI commands.
	 */
	private function register_wp_cli() {
		WP_CLI::add_command( 'abmm', 'ABMM_WP_CLI_Command' );
	}
}

/* ==================================================================
   WP-CLI Command Class
   ================================================================== */

if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI_Command' ) ) {

	class ABMM_WP_CLI_Command extends WP_CLI_Command {

		/**
		 * Export mega menus to a JSON file.
		 *
		 * ## OPTIONS
		 *
		 * [--file=<file>]
		 * : Path to the output JSON file. Defaults to wp-content/ashbi-mega-menu-export.json
		 *
		 * [--id=<id>]
		 * : Export a single menu by ID. If omitted, exports all menus.
		 *
		 * ## EXAMPLES
		 *
		 *     wp abmm export --file=/path/to/menu.json
		 *     wp abmm export --id=menu_main --file=/path/to/menu.json
		 *
		 * @param array $args
		 * @param array $assoc_args
		 */
		public function export( $args, $assoc_args ) {
			$file = $assoc_args['file'] ?? WP_CONTENT_DIR . '/ashbi-mega-menu-export.json';
			$id   = $assoc_args['id'] ?? null;

			$exporter = ABMM_Import_Export::instance();

			if ( $id ) {
				$data = $exporter->export_single_menu( $id );
			} else {
				$data = $exporter->export_json();
			}

			if ( is_wp_error( $data ) ) {
				WP_CLI::error( $data->get_error_message() );
			}

			$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
			$written = file_put_contents( $file, $json );

			if ( false === $written ) {
				WP_CLI::error( "Failed to write to {$file}" );
			}

			$menu_count = count( $data['menus'] ?? array() );
			WP_CLI::success( "Exported {$menu_count} menu(s) to {$file}" );
		}

		/**
		 * Import mega menus from a JSON file.
		 *
		 * ## OPTIONS
		 *
		 * [--file=<file>]
		 * : Path to the JSON import file. Required.
		 *
		 * [--mode=<mode>]
		 * : Import mode: 'merge' (default), 'replace', or 'copy'.
		 *
		 * [--dry-run]
		 * : Validate the file without importing.
		 *
		 * ## EXAMPLES
		 *
		 *     wp abmm import --file=/path/to/menu.json
		 *     wp abmm import --file=/path/to/menu.json --mode=replace
		 *     wp abmm import --file=/path/to/menu.json --dry-run
		 *
		 * @param array $args
		 * @param array $assoc_args
		 */
		public function import( $args, $assoc_args ) {
			$file    = $assoc_args['file'] ?? null;
			$mode    = $assoc_args['mode'] ?? 'merge';
			$dry_run = isset( $assoc_args['dry-run'] );

			if ( ! $file ) {
				WP_CLI::error( 'Please specify --file=<path>' );
			}

			if ( ! file_exists( $file ) ) {
				WP_CLI::error( "File not found: {$file}" );
			}

			$size = filesize( $file );
			if ( false === $size ) {
				WP_CLI::error( 'Could not determine the import file size.' );
			}
			if ( self::MAX_IMPORT_BYTES < $size ) {
				WP_CLI::error( 'Import files must be 1 MB or smaller.' );
			}

			$raw = file_get_contents( $file, false, null, 0, self::MAX_IMPORT_BYTES + 1 );
			if ( false === $raw || strlen( $raw ) > self::MAX_IMPORT_BYTES ) {
				WP_CLI::error( 'Import files must be 1 MB or smaller.' );
			}
			$payload = json_decode( $raw, true, 32 );

			if ( JSON_ERROR_NONE !== json_last_error() ) {
				WP_CLI::error( 'Invalid JSON in import file.' );
			}

			$exporter = ABMM_Import_Export::instance();
			$errors   = $exporter->validate_payload( $payload );

			if ( ! empty( $errors ) ) {
				foreach ( $errors as $error ) {
					WP_CLI::warning( $error );
				}
				WP_CLI::error( 'Validation failed. Fix errors above and try again.' );
			}

			if ( $dry_run ) {
				$menus = isset( $payload['menus'] ) && is_array( $payload['menus'] )
					? $payload['menus']
					: $payload;
				WP_CLI::success( 'Validation passed. ' . count( $menus ) . ' menu(s) ready to import.' );
				return;
			}

			$result = $exporter->import_json( $payload, $mode );

			if ( is_wp_error( $result ) ) {
				WP_CLI::error( $result->get_error_message() );
			}

			$created = 0;
			$updated = 0;
			$failed  = 0;

			foreach ( $result as $id => $info ) {
				if ( 'imported' === $info['status'] ) {
					$action = $info['action'];
					if ( 'created' === $action ) {
						$created++;
					} else {
						$updated++;
					}
					WP_CLI::log( "[{$action}] {$id} — {$info['title']} ({$info['item_count']} items)" );
				} else {
					$failed++;
					WP_CLI::warning( "[failed] {$id} — {$info['reason']}" );
				}
			}

			WP_CLI::success( "Import complete: {$created} created, {$updated} updated, {$failed} failed." );
		}

		/**
		 * List all mega menus.
		 *
		 * ## EXAMPLES
		 *
		 *     wp abmm list
		 *
		 * @param array $args
		 * @param array $assoc_args
		 */
		public function list( $args, $assoc_args ) {
			$menus = ABMM_Data::instance()->get_all();

			if ( empty( $menus ) ) {
				WP_CLI::warning( 'No menus found.' );
				return;
			}

			$rows = array();
			foreach ( $menus as $id => $menu ) {
				$rows[] = array(
					'id'       => $id,
					'title'    => $menu['title'] ?? '',
					'items'    => count( $menu['items'] ?? array() ),
					'shortcode' => '[ashbi_mega_menu id="' . $id . '" ]',
				);
			}

			WP_CLI\Utils\format_items( 'table', $rows, array( 'id', 'title', 'items', 'shortcode' ) );
		}
	}
}
