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
    'jquery/jquery-storageapi'
], function ($) {
    'use strict';

    var probeKey = 'amzn-storage-probe';

    /**
     * Stand-in used when the browser denies access to both localStorage and
     * cookies - iOS Safari with "Block All Cookies", or Chrome's per-site
     * "Block" for cookies and site data. In that state jquery-storageapi has no
     * backing store left: window.localStorage throws, its cookie fallback is
     * silently dropped, and a namespaced get() then throws a ReferenceError.
     * Keeping the same surface lets callers stay unaware of the difference; the
     * only loss is that nothing survives the page.
     *
     * @returns {Object}
     */
    function memoryStorage() {
        var data = {};

        return {
            /**
             * @param {String} [key]
             * @returns {*}
             */
            get: function (key) {
                return key === undefined ? $.extend({}, data) : data[key];
            },

            /**
             * @param {String} key
             * @param {*} value
             * @returns {*}
             */
            set: function (key, value) {
                data[key] = value;

                return value;
            },

            /**
             * @param {String} key
             */
            remove: function (key) {
                delete data[key];
            },

            /**
             * @returns {Boolean}
             */
            removeAll: function () {
                data = {};

                return true;
            },

            /**
             * @param {String} key
             * @returns {Boolean}
             */
            isSet: function (key) {
                return data.hasOwnProperty(key);
            },

            /**
             * @returns {Boolean}
             */
            isEmpty: function () {
                return $.isEmptyObject(data);
            },

            /**
             * @returns {Array}
             */
            keys: function () {
                return Object.keys(data);
            }
        };
    }

    /**
     * Can this namespace actually be written to and read back?
     *
     * @param {Object} storage
     * @returns {Boolean}
     */
    function isUsable(storage) {
        try {
            storage.set(probeKey, 'ok');

            if (storage.get(probeKey) !== 'ok') {
                return false;
            }

            storage.remove(probeKey);

            return true;
        } catch (e) {
            return false;
        }
    }

    /**
     * Namespaced localStorage that degrades to an in-memory store instead of
     * throwing when the browser denies access to storage.
     *
     * @param {String} namespace
     * @returns {Object}
     */
    return function (namespace) {
        var storage;

        try {
            storage = $.initNamespaceStorage(namespace).localStorage;
        } catch (e) {
            return memoryStorage();
        }

        return isUsable(storage) ? storage : memoryStorage();
    };
});
