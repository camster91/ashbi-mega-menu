<?php
/**
 * Page-level menu context selection.
 *
 * @package Ashbi_Mega_Menu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABMM_Page_Context {

	/**
	 * Page meta key used for an explicit product-profile exception.
	 */
	const META_KEY = '_abmm_product_profile';

	/**
	 * @var ABMM_Page_Context|null
	 */
	private static $instance = null;

	/**
	 * @return ABMM_Page_Context
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 10, 2 );
		add_action( 'save_post', array( $this, 'save_meta_box' ), 10, 2 );
	}

	/**
	 * Add an exception-only selector. Most pages inherit a product profile from
	 * their product hub, so editors do not have to configure every child page.
	 *
	 * @return void
	 */
	public function add_meta_box( $post_type, $post ) {
		$type_object = get_post_type_object( $post_type );
		if ( ! $type_object || ( empty( $type_object->public ) && 'elementor_library' !== $post_type ) ) {
			return;
		}
		add_meta_box(
			'abmm-page-context',
			__( 'Ashbi Mega Menu context', 'ashbi-mega-menu' ),
			array( $this, 'render_meta_box' ),
			$post_type,
			'side',
			'default'
		);
	}

	/**
	 * Render the menu selector on WordPress pages.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function render_meta_box( $post ) {
		$profiles = ABMM_Product_Profiles::instance()->get_all();
		$selected = get_post_meta( $post->ID, self::META_KEY, true );
		wp_nonce_field( 'abmm_save_page_context', 'abmm_page_context_nonce' );
		?>
		<p><?php esc_html_e( 'Usually this page inherits product navigation from its product hub. Choose a profile only when this page is a genuine exception.', 'ashbi-mega-menu' ); ?></p>
		<label class="screen-reader-text" for="abmm-page-context-profile"><?php esc_html_e( 'Product navigation override', 'ashbi-mega-menu' ); ?></label>
		<select class="widefat" id="abmm-page-context-profile" name="abmm_page_context_profile">
			<option value=""><?php esc_html_e( 'Inherit or use global navigation', 'ashbi-mega-menu' ); ?></option>
			<?php foreach ( $profiles as $profile_id => $profile ) : ?>
				<option value="<?php echo esc_attr( $profile_id ); ?>" <?php selected( $selected, $profile_id ); ?>>
					<?php echo esc_html( $profile['title'] ?: $profile['label'] ?: $profile_id ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php if ( empty( $profiles ) ) : ?>
			<p class="description"><?php esc_html_e( 'Create product profiles under Mega Menu → Product Navigation, then return here only if this page needs an exception.', 'ashbi-mega-menu' ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Persist a selected exception only when it references an existing profile.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Current post.
	 * @return void
	 */
	public function save_meta_box( $post_id, $post ) {
		if (
			( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ||
			wp_is_post_revision( $post_id ) ||
			! isset( $_POST['abmm_page_context_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['abmm_page_context_nonce'] ) ), 'abmm_save_page_context' ) ||
			! current_user_can( 'edit_post', $post_id )
		) {
			return;
		}

		$profile_id = isset( $_POST['abmm_page_context_profile'] )
			? sanitize_key( wp_unslash( $_POST['abmm_page_context_profile'] ) )
			: '';
		$profiles = ABMM_Product_Profiles::instance()->get_all();

		if ( '' === $profile_id || ! isset( $profiles[ $profile_id ] ) ) {
			delete_post_meta( $post_id, self::META_KEY );
			return;
		}

		update_post_meta( $post_id, self::META_KEY, $profile_id );
	}

	/**
	 * Resolve a product profile for the queried page. Kept as a small public
	 * bridge for integrations created during the earlier page-context draft.
	 *
	 * @return array{profile:array|null,source:string,post_id:int}
	 */
	public function resolve_profile() {
		return ABMM_Product_Profiles::instance()->resolve_for_request();
	}
}
