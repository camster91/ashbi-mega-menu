<?php
/** Import a real classic WordPress menu, then check the draft, the report and that the source is untouched. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit; }
function abmm_nav_assert( $condition, $message ) { if ( ! $condition ) { WP_CLI::error( $message ); } }

$data     = ABMM_Data::instance();
$original = $data->get_all();
$posts    = array();
$menu_id  = wp_create_nav_menu( 'ABMM import QA ' . wp_generate_password( 6, false ) );
abmm_nav_assert( ! is_wp_error( $menu_id ), 'Create a disposable WordPress menu.' );

try {
	$posts['page']  = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'ABMM QA page' ) );
	$posts['draft'] = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'ABMM QA draft' ) );
	$category       = wp_insert_term( 'ABMM QA category ' . wp_generate_password( 4, false ), 'category' );
	abmm_nav_assert( ! is_wp_error( $category ), 'Create a category to link to.' );

	$add = function ( $args ) use ( $menu_id ) {
		$id = wp_update_nav_menu_item( $menu_id, 0, array_merge( array( 'menu-item-status' => 'publish' ), $args ) );
		abmm_nav_assert( ! is_wp_error( $id ) && $id, 'Add a WordPress menu item.' );
		return $id;
	};
	$home     = $add( array( 'menu-item-title' => 'Home', 'menu-item-url' => home_url( '/' ), 'menu-item-type' => 'custom', 'menu-item-target' => '_blank', 'menu-item-classes' => 'menu-home', 'menu-item-xfn' => 'nofollow' ) );
	$products = $add( array( 'menu-item-title' => 'Products', 'menu-item-url' => '#', 'menu-item-type' => 'custom' ) );
	$software = $add( array( 'menu-item-title' => 'Software', 'menu-item-object' => 'page', 'menu-item-object-id' => $posts['page'], 'menu-item-type' => 'post_type', 'menu-item-parent-id' => $products, 'menu-item-description' => 'Apps' ) );
	$add( array( 'menu-item-title' => 'Topic', 'menu-item-object' => 'category', 'menu-item-object-id' => $category['term_id'], 'menu-item-type' => 'taxonomy', 'menu-item-parent-id' => $software ) );
	$deep     = $add( array( 'menu-item-title' => 'Deep', 'menu-item-url' => home_url( '/deep/' ), 'menu-item-type' => 'custom', 'menu-item-parent-id' => $software ) );
	$add( array( 'menu-item-title' => 'Deeper', 'menu-item-url' => home_url( '/deeper/' ), 'menu-item-type' => 'custom', 'menu-item-parent-id' => $deep ) );
	$add( array( 'menu-item-title' => 'Draft', 'menu-item-object' => 'page', 'menu-item-object-id' => $posts['draft'], 'menu-item-type' => 'post_type', 'menu-item-parent-id' => $products ) );
	$company  = $add( array( 'menu-item-title' => 'Company', 'menu-item-url' => home_url( '/company/' ), 'menu-item-type' => 'custom' ) );
	$add( array( 'menu-item-title' => 'About', 'menu-item-url' => home_url( '/about/' ), 'menu-item-type' => 'custom', 'menu-item-parent-id' => $company ) );

	$before = wp_json_encode( wp_get_nav_menu_items( $menu_id ) );
	$result = $data->import_from_nav_menu( $menu_id );
	abmm_nav_assert( ! is_wp_error( $result ), 'A real WordPress menu converts.' );
	$items = $result['menu']['items'];
	abmm_nav_assert( array( 'Home', 'Products', 'Company' ) === array_column( $items, 'label' ), 'Top-level items keep their order.' );
	abmm_nav_assert( '_blank' === $items[0]['target'] && false !== strpos( $items[0]['rel'], 'nofollow' ) && 'menu-home' === $items[0]['class'], 'Target, rel and classes survive.' );
	$software_cat = $items[1]['categories'][0];
	abmm_nav_assert( 'Software' === $software_cat['title'] && 'Apps' === $software_cat['description'], 'Depth-2 children become categories with descriptions.' );
	abmm_nav_assert( get_permalink( $posts['page'] ) === $software_cat['panel_url'], "A page item's permalink becomes the see-all link." );
	$labels = array_column( $software_cat['groups'][0]['links'], 'label' );
	abmm_nav_assert( array( 'Topic', 'Deep', 'Deeper' ) === $labels, 'Category, custom and deeper links are imported in order.' );
	abmm_nav_assert( get_term_link( (int) $category['term_id'], 'category' ) === $software_cat['groups'][0]['links'][0]['url'], 'Category links keep their URL.' );
	abmm_nav_assert( 1 === count( $items[1]['categories'] ), 'The unpublished page is not imported.' );
	abmm_nav_assert( in_array( 'Draft', array_column( $result['report']['skipped'], 'label' ), true ), 'The unpublished page is reported as skipped.' );
	abmm_nav_assert( array( 'Deeper' ) === array_column( $result['report']['flattened'], 'label' ), 'The depth-4 item is reported as flattened.' );
	abmm_nav_assert( ! empty( $items[2]['hide_category_bar'] ), 'A parent with plain children becomes one columns-only category.' );
	abmm_nav_assert( $original === $data->get_all(), 'Preview stores nothing.' );

	$new_id = $data->create_from_import( $result['menu'] );
	abmm_nav_assert( is_string( $new_id ) && $data->get( $new_id ), 'Import stores a new menu.' );
	abmm_nav_assert( count( $original ) + 1 === count( $data->get_all() ), 'Import adds exactly one menu.' );
	abmm_nav_assert( $before === wp_json_encode( wp_get_nav_menu_items( $menu_id ) ), 'The WordPress menu is unchanged.' );
	$locations = get_nav_menu_locations();
	abmm_nav_assert( ! in_array( $new_id, $locations, true ), 'The draft is not assigned to a theme location.' );
	$html = do_shortcode( '[ashbi_mega_menu id="' . $new_id . '"]' );
	abmm_nav_assert( false !== strpos( $html, 'data-abmm-id="' . $new_id . '"' ) && false !== strpos( $html, 'Software' ), 'The imported draft renders with the live renderer.' );

	// A Site Editor navigation with the same shape, in block markup.
	$link = function ( $attrs ) { return '<!-- wp:navigation-link ' . wp_json_encode( $attrs ) . ' /-->'; };
	$posts['navigation'] = wp_insert_post( array(
		'post_type'    => 'wp_navigation',
		'post_status'  => 'publish',
		'post_title'   => 'ABMM QA header',
		'post_content' => '<!-- wp:home-link {"label":"Start"} /-->'
			. '<!-- wp:navigation-submenu {"label":"Products","url":"#","kind":"custom"} -->'
			. $link( array( 'label' => 'Software', 'type' => 'page', 'id' => $posts['page'], 'kind' => 'post-type', 'url' => '/stale/', 'opensInNewTab' => true ) )
			. $link( array( 'label' => 'Draft', 'type' => 'page', 'id' => $posts['draft'], 'kind' => 'post-type' ) )
			. '<!-- /wp:navigation-submenu -->'
			. '<!-- wp:search {"label":"Search"} /-->',
	) );
	abmm_nav_assert( $posts['navigation'] && ! is_wp_error( $posts['navigation'] ), 'Create a disposable Site Editor navigation.' );
	$nav_content = get_post_field( 'post_content', $posts['navigation'] );
	$block = $data->import_from_navigation_post( $posts['navigation'] );
	abmm_nav_assert( ! is_wp_error( $block ), 'A real Site Editor navigation converts.' );
	abmm_nav_assert( array( 'Start', 'Products' ) === array_column( $block['menu']['items'], 'label' ), 'Home link and submenu become top-level items.' );
	$block_links = $block['menu']['items'][1]['categories'][0]['groups'][0]['links'];
	abmm_nav_assert( array( 'Software' ) === array_column( $block_links, 'label' ) && get_permalink( $posts['page'] ) === $block_links[0]['url'] && '_blank' === $block_links[0]['target'], 'Page links follow the current permalink and keep new-tab.' );
	$block_skipped = array_column( $block['report']['skipped'], 'label' );
	abmm_nav_assert( in_array( 'Draft', $block_skipped, true ) && in_array( 'Search block', $block_skipped, true ), 'Unpublished pages and non-link blocks are reported as skipped.' );
	abmm_nav_assert( $nav_content === get_post_field( 'post_content', $posts['navigation'] ), 'The Site Editor navigation is unchanged.' );
} finally {
	wp_delete_nav_menu( $menu_id );
	foreach ( $posts as $post ) { wp_delete_post( $post, true ); }
	if ( ! empty( $category['term_id'] ) ) { wp_delete_term( (int) $category['term_id'], 'category' ); }
	$data->replace_all( $original );
}
WP_CLI::success( 'WordPress menu import: classic and Site Editor conversion, report, draft creation and untouched sources passed.' );
