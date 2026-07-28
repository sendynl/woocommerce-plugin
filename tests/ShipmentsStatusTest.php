<?php

use Sendy\Api\Connection;
use Sendy\Api\Http\Response;
use Sendy\WooCommerce\ApiClientFactory;
use Sendy\WooCommerce\Enums\ProcessingMethod;
use Sendy\WooCommerce\Modules\Orders\CreateShipments;

/**
 * Tests for the sendy_shipments_status AJAX endpoint.
 *
 * The endpoint reports per order whether its labels can be printed (used by
 * the orders list print flow to decide between printing directly, creating a
 * shipment first, or polling until the portal has generated the label), and
 * persists the packages meta once a shipment reaches the generated status.
 */
class ShipmentsStatusTest extends WP_Ajax_UnitTestCase
{
    use Sendy_Fakes_Api_Connection;

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

    public function test_orders_without_a_shipment_or_that_do_not_resolve_are_reported_as_none(): void
    {
        new Sendy_Fake_Order(1);

        $response = $this->dispatch(['order_ids' => [1, 2]]);

        $this->assertSame(['1' => 'none', '2' => 'none'], $this->stringKeys($response['statuses']));
    }

    public function test_orders_with_packages_meta_are_ready_without_touching_the_api(): void
    {
        new Sendy_Fake_Order(1, [
            '_sendy_shipment_id' => 'shipment-1',
            '_sendy_packages' => [['package_number' => '3S1', 'tracking_url' => 'https://example.com']],
        ]);

        $transport = $this->fake_transport(new Response(200, [], json_encode([])));

        $response = $this->dispatch(['order_ids' => [1]]);

        $this->assertSame(['1' => 'ready'], $this->stringKeys($response['statuses']));
        $this->assertNull($transport->lastRequest, 'The API must not be called when the packages meta is present');
    }

    public function test_a_generated_shipment_is_ready_and_its_packages_are_persisted(): void
    {
        $order = new Sendy_Fake_Order(1, ['_sendy_shipment_id' => 'shipment-1']);

        $packages = [['package_number' => '3S1', 'tracking_url' => 'https://example.com']];

        $transport = $this->fake_transport(new Response(200, [], json_encode([
            'data' => ['uuid' => 'shipment-1', 'status' => 'generated', 'packages' => $packages],
        ])));

        $response = $this->dispatch(['order_ids' => [1]]);

        $this->assertSame(['1' => 'ready'], $this->stringKeys($response['statuses']));
        $this->assertSame($packages, $order->get_meta('_sendy_packages'));
        $this->assertTrue($order->was_saved());
        $this->assertStringContainsString('/shipments/shipment-1', $transport->lastRequest->getUrl());
    }

    public function test_new_and_pending_shipments_are_reported_as_pending(): void
    {
        new Sendy_Fake_Order(1, ['_sendy_shipment_id' => 'shipment-1']);
        new Sendy_Fake_Order(2, ['_sendy_shipment_id' => 'shipment-2']);

        $this->fake_transport(
            new Response(200, [], json_encode(['data' => ['uuid' => 'shipment-1', 'status' => 'new', 'packages' => []]])),
            new Response(200, [], json_encode(['data' => ['uuid' => 'shipment-2', 'status' => 'pending', 'packages' => []]])),
        );

        $response = $this->dispatch(['order_ids' => [1, 2]]);

        $this->assertSame(['1' => 'pending', '2' => 'pending'], $this->stringKeys($response['statuses']));
    }

    public function test_a_pending_shipment_flashes_no_notice_without_the_flash_unprintable_flag(): void
    {
        new Sendy_Fake_Order(1, ['_sendy_shipment_id' => 'shipment-1']);

        $this->fake_transport(
            new Response(200, [], json_encode(['data' => ['uuid' => 'shipment-1', 'status' => 'pending', 'packages' => []]])),
        );

        $response = $this->dispatch(['order_ids' => [1]]);

        $this->assertSame(['1' => 'pending'], $this->stringKeys($response['statuses']));
        $this->assertFalse(get_option('sendy_flash_admin_messages'));
    }

    public function test_a_pending_shipment_flashes_a_warning_notice_with_the_flash_unprintable_flag(): void
    {
        new Sendy_Fake_Order(1, ['_sendy_shipment_id' => 'shipment-1']);

        $this->fake_transport(
            new Response(200, [], json_encode(['data' => ['uuid' => 'shipment-1', 'status' => 'pending', 'packages' => []]])),
        );

        $response = $this->dispatch(['order_ids' => [1], 'flash_unprintable' => '1']);

        $this->assertSame(['1' => 'pending'], $this->stringKeys($response['statuses']));

        $messages = get_option('sendy_flash_admin_messages');
        $this->assertSame('warning', $messages[0]['type']);
        $this->assertStringContainsString('still being generated', $messages[0]['message']);
        $this->assertStringContainsString('order #1', $messages[0]['message']);
    }

    public function test_the_sendy_processing_method_explains_that_the_shipment_needs_processing(): void
    {
        update_option('sendy_processing_method', ProcessingMethod::Sendy);

        new Sendy_Fake_Order(1, ['_sendy_shipment_id' => 'shipment-1']);

        $this->fake_transport(
            new Response(200, [], json_encode(['data' => ['uuid' => 'shipment-1', 'status' => 'new', 'packages' => []]])),
        );

        $response = $this->dispatch(['order_ids' => [1], 'flash_unprintable' => '1']);

        $this->assertSame(['1' => 'pending'], $this->stringKeys($response['statuses']));

        // Waiting is pointless in this mode: the portal generates the label.
        $messages = get_option('sendy_flash_admin_messages');
        $this->assertCount(1, $messages);
        $this->assertSame('warning', $messages[0]['type']);
        $this->assertStringContainsString('order #1', $messages[0]['message']);
        $this->assertStringContainsString('Process the shipment in Sendy', $messages[0]['message']);
        $this->assertStringNotContainsString('try again in a moment', $messages[0]['message']);
    }

    public function test_several_unprintable_orders_are_counted_in_one_notice(): void
    {
        // A whole page of orders can be unprintable; naming each one would bury
        // the page in notices.
        update_option('sendy_processing_method', ProcessingMethod::Sendy);

        new Sendy_Fake_Order(1, ['_sendy_shipment_id' => 'shipment-1']);
        new Sendy_Fake_Order(2);
        new Sendy_Fake_Order(3, [
            '_sendy_shipment_id' => 'shipment-3',
            '_sendy_packages' => [['package_number' => '3S1']],
        ]);

        $this->fake_transport(
            new Response(200, [], json_encode(['data' => ['uuid' => 'shipment-1', 'status' => 'new', 'packages' => []]])),
        );

        $this->dispatch(['order_ids' => [1, 2, 3], 'flash_unprintable' => '1']);

        $messages = get_option('sendy_flash_admin_messages');
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('for 2 orders', $messages[0]['message'], 'The printable order is not counted');
        $this->assertStringContainsString('Process the shipments in Sendy', $messages[0]['message']);
    }

    public function test_several_pending_orders_are_counted_in_one_notice(): void
    {
        new Sendy_Fake_Order(1, ['_sendy_shipment_id' => 'shipment-1']);
        new Sendy_Fake_Order(2, ['_sendy_shipment_id' => 'shipment-2']);

        $this->fake_transport(
            new Response(200, [], json_encode(['data' => ['uuid' => 'shipment-1', 'status' => 'pending', 'packages' => []]])),
            new Response(200, [], json_encode(['data' => ['uuid' => 'shipment-2', 'status' => 'pending', 'packages' => []]])),
        );

        $this->dispatch(['order_ids' => [1, 2], 'flash_unprintable' => '1']);

        $messages = get_option('sendy_flash_admin_messages');
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('labels for 2 orders are still being generated', $messages[0]['message']);
    }

    public function test_a_failed_shipment_is_reported_as_failed_with_a_flash_notice(): void
    {
        new Sendy_Fake_Order(1, ['_sendy_shipment_id' => 'shipment-1']);

        $this->fake_transport(new Response(200, [], json_encode([
            'data' => ['uuid' => 'shipment-1', 'status' => 'failed', 'packages' => []],
        ])));

        $response = $this->dispatch(['order_ids' => [1]]);

        $this->assertSame(['1' => 'failed'], $this->stringKeys($response['statuses']));

        $messages = get_option('sendy_flash_admin_messages');
        $this->assertSame('error', $messages[0]['type']);
        $this->assertStringContainsString('order #1', $messages[0]['message']);
        $this->assertStringContainsString('failed', $messages[0]['message']);
    }

    public function test_an_api_error_is_reported_as_failed_with_a_flash_notice(): void
    {
        new Sendy_Fake_Order(1, ['_sendy_shipment_id' => 'shipment-1']);

        $this->fake_transport(new Response(500, [], json_encode(['message' => 'Whoops'])));

        $response = $this->dispatch(['order_ids' => [1]]);

        $this->assertSame(['1' => 'failed'], $this->stringKeys($response['statuses']));

        $messages = get_option('sendy_flash_admin_messages');
        $this->assertSame('error', $messages[0]['type']);
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
            'action' => 'sendy_shipments_status',
            'nonce' => wp_create_nonce('sendy_print_labels'),
        ], $post);

        try {
            $this->_handleAjax('sendy_shipments_status');
            $this->fail('The AJAX handler was expected to send a JSON response and die');
        } catch (WPAjaxDieContinueException $exception) {
            // wp_send_json() ends in an empty wp_die().
        }

        return json_decode($this->_last_response, true);
    }

    /**
     * json_decode() turns numeric JSON object keys into PHP int keys; convert
     * them back so assertions can use one canonical shape.
     *
     * @param array<int|string,string> $statuses
     * @return array<string,string>
     */
    private function stringKeys(array $statuses): array
    {
        $result = [];

        foreach ($statuses as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }
}
