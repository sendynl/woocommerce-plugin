<?php

use Sendy\WooCommerce\Plugin;

/**
 * Tests for the asset cache-busting version helper.
 *
 * Releases invalidate browser caches through the plugin version; with
 * SCRIPT_DEBUG enabled the file modification time is used instead so a
 * development environment cannot serve stale assets.
 */
class AssetVersionTest extends WP_UnitTestCase
{
    public function test_missing_files_fall_back_to_the_plugin_version(): void
    {
        $this->assertSame(Plugin::VERSION, Plugin::asset_version('/resources/js/does-not-exist.js'));
    }

    public function test_existing_files_are_versioned_by_mtime_only_with_script_debug(): void
    {
        $path = '/resources/js/print-labels.js';

        $expected = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG
            ? (string) filemtime(dirname(SENDY_WC_PLUGIN_FILE) . $path)
            : Plugin::VERSION;

        $this->assertSame($expected, Plugin::asset_version($path));
    }
}
