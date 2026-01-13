<?php

namespace Sendy\WooCommerce\Utils;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;
use Sendy\WooCommerce\Plugin;

define('SENDY_BLOCK_VERSION', '1.0.0');

class BlocksIntegration implements IntegrationInterface
{

    public function get_name()
    {
        return 'sendy-pickup-points';
    }

    public function initialize()
    {
        $this->register_block_frontend_scripts();
        $this->register_block_editor_scripts();
    }

    public function get_script_handles()
    {
        return ['sendy-checkout-block-frontend'];
    }

    public function get_editor_script_handles()
    {
        return ['sendy-block-editor'];
    }

    public function get_script_data()
    {
        return [];
    }

    private function register_block_frontend_scripts()
    {
        $script_url = SENDY_WC_PLUGIN_DIR_URL . '/build/sendy-checkout-block-frontend.js';
        $script_asset_path = SENDY_WC_PLUGIN_DIR_URL . '/build/sendy-checkout-block-frontend.asset.php';

        $script_asset = file_exists($script_asset_path)
            ? require $script_asset_path
            : [
                'dependencies' => [],
                'version' => $this->get_file_version($script_asset_path),
            ];

        wp_enqueue_style(
            'sendy-checkout-block-frontend-styling',
            SENDY_WC_PLUGIN_DIR_URL . '/resources/css/frontend.css',
            [],
            Plugin::VERSION,
        );

        wp_register_script(
            'sendy-checkout-block-frontend',
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );
    }

    private function register_block_editor_scripts()
    {
        $script_path = '/build/index.js';
        $script_url = plugins_url('sendy' . $script_path);
        $script_asset_path = plugins_url('sendy/build/index.asset.php');
        $script_asset = file_exists($script_asset_path)
            ? require $script_asset_path
            : [
                'dependencies' => [],
                'version' => $this->get_file_version($script_asset_path),
            ];

        wp_register_script(
            'sendy-block-editor',
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );
    }

    protected function get_file_version($file)
    {
        if (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG && file_exists($file)) {
            return filemtime($file);
        }

        return SENDY_BLOCK_VERSION;
    }
}
