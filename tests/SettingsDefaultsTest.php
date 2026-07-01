<?php

use Sendy\WooCommerce\Plugin;

/**
 * Regression tests for Plugin::set_default_values_for_settings().
 *
 * The method runs on every request via the `init` hook. It must initialise
 * options that have never been set, without overwriting a value the user
 * deliberately turned off - an unchecked checkbox is saved as boolean false,
 * and get_option() returns false for a missing option too, so a naive
 * `=== false` check would treat "off" as "unset" and switch it back on.
 * With a persistent object cache the false is returned as-is on the next
 * request, which is what made this reproduce in production.
 */
class SettingsDefaultsTest extends WP_UnitTestCase
{
    public function test_disabled_checkbox_is_not_reset_to_its_default(): void
    {
        // The default-setter has already run once and turned the option on
        // (this is why unchecking is a real true -> false change that persists;
        // update_option() would no-op if false were written to a never-set option).
        update_option('sendy_import_products', true);

        // The user unticks "send products" and saves.
        update_option('sendy_import_products', false);

        Plugin::instance()->set_default_values_for_settings();

        $this->assertFalse(
            (bool) get_option('sendy_import_products'),
            'A checkbox saved as false must stay off, not be reset to its default'
        );
    }

    public function test_missing_option_receives_its_default(): void
    {
        delete_option('sendy_import_products');

        Plugin::instance()->set_default_values_for_settings();

        $this->assertTrue(
            (bool) get_option('sendy_import_products'),
            'A never-configured option should be initialised to its default'
        );
    }

    public function test_enabled_checkbox_is_preserved(): void
    {
        update_option('sendy_import_products', true);

        Plugin::instance()->set_default_values_for_settings();

        $this->assertTrue(
            (bool) get_option('sendy_import_products'),
            'An enabled setting must be left enabled'
        );
    }

    public function test_string_setting_is_not_overwritten_when_already_set(): void
    {
        update_option('sendy_processing_method', 'sendy');

        Plugin::instance()->set_default_values_for_settings();

        $this->assertSame(
            'sendy',
            get_option('sendy_processing_method'),
            'A configured non-default value must be preserved'
        );
    }
}
