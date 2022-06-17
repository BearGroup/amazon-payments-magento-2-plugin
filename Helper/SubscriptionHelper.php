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
namespace Amazon\Pay\Helper;

use Amazon\Pay\Model\Adapter\AmazonPayAdapter;
use ParadoxLabs\Subscriptions\Model\Subscription;
use Magento\Quote\Model\Quote;
use Magento\Vault\Model\PaymentToken;
use Magento\Vault\Model\PaymentTokenRepository;

class SubscriptionHelper
{
    /**
     * @var AmazonPayAdapter
     */
    private $amazonAdapter;

    /**
     * @param PaymentTokenRepository $paymentTokenRepository
     */
    private $paymentTokenRepository;

    /**
     * @param AmazonPayAdapter $amazonAdapter
     * @param PaymentTokenRepository $paymentTokenRepository
     */
    public function __construct(
        AmazonPayAdapter $amazonAdapter,
        PaymentTokenRepository $paymentTokenRepository
    ) {
        $this->amazonAdapter = $amazonAdapter;
        $this->paymentTokenRepository = $paymentTokenRepository;
    }

    /**
     * Close charge permission and deactivate token when subscription is canceled.
     *
     * @param Quote $quote
     * @param PaymentToken $token
     * @return void
     */
    public function cancelToken(Quote $quote, PaymentToken $token)
    {
        $this->amazonAdapter->closeChargePermission(
            $quote->getStoreId(),
            $token->getGatewayToken(),
            'Canceled due to cancellation of subscription by the customer.'
        );

        $token->setIsActive(false);
        $token->setIsVisible(false);
        $this->paymentTokenRepository->save($token);
    }

    public function hasShorterFrequency(array $first, array $second)
    {
        $unitMap = [
            'day'   => 1,
            'week'  => 2,
            'month' => 3,
            'year'  => 4
        ];

        if ($unitMap[strtolower($first['unit'])] < $unitMap[strtolower($second['unit'])]) {
            return true;
        } elseif ($unitMap[strtolower($first['unit'])] == $unitMap[strtolower($second['unit'])]) {
            return $first['value'] > $second['value'];
        }

        return false;
    }
}
