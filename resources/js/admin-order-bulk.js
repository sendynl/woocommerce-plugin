(function ($) {
    let sendyOrderBulk = {
        init: function () {
            let ordersFilter = $('#wc-orders-filter, #posts-filter');

            $(ordersFilter).on('change', '#bulk-action-selector-top', this.toggleCreateShipmentsModal);
            $(ordersFilter).on('change', '#bulk-action-selector-bottom', this.toggleCreateShipmentsModal);

            $(ordersFilter).on('click', '.button.action', this.disableSubmitButton);
        },

        /**
         *
         * @param {Event} event
         */
        toggleCreateShipmentsModal: function (event) {
            event.preventDefault();

            let value = $(this).val(),
                bulkActionsForm = $(this).parents('#wc-orders-filter, #posts-filter'),
                title = $(':selected', this).text();

            if (value === 'sendy_create_shipments') {
                tb_show('', '/?TB_inline=true&width=420&height=320&inlineId=sendy-create-shipments-modal');

                let thickboxWindow = $('#TB_window');

                thickboxWindow.find('#TB_ajaxWindowTitle').text(title);

                thickboxWindow.find('#sendy-create-shipments-button').on('click', function (event) {
                    event.preventDefault();

                    let sendyFieldsContainer = bulkActionsForm.append('<div></div>').hide();

                    let preferenceId = thickboxWindow.find('#sendy_preference_id').find(':selected').val();
                    $(sendyFieldsContainer).append(`<input type="hidden" name="sendy_preference_id" value="${preferenceId}">`);

                    if (thickboxWindow.find('#sendy_shop_id')) {
                        let shopId = thickboxWindow.find('#sendy_shop_id').find(':selected').val();
                        $(sendyFieldsContainer).append(`<input type="hidden" name="sendy_shop_id" value="${shopId}">`);
                    }

                    let nonce = thickboxWindow.find('#sendy_bulk_modal_nonce').val();
                    $(sendyFieldsContainer).append(`<input type="hidden" name="sendy_bulk_modal_nonce" value="${nonce}">`);

                    let amount = thickboxWindow.find('#sendy_amount').val();
                    $(sendyFieldsContainer).append(`<input type="hidden" name="sendy_amount" value="${amount}">`);

                    $(this).prop('disabled', true);

                    bulkActionsForm.submit();
                });
            }
        },

        disableSubmitButton: function() {
            let bulkActions = $(this).closest('.bulkactions'),
                dropdown = bulkActions.find('select[name=action]');

            if (dropdown.val() === 'sendy_create_shipments') {
                $(this).prop('disabled', true);
                $('#posts-filter').submit();
            }
        }
    };

    sendyOrderBulk.init();
})(jQuery);
