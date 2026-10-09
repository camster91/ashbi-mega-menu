<?php
/**
 * WordPress Widget: Ashbi Mega Menu.
 *
 * @package Ashbi_Mega_Menu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ABMM_Widget
 *
 * Displays a mega menu in any widget area.
 */
class ABMM_Widget extends WP_Widget {

	/**
	 * Sets up the widget.
	 */
	public function __construct() {
		parent::__construct(
			'abmm_widget',
			__( 'Ashbi Mega Menu', 'ashbi-mega-menu' ),
			array(
				'description' => __( 'Display a mega menu in any widget area.', 'ashbi-mega-menu' ),
				'classname'   => 'abmm-widget',
			)
		);
	}

	/**
	 * Outputs the widget content on the frontend.
	 *
	 * @param array $args     Display arguments.
	 * @param array $instance Settings for the current widget instance.
	 */
	public function widget( $args, $instance ) {
		$title   = ! empty( $instance['title'] ) ? $instance['title'] : '';
		$menu_id = ! empty( $instance['menu_id'] ) ? $instance['menu_id'] : '';
		$context = 'page' === ( $instance['context'] ?? '' ) ? 'page' : '';

		if ( ! $menu_id ) {
			return;
		}

		echo $args['before_widget'];

		if ( $title ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
		}

		echo ABMM_Frontend::instance()->render( $menu_id, array( 'context' => $context ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		echo $args['after_widget'];
	}

	/**
	 * Outputs the settings form in the admin Widgets screen.
	 *
	 * @param array $instance Current settings.
	 */
	public function form( $instance ) {
		$title   = ! empty( $instance['title'] ) ? $instance['title'] : '';
		$menu_id = ! empty( $instance['menu_id'] ) ? $instance['menu_id'] : '';
		$context = 'page' === ( $instance['context'] ?? '' );
		$menus   = ABMM_Data::instance()->get_all();
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
				<?php esc_html_e( 'Title:', 'ashbi-mega-menu' ); ?>
			</label>
			<input
				class="widefat"
				id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
				type="text"
				value="<?php echo esc_attr( $title ); ?>"
			/>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'menu_id' ) ); ?>">
				<?php esc_html_e( 'Mega Menu:', 'ashbi-mega-menu' ); ?>
			</label>
			<select
				class="widefat"
				id="<?php echo esc_attr( $this->get_field_id( 'menu_id' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'menu_id' ) ); ?>"
			>
				<option value=""><?php esc_html_e( '— Select a menu —', 'ashbi-mega-menu' ); ?></option>
				<?php foreach ( $menus as $id => $menu ) : ?>
					<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $menu_id, $id ); ?>>
						<?php echo esc_html( $menu['title'] ?: $id ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'context' ) ); ?>">
				<input id="<?php echo esc_attr( $this->get_field_id( 'context' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'context' ) ); ?>" type="checkbox" value="page" <?php checked( $context ); ?> />
				<?php esc_html_e( 'Use page product navigation', 'ashbi-mega-menu' ); ?>
			</label>
		</p>
		<?php
	}

	/**
	 * Handles updating settings for the current widget instance.
	 *
	 * @param array $new_instance New settings.
	 * @param array $old_instance Previous settings.
	 * @return array Updated settings.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();

		$instance['title']   = sanitize_text_field( $new_instance['title'] ?? '' );
		$instance['menu_id'] = sanitize_text_field( $new_instance['menu_id'] ?? '' );
		$instance['context'] = 'page' === ( $new_instance['context'] ?? '' ) ? 'page' : '';

		return $instance;
	}
}

/**
 * Register the widget.
 */
function abmm_register_widget() {
	register_widget( 'ABMM_Widget' );
}
add_action( 'widgets_init', 'abmm_register_widget' );
