<?php

namespace Amazon\Pay\Model\Spc;

use Amazon\Pay\Api\Spc\AddressInterface as SpcAddressInterface;
use Amazon\Pay\Helper\Spc\Cart;
use Amazon\Pay\Model\CheckoutSessionManagement;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Framework\Webapi\Exception as WebapiException;
use Amazon\Pay\Helper\Spc\ShippingMethod;

class Address implements SpcAddressInterface
{
    /**
     * @var CartRepositoryInterface
     */
    protected $cartRepository;

    /**
     * @var AddressInterface
     */
    protected $address;

    /**
     * @var CheckoutSessionManagement
     */
    protected $checkoutSessionManager;

    /**
     * @var Cart
     */
    protected $cartHelper;

    /**
     * @var ShippingMethod
     */
    protected $shippingMethodHelper;

    /**
     * @param CartRepositoryInterface $cartRepository
     * @param AddressInterface $address
     * @param CheckoutSessionManagement $checkoutSessionManagement
     * @param Cart $cartHelper
     * @param ShippingMethod $shippingMethodHelper
     */
    public function __construct(
        CartRepositoryInterface $cartRepository,
        AddressInterface $address,
        CheckoutSessionManagement $checkoutSessionManagement,
        Cart $cartHelper,
        ShippingMethod $shippingMethodHelper
    )
    {
        $this->cartRepository = $cartRepository;
        $this->address = $address;
        $this->checkoutSessionManager = $checkoutSessionManagement;
        $this->cartHelper = $cartHelper;
        $this->shippingMethodHelper = $shippingMethodHelper;
    }

    /**
     * @inheritdoc
     */
    public function saveAddress(int $cartId, $cartDetails = null)
    {
        // Get quote
        try {
            /** @var $quote \Magento\Quote\Model\Quote */
            $quote = $this->cartRepository->getActive($cartId);
        } catch (NoSuchEntityException $e) {
            $this->cartHelper->logError('SPC Address: InvalidCartId. CartId: '. $cartId .' - ', $cartDetails);

            throw new \Magento\Framework\Webapi\Exception(
                new Phrase('InvalidCartId'), 404, 404
            );
        }

        $checkoutSessionId = $cartDetails['checkout_session_id'] ?? null;

        // Get addresses for updating
        if ($cartDetails && $checkoutSessionId) {
            $amazonSession = $this->checkoutSessionManager->getAmazonSession($checkoutSessionId);

            $amazonSessionStatus = $amazonSession['status'] ?? '404';
            if (!preg_match('/^2\d\d$/', $amazonSessionStatus)) {
                $this->cartHelper->logError(
                    'SPC Address: '. $amazonSession['reasonCode'] .'. CartId: '. $cartId .' - ', $cartDetails
                );

                throw new WebapiException(
                    new Phrase($amazonSession['reasonCode'])
                );
            }

            if ($amazonSession['statusDetails']['state'] !== 'Open') {
                $this->cartHelper->logError(
                    'SPC Address: '. $amazonSession['statusDetails']['reasonCode'] .'. CartId: '. $cartId .' - ', $cartDetails
                );

                throw new WebapiException(
                    new Phrase($amazonSession['statusDetails']['reasonCode'])
                );
            }

            // Get and set shipping address
            $magentoAddress = $this->checkoutSessionManager->getShippingAddress($checkoutSessionId);
            if (isset($magentoAddress[0])) {
                $shippingAddress = $this->address->setData($magentoAddress[0]);
                $quote->setShippingAddress($shippingAddress);
            }
            else {
                $this->cartHelper->logError(
                    'SPC Address: InvalidRequest - No shipping address. CartId: '. $cartId .' - ', $cartDetails
                );

                throw new WebapiException(
                    new Phrase('InvalidRequest')
                );
            }
            // Get and set billing address
            $magentoAddress = $this->checkoutSessionManager->getBillingAddress($checkoutSessionId);
            if (isset($magentoAddress[0])) {
                $billingAddress = $this->address->setData($magentoAddress[0]);
                $quote->setBillingAddress($billingAddress);
            }
            else {
                $this->cartHelper->logError(
                    'SPC Address: InvalidRequest - No billing address. CartId: '. $cartId .' - ', $cartDetails
                );

                throw new WebapiException(
                    new Phrase('InvalidRequest')
                );
            }

            $this->cartRepository->save($quote);

            $this->shippingMethodHelper->setShippingMethodOnQuote($quote);
        }

        // Save and create response
        return $this->cartHelper->createResponse($quote->getId(), $checkoutSessionId);
    }
}