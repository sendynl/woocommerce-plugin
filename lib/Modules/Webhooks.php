<?php

namespace Sendy\WooCommerce\Modules;

use Sendy\Api\ApiException;
use Sendy\Api\Exceptions\SendyException;
use Sendy\WooCommerce\ApiClientFactory;
use Sendy\WooCommerce\Enums\ProcessingMethod;
use WC_Order;
use WC_Order_Query;

class Webhooks
{
    public function __construct()
    {
        add_action('update_option_sendy_processing_method', [$this, 'handle_sendy_processing_method_change'], 10, 3);
        add_action('rest_api_init', [$this, 'init_rest_api_endpoint']);
        add_action('sendy_cron', [$this, 'ensure_webhook_installed']);
    }

    /**
     * Create or delete the webhook based on the new value
     *
     * @param mixed $oldValue
     * @param mixed $newValue
     */
    public function handle_sendy_processing_method_change($oldValue, $newValue): void
    {
        if ($oldValue === $newValue || ! in_array($newValue, ProcessingMethod::cases())) {
            return;
        }

        if ($newValue === ProcessingMethod::WooCommerce) {
            $this->deleteWebhook();
        }

        if ($newValue === ProcessingMethod::Sendy) {
            $this->createWebhook();
        }
    }

    public function init_rest_api_endpoint(): void
    {
        if (get_option('sendy_processing_method') != ProcessingMethod::Sendy) {
            return;
        }

        register_rest_route('sendy/v1', '/webhook', [
            'methods' => 'POST',
            'callback' => [$this, 'webhook_callback'],
            'permission_callback' => function () { return true; },
        ]);
    }

    public function webhook_callback(\WP_REST_Request $request)
    {
        $verificationError = $this->verifySignature($request);

        if ($verificationError) {
            return $verificationError;
        }

        $payload = $request->get_json_params() ?? [];

        if (! array_key_exists('data', $payload)) {
            return;
        }

        switch ($payload['data']['event']) {
            case 'shipment.generated':
                $this->handleShipmentGenerated($payload['data']['id']);
                break;

            case 'shipment.cancelled':
            case 'shipment.deleted':
                $this->handleShipmentDeletedOrCancelled($payload['data']['id']);
                break;

            case 'shipment.delivered':
                $this->handleShipmentDelivered($payload['data']['id']);
                break;
        }

        return rest_ensure_response([
            'status' => 'success',
            'message' => 'Webhook processed',
        ]);
    }

    public function ensure_webhook_installed(): void
    {
        if (get_option('sendy_processing_method') === ProcessingMethod::WooCommerce) {
            return;
        }

        if (get_option('sendy_webhook_last_checked') >= time() - 24 * 60 * 60) {
            return;
        }

        try {
            $webhooks = ApiClientFactory::buildConnectionUsingTokens()->webhook->list();

            $webhookIds = array_map(function ($webhook) {
                return $webhook['id'];
            }, $webhooks);

            if (! get_option('sendy_webhook_id') || ! in_array(get_option('sendy_webhook_id'), $webhookIds)) {
                $this->createWebhook();
            }

            update_option('sendy_webhook_last_checked', time());
        } catch (ApiException $exception) {
            return;
        }
    }

    /**
     * @throws SendyException
     */
    public static function regenerateWebhookSecret(): void
    {
        $clientId = get_option('sendy_client_id');
        $response = ApiClientFactory::buildConnectionUsingTokens()
            ->post("/regenerate-webhook-secret/{$clientId}");

        update_option('sendy_webhook_secret', $response['webhook_secret'], false);
    }

    public function deactivate(): void
    {
        $this->deleteWebhook();

        delete_option('sendy_webhook_last_checked');
    }

    private function verifySignature(\WP_REST_Request $request): ?\WP_REST_Response
    {
        $signature = $request->get_header('X-Signature');
        $timestamp = $request->get_header('X-Timestamp');

        if (! $signature || ! $timestamp) {
            return new \WP_REST_Response(['error' => 'Missing signature headers'], 401);
        }

        $secret = get_option('sendy_webhook_secret');

        if (! $secret) {
            try {
                self::regenerateWebhookSecret();
            } catch (\Exception $e) {
                return new \WP_REST_Response(['error' => 'Failed to regenerate webhook secret'], 500);
            }

            return new \WP_REST_Response(['error' => 'Webhook secret not configured'], 401);
        }

        $expected = hash_hmac('sha256', $timestamp . $request->get_body(), $secret);

        if (! hash_equals($expected, $signature)) {
            return new \WP_REST_Response(['error' => 'Invalid signature'], 401);
        }

        return null;
    }

    /**
     * Delete the webhook in the API
     */
    private function deleteWebhook(): void
    {
        $webhookId = get_option('sendy_webhook_id');

        if ($webhookId) {
            try {
                ApiClientFactory::buildConnectionUsingTokens()->webhook->delete($webhookId);
            } catch (ApiException $exception) {
                // Webhook was likely already deleted
            } finally {
                delete_option('sendy_webhook_id');
            }
        }
    }

    /**
     * Create the webhook in the API
     */
    private function createWebhook(): void
    {
        try {
            $webhook = ApiClientFactory::buildConnectionUsingTokens()->webhook->create([
                'url' => get_rest_url(null, 'sendy/v1/webhook', 'https'),
                'events' => [
                    'shipment.generated',
                    'shipment.deleted',
                    'shipment.cancelled',
                    'shipment.delivered',
                ],
            ]);

            update_option('sendy_webhook_id', $webhook['id']);
        } catch (ApiException $exception) {

        }
    }

    private function handleShipmentGenerated(string $shipmentId): void
    {
        $order = $this->fetchOrderByShipmentId($shipmentId);

        if ($order) {
            $shipment = ApiClientFactory::buildConnectionUsingTokens()->shipment->get($shipmentId);

            $order->update_meta_data('_sendy_packages', $shipment['packages']);

            if (get_option('sendy_mark_order_as_completed') === 'after-shipment-created') {
                $order->set_status('completed', __('Sendy: Shipment created', 'sendy'));
            }

            $order->save();
        }
    }

    private function handleShipmentDeletedOrCancelled(string $shipmentId): void
    {
        $order = $this->fetchOrderByShipmentId($shipmentId);

        if ($order) {
            $order->update_meta_data('_sendy_packages', null);
            $order->update_meta_data('_sendy_shipment_id', null);
            $order->save();
        }
    }

    private function handleShipmentDelivered(string $shipmentId): void
    {
        if (get_option('sendy_mark_order_as_completed') !== 'after-shipment-delivered') {
            return;
        }

        $order = $this->fetchOrderByShipmentId($shipmentId);

        if ($order) {
            $order->set_status('completed', __('Sendy: Shipment delivered', 'sendy'));
            $order->save();
        }
    }

    private function fetchOrderByShipmentId(string $shipmentId): ?WC_Order
    {
        $query = new WC_Order_Query([
            'limit' => 1,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            'meta_key' => '_sendy_shipment_id',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
            'meta_value' => $shipmentId,
            'meta_compare' => '=',
        ]);

        /** @var list<WC_Order> $result */
        $result = $query->get_orders();

        if (empty($result)) {
            return null;
        }

        return $result[0];
    }
}
