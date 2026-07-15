<?php

use Sendy\Api\Connection;
use Sendy\Api\Http\Response;
use Sendy\WooCommerce\ApiClientFactory;
use Sendy\WooCommerce\Modules\Orders\PrintLabels;

/**
 * Tests for the sendy_print_labels AJAX endpoint.
 *
 * The endpoint returns the label-fetch response from the Sendy API as-is
 * (plain wp_send_json, no success/data envelope) so the shared print-labels.js
 * can read `labels ?? documents`, forwards the x-sendy-* response headers the
 * print app needs, and marks orders as completed server-side after a
 * successful fetch when the setting asks for it.
 */
class PrintLabelsTest extends WP_Ajax_UnitTestCase
{
    private PrintLabels $module;

    public function setUp(): void
    {
        parent::setUp();

        Sendy_Fake_Order::reset();

        $this->module = new PrintLabels();

        $user = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user);
        wp_get_current_user()->add_cap('manage_woocommerce');
        wp_get_current_user()->add_cap('edit_shop_orders');

        delete_option('sendy_flash_admin_messages');
    }

    public function tearDown(): void
    {
        $this->set_api_connection(null);

        parent::tearDown();
    }

    public function test_only_orders_with_a_shipment_contribute_their_shipment_id(): void
    {
        new Sendy_Fake_Order(1, ['_sendy_shipment_id' => 'shipment-1']);
        new Sendy_Fake_Order(2);
        new Sendy_Fake_Order(4, ['_sendy_shipment_id' => 'shipment-4']);

        // Order 3 does not resolve at all, order 2 has no shipment.
        $orders = $this->module->orders_with_shipment([1, 2, 3, 4]);

        $this->assertSame(
            ['shipment-1', 'shipment-4'],
            $this->module->shipment_ids($orders)
        );
    }

    public function test_request_without_shipments_yields_a_400_with_a_flash_notice(): void
    {
        new Sendy_Fake_Order(1);

        $response = $this->dispatch(['order_ids' => [1, 2]]);

        $this->assertSame('None of the selected orders have any labels', $response['message']);
        $this->assertSame(
            [['type' => 'notice', 'message' => 'None of the selected orders have any labels']],
            get_option('sendy_flash_admin_messages')
        );
    }

    public function test_orders_are_marked_completed_when_the_setting_asks_for_it(): void
    {
        update_option('sendy_mark_order_as_completed', 'after-label-printed');

        $order = new Sendy_Fake_Order(1, ['_sendy_shipment_id' => 'shipment-1']);

        $this->assertTrue($this->module->mark_orders_as_completed([$order]));
        $this->assertSame('completed', $order->get_status());
        $this->assertSame('Sendy: Label printed', $order->get_status_note());
        $this->assertTrue($order->was_saved());
    }

    public function test_orders_are_left_alone_with_any_other_completion_setting(): void
    {
        update_option('sendy_mark_order_as_completed', 'after-shipment-created');

        $order = new Sendy_Fake_Order(1, ['_sendy_shipment_id' => 'shipment-1']);

        $this->assertFalse($this->module->mark_orders_as_completed([$order]));
        $this->assertNull($order->get_status());
        $this->assertFalse($order->was_saved());
    }

    public function test_successful_fetch_returns_the_api_response_unwrapped_with_a_reload_flag(): void
    {
        update_option('sendy_mark_order_as_completed', 'after-label-printed');

        $orderWithShipment = new Sendy_Fake_Order(1, ['_sendy_shipment_id' => 'shipment-1']);
        $orderWithoutShipment = new Sendy_Fake_Order(2);

        $transport = $this->fake_transport(200, ['x-sendy-token' => ['print-app-token']], ['labels' => 'BASE64PDF']);

        $response = $this->dispatch(['order_ids' => [1, 2]]);

        $this->assertSame('BASE64PDF', $response['labels']);
        $this->assertTrue($response['reload']);
        $this->assertArrayNotHasKey('success', $response, 'The response must not be wrapped in a wp_send_json_success envelope');

        $this->assertStringContainsString('/labels', $transport->lastRequest->getUrl());
        $this->assertStringContainsString('shipment-1', urldecode($transport->lastRequest->getUrl()));

        $this->assertSame('completed', $orderWithShipment->get_status());
        $this->assertNull($orderWithoutShipment->get_status(), 'Orders without a shipment must not be marked as completed');
    }

    public function test_no_reload_is_requested_when_nothing_changed_server_side(): void
    {
        update_option('sendy_mark_order_as_completed', 'after-shipment-created');

        $order = new Sendy_Fake_Order(1, ['_sendy_shipment_id' => 'shipment-1']);

        $this->fake_transport(200, [], ['labels' => 'BASE64PDF']);

        $response = $this->dispatch(['order_ids' => [1]]);

        $this->assertFalse($response['reload']);
        $this->assertNull($order->get_status());
    }

    public function test_api_failure_yields_a_502_with_a_flash_notice(): void
    {
        update_option('sendy_mark_order_as_completed', 'after-label-printed');

        $order = new Sendy_Fake_Order(1, ['_sendy_shipment_id' => 'shipment-1']);

        $this->fake_transport(500, [], ['message' => 'Whoops']);

        $response = $this->dispatch(['order_ids' => [1]]);

        $this->assertStringContainsString('Error while fetching labels', $response['message']);

        $messages = get_option('sendy_flash_admin_messages');
        $this->assertSame('error', $messages[0]['type']);
        $this->assertStringContainsString('Error while fetching labels', $messages[0]['message']);

        $this->assertNull($order->get_status(), 'Orders must not be marked as completed when fetching the labels fails');
    }

    public function test_an_invalid_nonce_is_rejected(): void
    {
        new Sendy_Fake_Order(1, ['_sendy_shipment_id' => 'shipment-1']);

        $response = $this->dispatch(['order_ids' => [1], 'nonce' => 'invalid']);

        $this->assertSame('Nonce verification failed', $response['message']);
    }

    public function test_a_user_without_the_required_capabilities_is_rejected(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'editor']));

        new Sendy_Fake_Order(1, ['_sendy_shipment_id' => 'shipment-1']);

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
            'action' => 'sendy_print_labels',
            'nonce' => wp_create_nonce('sendy_print_labels'),
        ], $post);

        try {
            $this->_handleAjax('sendy_print_labels');
            $this->fail('The AJAX handler was expected to send a JSON response and die');
        } catch (WPAjaxDieContinueException $exception) {
            // wp_send_json() ends in an empty wp_die(), which the AJAX test
            // case converts into this exception after buffering the output.
        }

        return json_decode($this->_last_response, true);
    }

    /**
     * Point ApiClientFactory's memoized connection at a canned HTTP response.
     */
    private function fake_transport(int $statusCode, array $headers, array $body): Sendy_Fake_Transport
    {
        $transport = new Sendy_Fake_Transport(new Response($statusCode, $headers, json_encode($body)));

        // buildConnectionUsingTokens() refuses to run with empty token options.
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
