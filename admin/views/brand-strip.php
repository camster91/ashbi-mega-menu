<?php
/**
 * Shared page header for the plugin's admin screens.
 *
 * Without $abmm_page_header this prints the compact product strip used by the
 * menu editor. The dashboard and the product pages pass $abmm_page_header to
 * get one header pattern: a 40px logo, the page title, a one-line intro and
 * right-aligned actions, then (when product pages are on) a segmented
 * navigation between Menus, Products, Branding and Coverage.
 *
 * Expected keys of $abmm_page_header:
 * - title   (string) Page title.
 * - intro   (string) One-line description.
 * - current (string) Current section: menus, products, branding or coverage.
 * - tabs    (bool)   Whether to show the section navigation. Default true.
 * - actions (array)  Optional actions: label, url (a link) or class (a button),
 *                    primary (bool), icon (dashicon slug).
 *
 * @package Ashbi_Mega_Menu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $abmm_page_header ) || ! is_array( $abmm_page_header ) ) :
	?>
<div class="abmm-brand-strip">
	<img src="<?php echo esc_url( ABMM_PLUGIN_URL . 'assets/brand/icon.svg' ); ?>" width="32" height="32" alt="" />
	<strong><?php esc_html_e( 'Ashbi Mega Menu', 'ashbi-mega-menu' ); ?></strong>
</div>
	<?php
	return;
endif;

$abmm_header_tabs    = array(
	'menus'    => array(
		'label' => __( 'Menus', 'ashbi-mega-menu' ),
		'icon'  => 'menu-alt3',
		'url'   => admin_url( 'admin.php?page=ashbi-mega-menu' ),
	),
	'products' => array(
		'label' => __( 'Products', 'ashbi-mega-menu' ),
		'icon'  => 'screenoptions',
		'url'   => admin_url( 'admin.php?page=ashbi-mega-menu-products' ),
	),
	'branding' => array(
		'label' => __( 'Branding', 'ashbi-mega-menu' ),
		'icon'  => 'format-image',
		'url'   => admin_url( 'admin.php?page=ashbi-mega-menu-product-branding' ),
	),
	'coverage' => array(
		'label' => __( 'Coverage', 'ashbi-mega-menu' ),
		'icon'  => 'visibility',
		'url'   => admin_url( 'admin.php?page=ashbi-mega-menu-coverage' ),
	),
);
$abmm_header_current = (string) ( $abmm_page_header['current'] ?? '' );
$abmm_header_actions = is_array( $abmm_page_header['actions'] ?? null ) ? $abmm_page_header['actions'] : array();
$abmm_header_tabbed  = ! isset( $abmm_page_header['tabs'] ) || ! empty( $abmm_page_header['tabs'] );
?>
<header class="abmm-dash-header abmm-page-header<?php echo $abmm_header_tabbed ? ' has-tabs' : ''; ?>">
	<div class="abmm-page-header__top">
		<div class="abmm-logo-title">
			<img src="<?php echo esc_url( ABMM_PLUGIN_URL . 'assets/brand/icon.svg' ); ?>" width="40" height="40" alt="" />
			<div class="abmm-page-header__text">
				<h1><?php echo esc_html( (string) ( $abmm_page_header['title'] ?? '' ) ); ?></h1>
				<?php if ( ! empty( $abmm_page_header['intro'] ) ) : ?>
					<p class="abmm-admin-intro"><?php echo esc_html( (string) $abmm_page_header['intro'] ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php if ( $abmm_header_actions ) : ?>
			<div class="abmm-admin-header__actions">
				<?php foreach ( $abmm_header_actions as $abmm_header_action ) : ?>
					<?php
					$abmm_action_classes = array( 'button' );
					if ( ! empty( $abmm_header_action['primary'] ) ) {
						$abmm_action_classes[] = 'button-primary';
					}
					foreach ( preg_split( '/\s+/', (string) ( $abmm_header_action['class'] ?? '' ) ) as $abmm_action_extra ) {
						if ( '' !== $abmm_action_extra ) {
							$abmm_action_classes[] = sanitize_html_class( $abmm_action_extra );
						}
					}
					$abmm_action_icon  = (string) ( $abmm_header_action['icon'] ?? '' );
					$abmm_action_label = (string) ( $abmm_header_action['label'] ?? '' );
					?>
					<?php if ( ! empty( $abmm_header_action['url'] ) ) : ?>
						<a class="<?php echo esc_attr( implode( ' ', $abmm_action_classes ) ); ?>" href="<?php echo esc_url( (string) $abmm_header_action['url'] ); ?>">
							<?php if ( '' !== $abmm_action_icon ) : ?>
								<span class="dashicons dashicons-<?php echo esc_attr( sanitize_html_class( $abmm_action_icon ) ); ?>" aria-hidden="true"></span>
							<?php endif; ?>
							<?php echo esc_html( $abmm_action_label ); ?>
						</a>
					<?php else : ?>
						<button type="button" class="<?php echo esc_attr( implode( ' ', $abmm_action_classes ) ); ?>">
							<?php if ( '' !== $abmm_action_icon ) : ?>
								<span class="dashicons dashicons-<?php echo esc_attr( sanitize_html_class( $abmm_action_icon ) ); ?>" aria-hidden="true"></span>
							<?php endif; ?>
							<?php echo esc_html( $abmm_action_label ); ?>
						</button>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php if ( $abmm_header_tabbed ) : ?>
		<nav class="abmm-page-tabs" aria-label="<?php esc_attr_e( 'Ashbi Mega Menu sections', 'ashbi-mega-menu' ); ?>">
			<?php foreach ( $abmm_header_tabs as $abmm_tab_key => $abmm_tab ) : ?>
				<a href="<?php echo esc_url( $abmm_tab['url'] ); ?>"<?php echo $abmm_tab_key === $abmm_header_current ? ' class="is-current" aria-current="page"' : ''; ?>><span class="dashicons dashicons-<?php echo esc_attr( $abmm_tab['icon'] ); ?>" aria-hidden="true"></span><?php echo esc_html( $abmm_tab['label'] ); ?></a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>
</header>
<hr class="wp-header-end" />
