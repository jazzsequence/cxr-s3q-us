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

function bootstrap() {
	add_filter( 'authenticate', __NAMESPACE__ . '\\restrict_logins', 30, 3 );
}

/**
 * Restrict logins to only allow User ID 1.
 *
 * @param WP_User|WP_Error $user The authenticated user object, or WP_Error on failure.
 * @param string $username Username or email address used for login.
 * @param string $password Password used for login.
 * @return WP_User|WP_Error The authenticated user object, or WP_Error on failure.
 */
function restrict_logins( $user, $username, $password ) {
    // Check if the user is valid.
    if ( ! is_wp_error( $user ) ) {
        // If the user is not user ID 1, deny login.
        if ( $user->ID !== 1 ) {
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
