<?php

use Sendy\WooCommerce\Enums\ProcessingMethod;
use Sendy\WooCommerce\Modules\Orders\BulkActions;

/**
 * Tests for the data the orders list print flow receives from PHP.
 *
 * orders-list-print-flow.js decides between creating shipments and printing
 * what already exists based on the localized printExistingOnly flag, so the
 * flag has to survive the trip through wp_localize_script().
 */
class PrintFlowAssetsTest extends WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        set_current_screen('woocommerce_page_wc-orders');
    }

    public function test_the_woocommerce_processing_method_creates_and_prints(): void
    {
        update_option('sendy_processing_method', ProcessingMethod::WooCommerce);

        $data = $this->localizedData();

        $this->assertStringContainsString('var sendyPrintFlow = ', $data);
        $this->assertStringContainsString('"printExistingOnly":""', $data);
    }

    public function test_the_sendy_processing_method_only_prints_existing_labels(): void
    {
        update_option('sendy_processing_method', ProcessingMethod::Sendy);

        $this->assertStringContainsString(
            '"printExistingOnly":"1"',
            $this->localizedData(),
        );
    }

    private function localizedData(): string
    {
        $module = new BulkActions();
        $module->enqueue_assets();

        $data = wp_scripts()->get_data('sendy-orders-list-print-flow', 'data');

        $this->assertIsString($data, 'The print flow script must receive localized data');

        return $data;
    }
}
