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
class CreateShipmentsTest extends WP_Ajax_UnitTestCase
{
    private CreateShipments $module;

    public function setUp(): void
    {
        parent::setUp();

        Sendy_Fake_Order::reset();

        $this->module = new CreateShipments();

        $user = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user);
        wp_get_current_user()->add_cap('manage_woocommerce');
        wp_get_current_user()->add_cap('edit_shop_orders');

        update_option('sendy_processing_method', ProcessingMethod::WooCommerce);
        delete_option('sendy_flash_admin_messages');
    }

    public function tearDown(): void
    {
        $this->set_api_connection(null);

        parent::tearDown();
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

        $this->assertSame([1], $response['created']);
        $this->assertSame([], $response['failed']);

        $this->assertSame('new-shipment', $orderWithoutShipment->get_meta('_sendy_shipment_id'));
        $this->assertSame($packages, $orderWithoutShipment->get_meta('_sendy_packages'));
        $this->assertTrue($orderWithoutShipment->was_saved());

        $this->assertCount(1, $transport->requests, 'Orders that already have a shipment must not trigger an API call');
        $this->assertStringContainsString('/shipments/preference', $transport->lastRequest->getUrl());

        $this->assertSame('shop-1', get_option('sendy_previously_used_shop_id'));
        $this->assertSame('pref-1', get_option('sendy_previously_used_preference_id'));
        $this->assertSame('2', get_option('sendy_previously_used_amount'));
    }

    public function test_shipments_are_created_with_smart_rules_for_the_sendy_processing_method(): void
    {
        update_option('sendy_processing_method', ProcessingMethod::Sendy);
        set_transient('sendy_shops', ['shop-1' => 'Shop']);

        $order = new Sendy_Fake_Order(1);

        $transport = $this->fake_transport(new Response(200, [], json_encode([
            'data' => ['uuid' => 'smart-shipment'],
        ])));

        $response = $this->dispatch(['order_ids' => [1]]);

        $this->assertSame([1], $response['created']);
        $this->assertSame('smart-shipment', $order->get_meta('_sendy_shipment_id'));
        $this->assertStringContainsString('/shipments/smart-rule', $transport->lastRequest->getUrl());
    }

    public function test_orders_whose_creation_fails_are_reported_as_failed(): void
    {
        $order = new Sendy_Fake_Order(1);

        $this->fake_transport(new Response(422, [], json_encode([
            'message' => 'The given data was invalid',
            'errors' => ['postal_code' => ['The postal code is invalid']],
        ])));

        $response = $this->dispatch(['order_ids' => [1, 99]]);

        $this->assertSame([], $response['created']);
        $this->assertSame([1, 99], $response['failed'], 'Unresolvable orders count as failed');

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
        $_POST = array_merge([
            'action' => 'sendy_create_shipments',
            'nonce' => wp_create_nonce('sendy_bulk_modal'),
            'shop_id' => 'shop-1',
            'preference_id' => 'pref-1',
            'amount' => '2',
        ], $post);

        try {
            $this->_handleAjax('sendy_create_shipments');
            $this->fail('The AJAX handler was expected to send a JSON response and die');
        } catch (WPAjaxDieContinueException $exception) {
            // wp_send_json() ends in an empty wp_die().
        }

        return json_decode($this->_last_response, true);
    }

    /**
     * Point ApiClientFactory's memoized connection at canned HTTP responses.
     */
    private function fake_transport(Response ...$responses): Sendy_Fake_Transport
    {
        $transport = new Sendy_Fake_Transport(...$responses);

        update_option('sendy_access_token', 'access-token', false);
        update_option('sendy_refresh_token', 'refresh-token', false);
        update_option('sendy_token_expires', time() + 3600, false);

        $this->set_api_connection(
            (new Connection())
                ->setTransport($transport)
                ->setAccessToken('access-token')
                ->setRefreshToken('refresh-token')
                ->setTokenExpires(time() + 3600)
        );

        return $transport;
    }

    private function set_api_connection(?Connection $connection): void
    {
        $property = new ReflectionProperty(ApiClientFactory::class, 'connection');
        $property->setAccessible(true);
        $property->setValue(null, $connection);
    }
}
