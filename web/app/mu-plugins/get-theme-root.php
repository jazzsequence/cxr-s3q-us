<?php
/**
 * Require theme.php if it's not already loaded to avoid get_theme_root not defined error.
 */

if ( ! function_exists( 'get_theme_root' ) ) {
	require_once ABSPATH . '/wp-includes/theme.php';
}
