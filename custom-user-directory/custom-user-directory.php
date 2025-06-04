<?php
/**
 * Plugin Name: Custom User Directory
 * Plugin URI: https://example.com/custom-user-directory
 * Description: Displays a filterable and paginated table of users with specific details, including Workreap theme data.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: custom-user-directory
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Registers the [user_directory_table] shortcode.
 */
function cud_register_shortcode() {
    add_shortcode( 'user_directory_table', 'cud_render_user_table_shortcode' );
}
add_action( 'init', 'cud_register_shortcode' );

/**
 * Renders the user table for the [user_directory_table] shortcode.
 *
 * @param array $atts Shortcode attributes.
 * @return string HTML output for the user table.
 */
function cud_render_user_table_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'users_per_page' => 20, // Default users per page
    ), $atts, 'user_directory_table' );

    $users_per_page = intval( $atts['users_per_page'] );
    $current_page = get_query_var( 'paged' ) ? intval( get_query_var( 'paged' ) ) : 1;
    // Fallback for paged when not on a static front page or main query
    if ( $current_page === 1 && isset( $_GET['paged'] ) ) {
        $current_page = intval( $_GET['paged'] );
    }
    $current_page = max(1, $current_page);


    // Get search parameters
    $s_address = isset( $_GET['s_address'] ) ? sanitize_text_field( wp_unslash( $_GET['s_address'] ) ) : '';
    $s_city = isset( $_GET['s_city'] ) ? sanitize_text_field( wp_unslash( $_GET['s_city'] ) ) : '';
    $s_postal_code = isset( $_GET['s_postal_code'] ) ? sanitize_text_field( wp_unslash( $_GET['s_postal_code'] ) ) : '';

    $users_data_args = array(
        'number' => $users_per_page,
        'paged'  => $current_page,
        's_address' => $s_address,
        's_city'    => $s_city,
        's_postal_code' => $s_postal_code,
    );

    $users_data_result = cud_get_users_data( $users_data_args );
    $users = $users_data_result['users'];
    $total_users = $users_data_result['total_users'];
    $total_pages = $users_per_page > 0 ? ceil( $total_users / $users_per_page ) : 1;


    $output = '<div class="custom-user-directory-wrap">';
    $output .= '<h2>User Directory Table</h2>';

    // Search/filter form
    $output .= '<form method="get" action="' . esc_url( get_permalink() ) . '" class="cud-filters-form">';
    $output .= '<input type="hidden" name="page_id" value="' . get_the_ID() . '">'; // Useful if shortcode is on a page
    // Or remove page_id and rely on get_permalink() if it's always correct.
    // For some permalink structures, just action="" might be better.

    $output .= '<p>';
    $output .= '<label for="s_address">' . esc_html__( 'Address:', 'custom-user-directory' ) . '</label>';
    $output .= '<input type="text" name="s_address" id="s_address" value="' . esc_attr( $s_address ) . '"> ';

    $output .= '<label for="s_city">' . esc_html__( 'City:', 'custom-user-directory' ) . '</label>';
    $output .= '<input type="text" name="s_city" id="s_city" value="' . esc_attr( $s_city ) . '"> ';

    $output .= '<label for="s_postal_code">' . esc_html__( 'Postal Code:', 'custom-user-directory' ) . '</label>';
    $output .= '<input type="text" name="s_postal_code" id="s_postal_code" value="' . esc_attr( $s_postal_code ) . '"> ';

    $output .= '<input type="submit" value="' . esc_attr__( 'Search', 'custom-user-directory' ) . '" class="button">';
    $output .= '</p>';
    $output .= '</form>';


    if ( ! empty( $users ) ) {
        $output .= '<table class="wp-list-table widefat fixed striped users">';
        $output .= '<thead><tr>';
        $output .= '<th>' . esc_html__( 'First Name', 'custom-user-directory' ) . '</th>';
        $output .= '<th>' . esc_html__( 'Last Name', 'custom-user-directory' ) . '</th>';
        $output .= '<th>' . esc_html__( 'Phone', 'custom-user-directory' ) . '</th>';
        $output .= '<th>' . esc_html__( 'Address', 'custom-user-directory' ) . '</th>';
        $output .= '<th>' . esc_html__( 'City', 'custom-user-directory' ) . '</th>';
        $output .= '<th>' . esc_html__( 'Postal Code', 'custom-user-directory' ) . '</th>';
        $output .= '<th>' . esc_html__( 'Department', 'custom-user-directory' ) . '</th>';
        $output .= '<th>' . esc_html__( 'Registered', 'custom-user-directory' ) . '</th>';
        $output .= '<th>' . esc_html__( 'Verified', 'custom-user-directory' ) . '</th>';
        $output .= '<th>' . esc_html__( 'Skills', 'custom-user-directory' ) . '</th>';
        $output .= '</tr></thead>';

        $output .= '<tbody>';
        foreach ( $users as $user ) {
            $output .= '<tr>';
            $output .= '<td>' . esc_html( $user['first_name'] ) . '</td>';
            $output .= '<td>' . esc_html( $user['last_name'] ) . '</td>';
            $output .= '<td>' . esc_html( $user['phone_number'] ) . '</td>';
            $output .= '<td>' . esc_html( $user['address'] ) . '</td>';
            $output .= '<td>' . esc_html( $user['city'] ) . '</td>';
            $output .= '<td>' . esc_html( $user['postal_code'] ) . '</td>';
            $output .= '<td>' . esc_html( $user['department'] ) . '</td>';
            $output .= '<td>' . esc_html( $user['registration_date'] ) . '</td>';

            $verified_icon = '&#10008;'; // Cross mark (X)
            if ( strtolower( $user['verified_status'] ) === 'yes' ) {
                $verified_icon = '&#10004;'; // Check mark (✓)
            }
            $output .= '<td style="text-align: center;">' . $verified_icon . '</td>';

            $output .= '<td>' . esc_html( $user['skills'] ) . '</td>';
            $output .= '</tr>';
        }
        $output .= '</tbody>';
        $output .= '</table>';

        // Pagination links
        if ( $total_pages > 1 ) {
            $output .= '<div class="cud-pagination">';
            $base_url = get_permalink();
            $pagination_args = array();
            if( !empty($s_address) ) $pagination_args['s_address'] = $s_address;
            if( !empty($s_city) ) $pagination_args['s_city'] = $s_city;
            if( !empty($s_postal_code) ) $pagination_args['s_postal_code'] = $s_postal_code;

            if ( $current_page > 1 ) {
                $prev_page_url = add_query_arg( array_merge( $pagination_args, array('paged' => $current_page - 1) ), $base_url );
                $output .= '<a href="' . esc_url( $prev_page_url ) . '" class="prev page-numbers">&laquo; ' . esc_html__( 'Previous', 'custom-user-directory' ) . '</a>';
            }

            if ( $current_page < $total_pages ) {
                $next_page_url = add_query_arg( array_merge( $pagination_args, array('paged' => $current_page + 1) ), $base_url );
                $output .= '<a href="' . esc_url( $next_page_url ) . '" class="next page-numbers">' . esc_html__( 'Next', 'custom-user-directory' ) . ' &raquo;</a>';
            }
            $output .= '</div>';
        }

    } else {
        $output .= '<p>' . esc_html__( 'No users found matching your criteria.', 'custom-user-directory' ) . '</p>';
    }

    $output .= '</div>'; // close custom-user-directory-wrap

    return $output;
}

/**
 * Retrieves user data for the directory table.
 *
 * @param array $args Arguments for WP_User_Query including pagination and filtering.
 * @return array An array containing 'users' and 'total_users'.
 */
function cud_get_users_data( $args = array() ) {
    $default_args = array(
        'orderby' => 'registered',
        'order'   => 'DESC',
        'number'  => 20, // Default users per page
        'paged'   => 1,  // Default to page 1
        's_address' => '',
        's_city'    => '',
        's_postal_code' => '',
    );
    $query_args = wp_parse_args( $args, $default_args );

    // Meta query for search filters
    $meta_query = array('relation' => 'AND');

    if ( ! empty( $query_args['s_address'] ) ) {
        $meta_query[] = array(
            'key'     => 'address', // Assuming meta key is 'address'
            'value'   => sanitize_text_field( $query_args['s_address'] ),
            'compare' => 'LIKE',
        );
    }
    if ( ! empty( $query_args['s_city'] ) ) {
        $meta_query[] = array(
            'key'     => 'city', // Assuming meta key is 'city'
            'value'   => sanitize_text_field( $query_args['s_city'] ),
            'compare' => 'LIKE',
        );
    }
    if ( ! empty( $query_args['s_postal_code'] ) ) {
        // For French postal codes, a more specific validation/comparison might be needed.
        // Using LIKE for broader matching here.
        $meta_query[] = array(
            'key'     => 'postal_code', // Assuming meta key is 'postal_code'
            'value'   => sanitize_text_field( $query_args['s_postal_code'] ),
            'compare' => 'LIKE',
        );
    }

    if ( count( $meta_query ) > 1 ) { // More than just 'relation' => 'AND'
        $query_args['meta_query'] = $meta_query;
    }

    // Remove our custom search args before passing to WP_User_Query
    unset( $query_args['s_address'], $query_args['s_city'], $query_args['s_postal_code'] );


    // Ensure 'number' (users per page) is set for offset calculation
    if ( !isset($query_args['number']) ) {
        $query_args['number'] = $default_args['number'];
    }
    // Calculate offset if paged is used
    if ( $query_args['paged'] > 1 ) {
        $query_args['offset'] = ( $query_args['paged'] - 1 ) * $query_args['number'];
    }


    $user_query = new WP_User_Query( $query_args );
    $users_data = array();

    if ( ! empty( $user_query->get_results() ) ) {
        foreach ( $user_query->get_results() as $user ) {
            $user_info = array(
                'id'                => $user->ID,
                'username'          => $user->user_login,
                'email'             => $user->user_email,
                'registration_date' => date_i18n( get_option( 'date_format' ), strtotime( $user->user_registered ) ),
                'first_name'        => get_user_meta( $user->ID, 'first_name', true ),
                'last_name'         => get_user_meta( $user->ID, 'last_name', true ),
                'phone_number'      => get_user_meta( $user->ID, 'phone_number', true ),
                'address'           => get_user_meta( $user->ID, 'address', true ),
                'city'              => get_user_meta( $user->ID, 'city', true ),
                'postal_code'       => get_user_meta( $user->ID, 'postal_code', true ),
                'department'        => get_user_meta( $user->ID, 'department', true ),
                'verified_status'   => get_user_meta( $user->ID, '_workreap_user_verified', true ),
                'skills'            => '',
            );

            $user_skills_terms = wp_get_object_terms( $user->ID, 'workreap_languages' );
            if ( ! is_wp_error( $user_skills_terms ) && ! empty( $user_skills_terms ) ) {
                $skill_names = array();
                foreach ( $user_skills_terms as $term ) {
                    $skill_names[] = $term->name;
                }
                $user_info['skills'] = implode( ', ', $skill_names );
            } else {
                $meta_skills = get_user_meta( $user->ID, '_workreap_languages', true );
                if ( is_array( $meta_skills ) ) {
                    $user_info['skills'] = implode( ', ', $meta_skills );
                } elseif ( ! empty( $meta_skills ) ) {
                    $user_info['skills'] = $meta_skills;
                }
            }

            if ( empty( $user_info['first_name'] ) && empty( $user_info['last_name'] ) ) {
                $user_info['first_name'] = $user->display_name;
            }

            $users_data[] = $user_info;
        }
    }

    return array(
        'users' => $users_data,
        'total_users' => $user_query->get_total(),
    );
}

/**
 * Enqueues the plugin's stylesheet.
 */
function cud_enqueue_styles() {
    // Only enqueue if the shortcode is likely to be on the page.
    // A more robust check would be to see if the global $post object contains the shortcode.
    // For now, we'll enqueue it if it's not the admin area.
    if ( ! is_admin() ) {
        wp_enqueue_style(
            'custom-user-directory-style', // Handle
            plugin_dir_url( __FILE__ ) . 'assets/css/custom-user-directory.css', // Path to CSS file
            array(), // Dependencies
            '1.0.0' // Version
        );
    }
}
add_action( 'wp_enqueue_scripts', 'cud_enqueue_styles' );

?>
