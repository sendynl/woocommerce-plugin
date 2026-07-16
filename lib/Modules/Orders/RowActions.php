<?php

namespace Sendy\WooCommerce\Modules\Orders;

class RowActions extends OrdersModule
{
    public function __construct()
    {
        // Both the legacy posts table and the HPOS orders table render the
        // actions column through this same filter.
        add_filter('woocommerce_admin_order_actions', [$this, 'add_print_label_action'], 10, 2);
    }

    /**
     * Add a print label button to the actions column
     *
     * The URL is a no-op; orders-list-print-flow.js intercepts the click and,
     * for orders without a shipment, creates one first before printing.
     *
     * @param array<string,array<string,string>> $actions
     * @param \WC_Order $order
     * @return array<string,array<string,string>>
     */
    public function add_print_label_action(array $actions, $order): array
    {
        if ($order->meta_exists('_sendy_shipment_id')) {
            $actions['sendy_print_label'] = [
                'url' => '#',
                'name' => __('Sendy - Print label', 'sendy'),
                'action' => 'sendy_print_label',
            ];
        } else {
            $actions['sendy_create_shipment_print_label'] = [
                'url' => '#',
                'name' => __('Sendy - Create shipment and print label', 'sendy'),
                'action' => 'sendy_create_shipment_print_label',
            ];
        }

        return $actions;
    }
}
