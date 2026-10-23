<?php
/**
 * Shared page header for the plugin's admin screens.
 *
 * Without $abmm_page_header this prints the compact product strip used by the
 * menu editor. The product pages pass $abmm_page_header to get the same header
 * as the dashboard (logo, title, intro, actions) plus the section tabs that
 * link Product Navigation, Product Branding and Navigation Coverage.
 *
 * Expected keys of $abmm_page_header:
 * - title   (string) Page title.
 * - intro   (string) One-line description.
 * - current (string) Current section: products, branding or coverage.
 * - actions (array)  Optional buttons: label, url, primary (bool), icon (dashicon slug).
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

$abmm_header_tabs = array(
	'products' => array(
		'label' => __( 'Products', 'ashbi-mega-menu' ),
		'url'   => admin_url( 'admin.php?page=ashbi-mega-menu-products' ),
	),
	'branding' => array(
		'label' => __( 'Branding', 'ashbi-mega-menu' ),
		'url'   => admin_url( 'admin.php?page=ashbi-mega-menu-product-branding' ),
	),
	'coverage' => array(
		'label' => __( 'Coverage', 'ashbi-mega-menu' ),
		'url'   => admin_url( 'admin.php?page=ashbi-mega-menu-coverage' ),
	),
);
$abmm_header_current = (string) ( $abmm_page_header['current'] ?? '' );
$abmm_header_actions = is_array( $abmm_page_header['actions'] ?? null ) ? $abmm_page_header['actions'] : array();
?>
<header class="abmm-dash-header abmm-page-header">
	<div class="abmm-logo-title">
		<img src="<?php echo esc_url( ABMM_PLUGIN_URL . 'assets/brand/icon.svg' ); ?>" width="44" height="44" alt="" />
		<div>
			<p class="abmm-page-header__eyebrow"><a href="<?php echo esc_url( admin_url( 'admin.php?page=ashbi-mega-menu' ) ); ?>"><?php esc_html_e( 'Ashbi Mega Menu', 'ashbi-mega-menu' ); ?></a></p>
			<h1><?php echo esc_html( (string) ( $abmm_page_header['title'] ?? '' ) ); ?></h1>
			<?php if ( ! empty( $abmm_page_header['intro'] ) ) : ?>
				<p class="abmm-admin-intro"><?php echo esc_html( (string) $abmm_page_header['intro'] ); ?></p>
			<?php endif; ?>
		</div>
	</div>
	<?php if ( $abmm_header_actions ) : ?>
		<div class="abmm-admin-header__actions">
			<?php foreach ( $abmm_header_actions as $abmm_header_action ) : ?>
				<a class="button <?php echo ! empty( $abmm_header_action['primary'] ) ? 'button-primary' : ''; ?>" href="<?php echo esc_url( (string) ( $abmm_header_action['url'] ?? '' ) ); ?>">
					<?php if ( ! empty( $abmm_header_action['icon'] ) ) : ?>
						<span class="dashicons dashicons-<?php echo esc_attr( sanitize_html_class( $abmm_header_action['icon'] ) ); ?>" aria-hidden="true"></span>
					<?php endif; ?>
					<?php echo esc_html( (string) ( $abmm_header_action['label'] ?? '' ) ); ?>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</header>
<nav class="abmm-page-tabs" aria-label="<?php esc_attr_e( 'Product pages', 'ashbi-mega-menu' ); ?>">
	<?php foreach ( $abmm_header_tabs as $abmm_tab_key => $abmm_tab ) : ?>
		<a href="<?php echo esc_url( $abmm_tab['url'] ); ?>"<?php echo $abmm_tab_key === $abmm_header_current ? ' class="is-current" aria-current="page"' : ''; ?>><?php echo esc_html( $abmm_tab['label'] ); ?></a>
	<?php endforeach; ?>
</nav>
<hr class="wp-header-end" />
