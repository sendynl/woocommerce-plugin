<?php

namespace Sendy\WooCommerce;

use Sendy\Api\Connection;

class ApiClientFactory
{
    private static ?Connection $connection = null;

    public static function buildBaseConnection(): Connection
    {
        return (new Connection())
            ->setTransport(new Transport())
            ->setClientId(get_option('sendy_client_id'))
            ->setClientSecret(get_option('sendy_client_secret'))
            ->setUserAgentAppendix(
                sprintf('WooCommerce/%s Sendy/%s', WC_VERSION, Plugin::VERSION),
            )
            ->setOauthClient(true)
            ->setRedirectUrl(sendy_oauth_redirect_url())
            ->setTokenUpdateCallback(function (Connection $connection) {
                update_option('sendy_access_token', $connection->getAccessToken(), false);
                update_option('sendy_refresh_token', $connection->getRefreshToken(), false);
                update_option('sendy_token_expires', $connection->getTokenExpires(), false);
            })
        ;
    }

    public static function buildConnectionUsingCode(string $code): Connection
    {
        if (! self::$connection instanceof Connection) {
            self::$connection = self::buildBaseConnection()->setAuthorizationCode($code);
        }

        return self::$connection;
    }

    public static function buildConnectionUsingTokens(): Connection
    {
        if (get_option('sendy_access_token') == '' || get_option('sendy_refresh_token') == '' || get_option('sendy_token_expires') == '') {
            throw new \RuntimeException('Please authenticate first before using this method');
        }

        if (! self::$connection instanceof Connection) {
            return self::$connection = self::buildBaseConnection()
                ->setAccessToken(get_option('sendy_access_token'))
                ->setRefreshToken(get_option('sendy_refresh_token'))
                ->setTokenExpires(get_option('sendy_token_expires'))
            ;
        }

        return self::$connection;
    }
}
