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
    ['Amazon_Pay/js/model/storage'],
    function (amazonStorage) {
        'use strict';

        /**
         * Discard any stale Amazon checkout session.
         *
         * Invoked through text/x-magento-init from Amazon_Pay::config.phtml, replacing the
         * executable inline require() that template used to emit. A data block is not subject
         * to script-src, so this keeps working under an enforced Content Security Policy.
         */
        return function () {
            amazonStorage.clearAmazonCheckout();
        };
    }
);
