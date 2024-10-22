<?php

namespace Sendy\WooCommerce\Modules\Orders;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use Sendy\WooCommerce\Plugin;
use Sendy\WooCommerce\Repositories\Preferences;
use Sendy\WooCommerce\Repositories\Shops;
use Sendy\WooCommerce\Utils\View;
use WC_Order;
use WP_Post;

class Single extends OrdersModule
{
    public function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('add_meta_boxes', [$this, 'add_meta_box'], 20, 2);

        add_action('wp_ajax_sendy_order_single_save_form', [$this, 'handle_create_shipment_from_form']);

        add_action('woocommerce_admin_order_data_after_shipping_address', [$this, 'display_shipping_data'], 10, 1);

        add_action('admin_init', [$this, 'download_label'], 10);
    }

    /**
     * Add a meta box to the order page
     *
     * @param string $postType
     * @param \WP_Post|WC_Order $postOrOrderObject
     * @return void
     */
    public function add_meta_box(string $postType, $postOrOrderObject): void
    {
        if (! $this->init_order_object($postOrOrderObject)) {
            return;
        }

        try {
            $screen = wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
                ? wc_get_page_screen_id( 'shop-order' )
                : 'shop_order';
        } catch ( \Exception $e ) {
            $screen = 'shop_order';
        }

        add_meta_box(
            'woocommerce-sendy-shipment',
            esc_html__('Sendy', 'sendy'),
            [$this, 'meta_box_html'],
            $screen,
            'side',
            'high',
        );
    }

    /**
     * Render the content of the meta box
     *
     * @param WP_Post|WC_Order $postOrOrderObject
     * @return void
     */
    public function meta_box_html($postOrOrderObject): void
    {
        if (!$order = $this->init_order_object($postOrOrderObject)) {
            return;
        }

        $preferences = (new Preferences())->get();
        $shops = (new Shops())->list();

        echo View::fromTemplate('admin/meta_box/single.php')->render([
            'order' => $order,
            'preferences' => $preferences,
            'shops' => $shops,
        ]);
    }

    /**
     * Load the assets if needed
     *
     * @return void
     */
    public function enqueue_assets(): void
    {
        if ($this->on_order_edit_page()) {
            wp_enqueue_script(
                'sendy-admin-order-single',
                SENDY_WC_PLUGIN_DIR_URL . '/resources/js/admin-order-single.js',
                [],
                Plugin::VERSION,
                true
            );
        }
    }

    /**
     * Handle the submission of the form to create a shipment from the order page
     *
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function handle_create_shipment_from_form(): void
    {
        try {
            if (!isset($_REQUEST['nonce']) || !check_ajax_referer('sendy_create_shipment', 'nonce')) {
                throw new \Exception( esc_html__( 'Nonce verification failed', 'postnl-for-woocommerce' ) );
            }

            if (!empty($_REQUEST['order_id'])) {
                $order = wc_get_order(sanitize_key($_REQUEST['order_id']));

                $this->create_shipment_from_order(
                    $order,
                    sanitize_key($_REQUEST['preference_id'] ?? ''),
                    sanitize_key($_REQUEST['shop_id'] ?? ''),
                    sanitize_key($_REQUEST['amount'] ?? '')
                );

                wp_send_json_success();
            }
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Display the shipping method and chosen pick-up point on the detail page for admins
     *
     * @param WC_Order $order
     * @return void
     */
    public function display_shipping_data(WC_Order $order): void
    {
        echo View::fromTemplate('admin/single/shipping_data.php')->render(['order' => $order]);
    }

    /**
     * Offer the label as a download to the user
     *
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function download_label(): void
    {
        if (empty($_GET['sendy_download_label_nonce'])) {
            return;
        }

        if (empty($_GET['sendy_action'])) {
            return;
        }

        if (!wp_verify_nonce(sanitize_key($_REQUEST['sendy_download_label_nonce'] ?? ''), 'sendy_download_label')) {
            wp_die(__('Nonce verification failed', 'sendy'));
        }

        if (sanitize_key($_REQUEST['sendy_action'] ?? '') === 'download_label') {
            $order = wc_get_order(sanitize_key($_REQUEST['order_id'] ?? ''));

            if (!$order) {
                wp_die(__('Order could not be found', 'sendy'));
            }

            if (!current_user_can('manage_woocommerce') || !current_user_can('view_order', $order->get_id())) {
                wp_die('You do not have sufficient permissions to access this page.');
            }

            if (!$order->meta_exists('_sendy_shipment_id')) {
                wp_die(__('No shipment created for order', 'sendy'));
            }

            if (get_option('sendy_mark_order_as_completed') === 'after-label-printed') {
                $order->set_status('completed', __('Sendy: Label printed', 'sendy'));
                $order->save();
            }

            $this->offer_labels_as_download([$order->get_meta('_sendy_shipment_id')]);
        }
    }

    /**
     * Determine if the user is on an order page
     *
     * @return bool
     */
    private function on_order_edit_page(): bool
    {
        $screen = get_current_screen();

        return ($screen->id === 'woocommerce_page_wc-orders' &&
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            isset($_GET['action']) &&
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            sanitize_key($_GET['action']) === 'edit') ||
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            ($screen->id === 'shop_order' && $screen->base === 'post' && sanitize_key($_GET['action']) === 'edit');
    }
}
