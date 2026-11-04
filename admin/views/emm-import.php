<?php
/**
 * Import from Easy Mega Menu.
 *
 * @package Ashbi_Mega_Menu
 *
 * @var array        $preview Import preview from ABMM_EMM_Migration::preview().
 * @var string       $notice  Status notice key.
 * @var array|null       $result  Last import summary.
 * @var string           $error   Why the last import failed, if it did.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$abmm_takeover       = ABMM_EMM_Migration::takeover_enabled();
$abmm_source_active  = ABMM_EMM_Migration::source_active();
$abmm_has_conflicts  = (bool) array_filter( wp_list_pluck( $preview['menus'], 'conflict' ) );
?>
<div class="wrap abmm-emm-import">
	<h1><?php esc_html_e( 'Import from Easy Mega Menu', 'ashbi-mega-menu' ); ?></h1>
	<p><?php esc_html_e( 'Copy your Easy Mega Menu menus into Ashbi Mega Menu. Easy Mega Menu\'s data is only read: nothing in it is changed or deleted, so you can switch back at any time.', 'ashbi-mega-menu' ); ?></p>

	<?php if ( 'imported' === $notice ) : ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Import finished. Easy Mega Menu was not changed.', 'ashbi-mega-menu' ); ?></p></div>
	<?php elseif ( 'error' === $notice ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( '' !== $error ? $error : __( 'The import did not finish. Try again, or restore a snapshot from Tools.', 'ashbi-mega-menu' ) ); ?></p></div>
	<?php elseif ( 'saved' === $notice ) : ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Setting saved.', 'ashbi-mega-menu' ); ?></p></div>
	<?php endif; ?>

	<?php if ( is_array( $result ) ) : ?>
		<h2><?php esc_html_e( 'Last import', 'ashbi-mega-menu' ); ?></h2>
		<ul class="ul-disc">
			<li><?php echo esc_html( sprintf( /* translators: %d: number of menus. */ _n( '%d menu copied.', '%d menus copied.', count( (array) $result['menus'] ), 'ashbi-mega-menu' ), count( (array) $result['menus'] ) ) ); ?></li>
			<?php if ( ! empty( $result['skipped'] ) ) : ?>
				<li><?php echo esc_html( sprintf( /* translators: %s: menu IDs. */ __( 'Kept your existing Ashbi menus: %s', 'ashbi-mega-menu' ), implode( ', ', $result['skipped'] ) ) ); ?></li>
			<?php endif; ?>
			<?php if ( ! empty( $result['kept_profiles'] ) ) : ?>
				<li><?php echo esc_html( sprintf( /* translators: %s: product profile IDs. */ __( 'Kept your existing Ashbi product profiles: %s', 'ashbi-mega-menu' ), implode( ', ', $result['kept_profiles'] ) ) ); ?></li>
			<?php endif; ?>
			<?php if ( ! empty( $result['kept_branding'] ) ) : ?>
				<li><?php echo esc_html( sprintf( /* translators: %s: product profile IDs. */ __( 'Branding not copied, because its profile or menu was not imported or you already have your own: %s', 'ashbi-mega-menu' ), implode( ', ', $result['kept_branding'] ) ) ); ?></li>
			<?php endif; ?>
			<li><?php echo esc_html( $result['assets']['message'] ?? '' ); ?></li>
			<li><?php echo esc_html( sprintf( /* translators: 1: product profiles, 2: branding settings, 3: pages. */ __( 'Product navigation: %1$d profiles, %2$d branding settings and %3$d page assignments copied.', 'ashbi-mega-menu' ), (int) $result['profiles'], (int) $result['branding'], (int) $result['pages'] ) ); ?></li>
		</ul>
	<?php endif; ?>

	<h2><?php esc_html_e( '1. Check what will be copied', 'ashbi-mega-menu' ); ?></h2>
	<?php if ( empty( $preview['menus'] ) ) : ?>
		<p><?php esc_html_e( 'No Easy Mega Menu menus were found on this site.', 'ashbi-mega-menu' ); ?></p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Menu', 'ashbi-mega-menu' ); ?></th>
					<th scope="col"><?php esc_html_e( 'ID (kept)', 'ashbi-mega-menu' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Top items', 'ashbi-mega-menu' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Links', 'ashbi-mega-menu' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Not copied exactly', 'ashbi-mega-menu' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $preview['menus'] as $abmm_row ) : ?>
					<tr>
						<td>
							<strong><?php echo esc_html( $abmm_row['title'] ); ?></strong>
							<?php if ( $abmm_row['conflict'] ) : ?>
								<br /><em><?php esc_html_e( 'An Ashbi menu already uses this ID. It is kept unless you choose to replace it below.', 'ashbi-mega-menu' ); ?></em>
							<?php elseif ( $abmm_row['exists'] ) : ?>
								<br /><em><?php esc_html_e( 'Imported before: it will be updated.', 'ashbi-mega-menu' ); ?></em>
							<?php endif; ?>
						</td>
						<td><code><?php echo esc_html( $abmm_row['id'] ); ?></code></td>
						<td><?php echo (int) $abmm_row['items']; ?></td>
						<td><?php echo (int) $abmm_row['links']; ?></td>
						<td>
							<?php if ( empty( $abmm_row['issues'] ) ) : ?>
								<?php esc_html_e( 'Nothing, everything matches.', 'ashbi-mega-menu' ); ?>
							<?php else : ?>
								<ul class="ul-disc">
									<?php foreach ( $abmm_row['issues'] as $abmm_issue ) : ?>
										<li><?php echo esc_html( $abmm_issue ); ?></li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p>
			<?php echo esc_html( sprintf( /* translators: 1: product profiles, 2: branding settings, 3: pages. */ __( 'Also copied: %1$d product profiles, %2$d product branding settings and %3$d page assignments.', 'ashbi-mega-menu' ), (int) $preview['profiles'], (int) $preview['branding'], (int) $preview['assignments'] ) ); ?>
		</p>
		<p>
			<?php if ( $preview['assets_ready'] ) : ?>
				<?php echo esc_html( sprintf( /* translators: %s: Easy Mega Menu version. */ __( 'Styles and icons will be copied from Easy Mega Menu %s, so the menus look exactly the same.', 'ashbi-mega-menu' ), $preview['plugin']['version'] ) ); ?>
			<?php else : ?>
				<?php esc_html_e( 'Easy Mega Menu\'s files were not found. The bundled classic styles and generic icons will be used; your own icons may show as a link icon.', 'ashbi-mega-menu' ); ?>
			<?php endif; ?>
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="abmm_emm_import" />
			<?php wp_nonce_field( 'abmm_emm_import' ); ?>
			<?php if ( $abmm_has_conflicts ) : ?>
				<p><label><input type="checkbox" name="overwrite" value="1" /> <?php esc_html_e( 'Replace Ashbi menus that use the same ID', 'ashbi-mega-menu' ); ?></label></p>
			<?php endif; ?>
			<?php submit_button( __( 'Import menus', 'ashbi-mega-menu' ), 'primary', 'submit', false ); ?>
			<span class="description"><?php esc_html_e( 'A snapshot of your Ashbi menus is saved first.', 'ashbi-mega-menu' ); ?></span>
		</form>
	<?php endif; ?>

	<h2><?php esc_html_e( '2. Test before you switch', 'ashbi-mega-menu' ); ?></h2>
	<?php $abmm_menus_in_use = $this->menus_in_use( array_column( $preview['menus'] ?? array(), 'id' ) ); ?>
	<p><?php esc_html_e( 'Imported menus use the Classic (Easy Mega Menu) look. Put one on a private draft page with the Ashbi shortcode and compare it with your live header. A plain page is narrower than a full-width header, so dropdowns can open in a different place: compare with the Easy Mega Menu shortcode on a draft with the same layout.', 'ashbi-mega-menu' ); ?></p>
	<?php if ( $abmm_menus_in_use ) : ?>
		<p><?php esc_html_e( 'Your pages use these menus, so test each one:', 'ashbi-mega-menu' ); ?></p>
		<?php foreach ( $abmm_menus_in_use as $abmm_menu_id ) : ?>
			<p><code>[ashbi_mega_menu id="<?php echo esc_html( $abmm_menu_id ); ?>"]</code></p>
		<?php endforeach; ?>
	<?php else : ?>
		<p><code>[ashbi_mega_menu id="<?php echo esc_html( $preview['menus'][0]['id'] ?? 'menu-id' ); ?>"]</code></p>
	<?php endif; ?>

	<h2><?php esc_html_e( '3. Switch', 'ashbi-mega-menu' ); ?></h2>
	<p><?php esc_html_e( 'Turn this on, then deactivate Easy Mega Menu (do not delete it). Your headers keep their [easy_mega_menu] shortcodes and Ashbi answers them. To switch back, reactivate Easy Mega Menu: it takes over again straight away.', 'ashbi-mega-menu' ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="abmm_emm_takeover" />
		<?php wp_nonce_field( 'abmm_emm_takeover' ); ?>
		<p><label><input type="checkbox" name="takeover" value="1" <?php checked( $abmm_takeover ); ?> /> <?php esc_html_e( 'Handle [easy_mega_menu] shortcodes', 'ashbi-mega-menu' ); ?></label></p>
		<p class="description">
			<?php if ( $abmm_source_active ) : ?>
				<?php esc_html_e( 'Easy Mega Menu is active, so it still answers its own shortcodes. Ashbi takes over only after you deactivate it.', 'ashbi-mega-menu' ); ?>
			<?php elseif ( $abmm_takeover ) : ?>
				<?php esc_html_e( 'Ashbi is answering [easy_mega_menu] shortcodes now.', 'ashbi-mega-menu' ); ?>
			<?php else : ?>
				<?php esc_html_e( 'Easy Mega Menu is not active. Turning this on makes your headers show the imported menus.', 'ashbi-mega-menu' ); ?>
			<?php endif; ?>
		</p>
		<?php submit_button( __( 'Save', 'ashbi-mega-menu' ), 'secondary', 'submit', false ); ?>
	</form>
</div>
