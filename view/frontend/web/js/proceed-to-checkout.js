define([
    'jquery',
    'Magento_Customer/js/model/authentication-popup',
    'Magento_Ui/js/modal/modal',
    'Magento_Customer/js/customer-data'
], function ($, authenticationPopup, modal, customerData) {
    'use strict';

    return function (config, element) {
        $(element).click(function (event) {
            var cart = customerData.get('cart'),
                customer = customerData.get('customer');

            event.preventDefault();

            if (!customer().firstname && cart().isGuestCheckoutAllowed === false) {
                authenticationPopup.showModal();

                return false;
            }
            $(element).attr('disabled', true);

            var options = {
                type: 'popup',
                responsive: true,
                title: 'Main title',
                buttons: [{
                    text: $.mage.__('Ok'),
                    class: '',
                    click: function () {
                        this.closeModal();
                    }
                }]
            };

            var popup = modal(options, $('#nf-modal'));
            /*$('#nf-modal').modal('openModal');
            $('#nf-modal').show();*/

            let url = $('#nf-modal').find('div.modal-body-content').attr('data-store-url') + 'checkout/cart/';
            $.get(url, function(data) {
                let nfCheckout = $(data).find('#nf-modal').find('div.modal-body-content');
                let cartId = nfCheckout.attr('data-cart-id');
                let merchantId = nfCheckout.attr('data-merchant-id');
                let storeUrl = nfCheckout.attr('data-store-url');
                let storeId = nfCheckout.attr('data-store-id');
                let accessToken = nfCheckout.attr('data-access-token');
                let isLoggedIn = nfCheckout.attr('data-customer-is-logged-in');

                $('div.modal-body-content').attr('data-cart-id', cartId);
                $('div.modal-body-content').attr('data-access-token', accessToken);

                nfOpenCheckout({
                    'data-nf-merchant-id': merchantId,
                    'data-nf-cart-id': cartId,
                    'data-nf-store-url': storeUrl,
                    'data-nf-access-token': accessToken,
                    'data-nf-customer-is-logged-in': isLoggedIn,
                    'data-nf-store-id': storeId
                });
            });

            /*location.href = config.checkoutUrl;*/
        });

    };
});
