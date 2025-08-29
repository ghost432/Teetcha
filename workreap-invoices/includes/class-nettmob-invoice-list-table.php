<?php

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Nettmob_Invoice_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => __('Facture', 'workreap-invoices'), // singular name of the listed records
            'plural'   => __('Factures', 'workreap-invoices'), // plural name of the listed records
            'ajax'     => false // should this table support ajax?
        ]);
    }

    /**
     * Prepare the items for the table to process
     */
    public function prepare_items() {
        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = [$columns, $hidden, $sortable];

        $per_page = $this->get_items_per_page('invoices_per_page', 20);
        $current_page = $this->get_pagenum();

        // Get orders
        $args = array(
            'limit'    => $per_page,
            'paged'    => $current_page,
            'orderby'  => (isset($_REQUEST['orderby'])) ? sanitize_key($_REQUEST['orderby']) : 'date',
            'order'    => (isset($_REQUEST['order'])) ? sanitize_key($_REQUEST['order']) : 'DESC',
            'status'   => array_keys(wc_get_order_statuses()), // Get all order statuses
            // Potentially filter by orders that are "projects" if there's a meta key for that
        );
        $orders_query = new WC_Order_Query($args);
        $orders = $orders_query->get_orders();

        $total_items = $orders_query->get_total();

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);

        $data = [];
        if (!empty($orders)) {
            foreach ($orders as $order) {
                if (!$order) continue;

                $order_id = $order->get_id();

                // --- Data extraction ---
                // Client Info
                $client_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
                if (empty($client_name) && $order->get_billing_company()) {
                    $client_name = $order->get_billing_company();
                } elseif(empty($client_name)) {
                    $customer = $order->get_user();
                    $client_name = $customer ? $customer->display_name : __('Guest', 'workreap-invoices');
                }
                $client_email = $order->get_billing_email();

                // Project Name & Freelancer: This is highly dependent on Workreap theme structure.
                // Assumption: Product name is the project name. Freelancer ID is stored in order meta.
                $project_name = [];
                foreach ($order->get_items() as $item_id => $item) {
                    $project_name[] = $item->get_name();
                }
                $project_name = implode(', ', $project_name);
                if(empty($project_name)) $project_name = __('N/A', 'workreap-invoices');

                // Attempt to get Freelancer ID - YOU MUST VERIFY THIS META KEY
                $freelancer_id = $order->get_meta('_freelancer_id', true);
                // Fallback if _freelancer_id is not on order, but maybe on product or project post linked to order
                if (!$freelancer_id) {
                     // Example: Loop through items, get product_id, then get freelancer_id from product_meta
                    foreach ($order->get_items() as $item_id => $item) {
                        $product_id = $item->get_product_id();
                        if ($product_id) {
                            // This is a guess - Workreap might store freelancer on the product
                            $freelancer_id = get_post_meta($product_id, '_freelancer_id', true);
                            if ($freelancer_id) break;
                        }
                    }
                }

                $freelancer_name = __('N/A', 'workreap-invoices');
                if ($freelancer_id) {
                    $freelancer_user = get_userdata($freelancer_id);
                    if ($freelancer_user) {
                        $freelancer_name = $freelancer_user->display_name;
                    }
                }

                // Hours - also theme-dependent. Maybe stored in order_meta if type is hourly.
                // For example, if it's from the original code's $order_meta['approved_total_time']
                $cus_woo_product_data = $order->get_meta('cus_woo_product_data', true);
                $hours = !empty($cus_woo_product_data['approved_total_time']) ? $cus_woo_product_data['approved_total_time'] : __('N/A', 'workreap-invoices');
                // If not hourly, this might be irrelevant. We need to check project_type.
                $project_type = $order->get_meta('project_type', true);
                if ($project_type !== 'hourly') {
                    $hours = __('N/A', 'workreap-invoices');
                }


                $data[] = [
                    'order_id'     => $order_id,
                    'project_name' => $project_name,
                    'freelancer'   => $freelancer_name,
                    'client'       => $client_name,
                    'client_email' => $client_email,
                    'price'        => $order->get_formatted_order_total(),
                    'hours'        => $hours,
                    'order_date'   => $order->get_date_created() ? $order->get_date_created()->date_i18n(get_option('date_format')) : '',
                    'status'       => wc_get_order_status_name($order->get_status()),
                    'actions'      => '', // Will be populated by column_actions
                    'raw_order'    => $order // Keep raw order object if needed for actions column
                ];
            }
        }
        $this->items = $data;
    }

    /**
     * Override the parent columns method. Defines the columns to use in your listing table
     */
    public function get_columns() {
        $columns = [
            // 'cb'          => '<input type="checkbox" />', // For bulk actions
            'order_id'    => __('ID Commande', 'workreap-invoices'),
            'project_name'=> __('Nom de la mission/Produit', 'workreap-invoices'),
            'freelancer'  => __('Freelance', 'workreap-invoices'),
            'client'      => __('Client', 'workreap-invoices'),
            'client_email'=> __('Email Client', 'workreap-invoices'),
            'price'       => __('Prix Total', 'workreap-invoices'),
            'hours'       => __('Nb. Heures', 'workreap-invoices'), // Relevant for hourly
            'order_date'  => __('Date Commande', 'workreap-invoices'),
            'status'      => __('Statut Commande', 'workreap-invoices'),
            'actions'     => __('Actions', 'workreap-invoices'),
        ];
        return $columns;
    }

    /**
     * Define which columns are hidden
     */
    public function get_hidden_columns() {
        return []; // No hidden columns by default
    }

    /**
     * Define the sortable columns
     */
    public function get_sortable_columns() {
        return [
            'order_id'   => ['order_id', false], // true for default sort
            'project_name' => ['project_name', false],
            // Sorting by freelancer/client name might be complex if not directly on order
            'price'      => ['price', false],
            'order_date' => ['order_date', true], // Default sort by date
            'status'     => ['status', false],
        ];
    }

    /**
     * Get the dummy data for the table.
     * Replace this with actual data fetching from WooCommerce orders and Workreap project meta.
     */
    // This method is no longer used as data is fetched directly in prepare_items
    // private function get_dummy_data() { ... }

    /**
     * Render a column when no column specific method exists.
     *
     * @param array $item A singular item (one row's data).
     * @param string $column_name The name/slug of the column to be displayed.
     * @return string Text or HTML to be placed inside the column TD.
     */
    protected function column_default($item, $column_name) {
        switch ($column_name) {
            case 'order_id':
                 // Link to the WooCommerce order edit page
                $order_edit_url = admin_url('post.php?post=' . absint($item['order_id']) . '&action=edit');
                return sprintf('<a href="%s">#%s</a>', esc_url($order_edit_url), esc_html($item['order_id']));
            case 'project_name':
            case 'freelancer':
            case 'client':
            case 'client_email':
            case 'price':
            case 'hours':
            case 'order_date':
            case 'status':
                return isset($item[$column_name]) ? esc_html($item[$column_name]) : '';
            // Note: 'actions' column is handled by column_actions method
            default:
                // For debugging, show the whole item array if a column is not specifically handled.
                // return print_r($item, true);
                return ''; // Or return empty string for unhandled columns in production
        }
    }

    /**
     * Render the "Actions" column
     */
    public function column_actions($item) {
        $order_id = $item['order_id']; // Assuming 'order_id' is the key for the order ID in your $item array
        $actions = array();

        // Client Invoice
        $client_invoice_url = nettmob_get_client_invoice_url($order_id);
        if ($client_invoice_url) {
            $actions['view_client_invoice'] = sprintf(
                '<a href="%s" target="_blank" title="%s"><span class="dashicons dashicons-format-aside"></span></a>',
                esc_url($client_invoice_url),
                esc_attr__('Voir Facture Client', 'workreap-invoices')
            );
        } else {
            $generate_client_url = wp_nonce_url(
                admin_url('admin-post.php?action=nettmob_generate_client_invoice&order_id=' . $order_id),
                'nettmob_generate_client_invoice_nonce_' . $order_id,
                '_wpnonce_generate_client_invoice'
            );
            $actions['generate_client_invoice'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url($generate_client_url),
                esc_html__('Générer Facture Client', 'workreap-invoices')
            );
        }

        // Commission Invoice
        $commission_invoice_url = nettmob_get_commission_invoice_url($order_id);
        if ($commission_invoice_url) {
            $actions['view_commission_invoice'] = sprintf(
                '<a href="%s" target="_blank" title="%s"><span class="dashicons dashicons-money-alt"></span></a>', // Using a different icon
                esc_url($commission_invoice_url),
                esc_attr__('Voir Relevé Commission', 'workreap-invoices')
            );
        } else {
            $generate_commission_url = wp_nonce_url(
                admin_url('admin-post.php?action=nettmob_generate_commission_invoice&order_id=' . $order_id),
                'nettmob_generate_commission_invoice_nonce_' . $order_id,
                '_wpnonce_generate_commission_invoice'
            );
            $actions['generate_commission_invoice'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url($generate_commission_url),
                esc_html__('Générer Relevé Commission', 'workreap-invoices')
            );
        }

        return $this->row_actions($actions, false); // false for always_visible, true for visible on hover
    }

    // TODO: Add methods for column_cb (checkboxes for bulk actions) if needed.
    // TODO: Add methods for handling sorting and pagination.
    // TODO: Implement actual data fetching in prepare_items().
}
?>
