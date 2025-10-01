<?php

namespace Sendy\WooCommerce\ShippingMethods;

use WC_Shipping_Flat_Rate;

/**
 * @mixin WC_Shipping_Flat_Rate
 */
trait ShippingMethodTrait
{
    public function init_form_fields()
    {
        $this->form_fields = [];
    }

    public function calculate_shipping($package = [])
    {
        // Set free shipping rate if cart subtotal exceeds minimum_for_free_shipping
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

        return $form_fields;
    }
}
