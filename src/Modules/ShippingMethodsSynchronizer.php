<?php

namespace Sendy\WooCommerce\Modules;

use Sendy\Api\ApiException;
use Sendy\WooCommerce\ApiClientFactory;
use Sendy\WooCommerce\Enums\ProcessingMethod;
use Sendy\WooCommerce\Resources\ShippingMethod;

class ShippingMethodsSynchronizer
{
    public function __construct()
    {
        if (get_option('sendy_processing_method') === ProcessingMethod::Sendy) {
            add_action('admin_init', [$this, 'synchronize_shipping_methods']);
            add_action('woocommerce_shipping_zone_method_added', [$this, 'handle_method_added'], 10, 3);
            add_action('woocommerce_shipping_zone_method_deleted', [$this, 'handle_method_deleted'], 10, 2);
        }
    }

    public function synchronize_shipping_methods(): void
    {
        if (get_option('sendy_shipping_methods_last_sync') > time() - 24 * 60 * 60) {
            return;
        }

        $this->synchronizeShippingMethods();
    }

    public function handle_method_added($instanceId, $methodId, $zoneId): void
    {
        $this->synchronizeShippingMethods();
    }

    public function handle_method_deleted($instanceId, $zoneId): void
    {
        $this->synchronizeShippingMethods();
    }

    private function synchronizeShippingMethods(): void
    {
        $data = [];

        $zones = \WC_Shipping_Zones::get_zones();

        foreach ($zones as $zone) {
            foreach ($zone['shipping_methods'] as $instanceId => $method) {
                $data[] = [
                    'external_id' => (string) $instanceId,
                    'name' => "{$zone['zone_name']} - {$method->get_title()}",
                ];
            }
        }

        // The 'Rest of the world' zone is treated differently than other zones and will always have '0' as the ID
        $defaultZone = new \WC_Shipping_Zone(0);

        foreach ($defaultZone->get_shipping_methods() as $instanceId => $method) {
            $data[] = [
                'external_id' => (string) $instanceId,
                'name' => __('Rest of the world', 'sendy') . " - " . $method->get_title(),
            ];
        }

        try {
            $endpoint = new ShippingMethod(ApiClientFactory::buildConnectionUsingTokens());
            $endpoint->sync($data);
        } catch (ApiException $exception) {
            // TODO Implement logging
        }

        update_option('sendy_shipping_methods_last_sync', time());
    }
}
