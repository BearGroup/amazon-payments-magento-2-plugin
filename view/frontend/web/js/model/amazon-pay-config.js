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
define(
    ['uiRegistry'],
    function (registry) {
        'use strict';

        /**
         * Read the JSON data block emitted by Amazon_Pay::config.phtml.
         *
         * The config is delivered as data rather than as an executable inline script so that a
         * Content Security Policy never applies to it — there is no per-request nonce to go
         * stale when the markup is cached. Reading it here is also synchronous, so consumers
         * can no longer race ahead of the value being published.
         *
         * @returns {Object|undefined}
         */
        var readConfig = function () {
                var element = document.getElementById('amazon-pay-config');

                if (!element) {
                    return undefined;
                }

                try {
                    return JSON.parse(element.textContent);
                } catch (e) {
                    return undefined;
                }
            },
            config = registry.get('amazonPay');

        if (config === undefined) {
            config = readConfig();

            if (config !== undefined) {
                // publish for anything reading the registry key directly
                registry.set('amazonPay', config);
            }
        }

        config = config || {};

        return {
            /**
             * @returns {string}
             */
            getCode: function () {
                return this.getValue('code');
            },

            /**
             * @returns {string}
             */
            getVaultCode: function () {
                return this.getValue('vault_code');
            },

            /**
             * Get config value
             */
            getValue: function (key, defaultValue) {
                if (config.hasOwnProperty(key)) {
                    return config[key];
                } else if (defaultValue !== undefined) {
                    return defaultValue;
                }
            },

            /**
             * Is amazonPay defined?
             */
            isDefined: function () {
                return registry.get('amazonPay') !== undefined;
            }

        };
    }
);
