<?php

namespace Sendy\WooCommerce\ShippingMethods;

use WC_Shipping_Flat_Rate;

class PickupPointDelivery extends WC_Shipping_Flat_Rate
{
    use ShippingMethodTrait;

    public const ID = 'sendy_pickup_point';

    public function __construct($instance_id = 0)
    {
        $this->id = self::ID;
        $this->instance_id = absint($instance_id);
        $this->method_title = __('Sendy - Pickup Point Delivery', 'sendy');
        $this->method_description = __('Let your customers choose a pick-up point', 'sendy');

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

    /**
     * @param array<string,array<string,mixed>> $form_fields
     * @return array<string,array<string,mixed>>
     */
    public function instance_form_fields(array $form_fields): array
    {
        $form_fields['carrier'] = [
            'title' => __('Carrier', 'sendy'),
            'type' => 'select',
            'options' => [
                'DHL' => 'DHL eCommerce',
                'PostNL' => 'PostNL',
                'DPD' => 'DPD',
            ],
            'class' => 'wc-enhanced-select',
            'default' => 'class',
            'description' => __('Select which carrier to show the pickup points for', 'sendy')
        ];

        return $form_fields;
    }
}
