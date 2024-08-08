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

use Amazon\Pay\Gateway\Config\Config;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;

class EmailOrderSender
{
    /**
     * Prevent order confirmation email if status is Payment Review
     *
     * @param OrderSender $subject
     * @param callable $proceed
     * @param Order $order
     * @param bool $forceSyncMode
     * @return array
     */
    public function aroundSend(OrderSender $subject, callable $proceed, Order $order, $forceSyncMode = false)
    {
        $paymentMethodCode = $order->getPayment()->getMethod();
        $orderStatus = $order->getStatus();

        if ($paymentMethodCode == Config::CODE) {
            // Keep order in email confirmation queue until payment has successfully processed
            if ($orderStatus == Order::STATE_PAYMENT_REVIEW) {
                $order->setSendEmail(1);
                return false;
            }

            if ($orderStatus != Order::STATE_PROCESSING) {
                $order->setSendEmail(0);
                return false;
            }
        }

        return $proceed($order, $forceSyncMode);
    }
}
