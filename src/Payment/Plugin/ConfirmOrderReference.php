<?php

namespace Amazon\Payment\Plugin;

use Magento\Checkout\Model\Session;
use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Amazon\Payment\Model\Adapter\AmazonPaymentAdapter;
use Amazon\Payment\Model\OrderInformationManagement;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Framework\Exception\LocalizedException;


/**
 * Class ConfirmOrderReference
 *
 * Confirm the OrderReference when payment details are saved
 */
class ConfirmOrderReference
{
    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var AmazonPaymentAdapter
     */
    private $adapter;

    /**
     * @var OrderInformationManagement
     */
    private $orderInformationManagement;

    /**
     * ConfirmOrderReference constructor.
     * @param Session $checkoutSession
     * @param AmazonPaymentAdapter $adapter
     * @param OrderInformationManagement $orderInformationManagement
     */
    public function __construct(
        Session $checkoutSession,
        AmazonPaymentAdapter $adapter,
        OrderInformationManagement $orderInformationManagement
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->adapter = $adapter;
        $this->orderInformationManagement = $orderInformationManagement;
    }

    /**
     * @param PaymentInformationManagementInterface $subject
     * @param $result
     * @param $cartId
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function afterSavePaymentInformation(
        PaymentInformationManagementInterface $subject,
        $result,
        $cartId,
        PaymentInterface $paymentMethod,
        AddressInterface $billingAddress = null
    ) {
        if($paymentMethod->getMethod() == 'amazon_payment') { //TODO: constant?
            /** @var \Magento\Quote\Model\Quote $quote */
            $quote = $this->checkoutSession->getQuote();
            $amazonOrderReferenceId = $quote
                ->getExtensionAttributes()
                ->getAmazonOrderReferenceId()
                ->getAmazonOrderReferenceId();

            $this->orderInformationManagement->saveOrderInformation($amazonOrderReferenceId);
            $response = $this->adapter->confirmOrderReference($quote->getStoreId(), $amazonOrderReferenceId);
            if(!$response) {
                //TODO: use appropriate exception class
                throw new \Exception('Unable to confirm order reference');
            }
        }

        return $result;
    }
}
