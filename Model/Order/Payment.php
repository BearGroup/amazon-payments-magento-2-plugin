<?php

namespace Amazon\Pay\Model\Order;

use Amazon\Pay\Model\AmazonConfig;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Config\Scope;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Registry;
use Magento\Payment\Helper\Data;
use Magento\Sales\Api\CreditmemoManagementInterface as CreditmemoManager;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Order\Payment\Operations\SaleOperation;
use Magento\Sales\Model\Order\Payment\Processor;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\ManagerInterface;
use Magento\Store\Model\ScopeInterface;

class Payment extends \Magento\Sales\Model\Order\Payment
{

    const AMAZON_PAY_METHODS =  [
        'amazon_payment_v2',
        'amazon_payment_v2_vault'
    ];

    const SELLER_CENTRAL_URL = [
        'de' => 'https://sellercentral-europe.amazon.com/external-payments/pmd/payment-details',
        'uk' => 'https://sellercentral-europe.amazon.com/external-payments/pmd/payment-details',
        'jp' => 'https://sellercentral.amazon.com/external-payments/pmd/payment-details',
        'us' => 'https://sellercentral.amazon.com/external-payments/pmd/payment-details',
        'default' => 'https://sellercentral.amazon.com/external-payments/pmd/payment-details'
    ];
    
    /**
     * @var AmazonConfig
     */
    private $amazonConfig;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param Data $paymentData
     * @param EncryptorInterface $encryptor
     * @param CreditmemoFactory $creditmemoFactory
     * @param PriceCurrencyInterface $priceCurrency
     * @param TransactionRepositoryInterface $transactionRepository
     * @param ManagerInterface $transactionManager
     * @param Transaction\BuilderInterface $transactionBuilder
     * @param Processor $paymentProcessor
     * @param OrderRepositoryInterface $orderRepository
     * @param AmazonConfig $amazonConfig
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     * @param CreditmemoManager|null $creditmemoManager
     * @param SaleOperation|null $saleOperation
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        Data $paymentData,
        EncryptorInterface $encryptor,
        CreditmemoFactory $creditmemoFactory,
        PriceCurrencyInterface $priceCurrency,
        TransactionRepositoryInterface $transactionRepository,
        ManagerInterface $transactionManager,
        Transaction\BuilderInterface $transactionBuilder,
        Processor $paymentProcessor,
        OrderRepositoryInterface $orderRepository,
        \Amazon\Pay\Model\AmazonConfig $amazonConfig,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = [],
        CreditmemoManager $creditmemoManager = null,
        SaleOperation $saleOperation = null,

    ) {
        parent::__construct($context, $registry, $extensionFactory, $customAttributeFactory, $paymentData, $encryptor, $creditmemoFactory, $priceCurrency, $transactionRepository, $transactionManager, $transactionBuilder, $paymentProcessor, $orderRepository, $resource, $resourceCollection, $data, $creditmemoManager, $saleOperation);
        $this->amazonConfig = $amazonConfig;
    }

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
        $sellerCentralBaseUrl = $this->getSellerCentralBaseUrl();
        // $txnId is a bit varying depending on the situation. Can be a session id, or the orderReferenceId with an appended status flag
        $link = '<a href="' . $sellerCentralBaseUrl . '?orderReferenceId=' . $chargePermissionId . '">' . $txnId . '</a>';
        $message .= ' ' . __('Transaction ID: "%1"', $link);
        return $message;
    }

    /**
     * @return string
     */
    private function getSellerCentralBaseUrl(): string
    {
        $paymentRegion = $this->amazonConfig->getPaymentRegion();
        if (in_array($paymentRegion, self::SELLER_CENTRAL_URL, true)) {
            return self::SELLER_CENTRAL_URL[$paymentRegion];
        }

        return self::SELLER_CENTRAL_URL['default'];
    }


}
