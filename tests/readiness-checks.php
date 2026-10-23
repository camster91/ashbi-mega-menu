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

echo "Readiness flags plain-text links and long labels.\n";
