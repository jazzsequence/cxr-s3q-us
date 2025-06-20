<?php
/**
 * Require theme.php if it's not already loaded to avoid get_theme_root not defined error.
 */

add_action( 'muplugins_loaded', function () {
	if ( ! function_exists( 'get_theme_root' ) ) {
		require_once WPINC . '/theme.php';
	}
} );
