<?php

/**
 * Removes the plugin's options and transients when the plugin is uninstalled,
 * so a reinstall starts with fresh credentials and the portal creates a new
 * OAuth client. Shipment metadata on orders is left in place.
 */

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$sendy_options = [
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

foreach ($sendy_options as $sendy_option) {
    delete_option($sendy_option);
}

delete_transient('sendy_shops');
