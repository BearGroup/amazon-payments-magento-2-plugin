/*global define*/

define([
    'Amazon_Pay/js/model/storage',
    'mage/url',
    'Magento_Ui/js/model/messageList',
    'mage/translate'
], function (amazonStorage, url, globalMessageList, $t) {
    'use strict';

    return function (errorProcessor) {
        /**
         * @param {Object} response
         * @param {Object} messageContainer
         */
        errorProcessor.process = function (response, messageContainer) {
            var error,
                isAmazonCheckout = false;

            messageContainer = messageContainer || globalMessageList;

            if (response.status == 401) { //eslint-disable-line eqeqeq
                this.redirectTo(url.build('customer/account/login/'));
            } else {
                try {
                    isAmazonCheckout = amazonStorage.isAmazonCheckout();
                } catch (exception) {
                    // Reading the checkout session must not decide which message
                    // the shopper sees: a storage failure here used to discard a
                    // perfectly good response body in favour of the generic
                    // message below.
                    console.error('Amazon Pay: unable to read the checkout session.', exception);
                }

                if (isAmazonCheckout && response.hasOwnProperty('message')) {
                    error = {
                        message: $t(response.message)
                    };
                } else {
                    try {
                        error = JSON.parse(response.responseText);
                    } catch (exception) {
                        // Not a JSON body - an HTML error page, a redirect to the
                        // login form, or an empty response. Log it: the generic
                        // message that replaces it carries no clue as to what
                        // actually failed, and this mixin overrides the error
                        // processor for every payment method on the page.
                        console.error(
                            'Amazon Pay: unexpected non-JSON response (HTTP ' + response.status + ').',
                            response.responseText
                        );
                        error = {
                            message: $t('Something went wrong with your request. Please try again later.')
                        };
                    }
                }

                messageContainer.addErrorMessage(error);
            }
        }

        return errorProcessor;
    }
});
