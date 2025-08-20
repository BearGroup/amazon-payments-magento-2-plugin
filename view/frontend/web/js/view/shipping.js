/*global define*/
define(
    [
        'jquery',
        'Amazon_Pay/js/model/storage',
        'uiRegistry',
        'Magento_Checkout/js/model/checkout-data-resolver',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/model/customer',
        'mage/translate',
        'Magento_Checkout/js/action/select-billing-address'
    ],
    function (
        $,
        amazonStorage,
        registry,
        checkoutDataResolver,
        quote,
        customer,
        $t,
        selectBillingAddress
    ) {
        'use strict';

        return function(Component) {
            if (!amazonStorage.isAmazonCheckout()) {
                return Component;
            }

            return Component.extend({
                /**
                 * Initialize shipping
                 */
                initialize: function () {
                    this._super();
                    this.isNewAddressAdded(true);
                    this.refreshShippingRegion();
                    return this;
                },

                /**
                 * Validate guest email
                 */
                validateGuestEmail: function () {
                    var loginFormSelector = 'form[data-role=email-with-possible-login]';

                    $(loginFormSelector).validation();

                    return $(loginFormSelector + ' input[type=email]').valid();
                },

                /**
                 * @return {Boolean}
                 */
                validateShippingInformation: function () {
                    var isValidShippingInformation = this._super();
                    if (!isValidShippingInformation) {
                        var option = _.isObject(this.countryOptions) && this.countryOptions[quote.shippingAddress().countryId];
                        if (customer.isLoggedIn() &&
                            option &&
                            option['is_region_required'] &&
                            !quote.shippingAddress().region) {

                                if (!this.isFormPopUpVisible()) {
                                    this.showFormPopUp();    
                                }
                                
                                if (_.isUndefined(this.errorValidationMessage()) || this.errorValidationMessage() === false) {
                                    this.errorValidationMessage($t('Please specify a regionId in shipping address.'));
                                }
                        }
                    } else {
                        if ($('button.iosc-place-order-button').length > 0) {
                            selectBillingAddress(quote.shippingAddress());
                        }
                    }

                    return isValidShippingInformation;
                },

                refreshShippingRegion: function() {
                    var checkoutProvider = registry.get('checkoutProvider');

                    checkoutProvider.on('shippingAddress.region_id', function (regionId) {
                        checkoutDataResolver.resolveEstimationAddress();
                    });
                }
            });
        }
    }
);
