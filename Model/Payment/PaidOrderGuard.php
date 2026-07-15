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

namespace Amazon\Pay\Model\Payment;

use Amazon\Pay\Logger\Logger;
use Amazon\Pay\Model\Adapter\AmazonPayAdapter;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Guards order cancellation paths against voiding orders whose payment already succeeded.
 */
class PaidOrderGuard
{
    /**
     * @var AmazonPayAdapter
     */
    private $amazonAdapter;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param AmazonPayAdapter $amazonAdapter
     * @param Logger $logger
     */
    public function __construct(
        AmazonPayAdapter $amazonAdapter,
        Logger $logger
    ) {
        $this->amazonAdapter = $amazonAdapter;
        $this->logger = $logger;
    }

    /**
     * Check if payment succeeded enough to prevent canceling the order on follow-up errors.
     *
     * Checks order totals first; falls back to a live Amazon charge state lookup. When no
     * charge ID is given, $resolveChargeIdFromOrder controls whether one is derived from the
     * payment's last transaction ID (checkout-flow callers pass false: their last transaction
     * may be a checkout-session ID, not a charge).
     *
     * @param OrderInterface $order
     * @param string|null $chargeId
     * @param bool $resolveChargeIdFromOrder
     * @return bool
     */
    public function isOrderPaidOrCaptured(OrderInterface $order, $chargeId = null, $resolveChargeIdFromOrder = true)
    {
        if ((float)$order->getTotalPaid() > 0 || (float)$order->getTotalDue() <= 0.0001) {
            return true;
        }

        if (!$chargeId && $resolveChargeIdFromOrder) {
            $chargeId = $this->extractChargeIdFromOrder($order);
        }
        if (!$chargeId) {
            return false;
        }

        try {
            $charge = $this->amazonAdapter->getCharge($order->getStoreId(), $chargeId);
            return ($charge['statusDetails']['state'] ?? '') === 'Captured';
        } catch (\Exception $e) {
            $this->logger->error('Unable to verify charge state before cancel. chargeId: ' . $chargeId
                . ' Error: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Resolve charge ID from payment transaction data where possible.
     *
     * @param OrderInterface $order
     * @return string|null
     */
    public function extractChargeIdFromOrder(OrderInterface $order)
    {
        $transactionId = (string)$order->getPayment()->getLastTransId();
        if ($transactionId === '') {
            return null;
        }

        foreach (['-capture', '-void'] as $suffix) {
            if (substr($transactionId, -strlen($suffix)) === $suffix) {
                return substr($transactionId, 0, -strlen($suffix));
            }
        }

        return $transactionId;
    }
}
