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

use Magento\Vault\Model\PaymentTokenRepository as TokenRepository;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use ParadoxLabs\Subscriptions\Model\Source\Status;
use ParadoxLabs\Subscriptions\Model\SubscriptionRepository;
use Amazon\Pay\Helper\SubscriptionHelper;
use Amazon\Pay\Gateway\Config\Config;

class PaymentTokenRepository
{
    /**
     * @var SubscriptionHelper
     */
    private $subscriptionHelper;

    /**
     * @var SubscriptionRepository
     */
    private $subscriptionRepository;

    /**
    * @var SearchCriteriaBuilder
    */
    protected $searchCriteriaBuilder;

    /**
     * @param SubscriptionHelper $subscriptionHelper
     * @param SubscriptionRepository $subscriptionRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        SubscriptionHelper $subscriptionHelper,
        SubscriptionRepository $subscriptionRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->subscriptionHelper = $subscriptionHelper;
        $this->subscriptionRepository = $subscriptionRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    public function aroundDelete(
        TokenRepository $tokenRepository,
        callable $proceed,
        PaymentTokenInterface $paymentToken
    ) {
        if ($paymentToken->getPaymentMethodCode() === Config::CODE) {
            // Cancel associated AP subscriptions
            $customerId = $paymentToken->getCustomerId();
            $publicHash = $paymentToken->getPublicHash();
            $vaultCode = Config::VAULT_CODE;
            $searchCriteria = $this->searchCriteriaBuilder->addFilter('customer_id', $customerId)
                ->addFilter('status', Status::STATUS_ACTIVE)
                ->create();

            $activeSubscriptions = $this->subscriptionRepository->getList($searchCriteria)
                ->getItems();
            $amazonSubscriptions = array_filter($activeSubscriptions, function ($subscription) use ($publicHash, $vaultCode) {
                $quote = $subscription->getQuote();
                return $quote->getPayment()
                    ->getMethod() === $vaultCode
                    && $quote->getPayment()->getAdditionalInformation()['public_hash'] === $publicHash;
            });

            $this->subscriptionHelper->cancelToken(reset($amazonSubscriptions)->getQuote(), $paymentToken);
            foreach ($amazonSubscriptions as $amazonSubscription) {
                $amazonSubscription->setStatus(Status::STATUS_CANCELED, 'Subscription canceled due to payment token deletion');
                $this->subscriptionRepository->save($amazonSubscription);
            }
            
            return true;
        }

        return $proceed($paymentToken);
    }
}
