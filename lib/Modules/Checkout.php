<?php

namespace Sendy\WooCommerce\Modules;

use Sendy\WooCommerce\Plugin;
use Sendy\WooCommerce\ShippingMethods\PickupPointDelivery;
use Sendy\WooCommerce\Utils\View;
use WP_Error;

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

        add_action('woocommerce_after_checkout_validation', [$this, 'validate_pickup_point'], 10, 2);
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
            wp_enqueue_script('sendy-api', 'https://app.sendy.nl/embed/api.js', [], Plugin::VERSION, ['in_footer' => true]);
            wp_enqueue_script('sendy-checkout', SENDY_WC_PLUGIN_DIR_URL . '/resources/js/checkout.js', ['jquery', 'sendy-api'], Plugin::VERSION, ['in_footer' => true]);
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
            if (!is_null(sendy_shipping_method_instance_id())) {
                $carrier = $this->get_carrier_for_shipping_method(sendy_shipping_method_instance_id());

                $selectedPickupPoint = WC()->session->get("sendy_selected_parcelshop_". sendy_shipping_method_instance_id());

                echo wp_kses(
                    View::fromTemplate('checkout/pickup_point_selection.php')->render([
                        'carrier' => $carrier,
                        'instance_id' => sendy_shipping_method_instance_id(),
                        'selected_pickup_point' => $selectedPickupPoint,
                    ]),
                    View::ALLOWED_TAGS
                );
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
        $instanceId = sendy_shipping_method_instance_id();

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
            echo wp_kses(
                View::fromTemplate('checkout/order_confirmation.php')->render([
                    'pickup_point' => $order->get_meta('_sendy_pickup_point_data')
                ]),
                View::ALLOWED_TAGS
            );
        }
    }

    /**
     * Validate if the pickup point is selected
     *
     * @param array $fields
     * @param WP_Error $errors
     */
    public function validate_pickup_point(array $fields, WP_Error $errors): void
    {
        if (in_array('sendy_pickup_point', wc_get_chosen_shipping_method_ids())) {
            $instanceId = sendy_shipping_method_instance_id();

            if (empty($instanceId)) {
                $errors->add('sendy_pickup_point_error', __('Please select a pick-up point.', 'sendy'));
            } else {
                $selectedPickupPoint = WC()->session->get("sendy_selected_parcelshop_{$instanceId}");

                if (empty($selectedPickupPoint)) {
                    $errors->add('sendy_pickup_point_error', __('Please select a pick-up point.', 'sendy'));
                }
            }
        }
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
        return (new PickupPointDelivery($instance_id))->get_option('carrier');
    }
}
