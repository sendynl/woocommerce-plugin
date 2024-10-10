<?php

namespace Sendy\WooCommerce\Modules\Admin;

use Sendy\Api\ApiException;
use Sendy\WooCommerce\ApiClientFactory;
use Sendy\WooCommerce\Modules\Admin\Fields\Checkbox;
use Sendy\WooCommerce\Modules\Admin\Fields\Dropdown;
use Sendy\WooCommerce\Utils\View;

class Settings
{
    public function __construct()
    {
        if (!is_admin()) {
            return;
        }

        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_init', [$this, 'logout_action']);
    }

    /**
     * Add the settings page for the module as a submenu item for the WooCommerce menu
     *
     * @return void
     */
    public function register_menu(): void
    {
        add_submenu_page(
            'woocommerce',
            __('Sendy', 'sendy'),
            __('Sendy', 'sendy'),
            'manage_woocommerce',
            'sendy',
            [$this, 'render_settings_page'],
        );
    }

    /**
     * Render the settings page
     *
     * @return void
     */
    public function render_settings_page(): void
    {
        $name = null;

        if (sendy_is_authenticated()) {
            try {
                $result = ApiClientFactory::buildConnectionUsingTokens()->me->get();

                $name = $result['name'];
            } catch (ApiException $e) {
                // When this action fails, it will be because the access token is invalid. The best option is to null
                // the access token option and restart the authentication process from the start. Only resetting the
                // access token is sufficient as it will not generate multiple instances of the WooCommerce integration
                // in the Sendy application
                update_option('sendy_access_token', null);
            }
        }

        echo View::fromTemplate('admin/settings.php')->render(['name' => $name]);
    }

    public function register_settings(): void
    {
        $slug = 'sendy';

        add_settings_section('sendy_section_id','', '', $slug);

        register_setting('sendy_general_settings', 'sendy_import_weight', function ($value) { return $value === 'true'; } );
        register_setting('sendy_general_settings', 'sendy_import_products',  function ($value) { return $value === 'true'; });
        register_setting('sendy_general_settings', 'sendy_mark_order_as_completed');

        add_settings_field(
            'sendy_import_weight',
            __('Import weight', 'sendy'),
            [$this, 'render_import_weight_field'],
            $slug,
            'sendy_section_id'
        );

        add_settings_field(
            'sendy_import_products',
            __('Import products', 'sendy'),
            [$this, 'render_import_products_field'],
            $slug,
            'sendy_section_id',
        );

        add_settings_field(
            'sendy_mark_order_as_completed',
            __('Mark order as completed', 'sendy'),
            [$this, 'render_status_after_shipping_field'],
            $slug,
            'sendy_section_id',
        );
    }

    public function render_import_weight_field(): void
    {
        (new Checkbox('sendy_import_weight', __('Import weight when creating a shipment', 'sendy')))->render();
    }

    public function render_import_products_field(): void
    {
        (new Checkbox('sendy_import_products', __('Send products to Sendy when creating a shipment', 'sendy')))->render();
    }

    public function render_status_after_shipping_field(): void
    {
        $options = [
            'manually' => __('Manually', 'sendy'),
            'after-shipment-created' => __('After the shipment is created', 'sendy'),
            'after-label-printed' => __('After the label is printed', 'sendy'),
        ];

        (new Dropdown('sendy_mark_order_as_completed'))->render(['options' => $options]);
    }

    public function logout_action(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['sendy_logout'])) {
            update_option('sendy_client_id', '');

            wp_safe_redirect(admin_url('admin.php?page=sendy'));
        }
    }

}
