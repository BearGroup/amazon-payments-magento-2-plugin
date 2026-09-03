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

    var callbacks = [];
    var localStorage = null;
    var getLocalStorage = function () {
        if (localStorage === null) {
            localStorage = safeStorage('amzn-checkout-session-config');
        }
        return localStorage;
    };

    /**
     * Hand the config to everyone waiting on the in-flight request.
     *
     * The queue is taken and emptied before any callback runs: the
     * `callbacks.length == 1` guard below is what stops a second request being
     * issued while one is in flight, so an entry left behind by a failed
     * request - or by a callback that throws - would wedge that guard and leave
     * the button silently dead for the rest of the page's life.
     *
     * @param {Object} config
     */
    var resolve = function (config) {
        var waiting = callbacks;

        callbacks = [];

        _.each(waiting, function (waitingCallback) {
            waitingCallback(config);
        });
    };

    return function (callback, forceReload = false) {
        var cartId = customerData.get('cart')()['data_id'] || window.checkout.storeId;
        var config = getLocalStorage().get('config') || false;
        if (forceReload
            || cartId !== getLocalStorage().get('cart_id')
            || typeof config.checkout_payload === 'undefined'
            || !config.checkout_payload.includes(document.URL.slice(0, -1))) {
            callbacks.push(callback);
            if (callbacks.length == 1) {
                remoteStorage.get(url.build('amazon_pay/checkout/config')).done(function (config) {
                    getLocalStorage().set('cart_id', cartId);
                    getLocalStorage().set('config', config);
                    resolve(config);
                }).fail(function (response) {
                    // Deliberately not resolved with the cached config: a stale
                    // PayNow payload carries a stale charge amount. The cache is
                    // left untouched so the next draw or click retries.
                    console.error('Amazon Pay: unable to load the checkout session config.', response);
                    resolve({});
                });
            }
        } else {
            callback(getLocalStorage().get('config'));
        }
    };
});
