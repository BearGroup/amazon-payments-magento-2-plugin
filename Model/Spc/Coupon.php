<?php

namespace Amazon\Pay\Model\Spc;

use Amazon\Pay\Api\Spc\CouponInterface;
use Amazon\Pay\Helper\Spc\CheckoutSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Phrase;
use Magento\Quote\Api\CartRepositoryInterface;
use Amazon\Pay\Helper\Spc\Cart;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Support\Model\Report\Group\Modules\Modules;

class Coupon implements CouponInterface
{
    /**
     * @var StoreInterface
     */
    protected $store;

    /**
     * @var CartRepositoryInterface
     */
    protected $cartRepository;

    /**
     * @var Cart
     */
    protected $cartHelper;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSessionHelper;

    /**
     * @var Modules
     */
    protected $modules;

    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var mixed|null
     */
    protected $giftCardAccountManagement = null;

    /**
     * @var mixed|null
     */
    protected $giftCardAccount = null;

    /**
     * @param StoreInterface $store
     * @param CartRepositoryInterface $cartRepository
     * @param Cart $cartHelper
     * @param CheckoutSession $checkoutSessionHelper
     * @param Modules $modules
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(
        StoreInterface $store,
        CartRepositoryInterface $cartRepository,
        Cart $cartHelper,
        CheckoutSession $checkoutSessionHelper,
        Modules $modules,
        ObjectManagerInterface $objectManager
    )
    {
        $this->store = $store;
        $this->cartRepository = $cartRepository;
        $this->cartHelper = $cartHelper;
        $this->checkoutSessionHelper = $checkoutSessionHelper;
        $this->modules = $modules;
        $this->objectManager = $objectManager;


        // Check it Magento's gift card account module is enabled
        if ($this->modules->isModuleEnabled('Magento_GiftCardAccount')) {
            $this->giftCardAccountManagement = $this->objectManager->create(
                \Magento\GiftCardAccount\Model\Service\GiftCardAccountManagement::class
            );

            $this->giftCardAccount = $this->objectManager->create(
                \Magento\GiftCardAccount\Api\Data\GiftCardAccountInterface::class
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function applyCoupon(int $cartId, $cartDetails = null)
    {
        // Get quote
        try {
            /** @var $quote \Magento\Quote\Model\Quote */
            $quote = $this->cartRepository->getActive($cartId);

            // Set currency on the http context
            $this->store->setCurrentCurrencyCode($quote->getQuoteCurrencyCode());
        } catch (NoSuchEntityException $e) {
            $this->cartHelper->logError('SPC Coupon: InvalidCartId. CartId: '. $cartId .' - ', $cartDetails);

            throw new \Magento\Framework\Webapi\Exception(
                new Phrase("Cart Id ". $cartId ." not found or inactive"), "InvalidCartId", 404
            );
        }

        // Get checkoutSessionId
        $checkoutSessionId = $cartDetails['checkout_session_id'] ?? null;

        // Get checkout session for verification
        if ($cartDetails && $checkoutSessionId) {
            if ($this->checkoutSessionHelper->confirmCheckoutSession($quote, $cartDetails, $checkoutSessionId)) {
                // fail flags
                $couponFailed = false;
                $giftCardFailed = false;

                // Only grabbing the first one, as Magento only accepts one coupon code
                if (isset($cartDetails['coupons'][0]['coupon_code'])) {
                    $couponCode = $cartDetails['coupons'][0]['coupon_code'];

                    // Attempt to set coupon code
                    $quote->setCouponCode($couponCode);

                    // Save cart
                    $this->cartRepository->save($quote);

                    // Check if the coupon was applied
                    if ($quote->getCouponCode() != $couponCode) {
                        $this->cartHelper->logError(
                            'SPC Coupon: CouponNotApplicable - The coupon could not be applied to the cart. CartId: ' . $cartId . ' - ', $cartDetails
                        );

                        $couponFailed = true;
                    }

                    // Attempt to set gift card with Magento's gift card features
                    if ($this->giftCardAccountManagement) {
                        try {
                            $giftCardAccount = $this->giftCardAccount;
                            $giftCardAccount->setGiftCards([$couponCode]);
                            $this->giftCardAccountManagement->saveByQuoteId($quote->getId(), $giftCardAccount);
                        } catch (\Exception $e) {
                            $giftCardFailed = true;
                        }

                        // check to see if both failed
                        if ($couponFailed && $giftCardFailed) {
                            throw new \Magento\Framework\Webapi\Exception(
                                new Phrase("The code '" . $couponCode . "' does not apply as a coupon or gift card"), "CouponNotApplicable", 400
                            );
                        }
                    }
                    // if no gift card feature, check on failure of coupon only
                    else {
                        if ($couponFailed) {
                            throw new \Magento\Framework\Webapi\Exception(
                                new Phrase("The code '" . $couponCode . "' does not apply as a coupon code"), "CouponNotApplicable", 400
                            );
                        }
                    }
                }
            }
        }

        return $this->cartHelper->createResponse($quote->getId(), $checkoutSessionId);
    }
}
