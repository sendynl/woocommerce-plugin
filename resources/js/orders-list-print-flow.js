/**
 * Create-and-print flow for the orders list.
 *
 * Checks which orders already have a Sendy shipment, creates shipments for
 * the ones that do not (asking for shop/preference/amount through the same
 * modal as the "Create shipments" bulk action), waits until the Sendy portal
 * has generated the labels, and then prints them through sendyPrintLabels()
 * from print-labels.js. Used by both the per-order button in the actions
 * column and the "Sendy - Print labels" bulk action.
 */
( function () {
	'use strict';

	const POLL_INTERVAL = 3000;
	const POLL_TIMEOUT = 60000;

	const sleep = ( milliseconds ) =>
		new Promise( ( resolve ) => setTimeout( resolve, milliseconds ) );

	/**
	 * Fetch the shipment status per order: none|pending|ready|failed.
	 *
	 * @param {Array<string|number>} orderIds
	 * @param {boolean}              flagPending When true, asks the server to
	 *                                           flash a notice for orders
	 *                                           that come back pending.
	 * @return {Promise<Object<string,string>>}
	 */
	const fetchStatuses = async ( orderIds, flagPending = false ) => {
		const body = new URLSearchParams();
		body.append( 'action', 'sendy_shipments_status' );
		body.append(
			'nonce',
			document.getElementById( 'sendy-print-labels-nonce' ).value
		);
		if ( flagPending ) {
			body.append( 'flash_pending', '1' );
		}
		for ( const orderId of orderIds ) {
			body.append( 'order_ids[]', orderId );
		}

		const response = await fetch( ajaxurl, { method: 'POST', body } );
		if ( ! response.ok ) {
			throw new Error( 'Failed to fetch the shipment statuses' );
		}

		return ( await response.json() ).statuses;
	};

	/**
	 * Create shipments for the given orders.
	 *
	 * @param {Array<string|number>} orderIds
	 * @param {Object} fields The values collected from the modal
	 * @return {Promise<{created: number[], failed: number[]}>}
	 */
	const createShipments = async ( orderIds, fields ) => {
		const body = new URLSearchParams();
		body.append( 'action', 'sendy_create_shipments' );
		body.append( 'nonce', fields.nonce );
		body.append( 'shop_id', fields.shopId );
		body.append( 'preference_id', fields.preferenceId );
		body.append( 'amount', fields.amount );
		for ( const orderId of orderIds ) {
			body.append( 'order_ids[]', orderId );
		}

		const response = await fetch( ajaxurl, { method: 'POST', body } );
		if ( ! response.ok ) {
			throw new Error( 'Failed to create the shipments' );
		}

		return response.json();
	};

	/**
	 * Ask for the shop/preference/amount through the same thickbox modal as
	 * the "Create shipments" bulk action. Resolves with the chosen values,
	 * or null when the modal is dismissed.
	 *
	 * @return {Promise<Object|null>}
	 */
	const openCreateShipmentsModal = () =>
		new Promise( ( resolve ) => {
			if ( ! document.getElementById( 'sendy-create-shipments-modal' ) ) {
				// The modal is replaced by a connection error notice when
				// the Sendy API is unreachable.
				resolve( null );
				return;
			}

			tb_show(
				'',
				'/?TB_inline=true&width=420&height=320&inlineId=sendy-create-shipments-modal'
			);

			const thickboxWindow = jQuery( '#TB_window' );
			const button = thickboxWindow.find(
				'#sendy-create-shipments-button'
			);
			const originalButtonText = button.text();

			thickboxWindow
				.find( '#TB_ajaxWindowTitle' )
				.text( sendyPrintFlowL10n.modalTitle );
			button.text( sendyPrintFlowL10n.modalButtonText );

			// Thickbox moves the inline content back into the page when the
			// modal closes; undo our changes so the "Create shipments" bulk
			// action shows its own button text, and treat a dismissal as a
			// cancel (a no-op when the confirm handler already resolved).
			jQuery( 'body' ).one( 'thickbox:removed', function () {
				button
					.text( originalButtonText )
					.prop( 'disabled', false )
					.off( 'click.sendyPrintFlow' );
				resolve( null );
			} );

			button
				.off( 'click.sendyPrintFlow click.sendyBulk' )
				.on( 'click.sendyPrintFlow', function ( event ) {
					event.preventDefault();

					jQuery( this ).prop( 'disabled', true );

					resolve( {
						shopId: thickboxWindow
							.find( '#sendy_shop_id :selected' )
							.val(),
						preferenceId:
							thickboxWindow
								.find( '#sendy_preference_id :selected' )
								.val() ?? '',
						amount:
							thickboxWindow.find( '#sendy_amount' ).val() ?? '',
						nonce: thickboxWindow
							.find( '#sendy_bulk_modal_nonce' )
							.val(),
					} );

					tb_remove();
				} );
		} );

	/**
	 * Poll until the labels for the given orders are available and return
	 * the ids of the orders whose labels can be printed. Orders that end up
	 * without a shipment or with a failed shipment are dropped; orders still
	 * pending when the timeout passes are dropped too.
	 *
	 * @param {Array<string|number>} orderIds
	 * @return {Promise<string[]>}
	 */
	const waitUntilReady = async ( orderIds ) => {
		const deadline = Date.now() + POLL_TIMEOUT;
		let pending = orderIds.map( String );
		const ready = [];

		while ( pending.length ) {
			const statuses = await fetchStatuses( pending );

			pending = [];

			for ( const [ orderId, status ] of Object.entries( statuses ) ) {
				if ( status === 'ready' ) {
					ready.push( orderId );
				} else if ( status === 'pending' ) {
					pending.push( orderId );
				}
			}

			if ( ! pending.length || Date.now() >= deadline ) {
				break;
			}

			await sleep( POLL_INTERVAL );
		}

		if ( pending.length ) {
			await fetchStatuses( pending, true ).catch( () => {} );
		}

		return ready;
	};

	/**
	 * Print the labels of the given orders, creating shipments first for the
	 * orders that have none.
	 *
	 * @param {Array<string|number>} orderIds
	 */
	const runPrintFlow = async ( orderIds ) => {
		const statuses = await fetchStatuses( orderIds );
		const withoutShipment = orderIds.filter(
			( orderId ) => statuses[ orderId ] === 'none'
		);
		// Orders that are already in a terminal failed state have flashed a
		// notice; drop them instead of re-checking (and re-flashing) later.
		const printable = orderIds.filter(
			( orderId ) => statuses[ orderId ] !== 'failed'
		);

		let createdShipments = false;

		if ( withoutShipment.length ) {
			const fields = await openCreateShipmentsModal();

			if ( ! fields ) {
				return;
			}

			const result = await createShipments( withoutShipment, fields );
			createdShipments = result.created.length > 0;

			if (
				! result.created.length &&
				withoutShipment.length === orderIds.length
			) {
				// Nothing can be printed; reload to show the flash notices
				// explaining what went wrong.
				window.location.reload();
				return;
			}
		}

		const ready = await waitUntilReady( printable );

		if ( ready.length ) {
			await window.sendyPrintLabels( ready );
		}

		if ( createdShipments || ready.length !== orderIds.length ) {
			// Show the new shipment data and any flash notices for the
			// orders that could not be printed.
			window.location.reload();
		}
	};

	/**
	 * Print the labels of the given orders, creating shipments first for the
	 * orders that have none. The returned promise never rejects, so callers
	 * can chain finally() without handling errors themselves.
	 *
	 * @param {Array<string|number>} orderIds
	 * @return {Promise<void>}
	 */
	window.sendyOrdersListPrintFlow = ( orderIds ) =>
		runPrintFlow( orderIds ).catch( ( error ) => {
			console.error( 'Sendy: printing labels failed', error );
		} );

	// The per-order print buttons in the actions column. The order id comes
	// from the row's bulk-selection checkbox (id[] on the HPOS table, post[]
	// on the legacy table).
	jQuery( document ).on(
		'click',
		'.wc-action-button-sendy_print_label, .wc-action-button-sendy_create_shipment_print_label',
		function ( event ) {
			event.preventDefault();

			const button = jQuery( this );

			if ( button.hasClass( 'disabled' ) ) {
				return;
			}

			const orderId = button
				.closest( 'tr' )
				.find( 'input[name="id[]"], input[name="post[]"]' )
				.val();

			button.addClass( 'disabled' );

			window
				.sendyOrdersListPrintFlow( [ orderId ] )
				.finally( () => button.removeClass( 'disabled' ) );
		}
	);
} )();
