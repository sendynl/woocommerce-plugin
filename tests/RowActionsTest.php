<?php

use Sendy\WooCommerce\Enums\ProcessingMethod;
use Sendy\WooCommerce\Modules\Orders\RowActions;

/**
 * Tests for the print label action button in the orders list actions column.
 */
class RowActionsTest extends WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Sendy_Fake_Order::reset();

        update_option('sendy_processing_method', ProcessingMethod::WooCommerce);
    }

    public function test_orders_with_a_shipment_get_a_print_label_action(): void
    {
        $order = new Sendy_Fake_Order(1, ['_sendy_shipment_id' => 'shipment-1']);

        $actions = (new RowActions())->add_print_label_action(['complete' => ['action' => 'complete']], $order);

        $this->assertArrayHasKey('complete', $actions, 'Existing actions must be preserved');
        $this->assertSame([
            'url' => '#',
            'name' => 'Sendy - Print label',
            'action' => 'sendy_print_label',
        ], $actions['sendy_print_label']);
        $this->assertArrayNotHasKey('sendy_create_shipment_print_label', $actions);
    }

    public function test_orders_without_a_shipment_get_a_create_and_print_action(): void
    {
        $order = new Sendy_Fake_Order(1);

        $actions = (new RowActions())->add_print_label_action([], $order);

        $this->assertSame([
            'url' => '#',
            'name' => 'Sendy - Create shipment and print label',
            'action' => 'sendy_create_shipment_print_label',
        ], $actions['sendy_create_shipment_print_label']);
        $this->assertArrayNotHasKey('sendy_print_label', $actions);
    }

    public function test_the_sendy_processing_method_only_offers_to_print(): void
    {
        // The portal creates and processes the shipments for this method, so
        // the button must not promise a create-and-print it will not do.
        update_option('sendy_processing_method', ProcessingMethod::Sendy);

        $actions = (new RowActions())->add_print_label_action([], new Sendy_Fake_Order(1));

        $this->assertSame('sendy_print_label', $actions['sendy_print_label']['action']);
        $this->assertArrayNotHasKey('sendy_create_shipment_print_label', $actions);
    }

    public function test_the_filter_is_registered(): void
    {
        $module = new RowActions();

        $this->assertSame(10, has_filter('woocommerce_admin_order_actions', [$module, 'add_print_label_action']));
    }
}
