<?php
/**
 * Plugin Name: Limit logins
 * Description: Block logins from any account that's not me.
 * Version: 1.0
 * Author: Chris Reynolds
 * Author URI: https://chrisreynolds.io
 * License: MIT
 */

namespace s3q\LimitLogins;

/**
<<<<<<< HEAD
 * Initializes the S3Q Limit Logins plugin.
 *
 * This function sets up the necessary hooks and filters to limit login attempts.
=======
 * Do stuff.
>>>>>>> b9c69040e39c048fde40f908d322edc9c3fd8c4f
 */
function bootstrap() {
	add_filter( 'authenticate', __NAMESPACE__ . '\\restrict_logins', 30 );
}

/**
 * Check if the current user is me.
 * 
 * @return bool true if it me, false if it not me.
 */
function it_me() {
	$current_user = get_current_user_id();

	if ( ! $current_user ) {
		return false;
	}

	if ( ! is_user_logged_in( $current_user ) ) {
		return false;
	}

	if ( ! is_user_admin( $current_user ) ) {
		return false;
	}

	if ( $current_user !== 1 ) {
		return false;
	}

	// It me.
	return true;
}

/**
 * Restrict logins to only allow User ID 1.
 *
 * @param WP_User|WP_Error $user The authenticated user object, or WP_Error on failure.
 * @param string $username Username or email address used for login.
 * @param string $password Password used for login.
 * @return WP_User|WP_Error The authenticated user object, or WP_Error on failure.
 */
function restrict_logins( $user ) {
	// Check if the user is valid.
	if ( ! is_wp_error( $user ) ) {
		// If the user is not user ID 1, deny login.
		if ( ! it_me() ) {
			return new \WP_Error(
				'access_denied',
				__( 'Login is restricted to the site administrator.', 'limit-logins' )
			);
		}
	}

	return $user;
}

// Maximum effort.
bootstrap();
