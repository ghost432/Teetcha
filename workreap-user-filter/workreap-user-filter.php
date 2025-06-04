<?php
/**
 * Plugin Name: Workreap User Filter
 * Plugin URI: https://example.com/workreap-user-filter
 * Description: Adds a filter to the WordPress users list to show verified and unverified users based on the Workreap theme.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: workreap-user-filter
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Add a filter dropdown to the users list table.
 *
 * @param string $which The location of the filter dropdown ('top' or 'bottom').
 */
function wuf_add_user_verification_filter( $which ) {
    // Only add the filter to the top of the table
    if ( 'top' !== $which ) {
        return;
    }

    $selected_filter = isset( $_GET['user_verification_status'] ) ? sanitize_text_field( $_GET['user_verification_status'] ) : '';
    ?>
    <label class="screen-reader-text" for="user_verification_status_filter"><?php esc_html_e( 'Filter by verification status', 'workreap-user-filter' ); ?></label>
    <select name="user_verification_status" id="user_verification_status_filter">
        <option value=""><?php esc_html_e( 'All Users', 'workreap-user-filter' ); ?></option>
        <option value="verified" <?php selected( $selected_filter, 'verified' ); ?>><?php esc_html_e( 'Verified Users', 'workreap-user-filter' ); ?></option>
        <option value="unverified" <?php selected( $selected_filter, 'unverified' ); ?>><?php esc_html_e( 'Unverified Users', 'workreap-user-filter' ); ?></option>
    </select>
    <?php
    submit_button( esc_html__( 'Filter', 'workreap-user-filter' ), 'secondary', 'wuf_filter_submit', false );
}
add_action( 'restrict_manage_users', 'wuf_add_user_verification_filter' );

/**
 * Modify the user query based on the selected verification status.
 *
 * @param WP_User_Query $query The WP_User_Query instance (passed by reference).
 */
function wuf_filter_users_by_verification_status( $query ) {
    if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
        return;
    }

    $screen = get_current_screen();
    if ( ! $screen || 'users' !== $screen->id ) {
        return;
    }

    if ( isset( $_GET['user_verification_status'] ) && ! empty( $_GET['user_verification_status'] ) ) {
        $filter_value = sanitize_text_field( $_GET['user_verification_status'] );
        $meta_query = array(
            'relation' => 'AND',
        );

        // Assuming the meta key is '_workreap_user_verified' and values are 'yes' or 'no'
        // Adjust this if Workreap uses a different meta key or values
        if ( 'verified' === $filter_value ) {
            $meta_query[] = array(
                'key'     => '_workreap_user_verified', // Adjust if necessary
                'value'   => 'yes',                   // Adjust if necessary
                'compare' => '=',
            );
        } elseif ( 'unverified' === $filter_value ) {
            $meta_query[] = array(
                'key'     => '_workreap_user_verified', // Adjust if necessary
                'value'   => 'no',                    // Adjust if necessary
                'compare' => '=',
            );
        }

        if ( count( $meta_query ) > 1 ) { // Ensure we have a condition to add
            $query->set( 'meta_query', $meta_query );
        }
    }
}
add_action( 'pre_get_users', 'wuf_filter_users_by_verification_status' );
