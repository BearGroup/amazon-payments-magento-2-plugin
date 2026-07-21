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

namespace Amazon\Pay\Cron;

use Amazon\Pay\Helper\Transaction as TransactionHelper;
use Amazon\Pay\Logger\Logger;
use Amazon\Pay\Model\Adapter\AmazonPayAdapter;
use Amazon\Pay\Model\CheckoutSessionManagement;
use Amazon\Pay\Model\Payment\PaidOrderGuard;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Amazon\Pay\Model\AsyncManagement\Charge as AsyncCharge;

class CleanUpIncompleteSessions
{
    public const SESSION_STATUS_STATE_CANCELED = 'Canceled';
    public const SESSION_STATUS_STATE_OPEN = 'Open';
    public const SESSION_STATUS_STATE_COMPLETED = 'Completed';

    protected const LOG_PREFIX = 'AmazonCleanUpIncompleteSesssions: ';

    /**
     * @var TransactionHelper
     */
    protected $transactionHelper;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var AmazonPayAdapter
     */
    protected $amazonPayAdapter;

    /**
     * @var CheckoutSessionManagement
     */
    protected $checkoutSessionManagement;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var AsyncCharge
     */
    protected $asyncCharge;

    /**
     * @var PaidOrderGuard
     */
    protected $paidOrderGuard;

    /**
     * @param TransactionHelper $transactionHelper
     * @param Logger $logger
     * @param AmazonPayAdapter $amazonPayAdapter
     * @param CheckoutSessionManagement $checkoutSessionManagement
     * @param OrderRepositoryInterface $orderRepository
     * @param AsyncCharge $asyncCharge
     * @param PaidOrderGuard $paidOrderGuard
     */
    public function __construct(
        TransactionHelper $transactionHelper,
        Logger $logger,
        AmazonPayAdapter $amazonPayAdapter,
        CheckoutSessionManagement $checkoutSessionManagement,
        OrderRepositoryInterface $orderRepository,
        AsyncCharge $asyncCharge,
        PaidOrderGuard $paidOrderGuard
    ) {
        $this->transactionHelper = $transactionHelper;
        $this->logger = $logger;
        $this->amazonPayAdapter = $amazonPayAdapter;
        $this->checkoutSessionManagement = $checkoutSessionManagement;
        $this->orderRepository = $orderRepository;
        $this->asyncCharge = $asyncCharge;
        $this->paidOrderGuard = $paidOrderGuard;
    }

    /**
     * Execute cleanup
     *
     * @return void
     */
    public function execute()
    {
        // Get transactions
        $incompleteTransactionList = $this->transactionHelper->getIncomplete();

        // Process each transaction
        foreach ($incompleteTransactionList as $transactionData) {
            $this->processTransaction($transactionData);
        }
    }

    /**
     * Process a single transaction
     *
     * @param array $transactionData
     * @return void
     */
    protected function processTransaction(array $transactionData)
    {
        $checkoutSessionId = $transactionData['checkout_session_id'];
        $orderId = $transactionData['order_id'];

        $this->logger->debug(self::LOG_PREFIX . 'Cleaning up checkout session id: ' . $checkoutSessionId);

        try {
            // Check current state of Amazon checkout session
            $amazonSession = $this->amazonPayAdapter->getCheckoutSession(
                $transactionData['store_id'],
                $checkoutSessionId
            );
            // On API errors the adapter does not throw; it returns the decoded error
            // body with the HTTP status attached, and statusDetails is absent
            $status = (int) ($amazonSession['status'] ?? 200);
            if (!in_array($status, [200, 201])) {
                if ($status === 404) {
                    $logMessage = 'Checkout session no longer exists (404 ResourceNotFound), ';
                    $logMessage .= 'cancelling order and closing transaction: ' . $checkoutSessionId;
                    $this->logger->info(self::LOG_PREFIX . $logMessage);
                    $this->cancelOrder(
                        $orderId,
                        'The Amazon Pay checkout session expired or no longer exists.'
                    );
                    $this->transactionHelper->closeTransaction($transactionData['transaction_id']);
                } else {
                    $logMessage = 'Unexpected status ' . $status . ' fetching checkout session: ';
                    $logMessage .= $checkoutSessionId;
                    $this->logger->error(self::LOG_PREFIX . $logMessage);
                }
                return;
            }

            $state = $amazonSession['statusDetails']['state'] ?? false;
            switch ($state) {
                case self::SESSION_STATUS_STATE_CANCELED:
                    $logMessage = 'Checkout session Canceled, cancelling order and closing transaction: ';
                    $logMessage .= $checkoutSessionId;
                    $this->logger->info(self::LOG_PREFIX . $logMessage);
                    $cancelledMessage = $this->checkoutSessionManagement->getCanceledMessage($amazonSession);
                    $this->cancelOrder($orderId, $cancelledMessage);
                    $this->transactionHelper->closeTransaction($transactionData['transaction_id']);
                    break;
                case self::SESSION_STATUS_STATE_OPEN:
                    $logMessage = 'Checkout session Open, completing: ';
                    $logMessage .= $checkoutSessionId;
                    $this->logger->debug(self::LOG_PREFIX . $logMessage);
                    try {
                        $this->checkoutSessionManagement->completeCheckoutSession(
                            $checkoutSessionId,
                            null,
                            $orderId
                        );
                    } catch (\Exception $e) {
                        // completeCheckoutSession cancels an unpaid order when completion
                        // fails (e.g. the quote was purged). If it did, close the still-open
                        // transaction so the canceled order is not left with a dangling one,
                        // mirroring the Canceled/404 branches. Otherwise rethrow so genuine
                        // failures are logged and retried.
                        $order = $this->loadOrder($orderId);
                        if ($order && $order->getState() === Order::STATE_CANCELED) {
                            $logMessage = 'Order canceled during completion, closing transaction: ';
                            $logMessage .= $checkoutSessionId;
                            $this->logger->info(self::LOG_PREFIX . $logMessage);
                            $this->transactionHelper->closeTransaction($transactionData['transaction_id']);
                        } else {
                            throw $e;
                        }
                    }
                    break;
                case self::SESSION_STATUS_STATE_COMPLETED:
                    $logMessage = 'Checkout session Completed, nothing more needed: ';
                    $logMessage .= $checkoutSessionId;
                    $this->logger->debug(self::LOG_PREFIX . $logMessage);
                    break;
            }
        } catch (\Exception $e) {
            $errorMessage = 'Unable to process checkoutSessionId: ' . $checkoutSessionId;
            $this->logger->error(self::LOG_PREFIX . $errorMessage . '. ' . $e->getMessage());
        }
    }

    /**
     * Cancel the order
     *
     * @param int $orderId
     * @param string $reasonMessage
     * @return void
     */
    protected function cancelOrder($orderId, $reasonMessage = '')
    {
        $order = $this->loadOrder($orderId);

        if ($order) {
            if ($this->paidOrderGuard->isOrderPaidOrCaptured($order)) {
                $this->logger->info(
                    self::LOG_PREFIX . 'Skip cancellation for already paid/captured order: ' . $orderId
                );
                return;
            }
            $this->checkoutSessionManagement->cancelOrder($order, null, $reasonMessage);
        } else {
            $this->logger->error(self::LOG_PREFIX . 'Order not found for ID: ' . $orderId);
        }
    }

    /**
     * Load order by ID
     *
     * @param int $orderId
     * @return OrderInterface
     */
    protected function loadOrder($orderId)
    {
        try {
            return $this->orderRepository->get($orderId);
        } catch (\Exception $e) {
            $this->logger->error(self::LOG_PREFIX . 'Error loading order: ' . $e->getMessage());
            return null;
        }
    }

}
