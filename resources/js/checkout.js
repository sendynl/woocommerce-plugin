(function ($) {
    let init = () => {
        $(document).on('click', '#sendy-pick-up-point-button', _showPickupPointPicker);
    };

    let _showPickupPointPicker = (event) => {
        event.preventDefault();

        const shippingCountrySelector = '#shipping_country',
            shippingPostcodeSelector = '#shipping_postcode',
            billingCountrySelector = '#billing_country',
            billingPostcodeSelector = '#billing_postcode';

        let differentAddress = $('#ship-to-different-address-checkbox').is(':checked');

        let countrySelector = differentAddress ? shippingCountrySelector : billingCountrySelector,
            postcodeSelector = differentAddress ? shippingPostcodeSelector : billingPostcodeSelector;

        let address = $(postcodeSelector).val();

        let data = {
            country: $(countrySelector).find(':selected').val()
                ?? $(countrySelector).val()
                ?? 'NL',
            carriers: [$('#sendy-pick-up-point-button').data('carrier')],
            address: address,
        };

        window.Sendy.parcelShopPicker.open(data, _handleSelection, _handleErrors);
    };

    let _handleSelection = (data) => {
        data.nonce = $('#sendy-nonce').val();
        data.instance_id = $('#sendy-instance-id').val();

        let request = wp.ajax.post('sendy_set_pickup_point', data);

        request.done(() => { $('body').trigger('update_checkout'); });
    };

    let _handleErrors = (errors) => {
        console.log(errors);
    }

    init();

})(jQuery);
