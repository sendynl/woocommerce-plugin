( function ( $ ) {
	let sendyOrderBulk = {
		init: function () {
			let ordersFilter = $( '#wc-orders-filter, #posts-filter' );

			$( ordersFilter ).on(
				'change',
				'#bulk-action-selector-top',
				this.toggleCreateShipmentsModal
			);
			$( ordersFilter ).on(
				'change',
				'#bulk-action-selector-bottom',
				this.toggleCreateShipmentsModal
			);

			$( ordersFilter ).on(
				'click',
				'.button.action',
				this.disableSubmitButton
			);

			$( ordersFilter ).on( 'submit', this.interceptPrintLabels );
		},

		/**
		 * Handle the print labels bulk action client-side so shipments can
		 * be created for orders that have none and the labels can be sent
		 * to the print app
		 *
		 * @param {Event} event
		 */
		interceptPrintLabels: function ( event ) {
			let form = $( this ),
				action = form.find( 'select[name=action]' ).val(),
				action2 = form.find( 'select[name=action2]' ).val();

			if (
				action !== 'sendy_print_labels' &&
				action2 !== 'sendy_print_labels'
			) {
				return;
			}

			event.preventDefault();

			let orderIds = form
				.find(
					'input[name="id[]"]:checked, input[name="post[]"]:checked'
				)
				.map( function () {
					return this.value;
				} )
				.get();

			// Block the form while printing to prevent repeated submissions.
			form.block( {
				message: null,
				overlayCSS: { background: '#fff', opacity: 0.6 },
			} );

			window
				.sendyOrdersListPrintFlow( orderIds )
				.finally( () => form.unblock() );
		},

		/**
		 * Ask for the shop/preference/amount through the shared
		 * create-shipments modal and submit the bulk action form with the
		 * chosen values.
		 *
		 * @param {Event} event
		 */
		toggleCreateShipmentsModal: function ( event ) {
			event.preventDefault();

			if ( $( this ).val() !== 'sendy_create_shipments' ) {
				return;
			}

			const bulkActionsForm = $( this ).parents(
				'#wc-orders-filter, #posts-filter'
			);
			const title = $( ':selected', this ).text();

			window
				.sendyOpenCreateShipmentsModal( { title } )
				.then( function ( fields ) {
					if ( ! fields ) {
						return;
					}

					const container = $( '<div></div>' )
						.hide()
						.appendTo( bulkActionsForm );

					$.each(
						{
							sendy_shop_id: fields.shopId,
							sendy_preference_id: fields.preferenceId,
							sendy_amount: fields.amount,
							sendy_bulk_modal_nonce: fields.nonce,
						},
						function ( name, value ) {
							container.append(
								$( '<input>', { type: 'hidden', name, value } )
							);
						}
					);

					bulkActionsForm.submit();
				} );
		},

		disableSubmitButton: function () {
			let bulkActions = $( this ).closest( '.bulkactions' ),
				dropdown = bulkActions.find( 'select[name=action]' );

			if ( dropdown.val() === 'sendy_create_shipments' ) {
				$( this ).prop( 'disabled', true );
				$( '#posts-filter' ).submit();
			}
		},
	};

	sendyOrderBulk.init();
} )( jQuery );
