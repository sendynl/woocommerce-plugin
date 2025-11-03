<?php

namespace Sendy\WooCommerce\Repositories;

class Shops extends Repository
{
    /**
     * Returns a map of shops
     *
     * The shops are stored in the transient for the duration of one hour. The results from the API are transformed to
     * a map of shops with the UUID as key and the name as value.
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Sendy\Api\ApiException
     */
    public function list(): array
    {
        $shops = get_transient('sendy_shops');

        if (! $shops) {
            $result = $this->connection()->shop->list();

            $shops = [];

            foreach ($result as $shop) {
                $shops[$shop['uuid']] = $shop['name'];
            }

            set_transient('sendy_shops', $shops, 3600);
        }

        return $shops;
    }
}
