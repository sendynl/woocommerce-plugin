<?php

namespace Sendy\WooCommerce\Modules\Orders;

use Sendy\WooCommerce\Plugin;
use Sendy\WooCommerce\Repositories\Preferences;
use Sendy\WooCommerce\Repositories\Shops;
use Sendy\WooCommerce\Utils\View;

class BulkActions extends OrdersModule
{
    public function __construct()
    {
        add_filter('bulk_actions-woocommerce_page_wc-orders', [$this, 'add_bulk_actions'], 10, 1);
        add_filter('handle_bulk_actions-woocommerce_page_wc-orders', [$this, 'handle_bulk_action_create_shipments'], 10, 3);
        add_filter('handle_bulk_actions-woocommerce_page_wc-orders', [$this, 'handle_bulk_action_print_labels'], 10, 3);

        add_filter('bulk_actions-edit-shop_order', [$this, 'add_bulk_actions'], 10, 1);
        add_filter('handle_bulk_actions-edit-shop_order', [$this, 'handle_bulk_action_create_shipments'], 10, 3);
        add_filter('handle_bulk_actions-edit-shop_order', [$this, 'handle_bulk_action_print_labels'], 10, 3);

        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        add_action('admin_footer', [$this, 'modal_create_shipments']);
    }

    /**
     * Add the bulk actions to the dropdown menu
     *
     * @param array<string,string> $bulkActions
     * @return array<string,string>
     */
    public function add_bulk_actions(array $bulkActions): array
    {
        $bulkActions['sendy_create_shipments'] = esc_html__('Sendy - Create shipments', 'sendy');
        $bulkActions['sendy_print_labels'] = esc_html__('Sendy - Print labels', 'sendy');

        return $bulkActions;
    }

    /**
     * Create the shipments with the selected preference
     *
     * @param string $redirect
     * @param string $action
     * @param array $objectIds
     * @return string
     */
    public function handle_bulk_action_create_shipments(string $redirect, string $action, array $objectIds): string
    {
        if ($action !== 'sendy_create_shipments') {
            return $redirect;
        }

        if (!isset($_REQUEST['sendy_bulk_modal_nonce']) || ! wp_verify_nonce(sanitize_key($_REQUEST['sendy_bulk_modal_nonce']), 'sendy_bulk_modal')) {
            wp_die('Nonce verification failed');
        }

        foreach ($objectIds as $id) {
            $this->create_shipment_from_order(
                wc_get_order($id),
                sanitize_key($_REQUEST['sendy_preference_id'] ?? ''),
                sanitize_key($_REQUEST['sendy_shop_id'] ?? '')
            );
        }

        update_option('sendy_previously_used_preference_id', sanitize_key($_REQUEST['sendy_preference_id'] ?? ''));
        update_option('sendy_previously_used_shop_id', sanitize_key($_REQUEST['sendy_shop_id'] ?? ''));

        return $redirect;
    }

    /**
     * Handle the print labels bulk action
     *
     * @param string $redirect
     * @param string $action
     * @param array $objectIds
     * @return string|void
     */
    public function handle_bulk_action_print_labels(string $redirect, string $action, array $objectIds)
    {
        if ($action !== 'sendy_print_labels' || empty($objectIds)) {
            return $redirect;
        }

        $shipmentIds = [];

        foreach ($objectIds as $objectId) {
            $order = wc_get_order($objectId);

            if ($order->meta_exists('_sendy_shipment_id')) {
                $shipmentIds[] = $order->get_meta('_sendy_shipment_id');

                if (get_option('sendy_mark_order_as_completed') === 'after-label-printed') {
                    $order->set_status('completed', __('Sendy: Label printed', 'sendy'));
                    $order->save();
                }
            }
        }

        if (count($shipmentIds) == 0) {
            sendy_flash_admin_notice('notice', __('Non of the selected orders have any labels', 'sendy'));

            return $redirect;
        }

        $this->offer_labels_as_download($shipmentIds);
    }

    /**
     * Load the assets when needed
     *
     * @return void
     */
    public function enqueue_assets(): void
    {
        if ($this->on_orders_list_page()) {
            wp_enqueue_style('thickbox');
            wp_enqueue_script('thickbox');

            wp_enqueue_script(
                'sendy-admin-order-bulk',
                SENDY_WC_PLUGIN_DIR_URL . '/resources/js/admin-order-bulk.js',
                [],
                Plugin::VERSION,
                true
            );
        }
    }

    /**
     * Add a modal to the overview page
     *
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Sendy\Api\ApiException
     */
    public function modal_create_shipments(): void
    {
        if ($this->on_orders_list_page()) {
            $preferences = (new Preferences())->get();
            $shops = (new Shops())->list();

            echo View::fromTemplate('admin/modals/create-shipment.php')->render([
                'fields' => $this->create_shipment_fields($shops, $preferences)
            ]);
        }
    }

    /**
     * Return the fields needed for the modal to create a shipment
     *
     * @param array $shops
     * @param array $preferences
     * @return array<int,array<string,mixed>>
     */
    private function create_shipment_fields(array $shops, array $preferences): array
    {
        $fields = [];

        $fields[] = [
            'id' => 'sendy_shop_id',
            'type' => 'select',
            'label' => __('Shop', 'sendy'),
            'description' => __('The shipments will be created with the selected shop', 'sendy'),
            'value' => get_option('sendy_previously_used_shop_id'),
            'options' => $shops,
        ];

        $fields[] = [
            'id' => 'sendy_preference_id',
            'type' => 'select',
            'label' => __('Select preference', 'sendy'),
            'description' => __('The shipments will be created with the preference you select here', 'sendy'),
            'value' => get_option('sendy_previously_used_preference_id'),
            'options' => $preferences,
        ];

        return $fields;
    }

    /**
     * Determine whether the user is on an orders overview page
     *
     * @return bool
     */
    private function on_orders_list_page(): bool
    {
        $screen = get_current_screen();

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return ($screen->id === 'woocommerce_page_wc-orders' && !isset($_GET['action'])) ||
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            ($screen->id === 'edit-shop_order' && $screen->base === 'edit' && !isset($_GET['action']));
    }
}
