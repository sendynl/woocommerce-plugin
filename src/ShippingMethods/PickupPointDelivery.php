<?php

namespace Sendy\WooCommerce\ShippingMethods;

class PickupPointDelivery extends \WC_Shipping_Flat_Rate
{
    public const ID = 'sendy_pickup_point';

    public function __construct($instance_id = 0)
    {
        $this->id = self::ID;
        $this->instance_id = absint($instance_id);
        $this->method_title = __('Pickup Point Delivery', 'sendy');
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

    public function init_form_fields()
    {
        $this->form_fields = [];
    }


    public function calculate_shipping($package = [])
    {
        // Set free shipping rate if cart subtotal exceed minimum_for_free_shipping
        $minimum_for_free_shipping = $this->get_option('minimum_for_free_shipping');

        if ('' !== $minimum_for_free_shipping && $package['cart_subtotal'] > $minimum_for_free_shipping) {
            $rate = [
                'id' => $this->get_rate_id(),
                'label' => $this->title,
                'cost' => 0,
                'package' => $package,
            ];

            $this->add_rate($rate);
        } else {
            parent::calculate_shipping($package);
        }
    }

    /**
     * @param array<string,array<string,mixed>> $form_fields
     * @return array<string,array<string,mixed>>
     */
    public function instance_form_fields(array $form_fields): array
    {
        $form_fields['title']['default'] = $this->method_title;

        $form_fields['minimum_for_free_shipping'] = [
            // translators: %s contains the currency symbol of the shop
            'title' => sprintf(esc_html__('Free shipping from %s', 'sendy'), get_woocommerce_currency_symbol()),
            'type' => 'number',
            'desc_tip' => esc_html__('Keep empty if you don’t want to use Free shipping', 'sendy'),
            'default' => 0,
        ];

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
