<?php

namespace Sendy\WooCommerce\Modules;

use Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema;
use Sendy\WooCommerce\ShippingMethods\PickupPointDelivery;
use Sendy\WooCommerce\Utils\BlocksIntegration;

final class BlocksCheckout
{
    public static function init(): self
    {
        return new self;
    }

    private function __construct()
    {
        add_action('woocommerce_store_api_checkout_order_processed', [$this, 'validate_pickup_point'], 9);
        add_action('woocommerce_store_api_checkout_order_processed', [$this, 'store_selected_pickup_point_in_order']);
        add_action('woocommerce_blocks_loaded', [$this, 'register_store_api_endpoints']);
        add_action('woocommerce_blocks_loaded', [$this, 'register_checkout_block']);
    }

    public function register_checkout_block(): void
    {
        add_action('woocommerce_blocks_checkout_block_registration', function ($integrationRegistry) {
            $integrationRegistry->register(new BlocksIntegration());
        });
    }

    /**
     * Validate if a pick-up point is selected
     *
     * @param \WC_Order $order
     * @return void
     */
    public function validate_pickup_point(\WC_Order $order): void
    {
        if (! in_array('sendy_pickup_point', wc_get_chosen_shipping_method_ids())) {
            return;
        }

        $instanceId = sendy_shipping_method_instance_id();

        $selectedPickupPoint = WC()->session->get("sendy_selected_parcelshop_{$instanceId}");

        if (empty($selectedPickupPoint)) {
            wc_add_notice(__('Please select a pick-up point.', 'sendy'), 'error');
        }
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

            $order->save();

            WC()->session->set("sendy_selected_parcelshop_{$instanceId}", null);
        }
    }


    public function register_store_api_endpoints(): void
    {
        woocommerce_store_api_register_update_callback([
            'namespace' => 'sendy-set-pickup-point',
            'callback' => function ($data) {
                $instanceId = sendy_shipping_method_instance_id();

                $selectedPickupPoint = [
                    'instance_id' => $instanceId,
                    'id' => sanitize_text_field($data['id'] ?? ''),
                    'name' => sanitize_text_field($data['name'] ?? ''),
                    'street' => sanitize_text_field($data['street'] ?? ''),
                    'number' => sanitize_text_field($data['number'] ?? ''),
                    'postal_code' => sanitize_text_field($data['postal_code'] ?? ''),
                    'city' => sanitize_text_field($data['city'] ?? '')
                ];

                WC()->session->set('sendy_selected_parcelshop_' . $instanceId, $selectedPickupPoint);
            },
        ]);

        woocommerce_store_api_register_endpoint_data([
            'endpoint' => CartSchema::IDENTIFIER,
            'namespace' => 'sendy-pickup-point',
            'data_callback' => function () {
                $instanceId = sendy_shipping_method_instance_id();

                $data = WC()->session->get("sendy_selected_parcelshop_{$instanceId}");

                return [
                    'name' => $data['name'] ?? null,
                    'street' => $data['street'] ?? null,
                    'number' => $data['number'] ?? null,
                    'postal_code' => $data['postal_code'] ?? null,
                    'city' => $data['city'] ?? null,
                ];
            },
            'schema_callback' => fn () => [
                'properties' => [
                    'name' => [
                        'description' => 'Name of the pickup point',
                        'type' => ['string', 'null'],
                        'readonly' => true,
                    ],
                    'street' => [
                        'description' => 'Street of the pickup point',
                        'type' => ['string', 'null'],
                        'readonly' => true,
                    ],
                    'number' => [
                        'description' => 'Number of the pickup point',
                        'type' => ['string', 'null'],
                        'readonly' => true,
                    ],
                    'portal_code' => [
                        'description' => 'Postal code of the pickup point',
                        'type' => ['string', 'null'],
                        'readonly' => true,
                    ],
                    'city' => [
                        'description' => 'City of the pickup point',
                        'type' => ['string', 'null'],
                        'readonly' => true,
                    ],
                ]
            ],
            'schema_type' => ARRAY_A,
        ]);

        woocommerce_store_api_register_endpoint_data([
            'endpoint' => CartSchema::IDENTIFIER,
            'namespace' => 'sendy-carrier',
            'data_callback' => function () {
                $instanceId = sendy_shipping_method_instance_id();

                $carrier = (new PickupPointDelivery($instanceId))->get_option('carrier');

                return [
                    'carrier' => $carrier ?? null,
                ];
            },
            'schema_callback' => fn () => [
                'properties' => [
                    'name' => [
                        'description' => 'The carrier to user for the pickup point picker',
                        'type' => ['string', 'null'],
                        'readonly' => true,
                    ],
                ]
            ],
            'schema_type' => ARRAY_A,
        ]);
    }
}
