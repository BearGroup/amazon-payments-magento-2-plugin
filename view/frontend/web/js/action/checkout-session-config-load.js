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
    'jquery',
    'underscore',
    'mage/storage',
    'mage/url',
    'Magento_Customer/js/customer-data',
    'Amazon_Pay/js/model/safe-storage'
], function ($, _, remoteStorage, url, customerData, safeStorage) {
    'use strict';

    // A failed config request is retried once: the endpoint is same-origin and
    // a single dropped request - a backgrounded tab, a flaky mobile connection -
    // otherwise takes Amazon Pay off the page until it is reloaded.
    var maxAttempts = 2;
    var retryDelay = 1500;

    // mage/storage sets no AJAX timeout and jQuery has none by default, so a
    // request that never settles would hold the queue below - and every button
    // waiting in it - for the life of the page. Abort instead: that reports as a
    // transient failure, and is retried like one.
    var requestTimeout = 10000;

    var callbacks = [];
    var localStorage = null;
    var getLocalStorage = function () {
        if (localStorage === null) {
            localStorage = safeStorage('amzn-checkout-session-config');
        }
        return localStorage;
    };

    /**
     * Hand the result to everyone waiting on the in-flight request.
     *
     * The queue is taken and emptied before any callback runs: the
     * `callbacks.length == 1` guard below is what stops a second request being
     * issued while one is in flight, so an entry left behind by a failed
     * request - or by a callback that throws - would wedge that guard and leave
     * the button silently dead for the rest of the page's life. Each callback
     * is called in isolation for the same reason: one button throwing must not
     * cost the others their config.
     *
     * @param {Object} config
     * @param {Boolean} failed - true when the request itself failed, as opposed
     *                           to Amazon Pay being unavailable for this cart
     */
    var resolve = function (config, failed) {
        var waiting = callbacks;

        callbacks = [];

        _.each(waiting, function (waitingCallback) {
            try {
                waitingCallback(config, failed === true);
            } catch (exception) {
                console.error('Amazon Pay: a checkout session config callback failed.', exception);
            }
        });
    };

    /**
     * A request that failed outright - connection dropped, timed out, throttled,
     * or a server error - can succeed on a second attempt. A 4xx (bar 408/429)
     * is an answer, not a hiccup, and is not retried.
     *
     * @param {Object} response
     * @returns {Boolean}
     */
    var isTransient = function (response) {
        var status = response && response.status;

        return !status || status >= 500 || status === 408 || status === 429;
    };

    /**
     * @param {String|Number} cartId
     * @param {Number} attempt
     */
    var request = function (cartId, attempt) {
        var pending = remoteStorage.get(url.build('amazon_pay/checkout/config')),
            timer = setTimeout(function () {
                if (typeof pending.abort === 'function') {
                    pending.abort();
                }
            }, requestTimeout);

        pending.done(function (config) {
            clearTimeout(timer);
            getLocalStorage().set('cart_id', cartId);
            getLocalStorage().set('config', config);
            resolve(config, false);
        }).fail(function (response) {
            clearTimeout(timer);

            if (attempt < maxAttempts && isTransient(response)) {
                setTimeout(function () {
                    request(cartId, attempt + 1);
                }, retryDelay);

                return;
            }

            // Deliberately not resolved with the cached config: a stale
            // PayNow payload carries a stale charge amount. The cache is
            // left untouched so the next draw or click retries.
            //
            // Only the status is reported: the body of a non-JSON error
            // response is an HTML page that can carry form keys and customer
            // details, and console output is collected by session-replay and
            // monitoring scripts.
            console.error(
                'Amazon Pay: unable to load the checkout session config (HTTP '
                + ((response && response.status) || 0) + ' '
                + ((response && response.statusText) || 'no response')
                + ') after ' + attempt + ' attempt(s).'
            );
            resolve({}, true);
        });
    };

    /**
     * @param {Function} callback - called with (config, failed); an empty config
     *                              means Amazon Pay is not available, and
     *                              `failed` says whether that is because the
     *                              request could not be completed
     * @param {Boolean} [forceReload]
     */
    return function (callback, forceReload = false) {
        var cartId = customerData.get('cart')()['data_id'] || window.checkout.storeId;
        var config = getLocalStorage().get('config') || false;
        if (forceReload
            || cartId !== getLocalStorage().get('cart_id')
            || typeof config.checkout_payload === 'undefined'
            || !config.checkout_payload.includes(document.URL.slice(0, -1))) {
            callbacks.push(callback);
            if (callbacks.length == 1) {
                request(cartId, 1);
            }
        } else {
            callback(getLocalStorage().get('config'), false);
        }
    };
});
