<?php

use Sendy\WooCommerce\Plugin;

/**
 * Uninstalling must wipe all plugin options, so a shop that changes hands can
 * reconnect with fresh credentials. Deactivating must leave the credentials
 * and settings alone.
 */
class UninstallTest extends WP_UnitTestCase
{
    // Bookkeeping that the modules clean up on deactivation; deactivating must
    // not touch any other option.
    private const MODULE_STATE_OPTIONS = [
        'sendy_webhook_id',
        'sendy_webhook_last_checked',
        'sendy_shipping_methods_last_sync',
    ];

    private const OPTIONS = [
        'sendy_client_id',
        'sendy_client_secret',
        'sendy_hostname',
        'sendy_access_token',
        'sendy_refresh_token',
        'sendy_token_expires',
        'sendy_webhook_secret',
        'sendy_webhook_id',
        'sendy_webhook_last_checked',
        'sendy_shipping_methods_last_sync',
        'sendy_flash_admin_messages',
        'sendy_import_weight',
        'sendy_import_products',
        'sendy_mark_order_as_completed',
        'sendy_processing_method',
        'sendy_processable_order_status',
        'sendy_default_shop',
        'sendy_previously_used_preference_id',
        'sendy_previously_used_amount',
        'sendy_previously_used_shop_id',
    ];

    public function test_uninstall_deletes_all_plugin_options(): void
    {
        foreach (self::OPTIONS as $option) {
            update_option($option, 'some-value');
        }

        $this->run_uninstall_script();

        foreach (self::OPTIONS as $option) {
            $this->assertFalse(
                get_option($option),
                "Option {$option} should be deleted on uninstall"
            );
        }
    }

    public function test_uninstall_deletes_the_shops_transient(): void
    {
        set_transient('sendy_shops', [['id' => 1]], 3600);

        $this->run_uninstall_script();

        $this->assertFalse(get_transient('sendy_shops'));
    }

    public function test_deactivation_does_not_delete_the_options(): void
    {
        $options = array_diff(self::OPTIONS, self::MODULE_STATE_OPTIONS);

        foreach ($options as $option) {
            update_option($option, 'some-value');
        }

        Plugin::instance()->deactivate();

        foreach ($options as $option) {
            $this->assertSame(
                'some-value',
                get_option($option),
                "Option {$option} should survive deactivation"
            );
        }
    }

    private function run_uninstall_script(): void
    {
        // WordPress defines this constant before including uninstall.php; the
        // script must refuse to run without it.
        if (! defined('WP_UNINSTALL_PLUGIN')) {
            define('WP_UNINSTALL_PLUGIN', 'sendy/sendy.php');
        }

        require dirname(__DIR__) . '/uninstall.php';
    }
}
