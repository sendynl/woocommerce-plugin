/**
 * Send labels to the local Sendy print app, falling back to a PDF download.
 * Labels are fetched through the sendy_print_labels AJAX endpoint; the
 * print app is a desktop application listening on http://127.0.0.1:7639.
 */
(function () {
	'use strict';

	const checkPrintApiAvailability = async () => {
		// Abort the health check after a few seconds so a hung Local Network
		// Access permission prompt cannot stall the flow indefinitely.
		const controller = new AbortController();
		const timeout = setTimeout(() => controller.abort(), 3000);

		try {
			const response = await fetch('http://127.0.0.1:7639/health', {
				signal: controller.signal,
			});
			if (!response.ok) {
				return false;
			}
			const responseData = await response.json();
			return responseData.name === 'Sendy';
		} catch (error) {
			// Only log errors other than network errors. Network errors are
			// expected when the app is not installed.
			if (!isNetworkError(error) && error.name !== 'AbortError') {
				console.error('Error checking app status:', error);
			}
			return false;
		} finally {
			clearTimeout(timeout);
		}
	};

	/**
	 * @param {Array<string|number>} orderIds
	 * @throws {PrintError}
	 */
	const fetchDocument = async (orderIds) => {
		const body = new URLSearchParams();
		body.append('action', 'sendy_print_labels');
		body.append(
			'nonce',
			document.getElementById('sendy-print-labels-nonce').value
		);
		for (const orderId of orderIds) {
			body.append('order_ids[]', orderId);
		}

		const response = await fetch(ajaxurl, { method: 'POST', body });
		if (!response.ok) {
			throw new PrintError('Failed to fetch document', response);
		}
		const responseData = await response.json();
		return {
			base64: responseData.labels ?? responseData.documents,
			token: response.headers.get('X-Sendy-Token') ?? '',
			reload: responseData.reload === true,
		};
	};

	/**
	 * @throws {PrintError}
	 */
	const printDocument = async (printableDocument) => {
		const response = await fetch('http://127.0.0.1:7639/print', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				Authorization: 'Bearer ' + printableDocument.token,
			},
			body: JSON.stringify({
				label: printableDocument.base64,
			}),
		});
		if (!response.ok) {
			throw new PrintError('Failed to print document', response);
		}
		return response;
	};

	const downloadDocument = async (printableDocument, filename = '') => {
		const pdf = await fetch(
			'data:application/pdf;base64,' + printableDocument.base64
		);
		const blob = await pdf.blob();
		const url = URL.createObjectURL(blob);
		const link = document.createElement('a');
		link.href = url;
		link.download = filename || 'labels.pdf';
		link.target = '_blank';
		link.click();
		link.remove();
		URL.revokeObjectURL(url);
	};

	const isNetworkError = (error) =>
		error instanceof TypeError &&
		(error.message === 'Failed to fetch' ||
			error.message ===
				'NetworkError when attempting to fetch resource.');

	class PrintError extends Error {
		response;
		status;
		constructor(message, response) {
			super(`${message}: ${response.status} ${response.statusText}`);
			this.name = 'PrintError';
			this.response = response;
			this.status = response.status;
		}
	}

	const printLabels = async (orderIds) => {
		const printableDocumentPromise = fetchDocument(orderIds);
		const printApiIsAvailable = await checkPrintApiAvailability();

		let printableDocument;
		try {
			printableDocument = await printableDocumentPromise;
		} catch (error) {
			if (error instanceof PrintError) {
				// The server stored a flash notice explaining the problem;
				// reload so it is displayed.
				window.location.reload();
				return;
			}
			throw error;
		}

		if (printApiIsAvailable) {
			try {
				await printDocument(printableDocument);
			} catch (error) {
				// The print app failed after a passing health check; the
				// label is already fetched, so fall back to downloading it.
				await downloadDocument(printableDocument);
			}
		} else {
			await downloadDocument(printableDocument);
		}

		if (printableDocument.reload) {
			// Reload so flash notices and updated order statuses render.
			window.location.reload();
		}
	};

	/**
	 * Print the labels of the given orders, or download them as a PDF when
	 * the print app is not available. The returned promise never rejects, so
	 * callers can chain finally() without handling errors themselves.
	 *
	 * @param {Array<string|number>} orderIds
	 * @return {Promise<void>}
	 */
	window.sendyPrintLabels = (orderIds) =>
		printLabels(orderIds).catch((error) => {
			console.error('Sendy: printing labels failed', error);
		});
})();
