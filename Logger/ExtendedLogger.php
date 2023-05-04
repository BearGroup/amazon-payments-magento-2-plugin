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
namespace Amazon\Pay\Logger;

use DateTimeZone;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Enables implementation of custom log file.
 */
class ExtendedLogger extends \Monolog\Logger
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        string $name,
        array $handlers = [],
        array $processors = [],
        ?DateTimeZone $timezone = null
    ) {
        $this->scopeConfig = $scopeConfig;

        if ($timezone) {
            parent::__construct($name, $handlers, $processors, $timezone);
        } else {
            parent::__construct($name, $handlers, $processors);
        }

    }

    /**
     * @return bool
     */
    private function isEnabled() : bool
    {
        return (bool)$this->scopeConfig->getValue(
            'payment/amazon_payment/extended_logging',
            ScopeInterface::SCOPE_STORE,
        );
    }

    /**
     * @param $message
     * @param $sessionId
     * @param array $context
     * @return void
     */
    public function debug($message, $method = '', string $sessionId = '', array $context = []): void
    {
        if ($this->isEnabled()) {
            parent::debug($this->formatMessage($message, $method, $sessionId), $context);
        }
    }

    /**
     * @param $message
     * @param $sessionId
     * @param array $context
     * @return void
     */
    public function info($message, $method = '', string $sessionId = '', array $context = []): void
    {
        if ($this->isEnabled()) {
            parent::info($this->formatMessage($message, $method, $sessionId), $context);
        }

    }

    /**
     * @param $message
     * @param $sessionId
     * @param array $context
     * @return void
     */
    public function error($message, $method = '', string $sessionId = '', array $context = []): void
    {
        if ($this->isEnabled()) {
            parent::error($this->formatMessage($message, $method, $sessionId), $context);
        }
    }

    /**
     * @param $originalMessage
     * @param $method
     * @param $sessionId
     * @return string
     */
    private function formatMessage($originalMessage, $method, $sessionId = '')
    {
        return  $sessionId . ' | METHOD: ' . $method . ' | MSG: ' . $originalMessage;
    }

}
