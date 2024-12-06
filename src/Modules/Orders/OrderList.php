<?php

namespace Sendy\WooCommerce\Modules\Orders;

use GuzzleHttp\Exception\GuzzleException;
use Sendy\Api\ApiException;
use Sendy\WooCommerce\Repositories\Shipments;
use Sendy\WooCommerce\Utils\View;

class OrderList
{
    private Shipments $shipments;

    public function __construct()
    {
        $this->shipments = new Shipments();

        add_filter('manage_edit-shop_order_columns', [$this, 'add_sendy_column_headers'], 29);
        add_filter('manage_woocommerce_page_wc-orders_columns', [$this, 'add_sendy_column_headers'], 29);

        add_action('manage_shop_order_posts_custom_column', [$this, 'add_sendy_columns'], 29, 2);
        add_action('manage_woocommerce_page_wc-orders_custom_column', [$this, 'add_sendy_columns'], 29, 2);
    }

    /**
     * Add the headers for the Sendy columns to the table with orders
     *
     * @param array $columns
     * @return array
     */
    public function add_sendy_column_headers(array $columns): array
    {
        $wc_actions = $columns['wc_actions'] ?? null;

        unset($columns['wc_actions']);

        $columns['sendy_shipping_method'] = esc_html__('Shipping method', 'sendy');
        $columns['sendy_track_trace'] = esc_html__('Track and trace', 'sendy');

        if ($wc_actions) {
            $columns['wc_actions'] = $wc_actions;
        }

        return $columns;
    }

    /**
     * Add the content of the columns to the table with orders
     *
     * @param $column
     * @param $order_id
     * @return void
     */
    public function add_sendy_columns($column, $order_id = null): void
    {
        if (is_null($order_id)) {
            return;
        }

        $order = wc_get_order($order_id);

        $this->migrate_legacy_data($order);

        if ($column === 'sendy_shipping_method') {
            echo wp_kses(
                View::fromTemplate('admin/orders/shipping_method.php')->render(['order' => $order]),
                View::ALLOWED_TAGS
            );
        }

        if ($column === 'sendy_track_trace') {
            echo wp_kses(
                View::fromTemplate('admin/orders/track_trace.php')->render(['order' => $order]),
                View::ALLOWED_TAGS
            );
        }
    }

    /**
     * Migrate the meta data from the legacy plug-in to the new structure
     *
     * @param \WC_Order $order
     * @return void
     * @throws GuzzleException
     */
    private function migrate_legacy_data(\WC_Order $order): void
    {
        if ($order->get_meta('sendy_shipment_id')) {
            try {
                $shipment = $this->shipments->get($order->get_meta('sendy_shipment_id'));

                $order->update_meta_data('_sendy_shipment_id', $shipment['uuid']);
                $order->update_meta_data('_sendy_packages', $shipment['packages']);
                $order->update_meta_data('sendy_shipment_id', null);
            } catch (ApiException $e) {
            }
        }
    }
}
