<?php

namespace Sendy\WooCommerce\Repositories;

class Shipments extends Repository
{
    /**
     * Fetch a shipment from the API
     *
     * @param string $shipmentId The UUID of the shipment
     * @return array<string,mixed>
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Sendy\Api\ApiException
     */
    public function get(string $shipmentId): array
    {
        return $this->connection->shipment->get($shipmentId);
    }
}
