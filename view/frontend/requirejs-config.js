var config = {
    map: {
        '*': {
            "Magento_Checkout/js/sidebar": 'NoFraud_Connect/js/sidebar',
            "Magento_Checkout/js/proceed-to-checkout": 'NoFraud_Connect/js/proceed-to-checkout'
        }
    },
    config: {
        mixins: {
            'Magento_Checkout/js/sidebar': 'NoFraud_Connect/js/sidebar'
        }
    }
};