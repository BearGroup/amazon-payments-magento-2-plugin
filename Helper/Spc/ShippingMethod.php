<?php

namespace Amazon\Pay\Helper\Spc;

use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Api\ShippingInformationManagementInterface;
use Magento\Quote\Api\ShippingMethodManagementInterface;

class ShippingMethod
{
    /**
     * @var ShippingMethodManagementInterface
     */
    protected $shippingMethodManagement;

    /**
     * @var ShippingInformationInterface
     */
    protected $shippingInformation;

    /**
     * @var ShippingInformationManagementInterface
     */
    protected $shippingInformationManagement;

    /**
     * @param ShippingMethodManagementInterface $shippingMethodManagement
     * @param ShippingInformationInterface $shippingInformation
     * @param ShippingInformationManagementInterface $shippingInformationManagement
     */
    public function __construct(
        ShippingMethodManagementInterface $shippingMethodManagement,
        ShippingInformationInterface $shippingInformation,
        ShippingInformationManagementInterface $shippingInformationManagement
    )
    {
        $this->shippingMethodManagement = $shippingMethodManagement;
        $this->shippingInformation = $shippingInformation;
        $this->shippingInformationManagement = $shippingInformationManagement;
    }

    /**
     * @param $quote
     * @param $code
     * @return void
     */
    public function setShippingMethodOnQuote($quote, $code = false)
    {
        // Only grabbing the first one, as Magento only accepts one coupon code
        if ($quote->getShippingAddress()->validate() && $code) {
            $shippingMethodCode = $code;

            $address = $quote->getShippingAddress();

            // Save address with shipping method
            if ((strpos($shippingMethodCode, '_') !== false)) {
                $shippingInformation = $this->shippingInformation->setShippingAddress($address);

                $shippingMethod = explode('_', $shippingMethodCode);
                $shippingInformation->setShippingCarrierCode($shippingMethod[0])
                    ->setShippingMethodCode($shippingMethod[1]);

                $this->shippingInformationManagement->saveAddressInformation($quote->getId(), $shippingInformation);
            }
        }
        // Select the cheapest option
        else if ($quote->getShippingAddress()->validate()) {
            $shippingMethods = $this->shippingMethodManagement->estimateByExtendedAddress($quote->getId(), $quote->getShippingAddress());
            $cheapestMethod = [
                'carrier' => '',
                'code' => '',
                'amount' => 100000
            ];

            foreach ($shippingMethods as $method) {
                if ($method->getAmount() < $cheapestMethod['amount']) {
                    $cheapestMethod['carrier'] = $method->getCarrierCode();
                    $cheapestMethod['code'] = $method->getMethodCode();
                    $cheapestMethod['amount'] = $method->getAmount();
                }
            }

            // Save address with shipping method
            if (!empty($cheapestMethod['carrier'])) {
                $address = $quote->getShippingAddress();
                $shippingInformation = $this->shippingInformation->setShippingAddress($address);

                $shippingInformation->setShippingCarrierCode($cheapestMethod['carrier'])
                    ->setShippingMethodCode($cheapestMethod['code']);

                $this->shippingInformationManagement->saveAddressInformation($quote->getId(), $shippingInformation);
            }
        }
    }
}