<?php

namespace Sendy\WooCommerce\Repositories;

use Sendy\Api\Connection;
use Sendy\WooCommerce\ApiClientFactory;

abstract class Repository
{
    protected function connection(): Connection
    {
        return ApiClientFactory::buildConnectionUsingTokens();
    }
}
