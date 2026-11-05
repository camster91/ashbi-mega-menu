<?php
/**
 * Switch from Easy Mega Menu.
 *
 * @package Ashbi_Mega_Menu
 *
 * @var array        $preview Import preview from ABMM_EMM_Migration::preview().
 * @var string       $notice  Status notice key.
 * @var array|null   $result  Counts from the last action.
 * @var string       $error   Why the last action failed, if it did.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$abmm_source_active = ABMM_EMM_Migration::source_active();
$abmm_installed     = null !== $preview['plugin'];
$abmm_has_menus     = ! empty( $preview['menus'] );
$abmm_codes_left    = $this->shortcode_in_content();
$abmm_blocked       = $abmm_codes_left ? $this->finish_blocked_reason() : '';
$abmm_can_undo      = $this->has_backup();
$abmm_step          = 0;
?>
<div class="wrap abmm-emm-import">
	<h1><?php esc_html_e( 'Switch from Easy Mega Menu', 'ashbi-mega-menu' ); ?></h1>
	<?php if ( $abmm_installed || $abmm_codes_left ) : ?>
		<p><?php esc_html_e( 'Move your menus into Ashbi Mega Menu, then remove Easy Mega Menu. Visitors see the same header the whole time.', 'ashbi-mega-menu' ); ?></p>
	<?php else : ?>
		<div class="notice notice-success inline"><p><?php esc_html_e( 'You have finished switching. Easy Mega Menu is gone and your pages use Ashbi Mega Menu.', 'ashbi-mega-menu' ); ?></p></div>
	<?php endif; ?>

	<?php if ( 'imported' === $notice ) : ?>
		<div class="notice notice-success">
			<p><?php esc_html_e( 'Your menus were copied. Easy Mega Menu was not changed.', 'ashbi-mega-menu' ); ?></p>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=ashbi-mega-menu' ) ); ?>"><?php esc_html_e( 'Open your menus', 'ashbi-mega-menu' ); ?></a></p>
		</div>
	<?php elseif ( 'finished' === $notice && is_array( $result ) ) : ?>
		<div class="notice <?php echo empty( $result['failed'] ) && empty( $result['skipped'] ) ? 'notice-success' : 'notice-warning'; ?>">
			<p><?php echo esc_html( sprintf( /* translators: 1: posts and pages, 2: Elementor layouts, 3: widgets. */ __( 'Done. Pages and posts updated: %1$d. Elementor layouts updated: %2$d. Widget areas updated: %3$d. A backup was saved first.', 'ashbi-mega-menu' ), (int) $result['posts'], (int) $result['elementor'], (int) $result['widgets'] ) ); ?></p>
			<?php if ( ! empty( $result['failed'] ) ) : ?>
				<p><?php echo esc_html( sprintf( /* translators: %d: number of places. */ _n( '%d place could not be updated. Its old code still works.', '%d places could not be updated. Their old codes still work.', (int) $result['failed'], 'ashbi-mega-menu' ), (int) $result['failed'] ) ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $result['skipped'] ) ) : ?>
				<p><?php esc_html_e( 'Some content changed during the switch and was left as it is. Run Finish switching again to check those places.', 'ashbi-mega-menu' ); ?></p>
			<?php endif; ?>
		</div>
	<?php elseif ( 'undone' === $notice && is_array( $result ) ) : ?>
		<div class="notice <?php echo empty( $result['failed'] ) && empty( $result['skipped'] ) ? 'notice-success' : 'notice-warning'; ?>">
			<p><?php echo esc_html( sprintf( /* translators: %d: number of places. */ _n( 'Undone. %d place was put back.', 'Undone. %d places were put back.', (int) $result['restored'], 'ashbi-mega-menu' ), (int) $result['restored'] ) ); ?></p>
			<?php if ( ! empty( $result['skipped'] ) ) : ?>
				<p><?php echo esc_html( sprintf( /* translators: %d: number of places. */ _n( '%d place was edited after the switch, so it was left as it is.', '%d places were edited after the switch, so they were left as they are.', (int) $result['skipped'], 'ashbi-mega-menu' ), (int) $result['skipped'] ) ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $result['failed'] ) ) : ?>
				<p><?php esc_html_e( 'Some changes could not be undone. The backup was kept so you can retry.', 'ashbi-mega-menu' ); ?></p>
			<?php endif; ?>
		</div>
	<?php elseif ( 'error' === $notice ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( '' !== $error ? $error : __( 'That did not finish. Try again, or restore a backup from History.', 'ashbi-mega-menu' ) ); ?></p></div>
	<?php endif; ?>

	<?php if ( 'imported' === $notice && is_array( $result ) ) : ?>
		<h2><?php esc_html_e( 'Last copy', 'ashbi-mega-menu' ); ?></h2>
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
			<li><?php echo esc_html( sprintf( /* translators: 1: product profiles, 2: branding settings, 3: pages. */ __( 'Product Navigation: %1$d profiles, %2$d branding settings and %3$d page assignments copied.', 'ashbi-mega-menu' ), (int) $result['profiles'], (int) $result['branding'], (int) $result['pages'] ) ); ?></li>
		</ul>
	<?php endif; ?>

	<?php if ( $abmm_has_menus ) : ?>
		<h2><?php echo esc_html( sprintf( /* translators: %d: step number. */ __( '%d. Copy your menus', 'ashbi-mega-menu' ), ++$abmm_step ) ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Copy', 'ashbi-mega-menu' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Menu', 'ashbi-mega-menu' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Top items', 'ashbi-mega-menu' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Links', 'ashbi-mega-menu' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Not copied exactly', 'ashbi-mega-menu' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $preview['menus'] as $abmm_row ) : ?>
					<?php $abmm_replaces_edits = $abmm_row['conflict'] || ! empty( $abmm_row['edited'] ); ?>
					<tr>
						<td>
							<?php /* The checkbox joins the import form below through its form attribute. */ ?>
							<input type="checkbox" form="abmm-emm-import-form" name="menus[]" value="<?php echo esc_attr( $abmm_row['id'] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: menu title. */ __( 'Copy %s', 'ashbi-mega-menu' ), $abmm_row['title'] ) ); ?>" <?php checked( ! $abmm_replaces_edits ); ?> />
						</td>
						<td>
							<strong><?php echo esc_html( $abmm_row['title'] ); ?></strong>
							<?php if ( $abmm_row['conflict'] ) : ?>
								<br /><em><?php esc_html_e( 'An Ashbi menu already uses this ID. It is kept unless you tick this row, which replaces it.', 'ashbi-mega-menu' ); ?></em>
							<?php elseif ( ! empty( $abmm_row['edited'] ) ) : ?>
								<br /><em><?php esc_html_e( 'Your Ashbi copy differs from Easy Mega Menu, usually because you edited it. It is kept unless you tick this row, which replaces your edits.', 'ashbi-mega-menu' ); ?></em>
							<?php elseif ( $abmm_row['exists'] ) : ?>
								<br /><em><?php esc_html_e( 'Copied before, with no differences.', 'ashbi-mega-menu' ); ?></em>
							<?php endif; ?>
						</td>
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
			<?php elseif ( $preview['assets_kept'] ) : ?>
				<?php esc_html_e( 'The styles and icons copied earlier are still in place, so the menus look exactly the same.', 'ashbi-mega-menu' ); ?>
			<?php else : ?>
				<?php esc_html_e( 'Easy Mega Menu\'s files were not found. The bundled styles and generic icons will be used; your own icons may show as a link icon.', 'ashbi-mega-menu' ); ?>
			<?php endif; ?>
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="abmm-emm-import-form">
			<input type="hidden" name="action" value="abmm_emm_import" />
			<input type="hidden" name="abmm_select_menus" value="1" />
			<?php wp_nonce_field( 'abmm_emm_import' ); ?>
			<?php submit_button( __( 'Copy ticked menus', 'ashbi-mega-menu' ), 'primary', 'submit', false ); ?>
			<span class="description"><?php esc_html_e( 'A backup of your Ashbi menus is saved first, and each replaced menu keeps a saved version you can restore from History.', 'ashbi-mega-menu' ); ?></span>
		</form>
	<?php endif; ?>

	<?php if ( $abmm_codes_left || $abmm_can_undo ) : ?>
		<h2><?php echo esc_html( sprintf( /* translators: %d: step number. */ __( '%d. Finish switching', 'ashbi-mega-menu' ), ++$abmm_step ) ); ?></h2>
		<?php if ( $abmm_codes_left ) : ?>
			<p><?php esc_html_e( 'This changes the old menu code on your pages, Elementor templates and widgets to the Ashbi code, for every post status except trash. Visitors see the same header. A backup is saved first, so you can undo it.', 'ashbi-mega-menu' ); ?></p>
			<?php if ( '' !== $abmm_blocked ) : ?>
				<div class="notice notice-warning inline"><p><?php echo esc_html( $abmm_blocked ); ?></p></div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="abmm_emm_finish" />
				<?php wp_nonce_field( 'abmm_emm_finish' ); ?>
				<?php submit_button( __( 'Finish switching', 'ashbi-mega-menu' ), 'primary', 'submit', false, '' !== $abmm_blocked ? array( 'disabled' => 'disabled' ) : array() ); ?>
			</form>
		<?php else : ?>
			<p><?php esc_html_e( 'No page, template or widget uses the old menu code any more.', 'ashbi-mega-menu' ); ?></p>
		<?php endif; ?>
		<?php if ( $abmm_can_undo ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="abmm_emm_undo" />
				<?php wp_nonce_field( 'abmm_emm_undo' ); ?>
				<p>
					<?php submit_button( __( 'Undo the switch', 'ashbi-mega-menu' ), 'secondary', 'submit', false ); ?>
					<span class="description"><?php esc_html_e( 'Puts the old codes back from the backup. Anything edited since the switch is left as it is.', 'ashbi-mega-menu' ); ?></span>
				</p>
			</form>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( $abmm_installed ) : ?>
		<h2><?php echo esc_html( sprintf( /* translators: %d: step number. */ __( '%d. Remove Easy Mega Menu', 'ashbi-mega-menu' ), ++$abmm_step ) ); ?></h2>
		<p>
			<?php if ( $abmm_source_active ) : ?>
				<?php esc_html_e( 'Deactivate Easy Mega Menu on the Plugins screen. Your headers keep working.', 'ashbi-mega-menu' ); ?>
			<?php else : ?>
				<?php esc_html_e( 'Once your site looks right, delete Easy Mega Menu on the Plugins screen. Ashbi keeps its own copy of everything it needs.', 'ashbi-mega-menu' ); ?>
			<?php endif; ?>
			<a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>"><?php esc_html_e( 'Go to Plugins', 'ashbi-mega-menu' ); ?></a>
		</p>
	<?php endif; ?>
</div>
