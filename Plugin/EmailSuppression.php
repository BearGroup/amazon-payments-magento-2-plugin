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

use Magento\Framework\Event\Observer;
use Magento\Quote\Observer\SubmitObserver;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Amazon\Pay\Gateway\Config\Config;

class EmailSuppression
{
    /**
     * Prevent email unless status is processing
     *
     * @param Observer $observer
     */
    public function beforeExecute(SubmitObserver $subject, Observer $observer)
    {
        /** @var Order $order */
        $order = $observer->getEvent()->getOrder();
        /** @var Payment $payment */
        $payment = $order->getPayment();

        if ($payment->getMethod() == Config::CODE && $order->getState() != Order::STATE_PROCESSING) {
            $order->setCanSendNewEmailFlag(false);
        }

        return null;
    }
}
