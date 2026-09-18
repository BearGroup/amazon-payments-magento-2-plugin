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

    /**
     * The URL each region's checkout.js was first configured with, and how many
     * times its load has been given up on. See resetModule().
     */
    var baseUrls = {};
    var attempts = {};

    /**
     * The RequireJS module id to request for the region's next attempt.
     *
     * @param {String} baseName
     * @returns {String}
     */
    var moduleFor = function (baseName) {
        var attempt = attempts[baseName] || 0;

        return attempt ? baseName + '_retry' + attempt : baseName;
    };

    /**
     * Arrange for the next attempt at a region's checkout.js to go back to the
     * network, by giving it both a module id and a URL that have not been used.
     *
     * Neither half is optional. A browser reuses an identical script request
     * that is still in flight, so a retry on the same URL ends up waiting on the
     * very request that hung - hence the query string, which the CDN ignores and
     * which leaves the host, and so the CSP whitelist, alone. And a module id
     * that has already been defined is handed straight back from RequireJS'
     * cache, so the retry needs one that has not been used; a fresh id is also
     * why require.undef() is not called here, which keeps this independent of
     * how RequireJS treats an id it has already cleaned out of its registry.
     *
     * @param {String} baseName
     */
    var resetModule = function (baseName) {
        var paths = {},
            attempt,
            url;

        try {
            if (!baseUrls[baseName]) {
                // toUrl() skips the extension for a name with no dot in it, and
                // carries a urlArgs cache-buster if the theme configured one.
                url = require.toUrl(baseName).split('?')[0];
                baseUrls[baseName] = /\.js$/.test(url) ? url : url + '.js';
            }

            attempt = (attempts[baseName] || 0) + 1;
            paths[baseName + '_retry' + attempt] = baseUrls[baseName] + '?retry=' + attempt;
            require.config({paths: paths});
            attempts[baseName] = attempt;
        } catch (e) {
            // The next attempt will reuse the current module; nothing more can
            // be done here, and the report below is worth more than this error.
        }
    };

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
            var baseName = this.getCheckoutModuleName(),
                moduleName = moduleFor(baseName),
                settled = false,
                timer,
                fail = function (reason, detail, resetLoader) {
                    if (settled) {
                        return;
                    }
                    settled = true;
                    clearTimeout(timer);

                    // Let a later draw or click re-request the script instead of
                    // being handed this failure - or this hung request - again.
                    if (resetLoader) {
                        resetModule(baseName);
                    }

                    console.error('Amazon Pay: ' + reason + ' (' + moduleName + ')', detail || '');

                    if (onError) {
                        onError(reason);
                    }
                };

            // A request that timed out may still arrive afterwards. If it has,
            // use it rather than requesting the script again.
            if (typeof window.amazon !== 'undefined' && window.amazon.Pay) {
                settled = true;
                onReady(window.amazon);

                return;
            }

            timer = setTimeout(function () {
                fail('timed out loading the Amazon Pay checkout script', null, true);
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
