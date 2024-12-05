<?php

/**
 * Plugin Name: s3q.us Redirect Widget
 * Description: Adds a WordPress admin dashboard widget for creating redirects using Safe Redirect Manager.
 * Version: 1.0
 * Author: Chris Reynolds
 * Author URI: https://chrisreynolds.io
 * License: MIT
 */

namespace s3q\Redirects;

// Prevent direct access to the file
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bootstrap() {
	add_action( 'wp_dashboard_setup', __NAMESPACE__ . '\\add_redirect_dashboard_widget' );
	add_filter( 'srm_max_redirects', __NAMESPACE__ . '\\bump_max_redirects' );
}

function bump_max_redirects() {
	return 9999;
}

function add_redirect_dashboard_widget() {
	wp_add_dashboard_widget(
		'redirect_dashboard_widget', 
		__( 's3q Custom Link', 's3q-redirect-widget' ),
		'render_redirect_dashboard_widget'
	);
}

function render_redirect_dashboard_widget() {
	if ( isset( $_POST['redirect_nonce'] ) && wp_verify_nonce( $_POST['redirect_nonce'], 'add_redirect' ) ) {
		$redirect_from = isset( $_POST['redirect_from'] ) ? sanitize_text_field( $_POST['redirect_from'] ) : '';
		$redirect_to = isset( $_POST['redirect_to'] ) ? esc_url_raw( $_POST['redirect_to'] ) : '';
		$status_code = isset( $_POST['status_code'] ) ? absint( $_POST['status_code'] ) : 302;

		if ( $redirect_from && $redirect_to ) {
			$post_id = wp_insert_post( [
				'post_type' => 'redirect_rule',
				'post_status' => 'publish',
				'meta_input' => [
					'_redirect_rule_from' => $redirect_from,
					'_redirect_rule_to' => $redirect_to,
					'_redirect_rule_status_code' => $status_code,
				],
			] );

			if ( $post_id ) {
				echo '<div class="updated"><p>' . esc_html__( 'Redirect added successfully!', 's3q-redirect-widget' ) . '</p></div>';
			} else {
				echo '<div class="error"><p>' . esc_html__( 'Failed to add redirect.', 's3q-redirect-widget' ) . '</p></div>';
			}
		} else {
			echo '<div class="error"><p>'. esc_html__( 'Both "Redirect From" and "Redirect To" fields are required.', 's3q-redirect-widget' ) . '</p></div>';
		}
	}
?>
	<form method="post" action="">
		<p>
			<label for="redirect_from"><strong><?php esc_html_e( 'Redirect From:', 's3q-redirect-widget' ); ?></strong></label><br>
			<input type="text" id="redirect_from" name="redirect_from" placeholder="/short-url" class="widefat">
		</p>
		<p>
			<label for="redirect_to"><strong><?php esc_html_e( 'Redirect To:', 's3q-redirect-widget' ); ?></strong></label><br>
			<input type="url" id="redirect_to" name="redirect_to" placeholder="https://example.com/target-url" class="widefat">
		</p>
		<p>
			<label for="status_code"><strong><?php esc_html_e( 'HTTP Status Code:', 's3q-redirect-widget' ); ?></strong></label><br>
			<select id="status_code" name="status_code" class="widefat">
				<option value="302"><?php esc_html_e( '302 - Found (Temporary)', 's3q-redirect-widget' ); ?></option>
				<option value="301"><?php esc_html_e( '301 - Moved Permanently', 's3q-redirect-widget' ); ?></option>
			</select>
		</p>
		<?php wp_nonce_field('add_redirect', 'redirect_nonce'); ?>
		<p>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Add Redirect', 's3q-redirect-widget' ); ?></button>
		</p>
	</form>
<?php
}

// Make it so.
bootstrap();
