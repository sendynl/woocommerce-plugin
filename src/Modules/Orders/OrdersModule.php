<?php

namespace Sendy\WooCommerce\Modules\Orders;

use GuzzleHttp\Exception\GuzzleException;
use Sendy\Api\ApiException;
use Sendy\Api\Connection;
use Sendy\WooCommerce\ApiClientFactory;

abstract class OrdersModule
{
    private Connection $apiClient;

    /**
     * Initialize an order object from the meta box
     *
     * @param \WC_Order|null
     * @return \WP_Post|\WC_Order|null
     */
    protected function init_order_object($metaboxObject)
    {
        if (is_a($metaboxObject, 'WP_Post')) {
            return wc_get_order($metaboxObject->ID);
        }

        if (is_a($metaboxObject, 'WC_Order')) {
            return $metaboxObject;
        }

        return null;
    }

    /**
     * Determine if the order should be shipped to a pickup point
     *
     * @param \WC_Order $order
     * @return bool
     */
    protected function is_pickup_point_delivery(\WC_Order $order): bool
    {
        return $order->meta_exists('_sendy_pickup_point_id');
    }

    /**
     * Create the order in the Sendy API
     *
     * @param \WC_Order $order
     * @param string $preferenceId The UUID of the selected shipping preference
     * @param string $shopId The UUID of the selected shop
     * @param int $amount The amount of packages the shipment should contain
     * @return void
     * @throws GuzzleException
     */
    protected function create_shipment_from_order(\WC_Order $order, string $preferenceId, string $shopId, int $amount): void
    {
        $this->apiClient ??= ApiClientFactory::buildConnectionUsingTokens();

        if ($order->meta_exists('_sendy_shipment_id')) {
            // translators: %s The ID of the order
            sendy_flash_admin_notice('notice', sprintf(__('Order #%s already has a shipment created', 'sendy'), $order->get_id()));
        }

        $request = [
            'preference_id' => $preferenceId,
            'shop_id' => $shopId,
            'weight' => 1,
            'amount' => $amount,
            'reference' => $order->get_id(),
            'order_date' => $order->get_date_created()->format(\DateTimeInterface::RFC3339),
            'options' => [],
        ];

        $this->addAddressToRequest($order, $request);

        if ($this->is_pickup_point_delivery($order)) {
            $request['options']['parcel_shop_id'] = $order->get_meta('_sendy_pickup_point_id');
        }

        if (get_option('sendy_import_weight')) {
            $this->addWeightToRequest($order, $request);
        }

        if (get_option('sendy_import_products')) {
            $this->addProductsToRequest($order, $request);
        }

        try {
            $result = $this->apiClient->shipment->createFromPreference($request);

            $order->update_meta_data('_sendy_shipment_id', $result['uuid']);
            $order->update_meta_data('_sendy_packages', $result['packages']);

            if (get_option('sendy_mark_order_as_completed') === 'after-shipment-created') {
                $order->set_status('completed', __('Sendy: Shipment created', 'sendy'));
            }

            $order->save();
        } catch (ApiException $e) {
            if ($e->getCode() === 500) {
                // translators: %1$s should contain the ID of the order and %2$s the error
                $message = sprintf(__('Error while creating shipment for order #%1$s: %2$s', 'sendy'), $order->get_id(), $e->getMessage());
            } else {
                $statusCode = $e->getPrevious()->getCode();

                if ($statusCode === 401) {
                    // translators: %s should contain the ID of the order
                    $message = sprintf(__('Error while creating shipment for order #%s: Authentication failed. Check the settings page to reconnect with Sendy.', 'sendy'), $order->get_id());
                } else if ($statusCode === 422) {
                    $errors = [];

                    foreach ($e->getErrors() as $_ => $messages) {
                        $errors = array_merge($errors, $messages);
                    }

                    // translators: %1$s should contain the ID of the order and %2$s the error
                    $message = sprintf(__('Error while creating shipment for order #%1$s: %2$s', 'sendy'), $order->get_id(), implode("\n", $errors));
                } else if ($statusCode === 429) {
                    // translators: %s should contain the ID of the order
                    $message = sprintf(__('Error while creating shipment for order #%s: Too many requests. Please try again later.', 'sendy'), $order->get_id());
                } else {
                    // translators: %s should contain the ID of the order
                    $message = sprintf(__('Error while creating shipment for order #%s: Unknown error.', 'sendy'), $order->get_id());
                }
            }

            sendy_flash_admin_notice('error', $message);
        }
    }

    /**
     * Fetch the labels from the Sendy API and offer them as download to the user
     *
     * @param array $shipment_ids
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function offer_labels_as_download(array $shipment_ids): void
    {
        $this->apiClient ??= ApiClientFactory::buildConnectionUsingTokens();

        try {
            $response = $this->apiClient->label->get($shipment_ids);

            if (ob_get_contents()) {
                ob_clean();
            }

            $labels_safe = base64_decode($response['labels']);

            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="labels.pdf"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . strlen($labels_safe));

            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $labels_safe;
            exit;
        } catch (ApiException $exception) {
            wp_die(esc_html__('Something went wrong while downloading the labels', 'sendy'));
        }
    }

    /**
     * Add the shipping address to the request to create the shipment
     *
     * @param \WC_Order $order
     * @param $request
     * @return void
     */
    private function addAddressToRequest(\WC_Order $order, &$request): void
    {
        $request['contact'] = sprintf(
            '%s %s',
            $order->get_shipping_first_name() ?? $order->get_billing_first_name(),
            $order->get_shipping_last_name() ?? $order->get_billing_last_name()
        );

        $address = $this->parseAddressFromOrder($order);

        $request['company_name'] = $order->get_shipping_company() ?? $order->get_billing_company();
        $request['country'] = $order->get_shipping_country() ?? $order->get_billing_country();
        $request['street'] = $address->street;
        $request['number'] = $address->number;
        $request['addition'] = $address->addition;
        $request['postal_code'] = $order->get_shipping_postcode() ?? $order->get_billing_postcode();
        $request['city'] = $order->get_shipping_city() ?? $order->get_billing_city();
        $request['phone'] = $order->get_shipping_phone() ?? $order->get_billing_phone();
        $request['email'] = $order->get_billing_email();
    }

    /**
     * Add the weight to the request to create a shipment
     *
     * @param \WC_Order $order
     * @param $request
     * @return void
     */
    private function addWeightToRequest(\WC_Order $order, &$request): void
    {
        $weight = 0;

        foreach ($order->get_items() as $item) {
            $weight += (float)$item->get_product()->get_weight() * $item->get_quantity();
        }

        if ($weight > 0) {
            $request['weight'] = $weight;
        }
    }

    /**
     * Add the product data to the request to create a shipment
     *
     * @param \WC_Order $order
     * @param $request
     * @return void
     */
    private function addProductsToRequest(\WC_Order $order, &$request): void
    {
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();

            $request['products'][] = [
                'description' => $item->get_name(),
                'sku' => $product->get_sku(),
                'quantity' => $item->get_quantity(),
                'unit_price' => $item->get_subtotal() / $item->get_quantity(),
                'unit_weight' => absint($product->get_weight()) > 0 ? absint($product->get_weight()) * 1000 : null,
            ];
        }
    }

    /**
     * Split the address into street, number and addition
     *
     * First it tries to extra the address from address_1. If no number can be found, it is assumed that the number and
     * addition are stored in address_2. It will concatenate address_1 and address_2 and parse the address from the
     * concatenated string.
     *
     * @param \WC_Order $order
     * @return object{street: string, house_number:string|null, house_number_addition:string|null}
     */
    private function parseAddressFromOrder(\WC_Order $order): \stdClass
    {
        $address = sendy_parse_address($order->get_shipping_address_1() ?? $order->get_billing_address_1());

        if (is_null($address->number)) {
            return sendy_parse_address(sprintf('%s %s',
                $order->get_shipping_address_1() ?? $order->get_billing_address_1(),
                $order->get_shipping_address_2() ?? $order->get_billing_address_2()
            ));
        }

        return $address;
    }
}
