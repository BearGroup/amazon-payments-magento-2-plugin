<?php
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
namespace Amazon\Pay\Model\Config\Backend;

/**
 * Backend model for the private key entered as text
 */
class PrivateKeyText extends \Magento\Config\Model\Config\Backend\Encrypted
{
    /**
     * Value the admin form writes into the text field while no key has been entered
     */
    public const PLACEHOLDER = '------';

    /**
     * Keep the stored key when the form submits its placeholder instead of a key
     *
     * @return void
     */
    public function beforeSave()
    {
        if (trim((string)$this->getValue()) === self::PLACEHOLDER) {
            $this->_dataSaveAllowed = false;
            return;
        }

        parent::beforeSave();
    }
}
