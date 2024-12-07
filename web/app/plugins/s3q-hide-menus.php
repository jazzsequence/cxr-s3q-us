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
	// Remove top-level menu items
	remove_menu_page('edit.php');                 // Posts
	remove_menu_page('edit-comments.php');        // Comments
	remove_menu_page('edit.php?post_type=page');  // Pages
	remove_menu_page('plugins.php');             // Plugins
	remove_menu_page('users.php');               // Users
	remove_menu_page('options-general.php');     // Settings

	// Re-add them under Tools
	add_submenu_page(
		'tools.php',
		'Posts',
		'Posts',
		'edit_posts',
		'edit.php'
	);

	add_submenu_page(
		'tools.php',
		'Comments',
		'Comments',
		'moderate_comments',
		'edit-comments.php'
	);

	add_submenu_page(
		'tools.php',
		'Pages',
		'Pages',
		'edit_pages',
		'edit.php?post_type=page'
	);

	add_submenu_page(
		'tools.php',
		'Plugins',
		'Plugins',
		'activate_plugins',
		'plugins.php'
	);

	add_submenu_page(
		'tools.php',
		'Users',
		'Users',
		'list_users',
		'users.php'
	);

	add_submenu_page(
		'tools.php',
		'Settings',
		'Settings',
		'manage_options',
		'options-general.php'
	);
}

function bootstrap() {
	add_action('admin_menu', __NAMESPACE__ . '\\custom_reorganize_admin_menu', 999); // Priority ensures changes are made last.
}

// Make it so.
bootstrap();
