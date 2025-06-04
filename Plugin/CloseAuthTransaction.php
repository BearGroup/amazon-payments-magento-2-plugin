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

use \Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Model\Order\Payment\Transaction;

class CloseAuthTransaction
{
    /**
     * @var OrderPaymentRepositoryInterface
     */
    protected $orderPaymentRepository;

    /**
     * @param \Magento\Sales\Api\OrderPaymentRepositoryInterface $orderPaymentRepository
     */
    public function __construct(OrderPaymentRepositoryInterface $orderPaymentRepository)
    {
        $this->orderPaymentRepository = $orderPaymentRepository;
    }

    /**
     * Ensure transaction saves before attempting to close parent (self) infinitely.
     *
     * Added for change of parent transaction linkage in 2.4.8.
     *
     * @param Transaction $subject
     * @param bool $shouldSave
     * @return array
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function beforeClose(
        Transaction $subject,
        bool $shouldSave = true
    ) {
        if ($paymentId = $subject->getPaymentId()) {
            if ($payment = $this->orderPaymentRepository->get($paymentId)) {
                $paymentMethodTitle = $payment->getAdditionalInformation('method_title') ?? '';
                if (strpos($paymentMethodTitle, 'Amazon Pay') !== false &&
                    $subject->getTxnType() == Transaction::TYPE_AUTH) {
                    $shouldSave = true;
                    $subject->isFailsafe(true);
                }
            }
        }

        return [$shouldSave];
    }
}
