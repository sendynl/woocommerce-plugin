<?php

namespace Sendy\WooCommerce\ShippingMethods;

use WC_Shipping_Flat_Rate;

class StandardDelivery extends WC_Shipping_Flat_Rate
{
    use ShippingMethodTrait;

    public const ID = 'sendy_standard_delivery';

    public function __construct($instance_id = 0)
    {
        $this->id = self::ID;
        $this->instance_id = absint($instance_id);
        $this->method_title = __('Sendy - Standard Delivery', 'sendy');
        $this->method_description = __('Standard delivery method', 'sendy');


        $this->supports = [
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        ];

        $this->init();
        $this->init_form_fields();
        $this->init_settings();

        add_filter('woocommerce_shipping_instance_form_fields_' . $this->id, [$this, 'instance_form_fields'], 10, 1);
        add_action('woocommerce_update_options_shipping_' . $this->id, [$this, 'process_admin_options']);
    }
}
