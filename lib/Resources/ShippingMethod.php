<?php

namespace Sendy\WooCommerce\Resources;

use Sendy\WooCommerce\ApiClientFactory;

final class ShippingMethod
{
    public function sync(array $data): array
    {
        return ApiClientFactory::buildConnectionUsingTokens()->put('/webshop_shipping_methods', [
            'shipping_methods' => $data,
        ]);
    }
}
