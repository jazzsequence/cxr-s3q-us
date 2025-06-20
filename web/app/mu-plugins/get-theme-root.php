<?php
/**
 * Load autoloader
 */

if ( file_exists( ABSPATH . '/vendor/autoload.php' ) ) {
	require_once dirname( __DIR__ ) . '/vendor/autoload.php';
} else {
	wp_die( 'Autoloader not found' );
}
