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
namespace Amazon\Pay\Plugin;

use ParadoxLabs\Subscriptions\Model\Service\Payment;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Quote\Api\Data\CartInterface;
use Amazon\Pay\Gateway\Config\Config;

class PaymentTokenService
{
    /**
     * @param Payment $paymentService
     * @param PaymentTokenInterface[] $cards
     * @param CartInterface $quote
     * @return PaymentTokenInterface[]
     */
    public function afterGetActiveCustomerCardsForQuote(
        Payment $paymentService,
        $cards,
        CartInterface $quote
	) {
        if ($quote->getPayment()->getMethod() === Config::VAULT_CODE) {
            return array_filter($cards, function ($card) use ($paymentService, $quote) {
                return $card->getPaymentMethodCode() !== Config::CODE 
                    || $card->getId() === $paymentService->getQuoteCard($quote)->getId();
            });
        }

        return array_filter($cards, function ($card) {
            return $card->getPaymentMethodCode() !== Config::CODE;
        });
    }
}

