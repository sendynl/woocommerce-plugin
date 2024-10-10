(function ($) {
    let sendyOrderBulk = {
        init: function () {
            let postsFilter = $('#wc-orders-filter');

            $(postsFilter).on('change', '#bulk-action-selector-top', this.toggleCreateShipmentsModal);
            $(postsFilter).on('change', '#bulk-action-selector-bottom', this.toggleCreateShipmentsModal);

            $(postsFilter).on('click', '.button.action', this.disableSubmitButton);
        },

        /**
         *
         * @param {Event} event
         */
        toggleCreateShipmentsModal: function (event) {
            event.preventDefault();

            let value = $(this).val(),
                bulkActionsForm = $(this).parents('#wc-orders-filter'),
                title = $(':selected', this).text();

            if (value === 'sendy_create_shipments') {
                tb_show('', '/?TB_inline=true&width=640&height=480&inlineId=sendy-create-shipments-modal');

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
