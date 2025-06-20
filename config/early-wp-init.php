<?php
// config/early-wp-init.php
// This file is loaded by Composer's autoloader very early to set up
// a minimal WordPress environment for packages that need it before
// wp-settings.php is fully loaded (e.g., Freemius SDK).

if ( ! defined( 'ABSPATH' ) ) {
    // Determine project root. Assumes this file is in ProjectRoot/config/
    // and Bedrock structure (web/wp/ for WordPress core).
    $project_root = dirname( __DIR__ );
    define( 'ABSPATH', $project_root . '/web/wp/' );
}

if ( ! defined( 'WP_CONTENT_DIR' ) ) {
    // Determine project root.
    $project_root = dirname( __DIR__ );
    define( 'WP_CONTENT_DIR', $project_root . '/web/app/' );
}

// Load plugin.php for apply_filters, do_action, etc., which theme.php might use.
// Check if ABSPATH is defined and the file exists before requiring.
if ( defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-includes/plugin.php' ) ) {
    require_once ABSPATH . 'wp-includes/plugin.php';
}

// Load theme.php to define get_theme_root() if it's not already defined.
if ( ! function_exists( 'get_theme_root' ) ) {
    // Check if ABSPATH is defined and the file exists before requiring.
    if ( defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-includes/theme.php' ) ) {
        require_once ABSPATH . 'wp-includes/theme.php';
    }
}
