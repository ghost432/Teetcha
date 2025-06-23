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

        // For now, using dummy data. Replace with actual data fetching later.
        $this->items = $this->get_dummy_data();

        // TODO: Add pagination later
        // $per_page = 20;
        // $current_page = $this->get_pagenum();
        // $total_items = count($this->items); // This will be dynamic from a query

        // $this->set_pagination_args([
        //     'total_items' => $total_items,
        //     'per_page'    => $per_page
        // ]);
    }

    /**
     * Override the parent columns method. Defines the columns to use in your listing table
     */
    public function get_columns() {
        $columns = [
            // 'cb'          => '<input type="checkbox" />', // For bulk actions
            'order_id'    => __('ID Commande', 'workreap-invoices'),
            'project_name'=> __('Nom de la mission', 'workreap-invoices'),
            'freelancer'  => __('Freelance', 'workreap-invoices'),
            'client'      => __('Client', 'workreap-invoices'),
            'client_email'=> __('Email Client', 'workreap-invoices'),
            'price'       => __('Prix', 'workreap-invoices'),
            'hours'       => __('Nb. Heures', 'workreap-invoices'),
            'order_date'  => __('Date Commande', 'workreap-invoices'),
            'status'      => __('Statut', 'workreap-invoices'),
            'actions'     => __('Actions', 'workreap-invoices'),
        ];
        return $columns;
    }

    /**
     * Define which columns are hidden
     */
    public function get_hidden_columns() {
        return [];
    }

    /**
     * Define the sortable columns
     */
    public function get_sortable_columns() {
        return [
            'order_id'   => ['order_id', false],
            'project_name' => ['project_name', false],
            'freelancer' => ['freelancer', false],
            'client'     => ['client', false],
            'price'      => ['price', false],
            'order_date' => ['order_date', true], // true means it's already sorted by this column
            'status'     => ['status', false],
        ];
    }

    /**
     * Get the dummy data for the table.
     * Replace this with actual data fetching from WooCommerce orders and Workreap project meta.
     */
    private function get_dummy_data() {
        // This is placeholder data.
        // In a real scenario, you would query WooCommerce orders.
        // For each order, you'd fetch related project info, freelancer, client.
        return [
            [
                'order_id' => 123,
                'project_name' => 'Développement Plugin WordPress',
                'freelancer' => 'Jean Dupont',
                'client' => 'Entreprise X',
                'client_email' => 'clientx@example.com',
                'price' => '500 ' . get_woocommerce_currency_symbol(),
                'hours' => '20',
                'order_date' => '2023-10-26',
                'status' => 'Terminé',
                'actions' => '' // Links will be added here
            ],
            [
                'order_id' => 124,
                'project_name' => 'Rédaction Articles Blog',
                'freelancer' => 'Alice Martin',
                'client' => 'Magazine Y',
                'client_email' => 'clienty@example.com',
                'price' => '150 ' . get_woocommerce_currency_symbol(),
                'hours' => 'N/A', // For fixed price
                'order_date' => '2023-10-25',
                'status' => 'En cours',
                'actions' => ''
            ]
        ];
    }

    /**
     * Render a column when no column specific method exists.
     */
    protected function column_default($item, $column_name) {
        switch ($column_name) {
            case 'order_id':
            case 'project_name':
            case 'freelancer':
            case 'client':
            case 'client_email':
            case 'price':
            case 'hours':
            case 'order_date':
            case 'status':
            // case 'actions': // Actions will have its own method or be generated in prepare_items
                return esc_html($item[$column_name]);
            default:
                return print_r($item, true); //Show the whole array for troubleshooting purposes
        }
    }

    /**
     * Render the "Actions" column
     */
    // public function column_actions($item) {
    //     $actions = array(
    //         'view_invoice_client' => sprintf('<a href="?page=%s&action=%s&order_id=%s&pdf_type=client&_wpnonce=%s">Facture Client (PDF)</a>', esc_attr($_REQUEST['page']), 'download_pdf', absint($item['order_id']), wp_create_nonce('nettmob_download_client_invoice')),
    //         'view_invoice_commission' => sprintf('<a href="?page=%s&action=%s&order_id=%s&pdf_type=commission&_wpnonce=%s">Relevé Commission (PDF)</a>', esc_attr($_REQUEST['page']), 'download_pdf', absint($item['order_id']), wp_create_nonce('nettmob_download_commission_invoice')),
    //     );
    //     return $this->row_actions($actions);
    // }

    // TODO: Add methods for column_cb (checkboxes for bulk actions) if needed.
    // TODO: Add methods for handling sorting and pagination.
    // TODO: Implement actual data fetching in prepare_items().
}
?>
