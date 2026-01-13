<?php

namespace Sendy\WooCommerce\Modules\Orders;

use Sendy\WooCommerce\Enums\ProcessingMethod;
use WC_Order;

class ProcessInBackground extends OrdersModule
{
    public function __construct()
    {
        if (get_option('sendy_processing_method') === ProcessingMethod::Sendy) {
            add_action('woocommerce_checkout_order_created', [$this, 'handle_order_created']);
            add_action('woocommerce_order_status_changed', [$this, 'handle_order_status_change'], 10, 4);
        }
    }

    public function handle_order_created(WC_Order $order): void
    {
        if ($order->get_status() !== get_option('sendy_processable_order_status')) {
            return;
        }

        if ($order->get_meta('_sendy_shipment_id')) {
            return;
        }

        $this->create_shipment_with_smart_rules($order);
    }

    public function handle_order_status_change(int $orderId, string $oldStatus, string $newStatus, WC_Order $order): void
    {
        if ($newStatus !== get_option('sendy_processable_order_status')) {
            return;
        }

        if ($order->get_meta('_sendy_shipment_id')) {
            return;
        }

        $this->create_shipment_with_smart_rules($order);
    }
}
