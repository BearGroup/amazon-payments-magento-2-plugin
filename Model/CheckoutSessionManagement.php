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

namespace Amazon\Pay\Model;

use Amazon\Pay\Api\Data\CheckoutSessionInterface;
use Amazon\Pay\Gateway\Config\Config;
use Amazon\Pay\Helper\Session;
use Amazon\Pay\Model\Config\Source\AuthorizationMode;
use Amazon\Pay\Model\Config\Source\PaymentAction;
use Amazon\Pay\Helper\Customer as CustomerHelper;
use Amazon\Pay\Model\Customer\CompositeMatcher as Matcher;
use Amazon\Pay\Api\Data\AmazonCustomerInterface;
use Amazon\Pay\Model\Exception\OrderFailureException;
use Amazon\Pay\Model\Invoice\OpenInvoiceCanceller;
use Magento\Framework\App\ObjectManager;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Framework\Encryption\Encryptor;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Sales\Api\Data\TransactionInterface as Transaction;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Magento\Vault\Model\PaymentTokenFactory;
use Magento\Integration\Model\Oauth\TokenFactory as TokenModelFactory;
use Magento\Authorization\Model\UserContextInterface as UserContext;
use Magento\Framework\Phrase\Renderer\Translate as Translate;
use Magento\SalesRule\Model\Coupon\UpdateCouponUsages;
use Magento\Store\Model\ScopeInterface;

class CheckoutSessionManagement implements \Amazon\Pay\Api\CheckoutSessionManagementInterface
{
    protected const GENERIC_COMPLETE_CHECKOUT_ERROR_MESSAGE = 'Unable to complete Amazon Pay checkout.';
    protected const ADDRESS_CHANGED_CHECKOUT_ERROR_MESSAGE = 'Shipping address mismatch.';
    private const CAPTURE_STATE_UNKNOWN = 'Unknown';

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \Magento\Quote\Model\QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * @var \Magento\Quote\Api\CartManagementInterface
     */
    private $cartManagement;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var CustomerRegistry
     */
    private $customerRegistry;

    /**
     * @var Encryptor
     */
    private $encryptor;

    /**
     * @var \Magento\Sales\Api\OrderPaymentRepositoryInterface
     */
    private $paymentRepository;

    /**
     * @var \Magento\Framework\Validator\Factory
     */
    private $validatorFactory;

    /**
     * @var \Magento\Directory\Model\ResourceModel\Country\CollectionFactory
     */
    private $countryCollectionFactory;

    /**
     * @var \Magento\Sales\Api\TransactionRepositoryInterface
     */
    private $transactionRepository;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $magentoCheckoutSession;

    /**
     * @var \Amazon\Pay\Domain\AmazonAddressFactory
     */
    private $amazonAddressFactory;

    /**
     * @var \Amazon\Pay\Helper\Address
     */
    private $addressHelper;

    /**
     * @var \Amazon\Pay\Helper\Data
     */
    private $amazonHelper;

    /**
     * @var AmazonConfig
     */
    private $amazonConfig;

    /**
     * @var Adapter\AmazonPayAdapter
     */
    private $amazonAdapter;

    /**
     * @var AsyncManagement
     */
    private $asyncManagement;

    /**
     * @var AsyncManagement\Charge
     */
    private $asyncCharge;

    /**
     * @var array
     */
    private $amazonSessions = [];

    /**
     * @var array
     */
    private $carts = [];

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\CollectionFactory
     */
    private $orderCollectionFactory;

    /**
     * @var MaskedQuoteIdToQuoteIdInterface
     */
    private $maskedQuoteIdConverter;

    /**
     * @var PaymentTokenRepositoryInterface
     */
    private $paymentTokenRepository;

    /**
     * @var PaymentTokenFactory
     */
    private $paymentTokenFactory;

    /**
     * @var PaymentTokenManagement
     */
    private $paymentTokenManagement;

    /**
     * @var \Amazon\Pay\Model\Subscription\SubscriptionManager
     */
    private $subscriptionManager;

    /**
     * @var CustomerHelper
     */
    private $customerHelper;

    /**
     * @var Matcher
     */
    private $matcher;

    /**
     * Token Model
     *
     * @var TokenModelFactory
     */
    private $tokenModelFactory;

    /**
     *
     * @var UserContext
     */
    private $userContext;

    /**
     * @var \Amazon\Pay\Logger\Logger
     */
    private $logger;

    /**
     * @var Session
     */
    private $session;

    /**
     * @var Translate
     */
    private $translationRenderer;

    /**
     * @var UpdateCouponUsages
     */
    private $updateCouponUsages;

    /**
     * @var OpenInvoiceCanceller
     */
    private $openInvoiceCanceller;

    /**
     * Capture state of the orders whose charge was or may have been captured at Amazon in this request, by order ID
     *
     * The state is Captured, CaptureInitiated, or CAPTURE_STATE_UNKNOWN when the capture gave no answer.
     *
     * @var string[]
     */
    private $capturedOrderIds = [];

    /**
     * @var \Magento\Framework\Notification\NotifierInterface
     */
    private $notifier;

    /**
     * CheckoutSessionManagement constructor.
     *
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Quote\Model\QuoteIdMaskFactory $quoteIdMaskFactory
     * @param \Magento\Quote\Api\CartManagementInterface $cartManagement
     * @param \Magento\Quote\Api\CartRepositoryInterface $cartRepository
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository
     * @param \Magento\Customer\Model\CustomerRegistry $customerRegistry
     * @param \Magento\Framework\Encryption\Encryptor $encryptor
     * @param \Magento\Sales\Api\OrderPaymentRepositoryInterface $paymentRepository
     * @param \Magento\Framework\Validator\Factory $validatorFactory ,
     * @param \Magento\Directory\Model\ResourceModel\Country\CollectionFactory $countryCollectionFactory ,
     * @param \Magento\Checkout\Model\Session $magentoCheckoutSession
     * @param \Amazon\Pay\Domain\AmazonAddressFactory $amazonAddressFactory ,
     * @param \Amazon\Pay\Helper\Address $addressHelper ,
     * @param \Amazon\Pay\Helper\Data $amazonHelper
     * @param AmazonConfig $amazonConfig
     * @param Adapter\AmazonPayAdapter $amazonAdapter
     * @param AsyncManagement $asyncManagement
     * @param \Amazon\Pay\Model\AsyncManagement\Charge $asyncCharge
     * @param \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdConverter
     * @param PaymentTokenRepositoryInterface $paymentTokenRepository
     * @param PaymentTokenFactory $paymentTokenFactory
     * @param PaymentTokenManagementInterface $paymentTokenManagement
     * @param \Amazon\Pay\Model\Subscription\SubscriptionManager $subscriptionManager
     * @param CustomerHelper $customerHelper
     * @param Matcher $matcher
     * @param TokenModelFactory $tokenModelFactory
     * @param UserContext $userContext
     * @param \Amazon\Pay\Logger\Logger $logger
     * @param Session $session
     * @param Translate $translationRenderer
     * @param UpdateCouponUsages $updateCouponUsages
     * @param OpenInvoiceCanceller|null $openInvoiceCanceller
     * @param \Magento\Framework\Notification\NotifierInterface|null $notifier
     */
    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Quote\Model\QuoteIdMaskFactory $quoteIdMaskFactory,
        \Magento\Quote\Api\CartManagementInterface $cartManagement,
        \Magento\Quote\Api\CartRepositoryInterface $cartRepository,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Customer\Model\CustomerRegistry $customerRegistry,
        \Magento\Framework\Encryption\Encryptor $encryptor,
        \Magento\Sales\Api\OrderPaymentRepositoryInterface $paymentRepository,
        \Magento\Framework\Validator\Factory $validatorFactory,
        \Magento\Directory\Model\ResourceModel\Country\CollectionFactory $countryCollectionFactory,
        \Magento\Checkout\Model\Session $magentoCheckoutSession,
        \Amazon\Pay\Domain\AmazonAddressFactory $amazonAddressFactory,
        \Amazon\Pay\Helper\Address $addressHelper,
        \Amazon\Pay\Helper\Data $amazonHelper,
        \Amazon\Pay\Model\AmazonConfig $amazonConfig,
        \Amazon\Pay\Model\Adapter\AmazonPayAdapter $amazonAdapter,
        \Amazon\Pay\Model\AsyncManagement $asyncManagement,
        \Amazon\Pay\Model\AsyncManagement\Charge $asyncCharge,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdConverter,
        PaymentTokenRepositoryInterface $paymentTokenRepository,
        PaymentTokenFactory $paymentTokenFactory,
        PaymentTokenManagementInterface $paymentTokenManagement,
        \Amazon\Pay\Model\Subscription\SubscriptionManager $subscriptionManager,
        CustomerHelper $customerHelper,
        Matcher $matcher,
        TokenModelFactory $tokenModelFactory,
        UserContext $userContext,
        \Amazon\Pay\Logger\Logger $logger,
        Session $session,
        Translate $translationRenderer,
        UpdateCouponUsages $updateCouponUsages,
        ?OpenInvoiceCanceller $openInvoiceCanceller = null,
        ?\Magento\Framework\Notification\NotifierInterface $notifier = null
    ) {
        $this->storeManager = $storeManager;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->cartManagement = $cartManagement;
        $this->cartRepository = $cartRepository;
        $this->orderRepository = $orderRepository;
        $this->customerRepository = $customerRepository;
        $this->customerRegistry = $customerRegistry;
        $this->encryptor = $encryptor;
        $this->paymentRepository = $paymentRepository;
        $this->validatorFactory = $validatorFactory;
        $this->countryCollectionFactory = $countryCollectionFactory;
        $this->magentoCheckoutSession = $magentoCheckoutSession;
        $this->amazonAddressFactory = $amazonAddressFactory;
        $this->addressHelper = $addressHelper;
        $this->amazonHelper = $amazonHelper;
        $this->amazonConfig = $amazonConfig;
        $this->amazonAdapter = $amazonAdapter;
        $this->asyncManagement = $asyncManagement;
        $this->asyncCharge = $asyncCharge;
        $this->transactionRepository = $transactionRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->maskedQuoteIdConverter = $maskedQuoteIdConverter;
        $this->paymentTokenRepository = $paymentTokenRepository;
        $this->paymentTokenFactory = $paymentTokenFactory;
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->subscriptionManager = $subscriptionManager;
        $this->customerHelper = $customerHelper;
        $this->matcher = $matcher;
        $this->tokenModelFactory = $tokenModelFactory;
        $this->userContext = $userContext;
        $this->logger = $logger;
        $this->session = $session;
        $this->translationRenderer = $translationRenderer;
        $this->updateCouponUsages = $updateCouponUsages;
        $this->openInvoiceCanceller = $openInvoiceCanceller
            ?: ObjectManager::getInstance()->get(OpenInvoiceCanceller::class);
        $this->notifier = $notifier
            ?: ObjectManager::getInstance()->get(\Magento\Framework\Notification\NotifierInterface::class);
    }

    /**
     * Get Amazon checkout session info from cache or API call
     *
     * @param mixed $amazonSessionId
     * @return mixed
     */
    protected function getAmazonSession($amazonSessionId)
    {
        if (!isset($this->amazonSessions[$amazonSessionId])) {
            $this->amazonSessions[$amazonSessionId] = $this->amazonAdapter->getCheckoutSession(
                $this->storeManager->getStore()->getId(),
                $amazonSessionId
            );
        }
        return $this->amazonSessions[$amazonSessionId];
    }

    /**
     * True if module is enabled, active, and cart contains no restricted products
     *
     * @param CartInterface $quote
     * @return bool
     */
    protected function canCheckoutWithAmazon($quote)
    {
        return $this->amazonConfig->isEnabled() &&
            !$this->amazonHelper->hasRestrictedProducts($quote);
    }

    /**
     * Check if quote ID is associated with another order
     *
     * In some particular cases an error occurs in Magento where an order with the same quoteId
     * is duplicated in a very short time difference.
     * This method checks if there is already an order created for that particular Quote.
     * https://github.com/magento/magento2/issues/13952
     *
     * @param CartInterface $quote
     * @return bool
     */
    protected function canSubmitQuote($quote)
    {
        if (!$quote->getIsActive()) {
            return false;
        }

        $orderCollection = $this->orderCollectionFactory->create()
            ->addFieldToSelect('increment_id')
            ->addFieldToFilter('quote_id', ['eq' => $quote->getId()])
            ->addFieldToFilter('status', ['neq' => \Magento\Sales\Model\Order::STATE_CANCELED]);

        return ($orderCollection->count() == 0);
    }

    /**
     * Get Amazon address data from checkout session
     *
     * @param mixed $amazonSessionId
     * @param bool $isShippingAddress
     * @param mixed $addressDataExtractor
     * @return mixed
     */
    protected function fetchAddress($amazonSessionId, $isShippingAddress, $addressDataExtractor)
    {
        $result = false;

        $session = $this->getAmazonSession($amazonSessionId);

        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        $addressData = call_user_func($addressDataExtractor, $session);
        if (!empty($addressData)) {
            $addressData['state'] = $addressData['stateOrRegion'];
            $addressData['phone'] = $addressData['phoneNumber'];

            $address = array_combine(
                array_map('ucfirst', array_keys($addressData)),
                array_values($addressData)
            );

            $result = $this->convertToMagentoAddress($address, $isShippingAddress);
            $result[0]['email'] = $session['buyer']['email'];
        }

        return $result;
    }

    /**
     * Format Amazon address data as Magento address
     *
     * @param array $address
     * @param boolean $isShippingAddress
     * @return array
     */
    protected function convertToMagentoAddress(array $address, $isShippingAddress = false)
    {
        $amazonAddress  = $this->amazonAddressFactory->create(['address' => $address]);
        $magentoAddress = $this->addressHelper->convertToMagentoEntity($amazonAddress);

        if ($isShippingAddress) {
            $countryCollection = $this->countryCollectionFactory->create();

            $collectionSize = $countryCollection->loadByStore()
                ->addFieldToFilter('country_id', ['eq' => $magentoAddress->getCountryId()])
                ->setPageSize(1)
                ->setCurPage(1)
                ->getSize();

            if (1 != $collectionSize) {
                throw new WebapiException(__('The store doesn\'t support the country that was entered. ' .
                    'To review allowed countries, go to General > General > Allow Countries list. Enter ' .
                    'a supported country and try again. '));
            }
        }

        return [$this->addressHelper->convertToArray($magentoAddress)];
    }

    /**
     * @inheritdoc
     */
    public function getConfig($cartId = null)
    {
        $result = [];
        $quote = $this->session->getQuoteFromIdOrSession($cartId);

        if ($this->canCheckoutWithAmazon($quote)) {
            $loginButtonPayload = $this->amazonAdapter->generateLoginButtonPayload();
            $checkoutButtonPayload = $this->amazonAdapter->generateCheckoutButtonPayload($quote);
            $config = [
                'merchant_id' => $this->amazonConfig->getMerchantId(),
                'currency' => $this->amazonConfig->getCurrencyCode(),
                'button_color' => $this->amazonConfig->getButtonColor(),
                'language' => $this->amazonConfig->getLanguage(),
                'sandbox' => $this->amazonConfig->isSandboxEnabled(),
                'login_payload' => $loginButtonPayload,
                'login_signature' => $this->amazonAdapter->signButton($loginButtonPayload),
                'checkout_payload' => $checkoutButtonPayload,
                'checkout_signature' => $this->amazonAdapter->signButton($checkoutButtonPayload),
                'public_key_id' => $this->amazonConfig->getPublicKeyId(),
            ];

            if ($quote) {
                // Ensure the totals are up to date, in case the checkout does something to update qty or shipping
                // without collecting totals
                $quote->collectTotals();
                $config['pay_only'] = $this->amazonHelper->isPayOnly($quote);

                $payNowButtonPayload = $this->amazonAdapter->generatePayNowButtonPayload(
                    $quote,
                    $this->amazonConfig->getPaymentAction()
                );
                $config['paynow_payload'] = $payNowButtonPayload;
                $config['paynow_signature'] = $this->amazonAdapter->signButton($payNowButtonPayload);
            }

            $result[] = $config;
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function getShippingAddress($amazonSessionId)
    {
        $result =  $this->fetchAddress($amazonSessionId, true, function ($session) {
            return $session['shippingAddress'] ?? [];
        });

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function getBillingAddress($amazonSessionId)
    {
        $result = $this->fetchAddress($amazonSessionId, false, function ($session) {
            return $session['billingAddress'] ?? [];
        });

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function getPaymentDescriptor($amazonSessionId)
    {
        $session = $this->getAmazonSession($amazonSessionId);
        return $session['paymentPreferences'][0]['paymentDescriptor'] ?? '';
    }

    /**
     * @inheritDoc
     */
    public function updateCheckoutSession($amazonCheckoutSessionId, $cartId = null)
    {
        if (!$quote = $this->session->getQuoteFromIdOrSession($cartId)) {
            return [];
        }

        $result = null;
        $paymentIntent = Adapter\AmazonPayAdapter::PAYMENT_INTENT_AUTHORIZE;

        if ($this->canCheckoutWithAmazon($quote)) {
            $response = $this->amazonAdapter->updateCheckoutSession(
                $quote,
                $amazonCheckoutSessionId,
                $paymentIntent
            );
            if (!empty($response['webCheckoutDetails']['amazonPayRedirectUrl'])) {
                $result = $response['webCheckoutDetails']['amazonPayRedirectUrl'];
            } elseif (!empty($response) && $response['status'] == 404) {
                $result = ['status' => $response['reasonCode']];
            }
        }
        return $result;
    }

    /**
     * Load transaction.
     *
     * @param mixed $transactionId
     * @param \Magento\Sales\Api\Data\TransactionInterface $type
     * @return mixed
     */
    private function getTransaction($transactionId, $type = null)
    {
        $this->searchCriteriaBuilder->addFilter(Transaction::TXN_ID, $transactionId);

        if ($type) {
            $this->searchCriteriaBuilder->addFilter(Transaction::TXN_TYPE, $type);
        }

        $searchCriteria = $this->searchCriteriaBuilder->create();
        $transactionCollection = $this->transactionRepository->getList($searchCriteria);

        if (count($transactionCollection)) {
            return $transactionCollection->getFirstItem();
        } else {
            return null;
        }
    }

    /**
     * Update transaction ID associated with payment
     *
     * Swaps the checkoutSessionId that was originally stored on the sales_payment_transaction record with the
     * real payment charge (transaction) id. Also updates the payment's last transaction id to match.
     *
     * @param string $chargeId
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface $payment
     * @param mixed $transaction
     * @return void
     */
    private function updateTransactionId($chargeId, $payment, $transaction)
    {
        $transaction->setTxnId($chargeId);
        $this->transactionRepository->save($transaction);

        $payment->setLastTransId($chargeId);
        $this->paymentRepository->save($payment);

        if ($invoice = $payment->getCreatedInvoice()) {
            $invoice->setTransactionId($chargeId)->save();
        }
    }

    /**
     * Set order as pending review
     *
     * @param Payment $payment
     */
    private function setPending($payment)
    {
        $order = $payment->getOrder();
        $payment->setIsTransactionPending(true);
        $order->setState(\Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW)->setStatus(
            \Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW
        );
        $this->orderRepository->save($order);
    }

    /**
     * Get the state of the charge after a capture
     *
     * A final state read from the charge is used. Otherwise the answer to the capture is used when there is one,
     * because a charge that is read right after the capture can still show the state from before the capture.
     *
     * @param array $amazonCharge
     * @param string $captureState state from the capture answer; empty when there was no capture or no answer
     * @return string empty when the state is not known
     */
    private function getChargeStateAfterCapture($amazonCharge, $captureState)
    {
        $chargeState = $amazonCharge['statusDetails']['state'] ?? '';
        if ($captureState && !in_array($chargeState, ['Captured', 'Declined', 'Canceled'])) {
            return $captureState;
        }

        return $chargeState;
    }

    /**
     * Keep the order in payment review until the capture has a final state; no invoice is paid
     *
     * @param Payment $payment
     * @param Transaction $transaction
     * @return void
     */
    private function setCapturePending($payment, $transaction)
    {
        $payment->setIsTransactionClosed(false);
        $this->setPending($payment);
        $transaction->setIsClosed(false);
    }

    /**
     * Queue a charge whose capture has no final state yet
     *
     * This runs as the last step of a checkout that did not fail, so that a canceled order is never queued. The async
     * process marks the invoice paid when Amazon reports the charge as captured, cancels the order when the charge is
     * declined, and captures an authorized charge again.
     *
     * @param OrderInterface $order
     * @param string $chargeId
     * @param string $chargeState
     * @return void
     */
    private function queueUnfinishedCapture($order, $chargeId, $chargeState)
    {
        if ($chargeState != 'CaptureInitiated') {
            $this->logger->error(
                'Amazon did not confirm the capture of charge ' . $chargeId . ' of Order #' . $order->getIncrementId()
                . ' (charge state: ' . ($chargeState ?: 'unknown') . '). The order stays in payment review and the'
                . ' charge is queued for the async process'
            );
            $order->addStatusHistoryComment(
                __(
                    'Amazon did not confirm the capture of charge %1 (charge state: %2). The order stays in payment'
                    . ' review until the async process gets the final state of the charge.',
                    $chargeId,
                    $chargeState ?: __('unknown')
                )
            );
            $this->orderRepository->save($order);
            $this->notifyAdmin(
                __('Amazon Pay capture not confirmed'),
                __(
                    'Amazon did not confirm the capture of charge %1 of Order #%2. The order stays in payment'
                    . ' review and the async process checks the charge again. If the order then goes to processing'
                    . ' without a paid invoice, the payment is only authorized: capture it before you ship the order.',
                    $chargeId,
                    $order->getIncrementId()
                )
            );
        }
        $this->asyncManagement->queuePendingAuthorization($chargeId);
    }

    /**
     * Add a notice to the admin inbox; a failure is only logged
     *
     * @param \Magento\Framework\Phrase|string $title
     * @param \Magento\Framework\Phrase|string $description
     * @return void
     */
    private function notifyAdmin($title, $description)
    {
        try {
            $this->notifier->addNotice($title, $description);
        } catch (\Exception $e) {
            $this->logger->error('Unable to add the admin notice "' . $title . '": ' . $e->getMessage());
        }
    }

    /**
     * Set order as processing
     *
     * @param Payment $payment
     * @param bool $payInvoice
     * @return void
     */
    protected function setProcessing($payment, $payInvoice = true)
    {
        $order = $payment->getOrder();
        $payment->setIsTransactionPending(false);
        $invoiceCollection = $order->getInvoiceCollection();
        if (!empty($invoiceCollection->getItems()) && $payInvoice) {
            $invoiceCollection->getFirstItem()->pay();
        }
        $state = \Magento\Sales\Model\Order::STATE_PROCESSING;
        $status = $order->getConfig()->getStateDefaultStatus($state);
        $order->setState($state)->setStatus($status);
        $this->orderRepository->save($order);
    }

    /**
     * Add capture comment to order
     *
     * @param Payment $payment
     * @param mixed $chargeId
     * @return void
     */
    protected function addCaptureComment($payment, $chargeId)
    {
        $order = $payment->getOrder();
        $formattedAmount = $order->getBaseCurrency()->formatTxt($order->getBaseGrandTotal());
        if ($order->getBaseCurrencyCode() != $order->getOrderCurrencyCode()) {
            $formattedAmount = $formattedAmount . ' [' . $order->formatPriceTxt($payment->getAmountOrdered()) . ']';
        }
        $paymentAction = $this->amazonConfig->getPaymentAction(
            ScopeInterface::SCOPE_STORE,
            $order->getStoreId()
        );
        if ($paymentAction == PaymentAction::AUTHORIZE_AND_CAPTURE) {
            $message = __('Captured amount of %1 online.', $formattedAmount);
        } else {
            $message = __('Authorized amount of %1.', $formattedAmount);
        }
        $payment->addTransactionCommentsToOrder($chargeId, $message);
        $this->orderRepository->save($order);
    }

    /**
     * Cancel order
     *
     * @param OrderInterface $order
     * @param CartInterface $quote
     * @param string $reasonMessage
     * @return void
     */
    public function cancelOrder($order, $quote = null, $reasonMessage = '')
    {
        if (!$quote) {
            $quote = $this->getQuote($order);
        }

        // The money was captured. A canceled order cannot be refunded from the admin, so keep the order and
        // leave the refund decision to the merchant.
        if ($this->isPaymentCaptured($order)) {
            $this->logger->error(
                'Order #' . $order->getIncrementId() . ' was not canceled because its payment is already '
                . 'captured. Cancel reason: ' . $reasonMessage
            );
            $order->addStatusHistoryComment(
                __('The order was not canceled because its payment is already captured. %1', $reasonMessage)
            );
            $order->save();
            return;
        }

        // Cancel unpaid invoices first. This takes the invoiced quantities and totals off the order; items that
        // still count as invoiced are not canceled below and their inventory reservations are not released.
        // Invoice::cancel() sets the order to processing, so it has to run before the order is set to canceled.
        foreach ($order->getInvoiceCollection() as $invoice) {
            if (!$invoice->canCancel()) {
                continue;
            }
            $this->openInvoiceCanceller->cancel($invoice);
            // the order save does not cascade to invoices, so register it to be saved with the order
            $order->addRelatedObject($invoice);
        }

        // set order as cancelled
        $order->setState(\Magento\Sales\Model\Order::STATE_CANCELED)->setStatus(
            \Magento\Sales\Model\Order::STATE_CANCELED
        );
        // cancel associated items to account for inventory reservations
        foreach ($order->getAllItems() as $item) {
            $item->cancel();
        }
        $order->getPayment()->setIsTransactionClosed(true);

        // decrement coupon usages if applicable
        $this->updateCouponUsages->execute($order, false);

        if (!$reasonMessage) {
            $reasonMessage = __('Something went wrong. Choose another payment method for checkout and try again.');
        }

        $order->addStatusHistoryComment($reasonMessage);

        $order->save();

        if ($this->subscriptionManager->hasSubscription($quote)) {
            $this->subscriptionManager->cancel($order);
        }
    }

    /**
     * Whether the payment of the order was captured, or may have been captured
     *
     * The invoice is marked as paid only after the capture at Amazon, so a capture made in this request counts
     * even while the invoice is still open. So does a capture that Amazon started but has not finished, and a
     * capture that gave no answer, because the money may be taken.
     *
     * @param OrderInterface $order
     * @return bool
     */
    private function isPaymentCaptured($order)
    {
        return isset($this->capturedOrderIds[$order->getId()]) || $this->hasPaidInvoice($order);
    }

    /**
     * Return result array with failure flag and message
     *
     * @param string $message
     * @param string $logEntryDetails
     * @return array
     */
    protected function handleCompleteCheckoutSessionError($message, $logEntryDetails = '')
    {
        $this->logger->error($message . ' ' . $logEntryDetails);
        if ($message == $this::ADDRESS_CHANGED_CHECKOUT_ERROR_MESSAGE) {
            $message = $this::GENERIC_COMPLETE_CHECKOUT_ERROR_MESSAGE;
        }
        $result = [
            'success' => false,
            'message' => $this->getTranslationString($message)
        ];
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function completeCheckoutSession($amazonSessionId, $cartId = null, $orderId = null)
    {
        if (!$amazonSessionId) {
            return $this->handleCompleteCheckoutSessionError(
                self::GENERIC_COMPLETE_CHECKOUT_ERROR_MESSAGE,
                'Missing AmazonSessionId.'
            );
        }

        if (!$orderId) {
            $orderResult = $this->placeOrCollectOrder($amazonSessionId, $cartId);
            if (!$orderResult['success']) {
                return $orderResult;
            }
            $orderId = $orderResult['order_id'] ?? null;
            if (!$orderId) {
                throw new OrderFailureException('Missing order_id');
            }
        }

        $result = [
            'success' => false
        ];

        try {
            $order = $this->orderRepository->get($orderId);
            $quote = $this->getQuote($order);

            // @TODO: associate token with payment?
            $result['order_id'] = $orderId;
            $result['increment_id'] = $order->getIncrementId();
            // Order is canceled on failure
            $amazonCheckoutResult = $this->completeAmazonCheckoutSession($amazonSessionId, $order, $quote);
            if (!$amazonCheckoutResult['success']) {
                return $amazonCheckoutResult;
            }

            // Order is canceled on failure
            $paymentResult = $this->handlePayment($amazonSessionId, $amazonCheckoutResult, $order, $quote);
            if (!$paymentResult['success']) {
                return $paymentResult;
            }

            $result['success'] = true;

        } catch (\Exception $e) {
            if (isset($order) && $this->isPaymentCaptured($order)) {
                // The buyer has already paid: keep the charge permission, because refunds need it, and do not put
                // the quote of the placed order back in the cart, because it cannot be submitted again
                $this->logger->error(
                    'Checkout of Order #' . $order->getIncrementId() . ' failed after the capture was sent to Amazon, '
                    . 'the order is kept. Error: ' . $e->getMessage()
                );
            } elseif (isset($order)) {
                $this->closeChargePermission($amazonSessionId, $order, $e);
                $session = $this->getAmazonSession($amazonSessionId);
                $cancelledMessage = $this->getCanceledMessage($session);
                $this->cancelOrder($order, $quote, $cancelledMessage);
                $this->magentoCheckoutSession->restoreQuote();
            }

            throw $e;
        }
        return $result;
    }

    /**
     * Collect quote, check amazon checkout session, place order
     *
     * @param string $amazonSessionId
     * @param string $quoteId
     * @return array|false[]|true[]
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function placeOrder($amazonSessionId, $quoteId = null)
    {
        // verify the shipping address has not been modified in Magento, it must match
        // the one selected in the Amazon checkout session (express checkout only)
        if ($this->isExpressCheckoutFlow()) {
            if ($amznShippingAddress = $this->getShippingAddress($amazonSessionId)) {
                $amazonAddress = $amznShippingAddress[0];
                $magentoAddress = $this->session->getQuoteFromIdOrSession($quoteId)->getShippingAddress();
                if (!$this->addressHelper->validateShippingIsSame($amazonAddress, $magentoAddress)) {
                    return $this->handleCompleteCheckoutSessionError(
                        self::ADDRESS_CHANGED_CHECKOUT_ERROR_MESSAGE,
                        $this->getAddressMismatchDetails($amazonAddress, $magentoAddress)
                    );
                }
            }
        }

        if (!$quote = $this->session->getQuoteFromIdOrSession($quoteId)) {
            $errorMsg = "Unable to complete Amazon Pay checkout. Quote not found.";
            if ($quoteId) {
                $errorMsg .= ' quoteId: ' . $quoteId . '.';
            }
            $this->logger->error($errorMsg);
            return [
                'success' => false,
                'message' => $this->getTranslationString('Unable to complete Amazon Pay checkout'),
            ];
        }

        if (!$this->canCheckoutWithAmazon($quote) || !$this->canSubmitQuote($quote)) {
            $this->logger->error("Unable to complete Amazon Pay checkout. Can't submit quote id: " . $quote->getId());
            return [
                'success' => false,
                'message' => $this->getTranslationString('Unable to complete Amazon Pay checkout'),
            ];
        }

        if (!$quote->getCustomer()->getId()) {
            $quote->setCheckoutMethod(\Magento\Quote\Api\CartManagementInterface::METHOD_GUEST);
        }

        // check the Amazon session one last time before placing the order
        $amazonSession = $this->amazonAdapter->getCheckoutSession(
            $quote->getStoreId(),
            $amazonSessionId
        );

        if ($amazonSession['statusDetails']['state'] == 'Canceled') {
            return [
                'success' => false,
                'message' => $this->getCanceledMessage($amazonSession),
            ];
        }

        if (empty($quote->getCustomerEmail())) {
            $email = null;
            if (isset($amazonSession['buyer']) && is_array($amazonSession['buyer'])) {
                $email = $amazonSession['buyer']['email'] ?? null;
            }
            if (!$email) {
                $email = $quote->getBillingAddress() ? $quote->getBillingAddress()->getEmail() : null;
            }
            if (!$email) {
                $email = $quote->getShippingAddress() ? $quote->getShippingAddress()->getEmail() : null;
            }
            if ($email) {
                $quote->setCustomerEmail($email);
            } else {
                $this->logger->warning('Amazon Pay: buyer email missing; quote has no customer email.', ['amazonSessionId' => $amazonSessionId]);
            }
        }

        // get payment to load it in the session, so that a salesrule that relies on payment method conditions
        // can work as expected
        $payment = $quote->getPayment();

        // Some checkout flows (especially 3rd party) could get to this point without setting payment method
        if (empty($payment->getMethod())) {
            $payment->setMethod(Config::CODE);
        }

        // set amazon session id on payment object to be used in authorize
        $payment->setAdditionalInformation('amazon_session_id', $amazonSessionId);

        // collect quote totals before placing order (needed for 2.3.0 and lower)
        // https://github.com/amzn/amazon-payments-magento-2-plugin/issues/992
        $quote->collectTotals();

        $orderId = null;
        try {
            $orderId = $this->cartManagement->placeOrder($quote->getId());
        } catch (\Exception $e) {
            $errorMsg = 'Unable to place order for quoteId ' . $quote->getId();
            $this->logger->error($errorMsg . ': ' . $e->getMessage());
        }

        if (!$orderId) {
            $errorMsg = "Unable to complete Amazon Pay checkout. Unable to place order with quote id: ";
            $this->logger->error($errorMsg . $quote->getId());
            return [
                'success' => false,
                'message' => $this->getTranslationString(self::GENERIC_COMPLETE_CHECKOUT_ERROR_MESSAGE)
            ];
        }

        return [
            'success' => true,
            'order_id' => $orderId
        ];
    }

    /**
     * Get display message for failed payment
     *
     * @param mixed $amazonSession
     * @return \Magento\Framework\Phrase|mixed
     */
    public function getCanceledMessage($amazonSession)
    {
        if ($amazonSession['statusDetails']['reasonCode'] == 'BuyerCanceled') {
            return $this->getTranslationString('This transaction was cancelled. Please try again.');
        } elseif ($amazonSession['statusDetails']['reasonCode'] == 'Declined') {
            return $this->getTranslationString(
                'This transaction was declined. Please try again using a different payment method.'
            );
        }

        return $amazonSession['statusDetails']['reasonDescription'];
    }

    /**
     * Get log-friendly details of disagreeing checkout session addresses
     *
     * @param mixed $amazonAddress
     * @param \Magento\Quote\Model\Quote\Address $magentoAddress
     * @return string
     */
    protected function getAddressMismatchDetails($amazonAddress, $magentoAddress)
    {
        return 'Address from Amazon account: ' . json_encode($amazonAddress) . '; Address entered in Magento: ' .
            json_encode([
                'city' => $magentoAddress->getCity(),
                'firstname' => $magentoAddress->getFirstName(),
                'lastname' => $magentoAddress->getLastname(),
                'country_id' => $magentoAddress->getCountryId(),
                'street' => $magentoAddress->getStreet(),
                'postcode' => $magentoAddress->getPostcode(),
                'telephone' => $magentoAddress->getTelephone(),
                'region' => $magentoAddress->getRegion(),
                'region_id' => $magentoAddress->getRegionId(),
                'region_code' => $magentoAddress->getRegionCode(),
                'email' => $magentoAddress->getEmail()
            ]);
    }

    /**
     * Determine whether the checkout session was completed via Express Checkout
     *
     * @return bool
     */
    protected function isExpressCheckoutFlow()
    {
        $stack = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);
        return !empty(array_filter($stack, function ($entry) {
            return isset($entry['class'])
                && $entry['class'] === 'Amazon\\Pay\\Controller\\Checkout\\PlaceOrder\\Interceptor';
        }));
    }

    /**
     * Update vault token
     *
     * @param string $amazonSessionId
     * @param string $chargePermissionId
     * @param CartInterface $quote
     * @param OrderInterface $order
     * @return void
     */
    protected function updateVaultToken($amazonSessionId, $chargePermissionId, $quote, $order)
    {
        if ($this->amazonConfig->isVaultEnabled() && $this->subscriptionManager->hasSubscription($quote)) {
            $token = $this->paymentTokenManagement->getByGatewayToken(
                $amazonSessionId,
                Config::CODE,
                $order->getCustomerId()
            );
            if ($token) {
                $token->setGatewayToken($chargePermissionId);
                $token->setIsVisible(true);
                $this->paymentTokenRepository->save($token);
            } else {
                $message = "Unable to update vault token. amazonSessionId: " . $amazonSessionId
                    . ' Customer Id: ' . $order->getCustomerId();
                $this->logger->debug($message);
            }
        }
    }

    /**
     * Get a translated string to return through the web API
     *
     * @param string $message
     * @return string
     */
    private function getTranslationString($message)
    {
        try {
            $translation = $this->translationRenderer->render([$message], []);
        } catch (\Exception $e) {
            $translation = $message;
        }

        return $translation;
    }

    /**
     * Sign in customer to Magento store via Amazon Sign In
     *
     * @param mixed $buyerToken
     * @return mixed
     */
    public function signIn($buyerToken)
    {
        if (!$this->amazonConfig->isLwaEnabled()) {
            $result = [
                'success' => false,
                'message' => __('Amazon Sign-in is disabled')
            ];

            return [$result];
        }

        try {
            $buyerInfo = $this->amazonAdapter->getBuyer($buyerToken);
            $amazonCustomer = $this->getAmazonCustomer($buyerInfo);

            if ($amazonCustomer) {
                $customer = $this->processAmazonCustomer($amazonCustomer);
                if ($customer && $customer instanceof \Magento\Customer\Model\Data\Customer) {
                    $customerToken = $this
                        ->tokenModelFactory
                        ->create()
                        ->createCustomerToken($customer->getId())
                        ->getToken();
                    $result = [
                        'success' => true,
                        'customer_id' => $customer->getId(),
                        'customer_email' => $customer->getEmail(),
                        'customer_firstname' => $customer->getFirstName(),
                        'customer_last' => $customer->getLastName(),
                        'customer_bearer_token' => $customerToken
                    ];

                } else {
                    // Magento customer exists with same email used for Sign in with Amazon
                    $result = [
                        'success' => false,
                        'customer_email' => $customer->getEmail(),
                        'message' => __('A shop account for this email address already exists. Please enter your ' .
                            'shop accounts password to log in without leaving the shop.')
                    ];
                }

            } else {
                $result = $this->getBuyerIdError($buyerToken);
            }

        } catch (\Exception $e) {
            $result = $this->getLoginError($e);
        }

        return [$result];
    }

    /**
     * Match amazon customer data to existing store customer or create new account
     *
     * @param AmazonCustomerInterface $amazonCustomer
     * @return \Amazon\Pay\Api\Data\AmazonCustomerInterface|\Magento\Customer\Api\Data\CustomerInterface|null
     */
    protected function processAmazonCustomer(AmazonCustomerInterface $amazonCustomer)
    {
        $customerData = $this->matcher->match($amazonCustomer);

        if (null === $customerData) {
            return $this->customerHelper->createCustomer($amazonCustomer);
        }

        if ($amazonCustomer->getId() != $customerData->getExtensionAttributes()->getAmazonId()) {
            return $amazonCustomer;
        }

        return $customerData;
    }

    /**
     * Get customer from Amazon buyerInfo
     *
     * @param mixed $buyerInfo
     * @return \Amazon\Pay\Domain\AmazonCustomer|bool
     */
    protected function getAmazonCustomer($buyerInfo)
    {
        return $this->customerHelper->getAmazonCustomer($buyerInfo);
    }

    /**
     * Get sign in errors related to empty buyer ID
     *
     * @param string $buyerToken
     * @return array
     */
    protected function getBuyerIdError($buyerToken)
    {
        $this->logger->error('Amazon buyerId is empty. Token: ' . $buyerToken);
        return [
            'success' => false,
            'message' => __('Amazon buyerId is empty')
        ];
    }

    /**
     * Get general errors associated with sign in failure
     *
     * @param \Exception $e
     * @return array
     */
    protected function getLoginError($e)
    {
        $this->logger->error('An error occurred while matching your Amazon account with ' .
            'your store account. : ' . $e->getMessage());
        return [
            'success' => false,
            'message' => __($e->getMessage())
        ];
    }

    /**
     * Link an amazon_customer to a Magento customer
     *
     * @param mixed $buyerToken
     * @param string $password
     * @return mixed
     */
    public function setCustomerLink($buyerToken, $password)
    {
        try {
            $buyerInfo = $this->amazonAdapter->getBuyer($buyerToken);
            $amazonCustomer = $this->getAmazonCustomer($buyerInfo);

            if ($amazonCustomer) {
                $magentoCustomer = $this->customerRepository->get($amazonCustomer->getEmail());
                $customerSecure = $this->customerRegistry->retrieveSecureData($magentoCustomer->getId());
                $hash = $customerSecure->getPasswordHash() ?? '';

                if ($this->encryptor->validateHash($password, $hash)) {
                    $this->customerHelper->updateCustomerLink($magentoCustomer->getId(), $amazonCustomer->getId());
                    return $this->signIn($buyerToken);
                } else {
                    return [
                        [
                            'success' => false,
                            'message' => __('The password supplied was incorrect')
                        ]
                    ];
                }
            } else {
                $result = $this->getBuyerIdError($buyerToken);
            }
        } catch (\Exception $e) {
            $result = $this->getLoginError($e);
        }

        return [$result];
    }

    /**
     * OrderId included in successful placement or collection
     *
     * @param string $amazonSessionId
     * @param string $cartId
     * @return array
     */
    private function placeOrCollectOrder($amazonSessionId, $cartId)
    {
        // If cartId passed, an order still needs to be placed
        if ($cartId) {
            try {
                $result = $this->placeOrder($amazonSessionId, $cartId);
            } catch (\Exception $e) {
                $logEntryDetails = 'amazonSessionId: ' . $amazonSessionId
                    . ' cartId: ' . $cartId
                    . ' Error: ' . $e->getMessage();
                return $this->handleCompleteCheckoutSessionError(
                    self::GENERIC_COMPLETE_CHECKOUT_ERROR_MESSAGE,
                    $logEntryDetails
                );
            }
        } else {
            try {
                $transaction = $this->getTransaction($amazonSessionId);
                // If no transaction for amazonSessionId, the order still needs placed (APB)
                if ($transaction) {
                    $result = [
                        'success' => true,
                        'order_id' => $transaction->getOrderId()
                    ];
                } else {
                    $result = $this->placeOrder($amazonSessionId);
                }
            } catch (\Exception $e) {
                $logEntryDetails =  'amazonSessionId: ' . $amazonSessionId . ' ' . $e->getMessage();
                return $this->handleCompleteCheckoutSessionError(
                    self::GENERIC_COMPLETE_CHECKOUT_ERROR_MESSAGE,
                    $logEntryDetails
                );
            }
        }
        if (!$result['success']) {
            $reason = $result['message'] ?? '';
            return $this->handleCompleteCheckoutSessionError(
                $reason,
                $reason
            );
        }

        return $result;
    }

    /**
     * Complete checkout session on amazon side
     *
     * @param string $amazonSessionId
     * @param OrderInterface $order
     * @param CartInterface $quote
     * @return array
     */
    private function completeAmazonCheckoutSession($amazonSessionId, $order, $quote)
    {
        $amazonCompleteCheckoutResult = $this->amazonAdapter->completeCheckoutSession(
            $order->getStoreId(),
            $amazonSessionId,
            $order->getGrandTotal(),
            $order->getOrderCurrencyCode()
        );

        $completeCheckoutStatus = $amazonCompleteCheckoutResult['status'] ?? '404';

        if (!preg_match('/^2\d\d$/', $completeCheckoutStatus)) {

            $session = $this->amazonAdapter->getCheckoutSession(
                $order->getStoreId(),
                $amazonSessionId
            );

            $cancelledMessage = $this->getCanceledMessage($session);

            // Something went wrong, but the order has already been placed, so cancelling it
            $this->cancelOrder($order, $quote, $cancelledMessage);
            $this->magentoCheckoutSession->restoreQuote();

            if (isset($session['chargePermissionId'])) {
                $this->amazonAdapter->closeChargePermission(
                    $order->getStoreId(),
                    $session['chargePermissionId'],
                    'Canceled due to checkout session failed to complete',
                    true
                );
            }

            if (!$cancelledMessage) {
                $cancelledMessage = 'Something went wrong. Choose another payment method for checkout and try again.';
            }

            return $this->handleCompleteCheckoutSessionError(
                $cancelledMessage,
                'Order cancelled due to Amazon checkout session failure: ' . $amazonCompleteCheckoutResult['message']
            );
        }

        return [
            'success' => true,
            'amazonCompleteCheckoutResult' => $amazonCompleteCheckoutResult
        ];
    }

    /**
     * Validate checkout success and handle based on state
     *
     * @param string $amazonSessionId
     * @param array $amazonCheckoutResult
     * @param OrderInterface $order
     * @param CartInterface $quote
     * @return array
     */
    private function handlePayment($amazonSessionId, $amazonCheckoutResult, $order, $quote)
    {
        try {
            // $amazonCheckoutResult holds success flag and actual result from api call
            $amazonCompleteCheckoutResult = $amazonCheckoutResult['amazonCompleteCheckoutResult'];
            $payment = $order->getPayment();
            $chargeId = $amazonCompleteCheckoutResult['chargeId'];
            $transaction = $this->getTransaction($amazonCompleteCheckoutResult['checkoutSessionId']);
            $completeCheckoutStatus = $amazonCompleteCheckoutResult['status'] ?? '404';

            $paymentAction = $this->amazonConfig->getPaymentAction(
                ScopeInterface::SCOPE_STORE,
                $order->getStoreId()
            );

            $authorizationMode = $this->amazonConfig->getAuthorizationMode();
            $captureAttempted = false;
            $captureState = '';
            $invoicePaid = false;
            $unfinishedCaptureState = null;
            if ($completeCheckoutStatus != '202' && $paymentAction == PaymentAction::AUTHORIZE_AND_CAPTURE) {
                $captureAttempted = true;
                // capture on Amazon Pay; an error answer is returned, not thrown, and has no charge state
                try {
                    $captureResponse = $this->amazonAdapter->captureCharge(
                        $order->getStoreId(),
                        $chargeId,
                        $order->getGrandTotal(),
                        $order->getOrderCurrencyCode()
                    );
                    if (in_array($captureResponse['status'] ?? null, [200, 201])) {
                        $captureState = $captureResponse['statusDetails']['state'] ?? '';
                    }
                } catch (\Exception $captureException) {
                    // no answer: the capture may still have been made, so the order is kept like a captured one
                    // until the charge state read below shows otherwise
                    $this->capturedOrderIds[$order->getId()] = self::CAPTURE_STATE_UNKNOWN;
                    $this->logger->error(
                        'Capture of charge ' . $chargeId . ' of Order #' . $order->getIncrementId()
                        . ' gave no answer: ' . $captureException->getMessage()
                    );
                }
                // remember the capture: if a later step fails, the order must not be canceled
                if (in_array($captureState, ['Captured', 'CaptureInitiated'])) {
                    $this->capturedOrderIds[$order->getId()] = $captureState;
                }
                // capture and invoice on the Magento side, only when Amazon confirms that the money is taken
                if ($captureState == 'Captured' && $authorizationMode == AuthorizationMode::SYNC) {
                    $this->setProcessing($payment, false);
                    if (!$this->asyncCharge->capture($order, $chargeId, $order->getGrandTotal())) {
                        // recovered below: the charge is queued so that the invoice is marked paid later
                        throw new \Magento\Framework\Exception\LocalizedException(
                            __('The invoice could not be marked paid after the capture')
                        );
                    }
                    $invoicePaid = true;
                }
            }
            $amazonCharge = $this->amazonAdapter->getCharge($order->getStoreId(), $chargeId);
            $chargePermissionId = $amazonCharge['chargePermissionId']
                ?? $amazonCompleteCheckoutResult['chargePermissionId'];

            // @TODO: for recurring, the order incremenet ID needs to be updated on the charge
            //Send merchantReferenceId to Amazon
            $this->amazonAdapter->updateChargePermission(
                $order->getStoreId(),
                $chargePermissionId,
                ['merchantReferenceId' => $order->getIncrementId()]
            );

            $chargeState = $this->getChargeStateAfterCapture($amazonCharge, $captureState);
            if (in_array($chargeState, ['Captured', 'CaptureInitiated'])) {
                $this->capturedOrderIds[$order->getId()] = $chargeState;
            } elseif ($chargeState !== '') {
                // the charge was not captured, for example it is still authorized or it was declined
                unset($this->capturedOrderIds[$order->getId()]);
            } elseif ($captureAttempted) {
                // the capture may have been made: keep the order if a later step fails
                $this->capturedOrderIds[$order->getId()] = self::CAPTURE_STATE_UNKNOWN;
            }

            switch ($chargeState) {
                case 'AuthorizationInitiated':
                    $payment->setIsTransactionClosed(false);
                    $this->setPending($payment);
                    $transaction->setIsClosed(false);
                    $this->asyncManagement->queuePendingAuthorization($chargeId);
                    break;
                case 'Authorized':
                    if ($captureAttempted) {
                        // the capture failed: the charge is only authorized, so the invoice must not be paid
                        $this->setCapturePending($payment, $transaction);
                        $unfinishedCaptureState = $chargeState;
                        break;
                    }
                    $this->setProcessing($payment);
                    if ($authorizationMode == AuthorizationMode::SYNC_THEN_ASYNC) {
                        $this->addCaptureComment($payment, $chargePermissionId);
                    }
                    break;
                case 'CaptureInitiated':
                    // Amazon has not finished the capture and can still decline it
                    $this->setCapturePending($payment, $transaction);
                    $unfinishedCaptureState = $chargeState;
                    break;
                case 'Captured':
                    $payment->setIsTransactionClosed(true);
                    $transaction->setIsClosed(true);
                    $this->setProcessing($payment, false);

                    // capture and invoice on the Magento side, unless that was done after the capture above;
                    // recovered below when the invoice cannot be marked paid
                    if (!$invoicePaid
                        && in_array($authorizationMode, [AuthorizationMode::SYNC, AuthorizationMode::SYNC_THEN_ASYNC])
                        && !$this->asyncCharge->capture($order, $chargeId, $quote->getGrandTotal())
                    ) {
                        throw new \Magento\Framework\Exception\LocalizedException(
                            __('The invoice could not be marked paid after the capture')
                        );
                    }
                    break;
                case 'Declined':
                case 'Canceled':
                    // no money was taken: the order is canceled below
                    throw new \Magento\Framework\Exception\LocalizedException(
                        __('The charge is %1', strtolower($chargeState))
                    );
                default:
                    if ($captureAttempted) {
                        // the result of the capture is not known, for example because the charge could not be read
                        $this->setCapturePending($payment, $transaction);
                        $unfinishedCaptureState = $chargeState;
                        break;
                    }
                    if ($chargeState === '') {
                        throw new \Magento\Framework\Exception\LocalizedException(
                            __('The state of charge %1 could not be read', $chargeId)
                        );
                    }
                    break;
            }

            // relies on updateTransactionId to save the $payment
            $payment->setAdditionalInformation(
                'charge_permission_id',
                $amazonCompleteCheckoutResult['chargePermissionId']
            );

            $this->updateTransactionId($chargeId, $payment, $transaction);
            $this->updateVaultToken(
                $amazonSessionId,
                $amazonCompleteCheckoutResult['chargePermissionId'],
                $quote,
                $order
            );

            // queued last: when a step above fails, the order is canceled or kept below, and is not queued here
            if ($unfinishedCaptureState !== null) {
                $this->queueUnfinishedCapture($order, $chargeId, $unfinishedCaptureState);
            }
            return ['success'=>true];
        } catch (\Exception $e) {
            // The buyer has already paid: finish the checkout instead of failing it
            if ($this->isPaymentCaptured($order)) {
                $this->keepCapturedOrder(
                    $amazonSessionId,
                    $order,
                    $amazonCompleteCheckoutResult['chargeId'] ?? null,
                    $amazonCompleteCheckoutResult['chargePermissionId'] ?? null,
                    $e
                );
                return ['success' => true];
            }

            $this->closeChargePermission($amazonSessionId, $order, $e);

            $session = $this->amazonAdapter->getCheckoutSession(
                $order->getStoreId(),
                $amazonSessionId
            );

            $cancelledMessage = $this->getCanceledMessage($session);
            $this->cancelOrder($order, $quote, $cancelledMessage);
            $this->magentoCheckoutSession->restoreQuote();

            $logEntryDetails = 'amazonSessionId: ' . $amazonSessionId
                . ' quoteId: ' . $quote->getId()
                . ' Error: ' . $e->getMessage();
            return $this->handleCompleteCheckoutSessionError(
                self::GENERIC_COMPLETE_CHECKOUT_ERROR_MESSAGE,
                $logEntryDetails
            );
        }
    }

    /**
     * Keep the order of a checkout whose payment was captured, or may have been, before a later step failed
     *
     * The charge permission stays open, because refunds need it, and the quote of the placed order is not restored,
     * because it cannot be submitted again. The open invoice is marked as paid when the capture is complete; if
     * that cannot be done now, the charge is queued and the async process marks it paid later.
     *
     * Nothing here throws: the buyer has paid, so the checkout must finish.
     *
     * @param string $amazonSessionId
     * @param OrderInterface $order
     * @param string|null $chargeId
     * @param string|null $chargePermissionId
     * @param \Exception $e
     * @return void
     */
    private function keepCapturedOrder($amazonSessionId, $order, $chargeId, $chargePermissionId, \Exception $e)
    {
        $this->logger->error(
            'A checkout step failed after the capture of Order #' . $order->getIncrementId() . ' was sent to Amazon'
            . ' (capture state: ' . ($this->capturedOrderIds[$order->getId()] ?? '') . '), the order is kept. Error: '
            . $e->getMessage()
        );

        // The failed step may have left changes on the order that were never saved, such as an invoice and paid totals
        // that Invoice::pay() set before an observer or a save failed. Continue with the order as it is stored, so
        // that those changes are neither taken for the real state nor saved.
        $storedOrder = $this->loadStoredOrder($order);
        if (!$storedOrder) {
            $this->logUnrecoveredOrder($order, $chargeId, $chargePermissionId);
            return;
        }
        $order = $storedOrder;

        $invoicePaid = false;
        try {
            $invoicePaid = $this->hasPaidInvoice($order);
            if ($chargeId && !$invoicePaid && ($this->capturedOrderIds[$order->getId()] ?? '') == 'Captured') {
                $this->asyncCharge->capture($order, $chargeId, $order->getGrandTotal());
                $invoicePaid = $this->hasPaidInvoice($order);
            }
        } catch (\Exception $captureException) {
            $this->logger->error(
                'Unable to mark the invoice of Order #' . $order->getIncrementId() . ' as paid: '
                . $captureException->getMessage()
            );
            // the failed capture may have left unsaved changes as well
            $storedOrder = $this->loadStoredOrder($order);
            if (!$storedOrder) {
                $this->logUnrecoveredOrder($order, $chargeId, $chargePermissionId);
                return;
            }
            $order = $storedOrder;
        }

        // Runs after the capture above: Charge::capture() finds the invoice by its transaction ID, and once that is
        // the charge ID it would work on a separate copy of the order.
        if ($chargeId) {
            $captureComplete = ($this->capturedOrderIds[$order->getId()] ?? '') == 'Captured';
            $this->saveChargeTransaction($amazonSessionId, $order, $chargeId, $captureComplete, !$invoicePaid);
        }

        try {
            if ($chargePermissionId) {
                $order->getPayment()->setAdditionalInformation('charge_permission_id', $chargePermissionId);
            }
            $order->addStatusHistoryComment(
                __(
                    'A checkout step failed after the capture was sent to Amazon, so the order was kept. Error: %1',
                    $e->getMessage()
                )
            );
            $order->save();
        } catch (\Exception $saveException) {
            $this->logger->error(
                'Unable to save Order #' . $order->getIncrementId() . ' after its checkout failed: '
                . $saveException->getMessage()
            );
        }
    }

    /**
     * Load a new instance of the order as it is stored, without the unsaved changes of the given instance
     *
     * The order repository returns the instance it already holds, so the order is loaded through a collection.
     *
     * @param OrderInterface $order
     * @return OrderInterface|null null when the order cannot be loaded
     */
    private function loadStoredOrder($order)
    {
        try {
            $storedOrder = $this->orderCollectionFactory->create()
                ->addFieldToFilter('entity_id', $order->getId())
                ->getFirstItem();
            if ($storedOrder->getId()) {
                return $storedOrder;
            }
            $this->logger->error('Unable to reload Order #' . $order->getIncrementId() . ': not found');
        } catch (\Exception $loadException) {
            $this->logger->error(
                'Unable to reload Order #' . $order->getIncrementId() . ': ' . $loadException->getMessage()
            );
        }

        return null;
    }

    /**
     * Report an order whose checkout failed after the capture and that could not be reloaded
     *
     * Its instance may hold unsaved changes, such as an invoice marked paid whose save failed, so it is neither trusted
     * nor saved. The order stays as it is stored, with the charge permission open; the invoice has to be checked by
     * hand.
     *
     * @param OrderInterface $order
     * @param string|null $chargeId
     * @param string|null $chargePermissionId
     * @return void
     */
    private function logUnrecoveredOrder($order, $chargeId, $chargePermissionId)
    {
        $this->reportUnrecoveredCapture(
            $order,
            $chargeId,
            ($this->capturedOrderIds[$order->getId()] ?? '') == 'Captured',
            'Order #' . $order->getIncrementId() . ' could not be updated after its checkout failed (capture state: '
            . ($this->capturedOrderIds[$order->getId()] ?? '') . '). Charge: ' . $chargeId . ', charge permission: '
            . $chargePermissionId
        );
    }

    /**
     * Store the charge ID on the checkout session transaction, as a completed checkout does
     *
     * Notifications and the async process find the order by the charge ID. When the invoice is still unpaid, the
     * charge is also queued, so the async process marks the invoice paid once the capture is complete.
     *
     * @param string $amazonSessionId
     * @param OrderInterface $order
     * @param string $chargeId
     * @param bool $captureComplete
     * @param bool $queueCharge
     * @return void
     */
    private function saveChargeTransaction($amazonSessionId, $order, $chargeId, $captureComplete, $queueCharge)
    {
        try {
            if (!$this->getTransaction($chargeId)) {
                $transaction = $this->getTransaction($amazonSessionId);
                if ($transaction) {
                    // as for a completed checkout: the transaction is closed once the charge is captured
                    if ($captureComplete) {
                        $transaction->setIsClosed(true);
                    }
                    $this->updateTransactionId($chargeId, $order->getPayment(), $transaction);
                }
            }
        } catch (\Exception $transactionException) {
            $this->logger->error(
                'Unable to store charge ' . $chargeId . ' on Order #' . $order->getIncrementId() . ': '
                . $transactionException->getMessage()
            );
        }

        if (!$queueCharge) {
            return;
        }

        try {
            // the async process finds the order by the charge ID, so queue the charge only when that works
            if ($this->getTransaction($chargeId)) {
                $this->asyncManagement->queuePendingAuthorization($chargeId);
                $this->logger->error(
                    'Order #' . $order->getIncrementId() . ' has no paid invoice yet, charge ' . $chargeId
                    . ' is queued for the async process'
                );
                return;
            }
            $reason = 'charge ' . $chargeId . ' could not be linked to the order';
        } catch (\Exception $queueException) {
            $reason = 'charge ' . $chargeId . ' could not be queued: ' . $queueException->getMessage();
        }

        $this->reportUnrecoveredCapture(
            $order,
            $chargeId,
            $captureComplete,
            'Order #' . $order->getIncrementId() . ' has no paid invoice and ' . $reason
        );
    }

    /**
     * Report a capture that the checkout could not record and that the async process will not check
     *
     * The report goes to the log and to the admin inbox. When the capture is complete, the order only needs a paid
     * invoice. When it is not confirmed, Amazon can still decline it, so the charge has to be checked at Amazon before
     * the order is shipped.
     *
     * @param OrderInterface $order
     * @param string|null $chargeId
     * @param bool $captureComplete
     * @param string $logMessage
     * @return void
     */
    private function reportUnrecoveredCapture($order, $chargeId, $captureComplete, $logMessage)
    {
        if ($captureComplete) {
            $this->logger->error($logMessage . ': make sure that the order has a paid invoice');
            $description = __(
                'Order #%1 was captured at Amazon (charge %2), but the checkout could not record the capture. '
                . 'Make sure that the order has a paid invoice; to create one, use Capture Offline.',
                $order->getIncrementId(),
                $chargeId
            );
        } else {
            $this->logger->error(
                $logMessage . ': the capture is not confirmed, check the charge at Amazon before you ship the order'
            );
            $description = __(
                'Amazon did not confirm the capture of Order #%1 (charge %2), and the checkout could not record it. '
                . 'Check the charge at Amazon before you ship the order: when it is captured, make sure that the '
                . 'order has a paid invoice (to create one, use Capture Offline); when it is declined, cancel the '
                . 'order.',
                $order->getIncrementId(),
                $chargeId
            );
        }
        $this->notifyAdmin(__('Amazon Pay capture not recorded'), $description);
    }

    /**
     * Whether the order has a paid invoice
     *
     * @param OrderInterface $order
     * @return bool
     */
    private function hasPaidInvoice($order)
    {
        foreach ($order->getInvoiceCollection() as $invoice) {
            if ($invoice->getState() == Invoice::STATE_PAID) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cleanup after an error
     *
     * @param string $amazonSessionId
     * @param OrderInterface $order
     * @param \Exception $e
     * @return void
     */
    private function closeChargePermission($amazonSessionId, OrderInterface $order, \Exception $e)
    {
        $session = $this->amazonAdapter->getCheckoutSession(
            $order->getStoreId(),
            $amazonSessionId
        );

        if (isset($session['chargePermissionId'])) {
            $this->amazonAdapter->closeChargePermission(
                $order->getStoreId(),
                $session['chargePermissionId'],
                'Canceled due to technical issue: ' . $e->getMessage(),
                true
            );
        }
    }

    /**
     * Set order status to payment review
     *
     * @param mixed $orderId
     * @return void
     */
    public function setOrderPendingPaymentReview($orderId)
    {
        try {
            if (!$orderId) {
                throw new \InvalidArgumentException('orderId missing');
            }
            $order = $this->orderRepository->get($orderId);
            // Update status to Pending Payment Review to support order placement before auth
            $payment = $order->getPayment();
            $this->setPending($payment);
        } catch (\Exception $e) {
            $this->logger->error('Unable to set payment review order status. ' . $e->getMessage());
        }
    }

    /**
     * Get order by quote
     *
     * @param OrderInterface $order
     * @return CartInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getQuote(OrderInterface $order)
    {
        $quoteId = $order->getQuoteId();
        return $this->cartRepository->get($quoteId);
    }
}
