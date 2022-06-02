/**
 * Copyright © Amazon.com, Inc. or its affiliates. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License").
 * You may not use this file except in compliance with the License.
 * A copy of the License is located at
 *
 *  http://aws.amazon.com/apache2.0
 *
 * or in the "license" file accompanying this file. This file is distributed
 * on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either
 * express or implied. See the License for the specific language governing
 * permissions and limitations under the License.
 */
define([
    'ko',
    'jquery',
    'Amazon_Pay/js/action/checkout-session-config-load',
    'Amazon_Pay/js/action/checkout-session-button-payload-load',
    'Amazon_Pay/js/model/storage',
    'mage/url',
    'Amazon_Pay/js/amazon-checkout',
    'Magento_Customer/js/customer-data',
    'Magento_Checkout/js/model/payment/additional-validators',
    'mage/storage',
    'Magento_Checkout/js/model/error-processor'
], function (
        ko,
        $,
        checkoutSessionConfigLoad,
        buttonPayloadLoad,
        amazonStorage,
        url,
        amazonCheckout,
        customerData,
        additionalValidators,
        storage,
        errorProcessor
    ) {
    'use strict';

    $.widget('amazon.AmazonButton', {
        options: {
            payOnly: null,
            placement: 'Cart',
            hideIfUnavailable: '',
            buttonType: 'Normal',
            isIosc: ko.observable($('button.iosc-place-order-button').length > 0)
        },

        drawing: false,

        _loadButtonConfig: function (callback) {
            checkoutSessionConfigLoad(function (checkoutSessionConfig) {
                if (!$.isEmptyObject(checkoutSessionConfig)) {
                    callback({
                        merchantId: checkoutSessionConfig['merchant_id'],
                        publicKeyId: checkoutSessionConfig['public_key_id'],
                        ledgerCurrency: checkoutSessionConfig['currency'],
                        sandbox: checkoutSessionConfig['sandbox'],
                        checkoutLanguage: checkoutSessionConfig['language'],
                        productType: this._isPayOnly() ? 'PayOnly' : 'PayAndShip',
                        placement: this.options.placement,
                        buttonColor: checkoutSessionConfig['button_color']
                    });

                    if (this.options.placement !== "Checkout") {
                        $(this.options.hideIfUnavailable).show();
                    }
                } else {
                    $(this.options.hideIfUnavailable).hide();
                }
            }.bind(this));
        },

        _loadInitCheckoutPayload: function (callback, payloadType) {
            checkoutSessionConfigLoad(function (checkoutSessionConfig) {
                buttonPayloadLoad(function (buttonPayload) {
                    callback({
                        createCheckoutSessionConfig: {
                            payloadJSON: buttonPayload[0],
                            signature: buttonPayload[1],
                            publicKeyId: checkoutSessionConfig['public_key_id']
                        }
                    });
                }, payloadType);
            });
        },

        /**
         * @returns {boolean}
         * @private
         */
        _isPayOnly: function () {
            var cartData = customerData.get('cart');

            // No cart data yet or cart is empty, for the pdp button
            if (typeof cartData().amzn_pay_only === 'undefined' || parseInt(cartData().summary_count) === 0) {
                return this.options.payOnly
            }

            // Check if cart has items and it's the pdp button
            if (parseInt(cartData().summary_count) > 0 && this.options.payOnly !== null) {
                return cartData().amzn_pay_only && this.options.payOnly;
            }

            return cartData().amzn_pay_only;
        },

        /**
         * Create button
         */
        _create: function () {
            var self = this;
            if (this.options.placement === 'PayNow') {
                // PayNow is not a valid placement for Amazon Pay API, only used for changing payload
                this.options.placement = 'Checkout';
                this.buttonType = 'PayNow';
            }

            this._draw();
            this._subscribeToCartUpdates();
        },

        /**
         * Draw button
         **/
        _draw: function () {
            var self = this;

            if (!this.drawing) {
                this.drawing = true;
                var $buttonContainer = this.element;
                amazonCheckout.withAmazonCheckout(function (amazon, args) {
                    var $buttonRoot = $('<div></div>');
                    $buttonRoot.html('<img src="' + require.toUrl('images/loader-1.gif') + '" alt="" width="24" />');
                    $buttonContainer.empty().append($buttonRoot);

                    this._loadButtonConfig(function (buttonConfig) {
                        try {
                            var amazonPayButton = amazon.Pay.renderButton('#' + $buttonRoot.empty().removeUniqueId().uniqueId().attr('id'), buttonConfig);
                        } catch (e) {
                            console.log('Amazon Pay button render error: ' + e);
                            return;
                        }
                        amazonPayButton.onClick(function() {
                            if (self.buttonType === 'PayNow' && !additionalValidators.validate()) {
                                return false;
                            }
                            //This is for compatibility with Iosc. We need to update the customer's Magento session before getting the final config and payload
                            if (self.buttonType === 'PayNow' && self.options.isIosc()) {
                                storage.post(
                                    'checkout/onepage/update',
                                    "{}",
                                    false
                                ).done(
                                    function (response) {
                                        if (!response.error) {
                                            self._initCheckout(amazonPayButton);
                                        } else {
                                            errorProcessor.process(response);
                                        }
                                    }
                                ).fail(
                                    function (response) {
                                        errorProcessor.process(response);
                                    }
                                );
                            }else{
                                self._initCheckout(amazonPayButton);
                            }
                        });

                        $('.amazon-button-container .field-tooltip').fadeIn();
                        self.drawing = false;

                        if (self.buttonType === 'PayNow' && self._isPayOnly()) {
                            customerData.get('checkout-data').subscribe(function (checkoutData) {
                                const opacity = checkoutData.selectedBillingAddress ? 1 : 0.5;

                                const shadow = $('.amazon-checkout-button > div')[0].shadowRoot;
                                $(shadow).find('.amazonpay-button-view1').css('opacity', opacity);
                            });
                        }
                    });
                }, this);
            }
        },

        _initCheckout: function (amazonPayButton) {
            if (!customerData.get('checkout-data')().selectedBillingAddress
                && this.buttonType === 'PayNow'
                && this._isPayOnly()) {
                return;
            }

            var payloadType = this.buttonType ?
                'paynow' :
                'checkout';
            this._loadInitCheckoutPayload(function (initCheckoutPayload) {
                amazonPayButton.initCheckout(initCheckoutPayload);
            }, payloadType);
            customerData.invalidate('*');
        },

        /**
         * Redraw button if needed
         **/
        _subscribeToCartUpdates: function () {
            var self = this;

            amazonCheckout.withAmazonCheckout(function (amazon, args) {
                var cartData = customerData.get('cart');
                cartData.subscribe(function (updatedCart) {
                    if (!$(self.options.hideIfUnavailable).first().is(':visible')) {
                        self._draw();
                    }
                });
            });
        },

        click: function () {
            this.element.children().first().trigger('click');
        }
    });


    var cart = customerData.get('cart'),
        customer = customerData.get('customer'),
        canCheckoutWithAmazon = false;

    // to use Amazon Pay: customer needs to be logged in, or guest checkout allowed, or Amazon Sign-in enabled
    if (customer().firstname || amazonStorage.isGuestCheckoutEnabled || amazonStorage.isLwaEnabled) {
        canCheckoutWithAmazon = true;
    }

    if (amazonStorage.isEnabled && canCheckoutWithAmazon) {
        return $.amazon.AmazonButton;
    } else {
        return function(config, element) {
            customer.subscribe(function() {
                if (customer().firstname || amazonStorage.isGuestCheckoutEnabled || amazonStorage.isLwaEnabled) {
                    $(element).AmazonButton();
                }
            });
        };
    }
});
