<?php

use Sendy\Api\Connection;
use Sendy\Api\Http\Response;
use Sendy\WooCommerce\ApiClientFactory;
use Sendy\WooCommerce\Enums\ProcessingMethod;
use Sendy\WooCommerce\Modules\Orders\CreateShipments;

/**
 * Tests for the sendy_create_shipments AJAX endpoint.
 *
 * The endpoint creates shipments for the selected orders that do not have
 * one yet — through a preference (WooCommerce processing method) or smart
 * rules (Sendy processing method) — and reports which orders now have a
 * shipment so the orders list print flow knows what it can print.
 */
class CreateShipmentsTest extends Sendy_Ajax_TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        new CreateShipments();
    }

    public function test_shipments_are_created_through_the_preference_for_orders_without_one(): void
    {
        $orderWithoutShipment = new Sendy_Fake_Order(1);
        new Sendy_Fake_Order(2, ['_sendy_shipment_id' => 'existing-shipment']);

        $packages = [['package_number' => '3S1', 'tracking_url' => 'https://example.com']];

        $transport = $this->fake_transport(new Response(200, [], json_encode([
            'data' => ['uuid' => 'new-shipment', 'packages' => $packages],
        ])));

        $response = $this->dispatch(['order_ids' => [1, 2]]);

        $this->assertSame(['created' => [1]], $response, 'Only the order without a shipment is reported as created');

        $this->assertSame('new-shipment', $orderWithoutShipment->get_meta('_sendy_shipment_id'));
        $this->assertSame($packages, $orderWithoutShipment->get_meta('_sendy_packages'));
        $this->assertTrue($orderWithoutShipment->was_saved());

        $this->assertCount(1, $transport->requests, 'Orders that already have a shipment must not trigger an API call');
        $this->assertStringContainsString('/shipments/preference', $transport->lastRequest->getUrl());

        // The shop and the preference are both UUID strings, so swapping them
        // anywhere along the call chain would still type check.
        $payload = json_decode($transport->lastRequest->getBody(), true);
        $this->assertSame('shop-1', $payload['shop_id']);
        $this->assertSame('pref-1', $payload['preference_id']);
        $this->assertSame(2, $payload['amount']);

        $this->assertSame('shop-1', get_option('sendy_previously_used_shop_id'));
        $this->assertSame('pref-1', get_option('sendy_previously_used_preference_id'));
        $this->assertSame('2', get_option('sendy_previously_used_amount'));
    }

    public function test_the_sendy_processing_method_is_refused(): void
    {
        // The portal creates and processes the shipments for this method, so
        // the print flow never asks this endpoint to create one. Refusing
        // rather than trusting the client keeps a browser running a cached
        // older copy of the script from adding shipments anyway.
        update_option('sendy_processing_method', ProcessingMethod::Sendy);
        set_transient('sendy_shops', ['shop-1' => 'Shop']);

        $order = new Sendy_Fake_Order(1);

        $transport = $this->fake_transport(new Response(200, [], json_encode([
            'data' => ['uuid' => 'smart-shipment'],
        ])));

        $response = $this->dispatch(['order_ids' => [1]]);

        $this->assertArrayNotHasKey('created', $response);
        $this->assertStringContainsString('created in Sendy', $response['message']);
        $this->assertSame([], $transport->requests, 'No shipment may be created');
        $this->assertFalse($order->meta_exists('_sendy_shipment_id'));
    }

    public function test_orders_whose_creation_fails_are_not_reported_as_created(): void
    {
        $order = new Sendy_Fake_Order(1);

        $this->fake_transport(new Response(422, [], json_encode([
            'message' => 'The given data was invalid',
            'errors' => ['postal_code' => ['The postal code is invalid']],
        ])));

        $response = $this->dispatch(['order_ids' => [1, 99]]);

        $this->assertSame([], $response['created'], 'Neither the failed nor the unresolvable order was created');

        $this->assertFalse($order->meta_exists('_sendy_shipment_id'));

        $messages = get_option('sendy_flash_admin_messages');
        $this->assertSame('error', $messages[0]['type']);
        $this->assertStringContainsString('order #1', $messages[0]['message']);
    }

    public function test_an_invalid_nonce_is_rejected(): void
    {
        $response = $this->dispatch(['order_ids' => [1], 'nonce' => 'invalid']);

        $this->assertSame('Nonce verification failed', $response['message']);
    }

    public function test_a_user_without_the_required_capabilities_is_rejected(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'editor']));

        $response = $this->dispatch(['order_ids' => [1]]);

        $this->assertSame('You do not have sufficient permissions to access this page.', $response['message']);
    }

    /**
     * Fire the AJAX endpoint and return the decoded JSON response.
     *
     * @param array<string,mixed> $post
     * @return array<string,mixed>
     */
    private function dispatch(array $post): array
    {
        return $this->dispatch_ajax('sendy_create_shipments', array_merge([
            'nonce' => wp_create_nonce('sendy_bulk_modal'),
            'shop_id' => 'shop-1',
            'preference_id' => 'pref-1',
            'amount' => '2',
        ], $post));
    }
}
