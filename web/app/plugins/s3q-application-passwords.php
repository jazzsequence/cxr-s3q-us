<?php
/**
 * Plugin Name: s3q.us Application Passwords
 * Description: Handles application passwords on s3q.us
 * Version: 1.0
 * Author: Chris Reynolds
 * Author URI: https://chrisreynolds.io
 * License: MIT
 */

namespace s3q\ApplicationPasswords;

function bootstrap() {
	add_filter('wp_is_application_passwords_available', __NAMESPACE__ . '\\override_application_password_check');
	add_action('cli_init', 'register_application_password_wp_cli_command');
}

function override_application_password_check($available) {
	if (defined('APPLICATION_PASSWORDS_DISABLE_CONFLICT_CHECK') && APPLICATION_PASSWORDS_DISABLE_CONFLICT_CHECK) {
		return true;
	}
	return $available;
}

/**
 * Registers a custom WP-CLI command to create Application Passwords.
 */
function register_application_password_wp_cli_command() {
	WP_CLI::add_command('application-password', function ($args, $assoc_args) {
		$defaults = [
			'username' => '',
			'name'     => '',
		];
		$assoc_args = wp_parse_args($assoc_args, $defaults);

		if (empty($assoc_args['username']) || empty($assoc_args['name'])) {
			WP_CLI::error('You must provide both a username and an application name.');
		}

		$user = get_user_by('login', $assoc_args['username']);
		if (! $user) {
			WP_CLI::error("User '{$assoc_args['username']}' not found.");
		}

		$password_data = WP_Application_Passwords::create_new_application_password($user->ID, [
			'name' => $assoc_args['name'],
		]);

		if (is_wp_error($password_data)) {
			WP_CLI::error($password_data->get_error_message());
		}

		WP_CLI::success("Application Password created for {$assoc_args['username']}: {$password_data[0]}");
	});
}

// Maximum effort.
bootstrap();
	