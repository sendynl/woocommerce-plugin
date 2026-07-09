<?php

namespace Sendy\WooCommerce\Modules\Orders;

use Sendy\Api\Exceptions\SendyException;
use Sendy\WooCommerce\ApiClientFactory;

class PrintLabels extends OrdersModule
{
    public function __construct()
    {
        add_action('wp_ajax_sendy_print_labels', [$this, 'handle_print_labels']);
    }

    /**
     * Fetch the labels for the selected orders and return them to print-labels.js
     *
     * The Sendy API response is passed through as-is (plain wp_send_json, no
     * success/data envelope) so the shared JS can read `labels ?? documents`,
     * with one extra top-level key: `reload`, true when any order was marked
     * as completed. The x-sendy-* response headers are forwarded because the
     * print app authenticates with the x-sendy-token value.
     */
    public function handle_print_labels(): void
    {
        if (! check_ajax_referer('sendy_print_labels', 'nonce', false)) {
            wp_send_json(['message' => __('Nonce verification failed', 'sendy')], 403);
        }

        if (! current_user_can('manage_woocommerce') || ! current_user_can('edit_shop_orders')) {
            wp_send_json(['message' => __('You do not have sufficient permissions to access this page.', 'sendy')], 403);
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- intval() sanitizes each id
        $orders = $this->orders_with_shipment(array_map('intval', (array) ($_POST['order_ids'] ?? [])));

        if ($orders === []) {
            $message = __('None of the selected orders have any labels', 'sendy');

            sendy_flash_admin_notice('notice', $message);
            wp_send_json(['message' => $message], 400);
        }

        try {
            $sendy = ApiClientFactory::buildConnectionUsingTokens();
            $response = $sendy->label->get($this->shipment_ids($orders));

            // Captured immediately: the memoized connection's headers are
            // overwritten by any later SDK call, such as ProcessInBackground
            // reacting to the status change below.
            $sendyHeaders = $sendy->sendyHeaders;
        } catch (SendyException $exception) {
            // translators: %s contains the error message
            $message = sprintf(__('Error while fetching labels: %s', 'sendy'), $exception->getMessage());

            sendy_flash_admin_notice('error', $message);
            wp_send_json(['message' => $message], 502);
        }

        $marked = $this->mark_orders_as_completed($orders);

        foreach ($this->headers_to_forward($sendyHeaders) as $name => $value) {
            header("{$name}: {$value}");
        }

        wp_send_json($response + ['reload' => $marked]);
    }

    /**
     * Resolve the given order ids to orders that have a shipment
     *
     * @param int[] $orderIds
     * @return \WC_Order[]
     */
    public function orders_with_shipment(array $orderIds): array
    {
        $orders = [];

        foreach ($orderIds as $orderId) {
            $order = wc_get_order($orderId);

            if ($order && $order->meta_exists('_sendy_shipment_id')) {
                $orders[] = $order;
            }
        }

        return $orders;
    }

    /**
     * @param \WC_Order[] $orders
     * @return string[]
     */
    public function shipment_ids(array $orders): array
    {
        return array_map(function ($order) {
            return $order->get_meta('_sendy_shipment_id');
        }, $orders);
    }

    /**
     * Mark the orders as completed when the plugin is configured to do so
     * after printing labels. Returns whether any order was updated.
     *
     * @param \WC_Order[] $orders
     */
    public function mark_orders_as_completed(array $orders): bool
    {
        if (get_option('sendy_mark_order_as_completed') !== 'after-label-printed') {
            return false;
        }

        foreach ($orders as $order) {
            $order->set_status('completed', __('Sendy: Label printed', 'sendy'));
            $order->save();
        }

        return $orders !== [];
    }

    /**
     * Select the x-sendy-* headers to forward to the client
     *
     * The match is case-insensitive because HTTP/2 lowercases header names,
     * values may be Guzzle-style arrays (the first element wins), and CR/LF
     * is stripped to prevent header injection.
     *
     * @param array<string,string|string[]> $sendyHeaders
     * @return array<string,string>
     */
    public function headers_to_forward(array $sendyHeaders): array
    {
        $headers = [];

        foreach ($sendyHeaders as $name => $value) {
            if (strpos(strtolower($name), 'x-sendy-') !== 0) {
                continue;
            }

            $value = is_array($value) ? reset($value) : $value;

            $headers[$name] = str_replace(["\r", "\n"], '', (string) $value);
        }

        return $headers;
    }
}
