<?php

namespace Sendy\WooCommerce\Repositories;

class Preferences extends Repository
{
    /**
     * Returns a map of shipping preferences
     *
     * The preferences are stored in the transient for the duration of one hour. The results from the API are
     * transformed to map of preferences with the UUID as key and the name as value.
     *
     * @return array<string,string>
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Sendy\Api\ApiException
     */
    public function get(): array
    {
        $preferences = get_transient('sendy_shipping_preferences');

        if (! $preferences) {
            $result = $this->connection->shippingPreference->list();

            $preferences = [];

            foreach ($result as $preference) {
                $preferences[$preference['uuid']] = $preference['name'];
            }

            set_transient('sendy_shipping_preferences', $preferences, 3600);
        }

        return $preferences;
    }
}
