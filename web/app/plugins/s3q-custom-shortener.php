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

function bootstrap()
{
	add_action('admin_menu', __NAMESPACE__ . '\\add_bookmarklet_menu_page');
	add_action('rest_api_init', __NAMESPACE__ . '\\register_redirect_manager_route');
}

// Adds the submenu page under Tools
function add_bookmarklet_menu_page() {
	add_submenu_page(
		'tools.php',                // Parent menu slug
		'Bookmarklet',              // Page title
		'URL Shortener Bookmarklet', // Menu title
		'manage_options',           // Capability required to view
		'shortener-bookmarklet',    // Menu slug
		__NAMESPACE__ . '\\render_bookmarklet_page' // Callback function
	);
}

// Generate a new application password for the user if one doesn't already exist
function get_user_application_password() {
	$user_id = get_current_user_id();
	if (! $user_id) {
		return null;
	}

	$application_passwords = get_user_meta($user_id, '_application_passwords', true);

	// Check if there are existing passwords
	if (! empty($application_passwords)) {
		// If passwords exist, return their metadata, but not the password
		return [
			'exists' => true,
			'password' => null,
		];
	}

	// Generate a new password
	$password_data = \WP_Application_Passwords::create_new_application_password($user_id, [
		'name' => 'URL Shortener',
	]);

	if (is_wp_error($password_data)) {
		return null;
	}

	return [
		'exists' => false,
		'password' => $password_data[0], // Only available during creation
	];
}

function render_bookmarklet_page() {
	$username = wp_get_current_user()->user_login;
	$site_url = home_url();

	// Fetch the user's application password
	$app_password_data = get_user_application_password();

	// Generate the bookmarklet code
	if ($app_password_data && $app_password_data['password']) {
		$app_password = $app_password_data['password'];
		$bookmarklet = "javascript:(function(){const url=prompt('Enter the URL to shorten:');if(!url)return;fetch('{$site_url}/wp-json/redirect-manager/v1/add',{method:'POST',headers:{'Content-Type':'application/json','Authorization':'Basic '+btoa('{$username}:{$app_password}')},body:JSON.stringify({from:'/short-'+Math.random().toString(36).substr(2,6),to:url})}).then(res=>res.json()).then(data=>alert(data.success?'Short URL: {$site_url}'+data.post_id:'Error creating short URL')).catch(err=>console.error(err));})();";
	} else {
		$bookmarklet = esc_html__('An existing application password was found. However, it cannot be displayed for security reasons. If you need a new bookmarklet, delete the old password and refresh this page.', 's3q-shortener');
	}

?>
	<div class="wrap">
		<h1><?php esc_html_e('URL Shortener Bookmarklet', 's3q-shortener'); ?></h1>
		<?php if ($app_password_data && $app_password_data['password']) : ?>
			<p><?php esc_html_e('Copy the following bookmarklet code and add it to your browser\'s bookmarks:', 's3q-shortener'); ?></p>
			<textarea readonly style="width: 100%; height: 150px;"><?php echo esc_html($bookmarklet); ?></textarea>
		<?php else : ?>
			<p><?php esc_html_e('An application password already exists, but it cannot be displayed for security reasons.', 's3q-shortener'); ?></p>
			<ol>
				<li><?php esc_html_e('Delete the existing password in your profile if you wish to generate a new one.', 's3q-shortener'); ?></li>
				<li><?php esc_html_e('Refresh this page after creating a new password.', 's3q-shortener'); ?></li>
			</ol>
		<?php endif; ?>
		<p><?php esc_html_e('Instructions:', 's3q-shortener'); ?></p>
		<ol>
			<li><?php esc_html_e('Copy the code above.', 's3q-shortener'); ?></li>
			<li><?php esc_html_e('Create a new bookmark in your browser.', 's3q-shortener'); ?></li>
			<li><?php esc_html_e('Paste the code into the URL field of the bookmark.', 's3q-shortener'); ?></li>
		</ol>
	</div>
<?php
}

function register_redirect_manager_route()
{
	register_rest_route('redirect-manager/v1', '/add', [
		'methods' => 'POST',
		'callback' => __NAMESPACE__ . '\\add_redirect_via_api',
		'permission_callback' => function () {
			return current_user_can('edit_posts'); // Restrict access to authorized users
		},
		'args' => [
			'from' => [
				'required' => true,
				'type'     => 'string',
			],
			'to' => [
				'required' => true,
				'type'     => 'string',
			],
		],
	]);

	register_rest_route('redirect-manager/v1', '/list', [
		'methods'  => 'GET',
		'callback' => __NAMESPACE__ . '\\list_redirects_via_api',
		'permission_callback' => function () {
			return current_user_can('edit_posts'); // Restrict access
		},
	]);
}

function add_redirect_via_api($request)
{
	$from = sanitize_text_field($request->get_param('from'));
	$to   = esc_url_raw($request->get_param('to'));

	if (empty($from) || empty($to)) {
		return new \WP_Error('invalid_parameters', 'Both "from" and "to" parameters are required.', ['status' => 400]);
	}

	$post_id = wp_insert_post([
		'post_type'   => 'redirect_rule',
		'post_status' => 'publish',
		'meta_input'  => [
			'_redirect_rule_from' => $from,
			'_redirect_rule_to'   => $to,
		],
	]);

	if ($post_id) {
		return rest_ensure_response(['success' => true, 'post_id' => $post_id]);
	} else {
		return new \WP_Error('insert_failed', 'Failed to add redirect.', ['status' => 500]);
	}
}

function list_redirects_via_api()
{
	$query = new \WP_Query([
		'post_type'      => 'redirect_rule',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
	]);

	$redirects = [];

	if ($query->have_posts()) {
		while ($query->have_posts()) {
			$query->the_post();
			$redirects[] = [
				'id'   => get_the_ID(),
				'from' => get_post_meta(get_the_ID(), '_redirect_rule_from', true),
				'to'   => get_post_meta(get_the_ID(), '_redirect_rule_to', true),
			];
		}
		wp_reset_postdata();
	}

	return rest_ensure_response($redirects);
}

// Maximum effort.
bootstrap();
