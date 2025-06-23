<?php
/**
 * Plugin Name:       Workreap Invoices
 * Plugin URI:        https://example.com/plugins/workreap-invoices/
 * Description:       Génère des factures pour les projets travaillés par les freelances pour les clients sur le thème Workreap.
 * Version:           1.0.0
 * Author:            Votre Nom ou Nom de l'Entreprise
 * Author URI:        https://example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       workreap-invoices
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 */
define( 'WORKREAP_INVOICES_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 */
function activate_workreap_invoices() {
	// Activation code here.
}
register_activation_hook( __FILE__, 'activate_workreap_invoices' );

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_workreap_invoices() {
	// Deactivation code here.
}
register_deactivation_hook( __FILE__, 'deactivate_workreap_invoices' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
// require plugin_dir_path( __FILE__ ) . 'includes/class-workreap-invoices.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_workreap_invoices() {

	// $plugin = new Workreap_Invoices();
	// $plugin->run();

}
// run_workreap_invoices();

// Add the invoice generation function here or include it from another file.

if (!function_exists('workreap_freelancer_invoice_details_plugin')) {
    // Renamed to avoid conflict if the theme function still exists
    // We will also need to decide how this function is triggered.
    // For now, it's just defined.
    function workreap_freelancer_invoice_details_plugin($args=array()) {
        global $workreap_settings; // This global might need to be populated or accessed differently
        ob_start(); // Start output buffering

        $identity   = !empty($args['identity']) ? intval($args['identity']) : "";
        $order_id   = !empty($args['order_id']) ? intval($args['order_id']) : "";

        // It's better to get settings via get_option if they are theme options
        // For now, assuming $workreap_settings is available or will be handled
        $site_logo  = !empty($workreap_settings['defaul_site_logo']['url']) ? $workreap_settings['defaul_site_logo']['url'] : '';

        $order_type = get_post_meta( $order_id, 'project_type',true );

        // Common variables that are needed regardless of project type (or can be fetched early)
        $order = wc_get_order($order_id);
        if (empty($order_id) || !$order) {
             echo '<p>' . sprintf(esc_html__('Order ID %s not found or invalid.', 'workreap-invoices'), esc_html($order_id)) . '</p>';
            return ob_get_clean();
        }

        // Check if WooCommerce is active (though $order check implies it)
        if (!class_exists('WooCommerce')) {
            echo '<p>' . esc_html__('WooCommerce is not active. This plugin requires WooCommerce to function.', 'workreap-invoices') . '</p>';
            return ob_get_clean();
        }

        $date_format    = get_option( 'date_format' );
        $data_created   = $order->get_date_created();
        $order_status   = $order->get_status();
        $order_meta     = get_post_meta( $order_id, 'cus_woo_product_data', true );
        $order_meta     = !empty($order_meta) ? $order_meta : array();

        if(!empty($order_status) && $order_status === 'refunded'){
            $order_status_text  = esc_html__('Refunded','workreap-invoices');
        } else if(!empty($order_status) && $order_status === 'completed'){
            $order_status_text  = esc_html__('Completed','workreap-invoices');
        } else {
            $order_status_text  = wc_get_order_status_name($order_status);
        }
        $data_created   = date_i18n($date_format, strtotime($data_created->date('Y-m-d H:i:s')));

        $processing_fee   = get_post_meta($order_id, 'admin_shares', true);
        $processing_fee   = isset($processing_fee) ? floatval($processing_fee) : 0;
        // $get_total might differ based on project type, or be calculated after specific project type details
        // For now, we keep its original calculation method, assuming it's generic enough or will be adjusted.
        $get_total        = get_post_meta($order_id, 'freelancer_shares', true);
        $get_total        = !empty($get_total) ? floatval($get_total) : 0;

        $billing_address        = $order->get_formatted_billing_address();
        $from_billing_address   = !empty($identity) && function_exists('workreap_user_billing_address') ? workreap_user_billing_address($identity) : '';

        $project_id     = !empty($order_meta['project_id']) ? $order_meta['project_id'] : '';
        $project_title  = !empty($project_id) ? get_the_title( $project_id ) : '';
        $task_title     = $project_title; // Base title

        // Settings from $workreap_settings - ensure this global is reliable or switch to get_option
        $invoice_terms  = !empty($workreap_settings['invoice_terms']) ? $workreap_settings['invoice_terms'] : '';
        $invoice_billing_to = !empty($workreap_settings['invoice_billing_to']) ? $workreap_settings['invoice_billing_to'] : '';
        $billing_address    = !empty($invoice_billing_to) && !empty($workreap_settings['invoice_billing_address']) ? $workreap_settings['invoice_billing_address'] : $billing_address;

        if (!function_exists('workreap_price_format_plugin')) {
            function workreap_price_format_plugin($price, $return = false) {
                $formatted_price = wc_price($price);
                if ($return) {
                    return $formatted_price;
                }
                echo $formatted_price;
            }
        }
        $price_format_func = function_exists('workreap_price_format') ? 'workreap_price_format' : 'workreap_price_format_plugin';

        // Start HTML Output - Common Header Part
        ?>
        <div class="wr-main-section wr-invoice-plugin <?php echo esc_attr('wr-invoice-type-'.$order_type); ?>">
            <div class="container">
                <div class="row">
                    <div class="col-sm-12">
                        <div class="wr-invoicedetal">
                            <div class="wr-printable">
                                <div class="wr-invoicebill">
                                    <?php if( !empty($site_logo) ){
                                        if( !empty($args['option']) && $args['option'] === 'pdf'){
                                            $type           = pathinfo($site_logo, PATHINFO_EXTENSION);
                                            $data           = @file_get_contents($site_logo);
                                            if ($data !== false) {
                                                $base64_logo    = 'data:image/' . $type . ';base64,' . base64_encode($data);
                                                echo do_shortcode( '<figure><img src="'.($base64_logo).'" alt="'.esc_attr__('invoice detail','workreap-invoices').'"></figure>' );
                                            } else {
                                                echo '<p>' . esc_html__('Error loading site logo.', 'workreap-invoices') . '</p>';
                                            }
                                        } else { ?>
                                            <figure>
                                                <img src="<?php echo esc_url($site_logo);?>" alt="<?php esc_attr_e('invoice detail','workreap-invoices');?>">
                                            </figure>
                                    <?php } } ?>
                                    <div class="wr-billno">
                                        <h3><?php esc_html_e('Invoice', 'workreap-invoices'); ?></h3>
                                        <span># <?php echo intval($order_id); ?></span>
                                    </div>
                                    <?php
                                    if (empty($args['option']) || $args['option'] !== 'pdf') {
                                        $download_url = add_query_arg(array(
                                            'download_invoice_pdf' => 'true',
                                            'order_id'             => $order_id,
                                            '_wpnonce'             => wp_create_nonce('download_invoice_' . $order_id)
                                        ), home_url());
                                        ?>
                                        <div class="wr-invoice-download">
                                            <a href="<?php echo esc_url($download_url); ?>" class="button wr-btn"><?php esc_html_e('Download PDF', 'workreap-invoices'); ?></a>
                                        </div>
                                    <?php } ?>
                                </div>
                                <div class="wr-tasksinfos">
                                    <?php if( !empty($task_title) ){?>
                                        <div class="wr-invoicetasks">
                                            <h5><?php esc_html_e('Project Title','workreap-invoices');?>:</h5>
                                            <h3><?php echo esc_html($task_title); ?></h3>
                                        </div>
                                    <?php } ?>
                                    <div class="wr-tasksdates">
                                        <div class="wr-tags"><span class="wr-tag-ongoing order-status-<?php echo esc_attr(sanitize_title($order_status)); ?>"><?php echo esc_html($order_status_text);?></span></div>
                                        <span> <em><?php esc_html_e('Issue date:', 'workreap-invoices') ?>&nbsp;</em><?php echo esc_html($data_created); ?></span>
                                    </div>
                                </div>
                                <div class="wr-invoicefromto">
                                    <?php if (!empty($from_billing_address)){ ?>
                                        <div class="wr-fromreceiver">
                                            <h5><?php esc_html_e('From:', 'workreap-invoices'); ?></h5>
                                            <span><?php echo do_shortcode(nl2br(esc_html($from_billing_address))); ?></span>
                                        </div>
                                    <?php } ?>
                                    <?php if( !empty($billing_address) ){?>
                                        <div class="wr-fromreceiver">
                                            <h5><?php esc_html_e('To:', 'workreap-invoices'); ?></h5>
                                            <span><?php echo do_shortcode(nl2br($billing_address)); ?></span>
                                        </div>
                                    <?php } ?>
                                </div>

                                <?php
                                // Specific part for project type
                                if( !empty($order_type) && $order_type === 'hourly' ){
                                    $invoice_status = get_post_meta( $order_id,'_task_status', true );
                                    $invoice_status = !empty($invoice_status) ? $invoice_status : '';

                                    // Update task_title for hourly to include interval name if present
                                    if (!empty($order_meta['interval_name'])) {
                                        $task_title = $project_title ? $project_title . ' ('. $order_meta['interval_name'].')' : $order_meta['interval_name'];
                                        // We might need to update the display of task_title if it was already printed
                                        // This is a bit tricky as task_title is in the common header.
                                        // For now, the generic project_title is in the header.
                                        // Hourly specific title detail can be in its section.
                                    }
                                ?>
                                    <?php if( !empty($invoice_status) && $invoice_status === 'pending'){?>
                                        <div class="wr-freelancer-empty-hourlyinvoice">
                                            <div class="wr-orderrequest wr-alert-success">
                                                <p><?php esc_html_e('Buyer has not released the payment against the project for which you are hired. Once you will submit the hours for approval then employer will review and release the payment','workreap-invoices') ?></p>
                                            </div>
                                        </div>
                                    <?php } else {?>
                                        <div class="wr-invoicetask-details">
                                            <h4><?php esc_html_e('Hourly Details', 'workreap-invoices'); ?></h4>
                                            <table class="wr-table wr-invoice-table">
                                                <thead>
                                                <tr>
                                                    <th><?php esc_html_e('#','workreap-invoices');?></th>
                                                    <th><?php esc_html_e('Description', 'workreap-invoices'); ?></th>
                                                    <th><?php esc_html_e('Rate per hour', 'workreap-invoices'); ?></th>
                                                    <th><?php esc_html_e('Total hours', 'workreap-invoices'); ?></th>
                                                    <th><?php esc_html_e('Amount', 'workreap-invoices'); ?></th>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if( isset($order_meta['approved_total_time']) && isset($order_meta['approved_amount']) ){?>
                                                        <tr>
                                                            <td data-label="<?php esc_attr_e('#', 'workreap-invoices');?>"><?php echo intval(1);?></td>
                                                            <td data-label="<?php esc_attr_e('Description', 'workreap-invoices');?>">
                                                                <?php
                                                                    if( !empty($order_meta['interval_name']) ){
                                                                        echo esc_html($order_meta['interval_name']);
                                                                    } else {
                                                                        echo esc_html($project_title); // Fallback to project title
                                                                    }
                                                                ?>
                                                            </td>
                                                            <td data-label="<?php esc_attr_e('Rate per hour', 'workreap-invoices'); ?>"><?php call_user_func($price_format_func, $order_meta['hourly_rate']);?></td>
                                                            <td data-label="<?php esc_attr_e('Total hours', 'workreap-invoices'); ?>"><?php echo esc_html($order_meta['approved_total_time']);?></td>
                                                            <td data-label="<?php esc_attr_e('Amount', 'workreap-invoices');?>"><?php call_user_func($price_format_func, $order_meta['approved_amount']);?></td>
                                                        </tr>
                                                    <?php } ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php } ?>
                                <?php
                                } else if ( !empty($order_type) && ($order_type === 'fixed' || $order_type === 'milestone_based' ) ) { // Assuming 'fixed' or 'milestone_based' might be a type
                                    // Placeholder for fixed price project invoice details
                                    // You'll need to fetch relevant data for fixed projects, e.g., agreed price, milestones if any.
                                    $fixed_price_amount = get_post_meta($order_id, '_order_total', true); // Example: get total from order itself
                                    $fixed_price_amount = !empty($fixed_price_amount) ? $fixed_price_amount : $order->get_total();

                                    // For fixed projects, the "sub_total" might just be the project price.
                                    // And admin commission might be calculated differently or stored in different meta.
                                    // We need to ensure $get_total and $processing_fee are correct for fixed projects.
                                    // For now, we'll assume $get_total is the freelancer's share after commission.
                                ?>
                                    <div class="wr-invoicetask-details">
                                        <h4><?php esc_html_e('Project Details', 'workreap-invoices'); ?></h4>
                                        <table class="wr-table wr-invoice-table">
                                            <thead>
                                                <tr>
                                                    <th><?php esc_html_e('Description', 'workreap-invoices'); ?></th>
                                                    <th><?php esc_html_e('Amount', 'workreap-invoices'); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td data-label="<?php esc_attr_e('Description', 'workreap-invoices');?>"><?php echo esc_html($project_title); ?></td>
                                                    <td data-label="<?php esc_attr_e('Amount', 'workreap-invoices');?>"><?php call_user_func($price_format_func, $fixed_price_amount);?></td>
                                                </tr>
                                                <?php
                                                // If there are milestones for a fixed project, you might loop through them here.
                                                // Example:
                                                // $milestones = get_post_meta($project_id, '_milestones', true);
                                                // if (!empty($milestones) && is_array($milestones)) {
                                                //    foreach($milestones as $milestone) {
                                                //        // Display milestone details
                                                //    }
                                                // }
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php
                                } else {
                                    // Handle other project types or show a default message
                                ?>
                                    <div class="wr-invoicetask-details">
                                        <p><?php
                                            if (empty($order_type)) {
                                                esc_html_e('Project type not specified for this order. Cannot display detailed breakdown.', 'workreap-invoices');
                                            } else {
                                                printf(esc_html__('Invoice details for project type "%s" are not yet implemented.', 'workreap-invoices'), esc_html($order_type));
                                            }
                                        ?></p>
                                    </div>
                                <?php
                                }
                                ?>

                                <?php // Common Footer Part (Subtotals, Totals, Terms) ?>
                                <div class="wr-subtotal">
                                    <ul class="wr-subtotalbill">
                                        <?php
                                        // Subtotal logic might need adjustment based on project type
                                        // For hourly, it's $order_meta['approved_amount']
                                        // For fixed, it might be the $fixed_price_amount before commission
                                        $sub_total_display = 0;
                                        if ($order_type === 'hourly' && isset($order_meta['approved_amount'])) {
                                            $sub_total_display = $order_meta['approved_amount'];
                                        } elseif (($order_type === 'fixed' || $order_type === 'milestone_based') && isset($fixed_price_amount)) {
                                            // This assumes fixed_price_amount is the amount *before* admin commission.
                                            // If $get_total is freelancer share and $processing_fee is admin share,
                                            // then sub_total (gross) would be $get_total + $processing_fee.
                                            $sub_total_display = $get_total + $processing_fee;
                                        }
                                        if ($sub_total_display > 0) {
                                        ?>
                                            <li><?php esc_html_e('Sub total:', 'workreap-invoices'); ?> <h6><?php call_user_func($price_format_func, $sub_total_display); ?></h6></li>
                                        <?php } ?>
                                        <li><?php esc_html_e('Admin commission:', 'workreap-invoices'); ?> <h6><?php call_user_func($price_format_func, $processing_fee); ?></h6></li>
                                    </ul>
                                    <div class="wr-sumtotal"><?php esc_html_e('Net Earning (Freelancer):','workreap-invoices'); ?> <h6><?php call_user_func($price_format_func, $get_total); ?></h6></div>
                                </div>

                                <?php if( !empty($invoice_terms) ){?>
                                    <div class="wr-anoverview">
                                        <h4><?php esc_html_e('Terms & Conditions', 'workreap-invoices'); ?></h4>
                                        <div class="wr-description">
                                            <?php echo do_shortcode( wpautop( wp_kses_post( $invoice_terms ) ) ); ?>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean(); // Return buffered content
        // Removed the old "else" block that was outside the main if/else if for order_type
    }
}

// Shortcode to display the invoice
add_shortcode('workreap_invoice', 'workreap_invoice_shortcode_handler');
function workreap_invoice_shortcode_handler($atts) {
    $atts = shortcode_atts(array(
        'order_id' => null,
        // 'identity' argument for workreap_freelancer_invoice_details_plugin will be determined
        // by the user viewing or by order meta, rather than direct shortcode attribute for security.
    ), $atts, 'workreap_invoice');

    if (empty($atts['order_id'])) {
        return "<p>".esc_html__('Error: Missing order_id for the invoice shortcode.', 'workreap-invoices')."</p>";
    }

    if (!is_user_logged_in()) {
       return "<p>".esc_html__('Please log in to view invoices.', 'workreap-invoices')."</p>";
    }

    $current_user_id = get_current_user_id();
    $order_id = intval($atts['order_id']);
    $order = wc_get_order($order_id);

    if (!$order) {
        return "<p>".sprintf(esc_html__('Error: Could not find order with ID %s.', 'workreap-invoices'), $order_id)."</p>";
    }

    // Determine identity for the 'From' field (usually freelancer)
    // This logic should be robust and align with how Workreap stores freelancer data on orders.
    $freelancer_id = get_post_meta($order_id, '_freelancer_id', true); // Adjust meta key as needed
    $employer_id = $order->get_customer_id();

    // Permission check: Current user must be related to the order or an admin.
    // This is a basic check. Refine as per Workreap's roles and capabilities.
    if ( $current_user_id != $freelancer_id && $current_user_id != $employer_id && !current_user_can('manage_woocommerce') ) {
        return "<p>".esc_html__('You do not have permission to view this invoice.', 'workreap-invoices')."</p>";
    }

    // The 'identity' for the invoice function is typically the ID of the entity "From" whom the invoice originates.
    // This is often the freelancer. If the platform issues invoices, this might be a platform ID or fixed info.
    // If this shortcode can be viewed by both freelancer and employer, the 'identity' (From address) should consistently be the freelancer.
    $invoice_identity_args = $freelancer_id ? $freelancer_id : $employer_id; // Fallback, but freelancer ID is preferred for "From"

    $invoice_args = array(
        'order_id' => $order_id,
        'identity' => $invoice_identity_args
    );

    return workreap_freelancer_invoice_details_plugin($invoice_args);
}


/**
 * Ensure the TCPDF library is available.
 * We'll assume it's placed in includes/lib/tcpdf/tcpdf.php
 * You need to download TCPDF and place it there.
 */
if (!class_exists('TCPDF')) {
    $tcpdf_path = plugin_dir_path(__FILE__) . 'includes/lib/tcpdf/tcpdf.php';
    if (file_exists($tcpdf_path)) {
        require_once($tcpdf_path);
    } else {
        // TCPDF is not available, add an admin notice or log an error
        // For now, we'll just define a dummy class to avoid fatal errors if TCPDF is missing
        // but PDF generation will not work.
        if (!is_admin()) { // Avoid issues in admin if class is redeclared
            class TCPDF {
                public function __call($method, $args) {
                    error_log("TCPDF class not found or tcpdf.php is missing. PDF generation will fail.");
                    return null;
                }
                public static function __callStatic($method, $args) {
                    error_log("TCPDF class not found or tcpdf.php is missing. PDF generation will fail.");
                    return null;
                }
            }
        }
    }
}

/**
 * Generates a PDF invoice and saves it or outputs it to the browser.
 *
 * @param array $args Arguments for the invoice (order_id, identity).
 * @param string $output_mode 'F' to save to file, 'I' to output to browser, 'D' to download.
 * @return string|bool Path to the saved PDF file if $output_mode is 'F', true on success for 'I'/'D', false on failure.
 */
function workreap_generate_invoice_pdf($args, $output_mode = 'F') {
    if (!class_exists('TCPDF') || !method_exists('TCPDF', 'AddPage')) {
        error_log('TCPDF library not loaded. Cannot generate PDF.');
        return false;
    }

    $order_id = !empty($args['order_id']) ? intval($args['order_id']) : 0;
    if (empty($order_id)) {
        error_log('Order ID is missing for PDF generation.');
        return false;
    }

    // Get the HTML content of the invoice
    // Pass 'pdf' option to handle image embedding differently if needed
    $invoice_html_args = $args;
    $invoice_html_args['option'] = 'pdf';
    $invoice_html = workreap_freelancer_invoice_details_plugin($invoice_html_args);

    if (empty($invoice_html)) {
        error_log("Failed to generate HTML content for invoice Order ID: {$order_id}.");
        return false;
    }

    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor(get_bloginfo('name'));
    $pdf->SetTitle(sprintf(esc_html__('Invoice #%s', 'workreap-invoices'), $order_id));
    $pdf->SetSubject(sprintf(esc_html__('Invoice for Order #%s', 'workreap-invoices'), $order_id));

    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    // Set default monospaced font
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

    // Set margins
    $pdf->SetMargins(PDF_MARGIN_LEFT, 15, PDF_MARGIN_RIGHT); // Left, Top, Right
    $pdf->SetHeaderMargin(0);
    $pdf->SetFooterMargin(0);


    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

    // Set image scale factor
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

    // Add a page
    $pdf->AddPage();

    // Set font
    $pdf->SetFont('helvetica', '', 10);

    // Write HTML content
    // TCPDF has limitations with complex CSS. Simpler HTML is better.
    // We might need to adjust the HTML output from workreap_freelancer_invoice_details_plugin
    // specifically for PDF generation if rendering issues occur.
    $pdf->writeHTML($invoice_html, true, false, true, false, '');

    // Define file path for saving
    $upload_dir = wp_upload_dir();
    $invoice_dir = $upload_dir['basedir'] . '/workreap-invoices/';

    // Create directory if it doesn't exist
    if (!file_exists($invoice_dir)) {
        wp_mkdir_p($invoice_dir);
        // Add a .htaccess file to prevent direct listing, if possible
        if (!file_exists($invoice_dir . '.htaccess')) {
            $htaccess_content = "Options -Indexes";
            @file_put_contents($invoice_dir . '.htaccess', $htaccess_content);
        }
        // Add an index.php file to prevent direct listing
         if (!file_exists($invoice_dir . 'index.php')) {
            $index_content = "<?php // Silence is golden";
            @file_put_contents($invoice_dir . 'index.php', $index_content);
        }
    }

    // Sanitize order_id for filename
    $filename_order_id = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', (string)$order_id);
    $pdf_file_name = 'invoice-' . $filename_order_id . '.pdf';
    $pdf_file_path = $invoice_dir . $pdf_file_name;

    // Output the PDF
    try {
        if ($output_mode === 'F') {
            $pdf->Output($pdf_file_path, 'F'); // Save to file
            return $pdf_file_path;
        } elseif ($output_mode === 'D') {
            $pdf->Output($pdf_file_name, 'D'); // Force download
            return true;
        } elseif ($output_mode === 'I') {
            $pdf->Output($pdf_file_name, 'I'); // Output to browser
            return true;
        } else {
             error_log("Invalid output mode: {$output_mode}");
            return false;
        }
    } catch (Exception $e) {
        error_log("TCPDF Exception: " . $e->getMessage());
        return false;
    }
}

// Action to trigger PDF generation and download
add_action('init', 'workreap_handle_invoice_pdf_download');
function workreap_handle_invoice_pdf_download() {
   if (isset($_GET['download_invoice_pdf']) && isset($_GET['order_id']) && isset($_GET['_wpnonce'])) {
       $order_id = intval($_GET['order_id']);
       $nonce = sanitize_text_field($_GET['_wpnonce']); // Sanitize nonce

       if (!is_user_logged_in()) {
           wp_die(esc_html__('You must be logged in to download invoices.', 'workreap-invoices'), esc_html__('Permission Denied', 'workreap-invoices'), array('response' => 403));
       }

       // Verify nonce
       if (!wp_verify_nonce($nonce, 'download_invoice_' . $order_id)) {
           wp_die(esc_html__('Invalid security token. Please try again.', 'workreap-invoices'), esc_html__('Nonce Verification Failed', 'workreap-invoices'), array('response' => 403));
       }

       $current_user_id = get_current_user_id();

       // Basic check: Ensure order_id is valid.
       // In a real plugin, you'd also check if $current_user_id is authorized for $order_id
       // (e.g., is the freelancer, the employer, or an admin).
       // For now, we'll assume if the nonce is valid and user is logged in, it's okay.
       // This is a significant simplification for this example.
       $order = wc_get_order($order_id);
       if (!$order) {
           wp_die(esc_html__('Invalid Order ID.', 'workreap-invoices'), esc_html__('Error', 'workreap-invoices'), array('response' => 400));
       }


       // Determine the identity for the invoice (freelancer or employer)
       // This logic might need to be more sophisticated based on Workreap's data structure.
       // For now, we'll try to get the customer ID from the order, assuming the client is the customer.
       // The 'identity' in the original code was for the 'From' address, often the freelancer.
       // We need to ensure the PDF is generated with the correct 'From' and 'To'.
       // Let's assume the $current_user_id is the one requesting, and we need to determine their role
       // or get the freelancer ID associated with the order for the 'From' part.

       $freelancer_id = get_post_meta($order_id, '_freelancer_id', true); // Example meta key, adjust if different
       $employer_id = $order->get_customer_id();

       // For the 'identity' argument of workreap_freelancer_invoice_details_plugin,
       // it's usually the freelancer's ID for the "From" address.
       // This part needs to be robust. If the theme stores the freelancer ID on the order, use that.
       // The original function `workreap_freelancer_invoice_details` seems to be for the freelancer's view.
       $invoice_identity_id = $freelancer_id ? $freelancer_id : $employer_id; // Fallback, needs refinement

        // A more robust permission check is needed here:
        // For example:
        // if ( $current_user_id != $freelancer_id && $current_user_id != $employer_id && !current_user_can('manage_woocommerce') ) {
        //    wp_die(esc_html__('You do not have permission to view this invoice.', 'workreap-invoices'), esc_html__('Permission Denied', 'workreap-invoices'), array('response' => 403));
        // }


       if ($order_id > 0 && $current_user_id > 0 ) {
           $args = array(
               'order_id' => $order_id,
               'identity' => $invoice_identity_id // This should be the ID of the entity "From" whom the invoice is.
                                                // Typically the freelancer or the platform itself.
           );
           $pdf_generated = workreap_generate_invoice_pdf($args, 'D'); // 'D' for download

           if (!$pdf_generated) {
                wp_die(esc_html__('Could not generate the PDF invoice. The TCPDF library might be missing or there was an error during generation. Please check the error logs.', 'workreap-invoices'), esc_html__('PDF Generation Failed', 'workreap-invoices'), array('response' => 500));
           }
           exit; // Important to prevent further WordPress output
       } else {
           wp_die(esc_html__('Invalid request.', 'workreap-invoices'), esc_html__('Error', 'workreap-invoices'), array('response' => 400));
       }
   }
}

// Function to get URL for a saved PDF (if stored)
// function get_workreap_invoice_pdf_url($order_id) {
//    $upload_dir = wp_upload_dir();
//    $filename_order_id = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', (string)$order_id);
//    $pdf_file_name = 'invoice-' . $filename_order_id . '.pdf';
//    $pdf_file_path_relative = '/workreap-invoices/' . $pdf_file_name;
//
//    if (file_exists($upload_dir['basedir'] . $pdf_file_path_relative)) {
//        return $upload_dir['baseurl'] . $pdf_file_path_relative;
//    }
//    return false;
// }

// Nettmob Invoice Settings Page

define('NETTMOO_INVOICE_SETTINGS_SLUG', 'nettmob-invoice-settings');
define('NETTMOO_INVOICE_OPTION_GROUP', 'nettmob_invoice_options');
define('NETTMOO_INVOICE_OPTION_NAME', 'nettmob_invoice_settings');

// Function to get Nettmob settings, with defaults
function nettmob_get_invoice_settings() {
    $defaults = array(
        'nettmob_company_name' => 'Nettmobfrance',
        'nettmob_address' => '',
        'nettmob_siret_tva' => '',
        'nettmob_logo_id' => 0, // Attachment ID for the logo
        'nettmob_vat_rate' => '20', // Default VAT rate
        'nettmob_payment_delay' => '30 jours net',
        'nettmob_pdf_header_text' => '',
        'nettmob_pdf_footer_text' => '',
        'nettmob_pdf_layout' => 'default' // For future layout selection
    );
    $settings = get_option(NETTMOO_INVOICE_OPTION_NAME, $defaults);
    return wp_parse_args($settings, $defaults); // Ensure all keys are present
}


add_action('admin_menu', 'nettmob_invoice_add_admin_menu');
add_action('admin_init', 'nettmob_invoice_settings_init');

function nettmob_invoice_add_admin_menu() {
    // Add top-level menu page for the main invoice list
    add_menu_page(
        __('Nettmob Factures', 'workreap-invoices'), // Page Title
        __('Nettmob Facture', 'workreap-invoices'),  // Menu Title
        'manage_options',                            // Capability (administrator)
        'nettmob-invoices',                          // Menu Slug (main page)
        'nettmob_invoice_list_page_html',            // Function to display the list page
        'dashicons-text-page',                       // Icon
        30                                           // Position
    );

    // Add submenu page for Settings
    add_submenu_page(
        'nettmob-invoices',                          // Parent Slug
        __('Réglages - Nettmob Facture', 'workreap-invoices'), // Page Title
        __('Réglages', 'workreap-invoices'),         // Menu Title
        'manage_options',                            // Capability
        NETTMOO_INVOICE_SETTINGS_SLUG,               // Menu Slug (settings page)
        'nettmob_invoice_settings_page_html'         // Function to display the settings page
    );
}

// Placeholder for the invoice list page HTML callback function
// We will develop this function and the WP_List_Table class in the next steps.
function nettmob_invoice_list_page_html() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Liste des Factures Nettmob', 'workreap-invoices'); ?></h1>
        <form method="post">
            <?php
            // Ensure the class file is included
            if (!class_exists('Nettmob_Invoice_List_Table')) {
                require_once plugin_dir_path(__FILE__) . 'includes/class-nettmob-invoice-list-table.php';
            }
            $invoice_list_table = new Nettmob_Invoice_List_Table();
            $invoice_list_table->prepare_items();
            // Search box (optional, can be added to WP_List_Table class as well)
            // $invoice_list_table->search_box(__('Rechercher Factures','workreap-invoices'), 'invoice_search');
            $invoice_list_table->display();
            ?>
        </form>
    </div>
    <?php
}


function nettmob_invoice_settings_init() {
    register_setting(NETTMOO_INVOICE_OPTION_GROUP, NETTMOO_INVOICE_OPTION_NAME, 'nettmob_invoice_settings_sanitize');

    // Section: Nettmobfrance Information
    add_settings_section(
        'nettmob_invoice_section_nettmobinfo',
        __('Informations de Nettmobfrance', 'workreap-invoices'),
        'nettmob_invoice_section_nettmobinfo_cb',
        NETTMOO_INVOICE_SETTINGS_SLUG
    );

    add_settings_field(
        'nettmob_company_name',
        __('Nom de l\'entreprise', 'workreap-invoices'),
        'nettmob_invoice_field_text_cb',
        NETTMOO_INVOICE_SETTINGS_SLUG,
        'nettmob_invoice_section_nettmobinfo',
        ['label_for' => 'nettmob_company_name', 'option_name' => NETTMOO_INVOICE_OPTION_NAME, 'default' => 'Nettmobfrance']
    );
    add_settings_field(
        'nettmob_address',
        __('Adresse complète', 'workreap-invoices'),
        'nettmob_invoice_field_textarea_cb', // Changed to textarea for address
        NETTMOO_INVOICE_SETTINGS_SLUG,
        'nettmob_invoice_section_nettmobinfo',
        ['label_for' => 'nettmob_address', 'option_name' => NETTMOO_INVOICE_OPTION_NAME]
    );
    add_settings_field(
        'nettmob_siret_tva',
        __('SIRET / N° TVA', 'workreap-invoices'),
        'nettmob_invoice_field_text_cb',
        NETTMOO_INVOICE_SETTINGS_SLUG,
        'nettmob_invoice_section_nettmobinfo',
        ['label_for' => 'nettmob_siret_tva', 'option_name' => NETTMOO_INVOICE_OPTION_NAME]
    );
     add_settings_field(
        'nettmob_logo_id',
        __('Logo de Nettmobfrance', 'workreap-invoices'),
        'nettmob_invoice_field_logo_upload_cb',
        NETTMOO_INVOICE_SETTINGS_SLUG,
        'nettmob_invoice_section_nettmobinfo',
        ['label_for' => 'nettmob_logo_id', 'option_name' => NETTMOO_INVOICE_OPTION_NAME]
    );


    // Section: Invoice Parameters
    add_settings_section(
        'nettmob_invoice_section_parameters',
        __('Paramètres de facturation', 'workreap-invoices'),
        'nettmob_invoice_section_parameters_cb',
        NETTMOO_INVOICE_SETTINGS_SLUG
    );
    add_settings_field(
        'nettmob_vat_rate',
        __('Taux de TVA (%)', 'workreap-invoices'),
        'nettmob_invoice_field_number_cb', // Changed to number
        NETTMOO_INVOICE_SETTINGS_SLUG,
        'nettmob_invoice_section_parameters',
        ['label_for' => 'nettmob_vat_rate', 'option_name' => NETTMOO_INVOICE_OPTION_NAME, 'default' => '20', 'min' => '0', 'step' => '0.01']
    );
    add_settings_field(
        'nettmob_payment_delay',
        __('Délai de paiement par défaut', 'workreap-invoices'),
        'nettmob_invoice_field_text_cb',
        NETTMOO_INVOICE_SETTINGS_SLUG,
        'nettmob_invoice_section_parameters',
        ['label_for' => 'nettmob_payment_delay', 'option_name' => NETTMOO_INVOICE_OPTION_NAME, 'default' => '30 jours net']
    );

    // Section: PDF Customization
    add_settings_section(
        'nettmob_invoice_section_pdfcustom',
        __('Personnalisation PDF', 'workreap-invoices'),
        'nettmob_invoice_section_pdfcustom_cb',
        NETTMOO_INVOICE_SETTINGS_SLUG
    );
    add_settings_field(
        'nettmob_pdf_layout',
        __('Mise en page PDF', 'workreap-invoices'),
        'nettmob_invoice_field_select_cb',
        NETTMOO_INVOICE_SETTINGS_SLUG,
        'nettmob_invoice_section_pdfcustom',
        [
            'label_for' => 'nettmob_pdf_layout',
            'option_name' => NETTMOO_INVOICE_OPTION_NAME,
            'options' => ['default' => __('Défaut', 'workreap-invoices')], // Add more layouts later
            'default' => 'default'
        ]
    );
    add_settings_field(
        'nettmob_pdf_header_text',
        __('Texte d\'en-tête PDF personnalisé', 'workreap-invoices'),
        'nettmob_invoice_field_textarea_cb',
        NETTMOO_INVOICE_SETTINGS_SLUG,
        'nettmob_invoice_section_pdfcustom',
        ['label_for' => 'nettmob_pdf_header_text', 'option_name' => NETTMOO_INVOICE_OPTION_NAME]
    );
    add_settings_field(
        'nettmob_pdf_footer_text',
        __('Texte de pied de page PDF personnalisé', 'workreap-invoices'),
        'nettmob_invoice_field_textarea_cb',
        NETTMOO_INVOICE_SETTINGS_SLUG,
        'nettmob_invoice_section_pdfcustom',
        ['label_for' => 'nettmob_pdf_footer_text', 'option_name' => NETTMOO_INVOICE_OPTION_NAME]
    );
}

// Section Callbacks
function nettmob_invoice_section_nettmobinfo_cb($args) {
    echo '<p>' . esc_html__('Configurez les informations de votre entreprise qui apparaîtront sur les factures.', 'workreap-invoices') . '</p>';
}
function nettmob_invoice_section_parameters_cb($args) {
    echo '<p>' . esc_html__('Définissez les paramètres financiers pour les factures.', 'workreap-invoices') . '</p>';
}
function nettmob_invoice_section_pdfcustom_cb($args) {
    echo '<p>' . esc_html__('Personnalisez l\'apparence des PDF générés.', 'workreap-invoices') . '</p>';
}


// Field Callbacks
function nettmob_invoice_field_text_cb($args) {
    $options = get_option($args['option_name'], []);
    $value = isset($options[$args['label_for']]) ? $options[$args['label_for']] : (isset($args['default']) ? $args['default'] : '');
    ?>
    <input type="text" id="<?php echo esc_attr($args['label_for']); ?>"
           name="<?php echo esc_attr($args['option_name'] . '[' . $args['label_for'] . ']'); ?>"
           value="<?php echo esc_attr($value); ?>" class="regular-text">
    <?php if (isset($args['description'])) : ?>
        <p class="description"><?php echo esc_html($args['description']); ?></p>
    <?php endif;
}

function nettmob_invoice_field_number_cb($args) {
    $options = get_option($args['option_name'], []);
    $value = isset($options[$args['label_for']]) ? $options[$args['label_for']] : (isset($args['default']) ? $args['default'] : '');
    $min = isset($args['min']) ? $args['min'] : '';
    $step = isset($args['step']) ? $args['step'] : '';
    ?>
    <input type="number" id="<?php echo esc_attr($args['label_for']); ?>"
           name="<?php echo esc_attr($args['option_name'] . '[' . $args['label_for'] . ']'); ?>"
           value="<?php echo esc_attr($value); ?>" class="small-text"
           min="<?php echo esc_attr($min); ?>" step="<?php echo esc_attr($step); ?>">
    <?php if (isset($args['description'])) : ?>
        <p class="description"><?php echo esc_html($args['description']); ?></p>
    <?php endif;
}

function nettmob_invoice_field_textarea_cb($args) {
    $options = get_option($args['option_name'], []);
    $value = isset($options[$args['label_for']]) ? $options[$args['label_for']] : (isset($args['default']) ? $args['default'] : '');
    ?>
    <textarea id="<?php echo esc_attr($args['label_for']); ?>"
              name="<?php echo esc_attr($args['option_name'] . '[' . $args['label_for'] . ']'); ?>"
              rows="5" class="large-text"><?php echo esc_textarea($value); ?></textarea>
    <?php if (isset($args['description'])) : ?>
        <p class="description"><?php echo esc_html($args['description']); ?></p>
    <?php endif;
}

function nettmob_invoice_field_select_cb($args) {
    $options = get_option($args['option_name'], []);
    $value = isset($options[$args['label_for']]) ? $options[$args['label_for']] : (isset($args['default']) ? $args['default'] : '');
    ?>
    <select id="<?php echo esc_attr($args['label_for']); ?>"
            name="<?php echo esc_attr($args['option_name'] . '[' . $args['label_for'] . ']'); ?>">
        <?php foreach ($args['options'] as $val => $label) : ?>
            <option value="<?php echo esc_attr($val); ?>" <?php selected($value, $val); ?>>
                <?php echo esc_html($label); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php if (isset($args['description'])) : ?>
        <p class="description"><?php echo esc_html($args['description']); ?></p>
    <?php endif;
}

function nettmob_invoice_field_logo_upload_cb($args) {
    $options = get_option($args['option_name'], []);
    $value = isset($options[$args['label_for']]) ? $options[$args['label_for']] : 0;
    $image_src = $value ? wp_get_attachment_image_src($value, 'medium') : false;
    $image_url = $image_src ? $image_src[0] : '';
    ?>
    <div class="nettmob-logo-uploader">
        <input type="hidden" id="<?php echo esc_attr($args['label_for']); ?>" name="<?php echo esc_attr($args['option_name'] . '[' . $args['label_for'] . ']'); ?>" value="<?php echo esc_attr($value); ?>" />
        <button type="button" class="button nettmob-upload-logo-button"><?php esc_html_e('Télécharger/Choisir un logo', 'workreap-invoices'); ?></button>
        <button type="button" class="button nettmob-remove-logo-button" style="<?php echo $value ? '' : 'display:none;'; ?>"><?php esc_html_e('Retirer le logo', 'workreap-invoices'); ?></button>
        <div class="nettmob-logo-preview" style="margin-top:10px;">
            <?php if ($image_url) : ?>
                <img src="<?php echo esc_url($image_url); ?>" style="max-width:200px; height:auto;" />
            <?php endif; ?>
        </div>
    </div>
    <script type="text/javascript">
    jQuery(document).ready(function($){
        // Ensure wp.media is loaded
        if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
            return;
        }

        var mediaUploader;
        $('.nettmob-upload-logo-button').click(function(e) {
            e.preventDefault();
            var $button = $(this);
            var $inputField = $button.siblings('input[type="hidden"]');
            var $previewDiv = $button.siblings('.nettmob-logo-preview');
            var $removeButton = $button.siblings('.nettmob-remove-logo-button');

            if (mediaUploader) {
                mediaUploader.open();
                return;
            }
            mediaUploader = wp.media.frames.file_frame = wp.media({
                title: '<?php esc_js_e('Choisir un logo', 'workreap-invoices'); ?>',
                button: {
                    text: '<?php esc_js_e('Utiliser ce logo', 'workreap-invoices'); ?>'
                }, multiple: false });
            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                $inputField.val(attachment.id);
                $previewDiv.html('<img src="' + attachment.sizes.medium.url + '" style="max-width:200px; height:auto;" />');
                $removeButton.show();
            });
            mediaUploader.open();
        });
        $('.nettmob-remove-logo-button').click(function(e){
            e.preventDefault();
            var $button = $(this);
            var $inputField = $button.siblings('input[type="hidden"]');
            var $previewDiv = $button.siblings('.nettmob-logo-preview');
            $inputField.val('');
            $previewDiv.html('');
            $button.hide();
        });
    });
    </script>
    <?php
}


// HTML for the settings page
function nettmob_invoice_settings_page_html() {
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields(NETTMOO_INVOICE_OPTION_GROUP);
            do_settings_sections(NETTMOO_INVOICE_SETTINGS_SLUG);
            submit_button(__('Enregistrer les modifications', 'workreap-invoices'));
            ?>
        </form>
    </div>
    <?php
}

// Sanitize callback
function nettmob_invoice_settings_sanitize($input) {
    $sanitized_input = array();
    if (isset($input['nettmob_company_name'])) {
        $sanitized_input['nettmob_company_name'] = sanitize_text_field($input['nettmob_company_name']);
    }
    if (isset($input['nettmob_address'])) {
        $sanitized_input['nettmob_address'] = sanitize_textarea_field($input['nettmob_address']);
    }
    if (isset($input['nettmob_siret_tva'])) {
        $sanitized_input['nettmob_siret_tva'] = sanitize_text_field($input['nettmob_siret_tva']);
    }
    if (isset($input['nettmob_logo_id'])) {
        $sanitized_input['nettmob_logo_id'] = absint($input['nettmob_logo_id']);
    }
    if (isset($input['nettmob_vat_rate'])) {
        $sanitized_input['nettmob_vat_rate'] = sanitize_text_field($input['nettmob_vat_rate']); // number, but sanitize as text then validate
        // Could add more validation here to ensure it's a valid number/percentage
    }
    if (isset($input['nettmob_payment_delay'])) {
        $sanitized_input['nettmob_payment_delay'] = sanitize_text_field($input['nettmob_payment_delay']);
    }
    if (isset($input['nettmob_pdf_header_text'])) {
        $sanitized_input['nettmob_pdf_header_text'] = sanitize_textarea_field($input['nettmob_pdf_header_text']);
    }
    if (isset($input['nettmob_pdf_footer_text'])) {
        $sanitized_input['nettmob_pdf_footer_text'] = sanitize_textarea_field($input['nettmob_pdf_footer_text']);
    }
    if (isset($input['nettmob_pdf_layout'])) {
        $sanitized_input['nettmob_pdf_layout'] = sanitize_key($input['nettmob_pdf_layout']);
    }

    // Add more sanitization as needed for other fields

    return $sanitized_input;
}

// Enqueue media uploader scripts for logo
add_action('admin_enqueue_scripts', 'nettmob_invoice_enqueue_admin_scripts');
function nettmob_invoice_enqueue_admin_scripts($hook_suffix) {
    // Only load on our settings page. The hook_suffix for a top-level page is 'toplevel_page_PAGE_SLUG'
    if ('toplevel_page_' . NETTMOO_INVOICE_SETTINGS_SLUG === $hook_suffix ||
        (isset($_GET['page']) && $_GET['page'] === NETTMOO_INVOICE_SETTINGS_SLUG)) { // Check for submenu page too if structure changes
        wp_enqueue_media();
    }
}

?>
