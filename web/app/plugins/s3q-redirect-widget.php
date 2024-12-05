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
	add_action( 'wp_dashboard_setup', __NAMESPACE__ . '\\add_redirect_list_dashboard_widget' );
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\redirect_list_widget_styles' );
	add_action( 'wp_ajax_add_favorite', __NAMESPACE__ . '\\add_favorite_redirect' );
	add_action( 'wp_ajax_remove_favorite', __NAMESPACE__ . '\\remove_favorite_redirect' );
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_redirect_widget_script' );
	add_filter( 'srm_max_redirects', __NAMESPACE__ . '\\bump_max_redirects' );
	add_filter( 'post_type_supports', __NAMESPACE__ . '\\enable_sticky_support_for_redirect_rule', 10, 2 );
	add_filter( 'post_row_actions', __NAMESPACE__ . '\\add_favorite_action_link', 10, 2 );
	
}

function bump_max_redirects() {
	return 9999;
}

function enable_sticky_support_for_redirect_rule( $supports, $post_type ) {
    if ( 'redirect_rule' === $post_type ) {
        $supports[] = 'sticky';
    }
    return $supports;
}

function add_redirect_dashboard_widget() {
	wp_add_dashboard_widget(
		'redirect_dashboard_widget', 
		__( 's3q Custom Link', 's3q-redirect-widget' ),
		__NAMESPACE__ . '\\render_redirect_dashboard_widget'
	);
}

function add_redirect_list_dashboard_widget() {
	wp_add_dashboard_widget(
		'redirect_list_dashboard_widget',
        'Recent Redirects',
        __NAMESPACE__ . '\\render_redirect_list_dashboard_widget'
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

function render_redirect_item( $post_id ) {
    $redirect_from = get_post_meta( $post_id, '_redirect_rule_from', true );
    $redirect_to = get_post_meta( $post_id, '_redirect_rule_to', true );
    $is_sticky = is_sticky( $post_id );

    $full_url = home_url( $redirect_from );
    $toggle_action = $is_sticky ? 'remove_favorite' : 'add_favorite';
    $star_class = $is_sticky ? 'dashicons-star-filled' : 'dashicons-star-empty';

    echo '<div class="redirect-item">';
    echo '<span class="favorite-toggle dashicons ' . esc_attr( $star_class ) . '" data-post-id="' . esc_attr( $post_id ) . '" data-action="' . esc_attr( $toggle_action ) . '"></span>';
    echo '<div>';
    echo '<strong>' . esc_html__( 'Short URL', 's3q-redirect-widget' ) . ':</strong>';
    echo '<input type="text" readonly value="' . esc_attr( $full_url ) . '">';
    echo '<strong>' . esc_html__( 'Redirects To', 's3q-redirect-widget' ) . ':</strong> <a href="' . esc_url( $redirect_to ) . '" target="_blank">' . esc_html( $redirect_to ) . '</a>';
    echo '</div>';
    echo '</div>';
}

function render_redirect_list_dashboard_widget() {
    // Number of redirects to display per page
    $redirects_per_page = 10;

    // Current page
    $current_page = isset( $_GET['redirect_page'] ) ? absint( $_GET['redirect_page'] ) : 1;

    // Get sticky redirects
    $sticky_redirects = get_option( 'sticky_posts', [] );

    // Display sticky (favorited) redirects
    if ( $sticky_redirects ) {
        $sticky_query_args = [
            'post_type' => 'redirect_rule',
            'post__in' => $sticky_redirects,
            'posts_per_page' => -1,
            'orderby' => 'post__in',
            'post_status' => 'publish',
        ];

        $sticky_query = new \WP_Query( $sticky_query_args );

        if ( $sticky_query->have_posts() ) {
            while ( $sticky_query->have_posts() ) {
                $sticky_query->the_post();
                render_redirect_item( get_the_ID() );
            }
            wp_reset_postdata();
        }
    }

    // Query non-sticky redirects
    $query_args = [
        'post_type' => 'redirect_rule',
        'post__not_in' => $sticky_redirects,
        'posts_per_page' => $redirects_per_page,
        'paged' => $current_page,
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'DESC',
    ];

    $redirect_query = new \WP_Query( $query_args );

    if ( $redirect_query->have_posts() ) {
        echo '<div class="redirect-list-widget">';

        while ( $redirect_query->have_posts() ) {
            $redirect_query->the_post();
            render_redirect_item( get_the_ID() );
        }

        echo '</div>';

        // Pagination links
        $total_pages = $redirect_query->max_num_pages;
        if ( $total_pages > 1 ) {
            echo '<div class="pagination">';
            for ( $i = 1; $i <= $total_pages; $i++ ) {
                $class = ( $i === $current_page ) ? 'current' : '';
                $link  = add_query_arg( 'redirect_page', $i );
                echo '<a href="' . esc_url( $link ) . '" class="' . esc_attr( $class ) . '" style="margin-right: 5px;">' . esc_html( $i ) . '</a>';
            }
            echo '</div>';
        }

        wp_reset_postdata();
    } else {
        echo '<p>' . esc_html__( 'No redirects found.', 's3q-redirect-widget' ) . '</p>';
    }
}

function redirect_list_widget_styles() {
    wp_add_inline_style(
        'dashboard',
        '.redirect-list-widget input { cursor: pointer; }
         .pagination a { text-decoration: none; padding: 3px 8px; background: #0073aa; color: #fff; border-radius: 3px; }
         .pagination a.current { background: #333; }
    	.favorite-toggle { font-size: 20px; color: #ffcc00; cursor: pointer; }
     	.favorite-toggle:hover { color: #ff9900; }
		.redirect-list-widget input:focus, .favorited-redirects input:focus { border-color: #0073aa; box-shadow: 0 0 3px #0073aa; }
		#redirect_list_dashboard_widget { padding-right: 20px; }
		.redirect-item input { width: 100%; padding: 5px; }
		.redirect-item {
			display: grid;
			grid-template-columns: auto 1fr;
			align-items: center;
			gap: 10px; /* Adjust spacing between the star and input */
			margin-bottom: 1em;
		}'
    );
}

function add_favorite_action_link( $actions, $post ) {
    if ( 'redirect_rule' === $post->post_type ) {
        $is_sticky = is_sticky( $post->ID );

        $actions['favorite'] = $is_sticky
            ? '<a href="' . esc_url( get_favorite_toggle_url( $post->ID, false ) ) . '">Unfavorite</a>'
            : '<a href="' . esc_url( get_favorite_toggle_url( $post->ID, true ) ) . '">Favorite</a>';
    }

    return $actions;
}

function get_favorite_toggle_url( $post_id, $make_sticky ) {
    $action = $make_sticky ? 'add_favorite' : 'remove_favorite';
    return add_query_arg(
        [
            'post_id' => $post_id,
            'action'  => $action,
            '_wpnonce' => wp_create_nonce( 'favorite_action' ),
        ],
        admin_url( 'admin-post.php' )
    );
}

function add_favorite_redirect() {
    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'favorite_action' ) ) {
        wp_send_json_error( [ 'message' => 'Invalid nonce.' ] );
    }

    $post_id = absint( $_POST['post_id'] );
    if ( current_user_can( 'edit_post', $post_id ) ) {
        stick_post( $post_id );
        wp_send_json_success( [ 'message' => 'Post favorited.' ] );
    } else {
        wp_send_json_error( [ 'message' => 'Permission denied.' ] );
    }
}

function remove_favorite_redirect() {
    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'favorite_action' ) ) {
        wp_send_json_error( [ 'message' => 'Invalid nonce.' ] );
    }

    $post_id = absint( $_POST['post_id'] );
    if ( current_user_can( 'edit_post', $post_id ) ) {
        unstick_post( $post_id );
        wp_send_json_success( [ 'message' => 'Post unfavorited.' ] );
    } else {
        wp_send_json_error( [ 'message' => 'Permission denied.' ] );
    }
}

function enqueue_redirect_widget_script() {
    // Check if we're on the dashboard
    $current_screen = get_current_screen();
    if ( $current_screen && 'dashboard' === $current_screen->base ) {
        // Register a placeholder script to attach inline code to
        wp_register_script( 'redirect-widget-inline', '', [], false, true );

        // Localize script variables
        wp_localize_script(
            'redirect-widget-inline',
            's3qRedirectWidget',
            [
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'favorite_action' ),
            ]
        );

        // Inline JavaScript to select text on click and toggle favorites.
        wp_add_inline_script(
            'redirect-widget-inline',
            "
            document.addEventListener('DOMContentLoaded', function () {
                const textInputs = document.querySelectorAll('.redirect-list-widget input, .favorited-redirects input');
                textInputs.forEach((input) => {
                    input.addEventListener('click', function () {
                        this.select();
                    });
                });

                const favoriteToggles = document.querySelectorAll('.favorite-toggle');
                favoriteToggles.forEach((toggle) => {
                    toggle.addEventListener('click', function () {
                        const postId = this.dataset.postId;
                        const action = this.dataset.action;
                        const icon = this;

						fetch(s3qRedirectWidget.ajaxurl, {
							method: 'POST',
							headers: {
								'Content-Type': 'application/x-www-form-urlencoded',
							},
							body: new URLSearchParams({
								action: action,
								post_id: postId,
								_wpnonce: s3qRedirectWidget.nonce
							})
						})
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                if (action === 'add_favorite') {
                                    icon.classList.remove('dashicons-star-empty');
                                    icon.classList.add('dashicons-star-filled');
                                    icon.dataset.action = 'remove_favorite';
                                } else {
                                    icon.classList.remove('dashicons-star-filled');
                                    icon.classList.add('dashicons-star-empty');
                                    icon.dataset.action = 'add_favorite';
                                }
                            } else {
                                console.error(data.message);
                            }
                        });
                    });
                });
            });
            "
        );

        // Enqueue the script
        wp_enqueue_script( 'redirect-widget-inline' );
    }
}

// Make it so.
bootstrap();
