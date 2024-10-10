<?php

namespace Sendy\WooCommerce\Repositories;

use Sendy\Api\Connection;
use Sendy\WooCommerce\ApiClientFactory;

abstract class Repository
{
    protected Connection $connection;

    public function __construct()
    {
        $this->connection = ApiClientFactory::buildConnectionUsingTokens();
    }
}
