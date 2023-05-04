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
namespace Amazon\Pay\Logger\Handler;

use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Logger\Handler\Base;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Monolog\Logger;

class Extended extends Base
{
    const FILENAME = '/var/log/extended.log';

    /**
     * @var string
     */
    protected $fileName = self::FILENAME;

    /**
     * @var int
     */
    protected $loggerType = Logger::INFO;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    public function __construct(
        DriverInterface $filesystem,
        $filePath,
        $fileName,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->setLoggerType();
        parent::__construct($filesystem, $filePath, $fileName);
    }

    public function isEnabled()
    {
        return $this->scopeConfig->getValue(
            'payment/amazon_payment/extended_logging',
            ScopeInterface::SCOPE_STORE,
        );
    }

    /**
     * @return void
     */
    private function setLoggerType() :void
    {
        $logTypeConfig = $this->scopeConfig->getValue(
            'payment/amazon_payment/extended_logging_level',
            ScopeInterface::SCOPE_STORE,
        );

        $this->loggerType = match ($logTypeConfig) {
            'debug' => Logger::DEBUG,
            default => Logger::INFO,
        };
    }

}
