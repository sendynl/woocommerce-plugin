(function ($) {
	$('select[name=sendy_processing_method]').on('change', function (e) {
		let processingMethodFields = $('.sendy-processing-method-field');

		if ($(this).find('option:selected').val() === 'sendy') {
			$(processingMethodFields).removeClass('hidden');
		} else {
			$(processingMethodFields).addClass('hidden');
		}
	});
})(jQuery);
