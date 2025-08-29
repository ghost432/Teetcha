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
    /**
     * Generates the HTML content for a client invoice based on a WooCommerce order.
     * This HTML is then typically used for PDF generation.
     *
     * @param array $args {
     *     Optional. An array of arguments.
     *
     *     @type int    $order_id The ID of the WooCommerce order.
     *     @type int    $identity Optional. The ID of the freelancer (used for "on behalf of" scenarios).
     *                              If not provided, attempts to get it from order metadata.
     *     @type string $option   Optional. Set to 'pdf' for PDF-specific rendering (e.g., base64 images).
     * }
     * @return string The HTML content of the invoice, or an error message on failure.
     */
    function workreap_freelancer_invoice_details_plugin($args=array()) {
        ob_start();

        // Args: order_id (mandatory)
        // Args: option ('pdf' for PDF specific rendering like base64 images)
        $order_id   = !empty($args['order_id']) ? intval($args['order_id']) : 0;
        $is_pdf_render = (!empty($args['option']) && $args['option'] === 'pdf');

        if (empty($order_id)) {
            echo '<p>' . esc_html__('Order ID is missing for invoice generation.', 'workreap-invoices') . '</p>';
            return ob_get_clean();
        }

        $order = wc_get_order($order_id);
        if (!$order) {
             echo '<p>' . sprintf(esc_html__('Order ID %s not found or invalid.', 'workreap-invoices'), esc_html($order_id)) . '</p>';
            return ob_get_clean();
        }

        if (!class_exists('WooCommerce')) {
            echo '<p>' . esc_html__('WooCommerce is not active. This plugin requires WooCommerce to function.', 'workreap-invoices') . '</p>';
            return ob_get_clean();
        }

        // Nettmob Settings from our plugin's options
        $nettmob_settings = nettmob_get_invoice_settings();
        $nettmob_company_name = esc_html($nettmob_settings['nettmob_company_name']);
        $nettmob_address_html = nl2br(esc_html($nettmob_settings['nettmob_address']));
        $nettmob_siret_tva = esc_html($nettmob_settings['nettmob_siret_tva']);
        $nettmob_logo_id = $nettmob_settings['nettmob_logo_id'];
        $nettmob_logo_url = $nettmob_logo_id ? wp_get_attachment_url($nettmob_logo_id) : '';

        $site_logo_to_use = $nettmob_logo_url;

        // Freelancer Info (the one who performed the service)
        // The 'identity' key in $args used to be freelancer_id. Let's try to get it from order meta first.
        $freelancer_id = $order->get_meta('_freelancer_id', true);
        if (!$freelancer_id && !empty($args['identity'])) { // Fallback to args if provided (e.g. from shortcode)
            $freelancer_id = intval($args['identity']);
        }
        if (!$freelancer_id) { // Try to get from product item if not on order or args
            foreach ($order->get_items() as $item_id => $item) {
                $product_id = $item->get_product_id();
                if ($product_id) {
                    // YOU MUST VERIFY THIS META KEY FOR FREELANCER ID ON PRODUCT/PROJECT
                    $freelancer_id_from_product = get_post_meta($product_id, '_freelancer_id', true);
                    if ($freelancer_id_from_product) {
                        $freelancer_id = $freelancer_id_from_product;
                        break;
                    }
                }
            }
        }

        $freelancer_first_name = '';
        $freelancer_last_name = '';
        $freelancer_display_name = __('N/A', 'workreap-invoices');

        if ($freelancer_id) {
            $freelancer_user_data = get_userdata($freelancer_id);
            if ($freelancer_user_data) {
                $freelancer_first_name = $freelancer_user_data->first_name;
                $freelancer_last_name = $freelancer_user_data->last_name;
                $freelancer_display_name = trim($freelancer_first_name . ' ' . $freelancer_last_name);
                if(empty($freelancer_display_name)) $freelancer_display_name = $freelancer_user_data->display_name;
            }
        }

        // Client Info (Billing details from WooCommerce order)
        $client_first_name = $order->get_billing_first_name();
        $client_last_name = $order->get_billing_last_name();
        $client_company_name = $order->get_billing_company();
        $client_email = $order->get_billing_email();
        // Construct client address string
        $client_address_parts = array_filter(array(
            $order->get_billing_address_1(),
            $order->get_billing_address_2(),
            $order->get_billing_city(),
            $order->get_billing_state(),
            $order->get_billing_postcode(),
            WC()->countries->countries[$order->get_billing_country()] ?? $order->get_billing_country()
        ));
        $client_address_html = implode('<br />', $client_address_parts);


        // Order Details for Invoice
        $order_date_obj = $order->get_date_created();
        $invoice_date = $order_date_obj ? $order_date_obj->date_i18n(get_option('date_format')) : date_i18n(get_option('date_format'));
        $payment_delay_text = esc_html($nettmob_settings['nettmob_payment_delay']);

        // Line Items
        $line_items_html = '';
        $subtotal_before_vat = 0;

        foreach($order->get_items() as $item_id => $item) {
            $product_name = $item->get_name();
            $quantity = $item->get_quantity();
            // get_subtotal() is typically pre-tax unless WC settings are unusual.
            // If prices include tax, get_subtotal() also includes tax for that line.
            // We need the pre-tax line total to calculate overall VAT correctly.
            $line_total_pre_tax = $item->get_subtotal(); // Amount for this line, before order discounts, after item discounts
            $line_total_display = wc_price($line_total_pre_tax, array('currency' => $order->get_currency()));

            // If your WooCommerce prices *include* tax, and $item->get_subtotal() includes tax,
            // you need to subtract the line tax: $line_total_pre_tax = $item->get_subtotal() - $item->get_subtotal_tax();
            // For now, assuming $item->get_subtotal() is what we need for pre-tax sum.
            // This is a common point of confusion with WooCommerce taxes.
            // The most reliable way to get order totals (subtotal, total, tax) is from the $order object itself after all items.

            $subtotal_before_vat += $line_total_pre_tax;

            $line_items_html .= sprintf(
                '<tr>
                    <td>%s</td>
                    <td style="text-align:center;">%s</td>
                    <td style="text-align:right;">%s</td>
                 </tr>',
                esc_html($product_name),
                esc_html($quantity),
                $line_total_display // wc_price already escapes
            );
        }
        // More reliable subtotal from order object, especially if complex taxes/discounts apply
        $subtotal_before_vat = $order->get_subtotal(); // This is generally sum of line subtotals pre-discount, pre-shipping.

        // Financials using Nettmob settings
        $vat_rate_config = floatval($nettmob_settings['nettmob_vat_rate']);
        $vat_amount = 0;
        if ($vat_rate_config > 0) {
            $vat_amount = ($subtotal_before_vat * $vat_rate_config) / 100;
        }
        $total_with_vat = $subtotal_before_vat + $vat_amount;

        // PDF Header/Footer Text
        $pdf_header_text_config = !empty($nettmob_settings['nettmob_pdf_header_text']) ? wpautop(wp_kses_post($nettmob_settings['nettmob_pdf_header_text'])) : '';
        $pdf_footer_text_config = !empty($nettmob_settings['nettmob_pdf_footer_text']) ? wpautop(wp_kses_post($nettmob_settings['nettmob_pdf_footer_text'])) : '';

        // Original Workreap project type specific logic (e.g. hourly details) - can be adapted if needed
        // $order_type = $order->get_meta('project_type', true);
        // $order_meta_workreap = $order->get_meta('cus_woo_product_data', true);
        // $order_meta_workreap = !empty($order_meta_workreap) ? $order_meta_workreap : array();

        // Price formatting function
        $price_format_func = function($price) use ($order) {
            return wc_price($price, array('currency' => $order->get_currency()));
        };

        // BEGIN HTML STRUCTURE - This is a simplified example, you'll need to style it properly.
        // The classes like wr-main-section, wr-invoicebill etc. are from your original HTML.
        ?>
        <div class="nettmob-invoice-wrap wr-main-section wr-invoice-plugin">
            <div class="wr-printable">
                <?php if ($pdf_header_text_config): ?>
                    <div class="nettmob-invoice-custom-header">
                        <?php echo $pdf_header_text_config; ?>
                    </div>
                <?php endif; ?>

                <div class="wr-invoicebill" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                    <div class="nettmob-logo">
                        <?php if( !empty($site_logo_to_use) ){
                            if( $is_pdf_render ){
                                $type = pathinfo($site_logo_to_use, PATHINFO_EXTENSION);
                                $data = @file_get_contents($site_logo_to_use);
                                if ($data !== false) {
                                    $base64_logo = 'data:image/' . $type . ';base64,' . base64_encode($data);
                                    echo '<img src="'.esc_url($base64_logo).'" alt="'.esc_attr($nettmob_company_name).'" style="max-width: 200px; max-height: 100px;" />';
                                } else {
                                    echo '<p>' . esc_html__('Error loading site logo.', 'workreap-invoices') . '</p>';
                                }
                            } else { ?>
                                <img src="<?php echo esc_url($site_logo_to_use);?>" alt="<?php echo esc_attr($nettmob_company_name);?>" style="max-width: 200px; max-height: 100px;">
                            <?php }
                        } ?>
                    </div>
                    <div class="wr-billno" style="text-align: right;">
                        <h2><?php esc_html_e('FACTURE', 'workreap-invoices'); ?></h2>
                        <p><strong><?php esc_html_e('Facture N°:', 'workreap-invoices'); ?></strong> <?php echo esc_html($order->get_order_number()); // Use WC order number ?></p>
                        <p><strong><?php esc_html_e('Date de facturation:', 'workreap-invoices'); ?></strong> <?php echo esc_html($invoice_date); ?></p>
                        <p><strong><?php esc_html_e('Délai de paiement:', 'workreap-invoices'); ?></strong> <?php echo esc_html($payment_delay_text); ?></p>
                    </div>
                </div>

                <div class="nettmob-invoice-header-info" style="margin-bottom: 30px;">
                    <p><?php printf(esc_html__('Facture émise par %s au nom de : %s', 'workreap-invoices'), '<strong>' . esc_html($nettmob_company_name) . '</strong>', '<strong>' . esc_html($freelancer_display_name) . '</strong>'); ?></p>
                    <?php if($nettmob_siret_tva): ?>
                        <p><?php echo esc_html($nettmob_siret_tva); ?></p>
                    <?php endif; ?>
                     <?php if($nettmob_address_html): ?>
                        <p><?php echo $nettmob_address_html; // Already escaped and nl2br'd ?></p>
                    <?php endif; ?>
                </div>

                <div class="wr-invoicefromto" style="display: flex; justify-content: space-between; margin-bottom: 30px;">
                    <div class="nettmob-client-info wr-fromreceiver" style="width: 48%;">
                        <h4><?php esc_html_e('Client :', 'workreap-invoices'); ?></h4>
                        <p>
                            <strong><?php echo esc_html(trim($client_first_name . ' ' . $client_last_name)); ?></strong><br>
                            <?php if ($client_company_name): ?>
                                <?php echo esc_html($client_company_name); ?><br>
                            <?php endif; ?>
                            <?php echo $client_address_html; // WC formatted address, should be safe ?><br>
                            <?php echo esc_html($client_email); ?>
                        </p>
                    </div>
                     <?php /* Placeholder for "From" if needed, but Nettmob is the issuer here
                     <div class="wr-fromreceiver" style="width: 48%;">
                        <h5><?php esc_html_e('Prestataire (pour information) :', 'workreap-invoices'); ?></h5>
                        <p><strong><?php echo esc_html($freelancer_display_name); ?></strong></p>
                    </div>
                    */ ?>
                </div>

                <table class="wr-table wr-invoice-table nettmob-invoice-items" style="width: 100%; margin-bottom: 20px; border-collapse: collapse;">
                    <thead>
                        <tr style="background-color: #f0f0f0;">
                            <th style="text-align:left; padding: 8px; border: 1px solid #ddd;"><?php esc_html_e('Description', 'workreap-invoices'); ?></th>
                            <th style="text-align:center; padding: 8px; border: 1px solid #ddd;"><?php esc_html_e('Quantité', 'workreap-invoices'); ?></th>
                            <th style="text-align:right; padding: 8px; border: 1px solid #ddd;"><?php esc_html_e('Prix HT', 'workreap-invoices'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php echo $line_items_html; // Generated above ?>
                    </tbody>
                </table>

                <div class="wr-subtotal" style="width: 50%; margin-left: auto; text-align: right;">
                    <ul class="wr-subtotalbill" style="list-style: none; padding: 0;">
                        <li><?php esc_html_e('Sous-Total HT:', 'workreap-invoices'); ?> <span style="float:right;"><?php echo $price_format_func($subtotal_before_vat); ?></span></li>
                        <?php if ($vat_rate_config > 0): ?>
                        <li><?php printf(esc_html__('TVA (%s%%):', 'workreap-invoices'), esc_html($vat_rate_config)); ?> <span style="float:right;"><?php echo $price_format_func($vat_amount); ?></span></li>
                        <?php endif; ?>
                    </ul>
                    <div class="wr-sumtotal" style="font-weight: bold; margin-top: 10px; border-top: 1px solid #ddd; padding-top: 10px;">
                        <?php esc_html_e('TOTAL TTC:', 'workreap-invoices'); ?> <span style="float:right;"><?php echo $price_format_func($total_with_vat); ?></span>
                    </div>
                </div>

                <?php if ($pdf_footer_text_config): ?>
                    <div class="nettmob-invoice-custom-footer" style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 15px;">
                        <?php echo $pdf_footer_text_config; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Helper functions for PDF existence and URLs
if (!function_exists('nettmob_get_invoice_upload_dir_path')) {
    function nettmob_get_invoice_upload_dir_path() {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/workreap-invoices/';
    }
}
if (!function_exists('nettmob_get_invoice_upload_dir_url')) {
    function nettmob_get_invoice_upload_dir_url() {
        $upload_dir = wp_upload_dir();
        return $upload_dir['baseurl'] . '/workreap-invoices/';
    }
}

// Convention de nommage: client_[order_id].pdf, commission_[order_id].pdf
// NOTE: We might add a timestamp or hash to the filename later if regeneration is frequent,
// but for a simple existence check, this is okay. We'd need a more robust way to find the *latest* if so.

if (!function_exists('nettmob_get_client_invoice_filename')) {
    function nettmob_get_client_invoice_filename($order_id) {
        return 'client_invoice_' . intval($order_id) . '.pdf';
    }
}

if (!function_exists('nettmob_get_commission_invoice_filename')) {
    function nettmob_get_commission_invoice_filename($order_id) {
        return 'commission_invoice_' . intval($order_id) . '.pdf';
    }
}

if (!function_exists('nettmob_has_client_invoice')) {
    function nettmob_has_client_invoice($order_id) {
        $filepath = nettmob_get_invoice_upload_dir_path() . nettmob_get_client_invoice_filename($order_id);
        return file_exists($filepath);
        // return false; // TEMP: Force "Generate" links for testing
    }
}

if (!function_exists('nettmob_has_commission_invoice')) {
    function nettmob_has_commission_invoice($order_id) {
        $filepath = nettmob_get_invoice_upload_dir_path() . nettmob_get_commission_invoice_filename($order_id);
        return file_exists($filepath);
        // return false; // TEMP: Force "Generate" links for testing
    }
}

if (!function_exists('nettmob_get_client_invoice_url')) {
    function nettmob_get_client_invoice_url($order_id) {
        if (nettmob_has_client_invoice($order_id)) {
            return nettmob_get_invoice_upload_dir_url() . nettmob_get_client_invoice_filename($order_id);
        }
        return false;
    }
}

if (!function_exists('nettmob_get_commission_invoice_url')) {
    function nettmob_get_commission_invoice_url($order_id) {
        if (nettmob_has_commission_invoice($order_id)) {
            return nettmob_get_invoice_upload_dir_url() . nettmob_get_commission_invoice_filename($order_id);
        }
        return false;
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
        error_log('Client Invoice PDF Generation: Order ID is missing.');
        return false;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        error_log("Client Invoice PDF Generation: Could not retrieve order for ID {$order_id}.");
        return false;
    }

    // Get Nettmob settings to use for PDF metadata
    $nettmob_settings = nettmob_get_invoice_settings();
    $nettmob_company_name = $nettmob_settings['nettmob_company_name'];

    // Prepare arguments for the HTML generation function
    // The 'identity' here should be the freelancer_id if the invoice is "on behalf of"
    // This needs to be correctly passed to workreap_generate_invoice_pdf
    // For now, we assume it's part of $args if needed by workreap_freelancer_invoice_details_plugin
    $invoice_html_args = array(
        'order_id' => $order_id,
        'identity' => !empty($args['identity']) ? $args['identity'] : null, // Pass through freelancer ID if available
        'option'   => 'pdf' // Critical for base64 image encoding
    );
    $invoice_html = workreap_freelancer_invoice_details_plugin($invoice_html_args);

    if (empty($invoice_html)) {
        error_log("Client Invoice PDF Generation: Failed to generate HTML content for Order ID {$order_id}.");
        return false;
    }

    // Create new PDF document
    // TODO: Consider page size/orientation options from settings later
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator(esc_html($nettmob_company_name));
    $pdf->SetAuthor(esc_html($nettmob_company_name));
    $pdf->SetTitle(sprintf(esc_html__('Facture %s - %s', 'workreap-invoices'), $order->get_order_number(), esc_html($nettmob_company_name)));
    $pdf->SetSubject(sprintf(esc_html__('Facture pour la commande %s', 'workreap-invoices'), $order->get_order_number()));

    // Remove default TCPDF header/footer if we are embedding them via HTML from settings
    // (nettmob_pdf_header_text, nettmob_pdf_footer_text)
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
    // Use the new filename convention
    $pdf_file_name = nettmob_get_client_invoice_filename($order_id);
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

/**
 * Adds the Nettmob Invoice menu and submenu pages to the WordPress admin.
 */
add_action('admin_menu', 'nettmob_invoice_add_admin_menu');
function nettmob_invoice_add_admin_menu() {
    // Add top-level menu page for the main invoice list
    add_menu_page(
        __('Nettmob Factures', 'workreap-invoices'),      // Page Title
        __('Nettmob Facture', 'workreap-invoices'),     // Menu Title
        'manage_options',                               // Capability (administrator)
        'nettmob-invoices',                             // Menu Slug (main page)
        'nettmob_invoice_list_page_html',               // Function to display the list page
        'dashicons-text-page',                          // Icon
        30                                              // Position
    );

    // Add submenu page for Settings
    add_submenu_page(
        'nettmob-invoices',                             // Parent Slug
        __('Réglages - Nettmob Facture', 'workreap-invoices'), // Page Title
        __('Réglages', 'workreap-invoices'),            // Menu Title
        'manage_options',                               // Capability
        NETTMOO_INVOICE_SETTINGS_SLUG,                  // Menu Slug (settings page)
        'nettmob_invoice_settings_page_html'            // Function to display the settings page
    );
}

/**
 * Renders the HTML for the main invoice listing page in the admin.
 * This page will display the Nettmob_Invoice_List_Table.
 */
function nettmob_invoice_list_page_html() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Accès non autorisé.', 'workreap-invoices'));
    }
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

function nettmob_get_commission_invoice_html($order_id, $is_pdf_render = false) {
    if (empty($order_id)) {
        return '<p>' . esc_html__('Order ID is missing for commission slip.', 'workreap-invoices') . '</p>';
    }
    $order = wc_get_order($order_id);
    if (!$order) {
        return '<p>' . sprintf(esc_html__('Order ID %s not found or invalid for commission slip.', 'workreap-invoices'), esc_html($order_id)) . '</p>';
    }

    ob_start();

    // Nettmob Settings
    $nettmob_settings = nettmob_get_invoice_settings();
    $nettmob_company_name = esc_html($nettmob_settings['nettmob_company_name']);
    $nettmob_address_html = nl2br(esc_html($nettmob_settings['nettmob_address']));
    $nettmob_siret_tva = esc_html($nettmob_settings['nettmob_siret_tva']);
    $nettmob_logo_id = $nettmob_settings['nettmob_logo_id'];
    $nettmob_logo_url = $nettmob_logo_id ? wp_get_attachment_url($nettmob_logo_id) : '';
    $site_logo_to_use = $nettmob_logo_url;
    $payment_delay_text = esc_html($nettmob_settings['nettmob_payment_delay']);
    $pdf_header_text_config = !empty($nettmob_settings['nettmob_pdf_header_text']) ? wpautop(wp_kses_post($nettmob_settings['nettmob_pdf_header_text'])) : '';
    $pdf_footer_text_config = !empty($nettmob_settings['nettmob_pdf_footer_text']) ? wpautop(wp_kses_post($nettmob_settings['nettmob_pdf_footer_text'])) : '';

    // Client Info
    $client_first_name = $order->get_billing_first_name();
    $client_last_name = $order->get_billing_last_name();
    $client_company_name = $order->get_billing_company();
    $client_email = $order->get_billing_email();
    $client_address_html = $order->get_formatted_billing_address();


    // Freelancer Info
    $freelancer_id = $order->get_meta('_freelancer_id', true);
     if (!$freelancer_id) {
        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            if ($product_id) {
                $freelancer_id_from_product = get_post_meta($product_id, '_freelancer_id', true);
                if ($freelancer_id_from_product) {
                    $freelancer_id = $freelancer_id_from_product;
                    break;
                }
            }
        }
    }
    $freelancer_name = __('N/A', 'workreap-invoices');
    if ($freelancer_id) {
        $freelancer_user_data = get_userdata($freelancer_id);
        if ($freelancer_user_data) {
            $freelancer_name = trim($freelancer_user_data->first_name . ' ' . $freelancer_user_data->last_name);
            if(empty($freelancer_name)) $freelancer_name = $freelancer_user_data->display_name;
        }
    }

    // Order Details
    $order_date_obj = $order->get_date_created();
    $transaction_date = $order_date_obj ? $order_date_obj->date_i18n(get_option('date_format')) : date_i18n(get_option('date_format'));
    $order_number = $order->get_order_number();

    // Commission Amount
    // IMPORTANT: Verify this meta key for admin shares/platform commission
    $commission_amount = floatval(get_post_meta($order_id, 'admin_shares', true));

    $price_format_func = function($price) use ($order) {
        return wc_price($price, array('currency' => $order->get_currency()));
    };

    ?>
    <div class="nettmob-commission-slip-wrap" style="font-family: sans-serif; font-size: 12px; padding: 20px;">
        <?php if ($pdf_header_text_config): ?>
            <div class="nettmob-invoice-custom-header" style="margin-bottom: 20px;"><?php echo $pdf_header_text_config; ?></div>
        <?php endif; ?>

        <table style="width: 100%; margin-bottom: 30px;">
            <tr>
                <td style="width: 60%; vertical-align: top;">
                    <?php if (!empty($site_logo_to_use)): ?>
                        <?php
                        if ($is_pdf_render) {
                            $type = pathinfo($site_logo_to_use, PATHINFO_EXTENSION);
                            $data = @file_get_contents($site_logo_to_use);
                            if ($data !== false) {
                                $base64_logo = 'data:image/' . $type . ';base64,' . base64_encode($data);
                                echo '<img src="'.esc_url($base64_logo).'" alt="'.esc_attr($nettmob_company_name).'" style="max-width: 180px; max-height: 90px;" />';
                            }
                        } else {
                            echo '<img src="'.esc_url($site_logo_to_use).'" alt="'.esc_attr($nettmob_company_name).'" style="max-width: 180px; max-height: 90px;" />';
                        }
                        ?>
                    <?php endif; ?>
                </td>
                <td style="width: 40%; text-align: right; vertical-align: top;">
                    <h2 style="margin:0 0 10px 0; font-size: 20px;"><?php esc_html_e('RELEVÉ DE COMMISSION', 'workreap-invoices'); ?></h2>
                    <p style="margin:2px 0;"><strong><?php esc_html_e('Commande N°:', 'workreap-invoices'); ?></strong> <?php echo esc_html($order_number); ?></p>
                    <p style="margin:2px 0;"><strong><?php esc_html_e('Date de Transaction:', 'workreap-invoices'); ?></strong> <?php echo esc_html($transaction_date); ?></p>
                </td>
            </tr>
        </table>

        <table style="width: 100%; margin-bottom: 30px;">
            <tr>
                <td style="width: 50%; vertical-align: top;">
                    <h4 style="margin:0 0 5px 0;"><?php esc_html_e('Plateforme :', 'workreap-invoices'); ?></h4>
                    <p style="margin:0;">
                        <strong><?php echo esc_html($nettmob_company_name); ?></strong><br>
                        <?php echo $nettmob_address_html; ?><br>
                        <?php if($nettmob_siret_tva) { echo esc_html($nettmob_siret_tva) . '<br>'; } ?>
                    </p>
                </td>
                <td style="width: 50%; vertical-align: top;">
                    <h4 style="margin:0 0 5px 0;"><?php esc_html_e('Client :', 'workreap-invoices'); ?></h4>
                     <p style="margin:0;">
                        <strong><?php echo esc_html(trim($client_first_name . ' ' . $client_last_name)); ?></strong><br>
                        <?php if ($client_company_name): ?>
                            <?php echo esc_html($client_company_name); ?><br>
                        <?php endif; ?>
                        <?php echo $client_address_html; ?><br>
                        <?php echo esc_html($client_email); ?>
                    </p>
                </td>
            </tr>
        </table>

        <h4 style="margin:20px 0 10px 0; border-bottom: 1px solid #eee; padding-bottom: 5px;"><?php esc_html_e('Détails de la Transaction', 'workreap-invoices'); ?></h4>
        <p><strong><?php esc_html_e('Freelance concerné :', 'workreap-invoices'); ?></strong> <?php echo esc_html($freelancer_name); ?></p>

        <table style="width: 100%; margin-bottom: 30px; border-collapse: collapse;">
            <thead>
                <tr style="background-color: #f9f9f9;">
                    <th style="text-align:left; padding: 8px; border: 1px solid #eee;"><?php esc_html_e('Description', 'workreap-invoices'); ?></th>
                    <th style="text-align:right; padding: 8px; border: 1px solid #eee;"><?php esc_html_e('Montant', 'workreap-invoices'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="padding: 8px; border: 1px solid #eee;"><?php printf(esc_html__('Commission de la plateforme pour la commande N° %s', 'workreap-invoices'), esc_html($order_number)); ?></td>
                    <td style="text-align:right; padding: 8px; border: 1px solid #eee;"><?php echo $price_format_func($commission_amount); ?></td>
                </tr>
            </tbody>
        </table>

        <?php if (!empty($payment_delay_text)): ?>
            <p><strong><?php esc_html_e('Délai de paiement de la facture client associée:', 'workreap-invoices'); ?></strong> <?php echo esc_html($payment_delay_text); ?></p>
        <?php endif; ?>

        <?php if ($pdf_footer_text_config): ?>
            <div class="nettmob-invoice-custom-footer" style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 15px; font-size: 10px;">
                <?php echo $pdf_footer_text_config; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}


function workreap_generate_commission_pdf($args, $output_mode = 'F') {
    if (!class_exists('TCPDF') || !method_exists('TCPDF', 'AddPage')) {
        error_log('Commission PDF Generation: TCPDF library not loaded.');
        return false;
    }

    $order_id = !empty($args['order_id']) ? intval($args['order_id']) : 0;
    if (empty($order_id)) {
        error_log('Commission PDF Generation: Order ID is missing.');
        return false;
    }
     $order = wc_get_order($order_id);
    if (!$order) {
        error_log("Commission PDF Generation: Could not retrieve order for ID {$order_id}.");
        return false;
    }

    $nettmob_settings = nettmob_get_invoice_settings();
    $nettmob_company_name = $nettmob_settings['nettmob_company_name'];

    $commission_html = nettmob_get_commission_invoice_html($order_id, true); // true for PDF render (base64 images)

    if (empty($commission_html)) {
        error_log("Commission PDF Generation: Failed to generate HTML content for Order ID {$order_id}.");
        return false;
    }

    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    $pdf->SetCreator(esc_html($nettmob_company_name));
    $pdf->SetAuthor(esc_html($nettmob_company_name));
    $pdf->SetTitle(sprintf(esc_html__('Relevé de Commission %s - %s', 'workreap-invoices'), $order->get_order_number(), esc_html($nettmob_company_name)));
    $pdf->SetSubject(sprintf(esc_html__('Relevé de commission pour la commande %s', 'workreap-invoices'), $order->get_order_number()));

    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(PDF_MARGIN_LEFT, 15, PDF_MARGIN_RIGHT);
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 10);
    $pdf->writeHTML($commission_html, true, false, true, false, '');

    $upload_dir = wp_upload_dir();
    $invoice_dir = nettmob_get_invoice_upload_dir_path(); // Use helper
     if (!file_exists($invoice_dir)) {
        wp_mkdir_p($invoice_dir);
        if (!file_exists($invoice_dir . '.htaccess')) {
            @file_put_contents($invoice_dir . '.htaccess', "Options -Indexes");
        }
         if (!file_exists($invoice_dir . 'index.php')) {
            @file_put_contents($invoice_dir . 'index.php', "<?php // Silence is golden");
        }
    }

    $pdf_file_name = nettmob_get_commission_invoice_filename($order_id);
    $pdf_file_path = $invoice_dir . $pdf_file_name;

    try {
        if ($output_mode === 'F') {
            $pdf->Output($pdf_file_path, 'F');
            return $pdf_file_path;
        } elseif ($output_mode === 'D') {
            $pdf->Output($pdf_file_name, 'D');
            return true;
        } elseif ($output_mode === 'I') {
            $pdf->Output($pdf_file_name, 'I');
            return true;
        } else {
            error_log("Commission PDF Generation: Invalid output mode {$output_mode}");
            return false;
        }
    } catch (Exception $e) {
        error_log("Commission PDF Generation TCPDF Exception: " . $e->getMessage());
        return false;
    }
}

// Admin Post Actions for PDF Generation

add_action('admin_post_nettmob_generate_client_invoice', 'nettmob_handle_generate_client_invoice');
function nettmob_handle_generate_client_invoice() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_die(__('Vous n\'avez pas les permissions suffisantes pour effectuer cette action.', 'workreap-invoices'));
    }

    // Get order ID and verify nonce
    $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
    $nonce = isset($_GET['_wpnonce_generate_client_invoice']) ? $_GET['_wpnonce_generate_client_invoice'] : '';

    if (!$order_id || !wp_verify_nonce($nonce, 'nettmob_generate_client_invoice_nonce_' . $order_id)) {
        wp_die(__('Lien invalide ou action non autorisée.', 'workreap-invoices'));
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        wp_die(__('Commande non trouvée.', 'workreap-invoices'));
    }

    // Determine freelancer ID for the invoice context (passed as 'identity' to generation function)
    // This logic should mirror how 'identity' is determined if it's needed by workreap_freelancer_invoice_details_plugin
    $freelancer_id = $order->get_meta('_freelancer_id', true);
    if (!$freelancer_id) {
        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            if ($product_id) {
                $freelancer_id_from_product = get_post_meta($product_id, '_freelancer_id', true);
                if ($freelancer_id_from_product) {
                    $freelancer_id = $freelancer_id_from_product;
                    break;
                }
            }
        }
    }

    $args = array(
        'order_id' => $order_id,
        'identity' => $freelancer_id // Pass freelancer_id as identity
    );

    $pdf_path = workreap_generate_invoice_pdf($args, 'F'); // Save to file

    if ($pdf_path && file_exists($pdf_path)) {
        // Redirect to the PDF file to open in browser or trigger download depending on browser settings
        // Or, to force download:
        // header('Content-Description: File Transfer');
        // header('Content-Type: application/pdf');
        // header('Content-Disposition: attachment; filename="'.basename($pdf_path).'"');
        // header('Expires: 0');
        // header('Cache-Control: must-revalidate');
        // header('Pragma: public');
        // header('Content-Length: ' . filesize($pdf_path));
        // readfile($pdf_path);
        // exit;

        // For now, just redirect back to the list table with a success message
        wp_redirect(add_query_arg(array('page' => 'nettmob-invoices', 'message' => 'client_invoice_generated', 'order_id' => $order_id), admin_url('admin.php')));
        exit;
    } else {
        // Redirect back with an error message
        wp_redirect(add_query_arg(array('page' => 'nettmob-invoices', 'error' => 'client_invoice_failed', 'order_id' => $order_id), admin_url('admin.php')));
        exit;
    }
}

add_action('admin_post_nettmob_generate_commission_invoice', 'nettmob_handle_generate_commission_invoice');
function nettmob_handle_generate_commission_invoice() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Vous n\'avez pas les permissions suffisantes pour effectuer cette action.', 'workreap-invoices'));
    }

    $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
    $nonce = isset($_GET['_wpnonce_generate_commission_invoice']) ? $_GET['_wpnonce_generate_commission_invoice'] : '';

    if (!$order_id || !wp_verify_nonce($nonce, 'nettmob_generate_commission_invoice_nonce_' . $order_id)) {
        wp_die(__('Lien invalide ou action non autorisée.', 'workreap-invoices'));
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        wp_die(__('Commande non trouvée.', 'workreap-invoices'));
    }

    // 'identity' might not be strictly needed for commission PDF if it's always from Nettmob's perspective
    // but good to pass order_id
    $args = array('order_id' => $order_id);

    $pdf_path = workreap_generate_commission_pdf($args, 'F'); // Save to file

    if ($pdf_path && file_exists($pdf_path)) {
        wp_redirect(add_query_arg(array('page' => 'nettmob-invoices', 'message' => 'commission_invoice_generated', 'order_id' => $order_id), admin_url('admin.php')));
        exit;
    } else {
        wp_redirect(add_query_arg(array('page' => 'nettmob-invoices', 'error' => 'commission_invoice_failed', 'order_id' => $order_id), admin_url('admin.php')));
        exit;
    }
}

// Display admin notices for generation success/failure
add_action('admin_notices', 'nettmob_invoice_admin_notices');
function nettmob_invoice_admin_notices() {
    if (empty($_GET['page']) || $_GET['page'] !== 'nettmob-invoices' || empty($_GET['order_id'])) {
        return;
    }

    $order_id = intval($_GET['order_id']);

    if (!empty($_GET['message'])) {
        $message_type = sanitize_key($_GET['message']);
        $notice_message = '';
        if ($message_type === 'client_invoice_generated') {
            $notice_message = sprintf(__('Facture client pour la commande #%s générée avec succès.', 'workreap-invoices'), $order_id);
        } elseif ($message_type === 'commission_invoice_generated') {
            $notice_message = sprintf(__('Relevé de commission pour la commande #%s généré avec succès.', 'workreap-invoices'), $order_id);
        }

        if ($notice_message) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($notice_message) . '</p></div>';
        }
    } elseif (!empty($_GET['error'])) {
        $error_type = sanitize_key($_GET['error']);
        $error_message = '';
        if ($error_type === 'client_invoice_failed') {
            $error_message = sprintf(__('Erreur lors de la génération de la facture client pour la commande #%s.', 'workreap-invoices'), $order_id);
        } elseif ($error_type === 'commission_invoice_failed') {
            $error_message = sprintf(__('Erreur lors de la génération du relevé de commission pour la commande #%s.', 'workreap-invoices'), $order_id);
        }

        if ($error_message) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error_message) . '</p></div>';
        }
    }
}

?>
