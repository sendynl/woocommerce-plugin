<?php

namespace Sendy\WooCommerce\Modules;

use GuzzleHttp\Exception\GuzzleException;
use Sendy\WooCommerce\ApiClientFactory;

class OAuth
{
    public function __construct()
    {
        add_action('admin_init', [$this, 'initialize_credentials']);
        add_action('admin_init', [$this, 'oauth_callback']);
    }

    /**
     * Initialize or reset the OAuth credentials
     *
     * When the client is not yet determined the manager will create a client id and secret pair. When the domain of the
     * site changed, the id/secret pair will be reset because the redirect URI for the OAuth connection will be changed
     * as well. In that case the user will need to re-authenticate with the application.
     *
     * @return void
     */
    public function initialize_credentials(): void
    {
        if (get_option('sendy_client_id') == '' || get_option('sendy_hostname') != get_site_url()) {
            update_option('sendy_client_id', wp_generate_uuid4());
            update_option('sendy_client_secret', wp_generate_password(40));

            update_option('sendy_access_token', null);
            update_option('sendy_refresh_token', null);
            update_option('sendy_token_expires', null);

            update_option('sendy_hostname', get_site_url());
        }
    }

    /**
     * Handle the OAuth callback
     *
     *
     *
     * @return void
     */
    public function oauth_callback(): void
    {
        if (isset($_GET['sendy_oauth_callback'])) {
            if (!current_user_can('manage_woocommerce')) {
                wp_die('You do not have sufficient permissions to access this page.');
            }

            if (!isset($_GET['state']) || !wp_verify_nonce(sanitize_key($_GET['state']), 'sendy_oauth_callback_nonce')) {
                wp_die('Nonce verification failed.');
            }

            if (!isset($_GET['code'])) {
                wp_die('Missing code parameter in the URL');
            }

            try {
                $connection = ApiClientFactory::buildConnectionUsingCode(sanitize_key($_GET['code']));

                $connection->checkOrAcquireAccessToken();

                sendy_flash_admin_notice('success', __('Authentication successful', 'sendy'));

                wp_safe_redirect(admin_url('admin.php?page=sendy'));
            } catch (GuzzleException $e) {
                sendy_flash_admin_notice('warning', __('Authentication failed. Please try again', 'sendy'));

                wp_safe_redirect(admin_url('admin.php?page=sendy'));
            } finally {
                exit;
            }
        }
    }
}