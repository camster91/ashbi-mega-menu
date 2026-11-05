<?php
/**
 * Clear the page cache when a menu changes, so visitors never see an old header.
 *
 * Only NitroPack is cleared: it is the one page cache this plugin already works
 * with. It is cleared at most once per request, after the menus are stored.
 *
 * @package Ashbi_Mega_Menu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABMM_Cache {

	/**
	 * @var ABMM_Cache|null
	 */
	private static $instance = null;

	/**
	 * A menu changed in this request and the cache is not cleared yet.
	 *
	 * @var bool
	 */
	private $pending = false;

	/**
	 * What clearing did: true cleared, false failed, null not tried.
	 *
	 * @var bool|null
	 */
	private $result = null;

	/**
	 * @return ABMM_Cache
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'abmm_menu_saved', array( $this, 'mark_changed' ) );
		add_action( 'abmm_menu_deleted', array( $this, 'mark_changed' ) );
		// These hooks run inside the save lock, so the clearing waits until later.
		add_action( 'shutdown', array( $this, 'flush' ) );
	}

	public function mark_changed() {
		$this->pending = null === $this->result;
	}

	/**
	 * Clear the cache if a menu changed. Save requests call this to report the
	 * result; every other request that changed a menu reaches it at shutdown.
	 *
	 * @return bool|null True when cleared, false when it failed, null when there
	 *                   was nothing to do or no cache to clear.
	 */
	public function flush() {
		if ( ! $this->pending ) {
			return $this->result;
		}
		$this->pending = false;
		if ( ! function_exists( 'nitropack_sdk_purge' ) ) {
			return null;
		}
		try {
			$this->result = (bool) nitropack_sdk_purge( null, null, 'Ashbi Mega Menu: a menu changed' );
		} catch ( \Throwable $error ) {
			$this->result = false;
		}
		return $this->result;
	}
}
