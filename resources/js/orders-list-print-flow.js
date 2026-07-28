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
	 * POST to admin-ajax for the given orders and decode the JSON response.
	 *
	 * @param {Object<string,string>} params Form fields, including action.
	 * @param {Array<string|number>}  orderIds
	 * @return {Promise<any>}
	 */
	const ajaxPost = async ( params, orderIds ) => {
		const body = new URLSearchParams( params );
		for ( const orderId of orderIds ) {
			body.append( 'order_ids[]', orderId );
		}

		const response = await fetch( ajaxurl, { method: 'POST', body } );
		if ( ! response.ok ) {
			throw new Error( `The ${ params.action } request failed` );
		}

		return response.json();
	};

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
		const params = {
			action: 'sendy_shipments_status',
			nonce: document.getElementById( 'sendy-print-labels-nonce' )
				.value,
		};
		if ( flagPending ) {
			params.flash_pending = '1';
		}

		return ( await ajaxPost( params, orderIds ) ).statuses;
	};

	/**
	 * Create shipments for the given orders.
	 *
	 * @param {Array<string|number>} orderIds
	 * @param {Object} fields The values collected from the modal
	 * @return {Promise<{created: number[], failed: number[]}>}
	 */
	const createShipments = ( orderIds, fields ) =>
		ajaxPost(
			{
				action: 'sendy_create_shipments',
				nonce: fields.nonce,
				shop_id: fields.shopId,
				preference_id: fields.preferenceId,
				amount: fields.amount,
			},
			orderIds
		);

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
			// On the last poll before the deadline, have the server flash a
			// notice for the orders that are still pending afterwards.
			const isLastPoll = Date.now() + POLL_INTERVAL > deadline;
			const statuses = await fetchStatuses( pending, isLastPoll );

			pending = [];

			for ( const [ orderId, status ] of Object.entries( statuses ) ) {
				if ( status === 'ready' ) {
					ready.push( orderId );
				} else if ( status === 'pending' ) {
					pending.push( orderId );
				}
			}

			if ( ! pending.length || isLastPoll ) {
				break;
			}

			await sleep( POLL_INTERVAL );
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
		const statuses = await fetchStatuses( orderIds.map( String ) );
		const byStatus = ( wanted ) =>
			Object.keys( statuses ).filter(
				( orderId ) => statuses[ orderId ] === wanted
			);

		const withoutShipment = byStatus( 'none' );
		const ready = byStatus( 'ready' );
		// Orders whose labels still need waiting on: the ones already being
		// generated plus the shipments created below. Orders in a terminal
		// failed state have flashed a notice; drop them instead of
		// re-checking (and re-flashing) later.
		const pending = byStatus( 'pending' );

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

			pending.push( ...result.created.map( String ) );
		}

		if ( pending.length ) {
			ready.push( ...( await waitUntilReady( pending ) ) );
		}

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

			button.addClass( 'disabled' ).attr( 'aria-disabled', 'true' );

			window
				.sendyOrdersListPrintFlow( [ orderId ] )
				.finally( () =>
					button
						.removeClass( 'disabled' )
						.removeAttr( 'aria-disabled' )
				);
		}
	);
} )();
