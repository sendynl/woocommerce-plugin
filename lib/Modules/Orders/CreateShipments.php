<?php

namespace Sendy\WooCommerce\Modules\Orders;

use Sendy\Api\Exceptions\SendyException;
use Sendy\WooCommerce\Repositories\Shipments;

class CreateShipments extends OrdersModule
{
    public function __construct()
    {
        add_action('wp_ajax_sendy_create_shipments', [$this, 'handle_create_shipments']);
        add_action('wp_ajax_sendy_shipments_status', [$this, 'handle_shipments_status']);
    }

    /**
     * Create shipments for the selected orders that do not have one yet
     */
    public function handle_create_shipments(): void
    {
        $this->verify_ajax_request('sendy_bulk_modal', true);

        if ($this->print_existing_labels_only()) {
            // Refuse rather than trust the client: a cached older copy of the
            // script could otherwise create shipments the portal creates itself.
            $message = __('Shipments for this processing method are created in Sendy', 'sendy');

            sendy_flash_admin_notice('error', $message);
            wp_send_json(['message' => $message], 400);
        }

        $shopId = sanitize_key($_POST['shop_id'] ?? '');
        $preferenceId = sanitize_key($_POST['preference_id'] ?? '');
        $amount = sanitize_key($_POST['amount'] ?? '1');

        $created = [];

        try {
            foreach ($this->requested_order_ids() as $orderId) {
                $order = wc_get_order($orderId);

                if (! $order || $order->meta_exists('_sendy_shipment_id')) {
                    continue;
                }

                $this->create_shipment_flashing_errors($order, $shopId, $preferenceId, (int) $amount);

                if ($order->meta_exists('_sendy_shipment_id')) {
                    $created[] = $orderId;
                }
            }
        } catch (\RuntimeException $exception) {
            $this->send_not_connected_error();
        }

        if ($created !== []) {
            $this->remember_previously_used($shopId, $preferenceId, $amount);
        }

        wp_send_json(['created' => $created]);
    }

    /**
     * Report per order whether its labels can be printed: none, pending,
     * ready or failed
     */
    public function handle_shipments_status(): void
    {
        $this->verify_ajax_request('sendy_print_labels', true);

        $statuses = [];

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- boolean flag, nothing to sanitize
        $flashUnprintable = ! empty($_POST['flash_unprintable']);

        try {
            foreach ($this->requested_order_ids() as $orderId) {
                $statuses[$orderId] = $this->shipment_status(wc_get_order($orderId));
            }
        } catch (\RuntimeException $exception) {
            $this->send_not_connected_error();
        }

        if ($flashUnprintable) {
            $this->flash_unprintable_notice($this->unprintable_orders($statuses));
        }

        wp_send_json(['statuses' => $statuses]);
    }

    /**
     * @return int[]
     */
    private function requested_order_ids(): array
    {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- intval() sanitizes each id
        return array_map('intval', (array) ($_POST['order_ids'] ?? []));
    }

    /**
     * Report a broken connection as JSON
     *
     * An access token registers these endpoints, but the other token options
     * can still be missing; building the API connection then throws.
     */
    private function send_not_connected_error(): void
    {
        $message = __('The plugin is not connected to Sendy. Check the settings page to reconnect.', 'sendy');

        sendy_flash_admin_notice('error', $message);
        wp_send_json(['message' => $message], 403);
    }

    /**
     * The orders that have no label to print
     *
     * Failed shipments have flashed their own explanation, and orders without
     * a shipment only matter when this flow is not the one creating them.
     *
     * @param array<int,string> $statuses
     * @return int[]
     */
    private function unprintable_orders(array $statuses): array
    {
        $unprintable = $this->print_existing_labels_only() ? ['none', 'pending'] : ['pending'];

        return array_keys(array_filter($statuses, function (string $status) use ($unprintable): bool {
            return in_array($status, $unprintable, true);
        }));
    }

    /**
     * Explain in one notice which orders have no label to print
     *
     * Printing a whole page of orders can leave many of them unprintable, so
     * they are counted rather than named.
     *
     * @param int[] $orderIds
     */
    private function flash_unprintable_notice(array $orderIds): void
    {
        if ($orderIds === []) {
            return;
        }

        $count = count($orderIds);

        if ($this->print_existing_labels_only()) {
            $message = $count > 1
                // translators: %s contains the number of orders
                ? __('There is no label to print for %s orders yet. Process the shipments in Sendy first.', 'sendy')
                // translators: %s contains the ID of the order
                : __('There is no label to print for order #%s yet. Process the shipment in Sendy first.', 'sendy');
        } else {
            $message = $count > 1
                // translators: %s contains the number of orders
                ? __('The labels for %s orders are still being generated. Please try again in a moment.', 'sendy')
                // translators: %s contains the ID of the order
                : __('The labels for order #%s are still being generated. Please try again in a moment.', 'sendy');
        }

        sendy_flash_admin_notice('warning', sprintf($message, $count > 1 ? $count : reset($orderIds)));
    }

    /**
     * Determine the print status of a single order's shipment
     *
     * Persists the packages once the shipment is generated, so later checks
     * skip the API.
     *
     * @param \WC_Order|false $order
     */
    private function shipment_status($order): string
    {
        if (! $order || ! $order->meta_exists('_sendy_shipment_id')) {
            return 'none';
        }

        $packages = $order->get_meta('_sendy_packages');

        if (is_array($packages) && $packages !== []) {
            return 'ready';
        }

        try {
            $shipment = (new Shipments())->get($order->get_meta('_sendy_shipment_id'));
        } catch (SendyException $exception) {
            sendy_flash_admin_notice('error', sprintf(
                // translators: %1$s contains the ID of the order, %2$s the error message
                __('Error while checking the shipment for order #%1$s: %2$s', 'sendy'),
                $order->get_id(),
                $exception->getMessage(),
            ));

            return 'failed';
        }

        $status = $shipment['status'] ?? '';

        if ($status === 'generated') {
            $order->update_meta_data('_sendy_packages', $shipment['packages'] ?? []);
            $order->save();

            return 'ready';
        }

        if (in_array($status, ['new', 'pending'], true)) {
            return 'pending';
        }

        sendy_flash_admin_notice('error', sprintf(
            // translators: %1$s contains the ID of the order, %2$s the status of the shipment
            __('The labels for order #%1$s cannot be printed (shipment status: %2$s)', 'sendy'),
            $order->get_id(),
            $status !== '' ? $status : 'unknown',
        ));

        return 'failed';
    }
}
