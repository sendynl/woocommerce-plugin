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
     * The URL carries the order id for orders-list-print-flow.js, which
     * intercepts the click and, for orders without a shipment, creates one
     * first before printing. The tooltip promises the creation only when the
     * flow would perform it: the Sendy processing method never creates from
     * here, so the button only offers to print in that mode.
     *
     * @param array<string,array<string,string>> $actions
     * @param \WC_Order $order
     * @return array<string,array<string,string>>
     */
    public function add_print_label_action(array $actions, $order): array
    {
        $createsShipmentFirst = ! $order->meta_exists('_sendy_shipment_id') && ! $this->print_existing_labels_only();

        $actions['sendy_print_label'] = [
            'url' => '#sendy-print-label-' . $order->get_id(),
            'name' => $createsShipmentFirst
                ? __('Sendy - Create shipment and print label', 'sendy')
                : __('Sendy - Print label', 'sendy'),
            'action' => 'sendy_print_label',
        ];

        return $actions;
    }
}
