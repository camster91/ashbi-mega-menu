<?php
/**
 * Checklist catches links typed as plain text and labels too long for a menu bar.
 */
ob_start();
require __DIR__ . '/menu-save-cache.php';
ob_end_clean();

$checker = new ABMM_Test_Data();
foreach ( array( 'http://about', 'http://not%20a%20url%20at%20all', 'https://', 'http://contact-page/' ) as $url ) {
	check( $checker->looks_like_broken_url( $url ), "Flag $url" );
}
foreach ( array( '', '#', '/about/', '?p=1', '//cdn.example.com/x', 'https://example.com', 'http://localhost:8080/', 'mailto:a@example.com', 'http://192.168.1.10/' ) as $url ) {
	check( ! $checker->looks_like_broken_url( $url ), "Allow $url" );
}

$menu = array(
	'title' => 'Main',
	'items' => array(
		array( 'type' => 'link', 'label' => 'About', 'url' => 'http://about' ),
		array( 'type' => 'link', 'label' => str_repeat( 'Long ', 10 ), 'url' => 'https://example.com/long' ),
		array( 'type' => 'link', 'label' => 'Home', 'url' => 'https://example.com/' ),
	),
);
$messages = implode( "\n", array_column( $checker->readiness( $menu )['issues'], 'message' ) );
check( false !== strpos( $messages, 'Check the link for "About"' ), 'Readiness flags a plain-text link' );
check( false !== strpos( $messages, 'so it fits in the menu bar' ), 'Readiness flags a label too long for the bar' );
$menu['items'] = array( $menu['items'][2] );
check( $checker->readiness( $menu )['ready'], 'A normal menu is ready' );

$menu['brand'] = array( 'show' => true, 'title' => '', 'logo_url' => '', 'logo_dark_url' => '' );
$messages = implode( "\n", array_column( $checker->readiness( $menu )['issues'], 'message' ) );
check( false !== strpos( $messages, 'Add a logo image or site title' ), 'Readiness flags a logo with nothing to show' );
$menu['brand']['logo_url'] = 'http://logo';
$messages = implode( "\n", array_column( $checker->readiness( $menu )['issues'], 'message' ) );
check( false !== strpos( $messages, 'Check the logo image link' ), 'Readiness flags a plain-text logo link' );
$menu['brand']['logo_url'] = 'https://example.com/logo.svg';
check( $checker->readiness( $menu )['ready'], 'A logo with a real image is ready' );

$menu['brand'] = array();
$menu['items'][] = array( 'type' => 'link', 'label' => 'Sample', 'url' => '#abmm-sample' );
$messages = implode( "\n", array_column( $checker->readiness( $menu )['issues'], 'message' ) );
check( false !== strpos( $messages, 'Replace 1 sample link' ), 'Readiness counts template links still to replace' );

// Links that go nowhere are named, in the layout visitors see only.
$menu = array(
	'title' => 'Layouts',
	'items' => array(
		array( 'type' => 'link', 'label' => 'Home', 'url' => '/' ),
		array( 'type' => 'link', 'label' => 'Dead', 'url' => '#' ),
		array(
			'type'       => 'mega',
			'label'      => 'Features',
			'mega_style' => 'features',
			'links'      => array( array( 'label' => 'Shown', 'url' => '/shown' ), array( 'label' => 'Empty', 'url' => '' ) ),
			'categories' => array( array( 'groups' => array( array( 'links' => array( array( 'label' => 'Hidden dead', 'url' => '#' ) ) ) ) ) ),
		),
		array(
			'type'       => 'mega',
			'label'      => 'Only hidden',
			'mega_style' => 'features',
			'links'      => array(),
			'categories' => array( array( 'groups' => array( array( 'links' => array( array( 'label' => 'Real but hidden', 'url' => '/hidden' ) ) ) ) ) ),
		),
	),
);
$messages = implode( "\n", array_column( $checker->readiness( $menu )['issues'], 'message' ) );
check( false !== strpos( $messages, 'Add a link for "Dead"' ), 'Readiness flags a top link pointing at #' );
check( false !== strpos( $messages, 'Add a link for "Empty"' ), 'Readiness flags an empty link in the active layout' );
check( false === strpos( $messages, 'Hidden dead' ), 'Readiness ignores links in the hidden layout' );
check( false !== strpos( $messages, 'real destination to Only hidden' ), 'Links hidden by the layout do not satisfy a mega item' );

// A sample stops being one once its items change, and never becomes one again.
$sample = array( 'title' => 'Sample', 'starter_source' => 'generic', 'items' => array( array( 'id' => 'a', 'label' => 'One', 'type' => 'link', 'url' => '/one' ) ) );
$database_options[ ABMM_OPTION_KEY ]['sample'] = $sample;
$cached_options[ ABMM_OPTION_KEY ]['sample']   = $sample;
$renamed = $sample;
$renamed['title'] = 'Renamed';
check( true === $checker->save_if_revision( 'sample', $renamed, $checker->revision( $checker->get_all()['sample'] ) ), 'A sample saves' );
check( 'generic' === $checker->get_all()['sample']['starter_source'], 'A sample with the same items stays a sample' );
$edited = $renamed;
$edited['items'][0]['label'] = 'Mine';
check( true === $checker->save_if_revision( 'sample', $edited, $checker->revision( $checker->get_all()['sample'] ) ), 'An edited sample saves' );
check( '' === $checker->get_all()['sample']['starter_source'], 'Changing the items ends the sample' );
$stale = $edited;
$stale['title'] = 'Still open in another tab';
check( true === $checker->save_if_revision( 'sample', $stale, $checker->revision( $checker->get_all()['sample'] ) ), 'A later save goes through' );
check( '' === $checker->get_all()['sample']['starter_source'], 'A later save cannot turn it back into a sample' );
unset( $database_options[ ABMM_OPTION_KEY ]['sample'], $cached_options[ ABMM_OPTION_KEY ]['sample'] );

// Saved versions: bounded, deduplicated, restorable and undoable.
$first = array( 'title' => 'First', 'items' => array() );
$database_options[ ABMM_OPTION_KEY ]['versioned'] = $first;
$cached_options[ ABMM_OPTION_KEY ]['versioned']   = $first;
$second = array( 'title' => 'Second', 'items' => array() );
$revision = $checker->revision( $checker->get_all()['versioned'] );
check( true === $checker->save_if_revision( 'versioned', $second, $revision ), 'The test menu saves' );
check( $checker->remember_version( 'versioned', $first ), 'A version is kept' );
check( ! $checker->remember_version( 'versioned', $first ), 'The same version is not kept twice' );
$other = array( 'title' => 'Other', 'items' => array() );
check( $checker->remember_version( 'versioned', $other ), 'A burst keeps the good save before a bad one' );
check( array( 'Other', 'First' ) === array_column( $checker->menu_versions( 'versioned' ), 'title' ), 'The state before the burst stays too' );
$newest = array( 'title' => 'Newest', 'items' => array() );
check( $checker->remember_version( 'versioned', $newest ), 'A later save in the burst is kept' );
check( array( 'Newest', 'First' ) === array_column( $checker->menu_versions( 'versioned' ), 'title' ), 'A burst cannot flush the history: it never holds more than two' );
for ( $i = 0; $i < ABMM_Data::MAX_VERSIONS + 3; $i++ ) {
	$checker->remember_version( 'versioned', array( 'title' => "Forced $i", 'items' => array() ), true );
}
check( ABMM_Data::MAX_VERSIONS === count( $checker->menu_versions( 'versioned' ) ), 'The history is bounded' );
check( ! isset( $checker->menu_versions( 'versioned' )[0]['menu'] ), 'The list leaves out menu data' );
check( ! $checker->remember_version( 'large', array( 'title' => str_repeat( 'x', ABMM_Data::MAX_VERSION_BYTES + 1 ) ), true ), 'An oversized menu is not kept' );
$versions = $checker->menu_versions( 'versioned' );
$now      = $checker->revision( $checker->get_all()['versioned'] );
check( is_wp_error( $checker->restore_version( 'versioned', 'missing', $now ) ), 'An unknown version is refused' );
check( is_wp_error( $checker->restore_version( 'versioned', $versions[0]['id'], 'stale' ) ), 'A stale restore is refused' );
check( 'Second' === $checker->get_all()['versioned']['title'], 'A refused restore changes nothing' );
check( true === $checker->restore_version( 'versioned', $versions[0]['id'], $now ), 'A saved version restores' );
check( $versions[0]['title'] === $checker->get_all()['versioned']['title'], 'The menu is back to that version' );
check( 'Second' === $checker->menu_versions( 'versioned' )[0]['title'], 'The replaced version is kept, so a restore can be undone' );

echo "Readiness flags plain-text links and long labels.\n";
