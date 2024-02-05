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

use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Creditmemo;

class PaymentRefund
{
    /**
     * Unset invoices so that they need to be reloaded, 
     * in case the transaction ID needs to be modified.
     * 
     * @param Payment $subject
     * @param CreditMemo $creditmemo
     * @return Creditmemo
     */
    public function beforeRefund(
        Payment $subject,
        Creditmemo $creditmemo
    ) {
        $invoice = $creditmemo->getInvoice();
        if ($invoice && strpos($invoice->getTransactionId(), '-capture') !== false) {
            $creditmemo->setData('invoice', null);
        }

        return [$creditmemo];
    }
  
}