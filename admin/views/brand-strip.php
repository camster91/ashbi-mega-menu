<?php
/** Compact product identity for plugin admin screens. */
if ( ! defined( 'ABSPATH' ) ) {
 exit;
}
?>
<div class="abmm-brand-strip">
 <img src="<?php echo esc_url( ABMM_PLUGIN_URL . 'assets/brand/icon.svg' ); ?>" width="32" height="32" alt="" />
 <strong><?php esc_html_e( 'Ashbi Mega Menu', 'ashbi-mega-menu' ); ?></strong>
 <span><?php esc_html_e( 'Visual navigation for WordPress', 'ashbi-mega-menu' ); ?></span>
</div>
