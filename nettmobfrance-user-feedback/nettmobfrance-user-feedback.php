<?php
/**
 * Plugin Name: NettmobFrance User Feedback
 * Plugin URI: https://www.nettmob.fr
 * Description: A simple plugin to collect user feedback via a floating button and form, and view submissions in the admin area.
 * Version: 1.0.0
 * Author: NettmobFrance
 * Author URI: https://www.nettmob.fr
 * License: GPL2
 * Text Domain: nettmobfrance-user-feedback
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Define plugin path and URL constants for easy access.
define( 'NUF_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'NUF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Creates the custom database table for feedback submissions on plugin activation.
 *
 * This function is hooked to `register_activation_hook`.
 */
function nettmob_create_feedback_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'nettmob_feedback_submissions'; // `wp_nettmob_feedback_submissions`
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        submission_date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
        user_id BIGINT(20) UNSIGNED NULL,
        user_name VARCHAR(255) NULL,
        user_email VARCHAR(255) NULL,
        mission_notifications VARCHAR(10) NOT NULL,
        notification_suggestions TEXT NULL,
        kyc_difficulties VARCHAR(10) NOT NULL,
        kyc_details TEXT NULL,
        receive_sms VARCHAR(10) NOT NULL,
        receive_email VARCHAR(10) NOT NULL,
        preferred_contact VARCHAR(10) NOT NULL,
        app_download_problems VARCHAR(10) NOT NULL,
        app_download_details TEXT NULL,
        platform_feedback TEXT NULL,
        platform_rating TINYINT UNSIGNED NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' ); // Contains dbDelta()
    dbDelta( $sql ); // Creates or updates the table.
}
register_activation_hook( __FILE__, 'nettmob_create_feedback_table' );

/**
 * Enqueues scripts and styles for the plugin.
 *
 * - Frontend styles for the button and form.
 * - Frontend JavaScript for form toggling and AJAX submission.
 * - Localizes script to pass AJAX URL and nonce to JavaScript.
 *
 * Hooked to `wp_enqueue_scripts`.
 */
function nuf_enqueue_scripts() {
    // Enqueue main plugin stylesheet for the frontend.
    wp_enqueue_style( 'nuf-style', NUF_PLUGIN_URL . 'css/style.css', array(), '1.0.0', 'all' );

    // Enqueue main plugin script for the frontend.
    wp_enqueue_script( 'nuf-script', NUF_PLUGIN_URL . 'js/script.js', array( 'jquery' ), '1.0.0', true );

    // Pass data to JavaScript: AJAX URL for submissions and a nonce for security.
    wp_localize_script( 'nuf-script', 'nettmob_feedback_ajax', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ), // WordPress AJAX handler URL.
        'nonce'    => wp_create_nonce( 'nettmob_feedback_nonce_action' ) // Nonce for AJAX request verification.
    ));
}
add_action( 'wp_enqueue_scripts', 'nuf_enqueue_scripts' );

/**
 * Generates the HTML for the feedback form.
 *
 * This function is called to display the form within the floating container
 * and can also be used via the [nettmob_feedback_form] shortcode.
 *
 * @return string The HTML output of the form.
 */
function nettmob_display_feedback_form() {
    ob_start(); // Start output buffering to capture HTML.
    ?>
    <form id="nettmob-feedback-form" method="post">
        <div>
            <label for="nettmob_mission_notifications"><?php _e( 'Recevez-vous bien les notifications des missions ?', 'nettmobfrance-user-feedback' ); ?></label><br />
            <input type="radio" name="nettmob_mission_notifications" value="oui" required> <?php _e( 'Oui', 'nettmobfrance-user-feedback' ); ?>
            <input type="radio" name="nettmob_mission_notifications" value="non"> <?php _e( 'Non', 'nettmobfrance-user-feedback' ); ?>
        </div>

        <div>
            <label for="nettmob_notification_suggestions"><?php _e( 'Avez-vous des suggestions pour améliorer la façon dont vous recevez les notifications ?', 'nettmobfrance-user-feedback' ); ?></label><br />
            <textarea name="nettmob_notification_suggestions"></textarea>
        </div>

        <div>
            <label for="nettmob_kyc_difficulties"><?php _e( 'Avez-vous rencontré des difficultés à faire votre KYC ?', 'nettmobfrance-user-feedback' ); ?></label><br />
            <input type="radio" name="nettmob_kyc_difficulties" value="oui" required> <?php _e( 'Oui', 'nettmobfrance-user-feedback' ); ?>
            <input type="radio" name="nettmob_kyc_difficulties" value="non"> <?php _e( 'Non', 'nettmobfrance-user-feedback' ); ?>
        </div>

        <div id="nettmob_kyc_details_container" style="display:none;">
            <label for="nettmob_kyc_details"><?php _e( 'Si oui, lesquelles ?', 'nettmobfrance-user-feedback' ); ?></label><br />
            <textarea name="nettmob_kyc_details"></textarea>
        </div>

        <div>
            <label for="nettmob_receive_sms"><?php _e( 'Recevez-vous des SMS de notre part ?', 'nettmobfrance-user-feedback' ); ?></label><br />
            <input type="radio" name="nettmob_receive_sms" value="oui" required> <?php _e( 'Oui', 'nettmobfrance-user-feedback' ); ?>
            <input type="radio" name="nettmob_receive_sms" value="non"> <?php _e( 'Non', 'nettmobfrance-user-feedback' ); ?>
        </div>

        <div>
            <label for="nettmob_receive_email"><?php _e( 'Recevez-vous des emails de notre part ?', 'nettmobfrance-user-feedback' ); ?></label><br />
            <input type="radio" name="nettmob_receive_email" value="oui" required> <?php _e( 'Oui', 'nettmobfrance-user-feedback' ); ?>
            <input type="radio" name="nettmob_receive_email" value="non"> <?php _e( 'Non', 'nettmobfrance-user-feedback' ); ?>
        </div>

        <div>
            <label for="nettmob_preferred_contact"><?php _e( 'Quelle est votre préférence de contact ?', 'nettmobfrance-user-feedback' ); ?></label><br />
            <input type="radio" name="nettmob_preferred_contact" value="sms" required> <?php _e( 'SMS', 'nettmobfrance-user-feedback' ); ?>
            <input type="radio" name="nettmob_preferred_contact" value="email"> <?php _e( 'Email', 'nettmobfrance-user-feedback' ); ?>
        </div>

        <div>
            <label for="nettmob_app_download_problems"><?php _e( 'Avez-vous rencontré des problèmes pour télécharger l\'application ?', 'nettmobfrance-user-feedback' ); ?></label><br />
            <input type="radio" name="nettmob_app_download_problems" value="oui" required> <?php _e( 'Oui', 'nettmobfrance-user-feedback' ); ?>
            <input type="radio" name="nettmob_app_download_problems" value="non"> <?php _e( 'Non', 'nettmobfrance-user-feedback' ); ?>
        </div>

        <div id="nettmob_app_download_details_container" style="display:none;">
            <label for="nettmob_app_download_details"><?php _e( 'Si oui, lesquels ?', 'nettmobfrance-user-feedback' ); ?></label><br />
            <textarea name="nettmob_app_download_details"></textarea>
        </div>

        <div>
            <label for="nettmob_platform_feedback"><?php _e( 'Avez-vous des suggestions ou critiques concernant la plateforme afin que nous puissions améliorer l\'expérience utilisateur ?', 'nettmobfrance-user-feedback' ); ?></label><br />
            <textarea name="nettmob_platform_feedback" id="nettmob_platform_feedback"></textarea>
        </div>

        <div class="form-field form-field-stars">
            <label><?php _e('Quelle note donneriez-vous à notre plateforme et services ?', 'nettmobfrance-user-feedback'); ?></label>
            <div class="star-rating-input">
                <input type="radio" id="star5" name="nettmob_platform_rating" value="5" required /><label for="star5" title="<?php _e('5 stars', 'nettmobfrance-user-feedback'); ?>">&#9733;</label>
                <input type="radio" id="star4" name="nettmob_platform_rating" value="4" /><label for="star4" title="<?php _e('4 stars', 'nettmobfrance-user-feedback'); ?>">&#9733;</label>
                <input type="radio" id="star3" name="nettmob_platform_rating" value="3" /><label for="star3" title="<?php _e('3 stars', 'nettmobfrance-user-feedback'); ?>">&#9733;</label>
                <input type="radio" id="star2" name="nettmob_platform_rating" value="2" /><label for="star2" title="<?php _e('2 stars', 'nettmobfrance-user-feedback'); ?>">&#9733;</label>
                <input type="radio" id="star1" name="nettmob_platform_rating" value="1" /><label for="star1" title="<?php _e('1 star', 'nettmobfrance-user-feedback'); ?>">&#9733;</label>
            </div>
        </div>

        <div>
            <?php wp_nonce_field( 'nettmob_feedback_nonce_action', 'nettmob_feedback_nonce_field' ); // Nonce field for security. ?>
            <input type="submit" name="nettmob_submit_feedback" value="<?php _e( 'Envoyer votre avis', 'nettmobfrance-user-feedback' ); ?>">
        </div>
    </form>
    <?php
    return ob_get_clean(); // Return buffered HTML.
}

/**
 * Registers a shortcode [nettmob_feedback_form] to display the feedback form.
 *
 * @return string HTML output of the feedback form.
 */
function nettmob_feedback_form_shortcode() {
    return nettmob_display_feedback_form();
}
add_shortcode( 'nettmob_feedback_form', 'nettmob_feedback_form_shortcode' );

/**
 * Adds the feedback button and form container to the website footer.
 *
 * This function is hooked to `wp_footer`.
 * It retrieves the logo URL from settings and displays it if available.
 */
function nettmob_add_feedback_button_and_form_container() {
    $logo_url = get_option( 'nettmob_feedback_logo_url', '' ); // Get saved logo URL.
    ?>
    <button id="nettmob-feedback-trigger-button">
        <span class="icon">&#128172;</span> <?php _e( 'Nettmob Avis', 'nettmobfrance-user-feedback' ); // Speech bubble icon ?>
    </button>
    <div id="nettmob-feedback-form-container" style="display: none;">
        <button id="nettmob-close-feedback-form">X</button>
        <?php if ( ! empty( $logo_url ) ) : // Display logo if URL is set. ?>
            <div class="nuf-form-logo">
                <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php _e( 'Logo', 'nettmobfrance-user-feedback' ); ?>">
            </div>
        <?php endif; ?>
        <h3><?php _e( 'Votre Avis Nous Intéresse!', 'nettmobfrance-user-feedback' ); ?></h3>
        <?php echo nettmob_display_feedback_form(); // Display the form. ?>
    </div>
    <?php
}
// Ensure the function is correctly hooked, remove any previous hook for safety during development.
remove_action( 'wp_footer', 'nettmob_add_feedback_button_and_form_container' );
add_action( 'wp_footer', 'nettmob_add_feedback_button_and_form_container' );

/**
 * Adds the admin menu page for the plugin.
 *
 * This function is hooked to `admin_menu`.
 * It creates a top-level menu item for accessing plugin settings and feedback.
 */
function nettmob_add_admin_menu() {
    add_menu_page(
        __( 'Nettmob User Feedback', 'nettmobfrance-user-feedback' ), // Page title
        __( 'Nettmob Feedback', 'nettmobfrance-user-feedback' ),    // Menu title
        'manage_options',                                           // Capability required
        'nettmob-user-feedback',                                    // Menu slug
        'nettmob_feedback_admin_page_html',                         // Callback function to display page content
        'dashicons-feedback',                                       // Icon URL or dashicon class
        75                                                          // Position in menu
    );
}
add_action( 'admin_menu', 'nettmob_add_admin_menu' );

/**
 * Registers plugin settings using the WordPress Settings API.
 *
 * This function is hooked to `admin_init`.
 * It registers settings for logo URL and recipient email.
 */
function nettmob_register_settings() {
    // Register a setting group.
    register_setting(
        'nettmob_feedback_settings_group',      // Option group
        'nettmob_feedback_logo_url',            // Option name
        array(
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw', // Sanitize URL.
            'default'           => '',
        )
    );

    register_setting(
        'nettmob_feedback_settings_group',      // Option group
        'nettmob_feedback_recipient_email',     // Option name
        array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_email', // Sanitize email.
            'default'           => get_option('admin_email'), // Default to site admin email.
        )
    );

    // Add a settings section for general settings.
    add_settings_section(
        'nettmob_feedback_general_section',         // ID
        __( 'General Settings', 'nettmobfrance-user-feedback' ), // Title
        null,                                       // Callback for section description (none needed here)
        'nettmob-user-feedback'                     // Page slug where this section appears
    );

    // Add settings field for Logo URL.
    add_settings_field(
        'nettmob_logo_url_field',                   // ID
        __( 'Logo URL', 'nettmobfrance-user-feedback' ), // Title
        'nettmob_logo_url_callback',                // Callback function to render the field
        'nettmob-user-feedback',                    // Page slug
        'nettmob_feedback_general_section'          // Section ID
    );

    // Add settings field for Recipient Email.
    add_settings_field(
        'nettmob_recipient_email_field',            // ID
        __( 'Recipient Email', 'nettmobfrance-user-feedback' ), // Title
        'nettmob_recipient_email_callback',         // Callback function to render the field
        'nettmob-user-feedback',                    // Page slug
        'nettmob_feedback_general_section'          // Section ID
    );
}
add_action( 'admin_init', 'nettmob_register_settings' );

/**
 * Renders the input field for the Logo URL setting.
 */
function nettmob_logo_url_callback() {
    $logo_url = get_option( 'nettmob_feedback_logo_url', '' );
    echo '<input type="url" id="nettmob_feedback_logo_url" name="nettmob_feedback_logo_url" value="' . esc_attr( $logo_url ) . '" class="regular-text">';
    echo '<p class="description">' . __( 'Enter the URL for the logo to display at the top of the feedback form.', 'nettmobfrance-user-feedback' ) . '</p>';
}

/**
 * Renders the input field for the Recipient Email setting.
 */
function nettmob_recipient_email_callback() {
    $recipient_email = get_option( 'nettmob_feedback_recipient_email', get_option('admin_email') );
    echo '<input type="email" id="nettmob_feedback_recipient_email" name="nettmob_feedback_recipient_email" value="' . esc_attr( $recipient_email ) . '" class="regular-text">';
    echo '<p class="description">' . __( 'Email address to receive the feedback submissions.', 'nettmobfrance-user-feedback' ) . '</p>';
}

/**
 * Renders the HTML for the admin page (settings and feedback display).
 */
function nettmob_feedback_admin_page_html() {
    // Check user capabilities.
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

        <!-- Settings Form -->
        <form action="options.php" method="post">
            <?php
            settings_fields( 'nettmob_feedback_settings_group' ); // Output nonce, action, and option_page fields for the group.
            do_settings_sections( 'nettmob-user-feedback' );    // Output the settings sections and fields for the page.
            submit_button( __( 'Save Settings', 'nettmobfrance-user-feedback' ) );
            ?>
        </form>

        <hr>
        <h2><?php _e( 'Submitted Feedback', 'nettmobfrance-user-feedback' ); ?></h2>
        <?php
        global $wpdb;
        $table_name = $wpdb->prefix . 'nettmob_feedback_submissions';
        // Query to get all feedback submissions, ordered by the newest first.
        $results = $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY submission_date DESC" );

        if ( $results ) {
            ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col"><?php _e( 'Date', 'nettmobfrance-user-feedback' ); ?></th>
                        <th scope="col"><?php _e( 'User', 'nettmobfrance-user-feedback' ); ?></th>
                        <th scope="col"><?php _e( 'Mission Notifications', 'nettmobfrance-user-feedback' ); ?></th>
                        <th scope="col" class="nuf-admin-column-suggestion"><?php _e( 'Suggestions', 'nettmobfrance-user-feedback' ); ?></th>
                        <th scope="col"><?php _e( 'KYC Difficulties', 'nettmobfrance-user-feedback' ); ?></th>
                        <th scope="col" class="nuf-admin-column-details"><?php _e( 'KYC Details', 'nettmobfrance-user-feedback' ); ?></th>
                        <th scope="col"><?php _e( 'Receives SMS', 'nettmobfrance-user-feedback' ); ?></th>
                        <th scope="col"><?php _e( 'Receives Email', 'nettmobfrance-user-feedback' ); ?></th>
                        <th scope="col"><?php _e( 'Preferred Contact', 'nettmobfrance-user-feedback' ); ?></th>
                        <th scope="col"><?php _e( 'App Problems', 'nettmobfrance-user-feedback' ); ?></th>
                        <th scope="col" class="nuf-admin-column-details"><?php _e( 'App Details', 'nettmobfrance-user-feedback' ); ?></th>
                        <th scope="col" class="nuf-admin-column-suggestion"><?php _e( 'Platform Feedback', 'nettmobfrance-user-feedback' ); ?></th>
                        <th scope="col"><?php _e( 'Platform Rating', 'nettmobfrance-user-feedback' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $results as $row ) : ?>
                        <tr>
                            <td><?php echo esc_html( date_format( date_create( $row->submission_date ), 'Y/m/d H:i:s' ) ); ?></td>
                            <td>
                                <?php
                                if ( $row->user_id ) { // If submitted by a logged-in user
                                    $user_info = get_userdata($row->user_id);
                                    echo esc_html( $user_info ? $user_info->display_name : __( 'ID:', 'nettmobfrance-user-feedback' ) . ' ' . $row->user_id );
                                    echo '<br><small>' . esc_html( $row->user_email ? $row->user_email : ($user_info ? $user_info->user_email : __( 'N/A', 'nettmobfrance-user-feedback' )) ) . '</small>';
                                } elseif ( $row->user_name || $row->user_email) { // If guest user provided name/email (not implemented in current form)
                                    echo esc_html( $row->user_name ? $row->user_name : __( 'Anonymous', 'nettmobfrance-user-feedback' ) );
                                    echo '<br><small>' . esc_html( $row->user_email ? $row->user_email : __( 'N/A', 'nettmobfrance-user-feedback' ) ) . '</small>';
                                } else {
                                    _e( 'Guest', 'nettmobfrance-user-feedback' );
                                }
                                ?>
                            </td>
                            <td><?php echo esc_html( ucfirst( $row->mission_notifications ) ); ?></td>
                            <td class="nuf-admin-column-suggestion"><?php echo wp_trim_words( esc_html( $row->notification_suggestions ), 15, '...' ); ?></td>
                            <td><?php echo esc_html( ucfirst( $row->kyc_difficulties ) ); ?></td>
                            <td class="nuf-admin-column-details"><?php echo wp_trim_words( esc_html( $row->kyc_details ), 15, '...' ); ?></td>
                            <td><?php echo esc_html( ucfirst( $row->receive_sms ) ); ?></td>
                            <td><?php echo esc_html( ucfirst( $row->receive_email ) ); ?></td>
                            <td><?php echo esc_html( ucfirst( $row->preferred_contact ) ); ?></td>
                            <td><?php echo esc_html( ucfirst( $row->app_download_problems ) ); ?></td>
                            <td class="nuf-admin-column-details"><?php echo wp_trim_words( esc_html( $row->app_download_details ), 15, '...' ); ?></td>
                            <td class="nuf-admin-column-suggestion"><?php echo esc_html( wp_trim_words( $row->platform_feedback, 15, '...' ) ); ?></td>
                            <td>
                                <?php
                                if ( $row->platform_rating ) {
                                    // Generate star characters based on the rating
                                    $stars = str_repeat('&#9733;', (int)$row->platform_rating); // Filled star
                                    $empty_stars = str_repeat('&#9734;', 5 - (int)$row->platform_rating); // Empty star
                                    echo '<span style="color: #ffb900;">' . $stars . '</span>' . $empty_stars;
                                    // echo esc_html( $row->platform_rating ) . ' ' . esc_html__( 'star(s)', 'nettmobfrance-user-feedback' );
                                } else {
                                    echo esc_html__( 'N/A', 'nettmobfrance-user-feedback' );
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php
        } else {
            // Message if no feedback submissions are found.
            echo '<p>' . __( 'No feedback submissions yet.', 'nettmobfrance-user-feedback' ) . '</p>';
        }
        ?>

        <hr>
        <h2><?php _e('Send Feedback Invitation by Email', 'nettmobfrance-user-feedback'); ?></h2>
        <p><?php _e('You can use the following shortcode on any page or post to send an email inviting a user to provide feedback. The email will contain a link to your website\'s homepage where the feedback form can be accessed using the floating button.', 'nettmobfrance-user-feedback'); ?></p>
        <p><strong><?php _e('Example Usage:', 'nettmobfrance-user-feedback'); ?></strong></p>
        <p><code>[nettmob_send_feedback_invitation email="user@example.com" subject="<?php esc_attr_e('Your feedback is important to us!', 'nettmobfrance-user-feedback'); ?>"]</code></p>
        <p><?php _e('Replace "user@example.com" with the recipient\'s email address. You can also customize the subject line. If no subject is provided, a default one will be used.', 'nettmobfrance-user-feedback'); ?></p>
        <p><em><?php _e('Note: This shortcode, when executed, will attempt to send an email. It is generally intended for programmatic use or for admins who understand its function when placing it on a page. Displaying its output (success/failure message) on a public page might be desired in some cases.', 'nettmobfrance-user-feedback'); ?></em></p>

    </div>
    <?php
}

/**
 * Handles AJAX form submission.
 *
 * Hooked to `wp_ajax_nettmob_submit_feedback` (for logged-in users)
 * and `wp_ajax_nopriv_nettmob_submit_feedback` (for non-logged-in users).
 * Verifies nonce, sanitizes data, saves to database, and sends email notification.
 */
function nettmob_handle_ajax_submission() {
    // Verify nonce for security.
    check_ajax_referer( 'nettmob_feedback_nonce_action', 'nettmob_feedback_nonce_field' );

    global $wpdb;
    $table_name = $wpdb->prefix . 'nettmob_feedback_submissions';

    // Sanitize and prepare data for database insertion and email.
    $data = array();
    $current_user = wp_get_current_user(); // Get current user object.

    if ( $current_user->ID > 0 ) { // If user is logged in
        $data['user_id'] = $current_user->ID;
        $data['user_name'] = sanitize_text_field( $current_user->display_name );
        $data['user_email'] = sanitize_email( $current_user->user_email );
    } else {
        // For non-logged-in users, these will be NULL as the form doesn't currently ask for name/email.
        // If you add name/email fields for guests, retrieve them here:
        // $data['user_name'] = isset($_POST['guest_name']) ? sanitize_text_field($_POST['guest_name']) : null;
        // $data['user_email'] = isset($_POST['guest_email']) ? sanitize_email($_POST['guest_email']) : null;
    }

    // Sanitize each form field value.
    $data['mission_notifications'] = isset($_POST['nettmob_mission_notifications']) ? sanitize_text_field($_POST['nettmob_mission_notifications']) : '';
    $data['notification_suggestions'] = isset($_POST['nettmob_notification_suggestions']) ? sanitize_textarea_field($_POST['nettmob_notification_suggestions']) : null;
    $data['kyc_difficulties'] = isset($_POST['nettmob_kyc_difficulties']) ? sanitize_text_field($_POST['nettmob_kyc_difficulties']) : '';
    $data['kyc_details'] = isset($_POST['nettmob_kyc_details']) ? sanitize_textarea_field($_POST['nettmob_kyc_details']) : null;
    $data['receive_sms'] = isset($_POST['nettmob_receive_sms']) ? sanitize_text_field($_POST['nettmob_receive_sms']) : '';
    $data['receive_email'] = isset($_POST['nettmob_receive_email']) ? sanitize_text_field($_POST['nettmob_receive_email']) : '';
    $data['preferred_contact'] = isset($_POST['nettmob_preferred_contact']) ? sanitize_text_field($_POST['nettmob_preferred_contact']) : '';
    $data['app_download_problems'] = isset($_POST['nettmob_app_download_problems']) ? sanitize_text_field($_POST['nettmob_app_download_problems']) : '';
    $data['app_download_details'] = isset($_POST['nettmob_app_download_details']) ? sanitize_textarea_field($_POST['nettmob_app_download_details']) : null;

    // Sanitize new fields
    $data['platform_feedback'] = isset($_POST['nettmob_platform_feedback']) ? sanitize_textarea_field($_POST['nettmob_platform_feedback']) : '';
    $platform_rating = isset($_POST['nettmob_platform_rating']) ? intval($_POST['nettmob_platform_rating']) : null;
    if ($platform_rating !== null && ($platform_rating < 1 || $platform_rating > 5)) {
        $platform_rating = null; // Ensure rating is within 1-5 range, or null.
    }
    $data['platform_rating'] = $platform_rating;

    // `submission_date` is handled by database default (CURRENT_TIMESTAMP).

    // Insert data into the database.
    $inserted = $wpdb->insert( $table_name, $data );

    if ( ! $inserted ) {
        // If database insertion fails, send error response.
        wp_send_json_error( array( 'message' => __( 'Error saving your feedback to the database. Please try again.', 'nettmobfrance-user-feedback' ) . ' ' . $wpdb->last_error ) );
        return;
    }

    // Prepare Email content.
    $recipient_email = get_option( 'nettmob_feedback_recipient_email', get_option( 'admin_email' ) ); // Get recipient from settings or default to admin.
    $site_name = get_bloginfo( 'name' );
    $subject = sprintf( __( 'Nouveau Feedback Utilisateur - %s', 'nettmobfrance-user-feedback' ), $site_name );

    $email_body = "<html><body>";
    $email_body .= "<h2>" . __( 'Nouveau Feedback Utilisateur', 'nettmobfrance-user-feedback' ) . "</h2>";
    if ($data['user_id'] > 0 && !empty($data['user_name'])) { // Check if user_name is not empty
        $email_body .= "<p><strong>" . __( 'Utilisateur:', 'nettmobfrance-user-feedback' ) . "</strong> " . esc_html($data['user_name']) . " (" . esc_html($data['user_email']) . ")</p>";
    } elseif (!empty($data['user_name'])) { // For guests if name was provided
         $email_body .= "<p><strong>" . __( 'Utilisateur:', 'nettmobfrance-user-feedback' ) . "</strong> " . esc_html($data['user_name']) . " (" . esc_html($data['user_email']) . ")</p>";
    }


    $email_body .= "<ul>";
    // Define labels for email body for better readability
    $field_labels = array(
        'mission_notifications' => __('Recevez-vous bien les notifications des missions ?', 'nettmobfrance-user-feedback'),
        'notification_suggestions' => __('Suggestions pour améliorer les notifications :', 'nettmobfrance-user-feedback'),
        'kyc_difficulties' => __('Difficultés à faire le KYC ?', 'nettmobfrance-user-feedback'),
        'kyc_details' => __('Détails KYC :', 'nettmobfrance-user-feedback'),
        'receive_sms' => __('Reçoit des SMS ?', 'nettmobfrance-user-feedback'),
        'receive_email' => __('Reçoit des emails ?', 'nettmobfrance-user-feedback'),
        'preferred_contact' => __('Préférence de contact :', 'nettmobfrance-user-feedback'),
        'app_download_problems' => __('Problèmes pour télécharger l\'application ?', 'nettmobfrance-user-feedback'),
        'app_download_details' => __('Détails problèmes application :', 'nettmobfrance-user-feedback'),
        'platform_feedback' => __('Suggestions ou critiques plateforme :', 'nettmobfrance-user-feedback'),
        'platform_rating' => __('Note plateforme et services :', 'nettmobfrance-user-feedback'),
    );

    foreach ($data as $key => $value) {
        // Skip user_id, user_name, user_email for this loop as they are handled above or not relevant as a list item.
        // Also explicitly check for null for platform_rating as 0 is a valid value for empty() but we might want to show null ratings.
        if (in_array($key, array('user_id', 'user_name', 'user_email'))) continue;
        if ($value === null || $value === '') continue; // Skip empty or null values more broadly.

        $label = isset($field_labels[$key]) ? $field_labels[$key] : ucwords(str_replace('_', ' ', $key));
        $display_value = $value;
        if ($key === 'platform_rating') {
            $display_value = sprintf(_n('%s star', '%s stars', $value, 'nettmobfrance-user-feedback'), $value);
        } else {
            $display_value = ucfirst($value);
        }
        $email_body .= "<li><strong>" . esc_html($label) . ":</strong> " . nl2br(esc_html($display_value)) . "</li>";
    }
    $email_body .= "</ul>";
    $email_body .= "<p><small>" . __( 'Soumis le:', 'nettmobfrance-user-feedback' ) . " " . current_time('mysql') . "</small></p>";
    $email_body .= "</body></html>";

    $headers = array('Content-Type: text/html; charset=UTF-8');
    $from_email = get_option('admin_email'); // Use site admin email as sender.
    $headers[] = 'From: ' . $site_name . ' <' . $from_email . '>';
    if ($data['user_id'] > 0 && !empty($data['user_email'])) { // If logged-in user has an email
        $headers[] = 'Reply-To: ' . $data['user_name'] . ' <' . $data['user_email'] . '>';
    } elseif (!empty($data['user_email'])) { // If guest provided email
        $headers[] = 'Reply-To: ' . ($data['user_name'] ? $data['user_name'] : 'Guest') . ' <' . $data['user_email'] . '>';
    }


    $mailed = wp_mail( $recipient_email, $subject, $email_body, $headers );

    if ( $mailed ) {
        // If email sent successfully.
        wp_send_json_success( array( 'message' => __( 'Merci de votre avis! &#x1F60A;', 'nettmobfrance-user-feedback' ) ) );
    } else {
        // If email failed, data is still saved. Inform user, maybe log this for admin.
        wp_send_json_success( array( 'message' => __( 'Merci de votre avis! (L\'email n\'a pas pu être envoyé, mais vos commentaires sont sauvegardés)', 'nettmobfrance-user-feedback' ) ) );
    }
}
add_action( 'wp_ajax_nettmob_submit_feedback', 'nettmob_handle_ajax_submission' );
add_action( 'wp_ajax_nopriv_nettmob_submit_feedback', 'nettmob_handle_ajax_submission' );

/**
 * Loads the plugin text domain for internationalization.
 *
 * Hooked to `plugins_loaded`.
 */
function nettmob_load_textdomain() {
    load_plugin_textdomain( 'nettmobfrance-user-feedback', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'nettmob_load_textdomain' );

/**
 * Checks if database table updates are needed and applies them.
 *
 * This function is hooked to `admin_init`. It checks for the existence
 * of new columns and adds them if they are missing.
 */
function nettmob_update_db_check() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'nettmob_feedback_submissions';

    // Check for 'platform_feedback' column
    $column_platform_feedback = $wpdb->get_results( $wpdb->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = %s AND column_name = 'platform_feedback' AND table_schema = %s",
        $table_name,
        DB_NAME // WordPress constant for the database name
    ) );

    if ( empty( $column_platform_feedback ) ) {
        $wpdb->query( "ALTER TABLE $table_name ADD platform_feedback TEXT NULL" );
    }

    // Check for 'platform_rating' column
    $column_platform_rating = $wpdb->get_results( $wpdb->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = %s AND column_name = 'platform_rating' AND table_schema = %s",
        $table_name,
        DB_NAME
    ) );

    if ( empty( $column_platform_rating ) ) {
        $wpdb->query( "ALTER TABLE $table_name ADD platform_rating TINYINT UNSIGNED NULL" );
    }
}
add_action( 'admin_init', 'nettmob_update_db_check' );

/**
 * Shortcode to send a feedback invitation email.
 *
 * Usage: [nettmob_send_feedback_invitation email="user@example.com" subject="Your Feedback Is Important!"]
 *
 * @param array $atts Shortcode attributes.
 * @return string HTML output (success or error message).
 */
function nettmob_send_feedback_invitation_shortcode( $atts ) {
    $atts = shortcode_atts(
        array(
            'email'   => '',
            'subject' => __( 'Your feedback is important to us!', 'nettmobfrance-user-feedback' ), // Default subject
        ),
        $atts,
        'nettmob_send_feedback_invitation'
    );

    $recipient_email = sanitize_email( $atts['email'] );
    $email_subject   = sanitize_text_field( $atts['subject'] );

    if ( ! is_email( $recipient_email ) ) {
        return '<p class="nuf-shortcode-error">' . esc_html__( 'Error: Invalid email address provided in the shortcode.', 'nettmobfrance-user-feedback' ) . '</p>';
    }

    $site_name   = get_bloginfo( 'name' );
    $site_url    = home_url( '/' );
    $admin_email = get_option( 'admin_email' );

    $message_body = sprintf(
        __( "Hello,<br><br>We would love to hear your feedback about your experience with %s. Please visit our website to share your thoughts by clicking the feedback button available on our site.<br><br>Website: %s<br><br>Thank you!", 'nettmobfrance-user-feedback' ),
        esc_html( $site_name ),
        esc_url( $site_url )
    );

    // For HTML emails, ensure line breaks are converted.
    // $message_body = nl2br( $message_body ); // Not strictly needed if using sprintf with <br> directly

    $headers   = array();
    $headers[] = "From: " . esc_html( $site_name ) . " <" . $admin_email . ">";
    $headers[] = "Reply-To: " . $admin_email;
    $headers[] = "Content-Type: text/html; charset=UTF-8";

    $sent = wp_mail( $recipient_email, $email_subject, $message_body, $headers );

    if ( $sent ) {
        return '<p class="nuf-shortcode-success">' . sprintf(
            esc_html__( 'Feedback invitation successfully sent to %s.', 'nettmobfrance-user-feedback' ),
            esc_html( $recipient_email )
        ) . '</p>';
    } else {
        return '<p class="nuf-shortcode-error">' . esc_html__( 'Failed to send the feedback invitation. Please check your site\'s email configuration.', 'nettmobfrance-user-feedback' ) . '</p>';
    }
}
add_shortcode( 'nettmob_send_feedback_invitation', 'nettmob_send_feedback_invitation_shortcode' );

?>
