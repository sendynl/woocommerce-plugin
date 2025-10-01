<?php

namespace Sendy\WooCommerce\Enums;

final class ProcessingMethod extends Enum
{
    public const WooCommerce = 'woocommerce';
    public const Sendy = 'sendy';

    public static function cases(): array
    {
        return [
            self::WooCommerce,
            self::Sendy,
        ];
    }

    public static function casesWithDescription(): array
    {
        return [
            self::WooCommerce => __('Create shipments in WooCommerce', 'sendy'),
            self::Sendy => __('Process shipments in Sendy', 'sendy'),
        ];
    }
}
