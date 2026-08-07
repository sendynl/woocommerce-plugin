<?php

use Sendy\Api\Http\Response;
use Sendy\WooCommerce\Enums\ProcessingMethod;
use Sendy\WooCommerce\Modules\Orders\Single;

/**
 * Tests for the sendy_order_single_save_form AJAX endpoint.
 *
 * The meta box on the order edit page posts here to create the shipment for a
 * single order, through a preference (WooCommerce processing method) or smart
 * rules (Sendy processing method).
 */
class SingleCreateShipmentTest extends Sendy_Ajax_TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        new Single();
    }

    public function test_the_shipment_is_created_from_the_chosen_preference(): void
    {
        $order = new Sendy_Fake_Order(1);

        $transport = $this->fake_transport(new Response(200, [], json_encode([
            'data' => ['uuid' => 'new-shipment', 'packages' => []],
        ])));

        $this->assertSame(['success' => true], $this->dispatch(['order_id' => '1']));

        $this->assertSame('new-shipment', $order->get_meta('_sendy_shipment_id'));
        $this->assertStringContainsString('/shipments/preference', $transport->lastRequest->getUrl());

        // The shop and the preference are both UUID strings, so swapping them
        // anywhere along the call chain would still type check.
        $payload = json_decode($transport->lastRequest->getBody(), true);
        $this->assertSame('shop-1', $payload['shop_id']);
        $this->assertSame('pref-1', $payload['preference_id']);
        $this->assertSame(3, $payload['amount']);
    }

    public function test_the_shipment_is_created_with_smart_rules_for_the_sendy_processing_method(): void
    {
        update_option('sendy_processing_method', ProcessingMethod::Sendy);
        set_transient('sendy_shops', ['shop-1' => 'Shop']);

        $order = new Sendy_Fake_Order(1);

        $transport = $this->fake_transport(new Response(200, [], json_encode([
            'data' => ['uuid' => 'smart-shipment'],
        ])));

        $this->assertSame(['success' => true], $this->dispatch(['order_id' => '1']));

        $this->assertSame('smart-shipment', $order->get_meta('_sendy_shipment_id'));
        $this->assertStringContainsString('/shipments/smart-rule', $transport->lastRequest->getUrl());
        $this->assertSame('shop-1', json_decode($transport->lastRequest->getBody(), true)['shop_id']);
    }

    public function test_a_failing_creation_reports_the_api_error(): void
    {
        $order = new Sendy_Fake_Order(1);

        $this->fake_transport(new Response(422, [], json_encode([
            'message' => 'The given data was invalid',
            'errors' => ['postal_code' => ['The postal code is invalid']],
        ])));

        $this->dispatch(['order_id' => '1']);

        $this->assertFalse($order->meta_exists('_sendy_shipment_id'));

        $messages = get_option('sendy_flash_admin_messages');
        $this->assertSame('error', $messages[0]['type']);
        $this->assertStringContainsString('The postal code is invalid', $messages[0]['message']);
    }

    /**
     * Fire the AJAX endpoint and return the decoded JSON response.
     *
     * @param array<string,mixed> $request
     * @return array<string,mixed>
     */
    private function dispatch(array $request): array
    {
        return $this->dispatch_ajax('sendy_order_single_save_form', array_merge([
            'nonce' => wp_create_nonce('sendy_create_shipment'),
            'shop_id' => 'shop-1',
            'preference_id' => 'pref-1',
            'amount' => '3',
        ], $request));
    }
}
