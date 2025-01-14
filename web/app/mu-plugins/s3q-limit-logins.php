<?php
/**
 * Plugin Name: Limit Logins
 * Description: Block logins from any account that's not User ID 1.
 * Version: 1.1
 * Author: Chris Reynolds
 * Author URI: https://chrisreynolds.io
 * License: MIT
 */

namespace s3q\LimitLogins;

/**
 * Initializes the S3Q Limit Logins plugin.
 */
function bootstrap() {
	add_filter( 'authenticate', __NAMESPACE__ . '\\restrict_logins', 30 );
	add_action( 'wp_logout', __NAMESPACE__ . '\\handle_logout' );
}

/**
 * Ensure clean logout handling.
 */
function handle_logout() {
	wp_destroy_current_session();
	wp_clear_auth_cookie();
	// Redirect to home page after logout.
	wp_redirect( home_url() );
	exit;
}

/**
 * Check if a given user is the allowed user (ID 1).
 *
 * @param int|null $user_id The user ID to check. If null, checks the current logged-in user.
 * @return bool True if the user is User ID 1, false otherwise.
 */
function it_me( $user_id = null ) {
	// Use current logged-in user if no ID is passed.
	if ( is_null( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	// Return true if the user ID is 1 (admin user).
	return (int) $user_id === 1;
}

/**
 * Restrict logins to only allow User ID 1.
 *
 * @param WP_User|WP_Error $user The authenticated user object, or WP_Error on failure.
 * @return WP_User|WP_Error The authenticated user object, or WP_Error on failure.
 */
function restrict_logins( $user ) {
	// If login failed for other reasons, let the error pass through.
	if ( is_wp_error( $user ) ) {
		return $user;
	}

	// Check if the authenticated user is User ID 1.
	if ( ! it_me( $user->ID ) ) {
		return new \WP_Error(
			'access_denied',
			__( 'Login is restricted to the site administrator.', 'limit-logins' )
		);
	}

	// Allow login.
	return $user;
}

// Maximum effort.
bootstrap();
