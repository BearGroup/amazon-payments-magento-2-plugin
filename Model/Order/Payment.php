<?php

namespace Amazon\Pay\Model\Order;

use Magento\Sales\Model\Order\Payment\Transaction;

class Payment extends \Magento\Sales\Model\Order\Payment
{
    const AMAZON_PAY_METHODS =  [
        'amazon_payment_v2',
        'amazon_payment_v2_vault'
    ];

    const SELLER_CENTRAL_URL = 'https://sellercentral.amazon.com/external-payments/pmd/payment-details';

    /**
     * Append transaction ID (if any) message to the specified message
     *
     * @param Transaction|null $transaction
     * @param string $message
     * @return string
     */
    protected function _appendTransactionToMessage($transaction, $message): string
    {
        if ($transaction) {

            $method = $transaction->getPayment()->getMethod();
            $txnId = is_object($transaction) ? $transaction->getHtmlTxnId() : $transaction;

            // If an amazon pay payment method was used we're going to turn the charge permission id into a link to sellercentral
            if (in_array($method, self::AMAZON_PAY_METHODS, true)) {

                $paymentAdditionalInformation = $transaction->getPayment()->getAdditionalInformation();
                $chargePermissionId = $paymentAdditionalInformation['charge_permission_id'] ?? false;
                if ($chargePermissionId) {
                    return $this->setSellerCentralPaymentDetailsLink($message, $chargePermissionId, $txnId);
                }
            }

            $message .= ' ' . __('Transaction ID: "%1"', $txnId);
        }

        return $message;
    }

    /**
     * @param $message
     * @param $chargePermissionId
     * @param $txnId
     * @return string
     */
    private function setSellerCentralPaymentDetailsLink($message, $chargePermissionId, $txnId): string
    {
        // $txnId is a bit varying depending on the situation. Can be a session id, or the orderReferenceId with an appended status flag
        $link = '<a href="' . self::SELLER_CENTRAL_URL . '?orderReferenceId=' . $chargePermissionId . '">' . $txnId . '</a>';
        $message .= ' ' . __('Transaction ID: "%1"', $link);
        return $message;
    }
}
