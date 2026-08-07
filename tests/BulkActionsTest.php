<?php

use Sendy\Api\Http\Response;
use Sendy\WooCommerce\Enums\ProcessingMethod;
use Sendy\WooCommerce\Modules\Orders\BulkActions;

/**
 * Tests for the "Sendy - Create shipments" bulk action.
 */
class BulkActionsTest extends Sendy_Ajax_TestCase
{
    public function tearDown(): void
    {
        $_REQUEST = [];

        parent::tearDown();
    }

    public function test_orders_that_do_not_resolve_are_skipped(): void
    {
        $order = new Sendy_Fake_Order(1);

        $this->fake_transport(new Response(200, [], json_encode([
            'data' => ['uuid' => 'new-shipment', 'packages' => []],
        ])));

        $redirect = $this->handle([99, 1]);

        $this->assertSame('redirect', $redirect);
        $this->assertSame('new-shipment', $order->get_meta('_sendy_shipment_id'));
    }

    public function test_a_failing_order_does_not_abort_the_rest_of_the_batch(): void
    {
        update_option('sendy_processing_method', ProcessingMethod::Sendy);

        new Sendy_Fake_Order(1);
        new Sendy_Fake_Order(2);

        // Without the sendy_shops transient every order fetches the shop
        // list, a call that create_shipment() does not guard itself.
        $transport = $this->fake_transport(
            new Response(500, [], json_encode(['message' => 'Whoops'])),
            new Response(500, [], json_encode(['message' => 'Whoops'])),
        );

        $this->handle([1, 2]);

        $this->assertCount(2, $transport->requests, 'The second order must still be attempted');

        $messages = get_option('sendy_flash_admin_messages');
        $this->assertCount(2, $messages);
        $this->assertStringContainsString('order #1', $messages[0]['message']);
        $this->assertStringContainsString('order #2', $messages[1]['message']);
    }

    private function handle(array $orderIds): string
    {
        $_REQUEST['sendy_bulk_modal_nonce'] = wp_create_nonce('sendy_bulk_modal');
        $_REQUEST['sendy_shop_id'] = 'shop-1';
        $_REQUEST['sendy_preference_id'] = 'pref-1';
        $_REQUEST['sendy_amount'] = '2';

        return (new BulkActions())->handle_bulk_action_create_shipments('redirect', 'sendy_create_shipments', $orderIds);
    }
}
