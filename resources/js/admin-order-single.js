(function ($) {
	let sendyOrderSingle = {
		init: function () {
			let createShipmentButton = $(
				'#sendy-metabox-create-shipment-button'
			);

			$(createShipmentButton).on('click', this.createShipment);
		},

		/**
		 * @param {Event} event
		 */
		createShipment: function (event) {
			event.preventDefault();

			$(this).prop('disabled', true);

			let shopId = $('#sendy-metabox-shop-dropdown')
					.find(':selected')
					.val(),
				preferenceId = $('#sendy-metabox-preference-dropdown')
					.find(':selected')
					.val(),
				amount = $('#sendy-metabox-amount').val(),
				nonce = $('#sendy-create-shipment-nonce').val();

			let data = {
				nonce: nonce,
				action: 'sendy_order_single_save_form',
				order_id: woocommerce_admin_meta_boxes.post_id,
				shop_id: shopId,
				preference_id: preferenceId,
				amount: amount,
			};

			$.post(woocommerce_admin_meta_boxes.ajax_url, data, () => {
				// Reload the page to display the error or success message to the user
				window.location.reload();
			});
		},
	};

	sendyOrderSingle.init();
})(jQuery);
