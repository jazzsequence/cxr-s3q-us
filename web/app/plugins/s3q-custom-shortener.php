<?php
/**
 * Plugin Name: s3q.us Custom Shortener
 * Description: Allow links to be shortened from off of s3q.us via bookmarklet.
 * Version: 1.0
 * Author: Chris Reynolds
 * Author URI: https://chrisreynolds.io
 * License: MIT
 */

namespace s3q\Shortener;

use WP_Error;

function bootstrap() {
	add_action('admin_menu', __NAMESPACE__ . '\\add_bookmarklet_menu_page');
	add_action('rest_api_init', __NAMESPACE__ . '\\register_redirect_manager_route');
	add_action('init', __NAMESPACE__ . '\\add_rewrite_rules');
	add_action('template_redirect', __NAMESPACE__ . '\\handle_public_shorten_url');
}

// Adds the submenu page under Tools
function add_bookmarklet_menu_page() {
	add_submenu_page(
		'tools.php',
		__( 'Bookmarklet', 's3q-shortener' ),
		__( 'URL Shortener Bookmarklet', 's3q-shortener' ),
		'manage_options',
		'shortener-bookmarklet',
		__NAMESPACE__ . '\\render_bookmarklet_page'
	);
}

// Renders the bookmarklet page content
function render_bookmarklet_page() {
	$site_url = home_url();
	$api_key = pantheon_get_secret('bookmarklet_api');
	$public_endpoint = "{$site_url}/shorten-url";

	if ($api_key) {
		// Generate the bookmarklet code
		$bookmarklet = "javascript:(function(){const to=prompt('Enter the URL to shorten:');if(!to)return;let from=prompt('Enter your custom short URL (e.g., /my-short-url):');if(!from)return;if(!from.startsWith('/'))from='/'+from;window.open('{$public_endpoint}?to='+encodeURIComponent(to)+'&from='+encodeURIComponent(from),'_blank','width=400,height=300,toolbar=no,menubar=no,scrollbars=no,resizable=no,location=no,status=no');})();";
	} else {
		$bookmarklet = esc_html__('API key not configured. Please set it up in Pantheon Secrets.', 's3q-shortener');
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e('URL Shortener Bookmarklet', 's3q-shortener'); ?></h1>
		<?php if ($api_key) : ?>
			<p><?php esc_html_e('Copy the following bookmarklet code and add it to your browser\'s bookmarks:', 's3q-shortener'); ?></p>
			<textarea readonly style="width: 100%; height: 150px;"><?php echo esc_html($bookmarklet); ?></textarea>
		<?php else : ?>
			<p><?php esc_html_e('API key not configured. Please set it up in Pantheon Secrets.', 's3q-shortener'); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

// Registers the REST API route
function register_redirect_manager_route() {
	register_rest_route('redirect-manager/v1', '/add', [
		'methods' => 'POST',
		'callback' => __NAMESPACE__ . '\\add_redirect_via_api',
		'permission_callback' => __NAMESPACE__ . '\\validate_api_key',
		'args' => [
			'from' => ['required' => true, 'type' => 'string'],
			'to' => ['required' => true, 'type' => 'string'],
		],
	]);
}

// Validates the API key in the request
function validate_api_key($request) {
	$api_key = pantheon_get_secret('bookmarklet_api');
	$provided_key = $request->get_header('X-API-Key');

	if (!$api_key || $api_key !== $provided_key) {
		return new WP_Error(
			'rest_forbidden',
			__('Invalid API key.', 's3q-shortener'),
			['status' => 401]
		);
	}

	return true;
}

// Handles the redirect creation via the REST API
function add_redirect_via_api($request) {
	$from = sanitize_text_field($request->get_param('from'));
	$to = esc_url_raw($request->get_param('to'));

	if (empty($from) || empty($to)) {
		return new WP_Error(
			'invalid_parameters',
			__('Both "to" and "from" are required.', 's3q-shortener'),
			['status' => 400]
		);
	}

	$post_id = wp_insert_post([
		'post_type' => 'redirect_rule',
		'post_status' => 'publish',
		'meta_input' => [
			'_redirect_rule_from' => $from,
			'_redirect_rule_to' => $to,
		],
	]);

	if ($post_id) {
		return rest_ensure_response([
			'success' => true,
			'post_id' => $post_id,
			'from' => $from,
		]);
	}

	return new WP_Error(
		'insert_failed',
		__('Failed to create redirect.', 's3q-shortener'),
		['status' => 500]
	);
}

// Adds rewrite rules for the public page
function add_rewrite_rules() {
	add_rewrite_rule('^shorten-url$', 'index.php?shorten_url=1', 'top');
	add_rewrite_tag('%shorten_url%', '1');
}

// Handles the public shortening page
function handle_public_shorten_url() {
	if (get_query_var('shorten_url') === '1') {
		$to = esc_url_raw($_GET['to'] ?? '');
		$from = sanitize_text_field($_GET['from'] ?? '');

		if (empty($to) || empty($from)) {
			wp_die( __('Invalid parameters. Both "to" and "from" are required.', 's3q-shortener' ) );
		}

		$api_key = pantheon_get_secret('bookmarklet_api');
		$response = wp_remote_post(home_url('/wp-json/redirect-manager/v1/add'), [
			'headers' => [
				'Content-Type' => 'application/json',
				'X-API-Key' => $api_key,
			],
			'body' => json_encode(['to' => $to, 'from' => $from]),
		]);

		if (is_wp_error($response)) {
			wp_die( __( 'Error creating short URL: ', 's3q-shortener' ) . $response->get_error_message());
		}

		$body = wp_remote_retrieve_body($response);
		$result = json_decode($body, true);

		if (!empty($result['success'])) {
			echo wp_kses_post( sprintf( 
				__( 'Short URL created successfully! <a href="%1$s">%2$s</a>', 's3q-shortener' ), 
				$result['from'],
				$result['from'] 
			) );
		} else {
			echo esc_html__( 'Error: ', 's3q-shortener' ) . ( $result['message'] ?? esc_html__( 'Unknown error', 's3q-shortener' ) );
		}

		exit;
	}
}

// Maximum effort.
bootstrap();
