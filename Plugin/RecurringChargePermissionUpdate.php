<?php

namespace Amazon\Pay\Plugin;

use Magento\Checkout\Model\PaymentInformationManagement;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Amazon\Pay\Model\Adapter\AmazonPayAdapter;
use Amazon\Pay\Model\Subscription\SubscriptionManager;
use Amazon\Pay\Helper\SubscriptionHelper;

class RecurringChargePermissionUpdate
{
    /**
     * @var PaymentTokenManagementInterface
     */
    private $paymentTokenManagement;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var AmazonAdapter
     */
    private $amazonAdapter;

    /**
     * @var SubscriptionManager
     */
    private $subscriptionManager;

    /**
     * @var SubscriptionHelper
     */
    private $subscriptionHelper;

    public function __construct(
        PaymentTokenManagementInterface $paymentTokenManagement,
        CartRepositoryInterface $cartRepository,
        AmazonPayAdapter $amazonAdapter,
        SubscriptionManager $subscriptionManager,
        SubscriptionHelper $subscriptionHelper
    ) {
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->cartRepository = $cartRepository;
        $this->amazonAdapter = $amazonAdapter;
        $this->subscriptionManager = $subscriptionManager;
        $this->subscriptionHelper = $subscriptionHelper;
    }

    /**
     * Check to see if a stored AP token needs to have its recurring metadata updated
     * before using it for another recurring charge.
     * 
     * @param PaymentInformationManagement $subject
     * @param  $cartId
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @return array
     */
    public function beforeSavePaymentInformationAndPlaceOrder (
        PaymentInformationManagement $subject,
        $cartId,
        \Magento\Quote\Api\Data\PaymentInterface $paymentMethod,
        \Magento\Quote\Api\Data\AddressInterface $billingAddress = null
    ) {
        if ($paymentMethod->getMethod() === 'amazon_payment_v2_vault') {
            $quote = $this->cartRepository->getActive($cartId);

            if ($this->subscriptionManager->hasSubscription($quote)) {
                $customerId = $quote->getBillingAddress()
                    ->getCustomerId();

                $chargePermissionId = $this->paymentTokenManagement
                    ->getByPublicHash($paymentMethod->getAdditionalData()['public_hash'], $customerId)
                    ->getGatewayToken();

                $chargePermission = $this->amazonAdapter->getChargePermission($quote->getStoreId(), $chargePermissionId);
                $newFrequency = $this->amazonAdapter->getRecurringMetadata($quote)['frequency'];
                $oldFrequency = $chargePermission['recurringMetadata']['frequency'];
                if ($this->subscriptionHelper->hasShorterFrequency($newFrequency, $oldFrequency)) {
                    if (!$quote->getReservedOrderId()) {
                        try {
                            $quote->reserveOrderId()->save();
                        } catch (\Exception $e) {
                            $this->logger->debug($e->getMessage());
                        }
                    }

                    $payload = [
                        'merchantReferenceId' => $quote->getReservedOrderId(),
                        'recurringMetadata' => $newFrequency
                    ];

                    $this->amazonAdapter->updateChargePermission(
                        $quote->getStoreId(),
                        $chargePermissionId,
                        $payload
                    );
                }
            }
        }
        
        return [$cartId, $paymentMethod, $billingAddress];
    }
}
