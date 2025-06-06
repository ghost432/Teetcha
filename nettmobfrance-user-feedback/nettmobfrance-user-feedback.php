<?php
/**
 * Plugin Name: NettmobFrance User Feedback
 * Plugin URI: https://www.nettmob.fr
 * Description: A simple plugin to collect user feedback via a floating button and form, and view submissions in the admin area.
 * Version: 1.1.0
 * Author: NettmobFrance
 * Author URI: https://nettmobfrance.fr
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
 */
function nettmob_create_feedback_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'nettmob_feedback_submissions';
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
        preferred_contact TEXT NULL,
        app_download_problems VARCHAR(10) NOT NULL,
        app_download_details TEXT NULL,
        platform_feedback TEXT NULL,
        platform_rating TINYINT UNSIGNED NULL,
        user_agent TEXT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}
register_activation_hook( __FILE__, 'nettmob_create_feedback_table' );

/**
 * Enqueues scripts and styles for the plugin.
 */
function nuf_enqueue_scripts() {
    $plugin_data = get_plugin_data( __FILE__ );
    $plugin_version = $plugin_data['Version'] ? $plugin_data['Version'] : '1.1.0';

    wp_enqueue_style( 'nuf-style', NUF_PLUGIN_URL . 'css/style.css', array(), $plugin_version, 'all' );
    wp_enqueue_script( 'nuf-script', NUF_PLUGIN_URL . 'js/script.js', array( 'jquery' ), $plugin_version, true );

    $current_page_id = 0;
    if (is_singular() || is_page() || is_single()) {
        $current_page_id = get_the_ID();
    } elseif (is_front_page()) {
         $current_page_id = (int) get_option('page_on_front', 0);
    }

    $current_user_id = get_current_user_id();
    $user_has_submitted = false;
    if ($current_user_id > 0) {
        // if (!current_user_can('manage_options')) { // Optional: exempt admins
            $user_has_submitted = (bool) get_user_meta($current_user_id, 'nettmob_feedback_submitted_by_user', true);
        // }
    }
    // Note: For guests, $user_has_submitted remains false here. JS will check the main cookie.

    $direct_display_enabled = (bool) get_option('nettmob_direct_display_enabled', 0);
    $direct_display_pages_option = get_option('nettmob_direct_display_pages', 'none');
    $is_direct_display_page = false;

    if ($direct_display_enabled) {
        $can_show_direct_display = true;
        if ($user_has_submitted && $current_user_id > 0) {
             $can_show_direct_display = false;
        }
        // For guests, JS will check the main 'nettmob_feedback_submitted' cookie.
        // $is_direct_display_page can be true here for guests, JS makes final call.
        if ($can_show_direct_display) {
            if ($direct_display_pages_option === 'all') {
                $is_direct_display_page = true;
            } elseif ($direct_display_pages_option !== 'none' && $current_page_id > 0 && (int)$direct_display_pages_option === $current_page_id) {
                $is_direct_display_page = true;
            }
        }
    }

    wp_localize_script( 'nuf-script', 'nettmob_feedback_ajax', array(
        'ajax_url'        => admin_url( 'admin-ajax.php' ),
        'nonce'           => wp_create_nonce( 'nettmob_feedback_nonce_action' ),
        'popup_enabled'   => get_option('nettmob_popup_enabled', 0),
        'popup_pages'     => get_option('nettmob_popup_pages', 'none'),
        'cookie_lifetime' => get_option('nettmob_popup_cookie_lifetime', 30),
        'current_page_id' => (string)$current_page_id,
        'is_front_page'   => is_front_page(),
        'page_on_front'   => (string)get_option('page_on_front', '0'),
        'cookie_name'     => 'nettmob_feedback_submitted',
        'popup_close_cookie_name' => 'nettmob_feedback_popup_closed_temp',
        'user_has_submitted' => $user_has_submitted,
        'i18n_already_submitted' => __('You have already submitted feedback.', 'nettmobfrance-user-feedback'),
        'is_direct_display_page' => $is_direct_display_page,
        'button_clicked_session_cookie_name' => 'nettmob_button_clicked_session',
        'debug_mode'      => defined('WP_DEBUG') && WP_DEBUG
    ));
}
add_action( 'wp_enqueue_scripts', 'nuf_enqueue_scripts' );


/**
 * Adds custom classes to the body tag.
 */
function nuf_add_body_classes($classes) {
    $user_has_submitted_for_body_class = false;
    if (is_user_logged_in()) {
        $user_has_submitted_for_body_class = (bool) get_user_meta(get_current_user_id(), 'nettmob_feedback_submitted_by_user', true);
    } else {
        // Check cookie for guests
        if (isset($_COOKIE['nettmob_feedback_submitted'])) {
            $user_has_submitted_for_body_class = true;
        }
    }

    $direct_display_enabled_for_body = (bool) get_option('nettmob_direct_display_enabled', 0);
    $direct_display_pages_option_for_body = get_option('nettmob_direct_display_pages', 'none');
    $is_direct_display_page_for_body_class = false;

    if ($direct_display_enabled_for_body && !$user_has_submitted_for_body_class) {
        $current_page_id_for_body_class = 0;
        if (is_singular() || is_page() || is_single()) {
            $current_page_id_for_body_class = get_the_ID();
        } elseif (is_front_page()) {
            $current_page_id_for_body_class = (int) get_option('page_on_front', 0);
        }

        if ($direct_display_pages_option_for_body === 'all') {
            $is_direct_display_page_for_body_class = true;
        } elseif ($direct_display_pages_option_for_body !== 'none' && $current_page_id_for_body_class > 0 && (int)$direct_display_pages_option_for_body === $current_page_id_for_body_class) {
            $is_direct_display_page_for_body_class = true;
        }
    }

    if ($is_direct_display_page_for_body_class) {
        $classes[] = 'nuf-direct-display-on-page';
    }
    return $classes;
}
add_filter('body_class', 'nuf_add_body_classes');


/**
 * Generates the HTML for the feedback form.
 */
function nettmob_display_feedback_form() {
    ob_start();
    ?>
    <form id="nettmob-feedback-form" method="post">
        <div>
            <label for="nettmob_mission_notifications"><?php _e( 'Recevez-vous bien les notifications des missions ?', 'nettmobfrance-user-feedback' ); ?></label><br />
            <input type="radio" name="nettmob_mission_notifications" value="oui" required> <?php _e( 'Oui', 'nettmobfrance-user-feedback' ); ?>
            <input type="radio" name="nettmob_mission_notifications" value="non"> <?php _e( 'Non', 'nettmobfrance-user-feedback' ); ?>
        </div>
        <div id="nettmob_notification_suggestions_container">
            <label for="nettmob_notification_suggestions"><?php _e( 'Avez-vous des suggestions pour améliorer la façon dont vous recevez les notifications ?', 'nettmobfrance-user-feedback' ); ?></label><br />
            <textarea name="nettmob_notification_suggestions" id="nettmob_notification_suggestions"></textarea>
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
            <label><?php _e('Quelle est votre préférence de contact ? (Plusieurs choix possibles)', 'nettmobfrance-user-feedback'); ?></label><br />
            <div style="margin-bottom: 5px;"><label><input type="checkbox" name="nettmob_preferred_contact[]" value="sms" /> <?php _e('SMS', 'nettmobfrance-user-feedback'); ?></label></div>
            <div style="margin-bottom: 5px;"><label><input type="checkbox" name="nettmob_preferred_contact[]" value="email" /> <?php _e('Email', 'nettmobfrance-user-feedback'); ?></label></div>
            <div><label><input type="checkbox" name="nettmob_preferred_contact[]" value="notification" /> <?php _e('Notification Push', 'nettmobfrance-user-feedback'); ?></label></div>
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
            <?php wp_nonce_field( 'nettmob_feedback_nonce_action', 'nettmob_feedback_nonce_field' ); ?>
            <input type="submit" name="nettmob_submit_feedback" value="<?php _e( 'Envoyer votre avis', 'nettmobfrance-user-feedback' ); ?>">
        </div>
    </form>
    <?php
    return ob_get_clean();
}

/**
 * Registers a shortcode [nettmob_feedback_form] to display the feedback form.
 */
function nettmob_feedback_form_shortcode() {
    return nettmob_display_feedback_form();
}
add_shortcode( 'nettmob_feedback_form', 'nettmob_feedback_form_shortcode' );

/**
 * Adds the feedback button and form container to the website footer.
 */
function nettmob_add_feedback_button_and_form_container() {
    $logo_url = get_option( 'nettmob_feedback_logo_url', '' );

    $user_id_for_direct_display = get_current_user_id();
    $user_has_submitted_for_direct_display = false;
    if ($user_id_for_direct_display > 0) {
        $user_has_submitted_for_direct_display = (bool) get_user_meta($user_id_for_direct_display, 'nettmob_feedback_submitted_by_user', true);
    } else {
        if (isset($_COOKIE['nettmob_feedback_submitted'])) {
            $user_has_submitted_for_direct_display = true;
        }
    }

    $direct_display_enabled = (bool) get_option('nettmob_direct_display_enabled', 0);
    $direct_display_pages_option = get_option('nettmob_direct_display_pages', 'none');
    $is_direct_display_page = false;

    if ($direct_display_enabled && !$user_has_submitted_for_direct_display) {
        $current_page_id_for_button = 0;
         if (is_singular() || is_page() || is_single()) {
            $current_page_id_for_button = get_the_ID();
        } elseif (is_front_page()) {
            $current_page_id_for_button = (int) get_option('page_on_front', 0);
        }

        if ($direct_display_pages_option === 'all') {
            $is_direct_display_page = true;
        } elseif ($direct_display_pages_option !== 'none' && $current_page_id_for_button > 0 && (int)$direct_display_pages_option === $current_page_id_for_button) {
            $is_direct_display_page = true;
        }
    }

    $container_style = 'display: none;';
    $container_classes = '';

    if ($is_direct_display_page) {
        $container_style = 'display: block;';
        $container_classes = 'nuf-direct-display-active';
    }
    ?>
    <button id="nettmob-feedback-trigger-button" <?php if ($is_direct_display_page) echo 'style="display:none;"'; ?>>
        <span class="icon">&#128172;</span> <?php _e( 'Nettmob Avis', 'nettmobfrance-user-feedback' ); ?>
    </button>
    <div id="nettmob-feedback-form-container" class="<?php echo esc_attr($container_classes); ?>" style="<?php echo esc_attr($container_style); ?>">
        <div id="nettmob-feedback-form-inner">
            <button id="nettmob-close-feedback-form">X</button>
            <?php if ( ! empty( $logo_url ) ) : ?>
                <div class="nuf-form-logo">
                    <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php _e( 'Logo', 'nettmobfrance-user-feedback' ); ?>">
                </div>
            <?php endif; ?>
            <h3><?php _e( 'Votre Avis Nous Intéresse!', 'nettmobfrance-user-feedback' ); ?></h3>
            <?php echo nettmob_display_feedback_form(); ?>
        </div>
    </div>
    <?php
}
add_action( 'wp_footer', 'nettmob_add_feedback_button_and_form_container' );

/**
 * Adds the admin menu page for the plugin.
 */
function nettmob_add_admin_menu() {
    add_menu_page(
        __( 'Nettmob User Feedback', 'nettmobfrance-user-feedback' ),
        __( 'Nettmob Feedback', 'nettmobfrance-user-feedback' ),
        'manage_options',
        'nettmob-user-feedback',
        'nettmob_feedback_admin_page_html',
        'dashicons-feedback',
        75
    );
}
add_action( 'admin_menu', 'nettmob_add_admin_menu' );

/**
 * Registers plugin settings using the WordPress Settings API.
 */
function nettmob_register_settings() {
    register_setting(
        'nettmob_feedback_settings_group',
        'nettmob_feedback_logo_url',
        array( 'type' => 'string', 'sanitize_callback' => 'esc_url_raw', 'default' => '' )
    );
    register_setting(
        'nettmob_feedback_settings_group',
        'nettmob_feedback_recipient_email',
        array( 'type' => 'string', 'sanitize_callback' => 'sanitize_email', 'default' => get_option('admin_email') )
    );
    add_settings_section(
        'nettmob_feedback_general_section',
        __( 'General Settings', 'nettmobfrance-user-feedback' ),
        null,
        'nettmob-user-feedback'
    );
    add_settings_field(
        'nettmob_logo_url_field',
        __( 'Logo URL', 'nettmobfrance-user-feedback' ),
        'nettmob_logo_url_callback',
        'nettmob-user-feedback',
        'nettmob_feedback_general_section'
    );
    add_settings_field(
        'nettmob_recipient_email_field',
        __( 'Recipient Email', 'nettmobfrance-user-feedback' ),
        'nettmob_recipient_email_callback',
        'nettmob-user-feedback',
        'nettmob_feedback_general_section'
    );

    // --- Popup Settings ---
    register_setting('nettmob_feedback_settings_group', 'nettmob_popup_enabled');
    register_setting('nettmob_feedback_settings_group', 'nettmob_popup_pages', array('sanitize_callback' => 'nettmob_sanitize_popup_pages', 'default' => 'none'));
    register_setting('nettmob_feedback_settings_group', 'nettmob_popup_cookie_lifetime', array('type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 30));
    add_settings_section('nettmob_feedback_popup_section', __( 'Popup Settings', 'nettmobfrance-user-feedback' ), 'nettmob_popup_section_callback', 'nettmob-user-feedback');
    add_settings_field('nettmob_popup_enabled_field', __( 'Enable Feedback Popup', 'nettmobfrance-user-feedback' ), 'nettmob_popup_enabled_callback', 'nettmob-user-feedback', 'nettmob_feedback_popup_section');
    add_settings_field('nettmob_popup_pages_field', __( 'Show Popup on Pages', 'nettmobfrance-user-feedback' ), 'nettmob_popup_pages_callback', 'nettmob-user-feedback', 'nettmob_feedback_popup_section');
    add_settings_field('nettmob_popup_cookie_lifetime_field', __( 'Popup Cookie Lifetime', 'nettmobfrance-user-feedback' ), 'nettmob_popup_cookie_lifetime_callback', 'nettmob-user-feedback', 'nettmob_feedback_popup_section');

    // --- Direct Display Settings ---
    register_setting('nettmob_feedback_settings_group', 'nettmob_direct_display_enabled');
    register_setting('nettmob_feedback_settings_group', 'nettmob_direct_display_pages', array('sanitize_callback' => 'nettmob_sanitize_popup_pages', 'default' => 'none'));
    add_settings_section('nettmob_feedback_direct_display_section', __( 'Direct Form Display Settings (Instead of Popup/Button)', 'nettmobfrance-user-feedback' ), 'nettmob_direct_display_section_callback', 'nettmob-user-feedback');
    add_settings_field('nettmob_direct_display_enabled_field', __( 'Enable Direct Display on Page', 'nettmobfrance-user-feedback' ), 'nettmob_direct_display_enabled_callback', 'nettmob-user-feedback', 'nettmob_feedback_direct_display_section');
    add_settings_field('nettmob_direct_display_pages_field', __( 'Show Form Directly on Page(s)', 'nettmobfrance-user-feedback' ), 'nettmob_direct_display_pages_callback', 'nettmob-user-feedback', 'nettmob_feedback_direct_display_section');
}
add_action( 'admin_init', 'nettmob_register_settings' );

/**
 * Callback for the Popup Settings section text.
 */
function nettmob_popup_section_callback() {
    echo '<p>' . esc_html__( 'Configure the behavior of the feedback form popup. The popup will display the same form as the floating button but will appear automatically based on these settings if enabled.', 'nettmobfrance-user-feedback' ) . '</p>';
}

/**
 * Sanitizes the page selection setting (used for both popup and direct display).
 */
function nettmob_sanitize_popup_pages($input) {
    $allowed_values = array('none', 'all');
    if (in_array($input, $allowed_values, true)) {
        return $input;
    }
    $page_id = absint($input);
    if ($page_id == 0 && $input !== '0') {
        return 'none';
    }
    return (string)$page_id;
}

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
 * Renders the checkbox for the Enable Popup setting.
 */
function nettmob_popup_enabled_callback() {
    $option = get_option('nettmob_popup_enabled');
    echo '<input type="checkbox" id="nettmob_popup_enabled" name="nettmob_popup_enabled" value="1" ' . checked( $option, 1, false ) . '>';
    echo ' <label for="nettmob_popup_enabled">' . esc_html__('Check to enable the automatic feedback popup.', 'nettmobfrance-user-feedback') . '</label>';
}

/**
 * Renders the single-select dropdown for choosing pages for the popup.
 */
function nettmob_popup_pages_callback() {
    $current_setting = get_option('nettmob_popup_pages', 'none');
    $pages = get_pages();
    echo "<select name='nettmob_popup_pages' id='nettmob_popup_pages' style='min-width: 250px;'>";
    echo "<option value='none' " . selected($current_setting, 'none', false) . ">" . esc_html__('-- Disabled (No Popup) --', 'nettmobfrance-user-feedback') . "</option>";
    echo "<option value='all' " . selected($current_setting, 'all', false) . ">" . esc_html__('All Pages', 'nettmobfrance-user-feedback') . "</option>";
    if ($pages) {
        echo "<optgroup label='" . esc_attr__('Specific Pages', 'nettmobfrance-user-feedback') . "'>";
        foreach ($pages as $page) {
            echo "<option value='" . esc_attr($page->ID) . "' " . selected($current_setting, (string)$page->ID, false) . ">";
            echo esc_html($page->post_title);
            echo "</option>";
        }
        echo "</optgroup>";
    }
    echo "</select>";
    echo "<p class='description'>" . esc_html__('Select where the popup should appear. "Disabled" means no automatic popup. "All Pages" means on every page. Or, choose a specific page.', 'nettmobfrance-user-feedback') . "</p>";
}

/**
 * Renders the number input for the Popup Cookie Lifetime setting.
 */
function nettmob_popup_cookie_lifetime_callback() {
    $option = get_option('nettmob_popup_cookie_lifetime', 30);
    echo '<input type="number" id="nettmob_popup_cookie_lifetime" name="nettmob_popup_cookie_lifetime" value="' . esc_attr($option) . '" min="0" class="small-text"> ';
    echo '<label for="nettmob_popup_cookie_lifetime">' . esc_html__('days (Set to 0 to show always, ignoring cookie).', 'nettmobfrance-user-feedback') . '</label>';
    echo "<p class='description'>" . esc_html__('After a user submits the feedback form via the popup, a cookie is set to prevent the popup from reappearing. Define its duration here.', 'nettmobfrance-user-feedback') . "</p>";
}

/**
 * Callback for the Direct Display Settings section text.
 */
function nettmob_direct_display_section_callback() {
    echo '<p>' . esc_html__( 'Configure pages where the feedback form will be displayed directly on page load (without requiring a click, replacing the popup/button logic for those pages).', 'nettmobfrance-user-feedback' ) . '</p>';
}

/**
 * Renders the checkbox for the Enable Direct Display setting.
 */
function nettmob_direct_display_enabled_callback() {
    $option = get_option('nettmob_direct_display_enabled');
    echo '<input type="checkbox" id="nettmob_direct_display_enabled" name="nettmob_direct_display_enabled" value="1" ' . checked( $option, 1, false ) . '>';
    echo ' <label for="nettmob_direct_display_enabled" class="description">' . esc_html__('Enable to display the form directly on the selected page(s) below.', 'nettmobfrance-user-feedback') . '</label>';
}

/**
 * Renders the single-select dropdown for choosing pages for direct display.
 */
function nettmob_direct_display_pages_callback() {
    $current_setting = get_option('nettmob_direct_display_pages', 'none');
    $pages = get_pages();
    echo "<select name='nettmob_direct_display_pages' id='nettmob_direct_display_pages' style='min-width: 250px;'>";
    echo "<option value='none' " . selected($current_setting, 'none', false) . ">" . esc_html__('-- Disabled / No specific page --', 'nettmobfrance-user-feedback') . "</option>";
    echo "<option value='all' " . selected($current_setting, 'all', false) . ">" . esc_html__('All Pages', 'nettmobfrance-user-feedback') . "</option>";
    if ($pages) {
        echo "<optgroup label='" . esc_attr__('Specific Pages', 'nettmobfrance-user-feedback') . "'>";
        foreach ($pages as $page) {
            echo "<option value='" . esc_attr($page->ID) . "' " . selected($current_setting, (string)$page->ID, false) . ">";
            echo esc_html($page->post_title);
            echo "</option>";
        }
        echo "</optgroup>";
    }
    echo "</select>";
    echo "<p class='description'>" . esc_html__('Select where the form should be displayed directly. If "Enable Direct Display" is checked, the form will appear on these pages instead of the button/popup.', 'nettmobfrance-user-feedback') . "</p>";
}

/**
 * Renders the HTML for the admin page (settings and feedback display).
 */
function nettmob_feedback_admin_page_html() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields( 'nettmob_feedback_settings_group' );
            do_settings_sections( 'nettmob-user-feedback' );
            submit_button( __( 'Save Settings', 'nettmobfrance-user-feedback' ) );
            ?>
        </form>
        <hr>
        <h2><?php _e( 'Submitted Feedback', 'nettmobfrance-user-feedback' ); ?></h2>
        <?php
        global $wpdb;
        $table_name = $wpdb->prefix . 'nettmob_feedback_submissions';
        $results = $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY submission_date DESC" );
        if ( $results ) {
            ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col"><?php _e( 'Submission Date', 'nettmobfrance-user-feedback' ); ?></th>
                        <th scope="col"><?php _e( 'User', 'nettmobfrance-user-feedback' ); ?></th>
                        <th scope="col"><?php _e( 'Mission Notifications', 'nettmobfrance-user-feedback' ); ?></th>
                        <th scope="col" class="nuf-admin-column-suggestion"><?php _e( 'Suggestions', 'nettmobfrance-user-feedback' ); ?></th>
                        <th scope="col"><?php _e( 'KYC Difficulties', 'nettmobfrance-user-feedback' ); ?></th>
                        <th scope="col" class="nuf-admin-column-details"><?php _e( 'KYC Details', 'nettmobfrance-user-feedback' ); ?></th>
                        <th scope="col"><?php _e( 'Receives SMS', 'nettmobfrance-user-feedback' ); ?></th>
                        <th scope="col"><?php _e( 'Receives Email (Old)', 'nettmobfrance-user-feedback' ); ?></th>
                        <th scope="col"><?php _e( 'Preferred Contact', 'nettmobfrance-user-feedback' ); ?></th>
                        <th scope="col"><?php _e( 'App Problems', 'nettmobfrance-user-feedback' ); ?></th>
                        <th scope="col" class="nuf-admin-column-details"><?php _e( 'App Details', 'nettmobfrance-user-feedback' ); ?></th>
                        <th scope="col" class="nuf-admin-column-suggestion"><?php _e( 'Platform Feedback', 'nettmobfrance-user-feedback' ); ?></th>
                        <th scope="col"><?php _e( 'Platform Rating', 'nettmobfrance-user-feedback' ); ?></th>
                        <th scope="col"><?php _e( 'Device Info', 'nettmobfrance-user-feedback'); ?></th>
                        <th scope="col"><?php _e( 'Actions', 'nettmobfrance-user-feedback' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $results as $row ) : ?>
                        <tr>
                            <td><?php echo esc_html( mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $row->submission_date) ); ?></td>
                            <td>
                                <?php
                                if ( $row->user_id ) {
                                    $user_info = get_userdata($row->user_id);
                                    echo esc_html( $user_info ? $user_info->display_name : __( 'ID:', 'nettmobfrance-user-feedback' ) . ' ' . $row->user_id );
                                    echo '<br><small>' . esc_html( $row->user_email ? $row->user_email : ($user_info ? $user_info->user_email : __( 'N/A', 'nettmobfrance-user-feedback' )) ) . '</small>';
                                } elseif ( $row->user_name || $row->user_email) {
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
                            <td><?php echo esc_html( $row->preferred_contact ); ?></td>
                            <td><?php echo esc_html( ucfirst( $row->app_download_problems ) ); ?></td>
                            <td class="nuf-admin-column-details"><?php echo wp_trim_words( esc_html( $row->app_download_details ), 15, '...' ); ?></td>
                            <td class="nuf-admin-column-suggestion"><?php echo esc_html( wp_trim_words( $row->platform_feedback, 15, '...' ) ); ?></td>
                            <td>
                                <?php
                                if ( $row->platform_rating ) {
                                    $stars = str_repeat('&#9733;', (int)$row->platform_rating);
                                    $empty_stars = str_repeat('&#9734;', 5 - (int)$row->platform_rating);
                                    echo '<span style="color: #ffb900;">' . $stars . '</span>' . $empty_stars;
                                } else {
                                    echo esc_html__( 'N/A', 'nettmobfrance-user-feedback' );
                                }
                                ?>
                            </td>
                            <td title="<?php echo esc_attr($row->user_agent); ?>">
                                <?php echo esc_html(wp_trim_words($row->user_agent, 10, '...')); ?>
                            </td>
                            <td>
                                <?php
                                $delete_link = add_query_arg(array(
                                    'action'  => 'nuf_delete_feedback',
                                    'item_id' => $row->id,
                                    '_wpnonce' => wp_create_nonce('nuf_delete_feedback_nonce_' . $row->id)
                                ), admin_url('admin.php?page=nettmob-user-feedback'));
                                ?>
                                <a href="<?php echo esc_url($delete_link); ?>" class="button button-link-delete"
                                   onclick="return confirm('<?php echo esc_js(__('Are you sure you want to permanently delete this feedback entry? This action cannot be undone.', 'nettmobfrance-user-feedback')); ?>');">
                                   <?php _e('Delete Permanently', 'nettmobfrance-user-feedback'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php
        } else {
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
 */
function nettmob_handle_ajax_submission() {
    check_ajax_referer( 'nettmob_feedback_nonce_action', 'nettmob_feedback_nonce_field' );
    global $wpdb;
    $table_name = $wpdb->prefix . 'nettmob_feedback_submissions';
    $data = array();
    $current_user = wp_get_current_user();
    if ( $current_user->ID > 0 ) {
        $data['user_id'] = $current_user->ID;
        $data['user_name'] = sanitize_text_field( $current_user->display_name );
        $data['user_email'] = sanitize_email( $current_user->user_email );
    }
    $data['mission_notifications'] = isset($_POST['nettmob_mission_notifications']) ? sanitize_text_field($_POST['nettmob_mission_notifications']) : '';
    $data['notification_suggestions'] = isset($_POST['nettmob_notification_suggestions']) ? sanitize_textarea_field($_POST['nettmob_notification_suggestions']) : null;
    $data['kyc_difficulties'] = isset($_POST['nettmob_kyc_difficulties']) ? sanitize_text_field($_POST['nettmob_kyc_difficulties']) : '';
    $data['kyc_details'] = isset($_POST['nettmob_kyc_details']) ? sanitize_textarea_field($_POST['nettmob_kyc_details']) : null;
    $data['receive_sms'] = isset($_POST['nettmob_receive_sms']) ? sanitize_text_field($_POST['nettmob_receive_sms']) : '';
    $data['receive_email'] = isset($_POST['nettmob_receive_email']) ? sanitize_text_field($_POST['nettmob_receive_email']) : '';
    $preferred_contact_array = array();
    if (isset($_POST['nettmob_preferred_contact']) && is_array($_POST['nettmob_preferred_contact'])) {
        foreach ($_POST['nettmob_preferred_contact'] as $contact_method) {
            $preferred_contact_array[] = sanitize_text_field($contact_method);
        }
    }
    $data['preferred_contact'] = implode(', ', $preferred_contact_array);
    $data['app_download_problems'] = isset($_POST['nettmob_app_download_problems']) ? sanitize_text_field($_POST['nettmob_app_download_problems']) : '';
    $data['app_download_details'] = isset($_POST['nettmob_app_download_details']) ? sanitize_textarea_field($_POST['nettmob_app_download_details']) : null;
    $data['platform_feedback'] = isset($_POST['nettmob_platform_feedback']) ? sanitize_textarea_field($_POST['nettmob_platform_feedback']) : '';
    $platform_rating = isset($_POST['nettmob_platform_rating']) ? intval($_POST['nettmob_platform_rating']) : null;
    if ($platform_rating !== null && ($platform_rating < 1 || $platform_rating > 5)) {
        $platform_rating = null;
    }
    $data['platform_rating'] = $platform_rating;
    $data['user_agent'] = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
    $inserted = $wpdb->insert( $table_name, $data );
    if ( ! $inserted ) {
        wp_send_json_error( array( 'message' => __( 'Error saving your feedback to the database. Please try again.', 'nettmobfrance-user-feedback' ) . ' ' . $wpdb->last_error ) );
        return;
    }
    $recipient_email = get_option( 'nettmob_feedback_recipient_email', get_option( 'admin_email' ) );
    $site_name = get_bloginfo( 'name' );
    $subject = sprintf( __( 'Nouveau Feedback Utilisateur - %s', 'nettmobfrance-user-feedback' ), $site_name );
    $email_body = "<html><body>";
    $email_body .= "<h2>" . __( 'Nouveau Feedback Utilisateur', 'nettmobfrance-user-feedback' ) . "</h2>";
    if ($data['user_id'] > 0 && !empty($data['user_name'])) {
        $email_body .= "<p><strong>" . __( 'Utilisateur:', 'nettmobfrance-user-feedback' ) . "</strong> " . esc_html($data['user_name']) . " (" . esc_html($data['user_email']) . ")</p>";
    } elseif (!empty($data['user_name'])) {
         $email_body .= "<p><strong>" . __( 'Utilisateur:', 'nettmobfrance-user-feedback' ) . "</strong> " . esc_html($data['user_name']) . " (" . esc_html($data['user_email']) . ")</p>";
    }
    $email_body .= "<ul>";
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
        'user_agent' => __('Device Info (User Agent):', 'nettmobfrance-user-feedback'),
    );
    foreach ($data as $key => $value) {
        if (in_array($key, array('user_id', 'user_name', 'user_email'))) continue;
        if ($value === null || $value === '') continue;
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
    $from_email = get_option('admin_email');
    $headers[] = 'From: ' . $site_name . ' <' . $from_email . '>';
    if ($data['user_id'] > 0 && !empty($data['user_email'])) {
        $headers[] = 'Reply-To: ' . $data['user_name'] . ' <' . $data['user_email'] . '>';
    } elseif (!empty($data['user_email'])) {
        $headers[] = 'Reply-To: ' . ($data['user_name'] ? $data['user_name'] : 'Guest') . ' <' . $data['user_email'] . '>';
    }
    $mailed = wp_mail( $recipient_email, $subject, $email_body, $headers );
    if ($inserted) {
        $user_id = get_current_user_id();
        if ($user_id > 0) {
            update_user_meta($user_id, 'nettmob_feedback_submitted_by_user', true);
        }
    }
    if ( $mailed ) {
        wp_send_json_success( array( 'message' => __( 'Merci de votre avis! &#x1F60A;', 'nettmobfrance-user-feedback' ) ) );
    } else {
        wp_send_json_success( array( 'message' => __( 'Merci de votre avis! (L\'email n\'a pas pu être envoyé, mais vos commentaires sont sauvegardés)', 'nettmobfrance-user-feedback' ) ) );
    }
}
add_action( 'wp_ajax_nettmob_submit_feedback', 'nettmob_handle_ajax_submission' );
add_action( 'wp_ajax_nopriv_nettmob_submit_feedback', 'nettmob_handle_ajax_submission' );

/**
 * Loads the plugin text domain for internationalization.
 */
function nettmob_load_textdomain() {
    load_plugin_textdomain( 'nettmobfrance-user-feedback', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'nettmob_load_textdomain' );

/**
 * Checks if database table updates are needed and applies them.
 */
function nettmob_update_db_check() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'nettmob_feedback_submissions';
    $column_platform_feedback = $wpdb->get_results( $wpdb->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = %s AND column_name = 'platform_feedback' AND table_schema = %s",
        $table_name, DB_NAME ) );
    if ( empty( $column_platform_feedback ) ) {
        $wpdb->query( "ALTER TABLE $table_name ADD platform_feedback TEXT NULL" );
    }
    $column_platform_rating = $wpdb->get_results( $wpdb->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = %s AND column_name = 'platform_rating' AND table_schema = %s",
        $table_name, DB_NAME ) );
    if ( empty( $column_platform_rating ) ) {
        $wpdb->query( "ALTER TABLE $table_name ADD platform_rating TINYINT UNSIGNED NULL" );
    }
    $column_receive_general_notifications_exists = $wpdb->get_results( $wpdb->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = %s AND column_name = 'receive_general_notifications' AND table_schema = %s",
        $table_name, DB_NAME ) );
    if ( !empty( $column_receive_general_notifications_exists ) ) {
        $wpdb->query( "ALTER TABLE $table_name DROP COLUMN receive_general_notifications" );
    }
    $column_preferred_contact_type = $wpdb->get_var( $wpdb->prepare(
        "SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = %s AND column_name = 'preferred_contact' AND table_schema = %s",
        $table_name, DB_NAME ) );
    if ( $column_preferred_contact_type && strtolower( $column_preferred_contact_type ) != 'text' ) {
        $wpdb->query( "ALTER TABLE $table_name MODIFY preferred_contact TEXT NULL" );
    }
    $column_user_agent = $wpdb->get_results( $wpdb->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = %s AND column_name = 'user_agent' AND table_schema = %s",
        $table_name, DB_NAME ) );
    if ( empty( $column_user_agent ) ) {
        $wpdb->query( "ALTER TABLE $table_name ADD user_agent TEXT NULL" );
    }
}
add_action( 'admin_init', 'nettmob_update_db_check' );

/**
 * Shortcode to send a feedback invitation email.
 */
function nettmob_send_feedback_invitation_shortcode( $atts ) {
    $atts = shortcode_atts( array(
            'email'   => '',
            'subject' => __( 'Your feedback is important to us!', 'nettmobfrance-user-feedback' ),
        ), $atts, 'nettmob_send_feedback_invitation' );
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
        esc_html( $site_name ), esc_url( $site_url ) );
    $headers   = array();
    $headers[] = "From: " . esc_html( $site_name ) . " <" . $admin_email . ">";
    $headers[] = "Reply-To: " . $admin_email;
    $headers[] = "Content-Type: text/html; charset=UTF-8";
    $sent = wp_mail( $recipient_email, $email_subject, $message_body, $headers );
    if ( $sent ) {
        return '<p class="nuf-shortcode-success">' . sprintf(
            esc_html__( 'Feedback invitation successfully sent to %s.', 'nettmobfrance-user-feedback' ),
            esc_html( $recipient_email ) ) . '</p>';
    } else {
        return '<p class="nuf-shortcode-error">' . esc_html__( 'Failed to send the feedback invitation. Please check your site\'s email configuration.', 'nettmobfrance-user-feedback' ) . '</p>';
    }
}
add_shortcode( 'nettmob_send_feedback_invitation', 'nettmob_send_feedback_invitation_shortcode' );

/**
 * Handles the deletion of a feedback submission.
 */
function nuf_handle_delete_feedback_submission() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to delete feedback.', 'nettmobfrance-user-feedback'));
    }
    $item_id = isset($_GET['item_id']) ? absint($_GET['item_id']) : 0;
    if ($item_id === 0 || !isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_key($_GET['_wpnonce']), 'nuf_delete_feedback_nonce_' . $item_id)) {
        wp_die(esc_html__('Security check failed. Invalid item or nonce.', 'nettmobfrance-user-feedback'));
    }
    if ($item_id > 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'nettmob_feedback_submissions';
        $deleted = $wpdb->delete($table_name, array('id' => $item_id), array('%d'));
        if ($deleted) {
            set_transient('nuf_admin_notice_feedback_deleted', true, 5);
        } else {
            set_transient('nuf_admin_notice_feedback_delete_failed', true, 5);
        }
    }
    wp_redirect(admin_url('admin.php?page=nettmob-user-feedback'));
    exit;
}
add_action('admin_action_nuf_delete_feedback', 'nuf_handle_delete_feedback_submission');

/**
 * Displays admin notices for feedback deletion.
 */
function nuf_display_admin_notices() {
    if (get_transient('nuf_admin_notice_feedback_deleted')) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Feedback entry deleted successfully.', 'nettmobfrance-user-feedback') . '</p></div>';
        delete_transient('nuf_admin_notice_feedback_deleted');
    }
    if (get_transient('nuf_admin_notice_feedback_delete_failed')) {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Failed to delete feedback entry.', 'nettmobfrance-user-feedback') . '</p></div>';
        delete_transient('nuf_admin_notice_feedback_delete_failed');
    }
}
add_action('admin_notices', 'nuf_display_admin_notices');

/**
 * Shortcode to output a trigger element (e.g., a button or link) that opens the feedback form as a modal.
 */
function nettmob_feedback_popup_trigger_shortcode($atts) {
    $atts = shortcode_atts(array(
        'text'  => __('Open Feedback Form', 'nettmobfrance-user-feedback'),
        'class' => 'button nuf-popup-trigger-shortcode-default',
    ), $atts, 'nettmob_feedback_popup_trigger');
    $text = sanitize_text_field($atts['text']);
    $class = esc_attr($atts['class']);
    return '<a href="#" class="nuf-popup-trigger-shortcode ' . $class . '">' . esc_html($text) . '</a>';
}
add_shortcode('nettmob_feedback_popup_trigger', 'nettmob_feedback_popup_trigger_shortcode');

/**
 * Shortcode to output a trigger element for Elementor popups (specifically triggers modal).
 *
 * Usage: [nettmob_feedback_elementor_popup text="Give Feedback" class="my-custom-class"]
 *
 * @param array $atts Shortcode attributes.
 * @return string HTML for the trigger element.
 */
function nettmob_feedback_elementor_popup_shortcode($atts) {
    $atts = shortcode_atts(array(
        'text'  => __('Open Feedback Form', 'nettmobfrance-user-feedback'), // Default button text
        'class' => 'button nuf-elementor-popup-trigger-default', // Default class for styling
    ), $atts, 'nettmob_feedback_elementor_popup');

    $text = sanitize_text_field($atts['text']);
    $class = esc_attr($atts['class']);

    return '<a href="#" class="nuf-elementor-popup-trigger ' . $class . '">' . esc_html($text) . '</a>';
}
add_shortcode('nettmob_feedback_elementor_popup', 'nettmob_feedback_elementor_popup_shortcode');

?>
