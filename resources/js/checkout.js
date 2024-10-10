(function ($) {
    let init = () => {
        $(document).on('click', '#sendy-pick-up-point-button', _showPickupPointPicker);
    };

    let _showPickupPointPicker = (event) => {
        event.preventDefault();

        let data = {
            country: $('#shipping_country').find(':selected').val() ?? $('#billing_country').find(':selected').val(),
            carriers: [$('#sendy-pick-up-point-button').data('carrier')],
            address: $('#shipping_postcode').val() ?? $('#billing_postcode').val(),
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
