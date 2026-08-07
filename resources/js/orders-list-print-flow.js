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

	// The server flashes a notice explaining a failed request; the flow
	// reloads to show it.
	class AjaxError extends Error {}

	/**
	 * POST to admin-ajax for the given orders and decode the JSON response.
	 *
	 * @param {Object<string,string>} params Form fields, including action.
	 * @param {Array<string|number>}  orderIds
	 * @return {Promise<any>}
	 * @throws {AjaxError}
	 */
	const ajaxPost = async ( params, orderIds ) => {
		const body = new URLSearchParams( params );
		for ( const orderId of orderIds ) {
			body.append( 'order_ids[]', orderId );
		}

		const response = await fetch( ajaxurl, { method: 'POST', body } );
		const data = await response.json().catch( () => null );

		if ( ! response.ok ) {
			throw new AjaxError(
				data?.message ?? `The ${ params.action } request failed`
			);
		}

		return data;
	};

	/**
	 * Fetch the shipment status per order: none|pending|ready|failed.
	 *
	 * @param {Array<string|number>} orderIds
	 * @param {boolean}              flagUnprintable When true, asks the server
	 *                                               to flash a notice for the
	 *                                               orders it cannot print.
	 * @return {Promise<Object<string,string>>}
	 */
	const fetchStatuses = async ( orderIds, flagUnprintable = false ) => {
		const params = {
			action: 'sendy_shipments_status',
			nonce: document.getElementById( 'sendy-print-labels-nonce' ).value,
		};
		if ( flagUnprintable ) {
			params.flash_unprintable = '1';
		}

		return ( await ajaxPost( params, orderIds ) ).statuses;
	};

	/**
	 * Create shipments for the given orders.
	 *
	 * @param {Array<string|number>} orderIds
	 * @param {Object} fields The values collected from the modal
	 * @return {Promise<{created: number[]}>}
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
	 * Ask for the shop/preference/amount through the create-shipments
	 * thickbox modal. Resolves with the chosen values, or null when the
	 * modal is dismissed. This is the modal's only owner: admin-order-bulk.js
	 * calls it too for the "Create shipments" bulk action.
	 *
	 * @param {Object} options
	 * @param {string} options.title        The thickbox window title.
	 * @param {string} [options.buttonText] Replaces the confirm button text
	 *                                      until the modal closes.
	 * @return {Promise<Object|null>}
	 */
	window.sendyOpenCreateShipmentsModal = ( { title, buttonText } ) =>
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

			thickboxWindow.find( '#TB_ajaxWindowTitle' ).text( title );

			if ( buttonText ) {
				button.text( buttonText );
			}

			// Thickbox moves the inline content back into the page when the
			// modal closes; undo our changes for the next opening, and treat
			// a dismissal as a cancel (a no-op when the confirm handler
			// already resolved).
			jQuery( 'body' ).one( 'thickbox:removed', function () {
				button
					.text( originalButtonText )
					.prop( 'disabled', false )
					.off( 'click.sendyModal' );
				resolve( null );
			} );

			button
				.off( 'click.sendyModal' )
				.on( 'click.sendyModal', function ( event ) {
					event.preventDefault();

					jQuery( this ).prop( 'disabled', true );

					resolve( {
						shopId:
							thickboxWindow
								.find( '#sendy_shop_id :selected' )
								.val() ?? '',
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
		let pending = orderIds;
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
	 * The ids from a status map that have the wanted status.
	 *
	 * @param {Object<string,string>} statuses
	 * @param {string}               wanted
	 * @return {string[]}
	 */
	const idsWithStatus = ( statuses, wanted ) =>
		Object.keys( statuses ).filter(
			( orderId ) => statuses[ orderId ] === wanted
		);

	/**
	 * Print the labels of the orders that have one, if any. Asking for the
	 * labels of nothing fails the request and flashes a notice.
	 *
	 * @param {string[]} ready
	 */
	const printReady = async ( ready ) => {
		if ( ready.length ) {
			await window.sendyPrintLabels( ready );
		}
	};

	/**
	 * Print the labels that already exist and explain the orders that have
	 * none, without creating shipments or waiting for labels. Used for the
	 * Sendy processing method, where the portal generates the labels itself.
	 *
	 * @param {Array<string|number>} orderIds
	 */
	const printExistingLabels = async ( orderIds ) => {
		const statuses = await fetchStatuses( orderIds, true );
		const ready = idsWithStatus( statuses, 'ready' );

		await printReady( ready );

		if ( ready.length !== orderIds.length ) {
			// Show the notices for the orders without a label.
			window.location.reload();
		}
	};

	/**
	 * Print the labels of the given orders, creating shipments first for the
	 * orders that have none where the processing method allows it.
	 *
	 * @param {Array<string|number>} orderIds
	 */
	const runPrintFlow = async ( orderIds ) => {
		if ( sendyPrintFlow.printExistingOnly ) {
			return printExistingLabels( orderIds );
		}

		const statuses = await fetchStatuses( orderIds );

		const withoutShipment = idsWithStatus( statuses, 'none' );
		const ready = idsWithStatus( statuses, 'ready' );
		// Failed orders have flashed a notice; re-checking would re-flash it.
		const pending = idsWithStatus( statuses, 'pending' );

		let createdShipments = false;

		if ( withoutShipment.length ) {
			const single = orderIds.length === 1;
			const fields = await window.sendyOpenCreateShipmentsModal( {
				title: single
					? sendyPrintFlow.modalTitleSingular
					: sendyPrintFlow.modalTitle,
				buttonText: single
					? sendyPrintFlow.modalButtonTextSingular
					: sendyPrintFlow.modalButtonText,
			} );

			if ( ! fields ) {
				return;
			}

			const result = await createShipments( withoutShipment, fields );
			createdShipments = result.created.length > 0;

			if (
				! result.created.length &&
				withoutShipment.length === orderIds.length
			) {
				// Nothing printable; reload to show the flash notices.
				window.location.reload();
				return;
			}

			pending.push( ...result.created.map( String ) );
		}

		if ( pending.length ) {
			ready.push( ...( await waitUntilReady( pending ) ) );
		}

		await printReady( ready );

		if ( createdShipments || ready.length !== orderIds.length ) {
			// Show the new shipment data and any notices for the orders that
			// could not be printed.
			window.location.reload();
		}
	};

	/**
	 * Print the labels of the given orders, creating shipments first where
	 * the processing method allows it. The returned promise never rejects,
	 * so callers can chain finally() without handling errors themselves.
	 *
	 * @param {Array<string|number>} orderIds
	 * @return {Promise<void>}
	 */
	window.sendyOrdersListPrintFlow = ( orderIds ) =>
		runPrintFlow( orderIds.map( String ) ).catch( ( error ) => {
			console.error( 'Sendy: printing labels failed', error );

			if ( error instanceof AjaxError ) {
				window.location.reload();
			}
		} );

	// The per-order print buttons in the actions column. The href carries
	// the id of the order the button was rendered for.
	jQuery( document ).on(
		'click',
		'.wc-action-button-sendy_print_label',
		function ( event ) {
			event.preventDefault();

			const button = jQuery( this );

			if ( button.hasClass( 'disabled' ) ) {
				return;
			}

			const orderId = button
				.attr( 'href' )
				.replace( '#sendy-print-label-', '' );

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
