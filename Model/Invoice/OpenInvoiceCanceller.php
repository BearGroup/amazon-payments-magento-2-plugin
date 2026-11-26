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

namespace Amazon\Pay\Model\Invoice;

use Magento\Sales\Model\Order\Invoice;

class OpenInvoiceCanceller
{
    /**
     * Payment fields that Payment::cancelInvoice() subtracts the invoice amounts from
     */
    private const PAID_AMOUNT_FIELDS = [
        'amount_paid',
        'base_amount_paid',
        'shipping_captured',
        'base_shipping_captured',
    ];

    /**
     * Cancel an open (never paid) invoice
     *
     * Invoice::cancel() takes the invoice total off the amounts paid of the payment even though an open invoice
     * never added to them, which would leave a negative amount paid. Keep those amounts as they were.
     *
     * Nothing is saved here: the caller saves the order, which saves its payment, with the invoice added as a
     * related object. The invoice must belong to that order instance (Order::getInvoiceCollection() does that).
     *
     * @param Invoice $invoice
     * @return void
     */
    public function cancel(Invoice $invoice)
    {
        $payment = $invoice->getOrder()->getPayment();
        $paidAmounts = [];
        foreach (self::PAID_AMOUNT_FIELDS as $field) {
            $paidAmounts[$field] = $payment->getData($field);
        }
        $invoice->cancel();
        $payment->addData($paidAmounts);
    }
}
