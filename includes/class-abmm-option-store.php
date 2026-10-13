<?php
/** Locked persistence and private recovery drafts for product settings. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class ABMM_Option_Store {

	public static function revision( $record ) {
		return hash( 'sha256', wp_json_encode( $record ) );
	}

	public static function locked( $callback ) {
		return ABMM_Data::instance()->with_collection_lock( function () use ( $callback ) {
			self::refresh();
			return call_user_func( $callback );
		} );
	}

	public static function refresh() {
		foreach ( array( 'abmm_product_profiles', 'abmm_product_branding', 'alloptions', 'notoptions' ) as $key ) {
			wp_cache_delete( $key, 'options' );
		}
	}

	/** A false update can mean unchanged data; verify the actual stored value. */
	public static function persist( $key, $value ) {
		update_option( $key, $value, false );
		self::refresh();
		if ( get_option( $key, array() ) !== $value ) {
			return new WP_Error( 'abmm_product_persist_failed', __( 'Your changes could not be stored. Your draft is retained. Retry, or ask your administrator to check the database.', 'ashbi-mega-menu' ) );
		}
		return true;
	}

	/** Redirect with an expiring, administrator-specific draft; never place fields in the URL. */
	public static function recover( $page, $id, $record, $error ) {
		$token = wp_generate_uuid4();
		$key = 'abmm_product_draft_' . get_current_user_id() . '_' . $token;
		$draft = array( 'page' => $page, 'id' => $id, 'record' => $record, 'error' => $error );
		if ( ! set_transient( $key, $draft, 30 * MINUTE_IN_SECONDS ) ) {
			wp_die( esc_html__( 'The changes and recovery draft could not be saved. Use your browser Back button to recover your entered values and retry.', 'ashbi-mega-menu' ), '', array( 'back_link' => true ) );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => $page, 'profile' => $id, 'abmm_draft' => $token, '_abmm_draft_nonce' => wp_create_nonce( 'abmm_product_draft_' . $token ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function draft( $page ) {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_GET['abmm_draft'], $_GET['_abmm_draft_nonce'] ) || ! is_string( $_GET['abmm_draft'] ) || ! is_string( $_GET['_abmm_draft_nonce'] ) ) { return null; }
		$token = sanitize_key( wp_unslash( $_GET['abmm_draft'] ) );
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_abmm_draft_nonce'] ) ), 'abmm_product_draft_' . $token ) ) { return null; }
		$draft = get_transient( 'abmm_product_draft_' . get_current_user_id() . '_' . $token );
		return is_array( $draft ) && $page === $draft['page'] ? $draft : null;
	}
}
