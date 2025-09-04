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
                update_option('sendy_access_token', $connection->getAccessToken(), false);
                update_option('sendy_refresh_token', $connection->getRefreshToken(), false);
                update_option('sendy_token_expires', $connection->getTokenExpires(), false);

                if (function_exists('wp_cache_set')) {
                    wp_cache_set('sendy_access_token', $connection->getAccessToken(), 'options');
                    wp_cache_set('sendy_refresh_token', $connection->getRefreshToken(), 'options');
                    wp_cache_set('sendy_token_expires', $connection->getTokenExpires(), 'options');
                }
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
