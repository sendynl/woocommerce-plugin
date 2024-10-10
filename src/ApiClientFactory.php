<?php

namespace Sendy\WooCommerce;

use Sendy\Api\Connection;

class ApiClientFactory
{
    public static function buildBaseConnection(): Connection
    {
        return (new Connection())
            ->setClientId(get_option('sendy_client_id'))
            ->setClientSecret(get_option('sendy_client_secret'))
            ->setUserAgentAppendix(
                sprintf('WordPress/%s WooCommerce/%s Sendy/%s', get_bloginfo('version'), WC_VERSION, Plugin::VERSION)
            )
            ->setOauthClient(true)
            ->setRedirectUrl(sendy_oauth_redirect_url())
            ->setTokenUpdateCallback(function (Connection $connection) {
                update_option('sendy_access_token', $connection->getAccessToken());
                update_option('sendy_refresh_token', $connection->getRefreshToken());
                update_option('sendy_token_expires', $connection->getTokenExpires());
            })
        ;
    }

    public static function buildConnectionUsingCode(string $code): Connection
    {
        return self::buildBaseConnection()->setAuthorizationCode($code);
    }

    public static function buildConnectionUsingTokens(): Connection
    {
        if (get_option('sendy_access_token') == '' || get_option('sendy_refresh_token') == '' || get_option('sendy_token_expires') == '') {
            throw new \RuntimeException('Please authenticate first before using this method');
        }

        return self::buildBaseConnection()
            ->setAccessToken(get_option('sendy_access_token'))
            ->setRefreshToken(get_option('sendy_refresh_token'))
            ->setTokenExpires(get_option('sendy_token_expires'))
        ;
    }
}
