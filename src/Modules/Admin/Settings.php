<?php

namespace Sendy\WooCommerce\Modules\Admin;

use Sendy\Api\ApiException;
use Sendy\WooCommerce\ApiClientFactory;
use Sendy\WooCommerce\Enums\ProcessingMethod;
use Sendy\WooCommerce\Modules\Admin\Fields\Checkbox;
use Sendy\WooCommerce\Modules\Admin\Fields\Dropdown;
use Sendy\WooCommerce\Plugin;
use Sendy\WooCommerce\Repositories\Shops;
use Sendy\WooCommerce\Utils\View;

class Settings
{
    public function __construct()
    {
        if (!is_admin()) {
            return;
        }

        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_init', [$this, 'logout_action']);
    }

    public function enqueue_assets(): void
    {
        wp_enqueue_script(
            'sendy-admin-settings',
            SENDY_WC_PLUGIN_DIR_URL . '/resources/js/admin-settings.js',
            [],
            Plugin::VERSION,
            true
        );
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
                update_option('sendy_access_token', null, false);
            }
        }

        echo wp_kses(
            View::fromTemplate('admin/settings.php')->render(['name' => $name]),
            View::ALLOWED_TAGS
        );
    }

    public function register_settings(): void
    {
        $slug = 'sendy';

        add_settings_section('sendy_section_id', '', '', $slug);

        register_setting('sendy_general_settings', 'sendy_import_weight', fn ($value) => $value === 'true');
        register_setting('sendy_general_settings', 'sendy_import_products', fn ($value) => $value === 'true');

        register_setting('sendy_general_settings', 'sendy_mark_order_as_completed', [
            'sanitize_callback' => function ($value): string {
                $possibleValues = [
                    'manually',
                    'after-shipment-created',
                    'after-label-printed',
                    'after-shipment-delivered',
                ];

                if (!in_array($value, $possibleValues)) {
                    return 'manually';
                }

                return $value;
            }
        ]);

        register_setting('sendy_general_settings', 'sendy_processing_method', [
            'sanitize_callback' => function ($value): string {
                if (!in_array($value, ProcessingMethod::cases())) {
                    return ProcessingMethod::WooCommerce;
                }

                return $value;
            }
        ]);

        register_setting('sendy_general_settings', 'sendy_processable_order_status', [
            'sanitize_callback' => function ($value): string {
                $orderStatuses = wc_get_order_statuses();

                $possibleValues = [];

                foreach ($orderStatuses as $status => $label) {
                    // The 'wc-' prefix is stripped as it is intended only for WooCommerce internally.
                    $possibleValues[] = str_replace('wc-', '', $status);
                }

                if (!in_array($value, $possibleValues)) {
                    return $possibleValues[0];
                }

                return $value;
            }
        ]);

        register_setting('sendy_general_settings', 'sendy_default_shop', fn ($value) => $value);

        add_settings_field(
            'sendy_processing_method',
            __('Processing method', 'sendy'),
            [$this, 'render_processing_method_dropdown'],
            $slug,
            'sendy_section_id',
        );

        $hidden = get_option('sendy_processing_method') !== ProcessingMethod::Sendy ? 'hidden' : '';

        add_settings_field(
            'sendy_processable_order_status',
            __('Send order to Sendy when status changed to', 'sendy'),
            [$this, 'render_processable_order_status_dropdown'],
            $slug,
            'sendy_section_id',
            ['class' => "sendy-processing-method-field {$hidden}"]
        );

        add_settings_field(
            'sendy_default_shop',
            __('Shop to use when orders are sent to Sendy', 'sendy'),
            [$this, 'render_default_shop_dropdown'],
            $slug,
            'sendy_section_id',
            ['class' => "sendy-processing-method-field {$hidden}"]
        );

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
        ];

        if (get_option('sendy_processing_method') === ProcessingMethod::WooCommerce) {
            $options['after-label-printed'] = __('After the label is printed', 'sendy');
        }

        if (get_option('sendy_processing_method') === ProcessingMethod::Sendy) {
            $options['after-shipment-delivered'] = __('After the shipment is delivered', 'sendy');
        }

        (new Dropdown('sendy_mark_order_as_completed'))->render(['options' => $options]);
    }

    public function render_processing_method_dropdown(): void
    {
        $options = ProcessingMethod::casesWithDescription();

        (new Dropdown('sendy_processing_method'))->render(['options' => $options]);
    }

    public function render_processable_order_status_dropdown(): void
    {
        $orderStatuses = wc_get_order_statuses();

        $options = [];

        foreach ($orderStatuses as $status => $label) {
            // The 'wc-' prefix is stripped as it is intended only for WooCommerce internally.
            $status = str_replace('wc-', '', $status);

            $options[$status] = $label;
        }

        (new Dropdown('sendy_processable_order_status'))->render(['options' => $options]);
    }

    public function render_default_shop_dropdown(): void
    {
        $options = (new Shops())->list();

        (new Dropdown('sendy_default_shop'))->render(['options' => $options]);
    }

    public function logout_action(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['sendy_logout'])) {
            update_option('sendy_access_token', null, false);

            wp_safe_redirect(admin_url('admin.php?page=sendy'));
        }
    }

}
