<?php

namespace Sendy\WooCommerce\Modules;

use Sendy\WooCommerce\Plugin;
use Sendy\WooCommerce\Utils\View;

final class Checkout
{
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('woocommerce_review_order_after_shipping', [$this, 'render_pickup_point_field'], 10);

        add_action('wp_ajax_sendy_set_pickup_point', [$this, 'store_selected_pickup_point_in_session']);
        add_action('wp_ajax_nopriv_sendy_set_pickup_point', [$this, 'store_selected_pickup_point_in_session']);

        add_action('woocommerce_checkout_create_order', [$this, 'store_selected_pickup_point_in_order'], 10, 3);

        add_action('woocommerce_order_details_after_customer_address', [$this, 'show_selected_pickup_point_on_confirmation'], 10, 2);
    }

    /**
     * Load the assets, but only on the checkout page
     *
     * @return void
     */
    public function enqueue_assets(): void
    {
        if (is_checkout()) {
            wp_enqueue_script('wp-util');
            wp_enqueue_script('sendy-api', 'https://app.sendy.nl/embed/api.js', [], Plugin::VERSION);
            wp_enqueue_script('sendy-checkout', SENDY_WC_PLUGIN_DIR_URL . '/resources/js/checkout.js', ['jquery', 'sendy-api'], Plugin::VERSION);
        }
    }

    /**
     * Render the button to select a pick-up point in the checkout
     *
     * @return void
     */
    public function render_pickup_point_field(): void
    {
        if (in_array('sendy_pickup_point', wc_get_chosen_shipping_method_ids())) {
            if (!is_null($this->get_shipping_method_instance_id())) {
                $carrier = $this->get_carrier_for_shipping_method($this->get_shipping_method_instance_id());

                $selectedPickupPoint = WC()->session->get("sendy_selected_parcelshop_{$this->get_shipping_method_instance_id()}");

                echo View::fromTemplate('checkout/pickup_point_selection.php')->render([
                    'carrier' => $carrier,
                    'instance_id' => $this->get_shipping_method_instance_id(),
                    'selected_pickup_point' => $selectedPickupPoint,
                ]);
            }
        }
    }

    /**
     * Store the selected pickup point in the session
     *
     * @return void
     */
    public function store_selected_pickup_point_in_session(): void
    {
        if (empty($_REQUEST['nonce']) || !wp_verify_nonce(sanitize_key($_REQUEST['nonce']), 'sendy_store_pickup_point')) {
            wp_send_json_error('Nonce verification failed');
        }

        $data = [
            'id' => sanitize_text_field(wp_unslash(isset($_REQUEST['id']) ? $_REQUEST['id'] : '')),
            'name' => sanitize_text_field(wp_unslash(isset($_REQUEST['name']) ? $_REQUEST['name'] : '')),
            'street' => sanitize_text_field(wp_unslash(isset($_REQUEST['street']) ? $_REQUEST['street'] : '')),
            'number' => sanitize_text_field(wp_unslash(isset($_REQUEST['number']) ? $_REQUEST['number'] : '')),
            'postal_code' => sanitize_text_field(wp_unslash(isset($_REQUEST['postal_code']) ? $_REQUEST['postal_code'] : '')),
            'city' => sanitize_text_field(wp_unslash(isset($_REQUEST['city']) ? $_REQUEST['city'] : ''))
        ];

        WC()->session->set('sendy_selected_parcelshop_' . sanitize_key($_REQUEST['instance_id'] ?? ''), $data);

        wp_send_json_success();
    }

    /**
     * Add the selected pickup point to the order metadata
     *
     * @param \WC_Order $order
     * @return void
     */
    public function store_selected_pickup_point_in_order(\WC_Order $order): void
    {
        $instanceId = $this->get_shipping_method_instance_id();

        if ($data = WC()->session->get("sendy_selected_parcelshop_{$instanceId}")) {

            $order->update_meta_data('_sendy_pickup_point_id', $data['id']);
            $order->update_meta_data('_sendy_pickup_point_data', $data);

            WC()->session->set("sendy_selected_parcelshop_{$instanceId}", null);
        }
    }

    /**
     * Display the address of the selected pickup point on the order confirmation page
     *
     * @param string $addressType
     * @param \WC_Order $order
     * @return void
     */
    public function show_selected_pickup_point_on_confirmation(string $addressType, \WC_Order $order): void
    {
        if ($addressType === 'shipping' && $order->meta_exists('_sendy_pickup_point_id')) {
            echo View::fromTemplate('checkout/order_confirmation.php')
                ->render(['pickup_point' => $order->get_meta('_sendy_pickup_point_data')]);
        }
    }

    /**
     * Get the instance id of the selected shipment method
     *
     * This ID is used to correctly store the selected pickup point and makes it possible to offer pickup point delivery
     * for multiple carriers.
     *
     * @return int|null
     */
    private function get_shipping_method_instance_id(): ?int
    {
        $selectedMethods = WC()->session->get('chosen_shipping_methods');

        $pickupPointDelivery = array_filter(
            $selectedMethods,
            function ($item) { return str_starts_with($item, 'sendy_pickup_point'); }
        );

        if (count($pickupPointDelivery) > 0) {
            [$name, $instanceId] = explode(':', $pickupPointDelivery[0]);

            return (int) $instanceId;
        }

        return null;
    }

    /**
     * Get the carrier for the shipping method
     *
     * This method is used to load the correct pick-up points for a carrier in the checkout
     *
     * @param int $instance_id
     * @return string|null
     */
    private function get_carrier_for_shipping_method(int $instance_id): ?string
    {
        $options = get_option("woocommerce_sendy_pickup_point_{$instance_id}_settings");

        return $options['carrier'] ?? null;
    }
}
