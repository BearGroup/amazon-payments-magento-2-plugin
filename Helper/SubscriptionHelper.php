<?php

namespace Amazon\Pay\Helper;

use Amazon\Pay\Model\Adapter\AmazonPayAdapter;
use Magento\Quote\Model\Quote;
use Magento\Vault\Model\PaymentToken;
use Magento\Vault\Model\PaymentTokenRepository;

class SubscriptionHelper
{
    /**
     * @var AmazonPayAdapter
     */
    private $amazonAdapter;

    /**
     * @param PaymentTokenRepository $paymentTokenRepository
     */
    private $paymentTokenRepository;

    /**
     * @param AmazonPayAdapter $amazonAdapter
     * @param PaymentTokenRepository $paymentTokenRepository
     */
    public function __construct(
        AmazonPayAdapter $amazonAdapter,
        PaymentTokenRepository $paymentTokenRepository
    ) {
        $this->amazonAdapter = $amazonAdapter;
        $this->paymentTokenRepository = $paymentTokenRepository;
    }

    public function cancelToken(Quote $quote, PaymentToken $token)
    {
        $this->amazonAdapter->closeChargePermission(
            $quote->getStoreId(),
            $token->getGatewayToken(),
            'Canceled due to cancellation of subscription by the customer.'
        );

        $token->setIsActive(false);
        $this->paymentTokenRepository->save($token);
    }
}
