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
    'Amazon_Pay/js/model/storage'
], function (amazonStorage) {
    'use strict';

    /**
     * Magento's frontend RequireJS config sets `waitSeconds: 0`, which disables
     * RequireJS' own load timeout. Without a timeout of our own, a checkout.js
     * request that never completes - a real possibility in a throttled or
     * suspended in-app browser - leaves the caller waiting on a callback that
     * never arrives, with nothing logged.
     */
    var loadTimeout = 15000;

    return {
        /**
         * Return the appropriate (region-specific) checkout.js module name
         */
        getCheckoutModuleName: function() {
            switch(amazonStorage.getRegion()) {
                case 'de':
                    return 'amazonPayCheckoutDE';
                    break;
                case 'uk':
                    return 'amazonPayCheckoutUK';
                    break;
                case 'jp':
                    return 'amazonPayCheckoutJP';
                    break;
                case 'us':
                default:
                    return 'amazonPayCheckoutUS';
                    break;
            }
        },

        /**
         * Load the region's checkout.js and hand window.amazon to onReady.
         *
         * onError is called - once - if the script cannot be loaded, does not
         * arrive within loadTimeout, or loads without exposing window.amazon.Pay.
         * Callers that latch state while waiting must use it, otherwise a failed
         * third-party script load leaves them wedged for the life of the page.
         *
         * @param {Function} onReady
         * @param {Function} [onError]
         */
        loadAmazonCheckout: function (onReady, onError) {
            var moduleName = this.getCheckoutModuleName(),
                settled = false,
                timer,
                fail = function (reason, detail, undefModule) {
                    if (settled) {
                        return;
                    }
                    settled = true;
                    clearTimeout(timer);

                    // Drop the failed module so a later draw or click re-requests
                    // the script instead of being handed the failure again. Not
                    // done on timeout: the request may still be in flight.
                    if (undefModule && typeof require.undef === 'function') {
                        try {
                            require.undef(moduleName);
                        } catch (e) {
                            // Nothing to clean up; not worth losing the report below.
                        }
                    }

                    console.error('Amazon Pay: ' + reason + ' (' + moduleName + ')', detail || '');

                    if (onError) {
                        onError(reason);
                    }
                };

            timer = setTimeout(function () {
                fail('timed out loading the Amazon Pay checkout script', null, false);
            }, loadTimeout);

            require([moduleName], function () {
                if (settled) {
                    return;
                }

                if (typeof window.amazon === 'undefined' || !window.amazon.Pay) {
                    fail('the Amazon Pay checkout script loaded without exposing window.amazon.Pay', null, true);
                    return;
                }

                settled = true;
                clearTimeout(timer);
                onReady(window.amazon);
            }, function (error) {
                fail('failed to load the Amazon Pay checkout script', error, true);
            });
        },

        /**
         * Wrapper for accessing window.amazon safely
         */
        withAmazonCheckout: function(cb, _this) {
            var args = Array.prototype.slice.call(arguments, 2);
            return this.loadAmazonCheckout(function (amazon) {
                return cb.apply(_this, [amazon].concat(args));
            });
        }
    };
});
