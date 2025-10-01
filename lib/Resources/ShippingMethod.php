<?php

namespace Sendy\WooCommerce\Resources;

use Sendy\Api\Resources\Resource;

final class ShippingMethod extends Resource
{
    public function sync(array $data): array
    {
        return $this->connection->put('/webshop_shipping_methods', [
            'shipping_methods' => $data,
        ]);
    }
}
