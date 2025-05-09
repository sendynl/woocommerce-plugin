<?php

namespace Sendy\WooCommerce;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use Sendy\WooCommerce\Modules\Admin\Settings;
use Sendy\WooCommerce\Modules\Checkout;
use Sendy\WooCommerce\Modules\OAuth;
use Sendy\WooCommerce\Modules\Orders\BulkActions;
use Sendy\WooCommerce\Modules\Orders\ProcessInBackground;
use Sendy\WooCommerce\Modules\Orders\OrderList;
use Sendy\WooCommerce\Modules\Orders\Single;
use Sendy\WooCommerce\Modules\ShippingMethodsSynchronizer;
use Sendy\WooCommerce\Modules\Webhooks;
use Sendy\WooCommerce\ShippingMethods\PickupPointDelivery;
use Sendy\WooCommerce\ShippingMethods\StandardDelivery;
use WC_Shipping_Method;

class Plugin
{
    public const VERSION = '3.2.1';

    public const SETTINGS_ID = 'sendy';

    private static Plugin $instance;

    private array $modules = [];

    private function __construct()
    {
        add_action('init', [$this, 'initialize_plugin'], 0);
        add_action('before_woocommerce_init', [$this, 'declare_wc_hpos_compatibility'], 10);
        add_action('before_woocommerce_init', [$this, 'declare_checkout_blocks_incompatibility'], 10);
    }

    public static function instance(): Plugin
    {
        return self::$instance ??= new self();
    }

    public function initialize_plugin(): void
    {
        $this->set_default_values_for_settings();
        $this->define_constants();
        $this->init_hooks();
        $this->init_internationalization();
        $this->init_cron();
    }

    public function declare_wc_hpos_compatibility(): void
    {
        if (class_exists('Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            FeaturesUtil::declare_compatibility('custom_order_tables', SENDY_WC_PLUGIN_BASENAME);
        }
    }

    public function declare_checkout_blocks_incompatibility(): void
    {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            FeaturesUtil::declare_compatibility('cart_checkout_blocks', SENDY_WC_PLUGIN_BASENAME, false);
        }
    }

    private function define_constants(): void
    {
        $upload_dir = wp_upload_dir();

        define('SENDY_WC_PLUGIN_DIR_PATH', untrailingslashit(plugin_dir_path(SENDY_WC_PLUGIN_FILE)));
        define('SENDY_WC_PLUGIN_DIR_URL', untrailingslashit(plugins_url('/', SENDY_WC_PLUGIN_FILE)));
        define('SENDY_WC_VERSION', self::VERSION);
        define('SENDY_SETTINGS_ID', self::SETTINGS_ID);
        define('SENDY_LOG_DIR', $upload_dir['basedir'] . '/sendy-logs');
        define('SENDY_UPLOAD_DIR', $upload_dir['basedir'] . '/sendy');
    }

    private function init_hooks(): void
    {
        add_action('woocommerce_shipping_methods', [$this, 'add_shipping_methods']);

        add_action('init', [$this, 'initialize_modules']);

        if (is_admin()) {
            add_action('admin_notices', [$this, 'display_admin_notices']);
        }
    }

    private function init_internationalization(): void
    {
        // This filter is added in order to override the translations from the WordPress translations directory if a
        // translation is provided by the plug-in.
        add_filter('load_textdomain_mofile', function (string $moFile, string $domain) {
            $basePath = WP_CONTENT_DIR . '/plugins/sendy/languages/';

            if ($domain === 'sendy' && str_starts_with($moFile, WP_LANG_DIR . '/plugins/') !== false) {
                $locale = apply_filters('plugin_locale', determine_locale(), $domain);

                $filename = $basePath . '/sendy-' . $locale . '.mo';

                if (is_readable($filename)) {
                    return $filename;
                }
            }

            return $moFile;
        }, 10, 2);

        load_plugin_textdomain('sendy', false, untrailingslashit(dirname(SENDY_WC_PLUGIN_BASENAME)) . '/languages');
    }

    private function init_cron(): void
    {
        if (!wp_next_scheduled('sendy_cron')) {
            wp_schedule_event(time(), 'hourly', 'sendy_cron');
        }
    }

    public function initialize_modules(): void
    {
        $this->modules['oauth'] = new OAuth();
        $this->modules['admin_settings'] = new Settings();

        if (sendy_is_authenticated()) {
            $this->modules['orders_bulk_actions'] = new BulkActions();
            $this->modules['orders_list'] = new OrderList();
            $this->modules['orders_single'] = new Single();
            $this->modules['checkout'] = new Checkout();
            $this->modules['webhooks'] = new Webhooks();
            $this->modules['orders_sendy'] = new ProcessInBackground();
            $this->modules['shipping_methods_synchronizer'] = new ShippingMethodsSynchronizer();
        }
    }

    /**
     * @param array<string,WC_Shipping_Method> $shippingMethods
     * @return array<string,WC_Shipping_Method>
     */
    public function add_shipping_methods(array $shippingMethods): array
    {
        $shippingMethods[StandardDelivery::ID] = StandardDelivery::class;
        $shippingMethods[PickupPointDelivery::ID] = PickupPointDelivery::class;

        return $shippingMethods;
    }

    public function display_admin_notices(): void
    {
        if (is_array(get_option('sendy_flash_admin_messages'))) {
            $messages = get_option('sendy_flash_admin_messages');

            foreach ($messages as $message) {
                printf('<div class="notice notice-%s">%s</div>', esc_html($message['type']), esc_html($message['message']));
            }

            delete_option('sendy_flash_admin_messages');
        }
    }

    public function set_default_values_for_settings(): void
    {
        $defaultValues = [
            'sendy_import_weight' => true,
            'sendy_import_products' => true,
            'sendy_mark_order_as_completed' => 'after-shipment-created',
            'sendy_processing_method' => 'woocommerce',
            'sendy_processable_status' => 'processing',
        ];

        foreach ($defaultValues as $option => $defaultValue) {
            if (get_option($option) === false) {
                update_option($option, $defaultValue);
            }
        }
    }

    public function deactivate(): void
    {
        if (wp_next_scheduled('sendy_cron')) {
            wp_clear_scheduled_hook('sendy_cron');
        }

        foreach ($this->modules as $module) {
            if (method_exists($module, 'deactivate')) {
                $module->deactivate();
            }
        }
    }
}
