<?php
/**
 * Plugin Name: s3q.us Hide Menus
 * Description: Hides sidebar menus for s3q.us
 * Version: 1.0
 * Author: Chris Reynolds
 * Author URI: https://chrisreynolds.io
 * License: MIT
 */

namespace s3q\Menus;

function custom_reorganize_admin_menu() {
	global $menu, $pagenow;

	// List of menus to reorganize
	$menus_to_move = [
		'edit.php' => [ 'Posts', 'edit_posts', 'edit.php', 'post-new.php', 'post.php' ],
		'edit-comments.php' => [ 'Comments', 'moderate_comments', 'edit-comments.php', 'comment.php' ],
		'edit.php?post_type=page' => [ 'Pages', 'edit_pages', 'edit.php?post_type=page', 'post-new.php?post_type=page', 'post.php' ],
		'plugins.php' => [ 'Plugins', 'activate_plugins', 'plugins.php', 'plugin-install.php', 'plugin-editor.php' ],
		'users.php' => [ 'Users', 'list_users', 'users.php', 'user-edit.php', 'profile.php' ],
		'options-general.php' => [ 'Settings', 'manage_options', 'options-general.php', 'options.php' ],
	];

	foreach ( $menus_to_move as $menu_slug => $menu_details ) {
		// Check if the current page matches the menu being moved
		$is_on_menu_page = in_array( $pagenow, array_slice( $menu_details, 2 ), true );

		if ( ! $is_on_menu_page ) {
			// If not on the menu's page, remove the menu and add it to Tools
			remove_menu_page( $menu_slug );

			if ( $menu_details[0] !== 'Comments' ) {
				add_submenu_page(
					'tools.php',
					$menu_details[0], // Page title
					$menu_details[0], // Menu label
					$menu_details[1], // Capability
					$menu_slug        // Menu slug
				);
			}
		}
	}
}

function bootstrap() {
	// Hook the menu reorganization to admin_menu
	add_action( 'admin_menu', __NAMESPACE__ . '\\custom_reorganize_admin_menu', 999 );
}

// Make it so.
bootstrap();
