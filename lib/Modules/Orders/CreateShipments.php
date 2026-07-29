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
     *
     * Used by the orders list print flow: the modal collects the shop,
     * preference and amount (the same fields as the "Create shipments" bulk
     * action) and this endpoint performs the creation over AJAX so the flow
     * can wait for the labels and print them afterwards. Orders that already
     * have a shipment are skipped. Errors per order are flashed as admin
     * notices by the inherited creation methods; the response only reports
     * which orders now have a shipment.
     */
    public function handle_create_shipments(): void
    {
        $this->verify_ajax_request('sendy_bulk_modal');

        if ($this->print_existing_labels_only()) {
            // The print flow does not offer to create shipments for this
            // processing method; refuse rather than trust the client, so a
            // browser running a cached older copy of the script cannot add
            // shipments the portal would have created itself.
            wp_send_json(['message' => __('Shipments for this processing method are created in Sendy', 'sendy')], 400);
        }

        $shopId = sanitize_key($_POST['shop_id'] ?? '');
        $preferenceId = sanitize_key($_POST['preference_id'] ?? '');
        $amount = sanitize_key($_POST['amount'] ?? '1');

        $created = [];

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- intval() sanitizes each id
        foreach (array_map('intval', (array) ($_POST['order_ids'] ?? [])) as $orderId) {
            $order = wc_get_order($orderId);

            if (! $order || $order->meta_exists('_sendy_shipment_id')) {
                continue;
            }

            try {
                $this->create_shipment($order, $shopId, $preferenceId, (int) $amount);
            } catch (SendyException $exception) {
                // The creation methods flash API validation errors themselves;
                // this guards the calls they make outside their own try/catch,
                // such as fetching the shop list for smart rules.
                // translators: %1$s contains the ID of the order, %2$s the error message
                sendy_flash_admin_notice('error', sprintf(
                    __('Error while creating shipment for order #%1$s: %2$s', 'sendy'),
                    $order->get_id(),
                    $exception->getMessage(),
                ));
            }

            if ($order->meta_exists('_sendy_shipment_id')) {
                $created[] = $orderId;
            }
        }

        if ($created !== []) {
            $this->remember_previously_used($shopId, $preferenceId, $amount);
        }

        wp_send_json(['created' => $created]);
    }

    /**
     * Report per order whether its labels can be printed
     *
     * Returns a map of order id to one of: `none` (no shipment yet),
     * `pending` (shipment exists but the label is still being generated),
     * `ready` (labels can be printed), or `failed` (generation failed or the
     * shipment was cancelled). The orders list print flow uses this to decide
     * between printing directly, creating a shipment first, or polling until
     * the Sendy portal has generated the label.
     *
     * With `flash_unprintable` set, the orders that have no label to print are
     * explained in a single admin notice: the flow is done waiting for them.
     */
    public function handle_shipments_status(): void
    {
        $this->verify_ajax_request('sendy_print_labels');

        $statuses = [];

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- boolean flag, nothing to sanitize
        $flashUnprintable = ! empty($_POST['flash_unprintable']);

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- intval() sanitizes each id
        foreach (array_map('intval', (array) ($_POST['order_ids'] ?? [])) as $orderId) {
            $statuses[$orderId] = $this->shipment_status(wc_get_order($orderId));
        }

        if ($flashUnprintable) {
            $this->flash_unprintable_notice($this->unprintable_orders($statuses));
        }

        wp_send_json(['statuses' => $statuses]);
    }

    /**
     * The orders that have no label to print
     *
     * Orders with a failed shipment have flashed their own explanation
     * already, and an order without a shipment is only worth mentioning when
     * this flow is not the one that creates it.
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
     * The Sendy processing method generates the labels in the portal, so there
     * is nothing for the shop to wait for; the WooCommerce processing method
     * generates them while the flow polls, so a shipment that is still pending
     * when the flow gives up may well be ready shortly after. Printing a whole
     * page of orders can leave many of them unprintable, so they are counted
     * rather than named.
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
     * Orders whose packages are already stored do not hit the API. When the
     * API reports the shipment as generated, the packages are persisted so
     * the track and trace column and the order meta box pick them up.
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
