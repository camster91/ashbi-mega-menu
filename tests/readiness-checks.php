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

echo "Readiness flags plain-text links and long labels.\n";
