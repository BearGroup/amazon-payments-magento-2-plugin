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

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\ManagerInterface;

class PaymentTransactionIdUpdate
{
    /**
     * @var TransactionRepositoryInterface
     */
    private $transactionRepository;

    /**
     * @var OrderPaymentRepositoryInterface
     */
    private $paymentRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @param TransactionRepositoryInterface $transactionRepository
     * @param OrderPaymentRepositoryInterface $paymentRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        TransactionRepositoryInterface $transactionRepository,
        OrderPaymentRepositoryInterface $paymentRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->transactionRepository = $transactionRepository;
        $this->paymentRepository = $paymentRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * Set special transaction ID if AP transaction is voided
     * 
     * @param ManagerInterface $subject
     * @param OrderPaymentInterface $payment
     * @param string $type
     * @param bool $transactionBasedOn
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeGenerateTransactionId(
        ManagerInterface $subject,
        OrderPaymentInterface $payment,
        $type,
        $transactionBasedOn = false
    ) {
        $paymentMethodTitle = $payment->getAdditionalInformation('method_title') ?? '';
        if (strpos($paymentMethodTitle, 'Amazon Pay') !== false) {
            // Update existing IDs if necessary
            $authTransaction = $payment->getAuthorizationTransaction();

            if ($authTransaction) {
                $authTxnId = $authTransaction->getTxnId();

                if (strpos($authTxnId, '-C') !== false) {
                    // Auth txn ID needs to be updated to charge permission ID
                    $chargeId = $authTxnId;
                    $chargePermissionId = substr($chargeId, 0, -8);
                    $authTransaction->setTxnId($chargePermissionId);

                    $this->transactionRepository->save($authTransaction);

                    // Update other transactions on payment
                    $searchCriteria = $this->searchCriteriaBuilder
                        ->addFilter('payment_id', $payment->getEntityId())
                        ->create();
                    $transactionsList = $this->transactionRepository->getList($searchCriteria)->getItems();

                    foreach ($transactionsList as $transaction) {
                        if ($transaction->getTxnType() == Transaction::TYPE_CAPTURE) {
                            $transaction->setParentTxnId($chargePermissionId);
                            if (strpos($transaction->getTxnId(), '-capture') !== false) {
                                $transaction->setTxnId($chargeId);
                            }

                            $this->transactionRepository->save($transaction);
                        }

                        if ($transaction->getTxnType() == Transaction::TYPE_REFUND) {
                            if (strpos($transaction->getParentTxnId(), '-capture') !== false) {
                                $transaction->setParentTxnId($chargeId);
                                $this->transactionRepository->save($transaction);
                            }
                        }

                        if ($transaction->getTxnType() == Transaction::TYPE_VOID) {
                            $transaction->setParentTxnId($chargePermissionId);
                            $this->transactionRepository->save($transaction);
                        }
                    }

                    // Update payment additional information and last transaction ID
                    $payment->setAdditionalInformation('charge_id', $chargeId);
                    $payment->unsAdditionalInformation('charge_permission_id');

                    if ($payment->getLastTransId() === $authTxnId) {
                        $payment->setLastTransId($chargePermissionId);
                    }

                    if (strpos($payment->getLastTransId(), '-capture') !== false) {
                        $payment->setLastTransId($chargeId);
                    }

                    $this->paymentRepository->save($payment);
                }
            }
            
            if ($type == Transaction::TYPE_VOID) {
                $chargePermissionId = $payment->getAuthorizationTransaction()->getTxnId();
                $payment->setTransactionId($chargePermissionId . '-void');
            }
        }

        return [
            $payment,
            $type,
            $transactionBasedOn
        ];
    }
}