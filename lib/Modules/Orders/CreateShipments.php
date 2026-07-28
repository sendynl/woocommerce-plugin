<?php

namespace Sendy\WooCommerce\Modules\Orders;

use Sendy\Api\Exceptions\SendyException;
use Sendy\WooCommerce\Enums\ProcessingMethod;
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
     * have a shipment are skipped and appear in neither result list. Errors
     * per order are flashed as admin notices by the inherited creation
     * methods; the response only reports which orders now have a shipment.
     */
    public function handle_create_shipments(): void
    {
        $this->verify_ajax_request('sendy_bulk_modal');

        $shopId = sanitize_key($_POST['shop_id'] ?? '');
        $preferenceId = sanitize_key($_POST['preference_id'] ?? '');
        $amount = sanitize_key($_POST['amount'] ?? '1');

        $created = [];
        $failed = [];

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- intval() sanitizes each id
        foreach (array_map('intval', (array) ($_POST['order_ids'] ?? [])) as $orderId) {
            $order = wc_get_order($orderId);

            if (! $order) {
                $failed[] = $orderId;

                continue;
            }

            if ($order->meta_exists('_sendy_shipment_id')) {
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
            } else {
                $failed[] = $orderId;
            }
        }

        if ($created !== []) {
            update_option('sendy_previously_used_shop_id', $shopId);

            if (get_option('sendy_processing_method') === ProcessingMethod::WooCommerce) {
                update_option('sendy_previously_used_preference_id', $preferenceId);
                update_option('sendy_previously_used_amount', $amount);
            }
        }

        wp_send_json(['created' => $created, 'failed' => $failed]);
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
     */
    public function handle_shipments_status(): void
    {
        $this->verify_ajax_request('sendy_print_labels');

        $statuses = [];

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- boolean flag, nothing to sanitize
        $flashPending = ! empty($_POST['flash_pending']);

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- intval() sanitizes each id
        foreach (array_map('intval', (array) ($_POST['order_ids'] ?? [])) as $orderId) {
            $statuses[$orderId] = $this->shipment_status(wc_get_order($orderId));

            if ($flashPending && $statuses[$orderId] === 'pending') {
                // translators: %s contains the ID of the order
                sendy_flash_admin_notice('warning', sprintf(
                    __('The labels for order #%s are still being generated. Please try again in a moment.', 'sendy'),
                    $orderId,
                ));
            }
        }

        wp_send_json(['statuses' => $statuses]);
    }

    /**
     * Send a 403 JSON response when the nonce or the capabilities do not check out
     */
    private function verify_ajax_request(string $nonceAction): void
    {
        if (! check_ajax_referer($nonceAction, 'nonce', false)) {
            wp_send_json(['message' => __('Nonce verification failed', 'sendy')], 403);
        }

        if (! current_user_can('manage_woocommerce') || ! current_user_can('edit_shop_orders')) {
            wp_send_json(['message' => __('You do not have sufficient permissions to access this page.', 'sendy')], 403);
        }
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
    public function shipment_status($order): string
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
            // translators: %1$s contains the ID of the order, %2$s the error message
            sendy_flash_admin_notice('error', sprintf(
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

        // translators: %1$s contains the ID of the order, %2$s the status of the shipment
        sendy_flash_admin_notice('error', sprintf(
            __('The labels for order #%1$s cannot be printed (shipment status: %2$s)', 'sendy'),
            $order->get_id(),
            $status !== '' ? $status : 'unknown',
        ));

        return 'failed';
    }
}
